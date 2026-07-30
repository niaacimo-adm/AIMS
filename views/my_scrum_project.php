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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <?php include '../includes/mainheader.php'; ?>
  <?php include '../includes/sidebar_scrum.php'; ?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1><i class="fas fa-folder-open mr-2" style="color:var(--s-teal)"></i>My Projects</h1>
          </div>
          <div class="col-sm-6">
            <div class="float-right d-flex align-items-center">
              <span class="grid-count-badge" id="projectGridCountBadge">0 projects</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="grid-search-bar">
          <input type="text" id="projectGridSearch" class="grid-search-input" placeholder="Search projects by name, code…">
        </div>
        <div class="project-grid" id="myProjectsContainer">
          <!-- My projects will be loaded here -->
        </div>
      </div>
    </section>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
let allProjects = [];

$(document).ready(function() {
    localStorage.setItem('currentTheme', 'scrum');
    loadMyProjects();

    $('#projectGridSearch').on('input', function() {
        renderMyProjects(filterProjects(allProjects));
    });
});

function filterProjects(projects) {
    const term = ($('#projectGridSearch').val() || '').toLowerCase();
    if (!term) return projects;
    return projects.filter(p =>
        `${p.project_name} ${p.project_code}`.toLowerCase().includes(term)
    );
}

function loadMyProjects() {
    $.post('../includes/project_ajax.php', {
        action: 'get_my_projects'
    }, function(response) {
        if (response.success) {
            allProjects = response.projects;
            renderMyProjects(filterProjects(allProjects));
        }
    }, 'json');
}

function renderMyProjects(projects) {
    const container = $('#myProjectsContainer');
    container.empty();
    $('#projectGridCountBadge').text(`${projects.length} project${projects.length !== 1 ? 's' : ''}`);

    if (allProjects.length === 0) {
        container.html(`
            <div class="no-results-card">
                <i class="fas fa-folder-open fa-2x mb-2"></i>
                <p><strong>No Projects Found</strong><br>You are not assigned to any projects yet.</p>
                <button class="btn btn-primary" onclick="window.location.href='scrum_project.php'">
                    Browse All Projects
                </button>
            </div>
        `);
        return;
    }

    if (projects.length === 0) {
        container.html(`
            <div class="no-results-card">
                <i class="fas fa-search fa-2x mb-2"></i>
                <p>No projects match your search</p>
            </div>
        `);
        return;
    }

    projects.forEach(project => {
        const progress = calculateProjectProgress(project);
        const initials = (project.project_name || '?').substring(0, 2).toUpperCase();
        const color = project.color || 'var(--s-teal)';
        const startDate = project.start_date ? new Date(project.start_date).toLocaleDateString() : 'Not set';
        const endDate = project.end_date ? new Date(project.end_date).toLocaleDateString() : 'Not set';

        const projectCard = $(`
            <div class="project-grid-card">
                <div class="project-grid-card-accent" style="background: ${color}"></div>
                <div class="project-grid-card-top">
                    <div class="project-grid-card-icon" style="background: ${color}22; color: ${color}">
                        ${initials}
                    </div>
                    <span class="project-grid-card-status badge-${getStatusBadgeClass(project.status)}">
                        ${(project.status || 'unknown').replace('_', ' ').toUpperCase()}
                    </span>
                </div>

                <div class="project-grid-card-body">
                    <h5 class="project-grid-card-title">${escapeHtml(project.project_name)}</h5>
                    <p class="project-grid-card-subtitle">
                        <i class="fas fa-hashtag"></i> ${escapeHtml(project.project_code)}
                    </p>

                    <div class="project-grid-card-meta">
                        <div class="project-grid-card-meta-row">
                            <i class="fas fa-calendar-alt"></i>
                            <span>${startDate} &rarr; ${endDate}</span>
                        </div>
                        <div class="project-grid-card-meta-row">
                            <i class="fas fa-user-tag"></i>
                            <span>${escapeHtml(project.role || 'Member')}</span>
                        </div>
                    </div>

                    <div class="project-grid-card-progress">
                        <div class="project-grid-card-progress-header">
                            <span>Progress</span>
                            <span>${progress}%</span>
                        </div>
                        <div class="project-progress-track">
                            <div class="project-progress-fill" style="width: ${progress}%"></div>
                        </div>
                    </div>

                    <div class="project-grid-card-stats">
                        <div class="project-grid-card-stat">
                            <div class="project-grid-card-stat-value">${project.total_tasks || 0}</div>
                            <div class="project-grid-card-stat-label">Total</div>
                        </div>
                        <div class="project-grid-card-stat">
                            <div class="project-grid-card-stat-value stat-success">${project.completed_tasks || 0}</div>
                            <div class="project-grid-card-stat-label">Done</div>
                        </div>
                        <div class="project-grid-card-stat">
                            <div class="project-grid-card-stat-value stat-primary">${project.my_tasks || 0}</div>
                            <div class="project-grid-card-stat-label">Mine</div>
                        </div>
                    </div>
                </div>

                <div class="project-grid-card-actions">
                    <button type="button" class="project-grid-card-action-btn go open-project" data-project-id="${project.project_id}" title="Open Project">
                        <i class="fas fa-external-link-alt"></i><span>Open</span>
                    </button>
                    <button type="button" class="project-grid-card-action-btn view view-tasks" data-project-id="${project.project_id}" title="My Tasks">
                        <i class="fas fa-tasks"></i><span>My Tasks</span>
                    </button>
                    <button type="button" class="project-grid-card-action-btn activity view-activity" data-project-id="${project.project_id}" data-project-name="${escapeHtml(project.project_name)}" title="Activity Log">
                        <i class="fas fa-history"></i><span>Activity</span>
                    </button>
                </div>
            </div>
        `);

        container.append(projectCard);
    });

    $('.open-project').click(function() {
        const projectId = $(this).data('project-id');
        window.location.href = `scrum.php?project_id=${projectId}`;
    });

    $('.view-tasks').click(function() {
        const projectId = $(this).data('project-id');
        window.location.href = `my_scrum_task.php?project_id=${projectId}`;
    });

    $('.view-activity').click(function() {
        const projectId = $(this).data('project-id');
        const projectName = $(this).data('project-name');
        viewProjectActivity(projectId, projectName);
    });
}

