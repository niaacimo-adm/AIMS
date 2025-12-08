<?php
// includes/queue_stats.php
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

switch($action) {
    case 'get_daily_stats':
        getDailyStats();
        break;
    case 'get_section_stats':
        getSectionStats();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getDailyStats() {
    global $db;
    
    // Get today's date
    $today = date('Y-m-d');
    
    // Total visitors today
    $query = "SELECT COUNT(*) as total FROM visitor_queue WHERE DATE(time_in) = CURDATE()";
    $result = $db->query($query);
    $total = $result->fetch_assoc()['total'];
    
    // Waiting count
    $query = "SELECT COUNT(*) as waiting FROM visitor_queue WHERE status = 'waiting' AND DATE(time_in) = CURDATE()";
    $result = $db->query($query);
    $waiting = $result->fetch_assoc()['waiting'];
    
    // Serving count
    $query = "SELECT COUNT(*) as serving FROM visitor_queue WHERE status = 'serving' AND DATE(time_in) = CURDATE()";
    $result = $db->query($query);
    $serving = $result->fetch_assoc()['serving'];
    
    // Completed count
    $query = "SELECT COUNT(*) as completed FROM visitor_queue WHERE status = 'completed' AND DATE(time_in) = CURDATE()";
    $result = $db->query($query);
    $completed = $result->fetch_assoc()['completed'];
    
    // Average wait time
    $query = "SELECT AVG(TIMESTAMPDIFF(MINUTE, time_in, time_called)) as avg_wait_time 
              FROM visitor_queue 
              WHERE status IN ('serving', 'completed') 
              AND time_called IS NOT NULL 
              AND DATE(time_in) = CURDATE()";
    $result = $db->query($query);
    $avg_wait = $result->fetch_assoc()['avg_wait_time'];
    
    echo json_encode([
        'success' => true,
        'total_today' => $total,
        'waiting' => $waiting,
        'serving' => $serving,
        'completed' => $completed,
        'avg_wait_time' => round($avg_wait ?: 0, 1)
    ]);
}

function getSectionStats() {
    global $db;
    
    $section_stats = [];
    
    // Get section statistics
    $query = "SELECT s.section_id, s.section_name, s.section_code,
              (SELECT COUNT(*) FROM visitor_queue 
               WHERE section_id = s.section_id 
               AND DATE(time_in) = CURDATE()) as total,
              (SELECT COUNT(*) FROM visitor_queue 
               WHERE section_id = s.section_id 
               AND status = 'waiting' 
               AND DATE(time_in) = CURDATE()) as waiting,
              (SELECT COUNT(*) FROM visitor_queue 
               WHERE section_id = s.section_id 
               AND status = 'serving' 
               AND DATE(time_in) = CURDATE()) as serving,
              (SELECT COUNT(*) FROM visitor_queue 
               WHERE section_id = s.section_id 
               AND status = 'completed' 
               AND DATE(time_in) = CURDATE()) as completed
              FROM section s
              WHERE s.office_id = 1
              ORDER BY s.section_name";
    
    $result = $db->query($query);
    while($row = $result->fetch_assoc()) {
        $section_stats[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'sections' => $section_stats
    ]);
}
?>