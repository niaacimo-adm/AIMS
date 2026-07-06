<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$emp_id = $_SESSION['emp_id'] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// A handful of actions are personal self-service (any logged-in employee
// checking their OWN assigned equipment) and don't need the module permission.
$self_service_actions = ['get_my_equipment', 'get_my_equipment_history'];

if (!$emp_id) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Every other action below requires at least read access to the module
if (!in_array($action, $self_service_actions) && !(hasPermission('view_ict_equipment') || hasPermission('manage_ict_maintenance'))) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Actions that require full manage rights
$manage_actions = [
    'save_equipment', 'delete_equipment', 'assign_equipment', 'return_equipment',
    'add_maintenance', 'update_maintenance', 'save_category', 'delete_category'
];
if (in_array($action, $manage_actions) && !hasPermission('manage_ict_maintenance')) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
    exit;
}

function generate_asset_tag($db, $id) {
    return 'ICT-' . date('Y') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

switch ($action) {

    // -----------------------------------------------------------
    case 'list_equipment':
        $sql = "SELECT e.*, c.category_name,
                       o.office_name,
                       (SELECT CONCAT(emp.first_name, ' ', emp.last_name)
                          FROM ict_equipment_assignments a
                          JOIN employee emp ON emp.emp_id = a.employee_id
                         WHERE a.equipment_id = e.id AND a.status = 'Assigned'
                         ORDER BY a.assigned_date DESC LIMIT 1) AS assigned_to
                FROM ict_equipment e
                LEFT JOIN ict_categories c ON c.id = e.category_id
                LEFT JOIN office o ON o.office_id = e.office_id
                ORDER BY e.id DESC";
        $res = $db->query($sql);
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // -----------------------------------------------------------
    case 'get_equipment':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM ict_equipment WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        echo json_encode(['success' => (bool) $row, 'data' => $row]);
        break;

    // -----------------------------------------------------------
    case 'save_equipment':
        $id             = (int) ($_POST['id'] ?? 0);
        $category_id    = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $brand          = trim($_POST['brand'] ?? '');
        $model          = trim($_POST['model'] ?? '');
        $serial_number  = trim($_POST['serial_number'] ?? '');
        $specifications = trim($_POST['specifications'] ?? '');
        $date_acquired  = !empty($_POST['date_acquired']) ? $_POST['date_acquired'] : null;
        $cost           = !empty($_POST['acquisition_cost']) ? (float) $_POST['acquisition_cost'] : null;
        $supplier       = trim($_POST['supplier'] ?? '');
        $office_id      = !empty($_POST['office_id']) ? (int) $_POST['office_id'] : null;
        $condition      = $_POST['condition_status'] ?? 'Good';
        $status         = $_POST['status'] ?? 'Available';
        $remarks        = trim($_POST['remarks'] ?? '');

        if ($equipment_name === '') {
            echo json_encode(['success' => false, 'message' => 'Equipment name is required.']);
            break;
        }

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE ict_equipment SET category_id=?, equipment_name=?, brand=?, model=?,
                serial_number=?, specifications=?, date_acquired=?, acquisition_cost=?, supplier=?, office_id=?,
                condition_status=?, status=?, remarks=? WHERE id=?");
            $stmt->bind_param(
                "issssssdsisssi",
                $category_id, $equipment_name, $brand, $model, $serial_number, $specifications,
                $date_acquired, $cost, $supplier, $office_id, $condition, $status, $remarks, $id
            );
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Equipment updated.', 'id' => $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO ict_equipment
                (asset_tag, category_id, equipment_name, brand, model, serial_number, specifications,
                 date_acquired, acquisition_cost, supplier, office_id, condition_status, status, remarks, created_by)
                VALUES ('', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "issssssdsisssi",
                $category_id, $equipment_name, $brand, $model, $serial_number, $specifications,
                $date_acquired, $cost, $supplier, $office_id, $condition, $status, $remarks, $emp_id
            );
            $stmt->execute();
            $new_id = $db->insert_id;

            // Assign the permanent, unique asset tag now that we have an id
            $tag = generate_asset_tag($db, $new_id);
            $upd = $db->prepare("UPDATE ict_equipment SET asset_tag = ? WHERE id = ?");
            $upd->bind_param("si", $tag, $new_id);
            $upd->execute();

            echo json_encode(['success' => true, 'message' => 'Equipment added.', 'id' => $new_id, 'asset_tag' => $tag]);
        }
        break;

    // -----------------------------------------------------------
    case 'delete_equipment':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM ict_equipment WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Equipment deleted.']);
        break;

    // -----------------------------------------------------------
    case 'get_categories':
        $res = $db->query("SELECT * FROM ict_categories ORDER BY category_name");
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // -----------------------------------------------------------
    case 'save_category':
        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-microchip');
        if ($name === '') { echo json_encode(['success' => false, 'message' => 'Category name required.']); break; }
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE ict_categories SET category_name=?, icon=? WHERE id=?");
            $stmt->bind_param("ssi", $name, $icon, $id);
        } else {
            $stmt = $db->prepare("INSERT INTO ict_categories (category_name, icon) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $icon);
        }
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Category saved.']);
        break;

    // -----------------------------------------------------------
    case 'delete_category':
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM ict_categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Category deleted.']);
        break;

    // -----------------------------------------------------------
    case 'get_available_equipment':
        $res = $db->query("SELECT id, asset_tag, equipment_name, brand, model FROM ict_equipment WHERE status = 'Available' ORDER BY equipment_name");
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // -----------------------------------------------------------
    case 'get_employees':
        $res = $db->query("SELECT emp_id, CONCAT(first_name,' ',last_name) AS full_name FROM employee ORDER BY first_name");
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // -----------------------------------------------------------
    case 'assign_equipment':
        $equipment_id  = (int) ($_POST['equipment_id'] ?? 0);
        $employee_id   = (int) ($_POST['employee_id'] ?? 0);
        $expected_ret  = !empty($_POST['expected_return_date']) ? $_POST['expected_return_date'] : null;
        $condition_on  = trim($_POST['condition_on_assign'] ?? 'Good');
        $remarks       = trim($_POST['remarks'] ?? '');

        if (!$equipment_id || !$employee_id) {
            echo json_encode(['success' => false, 'message' => 'Equipment and employee are required.']);
            break;
        }

        // Verify equipment is currently available
        $chk = $db->prepare("SELECT status FROM ict_equipment WHERE id = ? FOR UPDATE");
        $db->begin_transaction();
        $chk->bind_param("i", $equipment_id);
        $chk->execute();
        $st = $chk->get_result()->fetch_assoc();
        if (!$st || $st['status'] !== 'Available') {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'This equipment is not currently available.']);
            break;
        }

        $ins = $db->prepare("INSERT INTO ict_equipment_assignments
            (equipment_id, employee_id, assigned_by, expected_return_date, condition_on_assign, remarks)
            VALUES (?, ?, ?, ?, ?, ?)");
        $ins->bind_param("iiisss", $equipment_id, $employee_id, $emp_id, $expected_ret, $condition_on, $remarks);
        $ins->execute();

        $upd = $db->prepare("UPDATE ict_equipment SET status = 'Assigned' WHERE id = ?");
        $upd->bind_param("i", $equipment_id);
        $upd->execute();

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Equipment assigned successfully.']);
        break;

    // -----------------------------------------------------------
    case 'return_equipment':
        $assignment_id     = (int) ($_POST['assignment_id'] ?? 0);
        $condition_on_ret  = trim($_POST['condition_on_return'] ?? 'Good');
        $remarks           = trim($_POST['remarks'] ?? '');
        $new_status        = $_POST['new_status'] ?? 'Available'; // Available or Under Repair / Retired

        $stmt = $db->prepare("SELECT equipment_id FROM ict_equipment_assignments WHERE id = ? AND status = 'Assigned'");
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Active assignment not found.']);
            break;
        }
        $equipment_id = $row['equipment_id'];

        $db->begin_transaction();
        $upd = $db->prepare("UPDATE ict_equipment_assignments
            SET status='Returned', returned_date = NOW(), returned_to = ?, condition_on_return = ?, remarks = CONCAT(IFNULL(remarks,''), ?)
            WHERE id = ?");
        $remarks_append = $remarks !== '' ? ("\n[Return] " . $remarks) : '';
        $upd->bind_param("issi", $emp_id, $condition_on_ret, $remarks_append, $assignment_id);
        $upd->execute();

        $upd2 = $db->prepare("UPDATE ict_equipment SET status = ? WHERE id = ?");
        $upd2->bind_param("si", $new_status, $equipment_id);
        $upd2->execute();
        $db->commit();

        echo json_encode(['success' => true, 'message' => 'Equipment returned.']);
        break;

    // -----------------------------------------------------------
    case 'list_assignments':
        $sql = "SELECT a.*, e.asset_tag, e.equipment_name,
                       CONCAT(emp.first_name,' ',emp.last_name) AS employee_name,
                       CONCAT(ab.first_name,' ',ab.last_name) AS assigned_by_name
                FROM ict_equipment_assignments a
                JOIN ict_equipment e ON e.id = a.equipment_id
                JOIN employee emp ON emp.emp_id = a.employee_id
                LEFT JOIN employee ab ON ab.emp_id = a.assigned_by
                ORDER BY a.assigned_date DESC";
        $res = $db->query($sql);
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // -----------------------------------------------------------
    case 'lookup_by_qr':
        $tag = trim($_POST['tag'] ?? $_GET['tag'] ?? '');
        // Allow scanning either the bare tag or a full URL ending in ?tag=XXXX
        if (strpos($tag, 'tag=') !== false) {
            parse_str(parse_url($tag, PHP_URL_QUERY) ?? '', $qs);
            $tag = $qs['tag'] ?? $tag;
        }

        $stmt = $db->prepare("SELECT e.*, c.category_name, o.office_name
                               FROM ict_equipment e
                               LEFT JOIN ict_categories c ON c.id = e.category_id
                               LEFT JOIN office o ON o.office_id = e.office_id
                               WHERE e.asset_tag = ?");
        $stmt->bind_param("s", $tag);
        $stmt->execute();
        $equipment = $stmt->get_result()->fetch_assoc();

        $log = $db->prepare("INSERT INTO ict_scan_logs (equipment_id, scanned_tag, scanned_by, found) VALUES (?, ?, ?, ?)");
        $eid = $equipment['id'] ?? null;
        $found = $equipment ? 1 : 0;
        $log->bind_param("isii", $eid, $tag, $emp_id, $found);
        $log->execute();

        if (!$equipment) {
            echo json_encode(['success' => false, 'message' => 'No equipment found for tag: ' . htmlspecialchars($tag)]);
            break;
        }

        $astmt = $db->prepare("SELECT a.*, CONCAT(emp.first_name,' ',emp.last_name) AS employee_name
                                FROM ict_equipment_assignments a
                                JOIN employee emp ON emp.emp_id = a.employee_id
                                WHERE a.equipment_id = ? AND a.status = 'Assigned'
                                ORDER BY a.assigned_date DESC LIMIT 1");
        $astmt->bind_param("i", $eid);
        $astmt->execute();
        $current_assignment = $astmt->get_result()->fetch_assoc();

        echo json_encode(['success' => true, 'equipment' => $equipment, 'current_assignment' => $current_assignment]);
        break;

    // -----------------------------------------------------------
    case 'add_maintenance':
        $equipment_id = (int) ($_POST['equipment_id'] ?? 0);
        $issue        = trim($_POST['issue_description'] ?? '');
        $date_reported = !empty($_POST['date_reported']) ? $_POST['date_reported'] : date('Y-m-d');
        if (!$equipment_id || $issue === '') {
            echo json_encode(['success' => false, 'message' => 'Equipment and issue description are required.']);
            break;
        }
        $db->begin_transaction();
        $stmt = $db->prepare("INSERT INTO ict_maintenance_logs (equipment_id, reported_by, issue_description, date_reported)
                               VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $equipment_id, $emp_id, $issue, $date_reported);
        $stmt->execute();

        $upd = $db->prepare("UPDATE ict_equipment SET status = 'Under Repair' WHERE id = ?");
        $upd->bind_param("i", $equipment_id);
        $upd->execute();
        $db->commit();

        echo json_encode(['success' => true, 'message' => 'Maintenance issue logged.']);
        break;

    // -----------------------------------------------------------
    case 'update_maintenance':
        $id            = (int) ($_POST['id'] ?? 0);
        $technician    = trim($_POST['technician'] ?? '');
        $action_taken  = trim($_POST['action_taken'] ?? '');
        $cost          = !empty($_POST['cost']) ? (float) $_POST['cost'] : null;
        $status        = $_POST['status'] ?? 'In Progress';
        $date_resolved = $status === 'Resolved' ? date('Y-m-d') : null;

        $stmt = $db->prepare("UPDATE ict_maintenance_logs SET technician=?, action_taken=?, cost=?, status=?, date_resolved=? WHERE id=?");
        $stmt->bind_param("ssdssi", $technician, $action_taken, $cost, $status, $date_resolved, $id);
        $stmt->execute();

        if ($status === 'Resolved') {
            $eq = $db->prepare("SELECT equipment_id FROM ict_maintenance_logs WHERE id = ?");
            $eq->bind_param("i", $id);
            $eq->execute();
            $row = $eq->get_result()->fetch_assoc();
            if ($row) {
                $upd = $db->prepare("UPDATE ict_equipment SET status = 'Available' WHERE id = ?");
                $upd->bind_param("i", $row['equipment_id']);
                $upd->execute();
            }
        }
        echo json_encode(['success' => true, 'message' => 'Maintenance record updated.']);
        break;

    // -----------------------------------------------------------
    case 'list_maintenance':
        $sql = "SELECT m.*, e.asset_tag, e.equipment_name,
                       CONCAT(emp.first_name,' ',emp.last_name) AS reported_by_name
                FROM ict_maintenance_logs m
                JOIN ict_equipment e ON e.id = m.equipment_id
                LEFT JOIN employee emp ON emp.emp_id = m.reported_by
                ORDER BY m.date_reported DESC, m.id DESC";
        $res = $db->query($sql);
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // -----------------------------------------------------------
    case 'dashboard_stats':
        $stats = [
            'total'      => 0, 'available' => 0, 'assigned' => 0,
            'under_repair' => 0, 'retired' => 0,
        ];
        $res = $db->query("SELECT status, COUNT(*) AS c FROM ict_equipment GROUP BY status");
        while ($r = $res->fetch_assoc()) {
            $stats['total'] += (int) $r['c'];
            switch ($r['status']) {
                case 'Available': $stats['available'] = (int) $r['c']; break;
                case 'Assigned': $stats['assigned'] = (int) $r['c']; break;
                case 'Under Repair': $stats['under_repair'] = (int) $r['c']; break;
                case 'Retired': $stats['retired'] = (int) $r['c']; break;
            }
        }
        $recent = $db->query("SELECT a.assigned_date, e.equipment_name, e.asset_tag,
                                      CONCAT(emp.first_name,' ',emp.last_name) AS employee_name
                               FROM ict_equipment_assignments a
                               JOIN ict_equipment e ON e.id = a.equipment_id
                               JOIN employee emp ON emp.emp_id = a.employee_id
                               ORDER BY a.assigned_date DESC LIMIT 5");
        $recent_rows = [];
        while ($r = $recent->fetch_assoc()) { $recent_rows[] = $r; }

        echo json_encode(['success' => true, 'stats' => $stats, 'recent_assignments' => $recent_rows]);
        break;

    // -----------------------------------------------------------
    // Self-service: what equipment is CURRENTLY assigned to me
    case 'get_my_equipment':
        $stmt = $db->prepare("SELECT a.id AS assignment_id, a.assigned_date, a.expected_return_date,
                                      a.condition_on_assign, a.remarks,
                                      e.id AS equipment_id, e.asset_tag, e.equipment_name, e.brand, e.model,
                                      e.serial_number, e.condition_status, c.category_name
                               FROM ict_equipment_assignments a
                               JOIN ict_equipment e ON e.id = a.equipment_id
                               LEFT JOIN ict_categories c ON c.id = e.category_id
                               WHERE a.employee_id = ? AND a.status = 'Assigned'
                               ORDER BY a.assigned_date DESC");
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // -----------------------------------------------------------
    // Self-service: my full assignment history (including returned items)
    case 'get_my_equipment_history':
        $stmt = $db->prepare("SELECT a.*, e.asset_tag, e.equipment_name, e.brand, e.model, c.category_name
                               FROM ict_equipment_assignments a
                               JOIN ict_equipment e ON e.id = a.equipment_id
                               LEFT JOIN ict_categories c ON c.id = e.category_id
                               WHERE a.employee_id = ?
                               ORDER BY a.assigned_date DESC");
        $stmt->bind_param("i", $emp_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    // -----------------------------------------------------------
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}