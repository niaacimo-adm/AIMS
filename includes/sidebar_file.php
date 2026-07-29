<?php
ob_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$current_page = basename($_SERVER['PHP_SELF']);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$employee_name    = '';
$employee_picture = '../dist/img/user2-160x160.jpg';
$employee_id      = $_SESSION['emp_id'] ?? null;

if ($employee_id) {
    $database_sb = new Database();
    $db_sb = $database_sb->getConnection();
    $q = "SELECT first_name, last_name, picture FROM employee WHERE emp_id = ?";
    $st = $db_sb->prepare($q);
    $st->bind_param("i", $employee_id);
    $st->execute();
    $r = $st->get_result();
    if ($r->num_rows > 0) {
        $ed = $r->fetch_assoc();
        $employee_name = htmlspecialchars($ed['first_name'] . ' ' . $ed['last_name']);
        if (!empty($ed['picture'])) {
            $pp = '../dist/img/employees/' . $ed['picture'];
            if (file_exists($pp)) $employee_picture = $pp;
        }
    }
}
ob_end_clean();

/* These vars come from the parent (section_files.php): $folders, $section_name, $db, $user_emp_id */
$sb_folders      = isset($folders) ? $folders : [];
$sb_section_name = isset($section_name) ? $section_name : 'My Drive';

