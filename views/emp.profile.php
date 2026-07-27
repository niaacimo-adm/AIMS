<?php
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$database = new Database();
$db       = $database->getConnection();

$emp_id = $_GET['emp_id'] ?? null;
if (!$emp_id) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Employee ID is required'];
    header("Location: emp.list.php"); exit();
}

// ── Main employee query ──────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT e.*, es.status_name as employment_status, es.color as employment_color,
    o.office_name, o.manager_emp_id as office_manager_id,
    m.first_name as office_manager_first_name, m.last_name as office_manager_last_name,
    p.position_name, ap.status_name as appointment_status, ap.color as appointment_color,
    (SELECT COUNT(*) FROM office WHERE manager_emp_id = e.emp_id) as is_office_manager
  FROM employee e
  LEFT JOIN employment_status es ON e.employment_status_id = es.status_id
  LEFT JOIN office o ON e.office_id = o.office_id
  LEFT JOIN employee m ON o.manager_emp_id = m.emp_id
  LEFT JOIN position p ON e.position_id = p.position_id
  LEFT JOIN appointment_status ap ON e.appointment_status_id = ap.appointment_id
  WHERE e.emp_id = ?");
$stmt->bind_param("i", $emp_id); $stmt->execute();
$r = $stmt->get_result(); $employee = $r->fetch_assoc();
$r->free(); $stmt->close();

if (!$employee) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Employee not found'];
    header("Location: emp.list.php"); exit();
}

// ── Sections where employee is head ─────────────────────────────────────────
$stmt = $db->prepare("SELECT s.section_id, s.section_name, s.section_code, o.office_name
                      FROM section s LEFT JOIN office o ON s.office_id = o.office_id
                      WHERE s.head_emp_id = ?");
$stmt->bind_param("i", $emp_id); $stmt->execute();
$r = $stmt->get_result(); $sections_as_head = [];
while ($row = $r->fetch_assoc()) { $sections_as_head[] = $row; }
$r->free(); $stmt->close();

// ── Unit sections where employee is head ─────────────────────────────────────
$stmt = $db->prepare("SELECT us.unit_id, us.unit_name, us.unit_code, s.section_name, s.section_id
                      FROM unit_section us LEFT JOIN section s ON us.section_id = s.section_id
                      WHERE us.head_emp_id = ?");
$stmt->bind_param("i", $emp_id); $stmt->execute();
$r = $stmt->get_result(); $units_as_head = [];
while ($row = $r->fetch_assoc()) { $units_as_head[] = $row; }
$r->free(); $stmt->close();

// ── Current section/unit assignment ──────────────────────────────────────────
$stmt = $db->prepare("SELECT s.section_name, s.section_id,
    sh.first_name as section_head_first_name, sh.last_name as section_head_last_name,
    us.unit_name, us.unit_id,
    uh.first_name as unit_head_first_name, uh.last_name as unit_head_last_name
  FROM employee e
  LEFT JOIN section s ON e.section_id = s.section_id
  LEFT JOIN employee sh ON s.head_emp_id = sh.emp_id
  LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
  LEFT JOIN employee uh ON us.head_emp_id = uh.emp_id
  WHERE e.emp_id = ?");
$stmt->bind_param("i", $emp_id); $stmt->execute();
$current_assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Handle file upload ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['employee_files'])) {
    $uploadSuccess = 0; $uploadErrors = [];
    try {
        $targetDir = "../dist/files/employees/{$emp_id}/";
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) throw new Exception("Could not create upload directory.");
        if (!is_writable($targetDir)) throw new Exception("Upload directory is not writable.");
        $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif'];
        foreach ($_FILES['employee_files']['name'] as $key => $name) {
            if ($_FILES['employee_files']['error'][$key] !== UPLOAD_ERR_OK) { $uploadErrors[] = "Error with '{$name}'."; continue; }
            $fileName = basename($name); $targetFile = $targetDir.$fileName;
            $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            if (file_exists($targetFile))                                { $uploadErrors[] = "'{$fileName}' already exists."; continue; }
            if ($_FILES['employee_files']['size'][$key] > 200*1024*1024) { $uploadErrors[] = "'{$fileName}' too large."; continue; }
            if (!in_array($fileType, $allowed))                          { $uploadErrors[] = "'{$fileName}' type not allowed."; continue; }
            if (move_uploaded_file($_FILES['employee_files']['tmp_name'][$key], $targetFile)) { $uploadSuccess++; }
            else { $uploadErrors[] = "Error uploading '{$fileName}'."; }
        }
        $msg = $uploadSuccess > 0
            ? "{$uploadSuccess} file(s) uploaded!" . (!empty($uploadErrors) ? " ".count($uploadErrors)." failed." : "")
            : "No files uploaded. " . implode(" ", $uploadErrors);
        $_SESSION['toast'] = ['type' => $uploadSuccess > 0 ? 'success' : 'error', 'message' => $msg];
    } catch (Exception $e) { $_SESSION['toast'] = ['type' => 'error', 'message' => $e->getMessage()]; }
    header("Location: emp.profile.php?emp_id={$emp_id}"); exit();
}

// ── Handle file deletion ──────────────────────────────────────────────────────
if (isset($_GET['delete_file'])) {
    $fp = "../dist/files/employees/{$emp_id}/" . basename($_GET['delete_file']);
    if (file_exists($fp)) { unlink($fp); $_SESSION['toast'] = ['type' => 'success', 'message' => 'File deleted!']; }
    else                  { $_SESSION['toast'] = ['type' => 'error',   'message' => 'File not found!']; }
    header("Location: emp.profile.php?emp_id={$emp_id}"); exit();
}

// ── Handle bulk delete with password confirmation ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_files'])) {
    $confirm_pw = $_POST['delete_all_password'] ?? '';
    if (empty($confirm_pw)) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Password is required to delete files.'];
        header("Location: emp.profile.php?emp_id={$emp_id}"); exit();
    }
    // Verify the logged-in user's password (admin performing the action)
    $logged_in_id = $_SESSION['emp_id'] ?? 0;
    $pw_stmt = $db->prepare("SELECT password FROM users WHERE employee_id = ?");
    $pw_stmt->bind_param("i", $logged_in_id);
    $pw_stmt->execute();
    $pw_row = $pw_stmt->get_result()->fetch_assoc();
    $pw_stmt->close();
    if (!$pw_row || !password_verify($confirm_pw, $pw_row['password'])) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Incorrect password. No files were deleted.'];
        header("Location: emp.profile.php?emp_id={$emp_id}"); exit();
    }
    $selected = $_POST['selected_files'] ?? [];
    if (empty($selected)) {
        $_SESSION['toast'] = ['type' => 'warning', 'message' => 'No files selected for deletion.'];
        header("Location: emp.profile.php?emp_id={$emp_id}"); exit();
    }
    $deleted = 0;
    $dir = "../dist/files/employees/{$emp_id}/";
    foreach ($selected as $fname) {
        $fp = $dir . basename($fname);
        if (file_exists($fp)) { unlink($fp); $deleted++; }
    }
    $_SESSION['toast'] = ['type' => 'success', 'message' => "{$deleted} file(s) deleted successfully."];
    header("Location: emp.profile.php?emp_id={$emp_id}"); exit();
}

// ── Uploaded files list ───────────────────────────────────────────────────────
$uploadDir = "../dist/files/employees/{$emp_id}/";
$uploadedFiles = [];
if (is_dir($uploadDir)) {
    foreach (scandir($uploadDir) as $f) {
        if ($f !== '.' && $f !== '..') {
            $fp = $uploadDir.$f;
            $uploadedFiles[] = ['name'=>$f,'size'=>filesize($fp),'modified'=>filemtime($fp),'type'=>mime_content_type($fp)];
        }
    }
}

