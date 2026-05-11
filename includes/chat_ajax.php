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
            
        case 'toggle_reaction':
            $message_id = intval($_POST['message_id'] ?? 0);
            $emoji      = trim($_POST['emoji'] ?? '');

            $allowed_emojis = ['👍','❤️','😂','😮','😢','👏'];
            if (!$message_id || !in_array($emoji, $allowed_emojis)) {
                echo json_encode(['success' => false, 'error' => 'Invalid input']);
                break;
            }

            $result = $chat->toggleReaction($message_id, $current_user_id, $emoji);
            echo json_encode(['success' => true, 'result' => $result]);
            break;

        case 'get_reactions':
            $room_id = intval($_POST['room_id'] ?? 0);
            if (!$room_id) {
                echo json_encode(['success' => false, 'error' => 'Invalid room_id']);
                break;
            }
            $reactions = $chat->getReactions($room_id);
            echo json_encode(['success' => true, 'reactions' => $reactions]);
            break;

        case 'delete_message':
            $message_id = intval($_POST['message_id'] ?? 0);
            if ($message_id) {
                $deleted = $chat->deleteMessage($message_id, $current_user_id);
                echo json_encode(['success' => $deleted, 'error' => $deleted ? null : 'Not allowed or message not found']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid message_id']);
            }
            break;
        
        case 'send_file':
        // Check if a file was uploaded
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
            break;
        }

        $room_id = $_POST['room_id'];
        $file = $_FILES['file'];
        
        // Validate max size (50MB)
        $maxSize = 50 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'error' => 'File exceeds 50MB limit']);
            break;
        }

    // Validate file type (optional)
    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'text/plain', 'application/zip', 'application/x-rar-compressed',
                    'video/mp4', 'audio/mpeg'];
    if (!in_array($file['type'], $allowedMime)) {
        echo json_encode(['success' => false, 'error' => 'File type not allowed']);
        break;
    }

    // Ensure upload directory exists
    $uploadDir = '../uploads/chat/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
    $newName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $baseName) . '.' . $extension;
    $targetPath = $uploadDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Use the ChatFunctions method to insert file metadata
        $message_id = $chat->sendFileMessage(
            $room_id,
            $current_user_id,
            $file['name'],        // original filename as message text
            strpos($file['type'], 'image/') === 0 ? 'image' : 'file',
            $newName,
            $file['type'],
            $file['size']
        );
        if ($message_id) {
            echo json_encode(['success' => true, 'message_id' => $message_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save file metadata to database']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
    }
    break;
            }
            
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>