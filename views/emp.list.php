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
  <link rel="stylesheet" href="../css/emp_list.css">
  <style>
    /* =====================================================
       GLOBAL DARK MODE BASE
       ===================================================== */
    body.dark-mode { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .content-wrapper { background-color: var(--body-bg) !important; }
    body.dark-mode .card { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
    body.dark-mode .card-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; border-color: var(--card-border) !important; }
    body.dark-mode .card-body { background: var(--card-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .card-footer { background: var(--card-bg) !important; border-color: var(--card-border) !important; }
    body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .modal-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; }
    body.dark-mode .modal-body { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .modal-footer { background: var(--modal-bg) !important; border-color: var(--card-border) !important; }
    body.dark-mode .table { background: var(--table-bg) !important; color: var(--text-primary) !important; }
    body.dark-mode .table thead th { background: var(--table-stripe) !important; color: var(--text-primary) !important; border-color: var(--table-border) !important; }
    body.dark-mode .table td, body.dark-mode .table th { border-color: var(--table-border) !important; color: var(--text-primary) !important; }
    body.dark-mode .table-striped tbody tr:nth-of-type(odd) { background: var(--table-stripe) !important; }
    body.dark-mode .table-hover tbody tr:hover { background: var(--notification-unread-bg) !important; }
    body.dark-mode .table-bordered { border-color: var(--table-border) !important; }
    body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .form-control:focus { border-color: #5a7fa8 !important; box-shadow: 0 0 0 0.2rem rgba(90,127,168,.25) !important; }
    body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
    body.dark-mode .input-group-text { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode label, body.dark-mode .form-label { color: var(--text-primary) !important; }
    body.dark-mode .text-muted { color: var(--text-muted) !important; }
    body.dark-mode .text-dark { color: var(--text-primary) !important; }
    body.dark-mode h1,body.dark-mode h2,body.dark-mode h3,body.dark-mode h4,body.dark-mode h5,body.dark-mode h6 { color: var(--text-primary) !important; }
    body.dark-mode p, body.dark-mode span:not(.badge) { color: var(--text-primary); }
    body.dark-mode .breadcrumb { background: var(--card-bg) !important; }
    body.dark-mode .breadcrumb-item a { color: #7aabdf !important; }
    body.dark-mode .breadcrumb-item.active { color: var(--text-muted) !important; }
    body.dark-mode .dropdown-menu { background: var(--dropdown-bg) !important; border-color: var(--dropdown-border) !important; }
    body.dark-mode .dropdown-item { color: var(--dropdown-color) !important; }
    body.dark-mode .dropdown-item:hover { background: var(--table-stripe) !important; }
    body.dark-mode .page-item .page-link { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
    body.dark-mode .page-item.active .page-link { background: var(--sidebar-active-bg) !important; border-color: var(--sidebar-active-bg) !important; }
    body.dark-mode hr { border-color: var(--card-border) !important; }
    body.dark-mode .dataTables_wrapper { color: var(--text-primary) !important; }
    body.dark-mode .dataTables_filter input, body.dark-mode .dataTables_length select { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .dataTables_info { color: var(--text-muted) !important; }
    body.dark-mode .select2-container--bootstrap4 .select2-selection { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
    body.dark-mode .select2-container--bootstrap4 .select2-selection__rendered { color: var(--input-color) !important; }
    body.dark-mode .select2-dropdown { background: var(--dropdown-bg) !important; border-color: var(--card-border) !important; }
    body.dark-mode .select2-results__option { color: var(--dropdown-color) !important; }
    body.dark-mode .select2-results__option--highlighted { background: var(--sidebar-active-bg) !important; color: #fff !important; }
    body.dark-mode .content-header { background: var(--card-bg) !important; color: var(--text-primary) !important; }

    /* =====================================================
       PAGE HEADER / TOOLBAR
       ===================================================== */
    .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
    .page-toolbar {
      background: linear-gradient(135deg, #2c982f 0%, #04a92a 100%);
      border-radius: 12px;
      padding: 20px 24px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      box-shadow: 0 4px 15px rgba(79,70,229,0.25);
    }
    .page-toolbar-left h1 {
      color: #fff !important;
      font-size: 22px;
      font-weight: 700;
      margin: 0;
    }
    .page-toolbar-left p {
      color: rgba(255,255,255,0.75) !important;
      margin: 2px 0 0;
      font-size: 13px;
    }
    .page-toolbar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

    .btn-toolbar-primary {
      background: rgba(255,255,255,0.2);
      border: 1px solid rgba(255,255,255,0.35);
      color: #fff !important;
      border-radius: 8px;
      padding: 8px 16px;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.2s;
      backdrop-filter: blur(4px);
    }
    .btn-toolbar-primary:hover, .btn-toolbar-primary:focus {
      background: rgba(255,255,255,0.35);
      border-color: rgba(255,255,255,0.6);
      color: #fff !important;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .btn-toolbar-primary.active {
      background: #fff;
      color: #4f46e5 !important;
      border-color: #fff;
    }
    .btn-toolbar-add {
      background: #10b981;
      border: 1px solid #10b981;
      color: #fff !important;
      border-radius: 8px;
      padding: 8px 16px;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.2s;
    }
    .btn-toolbar-add:hover { background: #059669; border-color: #059669; color: #fff !important; transform: translateY(-1px); }
    .btn-toolbar-filter-badge {
      background: #ef4444;
      color: #fff;
      border-radius: 10px;
      padding: 1px 6px;
      font-size: 10px;
      font-weight: 700;
      margin-left: 4px;
    }

    /* =====================================================
       MODERN GRID CARDS
       ===================================================== */
    .emp-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 20px;
      padding: 4px 0 20px;
    }

    .emp-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.07);
      overflow: hidden;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      border: 1px solid #f0f0f0;
      position: relative;
    }
    .emp-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 28px rgba(79,70,229,0.15);
      border-color: #c7d2fe;
    }

    /* Accent stripe at top */
    .emp-card-accent {
      height: 5px;
      width: 100%;
      background: linear-gradient(90deg, #4f46e5, #7c73e6);
    }

    .emp-card-photo-wrap {
      display: flex;
      justify-content: center;
      padding: 20px 20px 10px;
      position: relative;
    }
    .emp-card-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #eef2ff;
      box-shadow: 0 4px 12px rgba(79,70,229,0.15);
    }
    .emp-card-avatar-placeholder {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      font-weight: 700;
      color: #4f46e5;
      border: 3px solid #eef2ff;
      box-shadow: 0 4px 12px rgba(79,70,229,0.15);
      text-transform: uppercase;
    }

    .emp-card-body {
      padding: 0 16px 14px;
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    .emp-card-name {
      font-size: 15px;
      font-weight: 700;
      color: #1e1b4b;
      margin: 0 0 3px;
      line-height: 1.3;
    }
    .emp-card-position {
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 10px;
      font-weight: 500;
    }

    .emp-card-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
      justify-content: center;
      margin-bottom: 12px;
    }
    .emp-card-badge {
      font-size: 10px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      letter-spacing: 0.3px;
      text-transform: uppercase;
    }

    .emp-card-meta {
      width: 100%;
      border-top: 1px solid #f3f4f6;
      padding-top: 10px;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .emp-card-meta-row {
      display: flex;
      align-items: center;
      gap: 7px;
      font-size: 11.5px;
      color: #6b7280;
    }
    .emp-card-meta-row i {
      width: 14px;
      text-align: center;
      color: #4f46e5;
      flex-shrink: 0;
    }
    .emp-card-meta-row span {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .emp-card-actions {
      display: flex;
      border-top: 1px solid #f3f4f6;
      overflow: hidden;
      border-radius: 0 0 16px 16px;
    }
    .emp-card-action-btn {
      flex: 1;
      border: none;
      background: none;
      padding: 10px 6px;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      font-weight: 600;
    }
    .emp-card-action-btn:not(:last-child) { border-right: 1px solid #f3f4f6; }
    .emp-card-action-btn.edit  { color: #f59e0b; }
    .emp-card-action-btn.view  { color: #6b7280; }
    .emp-card-action-btn.del   { color: #ef4444; }
    .emp-card-action-btn:hover { background: #f8faff; }
    .emp-card-action-btn.edit:hover  { background: #fffbeb; }
    .emp-card-action-btn.view:hover  { background: #f9fafb; }
    .emp-card-action-btn.del:hover   { background: #fff1f2; }

    /* DARK MODE — Grid Cards */
    body.dark-mode .emp-card {
      background: var(--card-bg) !important;
      border-color: var(--card-border) !important;
      box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
    body.dark-mode .emp-card:hover {
      border-color: #5b65a8 !important;
      box-shadow: 0 12px 28px rgba(0,0,0,0.4);
    }
    body.dark-mode .emp-card-name { color: var(--text-primary) !important; }
    body.dark-mode .emp-card-position { color: var(--text-muted) !important; }
    body.dark-mode .emp-card-avatar { border-color: var(--card-border) !important; }
    body.dark-mode .emp-card-avatar-placeholder {
      background: linear-gradient(135deg, #2d3561, #3a3f6e) !important;
      border-color: var(--card-border) !important;
      color: #a5b4fc !important;
    }
    body.dark-mode .emp-card-meta { border-color: var(--card-border) !important; }
    body.dark-mode .emp-card-meta-row { color: var(--text-muted) !important; }
    body.dark-mode .emp-card-meta-row i { color: #818cf8 !important; }
    body.dark-mode .emp-card-meta-row span { color: var(--text-muted) !important; }
    body.dark-mode .emp-card-actions { border-color: var(--card-border) !important; background: var(--card-bg) !important; }
    body.dark-mode .emp-card-action-btn:not(:last-child) { border-color: var(--card-border) !important; }
    body.dark-mode .emp-card-action-btn:hover { background: var(--table-stripe) !important; }
    body.dark-mode .emp-card-action-btn.edit:hover  { background: rgba(245,158,11,0.1) !important; }
    body.dark-mode .emp-card-action-btn.view:hover  { background: var(--table-stripe) !important; }
    body.dark-mode .emp-card-action-btn.del:hover   { background: rgba(239,68,68,0.1) !important; }

    /* Grid container wrapper */
    .grid-view-wrapper { display: none; }
    .grid-search-bar {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 18px;
      flex-wrap: wrap;
    }
    .grid-search-input {
      flex: 1;
      min-width: 180px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      padding: 9px 14px 9px 36px;
      font-size: 13px;
      background: #fff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M13 13l3 3m-5-3a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3e%3c/svg%3e") 10px center no-repeat;
      background-size: 16px;
      transition: all 0.2s;
    }
    .grid-search-input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
    body.dark-mode .grid-search-input {
      background-color: var(--input-bg) !important;
      color: var(--input-color) !important;
      border-color: var(--input-border) !important;
    }

    .grid-count-badge {
      font-size: 12px;
      font-weight: 600;
      color: #6b7280;
      white-space: nowrap;
    }
    body.dark-mode .grid-count-badge { color: var(--text-muted) !important; }

    /* Grid pagination */
    .grid-pagination-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 0 0;
      flex-wrap: wrap;
      gap: 8px;
    }
    .grid-pagination-info { font-size: 12px; color: #6b7280; font-weight: 500; }
    body.dark-mode .grid-pagination-info { color: var(--text-muted) !important; }
    .grid-pagination-btns { display: flex; gap: 6px; }
    .grid-page-btn {
      border: 1px solid #e2e8f0;
      background: #fff;
      color: #374151;
      border-radius: 8px;
      padding: 6px 14px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .grid-page-btn:hover:not(:disabled) { border-color: #4f46e5; color: #4f46e5; background: #eef2ff; }
    .grid-page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .grid-page-btn.active { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    body.dark-mode .grid-page-btn {
      background: var(--card-bg) !important;
      border-color: var(--card-border) !important;
      color: var(--text-primary) !important;
    }
    body.dark-mode .grid-page-btn:hover:not(:disabled) { border-color: #818cf8 !important; color: #818cf8 !important; background: var(--table-stripe) !important; }
    body.dark-mode .grid-page-btn.active { background: var(--sidebar-active-bg) !important; border-color: var(--sidebar-active-bg) !important; color: #fff !important; }

    .no-results-card {
      grid-column: 1 / -1;
      text-align: center;
      padding: 60px 20px;
      color: #9ca3af;
    }
    .no-results-card i { font-size: 48px; margin-bottom: 14px; opacity: 0.4; }
    .no-results-card p { font-size: 15px; margin: 0; font-weight: 500; }
    body.dark-mode .no-results-card { color: var(--text-muted) !important; }

    /* =====================================================
       ADVANCED SEARCH PANEL (SLIDE-IN)
       ===================================================== */
    .adv-search-backdrop {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.4);
      z-index: 1040;
      backdrop-filter: blur(2px);
    }
    .adv-search-backdrop.show { display: block; }

    .adv-search-panel {
      position: fixed;
      top: 0; right: -420px;
      width: 400px; max-width: 95vw;
      height: 100%;
      background: #fff;
      z-index: 1050;
      box-shadow: -8px 0 32px rgba(0,0,0,0.15);
      display: flex;
      flex-direction: column;
      transition: right 0.3s cubic-bezier(.4,0,.2,1);
      border-radius: 16px 0 0 16px;
    }
    .adv-search-panel.show { right: 0; }
    body.dark-mode .adv-search-panel { background: var(--modal-bg) !important; }

    .adv-search-header {
      background: linear-gradient(135deg, #4f46e5 0%, #7c73e6 100%);
      padding: 20px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-radius: 16px 0 0 0;
      flex-shrink: 0;
    }
    .adv-search-header h4 { color: #fff !important; margin: 0; font-size: 17px; font-weight: 700; }
    .adv-search-close {
      background: rgba(255,255,255,0.2);
      border: none;
      color: #fff;
      width: 32px; height: 32px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 16px;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.2s;
    }
    .adv-search-close:hover { background: rgba(255,255,255,0.35); }

    .adv-search-body { flex: 1; overflow-y: auto; padding: 20px 24px; }
    .adv-search-section { margin-bottom: 22px; }
    .adv-search-section h6 {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #4f46e5;
      margin-bottom: 12px;
      padding-bottom: 6px;
      border-bottom: 2px solid #eef2ff;
    }
    body.dark-mode .adv-search-section h6 { color: #818cf8 !important; border-color: var(--card-border) !important; }
    .adv-field { margin-bottom: 12px; }
    .adv-field label { font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 5px; display: block; }
    body.dark-mode .adv-field label { color: var(--text-primary) !important; }
    .adv-input {
      width: 100%;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      padding: 9px 12px;
      font-size: 13px;
      background: #fff;
      transition: all 0.2s;
    }
    .adv-input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
    body.dark-mode .adv-input { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }

    .adv-search-footer {
      padding: 16px 24px;
      border-top: 1px solid #e2e8f0;
      display: flex;
      gap: 10px;
      flex-shrink: 0;
    }
    body.dark-mode .adv-search-footer { border-color: var(--card-border) !important; background: var(--modal-bg) !important; }
    .btn-adv-clear {
      flex: 1;
      border: 1px solid #e2e8f0;
      background: #fff;
      color: #6b7280;
      border-radius: 8px;
      padding: 10px;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-adv-clear:hover { border-color: #ef4444; color: #ef4444; background: #fff1f2; }
    body.dark-mode .btn-adv-clear { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-muted) !important; }
    .btn-adv-apply {
      flex: 2;
      border: none;
      background: linear-gradient(135deg, #4f46e5, #7c73e6);
      color: #fff;
      border-radius: 8px;
      padding: 10px;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 2px 8px rgba(79,70,229,0.3);
    }
    .btn-adv-apply:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(79,70,229,0.4); }

    /* =====================================================
       TABLE VIEW WRAPPER
       ===================================================== */
    .table-view-wrapper { display: block; }

    /* =====================================================
       IMPORT MODAL DARK
       ===================================================== */
    body.dark-mode #importModal .modal-content { background: var(--modal-bg) !important; }
    body.dark-mode #importModal .modal-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; }
    body.dark-mode #importModal label { color: var(--text-primary) !important; }
    body.dark-mode #importModal .text-muted { color: var(--text-muted) !important; }

    /* Card wrapper */
    .list-card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.07);
      background: #fff;
    }
    body.dark-mode .list-card { background: var(--card-bg) !important; }
  
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

        <!-- ===== PAGE TOOLBAR ===== -->
        <div class="page-toolbar">
          <div class="page-toolbar-left">
            <h1><i class="fas fa-users mr-2"></i>Employee Directory</h1>
            <p><?= count($employees) ?> total employees</p>
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

        <!-- ===== MAIN CARD ===== -->
        <div class="card list-card">
          <div class="card-body" style="padding: 24px;">

            <!-- TABLE VIEW -->
            <div class="table-view-wrapper">
              <div class="table-responsive">
                <table id="employeeTable" class="table table-bordered table-striped table-hover">
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
                               style="width:44px;height:44px;object-fit:cover;"
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
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-file-import mr-2"></i>Import Employees from Excel</h5>
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
            cancelButtonColor: '#3085d6',
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