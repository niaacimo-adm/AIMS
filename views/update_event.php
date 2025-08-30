<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Validate required fields
    if (empty($_POST['id'])) {
        throw new Exception('Event ID is required');
    }
    if (empty($_POST['title'])) {
        throw new Exception('Title is required');
    }
    if (empty($_POST['start'])) {
        throw new Exception('Start date is required');
    }
    if (empty($_POST['type'])) {
        throw new Exception('Event type is required');
    }

    $id = (int)$_POST['id'];
    $title = trim($_POST['title']);
    $type = trim($_POST['type']);
    $start = trim($_POST['start']);
    $end = isset($_POST['end']) ? trim($_POST['end']) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Validate date formats and logic
    $startDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $start);
    if (!$startDateTime) {
        throw new Exception('Invalid start date format. Use YYYY-MM-DDTHH:MM');
    }

    if ($end) {
        $endDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $end);
        if (!$endDateTime) {
            throw new Exception('Invalid end date format. Use YYYY-MM-DDTHH:MM');
        }
        if ($endDateTime < $startDateTime) {
            throw new Exception('End date must be after start date');
        }
    }

    // Check if event exists
    $checkStmt = $conn->prepare("SELECT id FROM events WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows === 0) {
        throw new Exception('Event not found');
    }
    $checkStmt->close();

    // Update the event
    $stmt = $conn->prepare("UPDATE events SET 
                          title = ?, 
                          type = ?, 
                          start_date = ?, 
                          end_date = ?, 
                          description = ?
                          WHERE id = ?");
    $stmt->bind_param("sssssi", $title, $type, $start, $end, $description, $id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update event: ' . $stmt->error);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Event updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>