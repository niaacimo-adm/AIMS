<?php
// includes/queue_ajax.php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

function jsonResponse($data)
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function logError($message)
{
    error_log("Queue AJAX Error: " . $message);
}

// Test database connection
if (!$db) {
    error_log("Database connection failed in queue_ajax.php");
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $db->connect_error]);
    exit;
}

try {
    switch ($action) {
        case 'add_to_queue':
            addToQueue();
            break;
        case 'get_queue':
            getQueue();
            break;
        case 'call_next':
            callNext();
            break;
        case 'call_visitor':
            callVisitor();
            break;
        case 'serve_visitor':
            serveVisitor();
            break;
        case 'complete_visitor':
            completeVisitor();
            break;
        case 'cancel_visitor':
            cancelVisitor();
            break;
        case 'update_visitor':
            updateVisitor();
            break;
        case 'get_queue_status':
            getQueueStatus();
            break;
        case 'get_display_data':
            getDisplayData();
            break;
        case 'get_section_counters':
            getSectionCounters();
            break;
        case 'get_visitor_details':
            getVisitorDetails();
            break;
        case 'get_section_queue':
            getSectionQueue();
            break;
        case 'call_next_section':
            callNextSection();
            break;
        case 'get_completed_today':
            getCompletedToday();
            break;
        case 'recall_visitor':
            recallVisitor();
            break;
        case 'transfer_queue':
            transferQueue();
            break;
        case 'call_specific_visitor':
            callSpecificVisitor();
            break;
        // case 'reset_daily_queue':
        //     resetDailyQueue();
        //     break;
        case 'get_queue_summary':
            getQueueSummary();
            break;
        case 'no_show_visitor':
            noShowVisitor();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    logError($e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
function callSpecificVisitor()
{
    global $db;

    $queue_id = $_POST['queue_id'] ?? 0;
    $section_type = $_POST['type'] ?? '';
    $section_id = $_POST['section_id'] ?? 0;
    $unit_id = $_POST['unit_id'] ?? 0;
    $is_manager_staff = $_POST['is_manager_staff'] ?? false;

    if ($queue_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid queue ID']);
        return;
    }

    // First, verify that the visitor belongs to the current section/unit
    $verifyQuery = "SELECT * FROM visitor_queue WHERE id = ? AND status IN ('waiting', 'called') ";
    
    if ($section_type == 'imo') {
        $verifyQuery .= "AND section_name = 'IMO Office' ";
    } elseif ($section_type == 'section' && $section_id > 0) {
        $verifyQuery .= "AND section_id = ? ";
    } elseif ($section_type == 'unit' && $unit_id > 0) {
        $verifyQuery .= "AND unit_id = ? ";
    }
    
    $stmt = $db->prepare($verifyQuery);
    
    if ($section_type == 'section' && $section_id > 0) {
        $stmt->bind_param("ii", $queue_id, $section_id);
    } elseif ($section_type == 'unit' && $unit_id > 0) {
        $stmt->bind_param("ii", $queue_id, $unit_id);
    } else {
        $stmt->bind_param("i", $queue_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Update visitor status to 'serving'
        $updateQuery = "UPDATE visitor_queue SET status = 'serving', time_called = NOW() WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bind_param("i", $queue_id);
        
        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true,
                'queue_number' => $row['queue_number'],
                'visitor_name' => $row['visitor_name'],
                'message' => 'Visitor called successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update visitor status: ' . $db->error
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Visitor not found in your queue or already being served'
        ]);
    }
}

function getSectionQueue()
{
    global $db;

    // Get queue for specific section/unit/imo
    $section_type = $_POST['type'] ?? '';
    $section_id = $_POST['section_id'] ?? 0;
    $unit_id = $_POST['unit_id'] ?? 0;
    $is_manager_staff = $_POST['is_manager_staff'] ?? false;

    // Build query based on section type
    $whereClause = "WHERE q.status IN ('waiting', 'called', 'serving') ";
    $params = [];
    $types = "";

    if ($section_type == 'imo') {
        $whereClause .= "AND q.section_name = 'IMO Office' ";
    } elseif ($section_type == 'section' && $section_id > 0) {
        $whereClause .= "AND q.section_id = ? ";
        $params[] = $section_id;
        $types .= "i";
    } elseif ($section_type == 'unit' && $unit_id > 0) {
        $whereClause .= "AND q.unit_id = ? ";
        $params[] = $unit_id;
        $types .= "i";
    }

    // Get current serving visitor
    $currentQuery = "SELECT q.* FROM visitor_queue q $whereClause AND q.status = 'serving' LIMIT 1";
    $waitingQuery = "SELECT q.* FROM visitor_queue q $whereClause AND q.status IN ('waiting', 'called') ORDER BY 
                     CASE WHEN q.is_priority = 1 THEN 0 ELSE 1 END, 
                     q.priority_number ASC, 
                     q.id ASC";

    // Execute queries with parameters if any
    if (!empty($params)) {
        $stmt = $db->prepare($currentQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();

        $stmt = $db->prepare($waitingQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $waiting = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $current = $db->query($currentQuery)->fetch_assoc();
        $waiting = $db->query($waitingQuery)->fetch_all(MYSQLI_ASSOC);
    }

    // Get statistics
    $statsQuery = "SELECT 
                    SUM(CASE WHEN status IN ('waiting', 'called') THEN 1 ELSE 0 END) as waiting_count,
                    SUM(CASE WHEN status = 'serving' THEN 1 ELSE 0 END) as serving_count,
                    COUNT(*) as total_today
                   FROM visitor_queue 
                   WHERE DATE(time_in) = CURDATE() ";

    if ($section_type == 'imo') {
        $statsQuery .= "AND section_name = 'IMO Office'";
    } elseif ($section_type == 'section' && $section_id > 0) {
        $statsQuery .= "AND section_id = ?";
    } elseif ($section_type == 'unit' && $unit_id > 0) {
        $statsQuery .= "AND unit_id = ?";
    }

    if (!empty($params)) {
        $stmt = $db->prepare($statsQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
    } else {
        $stats = $db->query($statsQuery)->fetch_assoc();
    }

    echo json_encode([
        'success' => true,
        'current_serving' => $current,
        'waiting_list' => $waiting,
        'statistics' => $stats
    ]);
}

function callNextSection()
{
    global $db;

    // Call next visitor for specific section
    $section_type = $_POST['type'] ?? '';
    $section_id = $_POST['section_id'] ?? 0;
    $unit_id = $_POST['unit_id'] ?? 0;
    $is_manager_staff = $_POST['is_manager_staff'] ?? false;

    // Find next visitor based on section
    $query = "SELECT * FROM visitor_queue 
              WHERE status IN ('waiting', 'called') ";

    if ($section_type == 'imo') {
        $query .= "AND section_name = 'IMO Office' ";
    } elseif ($section_type == 'section' && $section_id > 0) {
        $query .= "AND section_id = ? ";
    } elseif ($section_type == 'unit' && $unit_id > 0) {
        $query .= "AND unit_id = ? ";
    }

    $query .= "ORDER BY 
               CASE WHEN is_priority = 1 THEN 0 ELSE 1 END, 
               priority_number ASC, 
               id ASC 
               LIMIT 1";

    $stmt = $db->prepare($query);

    if ($section_type == 'section' && $section_id > 0) {
        $stmt->bind_param("i", $section_id);
    } elseif ($section_type == 'unit' && $unit_id > 0) {
        $stmt->bind_param("i", $unit_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Update visitor status to 'serving'
        $updateQuery = "UPDATE visitor_queue SET status = 'serving', time_called = NOW() WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();

        echo json_encode([
            'success' => true,
            'queue_number' => $row['queue_number'],
            'visitor_name' => $row['visitor_name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No visitors waiting in queue'
        ]);
    }
}

function getCompletedToday()
{
    global $db;

    // Get completed visitors for today
    $section_type = $_POST['type'] ?? '';
    $section_id = $_POST['section_id'] ?? 0;
    $unit_id = $_POST['unit_id'] ?? 0;

    $query = "SELECT * FROM visitor_queue 
              WHERE status = 'completed' 
              AND DATE(time_in) = CURDATE() ";

    if ($section_type == 'imo') {
        $query .= "AND section_name = 'IMO Office' ";
    } elseif ($section_type == 'section' && $section_id > 0) {
        $query .= "AND section_id = ? ";
    } elseif ($section_type == 'unit' && $unit_id > 0) {
        $query .= "AND unit_id = ? ";
    }

    $query .= "ORDER BY time_in DESC LIMIT 20";

    $stmt = $db->prepare($query);

    if ($section_type == 'section' && $section_id > 0) {
        $stmt->bind_param("i", $section_id);
    } elseif ($section_type == 'unit' && $unit_id > 0) {
        $stmt->bind_param("i", $unit_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $completed = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'completed_visitors' => $completed
    ]);
}

function recallVisitor()
{
    global $db;

    // Recall a visitor (announce again)
    $queue_id = $_POST['queue_id'] ?? 0;

    $query = "SELECT * FROM visitor_queue WHERE id = ? AND status = 'serving'";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $queue_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'queue_number' => $row['queue_number'],
            'message' => 'Visitor recalled successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Visitor not found or not currently being served'
        ]);
    }
}

function transferQueue()
{
    global $db;

    // Transfer queue to another section
    $target_section = $_POST['target_section'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $section_type = $_POST['type'] ?? '';
    $section_id = $_POST['section_id'] ?? 0;
    $unit_id = $_POST['unit_id'] ?? 0;

    // Parse target section
    $target_type = '';
    $target_id = 0;

    if (strpos($target_section, 'section_') === 0) {
        $target_type = 'section';
        $target_id = intval(str_replace('section_', '', $target_section));
    } elseif (strpos($target_section, 'unit_') === 0) {
        $target_type = 'unit';
        $target_id = intval(str_replace('unit_', '', $target_section));
    } elseif ($target_section == 'manager_office') {
        $target_type = 'imo';
    }

    // Get target section/unit name
    $target_name = '';
    if ($target_type == 'section') {
        $query = "SELECT section_name FROM section WHERE section_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $target_name = $row['section_name'];
        }
    } elseif ($target_type == 'unit') {
        $query = "SELECT unit_name FROM unit_section WHERE unit_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $target_name = $row['unit_name'] . ' Unit';
        }
    } elseif ($target_type == 'imo') {
        $target_name = 'IMO Office';
    }

    // Update all waiting/called visitors from current section
    $updateQuery = "UPDATE visitor_queue SET ";

    if ($target_type == 'section') {
        $updateQuery .= "section_id = ?, unit_id = NULL, section_name = ? ";
        $params = [$target_id, $target_name];
        $types = "is";
    } elseif ($target_type == 'unit') {
        // Get parent section for the unit
        $unitQuery = "SELECT section_id FROM unit_section WHERE unit_id = ?";
        $unitStmt = $db->prepare($unitQuery);
        $unitStmt->bind_param("i", $target_id);
        $unitStmt->execute();
        $unitResult = $unitStmt->get_result();
        $unitData = $unitResult->fetch_assoc();

        $parent_section_id = $unitData['section_id'] ?? 0;

        $updateQuery .= "section_id = ?, unit_id = ?, section_name = (SELECT section_name FROM section WHERE section_id = ?) ";
        $params = [$parent_section_id, $target_id, $parent_section_id];
        $types = "iii";
    } elseif ($target_type == 'imo') {
        $updateQuery .= "section_id = NULL, unit_id = NULL, section_name = ? ";
        $params = [$target_name];
        $types = "s";
    }

    $updateQuery .= ", remarks = CONCAT(IFNULL(remarks, ''), ' Transferred from current section. Reason: ', ?) ";
    $params[] = $reason;
    $types .= "s";

    $updateQuery .= "WHERE status IN ('waiting', 'called') ";

    if ($section_type == 'imo') {
        $updateQuery .= "AND section_name = 'IMO Office' ";
    } elseif ($section_type == 'section' && $section_id > 0) {
        $updateQuery .= "AND section_id = ? ";
        $params[] = $section_id;
        $types .= "i";
    } elseif ($section_type == 'unit' && $unit_id > 0) {
        $updateQuery .= "AND unit_id = ? ";
        $params[] = $unit_id;
        $types .= "i";
    }

    $stmt = $db->prepare($updateQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Queue transferred to ' . $target_name,
        'transferred_count' => $stmt->affected_rows
    ]);
}

function addToQueue()
{
    global $db;

    // Get POST data
    $data = [
        'visitor_name' => $_POST['visitor_name'] ?? '',
        'company' => $_POST['company'] ?? '',
        'purpose' => $_POST['purpose'] ?? '',
        'person_to_visit' => $_POST['person_to_visit'] ?? '',
        'section' => $_POST['section'] ?? '',
        'contact_number' => $_POST['contact_number'] ?? '',
        'remarks' => $_POST['remarks'] ?? '',
        'is_priority' => $_POST['is_priority'] ?? '0' // Add this line
    ];

    // Log the incoming data for debugging
    error_log("addToQueue - Received data: " . print_r($data, true));

    // Validate required fields
    if (
        empty($data['visitor_name']) || empty($data['purpose']) ||
        empty($data['person_to_visit']) || empty($data['section'])
    ) {
        jsonResponse(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }

    $section_type = '';
    $section_id = 0;
    $unit_id = 0;
    $prefix = ''; // Will be set based on section/unit
    $section_name = '';
    $unit_name = '';

    // Check if it's a priority queue (based on checkbox)
    $is_priority = isset($_POST['is_priority']) && $_POST['is_priority'] == '1' ? 1 : 0;
    $queue_prefix = $is_priority ? 'P' : 'V'; // Priority = P, Regular = V

    $section_code = '';
    $unit_code = '';

    if ($data['section'] === 'manager_office') {
        $section_type = 'manager';
        $section_name = "IMO Office";
        $section_code = "IMO";  // Use IMO for Manager's Office
        $prefix = "IMO";
    } elseif (strpos($data['section'], 'section_') === 0) {
        $section_type = 'section';
        $section_id = intval(str_replace('section_', '', $data['section']));

        // Get section details with code
        $stmt = $db->prepare("SELECT section_name, section_code FROM section WHERE section_id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $section_name = $row['section_name'];
            $section_code = $row['section_code'];
            $prefix = $section_code;  // Use section code instead of initials
        }
    } elseif (strpos($data['section'], 'unit_') === 0) {
        $section_type = 'unit';
        $unit_id = intval(str_replace('unit_', '', $data['section']));

        // Get unit details with code
        $stmt = $db->prepare("SELECT unit_name, unit_code FROM unit_section WHERE unit_id = ?");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $unit_name = $row['unit_name'];
            $unit_code = $row['unit_code'];
            $prefix = $unit_code;  // Use unit code instead of initials

            // Also get the parent section for the unit
            $stmt2 = $db->prepare("SELECT s.section_id, s.section_name FROM section s 
                               INNER JOIN unit_section us ON s.section_id = us.section_id 
                               WHERE us.unit_id = ?");
            $stmt2->bind_param("i", $unit_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            if ($row2 = $result2->fetch_assoc()) {
                $section_id = $row2['section_id'];
                $section_name = $row2['section_name'];
            }
        }
    }

    // Generate queue number
    if ($is_priority) {
        // Generate priority queue number
        $query = "SELECT MAX(priority_number) as last_number FROM visitor_queue 
              WHERE priority_number LIKE '{$prefix}-P%' 
              AND DATE(time_in) = CURDATE()";
        $result = $db->query($query);
        $row = $result->fetch_assoc();

        if ($row && $row['last_number']) {
            $last_num = intval(substr($row['last_number'], -3));
            $next_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $next_num = '001';
        }

        $queue_number = $section_name ?: $unit_name;
        $priority_number = $prefix . '-P' . $next_num; // Format: CODE-P001
        $queue_number = $prefix . '-P' . $next_num; // Also set queue_number for priority
    } else {
        // Generate regular queue number
        $query = "SELECT MAX(queue_number) as last_number FROM visitor_queue 
              WHERE queue_number LIKE '{$prefix}-V%' 
              AND DATE(time_in) = CURDATE()
              AND is_priority = 0";
        $result = $db->query($query);
        $row = $result->fetch_assoc();

        if ($row && $row['last_number']) {
            $last_num = intval(substr($row['last_number'], -3));
            $next_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $next_num = '001';
        }

        $queue_number = $prefix . '-V' . $next_num; // Format: CODE-V001
        $priority_number = null;
    }

    // Get employee name (not storing it anymore)
    $emp_id = intval($data['person_to_visit']);

    $stmt = $db->prepare("INSERT INTO visitor_queue 
        (queue_number, priority_number, visitor_name, company, purpose, person_to_visit,
        section_id, unit_id, section_name, unit_name, is_manager_office,
        contact_number, remarks, status, created_by, is_priority) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $created_by = $_SESSION['emp_id'] ?? 0;
    $is_manager_office = ($section_type === 'manager') ? 1 : 0;
    $status = 'waiting';

    // Handle section/unit ID assignment properly
    $section_id_param = null;
    $unit_id_param = null;

    if ($section_type === 'manager') {
        $section_id_param = null;
        $unit_id_param = null;
    } elseif ($section_type === 'section') {
        $section_id_param = $section_id;
        $unit_id_param = null;
    } elseif ($section_type === 'unit') {
        $section_id_param = $section_id; // Parent section ID for the unit
        $unit_id_param = $unit_id; // Actual unit ID
    }

    $stmt->bind_param(
        "sssssiiissiissii", // 16 parameters
        $queue_number,        // queue_number
        $priority_number,     // priority_number
        $data['visitor_name'], // visitor_name
        $data['company'],     // company
        $data['purpose'],     // purpose
        $emp_id,              // person_to_visit
        $section_id_param,    // section_id
        $unit_id_param,       // unit_id
        $section_name,        // section_name
        $unit_name,           // unit_name
        $is_manager_office,   // is_manager_office
        $data['contact_number'], // contact_number
        $data['remarks'],     // remarks
        $status,              // status
        $created_by,          // created_by
        $is_priority          // is_priority
    );

    if ($stmt->execute()) {
        $queue_id = $stmt->insert_id;

        // Return the correct display queue number
        $display_queue_number = $is_priority ? $priority_number : $queue_number;

        jsonResponse([
            'success' => true,
            'queue_id' => $queue_id,
            'queue_number' => $display_queue_number,
            'is_priority' => $is_priority,
            'priority_number' => $priority_number,
            'visitor_name' => $data['visitor_name'],
            'section_name' => $section_name,
            'unit_name' => $unit_name,
            'time_in' => date('Y-m-d H:i:s')
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to add to queue: ' . $db->error]);
    }
}

function getInitials($name)
{
    $words = explode(' ', $name);
    $initials = '';

    foreach ($words as $word) {
        if (ctype_upper($word[0])) {
            $initials .= $word[0];
        }
    }

    // If no uppercase initials found, use first letters
    if (empty($initials)) {
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }

    return $initials;
}
function getQueue()
{
    global $db;

    // Debug: Check if we can connect to the database
    if (!$db) {
        jsonResponse(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    // Try to get data with better error handling
    try {

        $query = "SELECT vq.*, 
              s.section_name, s.section_code,
              u.unit_name, u.unit_code,
              e.first_name, e.last_name,
              CONCAT(e.last_name, ', ', e.first_name) AS employee_name
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              LEFT JOIN employee e ON vq.person_to_visit = e.emp_id
              WHERE DATE(vq.time_in) = CURDATE() 
              AND vq.status IN ('waiting', 'called', 'serving')
              ORDER BY 
                vq.is_priority DESC, -- Priority queues first
                CASE vq.status 
                    WHEN 'serving' THEN 1
                    WHEN 'called' THEN 2
                    WHEN 'waiting' THEN 3
                    ELSE 4
                END, 
                vq.time_in ASC";
        error_log("Queue Query Executing: " . $query);

        $result = $db->query($query);

        if (!$result) {
            error_log("Database error in getQueue: " . $db->error);
            jsonResponse([
                'success' => false,
                'message' => 'Database query failed: ' . $db->error,
                'data' => []
            ]);
            return;
        }

        $queue = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $queue[] = [
                    'id' => $row['id'] ?? 0,
                    'queue_number' => $row['queue_number'] ?? '',
                    'priority_number' => $row['priority_number'] ?? '',
                    'is_priority' => $row['is_priority'] ?? 0,
                    'visitor_name' => $row['visitor_name'] ?? '',
                    'company' => $row['company'] ?? '',
                    'purpose' => $row['purpose'] ?? '',
                    'section_id' => $row['section_id'] ?? null,
                    'unit_id' => $row['unit_id'] ?? null,
                    'section_name' => $row['section_name'] ?? '',
                    'unit_name' => $row['unit_name'] ?? '',
                    'employee_name' => $row['employee_name'] ?? '',
                    'contact_number' => $row['contact_number'] ?? '',
                    'remarks' => $row['remarks'] ?? '',
                    'time_in' => $row['time_in'] ?? '',
                    'time_called' => $row['time_called'] ?? '',

                    'status' => $row['status'] ?? 'waiting',
                    'created_by' => $row['created_by'] ?? 0,
                    'first_name' => $row['first_name'] ?? '',
                    'last_name' => $row['last_name'] ?? ''
                ];
            }
        }

        error_log("Queue data found: " . count($queue) . " records");

        // Return success with data
        jsonResponse([
            'success' => true,
            'message' => 'Queue data loaded successfully',
            'data' => $queue,
            'recordsTotal' => count($queue),
            'recordsFiltered' => count($queue)
        ]);
    } catch (Exception $e) {
        error_log("Exception in getQueue: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
            'data' => []
        ]);
    }
}

function callNext()
{
    global $db;

    $section_filter = $_POST['section'] ?? '';

    // Build WHERE clause
    $where_clause = "vq.status = 'waiting' AND DATE(vq.time_in) = CURDATE()";
    $params = [];
    $types = "";

    if (!empty($section_filter)) {
        if (strpos($section_filter, 'section_') === 0) {
            $section_id = intval(str_replace('section_', '', $section_filter));
            $where_clause .= " AND vq.section_id = ?";
            $params[] = $section_id;
            $types .= "i";
        } elseif (strpos($section_filter, 'unit_') === 0) {
            $unit_id = intval(str_replace('unit_', '', $section_filter));
            $where_clause .= " AND vq.unit_id = ?";
            $params[] = $unit_id;
            $types .= "i";
        }
    }

    // Find next waiting visitor
    $query = "SELECT vq.*, s.section_name, u.unit_name 
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE $where_clause
              ORDER BY vq.time_in ASC 
              LIMIT 1";

    $stmt = $db->prepare($query);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        jsonResponse(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        return;
    }

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $visitor = $result->fetch_assoc();

        // Update to called - This should work now with updated schema
        $update = $db->prepare("UPDATE visitor_queue 
                               SET status = 'called', 
                                   time_called = NOW() 
                               WHERE id = ?");
        $update->bind_param("i", $visitor['id']);

        if ($update->execute()) {
            jsonResponse([
                'success' => true,
                'queue_number' => $visitor['queue_number'],
                'visitor_name' => $visitor['visitor_name'],
                'section_name' => $visitor['section_name'] ?? '',
                'unit_name' => $visitor['unit_name'] ?? ''
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to call visitor: ' . $db->error]);
        }
    } else {
        $message = 'No visitors in queue';
        if (!empty($section_filter)) {
            $message .= ' for this section/unit';
        }
        jsonResponse(['success' => false, 'message' => $message]);
    }
}

function callVisitor()
{
    global $db;

    $queue_id = intval($_POST['queue_id'] ?? 0);

    if ($queue_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid queue ID']);
        return;
    }

    // Fetch visitor — allow waiting OR called (re-announce support)
    $query = "SELECT vq.*,
              COALESCE(s.section_name, vq.section_name) as section_name,
              s.section_code,
              u.unit_name, u.unit_code,
              vq.is_manager_office
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE vq.id = ? AND vq.status IN ('waiting', 'called')";

    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $queue_id);

    if (!$stmt->execute()) {
        jsonResponse(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        return;
    }

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $visitor = $result->fetch_assoc();

        $update = $db->prepare("UPDATE visitor_queue 
                               SET status = 'called',
                                   time_called = NOW()
                               WHERE id = ?");
        $update->bind_param("i", $visitor['id']);

        if ($update->execute()) {
            // Resolve correct destination — unit_name takes priority over section_name
            if (!empty($visitor['is_manager_office'])) {
                $destination = 'IMO Office';
            } elseif (!empty($visitor['unit_name'])) {
                $destination = $visitor['unit_name'];   // e.g. "Cashier Unit"
            } else {
                $destination = $visitor['section_name'] ?? '';  // e.g. "Finance Section"
            }

            jsonResponse([
                'success'      => true,
                'queue_number' => $visitor['is_priority'] ? $visitor['priority_number'] : $visitor['queue_number'],
                'visitor_name' => $visitor['visitor_name'],
                'section_name' => $visitor['section_name'] ?? '',
                'unit_name'    => $visitor['unit_name'] ?? '',
                'destination'  => $destination,
                'is_imo'       => (bool)$visitor['is_manager_office'],
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to call visitor: ' . $db->error]);
        }
    } else {
        // Check if the visitor exists but is already serving/completed
        $check = $db->prepare("SELECT status FROM visitor_queue WHERE id = ?");
        $check->bind_param("i", $queue_id);
        $check->execute();
        $checkRow = $check->get_result()->fetch_assoc();
        if ($checkRow) {
            jsonResponse(['success' => false, 'message' => 'Visitor is already ' . $checkRow['status'] . ' and cannot be re-called.']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Visitor not found']);
        }
    }
}

function serveVisitor()
{
    global $db;

    $queue_id = intval($_POST['queue_id'] ?? 0);

    $update = $db->prepare("UPDATE visitor_queue 
                           SET status = 'serving',
                               time_called = COALESCE(time_called, NOW())
                           WHERE id = ? AND status IN ('called', 'waiting')");
    $update->bind_param("i", $queue_id);

    if ($update->execute() && $update->affected_rows > 0) {
        $q = $db->prepare("SELECT queue_number, priority_number, is_priority, visitor_name,
                            COALESCE(s.section_name, vq.section_name) as section_name, u.unit_name
                            FROM visitor_queue vq
                            LEFT JOIN section s ON vq.section_id = s.section_id
                            LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
                            WHERE vq.id = ?");
        $q->bind_param("i", $queue_id);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        jsonResponse([
            'success'      => true,
            'message'      => 'Visitor is now being served',
            'queue_number' => $row['is_priority'] ? $row['priority_number'] : $row['queue_number'],
            'visitor_name' => $row['visitor_name'],
            'section_name' => $row['section_name'] ?? '',
            'unit_name'    => $row['unit_name'] ?? ''
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Could not update status. Visitor may already be serving or not found.']);
    }
}

function completeVisitor()
{
    global $db;

    $queue_id  = intval($_POST['queue_id'] ?? 0);
    $remarks   = $_POST['remarks'] ?? null; // optional completion note

    $update = $db->prepare("UPDATE visitor_queue 
                           SET status = 'completed',
                               remarks = CASE WHEN ? IS NOT NULL AND ? != '' THEN ? ELSE remarks END
                           WHERE id = ? AND status = 'serving'");
    $update->bind_param("sssi", $remarks, $remarks, $remarks, $queue_id);

    if ($update->execute() && $update->affected_rows > 0) {
        jsonResponse(['success' => true, 'message' => 'Visitor service completed']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Could not complete. Visitor may not be in serving status.']);
    }
}

function cancelVisitor()
{
    global $db;

    $queue_id = intval($_POST['queue_id'] ?? 0);

    $update = $db->prepare("UPDATE visitor_queue 
                           SET status = 'cancelled'
                           WHERE id = ?");
    $update->bind_param("i", $queue_id);

    if ($update->execute()) {
        jsonResponse(['success' => true, 'message' => 'Visitor cancelled']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to cancel visitor: ' . $db->error]);
    }
}

function updateVisitor()
{
    global $db;

    $queue_id = intval($_POST['queue_id'] ?? 0);
    $visitor_name = $_POST['visitor_name'] ?? '';
    $company = $_POST['company'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';

    if (empty($visitor_name)) {
        jsonResponse(['success' => false, 'message' => 'Visitor name is required']);
        return;
    }

    $update = $db->prepare("UPDATE visitor_queue 
                           SET visitor_name = ?, 
                               company = ?, 
                               contact_number = ?,
                               updated_at = NOW()
                           WHERE id = ?");
    $update->bind_param("sssi", $visitor_name, $company, $contact_number, $queue_id);

    if ($update->execute()) {
        jsonResponse(['success' => true, 'message' => 'Visitor updated successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update visitor: ' . $db->error]);
    }
}

function getQueueStatus()
{
    global $db;

    // Get current serving visitor
    $query = "SELECT vq.*, s.section_name, u.unit_name 
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE vq.status = 'serving' 
              AND DATE(vq.time_in) = CURDATE()
              ORDER BY vq.time_called DESC 
              LIMIT 1";

    $result = $db->query($query);
    $now_serving = $result ? $result->fetch_assoc() : null;

    // Get next in line (called but not yet serving)
    $query = "SELECT queue_number FROM visitor_queue 
              WHERE status = 'called' 
              AND DATE(time_in) = CURDATE()
              ORDER BY time_called ASC 
              LIMIT 1";

    $result = $db->query($query);
    $next_in_line = $result ? $result->fetch_assoc() : null;

    // Get waiting count
    $query = "SELECT COUNT(*) as waiting_count FROM visitor_queue 
              WHERE status = 'waiting' 
              AND DATE(time_in) = CURDATE()";

    $result = $db->query($query);
    $waiting = $result ? $result->fetch_assoc() : ['waiting_count' => 0];

    jsonResponse([
        'success' => true,
        'now_serving' => $now_serving,
        'next_in_line' => $next_in_line ? $next_in_line['queue_number'] : null,
        'waiting_count' => $waiting['waiting_count'] ?? 0,
        'average_wait_time' => 5 // minutes
    ]);
}

function getDisplayData()
{
    global $db;

    // Get current priority serving visitor
    $query = "SELECT vq.*, 
              CASE 
                  WHEN vq.is_manager_office = 1 THEN 'IMO Office'
                  WHEN u.unit_name IS NOT NULL AND u.unit_name != '' THEN u.unit_name
                  ELSE COALESCE(s.section_name, vq.section_name, 'General')
              END as section_name,
              CASE 
                  WHEN vq.is_manager_office = 1 THEN 'IMO'
                  WHEN u.unit_code IS NOT NULL AND u.unit_code != '' THEN u.unit_code
                  ELSE COALESCE(s.section_code, '')
              END as section_code,
              u.unit_name, u.unit_code
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE vq.status = 'serving'
              AND vq.is_priority = 1
              AND DATE(vq.time_in) = CURDATE()
              ORDER BY vq.time_called DESC 
              LIMIT 1";

    $result = $db->query($query);
    $current_priority = $result ? $result->fetch_assoc() : null;

    // If no priority serving, get the most recent called priority visitor
    if (!$current_priority) {
        $query = "SELECT vq.*, 
                  CASE 
                      WHEN vq.is_manager_office = 1 THEN 'IMO Office'
                      ELSE s.section_name 
                  END as section_name,
                  CASE 
                      WHEN vq.is_manager_office = 1 THEN 'IMO'
                      ELSE s.section_code 
                  END as section_code,
                  u.unit_name, u.unit_code
                  FROM visitor_queue vq
                  LEFT JOIN section s ON vq.section_id = s.section_id
                  LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
                  WHERE vq.status = 'called'
                  AND vq.is_priority = 1
                  AND DATE(vq.time_in) = CURDATE()
                  ORDER BY vq.time_called DESC 
                  LIMIT 1";

        $result = $db->query($query);
        $current_priority = $result ? $result->fetch_assoc() : null;
    }

    // Get current regular serving visitor
    $query = "SELECT vq.*, 
              CASE 
                  WHEN vq.is_manager_office = 1 THEN 'IMO Office'
                  WHEN u.unit_name IS NOT NULL AND u.unit_name != '' THEN u.unit_name
                  ELSE COALESCE(s.section_name, vq.section_name, 'General')
              END as section_name,
              CASE 
                  WHEN vq.is_manager_office = 1 THEN 'IMO'
                  WHEN u.unit_code IS NOT NULL AND u.unit_code != '' THEN u.unit_code
                  ELSE COALESCE(s.section_code, '')
              END as section_code,
              u.unit_name, u.unit_code
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE vq.status = 'serving'
              AND vq.is_priority = 0
              AND DATE(vq.time_in) = CURDATE()
              ORDER BY vq.time_called DESC 
              LIMIT 1";

    $result = $db->query($query);
    $current_regular = $result ? $result->fetch_assoc() : null;

    // If no regular serving, get the most recent called regular visitor
    if (!$current_regular) {
        $query = "SELECT vq.*, 
                  CASE 
                      WHEN vq.is_manager_office = 1 THEN 'IMO Office'
                      ELSE s.section_name 
                  END as section_name,
                  CASE 
                      WHEN vq.is_manager_office = 1 THEN 'IMO'
                      ELSE s.section_code 
                  END as section_code,
                  u.unit_name, u.unit_code
                  FROM visitor_queue vq
                  LEFT JOIN section s ON vq.section_id = s.section_id
                  LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
                  WHERE vq.status = 'called'
                  AND vq.is_priority = 0
                  AND DATE(vq.time_in) = CURDATE()
                  ORDER BY vq.time_called DESC 
                  LIMIT 1";

        $result = $db->query($query);
        $current_regular = $result ? $result->fetch_assoc() : null;
    }

    // Post-process: ensure destination name is always set correctly.
    // SQL CASE already prefers unit_name over section_name, this is a safety fallback.
    foreach (['current_priority', 'current_regular'] as $var) {
        if (!$$var) continue;
        if (!empty($$var['is_manager_office'])) {
            $$var['section_name'] = 'IMO Office';
            $$var['section_code'] = 'IMO';
        } elseif (empty($$var['section_name']) && !empty($$var['unit_name'])) {
            $$var['section_name'] = $$var['unit_name'];
        } elseif (empty($$var['section_name'])) {
            $$var['section_name'] = 'General Queue';
        }
        // Add a dedicated 'destination' key used by TTS — always the most specific name
        $$var['destination'] = $$var['section_name'];
    }

    // Get waiting queue (max 10) - includes called visitors, separated by priority
    $query = "SELECT vq.*, 
              CASE 
                  WHEN vq.is_manager_office = 1 THEN 'IMO Office'
                  WHEN u.unit_name IS NOT NULL AND u.unit_name != '' THEN u.unit_name
                  ELSE COALESCE(s.section_name, vq.section_name, 'General')
              END as section_name,
              u.unit_name 
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE vq.status IN ('waiting', 'called')
              AND DATE(vq.time_in) = CURDATE()
              ORDER BY 
                vq.is_priority DESC, -- Priority first
                CASE vq.status 
                    WHEN 'called' THEN 1
                    WHEN 'waiting' THEN 2
                END,
                vq.time_in ASC
              LIMIT 10";

    $result = $db->query($query);
    $waiting_queue = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['is_manager_office'])) {
                $row['section_name'] = "IMO Office";
            } elseif (empty($row['section_name']) && !empty($row['unit_name'])) {
                $row['section_name'] = $row['unit_name'];
            }
            $row['destination'] = $row['section_name'];
            $waiting_queue[] = $row;
        }
    }

    // Get recently served (last 10)
    $query = "SELECT vq.*, 
              CASE 
                  WHEN vq.is_manager_office = 1 THEN 'IMO Office'
                  WHEN u.unit_name IS NOT NULL AND u.unit_name != '' THEN u.unit_name
                  ELSE COALESCE(s.section_name, vq.section_name, 'General')
              END as section_name,
              u.unit_name 
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE vq.status = 'completed' 
              AND DATE(vq.time_in) = CURDATE()
              ORDER BY vq.time_called DESC 
              LIMIT 10";

    $result = $db->query($query);
    $served_queue = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['is_manager_office'])) {
                $row['section_name'] = "IMO Office";
            } elseif (empty($row['section_name']) && !empty($row['unit_name'])) {
                $row['section_name'] = $row['unit_name'];
            }
            $row['destination'] = $row['section_name'];
            $served_queue[] = $row;
        }
    }

    // Get section status with COALESCE for better null handling
    $sections = [];
    $query = "SELECT s.section_id, s.section_code, s.section_name,
              COALESCE((SELECT queue_number FROM visitor_queue 
                       WHERE section_id = s.section_id 
                       AND status = 'serving' 
                       AND DATE(time_in) = CURDATE() 
                       ORDER BY time_called DESC LIMIT 1), '---') as current_serving,
              COALESCE((SELECT COUNT(*) FROM visitor_queue 
                       WHERE section_id = s.section_id 
                       AND status IN ('waiting', 'called')
                       AND DATE(time_in) = CURDATE()), 0) as waiting_count,
              COALESCE((SELECT COUNT(*) FROM visitor_queue 
                       WHERE section_id = s.section_id 
                       AND status IN ('serving')
                       AND DATE(time_in) = CURDATE()), 0) as serving_count,
              COALESCE((SELECT COUNT(*) FROM visitor_queue 
                       WHERE section_id = s.section_id 
                       AND DATE(time_in) = CURDATE()), 0) as total_today
              FROM section s
              WHERE s.office_id = 1";

    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }
    }

    // Add manager's office as a virtual section with IMO code
    $query = "SELECT 
                0 as section_id,
                'IMO' as section_code,
                'IMO Office' as section_name,
                COALESCE((SELECT queue_number FROM visitor_queue 
                         WHERE is_manager_office = 1 
                         AND status = 'serving' 
                         AND DATE(time_in) = CURDATE() 
                         ORDER BY time_called DESC LIMIT 1), '---') as current_serving,
                COALESCE((SELECT COUNT(*) FROM visitor_queue 
                         WHERE is_manager_office = 1 
                         AND status IN ('waiting', 'called')
                         AND DATE(time_in) = CURDATE()), 0) as waiting_count,
                COALESCE((SELECT COUNT(*) FROM visitor_queue 
                         WHERE is_manager_office = 1 
                         AND status IN ('serving')
                         AND DATE(time_in) = CURDATE()), 0) as serving_count,
                COALESCE((SELECT COUNT(*) FROM visitor_queue 
                         WHERE is_manager_office = 1 
                         AND DATE(time_in) = CURDATE()), 0) as total_today";

    $result = $db->query($query);
    if ($row = $result->fetch_assoc()) {
        array_unshift($sections, $row); // Add IMO office at the beginning
    }

    // Get unit status
    $units = [];
    $query = "SELECT u.unit_id, u.unit_code, u.unit_name,
              COALESCE((SELECT queue_number FROM visitor_queue 
                       WHERE unit_id = u.unit_id 
                       AND status = 'serving' 
                       AND DATE(time_in) = CURDATE() 
                       ORDER BY time_called DESC LIMIT 1), '---') as current_serving,
              COALESCE((SELECT COUNT(*) FROM visitor_queue 
                       WHERE unit_id = u.unit_id 
                       AND status IN ('waiting', 'called')
                       AND DATE(time_in) = CURDATE()), 0) as waiting_count,
              COALESCE((SELECT COUNT(*) FROM visitor_queue 
                       WHERE unit_id = u.unit_id 
                       AND status IN ('serving')
                       AND DATE(time_in) = CURDATE()), 0) as serving_count,
              COALESCE((SELECT COUNT(*) FROM visitor_queue 
                       WHERE unit_id = u.unit_id 
                       AND DATE(time_in) = CURDATE()), 0) as total_today
              FROM unit_section u";

    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
    }

    // DEBUG: Log what we found
    error_log("Current priority: " . ($current_priority ? $current_priority['queue_number'] : 'None'));
    error_log("Current regular: " . ($current_regular ? $current_regular['queue_number'] : 'None'));

    jsonResponse([
        'success' => true,
        'current_priority' => $current_priority,
        'current_regular' => $current_regular,
        'waiting_queue' => $waiting_queue,
        'served_queue' => $served_queue,
        'sections' => $sections,
        'units' => $units
    ]);
}


function getSectionCounters()
{
    global $db;

    $counters = [];

    // Get sections as counters
    $query = "SELECT s.section_id, s.section_name, s.section_code,
              (SELECT queue_number FROM visitor_queue 
               WHERE section_id = s.section_id 
               AND status = 'serving' 
               AND DATE(time_in) = CURDATE() 
               ORDER BY time_called DESC LIMIT 1) as current_serving,
              (SELECT COUNT(*) FROM visitor_queue 
               WHERE section_id = s.section_id 
               AND status = 'waiting'
               AND DATE(time_in) = CURDATE()) as waiting_count,
              (SELECT COUNT(*) FROM visitor_queue 
               WHERE section_id = s.section_id 
               AND status IN ('serving', 'called')
               AND DATE(time_in) = CURDATE()) as serving_count,
              (SELECT COUNT(*) FROM visitor_queue 
               WHERE section_id = s.section_id 
               AND DATE(time_in) = CURDATE()) as total_today
              FROM section s
              WHERE s.office_id = 1
              ORDER BY s.section_name";

    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $counters[] = [
                'type' => 'section',
                'id' => $row['section_id'],
                'name' => $row['section_name'] . ' (' . $row['section_code'] . ')',
                'current_serving' => $row['current_serving'] ?? '---',
                'waiting_count' => $row['waiting_count'] ?? 0,
                'serving_count' => $row['serving_count'] ?? 0,
                'total_today' => $row['total_today'] ?? 0
            ];
        }
    }

    // Get units as counters
    $query = "SELECT u.unit_id, u.unit_name, u.unit_code,
              (SELECT queue_number FROM visitor_queue 
               WHERE unit_id = u.unit_id 
               AND status = 'serving' 
               AND DATE(time_in) = CURDATE() 
               ORDER BY time_called DESC LIMIT 1) as current_serving,
              (SELECT COUNT(*) FROM visitor_queue 
               WHERE unit_id = u.unit_id 
               AND status = 'waiting'
               AND DATE(time_in) = CURDATE()) as waiting_count,
              (SELECT COUNT(*) FROM visitor_queue 
               WHERE unit_id = u.unit_id 
               AND status IN ('serving', 'called')
               AND DATE(time_in) = CURDATE()) as serving_count,
              (SELECT COUNT(*) FROM visitor_queue 
               WHERE unit_id = u.unit_id 
               AND DATE(time_in) = CURDATE()) as total_today
              FROM unit_section u
              ORDER BY u.unit_name";

    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $counters[] = [
                'type' => 'unit',
                'id' => $row['unit_id'],
                'name' => $row['unit_name'] . ' (' . $row['unit_code'] . ')',
                'current_serving' => $row['current_serving'] ?? '---',
                'waiting_count' => $row['waiting_count'] ?? 0,
                'serving_count' => $row['serving_count'] ?? 0,
                'total_today' => $row['total_today'] ?? 0
            ];
        }
    }

    jsonResponse([
        'success' => true,
        'counters' => $counters
    ]);
}

function getVisitorDetails()
{
    global $db;

    $queue_id = intval($_POST['queue_id'] ?? 0);

    $query = "SELECT * FROM visitor_queue WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $queue_id);

    if (!$stmt->execute()) {
        jsonResponse(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        return;
    }

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        jsonResponse([
            'success' => true,
            'visitor_name' => $row['visitor_name'],
            'company' => $row['company'],
            'contact_number' => $row['contact_number']
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Visitor not found']);
    }
}

// ══════════════════════════════════════════════════════════════
//  NEW FUNCTIONS
// ══════════════════════════════════════════════════════════════

/**
 * Mark a visitor as "no show" — they were called but didn't appear.
 * Sets status to 'cancelled' with a no_show flag.
 */
function noShowVisitor()
{
    global $db;
    $queue_id = intval($_POST['queue_id'] ?? 0);

    $update = $db->prepare("UPDATE visitor_queue 
                           SET status = 'cancelled',
                               remarks = CONCAT(COALESCE(remarks,''), ' [NO SHOW]')
                           WHERE id = ? AND status IN ('called', 'waiting')");
    $update->bind_param("i", $queue_id);

    if ($update->execute() && $update->affected_rows > 0) {
        jsonResponse(['success' => true, 'message' => 'Visitor marked as no-show']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Could not mark as no-show']);
    }
}

/**
 * Reset (cancel) all waiting/called visitors for today.
 * Used at end of day or to clear the queue. Only allowed for admin.
 */
// function resetDailyQueue()
// {
//     global $db;


//     $update->bind_param();

//     if ($db->query("UPDATE visitor_queue 
//                     SET status = 'cancelled'
//                     WHERE DATE(time_in) = CURDATE()
//                     AND status IN ('waiting', 'called')")) {
//         jsonResponse([
//             'success' => true,
//             'message' => 'Queue reset. ' . $db->affected_rows . ' visitor(s) cleared.',
//             'cleared' => $db->affected_rows
//         ]);
//     } else {
//         jsonResponse(['success' => false, 'message' => 'Reset failed: ' . $db->error]);
//     }
// }

/**
 * Quick summary stats for the dashboard widget — today's numbers.
 */
function getQueueSummary()
{
    global $db;

    $result = $db->query("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='waiting'   THEN 1 ELSE 0 END) as waiting,
        SUM(CASE WHEN status='called'    THEN 1 ELSE 0 END) as called,
        SUM(CASE WHEN status='serving'   THEN 1 ELSE 0 END) as serving,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(is_priority) as priority_total,
        ROUND(AVG(CASE WHEN time_called IS NOT NULL 
                  THEN TIMESTAMPDIFF(MINUTE, time_in, time_called) END), 1) as avg_wait_min,
        NULL as avg_serve_min
        FROM visitor_queue
        WHERE DATE(time_in) = CURDATE()");

    if ($result) {
        $row = $result->fetch_assoc();
        jsonResponse(['success' => true, 'summary' => $row]);
    } else {
        jsonResponse(['success' => false, 'message' => $db->error]);
    }
}