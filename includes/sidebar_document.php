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

<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="document_dashboard.php" class="brand-link" style="background: var(--sidebar-brand-bg) !important;">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="NIA Logo" class="brand-image img-circle elevation-3" style="opacity:.8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b></span>
    </a>

    <div class="sidebar">
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
                       class="nav-link <?= ($current_page === 'document_list.php' && ($_GET['kind'] ?? '') === 'incoming') ? 'active' : 'text-white' ?>">
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
                       class="nav-link <?= ($current_page === 'document_list.php' && ($_GET['kind'] ?? '') === 'outgoing') ? 'active' : 'text-white' ?>">
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
                       class="nav-link <?= ($current_page === 'document_list.php' && ($_GET['kind'] ?? '') === 'internal') ? 'active' : 'text-white' ?>">
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

                                <!-- Document Types -->
                <li class="nav-item">
                    <a href="document_types.php"
                       class="nav-link <?= $current_page === 'document_types.php' ? 'active bg-olive' : 'text-white' ?>"
                       title="Manage document types">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Document Types</p>
                    </a>
                </li>

                                <!-- Daily Archive -->
                <li class="nav-item">
                    <a href="document_archive.php"
                       class="nav-link <?= $current_page === 'document_archive.php' ? 'active' : 'text-white' ?>"
                       title="Archived daily documents — table resets each midnight">
                        <i class="nav-icon fas fa-archive"></i>
                        <p>Daily Archive
                            <?php
                            // Count archives logged today (PHT)
                            $today_arc = 0;
                            if ($employee_id) {
                                $arc_res = $db2->query("
                                    SELECT COUNT(*) AS cnt
                                    FROM document_archive
                                    WHERE DATE(archived_at) = CURDATE()
                                ");
                                if ($arc_res) { $today_arc = (int)($arc_res->fetch_assoc()['cnt'] ?? 0); }
                            }
                            if ($today_arc > 0): ?>
                            <span class="badge badge-secondary right" title="<?= $today_arc ?> document(s) archived today"><?= $today_arc ?></span>
                            <?php endif; ?>
                        </p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>

<style>
/* ── Document sidebar: notification badge pulse ────────────── */
.sidebar-notif-badge {
    animation: badgePulse 2s infinite;
}
@keyframes badgePulse {
    0%   { box-shadow: 0 0 0 0 rgba(220,53,69,.6); }
    70%  { box-shadow: 0 0 0 6px rgba(220,53,69,0); }
    100% { box-shadow: 0 0 0 0 rgba(220,53,69,0); }
}

/* Scrollbar matches the green theme */
.sidebar::-webkit-scrollbar { width: 5px; }
.sidebar::-webkit-scrollbar-track { background: var(--sidebar-bg); }
.sidebar::-webkit-scrollbar-thumb { background: var(--green-dark, #2a9863); border-radius: 5px; }
.sidebar::-webkit-scrollbar-thumb:hover { background: var(--green, #24e78f); }

/* Section headers */
.nav-header {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .1em;
    color: rgba(255,255,255,.45) !important;
    padding: 14px 1rem 4px;
}
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