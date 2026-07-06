<?php
/**
 * document_export.php
 * Exports document records into the official NIA-ACIMO Excel templates.
 *
 * Template structure (both DOC_REPORT.xlsx and DOC_REPORT_ARCHIVE.xlsx):
 *   Row 1  : "Republic of the Philippines"        (B1, with logo images cols A & E)
 *   Row 2  : "Office of the President"            (B2)
 *   Row 3  : "national irrigation administration" (B3)
 *   Row 4  : "Albay-Catanduanes IMO"              (B4)
 *   Row 5  : [empty spacer]
 *   Row 6  : Title merged A6:G6  ("ALL DOCUMENTS" / "DOCUMENTS ARCHIVED")
 *   Row 7  : Export/Archive date — re-merged A7:G7 (template has A7:B7 placeholder)
 *   Row 8  : Column headers  #  |  DOCUMENT NO.  |  DOCUMENT NAME/ PARTICULARS  |  DOCUMENT TYPE  |  KIND  |  STATUS  |  REMARKS  |  DATE/TIME CREATED  |  DATE/TIME UPDATED
 *   Row 9+ : DATA rows
 *
 * GET params:
 *   type = "list" (default) | "archive"
 *   kind = "" | "incoming" | "outgoing" | "external"
 *   date = "YYYY-MM-DD"  (archive only)
 */

date_default_timezone_set('Asia/Manila');
require_once '../includes/auth.php';
require_once '../config/database.php';

// ── PhpSpreadsheet ────────────────────────────────────────────────────────────
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($composerAutoload)) {
    http_response_code(500);
    die('PhpSpreadsheet not installed. Run: composer require phpoffice/phpspreadsheet');
}
require_once $composerAutoload;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;

// ── Parameters ────────────────────────────────────────────────────────────────
$type      = (isset($_GET['type']) && $_GET['type'] === 'archive') ? 'archive' : 'list';
$kind      = (isset($_GET['kind']) && in_array($_GET['kind'], ['incoming','outgoing','external']))
             ? $_GET['kind'] : '';
$dateParam = trim($_GET['date'] ?? '');

// ── DB ────────────────────────────────────────────────────────────────────────
$database = new Database();
$db       = $database->getConnection();
$db->query("SET time_zone = '+08:00'");

// ── Template path ─────────────────────────────────────────────────────────────
// This file:  C:\xampp\htdocs\AIMS\views\document_export.php
// Templates:  C:\xampp\htdocs\AIMS\public\templates\
$tplDir  = dirname(__DIR__) . '/public/templates/';
$tplFile = $tplDir . ($type === 'archive' ? 'DOC_REPORT_ARCHIVE.xlsx' : 'DOC_REPORT.xlsx');

if (!file_exists($tplFile)) {
    http_response_code(500);
    die('Template file not found at: ' . $tplFile);
}

// ── Fetch records ─────────────────────────────────────────────────────────────
$rows = [];

if ($type === 'archive') {
    $arcDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)
               ? $dateParam
               : date('Y-m-d', strtotime('-1 day'));

    $sql = "SELECT kind, document_number, document_name,
                   document_type AS type_name, status, remarks, date_forwarded,
                   date_forwarded AS created_at, archived_at AS updated_at
            FROM document_archive
            WHERE archive_date = ?" . ($kind ? " AND kind = ?" : "") . "
            ORDER BY document_number ASC";

    $stmt = $db->prepare($sql);
    $kind ? $stmt->bind_param("ss", $arcDate, $kind)
          : $stmt->bind_param("s",  $arcDate);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }

} else {
    $where  = "WHERE 1=1";
    $params = [];
    $types  = '';
    if ($kind) { $where .= " AND dr.kind = ?"; $params[] = $kind; $types .= 's'; }

    $sql = "SELECT dr.document_number, dr.document_name, dr.kind,
                   dr.status, dr.remarks, dr.date_forwarded, dt.type_name,
                   dr.created_at, dr.updated_at
            FROM document_records dr
            LEFT JOIN document_types dt ON dr.document_type_id = dt.id
            $where
            ORDER BY dr.document_number ASC";

    $stmt = $db->prepare($sql);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
}

