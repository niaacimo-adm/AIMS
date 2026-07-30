<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tasks.php';
require_once __DIR__ . '/activity_log.php';
require_once __DIR__ . '/project_access.php';

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
            $project_id = (int)$_POST['project_id'];
            $current_emp_id = (int)($_SESSION['emp_id'] ?? 0);

            if (!isProjectMember($db, $project_id, $current_emp_id)) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                break;
            }

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

            $current_emp_id = (int)($_SESSION['emp_id'] ?? 0);
            if (!isProjectMember($db, $project_id, $current_emp_id)) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                break;
            }

            // Tasks can only be assigned to people who are actually members
            // of the project — never trust the client-side dropdown alone.
            $invalidAssignees = getNonMemberAssignees($db, $project_id, $assignee_ids);
            if (!empty($invalidAssignees)) {
                echo json_encode(['success' => false, 'error' => 'One or more selected assignees are not members of this project']);
                break;
            }
            
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

                    logActivity($db, 'task', $new_task_id, $project_id, $created_by, 'created', "Created the task \"$title\" in \"{$board['board_name']}\"");

                    echo json_encode(['success' => true, 'task_id' => $new_task_id]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Database preparation failed: ' . $db->error]);
            }
            break;
            
        case 'update_task_status':
            $task_id = (int)($_POST['task_id'] ?? 0);
            $status = trim($_POST['status'] ?? '');

            if (!$task_id || $status === '') {
                echo json_encode(['success' => false, 'error' => 'Missing task or status']);
                break;
            }

            // scrum.php groups cards purely by board_id, not by this status string.
            // If we only update status here (as the old code did), the task keeps
            // showing up under its old column on the scrumboard. Look up the task's
            // project, find the board whose name maps to this status (the same
            // slug create_task/update_task_board use), and move board_id together
            // with status so both pages agree on the column.
            $proj_stmt = $db->prepare("SELECT project_id, status AS old_status, title FROM tasks WHERE task_id = ?");
            $proj_stmt->bind_param("i", $task_id);
            $proj_stmt->execute();
            $proj_row = $proj_stmt->get_result()->fetch_assoc();

            if (!$proj_row) {
                echo json_encode(['success' => false, 'error' => 'Task not found']);
                break;
            }

            if (!isProjectMember($db, (int)$proj_row['project_id'], (int)($_SESSION['emp_id'] ?? 0))) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                break;
            }

            $board_id = null;
            $boards_stmt = $db->prepare("SELECT board_id, board_name FROM project_boards WHERE project_id = ?");
            $boards_stmt->bind_param("i", $proj_row['project_id']);
            $boards_stmt->execute();
            $boards_result = $boards_stmt->get_result();
            while ($b = $boards_result->fetch_assoc()) {
                if (strtolower(str_replace(' ', '', $b['board_name'])) === strtolower($status)) {
                    $board_id = $b['board_id'];
                    break;
                }
            }

            if ($board_id) {
                $sync_stmt = $db->prepare("UPDATE tasks SET status = ?, board_id = ? WHERE task_id = ?");
                $sync_stmt->bind_param("sii", $status, $board_id, $task_id);
                $updated = $sync_stmt->execute();
            } else {
                // No board in this project matches that status name (e.g. a custom
                // board) — fall back to updating status only, same as before.
                $updated = $taskManager->updateTaskStatus($task_id, $status);
            }

            if ($updated) {
                logActivity(
                    $db, 'task', $task_id, $proj_row['project_id'], (int)($_SESSION['emp_id'] ?? 0),
                    'status_changed',
                    "Changed status from \"{$proj_row['old_status']}\" to \"$status\"",
                    ['from' => $proj_row['old_status'], 'to' => $status]
                );
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
            $task_id = (int)($_POST['task_id'] ?? 0);
            $board_id = (int)($_POST['board_id'] ?? 0);

            // Get board details
            $board_check = $db->prepare("SELECT board_id, board_name FROM project_boards WHERE board_id = ?");
            $board_check->bind_param("i", $board_id);
            $board_check->execute();
            $board_result = $board_check->get_result();

            if ($board_result->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid board selected']);
                break;
            }
            $board = $board_result->fetch_assoc();

            // Derive status from the board's name the exact same way create_task /
            // update_task do, instead of the previous 'board_<id>' placeholder.
            // That placeholder is what caused My Tasks (which reads the literal
            // status string, e.g. 'backlog'/'todo'/'inprogress') to fall out of
            // sync with drags made on the scrumboard, which only updated board_id.
            $status = strtolower(str_replace(' ', '', $board['board_name']));

            // Grab the task's project + title (and old board name) before the
            // update so the log entry can describe the move.
            $move_info_stmt = $db->prepare("
                SELECT t.project_id, t.title, pb.board_name AS old_board_name
                FROM tasks t
                LEFT JOIN project_boards pb ON t.board_id = pb.board_id
                WHERE t.task_id = ?
            ");
            $move_info_stmt->bind_param("i", $task_id);
            $move_info_stmt->execute();
            $move_info = $move_info_stmt->get_result()->fetch_assoc();

            if ($move_info && !isProjectMember($db, (int)$move_info['project_id'], (int)($_SESSION['emp_id'] ?? 0))) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                break;
            }

            // Update both board_id AND status so both pages agree on the column.
            $stmt = $db->prepare("UPDATE tasks SET board_id = ?, status = ? WHERE task_id = ?");
            $stmt->bind_param("isi", $board_id, $status, $task_id);
            
            if ($stmt->execute()) {
                if ($move_info) {
                    logActivity(
                        $db, 'task', $task_id, (int)$move_info['project_id'], (int)($_SESSION['emp_id'] ?? 0),
                        'moved',
                        "Moved \"" . $move_info['title'] . "\" from \"" . ($move_info['old_board_name'] ?? 'Unknown') . "\" to \"" . $board['board_name'] . "\"",
                        ['from_board' => $move_info['old_board_name'], 'to_board' => $board['board_name']]
                    );
                }
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

            // Needed for the activity log entry below
            $project_id_for_log_stmt = $db->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
            $project_id_for_log_stmt->bind_param("i", $task_id);
            $project_id_for_log_stmt->execute();
            $project_id_for_log_row = $project_id_for_log_stmt->get_result()->fetch_assoc();
            $project_id_for_log = $project_id_for_log_row ? (int)$project_id_for_log_row['project_id'] : 0;

            if (!$project_id_for_log || !isProjectMember($db, $project_id_for_log, (int)($_SESSION['emp_id'] ?? 0))) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                break;
            }

            // Tasks can only be assigned to people who are actually members
            // of the project — never trust the client-side dropdown alone.
            $invalidAssignees = getNonMemberAssignees($db, $project_id_for_log, $assignee_ids);
            if (!empty($invalidAssignees)) {
                echo json_encode(['success' => false, 'error' => 'One or more selected assignees are not members of this project']);
                break;
            }

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

                    logActivity($db, 'task', $task_id, $project_id_for_log, (int)($_SESSION['emp_id'] ?? 0), 'updated', "Updated the task \"$title\"");

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
            $current_emp_id = (int)($_SESSION['emp_id'] ?? 0);

            // Grab project/title/creator before deleting so the log entry
            // (recorded against the project, not the now-gone task) still
            // reads clearly, and so we can check permission below.
            $del_info_stmt = $db->prepare("
                SELECT t.project_id, t.title, p.created_by AS project_creator
                FROM tasks t
                JOIN projects p ON t.project_id = p.project_id
                WHERE t.task_id = ?
            ");
            $del_info_stmt->bind_param("i", $task_id);
            $del_info_stmt->execute();
            $del_info = $del_info_stmt->get_result()->fetch_assoc();

            if (!$del_info) {
                echo json_encode(['success' => false, 'error' => 'Task not found']);
                break;
            }

            // Only the creator of the project may delete tasks — not the
            // task's own creator, not assignees, not other project members.
            if ((int)$del_info['project_creator'] !== $current_emp_id) {
                echo json_encode(['success' => false, 'error' => 'Only the project creator can delete tasks']);
                break;
            }

            $del_assignees = $db->prepare("DELETE FROM task_assignees WHERE task_id = ?");
            $del_assignees->bind_param("i", $task_id);
            $del_assignees->execute();

            $stmt = $db->prepare("DELETE FROM tasks WHERE task_id = ?");
            $stmt->bind_param("i", $task_id);
            
            if ($stmt->execute()) {
                logActivity($db, 'task', $task_id, (int)$del_info['project_id'], $current_emp_id, 'deleted', "Deleted the task \"" . $del_info['title'] . "\"");
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
            }
            break;

        case 'get_task_activity':
            $task_id = (int)($_POST['task_id'] ?? 0);
            if (!$task_id) {
                echo json_encode(['success' => false, 'error' => 'Missing task id']);
                break;
            }

            $task_proj_stmt = $db->prepare("SELECT project_id FROM tasks WHERE task_id = ?");
            $task_proj_stmt->bind_param("i", $task_id);
            $task_proj_stmt->execute();
            $task_proj_row = $task_proj_stmt->get_result()->fetch_assoc();

            if (!$task_proj_row || !isProjectMember($db, (int)$task_proj_row['project_id'], (int)($_SESSION['emp_id'] ?? 0))) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                break;
            }

            $result = getActivityLog($db, 'task', $task_id);
            $activity = [];
            while ($row = $result->fetch_assoc()) {
                $activity[] = $row;
            }
            echo json_encode(['success' => true, 'activity' => $activity]);
            break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
            }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>