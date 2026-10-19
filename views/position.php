<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add new position
    if (isset($_POST['add_position'])) {
        $position_name  = trim($_POST['position_name']);
        $salary_per_day = isset($_POST['salary_per_day']) && $_POST['salary_per_day'] !== '' ? (float)$_POST['salary_per_day'] : 0;
        // Monthly salary is always derived from the daily rate (per day x 31), computed server-side for accuracy/security.
        $salary         = round($salary_per_day * 31, 2);

        if (!empty($position_name)) {
            $stmt = $db->prepare("INSERT INTO position (position_name, salary, salary_per_day) VALUES (?, ?, ?)");
            if ($stmt->execute([$position_name, $salary, $salary_per_day])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Position added successfully!'
                ];
                header("Location: position.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to add position.'
                ];
            }
        }
    }

    // Update position
    if (isset($_POST['update_position'])) {
        $id             = (int)$_POST['id'];
        $position_name  = trim($_POST['position_name']);
        $salary_per_day = isset($_POST['salary_per_day']) && $_POST['salary_per_day'] !== '' ? (float)$_POST['salary_per_day'] : 0;
        // Monthly salary is always derived from the daily rate (per day x 31), computed server-side for accuracy/security.
        $salary         = round($salary_per_day * 31, 2);

        if (!empty($position_name)) {
            $stmt = $db->prepare("UPDATE position SET position_name = ?, salary = ?, salary_per_day = ? WHERE position_id = ?");
            if ($stmt->execute([$position_name, $salary, $salary_per_day, $id])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Position updated successfully!'
                ];
                header("Location: position.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to update position.'
                ];
            }
        }
    }
}

$query = "SELECT * FROM position ORDER BY position_name";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
// Fetch all positions for reference
$positions = [];
while ($row = $result->fetch_assoc()) {
    $positions[] = $row;
}

// If editing, load that position's record so the form can be pre-filled
$editPosition = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($positions as $p) {
        if ($p['position_id'] == $editId) {
            $editPosition = $p;
            break;
        }
    }
}
$isEditing = $editPosition !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Position</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../css/module-theme.css">
  <style>
    .badge-salary { background:rgba(42,152,99,.12) !important; color:var(--h-success) !important; }
  </style>
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
                <div class="pg-hero-title"><i class="fas fa-users-cog"></i>Position Management</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Manage available positions and their salary within the organization.</p>
            </div>
        </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Manage Positions</h3>
            <div class="card-tools d-flex align-items-center">
              <div class="mod-chips">
                <span class="mod-chip active" style="cursor:default">Total<span class="n"><?= count($positions) ?></span></span>
              </div>
              <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal">
                <i class="fas fa-plus"></i> New Position
              </button>
            </div>
          </div>
          <div class="card-body">
            <table id="sectable" class="table table-striped mod-table">
              <thead>
                <tr>
                  <th>Position Name</th>
                  <th>Salary per Day</th>
                  <th>Salary per Position</th>
                  <th style="width:110px">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($positions as $status): ?>
                <tr>
                  <td><span class="mod-title"><?= htmlspecialchars($status['position_name']) ?></span></td>
                  <td data-order="<?= (float)($status['salary_per_day'] ?? 0) ?>">
                    <span class="badge badge-salary">&#8369;<?= number_format((float)($status['salary_per_day'] ?? 0), 2) ?></span>
                  </td>
                  <td data-order="<?= (float)($status['salary'] ?? 0) ?>">
                    <span class="badge badge-salary">&#8369;<?= number_format((float)($status['salary'] ?? 0), 2) ?></span>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-warning" title="Edit"
                            data-toggle="modal" data-target="#editModal<?= (int)$status['position_id'] ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger delete-btn" title="Delete"
                            data-id="<?= (int)$status['position_id'] ?>"
                            data-name="<?= htmlspecialchars($status['position_name']) ?>">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
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
          <h5 class="modal-title"><i class="fas fa-plus-circle"></i>New Position</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="add_name">Position Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="add_name" name="position_name"
                   value="<?= '' ?>" required>
          </div>
          <div class="form-group">
            <label for="add_day">Salary per Day <span class="text-danger">*</span></label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text" style="border-radius:8px 0 0 8px !important">&#8369;</span></div>
              <input type="number" step="0.01" min="0" class="form-control js-perday" id="add_day" name="salary_per_day"
                     value="<?= '' ?>" placeholder="0.00" required>
            </div>
            <small class="text-muted">Daily rate for this position.</small>
          </div>
          <div class="form-group mb-0">
            <label for="add_mon">Salary per Position (Monthly)</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text" style="border-radius:8px 0 0 8px !important">&#8369;</span></div>
              <input type="number" step="0.01" min="0" class="form-control js-monthly" id="add_mon" name="salary"
                     value="<?= '' ?>" placeholder="0.00" readonly>
            </div>
            <small class="text-muted">Auto-computed: Salary per Day &times; 31.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_position" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Add Position</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit modals -->
