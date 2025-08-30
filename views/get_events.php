<?php
require_once __DIR__ . '/../config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getEventColor($type) {
    $colors = [
        'birthday' => '#ff69b4',
        'holiday' => '#ffa500',
        'meeting' => '#4682b4',
        'event' => '#3c8dbc'
    ];
    return $colors[strtolower($type)] ?? '#3c8dbc';
}

function validateDate($date, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

try {
    // Verify database connection
    if (!isset($conn) || !$conn->ping()) {
        throw new Exception("Database connection failed");
    }

    $events = [];
    $query = "SELECT id, title, type, start_date, end_date, description 
              FROM events 
              WHERE start_date IS NOT NULL";
    
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    while($row = $result->fetch_assoc()) {
        try {
            // Skip invalid dates
            if (!validateDate($row['start_date']) || 
                ($row['end_date'] && !validateDate($row['end_date']))) {
                error_log("Invalid date in event ID {$row['id']}");
                continue;
            }

            $events[] = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'start' => $row['start_date'],
                'end' => $row['end_date'] ?: null,
                'description' => $row['description'],
                'type' => $row['type'],
                'backgroundColor' => getEventColor($row['type']),
                'borderColor' => getEventColor($row['type']),
                'editable' => true
            ];
        } catch (Exception $e) {
            error_log("Error processing event ID {$row['id']}: " . $e->getMessage());
            continue;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $events,
        'count' => count($events),
        'generated_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Events Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ]);
}
?>