<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get employee data if user is logged in
$employee_name = '';
$employee_picture = '../dist/img/user2-160x160.jpg'; // Default image
$employee_id = $_SESSION['emp_id'] ?? null;

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
}
?>
<style>
/* Make sidebar a flex column with fixed height */
.main-sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    width: 250px !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
}

/* Brand logo stays at top */
.brand-link {
    flex-shrink: 0 !important;
}

/* Sidebar content becomes scrollable */
.sidebar {
    flex: 1 !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding-bottom: 20px !important;
}

/* Keep existing styles */
.sidebar-dark-primary {
    background-color: var(--sidebar-bg) !important;
}

.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link {
    color: var(--sidebar-text) !important;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
    background-color: var(--sidebar-active-bg) !important;
    color: var(--sidebar-active-text) !important;
    border-left: 4px solid rgba(255,255,255,0.5);
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: var(--sidebar-hover-bg) !important;
    color: white !important;
}
.brand-link.bg-gradient-primary {
    background: var(--sidebar-brand-bg) !important;
}
.nav-header {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 1rem;
}

/* Custom scrollbar for better appearance */
.sidebar::-webkit-scrollbar {
    width: 5px;
}
.sidebar::-webkit-scrollbar-track {
    background: var(--sidebar-bg);
}
.sidebar::-webkit-scrollbar-thumb {
    background: #4aad7a;
    border-radius: 5px;
}
.sidebar::-webkit-scrollbar-thumb:hover {
    background: #24e78f;
}

