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

// Fetch RIS records
$ris_records = [];
$ris_query = "SELECT r.*, u.user as created_by_name, COUNT(ri.id) as item_count 
             FROM ris_records r 
             LEFT JOIN users u ON r.created_by = u.id 
             LEFT JOIN ris_items ri ON r.id = ri.ris_id 
             GROUP BY r.id 
             ORDER BY r.created_at DESC";
$ris_result = $db->query($ris_query);
if ($ris_result) {
    while ($row = $ris_result->fetch_assoc()) {
        $ris_records[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RIS Records - Inventory Management</title>
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

        /* ── Table ── */
        #risTable.table { color: var(--h-text) !important; }
        #risTable.table thead th {
            background: var(--h-card-alt) !important; border: none !important; border-bottom: 1px solid var(--h-border) !important;
            color: var(--h-muted) !important; font-size: .68rem !important; font-weight: 700 !important;
            text-transform: uppercase !important; letter-spacing: .6px !important; padding: 10px 14px !important;
        }
        #risTable.table tbody tr { transition: background .12s; }
        #risTable.table tbody tr:hover { background: var(--h-card-alt) !important; }
        #risTable.table tbody td {
            border-top: 1px solid var(--h-border) !important; border-left: none !important; border-right: none !important;
            padding: 12px 14px !important; vertical-align: middle !important; font-size: .85rem !important; color: var(--h-text) !important;
        }
        #risTable.table-bordered { border: none !important; }
        .table-responsive { border-radius: 8px; overflow: hidden; }

        /* Row action buttons */
        .action-buttons { display: flex; gap: 5px; }
        .action-buttons .btn.btn-info {
            background: rgba(42,152,99,.12) !important; color: var(--h-blue) !important; border: 1px solid rgba(42,152,99,.25) !important; border-radius: 7px !important;
        }
        .action-buttons .btn:hover { filter: brightness(1.15); transform: translateY(-1px); }

        /* Alerts */
        .alert-success { background: var(--h-primary-dim) !important; color: var(--h-primary) !important; border: 1px solid var(--h-border) !important; border-radius: 10px !important; }
        .alert-danger  { background: rgba(201,34,46,.10) !important; color: var(--h-danger) !important; border: 1px solid rgba(201,34,46,.25) !important; border-radius: 10px !important; }
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
                <div class="pg-hero-title"><i class="fas fa-file-export"></i>RIS Records</div>
                <div class="pg-hero-divider"></div>
                <p class="pg-hero-sub">Requisition and Issue Slip records issued against delivered items</p>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                    <div class="alert alert-success">RIS created successfully!</div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header" style="display:flex !important; justify-content:space-between !important; align-items:center !important;">
                        <h3 class="card-title" style="margin-right:auto !important;">Requisition and Issue Slip Records</h3>
                        <div class="card-tools" style="display:flex !important; justify-content:flex-end !important; margin-left:auto !important;">
                            <a href="delivery_entry.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to Delivery Management
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="risTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>RIS Number</th>
                                        <th>Requisition Office</th>
                                        <th>Purpose</th>
                                        <th>Requested By</th>
                                        <th>Items</th>
                                        <th>Created By</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ris_records as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['ris_number']) ?></td>
                                        <td><?= htmlspecialchars($record['requisition_office']) ?></td>
                                        <td><?= htmlspecialchars($record['purpose']) ?></td>
                                        <td><?= htmlspecialchars($record['requested_by']) ?></td>
                                        <td class="text-center"><?= $record['item_count'] ?></td>
                                        <td><?= htmlspecialchars($record['created_by_name']) ?></td>
                                        <td><?= date('M j, Y g:i A', strtotime($record['created_at'])) ?></td>
                                        <td class="action-buttons">
                                            <a href="ris_view.php?id=<?= $record['id'] ?>" class="btn btn-info btn-sm" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        $('#risTable').DataTable({
            "responsive": true,
            "autoWidth": false,
            "order": [[6, 'desc']]
        });
    });
</script>
</body>
</html>