<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/travel_order_engine.php';   // formats, Word filling, print preview, report

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$database = new Database();
$db = $database->getConnection();

/* Who may use this module (same rule as Certificate of Appearance). Change the permission name here if needed. */
const TO_PERMISSION = 'manage_employees';

$module_name = 'Travel Order';
$check_stmt = $db->prepare("SELECT is_under_maintenance FROM system_modules WHERE module_name = ?");
$check_stmt->bind_param("s", $module_name);
$check_stmt->execute();
$result = $check_stmt->get_result();
if ($result->num_rows > 0) {
  $module = $result->fetch_assoc();
  if ($module['is_under_maintenance'] && !hasPermission('manage_settings')) {
    $_SESSION['error'] = "The $module_name module is currently under maintenance. Please try again later.";
    header("Location: ../unauthorized.php");
    exit();
  }
}

if (!hasPermission(TO_PERMISSION)) {
  $_SESSION['error'] = "You do not have permission to access this module.";
  header("Location: ../unauthorized.php");
  exit();
}

$positions = [];
$pr = $db->query("SELECT position_name FROM position ORDER BY position_name ASC");
while ($row = $pr->fetch_assoc()) { $positions[] = $row['position_name']; }

/* Employees for the "Personnel to travel" dropdown. Position comes from the employee's own position_id. */
$employees = [];
$er = $db->query("SELECT e.emp_id, e.id_number, e.first_name, e.middle_name, e.last_name, e.ext_name, p.position_name
                  FROM employee e
                  LEFT JOIN position p ON p.position_id = e.position_id
                  ORDER BY e.last_name ASC, e.first_name ASC");
if ($er) {
  while ($row = $er->fetch_assoc()) {
    $nm = to_employee_name($row);
    if ($nm === '') continue;
    $employees[] = ['id' => (int)$row['emp_id'], 'idno' => (string)($row['id_number'] ?? ''), 'name' => $nm, 'position' => trim((string)($row['position_name'] ?? ''))];
  }
}

$TO_TEMPLATES = to_templates();

/* ───────────────────────── database helpers ───────────────────────── */
function to_ids_from($raw) {
  $ids = array_values(array_unique(array_filter(array_map('intval', is_array($raw) ? $raw : explode(',', (string)$raw)))));
  return array_slice($ids, 0, TO_MAX_BATCH);
}

/* the saved travel orders with these ids, as engine models (oldest number first) */
function to_fetch_models($db, array $ids) {
  if (!$ids) return [];
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $db->prepare("SELECT * FROM travel_order WHERE to_id IN ($ph) ORDER BY series_year ASC, to_seq ASC, to_id ASC");
  $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
  $stmt->execute();
  $out = [];
  foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $out[] = to_model($r);
  return $out;
}

/* next free number for a code + series year, e.g. ADM / 2026 -> 241 */
function to_next_seq($db, $code, $year) {
  $stmt = $db->prepare("SELECT COALESCE(MAX(to_seq), 0) + 1 AS n FROM travel_order WHERE to_code = ? AND series_year = ?");
  $stmt->bind_param('si', $code, $year);
  $stmt->execute();
  return (int)$stmt->get_result()->fetch_assoc()['n'];
}

function to_number_taken($db, $code, $seq, $year, $except_id) {
  $stmt = $db->prepare("SELECT 1 FROM travel_order WHERE to_code = ? AND to_seq = ? AND series_year = ? AND to_id <> ? LIMIT 1");
  $stmt->bind_param('siii', $code, $seq, $year, $except_id);
  $stmt->execute();
  $stmt->store_result();
  return $stmt->num_rows > 0;
}

/* ───────────────────── download (GET): one order, or all selected in ONE .docx ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($_GET['action'] ?? '', ['download', 'download_bulk'], true)) {
  $ids = to_ids_from($_GET['ids'] ?? ($_GET['id'] ?? ''));
  if (!$ids) { http_response_code(400); exit('No travel orders selected.'); }
  $models = to_fetch_models($db, $ids);
  if (!$models) { http_response_code(404); exit('Travel order not found.'); }
  to_download_docx($models, $_GET['template'] ?? null);
}

/* ───────────────────── letterhead picture used by the print preview ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'letterhead') {
  $f = to_letterhead_file((string)($_GET['template'] ?? ''));
  if (!is_file($f)) { http_response_code(404); exit('Letterhead not found.'); }
  header('Content-Type: image/png');
  header('Content-Length: ' . filesize($f));
  header('Cache-Control: private, max-age=86400');
  readfile($f);
  exit();
}

/* ───────────────────── print preview (GET): one (id) or several (ids) ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'preview') {
  $ids = to_ids_from($_GET['ids'] ?? ($_GET['id'] ?? ''));
  if (!$ids) { http_response_code(400); exit('No travel orders selected.'); }
  $models = to_fetch_models($db, $ids);
  if (!$models) { http_response_code(404); exit('Travel orders not found.'); }
  to_preview_page($models, $_GET['template'] ?? null, function ($k) {
    return 'travel_order.php?action=letterhead&template=' . urlencode($k);
  });
}

/* ───────────────────── report (GET): print-ready yearly / monthly report ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'report') {
  $year = (int)($_GET['year'] ?? date('Y'));
  if ($year < 2000 || $year > 2100) $year = (int)date('Y');
  $month = (int)($_GET['month'] ?? 0);
  $by_month = $month >= 1 && $month <= 12;

  $sql = "SELECT * FROM travel_order WHERE YEAR(travel_start) = ?" . ($by_month ? " AND MONTH(travel_start) = ?" : "")
       . " ORDER BY series_year ASC, to_seq ASC, to_id ASC";
  $stmt = $db->prepare($sql);
  if ($by_month) { $stmt->bind_param('ii', $year, $month); } else { $stmt->bind_param('i', $year); }
  $stmt->execute();
  $models = [];
  foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $models[] = to_model($r);
  $period = $by_month ? strtoupper(date('F', mktime(0, 0, 0, $month, 1))) . ' ' . $year : 'CY ' . $year;
  to_report_page($models, $period);
}

/* ───────────────────────── AJAX (POST) ───────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');

  try {
    $action = $_POST['action'];

    if ($action === 'create' || $action === 'update') {
      $d = to_collect($_POST);
      if ($err = to_validate($d)) {
        echo json_encode(['success' => false, 'message' => $err]);
        exit();
      }
      $id = $action === 'update' ? (int)($_POST['to_id'] ?? 0) : 0;
      if (to_number_taken($db, $d['to_code'], $d['to_seq'], $d['series_year'], $id)) {
        echo json_encode(['success' => false, 'message' => 'Travel order No. ' . $d['to_code'] . '-TO-' . $d['to_seq'] . ' s. ' . $d['series_year'] . ' already exists. Please use another number.']);
        exit();
      }
      [$t_start, $t_end] = to_span($d['items']);
      $personnel = json_encode($d['people'], JSON_UNESCAPED_UNICODE);
      $itinerary = json_encode($d['items'],  JSON_UNESCAPED_UNICODE);

      if ($action === 'create') {
        $created_by = (int)$_SESSION['user_id'];
        $stmt = $db->prepare("INSERT INTO travel_order
          (to_code, to_seq, series_year, to_date, template_key, personnel, itinerary, travel_start, travel_end,
           expected_output, remarks, approver_name, approver_title, recommender_name, recommender_title, prepared_name, prepared_title, created_by)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sii' . str_repeat('s', 14) . 'i',
          $d['to_code'], $d['to_seq'], $d['series_year'], $d['to_date'], $d['template_key'], $personnel, $itinerary, $t_start, $t_end,
          $d['expected_output'], $d['remarks'], $d['approver_name'], $d['approver_title'], $d['recommender_name'], $d['recommender_title'],
          $d['prepared_name'], $d['prepared_title'], $created_by);
        if ($stmt->execute()) {
          echo json_encode(['success' => true, 'message' => 'Travel order saved! No.: ' . $d['to_code'] . '-TO-' . $d['to_seq'] . ' s. ' . $d['series_year'], 'id' => $db->insert_id]);
        } else {
          echo json_encode(['success' => false, 'message' => 'Failed to save travel order.']);
        }
      } else {
        $stmt = $db->prepare("UPDATE travel_order SET
          to_code=?, to_seq=?, series_year=?, to_date=?, template_key=?, personnel=?, itinerary=?, travel_start=?, travel_end=?,
          expected_output=?, remarks=?, approver_name=?, approver_title=?, recommender_name=?, recommender_title=?, prepared_name=?, prepared_title=?
          WHERE to_id=?");
        $stmt->bind_param('sii' . str_repeat('s', 14) . 'i',
          $d['to_code'], $d['to_seq'], $d['series_year'], $d['to_date'], $d['template_key'], $personnel, $itinerary, $t_start, $t_end,
          $d['expected_output'], $d['remarks'], $d['approver_name'], $d['approver_title'], $d['recommender_name'], $d['recommender_title'],
          $d['prepared_name'], $d['prepared_title'], $id);
        echo json_encode($stmt->execute()
          ? ['success' => true,  'message' => 'Travel order updated successfully!']
          : ['success' => false, 'message' => 'Failed to update travel order.']);
      }
      exit();
    }

    if ($action === 'next_number') {
      $code = to_upper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['to_code'] ?? ''))) ?: TO_DEFAULT_CODE;
      $year = (int)($_POST['series_year'] ?? date('Y'));
      echo json_encode(['success' => true, 'seq' => to_next_seq($db, $code, $year)]);
      exit();
    }

    if ($action === 'bulk_delete') {
      $ids = to_ids_from($_POST['ids'] ?? []);
      if (!$ids) {
        echo json_encode(['success' => false, 'message' => 'No travel orders selected.']);
        exit();
      }
      $ph = implode(',', array_fill(0, count($ids), '?'));
      $stmt = $db->prepare("DELETE FROM travel_order WHERE to_id IN ($ph)");
      $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
      if ($stmt->execute()) {
        $n = $stmt->affected_rows;
        echo json_encode(['success' => true, 'count' => $n, 'message' => $n . ' travel order' . ($n === 1 ? '' : 's') . ' deleted successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete the selected travel orders.']);
      }
      exit();
    }

    if ($action === 'delete_all') {
      if (($_POST['confirm'] ?? '') !== 'DELETE') {
        echo json_encode(['success' => false, 'message' => 'Confirmation text did not match.']);
        exit();
      }
      if ($db->query("DELETE FROM travel_order")) {
        $n = $db->affected_rows;
        echo json_encode(['success' => true, 'count' => $n, 'message' => 'All travel orders (' . $n . ') deleted successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete travel orders.']);
      }
      exit();
    }

    if ($action === 'delete') {
      $id = (int)($_POST['to_id'] ?? 0);
      $stmt = $db->prepare("DELETE FROM travel_order WHERE to_id = ?");
      $stmt->bind_param("i", $id);
      echo json_encode($stmt->execute()
        ? ['success' => true,  'message' => 'Travel order deleted successfully!']
        : ['success' => false, 'message' => 'Failed to delete travel order.']);
      exit();
    }

    if ($action === 'get') {
      $id = (int)($_POST['to_id'] ?? 0);
      $stmt = $db->prepare("SELECT * FROM travel_order WHERE to_id = ?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      if ($row) {
        $m = to_model($row);
        $m['to_id'] = (int)$row['to_id'];
        $m['to_date'] = $row['to_date'];
        $m['to_number'] = to_number($m);
        $m['created_at'] = $row['created_at'] ?? null;
        unset($m['personnel'], $m['itinerary']);
        echo json_encode(['success' => true, 'data' => $m], JSON_UNESCAPED_UNICODE);
      } else {
        echo json_encode(['success' => false, 'message' => 'Travel order not found.']);
      }
      exit();
    }
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
  }
}

// ── List ──
$orders = [];
$r = $db->query("SELECT * FROM travel_order ORDER BY travel_start DESC, to_id DESC");
while ($row = $r->fetch_assoc()) { $orders[] = to_model($row) + ['to_id' => (int)$row['to_id']]; }

$known_codes = [TO_DEFAULT_CODE];
$r = $db->query("SELECT DISTINCT to_code FROM travel_order ORDER BY to_code ASC");
while ($row = $r->fetch_assoc()) { $known_codes[] = $row['to_code']; }
$known_codes = array_values(array_unique($known_codes));

$cur_year = (int)date('Y');
$next_seq = to_next_seq($db, TO_DEFAULT_CODE, $cur_year);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Travel Order</title>
  <?php include '../includes/header.php'; ?>
  <style>
    :root {
      --h-bg:       #eef7f2;
      --h-card:     #ffffff;
      --h-card-alt: #f0faf5;
      --h-surface3: #e2f3ea;
      --h-border:   rgba(42,152,99,0.18);
      --h-text:     #0f2d1e;
      --h-muted:    #4a7a5e;
      --h-primary:  #2a9863;
      --h-primary-dim: rgba(42,152,99,.10);
      --h-accent:   #24e78f;
      --h-success:  #2a9863;
      --h-warning:  #e67700;
      --h-danger:   #c92a2a;
      --h-blue:     #2a9863;
      --h-shadow:   0 4px 24px rgba(42,152,99,.12);
      --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
    }
    body.dark-mode {
      --h-bg:       #0b1f17;
      --h-card:     #102f22;
      --h-card-alt: #0e2619;
      --h-surface3: #16352a;
      --h-border:   rgba(36,231,143,0.12);
      --h-text:     #d4f5e5;
      --h-muted:    #6aad8a;
      --h-primary:  #24e78f;
      --h-primary-dim: rgba(36,231,143,.12);
      --h-accent:   #2a9863;
      --h-success:  #24e78f;
      --h-warning:  #ffd43b;
      --h-danger:   #ff6b6b;
      --h-blue:     #24e78f;
      --h-shadow:   0 4px 24px rgba(0,0,0,.35);
      --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
    }

    .content-wrapper { background: var(--h-bg) !important; }

    /* CRITICAL Z-INDEX FIX FOR MODAL OVERLAP */
    .main-header.navbar, .main-header, nav.main-header, header.main-header {
        z-index: 1000 !important;
    }

    .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
    
    .main-sidebar, aside.main-sidebar {
        z-index: 999 !important;
    }
    .modal-backdrop {
        z-index: 1040 !important;
    }
    .modal {
        z-index: 1050 !important;
    }
    .modal-dialog {
        z-index: 1051 !important;
    }
    .modal-content {
        z-index: 1052 !important;
    }
    .select2-container, .select2-dropdown {
        z-index: 1055 !important;
    }
  
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

        /* ── Card (mirrors the Scrum > Projects "card" look) ── */
        .card {
            background: var(--h-card) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 12px !important;
            box-shadow: var(--h-shadow-sm) !important;
        }
        .card-header {
            background: var(--h-card) !important;
            border-bottom: 1px solid var(--h-border) !important;
            padding: 14px 20px !important;
        }
        .card-title {
            font-weight: 700 !important;
            font-size: .9rem !important;
            color: var(--h-text) !important;
            letter-spacing: -.2px;
        }
        .card-body { background: var(--h-card) !important; padding: 18px !important; }
        .card-tools .btn-primary {
            background: var(--h-primary) !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: .8rem !important;
            box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .card-tools .btn-primary:hover { filter: brightness(1.1) !important; }

        /* ── Table ── */
        #toTable.table { color: var(--h-text) !important; }
        #toTable.table thead th {
            background: var(--h-card-alt) !important;
            border: none !important;
            border-bottom: 1px solid var(--h-border) !important;
            color: var(--h-muted) !important;
            font-size: .68rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .6px !important;
            padding: 10px 14px !important;
        }
        #toTable.table tbody tr { transition: background .12s; }
        #toTable.table tbody tr:hover { background: var(--h-card-alt) !important; }
        #toTable.table tbody td {
            border-top: 1px solid var(--h-border) !important;
            border-left: none !important;
            border-right: none !important;
            padding: 12px 14px !important;
            vertical-align: middle !important;
            font-size: .85rem !important;
            color: var(--h-text) !important;
        }
        #toTable.table-bordered { border: none !important; }

        /* Status badges */
        .badge-secondary { background: var(--h-surface3) !important; color: var(--h-muted) !important; border-radius: 5px !important; }
        .badge-warning   { background: rgba(230,119,0,.15) !important; color: var(--h-warning) !important; border-radius: 5px !important; }
        .badge-info      { background: rgba(42,152,99,.12) !important; color: var(--h-blue) !important; border-radius: 5px !important; }
        .badge-primary   { background: rgba(167,139,250,.15) !important; color: #7c5cd6 !important; border-radius: 5px !important; }
        .badge-success   { background: rgba(42,152,99,.15) !important; color: var(--h-success) !important; border-radius: 5px !important; }
        .badge-danger    { background: rgba(201,34,46,.15) !important; color: var(--h-danger) !important; border-radius: 5px !important; }

        /* Row action buttons */
        #toTable .btn-sm.btn-info    { background: rgba(42,152,99,.12) !important; color: var(--h-blue) !important; border: 1px solid rgba(42,152,99,.25) !important; border-radius: 7px !important; }
        #toTable .btn-sm.btn-warning { background: rgba(230,119,0,.12) !important; color: var(--h-warning) !important; border: 1px solid rgba(230,119,0,.25) !important; border-radius: 7px !important; }
        #toTable .btn-sm.btn-danger  { background: rgba(201,34,46,.12) !important; color: var(--h-danger) !important; border: 1px solid rgba(201,34,46,.25) !important; border-radius: 7px !important; }
        #toTable .btn-sm:hover { filter: brightness(1.15); transform: translateY(-1px); }

        /* ── Scrum-style modal (mirrors the "Create New Project" modal in
           Scrum > Projects) — applied to Add/Edit/View Applicant ── */
        .scrum-style-modal .modal-content {
            background: var(--h-card) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 14px !important;
            box-shadow: 0 24px 80px rgba(0,0,0,.25) !important;
            color: var(--h-text) !important;
        }
        .scrum-style-modal .modal-header {
            background: var(--h-card-alt) !important;
            border-bottom: 1px solid var(--h-border) !important;
            border-radius: 14px 14px 0 0 !important;
            padding: 16px 20px !important;
        }
        .scrum-style-modal .modal-title {
            font-weight: 700 !important;
            font-size: .95rem !important;
            color: var(--h-text) !important;
        }
        .scrum-style-modal .modal-title i { color: var(--h-primary) !important; margin-right: 8px; }
        .scrum-style-modal .modal-header .close {
            color: var(--h-muted) !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }
        .scrum-style-modal .modal-header .close:hover { opacity: .7 !important; }
        .scrum-style-modal .modal-body { padding: 20px !important; background: var(--h-card) !important; }
        .scrum-style-modal .modal-footer {
            border-top: 1px solid var(--h-border) !important;
            padding: 14px 20px !important;
            background: var(--h-card-alt) !important;
            border-radius: 0 0 14px 14px !important;
        }
        .scrum-style-modal label {
            color: var(--h-muted) !important;
            font-size: .72rem !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: .5px !important;
            margin-bottom: 5px !important;
        }
        .scrum-style-modal label .text-danger { text-transform: none !important; }
        .scrum-style-modal .form-control,
        .scrum-style-modal .form-control:focus {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 8px !important;
            font-size: .875rem !important;
        }
        .scrum-style-modal .form-control::placeholder { color: var(--h-muted) !important; }
        .scrum-style-modal .form-control:focus {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }
        .scrum-style-modal select.form-control option { background: var(--h-card) !important; color: var(--h-text) !important; }
        .scrum-style-modal hr { border-color: var(--h-border) !important; }
        .scrum-style-modal h6 {
            color: var(--h-muted) !important;
            font-size: .72rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .5px !important;
        }
        .scrum-style-modal .form-text.text-muted { color: var(--h-muted) !important; }
        .scrum-style-modal .info-box {
            background: var(--h-card-alt) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 8px !important;
            padding: 10px 14px !important;
            font-size: .8rem !important;
            color: var(--h-text) !important;
        }

        /* Bootstrap custom-file input */
        .scrum-style-modal .custom-file-label {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 8px !important;
        }
        .scrum-style-modal .custom-file-label::after {
            background: var(--h-surface3) !important;
            color: var(--h-muted) !important;
            border-left: 1.5px solid var(--h-border) !important;
            border-radius: 0 8px 8px 0 !important;
        }
        .scrum-style-modal .custom-file-input:focus ~ .custom-file-label {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }

        /* Select2 (default theme) — Position Applied picker */
        .scrum-style-modal .select2-container--default .select2-selection--multiple {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            border-radius: 8px !important;
            min-height: 38px !important;
        }
        .scrum-style-modal .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: var(--h-primary-dim) !important;
            border: 1px solid var(--h-primary) !important;
            color: var(--h-text) !important;
            border-radius: 5px !important;
        }
        .scrum-style-modal .select2-container--default .select2-selection__choice__remove { color: var(--h-muted) !important; }
        .scrum-style-modal .select2-container--default .select2-selection__choice__remove:hover { color: var(--h-danger) !important; }
        .scrum-style-modal .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }
        .scrum-style-modal .select2-dropdown {
            background: var(--h-card-alt) !important;
            border: 1px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 8px !important;
            box-shadow: var(--h-shadow) !important;
        }
        .scrum-style-modal .select2-search--dropdown .select2-search__field {
            background: var(--h-card) !important;
            border: 1px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 6px !important;
        }
        .scrum-style-modal .select2-results__option { color: var(--h-text) !important; background: transparent !important; }
        .scrum-style-modal .select2-results__option--highlighted[aria-selected] {
            background: var(--h-primary) !important;
            color: #fff !important;
        }

        /* Select2 (single) — Designation picker, themed to match the form inputs */
        .scrum-style-modal .select2-container { width: 100% !important; }
        .scrum-style-modal .select2-container--default .select2-selection--single {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            border-radius: 8px !important;
            height: 38px !important;
            display: flex; align-items: center;
        }
        .scrum-style-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--h-text) !important;
            font-size: .875rem !important;
            line-height: 36px !important;
            padding-left: 12px !important;
            padding-right: 30px !important;
        }
        .scrum-style-modal .select2-container--default .select2-selection--single .select2-selection__placeholder { color: var(--h-muted) !important; }
        .scrum-style-modal .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; right: 6px !important; }
        .scrum-style-modal .select2-container--default .select2-selection--single .select2-selection__arrow b { border-color: var(--h-primary) transparent transparent transparent !important; }
        .scrum-style-modal .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b { border-color: transparent transparent var(--h-primary) transparent !important; }
        .scrum-style-modal .select2-container--default.select2-container--focus .select2-selection--single,
        .scrum-style-modal .select2-container--default.select2-container--open .select2-selection--single {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }
        .scrum-style-modal .select2-results__options { max-height: 220px; }
        .scrum-style-modal .select2-results__option { padding: 8px 12px !important; font-size: .875rem; }
        .scrum-style-modal .select2-results__option[aria-selected=true] {
            background: var(--h-primary-dim) !important;
            color: var(--h-primary) !important;
            font-weight: 600;
        }
        .scrum-style-modal .select2-results__options::-webkit-scrollbar { width: 6px; }
        .scrum-style-modal .select2-results__options::-webkit-scrollbar-thumb { background: var(--h-primary); border-radius: 6px; }
        .scrum-style-modal .select2-results__options::-webkit-scrollbar-track { background: transparent; }
        .scrum-style-modal .select2-search--dropdown { padding: 8px !important; }

        /* Buttons */
        .scrum-style-modal .btn-secondary {
            background: var(--h-card-alt) !important;
            color: var(--h-muted) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
        }
        .scrum-style-modal .btn-primary,
        .scrum-style-modal .btn-warning {
            background: var(--h-primary) !important;
            color: #fff !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .scrum-style-modal .btn-primary:hover,
        .scrum-style-modal .btn-warning:hover { filter: brightness(1.1) !important; color: #fff !important; }

        /* View Applicant detail content */
        .scrum-style-modal #viewApplicantContent p { color: var(--h-text) !important; }
        .scrum-style-modal #viewApplicantContent strong { color: var(--h-muted) !important; font-weight: 700 !important; }
        .scrum-style-modal #viewApplicantContent a { color: var(--h-primary) !important; }
        .scrum-style-modal #viewApplicantContent hr { border-color: var(--h-border) !important; }

        /* Applicant Details tabs */
        .applicant-detail-tabs {
            border-bottom: 1px solid var(--h-border) !important;
            margin: -20px -20px 18px !important;
            padding: 0 20px !important;
        }
        .applicant-detail-tabs .nav-link {
            border: none !important;
            border-bottom: 2px solid transparent !important;
            color: var(--h-muted) !important;
            font-size: .82rem !important;
            font-weight: 700 !important;
            padding: 12px 14px !important;
            border-radius: 0 !important;
            transition: color .15s, border-color .15s;
        }
        .applicant-detail-tabs .nav-link:hover { color: var(--h-text) !important; }
        .applicant-detail-tabs .nav-link.active {
            color: var(--h-primary) !important;
            background: transparent !important;
            border-bottom-color: var(--h-primary) !important;
        }
        .applicant-detail-tab-content { padding-top: 2px; }

        /* Uploaded documents nav list */
        .doc-nav-list { display: flex; flex-direction: column; gap: 8px; }
        .doc-nav-item {
            display: flex; align-items: center; gap: 12px;
            background: var(--h-card-alt) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 10px !important;
            padding: 10px 14px !important;
            text-decoration: none !important;
            transition: border-color .15s, transform .15s, box-shadow .15s;
        }
        .doc-nav-item:not(.doc-nav-item-empty):hover {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 1px var(--h-primary-dim) !important;
            transform: translateY(-1px);
        }
        .doc-nav-item-empty { opacity: .6; cursor: default; }
        .doc-nav-icon {
            width: 34px; height: 34px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: var(--h-surface3) !important;
            border-radius: 8px !important;
            font-size: .95rem;
        }
        .doc-nav-info { display: flex; flex-direction: column; min-width: 0; flex: 1; }
        .doc-nav-label {
            font-size: .68rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: var(--h-muted) !important;
        }
        .doc-nav-file {
            font-size: .84rem; color: var(--h-text) !important; font-weight: 500;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .doc-nav-item-empty .doc-nav-file { color: var(--h-muted) !important; font-style: italic; }
        .doc-nav-open {
            color: var(--h-muted) !important;
            font-size: .8rem; flex-shrink: 0;
        }
        .doc-nav-item:hover .doc-nav-open { color: var(--h-primary) !important; }
  
    /* ── Distinct badge colors: Provided = green, Not Provided = red; templates = blue / purple ── */
    #toTable .badge { font-size: .7rem; font-weight: 700; padding: 5px 9px; border-radius: 6px !important; letter-spacing: .2px; }
    .badge-ca-yes { background: rgba(42,152,99,.16) !important; color: #1f7a4d !important; border: 1px solid rgba(42,152,99,.35); }
    .badge-ca-no  { background: rgba(201,42,42,.12) !important; color: #c92a2a !important; border: 1px solid rgba(201,42,42,.30); }
    .badge-tpl-standard    { background: rgba(37,99,235,.12) !important; color: #1d4ed8 !important; border: 1px solid rgba(37,99,235,.30); }
    .badge-tpl-imo_manager { background: rgba(124,58,237,.12) !important; color: #6d28d9 !important; border: 1px solid rgba(124,58,237,.30); }
    .badge-tpl { white-space: normal; }
    body.dark-mode .badge-ca-yes { color: #24e78f !important; background: rgba(36,231,143,.14) !important; border-color: rgba(36,231,143,.35); }
    body.dark-mode .badge-ca-no  { color: #ff8787 !important; background: rgba(255,107,107,.14) !important; border-color: rgba(255,107,107,.35); }
    body.dark-mode .badge-tpl-standard    { color: #7cb0ff !important; background: rgba(96,150,255,.15) !important; border-color: rgba(96,150,255,.35); }
    body.dark-mode .badge-tpl-imo_manager { color: #c4a5ff !important; background: rgba(167,139,250,.15) !important; border-color: rgba(167,139,250,.35); }

    /* ── Match Applicant Databank look: header buttons, row buttons, dropdowns, checkboxes ── */
    .card-tools .btn { border-radius: 8px !important; font-weight: 700 !important; font-size: .8rem !important; }
    .card-tools .btn-success,
    .card-tools .btn-outline-success {
        background: var(--h-primary-dim) !important;
        color: var(--h-primary) !important;
        border: 1px solid var(--h-primary) !important;
        box-shadow: none !important;
    }
    .card-tools .btn-success:hover,
    .card-tools .btn-outline-success:hover:not(:disabled) { background: var(--h-primary) !important; color: #fff !important; }
    .card-tools .btn-outline-success:disabled { opacity: .5; cursor: not-allowed; }
    .card-tools .btn-outline-danger {
        background: rgba(201,34,46,.10) !important; color: var(--h-danger) !important;
        border: 1px solid rgba(201,34,46,.40) !important; box-shadow: none !important;
    }
    .card-tools .btn-outline-danger:hover:not(:disabled) { background: var(--h-danger) !important; color: #fff !important; }
    .card-tools .btn-outline-danger:disabled { opacity: .5; cursor: not-allowed; }
    .dropdown-menu .dropdown-item.disabled { opacity: .45; pointer-events: none; }
    .dropdown-menu .dropdown-item.text-danger:hover { background: rgba(201,34,46,.12) !important; color: var(--h-danger) !important; }
    #toTable .btn-sm.btn-success { background: rgba(42,152,99,.12) !important; color: var(--h-success) !important; border: 1px solid rgba(42,152,99,.25) !important; border-radius: 7px !important; }
    #toTable .btn-group { white-space: nowrap; }
    #toTable .btn-group .btn-sm { margin-right: 0; }
    #toTable td:last-child { white-space: nowrap; }
    #toTable td:last-child > .btn { margin-left: 2px; }
    #toTable input[type=checkbox], .to-select { accent-color: var(--h-primary); width: 15px; height: 15px; cursor: pointer; }
    #toTable thead th:first-child { width: 30px; text-align: center; }
    #toTable tbody td:first-child { text-align: center; }
    .dropdown-menu {
        background: var(--h-card) !important;
        border: 1px solid var(--h-border) !important;
        border-radius: 10px !important;
        box-shadow: var(--h-shadow) !important;
        padding: 6px !important;
        z-index: 1060 !important;
    }
    .dropdown-menu .dropdown-header {
        color: var(--h-muted) !important;
        font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
    }
    .dropdown-menu .dropdown-item {
        color: var(--h-text) !important;
        font-size: .84rem; border-radius: 7px; padding: 7px 12px;
    }
    .dropdown-menu .dropdown-item:hover,
    .dropdown-menu .dropdown-item:focus { background: var(--h-primary-dim) !important; color: var(--h-primary) !important; }
    .scrum-style-modal .btn-success {
        background: var(--h-primary) !important; color: #fff !important; border: none !important;
        border-radius: 8px !important; font-weight: 700 !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important;
    }
    .scrum-style-modal .btn-success:hover { filter: brightness(1.1) !important; color: #fff !important; }
    .scrum-style-modal .btn-outline-primary,
    .scrum-style-modal .btn-outline-danger { border-radius: 8px !important; font-weight: 600 !important; background: transparent !important; }
    .scrum-style-modal .btn-outline-primary { color: var(--h-primary) !important; border: 1px solid var(--h-primary) !important; }
    .scrum-style-modal .btn-outline-primary:hover { background: var(--h-primary-dim) !important; }
    .scrum-style-modal .btn-outline-danger { color: var(--h-danger) !important; border: 1px solid rgba(201,34,46,.4) !important; }
    .scrum-style-modal .btn-outline-danger:hover:not(:disabled) { background: rgba(201,34,46,.12) !important; }
    .scrum-style-modal .custom-control-label { color: var(--h-text) !important; font-size: .85rem !important; font-weight: 500 !important; }
    .scrum-style-modal .custom-control-input:checked ~ .custom-control-label::before {
        background-color: var(--h-primary) !important; border-color: var(--h-primary) !important;
    }
    .scrum-style-modal .custom-control-label::before { background: var(--h-card-alt); border: 1.5px solid var(--h-border); }
    .scrum-style-modal .custom-control-input:focus ~ .custom-control-label::before { box-shadow: 0 0 0 3px var(--h-primary-dim) !important; }
    .bulk-people .form-control { background: var(--h-card) !important; }
    .scrum-style-modal #viewCaContent .ca-detail-row:last-child { border-bottom: none; }
    .ca-meals { display:none; padding:10px 14px; border:1.5px dashed var(--h-border); border-radius:8px; margin-top:6px; }
    .ca-check { margin-right:18px; }
    .no-meal-detail .ca-meals { display:none !important; }
    .ca-template-note { color: var(--h-muted) !important; }
    .bulk-people { position:relative; border:1.5px solid var(--h-border); border-radius:8px; padding:12px; background: var(--h-card-alt); }
    .bulk-row { margin-bottom:8px; }
    /* Fixed grid so name / designation columns never resize when a designation is picked */
    .bulk-row > .d-flex {
        display:grid !important;
        grid-template-columns: 30px minmax(0,1fr) minmax(0,1fr) 34px;
        gap:8px; align-items:center;
    }
    .bulk-row > .d-flex > .flex-fill { padding-right:0 !important; min-width:0; width:100%; }
    .bulk-row .bulk-name { width:100%; }
    .bulk-row .select2-container { width:100% !important; min-width:0; }
    .bulk-row .select2-container--default .select2-selection--single {
        display:flex !important; align-items:center; position:relative; overflow:hidden;
    }
    .bulk-row .select2-container--default .select2-selection--single .select2-selection__rendered {
        flex:1 1 auto; min-width:0; display:block !important;
        white-space:nowrap !important; overflow:hidden !important; text-overflow:ellipsis !important;
        padding-left:12px !important; padding-right:46px !important;
    }
    .bulk-row .select2-container--default .select2-selection--single .select2-selection__clear {
        position:absolute; right:28px; top:0; height:36px; line-height:36px;
        margin:0; padding:0; float:none; color:var(--h-muted);
    }
    .bulk-row .select2-container--default .select2-selection--single .select2-selection__clear:hover { color:var(--h-danger); }
    .bulk-row .bulk-remove { width:34px; height:34px; padding:0; display:flex; align-items:center; justify-content:center; }
    .bulk-people .select2-dropdown { min-width:260px; }
    .bulk-people .select2-results__option { white-space:normal; }
    .bulk-num { width:30px; font-weight:700; color:var(--h-muted); }
    .bulk-people .select2-container { width:100% !important; }
    .ca-detail-row { display:flex; align-items:flex-start; padding:6px 0; border-bottom:1px solid var(--h-border); font-size:.875rem; }
    .ca-detail-row .k { width:160px; color:var(--h-muted); font-weight:600; flex-shrink:0; }
    /* long purposes / unbroken text wrap inside the modal instead of overflowing it */
    .ca-detail-row .v { color:var(--h-text); flex:1 1 auto; min-width:0; overflow-wrap:anywhere; word-break:break-word; white-space:pre-line; }
    #toTable td { overflow-wrap:anywhere; }

    /* ── View modal: read-only "certificate card" (looks different from the Edit form) ── */
    .cav-top { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 16px; border-radius:10px;
               background: var(--h-primary); color:#fff; }
    .cav-tn-label { font-size:.68rem; letter-spacing:.8px; text-transform:uppercase; opacity:.85; font-weight:700; }
    .cav-tn { font-size:1.35rem; font-weight:800; letter-spacing:.5px; line-height:1.15; }
    .cav-tpl { font-size:.75rem; font-weight:700; background:rgba(255,255,255,.2); padding:5px 10px; border-radius:20px; white-space:nowrap; }
    .cav-person { display:flex; align-items:center; gap:14px; padding:16px 4px 6px; }
    .cav-avatar { width:54px; height:54px; border-radius:50%; background:var(--h-primary-dim); color:var(--h-primary);
                  display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
    .cav-person-text { min-width:0; }
    .cav-name { font-size:1.15rem; font-weight:800; color:var(--h-text); overflow-wrap:anywhere; line-height:1.2; }
    .cav-desig { font-size:.9rem; color:var(--h-text); }
    .cav-office { font-size:.8rem; color:var(--h-muted); overflow-wrap:anywhere; margin-top:2px; }
    .cav-dates { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:12px 0; padding:12px 16px;
                 border:1.5px solid var(--h-border); border-radius:10px; background:var(--h-card-alt); }
    .cav-date { display:flex; flex-direction:column; }
    .cav-date span { font-size:.68rem; letter-spacing:.6px; text-transform:uppercase; color:var(--h-muted); font-weight:700; }
    .cav-date strong { font-size:.98rem; color:var(--h-text); }
    .cav-date:last-child { text-align:right; }
    .cav-arrow { display:flex; flex-direction:column; align-items:center; color:var(--h-primary); font-size:.95rem; }
    .cav-arrow small { font-size:.68rem; font-weight:700; color:var(--h-muted); }
    .cav-section { border:1.5px solid var(--h-border); border-radius:10px; padding:10px 14px; margin-bottom:12px; min-width:0; }
    .cav-title { font-size:.7rem; letter-spacing:.7px; text-transform:uppercase; font-weight:800; color:var(--h-primary); margin-bottom:6px; }
    .cav-title i { margin-right:5px; }
    .cav-purpose { color:var(--h-text); font-size:.92rem; overflow-wrap:anywhere; word-break:break-word; white-space:pre-line; }
    .cav-grid { display:grid; grid-template-columns: 1fr 2fr; gap:12px; }
    .cav-grid .cav-section { margin-bottom:0; }
    .cav-status { font-size:.78rem !important; padding:6px 12px !important; border-radius:20px !important; font-weight:700; }
    .cav-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .cav-chip { font-size:.74rem; font-weight:700; padding:4px 10px; border-radius:20px; border:1px solid var(--h-border); }
    .cav-chip.on  { background:var(--h-primary-dim); color:var(--h-primary); border-color:var(--h-primary); }
    .cav-chip.off { color:var(--h-muted); opacity:.55; text-decoration:line-through; }
    .cav-sign { display:flex; justify-content:space-between; align-items:flex-end; gap:12px; margin-top:12px; padding-top:10px; border-top:1px dashed var(--h-border); }
    .cav-sign span { display:block; font-size:.68rem; letter-spacing:.6px; text-transform:uppercase; color:var(--h-muted); font-weight:700; }
    .cav-sign strong { color:var(--h-text); }
    .cav-sign small { display:block; color:var(--h-muted); }
    .cav-created { font-size:.74rem; color:var(--h-muted); text-align:right; white-space:nowrap; }
    @media (max-width:576px) { .cav-grid { grid-template-columns:1fr; } .cav-top { flex-direction:column; align-items:flex-start; } }


    /* ───────── Travel Order additions ───────── */
    #toTable .badge { font-size: .7rem; font-weight: 700; padding: 5px 9px; border-radius: 6px !important; letter-spacing: .2px; white-space: nowrap !important; overflow-wrap: normal !important; word-break: normal !important; }
    .badge-fmt-standard  { background: rgba(37,99,235,.12) !important; color: #1d4ed8 !important; border: 1px solid rgba(37,99,235,.30); }
    .badge-fmt-with_ca   { background: rgba(230,119,0,.14) !important; color: #b45f00 !important; border: 1px solid rgba(230,119,0,.35); }
    .badge-fmt-itinerary { background: rgba(124,58,237,.12) !important; color: #6d28d9 !important; border: 1px solid rgba(124,58,237,.30); }
    body.dark-mode .badge-fmt-standard  { color: #7cb0ff !important; background: rgba(96,150,255,.15) !important; border-color: rgba(96,150,255,.35); }
    body.dark-mode .badge-fmt-with_ca   { color: #ffc266 !important; background: rgba(255,194,102,.14) !important; border-color: rgba(255,194,102,.35); }
    body.dark-mode .badge-fmt-itinerary { color: #c4a5ff !important; background: rgba(167,139,250,.15) !important; border-color: rgba(167,139,250,.35); }
    #toTable .btn-group { white-space: nowrap; }
    #toTable td:last-child { white-space: nowrap; }
    #toTable td:last-child > .btn { margin-left: 2px; }
    #toTable input[type=checkbox], .to-select { accent-color: var(--h-primary); width: 15px; height: 15px; cursor: pointer; }
    #toTable thead th:first-child { width: 30px; text-align: center; }
    #toTable tbody td:first-child { text-align: center; }
    #toTable td { overflow-wrap: anywhere; }
    #toTable .btn-sm.btn-success { background: rgba(42,152,99,.12) !important; color: var(--h-success) !important; border: 1px solid rgba(42,152,99,.25) !important; border-radius: 7px !important; }
    #toTable .btn-sm.btn-primary { background: rgba(37,99,235,.10) !important; color: #1d4ed8 !important; border: 1px solid rgba(37,99,235,.25) !important; border-radius: 7px !important; box-shadow: none !important; }
    #toTable .btn-sm.btn-info    { background: rgba(42,152,99,.12) !important; color: var(--h-blue) !important; border: 1px solid rgba(42,152,99,.25) !important; border-radius: 7px !important; }
    #toTable .btn-sm.btn-warning { background: rgba(230,119,0,.12) !important; color: var(--h-warning) !important; border: 1px solid rgba(230,119,0,.25) !important; border-radius: 7px !important; }
    #toTable .btn-sm.btn-danger  { background: rgba(201,34,46,.12) !important; color: var(--h-danger) !important; border: 1px solid rgba(201,34,46,.25) !important; border-radius: 7px !important; }
    #toTable .ln { display: block; }
    #toTable .more { font-size: .72rem; color: var(--h-muted); }

    /* form: repeaters */
    .to-sec { font-size: .72rem; letter-spacing: .6px; text-transform: uppercase; font-weight: 800; color: var(--h-primary); margin: 4px 0 8px; display: flex; align-items: center; justify-content: space-between; }
    .to-sec small { text-transform: none; letter-spacing: 0; font-weight: 500; color: var(--h-muted); }
    .to-box { border: 1.5px solid var(--h-border); border-radius: 10px; padding: 12px; background: var(--h-card-alt); margin-bottom: 16px; }
    .to-ppl-row { display: grid; grid-template-columns: 30px minmax(0,1fr) minmax(0,1fr) 34px; gap: 8px; align-items: center; margin-bottom: 8px; }
    .to-ppl-row .num { font-weight: 700; color: var(--h-muted); }
    .to-ppl-row > div { min-width: 0; }
    .scrum-style-modal .to-box .form-control { background: var(--h-card) !important; }
    .to-ppl-row .select2-container { width: 100% !important; min-width: 0; }
    .to-ppl-row .select2-container--default .select2-selection--single { display: flex !important; align-items: center; position: relative; overflow: hidden; }
    .to-ppl-row .select2-container--default .select2-selection--single .select2-selection__rendered { flex: 1 1 auto; min-width: 0; display: block !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; padding-right: 46px !important; }
    .to-ppl-row .select2-container--default .select2-selection--single .select2-selection__clear { position: absolute; right: 28px; top: 0; height: 36px; line-height: 36px; margin: 0; padding: 0; float: none; color: var(--h-muted); }
    .to-rm { width: 34px; height: 34px; padding: 0; display: flex; align-items: center; justify-content: center; }
    .to-item { border: 1.5px solid var(--h-border); border-radius: 10px; padding: 12px 14px 4px; background: var(--h-card); margin-bottom: 10px; }
    .to-item-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-weight: 800; font-size: .8rem; color: var(--h-text); }
    .to-item-head .badge-n { background: var(--h-primary); color: #fff; border-radius: 20px; padding: 2px 10px; font-size: .72rem; margin-right: 6px; }
    .to-rev { border-top: 1px dashed var(--h-border); margin: 4px 0 8px; padding-top: 10px; }
    .to-rev-title { font-size: .68rem; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; color: var(--h-warning); margin-bottom: 6px; }
    .to-fmt-note { color: var(--h-muted) !important; }
    .to-fmt-card { border: 1.5px solid var(--h-border); border-radius: 10px; padding: 10px 14px; background: var(--h-primary-dim); font-size: .82rem; color: var(--h-text); margin-top: 6px; }
    /* show / hide parts of the form by chosen format */
    #toForm .only-ca, #toForm .only-itin, #toForm .not-std { display: none; }
    #toForm.fmt-with_ca .only-ca { display: block; }
    #toForm.fmt-itinerary .only-itin { display: block; }
    #toForm.fmt-with_ca .not-std, #toForm.fmt-itinerary .not-std { display: block; }
    #toForm.fmt-with_ca .only-ca.row, #toForm.fmt-itinerary .only-itin.row { display: flex; }

    /* view modal */
    .tov-people { display: flex; flex-direction: column; gap: 4px; padding: 14px 4px 6px; }
    .tov-p { display: flex; align-items: center; gap: 12px; }
    .tov-p .cav-avatar { width: 38px; height: 38px; font-size: 1rem; }
    .tov-table { width: 100%; border-collapse: collapse; font-size: .84rem; margin-bottom: 12px; }
    .tov-table th { background: var(--h-card-alt); color: var(--h-muted); font-size: .66rem; letter-spacing: .6px; text-transform: uppercase; padding: 7px 10px; text-align: left; border-bottom: 1px solid var(--h-border); }
    .tov-table td { padding: 8px 10px; border-bottom: 1px solid var(--h-border); color: var(--h-text); vertical-align: top; overflow-wrap: anywhere; white-space: pre-line; }
    .tov-rev td { background: rgba(230,119,0,.07); }
    .tov-rev .tag { font-size: .64rem; font-weight: 800; color: var(--h-warning); text-transform: uppercase; display: block; }
    .tov-wrap { overflow-x: auto; }
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
            <div class="pg-hero-title"><i class="fas fa-plane-departure"></i>Travel Order</div>
            <div class="pg-hero-divider"></div>
            <p class="pg-hero-sub">Prepare, print and download Travel Orders &mdash; Standard, with Certificate of Appearance, or Itinerary of Travel</p>
          </div>
        </div>
      </div>

      <div class="content">
        <div class="container-fluid">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Manage Travel Orders</h3>
              <div class="card-tools">
                <button type="button" class="btn btn-outline-primary btn-sm mr-1" id="bulkPreviewBtn" disabled title="Print preview of the selected travel orders">
                  <i class="fas fa-print"></i> Print Selected
                </button>
                <div class="btn-group mr-1">
                  <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="bulkDownloadBtn" data-toggle="dropdown" data-boundary="window" disabled>
                    <i class="fas fa-file-word"></i> Download Selected (<span id="selCount">0</span>)
                  </button>
                  <div class="dropdown-menu dropdown-menu-right">
                    <h6 class="dropdown-header">Download all selected in 1 Word file</h6>
                    <?php foreach ($TO_TEMPLATES as $key => $t): ?>
                      <a class="dropdown-item bulk-dl-item" data-template="<?= $key ?>" href="#"><?= htmlspecialchars($t['label']) ?></a>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="btn-group mr-1">
                  <button type="button" class="btn btn-outline-danger btn-sm dropdown-toggle" id="bulkDeleteBtn" data-toggle="dropdown" data-boundary="window"<?= $orders ? '' : ' disabled' ?>>
                    <i class="fas fa-trash"></i> Delete
                  </button>
                  <div class="dropdown-menu dropdown-menu-right">
                    <h6 class="dropdown-header">Delete travel orders</h6>
                    <a class="dropdown-item disabled" id="delSelItem" href="#"><i class="fas fa-check-square mr-1"></i>Delete Selected (<span id="delSelCount">0</span>)</a>
                    <a class="dropdown-item text-danger" id="delAllItem" href="#" data-total="<?= count($orders) ?>"><i class="fas fa-exclamation-triangle mr-1"></i>Delete All (<?= count($orders) ?>)</a>
                  </div>
                </div>
                <button type="button" class="btn btn-outline-success btn-sm mr-1" data-toggle="modal" data-target="#reportToModal">
                  <i class="fas fa-print"></i> Report
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="newToBtn">
                  <i class="fas fa-plus"></i> New Travel Order
                </button>
              </div>
            </div>
            <div class="card-body">
              <table id="toTable" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th class="no-sort" style="width:30px;"><input type="checkbox" id="toSelectAll" title="Select all on this page"></th>
                    <th>Travel Order No.</th>
                    <th>Order Date</th>
                    <th>Personnel</th>
                    <th>Destination</th>
                    <th>Travel Dates</th>
                    <th>Format</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orders as $o):
                    $names = array_map(function ($p) { return $p['name']; }, $o['people']);
                    $locs  = array_values(array_unique(array_map(function ($i) { return $i['location']; }, $o['items'])));
                    [$ts, $te] = to_span($o['items']);
                    $tk = $o['template_key'];
                  ?>
                  <tr>
                    <td><input type="checkbox" class="to-select" value="<?= (int)$o['to_id'] ?>"></td>
                    <td class="text-nowrap" data-order="<?= (int)$o['series_year'] * 1000000 + (int)$o['to_seq'] ?>"><strong><?= to_h(to_number($o)) ?></strong><br><small class="text-muted">s. <?= (int)$o['series_year'] ?></small></td>
                    <td class="text-nowrap" data-order="<?= to_h($o['to_date']) ?>"><?= to_h(to_fmt_date($o['to_date'])) ?></td>
                    <td><?php foreach (array_slice($names, 0, 3) as $n): ?><span class="ln"><?= to_h($n) ?></span><?php endforeach; ?>
                        <?php if (count($names) > 3): ?><span class="more">+ <?= count($names) - 3 ?> more</span><?php endif; ?></td>
                    <td><?php foreach (array_slice($locs, 0, 2) as $l): ?><span class="ln"><?= to_h($l) ?></span><?php endforeach; ?>
                        <?php if (count($locs) > 2): ?><span class="more">+ <?= count($locs) - 2 ?> more</span><?php endif; ?></td>
                    <td class="text-nowrap" data-order="<?= to_h($ts) ?>"><?= to_h(to_fmt_range($ts, $te)) ?></td>
                    <td><span class="badge badge-fmt-<?= to_h($tk) ?>"><?= to_h($TO_TEMPLATES[$tk]['short']) ?></span></td>
                    <td>
                      <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-toggle="dropdown" data-boundary="window" title="Download Word file">
                          <i class="fas fa-file-word"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                          <h6 class="dropdown-header">Download using format</h6>
                          <?php foreach ($TO_TEMPLATES as $key => $t): ?>
                            <a class="dropdown-item" href="travel_order.php?action=download&id=<?= (int)$o['to_id'] ?>&template=<?= $key ?>">
                              <?= htmlspecialchars($t['label']) ?>
                              <?php if ($tk === $key): ?><small class="text-muted">(saved)</small><?php endif; ?>
                            </a>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <button class="btn btn-sm btn-primary preview-btn" data-id="<?= (int)$o['to_id'] ?>" title="Print preview"><i class="fas fa-print"></i></button>
                      <button class="btn btn-sm btn-info view-btn" data-id="<?= (int)$o['to_id'] ?>" title="View"><i class="fas fa-eye"></i></button>
                      <button class="btn btn-sm btn-warning edit-btn" data-id="<?= (int)$o['to_id'] ?>" title="Edit"><i class="fas fa-edit"></i></button>
                      <button class="btn btn-sm btn-danger delete-btn" data-id="<?= (int)$o['to_id'] ?>" title="Delete"><i class="fas fa-trash"></i></button>
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

    <?php include '../includes/mainfooter.php'; ?>
  </div>

  <!-- Add / Edit Modal -->
  <div class="modal fade scrum-style-modal" id="toModal" tabindex="-1" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <form id="toForm" autocomplete="off">
          <input type="hidden" name="to_id" id="f_to_id">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-plane-departure"></i><span id="toModalTitle">New Travel Order</span></h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">

            <div class="form-group">
              <label>Format <span class="text-danger">*</span></label>
              <select name="template_key" id="f_template_key" class="form-control" required>
                <?php foreach ($TO_TEMPLATES as $key => $t): ?>
                  <option value="<?= $key ?>"><?= htmlspecialchars($t['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="to-fmt-card" id="fmtDesc"></div>
              <small class="form-text text-muted">You can still print or download this order in any other format later.</small>
            </div>

            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label>Office Code <span class="text-danger">*</span></label>
                  <input type="text" name="to_code" id="f_to_code" class="form-control text-uppercase" list="toCodes" maxlength="20" value="<?= TO_DEFAULT_CODE ?>" required>
                  <datalist id="toCodes"><?php foreach ($known_codes as $c): ?><option value="<?= to_h($c) ?>"><?php endforeach; ?></datalist>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Number <span class="text-danger">*</span></label>
                  <input type="number" name="to_seq" id="f_to_seq" class="form-control" min="1" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Series Year <span class="text-danger">*</span></label>
                  <input type="number" name="series_year" id="f_series_year" class="form-control" min="2000" max="2100" value="<?= $cur_year ?>" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Date of Travel Order <span class="text-danger">*</span></label>
                  <input type="date" name="to_date" id="f_to_date" class="form-control" required>
                </div>
              </div>
            </div>
            <small class="form-text text-muted mb-3" style="margin-top:-6px;">Prints as <b id="toNoPreview"></b></small>

            <div class="to-sec"><span><i class="fas fa-users mr-1"></i>Personnel to travel</span><small>pick an employee &mdash; the position fills in by itself. Not on the list? Just type the name.</small></div>
            <div class="to-box">
              <div id="ppl_rows"></div>
              <button type="button" class="btn btn-sm btn-outline-primary" id="addPersonBtn"><i class="fas fa-user-plus"></i> Add person</button>
            </div>

            <div class="to-sec"><span><i class="fas fa-route mr-1"></i>Destinations</span><small class="only-ca">The CA format fits about 3 destinations on one page.</small><small class="only-itin">The form has room for 5 dates; more continue on a 2nd page.</small></div>
            <div id="itin_rows"></div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="addItemBtn"><i class="fas fa-plus"></i> Add destination</button>

            <div class="row only-ca">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Expected Output</label>
                  <textarea name="expected_output" id="f_expected_output" class="form-control" rows="2"></textarea>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Remarks</label>
                  <textarea name="remarks" id="f_remarks" class="form-control" rows="2"></textarea>
                </div>
              </div>
            </div>

            <div class="to-sec"><span><i class="fas fa-signature mr-1"></i>Signatories</span></div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Approved by</label>
                  <input type="text" name="approver_name" id="f_approver_name" class="form-control">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Position</label>
                  <input type="text" name="approver_title" id="f_approver_title" class="form-control">
                </div>
              </div>
            </div>
            <div class="row only-itin">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Recommended by</label>
                  <input type="text" name="recommender_name" id="f_recommender_name" class="form-control" placeholder="Name of Section Head">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Section / Position</label>
                  <input type="text" name="recommender_title" id="f_recommender_title" class="form-control" placeholder="e.g. Acting Administrative Section Head">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Prepared by <small class="text-muted" style="text-transform:none;">(blank = first person above)</small></label>
                  <input type="text" name="prepared_name" id="f_prepared_name" class="form-control">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Position</label>
                  <input type="text" name="prepared_title" id="f_prepared_title" class="form-control">
                </div>
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" id="toSaveBtn">Save Travel Order</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Report Modal -->
  <?php
    $report_years = [$cur_year];
    foreach ($orders as $o) { [$ts] = to_span($o['items']); if ($ts) $report_years[] = (int)substr($ts, 0, 4); }
    $report_years = array_unique($report_years); rsort($report_years);
  ?>
  <div class="modal fade scrum-style-modal" id="reportToModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="get" action="travel_order.php" target="_blank">
          <input type="hidden" name="action" value="report">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-print"></i>Travel Order Report</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Year (by travel date)</label>
                  <select name="year" class="form-control">
                    <?php foreach ($report_years as $y): ?><option value="<?= $y ?>"><?= $y ?></option><?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Month</label>
                  <select name="month" class="form-control">
                    <option value="0">Whole year</option>
                    <?php for ($mo = 1; $mo <= 12; $mo++): ?><option value="<?= $mo ?>"><?= date('F', mktime(0, 0, 0, $mo, 1)) ?></option><?php endfor; ?>
                  </select>
                </div>
              </div>
            </div>
            <small class="form-text text-muted">Opens a print-ready page with Travel Order No., Date, Name / Position, Destination, Travel Dates, Purpose and Format.</small>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-print"></i> Open Report</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Modal -->
  <div class="modal fade scrum-style-modal" id="viewToModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-file-alt"></i>Travel Order</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body"><div id="viewToContent"></div></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="viewToPreviewBtn"><i class="fas fa-print"></i> Print Preview</button>
          <div class="btn-group dropup">
            <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown"><i class="fas fa-file-word"></i> Download .docx</button>
            <div class="dropdown-menu" id="viewToDownloadMenu">
              <?php foreach ($TO_TEMPLATES as $key => $t): ?>
                <a class="dropdown-item" data-template="<?= $key ?>" href="#"><?= htmlspecialchars($t['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Print Preview Modal -->
  <div class="modal fade scrum-style-modal" id="previewToModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-print"></i>Print Preview</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body p-0" style="position:relative; background:#525659;">
          <div id="previewToLoading" class="text-center text-white" style="position:absolute; inset:0; padding-top:30vh;">
            <i class="fas fa-spinner fa-spin fa-2x"></i><div class="mt-2">Preparing travel order&hellip;</div>
          </div>
          <iframe id="previewToFrame" title="Travel order preview" style="width:100%; height:72vh; border:0; position:relative;"></iframe>
        </div>
        <div class="modal-footer">
          <div class="form-inline align-items-center mr-2">
            <label class="mr-1 mb-0 small" for="toTplPreview"><i class="fas fa-file-alt"></i> Format:</label>
            <select id="toTplPreview" class="form-control form-control-sm">
              <option value="">Saved format</option>
              <?php foreach ($TO_TEMPLATES as $key => $t): ?>
                <option value="<?= $key ?>"><?= htmlspecialchars($t['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="previewToHint" class="small text-muted mr-auto" style="max-width:420px; line-height:1.3;"></div>
          <button type="button" class="btn btn-primary" id="previewToPrint"><i class="fas fa-print"></i> Print</button>
          <a class="btn btn-success" id="previewToDownload" href="#"><i class="fas fa-file-word"></i> Download .docx</a>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
  $(document).ready(function() {
    const TO_T         = <?= json_encode(array_map(function ($t) { return ['label' => $t['label'], 'short' => $t['short'], 'desc' => $t['desc'], 'approver' => $t['approver'], 'paper' => $t['page']['name']]; }, $TO_TEMPLATES), JSON_UNESCAPED_UNICODE) ?>;
    const TO_DEFAULT   = <?= json_encode(TO_DEFAULT_TEMPLATE) ?>;
    const TO_CODE      = <?= json_encode(TO_DEFAULT_CODE) ?>;
    const TO_POSITIONS = <?= json_encode($positions, JSON_UNESCAPED_UNICODE) ?>;
    const TO_EMP       = <?= json_encode($employees, JSON_UNESCAPED_UNICODE) ?>;
    const TO_YEAR      = <?= (int)$cur_year ?>;
    const TO_NEXT      = <?= (int)$next_seq ?>;
    const TO_TODAY     = <?= json_encode(date('Y-m-d')) ?>;

    const toTable = $('#toTable').DataTable({
      responsive: true,
      order: [[5, 'desc']],
      columnDefs: [{ targets: [0, 7], orderable: false, searchable: false }]
    });

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function fmtDate(d) {
      if (!d) return '';
      return new Date(d + 'T00:00:00').toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    }
    function fmtRange(a, b) {
      if (!a) return '';
      if (!b || b === a) return fmtDate(a);
      let x = new Date(a + 'T00:00:00'), y = new Date(b + 'T00:00:00');
      let m = function(d) { return d.toLocaleDateString('en-US', { month:'long' }); };
      if (x.getFullYear() === y.getFullYear() && x.getMonth() === y.getMonth()) return m(x) + ' ' + x.getDate() + '\u2013' + y.getDate() + ', ' + y.getFullYear();
      if (x.getFullYear() === y.getFullYear()) return m(x) + ' ' + x.getDate() + ' \u2013 ' + m(y) + ' ' + y.getDate() + ', ' + y.getFullYear();
      return fmtDate(a) + ' \u2013 ' + fmtDate(b);
    }

    // ── Print preview (HTML on the letterhead, ready to print) ──
    let previewIds = [];
    function loadPreview() {
      if (!previewIds.length) return;
      let tpl = $('#toTplPreview').val(), tplQs = tpl ? '&template=' + encodeURIComponent(tpl) : '', idsQs = previewIds.join(',');
      $('#previewToHint').html('<i class="fas fa-info-circle"></i> In the print dialog choose <b>Margins: None</b> and <b>Scale: 100%</b>. Paper: Standard = A4, With CA = 8.5 &times; 13 in, Itinerary = 13 &times; 8.5 in landscape.');
      $('#previewToLoading').show();
      $('#previewToFrame').attr('src', 'travel_order.php?action=preview&ids=' + idsQs + tplQs);
      $('#previewToDownload').attr('href', previewIds.length === 1
        ? 'travel_order.php?action=download&id=' + idsQs + tplQs
        : 'travel_order.php?action=download_bulk&ids=' + idsQs + (tpl ? tplQs : '&template=standard'));
    }
    function openPreview(ids) {
      previewIds = ids;
      $('#toTplPreview').val('');
      $('#previewToFrame').attr('src', 'about:blank');
      $('#previewToModal').modal('show');
      loadPreview();
    }
    $('#previewToFrame').on('load', function() { if (this.getAttribute('src') !== 'about:blank') $('#previewToLoading').hide(); });
    $('#previewToModal').on('hidden.bs.modal', function() { $('#previewToFrame').attr('src', 'about:blank'); });
    $('#toTplPreview').on('change', loadPreview);
    $('#previewToPrint').on('click', function() {
      let f = document.getElementById('previewToFrame');
      try { f.contentWindow.focus(); f.contentWindow.print(); }
      catch (e) { window.open(f.src, '_blank'); }
    });
    $(document).on('click', '.preview-btn', function() { openPreview([$(this).data('id')]); });
    $('#bulkPreviewBtn').on('click', function() {
      let ids = Object.keys(selectedIds).map(Number).sort(function(a, b) { return a - b; });
      if (!ids.length) return;
      if (ids.length > <?= TO_MAX_BATCH ?>) { Swal.fire('Too many selected', 'You can print up to <?= TO_MAX_BATCH ?> travel orders at a time.', 'warning'); return; }
      openPreview(ids);
    });
    $('#viewToPreviewBtn').on('click', function() {
      let id = $('#viewToDownloadMenu a').first().attr('href').match(/[?&]id=(\d+)/);
      if (!id) return;
      $('#viewToModal').one('hidden.bs.modal', function() { openPreview([parseInt(id[1], 10)]); }).modal('hide');
    });

    // ── Select travel orders & download them again as ONE combined Word file ──
    let selectedIds = {};
    function refreshSelection() {
      let ids = Object.keys(selectedIds);
      $('#selCount').text(ids.length);
      $('#delSelCount').text(ids.length);
      $('#delSelItem').toggleClass('disabled', ids.length === 0);
      $('#bulkPreviewBtn').prop('disabled', ids.length === 0);
      $('#bulkDownloadBtn').prop('disabled', ids.length === 0)
        .toggleClass('btn-outline-success', ids.length === 0)
        .toggleClass('btn-success', ids.length > 0);
      let $boxes = $('#toTable tbody .to-select');
      $('#toSelectAll').prop('checked', $boxes.length > 0 && $boxes.filter(':checked').length === $boxes.length);
    }
    function syncVisibleChecks() {
      $('#toTable tbody .to-select').each(function() { this.checked = !!selectedIds[this.value]; });
      refreshSelection();
    }
    $(document).on('change', '.to-select', function() {
      if (this.checked) selectedIds[this.value] = true; else delete selectedIds[this.value];
      refreshSelection();
    });
    $('#toSelectAll').on('change', function() {
      let on = this.checked;
      $('#toTable tbody .to-select').each(function() {
        this.checked = on;
        if (on) selectedIds[this.value] = true; else delete selectedIds[this.value];
      });
      refreshSelection();
    });
    toTable.on('draw', syncVisibleChecks);

    $(document).on('click', '.bulk-dl-item', function(e) {
      e.preventDefault();
      let ids = Object.keys(selectedIds).map(Number).sort(function(a, b) { return a - b; });
      if (!ids.length) return;
      if (ids.length > <?= TO_MAX_BATCH ?>) { Swal.fire('Too many selected', 'You can download up to <?= TO_MAX_BATCH ?> travel orders at a time.', 'warning'); return; }
      window.location.href = 'travel_order.php?action=download_bulk&ids=' + ids.join(',') + '&template=' + encodeURIComponent($(this).data('template'));
    });

    // ── Delete several selected / all ──
    $('#delSelItem').on('click', function(e) {
      e.preventDefault();
      let ids = Object.keys(selectedIds).map(Number);
      if (!ids.length) { Swal.fire('Nothing selected', 'Tick the travel orders you want to delete first.', 'info'); return; }
      Swal.fire({
        title: 'Delete ' + ids.length + ' travel order' + (ids.length === 1 ? '' : 's') + '?',
        text: "You won't be able to revert this!",
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6', confirmButtonText: 'Yes, delete them!'
      }).then(function(r) {
        if (!r.isConfirmed) return;
        $.post('travel_order.php', { action: 'bulk_delete', ids: ids }, function(res) {
          if (res.success) Swal.fire('Deleted!', res.message, 'success').then(() => location.reload());
          else Swal.fire('Error!', res.message, 'error');
        }, 'json').fail(function() { Swal.fire('Error!', 'An error occurred while processing your request.', 'error'); });
      });
    });
    $('#delAllItem').on('click', function(e) {
      e.preventDefault();
      let total = $(this).data('total');
      if (!total) { Swal.fire('Nothing to delete', 'There are no travel orders.', 'info'); return; }
      Swal.fire({
        title: 'Delete ALL ' + total + ' travel orders?',
        html: 'This permanently removes every travel order and cannot be undone.<br>Type <b>DELETE</b> to confirm.',
        icon: 'warning', input: 'text', inputPlaceholder: 'DELETE', showCancelButton: true,
        confirmButtonColor: '#d33', cancelButtonColor: '#3085d6', confirmButtonText: 'Delete all',
        preConfirm: function(v) {
          if ($.trim(v) !== 'DELETE') { Swal.showValidationMessage('Please type DELETE exactly.'); return false; }
          return true;
        }
      }).then(function(r) {
        if (!r.isConfirmed) return;
        $.post('travel_order.php', { action: 'delete_all', confirm: 'DELETE' }, function(res) {
          if (res.success) Swal.fire('Deleted!', res.message, 'success').then(() => location.reload());
          else Swal.fire('Error!', res.message, 'error');
        }, 'json').fail(function() { Swal.fire('Error!', 'An error occurred while processing your request.', 'error'); });
      });
    });

    // ───────────────────────── the form ─────────────────────────
    const $form = $('#toForm');
    const ITEM_FIELDS = ['date_from','date_to','location','purpose','travel_time','duration','rev_location','rev_purpose','rev_travel_time','rev_duration'];

    function toNoText() {
      let code = ($('#f_to_code').val() || TO_CODE).toUpperCase().replace(/[^A-Z0-9]/g, '') || TO_CODE;
      $('#toNoPreview').text(code + '-TO-' + ($('#f_to_seq').val() || '___') + ' s. ' + ($('#f_series_year').val() || TO_YEAR));
    }
    $('#f_to_code, #f_to_seq, #f_series_year').on('input change', toNoText);

    // suggest the next free number when the code / year changes on a NEW order
    function suggestNumber() {
      if ($('#f_to_id').val()) return;
      $.post('travel_order.php', { action: 'next_number', to_code: $('#f_to_code').val(), series_year: $('#f_series_year').val() }, function(res) {
        if (res.success) { $('#f_to_seq').val(res.seq); toNoText(); }
      }, 'json');
    }
    $('#f_to_code, #f_series_year').on('change', suggestNumber);

    // format chooser: show/hide the parts that format uses, swap the default signing officer
    function applyTemplate(swapSignatory) {
      let key = $('#f_template_key').val(), t = TO_T[key]; if (!t) return;
      $form.removeClass('fmt-standard fmt-with_ca fmt-itinerary').addClass('fmt-' + key);
      $('#fmtDesc').html('<b>' + esc(t.label) + '</b> &mdash; ' + esc(t.desc) + ' <span class="text-muted">(' + esc(t.paper) + ')</span>');
      if (swapSignatory) {
        let $n = $('#f_approver_name'), $t = $('#f_approver_title');
        let defaults = $.map(TO_T, function(x) { return x.approver[0]; });
        if ($.trim($n.val()) === '' || defaults.indexOf($.trim($n.val())) !== -1) { $n.val(t.approver[0]); $t.val(t.approver[1]); }
      }
    }
    $('#f_template_key').on('change', function() { applyTemplate(true); });

    // — personnel rows: pick an employee (searchable) or type a name; position follows the employee —
    function renumberPeople() {
      let $rows = $('#ppl_rows .to-ppl-row');
      $rows.each(function(i) { $(this).find('.num').text((i + 1) + '.'); });
      $rows.find('.to-rm').prop('disabled', $rows.length <= 1);
    }
    function ensureOption($sel, val) {
      if (val && !$sel.find('option').filter(function() { return this.value === val; }).length) $sel.append($('<option>').val(val).text(val));
    }
    function setPosition($sel, pos) {                 // employee has no position on file -> leave it empty for the user
      ensureOption($sel, pos);
      $sel.val(pos || null).trigger('change');
    }
    function empMatcher(params, data) {               // search by name, position or ID number
      let term = $.trim(params.term).toLowerCase();
      if (term === '' || !data.element) return data;
      let hay = (data.text + ' ' + ($(data.element).attr('data-pos') || '') + ' ' + ($(data.element).attr('data-idno') || '')).toLowerCase();
      return hay.indexOf(term) > -1 ? data : null;
    }
    function empResult(d) {
      if (!d.id) return d.text;
      if (d.newTag) return $('<span>').text('Use "' + d.text + '" (not in the employee list)');
      let pos = d.element ? $(d.element).attr('data-pos') : '';
      let $r = $('<div>').append($('<div>').css('font-weight', 600).text(d.text));
      if (pos) $r.append($('<div>').css({ fontSize: '.75rem', opacity: .7 }).text(pos));
      return $r;
    }
    function addPerson(p) {
      p = p || {};
      let $row = $('<div class="to-ppl-row">' +
        '<div class="num"></div>' +
        '<div><select name="p_name[]" class="form-control p-name"><option value=""></option></select><input type="hidden" name="p_emp_id[]" class="p-empid"></div>' +
        '<select name="p_position[]" class="form-control p-pos"><option value=""></option></select>' +
        '<button type="button" class="btn btn-sm btn-outline-danger to-rm" title="Remove"><i class="fas fa-times"></i></button></div>');
      let $name = $row.find('.p-name'), $pos = $row.find('.p-pos');
      TO_EMP.forEach(function(e) {
        $name.append($('<option>').val(e.name).text(e.name).attr({ 'data-emp': e.id, 'data-pos': e.position, 'data-idno': e.idno }));
      });
      TO_POSITIONS.forEach(function(x) { $pos.append($('<option>').val(x).text(x)); });
      ensureOption($name, p.name); ensureOption($pos, p.position);
      $('#ppl_rows').append($row);
      $name.select2({
        placeholder: '-- Select employee or type a name --', allowClear: true, tags: true, width: '100%', dropdownParent: $('#toModal'),
        matcher: empMatcher, templateResult: empResult,
        insertTag: function(data, tag) { data.push(tag); },     // "use the typed name" goes LAST, so Enter picks the first matching employee
        createTag: function(params) { let t = $.trim(params.term).toUpperCase(); return t === '' ? null : { id: t, text: t, newTag: true }; }
      });
      $pos.select2({
        placeholder: '-- Position --', allowClear: true, tags: true, width: '100%', dropdownParent: $('#toModal'),
        createTag: function(params) { let t = $.trim(params.term); return t === '' ? null : { id: t, text: t, newTag: true }; }
      });
      $name.val(p.name || null).trigger('change');
      $pos.val(p.position || null).trigger('change');
      $row.find('.p-empid').val(p.emp_id || '');
      renumberPeople();
      return $row;
    }
    // the same employee cannot be added twice: cancel that choice (the list stays open for another pick)
    $(document).on('select2:selecting', '#ppl_rows .p-name', function(e) {
      let emp = $(e.params.args.data.element).attr('data-emp') || '';
      if (!emp) return;
      let $id = $(this).closest('.to-ppl-row').find('.p-empid');
      let dup = $('#ppl_rows .p-empid').not($id).filter(function() { return this.value === emp; }).length;
      if (dup) {
        e.preventDefault();
        Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: e.params.args.data.text + ' is already on this travel order.', showConfirmButton: false, timer: 2500 });
      }
    });
    // an employee was chosen -> remember who, and fill in the position
    $(document).on('select2:select', '#ppl_rows .p-name', function(e) {
      let $row = $(this).closest('.to-ppl-row'), $opt = $(e.params.data.element), emp = $opt.attr('data-emp') || '';
      $row.find('.p-empid').val(emp);                 // typed name (not an employee record) -> no id, position stays as typed
      if (emp) setPosition($row.find('.p-pos'), $opt.attr('data-pos') || '');
    });
    $(document).on('select2:clear', '#ppl_rows .p-name', function() { $(this).closest('.to-ppl-row').find('.p-empid').val(''); });
    $('#addPersonBtn').on('click', function() { addPerson().find('.p-name').select2('open'); });
    $(document).on('click', '#ppl_rows .to-rm', function() {
      let $row = $(this).closest('.to-ppl-row');
      $row.find('select').select2('destroy'); $row.remove(); renumberPeople();
    });

    // — destination rows —
    function renumberItems() {
      let $items = $('#itin_rows .to-item');
      $items.each(function(i) { $(this).find('.n').text(i + 1); });
      $items.find('.to-item-rm').prop('disabled', $items.length <= 1);
    }
    function addItem(it) {
      it = it || {};
      let $it = $('<div class="to-item">' +
        '<div class="to-item-head"><span><span class="badge-n n"></span>Destination</span><button type="button" class="btn btn-sm btn-outline-danger to-item-rm" title="Remove"><i class="fas fa-times"></i></button></div>' +
        '<div class="row">' +
          '<div class="col-md-3"><div class="form-group"><label>Date of travel <span class="text-danger">*</span></label><input type="date" name="i_date_from[]" class="form-control i-from"></div></div>' +
          '<div class="col-md-3"><div class="form-group"><label>Until <small style="text-transform:none">(optional)</small></label><input type="date" name="i_date_to[]" class="form-control i-to"></div></div>' +
          '<div class="col-md-3 not-std"><div class="form-group"><label>Travel time</label><input type="text" name="i_travel_time[]" class="form-control" placeholder="e.g. 2 hrs"></div></div>' +
          '<div class="col-md-3 not-std"><div class="form-group"><label>Duration of purpose</label><input type="text" name="i_duration[]" class="form-control" placeholder="e.g. 1 day"></div></div>' +
        '</div>' +
        '<div class="row">' +
          '<div class="col-md-6"><div class="form-group"><label>Location <span class="text-danger">*</span></label><textarea name="i_location[]" class="form-control" rows="2" placeholder="e.g. NIA Regional Office V, Pili, Camarines Sur"></textarea></div></div>' +
          '<div class="col-md-6"><div class="form-group"><label>Purpose <span class="text-danger">*</span></label><textarea name="i_purpose[]" class="form-control" rows="2" placeholder="e.g. attend the Regional Coordination Meeting"></textarea></div></div>' +
        '</div>' +
        '<div class="only-itin to-rev"><div class="to-rev-title"><i class="fas fa-pen"></i> Revised (only if the trip changed)</div><div class="row">' +
          '<div class="col-md-4"><div class="form-group"><label>Revised location</label><textarea name="i_rev_location[]" class="form-control" rows="1"></textarea></div></div>' +
          '<div class="col-md-4"><div class="form-group"><label>Revised purpose</label><textarea name="i_rev_purpose[]" class="form-control" rows="1"></textarea></div></div>' +
          '<div class="col-md-2"><div class="form-group"><label>Travel time</label><input type="text" name="i_rev_travel_time[]" class="form-control"></div></div>' +
          '<div class="col-md-2"><div class="form-group"><label>Duration</label><input type="text" name="i_rev_duration[]" class="form-control"></div></div>' +
        '</div></div></div>');
      ITEM_FIELDS.forEach(function(f) { $it.find('[name="i_' + f + '[]"]').val(it[f] || ''); });
      $('#itin_rows').append($it);
      renumberItems();
      return $it;
    }
    $('#addItemBtn').on('click', function() { addItem().find('.i-from').focus(); });
    $(document).on('click', '#itin_rows .to-item-rm', function() { $(this).closest('.to-item').remove(); renumberItems(); });
    $(document).on('change', '#itin_rows .i-from', function() { $(this).closest('.to-item').find('.i-to').attr('min', this.value); });

    function resetForm() {
      $form[0].reset();
      $('#ppl_rows').find('select').select2('destroy'); $('#ppl_rows').empty();
      $('#itin_rows').empty();
    }

    // New
    $('#newToBtn').on('click', function() {
      resetForm();
      $('#f_to_id').val('');
      $('#toModalTitle').text('New Travel Order');
      $('#toSaveBtn').text('Save Travel Order');
      $('#f_template_key').val(TO_DEFAULT);
      $('#f_to_code').val(TO_CODE); $('#f_series_year').val(TO_YEAR); $('#f_to_seq').val(TO_NEXT);
      $('#f_to_date').val(TO_TODAY);
      $('#f_approver_name, #f_approver_title').val('');
      applyTemplate(true);
      addPerson(); addItem();
      toNoText();
      $('#toModal').modal('show');
    });
    $('#toModal').on('shown.bs.modal', function() { if (!$('#f_to_id').val()) $('#ppl_rows .p-name').first().select2('open'); });

    function getOrder(id, cb) {
      $.post('travel_order.php', { action: 'get', to_id: id }, function(res) {
        if (res.success) cb(res.data); else Swal.fire('Error!', res.message, 'error');
      }, 'json');
    }

    // Edit
    $(document).on('click', '.edit-btn', function() {
      getOrder($(this).data('id'), function(d) {
        resetForm();
        $('#f_to_id').val(d.to_id);
        $('#toModalTitle').text('Edit Travel Order');
        $('#toSaveBtn').text('Update Travel Order');
        $('#f_template_key').val(TO_T[d.template_key] ? d.template_key : TO_DEFAULT);
        $('#f_to_code').val(d.to_code); $('#f_to_seq').val(d.to_seq); $('#f_series_year').val(d.series_year); $('#f_to_date').val(d.to_date);
        ['expected_output','remarks','approver_name','approver_title','recommender_name','recommender_title','prepared_name','prepared_title']
          .forEach(function(f) { $('#f_' + f).val(d[f] || ''); });
        applyTemplate(false);
        (d.people.length ? d.people : [{}]).forEach(addPerson);
        (d.items.length ? d.items : [{}]).forEach(function(it) { addItem(it).find('.i-from').trigger('change'); });
        toNoText();
        $('#toModal').modal('show');
      });
    });

    $form.on('submit', function(e) {
      e.preventDefault();
      let creating = !$('#f_to_id').val(), $btn = $('#toSaveBtn').prop('disabled', true);
      $.post('travel_order.php', $form.serialize() + '&action=' + (creating ? 'create' : 'update'), function(res) {
        if (res.success) Swal.fire('Success!', res.message, 'success').then(() => location.reload());
        else { $btn.prop('disabled', false); Swal.fire('Error!', res.message, 'error'); }
      }, 'json').fail(function() {
        $btn.prop('disabled', false);
        Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
      });
    });

    // View
    $(document).on('click', '.view-btn', function() {
      getOrder($(this).data('id'), function(d) {
        let t = TO_T[d.template_key] || TO_T[TO_DEFAULT];
        let people = d.people.map(function(p) {
          return '<div class="tov-p"><div class="cav-avatar"><i class="fas fa-user"></i></div><div class="cav-person-text"><div class="cav-name" style="font-size:1rem">' + esc(p.name) + '</div><div class="cav-desig">' + esc(p.position) + '</div></div></div>';
        }).join('');
        let rows = d.items.map(function(it, i) {
          let h = '<tr><td>' + (i + 1) + '</td><td>' + esc(fmtRange(it.date_from, it.date_to)) + '</td><td>' + esc(it.location) + '</td><td>' + esc(it.purpose) + '</td><td>' + esc(it.travel_time) + '</td><td>' + esc(it.duration) + '</td></tr>';
          if (it.rev_location || it.rev_purpose || it.rev_travel_time || it.rev_duration) {
            h += '<tr class="tov-rev"><td></td><td><span class="tag">Revised</span></td><td>' + esc(it.rev_location) + '</td><td>' + esc(it.rev_purpose) + '</td><td>' + esc(it.rev_travel_time) + '</td><td>' + esc(it.rev_duration) + '</td></tr>';
          }
          return h;
        }).join('');
        let first = d.items[0] ? d.items[0].date_from : '', last = d.items.reduce(function(a, it) { let e = it.date_to || it.date_from; return e > a ? e : a; }, first);
        let days = first ? Math.round((new Date(last) - new Date(first)) / 86400000) + 1 : 0;
        let extra = '';
        if (d.expected_output || d.remarks) {
          extra = '<div class="cav-grid" style="grid-template-columns:1fr 1fr; margin-bottom:12px">' +
            '<div class="cav-section"><div class="cav-title"><i class="fas fa-bullseye"></i>Expected Output</div><div class="cav-purpose">' + (esc(d.expected_output) || '&mdash;') + '</div></div>' +
            '<div class="cav-section"><div class="cav-title"><i class="fas fa-comment-dots"></i>Remarks</div><div class="cav-purpose">' + (esc(d.remarks) || '&mdash;') + '</div></div></div>';
        }
        let sign = '<div class="cav-sign"><div><span>Approved by</span><strong>' + esc(d.approver_name) + '</strong><small>' + esc(d.approver_title) + '</small></div>';
        if (d.recommender_name) sign += '<div><span>Recommended by</span><strong>' + esc(d.recommender_name) + '</strong><small>' + esc(d.recommender_title) + '</small></div>';
        sign += '<div class="cav-created">' + (d.created_at ? 'Created ' + fmtDate(String(d.created_at).substr(0, 10)) : '') + '</div></div>';
        $('#viewToContent').html(
          '<div class="cav-top"><div><div class="cav-tn-label">Travel Order No.</div><div class="cav-tn">' + esc(d.to_number) + ' s. ' + esc(d.series_year) + '</div></div>' +
            '<div class="cav-tpl"><i class="fas fa-file-word"></i> ' + esc(t.label) + '</div></div>' +
          '<div class="tov-people">' + people + '</div>' +
          '<div class="cav-dates"><div class="cav-date"><span>Order date</span><strong>' + esc(fmtDate(d.to_date)) + '</strong></div>' +
            '<div class="cav-arrow"><i class="fas fa-long-arrow-alt-right"></i><small>' + days + ' day' + (days === 1 ? '' : 's') + '</small></div>' +
            '<div class="cav-date"><span>Travel</span><strong>' + esc(fmtRange(first, last)) + '</strong></div></div>' +
          '<div class="cav-section tov-wrap"><div class="cav-title"><i class="fas fa-route"></i>Itinerary</div>' +
            '<table class="tov-table"><thead><tr><th>#</th><th>Date</th><th>Location</th><th>Purpose</th><th>Travel time</th><th>Duration</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
          extra + sign
        );
        $('#viewToDownloadMenu a').each(function() {
          $(this).attr('href', 'travel_order.php?action=download&id=' + d.to_id + '&template=' + $(this).data('template'));
        });
        $('#viewToModal').modal('show');
      });
    });

    // Delete
    $(document).on('click', '.delete-btn', function() {
      let id = $(this).data('id');
      Swal.fire({
        title: 'Are you sure?', text: "You won't be able to revert this!", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6', confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (!result.isConfirmed) return;
        $.post('travel_order.php', { action: 'delete', to_id: id }, function(res) {
          if (res.success) Swal.fire('Deleted!', res.message, 'success').then(() => location.reload());
          else Swal.fire('Error!', res.message, 'error');
        }, 'json');
      });
    });
  });
  </script>
</body>
</html>