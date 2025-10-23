<?php
ob_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get employee data if user is logged in
$employee_name = '';
$employee_picture = '../dist/img/user2-160x160.jpg'; // Default image
$employee_id = $_SESSION['emp_id'] ?? null;

// Get document counts and user section
$document_counts = ['incoming' => 0, 'outgoing' => 0, 'internal' => 0];
$user_section = null;
$user_section_name = '';
$user_section_code = '';

if ($employee_id) {
    // Database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Query to get employee name and picture
    $query = "SELECT first_name, last_name, picture FROM employee WHERE emp_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $employee_data = $result->fetch_assoc();
        $employee_name = htmlspecialchars($employee_data['first_name'] . ' ' . $employee_data['last_name']);
        
        // Check if picture exists
        if (!empty($employee_data['picture'])) {
            $picture_path = '../dist/img/employees/' . $employee_data['picture'];
            if (file_exists($picture_path)) {
                $employee_picture = $picture_path;
            }
        }
    }
    
    // Get document counts and user section using DocumentFunctions
    require_once '../includes/document_functions.php';
    $documentFunctions = new DocumentFunctions();
    $document_counts = $documentFunctions->getDocumentCounts();
    $user_section = $documentFunctions->getCurrentUserSection($employee_id);
    
    if ($user_section) {
        $user_section_name = $user_section['section_name'];
        $user_section_code = $user_section['section_code'];
    }
}

// Get section-specific document counts
$section_document_counts = ['incoming' => 0, 'outgoing' => 0, 'internal' => 0];
if ($user_section) {
    $section_counts_query = "SELECT document_type, COUNT(*) as count 
                           FROM document_monitoring 
                           WHERE to_section_id = ? 
                           GROUP BY document_type";
    $stmt = $db->prepare($section_counts_query);
    $stmt->bind_param("i", $user_section['section_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $section_document_counts[$row['document_type']] = $row['count'];
    }
}
?>
<aside class="main-sidebar sidebar-dark-olive elevation-4">
    <!-- Brand Logo -->
    <a href="document_dashboard.php" class="brand-link bg-gradient-olive">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar" style="background-color: #2b2b2b !important;">
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

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">
                <!-- Main Document Navigation -->
                <li class="nav-header text-uppercase text-muted">MAIN NAVIGATION</li>
                
                <li class="nav-item">
                    <a href="document_dashboard.php" class="nav-link <?= $current_page == 'document_dashboard.php' ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="document_monitoring.php" class="nav-link <?= $current_page == 'document_monitoring.php' ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-plus"></i>
                        <p>Create Documents</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="received_documents.php" class="nav-link <?= $current_page == 'received_documents.php' ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-handshake"></i>
                        <p>Received Documents</p>
                        <span class="badge badge-light badge-pill ml-auto">
                            <?= count($received_documents ?? []) ?>
                        </span>
                    </a>
                </li>
                <!-- Section-Specific Documents -->
                <?php if ($user_section_name): ?>
                <li class="nav-header text-uppercase text-muted mt-3">MY SECTION DOCUMENTS</li>
                
                <li class="nav-item">
                    <a href="section_documents.php?type=incoming" class="nav-link <?= ($current_page == 'section_documents.php' && ($_GET['type'] ?? '') == 'incoming') ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-inbox"></i>
                        <p>Incoming to <?= $user_section_code ?></p>
                        <span class="badge badge-light badge-pill ml-auto"><?= $section_document_counts['incoming'] ?></span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="section_documents.php?type=outgoing" class="nav-link <?= ($current_page == 'section_documents.php' && ($_GET['type'] ?? '') == 'outgoing') ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-paper-plane"></i>
                        <p>Outgoing from <?= $user_section_code ?></p>
                        <span class="badge badge-light badge-pill ml-auto"><?= $section_document_counts['outgoing'] ?></span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="section_documents.php?type=internal" class="nav-link <?= ($current_page == 'section_documents.php' && ($_GET['type'] ?? '') == 'internal') ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-exchange-alt"></i>
                        <p>Internal for <?= $user_section_code ?></p>
                        <span class="badge badge-light badge-pill ml-auto"><?= $section_document_counts['internal'] ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <!-- All Documents (Admin/Focal View) -->
                <li class="nav-header text-uppercase text-muted mt-3">ALL DOCUMENTS</li>
                
                <li class="nav-item">
                    <a href="documents_incoming.php" class="nav-link <?= $current_page == 'documents_incoming.php' ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-inbox"></i>
                        <p>All Incoming Documents</p>
                        <span class="badge badge-light badge-pill ml-auto"><?= $document_counts['incoming'] ?></span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="documents_outgoing.php" class="nav-link <?= $current_page == 'documents_outgoing.php' ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-paper-plane"></i>
                        <p>All Outgoing Documents</p>
                        <span class="badge badge-light badge-pill ml-auto"><?= $document_counts['outgoing'] ?></span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="documents_internal.php" class="nav-link <?= $current_page == 'documents_internal.php' ? 'active bg-olive' : 'text-white' ?>">
                        <i class="nav-icon fas fa-exchange-alt"></i>
                        <p>All Internal Documents</p>
                        <span class="badge badge-light badge-pill ml-auto"><?= $document_counts['internal'] ?></span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>

<style>
.sidebar-dark-olive .nav-sidebar > .nav-item > .nav-link {
    color: #c2c7d0 !important;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
}

.sidebar-dark-olive .nav-sidebar > .nav-item > .nav-link.active {
    background-color: #556b2f !important;
    color: #f8f9fa !important;
    border-left: 4px solid #d4af37;
}

.sidebar-dark-olive .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: rgba(85, 107, 47, 0.3) !important;
    color: #f8f9fa !important;
}

.brand-link.bg-gradient-olive {
    background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%) !important;
}

.nav-header {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 1rem;
    padding: 0.5rem 1rem;
    color: #6c757d !important;
    border-bottom: 1px solid #444;
}

.content-wrapper {
    margin-left: 250px !important;
    min-height: 100vh;
    background-color: #f8f9fa !important;
}

@media (max-width: 768px) {
    .main-sidebar.sidebar-dark-olive {
        transform: translateX(-100%);
    }
    
    .sidebar-open .main-sidebar.sidebar-dark-olive {
        transform: translateX(0);
    }
    
    .content-wrapper {
        margin-left: 0 !important;
    }
}

/* Olive color classes */
.bg-olive {
    background-color: #556b2f !important;
}

.btn-olive {
    background-color: #556b2f;
    border-color: #556b2f;
    color: #f8f9fa;
}

.btn-olive:hover {
    background-color: #465823;
    border-color: #465823;
    color: #f8f9fa;
}

.bg-gradient-olive {
    background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%) !important;
}

/* Update user panel badge */
.user-panel .info .badge {
    background-color: #556b2f !important;
    color: #f8f9fa !important;
    font-size: 0.7rem;
    margin-top: 2px;
}

.user-panel .info .badge-success {
    background-color: #28a745 !important;
}

/* Document count badges */
.nav-sidebar .badge {
    font-size: 0.7rem;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-sidebar .badge-light {
    background-color: #f8f9fa !important;
    color: #2b2b2b !important;
}
</style>
<script>
$(document).ready(function() {
    // Force set document theme
    localStorage.setItem('currentTheme', 'document');
    // Trigger theme update in mainheader
    if (window.parent && window.parent.setTheme) {
        window.parent.setTheme('document');
    }
});
</script>