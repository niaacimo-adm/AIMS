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

$database = new Database();
$db = $database->getConnection();

$query = "SELECT s.*, 
                 o.office_name,
                 CONCAT(e.first_name, ' ', e.last_name) as head_name,
                 (SELECT COUNT(*) FROM files WHERE section_id = s.section_id) as file_count
          FROM section s
          LEFT JOIN office o ON s.office_id = o.office_id
          LEFT JOIN employee e ON s.head_emp_id = e.emp_id
          ORDER BY s.section_name";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

$stats = [];
$stmt = $db->prepare("SELECT COUNT(*) as total FROM files");
$stmt->execute();
$stats['total_files'] = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as new FROM files WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$stats['new_files'] = $stmt->get_result()->fetch_assoc()['new'];

$stmt = $db->prepare("SELECT COUNT(*) as pending FROM files WHERE status = 'pending'");
$stmt->execute();
$stats['pending'] = $stmt->get_result()->fetch_assoc()['pending'];

$stmt = $db->prepare("SELECT COUNT(*) as approved FROM files WHERE status = 'approved'");
$stmt->execute();
$stats['approved'] = $stmt->get_result()->fetch_assoc()['approved'];

$stats['sections'] = count($sections);

$stmt = $db->prepare("SELECT COUNT(*) as manager_files FROM files WHERE section_id IS NULL OR section_id = 0");
$stmt->execute();
$stats['manager_files'] = $stmt->get_result()->fetch_assoc()['manager_files'];