/* Collapsible sections */
.nav-toggle { cursor: pointer; user-select: none; padding-right: 1rem; }
.nav-toggle:hover { color: #fff !important; }
.nav-toggle .nav-chevron { transition: transform .2s; font-size: .7rem; margin-top: .25rem; }
.nav-toggle.collapsed .nav-chevron { transform: rotate(-90deg); }
.nav-sec.sec-hidden { display: none; }
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link { padding: 0.6rem 1rem; }
</style>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard.php" class="brand-link bg-gradient-primary">
      <img src="../dist/img/employees/2020-nia-logo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><b>NIA-ACIMO</b> </span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar" style="background-color: var(--sidebar-bg) !important;">

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">

          <li class="nav-header nav-toggle text-light border-bottom pb-2" data-sec="emp" role="button" tabindex="0">EMPLOYEE MENU <i class="fas fa-chevron-down float-right nav-chevron"></i></li>
          <?php if (hasPermission('view_dashboard')): ?>
          <li class="nav-item nav-sec nav-sec-emp">
            <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>
          <?php endif; ?>
          <li class="nav-item nav-sec nav-sec-emp">
            <a href="attachments_monitoring.php" class="nav-link <?= $current_page == 'attachments_monitoring.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-paperclip"></i>
              <p>Attachment Monitoring</p>
            </a>
          </li>
          <?php if (hasPermission('view_calendar')): ?>
          <li class="nav-item nav-sec nav-sec-emp">
            <a href="calendar.php" class="nav-link <?= $current_page == 'calendar.php' ? 'active' : 'text-white' ?>">
              <i class="nav-icon fas fa-calendar-alt"></i>
              <p>Calendar</p>
            </a>
          </li>
          <?php endif; ?>
          <li class="nav-item nav-sec nav-sec-emp">
                  <a href="../views/coe_request.php" class="nav-link <?= $current_page == 'coe_request.php' ? 'active' : 'text-white' ?>">
                      <i class="fas fa-certificate nav-icon"></i>
                      <p>Request a COE</p>
                  </a>
              </li>
          <li class="nav-item nav-sec nav-sec-emp"> 
            <a href="leave_request.php" class="nav-link <?= $current_page == 'leave_request.php' ? 'active' : 'text-white' ?>">
              <i class="fas fa-newspaper nav-icon"></i>
              <p>Leave Request</p>
            </a>
          </li>
          <li class="nav-item nav-sec nav-sec-emp">
              <a href="personal_locator_slip.php" class="nav-link <?= $current_page == 'personal_locator_slip.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-location-arrow nav-icon"></i>
                  <p>Personal Locator Slip</p>
              </a>
          </li>
          <li class="nav-item nav-sec nav-sec-emp">
              <a href="my_ict_equipment.php" class="nav-link <?= $current_page == 'my_ict_equipment.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-laptop nav-icon"></i>
                  <p>My ICT Equipment</p>
              </a>
          </li>
          
          <?php if (hasPermission('manage_employees')): ?>
          <li class="nav-header nav-toggle text-light border-bottom pb-2 mt-3" data-sec="hr" role="button" tabindex="0">HR MANAGEMENT <i class="fas fa-chevron-down float-right nav-chevron"></i></li>
            <li class="nav-item nav-sec nav-sec-hr">
                <a href="../views/applicant.php" class="nav-link <?= $current_page == 'applicant.php' ? 'active' : 'text-white' ?>">
                    <i class="fas fa-clipboard-check nav-icon"></i>
                    <p>Applicant Databank</p>
                </a>
            </li>
              <li class="nav-item nav-sec nav-sec-hr">
                <a href="../views/emp.create.php" class="nav-link <?= $current_page == 'emp.create.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-user-plus nav-icon"></i>
                  <p>Create Employee</p>
                </a>
              </li>
              <li class="nav-item nav-sec nav-sec-hr">
                  <a href="../views/hr_leave_monitoring.php" class="nav-link <?= $current_page == 'hr_leave_monitoring.php' ? 'active' : 'text-white' ?>">
                      <i class="fas fa-clipboard-list nav-icon"></i>
                      <p>HR Leave Monitoring</p>
                  </a>
              </li>
              <li class="nav-item nav-sec nav-sec-hr">
                  <a href="../views/certificate_of_employment.php" class="nav-link <?= $current_page == 'certificate_of_employment.php' ? 'active' : 'text-white' ?>">
                      <i class="fas fa-certificate nav-icon"></i>
                      <p>Employee COE Request</p>
                  </a>
              </li>
              <li class="nav-item nav-sec nav-sec-hr">
                <a href="../views/emp.list.php" class="nav-link <?= $current_page == 'emp.list.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-users nav-icon"></i>
                  <p>Employee List</p>
                </a>
              </li>
              <li class="nav-item nav-sec nav-sec-hr">
                  <a href="../views/certificate_of_appearance.php" class="nav-link <?= $current_page == 'certificate_of_appearance.php' ? 'active' : 'text-white' ?>">
                      <i class="fas fa-file-signature nav-icon"></i>
                      <p>Certificate of Appearance</p>
                  </a>
              </li>
              <li class="nav-item nav-sec nav-sec-hr">
                  <a href="../views/personal_locator_monitoring.php" class="nav-link <?= $current_page == 'personal_locator_monitoring.php' ? 'active' : 'text-white' ?>">
                      <i class="fas fa-clipboard-check nav-icon"></i>
                      <p>Slip Monitoring</p>
                  </a>
              </li>
              <li class="nav-item nav-sec nav-sec-hr">
                  <a href="../views/intern.php" class="nav-link <?= $current_page == 'intern.php' ? 'active' : 'text-white' ?>">
                      <i class="fas fa-clipboard-check nav-icon"></i>
                      <p>Intern Databank</p>
                  </a>
              </li>
              <li class="nav-item nav-sec nav-sec-hr">
                <a href="../views/room_reservation.php" class="nav-link <?= $current_page == 'room_reservation.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-bed nav-icon"></i>
                  <p>Room Reservation</p>
                </a>
              </li>
          <?php endif; ?>
          
          <?php if (hasPermission('manage_settings')): ?>
          <li class="nav-header nav-toggle text-light border-bottom pb-2 mt-3" data-sec="settings" role="button" tabindex="0">SETTINGS <i class="fas fa-chevron-down float-right nav-chevron"></i></li>
          
          <li class="nav-item nav-sec nav-sec-settings">
              <a href="maintenance_page.php" class="nav-link <?= $current_page == 'maintenance_page.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-tools nav-icon"></i>
                  <p>Module Maintenance</p>
              </a>
          </li>
          <li class="nav-item nav-sec nav-sec-settings">
              <a href="content_management.php" class="nav-link <?= $current_page == 'content_management.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-tv nav-icon"></i>
                  <p>Content Management</p>
              </a>
          </li>
          <li class="nav-item nav-sec nav-sec-settings">
              <a href="announcements.php" class="nav-link <?= $current_page == 'announcements.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-bullhorn nav-icon"></i>
                  <p>Announcements</p>
              </a>
          </li>
          <li class="nav-item nav-sec nav-sec-settings">
              <a href="types_leaves.php" class="nav-link <?= $current_page == 'types_leaves.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-arrow-circle-right nav-icon"></i>
                  <p>Leave Types</p>
              </a>
          </li>
          <li class="nav-item nav-sec nav-sec-settings">
              <a href="appointment_status.php" class="nav-link <?= $current_page == 'appointment_status.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-briefcase nav-icon"></i>
                  <p>Appointment Settings</p>
              </a>
          </li>
          <li class="nav-item nav-sec nav-sec-settings">
            <a href="position.php" class="nav-link <?= $current_page == 'position.php' ? 'active' : 'text-white' ?>">
                <i class="fas fa-id-card-alt nav-icon"></i>
                <p>Positions</p>
            </a>
          </li>
          <li class="nav-item nav-sec nav-sec-settings">
              <a href="sections.php" class="nav-link <?= $current_page == 'sections.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-sitemap nav-icon"></i>
                  <p>Sections</p>
              </a>
          </li>
          <li class="nav-item nav-sec nav-sec-settings">
              <a href="offices.php" class="nav-link <?= $current_page == 'offices.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-building nav-icon"></i>
                  <p>Offices</p>
              </a>
          </li>
          <li class="nav-item nav-sec nav-sec-settings">
              <a href="employment_status.php" class="nav-link <?= $current_page == 'employment_status.php' ? 'active' : 'text-white' ?>">
                  <i class="fas fa-user-check nav-icon"></i>
                  <p>Employment Status</p>
              </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_users')): ?>
          <li class="nav-header nav-toggle text-light border-bottom pb-2 mt-3" data-sec="users" role="button" tabindex="0">USER MANAGEMENT <i class="fas fa-chevron-down float-right nav-chevron"></i></li>
          <li class="nav-item nav-sec nav-sec-users">
              <a href="users.php" class="nav-link <?= $current_page == 'users.php' ? 'active' : 'text-white' ?>">
                  <i class="nav-icon fas fa-user-cog"></i>
                  <p>Users</p>
              </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_roles')): ?>
          <li class="nav-item nav-sec nav-sec-users">
              <a href="roles.php" class="nav-link <?= $current_page == 'roles.php' ? 'active' : 'text-white' ?>">
                  <i class="nav-icon fas fa-user-shield"></i>
                  <p>Roles</p>
              </a>
          </li>
          <?php endif; ?>

          <?php if (hasPermission('manage_permissions')): ?>
          <li class="nav-item nav-sec nav-sec-users">
              <a href="permissions.php" class="nav-link <?= $current_page == 'permissions.php' ? 'active' : 'text-white' ?>">
                  <i class="nav-icon fas fa-key"></i>
                  <p>Permissions</p>
              </a>
          </li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
</aside>
<script>
(function () {
    var sb = document.querySelector('.main-sidebar .sidebar');
    if (!sb) return;
    var KEY_SCROLL = 'sidebarScrollTop', KEY_SEC = 'sidebarCollapsed';
    var collapsed = {};
    try { collapsed = JSON.parse(localStorage.getItem(KEY_SEC) || '{}'); } catch (e) {}

    function apply(sec) {
        var hide = !!collapsed[sec];
        var h = sb.querySelector('.nav-toggle[data-sec="' + sec + '"]');
        if (h) h.classList.toggle('collapsed', hide);
        sb.querySelectorAll('.nav-sec-' + sec).forEach(function (li) { li.classList.toggle('sec-hidden', hide); });
    }
    function save() { try { localStorage.setItem(KEY_SEC, JSON.stringify(collapsed)); } catch (e) {} }

    var toggles = sb.querySelectorAll('.nav-toggle');

    // Find which section holds the current page
    var activeSec = null;
    toggles.forEach(function (h) {
        var sec = h.getAttribute('data-sec');
        if (sb.querySelector('.nav-sec-' + sec + ' .nav-link.active')) activeSec = sec;
    });

    toggles.forEach(function (h) {
        var sec = h.getAttribute('data-sec');
        // Only the section containing the current page is open; all others start collapsed
        if (activeSec !== null) collapsed[sec] = (sec !== activeSec);
        apply(sec);
        function toggle() { collapsed[sec] = !collapsed[sec]; apply(sec); save(); }
        h.addEventListener('click', toggle);
        h.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
    });

    // Restore scroll position (runs before paint, so no jump to top)
    var saved = null;
    try { saved = sessionStorage.getItem(KEY_SCROLL); } catch (e) {}
    if (saved !== null) sb.scrollTop = parseInt(saved, 10) || 0;

    // Make sure the active link is visible
    var active = sb.querySelector('.nav-link.active');
    if (active) {
        var r = active.getBoundingClientRect(), b = sb.getBoundingClientRect();
        if (r.top < b.top || r.bottom > b.bottom) {
            sb.scrollTop += (r.top - b.top) - (b.height / 2) + (r.height / 2);
        }
    }

    function remember() { try { sessionStorage.setItem(KEY_SCROLL, sb.scrollTop); } catch (e) {} }
    sb.addEventListener('scroll', remember, { passive: true });
    sb.addEventListener('click', function (e) { if (e.target.closest('a')) remember(); });
    window.addEventListener('beforeunload', remember);
})();
</script>
<style>
.sidebar-dark-primary {
    background-color: var(--sidebar-bg) !important;
}

.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link {
    color: var(--sidebar-text) !important;
    border-radius: 0;
    margin: 0;
    padding: 0.75rem 1rem;
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
    background-color: var(--sidebar-active-bg) !important;
    color: var(--sidebar-active-text) !important;
    border-left: 4px solid rgba(255,255,255,0.5);
}
.sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: var(--sidebar-hover-bg) !important;
    color: white !important;
}
.brand-link.bg-gradient-primary {
    background: var(--sidebar-brand-bg) !important;
}
.nav-header {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 1rem;
}
</style>
<script>
$(document).ready(function() {
    // Force set admin theme
    localStorage.setItem('currentTheme', 'admin');
    // Trigger theme update in mainheader
    if (window.parent && window.parent.setTheme) {
        window.parent.setTheme('admin');
    }
});
</script>