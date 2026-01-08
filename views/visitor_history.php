<?php
// visitor_history.php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set theme for this module
$_SESSION['current_theme'] = 'queue';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get all employees for "Person to Visit" dropdown
$employees = [];
$employee_query = "SELECT emp_id, first_name, last_name, position_id, section_id 
                   FROM employee 
                   WHERE employment_status_id = 1 
                   ORDER BY last_name, first_name";
$employee_result = $db->query($employee_query);
if ($employee_result) {
    $employees = $employee_result->fetch_all(MYSQLI_ASSOC);
}

// Get all sections for "Department" dropdown
$sections = [];
$section_query = "SELECT section_id, section_name, section_code 
                  FROM section 
                  WHERE office_id = 1 
                  ORDER BY section_name";
$section_result = $db->query($section_query);
if ($section_result) {
    $sections = $section_result->fetch_all(MYSQLI_ASSOC);
}

// Get all units
$units = [];
$unit_query = "SELECT unit_id, unit_name, unit_code, section_id 
               FROM unit_section 
               ORDER BY unit_name";
$unit_result = $db->query($unit_query);
if ($unit_result) {
    $units = $unit_result->fetch_all(MYSQLI_ASSOC);
}

// Handle search/filter parameters
$search = isset($_GET['search']) ? $db->real_escape_string($_GET['search']) : '';
$status = isset($_GET['status']) ? $db->real_escape_string($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $db->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $db->real_escape_string($_GET['date_to']) : '';

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(vq.visitor_name LIKE '%$search%' OR 
                          vq.company LIKE '%$search%' OR 
                          vq.queue_number LIKE '%$search%' OR 
                          vq.person_to_visit LIKE '%$search%')";
}

