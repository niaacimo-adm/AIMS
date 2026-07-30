<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/projects.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize project manager
$projectManager = new ProjectManager();
$user_id = $_SESSION['emp_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Scrum Dashboard | NIA-ACIMO AIMS</title>
  <?php include '../includes/header.php'; ?>
  
  <style>
    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: var(--card-bg) !important;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid var(--card-border);
        border-left: 4px solid var(--green-dark);
    }
    .stat-card h3 {
        font-size: 2rem;
        margin: 0;
        color: var(--text-primary);
    }
    .stat-card p {
        margin: 0.5rem 0 0 0;
        color: var(--text-muted);
    }
    .stat-card.project { border-left-color: var(--green-dark); }
    .stat-card.task { border-left-color: #e74c3c; }
    .stat-card.completed { border-left-color: var(--green); }
    .stat-card.pending { border-left-color: #f39c12; }

    .recent-activities {
        background: var(--card-bg) !important;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid var(--card-border);
        color: var(--text-primary);
    }
    .activity-item {
        padding: 1rem 0;
        border-bottom: 1px solid var(--card-border);
    }
    .activity-item:last-child {
        border-bottom: none;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini theme-scrum">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar_scrum.php'; ?>
  
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Scrum Dashboard</h1>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="dashboard-stats">
          <div class="stat-card project">
            <h3 id="totalProjects">0</h3>
            <p>Total Projects</p>
          </div>
          <div class="stat-card task">
            <h3 id="totalTasks">0</h3>
            <p>Total Tasks</p>
          </div>
          <div class="stat-card completed">
            <h3 id="completedTasks">0</h3>
            <p>Completed Tasks</p>
          </div>
          <div class="stat-card pending">
            <h3 id="pendingTasks">0</h3>
            <p>Pending Tasks</p>
          </div>
        </div>

        <div class="row">
          <div class="col-md-8">
            <div class="recent-activities">
              <h4>Recent Activities</h4>
              <div id="recentActivities">
                <!-- Activities will be loaded here -->
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
              </div>
              <div class="card-body">
                <button class="btn btn-primary btn-block mb-2" onclick="window.location.href='scrum.php'">
                  <i class="fas fa-scroll mr-2"></i>Go to Scrum Board
                </button>
                <button class="btn btn-success btn-block mb-2" onclick="window.location.href='scrum_project.php'">
                  <i class="fas fa-project-diagram mr-2"></i>View All Projects
                </button>
                <button class="btn btn-info btn-block mb-2" onclick="window.location.href='my_scrum_task.php'">
                  <i class="fas fa-tasks mr-2"></i>My Tasks
                </button>
                <button class="btn btn-warning btn-block" onclick="window.location.href='scrum_calendar.php'">
                  <i class="fas fa-calendar-alt mr-2"></i>Calendar View
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>
<?php include '../includes/mainfooter.php'; ?>
<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Set scrum theme
    localStorage.setItem('currentTheme', 'scrum');
    
    // Load dashboard statistics
    loadDashboardStats();
    loadRecentActivities();
});

function loadDashboardStats() {
    $.post('../includes/project_ajax.php', {
        action: 'get_dashboard_stats'
    }, function(response) {
        if (response.success) {
            $('#totalProjects').text(response.stats.total_projects);
            $('#totalTasks').text(response.stats.total_tasks);
            $('#completedTasks').text(response.stats.completed_tasks);
            $('#pendingTasks').text(response.stats.pending_tasks);
        }
    }, 'json');
}

function loadRecentActivities() {
    $.post('../includes/project_ajax.php', {
        action: 'get_recent_activities'
    }, function(response) {
        if (response.success) {
            const activities = $('#recentActivities');
            activities.empty();
            
            if (response.activities.length === 0) {
                activities.html('<p class="text-muted">No recent activities</p>');
                return;
            }
            
            response.activities.forEach(activity => {
                const timeAgo = getTimeAgo(activity.created_at);
                activities.append(`
                    <div class="activity-item">
                        <div class="d-flex justify-content-between">
                            <strong>${activity.description}</strong>
                            <small class="text-muted">${timeAgo}</small>
                        </div>
                        <small class="text-muted">Project: ${activity.project_name}</small>
                    </div>
                `);
            });
        }
    }, 'json');
}

function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} minutes ago`;
    if (diffHours < 24) return `${diffHours} hours ago`;
    return `${diffDays} days ago`;
}
</script>
</body>
</html>