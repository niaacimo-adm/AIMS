<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    // Validate input
    if (!isset($_POST['id'])) {
        throw new Exception('Missing event ID');
    }

    $id = (int)$_POST['id'];
    
    // Check if event exists
    $check = $conn->prepare("SELECT id FROM events WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows === 0) {
        throw new Exception('Event not found');
    }

    // Delete the event
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception('Delete failed: ' . $stmt->error);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Event deleted'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>