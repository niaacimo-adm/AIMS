<?php
// Start session at the VERY beginning, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once 'chat_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['emp_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$chat = new ChatFunctions();
$action = $_POST['action'] ?? '';
$current_user_id = $_SESSION['emp_id'];

try {
    switch ($action) {
        case 'update_online_status':
            $chat->updateOnlineStatus($current_user_id, 1);
            echo json_encode(['success' => true]);
            break;

        case 'get_online_users':
            $users = $chat->getOnlineUsers($current_user_id);
            echo json_encode(['success' => true, 'users' => $users]);
            break;

        case 'get_messages':
            $room_id = $_POST['room_id'];
            $last_message_id = $_POST['last_message_id'] ?? 0;
            $messages = $chat->getMessages($room_id, $last_message_id);
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;

        case 'send_message':
            $room_id = $_POST['room_id'];
            $message = trim($_POST['message']);
            
            if (!empty($message)) {
                $message_id = $chat->sendMessage($room_id, $current_user_id, $message);
                if ($message_id) {
                    echo json_encode(['success' => true, 'message_id' => $message_id]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            }
            break;

        case 'mark_read':
            $room_id = $_POST['room_id'];
            $chat->markMessagesAsRead($room_id, $current_user_id);
            echo json_encode(['success' => true]);
            break;

        case 'get_unread_count':
            $unread_count = $chat->getUnreadCount($current_user_id);
            echo json_encode(['success' => true, 'unread_count' => $unread_count]);
            break;
        case 'get_private_room':
            $recipient_id = $_POST['recipient_id'];
            $room_id = $chat->getPrivateRoom($current_user_id, $recipient_id);
            if ($room_id) {
                echo json_encode(['success' => true, 'room_id' => $room_id]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create room']);
            }
            break;
        case 'get_unread_counts':
            $unread_data = $chat->getUnreadCounts($current_user_id);
            echo json_encode([
                'success' => true,
                'total_unread' => $unread_data['total_unread'],
                'user_unread_counts' => $unread_data['user_unread_counts']
            ]);
            break;
        case 'set_offline':
            $chat->updateOnlineStatus($current_user_id, 0);
            echo json_encode(['success' => true]);
            break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
            }
            
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>