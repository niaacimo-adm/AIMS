<?php
/**
 * get_approved_leaves.php
 * Returns approved leave requests as FullCalendar-compatible events.
 *
 * Query params:
 *   emp_id  (optional) – when supplied, returns ONLY that employee's approved leaves
 *                        (used by leave_request.php to highlight dates in the mini-calendar)
 *   format  (optional) – "dates" → returns a plain array of date strings (YYYY-MM-DD)
 *                        default  → returns FullCalendar event objects
 */
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$database = new Database();
$db       = $database->getConnection();

$emp_id_filter = intval($_GET['emp_id'] ?? 0);
$format        = $_GET['format'] ?? 'events';

// ── Build query ─────────────────────────────────────────────────────────────
// Always exclude Job Order employees.
// If emp_id is given, scope to that employee only.
$where  = "WHERE lr.status = 'Approved'
             AND (ap.status_name IS NULL OR ap.status_name != 'Job Order')";
$params = [];
$types  = '';

if ($emp_id_filter) {
    $where  .= " AND lr.emp_id = ?";
    $params[]= $emp_id_filter;
    $types  .= 'i';
}

$sql = "
    SELECT lr.leave_request_id,
           lr.emp_id,
           lr.date_from,
           lr.date_to,
           lr.number_of_days,
           lr.inclusive_dates,
           lt.leave_type_name,
           CONCAT(e.first_name, ' ', e.last_name) AS emp_name
    FROM leave_request lr
    LEFT JOIN employee            e  ON lr.emp_id               = e.emp_id
    LEFT JOIN appointment_status  ap ON e.appointment_status_id = ap.appointment_id
    LEFT JOIN leave_type          lt ON lr.leave_type_id        = lt.leave_type_id
    $where
    ORDER BY lr.date_from ASC
";

$stmt = $db->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── "dates" format: flat array of every approved-leave date for a single emp ─
if ($format === 'dates') {
    $dates = [];
    foreach ($rows as $row) {
        // Expand inclusive_dates string (comma-separated "Month Day, Year") if present,
        // otherwise walk date_from → date_to.
        if (!empty($row['inclusive_dates'])) {
            foreach (array_map('trim', explode(',', $row['inclusive_dates'])) as $ds) {
                $d = DateTime::createFromFormat('F j, Y', $ds);
                if ($d) {
                    $dates[] = $d->format('Y-m-d');
                }
            }
        } else {
            $cur = new DateTime($row['date_from']);
            $end = new DateTime($row['date_to']);
            while ($cur <= $end) {
                $dow = (int)$cur->format('N'); // 1=Mon … 7=Sun
                if ($dow < 6) {               // weekdays only
                    $dates[] = $cur->format('Y-m-d');
                }
                $cur->modify('+1 day');
            }
        }
    }
    echo json_encode(['success' => true, 'dates' => array_values(array_unique($dates))]);
    exit;
}

// ── Default: FullCalendar event objects ──────────────────────────────────────
$events = [];
foreach ($rows as $row) {
    // FullCalendar end date is exclusive, so add 1 day to date_to
    $endExclusive = (new DateTime($row['date_to']))->modify('+1 day')->format('Y-m-d');

    $events[] = [
        'id'    => 'leave_' . $row['leave_request_id'],
        'title' => $row['emp_name'] . ' — ' . ($row['leave_type_name'] ?? 'Leave')
                   . ' (' . $row['number_of_days'] . 'd)',
        'start' => $row['date_from'],
        'end'   => $endExclusive,
        'allDay'=> true,
        'extendedProps' => [
            'type'           => 'leave',
            'leave_type'     => $row['leave_type_name'],
            'emp_name'       => $row['emp_name'],
            'number_of_days' => $row['number_of_days'],
            'description'    => ($row['leave_type_name'] ?? 'Leave')
                                . ' — ' . $row['number_of_days'] . ' day(s)',
        ],
    ];
}

echo json_encode(['success' => true, 'data' => $events]);