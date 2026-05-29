<?php
/**
 * auth_bypass.php
 * ───────────────
 * Used by document_archive_cron.php to skip the normal browser-session
 * auth check (which would fail in a CLI/cron context).
 *
 * Place this file at: /includes/auth_bypass.php
 *
 * IMPORTANT: This file must NOT be reachable from the web on its own.
 * Add to your .htaccess or nginx config:
 *   <Files "auth_bypass.php">
 *       Order Allow,Deny
 *       Deny from all
 *   </Files>
 */

// Prevent direct web access — only allow CLI or requests that already
// went through document_archive_cron.php's token gate.
if (php_sapi_name() !== 'cli' && !defined('CRON_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

// Provide a minimal $_SESSION so any code that reads session vars
// (e.g. emp_id checks) doesn't crash with undefined-index warnings.
if (session_status() === PHP_SESSION_NONE) {
    // Sessions don't work in CLI — just fake the superglobal.
    $_SESSION = $_SESSION ?? [];
}

// Stub out any session-based employee/user IDs so permission guards
// don't block the cron path.  The cron uses cron_token auth instead.
$_SESSION['emp_id']  = $_SESSION['emp_id']  ?? 0;
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 0;