function formatSizeUnits($b) {
    if ($b >= 1073741824) return number_format($b/1073741824,2).' GB';
    if ($b >= 1048576)    return number_format($b/1048576,2).' MB';
    if ($b >= 1024)       return number_format($b/1024,2).' KB';
    if ($b > 1)           return $b.' bytes';
    return $b == 1 ? '1 byte' : '0 bytes';
}

// ── Focal person / Secretary ──────────────────────────────────────────────────
$stmt = $db->prepare("SELECT ss.section_id, s.section_name FROM section_secretaries ss
                      JOIN section s ON ss.section_id = s.section_id WHERE ss.emp_id = ?");
$stmt->bind_param("i", $emp_id); $stmt->execute();
$r = $stmt->get_result(); $sections_as_secretary = [];
while ($row = $r->fetch_assoc()) { $sections_as_secretary[] = $row; }
$r->free(); $stmt->close();

// ── Manager office staff ─────────────────────────────────────────────────────
$is_manager_office_staff = false;
$stmt = $db->prepare("SELECT COUNT(*) as c FROM managers_office_staff WHERE emp_id = ?");
$stmt->bind_param("i", $emp_id); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row['c'] > 0) $is_manager_office_staff = true;
$stmt->close();

$has_leadership = $employee['is_office_manager'] || !empty($sections_as_head) || !empty($units_as_head) || !empty($sections_as_secretary);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HR System | Employee Profile</title>
  <?php include '../includes/header.php'; ?>
  <style>

        /* ── Theme Tokens (NIA Green) ── */
    <?php
    // Unified NIA green theme — deep forest gradient, refined for a modern surface system
    $theme = [
      'primary'    => 'linear-gradient(158deg,#123b2a 0%,#1a5c38 48%,#278f5c 100%)',
      'solid'      => '#1a5c38',
      'solid_dark' => '#2a9863',
      'sidebar'    => '#1a5c38',
      'header'     => 'linear-gradient(158deg,#123b2a 0%,#1a5c38 48%,#278f5c 100%)',
      'button'     => 'linear-gradient(135deg,#1a5c38,#2a9863)',
      'accent'     => '#1f7a4d',
      'light'      => '#eef8f2',
      'text_on'    => '#ffffff',
    ];
    ?>

    :root {
      --clr-accent:      <?= $theme['solid'] ?>;
      --clr-accent-dark: <?= $theme['solid_dark'] ?>;
      --clr-accent-light:<?= $theme['light'] ?>;
      --clr-gradient:    <?= $theme['primary'] ?>;
      --clr-header:      <?= $theme['header'] ?>;
      --clr-sidebar:     <?= $theme['sidebar'] ?>;
      --clr-button:      <?= $theme['button'] ?>;
      --text-on-accent:  <?= $theme['text_on'] ?>;
      --clr-gold:#d4af37;

      /* Radius scale */
      --radius-xl:22px; --radius-lg:16px; --radius-md:12px; --radius-sm:8px; --radius-xs:6px;

      /* Type */
      --font-body:'DM Sans',sans-serif; --font-display:'Syne',sans-serif;

      /* Surfaces (light) */
      --pf-bg-page:#f2f6f4; --pf-bg-card:#fff; --pf-bg-tile:#f7faf8;
      --pf-bg-form:#f7faf8; --pf-bg-guide:var(--clr-accent-light); --pf-bg-upload:#fafcfb;
      --pf-border:#e5ece8; --pf-border-input:#dde6e1;
      --pf-text-primary:#152420; --pf-text-body:#3a4a44; --pf-text-muted:#6b7a74;
      --pf-text-hint:#95a49e; --pf-text-label:#4c5c56;
      --pf-info-row-border:#eef2f0; --pf-leadership-bg:#f7faf8; --pf-table-border:#e5ece8;
      --pf-scrollbar-track:#eef2f0; --pf-scrollbar-thumb:#c4d2cc;

      /* Elevation — tinted, soft, layered */
      --pf-shadow-xs:0 1px 2px rgba(16,40,30,.05);
      --pf-shadow-card:0 1px 2px rgba(16,40,30,.04), 0 8px 24px -8px rgba(16,40,30,.10);
      --pf-shadow-hover:0 4px 10px -2px rgba(16,40,30,.10), 0 16px 32px -12px rgba(16,40,30,.16);
      --pf-shadow-modal:0 24px 70px -12px rgba(8,24,17,.35);
      --pf-shadow-tabbar:0 10px 30px -14px rgba(8,24,17,.35);
    }
    body.dark-mode {
      --pf-bg-page:var(--body-bg); --pf-bg-card:var(--card-bg); --pf-bg-tile:var(--table-stripe);
      --pf-bg-form:var(--table-stripe); --pf-bg-guide:rgba(42,152,99,.08); --pf-bg-upload:var(--table-stripe);
      --pf-border:var(--card-border); --pf-border-input:var(--input-border);
      --pf-text-primary:var(--text-primary); --pf-text-body:var(--text-primary); --pf-text-muted:var(--text-muted);
      --pf-text-hint:var(--text-muted); --pf-text-label:var(--text-muted);
      --pf-info-row-border:var(--card-border); --pf-leadership-bg:var(--table-stripe); --pf-table-border:var(--table-border);
      --pf-scrollbar-track:var(--card-bg); --pf-scrollbar-thumb:#4a5068;
      --pf-shadow-card:0 1px 2px rgba(0,0,0,.3), 0 8px 24px -8px rgba(0,0,0,.45);
      --pf-shadow-hover:0 4px 10px -2px rgba(0,0,0,.35), 0 16px 32px -12px rgba(0,0,0,.55);
      --pf-shadow-modal:0 24px 70px -12px rgba(0,0,0,.65);
      --pf-shadow-tabbar:0 10px 30px -14px rgba(0,0,0,.6);
    }

    body,.content-wrapper { font-family:var(--font-body)!important; background:var(--pf-bg-page)!important; }
    .content-header h1 { font-family:var(--font-display); color:var(--clr-accent); font-weight:800; letter-spacing:-.5px; }
    .breadcrumb { background:transparent; }
    .breadcrumb-item.active { color:var(--clr-accent); }
    .breadcrumb-item a { color:var(--pf-text-muted); text-decoration:none; }
    .breadcrumb-item a:hover { color:var(--clr-accent); }

    /* ══════════════════════ HERO ══════════════════════ */
    .manager-hero {
      background:#0b1f17; border-radius:var(--radius-xl); padding:34px 38px;
      color:#fff; box-shadow:var(--pf-shadow-card); margin-bottom:18px;
      position:relative; overflow:hidden; border:1px solid rgba(255,255,255,.06);
    }
    .manager-hero::before,.manager-hero::after { display:none; }
    .mh-mesh {
      position:absolute; inset:-50%; width:200%; height:200%;
      background:
        radial-gradient(ellipse 60% 55% at 18% 28%, rgba(39,143,92,.20) 0%, transparent 58%),
        radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.14) 0%, transparent 58%),
        radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
        linear-gradient(160deg,#123b2a 0%,#081712 55%,#1a5c38 100%);
      animation:mhMeshDrift 24s ease-in-out infinite alternate;
      z-index:0; pointer-events:none;
    }
    @keyframes mhMeshDrift { from{ transform:translate3d(0,0,0) scale(1);} to{ transform:translate3d(-2%,2%,0) scale(1.04);} }
    .mh-dots {
      position:absolute; inset:0; z-index:0; pointer-events:none;
      background-image:radial-gradient(circle, rgba(39,143,92,.08) 1px, transparent 1px);
      background-size:34px 34px;
    }
    .mh-hex {
      position:absolute; inset:0; pointer-events:none; opacity:.05; z-index:0;
      background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V16L28 0l28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V50l28-16 28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3C/svg%3E");
      background-size:56px 100px;
    }
    .mh-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
    .mh-orb  { position:absolute; border-radius:50%; filter:blur(64px); animation:mhOrbFloat 18s ease-in-out infinite; }
    @keyframes mhOrbFloat { 0%,100%{ transform:translate(0,0);} 50%{ transform:translate(14px,-16px);} }
    .mh-orb-1 { width:280px; height:280px; background:rgba(39,143,92,.14); top:-80px;   left:-60px;  animation-duration:21s; }
    .mh-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.11); bottom:-50px;right:-40px; animation-delay:-7s; animation-duration:17s; }
    .mh-orb-3 { width:150px; height:150px; background:rgba(212,175,55,.06); top:40%;     right:18%;   animation-delay:-13s; animation-duration:24s; }
    .mh-logo-watermark { position:absolute; top:50%; right:3%; transform:translateY(-50%); width:150px; height:auto; pointer-events:none; z-index:0; opacity:.28; filter:grayscale(.2); }
    .mh-arc { position:absolute; top:-40px; right:-40px; width:180px; height:180px; border-radius:50%; background:radial-gradient(circle,rgba(39,143,92,.18) 0%,transparent 70%); pointer-events:none; z-index:0; }
    .mh-content { position:relative; z-index:2; }
    .mh-inner { display:flex; align-items:center; gap:26px; flex-wrap:wrap; }

    @media(max-width:640px) { .manager-hero { padding:26px 22px; text-align:left; } }

    /* ── Avatar ── */
    .profile-hero-avatar {
      width:104px; height:104px; border-radius:26px;
      border:3px solid rgba(255,255,255,.22); box-shadow:0 10px 28px -8px rgba(0,0,0,.5), 0 0 0 5px rgba(39,143,92,.14);
      object-fit:cover; flex-shrink:0; position:relative; z-index:1;
    }
    .profile-hero-initials {
      width:104px; height:104px; border-radius:26px;
      background:linear-gradient(155deg,rgba(255,255,255,.16),rgba(255,255,255,.05));
      border:3px solid rgba(255,255,255,.22); box-shadow:0 10px 28px -8px rgba(0,0,0,.5), 0 0 0 5px rgba(39,143,92,.14);
      display:flex; align-items:center; justify-content:center;
      font-family:var(--font-display); font-size:2.1rem; font-weight:700; color:#fff;
      flex-shrink:0; position:relative; z-index:1;
    }
    .profile-hero-info { position:relative; z-index:1; flex:1; min-width:200px; }
    .profile-hero-info h2 {
      font-family:var(--font-display); font-size:1.75rem; font-weight:800;
      color:#fff; margin:0 0 4px; letter-spacing:-.5px;
    }
    .hero-sub { color:rgba(255,255,255,.85); font-size:.96rem; font-weight:500; margin:0 0 3px; }
    .hero-id  { color:rgba(255,255,255,.5);  font-size:.76rem; margin:0; letter-spacing:1px; text-transform:uppercase; font-weight:600; }
    .hero-badges { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; position:relative; z-index:1; }
    .hero-status-badge {
      display:inline-flex; align-items:center; gap:6px;
      padding:5px 13px; border-radius:20px;
      font-size:.73rem; font-weight:700; letter-spacing:.3px;
      border:1px solid rgba(255,255,255,.16); backdrop-filter:blur(6px);
      box-shadow:0 2px 8px rgba(0,0,0,.15);
    }
    .hero-edit-btn {
      margin-left:auto; flex-shrink:0; align-self:flex-start;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22);
      color:#fff!important; border-radius:999px;
      padding:10px 22px; font-size:.85rem; font-weight:600;
      transition:all .22s; position:relative; z-index:1;
      text-decoration:none; display:inline-flex; align-items:center; gap:7px;
      backdrop-filter:blur(6px); box-shadow:0 4px 14px rgba(0,0,0,.18);
    }
    .hero-edit-btn:hover { background:rgba(255,255,255,.22); transform:translateY(-1px); }
    @media(max-width:640px) {
      .mh-inner { flex-direction:column; text-align:center; gap:16px; }
      .profile-hero-avatar,.profile-hero-initials { width:88px; height:88px; font-size:1.7rem; border-radius:22px; }
      .profile-hero-info h2 { font-size:1.4rem; }
      .hero-badges { justify-content:center; }
      .hero-edit-btn { margin:0 auto; align-self:center; }
    }

    /* ══════════════════════ FLOATING PILL TABS ══════════════════════ */
    .pf-tabs-bar {
      background:var(--pf-bg-card); border-radius:999px;
      display:flex; padding:6px; gap:4px; box-shadow:var(--pf-shadow-tabbar);
      border:1px solid var(--pf-border); margin-bottom:18px;
    }
    body.dark-mode .pf-tabs-bar { background:var(--card-bg)!important; }
    .pf-tabs-bar .nav-link {
      flex:1; text-align:center; padding:12px 10px;
      color:var(--pf-text-muted)!important; font-size:.85rem; font-weight:600;
      border-radius:999px!important; border:none!important; transition:all .25s cubic-bezier(.4,0,.2,1);
      position:relative;
    }
    .pf-tabs-bar .nav-link i { margin-right:6px; font-size:.9rem; }
    .pf-tabs-bar .nav-link.active {
      color:#fff!important; background:var(--clr-button)!important;
      box-shadow:0 6px 16px -4px rgba(26,92,56,.55);
    }
    .pf-tabs-bar .nav-link:hover:not(.active) { background:var(--clr-accent-light)!important; color:var(--clr-accent)!important; }
    body.dark-mode .pf-tabs-bar .nav-link:hover:not(.active) { background:rgba(42,152,99,.12)!important; color:var(--clr-accent)!important; }

    /* ── Tab content ── */
    .pf-tab-content {
      background:var(--pf-bg-card); border-radius:var(--radius-xl);
      box-shadow:var(--pf-shadow-card); padding:32px 34px; min-height:420px;
      border:1px solid var(--pf-border);
    }
    body.dark-mode .pf-tab-content { background:var(--card-bg)!important; }
    .pf-tab-content .card { border:none!important; box-shadow:none!important; background:transparent!important; }

    /* ── Section title ── */
    .pf-section-title {
      font-family:var(--font-display); font-size:1rem; font-weight:700;
      color:var(--pf-text-primary); margin-bottom:18px; padding-bottom:0;
      border-bottom:none; display:flex; align-items:center; gap:10px;
    }
    .pf-section-title::before { content:''; width:9px; height:9px; border-radius:3px; background:var(--clr-button); flex-shrink:0; transform:rotate(45deg); }
    .pf-section-title.mt-2 { margin-top:34px; }
    body.dark-mode .pf-section-title { color:var(--text-primary)!important; }

    /* ── Info tile grid ── */
    .pf-info-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:4px; }
    .pf-info-grid.cols-2 { grid-template-columns:repeat(2,1fr); }
    .pf-info-grid.cols-4 { grid-template-columns:repeat(4,1fr); }
    @media(max-width:900px) { .pf-info-grid,.pf-info-grid.cols-4 { grid-template-columns:repeat(2,1fr); } }
    @media(max-width:520px) { .pf-info-grid,.pf-info-grid.cols-2,.pf-info-grid.cols-4 { grid-template-columns:1fr; } }
    .pf-info-tile {
      background:var(--pf-bg-tile); border-radius:var(--radius-md);
      padding:14px 16px; border:1px solid var(--pf-border);
      transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .pf-info-tile:hover { transform:translateY(-2px); box-shadow:var(--pf-shadow-hover); border-color:var(--clr-accent); }
    .tile-label { font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:var(--clr-accent); margin-bottom:5px; }
    .tile-value { font-size:.9rem; font-weight:600; color:var(--pf-text-primary); word-break:break-word; }
    .tile-value a { color:var(--clr-accent); text-decoration:none; }
    .tile-value a:hover { text-decoration:underline; }
    body.dark-mode .pf-info-tile { background:var(--table-stripe)!important; }
    body.dark-mode .tile-value { color:var(--text-primary)!important; }

    /* ── Status badge ── */
    .status-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-weight:700; font-size:.78rem; letter-spacing:.3px; }

    /* ── Leadership grid ── */
    .leadership-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:14px; }
    .leadership-item {
      background:var(--pf-leadership-bg); border-radius:var(--radius-md); padding:16px 18px;
      border:1px solid var(--pf-border); border-top:3px solid var(--clr-accent);
      transition:transform .18s ease, box-shadow .18s ease;
    }
    .leadership-item:hover { transform:translateY(-2px); box-shadow:var(--pf-shadow-hover); }
    .leadership-item.focal { border-top-color:#7c3aed; }
    .l-title { font-weight:700; font-size:.88rem; color:var(--pf-text-primary); margin-bottom:4px; }
    .l-sub   { font-size:.78rem; color:var(--pf-text-muted); margin-bottom:10px; line-height:1.45; }
    body.dark-mode .leadership-item { background:var(--table-stripe)!important; }
    body.dark-mode .l-title { color:var(--text-primary)!important; }
    body.dark-mode .l-sub   { color:var(--text-muted)!important; }

    /* ── Inline file upload (drop area) ── */
    .file-drop-area {
      border:2px dashed var(--pf-border);
      border-radius:var(--radius-lg);
      background:var(--pf-bg-tile);
      cursor:pointer; transition:all .22s; margin-bottom:0; position:relative;
    }
    .file-drop-area:hover, .file-drop-area.drag-over { border-color:var(--clr-accent); background:var(--clr-accent-light); }
    .file-drop-inner { padding:32px 20px; text-align:center; }
    .file-drop-icon { font-size:2.1rem; color:var(--clr-accent); margin-bottom:10px; display:block; opacity:.65; }
    .file-drop-title { font-size:.9rem; font-weight:600; color:var(--pf-text-body); margin:0 0 4px; }
    .file-drop-browse { color:var(--clr-accent); text-decoration:underline; font-weight:600; }
    .file-drop-hint { font-size:.78rem; color:var(--pf-text-muted); margin:0; }
    body.dark-mode .file-drop-area { background:var(--table-stripe)!important; border-color:var(--card-border)!important; }
    body.dark-mode .file-drop-area:hover, body.dark-mode .file-drop-area.drag-over { border-color:var(--clr-accent)!important; background:rgba(42,152,99,.08)!important; }
    body.dark-mode .file-drop-title { color:var(--text-primary)!important; }

    .file-preview-list { list-style:none; padding:16px 20px 0; margin:0; }
    .file-preview-item { display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid var(--pf-border); font-size:.84rem; }
    .file-preview-item:last-child { border-bottom:none; }
    .fpi-name { flex:1; color:var(--pf-text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .fpi-size { color:var(--pf-text-muted); font-size:.75rem; white-space:nowrap; }
    body.dark-mode .file-preview-item { border-color:var(--card-border)!important; }
    body.dark-mode .fpi-name { color:var(--text-primary)!important; }
    body.dark-mode .fpi-size { color:var(--text-muted)!important; }

    .file-drop-actions { display:flex; justify-content:flex-end; gap:10px; padding:10px 14px; margin-top:10px; }
    body.dark-mode .file-drop-actions { border-color:var(--card-border)!important; }
    .btn-file-clear {
      background:transparent; border:1.5px solid var(--pf-border); color:var(--pf-text-muted);
      border-radius:var(--radius-sm); padding:7px 16px; font-size:.84rem; font-weight:600; cursor:pointer; transition:all .2s;
    }
    .btn-file-clear:hover { border-color:#ef4444; color:#ef4444; }
    .btn-file-upload {
      background:var(--clr-button); color:#fff; border:none;
      border-radius:var(--radius-sm); padding:7px 20px;
      font-size:.84rem; font-weight:600; cursor:pointer; transition:all .2s; box-shadow:0 4px 12px -3px rgba(26,92,56,.5);
    }
    .btn-file-upload:hover { opacity:.92; transform:translateY(-1px); box-shadow:0 6px 16px -3px rgba(26,92,56,.6); }

    /* ── Uploaded files list ── */
    .files-empty { text-align:center; padding:52px 20px; color:var(--pf-text-muted); }
    .files-empty i { font-size:2.6rem; margin-bottom:14px; display:block; opacity:.35; }
    .files-empty p { font-size:.9rem; margin:0; }

    .files-list-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
    .files-count { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--clr-accent); }

    .files-list { border:1px solid var(--pf-border); border-radius:var(--radius-md); overflow:hidden; }
    body.dark-mode .files-list { border-color:var(--card-border)!important; }

    .file-row { display:flex; align-items:center; gap:12px; padding:12px 16px; border-bottom:1px solid var(--pf-border); background:var(--pf-bg-card); transition:background .15s; }
    .file-row:last-child { border-bottom:none; }
    .file-row:hover { background:var(--pf-bg-tile); }
    body.dark-mode .file-row { background:var(--card-bg)!important; border-color:var(--card-border)!important; }
    body.dark-mode .file-row:hover { background:var(--table-stripe)!important; }

    .file-row-icon { font-size:1.35rem; flex-shrink:0; width:34px; height:34px; border-radius:var(--radius-xs); background:var(--pf-bg-tile); display:flex; align-items:center; justify-content:center; }
    body.dark-mode .file-row-icon { background:var(--table-stripe)!important; }
    .file-row-info { flex:1; min-width:0; }
    .file-row-name { display:block; font-size:.88rem; font-weight:600; color:var(--pf-text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .file-row-meta { font-size:.74rem; color:var(--pf-text-muted); }
    body.dark-mode .file-row-name { color:var(--text-primary)!important; }
    body.dark-mode .file-row-meta { color:var(--text-muted)!important; }

    .file-row-actions { display:flex; gap:4px; flex-shrink:0; }
    .file-action-btn {
      width:32px; height:32px; border-radius:var(--radius-sm);
      display:inline-flex; align-items:center; justify-content:center;
      font-size:.82rem; border:none; cursor:pointer; transition:all .15s; text-decoration:none;
    }
    .file-action-btn.download { background:var(--clr-accent-light); color:var(--clr-accent); }
    .file-action-btn.download:hover { background:var(--clr-accent); color:#fff; }
    .file-action-btn.preview  { background:#ecfdf5; color:#16a34a; }
    .file-action-btn.preview:hover  { background:#16a34a; color:#fff; }
    .file-action-btn.delete   { background:#fff1f2; color:#ef4444; }
    .file-action-btn.delete:hover   { background:#ef4444; color:#fff; }
    body.dark-mode .file-action-btn.download { background:rgba(255,255,255,.07)!important; color:var(--clr-accent)!important; }
    body.dark-mode .file-action-btn.preview  { background:rgba(22,163,74,.12)!important; color:#4ade80!important; }
    body.dark-mode .file-action-btn.delete   { background:rgba(239,68,68,.12)!important; color:#f87171!important; }

    .files-toolbar { display:flex; align-items:center; gap:12px; padding:11px 14px; margin-bottom:2px; background:var(--pf-bg-tile); border-radius:var(--radius-md); border:1px solid var(--pf-border); }
    body.dark-mode .files-toolbar { background:var(--table-stripe)!important; border-color:var(--card-border)!important; }
    .files-select-all-label { display:flex; align-items:center; gap:7px; font-size:.82rem; font-weight:600; color:var(--pf-text-body); cursor:pointer; margin:0; }
    body.dark-mode .files-select-all-label { color:var(--text-primary)!important; }
    .files-count { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--clr-accent); margin-left:auto; }
    .btn-delete-selected {
      background:#fff1f2; color:#ef4444; border:1.5px solid #fecdd3;
      border-radius:var(--radius-sm); padding:5px 14px; font-size:.82rem; font-weight:700;
      cursor:pointer; transition:all .18s; display:flex; align-items:center; gap:5px;
    }
    .btn-delete-selected:not(:disabled):hover { background:#ef4444; color:#fff; border-color:#ef4444; }
    .btn-delete-selected:disabled { opacity:.45; cursor:not-allowed; }
    body.dark-mode .btn-delete-selected { background:rgba(239,68,68,.12)!important; border-color:rgba(239,68,68,.3)!important; color:#f87171!important; }
    body.dark-mode .btn-delete-selected:not(:disabled):hover { background:#ef4444!important; color:#fff!important; }

    .file-check-input { width:16px; height:16px; cursor:pointer; accent-color:var(--clr-accent); }
    .file-row-check { flex-shrink:0; display:flex; align-items:center; padding-right:4px; cursor:pointer; }
    .file-row.selected { background:var(--clr-accent-light)!important; }
    body.dark-mode .file-row.selected { background:rgba(42,152,99,.1)!important; }

    #filePreviewContent iframe { border:none; width:100%; height:500px; display:block; }

    /* ── Security / password forms ── */
    .pf-form-card { background:var(--pf-bg-form); border-radius:var(--radius-lg); padding:24px; border:1px solid var(--pf-border); margin-bottom:16px; }
    .pf-form-card .pf-form-title { font-family:var(--font-display); font-size:.95rem; font-weight:700; color:var(--pf-text-primary); margin-bottom:18px; display:flex; align-items:center; gap:8px; }
    .pf-form-card .pf-form-title i { color:var(--clr-accent); }
    .form-control { border-radius:var(--radius-sm)!important; border:1.5px solid var(--pf-border-input)!important; font-size:.88rem!important; padding:9px 13px!important; transition:border-color .2s,box-shadow .2s!important; }
    .form-control:focus { border-color:var(--clr-accent)!important; box-shadow:0 0 0 3px rgba(31,122,77,.12)!important; }
    .form-group label { font-size:.82rem; font-weight:600; color:var(--pf-text-label); margin-bottom:5px; }
    .input-group-text { border-radius:0 var(--radius-sm) var(--radius-sm) 0!important; cursor:pointer; }
    .pf-guide-box { background:var(--pf-bg-guide); border-radius:var(--radius-md); padding:20px 22px; border:1px solid transparent; height:100%; position:relative; }
    .pf-guide-box::before { content:''; position:absolute; left:0; top:14px; bottom:14px; width:4px; border-radius:4px; background:var(--clr-button); }
    .pf-guide-box .guide-title { font-size:.82rem; font-weight:700; color:var(--clr-accent); margin-bottom:10px; text-transform:uppercase; letter-spacing:.5px; }
    .pf-guide-box ul { padding-left:18px; margin:0; }
    .pf-guide-box ul li { font-size:.83rem; color:var(--pf-text-body); margin-bottom:6px; line-height:1.5; }
    .pf-guide-box p { color:var(--pf-text-body); }
    body.dark-mode .pf-form-card { background:var(--table-stripe)!important; border-color:var(--card-border)!important; }
    body.dark-mode .pf-guide-box { background:rgba(42,152,99,.08)!important; }
    body.dark-mode .pf-guide-box .guide-title { color:var(--clr-accent)!important; }
    body.dark-mode .pf-form-card .pf-form-title { color:var(--text-primary)!important; }
    body.dark-mode .form-group label { color:var(--text-muted)!important; }

    /* ── Password strength ── */
    .password-strength { margin-top:8px; }
    .progress { height:6px; border-radius:4px; background:var(--pf-border); }
    .progress-bar { border-radius:4px; transition:width .35s ease; }
    #passwordStrengthText { font-size:.78rem; color:var(--pf-text-muted); margin-top:4px; display:block; }
    .password-match { font-size:.82rem; font-weight:500; margin-top:5px; }
    .password-hint { font-size:.78rem; color:var(--pf-text-muted); margin-top:4px; }
    body.dark-mode .progress { background:var(--card-border)!important; }
    body.dark-mode #passwordStrengthText { color:var(--text-muted)!important; }
    body.dark-mode .password-hint { color:var(--text-muted)!important; }

    /* ── Buttons ── */
    .btn-accent { background:var(--clr-button); color:var(--text-on-accent)!important; border:none; border-radius:var(--radius-sm); padding:8px 18px; font-size:.87rem; font-weight:600; transition:all .22s; cursor:pointer; box-shadow:0 4px 12px -3px rgba(26,92,56,.4); }
    .btn-accent:hover { transform:translateY(-1px); box-shadow:0 6px 16px -3px rgba(26,92,56,.5); opacity:.95; }
    .btn-accent-outline { background:transparent; color:var(--clr-accent)!important; border:1.5px solid var(--clr-accent); border-radius:var(--radius-sm); padding:7px 16px; font-size:.87rem; font-weight:600; transition:all .22s; }
    .btn-accent-outline:hover { background:var(--clr-accent-light); }
    .btn-primary,.btn-modern-primary { background:var(--clr-button)!important; border-color:var(--clr-accent)!important; color:#fff!important; border-radius:var(--radius-sm)!important; font-weight:600!important; font-size:.87rem!important; }
    .btn-primary:hover { transform:translateY(-1px); opacity:.9; }
    .btn-info { background:var(--clr-accent)!important; border-color:var(--clr-accent-dark)!important; color:#fff!important; border-radius:var(--radius-sm)!important; font-weight:600!important; }

    /* ── Modal ── */
    .modal-header { background:var(--clr-header)!important; color:#fff!important; border-radius:var(--radius-md) var(--radius-md) 0 0; }
    .modal-header .close { color:#fff!important; opacity:.8; }
    .modal-content { border-radius:var(--radius-lg)!important; border:none!important; box-shadow:var(--pf-shadow-modal)!important; overflow:hidden; }

    /* ── Misc ── */
    .manager-link { color:var(--pf-text-body); text-decoration:none; transition:color .2s; font-weight:600; }
    .manager-link:hover { color:var(--clr-accent); text-decoration:underline; }
    .password-toggle { cursor:pointer; }
    .table-responsive { border:1px solid var(--pf-table-border); border-radius:var(--radius-sm); }
    #filesTable { margin-bottom:0!important; }
    .badge-primary { background:var(--clr-accent)!important; }
    .text-primary  { color:var(--clr-accent)!important; }

    /* ── Footer fix ── */
    .wrapper { display:flex; flex-direction:column; min-height:100vh; }
    .content-wrapper { flex:1 0 auto; }
    .main-footer { flex-shrink:0; position:relative!important; bottom:auto!important; margin-left:250px!important; width:calc(100% - 250px)!important; }
    body.sidebar-collapse .main-footer { margin-left:0!important; width:100%!important; }
    @media(max-width:768px) { .main-footer { margin-left:0!important; width:100%!important; } }

    /* ── Dark mode extra ── */
    body.dark-mode .content-header h1 { color:var(--clr-accent)!important; }
    body.dark-mode .breadcrumb-item a { color:var(--text-muted)!important; }
    body.dark-mode .breadcrumb-item.active { color:var(--clr-accent)!important; }

    /* ── Scrollbar ── */
    ::-webkit-scrollbar{width:6px;height:6px} ::-webkit-scrollbar-track{background:var(--pf-scrollbar-track)}
    ::-webkit-scrollbar-thumb{background:var(--pf-scrollbar-thumb);border-radius:3px}
    ::-webkit-scrollbar-thumb:hover{background:var(--clr-accent)}
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar.php'; ?>

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1>Employee Profile</h1></div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="emp.list.php">Employees</a></li>
              <li class="breadcrumb-item active">Profile</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid" style="max-width:1100px;">

        <!-- ── Hero Banner ── -->
        <div class="manager-hero mb-4">
          <!-- Login-style background layers -->
          <div class="mh-mesh"></div>
          <div class="mh-dots"></div>
          <div class="mh-hex"></div>
          <div class="mh-orbs">
            <div class="mh-orb mh-orb-1"></div>
            <div class="mh-orb mh-orb-2"></div>
            <div class="mh-orb mh-orb-3"></div>
          </div>
          <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
          <div class="mh-arc"></div>
          <div class="mh-content">
          <div class="mh-inner">
            <?php
            $imagePath = '../dist/img/employees/' . htmlspecialchars($employee['picture'] ?? '');
            $initials  = strtoupper(substr($employee['first_name'],0,1).substr($employee['last_name'],0,1));
            if (!empty($employee['picture']) && file_exists($imagePath)): ?>
              <img class="profile-hero-avatar" src="<?= $imagePath ?>"
                   alt="<?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?>">
            <?php else: ?>
              <div class="profile-hero-initials"><?= $initials ?></div>
            <?php endif; ?>

            <div class="profile-hero-info">
              <h2><?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?></h2>
              <p class="hero-sub"><?= htmlspecialchars($employee['position_name'] ?? '—') ?></p>
              <p class="hero-id"><?= htmlspecialchars($employee['id_number'] ?? '') ?></p>

              <!-- Status badges surfaced in the hero -->
              <div class="hero-badges">
                <?php if (!empty($employee['employment_color'])): ?>
                  <span class="hero-status-badge"
                        style="background-color:<?= htmlspecialchars($employee['employment_color']) ?>;
                               color:<?= (hexdec(substr($employee['employment_color'],1))>0xffffff/2)?'#000':'#fff' ?>">
                    <i class="fas fa-circle" style="font-size:.55rem;"></i>
                    <?= htmlspecialchars($employee['employment_status']) ?>
                  </span>
                <?php endif; ?>
                <?php if (!empty($employee['appointment_color'])): ?>
                  <span class="hero-status-badge"
                        style="background-color:<?= htmlspecialchars($employee['appointment_color']) ?>;
                               color:<?= (hexdec(substr($employee['appointment_color'],1))>0xffffff/2)?'#000':'#fff' ?>">
                    <i class="fas fa-file-signature" style="font-size:.7rem;"></i>
                    <?= htmlspecialchars($employee['appointment_status']) ?>
                  </span>
                <?php endif; ?>
                <?php if ($is_manager_office_staff): ?>
                  <span class="hero-status-badge" style="background:rgba(255,255,255,.2);color:#fff;">
                    <i class="fas fa-star" style="font-size:.7rem;"></i> Manager's Office Staff
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <a href="emp.edit.php?emp_id=<?= $emp_id ?>" class="hero-edit-btn">
              <i class="fas fa-edit"></i> Edit Profile
            </a>
          </div>
          </div>
        </div><!-- /.manager-hero -->

        <!-- ── Tab bar ── -->
        <div class="pf-tabs-bar">
          <ul class="nav w-100" id="mainTabs">
            <li class="nav-item flex-fill">
              <a class="nav-link active" href="#about" data-toggle="tab">
                <i class="fas fa-user"></i> About Me
              </a>
            </li>
            <li class="nav-item flex-fill">
              <a class="nav-link" href="#file" data-toggle="tab">
                <i class="fas fa-folder"></i> Files
                <?php if (!empty($uploadedFiles)): ?>
                  <span style="font-size:.7rem;background:rgba(255,255,255,.25);border-radius:10px;padding:1px 7px;margin-left:4px;">
                    <?= count($uploadedFiles) ?>
                  </span>
                <?php endif; ?>
              </a>
            </li>
          </ul>
        </div>

        <!-- ── Tab content ── -->
        <div class="pf-tab-content">
          <div class="tab-content" style="background:transparent;padding:0;box-shadow:none;border-radius:0;min-height:unset;">

            <!-- ════════════════════ ABOUT ME ════════════════════ -->
            <div class="active tab-pane" id="about">

              <!-- 1. Personal Information -->
              <div class="pf-section-title">Personal Information</div>
              <div class="pf-info-grid">
                <div class="pf-info-tile">
                  <div class="tile-label">Full Name</div>
                  <div class="tile-value"><?= htmlspecialchars(trim($employee['first_name'].' '.($employee['middle_name']??'').' '.$employee['last_name'].' '.($employee['ext_name']??''))) ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Gender</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['gender'] ?? '—') ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Birthday</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['bday'] ?? '—') ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Email</div>
                  <div class="tile-value" style="word-break:break-all;"><?= htmlspecialchars($employee['email']) ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Phone</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['phone_number']) ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Address</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['address'] ?? '—') ?></div>
                </div>
              </div>

              <!-- 2. Assignment -->
              <div class="pf-section-title mt-2">Assignment</div>
              <div class="pf-info-grid cols-4">
                <div class="pf-info-tile">
                  <div class="tile-label">Position</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['position_name'] ?? '—') ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Office</div>
                  <div class="tile-value"><?= htmlspecialchars($employee['office_name'] ?? '—') ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Section</div>
                  <div class="tile-value"><?= htmlspecialchars($current_assignment['section_name'] ?? '—') ?></div>
                </div>
                <div class="pf-info-tile">
                  <div class="tile-label">Unit Section</div>
                  <div class="tile-value"><?= htmlspecialchars($current_assignment['unit_name'] ?? '—') ?></div>
                </div>
              </div>

              <?php if (!empty($employee['office_manager_first_name'])): ?>
              <!-- 3. Office Manager -->
              <div class="pf-section-title mt-2">Office Manager</div>
              <div class="pf-info-grid cols-2">
                <div class="pf-info-tile">
                  <div class="tile-label">Manager</div>
                  <div class="tile-value">
                    <a href="emp.profile.php?emp_id=<?= $employee['office_manager_id'] ?>">
                      <?= htmlspecialchars($employee['office_manager_first_name'].' '.$employee['office_manager_last_name']) ?>
                    </a>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <?php if ($has_leadership): ?>
              <!-- 4. Leadership Roles -->
              <div class="pf-section-title mt-2">Leadership Roles</div>
              <div class="leadership-grid">

                <?php if ($employee['is_office_manager']): ?>
                <div class="leadership-item">
                  <div class="l-title"><i class="fas fa-building mr-1" style="color:var(--clr-accent)"></i> Division Manager</div>
                  <div class="l-sub">Manages: <?= htmlspecialchars($employee['office_name']) ?></div>
                </div>
                <?php endif; ?>

                <?php foreach ($sections_as_head as $s): ?>
                <div class="leadership-item">
                  <div class="l-title"><i class="fas fa-users mr-1" style="color:var(--clr-accent)"></i> Section Head</div>
                  <div class="l-sub">
                    <?= htmlspecialchars($s['section_name']) ?>
                    <?php if (!empty($s['section_code'])): ?><span style="opacity:.6">(<?= htmlspecialchars($s['section_code']) ?>)</span><?php endif; ?>
                    <br><?= htmlspecialchars($s['office_name']) ?>
                  </div>
                  <a href="sections.php?edit=<?= $s['section_id'] ?>" class="btn btn-accent" style="font-size:.73rem;padding:4px 10px;">
                    <i class="fas fa-edit"></i> Manage
                  </a>
                </div>
                <?php endforeach; ?>

                <?php foreach ($sections_as_secretary as $s): ?>
                <div class="leadership-item focal">
                  <div class="l-title"><i class="fas fa-user-secret mr-1" style="color:#7c3aed"></i> Focal Person</div>
                  <div class="l-sub"><?= htmlspecialchars($s['section_name']) ?></div>
                  <a href="sections.php?edit=<?= $s['section_id'] ?>" class="btn btn-accent" style="font-size:.73rem;padding:4px 10px;">
                    <i class="fas fa-edit"></i> Manage
                  </a>
                </div>
                <?php endforeach; ?>

                <?php foreach ($units_as_head as $u): ?>
                <div class="leadership-item">
                  <div class="l-title"><i class="fas fa-layer-group mr-1" style="color:var(--clr-accent)"></i> Unit Head</div>
                  <div class="l-sub">
                    <?= htmlspecialchars($u['unit_name']) ?>
                    <?php if (!empty($u['unit_code'])): ?><span style="opacity:.6">(<?= htmlspecialchars($u['unit_code']) ?>)</span><?php endif; ?>
                    <br>Parent: <?= htmlspecialchars($u['section_name']) ?>
                  </div>
                  <a href="sections.php?edit_unit=<?= $u['unit_id'] ?>" class="btn btn-accent" style="font-size:.73rem;padding:4px 10px;">
                    <i class="fas fa-edit"></i> Manage
                  </a>
                </div>
                <?php endforeach; ?>

              </div><!-- /.leadership-grid -->
              <?php endif; ?>

            </div><!-- /#about -->

            <!-- ════════════════════ FILES ════════════════════ -->
            <div class="tab-pane" id="file">
              <div class="pf-section-title">Employee Files</div>

              <!-- ── Inline Upload Form ── -->
              <form method="post" enctype="multipart/form-data" id="uploadForm"
                    action="emp.profile.php?emp_id=<?= $emp_id ?>">

                <!-- File input OUTSIDE drop area — hidden, no overlay -->
                <input type="file" id="employee_files" name="employee_files[]" multiple
                       style="display:none; position:static;">

                <!-- Drop zone — click opens picker via JS -->
                <div class="file-drop-area" id="fileDropArea">
                  <div class="file-drop-inner" id="fileDropInner">
                    <i class="fas fa-cloud-upload-alt file-drop-icon"></i>
                    <p class="file-drop-title">Drag &amp; drop files here, or <span class="file-drop-browse">browse</span></p>
                    <p class="file-drop-hint">PDF, DOC, XLS, PPT, JPG, PNG &mdash; Max 200MB each</p>
                  </div>
                  <div id="selectedFilesPreview" style="display:none;">
                    <ul id="filePreviewList" class="file-preview-list"></ul>
                  </div>
                </div>

                <!-- Action buttons OUTSIDE the drop area — no overlay can intercept them -->
                <div class="file-drop-actions" id="uploadActions" style="display:none;">
                  <button type="button" class="btn-file-clear" id="clearFilesBtn">
                    <i class="fas fa-times mr-1"></i> Clear
                  </button>
                  <button type="submit" class="btn-file-upload" id="uploadSubmitBtn">
                    <i class="fas fa-upload mr-1"></i> Upload
                  </button>
                </div>

              </form>

              <!-- ── File List ── -->
              <?php if (empty($uploadedFiles)): ?>
                <div class="files-empty">
                  <i class="fas fa-folder-open"></i>
                  <p>No files uploaded yet.</p>
                </div>
              <?php else: ?>

                <form method="post" id="bulkDeleteForm"
                      action="emp.profile.php?emp_id=<?= $emp_id ?>">
                  <input type="hidden" name="delete_all_files" value="1">

                  <div class="files-toolbar">
                    <label class="files-select-all-label">
                      <input type="checkbox" id="selectAllFiles" class="file-check-input">
                      <span>Select all</span>
                    </label>
                    <span class="files-count"><i class="fas fa-file mr-1"></i><?= count($uploadedFiles) ?> file<?= count($uploadedFiles) !== 1 ? 's' : '' ?></span>
                    <button type="button" class="btn-delete-selected" id="deleteSelectedBtn" disabled>
                      <i class="fas fa-trash mr-1"></i> Delete Selected
                    </button>
                  </div>

                  <div class="files-list" id="filesList">
                    <?php foreach ($uploadedFiles as $file):
                      $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                      $ic = 'fa-file'; $ft = 'File'; $ic_color = '#6b7280';
                      if ($ext==='pdf')                            { $ic='fa-file-pdf';        $ft='PDF';        $ic_color='#ef4444'; }
                      elseif (in_array($ext,['doc','docx']))       { $ic='fa-file-word';       $ft='Word';       $ic_color='#2563eb'; }
                      elseif (in_array($ext,['xls','xlsx']))       { $ic='fa-file-excel';      $ft='Excel';      $ic_color='#16a34a'; }
                      elseif (in_array($ext,['ppt','pptx']))       { $ic='fa-file-powerpoint'; $ft='PowerPoint'; $ic_color='#ea580c'; }
                      elseif (in_array($ext,['jpg','jpeg','png','gif'])) { $ic='fa-file-image'; $ft='Image';     $ic_color='#7c3aed'; }
                    ?>
                    <div class="file-row">
                      <label class="file-row-check">
                        <input type="checkbox" name="selected_files[]"
                               value="<?= htmlspecialchars($file['name']) ?>"
                               class="file-check-input file-checkbox">
                      </label>
                      <div class="file-row-icon" style="color:<?= $ic_color ?>">
                        <i class="fas <?= $ic ?>"></i>
                      </div>
                      <div class="file-row-info">
                        <span class="file-row-name" title="<?= htmlspecialchars($file['name']) ?>">
                          <?= htmlspecialchars($file['name']) ?>
                        </span>
                        <span class="file-row-meta"><?= $ft ?> &middot; <?= formatSizeUnits($file['size']) ?> &middot; <?= date('M d, Y', $file['modified']) ?></span>
                      </div>
                      <div class="file-row-actions">
                        <a href="../dist/files/employees/<?= $emp_id ?>/<?= urlencode($file['name']) ?>"
                           class="file-action-btn download"
                           download="<?= htmlspecialchars($file['name']) ?>" title="Download">
                          <i class="fas fa-download"></i>
                        </a>
                        <button class="file-action-btn preview view-file-btn" title="Preview"
                                data-filepath="../dist/files/employees/<?= $emp_id ?>/<?= urlencode($file['name']) ?>"
                                data-filetype="<?= $ft ?>"
                                data-filename="<?= htmlspecialchars($file['name']) ?>">
                          <i class="fas fa-eye"></i>
                        </button>
                        <a href="emp.profile.php?emp_id=<?= $emp_id ?>&delete_file=<?= urlencode($file['name']) ?>"
                           class="file-action-btn delete delete-file-btn"
                           data-filename="<?= htmlspecialchars($file['name']) ?>" title="Delete">
                          <i class="fas fa-trash"></i>
                        </a>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>

                </form>

                <!-- Delete-selected password confirmation modal -->
                <div class="modal fade" id="deleteSelectedModal" tabindex="-1" role="dialog" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash mr-2"></i>Confirm Deletion</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                      </div>
                      <div class="modal-body">
                        <p id="deleteSelectedSummary" class="mb-3" style="font-size:.9rem;color:var(--pf-text-body);"></p>
                        <div class="form-group mb-0">
                          <label for="delete_all_password" style="font-size:.82rem;font-weight:600;">Enter your password to confirm</label>
                          <div class="input-group">
                            <input type="password" class="form-control" id="delete_all_password"
                                   placeholder="Your current password"
                                   autocomplete="current-password">
                            <div class="input-group-append">
                              <span class="input-group-text" onclick="togglePassword('delete_all_password')" style="cursor:pointer;">
                                <i class="fas fa-eye"></i>
                              </span>
                            </div>
                          </div>
                          <div id="deletePasswordError" class="mt-1" style="font-size:.8rem;color:#ef4444;display:none;"></div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteSelectedBtn">
                          <i class="fas fa-trash mr-1"></i> Delete Files
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

              <?php endif; ?>

            </div><!-- /#file -->

          </div><!-- /.tab-content -->
        </div><!-- /.pf-tab-content -->

      </div><!-- /.container-fluid -->
    </section>
  </div><!-- /.content-wrapper -->

  <?php include '../includes/mainfooter.php'; ?>
</div><!-- ./wrapper -->

<?php include '../includes/footer.php'; ?>

<!-- ── File Preview Modal ── -->
<div class="modal fade" id="filePreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">File Preview</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="filePreviewContent"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <a id="downloadPreviewBtn" href="#" class="btn btn-primary" download><i class="fas fa-download"></i> Download</a>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<?php if (isset($_SESSION['toast'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const t = <?php echo json_encode($_SESSION['toast']); ?>;
    Swal.fire({ toast:true, position:'top-end', icon:t.type, title:t.message,
                showConfirmButton:false, timer:3000, timerProgressBar:true,
                didOpen:(el)=>{ el.addEventListener('mouseenter',Swal.stopTimer); el.addEventListener('mouseleave',Swal.resumeTimer); }});
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

<script src="../plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ── Drag-and-drop upload area ── */
    const dropArea    = document.getElementById('fileDropArea');
    const fileInput   = document.getElementById('employee_files');
    const inner       = document.getElementById('fileDropInner');
    const selPreview  = document.getElementById('selectedFilesPreview');
    const previewList = document.getElementById('filePreviewList');
    const clearBtn    = document.getElementById('clearFilesBtn');
    const uploadActions = document.getElementById('uploadActions');

    function fmtSize(b) {
        return b>=1048576?(b/1048576).toFixed(1)+' MB':b>=1024?(b/1024).toFixed(1)+' KB':b+' B';
    }
    function renderPicked(files) {
        if (!previewList) return;
        previewList.innerHTML = '';
        for (let f of files) {
            previewList.innerHTML += `<li class="file-preview-item">
              <i class="fas fa-file mr-2" style="color:var(--clr-accent)"></i>
              <span class="fpi-name">${f.name}</span>
              <span class="fpi-size">${fmtSize(f.size)}</span></li>`;
        }
        if (inner)         inner.style.display         = 'none';
        if (selPreview)    selPreview.style.display     = 'block';
        if (uploadActions) uploadActions.style.display  = 'flex';
    }
    function resetUpload() {
        if (fileInput)     fileInput.value              = '';
        if (inner)         inner.style.display          = 'block';
        if (selPreview)    selPreview.style.display      = 'none';
        if (previewList)   previewList.innerHTML         = '';
        if (uploadActions) uploadActions.style.display   = 'none';
    }
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (this.files.length) renderPicked(this.files);
            else resetUpload();
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () { resetUpload(); });
    }
    // Drop area click → open file picker directly (no overlay to guard against)
    if (dropArea && fileInput) {
        dropArea.addEventListener('click', function () {
            fileInput.click();
        });
        ['dragenter','dragover'].forEach(ev => dropArea.addEventListener(ev, function(e){
            e.preventDefault(); e.stopPropagation(); dropArea.classList.add('drag-over');
        }));
        ['dragleave','drop'].forEach(ev => dropArea.addEventListener(ev, function(e){
            e.preventDefault(); e.stopPropagation(); dropArea.classList.remove('drag-over');
        }));
        dropArea.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            if (dt && dt.files.length) { fileInput.files = dt.files; renderPicked(dt.files); }
        });
    }

    /* ── Checkbox / select-all / delete-selected ── */
    const selectAllCb   = document.getElementById('selectAllFiles');
    const deleteSelBtn  = document.getElementById('deleteSelectedBtn');
    const confirmDelBtn = document.getElementById('confirmDeleteSelectedBtn');
    const delPwInput    = document.getElementById('delete_all_password');
    const delPwErr      = document.getElementById('deletePasswordError');
    const bulkForm      = document.getElementById('bulkDeleteForm');

    function updateDeleteBtn() {
        if (!deleteSelBtn) return;
        const checked = document.querySelectorAll('.file-checkbox:checked').length;
        deleteSelBtn.disabled = checked === 0;
        document.querySelectorAll('.file-checkbox').forEach(function(cb) {
            cb.closest('.file-row').classList.toggle('selected', cb.checked);
        });
        if (selectAllCb) {
            const total = document.querySelectorAll('.file-checkbox').length;
            selectAllCb.indeterminate = checked > 0 && checked < total;
            selectAllCb.checked = checked === total && total > 0;
        }
    }
    if (selectAllCb) {
        selectAllCb.addEventListener('change', function() {
            document.querySelectorAll('.file-checkbox').forEach(cb => { cb.checked = this.checked; });
            updateDeleteBtn();
        });
    }
    document.querySelectorAll('.file-checkbox').forEach(cb => cb.addEventListener('change', updateDeleteBtn));

    if (deleteSelBtn) {
        deleteSelBtn.addEventListener('click', function() {
            const count = document.querySelectorAll('.file-checkbox:checked').length;
            if (!count) return;
            const summary = document.getElementById('deleteSelectedSummary');
            if (summary) summary.textContent = `You are about to permanently delete ${count} file${count!==1?'s':''}. This cannot be undone.`;
            if (delPwInput) delPwInput.value = '';
            if (delPwErr) { delPwErr.style.display = 'none'; delPwErr.textContent = ''; }
            // Show Bootstrap modal — works with Bootstrap 4 (jQuery) or Bootstrap 5
            const modalEl = document.getElementById('deleteSelectedModal');
            if (modalEl) {
                if (typeof $ !== 'undefined') $(modalEl).modal('show');
                else if (window.bootstrap) new window.bootstrap.Modal(modalEl).show();
            }
        });
    }
    if (confirmDelBtn && bulkForm) {
        confirmDelBtn.addEventListener('click', function() {
            const pw = delPwInput ? delPwInput.value.trim() : '';
            if (!pw) {
                if (delPwErr) { delPwErr.textContent = 'Password is required.'; delPwErr.style.display = 'block'; }
                return;
            }
            let hiddenPw = bulkForm.querySelector('input[name="delete_all_password"]');
            if (!hiddenPw) {
                hiddenPw = document.createElement('input');
                hiddenPw.type = 'hidden';
                hiddenPw.name = 'delete_all_password';
                bulkForm.appendChild(hiddenPw);
            }
            hiddenPw.value = pw;
            bulkForm.submit();
        });
    }

    /* ── Single-file delete confirmation (vanilla, no jQuery) ── */
    document.querySelectorAll('.delete-file-btn').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const url  = this.getAttribute('href');
            const name = this.dataset.filename;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Delete File?',
                    text: `"${name}" will be permanently deleted.`,
                    icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then(r => { if (r.isConfirmed) window.location.href = url; });
            } else {
                if (confirm(`Delete "${name}"? This cannot be undone.`)) window.location.href = url;
            }
        });
    });

    /* ── File preview — iframe for PDF, inline for images ── */
    document.querySelectorAll('.view-file-btn').forEach(function(el) {
        el.addEventListener('click', function() {
            const fp  = this.dataset.filepath;
            const ft  = (this.dataset.filetype || '').toLowerCase();
            const con = document.getElementById('filePreviewContent');
            const dlBtn = document.getElementById('downloadPreviewBtn');
            if (!con) return;
            if (dlBtn) { dlBtn.href = fp; dlBtn.setAttribute('download', fp.split('/').pop()); }
            con.innerHTML = '';
            if (ft === 'pdf') {
                con.innerHTML = `<iframe src="${fp}" width="100%" height="520"
                    style="border:none;display:block;" title="PDF Preview">
                    <p>Cannot display PDF. <a href="${fp}" download>Download instead</a>.</p>
                  </iframe>`;
            } else if (['jpg','jpeg','png','gif','image'].includes(ft)) {
                con.innerHTML = `<img src="${fp}" class="img-fluid"
                    style="display:block;max-height:520px;margin:0 auto;" alt="Preview">`;
            } else {
                con.innerHTML = `<div class="text-center p-5">
                  <i class="fas fa-file fa-5x mb-3 text-muted"></i>
                  <p class="lead">This file type cannot be previewed.</p>
                  <p>Please download the file to view it.</p></div>`;
            }
            const modalEl = document.getElementById('filePreviewModal');
            if (modalEl) {
                if (typeof $ !== 'undefined') $(modalEl).modal('show');
                else if (window.bootstrap) new window.bootstrap.Modal(modalEl).show();
            }
        });
    });

});/* end DOMContentLoaded */

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = input.parentNode.querySelector('.password-toggle i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
    } else {
        input.type = 'password';
        if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
    }
}
</script>
</body>
</html>