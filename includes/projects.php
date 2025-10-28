<?php
require_once '../config/database.php';

class ProjectManager {
    private $db;
    
    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            error_log("ProjectManager: Database connection established");
        } catch (Exception $e) {
            error_log("ProjectManager: Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    // Check if user can assign tasks
    public function canAssignTasks($emp_id) {
        $query = "SELECT e.is_manager, s.head_emp_id, e.emp_id, ur.name as role_name 
                FROM employee e 
                LEFT JOIN section s ON e.section_id = s.section_id 
                LEFT JOIN users u ON e.emp_id = u.employee_id 
                LEFT JOIN user_roles ur ON u.role_id = ur.id 
                WHERE e.emp_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Administrators, managers, and section heads can assign tasks
            return ($user['role_name'] === 'Administrator' || 
                $user['is_manager'] == 1 || 
                $user['emp_id'] == $user['head_emp_id']);
        }
        return false;
    }
    
    // Create new project
    public function createProject($data) {
        // Check if project code already exists
        $check_query = "SELECT project_id FROM projects WHERE project_code = ?";
        $check_stmt = $this->db->prepare($check_query);
        $check_stmt->bind_param("s", $data['project_code']);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'error' => 'Project code already exists'];
        }
        
        $query = "INSERT INTO projects (project_name, project_description, project_code, created_by, start_date, end_date, color) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sssisss", 
            $data['project_name'],
            $data['project_description'],
            $data['project_code'],
            $data['created_by'],
            $data['start_date'],
            $data['end_date'],
            $data['color']
        );
        
        if ($stmt->execute()) {
            $project_id = $this->db->insert_id;
            // Add creator as project owner
            $this->addProjectMember($project_id, $data['created_by'], 'owner', $data['created_by']);
            return ['success' => true, 'project_id' => $project_id];
        }
        return ['success' => false, 'error' => 'Failed to create project: ' . $this->db->error];
    }
    
    // Add project member
    public function addProjectMember($project_id, $emp_id, $role, $added_by) {
        $query = "INSERT INTO project_members (project_id, emp_id, role, added_by) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iisi", $project_id, $emp_id, $role, $added_by);
        return $stmt->execute();
    }
    
    // Get all projects for user
    public function getUserProjects($emp_id) {
        error_log("ProjectManager: Getting projects for user ID: " . $emp_id);
        
        if (!$this->db) {
            error_log("ProjectManager: Database connection is null");
            return false;
        }
        
        $query = "SELECT p.*, pm.role 
                FROM projects p 
                JOIN project_members pm ON p.project_id = pm.project_id 
                WHERE pm.emp_id = ? AND p.status = 'active'
                ORDER BY p.created_at DESC";
        
        error_log("ProjectManager: Executing query: " . $query);
        
        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            error_log("ProjectManager: Prepare failed: " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("i", $emp_id);
        
        if (!$stmt->execute()) {
            error_log("ProjectManager: Execute failed: " . $stmt->error);
            return false;
        }
        
        $result = $stmt->get_result();
        
        $projects = [];
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        
        error_log("ProjectManager: Found " . count($projects) . " projects for user " . $emp_id);
        foreach ($projects as $project) {
            error_log("ProjectManager: Project: " . $project['project_name'] . " (ID: " . $project['project_id'] . ")");
        }
        
        return $result;
    }
    
    // Get project details
    public function getProject($project_id, $emp_id) {
        $query = "SELECT p.*, pm.role 
                 FROM projects p 
                 JOIN project_members pm ON p.project_id = pm.project_id 
                 WHERE p.project_id = ? AND pm.emp_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $project_id, $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    // Get project members
    public function getProjectMembers($project_id) {
        $query = "SELECT e.emp_id, e.first_name, e.last_name, e.picture, pm.role 
                 FROM project_members pm 
                 JOIN employee e ON pm.emp_id = e.emp_id 
                 WHERE pm.project_id = ? 
                 ORDER BY pm.role DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    // Get available employees for assignment
    public function getAssignableEmployees() {
        $query = "SELECT e.emp_id, e.first_name, e.last_name, e.picture, e.is_manager,
                        s.section_name, ur.name as role_name
                FROM employee e 
                LEFT JOIN section s ON e.section_id = s.section_id 
                LEFT JOIN users u ON e.emp_id = u.employee_id 
                LEFT JOIN user_roles ur ON u.role_id = ur.id 
                WHERE e.employment_status_id = 1
                ORDER BY e.first_name, e.last_name";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    // Get all projects for monitoring
    public function getAllProjects() {
        $query = "SELECT p.*, 
                 COUNT(DISTINCT t.task_id) as task_count,
                 COUNT(DISTINCT pm.emp_id) as member_count,
                 creator.first_name as creator_first, creator.last_name as creator_last
                 FROM projects p
                 LEFT JOIN tasks t ON p.project_id = t.project_id
                 LEFT JOIN project_members pm ON p.project_id = pm.project_id
                 LEFT JOIN employee creator ON p.created_by = creator.emp_id
                 GROUP BY p.project_id
                 ORDER BY p.created_at DESC";
        
        $result = $this->db->query($query);
        return $result;
    }
    
    // Update project status
    public function updateProjectStatus($project_id, $status) {
        $query = "UPDATE projects SET status = ? WHERE project_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("si", $status, $project_id);
        return $stmt->execute();
    }
}
?>