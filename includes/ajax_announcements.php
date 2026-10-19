<?php
/**
 * Public announcements feed for the login page.
 * Returns ONLY fields that are safe to show to anyone (no names, no leave data).
 * Location: includes/ajax_announcements.php (called from login.php).
 * Keep the LIMIT in sync with the note shown on views/announcements.php.
 */
ob_start();   // swallow stray output (BOM/whitespace in includes, PHP notices) so the JSON stays valid

require_once '../config/database.php';

$out = ['success' => false, 'data' => []];
$status = 200;

try {
    $database = new Database();
    $db = $database->getConnection();

    $sql = "SELECT id, title, body, category, is_pinned,
                   COALESCE(publish_at, created_at) AS posted_at
            FROM announcements
            WHERE is_active = 1
              AND (publish_at IS NULL OR publish_at <= NOW())
              AND (expires_at IS NULL OR expires_at >= NOW())
            ORDER BY is_pinned DESC, posted_at DESC
            LIMIT 10";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $out = ['success' => true, 'data' => $rows];
} catch (Throwable $e) {
    error_log('[ajax_announcements] ' . $e->getMessage());   // details go to the server log, not the browser
    $status = 500;
}

ob_end_clean();
http_response_code($status);
header('Content-Type: application/json; charset=utf-8');
if ($status === 200) header('Cache-Control: public, max-age=60');
echo json_encode($out);