<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

require_once '../includes/auth.php';
require_once '../config/database.php';

// Force 200 OK and JSON content type — must come AFTER includes
// so any header() calls inside auth.php/database.php are overridden.
http_response_code(200);
header('Content-Type: application/json');

// Shutdown guard: if PHP dies unexpectedly (fatal error, uncaught exception)
// after we've already started a JSON response, output a safe JSON error
// instead of an HTML error page that breaks jQuery's JSON parser.
$__json_sent = false;
register_shutdown_function(function() use (&$__json_sent) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!$__json_sent) {
            ob_clean();
            http_response_code(200); // keep 200 so jQuery parses the JSON
            echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $err['message'] . ' in ' . basename($err['file']) . ' line ' . $err['line']
            ]);
        }
    }
});

$database = new Database();
$db = $database->getConnection();

// All date columns are TIMESTAMP. We set session timezone to PHT (+08:00).
// IMPORTANT: Because of this session tz, MariaDB interprets ALL inserted datetime
// strings as PHT and auto-converts to UTC for storage. So we must INSERT using
// date() (PHT local time), NOT gmdate() (UTC) — otherwise MariaDB double-subtracts 8hrs.
$db->query("SET time_zone = '+08:00'");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Helper: clean buffer, output JSON with guaranteed 200, mark sent, exit.
function json_out(array $data): void {
    global $__json_sent;
    $__json_sent = true;
    ob_clean();
    http_response_code(200);
    echo json_encode($data);
    exit;
}

