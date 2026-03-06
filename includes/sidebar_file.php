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
?>

<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard_ia.php" class="brand-link bg-gradient-primary">
        <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar" style="background-color: #2c3e50 !important;">
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

        <!-- New Folder Button - Windows Explorer style -->
        <div class="sidebar-header">
            <button class="btn-new-folder" data-toggle="modal" data-target="#createFolderModal">
                <i class="fas fa-folder-plus"></i>
                <span>New folder</span>
            </button>
        </div>

        <!-- Navigation - Windows Explorer style -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                
                <!-- Quick Access Section -->
                <li class="nav-header">Quick access</li>
                
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
                
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="if(typeof filterShared==='function')filterShared();return false;">
                        <i class="nav-icon fas fa-share-alt"></i>
                        <p>Shared with me</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="if(typeof filterImportant==='function')filterImportant();return false;">
                        <i class="nav-icon far fa-star"></i>
                        <p>Starred</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="if(typeof filterTrash==='function')filterTrash();return false;">
                        <i class="nav-icon fas fa-trash"></i>
                        <p>Trash</p>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <!-- This PC / My Drive Section -->
                <li class="nav-header">This PC</li>
                
                <!-- Root folder -->
                <li class="nav-item" id="sbNavRootItem">
                    <a href="#" class="nav-link sb-active" onclick="if(typeof showRootView==='function')showRootView();return false;">
                        <i class="nav-icon fas fa-hdd"></i>
                        <p><?= htmlspecialchars($sb_section_name) ?></p>
                        <?php if (!empty($sb_folders)): ?>
                            <span class="badge badge-info right"><?= count($sb_folders) ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <!-- Folders with tree structure -->
                <?php if (!empty($sb_folders)): ?>
                    <?php foreach ($sb_folders as $sf): 
                        $sf_access = isset($db) && function_exists('hasFolderPermission')
                            ? hasFolderPermission($db, $sf['folder_id'], isset($user_emp_id) ? $user_emp_id : 0, 'view')
                            : true;
                        
                        // Check if this folder has subfolders (you'll need to implement this logic)
                        $has_subfolders = false; // Replace with actual check
                    ?>
                    <li class="nav-item <?= $has_subfolders ? 'has-treeview' : '' ?>" id="sbNavFolder_<?= $sf['folder_id'] ?>">
                        <a href="#" 
                           class="nav-link <?= !$sf_access ? 'disabled' : '' ?>"
                           onclick="<?= $sf_access ? "openFolder({$sf['folder_id']},'" . htmlspecialchars(addslashes($sf['folder_name'])) . "',{$sf['is_locked']});return false;" : "showNoAccess();return false;" ?>">
                            
                            <?php if ($has_subfolders): ?>
                                <i class="nav-icon fas fa-chevron-right"></i>
                            <?php endif; ?>
                            
                            <i class="fas fa-folder folder-icon <?= $sf['is_locked'] ? 'locked' : '' ?>"></i>
                            <p>
                                <?= htmlspecialchars($sf['folder_name']) ?>
                                <?php if ($sf['is_locked']): ?>
                                    <i class="fas fa-lock ml-1" style="font-size: 11px;"></i>
                                <?php endif; ?>
                            </p>
                            
                            <?php if ($sf['file_count'] > 0): ?>
                                <span class="badge badge-light right"><?= $sf['file_count'] ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <?php if ($has_subfolders): ?>
                            <ul class="nav nav-treeview">
                                <!-- Subfolders would go here -->
                            </ul>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                
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
    background: #2c3e50 !important;
    box-shadow: none !important;
}

.main-sidebar .sidebar {
    background: #2c3e50 !important;
}

.brand-link {
    background: #1e2b38 !important;
    border-bottom: 1px solid #3a4a5a !important;
    padding: 15px !important;
}

.brand-link .brand-text {
    color: #ecf0f1 !important;
    font-weight: 500 !important;
}

/* User panel */
.user-panel {
    border-bottom: 1px solid #3a4a5a !important;
    padding-bottom: 15px !important;
    margin-bottom: 10px !important;
}

.user-panel .info a {
    color: #ecf0f1 !important;
    font-size: 14px;
}

/* New folder button - Windows Explorer style */
.sidebar-header {
    padding: 10px 15px;
    border-bottom: 1px solid #3a4a5a;
}

.btn-new-folder {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 8px 12px;
    background: #34495e;
    color: #ecf0f1;
    border: 1px solid #4a5a6a;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-new-folder:hover {
    background: #3d566e;
    border-color: #5a6a7a;
}

.btn-new-folder i {
    color: #f1c40f;
    font-size: 14px;
}

/* Navigation headers */
.nav-header {
    padding: 8px 15px 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #95a5a6;
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
    color: #bdc3c7;
    border-radius: 0;
    transition: all 0.2s;
    gap: 10px;
}

.nav-sidebar > .nav-item > .nav-link:hover {
    background: #34495e;
    color: #ecf0f1;
}

.nav-sidebar > .nav-item > .nav-link.active,
.nav-sidebar > .nav-item > .nav-link.sb-active {
    background: #2980b9;
    color: #fff;
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
    color: #f1c40f;
    font-size: 14px;
    width: 20px;
    text-align: center;
}

.folder-icon.locked {
    color: #e67e22;
}

/* Tree view for subfolders */
.nav-treeview {
    padding-left: 35px;
    list-style: none;
}

.nav-treeview .nav-item {
    margin: 2px 0;
}

.nav-treeview .nav-link {
    display: flex;
    align-items: center;
    padding: 6px 15px;
    color: #bdc3c7;
    font-size: 13px;
    gap: 8px;
}

.nav-treeview .nav-link:hover {
    background: #34495e;
    color: #ecf0f1;
}

/* Badges */
.nav-sidebar .badge {
    margin-left: auto;
    font-weight: 400;
    background: #3a4a5a;
    color: #bdc3c7;
    padding: 2px 6px;
    font-size: 11px;
}

.nav-sidebar .nav-link.active .badge,
.nav-sidebar .nav-link.sb-active .badge {
    background: #1f6a9a;
    color: #fff;
}

/* Divider */
.nav-divider {
    height: 1px;
    margin: 10px 15px;
    background: #3a4a5a;
    list-style: none;
}

/* Storage info - Windows Explorer style */
.storage-info {
    padding: 15px;
    margin-top: 10px;
}

.storage-bar {
    height: 4px;
    background: #3a4a5a;
    border-radius: 2px;
    margin-bottom: 8px;
    overflow: hidden;
}

.storage-bar-fill {
    height: 100%;
    background: #3498db;
    border-radius: 2px;
}

.storage-details {
    font-size: 11px;
    color: #95a5a6;
}

.storage-details i {
    margin-right: 5px;
    font-size: 11px;
}

.storage-details a {
    color: #3498db;
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

body.dark-mode .sidebar { background-color: var(--sidebar-bg) !important; }
body.dark-mode aside.main-sidebar { background-color: var(--sidebar-bg) !important; }
.brand-link.bg-gradient-primary {
    background:  #007bff !important;
}
</style>

<script>
$(document).ready(function() {
    if (localStorage.getItem('darkMode') === '1') {
        $('body').addClass('dark-mode');
    }
    
    // Initialize treeview for folders with subfolders
    $('.has-treeview > .nav-link').click(function(e) {
        e.preventDefault();
        $(this).parent().toggleClass('menu-open');
        $(this).find('.fa-chevron-right').toggleClass('fa-chevron-down');
    });
});
</script>