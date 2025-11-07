<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $employee_id = $_POST['employee_id'] ?? 0;
    $date = $_POST['date'] ?? '';
    
    if ($employee_id && $date) {
        $current_date = new DateTime($date);
        $week_start = clone $current_date;
        $week_start->modify('this week');
        $week_start->setTime(0, 0, 0);
        
        $week_end = clone $week_start;
        $week_end->modify('next week')->modify('-1 day');
        $week_end->setTime(23, 59, 59);
        
        $query = "SELECT COUNT(*) as slip_count 
                 FROM personal_locator_slips 
                 WHERE employee_id = ? 
                 AND purpose_type = 'personal' 
                 AND date BETWEEN ? AND ? 
                 AND status != 'rejected'";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("iss", $employee_id, $week_start->format('Y-m-d'), $week_end->format('Y-m-d'));
        $stmt->execute();
        $result = $stmt->get_result();
        $slip_count = $result->fetch_assoc()['slip_count'];
        
        header('Content-Type: application/json');
        if ($slip_count > 0) {
            echo json_encode([
                'isAllowed' => false,
                'message' => "You can only submit one personal matter locator slip per week. You already have a personal matter slip for this week (".$week_start->format('M j')." - ".$week_end->format('M j, Y').")."
            ]);
        } else {
            echo json_encode([
                'isAllowed' => true,
                'message' => ''
            ]);
        }
    }
}
?>