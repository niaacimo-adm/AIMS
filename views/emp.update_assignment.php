<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = $_POST['emp_id'] ?? null;
    $employment_status_id = $_POST['employment_status_id'] ?? null;
    $appointment_status_id = $_POST['appointment_status_id'] ?? null;
    $position_id = $_POST['position_id'] ?? null;
    $section_id = ($_POST['section_id'] === 'nosec') ? NULL : $_POST['section_id']; // Modified
    $unit_section_id = ($_POST['unit_section_id'] === 'nousec') ? NULL : $_POST['unit_section_id']; // Modified
    $office_id = $_POST['office_id'] ?? null;

    try {
        // Start transaction
        $db->begin_transaction();

        // Update employee assignment
    $stmt = $db->prepare("UPDATE employee SET 
        employment_status_id = ?,
        appointment_status_id = ?,
        position_id = ?,
        section_id = ?,
        office_id = ?
        WHERE emp_id = ?");

    $stmt->bind_param("iiiiii", 
        $employment_status_id,
        $appointment_status_id,
        $position_id,
        $section_id,
        $office_id,
        $emp_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to update employee assignment");
    }

    // Handle unit section assignments
    $unit_section_ids = $_POST['unit_section_ids'] ?? [];

    // Clear existing unit assignments
    $stmt = $db->prepare("DELETE FROM employee_unit_sections WHERE emp_id = ?");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();

    // Insert new unit assignments
    if (!empty($unit_section_ids)) {
        $stmt = $db->prepare("INSERT INTO employee_unit_sections (emp_id, unit_id) VALUES (?, ?)");
        foreach ($unit_section_ids as $unit_id) {
            if ($unit_id !== '') { // Skip empty values
                $stmt->bind_param("ii", $emp_id, $unit_id);
                $stmt->execute();
            }
        }
    }

        $db->commit();
        
        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => 'Employee assignment updated successfully!'
        ];
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Error updating assignment: ' . $e->getMessage()
        ];
    }
    
    header("Location: emp.list.php?success=1");
    exit();
}

header("Location: emp.list.php?error=1");
exit();
?>