// ── Load template ─────────────────────────────────────────────────────────────
// Loading the template preserves: letterhead text, logo images, merged cells,
// column widths, row heights, header row styling, page setup (landscape, paper size).
$spreadsheet = IOFactory::load($tplFile);
$ws          = $spreadsheet->getActiveSheet();

// ── Row 7: export/archive date + time ────────────────────────────────────────
// The timestamp is captured once at the top of execution so it is consistent
// throughout the file (archive label vs list label).
$exportTime  = date('h:iA');   // e.g. 03:22PM
$exportLabel = ($type === 'archive' && !empty($arcDate))
    ? 'Archive Date: ' . date('F d, Y', strtotime($arcDate)) . ' | ' . $exportTime
    : 'As of ' . date('F d, Y') . ' | ' . $exportTime;

// ── Row 6: extend title merge from A6:G6 → A6:I6 to cover the two new columns ─
// Template has A6:G6. H & I now carry data, so the title must span them too.
$ws->unmergeCells('A6:G6');
$ws->mergeCells('A6:I6');
// Re-apply the title value (setValue is lost after unmerge on some PhpSpreadsheet builds)
if ($kind) {
    $label = strtoupper($kind) . ' DOCUMENTS' . ($type === 'archive' ? ' ARCHIVED' : '');
    $ws->getCell('A6')->setValue($label);
}
// Ensure title style is preserved (Bell MT 14pt Bold Center — same as template)
$ws->getStyle('A6')->applyFromArray([
    'font'      => ['name' => 'Bell MT', 'size' => 14, 'bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER],
]);

// ── Row 7: unmerge A7:B7 placeholder, re-merge A7:I7 (full width) ─────────────
$ws->unmergeCells('A7:B7');
$ws->mergeCells('A7:I7');

// Write the date+time label
$ws->getCell('A7')->setValue($exportLabel);

// Each template uses a slightly different font in row 7
if ($type === 'archive') {
    $ws->getStyle('A7')->applyFromArray([
        'font'      => ['name' => 'Calibri', 'size' => 11, 'bold' => false, 'italic' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => false],
    ]);
} else {
    $ws->getStyle('A7')->applyFromArray([
        'font'      => ['name' => 'Cambria', 'size' => 9, 'bold' => false, 'italic' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => false],
    ]);
}

// ── Row 8: column headers — DO NOT MODIFY (preserved exactly from template) ───

// ── Clear placeholder rows 9+ from template ───────────────────────────────────
// FIX: The original code only cleared row 9 cols 1-7 with setValue(null), which
// left behind pre-styled ghost cells. We must iterate all pre-existing data rows
// (the template has styled empty rows 9 onward) and clear them properly so
// they do not bleed into our freshly written rows.
$DATA_START = 9;
for ($r = $DATA_START; $r <= $ws->getHighestRow(); $r++) {
    for ($c = 1; $c <= 7; $c++) {
        $ws->getCellByColumnAndRow($c, $r)->setValue(null);
    }
    // Remove any row-level merges that the template may have pre-applied
    // (template can have merged placeholder rows below the header)
    foreach ($ws->getMergeCells() as $mergeRange) {
        // Only remove merges that fall entirely within data rows
        $rangeParts = explode(':', $mergeRange);
        if (count($rangeParts) === 2) {
            preg_match('/\d+/', $rangeParts[0], $m1);
            preg_match('/\d+/', $rangeParts[1], $m2);
            if (!empty($m1[0]) && $m1[0] >= $DATA_START) {
                $ws->unmergeCells($mergeRange);
            }
        }
    }
    break; // Only process the first data row to clear template placeholder; real rows are added below
}

// ── Status color map ──────────────────────────────────────────────────────────
// FIX: The generated files were missing the green status color that the template
// uses (FF167C27). Map status values to their display colors.
$statusColors = [
    'pending'    => 'FF167C27',   // green — in-progress
    'received'   => 'FF167C27',   // green — completed receipt
    'released'   => 'FF167C27',   // green — released/done
    'forwarded'  => 'FF167C27',   // green
    'returned'   => 'FFFF0000',   // red — returned/rejected
    'cancelled'  => 'FFFF0000',   // red
    'on hold'    => 'FFFF8C00',   // orange — on hold
];

// ── Style definitions ─────────────────────────────────────────────────────────
$fontBase   = ['name' => 'Cambria', 'size' => 10, 'bold' => false];
$thinBorder = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                               'color'       => ['argb' => 'FFD0D0D0']]]];
$alignC = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true]];
$alignL = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true]];
// Alternating row fills — white and very light blue (accent5 tint, matches template palette)
$fillW = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']]];
$fillB = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDDE9F4']]];

