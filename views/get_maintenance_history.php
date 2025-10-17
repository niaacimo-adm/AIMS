<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!isset($_SESSION['emp_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

$database = new Database();
$db = $database->getConnection();
$employee_id = $_SESSION['emp_id'];

$equipment_id = $_GET['equipment_id'] ?? null;

if (!$equipment_id) {
    echo '<div class="alert alert-danger">Equipment ID is required.</div>';
    exit();
}

// Verify the equipment belongs to the current employee
$verify_query = "SELECT equipment_id FROM ict_equipment WHERE equipment_id = ? AND assigned_to = ?";
$verify_stmt = $db->prepare($verify_query);
$verify_stmt->bind_param("ii", $equipment_id, $employee_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    echo '<div class="alert alert-danger">Equipment not found or access denied.</div>';
    exit();
}

// Get maintenance history
$query = "SELECT m.*, e.first_name, e.last_name 
          FROM ict_maintenance m 
          LEFT JOIN employee e ON m.reported_by = e.emp_id 
          WHERE m.equipment_id = ? 
          ORDER BY m.report_date DESC";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $equipment_id);
$stmt->execute();
$result = $stmt->get_result();
$maintenance_history = $result->fetch_all(MYSQLI_ASSOC);

if (empty($maintenance_history)) {
    echo '<div class="alert alert-info">
            <h5><i class="icon fas fa-info"></i> No Maintenance History</h5>
            No maintenance requests have been submitted for this equipment.
          </div>';
} else {
    echo '<div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date Reported</th>
                        <th>Issue Type</th>
                        <th>Description</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reported By</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($maintenance_history as $record) {
        echo '<tr>
                <td>' . date('M d, Y', strtotime($record['report_date'])) . '</td>
                <td>' . htmlspecialchars($record['issue_type']) . '</td>
                <td>' . htmlspecialchars($record['description']) . '</td>
                <td>
                    <span class="badge badge-' . 
                        ($record['priority'] == 'Low' ? 'secondary' : 
                        ($record['priority'] == 'Medium' ? 'info' : 
                        ($record['priority'] == 'High' ? 'warning' : 'danger'))) . '">
                        ' . $record['priority'] . '
                    </span>
                </td>
                <td>
                    <span class="badge badge-' . 
                        ($record['status'] == 'Completed' ? 'success' : 
                        ($record['status'] == 'In Progress' ? 'primary' : 
                        ($record['status'] == 'Pending' ? 'warning' : 'secondary'))) . '">
                        ' . $record['status'] . '
                    </span>
                </td>
                <td>' . htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) . '</td>
              </tr>';
    }
    
    echo '</tbody></table></div>';
}
?>