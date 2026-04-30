<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require_once '../includes/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function toMySQLDateTime($input) {
    if (empty($input)) return null;
    $ts = strtotime(str_replace('T', ' ', $input));
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

// ─────────────────────────────────────────────────────────────
// GET – fetch single document
// ─────────────────────────────────────────────────────────────
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        ob_clean(); echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit;
    }

    $stmt = $db->prepare("
        SELECT dr.*, 
               dt.type_name,
               CONCAT(e1.first_name, ' ', e1.last_name) AS forwarded_by_name,
               CONCAT(e2.first_name, ' ', e2.last_name) AS forwarded_to_name,
               ds1.section_name AS from_section_name,
               ds2.section_name AS to_section_name,
               us1.unit_name   AS from_unit_name,
               us2.unit_name   AS to_unit_name
        FROM document_records dr
        LEFT JOIN document_types dt ON dr.document_type_id = dt.id
        LEFT JOIN employee e1 ON dr.forwarded_by_emp_id = e1.emp_id
        LEFT JOIN employee e2 ON dr.forwarded_to_emp_id = e2.emp_id
        LEFT JOIN document_sections ds1 ON dr.from_section_id = ds1.id
        LEFT JOIN document_sections ds2 ON dr.forwarded_to_section_id = ds2.id
        LEFT JOIN unit_section us1 ON dr.from_unit_id = us1.unit_id
        LEFT JOIN unit_section us2 ON dr.forwarded_to_unit_id = us2.unit_id
        WHERE dr.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    if (!$doc) {
        ob_clean(); echo json_encode(['success' => false, 'message' => 'Document not found']); exit;
    }

    $history = [];
    $hstmt = $db->prepare("
        SELECT df.*,
               CONCAT(e_by.first_name, ' ', e_by.last_name) AS fwd_by_name,
               CONCAT(e_to.first_name, ' ', e_to.last_name) AS fwd_to_name,
               ds.section_name AS to_section_name,
               u.unit_name AS to_unit_name
        FROM document_forwards df
        LEFT JOIN employee e_by ON df.fwd_by_emp_id = e_by.emp_id
        LEFT JOIN employee e_to ON df.fwd_to_emp_id = e_to.emp_id
        LEFT JOIN document_sections ds ON df.fwd_to_section_id = ds.id
        LEFT JOIN unit_section u ON df.fwd_to_unit_id = u.unit_id
        WHERE df.document_id = ?
        ORDER BY df.fwd_date ASC
    ");
    if ($hstmt) {
        $hstmt->bind_param("i", $id);
        $hstmt->execute();
        $history = $hstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    ob_clean();
    echo json_encode(['success' => true, 'data' => $doc, 'history' => $history]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// SEARCH EMPLOYEE
// ─────────────────────────────────────────────────────────────
if ($action === 'search_employee') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        ob_clean(); echo json_encode(['success' => false, 'employees' => []]); exit;
    }
    $like = '%' . $q . '%';
    $stmt = $db->prepare("
        SELECT e.emp_id,
               CONCAT(TRIM(e.first_name), ' ', TRIM(e.last_name)) AS full_name,
               s.section_id, s.section_name,
               us.unit_id, us.unit_name
        FROM employee e
        LEFT JOIN section s ON e.section_id = s.section_id
        LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
        WHERE CONCAT(e.first_name, ' ', e.last_name) LIKE ?
        LIMIT 10
    ");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    ob_clean();
    echo json_encode(['success' => true, 'employees' => $employees]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// ADD document
// ─────────────────────────────────────────────────────────────
if ($action === 'add') {
    $kind          = trim($_POST['kind'] ?? '');
    $document_num  = trim($_POST['document_number'] ?? '');
    $type_id       = (int)($_POST['document_type_id'] ?? 0) ?: null;
    $doc_name      = trim($_POST['document_name'] ?? '');
    $date_received = trim($_POST['date_received'] ?? '') ?: null;
    $status        = $_POST['status'] ?? 'pending';
    $remarks       = trim($_POST['remarks'] ?? '');

    if (!$kind || !$document_num || !$doc_name) {
        echo json_encode(['success'=>false,'message'=>'Kind, Document Number, and Name are required.']); exit;
    }

    $forwarded_by_emp_id = $_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? null;
    $date_forwarded = date('Y-m-d H:i:s');
    $date_received_db = !empty($date_received) ? date('Y-m-d H:i:s', strtotime($date_received)) : null;

    $stmt = $db->prepare("
        INSERT INTO document_records
            (kind, document_number, document_type_id, document_name,
             forwarded_by_emp_id, forwarded_by_name,
             from_section_id, from_unit_id,
             forwarded_to_emp_id, forwarded_to,
             forwarded_to_section_id, forwarded_to_unit_id,
             date_forwarded, date_received, status, remarks)
        VALUES (?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?)
    ");
    // 16 parameters
    $types = "ssisisi i i i s i i s s s s";
    $types = preg_replace('/\s+/', '', $types);
    $stmt->bind_param(
        $types,
        $kind, $document_num, $type_id, $doc_name,
        $forwarded_by_emp_id, null,  // forwarded_by_name = null
        null, null,                  // from_section_id, from_unit_id = null
        null, '',                    // forwarded_to_emp_id = null, forwarded_to = ''
        null, null,                  // forwarded_to_section_id, forwarded_to_unit_id = null
        $date_forwarded, $date_received_db, $status, $remarks
    );

    if ($stmt->execute()) {
        echo json_encode(['success'=>true, 'id'=>$db->insert_id]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Add failed: '.$stmt->error]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// UPDATE document
// ─────────────────────────────────────────────────────────────
if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }

    $kind          = trim($_POST['kind'] ?? '');
    $doc_num       = trim($_POST['document_number'] ?? '');
    $type_id       = (int)($_POST['document_type_id'] ?? 0) ?: null;
    $doc_name      = trim($_POST['document_name'] ?? '');
    $fwd_by_emp    = (int)($_POST['forwarded_by_emp_id'] ?? 0) ?: null;
    $from_sec      = (int)($_POST['from_section_id'] ?? 0) ?: null;
    $fwd_to_emp    = (int)($_POST['forwarded_to_emp_id'] ?? 0) ?: null;
    $to_sec        = (int)($_POST['forwarded_to_section_id'] ?? 0) ?: null;
    $to_unit       = (int)($_POST['forwarded_to_unit_id'] ?? 0) ?: null;
    $date_fwd      = $_POST['date_forwarded'] ?? date('Y-m-d H:i:s');
    $date_rcv      = trim($_POST['date_received'] ?? '') ?: null;
    $status        = $_POST['status'] ?? 'pending';
    $remarks       = trim($_POST['remarks'] ?? '');

    $date_fwd_db = date('Y-m-d H:i:s', strtotime($date_fwd));
    $date_rcv_db = !empty($date_rcv) ? date('Y-m-d H:i:s', strtotime($date_rcv)) : null;

    $stmt = $db->prepare("
        UPDATE document_records SET
            kind = ?, document_number = ?, document_type_id = ?, document_name = ?,
            forwarded_by_emp_id = ?, from_section_id = ?,
            forwarded_to_emp_id = ?, forwarded_to_section_id = ?, forwarded_to_unit_id = ?,
            date_forwarded = ?, date_received = ?, status = ?, remarks = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        "ssisiiiiissi",
        $kind, $doc_num, $type_id, $doc_name,
        $fwd_by_emp, $from_sec,
        $fwd_to_emp, $to_sec, $to_unit,
        $date_fwd_db, $date_rcv_db, $status, $remarks, $id
    );

    if ($stmt->execute()) {
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Update failed: '.$stmt->error]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// FORWARD – cleaned, with only JSON output
// ─────────────────────────────────────────────────────────────
if ($action === 'forward') {
    $doc_id         = (int)($_POST['id'] ?? 0);
    $fwd_by_emp     = (int)($_POST['fwd_by_emp_id'] ?? 0) ?: null;
    $fwd_to_emp     = (int)($_POST['fwd_to_emp_id'] ?? 0);
    $fwd_to_sec     = (int)($_POST['fwd_to_section_id'] ?? 0) ?: null;
    $fwd_to_unit    = (int)($_POST['fwd_to_unit_id'] ?? 0) ?: null;
    $fwd_date_raw   = $_POST['fwd_date'] ?? date('Y-m-d H:i:s');
    $fwd_remarks    = trim($_POST['fwd_remarks'] ?? '');

    if (!$doc_id || !$fwd_to_emp) {
        echo json_encode(['success'=>false,'message'=>'Missing required: document ID or recipient emp_id']); exit;
    }

    $fwd_date = toMySQLDateTime($fwd_date_raw) ?: date('Y-m-d H:i:s');

    // Insert history
    $istmt = $db->prepare("
        INSERT INTO document_forwards
            (document_id, fwd_by_emp_id, fwd_to_emp_id, fwd_to_section_id, fwd_to_unit_id, fwd_date, fwd_remarks)
        VALUES (?,?,?,?,?,?,?)
    ");
    if (!$istmt) {
        echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$db->error]); exit;
    }
    $istmt->bind_param("iiiiiss", $doc_id, $fwd_by_emp, $fwd_to_emp, $fwd_to_sec, $fwd_to_unit, $fwd_date, $fwd_remarks);
    if (!$istmt->execute()) {
        echo json_encode(['success'=>false,'message'=>'History insert failed: '.$istmt->error]); exit;
    }

    // Update main record
    $ustmt = $db->prepare("
        UPDATE document_records SET
            forwarded_to_emp_id = ?,
            forwarded_to_section_id = ?,
            forwarded_to_unit_id = ?,
            date_forwarded = ?,
            status = 'pending'
        WHERE id = ?
    ");
    $ustmt->bind_param("iiisi", $fwd_to_emp, $fwd_to_sec, $fwd_to_unit, $fwd_date, $doc_id);
    $ok = $ustmt->execute();

    if ($ok) {
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Update failed: '.$ustmt->error]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// UPDATE STATUS
// ─────────────────────────────────────────────────────────────
if ($action === 'update_status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    if (!$id || !in_array($status, ['pending','received','returned','completed','archived'])) {
        echo json_encode(['success'=>false,'message'=>'Invalid status or ID.']); exit;
    }
    $stmt = $db->prepare("UPDATE document_records SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $success = $stmt->execute();
    echo json_encode(['success'=>$success, 'message'=>$success ? 'Status updated' : 'Update failed: '.$stmt->error]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// DELETE
// ─────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }
    $stmt = $db->prepare("DELETE FROM document_records WHERE id = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    echo json_encode(['success'=>$success, 'message'=>$success ? 'Deleted' : 'Delete failed: '.$stmt->error]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action.']);