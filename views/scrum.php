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
$canAssignTasks = $projectManager->canAssignTasks($_SESSION['emp_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Scrumboard | NIA-ACIMO AIMS</title>
  
  <?php include '../includes/header.php'; ?>
  
  <!-- Custom Styles for Scrumboard -->
  <style>
    :root {
      --scrum-primary: #8B5CF6;
      --scrum-secondary: #7C3AED;
      --scrum-accent: #A78BFA;
      --scrum-background: #F8FAFC;
      --scrum-card: #FFFFFF;
      --scrum-text: #1E293B;
      --scrum-border: #E2E8F0;
    }

    body {
      background-color: var(--scrum-background) !important;
    }

    .theme-scrumboard .main-header {
      background: linear-gradient(135deg, var(--scrum-primary), var(--scrum-secondary)) !important;
    }

    .theme-scrumboard #mainFooter {
      background: linear-gradient(135deg, var(--scrum-primary), var(--scrum-secondary)) !important;
    }

    /* Main Content Structure */
    .content-wrapper {
      background-color: var(--scrum-background) !important;
    }

    .scrumboard-container {
      display: flex;
      min-height: calc(100vh - 150px);
      background-color: var(--scrum-background);
    }

    .scrum-sidebar {
      width: 280px;
      background: white;
      border-right: 1px solid var(--scrum-border);
      display: flex;
      flex-direction: column;
      transition: all 0.3s ease;
      flex-shrink: 0;
      box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    }

    .scrum-main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-width: 0;
      padding: 0;
    }

    .scrum-header {
      padding: 1rem 1.5rem;
      background: white;
      border-bottom: 1px solid var(--scrum-border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
      margin-bottom: 0;
    }

    .scrum-content {
      flex: 1;
      padding: 1.5rem;
      overflow: auto;
      background-color: var(--scrum-background);
    }

    /* Column Styles */
    .columns-container {
      display: flex;
      gap: 1rem;
      overflow-x: auto;
      padding: 0.5rem;
      height: 100%;
      min-height: 500px;
    }

    .column {
      min-width: 300px;
      background: #F1F5F9;
      border-radius: 8px;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .column-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
    }

    .column-title {
      font-weight: 600;
      color: var(--scrum-text);
      margin: 0;
      font-size: 1rem;
    }

    .task-count {
      background: var(--scrum-primary);
      color: white;
      border-radius: 12px;
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .tasks-container {
      flex: 1;
      overflow-y: auto;
      min-height: 100px;
      background: rgba(255,255,255,0.5);
      border-radius: 6px;
      padding: 0.5rem;
    }

    .task-card {
      background: white;
      border-radius: 8px;
      padding: 0.75rem;
      margin-bottom: 0.75rem;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--scrum-border);
      cursor: grab;
      transition: all 0.2s ease;
      position: relative;
    }

    .task-card:hover {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      transform: translateY(-1px);
    }

    .task-card:active {
      cursor: grabbing;
    }

    .task-card.dragging {
      opacity: 0.6;
      transform: rotate(5deg);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .task-title {
      font-weight: 500;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
      line-height: 1.4;
    }

    .task-meta {
      display: flex;
      justify-content: space-between;
      font-size: 0.75rem;
      color: #64748B;
    }

    .task-labels {
      display: flex;
      gap: 0.25rem;
      margin-bottom: 0.5rem;
      flex-wrap: wrap;
    }

    .task-label {
      padding: 0.125rem 0.5rem;
      border-radius: 12px;
      font-size: 0.7rem;
      font-weight: 500;
    }

    .label-revise { background: #FEF3C7; color: #D97706; }
    .label-urgent { background: #FECACA; color: #DC2626; }
    .label-design { background: #DBEAFE; color: #1D4ED8; }
    .label-development { background: #D1FAE5; color: #047857; }
    .label-review { background: #E0E7FF; color: #3730A3; }

    .task-priority {
      position: absolute;
      top: 8px;
      right: 8px;
      font-size: 0.6rem;
      padding: 0.2rem 0.4rem;
      border-radius: 4px;
      font-weight: bold;
    }

    .priority-urgent { background: #DC2626; color: white; }
    .priority-high { background: #EA580C; color: white; }
    .priority-medium { background: #CA8A04; color: white; }
    .priority-low { background: #16A34A; color: white; }

    /* Projects Monitoring */
    .project-card {
      border: 1px solid #e3e6f0;
      border-radius: 0.35rem;
      margin-bottom: 1rem;
    }

    .project-card-header {
      background: #f8f9fc;
      padding: 0.75rem 1.25rem;
      border-bottom: 1px solid #e3e6f0;
    }

    .project-color-badge {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      display: inline-block;
      margin-right: 8px;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .columns-container {
        flex-direction: column;
        overflow-y: auto;
      }

      .column {
        min-width: auto;
      }

      .scrum-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
      }
    }
    .board-color-badge {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }

    .column-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .column-footer {
        border-top: 1px solid var(--scrum-border);
        padding-top: 0.75rem;
    }

    .empty-column {
        text-align: center;
        padding: 2rem;
        color: #64748B;
    }

    .empty-column i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        opacity: 0.5;
    }

    .empty-column p {
        margin: 0;
        font-size: 0.9rem;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini theme-scrum">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
<?php include '../includes/sidebar_scrum.php'; ?>
  <div class="content-wrapper">
    <!-- Content Wrapper. Contains page content -->
    <div class="scrum-main-content">
      <!-- Scrum Header -->
      <div class="scrum-header">
        <div class="d-flex align-items-center">
          <h3 class="mb-0" id="currentProjectTitle">Select a Project</h3>
          <span class="badge badge-secondary ml-2" id="currentProjectStatus">No Project</span>
          <div class="btn-group ml-3">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-toggle="dropdown">
              <i class="fas fa-project-diagram mr-1"></i> Projects
            </button>
            <div class="dropdown-menu" id="projectsDropdown">
              <!-- Projects will be loaded here -->
            </div>
          </div>
          <button class="btn btn-outline-secondary ml-2" id="boardSettingsBtn">
              <i class="fas fa-columns mr-1"></i> Board Settings
          </button>
        </div>
        <div class="d-flex align-items-center">
          <div class="input-group mr-3" style="width: 300px;">
            <input type="text" class="form-control" id="taskSearch" placeholder="Search tasks...">
            <div class="input-group-append">
              <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
          <div class="btn-group mr-2">
            <button type="button" class="btn btn-outline-primary" id="filterBtn">
              <i class="fas fa-filter"></i> Filter
            </button>
            <button type="button" class="btn btn-outline-primary" id="viewMyTasksBtn">
              <i class="fas fa-tasks"></i> My Tasks
            </button>
          </div>
          <?php if ($canAssignTasks): ?>
          <button class="btn btn-primary" id="addTaskBtn">
            <i class="fas fa-plus mr-1"></i> Add Task
          </button>
          <?php endif; ?>
          <button class="btn btn-success ml-2" id="newProjectBtn">
            <i class="fas fa-plus mr-1"></i> New Project
          </button>
          <button class="btn btn-info ml-2" id="projectsMonitoringBtn">
            <i class="fas fa-chart-line mr-1"></i> Monitoring
          </button>
        </div>
      </div>

      <!-- Projects Monitoring Section -->
      <div class="container-fluid mt-4" id="projectsMonitoring" style="display: none;">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Projects Monitoring</h3>
                <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                  </button>
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
                        <th>Created By</th>
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
        </div>
      </div>

      <!-- My Tasks Section -->
      <div class="container-fluid mt-4" id="myTasksSection" style="display: none;">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">My Tasks</h3>
                <div class="card-tools">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-bordered table-hover" id="myTasksTable">
                    <thead>
                      <tr>
                        <th>Task Title</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th>Created By</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody id="myTasksTableBody">
                      <!-- My tasks will be loaded here -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    <!-- Scrum Content -->
    <div class="scrum-content" id="scrumBoardContent">
        <div class="columns-container" id="dynamicColumnsContainer">
            <!-- Boards will be loaded dynamically here -->
            <div class="col-12 text-center py-5" id="noBoardsMessage" style="display: none;">
                <i class="fas fa-columns fa-3x text-muted mb-3"></i>
                <p class="text-muted">No boards found. Create your first board to get started.</p>
                <button class="btn btn-primary" onclick="scrumboard.showAddBoardModal()">
                    <i class="fas fa-plus mr-1"></i> Create First Board
                </button>
            </div>
        </div>
    </div>
    </div>
  </div>
  <?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" role="dialog" aria-labelledby="addTaskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="taskForm">
          <input type="hidden" id="currentProjectId" value="">
          <div class="row">
            <div class="col-md-8">
              <div class="form-group">
                <label for="taskTitle">Task Title *</label>
                <input type="text" class="form-control" id="taskTitle" placeholder="Enter task title" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="taskPriority">Priority</label>
                <select class="form-control" id="taskPriority">
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label for="taskDescription">Description</label>
            <textarea class="form-control" id="taskDescription" rows="3" placeholder="Enter task description"></textarea>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="taskAssignee">Assignee</label>
                <select class="form-control" id="taskAssignee">
                  <option value="">Unassigned</option>
                  <!-- Assignable employees will be loaded here -->
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="taskDueDate">Due Date</label>
                <input type="date" class="form-control" id="taskDueDate">
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label>Labels</label>
            <div class="d-flex flex-wrap">
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="labelRevise" value="revise">
                <label class="custom-control-label" for="labelRevise">Revise</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="labelUrgent" value="urgent">
                <label class="custom-control-label" for="labelUrgent">Urgent</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="labelDesign" value="design">
                <label class="custom-control-label" for="labelDesign">Design</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="labelDevelopment" value="development">
                <label class="custom-control-label" for="labelDevelopment">Development</label>
              </div>
              <div class="custom-control custom-checkbox mr-3 mb-2">
                <input type="checkbox" class="custom-control-input" id="labelReview" value="review">
                <label class="custom-control-label" for="labelReview">Review</label>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveTaskBtn">Save Task</button>
      </div>
    </div>
  </div>
</div>

<!-- New Project Modal -->
<div class="modal fade" id="newProjectModal" tabindex="-1" role="dialog" aria-labelledby="newProjectModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newProjectModalLabel">Create New Project</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="projectForm">
          <div class="form-group">
            <label for="projectName">Project Name *</label>
            <input type="text" class="form-control" id="projectName" placeholder="Enter project name" required>
          </div>
          <div class="form-group">
            <label for="projectCode">Project Code *</label>
            <input type="text" class="form-control" id="projectCode" placeholder="Enter project code" required>
          </div>
          <div class="form-group">
            <label for="projectDescription">Description</label>
            <textarea class="form-control" id="projectDescription" rows="3" placeholder="Enter project description"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="projectStartDate">Start Date</label>
                <input type="date" class="form-control" id="projectStartDate">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="projectEndDate">End Date</label>
                <input type="date" class="form-control" id="projectEndDate">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label for="projectColor">Color</label>
            <input type="color" class="form-control" id="projectColor" value="#007bff">
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
<!-- Add Board Modal -->
<div class="modal fade" id="addBoardModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addBoardModalLabel">Add New Board</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="boardForm">
          <input type="hidden" id="editBoardId" value="">
          <div class="form-group">
            <label for="boardName">Board Name *</label>
            <input type="text" class="form-control" id="boardName" required>
          </div>
          <div class="form-group">
            <label for="boardDescription">Description</label>
            <textarea class="form-control" id="boardDescription" rows="3"></textarea>
          </div>
          <div class="form-group">
            <label for="boardColor">Color</label>
            <input type="color" class="form-control" id="boardColor" value="#007bff">
          </div>
          <div class="form-group">
            <label for="boardOrder">Order</label>
            <input type="number" class="form-control" id="boardOrder" min="0" value="0">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="deleteBoardBtn" style="display: none;">Delete</button>
        <button type="button" class="btn btn-primary" id="saveBoardBtn">Save Board</button>
      </div>
    </div>
  </div>
</div>

<!-- Board Settings Dropdown -->
<div class="modal fade" id="boardSettingsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Board Settings</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="list-group">
          <button type="button" class="list-group-item list-group-item-action" id="addNewBoardBtn">
            <i class="fas fa-plus mr-2"></i>Add New Board
          </button>
          <button type="button" class="list-group-item list-group-item-action" id="manageBoardsBtn">
            <i class="fas fa-cog mr-2"></i>Manage Boards
          </button>
          <button type="button" class="list-group-item list-group-item-action" id="resetBoardsBtn">
            <i class="fas fa-redo mr-2"></i>Reset to Default Boards
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
class Scrumboard {
    constructor() {
        this.currentProjectId = null;
        this.currentProject = null;
        this.projects = [];
        this.tasks = [];
        this.boards = [];
        this.canAssignTasks = <?php echo $canAssignTasks ? 'true' : 'false'; ?>;
        this.selectedBoardId = null; // Add this line
        this.init();
    }
    
    init() {
        this.loadProjects();
        this.setupEventListeners();
        this.setupDragAndDrop();
        this.handleUrlParameters();
    }
    
    handleUrlParameters() {
        // Get project_id from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const projectId = urlParams.get('project_id');
        
        if (projectId) {
            // Store the project ID to select after projects are loaded
            this.pendingProjectId = projectId;
        }
    }
    
    setupEventListeners() {
        // New project button
        $('#newProjectBtn').click(() => this.showNewProjectModal());
        $('#saveProjectBtn').click(() => this.createProject());
        
        // Add task button
        $('#addTaskBtn').click(() => this.showAddTaskModal());
        $('#saveTaskBtn').click(() => this.createTask());
        
        // Projects monitoring
        $('#projectsMonitoringBtn').click(() => this.toggleProjectsMonitoring());
        
        // My tasks
        $('#viewMyTasksBtn').click(() => this.toggleMyTasks());
        
        // Task search
        $('#searchBtn').click(() => this.searchTasks());
        $('#taskSearch').on('keypress', (e) => {
            if (e.which === 13) this.searchTasks();
        });
        // Board management
        $('#boardSettingsBtn').click(() => this.showBoardSettings());
        $('#addNewBoardBtn').click(() => this.showAddBoardModal());
        $('#manageBoardsBtn').click(() => this.showManageBoards());
        $('#resetBoardsBtn').click(() => this.resetBoards());
        $('#saveBoardBtn').click(() => this.saveBoard());
        $('#deleteBoardBtn').click(() => this.deleteBoard());

        $(document).on('click', '.board-settings', (e) => {
            const boardId = $(e.currentTarget).data('board-id');
            const board = this.boards.find(b => b.board_id == boardId);
            if (board) {
                this.showAddBoardModal(board);
            }
        });
        
        $(document).on('click', '.add-task-to-board', (e) => {
            const boardId = $(e.currentTarget).data('board-id');
            this.showAddTaskModal(boardId);
        });
    }
    
    setupDragAndDrop() {
        $(document).on('dragstart', '.task-card', function(e) {
            const taskId = $(this).data('task-id');
            e.originalEvent.dataTransfer.setData('text/plain', taskId);
            setTimeout(() => {
                $(this).addClass('dragging');
            }, 0);
        });
        
        $(document).on('dragend', '.task-card', function() {
            $(this).removeClass('dragging');
        });
        
        $(document).on('dragover', '.tasks-container', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
        });
        
        $(document).on('drop', '.tasks-container', async (e) => {
            e.preventDefault();
            const taskId = e.originalEvent.dataTransfer.getData('text/plain');
            const newBoardId = $(e.currentTarget).data('board-id');
            
            if (taskId && newBoardId) {
                await this.updateTaskBoard(taskId, newBoardId);
            }
        });
    }
    
    async loadProjects() {
        try {
            console.log('Loading projects for user...');
            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_user_projects'
            });
            
            console.log('Projects response:', response);
            
            if (response.success) {
                this.projects = response.projects;
                console.log('Loaded projects:', this.projects);
                this.renderProjectsDropdown();
                
                // If we have a pending project ID from URL, select it
                if (this.pendingProjectId) {
                    this.selectProject(this.pendingProjectId);
                    this.pendingProjectId = null;
                } else if (this.projects.length > 0 && !this.currentProjectId) {
                    // Otherwise select the first project as default
                    console.log('Selecting first project:', this.projects[0].project_id);
                    this.selectProject(this.projects[0].project_id);
                } else {
                    console.log('No projects to select');
                    this.showNoProjectsMessage();
                }
            } else {
                console.error('Failed to load projects:', response.error);
                this.showError('Failed to load projects: ' + (response.error || 'Unknown error'));
                this.showNoProjectsMessage();
            }
        } catch (error) {
            console.error('Error loading projects:', error);
            this.showError('Failed to load projects: ' + error.message);
            this.showNoProjectsMessage();
        }
    }

    showNoProjectsMessage() {
        const container = $('.columns-container');
        container.html(`
            <div class="col-12 text-center py-5">
                <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Projects Found</h4>
                <p class="text-muted">You are not a member of any projects yet.</p>
                <button class="btn btn-primary" onclick="scrumboard.showNewProjectModal()">
                    <i class="fas fa-plus mr-1"></i> Create Your First Project
                </button>
                <button class="btn btn-outline-secondary ml-2" onclick="scrumboard.testConnection()">
                    <i class="fas fa-bug mr-1"></i> Debug Connection
                </button>
            </div>
        `);
    }

    async testConnection() {
        try {
            console.log('Testing connection...');
            const response = await $.post('../includes/project_ajax.php', {
                action: 'test_connection'
            });
            
            console.log('Connection test result:', response);
            
            if (response.success) {
                Swal.fire({
                    title: 'Connection Test',
                    html: `
                        <div class="text-left">
                            <p><strong>Total Projects:</strong> ${response.debug.projects_total}</p>
                            <p><strong>Total Members:</strong> ${response.debug.members_total}</p>
                            <p><strong>Your Projects:</strong> ${response.debug.user_projects}</p>
                            <p><strong>Your User ID:</strong> ${response.debug.session_emp_id}</p>
                            <p><strong>Session Status:</strong> ${response.debug.session_status}</p>
                        </div>
                    `,
                    icon: 'info'
                });
            } else {
                this.showError('Connection test failed: ' + response.error);
            }
        } catch (error) {
            console.error('Connection test error:', error);
            this.showError('Connection test failed: ' + error.message);
        }
    }
    
    renderProjectsDropdown() {
        const dropdown = $('#projectsDropdown');
        dropdown.empty();
        
        if (this.projects.length === 0) {
            dropdown.append('<a class="dropdown-item text-muted" href="#">No projects found</a>');
            return;
        }
        
        this.projects.forEach(project => {
            const item = $(`
                <a class="dropdown-item project-item" href="#" data-project-id="${project.project_id}">
                    <div class="d-flex align-items-center">
                        <div class="project-color-badge" style="background-color: ${project.color}"></div>
                        <div>
                            <div class="font-weight-bold">${project.project_name}</div>
                            <small class="text-muted">${project.project_code}</small>
                        </div>
                    </div>
                </a>
            `);
            
            item.click(() => this.selectProject(project.project_id));
            dropdown.append(item);
        });
    }
    
    async selectProject(projectId) {
        try {
            this.currentProjectId = projectId;
            this.currentProject = this.projects.find(p => p.project_id == projectId);
            
            // Update UI
            $('#currentProjectTitle').text(this.currentProject.project_name);
            $('#currentProjectStatus').text(this.currentProject.status.charAt(0).toUpperCase() + this.currentProject.status.slice(1));
            $('#currentProjectStatus').removeClass('badge-secondary badge-success badge-warning badge-danger')
                .addClass(this.getStatusBadgeClass(this.currentProject.status));
            $('#currentProjectId').val(projectId);
            
            // Load boards and tasks
            await this.loadProjectBoards();
            await this.loadProjectTasks();
            await this.loadAssignableEmployees();
            
            // Hide other sections
            $('#projectsMonitoring').hide();
            $('#myTasksSection').hide();
            $('#scrumBoardContent').show();
            
        } catch (error) {
            console.error('Error selecting project:', error);
            this.showError('Failed to load project');
        }
    }
    
    getStatusBadgeClass(status) {
        const classes = {
            'active': 'badge-success',
            'completed': 'badge-primary',
            'on_hold': 'badge-warning',
            'cancelled': 'badge-danger'
        };
        return classes[status] || 'badge-secondary';
    }
    
    async loadProjectTasks() {
        try {
            const response = await $.post('../includes/task_ajax.php', {
                action: 'get_project_tasks',
                project_id: this.currentProjectId
            });
            
            if (response.success) {
                this.tasks = response.tasks;
                this.renderTasks();
            }
        } catch (error) {
            console.error('Error loading tasks:', error);
            this.showError('Failed to load tasks');
        }
    }
    
    renderTasks() {
        // Clear all task containers first
        $('.tasks-container').empty();
        
        // Group tasks by board_id
        const tasksByBoard = {};
        this.tasks.forEach(task => {
            if (!tasksByBoard[task.board_id]) {
                tasksByBoard[task.board_id] = [];
            }
            tasksByBoard[task.board_id].push(task);
        });
        
        // Render tasks for each board
        this.boards.forEach(board => {
            const container = $(`[data-board-id="${board.board_id}"]`);
            const boardTasks = tasksByBoard[board.board_id] || [];
            
            if (boardTasks.length === 0) {
                container.append(`
                    <div class="empty-column">
                        <i class="fas fa-inbox"></i>
                        <p>No tasks</p>
                    </div>
                `);
            } else {
                boardTasks.forEach(task => {
                    const taskHtml = this.createTaskHtml(task);
                    container.append(taskHtml);
                });
            }
            
            // Update task count for this board
            $(`[data-board-id="${board.board_id}"]`).closest('.column').find('.task-count').text(boardTasks.length);
        });
    }
    
    createTaskHtml(task) {
        const labels = task.labels ? task.labels.split(',') : [];
        const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Not set';
        const assigneeName = task.assigned_to ? `${task.first_name} ${task.last_name}` : 'Unassigned';
        const creatorName = task.creator_first ? `${task.creator_first} ${task.creator_last}` : 'Unknown';
        
        return `
            <div class="task-card" draggable="true" data-task-id="${task.task_id}" data-board-id="${task.board_id}">
                ${task.priority !== 'medium' ? 
                    `<div class="task-priority priority-${task.priority}">${task.priority}</div>` : ''}
                <div class="task-labels">
                    ${labels.map(label => 
                        `<span class="task-label label-${label}">${label.charAt(0).toUpperCase() + label.slice(1)}</span>`
                    ).join('')}
                </div>
                <div class="task-title">${this.escapeHtml(task.title)}</div>
                <div class="task-meta">
                    <span><i class="far fa-calendar mr-1"></i> ${dueDate}</span>
                    <span><i class="far fa-user mr-1"></i> ${assigneeName}</span>
                </div>
                <small class="text-muted">Created by: ${creatorName}</small>
            </div>
        `;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    async loadAssignableEmployees() {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_assignable_employees'
            });
            
            if (response.success) {
                const select = $('#taskAssignee');
                select.empty();
                select.append('<option value="">Unassigned</option>');
                
                response.employees.forEach(employee => {
                    const role = employee.role_name || (employee.is_manager ? 'Manager' : 'Employee');
                    select.append(`<option value="${employee.emp_id}">${employee.first_name} ${employee.last_name} (${role})</option>`);
                });
            }
        } catch (error) {
            console.error('Error loading assignable employees:', error);
        }
    }
    
    showNewProjectModal() {
        $('#newProjectModal').modal('show');
    }
    
    async createProject() {
        const formData = {
            project_name: $('#projectName').val().trim(),
            project_code: $('#projectCode').val().trim(),
            project_description: $('#projectDescription').val().trim(),
            start_date: $('#projectStartDate').val(),
            end_date: $('#projectEndDate').val(),
            color: $('#projectColor').val(),
            created_by: <?= $_SESSION['emp_id'] ?>
        };
        
        console.log('Sending project data:', formData); // Add this line for debugging
        
        if (!formData.project_name || !formData.project_code) {
            this.showError('Please fill in all required fields');
            return;
        }
        
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'create_project',
                ...formData
            });
            
            console.log('Server response:', response); // Add this line for debugging
            
            if (response.success) {
                $('#newProjectModal').modal('hide');
                $('#projectForm')[0].reset();
                await this.loadProjects();
                this.showSuccess('Project created successfully');
                
                // Select the new project
                if (response.project_id) {
                    this.selectProject(response.project_id);
                }
            } else {
                this.showError(response.error || 'Failed to create project');
            }
        } catch (error) {
            console.error('Error creating project:', error);
            this.showError('Failed to create project');
        }
    }
    
    showAddTaskModal() {
        if (!this.currentProjectId) {
            this.showError('Please select a project first');
            return;
        }
        $('#addTaskModal').modal('show');
    }
    
    async createTask() {
        const labels = [];
        if ($('#labelRevise').is(':checked')) labels.push('revise');
        if ($('#labelUrgent').is(':checked')) labels.push('urgent');
        if ($('#labelDesign').is(':checked')) labels.push('design');
        if ($('#labelDevelopment').is(':checked')) labels.push('development');
        if ($('#labelReview').is(':checked')) labels.push('review');
        
        // Use selected board or first board as default
        const boardId = this.selectedBoardId || (this.boards.length > 0 ? this.boards[0].board_id : null);
        
        const formData = {
            project_id: this.currentProjectId,
            title: $('#taskTitle').val().trim(),
            description: $('#taskDescription').val().trim(),
            board_id: boardId, // Use the board ID here
            priority: $('#taskPriority').val(),
            labels: labels,
            due_date: $('#taskDueDate').val(),
            assigned_to: $('#taskAssignee').val(),
            created_by: <?= $_SESSION['emp_id'] ?>
        };
        
        if (!formData.title) {
            this.showError('Please enter a task title');
            return;
        }
        
        if (!formData.board_id) {
            this.showError('No boards available. Please create a board first.');
            return;
        }
        
        try {
            const response = await $.post('../includes/task_ajax.php', {
                action: 'create_task',
                ...formData
            });
            
            if (response.success) {
                $('#addTaskModal').modal('hide');
                $('#taskForm')[0].reset();
                this.selectedBoardId = null; // Reset selected board
                await this.loadProjectTasks();
                this.showSuccess('Task created successfully');
            } else {
                this.showError(response.error || 'Failed to create task');
            }
        } catch (error) {
            console.error('Error creating task:', error);
            this.showError('Failed to create task');
        }
    }
    
    async updateTaskStatus(taskId, newStatus) {
        try {
            const response = await $.post('../includes/task_ajax.php', {
                action: 'update_task_status',
                task_id: taskId,
                status: newStatus
            });
            
            if (response.success) {
                // Reload tasks to reflect the change
                await this.loadProjectTasks();
            } else {
                this.showError('Failed to update task status');
            }
        } catch (error) {
            console.error('Error updating task status:', error);
            this.showError('Failed to update task status');
        }
    }
    
    async toggleProjectsMonitoring() {
        const monitoringSection = $('#projectsMonitoring');
        if (monitoringSection.is(':visible')) {
            monitoringSection.hide();
            $('#scrumBoardContent').show();
        } else {
            await this.loadProjectsMonitoring();
            monitoringSection.show();
            $('#scrumBoardContent').hide();
            $('#myTasksSection').hide();
        }
    }
    
    async loadProjectsMonitoring() {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_projects_monitoring'
            });
            
            if (response.success) {
                this.renderProjectsMonitoring(response.projects);
            }
        } catch (error) {
            console.error('Error loading projects monitoring:', error);
            this.showError('Failed to load projects monitoring');
        }
    }
    
    renderProjectsMonitoring(projects) {
        const tbody = $('#projectsTableBody');
        tbody.empty();
        
        if (projects.length === 0) {
            tbody.append('<tr><td colspan="9" class="text-center py-4 text-muted">No projects found</td></tr>');
            return;
        }
        
        projects.forEach(project => {
            const createdBy = project.creator_first ? 
                `${project.creator_first} ${project.creator_last}` : 'Unknown';
                
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
                        <span class="badge ${this.getStatusBadgeClass(project.status)}">
                            ${project.status.replace('_', ' ').toUpperCase()}
                        </span>
                    </td>
                    <td>${project.task_count || 0}</td>
                    <td>${project.member_count || 0}</td>
                    <td>${createdBy}</td>
                    <td>
                        <button class="btn btn-sm btn-primary view-project" data-project-id="${project.project_id}">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-success select-project" data-project-id="${project.project_id}">
                            <i class="fas fa-play"></i>
                        </button>
                    </td>
                </tr>
            `);
            
            tbody.append(row);
        });
        
        // Add event listeners
        $('.view-project').off().click((e) => {
            const projectId = $(e.currentTarget).data('project-id');
            this.viewProjectDetails(projectId);
        });
        
        $('.select-project').off().click((e) => {
            const projectId = $(e.currentTarget).data('project-id');
            this.selectProject(projectId);
        });
    }
    
    async viewProjectDetails(projectId) {
        try {
            const response = await $.post('../includes/project_ajax.php', {
                action: 'get_project_details',
                project_id: projectId
            });
            
            if (response.success) {
                const project = response.project;
                let membersHtml = '';
                
                if (project.members && project.members.length > 0) {
                    membersHtml = project.members.map(member => 
                        `<li>${member.first_name} ${member.last_name} (${member.role})</li>`
                    ).join('');
                } else {
                    membersHtml = '<li>No members</li>';
                }
                
                Swal.fire({
                    title: project.project_name,
                    html: `
                        <div class="text-left">
                            <p><strong>Code:</strong> ${project.project_code}</p>
                            <p><strong>Description:</strong> ${project.project_description || 'No description'}</p>
                            <p><strong>Status:</strong> <span class="badge ${this.getStatusBadgeClass(project.status)}">${project.status.toUpperCase()}</span></p>
                            <p><strong>Dates:</strong> ${project.start_date || 'Not set'} to ${project.end_date || 'Not set'}</p>
                            <p><strong>Members:</strong></p>
                            <ul>${membersHtml}</ul>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
            } else {
                this.showError('Failed to load project details');
            }
        } catch (error) {
            console.error('Error viewing project details:', error);
            this.showError('Failed to load project details');
        }
    }
    
    async toggleMyTasks() {
        const myTasksSection = $('#myTasksSection');
        if (myTasksSection.is(':visible')) {
            myTasksSection.hide();
            $('#scrumBoardContent').show();
        } else {
            await this.loadMyTasks();
            myTasksSection.show();
            $('#scrumBoardContent').hide();
            $('#projectsMonitoring').hide();
        }
    }
    
    async loadMyTasks() {
        try {
            const response = await $.post('../includes/task_ajax.php', {
                action: 'get_user_tasks',
                project_id: this.currentProjectId || null
            });
            
            if (response.success) {
                this.renderMyTasks(response.tasks);
            }
        } catch (error) {
            console.error('Error loading my tasks:', error);
            this.showError('Failed to load your tasks');
        }
    }
    
    renderMyTasks(tasks) {
        const tbody = $('#myTasksTableBody');
        tbody.empty();
        
        if (tasks.length === 0) {
            tbody.append('<tr><td colspan="7" class="text-center py-4 text-muted">No tasks assigned to you</td></tr>');
            return;
        }
        
        tasks.forEach(task => {
            const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Not set';
            const creatorName = task.creator_first ? `${task.creator_first} ${task.creator_last}` : 'Unknown';
            
            const row = $(`
                <tr>
                    <td>${this.escapeHtml(task.title)}</td>
                    <td>${task.project_name} (${task.project_code})</td>
                    <td>
                        <span class="badge badge-${this.getTaskStatusBadgeClass(task.status)}">
                            ${task.status.replace('_', ' ').toUpperCase()}
                        </span>
                    </td>
                    <td>
                        <span class="badge priority-${task.priority}">
                            ${task.priority.toUpperCase()}
                        </span>
                    </td>
                    <td>${dueDate}</td>
                    <td>${creatorName}</td>
                    <td>
                        <button class="btn btn-sm btn-info view-task" data-task-id="${task.task_id}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `);
            
            tbody.append(row);
        });
        
        // Add event listeners for task viewing
        $('.view-task').off().click((e) => {
            const taskId = $(e.currentTarget).data('task-id');
            this.viewTaskDetails(taskId);
        });
    }
    
    getTaskStatusBadgeClass(status) {
        const classes = {
            'backlog': 'secondary',
            'todo': 'warning',
            'inprogress': 'info',
            'review': 'primary',
            'done': 'success'
        };
        return classes[status] || 'secondary';
    }
    
    async viewTaskDetails(taskId) {
        const task = this.tasks.find(t => t.task_id == taskId) || 
                    (await this.loadTaskDetails(taskId));
        
        if (task) {
            const labels = task.labels ? task.labels.split(',') : [];
            const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Not set';
            const assigneeName = task.assigned_to ? `${task.first_name} ${task.last_name}` : 'Unassigned';
            const creatorName = task.creator_first ? `${task.creator_first} ${task.creator_last}` : 'Unknown';
            
            Swal.fire({
                title: task.title,
                html: `
                    <div class="text-left">
                        <p><strong>Description:</strong> ${task.description || 'No description'}</p>
                        <p><strong>Status:</strong> <span class="badge ${this.getTaskStatusBadgeClass(task.status)}">${task.status.toUpperCase()}</span></p>
                        <p><strong>Priority:</strong> <span class="badge priority-${task.priority}">${task.priority.toUpperCase()}</span></p>
                        <p><strong>Due Date:</strong> ${dueDate}</p>
                        <p><strong>Assigned To:</strong> ${assigneeName}</p>
                        <p><strong>Created By:</strong> ${creatorName}</p>
                        ${labels.length > 0 ? `
                            <p><strong>Labels:</strong> 
                                ${labels.map(label => `<span class="badge label-${label}">${label.toUpperCase()}</span>`).join(' ')}
                            </p>
                        ` : ''}
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'OK'
            });
        }
    }
    
    async loadTaskDetails(taskId) {
        try {
            // In a real implementation, you might have a separate endpoint for this
            // For now, we'll search in current tasks or all tasks
            const response = await $.post('../includes/task_ajax.php', {
                action: 'get_all_tasks',
                task_id: taskId
            });
            
            if (response.success && response.tasks.length > 0) {
                return response.tasks[0];
            }
        } catch (error) {
            console.error('Error loading task details:', error);
        }
        return null;
    }
    
    searchTasks() {
        const searchTerm = $('#taskSearch').val().toLowerCase();
        
        $('.task-card').each(function() {
            const title = $(this).find('.task-title').text().toLowerCase();
            const description = $(this).find('.task-description')?.text().toLowerCase() || '';
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
    
    showSuccess(message) {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: message,
            timer: 3000,
            showConfirmButton: false
        });
    }
    
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message
        });
    }
    async updateTaskBoard(taskId, newBoardId) {
        try {
            const response = await $.post('../includes/task_ajax.php', {
                action: 'update_task_board',
                task_id: taskId,
                board_id: newBoardId
            });
            
            if (response.success) {
                await this.loadProjectTasks();
                this.showSuccess('Task moved successfully');
            } else {
                this.showError('Failed to move task');
            }
        } catch (error) {
            console.error('Error moving task:', error);
            this.showError('Failed to move task');
        }
    }
    async loadProjectBoards() {
        try {
            console.log('Loading boards for project:', this.currentProjectId);
            const response = await $.post('../includes/board_ajax.php', {
                action: 'get_project_boards',
                project_id: this.currentProjectId
            });
            
            console.log('Boards response:', response);
            
            if (response.success) {
                this.boards = response.boards;
                console.log('Loaded boards:', this.boards);
                this.renderBoards();
            } else {
                console.error('Failed to load boards:', response.error);
                this.boards = [];
                this.renderBoards();
            }
        } catch (error) {
            console.error('Error loading boards:', error);
            this.boards = [];
            this.renderBoards();
        }
    }

    renderBoards() {
        const container = $('.columns-container');
        container.empty();
        
        if (this.boards.length === 0) {
            container.append(`
                <div class="col-12 text-center py-5">
                    <i class="fas fa-columns fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No boards found. Create your first board to get started.</p>
                    <button class="btn btn-primary" onclick="scrumboard.showAddBoardModal()">
                        <i class="fas fa-plus mr-1"></i> Create First Board
                    </button>
                </div>
            `);
            return;
        }

        // Sort boards by order
        const sortedBoards = this.boards.sort((a, b) => a.board_order - b.board_order);
        
        sortedBoards.forEach(board => {
            const columnHtml = this.createBoardColumn(board);
            container.append(columnHtml);
        });
    }

    createBoardColumn(board) {
        const statusTasks = this.tasks.filter(task => task.board_id == board.board_id);
        const taskCount = statusTasks.length;
        
        return `
            <div class="column" data-board-id="${board.board_id}">
                <div class="column-header">
                    <div class="d-flex align-items-center">
                        <div class="board-color-badge mr-2" style="background-color: ${board.board_color}"></div>
                        <h4 class="column-title mb-0">${board.board_name}</h4>
                    </div>
                    <div class="column-actions">
                        <span class="task-count">${taskCount}</span>
                        ${this.canAssignTasks ? `
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-secondary board-settings" 
                                        data-board-id="${board.board_id}" title="Board Settings">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>
                <div class="tasks-container" data-board-id="${board.board_id}">
                    ${taskCount === 0 ? `
                        <div class="empty-column">
                            <i class="fas fa-inbox"></i>
                            <p>No tasks</p>
                        </div>
                    ` : ''}
                    ${statusTasks.map(task => this.createTaskHtml(task)).join('')}
                </div>
                ${this.canAssignTasks ? `
                    <div class="column-footer mt-2">
                        <button class="btn btn-sm btn-outline-primary btn-block add-task-to-board" 
                                data-board-id="${board.board_id}">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    }

    showBoardSettings() {
        $('#boardSettingsModal').modal('show');
    }

    showAddBoardModal(board = null) {
        $('#addBoardModalLabel').text(board ? 'Edit Board' : 'Add New Board');
        $('#editBoardId').val(board ? board.board_id : '');
        $('#boardName').val(board ? board.board_name : '');
        $('#boardDescription').val(board ? board.board_description : '');
        $('#boardColor').val(board ? board.board_color : '#007bff');
        $('#boardOrder').val(board ? board.board_order : this.boards.length);
        $('#deleteBoardBtn').toggle(!!board);
        
        $('#boardSettingsModal').modal('hide');
        $('#addBoardModal').modal('show');
    }

    async saveBoard() {
        const formData = {
            board_id: $('#editBoardId').val(),
            project_id: this.currentProjectId,
            board_name: $('#boardName').val().trim(),
            board_description: $('#boardDescription').val().trim(),
            board_color: $('#boardColor').val(),
            board_order: $('#boardOrder').val()
        };

        if (!formData.board_name) {
            this.showError('Please enter a board name');
            return;
        }

        try {
            const action = formData.board_id ? 'update_board' : 'create_board';
            const response = await $.post('../includes/board_ajax.php', {
                action: action,
                ...formData
            });

            if (response.success) {
                $('#addBoardModal').modal('hide');
                $('#boardForm')[0].reset();
                await this.loadProjectBoards();
                this.showSuccess(`Board ${formData.board_id ? 'updated' : 'created'} successfully`);
            } else {
                this.showError(response.error || `Failed to ${formData.board_id ? 'update' : 'create'} board`);
            }
        } catch (error) {
            console.error('Error saving board:', error);
            this.showError(`Failed to ${formData.board_id ? 'update' : 'create'} board`);
        }
    }

    async deleteBoard() {
        const boardId = $('#editBoardId').val();
        
        Swal.fire({
            title: 'Are you sure?',
            text: "This will delete the board and all tasks in it!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await $.post('../includes/board_ajax.php', {
                        action: 'delete_board',
                        board_id: boardId
                    });

                    if (response.success) {
                        $('#addBoardModal').modal('hide');
                        await this.loadProjectBoards();
                        this.showSuccess('Board deleted successfully');
                    } else {
                        this.showError(response.error || 'Failed to delete board');
                    }
                } catch (error) {
                    console.error('Error deleting board:', error);
                    this.showError('Failed to delete board');
                }
            }
        });
    }

    async resetBoards() {
        Swal.fire({
            title: 'Reset Boards?',
            text: "This will replace all current boards with default boards (Backlog, To Do, In Progress, Review, Done)",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, reset!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await $.post('../includes/board_ajax.php', {
                        action: 'reset_boards',
                        project_id: this.currentProjectId
                    });

                    if (response.success) {
                        $('#boardSettingsModal').modal('hide');
                        await this.loadProjectBoards();
                        this.showSuccess('Boards reset to default successfully');
                    } else {
                        this.showError(response.error || 'Failed to reset boards');
                    }
                } catch (error) {
                    console.error('Error resetting boards:', error);
                    this.showError('Failed to reset boards');
                }
            }
        });
    }
}

// Initialize scrumboard when document is ready
$(document).ready(function() {
    // Set scrumboard theme
    localStorage.setItem('currentTheme', 'scrumboard');
    $('.main-header').css('background', 'linear-gradient(135deg, #8B5CF6, #7C3AED)');
    $('#mainFooter').css('background', 'linear-gradient(135deg, #8B5CF6, #7C3AED)');
    
    window.scrumboard = new Scrumboard();
});
// Initialize scrumboard when document is ready
$(document).ready(function() {
    // Set scrumboard theme
    localStorage.setItem('currentTheme', 'scrum');
    $('.main-header').css('background', 'linear-gradient(135deg, #8B5CF6, #7C3AED)');
    $('#mainFooter').css('background', 'linear-gradient(135deg, #8B5CF6, #7C3AED)');
    
    window.scrumboard = new Scrumboard();
});
</script>
</body>
</html>