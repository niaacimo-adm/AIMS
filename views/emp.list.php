<?php
session_start();
require_once '../config/database.php';
require '../vendor/autoload.php';

// Handle Excel import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $_FILES['excel_file']['error']);
        }
        $allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($_FILES['excel_file']['type'], $allowedTypes)) {
            throw new Exception("Only .xlsx files are allowed.");
        }
        $inputFileName = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        array_shift($rows);

        $database = new Database();
        $db = $database->getConnection();
        $successCount = 0;

        $validDefaults = [
            'employment_status_id'  => $db->query("SELECT MIN(status_id) FROM employment_status")->fetch_row()[0],
            'appointment_status_id' => $db->query("SELECT MIN(appointment_id) FROM appointment_status")->fetch_row()[0],
            'section_id'            => $db->query("SELECT MIN(section_id) FROM section")->fetch_row()[0],
            'office_id'             => $db->query("SELECT MIN(office_id) FROM office")->fetch_row()[0],
            'position_id'           => $db->query("SELECT MIN(position_id) FROM position")->fetch_row()[0]
        ];

        foreach ($rows as $row) {
            $employeeData = [
                'id_number'             => $row[0] ?? '',
                'first_name'            => $row[1] ?? '',
                'last_name'             => $row[2] ?? '',
                'email'                 => $row[3] ?? '',
                'phone_number'          => $row[4] ?? '',
                'employment_status_id'  => $validDefaults['employment_status_id'],
                'appointment_status_id' => $validDefaults['appointment_status_id'],
                'section_id'            => $validDefaults['section_id'],
                'office_id'             => $validDefaults['office_id'],
                'position_id'           => $validDefaults['position_id']
            ];
            $query = "INSERT INTO employee (id_number, first_name, last_name, email, phone_number,
                      employment_status_id, appointment_status_id, section_id, office_id, position_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bind_param("sssssiiiii",
                $employeeData['id_number'], $employeeData['first_name'], $employeeData['last_name'],
                $employeeData['email'], $employeeData['phone_number'],
                $employeeData['employment_status_id'], $employeeData['appointment_status_id'],
                $employeeData['section_id'], $employeeData['office_id'], $employeeData['position_id']
            );
            $stmt->execute();
            $successCount++;
        }
        $_SESSION['toast'] = ['type' => 'success', 'message' => "Successfully imported $successCount employees!"];
        header("Location: emp.list.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "Import failed: " . $e->getMessage()];
    }
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT e.*, es.status_name as employment_status, o.office_name, s.section_name,
            CONCAT(sh.first_name, ' ', sh.last_name) as section_head_name,
            us.unit_name as unit_section_name,
            CONCAT(uh.first_name, ' ', uh.last_name) as unit_head_name,
            p.position_name, ap.status_name as appointment_status, ap.color as appointment_color
          FROM employee e
          LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
          LEFT JOIN office o ON e.office_id = o.office_id
          LEFT JOIN section s ON e.section_id = s.section_id
          LEFT JOIN employee sh ON s.head_emp_id = sh.emp_id
          LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
          LEFT JOIN employee uh ON us.head_emp_id = uh.emp_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
          ORDER BY e.last_name ASC, e.first_name ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$employees = [];
while ($row = $result->fetch_assoc()) { $employees[] = $row; }
$result->free();
$stmt->close();

$stmt = $db->prepare("SELECT * FROM appointment_status");
$stmt->execute();
$result = $stmt->get_result();
$appointmentStatuses = [];
while ($row = $result->fetch_assoc()) { $appointmentStatuses[] = $row; }
$result->free();
$stmt->close();

