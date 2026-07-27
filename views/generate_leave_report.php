<?php
/**
 * generate_leave_report.php
 *
 * Generates downloadable .xlsx reports from the HR Leave Monitoring module.
 *
 *   ?report=balance&month=YYYY-MM   → Leave Credits Balance report, formatted after
 *                                     ACIMO_LEAVE_BALANCE_AS_OF_APRIL_2026.xlsx
 *   ?report=activity&month=YYYY-MM  → Leave request activity log for that month
 *
 * Requires PhpSpreadsheet (composer require phpoffice/phpspreadsheet).
 * Adjust the require_once path below to match where vendor/ lives in this project.
 */

require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$user_role_id = intval($_SESSION['role_id'] ?? 0);
// Same viewer roles as hr_leave_monitoring.php (Administrator, Manager, Heads, Unit Head, +25)
$can_view = in_array($user_role_id, [1, 2, 12, 14, 25]);
if (!$can_view) {
    http_response_code(403);
    die('<p style="font-family:Arial;padding:30px;color:#c92a2a">Access denied. Insufficient privileges.</p>');
}

// ── PhpSpreadsheet ────────────────────────────────────────────────────────────
// This file is NOT bundled with the app yet — install it once with:
//     composer require phpoffice/phpspreadsheet
// and confirm vendor/autoload.php resolves from this directory.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    die('<p style="font-family:Arial;padding:30px;color:#c92a2a"><strong>PhpSpreadsheet not found.</strong><br>'
        . 'Run <code>composer require phpoffice/phpspreadsheet</code> in the project root, '
        . 'then confirm <code>' . htmlspecialchars($autoload) . '</code> exists.</p>');
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$database = new Database();
$db       = $database->getConnection();

/* ── Inputs ── */
$report_type = ($_GET['report'] ?? 'balance') === 'activity' ? 'activity' : 'balance';
$month_param = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month_param)) $month_param = date('Y-m');
$rep_year   = (int) substr($month_param, 0, 4);
$month_name = strtoupper(date('F', strtotime($month_param . '-01')));
$last_day   = date('j', strtotime('last day of ' . $month_param . '-01'));

/* Monetization factor (Monthly Salary × 12 / 249 working days), reverse-derived
   from ACIMO_LEAVE_BALANCE_AS_OF_APRIL_2026.xlsx so this report reproduces the
   agency's existing figures exactly. Adjust here if the official rate changes. */
const MONETIZATION_FACTOR = 0.0481927;
const ORG_TITLE_LINE1     = 'ALBAY-CATANDUANES IMO EMPLOYEES';

/* Standing report signatories — same three names used on the source template.
   Update these three lines if the designated preparer/reviewer/approver changes. */
const SIG_PREPARED_NAME = 'REESE P. BONAPOS';
const SIG_PREPARED_ROLE = 'Clerk Processor A';
const SIG_REVIEWED_NAME = 'MYRA M. ETCOBANEZ';
const SIG_REVIEWED_ROLE = 'Administrative Services Officer B';
const SIG_NOTED_NAME    = 'ENGR. MARK CLOYD G. SO';
const SIG_NOTED_ROLE    = 'Acting Division Manager';

