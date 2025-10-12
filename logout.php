<?php
// Start session at the VERY beginning, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fix the paths - since logout.php is in root directory
require_once 'config/database.php';
require_once 'includes/chat_functions.php';

// Set user offline in chat system
if (isset($_SESSION['emp_id'])) {
    try {
        $chat = new ChatFunctions();
        $chat->updateOnlineStatus($_SESSION['emp_id'], 0); // Set to offline
        error_log("User {$_SESSION['emp_id']} set offline during logout");
    } catch (Exception $e) {
        error_log("Error setting user offline: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any cached data
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page
header("Location: index.php");
exit();
?>