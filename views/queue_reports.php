<?php
// queue_reports.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['current_theme'] = 'queue';

$database = new Database();
$db = $database->getConnection();

// Date range defaults
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'daily';

// Summary stats for selected range
$summary = [];
$queries = [
    'total'     => "SELECT COUNT(*) as v FROM visitor_queue WHERE DATE(time_in) BETWEEN ? AND ?",
    'priority'  => "SELECT COUNT(*) as v FROM visitor_queue WHERE is_priority=1 AND DATE(time_in) BETWEEN ? AND ?",
    'completed' => "SELECT COUNT(*) as v FROM visitor_queue WHERE status='completed' AND DATE(time_in) BETWEEN ? AND ?",
    'cancelled' => "SELECT COUNT(*) as v FROM visitor_queue WHERE status='cancelled' AND DATE(time_in) BETWEEN ? AND ?",
    'avg_wait'  => "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, time_in, time_called)),1) as v FROM visitor_queue WHERE time_called IS NOT NULL AND DATE(time_in) BETWEEN ? AND ?",
    'avg_serve' => "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, time_called, time_completed)),1) as v FROM visitor_queue WHERE status='completed' AND time_completed IS NOT NULL AND DATE(time_in) BETWEEN ? AND ?",
];
foreach ($queries as $key => $q) {
    $st = $db->prepare($q);
    $st->bind_param("ss", $date_from, $date_to);
    $st->execute();
    $summary[$key] = $st->get_result()->fetch_assoc()['v'] ?? 0;
}