$stmt = $db->prepare("SELECT * FROM employment_status");
$stmt->execute();
$result = $stmt->get_result();
$employmentStatuses = [];
while ($row = $result->fetch_assoc()) { $employmentStatuses[] = $row; }
$result->free();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HR System | Employee List</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../css/module-theme.css">
</head>
<body class="hold-transition sidebar-mini emp-list-page">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar.php'; ?>

  <div class="content-wrapper">
    
        <!-- Page Hero -->
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
                    <div class="pg-hero-title"><i class="fas fa-users"></i>Employee Directory</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">View, search and manage all employees</p>
                    <p class="pg-hero-sub"><?= count($employees) ?> total employees</p>
                    <div class="pg-hero-divider"></div>
                </div>
                
                <div class="page-toolbar-right">
                  <!-- View Toggle -->
                  <div class="btn-group">
                    <button id="tableViewBtn" class="btn-toolbar-primary active" title="Table View">
                      <i class="fas fa-table"></i>
                      <span class="d-none d-sm-inline ml-1">Table</span>
                    </button>
                    <button id="gridViewBtn" class="btn-toolbar-primary" title="Grid View">
                      <i class="fas fa-th-large"></i>
                      <span class="d-none d-sm-inline ml-1">Grid</span>
                    </button>
                  </div>

                  <!-- Action Buttons -->
                  <a href="emp.create.php" class="btn-toolbar-add" title="Add Employee">
                    <i class="fas fa-plus"></i>
                    <span class="d-none d-sm-inline ml-1">Add New</span>
                  </a>
                  <button type="button" class="btn-toolbar-primary" data-toggle="modal" data-target="#importModal" title="Import">
                    <i class="fas fa-file-import"></i>
                    <span class="d-none d-sm-inline ml-1">Import</span>
                  </button>
                  <a href="emp.export.php" class="btn-toolbar-primary" title="Export">
                    <i class="fas fa-file-export"></i>
                    <span class="d-none d-sm-inline ml-1">Export</span>
                  </a>
                  <button type="button" class="btn-toolbar-primary" id="advancedSearchBtn" title="Advanced Search">
                    <i class="fas fa-sliders-h"></i>
                    <span class="d-none d-sm-inline ml-1">Filters</span>
                    <span id="activeFilterCount" class="btn-toolbar-filter-badge" style="display:none;">0</span>
                  </button>
                </div>
            </div>
        </div>

    <section class="content">
      <div class="container-fluid">

        <?php if (isset($_GET['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i>
            <?= htmlspecialchars($_GET['success'] == '1' ? "Employee created successfully!" : "Employee assignment updated successfully!") ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
          </div>
        <?php endif; ?>

        <!-- ===== MAIN CARD ===== -->
        <div class="card list-card">
          <div class="card-body">

            <!-- TABLE VIEW -->
            <div class="table-view-wrapper">
              <div class="table-responsive">
                <table id="employeeTable" class="table table-striped mod-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Picture</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Phone</th>
                      <th>Birthday</th>
                      <th>Employment Status</th>
                      <th>Appointment Status</th>
                      <th>Position</th>
                      <th>Office</th>
                      <th>Section</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($employees as $employee): ?>
                    <tr data-employment-status="<?= $employee['employment_status_id'] ?>"
                        data-appointment-status="<?= $employee['appointment_status_id'] ?>">
                      <td><?= htmlspecialchars($employee['id_number']) ?></td>
                      <td>
                        <?php
                        $imagePath = '../dist/img/employees/' . htmlspecialchars($employee['picture']);
                        if (!empty($employee['picture']) && file_exists($imagePath)): ?>
                          <img src="<?= $imagePath ?>" class="img-circle elevation-2"
                               style="width:44px;height:44px;object-fit:cover;border:2px solid var(--h-primary-dim);"
                               alt="<?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?>">
                        <?php else: ?>
                          <div class="text-center"><i class="fas fa-user-circle fa-2x text-muted"></i></div>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?></td>
                      <td><?= htmlspecialchars($employee['email']) ?></td>
                      <td><?= htmlspecialchars($employee['phone_number']) ?></td>
                      <td><?= htmlspecialchars($employee['bday']) ?></td>
                      <td>
                        <?php
                        $si = null;
                        foreach ($employmentStatuses as $s) { if ($s['status_id'] == $employee['employment_status_id']) { $si = $s; break; } }
                        if ($si): ?>
                          <span class="badge" style="background:<?= htmlspecialchars($si['color']) ?>;color:<?= (hexdec(substr($si['color'],1))>0xffffff/2)?'#000':'#fff' ?>">
                            <?= htmlspecialchars($si['status_name']) ?>
                          </span>
                        <?php else: ?><span class="badge badge-secondary">—</span><?php endif; ?>
                      </td>
                      <td>
                        <?php
                        $si = null;
                        foreach ($appointmentStatuses as $s) { if ($s['appointment_id'] == $employee['appointment_status_id']) { $si = $s; break; } }
                        if ($si): ?>
                          <span class="badge" style="background:<?= htmlspecialchars($si['color']) ?>;color:<?= (hexdec(substr($si['color'],1))>0xffffff/2)?'#000':'#fff' ?>">
                            <?= htmlspecialchars($si['status_name']) ?>
                          </span>
                        <?php else: ?><span class="badge badge-secondary">—</span><?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($employee['position_name']) ?></td>
                      <td><?= htmlspecialchars($employee['office_name']) ?></td>
                      <td>
                        <?= htmlspecialchars($employee['section_name']) ?>
                        <?php if (!empty($employee['unit_section_name'])): ?>
                          <small class="text-muted d-block">Unit: <?= htmlspecialchars($employee['unit_section_name']) ?></small>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="btn-group">
                          <a href="emp.edit.php?emp_id=<?= $employee['emp_id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                            <i class="fas fa-edit"></i>
                          </a>
                          <a href="emp.profile.php?emp_id=<?= $employee['emp_id'] ?>" class="btn btn-sm btn-secondary" title="View Profile">
                            <i class="fas fa-user"></i>
                          </a>
                          <button type="button" class="btn btn-sm btn-danger delete-employee"
                                  data-emp-id="<?= $employee['emp_id'] ?>"
                                  data-emp-name="<?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?>"
                                  data-emp-id-number="<?= htmlspecialchars($employee['id_number']) ?>"
                                  title="Delete">
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div><!-- /.table-view-wrapper -->

            <!-- GRID VIEW -->
            <div class="grid-view-wrapper" id="gridViewWrapper">
              <!-- Search bar -->
              <div class="grid-search-bar">
                <input type="text" id="gridSearch" class="grid-search-input"
                       placeholder="Search employees by name, ID, position…">
                <span class="grid-count-badge" id="gridCountBadge">
                  <?= count($employees) ?> employees
                </span>
              </div>

              <!-- Cards -->
              <div class="emp-grid" id="empGrid">
                <?php foreach ($employees as $employee):
                  $imagePath = '../dist/img/employees/' . htmlspecialchars($employee['picture']);
                  $hasImg = !empty($employee['picture']) && file_exists($imagePath);
                  $initials = strtoupper(substr($employee['first_name'],0,1).substr($employee['last_name'],0,1));

                  // Employment status
                  $empSt = null;
                  foreach ($employmentStatuses as $s) { if ($s['status_id'] == $employee['employment_status_id']) { $empSt = $s; break; } }
                  // Appointment status
                  $appSt = null;
                  foreach ($appointmentStatuses as $s) { if ($s['appointment_id'] == $employee['appointment_status_id']) { $appSt = $s; break; } }
                ?>
                <div class="emp-card"
                     data-search="<?= strtolower(htmlspecialchars($employee['first_name'].' '.$employee['last_name'].' '.$employee['id_number'].' '.($employee['position_name'] ?? '').' '.($employee['office_name'] ?? ''))) ?>"
                     data-position="<?= $employee['position_id'] ?>"
                     data-office="<?= $employee['office_id'] ?>"
                     data-section="<?= $employee['section_id'] ?>"
                     data-employment-status="<?= $employee['employment_status_id'] ?>"
                     data-appointment-status="<?= $employee['appointment_status_id'] ?>"
                     data-last-name="<?= htmlspecialchars(strtolower($employee['last_name'])) ?>">

                  <div class="emp-card-accent"></div>

                  <div class="emp-card-photo-wrap">
                    <?php if ($hasImg): ?>
                      <img src="<?= $imagePath ?>" class="emp-card-avatar"
                           alt="<?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?>">
                    <?php else: ?>
                      <div class="emp-card-avatar-placeholder"><?= $initials ?></div>
                    <?php endif; ?>
                  </div>

                  <div class="emp-card-body">
                    <h5 class="emp-card-name">
                      <?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?>
                    </h5>
                    <p class="emp-card-position"><?= htmlspecialchars($employee['position_name'] ?? '—') ?></p>

                    <div class="emp-card-badges">
                      <?php if ($empSt): ?>
                        <span class="emp-card-badge"
                              style="background:<?= htmlspecialchars($empSt['color']) ?>;
                                     color:<?= (hexdec(substr($empSt['color'],1))>0xffffff/2)?'#000':'#fff' ?>">
                          <?= htmlspecialchars($empSt['status_name']) ?>
                        </span>
                      <?php endif; ?>
                      <?php if ($appSt): ?>
                        <span class="emp-card-badge"
                              style="background:<?= htmlspecialchars($appSt['color']) ?>;
                                     color:<?= (hexdec(substr($appSt['color'],1))>0xffffff/2)?'#000':'#fff' ?>">
                          <?= htmlspecialchars($appSt['status_name']) ?>
                        </span>
                      <?php endif; ?>
                    </div>

                    <div class="emp-card-meta">
                      <?php if (!empty($employee['id_number'])): ?>
                      <div class="emp-card-meta-row">
                        <i class="fas fa-id-card"></i>
                        <span><?= htmlspecialchars($employee['id_number']) ?></span>
                      </div>
                      <?php endif; ?>
                      <?php if (!empty($employee['email'])): ?>
                      <div class="emp-card-meta-row">
                        <i class="fas fa-envelope"></i>
                        <span><?= htmlspecialchars($employee['email']) ?></span>
                      </div>
                      <?php endif; ?>
                      <?php if (!empty($employee['phone_number'])): ?>
                      <div class="emp-card-meta-row">
                        <i class="fas fa-phone"></i>
                        <span><?= htmlspecialchars($employee['phone_number']) ?></span>
                      </div>
                      <?php endif; ?>
                      <?php if (!empty($employee['office_name'])): ?>
                      <div class="emp-card-meta-row">
                        <i class="fas fa-building"></i>
                        <span><?= htmlspecialchars($employee['office_name']) ?></span>
                      </div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="emp-card-actions">
                    <a href="emp.edit.php?emp_id=<?= $employee['emp_id'] ?>" class="emp-card-action-btn edit" title="Edit">
                      <i class="fas fa-edit"></i>
                      <span class="d-none d-md-inline" style="font-size:11px;">Edit</span>
                    </a>
                    <a href="emp.profile.php?emp_id=<?= $employee['emp_id'] ?>" class="emp-card-action-btn view" title="Profile">
                      <i class="fas fa-user"></i>
                      <span class="d-none d-md-inline" style="font-size:11px;">Profile</span>
                    </a>
                    <button type="button" class="emp-card-action-btn del delete-employee"
                            data-emp-id="<?= $employee['emp_id'] ?>"
                            data-emp-name="<?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?>"
                            data-emp-id-number="<?= htmlspecialchars($employee['id_number']) ?>"
                            title="Delete">
                      <i class="fas fa-trash"></i>
                      <span class="d-none d-md-inline" style="font-size:11px;">Delete</span>
                    </button>
                  </div>
                </div>
                <?php endforeach; ?>
              </div><!-- /#empGrid -->

              <!-- Grid pagination -->
              <div class="grid-pagination-bar" id="gridPaginationBar"></div>
            </div><!-- /#gridViewWrapper -->

          </div><!-- /.card-body -->
        </div><!-- /.list-card -->

      </div><!-- /.container-fluid -->
    </section>
  </div><!-- /.content-wrapper -->

  <?php include '../includes/mainfooter.php'; ?>
</div><!-- ./wrapper -->

<!-- ===== IMPORT MODAL ===== -->
<div class="modal fade scrum-style-modal" id="importModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-file-import"></i>Import Employees from Excel</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form action="emp.list.php" method="post" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="form-group">
            <label for="excel_file">Excel File (.xlsx)</label>
            <input type="file" class="form-control-file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
            <small class="form-text text-muted">
              Upload a .xlsx / .xls file.
              <a href="path/to/sample_template.xlsx" download>Download sample template</a>
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i>Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== ADVANCED SEARCH PANEL ===== -->
<div class="adv-search-backdrop" id="advSearchBackdrop"></div>
<div class="adv-search-panel" id="advSearchPanel">
  <div class="adv-search-header">
    <h4><i class="fas fa-sliders-h mr-2"></i>Advanced Filters</h4>
    <button class="adv-search-close" id="advSearchClose"><i class="fas fa-times"></i></button>
  </div>
  <div class="adv-search-body">

    <div class="adv-search-section">
      <h6><i class="fas fa-user mr-1"></i> Basic Information</h6>
      <div class="adv-field"><label>Name</label><input type="text" class="adv-input" id="searchName" placeholder="Search by name…"></div>
      <div class="adv-field"><label>ID Number</label><input type="text" class="adv-input" id="searchIdNumber" placeholder="Enter employee ID…"></div>
      <div class="adv-field"><label>Email</label><input type="text" class="adv-input" id="searchEmail" placeholder="Search by email…"></div>
      <div class="adv-field"><label>Phone</label><input type="text" class="adv-input" id="searchPhone" placeholder="Search by phone…"></div>
    </div>

    <div class="adv-search-section">
      <h6><i class="fas fa-briefcase mr-1"></i> Employment Details</h6>
      <div class="adv-field">
        <label>Position</label>
        <select class="adv-input" id="searchPosition">
          <option value="">All Positions</option>
          <?php
          $posStmt = $db->prepare("SELECT position_id, position_name FROM position ORDER BY position_name");
          $posStmt->execute();
          $posRes = $posStmt->get_result();
          while ($p = $posRes->fetch_assoc()): ?>
            <option value="<?= $p['position_id'] ?>"><?= htmlspecialchars($p['position_name']) ?></option>
          <?php endwhile; $posRes->free(); $posStmt->close(); ?>
        </select>
      </div>
      <div class="adv-field">
        <label>Office</label>
        <select class="adv-input" id="searchOffice">
          <option value="">All Offices</option>
          <?php
          $offStmt = $db->prepare("SELECT office_id, office_name FROM office ORDER BY office_name");
          $offStmt->execute();
          $offRes = $offStmt->get_result();
          while ($o = $offRes->fetch_assoc()): ?>
            <option value="<?= $o['office_id'] ?>"><?= htmlspecialchars($o['office_name']) ?></option>
          <?php endwhile; $offRes->free(); $offStmt->close(); ?>
        </select>
      </div>
      <div class="adv-field">
        <label>Section</label>
        <select class="adv-input" id="searchSection">
          <option value="">All Sections</option>
          <?php
          $secStmt = $db->prepare("SELECT section_id, section_name FROM section ORDER BY section_name");
          $secStmt->execute();
          $secRes = $secStmt->get_result();
          while ($sc = $secRes->fetch_assoc()): ?>
            <option value="<?= $sc['section_id'] ?>"><?= htmlspecialchars($sc['section_name']) ?></option>
          <?php endwhile; $secRes->free(); $secStmt->close(); ?>
        </select>
      </div>
    </div>

    <div class="adv-search-section">
      <h6><i class="fas fa-tags mr-1"></i> Status Filters</h6>
      <div class="adv-field">
        <label>Employment Status</label>
        <select class="adv-input" id="searchEmploymentStatus">
          <option value="">All Statuses</option>
          <?php foreach ($employmentStatuses as $s): ?>
            <option value="<?= $s['status_id'] ?>"><?= htmlspecialchars($s['status_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="adv-field">
        <label>Appointment Status</label>
        <select class="adv-input" id="searchAppointmentStatus">
          <option value="">All Statuses</option>
          <?php foreach ($appointmentStatuses as $s): ?>
            <option value="<?= $s['appointment_id'] ?>"><?= htmlspecialchars($s['status_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="adv-search-section">
      <h6><i class="fas fa-calendar-alt mr-1"></i> Date Range</h6>
      <div class="adv-field"><label>From</label><input type="date" class="adv-input" id="searchDateFrom"></div>
      <div class="adv-field"><label>To</label><input type="date" class="adv-input" id="searchDateTo"></div>
    </div>

  </div>
  <div class="adv-search-footer">
    <button class="btn-adv-clear" id="clearSearchFilters"><i class="fas fa-eraser mr-1"></i>Clear All</button>
    <button class="btn-adv-apply" id="applySearchFilters"><i class="fas fa-filter mr-1"></i>Apply Filters</button>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Toast -->
<?php if (isset($_SESSION['toast'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const t = <?php echo json_encode($_SESSION['toast']); ?>;
    Swal.fire({ toast:true, position:'top-end', icon:t.type, title:t.message, showConfirmButton:false, timer:3000, timerProgressBar:true,
        didOpen:(el)=>{ el.addEventListener('mouseenter',Swal.stopTimer); el.addEventListener('mouseleave',Swal.resumeTimer); }
    });
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

<!-- ===== MAIN SCRIPTS ===== -->
<script>
$(document).ready(function () {

    /* -------------------------------------------------------
       DataTable init
    ------------------------------------------------------- */
    var empTable = $('#employeeTable').DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[5,10,15,20,100],[5,10,15,20,100]],
        columnDefs: [
            { responsivePriority:1, targets:1 },
            { responsivePriority:2, targets:2 },
            { responsivePriority:3, targets:-1 }
        ],
        order: [[2,'asc']],
        dom: '<"top"lf>rt<"bottom"ip>',
        language: { lengthMenu:'Show _MENU_ entries', paginate:{ previous:'&laquo;', next:'&raquo;' } }
    });

    /* -------------------------------------------------------
       Grid pagination state
    ------------------------------------------------------- */
    const ITEMS_PER_PAGE = 16;
    let currentPage = 1;
    let visibleIndices = [];

    function getAllCards() { return $('.emp-card'); }

    function rebuildVisibleIndices() {
        visibleIndices = [];
        getAllCards().each(function (i) {
            if ($(this).data('visible') !== false) visibleIndices.push(i);
        });
    }

    function renderGridPage() {
        getAllCards().hide();
        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        const end   = start + ITEMS_PER_PAGE;
        visibleIndices.slice(start, end).forEach(i => getAllCards().eq(i).show());
        renderPagination();
        updateCountBadge();
    }

    function renderPagination() {
        const totalPages = Math.ceil(visibleIndices.length / ITEMS_PER_PAGE);
        const bar = $('#gridPaginationBar').empty();
        if (totalPages <= 1) return;

        bar.append(`<span class="grid-pagination-info">Page ${currentPage} of ${totalPages} &nbsp;·&nbsp; ${visibleIndices.length} employees</span>`);

        const btns = $('<div class="grid-pagination-btns"></div>');
        btns.append(`<button class="grid-page-btn" id="prevPage" ${currentPage===1?'disabled':''}><i class="fas fa-chevron-left"></i> Prev</button>`);

        // Page number buttons (max 5 visible)
        const half = 2;
        let s = Math.max(1, currentPage - half);
        let e = Math.min(totalPages, s + 4);
        s = Math.max(1, e - 4);
        for (let p = s; p <= e; p++) {
            btns.append(`<button class="grid-page-btn${p===currentPage?' active':''}" data-page="${p}">${p}</button>`);
        }

        btns.append(`<button class="grid-page-btn" id="nextPage" ${currentPage===totalPages?'disabled':''}> Next <i class="fas fa-chevron-right"></i></button>`);
        bar.append(btns);

        btns.find('#prevPage').click(function () { if (currentPage > 1) { currentPage--; renderGridPage(); } });
        btns.find('#nextPage').click(function () { if (currentPage < totalPages) { currentPage++; renderGridPage(); } });
        btns.find('[data-page]').click(function () { currentPage = parseInt($(this).data('page')); renderGridPage(); });
    }

    function updateCountBadge() {
        $('#gridCountBadge').text(visibleIndices.length + ' employee' + (visibleIndices.length !== 1 ? 's' : ''));
    }

    /* -------------------------------------------------------
       Sort grid cards alphabetically by last name
    ------------------------------------------------------- */
    function sortCards() {
        const cards = getAllCards().get();
        cards.sort((a, b) => ($(a).data('last-name') || '').localeCompare($(b).data('last-name') || ''));
        $('#empGrid').append(cards);
    }

    /* -------------------------------------------------------
       Init grid as default view
    ------------------------------------------------------- */
    function initGrid() {
        sortCards();
        getAllCards().each(function () { $(this).data('visible', true); });
        rebuildVisibleIndices();
        renderGridPage();
    }

    initGrid();
    // Default: show grid, hide table
    $('.table-view-wrapper').hide();
    $('#gridViewWrapper').show();
    $('#gridViewBtn').addClass('active');
    $('#tableViewBtn').removeClass('active');

    /* -------------------------------------------------------
       View toggle
    ------------------------------------------------------- */
    $('#tableViewBtn').click(function () {
        $('.table-view-wrapper').show();
        $('#gridViewWrapper').hide();
        $(this).addClass('active');
        $('#gridViewBtn').removeClass('active');
        empTable.columns.adjust().responsive.recalc();
    });

    $('#gridViewBtn').click(function () {
        $('.table-view-wrapper').hide();
        $('#gridViewWrapper').show();
        $(this).addClass('active');
        $('#tableViewBtn').removeClass('active');
        sortCards();
        getAllCards().each(function () { $(this).data('visible', true); });
        rebuildVisibleIndices();
        currentPage = 1;
        renderGridPage();
    });

    /* -------------------------------------------------------
       Grid search (live filter)
    ------------------------------------------------------- */
    $('#gridSearch').on('input', function () {
        const term = $(this).val().toLowerCase().trim();
        getAllCards().each(function () {
            const match = !term || ($(this).data('search') || '').includes(term);
            $(this).data('visible', match);
        });
        rebuildVisibleIndices();
        currentPage = 1;
        renderGridPage();
    });

    /* -------------------------------------------------------
       Advanced search panel
    ------------------------------------------------------- */
    let activeFilters = {};
    let filterCount = 0;

    $('#advancedSearchBtn').click(function () {
        $('#advSearchPanel').addClass('show');
        $('#advSearchBackdrop').addClass('show');
        $('body').css('overflow', 'hidden');
    });

    function closeAdvSearch() {
        $('#advSearchPanel').removeClass('show');
        $('#advSearchBackdrop').removeClass('show');
        $('body').css('overflow', '');
    }

    $('#advSearchClose, #advSearchBackdrop').click(closeAdvSearch);

    $('#clearSearchFilters').click(function () {
        $('#advSearchPanel .adv-input').val('');
        activeFilters = {}; filterCount = 0;
        updateFilterBadge();
        resetFilters();
        closeAdvSearch();
    });

    $('#applySearchFilters').click(function () {
        activeFilters = {
            name:               $('#searchName').val().trim().toLowerCase(),
            idNumber:           $('#searchIdNumber').val().trim().toLowerCase(),
            email:              $('#searchEmail').val().trim().toLowerCase(),
            phone:              $('#searchPhone').val().trim().toLowerCase(),
            position:           $('#searchPosition').val(),
            office:             $('#searchOffice').val(),
            section:            $('#searchSection').val(),
            employmentStatus:   $('#searchEmploymentStatus').val(),
            appointmentStatus:  $('#searchAppointmentStatus').val()
        };
        filterCount = Object.values(activeFilters).filter(v => v !== '' && v !== null).length;
        updateFilterBadge();
        applyFilters();
        closeAdvSearch();
    });

    function updateFilterBadge() {
        const badge = $('#activeFilterCount');
        filterCount > 0 ? badge.text(filterCount).show() : badge.hide();
    }

    function resetFilters() {
        if ($('#tableViewBtn').hasClass('active')) {
            empTable.search('').columns().search('').draw();
            $.fn.dataTable.ext.search = [];
        } else {
            getAllCards().each(function () { $(this).data('visible', true); });
            rebuildVisibleIndices();
            currentPage = 1;
            renderGridPage();
        }
    }

    function applyFilters() {
        if ($('#tableViewBtn').hasClass('active')) {
            applyTableFilters();
        } else {
            applyGridFilters();
        }
    }

    function applyTableFilters() {
        empTable.search('').columns().search('');
        $.fn.dataTable.ext.search = [];
        if (!filterCount) { empTable.draw(); return; }

        if (activeFilters.name)     empTable.column(2).search(activeFilters.name, true, false);
        if (activeFilters.idNumber) empTable.column(0).search(activeFilters.idNumber, true, false);
        if (activeFilters.email)    empTable.column(3).search(activeFilters.email, true, false);
        if (activeFilters.phone)    empTable.column(4).search(activeFilters.phone, true, false);

        if (activeFilters.employmentStatus || activeFilters.appointmentStatus) {
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                const row = empTable.row(dataIndex).node();
                if (!row) return true;
                let emMatch = true, apMatch = true;
                if (activeFilters.employmentStatus)
                    emMatch = $(row).data('employment-status') && $(row).data('employment-status').toString() === activeFilters.employmentStatus;
                if (activeFilters.appointmentStatus)
                    apMatch = $(row).data('appointment-status') && $(row).data('appointment-status').toString() === activeFilters.appointmentStatus;
                return emMatch && apMatch;
            });
        }
        empTable.draw();
        $.fn.dataTable.ext.search = [];
    }

    function applyGridFilters() {
        getAllCards().each(function () {
            const card = $(this);
            const data = (card.data('search') || '').toLowerCase();
            let match = true;

            if (activeFilters.name    && !data.includes(activeFilters.name))     match = false;
            if (activeFilters.idNumber && !data.includes(activeFilters.idNumber)) match = false;
            if (activeFilters.email   && !data.includes(activeFilters.email))    match = false;
            if (activeFilters.phone   && !data.includes(activeFilters.phone))    match = false;
            if (activeFilters.position && card.data('position').toString() !== activeFilters.position) match = false;
            if (activeFilters.office   && card.data('office').toString()   !== activeFilters.office)   match = false;
            if (activeFilters.section  && card.data('section').toString()  !== activeFilters.section)  match = false;
            if (activeFilters.employmentStatus  && card.data('employment-status').toString() !== activeFilters.employmentStatus)  match = false;
            if (activeFilters.appointmentStatus && card.data('appointment-status').toString() !== activeFilters.appointmentStatus) match = false;

            card.data('visible', match);
        });
        rebuildVisibleIndices();
        currentPage = 1;
        renderGridPage();
    }

    /* -------------------------------------------------------
       Delete employee
    ------------------------------------------------------- */
    $(document).on('click', '.delete-employee', function () {
        const empId       = $(this).data('emp-id');
        const empName     = $(this).data('emp-name');
        const empIdNumber = $(this).data('emp-id-number');

        Swal.fire({
            title: 'Confirm Deletion',
            html: `<strong>You are about to delete:</strong><br>${empName}<br><br>
                   <span class="text-danger">This action cannot be undone!</span>
                   <div class="mt-3">
                     <label class="form-label text-left d-block"><small>Enter Employee ID to confirm:</small></label>
                     <div class="input-group">
                       <input type="password" id="confirmEmpId" class="form-control" placeholder="Employee ID"
                              style="border:1px solid #ddd;border-radius:4px 0 0 4px;padding:8px;">
                       <div class="input-group-append">
                         <button type="button" id="toggleIdVis" class="btn btn-outline-secondary"
                                 style="border:1px solid #ddd;border-left:none;border-radius:0 4px 4px 0;">
                           <i class="fas fa-eye"></i>
                         </button>
                       </div>
                     </div>
                     <div id="empIdError" class="text-danger small mt-1" style="display:none;">ID does not match!</div>
                   </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            buttonsStyling: true,
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const v = document.getElementById('confirmEmpId').value.trim();
                const err = document.getElementById('empIdError');
                if (!v) { err.textContent = 'Please enter the Employee ID'; err.style.display = 'block'; return false; }
                if (v !== empIdNumber.toString()) { err.textContent = 'ID does not match!'; err.style.display = 'block'; return false; }
                return true;
            },
            didOpen: () => {
                const input = document.getElementById('confirmEmpId');
                input.focus();
                document.getElementById('toggleIdVis').addEventListener('click', function () {
                    const t = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', t);
                    this.innerHTML = t === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
                });
                input.addEventListener('input', function () {
                    const err = document.getElementById('empIdError');
                    if (this.value.trim() === empIdNumber.toString()) {
                        err.style.display = 'none'; this.style.borderColor = '#28a745';
                    } else if (this.value.trim()) {
                        this.style.borderColor = '#dc3545';
                    } else {
                        this.style.borderColor = '#ddd'; err.style.display = 'none';
                    }
                });
            }
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({ title:'Deleting…', html:'Please wait', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });
                $.ajax({
                    url: 'emp.delete.php', type: 'POST', data: { emp_id: empId }, dataType: 'json',
                    success: function (res) {
                        if (res && res.success) {
                            Swal.fire({ icon:'success', title:'Deleted!', text:res.message, timer:2500, showConfirmButton:false })
                                .then(() => location.reload());
                        } else {
                            Swal.fire({ icon:'error', title:'Failed', text: res ? res.message : 'Unknown error' });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon:'error', title:'Error', text:'Server error. Please try again.' });
                    }
                });
            }
        });
    });

});
</script>
</body>
</html>