// Fetch recent activity (last 10 file uploads across all sections)
$recent_stmt = $db->prepare(
    "SELECT f.file_name, f.file_type, f.created_at, f.file_size,
            CONCAT(e.first_name, ' ', e.last_name) as uploader,
            s.section_name
     FROM files f
     LEFT JOIN employee e ON f.uploaded_by = e.emp_id
     LEFT JOIN section s ON f.section_id = s.section_id
     WHERE (f.is_deleted IS NULL OR f.is_deleted = 0)
     ORDER BY f.created_at DESC
     LIMIT 8"
);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();
$recent_files = [];
while ($row = $recent_result->fetch_assoc()) {
    $recent_files[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Management Dashboard</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/file.css">
    <style>
        /* ── Layout override: no AdminLTE sidebar ─────────────── */
        .main-sidebar, .control-sidebar { display: none !important; }
        .wrapper, .content-wrapper, .main-footer { margin-left: 0 !important; width: 100% !important; }
        .main-header { margin-left: 0 !important; width: 100% !important; }
        body.sidebar-mini.layout-fixed .content-wrapper { margin-left: 0 !important; }

        /* ── Token colours ────────────────────────────────────── */
        :root {
            --brand:       #800020;
            --brand-dark:  #5a0a1d;
            --brand-light: rgba(128,0,32,.08);
            --radius:      14px;
            --radius-sm:   8px;
            --shadow:      0 2px 12px rgba(0,0,0,.07);
            --shadow-md:   0 6px 24px rgba(0,0,0,.11);
            --transition:  all .25s ease;
        }

        /* ── Page shell ───────────────────────────────────────── */
        body { background: #f2f4f8; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        .page-header {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            padding: 28px 32px 56px;
            position: relative;
            overflow: hidden;
        }
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -28px; left: 0; right: 0;
            height: 56px;
            background: #f2f4f8;
            border-radius: 50% 50% 0 0 / 100% 100% 0 0;
        }
        body.dark-mode .page-header::after { background: var(--body-bg, #0f172a); }
        .page-header h1 { color: #fff; font-size: 1.7rem; font-weight: 700; margin: 0; }
        .page-header p  { color: rgba(255,255,255,.8); margin: 4px 0 0; font-size: .9rem; }

        /* ── Stat cards ───────────────────────────────────────── */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        @media (max-width: 992px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .stats-row { grid-template-columns: 1fr; } }

        .stat-card {
            background: #fff;
            border-radius: var(--radius);
            padding: 20px 22px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--transition);
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: rgba(128,0,32,.15); }
        .stat-card .stat-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }
        .stat-card .stat-value { font-size: 1.8rem; font-weight: 700; line-height: 1; color: #1e293b; }
        .stat-card .stat-label { font-size: .8rem; color: #64748b; margin-top: 2px; }
        .stat-card .stat-trend {
            position: absolute; top: 12px; right: 14px;
            font-size: .72rem; font-weight: 600;
            padding: 2px 8px; border-radius: 20px;
        }

        .stat-icon.purple { background: #ede9fe; color: #7c3aed; }
        .stat-icon.pink   { background: #fce7f3; color: #db2777; }
        .stat-icon.blue   { background: #dbeafe; color: #2563eb; }
        .stat-icon.green  { background: #dcfce7; color: #16a34a; }
        .stat-trend.up    { background: #dcfce7; color: #16a34a; }
        .stat-trend.warn  { background: #fef3c7; color: #d97706; }

        /* ── Main content area ────────────────────────────────── */
        .main-content { display: grid; grid-template-columns: 1fr 320px; gap: 20px; }
        @media (max-width: 1200px) { .main-content { grid-template-columns: 1fr; } }

        /* ── Section grid ─────────────────────────────────────── */
        .fm-card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .fm-card-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f0f0f5;
            display: flex; align-items: center; justify-content: space-between;
        }
        .fm-card-header h5 { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0; }
        .fm-card-body { padding: 18px 22px; }

        /* Search bar */
        .section-search-wrap {
            position: relative; margin-bottom: 18px;
        }
        .section-search-wrap input {
            width: 100%; padding: 10px 16px 10px 40px;
            border: 1px solid #e2e8f0; border-radius: var(--radius-sm);
            font-size: .875rem; background: #f8fafc; color: #1e293b;
            transition: var(--transition);
        }
        .section-search-wrap input:focus { outline: none; border-color: var(--brand); background: #fff; box-shadow: 0 0 0 3px rgba(128,0,32,.1); }
        .section-search-wrap i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        /* Section cards grid */
        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
        }

        .sc-item {
            border-radius: var(--radius-sm);
            border: 1px solid #e8eaf0;
            background: #fff;
            padding: 16px;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: block;
        }
        .sc-item::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--brand), var(--brand-dark));
        }
        /* Colour variants for ::before */
        .sc-item:nth-child(8n+2)::before { background: linear-gradient(90deg, #f093fb, #f5576c); }
        .sc-item:nth-child(8n+3)::before { background: linear-gradient(90deg, #4facfe, #00f2fe); }
        .sc-item:nth-child(8n+4)::before { background: linear-gradient(90deg, #43e97b, #38f9d7); }
        .sc-item:nth-child(8n+5)::before { background: linear-gradient(90deg, #fa709a, #fee140); }
        .sc-item:nth-child(8n+6)::before { background: linear-gradient(90deg, #a18cd1, #fbc2eb); }
        .sc-item:nth-child(8n+7)::before { background: linear-gradient(90deg, #84fab0, #8fd3f4); }
        .sc-item:nth-child(8n+8)::before { background: linear-gradient(90deg, #fccb90, #d57eeb); }

        .sc-item:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.1); border-color: #c8d0e0; }
        .sc-item:hover .sc-arrow { opacity: 1; transform: translateX(0); }

        .sc-icon { font-size: 1.6rem; margin-bottom: 10px; color: var(--brand); }
        .sc-item:nth-child(8n+2) .sc-icon { color: #f5576c; }
        .sc-item:nth-child(8n+3) .sc-icon { color: #00c6ff; }
        .sc-item:nth-child(8n+4) .sc-icon { color: #38f9d7; }
        .sc-item:nth-child(8n+5) .sc-icon { color: #fa709a; }
        .sc-item:nth-child(8n+6) .sc-icon { color: #a18cd1; }
        .sc-item:nth-child(8n+7) .sc-icon { color: #43b89c; }
        .sc-item:nth-child(8n+8) .sc-icon { color: #d57eeb; }

        .sc-name {
            font-weight: 700; font-size: .9rem; color: #1e293b;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            margin-bottom: 4px;
        }
        .sc-meta { font-size: .75rem; color: #64748b; margin-bottom: 8px; }
        .sc-meta span { display: inline-flex; align-items: center; gap: 4px; margin-right: 8px; }
        .sc-badges { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 8px; }
        .sc-badge {
            font-size: .68rem; font-weight: 600;
            padding: 2px 8px; border-radius: 20px;
            background: #f1f5f9; color: #475569;
        }
        .sc-badge.brand { background: var(--brand-light); color: var(--brand); }

        .sc-arrow {
            position: absolute; right: 14px; top: 50%;
            transform: translateX(4px) translateY(-50%);
            opacity: 0; transition: var(--transition);
            color: #94a3b8; font-size: .85rem;
        }

        /* ── Right sidebar ────────────────────────────────────── */
        .right-sidebar > * + * { margin-top: 16px; }

        /* Activity feed */
        .activity-item {
            display: flex; gap: 12px; align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f5;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon {
            width: 34px; height: 34px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .9rem; flex-shrink: 0;
        }
        .ai-pdf   { background: #fee2e2; color: #dc2626; }
        .ai-doc   { background: #dbeafe; color: #2563eb; }
        .ai-xls   { background: #dcfce7; color: #16a34a; }
        .ai-img   { background: #fce7f3; color: #db2777; }
        .ai-ppt   { background: #fef3c7; color: #d97706; }
        .ai-other { background: #f1f5f9; color: #64748b; }

        .activity-info { flex: 1; min-width: 0; }
        .activity-name {
            font-size: .82rem; font-weight: 600; color: #1e293b;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .activity-sub { font-size: .73rem; color: #94a3b8; margin-top: 2px; }

        /* Quick-action buttons */
        .quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .qa-btn {
            display: flex; flex-direction: column; align-items: center;
            gap: 6px; padding: 14px 10px;
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: var(--radius-sm); cursor: pointer;
            transition: var(--transition); color: #475569;
            font-size: .78rem; font-weight: 600; text-decoration: none;
            text-align: center;
        }
        .qa-btn i { font-size: 1.2rem; }
        .qa-btn:hover { background: var(--brand-light); border-color: var(--brand); color: var(--brand); }

        /* Storage bar */
        .storage-bar-wrap { margin-top: 8px; }
        .storage-bar-bg   { background: #e2e8f0; border-radius: 99px; height: 8px; overflow: hidden; }
        .storage-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--brand), var(--brand-dark)); transition: width .6s ease; }
        .storage-label    { display: flex; justify-content: space-between; font-size: .75rem; color: #64748b; margin-bottom: 6px; }

        /* Empty state */
        .empty-state {
            text-align: center; padding: 40px 20px; color: #94a3b8;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; display: block; }

        /* ── Dark mode overrides ──────────────────────────────── */
        body.dark-mode .stat-card,
        body.dark-mode .fm-card,
        body.dark-mode .sc-item { background: var(--card-bg, #1e293b) !important; border-color: var(--card-border, #334155) !important; }
        body.dark-mode .stat-card .stat-value,
        body.dark-mode .sc-name,
        body.dark-mode .fm-card-header h5 { color: var(--text-primary, #f1f5f9) !important; }
        body.dark-mode .section-search-wrap input { background: var(--input-bg, #1e293b) !important; color: var(--input-color, #f1f5f9) !important; border-color: var(--input-border, #334155) !important; }
        body.dark-mode .qa-btn { background: var(--card-bg, #1e293b) !important; border-color: var(--card-border, #334155) !important; color: var(--text-secondary, #cbd5e1) !important; }
        body.dark-mode .qa-btn:hover { background: rgba(128,0,32,.2) !important; border-color: var(--brand) !important; color: #f87171 !important; }
        body.dark-mode .activity-item { border-color: var(--card-border, #334155) !important; }
        body.dark-mode .activity-name { color: var(--text-primary, #f1f5f9) !important; }
        body.dark-mode .sc-badge { background: var(--card-border, #334155) !important; color: var(--text-secondary, #cbd5e1) !important; }
        body.dark-mode .fm-card-header { border-color: var(--card-border, #334155) !important; }
        body.dark-mode .storage-bar-bg { background: var(--card-border, #334155) !important; }
        body.dark-mode body { background: var(--body-bg, #0f172a) !important; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>

    <div class="content-wrapper" style="margin-left:0 !important; width:100% !important; background:transparent;">

        <!-- Page header banner -->
        <div class="page-header">
            <h1><i class="fas fa-hdd mr-2"></i> File Management</h1>
            <p>Manage files and folders across all sections and offices</p>
        </div>

        <div style="padding: 0 24px 24px; margin-top: -20px; position: relative; z-index: 2;">

            <!-- ── Stat cards ── -->
            <div class="stats-row mb-4">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-folder-open"></i></div>
                    <div>
                        <div class="stat-value"><?= number_format($stats['total_files']) ?></div>
                        <div class="stat-label">Total Files</div>
                    </div>
                    <span class="stat-trend up"><i class="fas fa-arrow-up"></i> All</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pink"><i class="fas fa-star"></i></div>
                    <div>
                        <div class="stat-value"><?= number_format($stats['new_files']) ?></div>
                        <div class="stat-label">New This Week</div>
                    </div>
                    <span class="stat-trend up"><i class="fas fa-arrow-up"></i> 7d</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="stat-value"><?= number_format($stats['pending']) ?></div>
                        <div class="stat-label">Pending Approval</div>
                    </div>
                    <?php if ($stats['pending'] > 0): ?>
                    <span class="stat-trend warn"><i class="fas fa-exclamation"></i> Review</span>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="stat-value"><?= number_format($stats['approved']) ?></div>
                        <div class="stat-label">Approved Files</div>
                    </div>
                    <span class="stat-trend up"><i class="fas fa-check"></i> OK</span>
                </div>
            </div>

            <!-- Session alerts -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- ── Main grid ── -->
            <div class="main-content">

                <!-- LEFT: Sections -->
                <div>
                    <div class="fm-card">
                        <div class="fm-card-header">
                            <h5><i class="fas fa-th-large mr-2" style="color:var(--brand)"></i>Sections &amp; Offices</h5>
                            <span class="badge" style="background:var(--brand-light);color:var(--brand);font-size:.78rem;padding:4px 10px;border-radius:20px;">
                                <?= count($sections) + 1 ?> sections
                            </span>
                        </div>
                        <div class="fm-card-body">

                            <!-- Search -->
                            <div class="section-search-wrap">
                                <i class="fas fa-search"></i>
                                <input type="text" id="sectionSearch" placeholder="Search sections…" autocomplete="off">
                            </div>

                            <div class="sections-grid" id="sectionsGrid">

                                <?php if (empty($sections)): ?>
                                    <div class="empty-state" style="grid-column:1/-1">
                                        <i class="fas fa-layer-group"></i>
                                        <p>No sections found. Add sections to get started.</p>
                                    </div>
                                <?php else: ?>

                                    <!-- Manager's Office card -->
                                    <a href="section_files.php?section_id=manager" class="sc-item" data-name="manager's office">
                                        <div class="sc-icon"><i class="fas fa-user-tie"></i></div>
                                        <div class="sc-name">Manager's Office</div>
                                        <div class="sc-meta">
                                            <span><i class="fas fa-file-alt"></i> <?= $stats['manager_files'] ?> files</span>
                                        </div>
                                        <div class="sc-badges">
                                            <span class="sc-badge brand">IMO</span>
                                            <span class="sc-badge">Executive</span>
                                        </div>
                                        <i class="fas fa-chevron-right sc-arrow"></i>
                                    </a>

                                    <?php foreach ($sections as $section): ?>
                                    <a href="section_files.php?section_id=<?= $section['section_id'] ?>"
                                       class="sc-item"
                                       data-name="<?= strtolower(htmlspecialchars($section['section_name'])) ?>">
                                        <div class="sc-icon"><i class="fas fa-folder"></i></div>
                                        <div class="sc-name"><?= htmlspecialchars($section['section_name']) ?></div>
                                        <div class="sc-meta">
                                            <span><i class="fas fa-file-alt"></i> <?= $section['file_count'] ?> files</span>
                                            <?php if (!empty($section['head_name'])): ?>
                                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($section['head_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="sc-badges">
                                            <span class="sc-badge brand"><?= htmlspecialchars($section['section_code']) ?></span>
                                            <?php if (!empty($section['office_name'])): ?>
                                            <span class="sc-badge"><?= htmlspecialchars($section['office_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <i class="fas fa-chevron-right sc-arrow"></i>
                                    </a>
                                    <?php endforeach; ?>

                                <?php endif; ?>
                            </div><!-- /sections-grid -->

                        </div>
                    </div>
                </div>

                <!-- RIGHT: Sidebar widgets -->
                <div class="right-sidebar">

                    <!-- Quick Actions -->
                    <div class="fm-card">
                        <div class="fm-card-header">
                            <h5><i class="fas fa-bolt mr-2" style="color:#f59e0b"></i>Quick Actions</h5>
                        </div>
                        <div class="fm-card-body">
                            <div class="quick-actions">
                                <a href="section_files.php?section_id=manager" class="qa-btn">
                                    <i class="fas fa-folder-plus"></i> New Folder
                                </a>
                                <a href="section_files.php?section_id=manager" class="qa-btn">
                                    <i class="fas fa-upload"></i> Upload File
                                </a>
                                <a href="recent_files.php" class="qa-btn">
                                    <i class="fas fa-history"></i> Recent Files
                                </a>
                                <a href="starred_files.php" class="qa-btn">
                                    <i class="fas fa-star"></i> Starred
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Storage Overview -->
                    <div class="fm-card">
                        <div class="fm-card-header">
                            <h5><i class="fas fa-hdd mr-2" style="color:#2563eb"></i>Storage Overview</h5>
                        </div>
                        <div class="fm-card-body">
                            <div class="storage-bar-wrap">
                                <?php
                                // Approximate total file size
                                $size_stmt = $db->prepare("SELECT COALESCE(SUM(file_size),0) as total_size FROM files WHERE is_deleted IS NULL OR is_deleted=0");
                                $size_stmt->execute();
                                $total_size_bytes = $size_stmt->get_result()->fetch_assoc()['total_size'];
                                $total_size_mb = round($total_size_bytes / (1024*1024), 1);
                                $quota_mb = 5120; // 5 GB quota — adjust as needed
                                $pct = min(100, round(($total_size_mb / $quota_mb) * 100, 1));
                                ?>
                                <div class="storage-label">
                                    <span><?= $total_size_mb ?> MB used</span>
                                    <span><?= $quota_mb / 1024 ?> GB total</span>
                                </div>
                                <div class="storage-bar-bg">
                                    <div class="storage-bar-fill" style="width:<?= $pct ?>%"></div>
                                </div>
                                <div style="font-size:.72rem;color:#94a3b8;margin-top:6px;"><?= $pct ?>% of quota used</div>
                            </div>
                            <hr style="margin:14px 0;border-color:#f0f0f5;">
                            <div style="display:flex;justify-content:space-between;font-size:.8rem;color:#64748b;">
                                <span><i class="fas fa-layer-group mr-1"></i><?= $stats['sections'] ?> sections</span>
                                <span><i class="fas fa-files-medical mr-1"></i><?= number_format($stats['total_files']) ?> files</span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity Feed -->
                    <div class="fm-card">
                        <div class="fm-card-header">
                            <h5><i class="fas fa-stream mr-2" style="color:#10b981"></i>Recent Uploads</h5>
                            <a href="recent_files.php" style="font-size:.75rem;color:var(--brand);text-decoration:none;">View all</a>
                        </div>
                        <div class="fm-card-body" style="padding-top:4px;">
                            <?php if (empty($recent_files)): ?>
                                <div class="empty-state" style="padding:20px">
                                    <i class="fas fa-inbox" style="font-size:2rem"></i>
                                    <p style="font-size:.85rem">No recent uploads</p>
                                </div>
                            <?php else: ?>
                                <?php
                                function dashIconClass($type) {
                                    $t = strtolower($type);
                                    if (in_array($t, ['pdf'])) return 'ai-pdf';
                                    if (in_array($t, ['doc','docx','txt'])) return 'ai-doc';
                                    if (in_array($t, ['xls','xlsx','csv'])) return 'ai-xls';
                                    if (in_array($t, ['ppt','pptx'])) return 'ai-ppt';
                                    if (in_array($t, ['jpg','jpeg','png','gif','webp'])) return 'ai-img';
                                    return 'ai-other';
                                }
                                function dashIcon($type) {
                                    $t = strtolower($type);
                                    if ($t==='pdf') return 'fa-file-pdf';
                                    if (in_array($t,['doc','docx'])) return 'fa-file-word';
                                    if (in_array($t,['xls','xlsx','csv'])) return 'fa-file-excel';
                                    if (in_array($t,['ppt','pptx'])) return 'fa-file-powerpoint';
                                    if (in_array($t,['jpg','jpeg','png','gif','webp'])) return 'fa-file-image';
                                    return 'fa-file';
                                }
                                function timeAgo($dt) {
                                    $diff = time() - strtotime($dt);
                                    if ($diff < 60) return 'just now';
                                    if ($diff < 3600) return floor($diff/60) . 'm ago';
                                    if ($diff < 86400) return floor($diff/3600) . 'h ago';
                                    return floor($diff/86400) . 'd ago';
                                }
                                ?>
                                <?php foreach ($recent_files as $rf): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?= dashIconClass($rf['file_type']) ?>">
                                        <i class="fas <?= dashIcon($rf['file_type']) ?>"></i>
                                    </div>
                                    <div class="activity-info">
                                        <div class="activity-name" title="<?= htmlspecialchars($rf['file_name']) ?>">
                                            <?= htmlspecialchars($rf['file_name']) ?>
                                        </div>
                                        <div class="activity-sub">
                                            <?= htmlspecialchars($rf['uploader'] ?? 'Unknown') ?>
                                            &middot; <?= htmlspecialchars($rf['section_name'] ?? "Manager's Office") ?>
                                            &middot; <?= timeAgo($rf['created_at']) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /right-sidebar -->
            </div><!-- /main-content -->
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<!-- Section search JS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('sectionSearch');
    if (!input) return;
    input.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#sectionsGrid .sc-item').forEach(function (card) {
            const name = card.dataset.name || '';
            card.style.display = (q === '' || name.includes(q)) ? '' : 'none';
        });
    });
});
</script>

<?php if (!empty($_SESSION['swal_error'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: <?= json_encode($_SESSION['swal_error']['title']) ?>,
        text:  <?= json_encode($_SESSION['swal_error']['text']) ?>,
        icon:  <?= json_encode($_SESSION['swal_error']['icon']) ?>,
        confirmButtonColor: '#800020',
        confirmButtonText: 'OK'
    });
});
</script>
<?php unset($_SESSION['swal_error']); endif; ?>
</body>
</html>