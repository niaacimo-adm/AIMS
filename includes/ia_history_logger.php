<?php
require_once '../config/database.php';

class IAHistoryLogger {
    private $db;
    private $connection;

    public function __construct() {
        $database = new Database();
        $this->connection = $database->getConnection();
        $this->db = $this->connection;
    }

    /**
     * Log IA Profile activity
     */
    public function logActivity($ia_profile_id, $action, $description, $field_name = null, $old_value = null, $new_value = null) {
        try {
            // Get user information
            $performed_by = $_SESSION['emp_id'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            // Prepare the query
            $query = "INSERT INTO ia_profile_history 
                     (ia_profile_id, action, field_name, old_value, new_value, description, performed_by, ip_address, user_agent) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->db->error);
            }

            $stmt->bind_param(
                'isssssiss',
                $ia_profile_id,
                $action,
                $field_name,
                $old_value,
                $new_value,
                $description,
                $performed_by,
                $ip_address,
                $user_agent
            );

            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            return true;

        } catch (Exception $e) {
            error_log("IA History Logger Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log IA Profile creation
     */
    public function logCreation($ia_profile_id, $ia_name) {
        $description = "IA Profile '{$ia_name}' was created";
        return $this->logActivity($ia_profile_id, 'created', $description);
    }

    /**
     * Log IA Profile update with field changes
     */
    public function logUpdate($ia_profile_id, $ia_name, $changes) {
        if (empty($changes)) {
            return true;
        }

        $description = "IA Profile '{$ia_name}' was updated";
        
        // Log overall update
        $this->logActivity($ia_profile_id, 'updated', $description);

        // Log individual field changes
        foreach ($changes as $field => $change) {
            $this->logActivity(
                $ia_profile_id, 
                'updated', 
                "Field '{$field}' updated in '{$ia_name}'",
                $field,
                $change['old'],
                $change['new']
            );
        }

        return true;
    }

    /**
     * Log IA Profile deletion
     */
    public function logDeletion($ia_profile_id, $ia_name) {
        $description = "IA Profile '{$ia_name}' was deleted";
        return $this->logActivity($ia_profile_id, 'deleted', $description);
    }

    /**
     * Log employee assignment
     */
    public function logAssignment($ia_profile_id, $ia_name, $employee_name, $action_type) {
        $description = "Employee '{$employee_name}' was {$action_type} to IA Profile '{$ia_name}'";
        return $this->logActivity($ia_profile_id, 'assigned', $description);
    }

    /**
     * Log IA Officer activity
     */
    public function logOfficerActivity($ia_profile_id, $ia_name, $officer_name, $action, $position = null) {
        $descriptions = [
            'officer_added' => "Officer '{$officer_name}' ({$position}) was added to '{$ia_name}'",
            'officer_updated' => "Officer '{$officer_name}' was updated in '{$ia_name}'",
            'officer_deleted' => "Officer '{$officer_name}' was removed from '{$ia_name}'"
        ];

        $description = $descriptions[$action] ?? "Officer activity in '{$ia_name}'";
        return $this->logActivity($ia_profile_id, $action, $description);
    }

    /**
     * Get history for a specific IA Profile
     */
    public function getHistory($ia_profile_id, $limit = 50) {
        try {
            $query = "SELECT h.*, 
                             e.first_name, e.last_name, e.middle_name, e.ext_name,
                             CONCAT(e.first_name, ' ', e.last_name) as performer_name
                      FROM ia_profile_history h
                      LEFT JOIN employee e ON h.performed_by = e.emp_id
                      WHERE h.ia_profile_id = ?
                      ORDER BY h.performed_at DESC
                      LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->db->error);
            }

            $stmt->bind_param('ii', $ia_profile_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }

            $stmt->close();
            return $history;

        } catch (Exception $e) {
            error_log("Get History Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Compare two arrays and find changes
     */
    public function findChanges($old_data, $new_data) {
        $changes = [];

        foreach ($new_data as $key => $new_value) {
            $old_value = $old_data[$key] ?? null;
            
            // Handle different data types and formats
            if ($this->valuesDiffer($old_value, $new_value)) {
                $changes[$key] = [
                    'old' => $this->formatValue($old_value),
                    'new' => $this->formatValue($new_value)
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if values are different
     */
    private function valuesDiffer($old_value, $new_value) {
        // Handle null values
        if ($old_value === null && $new_value === null) return false;
        if ($old_value === null && $new_value !== null) return true;
        if ($old_value !== null && $new_value === null) return true;
        
        // Handle empty strings
        if ($old_value === '' && $new_value === '') return false;
        
        // Compare values
        return $old_value != $new_value;
    }

    /**
     * Format value for display
     */
    private function formatValue($value) {
        if ($value === null) return 'NULL';
        if ($value === '') return '(Empty)';
        
        // Handle date fields
        if (strtotime($value) !== false && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return date('M j, Y', strtotime($value));
        }
        
        return $value;
    }
}
?>