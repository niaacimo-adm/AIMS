<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

$module_name = 'Admin Dashboard';
$check_stmt = $db->prepare("SELECT is_under_maintenance FROM system_modules WHERE module_name = ?");
$check_stmt->bind_param("s", $module_name);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
  $module = $result->fetch_assoc();
  if ($module['is_under_maintenance'] && !hasPermission('manage_settings')) {
    // Redirect to maintenance page or show message
    $_SESSION['error'] = "The $module_name module is currently under maintenance. Please try again later.";
    header("Location: ../unauthorized.php");
    exit();
  }
}


// Get user role information
$stmt = $db->prepare("
    SELECT u.id, u.user, r.name as role_name, r.id as role_id 
    FROM users u
    LEFT JOIN user_roles r ON u.role_id = r.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  session_destroy();
  header("Location: login.php");
  exit();
}

$user = $result->fetch_assoc();
$role_id = $user['role_id'];
$role_name = $user['role_name'];

// Fetch all sections with their unit sections and heads
$query = "SELECT s.*, 
                 CONCAT(e.first_name, ' ', e.last_name) as head_name,
                 e.picture as head_picture,
                 (SELECT COUNT(*) FROM unit_section WHERE section_id = s.section_id) as unit_count
          FROM section s
          LEFT JOIN employee e ON s.head_emp_id = e.emp_id
          ORDER BY s.section_name";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$sections = [];
while ($row = $result->fetch_assoc()) {
  $sections[] = $row;
}

// Fetch all unit sections with their heads
$query = "SELECT us.*, 
                 s.section_name,
                 CONCAT(e.first_name, ' ', e.last_name) as head_name,
                 e.picture as head_picture
          FROM unit_section us
          LEFT JOIN section s ON us.section_id = s.section_id
          LEFT JOIN employee e ON us.head_emp_id = e.emp_id
          ORDER BY us.unit_name";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$unit_sections = [];
while ($row = $result->fetch_assoc()) {
  $unit_sections[] = $row;
}

// Fetch Manager from employee table using is_manager field
$query = "SELECT e.*, 
                 p.position_name,
                 o.office_name
          FROM employee e
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN office o ON e.office_id = o.office_id
          WHERE e.is_manager = 1
          LIMIT 1";

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$manager = $result->fetch_assoc();

// Fetch Manager's Office Staff
$query = "SELECT mos.*, 
                 CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                 e.picture as employee_picture,
                 e.email as employee_email,
                 e.phone_number as employee_phone,
                 p.position_name as employee_position,
                 o.office_name as employee_office
          FROM managers_office_staff mos
          JOIN employee e ON mos.emp_id = e.emp_id
          LEFT JOIN position p ON e.position_id = p.position_id
          LEFT JOIN office o ON e.office_id = o.office_id
          ORDER BY mos.position
          LIMIT 6"; // Limit to 6 for dashboard display

$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$manager_staff = [];
while ($row = $result->fetch_assoc()) {
  $manager_staff[] = $row;
}

// Fetch data for appointment status chart
$query = "SELECT a.status_name, a.color, COUNT(e.emp_id) as count
          FROM appointment_status a
          LEFT JOIN employee e ON a.appointment_id = e.appointment_status_id
          GROUP BY a.appointment_id, a.status_name, a.color
          ORDER BY count DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$appointment_data = [];
while ($row = $result->fetch_assoc()) {
  $appointment_data[] = $row;
}

// Fetch data for gender distribution by section
$query = "SELECT s.section_name, 
                 SUM(CASE WHEN e.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                 SUM(CASE WHEN e.gender = 'Female' THEN 1 ELSE 0 END) as female_count,
                 COUNT(e.emp_id) as total_count
          FROM section s
          LEFT JOIN employee e ON s.section_id = e.section_id
          GROUP BY s.section_id, s.section_name
          ORDER BY s.section_name";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$gender_data = [];
while ($row = $result->fetch_assoc()) {
  $gender_data[] = $row;
}

// Fetch count of active employees
$query = "SELECT COUNT(*) as active_count 
          FROM employee 
          WHERE employment_status_id = 1"; // Assuming 1 is the ID for active status
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$active_employees = $result->fetch_assoc()['active_count'];

// Add this to your dashboard.php or profile.php after login
function isUsingTemporaryPassword($emp_id, $db)
{
  $query = "SELECT u.password, e.id_number 
              FROM users u 
              JOIN employee e ON u.employee_id = e.emp_id 
              WHERE u.employee_id = ?";
  $stmt = $db->prepare($query);
  $stmt->bind_param("i", $emp_id);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();

  if ($result) {
    // Check if password matches the employee number (temporary password)
    return password_verify($result['id_number'], $result['password']);
  }
  return false;
}

// Usage in your dashboard or profile:
if (isUsingTemporaryPassword($_SESSION['emp_id'], $db)) {
  $_SESSION['toast'] = [
    'type' => 'warning',
    'message' => 'You are using a temporary password. Please change your password for security.'
  ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminLTE 3 | Organization Dashboard</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="../css/dashboard.css">
  <!-- Chart.js -->
  <?php include '../includes/header.php'; ?>
</head>

<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <!-- Main Sidebar Container -->
    <?php include '../includes/sidebar.php'; ?>
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
      <!-- Content Header (Page header) -->
      <div class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1 class="m-0">Organization Dashboard</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item active">Dashboard</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

      <!-- Main content -->
      <div class="content">
        <div class="container-fluid">
        
        </div>
      </div>
    </div>


    <?php include '../includes/mainfooter.php'; ?>
  </div>
  <?php include '../includes/footer.php'; ?>

 
</body>

</html>