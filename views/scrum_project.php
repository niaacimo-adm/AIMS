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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Projects Monitoring | NIA-ACIMO AIMS</title>
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
            <h1>Projects Monitoring</h1>
          </div>
          <div class="col-sm-6">
            <button class="btn btn-success float-right" id="newProjectBtn">
              <i class="fas fa-plus mr-1"></i> New Project
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">All Projects</h3>
            <div class="card-tools">
              <div class="input-group input-group-sm" style="width: 200px;">
                <input type="text" class="form-control" id="projectSearch" placeholder="Search projects...">
                <div class="input-group-append">
                  <button class="btn btn-primary">
                    <i class="fas fa-search"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered table-hover" id="projectsTable">
                <thead>
                  <tr>
                    <th>Project Code</th>
                    <th>Project Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Tasks</th>
                    <th>Members</th>
                    <th>Progress</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="projectsTableBody">
                  <!-- Projects will be loaded here -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- New Project Modal -->
<div class="modal fade" id="newProjectModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create New Project</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="projectForm">
          <div class="form-group">
            <label>Project Name *</label>
            <input type="text" class="form-control" id="projectName" required>
          </div>
          <div class="form-group">
            <label>Project Code *</label>
            <input type="text" class="form-control" id="projectCode" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" id="projectDescription" rows="3"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Start Date</label>
                <input type="date" class="form-control" id="projectStartDate">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>End Date</label>
                <input type="date" class="form-control" id="projectEndDate">
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveProjectBtn">Create Project</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    localStorage.setItem('currentTheme', 'scrum');
    loadProjects();
    
    $('#newProjectBtn').click(() => $('#newProjectModal').modal('show'));
    $('#saveProjectBtn').click(createProject);
    $('#projectSearch').on('input', filterProjects);
});

function loadProjects() {
    $.post('../includes/project_ajax.php', {
        action: 'get_projects_monitoring'
    }, function(response) {
        if (response.success) {
            renderProjectsTable(response.projects);
        }
    }, 'json');
}

function renderProjectsTable(projects) {
    const tbody = $('#projectsTableBody');
    tbody.empty();
    
    if (projects.length === 0) {
        tbody.html('<tr><td colspan="9" class="text-center py-4 text-muted">No projects found</td></tr>');
        return;
    }
    
    projects.forEach(project => {
        const progress = calculateProgress(project);
        const row = $(`
            <tr>
                <td><strong>${project.project_code}</strong></td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="project-color-badge" style="background-color: ${project.color}"></div>
                        ${project.project_name}
                    </div>
                </td>
                <td>${project.start_date || 'Not set'}</td>
                <td>${project.end_date || 'Not set'}</td>
                <td>
                    <span class="badge badge-${getStatusBadgeClass(project.status)}">
                        ${project.status.replace('_', ' ').toUpperCase()}
                    </span>
                </td>
                <td>${project.task_count || 0}</td>
                <td>${project.member_count || 0}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: ${progress}%">
                            ${progress}%
                        </div>
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-info view-project" data-project-id="${project.project_id}">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn btn-sm btn-warning edit-project" data-project-id="${project.project_id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-primary select-project" data-project-id="${project.project_id}">
                        <i class="fas fa-play"></i>
                    </button>
                </td>
            </tr>
        `);
        
        tbody.append(row);
    });
    
    // Add event listeners
    $('.view-project').click(function() {
        const projectId = $(this).data('project-id');
        // Redirect to scrumboard with project ID
        window.location.href = `scrum.php?project_id=${projectId}`;
    });
    
    $('.select-project').click(function() {
        const projectId = $(this).data('project-id');
        window.location.href = `scrum.php?project_id=${projectId}`;
    });
    
    $('.edit-project').click(function() {
        const projectId = $(this).data('project-id');
        // Optional: Implement edit functionality
        editProject(projectId);
    });
}

function calculateProgress(project) {
    if (!project.task_count) return 0;
    const completed = project.completed_tasks || 0;
    return Math.round((completed / project.task_count) * 100);
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

function createProject() {
    const formData = {
        project_name: $('#projectName').val(),
        project_code: $('#projectCode').val(),
        project_description: $('#projectDescription').val(),
        start_date: $('#projectStartDate').val(),
        end_date: $('#projectEndDate').val()
    };
    
    if (!formData.project_name || !formData.project_code) {
        alert('Please fill in all required fields');
        return;
    }
    
    $.post('../includes/project_ajax.php', {
        action: 'create_project',
        ...formData
    }, function(response) {
        if (response.success) {
            $('#newProjectModal').modal('hide');
            $('#projectForm')[0].reset();
            loadProjects();
            Swal.fire('Success', 'Project created successfully', 'success');
        } else {
            Swal.fire('Error', response.error || 'Failed to create project', 'error');
        }
    }, 'json');
}

function filterProjects() {
    const searchTerm = $('#projectSearch').val().toLowerCase();
    $('#projectsTable tbody tr').each(function() {
        const text = $(this).text().toLowerCase();
        $(this).toggle(text.includes(searchTerm));
    });
}

function viewProjectDetails(projectId) {
    // Implementation for viewing project details
    window.location.href = `project_details.php?project_id=${projectId}`;
}
</script>
</body>
</html>