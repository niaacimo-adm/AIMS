<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once 'leave_functions.php';

$database = new Database();
$db = $database->getConnection();
$leaveFunctions = new LeaveFunctions();

// Handle manual balance update
if ($_POST && isset($_POST['update_balances'])) {
    $year = $_POST['year'] ?? date('Y');
    $updated_count = $leaveFunctions->manuallyUpdateAllLeaveBalances($year);
    $success = "Successfully updated leave balances for $updated_count employee-leave type combinations.";
}

// Handle annual reset
if ($_POST && isset($_POST['annual_reset'])) {
    $year = $_POST['year'] ?? date('Y');
    $reset_count = $leaveFunctions->annualLeaveBalanceReset($year);
    $success = "Successfully reset leave balances for $reset_count employee-leave type combinations for year $year.";
}

// Handle manual adjustment
if ($_POST && isset($_POST['adjust_balance'])) {
    $emp_id = $_POST['emp_id'];
    $leave_type_id = $_POST['leave_type_id'];
    $new_balance = $_POST['new_balance'];
    $year = $_POST['year'];
    $remarks = $_POST['remarks'];
    
    try {
        if ($leaveFunctions->manuallyAdjustLeaveBalance($emp_id, $leave_type_id, $new_balance, $year, $remarks)) {
            $success = "Successfully adjusted leave balance.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all active employees for dropdown - CORRECTED QUERY
// Show only employees who can apply for leave (excluding Job Order appointment status)
$employees_query = "SELECT e.emp_id, e.first_name, e.last_name, aps.status_name 
                   FROM employee e 
                   INNER JOIN appointment_status aps ON e.appointment_status_id = aps.appointment_id
                   WHERE e.employment_status_id = 1 
                   AND aps.status_name != 'Job Order'
                   ORDER BY e.first_name, e.last_name";

$employees_result = $db->query($employees_query);

// Get leave types
$leave_types = $leaveFunctions->getLeaveTypes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Balance Management - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Leave Balance Management</h1>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <i class="icon fas fa-check"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <i class="icon fas fa-ban"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Manual Balance Update -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Update All Leave Balances</h3>
                            </div>
                            <div class="card-body">
                                <p>This will initialize or update leave balances for all active employees based on default values.</p>
                                <form method="POST">
                                    <div class="form-group">
                                        <label for="year">Year</label>
                                        <input type="number" class="form-control" id="year" name="year" 
                                               value="<?php echo date('Y'); ?>" min="2020" max="2030" required>
                                    </div>
                                    <button type="submit" name="update_balances" class="btn btn-primary">
                                        <i class="fas fa-sync"></i> Update All Balances
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Annual Reset -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Annual Balance Reset</h3>
                            </div>
                            <div class="card-body">
                                <p>Reset all leave balances to default values for the specified year (typically done at year start).</p>
                                <form method="POST">
                                    <div class="form-group">
                                        <label for="reset_year">Year</label>
                                        <input type="number" class="form-control" id="reset_year" name="year" 
                                               value="<?php echo date('Y'); ?>" min="2020" max="2030" required>
                                    </div>
                                    <button type="submit" name="annual_reset" class="btn btn-warning">
                                        <i class="fas fa-redo"></i> Annual Reset
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manual Adjustment -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Manual Balance Adjustment</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="emp_id">Employee</label>
                                                <select class="form-control" id="emp_id" name="emp_id" required>
                                                    <option value="">Select Employee</option>
                                                    <?php if ($employees_result && $employees_result->num_rows > 0): ?>
                                                        <?php while ($employee = $employees_result->fetch_assoc()): ?>
                                                            <option value="<?php echo $employee['emp_id']; ?>">
                                                                <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <option value="">No eligible employees found</option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="leave_type_id">Leave Type</label>
                                                <select class="form-control" id="leave_type_id" name="leave_type_id" required>
                                                    <option value="">Select Leave Type</option>
                                                    <?php foreach ($leave_types as $type): ?>
                                                        <option value="<?php echo $type['leave_type_id']; ?>">
                                                            <?php echo $type['leave_name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="adjust_year">Year</label>
                                                <input type="number" class="form-control" id="adjust_year" name="year" 
                                                       value="<?php echo date('Y'); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="new_balance">New Balance</label>
                                                <input type="number" class="form-control" id="new_balance" name="new_balance" 
                                                       step="0.5" min="0" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="remarks">Remarks</label>
                                                <input type="text" class="form-control" id="remarks" name="remarks" 
                                                       placeholder="Reason for adjustment">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" name="adjust_balance" class="btn btn-info">
                                        <i class="fas fa-edit"></i> Adjust Balance
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>