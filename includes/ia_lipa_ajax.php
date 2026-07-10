<?php
/**
 * ia_lipa_ajax.php
 * Backend for the LIPA (List of Irrigated and Planted Area) module.
 *
 * Actions:
 *   list            - list entries for an ia_profile_id (+ optional crop_year, season, sector filters) with summary totals
 *   get_filters     - distinct crop_year/season/sector combos available for an ia_profile_id
 *   get_entry       - single entry (for edit modal)
 *   add             - manual add of one entry
 *   update          - edit one entry
 *   delete          - delete one entry
 *   delete_period   - delete all entries for ia_profile_id + crop_year + season (used before re-import)
 *   import          - upload an .xlsx (same layout as the LIPA template: one sheet per sector) and bulk-insert
 */

// Buffer everything from the very first line. If any included file (or
// PhpSpreadsheet internally) emits a notice/warning/deprecation, it lands in
// this buffer instead of streaming straight to the browser - which matters
// because the xlsx export writes binary data to php://output later, and any
// stray text mixed into that binary corrupts the file (Excel then reports
// "file format or file extension is not valid").
ob_start();

require_once '../config/database.php';
require_once '../includes/auth.php';

// PhpSpreadsheet (already used elsewhere in this project for report generation/import)
$__autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($__autoload_path)) {
    require_once $__autoload_path;
}

// Importing a multi-sheet workbook can be memory/time heavy - raise limits
// defensively for this request only (won't affect other pages). This is a
// safety net; the read filter in importLipaFile() is the real fix for
// corrupted workbooks with an inflated "used range".
@ini_set('memory_limit', '512M');
@set_time_limit(120);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Deprecated/notice-level messages from vendor code (e.g. PhpSpreadsheet on
// newer PHP versions) must never be echoed to output - this endpoint streams
// binary xlsx data, and any stray HTML text spliced into that stream
// corrupts the file. Log errors instead of displaying them.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// TEMPORARY DIAGNOSTIC LOGGING - remove once the large-export issue is
// resolved. Fatal errors (out-of-memory, timeouts) are NOT catchable with
// try/catch, and depending on your php.ini's error_log setting they may be
// going somewhere you're not checking (or nowhere at all, e.g. on some
// Windows/XAMPP setups). This forces them into a file we control, and logs
// timing/memory at each major step so we can see exactly where a large
// export dies.
define('LIPA_DEBUG_LOG', __DIR__ . '/lipa_export_debug.log');
ini_set('error_log', LIPA_DEBUG_LOG);
function lipaDebugLog($msg) {
    $mem = round(memory_get_usage(true) / 1048576, 1);
    $peak = round(memory_get_peak_usage(true) / 1048576, 1);
    @file_put_contents(LIPA_DEBUG_LOG, sprintf(
        "[%s] mem=%sMB peak=%sMB %s\n",
        date('Y-m-d H:i:s'), $mem, $peak, $msg
    ), FILE_APPEND);
}
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        lipaDebugLog('FATAL: ' . $err['message'] . ' in ' . $err['file'] . ' on line ' . $err['line']);
    } else {
        lipaDebugLog('Request ended normally (or connection closed by client).');
    }
});
lipaDebugLog('--- Request start: ' . ($_SERVER['REQUEST_URI'] ?? ''));

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        listLipaEntries($db);
        break;
    case 'get_filters':
        getLipaFilters($db);
        break;
    case 'get_entry':
        getLipaEntry($db);
        break;
    case 'add':
        addLipaEntry($db);
        break;
    case 'update':
        updateLipaEntry($db);
        break;
    case 'delete':
        deleteLipaEntry($db);
        break;
    case 'delete_period':
        deleteLipaPeriod($db);
        break;
    case 'import':
        importLipaFile($db);
        break;
    case 'export':
        exportLipaReport($db);
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
}

// ============================================================
// Helpers
// ============================================================

