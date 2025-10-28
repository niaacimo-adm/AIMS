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
  <title>My Projects | NIA-ACIMO AIMS</title>
  <?php include '../includes/header.php'; ?>
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
            <h1>My Projects</h1>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="row" id="myProjectsContainer">
          <!-- My projects will be loaded here -->
        </div>
      </div>
    </section>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    localStorage.setItem('currentTheme', 'scrum');
    loadMyProjects();
});

function loadMyProjects() {
    $.post('../includes/project_ajax.php', {
        action: 'get_my_projects'
    }, function(response) {
        if (response.success) {
            renderMyProjects(response.projects);
        }
    }, 'json');
}

function renderMyProjects(projects) {
    const container = $('#myProjectsContainer');
    container.empty();
    
    if (projects.length === 0) {
        container.html(`
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <h4>No Projects Found</h4>
                    <p>You are not assigned to any projects yet.</p>
                    <button class="btn btn-primary" onclick="window.location.href='scrum_project.php'">
                        Browse All Projects
                    </button>
                </div>
            </div>
        `);
        return;
    }
    
    projects.forEach(project => {
        const progress = calculateProjectProgress(project);
        const projectCard = $(`
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card project-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">${project.project_name}</h5>
                        <span class="badge badge-${getStatusBadgeClass(project.status)}">
                            ${project.status.replace('_', ' ').toUpperCase()}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="project-info mb-3">
                            <p class="text-muted mb-1"><strong>Code:</strong> ${project.project_code}</p>
                            <p class="text-muted mb-1"><strong>Dates:</strong> ${project.start_date || 'Not set'} to ${project.end_date || 'Not set'}</p>
                            <p class="text-muted mb-2"><strong>My Role:</strong> ${project.role || 'Member'}</p>
                        </div>
                        
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: ${progress}%">
                                ${progress}%
                            </div>
                        </div>
                        
                        <div class="project-stats">
                            <div class="row text-center">
                                <div class="col-4">
                                    <small class="text-muted">Total Tasks</small>
                                    <div class="h6 mb-0">${project.total_tasks || 0}</div>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Completed</small>
                                    <div class="h6 mb-0 text-success">${project.completed_tasks || 0}</div>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">My Tasks</small>
                                    <div class="h6 mb-0 text-primary">${project.my_tasks || 0}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-sm btn-primary open-project" data-project-id="${project.project_id}">
                            <i class="fas fa-external-link-alt mr-1"></i> Open Project
                        </button>
                        <button class="btn btn-sm btn-info view-tasks" data-project-id="${project.project_id}">
                            <i class="fas fa-tasks mr-1"></i> My Tasks
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        container.append(projectCard);
    });
    
    // Add event listeners
    $('.open-project').click(function() {
        const projectId = $(this).data('project-id');
        window.location.href = `scrum.php?project_id=${projectId}`;
    });
    
    $('.view-tasks').click(function() {
        const projectId = $(this).data('project-id');
        window.location.href = `my_scrum_task.php?project_id=${projectId}`;
    });
}

function calculateProjectProgress(project) {
    if (!project.total_tasks) return 0;
    const completed = project.completed_tasks || 0;
    return Math.round((completed / project.total_tasks) * 100);
}

function getStatusBadgeClass(status) {
    const classes = {
        'active': 'success',
        'completed': 'primary',
        'on_hold': 'warning',
        'cancelled': 'danger'
    };
    return classes[status] || 'secondary';
}
</script>

<style>
.project-card {
    transition: transform 0.2s;
    border-left: 4px solid #007bff;
}
.project-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.project-card .card-header {
    background: white;
    border-bottom: 1px solid #e3e6f0;
}
</style>
</body>
</html>