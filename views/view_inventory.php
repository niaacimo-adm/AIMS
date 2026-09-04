<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Categories & units — used by both the Add and Edit modals below
$categories = [];
$category_result = $db->query("SELECT id, name FROM categories ORDER BY name");
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
    }
}

$units = [];
$unit_result = $db->query("SELECT id, name, abbreviation FROM unit_of_measure ORDER BY name");
if ($unit_result) {
    while ($row = $unit_result->fetch_assoc()) {
        $units[] = $row;
    }
}

// ---------------------------------------------------------------------
// AJAX endpoint: create / get / update / delete an item.
// This replaces the separate new_stock.php and edit_item.php pages —
// their logic now lives here and is driven from the modals below.
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unit_of_measure = trim($_POST['unit_of_measure'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Item name is required.']);
            exit();
        }
        if (empty($unit_of_measure)) {
            echo json_encode(['success' => false, 'message' => 'Unit of measure is required.']);
            exit();
        }

        $stmt = $db->prepare("INSERT INTO items (category_id, name, description, unit_of_measure) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $category_id, $name, $description, $unit_of_measure);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item added successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding item: ' . $db->error]);
        }
        $stmt->close();
        exit();
    }

    if ($action === 'get') {
        $item_id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare(
            "SELECT i.*,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE -sm.quantity END), 0) AS current_stock
             FROM items i
             LEFT JOIN stock_movements sm ON sm.item_id = i.id
             WHERE i.id = ?
             GROUP BY i.id"
        );
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($item) {
            echo json_encode(['success' => true, 'data' => $item]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
        }
        exit();
    }

    if ($action === 'update') {
        $item_id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unit_of_measure = trim($_POST['unit_of_measure'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $adjustment_type = $_POST['adjustment_type'] ?? '';
        $adjustment_quantity = intval($_POST['adjustment_quantity'] ?? 0);
        $adjustment_reference = trim($_POST['adjustment_reference'] ?? '');
        $adjustment_notes = trim($_POST['adjustment_notes'] ?? '');

        if ($item_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item.']);
            exit();
        }
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Item name is required.']);
            exit();
        }
        if (empty($unit_of_measure)) {
            echo json_encode(['success' => false, 'message' => 'Unit of measure is required.']);
            exit();
        }
        if ($adjustment_quantity < 0) {
            echo json_encode(['success' => false, 'message' => 'Adjustment quantity cannot be negative.']);
            exit();
        }

        $db->begin_transaction();
        try {
            $update_stmt = $db->prepare(
                "UPDATE items SET category_id = ?, name = ?, description = ?, unit_of_measure = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $update_stmt->bind_param("isssi", $category_id, $name, $description, $unit_of_measure, $item_id);
            $update_stmt->execute();
            $update_stmt->close();

            if (in_array($adjustment_type, ['in', 'out'], true) && $adjustment_quantity > 0) {
                if ($adjustment_type === 'out') {
                    $avail_stmt = $db->prepare(
                        "SELECT COALESCE(SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE -quantity END), 0) AS current_stock
                         FROM stock_movements WHERE item_id = ?"
                    );
                    $avail_stmt->bind_param("i", $item_id);
                    $avail_stmt->execute();
                    $available = (int) ($avail_stmt->get_result()->fetch_assoc()['current_stock'] ?? 0);
                    $avail_stmt->close();

                    if ($adjustment_quantity > $available) {
                        $db->rollback();
                        echo json_encode([
                            'success' => false,
                            'message' => "Stock Out of {$adjustment_quantity} exceeds available stock ({$available}). Adjust the quantity or record a Stock In first.",
                        ]);
                        exit();
                    }
                }

                $movement_stmt = $db->prepare(
                    "INSERT INTO stock_movements (item_id, movement_type, quantity, reference, notes) VALUES (?, ?, ?, ?, ?)"
                );
                $movement_stmt->bind_param("isiss", $item_id, $adjustment_type, $adjustment_quantity, $adjustment_reference, $adjustment_notes);
                $movement_stmt->execute();
                $movement_stmt->close();
            }

            $db->commit();

            // Recompute stock from movement history (source of truth) and keep
            // items.current_stock in sync for any other pages that read it directly.
            $stock_stmt = $db->prepare(
                "SELECT COALESCE(SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE -quantity END), 0) AS current_stock
                 FROM stock_movements WHERE item_id = ?"
            );
            $stock_stmt->bind_param("i", $item_id);
            $stock_stmt->execute();
            $current_stock = (int) ($stock_stmt->get_result()->fetch_assoc()['current_stock'] ?? 0);
            $stock_stmt->close();

            $sync_stmt = $db->prepare("UPDATE items SET current_stock = ? WHERE id = ?");
            $sync_stmt->bind_param("ii", $current_stock, $item_id);
            $sync_stmt->execute();
            $sync_stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Item updated successfully!',
                'current_stock' => $current_stock,
            ]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Error updating item: ' . $e->getMessage()]);
        }
        exit();
    }

    if ($action === 'delete') {
        $delete_id = intval($_POST['id'] ?? 0);

        if ($delete_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item.']);
            exit();
        }

        $db->begin_transaction();
        try {
            $del_movements = $db->prepare("DELETE FROM stock_movements WHERE item_id = ?");
            $del_movements->bind_param("i", $delete_id);
            $del_movements->execute();
            $del_movements->close();

            $del_item = $db->prepare("DELETE FROM items WHERE id = ?");
            $del_item->bind_param("i", $delete_id);
            $del_item->execute();
            $del_item->close();

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Item deleted successfully!']);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Cannot delete this item — it may still be referenced elsewhere in the system.']);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}

// ---------------------------------------------------------------------
// Page render — inventory list
// ---------------------------------------------------------------------
$category_filter = $_GET['category'] ?? '';

$query = "SELECT 
            i.*, 
            c.name as category_name,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE -sm.quantity END), 0) as current_stock
          FROM items i 
          LEFT JOIN categories c ON i.category_id = c.id 
          LEFT JOIN stock_movements sm ON i.id = sm.item_id
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($category_filter) && $category_filter != 'all') {
    $query .= " AND i.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

$query .= " GROUP BY i.id ORDER BY i.name";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Inventory - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        :root {
            --h-bg:       #eef7f2;
            --h-card:     #ffffff;
            --h-card-alt: #f0faf5;
            --h-surface3: #e2f3ea;
            --h-border:   rgba(42,152,99,0.18);
            --h-text:     #0f2d1e;
            --h-muted:    #4a7a5e;
            --h-primary:  #2a9863;
            --h-primary-dim: rgba(42,152,99,.10);
            --h-accent:   #24e78f;
            --h-success:  #2a9863;
            --h-warning:  #e67700;
            --h-danger:   #c92a2a;
            --h-blue:     #2a9863;
            --h-shadow:   0 4px 24px rgba(42,152,99,.12);
            --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
        }
        body.dark-mode {
            --h-bg:       #0b1f17;
            --h-card:     #102f22;
            --h-card-alt: #0e2619;
            --h-surface3: #16352a;
            --h-border:   rgba(36,231,143,0.12);
            --h-text:     #d4f5e5;
            --h-muted:    #6aad8a;
            --h-primary:  #24e78f;
            --h-primary-dim: rgba(36,231,143,.12);
            --h-accent:   #2a9863;
            --h-success:  #24e78f;
            --h-warning:  #ffd43b;
            --h-danger:   #ff6b6b;
            --h-blue:     #24e78f;
            --h-shadow:   0 4px 24px rgba(0,0,0,.35);
            --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
        }

        .content-wrapper { background: var(--h-bg) !important; }

        .main-header.navbar, .main-header, nav.main-header, header.main-header { z-index: 1000 !important; }
        .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
        .main-sidebar, aside.main-sidebar { z-index: 999 !important; }
        .modal-backdrop { z-index: 1040 !important; }
        .modal { z-index: 1050 !important; }
        .modal-dialog { z-index: 1051 !important; }
        .modal-content { z-index: 1052 !important; }

        /* ══ HERO ══ */
        @keyframes pgHeroMeshDrift {
            0%   { transform:translate(0,0)   rotate(0deg); }
            100% { transform:translate(3%,2%) rotate(2deg); }
        }
        @keyframes pgHeroOrbFloat {
            0%,100% { opacity:.4; transform:translate(0,0)       scale(1);    }
            33%      { opacity:.7; transform:translate(18px,-26px) scale(1.05); }
            66%      { opacity:.5; transform:translate(-12px,16px) scale(.95);  }
        }
        .pg-hero { background:#0b1f17; padding:36px 28px 66px; position:relative; overflow:hidden; }
        .pg-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:pgHeroMeshDrift 22s ease-in-out infinite alternate;
            z-index:0;
        }
        .pg-hero-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .pg-orb { position:absolute; border-radius:50%; filter:blur(60px); animation:pgHeroOrbFloat 18s ease-in-out infinite; }
        .pg-orb-1 { width:280px; height:280px; background:rgba(36,231,143,.11); top:-80px;    left:-60px;  animation-duration:21s; }
        .pg-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.10);  bottom:-50px; right:-40px; animation-delay:-7s; animation-duration:17s; }
        .pg-orb-3 { width:160px; height:160px; background:rgba(212,175,55,.06); top:40%;      right:20%;   animation-delay:-13s; animation-duration:24s; }
        .pg-orb-4 { width:120px; height:120px; background:rgba(36,231,143,.07); bottom:15%;   left:15%;    animation-delay:-4s;  animation-duration:15s; }
        .pg-hero-dots {
            position:absolute; inset:0; z-index:0; pointer-events:none;
            background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px);
            background-size:36px 36px;
        }
        .pg-hero-arc {
            position:absolute; top:-50px; right:-50px; width:200px; height:200px; border-radius:50%;
            background:radial-gradient(circle,rgba(36,231,143,.18) 0%,transparent 70%);
            pointer-events:none; z-index:0;
        }
        .pg-hero::after {
            content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px;
            background:var(--body-bg, #eef7f2); clip-path:ellipse(58% 100% at 50% 100%); z-index:1;
        }
        body.dark-mode .pg-hero::after { background:var(--body-bg, #0b1f17); }
        .pg-hero-inner { position:relative; z-index:2; }
        .pg-hero-title {
            color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px;
            letter-spacing:-.3px; text-shadow:0 2px 14px rgba(0,0,0,.45);
            display:flex; align-items:center; gap:10px;
        }
        .pg-hero-sub  { color:rgba(212,245,229,.75); margin:0 0 14px; font-size:.9rem; }
        .pg-hero-divider {
            width:48px; height:2px; border-radius:2px; margin:0 0 12px;
            background:linear-gradient(90deg,transparent,#24e78f,transparent);
        }
        .pg-hero-layout { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; position:relative; z-index:2; }

        /* ── Card ── */
        .card { background: var(--h-card) !important; border: 1px solid var(--h-border) !important; border-radius: 12px !important; box-shadow: var(--h-shadow-sm) !important; }
        .card-header { background: var(--h-card) !important; border-bottom: 1px solid var(--h-border) !important; padding: 14px 20px !important; display:flex !important; align-items:center !important; justify-content:space-between !important; flex-wrap:wrap !important; gap:10px !important; }
        .card-title { font-weight: 700 !important; font-size: .9rem !important; color: var(--h-text) !important; letter-spacing: -.2px; margin-right: auto !important; }
        .card-body { background: var(--h-card) !important; padding: 18px !important; }
        .card-tools { display:flex !important; align-items:center !important; justify-content:flex-end !important; gap:8px !important; flex-wrap:wrap !important; margin-left: auto !important; }
        .card-tools .btn-primary {
            background: var(--h-primary) !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: .8rem !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .card-tools .btn-primary:hover { filter: brightness(1.1) !important; }
        .card-tools .btn-outline-secondary {
            border: 1px solid var(--h-border) !important; color: var(--h-muted) !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: .8rem !important; background: var(--h-card-alt) !important;
        }

        /* ── Table ── */
        #inventoryTable.table { color: var(--h-text) !important; }
        #inventoryTable.table thead th {
            background: var(--h-card-alt) !important; border: none !important; border-bottom: 1px solid var(--h-border) !important;
            color: var(--h-muted) !important; font-size: .68rem !important; font-weight: 700 !important;
            text-transform: uppercase !important; letter-spacing: .6px !important; padding: 10px 14px !important;
        }
        #inventoryTable.table tbody tr { transition: background .12s; }
        #inventoryTable.table tbody tr:hover { background: var(--h-card-alt) !important; }
        #inventoryTable.table tbody td {
            border-top: 1px solid var(--h-border) !important; border-left: none !important; border-right: none !important;
            padding: 12px 14px !important; vertical-align: middle !important; font-size: .85rem !important; color: var(--h-text) !important;
        }
        #inventoryTable.table-bordered { border: none !important; }
        #inventoryTable tbody tr.stock-very-low td { background: rgba(201,34,46,.06) !important; }
        #inventoryTable tbody tr.stock-error td { background: rgba(230,119,0,.08) !important; }

        .description-cell {
            max-width: 260px; display: inline-block; white-space: nowrap; overflow: hidden;
            text-overflow: ellipsis; vertical-align: bottom;
        }

        /* Status badges */
        .badge-secondary { background: var(--h-surface3) !important; color: var(--h-muted) !important; border-radius: 5px !important; }
        .badge-success   { background: rgba(42,152,99,.15) !important; color: var(--h-success) !important; border-radius: 5px !important; }
        .badge-danger    { background: rgba(201,34,46,.15) !important; color: var(--h-danger) !important; border-radius: 5px !important; }
        .badge-warning   { background: rgba(230,119,0,.15) !important; color: var(--h-warning) !important; border-radius: 5px !important; }

        /* Row action buttons */
        #inventoryTable .btn-sm.btn-primary { background: rgba(42,152,99,.12) !important; color: var(--h-blue) !important; border: 1px solid rgba(42,152,99,.25) !important; border-radius: 7px !important; }
        #inventoryTable .btn-sm.btn-info    { background: rgba(230,119,0,.12) !important; color: var(--h-warning) !important; border: 1px solid rgba(230,119,0,.25) !important; border-radius: 7px !important; }
        #inventoryTable .btn-sm.btn-danger  { background: rgba(201,34,46,.12) !important; color: var(--h-danger) !important; border: 1px solid rgba(201,34,46,.25) !important; border-radius: 7px !important; }
        #inventoryTable .btn-sm:hover { filter: brightness(1.15); transform: translateY(-1px); }

        /* ── Scrum-style modal (mirrors Applicant Databank modals) ── */
        .scrum-style-modal .modal-content { background: var(--h-card) !important; border: 1px solid var(--h-border) !important; border-radius: 14px !important; box-shadow: 0 24px 80px rgba(0,0,0,.25) !important; color: var(--h-text) !important; }
        .scrum-style-modal .modal-header { background: var(--h-card-alt) !important; border-bottom: 1px solid var(--h-border) !important; border-radius: 14px 14px 0 0 !important; padding: 16px 20px !important; }
        .scrum-style-modal .modal-title { font-weight: 700 !important; font-size: .95rem !important; color: var(--h-text) !important; }
        .scrum-style-modal .modal-title i { color: var(--h-primary) !important; margin-right: 8px; }
        .scrum-style-modal .modal-header .close { color: var(--h-muted) !important; text-shadow: none !important; opacity: 1 !important; }
        .scrum-style-modal .modal-header .close:hover { opacity: .7 !important; }
        .scrum-style-modal .modal-body { padding: 20px !important; background: var(--h-card) !important; }
        .scrum-style-modal .modal-footer { border-top: 1px solid var(--h-border) !important; padding: 14px 20px !important; background: var(--h-card-alt) !important; border-radius: 0 0 14px 14px !important; }
        .scrum-style-modal label { color: var(--h-muted) !important; font-size: .72rem !important; font-weight: 600 !important; text-transform: uppercase !important; letter-spacing: .5px !important; margin-bottom: 5px !important; }
        .scrum-style-modal label .text-danger { text-transform: none !important; }
        .scrum-style-modal .form-control, .scrum-style-modal .form-control:focus {
            background: var(--h-card-alt) !important; border: 1.5px solid var(--h-border) !important; color: var(--h-text) !important;
            border-radius: 8px !important; font-size: .875rem !important;
        }
        .scrum-style-modal .form-control::placeholder { color: var(--h-muted) !important; }
        .scrum-style-modal .form-control:focus { border-color: var(--h-primary) !important; box-shadow: 0 0 0 3px var(--h-primary-dim) !important; }
        .scrum-style-modal select.form-control option { background: var(--h-card) !important; color: var(--h-text) !important; }
        .scrum-style-modal .form-text.text-muted, .scrum-style-modal .form-text.text-danger { color: var(--h-muted) !important; }
        .scrum-style-modal .info-box { background: var(--h-card-alt) !important; border: 1px solid var(--h-border) !important; border-radius: 8px !important; padding: 10px 14px !important; font-size: .8rem !important; color: var(--h-text) !important; }

        .scrum-style-modal .btn-secondary { background: var(--h-card-alt) !important; color: var(--h-muted) !important; border: 1px solid var(--h-border) !important; border-radius: 8px !important; font-weight: 600 !important; }
        .scrum-style-modal .btn-primary, .scrum-style-modal .btn-warning {
            background: var(--h-primary) !important; color: #fff !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .scrum-style-modal .btn-primary:hover, .scrum-style-modal .btn-warning:hover { filter: brightness(1.1) !important; color: #fff !important; }
        .scrum-style-modal .btn-outline-danger {
            border: 1px solid var(--h-danger) !important; color: var(--h-danger) !important; border-radius: 8px !important; font-weight: 700 !important; background: transparent !important;
        }
        .scrum-style-modal .btn-outline-danger:hover { background: rgba(201,34,46,.1) !important; }

        /* Current stock readout inside Edit modal */
        .stock-readout {
            background: var(--h-card-alt) !important; border: 1px solid var(--h-border) !important; border-radius: 10px !important;
            padding: 14px 18px !important; display:flex; align-items:center; justify-content:space-between; margin-bottom: 18px;
        }
        .stock-readout .label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color: var(--h-muted) !important; }
        .stock-readout .badge { font-size: .95rem !important; padding: 6px 12px !important; }

        /* Stock Adjustment tab navigation inside Edit modal */
        .edit-item-tabs {
            border-bottom: 1.5px solid var(--h-border) !important; margin-bottom: 18px !important; gap: 4px;
        }
        .edit-item-tabs .nav-link {
            border: none !important; border-bottom: 2px solid transparent !important; border-radius: 8px 8px 0 0 !important;
            color: var(--h-muted) !important; font-size: .78rem !important; font-weight: 700 !important;
            text-transform: uppercase !important; letter-spacing: .5px !important; padding: 10px 16px !important;
        }
        .edit-item-tabs .nav-link:hover { color: var(--h-primary) !important; background: var(--h-primary-dim) !important; }
        .edit-item-tabs .nav-link.active {
            color: var(--h-primary) !important; background: var(--h-primary-dim) !important;
            border-bottom: 2px solid var(--h-primary) !important;
        }
        .edit-item-tab-content { margin-bottom: 4px; }

        /* Danger zone inside Edit modal */
        .danger-zone-inline {
            border: 1px solid rgba(201,34,46,.3) !important; background: rgba(201,34,46,.05) !important; border-radius: 10px !important;
            padding: 14px 18px !important; margin-top: 18px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
        }
        .danger-zone-inline p { margin: 0; font-size: .8rem; color: var(--h-text) !important; }
        .danger-zone-inline strong { color: var(--h-danger) !important; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_inventory.php'; ?>

    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="pg-hero">
            <div class="pg-hero-mesh"></div>
            <div class="pg-hero-dots"></div>
            <div class="pg-hero-orbs">
                <div class="pg-orb pg-orb-1"></div>
                <div class="pg-orb pg-orb-2"></div>
                <div class="pg-orb pg-orb-3"></div>
                <div class="pg-orb pg-orb-4"></div>
            </div>
            <div class="pg-hero-arc"></div>
            <div class="pg-hero-layout">
                <div class="pg-hero-inner">
                    <div class="pg-hero-title"><i class="fas fa-boxes"></i>View Inventory</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Track items, stock levels, and adjustments</p>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <div class="card">
                    <div class="card-header" style="display:flex !important; justify-content:space-between !important; align-items:center !important;">
                        <h3 class="card-title" style="margin-right:auto !important;">Inventory Items</h3>
                        <div class="card-tools" style="display:flex !important; justify-content:flex-end !important; margin-left:auto !important;">
                            <form method="GET" class="form-inline mr-2">
                                <div class="input-group input-group-sm">
                                    <select class="form-control" name="category" onchange="this.form.submit()" style="margin-right: 8px;">
                                        <option value="all">All Categories</option>
                                        <?php foreach ($categories as $id => $cname): ?>
                                            <option value="<?= $id ?>" <?= $category_filter == $id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cname) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                            <a href="delivery_entry.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-truck"></i> Delivery Entry
                            </a>
                            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addItemModal">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="inventoryTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Unit</th>
                                        <th>Current Stock</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No items found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($items as $item):
                                            $stock_class = $item['current_stock'] < 0 ? 'stock-error' : ($item['current_stock'] == 0 ? 'stock-very-low' : '');
                                            $description = $item['description'] ?? '';
                                        ?>
                                            <tr class="<?= $stock_class ?>">
                                                <td><?= htmlspecialchars($item['name']) ?></td>
                                                <td><?= htmlspecialchars($item['category_name'] ?? 'N/A') ?></td>
                                                <td>
                                                    <span class="description-cell" title="<?= htmlspecialchars($description) ?>">
                                                        <?= htmlspecialchars($description !== '' ? $description : '—') ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($item['unit_of_measure'] ?? '') ?></td>
                                                <td><?= $item['current_stock'] ?></td>
                                                <td>
                                                    <?php if ($item['current_stock'] < 0): ?>
                                                        <span class="badge badge-warning" title="Stock movement history doesn't add up — recorded stock-outs exceed stock-ins. Check the item's history and correct it.">Data Error</span>
                                                    <?php elseif ($item['current_stock'] == 0): ?>
                                                        <span class="badge badge-danger">Out of Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">In Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary edit-item-btn" data-id="<?= $item['id'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="stock_movements.php?item_id=<?= $item['id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-history"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger delete-item-btn"
                                                        data-id="<?= $item['id'] ?>"
                                                        data-name="<?= htmlspecialchars($item['name']) ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Add Item Modal -->
<div class="modal fade scrum-style-modal" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addItemForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i>Add New Item</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" class="form-control">
                            <option value="0">-- Select Category --</option>
                            <?php foreach ($categories as $id => $cname): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($cname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Unit of Measure <span class="text-danger">*</span></label>
                        <select name="unit_of_measure" class="form-control" required>
                            <option value="">-- Select Unit of Measure --</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= htmlspecialchars($unit['name']) ?>">
                                    <?= htmlspecialchars($unit['name']) ?><?= $unit['abbreviation'] ? ' (' . htmlspecialchars($unit['abbreviation']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($units)): ?>
                            <small class="form-text text-danger">
                                No units of measure found. <a href="unit_of_measure.php">Add one first</a>.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade scrum-style-modal" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editItemForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i>Edit Item</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="stock-readout">
                        <span class="label">Current Stock</span>
                        <span class="badge" id="edit_current_stock_badge">0</span>
                    </div>

                    <ul class="nav nav-tabs edit-item-tabs" id="editItemTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="edit-details-tab" data-toggle="tab" href="#editDetailsTab" role="tab" aria-controls="editDetailsTab" aria-selected="true">
                                <i class="fas fa-info-circle mr-1"></i>Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="edit-adjustment-tab" data-toggle="tab" href="#editAdjustmentTab" role="tab" aria-controls="editAdjustmentTab" aria-selected="false">
                                <i class="fas fa-exchange-alt mr-1"></i>Stock Adjustment
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content edit-item-tab-content" id="editItemTabsContent">
                        <div class="tab-pane fade show active" id="editDetailsTab" role="tabpanel" aria-labelledby="edit-details-tab">
                            <div class="form-group">
                                <label>Item Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category_id" id="edit_category_id" class="form-control">
                                    <option value="0">-- Select Category --</option>
                                    <?php foreach ($categories as $id => $cname): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($cname) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" id="edit_description" class="form-control" rows="3" placeholder="Optional details about this item"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Unit of Measure <span class="text-danger">*</span></label>
                                <select name="unit_of_measure" id="edit_unit_of_measure" class="form-control" required>
                                    <option value="">-- Select Unit of Measure --</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?= htmlspecialchars($unit['name']) ?>">
                                            <?= htmlspecialchars($unit['name']) ?><?= $unit['abbreviation'] ? ' (' . htmlspecialchars($unit['abbreviation']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="editAdjustmentTab" role="tabpanel" aria-labelledby="edit-adjustment-tab">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Type</label>
                                        <select class="form-control" id="edit_adjustment_type" name="adjustment_type">
                                            <option value="">-- Select --</option>
                                            <option value="in">Stock In</option>
                                            <option value="out">Stock Out</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Quantity</label>
                                        <input type="number" class="form-control" id="edit_adjustment_quantity" name="adjustment_quantity" value="0" min="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Reference</label>
                                        <input type="text" class="form-control" id="edit_adjustment_reference" name="adjustment_reference" placeholder="Reference number">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea class="form-control" id="edit_adjustment_notes" name="adjustment_notes" rows="2" placeholder="Adjustment notes"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="danger-zone-inline">
                        <p>Deleting <strong id="edit_delete_name">this item</strong> also removes its stock movement history. This cannot be undone.</p>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="edit_delete_btn">
                            <i class="fas fa-trash"></i> Delete Item
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#inventoryTable').DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        order: [[0, "asc"]],
        pageLength: 10
    }).buttons().container().appendTo('#inventoryTable_wrapper .col-md-6:eq(0)');

    function selectWithLegacyOption($select, value, legacyLabelSuffix) {
        if ($select.find('option[value="' + value + '"]').length === 0 && value !== '' && value !== null) {
            $select.append('<option value="' + value + '">' + value + ' ' + legacyLabelSuffix + '</option>');
        }
        $select.val(value);
    }

    // Add Item
    $('#addItemForm').on('submit', function(e) {
        e.preventDefault();
        let formData = $(this).serialize() + '&action=create';

        $.ajax({
            url: 'view_inventory.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error!', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
            }
        });
    });

    // Open Edit modal
    // Delegated on the table (not the buttons themselves) because DataTables
    // only keeps the current page's rows in the DOM — buttons on other pages
    // don't exist yet when the page first loads, so a direct .on('click', ...)
    // binding never reaches them.
    $('#inventoryTable').on('click', '.edit-item-btn', function() {
        let id = $(this).data('id');

        $.ajax({
            url: 'view_inventory.php',
            type: 'POST',
            data: { action: 'get', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let data = response.data;
                    $('#edit_id').val(data.id);
                    $('#edit_name').val(data.name);
                    $('#edit_description').val(data.description);
                    $('#edit_category_id').val(data.category_id);
                    selectWithLegacyOption($('#edit_unit_of_measure'), data.unit_of_measure, '(legacy)');
                    $('#edit_adjustment_type').val('');
                    $('#edit_adjustment_quantity').val(0);
                    $('#edit_adjustment_reference').val('');
                    $('#edit_adjustment_notes').val('');

                    let stock = parseInt(data.current_stock, 10) || 0;
                    let $badge = $('#edit_current_stock_badge');
                    $badge.text(stock).removeClass('badge-success badge-danger badge-warning');
                    if (stock < 0) {
                        $badge.addClass('badge-warning').attr('title', "Stock movement history doesn't add up — recorded stock-outs exceed stock-ins.");
                    } else if (stock === 0) {
                        $badge.addClass('badge-danger').removeAttr('title');
                    } else {
                        $badge.addClass('badge-success').removeAttr('title');
                    }

                    $('#edit_delete_name').text(data.name);
                    $('#edit_delete_btn').data('id', data.id).data('name', data.name);

                    // Always open on the Details tab
                    $('#editItemTabs a[href="#editDetailsTab"]').tab('show');

                    $('#editItemModal').modal('show');
                } else {
                    Swal.fire('Error!', response.message, 'error');
                }
            }
        });
    });

    // Update Item
    $('#editItemForm').on('submit', function(e) {
        e.preventDefault();
        let formData = $(this).serialize() + '&action=update';

        $.ajax({
            url: 'view_inventory.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error!', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
            }
        });
    });

    // Delete (from table row button or the Edit modal's danger zone)
    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Are you sure?',
            html: 'Delete <strong>' + name + '</strong>? This will also remove its stock movement history and cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c92a2a',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'view_inventory.php',
                    type: 'POST',
                    data: { action: 'delete', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    }
                });
            }
        });
    }

    $('#inventoryTable').on('click', '.delete-item-btn', function() {
        confirmDelete($(this).data('id'), $(this).data('name'));
    });

    $('#edit_delete_btn').on('click', function() {
        confirmDelete($(this).data('id'), $(this).data('name'));
    });

    // Support old bookmarked links: view_inventory.php?add=1 or ?edit_id=123
    let params = new URLSearchParams(window.location.search);
    if (params.get('add') === '1') {
        $('#addItemModal').modal('show');
    }
    if (params.get('edit_id')) {
        $('.edit-item-btn[data-id="' + params.get('edit_id') + '"]').trigger('click');
    }
});
</script>
</body>
</html>