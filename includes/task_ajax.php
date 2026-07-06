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
                    e.first_name, e.last_name, e.picture as assignee_picture,
                    creator.first_name as creator_first, creator.last_name as creator_last, creator.picture as creator_picture,
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

            // Fetch all assignees (multiple per task) for this project's tasks
            $assignees_stmt = $db->prepare("
                SELECT ta.task_id, e.emp_id, e.first_name, e.last_name, e.picture
                FROM task_assignees ta
                JOIN employee e ON ta.emp_id = e.emp_id
                JOIN tasks t ON ta.task_id = t.task_id
                WHERE t.project_id = ?
            ");
            $assignees_stmt->bind_param("i", $project_id);
            $assignees_stmt->execute();
            $assignees_result = $assignees_stmt->get_result();
            $assigneesByTask = [];
            while ($row = $assignees_result->fetch_assoc()) {
                $assigneesByTask[$row['task_id']][] = $row;
            }

            foreach ($tasks as &$task) {
                $task['assignees'] = $assigneesByTask[$task['task_id']] ?? [];
            }
            unset($task);

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
            // Support multiple assignees (sent as assigned_to[] from Select2 multi-select)
            $assigned_to_input = $_POST['assigned_to'] ?? [];
            if (!is_array($assigned_to_input)) {
                $assigned_to_input = $assigned_to_input === '' ? [] : [$assigned_to_input];
            }
            $assignee_ids = array_values(array_unique(array_filter(array_map('intval', $assigned_to_input), function($v) {
                return $v > 0;
            })));
            // Keep legacy single-value column in sync (first selected assignee, or null)
            $assigned_to = !empty($assignee_ids) ? $assignee_ids[0] : null;
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
            
            // Use the board name as status for display purposes
            $status = strtolower(str_replace(' ', '', $board['board_name']));
            
            $stmt = $db->prepare("INSERT INTO tasks (project_id, title, description, board_id, status, priority, labels, due_date, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt) {
                $stmt->bind_param("isssssssii", $project_id, $title, $description, $board_id, $status, $priority, $labels, $due_date, $assigned_to, $created_by);
                
                if ($stmt->execute()) {
                    $new_task_id = $db->insert_id;

                    // Save the (possibly multiple) assignees
                    if (!empty($assignee_ids)) {
                        $assignee_stmt = $db->prepare("INSERT INTO task_assignees (task_id, emp_id) VALUES (?, ?)");
                        foreach ($assignee_ids as $emp_id) {
                            $assignee_stmt->bind_param("ii", $new_task_id, $emp_id);
                            $assignee_stmt->execute();
                        }
                    }

                    echo json_encode(['success' => true, 'task_id' => $new_task_id]);
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
        
        case 'update_task':
            if (empty($_POST['task_id']) || empty($_POST['title']) || empty($_POST['board_id'])) {
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                break;
            }
            
            $task_id = (int)$_POST['task_id'];
            $title = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            $board_id = (int)$_POST['board_id'];
            $priority = $_POST['priority'] ?? 'medium';
            $labels = isset($_POST['labels']) ? $_POST['labels'] : '';
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            // Support multiple assignees (sent as assigned_to[] from Select2 multi-select)
            $assigned_to_input = $_POST['assigned_to'] ?? [];
            if (!is_array($assigned_to_input)) {
                $assigned_to_input = $assigned_to_input === '' ? [] : [$assigned_to_input];
            }
            $assignee_ids = array_values(array_unique(array_filter(array_map('intval', $assigned_to_input), function($v) {
                return $v > 0;
            })));
            // Keep legacy single-value column in sync (first selected assignee, or null)
            $assigned_to = !empty($assignee_ids) ? $assignee_ids[0] : null;
            
            // Get board details
            $board_stmt = $db->prepare("SELECT board_name FROM project_boards WHERE board_id = ?");
            $board_stmt->bind_param("i", $board_id);
            $board_stmt->execute();
            $board_result = $board_stmt->get_result();
            $board = $board_result->fetch_assoc();
            
            if (!$board) {
                echo json_encode(['success' => false, 'error' => 'Invalid board selected']);
                break;
            }
            
            // Use the board name as status for display purposes
            $status = strtolower(str_replace(' ', '', $board['board_name']));
            
            $stmt = $db->prepare("UPDATE tasks SET title = ?, description = ?, board_id = ?, status = ?, priority = ?, labels = ?, due_date = ?, assigned_to = ?, updated_at = NOW() WHERE task_id = ?");
            
            if ($stmt) {
                $stmt->bind_param("sssssssii", $title, $description, $board_id, $status, $priority, $labels, $due_date, $assigned_to, $task_id);
                
                if ($stmt->execute()) {
                    // Sync the task_assignees table to match the new selection
                    $del_stmt = $db->prepare("DELETE FROM task_assignees WHERE task_id = ?");
                    $del_stmt->bind_param("i", $task_id);
                    $del_stmt->execute();

                    if (!empty($assignee_ids)) {
                        $assignee_stmt = $db->prepare("INSERT INTO task_assignees (task_id, emp_id) VALUES (?, ?)");
                        foreach ($assignee_ids as $emp_id) {
                            $assignee_stmt->bind_param("ii", $task_id, $emp_id);
                            $assignee_stmt->execute();
                        }
                    }

                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Database preparation failed: ' . $db->error]);
            }
            break;

        case 'delete_task':
            $task_id = (int)$_POST['task_id'];

            $del_assignees = $db->prepare("DELETE FROM task_assignees WHERE task_id = ?");
            $del_assignees->bind_param("i", $task_id);
            $del_assignees->execute();

            $stmt = $db->prepare("DELETE FROM tasks WHERE task_id = ?");
            $stmt->bind_param("i", $task_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
            }
            break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
            }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?> 