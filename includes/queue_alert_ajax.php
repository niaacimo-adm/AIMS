<?php
// includes/queue_alert_ajax.php
// Polled by queue_alert.js from every AIMS page.
//   GET  ?action=get   -> unacknowledged visitors for the logged-in employee
//   POST ?action=ack   -> acknowledge one visitor (id)

require_once '../config/database.php';

// Browser: identified by the PHP session (only start one if a session cookie exists)
$emp_id = null;
if (isset($_COOKIE[session_name()])) {
    session_start();
    $emp_id = $_SESSION['emp_id'] ?? null;
    session_write_close();          // don't lock the session while polling
}

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');
header('Cache-Control: no-store');
ini_set('display_errors', 0);

// 'person'  = only the employee chosen in "Person to Visit" is alerted
// 'section' = everyone in the same IMO / unit / section as the visitor's destination is alerted
const ALERT_SCOPE = 'person';

$db = (new Database())->getConnection();

// Windows tray helper: identified by its personal token (?token=...)
if (!$emp_id) {
    $token = $_GET['token'] ?? '';
    if (preg_match('/^[a-f0-9]{40}$/', $token)) {
        $t = $db->prepare("SELECT emp_id FROM queue_alert_tokens WHERE token = ?");
        $t->bind_param("s", $token);
        $t->execute();
        if ($tr = $t->get_result()->fetch_assoc()) {
            $emp_id = (int)$tr['emp_id'];
        }
    }
}

if (!$emp_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action = $_GET['action'] ?? 'get';

if ($action === 'ack') {
    $id = intval($_POST['id'] ?? 0);
    $stmt = $db->prepare("UPDATE visitor_queue
                          SET alert_ack_at = NOW(), alert_ack_by = ?
                          WHERE id = ? AND alert_ack_at IS NULL");
    $stmt->bind_param("ii", $emp_id, $id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// ---- build "who should see this visitor" condition ----
$cond   = "vq.person_to_visit = ?";
$params = [$emp_id];
$types  = "i";

if (ALERT_SCOPE === 'section') {
    $isMgr = $db->prepare("SELECT 1 FROM managers_office_staff WHERE emp_id = ?");
    $isMgr->bind_param("i", $emp_id);
    $isMgr->execute();

    if ($isMgr->get_result()->num_rows > 0) {
        $cond .= " OR vq.is_manager_office = 1";
    } else {
        $me = $db->prepare("SELECT section_id, unit_section_id FROM employee WHERE emp_id = ?");
        $me->bind_param("i", $emp_id);
        $me->execute();
        $row = $me->get_result()->fetch_assoc();

        if (!empty($row['unit_section_id'])) {
            $cond .= " OR vq.unit_id = ?";
            $params[] = $row['unit_section_id'];
            $types .= "i";
        } elseif (!empty($row['section_id'])) {
            $cond .= " OR (vq.section_id = ? AND vq.unit_id IS NULL)";
            $params[] = $row['section_id'];
            $types .= "i";
        }
    }
}

$sql = "SELECT vq.id, vq.queue_number, vq.priority_number, vq.is_priority,
               vq.visitor_name, vq.company, vq.purpose, vq.time_in, vq.status,
               COALESCE(NULLIF(vq.unit_name,''), vq.section_name) AS destination
        FROM visitor_queue vq
        WHERE DATE(vq.time_in) = CURDATE()
          AND vq.status IN ('waiting','called')
          AND vq.alert_ack_at IS NULL
          AND (vq.created_by IS NULL OR vq.created_by <> ?)
          AND ($cond)
        ORDER BY vq.is_priority DESC, vq.time_in ASC
        LIMIT 20";

$stmt = $db->prepare($sql);
$allParams = array_merge([$emp_id], $params);
$stmt->bind_param("i" . $types, ...$allParams);
$stmt->execute();

$alerts = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $alerts[] = [
        'id'           => (int)$r['id'],
        'queue_number' => $r['is_priority'] ? $r['priority_number'] : $r['queue_number'],
        'is_priority'  => (int)$r['is_priority'],
        'visitor_name' => $r['visitor_name'],
        'company'      => $r['company'],
        'purpose'      => $r['purpose'],
        'destination'  => $r['destination'],
        'status'       => $r['status'],
        'time_in'      => $r['time_in'],
    ];
}

echo json_encode(['success' => true, 'alerts' => $alerts]);