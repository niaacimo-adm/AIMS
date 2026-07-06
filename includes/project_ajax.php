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
                error_log("No projects found via getUserProjects, trying direct query...");
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
                        $project_query = "SELECT * FROM projects WHERE project_id IN ($placeholders) ORDER BY created_at DESC";
                        $project_stmt = $db->prepare($project_query);
                        $types = str_repeat('i', count($project_ids));
                        $project_stmt->bind_param($types, ...$project_ids);
                        $project_stmt->execute();
                        $project_result = $project_stmt->get_result();
                        
                        while ($row = $project_result->fetch_assoc()) {
                            $projects[] = $row;
                        }
                        error_log("Direct query found " . count($projects) . " projects");
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

                // Clean up dependent records first (in case FK constraints aren't set to CASCADE)
                $db->query("DELETE ta FROM task_assignees ta INNER JOIN tasks t ON ta.task_id = t.task_id WHERE t.project_id = $project_id");
                $db->query("DELETE FROM tasks WHERE project_id = $project_id");
                $db->query("DELETE FROM project_boards WHERE project_id = $project_id");
                $db->query("DELETE FROM project_members WHERE project_id = $project_id");

                $stmt = $db->prepare("DELETE FROM projects WHERE project_id = ?");
                $stmt->bind_param("i", $project_id);

                if ($stmt->execute()) {
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
            $project_id = $_POST['project_id'];
            $status = $_POST['status'];
            if ($projectManager->updateProjectStatus($project_id, $status)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update project status']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>