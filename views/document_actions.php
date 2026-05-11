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

function resolveDocumentSectionForeignKeyId($db, $sectionId) {
    if (!$sectionId) {
        return null;
    }

    $checkTable = $db->prepare("
        SELECT 1 FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_sections'
    ");
    if (!$checkTable) {
        return $sectionId;
    }

    $checkTable->execute();
    $tableExists = $checkTable->get_result()->num_rows > 0;
    $checkTable->close();

    if (!$tableExists) {
        return $sectionId;
    }

    $mapStmt = $db->prepare("SELECT id FROM document_sections WHERE section_id = ? LIMIT 1");
    if (!$mapStmt) {
        return $sectionId;
    }

    $mapStmt->bind_param('i', $sectionId);
    $mapStmt->execute();
    $row = $mapStmt->get_result()->fetch_assoc();
    $mapStmt->close();

    if ($row && isset($row['id'])) {
        return (int)$row['id'];
    }

    return null;
}

// ─────────────────────────────────────────────────────────────
// GET – fetch single document
// ─────────────────────────────────────────────────────────────
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        ob_clean(); echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit;
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
        ob_clean(); echo json_encode(['success' => false, 'message' => 'Document not found']); exit;
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

    ob_clean();
    echo json_encode(['success' => true, 'data' => $doc, 'history' => $history]);
    exit;
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

    ob_clean();
    echo json_encode(['success' => true, 'sections' => $sections]);
    exit;
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
    ob_clean();
    echo json_encode(['success' => true, 'units' => $units]);
    exit;
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

    ob_clean();
    echo json_encode(['success' => true, 'counts' => $counts]);
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
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Kind, Document Number, and Name are required.']);
        exit;
    }

    if (!in_array($kind, ['incoming', 'outgoing', 'internal'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid kind value.']);
        exit;
    }

    $forwarded_by_emp_id = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0) ?: null;
    $date_forwarded      = date('Y-m-d H:i:s');
    $date_received_db    = !empty($date_received) ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $date_received))) : null;

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
             date_forwarded, date_received, status, remarks)
        VALUES (?,?,?,?, ?,?,?,?, ?,?,?,?, ?,?,?,?)
    ");
    if (!$stmt) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
        exit;
    }

    // Correct type string: 16 params => s,s,i,s, i,s,i,i, i,s,i,i, s,s,s,s
    $stmt->bind_param(
        "ssisisiiissiisss",
        $kind, $document_num, $type_id, $doc_name,
        $forwarded_by_emp_id, $null_str,
        $null_int1, $null_int2,
        $null_int3, $empty_str,
        $null_int4, $null_int5,
        $date_forwarded, $date_received_db, $status, $remarks
    );

    ob_clean();
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $db->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Add failed: ' . $stmt->error]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// UPDATE document
// ─────────────────────────────────────────────────────────────
if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { ob_clean(); echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

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
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid kind value.']);
        exit;
    }

    // FIX: guard strtotime against empty/zero dates
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
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
        exit;
    }
    // 14 params: s,s,i,s, i,i, i,i,i, s,s,s,s, i
    $stmt->bind_param(
        "ssisiiiisssssi",
        $kind, $doc_num, $type_id, $doc_name,
        $fwd_by_emp, $from_sec,
        $fwd_to_emp, $to_sec, $to_unit,
        $date_fwd_db, $date_rcv_db, $status, $remarks, $id
    );

    ob_clean();
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    }
    exit;
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
    $fwd_date_raw = $_POST['fwd_date'] ?? '';
    $fwd_remarks  = trim($_POST['fwd_remarks'] ?? '');
    $forward_to   = trim($_POST['forward_to'] ?? 'section');

    if (!$doc_id) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Document ID missing']);
        exit;
    }

    // FIX: use toMySQLDateTime to safely handle datetime-local format
    $fwd_date = toMySQLDateTime($fwd_date_raw) ?: date('Y-m-d H:i:s');

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
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Destination section is required']);
            exit;
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

        // Build label (section + optional unit)
        $label_stmt = $db->prepare("
            SELECT s.section_name, u.unit_name
            FROM section s
            LEFT JOIN unit_section u ON u.unit_id = ?
            WHERE s.section_id = ?
            LIMIT 1
        ");
        $label_stmt->bind_param("ii", $fwd_to_unit, $fwd_to_sec);
        $label_stmt->execute();
        $label_row     = $label_stmt->get_result()->fetch_assoc();
        $section_label = $label_row['section_name'] ?? 'Unknown Section';
        $unit_label    = ($fwd_to_unit && !empty($label_row['unit_name'])) ? $label_row['unit_name'] : '';
        $fwd_label     = $unit_label ? "$section_label – $unit_label" : $section_label;

        // Clear office field for this path
        $fwd_to_off = null;
    }

    $resolved_fwd_to_sec = resolveDocumentSectionForeignKeyId($db, $fwd_to_sec);

    // Insert forwarding history
    $istmt = $db->prepare("
        INSERT INTO document_forwards
            (document_id, fwd_by_emp_id, fwd_to_emp_id, fwd_to_section_id, fwd_to_unit_id, fwd_to_office_id, fwd_date, fwd_remarks)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    if (!$istmt) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $db->error]);
        exit;
    }
    // FIX: was "iiiiiiis" — fwd_date is a datetime string so must be 's', not 'i'
    // Correct: document_id(i), fwd_by(i), fwd_to(i), sec(i), unit(i), office(i), fwd_date(s), remarks(s)
    $istmt->bind_param("iiiiiiss",
        $doc_id, $fwd_by_emp, $fwd_to_emp,
        $resolved_fwd_to_sec, $fwd_to_unit, $fwd_to_off,
        $fwd_date, $fwd_remarks
    );
    if (!$istmt->execute()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'History insert failed: ' . $istmt->error]);
        exit;
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
    $ustmt->bind_param("iiiissi",
        $fwd_to_emp, $fwd_to_sec, $fwd_to_unit, $fwd_to_off,
        $fwd_label, $fwd_date, $doc_id
    );
    $ok = $ustmt->execute();

    ob_clean();
    if ($ok) {
        echo json_encode([
            'success'      => true,
            'focal_person' => $resolved_name,
            'destination'  => $fwd_label,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $ustmt->error]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// UPDATE STATUS
// ─────────────────────────────────────────────────────────────
if ($action === 'update_status') {
    $id     = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    if (!$id || !in_array($status, ['pending', 'received', 'returned', 'completed', 'archived'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid status or ID.']);
        exit;
    }
    $stmt = $db->prepare("UPDATE document_records SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $success = $stmt->execute();
    ob_clean();
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Status updated' : 'Update failed: ' . $stmt->error,
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────
// DELETE
// ─────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { ob_clean(); echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
    $stmt = $db->prepare("DELETE FROM document_records WHERE id = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    ob_clean();
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Deleted' : 'Delete failed: ' . $stmt->error,
    ]);
    exit;
}

ob_clean();
echo json_encode(['success' => false, 'message' => 'Unknown action.']);