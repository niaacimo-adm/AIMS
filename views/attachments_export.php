<?php
session_start();
require_once '../config/database.php';
require '../vendor/autoload.php';
require_once __DIR__ . '/attachments_helpers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ---------------------------------------------------------------------------
// Template layout (STATUS_OF_ATTACHMENTS_2026.xlsx)
//   A7  : "ATTACHMENTS STATUS for QUINCENA: ..."      I8 : "FOR PAYROLL <period>"
//   Row 8 = headers, rows 9-58 = 50 employee rows, rows 60-61 = legend
// ---------------------------------------------------------------------------
const AM_TEMPLATE_PATH   = __DIR__ . '/../public/templates/STATUS_OF_ATTACHMENTS_2026.xlsx';
const AM_FIRST_ROW       = 9;
const AM_TEMPLATE_LAST   = 58;
const AM_TITLE_CELL      = 'A7';
const AM_PAYROLL_HEADER  = 'I8';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!file_exists(AM_TEMPLATE_PATH)) {
        throw new Exception('Template file not found: ' . basename(AM_TEMPLATE_PATH) . ' (expected in public/templates/)');
    }

    $exportPeriod = trim($_GET['export_period'] ?? '');
    $single = ($exportPeriod !== '' && $exportPeriod !== 'all');

    // ---- Which payroll periods are exported (one worksheet per period) -----
    $sql = "SELECT payroll_period, MIN(period_start) AS ps, MAX(period_end) AS pe, MAX(updated_at) AS ua
            FROM attachments_monitoring"
         . ($single ? " WHERE payroll_period = ?" : "")
         . " GROUP BY payroll_period
            ORDER BY (MIN(period_start) IS NULL), MIN(period_start), payroll_period";
    $stmt = $db->prepare($sql);
    if ($single) {
        $stmt->bind_param('s', $exportPeriod);
    }
    $stmt->execute();
    $periodRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$periodRows) {
        throw new Exception('There are no records to export.');
    }

    $periods = [];
    foreach ($periodRows as $p) {
        $start = $end = null;
        if (!empty($p['ps']) && !empty($p['pe']) && $p['ps'] !== '0000-00-00' && $p['pe'] !== '0000-00-00') {
            $start = $p['ps'];
            $end   = $p['pe'];
        } else {
            $guessYear = !empty($p['ua']) ? date('Y', strtotime($p['ua'])) : date('Y');
            $parsed = am_parse_period_text($p['payroll_period'], $guessYear);
            if ($parsed) {
                list($start, $end) = $parsed;
            }
        }
        if (!$start) {
            throw new Exception("Cannot work out the dates of payroll period '" . $p['payroll_period'] . "'. Run the SQL migration or edit the period.");
        }
        $periods[] = ['label' => $p['payroll_period'], 'start' => $start, 'end' => $end];
    }

    // ---- Load the template and make one sheet per period -------------------
    $spreadsheet = IOFactory::load(AM_TEMPLATE_PATH);
    $base = $spreadsheet->getSheet(0);

    // Prepare the template sheet BEFORE cloning it (see am_prepare_template_sheet).
    am_prepare_template_sheet($base);

    $sheets = [$base];
    for ($i = 1; $i < count($periods); $i++) {
        $copy = clone $base;               // clone the untouched template sheet
        $copy->setTitle('tmp' . $i);
        $spreadsheet->addSheet($copy);
        $sheets[] = $copy;
    }

    $mid = am_employee_middle_column($db);
    $targets = implode(',', array_map('intval', AM_TARGET_APPOINTMENT_STATUSES));
    $usedTitles = [];

    foreach ($periods as $i => $period) {
        // Employees: all monitored employees + anyone who has a record in this period
        $empSql = "SELECT e.emp_id, e.first_name, e.last_name, "
                . ($mid ? "e.`$mid`" : "''") . " AS middle_name
                   FROM employee e
                   WHERE e.appointment_status_id IN ($targets)
                      OR e.emp_id IN (SELECT emp_id FROM attachments_monitoring WHERE payroll_period = ?)
                   ORDER BY e.last_name, e.first_name";
        $es = $db->prepare($empSql);
        $es->bind_param('s', $period['label']);
        $es->execute();
        $employees = $es->get_result()->fetch_all(MYSQLI_ASSOC);
        $es->close();

        $rs = $db->prepare("SELECT * FROM attachments_monitoring WHERE payroll_period = ?");
        $rs->bind_param('s', $period['label']);
        $rs->execute();
        $records = [];
        foreach ($rs->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $records[(int)$r['emp_id']] = $r;
        }
        $rs->close();

        $rows = [];
        foreach ($employees as $e) {
            $rows[] = [
                'name' => am_format_employee_name($e['last_name'], $e['first_name'], $e['middle_name']),
                'rec'  => $records[(int)$e['emp_id']] ?? null,
            ];
        }

        am_fill_sheet($sheets[$i], $period['start'], $period['end'], $rows);
        $sheets[$i]->setTitle(am_sheet_title($period['start'], $period['end'], $usedTitles));
    }

    $spreadsheet->setActiveSheetIndex(0);

    // ---- File name ----------------------------------------------------------
    if (count($periods) === 1) {
        $filename = 'STATUS_OF_ATTACHMENTS_' . $periods[0]['start'] . '_to_' . $periods[0]['end'] . '.xlsx';
    } else {
        $filename = 'STATUS_OF_ATTACHMENTS_ALL_PERIODS_' . date('Y-m-d') . '.xlsx';
    }

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    $_SESSION['toast'] = [
        'type'    => 'error',
        'message' => 'Export failed: ' . $e->getMessage(),
    ];
    header('Location: attachments_monitoring.php');
    exit();
}

