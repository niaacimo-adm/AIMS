<?php
session_start();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/auth.php';

// Database connection
try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Basic authorization - require at least one permission
if (!isset($_SESSION['permissions']) || empty($_SESSION['permissions'])) {
    $_SESSION['error'] = 'Your account has no permissions assigned.';
    header("Location: /login.php");
    exit();
}
?>