function requireLipaPermission() {
    // Reuses the existing IA-profile permission; swap for a dedicated
    // 'manage_lipa' permission key if you add one to your permissions table.
    if (!hasPermission('manage_ia_profiles')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
}

/**
 * Normalize a cell value that might be a string date, an Excel serial
 * number, or a PHP DateTime (PhpSpreadsheet returns DateTime for
 * date-formatted cells) into 'Y-m-d' or null.
 */
function normalizeLipaDate($value) {
    if ($value === null || $value === '') return null;

    if ($value instanceof \DateTimeInterface) {
        return $value->format('Y-m-d');
    }

    if (is_numeric($value)) {
        try {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
            return $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    $value = trim((string)$value);
    if ($value === '') return null;

    // Try a handful of common formats seen in these sheets (including
    // sloppy manual entries like "05//2025" or "09/182025").
    $formats = ['m/d/Y', 'n/j/Y', 'Y-m-d', 'm-d-Y'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $value);
        if ($dt !== false) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return null; // unparseable - skip rather than fail the whole import
}

function normalizeLipaNumber($value) {
    if ($value === null || $value === '') return 0;
    $value = is_string($value) ? trim(str_replace(',', '', $value)) : $value;
    return is_numeric($value) ? (float)$value : 0;
}

// ============================================================
// List + summary
// ============================================================

function listLipaEntries($db) {
    header('Content-Type: application/json');
    requireLipaPermission();

    $iaProfileId = intval($_POST['ia_profile_id'] ?? $_GET['ia_profile_id'] ?? 0);
    $cropYear    = $_POST['crop_year'] ?? $_GET['crop_year'] ?? '';
    $season      = $_POST['season'] ?? $_GET['season'] ?? '';
    $sector      = $_POST['sector'] ?? $_GET['sector'] ?? '';

    if (empty($iaProfileId)) {
        echo json_encode(['success' => false, 'message' => 'Missing ia_profile_id']);
        return;
    }

    $where = ['ia_profile_id = ?'];
    $types = 'i';
    $params = [$iaProfileId];

    if ($cropYear !== '') { $where[] = 'crop_year = ?'; $types .= 'i'; $params[] = intval($cropYear); }
    if ($season !== '')   { $where[] = 'season = ?';    $types .= 's'; $params[] = $season; }
    if ($sector !== '')   { $where[] = 'sector = ?';    $types .= 's'; $params[] = $sector; }

    $whereSql = implode(' AND ', $where);

    $query = "SELECT * FROM ia_lipa_entries WHERE $whereSql ORDER BY sector, lot_no, id";
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $summaryQuery = "SELECT COUNT(*) AS farmer_count,
                             COALESCE(SUM(service_area_ha),0) AS total_service_area,
                             COALESCE(SUM(irrigated_planted_area_ha),0) AS total_irrigated_area,
                             COUNT(DISTINCT sector) AS sector_count
                      FROM ia_lipa_entries WHERE $whereSql";
    $stmt2 = $db->prepare($summaryQuery);
    $stmt2->bind_param($types, ...$params);
    $stmt2->execute();
    $summary = $stmt2->get_result()->fetch_assoc();

    echo json_encode(['success' => true, 'data' => $rows, 'summary' => $summary]);
}

function getLipaFilters($db) {
    header('Content-Type: application/json');
    requireLipaPermission();

    $iaProfileId = intval($_POST['ia_profile_id'] ?? $_GET['ia_profile_id'] ?? 0);
    if (empty($iaProfileId)) {
        echo json_encode(['success' => false, 'message' => 'Missing ia_profile_id']);
        return;
    }

    $stmt = $db->prepare("SELECT DISTINCT crop_year, season FROM ia_lipa_entries WHERE ia_profile_id = ? ORDER BY crop_year DESC, season");
    $stmt->bind_param('i', $iaProfileId);
    $stmt->execute();
    $periods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt2 = $db->prepare("SELECT DISTINCT sector FROM ia_lipa_entries WHERE ia_profile_id = ? ORDER BY sector");
    $stmt2->bind_param('i', $iaProfileId);
    $stmt2->execute();
    $sectors = array_column($stmt2->get_result()->fetch_all(MYSQLI_ASSOC), 'sector');

    echo json_encode(['success' => true, 'periods' => $periods, 'sectors' => $sectors]);
}

function getLipaEntry($db) {
    header('Content-Type: application/json');
    requireLipaPermission();

    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM ia_lipa_entries WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Entry not found']);
        return;
    }
    echo json_encode(['success' => true, 'data' => $row]);
}

// ============================================================
// Manual add / edit / delete
// ============================================================

function addLipaEntry($db) {
    header('Content-Type: application/json');
    requireLipaPermission();

    try {
        $f = lipaFieldsFromPost($_POST);
        if (empty($f['ia_profile_id']) || empty($f['crop_year']) || empty($f['season']) || empty($f['sector'])) {
            throw new Exception('IA, crop year, season and sector are required.');
        }

        $query = "INSERT INTO ia_lipa_entries
            (ia_profile_id, crop_year, season, sector, no, lot_no,
             landowner_first_name, landowner_mi, landowner_last_name,
             service_area_ha, irrigated_planted_area_ha,
             variety_inbred, variety_hybrid,
             date_sown, date_planted, expected_harvest_date,
             rsbsa_reg_no, crop_insurance, remarks,
             source, created_by)
            VALUES (?,?,?,?,?,?, ?,?,?, ?,?, ?,?, ?,?,?, ?,?,?, 'manual', ?)";
        $stmt = $db->prepare($query);
        $createdBy = $_SESSION['user_id'] ?? null;
        $stmt->bind_param(
            'iississssddssssssssi',
            $f['ia_profile_id'], $f['crop_year'], $f['season'], $f['sector'], $f['no'], $f['lot_no'],
            $f['landowner_first_name'], $f['landowner_mi'], $f['landowner_last_name'],
            $f['service_area_ha'], $f['irrigated_planted_area_ha'],
            $f['variety_inbred'], $f['variety_hybrid'],
            $f['date_sown'], $f['date_planted'], $f['expected_harvest_date'],
            $f['rsbsa_reg_no'], $f['crop_insurance'], $f['remarks'],
            $createdBy
        );
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Entry added.', 'id' => $db->insert_id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateLipaEntry($db) {
    header('Content-Type: application/json');
    requireLipaPermission();

    try {
        $id = intval($_POST['id'] ?? 0);
        if (empty($id)) throw new Exception('Missing entry id.');

        $f = lipaFieldsFromPost($_POST);

        $query = "UPDATE ia_lipa_entries SET
            crop_year=?, season=?, sector=?, no=?, lot_no=?,
            landowner_first_name=?, landowner_mi=?, landowner_last_name=?,
            service_area_ha=?, irrigated_planted_area_ha=?,
            variety_inbred=?, variety_hybrid=?,
            date_sown=?, date_planted=?, expected_harvest_date=?,
            rsbsa_reg_no=?, crop_insurance=?, remarks=?
            WHERE id=?";
        $stmt = $db->prepare($query);
        $stmt->bind_param(
            'ississssddssssssssi',
            $f['crop_year'], $f['season'], $f['sector'], $f['no'], $f['lot_no'],
            $f['landowner_first_name'], $f['landowner_mi'], $f['landowner_last_name'],
            $f['service_area_ha'], $f['irrigated_planted_area_ha'],
            $f['variety_inbred'], $f['variety_hybrid'],
            $f['date_sown'], $f['date_planted'], $f['expected_harvest_date'],
            $f['rsbsa_reg_no'], $f['crop_insurance'], $f['remarks'],
            $id
        );
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Entry updated.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteLipaEntry($db) {
    header('Content-Type: application/json');
    requireLipaPermission();

    $id = intval($_POST['id'] ?? 0);
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Missing entry id.']);
        return;
    }
    $stmt = $db->prepare("DELETE FROM ia_lipa_entries WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Entry deleted.']);
}

function deleteLipaPeriod($db) {
    header('Content-Type: application/json');
    requireLipaPermission();

    $iaProfileId = intval($_POST['ia_profile_id'] ?? 0);
    $cropYear    = intval($_POST['crop_year'] ?? 0);
    $season      = $_POST['season'] ?? '';

    if (empty($iaProfileId) || empty($cropYear) || empty($season)) {
        echo json_encode(['success' => false, 'message' => 'Missing ia_profile_id, crop_year or season.']);
        return;
    }

    $stmt = $db->prepare("DELETE FROM ia_lipa_entries WHERE ia_profile_id=? AND crop_year=? AND season=?");
    $stmt->bind_param('iis', $iaProfileId, $cropYear, $season);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Records for that period were cleared.', 'deleted' => $stmt->affected_rows]);
}

/** Pulls + sanitizes the shared set of POST fields used by add/update. */
function lipaFieldsFromPost($p) {
    return [
        'ia_profile_id'             => intval($p['ia_profile_id'] ?? 0),
        'crop_year'                 => intval($p['crop_year'] ?? 0),
        'season'                    => in_array($p['season'] ?? '', ['wet','dry']) ? $p['season'] : '',
        'sector'                    => trim($p['sector'] ?? ''),
        'no'                        => isset($p['no']) && $p['no'] !== '' ? intval($p['no']) : null,
        'lot_no'                    => trim($p['lot_no'] ?? ''),
        'landowner_first_name'      => trim($p['landowner_first_name'] ?? ''),
        'landowner_mi'              => trim($p['landowner_mi'] ?? ''),
        'landowner_last_name'       => trim($p['landowner_last_name'] ?? ''),
        'service_area_ha'           => normalizeLipaNumber($p['service_area_ha'] ?? 0),
        'irrigated_planted_area_ha' => normalizeLipaNumber($p['irrigated_planted_area_ha'] ?? 0),
        'variety_inbred'            => trim($p['variety_inbred'] ?? ''),
        'variety_hybrid'            => trim($p['variety_hybrid'] ?? ''),
        'date_sown'                 => normalizeLipaDate($p['date_sown'] ?? null),
        'date_planted'              => normalizeLipaDate($p['date_planted'] ?? null),
        'expected_harvest_date'     => normalizeLipaDate($p['expected_harvest_date'] ?? null),
        'rsbsa_reg_no'              => trim($p['rsbsa_reg_no'] ?? ''),
        'crop_insurance'            => trim($p['crop_insurance'] ?? ''),
        'remarks'                   => trim($p['remarks'] ?? ''),
    ];
}

// ============================================================
// Import from .xlsx
// ============================================================

/**
 * Bounds what PhpSpreadsheet will actually read off disk.
 *
 * Why this exists: a corrupted/mis-saved workbook can end up with a stray
 * formatted or valued cell far below the real data (we've seen single
 * leftover cells around row 1,000,000+, presumably from an accidental
 * paste/format-entire-column at some point). That single stray cell makes
 * the sheet's "used range" balloon to 1,000,000+ rows. Without a filter,
 * PhpSpreadsheet - and any code that loops up to getHighestDataRow() calling
 * getCell() - will try to instantiate a Cell object for every one of those
 * rows/columns, which exhausts PHP's memory limit even on a small workbook.
 *
 * The LIPA template never has more than a few hundred data rows or more
 * than columns A-O, so it's safe to hard-cap well above that and simply
 * ignore anything beyond it.
 */
class LipaImportReadFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
    const MAX_ROW = 5000;   // generous ceiling; real sheets use <300 rows
    const MAX_COL = 'O';    // last real data column in the LIPA template

    public function readCell($column, $row, $worksheetName = '') {
        if ($row > self::MAX_ROW) {
            return false;
        }
        if (strlen($column) > strlen(self::MAX_COL)) {
            return false;
        }
        if (strlen($column) === strlen(self::MAX_COL) && strcmp($column, self::MAX_COL) > 0) {
            return false;
        }
        return true;
    }
}

function importLipaFile($db) {
    header('Content-Type: application/json');
    requireLipaPermission();

    try {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            global $__autoload_path;
            throw new Exception('PhpSpreadsheet is not available on the server. Checked: ' . $__autoload_path . '. Run "composer require phpoffice/phpspreadsheet" in your project root, or adjust the autoload path in ia_lipa_ajax.php.');
        }

        $iaProfileId = intval($_POST['ia_profile_id'] ?? 0);
        $cropYear    = intval($_POST['crop_year'] ?? 0);
        $season      = in_array($_POST['season'] ?? '', ['wet','dry']) ? $_POST['season'] : '';
        $replace     = !empty($_POST['replace_existing']);

        if (empty($iaProfileId) || empty($cropYear) || empty($season)) {
            throw new Exception('IA profile, crop year and season are required.');
        }

        if (!isset($_FILES['lipa_file']) || $_FILES['lipa_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please select a valid LIPA Excel file to import.');
        }

        $file = $_FILES['lipa_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            throw new Exception('Please upload a .xlsx or .xls file.');
        }

        // Guard against corrupted workbooks where a stray formatted cell far
        // below the real data (e.g. row 1,000,000+) inflates the sheet's
        // "used range" and causes PhpSpreadsheet to blow through memory.
        // We cap how many rows/columns will ever be read from disk.
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file['tmp_name']);
        $reader->setReadDataOnly(true); // skip styles/formatting we don't need for import
        $reader->setReadFilter(new LipaImportReadFilter());
        $spreadsheet = $reader->load($file['tmp_name']);

        $db->begin_transaction();

        if ($replace) {
            $del = $db->prepare("DELETE FROM ia_lipa_entries WHERE ia_profile_id=? AND crop_year=? AND season=?");
            $del->bind_param('iis', $iaProfileId, $cropYear, $season);
            $del->execute();
        }

        $insertQuery = "INSERT INTO ia_lipa_entries
            (ia_profile_id, crop_year, season, sector, no, lot_no,
             landowner_first_name, landowner_mi, landowner_last_name,
             service_area_ha, irrigated_planted_area_ha,
             variety_inbred, variety_hybrid,
             date_sown, date_planted, expected_harvest_date,
             rsbsa_reg_no, crop_insurance, remarks,
             source, imported_from, created_by)
            VALUES (?,?,?,?,?,?, ?,?,?, ?,?, ?,?, ?,?,?, ?,?,?, 'import', ?, ?)";
        $stmt = $db->prepare($insertQuery);
        $createdBy = $_SESSION['user_id'] ?? null;

        $rowsImported = 0;

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $sector = trim($sheetName);

            // Cap at the read filter's ceiling too, so a corrupted workbook
            // can never force this loop into the millions regardless of
            // what getHighestDataRow() reports.
            $highestRow = min($sheet->getHighestDataRow(), LipaImportReadFilter::MAX_ROW);
            for ($r = 1; $r <= $highestRow; $r++) {
                // Use createIfNotExists=false so we don't instantiate a Cell
                // object for every blank row we skip over.
                if (!$sheet->cellExists('A' . $r)) continue;
                $noCell = $sheet->getCell('A' . $r)->getValue();

                // Data rows are the only ones with a numeric value in column A.
                // This automatically skips titles, repeated headers, blank
                // spacer rows, and "Subtotal" rows.
                if (!is_numeric($noCell)) continue;

                $get = function ($col) use ($sheet, $r) {
                    $cell = $sheet->getCell($col . $r);
                    return $cell->getValue();
                };

                $lotNo     = $get('B');
                $first     = $get('C');
                $mi        = $get('D');
                $last      = $get('E');
                $service   = $get('F');
                $irrigated = $get('G');
                $inbred    = $get('H');
                $hybrid    = $get('I');
                $sown      = $get('J');
                $planted   = $get('K');
                $harvest   = $get('L');
                $rsbsa     = $get('M');
                $insurance = $get('N');
                $remarks   = $get('O');

                $no = intval($noCell);
                $lotNo = $lotNo !== null ? trim((string)$lotNo) : null;
                $first = $first !== null ? trim((string)$first) : null;
                $mi = $mi !== null ? trim((string)$mi) : null;
                $last = $last !== null ? trim((string)$last) : null;
                $serviceArea = normalizeLipaNumber($service);
                $irrigatedArea = normalizeLipaNumber($irrigated);
                $inbred = $inbred !== null && $inbred !== '' ? trim((string)$inbred) : null;
                $hybrid = $hybrid !== null && $hybrid !== '' ? trim((string)$hybrid) : null;
                $dateSown = normalizeLipaDate($sown);
                $datePlanted = normalizeLipaDate($planted);
                $dateHarvest = normalizeLipaDate($harvest);
                $rsbsa = $rsbsa !== null ? trim((string)$rsbsa) : null;
                $insurance = $insurance !== null ? trim((string)$insurance) : null;
                $remarks = $remarks !== null ? trim((string)$remarks) : null;

                // Skip fully-empty "data" rows just in case
                if (empty($lotNo) && empty($last) && $serviceArea == 0 && $irrigatedArea == 0) continue;

                $filename = $file['name'];
                $stmt->bind_param(
                    'iississssddsssssssssi',
                    $iaProfileId, $cropYear, $season, $sector, $no, $lotNo,
                    $first, $mi, $last,
                    $serviceArea, $irrigatedArea,
                    $inbred, $hybrid,
                    $dateSown, $datePlanted, $dateHarvest,
                    $rsbsa, $insurance, $remarks,
                    $filename, $createdBy
                );
                $stmt->execute();
                $rowsImported++;
            }
        }

        $logStmt = $db->prepare("INSERT INTO ia_lipa_imports
            (ia_profile_id, crop_year, season, filename, rows_imported, replaced_existing, imported_by)
            VALUES (?,?,?,?,?,?,?)");
        $filename = $file['name'];
        $replacedFlag = $replace ? 1 : 0;
        $logStmt->bind_param('iissiii', $iaProfileId, $cropYear, $season, $filename, $rowsImported, $replacedFlag, $createdBy);
        $logStmt->execute();

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => "Imported $rowsImported record(s) from " . count($spreadsheet->getSheetNames()) . " sheet(s).",
            'rows_imported' => $rowsImported
        ]);
    } catch (Exception $e) {
        if ($db->in_transaction ?? false) { $db->rollback(); }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// Export to LIPA Report template
// ============================================================

/**
 * Path to the official LIPA report template. Adjust here if the
 * template ever moves; every export run reads a fresh copy so the
 * live file on disk is never modified.
 *
 * Defined as a function (not a top-level const) because PHP executes
 * top-level const statements in file order, not hoisted like function
 * definitions — a const placed after the switch dispatcher above would
 * still be undefined when an early action (like 'export') calls it.
 */
function getLipaReportTemplatePath() {
    return __DIR__ . '/../public/templates/LIPA-REPORT-TEMPLATE.xlsx';
}

function exportLipaReport($db) {
    requireLipaPermission();

    // Exports with many rows/sheets (insertNewRowBefore + duplicateStyle per
    // sheet, then writing the whole workbook) can be considerably heavier
    // than the global defaults set at the top of this file. If PHP hits
    // max_execution_time or runs out of memory *after* the headers below
    // have already been sent, the connection dies mid-stream and the
    // browser reports ERR_INVALID_RESPONSE instead of a normal PHP error.
    // Raise both further, specifically for this action.
    @ini_set('memory_limit', '1024M');
    @set_time_limit(300);

    try {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            global $__autoload_path;
            throw new Exception('PhpSpreadsheet is not available on the server. Checked: ' . $__autoload_path);
        }

        $iaProfileId = intval($_GET['ia_profile_id'] ?? $_POST['ia_profile_id'] ?? 0);
        $cropYear    = trim($_GET['crop_year'] ?? $_POST['crop_year'] ?? '');
        $season      = trim($_GET['season'] ?? $_POST['season'] ?? '');
        $sectorFilt  = trim($_GET['sector'] ?? $_POST['sector'] ?? '');

        if (empty($iaProfileId)) {
            throw new Exception('Missing IA profile.');
        }

        $profStmt = $db->prepare("SELECT * FROM ia_profiles WHERE id = ?");
        $profStmt->bind_param('i', $iaProfileId);
        $profStmt->execute();
        $profile = $profStmt->get_result()->fetch_assoc();
        if (!$profile) {
            throw new Exception('IA profile not found.');
        }

        $where = ['ia_profile_id = ?'];
        $types = 'i';
        $params = [$iaProfileId];
        if ($cropYear !== '')   { $where[] = 'crop_year = ?'; $types .= 'i'; $params[] = intval($cropYear); }
        if ($season !== '')     { $where[] = 'season = ?';    $types .= 's'; $params[] = $season; }
        if ($sectorFilt !== '') { $where[] = 'sector = ?';    $types .= 's'; $params[] = $sectorFilt; }
        $whereSql = implode(' AND ', $where);

        $query = "SELECT * FROM ia_lipa_entries WHERE $whereSql
                  ORDER BY crop_year, season, sector, no, lot_no, id";
        $stmt = $db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (!$rows) {
            throw new Exception('No LIPA records found for the selected filters.');
        }
        lipaDebugLog('Fetched ' . count($rows) . ' row(s) from DB.');

        // Group into crop_year -> season -> sector, since the template
        // dedicates one sheet per sector for a single crop year/season.
        $groups = [];
        foreach ($rows as $r) {
            $groups[$r['crop_year']][$r['season']][$r['sector']][] = $r;
        }

        $sheetCount = 0;
        foreach ($groups as $seasons) { foreach ($seasons as $sectors) { $sheetCount += count($sectors); } }
        lipaDebugLog("Will generate $sheetCount sheet(s).");

        $templatePath = realpath(getLipaReportTemplatePath());
        if (!$templatePath || !file_exists($templatePath)) {
            throw new Exception('LIPA report template not found at ' . getLipaReportTemplatePath() . '. Update getLipaReportTemplatePath() in ia_lipa_ajax.php if the file has moved.');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
        lipaDebugLog('Template loaded.');
        $templateSheet = $spreadsheet->getSheet(0);
        // Keep an untouched copy to clone from. $templateSheet itself gets
        // renamed in place below (when it's reused for the first group), so
        // cloning FROM it after that would just copy its new name and clash
        // with the sheet that already has it.
        $templateSheetOriginal = clone $templateSheet;

        $iaName   = $profile['ia_name'] ?? '';
        $location = buildLipaLocation($profile);

        $usedSheetNames = [];
        $sheetIndex = 0;

        foreach ($groups as $groupYear => $seasons) {
            foreach ($seasons as $groupSeason => $sectors) {
                foreach ($sectors as $groupSector => $sectorRows) {
                    if ($sheetIndex === 0) {
                        $sheet = $templateSheet; // reuse the template's own sheet for the first group
                    } else {
                        $sheet = clone $templateSheetOriginal; // clone the pristine copy, not the (possibly renamed) live sheet
                        $spreadsheet->addSheet($sheet, $sheetIndex);
                    }

                    populateLipaSheet($sheet, $sectorRows, $iaName, $location, $groupYear, $groupSeason, $groupSector);

                    $sheetName = sanitizeLipaSheetName($groupYear . ' ' . strtoupper($groupSeason) . ' - ' . $groupSector, $usedSheetNames);
                    $sheet->setTitle($sheetName);
                    $usedSheetNames[] = $sheetName;
                    $sheetIndex++;
                    lipaDebugLog("Built sheet $sheetIndex/$sheetCount: '$sheetName' (" . count($sectorRows) . ' rows).');
                }
            }
        }

        $spreadsheet->setActiveSheetIndex(0);
        lipaDebugLog('All sheets built. Starting write...');

        $iaSlug = trim(preg_replace('/[^A-Za-z0-9]+/', '_', $iaName ?: 'IA'), '_');
        $scope = ($cropYear !== '' ? 'CY' . $cropYear : 'AllYears') . '_' . ($season !== '' ? strtoupper($season) : 'AllSeasons');
        $filename = 'LIPA_Report_' . $iaSlug . '_' . $scope . '.xlsx';

        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        // Pre-calculating every formula in the workbook before writing is
        // expensive and scales poorly with sheet/row count; skip it since
        // Excel will happily recalculate the SUM() subtotals on open anyway.
        $writer->setPreCalculateFormulas(false);
        lipaDebugLog('Writer created, calling save()...');
        $writer->save('php://output');
        lipaDebugLog('Save complete. Exiting.');
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Best-effort assembly of a "Location" line from whichever address-like
 * columns actually exist on ia_profiles. Adjust the field list below if
 * your table uses different column names.
 */
function buildLipaLocation($profile) {
    if (!empty($profile['location'])) return $profile['location'];
    if (!empty($profile['address']))  return $profile['address'];
    $parts = [];
    foreach (['sitio', 'barangay', 'municipality', 'city', 'province'] as $f) {
        if (!empty($profile[$f])) $parts[] = $profile[$f];
    }
    return implode(', ', $parts);
}

function sanitizeLipaSheetName($name, $used) {
    $name = preg_replace('/[\\\\\/\?\*\[\]:]/', '-', $name);
    $name = substr($name, 0, 31);
    $base = $name;
    $i = 1;
    while (in_array($name, $used, true)) {
        $suffix = '_' . $i;
        $name = substr($base, 0, 31 - strlen($suffix)) . $suffix;
        $i++;
    }
    return $name;
}

/**
 * Fills one sector's worth of LIPA data into a clone of the template
 * sheet, matching the exact column layout used by importLipaFile():
 * A=No, B=Lot No, C-E=Landowner, F=Service Area, G=Irrigated/Planted,
 * H=Inbred, I=Hybrid, J-L=Dates, M=RSBSA, N=Crop Insurance, O=Remarks.
 */
function populateLipaSheet($sheet, $sectorRows, $iaName, $location, $cropYear, $season, $sectorName) {
    $seasonLabel = strtoupper($season) . ' SEASON';
    $sheet->setCellValue('B2', 'CY ' . $cropYear . ' ' . $seasonLabel);
    $sheet->setCellValue('D3', $iaName);
    $sheet->setCellValue('F5', $location);
    $sheet->setCellValue('I7', 'TSA Group:  ' . $sectorName);

    $startRow = 12; // first data row in the template
    $count = count($sectorRows);
    $extra = max(0, $count - 1);

    if ($extra > 0) {
        // Push the Subtotal row (13) down and duplicate row 12's
        // formatting (borders, number formats) across the new rows.
        $sheet->insertNewRowBefore($startRow + 1, $extra);
        $sheet->duplicateStyle(
            $sheet->getStyle('A' . $startRow . ':O' . $startRow),
            'A' . ($startRow + 1) . ':O' . ($startRow + $extra)
        );
    }

    $i = 0;
    foreach ($sectorRows as $r) {
        $row = $startRow + $i;
        $sheet->setCellValue('A' . $row, $r['no'] !== null && $r['no'] !== '' ? (int)$r['no'] : ($i + 1));
        $sheet->setCellValue('B' . $row, $r['lot_no']);
        $sheet->setCellValue('C' . $row, $r['landowner_first_name']);
        $sheet->setCellValue('D' . $row, $r['landowner_mi']);
        $sheet->setCellValue('E' . $row, $r['landowner_last_name']);
        $sheet->setCellValue('F' . $row, (float)$r['service_area_ha']);
        $sheet->setCellValue('G' . $row, (float)$r['irrigated_planted_area_ha']);
        $sheet->setCellValue('H' . $row, $r['variety_inbred']);
        $sheet->setCellValue('I' . $row, $r['variety_hybrid']);
        // Template formats J/K/L as text ('@'), so write dates as mm/dd/yyyy strings.
        $sheet->setCellValueExplicit('J' . $row, formatLipaDateForExport($r['date_sown']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('K' . $row, formatLipaDateForExport($r['date_planted']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('L' . $row, formatLipaDateForExport($r['expected_harvest_date']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('M' . $row, $r['rsbsa_reg_no']);
        $sheet->setCellValue('N' . $row, $r['crop_insurance']);
        $sheet->setCellValue('O' . $row, $r['remarks']);
        $i++;
    }

    $subtotalRow = $startRow + $count;
    $lastDataRow = $subtotalRow - 1;
    $sheet->setCellValue('A' . $subtotalRow, 'Subtotal');
    $sheet->setCellValue('F' . $subtotalRow, '=SUM(F' . $startRow . ':F' . $lastDataRow . ')');
    $sheet->setCellValue('G' . $subtotalRow, '=SUM(G' . $startRow . ':G' . $lastDataRow . ')');
    // Re-apply the "Subtotal" label merge in case row insertion didn't carry it over.
    try { $sheet->mergeCells('A' . $subtotalRow . ':E' . $subtotalRow); } catch (\Throwable $e) {}

    $sheet->getPageSetup()->setPrintArea('A1:O' . $subtotalRow);
}

function formatLipaDateForExport($value) {
    if (empty($value)) return '';
    $ts = strtotime($value);
    return $ts ? date('m/d/Y', $ts) : $value;
}