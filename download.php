<?php
/**
 * download.php — Force-download handler
 * Location: htdocs/sahur/download.php
 */

session_start();
require_once 'config/database.php';

/* ── MIME map ─────────────────────────────────────────────────────────── */
$mimeTypes = [
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'  => 'application/msword',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls'  => 'application/vnd.ms-excel',
    'pdf'  => 'application/pdf',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'zip'  => 'application/zip',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
];

/* ── Validate input ───────────────────────────────────────────────────── */
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

/* ── Fetch record from DB ─────────────────────────────────────────────── */
$database = new Database();
$db       = $database->getConnection();

$stmt = $db->prepare("SELECT form_name, file_path FROM company_forms WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    http_response_code(404);
    exit('Form not found in database.');
}

/* ── Resolve absolute path ────────────────────────────────────────────── */
// DB stores: '../uploads/forms/filename.ext'  (relative to views/)
// From sahur/ root, that '../uploads/...' goes ABOVE the project — wrong.
// We strip any leading '../' and resolve directly from __DIR__ (sahur/)

$storedPath = $row['file_path'];

// Remove leading '../' or './' segments to normalize to project root
$cleanPath = preg_replace('#^(\.\./)+#', '', $storedPath); // '../uploads/forms/x' → 'uploads/forms/x'
$absPath   = realpath(__DIR__ . '/' . $cleanPath);

/* ── Security: must be inside sahur/uploads/ ─────────────────────────── */
$uploadsBase = realpath(__DIR__ . '/uploads');

if (
    $uploadsBase === false
    || $absPath === false
    || !file_exists($absPath)
    || strpos($absPath, $uploadsBase) !== 0
) {
    http_response_code(404);
    // ── DEBUG: uncomment to see exact paths ──
    // exit('absPath=' . var_export($absPath,true) . ' | uploadsBase=' . var_export($uploadsBase,true) . ' | clean=' . $cleanPath);
    exit('File not found on server.');
}

/* ── MIME & filename ──────────────────────────────────────────────────── */
$ext      = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
$mime     = $mimeTypes[$ext] ?? 'application/octet-stream';
$safeName = trim(preg_replace('/[^A-Za-z0-9_\-\. ]/', '_', $row['form_name'])) . '.' . $ext;

/* ── Stream the file ──────────────────────────────────────────────────── */
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($absPath));

if (ob_get_level()) ob_end_clean();
readfile($absPath);
exit;