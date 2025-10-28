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
                    creator.first_name as creator_first, creator.last_name as creator_last
                FROM tasks t
                LEFT JOIN employee e ON t.assigned_to = e.emp_id
                LEFT JOIN employee creator ON t.created_by = creator.emp_id
                WHERE t.project_id = ?
                ORDER BY t.created_at DESC
            ");
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $tasks = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'tasks' => $tasks]);
            break;
            
        // In your task_ajax.php file
        case 'create_task':
            $project_id = $_POST['project_id'];
            $title = $_POST['title'];
            $description = $_POST['description'];
            $board_id = $_POST['board_id']; // Add this line
            $priority = $_POST['priority'];
            $labels = isset($_POST['labels']) ? implode(',', $_POST['labels']) : '';
            $due_date = $_POST['due_date'];
            $assigned_to = $_POST['assigned_to'];
            $created_by = $_POST['created_by'];
            
            $stmt = $db->prepare("INSERT INTO tasks (project_id, title, description, board_id, priority, labels, due_date, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ississsii", $project_id, $title, $description, $board_id, $priority, $labels, $due_date, $assigned_to, $created_by);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'task_id' => $db->insert_id]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create task']);
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
            
            $stmt = $db->prepare("UPDATE tasks SET board_id = ? WHERE task_id = ?");
            $stmt->bind_param("ii", $board_id, $task_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update task board']);
            }
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>