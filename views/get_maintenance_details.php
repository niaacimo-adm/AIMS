<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!isset($_GET['maintenance_id'])) {
    echo '<div class="alert alert-danger">Maintenance ID is required</div>';
    exit();
}

$database = new Database();
$db = $database->getConnection();

$maintenance_id = $_GET['maintenance_id'];

// Get maintenance details
$query = "SELECT m.*, 
                 e.equipment_name, e.asset_tag, e.serial_number, e.specifications,
                 c.category_name,
                 emp.first_name as reporter_first, emp.last_name as reporter_last,
                 tech.first_name as tech_first, tech.last_name as tech_last,
                 creator.first_name as creator_first, creator.last_name as creator_last
          FROM ict_maintenance m
          LEFT JOIN ict_equipment e ON m.equipment_id = e.equipment_id
          LEFT JOIN ict_equipment_categories c ON e.category_id = c.category_id
          LEFT JOIN employee emp ON m.reported_by = emp.emp_id
          LEFT JOIN employee tech ON m.assigned_technician = tech.emp_id
          LEFT JOIN employee creator ON m.reported_by = creator.emp_id
          WHERE m.maintenance_id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $maintenance_id);
$stmt->execute();
$result = $stmt->get_result();
$maintenance = $result->fetch_assoc();

if (!$maintenance) {
    echo '<div class="alert alert-danger">Maintenance request not found</div>';
    exit();
}

// Get maintenance notes
$notes_query = "SELECT mn.*, emp.first_name, emp.last_name 
                FROM ict_maintenance_notes mn 
                LEFT JOIN employee emp ON mn.added_by = emp.emp_id 
                WHERE mn.maintenance_id = ? 
                ORDER BY mn.added_date DESC";
$notes_stmt = $db->prepare($notes_query);
$notes_stmt->bind_param("i", $maintenance_id);
$notes_stmt->execute();
$notes_result = $notes_stmt->get_result();
$notes = $notes_result->fetch_all(MYSQLI_ASSOC);
?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info">
                <h6 class="card-title">Maintenance Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>Maintenance ID:</strong></td>
                        <td>#<?= $maintenance['maintenance_id'] ?></td>
                    </tr>
                    <tr>
                        <td><strong>Equipment:</strong></td>
                        <td><?= htmlspecialchars($maintenance['equipment_name']) ?> (<?= htmlspecialchars($maintenance['asset_tag']) ?>)</td>
                    </tr>
                    <tr>
                        <td><strong>Category:</strong></td>
                        <td><?= htmlspecialchars($maintenance['category_name']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Serial Number:</strong></td>
                        <td><?= htmlspecialchars($maintenance['serial_number']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Issue Type:</strong></td>
                        <td><?= htmlspecialchars($maintenance['issue_type']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Priority:</strong></td>
                        <td>
                            <span class="badge badge-<?= 
                                $maintenance['priority'] == 'Low' ? 'secondary' : 
                                ($maintenance['priority'] == 'Medium' ? 'info' : 
                                ($maintenance['priority'] == 'High' ? 'warning' : 'danger')) ?>">
                                <?= $maintenance['priority'] ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info">
                <h6 class="card-title">Status & Assignment</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="badge badge-<?= 
                                $maintenance['status'] == 'Completed' ? 'success' : 
                                ($maintenance['status'] == 'In Progress' ? 'primary' : 
                                ($maintenance['status'] == 'Pending' ? 'warning' : 'secondary')) ?>">
                                <?= $maintenance['status'] ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Reported By:</strong></td>
                        <td><?= htmlspecialchars($maintenance['reporter_first'] . ' ' . $maintenance['reporter_last']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Report Date:</strong></td>
                        <td><?= date('M d, Y H:i', strtotime($maintenance['report_date'])) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Assigned Technician:</strong></td>
                        <td>
                            <?php if ($maintenance['assigned_technician']): ?>
                                <?= htmlspecialchars($maintenance['tech_first'] . ' ' . $maintenance['tech_last']) ?>
                                <?php if ($maintenance['assigned_date']): ?>
                                    <br><small class="text-muted">Assigned: <?= date('M d, Y', strtotime($maintenance['assigned_date'])) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($maintenance['resolved_date']): ?>
                    <tr>
                        <td><strong>Resolved Date:</strong></td>
                        <td><?= date('M d, Y H:i', strtotime($maintenance['resolved_date'])) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title">Issue Description</h6>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($maintenance['description'])) ?></p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($maintenance['resolution_notes'])): ?>
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title">Resolution Notes</h6>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($maintenance['resolution_notes'])) ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title">Maintenance Notes</h6>
            </div>
            <div class="card-body">
                <?php if (empty($notes)): ?>
                    <p class="text-muted">No notes added yet.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($notes as $note): ?>
                            <div class="time-label">
                                <span class="bg-info"><?= date('M d, Y', strtotime($note['added_date'])) ?></span>
                            </div>
                            <div>
                                <i class="fas fa-comment bg-blue"></i>
                                <div class="timeline-item">
                                    <span class="time"><i class="fas fa-clock"></i> <?= date('H:i', strtotime($note['added_date'])) ?></span>
                                    <h3 class="timeline-header">
                                        <a href="#"><?= htmlspecialchars($note['first_name'] . ' ' . $note['last_name']) ?></a> added a note
                                    </h3>
                                    <div class="timeline-body">
                                        <?= nl2br(htmlspecialchars($note['note'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>