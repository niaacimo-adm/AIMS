<?php
ob_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$db->query("SET time_zone = '+08:00'");

// ── Core counts ──────────────────────────────────────────────────────────────
$counts = ['total'=>0,'incoming'=>0,'outgoing'=>0,'internal'=>0,'pending'=>0,'completed'=>0,'received'=>0,'returned'=>0];
$r = $db->query("SELECT
    COUNT(*)                    AS total,
    SUM(kind='incoming')        AS incoming,
    SUM(kind='outgoing')        AS outgoing,
    SUM(kind='internal')        AS internal,
    SUM(status='pending')       AS pending,
    SUM(status='completed')     AS completed,
    SUM(status='received')      AS received,
    SUM(status='returned')      AS returned
FROM document_records");
if ($r && $row = $r->fetch_assoc()) $counts = array_map('intval', $row);

// ── This month vs last month totals ──────────────────────────────────────────
$month_r = $db->query("
    SELECT
        SUM(MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())) AS this_month,
        SUM(MONTH(created_at)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(NOW()-INTERVAL 1 MONTH)) AS last_month
    FROM document_records
");
$month = ['this_month'=>0,'last_month'=>0];
if ($month_r && $mr = $month_r->fetch_assoc()) $month = array_map('intval', $mr);
$month_delta = $month['this_month'] - $month['last_month'];
$month_pct   = $month['last_month'] > 0 ? round(($month_delta / $month['last_month']) * 100) : ($month['this_month'] > 0 ? 100 : 0);

// ── Daily activity for the past 7 days ───────────────────────────────────────
$activity = [];
$act_r = $db->query("
    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
    FROM document_records
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$act_map = [];
if ($act_r) while ($a = $act_r->fetch_assoc()) $act_map[$a['day']] = (int)$a['cnt'];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $activity[] = ['label' => date('D', strtotime($d)), 'date' => $d, 'cnt' => $act_map[$d] ?? 0];
}
$act_max = max(array_column($activity, 'cnt') ?: [1]);

// ── Top document types ────────────────────────────────────────────────────────
$top_types = [];
$tt_r = $db->query("
    SELECT dt.type_name, COUNT(*) AS cnt
    FROM document_records dr
    LEFT JOIN document_types dt ON dr.document_type_id = dt.id
    WHERE dt.type_name IS NOT NULL
    GROUP BY dt.type_name
    ORDER BY cnt DESC
    LIMIT 5
");
if ($tt_r) while ($t = $tt_r->fetch_assoc()) $top_types[] = $t;
$type_max = !empty($top_types) ? (int)$top_types[0]['cnt'] : 1;

// ── Recent 5 documents (activity feed) ───────────────────────────────────────
$feed_r = $db->query("
    SELECT dr.id, dr.document_number, dr.document_name, dr.kind, dr.status, dr.created_at,
           dt.type_name,
           CONCAT(TRIM(e.first_name),' ',TRIM(e.last_name)) AS created_by
    FROM document_records dr
    LEFT JOIN document_types dt ON dr.document_type_id = dt.id
    LEFT JOIN employee e ON dr.created_by_emp_id = e.emp_id
    ORDER BY dr.created_at DESC
    LIMIT 6
");
$feed = [];
if ($feed_r) while ($f = $feed_r->fetch_assoc()) $feed[] = $f;

// ── Logged-in user ────────────────────────────────────────────────────────────
$logged_name = $_SESSION['username'] ?? 'User';
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Dashboard | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* ════════════════════════════════════════════════════════
           DESIGN TOKENS
        ════════════════════════════════════════════════════════ */
        :root {
            --navy:          #1a3c5e;
            --green:         #24e78f;
            --green-dark:    #2a9863;
            --green-mid:     #1a5c38;
            --green-dim:     #e6f7ef;
            --amber:         #f59e0b;
            --rose:          #f43f5e;
            --violet:        #7c3aed;
            --blue:          #2563eb;

            --surface:       #ffffff;
            --surface-2:     #f8fafb;
            --border:        rgba(42,152,99,.14);
            --text:          #0f2d1e;
            --muted:         #5a7a6a;
            --radius:        14px;
            --radius-sm:     8px;
            --shadow:        0 2px 16px rgba(26,60,94,.08);
            --shadow-md:     0 6px 24px rgba(26,60,94,.13);
        }
        body.dark-mode {
            --surface:    #111c27;
            --surface-2:  #172030;
            --border:     rgba(36,231,143,.10);
            --text:       #d4f5e5;
            --muted:      #5a9a78;
            --shadow:     0 2px 16px rgba(0,0,0,.35);
            --shadow-md:  0 6px 24px rgba(0,0,0,.45);
        }

        body { background: var(--surface-2); }

        /* ── Hero banner ─────────────────────────────────────── */
        .dash-hero {
            background: linear-gradient(135deg, var(--navy) 0%, #0f4c35 55%, var(--green-mid) 100%);
            border-radius: var(--radius);
            padding: 28px 32px;
            color: #fff;
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .dash-hero::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(36,231,143,.10);
        }
        .dash-hero::after {
            content: '';
            position: absolute;
            bottom: -60px; right: 80px;
            width: 140px; height: 140px;
            border-radius: 50%;
            background: rgba(36,231,143,.07);
        }
        .dash-hero-title {
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -.02em;
        }
        .dash-hero-sub {
            font-size: .88rem;
            opacity: .75;
            margin-top: 4px;
        }
        .dash-hero-date {
            font-size: .78rem;
            opacity: .6;
            margin-top: 10px;
            letter-spacing: .04em;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            backdrop-filter: blur(4px);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: .78rem;
            font-weight: 600;
            color: #fff;
        }
        .hero-badge i { color: var(--green); }

        /* ── Stat cards ──────────────────────────────────────── */
        .stat-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
            text-decoration: none !important;
            display: block;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 80px; height: 80px;
            border-radius: 50%;
            opacity: .06;
            transform: translate(20px,-20px);
        }
        .stat-card[data-color="navy"]::after   { background: var(--navy); }
        .stat-card[data-color="blue"]::after   { background: var(--blue); }
        .stat-card[data-color="green"]::after  { background: var(--green-dark); }
        .stat-card[data-color="violet"]::after { background: var(--violet); }
        .stat-card[data-color="amber"]::after  { background: var(--amber); }
        .stat-card[data-color="rose"]::after   { background: var(--rose); }

        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: #fff;
            margin-bottom: 14px;
            flex-shrink: 0;
        }
        .stat-num {
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1;
            color: var(--text);
            letter-spacing: -.03em;
        }
        .stat-lbl {
            font-size: .73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            margin-top: 4px;
        }
        .stat-trend {
            position: absolute;
            bottom: 16px; right: 16px;
            font-size: .7rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
        }
        .trend-up   { background: #d1fae5; color: #065f46; }
        .trend-down { background: #fee2e2; color: #991b1b; }
        .trend-flat { background: #f3f4f6; color: #6b7280; }
        body.dark-mode .trend-up   { background: #064e3b; color: #6ee7b7; }
        body.dark-mode .trend-down { background: #450a0a; color: #fca5a5; }
        body.dark-mode .trend-flat { background: #1f2937; color: #9ca3af; }

        /* ── Widget card (generic) ───────────────────────────── */
        .widget {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .widget-hd {
            padding: 16px 20px 12px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .widget-title {
            font-size: .82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .widget-title i { color: var(--green-dark); font-size: .85rem; }
        .widget-bd { padding: 18px 20px; }

        /* ── Section label ───────────────────────────────────── */
        .section-lbl {
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--muted);
            margin-bottom: 12px;
            padding-left: 2px;
        }

        /* ── Donut chart ─────────────────────────────────────── */
        .donut-wrap {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .donut-svg-wrap {
            position: relative;
            width: 120px; height: 120px;
            flex-shrink: 0;
        }
        .donut-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }
        .donut-center-num  { font-size: 1.5rem; font-weight: 800; color: var(--text); line-height: 1; }
        .donut-center-lbl  { font-size: .62rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .07em; margin-top: 2px; }
        .donut-legend { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 100px; }
        .donut-leg-item {
            display: flex; align-items: center; gap: 8px;
            font-size: .8rem; color: var(--text);
        }
        .donut-leg-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .donut-leg-val { margin-left: auto; font-weight: 700; color: var(--muted); font-size: .78rem; }

        /* ── Bar chart (activity) ────────────────────────────── */
        .bar-chart { display: flex; align-items: flex-end; gap: 6px; height: 80px; }
        .bar-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            flex: 1;
        }
        .bar-fill {
            width: 100%;
            border-radius: 5px 5px 0 0;
            background: linear-gradient(180deg, var(--green) 0%, var(--green-dark) 100%);
            min-height: 4px;
            transition: height .4s cubic-bezier(.4,0,.2,1);
            position: relative;
            cursor: default;
        }
        .bar-fill:hover { filter: brightness(1.1); }
        .bar-fill[data-cnt="0"] { background: var(--border); }
        .bar-day {
            font-size: .62rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .bar-cnt {
            font-size: .62rem;
            font-weight: 700;
            color: var(--green-dark);
        }
        body.dark-mode .bar-cnt { color: var(--green); }

        /* ── Type breakdown horizontal bars ─────────────────── */
        .type-row { margin-bottom: 12px; }
        .type-row-hd {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        .type-name {
            font-size: .79rem;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .type-cnt {
            font-size: .75rem;
            font-weight: 700;
            color: var(--muted);
        }
        .type-bar-track {
            height: 7px;
            background: var(--border);
            border-radius: 99px;
            overflow: hidden;
        }
        .type-bar-fill {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, var(--green-dark), var(--green));
            transition: width .6s cubic-bezier(.4,0,.2,1);
        }

        /* ── Activity feed ───────────────────────────────────── */
        .feed-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 11px 0;
            border-bottom: 1px solid var(--border);
        }
        .feed-item:last-child { border-bottom: none; padding-bottom: 0; }
        .feed-dot {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; color: #fff; flex-shrink: 0; margin-top: 1px;
        }
        .feed-doc-num {
            font-size: .72rem;
            font-weight: 700;
            background: var(--green-dim);
            color: var(--green-mid);
            border-radius: 5px;
            padding: 1px 6px;
            display: inline-block;
            margin-bottom: 2px;
        }
        body.dark-mode .feed-doc-num { background: rgba(36,231,143,.12); color: var(--green); }
        .feed-name {
            font-size: .81rem;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }
        .feed-meta {
            font-size: .72rem;
            color: var(--muted);
            margin-top: 2px;
        }
        .feed-time {
            font-size: .68rem;
            color: var(--muted);
            white-space: nowrap;
            margin-left: auto;
            padding-top: 4px;
        }

        /* ── Kind + status mini badges ───────────────────────── */
        .kb { display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em; }
        .kb-incoming { background:#dbeafe;color:#1d4ed8; }
        .kb-outgoing { background:#dcfce7;color:#166534; }
        .kb-internal { background:#ede9fe;color:#5b21b6; }
        .kb-external { background:#ede9fe;color:#5b21b6; }
        .sb { display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em; }
        .sb-pending   { background:#fff7ed;color:#c2410c; }
        .sb-received  { background:#e6f7ef;color:#1c4d38; }
        .sb-completed { background:#d1fae5;color:#065f46; }
        .sb-returned  { background:#fce7f3;color:#9d174d; }
        body.dark-mode .kb-incoming { background:#1e3a5f;color:#93c5fd; }
        body.dark-mode .kb-outgoing { background:#14532d;color:#86efac; }
        body.dark-mode .kb-internal { background:#2e1065;color:#c4b5fd; }
        body.dark-mode .kb-external { background:#2e1065;color:#c4b5fd; }

        /* ── Quick-action tiles ──────────────────────────────── */
        .qa-grid { display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
        .qa-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 10px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--surface-2);
            text-decoration: none !important;
            transition: all .18s;
            gap: 8px;
            text-align: center;
        }
        .qa-tile:hover {
            border-color: var(--green-dark);
            background: var(--green-dim);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        body.dark-mode .qa-tile:hover { background: rgba(42,152,99,.12); }
        .qa-tile-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: #fff;
        }
        .qa-tile-lbl { font-size: .75rem; font-weight: 700; color: var(--text); line-height: 1.25; }
        .qa-tile-sub { font-size: .66rem; color: var(--muted); }

        /* ── Progress ring for pending ───────────────────────── */
        .ring-wrap { position:relative; display:inline-flex; }
        .ring-label {
            position:absolute; inset:0;
            display:flex; flex-direction:column;
            align-items:center; justify-content:center;
        }
        .ring-pct  { font-size:1.1rem; font-weight:800; color:var(--text); line-height:1; }
        .ring-sub  { font-size:.6rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; }

        /* ── Animations ──────────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(14px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .fade-up { animation: fadeUp .45s ease both; }
        .delay-1 { animation-delay:.05s; }
        .delay-2 { animation-delay:.10s; }
        .delay-3 { animation-delay:.15s; }
        .delay-4 { animation-delay:.20s; }
        .delay-5 { animation-delay:.25s; }
        .delay-6 { animation-delay:.30s; }
        .delay-7 { animation-delay:.35s; }

        /* ── Misc ────────────────────────────────────────────── */
        .view-all-link {
            font-size: .75rem;
            font-weight: 700;
            color: var(--green-dark);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .view-all-link:hover { color: var(--green); text-decoration: none; }
        body.dark-mode .view-all-link { color: var(--green); }

        .empty-feed {
            text-align: center;
            padding: 30px 16px;
            color: var(--muted);
            font-size: .84rem;
        }
        .empty-feed i { font-size: 2rem; opacity: .3; display:block; margin-bottom:8px; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_document.php'; ?>

    <div class="content-wrapper" style="min-height:100vh;">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-1">
                    <div class="col-sm-6">
                        <h1 class="m-0" style="font-size:1.25rem;font-weight:800;color:var(--navy);">
                            <i class="fas fa-tachometer-alt mr-2" style="color:var(--green-dark);"></i>Document Dashboard
                        </h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- ── Stat Cards ─────────────────────────────────────────── -->
                <div class="row mb-4">
                    <?php
                    $stat_cards = [
                        ['label'=>'Total Records',  'val'=>$counts['total'],     'icon'=>'fa-folder-open',   'color'=>'navy',   'bg'=>'#1a3c5e', 'href'=>'document_list.php'],
                        ['label'=>'Incoming',        'val'=>$counts['incoming'],  'icon'=>'fa-inbox',         'color'=>'blue',   'bg'=>'#2563eb', 'href'=>'document_list.php?kind=incoming'],
                        ['label'=>'Outgoing',        'val'=>$counts['outgoing'],  'icon'=>'fa-paper-plane',   'color'=>'green',  'bg'=>'#2a9863', 'href'=>'document_list.php?kind=outgoing'],
                        ['label'=>'Internal',        'val'=>$counts['internal'],  'icon'=>'fa-exchange-alt',  'color'=>'violet', 'bg'=>'#7c3aed', 'href'=>'document_list.php?kind=internal'],
                        ['label'=>'Pending',         'val'=>$counts['pending'],   'icon'=>'fa-clock',         'color'=>'amber',  'bg'=>'#f59e0b', 'href'=>'document_list.php'],
                        ['label'=>'Completed',       'val'=>$counts['completed'], 'icon'=>'fa-check-circle',  'color'=>'rose',   'bg'=>'#10b981', 'href'=>'document_list.php'],
                    ];
                    foreach ($stat_cards as $i => $sc):
                    ?>
                    <div class="col-6 col-md-4 col-lg-2 mb-3 fade-up delay-<?= $i+1 ?>">
                        <a href="<?= $sc['href'] ?>" class="stat-card" data-color="<?= $sc['color'] ?>">
                            <div class="stat-icon" style="background:<?= $sc['bg'] ?>;">
                                <i class="fas <?= $sc['icon'] ?>"></i>
                            </div>
                            <div class="stat-num counter" data-target="<?= $sc['val'] ?>"><?= $sc['val'] ?></div>
                            <div class="stat-lbl"><?= $sc['label'] ?></div>
                            <?php if ($sc['label'] === 'Total Records' && $month_delta !== 0): ?>
                            <div class="stat-trend <?= $month_delta > 0 ? 'trend-up' : 'trend-down' ?>">
                                <i class="fas fa-arrow-<?= $month_delta > 0 ? 'up' : 'down' ?> mr-1"></i>
                                <?= abs($month_pct) ?>% MoM
                            </div>
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ── Main grid ──────────────────────────────────────────── -->
                <div class="row">

                    <!-- LEFT column (8/12) -->
                    <div class="col-lg-8 mb-4">

                        <!-- Activity Feed -->
                        <div class="widget fade-up delay-3 mb-4">
                            <div class="widget-hd">
                                <div class="widget-title">
                                    <i class="fas fa-stream"></i> Recent Activity
                                </div>
                                <a href="document_list.php" class="view-all-link">
                                    View all <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                            <div class="widget-bd" style="padding-top:10px;padding-bottom:10px;">
                                <?php if (empty($feed)): ?>
                                <div class="empty-feed">
                                    <i class="fas fa-folder-open"></i>
                                    No documents recorded yet.
                                </div>
                                <?php else: ?>
                                <?php
                                $kind_colors = ['incoming'=>'#2563eb','outgoing'=>'#2a9863','internal'=>'#7c3aed','external'=>'#7c3aed'];
                                $kind_icons  = ['incoming'=>'fa-inbox','outgoing'=>'fa-paper-plane','internal'=>'fa-exchange-alt','external'=>'fa-exchange-alt'];
                                foreach ($feed as $f):
                                    $kc  = $kind_colors[$f['kind']] ?? '#1a3c5e';
                                    $ki  = $kind_icons[$f['kind']]  ?? 'fa-file';
                                    $ts  = strtotime($f['created_at']);
                                    $ago = $ts ? human_time_diff($ts) : '—';
                                ?>
                                <div class="feed-item">
                                    <div class="feed-dot" style="background:<?= $kc ?>;">
                                        <i class="fas <?= $ki ?>"></i>
                                    </div>
                                    <div style="flex:1;min-width:0;">
                                        <div><span class="feed-doc-num"><?= htmlspecialchars($f['document_number']) ?></span></div>
                                        <div class="feed-name" title="<?= htmlspecialchars($f['document_name']) ?>"><?= htmlspecialchars($f['document_name']) ?></div>
                                        <div class="feed-meta">
                                            <span class="kb kb-<?= $f['kind'] ?>"><?= ucfirst($f['kind']) ?></span>
                                            &nbsp;<span class="sb sb-<?= $f['status'] ?>"><?= ucfirst($f['status']) ?></span>
                                            <?php if (!empty($f['type_name'])): ?>
                                            &nbsp;<span style="color:var(--muted);font-size:.68rem;">&middot; <?= htmlspecialchars($f['type_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="feed-time">
                                        <div style="text-align:right;"><?= $ago ?></div>
                                        <div style="margin-top:4px;text-align:right;">
                                            <a href="document_view.php?id=<?= $f['id'] ?>"
                                               style="font-size:.68rem;color:var(--green-dark);font-weight:700;text-decoration:none;">
                                                View <i class="fas fa-arrow-right" style="font-size:.55rem;"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <!-- RIGHT column (4/12) -->
                    <div class="col-lg-4">

                        <!-- Status Distribution Donut -->
                        <div class="widget fade-up delay-4 mb-4">
                            <div class="widget-hd">
                                <div class="widget-title"><i class="fas fa-chart-pie"></i> Status Breakdown</div>
                            </div>
                            <div class="widget-bd">
                                <?php
                                $total_for_donut = max($counts['total'], 1);
                                $donut_slices = [
                                    ['label'=>'Received',  'val'=>$counts['received'],  'color'=>'#2a9863'],
                                    ['label'=>'Pending',   'val'=>$counts['pending'],   'color'=>'#f59e0b'],
                                    ['label'=>'Completed', 'val'=>$counts['completed'], 'color'=>'#10b981'],
                                    ['label'=>'Returned',  'val'=>$counts['returned'],  'color'=>'#f43f5e'],
                                ];
                                $r_svg=52; $cx=60; $cy=60; $circumference=2*M_PI*$r_svg;
                                $offset=0; $gaps=[];
                                foreach ($donut_slices as $s) {
                                    $pct   = $total_for_donut > 0 ? $s['val']/$total_for_donut : 0;
                                    $dash  = $pct * $circumference;
                                    $gaps[]= ['dash'=>$dash,'offset'=>$offset,'color'=>$s['color'],'val'=>$s['val'],'label'=>$s['label']];
                                    $offset += $dash;
                                }
                                ?>
                                <div class="donut-wrap">
                                    <div class="donut-svg-wrap">
                                        <svg width="120" height="120" viewBox="0 0 120 120">
                                            <circle cx="<?=$cx?>" cy="<?=$cy?>" r="<?=$r_svg?>" fill="none" stroke="var(--border)" stroke-width="14"/>
                                            <?php foreach ($gaps as $g): ?>
                                            <?php if ($g['dash'] > 0): ?>
                                            <circle cx="<?=$cx?>" cy="<?=$cy?>" r="<?=$r_svg?>"
                                                fill="none"
                                                stroke="<?= $g['color'] ?>"
                                                stroke-width="14"
                                                stroke-dasharray="<?= round($g['dash'],2) ?> <?= round($circumference - $g['dash'],2) ?>"
                                                stroke-dashoffset="<?= round($circumference/4 - $g['offset'], 2) ?>"
                                                stroke-linecap="round"
                                                style="transition:stroke-dasharray .5s ease;"/>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                        </svg>
                                        <div class="donut-center">
                                            <div class="donut-center-num"><?= $counts['total'] ?></div>
                                            <div class="donut-center-lbl">Total</div>
                                        </div>
                                    </div>
                                    <div class="donut-legend">
                                        <?php foreach ($donut_slices as $s): ?>
                                        <div class="donut-leg-item">
                                            <div class="donut-leg-dot" style="background:<?= $s['color'] ?>;"></div>
                                            <span><?= $s['label'] ?></span>
                                            <span class="donut-leg-val"><?= $s['val'] ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Document Types -->
                        <?php if (!empty($top_types)): ?>
                        <div class="widget fade-up delay-5 mb-4">
                            <div class="widget-hd">
                                <div class="widget-title"><i class="fas fa-tags"></i> Top Document Types</div>
                            </div>
                            <div class="widget-bd">
                                <?php foreach ($top_types as $t): ?>
                                <div class="type-row">
                                    <div class="type-row-hd">
                                        <span class="type-name" title="<?= htmlspecialchars($t['type_name']) ?>"><?= htmlspecialchars($t['type_name']) ?></span>
                                        <span class="type-cnt"><?= $t['cnt'] ?></span>
                                    </div>
                                    <div class="type-bar-track">
                                        <div class="type-bar-fill" style="width:<?= round(($t['cnt']/$type_max)*100) ?>%;"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div><!-- /col-lg-4 -->
                </div><!-- /row -->

            </div><!-- /container-fluid -->
        </div><!-- /content -->
    </div><!-- /content-wrapper -->

    <?php include '../includes/mainfooter.php'; ?>
</div><!-- /wrapper -->

<script>
$(document).ready(function() {
    // Dark mode
    if (localStorage.getItem('darkMode') === '1') $('body').addClass('dark-mode');

    // Counter animation
    $('.counter').each(function() {
        const $el    = $(this);
        const target = parseInt($el.data('target')) || 0;
        if (target === 0) return;
        let current = 0;
        const step  = Math.max(1, Math.ceil(target / 40));
        const timer = setInterval(function() {
            current = Math.min(current + step, target);
            $el.text(current.toLocaleString());
            if (current >= target) clearInterval(timer);
        }, 28);
    });
});

<?php
// PHP helper — time ago
function human_time_diff($ts) {
    $diff = time() - $ts;
    if ($diff < 60)         return 'Just now';
    if ($diff < 3600)       return floor($diff/60).'m ago';
    if ($diff < 86400)      return floor($diff/3600).'h ago';
    if ($diff < 604800)     return floor($diff/86400).'d ago';
    return date('M d', $ts);
}
?>
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>