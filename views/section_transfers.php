<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Initialize role if not set
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = ''; // Default empty role
}

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    $_SESSION['error'] = "Database connection failed: " . $e->getMessage();
    header("Location: error.php");
    exit;
}

// Get all sections
$sections = $conn->query("SELECT * FROM section ORDER BY section_name")->fetch_all(MYSQLI_ASSOC);

// Get current section if specified
$current_section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;
$current_section = null;

if ($current_section_id) {
    $stmt = $conn->prepare("SELECT * FROM section WHERE section_id = ?");
    $stmt->bind_param("i", $current_section_id);
    $stmt->execute();
    $current_section = $stmt->get_result()->fetch_assoc();
}

// Get transfers for current section or all transfers
$transfers = [];
if ($current_section_id) {
    $query = "SELECT dt.*, d.doc_number, d.title, 
                     CONCAT(e.first_name, ' ', e.last_name) as sender_name,
                     s.section_name as from_section,
                     u.unit_name as to_unit_name
              FROM document_transfers dt
              JOIN documents d ON dt.doc_id = d.doc_id
              JOIN employee e ON dt.from_emp_id = e.emp_id
              JOIN section s ON e.section_id = s.section_id
              LEFT JOIN unit_section u ON dt.to_unit_id = u.unit_id
              WHERE dt.to_section_id = ?
              ORDER BY dt.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $current_section_id);
    $stmt->execute();
    $transfers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $query = "SELECT dt.*, d.doc_number, d.title, 
                     CONCAT(e.first_name, ' ', e.last_name) as sender_name,
                     s.section_name as from_section,
                     ts.section_name as to_section,
                     u.unit_name as to_unit_name
              FROM document_transfers dt
              JOIN documents d ON dt.doc_id = d.doc_id
              JOIN employee e ON dt.from_emp_id = e.emp_id
              JOIN section s ON e.section_id = s.section_id
              JOIN section ts ON dt.to_section_id = ts.section_id
              LEFT JOIN unit_section u ON dt.to_unit_id = u.unit_id
              ORDER BY dt.created_at DESC";
    $transfers = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Handle status updates AND remarks updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
                $transfer_id = (int)$_POST['transfer_id'];
        $new_status = $_POST['new_status'];
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
        $emp_id = getEmployeeId($conn, $_SESSION['user_id']);

        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // 1. Update transfer status
            $stmt = $conn->prepare("UPDATE document_transfers 
                                  SET status = ?, remarks = ?, processed_by = ?, processed_at = NOW()
                                  WHERE transfer_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("ssii", $new_status, $remarks, $emp_id, $transfer_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            // 2. Add to history
            $doc_id = (int)$_POST['doc_id'];
            $action = 'status updated';
            $details = "Transfer status changed to " . $new_status;
            if (!empty($remarks)) {
                $details .= " with remarks: " . $remarks;
            }
            
            $history_stmt = $conn->prepare("INSERT INTO document_history (doc_id, emp_id, action, details) 
                                          VALUES (?, ?, ?, ?)");
            if (!$history_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $history_stmt->bind_param("iiss", $doc_id, $emp_id, $action, $details);
            if (!$history_stmt->execute()) {
                throw new Exception("Execute failed: " . $history_stmt->error);
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Transfer status updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error updating status: " . $e->getMessage();
            error_log("Error updating transfer status: " . $e->getMessage());
        }
        
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } elseif (isset($_POST['update_remarks'])) {
        $transfer_id = (int)$_POST['transfer_id'];
        $new_remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
        $emp_id = getEmployeeId($conn, $_SESSION['user_id']);

        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // 1. Update transfer remarks
            $stmt = $conn->prepare("UPDATE document_transfers 
                                  SET remarks = ?, processed_by = ?, processed_at = NOW()
                                  WHERE transfer_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("sii", $new_remarks, $emp_id, $transfer_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            // 2. Add to history
            $doc_id = (int)$_POST['doc_id'];
            $action = 'updated remarks';
            $details = "Updated transfer remarks";
            if (!empty($new_remarks)) {
                $details .= ": " . $new_remarks;
            }
            
            $history_stmt = $conn->prepare("INSERT INTO document_history (doc_id, emp_id, action, details) 
                                          VALUES (?, ?, ?, ?)");
            if (!$history_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $history_stmt->bind_param("iiss", $doc_id, $emp_id, $action, $details);
            if (!$history_stmt->execute()) {
                throw new Exception("Execute failed: " . $history_stmt->error);
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Transfer remarks updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error updating remarks: " . $e->getMessage();
            error_log("Error updating transfer remarks: " . $e->getMessage());
        }
        
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// function getEmployeeId($conn, $user_id) {
//     $stmt = $conn->prepare("SELECT employee_id FROM users WHERE id = ?");
//     $stmt->bind_param("i", $user_id);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     if ($result && $row = $result->fetch_assoc()) {
//         return (int)$row['employee_id'];
//     }
//     return null;
// }

// function hasPermission($permission) {
//     // Implement your permission logic here
//     // For now, we'll just check if user is admin or section head
//     return $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'section_head';
// }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Document Transfers by Section</title>
  <?php include '../includes/header.php'; ?>
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="../plugins/sweetalert2/sweetalert2.min.css">
  <style>
    .section-card {
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .section-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .section-card.active {
        border-left: 4px solid #007bff;
    }
    .transfer-item {
        border-left: 3px solid #6c757d;
        padding-left: 15px;
        margin-bottom: 15px;
    }
    .transfer-item.pending {
        border-left-color: #ffc107;
    }
    .transfer-item.accepted {
        border-left-color: #28a745;
    }
    .transfer-item.revised {
        border-left-color: #17a2b8;
    }
    .transfer-item.returned {
        border-left-color: #6c757d;
    }
    .badge-status {
        min-width: 80px;
    }
    /* Improved dropdown menu */
    .dropdown-actions .dropdown-menu {
        min-width: 120px;
        padding: 0;
    }
    .dropdown-actions .dropdown-item {
        padding: 0.5rem 1rem;
    }
    .dropdown-actions .dropdown-item i {
        margin-right: 5px;
        width: 15px;
        text-align: center;
    }
    /* Add to your style section */
    .btn-group-sm > .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        line-height: 1.5;
    }
    .dropdown-item {
        padding: 0.25rem 1rem;
    }
    .dropdown-item .btn-link {
        text-decoration: none;
        display: block;
        padding: 0.25rem 0;
    }
    .remarks-textarea {
        width: 100%;
        min-height: 100px;
        margin-top: 10px;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar.php'; ?>

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Document Transfers</h1>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
          </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
          </div>
        <?php endif; ?>

        <div class="row">
          <!-- Sections List -->
          <div class="col-md-3">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Office/Sections</h3>
                <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                  </button>
                </div>
              </div>
              <div class="card-body p-0">
                <ul class="nav nav-pills flex-column">
                  <li class="nav-item">
                    <a href="section_transfers.php" class="nav-link <?= !$current_section_id ? 'active' : '' ?>">
                      <i class="fas fa-list"></i> All Transfers
                    </a>
                  </li>
                  <?php foreach ($sections as $section): ?>
                    <li class="nav-item">
                      <a href="section_transfers.php?section_id=<?= $section['section_id'] ?>" 
                         class="nav-link <?= $current_section_id == $section['section_id'] ? 'active' : '' ?>">
                        <i class="fas fa-building"></i> <?= htmlspecialchars($section['section_name']) ?>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>

          <!-- Transfers List -->
          <div class="col-md-9">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">
                  <?= $current_section ? htmlspecialchars($current_section['section_name']) . ' Transfers' : 'All Document Transfers' ?>
                </h3>
                <div class="card-tools">
                  <div class="input-group input-group-sm" style="width: 200px;">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search...">
                    <div class="input-group-append">
                      <button class="btn btn-primary">
                        <i class="fas fa-search"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card-body">
                <?php if (empty($transfers)): ?>
                  <div class="p-3 text-center">
                    <p class="text-muted">No document transfers found</p>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table id="transfersTable" class="table table-hover table-striped">
                      <thead>
                          <tr>
                              <th>Document</th>
                              <th>From</th>
                              <th>To</th>
                              <th>Status</th>
                              <th>Date</th>
                              <th>Remarks</th>
                              <th>Actions</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php foreach ($transfers as $transfer): ?>
                              <tr class="transfer-item <?= $transfer['status'] ?>">
                                  <td>
                                      <strong><?= htmlspecialchars($transfer['doc_number']) ?></strong><br>
                                      <?= htmlspecialchars($transfer['title']) ?>
                                  </td>
                                  <td>
                                      <?= htmlspecialchars($transfer['sender_name']) ?><br>
                                      <small class="text-muted"><?= htmlspecialchars($transfer['from_section']) ?></small>
                                  </td>
                                  <td>
                                      <?= isset($transfer['to_section']) ? htmlspecialchars($transfer['to_section']) : 
                                          ($current_section ? htmlspecialchars($current_section['section_name']) : 'N/A') ?>
                                      <?php if (!empty($transfer['to_unit_name'])): ?>
                                          <br><small class="text-muted"><?= htmlspecialchars($transfer['to_unit_name']) ?> Unit</small>
                                      <?php endif; ?>
                                  </td>
                                  <td>
                                      <span class="badge <?= $transfer['status'] == 'pending' ? 'bg-warning' : 
                                                              ($transfer['status'] == 'accepted' ? 'bg-success' : 
                                                              ($transfer['status'] == 'revised' ? 'bg-info' : 'bg-secondary')) ?>">
                                          <?= ucfirst($transfer['status']) ?>
                                      </span>
                                  </td>
                                  <td><?= date('M d, Y H:i', strtotime($transfer['created_at'])) ?></td>
                                  <td>
                                      <?php if (!empty($transfer['remarks'])): ?>
                                          <div class="btn-group btn-group-sm" role="group">
                                              <button class="btn btn-outline-secondary" 
                                                      onclick="showRemarks('<?= htmlspecialchars(addslashes($transfer['remarks'])) ?>')">
                                                  View
                                              </button>
                                              <?php if (hasPermission('manage_transfer')): ?>
                                                  <button class="btn btn-outline-primary" 
                                                          onclick="showEditRemarksModal(<?= $transfer['transfer_id'] ?>, <?= $transfer['doc_id'] ?>, '<?= htmlspecialchars(addslashes($transfer['remarks'])) ?>')">
                                                      Edit
                                                  </button>
                                              <?php endif; ?>
                                          </div>
                                      <?php else: ?>
                                          <?php if (hasPermission('manage_transfer')): ?>
                                              <button class="btn btn-sm btn-outline-primary" 
                                                      onclick="showEditRemarksModal(<?= $transfer['transfer_id'] ?>, <?= $transfer['doc_id'] ?>, '')">
                                                  Add Remarks
                                              </button>
                                          <?php else: ?>
                                              <span class="text-muted">None</span>
                                          <?php endif; ?>
                                      <?php endif; ?>
                                  </td>
                                  <td>
                                      <div class="btn-group btn-group-sm" role="group">
                                          <button type="button" class="btn btn-info" onclick="viewDocument(<?= $transfer['doc_id'] ?>)">
                                              <i class="fas fa-eye"></i> View
                                          </button>
                                          <?php if (hasPermission('manage_transfer') && $transfer['status'] === 'pending'): ?>
                                              <button type="button" class="btn btn-info dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                  <span class="sr-only">Toggle Dropdown</span>
                                              </button>
                                              <div class="dropdown-menu">
                                                  <form method="POST" class="dropdown-item p-0">
                                                      <input type="hidden" name="transfer_id" value="<?= $transfer['transfer_id'] ?>">
                                                      <input type="hidden" name="doc_id" value="<?= $transfer['doc_id'] ?>">
                                                      <input type="hidden" name="new_status" value="accepted">
                                                      <button type="submit" name="update_status" class="btn btn-link text-success w-100 text-left pl-3">
                                                          <i class="fas fa-check mr-2"></i> Accept
                                                      </button>
                                                  </form>
                                                  <div class="dropdown-divider"></div>
                                                  <a href="#" class="dropdown-item" onclick="showStatusModal('revised', <?= $transfer['transfer_id'] ?>, <?= $transfer['doc_id'] ?>)">
                                                      <i class="fas fa-edit mr-2"></i> Revise
                                                  </a>
                                                  <div class="dropdown-divider"></div>
                                                  <a href="#" class="dropdown-item" onclick="showStatusModal('returned', <?= $transfer['transfer_id'] ?>, <?= $transfer['doc_id'] ?>)">
                                                      <i class="fas fa-undo mr-2"></i> Return
                                                  </a>
                                              </div>
                                          <?php endif; ?>
                                      </div>
                                  </td>
                              </tr>
                          <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
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

<!-- DataTables JS -->
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert2 -->
<script src="../plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
  function viewDocument(doc_id) {
    window.location.href = 'doctrack.php?view_id=' + doc_id;
  }

  // Function to show remarks in a modal
  function showRemarks(remarks) {
      Swal.fire({
          title: 'Transfer Remarks',
          html: `<div class="text-left p-3">${remarks.replace(/\n/g, '<br>')}</div>`,
          confirmButtonText: 'Close',
          width: '600px'
      });
  }

  // Function to show edit remarks modal
  function showEditRemarksModal(transfer_id, doc_id, current_remarks) {
      // Decode HTML entities and handle quotes properly
      current_remarks = $('<div/>').html(current_remarks).text();
      
      Swal.fire({
          title: 'Edit Remarks',
          html: `
              <form id="editRemarksForm">
                  <input type="hidden" name="transfer_id" value="${transfer_id}">
                  <input type="hidden" name="doc_id" value="${doc_id}">
                  <input type="hidden" name="update_remarks" value="1">
                  <div class="form-group">
                      <label for="remarks">Remarks:</label>
                      <textarea id="remarks" name="remarks" class="form-control remarks-textarea" 
                                placeholder="Enter remarks...">${current_remarks}</textarea>
                  </div>
              </form>
          `,
          showCancelButton: true,
          confirmButtonText: 'Update Remarks',
          confirmButtonColor: '#17a2b8',
          cancelButtonText: 'Cancel',
          focusConfirm: false,
          preConfirm: () => {
              const form = document.getElementById('editRemarksForm');
              return true;
          }
      }).then((result) => {
          if (result.isConfirmed) {
              const form = document.getElementById('editRemarksForm');
              form.method = 'POST';
              form.action = '';
              form.submit();
          }
      });
  }

  function showStatusModal(status, transfer_id, doc_id) {
      const title = status === 'revised' ? 'Revise Document' : 'Return Document';
      const confirmText = status === 'revised' ? 'Revise' : 'Return';
      
      Swal.fire({
          title: title,
          html: `
              <form id="statusForm">
                  <input type="hidden" name="transfer_id" value="${transfer_id}">
                  <input type="hidden" name="doc_id" value="${doc_id}">
                  <input type="hidden" name="new_status" value="${status}">
                  <input type="hidden" name="update_status" value="1">
                  <div class="form-group">
                      <label for="remarks">Remarks:</label>
                      <textarea id="remarks" name="remarks" class="form-control remarks-textarea" 
                                placeholder="Enter remarks..." required></textarea>
                  </div>
              </form>
          `,
          showCancelButton: true,
          confirmButtonText: confirmText,
          confirmButtonColor: status === 'revised' ? '#17a2b8' : '#6c757d',
          cancelButtonText: 'Cancel',
          focusConfirm: false,
          preConfirm: () => {
              const form = document.getElementById('statusForm');
              if (!form.remarks.value.trim()) {
                  Swal.showValidationMessage('Remarks are required');
                  return false;
              }
              return true;
          }
      }).then((result) => {
          if (result.isConfirmed) {
              const form = document.getElementById('statusForm');
              form.method = 'POST';
              form.action = '';
              form.submit();
          }
      });
  }

  $(document).ready(function() {
    // Initialize DataTable with the new column
    $('#transfersTable').DataTable({
      "paging": true,
      "lengthChange": true,
      "searching": true,
      "ordering": true,
      "info": true,
      "autoWidth": false,
      "responsive": true,
      "order": [[4, 'desc']], // Default sort by date (column 4)
      "columnDefs": [
        { "orderable": false, "targets": [5, 6] } // Disable sorting for remarks and actions columns
      ],
      "language": {
        "search": "_INPUT_",
        "searchPlaceholder": "Search transfers...",
        "lengthMenu": "Show _MENU_ entries",
        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
        "infoEmpty": "Showing 0 to 0 of 0 entries",
        "infoFiltered": "(filtered from _MAX_ total entries)",
        "paginate": {
          "first": "First",
          "last": "Last",
          "next": "Next",
          "previous": "Previous"
        }
      }
    });

    // Focus search input when search button is clicked
    $('.card-tools .btn').click(function() {
      $('#searchInput').focus();
    });

    // Close dropdown when clicking outside
    $(document).click(function(e) {
      if (!$(e.target).closest('.dropdown-actions').length) {
        $('.dropdown-actions .dropdown-menu').hide();
      }
    });
  });
</script>
</body>
</html>