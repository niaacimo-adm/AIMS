<?php
require_once '../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
class DocumentFunctions {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    public function generateTrackingNumberFromSection($from_section_id, $document_type) {
        // Get section code from FROM section
        $section_query = "SELECT section_code FROM section WHERE section_id = ?";
        $stmt = $this->conn->prepare($section_query);
        $stmt->bind_param("i", $from_section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // If no section found, check if user is IMO Office staff
            if ($this->isManagerStaff($_SESSION['emp_id'])) {
                $section_code = 'IMO';
            } else {
                $section_code = 'GEN'; // General fallback
            }
        } else {
            $section = $result->fetch_assoc();
            $section_code = $section['section_code'];
        }
        
        // Get current date parts
        $current_date = date('m-d-y');
        
        // Determine document type prefix
        $type_prefix = '';
        switch($document_type) {
            case 'incoming': $type_prefix = 'IN'; break;
            case 'outgoing': $type_prefix = 'OUT'; break;
            case 'internal': $type_prefix = 'INT'; break;
            default: $type_prefix = 'DOC'; break;
        }
        
        // Get the last sequence number for this section, type and date
        $sequence_query = "SELECT tracking_no FROM document_monitoring 
                        WHERE tracking_no LIKE ? 
                        ORDER BY document_id DESC LIMIT 1";
        $search_pattern = $type_prefix . '-' . $section_code . ' ' . $current_date . '%';
        $stmt = $this->conn->prepare($sequence_query);
        $stmt->bind_param("s", $search_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $last_tracking = $result->fetch_assoc()['tracking_no'];
            $last_sequence = intval(substr($last_tracking, -3));
            $sequence = str_pad($last_sequence + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $sequence = '001';
        }
        
        return $type_prefix . '-' . $section_code . ' ' . $current_date . ' ' . $sequence;
    }
    public function getEmployeesBySection($section_id) {
        $query = "SELECT emp_id, first_name, last_name, middle_name 
                FROM employee 
                WHERE section_id = ? 
                ORDER BY first_name, last_name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        
        return $employees;
    }
    // Get current user's section
    public function getCurrentUserSection($emp_id) {
        // First check if user is IMO Office staff
        if ($this->isManagerStaff($emp_id)) {
            // Return IMO Office section details
            $query = "SELECT section_id, section_name, section_code 
                    FROM section 
                    WHERE section_code = 'IMO' 
                    LIMIT 1";
            $result = $this->conn->query($query);
            
            if ($result->num_rows > 0) {
                return $result->fetch_assoc();
            } else {
                // Create a fallback IMO section object
                return [
                    'section_id' => 5, // Assuming IMO Office has ID 5
                    'section_name' => 'IMO Office',
                    'section_code' => 'IMO'
                ];
            }
        }
        
        // Regular employee section lookup
        $query = "SELECT s.section_id, s.section_name, s.section_code 
                FROM employee e 
                JOIN section s ON e.section_id = s.section_id 
                WHERE e.emp_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
}

    // Check if user can access document monitoring (role-based)
    public function canAccessDocumentMonitoring($user_id) {
        $query = "SELECT ur.name 
                FROM users u 
                JOIN user_roles ur ON ur.id = u.role_id 
                WHERE u.emp_id = ? AND ur.name = 'Focal Person (Document Monitoring)'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }

    // Get section head initials for TO section
    public function getToSectionHeadInitials($to_section_id) {
        $query = "SELECT e.first_name, e.last_name, e.is_manager
                FROM section s 
                LEFT JOIN employee e ON s.head_emp_id = e.emp_id 
                WHERE s.section_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $to_section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $head = $result->fetch_assoc();
            if ($head['first_name'] && $head['last_name']) {
                $initials = substr($head['first_name'], 0, 1) . substr($head['last_name'], 0, 1);
                return strtoupper($initials);
            }
        }
        