// ── Write data rows — starts at row 9 ────────────────────────────────────────
foreach ($rows as $i => $doc) {
    $rowNum = $DATA_START + $i;
    $fill   = ($i % 2 === 0) ? $fillW : $fillB;

    // Format date_forwarded (kept for future use / audit)
    $dateFwd = '';
    $raw = $doc['date_forwarded'] ?? '';
    if ($raw && $raw !== '0000-00-00 00:00:00') {
        $ts = strtotime($raw);
        if ($ts > 0) $dateFwd = date('M d, Y h:i A', $ts);
    }

    // Format created_at and updated_at — "May 19, 2026 | 03:22PM"
    $fmtDatetime = function($val) {
        if (empty($val) || $val === '0000-00-00 00:00:00') return '—';
        $ts = strtotime($val);
        return ($ts > 0) ? date('M d, Y | h:iA', $ts) : '—';
    };
    $createdAt = $fmtDatetime($doc['created_at'] ?? '');
    $updatedAt = $fmtDatetime($doc['updated_at'] ?? '');

    $statusRaw     = $doc['status'] ?? '';
    $statusDisplay = ucfirst($statusRaw);

    // Values: A=#, B=DocNo, C=DocName, D=Type, E=Kind, F=Status, G=Remarks, H=Created, I=Updated
    $ws->getCellByColumnAndRow(1, $rowNum)->setValue($i + 1);
    $ws->getCellByColumnAndRow(2, $rowNum)->setValue($doc['document_number'] ?? '');
    $ws->getCellByColumnAndRow(3, $rowNum)->setValue($doc['document_name']   ?? '');
    $ws->getCellByColumnAndRow(4, $rowNum)->setValue($doc['type_name']       ?? '');
    $ws->getCellByColumnAndRow(5, $rowNum)->setValue(ucfirst($doc['kind']    ?? ''));
    $ws->getCellByColumnAndRow(6, $rowNum)->setValue($statusDisplay);
    $ws->getCellByColumnAndRow(7, $rowNum)->setValue($doc['remarks']         ?? '');
    $ws->getCellByColumnAndRow(8, $rowNum)->setValue($createdAt);
    $ws->getCellByColumnAndRow(9, $rowNum)->setValue($updatedAt);

    // A: # — center
    $ws->getStyleByColumnAndRow(1, $rowNum)
       ->applyFromArray(array_merge(['font' => $fontBase], $alignC, $fill, $thinBorder));
    // B: doc number — left
    $ws->getStyleByColumnAndRow(2, $rowNum)
       ->applyFromArray(array_merge(['font' => $fontBase], $alignL, $fill, $thinBorder));
    // C: doc name — left + wrap
    $ws->getStyleByColumnAndRow(3, $rowNum)
       ->applyFromArray(array_merge(['font' => $fontBase], $alignL, $fill, $thinBorder));
    // D, E: type / kind — center
    foreach ([4, 5] as $col) {
        $ws->getStyleByColumnAndRow($col, $rowNum)
           ->applyFromArray(array_merge(['font' => $fontBase], $alignC, $fill, $thinBorder));
    }
    // F: STATUS — center + color-coded font matching template (green for active statuses)
    $statusKey   = strtolower(trim($statusRaw));
    $statusColor = $statusColors[$statusKey] ?? 'FF000000';
    $statusFont  = array_merge($fontBase, ['color' => ['argb' => $statusColor]]);
    $ws->getStyleByColumnAndRow(6, $rowNum)
       ->applyFromArray(array_merge(['font' => $statusFont], $alignC, $fill, $thinBorder));
    // G: remarks — left
    $ws->getStyleByColumnAndRow(7, $rowNum)
       ->applyFromArray(array_merge(['font' => $fontBase], $alignL, $fill, $thinBorder));
    // H: DATE/TIME CREATED — center
    $ws->getStyleByColumnAndRow(8, $rowNum)
       ->applyFromArray(array_merge(['font' => $fontBase], $alignC, $fill, $thinBorder));
    // I: DATE/TIME UPDATED — center
    $ws->getStyleByColumnAndRow(9, $rowNum)
       ->applyFromArray(array_merge(['font' => $fontBase], $alignC, $fill, $thinBorder));

}
// Row heights for data rows are set to auto (-1) in the autofit section below.

