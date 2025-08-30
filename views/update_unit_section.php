<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_unit_section'])) {
    $unit_id = $_POST['unit_id'];
    $unit_name = trim($_POST['unit_name']);
    $head_emp_id = $_POST['head_emp_id'] ?: null;
    $unit_employees = $_POST['unit_employees'] ?? [];

    try {
        // Start transaction
        $db->begin_transaction();

        // First get the section_id for this unit
        $stmt = $db->prepare("SELECT section_id FROM unit_section WHERE unit_id = ?");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $unit_info = $result->fetch_assoc();
        $section_id = $unit_info['section_id'];

        if (!$section_id) {
            throw new Exception("Parent section not found for this unit");
        }

        // Update unit section
        $stmt = $db->prepare("UPDATE unit_section SET unit_name = ?, head_emp_id = ? WHERE unit_id = ?");
        if (!$stmt->execute([$unit_name, $head_emp_id, $unit_id])) {
            throw new Exception("Failed to update unit section");
        }

        // Update unit employees
        // First clear current assignments
        $stmt = $db->prepare("UPDATE employee SET unit_section_id = NULL WHERE unit_section_id = ?");
        if (!$stmt->execute([$unit_id])) {
            throw new Exception("Failed to clear current unit assignments");
        }

        // Then add new assignments
        if (!empty($unit_employees)) {
            $stmt = $db->prepare("UPDATE employee SET unit_section_id = ?, section_id = ? WHERE emp_id = ?");
            
            foreach ($unit_employees as $emp_id) {
                if (!$stmt->execute([$unit_id, $section_id, $emp_id])) {
                    throw new Exception("Failed to assign employee to unit and section");
                }
            }
        }

        $db->commit();
        
        $_SESSION['swal'] = [
            'type' => 'success',
            'title' => 'Success!',
            'text' => 'Unit section updated successfully!'
        ];
    } catch (Exception $e) {
        if (isset($db) && $db->in_transaction) {
            $db->rollback();
        }
        $_SESSION['swal'] = [
            'type' => 'error',
            'title' => 'Error!',
            'text' => $e->getMessage()
        ];
    }
    
    header("Location: sections.php");
    exit();
}
?>