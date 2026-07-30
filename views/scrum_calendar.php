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
  <title>Calendar | NIA-ACIMO AIMS</title>
  <?php include '../includes/header.php'; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
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
            <h1>Calendar</h1>
          </div>
          <div class="col-sm-6">
            <div class="float-right">
              <select class="form-control form-control-sm" id="projectFilter" style="width: 200px; display: inline-block;">
                <option value="">All Projects</option>
                <!-- Projects will be loaded here -->
              </select>
              <button class="btn btn-primary btn-sm ml-2" id="toggleView">
                <i class="fas fa-sync-alt mr-1"></i> Switch View
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div id="calendar"></div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

<script>
$(document).ready(function() {
    localStorage.setItem('currentTheme', 'scrum');
    initializeCalendar();
    loadProjectFilter();
    
    $('#projectFilter').change(refreshCalendar);
    $('#toggleView').click(toggleCalendarView);
});

let calendar;
let currentView = 'dayGridMonth';

function initializeCalendar() {
    const calendarEl = document.getElementById('calendar');
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: currentView,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            loadCalendarEvents(fetchInfo, successCallback);
        },
        eventClick: function(info) {
            viewEventDetails(info.event);
        },
        dateClick: function(info) {
            createNewTask(info.dateStr);
        },
        eventRender: function(info) {
            // Custom event rendering
            if (info.event.extendedProps.overdue) {
                info.el.style.backgroundColor = '#dc3545';
                info.el.style.borderColor = '#dc3545';
            }
        }
    });
    
    calendar.render();
}

function loadCalendarEvents(fetchInfo, successCallback) {
    const projectId = $('#projectFilter').val();
    
    $.post('../includes/task_ajax.php', {
        action: 'get_calendar_events',
        start_date: fetchInfo.startStr,
        end_date: fetchInfo.endStr,
        project_id: projectId
    }, function(response) {
        if (response.success) {
            const events = response.events.map(event => ({
                id: event.task_id,
                title: event.title,
                start: event.due_date,
                end: event.due_date,
                extendedProps: {
                    project: event.project_name,
                    priority: event.priority,
                    status: event.status,
                    overdue: event.overdue,
                    description: event.description
                },
                color: getEventColor(event)
            }));
            successCallback(events);
        }
    }, 'json');
}

function getEventColor(event) {
    if (event.overdue) return '#dc3545';
    
    const colors = {
        'urgent': '#dc2626',
        'high': '#ea580c',
        'medium': '#ca8a04',
        'low': '#16a34a'
    };
    
    return colors[event.priority] || '#3788d8';
}

function loadProjectFilter() {
    $.post('../includes/project_ajax.php', {
        action: 'get_my_projects'
    }, function(response) {
        if (response.success) {
            const filter = $('#projectFilter');
            response.projects.forEach(project => {
                filter.append(`<option value="${project.project_id}">${project.project_name}</option>`);
            });
        }
    }, 'json');
}

function refreshCalendar() {
    calendar.refetchEvents();
}

function toggleCalendarView() {
    const views = ['dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listMonth'];
    const currentIndex = views.indexOf(currentView);
    const nextIndex = (currentIndex + 1) % views.length;
    currentView = views[nextIndex];
    
    calendar.changeView(currentView);
    $('#toggleView').html(`<i class="fas fa-sync-alt mr-1"></i> ${getViewName(currentView)}`);
}

function getViewName(view) {
    const names = {
        'dayGridMonth': 'Month View',
        'timeGridWeek': 'Week View',
        'timeGridDay': 'Day View',
        'listMonth': 'List View'
    };
    return names[view] || 'Switch View';
}

function viewEventDetails(event) {
    const task = event.extendedProps;
    
    Swal.fire({
        title: event.title,
        html: `
            <div class="text-left">
                <p><strong>Project:</strong> ${task.project}</p>
                <p><strong>Due Date:</strong> ${event.start.toLocaleDateString()}</p>
                <p><strong>Status:</strong> <span class="badge badge-${getTaskStatusBadgeClass(task.status)}">${task.status.toUpperCase()}</span></p>
                <p><strong>Priority:</strong> <span class="badge priority-${task.priority}">${task.priority.toUpperCase()}</span></p>
                ${task.description ? `<p><strong>Description:</strong> ${task.description}</p>` : ''}
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'View Task',
        cancelButtonText: 'Close'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `scrum.php?task_id=${event.id}`;
        }
    });
}

function createNewTask(date) {
    Swal.fire({
        title: 'Create New Task',
        html: `
            <input type="text" id="taskTitle" class="swal2-input" placeholder="Task Title">
            <textarea id="taskDescription" class="swal2-textarea" placeholder="Description"></textarea>
            <select id="taskPriority" class="swal2-select">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
            <input type="date" id="taskDueDate" class="swal2-input" value="${date}">
        `,
        showCancelButton: true,
        confirmButtonText: 'Create Task',
        preConfirm: () => {
            const title = $('#taskTitle').val();
            const dueDate = $('#taskDueDate').val();
            if (!title) {
                Swal.showValidationMessage('Please enter task title');
                return false;
            }
            return {
                title: title,
                description: $('#taskDescription').val(),
                priority: $('#taskPriority').val(),
                due_date: dueDate
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            createTask(result.value);
        }
    });
}

function createTask(taskData) {
    $.post('../includes/task_ajax.php', {
        action: 'create_task',
        title: taskData.title,
        description: taskData.description,
        priority: taskData.priority,
        due_date: taskData.due_date,
        status: 'todo'
    }, function(response) {
        if (response.success) {
            refreshCalendar();
            Swal.fire('Success', 'Task created successfully', 'success');
        } else {
            Swal.fire('Error', response.error || 'Failed to create task', 'error');
        }
    }, 'json');
}

function getTaskStatusBadgeClass(status) {
    const classes = {
        'backlog': 'secondary',
        'todo': 'warning',
        'inprogress': 'info',
        'review': 'primary',
        'done': 'success'
    };
    return classes[status] || 'secondary';
}
</script>

<style>
#calendar {
    background: var(--card-bg);
    color: var(--text-primary);
    border-radius: 8px;
    padding: 1rem;
}
.fc-toolbar {
    padding: 1rem;
    margin-bottom: 0;
}
.fc-header-toolbar {
    margin-bottom: 1.5em !important;
}
.fc-theme-standard td, .fc-theme-standard th { border-color: var(--table-border) !important; }
.fc-col-header-cell-cushion, .fc-daygrid-day-number { color: var(--text-primary) !important; }
.fc-toolbar-title { color: var(--text-primary) !important; }
.fc-daygrid-day { background: var(--card-bg) !important; }
.fc-day-today { background: var(--table-stripe) !important; }
</style>
</body>
</html>