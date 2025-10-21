<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['id'])) {
    die('Slip ID is required');
}

$database = new Database();
$db = $database->getConnection();

$slip_id = $_GET['id'];

// Get slip details with additional employee information
$query = "SELECT pls.*, e.first_name, e.last_name, e.middle_name, e.ext_name, 
                 e.position_id, p.position_name, s.section_name, o.office_name,
                 app.first_name as approver_first, app.last_name as approver_last
          FROM personal_locator_slips pls
          JOIN employee e ON pls.employee_id = e.emp_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN section s ON e.section_id = s.section_id
          LEFT JOIN office o ON e.office_id = o.office_id
          LEFT JOIN employee app ON pls.approved_by = app.emp_id
          WHERE pls.id = ?";
          
$stmt = $db->prepare($query);
$stmt->bind_param("i", $slip_id);
$stmt->execute();
$slip = $stmt->get_result()->fetch_assoc();

if (!$slip) {
    die('Slip not found');
}

// Check if current user has permission to manage employees (admin)
$is_admin = hasPermission('manage_employees');
// Check if current user is the owner of the slip
$is_owner = ($_SESSION['emp_id'] == $slip['employee_id']);

// Build employee full name
$employee_name = $slip['first_name'] . ' ' . $slip['last_name'];
if ($slip['middle_name']) {
    $employee_name .= ' ' . $slip['middle_name'];
}
if ($slip['ext_name']) {
    $employee_name .= ' ' . $slip['ext_name'];
}
?>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Employee Name</label>
            <p class="form-control-static"><?= htmlspecialchars($employee_name) ?></p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Position</label>
            <p class="form-control-static"><?= htmlspecialchars($slip['position_name'] ?? 'N/A') ?></p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Section/Office</label>
            <p class="form-control-static">
                <?= htmlspecialchars($slip['section_name'] ?? 'N/A') ?>
                <?php if ($slip['office_name']): ?>
                    / <?= htmlspecialchars($slip['office_name']) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Date</label>
            <p class="form-control-static"><?= date('F j, Y', strtotime($slip['date'])) ?></p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Leave Time</label>
            <p class="form-control-static"><?= date('g:i A', strtotime($slip['leave_time'])) ?></p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Purpose Type</label>
            <p class="form-control-static"><?= ucfirst($slip['purpose_type']) ?></p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label class="font-weight-semibold">Purpose Details</label>
            <div class="p-3 bg-light rounded">
                <?= nl2br(htmlspecialchars($slip['purpose_details'])) ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Expected Return</label>
            <p class="form-control-static">
                <?php if ($slip['no_return']): ?>
                    <span class="badge badge-secondary">No Return Today</span>
                <?php else: ?>
                    <?= date('g:i A', strtotime($slip['expected_return'])) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Status</label>
            <p class="form-control-static">
                <span class="badge badge-<?= 
                    $slip['status'] == 'approved' ? 'success' : 
                    ($slip['status'] == 'rejected' ? 'danger' : 'warning')
                ?>">
                    <?= ucfirst($slip['status']) ?>
                </span>
            </p>
        </div>
    </div>
</div>

<?php if ($slip['approved_by']): ?>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Approved/Rejected By</label>
            <p class="form-control-static">
                <?= htmlspecialchars($slip['approver_first'] . ' ' . $slip['approver_last']) ?>
            </p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Action Date</label>
            <p class="form-control-static">
                <?= date('M j, Y g:i A', strtotime($slip['approved_at'])) ?>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="font-weight-semibold">Submitted On</label>
            <p class="form-control-static">
                <?= date('M j, Y g:i A', strtotime($slip['created_at'])) ?>
            </p>
        </div>
    </div>
</div>

<!-- Action Buttons - Only show for admins in monitoring page, not for employees in personal slip page -->
<?php if ($is_admin && $slip['status'] == 'pending'): ?>
<div class="row mt-4">
    <div class="col-md-12">
        <div class="text-right">
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="slip_id" value="<?= $slip['id'] ?>">
                <button type="submit" name="approve_slip" class="btn btn-success btn-modern">
                    <i class="fas fa-check mr-2"></i>Approve
                </button>
            </form>
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="slip_id" value="<?= $slip['id'] ?>">
                <button type="submit" name="reject_slip" class="btn btn-danger btn-modern ml-2">
                    <i class="fas fa-times mr-2"></i>Reject
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.form-control-static {
    padding: 8px 0;
    margin-bottom: 0;
    font-size: 1rem;
    border-bottom: 1px solid #e9ecef;
    min-height: auto;
}
.bg-light {
    background-color: #f8f9fa !important;
}
</style>