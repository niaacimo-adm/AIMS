<?php
session_start();
require_once '../config/database.php';
require_once 'helpers.php';

if (!isset($_SESSION['emp_id'])) {
    echo '<tr><td colspan="4" class="text-center py-4">Not authenticated</td></tr>';
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM admin_notifications 
        WHERE admin_emp_id = ? 
        ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $_SESSION['emp_id']);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);

if (count($notifications) > 0) {
    foreach ($notifications as $notification) {
        echo '<tr class="notification-item ' . ($notification['is_read'] ? '' : 'unread') . '">
                <td>' . htmlspecialchars($notification['message']) . '</td>
                <td>
                    <span class="badge badge-' . ($notification['is_read'] ? 'success' : 'warning') . '">
                        ' . ($notification['is_read'] ? 'Read' : 'Unread') . '
                    </span>
                </td>
                <td>' . time_elapsed_string($notification['created_at']) . '</td>
                <td>
                    <button class="btn btn-sm btn-info view-notification" data-id="' . $notification['id'] . '">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-notification" data-id="' . $notification['id'] . '">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
              </tr>';
    }
} else {
    echo '<tr><td colspan="4" class="text-center py-4">No notifications found</td></tr>';
}