// Build recursive folder tree for the sidebar
function sbBuildTree($db, $parent_id) {
    $children = [];
    if (!$db) return $children;
    $st = $db->prepare("SELECT f.folder_id, f.folder_name, f.is_locked,
        (SELECT COUNT(*) FROM folders WHERE parent_folder_id = f.folder_id) as child_count
        FROM folders f WHERE f.parent_folder_id = ? ORDER BY f.folder_name");
    if (!$st) return $children;
    $st->bind_param("i", $parent_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['children'] = sbBuildTree($db, $row['folder_id']);
        $children[] = $row;
    }
    return $children;
}

$sb_db = isset($db) ? $db : null;
foreach ($sb_folders as &$sf) {
    $sf['children'] = sbBuildTree($sb_db, $sf['folder_id']);
}
unset($sf);
?>

<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard_ia.php" class="brand-link bg-gradient-primary">
        <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar" style="background-color: var(--sidebar-bg, #1c4d38) !important;">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="<?= $employee_picture ?>" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="d-block text-white"><?= $employee_name ?: htmlspecialchars($_SESSION['username']) ?></a>
            <?php if (isset($_SESSION['role_name'])): ?>
            <span class="badge badge-primary mt-1">
                <?= htmlspecialchars($_SESSION['role_name']) ?>
            </span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>



        <!-- Navigation -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" role="menu">

                <!-- ── THIS PC ─────────────────────────────────── -->
                <li class="nav-header">This PC</li>

                <li class="nav-item" id="sbNavRootItem">
                    <?php
                    $current_page = basename($_SERVER['PHP_SELF']);
                    $hdd_href = ($current_page === 'folder_contents.php')
                        ? 'section_files.php?section_id=' . (isset($section_id) ? urlencode($section_id) : '')
                        : 'folder_contents.php?section_id=' . (isset($section_id) ? urlencode($section_id) : '');
                    ?>
                    <a href="<?= $hdd_href ?>" class="nav-link sb-active" id="sbRootLink">
                        <i class="nav-icon fas fa-hdd"></i>
                        <p><?= htmlspecialchars($sb_section_name) ?></p>
                    </a>
                </li>

                <!-- ── QUICK ACCESS ────────────────────────────── -->
                <li class="nav-header">Quick Access</li>

                <li class="nav-item">
                    <a href="file_management.php" class="nav-link <?= $current_page == 'file_management.php' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Home</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link" data-toggle="modal" data-target="#uploadFileModal">
                        <i class="nav-icon fas fa-cloud-upload-alt"></i>
                        <p>Upload</p>
                    </a>
                </li>

                <!-- Uploaded Files: collapsible with search bar -->
                <li class="nav-item" id="sbUploadedItem">
                    <a href="#" class="nav-link" id="sbUploadedToggle"
                       onclick="sbToggleUploaded();return false;">
                        <i class="nav-icon fas fa-file-upload"></i>
                        <p>Uploaded Files
                            <i class="right fas fa-angle-left" id="sbUploadedArrow"></i>
                        </p>
                    </a>
                    <div id="sbUploadedPanel" style="display:none;padding:4px 8px 8px;">
                        <div class="sb-search-wrap">
                            <i class="fas fa-search sb-search-icon"></i>
                            <input type="text" id="sbFileSearch" class="sb-search-input" placeholder="Search files&hellip;" autocomplete="off">
                        </div>
                        <div id="sbFileResults">
                            <div class="sb-loading"><i class="fas fa-spinner fa-spin"></i> Loading&hellip;</div>
                        </div>
                    </div>
                </li>

                <!-- ── VIEWS ───────────────────────────────────── -->
                <li class="nav-header">Views</li>

                <li class="nav-item">
                    <a href="#" class="nav-link" id="sbSharedLink"
                       onclick="if(typeof filterShared==='function'){filterShared();sbSetActive(this);}return false;">
                        <i class="nav-icon fas fa-share-alt"></i>
                        <p>Shared with me</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="starred_files.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'starred_files.php' ? 'active' : '' ?>" id="sbStarredLink">
                        <i class="nav-icon fas fa-star" style="color:#f59e0b;"></i>
                        <p>Starred</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link" id="sbTrashLink"
                       onclick="if(typeof filterTrash==='function'){filterTrash();sbSetActive(this);}return false;">
                        <i class="nav-icon fas fa-trash"></i>
                        <p>Trash</p>
                    </a>
                </li>

                <li class="nav-divider"></li>

                <!-- Storage info -->
                <li class="nav-item storage-info">
                    <div class="storage-bar">
                        <div class="storage-bar-fill" style="width: 45%;"></div>
                    </div>
                    <div class="storage-details">
                        <span><i class="fas fa-cloud"></i> 4.5 GB of 10 GB used</span>
                        <a href="#" class="float-right">Get more storage</a>
                    </div>
                </li>
            </ul>
        </nav>
    </div>
</aside>

<style>
/* Windows Explorer style sidebar */
.main-sidebar {
    background: var(--sidebar-bg, #1c4d38) !important;
    box-shadow: none !important;
}

.main-sidebar .sidebar {
    background: var(--sidebar-bg, #1c4d38) !important;
}

.brand-link {
    background: var(--sidebar-brand-bg, #1a5c38) !important;
    border-bottom: 1px solid rgba(255,255,255,.14) !important;
    padding: 15px !important;
}

.brand-link .brand-text {
    color: rgba(255,255,255,.92) !important;
    font-weight: 500 !important;
}

/* User panel */
.user-panel {
    border-bottom: 1px solid rgba(255,255,255,.14) !important;
    padding-bottom: 15px !important;
    margin-bottom: 10px !important;
}

.user-panel .info a {
    color: rgba(255,255,255,.92) !important;
    font-size: 14px;
}

/* New folder button - Windows Explorer style */
.sidebar-header {
    padding: 10px 15px;
    border-bottom: 1px solid rgba(255,255,255,.14);
}

.btn-new-folder {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 8px 12px;
    background: var(--sidebar-hover-bg, rgba(36,231,143,.12));
    color: var(--sidebar-text, rgba(255,255,255,.80));
    border: 1px solid rgba(255,255,255,.16);
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-new-folder:hover {
    background: rgba(36,231,143,.20);
    border-color: rgba(255,255,255,.26);
}

.btn-new-folder i {
    color: var(--green, #24e78f);
    font-size: 14px;
}

/* Navigation headers */
.nav-header {
    padding: 8px 15px 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: rgba(255,255,255,.45);
    list-style: none;
}

/* Navigation items - Windows Explorer style */
.nav-sidebar > .nav-item {
    margin: 2px 0;
}

.nav-sidebar > .nav-item > .nav-link {
    display: flex;
    align-items: center;
    padding: 8px 15px;
    color: var(--sidebar-text, rgba(255,255,255,.80));
    border-radius: 0;
    transition: all 0.2s;
    gap: 10px;
}

.nav-sidebar > .nav-item > .nav-link:hover {
    background: var(--sidebar-hover-bg, rgba(36,231,143,.12));
    color: rgba(255,255,255,.95);
}

.nav-sidebar > .nav-item > .nav-link.active,
.nav-sidebar > .nav-item > .nav-link.sb-active {
    background: var(--sidebar-active-bg, #24e78f);
    color: var(--sidebar-active-text, #0f2d1e);
}

.nav-sidebar > .nav-item > .nav-link.disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* Icons */
.nav-sidebar .nav-icon {
    width: 20px;
    text-align: center;
    font-size: 14px;
    margin-right: 0;
}

.folder-icon {
    color: var(--green, #24e78f);
    font-size: 14px;
    width: 20px;
    text-align: center;
}

.folder-icon.locked {
    color: #e67e22;
}

/* ── Sections / Uploaded collapsible arrows ──────────────────── */
.nav-sidebar .nav-link p .fa-angle-left {
    transition: transform 0.2s ease;
}
#sbSectionsItem.sb-open > .nav-link #sbSectionsArrow,
#sbUploadedItem.sb-open  > .nav-link #sbUploadedArrow {
    transform: rotate(-90deg);
}

/* Treeview sub-list */
.nav-treeview {
    padding-left: 0;
    list-style: none;
    background: rgba(0,0,0,0.12);
}

.nav-treeview .nav-item {
    margin: 2px 0;
}

.nav-treeview .nav-link {
    display: flex;
    align-items: center;
    padding: 6px 15px;
    color: var(--sidebar-text, rgba(255,255,255,.80));
    font-size: 13px;
    gap: 8px;
}

.nav-treeview .nav-link:hover {
    background: var(--sidebar-hover-bg, rgba(36,231,143,.12));
    color: rgba(255,255,255,.95);
}

/* Badges */
.nav-sidebar .badge {
    margin-left: auto;
    font-weight: 400;
    background: rgba(255,255,255,.14);
    color: var(--sidebar-text, rgba(255,255,255,.80));
    padding: 2px 6px;
    font-size: 11px;
}

.nav-sidebar .nav-link.active .badge,
.nav-sidebar .nav-link.sb-active .badge {
    background: var(--sidebar-active-text, #0f2d1e);
    color: var(--sidebar-active-bg, #24e78f);
}

/* Divider */
.nav-divider {
    height: 1px;
    margin: 10px 15px;
    background: rgba(255,255,255,.14);
    list-style: none;
}

/* Storage info - Windows Explorer style */
.storage-info {
    padding: 15px;
    margin-top: 10px;
}

.storage-bar {
    height: 4px;
    background: rgba(255,255,255,.14);
    border-radius: 2px;
    margin-bottom: 8px;
    overflow: hidden;
}

.storage-bar-fill {
    height: 100%;
    background: var(--green, #24e78f);
    border-radius: 2px;
}

.storage-details {
    font-size: 11px;
    color: rgba(255,255,255,.45);
}

.storage-details i {
    margin-right: 5px;
    font-size: 11px;
}

.storage-details a {
    color: var(--green, #24e78f);
    text-decoration: none;
}

.storage-details a:hover {
    text-decoration: underline;
}

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
body.dark-mode .form-control:focus { border-color: var(--green, #24e78f) !important; box-shadow: 0 0 0 0.2rem rgba(36,231,143,.25) !important; }
body.dark-mode select.form-control option { background: var(--input-bg) !important; color: var(--input-color) !important; }
body.dark-mode .input-group-text { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
body.dark-mode label, body.dark-mode .form-label { color: var(--text-primary) !important; }
body.dark-mode .text-muted { color: var(--text-muted) !important; }
body.dark-mode .text-dark { color: var(--text-primary) !important; }
body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 { color: var(--text-primary) !important; }
body.dark-mode p, body.dark-mode span:not(.badge) { color: var(--text-primary); }
body.dark-mode .breadcrumb { background: var(--card-bg) !important; }
body.dark-mode .breadcrumb-item a { color: var(--green, #24e78f) !important; }
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

body.dark-mode .sidebar { background-color: var(--sidebar-bg) !important; }
body.dark-mode aside.main-sidebar { background-color: var(--sidebar-bg) !important; }
.brand-link.bg-gradient-primary { background: var(--sidebar-brand-bg, #1a5c38) !important; }

/* Folder icons inside treeview */
.nav-treeview .nav-folder-icon {
    color: var(--green, #24e78f);
    font-size: 13px;
    width: 18px;
    text-align: center;
    flex-shrink: 0;
    margin-right: 4px;
}
.nav-treeview .nav-folder-icon.locked { color: #e67e22; }
.nav-treeview .nav-link { display: flex; align-items: center; gap: 6px;
    padding: 6px 15px 6px 22px; color: var(--sidebar-text, rgba(255,255,255,.80)); font-size: 13px; transition: background .15s; }
.nav-treeview .nav-link:hover { background: var(--sidebar-hover-bg, rgba(36,231,143,.12)); color: rgba(255,255,255,.95); }
.nav-treeview .nav-link.sb-active,
.nav-treeview .nav-link.active  { background: var(--sidebar-active-bg, #24e78f); color: var(--sidebar-active-text, #0f2d1e); }
.nav-treeview .nav-link.disabled { opacity:.5; pointer-events:none; }

/* Sections icon colour */
.sb-folder-open-icon { color: var(--green, #24e78f) !important; }

/* ── Uploaded-files search panel ─────────────────────────────── */
.sb-search-wrap {
    display: flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.18);
    border-radius: 4px; padding: 5px 9px; margin-bottom: 5px;
}
.sb-search-icon { color: rgba(255,255,255,.45); font-size: 11px; flex-shrink: 0; }
.sb-search-input {
    background: transparent; border: none; outline: none;
    color: rgba(255,255,255,.92); font-size: 12px; width: 100%;
}
.sb-search-input::placeholder { color: rgba(255,255,255,.45); }

#sbFileResults {
    max-height: 200px; overflow-y: auto; border-radius: 3px;
}
#sbFileResults::-webkit-scrollbar { width: 4px; }
#sbFileResults::-webkit-scrollbar-track { background: var(--sidebar-bg, #1c4d38); }
#sbFileResults::-webkit-scrollbar-thumb { background: rgba(255,255,255,.22); border-radius: 2px; }

.sb-loading { color: rgba(255,255,255,.45); font-size: 12px; padding: 7px 4px; text-align: center; }
.sb-file-row {
    display: flex; align-items: center; gap: 7px;
    padding: 5px 6px; border-radius: 3px; cursor: pointer;
    font-size: 12px; color: var(--sidebar-text, rgba(255,255,255,.80)); transition: background .15s;
}
.sb-file-row:hover { background: var(--sidebar-hover-bg, rgba(36,231,143,.12)); color: rgba(255,255,255,.95); }
.sb-file-row i { font-size: 12px; width: 14px; text-align: center; flex-shrink: 0; }
.sb-file-row span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sb-empty { color: rgba(255,255,255,.45); font-size: 12px; padding: 7px 4px; text-align: center; }

/* ── Sidebar Folder Tree ─────────────────────────────────────── */
#sbFolderTreePanel { max-height: 50vh; overflow-y: auto; }
#sbFolderTreePanel::-webkit-scrollbar { width: 3px; }
#sbFolderTreePanel::-webkit-scrollbar-thumb { background: rgba(255,255,255,.22); border-radius: 2px; }

.sb-tree-row {
    display: flex; align-items: center; gap: 5px;
    padding: 5px 8px; cursor: pointer; font-size: 12px;
    color: var(--sidebar-text, rgba(255,255,255,.80)); transition: background .15s; white-space: nowrap;
    border-radius: 3px; margin: 1px 4px;
}
.sb-tree-row:hover { background: var(--sidebar-hover-bg, rgba(36,231,143,.12)); color: rgba(255,255,255,.95); }
.sb-tree-row.sb-tree-active { background: var(--sidebar-active-bg, #24e78f); color: var(--sidebar-active-text, #0f2d1e); }
.sb-tree-row.sb-tree-active .sb-tree-folder-icon { color: var(--sidebar-active-text, #0f2d1e); }

.sb-tree-arrow { width: 12px; flex-shrink: 0; font-size: 9px; color: rgba(255,255,255,.45);
    transition: transform .15s; display: inline-flex; align-items: center; }
.sb-tree-arrow.open { transform: rotate(90deg); }
.sb-tree-arrow-hidden { opacity: 0; pointer-events: none; }

.sb-tree-folder-icon { color: var(--green, #24e78f); font-size: 12px; flex-shrink: 0; }
.sb-tree-locked { color: #e67e22 !important; }
.sb-tree-name { flex: 1; overflow: hidden; text-overflow: ellipsis; }
.sb-tree-lock-badge { font-size: 9px; color: #e67e22; flex-shrink: 0; }
</style>

<script>
$(document).ready(function() {
    if (localStorage.getItem('darkMode') === '1') $('body').addClass('dark-mode');
});

/* ── Sidebar folder tree toggle ──────────────────────────────── */
function sbToggleFolderTree() {
    var $panel = $('#sbFolderTreePanel');
    var $arrow = $('#sbFolderTreeArrow');
    if ($panel.is(':visible')) {
        $panel.slideUp(180);
        $arrow.css('transform', '');
    } else {
        $panel.slideDown(180);
        $arrow.css('transform', 'rotate(-90deg)');
    }
}

/* ── Folder tree row click: expand/collapse + navigate ───────── */
function sbTreeClick(e, folderId, isLocked, folderName, hasChildren, subId) {
    e.stopPropagation();
    var $row = $(e.currentTarget);
    var $arr = $('#arr_' + folderId);

    // Toggle children
    if (hasChildren) {
        var $sub = $('#' + subId);
        if ($sub.is(':visible')) {
            $sub.slideUp(150);
            $arr.removeClass('open');
        } else {
            $sub.slideDown(150);
            $arr.addClass('open');
        }
    }

    // Highlight active
    $('.sb-tree-row').removeClass('sb-tree-active');
    $row.addClass('sb-tree-active');

    // Navigate to folder
    if (typeof openFolder === 'function') {
        openFolder(folderId, folderName, isLocked);
    } else if (typeof navigateToFolder === 'function') {
        navigateToFolder(folderId, isLocked);
    }
}

/* ── Active state helper ─────────────────────────────────────── */
function sbSetActive(el) {
    $('.nav-sidebar .nav-link').removeClass('active sb-active');
    $(el).addClass('sb-active');
}

/* ── Fallback showRootView (used if page doesn't define its own) ─ */
if (typeof showRootView === 'undefined') {
    function showRootView() {
        window.location.href = 'section_files.php?section_id=<?= isset($section_id) ? urlencode($section_id) : '' ?>';
    }
}


/* ── Uploaded Files toggle + lazy load ──────────────────────── */
var _sbUploaded = false;
function sbToggleUploaded() {
    var $item  = $('#sbUploadedItem');
    var $panel = $('#sbUploadedPanel');
    if ($item.hasClass('sb-open')) {
        $panel.slideUp(180); $item.removeClass('sb-open');
    } else {
        $panel.slideDown(180); $item.addClass('sb-open');
        if (!_sbUploaded) { _sbUploaded = true; sbLoadFiles(''); }
    }
}

/* ── Debounced search ────────────────────────────────────────── */
var _sbTimer = null;
$(document).on('input', '#sbFileSearch', function() {
    var q = $(this).val();
    clearTimeout(_sbTimer);
    _sbTimer = setTimeout(function(){ sbLoadFiles(q); }, 300);
});

/* ── AJAX load uploaded files ────────────────────────────────── */
function sbLoadFiles(query) {
    $('#sbFileResults').html('<div class="sb-loading"><i class="fas fa-spinner fa-spin"></i> Loading…</div>');
    $.post(window.location.pathname + window.location.search,
           { action: 'get_sidebar_files', query: query || '' },
    function(resp) {
        try {
            var r = (typeof resp === 'string') ? JSON.parse(resp) : resp;
            if (r.success && r.files && r.files.length) {
                var iconMap = {
                    pdf:'file-pdf text-danger', doc:'file-word text-primary', docx:'file-word text-primary',
                    xls:'file-excel text-success', xlsx:'file-excel text-success',
                    ppt:'file-powerpoint text-warning', pptx:'file-powerpoint text-warning',
                    jpg:'file-image text-info', jpeg:'file-image text-info', png:'file-image text-info',
                    gif:'file-image text-info', zip:'file-archive text-secondary', rar:'file-archive text-secondary',
                    txt:'file-alt text-muted', mp4:'file-video text-danger',
                    avi:'file-video text-danger', mp3:'file-audio text-info', wav:'file-audio text-info'
                };
                var html = '';
                $.each(r.files, function(i, f) {
                    var ext  = (f.file_type || '').toLowerCase();
                    var icon = iconMap[ext] || 'file text-secondary';
                    var name = _sbEsc(f.file_name);
                    html += '<div class="sb-file-row" title="'+name+'" onclick="viewFileModal('+f.file_id+')">'
                          + '<i class="fas fa-' + icon + '"></i><span>' + name + '</span></div>';
                });
                $('#sbFileResults').html(html);
            } else {
                $('#sbFileResults').html('<div class="sb-empty"><i class="fas fa-inbox mr-1"></i>'
                    + (r.files && r.files.length === 0 ? 'No files found' : (r.message || 'No files')) + '</div>');
            }
        } catch(e) {
            $('#sbFileResults').html('<div class="sb-empty text-danger"><i class="fas fa-exclamation-circle mr-1"></i> Error loading</div>');
        }
    }).fail(function() {
        $('#sbFileResults').html('<div class="sb-empty text-danger"><i class="fas fa-exclamation-circle mr-1"></i> Load failed</div>');
    });
}

function _sbEsc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>