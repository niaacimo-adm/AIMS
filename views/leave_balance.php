<?php
// leave_balance.php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once 'leave_functions.php';

// Check if user is logged in
if (!isset($_SESSION['emp_id'])) {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$leaveFunctions = new LeaveFunctions();
$emp_id = $_SESSION['emp_id'];

// Get employee details
$employee_query = "SELECT e.*, s.section_name, p.position_name 
                  FROM employee e 
                  LEFT JOIN section s ON e.section_id = s.section_id 
                  LEFT JOIN position p ON e.position_id = p.position_id 
                  WHERE e.emp_id = ?";
$stmt = $db->prepare($employee_query);
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

$current_year = date('Y');

// Get all leave types with their balances using a direct query
$balance_query = "
    SELECT 
        lt.leave_type_id,
        lt.leave_name,
        lt.leave_code,
        lt.max_days_per_year,
        COALESCE(lb.total_credits, lt.max_days_per_year) as total_credits,
        COALESCE(lb.used_credits, 0) as used_credits,
        COALESCE(lb.balance, lt.max_days_per_year) as balance
    FROM leave_types lt
    LEFT JOIN leave_balances lb ON lt.leave_type_id = lb.leave_type_id 
                               AND lb.emp_id = ? 
                               AND lb.year = ?
    WHERE lt.is_active = 1
    ORDER BY lt.leave_name
";

$stmt = $db->prepare($balance_query);
$stmt->bind_param("ii", $emp_id, $current_year);
$stmt->execute();
$leave_balances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// If the query fails or returns empty, use default values
if (empty($leave_balances)) {
    // Get just the leave types and use max_days_per_year as default credits
    $types_query = "SELECT leave_type_id, leave_name, leave_code, max_days_per_year 
                   FROM leave_types 
                   WHERE is_active = 1 
                   ORDER BY leave_name";
    $types_result = $db->query($types_query);
    $leave_balances = [];
    
    while ($type = $types_result->fetch_assoc()) {
        $default_credits = $type['max_days_per_year'] ?? $this->getDefaultCredits($type['leave_code']);
        $leave_balances[] = [
            'leave_name' => $type['leave_name'],
            'leave_code' => $type['leave_code'],
            'total_credits' => $default_credits,
            'used_credits' => 0,
            'balance' => $default_credits
        ];
    }
}

// Helper function for default credits
function getDefaultCredits($leave_code) {
    $defaults = [
        'VL' => 15.0, 'SL' => 15.0, 'MFL' => 5.0, 'MATL' => 105.0,
        'PATL' => 7.0, 'SPL' => 3.0, 'SOLO' => 7.0, 'STUL' => 10.0,
        'VAWC' => 10.0, 'REHAB' => 30.0, 'WOMEN' => 2.0, 'CALAMITY' => 5.0,
        'TERMINAL' => 0.0, 'ADOPT' => 30.0
    ];
    return $defaults[$leave_code] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Leave Balances - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .balance-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .balance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .balance-positive { border-left-color: #28a745; }
        .balance-warning { border-left-color: #ffc107; }
        .balance-danger { border-left-color: #dc3545; }
        .balance-number {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .progress {
            height: 8px;
        }
    </style>
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
                        <h1 class="m-0">My Leave Balances</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Leave Balances</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <!-- Employee Info Card -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Employee Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Name:</strong> <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Position:</strong> <?php echo $employee['position_name'] ?? 'N/A'; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Section:</strong> <?php echo $employee['section_name'] ?? 'N/A'; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Year:</strong> <?php echo $current_year; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                    <!-- Leave Balances -->
                <div class="row">
                    <?php if (empty($leave_balances)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <h5><i class="icon fas fa-info"></i> No Leave Balances Found</h5>
                                <p>Your leave balances for <?php echo $current_year; ?> have not been initialized yet. Please contact HR.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($leave_balances as $balance): ?>
                            <?php
                            // Handle division by zero for leave types with no credits
                            $total_credits = floatval($balance['total_credits']);
                            $balance_value = floatval($balance['balance']);
                            
                            if ($total_credits > 0) {
                                $percentage = ($balance_value / $total_credits) * 100;
                            } else {
                                $percentage = 0;
                            }
                            
                            // Determine card color based on balance percentage
                            if ($total_credits == 0) {
                                $card_class = 'border-left-secondary';
                            } elseif ($percentage >= 50) {
                                $card_class = 'balance-positive';
                            } elseif ($percentage >= 25) {
                                $card_class = 'balance-warning';
                            } else {
                                $card_class = 'balance-danger';
                            }
                            ?>
                            <div class="col-md-3 mb-3">
                                <div class="card balance-card <?php echo $card_class; ?>">
                                    <div class="card-header">
                                        <h3 class="card-title mb-0"><?php echo htmlspecialchars($balance['leave_name']); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="text-center mb-3">
                                            <div class="balance-number 
                                                <?php echo $balance['balance'] > 0 ? 'text-success' : ($balance['balance'] == 0 ? 'text-secondary' : 'text-danger'); ?>">
                                                <?php echo number_format($balance['balance'], 1); ?>
                                            </div>
                                            <small class="text-muted">days remaining</small>
                                        </div>
                                        
                                        <?php if ($total_credits > 0): ?>
                                        <div class="progress mb-2">
                                            <div class="progress-bar 
                                                <?php echo $percentage >= 50 ? 'bg-success' : ($percentage >= 25 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                role="progressbar" 
                                                style="width: <?php echo $percentage; ?>%"
                                                aria-valuenow="<?php echo $percentage; ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-secondary" 
                                                role="progressbar" 
                                                style="width: 100%"
                                                aria-valuenow="100" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small class="text-muted">Total</small>
                                                <div><strong><?php echo $balance['total_credits']; ?></strong></div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Used</small>
                                                <div><strong><?php echo $balance['used_credits']; ?></strong></div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Remaining</small>
                                                <div><strong><?php echo $balance['balance']; ?></strong></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Leave Usage Statistics -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Leave Usage Summary - <?php echo $current_year; ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Leave Type</th>
                                                <th>Total Credits</th>
                                                <th>Used Credits</th>
                                                <th>Remaining Balance</th>
                                                <th>Usage Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($leave_balances)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No leave balance data available</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($leave_balances as $balance): ?>
                                                    <?php
                                                    $percentage_used = $balance['total_credits'] > 0 ? 
                                                        ($balance['used_credits'] / $balance['total_credits']) * 100 : 0;
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo $balance['leave_name']; ?></strong>
                                                            <br><small class="text-muted"><?php echo $balance['leave_code']; ?></small>
                                                        </td>
                                                        <td><?php echo $balance['total_credits']; ?> days</td>
                                                        <td><?php echo $balance['used_credits']; ?> days</td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $balance['balance'] > 0 ? 'success' : 'danger'; ?>">
                                                                <?php echo $balance['balance']; ?> days
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar <?php echo $percentage_used > 80 ? 'bg-danger' : ($percentage_used > 50 ? 'bg-warning' : 'bg-success'); ?>" 
                                                                     role="progressbar" 
                                                                     style="width: <?php echo $percentage_used; ?>%"
                                                                     aria-valuenow="<?php echo $percentage_used; ?>" 
                                                                     aria-valuemin="0" 
                                                                     aria-valuemax="100">
                                                                    <?php echo number_format($percentage_used, 1); ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>