<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Create database instance and get connection
$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = ''; // Default empty role
}
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_attachment'])) {
        // Update attachment status
        $monitoring_id = $_POST['monitoring_id'];
        $status = $_POST['status'];
        $filing_status = $_POST['filing_status'];
        $submission_date = !empty($_POST['submission_date']) ? $_POST['submission_date'] : null;
        $remarks = $_POST['remarks'];
        
        $query = "UPDATE attachments_monitoring 
                 SET status = ?, filing_status = ?, submission_date = ?, remarks = ?, updated_at = NOW() 
                 WHERE monitoring_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssssi", $status, $filing_status, $submission_date, $remarks, $monitoring_id);
        
        if ($stmt->execute()) {
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Attachment status updated successfully!'
            ];
        } else {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => 'Failed to update attachment status.'
            ];
        }
        
        // Redirect to prevent form resubmission
        header("Location: attachments_monitoring.php");
        exit();
        
    } elseif (isset($_POST['add_record'])) {
        // Add new monitoring record
        $emp_id = $_POST['emp_id'];
        $period_start = $_POST['period_start'];
        $period_end = $_POST['period_end'];
        $payroll_period = date('M j', strtotime($period_start)) . ' - ' . date('M j', strtotime($period_end));
        $status = $_POST['status'];
        $filing_status = $_POST['filing_status'];
        $submission_date = !empty($_POST['submission_date']) ? $_POST['submission_date'] : null;
        $remarks = $_POST['remarks'];
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle duplicates gracefully
        $query = "INSERT INTO attachments_monitoring 
                (emp_id, payroll_period, period_start, period_end, status, filing_status, submission_date, remarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                filing_status = VALUES(filing_status),
                submission_date = VALUES(submission_date),
                remarks = VALUES(remarks),
                period_start = VALUES(period_start),
                period_end = VALUES(period_end),
                updated_at = NOW()";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("isssssss", $emp_id, $payroll_period, $period_start, $period_end, $status, $filing_status, $submission_date, $remarks);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // New record inserted or existing record updated
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'Monitoring record saved successfully!'
                ];
            } else {
                $_SESSION['toast'] = [
                    'type' => 'warning',
                    'message' => 'No changes made to the record.'
                ];
            }
        } else {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => 'Failed to save monitoring record.'
            ];
        }
        
        // Redirect to prevent form resubmission
        header("Location: attachments_monitoring.php");
        exit();
        
    } elseif (isset($_POST['bulk_delete'])) {
        // Handle bulk delete
        try {
            if (isset($_POST['delete_all']) && $_POST['delete_all'] === '1') {
                // Delete all records
                $query = "DELETE FROM attachments_monitoring";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'message' => 'All records deleted successfully!'
                ];
            } else {
                // Delete specific records
                $recordIds = $_POST['record_ids'] ?? [];
                if (empty($recordIds)) {
                    throw new Exception("No records selected for deletion.");
                }
                
                $placeholders = str_repeat('?,', count($recordIds) - 1) . '?';
                $query = "DELETE FROM attachments_monitoring WHERE monitoring_id IN ($placeholders)";
                $stmt = $db->prepare($query);
                
                // Bind parameters
                $types = str_repeat('i', count($recordIds));
                $stmt->bind_param($types, ...$recordIds);
                
                if ($stmt->execute()) {
                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'message' => 'Selected records deleted successfully!'
                    ];
                } else {
                    throw new Exception("Failed to delete records.");
                }
            }
        } catch (Exception $e) {
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => "Delete failed: " . $e->getMessage()
            ];
        }
        
        // Redirect to prevent form resubmission
        header("Location: attachments_monitoring.php");
        exit();
    }
}

// Get target appointment status IDs
$targetStatuses = [5, 6, 8, 9, 10, 11, 39]; // Casual - SP, Casual - PC, Regular, CARP Co-Terminus, Permanent, Temp-Regular, CARP-Contractual

// Fetch employees with target appointment statuses
$query = "SELECT 
    e.emp_id, 
    e.id_number,
    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
    e.email,
    e.phone_number,
    ap.status_name as appointment_status,
    ap.color as appointment_color,
    p.position_name,
    o.office_name,
    s.section_name
