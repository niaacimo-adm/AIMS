<?php
ob_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$current_page = basename($_SERVER['PHP_SELF']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$employee_picture = '../dist/img/user2-160x160.jpg';
$employee_id      = $_SESSION['emp_id'] ?? null;

// ── Pre-fetch notification counts (pending docs forwarded to this user's section/unit)
$notif = ['incoming' => 0, 'outgoing' => 0, 'internal' => 0, 'total' => 0];

if ($employee_id) {
    $database2 = new Database();
    $db2       = $database2->getConnection();

    // Get the logged-in employee's section, unit, office, and manager flags
    $us = $db2->prepare("
        SELECT section_id, unit_section_id, office_id,
               is_manager, is_manager_office_staff
        FROM employee WHERE emp_id = ? LIMIT 1
    ");
    $us->bind_param("i", $employee_id);
    $us->execute();
    $urow      = $us->get_result()->fetch_assoc();
    $sec_id    = (int)($urow['section_id']              ?? 0);
    $unit_id   = (int)($urow['unit_section_id']         ?? 0);
    $office_id = (int)($urow['office_id']               ?? 0);
    $is_mgr    = (int)($urow['is_manager']              ?? 0);
    $is_staff  = (int)($urow['is_manager_office_staff'] ?? 0);

    // 1) Docs forwarded to this employee's section/unit
    if ($sec_id) {
        $nq = $db2->prepare("
            SELECT kind, COUNT(*) AS cnt
            FROM document_records
            WHERE status = 'pending'
              AND forwarded_to_section_id = ?
              AND (forwarded_to_unit_id = ? OR forwarded_to_unit_id IS NULL OR ? = 0)
            GROUP BY kind
        ");
        $nq->bind_param("iii", $sec_id, $unit_id, $unit_id);
        $nq->execute();
        $nrows = $nq->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($nrows as $nr) {
            $k = $nr['kind'];
            if (isset($notif[$k])) {
                $notif[$k]       += (int)$nr['cnt'];
                $notif['total']  += (int)$nr['cnt'];
            }
        }
    }

    // 2) Docs forwarded to this employee's office
    //    — only visible to managers, office staff, or the office's designated manager
    if ($office_id && ($is_mgr || $is_staff)) {
        $oq = $db2->prepare("
            SELECT kind, COUNT(*) AS cnt
            FROM document_records
            WHERE status = 'pending'
              AND forwarded_to_office_id = ?
            GROUP BY kind
        ");
        $oq->bind_param("i", $office_id);
        $oq->execute();
        $orows = $oq->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($orows as $or_) {
            $k = $or_['kind'];
            if (isset($notif[$k])) {
                $notif[$k]       += (int)$or_['cnt'];
                $notif['total']  += (int)$or_['cnt'];
            }
        }
    }

    // 3) Fallback: check if this employee is listed as manager_emp_id in the office table
    if ($office_id && !$is_mgr && !$is_staff) {
        $cm = $db2->prepare("SELECT 1 FROM office WHERE office_id = ? AND manager_emp_id = ? LIMIT 1");
        $cm->bind_param("ii", $office_id, $employee_id);
        $cm->execute();
        if ($cm->get_result()->num_rows > 0) {
            $oq2 = $db2->prepare("
                SELECT kind, COUNT(*) AS cnt
                FROM document_records
                WHERE status = 'pending'
                  AND forwarded_to_office_id = ?
                GROUP BY kind
            ");
            $oq2->bind_param("i", $office_id);
            $oq2->execute();
            $orows2 = $oq2->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($orows2 as $or2) {
                $k = $or2['kind'];
                if (isset($notif[$k])) {
                    $notif[$k]       += (int)$or2['cnt'];
                    $notif['total']  += (int)$or2['cnt'];
                }
            }
        }
    }
}
?>

<aside class="main-sidebar sidebar-dark-olive elevation-4">
    <!-- Brand Logo -->
    <a href="document_dashboard.php" class="brand-link bg-gradient-primary">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="NIA Logo" class="brand-image img-circle elevation-3" style="opacity:.8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b></span>
    </a>

    <div class="sidebar" style="background-color:#2c3e50 !important;">
        <!-- User Panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="<?= $employee_picture ?>" class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php" class="d-block text-white">
                        <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                    </a>
                    <?php if (isset($_SESSION['role_name'])): ?>
                    <span class="badge badge-primary mt-1"><?= htmlspecialchars($_SESSION['role_name']) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-header text-uppercase text-muted" style="font-size:.68rem;letter-spacing:.1em;">Main Navigation</li>

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="document_dashboard.php" class="nav-link <?= $current_page === 'document_dashboard.php' ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard
                            <?php if ($notif['total'] > 0): ?>
                            <span class="badge badge-warning right" title="<?= $notif['total'] ?> pending document(s) forwarded to your section"><?= $notif['total'] ?></span>
                            <?php endif; ?>
                        </p>
                    </a>
                </li>

                <li class="nav-header text-uppercase text-muted" style="font-size:.68rem;letter-spacing:.1em;">Document Records</li>

                <!-- All Documents -->
                <li class="nav-item">
                    <a href="document_list.php" class="nav-link <?= $current_page === 'document_list.php' && !isset($_GET['kind']) ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-folder-open"></i>
                        <p>All Documents</p>
                    </a>
                </li>

                <!-- Incoming -->
                <li class="nav-item">
                    <a href="document_list.php?kind=incoming"
                       class="nav-link <?= ($current_page === 'document_list.php' && ($_GET['kind'] ?? '') === 'incoming') ? 'active' : 'text-white' ?>"
                       style="<?= ($current_page === 'document_list.php' && ($_GET['kind'] ?? '') === 'incoming') ? 'background:#0d6efd!important;' : '' ?>">
                        <i class="nav-icon fas fa-inbox"></i>
                        <p>Incoming Documents
                            <?php if ($notif['incoming'] > 0): ?>
                            <span class="badge badge-danger right sidebar-notif-badge" title="<?= $notif['incoming'] ?> pending incoming document(s) for your section">
                                <?= $notif['incoming'] ?>
                            </span>
                            <?php endif; ?>
                        </p>
                    </a>
                </li>

                <!-- Outgoing -->
                <li class="nav-item">
                    <a href="document_list.php?kind=outgoing"
                       class="nav-link <?= ($current_page === 'document_list.php' && ($_GET['kind'] ?? '') === 'outgoing') ? 'active' : 'text-white' ?>"
                       style="<?= ($current_page === 'document_list.php' && ($_GET['kind'] ?? '') === 'outgoing') ? 'background:#198754!important;' : '' ?>">
                        <i class="nav-icon fas fa-paper-plane"></i>
                        <p>Outgoing Documents
                            <?php if ($notif['outgoing'] > 0): ?>
                            <span class="badge badge-danger right sidebar-notif-badge" title="<?= $notif['outgoing'] ?> pending outgoing document(s) for your section">
                                <?= $notif['outgoing'] ?>
                            </span>
                            <?php endif; ?>
                        </p>
                    </a>
                </li>

                <!-- Internal -->
                <li class="nav-item">
                    <a href="document_list.php?kind=internal"
                       class="nav-link <?= ($current_page === 'document_list.php' && ($_GET['kind'] ?? '') === 'internal') ? 'active' : 'text-white' ?>"
                       style="<?= ($current_page === 'document_list.php' && ($_GET['kind'] ?? '') === 'internal') ? 'background:#6f42c1!important;' : '' ?>">
                        <i class="nav-icon fas fa-exchange-alt"></i>
                        <p>Internal Documents
                            <?php if ($notif['internal'] > 0): ?>
                            <span class="badge badge-danger right sidebar-notif-badge" title="<?= $notif['internal'] ?> pending internal document(s) for your section">
                                <?= $notif['internal'] ?>
                            </span>
                            <?php endif; ?>
                        </p>
                    </a>
                </li>

                <li class="nav-header text-uppercase text-muted" style="font-size:.68rem;letter-spacing:.1em;">Settings</li>

                <!-- Document Types Management -->
                <li class="nav-item">
                    <a href="document_types.php" class="nav-link <?= $current_page === 'document_types.php' ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Document Types</p>
                    </a>
                </li>

                <!-- Sections Management -->
                <li class="nav-item">
                    <a href="document_sections.php" class="nav-link <?= $current_page === 'document_sections.php' ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-building"></i>
                        <p>Sections / Offices</p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>

<style>
/* ── Notification badge pulse animation ────────────────────── */
.sidebar-notif-badge {
    animation: badgePulse 2s infinite;
}
@keyframes badgePulse {
    0%   { box-shadow: 0 0 0 0 rgba(220,53,69,.6); }
    70%  { box-shadow: 0 0 0 6px rgba(220,53,69,0); }
    100% { box-shadow: 0 0 0 0 rgba(220,53,69,0); }
}

/* ── Dark mode overrides ────────────────────────────────────── */
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
body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
body.dark-mode .input-group-text { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode label { color: var(--text-primary) !important; }
body.dark-mode .text-muted { color: var(--text-muted) !important; }
body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 { color: var(--text-primary) !important; }
body.dark-mode .breadcrumb { background: var(--card-bg) !important; }
body.dark-mode .breadcrumb-item a { color: #7aabdf !important; }
body.dark-mode .dropdown-menu { background: var(--dropdown-bg) !important; border-color: var(--dropdown-border) !important; }
body.dark-mode .dropdown-item { color: var(--dropdown-color) !important; }
body.dark-mode .dropdown-item:hover { background: var(--table-stripe) !important; }
body.dark-mode .page-item .page-link { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
body.dark-mode .page-item.active .page-link { background: var(--sidebar-active-bg) !important; border-color: var(--sidebar-active-bg) !important; }
body.dark-mode hr { border-color: var(--card-border) !important; }
body.dark-mode .dataTables_wrapper { color: var(--text-primary) !important; }
body.dark-mode .dataTables_filter input, body.dark-mode .dataTables_length select { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .select2-container--bootstrap4 .select2-selection { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode .select2-container--bootstrap4 .select2-selection__rendered { color: var(--input-color) !important; }
body.dark-mode .select2-dropdown { background: var(--dropdown-bg) !important; border-color: var(--card-border) !important; }
body.dark-mode .select2-results__option { color: var(--dropdown-color) !important; }
body.dark-mode .select2-results__option--highlighted { background: var(--sidebar-active-bg) !important; color: #fff !important; }
body.dark-mode .sidebar { background-color: var(--sidebar-bg) !important; }
body.dark-mode aside.main-sidebar { background-color: var(--sidebar-bg) !important; }
.brand-link.bg-gradient-primary { background: #007bff !important; }
</style>

<script>
$(document).ready(function() {
    if (localStorage.getItem('darkMode') === '1') {
        $('body').addClass('dark-mode');
    }

    function updateBadge(navHref, count, badgeClass) {
        var $link = $('.nav-link[href="' + navHref + '"]');
        var $p    = $link.find('p');
        var $badge = $p.find('.badge');

        if (count > 0) {
            if ($badge.length === 0) {
                $badge = $('<span class="badge right sidebar-notif-badge"></span>');
                $p.append($badge);
            }
            $badge.removeClass('badge-warning badge-danger').addClass(badgeClass || 'badge-danger');
            $badge.text(count).show();
        } else {
            $badge.hide();
        }
    }

    function refreshSidebarNotifs() {
        $.get('document_actions.php', { action: 'get_notifications' }, function(r) {
            if (!r.success) return;
            var c = r.counts;
            updateBadge('document_list.php?kind=incoming', c.incoming, 'badge-danger');
            updateBadge('document_list.php?kind=outgoing', c.outgoing, 'badge-danger');
            updateBadge('document_list.php?kind=internal', c.internal, 'badge-danger');
            updateBadge('document_dashboard.php',          c.total,    'badge-warning');
        }, 'json').fail(function(){});
    }

    // Refresh immediately on load, then every 60 seconds
    refreshSidebarNotifs();
    setInterval(refreshSidebarNotifs, 60000);
});
</script>