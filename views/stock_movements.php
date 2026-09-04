<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get item ID from URL
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

// Get item information
$item = null;
if ($item_id > 0) {
    $query = "SELECT * FROM items WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
}

// Get stock movements with additional details including RIS information
$movements = [];
if ($item_id > 0) {
    $query = "SELECT sm.*, i.name as item_name, i.unit_of_measure, sm.unit_cost,
                     COALESCE(r.ris_number, iar.iar_number, sm.reference) as reference_display,
                     CASE 
                         WHEN r.ris_number IS NOT NULL THEN CONCAT('RIS: ', r.ris_number)
                         WHEN iar.iar_number IS NOT NULL THEN CONCAT('IAR: ', iar.iar_number)
                         ELSE sm.reference
                     END as display_reference
              FROM stock_movements sm 
              JOIN items i ON sm.item_id = i.id 
              LEFT JOIN ris_records r ON sm.reference = r.ris_number
              LEFT JOIN iar_records iar ON sm.reference = iar.po_number
              WHERE sm.item_id = ? 
              ORDER BY sm.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $movements = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$action_type = $_GET['action_type'] ?? '';

// Build filtered query if filters are applied
if (!empty($date_from) || !empty($date_to) || !empty($action_type)) {
    $query = "SELECT sm.*, i.name as item_name, i.unit_of_measure
              FROM stock_movements sm 
              JOIN items i ON sm.item_id = i.id 
              WHERE sm.item_id = ?";
    
    $params = [$item_id];
    $types = "i";
    
    if (!empty($date_from)) {
        $query .= " AND DATE(sm.created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(sm.created_at) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    
    if (!empty($action_type) && $action_type != 'all') {
        $query .= " AND sm.movement_type = ?";
        $params[] = $action_type;
        $types .= "s";
    }
    
    $query .= " ORDER BY sm.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $movements = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movements - Inventory Management</title>
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
        .modal-xl { max-width: 90%; }
        .card { background: var(--h-card) !important; border: 1px solid var(--h-border) !important; border-radius: 12px !important; box-shadow: var(--h-shadow-sm) !important; }
        .card-header { background: var(--h-card) !important; border-bottom: 1px solid var(--h-border) !important; padding: 14px 20px !important; display:flex !important; align-items:center !important; justify-content:space-between !important; flex-wrap:wrap !important; gap:10px !important; }
        .card-title { font-weight: 700 !important; font-size: .9rem !important; color: var(--h-text) !important; letter-spacing: -.2px; margin-right: auto !important; }
        .card-body { background: var(--h-card) !important; padding: 18px !important; }
        .card-tools { display:flex !important; align-items:center !important; justify-content:flex-end !important; gap:8px !important; flex-wrap:wrap !important; margin-left: auto !important; }
        .card-tools .btn-secondary {
            background: var(--h-card-alt) !important; color: var(--h-muted) !important; border: 1px solid var(--h-border) !important;
            border-radius: 8px !important; font-weight: 700 !important; font-size: .8rem !important;
        }
        .card-tools .btn-secondary:hover { background: var(--h-surface3) !important; }
        .card-tools .btn-primary {
            background: var(--h-primary) !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: .8rem !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .card-tools .btn-primary:hover { filter: brightness(1.1) !important; }

        /* Filter form */
        .filter-form .input-group { width: auto; }
        .filter-form .form-control {
            margin-right: 5px; background: var(--h-card-alt) !important; border: 1.5px solid var(--h-border) !important;
            color: var(--h-text) !important; border-radius: 8px !important; font-size: .82rem !important;
        }
        .filter-form .form-control:focus { border-color: var(--h-primary) !important; box-shadow: 0 0 0 3px var(--h-primary-dim) !important; }

        /* Item info boxes */
        .info-box { cursor: default; margin-bottom: 15px; }
        .info-box.bg-light {
            background: var(--h-card-alt) !important; border: 1px solid var(--h-border) !important; border-radius: 10px !important;
            box-shadow: none !important;
        }
        .info-box-text { color: var(--h-muted) !important; font-size: .68rem !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: .5px !important; }
        .info-box-number { color: var(--h-text) !important; font-size: .95rem !important; font-weight: 700 !important; }
        .stock-status { font-size: 1.2rem; font-weight: bold; }

        /* Description panel */
        .description-panel {
            background: var(--h-card-alt) !important; border: 1px solid var(--h-border) !important; border-radius: 10px !important;
            padding: 14px 16px !important; margin-bottom: 15px !important;
        }
        .description-panel .info-box-text { display:block; margin-bottom: 8px; }
        .description-text {
            color: var(--h-text) !important; font-size: .85rem !important; font-weight: 400 !important;
            line-height: 1.55 !important; white-space: pre-line; margin: 0;
        }
        .description-text.is-clamped {
            display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
        }
        .description-toggle {
            background: none !important; border: none !important; padding: 8px 0 0 !important;
            color: var(--h-primary) !important; font-size: .78rem !important; font-weight: 700 !important; cursor: pointer;
        }
        .description-toggle:hover { text-decoration: underline; }
        .description-toggle:focus { outline: none; }

        /* ── Table ── */
        #movementsTable.table { color: var(--h-text) !important; }
        #movementsTable.table thead th {
            background: var(--h-card-alt) !important; border: none !important; border-bottom: 1px solid var(--h-border) !important;
            color: var(--h-muted) !important; font-size: .68rem !important; font-weight: 700 !important;
            text-transform: uppercase !important; letter-spacing: .6px !important; padding: 10px 14px !important;
        }
        #movementsTable.table tbody tr { transition: background .12s; }
        #movementsTable.table tbody tr:hover { background: var(--h-card-alt) !important; }
        #movementsTable.table tbody td {
            border-top: 1px solid var(--h-border) !important; border-left: none !important; border-right: none !important;
            padding: 12px 14px !important; vertical-align: middle !important; font-size: .85rem !important; color: var(--h-text) !important;
        }
        #movementsTable.table-bordered { border: none !important; }
        .total-row td { font-weight: bold; background: var(--h-card-alt) !important; border-top: 1px solid var(--h-border) !important; color: var(--h-text) !important; }

        /* Badges */
        .badge-success { background: rgba(42,152,99,.15) !important; color: var(--h-success) !important; border-radius: 5px !important; }
        .badge-danger  { background: rgba(201,34,46,.15) !important; color: var(--h-danger) !important; border-radius: 5px !important; }
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
                    <div class="pg-hero-title"><i class="fas fa-history"></i>Stock Movements</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Full movement history and running totals for a single item</p>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if ($item): ?>
                <!-- Item Information -->
                <div class="card">
                    <div class="card-header" style="display:flex !important; justify-content:space-between !important; align-items:center !important;">
                        <h3 class="card-title" style="margin-right:auto !important;">Item: <?= htmlspecialchars($item['name']) ?></h3>
                        <div class="card-tools" style="display:flex !important; justify-content:flex-end !important; margin-left:auto !important; gap:8px;">
                            <a href="view_inventory.php?edit_id=<?= $item['id'] ?>" class="btn btn-primary btn-sm" style="display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-edit"></i> Edit Item
                            </a>
                            <a href="delivery_entry.php" class="btn btn-success btn-sm" style="display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-truck"></i> New Delivery
                            </a>
                            <button type="button" class="btn btn-secondary" onclick="window.history.back()" style="display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Item Name</span>
                                        <span class="info-box-number"><?= htmlspecialchars($item['name']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Unit of Measure</span>
                                        <span class="info-box-number"><?= htmlspecialchars($item['unit_of_measure'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Current Stock</span>
                                        <span class="info-box-number stock-status">
                                            <span class="badge badge-<?= 
                                                $item['current_stock'] == 0 ? 'danger' : 'success'
                                            ?>"><?= $item['current_stock'] ?></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="description-panel">
                                    <span class="info-box-text">Description</span>
                                    <?php $item_desc = trim($item['description'] ?? ''); ?>
                                    <?php if ($item_desc !== ''): ?>
                                        <p class="description-text is-clamped" id="itemDescriptionText"><?= htmlspecialchars($item_desc) ?></p>
                                        <button type="button" class="description-toggle" id="descriptionToggleBtn" onclick="toggleDescription()">Show more</button>
                                    <?php else: ?>
                                        <p class="description-text">N/A</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" style="display:flex !important; justify-content:space-between !important; align-items:center !important;">
                        <h3 class="card-title" style="margin-right:auto !important;">Stock Movement History</h3>
                        <div class="card-tools" style="display:flex !important; justify-content:flex-end !important; margin-left:auto !important;">
                            <form method="GET" class="form-inline filter-form">
                                <input type="hidden" name="item_id" value="<?= $item_id ?>">
                                <div class="input-group input-group-sm">
                                    <input type="date" name="date_from" class="form-control" 
                                           value="<?= htmlspecialchars($date_from) ?>" placeholder="From Date">
                                    <input type="date" name="date_to" class="form-control" 
                                           value="<?= htmlspecialchars($date_to) ?>" placeholder="To Date">
                                    <select class="form-control" name="action_type">
                                        <option value="all">All Actions</option>
                                        <option value="in" <?= $action_type == 'in' ? 'selected' : '' ?>>Stock In</option>
                                        <option value="out" <?= $action_type == 'out' ? 'selected' : '' ?>>Stock Out</option>
                                    </select>
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <a href="stock_movements.php?item_id=<?= $item_id ?>" class="btn btn-secondary">
                                            <i class="fas fa-sync"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="movementsTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Action Type</th>
                                        <th>Quantity</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                        <th>Reference</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($movements)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No stock movements found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $total_in = 0;
                                        $total_out = 0;
                                        $total_cost = 0;
                                        foreach ($movements as $movement): 
                                            $item_cost = $movement['quantity'] * ($movement['unit_cost'] ?? 0);
                                            if ($movement['movement_type'] == 'in') {
                                                $total_in += $movement['quantity'];
                                                $total_cost += $item_cost;
                                            } else {
                                                $total_out += $movement['quantity'];
                                            }
                                        ?>
                                            <tr>
                                                <td><?= date('Y-m-d H:i:s', strtotime($movement['created_at'])) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $movement['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                                        <?= strtoupper($movement['movement_type']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $movement['quantity'] ?> <?= htmlspecialchars($movement['unit_of_measure']) ?></td>
                                                <td class="text-right"><?= isset($movement['unit_cost']) ? number_format($movement['unit_cost'], 2) : 'N/A' ?></td>
                                                <td class="text-right"><?= isset($movement['unit_cost']) ? number_format($item_cost, 2) : 'N/A' ?></td>
                                                <td><?= htmlspecialchars($movement['reference']) ?></td>
                                                <td><?= htmlspecialchars($movement['notes']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <?php if (!empty($movements)): ?>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="2" class="text-right"><strong>Totals:</strong></td>
                                        <td><strong>IN: <?= number_format($total_in) ?> | OUT: <?= number_format($total_out) ?></strong></td>
                                        <td colspan="2" class="text-right"><strong>Total Cost: ₱<?= number_format($total_cost, 2) ?></strong></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <h4>Item not found</h4>
                        <p>Please select a valid item from the inventory.</p>
                        <a href="view_inventory.php" class="btn btn-primary">Back to Inventory</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>
    function toggleDescription() {
        var $text = $('#itemDescriptionText');
        var $btn = $('#descriptionToggleBtn');
        $text.toggleClass('is-clamped');
        $btn.text($text.hasClass('is-clamped') ? 'Show more' : 'Show less');
    }

    $(document).ready(function() {
        // Hide the "Show more" toggle if the description already fits within the clamp
        var descEl = document.getElementById('itemDescriptionText');
        if (descEl && descEl.scrollHeight <= descEl.clientHeight + 2) {
            $('#descriptionToggleBtn').hide();
        }

        // Initialize DataTable for movements
        $('#movementsTable').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "order": [[0, "desc"]],
            "buttons": ["copy", "csv", "excel", "pdf", "print"],
            "pageLength": 25,
            "dom": 'Bfrtip',
            "columns": [
                null,
                null,
                null,
                { "className": "text-right" },
                { "className": "text-right" },
                null,
                null
            ]
        }).buttons().container().appendTo('#movementsTable_wrapper .col-md-6:eq(0)');
    });
</script>
</body>
</html>