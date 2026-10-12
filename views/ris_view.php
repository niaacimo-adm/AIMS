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

// Load PhpSpreadsheet
try {
    $composerAutoload = '../vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
    } else {
        $phpspreadsheetPath = '../includes/phpspreadsheet/';
        if (file_exists($phpspreadsheetPath . 'src/PhpSpreadsheet/IOFactory.php')) {
            require_once $phpspreadsheetPath . 'src/PhpSpreadsheet/IOFactory.php';
            require_once $phpspreadsheetPath . 'src/PhpSpreadsheet/Spreadsheet.php';
            require_once $phpspreadsheetPath . 'src/PhpSpreadsheet/Writer/Xlsx.php';
        } else {
            throw new Exception("PhpSpreadsheet library not found. Please install via Composer: composer require phpoffice/phpspreadsheet");
        }
    }
} catch (Exception $e) {
    die("Error loading PhpSpreadsheet: " . $e->getMessage());
}

$database = new Database();
$db = $database->getConnection();

// Get RIS ID from URL
$ris_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get RIS information
$ris = null;
$ris_items = [];

if ($ris_id > 0) {
    // Get RIS details
    $query = "SELECT r.*, u.user as created_by_name, i.iar_number 
              FROM ris_records r 
              LEFT JOIN users u ON r.created_by = u.id 
              LEFT JOIN iar_records i ON r.iar_id = i.id 
              WHERE r.id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $ris_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ris = $result->fetch_assoc();
    $stmt->close();

    if ($ris) {
        // Get RIS items
        $items_query = "SELECT ri.*, i.name as item_name, i.unit_of_measure 
                       FROM ris_items ri 
                       JOIN items i ON ri.item_id = i.id 
                       WHERE ri.ris_id = ?";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bind_param("i", $ris_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $ris_items = $items_result->fetch_all(MYSQLI_ASSOC);
        $items_stmt->close();
    }
}

// Process Excel generation
if (isset($_GET['generate_excel']) && $ris_id > 0) {
    generateRISExcel($db, $ris_id);
    exit();
}