/* ===========================================================================
 * Helpers
 * ======================================================================== */

/**
 * The 2026 template stores its rows in an Excel *Table* with a custom table style.
 * PhpSpreadsheet cannot keep custom table styles (and 1.x cannot clone sheets that
 * contain tables), so the table is converted into a normal range that looks the same:
 * blue header, striped rows, plain filter buttons.
 */
function am_prepare_template_sheet($sheet)
{
    // Older PhpSpreadsheet versions have no table support at all (nothing to remove),
    // newer ones expose it through slightly different methods.
    if (method_exists($sheet, 'getTableNames') && method_exists($sheet, 'removeTableByName')) {
        foreach ($sheet->getTableNames() as $name) {
            $sheet->removeTableByName($name);
        }
    } elseif (method_exists($sheet, 'getTableCollection') && method_exists($sheet, 'removeTableByName')) {
        foreach ($sheet->getTableCollection() as $table) {
            $sheet->removeTableByName($table->getName());
        }
    }
    foreach (array_keys($sheet->getConditionalStylesCollection()) as $range) {
        if (method_exists($sheet, 'removeConditionalStyles')) {
            $sheet->removeConditionalStyles($range);      // rebuilt per period
        } else {
            $sheet->setConditionalStyles($range, []);
        }
    }
    $sheet->setAutoFilter('A8:I' . AM_TEMPLATE_LAST);     // new filter, not tied to the table

    $header = $sheet->getStyle('A8:I8');
    $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4C9BD7');
}

