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
// function toMySQLDateTime($input) {
//     if (empty($input)) return null;
//     $ts = strtotime(str_replace('T', ' ', $input));
//     return $ts ? date('Y-m-d H:i:s', $ts) : null;
// }

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
               CONCAT(TRIM(er.first_name), ' ', TRIM(er.last_name)) AS received_by_name,
               COALESCE(s1.section_name, o_from.office_name) AS from_section_name,
               s2.section_name  AS to_section_name,
               us1.unit_name    AS from_unit_name,
               us2.unit_name    AS to_unit_name,
               o_from.office_name AS from_office_name,
               (e1.section_id IS NULL AND e1.office_id IS NOT NULL) AS from_is_office
        FROM document_records dr
        LEFT JOIN document_types dt  ON dr.document_type_id         = dt.id
        LEFT JOIN employee       e1  ON dr.forwarded_by_emp_id      = e1.emp_id
        LEFT JOIN employee       e2  ON dr.forwarded_to_emp_id      = e2.emp_id
        LEFT JOIN employee       er  ON dr.received_by_emp_id       = er.emp_id
        LEFT JOIN section        s1  ON dr.from_section_id          = s1.section_id
        LEFT JOIN section        s2  ON dr.forwarded_to_section_id  = s2.section_id
        LEFT JOIN unit_section   us1 ON dr.from_unit_id             = us1.unit_id
        LEFT JOIN unit_section   us2 ON dr.forwarded_to_unit_id     = us2.unit_id
        LEFT JOIN office         o_from ON e1.office_id             = o_from.office_id
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
               o.office_name    AS to_office_name,
               CONCAT(TRIM(e_rc.first_name), ' ', TRIM(e_rc.last_name)) AS received_by_name
        FROM document_forwards df
        LEFT JOIN employee     e_by ON df.fwd_by_emp_id      = e_by.emp_id
        LEFT JOIN employee     e_to ON df.fwd_to_emp_id      = e_to.emp_id
        LEFT JOIN employee     e_rc ON df.received_by_emp_id = e_rc.emp_id
        LEFT JOIN section      s    ON df.fwd_to_section_id  = s.section_id
        LEFT JOIN unit_section u    ON df.fwd_to_unit_id     = u.unit_id
        LEFT JOIN office       o    ON df.fwd_to_office_id   = o.office_id
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

    $counts = ['incoming' => 0, 'outgoing' => 0, 'external' => 0, 'total' => 0];

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

    if (!in_array($kind, ['incoming', 'outgoing', 'external'])) {
        json_out(['success' => false, 'message' => 'Invalid kind value.']);
    }

    $forwarded_by_emp_id = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0) ?: null;
    // Use NOW() directly in SQL — session tz is already SET to +08:00 above,
    // so MariaDB's NOW() returns correct PHT time. Never use PHP date() here
    // because the PHP server may run in UTC, causing an 8-hour offset on insert.

    // ── Resolve creator's section/unit so IMO Office staff are tracked correctly ──
    // Employees assigned to the Manager's Office have section_id = NULL but have
    // office_id set. We read their section_id and unit_section_id directly so the
    // document record always knows where it originated — even for IMO staff.
    $creator_section_id = null;
    $creator_unit_id    = null;
    if ($forwarded_by_emp_id) {
        $cr = $db->prepare("SELECT section_id, unit_section_id FROM employee WHERE emp_id = ? LIMIT 1");
        $cr->bind_param("i", $forwarded_by_emp_id);
        $cr->execute();
        $crrow = $cr->get_result()->fetch_assoc();
        $creator_section_id = ($crrow['section_id']      ?? null) ?: null;
        $creator_unit_id    = ($crrow['unit_section_id'] ?? null) ?: null;
    }

    $null_str  = null;
    $null_int1 = null;
    $null_int2 = null;
    $empty_str = '';
    $null_int3 = null;
    $null_int4 = null;

    $stmt = $db->prepare("
        INSERT INTO document_records
            (kind, document_number, document_type_id, document_name,
             forwarded_by_emp_id, forwarded_by_name,
             from_section_id, from_unit_id,
             forwarded_to_emp_id, forwarded_to,
             forwarded_to_section_id, forwarded_to_unit_id,
             date_forwarded, date_received, status, remarks,
             created_by_emp_id)
        VALUES (?,?,?,?, ?,?,?,?, ?,?,?,?, NOW(),NOW(),?,?, ?)
    ");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
    }

    // Type string: 15 params (date_forwarded & date_received now use NOW() in SQL)
    // s,s,i,s, i,s,i,i, i,s,i,i, s,s,i
    $stmt->bind_param(
        "ssisisiiississi",
        $kind, $document_num, $type_id, $doc_name,
        $forwarded_by_emp_id, $null_str,
        $creator_section_id, $creator_unit_id,   // resolved from employee — NULL for IMO staff
        $null_int1, $empty_str,
        $null_int2, $null_int3,
        $status, $remarks,
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

    // ── Ownership / permission guard ─────────────────────────────────────────
    // Only the document creator OR a Masteradmin may edit a document.
    $caller_emp = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
    if (!$caller_emp) {
        json_out(['success' => false, 'message' => 'Not authenticated.']);
    }
    $own_chk = $db->prepare("SELECT created_by_emp_id FROM document_records WHERE id = ? LIMIT 1");
    $own_chk->bind_param("i", $id);
    $own_chk->execute();
    $own_row = $own_chk->get_result()->fetch_assoc();
    if (!$own_row) {
        json_out(['success' => false, 'message' => 'Document not found.']);
    }
    $is_creator   = ((int)($own_row['created_by_emp_id'] ?? 0) === $caller_emp);
    $admin_ids_u  = getMasteradminIds($db);
    $is_admin_u   = in_array($caller_emp, $admin_ids_u);
    if (!$is_creator && !$is_admin_u) {
        json_out(['success' => false, 'message' => 'Permission denied. Only the document creator or a Masteradmin can edit this document.']);
    }
    // ────────────────────────────────────────────────────────────────────────

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

    if (!in_array($kind, ['incoming', 'outgoing', 'external'])) {
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

    if (!$doc_id) {
        json_out(['success' => false, 'message' => 'Document ID missing']);
    }
    if (!$fwd_by_emp) {
        json_out(['success' => false, 'message' => 'Not authenticated.']);
    }

    // ── Ownership / permission guard ─────────────────────────────────────────
    // The document creator, a Masteradmin, OR a manager/office-staff of the
    // office the document was forwarded to may forward it onward.
    $fwd_own_chk = $db->prepare("SELECT created_by_emp_id, forwarded_to_section_id, forwarded_to_office_id FROM document_records WHERE id = ? LIMIT 1");
    $fwd_own_chk->bind_param("i", $doc_id);
    $fwd_own_chk->execute();
    $fwd_own_row = $fwd_own_chk->get_result()->fetch_assoc();
    if (!$fwd_own_row) {
        json_out(['success' => false, 'message' => 'Document not found.']);
    }
    $is_fwd_creator  = ((int)($fwd_own_row['created_by_emp_id'] ?? 0) === $fwd_by_emp);
    $admin_ids_f     = getMasteradminIds($db);
    $is_admin_f      = in_array($fwd_by_emp, $admin_ids_f);
    $is_office_recip = isOfficeRecipient($fwd_own_row, $fwd_by_emp, $db);
    if (!$is_fwd_creator && !$is_admin_f && !$is_office_recip) {
        json_out(['success' => false, 'message' => 'Permission denied. Only the document creator, a Masteradmin, or the assigned office recipient can forward this document.']);
    }
    // ────────────────────────────────────────────────────────────────────────

    $fwd_to_unit = (int)($_POST['fwd_to_unit_id'] ?? 0) ?: null;
    $fwd_to_sec  = (int)($_POST['fwd_to_section_id'] ?? 0) ?: null;
    $fwd_to_off  = (int)($_POST['fwd_to_office_id'] ?? 0) ?: null;
    $fwd_remarks  = trim($_POST['fwd_remarks'] ?? '');
    $forward_to   = trim($_POST['forward_to'] ?? 'section');

    // Stamp current PHT time using SQL NOW() — the session tz is already set to +08:00,
    // so NOW() returns PHT and MariaDB stores/reads it correctly. Using PHP date() here
    // can cause an 8-hour mismatch if the PHP server clock is in a different timezone.
    $fwd_date = 'NOW()';

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
    // Use NOW() in SQL so MariaDB uses session tz (+08:00) -- fixes 8-hour offset.
    $istmt = $db->prepare("
        INSERT INTO document_forwards
            (document_id, fwd_by_emp_id, fwd_to_emp_id, fwd_to_section_id, fwd_to_unit_id, fwd_to_office_id, fwd_date, fwd_remarks)
        VALUES (?,?,?,?,?,?,NOW(),?)
    ");
    if (!$istmt) {
        json_out(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
    }
    $istmt->bind_param("iiiiiis",
        $doc_id, $fwd_by_emp, $fwd_to_emp,
        $fwd_to_sec, $fwd_to_unit, $fwd_to_off,
        $fwd_remarks
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
            date_forwarded           = NOW(),
            date_received            = NULL,
            status                   = 'pending'
        WHERE id = ?
    ");
    // forwarded_to_section_id: after migration references section.section_id directly
    $ustmt->bind_param("iiiisi",
        $fwd_to_emp, $fwd_to_sec, $fwd_to_unit, $fwd_to_off,
        $fwd_label, $doc_id
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

if ($action === 'update_status') {
    $id     = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if (!$id || !in_array($status, ['pending', 'received', 'returned', 'completed', 'archived'])) {
        json_out(['success' => false, 'message' => 'Invalid status or ID.']);
    }

    // ── ADDED: Ownership / permission guard ──────────────────────────────────
    $caller_emp = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
    if (!$caller_emp) {
        json_out(['success' => false, 'message' => 'Not authenticated.']);
    }
    $own_chk = $db->prepare("SELECT id, created_by_emp_id, forwarded_to_office_id FROM document_records WHERE id = ? LIMIT 1");
    $own_chk->bind_param("i", $id);
    $own_chk->execute();
    $own_row = $own_chk->get_result()->fetch_assoc();
    if (!$own_row) {
        json_out(['success' => false, 'message' => 'Document not found.']);
    }
    $is_creator      = ((int)($own_row['created_by_emp_id'] ?? 0) === $caller_emp);
    $is_admin        = in_array($caller_emp, getMasteradminIds($db));
    $is_office_recip = isOfficeRecipient($own_row, $caller_emp, $db);
    if (!$is_creator && !$is_admin && !$is_office_recip) {
        json_out(['success' => false, 'message' => 'Permission denied. Only the document creator, a Masteradmin, or the assigned office recipient can change this status.']);
    }
    // ─────────────────────────────────────────────────────────────────────────

    if ($status === 'received') {
        // Record who received the document (document level)
        $stmt = $db->prepare("UPDATE document_records SET status = ?, date_received = NOW(), received_by_emp_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $status, $caller_emp, $id);

        // Also stamp the latest document_forwards row so each hop tracks its own receiver
        $fwd_stamp = $db->prepare("
            UPDATE document_forwards
            SET received_by_emp_id = ?, received_at = NOW()
            WHERE document_id = ?
              AND received_by_emp_id IS NULL
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($fwd_stamp) {
            $fwd_stamp->bind_param("ii", $caller_emp, $id);
            $fwd_stamp->execute();
        }
    } elseif ($status === 'pending') {
        // Clear receiver when reset to pending
        $stmt = $db->prepare("UPDATE document_records SET status = ?, date_received = NULL, received_by_emp_id = NULL WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
    } else {
        $stmt = $db->prepare("UPDATE document_records SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
    }

    $success = $stmt->execute();
    json_out([
        'success' => $success,
        'message' => $success ? 'Status updated' : 'Update failed: ' . $stmt->error,
    ]);
}

// ─────────────────────────────────────────────────────────────
// Helper: check if $emp_id is the manager or office staff of
// the office that this document was forwarded to.
// Allows IMO manager/staff to receive and re-forward documents.
// ─────────────────────────────────────────────────────────────
function isOfficeRecipient(array $doc_row, int $emp_id, $db): bool {
    $fwd_to_office = (int)($doc_row['forwarded_to_office_id'] ?? 0);
    if (!$fwd_to_office || !$emp_id) return false;

    // Is this employee the designated office manager?
    $chk = $db->prepare("SELECT 1 FROM office WHERE office_id = ? AND manager_emp_id = ? LIMIT 1");
    $chk->bind_param("ii", $fwd_to_office, $emp_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) return true;

    // Is this employee flagged as manager or manager_office_staff in that office?
    $chk2 = $db->prepare("
        SELECT 1 FROM employee
        WHERE emp_id = ? AND office_id = ?
          AND (is_manager = 1 OR is_manager_office_staff = 1)
        LIMIT 1
    ");
    $chk2->bind_param("ii", $emp_id, $fwd_to_office);
    $chk2->execute();
    return $chk2->get_result()->num_rows > 0;
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

// ─────────────────────────────────────────────────────────────
// ARCHIVE DAILY  – move today's documents to document_archive
// Called by the cron / midnight scheduler OR manually by admin
// ─────────────────────────────────────────────────────────────
if ($action === 'archive_daily') {
    // Only masteradmins (or a server-side cron token) may trigger this
    $caller = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
    $is_cron_token = (($_POST['cron_token'] ?? '') === getenv('ARCHIVE_CRON_TOKEN') && getenv('ARCHIVE_CRON_TOKEN') !== '');

    if (!$is_cron_token) {
        // Check masteradmin via users table
        $chk = $db->prepare("SELECT 1 FROM users u JOIN user_roles ur ON u.role_id=ur.id WHERE u.employee_id=? AND ur.id=1 LIMIT 1");
        if (!$chk) { json_out(['success' => false, 'message' => 'DB error checking permission.']); }
        $chk->bind_param("i", $caller);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            json_out(['success' => false, 'message' => 'Unauthorised. Only Masteradmin can trigger archiving.']);
        }
    }

    // Ensure document_archive table exists
    $db->query("
        CREATE TABLE IF NOT EXISTS document_archive (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            original_id      INT UNSIGNED NOT NULL,
            archive_date     DATE         NOT NULL COMMENT 'The calendar day being archived (PHT)',
            kind             VARCHAR(20)  NOT NULL,
            document_number  VARCHAR(100) NOT NULL,
            document_name    VARCHAR(255) NOT NULL,
            document_type    VARCHAR(100),
            status           VARCHAR(50),
            forwarded_by     VARCHAR(150),
            from_section     VARCHAR(150),
            to_section       VARCHAR(150),
            date_forwarded   DATETIME,
            remarks          TEXT,
            snapshot_json    LONGTEXT     COMMENT 'Full JSON snapshot of document_records row + joins',
            archived_by_emp  INT UNSIGNED,
            archived_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_archive_date (archive_date),
            INDEX idx_original_id  (original_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Determine archive date — allow explicit POST param for manual runs,
    // otherwise default to yesterday PHT (when cron fires just after midnight).
    $force_date = trim($_POST['archive_date'] ?? '');
    if ($force_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $force_date)) {
        $arc_date = $force_date;
    } else {
        $arc_date = date('Y-m-d', strtotime('-1 day'));
    }

    // ── Capture ALL documents that belong to this date ──────────────────────
    // A document "belongs" to a date if ANY of the following is true:
    //   1. It was CREATED on that date (created_at)           ← catches new unforwarded docs
    //   2. It was FORWARDED on that date (date_forwarded)     ← catches forwarded docs
    //      but exclude the zero-date sentinel (0000-00-00)    ← MariaDB stores '0000-00-00 00:00:00'
    //      when no forwarding has happened yet
    // Using CONVERT_TZ so the comparison is always done in PHT (+08:00)
    // regardless of what the server/MariaDB session tz is set to.
    $sel = $db->prepare("
        SELECT dr.*,
               dt.type_name,
               COALESCE(
                   CONCAT(TRIM(fbe.first_name),' ',TRIM(fbe.last_name)),
                   dr.forwarded_by_name
               ) AS forwarded_by_name,
               COALESCE(s1.section_name, o_from.office_name) AS from_section_name,
               s2.section_name AS to_section_name,
               us1.unit_name   AS from_unit_name,
               us2.unit_name   AS to_unit_name
        FROM document_records dr
        LEFT JOIN document_types dt    ON dr.document_type_id         = dt.id
        LEFT JOIN employee       fbe   ON dr.forwarded_by_emp_id      = fbe.emp_id
        LEFT JOIN section        s1    ON dr.from_section_id          = s1.section_id
        LEFT JOIN section        s2    ON dr.forwarded_to_section_id  = s2.section_id
        LEFT JOIN unit_section   us1   ON dr.from_unit_id             = us1.unit_id
        LEFT JOIN unit_section   us2   ON dr.forwarded_to_unit_id     = us2.unit_id
        LEFT JOIN office         o_from ON fbe.office_id              = o_from.office_id
        WHERE (
            -- FIX: Use DATE(col) directly — session tz is already '+08:00'
            -- so CONVERT_TZ was double-converting and skipping valid rows.
            DATE(dr.created_at) = ?
            OR
            (
                dr.date_forwarded IS NOT NULL
                AND dr.date_forwarded != '0000-00-00 00:00:00'
                AND DATE(dr.date_forwarded) = ?
            )
        )
        AND dr.id NOT IN (
            SELECT original_id FROM document_archive WHERE archive_date = ?
        )
    ");
    $sel->bind_param("sss", $arc_date, $arc_date, $arc_date);
    $sel->execute();
    $rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!$rows) {
        json_out(['success' => true, 'message' => "No new documents to archive for {$arc_date}.", 'archived' => 0]);
    }

    $ins = $db->prepare("
        INSERT INTO document_archive
            (original_id, archive_date, kind, document_number, document_name,
             document_type, status, forwarded_by, from_section, to_section,
             date_forwarded, remarks, snapshot_json, archived_by_emp, archived_at)
        VALUES (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?, NOW())
    ");

    $archived_count = 0;
    foreach ($rows as $doc) {
        // Sanitise date_forwarded: treat zero-date sentinel as NULL
        $date_fwd = (!empty($doc['date_forwarded']) && $doc['date_forwarded'] !== '0000-00-00 00:00:00')
                    ? $doc['date_forwarded']
                    : null;

        // Resolve best display value for "forwarded by"
        $fwd_by = !empty($doc['forwarded_by_name'])
                  ? trim($doc['forwarded_by_name'])
                  : (!empty($doc['forwarded_by_name_raw']) ? trim($doc['forwarded_by_name_raw']) : null);

        $ins->bind_param(
            "issss" . "sssss" . "sssi",
            $doc['id'],
            $arc_date,
            $doc['kind'],
            $doc['document_number'],
            $doc['document_name'],
            $doc['type_name'],
            $doc['status'],
            $fwd_by,
            $doc['from_section_name'],
            $doc['to_section_name'],
            $date_fwd,
            $doc['remarks'],
            json_encode($doc),
            $caller
        );
        if ($ins->execute()) { $archived_count++; }
    }

    json_out(['success' => true, 'message' => "Archived {$archived_count} document(s) for {$arc_date}.", 'archived' => $archived_count, 'date' => $arc_date]);
}

// ─────────────────────────────────────────────────────────────
// GET ARCHIVE  – retrieve archived documents for a given date or range
// ─────────────────────────────────────────────────────────────
if ($action === 'get_archive') {
    $arc_date   = trim($_GET['archive_date'] ?? date('Y-m-d', strtotime('-1 day')));
    $kind_filter = trim($_GET['kind'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $arc_date)) {
        json_out(['success' => false, 'message' => 'Invalid date format.']);
    }

    // Ensure table exists (read-only guard)
    $db->query("
        CREATE TABLE IF NOT EXISTS document_archive (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            original_id      INT UNSIGNED NOT NULL,
            archive_date     DATE         NOT NULL,
            kind             VARCHAR(20)  NOT NULL,
            document_number  VARCHAR(100) NOT NULL,
            document_name    VARCHAR(255) NOT NULL,
            document_type    VARCHAR(100),
            status           VARCHAR(50),
            forwarded_by     VARCHAR(150),
            from_section     VARCHAR(150),
            to_section       VARCHAR(150),
            date_forwarded   DATETIME,
            remarks          TEXT,
            snapshot_json    LONGTEXT,
            archived_by_emp  INT UNSIGNED,
            archived_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_archive_date (archive_date),
            INDEX idx_original_id  (original_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $where_parts = ["archive_date = ?"];
    $bind_types  = "s";
    $bind_vals   = [$arc_date];

    if ($kind_filter && in_array($kind_filter, ['incoming','outgoing','external'])) {
        $where_parts[] = "kind = ?";
        $bind_types   .= "s";
        $bind_vals[]   = $kind_filter;
    }

    $where_sql = implode(' AND ', $where_parts);
    $q = $db->prepare("
        SELECT id, original_id, archive_date, kind, document_number, document_name,
               document_type, status, forwarded_by, from_section, to_section,
               date_forwarded, remarks, archived_at
        FROM document_archive
        WHERE {$where_sql}
        ORDER BY date_forwarded ASC
    ");
    $q->bind_param($bind_types, ...$bind_vals);
    $q->execute();
    $docs = $q->get_result()->fetch_all(MYSQLI_ASSOC);

    json_out(['success' => true, 'documents' => $docs, 'date' => $arc_date, 'count' => count($docs)]);
}

// ─────────────────────────────────────────────────────────────
// GET ARCHIVE DAYS  – list of distinct dates that have archives
// ─────────────────────────────────────────────────────────────
if ($action === 'get_archive_days') {
    $db->query("
        CREATE TABLE IF NOT EXISTS document_archive (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            original_id INT UNSIGNED NOT NULL, archive_date DATE NOT NULL,
            kind VARCHAR(20), document_number VARCHAR(100), document_name VARCHAR(255),
            document_type VARCHAR(100), status VARCHAR(50), forwarded_by VARCHAR(150),
            from_section VARCHAR(150), to_section VARCHAR(150), date_forwarded DATETIME,
            remarks TEXT, snapshot_json LONGTEXT, archived_by_emp INT UNSIGNED,
            archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_archive_date (archive_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $days = $db->query("
        SELECT archive_date, COUNT(*) AS total,
               SUM(kind='incoming') AS incoming,
               SUM(kind='outgoing') AS outgoing,
               SUM(kind='external') AS external
        FROM document_archive
        GROUP BY archive_date
        ORDER BY archive_date DESC
        LIMIT 90
    ");
    $result = $days ? $days->fetch_all(MYSQLI_ASSOC) : [];
    json_out(['success' => true, 'days' => $result]);
}

// ─────────────────────────────────────────────────────────────
// CHECK MIDNIGHT STATUS – has today's auto-archive already run?
// Returns:
//   already_ran : true              → archive for yesterday is done, nothing to do
//   already_ran : false, fire_now : true  → archive missed, JS fires immediately
//   already_ran : false, fire_now : false → not yet midnight, arm timer for tonight
// ─────────────────────────────────────────────────────────────
if ($action === 'check_midnight_status') {
    $db->query("
        CREATE TABLE IF NOT EXISTS `document_archive_log` (
            `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `run_date`     DATE         NOT NULL UNIQUE,
            `archived`     INT UNSIGNED NOT NULL DEFAULT 0,
            `triggered_by` VARCHAR(20)  NOT NULL DEFAULT 'auto',
            `run_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Session tz is already '+08:00', so NOW() returns PHT wall-clock time.
    $pht_row = $db->query("
        SELECT DATE(NOW())                  AS today_pht,
               DATE(NOW() - INTERVAL 1 DAY) AS yesterday_pht,
               NOW()                        AS now_pht
    ");
    $pht       = $pht_row ? $pht_row->fetch_assoc() : [];
    $today     = $pht['today_pht']     ?? date('Y-m-d');
    $yesterday = $pht['yesterday_pht'] ?? date('Y-m-d', strtotime('-1 day'));

    // Archive is keyed to yesterday (the day that just finished).
    $row = $db->query("SELECT id FROM document_archive_log WHERE run_date = '$yesterday' LIMIT 1");
    $ran = $row && $row->num_rows > 0;

    // fire_now = true when archive hasn't run yet — JS fires immediately on page load.
    // This catches the case where cron missed and a user opens the page in the morning.
    $fire_now = !$ran;

    json_out([
        'success'     => true,
        'already_ran' => $ran,
        'fire_now'    => $fire_now,
        'today'       => $today,
        'yesterday'   => $yesterday,
        'server_time' => $pht['now_pht'] ?? date('Y-m-d H:i:s'),
    ]);
}

// ─────────────────────────────────────────────────────────────
// MIDNIGHT ARCHIVE  – copy documents to archive (NO delete)
// Documents stay in document_records untouched.
// Callable by: JS auto-trigger, masteradmin manual, or cron.
// ─────────────────────────────────────────────────────────────
if ($action === 'midnight_archive') {
    $caller       = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
    $is_cron      = (($_POST['cron_token'] ?? '') === getenv('ARCHIVE_CRON_TOKEN') && getenv('ARCHIVE_CRON_TOKEN') !== '');
    $is_auto      = ($_POST['triggered_by'] ?? '') === 'auto';
    $triggered_by = $is_cron ? 'cron' : ($is_auto ? 'auto' : 'manual');

    // Only masteradmin can trigger manually (auto & cron are unrestricted)
    if (!$is_cron && !$is_auto) {
        $chk = $db->prepare("SELECT 1 FROM users u JOIN user_roles ur ON u.role_id=ur.id WHERE u.employee_id=? AND ur.id=1 LIMIT 1");
        if (!$chk) { json_out(['success' => false, 'message' => 'DB error checking permission.']); }
        $chk->bind_param("i", $caller);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            json_out(['success' => false, 'message' => 'Unauthorised. Only Masteradmin can trigger archiving.']);
        }
    }

    // Ensure tables exist
    $db->query("
        CREATE TABLE IF NOT EXISTS `document_archive` (
            `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `original_id`     INT UNSIGNED NOT NULL,
            `archive_date`    DATE         NOT NULL,
            `kind`            VARCHAR(20)  NOT NULL,
            `document_number` VARCHAR(100) NOT NULL,
            `document_name`   VARCHAR(255) NOT NULL,
            `document_type`   VARCHAR(100) DEFAULT NULL,
            `status`          VARCHAR(50)  DEFAULT NULL,
            `forwarded_by`    VARCHAR(150) DEFAULT NULL,
            `from_section`    VARCHAR(150) DEFAULT NULL,
            `to_section`      VARCHAR(150) DEFAULT NULL,
            `date_forwarded`  DATETIME     DEFAULT NULL,
            `remarks`         TEXT         DEFAULT NULL,
            `snapshot_json`   LONGTEXT     DEFAULT NULL,
            `archived_by_emp` INT UNSIGNED DEFAULT NULL,
            `archived_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_archive_date` (`archive_date`),
            INDEX `idx_original_id`  (`original_id`),
            INDEX `idx_kind`         (`kind`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db->query("
        CREATE TABLE IF NOT EXISTS `document_archive_log` (
            `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `run_date`     DATE         NOT NULL UNIQUE,
            `archived`     INT UNSIGNED NOT NULL DEFAULT 0,
            `triggered_by` VARCHAR(20)  NOT NULL DEFAULT 'auto',
            `run_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Which date to archive
    // BUGFIX: Use PHT date from MariaDB session (SET time_zone='+08:00' already applied above),
    // not PHP date() which may use server UTC and produce wrong dates around midnight PHT.
    $pht_row  = $db->query("SELECT DATE(NOW()) AS today_pht, DATE(NOW() - INTERVAL 1 DAY) AS yesterday_pht");
    $pht_data = $pht_row ? $pht_row->fetch_assoc() : [];
    $pht_today     = $pht_data['today_pht']     ?? date('Y-m-d');
    $pht_yesterday = $pht_data['yesterday_pht'] ?? date('Y-m-d', strtotime('-1 day'));

    $force_date = trim($_POST['archive_date'] ?? '');
    if ($triggered_by === 'manual' && $force_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $force_date)) {
        $arc_date = $force_date;
    } elseif ($triggered_by === 'auto' || $triggered_by === 'cron') {
        $arc_date = $pht_yesterday;   // auto/cron: archive yesterday's docs
    } else {
        $arc_date = $pht_today;
    }

    // Idempotency: skip if already ran for this date (except manual re-runs)
    // BUGFIX: Query uses $arc_date (already PHT-correct), not today's server date.
    $already = $db->query("SELECT id FROM document_archive_log WHERE run_date = '$arc_date' LIMIT 1");
    if ($already && $already->num_rows > 0 && $triggered_by !== 'manual') {
        json_out(['success' => true, 'message' => "Already archived for {$arc_date}. Skipped.", 'archived' => 0, 'skipped' => true]);
    }

    // Fetch all documents belonging to this date (created OR forwarded)
    $sel = $db->prepare("
        SELECT dr.*,
               dt.type_name,
               COALESCE(
                   CONCAT(TRIM(fbe.first_name),' ',TRIM(fbe.last_name)),
                   dr.forwarded_by_name
               ) AS resolved_fwd_by,
               COALESCE(s1.section_name, o_from.office_name) AS from_section_name,
               s2.section_name AS to_section_name,
               us1.unit_name   AS from_unit_name,
               us2.unit_name   AS to_unit_name
        FROM document_records dr
        LEFT JOIN document_types dt    ON dr.document_type_id         = dt.id
        LEFT JOIN employee       fbe   ON dr.forwarded_by_emp_id      = fbe.emp_id
        LEFT JOIN section        s1    ON dr.from_section_id          = s1.section_id
        LEFT JOIN section        s2    ON dr.forwarded_to_section_id  = s2.section_id
        LEFT JOIN unit_section   us1   ON dr.from_unit_id             = us1.unit_id
        LEFT JOIN unit_section   us2   ON dr.forwarded_to_unit_id     = us2.unit_id
        LEFT JOIN office         o_from ON fbe.office_id              = o_from.office_id
        WHERE (
            -- FIX: Use DATE(col) directly. The session timezone is already SET to
            -- '+08:00' above, so MariaDB evaluates TIMESTAMP columns in PHT.
            -- CONVERT_TZ(col, '+00:00', '+08:00') was causing a double-conversion
            -- (MariaDB was reading the stored UTC value in PHT session context, then
            -- converting again from '+00:00' → '+08:00'), which produced wrong dates
            -- and caused valid rows to be skipped.
            DATE(dr.created_at) = ?
            OR (
                dr.date_forwarded IS NOT NULL
                AND dr.date_forwarded != '0000-00-00 00:00:00'
                AND DATE(dr.date_forwarded) = ?
            )
        )
        AND dr.id NOT IN (
            SELECT original_id FROM document_archive WHERE archive_date = ?
        )
    ");
    $sel->bind_param("sss", $arc_date, $arc_date, $arc_date);
    $sel->execute();
    $rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!$rows) {
        // Only log "0 archived" if there truly were no docs — don't block future runs
        // for dates where docs may still exist but weren't matched (e.g. tz mismatch).
        // Use INSERT IGNORE so a duplicate run_date doesn't error out.
        $db->query("INSERT IGNORE INTO document_archive_log (run_date, archived, triggered_by) VALUES ('$arc_date', 0, '$triggered_by')");
        json_out(['success' => true, 'message' => "No new documents to archive for {$arc_date}.", 'archived' => 0]);
    }

    // Insert into document_archive, then DELETE from document_records inside a transaction
    // so the live table is only cleared if all rows archive successfully.
    $ins = $db->prepare("
        INSERT INTO document_archive
            (original_id, archive_date, kind, document_number, document_name,
             document_type, status, forwarded_by, from_section, to_section,
             date_forwarded, remarks, snapshot_json, archived_by_emp, archived_at)
        VALUES (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?, NOW())
    ");

    $archived_count = 0;
    $archived_ids   = [];   // collect IDs for the DELETE step

    $db->begin_transaction();
    try {
        foreach ($rows as $doc) {
            $date_fwd = (!empty($doc['date_forwarded']) && $doc['date_forwarded'] !== '0000-00-00 00:00:00')
                        ? $doc['date_forwarded'] : null;
            $fwd_by   = trim($doc['resolved_fwd_by'] ?? '');

            $ins->bind_param(
                "issss" . "sssss" . "sssi",
                $doc['id'],
                $arc_date,
                $doc['kind'],
                $doc['document_number'],
                $doc['document_name'],
                $doc['type_name'],
                $doc['status'],
                $fwd_by,
                $doc['from_section_name'],
                $doc['to_section_name'],
                $date_fwd,
                $doc['remarks'],
                json_encode($doc),
                $caller
            );
            if ($ins->execute()) {
                $archived_count++;
                $archived_ids[] = (int)$doc['id'];
            }
        }

        // BUGFIX: Delete archived records from the live table so it resets daily.
        // We delete only the rows we just successfully archived (by collected IDs),
        // and only after all inserts succeed — transaction ensures atomicity.
        $deleted_count = 0;
        if ($archived_ids) {
            $id_list = implode(',', $archived_ids);

            // Remove forwarding history first (FK dependency)
            $db->query("DELETE FROM document_forwards WHERE document_id IN ($id_list)");

            // Remove any pending delete requests for these docs
            $db->query("DELETE FROM document_delete_requests WHERE document_id IN ($id_list)");

            // Now delete the main records
            $del = $db->query("DELETE FROM document_records WHERE id IN ($id_list)");
            $deleted_count = $del ? (int)$db->affected_rows : 0;
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        json_out(['success' => false, 'message' => 'Archive transaction failed: ' . $e->getMessage()]);
    }

    // Log this run
    $log = $db->prepare("
        INSERT INTO document_archive_log (run_date, archived, triggered_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE archived = archived + ?, triggered_by = ?, run_at = NOW()
    ");
    $log->bind_param("sisss", $arc_date, $archived_count, $triggered_by, $archived_count, $triggered_by);
    $log->execute();

    json_out([
        'success'      => true,
        'message'      => "Archived {$archived_count} document(s) for {$arc_date}. {$deleted_count} record(s) removed from live table.",
        'archived'     => $archived_count,
        'deleted'      => $deleted_count,
        'date'         => $arc_date,
        'triggered_by' => $triggered_by,
    ]);
}

// ─────────────────────────────────────────────────────────────
// UPLOAD FILE  –  attach one or more files to a document
// POST: action=upload_file, document_id=<id>
// FILES: files[] (multipart)
// ─────────────────────────────────────────────────────────────
if ($action === 'upload_file') {
    $doc_id   = (int)($_POST['document_id'] ?? 0);
    $emp_id   = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);

    if (!$doc_id) {
        json_out(['success' => false, 'message' => 'Document ID is required.']);
    }

    // Verify document exists
    $chk = $db->prepare("SELECT id FROM document_records WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $doc_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        json_out(['success' => false, 'message' => 'Document not found.']);
    }

    // Upload directory — one level up from the actions file, inside /uploads/document_files/
    // Adjust $upload_dir to match your server layout if needed.
    $upload_dir = __DIR__ . '/../uploads/document_files/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0775, true)) {
            json_out(['success' => false, 'message' => 'Upload directory could not be created.']);
        }
    }

    // Allowed MIME types
    $allowed_mime = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'text/plain', 'text/csv',
    ];
    $max_size = 20 * 1024 * 1024; // 20 MB per file

    $saved    = [];
    $errors   = [];
    $uploaded = $_FILES['files'] ?? null;

    if (!$uploaded || !is_array($uploaded['name'])) {
        json_out(['success' => false, 'message' => 'No files received.']);
    }

    $count = count($uploaded['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($uploaded['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = $uploaded['name'][$i] . ': Upload error code ' . $uploaded['error'][$i];
            continue;
        }
        $orig_name = basename($uploaded['name'][$i]);
        $tmp_path  = $uploaded['tmp_name'][$i];
        $file_size = $uploaded['size'][$i];

        // Size guard
        if ($file_size > $max_size) {
            $errors[] = $orig_name . ': exceeds 20 MB limit.';
            continue;
        }

        // MIME guard (use finfo for reliability)
        $finfo     = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($tmp_path);
        if (!in_array($mime_type, $allowed_mime)) {
            $errors[] = $orig_name . ': file type not allowed (' . $mime_type . ').';
            continue;
        }

        // Generate a unique stored filename
        $ext         = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $stored_name = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
        $dest        = $upload_dir . $stored_name;

        if (!move_uploaded_file($tmp_path, $dest)) {
            $errors[] = $orig_name . ': could not be saved.';
            continue;
        }

        // Insert DB record
        $ins = $db->prepare("
            INSERT INTO document_files
                (document_id, original_name, stored_name, mime_type, file_size, uploaded_by, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $ins->bind_param("isssii", $doc_id, $orig_name, $stored_name, $mime_type, $file_size, $emp_id);
        if ($ins->execute()) {
            $saved[] = ['id' => $db->insert_id, 'original_name' => $orig_name, 'mime_type' => $mime_type, 'file_size' => $file_size];
        } else {
            // Clean up orphaned file
            @unlink($dest);
            $errors[] = $orig_name . ': DB insert failed.';
        }
    }

    json_out([
        'success' => count($saved) > 0,
        'saved'   => $saved,
        'errors'  => $errors,
        'message' => count($saved) . ' file(s) uploaded' . (count($errors) ? '; ' . count($errors) . ' error(s).' : '.'),
    ]);
}

// ─────────────────────────────────────────────────────────────
// GET FILES  –  list all attachments for a document
// GET: action=get_files&document_id=<id>
// ─────────────────────────────────────────────────────────────
if ($action === 'get_files') {
    $doc_id = (int)($_GET['document_id'] ?? 0);
    if (!$doc_id) {
        json_out(['success' => false, 'message' => 'Document ID required.']);
    }

    $stmt = $db->prepare("
        SELECT df.id, df.original_name, df.stored_name, df.mime_type, df.file_size, df.uploaded_at,
               CONCAT(TRIM(e.first_name), ' ', TRIM(e.last_name)) AS uploaded_by_name
        FROM document_files df
        LEFT JOIN employee e ON df.uploaded_by = e.emp_id
        WHERE df.document_id = ?
        ORDER BY df.uploaded_at ASC
    ");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    json_out(['success' => true, 'files' => $files]);
}

// ─────────────────────────────────────────────────────────────
// DOWNLOAD FILE  –  stream a stored file to the browser
// GET: action=download_file&file_id=<id>&inline=1  (inline=preview)
// ─────────────────────────────────────────────────────────────
if ($action === 'download_file') {
    $file_id = (int)($_GET['file_id'] ?? 0);
    $inline  = (int)($_GET['inline']  ?? 0); // 1 = inline (preview), 0 = attachment (download)

    if (!$file_id) {
        json_out(['success' => false, 'message' => 'File ID required.']);
    }

    $stmt = $db->prepare("SELECT * FROM document_files WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();

    if (!$file) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    $upload_dir = __DIR__ . '/../uploads/document_files/';
    $path       = $upload_dir . $file['stored_name'];

    if (!file_exists($path)) {
        http_response_code(404);
        echo 'File missing on disk.';
        exit;
    }

    // Stream the file
    $disposition = $inline ? 'inline' : 'attachment';
    $safe_name   = rawurlencode($file['original_name']);

    ob_end_clean();
    header('Content-Type: '        . $file['mime_type']);
    header('Content-Length: '      . filesize($path));
    header('Content-Disposition: ' . $disposition . '; filename="' . $file['original_name'] . '"; filename*=UTF-8\'\'' . $safe_name);
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

// ─────────────────────────────────────────────────────────────
// DELETE FILE  –  remove one attachment
// POST: action=delete_file&file_id=<id>
// Only the uploader or a Masteradmin may delete.
// ─────────────────────────────────────────────────────────────
if ($action === 'delete_file') {
    $file_id = (int)($_POST['file_id'] ?? 0);
    $emp_id  = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);

    if (!$file_id || !$emp_id) {
        json_out(['success' => false, 'message' => 'Invalid parameters.']);
    }

    $stmt = $db->prepare("SELECT * FROM document_files WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();

    if (!$file) {
        json_out(['success' => false, 'message' => 'File not found.']);
    }

    // Check permission: uploader or masteradmin
    $is_uploader   = ((int)($file['uploaded_by'] ?? -1) === $emp_id);
    $admin_ids     = getMasteradminIds($db);
    $is_masteradmin = in_array($emp_id, $admin_ids);

    if (!$is_uploader && !$is_masteradmin) {
        json_out(['success' => false, 'message' => 'You do not have permission to delete this file.']);
    }

    // Delete DB record
    $del = $db->prepare("DELETE FROM document_files WHERE id = ?");
    $del->bind_param("i", $file_id);
    if (!$del->execute()) {
        json_out(['success' => false, 'message' => 'DB delete failed: ' . $del->error]);
    }

    // Remove physical file
    $upload_dir = __DIR__ . '/../uploads/document_files/';
    @unlink($upload_dir . $file['stored_name']);

    json_out(['success' => true, 'message' => 'File deleted.']);
}

if ($action === 'get_types') {
    $rows = $db->query("SELECT id, type_name, description FROM document_types ORDER BY type_name ASC");
    $types = $rows ? $rows->fetch_all(MYSQLI_ASSOC) : [];
    json_out(['success' => true, 'types' => $types]);
}
 
// ─────────────────────────────────────────────────────────────
// ADD TYPE
// POST: action=add_type  |  type_name, description
// ─────────────────────────────────────────────────────────────
if ($action === 'add_type') {
    $type_name   = trim($_POST['type_name']   ?? '');
    $description = trim($_POST['description'] ?? '');
 
    if (!$type_name) {
        json_out(['success' => false, 'message' => 'Type name is required.']);
    }
    if (mb_strlen($type_name) > 100) {
        json_out(['success' => false, 'message' => 'Type name must be 100 characters or fewer.']);
    }
 
    // Duplicate check (case-insensitive)
    $dup = $db->prepare("SELECT id FROM document_types WHERE LOWER(type_name) = LOWER(?) LIMIT 1");
    $dup->bind_param("s", $type_name);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        json_out(['success' => false, 'message' => "A type named \"$type_name\" already exists."]);
    }
 
    $stmt = $db->prepare("INSERT INTO document_types (type_name, description) VALUES (?, ?)");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
    }
    $desc_val = $description ?: null;
    $stmt->bind_param("ss", $type_name, $desc_val);
 
    if ($stmt->execute()) {
        json_out(['success' => true, 'id' => $db->insert_id, 'message' => 'Document type added.']);
    } else {
        json_out(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    }
}
 
// ─────────────────────────────────────────────────────────────
// UPDATE TYPE
// POST: action=update_type  |  id, type_name, description
// ─────────────────────────────────────────────────────────────
if ($action === 'update_type') {
    $id          = (int)($_POST['id']          ?? 0);
    $type_name   = trim($_POST['type_name']    ?? '');
    $description = trim($_POST['description']  ?? '');
 
    if (!$id) {
        json_out(['success' => false, 'message' => 'Invalid ID.']);
    }
    if (!$type_name) {
        json_out(['success' => false, 'message' => 'Type name is required.']);
    }
    if (mb_strlen($type_name) > 100) {
        json_out(['success' => false, 'message' => 'Type name must be 100 characters or fewer.']);
    }
 
    // Duplicate check — exclude self
    $dup = $db->prepare("SELECT id FROM document_types WHERE LOWER(type_name) = LOWER(?) AND id != ? LIMIT 1");
    $dup->bind_param("si", $type_name, $id);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        json_out(['success' => false, 'message' => "Another type named \"$type_name\" already exists."]);
    }
 
    $stmt = $db->prepare("UPDATE document_types SET type_name = ?, description = ? WHERE id = ?");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
    }
    $desc_val = $description ?: null;
    $stmt->bind_param("ssi", $type_name, $desc_val, $id);
 
    if ($stmt->execute()) {
        json_out(['success' => true, 'message' => 'Document type updated.']);
    } else {
        json_out(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    }
}
 
// ─────────────────────────────────────────────────────────────
// DELETE TYPE
// POST: action=delete_type  |  id
// ─────────────────────────────────────────────────────────────
if ($action === 'delete_type') {
    $id = (int)($_POST['id'] ?? 0);
 
    if (!$id) {
        json_out(['success' => false, 'message' => 'Invalid ID.']);
    }
 
    // Soft-guard: warn if documents still reference this type
    $ref = $db->prepare("SELECT COUNT(*) AS cnt FROM document_records WHERE document_type_id = ?");
    $ref->bind_param("i", $id);
    $ref->execute();
    $ref_count = (int)($ref->get_result()->fetch_assoc()['cnt'] ?? 0);
    if ($ref_count > 0) {
        json_out([
            'success' => false,
            'message' => "Cannot delete: $ref_count document record(s) are still using this type. Reassign them first."
        ]);
    }
 
    $stmt = $db->prepare("DELETE FROM document_types WHERE id = ?");
    if (!$stmt) {
        json_out(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
    }
    $stmt->bind_param("i", $id);
 
    if ($stmt->execute()) {
        json_out(['success' => true, 'message' => 'Document type deleted.']);
    } else {
        json_out(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
    }
}

// Catch-all — must be the very last line
json_out(['success' => false, 'message' => 'Unknown action.']);