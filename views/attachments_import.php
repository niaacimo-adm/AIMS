<?php
session_start();
require_once '../config/database.php';
require '../vendor/autoload.php';
require_once __DIR__ . '/attachments_helpers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

/* ===========================================================================
 * Excel import  (STATUS_OF_ATTACHMENTS_2026.xlsx and files exported from it)
 *
 *  - Columns are found by their header text, so the layout may move.
 *  - The payroll period is read from the "FOR PAYROLL ..." header (I8) and the
 *    title (A7). If neither can be read, the period fields of the import
 *    dialog are used. If the dialog has a period, it always wins.
 *  - Every worksheet in the file is imported (the "Export All" file has one
 *    sheet per period).
 *  - The old template (NO. | NAMES | STATUS | REMARKS | FILING STATUS | DATE)
 *    is still accepted.
 * ======================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    header('Content-Type: application/json');
    ini_set('display_errors', '0');

    $response = [
        'success' => false,
        'type'    => 'error',
        'message' => '',
        'details' => '',
    ];

    try {
        if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $_FILES['excel_file']['error']);
        }
        $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            throw new Exception('Only .xlsx files are allowed.');
        }

        // Optional period chosen in the import dialog
        $override = null;
        $ps = am_post_date($_POST['period_start'] ?? '');
        $pe = am_post_date($_POST['period_end'] ?? '');
        if ($ps || $pe) {
            if (!$ps || !$pe || $pe < $ps) {
                throw new Exception('Please give both a valid start and end date for the payroll period.');
            }
            $override = [$ps, $pe];
        }

        $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);

        $database = new Database();
        $db = $database->getConnection();
        $pools = am_load_employee_pools($db);

        $counts = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0];
        $errors = [];
        $skipped = 0;
        $sheetsRead = 0;
        $sheetProblems = [];

        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $title = $ws->getTitle();

            // ---- 1. header row ------------------------------------------------
            $map = [];
            $headerRow = null;
            $maxScan = min(40, $ws->getHighestRow());
            $maxCol  = min(20, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestColumn()));
            for ($r = 1; $r <= $maxScan && $headerRow === null; $r++) {
                for ($c = 1; $c <= $maxCol; $c++) {
                    if (strtoupper(am_clean_text(am_cell_text($ws, $c, $r))) === 'NAMES') {
                        $headerRow = $r;
                        break;
                    }
                }
            }
            if ($headerRow === null) {
                $sheetProblems[] = "Sheet '$title': header row with NAMES not found.";
                continue;
            }

            $payrollHeaderText = '';
            for ($c = 1; $c <= $maxCol; $c++) {
                $h = strtoupper(am_clean_text(am_cell_text($ws, $c, $headerRow)));
                if ($h === '') {
                    continue;
                }
                if ($h === 'NAMES' || $h === 'NAME') {
                    $map['name'] = $c;
                } elseif ($h === 'DATE SUBMITTED') {
                    $map['submitted'] = $c;
                } elseif ($h === 'DATE' && !isset($map['submitted'])) {      // old template
                    $map['submitted'] = $c;
                } elseif ($h === 'STATUS') {
                    $map['status'] = $c;
                } elseif ($h === 'REMARKS') {
                    $map['remarks'] = $c;
                } elseif ($h === 'DATE INFORMED') {
                    $map['informed'] = $c;
                } elseif ($h === 'STATUS OF COMPLIANCE') {
                    $map['compliance'] = $c;
                } elseif ($h === 'DATE COMPLIED') {
                    $map['complied'] = $c;
                } elseif ($h === 'FILING STATUS') {                          // old template
                    $map['filing'] = $c;
                } elseif (strpos($h, 'FOR PAYROLL') === 0) {
                    $map['payroll'] = $c;
                    $payrollHeaderText = am_cell_text($ws, $c, $headerRow);
                }
            }
            if (!isset($map['name']) || !isset($map['status'])) {
                $sheetProblems[] = "Sheet '$title': the NAMES and STATUS columns are required.";
                continue;
            }

            // ---- 2. payroll period -----------------------------------------------
            $period = $override;
            if (!$period) {
                $fromHeader = am_parse_period_text($payrollHeaderText);
                $fromTitle = null;
                for ($r = 1; $r < $headerRow && !$fromTitle; $r++) {
                    for ($c = 1; $c <= $maxCol && !$fromTitle; $c++) {
                        $fromTitle = am_parse_period_text(am_cell_text($ws, $c, $r));
                    }
                }
                if ($fromHeader && $fromTitle && $fromHeader !== $fromTitle) {
                    $sheetProblems[] = "Sheet '$title': the title says " . am_period_title($fromTitle[0], $fromTitle[1])
                        . " but the FOR PAYROLL header says " . am_period_title($fromHeader[0], $fromHeader[1])
                        . '. Fix one of them or choose the period in the import dialog.';
                    continue;
                }
                $period = $fromHeader ?: $fromTitle;
            }
            if (!$period) {
                $sheetProblems[] = "Sheet '$title': payroll period could not be read. Fill in the FOR PAYROLL header"
                    . ' (e.g. 10/1-15/2026) or choose the period in the import dialog.';
                continue;
            }
            list($pStart, $pEnd) = $period;
            $sheetsRead++;

            // ---- 3. rows ---------------------------------------------------------------
            $highest = $ws->getHighestRow();
            for ($r = $headerRow + 1; $r <= $highest; $r++) {
                $name = am_clean_text(am_cell_text($ws, $map['name'], $r));
                if ($name === '') {
                    continue;     // blank rows and the legend under the table
                }

                try {
                    list($emp, $err) = am_find_employee($name, $pools);
                    if (!$emp) {
                        throw new Exception($err === 'notfound'
                            ? "Employee '$name' not found in database"
                            : "Employee '$name' - " . $err);
                    }

                    $fields = [];

                    $status = am_normalize_status(am_cell_text($ws, $map['status'], $r), null);
                    if ($status === false) {
                        throw new Exception("Invalid status '" . am_cell_text($ws, $map['status'], $r)
                            . "'. Use: Complete, Lacking Information, For Review, No Attachment, Complete and Late");
                    }
                    if ($status !== null) {
                        $fields['status'] = $status;
                    }

                    if (isset($map['remarks'])) {
                        $fields['remarks'] = am_cell_text($ws, $map['remarks'], $r);
                    }

                    foreach (['submitted' => ['submission_date', 'Date Submitted'],
                              'informed'  => ['date_informed',   'Date Informed'],
                              'complied'  => ['date_complied',   'Date Complied']] as $key => $def) {
                        if (isset($map[$key])) {
                            $d = am_cell_date($ws, $map[$key], $r);
                            if ($d === false) {
                                throw new Exception("Invalid date in '{$def[1]}'");
                            }
                            $fields[$def[0]] = $d;
                        }
                    }

                    if (isset($map['compliance'])) {
                        $raw = am_cell_text($ws, $map['compliance'], $r);
                        $c = am_normalize_compliance($raw);
                        if ($c === false) {
                            throw new Exception("Invalid status of compliance '$raw'. Use: On-Time Compliance or Late Compliance");
                        }
                        $fields['compliance_status'] = $c;
                    }

                    if (isset($map['payroll'])) {
                        $raw = am_cell_text($ws, $map['payroll'], $r);
                        $p = am_normalize_status($raw, null);
                        if ($p === false) {
                            throw new Exception("Invalid FOR PAYROLL status '$raw'");
                        }
                        $fields['payroll_status'] = $p;
                    }

                    if (isset($map['filing'])) {
                        $raw = am_cell_text($ws, $map['filing'], $r);
                        $fl = am_normalize_filing($raw);
                        if ($fl === false) {
                            throw new Exception("Invalid filing status '$raw'");
                        }
                        if ($fl !== null) {
                            $fields['filing_status'] = $fl;
                        }
                    }

                    // Rows with only a name (e.g. employees who have not submitted yet) create no record
                    $hasData = false;
                    foreach ($fields as $v) {
                        if ($v !== null && $v !== '') {
                            $hasData = true;
                            break;
                        }
                    }
                    if (!$hasData) {
                        $skipped++;
                        continue;
                    }

                    $counts[am_upsert_record($db, $emp['emp_id'], $pStart, $pEnd, $fields)]++;

                } catch (Exception $e) {
                    $errors[] = "Sheet '$title' row $r: " . $e->getMessage();
                }
            }
        }

        if ($sheetsRead === 0) {
            throw new Exception("Invalid file format. Please download and use the template file.\n\n" . implode("\n", $sheetProblems));
        }

        $errors = array_merge($sheetProblems, $errors);
        $successCount = array_sum($counts);
        $errorCount = count($errors);
        $summary = "$successCount record(s) saved ({$counts['inserted']} new, {$counts['updated']} updated, {$counts['unchanged']} unchanged)"
            . ($skipped ? ", $skipped empty row(s) skipped" : '');

        if ($successCount === 0 && $errorCount === 0) {
            $response['success'] = true;
            $response['type'] = 'success';
            $response['message'] = 'Nothing to import';
            $response['details'] = $summary;
        } elseif ($successCount > 0 && $errorCount === 0) {
            $response['success'] = true;
            $response['type'] = 'success';
            $response['message'] = 'Import completed successfully!';
            $response['details'] = $summary;
        } elseif ($successCount > 0) {
            $response['success'] = true;
            $response['type'] = 'warning';
            $response['message'] = 'Import completed with some errors';
            $response['details'] = "$summary, $errorCount problem(s)\n\nFirst few errors:\n" . implode("\n", array_slice($errors, 0, 5));
            if ($errorCount > 5) {
                $response['details'] .= "\n... and " . ($errorCount - 5) . ' more';
            }
        } else {
            $response['message'] = 'Import failed';
            $response['details'] = 'No records were imported.' . ($errorCount ? "\n\nFirst few errors:\n" . implode("\n", array_slice($errors, 0, 5)) : '');
        }

    } catch (Exception $e) {
        $response['message'] = 'Import failed';
        $response['details'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// Handle bulk delete (keep existing functionality)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        if (isset($_POST['delete_all']) && $_POST['delete_all'] === '1') {
            // Delete all records
            $query = "DELETE FROM attachments_monitoring";
            $stmt = $db->prepare($query);
        } else {
            // Delete specific records
            $recordIds = $_POST['record_ids'] ?? [];
            if (empty($recordIds)) {
                throw new Exception("No records selected for deletion.");
            }

            $placeholders = str_repeat('?,', count($recordIds) - 1) . '?';
            $query = "DELETE FROM attachments_monitoring WHERE monitoring_id IN ($placeholders)";
            $stmt = $db->prepare($query);

            // Bind parameters
            $types = str_repeat('i', count($recordIds));
            $stmt->bind_param($types, ...$recordIds);
        }

        if ($stmt->execute()) {
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Records deleted successfully!'
            ];
        } else {
            throw new Exception("Failed to delete records.");
        }

    } catch (Exception $e) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => "Delete failed: " . $e->getMessage()
        ];
    }

    header("Location: attachments_monitoring.php");
    exit();
}

/* ===========================================================================
 * Cell helpers
 * ======================================================================== */