function generateRISExcel($db, $ris_id) {
    try {
        // Fetch RIS details
        $query = "SELECT r.*, u.user as created_by_name, i.iar_number, 
                         CONCAT(e.first_name, ' ', e.last_name) as requested_by_fullname,
                         e.position_id, p.position_name
                  FROM ris_records r 
                  LEFT JOIN users u ON r.created_by = u.id 
                  LEFT JOIN iar_records i ON r.iar_id = i.id 
                  LEFT JOIN employee e ON r.requested_by_id = e.emp_id
                  LEFT JOIN position p ON e.position_id = p.position_id
                  WHERE r.id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $ris_id);
        $stmt->execute();
        $ris = $stmt->get_result()->fetch_assoc();
        
        if (!$ris) {
            throw new Exception("RIS record not found");
        }
        
        // Fetch RIS items
        $items_query = "SELECT ri.*, i.name as item_name, i.unit_of_measure 
                       FROM ris_items ri 
                       JOIN items i ON ri.item_id = i.id 
                       WHERE ri.ris_id = ?";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bind_param("i", $ris_id);
        $items_stmt->execute();
        $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Load the RIS template
        $template_path = "../public/templates/RIS-FORM.xlsx";
        if (file_exists($template_path)) {
            $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($template_path);
        } else {
            // Create a basic RIS format if template doesn't exist
            $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set basic headers for RIS form
            $sheet->setCellValue('D1', 'REQUISITION AND ISSUE SLIP');
            $sheet->setCellValue('A3', 'Entity Name:');
            $sheet->setCellValue('A4', 'Division:');
            $sheet->setCellValue('A5', 'Office:');
            $sheet->setCellValue('F3', 'Fund Cluster:');
            $sheet->setCellValue('F4', 'Responsibility Center Code:');
            $sheet->setCellValue('F5', 'RIS No.:');
            $sheet->setCellValue('I3', 'Date:');
            
            // Item table headers
            $sheet->setCellValue('A7', 'REQUISITION');
            $sheet->setCellValue('A8', 'STOCK NO.');
            $sheet->setCellValue('B8', 'UNIT');
            $sheet->setCellValue('C8', 'DESCRIPTION');
            $sheet->setCellValue('E8', 'QUANTITY');
            $sheet->setCellValue('F8', 'STOCK AVAILABLE?');
            $sheet->setCellValue('G8', 'ISSUANCE');
            $sheet->setCellValue('H8', 'QUANTITY');
            $sheet->setCellValue('I8', 'REMARKS');
            
            $sheet->setCellValue('A10', 'Purpose:');
            
            // Signatories
            $sheet->setCellValue('B12', 'REQUESTED BY');
            $sheet->setCellValue('D12', 'APPROVED BY');
            $sheet->setCellValue('F12', 'ISSUED BY');
            $sheet->setCellValue('I12', 'RECEIVED BY');
            
            $sheet->setCellValue('A13', 'Signature:');
            $sheet->setCellValue('A14', 'Printed Name:');
            $sheet->setCellValue('A15', 'Designation:');
            $sheet->setCellValue('A16', 'Date:');
        }
        
        $sheet = $spreadsheet->getActiveSheet();
        
        // Fill in the header information
        $sheet->setCellValue('B3', 'National Irrigation Administration - ACIMO');
        $sheet->setCellValue('B4', $ris['requisition_office'] ?? '');
        $sheet->setCellValue('B5', 'Interim Albay-Catanduanes IMO, Tuburan, Ligao City');
        $sheet->setCellValue('I5', $ris['ris_number'] ?? '');
        $sheet->setCellValue('I3', $ris['created_at'] ? date('m/d/Y', strtotime($ris['created_at'])) : date('m/d/Y'));
        
        // Fill purpose
        $sheet->setCellValue('B10', $ris['purpose'] ?? '');
        
        // Fill requested by information
        $sheet->setCellValue('B14', $ris['requested_by'] ?? '');
        $sheet->setCellValue('B15', $ris['designation'] ?? '');
        $sheet->setCellValue('B16', $ris['created_at'] ? date('m/d/Y', strtotime($ris['created_at'])) : '');
        
        // Fill approved by (fixed values based on template)
        $sheet->setCellValue('D14', 'ENGR. MARK CLOYD G. SO, MPA');
        $sheet->setCellValue('D15', 'Acting Division Manager');
        
        // Fill issued by (fixed values based on template)
        $sheet->setCellValue('F14', 'LUISITO O. DEDASE');
        $sheet->setCellValue('F15', 'Property Officer B');
        
        // Start filling items from row 9 (similar to delivery_entry.php approach)
        $start_row = 9;
        
        // Count items and insert rows if needed (like in delivery_entry.php)
        $item_count = count($items);
        if ($item_count > 1) {
            $rows_to_insert = $item_count - 1;
            $sheet->insertNewRowBefore($start_row + 1, $rows_to_insert);
        }
        
        // Fill in item details (using the same pattern as delivery_entry.php)
        $row = $start_row;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $row, $item['unit_of_measure'] ?? '');
            $sheet->setCellValue('C' . $row, $item['description'] ?? $item['item_name']);
            $sheet->mergeCells('C' . $row . ':D' . $row);
            $sheet->setCellValue('E' . $row, $item['quantity'] ?? '');
            $sheet->setCellValue('H' . $row, $item['quantity'] ?? '');
            $row++;
        }
        
        // Set filename
        $filename = "RIS_" . ($ris['ris_number'] ?? $ris_id) . ".xlsx";
        
        
        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        
        // Output the file
        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo "Error generating RIS Excel: " . $e->getMessage();
    }
}

