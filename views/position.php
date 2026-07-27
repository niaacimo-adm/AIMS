<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add new position
    if (isset($_POST['add_position'])) {
        $position_name = trim($_POST['position_name']);
        $salary        = isset($_POST['salary']) && $_POST['salary'] !== '' ? (float)$_POST['salary'] : 0;

        if (!empty($position_name)) {
            $stmt = $db->prepare("INSERT INTO position (position_name, salary) VALUES (?, ?)");
            if ($stmt->execute([$position_name, $salary])) {
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
        $id            = (int)$_POST['id'];
        $position_name = trim($_POST['position_name']);
        $salary        = isset($_POST['salary']) && $_POST['salary'] !== '' ? (float)$_POST['salary'] : 0;

        if (!empty($position_name)) {
            $stmt = $db->prepare("UPDATE position SET position_name = ?, salary = ? WHERE position_id = ?");
            if ($stmt->execute([$position_name, $salary, $id])) {
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
  <title>AdminLTE 3 | Position </title>
  <?php include '../includes/header.php'; ?>
  <style>
/* ═══════════════════════════════════════════════════
   DESIGN TOKENS — Light Mode
═══════════════════════════════════════════════════ */
:root {
  --rr-bg:          #f0f4f8;
  --rr-surface:     #ffffff;
  --rr-surface-2:   #f8fafc;
  --rr-border:      #e2e8f0;
  --rr-border-sub:  #f1f5f9;
  --rr-text:        #0f172a;
  --rr-text-2:      #475569;
  --rr-text-muted:  #94a3b8;
  --rr-primary:     #2563eb;
  --rr-primary-dk:  #1d4ed8;
  --rr-primary-lt:  #eff6ff;
  --rr-accent:      #06b6d4;
  --rr-success:     #10b981;
  --rr-warning:     #f59e0b;
  --rr-danger:      #ef4444;
  --rr-purple:      #7c3aed;
  --rr-cyan:        #0891b2;
  --rr-shadow-sm:   0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
  --rr-shadow:      0 4px 16px rgba(0,0,0,.08);
  --rr-shadow-lg:   0 12px 40px rgba(0,0,0,.14);
  --rr-radius-sm:   6px;
  --rr-radius:      12px;
  --rr-radius-lg:   18px;
  --rr-font:        'DM Sans',sans-serif;
  --rr-font-h:      'Syne',sans-serif;
}
body.dark-mode {
  --rr-bg:         #0f172a;
  --rr-surface:    #1e293b;
  --rr-surface-2:  #162032;
  --rr-border:     #334155;
  --rr-border-sub: #1e293b;
  --rr-text:       #f1f5f9;
  --rr-text-2:     #94a3b8;
  --rr-text-muted: #64748b;
  --rr-primary-lt: rgba(37,99,235,.18);
  --rr-shadow-sm:  0 1px 3px rgba(0,0,0,.3);
  --rr-shadow:     0 4px 20px rgba(0,0,0,.4);
  --rr-shadow-lg:  0 12px 40px rgba(0,0,0,.5);
}
body,.content-wrapper { background:var(--rr-bg)!important; font-family:var(--rr-font)!important; }
.content { padding:0 20px; margin-top:-80px; position:relative; z-index:3; }

/* ═══ HERO ═══ */
@keyframes meshDrift  { 0%{transform:translate(0,0) rotate(0)} 100%{transform:translate(3%,2%) rotate(2deg)} }
@keyframes orbFloat   { 0%,100%{opacity:.4;transform:translate(0,0) scale(1)} 33%{opacity:.7;transform:translate(18px,-26px) scale(1.05)} 66%{opacity:.5;transform:translate(-12px,16px) scale(.95)} }
.pg-hero { background:#0b1f17;padding:36px 28px 66px;position:relative;overflow:hidden; }
.pg-hero-mesh { position:absolute;inset:-50%;width:200%;height:200%;z-index:0;
  background:radial-gradient(ellipse 60% 55% at 18% 28%,rgba(36,231,143,.16) 0%,transparent 58%),
             radial-gradient(ellipse 55% 60% at 82% 72%,rgba(42,152,99,.13) 0%,transparent 58%),
             linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
  animation:meshDrift 22s ease-in-out infinite alternate; }
.pg-hero-orbs { position:absolute;inset:0;z-index:0;pointer-events:none;overflow:hidden; }
.pg-orb { position:absolute;border-radius:50%;filter:blur(60px);animation:orbFloat 18s ease-in-out infinite; }
.pg-orb-1 { width:280px;height:280px;background:rgba(36,231,143,.11);top:-80px;left:-60px;animation-duration:21s; }
.pg-orb-2 { width:220px;height:220px;background:rgba(42,152,99,.10);bottom:-50px;right:-40px;animation-delay:-7s;animation-duration:17s; }
.pg-hero-dots { position:absolute;inset:0;z-index:0;pointer-events:none;
  background-image:radial-gradient(circle,rgba(36,231,143,.06) 1px,transparent 1px);background-size:36px 36px; }
.pg-hero::after { content:'';position:absolute;bottom:-32px;left:0;right:0;height:64px;
  background:var(--rr-bg);clip-path:ellipse(58% 100% at 50% 100%);z-index:1; }
.pg-hero-inner { position:relative;z-index:2; }
.pg-hero-title { color:#fff;font-size:1.75rem;font-weight:800;margin:0 0 6px;letter-spacing:-.3px;
  text-shadow:0 2px 14px rgba(0,0,0,.45);display:flex;align-items:center;gap:10px; }
.pg-hero-sub   { color:rgba(212,245,229,.75);margin:0 0 14px;font-size:.9rem; }
.pg-hero-divider { width:48px;height:2px;border-radius:2px;margin:0 0 12px;
  background:linear-gradient(90deg,transparent,#24e78f,transparent); }

/* ═══ CARDS ═══ */
.card {
  background:var(--rr-surface)!important;border:1px solid var(--rr-border)!important;
  border-radius:var(--rr-radius-lg)!important;box-shadow:var(--rr-shadow-sm)!important;
  transition:box-shadow .2s;
}
.card:hover { box-shadow:var(--rr-shadow)!important; }
.card-header {
  background:var(--rr-surface)!important;border-bottom:1px solid var(--rr-border-sub)!important;
  padding:1rem 1.25rem!important;display:flex;align-items:center;gap:.6rem;
}
.card-header::before { content:'';display:inline-block;width:4px;height:18px;border-radius:4px;
  background:linear-gradient(160deg,var(--rr-primary),var(--rr-accent));flex-shrink:0; }
.card-header .card-title,.card-header h3 {
  font-family:var(--rr-font-h);font-size:.95rem!important;font-weight:700!important;
  color:var(--rr-text)!important;letter-spacing:-.01em;margin:0!important;
}
.card-body { background:var(--rr-surface)!important; }
.card-footer { background:var(--rr-surface-2)!important;border-top:1px solid var(--rr-border-sub)!important;display:flex;gap:.5rem;flex-wrap:wrap; }

/* ═══ BADGES ═══ */
.badge { border-radius:20px!important;font-size:.7rem!important;font-weight:700!important;padding:.3em .75em!important; }
.badge-salary { background:rgba(16,185,129,.12)!important;color:#059669!important; }
body.dark-mode .badge-salary { background:rgba(16,185,129,.18)!important;color:#34d399!important; }

/* ═══ FORMS ═══ */
.form-group label { font-size:.76rem;font-weight:700;color:var(--rr-text-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem;display:block; }
.form-control {
  background:var(--rr-surface-2)!important;border:1.5px solid var(--rr-border)!important;
  border-radius:var(--rr-radius-sm)!important;color:var(--rr-text)!important;
  font-family:var(--rr-font)!important;font-size:.875rem!important;padding:.5rem .75rem!important;
  transition:border-color .15s,box-shadow .15s;
}
.form-control:focus { border-color:var(--rr-primary)!important;box-shadow:0 0 0 3px rgba(37,99,235,.12)!important;background:var(--rr-surface)!important; }
.form-text-hint { font-size:.75rem;color:var(--rr-text-muted);margin-top:.25rem; }
.input-group-text { background:var(--rr-surface-2)!important;border:1.5px solid var(--rr-border)!important;color:var(--rr-text-2)!important;font-weight:700;font-size:.85rem; }

/* ═══ BUTTONS ═══ */
.btn { font-family:var(--rr-font)!important;font-weight:600!important;font-size:.84rem!important;border-radius:var(--rr-radius-sm)!important;transition:all .18s!important; }
.btn-primary   { background:linear-gradient(135deg,var(--rr-primary),var(--rr-primary-dk))!important;border:none!important;color:#fff!important;box-shadow:0 2px 8px rgba(37,99,235,.3)!important; }
.btn-primary:hover { transform:translateY(-1px);box-shadow:0 4px 14px rgba(37,99,235,.4)!important;color:#fff!important; }
.btn-danger    { background:linear-gradient(135deg,var(--rr-danger),#dc2626)!important;border:none!important;color:#fff!important; }
.btn-info      { background:linear-gradient(135deg,var(--rr-cyan),#0e7490)!important;border:none!important;color:#fff!important; }
.btn-secondary { background:var(--rr-surface-2)!important;border:1.5px solid var(--rr-border)!important;color:var(--rr-text-2)!important; }
.btn-secondary:hover { background:var(--rr-border)!important;color:var(--rr-text)!important; }
.btn-xs { font-size:.72rem!important;padding:.25rem .55rem!important; }

/* ═══ EDIT-MODE HIGHLIGHT ═══ */
.card-editing { border-color:var(--rr-primary)!important;box-shadow:0 0 0 3px rgba(37,99,235,.15),var(--rr-shadow)!important; }
.card-editing .card-header::before { background:linear-gradient(160deg,var(--rr-warning),#d97706)!important; }

/* ═══ TABLES ═══ */
.table { font-size:.85rem;color:var(--rr-text); }
.table thead th { background:var(--rr-surface-2)!important;border-bottom:2px solid var(--rr-border)!important;font-weight:700;font-size:.73rem;text-transform:uppercase;letter-spacing:.07em;color:var(--rr-text-muted)!important;padding:.75rem 1rem;white-space:nowrap; }
.table tbody td { padding:.65rem 1rem;border-color:var(--rr-border-sub)!important;vertical-align:middle;color:var(--rr-text); }
.table-hover tbody tr:hover td { background:var(--rr-primary-lt)!important; }
body.dark-mode .table-hover tbody tr:hover td { background:rgba(37,99,235,.1)!important; }
.table tr.row-editing td { background:var(--rr-primary-lt)!important; }
body.dark-mode .table tr.row-editing td { background:rgba(37,99,235,.14)!important; }
div.dataTables_wrapper div.dataTables_length select,
div.dataTables_wrapper div.dataTables_filter input {
  border:1.5px solid var(--rr-border)!important;border-radius:var(--rr-radius-sm)!important;
  padding:.3rem .6rem;font-size:.82rem;font-family:var(--rr-font);
  background:var(--rr-surface-2)!important;color:var(--rr-text)!important;
}
div.dataTables_wrapper div.dataTables_info,
div.dataTables_wrapper .dataTables_length label,
div.dataTables_wrapper .dataTables_filter label { font-size:.8rem;color:var(--rr-text-muted);font-family:var(--rr-font); }
.paginate_button { border-radius:var(--rr-radius-sm)!important;font-size:.8rem!important; }
.paginate_button.current { background:var(--rr-primary)!important;border-color:var(--rr-primary)!important;color:#fff!important; }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <?php include '../includes/sidebar.php'; ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">

    <!-- Page Hero -->
    <div class="pg-hero">
        <div class="pg-hero-mesh"></div>
        <div class="pg-hero-orbs">
            <div class="pg-orb pg-orb-1"></div>
            <div class="pg-orb pg-orb-2"></div>
        </div>
        <div class="pg-hero-dots"></div>
        <div class="pg-hero-inner">
            <h1 class="pg-hero-title"><i class="fas fa-users-cog"></i> Position Management</h1>
            <p class="pg-hero-sub">Manage available positions and their salary within the organization.</p>
            <div class="pg-hero-divider"></div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-4">
            <div class="card card-primary mt-4 <?= $isEditing ? 'card-editing' : '' ?>" id="positionFormCard">
              <div class="card-header">
                <h3 class="card-title">
                  <i class="fas <?= $isEditing ? 'fa-edit' : 'fa-plus' ?> mr-1"></i>
                  <?= $isEditing ? 'Edit Position' : 'Add New Position' ?>
                </h3>
              </div>
              <form method="POST">
                <div class="card-body">
                  <?php if ($isEditing): ?>
                  <input type="hidden" name="id" value="<?= $editPosition['position_id'] ?>">
                  <?php endif; ?>
                  <div class="form-group">
                    <label for="position_name">Position Name</label>
                    <input type="text" class="form-control" id="position_name" name="position_name"
                           value="<?= $isEditing ? htmlspecialchars($editPosition['position_name']) : '' ?>" required>
                  </div>
                  <div class="form-group">
                    <label for="salary">Salary per Position</label>
                    <div class="input-group">
                      <div class="input-group-prepend">
                        <span class="input-group-text">&#8369;</span>
                      </div>
                      <input type="number" step="0.01" min="0" class="form-control" id="salary" name="salary"
                             value="<?= $isEditing ? htmlspecialchars($editPosition['salary']) : '' ?>"
                             placeholder="0.00" required>
                    </div>
                    <div class="form-text-hint">Monthly base salary assigned to this position.</div>
                  </div>
                </div>
                <div class="card-footer">
                  <button type="submit" name="<?= $isEditing ? 'update_position' : 'add_position' ?>" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i><?= $isEditing ? 'Save Changes' : 'Add Position' ?>
                  </button>
                  <?php if ($isEditing): ?>
                  <a href="position.php" class="btn btn-secondary">Cancel</a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>
          <div class="col-md-8">
            <div class="card card-primary mt-4">
              <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list mr-1"></i> Manage Positions</h3>
              </div>
              <div class="card-body">
                <table id="sectable" class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Position Name</th>
                      <th>Salary per Position</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($positions as $status): ?>
                    <tr class="<?= ($isEditing && $editPosition['position_id'] == $status['position_id']) ? 'row-editing' : '' ?>">
                      <td><?= htmlspecialchars($status['position_name']) ?></td>
                      <td>
                        <span class="badge badge-salary">
                          &#8369;<?= number_format((float)($status['salary'] ?? 0), 2) ?>
                        </span>
                      </td>
                      <td>
                        <div class="btn-group">
                          <a href="position.php?edit=<?= $status['position_id'] ?>#positionFormCard" class="btn btn-xs btn-info">
                            <i class="fas fa-edit"></i>
                          </a>
                          <button type="button" class="btn btn-xs btn-danger delete-btn"
                                  data-id="<?= $status['position_id'] ?>"
                                  data-name="<?= htmlspecialchars($status['position_name']) ?>">
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
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
    </section>
  </div>
  <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<!-- SweetAlert Notifications -->
<?php if (isset($_SESSION['swal'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $_SESSION['swal']['type'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        text: '<?= $_SESSION['swal']['text'] ?>',
        showConfirmButton: true,
        timer: 3000
    });

    // Immediately clear the session after showing
    fetch('clear_session.php')
        .then(response => response.text())
        .then(data => console.log('Session cleared'))
        .catch(error => console.error('Error clearing session:', error));
});
</script>
<?php
    unset($_SESSION['swal']);
endif;
?>

<!-- Delete Confirmation Script -->
<script>
$(document).on('click', '.delete-btn', function(e) {
    e.preventDefault();
    const positionId = $(this).data('id');
    const statusName = $(this).data('name');

    // First clear any existing session messages
    fetch('clear_session.php')
        .then(() => {
            // Then show the delete confirmation
            Swal.fire({
                title: 'Delete Position?',
                text: `Are you sure you want to delete "${statusName}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Delete',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('delete_position.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `position_id=${positionId}`
                    })
                    .then(response => response.json())
                    .catch(error => {
                        Swal.showValidationMessage('Request failed');
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const response = result.value;

                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
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
                            confirmButtonText: 'OK'
                        });
                    }
                }
            });
        });
});
</script>

<!-- DataTables Initialization -->
<script>
$(function () {
    $('#sectable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[5, 10, 15, 20, 100], [5, 10, 15, 20, 100]],
        columnDefs: [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: -1 }
        ]
    });
});
</script>
</body>
</html>