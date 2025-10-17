<?php
session_start();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['equipment_id'])) {
    echo '<div class="alert alert-danger">Equipment ID is required</div>';
    exit;
}

$equipment_id = intval($_GET['equipment_id']);

$query = "SELECT e.*, c.category_name, 
          emp.first_name, emp.last_name, emp.picture
          FROM ict_equipment e 
          LEFT JOIN ict_equipment_categories c ON e.category_id = c.category_id 
          LEFT JOIN employee emp ON e.assigned_to = emp.emp_id 
          WHERE e.equipment_id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $equipment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $equipment = $result->fetch_assoc();
    ?>
    <div class="row">
        <div class="col-md-6">
            <p><strong>Equipment:</strong> <?= htmlspecialchars($equipment['equipment_name']) ?></p>
            <p><strong>Category:</strong> <?= htmlspecialchars($equipment['category_name']) ?></p>
            <p><strong>Asset Tag:</strong> <?= htmlspecialchars($equipment['asset_tag']) ?></p>
            <p><strong>Serial No:</strong> <?= htmlspecialchars($equipment['serial_number']) ?></p>
        </div>
        <div class="col-md-6">
            <p><strong>Brand/Model:</strong> <?= htmlspecialchars($equipment['brand'] . ' ' . $equipment['model']) ?></p>
            <p><strong>Condition:</strong> 
                <span class="badge badge-<?= 
                    $equipment['condition'] == 'Excellent' ? 'success' : 
                    ($equipment['condition'] == 'Good' ? 'primary' : 
                    ($equipment['condition'] == 'Fair' ? 'warning' : 'danger')) ?>">
                    <?= $equipment['condition'] ?>
                </span>
            </p>
            <p><strong>Status:</strong> 
                <span class="badge badge-<?= 
                    $equipment['status'] == 'Available' ? 'success' : 
                    ($equipment['status'] == 'Assigned' ? 'primary' : 
                    ($equipment['status'] == 'Under Maintenance' ? 'warning' : 
                    ($equipment['status'] == 'Retired' ? 'secondary' : 'danger'))) ?>">
                    <?= $equipment['status'] ?>
                </span>
            </p>
        </div>
    </div>
    <?php
} else {
    echo '<div class="alert alert-danger">Equipment not found</div>';
}
?>