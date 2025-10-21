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

// Get slip details
$query = "SELECT pls.*, e.first_name, e.last_name, e.middle_name, e.ext_name, 
                 e.position_id, p.position_name, s.section_name
          FROM personal_locator_slips pls
          JOIN employee e ON pls.employee_id = e.emp_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN section s ON e.section_id = s.section_id
          WHERE pls.id = ?";
          
$stmt = $db->prepare($query);
$stmt->bind_param("i", $slip_id);
$stmt->execute();
$slip = $stmt->get_result()->fetch_assoc();

if (!$slip) {
    die('Slip not found');
}

// Additional security check - ensure slip is pending
if ($slip['status'] !== 'pending') {
    die('Only pending slips can be edited');
}
?>

<form method="POST" action="" id="editSlipForm">
    <input type="hidden" name="slip_id" value="<?= $slip['id'] ?>">
    
    <div class="modal-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="font-weight-semibold">Date *</label>
                    <input type="date" class="form-control form-control-modern" name="date" 
                           value="<?= $slip['date'] ?>" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="font-weight-semibold">Leave Time *</label>
                    <input type="time" class="form-control form-control-modern" name="leave_time" 
                           value="<?= date('H:i', strtotime($slip['leave_time'])) ?>" required>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="font-weight-semibold">Purpose Type *</label>
                    <select class="form-control form-control-modern" name="purpose_type" required>
                        <option value="personal" <?= $slip['purpose_type'] == 'personal' ? 'selected' : '' ?>>Personal</option>
                        <option value="official" <?= $slip['purpose_type'] == 'official' ? 'selected' : '' ?>>Official Business</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="font-weight-semibold">Employee</label>
                    <input type="text" class="form-control form-control-modern" 
                           value="<?= htmlspecialchars($slip['first_name'] . ' ' . $slip['last_name']) ?>" 
                           readonly>
                    <small class="text-muted">Employee cannot be changed</small>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="font-weight-semibold">Purpose Details *</label>
                    <textarea class="form-control form-control-modern" name="purpose_details" 
                              rows="3" required><?= htmlspecialchars($slip['purpose_details']) ?></textarea>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="no_return" 
                               id="editNoReturnCheckbox" <?= $slip['no_return'] ? 'checked' : '' ?>>
                        <label class="form-check-label font-weight-semibold" for="editNoReturnCheckbox">
                            No Return Today
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group" id="editExpectedReturnGroup">
                    <label class="font-weight-semibold">Expected Return Time *</label>
                    <input type="time" class="form-control form-control-modern" name="expected_return" 
                           id="editExpectedReturnInput" 
                           value="<?= $slip['expected_return'] ? date('H:i', strtotime($slip['expected_return'])) : '' ?>">
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-modern btn-light" data-dismiss="modal">Cancel</button>
        <button type="submit" name="update_slip" class="btn btn-modern btn-primary">
            <i class="fas fa-save mr-2"></i>Update Slip
        </button>
    </div>
</form>

<script>
// Initialize no return functionality
$('#editNoReturnCheckbox').change(function() {
    if ($(this).is(':checked')) {
        $('#editExpectedReturnGroup').hide();
        $('#editExpectedReturnInput').prop('required', false);
    } else {
        $('#editExpectedReturnGroup').show();
        $('#editExpectedReturnInput').prop('required', true);
    }
});

// Set initial state
if ($('#editNoReturnCheckbox').is(':checked')) {
    $('#editExpectedReturnGroup').hide();
    $('#editExpectedReturnInput').prop('required', false);
}
</script>