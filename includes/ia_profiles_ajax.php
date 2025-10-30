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
error_log("IA Profiles AJAX Action: " . $action);

switch ($action) {
    case 'add':
        addIaProfile($db);
        break;
    case 'view':
        viewIaProfile($db);
        break;
    case 'delete':
        deleteIaProfile($db);
        break;
    case 'get_ia_profiles': // ADD THIS CASE
        get_ia_profiles($db);
        break;
    case 'get_regions':
        getRegions($db);
        break;
    case 'get_provinces':
        getProvinces($db);
        break;
    case 'get_districts':
        getDistricts($db);
        break;
    case 'search_ia_profiles':
    searchIaProfiles($db);
        break;
    case 'get_idu_employees':
        getIduEmployees($db);
        break;
    case 'assign_employee':
        assignEmployeeToIa($db);
        break;
    case 'get_assigned_employee':
        getAssignedEmployee($db);
        break;
    default:
        // For non-JSON responses, don't set JSON header
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        } else {
            echo 'Invalid action: ' . $action;
        }
}

function getIduEmployees($db) {
    header('Content-Type: application/json');
    
    try {
        $query = "SELECT e.emp_id, e.first_name, e.middle_name, e.last_name, e.ext_name
                  FROM employee e 
                  INNER JOIN unit_section us ON e.unit_section_id = us.unit_id
                  WHERE us.unit_name = 'INSTITUTIONAL DEVELOPMENT UNIT'
                  AND e.section_id = 4
                  ORDER BY e.last_name, e.first_name";
        
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $fullName = $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'];
            if (!empty($row['ext_name'])) {
                $fullName .= ' ' . $row['ext_name'];
            }
            
            $employees[] = [
                'emp_id' => $row['emp_id'],
                'full_name' => trim($fullName)
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $employees]);
        
    } catch (Exception $e) {
        error_log("Error getting IDU employees: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function assignEmployeeToIa($db) {
    header('Content-Type: application/json');
    
    if (!hasPermission('manage_ia_profiles')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }

    $ia_profile_id = $_POST['ia_profile_id'] ?? 0;
    $emp_id = $_POST['emp_id'] ?? null;
    
    if (empty($ia_profile_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid IA Profile ID']);
        return;
    }

    try {
        // If emp_id is empty or 0, remove the assignment
        if (empty($emp_id)) {
            $query = "UPDATE ia_profiles SET assigned_employee_id = NULL WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param('i', $ia_profile_id);
        } else {
            $query = "UPDATE ia_profiles SET assigned_employee_id = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param('ii', $emp_id, $ia_profile_id);
        }
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee assigned successfully']);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Error assigning employee to IA: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getAssignedEmployee($db) {
    header('Content-Type: application/json');
    
    $ia_profile_id = $_POST['ia_profile_id'] ?? $_GET['ia_profile_id'] ?? 0;
    
    if (empty($ia_profile_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid IA Profile ID']);
        return;
    }

    try {
        $query = "SELECT ip.assigned_employee_id, e.first_name, e.middle_name, e.last_name, e.ext_name
                  FROM ia_profiles ip
                  LEFT JOIN employee e ON ip.assigned_employee_id = e.emp_id
                  WHERE ip.id = ?";
        
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $stmt->bind_param('i', $ia_profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        if ($data && $data['assigned_employee_id']) {
            $fullName = $data['first_name'] . ' ' . $data['middle_name'] . ' ' . $data['last_name'];
            if (!empty($data['ext_name'])) {
                $fullName .= ' ' . $data['ext_name'];
            }
            
            echo json_encode([
                'success' => true, 
                'assigned' => true,
                'emp_id' => $data['assigned_employee_id'],
                'employee_name' => trim($fullName)
            ]);
        } else {
            echo json_encode([
                'success' => true, 
                'assigned' => false,
                'emp_id' => null,
                'employee_name' => null
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error getting assigned employee: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Add this function to ia_profiles_ajax.php
function searchIaProfiles($db) {
    header('Content-Type: application/json');
    
    try {
        // Build the query based on search parameters
        $query = "SELECT * FROM ia_profiles WHERE 1=1";
        $params = [];
        $types = '';
        
        // IA Name
        if (!empty($_POST['ia_name'])) {
            $query .= " AND ia_name LIKE ?";
            $params[] = '%' . $_POST['ia_name'] . '%';
            $types .= 's';
        }
        
        // IA Code
        if (!empty($_POST['ia_code'])) {
            $query .= " AND ia_code LIKE ?";
            $params[] = '%' . $_POST['ia_code'] . '%';
            $types .= 's';
        }
        
        // President Name
        if (!empty($_POST['president_name'])) {
            $query .= " AND president_name LIKE ?";
            $params[] = '%' . $_POST['president_name'] . '%';
            $types .= 's';
        }
        
        // Status
        if (!empty($_POST['status'])) {
            $query .= " AND status = ?";
            $params[] = $_POST['status'];
            $types .= 's';
        }
        
        // Region
        if (!empty($_POST['region'])) {
            $query .= " AND region = ?";
            $params[] = $_POST['region'];
            $types .= 's';
        }
        
        // Province
        if (!empty($_POST['province'])) {
            $query .= " AND province = ?";
            $params[] = $_POST['province'];
            $types .= 's';
        }
        
        // Congressional District
        if (!empty($_POST['congressional_district'])) {
            $query .= " AND congressional_district LIKE ?";
            $params[] = '%' . $_POST['congressional_district'] . '%';
            $types .= 's';
        }
        
        // Service Area range
        if (!empty($_POST['service_area_min'])) {
            $query .= " AND service_area_ha >= ?";
            $params[] = floatval($_POST['service_area_min']);
            $types .= 'd';
        }
        
        if (!empty($_POST['service_area_max'])) {
            $query .= " AND service_area_ha <= ?";
            $params[] = floatval($_POST['service_area_max']);
            $types .= 'd';
        }
        
        // FUSA range
        if (!empty($_POST['fusa_min'])) {
            $query .= " AND fusa_ha >= ?";
            $params[] = floatval($_POST['fusa_min']);
            $types .= 'd';
        }
        
        if (!empty($_POST['fusa_max'])) {
            $query .= " AND fusa_ha <= ?";
            $params[] = floatval($_POST['fusa_max']);
            $types .= 'd';
        }
        
        // Canal Length range
        if (!empty($_POST['canal_length_min'])) {
            $query .= " AND canal_length_km >= ?";
            $params[] = floatval($_POST['canal_length_min']);
            $types .= 'd';
        }
        
        if (!empty($_POST['canal_length_max'])) {
            $query .= " AND canal_length_km <= ?";
            $params[] = floatval($_POST['canal_length_max']);
            $types .= 'd';
        }
        
        // Farmer Beneficiaries range
        if (!empty($_POST['farmer_beneficiaries_min'])) {
            $query .= " AND farmer_beneficiaries >= ?";
            $params[] = intval($_POST['farmer_beneficiaries_min']);
            $types .= 'i';
        }
        
        if (!empty($_POST['farmer_beneficiaries_max'])) {
            $query .= " AND farmer_beneficiaries <= ?";
            $params[] = intval($_POST['farmer_beneficiaries_max']);
            $types .= 'i';
        }
        
        // Actual IA Members range
        if (!empty($_POST['actual_ia_members_min'])) {
            $query .= " AND actual_ia_members >= ?";
            $params[] = intval($_POST['actual_ia_members_min']);
            $types .= 'i';
        }
        
        if (!empty($_POST['actual_ia_members_max'])) {
            $query .= " AND actual_ia_members <= ?";
            $params[] = intval($_POST['actual_ia_members_max']);
            $types .= 'i';
        }
        
        // TSAGs Count range
        if (!empty($_POST['tsags_count_min'])) {
            $query .= " AND tsags_count >= ?";
            $params[] = intval($_POST['tsags_count_min']);
            $types .= 'i';
        }
        
        if (!empty($_POST['tsags_count_max'])) {
            $query .= " AND tsags_count <= ?";
            $params[] = intval($_POST['tsags_count_max']);
            $types .= 'i';
        }
        
        // Male Members
        if (!empty($_POST['male_members_min'])) {
            $query .= " AND male_members >= ?";
            $params[] = intval($_POST['male_members_min']);
            $types .= 'i';
        }
        
        // Female Members
        if (!empty($_POST['female_members_min'])) {
            $query .= " AND female_members >= ?";
            $params[] = intval($_POST['female_members_min']);
            $types .= 'i';
        }
        
        // Date Organized range
        if (!empty($_POST['date_organized_from'])) {
            $query .= " AND date_organized >= ?";
            $params[] = $_POST['date_organized_from'];
            $types .= 's';
        }
        
        if (!empty($_POST['date_organized_to'])) {
            $query .= " AND date_organized <= ?";
            $params[] = $_POST['date_organized_to'];
            $types .= 's';
        }
        
        // SEC Registration Date
        if (!empty($_POST['sec_registration_date'])) {
            $query .= " AND sec_registration_date = ?";
            $params[] = $_POST['sec_registration_date'];
            $types .= 's';
        }
        
        $query .= " ORDER BY ia_name";
        
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        // Bind parameters if any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $profiles = [];
        while ($row = $result->fetch_assoc()) {
            $profiles[] = $row;
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $profiles,
            'count' => count($profiles)
        ]);
        
    } catch (Exception $e) {
        error_log("Error searching IA profiles: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => [],
            'count' => 0
        ]);
    }
}
function getRegions($db) {
    header('Content-Type: application/json');
    
    try {
        $query = "SELECT region_code, region_name FROM regions ORDER BY region_name";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $regions = [];
        while ($row = $result->fetch_assoc()) {
            $regions[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $regions]);
        
    } catch (Exception $e) {
        error_log("Error getting regions: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
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
function addIaProfile($db) {
    header('Content-Type: application/json');
    
    // Check if user has permission
    if (!hasPermission('add_ia_profile')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }

    try {
        $data = [
            'ia_name' => $_POST['ia_name'] ?? '',
            'ia_code' => $_POST['ia_code'] ?? '',
            'mailing_address' => $_POST['mailing_address'] ?? '',
            'president_name' => $_POST['president_name'] ?? '',
            'contact_number' => $_POST['contact_number'] ?? '',
            'date_organized' => $_POST['date_organized'] ?? null,
            'sec_registration_date' => $_POST['sec_registration_date'] ?? null,
            'sec_registration_number' => $_POST['sec_registration_number'] ?? '',
            'ia_tin' => $_POST['ia_tin'] ?? '',
            'service_area_ha' => $_POST['service_area_ha'] ?? 0,
            'fusa_ha' => $_POST['fusa_ha'] ?? 0,
            'farmer_beneficiaries' => $_POST['farmer_beneficiaries'] ?? 0,
            'actual_ia_members' => $_POST['actual_ia_members'] ?? 0,
            'tsags_count' => $_POST['tsags_count'] ?? 0,
            'existing_contract' => $_POST['existing_contract'] ?? '',
            'contract_effectivity_date' => $_POST['contract_effectivity_date'] ?? null,
            'canal_length_km' => $_POST['canal_length_km'] ?? 0,
            'male_members' => $_POST['male_members'] ?? 0,
            'female_members' => $_POST['female_members'] ?? 0,
            'congressional_district' => $_POST['district_text'] ?? '', // Use the displayed text
            'region' => $_POST['region_text'] ?? '', // Use the displayed text
            'province' => $_POST['province_text'] ?? '', // ADD THIS LINE - Capture province text
            'imo' => $_POST['imo'] ?? '',
            // FIX: Use the status from the form instead of hardcoding 'active'
            'status' => $_POST['status'] ?? 'operational', // Changed from 'active' to use form value
            'created_by' => $_SESSION['emp_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Convert empty strings to NULL for date fields
        if (empty($data['date_organized'])) $data['date_organized'] = null;
        if (empty($data['sec_registration_date'])) $data['sec_registration_date'] = null;
        if (empty($data['contract_effectivity_date'])) $data['contract_effectivity_date'] = null;

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO ia_profiles ($columns) VALUES ($placeholders)";
        error_log("SQL Query: " . $query);
        
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }

        $types = '';
        $values = [];
        foreach ($data as $value) {
            if (is_float($value)) {
                $types .= 'd'; // double
            } elseif (is_int($value)) {
                $types .= 'i'; // integer
            } else {
                $types .= 's'; // string
            }
            $values[] = $value;
        }

        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'IA Profile added successfully']);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Error adding IA Profile: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
// Add this function to ia_profiles_ajax.php
function get_ia_profiles($db) {
    header('Content-Type: application/json');
    
    try {
        $query = "SELECT ip.*, 
                         e.first_name, e.middle_name, e.last_name, e.ext_name
                  FROM ia_profiles ip 
                  LEFT JOIN employee e ON ip.assigned_employee_id = e.emp_id 
                  WHERE 1=1";
        
        $countQuery = "SELECT COUNT(*) as total FROM ia_profiles ip WHERE 1=1";
        
        $params = [];
        $types = '';
        $countParams = [];
        $countTypes = '';
        
        // Apply filters
        if (!empty($_POST['filter_assigned_employee'])) {
            $query .= " AND ip.assigned_employee_id = ?";
            $countQuery .= " AND ip.assigned_employee_id = ?";
            $params[] = $_POST['filter_assigned_employee'];
            $countParams[] = $_POST['filter_assigned_employee'];
            $types .= 'i';
            $countTypes .= 'i';
        }
        
        if (!empty($_POST['filter_status'])) {
            $query .= " AND ip.status = ?";
            $countQuery .= " AND ip.status = ?";
            $params[] = $_POST['filter_status'];
            $countParams[] = $_POST['filter_status'];
            $types .= 's';
            $countTypes .= 's';
        }
        
        if (!empty($_POST['filter_region'])) {
            $query .= " AND ip.region LIKE ?";
            $countQuery .= " AND ip.region LIKE ?";
            $params[] = '%' . $_POST['filter_region'] . '%';
            $countParams[] = '%' . $_POST['filter_region'] . '%';
            $types .= 's';
            $countTypes .= 's';
        }
        
        if (!empty($_POST['filter_province'])) {
            $query .= " AND ip.province LIKE ?";
            $countQuery .= " AND ip.province LIKE ?";
            $params[] = '%' . $_POST['filter_province'] . '%';
            $countParams[] = '%' . $_POST['filter_province'] . '%';
            $types .= 's';
            $countTypes .= 's';
        }
        
        if (!empty($_POST['filter_ia_name'])) {
            $query .= " AND ip.ia_name LIKE ?";
            $countQuery .= " AND ip.ia_name LIKE ?";
            $params[] = '%' . $_POST['filter_ia_name'] . '%';
            $countParams[] = '%' . $_POST['filter_ia_name'] . '%';
            $types .= 's';
            $countTypes .= 's';
        }
        
        if (!empty($_POST['filter_ia_code'])) {
            $query .= " AND ip.ia_code LIKE ?";
            $countQuery .= " AND ip.ia_code LIKE ?";
            $params[] = '%' . $_POST['filter_ia_code'] . '%';
            $countParams[] = '%' . $_POST['filter_ia_code'] . '%';
            $types .= 's';
            $countTypes .= 's';
        }
        
        // Get total count
        $countStmt = $db->prepare($countQuery);
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalRecords = $countResult->fetch_assoc()['total'];
        
        // Add ordering and pagination for DataTables
        $query .= " ORDER BY ip.ia_name";
        
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        // Bind parameters if any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $profiles = [];
        while ($row = $result->fetch_assoc()) {
            $profiles[] = $row;
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $profiles,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting IA profiles: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0
        ]);
    }
}
function viewIaProfile($db) {
    // For view action, we return HTML, not JSON
    $id = $_GET['id'] ?? 0;
    
    if (empty($id)) {
        echo '<div class="alert alert-danger">Invalid profile ID</div>';
        return;
    }

    try {
        $query = "SELECT * FROM ia_profiles WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        
        if ($profile) {
            echo '<div class="row">';
            echo '<div class="col-md-6"><strong>IA Name:</strong> ' . htmlspecialchars($profile['ia_name']) . '</div>';
            echo '<div class="col-md-6"><strong>IA Code:</strong> ' . htmlspecialchars($profile['ia_code']) . '</div>';
            echo '</div>';
            
            echo '<div class="row mt-3">';
            echo '<div class="col-12"><strong>Mailing Address:</strong> ' . htmlspecialchars($profile['mailing_address']) . '</div>';
            echo '</div>';
            
            echo '<div class="row mt-3">';
            echo '<div class="col-md-6"><strong>President Name:</strong> ' . htmlspecialchars($profile['president_name']) . '</div>';
            echo '<div class="col-md-6"><strong>Contact Number:</strong> ' . htmlspecialchars($profile['contact_number']) . '</div>';
            echo '</div>';
            
            echo '<div class="row mt-3">';
            echo '<div class="col-md-4"><strong>Service Area:</strong> ' . number_format($profile['service_area_ha'], 2) . ' ha</div>';
            echo '<div class="col-md-4"><strong>FUSA:</strong> ' . number_format($profile['fusa_ha'], 2) . ' ha</div>';
            echo '<div class="col-md-4"><strong>Canal Length:</strong> ' . number_format($profile['canal_length_km'], 3) . ' km</div>';
            echo '</div>';
            
            echo '<div class="row mt-3">';
            echo '<div class="col-md-4"><strong>IA Members:</strong> ' . $profile['actual_ia_members'] . '</div>';
            echo '<div class="col-md-4"><strong>Male Members:</strong> ' . $profile['male_members'] . '</div>';
            echo '<div class="col-md-4"><strong>Female Members:</strong> ' . $profile['female_members'] . '</div>';
            echo '</div>';
            
            echo '<div class="row mt-3">';
            echo '<div class="col-md-4"><strong>Farmer Beneficiaries:</strong> ' . $profile['farmer_beneficiaries'] . '</div>';
            echo '<div class="col-md-4"><strong>TSAGs Count:</strong> ' . $profile['tsags_count'] . '</div>';
            echo '<div class="col-md-4"><strong>Status:</strong> <span class="badge badge-' . ($profile['status'] == 'active' ? 'success' : 'danger') . '">' . ucfirst($profile['status']) . '</span></div>';
            echo '</div>';
            
            // Add more fields as needed
            if (!empty($profile['date_organized'])) {
                echo '<div class="row mt-3">';
                echo '<div class="col-12"><strong>Date Organized:</strong> ' . date('F j, Y', strtotime($profile['date_organized'])) . '</div>';
                echo '</div>';
            }
            
            if (!empty($profile['region'])) {
                echo '<div class="row mt-3">';
                echo '<div class="col-md-4"><strong>Region:</strong> ' . htmlspecialchars($profile['region']) . '</div>';
                echo '<div class="col-md-4"><strong>Province:</strong> ' . htmlspecialchars($profile['province']) . '</div>'; // ADD THIS LINE
                echo '<div class="col-md-4"><strong>Congressional District:</strong> ' . htmlspecialchars($profile['congressional_district']) . '</div>';
                echo '</div>';
            }
            
            // Additional fields
            if (!empty($profile['sec_registration_number'])) {
                echo '<div class="row mt-3">';
                echo '<div class="col-md-6"><strong>SEC Registration Number:</strong> ' . htmlspecialchars($profile['sec_registration_number']) . '</div>';
                if (!empty($profile['sec_registration_date'])) {
                    echo '<div class="col-md-6"><strong>SEC Registration Date:</strong> ' . date('F j, Y', strtotime($profile['sec_registration_date'])) . '</div>';
                }
                echo '</div>';
            }
            
            if (!empty($profile['ia_tin'])) {
                echo '<div class="row mt-3">';
                echo '<div class="col-12"><strong>IA TIN:</strong> ' . htmlspecialchars($profile['ia_tin']) . '</div>';
                echo '</div>';
            }
            
            if (!empty($profile['existing_contract'])) {
                echo '<div class="row mt-3">';
                echo '<div class="col-md-6"><strong>Existing Contract:</strong> ' . htmlspecialchars($profile['existing_contract']) . '</div>';
                if (!empty($profile['contract_effectivity_date'])) {
                    echo '<div class="col-md-6"><strong>Contract Effectivity Date:</strong> ' . date('F j, Y', strtotime($profile['contract_effectivity_date'])) . '</div>';
                }
                echo '</div>';
            }
            
            if (!empty($profile['imo'])) {
                echo '<div class="row mt-3">';
                echo '<div class="col-12"><strong>IMO:</strong> ' . htmlspecialchars($profile['imo']) . '</div>';
                echo '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">Profile not found</div>';
        }
    } catch (Exception $e) {
        error_log("Error viewing IA Profile: " . $e->getMessage());
        echo '<div class="alert alert-danger">Error loading profile: ' . $e->getMessage() . '</div>';
    }
}

function deleteIaProfile($db) {
    header('Content-Type: application/json');
    
    if (!hasPermission('delete_ia_profile')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }

    $id = $_POST['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid profile ID']);
        return;
    }

    try {
        $query = "DELETE FROM ia_profiles WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'IA Profile deleted successfully']);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Error deleting IA Profile: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
// Update the assigned employee query in viewIaProfile function
$assignedQuery = "SELECT e.first_name, e.middle_name, e.last_name, e.ext_name 
                  FROM ia_profiles ip 
                  LEFT JOIN employee e ON ip.assigned_employee_id = e.emp_id 
                  WHERE ip.id = ?";
$assignedStmt = $db->prepare($assignedQuery);
$assignedStmt->bind_param('i', $id);
$assignedStmt->execute();
$assignedResult = $assignedStmt->get_result();
$assignedEmployee = $assignedResult->fetch_assoc();

if ($assignedEmployee && $assignedEmployee['first_name']) {
    $assignedName = $assignedEmployee['first_name'] . ' ' . $assignedEmployee['middle_name'] . ' ' . $assignedEmployee['last_name'];
    if (!empty($assignedEmployee['ext_name'])) {
        $assignedName .= ' ' . $assignedEmployee['ext_name'];
    }
    
    echo '<div class="row mt-3">';
    echo '<div class="col-12"><strong>Assigned IDU Employee:</strong> ' . htmlspecialchars(trim($assignedName)) . '</div>';
    echo '</div>';
}
?>