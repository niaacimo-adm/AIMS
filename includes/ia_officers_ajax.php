<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Log the action for debugging
error_log("IA Officers AJAX Action: " . $action);

switch ($action) {
    case 'add':
        addIaOfficer($db); // Make sure this calls the correct function
        break;
    case 'view':
        viewIaProfile($db);
        break;
    case 'delete':
        deleteIaOfficer($db); // Make sure this calls the correct function
        break;
    case 'get_provinces':
        getProvinces($db);
        break;
    case 'get_districts':
        getDistricts($db);
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
}
function getProvinces($db) {
    header('Content-Type: application/json');
    
    $region_code = $_POST['region_code'] ?? '';
    
    if (empty($region_code)) {
        echo json_encode(['success' => false, 'message' => 'Region code is required']);
        return;
    }
    
    try {
        $query = "SELECT province_code, province_name FROM provinces WHERE region_code = ? ORDER BY province_name";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $stmt->bind_param('s', $region_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $provinces = [];
        while ($row = $result->fetch_assoc()) {
            $provinces[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $provinces]);
        
    } catch (Exception $e) {
        error_log("Error getting provinces: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getDistricts($db) {
    header('Content-Type: application/json');
    
    $province_code = $_POST['province_code'] ?? '';
    
    if (empty($province_code)) {
        echo json_encode(['success' => false, 'message' => 'Province code is required']);
        return;
    }
    
    try {
        $query = "SELECT district_code, district_name FROM congressional_districts WHERE province_code = ? ORDER BY district_name";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $stmt->bind_param('s', $province_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $districts = [];
        while ($row = $result->fetch_assoc()) {
            $districts[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $districts]);
        
    } catch (Exception $e) {
        error_log("Error getting districts: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
function addIaOfficer($db) {
    header('Content-Type: application/json');
    
    error_log("addIaOfficer function called");
    
    // Check if user has permission
    if (!hasPermission('add_ia_officer')) {
        error_log("Permission denied for add_ia_officer");
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }

    try {
        // Log all POST data for debugging
        error_log("POST data: " . print_r($_POST, true));
        
        $data = [
            'ia_profile_id' => $_POST['ia_profile_id'] ?? 0,
            'officer_name' => $_POST['officer_name'] ?? '',
            'position' => $_POST['position'] ?? '',
            'contact_number' => $_POST['contact_number'] ?? '',
            'email' => $_POST['email'] ?? '',
            'is_active' => $_POST['is_active'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Validate required fields
        if (empty($data['officer_name']) || empty($data['position'])) {
            error_log("Validation failed: officer_name or position is empty");
            echo json_encode(['success' => false, 'message' => 'Officer name and position are required']);
            return;
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO ia_officers ($columns) VALUES ($placeholders)";
        error_log("SQL Query: " . $query);
        error_log("Data to insert: " . print_r($data, true));
        
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }

        $types = '';
        $values = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i'; // integer
            } else {
                $types .= 's'; // string
            }
            $values[] = $value;
        }

        error_log("Bind types: " . $types);
        error_log("Values: " . print_r($values, true));
        
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            // Log the officer addition
            $logger = new IAHistoryLogger();
            
            // Get IA Profile name
            $profile_query = "SELECT ia_name FROM ia_profiles WHERE id = ?";
            $profile_stmt = $db->prepare($profile_query);
            $profile_stmt->bind_param('i', $data['ia_profile_id']);
            $profile_stmt->execute();
            $profile_result = $profile_stmt->get_result();
            $profile_data = $profile_result->fetch_assoc();
            
            if ($profile_data) {
                $logger->logOfficerActivity(
                    $data['ia_profile_id'], 
                    $profile_data['ia_name'], 
                    $data['officer_name'], 
                    'officer_added',
                    $data['position']
                );
            }
            
            error_log("Officer added successfully");
            echo json_encode(['success' => true, 'message' => 'Officer added successfully']);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Error adding IA Officer: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Update the deleteIaOfficer function
function deleteIaOfficer($db) {
    header('Content-Type: application/json');
    
    if (!hasPermission('delete_ia_officer')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }

    $id = $_POST['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid officer ID']);
        return;
    }

    try {
        // Get officer and profile info before deletion
        $info_query = "SELECT io.officer_name, io.position, io.ia_profile_id, ip.ia_name 
                      FROM ia_officers io 
                      JOIN ia_profiles ip ON io.ia_profile_id = ip.id 
                      WHERE io.id = ?";
        $info_stmt = $db->prepare($info_query);
        $info_stmt->bind_param('i', $id);
        $info_stmt->execute();
        $info_result = $info_stmt->get_result();
        $officer_data = $info_result->fetch_assoc();
        
        if (!$officer_data) {
            echo json_encode(['success' => false, 'message' => 'Officer not found']);
            return;
        }

        $query = "DELETE FROM ia_officers WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            // Log the deletion
            $logger = new IAHistoryLogger();
            $logger->logOfficerActivity(
                $officer_data['ia_profile_id'], 
                $officer_data['ia_name'], 
                $officer_data['officer_name'], 
                'officer_deleted'
            );
            
            echo json_encode(['success' => true, 'message' => 'Officer deleted successfully']);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Error deleting IA Officer: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>