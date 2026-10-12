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

// Get current employee details
$employee_id = $_SESSION['emp_id'] ?? 0;
$employee_details = [];

if ($employee_id) {
    // Modified query to handle employees without section_id (like managers_staff)
    $employee_query = "SELECT emp_id, CONCAT(first_name, ' ', last_name) as full_name, 
                      COALESCE(s.section_name, 'Manager\'s Office') as section_name,
                      CASE 
                          WHEN EXISTS (SELECT 1 FROM managers_office_staff WHERE emp_id = e.emp_id) THEN 'Manager\'s Office'
                          ELSE COALESCE(s.section_name, 'General')
                      END as display_section
                      FROM employee e 
                      LEFT JOIN section s ON e.section_id = s.section_id 
                      WHERE e.emp_id = ?";
    $stmt = $db->prepare($employee_query);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_details = $result->fetch_assoc();
}

// Get available supplies for the catalog
$supplies = [];
$supply_query = "SELECT id, name, description, unit_of_measure, current_stock 
                FROM items 
                ORDER BY name";
$supply_result = $db->query($supply_query);
if ($supply_result) {
    while ($row = $supply_result->fetch_assoc()) {
        $supplies[$row['id']] = $row;
    }
}

// Process form submission
$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_request'])) {
    // Get values from session/employee details
    $employee_name = $employee_details['full_name'] ?? '';
    $section = $employee_details['display_section'] ?? '';
    $request_date = date('Y-m-d'); // Current date
    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    // Validate required fields
    if (empty($employee_name)) {
        $error = "Employee information is required.";
    } elseif (empty($item_ids) || empty($quantities)) {
        $error = "Your cart is empty. Add at least one supply item.";
    } else {
        // Merge duplicates and validate against available stock
        $cart = [];
        foreach ($item_ids as $index => $item_id) {
            $item_id = (int)$item_id;
            $quantity = intval($quantities[$index] ?? 0);
            if ($item_id > 0 && $quantity > 0 && isset($supplies[$item_id])) {
                $cart[$item_id] = ($cart[$item_id] ?? 0) + $quantity;
            }
        }

        // No stock cap: anything above the current stock is a request for the next purchase.
        if (empty($cart)) {
            $error = "Your cart has no valid items.";
        }

        if (!$error) {
            // Begin transaction
            $db->begin_transaction();

            try {
                // Create supply request record
                $request_query = "INSERT INTO supply_requests (employee_id, employee_name, section, request_date) 
                                VALUES (?, ?, ?, ?)";
                $request_stmt = $db->prepare($request_query);
                $request_stmt->bind_param("isss", $employee_id, $employee_name, $section, $request_date);
                $request_stmt->execute();
                $request_id = $db->insert_id;
                $request_stmt->close();

                // Process each supply item
                $item_query = "INSERT INTO supply_request_items (request_id, supply_name, description, unit, quantity) 
                            VALUES (?, ?, ?, ?, ?)";
                $item_stmt = $db->prepare($item_query);
                foreach ($cart as $item_id => $quantity) {
                    $item_info = $supplies[$item_id];
                    $item_stmt->bind_param("isssi", $request_id, $item_info['name'], $item_info['description'], $item_info['unit_of_measure'], $quantity);
                    $item_stmt->execute();
                }
                $item_stmt->close();

                $db->commit();
                $success = "Supply request submitted successfully! Request ID: SR-" . str_pad($request_id, 4, '0', STR_PAD_LEFT);

                // Reset form
                $_POST = array();

            } catch (Exception $e) {
                $db->rollback();
                $error = "Error submitting supply request: " . $e->getMessage();
            }
        }
    }
}

