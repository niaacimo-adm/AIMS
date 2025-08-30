<?php
require_once __DIR__ . '/../config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

try {
    // Verify database connection
    if (!isset($conn) || !$conn->ping()) {
        throw new Exception("Database connection failed");
    }

    $birthdays = [];
    $currentYear = date('Y');

    // Get all employees with birthdays
    $query = "SELECT emp_id, first_name, last_name, bday 
              FROM employee 
              WHERE bday IS NOT NULL 
              AND bday != '0000-00-00' 
              AND bday != '1970-01-01'";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    while($row = $result->fetch_assoc()) {
        try {
            $bday = trim($row['bday']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bday)) {
                error_log("Invalid date format for employee {$row['emp_id']}: $bday");
                continue;
            }
            
            list($birthYear, $birthMonth, $birthDay) = explode('-', $bday);
            
            $birthdays[] = [
                'id' => 'birthday_' . $row['emp_id'] . '_' . $currentYear,
                'title' => $row['first_name'] . ' ' . $row['last_name'] . "'s Birthday",
                'start' => $currentYear . '-' . $birthMonth . '-' . $birthDay,
                'allDay' => true,
                'type' => 'birthday',
                'backgroundColor' => '#ff69b4',
                'borderColor' => '#ff69b4',
                'editable' => false,
                'extendedProps' => [
                    'recurring' => true,
                    'originalDate' => $bday,
                    'employeeId' => $row['emp_id']
                ]
            ];
        } catch (Exception $e) {
            error_log("Error processing birthday for employee {$row['emp_id']}: " . $e->getMessage());
            continue;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $birthdays,
        'count' => count($birthdays),
        'generated_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Birthdays Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ]);
}
?>