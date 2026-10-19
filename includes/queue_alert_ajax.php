<?php
// includes/queue_alert_ajax.php
// One endpoint for ALL "alert me" events: waiting queue visitors and new chat messages.
//   GET  ?action=get[&v=2][&token=...]  -> alerts for the logged-in employee
//                                          (v=2 clients also receive chat alerts)
//   POST ?action=ack  key=q123 | c31    -> acknowledge (q = queue visitor id, c = chat sender emp_id)

require_once '../config/database.php';

// Chat support is optional: queue alerts keep working even if chat is missing
$chatOk = false;
try {
    if (file_exists(__DIR__ . '/chat_functions.php')) {
        require_once __DIR__ . '/chat_functions.php';
        $chatOk = class_exists('ChatFunctions');
    }
} catch (Throwable $e) {
    $chatOk = false;
}

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

// ---------------------------------------------------------------- ACK
if ($action === 'ack') {
    $key = (string)($_POST['key'] ?? ($_POST['id'] ?? ''));

    if ($chatOk && preg_match('/^c(\d+)$/', $key, $m)) {
        // Chat: remember how many unread messages from this sender were dismissed
        try {
            $sid  = (int)$m[1];
            $chat = new ChatFunctions();
            $u    = $chat->getUnreadCounts($emp_id);
            $cnt  = (int)($u['user_unread_counts'][$sid] ?? 0);
            $stmt = $db->prepare("REPLACE INTO chat_alert_ack (emp_id, sender_id, acked_count) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $emp_id, $sid, $cnt);
            $stmt->execute();
        } catch (Throwable $e) {}
    } else {
        $id = intval(preg_replace('/^q/', '', $key));
        $stmt = $db->prepare("UPDATE visitor_queue
                              SET alert_ack_at = NOW(), alert_ack_by = ?
                              WHERE id = ? AND alert_ack_at IS NULL");
        $stmt->bind_param("ii", $emp_id, $id);
        $stmt->execute();
    }
    echo json_encode(['success' => true]);
    exit;
}

// ---------------------------------------------------------------- QUEUE ALERTS
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
        'key'          => 'q' . (int)$r['id'],
        'type'         => 'queue',
        'version'      => 1,
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

// ---------------------------------------------------------------- CHAT ALERTS (v=2 clients only)
if ($chatOk && ($_GET['v'] ?? '') === '2') {
    try {
        $chat   = new ChatFunctions();
        $unread = $chat->getUnreadCounts($emp_id);
        $counts = $unread['user_unread_counts'] ?? [];

        // previously dismissed counts
        $acked = [];
        $q = $db->prepare("SELECT sender_id, acked_count FROM chat_alert_ack WHERE emp_id = ?");
        $q->bind_param("i", $emp_id);
        $q->execute();
        foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $a) {
            $acked[(int)$a['sender_id']] = (int)$a['acked_count'];
        }

        $stillUnread = [];
        foreach ($counts as $sid => $cnt) {
            $sid = (int)$sid;
            $cnt = (int)$cnt;
            if ($cnt <= 0 || $sid === (int)$emp_id) continue;
            $stillUnread[] = $sid;

            // already dismissed and nothing new since
            if (isset($acked[$sid]) && $cnt <= $acked[$sid]) continue;

            // sender details
            $s = $db->prepare("SELECT first_name, last_name, picture FROM employee WHERE emp_id = ?");
            $s->bind_param("i", $sid);
            $s->execute();
            $emp = $s->get_result()->fetch_assoc();
            if (!$emp) continue;

            // newest message from that sender (for the preview)
            $preview = 'Sent you a message';
            $when    = date('Y-m-d H:i:s');
            $room = $chat->getPrivateRoom($emp_id, $sid);
            if ($room) {
                $msgs = $chat->getMessages($room, 0);
                for ($i = count($msgs) - 1; $i >= 0; $i--) {
                    $m = $msgs[$i];
                    if ((int)$m['sender_id'] === $sid && empty($m['is_deleted'])) {
                        if (($m['message_type'] ?? '') === 'image')      $preview = 'Sent a photo';
                        elseif (($m['message_type'] ?? '') === 'file')   $preview = 'Sent a file: ' . $m['message'];
                        else                                             $preview = (string)$m['message'];
                        if (mb_strlen($preview) > 140) $preview = mb_substr($preview, 0, 140) . '...';
                        $when = $m['created_at'] ?? $when;
                        break;
                    }
                }
            }

            $alerts[] = [
                'key'         => 'c' . $sid,
                'type'        => 'chat',
                'version'     => $cnt,
                'sender_id'   => $sid,
                'sender_name' => trim($emp['first_name'] . ' ' . $emp['last_name']),
                'picture'     => $emp['picture'],
                'preview'     => $preview,
                'count'       => $cnt,
                'time_in'     => $when,
            ];
        }

        // forget dismissals for senders who no longer have unread messages
        if ($stillUnread) {
            $in = implode(',', array_map('intval', $stillUnread));
            $db->query("DELETE FROM chat_alert_ack WHERE emp_id = " . (int)$emp_id . " AND sender_id NOT IN ($in)");
        } else {
            $db->query("DELETE FROM chat_alert_ack WHERE emp_id = " . (int)$emp_id);
        }
    } catch (Throwable $e) {
        // chat problems must never break queue alerts
    }
}

echo json_encode(['success' => true, 'alerts' => $alerts]);