if (!empty($status) && $status != 'all') {
    $where_conditions[] = "vq.status = '$status'";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(vq.time_in) >= '$date_from'";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(vq.time_in) <= '$date_to'";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get visitor queue history with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "SELECT COUNT(*) as total FROM visitor_queue vq $where_clause";
$count_result = $db->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get visitor queue records
$query = "SELECT vq.*, 
          e.first_name as emp_firstname, e.last_name as emp_lastname,
          s.section_name,
          us.unit_name
          FROM visitor_queue vq
          LEFT JOIN employee e ON vq.created_by = e.emp_id
          LEFT JOIN section s ON vq.section_id = s.section_id
          LEFT JOIN unit_section us ON vq.unit_id = us.unit_id
          $where_clause
          ORDER BY vq.time_in DESC
          LIMIT $limit OFFSET $offset";

$result = $db->query($query);
$visitor_history = [];
if ($result) {
    $visitor_history = $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get status badge class
function getStatusBadge($status) {
    $badge_classes = [
        'waiting' => 'badge-warning',
        'called' => 'badge-info',
        'serving' => 'badge-primary',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    
    return $badge_classes[$status] ?? 'badge-secondary';
}

// Function to get status text
function getStatusText($status) {
    $status_text = [
        'waiting' => 'Waiting',
        'called' => 'Called',
        'serving' => 'Serving',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];
    
    return $status_text[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visitor Queue History | NIA-ACIMO AIMS</title>

    <?php include '../includes/header.php'; ?>

    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .queue-number {
            font-weight: bold;
            color: #007bff;
        }
        .filter-card {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <?php include '../includes/mainheader.php'; ?>
        <?php include '../includes/sidebar_queue.php'; ?>

        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Visitor Queue History</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                                <li class="breadcrumb-item"><a href="queue.php">Queue Management</a></li>
                                <li class="breadcrumb-item active">Visitor History</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <!-- Filter Section -->
                    <div class="card filter-card mb-3">
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="search">Search</label>
                                            <input type="text" class="form-control" id="search" name="search" 
                                                   placeholder="Name, Company, Queue #" value="<?php echo htmlspecialchars($search); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="status">Status</label>
                                            <select class="form-control" id="status" name="status">
                                                <option value="all">All Status</option>
                                                <option value="waiting" <?php echo $status == 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                                                <option value="called" <?php echo $status == 'called' ? 'selected' : ''; ?>>Called</option>
                                                <option value="serving" <?php echo $status == 'serving' ? 'selected' : ''; ?>>Serving</option>
                                                <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="date_range">Date Range</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">
                                                        <i class="far fa-calendar-alt"></i>
                                                    </span>
                                                </div>
                                                <input type="text" class="form-control float-right" id="date_range" name="date_range">
                                                <input type="hidden" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
                                                <input type="hidden" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <button type="submit" class="btn btn-primary btn-block">
                                                <i class="fas fa-filter"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <a href="visitor_history.php" class="btn btn-secondary btn-block">
                                                <i class="fas fa-redo"></i> Reset
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row mb-3">
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <?php 
                                    $total_query = "SELECT COUNT(*) as total FROM visitor_queue";
                                    $total_result = $db->query($total_query);
                                    $total = $total_result->fetch_assoc()['total'];
                                    ?>
                                    <h3><?php echo $total; ?></h3>
                                    <p>Total Visitors</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <?php 
                                    $completed_query = "SELECT COUNT(*) as total FROM visitor_queue WHERE status = 'completed'";
                                    $completed_result = $db->query($completed_query);
                                    $completed = $completed_result->fetch_assoc()['total'];
                                    ?>
                                    <h3><?php echo $completed; ?></h3>
                                    <p>Completed</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <?php 
                                    $waiting_query = "SELECT COUNT(*) as total FROM visitor_queue WHERE status = 'waiting'";
                                    $waiting_result = $db->query($waiting_query);
                                    $waiting = $waiting_result->fetch_assoc()['total'];
                                    ?>
                                    <h3><?php echo $waiting; ?></h3>
                                    <p>Waiting</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <?php 
                                    $cancelled_query = "SELECT COUNT(*) as total FROM visitor_queue WHERE status = 'cancelled'";
                                    $cancelled_result = $db->query($cancelled_query);
                                    $cancelled = $cancelled_result->fetch_assoc()['total'];
                                    ?>
                                    <h3><?php echo $cancelled; ?></h3>
                                    <p>Cancelled</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visitor History Table -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Visitor Queue History</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Queue #</th>
                                            <th>Visitor Name</th>
                                            <th>Company</th>
                                            <th>Purpose</th>
                                            <th>Person to Visit</th>
                                            <th>Department/Unit</th>
                                            <th>Time In</th>
                                            <th>Time Called</th>
                                            <th>Time Completed</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($visitor_history)): ?>
                                            <tr>
                                                <td colspan="12" class="text-center">No records found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($visitor_history as $record): ?>
                                                <tr>
                                                    <td>
                                                        <span class="queue-number"><?php echo htmlspecialchars($record['queue_number']); ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($record['visitor_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['company'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($record['purpose']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['person_to_visit']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $department = $record['section_name'] ?? $record['section_name'] ?? 'N/A';
                                                        $unit = $record['unit_name'] ?? $record['unit_name'] ?? '';
                                                        echo htmlspecialchars($department);
                                                        if (!empty($unit)) {
                                                            echo '<br><small class="text-muted">' . htmlspecialchars($unit) . '</small>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo date('M d, Y h:i A', strtotime($record['time_in'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        if ($record['time_called']) {
                                                            echo date('M d, Y h:i A', strtotime($record['time_called']));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if ($record['time_completed']) {
                                                            echo date('M d, Y h:i A', strtotime($record['time_completed']));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo getStatusBadge($record['status']); ?> status-badge">
                                                            <?php echo getStatusText($record['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $created_by = $record['emp_firstname'] && $record['emp_lastname'] 
                                                            ? $record['emp_firstname'] . ' ' . $record['emp_lastname']
                                                            : 'System';
                                                        echo htmlspecialchars($created_by);
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-info btn-sm" 
                                                                    onclick="viewDetails(<?php echo $record['id']; ?>)"
                                                                    title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <?php if ($record['status'] == 'waiting' || $record['status'] == 'called'): ?>
                                                                <button type="button" class="btn btn-warning btn-sm" 
                                                                        onclick="editQueue(<?php echo $record['id']; ?>)"
                                                                        title="Edit">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($record['status'] == 'waiting'): ?>
                                                                <button type="button" class="btn btn-danger btn-sm" 
                                                                        onclick="cancelQueue(<?php echo $record['id']; ?>, '<?php echo $record['queue_number']; ?>')"
                                                                        title="Cancel">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-3">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) && $status != 'all' ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
                                                Previous
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) && $status != 'all' ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) && $status != 'all' ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
                                                Next
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <div class="row">
                                <div class="col-md-6">
                                    <p>Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries</p>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                        <i class="fas fa-file-excel"></i> Export to Excel
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="printReport()">
                                        <i class="fas fa-print"></i> Print Report
                                    </button>
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

    <script>

        // View details function
        function viewDetails(id) {
            window.location.href = 'view_visitor_details.php?id=' + id;
        }

        // Edit queue function
        function editQueue(id) {
            window.location.href = 'edit_queue.php?id=' + id;
        }

        // Cancel queue function
        function cancelQueue(id, queueNumber) {
            Swal.fire({
                title: 'Cancel Queue?',
                text: 'Are you sure you want to cancel queue number ' + queueNumber + '?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, cancel it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'cancel_queue.php',
                        type: 'POST',
                        data: {
                            id: id,
                            queue_number: queueNumber
                        },
                        success: function(response) {
                            const result = JSON.parse(response);
                            if (result.success) {
                                Swal.fire(
                                    'Cancelled!',
                                    'Queue number ' + queueNumber + ' has been cancelled.',
                                    'success'
                                ).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire(
                                    'Error!',
                                    result.message || 'Failed to cancel queue.',
                                    'error'
                                );
                            }
                        },
                        error: function() {
                            Swal.fire(
                                'Error!',
                                'An error occurred while processing your request.',
                                'error'
                            );
                        }
                    });
                }
            });
        }

        // Export to Excel function
        function exportToExcel() {
            // Get current filter parameters
            const params = new URLSearchParams(window.location.search);
            params.append('export', 'excel');
            
            window.location.href = 'export_visitor_history.php?' + params.toString();
        }

        // Print report function
        function printReport() {
            // Get current filter parameters
            const params = new URLSearchParams(window.location.search);
            params.append('print', 'true');
            
            const printWindow = window.open('print_visitor_history.php?' + params.toString(), '_blank');
            printWindow.focus();
        }

        // Initialize DataTable
        $(document).ready(function() {
            $('table').DataTable({
                "paging": false,
                "lengthChange": false,
                "searching": false,
                "ordering": true,
                "info": false,
                "autoWidth": false,
                "responsive": true,
                "order": [[6, 'desc']] // Sort by time_in descending by default
            });
        });
    </script>
</body>

</html>