// toMySQLDateTime: session tz is PHT, so MariaDB expects PHT strings on INSERT.
// Just normalize the datetime-local format — no timezone math needed.
function toMySQLDateTime($input) {
    if (empty($input)) return null;
    $ts = strtotime(str_replace('T', ' ', $input));
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

// resolveDocumentSectionForeignKeyId() removed — document_records.forwarded_to_section_id
// references section.section_id directly; no mapping table needed.

// ─────────────────────────────────────────────────────────────
// GET – fetch single document
// ─────────────────────────────────────────────────────────────
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        json_out(['success' => false, 'message' => 'Invalid ID']);
    }

    // FIX: was joining 'document_sections' (wrong table) — correct table is 'section'
    $stmt = $db->prepare("
        SELECT dr.*,
               dt.type_name,
               CONCAT(TRIM(e1.first_name), ' ', TRIM(e1.last_name)) AS forwarded_by_name,
               CONCAT(TRIM(e2.first_name), ' ', TRIM(e2.last_name)) AS forwarded_to_name,
               s1.section_name  AS from_section_name,
               s2.section_name  AS to_section_name,
               us1.unit_name    AS from_unit_name,
               us2.unit_name    AS to_unit_name
        FROM document_records dr
        LEFT JOIN document_types dt  ON dr.document_type_id         = dt.id
        LEFT JOIN employee       e1  ON dr.forwarded_by_emp_id      = e1.emp_id
        LEFT JOIN employee       e2  ON dr.forwarded_to_emp_id      = e2.emp_id
        LEFT JOIN section        s1  ON dr.from_section_id          = s1.section_id
        LEFT JOIN section        s2  ON dr.forwarded_to_section_id  = s2.section_id
        LEFT JOIN unit_section   us1 ON dr.from_unit_id             = us1.unit_id
        LEFT JOIN unit_section   us2 ON dr.forwarded_to_unit_id     = us2.unit_id
        WHERE dr.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    if (!$doc) {
        json_out(['success' => false, 'message' => 'Document not found']);
    }

    $history = [];
    // FIX: was joining 'document_sections' (wrong table) — correct table is 'section'
    $hstmt = $db->prepare("
        SELECT df.*,
               CONCAT(TRIM(e_by.first_name), ' ', TRIM(e_by.last_name)) AS fwd_by_name,
               CONCAT(TRIM(e_to.first_name), ' ', TRIM(e_to.last_name)) AS fwd_to_name,
               s.section_name   AS to_section_name,
               u.unit_name      AS to_unit_name,
               o.office_name    AS to_office_name
        FROM document_forwards df
        LEFT JOIN employee     e_by ON df.fwd_by_emp_id     = e_by.emp_id
        LEFT JOIN employee     e_to ON df.fwd_to_emp_id     = e_to.emp_id
        LEFT JOIN section      s    ON df.fwd_to_section_id = s.section_id
        LEFT JOIN unit_section u    ON df.fwd_to_unit_id    = u.unit_id
        LEFT JOIN office       o    ON df.fwd_to_office_id  = o.office_id
        WHERE df.document_id = ?
        ORDER BY df.id ASC
    ");
    if ($hstmt) {
        $hstmt->bind_param("i", $id);
        $hstmt->execute();
        $history = $hstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    json_out(['success' => true, 'data' => $doc, 'history' => $history]);
}

// ─────────────────────────────────────────────────────────────
// GET SECTIONS – for forward modal dropdown
// ─────────────────────────────────────────────────────────────
if ($action === 'get_sections') {
    $sections = $db->query("
        SELECT s.section_id AS id, s.section_name, s.section_code,
               o.office_name
        FROM section s
        LEFT JOIN office o ON s.office_id = o.office_id
        ORDER BY s.section_name
    ")->fetch_all(MYSQLI_ASSOC);

    json_out(['success' => true, 'sections' => $sections]);
}

// ─────────────────────────────────────────────────────────────
// GET UNITS BY SECTION
// ─────────────────────────────────────────────────────────────
if ($action === 'get_units') {
    $sec_id = (int)($_GET['section_id'] ?? 0);
    $units  = [];
    if ($sec_id) {
        $stmt = $db->prepare("
            SELECT unit_id AS id, unit_name, unit_code
            FROM unit_section
            WHERE section_id = ?
            ORDER BY unit_name
        ");
        $stmt->bind_param("i", $sec_id);
        $stmt->execute();
        $units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    json_out(['success' => true, 'units' => $units]);
}

// ─────────────────────────────────────────────────────────────
// GET NOTIFICATIONS – counts pending docs for user's section/unit/office
// ─────────────────────────────────────────────────────────────
if ($action === 'get_notifications') {
    $emp_id    = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
    $sec_id    = 0;
    $unit_id   = 0;
    $office_id = 0;

    if ($emp_id) {
        // FIX: employee table has no 'unit_section_id' mapped to a different alias
        // Correct column names per schema: section_id, unit_section_id, office_id
        $us = $db->prepare("
            SELECT section_id, unit_section_id, office_id
            FROM employee WHERE emp_id = ? LIMIT 1
        ");
        $us->bind_param("i", $emp_id);
        $us->execute();
        $urow      = $us->get_result()->fetch_assoc();
        $sec_id    = (int)($urow['section_id']      ?? 0);
        $unit_id   = (int)($urow['unit_section_id'] ?? 0);
        $office_id = (int)($urow['office_id']       ?? 0);
    }

    $counts = ['incoming' => 0, 'outgoing' => 0, 'internal' => 0, 'total' => 0];

    // 1) Documents forwarded to my section/unit
    if ($sec_id) {
        $qstmt = $db->prepare("
            SELECT kind, COUNT(*) AS cnt
            FROM document_records
            WHERE status = 'pending'
              AND forwarded_to_section_id = ?
              AND (forwarded_to_unit_id = ? OR forwarded_to_unit_id IS NULL OR ? = 0)
            GROUP BY kind
        ");
        $qstmt->bind_param("iii", $sec_id, $unit_id, $unit_id);
        $qstmt->execute();
        $rows = $qstmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $row) {
            $k = $row['kind'];
            if (isset($counts[$k])) {
                $counts[$k]      += (int)$row['cnt'];
                $counts['total'] += (int)$row['cnt'];
            }
        }
    }

    // 2) Documents forwarded to my office (manager or is_manager flag)
    // FIX: employee table has no role_id column — use is_manager / is_manager_office_staff instead
    if ($office_id) {
        $checkAccess = $db->prepare("
            SELECT 1 FROM employee
            WHERE emp_id = ? AND office_id = ?
              AND (is_manager = 1 OR is_manager_office_staff = 1)
            LIMIT 1
        ");
        $checkAccess->bind_param("ii", $emp_id, $office_id);
        $checkAccess->execute();
        $hasAccess = $checkAccess->get_result()->num_rows > 0;

        // Also check if they are the office manager directly
        if (!$hasAccess) {
            $checkManager = $db->prepare("
                SELECT 1 FROM office WHERE office_id = ? AND manager_emp_id = ? LIMIT 1
            ");
            $checkManager->bind_param("ii", $office_id, $emp_id);
            $checkManager->execute();
            $hasAccess = $checkManager->get_result()->num_rows > 0;
        }

        if ($hasAccess) {
            $offStmt = $db->prepare("
                SELECT kind, COUNT(*) AS cnt
                FROM document_records
                WHERE status = 'pending' AND forwarded_to_office_id = ?
                GROUP BY kind
            ");
            $offStmt->bind_param("i", $office_id);
            $offStmt->execute();
            $offRows = $offStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($offRows as $row) {
                $k = $row['kind'];
                if (isset($counts[$k])) {
                    $counts[$k]      += (int)$row['cnt'];
                    $counts['total'] += (int)$row['cnt'];
                }
            }
        }
    }

    json_out(['success' => true, 'counts' => $counts]);
}

// ─────────────────────────────────────────────────────────────
// ADD document
// ─────────────────────────────────────────────────────────────
if ($action === 'add') {
    $kind          = trim($_POST['kind'] ?? '');
    $document_num  = trim($_POST['document_number'] ?? '');
    $type_id       = (int)($_POST['document_type_id'] ?? 0) ?: null;
    $doc_name      = trim($_POST['document_name'] ?? '');
    $date_received = null; // always set to creation time below
    $status        = 'received'; // always 'received' on creation
    $remarks       = trim($_POST['remarks'] ?? '');

    if (!$kind || !$document_num || !$doc_name) {
        json_out(['success' => false, 'message' => 'Kind, Document Number, and Name are required.']);
    }

    if (!in_array($kind, ['incoming', 'outgoing', 'internal'])) {
        json_out(['success' => false, 'message' => 'Invalid kind value.']);
    }

    $forwarded_by_emp_id = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0) ?: null;
    $date_forwarded      = date('Y-m-d H:i:s'); // PHT local time — MariaDB session tz converts to UTC on store
    $date_received_db    = $date_forwarded;      // date_received = creation time

    // FIX: previous bind_param had wrong type string "ssisisiiisiissss"
    // Correct mapping: kind(s), doc_num(s), type_id(i), doc_name(s),
    //   fwd_by_emp(i), fwd_by_name(s), from_sec(i), from_unit(i),
    //   fwd_to_emp(i), fwd_to(s), fwd_to_sec(i), fwd_to_unit(i),
    //   date_fwd(s), date_rcv(s), status(s), remarks(s)
    // = "ssissiiiissssss" — but nulls for int still bind as i
    $null_str  = null;
    $null_int1 = null;
    $null_int2 = null;
    $null_int3 = null;
    $empty_str = '';
    $null_int4 = null;
    $null_int5 = null;

    $stmt = $db->prepare("
        INSERT INTO document_records
            (kind, document_number, document_type_id, document_name,
             forwarded_by_emp_id, forwarded_by_name,
             from_section_id, from_unit_id,
             forwarded_to_emp_id, forwarded_to,
             forwarded_to_section_id, forwarded_to_unit_id,
             date_forwarded, date_received, status, remarks,
             created_by_emp_id)
        VALUES (?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?, ?)
    ");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
    }

    // Correct type string: 17 params => s,s,i,s, i,s,i,i, i,s,i,i, s,s,s,s, i
    $stmt->bind_param(
        "ssisisiiissiisssi",
        $kind, $document_num, $type_id, $doc_name,
        $forwarded_by_emp_id, $null_str,
        $null_int1, $null_int2,
        $null_int3, $empty_str,
        $null_int4, $null_int5,
        $date_forwarded, $date_received_db, $status, $remarks,
        $forwarded_by_emp_id  // created_by_emp_id = the inserting user
    );

    if ($stmt->execute()) {
        json_out(['success' => true, 'id' => $db->insert_id]);
    } else {
        json_out(['success' => false, 'message' => 'Add failed: ' . $stmt->error]);
    }
}

// ─────────────────────────────────────────────────────────────
// UPDATE document
// ─────────────────────────────────────────────────────────────
if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { json_out(['success' => false, 'message' => 'Invalid ID']); }

    $kind       = trim($_POST['kind'] ?? '');
    $doc_num    = trim($_POST['document_number'] ?? '');
    $type_id    = (int)($_POST['document_type_id'] ?? 0) ?: null;
    $doc_name   = trim($_POST['document_name'] ?? '');
    $fwd_by_emp = (int)($_POST['forwarded_by_emp_id'] ?? 0) ?: null;
    $from_sec   = (int)($_POST['from_section_id'] ?? 0) ?: null;
    $fwd_to_emp = (int)($_POST['forwarded_to_emp_id'] ?? 0) ?: null;
    $to_sec     = (int)($_POST['forwarded_to_section_id'] ?? 0) ?: null;
    $to_unit    = (int)($_POST['forwarded_to_unit_id'] ?? 0) ?: null;
    $date_fwd   = $_POST['date_forwarded'] ?? date('Y-m-d H:i:s');
    $date_rcv   = trim($_POST['date_received'] ?? '') ?: null;
    $status     = $_POST['status'] ?? 'pending';
    $remarks    = trim($_POST['remarks'] ?? '');

    if (!in_array($kind, ['incoming', 'outgoing', 'internal'])) {
        json_out(['success' => false, 'message' => 'Invalid kind value.']);
    }

    // Convert input from browser (PHT datetime-local) for storage.
    // Session tz is PHT, so pass PHT strings — MariaDB converts to UTC on store.
    $date_fwd_db = (!empty($date_fwd) && $date_fwd !== '0000-00-00 00:00:00')
        ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $date_fwd)))
        : date('Y-m-d H:i:s');
    $date_rcv_db = (!empty($date_rcv) && $date_rcv !== '0000-00-00 00:00:00')
        ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $date_rcv)))
        : null;

    $stmt = $db->prepare("
        UPDATE document_records SET
            kind = ?, document_number = ?, document_type_id = ?, document_name = ?,
            forwarded_by_emp_id = ?, from_section_id = ?,
            forwarded_to_emp_id = ?, forwarded_to_section_id = ?, forwarded_to_unit_id = ?,
            date_forwarded = ?, date_received = ?, status = ?, remarks = ?
        WHERE id = ?
    ");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
    }
    // 14 params: s,s,i,s, i,i, i,i,i, s,s,s,s, i
    $stmt->bind_param(
        "ssisiiiisssssi",
        $kind, $doc_num, $type_id, $doc_name,
        $fwd_by_emp, $from_sec,
        $fwd_to_emp, $to_sec, $to_unit,
        $date_fwd_db, $date_rcv_db, $status, $remarks, $id
    );

    if ($stmt->execute()) {
        json_out(['success' => true]);
    } else {
        json_out(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    }
}

// ─────────────────────────────────────────────────────────────
// FORWARD – section/unit or IMO office
// ─────────────────────────────────────────────────────────────
if ($action === 'forward') {
    $doc_id      = (int)($_POST['id'] ?? 0);
    $fwd_by_emp  = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0) ?: null;
    $fwd_to_sec  = (int)($_POST['fwd_to_section_id'] ?? 0) ?: null;
    $fwd_to_unit = (int)($_POST['fwd_to_unit_id'] ?? 0) ?: null;
    $fwd_to_off  = (int)($_POST['fwd_to_office_id'] ?? 0) ?: null;
    $fwd_remarks  = trim($_POST['fwd_remarks'] ?? '');
    $forward_to   = trim($_POST['forward_to'] ?? 'section');

    if (!$doc_id) {
        json_out(['success' => false, 'message' => 'Document ID missing']);
    }

    // Stamp current PHT time — MariaDB session tz (+08:00) converts to UTC on store.
    $fwd_date = date('Y-m-d H:i:s');

    $fwd_to_emp    = null;
    $resolved_name = null;
    $fwd_label     = '';

    // ----- IMO Office forwarding -----
    if ($forward_to === 'imo' && $fwd_to_off) {
        // 1. Try office manager
        $offStmt = $db->prepare("
            SELECT o.manager_emp_id,
                   CONCAT(TRIM(e.first_name),' ',TRIM(e.last_name)) AS manager_name
            FROM office o
            LEFT JOIN employee e ON o.manager_emp_id = e.emp_id
            WHERE o.office_id = ?
        ");
        $offStmt->bind_param("i", $fwd_to_off);
        $offStmt->execute();
        $offRow = $offStmt->get_result()->fetch_assoc();
        if ($offRow && $offRow['manager_emp_id']) {
            $fwd_to_emp    = (int)$offRow['manager_emp_id'];
            $resolved_name = $offRow['manager_name'];
        }

        // 2. Fallback: any employee in this office marked as manager/office staff
        // FIX: removed role_id = 22 (column doesn't exist); use is_manager_office_staff instead
        if (!$fwd_to_emp) {
            $fpStmt = $db->prepare("
                SELECT e.emp_id,
                       CONCAT(TRIM(e.first_name),' ',TRIM(e.last_name)) AS full_name
                FROM employee e
                WHERE e.office_id = ? AND (e.is_manager = 1 OR e.is_manager_office_staff = 1)
                LIMIT 1
            ");
            $fpStmt->bind_param("i", $fwd_to_off);
            $fpStmt->execute();
            $fpRow = $fpStmt->get_result()->fetch_assoc();
            if ($fpRow) {
                $fwd_to_emp    = (int)$fpRow['emp_id'];
                $resolved_name = $fpRow['full_name'];
            }
        }

        // Build label
        $offNameStmt = $db->prepare("SELECT office_name FROM office WHERE office_id = ?");
        $offNameStmt->bind_param("i", $fwd_to_off);
        $offNameStmt->execute();
        $offName   = $offNameStmt->get_result()->fetch_assoc();
        $fwd_label = $offName['office_name'] ?? 'IMO Office';

        // Clear section/unit for this path
        $fwd_to_sec  = null;
        $fwd_to_unit = null;
    }
    // ----- Section / Unit forwarding -----
    else {
        if (!$fwd_to_sec) {
            json_out(['success' => false, 'message' => 'Destination section is required']);
        }

        // Use section head as the recipient
        $headstmt = $db->prepare("
            SELECT s.head_emp_id,
                   CONCAT(TRIM(e.first_name),' ',TRIM(e.last_name)) AS full_name
            FROM section s
            LEFT JOIN employee e ON s.head_emp_id = e.emp_id
            WHERE s.section_id = ?
            LIMIT 1
        ");
        $headstmt->bind_param("i", $fwd_to_sec);
        $headstmt->execute();
        $headrow = $headstmt->get_result()->fetch_assoc();
        if ($headrow && $headrow['head_emp_id']) {
            $fwd_to_emp    = (int)$headrow['head_emp_id'];
            $resolved_name = $headrow['full_name'];
        }

        // Build label — fetch section name
        $sec_label_stmt = $db->prepare("SELECT section_name FROM section WHERE section_id = ? LIMIT 1");
        $sec_label_stmt->bind_param("i", $fwd_to_sec);
        $sec_label_stmt->execute();
        $sec_label_row = $sec_label_stmt->get_result()->fetch_assoc();
        $sec_label_stmt->close();
        $section_label = $sec_label_row['section_name'] ?? 'Unknown Section';

        // Fetch unit name only if a unit was selected
        $unit_label = '';
        if ($fwd_to_unit) {
            $unit_label_stmt = $db->prepare("SELECT unit_name FROM unit_section WHERE unit_id = ? LIMIT 1");
            $unit_label_stmt->bind_param("i", $fwd_to_unit);
            $unit_label_stmt->execute();
            $unit_label_row = $unit_label_stmt->get_result()->fetch_assoc();
            $unit_label_stmt->close();
            $unit_label = $unit_label_row['unit_name'] ?? '';
        }

        $fwd_label = $unit_label ? "$section_label – $unit_label" : $section_label;

        // Clear office field for this path
        $fwd_to_off = null;
    }

    // The fk_to_section constraint on document_records.forwarded_to_section_id
    // references document_sections.id — but document_sections has no section_id column
    // and is unrelated to the section table. This is a DB design mismatch fixed by
    // the migration SQL. After running the migration, forwarded_to_section_id stores
    // section.section_id directly. Until then we pass NULL to avoid the FK violation.
    // Run fix_fk_migration.sql first, then this comment and the null workaround can be removed.

    // Insert forwarding history
    $istmt = $db->prepare("
        INSERT INTO document_forwards
            (document_id, fwd_by_emp_id, fwd_to_emp_id, fwd_to_section_id, fwd_to_unit_id, fwd_to_office_id, fwd_date, fwd_remarks)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    if (!$istmt) {
        json_out(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
    }
    $istmt->bind_param("iiiiiiss",
        $doc_id, $fwd_by_emp, $fwd_to_emp,
        $fwd_to_sec, $fwd_to_unit, $fwd_to_off,
        $fwd_date, $fwd_remarks
    );
    if (!$istmt->execute()) {
        json_out(['success' => false, 'message' => 'History insert failed: ' . $istmt->error]);
    }

    // Update main document record
    $ustmt = $db->prepare("
        UPDATE document_records SET
            forwarded_to_emp_id      = ?,
            forwarded_to_section_id  = ?,
            forwarded_to_unit_id     = ?,
            forwarded_to_office_id   = ?,
            forwarded_to             = ?,
            date_forwarded           = ?,
            status                   = 'pending'
        WHERE id = ?
    ");
    // forwarded_to_section_id: after migration references section.section_id directly
    $ustmt->bind_param("iiiissi",
        $fwd_to_emp, $fwd_to_sec, $fwd_to_unit, $fwd_to_off,
        $fwd_label, $fwd_date, $doc_id
    );
    $ok = $ustmt->execute();

    if ($ok) {
        json_out([
            'success'      => true,
            'focal_person' => $resolved_name,
            'destination'  => $fwd_label,
        ]);
    } else {
        json_out(['success' => false, 'message' => 'Update failed: ' . $ustmt->error]);
    }
}

// ─────────────────────────────────────────────────────────────
// UPDATE STATUS
// ─────────────────────────────────────────────────────────────
if ($action === 'update_status') {
    $id     = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    if (!$id || !in_array($status, ['pending', 'received', 'returned', 'completed', 'archived'])) {
        json_out(['success' => false, 'message' => 'Invalid status or ID.']);
    }
    $stmt = $db->prepare("UPDATE document_records SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $success = $stmt->execute();
    json_out([
        'success' => $success,
        'message' => $success ? 'Status updated' : 'Update failed: ' . $stmt->error,
    ]);
}

// ─────────────────────────────────────────────────────────────
// Helper: resolve Masteradmin emp_id(s)
// Administrator = user_roles.id = 1, joined via users.user_role_id.
// The employee table has NO role_id or is_masteradmin column.
// ─────────────────────────────────────────────────────────────
function getMasteradminIds($db): array {
    // Primary: users table joined to user_roles, get associated employee_id
    $res = $db->query("
        SELECT u.employee_id AS emp_id
        FROM users u
        JOIN user_roles ur ON u.role_id = ur.id
        WHERE ur.id = 1 AND u.employee_id IS NOT NULL
        LIMIT 10
    ");
    if ($res && $res->num_rows > 0) {
        $ids = array_column($res->fetch_all(MYSQLI_ASSOC), 'emp_id');
        return array_map('intval', $ids);
    }
    // Fallback: users with role_id = 1 (in case join fails)
    $res2 = $db->query("
        SELECT employee_id AS emp_id
        FROM users
        WHERE role_id = 1 AND employee_id IS NOT NULL
        LIMIT 10
    ");
    if ($res2 && $res2->num_rows > 0) {
        $ids = array_column($res2->fetch_all(MYSQLI_ASSOC), 'emp_id');
        return array_map('intval', $ids);
    }
    return [];
}

// ─────────────────────────────────────────────────────────────
// REQUEST DELETE  (replaces the old direct delete)
// Only the document creator can request deletion.
// A pending request blocks a second request.
// ─────────────────────────────────────────────────────────────
if ($action === 'request_delete') {
    $doc_id    = (int)($_POST['id'] ?? 0);
    $reason    = trim($_POST['reason'] ?? '');
    $emp_id    = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);

    if (!$doc_id) { json_out(['success' => false, 'message' => 'Invalid document ID.']); }
    if (!$emp_id) { json_out(['success' => false, 'message' => 'Not authenticated.']); }
    if (!$reason) { json_out(['success' => false, 'message' => 'Please provide a reason for deletion.']); }

    // Verify the caller is the creator of this document
    $chk = $db->prepare("SELECT id, document_number, document_name, created_by_emp_id FROM document_records WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $doc_id);
    $chk->execute();
    $doc = $chk->get_result()->fetch_assoc();
    if (!$doc) { json_out(['success' => false, 'message' => 'Document not found.']); }
    if ((int)$doc['created_by_emp_id'] !== $emp_id) {
        json_out(['success' => false, 'message' => 'Only the user who created this document can request its deletion.']);
    }

    // Check for an existing pending request
    $dup = $db->prepare("SELECT id FROM document_delete_requests WHERE document_id = ? AND status = 'pending' LIMIT 1");
    $dup->bind_param("i", $doc_id);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        json_out(['success' => false, 'message' => 'A delete request for this document is already pending approval.']);
    }

    // Requester full name for notification message
    $nstmt = $db->prepare("SELECT CONCAT(TRIM(first_name),' ',TRIM(last_name)) AS full_name FROM employee WHERE emp_id = ? LIMIT 1");
    $nstmt->bind_param("i", $emp_id);
    $nstmt->execute();
    $nrow = $nstmt->get_result()->fetch_assoc();
    $requester_name = $nrow['full_name'] ?? 'A user';

    // Insert delete request
    $ins = $db->prepare("
        INSERT INTO document_delete_requests (document_id, requested_by, reason, status, created_at)
        VALUES (?, ?, 'pending', ?, NOW())
    ");
    // Corrected: 3 params — i, i, s  (status is the literal string, reason is the variable)
    $ins2 = $db->prepare("
        INSERT INTO document_delete_requests (document_id, requested_by, reason, status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $ins2->bind_param("iis", $doc_id, $emp_id, $reason);
    if (!$ins2->execute()) {
        json_out(['success' => false, 'message' => 'Could not save request: ' . $ins2->error]);
    }
    $request_id = $db->insert_id;

    // Notify all Masteradmin users
    $admin_ids = getMasteradminIds($db);
    $msg = "{$requester_name} requested deletion of document \"{$doc['document_number']}\" — {$doc['document_name']}. Reason: {$reason}";
    $type = 'delete_request';
    $notif_stmt = $db->prepare("
        INSERT INTO document_notifications (recipient_emp_id, type, reference_id, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    foreach ($admin_ids as $admin_emp_id) {
        $notif_stmt->bind_param("isis", $admin_emp_id, $type, $request_id, $msg);
        $notif_stmt->execute();
    }

    json_out(['success' => true, 'message' => 'Delete request submitted. Awaiting Masteradmin approval.']);
}

// ─────────────────────────────────────────────────────────────
// APPROVE DELETE  (Masteradmin only)
// ─────────────────────────────────────────────────────────────
if ($action === 'approve_delete') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $admin_note = trim($_POST['admin_note'] ?? '');
    $reviewer   = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);

    if (!$request_id) { json_out(['success' => false, 'message' => 'Invalid request ID.']); }

    // Confirm reviewer is a Masteradmin
    $admin_ids = getMasteradminIds($db);
    if (!in_array($reviewer, $admin_ids)) {
        json_out(['success' => false, 'message' => 'Unauthorised. Only Masteradmin can approve delete requests.']);
    }

    // Fetch the request
    $rq = $db->prepare("
        SELECT ddr.*, dr.document_number, dr.document_name, dr.created_by_emp_id
        FROM document_delete_requests ddr
        JOIN document_records dr ON ddr.document_id = dr.id
        WHERE ddr.id = ? AND ddr.status = 'pending'
        LIMIT 1
    ");
    $rq->bind_param("i", $request_id);
    $rq->execute();
    $req = $rq->get_result()->fetch_assoc();
    if (!$req) { json_out(['success' => false, 'message' => 'Request not found or already resolved.']); }

    $doc_id = (int)$req['document_id'];

    // Delete dependent rows first (forwards history), then the document
    $db->begin_transaction();
    try {
        $df = $db->prepare("DELETE FROM document_forwards WHERE document_id = ?");
        $df->bind_param("i", $doc_id);
        $df->execute();

        $dd = $db->prepare("DELETE FROM document_records WHERE id = ?");
        $dd->bind_param("i", $doc_id);
        $dd->execute();
        if ($db->affected_rows < 1) { throw new Exception('Document record not found during deletion.'); }

        // Mark request approved
        $upd = $db->prepare("
            UPDATE document_delete_requests
            SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), admin_note = ?
            WHERE id = ?
        ");
        $upd->bind_param("isi", $reviewer, $admin_note, $request_id);
        $upd->execute();

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        json_out(['success' => false, 'message' => 'Approval failed: ' . $e->getMessage()]);
    }

    // Notify the original requester
    $doc_label = $req['document_number'] . ' — ' . $req['document_name'];
    $notif_msg = "Your delete request for document \"{$doc_label}\" has been APPROVED and the document has been permanently deleted.";
    $type = 'delete_approved';
    $ns = $db->prepare("
        INSERT INTO document_notifications (recipient_emp_id, type, reference_id, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $requester_emp = (int)$req['requested_by'];
    $ns->bind_param("isis", $requester_emp, $type, $request_id, $notif_msg);
    $ns->execute();

    json_out(['success' => true, 'message' => 'Document deleted and requester notified.']);
}

// ─────────────────────────────────────────────────────────────
// REJECT DELETE  (Masteradmin only)
// ─────────────────────────────────────────────────────────────
if ($action === 'reject_delete') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $admin_note = trim($_POST['admin_note'] ?? '');
    $reviewer   = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);

    if (!$request_id) { json_out(['success' => false, 'message' => 'Invalid request ID.']); }

    $admin_ids = getMasteradminIds($db);
    if (!in_array($reviewer, $admin_ids)) {
        json_out(['success' => false, 'message' => 'Unauthorised. Only Masteradmin can reject delete requests.']);
    }

    $rq = $db->prepare("
        SELECT ddr.*, dr.document_number, dr.document_name
        FROM document_delete_requests ddr
        JOIN document_records dr ON ddr.document_id = dr.id
        WHERE ddr.id = ? AND ddr.status = 'pending'
        LIMIT 1
    ");
    $rq->bind_param("i", $request_id);
    $rq->execute();
    $req = $rq->get_result()->fetch_assoc();
    if (!$req) { json_out(['success' => false, 'message' => 'Request not found or already resolved.']); }

    $upd = $db->prepare("
        UPDATE document_delete_requests
        SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), admin_note = ?
        WHERE id = ?
    ");
    $upd->bind_param("isi", $reviewer, $admin_note, $request_id);
    $upd->execute();

    // Notify requester
    $doc_label = $req['document_number'] . ' — ' . $req['document_name'];
    $notif_msg = "Your delete request for document \"{$doc_label}\" has been REJECTED." .
                 ($admin_note ? " Admin note: {$admin_note}" : '');
    $type = 'delete_rejected';
    $ns = $db->prepare("
        INSERT INTO document_notifications (recipient_emp_id, type, reference_id, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $requester_emp = (int)$req['requested_by'];
    $ns->bind_param("isis", $requester_emp, $type, $request_id, $notif_msg);
    $ns->execute();

    json_out(['success' => true, 'message' => 'Delete request rejected and requester notified.']);
}

// ─────────────────────────────────────────────────────────────
// GET DELETE REQUESTS  (Masteradmin: list all pending)
// ─────────────────────────────────────────────────────────────
if ($action === 'get_delete_requests') {
    $reviewer  = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
    $admin_ids = getMasteradminIds($db);
    if (!in_array($reviewer, $admin_ids)) {
        json_out(['success' => false, 'message' => 'Unauthorised.']);
    }

    $filter = trim($_GET['filter'] ?? 'pending');
    if (!in_array($filter, ['pending','approved','rejected','all'])) { $filter = 'pending'; }
    $where = $filter === 'all' ? '' : "WHERE ddr.status = '{$filter}'";

    $rows = $db->query("
        SELECT ddr.id, ddr.document_id, ddr.reason, ddr.status, ddr.created_at,
               ddr.admin_note, ddr.reviewed_at,
               dr.document_number, dr.document_name, dr.kind,
               CONCAT(TRIM(e_req.first_name),' ',TRIM(e_req.last_name)) AS requester_name,
               CONCAT(TRIM(e_rev.first_name),' ',TRIM(e_rev.last_name)) AS reviewer_name
        FROM document_delete_requests ddr
        JOIN document_records dr   ON ddr.document_id  = dr.id
        JOIN employee e_req        ON ddr.requested_by = e_req.emp_id
        LEFT JOIN employee e_rev   ON ddr.reviewed_by  = e_rev.emp_id
        {$where}
        ORDER BY ddr.created_at DESC
        LIMIT 200
    ")->fetch_all(MYSQLI_ASSOC);

    json_out(['success' => true, 'requests' => $rows]);
}

// ─────────────────────────────────────────────────────────────
// GET USER NOTIFICATIONS  (current user's unread + recent)
// ─────────────────────────────────────────────────────────────
if ($action === 'get_delete_notifications') {
    $emp_id = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
    if (!$emp_id) { json_out(['success' => false, 'message' => 'Not authenticated.']); }

    $rows = $db->prepare("
        SELECT id, type, reference_id, message, is_read, created_at
        FROM document_notifications
        WHERE recipient_emp_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $rows->bind_param("i", $emp_id);
    $rows->execute();
    $notifications = $rows->get_result()->fetch_all(MYSQLI_ASSOC);
    $unread = count(array_filter($notifications, fn($n) => !$n['is_read']));

    json_out(['success' => true, 'notifications' => $notifications, 'unread' => $unread]);
}

// ─────────────────────────────────────────────────────────────
// MARK NOTIFICATIONS READ
// ─────────────────────────────────────────────────────────────
if ($action === 'mark_notifications_read') {
    $emp_id = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
    if (!$emp_id) { json_out(['success' => false, 'message' => 'Not authenticated.']); }
    $db->prepare("UPDATE document_notifications SET is_read = 1 WHERE recipient_emp_id = ? AND is_read = 0")
       ->bind_param("i", $emp_id);
    // Re-prepare properly
    $ms = $db->prepare("UPDATE document_notifications SET is_read = 1 WHERE recipient_emp_id = ?");
    $ms->bind_param("i", $emp_id);
    $ms->execute();
    json_out(['success' => true]);
}

// ─────────────────────────────────────────────────────────────
// CHECK DELETE REQUEST STATUS  (requester polls their own request)
// ─────────────────────────────────────────────────────────────
if ($action === 'check_delete_request') {
    $doc_id = (int)($_GET['doc_id'] ?? 0);
    $emp_id = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
    if (!$doc_id || !$emp_id) { json_out(['success' => false, 'message' => 'Invalid params.']); }

    $st = $db->prepare("
        SELECT status FROM document_delete_requests
        WHERE document_id = ? AND requested_by = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $st->bind_param("ii", $doc_id, $emp_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    json_out(['success' => true, 'status' => $row['status'] ?? null]);
}

json_out(['success' => false, 'message' => 'Unknown action.']);