// Handle Excel file upload and processing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['excel_file']['tmp_name'];
        $file_name = $_FILES['excel_file']['name'];
        
        // Check file extension
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['xlsx', 'xls'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            try {
                // Load PhpSpreadsheet
                require_once '../vendor/autoload.php';
                
                $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($file_tmp_path);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Extract data from Excel (based on your template format)
                $employee_name = '';
                $section = '';
                $request_date = date('Y-m-d'); // Current date for Excel imports too
                $items = [];
                
                // Read Excel data (adjust cell references based on your actual Excel structure)
                foreach ($worksheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(FALSE);
                    $rowData = [];
                    
                    foreach ($cellIterator as $cell) {
                        $rowData[] = $cell->getCalculatedValue();
                    }
                    
                    // Extract employee name (adjust based on your Excel structure)
                    if (isset($rowData[0]) && strpos(strtolower($rowData[0]), 'name:') !== false) {
                        $employee_name = trim(str_replace('NAME:', '', $rowData[0]));
                    }
                    
                    // Extract section (adjust based on your Excel structure)
                    if (isset($rowData[1]) && strpos(strtolower($rowData[1]), 'section:') !== false) {
                        $section = trim(str_replace('SECTION:', '', $rowData[1]));
                    }
                    
                    // Extract items (assuming they start from row with numeric first cell)
                    if (is_numeric($rowData[0]) && !empty($rowData[1])) {
                        $items[] = [
                            'supply_name' => $rowData[1] ?? '',
                            'description' => $rowData[2] ?? '',
                            'unit' => $rowData[3] ?? '',
                            'quantity' => $rowData[4] ?? 0
                        ];
                    }
                }
                
                // Validate extracted data
                if (empty($employee_name) || empty($section) || empty($items)) {
                    $error = "Could not extract all required data from the Excel file. Please check the file format.";
                } else {
                    // Begin transaction
                    $db->begin_transaction();
                    
                    try {
                        // Create supply request record
                        $request_query = "INSERT INTO supply_requests (employee_id, employee_name, section, request_date) 
                                        VALUES (?, ?, ?, ?)";
                        $request_stmt = $db->prepare($request_query);
                        $request_stmt->bind_param("isss", $employee_id, $employee_name, $section, $request_date);
                        $request_stmt->execute();
                        $request_id = $db->insert_id;
                        $request_stmt->close();
                        
                        // Process each supply item
                        foreach ($items as $item) {
                            if (!empty($item['supply_name']) && $item['quantity'] > 0) {
                                // Add to supply_request_items
                                $item_query = "INSERT INTO supply_request_items (request_id, supply_name, description, unit, quantity) 
                                              VALUES (?, ?, ?, ?, ?)";
                                $item_stmt = $db->prepare($item_query);
                                $item_stmt->bind_param("isssi", $request_id, $item['supply_name'], $item['description'], $item['unit'], $item['quantity']);
                                $item_stmt->execute();
                                $item_stmt->close();
                            }
                        }
                        
                        $db->commit();
                        $success = "Supply request imported successfully from Excel! Request ID: SR-" . str_pad($request_id, 4, '0', STR_PAD_LEFT);
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error = "Error importing supply request: " . $e->getMessage();
                    }
                }
                
            } catch (Exception $e) {
                $error = "Error reading Excel file: " . $e->getMessage();
            }
        } else {
            $error = "Invalid file format. Please upload an Excel file (.xlsx or .xls).";
        }
    } else {
        $error = "Please select a valid Excel file to upload.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Supplies - Inventory Management</title>
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

        /* ── Card ── */
        .card { background: var(--h-card) !important; border: 1px solid var(--h-border) !important; border-radius: 12px !important; box-shadow: var(--h-shadow-sm) !important; }
        .card-header { background: var(--h-card) !important; border-bottom: 1px solid var(--h-border) !important; padding: 14px 20px !important; display:flex !important; align-items:center !important; justify-content:space-between !important; flex-wrap:wrap !important; gap:10px !important; }
        .card-title { font-weight: 700 !important; font-size: .9rem !important; color: var(--h-text) !important; letter-spacing: -.2px; margin-right: auto !important; }
        .card-body { background: var(--h-card) !important; padding: 18px !important; }
        .card-tools { display:flex !important; align-items:center !important; justify-content:flex-end !important; gap:8px !important; flex-wrap:wrap !important; margin-left: auto !important; }

        /* ══ SHARED ══ */
        .rs-section-title {
            font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.7px;
            color: var(--h-muted); margin: 0 0 12px; display:flex; align-items:center; gap:8px;
        }
        .rs-section-title::after { content:''; flex:1; height:1px; background: var(--h-border); }

        /* Requestor strip */
        .rs-grid { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; margin-bottom:20px; }
        @media (max-width: 767px) { .rs-grid { grid-template-columns: minmax(0,1fr); } }
        .rs-tile { background: var(--h-card-alt); border:1px solid var(--h-border); border-radius:10px; padding:12px 16px; display:flex; align-items:center; gap:14px; min-width:0; }
        .rs-tile-icon { width:38px; height:38px; flex:0 0 38px; border-radius:10px; background: var(--h-primary-dim); color: var(--h-primary); display:flex; align-items:center; justify-content:center; }
        .rs-tile-label { font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color: var(--h-muted); }
        .rs-tile-value { font-size:.95rem; font-weight:700; color: var(--h-text); word-break: break-word; }

        /* Buttons */
        .rs-btn {
            display:inline-flex; align-items:center; justify-content:center; gap:7px; border-radius:8px !important; font-weight:700 !important; font-size:.8rem !important;
            padding:8px 18px !important; border:1px solid transparent !important; cursor:pointer; transition: filter .12s, transform .12s;
        }
        .rs-btn:hover { filter:brightness(1.1); transform: translateY(-1px); text-decoration:none; }
        .rs-btn:disabled { opacity:.5; cursor:not-allowed; transform:none; filter:none; }
        .rs-btn-primary { background: var(--h-primary) !important; color:#fff !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important; }
        body.dark-mode .rs-btn-primary { color:#062015 !important; }
        .rs-btn-ghost { background: transparent !important; color: var(--h-text) !important; border-color: var(--h-border) !important; }
        .rs-btn-ghost:hover { color: var(--h-primary) !important; border-color: var(--h-primary) !important; }
        .rs-btn-block { width:100%; }
        .alert { display:flex; align-items:center; gap:10px; }

        /* ══ CATALOG ══ */
        .rs-search { position:relative; margin-bottom:14px; }
        .rs-search i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color: var(--h-muted); }
        .rs-search input {
            width:100%; height:42px; padding:0 14px 0 40px; border-radius:10px; font-size:.875rem;
            background: var(--h-card-alt); border:1.5px solid var(--h-border); color: var(--h-text);
        }
        .rs-search input:focus { outline:none; border-color: var(--h-primary); box-shadow:0 0 0 3px var(--h-primary-dim); }
        .rs-count { font-size:.75rem; color: var(--h-muted); margin-bottom:10px; }

        .catalog { display:grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap:12px; }
        .item-card {
            background: var(--h-card-alt); border:1.5px solid var(--h-border); border-radius:12px; padding:14px;
            display:flex; flex-direction:column; gap:8px; transition: border-color .12s, box-shadow .12s;
        }
        .item-card:hover { border-color: var(--h-primary); box-shadow: var(--h-shadow-sm); }
        .item-card.in-cart { border-color: var(--h-primary); background: var(--h-primary-dim); }
        .item-name { font-weight:800; font-size:.9rem; color: var(--h-text); line-height:1.25; }
        .item-desc {
            font-size:.76rem; color: var(--h-muted); line-height:1.35; min-height:2.1em;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
        }
        .item-meta { display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
        .chip {
            font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
            padding:3px 8px; border-radius:20px; background: var(--h-surface3); color: var(--h-muted);
        }
        .chip-stock { background: var(--h-primary-dim); color: var(--h-primary); }
        .chip-stock.low { background: rgba(230,119,0,.14); color: var(--h-warning); }
        .rs-note { font-size:.75rem; color: var(--h-muted); background: var(--h-card-alt); border:1px dashed var(--h-border); border-radius:8px; padding:8px 12px; margin-bottom:12px; }
        .chip-next { background: rgba(230,119,0,.14); color: var(--h-warning); }
        .chip-out { background: rgba(201,42,42,.12); color: var(--h-danger); }
        .item-actions { display:flex; gap:8px; align-items:center; margin-top:auto; }

        /* Quantity stepper */
        .stepper { display:inline-flex; align-items:center; border:1.5px solid var(--h-border); border-radius:8px; overflow:hidden; background: var(--h-card); }
        .stepper button {
            width:30px; height:34px; border:0; background:transparent; color: var(--h-text); font-weight:800; cursor:pointer;
        }
        .stepper button:hover { background: var(--h-primary-dim); color: var(--h-primary); }
        .stepper input {
            width:44px; height:34px; border:0; text-align:center; background:transparent; color: var(--h-text);
            font-weight:700; font-size:.85rem; -moz-appearance:textfield;
        }
        .stepper input::-webkit-outer-spin-button, .stepper input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
        .stepper input:focus { outline:none; }
        .btn-add-cart { flex:1; height:36px; padding:0 10px !important; white-space:nowrap; }
        .no-results { grid-column:1/-1; text-align:center; padding:40px 10px; color: var(--h-muted); }
        .no-results i { font-size:1.6rem; display:block; margin-bottom:8px; opacity:.6; }

        /* ══ CART ══ */
        .cart-panel { position:sticky; top:70px; }
        .cart-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
        .cart-head h4 { margin:0; font-size:.95rem; font-weight:800; color: var(--h-text); display:flex; align-items:center; gap:8px; }
        .cart-badge {
            min-width:22px; height:22px; padding:0 6px; border-radius:11px; background: var(--h-primary); color:#fff;
            font-size:.72rem; font-weight:800; display:inline-flex; align-items:center; justify-content:center;
        }
        body.dark-mode .cart-badge { color:#062015; }
        .cart-clear { background:none; border:0; color: var(--h-muted); font-size:.75rem; font-weight:700; cursor:pointer; }
        .cart-clear:hover { color: var(--h-danger); }
        .cart-list { max-height:48vh; overflow-y:auto; margin:0 -4px; padding:0 4px; }
        .cart-empty { text-align:center; padding:28px 10px; color: var(--h-muted); font-size:.82rem; }
        .cart-empty i { font-size:1.8rem; display:block; margin-bottom:8px; opacity:.5; }
        .cart-row { display:flex; gap:10px; align-items:center; padding:10px 0; border-bottom:1px solid var(--h-border); }
        .cart-row:last-child { border-bottom:0; }
        .cart-row-info { flex:1; min-width:0; }
        .cart-row-name { font-weight:700; font-size:.82rem; color: var(--h-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cart-row-unit { font-size:.7rem; color: var(--h-muted); }
        .cart-row .stepper button { width:26px; height:30px; }
        .cart-row .stepper input { width:38px; height:30px; }
        .cart-remove { border:0; background:transparent; color: var(--h-muted); width:28px; height:28px; border-radius:6px; cursor:pointer; }
        .cart-remove:hover { background: rgba(201,42,42,.1); color: var(--h-danger); }
        .cart-summary { display:flex; justify-content:space-between; font-size:.8rem; color: var(--h-muted); padding:12px 0 12px; border-top:1px solid var(--h-border); margin-top:6px; }
        .cart-summary strong { color: var(--h-text); }

        /* Floating cart button (small screens) */
        .cart-fab {
            display:none; position:fixed; right:18px; bottom:18px; z-index:1040; height:52px; padding:0 20px; border:0; border-radius:26px;
            background: var(--h-primary); color:#fff; font-weight:800; box-shadow:0 6px 20px rgba(0,0,0,.28); align-items:center; gap:10px;
        }
        body.dark-mode .cart-fab { color:#062015; }
        @media (max-width: 991px) {
            .cart-fab { display:inline-flex; }
            .cart-panel { position:static; margin-top:18px; }
        }
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
            <div class="pg-hero-inner">
                <div class="pg-hero-title"><i class="fas fa-dolly"></i>Request Supplies</div>
                <div class="pg-hero-divider"></div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($error) ?></span></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($success) ?></span></div>
                <?php endif; ?>

                <div class="card rs-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-store mr-2"></i>Supply Catalog</h3>
                        <div class="card-tools">
                            <a href="my_supply_requests.php" class="rs-btn rs-btn-ghost">
                                <i class="fas fa-list"></i> My Supply Requests
                            </a>
                            <?php
                            // Only show "Manage Supply Requests" to administrators or managers
                            if (isset($_SESSION['role_name']) && in_array($_SESSION['role_name'], ['Administrator', 'Manager'])):
                            ?>
                                <a href="manage_supply_requests.php" class="rs-btn rs-btn-primary">
                                    <i class="fas fa-cog"></i> Manage All Requests
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">

                        <div class="rs-grid">
                            <div class="rs-tile">
                                <div class="rs-tile-icon"><i class="fas fa-user"></i></div>
                                <div>
                                    <div class="rs-tile-label">Employee Name</div>
                                    <div class="rs-tile-value"><?= htmlspecialchars($employee_details['full_name'] ?? '') ?></div>
                                </div>
                            </div>
                            <div class="rs-tile">
                                <div class="rs-tile-icon"><i class="fas fa-building"></i></div>
                                <div>
                                    <div class="rs-tile-label">Section / Department</div>
                                    <div class="rs-tile-value"><?= htmlspecialchars($employee_details['display_section'] ?? '') ?></div>
                                </div>
                            </div>
                            <div class="rs-tile">
                                <div class="rs-tile-icon"><i class="fas fa-calendar-day"></i></div>
                                <div>
                                    <div class="rs-tile-label">Request Date</div>
                                    <div class="rs-tile-value"><?= date('F j, Y') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- ── Catalog ── -->
                            <div class="col-lg-8">
                                <div class="rs-section-title">Available Supplies</div>
                                <div class="rs-search">
                                    <i class="fas fa-search"></i>
                                    <input type="search" id="catalogSearch" placeholder="Search supplies by name or description…" autocomplete="off">
                                </div>
                                <div class="rs-note"><i class="fas fa-info-circle mr-1"></i>Add any item you need. Quantities above the current stock, and out-of-stock items, are requested for the next purchase.</div>
                                <div class="rs-count" id="catalogCount"></div>

                                <div class="catalog" id="catalog">
                                    <?php foreach ($supplies as $id => $item):
                                        $stock = (int)$item['current_stock'];
                                        $out = $stock <= 0;
                                    ?>
                                        <div class="item-card <?= $out ? 'out-of-stock' : '' ?>"
                                             data-id="<?= (int)$id ?>"
                                             data-name="<?= htmlspecialchars($item['name']) ?>"
                                             data-unit="<?= htmlspecialchars($item['unit_of_measure'] ?? '') ?>"
                                             data-stock="<?= $stock ?>"
                                             data-search="<?= htmlspecialchars(strtolower($item['name'] . ' ' . ($item['description'] ?? ''))) ?>">
                                            <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                            <div class="item-desc" title="<?= htmlspecialchars($item['description'] ?? '') ?>">
                                                <?= htmlspecialchars($item['description'] ?: 'No description') ?>
                                            </div>
                                            <div class="item-meta">
                                                <span class="chip"><?= htmlspecialchars($item['unit_of_measure'] ?: 'unit') ?></span>
                                                <?php if ($out): ?>
                                                    <span class="chip chip-out">Out of stock</span>
                                                <?php else: ?>
                                                    <span class="chip chip-stock <?= $stock <= 5 ? 'low' : '' ?>"><?= number_format($stock) ?> in stock</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-actions">
                                                <div class="stepper">
                                                    <button type="button" class="qty-minus" aria-label="Decrease quantity">−</button>
                                                    <input type="number" class="card-qty" value="1" min="1" aria-label="Quantity to request">
                                                    <button type="button" class="qty-plus" aria-label="Increase quantity">+</button>
                                                </div>
                                                <button type="button" class="rs-btn rs-btn-primary btn-add-cart">
                                                    <i class="fas fa-cart-plus"></i> <span class="btn-label">Add</span>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="no-results" id="noResults" style="display:none;">
                                        <i class="fas fa-box-open"></i>
                                        No supplies match your search.
                                    </div>
                                </div>
                                <?php if (empty($supplies)): ?>
                                    <div class="no-results"><i class="fas fa-box-open"></i>No supplies are currently available.</div>
                                <?php endif; ?>
                            </div>

                            <!-- ── Cart ── -->
                            <div class="col-lg-4">
                                <form method="POST" action="" id="supplyRequestForm" class="cart-panel">
                                    <div class="card mb-0" id="cartPanel" style="box-shadow: var(--h-shadow) !important;">
                                        <div class="card-body">
                                            <div class="cart-head">
                                                <h4><i class="fas fa-shopping-cart"></i> Your Request <span class="cart-badge" id="cartBadge">0</span></h4>
                                                <button type="button" class="cart-clear" id="clearCart">Clear all</button>
                                            </div>

                                            <div class="cart-list" id="cartList"></div>

                                            <div class="cart-summary">
                                                <span>Items: <strong id="sumItems">0</strong></span>
                                                <span>Total quantity: <strong id="sumQty">0</strong></span>
                                            </div>

                                            <div id="cartInputs"></div>

                                            <button type="submit" name="submit_request" id="submitBtn" class="rs-btn rs-btn-primary rs-btn-block" disabled>
                                                <i class="fas fa-paper-plane"></i> Submit Request
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Floating cart button for tablets / phones -->
<button type="button" class="cart-fab" id="cartFab" aria-label="View request cart">
    <i class="fas fa-shopping-cart"></i> View Cart <span class="cart-badge" id="fabBadge">0</span>
</button>

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        const cart = {};   // id -> {id, name, unit, stock, qty}
        const MAX_QTY = 99999;

        const $catalog   = $('#catalog');
        const $cartList  = $('#cartList');
        const $cartInputs = $('#cartInputs');

        function esc(s) {
            return String(s).replace(/[&<>"']/g, function(c) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        }
        function clamp(v, max) {
            v = parseInt(v, 10);
            if (isNaN(v) || v < 1) { v = 1; }
            return Math.min(v, max);
        }
        function notify(text, icon) {
            if (window.Swal) {
                Swal.fire({toast: true, position: 'top-end', icon: icon || 'info', title: text, showConfirmButton: false, timer: 1800});
            }
        }

        /* ── Catalog: search ── */
        function filterCatalog() {
            const q = $('#catalogSearch').val().toLowerCase().trim();
            let shown = 0;
            $catalog.find('.item-card').each(function() {
                const match = !q || String($(this).data('search')).indexOf(q) !== -1;
                $(this).toggle(match);
                if (match) { shown++; }
            });
            $('#noResults').toggle(shown === 0 && $catalog.find('.item-card').length > 0);
            $('#catalogCount').text(shown + ' supply item' + (shown === 1 ? '' : 's'));
        }
        $('#catalogSearch').on('input', filterCatalog);
        filterCatalog();

        /* ── Catalog: card stepper ── */
        $catalog.on('click', '.qty-minus, .qty-plus', function() {
            const $card = $(this).closest('.item-card');
            const $qty  = $card.find('.card-qty');
            const max   = MAX_QTY;
            const delta = $(this).hasClass('qty-plus') ? 1 : -1;
            $qty.val(clamp((parseInt($qty.val(), 10) || 1) + delta, max));
        });
        $catalog.on('change', '.card-qty', function() {
            $(this).val(clamp($(this).val(), MAX_QTY));
        });

        /* ── Add to cart ── */
        $catalog.on('click', '.btn-add-cart', function() {
            const $card = $(this).closest('.item-card');
            const id    = String($card.data('id'));
            const stock = parseInt($card.data('stock'), 10);
            const qty   = clamp($card.find('.card-qty').val(), MAX_QTY);

            const already = cart[id] ? cart[id].qty : 0;
            const newQty  = Math.min(already + qty, MAX_QTY);

            cart[id] = {
                id: id,
                name: String($card.data('name')),
                unit: String($card.data('unit') || ''),
                stock: stock,
                qty: newQty
            };

            if (newQty > stock) {
                notify('Added — ' + (newQty - stock).toLocaleString() + ' will be for the next purchase.', 'info');
            } else {
                notify('Added to your request', 'success');
            }
            $card.find('.card-qty').val(1);
            renderCart();
        });

        /* ── Cart rendering ── */
        function renderCart() {
            const ids = Object.keys(cart);
            let totalQty = 0;

            $cartList.empty();
            $cartInputs.empty();

            if (!ids.length) {
                $cartList.html('<div class="cart-empty"><i class="fas fa-shopping-basket"></i>Your cart is empty.<br>Pick supplies from the catalog and tap <strong>Add</strong>.</div>');
            }

            ids.forEach(function(id) {
                const it = cart[id];
                totalQty += it.qty;

                $cartList.append(
                    '<div class="cart-row" data-id="' + esc(id) + '">' +
                        '<div class="cart-row-info">' +
                            '<div class="cart-row-name" title="' + esc(it.name) + '">' + esc(it.name) + '</div>' +
                            '<div class="cart-row-unit">' + esc(it.unit) + ' · ' + it.stock.toLocaleString() + ' in stock' + '</div>' +
                            (it.qty > it.stock
                                ? '<span class="chip chip-next">' + (it.qty - it.stock).toLocaleString() + ' for next purchase</span>'
                                : '') +
                        '</div>' +
                        '<div class="stepper">' +
                            '<button type="button" class="cart-minus" aria-label="Decrease">−</button>' +
                            '<input type="number" class="cart-qty" value="' + it.qty + '" min="1">' +
                            '<button type="button" class="cart-plus" aria-label="Increase">+</button>' +
                        '</div>' +
                        '<button type="button" class="cart-remove" title="Remove" aria-label="Remove"><i class="fas fa-times"></i></button>' +
                    '</div>'
                );

                $cartInputs.append(
                    '<input type="hidden" name="item_id[]" value="' + esc(id) + '">' +
                    '<input type="hidden" name="quantity[]" value="' + it.qty + '">'
                );
            });

            // Highlight cards that are in the cart and update their button label
            $catalog.find('.item-card').each(function() {
                const inCart = !!cart[String($(this).data('id'))];
                $(this).toggleClass('in-cart', inCart);
                $(this).find('.btn-label').text(inCart ? 'Add more' : 'Add');
            });

            $('#cartBadge, #fabBadge').text(ids.length);
            $('#sumItems').text(ids.length);
            $('#sumQty').text(totalQty.toLocaleString());
            $('#submitBtn').prop('disabled', ids.length === 0);
            $('#cartFab').toggle(ids.length > 0 || window.innerWidth < 992);
        }

        /* ── Cart interactions ── */
        $cartList.on('click', '.cart-minus, .cart-plus', function() {
            const id = String($(this).closest('.cart-row').data('id'));
            const delta = $(this).hasClass('cart-plus') ? 1 : -1;
            const next = cart[id].qty + delta;
            if (next < 1) { return; }
            cart[id].qty = Math.min(next, MAX_QTY);
            renderCart();
        });
        $cartList.on('change', '.cart-qty', function() {
            const id = String($(this).closest('.cart-row').data('id'));
            cart[id].qty = clamp($(this).val(), MAX_QTY);
            renderCart();
        });
        $cartList.on('click', '.cart-remove', function() {
            delete cart[String($(this).closest('.cart-row').data('id'))];
            renderCart();
        });
        $('#clearCart').on('click', function() {
            Object.keys(cart).forEach(function(k) { delete cart[k]; });
            renderCart();
        });

        /* ── Floating cart button ── */
        $('#cartFab').on('click', function() {
            document.getElementById('cartPanel').scrollIntoView({behavior: 'smooth', block: 'start'});
        });

        /* ── Submit: confirm, block empty cart ── */
        $('#supplyRequestForm').on('submit', function(e) {
            if (!Object.keys(cart).length) {
                e.preventDefault();
                notify('Your cart is empty.', 'warning');
                return false;
            }
            return true;
        });

        renderCart();
    });
</script>
</body>
</html>