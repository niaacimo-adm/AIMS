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

// Test simple query
$test = $db->query("SELECT 1 as test");
if (!$test) {
    error_log("Database query test failed: " . $db->error);
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . $db->error]);
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
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    logError($e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
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
        'remarks' => $_POST['remarks'] ?? ''
    ];

    // Validate required fields
    if (
        empty($data['visitor_name']) || empty($data['purpose']) ||
        empty($data['person_to_visit']) || empty($data['section'])
    ) {
        jsonResponse(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }

    // Parse section/unit
    $section_type = '';
    $section_id = 0;
    $unit_id = 0;
    $prefix = 'V'; // Default prefix
    $section_name = '';
    $unit_name = '';

    if ($data['section'] === 'manager_office') {
        $section_type = 'manager';
        $prefix = 'IMO';  // Changed from 'MGR' to 'IMO'
        $section_name = "IMO Office";  // Changed name
    } elseif (strpos($data['section'], 'section_') === 0) {
        $section_type = 'section';
        $section_id = intval(str_replace('section_', '', $data['section']));

        // Get section details
        $stmt = $db->prepare("SELECT section_code, section_name FROM section WHERE section_id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $prefix = $row['section_code'];
            $section_name = $row['section_name'];
        }
    } elseif (strpos($data['section'], 'unit_') === 0) {
        $section_type = 'unit';
        $unit_id = intval(str_replace('unit_', '', $data['section']));

        // Get unit details
        $stmt = $db->prepare("SELECT unit_code, unit_name FROM unit_section WHERE unit_id = ?");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $prefix = $row['unit_code'];
            $unit_name = $row['unit_name'];
        }
    }

    // Generate queue number
    $date = date('Ymd');
    $query = "SELECT MAX(queue_number) as last_number FROM visitor_queue 
              WHERE queue_number LIKE '{$prefix}%' 
              AND DATE(time_in) = CURDATE()";
    $result = $db->query($query);
    $row = $result->fetch_assoc();

    if ($row && $row['last_number']) {
        $last_num = intval(substr($row['last_number'], -3));
        $next_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $next_num = '001';
    }

    $queue_number = $prefix . $date . $next_num;

    // Get employee name (not storing it anymore)
    $emp_id = intval($data['person_to_visit']);

    // Insert visitor
    $stmt = $db->prepare("INSERT INTO visitor_queue 
        (queue_number, visitor_name, company, purpose, person_to_visit,
        section_id, unit_id, section_name, unit_name, is_manager_office,
        contact_number, remarks, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $created_by = $_SESSION['emp_id'] ?? 0;
    $is_manager_office = ($section_type === 'manager') ? 1 : 0;
    $status = 'waiting';

    $section_id_param = $section_type === 'section' ? $section_id : null;
    $unit_id_param = $section_type === 'unit' ? $unit_id : null;

    $stmt->bind_param(
        "ssssiiissiissi",
        $queue_number,
        $data['visitor_name'],
        $data['company'],
        $data['purpose'],
        $emp_id,
        $section_id_param,
        $unit_id_param,
        $section_name,
        $unit_name,
        $is_manager_office,
        $data['contact_number'],
        $data['remarks'],
        $status,
        $created_by
    );

    if ($stmt->execute()) {
        $queue_id = $stmt->insert_id;
        jsonResponse([
            'success' => true,
            'queue_id' => $queue_id,
            'queue_number' => $queue_number,
            'visitor_name' => $data['visitor_name'],
            'section_name' => $section_name,
            'unit_name' => $unit_name,
            'time_in' => date('Y-m-d H:i:s')
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to add to queue: ' . $db->error]);
    }
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
                    'time_served' => $row['time_served'] ?? '',
                    'time_completed' => $row['time_completed'] ?? '',
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

    $query = "SELECT vq.*, s.section_name, u.unit_name 
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE vq.id = ?";

    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $queue_id);

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
        jsonResponse(['success' => false, 'message' => 'Visitor not found']);
    }
}

function serveVisitor()
{
    global $db;

    $queue_id = intval($_POST['queue_id'] ?? 0);

    $update = $db->prepare("UPDATE visitor_queue 
                           SET status = 'serving', 
                               time_served = NOW() 
                           WHERE id = ? AND status = 'called'");
    $update->bind_param("i", $queue_id);

    if ($update->execute()) {
        jsonResponse(['success' => true, 'message' => 'Visitor is now being served']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update status: ' . $db->error]);
    }
}

function completeVisitor()
{
    global $db;

    $queue_id = intval($_POST['queue_id'] ?? 0);

    $update = $db->prepare("UPDATE visitor_queue 
                           SET status = 'completed', 
                               time_completed = NOW() 
                           WHERE id = ?");
    $update->bind_param("i", $queue_id);

    if ($update->execute()) {
        jsonResponse(['success' => true, 'message' => 'Visitor service completed']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to complete visitor: ' . $db->error]);
    }
}

function cancelVisitor()
{
    global $db;

    $queue_id = intval($_POST['queue_id'] ?? 0);

    $update = $db->prepare("UPDATE visitor_queue 
                           SET status = 'cancelled', 
                               time_cancelled = NOW() 
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
              ORDER BY vq.time_served DESC 
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

    // Get current serving visitor
    $query = "SELECT vq.*, 
              CASE 
                  WHEN vq.is_manager_office = 1 THEN 'IMO Office'
                  ELSE s.section_name 
              END as section_name,
              CASE 
                  WHEN vq.is_manager_office = 1 THEN 'IMO'
                  ELSE s.section_code 
              END as section_code,
              u.unit_name 
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE vq.status = 'serving'
              AND DATE(vq.time_in) = CURDATE()
              ORDER BY vq.time_served DESC 
              LIMIT 1";

    $result = $db->query($query);
    $current_serving = $result ? $result->fetch_assoc() : null;

    // If no serving, get the most recent called visitor
    if (!$current_serving) {
        $query = "SELECT vq.*, 
                  CASE 
                      WHEN vq.is_manager_office = 1 THEN 'IMO Office'
                      ELSE s.section_name 
                  END as section_name,
                  CASE 
                      WHEN vq.is_manager_office = 1 THEN 'IMO'
                      ELSE s.section_code 
                  END as section_code,
                  u.unit_name 
                  FROM visitor_queue vq
                  LEFT JOIN section s ON vq.section_id = s.section_id
                  LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
                  WHERE vq.status = 'called'
                  AND DATE(vq.time_in) = CURDATE()
                  ORDER BY vq.time_called DESC 
                  LIMIT 1";

        $result = $db->query($query);
        $current_serving = $result ? $result->fetch_assoc() : null;
    }

    // Handle manager's office display
    if ($current_serving) {
        // Check if it's manager's office
        if (isset($current_serving['is_manager_office']) && $current_serving['is_manager_office']) {
            $current_serving['section_name'] = "IMO Office";
            $current_serving['section_code'] = "IMO";
        }
        // If section_name is empty but unit_name exists, use unit_name
        elseif (empty($current_serving['section_name']) && !empty($current_serving['unit_name'])) {
            $current_serving['section_name'] = $current_serving['unit_name'];
        }
        // If both are empty, set a default
        elseif (empty($current_serving['section_name']) && empty($current_serving['unit_name'])) {
            $current_serving['section_name'] = 'General Queue';
        }
    }

    // Get waiting queue (max 10) - includes called visitors
    $query = "SELECT vq.*, 
              CASE 
                  WHEN vq.is_manager_office = 1 THEN 'IMO Office'
                  ELSE s.section_name 
              END as section_name,
              u.unit_name 
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE vq.status IN ('waiting', 'called')
              AND DATE(vq.time_in) = CURDATE()
              ORDER BY 
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
            // Handle manager's office for waiting queue items
            if (isset($row['is_manager_office']) && $row['is_manager_office']) {
                $row['section_name'] = "IMO Office";
            }
            // If section_name is empty but unit_name exists, use unit_name
            elseif (empty($row['section_name']) && !empty($row['unit_name'])) {
                $row['section_name'] = $row['unit_name'];
            }
            $waiting_queue[] = $row;
        }
    }

    // Get recently served (last 10)
    $query = "SELECT vq.*, 
              CASE 
                  WHEN vq.is_manager_office = 1 THEN 'IMO Office'
                  ELSE s.section_name 
              END as section_name,
              u.unit_name 
              FROM visitor_queue vq
              LEFT JOIN section s ON vq.section_id = s.section_id
              LEFT JOIN unit_section u ON vq.unit_id = u.unit_id
              WHERE vq.status = 'completed' 
              AND DATE(vq.time_in) = CURDATE()
              ORDER BY vq.time_completed DESC 
              LIMIT 10";

    $result = $db->query($query);
    $served_queue = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Handle manager's office for served queue items
            if (isset($row['is_manager_office']) && $row['is_manager_office']) {
                $row['section_name'] = "IMO Office";
            }
            // If section_name is empty but unit_name exists, use unit_name
            elseif (empty($row['section_name']) && !empty($row['unit_name'])) {
                $row['section_name'] = $row['unit_name'];
            }
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
                       ORDER BY time_served DESC LIMIT 1), '---') as current_serving,
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
                         ORDER BY time_served DESC LIMIT 1), '---') as current_serving,
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
                       ORDER BY time_served DESC LIMIT 1), '---') as current_serving,
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
    error_log("Current serving: " . ($current_serving ? $current_serving['queue_number'] . " (status: " . $current_serving['status'] . ")" : 'None'));
    
    jsonResponse([
        'success' => true,
        'current_serving' => $current_serving,
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
               ORDER BY time_served DESC LIMIT 1) as current_serving,
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
               ORDER BY time_served DESC LIMIT 1) as current_serving,
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