        // If no section head found, check if it's IMO Office and get manager
        if ($to_section_id == 5) { // IMO Office section ID
            return $this->getManagerInitials();
        }
        
        return 'IB'; // Default initials
    }
    public function getManagerInitials() {
        $query = "SELECT first_name, last_name FROM employee WHERE is_manager = 1 LIMIT 1";
        $result = $this->conn->query($query);
        
        if ($result->num_rows > 0) {
            $manager = $result->fetch_assoc();
            $initials = substr($manager['first_name'], 0, 1) . substr($manager['last_name'], 0, 1);
            return strtoupper($initials);
        }
        
        return 'IB'; // Default initials
    }
    // Generate tracking number
    public function generateTrackingNumber($section_id) {
        // Get section code
        $section_query = "SELECT section_code FROM section WHERE section_id = ?";
        $stmt = $this->conn->prepare($section_query);
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $section = $result->fetch_assoc();
        $section_code = $section['section_code'];
        
        // Get current date parts
        $current_date = date('m-d-y');
        
        // Get the last sequence number for this section and date
        $sequence_query = "SELECT tracking_no FROM document_monitoring 
                          WHERE tracking_no LIKE ? 
                          ORDER BY document_id DESC LIMIT 1";
        $search_pattern = $section_code . ' ' . $current_date . '%';
        $stmt = $this->conn->prepare($sequence_query);
        $stmt->bind_param("s", $search_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $last_tracking = $result->fetch_assoc()['tracking_no'];
            $last_sequence = intval(substr($last_tracking, -3));
            $sequence = str_pad($last_sequence + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $sequence = '001';
        }
        
        return $section_code . ' ' . $current_date . ' ' . $sequence;
    }
    
    public function getSections() {
        $query = "SELECT * FROM section WHERE office_id = 1 ORDER BY section_id";
        $result = $this->conn->query($query);
        $sections = [];
        
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }
        
        return $sections;
    }
    
    // Check if user is a Manager Staff
    public function isManagerStaff($emp_id) {
        $query = "SELECT COUNT(*) as count FROM managers_office_staff WHERE emp_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] > 0;
    }

    // Get appropriate "From" options based on user type
    public function getFromOptions($emp_id) {
        if ($this->isManagerStaff($emp_id)) {
            // For Manager Staff, show IMO Office staff
            return [
                'type' => 'imo_staff',
                'options' => $this->getIMOOfficeStaff()
            ];
        } else {
            // For regular employees, show sections
            return [
                'type' => 'sections', 
                'options' => $this->getSections()
            ];
        }
    }

    // Get IMO Office staff (already exists, but ensure it's correct)
    public function getIMOOfficeStaff() {
        $query = "SELECT e.emp_id, e.first_name, e.last_name, e.middle_name, mos.position
                FROM managers_office_staff mos
                JOIN employee e ON mos.emp_id = e.emp_id
                ORDER BY e.first_name, e.last_name";
        
        $result = $this->conn->query($query);
        $staff = [];
        
        while ($row = $result->fetch_assoc()) {
            $staff[] = $row;
        }
        
        return $staff;
    }
    // Get section head initials
    public function getSectionHeadInitials($section_id) {
        $query = "SELECT e.first_name, e.last_name 
                 FROM section s 
                 LEFT JOIN employee e ON s.head_emp_id = e.emp_id 
                 WHERE s.section_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $head = $result->fetch_assoc();
            $initials = substr($head['first_name'], 0, 1) . substr($head['last_name'], 0, 1);
            return strtoupper($initials);
        }
        
