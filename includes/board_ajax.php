<?php
require_once '../config/database.php';
require_once __DIR__ . '/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request for debugging
error_log("Board AJAX Request: " . print_r($_POST, true));

$action = $_POST['action'] ?? '';

try {
    // Check if user is logged in
    if (!isset($_SESSION['emp_id'])) {
        throw new Exception('User not authenticated');
    }

    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    switch ($action) {
        case 'get_project_boards':
            if (!isset($_POST['project_id'])) {
                throw new Exception('Project ID is required');
            }
            
            $project_id = $_POST['project_id'];
            
            // Verify user has access to this project
            $access_stmt = $db->prepare("
                SELECT 1 FROM project_members 
                WHERE project_id = ? AND emp_id = ?
            ");
            $access_stmt->bind_param("ii", $project_id, $_SESSION['emp_id']);
            $access_stmt->execute();
            $has_access = $access_stmt->get_result()->num_rows > 0;
            
            if (!$has_access) {
                throw new Exception('You do not have access to this project');
            }
            
            $stmt = $db->prepare("SELECT * FROM project_boards WHERE project_id = ? ORDER BY board_order");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $db->error);
            }
            
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $boards = $result->fetch_all(MYSQLI_ASSOC);
            
            error_log("Found boards: " . count($boards));
            
            echo json_encode([
                'success' => true, 
                'boards' => $boards,
                'debug' => [
                    'project_id' => $project_id,
                    'boards_count' => count($boards)
                ]
            ]);
            break;
            
        case 'create_board':
            $project_id = $_POST['project_id'];
            $board_name = $_POST['board_name'];
            $board_description = $_POST['board_description'] ?? '';
            $board_color = $_POST['board_color'] ?? '#007bff';
            $board_order = $_POST['board_order'] ?? 0;
            
            $stmt = $db->prepare("INSERT INTO project_boards (project_id, board_name, board_description, board_color, board_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $project_id, $board_name, $board_description, $board_color, $board_order);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'board_id' => $db->insert_id]);
            } else {
                throw new Exception('Failed to create board: ' . $stmt->error);
            }
            break;
            
        case 'update_board':
            $board_id = $_POST['board_id'];
            $board_name = $_POST['board_name'];
            $board_description = $_POST['board_description'] ?? '';
            $board_color = $_POST['board_color'] ?? '#007bff';
            $board_order = $_POST['board_order'] ?? 0;
            
            $stmt = $db->prepare("UPDATE project_boards SET board_name = ?, board_description = ?, board_color = ?, board_order = ? WHERE board_id = ?");
            $stmt->bind_param("sssii", $board_name, $board_description, $board_color, $board_order, $board_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('Failed to update board: ' . $stmt->error);
            }
            break;

        case 'delete_board':
            $board_id = $_POST['board_id'];
            
            // First, update tasks to move them to another board or set to NULL
            $update_tasks = $db->prepare("UPDATE tasks SET board_id = NULL WHERE board_id = ?");
            $update_tasks->bind_param("i", $board_id);
            $update_tasks->execute();
            
            // Then delete the board
            $stmt = $db->prepare("DELETE FROM project_boards WHERE board_id = ?");
            $stmt->bind_param("i", $board_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('Failed to delete board: ' . $stmt->error);
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
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log("Board AJAX Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug' => [
            'action' => $action,
            'session_emp_id' => $_SESSION['emp_id'] ?? 'not_set'
        ]
    ]);
}
?>