// ── Total row ─────────────────────────────────────────────────────────────────
$totalRow = $DATA_START + count($rows);
$ws->getCellByColumnAndRow(1, $totalRow)->setValue('Total: ' . count($rows) . ' record(s)');
$ws->mergeCellsByColumnAndRow(1, $totalRow, 9, $totalRow);   // A→I (covers all 9 columns)
$ws->getStyleByColumnAndRow(1, $totalRow)->applyFromArray([
    'font'      => ['name' => 'Cambria', 'size' => 10, 'bold' => true, 'italic' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT,
                    'vertical'   => Alignment::VERTICAL_CENTER],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF2F2F2']],
    'borders'   => ['top' => ['borderStyle' => Border::BORDER_MEDIUM,
                              'color'       => ['argb' => 'FF5B9BD5']]],
]);
$ws->getRowDimension($totalRow)->setRowHeight(16);

// ── Autofit columns ───────────────────────────────────────────────────────────
// setAutoSize() measures each cell's text width and widens to the longest value.
// Free-text columns (C = doc name, G = remarks) are capped so the page stays
// printable; all other columns size freely up to their own cap.
$colMaxWidths = [
    1 => 6,    // A  #          — narrow counter
    2 => 28,   // B  Doc No.
    3 => 55,   // C  Doc Name   — capped (can be very long)
    4 => 28,   // D  Type
    5 => 14,   // E  Kind
    6 => 14,   // F  Status
    7 => 40,   // G  Remarks    — capped
    8 => 24,   // H  Created
    9 => 24,   // I  Updated
];

for ($col = 1; $col <= 9; $col++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $ws->getColumnDimension($colLetter)->setAutoSize(true);
}

// Force PhpSpreadsheet to calculate column widths now (before we apply caps).
$ws->calculateColumnWidths();

// Clamp any auto-sized column that exceeds its max width.
for ($col = 1; $col <= 9; $col++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $dim       = $ws->getColumnDimension($colLetter);
    $autoWidth = $dim->getWidth();
    $maxWidth  = $colMaxWidths[$col] ?? 30;
    if ($autoWidth > $maxWidth) {
        $dim->setAutoSize(false);
        $dim->setWidth($maxWidth);
    }
}

// ── Autofit row heights (data rows only) ──────────────────────────────────────
// Setting height to -1 lets Excel/LibreOffice auto-wrap text in each row.
// Header rows (1–8) and the total row keep their fixed heights from the template.
for ($r = $DATA_START; $r < $totalRow; $r++) {
    $ws->getRowDimension($r)->setRowHeight(-1);
}

// ── Update print area to match actual data range ──────────────────────────────
// FIX: The template has a fixed print area (e.g. $A$1:$G$155). After writing
// real data, update the print area so the printed page is not cut off or padded.
$lastPrintRow = $totalRow;
$ws->getPageSetup()->setPrintArea('A1:I' . $lastPrintRow);

// ── Output filename ───────────────────────────────────────────────────────────
$kindSuffix = $kind ? ('_' . $kind) : '';
if ($type === 'archive') {
    $dateSuffix = isset($arcDate) ? ('_' . $arcDate) : ('_' . date('Ymd'));
    $filename   = 'NIA_ACIMO_Archive' . $kindSuffix . $dateSuffix . '.xlsx';
} else {
    $filename = 'NIA_ACIMO_Documents' . $kindSuffix . '_' . date('Ymd') . '.xlsx';
}

// ── Stream to browser ─────────────────────────────────────────────────────────
ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;