function am_cell_raw($ws, $col, $row)
{
    $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
    if (!$ws->cellExists($coord)) {
        return null;
    }
    $cell = $ws->getCell($coord);
    try {
        $v = $cell->getCalculatedValue();
    } catch (\Throwable $e) {
        $v = $cell->getValue();
    }
    if ($v instanceof RichText) {
        $v = $v->getPlainText();
    }
    return $v;
}

/** Cell as trimmed text ('' when empty). Numbers are returned without trailing .0 */
function am_cell_text($ws, $col, $row)
{
    $v = am_cell_raw($ws, $col, $row);
    if ($v === null) {
        return '';
    }
    if (is_float($v) && floor($v) == $v) {
        $v = (int)$v;
    }
    return trim((string)$v);
}

/** Date cell => 'Y-m-d', null when empty, false when it is not a valid date. */
function am_cell_date($ws, $col, $row)
{
    $v = am_cell_raw($ws, $col, $row);
    if ($v === null || $v === '') {
        return null;
    }
    if (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) {
        $n = (float)$v;
        if ($n >= 20000 && $n <= 80000) {                // Excel serial date (1954-2119)
            return XlDate::excelToDateTimeObject($n)->format('Y-m-d');
        }
        return false;
    }
    $s = trim((string)$v);
    if ($s === '') {
        return null;
    }
    foreach (['m/d/Y', 'n/j/Y', 'm-d-Y', 'Y-m-d', 'M j, Y', 'F j, Y', 'm/d/y', 'n/j/y', 'd-M-Y'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $s);
        $e = DateTime::getLastErrors();
        if ($d && (!$e || ($e['warning_count'] == 0 && $e['error_count'] == 0))) {
            return $d->format('Y-m-d');
        }
    }
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : false;
}