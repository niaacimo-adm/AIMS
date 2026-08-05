<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session
error_log("=== PROJECT AJAX DEBUG ===");
error_log("Session emp_id: " . ($_SESSION['emp_id'] ?? 'Not set'));
error_log("Session status: " . session_status());

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/projects.php';
require_once __DIR__ . '/activity_log.php';
require_once __DIR__ . '/project_access.php';

header('Content-Type: application/json');

// Debug: Check if ProjectManager class exists
if (!class_exists('ProjectManager')) {
    error_log("ProjectManager class not found");
    echo json_encode(['success' => false, 'error' => 'ProjectManager class not loaded']);
    exit;
}

try {
    $projectManager = new ProjectManager();
    error_log("ProjectManager initialized successfully");
} catch (Exception $e) {
    error_log("Failed to initialize ProjectManager: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to initialize ProjectManager: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    error_log("Action received: " . $action);
    
    switch ($action) {
        // Alias: my_scrum_project.php and my_scrum_task.php's project filter
        // call this action name, but only 'get_user_projects' existed below —
        // every call was silently hitting the 'Invalid action' default and
        // rendering nothing. Reuse the exact same membership-based logic.
        case 'get_my_projects':
        case 'get_user_projects':
            $emp_id = $_SESSION['emp_id'] ?? null;
            if (!$emp_id) {
                error_log("No emp_id in session");
                echo json_encode(['success' => false, 'error' => 'User not logged in']);
                break;
            }
            
            error_log("Getting projects for user: " . $emp_id);
            $result = $projectManager->getUserProjects($emp_id);
            
            if (!$result) {
                error_log("getUserProjects returned false");
                echo json_encode(['success' => false, 'error' => 'Database query failed']);
                break;
            }
            
            $projects = [];
            while ($row = $result->fetch_assoc()) {
                $projects[] = $row;
            }
            error_log("Found " . count($projects) . " projects");
            
            // If no projects found but we know user has projects, try a direct query
            if (count($projects) === 0) {
                error_log("No projects found via getUserProjects for emp_id=$emp_id — primary JOIN query (which filters on p.status = 'active') returned 0 rows. Falling back to direct project_members lookup...");
                try {
                    $db = $projectManager->getConnection();
                    
                    // Get project IDs from project_members
                    $stmt = $db->prepare("SELECT project_id FROM project_members WHERE emp_id = ?");
                    $stmt->bind_param("i", $emp_id);
                    $stmt->execute();
                    $member_result = $stmt->get_result();
                    
                    $project_ids = [];
                    while ($row = $member_result->fetch_assoc()) {
                        $project_ids[] = $row['project_id'];
                    }
                    
                    if (count($project_ids) > 0) {
                        $placeholders = implode(',', array_fill(0, count($project_ids), '?'));
                        // Include the same task/member aggregates as getUserProjects() so
                        // total_tasks / completed_tasks / my_tasks are never silently dropped
                        // when this fallback path is the one that ends up running.
                        $project_query = "SELECT p.*, pm.role,
                                COUNT(DISTINCT t.task_id) as total_tasks,
                                COUNT(DISTINCT CASE WHEN t.status = 'done' THEN t.task_id END) as completed_tasks,
                                COUNT(DISTINCT CASE WHEN ta.emp_id = ? THEN t.task_id END) as my_tasks
                                FROM projects p
                                JOIN project_members pm ON p.project_id = pm.project_id AND pm.emp_id = ?
                                LEFT JOIN tasks t ON t.project_id = p.project_id
                                LEFT JOIN task_assignees ta ON ta.task_id = t.task_id
                                WHERE p.project_id IN ($placeholders)
                                GROUP BY p.project_id
                                ORDER BY p.created_at DESC";
                        $project_stmt = $db->prepare($project_query);
                        $types = 'ii' . str_repeat('i', count($project_ids));
                        $params = array_merge([$emp_id, $emp_id], $project_ids);
                        $project_stmt->bind_param($types, ...$params);
                        $project_stmt->execute();
                        $project_result = $project_stmt->get_result();
                        
                        while ($row = $project_result->fetch_assoc()) {
                            $projects[] = $row;
                        }
                        error_log("Direct query found " . count($projects) . " projects (with task stats)");
                    }
                } catch (Exception $e) {
                    error_log("Direct query failed: " . $e->getMessage());
                }
            }
            
            echo json_encode(['success' => true, 'projects' => $projects]);
            break;
            
        case 'test_connection':
            try {
                $db = $projectManager->getConnection();
                
                // Test projects table
                $result = $db->query("SELECT COUNT(*) as count FROM projects");
                $projects_count = $result->fetch_assoc()['count'];
                
                // Test project_members table
                $result = $db->query("SELECT COUNT(*) as count FROM project_members");
                $members_count = $result->fetch_assoc()['count'];
                
                // Test current user
                $emp_id = $_SESSION['emp_id'] ?? 0;
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM project_members WHERE emp_id = ?");
                $stmt->bind_param("i", $emp_id);
                $stmt->execute();
                $user_projects_count = $stmt->get_result()->fetch_assoc()['count'];
                
                echo json_encode([
                    'success' => true, 
                    'debug' => [
                        'projects_total' => $projects_count,
                        'members_total' => $members_count,
                        'user_projects' => $user_projects_count,
                        'session_emp_id' => $emp_id,
                        'session_status' => session_status()
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        // In your project_ajax.php file, in the create_project action
        case 'create_project':
            try {
                $db = $projectManager->getConnection();

                $requester_id = (int)($_SESSION['emp_id'] ?? 0);
                if (!$projectManager->canCreateProject($requester_id)) {
                    echo json_encode(['success' => false, 'error' => 'You do not have permission to create projects. Only Administrators, Heads, Managers, and Unit Heads can create projects.']);
                    break;
                }
                
                // Extract variables from POST
                $project_name = $_POST['project_name'] ?? '';
                $project_description = $_POST['project_description'] ?? '';
                $project_code = $_POST['project_code'] ?? '';
                $created_by = $_POST['created_by'] ?? $_SESSION['emp_id'];
                $start_date = $_POST['start_date'] ?? null;
                $end_date = $_POST['end_date'] ?? null;
                $color = $_POST['color'] ?? '#007bff';
                
                // Validate required fields
                if (empty($project_name) || empty($project_code)) {
                    echo json_encode(['success' => false, 'error' => 'Project name and code are required']);
                    break;
                }
                
                // Check if project code already exists
                $check_stmt = $db->prepare("SELECT project_id FROM projects WHERE project_code = ?");
                $check_stmt->bind_param("s", $project_code);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'error' => 'Project code already exists']);
                    break;
                }
                
                // Insert project
                $stmt = $db->prepare("INSERT INTO projects (project_name, project_description, project_code, created_by, start_date, end_date, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssisss", $project_name, $project_description, $project_code, $created_by, $start_date, $end_date, $color);
                
                if ($stmt->execute()) {
                    $project_id = $db->insert_id;
                    
                    // Create default boards for the new project
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
                    
                    // Add creator as project member with 'owner' role
                    $member_stmt = $db->prepare("INSERT INTO project_members (project_id, emp_id, role, added_by) VALUES (?, ?, 'owner', ?)");
                    $member_stmt->bind_param("iii", $project_id, $created_by, $created_by);
                    $member_stmt->execute();

                    // Add any additional selected members (e.g. from a multi-select)
                    $member_ids = isset($_POST['members']) ? (array)$_POST['members'] : [];
                    $member_ids = array_unique(array_filter(array_map('intval', $member_ids), function($v) use ($created_by) {
                        return $v > 0 && $v != $created_by;
                    }));

                    if (!empty($member_ids)) {
                        $extra_member_stmt = $db->prepare("INSERT INTO project_members (project_id, emp_id, role, added_by) VALUES (?, ?, 'member', ?)");
                        foreach ($member_ids as $member_id) {
                            $extra_member_stmt->bind_param("iii", $project_id, $member_id, $created_by);
                            $extra_member_stmt->execute();
                        }
                    }

                    logActivity($db, 'project', $project_id, $project_id, (int)$created_by, 'created', "Created the project \"$project_name\"");

                    echo json_encode(['success' => true, 'project_id' => $project_id]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to create project: ' . $db->error]);
                }
            } catch (Exception $e) {
                error_log("Project creation error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_assignable_employees':
            $employees = [];
            $result = $projectManager->getAssignableEmployees();
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
            echo json_encode(['success' => true, 'employees' => $employees]);
            break;
            
        case 'get_projects_monitoring':
            $result = $projectManager->getAllProjects();
            $projects = [];
            while ($row = $result->fetch_assoc()) {
                $projects[] = $row;
            }
            echo json_encode(['success' => true, 'projects' => $projects]);
            break;
            
        case 'get_project_details':
            $project_id = $_POST['project_id'];
            $project = $projectManager->getProject($project_id, $_SESSION['emp_id']);
            if ($project) {
                // Get project members
                $members_result = $projectManager->getProjectMembers($project_id);
                $members = [];
                while ($row = $members_result->fetch_assoc()) {
                    $members[] = $row;
                }
                $project['members'] = $members;
                echo json_encode(['success' => true, 'project' => $project]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Project not found']);
            }
            break;

        case 'delete_project':
            try {
                $db = $projectManager->getConnection();

                $project_id = (int)($_POST['project_id'] ?? 0);
                if ($project_id <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid project']);
                    break;
                }

                // Only the person who created the project may delete it —
                // not other owners/managers, and not just anyone with
                // canAssignTasks()-level access.
                $creator_stmt = $db->prepare("SELECT project_name, created_by FROM projects WHERE project_id = ?");
                $creator_stmt->bind_param("i", $project_id);
                $creator_stmt->execute();
                $project_row = $creator_stmt->get_result()->fetch_assoc();

                if (!$project_row) {
                    echo json_encode(['success' => false, 'error' => 'Project not found']);
                    break;
                }

                $current_emp_id_for_delete = (int)($_SESSION['emp_id'] ?? 0);
                if ((int)$project_row['created_by'] !== $current_emp_id_for_delete) {
                    echo json_encode(['success' => false, 'error' => 'Only the project creator can delete this project']);
                    break;
                }

                $deleted_project_name = $project_row['project_name'];

                // Clean up dependent records first (in case FK constraints aren't set to CASCADE)
                $db->query("DELETE ta FROM task_assignees ta INNER JOIN tasks t ON ta.task_id = t.task_id WHERE t.project_id = $project_id");
                $db->query("DELETE FROM tasks WHERE project_id = $project_id");
                $db->query("DELETE FROM project_boards WHERE project_id = $project_id");
                $db->query("DELETE FROM project_members WHERE project_id = $project_id");

                $stmt = $db->prepare("DELETE FROM projects WHERE project_id = ?");
                $stmt->bind_param("i", $project_id);

                if ($stmt->execute()) {
                    logActivity($db, 'project', $project_id, $project_id, $current_emp_id_for_delete, 'deleted', "Deleted the project \"$deleted_project_name\"");
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to delete project: ' . $stmt->error]);
                }
            } catch (Exception $e) {
                error_log("Project deletion error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'update_project_status':
            $project_id = (int)$_POST['project_id'];
            $status = $_POST['status'];
            if ($projectManager->updateProjectStatus($project_id, $status)) {
                // Ensure we have a DB connection for logging
                $db = $projectManager->getConnection();
                logActivity($db, 'project', $project_id, $project_id, (int)($_SESSION['emp_id'] ?? 0), 'status_changed', "Changed project status to \"$status\"", ['to' => $status]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update project status']);
            }
            break;

        case 'get_project_activity':
            $project_id = (int)($_POST['project_id'] ?? 0);
            if (!$project_id) {
                echo json_encode(['success' => false, 'error' => 'Missing project id']);
                break;
            }
            $db = $projectManager->getConnection();

            if (!isProjectMember($db, $project_id, (int)($_SESSION['emp_id'] ?? 0))) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                break;
            }

            $result = getProjectActivityLog($db, $project_id);
            $activity = [];
            while ($row = $result->fetch_assoc()) {
                $activity[] = $row;
            }
            echo json_encode(['success' => true, 'activity' => $activity]);
            break;

        case 'get_project_assignable_members':
            // Used by the scrumboard's task assignee dropdowns — unlike
            // get_assignable_employees (which lists everyone, for picking a
            // project's initial members), this only returns people who are
            // already members of the given project, since only members can
            // be assigned its tasks.
            $project_id = (int)($_POST['project_id'] ?? 0);
            if (!$project_id) {
                echo json_encode(['success' => false, 'error' => 'Missing project id']);
                break;
            }
            $db = $projectManager->getConnection();

            if (!isProjectMember($db, $project_id, (int)($_SESSION['emp_id'] ?? 0))) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                break;
            }

            $members_result = $projectManager->getProjectMembers($project_id);
            $employees = [];
            while ($row = $members_result->fetch_assoc()) {
                $employees[] = $row;
            }
            echo json_encode(['success' => true, 'employees' => $employees]);
            break;

        case 'add_project_member':
            try {
                $project_id = (int)($_POST['project_id'] ?? 0);
                $requester_id = (int)($_SESSION['emp_id'] ?? 0);
                $role = $_POST['role'] ?? 'member';
                $emp_ids = isset($_POST['emp_ids']) ? (array)$_POST['emp_ids'] : [];
                $emp_ids = array_unique(array_filter(array_map('intval', $emp_ids), function($v) {
                    return $v > 0;
                }));

                if (!$project_id) {
                    echo json_encode(['success' => false, 'error' => 'Missing project id']);
                    break;
                }
                if (empty($emp_ids)) {
                    echo json_encode(['success' => false, 'error' => 'Please select at least one employee']);
                    break;
                }
                if (!in_array($role, ['member', 'manager', 'owner'], true)) {
                    $role = 'member';
                }

                $db = $projectManager->getConnection();

                // Only existing project members can view the project; only
                // owners/managers (or admins/section heads via canAssignTasks)
                // may add new members to it.
                if (!isProjectMember($db, $project_id, $requester_id)) {
                    echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                    break;
                }

                $role_check = $db->prepare("SELECT role FROM project_members WHERE project_id = ? AND emp_id = ?");
                $role_check->bind_param("ii", $project_id, $requester_id);
                $role_check->execute();
                $role_row = $role_check->get_result()->fetch_assoc();
                $requester_role = $role_row['role'] ?? null;

                $canManageMembers = in_array($requester_role, ['owner', 'manager'], true)
                    || $projectManager->canAssignTasks($requester_id);

                if (!$canManageMembers) {
                    echo json_encode(['success' => false, 'error' => 'Only project owners or managers can add members']);
                    break;
                }

                // Find who's already a member so we don't duplicate or error out
                $existing = [];
                $existing_result = $projectManager->getProjectMembers($project_id);
                while ($row = $existing_result->fetch_assoc()) {
                    $existing[(int)$row['emp_id']] = true;
                }

                $added = [];
                $skipped = [];
                $insert_stmt = $db->prepare("INSERT INTO project_members (project_id, emp_id, role, added_by) VALUES (?, ?, ?, ?)");

                foreach ($emp_ids as $emp_id) {
                    if (isset($existing[$emp_id])) {
                        $skipped[] = $emp_id;
                        continue;
                    }
                    $insert_stmt->bind_param("iisi", $project_id, $emp_id, $role, $requester_id);
                    if ($insert_stmt->execute()) {
                        $added[] = $emp_id;
                    }
                }

                if (!empty($added)) {
                    $project_row = $db->query("SELECT project_name FROM projects WHERE project_id = " . (int)$project_id)->fetch_assoc();
                    $project_name = $project_row['project_name'] ?? '';
                    logActivity($db, 'project', $project_id, $project_id, $requester_id, 'members_added', count($added) . " member(s) added to \"$project_name\"", ['added' => $added]);
                }

                // Return the refreshed member list so the UI can update immediately
                $members_result = $projectManager->getProjectMembers($project_id);
                $members = [];
                while ($row = $members_result->fetch_assoc()) {
                    $members[] = $row;
                }

                echo json_encode([
                    'success' => true,
                    'added_count' => count($added),
                    'skipped_count' => count($skipped),
                    'members' => $members
                ]);
            } catch (Exception $e) {
                error_log("Add project member error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'remove_project_member':
            try {
                $project_id = (int)($_POST['project_id'] ?? 0);
                $emp_id = (int)($_POST['emp_id'] ?? 0);
                $requester_id = (int)($_SESSION['emp_id'] ?? 0);

                if (!$project_id || !$emp_id) {
                    echo json_encode(['success' => false, 'error' => 'Missing project or employee id']);
                    break;
                }

                $db = $projectManager->getConnection();

                if (!isProjectMember($db, $project_id, $requester_id)) {
                    echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                    break;
                }

                $role_check = $db->prepare("SELECT role FROM project_members WHERE project_id = ? AND emp_id = ?");
                $role_check->bind_param("ii", $project_id, $requester_id);
                $role_check->execute();
                $role_row = $role_check->get_result()->fetch_assoc();
                $requester_role = $role_row['role'] ?? null;

                $canManageMembers = in_array($requester_role, ['owner', 'manager'], true)
                    || $projectManager->canAssignTasks($requester_id);

                if (!$canManageMembers) {
                    echo json_encode(['success' => false, 'error' => 'Only project owners or managers can remove members']);
                    break;
                }

                // Look up the member being removed
                $target_check = $db->prepare("SELECT role FROM project_members WHERE project_id = ? AND emp_id = ?");
                $target_check->bind_param("ii", $project_id, $emp_id);
                $target_check->execute();
                $target_row = $target_check->get_result()->fetch_assoc();

                if (!$target_row) {
                    echo json_encode(['success' => false, 'error' => 'That person is not a member of this project']);
                    break;
                }

                if ($target_row['role'] === 'owner') {
                    echo json_encode(['success' => false, 'error' => 'The project owner cannot be removed']);
                    break;
                }

                $delete_stmt = $db->prepare("DELETE FROM project_members WHERE project_id = ? AND emp_id = ?");
                $delete_stmt->bind_param("ii", $project_id, $emp_id);

                if ($delete_stmt->execute()) {
                    $project_row = $db->query("SELECT project_name FROM projects WHERE project_id = " . (int)$project_id)->fetch_assoc();
                    $project_name = $project_row['project_name'] ?? '';
                    logActivity($db, 'project', $project_id, $project_id, $requester_id, 'member_removed', "Removed a member from \"$project_name\"", ['emp_id' => $emp_id]);

                    // Return the refreshed member list so the UI can update immediately
                    $members_result = $projectManager->getProjectMembers($project_id);
                    $members = [];
                    while ($row = $members_result->fetch_assoc()) {
                        $members[] = $row;
                    }

                    echo json_encode(['success' => true, 'members' => $members]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to remove member: ' . $db->error]);
                }
            } catch (Exception $e) {
                error_log("Remove project member error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'request_project_membership':
            try {
                $project_id = (int)($_POST['project_id'] ?? 0);
                $requester_id = (int)($_SESSION['emp_id'] ?? 0);

                if (!$project_id || !$requester_id) {
                    echo json_encode(['success' => false, 'error' => 'Missing project id']);
                    break;
                }

                $db = $projectManager->getConnection();

                // Already a member? Nothing to request.
                if (isProjectMember($db, $project_id, $requester_id)) {
                    echo json_encode(['success' => false, 'error' => 'You are already a member of this project']);
                    break;
                }

                // Don't stack duplicate pending requests
                $dup_check = $db->prepare("SELECT request_id FROM project_join_requests WHERE project_id = ? AND emp_id = ? AND status = 'pending'");
                $dup_check->bind_param("ii", $project_id, $requester_id);
                $dup_check->execute();

                if ($dup_check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'error' => 'You already have a pending request for this project']);
                    break;
                }

                $insert_stmt = $db->prepare("INSERT INTO project_join_requests (project_id, emp_id, status) VALUES (?, ?, 'pending')");
                $insert_stmt->bind_param("ii", $project_id, $requester_id);

                if ($insert_stmt->execute()) {
                    $project_row = $db->query("SELECT project_name FROM projects WHERE project_id = " . (int)$project_id)->fetch_assoc();
                    $project_name = $project_row['project_name'] ?? '';
                    logActivity($db, 'project', $project_id, $project_id, $requester_id, 'membership_requested', "Requested to join \"$project_name\"");
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to send request: ' . $db->error]);
                }
            } catch (Exception $e) {
                error_log("Request project membership error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'get_pending_join_requests':
            try {
                $project_id = (int)($_POST['project_id'] ?? 0);
                $requester_id = (int)($_SESSION['emp_id'] ?? 0);

                if (!$project_id) {
                    echo json_encode(['success' => false, 'error' => 'Missing project id']);
                    break;
                }

                $db = $projectManager->getConnection();

                if (!isProjectMember($db, $project_id, $requester_id)) {
                    echo json_encode(['success' => false, 'error' => 'You do not have permission to open this project']);
                    break;
                }

                $role_check = $db->prepare("SELECT role FROM project_members WHERE project_id = ? AND emp_id = ?");
                $role_check->bind_param("ii", $project_id, $requester_id);
                $role_check->execute();
                $role_row = $role_check->get_result()->fetch_assoc();
                $requester_role = $role_row['role'] ?? null;

                $canManageMembers = in_array($requester_role, ['owner', 'manager'], true)
                    || $projectManager->canAssignTasks($requester_id);

                if (!$canManageMembers) {
                    // Not an error — just nothing to show for regular members
                    echo json_encode(['success' => true, 'requests' => []]);
                    break;
                }

                $query = "SELECT jr.request_id, jr.project_id, jr.emp_id, jr.requested_at,
                                 e.first_name, e.last_name, e.picture
                          FROM project_join_requests jr
                          JOIN employee e ON jr.emp_id = e.emp_id
                          WHERE jr.project_id = ? AND jr.status = 'pending'
                          ORDER BY jr.requested_at ASC";
                $stmt = $db->prepare($query);
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                $result = $stmt->get_result();

                $requests = [];
                while ($row = $result->fetch_assoc()) {
                    $requests[] = $row;
                }

                echo json_encode(['success' => true, 'requests' => $requests]);
            } catch (Exception $e) {
                error_log("Get pending join requests error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'respond_join_request':
            try {
                $request_id = (int)($_POST['request_id'] ?? 0);
                $decision = $_POST['decision'] ?? '';
                $requester_id = (int)($_SESSION['emp_id'] ?? 0);

                if (!$request_id || !in_array($decision, ['approve', 'deny'], true)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid request']);
                    break;
                }

                $db = $projectManager->getConnection();

                $req_stmt = $db->prepare("SELECT * FROM project_join_requests WHERE request_id = ? AND status = 'pending'");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $join_request = $req_stmt->get_result()->fetch_assoc();

                if (!$join_request) {
                    echo json_encode(['success' => false, 'error' => 'Request not found or already handled']);
                    break;
                }

                $project_id = (int)$join_request['project_id'];
                $target_emp_id = (int)$join_request['emp_id'];

                $role_check = $db->prepare("SELECT role FROM project_members WHERE project_id = ? AND emp_id = ?");
                $role_check->bind_param("ii", $project_id, $requester_id);
                $role_check->execute();
                $role_row = $role_check->get_result()->fetch_assoc();
                $requester_role = $role_row['role'] ?? null;

                $canManageMembers = in_array($requester_role, ['owner', 'manager'], true)
                    || $projectManager->canAssignTasks($requester_id);

                if (!$canManageMembers) {
                    echo json_encode(['success' => false, 'error' => 'Only project owners or managers can respond to join requests']);
                    break;
                }

                $new_status = $decision === 'approve' ? 'approved' : 'denied';
                $update_stmt = $db->prepare("UPDATE project_join_requests SET status = ?, responded_at = NOW(), responded_by = ? WHERE request_id = ?");
                $update_stmt->bind_param("sii", $new_status, $requester_id, $request_id);
                $update_stmt->execute();

                if ($decision === 'approve') {
                    $already_member = isProjectMember($db, $project_id, $target_emp_id);
                    if (!$already_member) {
                        $projectManager->addProjectMember($project_id, $target_emp_id, 'member', $requester_id);
                    }
                    $project_row = $db->query("SELECT project_name FROM projects WHERE project_id = " . $project_id)->fetch_assoc();
                    $project_name = $project_row['project_name'] ?? '';
                    logActivity($db, 'project', $project_id, $project_id, $requester_id, 'join_request_approved', "Approved a join request for \"$project_name\"", ['emp_id' => $target_emp_id]);
                } else {
                    $project_row = $db->query("SELECT project_name FROM projects WHERE project_id = " . $project_id)->fetch_assoc();
                    $project_name = $project_row['project_name'] ?? '';
                    logActivity($db, 'project', $project_id, $project_id, $requester_id, 'join_request_denied', "Denied a join request for \"$project_name\"", ['emp_id' => $target_emp_id]);
                }

                // Return refreshed data so the UI can update immediately
                $members_result = $projectManager->getProjectMembers($project_id);
                $members = [];
                while ($row = $members_result->fetch_assoc()) {
                    $members[] = $row;
                }

                $pending_stmt = $db->prepare("SELECT jr.request_id, jr.project_id, jr.emp_id, jr.requested_at,
                                                      e.first_name, e.last_name, e.picture
                                               FROM project_join_requests jr
                                               JOIN employee e ON jr.emp_id = e.emp_id
                                               WHERE jr.project_id = ? AND jr.status = 'pending'
                                               ORDER BY jr.requested_at ASC");
                $pending_stmt->bind_param("i", $project_id);
                $pending_stmt->execute();
                $pending_result = $pending_stmt->get_result();
                $requests = [];
                while ($row = $pending_result->fetch_assoc()) {
                    $requests[] = $row;
                }

                echo json_encode([
                    'success' => true,
                    'decision' => $decision,
                    'members' => $members,
                    'requests' => $requests
                ]);
            } catch (Exception $e) {
                error_log("Respond join request error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            break;


        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>