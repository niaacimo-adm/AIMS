<?php
require_once '../config/database.php';

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

// Get activity logs with filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$action_type = $_GET['action_type'] ?? '';

$query = "SELECT sm.*, i.name as item_name, sm.created_at as action_date 
          FROM stock_movements sm 
          JOIN items i ON sm.item_id = i.id 
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($date_from)) {
    $query .= " AND DATE(sm.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND DATE(sm.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($action_type) && $action_type != 'all') {
    $query .= " AND sm.movement_type = ?";
    $params[] = $action_type;
    $types .= 's';
}

$query .= " ORDER BY sm.created_at DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management Dashboard</title>
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
            --h-purple:   #6f42c1;
            --h-teal:     #17a2b8;
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
            --h-purple:   #b794f6;
            --h-teal:     #4fd1c5;
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
        .pg-hero-layout { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; position:relative; z-index:2; }

        /* ── Card ── */
        .card { background: var(--h-card) !important; border: 1px solid var(--h-border) !important; border-radius: 12px !important; box-shadow: var(--h-shadow-sm) !important; }
        .card-header { background: var(--h-card) !important; border-bottom: 1px solid var(--h-border) !important; padding: 14px 20px !important; display:flex !important; align-items:center !important; justify-content:space-between !important; flex-wrap:wrap !important; gap:10px !important; }
        .card-title { font-weight: 700 !important; font-size: .9rem !important; color: var(--h-text) !important; letter-spacing: -.2px; margin-right: auto !important; margin-bottom: 0 !important; }
        .card-body { background: var(--h-card) !important; padding: 22px !important; }
        .card-tools { display:flex !important; align-items:center !important; justify-content:flex-end !important; gap:8px !important; flex-wrap:wrap !important; margin-left: auto !important; }

        /* ── Section label ── */
        .section-label {
            font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
            color: var(--h-muted) !important; margin: 0 0 14px; display:flex; align-items:center; gap:8px;
        }
        .section-label::after { content:''; flex:1; height:1px; background: var(--h-border); }

        /* ── Premium stat cards (replaces AdminLTE small-box) ── */
        .stat-card {
            background: var(--h-card) !important; border: 1px solid var(--h-border) !important; border-radius: 10px !important;
            box-shadow: var(--h-shadow-sm) !important; padding: 12px 14px !important; position: relative; overflow: hidden;
            transition: transform .25s ease, box-shadow .25s ease; height: 100%;
        }
        .stat-card::before { content:''; position:absolute; top:0; left:0; width:3px; height:100%; background: var(--stat-accent, var(--h-primary)); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--h-shadow) !important; }
        .stat-card .stat-icon {
            width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center;
            font-size:.75rem; margin-bottom:8px; background: color-mix(in srgb, var(--stat-accent, var(--h-primary)) 14%, transparent);
            color: var(--stat-accent, var(--h-primary));
        }
        .stat-card h3 { font-size:1.15rem; font-weight:800; margin:0 0 2px; color:var(--h-text) !important; letter-spacing:-.3px; }
        .stat-card p { margin:0 0 8px; color:var(--h-muted) !important; font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
        .stat-card .stat-link {
            font-size:.68rem; font-weight:700; color: var(--stat-accent, var(--h-primary)) !important; text-decoration:none !important;
            display:inline-flex; align-items:center; gap:4px; transition: gap .2s ease;
        }
        .stat-card .stat-link:hover { gap:7px; }
        .stat-new-stock  { --stat-accent: var(--h-success); }
        .stat-inventory  { --stat-accent: var(--h-primary); }
        .stat-ris        { --stat-accent: var(--h-warning); }
        .stat-rsmi       { --stat-accent: var(--h-danger); }

        /* ── Premium action cards (replaces dashboard-card) ── */
        .action-card {
            background: var(--h-card) !important; border: 1px solid var(--h-border) !important; border-radius: 14px !important;
            box-shadow: var(--h-shadow-sm) !important; padding: 28px 20px; text-align:center; height:100%;
            display:flex; flex-direction:column; align-items:center;
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .action-card:hover { transform: translateY(-5px); box-shadow: var(--h-shadow) !important; border-color: var(--action-accent, var(--h-primary)) !important; }
        .action-card .action-icon {
            width:60px; height:60px; border-radius:16px; display:flex; align-items:center; justify-content:center;
            margin-bottom:16px; font-size:1.5rem;
            background: color-mix(in srgb, var(--action-accent, var(--h-primary)) 14%, transparent);
            color: var(--action-accent, var(--h-primary));
        }
        .action-card h5 { font-weight:700; font-size:.85rem; margin-bottom:8px; color:var(--h-text) !important; text-transform:uppercase; letter-spacing:.5px; }
        .action-card p { color:var(--h-muted) !important; font-size:.8rem; margin-bottom:20px; flex-grow:1; }
        .action-card .btn {
            border-radius:8px !important; font-weight:700 !important; font-size:.8rem !important; padding:9px 18px !important;
            border:none !important; background: var(--action-accent, var(--h-primary)) !important; color:#fff !important;
            box-shadow: 0 2px 12px color-mix(in srgb, var(--action-accent, var(--h-primary)) 25%, transparent) !important;
        }
        .action-card .btn:hover { filter: brightness(1.08); color:#fff !important; }
        .action-new-stock   { --action-accent: var(--h-success); }
        .action-inventory   { --action-accent: var(--h-primary); }
        .action-stock-types { --action-accent: var(--h-purple); }
        .action-rsmi        { --action-accent: var(--h-danger); }
        .action-ris         { --action-accent: var(--h-warning); }
        .action-ics         { --action-accent: var(--h-teal); }

        /* ── Activity log table ── */
        #activityTable.table { color: var(--h-text) !important; }
        #activityTable.table thead th {
            background: var(--h-card-alt) !important; border: none !important; border-bottom: 1px solid var(--h-border) !important;
            color: var(--h-muted) !important; font-size: .68rem !important; font-weight: 700 !important;
            text-transform: uppercase !important; letter-spacing: .6px !important; padding: 10px 14px !important;
        }
        #activityTable.table tbody tr { transition: background .12s; }
        #activityTable.table tbody tr:hover { background: var(--h-card-alt) !important; }
        #activityTable.table tbody td {
            border-top: 1px solid var(--h-border) !important; border-left: none !important; border-right: none !important;
            padding: 12px 14px !important; vertical-align: middle !important; font-size: .85rem !important; color: var(--h-text) !important;
        }
        #activityTable.table-bordered { border: none !important; }

        /* Status badges */
        .badge-secondary { background: var(--h-surface3) !important; color: var(--h-muted) !important; border-radius: 5px !important; }
        .badge-success   { background: rgba(42,152,99,.15) !important; color: var(--h-success) !important; border-radius: 5px !important; }
        .badge-danger    { background: rgba(201,34,46,.15) !important; color: var(--h-danger) !important; border-radius: 5px !important; }

        /* Filter form (Activity Logs card header) */
        .filter-form .input-group { width: auto; flex-wrap: wrap; gap: 8px; }
        .filter-form .form-control {
            background: var(--h-card-alt) !important; border: 1.5px solid var(--h-border) !important; color: var(--h-text) !important;
            border-radius: 8px !important; font-size: .8rem !important; margin-right: 0 !important;
        }
        .filter-form .form-control::placeholder { color: var(--h-muted) !important; }
        .filter-form .form-control:focus { border-color: var(--h-primary) !important; box-shadow: 0 0 0 3px var(--h-primary-dim) !important; }
        .filter-form .input-group-append { display:flex; gap:8px; }
        .filter-form .btn-primary {
            background: var(--h-primary) !important; border: none !important; border-radius: 8px !important;
            font-weight: 700 !important; font-size: .8rem !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .filter-form .btn-primary:hover { filter: brightness(1.1) !important; }
        .filter-form .btn-secondary {
            background: var(--h-card-alt) !important; color: var(--h-muted) !important; border: 1px solid var(--h-border) !important;
            border-radius: 8px !important; font-weight: 700 !important; font-size: .8rem !important;
        }
        .filter-form .btn-secondary:hover { filter: brightness(1.05); }

        .custom-file-input:lang(en)~.custom-file-label::after { content: "Browse"; }
        #preview { max-width: 100%; height: auto; }
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
            <div class="pg-hero-layout">
                <div class="pg-hero-inner">
                    <div class="pg-hero-title"><i class="fas fa-warehouse"></i>Inventory Dashboard</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Overview, quick actions, and recent stock activity</p>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- Stat cards -->
                <div class="row">
                    <div class="col-lg-3 col-6 mb-4">
                        <div class="stat-card stat-new-stock">
                            <div class="stat-icon"><i class="fas fa-plus-circle"></i></div>
                            <h3>150</h3>
                            <p>New Stock Items</p>
                            <a href="new_stock.php" class="stat-link">More info <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6 mb-4">
                        <div class="stat-card stat-inventory">
                            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                            <h3>53<sup style="font-size: 1rem;">%</sup></h3>
                            <p>Inventory Growth</p>
                            <a href="view_inventory.php" class="stat-link">More info <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6 mb-4">
                        <div class="stat-card stat-ris">
                            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                            <h3>44</h3>
                            <p>RIS Requests</p>
                            <a href="#" class="stat-link">More info <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6 mb-4">
                        <div class="stat-card stat-rsmi">
                            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <h3>65</h3>
                            <p>RSMI Reports</p>
                            <a href="#" class="stat-link">More info <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Main row -->
                <div class="row">
                    <section class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Inventory Management</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-4">
                                        <div class="action-card action-new-stock">
                                            <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                                            <h5>New Stock</h5>
                                            <p>Add new items to your inventory</p>
                                            <a href="new_stock.php" class="btn">Add New Stock</a>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="action-card action-inventory">
                                            <div class="action-icon"><i class="fas fa-boxes"></i></div>
                                            <h5>Inventory</h5>
                                            <p>View and manage your inventory</p>
                                            <a href="view_inventory.php" class="btn">View Inventory</a>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="action-card action-stock-types">
                                            <div class="action-icon"><i class="fas fa-tags"></i></div>
                                            <h5>Stock Types</h5>
                                            <p>Manage categories and types</p>
                                            <a href="manage_types.php" class="btn">Manage Types</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-2">
                                    <div class="col-md-4 mb-4">
                                        <div class="action-card action-rsmi">
                                            <div class="action-icon"><i class="fas fa-exclamation-triangle"></i></div>
                                            <h5>RSMI</h5>
                                            <p>Report of Supplies and Materials Issued</p>
                                            <a href="#" class="btn">RSMI Reports</a>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="action-card action-ris">
                                            <div class="action-icon"><i class="fas fa-file-alt"></i></div>
                                            <h5>RIS</h5>
                                            <p>Requisition and Issue Slip</p>
                                            <a href="ris_records.php" class="btn">RIS Reports</a>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-4">
                                        <div class="action-card action-ics">
                                            <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                                            <h5>ICS</h5>
                                            <p>ICS management section</p>
                                            <a href="#" class="btn">ICS Tools</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Logs Section -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h3 class="card-title">Inventory Activity Logs</h3>
                                <div class="card-tools">
                                    <form method="GET" class="form-inline filter-form">
                                        <div class="input-group input-group-sm">
                                            <input type="date" name="date_from" class="form-control"
                                                   value="<?= htmlspecialchars($date_from) ?>" placeholder="From Date">
                                            <input type="date" name="date_to" class="form-control"
                                                   value="<?= htmlspecialchars($date_to) ?>" placeholder="To Date">
                                            <select class="form-control" name="action_type">
                                                <option value="all">All Actions</option>
                                                <option value="in" <?= $action_type == 'in' ? 'selected' : '' ?>>Stock In</option>
                                                <option value="out" <?= $action_type == 'out' ? 'selected' : '' ?>>Stock Out</option>
                                            </select>
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-filter"></i> Filter
                                                </button>
                                                <a href="inventory.php" class="btn btn-secondary">
                                                    <i class="fas fa-sync"></i> Reset
                                                </a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="activityTable" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Item</th>
                                                <th>Action Type</th>
                                                <th>Quantity</th>
                                                <th>Reference</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($logs)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">No activity logs found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($logs as $log): ?>
                                                    <tr>
                                                        <td><?= date('Y-m-d H:i:s', strtotime($log['action_date'])) ?></td>
                                                        <td><?= htmlspecialchars($log['item_name']) ?></td>
                                                        <td>
                                                            <span class="badge badge-<?= $log['movement_type'] == 'in' ? 'success' : 'danger' ?>">
                                                                <?= strtoupper($log['movement_type']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= $log['quantity'] ?></td>
                                                        <td><?= htmlspecialchars($log['reference']) ?></td>
                                                        <td><?= htmlspecialchars($log['notes']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        $('#activityTable').DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "order": [[0, "desc"]],
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#activityTable_wrapper .col-md-6:eq(0)');
    });
</script>
</body>
</html>