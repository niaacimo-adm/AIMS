<?php
require_once '../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/project_ajax.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($action) {
        case 'get_project_boards':
            $project_id = $_POST['project_id'];
            $stmt = $db->prepare("SELECT * FROM project_boards WHERE project_id = ? ORDER BY board_order");
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $boards = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'boards' => $boards]);
            break;
            
        case 'create_board':
            $project_id = $_POST['project_id'];
            $board_name = $_POST['board_name'];
            $board_description = $_POST['board_description'];
            $board_color = $_POST['board_color'];
            $board_order = $_POST['board_order'];
            
            $stmt = $db->prepare("INSERT INTO project_boards (project_id, board_name, board_description, board_color, board_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $project_id, $board_name, $board_description, $board_color, $board_order);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'board_id' => $db->insert_id]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create board']);
            }
            break;
            // Add these cases to your board_ajax.php switch statement
        case 'update_board':
            $board_id = $_POST['board_id'];
            $board_name = $_POST['board_name'];
            $board_description = $_POST['board_description'];
            $board_color = $_POST['board_color'];
            $board_order = $_POST['board_order'];
            
            $stmt = $db->prepare("UPDATE project_boards SET board_name = ?, board_description = ?, board_color = ?, board_order = ? WHERE board_id = ?");
            $stmt->bind_param("sssii", $board_name, $board_description, $board_color, $board_order, $board_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update board']);
            }
            break;

        case 'delete_board':
            $board_id = $_POST['board_id'];
            
            // First, update tasks to move them to another board or set to NULL
            // For now, we'll set them to NULL - you might want to handle this differently
            $update_tasks = $db->prepare("UPDATE tasks SET board_id = NULL WHERE board_id = ?");
            $update_tasks->bind_param("i", $board_id);
            $update_tasks->execute();
            
            // Then delete the board
            $stmt = $db->prepare("DELETE FROM project_boards WHERE board_id = ?");
            $stmt->bind_param("i", $board_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete board']);
            }
            break;
        case 'reset_boards':
            $project_id = $_POST['project_id'];
            
            // Delete existing boards
            $stmt = $db->prepare("DELETE FROM project_boards WHERE project_id = ?");
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            
            // Create default boards
            $default_boards = [
                ['Backlog', 'Tasks that are planned but not yet started', 1, '#6B7280'],
                ['To Do', 'Tasks ready to be worked on', 2, '#3B82F6'],
                ['In Progress', 'Tasks currently being worked on', 3, '#F59E0B'],
                ['Review', 'Tasks awaiting review', 4, '#8B5CF6'],
                ['Done', 'Completed tasks', 5, '#10B981']
            ];
            
            $board_stmt = $db->prepare("INSERT INTO project_boards (project_id, board_name, board_description, board_order, board_color) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($default_boards as $board) {
                $board_stmt->bind_param("issis", $project_id, $board[0], $board[1], $board[2], $board[3]);
                $board_stmt->execute();
            }
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>