function viewProjectActivity(projectId, projectName) {
    Swal.fire({
        title: projectName || 'Project Activity',
        html: `<div id="projectActivityLog" class="task-activity-log">
                   <div class="text-muted small">Loading activity&hellip;</div>
               </div>`,
        icon: 'info',
        confirmButtonText: 'Close',
        width: '600px',
        didOpen: () => loadProjectActivity(projectId)
    });
}

function loadProjectActivity(projectId) {
    $.post('../includes/project_ajax.php', {
        action: 'get_project_activity',
        project_id: projectId
    }, function(response) {
        const container = $('#projectActivityLog');
        if (!container.length) return; // modal already closed

        if (!response.success || !response.activity || response.activity.length === 0) {
            container.html('<div class="text-muted small">No activity yet.</div>');
            return;
        }

        const items = response.activity.map(entry => {
            const who = entry.first_name ? `${entry.first_name} ${entry.last_name}` : 'Someone';
            const when = new Date(entry.created_at).toLocaleString();
            const initial = (entry.first_name || '?').charAt(0).toUpperCase();
            const scopeTag = entry.entity_type === 'task'
                ? '<span class="activity-scope-tag">task</span>'
                : '<span class="activity-scope-tag project">project</span>';
            return `
                <div class="activity-item">
                    <div class="activity-avatar">${initial}</div>
                    <div class="activity-content">
                        <div class="activity-desc"><strong>${escapeHtml(who)}</strong> ${escapeHtml(entry.description)} ${scopeTag}</div>
                        <div class="activity-meta">${when}</div>
                    </div>
                </div>
            `;
        }).join('');

        container.html(items);
    }, 'json').fail(function() {
        $('#projectActivityLog').html('<div class="text-muted small">Couldn\'t load activity.</div>');
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

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
  /* Aliased to mainheader.php's site-wide green theme (light/dark mode aware)
     so this page automatically follows the global light/dark toggle. */
  --s-bg:         var(--body-bg);
  --s-surface:    var(--card-bg);
  --s-surface2:   var(--table-stripe);
  --s-surface3:   var(--notification-unread-bg);
  --s-border:     var(--card-border);
  --s-teal:       var(--green);
  --s-teal-dim:   rgba(36,231,143,.12);
  --s-teal-text:  var(--sidebar-active-text);
  --s-text:       var(--text-primary);
  --s-muted:      var(--text-muted);
  --s-danger:     #f85149;
  --s-warning:    #d29922;
  --s-green:      #3fb950;
  --s-blue:       #58a6ff;
  --s-violet:     #a78bfa;
  --s-shadow:     0 8px 32px rgba(15,45,30,.12);
  --s-font:       'Plus Jakarta Sans', sans-serif;
}

/* A custom property that references var(--card-bg) etc. only re-substitutes
   at the point it's declared — declaring it once at :root freezes it to the
   light value forever, even after body.dark-mode redefines --card-bg. Mirror
   mainheader.php's own pattern here so these aliases pick up the dark values. */
body.dark-mode {
  --s-bg:         var(--body-bg);
  --s-surface:    var(--card-bg);
  --s-surface2:   var(--table-stripe);
  --s-surface3:   var(--notification-unread-bg);
  --s-border:     var(--card-border);
  --s-teal-text:  var(--sidebar-active-text);
  --s-text:       var(--text-primary);
  --s-muted:      var(--text-muted);
  --s-shadow:     0 8px 32px rgba(0,0,0,.35);
}

body { font-family: var(--s-font) !important; background: var(--s-bg) !important; color: var(--s-text) !important; }
.content-wrapper { background: var(--s-bg) !important; }

.content-header h1 {
  font-family: var(--s-font) !important;
  font-weight: 800 !important;
  font-size: 1.5rem !important;
  color: var(--s-text) !important;
  letter-spacing: -.5px;
}

/* ── Search bar (matches board-view search on my_scrum_task.php) ── */
.grid-search-bar { display:flex; align-items:center; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
.grid-search-input {
  flex:1; min-width:180px; border-radius:8px !important;
  border:1px solid var(--s-border) !important;
  padding:9px 14px 9px 36px !important;
  font-size:13px !important; font-family: var(--s-font) !important;
  background: var(--s-surface2) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236aad8a' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M13 13l3 3m-5-3a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3e%3c/svg%3e") 10px center no-repeat !important;
  background-size:16px !important;
  color: var(--s-text) !important;
  transition: all .2s;
}
.grid-search-input:focus { outline:none; border-color: var(--s-teal) !important; box-shadow: 0 0 0 3px var(--s-teal-dim) !important; }
.grid-search-input::placeholder { color: var(--s-muted) !important; }
.grid-count-badge { font-size:12px; font-weight:600; color: var(--s-muted) !important; white-space:nowrap; }

.no-results-card { grid-column: 1 / -1; text-align:center; padding:60px 20px; color: var(--s-muted) !important; }
.no-results-card .btn-primary {
  background: var(--s-teal) !important; color: var(--s-teal-text) !important; border: none !important;
  border-radius: 9px !important; font-weight: 700 !important; margin-top: 10px;
}

/* ── Project card grid (mirrors task-grid-card styling from my_scrum_task.php) ── */
.project-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:20px; padding:4px 0 20px; }

.project-grid-card {
  background: var(--s-surface) !important;
  border-radius:16px !important;
  box-shadow: 0 2px 10px rgba(0,0,0,.07);
  overflow:hidden; transition: all .3s ease;
  display:flex; flex-direction:column;
  border:1px solid var(--s-border) !important;
  position:relative;
}
.project-grid-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px var(--s-teal-dim); border-color: var(--s-teal) !important; }
.project-grid-card-accent { height:5px; width:100%; }

.project-grid-card-top { display:flex; align-items:center; justify-content:space-between; padding:18px 18px 0; }
.project-grid-card-icon {
  width:48px; height:48px; border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  font-size:16px; font-weight:700; text-transform:uppercase;
  flex-shrink: 0;
}
.project-grid-card-status {
  font-size:10px; font-weight:700; padding:4px 10px; border-radius:20px;
  letter-spacing:.3px; text-transform:uppercase; white-space: nowrap;
}

.project-grid-card-body { padding:14px 18px 16px; flex:1; display:flex; flex-direction:column; }
.project-grid-card-title { font-size:15px; font-weight:700; color: var(--s-text) !important; margin:0 0 3px; line-height:1.3; font-family: var(--s-font) !important; }
.project-grid-card-subtitle { font-size:12px; color: var(--s-muted) !important; margin-bottom:12px; font-weight:500; }
.project-grid-card-subtitle i { color: var(--s-teal) !important; margin-right: 3px; }

.project-grid-card-meta { display:flex; flex-direction:column; gap:6px; margin-bottom: 14px; }
.project-grid-card-meta-row { display:flex; align-items:center; gap:7px; font-size:11.5px; color: var(--s-muted) !important; }
.project-grid-card-meta-row i { width:14px; text-align:center; color: var(--s-teal) !important; flex-shrink:0; }
.project-grid-card-meta-row span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.project-grid-card-progress { margin-bottom: 14px; }
.project-grid-card-progress-header { display:flex; justify-content:space-between; font-size:11px; font-weight:600; color: var(--s-muted) !important; margin-bottom:5px; }
.project-progress-track { height:8px; border-radius:6px; background: var(--s-surface3) !important; overflow:hidden; }
.project-progress-fill { height:100%; border-radius:6px; background: var(--s-teal) !important; transition: width .4s ease; }

.project-grid-card-stats {
  display:flex; border-top:1px solid var(--s-border) !important; padding-top:12px; margin-top:auto;
}
.project-grid-card-stat { flex:1; text-align:center; }
.project-grid-card-stat-value { font-size:16px; font-weight:800; color: var(--s-text) !important; font-family: var(--s-font) !important; }
.project-grid-card-stat-value.stat-success { color: var(--s-green) !important; }
.project-grid-card-stat-value.stat-primary { color: var(--s-blue) !important; }
.project-grid-card-stat-label { font-size:10px; color: var(--s-muted) !important; text-transform:uppercase; letter-spacing:.4px; font-weight:600; }

.project-grid-card-actions { display:flex; border-top:1px solid var(--s-border) !important; overflow:hidden; }
.project-grid-card-action-btn {
  flex:1; border:none; background:none; padding:11px 6px; font-size:13px; cursor:pointer;
  transition: all .2s; display:flex; align-items:center; justify-content:center; gap:6px; font-weight:600;
  color: var(--s-muted) !important; font-family: var(--s-font) !important;
}
.project-grid-card-action-btn:not(:last-child) { border-right:1px solid var(--s-border) !important; }
.project-grid-card-action-btn.go       { color: var(--s-teal) !important; }
.project-grid-card-action-btn.view     { color: var(--s-blue) !important; }
.project-grid-card-action-btn.activity { color: var(--s-violet) !important; }
.project-grid-card-action-btn:hover { background: var(--s-surface2) !important; }

/* ── Activity log popup (project + task, same look as my_scrum_task.php) ── */
.task-activity-log { max-height: 320px; overflow-y: auto; text-align: left; }
.task-activity-log .activity-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 8px 0; border-bottom: 1px solid var(--s-border) !important;
}
.task-activity-log .activity-item:last-child { border-bottom: none !important; }
.task-activity-log .activity-avatar {
  width: 26px; height: 26px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: var(--s-surface3) !important; color: var(--s-teal) !important;
  border: 1px solid var(--s-border) !important;
  font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.task-activity-log .activity-content { flex: 1; }
.task-activity-log .activity-desc { font-size: 13px; color: var(--s-text) !important; }
.task-activity-log .activity-meta { font-size: 11px; color: var(--s-muted) !important; margin-top: 2px; }
.activity-scope-tag {
  display: inline-block; font-size: 9px; font-weight: 700; text-transform: uppercase;
  padding: 1px 6px; border-radius: 8px; margin-left: 4px;
  background: var(--s-teal-dim) !important; color: var(--s-teal) !important;
}
.activity-scope-tag.project { background: rgba(167,139,250,.15) !important; color: var(--s-violet) !important; }

/* ── Status badge colors (reused across cards) ── */
.badge-secondary { background: var(--s-surface3) !important; color: var(--s-muted) !important; }
.badge-warning   { background: rgba(210,153,34,.18) !important; color: #e3a520 !important; }
.badge-info      { background: rgba(88,166,255,.15) !important; color: var(--s-blue) !important; }
.badge-primary   { background: rgba(167,139,250,.15) !important; color: var(--s-violet) !important; }
.badge-success   { background: rgba(63,185,80,.15) !important; color: var(--s-green) !important; }
.badge-danger    { background: rgba(248,81,73,.15) !important; color: var(--s-danger) !important; }

/* Scrollbars (mirrors board-view page) */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--s-surface); }
::-webkit-scrollbar-thumb { background: var(--s-surface3); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--s-muted); }
</style>
</body>
</html>