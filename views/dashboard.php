<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

require_once '../includes/module_guard.php';
checkModuleMaintenance($db);

$stmt = $db->prepare("SELECT u.id, u.user, r.name as role_name, r.id as role_id FROM users u LEFT JOIN user_roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { session_destroy(); header("Location: login.php"); exit(); }
$user = $result->fetch_assoc();
$role_id   = $user['role_id'];
$role_name = $user['role_name'];

// Sections
$stmt = $db->prepare("SELECT s.*, CONCAT(e.first_name,' ',e.last_name) as head_name, e.picture as head_picture, (SELECT COUNT(*) FROM unit_section WHERE section_id=s.section_id) as unit_count FROM section s LEFT JOIN employee e ON s.head_emp_id=e.emp_id ORDER BY s.section_name");
$stmt->execute();
$sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Unit sections
$stmt = $db->prepare("SELECT us.*, s.section_name, CONCAT(e.first_name,' ',e.last_name) as head_name, e.picture as head_picture FROM unit_section us LEFT JOIN section s ON us.section_id=s.section_id LEFT JOIN employee e ON us.head_emp_id=e.emp_id ORDER BY us.unit_name");
$stmt->execute();
$unit_sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Manager
$stmt = $db->prepare("SELECT e.*, p.position_name, o.office_name FROM employee e LEFT JOIN position p ON e.position_id=p.position_id LEFT JOIN office o ON e.office_id=o.office_id WHERE e.is_manager=1 LIMIT 1");
$stmt->execute();
$manager = $stmt->get_result()->fetch_assoc();

// Offices (main + branch/sub offices), with employee counts, for the office filter buttons
$stmt = $db->prepare("SELECT o.office_id, o.office_name, o.is_main_office, o.parent_office_id, COUNT(e.emp_id) as employee_count FROM office o LEFT JOIN employee e ON e.office_id=o.office_id GROUP BY o.office_id, o.office_name, o.is_main_office, o.parent_office_id ORDER BY o.is_main_office DESC, o.office_name");
$stmt->execute();
$offices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Appointment data
$stmt = $db->prepare("SELECT a.status_name, a.color, COUNT(e.emp_id) as count FROM appointment_status a LEFT JOIN employee e ON a.appointment_id=e.appointment_status_id GROUP BY a.appointment_id,a.status_name,a.color ORDER BY count DESC");
$stmt->execute();
$appointment_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Filter dropdown lists
$stmt = $db->prepare("SELECT status_id, status_name FROM employment_status ORDER BY status_id");
$stmt->execute();
$employment_statuses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $db->prepare("SELECT appointment_id, status_name FROM appointment_status ORDER BY status_name");
$stmt->execute();
$appointment_statuses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Gender data
$stmt = $db->prepare("SELECT s.section_name, SUM(CASE WHEN e.gender='Male' THEN 1 ELSE 0 END) as male_count, SUM(CASE WHEN e.gender='Female' THEN 1 ELSE 0 END) as female_count, COUNT(e.emp_id) as total_count FROM section s LEFT JOIN employee e ON s.section_id=e.section_id GROUP BY s.section_id,s.section_name ORDER BY s.section_name");
$stmt->execute();
$gender_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Active employees
$stmt = $db->prepare("SELECT COUNT(*) as active_count FROM employee WHERE employment_status_id=1");
$stmt->execute();
$active_employees = $stmt->get_result()->fetch_assoc()['active_count'];

// Total employees (all statuses)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM employee");
$stmt->execute();
$total_employees = $stmt->get_result()->fetch_assoc()['total'];

function isUsingTemporaryPassword($emp_id, $db) {
    $q = $db->prepare("SELECT u.password,e.id_number FROM users u JOIN employee e ON u.employee_id=e.emp_id WHERE u.employee_id=?");
    $q->bind_param("i",$emp_id); $q->execute();
    $r = $q->get_result()->fetch_assoc();
    return $r ? password_verify($r['id_number'],$r['password']) : false;
}
if (isset($_SESSION['emp_id']) && isUsingTemporaryPassword($_SESSION['emp_id'], $db)) {
    $_SESSION['toast'] = ['type'=>'warning','message'=>'You are using a temporary password. Please change it for security.'];
}

$uploads_url = '../dist/img/employees/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Organization Dashboard</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/dashboard.css?v=4">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">

        <div class="content" style="padding:20px 0 0;">
            <div class="container-fluid">


                
                <!-- ORGANIZATION CHART -->
                <?php
                $units_by_section = [];
                foreach ($unit_sections as $u) { $units_by_section[$u['section_id']][] = $u; }
                $staff_by_section = [];
                foreach ($gender_data as $g) { $staff_by_section[$g['section_name']] = (int)$g['total_count']; }
                function orgAvatar($pic, $name, $base) {
                    $ini = htmlspecialchars(mb_strtoupper(mb_substr(trim((string)$name) ?: '?', 0, 1)));
                    $img = !empty($pic) ? '<img src="' . $base . htmlspecialchars($pic) . '" alt="" onerror="this.remove()">' : '';
                    return '<span class="org-av">' . $ini . $img . '</span>';
                }
                ?>
                <div class="dir-card org-card">
                    <div class="dir-card-header">
                        <h5><div class="icon-circle ic-brand"><i class="fas fa-sitemap"></i></div>Organization structure</h5>
                        <span class="emp-count-badge"><?= count($sections) ?> sections &middot; <?= count($unit_sections) ?> units</span>
                    </div>
                    <div class="org-scroll">
                        <div class="org-tree">
                            <?php if ($manager): ?>
                            <div class="org-node org-root org-click" role="button" tabindex="0" data-kind="manager" data-title="Manager's Office Staff" title="View manager's office staff">
                                <?= orgAvatar($manager['picture'] ?? '', $manager['first_name'] ?? '', $uploads_url) ?>
                                <div class="org-text">
                                    <div class="org-name"><?= htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) ?></div>
                                    <div class="org-sub"><?= htmlspecialchars($manager['position_name'] ?? 'Manager') ?><?= !empty($manager['office_name']) ? ' &middot; ' . htmlspecialchars($manager['office_name']) : '' ?></div>
                                    <div class="org-chips"><span class="org-hint"><i class="fas fa-users"></i> Office staff</span></div>
                                </div>
                            </div>
                            <div class="org-stem"></div>
                            <?php endif; ?>

                            <?php if (empty($sections)): ?>
                            <div class="dir-empty" style="display:block"><i class="fas fa-sitemap"></i><p>No sections have been set up yet.</p></div>
                            <?php else: ?>
                            <div class="org-branches">
                                <?php foreach ($sections as $sec):
                                    $units = $units_by_section[$sec['section_id']] ?? [];
                                    $staff = $staff_by_section[$sec['section_name']] ?? 0; ?>
                                <div class="org-branch">
                                    <div class="org-node org-section org-click" role="button" tabindex="0" data-kind="section" data-id="<?= (int)$sec['section_id'] ?>" data-title="<?= htmlspecialchars($sec['section_name']) ?>">
                                        <?= orgAvatar($sec['head_picture'] ?? '', $sec['head_name'] ?: $sec['section_name'], $uploads_url) ?>
                                        <div class="org-text">
                                            <div class="org-name" title="<?= htmlspecialchars($sec['section_name']) ?>"><?= htmlspecialchars($sec['section_name']) ?></div>
                                            <div class="org-sub"><?= htmlspecialchars($sec['head_name'] ?: 'No head assigned') ?></div>
                                            <div class="org-chips">
                                                <span class="section-chip"><?= $staff ?> staff</span>
                                                <?php if ($units): ?><span class="section-chip"><?= count($units) ?> unit<?= count($units) !== 1 ? 's' : '' ?></span><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($units): ?>
                                    <ul class="org-units">
                                        <?php foreach ($units as $unit): ?>
                                        <li class="org-node org-unit org-click" role="button" tabindex="0" data-kind="unit" data-id="<?= (int)$unit['unit_id'] ?>" data-title="<?= htmlspecialchars($unit['unit_name']) ?>" data-sub="<?= htmlspecialchars($sec['section_name']) ?>">
                                            <?= orgAvatar($unit['head_picture'] ?? '', $unit['head_name'] ?: $unit['unit_name'], $uploads_url) ?>
                                            <div class="org-text">
                                                <div class="org-name" title="<?= htmlspecialchars($unit['unit_name']) ?>"><?= htmlspecialchars($unit['unit_name']) ?></div>
                                                <div class="org-sub"><?= htmlspecialchars($unit['head_name'] ?: 'No head assigned') ?></div>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- EMPLOYEE PANEL -->
                <div class="dir-card emp-panel" id="empPanel" style="display:none;">
                    <div class="dir-card-header">
                        <h5><div class="icon-circle ic-brand"><i class="fas fa-address-book"></i></div>
                            <span id="selectedDepartment"></span> <small id="selectedUnitInfo"></small></h5>
                        <div class="panel-actions">
                            <span class="emp-count-badge" id="employeeCount">0 employees</span>
                            <button type="button" class="panel-close" onclick="closePanel()" aria-label="Close employee list"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <div class="dir-controls">
                        <div class="dir-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" oninput="searchEmployees()" placeholder="Search name, ID, position…" aria-label="Search employees">
                        </div>
                        <select class="dir-select" id="employmentStatusFilter" onchange="filterEmployees()" aria-label="Employment status">
                            <option value="">All employment status</option>
                            <?php foreach ($employment_statuses as $es): ?>
                            <option value="<?= $es['status_id'] ?>" <?= $es['status_id'] == 1 ? 'selected' : '' ?>><?= htmlspecialchars($es['status_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="dir-select" id="appointmentStatusFilter" onchange="filterEmployees()" aria-label="Appointment status">
                            <option value="">All appointment status</option>
                            <?php foreach ($appointment_statuses as $as): ?>
                            <option value="<?= $as['appointment_id'] ?>"><?= htmlspecialchars($as['status_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="dir-main">
                        <div class="dir-loading" id="loadingIndicator"><div class="dir-spin"></div><p>Loading employees…</p></div>
                        <div id="employeesGrid" style="display:none;"><div class="emp-grid" id="employeesContainer"></div></div>
                        <div class="dir-empty" id="noEmployees" style="display:none;"><i class="fas fa-users"></i><p id="noEmployeesMessage">No employees found</p></div>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="dir-card">
                        <div class="dir-card-header"><h5><div class="icon-circle ic-blue"><i class="fas fa-chart-pie"></i></div>Appointment status</h5></div>
                        <div class="chart-body"><canvas id="appointmentChart"></canvas></div>
                    </div>
                    <div class="dir-card">
                        <div class="dir-card-header"><h5><div class="icon-circle ic-brand"><i class="fas fa-venus-mars"></i></div>Gender by section</h5></div>
                        <div class="chart-body"><canvas id="genderChart"></canvas></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
<script>if (typeof Chart === 'undefined') document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"><\/script>');</script>

<script>
const uploadsUrl = '<?= $uploads_url ?>';
let allEmployees = [], filteredEmployees = [], searchQuery = '';
let employmentStatusFilter = '1', appointmentStatusFilter = '', managerStaffOnly = false, reqId = 0;
const $id = x => document.getElementById(x);

document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    document.querySelectorAll('.org-click').forEach(n => n.addEventListener('click', () => selectNode(n)));
    document.addEventListener('keydown', e => {
        const n = e.target.closest && e.target.closest('.org-click');
        if (n && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); selectNode(n); }
    });
});

function selectNode(el) {
    document.querySelectorAll('.org-click.is-selected').forEach(n => n.classList.remove('is-selected'));
    el.classList.add('is-selected');
    const kind = el.dataset.kind;
    managerStaffOnly = kind === 'manager';
    $id('selectedDepartment').textContent = el.dataset.title;
    $id('selectedUnitInfo').textContent = el.dataset.sub ? '(' + el.dataset.sub + ')' : '';
    const panel = $id('empPanel');
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    loadEmployees(kind === 'section' ? el.dataset.id : null, kind === 'unit' ? el.dataset.id : null);
}

function closePanel() {
    $id('empPanel').style.display = 'none';
    document.querySelectorAll('.org-click.is-selected').forEach(n => n.classList.remove('is-selected'));
}

async function loadEmployees(sectionId, unitId) {
    const my = ++reqId;
    $id('loadingIndicator').style.display = 'block';
    $id('employeesGrid').style.display = 'none';
    $id('noEmployees').style.display = 'none';
    try {
        const fd = new FormData();
        if (sectionId) fd.append('section_id', sectionId);
        if (unitId)    fd.append('unit_id', unitId);
        const res  = await fetch('get_employees.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (my !== reqId) return;               // a newer click superseded this one
        allEmployees = data.success ? (data.employees || []) : [];
        if (!data.success && typeof toastr !== 'undefined') toastr.error(data.message || 'Failed to load employees');
    } catch (e) {
        if (my !== reqId) return;
        allEmployees = [];
        if (typeof toastr !== 'undefined') toastr.error('Failed to connect to server');
    }
    $id('loadingIndicator').style.display = 'none';
    displayEmployees();
}

function applySearchAndFilter() {
    const q = searchQuery.toLowerCase();
    filteredEmployees = allEmployees.filter(emp => {
        if (q && !((emp.first_name + ' ' + emp.last_name).toLowerCase().includes(q)
                || (emp.id_number || '').toLowerCase().includes(q)
                || (emp.position_name || '').toLowerCase().includes(q))) return false;
        if (employmentStatusFilter && emp.employment_status_id != employmentStatusFilter) return false;
        if (appointmentStatusFilter && emp.appointment_status_id != appointmentStatusFilter) return false;
        if (managerStaffOnly && emp.is_manager_office_staff != 1) return false;
        return true;
    });
}

function displayEmployees() {
    applySearchAndFilter();
    const box = $id('employeesContainer'), n = filteredEmployees.length;
    $id('employeeCount').textContent = n + ' employee' + (n !== 1 ? 's' : '');
    if (!n) {
        $id('employeesGrid').style.display = 'none';
        $id('noEmployees').style.display = 'block';
        $id('noEmployeesMessage').textContent = (searchQuery || employmentStatusFilter || appointmentStatusFilter)
            ? 'No employees match your current filters' : 'No employees found here';
        return;
    }
    box.innerHTML = filteredEmployees.map(emp => {
        const name = escapeHtml(emp.first_name + ' ' + emp.last_name);
        const ini  = escapeHtml(((emp.first_name || '').charAt(0) + (emp.last_name || '').charAt(0)).toUpperCase());
        const st = emp.employment_status_id == 1 ? 'active' : emp.employment_status_id == 2 ? 'leave' : 'inactive';
        const col = escapeHtml(emp.appointment_status_color || '#2a9863');
        return `<div class="org-node emp-card">
            <div class="org-av">${ini}${emp.picture ? `<img src="${uploadsUrl}${escapeHtml(emp.picture)}" alt="" onerror="this.remove()">` : ''}</div>
            <div class="org-text">
                <div class="org-name" title="${name}">${name}</div>
                <div class="org-sub">${escapeHtml(emp.position_name || 'No position')} &middot; ID ${escapeHtml(emp.id_number || '—')}</div>
                <div class="org-chips">
                    <span class="section-chip chip-${st}"><span class="status-dot sd-${st}"></span>${escapeHtml(emp.status_name || 'Unknown')}</span>
                    ${emp.appointment_status_name ? `<span class="section-chip" style="background:${col}22;color:${col};">${escapeHtml(emp.appointment_status_name)}</span>` : ''}
                </div>
            </div></div>`;
    }).join('');
    $id('employeesGrid').style.display = 'block';
    $id('noEmployees').style.display = 'none';
}

function searchEmployees() { searchQuery = $id('searchInput').value; displayEmployees(); }
function filterEmployees() {
    employmentStatusFilter  = $id('employmentStatusFilter').value;
    appointmentStatusFilter = $id('appointmentStatusFilter').value;
    displayEmployees();
}
function escapeHtml(t) { if (t === null || t === undefined) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

function initCharts() {
    if (typeof Chart === 'undefined') { const r = document.querySelector('.chart-row'); if (r) r.style.display = 'none'; return; }
    // AdminLTE ships Chart.js v2; v3+ has a different config shape. Support both.
    const v2 = !!(Chart.defaults && Chart.defaults.global);
    const css = n => getComputedStyle(document.body).getPropertyValue(n).trim();
    const font = "Inter, 'Segoe UI', system-ui, sans-serif";
    const textColor = css('--text2') || '#46604f';
    if (v2) { Chart.defaults.global.defaultFontFamily = font; Chart.defaults.global.defaultFontColor = textColor; }
    else    { Chart.defaults.font.family = font; Chart.defaults.color = textColor; }

    const make = (id, config) => {
        const el = document.getElementById(id);
        if (!el) return;
        try { new Chart(el.getContext('2d'), config); }
        catch (e) { console.error('Chart "' + id + '" failed:', e); }
    };

    const apptData = <?= json_encode($appointment_data) ?>;
    if (apptData.length > 0) {
        const legend = { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } };
        make('appointmentChart', {
            type: 'doughnut',
            data: {
                labels: apptData.map(d => d.status_name),
                datasets: [{ data: apptData.map(d => parseInt(d.count)), backgroundColor: apptData.map(d => d.color || '#2a9863'), borderWidth: 2, borderColor: css('--surface') || '#fff' }]
            },
            options: v2
                ? { responsive: true, maintainAspectRatio: false, cutoutPercentage: 68, legend: legend }
                : { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: legend } }
        });
    }

    const gd = <?= json_encode($gender_data) ?>;
    if (gd.length > 0) {
        const legend = { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } };
        const grid = css('--border') || '#e3ebe6';
        make('genderChart', {
            type: 'bar',
            data: {
                labels: gd.map(d => d.section_name),
                datasets: [
                    { label: 'Male',   data: gd.map(d => parseInt(d.male_count)),   backgroundColor: '#2563eb', borderRadius: 6, maxBarThickness: 28 },
                    { label: 'Female', data: gd.map(d => parseInt(d.female_count)), backgroundColor: '#db2777', borderRadius: 6, maxBarThickness: 28 }
                ]
            },
            options: v2
                ? { responsive: true, maintainAspectRatio: false, legend: legend,
                    scales: { xAxes: [{ gridLines: { display: false }, ticks: { fontSize: 10 } }],
                              yAxes: [{ gridLines: { color: grid }, ticks: { beginAtZero: true, fontSize: 10, precision: 0 } }] } }
                : { responsive: true, maintainAspectRatio: false, plugins: { legend: legend },
                    scales: { x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                              y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { font: { size: 10 }, precision: 0 } } } }
        });
    }
}

// ── Session toasts ────────────────────────────────────────────
<?php if (isset($_SESSION['toast'])): ?>
$(document).ready(function() {
    toastr[<?= json_encode($_SESSION['toast']['type']) ?>](<?= json_encode($_SESSION['toast']['message']) ?>);
});
<?php unset($_SESSION['toast']); endif; ?>
</script>
</body>
</html>