<?php foreach ($positions as $status): ?>
<div class="modal fade scrum-style-modal" id="editModal<?= (int)$status['position_id'] ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-edit"></i>Edit Position</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="<?= (int)$status['position_id'] ?>">
          <div class="form-group">
            <label for="edit<?= (int)$status['position_id'] ?>_name">Position Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="edit<?= (int)$status['position_id'] ?>_name" name="position_name"
                   value="<?= htmlspecialchars($status['position_name']) ?>" required>
          </div>
          <div class="form-group">
            <label for="edit<?= (int)$status['position_id'] ?>_day">Salary per Day <span class="text-danger">*</span></label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text" style="border-radius:8px 0 0 8px !important">&#8369;</span></div>
              <input type="number" step="0.01" min="0" class="form-control js-perday" id="edit<?= (int)$status['position_id'] ?>_day" name="salary_per_day"
                     value="<?= htmlspecialchars($status['salary_per_day'] ?? '') ?>" placeholder="0.00" required>
            </div>
            <small class="text-muted">Daily rate for this position.</small>
          </div>
          <div class="form-group mb-0">
            <label for="edit<?= (int)$status['position_id'] ?>_mon">Salary per Position (Monthly)</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text" style="border-radius:8px 0 0 8px !important">&#8369;</span></div>
              <input type="number" step="0.01" min="0" class="form-control js-monthly" id="edit<?= (int)$status['position_id'] ?>_mon" name="salary"
                     value="<?= htmlspecialchars($status['salary'] ?? '') ?>" placeholder="0.00" readonly>
            </div>
            <small class="text-muted">Auto-computed: Salary per Day &times; 31.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="update_position" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Changes</button>
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
    fetch('clear_session.php').catch(function () {});
});
</script>
<?php unset($_SESSION['swal']); endif; ?>

<script>
$(document).on('click', '.delete-btn', function(e) {
    e.preventDefault();
    const positionId = $(this).data('id');
    const statusName = $(this).data('name');

    fetch('clear_session.php')
        .then(() => {
            Swal.fire({
                title: 'Delete Position?',
                text: `Are you sure you want to delete "${statusName}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Delete',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('delete_position.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `position_id=${positionId}`
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

// Auto-compute monthly salary (daily x 31) in every form
$(document).on('input', '.js-perday', function () {
    var perDay = parseFloat(this.value);
    $(this).closest('.modal-body').find('.js-monthly')
        .val((!isNaN(perDay) && perDay >= 0) ? (perDay * 31).toFixed(2) : '');
});
$('.js-perday').trigger('input');

$(function () {
    $('#sectable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[5, 10, 15, 20, 100], [5, 10, 15, 20, 100]],
        columnDefs: [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: -1 },
            { orderable: false, targets: -1 }
        ],
        dom: '<"top"lf>rt<"bottom"ip>'
    });

    <?php if ($isEditing): ?>
    // Keep the old ?edit=<id> links working: open that position's edit modal
    $('#editModal<?= (int)$editPosition['position_id'] ?>').modal('show');
    <?php endif; ?>
});
</script>
</body>
</html>