FROM employee e
LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
LEFT JOIN position p ON e.position_id = p.position_id
LEFT JOIN office o ON e.office_id = o.office_id
LEFT JOIN section s ON e.section_id = s.section_id
WHERE e.appointment_status_id IN (" . implode(',', $targetStatuses) . ")
ORDER BY e.last_name, e.first_name";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);

// Fetch existing monitoring records
$monitoringQuery = "SELECT 
    am.*,
    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
    e.id_number
FROM attachments_monitoring am
LEFT JOIN employee e ON am.emp_id = e.emp_id
ORDER BY am.payroll_period DESC, am.updated_at DESC";
$monitoringStmt = $db->prepare($monitoringQuery);
$monitoringStmt->execute();
$monitoringResult = $monitoringStmt->get_result();
$monitoringRecords = $monitoringResult->fetch_all(MYSQLI_ASSOC);

// Get unique payroll periods for filter
$periodsQuery = "SELECT DISTINCT payroll_period FROM attachments_monitoring ORDER BY payroll_period DESC";
$periodsStmt = $db->prepare($periodsQuery);
$periodsStmt->execute();
$periodsResult = $periodsStmt->get_result();
$payrollPeriods = $periodsResult->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attachments Monitoring</title>
  <?php include '../includes/header.php'; ?>
  
  <style>
    .status-badge {
        font-size: 0.8rem;
        padding: 4px 8px;
        border-radius: 4px;
    }
    .status-complete { background-color: #28a745; color: white; }
    .status-incomplete { background-color: #ffc107; color: black; }
    .status-complete-late { background-color: #fd7e14; color: white; }
    .status-not-submitted { background-color: #dc3545; color: white; }
    
    .filing-badge {
        font-size: 0.8rem;
        padding: 4px 8px;
        border-radius: 4px;
    }
    .filing-forwarded { background-color: #17a2b8; color: white; }
    .filing-not-forwarded { background-color: #6c757d; color: white; }
    
    .monitoring-table th {
        background-color: #f8f9fa;
        position: sticky;
        top: 0;
    }
    
    .table-container {
        max-height: 600px;
        overflow-y: auto;
    }
    
    .appointment-badge {
        font-size: 0.75rem;
        padding: 3px 6px;
        margin: 1px;
    }
    .export-dropdown {
    min-width: 250px;
    }

    .export-period {
        font-size: 0.9rem;
        padding: 8px 15px;
    }

    .export-period:hover {
        background-color: #f8f9fa;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar.php'; ?>
  
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Attachments Monitoring</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Attachments Monitoring</li>
            </ol>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row">
          <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
              <div class="inner">
                <h3><?= count($employees) ?></h3>
                <p>Total Employees</p>
              </div>
              <div class="icon">
                <i class="fas fa-users"></i>
              </div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
              <div class="inner">
                <h3>
                  <?= count(array_filter($monitoringRecords, function($record) {
                    return $record['status'] === 'COMPLETE';
                  })) ?>
                </h3>
                <p>Complete Submissions</p>
              </div>
              <div class="icon">
                <i class="fas fa-check-circle"></i>
              </div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
              <div class="inner">
                <h3>
                  <?= count(array_filter($monitoringRecords, function($record) {
                    return $record['status'] === 'COMPLETE AND LATE';
                  })) ?>
                </h3>
                <p>Complete & Late</p>
              </div>
              <div class="icon">
                <i class="fas fa-clock"></i>
              </div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
              <div class="inner">
                <h3>
                  <?= count(array_filter($monitoringRecords, function($record) {
                    return $record['status'] === 'NOT SUBMITTED';
                  })) ?>
                </h3>
                <p>Not Submitted</p>
              </div>
              <div class="icon">
                <i class="fas fa-times-circle"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Attachments Monitoring</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addRecordModal">
                        <i class="fas fa-plus"></i> Add Record
                    </button>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#importModal">
                        <i class="fas fa-file-import"></i> Import Excel
                    </button>
                    
                    <!-- Export Dropdown -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-info btn-sm" id="exportBtn">
                            <i class="fas fa-file-export"></i> Export All Excel
                        </button>
                        <button type="button" class="btn btn-info btn-sm dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="sr-only">Toggle Dropdown</span>
                        </button>
                        <div class="dropdown-menu">
                            <h6 class="dropdown-header">Export by Payroll Period</h6>
                            <?php foreach ($payrollPeriods as $period): ?>
                                <a class="dropdown-item export-period" href="#" data-period="<?= htmlspecialchars($period['payroll_period']) ?>">
                                    <i class="fas fa-download mr-2"></i><?= htmlspecialchars($period['payroll_period']) ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($payrollPeriods)): ?>
                                <a class="dropdown-item disabled" href="#">No periods available</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Add Template Download Button -->
                    <button type="button" class="btn btn-warning btn-sm" id="templateBtn">
                        <i class="fas fa-download"></i> Download Template
                    </button>
                </div>
              </div>
              <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                  <div class="col-md-3">
                    <select id="periodFilter" class="form-control">
                      <option value="">All Payroll Periods</option>
                      <?php foreach ($payrollPeriods as $period): ?>
                        <option value="<?= htmlspecialchars($period['payroll_period']) ?>">
                          <?= htmlspecialchars($period['payroll_period']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <select id="statusFilter" class="form-control">
                      <option value="">All Statuses</option>
                      <option value="COMPLETE">Complete</option>
                      <option value="INCOMPLETE">Incomplete</option>
                      <option value="COMPLETE AND LATE">Complete & Late</option>
                      <option value="NOT SUBMITTED">Not Submitted</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <select id="filingFilter" class="form-control">
                      <option value="">All Filing Status</option>
                      <option value="FORWARDED">Forwarded</option>
                      <option value="NOT FORWARDED">Not Forwarded</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <input type="text" id="searchFilter" class="form-control" placeholder="Search employees...">
                  </div>
                </div>

                <div class="table-container">
                    <!-- Bulk Actions -->
                    <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card">
                        <div class="card-body">
                            <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="selectAll">
                                <label class="form-check-label" for="selectAll">Select All</label>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn" disabled>
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" id="deleteAllBtn">
                                    <i class="fas fa-trash-alt"></i> Delete All
                                </button>
                            </div>
                            </div>
                        </div>
                        </div>
                    </div>
                    </div>
                  <table id="monitoringTable" class="table table-bordered table-striped monitoring-table">
                    <thead>
                    <tr>
                        <th width="30">
                        <!-- <input type="checkbox" id="selectAllHeader"> -->
                        </th>
                        <th>Employee</th>
                        <th>ID Number</th>
                        <th>Appointment Status</th>
                        <th>Position</th>
                        <th>Payroll Period</th>
                        <th>Status</th>
                        <th>Filing Status</th>
                        <th>Submission Date</th>
                        <th>Remarks</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($monitoringRecords as $record): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="record-checkbox" name="record_ids[]" value="<?= $record['monitoring_id'] ?>">
                            </td>
                          <td><?= htmlspecialchars($record['employee_name']) ?></td>
                          <td><?= htmlspecialchars($record['id_number']) ?></td>
                          <td>
                            <?php 
                              // Find employee appointment status
                              $employeeAppointment = '';
                              foreach ($employees as $emp) {
                                if ($emp['emp_id'] == $record['emp_id']) {
                                  $employeeAppointment = $emp['appointment_status'];
                                  break;
                                }
                              }
                            ?>
                            <span class="badge appointment-badge"><?= htmlspecialchars($employeeAppointment) ?></span>
                          </td>
                          <td>
                            <?php 
                              // Find employee position
                              $employeePosition = '';
                              foreach ($employees as $emp) {
                                if ($emp['emp_id'] == $record['emp_id']) {
                                  $employeePosition = $emp['position_name'];
                                  break;
                                }
                              }
                              echo htmlspecialchars($employeePosition);
                            ?>
                          </td>
                          <td><?= htmlspecialchars($record['payroll_period']) ?></td>
                          <td>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $record['status'])) ?>">
                              <?= htmlspecialchars($record['status']) ?>
                            </span>
                          </td>
                          <td>
                            <span class="filing-badge filing-<?= strtolower(str_replace(' ', '-', $record['filing_status'])) ?>">
                              <?= htmlspecialchars($record['filing_status']) ?>
                            </span>
                          </td>
                          <td><?= $record['submission_date'] ? htmlspecialchars($record['submission_date']) : 'N/A' ?></td>
                          <td><?= htmlspecialchars($record['remarks']) ?: 'N/A' ?></td>
                          <td><?= htmlspecialchars($record['updated_at']) ?></td>
                          <td>
                            <button class="btn btn-sm btn-warning edit-record" 
                                    data-id="<?= $record['monitoring_id'] ?>"
                                    data-status="<?= $record['status'] ?>"
                                    data-filing_status="<?= $record['filing_status'] ?>"
                                    data-date="<?= $record['submission_date'] ?>"
                                    data-remarks="<?= htmlspecialchars($record['remarks']) ?>">
                              <i class="fas fa-edit"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
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

<!-- Add Record Modal -->
<div class="modal fade" id="addRecordModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Monitoring Record</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <div class="form-group">
            <label>Employee</label>
            <select name="emp_id" class="form-control" required>
              <option value="">Select Employee</option>
              <?php foreach ($employees as $employee): ?>
                <option value="<?= $employee['emp_id'] ?>">
                  <?= htmlspecialchars($employee['employee_name']) ?> (<?= htmlspecialchars($employee['id_number']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Payroll Period</label>
            <div class="row">
              <div class="col-md-6">
                <input type="date" name="period_start" class="form-control" required>
              </div>
              <div class="col-md-6">
                <input type="date" name="period_end" class="form-control" required>
              </div>
            </div>
            <small class="form-text text-muted">Select start and end dates for the payroll period</small>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control" required>
              <option value="NOT SUBMITTED">Not Submitted</option>
              <option value="COMPLETE">Complete</option>
              <option value="INCOMPLETE">Incomplete</option>
              <option value="COMPLETE AND LATE">Complete and Late</option>
            </select>
          </div>
          <div class="form-group">
            <label>Filing Status</label>
            <select name="filing_status" class="form-control" required>
              <option value="NOT FORWARDED">Not Forwarded</option>
              <option value="FORWARDED">Forwarded</option>
            </select>
          </div>
          <div class="form-group">
            <label>Submission Date</label>
            <input type="date" name="submission_date" class="form-control">
          </div>
          <div class="form-group">
            <label>Remarks</label>
            <textarea name="remarks" class="form-control" rows="3" placeholder="Enter remarks..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_record" class="btn btn-primary">Add Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Record Modal -->
<div class="modal fade" id="editRecordModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Monitoring Record</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST">
        <input type="hidden" name="monitoring_id" id="edit_monitoring_id">
        <div class="modal-body">
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="edit_status" class="form-control" required>
              <option value="NOT SUBMITTED">Not Submitted</option>
              <option value="COMPLETE">Complete</option>
              <option value="INCOMPLETE">Incomplete</option>
              <option value="COMPLETE AND LATE">Complete and Late</option>
            </select>
          </div>
          <div class="form-group">
            <label>Filing Status</label>
            <select name="filing_status" id="edit_filing_status" class="form-control" required>
              <option value="NOT FORWARDED">Not Forwarded</option>
              <option value="FORWARDED">Forwarded</option>
            </select>
          </div>
          <div class="form-group">
            <label>Submission Date</label>
            <input type="date" name="submission_date" id="edit_submission_date" class="form-control">
          </div>
          <div class="form-group">
            <label>Remarks</label>
            <textarea name="remarks" id="edit_remarks" class="form-control" rows="3" placeholder="Enter remarks..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="update_attachment" class="btn btn-primary">Update Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import Excel File</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="attachments_import.php" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="form-group">
            <label>Select Excel File (.xlsx)</label>
            <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
            <small class="form-text text-muted">
              File format should be: Employee ID | Payroll Period | Status | Filing Status | Submission Date | Remarks
            </small>
          </div>
          <div class="alert alert-info">
            <strong>Note:</strong> Download the template first to ensure correct format.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
  // Initialize DataTable
  const dataTable = $('#monitoringTable').DataTable({
    responsive: true,
    pageLength: 25,
    dom: '<"top"lf>rt<"bottom"ip>',
    columnDefs: [
      { orderable: false, targets: [0, 11] } // Disable sorting for checkbox and actions columns
    ]
  });

  // Store existing employee-period combinations for client-side validation
  const existingRecords = <?php echo json_encode(array_map(function($record) {
      return [
          'emp_id' => $record['emp_id'],
          'payroll_period' => $record['payroll_period']
      ];
  }, $monitoringRecords)); ?>;

  // Store all monitoring records for statistics calculation
  const allMonitoringRecords = <?php echo json_encode($monitoringRecords); ?>;

  // Function to check for duplicate on client side
  function checkDuplicate(empId, periodStart, periodEnd) {
    const payrollPeriod = formatPayrollPeriod(periodStart, periodEnd);
    return existingRecords.some(record => 
      record.emp_id == empId && record.payroll_period === payrollPeriod
    );
  }

  // Function to format payroll period like PHP does
  function formatPayrollPeriod(start, end) {
    const startDate = new Date(start);
    const endDate = new Date(end);
    
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    const startFormatted = months[startDate.getMonth()] + ' ' + startDate.getDate();
    const endFormatted = months[endDate.getMonth()] + ' ' + endDate.getDate();
    
    return startFormatted + ' - ' + endFormatted;
  }

  // Function to update statistics based on filtered records
  function updateStatistics(filteredRecords) {
    // Calculate counts for each status
    const completeCount = filteredRecords.filter(record => record.status === 'COMPLETE').length;
    const completeLateCount = filteredRecords.filter(record => record.status === 'COMPLETE AND LATE').length;
    const notSubmittedCount = filteredRecords.filter(record => record.status === 'NOT SUBMITTED').length;
    const incompleteCount = filteredRecords.filter(record => record.status === 'INCOMPLETE').length;
    
    // Get unique employees from filtered records
    const uniqueEmployees = [...new Set(filteredRecords.map(record => record.emp_id))];
    const totalEmployees = uniqueEmployees.length;

    // Update the statistics cards
    $('.small-box.bg-info .inner h3').text(totalEmployees);
    $('.small-box.bg-success .inner h3').text(completeCount);
    $('.small-box.bg-warning .inner h3').text(completeLateCount);
    $('.small-box.bg-danger .inner h3').text(notSubmittedCount);
  }

  // Function to apply custom filtering to DataTable
  function applyCustomFilters() {
    const periodFilter = $('#periodFilter').val().toLowerCase();
    const statusFilter = $('#statusFilter').val().toLowerCase();
    const filingFilter = $('#filingFilter').val().toLowerCase();
    const searchFilter = $('#searchFilter').val().toLowerCase();

    // Use DataTable's custom filtering
    $.fn.dataTable.ext.search.push(
      function(settings, data, dataIndex) {
        if (settings.nTable !== document.getElementById('monitoringTable')) {
          return true;
        }

        const rowPeriod = data[5].toLowerCase(); // Payroll Period column
        const rowStatus = data[6].toLowerCase(); // Status column
        const rowFiling = data[7].toLowerCase(); // Filing Status column
        const rowText = data.join(' ').toLowerCase(); // All row text for search

        const periodMatch = !periodFilter || rowPeriod.includes(periodFilter);
        const statusMatch = !statusFilter || rowStatus.includes(statusFilter);
        const filingMatch = !filingFilter || rowFiling.includes(filingFilter);
        const searchMatch = !searchFilter || rowText.includes(searchFilter);

        return periodMatch && statusMatch && filingMatch && searchMatch;
      }
    );

    // Redraw the table
    dataTable.draw();

    // Remove the custom filter function to avoid stacking
    $.fn.dataTable.ext.search.pop();
  }

  // Function to get filtered records based on current filters
  function getFilteredRecords() {
    const periodFilter = $('#periodFilter').val().toLowerCase();
    const statusFilter = $('#statusFilter').val().toLowerCase();
    const filingFilter = $('#filingFilter').val().toLowerCase();
    const searchFilter = $('#searchFilter').val().toLowerCase();

    return allMonitoringRecords.filter(record => {
      const periodMatch = !periodFilter || record.payroll_period.toLowerCase().includes(periodFilter);
      const statusMatch = !statusFilter || record.status.toLowerCase().includes(statusFilter);
      const filingMatch = !filingFilter || record.filing_status.toLowerCase().includes(filingFilter);
      const searchMatch = !searchFilter || 
        record.employee_name.toLowerCase().includes(searchFilter) ||
        record.id_number.toLowerCase().includes(searchFilter) ||
        record.payroll_period.toLowerCase().includes(searchFilter);

      return periodMatch && statusMatch && filingMatch && searchMatch;
    });
  }

  // Function to update both table and statistics
  function updateFilters() {
    applyCustomFilters();
    
    // Update statistics based on filtered records
    const filteredRecords = getFilteredRecords();
    updateStatistics(filteredRecords);
  }

  // Apply filters when they change
  $('#periodFilter, #statusFilter, #filingFilter, #searchFilter').on('change keyup', function() {
    updateFilters();
  });

  // Initial statistics update
  updateStatistics(allMonitoringRecords);

  // Edit record functionality
  $('.edit-record').click(function() {
    const id = $(this).data('id');
    const status = $(this).data('status');
    const filing_status = $(this).data('filing_status');
    const date = $(this).data('date');
    const remarks = $(this).data('remarks');

    $('#edit_monitoring_id').val(id);
    $('#edit_status').val(status);
    $('#edit_filing_status').val(filing_status);
    $('#edit_submission_date').val(date);
    $('#edit_remarks').val(remarks);

    $('#editRecordModal').modal('show');
  });

  // Bulk delete functionality
  $('#selectAll').change(function() {
    $('.record-checkbox').prop('checked', this.checked);
    updateBulkDeleteButton();
  });

  $('.record-checkbox').change(function() {
    updateBulkDeleteButton();
    updateSelectAllCheckbox();
  });

  function updateSelectAllCheckbox() {
    const allChecked = $('.record-checkbox:checked').length === $('.record-checkbox').length;
    $('#selectAll').prop('checked', allChecked);
  }

  function updateBulkDeleteButton() {
    const anyChecked = $('.record-checkbox:checked').length > 0;
    $('#bulkDeleteBtn').prop('disabled', !anyChecked);
  }

  $('#bulkDeleteBtn').click(function() {
    const selectedCount = $('.record-checkbox:checked').length;
    if (selectedCount === 0) {
      alert('Please select at least one record to delete.');
      return;
    }

    if (confirm(`Are you sure you want to delete ${selectedCount} selected record(s)?`)) {
      // Create a form and submit it
      const form = $('<form>').attr({
        method: 'POST',
        action: ''
      });
      
      $('.record-checkbox:checked').each(function() {
        form.append($('<input>').attr({
          type: 'hidden',
          name: 'record_ids[]',
          value: $(this).val()
        }));
      });
      
      form.append($('<input>').attr({
        type: 'hidden',
        name: 'bulk_delete',
        value: '1'
      }));
      
      $('body').append(form);
      form.submit();
    }
  });

  // Delete All functionality
  $('#deleteAllBtn').click(function() {
    if (confirm('Are you sure you want to delete ALL records? This action cannot be undone.')) {
      // Create a form and submit it
      const form = $('<form>').attr({
        method: 'POST',
        action: ''
      });
      
      form.append($('<input>').attr({
        type: 'hidden',
        name: 'delete_all',
        value: '1'
      }));
      
      form.append($('<input>').attr({
        type: 'hidden',
        name: 'bulk_delete',
        value: '1'
      }));
      
      $('body').append(form);
      form.submit();
    }
  });

  // Export functionality
  $('#exportBtn').click(function() {
    window.location.href = 'attachments_export.php';
  });

  // Export by period functionality
  $('.export-period').click(function(e) {
    e.preventDefault();
    const period = $(this).data('period');
    window.location.href = 'attachments_export.php?period=' + encodeURIComponent(period);
  });

  // Template download functionality
  $('#templateBtn').click(function() {
    window.location.href = 'attachments_template.php';
  });

  // Add Record Modal validation
  $('#addRecordModal form').submit(function(e) {
    const empId = $('select[name="emp_id"]').val();
    const periodStart = $('input[name="period_start"]').val();
    const periodEnd = $('input[name="period_end"]').val();

    if (checkDuplicate(empId, periodStart, periodEnd)) {
      e.preventDefault();
      alert('A record for this employee and payroll period already exists. Please update the existing record instead.');
      return false;
    }
  });

  // Initialize tooltips
  $('[data-toggle="tooltip"]').tooltip();
});
</script>
</body>
</html>