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
            $stmt = $db->prepare("UPDATE employment_status SET color = ? WHERE status_id = ?");
            $stmt->execute([$color, $id]);
        }
        $_SESSION['swal'] = [
            'type' => 'success',
            'title' => 'Success!',
            'text' => 'Status colors updated successfully!'
        ];
        header("Location: employment_status.php");
        exit();
    }
    
    // Add new status
    if (isset($_POST['add_status'])) {
        $status_name = trim($_POST['status_name']);
        $color = $_POST['color'];
        
        if (!empty($status_name)) {
            $stmt = $db->prepare("INSERT INTO employment_status (status_name, color) VALUES (?, ?)");
            if ($stmt->execute([$status_name, $color])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Status added successfully!'
                ];
                header("Location: employment_status.php");
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
            $stmt = $db->prepare("UPDATE employment_status SET status_name = ?, color = ? WHERE status_id = ?");
            if ($stmt->execute([$status_name, $color, $id])) {
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Status updated successfully!'
                ];
                header("Location: employment_status.php");
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
$query = "SELECT * FROM employment_status ORDER BY status_name";
          
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
  <title>AdminLTE 3 | Employment Statuses</title>
  <?php include '../includes/header.php'; ?>
  <style>
    .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
        .pg-hero-breadcrumb {
            background:transparent; padding:0; margin:0;
            display:flex; flex-wrap:wrap; gap:2px;
        }
        .pg-hero-breadcrumb .breadcrumb-item + .breadcrumb-item::before { color:rgba(212,245,229,.45); }
        .pg-hero-bc-link   { color:rgba(212,245,229,.65); text-decoration:none; font-size:.8rem; }
        .pg-hero-bc-link:hover { color:#24e78f; }
        .pg-hero-bc-active { color:rgba(212,245,229,.9); font-size:.8rem; }

        /* ══ HERO — login-style animated mesh + orbs + rings ══ */
        @keyframes pgHeroMeshDrift {
            0%   { transform:translate(0,0)   rotate(0deg); }
            100% { transform:translate(3%,2%) rotate(2deg); }
        }
        @keyframes pgHeroOrbFloat {
            0%,100% { opacity:.4; transform:translate(0,0)       scale(1);    }
            33%      { opacity:.7; transform:translate(18px,-26px) scale(1.05); }
            66%      { opacity:.5; transform:translate(-12px,16px) scale(.95);  }
        }
        @keyframes pgHeroRingPulse {
            0%,100% { opacity:.45; transform:scale(1);    }
            50%      { opacity:.85; transform:scale(1.04); }
        }
        .pg-hero {
            background:#0b1f17;
            padding:36px 28px 66px; position:relative; overflow:hidden;
        }
        .pg-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:pgHeroMeshDrift 22s ease-in-out infinite alternate;
            z-index:0;
        }
        .pg-hero-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .pg-orb { position:absolute; border-radius:50%; filter:blur(60px); animation:pgHeroOrbFloat 18s ease-in-out infinite; }
        .pg-orb-1 { width:280px; height:280px; background:rgba(36,231,143,.11); top:-80px;    left:-60px;  animation-duration:21s; }
        .pg-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.10);  bottom:-50px; right:-40px; animation-delay:-7s; animation-duration:17s; }
        .pg-orb-3 { width:160px; height:160px; background:rgba(212,175,55,.06); top:40%;      right:20%;   animation-delay:-13s; animation-duration:24s; }
        .pg-orb-4 { width:120px; height:120px; background:rgba(36,231,143,.07); bottom:15%;   left:15%;    animation-delay:-4s;  animation-duration:15s; }
        .pg-hero-dots {
            position:absolute; inset:0; z-index:0; pointer-events:none;
            background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px);
            background-size:36px 36px;
        }
        .pg-hero-hex {
            position:absolute; inset:0; pointer-events:none; opacity:.045; z-index:0;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V16L28 0l28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V50l28-16 28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3C/svg%3E");
            background-size:56px 100px;
        }
        .pg-hero-rings {
            position:absolute; top:50%; right:6%;
            transform:translateY(-50%);
            width:240px; height:240px; pointer-events:none; z-index:0;
        }
        .pg-ring {
            position:absolute; inset:0; border-radius:50%;
            border:1px solid rgba(36,231,143,.10);
            animation:pgHeroRingPulse 4s ease-in-out infinite;
        }
        .pg-ring:nth-child(2) { inset:28px; animation-delay:.8s;  opacity:.7; }
        .pg-ring:nth-child(3) { inset:56px; animation-delay:1.6s; opacity:.5; }
        .pg-hero-arc {
            position:absolute; top:-50px; right:-50px;
            width:200px; height:200px; border-radius:50%;
            background:radial-gradient(circle,rgba(36,231,143,.18) 0%,transparent 70%);
            pointer-events:none; z-index:0;
        }
        .pg-hero::after {
            content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px;
            background:var(--body-bg, #eef7f2); clip-path:ellipse(58% 100% at 50% 100%); z-index:1;
        }
        body.dark-mode .pg-hero::after { background:var(--body-bg, #0b1f17); }
        .pg-hero-inner { position:relative; z-index:2; }
        .pg-hero-title {
            color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px;
            letter-spacing:-.3px; text-shadow:0 2px 14px rgba(0,0,0,.45);
            display:flex; align-items:center; gap:10px;
        }
        .pg-hero-sub  { color:rgba(212,245,229,.75); margin:0 0 14px; font-size:.9rem; }
        .pg-hero-divider {
            width:48px; height:2px; border-radius:2px; margin:0 0 12px;
            background:linear-gradient(90deg,transparent,#24e78f,transparent);
        }
        .pg-hero-actions {
            position:relative; z-index:2;
            display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; margin-top:4px;
        }
        .pg-hero-date { color:rgba(212,245,229,.65); font-size:.82rem; align-self:center; }
        .pg-hero-btn {
            background:rgba(36,231,143,.1); backdrop-filter:blur(8px);
            border:1px solid rgba(36,231,143,.3); color:#d4f5e5;
            border-radius:10px; padding:8px 16px;
            font-size:.84rem; font-weight:700; cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:7px;
            transition:background .2s, transform .18s, box-shadow .2s;
        }
        .pg-hero-btn:hover {
            background:rgba(36,231,143,.22); border-color:rgba(36,231,143,.55);
            transform:translateY(-2px); box-shadow:0 4px 16px rgba(36,231,143,.2);
            color:#d4f5e5; text-decoration:none;
        }
        .pg-hero-layout {
            display:flex; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap; gap:14px; position:relative; z-index:2;
        }
        .mh-logo-watermark {
            position:absolute; top:50%; right:3%;
            transform:translateY(-50%);
            width:180px; height:auto; pointer-events:none; z-index:0;
            opacity:0.50;
        }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
  <!-- Main Sidebar Container -->
  <?php include '../includes/sidebar.php'; ?>
  <!-- Content Wrapper. Contains page content -->
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
                <div class="pg-hero-title">Employment Statuses</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Manage employment statuses</p>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-3">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Add New Status</h3>
              </div>
              <form method="POST">
                <div class="card-body">
                  <div class="form-group">
                    <label for="status_name">Status Name</label>
                    <input type="text" class="form-control" id="status_name" name="status_name" required>
                  </div>
                  <div class="form-group">
                    <label for="color">Color</label>
                    <input type="color" class="form-control" id="color" name="color" value="#007bff" required>
                  </div>
                </div>
                <div class="card-footer">
                  <button type="submit" name="add_status" class="btn btn-primary">Add Status</button>
                </div>
              </form>
            </div>
          </div>
          <div class="col-md-9">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Manage Statuses</h3>
              </div>
              <div class="card-body">
                <form method="POST">
                  <table id="example1" class="table table-bordered table-striped">
                    <thead>
                      <tr>
                        <th>Status Name</th>
                        <th>Color</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($statuses as $status): ?>
                      <tr>
                        <td><?= htmlspecialchars($status['status_name']) ?></td>
                        <td>
                          <input type="color" name="colors[<?= $status['status_id'] ?>]" 
                                value="<?= htmlspecialchars($status['color']) ?>">
                        </td>
                        <td>
                          <div class="btn-group">
                            <button type="button" class="btn btn-info" data-toggle="modal" 
                                    data-target="#editModal<?= $status['status_id'] ?>">
                              <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger delete-btn" 
                                    data-id="<?= $status['status_id'] ?>" 
                                    data-name="<?= htmlspecialchars($status['status_name']) ?>">
                              <i class="fas fa-trash"></i>
                            </button>
                          </div>
                          
                          <!-- Edit Modal -->
                          <div class="modal fade" id="editModal<?= $status['status_id'] ?>">
                            <div class="modal-dialog">
                              <div class="modal-content">
                                <div class="modal-header">
                                  <h4 class="modal-title">Edit Status</h4>
                                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                  </button>
                                </div>
                                <form method="POST">
                                  <div class="modal-body">
                                    <input type="hidden" name="id" value="<?= $status['status_id'] ?>">
                                    <div class="form-group">
                                      <label>Status Name</label>
                                      <input type="text" class="form-control" name="status_name" 
                                             value="<?= htmlspecialchars($status['status_name']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                      <label>Color</label>
                                      <input type="color" class="form-control" name="color" 
                                             value="<?= htmlspecialchars($status['color']) ?>" required>
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                    <button type="submit" name="update_status" class="btn btn-primary">Save changes</button>
                                  </div>
                                </form>
                              </div>
                            </div>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                  <div class="mt-3">
                    <button type="submit" name="update_colors" class="btn btn-primary">Save All Changes</button>
                  </div>
                </form>
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
    const statusId = $(this).data('id');
    const statusName = $(this).data('name');
    
    // First clear any existing session messages
    fetch('clear_session.php')
        .then(() => {
            // Then show the delete confirmation
            Swal.fire({
                title: 'Delete Status?',
                text: `Are you sure you want to delete "${statusName}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Delete',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('delete_estatus.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `status_id=${statusId}`
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
    $("#example1").DataTable({
        responsive: true,
        lengthChange: true, // Changed to true to show length menu
        autoWidth: false,
        pageLength: 5, // Default number of rows per page
        lengthMenu: [[5, 10, 15, 20, 100], [5, 10, 15, 20, 100]], // Pagination options
        columnDefs: [
            { responsivePriority: 1, targets: 1 }, // Picture
            { responsivePriority: 2, targets: 2 }, // Name
            { responsivePriority: 3, targets: -1 } // Actions
        ],
        dom: '<"top"lf>rt<"bottom"ip>', // Layout control
        language: {
            lengthMenu: "Show _MENU_ entries per page",
            paginate: {
                previous: "&laquo;",
                next: "&raquo;"
            }
        }
    });
});
</script>
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
<?php endif; ?>
</body>
</html>