// Per-section breakdown
$section_breakdown = [];
$st = $db->prepare("SELECT 
    COALESCE(s.section_name, vq.section_name, 'IMO Office') as name,
    COUNT(*) as total,
    SUM(vq.is_priority) as priority_count,
    SUM(CASE WHEN vq.status='completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN vq.status='cancelled' THEN 1 ELSE 0 END) as cancelled,
    ROUND(AVG(TIMESTAMPDIFF(MINUTE, vq.time_in, vq.time_called)),1) as avg_wait
    FROM visitor_queue vq
    LEFT JOIN section s ON vq.section_id = s.section_id
    WHERE DATE(vq.time_in) BETWEEN ? AND ?
    GROUP BY COALESCE(s.section_name, vq.section_name, 'IMO Office')
    ORDER BY total DESC");
$st->bind_param("ss", $date_from, $date_to);
$st->execute();
$section_breakdown = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// Daily trend (for chart)
$daily_trend = [];
$st = $db->prepare("SELECT DATE(time_in) as day, COUNT(*) as total,
    SUM(is_priority) as priority_count,
    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed
    FROM visitor_queue
    WHERE DATE(time_in) BETWEEN ? AND ?
    GROUP BY DATE(time_in) ORDER BY day ASC");
$st->bind_param("ss", $date_from, $date_to);
$st->execute();
$daily_trend = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// Hourly distribution for selected range
$hourly = [];
$st = $db->prepare("SELECT HOUR(time_in) as hr, COUNT(*) as total
    FROM visitor_queue WHERE DATE(time_in) BETWEEN ? AND ?
    GROUP BY HOUR(time_in) ORDER BY hr ASC");
$st->bind_param("ss", $date_from, $date_to);
$st->execute();
$hourly_raw = $st->get_result()->fetch_all(MYSQLI_ASSOC);
for ($h = 7; $h <= 17; $h++) {
    $found = array_filter($hourly_raw, fn($r) => (int)$r['hr'] === $h);
    $hourly[$h] = $found ? array_values($found)[0]['total'] : 0;
}

// Purpose breakdown
$purposes = [];
$st = $db->prepare("SELECT purpose, COUNT(*) as total FROM visitor_queue
    WHERE DATE(time_in) BETWEEN ? AND ? GROUP BY purpose ORDER BY total DESC");
$st->bind_param("ss", $date_from, $date_to);
$st->execute();
$purposes = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// Recent transactions list
$st = $db->prepare("SELECT vq.*, 
    COALESCE(s.section_name, vq.section_name) as sec_name,
    CONCAT(e.last_name,', ',e.first_name) as person_name
    FROM visitor_queue vq
    LEFT JOIN section s ON vq.section_id = s.section_id
    LEFT JOIN employee e ON vq.person_to_visit = e.emp_id
    WHERE DATE(vq.time_in) BETWEEN ? AND ?
    ORDER BY vq.time_in DESC LIMIT 100");
$st->bind_param("ss", $date_from, $date_to);
$st->execute();
$transactions = $st->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Queue Reports | NIA-ACIMO AIMS</title>
    <?php include '../includes/header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .stat-card { border-radius: 14px; padding: 20px 24px; color: #fff; display:flex; align-items:center; gap:18px; }
        .stat-card .icon { font-size: 36px; opacity: .85; }
        .stat-card .info .number { font-size: 38px; font-weight: 900; line-height:1; }
        .stat-card .info .label  { font-size: 13px; opacity: .9; margin-top:4px; letter-spacing:1px; text-transform:uppercase; }
        .bg-teal   { background: linear-gradient(135deg,#0d9488,#14b8a6); }
        .bg-red    { background: linear-gradient(135deg,#dc2626,#ef4444); }
        .bg-green  { background: linear-gradient(135deg,#16a34a,#22c55e); }
        .bg-orange { background: linear-gradient(135deg,#d97706,#f59e0b); }
        .bg-blue   { background: linear-gradient(135deg,#1d4ed8,#3b82f6); }
        .bg-purple { background: linear-gradient(135deg,#7c3aed,#a855f7); }
        .chart-card { background:#fff; border-radius:14px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
        .tbl-report th { background:#f1f5f9; font-size:12px; text-transform:uppercase; letter-spacing:1px; }
        .tbl-report td { vertical-align: middle; }
        .badge-pri { background:#dc2626; color:#fff; }
        .badge-reg { background:#1d4ed8; color:#fff; }
        .filter-bar { background:#fff; border-radius:12px; padding:16px 20px; border:1px solid #e2e8f0; margin-bottom:20px; box-shadow:0 1px 4px rgba(0,0,0,.05); }
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
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-chart-bar mr-2"></i>Queue Reports</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="queue.php">Queue</a></li>
                            <li class="breadcrumb-item active">Reports</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
        <div class="container-fluid">

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="form-inline flex-wrap" style="gap:10px;">
                    <div class="form-group mr-2">
                        <label class="mr-2 font-weight-bold">From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="form-group mr-2">
                        <label class="mr-2 font-weight-bold">To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary mr-2">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>"
                       class="btn btn-sm btn-secondary mr-2">Today</a>
                    <a href="?date_from=<?= date('Y-m-d', strtotime('monday this week')) ?>&date_to=<?= date('Y-m-d') ?>"
                       class="btn btn-sm btn-secondary mr-2">This Week</a>
                    <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>"
                       class="btn btn-sm btn-secondary mr-2">This Month</a>
                    <button type="button" class="btn btn-sm btn-success ml-auto" onclick="exportCSV()">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-3">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card bg-teal">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="info">
                            <div class="number"><?= number_format($summary['total']) ?></div>
                            <div class="label">Total Visitors</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card bg-red">
                        <div class="icon"><i class="fas fa-star"></i></div>
                        <div class="info">
                            <div class="number"><?= number_format($summary['priority']) ?></div>
                            <div class="label">Priority</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card bg-green">
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                        <div class="info">
                            <div class="number"><?= number_format($summary['completed']) ?></div>
                            <div class="label">Completed</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card bg-orange">
                        <div class="icon"><i class="fas fa-times-circle"></i></div>
                        <div class="info">
                            <div class="number"><?= number_format($summary['cancelled']) ?></div>
                            <div class="label">Cancelled</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card bg-blue">
                        <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                        <div class="info">
                            <div class="number"><?= $summary['avg_wait'] ?: '—' ?></div>
                            <div class="label">Avg Wait (min)</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stat-card bg-purple">
                        <div class="icon"><i class="fas fa-user-clock"></i></div>
                        <div class="info">
                            <div class="number"><?= $summary['avg_serve'] ?: '—' ?></div>
                            <div class="label">Avg Serve (min)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-3">
                <div class="col-lg-8 mb-3">
                    <div class="chart-card">
                        <h5 class="font-weight-bold mb-3"><i class="fas fa-chart-line mr-2 text-primary"></i>Daily Visitor Trend</h5>
                        <canvas id="trendChart" height="100"></canvas>
                    </div>
                </div>
                <div class="col-lg-4 mb-3">
                    <div class="chart-card">
                        <h5 class="font-weight-bold mb-3"><i class="fas fa-chart-pie mr-2 text-success"></i>Purpose Breakdown</h5>
                        <canvas id="purposeChart" height="180"></canvas>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-lg-7 mb-3">
                    <div class="chart-card">
                        <h5 class="font-weight-bold mb-3"><i class="fas fa-chart-bar mr-2 text-warning"></i>Hourly Distribution</h5>
                        <canvas id="hourlyChart" height="100"></canvas>
                    </div>
                </div>
                <div class="col-lg-5 mb-3">
                    <div class="chart-card">
                        <h5 class="font-weight-bold mb-3"><i class="fas fa-building mr-2 text-info"></i>Visitors by Section/Unit</h5>
                        <div class="table-responsive" style="max-height:260px; overflow-y:auto;">
                            <table class="table table-sm tbl-report mb-0">
                                <thead><tr><th>Section / Unit</th><th>Total</th><th>Priority</th><th>Completed</th><th>Avg Wait</th></tr></thead>
                                <tbody>
                                <?php foreach ($section_breakdown as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><strong><?= $row['total'] ?></strong></td>
                                    <td><?= $row['priority_count'] ?></td>
                                    <td><?= $row['completed'] ?></td>
                                    <td><?= $row['avg_wait'] ? $row['avg_wait'].' min' : '—' ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($section_breakdown)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No data</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="card-title mb-0"><i class="fas fa-list mr-2"></i>Transaction Log
                        <small class="text-muted ml-2">(<?= date('M d, Y', strtotime($date_from)) ?> – <?= date('M d, Y', strtotime($date_to)) ?>)</small>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover tbl-report mb-0" id="txTable">
                            <thead>
                                <tr>
                                    <th>Queue No.</th>
                                    <th>Visitor</th>
                                    <th>Purpose</th>
                                    <th>Section / Unit</th>
                                    <th>Person to Visit</th>
                                    <th>Time In</th>
                                    <th>Time Called</th>
                                    <th>Completed</th>
                                    <th>Wait (min)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($transactions as $tx):
                                $wait = ($tx['time_in'] && $tx['time_called'])
                                    ? round((strtotime($tx['time_called']) - strtotime($tx['time_in'])) / 60, 1)
                                    : null;
                                $statusBadge = match($tx['status']) {
                                    'completed' => 'badge-success',
                                    'cancelled' => 'badge-danger',
                                    'serving'   => 'badge-primary',
                                    'called'    => 'badge-info',
                                    default     => 'badge-warning'
                                };
                            ?>
                            <tr>
                                <td>
                                    <?php if ($tx['is_priority']): ?>
                                        <span class="badge badge-danger mr-1"><i class="fas fa-star"></i></span>
                                    <?php endif; ?>
                                    <strong><?= htmlspecialchars($tx['is_priority'] ? $tx['priority_number'] : $tx['queue_number']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($tx['visitor_name']) ?>
                                    <?php if ($tx['company']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($tx['company']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($tx['purpose']) ?></td>
                                <td><?= htmlspecialchars($tx['sec_name'] ?: $tx['unit_name'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($tx['person_name'] ?? 'N/A') ?></td>
                                <td><?= $tx['time_in'] ? date('h:i A', strtotime($tx['time_in'])) : '—' ?></td>
                                <td><?= $tx['time_called'] ? date('h:i A', strtotime($tx['time_called'])) : '—' ?></td>
                                <td><?= $tx['time_completed'] ? date('h:i A', strtotime($tx['time_completed'])) : '—' ?></td>
                                <td><?= $wait !== null ? $wait : '—' ?></td>
                                <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($tx['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">No records found for selected period</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
        </section>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// ── Chart Data from PHP ──
const trendLabels  = <?= json_encode(array_column($daily_trend, 'day')) ?>;
const trendTotal   = <?= json_encode(array_column($daily_trend, 'total')) ?>;
const trendPri     = <?= json_encode(array_column($daily_trend, 'priority_count')) ?>;
const trendComp    = <?= json_encode(array_column($daily_trend, 'completed')) ?>;

const hourlyLabels = <?= json_encode(array_map(fn($h) => date('g A', mktime($h,0,0)), array_keys($hourly))) ?>;
const hourlyData   = <?= json_encode(array_values($hourly)) ?>;

const purposeLabels = <?= json_encode(array_column($purposes, 'purpose')) ?>;
const purposeData   = <?= json_encode(array_column($purposes, 'total')) ?>;

// ── Daily Trend Chart ──
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [
            { label: 'Total', data: trendTotal, borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,.12)', tension: .4, fill: true, pointRadius: 4 },
            { label: 'Priority', data: trendPri, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,.08)', tension: .4, fill: false, pointRadius: 4 },
            { label: 'Completed', data: trendComp, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.08)', tension: .4, fill: false, pointRadius: 4 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// ── Hourly Chart ──
new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
        labels: hourlyLabels,
        datasets: [{ label: 'Visitors', data: hourlyData,
            backgroundColor: hourlyData.map((v, i) => v === Math.max(...hourlyData) ? '#d97706' : '#3b82f6'),
            borderRadius: 6 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// ── Purpose Pie Chart ──
new Chart(document.getElementById('purposeChart'), {
    type: 'doughnut',
    data: {
        labels: purposeLabels,
        datasets: [{ data: purposeData,
            backgroundColor: ['#0d9488','#3b82f6','#d97706','#dc2626','#7c3aed','#16a34a','#f59e0b'],
            borderWidth: 2 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});

// ── DataTable ──
$(document).ready(function() {
    $('#txTable').DataTable({
        pageLength: 25,
        order: [[5, 'desc']],
        responsive: true,
        dom: 'Blfrtip',
        language: { emptyTable: 'No records found' }
    });
});

// ── CSV Export ──
function exportCSV() {
    const table = document.getElementById('txTable');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    rows.forEach(row => {
        const cols = row.querySelectorAll('th, td');
        const rowData = Array.from(cols).map(col => '"' + col.innerText.replace(/"/g, '""').trim() + '"');
        csv.push(rowData.join(','));
    });
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'queue_report_<?= $date_from ?>_<?= $date_to ?>.csv';
    a.click();
}
</script>

<style>
@media print {
    .main-sidebar, .main-header, .content-header, .filter-bar button[onclick="window.print()"],
    .filter-bar button[onclick="exportCSV()"] { display: none !important; }
    .content-wrapper { margin-left: 0 !important; }
    .chart-card, .card { break-inside: avoid; }
}

/* =========================================================
   DARK MODE OVERRIDES — applied via body.dark-mode
   ========================================================= */
body.dark-mode { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .content-wrapper { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
body.dark-mode .card-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; border-color: var(--card-border) !important; }
body.dark-mode .card-body { background: var(--card-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .card-footer { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .modal-header { background: var(--modal-header-bg) !important; color: var(--modal-header-color) !important; }
body.dark-mode .modal-body { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .modal-footer { background: var(--modal-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .table { background: var(--table-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .table thead th { background: var(--table-stripe) !important; color: var(--text-primary) !important; border-color: var(--table-border) !important; }
body.dark-mode .table td, body.dark-mode .table th { border-color: var(--table-border) !important; color: var(--text-primary) !important; }
body.dark-mode .table-striped tbody tr:nth-of-type(odd) { background: var(--table-stripe) !important; }
body.dark-mode .table-hover tbody tr:hover { background: var(--notification-unread-bg) !important; }
body.dark-mode .table-bordered { border-color: var(--table-border) !important; }
body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .form-control:focus { border-color: #5a7fa8 !important; box-shadow: 0 0 0 0.2rem rgba(90,127,168,.25) !important; }
body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
body.dark-mode .input-group-text { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode label, body.dark-mode .form-label { color: var(--text-primary) !important; }
body.dark-mode .text-muted { color: var(--text-muted) !important; }
body.dark-mode .text-dark { color: var(--text-primary) !important; }
body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 { color: var(--text-primary) !important; }
body.dark-mode p, body.dark-mode span:not(.badge) { color: var(--text-primary); }
body.dark-mode .breadcrumb { background: var(--card-bg) !important; }
body.dark-mode .breadcrumb-item a { color: #7aabdf !important; }
body.dark-mode .breadcrumb-item.active { color: var(--text-muted) !important; }
body.dark-mode .nav-tabs .nav-link { color: var(--text-muted) !important; border-color: var(--card-border) !important; }
body.dark-mode .nav-tabs .nav-link.active { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .nav-tabs { border-color: var(--card-border) !important; }
body.dark-mode .tab-content, body.dark-mode .tab-pane { background: var(--card-bg) !important; color: var(--text-primary) !important; }
body.dark-mode .accordion .card { background: var(--card-bg) !important; }
body.dark-mode .accordion .card-header { background: var(--table-stripe) !important; }
body.dark-mode .list-group-item { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .dropdown-menu { background: var(--dropdown-bg) !important; border-color: var(--dropdown-border) !important; }
body.dark-mode .dropdown-item { color: var(--dropdown-color) !important; }
body.dark-mode .dropdown-item:hover { background: var(--table-stripe) !important; }
body.dark-mode .alert { border-color: var(--card-border) !important; }
body.dark-mode .alert-info { background: #1e2f3e !important; color: #93c5fd !important; }
body.dark-mode .alert-success { background: #1a2e1e !important; color: #86efac !important; }
body.dark-mode .alert-warning { background: #2e2412 !important; color: #fcd34d !important; }
body.dark-mode .alert-danger { background: #2e1515 !important; color: #fca5a5 !important; }
body.dark-mode .page-item .page-link { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .page-item.active .page-link { background: var(--sidebar-active-bg) !important; border-color: var(--sidebar-active-bg) !important; }
body.dark-mode hr { border-color: var(--card-border) !important; }
body.dark-mode .dataTables_wrapper { color: var(--text-primary) !important; }
body.dark-mode .dataTables_filter input, body.dark-mode .dataTables_length select { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .dataTables_info { color: var(--text-muted) !important; }
body.dark-mode .select2-container--bootstrap4 .select2-selection { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .select2-container--bootstrap4 .select2-selection__rendered { color: var(--input-color) !important; }
body.dark-mode .select2-dropdown { background: var(--dropdown-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .select2-results__option { color: var(--dropdown-color) !important; }
body.dark-mode .select2-results__option--highlighted { background: var(--sidebar-active-bg) !important; color: #fff !important; }

body.dark-mode .chart-card { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
body.dark-mode .filter-bar { background: var(--card-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .tbl-report th { background: var(--table-stripe) !important; color: var(--text-primary) !important; }
body.dark-mode .tbl-report td { color: var(--text-primary) !important; border-color: var(--table-border) !important; }

</style>
</body>
</html>