/** Fill one worksheet (a copy of the template) with one quincena. */
function am_fill_sheet($sheet, $start, $end, array $rows)
{
    $n = count($rows);

    // More employees than the 50 template rows: add rows above the legend
    $extra = max(0, $n - (AM_TEMPLATE_LAST - AM_FIRST_ROW + 1));
    if ($extra > 0) {
        $sheet->insertNewRowBefore(AM_TEMPLATE_LAST + 1, $extra);
        $sheet->duplicateStyle($sheet->getStyle('A' . AM_TEMPLATE_LAST . ':I' . AM_TEMPLATE_LAST),
                               'A' . (AM_TEMPLATE_LAST + 1) . ':I' . (AM_TEMPLATE_LAST + $extra));
        $h = $sheet->getRowDimension(AM_TEMPLATE_LAST)->getRowHeight();
        for ($r = AM_TEMPLATE_LAST + 1; $r <= AM_TEMPLATE_LAST + $extra; $r++) {
            $sheet->getRowDimension($r)->setRowHeight($h);
        }
    }
    $last = AM_TEMPLATE_LAST + $extra;

    // Titles
    $sheet->setCellValueExplicit(AM_TITLE_CELL, 'ATTACHMENTS STATUS for QUINCENA: ' . am_period_title($start, $end), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit(AM_PAYROLL_HEADER, "FOR PAYROLL\n" . am_period_code($start, $end), DataType::TYPE_STRING);
    $sheet->getStyle(AM_PAYROLL_HEADER)->getAlignment()->setWrapText(true);

    // Clear the sample data (also removes the "=1+A9" numbering formulas)
    for ($r = AM_FIRST_ROW; $r <= $last; $r++) {
        foreach (range('A', 'I') as $col) {
            $sheet->setCellValue($col . $r, null);
        }
    }

    // Data
    $r = AM_FIRST_ROW;
    foreach ($rows as $i => $row) {
        $rec = $row['rec'];
        $sheet->setCellValue('A' . $r, $i + 1);
        $sheet->setCellValueExplicit('B' . $r, $row['name'], DataType::TYPE_STRING);

        if ($rec) {
            am_set_date($sheet, 'C' . $r, $rec['submission_date']);
            $sheet->setCellValueExplicit('D' . $r, AM_STATUS_LABELS[$rec['status']] ?? (string)$rec['status'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E' . $r, (string)$rec['remarks'], DataType::TYPE_STRING);
            am_set_date($sheet, 'F' . $r, $rec['date_informed'] ?? null);
            $comp = $rec['compliance_status'] ?? '';
            if ($comp !== '' && $comp !== null) {
                $sheet->setCellValueExplicit('G' . $r, AM_COMPLIANCE_LABELS[$comp] ?? (string)$comp, DataType::TYPE_STRING);
            }
            am_set_date($sheet, 'H' . $r, $rec['date_complied'] ?? null);
            // FOR PAYROLL = final status after compliance; falls back to the status
            $final = !empty($rec['payroll_status']) ? $rec['payroll_status'] : $rec['status'];
            $sheet->setCellValueExplicit('I' . $r, AM_STATUS_LABELS[$final] ?? (string)$final, DataType::TYPE_STRING);
        }
        $r++;
    }

    foreach (['C', 'F', 'H'] as $col) {
        $sheet->getStyle($col . AM_FIRST_ROW . ':' . $col . $last)->getNumberFormat()->setFormatCode('mm/dd/yyyy');
    }

    // Row stripes (the template's table style used to draw these)
    for ($r = AM_FIRST_ROW; $r <= $last; $r++) {
        $sheet->getStyle("C$r:I$r")->getFill()->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB(($r - AM_FIRST_ROW) % 2 === 0 ? 'FFFFFFFF' : 'FFF6F8F9');
    }

    $sheet->setAutoFilter('A8:I' . $last);

    am_apply_conditional_formats($sheet, $start, $last);
    am_apply_validations($sheet, $last);
}

function am_set_date($sheet, $coord, $value)
{
    if (empty($value) || $value === '0000-00-00') {
        return;
    }
    $sheet->setCellValue($coord, XlDate::PHPToExcel(new DateTime($value)));
}

/**
 * The template's colour rules contain hard-coded dates of one quincena, so they
 * are rebuilt for the period being exported.
 */
function am_apply_conditional_formats($sheet, $start, $last)
{
    foreach (array_keys($sheet->getConditionalStylesCollection()) as $range) {
        $sheet->removeConditionalStyles($range);
    }

    $f = AM_FIRST_ROW;
    $s = new DateTime($start);
    $date = 'DATE(' . $s->format('Y') . ',' . (int)$s->format('n') . ',' . (int)$s->format('j') . ')';

    // C: green = submitted on/before the start of the period (advance / on time), red = after (late)
    $sheet->setConditionalStyles("C$f:C$last", [
        am_cf("AND(C$f<>\"\",C$f<=$date)", 'FFC6EFCE'),
        am_cf("AND(C$f<>\"\",C$f>$date)",  'FFF4C7C3'),
    ]);

    // D and I: status colours (same colours as the template)
    foreach (['D' => 'FFFDE4FF', 'I' => 'FFFED2EC'] as $col => $reviewColor) {
        $sheet->setConditionalStyles("$col$f:$col$last", [
            am_cf("ISNUMBER(SEARCH(\"NO ATTACHMENT\",$col$f))",      'FFFFC000'),
            am_cf("ISNUMBER(SEARCH(\"FOR REVIEW\",$col$f))",         $reviewColor),
            am_cf("ISNUMBER(SEARCH(\"LACKING INFORMATION\",$col$f))", 'FFB2DFE4'),
            am_cf("ISNUMBER(SEARCH(\"COMPLETE AND LATE\",$col$f))",  'FF67FFD0'),
            am_cf("ISNUMBER(SEARCH(\"COMPLETE\",$col$f))",           'FFD0F0D0'),
        ]);
    }

    // G: compliance
    $sheet->setConditionalStyles("G$f:G$last", [
        am_cf("ISNUMBER(SEARCH(\"LATE\",G$f))",    'FFFFC7CE'),
        am_cf("ISNUMBER(SEARCH(\"ON-TIME\",G$f))", 'FFC6EFCE'),
    ]);
}

function am_cf($formula, $argb)
{
    $c = new Conditional();
    $c->setConditionType(Conditional::CONDITION_EXPRESSION);
    $c->addCondition($formula);
    $c->setStopIfTrue(true);
    $fill = $c->getStyle()->getFill();
    $fill->setFillType(Fill::FILL_SOLID);
    $fill->getStartColor()->setARGB($argb);
    $fill->getEndColor()->setARGB($argb);
    return $c;
}

/** Drop-downs for the status columns and date checks for the date columns. */
function am_apply_validations($sheet, $last)
{
    $statusList     = 'Complete,Lacking Information,For Review,No Attachment,Complete and Late';
    $complianceList = 'On-Time Compliance,Late Compliance';

    $listDv = function ($list) {
        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_LIST);
        $dv->setAllowBlank(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorTitle('Invalid entry');
        $dv->setError('Please choose a value from the list.');
        $dv->setFormula1('"' . $list . '"');
        return $dv;
    };
    $dateDv = function () {
        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_DATE);
        $dv->setOperator(DataValidation::OPERATOR_BETWEEN);
        $dv->setAllowBlank(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorTitle('Invalid date');
        $dv->setError('Please enter a valid date (mm/dd/yyyy).');
        $dv->setFormula1('36526');   // 2000-01-01
        $dv->setFormula2('73415');   // 2100-01-01
        return $dv;
    };

    $status = $listDv($statusList);
    $comp   = $listDv($complianceList);
    $date   = $dateDv();

    for ($r = AM_FIRST_ROW; $r <= $last; $r++) {
        $sheet->getCell("D$r")->setDataValidation(clone $status);
        $sheet->getCell("I$r")->setDataValidation(clone $status);
        $sheet->getCell("G$r")->setDataValidation(clone $comp);
        foreach (['C', 'F', 'H'] as $col) {
            $sheet->getCell("$col$r")->setDataValidation(clone $date);
        }
    }
}

/** Worksheet name (max 31 chars, unique), e.g. "Oct 1-15 2026". */
function am_sheet_title($start, $end, array &$used)
{
    $s = new DateTime($start);
    $e = new DateTime($end);
    if ($s->format('Y-m') === $e->format('Y-m')) {
        $t = $s->format('M j') . '-' . $e->format('j Y');
    } else {
        $t = $s->format('M j') . '-' . $e->format('M j Y');
    }
    $base = $t;
    $k = 2;
    while (in_array(strtolower($t), $used, true)) {
        $t = $base . ' (' . $k++ . ')';
    }
    $used[] = strtolower($t);
    return $t;
}