<?php
// queue_counters.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['current_theme'] = 'queue';

$database = new Database();
$db = $database->getConnection();

$sections = [];
$result = $db->query("SELECT s.*,
    (SELECT COUNT(*) FROM visitor_queue WHERE section_id = s.section_id AND DATE(time_in) = CURDATE()) as today_count,
    (SELECT COUNT(*) FROM visitor_queue WHERE section_id = s.section_id AND status IN ('waiting','called') AND DATE(time_in) = CURDATE()) as waiting_count,
    (SELECT queue_number FROM visitor_queue WHERE section_id = s.section_id AND status = 'serving' AND DATE(time_in) = CURDATE() ORDER BY time_served DESC LIMIT 1) as now_serving
    FROM section s WHERE s.office_id = 1 ORDER BY s.section_name");
if ($result) $sections = $result->fetch_all(MYSQLI_ASSOC);

$units = [];
$result = $db->query("SELECT u.*, s.section_name,
    (SELECT COUNT(*) FROM visitor_queue WHERE unit_id = u.unit_id AND DATE(time_in) = CURDATE()) as today_count,
    (SELECT COUNT(*) FROM visitor_queue WHERE unit_id = u.unit_id AND status IN ('waiting','called') AND DATE(time_in) = CURDATE()) as waiting_count,
    (SELECT queue_number FROM visitor_queue WHERE unit_id = u.unit_id AND status = 'serving' AND DATE(time_in) = CURDATE() ORDER BY time_served DESC LIMIT 1) as now_serving
    FROM unit_section u LEFT JOIN section s ON u.section_id = s.section_id ORDER BY s.section_name, u.unit_name");
if ($result) $units = $result->fetch_all(MYSQLI_ASSOC);

$imo = $db->query("SELECT
    (SELECT COUNT(*) FROM visitor_queue WHERE is_manager_office=1 AND DATE(time_in)=CURDATE()) as today_count,
    (SELECT COUNT(*) FROM visitor_queue WHERE is_manager_office=1 AND status IN ('waiting','called') AND DATE(time_in)=CURDATE()) as waiting_count,
    (SELECT queue_number FROM visitor_queue WHERE is_manager_office=1 AND status='serving' AND DATE(time_in)=CURDATE() ORDER BY time_served DESC LIMIT 1) as now_serving")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Section/Unit Counters | Queue Management</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .counter-card{border-radius:14px;border:2px solid #e2e8f0;background:#fff;padding:0;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.06);transition:box-shadow .2s}
        .counter-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.12)}
        .counter-card .cc-head{background:linear-gradient(135deg,#1a8a3c,#2dc95a);color:#fff;padding:12px 18px;display:flex;align-items:center;justify-content:space-between}
        .counter-card.imo .cc-head{background:linear-gradient(135deg,#1d4ed8,#3b82f6)}
        .counter-card.unit .cc-head{background:linear-gradient(135deg,#7c3aed,#a855f7)}
        .cc-name{font-size:15px;font-weight:800;letter-spacing:.5px}
        .cc-code{font-size:11px;opacity:.8;letter-spacing:2px;text-transform:uppercase}
        .cc-body{display:grid;grid-template-columns:1fr 1fr 1fr}
        .cc-stat{padding:14px 10px;text-align:center;border-right:1px solid #f1f5f9}
        .cc-stat:last-child{border-right:none}
        .cc-stat-num{font-size:28px;font-weight:900;line-height:1}
        .cc-stat-lbl{font-size:10px;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;margin-top:3px}
        .cc-now{color:#16a34a}.cc-wait{color:#d97706}.cc-today{color:#64748b}
        .cc-serving-badge{font-size:11px;background:#dcfce7;color:#15803d;border:1px solid #86efac;border-radius:6px;padding:2px 8px;font-weight:700}
        .section-group-label{font-size:11px;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:#94a3b8;margin:20px 0 10px;display:flex;align-items:center;gap:10px}
        .section-group-label::after{content:'';flex:1;height:1px;background:#e2e8f0}
        .live-dot{display:inline-block;width:8px;height:8px;background:#22c55e;border-radius:50%;animation:blink 1.2s ease-in-out infinite;margin-right:4px}
        @keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_queue.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-7">
                        <h1 class="m-0"><i class="fas fa-tachometer-alt mr-2"></i>Section / Unit Counters
                            <span class="live-dot ml-2"></span>
                            <small class="text-muted" style="font-size:13px;">Live · refreshes every 10s</small>
                        </h1>
                    </div>
                    <div class="col-sm-5">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="queue.php">Queue</a></li>
                            <li class="breadcrumb-item active">Counters</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <section class="content"><div class="container-fluid">

            <!-- Global Summary -->
            <div class="row mb-3">
                <div class="col-md-3 col-sm-6 mb-2"><div class="info-box shadow-sm"><span class="info-box-icon bg-info"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Total Today</span><span class="info-box-number" id="gs-total">—</span></div></div></div>
                <div class="col-md-3 col-sm-6 mb-2"><div class="info-box shadow-sm"><span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span><div class="info-box-content"><span class="info-box-text">Waiting</span><span class="info-box-number" id="gs-waiting">—</span></div></div></div>
                <div class="col-md-3 col-sm-6 mb-2"><div class="info-box shadow-sm"><span class="info-box-icon bg-primary"><i class="fas fa-user-check"></i></span><div class="info-box-content"><span class="info-box-text">Serving</span><span class="info-box-number" id="gs-serving">—</span></div></div></div>
                <div class="col-md-3 col-sm-6 mb-2"><div class="info-box shadow-sm"><span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span><div class="info-box-content"><span class="info-box-text">Completed</span><span class="info-box-number" id="gs-completed">—</span></div></div></div>
            </div>

            <!-- IMO -->
            <div class="section-group-label"><i class="fas fa-building mr-2"></i>Manager's Office</div>
            <div class="row mb-2">
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="counter-card imo">
                        <div class="cc-head">
                            <div><div class="cc-name"><i class="fas fa-building mr-1"></i> IMO Office</div><div class="cc-code">IMO</div></div>
                        </div>
                        <div class="cc-body">
                            <div class="cc-stat"><div class="cc-stat-num cc-now" id="imo-now"><?= $imo['now_serving'] ?? '---' ?></div><div class="cc-stat-lbl">Serving</div></div>
                            <div class="cc-stat"><div class="cc-stat-num cc-wait" id="imo-wait"><?= $imo['waiting_count'] ?? 0 ?></div><div class="cc-stat-lbl">Waiting</div></div>
                            <div class="cc-stat"><div class="cc-stat-num cc-today" id="imo-today"><?= $imo['today_count'] ?? 0 ?></div><div class="cc-stat-lbl">Today</div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sections -->
            <div class="section-group-label"><i class="fas fa-sitemap mr-2"></i>Sections</div>
            <div class="row">
                <?php foreach ($sections as $sec): ?>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="counter-card" id="cc-sec-<?= $sec['section_id'] ?>">
                        <div class="cc-head">
                            <div><div class="cc-name"><?= htmlspecialchars($sec['section_name']) ?></div><div class="cc-code"><?= $sec['section_code'] ?></div></div>
                            <?php if ($sec['now_serving']): ?><span class="cc-serving-badge">Now: <?= htmlspecialchars($sec['now_serving']) ?></span><?php endif; ?>
                        </div>
                        <div class="cc-body">
                            <div class="cc-stat"><div class="cc-stat-num cc-now" id="sec-now-<?= $sec['section_id'] ?>"><?= $sec['now_serving'] ?? '---' ?></div><div class="cc-stat-lbl">Serving</div></div>
                            <div class="cc-stat"><div class="cc-stat-num cc-wait" id="sec-wait-<?= $sec['section_id'] ?>"><?= $sec['waiting_count'] ?></div><div class="cc-stat-lbl">Waiting</div></div>
                            <div class="cc-stat"><div class="cc-stat-num cc-today" id="sec-today-<?= $sec['section_id'] ?>"><?= $sec['today_count'] ?></div><div class="cc-stat-lbl">Today</div></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($sections)): ?><div class="col-12"><p class="text-muted">No sections configured.</p></div><?php endif; ?>
            </div>

            <!-- Units -->
            <div class="section-group-label"><i class="fas fa-layer-group mr-2"></i>Units</div>
            <div class="row">
                <?php foreach ($units as $unit): ?>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="counter-card unit" id="cc-unit-<?= $unit['unit_id'] ?>">
                        <div class="cc-head">
                            <div><div class="cc-name"><?= htmlspecialchars($unit['unit_name']) ?></div><div class="cc-code"><?= $unit['unit_code'] ?> · <?= htmlspecialchars($unit['section_name']) ?></div></div>
                            <?php if ($unit['now_serving']): ?><span class="cc-serving-badge">Now: <?= htmlspecialchars($unit['now_serving']) ?></span><?php endif; ?>
                        </div>
                        <div class="cc-body">
                            <div class="cc-stat"><div class="cc-stat-num cc-now" id="unit-now-<?= $unit['unit_id'] ?>"><?= $unit['now_serving'] ?? '---' ?></div><div class="cc-stat-lbl">Serving</div></div>
                            <div class="cc-stat"><div class="cc-stat-num cc-wait" id="unit-wait-<?= $unit['unit_id'] ?>"><?= $unit['waiting_count'] ?></div><div class="cc-stat-lbl">Waiting</div></div>
                            <div class="cc-stat"><div class="cc-stat-num cc-today" id="unit-today-<?= $unit['unit_id'] ?>"><?= $unit['today_count'] ?></div><div class="cc-stat-lbl">Today</div></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($units)): ?><div class="col-12"><p class="text-muted">No units configured.</p></div><?php endif; ?>
            </div>

        </div></section>
    </div>
    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>
$(document).ready(function(){
    refreshAll();
    setInterval(refreshAll, 10000);

    function refreshAll(){
        $.getJSON('../includes/queue_ajax.php?action=get_queue_summary', function(res){
            if(!res.success) return;
            const s = res.summary;
            $('#gs-total').text(s.total||0);
            $('#gs-waiting').text(s.waiting||0);
            $('#gs-serving').text(s.serving||0);
            $('#gs-completed').text(s.completed||0);
        });

        $.getJSON('../includes/queue_ajax.php?action=get_section_counters', function(res){
            if(!res.success||!res.counters) return;
            res.counters.forEach(function(c){
                const now = c.current_serving || '---';
                if(c.type==='section'){
                    $(`#sec-now-${c.id}`).text(now);
                    $(`#sec-wait-${c.id}`).text(c.waiting_count||0);
                    $(`#sec-today-${c.id}`).text(c.total_today||0);
                } else {
                    $(`#unit-now-${c.id}`).text(now);
                    $(`#unit-wait-${c.id}`).text(c.waiting_count||0);
                    $(`#unit-today-${c.id}`).text(c.total_today||0);
                }
            });
        });

        $.getJSON('../includes/queue_ajax.php?action=get_display_data', function(res){
            if(!res.success||!res.sections) return;
            const imo = res.sections.find(s=>s.section_code==='IMO');
            if(imo){
                $('#imo-now').text(imo.current_serving||'---');
                $('#imo-wait').text(imo.waiting_count||0);
                $('#imo-today').text(imo.total_today||0);
            }
        });
    }
});
</script>
</body>
</html>