<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update colors
    if (isset($_POST['update_colors'])) {
        foreach ($_POST['colors'] as $id => $color) {
            $stmt = $db->prepare("UPDATE appointment_status SET color = ? WHERE appointment_id = ?");
            $stmt->execute([$color, $id]);
        }
        $_SESSION['swal'] = [
            'type' => 'success',
            'title' => 'Success!',
            'text' => 'Status colors updated successfully!'
        ];
        header("Location: appointment_status.php");
        exit();
    }
    
    // Add new status
    if (isset($_POST['add_status'])) {
        $status_name = trim($_POST['status_name']);
        $color = $_POST['color'];
        
        if (!empty($status_name)) {
            $stmt = $db->prepare("INSERT INTO appointment_status (status_name, color) VALUES (?, ?)");
            if ($stmt->execute([$status_name, $color])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Status added successfully!'
                ];
                header("Location: appointment_status.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to add status.'
                ];
            }
        }
    }
    
    // Update status name
    if (isset($_POST['update_status'])) {
        $id = $_POST['id'];
        $status_name = trim($_POST['status_name']);
        $color = $_POST['color'];
        
        if (!empty($status_name)) {
            $stmt = $db->prepare("UPDATE appointment_status SET status_name = ?, color = ? WHERE appointment_id = ?");
            if ($stmt->execute([$status_name, $color, $id])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Status updated successfully!'
                ];
                header("Location: appointment_status.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to update status.'
                ];
            }
        }
    }
}
// Fetch all statuses
// $statuses = $db->query("SELECT * FROM appointment_status ORDER BY status_name")->fetchAll(PDO::FETCH_ASSOC);
$query = "SELECT * FROM appointment_status ORDER BY status_name";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$statuses = [];
while ($row = $result->fetch_assoc()) {
    $statuses[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Appointment Statuses</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../css/module-theme.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar.php'; ?>
  <div class="content-wrapper">
    <div class="pg-hero">
        <div class="pg-hero-mesh"></div>
        <div class="pg-hero-dots"></div>
        <div class="pg-hero-hex"></div>
        <div class="pg-hero-orbs">
            <div class="pg-orb pg-orb-1"></div>
            <div class="pg-orb pg-orb-2"></div>
            <div class="pg-orb pg-orb-3"></div>
            <div class="pg-orb pg-orb-4"></div>
        </div>
        <div class="pg-hero-rings">
            <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
        </div>
        <div class="pg-hero-arc"></div>
        <div class="pg-hero-layout">
            <div class="pg-hero-inner">
                <div class="pg-hero-title"><i class="fas fa-paperclip"></i>Appointment Statuses</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Track and manage appointment statuses</p>
            </div>
        </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Manage Statuses</h3>
            <div class="card-tools d-flex align-items-center">
              <div class="mod-chips">
                <span class="mod-chip active" style="cursor:default">Total<span class="n"><?= count($statuses) ?></span></span>
              </div>
              <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal">
                <i class="fas fa-plus"></i> New Status
              </button>
            </div>
          </div>
          <div class="card-body">
            <form method="POST" id="colorsForm">
              <table id="example1" class="table table-striped mod-table">
                <thead>
                  <tr>
                    <th>Status Name</th>
                    <th style="width:120px">Color</th>
                    <th style="width:110px">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($statuses as $status): ?>
                  <tr>
                    <td><span class="mod-title"><?= htmlspecialchars($status['status_name']) ?></span></td>
                    <td>
                      <input type="color" class="mod-color" name="colors[<?= (int)$status['appointment_id'] ?>]"
                             value="<?= htmlspecialchars($status['color']) ?>">
                    </td>
                    <td>
                      <button type="button" class="btn btn-sm btn-warning" title="Edit"
                              data-toggle="modal" data-target="#editModal<?= (int)$status['appointment_id'] ?>">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-danger delete-btn" title="Delete"
                              data-id="<?= (int)$status['appointment_id'] ?>"
                              data-name="<?= htmlspecialchars($status['status_name']) ?>">
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <div class="mt-3">
                <button type="submit" name="update_colors" class="btn btn-primary">
                  <i class="fas fa-save mr-1"></i>Save All Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>
  </div>
  <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- Add modal -->
<div class="modal fade scrum-style-modal" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-plus-circle"></i>New Status</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="status_name">Status Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="status_name" name="status_name" required>
          </div>
          <div class="form-group mb-0">
            <label for="color">Color</label>
            <input type="color" class="form-control" id="color" name="color" value="#007bff" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_status" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Add Status</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit modals (kept outside the bulk-colour form so forms are not nested) -->
<?php foreach ($statuses as $status): ?>
<div class="modal fade scrum-style-modal" id="editModal<?= (int)$status['appointment_id'] ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-edit"></i>Edit Status</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="<?= (int)$status['appointment_id'] ?>">
          <div class="form-group">
            <label>Status Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="status_name"
                   value="<?= htmlspecialchars($status['status_name']) ?>" required>
          </div>
          <div class="form-group mb-0">
            <label>Color</label>
            <input type="color" class="form-control" name="color"
                   value="<?= htmlspecialchars($status['color']) ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="update_status" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php include '../includes/footer.php'; ?>

<?php if (isset($_SESSION['swal'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: <?= json_encode($_SESSION['swal']['type']) ?>,
        title: <?= json_encode($_SESSION['swal']['title']) ?>,
        text: <?= json_encode($_SESSION['swal']['text']) ?>,
        showConfirmButton: true,
        confirmButtonColor: '#2a9863',
        timer: 3000
    });
});
</script>
<?php unset($_SESSION['swal']); endif; ?>

<script>
$(function () {
    var table = $("#example1").DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 5,
        lengthMenu: [[5, 10, 15, 20, 100], [5, 10, 15, 20, 100]],
        columnDefs: [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: 1 },
            { responsivePriority: 3, targets: -1 },
            { orderable: false, targets: [1, 2] }
        ],
        dom: '<"top"lf>rt<"bottom"ip>',
        language: {
            lengthMenu: "Show _MENU_ entries per page",
            paginate: { previous: "&laquo;", next: "&raquo;" }
        }
    });

    // DataTables removes rows on other pages from the DOM, so "Save All Changes"
    // would only post the visible page. Add the hidden rows' colours before submit.
    $('#colorsForm').on('submit', function () {
        var form = this;
        table.$('input.mod-color', { page: 'all' }).each(function () {
            if (!$.contains(form, this)) {
                $('<input type="hidden">').attr('name', this.name).val(this.value).appendTo(form);
            }
        });
    });
});

$(document).on('click', '.delete-btn', function(e) {
    e.preventDefault();
    const statusId = $(this).data('id');
    const statusName = $(this).data('name');

    fetch('clear_session.php')
        .then(() => {
            Swal.fire({
                title: 'Delete Status?',
                text: `Are you sure you want to delete "${statusName}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Delete',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('delete_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `status_id=${statusId}`
                    })
                    .then(response => response.json())
                    .catch(error => { Swal.showValidationMessage('Request failed'); });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const response = result.value;
                    if (response.success) {
                        Swal.fire({
                            icon: 'success', title: 'Deleted!', text: response.message,
                            timer: 2000, showConfirmButton: false
                        }).then(() => { window.location.reload(); });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Cannot Delete',
                            html: `
                                <p>${response.message}</p>
                                ${response.employeeCount > 0 ?
                                  '<div class="mt-3"><i class="fas fa-users mr-2"></i>Assigned to: ' +
                                  response.employeeCount + ' employee(s)</div>' : ''}
                            `,
                            showConfirmButton: true,
                            confirmButtonColor: '#2a9863',
                            confirmButtonText: 'OK'
                        });
                    }
                }
            });
        });
});
</script>
</body>
</html>