        return 'N/A';
    }

    public function getDocumentHistory($emp_id = null) {
        $query = "SELECT d.*, s.section_name as to_section_name, 
                        creator.first_name, creator.last_name
                FROM document_monitoring d
                LEFT JOIN section s ON d.to_section_id = s.section_id
                LEFT JOIN employee creator ON d.created_by = creator.emp_id
                ORDER BY d.created_at DESC
                LIMIT 50";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
        
        return $documents;
    }

    // Get documents received by specific user
    public function getDocumentsReceivedByUser($emp_id) {
        $query = "SELECT d.*, s.section_name, s.section_code, 
                        creator.first_name as creator_first, creator.last_name as creator_last,
                        creator_section.section_name as from_section_name
                FROM document_monitoring d
                LEFT JOIN section s ON d.to_section_id = s.section_id
                LEFT JOIN employee creator ON d.created_by = creator.emp_id
                LEFT JOIN section creator_section ON creator.section_id = creator_section.section_id
                WHERE d.received_by = ?
                ORDER BY d.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
        
        return $documents;
    }

    public function getDocumentsForUserSection($section_id) {
        $query = "SELECT d.*, 
                        ds.section_name as to_section_name,
                        creator.first_name as creator_first, 
                        creator.last_name as creator_last,
                        receiver.first_name as receiver_first, 
                        receiver.last_name as receiver_last
                FROM document_monitoring d
                LEFT JOIN section ds ON d.to_section_id = ds.section_id
                LEFT JOIN employee creator ON d.created_by = creator.emp_id
                LEFT JOIN employee receiver ON d.received_by = receiver.emp_id
                WHERE d.to_section_id = ?
                ORDER BY d.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
        
        return $documents;
    }

    // Update document status
    public function updateDocumentStatus($document_id, $status, $action_by) {
        $query = "UPDATE document_monitoring 
                SET status = ?, updated_by = ?, updated_at = NOW() 
                WHERE document_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sii", $status, $action_by, $document_id);
        
        return $stmt->execute();
    }

    // Add document action/note
    public function addDocumentAction($document_id, $action, $remarks, $action_by) {
        $query = "INSERT INTO document_actions (document_id, action, remarks, action_by, action_date) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("issi", $document_id, $action, $remarks, $action_by);
        
        return $stmt->execute();
    }

    // Get document actions/history
    public function getDocumentActions($document_id) {
        $query = "SELECT da.*, e.first_name, e.last_name 
                FROM document_actions da
                LEFT JOIN employee e ON da.action_by = e.emp_id
                WHERE da.document_id = ?
                ORDER BY da.action_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $actions = [];
        while ($row = $result->fetch_assoc()) {
            $actions[] = $row;
        }
        
        return $actions;
    }

    // Get document by ID
    public function getDocumentById($document_id) {
        $query = "SELECT d.*, s.section_name, s.section_code, 
                        creator.first_name as creator_first, creator.last_name as creator_last,
                        receiver.first_name as receiver_first, receiver.last_name as receiver_last,
                        creator_section.section_name as from_section_name
                FROM document_monitoring d
                LEFT JOIN section s ON d.to_section_id = s.section_id
                LEFT JOIN employee creator ON d.created_by = creator.emp_id
                LEFT JOIN employee receiver ON d.received_by = receiver.emp_id
                LEFT JOIN section creator_section ON creator.section_id = creator_section.section_id
                WHERE d.document_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    // Update document receiver when forwarding
    public function updateDocumentReceiver($document_id, $receiver_emp_id) {
        $query = "UPDATE document_monitoring 
                SET received_by = ?, updated_by = ?, updated_at = NOW() 
                WHERE document_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $receiver_emp_id, $_SESSION['emp_id'], $document_id);
        
        return $stmt->execute();
    }

    // Get employees by section for forwarding dropdown
    public function getEmployeesBySectionForDropdown($section_id) {
        $query = "SELECT emp_id, first_name, last_name, middle_name 
                FROM employee 
                WHERE section_id = ? 
                ORDER BY first_name, last_name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $options = '<option value="">Select Employee</option>';
        while ($row = $result->fetch_assoc()) {
            $fullName = $row['first_name'] . ' ' . $row['last_name'];
            if (!empty($row['middle_name'])) {
                $fullName .= ' ' . substr($row['middle_name'], 0, 1) . '.';
            }
            $options .= '<option value="' . $row['emp_id'] . '">' . $fullName . '</option>';
        }
        
        return $options;
    }

    public function getDocumentsCreatedByUser($user_id) {
        try {
            $query = "SELECT d.*, 
                            ds.section_name as to_section_name,
                            creator.first_name as creator_first, 
                            creator.last_name as creator_last,
                            receiver.first_name as receiver_first, 
                            receiver.last_name as receiver_last
                    FROM document_monitoring d
                    LEFT JOIN section ds ON d.to_section_id = ds.section_id
                    LEFT JOIN employee creator ON d.created_by = creator.emp_id
                    LEFT JOIN employee receiver ON d.received_by = receiver.emp_id
                    WHERE d.created_by = ?
                    ORDER BY d.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $documents = [];
            while ($row = $result->fetch_assoc()) {
                $documents[] = $row;
            }
            
            return $documents;
        } catch (Exception $e) {
            error_log("Error getting documents created by user: " . $e->getMessage());
            return [];
        }
    }
    public function updateDocumentSection($document_id, $new_section_id) {
        try {
            // First, verify the document exists
            $check_query = "SELECT document_id FROM document_monitoring WHERE document_id = ?";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bind_param("i", $document_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                error_log("Document ID $document_id not found");
                return false;
            }
            
            // Update the document section
            $update_query = "UPDATE document_monitoring SET to_section_id = ?, updated_at = NOW() WHERE document_id = ?";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bind_param("ii", $new_section_id, $document_id);
            
            $success = $update_stmt->execute();
            
            if (!$success) {
                error_log("Failed to update document section: " . $update_stmt->error);
            }
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Exception in updateDocumentSection: " . $e->getMessage());
            return false;
        }
    }
    // Create new document
    public function createDocument($data) {
        $query = "INSERT INTO document_monitoring (tracking_no, document_type, type_of_document, from_section, 
                 document_name, to_section_id, for_signature, received_by, remarks, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sssssisisi", 
            $data['tracking_no'],
            $data['document_type'],
            $data['type_of_document'],
            $data['from_section'],
            $data['document_name'],
            $data['to_section_id'],
            $data['for_signature'],
            $data['received_by'],
            $data['remarks'],
            $data['created_by']
        );
        
        return $stmt->execute();
    }
    
    // Get documents by type
    public function getDocumentsByType($document_type) {
        $query = "SELECT d.*, s.section_name, s.section_code, 
                 e.first_name, e.last_name, e.picture,
                 creator.first_name as creator_first, creator.last_name as creator_last
                 FROM document_monitoring d
                 LEFT JOIN section s ON d.to_section_id = s.section_id
                 LEFT JOIN employee e ON d.received_by = e.emp_id
                 LEFT JOIN employee creator ON d.created_by = creator.emp_id
                 WHERE d.document_type = ?
                 ORDER BY d.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $document_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
        
        return $documents;
    }
    
    // Get document counts for sidebar
    public function getDocumentCounts() {
        $query = "SELECT document_type, COUNT(*) as count 
                 FROM document_monitoring 
                 GROUP BY document_type";
        $result = $this->conn->query($query);
        
        $counts = [
            'incoming' => 0,
            'outgoing' => 0,
            'internal' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $counts[$row['document_type']] = $row['count'];
        }
        
        return $counts;
    }
    
    // Check if user has document monitoring role
    public function hasDocumentMonitoringRole($emp_id) {
        $query = "SELECT ur.name 
                 FROM user_roles ur 
                 JOIN users u ON ur.id = u.role_id 
                 WHERE u.emp_id = ? AND ur.name = 'Focal Person (Document Monitoring)'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    // Get user by ID
    public function getUserById($emp_id) {
        $query = "SELECT * FROM employee WHERE emp_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
}
?>