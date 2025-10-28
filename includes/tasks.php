<?php
require_once '../config/database.php';

class TaskManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Create new task
    public function createTask($data) {
        // Get max position for the status column
        $max_position = $this->getMaxPosition($data['project_id'], $data['status']);
        
        $query = "INSERT INTO tasks (project_id, title, description, status, priority, labels, due_date, assigned_to, created_by, position) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $labels = isset($data['labels']) ? implode(',', $data['labels']) : null;
        $position = $max_position + 1;
        
        $stmt->bind_param("issssssiii", 
            $data['project_id'],
            $data['title'],
            $data['description'],
            $data['status'],
            $data['priority'],
            $labels,
            $data['due_date'],
            $data['assigned_to'],
            $data['created_by'],
            $position
        );
        
        if ($stmt->execute()) {
            return ['success' => true, 'task_id' => $this->db->insert_id];
        }
        return ['success' => false, 'error' => 'Failed to create task'];
    }
    
    // Get tasks for project
    public function getProjectTasks($project_id) {
        $query = "SELECT t.*, 
                 e.first_name, e.last_name, e.picture,
                 creator.first_name as creator_first, creator.last_name as creator_last
                 FROM tasks t 
                 LEFT JOIN employee e ON t.assigned_to = e.emp_id 
                 LEFT JOIN employee creator ON t.created_by = creator.emp_id 
                 WHERE t.project_id = ? 
                 ORDER BY t.status, t.position ASC, t.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    // Update task status (drag & drop)
    public function updateTaskStatus($task_id, $status, $position = 0) {
        $query = "UPDATE tasks SET status = ?, position = ? WHERE task_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sii", $status, $position, $task_id);
        return $stmt->execute();
    }
    
    // Get user's tasks
    public function getUserTasks($emp_id, $project_id = null) {
        $query = "SELECT t.*, p.project_name, p.project_code,
                 e.first_name, e.last_name, e.picture,
                 creator.first_name as creator_first, creator.last_name as creator_last
                 FROM tasks t 
                 JOIN projects p ON t.project_id = p.project_id 
                 LEFT JOIN employee e ON t.assigned_to = e.emp_id 
                 LEFT JOIN employee creator ON t.created_by = creator.emp_id 
                 WHERE t.assigned_to = ?";
        
        if ($project_id) {
            $query .= " AND t.project_id = ?";
        }
        
        $query .= " ORDER BY t.due_date ASC, t.priority DESC";
        
        $stmt = $this->db->prepare($query);
        if ($project_id) {
            $stmt->bind_param("ii", $emp_id, $project_id);
        } else {
            $stmt->bind_param("i", $emp_id);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
    
    // Get all tasks (for monitoring)
    public function getAllTasks($filters = []) {
        $query = "SELECT t.*, p.project_name, p.project_code,
                 e.first_name, e.last_name, e.picture,
                 creator.first_name as creator_first, creator.last_name as creator_last
                 FROM tasks t 
                 JOIN projects p ON t.project_id = p.project_id 
                 LEFT JOIN employee e ON t.assigned_to = e.emp_id 
                 LEFT JOIN employee creator ON t.created_by = creator.emp_id 
                 WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if (isset($filters['project_id']) && $filters['project_id']) {
            $query .= " AND t.project_id = ?";
            $params[] = $filters['project_id'];
            $types .= "i";
        }
        
        if (isset($filters['status']) && $filters['status']) {
            $query .= " AND t.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        
        if (isset($filters['assigned_to']) && $filters['assigned_to']) {
            $query .= " AND t.assigned_to = ?";
            $params[] = $filters['assigned_to'];
            $types .= "i";
        }
        
        $query .= " ORDER BY t.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
    
    // Get max position for status column
    private function getMaxPosition($project_id, $status) {
        $query = "SELECT COALESCE(MAX(position), 0) as max_position 
                 FROM tasks 
                 WHERE project_id = ? AND status = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("is", $project_id, $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['max_position'];
    }
}
?>