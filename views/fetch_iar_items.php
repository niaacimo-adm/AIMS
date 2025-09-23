<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

if (isset($_POST['iar_id'])) {
    $iar_id = intval($_POST['iar_id']);
    
    // Query to get items from the specific IAR
    $query = "SELECT i.id as item_id, i.name, i.description, i.unit_of_measure, 
                 i.current_stock as quantity, ii.unit_price, ii.total_price
          FROM iar_items ii
          JOIN delivery_items di ON ii.delivery_item_id = di.id
          JOIN items i ON di.item_id = i.id
          WHERE ii.iar_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $iar_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    echo json_encode(['success' => true, 'items' => $items]);
} else {
    echo json_encode(['success' => false, 'message' => 'IAR ID not provided']);
}
?>