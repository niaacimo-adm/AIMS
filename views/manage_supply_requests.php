<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Administrators and managers only
if (!in_array($_SESSION['role_name'] ?? '', ['Administrator', 'Manager'])) {
    header("Location: request_supplies.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = $success = "";
$valid_statuses = ['Pending', 'Approved', 'Rejected', 'Cancelled'];

// Change a request's status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if ($request_id > 0 && in_array($new_status, ['Approved', 'Rejected', 'Pending'], true)) {
        $stmt = $db->prepare("UPDATE supply_requests SET status = ? WHERE id = ? AND status <> 'Cancelled'");
        $stmt->bind_param("si", $new_status, $request_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $success = "Request SR-" . str_pad($request_id, 4, '0', STR_PAD_LEFT) . " marked as " . $new_status . ".";
        } else {
            $error = "No change was made (the request may be cancelled or already " . $new_status . ").";
        }
        $stmt->close();
    } else {
        $error = "Invalid request.";
    }
}

// Delete a request permanently (its items are removed too)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_request'])) {
    $request_id = (int)($_POST['request_id'] ?? 0);

    if ($request_id > 0) {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("DELETE FROM supply_request_items WHERE request_id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare("DELETE FROM supply_requests WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();

            $db->commit();

            if ($deleted > 0) {
                $success = "Request SR-" . str_pad($request_id, 4, '0', STR_PAD_LEFT) . " was deleted.";
            } else {
                $error = "That request no longer exists.";
            }
        } catch (Throwable $e) {
            $db->rollback();
            $error = "Could not delete the request. Please try again.";
        }
    } else {
        $error = "Invalid request.";
    }
}

// Filters
$filter_status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT r.id, r.employee_name, r.section, r.request_date, r.status,
               (SELECT COUNT(*) FROM supply_request_items i WHERE i.request_id = r.id) AS item_count,
               (SELECT COALESCE(SUM(i.quantity), 0) FROM supply_request_items i WHERE i.request_id = r.id) AS total_qty
        FROM supply_requests r WHERE 1=1";
$types = '';
$params = [];

