<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

session_start();
checkPermission('view_calendar');

$database = new Database();
$db = $database->getConnection();

// Get recent activity (last 10 activities)
$query = "SELECT 
            'creation' as type,
            CONCAT('New transport request #', sr.request_no, ' to ', sr.destination) as message,
            sr.date_requested as activity_date,
            CONCAT(req.first_name, ' ', req.last_name) as employee_name
          FROM service_requests sr
          JOIN employee req ON sr.requesting_emp_id = req.emp_id
          WHERE sr.date_requested >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          
          UNION ALL
          
          SELECT 
            'approval' as type,
            CONCAT('Request #', sr.request_no, ' was approved by ', approver.first_name, ' ', approver.last_name) as message,
            sr.approved_at as activity_date,
            CONCAT(approver.first_name, ' ', approver.last_name) as employee_name
          FROM service_requests sr
          JOIN employee approver ON sr.approved_by = approver.emp_id
          WHERE sr.approved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND sr.status = 'approved'
          
          UNION ALL
          
          SELECT 
            'completion' as type,
            CONCAT('Trip #', sr.request_no, ' to ', sr.destination, ' was completed') as message,
            sr.updated_at as activity_date,
            CONCAT(req.first_name, ' ', req.last_name) as employee_name
          FROM service_requests sr
          JOIN employee req ON sr.requesting_emp_id = req.emp_id
          WHERE sr.status = 'completed'
          AND sr.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          
          UNION ALL
          
          SELECT 
            'passenger_approval' as type,
            CONCAT('Passenger ', e.first_name, ' ', e.last_name, ' was approved for request #', sr.request_no) as message,
            srp.approved_at as activity_date,
            CONCAT(approver.first_name, ' ', approver.last_name) as employee_name
          FROM service_request_passengers srp
          JOIN service_requests sr ON srp.request_id = sr.request_id
          JOIN employee e ON srp.emp_id = e.emp_id
          JOIN employee approver ON srp.approved_by = approver.emp_id
          WHERE srp.approved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND srp.approved = 1
          
          ORDER BY activity_date DESC
          LIMIT 10";

$result = $db->query($query);
$activities = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $time_ago = '';
        $activity_date = new DateTime($row['activity_date']);
        $now = new DateTime();
        $interval = $now->diff($activity_date);
        
        if ($interval->y > 0) {
            $time_ago = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
        } elseif ($interval->m > 0) {
            $time_ago = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
        } elseif ($interval->d > 0) {
            $time_ago = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
        } elseif ($interval->h > 0) {
            $time_ago = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
        } elseif ($interval->i > 0) {
            $time_ago = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
        } else {
            $time_ago = 'Just now';
        }
        
        $activities[] = [
            'type' => $row['type'],
            'message' => $row['message'],
            'time' => $time_ago
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $activities]);
?>