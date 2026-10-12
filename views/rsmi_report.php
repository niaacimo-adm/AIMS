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

// Load PhpSpreadsheet (same approach as delivery_entry.php)
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
            throw new Exception("PhpSpreadsheet library not found. Install via Composer: composer require phpoffice/phpspreadsheet");
        }
    }
} catch (Exception $e) {
    die("Error loading PhpSpreadsheet: " . $e->getMessage());
}

$database = new Database();
$db = $database->getConnection();

const RSMI_ITEM_ROWS  = 14;  // item rows on the template form (13-26)
const RSMI_RECAP_ROWS = 9;   // recapitulation rows on the template form (29-37)
const RSMI_FIRST_ITEM_ROW = 13;

$month_names = [1 => 'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];

/* ---------------------------------------------------------------
 * Data helpers
 * ------------------------------------------------------------- */

/** Does $table.$column exist? (cached) */
function rsmiHasColumn($db, $table, $column) {
    static $cache = [];
    $key = "$table.$column";
    if (!isset($cache[$key])) {
        $t = $db->real_escape_string($table);
        $c = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        $cache[$key] = ($res && $res->num_rows > 0);
    }
    return $cache[$key];
}

/**
 * Unit cost of an issued item: taken from the IAR the RIS was created from,
 * otherwise from the latest stock-in of the item on/before the RIS date,
 * otherwise from the latest stock-in overall.
 */
function rsmiUnitCost($db, $iar_id, $item_id, $ris_created_at) {
    static $cache = [];
    $key = intval($iar_id) . '|' . intval($item_id) . '|' . substr((string)$ris_created_at, 0, 10);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $cost = null;

    if (!empty($iar_id)) {
        $q = $db->prepare("SELECT ii.unit_price
                           FROM iar_items ii
                           JOIN delivery_items di ON di.id = ii.delivery_item_id
                           WHERE ii.iar_id = ? AND di.item_id = ? LIMIT 1");
        $q->bind_param("ii", $iar_id, $item_id);
        $q->execute();
        $hit = $q->get_result()->fetch_assoc();
        $q->close();
        if ($hit && $hit['unit_price'] !== null) {
            $cost = (float)$hit['unit_price'];
        }
    }
    if ($cost === null) {
        $q = $db->prepare("SELECT unit_cost FROM stock_movements
                           WHERE item_id = ? AND movement_type = 'in' AND unit_cost IS NOT NULL AND created_at <= ?
                           ORDER BY id DESC LIMIT 1");
        $q->bind_param("is", $item_id, $ris_created_at);
        $q->execute();
        $hit = $q->get_result()->fetch_assoc();
        $q->close();
        if ($hit) {
            $cost = (float)$hit['unit_cost'];
        }
    }
    if ($cost === null) {
        $q = $db->prepare("SELECT unit_cost FROM stock_movements
                           WHERE item_id = ? AND movement_type = 'in' AND unit_cost IS NOT NULL
                           ORDER BY id DESC LIMIT 1");
        $q->bind_param("i", $item_id);
        $q->execute();
        $hit = $q->get_result()->fetch_assoc();
        $q->close();
        if ($hit) {
            $cost = (float)$hit['unit_cost'];
        }
    }
    return $cache[$key] = ($cost ?? 0.0);
}

/**
 * All RIS lines issued in a date range [$from, $to).
 * Each line: ris_no, rcc, stock_no, item, unit, qty, unit_cost, amount, uacs, item_id, month
 */
function fetchRsmiLines($db, $from, $to) {
    $stock_col = rsmiHasColumn($db, 'items', 'stock_no') ? 'i.stock_no' : "''";
    $uacs_col  = rsmiHasColumn($db, 'items', 'uacs_code') ? 'i.uacs_code'
               : (rsmiHasColumn($db, 'items', 'uacs_object_code') ? 'i.uacs_object_code' : "''");

    $sql = "SELECT rr.id AS ris_id, rr.ris_number, rr.iar_id, rr.requisition_office, rr.created_at,
                   ri.id AS ris_item_id, ri.item_id, ri.description AS name, ri.unit, ri.quantity,
                   $stock_col AS stock_no, $uacs_col AS uacs_code
            FROM ris_items ri
            JOIN ris_records rr ON rr.id = ri.ris_id
            LEFT JOIN items i   ON i.id = ri.item_id
            WHERE rr.created_at >= ? AND rr.created_at < ?
            ORDER BY rr.created_at, rr.id, ri.id";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $lines = [];
    foreach ($rows as $r) {
        $qty  = (int)$r['quantity'];
        $cost = rsmiUnitCost($db, $r['iar_id'], $r['item_id'], $r['created_at']);
        $lines[] = [
            'ris_no'    => $r['ris_number'],
            'rcc'       => $r['requisition_office'],
            'stock_no'  => (string)$r['stock_no'],
            'item'      => $r['name'],
            'unit'      => $r['unit'],
            'qty'       => $qty,
            'unit_cost' => $cost,
            'amount'    => round($qty * $cost, 2),
            'uacs'      => (string)$r['uacs_code'],
            'item_id'   => (int)$r['item_id'],
            'month'     => (int)date('n', strtotime($r['created_at'])),
        ];
    }
    return $lines;
}

/** Lines for one month of a year. */
function fetchRsmiMonth($db, $year, $month) {
    $from = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    $to   = date('Y-m-d 00:00:00', strtotime($from . ' +1 month'));
    return fetchRsmiLines($db, $from, $to);
}

/** Recapitulation: one row per item (qty, weighted unit cost, total cost, UACS). */
function buildRsmiRecap(array $lines) {
    $recap = [];
    foreach ($lines as $l) {
        $k = $l['item_id'] . '|' . $l['stock_no'] . '|' . $l['item'];
        if (!isset($recap[$k])) {
            $recap[$k] = ['stock_no' => $l['stock_no'], 'item' => $l['item'], 'qty' => 0, 'total' => 0.0, 'uacs' => $l['uacs']];
        }
        $recap[$k]['qty']   += $l['qty'];
        $recap[$k]['total'] += $l['amount'];
    }
    foreach ($recap as &$r) {
        $r['unit_cost'] = $r['qty'] > 0 ? round($r['total'] / $r['qty'], 2) : 0.0;
    }
    unset($r);
    uasort($recap, function ($a, $b) { return strcasecmp($a['item'], $b['item']); });
    return array_values($recap);
}

/* ---------------------------------------------------------------
 * Excel generation (fills the RSMI-REPORT.xlsx template)
 * ------------------------------------------------------------- */

/** Fill one worksheet of the template with one month's data. */
function fillRsmiSheet($sheet, array $lines, $period_label, $serial_no, $fund_cluster, $custodian, $accountant) {
    $text  = PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
    $align = PhpOffice\PhpSpreadsheet\Style\Alignment::class;

    $recap = buildRsmiRecap($lines);

    // Grow the form when the month has more rows than the template holds.
    $extra_items = max(0, count($lines) - RSMI_ITEM_ROWS);
    if ($extra_items > 0) {
        $insert_at = RSMI_FIRST_ITEM_ROW + RSMI_ITEM_ROWS;           // row 27 (Recapitulation header)
        $sheet->insertNewRowBefore($insert_at, $extra_items);
        for ($i = 0; $i < $extra_items; $i++) {
            $r = $insert_at + $i;
            $sheet->duplicateStyle($sheet->getStyle('A' . ($insert_at - 1) . ':I' . ($insert_at - 1)), "A{$r}:I{$r}");
            $sheet->getRowDimension($r)->setRowHeight(15.75);
        }
    }
    $recap_first = 29 + $extra_items;
    $extra_recap = max(0, count($recap) - RSMI_RECAP_ROWS);
    if ($extra_recap > 0) {
        $insert_at = $recap_first + RSMI_RECAP_ROWS;                 // first row after the recap block
        $sheet->insertNewRowBefore($insert_at, $extra_recap);
        for ($i = 0; $i < $extra_recap; $i++) {
            $r = $insert_at + $i;
            $sheet->duplicateStyle($sheet->getStyle('A' . ($insert_at - 1) . ':I' . ($insert_at - 1)), "A{$r}:I{$r}");
            $sheet->getRowDimension($r)->setRowHeight(15.75);
        }
    }
    $shift = $extra_items + $extra_recap;

    // Header block
    $sheet->setCellValueExplicit('G8', '' . $serial_no, $text);
    $sheet->setCellValueExplicit('B9', '' . $fund_cluster, $text);
    $sheet->setCellValueExplicit('G9', '' . $period_label, $text);

    // Item lines
    $row = RSMI_FIRST_ITEM_ROW;
    foreach ($lines as $l) {
        $sheet->setCellValueExplicit("A{$row}", $l['ris_no'], $text);
        $sheet->setCellValueExplicit("B{$row}", $l['rcc'], $text);
        $sheet->setCellValueExplicit("C{$row}", $l['stock_no'], $text);
        $sheet->setCellValueExplicit("D{$row}", $l['item'], $text);
        $sheet->setCellValueExplicit("E{$row}", $l['unit'], $text);
        $sheet->setCellValue("F{$row}", $l['qty']);
        $sheet->setCellValue("G{$row}", $l['unit_cost']);
        $sheet->setCellValue("H{$row}", $l['amount']);

        $sheet->getStyle("A{$row}:H{$row}")->getAlignment()->setVertical($align::VERTICAL_CENTER);
        foreach (['A', 'B', 'C', 'E', 'F'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal($align::HORIZONTAL_CENTER)->setShrinkToFit(true);
        }
        $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal($align::HORIZONTAL_LEFT)->setWrapText(true);
        foreach (['G', 'H'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal($align::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sheet->getRowDimension($row)->setRowHeight(max(15.75, 15.75 * (int)ceil(mb_strlen($l['item']) / 24)));
        $row++;
    }

    // Recapitulation
    $row = $recap_first;
    foreach ($recap as $r) {
        $sheet->setCellValueExplicit("B{$row}", $r['stock_no'] !== '' ? $r['stock_no'] : $r['item'], $text);
        $sheet->setCellValue("C{$row}", $r['qty']);
        $sheet->setCellValue("F{$row}", $r['unit_cost']);
        $sheet->setCellValue("G{$row}", round($r['total'], 2));
        $sheet->setCellValueExplicit("H{$row}", $r['uacs'], $text);
        foreach (['B', 'C', 'H'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal($align::HORIZONTAL_CENTER)->setShrinkToFit(true);
        }
        foreach (['F', 'G'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal($align::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $row++;
    }

    // Signatories (template rows 41 shifted by the inserted rows)
    $sig = 41 + $shift;
    $sheet->setCellValueExplicit("A{$sig}", $custodian, $text);
    $sheet->setCellValueExplicit("F{$sig}", $accountant, $text);
    foreach (["A{$sig}", "F{$sig}"] as $cell) {
        $sheet->getStyle($cell)->getAlignment()->setHorizontal($align::HORIZONTAL_CENTER)
              ->setVertical($align::VERTICAL_BOTTOM)->setShrinkToFit(true);
    }

    $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
    $sheet->getPageSetup()->setFitToPage(true);
}

/**
 * Download RSMI as .xlsx. $month = 1-12 for one month, 0 for the whole year
 * (one sheet per month that has issuances).
 */
function generateRsmiExcel($db, $year, $month, $serial_no, $fund_cluster, $custodian, $accountant) {
    global $month_names;
    try {
        $template_path = "../public/templates/RSMI-REPORT.xlsx";
        if (!file_exists($template_path)) {
            throw new Exception("RSMI template not found (public/templates/RSMI-REPORT.xlsx)");
        }

        $months = $month > 0 ? [$month] : range(1, 12);
        $data = [];
        foreach ($months as $m) {
            $lines = fetchRsmiMonth($db, $year, $m);
            if (!empty($lines)) {
                $data[$m] = $lines;
            }
        }
        if (empty($data)) {
            throw new Exception("No supplies were issued for the selected period.");
        }

        $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($template_path);
        $base = $spreadsheet->getSheet(0);

        // Copy the untouched template once per month BEFORE filling anything
        // (cloning inside the same workbook keeps the cell styles intact).
        $sheets = [$base];
        for ($i = 1; $i < count($data); $i++) {
            $copy = clone $base;
            $copy->setTitle('RSMI ' . ($i + 1));
            $spreadsheet->addSheet($copy);
            $sheets[] = $copy;
        }

        $i = 0;
        foreach ($data as $m => $lines) {
            $sheet = $sheets[$i++];
            $sheet->setTitle(substr($month_names[$m], 0, 3) . ' ' . $year);
            $serial = $serial_no !== '' ? $serial_no : sprintf('%04d-%02d', $year, $m);
            fillRsmiSheet($sheet, $lines, $month_names[$m] . ' ' . $year, $serial, $fund_cluster, $custodian, $accountant);
        }
        $spreadsheet->setActiveSheetIndex(0);

        $filename = $month > 0
            ? sprintf('RSMI_%04d-%02d.xlsx', $year, $month)
            : sprintf('RSMI_%04d_Whole_Year.xlsx', $year);

        if (ob_get_length()) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    } catch (Exception $e) {
        error_log($e->getMessage());
        $_SESSION['flash_error'] = "Error generating RSMI: " . $e->getMessage();
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?year=' . intval($year) . '&month=' . intval($month));
    }
}

/* ---------------------------------------------------------------
 * Request handling
 * ------------------------------------------------------------- */

$flash_error = '';
if (isset($_SESSION['flash_error'])) { $flash_error = $_SESSION['flash_error']; unset($_SESSION['flash_error']); }

// Year list: every year that has RIS records, plus the current year
$years = [(int)date('Y')];
$yr = $db->query("SELECT DISTINCT YEAR(created_at) AS y FROM ris_records WHERE created_at IS NOT NULL ORDER BY y DESC");
if ($yr) {
    while ($r = $yr->fetch_assoc()) {
        if ($r['y']) { $years[] = (int)$r['y']; }
    }
}
$years = array_values(array_unique($years));
rsort($years);

$sel_year  = intval($_REQUEST['year'] ?? date('Y'));
if (!in_array($sel_year, $years, true)) { $sel_year = $years[0]; }
$sel_month = intval($_REQUEST['month'] ?? date('n'));          // 0 = whole year
if ($sel_month < 0 || $sel_month > 12) { $sel_month = (int)date('n'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_rsmi'])) {
    generateRsmiExcel(
        $db, $sel_year, $sel_month,
        trim($_POST['serial_no'] ?? ''),
        trim($_POST['fund_cluster'] ?? ''),
        trim($_POST['custodian'] ?? ''),
        trim($_POST['accountant'] ?? '')
    );
    exit();
}

// Whole-year data drives both the monthly overview and the preview
$year_lines = fetchRsmiLines($db, sprintf('%04d-01-01 00:00:00', $sel_year), sprintf('%04d-01-01 00:00:00', $sel_year + 1));
$overview = [];
foreach (range(1, 12) as $m) { $overview[$m] = ['ris' => 0, 'lines' => 0, 'qty' => 0, 'amount' => 0.0]; }
foreach ($year_lines as $l) {
    $overview[$l['month']]['lines']++;
    $overview[$l['month']]['qty']    += $l['qty'];
    $overview[$l['month']]['amount'] += $l['amount'];
}
$ris_per_month = [];
foreach ($year_lines as $l) { $ris_per_month[$l['month']][$l['ris_no']] = true; }
foreach ($ris_per_month as $m => $set) { $overview[$m]['ris'] = count($set); }

$preview = $sel_month > 0
    ? array_values(array_filter($year_lines, function ($l) use ($sel_month) { return $l['month'] === $sel_month; }))
    : $year_lines;
$preview_recap = buildRsmiRecap($preview);
$preview_total = array_sum(array_column($preview, 'amount'));
$period_label  = $sel_month > 0 ? $month_names[$sel_month] . ' ' . $sel_year : 'January - December ' . $sel_year;

// Default signatory = logged-in employee
$default_custodian = '';
$cur_emp_id = intval($_SESSION['emp_id'] ?? 0);
if ($cur_emp_id) {
    $s = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM employee WHERE emp_id = ?");
    $s->bind_param("i", $cur_emp_id);
    $s->execute();
    $e = $s->get_result()->fetch_assoc();
    $s->close();
    $default_custodian = $e['full_name'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSMI Report - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        :root {
            --h-bg:#eef7f2; --h-card:#ffffff; --h-card-alt:#f0faf5; --h-border:rgba(42,152,99,0.18);
            --h-text:#0f2d1e; --h-muted:#4a7a5e; --h-primary:#2a9863; --h-primary-dim:rgba(42,152,99,.10);
            --h-danger:#c92a2a; --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
        }
        body.dark-mode {
            --h-bg:#0b1f17; --h-card:#102f22; --h-card-alt:#0e2619; --h-border:rgba(36,231,143,0.12);
            --h-text:#d4f5e5; --h-muted:#6aad8a; --h-primary:#24e78f; --h-primary-dim:rgba(36,231,143,.12);
            --h-danger:#ff6b6b; --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
        }
        .content-wrapper { background: var(--h-bg) !important; }
        .main-header.navbar, .main-header, nav.main-header, header.main-header { z-index: 1000 !important; }
        .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
        .main-sidebar, aside.main-sidebar { z-index: 999 !important; }

        @keyframes pgHeroMeshDrift { 0% { transform:translate(0,0) rotate(0deg); } 100% { transform:translate(3%,2%) rotate(2deg); } }
        .pg-hero { background:#0b1f17; padding:36px 28px 66px; position:relative; overflow:hidden; }
        .pg-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:pgHeroMeshDrift 22s ease-in-out infinite alternate; z-index:0;
        }
        .pg-hero::after { content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px; background:var(--body-bg, #eef7f2); clip-path:ellipse(58% 100% at 50% 100%); z-index:1; }
        body.dark-mode .pg-hero::after { background:var(--body-bg, #0b1f17); }
        .pg-hero-inner { position:relative; z-index:2; }
        .pg-hero-title { color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px; display:flex; align-items:center; gap:10px; text-shadow:0 2px 14px rgba(0,0,0,.45); }
        .pg-hero-sub { color:rgba(212,245,229,.75); margin:0 0 14px; font-size:.9rem; }
        .pg-hero-divider { width:48px; height:2px; border-radius:2px; margin:0 0 12px; background:linear-gradient(90deg,transparent,#24e78f,transparent); }

        .card { background: var(--h-card) !important; border: 1px solid var(--h-border) !important; border-radius: 12px !important; box-shadow: var(--h-shadow-sm) !important; }
        .card-header { background: var(--h-card) !important; border-bottom: 1px solid var(--h-border) !important; padding: 14px 20px !important; display:flex !important; align-items:center !important; justify-content:space-between !important; flex-wrap:wrap !important; gap:10px !important; }
        .card-title { font-weight: 700 !important; font-size: .9rem !important; color: var(--h-text) !important; margin: 0 !important; }
        .card-body { background: var(--h-card) !important; padding: 18px !important; color: var(--h-text); }
        .form-group label { color: var(--h-muted); font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
        .btn-rsmi { background: var(--h-primary) !important; color:#fff !important; border:none !important; border-radius:8px !important; font-weight:700 !important; font-size:.82rem !important; }
        .btn-rsmi:hover { filter: brightness(1.1); }
        body.dark-mode .btn-rsmi { color:#062014 !important; }

        .stat-box { background: var(--h-card-alt); border:1px solid var(--h-border); border-radius:10px; padding:12px 16px; height:100%; }
        .stat-box .lbl { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--h-muted); }
        .stat-box .val { font-size:1.35rem; font-weight:800; color:var(--h-text); }

        table.rsmi-tbl.table thead th {
            background: var(--h-card-alt) !important; border:none !important; border-bottom:1px solid var(--h-border) !important;
            color: var(--h-muted) !important; font-size:.68rem !important; font-weight:700 !important;
            text-transform:uppercase !important; letter-spacing:.6px !important; padding:10px 12px !important;
        }
        table.rsmi-tbl.table tbody td { border-top:1px solid var(--h-border) !important; padding:9px 12px !important; font-size:.84rem !important; color:var(--h-text) !important; vertical-align:middle !important; }
        table.rsmi-tbl.table tbody tr:hover { background: var(--h-card-alt) !important; }
        tr.month-active td { background: var(--h-primary-dim) !important; font-weight:700; }
        tr.month-empty td { opacity:.55; }
        tfoot td { font-weight:800; border-top:2px solid var(--h-border) !important; color:var(--h-text) !important; }
        .num { text-align:right; font-variant-numeric: tabular-nums; }

        /* ── Compact layout: keep everything on one screen ── */
        .pg-hero { padding:20px 28px 50px !important; }
        .pg-hero-title { font-size:1.4rem !important; margin-bottom:2px !important; }
        .pg-hero-sub { margin-bottom:6px !important; }
        .pg-hero-divider { margin-bottom:6px !important; }
        .content { margin-top:-30px !important; }
        .form-group label { font-size:.68rem; }
        table.rsmi-tbl.compact.table thead th { padding:6px 10px !important; position:sticky; top:0; z-index:2; }
        table.rsmi-tbl.compact.table tbody td, table.rsmi-tbl.compact.table tfoot td { padding:4px 10px !important; font-size:.8rem !important; }
        .scroll-pane { max-height:calc(100vh - 360px); min-height:220px; overflow:auto; }
        tr.month-link { cursor:pointer; }
        .stat-strip { display:flex; gap:14px; font-size:.78rem; color:var(--h-muted); flex-wrap:wrap; }
        .stat-strip b { color:var(--h-text); }
        .nav-tabs .nav-link { font-size:.8rem; color:var(--h-muted); }
        .nav-tabs .nav-link.active { color:var(--h-primary) !important; font-weight:700; background:transparent !important; }
        .nav-tabs { border-color:var(--h-border) !important; }

        /* ── UI fixes ── */
        .card-body.p-0 { padding:0 !important; }
        .rs-tabs { display:flex; gap:4px; padding:8px 12px 0; border-bottom:1px solid var(--h-border); }
        .rs-tab { background:transparent; border:none; border-bottom:3px solid transparent; padding:6px 14px; font-size:.82rem; font-weight:600; color:var(--h-muted); cursor:pointer; }
        .rs-tab:hover { color:var(--h-text); }
        .rs-tab.active { color:var(--h-primary); border-bottom-color:var(--h-primary); font-weight:700; }
        .stat-strip { margin-left:auto; }
        .stat-strip span { background:var(--h-primary-dim); border-radius:20px; padding:2px 10px; }
        .scroll-pane { max-height:calc(100vh - 330px); }
        .form-control-sm { background:var(--h-card) !important; color:var(--h-text) !important; border-color:var(--h-border) !important; }
        #rsmiOptions { border-top:1px dashed var(--h-border); margin-top:6px; }
        table.rsmi-tbl.compact thead th { background:var(--h-card-alt) !important; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_inventory.php'; ?>

    <div class="content-wrapper">
        <div class="pg-hero">
            <div class="pg-hero-mesh"></div>
            <div class="pg-hero-inner">
                <div class="pg-hero-title"><i class="fas fa-file-invoice"></i>RSMI Report</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Report of Supplies and Materials Issued &mdash; generated per month and per year from RIS records</p>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- Filter + download (one compact row) -->
                <div class="card mb-2">
                    <div class="card-body py-2">
                        <form method="POST" action="" id="rsmiForm">
                            <div class="form-row align-items-end">
                                <div class="col-auto form-group mb-1">
                                    <label for="year" class="mb-0">Year</label>
                                    <select name="year" id="year" class="form-control form-control-sm">
                                        <?php foreach ($years as $y): ?>
                                            <option value="<?= $y ?>" <?= $y === $sel_year ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-auto form-group mb-1">
                                    <label for="month" class="mb-0">Month</label>
                                    <select name="month" id="month" class="form-control form-control-sm">
                                        <option value="0" <?= $sel_month === 0 ? 'selected' : '' ?>>Whole Year</option>
                                        <?php foreach ($month_names as $num => $name): ?>
                                            <option value="<?= $num ?>" <?= $num === $sel_month ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-auto form-group mb-1">
                                    <button type="button" class="btn btn-sm btn-rsmi" id="viewBtn"><i class="fas fa-eye mr-1"></i> View</button>
                                    <button type="submit" name="download_rsmi" value="1" class="btn btn-sm btn-rsmi ml-1" <?= empty($preview) ? 'disabled' : '' ?>>
                                        <i class="fas fa-file-excel mr-1"></i> Download Excel
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary ml-1" id="optToggle">
                                        <i class="fas fa-sliders-h mr-1"></i> Signatories / Serial No.
                                    </button>
                                </div>
                            </div>
                            <div id="rsmiOptions" style="display:none;">
                                <div class="form-row pt-2">
                                    <div class="col-md-3 form-group mb-1">
                                        <label for="serial_no" class="mb-0">Serial No.</label>
                                        <input type="text" class="form-control form-control-sm" id="serial_no" name="serial_no" placeholder="Auto: YYYY-MM" autocomplete="off">
                                    </div>
                                    <div class="col-md-3 form-group mb-1">
                                        <label for="fund_cluster" class="mb-0">Fund Cluster</label>
                                        <input type="text" class="form-control form-control-sm" id="fund_cluster" name="fund_cluster" autocomplete="off">
                                    </div>
                                    <div class="col-md-3 form-group mb-1">
                                        <label for="custodian" class="mb-0">Supply / Property Custodian</label>
                                        <input type="text" class="form-control form-control-sm" id="custodian" name="custodian" value="<?= htmlspecialchars($default_custodian) ?>" autocomplete="off">
                                    </div>
                                    <div class="col-md-3 form-group mb-1">
                                        <label for="accountant" class="mb-0">Designated Accounting Staff</label>
                                        <input type="text" class="form-control form-control-sm" id="accountant" name="accountant" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <!-- Year overview (left) -->
                    <div class="col-lg-4 mb-2">
                        <div class="card h-100 mb-0">
                            <div class="card-header py-2"><h3 class="card-title"><i class="fas fa-calendar-alt mr-2"></i><?= $sel_year ?> Overview</h3></div>
                            <div class="card-body p-0">
                                <table class="table rsmi-tbl compact mb-0">
                                    <thead>
                                        <tr><th>Month</th><th class="num">RIS</th><th class="num">Qty</th><th class="num">Amount</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($month_names as $num => $name):
                                            $o = $overview[$num]; ?>
                                            <tr class="<?= $num === $sel_month ? 'month-active' : '' ?> <?= $o['lines'] === 0 ? 'month-empty' : 'month-link' ?>"
                                                <?= $o['lines'] > 0 ? 'onclick="location.href=\'?year=' . $sel_year . '&month=' . $num . '\'"' : '' ?>>
                                                <td><?= $name ?></td>
                                                <td class="num"><?= $o['ris'] ?></td>
                                                <td class="num"><?= number_format($o['qty']) ?></td>
                                                <td class="num"><?= number_format($o['amount'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td>Total</td>
                                            <td class="num"><?= array_sum(array_column($overview, 'ris')) ?></td>
                                            <td class="num"><?= number_format(array_sum(array_column($overview, 'qty'))) ?></td>
                                            <td class="num"><?= number_format(array_sum(array_column($overview, 'amount')), 2) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Preview (right) -->
                    <div class="col-lg-8 mb-2">
                        <div class="card h-100 mb-0">
                            <div class="card-header py-2">
                                <h3 class="card-title"><i class="fas fa-list mr-2"></i><?= htmlspecialchars($period_label) ?></h3>
                                <div class="stat-strip">
                                    <span><b><?= count(array_unique(array_column($preview, 'ris_no'))) ?></b> RIS</span>
                                    <span><b><?= count($preview) ?></b> lines</span>
                                    <span><b><?= number_format(array_sum(array_column($preview, 'qty'))) ?></b> qty</span>
                                    <span><b>&#8369;<?= number_format($preview_total, 2) ?></b></span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($preview)): ?>
                                    <p class="text-muted p-3 mb-0">No supplies were issued in this period.</p>
                                <?php else: ?>
                                    <div class="rs-tabs">
                                        <button type="button" class="rs-tab active" data-pane="tabLines">Issued Items</button>
                                        <button type="button" class="rs-tab" data-pane="tabRecap">Recapitulation</button>
                                    </div>
                                    <div>
                                        <div class="rs-pane" id="tabLines">
                                            <div class="scroll-pane">
                                                <table class="table rsmi-tbl compact mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>RIS No.</th><th>Resp. Center</th><th>Stock No.</th><th>Item</th><th>Unit</th>
                                                            <th class="num">Qty</th><th class="num">Unit Cost</th><th class="num">Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($preview as $l): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($l['ris_no']) ?></td>
                                                                <td><?= htmlspecialchars($l['rcc']) ?></td>
                                                                <td><?= htmlspecialchars($l['stock_no']) ?></td>
                                                                <td><?= htmlspecialchars($l['item']) ?></td>
                                                                <td><?= htmlspecialchars($l['unit']) ?></td>
                                                                <td class="num"><?= number_format($l['qty']) ?></td>
                                                                <td class="num"><?= number_format($l['unit_cost'], 2) ?></td>
                                                                <td class="num"><?= number_format($l['amount'], 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="rs-pane" id="tabRecap" style="display:none;">
                                            <div class="scroll-pane">
                                                <table class="table rsmi-tbl compact mb-0">
                                                    <thead>
                                                        <tr><th>Stock No. / Item</th><th class="num">Quantity</th><th class="num">Unit Cost</th><th class="num">Total Cost</th><th>UACS Object Code</th></tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($preview_recap as $r): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($r['stock_no'] !== '' ? $r['stock_no'] . ' - ' . $r['item'] : $r['item']) ?></td>
                                                                <td class="num"><?= number_format($r['qty']) ?></td>
                                                                <td class="num"><?= number_format($r['unit_cost'], 2) ?></td>
                                                                <td class="num"><?= number_format($r['total'], 2) ?></td>
                                                                <td><?= htmlspecialchars($r['uacs']) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    $('#optToggle').on('click', function() { $('#rsmiOptions').slideToggle(120); });
    $('.rs-tab').on('click', function() {
        $('.rs-tab').removeClass('active'); $(this).addClass('active');
        $('.rs-pane').hide(); $('#' + $(this).data('pane')).show();
    });

    // "View" reloads the page for the chosen period (GET, so the URL can be bookmarked)
    $('#viewBtn').on('click', function() {
        window.location.href = 'rsmi_report.php?year=' + encodeURIComponent($('#year').val()) +
                               '&month=' + encodeURIComponent($('#month').val());
    });

    <?php if ($flash_error !== ''): ?>
    Swal.fire({ icon: 'error', title: 'Error!', text: <?= json_encode($flash_error) ?>, confirmButtonColor: '#c92a2a' });
    <?php endif; ?>
});
</script>
</body>
</html>