if ($report_type === 'activity') {
    $spreadsheet = build_activity_report($db, $month_param, $month_name, $rep_year);
    $filename    = 'Leave_Activity_Report_' . $month_param . '.xlsx';
} else {
    $spreadsheet = build_balance_report($db, $rep_year, $month_name, $last_day);
    $filename    = 'Leave_Balance_Report_' . $month_param . '.xlsx';
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;


/* ══════════════════════════════════════════════════════════════════════════
   REPORT 1 — Leave Credits Balance (as of end of selected month)

   NOTE ON "AS OF [MONTH]": leave_balance stores one cumulative total_credits /
   used_days row per employee per YEAR (no month-level snapshots exist in the
   schema). This report therefore reflects each employee's CURRENT live balance
   for that year, labeled with the selected month's last day — the same way
   the source template is a point-in-time export, not a stored history. If
   true historical month-end snapshots are needed later, leave_balance_log
   (which already timestamps every add/deduct) can be summed up to a cutoff
   date instead.
══════════════════════════════════════════════════════════════════════════ */
function build_balance_report(mysqli $db, int $year, string $month_name, string $last_day): Spreadsheet
{
    // 1 ── Employees + position salary + appointment status + section
    $employees = [];
    $res = $db->query("
        SELECT e.emp_id, e.first_name, e.last_name, e.middle_name,
               pos.position_name, COALESCE(pos.salary, 0) AS monthly_salary,
               COALESCE(s.section_name, s2.section_name, 'Unassigned Section') AS section_name,
               COALESCE(ap.status_name, 'Unspecified') AS appt_status
        FROM employee e
        LEFT JOIN position           pos ON e.position_id           = pos.position_id
        LEFT JOIN section            s   ON e.section_id             = s.section_id
        LEFT JOIN unit_section       us  ON e.unit_section_id        = us.unit_id
        LEFT JOIN section            s2  ON us.section_id            = s2.section_id
        LEFT JOIN appointment_status ap  ON e.appointment_status_id  = ap.appointment_id
        WHERE (ap.status_name IS NULL OR ap.status_name != 'Job Order')
        ORDER BY ap.status_name, COALESCE(s.section_name, s2.section_name, 'Unassigned Section'),
                 e.last_name, e.first_name
    ");
    while ($row = $res->fetch_assoc()) $employees[] = $row;

    // 2 ── Balances for the report year (leave_type_id 1 = VL, 2 = SL — per leave_balance.php)
    $balances = [];
    $bres = $db->query("SELECT emp_id, leave_type_id, total_credits, used_days FROM leave_balance WHERE year = " . (int)$year);
    while ($b = $bres->fetch_assoc()) $balances[$b['emp_id']][$b['leave_type_id']] = $b;

    // 3 ── Group: appt_status → section_name → [employees]
    $groups = [];
    foreach ($employees as $emp) {
        $vl = $balances[$emp['emp_id']][1] ?? ['total_credits' => 0, 'used_days' => 0];
        $sl = $balances[$emp['emp_id']][2] ?? ['total_credits' => 0, 'used_days' => 0];
        $emp['vl_bal'] = round((float)$vl['total_credits'] - (float)$vl['used_days'], 3);
        $emp['sl_bal'] = round((float)$sl['total_credits'] - (float)$sl['used_days'], 3);
        $groups[$emp['appt_status']][$emp['section_name']][] = $emp;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Leave Balance');

    // Column widths (A..H, matching the source template)
    foreach (['A' => 5, 'B' => 38, 'C' => 50, 'D' => 20, 'E' => 11, 'F' => 11, 'G' => 15, 'H' => 20] as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }

    $baseFont = ['name' => 'Cambria', 'size' => 12];
    $thin     = ['borderStyle' => Border::BORDER_THIN];
    $allBorders = ['borders' => ['allBorders' => $thin]];

    $sheet->getStyle('A1:H1000')->getFont()->setName('Cambria')->setSize(12);

    $r = 2;
    $sheet->setCellValue('B' . $r, ORG_TITLE_LINE1);
    $sheet->mergeCells("B{$r}:H{$r}");
    $sheet->getStyle("B{$r}")->getFont()->setBold(true);
    $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $r++;

    $sheet->setCellValue('B' . $r, "LEAVE CREDITS AS OF " . $month_name . " {$last_day}, {$year}");
    $sheet->mergeCells("B{$r}:H{$r}");
    $sheet->getStyle("B{$r}")->getFont()->setBold(true);
    $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $r += 2;

    $subtotalRows = []; // [status => H-cell of its TOTAL row], for the grand total formula

    foreach ($groups as $status => $sections) {
        // ── Column header block (repeated for every status group, matching the source) ──
        $hdrRow1 = $r;
        $sheet->setCellValue("B{$r}", 'NAME (Surname, Given Name, MI)');
        $sheet->setCellValue("C{$r}", 'POSITION');
        $sheet->setCellValue("D{$r}", 'MONTHLY SALARY');
        $sheet->setCellValue("E{$r}", 'LEAVE CREDITS');
        $sheet->setCellValue("H{$r}", 'MONETARY VALUE');
        $sheet->mergeCells("A{$r}:A" . ($r + 1));
        $sheet->mergeCells("B{$r}:B" . ($r + 1));
        $sheet->mergeCells("C{$r}:C" . ($r + 1));
        $sheet->mergeCells("D{$r}:D" . ($r + 1));
        $sheet->mergeCells("E{$r}:G{$r}");
        $sheet->mergeCells("H{$r}:H" . ($r + 1));
        $sheet->getStyle("B{$r}:H{$r}")->getFont()->setBold(true);
        $sheet->getStyle("B{$r}:H" . ($r + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;
        $sheet->setCellValue("E{$r}", 'VL');
        $sheet->setCellValue("F{$r}", 'SL');
        $sheet->setCellValue("G{$r}", 'TOTAL');
        $sheet->getStyle("E{$r}:G{$r}")->getFont()->setBold(true);
        $r++;

        // ── Status group label ──
        $sheet->setCellValue("A{$r}", strtoupper($status));
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;

        $groupFirstDataRow = null;
        $groupSectionRanges = []; // e.g. "G9:G10" per section, joined with commas for the SUM

        foreach ($sections as $section_name => $emps) {
            $sheet->setCellValue("A{$r}", $section_name);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;

            $sectionStart = $r;
            $num = 1;
            foreach ($emps as $emp) {
                $full_name = strtoupper(trim($emp['last_name'])) . ', ' . trim($emp['first_name'])
                           . ($emp['middle_name'] ? ' ' . strtoupper(substr(trim($emp['middle_name']), 0, 1)) . '.' : '');
                $sheet->setCellValue("A{$r}", $num++);
                $sheet->setCellValue("B{$r}", $full_name);
                $sheet->setCellValue("C{$r}", $emp['position_name'] ?? '');
                $sheet->setCellValue("D{$r}", (float)$emp['monthly_salary']);
                $sheet->setCellValue("E{$r}", $emp['vl_bal']);
                $sheet->setCellValue("F{$r}", $emp['sl_bal']);
                $sheet->setCellValue("G{$r}", "=E{$r}+F{$r}");
                $sheet->setCellValue("H{$r}", "=D{$r}*G{$r}*" . MONETIZATION_FACTOR);
                $sheet->getStyle("D{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("E{$r}:G{$r}")->getNumberFormat()->setFormatCode('#,##0.000');
                $sheet->getStyle("H{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                $r++;
            }
            if ($groupFirstDataRow === null) $groupFirstDataRow = $sectionStart;
            $groupSectionRanges[] = "G{$sectionStart}:G" . ($r - 1);
        }

        // ── TOTAL row for this status group ──
        $sheet->mergeCells("A{$r}:F{$r}");
        $sheet->setCellValue("A{$r}", 'TOTAL');
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$r}:H{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');

        $gRanges = implode(',', $groupSectionRanges);
        $hRanges = str_replace('G', 'H', $gRanges);
        $sheet->setCellValue("G{$r}", "=SUM({$gRanges})");
        $sheet->setCellValue("H{$r}", "=SUM({$hRanges})");
        $sheet->getStyle("G{$r}")->getNumberFormat()->setFormatCode('#,##0.000');
        $sheet->getStyle("H{$r}")->getNumberFormat()->setFormatCode('#,##0.00');

        $subtotalRows[$status] = $r;
        $r += 2;
    }

    // ── Grand total ──
    $sheet->setCellValue("B{$r}", 'GRAND TOTAL (ALL CATEGORIES)');
    $sheet->getStyle("B{$r}")->getFont()->setBold(true);
    $grandFormula = implode('+', array_map(fn($row) => "H{$row}", $subtotalRows));
    $sheet->setCellValue("D{$r}", "=" . $grandFormula);
    $sheet->getStyle("D{$r}")->getFont()->setBold(true);
    $sheet->getStyle("D{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
    $r += 3;

    // ── Signatories ──
    $sheet->setCellValue("B{$r}", 'Prepared by:');
    $sheet->setCellValue("C{$r}", '                        Reviewed by:');
    $sheet->setCellValue("G{$r}", 'Noted by:');
    $sheet->getStyle("B{$r}:G{$r}")->getFont()->setBold(true);
    $r += 3;
    $sheet->setCellValue("B{$r}", SIG_PREPARED_NAME);
    $sheet->setCellValue("C{$r}", '                                       ' . SIG_REVIEWED_NAME);
    $sheet->setCellValue("G{$r}", SIG_NOTED_NAME);
    $sheet->getStyle("B{$r}:G{$r}")->getFont()->setBold(true);
    $r++;
    $sheet->setCellValue("B{$r}", SIG_PREPARED_ROLE);
    $sheet->setCellValue("C{$r}", '                                                        ' . SIG_REVIEWED_ROLE);
    $sheet->setCellValue("G{$r}", SIG_NOTED_ROLE);
    $sheet->getStyle("B{$r}:G{$r}")->getFont()->setName('Arial')->setSize(10);

    // Borders around the whole data block (title rows excluded)
    $sheet->getStyle("A5:H" . (max($subtotalRows) ?: 5))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $sheet->setSelectedCell('A1');
    return $spreadsheet;
}


/* ══════════════════════════════════════════════════════════════════════════
   REPORT 2 — Leave Request Activity for the selected month
   (filtered the same way the existing "Month" filter on hr_leave_monitoring
   already filters the table: DATE_FORMAT(date_from,'%Y-%m'))
══════════════════════════════════════════════════════════════════════════ */
function build_activity_report(mysqli $db, string $month_param, string $month_name, int $year): Spreadsheet
{
    $stmt = $db->prepare("
        SELECT lr.leave_request_id, lr.date_from, lr.date_to, lr.number_of_days,
               lr.status, lr.reason, lr.hr_remarks, lr.created_at,
               lt.leave_type_name,
               CONCAT(e.last_name, ', ', e.first_name) AS emp_name,
               pos.position_name,
               COALESCE(s.section_name, s2.section_name, 'Unassigned Section') AS section_name,
               COALESCE(ap.status_name, 'Unspecified') AS appt_status,
               CONCAT(hr.first_name, ' ', hr.last_name) AS approved_by_name
        FROM leave_request lr
        LEFT JOIN employee            e   ON lr.emp_id                = e.emp_id
        LEFT JOIN position            pos ON e.position_id             = pos.position_id
        LEFT JOIN section             s   ON e.section_id              = s.section_id
        LEFT JOIN unit_section        us  ON e.unit_section_id         = us.unit_id
        LEFT JOIN section             s2  ON us.section_id             = s2.section_id
        LEFT JOIN appointment_status  ap  ON e.appointment_status_id   = ap.appointment_id
        LEFT JOIN leave_type          lt  ON lr.leave_type_id          = lt.leave_type_id
        LEFT JOIN employee            hr  ON lr.approved_by            = hr.emp_id
        WHERE DATE_FORMAT(lr.date_from, '%Y-%m') = ?
          AND (ap.status_name IS NULL OR ap.status_name != 'Job Order')
        ORDER BY lr.date_from, e.last_name, e.first_name
    ");
    $stmt->bind_param('s', $month_param);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Leave Activity');

    foreach (['A' => 5, 'B' => 30, 'C' => 28, 'D' => 24, 'E' => 16, 'F' => 12, 'G' => 12, 'H' => 9,
              'I' => 12, 'J' => 22, 'K' => 34] as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }
    $sheet->getStyle('A1:K500')->getFont()->setName('Cambria')->setSize(11);

    $sheet->setCellValue('A2', 'LEAVE REQUEST ACTIVITY REPORT');
    $sheet->mergeCells('A2:K2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A3', strtoupper($month_name) . ' ' . $year);
    $sheet->mergeCells('A3:K3');
    $sheet->getStyle('A3')->getFont()->setBold(true);
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $headers = ['#', 'Employee Name', 'Position', 'Section', 'Appointment', 'Leave Type',
                'Date From', 'Date To', 'No. of Days', 'Status', 'Approved/Rejected By', 'Remarks'];
    $r = 5;
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue("{$col}{$r}", $h);
        $col++;
    }
    $sheet->getStyle("A{$r}:L{$r}")->getFont()->setBold(true);
    $sheet->getStyle("A{$r}:L{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F5E9');
    $sheet->getStyle("A{$r}:L{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
    $r++;

    $firstDataRow = $r;
    $num = 1;
    $statusCounts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0, 'Disapproved' => 0, 'Cancelled' => 0];
    foreach ($rows as $row) {
        $sheet->setCellValue("A{$r}", $num++);
        $sheet->setCellValue("B{$r}", $row['emp_name']);
        $sheet->setCellValue("C{$r}", $row['position_name'] ?? '');
        $sheet->setCellValue("D{$r}", $row['section_name']);
        $sheet->setCellValue("E{$r}", $row['appt_status']);
        $sheet->setCellValue("F{$r}", $row['leave_type_name'] ?? '');
        $sheet->setCellValue("G{$r}", $row['date_from']);
        $sheet->setCellValue("H{$r}", $row['date_to']);
        $sheet->setCellValue("I{$r}", (float)$row['number_of_days']);
        $sheet->setCellValue("J{$r}", $row['status']);
        $sheet->setCellValue("K{$r}", $row['approved_by_name'] ?? '');
        $sheet->setCellValue("L{$r}", $row['hr_remarks'] ?: ($row['reason'] ?? ''));
        $sheet->getStyle("G{$r}:H{$r}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        if (isset($statusCounts[$row['status']])) $statusCounts[$row['status']]++;
        $r++;
    }
    $lastDataRow = $r - 1;

    if ($lastDataRow >= $firstDataRow) {
        $sheet->getStyle("A{$firstDataRow}:L{$lastDataRow}")->getBorders()->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN);
    }

    // ── Summary block ──
    $r += 2;
    $sheet->setCellValue("A{$r}", 'SUMMARY');
    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
    $r++;
    foreach ($statusCounts as $label => $count) {
        $sheet->setCellValue("A{$r}", $label . ':');
        $sheet->setCellValue("B{$r}", $count);
        $r++;
    }
    $sheet->setCellValue("A{$r}", 'TOTAL:');
    $sheet->setCellValue("B{$r}", "=SUM(B" . ($r - count($statusCounts)) . ":B" . ($r - 1) . ")");
    $sheet->getStyle("A{$r}:B{$r}")->getFont()->setBold(true);

    $sheet->setSelectedCell('A1');
    return $spreadsheet;
}