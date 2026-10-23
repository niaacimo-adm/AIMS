<?php
/**
 * includes/session_guard.php
 *
 * Put this at the TOP of every page/endpoint that needs a logged-in user,
 * right after session_start():
 *
 *     session_start();
 *     require_once __DIR__ . '/../includes/session_guard.php';   // adjust path
 *
 * It enforces: must be logged in, idle timeout, same browser (user-agent) as
 * at login. AJAX/JSON requests get HTTP 401 instead of a redirect.
 *
 * Optional: define('AIMS_LOGIN_URL', '/login.php') before including if your
 * folder layout differs from views/*.php -> ../login.php
 */

if (!defined('AIMS_IDLE_TIMEOUT')) define('AIMS_IDLE_TIMEOUT', 30 * 60);   // 30 minutes
if (!defined('AIMS_LOGIN_URL'))    define('AIMS_LOGIN_URL', '../login.php');

function aims_deny($reason) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();

    $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
           || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please sign in again.']);
    } else {
        header('Location: ' . AIMS_LOGIN_URL . '?' . $reason . '=1');
    }
    exit;
}

if (empty($_SESSION['user_id'])) {
    aims_deny('auth');
}
if (time() - (int)($_SESSION['last_activity'] ?? 0) > AIMS_IDLE_TIMEOUT) {
    aims_deny('expired');
}
if (isset($_SESSION['ua_hash'])
    && !hash_equals($_SESSION['ua_hash'], hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''))) {
    aims_deny('auth');   // session cookie used from a different browser
}

$_SESSION['last_activity'] = time();