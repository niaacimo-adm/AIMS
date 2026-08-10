<?php
/**
 * Fills templates/coe_template.docx with a saved certificate_of_employment
 * record and streams the finished .docx to the browser.
 *
 * Place this file at: views/generate_coe.php
 * Template expected at: templates/coe_template.docx   (one level above /views)
 *
 * Usage: generate_coe.php?coe_id=123
 */
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$user_role_id = intval($_SESSION['role_id'] ?? 0);
$can_view = in_array($user_role_id, [1, 2, 12, 14, 25]);
if (!$can_view) {
    http_response_code(403);
    die('Permission denied.');
}

$coe_id = intval($_GET['coe_id'] ?? 0);
if (!$coe_id) {
    http_response_code(400);
    die('Missing coe_id.');
}

$stmt = $db->prepare("
    SELECT c.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name, e.first_name, e.last_name, e.gender
    FROM certificate_of_employment c
    LEFT JOIN employee e ON c.emp_id = e.emp_id
    WHERE c.coe_id = ?
");
$stmt->bind_param("i", $coe_id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();

if (!$rec) {
    http_response_code(404);
    die('Certificate record not found.');
}

/** Escape a value for safe insertion into a WordprocessingML text node. */
function xmlEscape(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function ordinalSuffix(int $day): string {
    if ($day % 100 >= 11 && $day % 100 <= 13) return 'th';
    switch ($day % 10) {
        case 1:  return 'st';
        case 2:  return 'nd';
        case 3:  return 'rd';
        default: return 'th';
    }
}

/** Map the employee table's gender enum ('Male'/'Female'/'Other') to a title. */
function genderTitle(?string $gender): string {
    switch ($gender) {
        case 'Male':   return 'Mr.';
        case 'Female': return 'Ms.';
        default:       return 'Mr./Ms.'; // fallback for 'Other' or missing data
    }
}

$issued = new DateTime($rec['issued_date']);
$day    = (int) $issued->format('j');

$includeSalary = (int) $rec['include_salary'] === 1;

// Note: the template's literal XML already has ", " immediately after the
// {{EMP_NAME}} token, so the replacement value must NOT include it.
$replacements = [
    '{{EMP_NAME}}'              => xmlEscape(strtoupper(trim($rec['emp_name']))),
    '{{APPOINTMENT_STATUS}}'    => xmlEscape($rec['appointment_status_text']),
    '{{POSITION}}'              => xmlEscape($rec['position_text']),
    '{{SALARY_LEAD}}'           => $includeSalary ? ' with a ' : '',
    '{{SALARY_WORD}}'           => $includeSalary ? 'total' : '',
    '{{SALARY_SPACE}}'          => $includeSalary ? ' ' : '',
    '{{SALARY_AMOUNT_CLAUSE}}'  => $includeSalary
        ? 'monthly gross salary of P' . xmlEscape(number_format((float)$rec['salary_amount'], 2))
        : '',
    '{{REQUESTOR_TITLE}}'       => xmlEscape(genderTitle($rec['gender'] ?? null)),
    '{{REQUESTOR_REF}}'         => xmlEscape($rec['requestor_ref']),
    '{{PURPOSES}}'               => xmlEscape(trim($rec['purpose'] ?? '') !== '' ? trim($rec['purpose']) : 'reference purposes'),
    '{{DAY_NUM}}'                => (string) $day,
    '{{DAY_SUFFIX}}'             => ordinalSuffix($day),
    '{{MONTH_YEAR}}'             => xmlEscape($issued->format('F, Y')),
    '{{PLACE_ISSUED}}'          => xmlEscape($rec['place_issued']),
    '{{SIGNATORY_NAME}}'        => xmlEscape($rec['signatory_name']),
    '{{SIGNATORY_TITLE}}'       => xmlEscape($rec['signatory_title']),
];

$templatePath = __DIR__ . '/../public/templates/coe_template.docx';
if (!file_exists($templatePath)) {
    http_response_code(500);
    die('COE template not found on server. Expected at: ' . $templatePath);
}

$zip = new ZipArchive();
if ($zip->open($templatePath) !== true) {
    http_response_code(500);
    die('Could not open COE template.');
}
$documentXml = $zip->getFromName('word/document.xml');
$zip->close();

if ($documentXml === false) {
    http_response_code(500);
    die('COE template is corrupted (missing word/document.xml).');
}

$documentXml = strtr($documentXml, $replacements);

// Build the output file in a temp location, copying every entry from the
// template and swapping in the filled document.xml.
$tmpFile = tempnam(sys_get_temp_dir(), 'coe_') . '.docx';
copy($templatePath, $tmpFile);

$outZip = new ZipArchive();
if ($outZip->open($tmpFile) !== true) {
    http_response_code(500);
    die('Could not prepare the generated document.');
}
$outZip->deleteName('word/document.xml');
$outZip->addFromString('word/document.xml', $documentXml);
$outZip->close();

$safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($rec['emp_name']));
$fileName = 'COE_' . $safeName . '_' . $rec['issued_date'] . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, must-revalidate');
readfile($tmpFile);
unlink($tmpFile);
exit;