// Display helpers
function dv_text($value, $fallback = 'N/A') {
    $value = trim((string)($value ?? ''));
    return $value !== '' ? htmlspecialchars($value) : '<span class="text-muted font-italic">' . $fallback . '</span>';
}
function dv_date($value, $format = 'M j, Y', $fallback = 'N/A') {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '<span class="text-muted font-italic">' . $fallback . '</span>';
    }
    return date($format, strtotime($value));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RIS Details - Inventory Management</title>
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
        .card-tools .btn-primary {
            background: var(--h-primary) !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: .8rem !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .card-tools .btn-primary:hover { filter: brightness(1.1) !important; color:#fff !important; }


        /* ══ DETAIL VIEW ══ */
        .content { margin-top:-38px; }
        .dv-card .card-header { background: var(--h-card) !important; }
        .dv-back { color: var(--h-muted); font-size: .8rem; text-decoration: none; }
        .dv-back:hover { color: var(--h-primary); text-decoration: none; }
        .dv-status {
            display:inline-flex; align-items:center; gap:6px; padding:3px 12px; border-radius:20px;
            font-size:.72rem; font-weight:700; letter-spacing:.3px;
            background: var(--h-primary-dim); color: var(--h-primary); border:1px solid var(--h-border);
        }

        .dv-section-title {
            font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.7px;
            color: var(--h-muted); margin: 0 0 10px; display:flex; align-items:center; gap:8px;
        }
        .dv-section-title::after { content:''; flex:1; height:1px; background: var(--h-border); }

        .dv-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:12px; margin-bottom:22px; }
        @media (max-width: 991px) { .dv-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 575px) { .dv-grid { grid-template-columns: minmax(0, 1fr); } }
        .dv-tile {
            background: var(--h-card-alt); border:1px solid var(--h-border); border-radius:10px;
            padding:12px 16px; min-width:0;
        }
        .dv-tile-label { font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color: var(--h-muted); margin-bottom:4px; }
        .dv-tile-value { font-size:.95rem; font-weight:700; color: var(--h-text); word-break: break-word; }
        .dv-tile.is-wide { grid-column: span 2; }
        .dv-tile.is-accent { background: var(--h-primary-dim); border-color: var(--h-primary); }
        .dv-tile.is-accent .dv-tile-value { color: var(--h-primary); font-size:1.15rem; }
        @media (max-width: 575px) { .dv-tile.is-wide { grid-column: span 1; } }

        /* Items table */
        .dv-table-wrap { border:1px solid var(--h-border); border-radius:10px; overflow:hidden; margin-bottom:22px; }
        table.dv-table { width:100%; margin:0 !important; color: var(--h-text) !important; border:none !important; background: transparent !important; }
        table.dv-table thead th {
            background: var(--h-card-alt) !important; border:none !important; border-bottom:1px solid var(--h-border) !important;
            color: var(--h-muted) !important; font-size:.68rem !important; font-weight:700 !important;
            text-transform:uppercase !important; letter-spacing:.6px !important; padding:10px 14px !important; white-space:nowrap;
        }
        table.dv-table tbody td {
            border:none !important; border-top:1px solid var(--h-border) !important; padding:12px 14px !important;
            vertical-align:middle !important; font-size:.85rem !important; color: var(--h-text) !important; background: transparent !important;
        }
        table.dv-table tbody tr:first-child td { border-top:none !important; }
        table.dv-table tbody tr:hover td { background: var(--h-card-alt) !important; }
        table.dv-table .num { text-align:right; white-space:nowrap; font-variant-numeric: tabular-nums; }
        table.dv-table .item-name { font-weight:600; }
        table.dv-table .item-sub { display:block; font-size:.74rem; font-weight:400; color: var(--h-muted); margin-top:2px; white-space:normal; line-height:1.35; }
        table.dv-table tr.dv-total td {
            background: var(--h-primary-dim) !important; border-top:2px solid var(--h-primary) !important;
            font-weight:800; color: var(--h-text) !important;
        }
        table.dv-table tr.dv-total td.num-total { color: var(--h-primary) !important; font-size:.95rem !important; }

        /* Info cards */
        .dv-info-card { height:100%; }
        .dv-info-card .card-body { padding:6px 20px 14px !important; }
        .dv-row { display:flex; justify-content:space-between; gap:16px; padding:10px 0; border-bottom:1px dashed var(--h-border); font-size:.85rem; }
        .dv-row:last-child { border-bottom:none; }
        .dv-row .k { color: var(--h-muted); font-weight:600; }
        .dv-row .v { color: var(--h-text); font-weight:600; text-align:right; word-break: break-word; }
        .dv-row .v.is-empty { color: var(--h-muted); font-weight:400; font-style:italic; }

        /* Buttons */
        .dv-btn {
            display:inline-flex; align-items:center; gap:7px; border-radius:8px !important; font-weight:700 !important; font-size:.8rem !important;
            padding:7px 16px !important; border:1px solid transparent !important; cursor:pointer; transition: filter .12s, transform .12s;
        }
        .dv-btn:hover { filter:brightness(1.1); transform: translateY(-1px); text-decoration:none; }
        .dv-btn-primary { background: var(--h-primary) !important; color:#fff !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important; }
        .dv-btn-ghost { background: transparent !important; color: var(--h-text) !important; border-color: var(--h-border) !important; }
        .dv-btn-ghost:hover { color: var(--h-primary) !important; border-color: var(--h-primary) !important; }
        .card-footer { background: var(--h-card) !important; border-top:1px solid var(--h-border) !important; padding:14px 20px !important; }
        .alert-warning { background: rgba(230,119,0,.10) !important; color: var(--h-warning) !important; border:1px solid rgba(230,119,0,.25) !important; border-radius:10px !important; }

        @media print {
            .main-header, .main-sidebar, .pg-hero, .card-tools, .card-footer, .main-footer { display:none !important; }
            .content { margin-top:0 !important; padding:0 !important; }
            .content-wrapper { margin-left:0 !important; background:#fff !important; }
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
                <div class="pg-hero-title"><i class="fas fa-file-export"></i>RIS Details</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Requisition and Issue Slip<?= $ris ? ' ' . htmlspecialchars($ris['ris_number']) : '' ?> &middot; <a href="inventory.php" style="color:#24e78f;">Inventory</a> / <a href="ris_records.php" style="color:#24e78f;">RIS Records</a></p>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if ($ris): ?>
                <div class="card dv-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-file-export mr-2"></i>RIS: <?= htmlspecialchars($ris['ris_number']) ?></h3>
                        <div class="card-tools">
                            <a href="ris_view.php?id=<?= $ris_id ?>&generate_excel=1" class="dv-btn dv-btn-ghost">
                                <i class="fas fa-file-excel"></i> Generate RIS Excel
                            </a>
                            <a href="ris_records.php" class="dv-btn dv-btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to RIS Records
                            </a>
                        </div>
                    </div>
                    <div class="card-body">

                        <div class="dv-section-title">Requisition Information</div>
                        <div class="dv-grid">
                            <div class="dv-tile">
                                <div class="dv-tile-label">RIS Number</div>
                                <div class="dv-tile-value"><?= htmlspecialchars($ris['ris_number']) ?></div>
                            </div>
                            <div class="dv-tile is-wide">
                                <div class="dv-tile-label">Purpose</div>
                                <div class="dv-tile-value"><?= dv_text($ris['purpose']) ?></div>
                            </div>
                            <div class="dv-tile">
                                <div class="dv-tile-label">Requisition Office</div>
                                <div class="dv-tile-value"><?= dv_text($ris['requisition_office']) ?></div>
                            </div>
                            <div class="dv-tile is-wide">
                                <div class="dv-tile-label">Requested By</div>
                                <div class="dv-tile-value"><?= dv_text($ris['requested_by']) ?></div>
                            </div>
                            <div class="dv-tile">
                                <div class="dv-tile-label">Designation</div>
                                <div class="dv-tile-value"><?= dv_text($ris['designation']) ?></div>
                            </div>
                        </div>

                        <div class="dv-section-title">Items Issued</div>
                        <div class="dv-table-wrap">
                            <div class="table-responsive">
                                <table class="table dv-table">
                                    <thead>
                                        <tr>
                                            <th style="width:48px;">#</th>
                                            <th>Item</th>
                                            <th>Unit</th>
                                            <th class="num">Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $counter = 1;
                                        $total_quantity = 0;
                                        foreach ($ris_items as $item):
                                            $total_quantity += $item['quantity'];
                                        ?>
                                        <tr>
                                            <td><?= $counter++ ?></td>
                                            <td>
                                                <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                                <?php if (!empty($item['description']) && $item['description'] !== $item['item_name']): ?>
                                                    <span class="item-sub"><?= htmlspecialchars($item['description']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($item['unit_of_measure']) ?></td>
                                            <td class="num"><?= number_format($item['quantity']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($ris_items)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No items recorded for this RIS.</td></tr>
                                        <?php endif; ?>
                                        <tr class="dv-total">
                                            <td colspan="3" class="text-right">Total Quantity</td>
                                            <td class="num num-total"><?= number_format($total_quantity) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card dv-info-card mb-0">
                                    <div class="card-header"><h3 class="card-title"><i class="fas fa-link mr-2"></i>Reference Information</h3></div>
                                    <div class="card-body">
                                        <div class="dv-row"><span class="k">IAR Reference</span><span class="v"><?= dv_text($ris['iar_number'] ?? '') ?></span></div>
                                        <div class="dv-row"><span class="k">RIS Number</span><span class="v"><?= htmlspecialchars($ris['ris_number']) ?></span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card dv-info-card mb-0">
                                    <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>System Information</h3></div>
                                    <div class="card-body">
                                        <div class="dv-row"><span class="k">Created By</span><span class="v"><?= dv_text($ris['created_by_name'] ?? '') ?></span></div>
                                        <div class="dv-row"><span class="k">Date Created</span><span class="v"><?= dv_date($ris['created_at'], 'M j, Y g:i A') ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <h4>RIS not found</h4>
                        <p>Please select a valid RIS from the records.</p>
                        <a href="ris_records.php" class="dv-btn dv-btn-primary">Back to RIS Records</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>