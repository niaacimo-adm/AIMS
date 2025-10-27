<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['emp_id'])) {
    die('Unauthorized');
}

$database = new Database();
$db = $database->getConnection();
$equipment_id = $_GET['equipment_id'] ?? 0;

if ($equipment_id) {
    // Get maintenance history for this equipment
    $maintenance_query = "SELECT m.*, e.first_name, e.last_name, m.assigned_technician
                         FROM ict_maintenance m 
                         LEFT JOIN employee e ON m.reported_by = e.emp_id 
                         WHERE m.equipment_id = ? 
                         ORDER BY m.report_date DESC";
    $maintenance_stmt = $db->prepare($maintenance_query);
    $maintenance_stmt->bind_param("i", $equipment_id);
    $maintenance_stmt->execute();
    $maintenance_result = $maintenance_stmt->get_result();
    $maintenance_history = $maintenance_result->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($maintenance_history)): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date Reported</th>
                        <th>Issue Type</th>
                        <th>Description</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned Technician</th>
                        <th>Reported By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($maintenance_history as $record): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($record['report_date'])) ?></td>
                            <td><?= htmlspecialchars($record['issue_type']) ?></td>
                            <td><?= htmlspecialchars($record['description']) ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $record['priority'] == 'Low' ? 'secondary' : 
                                    ($record['priority'] == 'Medium' ? 'info' : 
                                    ($record['priority'] == 'High' ? 'warning' : 'danger')) ?>">
                                    <?= $record['priority'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= 
                                    $record['status'] == 'Completed' ? 'success' : 
                                    ($record['status'] == 'In Progress' ? 'primary' : 
                                    ($record['status'] == 'Pending' ? 'warning' : 'secondary')) ?>">
                                    <?= $record['status'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($record['assigned_technician'])): ?>
                                    <?php 
                                    // Get technician name if assigned
                                    $tech_query = "SELECT first_name, last_name FROM employee WHERE emp_id = ?";
                                    $tech_stmt = $db->prepare($tech_query);
                                    $tech_stmt->bind_param("i", $record['assigned_technician']);
                                    $tech_stmt->execute();
                                    $tech_result = $tech_stmt->get_result();
                                    $technician = $tech_result->fetch_assoc();
                                    echo htmlspecialchars($technician['first_name'] . ' ' . $technician['last_name']);
                                    ?>
                                <?php else: ?>
                                    <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <h5><i class="icon fas fa-info"></i> No Maintenance History</h5>
            No maintenance requests have been submitted for this equipment.
        </div>
    <?php endif;
} else {
    echo '<div class="alert alert-info"><h5><i class="icon fas fa-info"></i> No Equipment Selected</h5>Please select an equipment from the dropdown above to view maintenance history.</div>';
}
?>