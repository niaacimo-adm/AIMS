<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tasks.php';

header('Content-Type: application/json');
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
$taskManager = new TaskManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_project_tasks':
            $project_id = $_POST['project_id'];
            $stmt = $db->prepare("
                SELECT t.*, 
                    e.first_name, e.last_name,
                    creator.first_name as creator_first, creator.last_name as creator_last,
                    pb.board_name, pb.board_color
                FROM tasks t
                LEFT JOIN employee e ON t.assigned_to = e.emp_id
                LEFT JOIN employee creator ON t.created_by = creator.emp_id
                LEFT JOIN project_boards pb ON t.board_id = pb.board_id
                WHERE t.project_id = ?
                ORDER BY t.board_id, t.created_at DESC
            ");
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $tasks = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'tasks' => $tasks]);
            break;
            
        case 'create_task':
            if (empty($_POST['project_id']) || empty($_POST['title']) || empty($_POST['board_id'])) {
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                break;
            }
            
            $project_id = (int)$_POST['project_id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            $board_id = (int)$_POST['board_id'];
            $priority = $_POST['priority'] ?? 'medium';
            $labels = isset($_POST['labels']) ? $_POST['labels'] : '';
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
            $created_by = (int)$_POST['created_by'];
            
            // Get board details to determine status
            $board_stmt = $db->prepare("SELECT board_name FROM project_boards WHERE board_id = ?");
            $board_stmt->bind_param("i", $board_id);
            $board_stmt->execute();
            $board_result = $board_stmt->get_result();
            $board = $board_result->fetch_assoc();
            
            if (!$board) {
                echo json_encode(['success' => false, 'error' => 'Invalid board selected']);
                break;
            }
            
            // Convert board name to a valid status format
            // Use the board_id as the status to ensure consistency
            $status = 'board_' . $board_id;
            
            $stmt = $db->prepare("INSERT INTO tasks (project_id, title, description, board_id, status, priority, labels, due_date, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt) {
                $stmt->bind_param("isssssssii", $project_id, $title, $description, $board_id, $status, $priority, $labels, $due_date, $assigned_to, $created_by);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'task_id' => $db->insert_id]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Database preparation failed: ' . $db->error]);
            }
            break;
            
        case 'update_task_status':
            $task_id = $_POST['task_id'];
            $status = $_POST['status'];
            if ($taskManager->updateTaskStatus($task_id, $status)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update task status']);
            }
            break;
            
        case 'get_user_tasks':
            $project_id = $_POST['project_id'] ?? null;
            $result = $taskManager->getUserTasks($_SESSION['emp_id'], $project_id);
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
            echo json_encode(['success' => true, 'tasks' => $tasks]);
            break;
            
        case 'get_all_tasks':
            $filters = [];
            if (isset($_POST['project_id']) && $_POST['project_id']) {
                $filters['project_id'] = $_POST['project_id'];
            }
            if (isset($_POST['status']) && $_POST['status']) {
                $filters['status'] = $_POST['status'];
            }
            if (isset($_POST['assigned_to']) && $_POST['assigned_to']) {
                $filters['assigned_to'] = $_POST['assigned_to'];
            }
            
            $result = $taskManager->getAllTasks($filters);
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
            echo json_encode(['success' => true, 'tasks' => $tasks]);
            break;
        case 'update_task_board':
            $task_id = $_POST['task_id'];
            $board_id = $_POST['board_id'];
            
            // Get board details
            $board_check = $db->prepare("SELECT board_id FROM project_boards WHERE board_id = ?");
            $board_check->bind_param("i", $board_id);
            $board_check->execute();
            $board_result = $board_check->get_result();
            
            if ($board_result->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid board selected']);
                break;
            }
            
            // Use board_id as status for consistency
            $status = 'board_' . $board_id;
            
            // Update both board_id AND status
            $stmt = $db->prepare("UPDATE tasks SET board_id = ?, status = ? WHERE task_id = ?");
            $stmt->bind_param("isi", $board_id, $status, $task_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update task board: ' . $stmt->error]);
            }
            break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
            }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>