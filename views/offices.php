<?php
require_once '../config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

function validateOfficeManager($db, $emp_id, $current_office_id = null) {
    if (empty($emp_id)) {
        return false; // No employee selected, so no validation needed
    }

    // Check if employee is already a section or unit head
    $stmt = $db->prepare("SELECT COUNT(*) FROM section WHERE head_emp_id = ?");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $is_section_head = $stmt->get_result()->fetch_row()[0];
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM unit_section WHERE head_emp_id = ?");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $is_unit_head = $stmt->get_result()->fetch_row()[0];
    
    if ($is_section_head > 0 || $is_unit_head > 0) {
        return "This employee is already assigned as a section or unit head.";
    }
    
    // Check if employee is already a manager elsewhere (excluding current office if editing)
    $query = "SELECT COUNT(*) FROM office WHERE manager_emp_id = ?";
    $params = [$emp_id];
    $types = "i";
    
    if ($current_office_id !== null) {
        $query .= " AND office_id != ?";
        $params[] = $current_office_id;
        $types .= "i";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $is_manager_elsewhere = $stmt->get_result()->fetch_row()[0];
    
    if ($is_manager_elsewhere > 0) {
        return "This employee is already a manager of another office.";
    }
    
    return false;
}
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Common variables for both add and update
    $manager_emp_id = !empty($_POST['manager_emp_id']) ? $_POST['manager_emp_id'] : null;
    $office_name = trim($_POST['office_name'] ?? '');
    $office_address = $_POST['office_address'] ?? '';
    $is_main_office = isset($_POST['is_main_office']) ? 1 : 0;
    $parent_office_id = $_POST['parent_office_id'] ?? null;
    
    // Validate main office selection
    if ($is_main_office && $parent_office_id) {
        $_SESSION['swal'] = [
            'type' => 'error',
            'title' => 'Error!',
            'text' => 'A main office cannot have a parent office.'
        ];
        header("Location: offices.php");
        exit();
    }
    
    // Validate manager assignment if provided
    if ($manager_emp_id) {
        $current_office_id = $_POST['id'] ?? null; // Only exists for update
        $validation = validateOfficeManager($db, $manager_emp_id, $current_office_id);
        if ($validation) {
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => $validation
            ];
            header("Location: offices.php");
            exit();
        }
    }
    
    // Add new office
    if (isset($_POST['add_office'])) {
        if (!empty($office_name)) {
            // Convert empty parent_office_id to NULL
            $parent_office_id = !empty($parent_office_id) ? $parent_office_id : null;
            
            $stmt = $db->prepare("INSERT INTO office (office_name, office_address, manager_emp_id, is_main_office, parent_office_id) 
                                VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiii", $office_name, $office_address, $manager_emp_id, $is_main_office, $parent_office_id);
            
            if ($stmt->execute()) {
                // Update is_manager flag for the employee
                if ($manager_emp_id) {
                    $stmt = $db->prepare("UPDATE employee SET is_manager = 1 WHERE emp_id = ?");
                    $stmt->bind_param("i", $manager_emp_id);
                    $stmt->execute();
                }
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Office added successfully!'
                ];
                header("Location: offices.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to add office: ' . $db->error
                ];
            }
        }
    }
    // Update office
    if (isset($_POST['update_status'])) {
        $id = $_POST['id'];
        
        if (!empty($office_name)) {
            // Convert empty parent_office_id to NULL
            $parent_office_id = !empty($parent_office_id) ? $parent_office_id : null;
            
            // Get current office data
            $stmt = $db->prepare("SELECT manager_emp_id FROM office WHERE office_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $current_office = $stmt->get_result()->fetch_assoc();
            
            // Clear previous manager flag if manager changed
            if ($manager_emp_id != $current_office['manager_emp_id'] && $current_office['manager_emp_id']) {
                $stmt = $db->prepare("UPDATE employee SET is_manager = 0 WHERE emp_id = ?");
                $stmt->bind_param("i", $current_office['manager_emp_id']);
                $stmt->execute();
            }
            
            $stmt = $db->prepare("UPDATE office SET office_name = ?, office_address = ?, manager_emp_id = ?, 
                                is_main_office = ?, parent_office_id = ? WHERE office_id = ?");
            $stmt->bind_param("ssiiii", $office_name, $office_address, $manager_emp_id, $is_main_office, $parent_office_id, $id);
            
            if ($stmt->execute()) {
                // Update new manager flag
                if ($manager_emp_id) {
                    $stmt = $db->prepare("UPDATE employee SET is_manager = 1 WHERE emp_id = ?");
                    $stmt->bind_param("i", $manager_emp_id);
                    $stmt->execute();
                }
                
                $_SESSION['swal'] = [
                    'type' => 'success',
                    'title' => 'Success!',
                    'text' => 'Office updated successfully!'
                ];
                header("Location: offices.php");
                exit();
            } else {
                $_SESSION['swal'] = [
                    'type' => 'error',
                    'title' => 'Error!',
                    'text' => 'Failed to update office: ' . $db->error
                ];
            }
        }
    }

    // Validate parent office selection
    if (isset($_POST['has_parent_office']) && $_POST['has_parent_office'] && empty($_POST['parent_office_id'])) {
        $_SESSION['swal'] = [
            'type' => 'error',
            'title' => 'Error!',
            'text' => 'Please select a parent office when the checkbox is checked.'
        ];
        header("Location: offices.php");
        exit();
    }

    // Validate that parent office is not a main office
    if (!empty($_POST['parent_office_id'])) {
        $stmt = $db->prepare("SELECT is_main_office FROM office WHERE office_id = ?");
        $stmt->bind_param("i", $_POST['parent_office_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $parent_office = $result->fetch_assoc();
        
        if ($parent_office && $parent_office['is_main_office'] == 0) {
            $_SESSION['swal'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => 'The selected parent office must be a main office.'
            ];
            header("Location: offices.php");
            exit();
        }
    }
}

// Fetch all offices with their parent office names
$query = "SELECT o.*, parent.office_name as parent_office_name 
          FROM office o
          LEFT JOIN office parent ON o.parent_office_id = parent.office_id
          ORDER BY o.is_main_office DESC, o.office_name";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$offices = [];
while ($row = $result->fetch_assoc()) {
    $offices[] = $row;
}

// Fetch main offices for parent office dropdown
$main_offices = array_filter($offices, function($office) {
    return $office['is_main_office'] == 1;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Appointment Statuses</title>
  <?php include '../includes/header.php'; ?>
  <style>
    .input-group-text input[type="checkbox"] {
          margin: 0;
          cursor: pointer;
      }
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
                <div class="pg-hero-title">Offices</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Manage offices informa</p>
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
                    <h3 class="card-title">Add New Office</h3>
                </div>
                <form method="POST">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="office_name">Office Name</label>
                            <input type="text" class="form-control" id="office_name" name="office_name" required>
                        </div>
                        <div class="form-group">
                            <label for="office_address">Office Address</label>
                            <input type="text" class="form-control" id="office_address" name="office_address" required>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="is_main_office" name="is_main_office" value="1">
                                <label class="custom-control-label" for="is_main_office">Main Office</label>
                            </div>
                        </div>
                        <div class="form-group" id="parent_office_group">
                            <label for="parent_office_id">Parent Office</label>
                            <div class="input-group">
                                <select class="form-control" id="parent_office_id" name="parent_office_id">
                                    <option value="">-- Select Parent Office --</option>
                                    <?php foreach ($main_offices as $office): ?>
                                        <option value="<?= $office['office_id'] ?>"><?= htmlspecialchars($office['office_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="input-group-append">
                                    <div class="input-group-text">
                                        <input type="checkbox" id="has_parent_office" name="has_parent_office" value="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="manager_emp_id">Office Manager</label>
                            <select class="form-control select2-manager" id="manager_emp_id" name="manager_emp_id" multiple="multiple">
                                <option value="">-- Select Manager --</option>
                                <?php 
                                // Fetch employees who aren't already managers or heads elsewhere
                                $stmt = $db->prepare("SELECT e.emp_id, CONCAT(e.first_name, ' ', e.last_name) as full_name 
                                                    FROM employee e
                                                    LEFT JOIN section s ON e.emp_id = s.head_emp_id
                                                    LEFT JOIN unit_section us ON e.emp_id = us.head_emp_id
                                                    WHERE (e.is_manager = 0 AND s.head_emp_id IS NULL AND us.head_emp_id IS NULL)
                                                    OR e.emp_id = ?");
                                $current_manager = $office['manager_emp_id'] ?? 0;
                                $stmt->bind_param("i", $current_manager);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $available_managers = [];
                                while ($row = $result->fetch_assoc()) {
                                    $available_managers[] = $row;
                                }
                                
                                foreach ($available_managers as $manager): ?>
                                    <option value="<?= $manager['emp_id'] ?>">
                                        <?= htmlspecialchars($manager['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" name="add_office" class="btn btn-primary">Add Office</button>
                    </div>
                </form>
            </div>
          </div>
          <div class="col-md-9">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Manage Office Table</h3>
              </div>
              <div class="card-body">
                <form method="POST">
                    <table id="example1" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Office Name</th>
                                <th>Office Address</th>
                                <th>Designation Office</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($offices as $office): ?>
                            <tr>
                                <td><?= htmlspecialchars($office['office_name']) ?></td>
                                <td><?= htmlspecialchars($office['office_address']) ?></td>
                                <td>
                                    <?php if ($office['is_main_office'] == 1): ?>
                                        <span class="badge badge-success">Main Office</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Sub-Office</span>
                                        <?php if ($office['parent_office_name']): ?>
                                            <br><small class="text-muted"> <?= htmlspecialchars($office['parent_office_name']) ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-info" data-toggle="modal" 
                                                data-target="#editModal<?= $office['office_id'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger delete-btn" 
                                                data-id="<?= $office['office_id'] ?>" 
                                                data-name="<?= htmlspecialchars($office['office_name']) ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="modal fade" id="editModal<?= $office['office_id'] ?>">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h4 class="modal-title">Edit Office</h4>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="<?= $office['office_id'] ?>">
                                                        <div class="form-group">
                                                            <label>Office Name</label>
                                                            <input type="text" class="form-control" name="office_name" 
                                                                  value="<?= htmlspecialchars($office['office_name']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Office Address</label>
                                                            <input type="text" class="form-control" name="office_address" 
                                                                  value="<?= htmlspecialchars($office['office_address']) ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" id="is_main_office_<?= $office['office_id'] ?>" 
                                                                      name="is_main_office" value="1" <?= $office['is_main_office'] ? 'checked' : '' ?>>
                                                                <label class="custom-control-label" for="is_main_office_<?= $office['office_id'] ?>">Main Office</label>
                                                            </div>
                                                        </div>
                                                        <div class="form-group" id="parent_office_group_<?= $office['office_id'] ?>" style="<?= $office['is_main_office'] ? 'display: none;' : '' ?>">
                                                            <label>Parent Office</label>
                                                            <div class="input-group">
                                                                <select class="form-control" name="parent_office_id">
                                                                    <option value="">-- Select Parent Office --</option>
                                                                    <?php foreach ($main_offices as $main_office): 
                                                                        if ($main_office['office_id'] == $office['office_id']) continue; ?>
                                                                        <option value="<?= $main_office['office_id'] ?>" 
                                                                            <?= $main_office['office_id'] == $office['parent_office_id'] ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($main_office['office_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <div class="input-group-append">
                                                                    <div class="input-group-text">
                                                                        <input type="checkbox" id="has_parent_office_<?= $office['office_id'] ?>" name="has_parent_office" value="1" <?= $office['parent_office_id'] ? 'checked' : '' ?>>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Office Manager</label>
                                                            <select class="form-control select2-manager" name="manager_emp_id" multiple="multiple">
                                                                <option value="">-- Select Manager --</option>
                                                                <?php foreach ($available_managers as $manager): ?>
                                                                    <option value="<?= $manager['emp_id'] ?>" 
                                                                        <?= $manager['emp_id'] == $office['manager_emp_id'] ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($manager['full_name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
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

<script>
$(document).ready(function() {
$('#is_main_office').change(function() {
    if ($(this).is(':checked')) {
        $('#parent_office_group').hide();
        $('#parent_office_id').val('');
        $('#has_parent_office').prop('checked', false);
    } else {
        $('#parent_office_group').show();
    }
});

// For edit modals
$('[id^="is_main_office_"]').change(function() {
    const officeId = this.id.split('_')[2];
    if ($(this).is(':checked')) {
        $('#parent_office_group_' + officeId).hide();
        $('#parent_office_group_' + officeId + ' select').val('');
        $('#has_parent_office_' + officeId).prop('checked', false);
    } else {
        $('#parent_office_group_' + officeId).show();
    }
});

// Toggle parent office selection
$('[id^="has_parent_office"]').change(function() {
    const checkbox = $(this);
    const select = checkbox.closest('.input-group').find('select');
    
    if (checkbox.is(':checked')) {
        select.prop('required', true);
    } else {
        select.prop('required', false);
        select.val('');
    }
});
    
    // Initialize select2 for manager selection
    $('.select2-manager').select2({
        placeholder: "-- Select Manager --",
        allowClear: true,
        width: '100%',
        maximumSelectionLength: 1
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
    const officeId = $(this).data('id');
    const statusName = $(this).data('name');
    
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
                    return fetch('delete_office.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `office_id=${officeId}`
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