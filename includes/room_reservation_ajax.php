<?php
/**
 * room_reservation_ajax.php
 * AJAX endpoint for the Room Reservation module.
 * Place this file in the same /views/ directory as room_reservation.php
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$action   = $_REQUEST['action'] ?? '';
$emp_id   = $_SESSION['emp_id'] ?? 0;
$is_admin = hasPermission('manage_employees');

// ─── Route ──────────────────────────────────────────────────────────────────
switch ($action) {

    /* ────────────────────────────────────────────────────────
       GET ALL ROOMS
    ──────────────────────────────────────────────────────── */
    case 'get_rooms':
        $sql = "
            SELECT r.*,
                   -- check if any approved reservation is active RIGHT NOW
                   EXISTS (
                       SELECT 1 FROM room_reservations rr
                       WHERE rr.room_id = r.room_id
                         AND rr.status = 'approved'
                         AND rr.reservation_date = CURDATE()
                         AND CURTIME() BETWEEN rr.start_time AND rr.end_time
                   ) AS is_occupied
            FROM   rooms r
            WHERE  r.status = 'active'
            ORDER  BY r.room_name
        ";
        $result = $db->query($sql);
        $rooms  = [];
        while ($row = $result->fetch_assoc()) $rooms[] = $row;
        echo json_encode(['success' => true, 'data' => $rooms]);
        break;

    /* ────────────────────────────────────────────────────────
       GET SINGLE ROOM DETAIL
    ──────────────────────────────────────────────────────── */
    case 'get_room_detail':
        $room_id = (int)($_GET['room_id'] ?? 0);
        if (!$room_id) { echo json_encode(['success'=>false,'message'=>'Missing room_id']); break; }

        $stmt = $db->prepare("SELECT * FROM rooms WHERE room_id = ?");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        echo json_encode($row ? ['success'=>true,'data'=>$row] : ['success'=>false,'message'=>'Room not found']);
        break;

    /* ────────────────────────────────────────────────────────
       GET TODAY'S TIMELINE FOR A ROOM
       Returns hourly slots 07:00–20:00 with booking info
    ──────────────────────────────────────────────────────── */
    case 'get_today_timeline':
        $room_id = (int)($_GET['room_id'] ?? 0);
        if (!$room_id) { echo json_encode(['success'=>false]); break; }

        $stmt = $db->prepare("
            SELECT rr.start_time, rr.end_time, rr.purpose, rr.status,
                   TIMESTAMPDIFF(MINUTE, rr.start_time, rr.end_time) AS duration
            FROM   room_reservations rr
            WHERE  rr.room_id = ?
              AND  rr.reservation_date = CURDATE()
              AND  rr.status IN ('approved','pending')
            ORDER  BY rr.start_time
        ");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $res   = $stmt->get_result();
        $slots = [];
        while ($row = $res->fetch_assoc()) {
            $slots[] = [
                'start_time' => $row['start_time'],
                'end_time'   => $row['end_time'],
                'title'      => $row['purpose'],
                'status'     => $row['status'],
                'duration'   => (int)$row['duration'],
            ];
        }
        echo json_encode(['success'=>true,'slots'=>$slots]);
        break;

    /* ────────────────────────────────────────────────────────
       GET CALENDAR EVENTS
    ──────────────────────────────────────────────────────── */
    case 'get_calendar_events':
        $room_id = (int)($_GET['room_id'] ?? 0);
        $start   = $db->real_escape_string($_GET['start'] ?? date('Y-m-01'));
        $end     = $db->real_escape_string($_GET['end']   ?? date('Y-m-t'));

        $where = "rr.reservation_date BETWEEN '$start' AND '$end'
                  AND rr.status IN ('approved','pending','rejected')";
        if ($room_id) $where .= " AND rr.room_id = $room_id";

        // Non-admin users see only their own + approved
        if (!$is_admin) {
            $where .= " AND (rr.emp_id = $emp_id OR rr.status = 'approved')";
        }

        $sql = "
            SELECT rr.reservation_id,
                   CONCAT(e.first_name,' ',e.last_name) AS reserved_by,
                   r.room_name,
                   rr.purpose, rr.status,
                   rr.reservation_date,
                   rr.start_time, rr.end_time
            FROM   room_reservations rr
            JOIN   rooms r ON r.room_id = rr.room_id
            JOIN   employee e ON e.emp_id = rr.emp_id
            WHERE  $where
            ORDER  BY rr.reservation_date, rr.start_time
        ";
        $result = $db->query($sql);
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $color = '#f59e0b';
            if ($row['status'] === 'approved') $color = '#10b981';
            if ($row['status'] === 'rejected') $color = '#ef4444';

            $events[] = [
                'id'    => $row['reservation_id'],
                'title' => $row['room_name'] . ': ' . $row['purpose'],
                'start' => $row['reservation_date'] . 'T' . $row['start_time'],
                'end'   => $row['reservation_date'] . 'T' . $row['end_time'],
                'color' => $color,
                'extendedProps' => [
                    'reservation_id' => $row['reservation_id'],
                    'status'         => $row['status'],
                    'reserved_by'    => $row['reserved_by'],
                ],
            ];
        }
        echo json_encode(['success'=>true,'events'=>$events]);
        break;

    /* ────────────────────────────────────────────────────────
       CHECK AVAILABILITY
    ──────────────────────────────────────────────────────── */
    case 'check_availability':
        $room_id    = (int)($_GET['room_id'] ?? 0);
        $date       = $db->real_escape_string($_GET['date']       ?? '');
        $start_time = $db->real_escape_string($_GET['start_time'] ?? '');
        $end_time   = $db->real_escape_string($_GET['end_time']   ?? '');

        if (!$room_id || !$date || !$start_time || !$end_time) {
            echo json_encode(['available'=>false,'message'=>'Missing parameters']); break;
        }

        $sql = "
            SELECT reservation_id, purpose, start_time, end_time
            FROM   room_reservations
            WHERE  room_id = $room_id
              AND  reservation_date = '$date'
              AND  status IN ('approved','pending')
              AND  start_time < '$end_time'
              AND  end_time   > '$start_time'
        ";
        $result    = $db->query($sql);
        $conflicts = [];
        while ($row = $result->fetch_assoc()) $conflicts[] = $row;

        echo json_encode(['available' => count($conflicts) === 0, 'conflicts' => $conflicts]);
        break;

    /* ────────────────────────────────────────────────────────
       CREATE RESERVATION
    ──────────────────────────────────────────────────────── */
    case 'create_reservation':
        $room_id    = (int)($_POST['room_id'] ?? 0);
        $date       = $db->real_escape_string($_POST['date']        ?? '');
        $start_time = $db->real_escape_string($_POST['start_time']  ?? '');
        $end_time   = $db->real_escape_string($_POST['end_time']    ?? '');
        $purpose    = $db->real_escape_string($_POST['purpose']     ?? '');
        $description= $db->real_escape_string($_POST['description'] ?? '');
        $attendees  = (int)($_POST['attendees'] ?? 0);

        if (!$room_id || !$date || !$start_time || !$end_time || !$purpose) {
            echo json_encode(['success'=>false,'message'=>'All required fields must be filled.']); break;
        }
        if ($start_time >= $end_time) {
            echo json_encode(['success'=>false,'message'=>'End time must be after start time.']); break;
        }

        // Conflict check (approved only blocks; pending just warns but still allows)
        $conflict_sql = "
            SELECT COUNT(*) AS cnt
            FROM   room_reservations
            WHERE  room_id = $room_id
              AND  reservation_date = '$date'
              AND  status = 'approved'
              AND  start_time < '$end_time'
              AND  end_time   > '$start_time'
        ";
        $cnt = $db->query($conflict_sql)->fetch_assoc()['cnt'];
        if ($cnt > 0) {
            echo json_encode(['success'=>false,'message'=>'This room is already booked (approved) at that time. Please choose a different time.']); break;
        }

        $stmt = $db->prepare("
            INSERT INTO room_reservations
                (room_id, emp_id, reservation_date, start_time, end_time, purpose, description, attendees, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param('iisssssi', $room_id, $emp_id, $date, $start_time, $end_time, $purpose, $description, $attendees);

        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'reservation_id'=>$db->insert_id]);
        } else {
            echo json_encode(['success'=>false,'message'=>$db->error]);
        }
        break;

    /* ────────────────────────────────────────────────────────
       GET MY RESERVATIONS
    ──────────────────────────────────────────────────────── */
    case 'get_my_reservations':
        $stmt = $db->prepare("
            SELECT rr.*, r.room_name,
                   CONCAT(e.first_name,' ',e.last_name) AS full_name
            FROM   room_reservations rr
            JOIN   rooms r ON r.room_id = rr.room_id
            JOIN   employee e ON e.emp_id = rr.emp_id
            WHERE  rr.emp_id = ?
            ORDER  BY rr.reservation_date DESC, rr.start_time DESC
            LIMIT  30
        ");
        $stmt->bind_param('i', $emp_id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        echo json_encode(['success'=>true,'data'=>$rows]);
        break;

    /* ────────────────────────────────────────────────────────
       GET ALL RESERVATIONS (admin)
    ──────────────────────────────────────────────────────── */
    case 'get_all_reservations':
        if (!$is_admin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); break; }

        $status = $db->real_escape_string($_GET['status'] ?? '');
        $where  = $status ? "WHERE rr.status = '$status'" : '';

        $sql = "
            SELECT rr.*,
                   r.room_name,
                   CONCAT(e.first_name,' ',e.last_name) AS full_name
            FROM   room_reservations rr
            JOIN   rooms r ON r.room_id = rr.room_id
            JOIN   employee e ON e.emp_id = rr.emp_id
            $where
            ORDER  BY rr.reservation_date DESC, rr.start_time DESC
        ";
        $result = $db->query($sql);
        $rows   = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(['success'=>true,'data'=>$rows]);
        break;

    /* ────────────────────────────────────────────────────────
       GET SINGLE RESERVATION
    ──────────────────────────────────────────────────────── */
    case 'get_reservation':
        $res_id = (int)($_GET['reservation_id'] ?? 0);
        $stmt   = $db->prepare("
            SELECT rr.*,
                   r.room_name,
                   CONCAT(e.first_name,' ',e.last_name) AS full_name
            FROM   room_reservations rr
            JOIN   rooms r ON r.room_id = rr.room_id
            JOIN   employee e ON e.emp_id = rr.emp_id
            WHERE  rr.reservation_id = ?
        ");
        $stmt->bind_param('i', $res_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        // Non-admin can only see own or approved reservations
        if (!$is_admin && $row && $row['emp_id'] != $emp_id && $row['status'] !== 'approved') {
            echo json_encode(['success'=>false,'message'=>'Unauthorized']); break;
        }
        echo json_encode($row ? ['success'=>true,'data'=>$row] : ['success'=>false,'message'=>'Not found']);
        break;

    /* ────────────────────────────────────────────────────────
       UPDATE STATUS (approve / reject / cancel)
    ──────────────────────────────────────────────────────── */
    case 'update_status':
        $res_id      = (int)($_POST['reservation_id'] ?? 0);
        $new_status  = $db->real_escape_string($_POST['status']      ?? '');
        $admin_notes = $db->real_escape_string($_POST['admin_notes'] ?? '');

        $allowed_statuses = ['approved','rejected','cancelled'];
        if (!in_array($new_status, $allowed_statuses)) {
            echo json_encode(['success'=>false,'message'=>'Invalid status']); break;
        }

        // Only admin can approve/reject; owner can cancel own pending
        $stmt = $db->prepare("SELECT emp_id, status FROM room_reservations WHERE reservation_id = ?");
        $stmt->bind_param('i', $res_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if (!$existing) { echo json_encode(['success'=>false,'message'=>'Not found']); break; }

        if ($new_status === 'cancelled') {
            if ($existing['emp_id'] != $emp_id && !$is_admin) {
                echo json_encode(['success'=>false,'message'=>'Unauthorized']); break;
            }
        } elseif (!$is_admin) {
            echo json_encode(['success'=>false,'message'=>'Unauthorized']); break;
        }

        $stmt2 = $db->prepare("
            UPDATE room_reservations
            SET    status = ?, admin_notes = ?, updated_at = NOW()
            WHERE  reservation_id = ?
        ");
        $stmt2->bind_param('ssi', $new_status, $admin_notes, $res_id);

        if ($stmt2->execute()) echo json_encode(['success'=>true]);
        else echo json_encode(['success'=>false,'message'=>$db->error]);
        break;

    /* ────────────────────────────────────────────────────────
       STATS
    ──────────────────────────────────────────────────────── */
    case 'get_stats':
        $pending  = $db->query("SELECT COUNT(*) AS c FROM room_reservations WHERE emp_id=$emp_id AND status='pending'")->fetch_assoc()['c'];
        $approved = $db->query("SELECT COUNT(*) AS c FROM room_reservations WHERE emp_id=$emp_id AND status='approved'")->fetch_assoc()['c'];
        $today    = $db->query("SELECT COUNT(*) AS c FROM room_reservations WHERE reservation_date=CURDATE() AND status='approved'")->fetch_assoc()['c'];
        echo json_encode(['success'=>true,'pending'=>$pending,'approved'=>$approved,'today'=>$today]);
        break;

    /* ────────────────────────────────────────────────────────
       CREATE ROOM (admin)
    ──────────────────────────────────────────────────────── */
    case 'create_room':
        if (!$is_admin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); break; }

        $room_name      = $db->real_escape_string($_POST['room_name']      ?? '');
        $capacity       = (int)($_POST['capacity']                         ?? 0);
        $floor_location = $db->real_escape_string($_POST['floor_location'] ?? '');
        $description    = $db->real_escape_string($_POST['description']    ?? '');
        $amenities      = $db->real_escape_string($_POST['amenities']      ?? '');
        $color          = $db->real_escape_string($_POST['color']          ?? 'rc-icon-blue');
        $status_r       = $db->real_escape_string($_POST['status']         ?? 'active');

        if (!$room_name || !$capacity) { echo json_encode(['success'=>false,'message'=>'Room name and capacity required.']); break; }

        $sql = "INSERT INTO rooms (room_name,capacity,floor_location,description,amenities,color,status,created_at)
                VALUES ('$room_name',$capacity,'$floor_location','$description','$amenities','$color','$status_r',NOW())";
        if ($db->query($sql)) echo json_encode(['success'=>true,'room_id'=>$db->insert_id]);
        else echo json_encode(['success'=>false,'message'=>$db->error]);
        break;

    /* ────────────────────────────────────────────────────────
       UPDATE ROOM (admin)
    ──────────────────────────────────────────────────────── */
    case 'update_room':
        if (!$is_admin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); break; }

        $room_id        = (int)($_POST['room_id']                          ?? 0);
        $room_name      = $db->real_escape_string($_POST['room_name']      ?? '');
        $capacity       = (int)($_POST['capacity']                         ?? 0);
        $floor_location = $db->real_escape_string($_POST['floor_location'] ?? '');
        $description    = $db->real_escape_string($_POST['description']    ?? '');
        $amenities      = $db->real_escape_string($_POST['amenities']      ?? '');
        $color          = $db->real_escape_string($_POST['color']          ?? 'rc-icon-blue');
        $status_r       = $db->real_escape_string($_POST['status']         ?? 'active');

        $sql = "UPDATE rooms SET room_name='$room_name', capacity=$capacity, floor_location='$floor_location',
                    description='$description', amenities='$amenities', color='$color', status='$status_r',
                    updated_at=NOW()
                WHERE room_id=$room_id";
        if ($db->query($sql)) echo json_encode(['success'=>true]);
        else echo json_encode(['success'=>false,'message'=>$db->error]);
        break;

    /* ────────────────────────────────────────────────────────
       DELETE ROOM (admin)
    ──────────────────────────────────────────────────────── */
    case 'delete_room':
        if (!$is_admin) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); break; }

        $room_id = (int)($_POST['room_id'] ?? 0);
        $db->query("DELETE FROM room_reservations WHERE room_id=$room_id");
        if ($db->query("DELETE FROM rooms WHERE room_id=$room_id")) echo json_encode(['success'=>true]);
        else echo json_encode(['success'=>false,'message'=>$db->error]);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Unknown action: '.$action]);
}