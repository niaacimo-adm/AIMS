<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error_message = 'Unit name is required.';
    } else {
        $stmt = $db->prepare("INSERT INTO unit_of_measure (name, abbreviation, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $abbreviation, $description);
        if ($stmt->execute()) {
            $success_message = 'Unit of measure added successfully.';
        } else {
            $error_message = ($db->errno === 1062)
                ? 'A unit with that name already exists.'
                : 'Failed to add unit of measure.';
        }
    }
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $id <= 0) {
        $error_message = 'Unit name is required.';
    } else {
        $stmt = $db->prepare("UPDATE unit_of_measure SET name = ?, abbreviation = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $abbreviation, $description, $id);
        if ($stmt->execute()) {
            $success_message = 'Unit of measure updated successfully.';
        } else {
            $error_message = ($db->errno === 1062)
                ? 'A unit with that name already exists.'
                : 'Failed to update unit of measure.';
        }
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        // Look up the unit name so we can warn if items still reference it
        $check_stmt = $db->prepare("SELECT name FROM unit_of_measure WHERE id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $unit_row = $check_stmt->get_result()->fetch_assoc();

        $in_use = 0;
        if ($unit_row) {
            $usage_stmt = $db->prepare("SELECT COUNT(*) as cnt FROM items WHERE unit_of_measure = ?");
            $usage_stmt->bind_param("s", $unit_row['name']);
            $usage_stmt->execute();
            $in_use = (int)$usage_stmt->get_result()->fetch_assoc()['cnt'];
        }

        if ($in_use > 0) {
            $error_message = "Cannot delete: {$in_use} item(s) still use this unit of measure.";
        } else {
            $stmt = $db->prepare("DELETE FROM unit_of_measure WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = 'Unit of measure deleted successfully.';
            } else {
                $error_message = 'Failed to delete unit of measure.';
            }
        }
    }
}

// Fetch all units
$units = [];
$result = $db->query("SELECT u.id, u.name, u.abbreviation, u.description, u.created_at,
                             (SELECT COUNT(*) FROM items i WHERE i.unit_of_measure = u.name) AS item_count
                      FROM unit_of_measure u ORDER BY u.name");
if ($result) {
    $units = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit of Measure - Inventory Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        :root {
            --h-bg:       #eef7f2;
            --h-card:     #ffffff;
            --h-card-alt: #f0faf5;
            --h-surface3: #e2f3ea;
            --h-border:   rgba(42,152,99,0.18);
            --h-text:     #0f2d1e;
            --h-muted:    #4a7a5e;
            --h-primary:  #2a9863;
            --h-primary-dim: rgba(42,152,99,.10);
            --h-accent:   #24e78f;
            --h-success:  #2a9863;
            --h-warning:  #e67700;
            --h-danger:   #c92a2a;
            --h-blue:     #2a9863;
            --h-shadow:   0 4px 24px rgba(42,152,99,.12);
            --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
        }
        body.dark-mode {
            --h-bg:       #0b1f17;
            --h-card:     #102f22;
            --h-card-alt: #0e2619;
            --h-surface3: #16352a;
            --h-border:   rgba(36,231,143,0.12);
            --h-text:     #d4f5e5;
            --h-muted:    #6aad8a;
            --h-primary:  #24e78f;
            --h-primary-dim: rgba(36,231,143,.12);
            --h-accent:   #2a9863;
            --h-success:  #24e78f;
            --h-warning:  #ffd43b;
            --h-danger:   #ff6b6b;
            --h-blue:     #24e78f;
            --h-shadow:   0 4px 24px rgba(0,0,0,.35);
            --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
        }

        .content-wrapper { background: var(--h-bg) !important; }
        .main-header.navbar, .main-header, nav.main-header, header.main-header { z-index: 1000 !important; }
        .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
        .main-sidebar, aside.main-sidebar { z-index: 999 !important; }

        /* ══ HERO ══ */
        @keyframes pgHeroMeshDrift {
            0%   { transform:translate(0,0)   rotate(0deg); }
            100% { transform:translate(3%,2%) rotate(2deg); }
        }
        @keyframes pgHeroOrbFloat {
            0%,100% { opacity:.4; transform:translate(0,0)       scale(1);    }
            33%      { opacity:.7; transform:translate(18px,-26px) scale(1.05); }
            66%      { opacity:.5; transform:translate(-12px,16px) scale(.95);  }
        }
        .pg-hero { background:#0b1f17; padding:36px 28px 66px; position:relative; overflow:hidden; }
        .pg-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:pgHeroMeshDrift 22s ease-in-out infinite alternate;
            z-index:0;
        }
        .pg-hero-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .pg-orb { position:absolute; border-radius:50%; filter:blur(60px); animation:pgHeroOrbFloat 18s ease-in-out infinite; }
        .pg-orb-1 { width:280px; height:280px; background:rgba(36,231,143,.11); top:-80px;    left:-60px;  animation-duration:21s; }
        .pg-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.10);  bottom:-50px; right:-40px; animation-delay:-7s; animation-duration:17s; }
        .pg-orb-3 { width:160px; height:160px; background:rgba(212,175,55,.06); top:40%;      right:20%;   animation-delay:-13s; animation-duration:24s; }
        .pg-orb-4 { width:120px; height:120px; background:rgba(36,231,143,.07); bottom:15%;   left:15%;    animation-delay:-4s;  animation-duration:15s; }
        .pg-hero-dots {
            position:absolute; inset:0; z-index:0; pointer-events:none;
            background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px);
            background-size:36px 36px;
        }
        .pg-hero-arc {
            position:absolute; top:-50px; right:-50px; width:200px; height:200px; border-radius:50%;
            background:radial-gradient(circle,rgba(36,231,143,.18) 0%,transparent 70%);
            pointer-events:none; z-index:0;
        }
        .pg-hero::after {
            content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px;
            background:var(--body-bg, #eef7f2); clip-path:ellipse(58% 100% at 50% 100%); z-index:1;
        }
        body.dark-mode .pg-hero::after { background:var(--body-bg, #0b1f17); }
        .pg-hero-inner { position:relative; z-index:2; }
        .pg-hero-title {
            color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px;
            letter-spacing:-.3px; text-shadow:0 2px 14px rgba(0,0,0,.45);
            display:flex; align-items:center; gap:10px;
        }
        .pg-hero-sub  { color:rgba(212,245,229,.75); margin:0 0 14px; font-size:.9rem; }
        .pg-hero-divider {
            width:48px; height:2px; border-radius:2px; margin:0 0 12px;
            background:linear-gradient(90deg,transparent,#24e78f,transparent);
        }

        /* ── Card ── */
        .card { background: var(--h-card) !important; border: 1px solid var(--h-border) !important; border-radius: 12px !important; box-shadow: var(--h-shadow-sm) !important; }
        .card-header { background: var(--h-card) !important; border-bottom: 1px solid var(--h-border) !important; padding: 14px 20px !important; display:flex !important; align-items:center !important; justify-content:space-between !important; flex-wrap:wrap !important; gap:10px !important; }
        .card-title { font-weight: 700 !important; font-size: .9rem !important; color: var(--h-text) !important; letter-spacing: -.2px; margin-right: auto !important; }
        .card-body { background: var(--h-card) !important; padding: 18px !important; }
        .card-tools { display:flex !important; align-items:center !important; justify-content:flex-end !important; gap:8px !important; flex-wrap:wrap !important; margin-left: auto !important; }
        .card-tools .btn-primary {
            background: var(--h-primary) !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: .8rem !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .card-tools .btn-primary:hover { filter: brightness(1.1) !important; color:#fff !important; }


        /* ══ SHARED MANAGE-PAGE STYLES ══ */
        .content { margin-top:-38px; }
        .hk-card .card-header { background: var(--h-card) !important; }
        .hk-count {
            display:inline-flex; align-items:center; padding:2px 10px; border-radius:20px; margin-left:8px;
            font-size:.7rem; font-weight:700; background: var(--h-primary-dim); color: var(--h-primary); border:1px solid var(--h-border);
        }

        /* Forms */
        .hk-card label, .modal label { font-size:.68rem !important; font-weight:700 !important; text-transform:uppercase; letter-spacing:.5px; color: var(--h-muted) !important; margin-bottom:5px; }
        .hk-card .form-control, .modal .form-control {
            background: var(--h-card-alt) !important; border: 1.5px solid var(--h-border) !important; color: var(--h-text) !important;
            border-radius: 8px !important; min-height:38px; font-size:.875rem;
        }
        .hk-card .form-control:focus, .modal .form-control:focus { border-color: var(--h-primary) !important; box-shadow: 0 0 0 3px var(--h-primary-dim) !important; }
        .form-control::placeholder { color: var(--h-muted); opacity:.7; }
        .req { color: var(--h-danger); }

        /* Buttons */
        .hk-btn {
            display:inline-flex; align-items:center; justify-content:center; gap:7px; border-radius:8px !important; font-weight:700 !important; font-size:.8rem !important;
            padding:8px 18px !important; border:1px solid transparent !important; cursor:pointer; transition: filter .12s, transform .12s;
        }
        .hk-btn:hover { filter:brightness(1.1); transform: translateY(-1px); text-decoration:none; }
        .hk-btn-primary { background: var(--h-primary) !important; color:#fff !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important; }
        .hk-btn-ghost { background: transparent !important; color: var(--h-text) !important; border-color: var(--h-border) !important; }
        .hk-btn-ghost:hover { color: var(--h-primary) !important; border-color: var(--h-primary) !important; }
        .hk-btn-danger { background: var(--h-danger) !important; color:#fff !important; }
        .hk-btn-block { width:100%; }

        /* Table */
        table.hk-table { width:100%; margin:0 !important; color: var(--h-text) !important; border:none !important; background: transparent !important; }
        table.hk-table thead th {
            background: var(--h-card-alt) !important; border:none !important; border-bottom:1px solid var(--h-border) !important;
            color: var(--h-muted) !important; font-size:.68rem !important; font-weight:700 !important;
            text-transform:uppercase !important; letter-spacing:.6px !important; padding:10px 14px !important; white-space:nowrap;
        }
        table.hk-table tbody td {
            border:none !important; border-top:1px solid var(--h-border) !important; padding:12px 14px !important;
            vertical-align:middle !important; font-size:.85rem !important; color: var(--h-text) !important; background: transparent !important;
        }
        table.hk-table tbody tr:hover td { background: var(--h-card-alt) !important; }
        table.hk-table .cell-name { font-weight:700; }
        table.hk-table .cell-muted { color: var(--h-muted) !important; }
        .hk-pill {
            display:inline-block; padding:2px 10px; border-radius:20px; font-size:.72rem; font-weight:700;
            background: var(--h-primary-dim); color: var(--h-primary); border:1px solid var(--h-border);
        }
        .hk-pill.is-zero { background: transparent; color: var(--h-muted); }
        .hk-actions { display:flex; gap:6px; white-space:nowrap; }
        .hk-icon-btn {
            width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; cursor:pointer;
            border:1px solid var(--h-border); background: var(--h-card-alt); color: var(--h-muted); transition: all .12s; padding:0;
        }
        .hk-icon-btn.is-edit { color: var(--h-primary); background: var(--h-primary-dim); border-color: rgba(42,152,99,.25); }
        .hk-icon-btn.is-edit:hover { background: var(--h-primary); color:#fff; }
        .hk-icon-btn.is-delete { color: var(--h-danger); background: rgba(201,42,42,.08); border-color: rgba(201,42,42,.28); }
        .hk-icon-btn.is-delete:hover { background: var(--h-danger); color:#fff; }
        .hk-icon-btn.is-disabled { opacity:.4; cursor:not-allowed; pointer-events:none; }
        .hk-empty { text-align:center; padding:40px 16px; color: var(--h-muted); }
        .hk-empty i { font-size:2rem; color: var(--h-primary); opacity:.55; margin-bottom:10px; display:block; }
        .hk-empty p { margin:0; font-size:.9rem; }

        /* DataTables */
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info { color: var(--h-muted) !important; font-size:.8rem; }
        .dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input {
            background: var(--h-card-alt) !important; border:1.5px solid var(--h-border) !important; color: var(--h-text) !important; border-radius:8px !important;
        }
        .dataTables_wrapper .page-link { background: var(--h-card) !important; color: var(--h-text) !important; border-color: var(--h-border) !important; font-size:.8rem; }
        .dataTables_wrapper .page-item.active .page-link { background: var(--h-primary) !important; border-color: var(--h-primary) !important; color:#fff !important; }
        .dataTables_wrapper .page-item.disabled .page-link { opacity:.5; }
        .dataTables_wrapper .row:first-child, .dataTables_wrapper .row:last-child { padding: 6px 4px; align-items:center; }

        /* Modals */
        .modal-content { background: var(--h-card) !important; border:1px solid var(--h-border) !important; border-radius:14px !important; box-shadow: var(--h-shadow) !important; color: var(--h-text); }
        .modal-header { border-bottom:1px solid var(--h-border) !important; padding:16px 22px !important; }
        .modal-title { font-weight:800 !important; font-size:1rem !important; color: var(--h-text) !important; display:flex; align-items:center; gap:10px; }
        .modal-title i { color: var(--h-primary); }
        .modal-header .close { color: var(--h-muted) !important; text-shadow:none; opacity:.8; }
        .modal-body { padding:20px 22px !important; }
        .modal-footer { border-top:1px solid var(--h-border) !important; padding:14px 22px !important; }
        .modal-backdrop.show { opacity:.55; }
        .del-warning { display:flex; gap:14px; align-items:flex-start; }
        .del-warning .del-icon { width:42px; height:42px; flex:0 0 42px; border-radius:50%; background: rgba(201,42,42,.10); color: var(--h-danger); display:flex; align-items:center; justify-content:center; }
        .del-warning p { margin:0; font-size:.9rem; }
        .del-warning .del-sub { color: var(--h-muted); font-size:.8rem; margin-top:4px; }
        .alert { display:block; }
        .alert .close { color: inherit !important; opacity:.6; text-shadow:none; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_inventory.php'; ?>

    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="pg-hero">
            <div class="pg-hero-mesh"></div>
            <div class="pg-hero-dots"></div>
            <div class="pg-hero-orbs">
                <div class="pg-orb pg-orb-1"></div>
                <div class="pg-orb pg-orb-2"></div>
                <div class="pg-orb pg-orb-3"></div>
                <div class="pg-orb pg-orb-4"></div>
            </div>
            <div class="pg-hero-arc"></div>
            <div class="pg-hero-inner">
                <div class="pg-hero-title"><i class="fas fa-ruler-combined"></i>Unit of Measure</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Units used when recording and issuing inventory items &middot; <a href="inventory.php" style="color:#24e78f;">Inventory</a> / Unit of Measure</p>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <div class="card hk-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-ruler-combined mr-2"></i>Units of Measure<span class="hk-count"><?= count($units) ?></span></h3>
                        <div class="card-tools">
                            <button type="button" class="hk-btn hk-btn-primary" data-toggle="modal" data-target="#addUnitModal">
                                <i class="fas fa-plus-circle"></i> Add Unit of Measure
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($units)): ?>
                            <div class="hk-empty"><i class="fas fa-ruler-combined"></i><p>No units of measure yet. Add your first one.</p></div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table id="unitTable" class="table hk-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Abbreviation</th>
                                        <th>Description</th>
                                        <th class="text-center">Items</th>
                                        <th>Date Added</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($units as $unit): ?>
                                        <?php $in_use = (int)$unit['item_count']; ?>
                                        <tr>
                                            <td class="cell-name"><?= htmlspecialchars($unit['name']) ?></td>
                                            <td><?php if (($unit['abbreviation'] ?? '') !== ''): ?><span class="hk-pill"><?= htmlspecialchars($unit['abbreviation']) ?></span><?php endif; ?></td>
                                            <td class="cell-muted"><?= htmlspecialchars($unit['description'] ?? '') ?></td>
                                            <td class="text-center"><span class="hk-pill<?= $in_use ? '' : ' is-zero' ?>"><?= $in_use ?></span></td>
                                            <td data-order="<?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($unit['created_at']))) ?>"><?= htmlspecialchars(date('M d, Y', strtotime($unit['created_at']))) ?></td>
                                            <td>
                                                <div class="hk-actions justify-content-end">
                                                    <button type="button" class="hk-icon-btn is-edit edit-unit-btn" title="Edit"
                                                        data-id="<?= $unit['id'] ?>"
                                                        data-name="<?= htmlspecialchars($unit['name']) ?>"
                                                        data-abbreviation="<?= htmlspecialchars($unit['abbreviation'] ?? '') ?>"
                                                        data-description="<?= htmlspecialchars($unit['description'] ?? '') ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($in_use): ?>
                                                        <span title="In use by <?= $in_use ?> item(s) - cannot delete">
                                                            <button type="button" class="hk-icon-btn is-delete is-disabled" tabindex="-1" disabled><i class="fas fa-trash"></i></button>
                                                        </span>
                                                    <?php else: ?>
                                                        <button type="button" class="hk-icon-btn is-delete delete-unit-btn" title="Delete"
                                                            data-id="<?= $unit['id'] ?>"
                                                            data-name="<?= htmlspecialchars($unit['name']) ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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

<!-- Add Unit Modal -->
<div class="modal fade" id="addUnitModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="POST" action="unit_of_measure.php">
            <input type="hidden" name="action" value="add">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i>Add Unit of Measure</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="req">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="50" placeholder="e.g. Piece">
                    </div>
                    <div class="form-group">
                        <label>Abbreviation</label>
                        <input type="text" name="abbreviation" class="form-control" maxlength="10" placeholder="e.g. pc">
                    </div>
                    <div class="form-group mb-0">
                        <label>Description</label>
                        <textarea name="description" class="form-control" maxlength="100" rows="2" placeholder="Optional"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="hk-btn hk-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="hk-btn hk-btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Unit Modal -->
<div class="modal fade" id="editUnitModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="POST" action="unit_of_measure.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i>Edit Unit of Measure</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="req">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label>Abbreviation</label>
                        <input type="text" name="abbreviation" id="edit_abbreviation" class="form-control" maxlength="10">
                    </div>
                    <div class="form-group mb-0">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" maxlength="100" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="hk-btn hk-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="hk-btn hk-btn-primary"><i class="fas fa-save"></i> Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="deleteUnitModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="POST" action="unit_of_measure.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delete_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash"></i>Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="del-warning">
                        <div class="del-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div>
                            <p>Are you sure you want to delete <strong id="delete_name"></strong>?</p>
                            <p class="del-sub">This cannot be undone.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="hk-btn hk-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="hk-btn hk-btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        if ($('#unitTable').length) {
            $('#unitTable').DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "order": [[0, "asc"]],
                "pageLength": 25,
                "columnDefs": [{ "orderable": false, "targets": 5 }]
            });
        }

        // Delegated handlers so the buttons also work on rows that DataTables
        // is not showing yet (other pages, after a search, after sorting)
        $(document).on('click', '.edit-unit-btn', function() {
            $('#edit_id').val($(this).data('id'));
            $('#edit_name').val($(this).data('name'));
            $('#edit_abbreviation').val($(this).data('abbreviation'));
            $('#edit_description').val($(this).data('description'));
            $('#editUnitModal').modal('show');
        });

        $(document).on('click', '.delete-unit-btn', function() {
            $('#delete_id').val($(this).data('id'));
            $('#delete_name').text($(this).data('name'));
            $('#deleteUnitModal').modal('show');
        });
    });
</script>
</body>
</html>