if (in_array($filter_status, $valid_statuses, true)) {
    $sql .= " AND r.status = ?";
    $types .= 's';
    $params[] = $filter_status;
}
if ($search !== '') {
    $sql .= " AND (r.employee_name LIKE ? OR r.section LIKE ? OR CONCAT('SR-', LPAD(r.id, 4, '0')) LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
$sql .= " ORDER BY r.id DESC";

$stmt = $db->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$requests = [];
while ($row = $res->fetch_assoc()) {
    $row['items'] = [];
    $requests[(int)$row['id']] = $row;
}
$stmt->close();

if ($requests) {
    $ids = implode(',', array_map('intval', array_keys($requests)));
    $items_res = $db->query("SELECT request_id, supply_name, description, unit, quantity FROM supply_request_items WHERE request_id IN ($ids) ORDER BY id");
    if ($items_res) {
        while ($it = $items_res->fetch_assoc()) {
            $requests[(int)$it['request_id']]['items'][] = $it;
        }
    }
}

// Status counts for the header
$counts = array_fill_keys($valid_statuses, 0);
$count_res = $db->query("SELECT status, COUNT(*) AS c FROM supply_requests GROUP BY status");
if ($count_res) {
    while ($c = $count_res->fetch_assoc()) {
        if (isset($counts[$c['status']])) {
            $counts[$c['status']] = (int)$c['c'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Supply Requests - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
    :root {
        --h-bg:#eef7f2; --h-card:#ffffff; --h-card-alt:#f0faf5; --h-surface3:#e2f3ea;
        --h-border:rgba(42,152,99,0.18); --h-text:#0f2d1e; --h-muted:#4a7a5e;
        --h-primary:#2a9863; --h-primary-dim:rgba(42,152,99,.10); --h-accent:#24e78f;
        --h-warning:#e67700; --h-danger:#c92a2a;
        --h-shadow:0 4px 24px rgba(42,152,99,.12); --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
    }
    body.dark-mode {
        --h-bg:#0b1f17; --h-card:#102f22; --h-card-alt:#0e2619; --h-surface3:#16352a;
        --h-border:rgba(36,231,143,0.12); --h-text:#d4f5e5; --h-muted:#6aad8a;
        --h-primary:#24e78f; --h-primary-dim:rgba(36,231,143,.12); --h-accent:#2a9863;
        --h-warning:#ffd43b; --h-danger:#ff6b6b;
        --h-shadow:0 4px 24px rgba(0,0,0,.35); --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
    }
    .content-wrapper { background: var(--h-bg) !important; }
    .main-header.navbar, .main-header, nav.main-header, header.main-header { z-index:1000 !important; }
    .main-sidebar, aside.main-sidebar { z-index:999 !important; }
    .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }

    @keyframes pgHeroMeshDrift { 0%{transform:translate(0,0) rotate(0deg);} 100%{transform:translate(3%,2%) rotate(2deg);} }
    .pg-hero { background:#0b1f17; padding:36px 28px 66px; position:relative; overflow:hidden; }
    .pg-hero-mesh {
        position:absolute; inset:-50%; width:200%; height:200%;
        background:
            radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
            radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
            linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
        animation:pgHeroMeshDrift 22s ease-in-out infinite alternate; z-index:0;
    }
    .pg-hero-dots { position:absolute; inset:0; z-index:0; pointer-events:none;
        background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px); background-size:36px 36px; }
    .pg-hero::after { content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px;
        background:var(--body-bg, #eef7f2); clip-path:ellipse(58% 100% at 50% 100%); z-index:1; }
    body.dark-mode .pg-hero::after { background:var(--body-bg, #0b1f17); }
    .pg-hero-inner { position:relative; z-index:2; }
    .pg-hero-title { color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px; letter-spacing:-.3px;
        text-shadow:0 2px 14px rgba(0,0,0,.45); display:flex; align-items:center; gap:10px; }
    .pg-hero-divider { width:48px; height:2px; border-radius:2px; margin:0 0 12px;
        background:linear-gradient(90deg,transparent,#24e78f,transparent); }

    .card { background:var(--h-card) !important; border:1px solid var(--h-border) !important; border-radius:12px !important; box-shadow:var(--h-shadow-sm) !important; }
    .card-header { background:var(--h-card) !important; border-bottom:1px solid var(--h-border) !important; padding:14px 20px !important;
        display:flex !important; align-items:center !important; justify-content:space-between !important; flex-wrap:wrap !important; gap:10px !important; }
    .card-title { font-weight:700 !important; font-size:.9rem !important; color:var(--h-text) !important; margin-right:auto !important; }
    .card-body { background:var(--h-card) !important; padding:18px !important; color:var(--h-text); }
    .card-tools { display:flex !important; align-items:center !important; gap:8px; flex-wrap:wrap; margin-left:auto !important; }

    .rs-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; border-radius:8px !important; font-weight:700 !important; font-size:.8rem !important;
        padding:8px 18px !important; border:1px solid transparent !important; cursor:pointer; transition:filter .12s, transform .12s; }
    .rs-btn:hover { filter:brightness(1.1); transform:translateY(-1px); text-decoration:none; }
    .rs-btn-sm { padding:5px 12px !important; font-size:.75rem !important; }
    .rs-btn-primary { background:var(--h-primary) !important; color:#fff !important; }
    body.dark-mode .rs-btn-primary { color:#062015 !important; }
    .rs-btn-ghost { background:transparent !important; color:var(--h-text) !important; border-color:var(--h-border) !important; }
    .rs-btn-ghost:hover { color:var(--h-primary) !important; border-color:var(--h-primary) !important; }
    .rs-btn-danger { background:rgba(201,42,42,.10) !important; color:var(--h-danger) !important; border-color:rgba(201,42,42,.30) !important; }
    .rs-btn-danger:hover { background:var(--h-danger) !important; color:#fff !important; }
    .alert { display:flex; align-items:center; gap:10px; }

    .badge-status { display:inline-block; font-size:.66rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; padding:4px 10px; border-radius:20px; }
    .st-pending   { background:rgba(230,119,0,.14);  color:var(--h-warning); }
    .st-approved  { background:var(--h-primary-dim); color:var(--h-primary); }
    .st-rejected  { background:rgba(201,42,42,.12);  color:var(--h-danger); }
    .st-cancelled { background:var(--h-surface3);    color:var(--h-muted); }

    .rs-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
    .rs-filters input, .rs-filters select { height:38px; border-radius:8px; padding:0 12px; font-size:.85rem;
        background:var(--h-card-alt); border:1.5px solid var(--h-border); color:var(--h-text); }
    .rs-filters input { flex:1; min-width:200px; }
    .rs-filters input:focus, .rs-filters select:focus { outline:none; border-color:var(--h-primary); box-shadow:0 0 0 3px var(--h-primary-dim); }

    .rs-table { width:100%; border-collapse:collapse; }
    .rs-table th { font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--h-muted);
        text-align:left; padding:10px 12px; border-bottom:1px solid var(--h-border); white-space:nowrap; }
    .rs-table td { padding:12px; border-bottom:1px solid var(--h-border); font-size:.85rem; color:var(--h-text); vertical-align:middle; }
    .rs-table tr.detail-row td { background:var(--h-card-alt); padding:14px 18px; }
    .rs-table .num { text-align:right; }
    .rs-empty { text-align:center; padding:50px 10px; color:var(--h-muted); }
    .rs-empty i { font-size:2rem; display:block; margin-bottom:10px; opacity:.5; }

    /* My requests: one card per request */
    .req-card { background:var(--h-card-alt); border:1px solid var(--h-border); border-radius:12px; margin-bottom:14px; overflow:hidden; }
    .req-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:14px 18px; border-bottom:1px solid var(--h-border); }
    .req-id { font-weight:800; font-size:.95rem; color:var(--h-text); }
    .req-date { font-size:.78rem; color:var(--h-muted); }
    .req-body { padding:6px 18px 14px; }
</style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_inventory.php'; ?>

    <div class="content-wrapper">
        <div class="pg-hero">
            <div class="pg-hero-mesh"></div>
            <div class="pg-hero-dots"></div>
            <div class="pg-hero-inner">
                <div class="pg-hero-title"><i class="fas fa-cog"></i>Manage Supply Requests</div>
                <div class="pg-hero-divider"></div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($error) ?></span></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($success) ?></span></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-inbox mr-2"></i>All Supply Requests</h3>
                        <div class="card-tools">
                            <span class="badge-status st-pending"><?= $counts['Pending'] ?> pending</span>
                            <span class="badge-status st-approved"><?= $counts['Approved'] ?> approved</span>
                            <span class="badge-status st-rejected"><?= $counts['Rejected'] ?> rejected</span>
                            <a href="request_supplies.php" class="rs-btn rs-btn-ghost"><i class="fas fa-plus"></i> New Request</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="rs-filters">
                            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by employee, section or request ID…">
                            <select name="status" onchange="this.form.submit()">
                                <option value="">All statuses</option>
                                <?php foreach ($valid_statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="rs-btn rs-btn-ghost"><i class="fas fa-search"></i> Search</button>
                        </form>

                        <?php if (!$requests): ?>
                            <div class="rs-empty"><i class="fas fa-inbox"></i>No supply requests found.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="rs-table">
                                <thead>
                                    <tr>
                                        <th>Request</th><th>Employee</th><th>Section</th><th>Date</th>
                                        <th class="num">Items</th><th class="num">Total Qty</th><th>Status</th><th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($requests as $r):
                                    $status = $r['status'] ?: 'Pending';
                                    $rid = (int)$r['id'];
                                ?>
                                    <tr>
                                        <td><strong>SR-<?= str_pad($rid, 4, '0', STR_PAD_LEFT) ?></strong></td>
                                        <td><?= htmlspecialchars($r['employee_name']) ?></td>
                                        <td><?= htmlspecialchars($r['section']) ?></td>
                                        <td><?= date('M j, Y', strtotime($r['request_date'])) ?></td>
                                        <td class="num"><?= (int)$r['item_count'] ?></td>
                                        <td class="num"><?= number_format((int)$r['total_qty']) ?></td>
                                        <td><span class="badge-status st-<?= strtolower(htmlspecialchars($status)) ?>"><?= htmlspecialchars($status) ?></span></td>
                                        <td>
                                            <div class="d-flex" style="gap:6px; flex-wrap:wrap;">
                                                <button type="button" class="rs-btn rs-btn-ghost rs-btn-sm toggle-items" data-target="items-<?= $rid ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($status !== 'Cancelled'): ?>
                                                    <form method="POST" class="m-0 d-flex" style="gap:6px;">
                                                        <input type="hidden" name="request_id" value="<?= $rid ?>">
                                                        <?php if ($status !== 'Approved'): ?>
                                                            <button type="submit" name="update_status" value="1" class="rs-btn rs-btn-primary rs-btn-sm"
                                                                    onclick="this.form.new_status.value='Approved'"><i class="fas fa-check"></i> Approve</button>
                                                        <?php endif; ?>
                                                        <?php if ($status !== 'Rejected'): ?>
                                                            <button type="submit" name="update_status" value="1" class="rs-btn rs-btn-danger rs-btn-sm"
                                                                    onclick="this.form.new_status.value='Rejected'; return confirm('Reject this request?');"><i class="fas fa-times"></i> Reject</button>
                                                        <?php endif; ?>
                                                        <input type="hidden" name="new_status" value="">
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="m-0" onsubmit="return confirm('Permanently delete SR-<?= str_pad($rid, 4, '0', STR_PAD_LEFT) ?> and all of its items? This cannot be undone.');">
                                                    <input type="hidden" name="request_id" value="<?= $rid ?>">
                                                    <button type="submit" name="delete_request" value="1" class="rs-btn rs-btn-danger rs-btn-sm"><i class="fas fa-trash"></i> Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="detail-row" id="items-<?= $rid ?>" style="display:none;">
                                        <td colspan="8">
                                            <table class="rs-table">
                                                <thead><tr><th>Supply</th><th>Description</th><th>Unit</th><th class="num">Qty</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($r['items'] as $it): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($it['supply_name']) ?></strong></td>
                                                        <td><?= htmlspecialchars($it['description'] ?: '—') ?></td>
                                                        <td><?= htmlspecialchars($it['unit'] ?: '—') ?></td>
                                                        <td class="num"><?= number_format((int)$it['quantity']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<script>
    $(document).on('click', '.toggle-items', function() {
        $('#' + $(this).data('target')).toggle();
    });
</script>
</body>
</html>