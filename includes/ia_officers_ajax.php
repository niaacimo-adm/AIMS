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
        $query = "DELETE FROM ia_officers WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
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