<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Fetch sections data from database
$query = "SELECT s.*, 
                 o.office_name,
                 CONCAT(e.first_name, ' ', e.last_name) as head_name,
                 (SELECT COUNT(*) FROM files WHERE section_id = s.section_id) as file_count
          FROM section s
          LEFT JOIN office o ON s.office_id = o.office_id
          LEFT JOIN employee e ON s.head_emp_id = e.emp_id
          ORDER BY s.section_name";
          
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

// Fetch dashboard statistics
$stats = [];
// Total files
$stmt = $db->prepare("SELECT COUNT(*) as total FROM files");
$stmt->execute();
$stats['total_files'] = $stmt->get_result()->fetch_assoc()['total'];

// New files (last 7 days)
$stmt = $db->prepare("SELECT COUNT(*) as new FROM files WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$stats['new_files'] = $stmt->get_result()->fetch_assoc()['new'];

// Pending approvals
$stmt = $db->prepare("SELECT COUNT(*) as pending FROM files WHERE status = 'pending'");
$stmt->execute();
$stats['pending'] = $stmt->get_result()->fetch_assoc()['pending'];

// Approved files
$stmt = $db->prepare("SELECT COUNT(*) as approved FROM files WHERE status = 'approved'");
$stmt->execute();
$stats['approved'] = $stmt->get_result()->fetch_assoc()['approved'];

// Total sections
$stats['sections'] = count($sections);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Management Dashboard</title>
    
    <?php include '../includes/header.php'; ?>
    
    <style>
        /* Custom styles for the dashboard */
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --info-color: #4895ef;
            --warning-color: #f72585;
            --danger-color: #7209b7;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
            background: white;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .dashboard-card .card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .dashboard-card .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 20px;
        }
        
        .file-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .summary-item {
            text-align: center;
            padding: 15px;
        }
        
        .summary-item h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .summary-item p {
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }
        
        .card-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: #343a40;
        }
        
        .small-box {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            color: white;
            margin-bottom: 20px;
        }
        
        .small-box>.inner {
            padding: 20px;
            position: relative;
            z-index: 2;
        }
        
        .small-box h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            white-space: nowrap;
            padding: 0;
        }
        
        .small-box p {
            font-size: 1rem;
            color: rgba(255,255,255,0.9);
        }
        
        .small-box .icon {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 0;
            font-size: 70px;
            color: rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }
        
        .small-box:hover .icon {
            font-size: 75px;
            transform: rotate(5deg);
        }
        
        .bg-gradient-new-files {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .bg-gradient-sections {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .bg-gradient-pending {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .bg-gradient-approved {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .activity-logs {
            margin-top: 30px;
        }
        
        .card-header {
            border-bottom: 1px solid rgba(0,0,0,0.08);
            background-color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #343a40;
        }
        
        /* Filter form styles */
        .filter-form .input-group {
            width: auto;
        }
        .filter-form .form-control {
            margin-right: 5px;
            border-radius: 6px;
        }
        
        /* Custom colors */
        .btn-primary-custom {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary-custom:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white;
        }
        
        .text-primary-custom {
            color: var(--primary-color) !important;
        }
        
        /* File type badges */
        .badge-document { background-color: #007bff; }
        .badge-spreadsheet { background-color: #28a745; }
        .badge-presentation { background-color: #ffc107; color: #212529; }
        .badge-image { background-color: #e83e8c; }
        .badge-pdf { background-color: #dc3545; }
        .badge-archive { background-color: #6f42c1; }
        
        /* Status badges */
        .badge-pending { background-color: #fd7e14; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
        .badge-draft { background-color: #6c757d; }
        
        /* Section cards */
        .section-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .section-card:hover {
            border-left: 4px solid var(--secondary-color);
        }
        
        .section-card .card-body {
            padding: 15px;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .section-card h5 {
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: #343a40;
        }
        
        .section-card p {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .section-card .section-info {
            flex-grow: 1;
        }
        
        .section-card .section-actions {
            margin-top: auto;
        }
        
        .section-badge {
            font-size: 0.8rem;
            margin-right: 5px;
        }
        
        /* Modal styles */
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 0;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        /* Navbar customization */
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
        }
        
        /* Table styles */
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        /* Footer */
        .main-footer {
            background: white;
            padding: 15px;
            border-top: 1px solid #dee2e6;
            margin-top: 30px;
        }
        
        /* Section colors for variety */
        .section-card:nth-child(4n+1) { border-left-color: #4361ee; }
        .section-card:nth-child(4n+2) { border-left-color: #f093fb; }
        .section-card:nth-child(4n+3) { border-left-color: #4facfe; }
        .section-card:nth-child(4n+4) { border-left-color: #43e97b; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_file.php'; ?>
    <div class="content-wrapper">

        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">File Management Dashboard</h1>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-new-files">
                            <div class="inner">
                                <h3 id="newFilesCount"><?= $stats['new_files'] ?></h3>
                                <p>New Files Uploaded</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-file-upload"></i>
                            </div>
                            <a href="upload_files.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-sections">
                            <div class="inner">
                                <h3 id="sectionsCount"><?= $stats['sections'] ?></h3>
                                <p>Sections</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-sitemap"></i>
                            </div>
                            <a href="manage_sections.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-pending">
                            <div class="inner">
                                <h3 id="pendingCount"><?= $stats['pending'] ?></h3>
                                <p>Pending Approvals</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <a href="pending_approvals.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-gradient-approved">
                            <div class="inner">
                                <h3 id="approvedCount"><?= $stats['approved'] ?></h3>
                                <p>Approved Files</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <a href="approved_files.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <!-- ./col -->
                </div>
                <!-- Main row -->
                <div class="row">
                    <!-- Left col -->
                    <section class="col-lg-12">
                        <!-- Custom tabs (Charts with tabs)-->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title">File Management</h3>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <!-- Dynamic Section Cards -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h4 class="mb-3">Sections & Offices</h4>
                                        <div class="row" id="sectionsContainer">
                                            <?php if (empty($sections)): ?>
                                                <div class="col-12">
                                                    <div class="alert alert-info text-center">
                                                        <i class="fas fa-info-circle mr-2"></i>
                                                        No sections found. Please add sections to get started.
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($sections as $section): ?>
                                                    <div class="col-md-6 col-lg-3 mb-4">
                                                        <div class="card section-card dashboard-card">
                                                            <div class="card-body">
                                                                <div class="section-info">
                                                                    <h5 class="font-weight-bold"><?= htmlspecialchars($section['section_name']) ?></h5>
                                                                    <div class="mb-2">
                                                                        <span class="badge badge-primary section-badge"><?= htmlspecialchars($section['section_code']) ?></span>
                                                                        <?php if (!empty($section['office_name'])): ?>
                                                                            <span class="badge badge-secondary section-badge"><?= htmlspecialchars($section['office_name']) ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    
                                                                    <div class="section-details">
                                                                        <p class="mb-1">
                                                                            <i class="fas fa-file mr-2 text-primary"></i>
                                                                            <strong><?= $section['file_count'] ?></strong> files
                                                                        </p>
                                                                        
                                                                        <?php if (!empty($section['head_name'])): ?>
                                                                            <p class="mb-1">
                                                                                <i class="fas fa-user-tie mr-2 text-info"></i>
                                                                                Head: <?= htmlspecialchars($section['head_name']) ?>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                        
                                                                        <p class="mb-0 text-muted small">
                                                                            <i class="fas fa-calendar mr-1"></i>
                                                                            Created: <?= date('M j, Y', strtotime($section['created_at'])) ?>
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="section-actions mt-3">
                                                                    <div class="btn-group w-100">
                                                                        <a href="section_files.php?section_id=<?= $section['section_id'] ?>" 
                                                                           class="btn btn-primary-custom btn-sm">
                                                                            <i class="fas fa-eye mr-1"></i> View Files
                                                                        </a>
                                                                        <button class="btn btn-outline-secondary btn-sm create-folder-btn" 
                                                                                data-section-id="<?= $section['section_id'] ?>"
                                                                                data-section-name="<?= htmlspecialchars($section['section_name']) ?>">
                                                                            <i class="fas fa-folder-plus"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.card-body -->
                        </div>
                        <!-- /.card -->

                    </section>
                    <!-- /.Left col -->
                </div>
                <!-- /.row (main row) -->
            </div>
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 text-center">
                    <strong>File Management System</strong> &copy; 2025
                </div>
            </div>
        </div>
    </footer>
</div>
<?php include '../includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // Initialize DataTable for files
        $('#filesTable').DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "order": [[4, "desc"]],
            "ajax": {
                "url": "get_recent_files.php",
                "type": "GET",
                "dataSrc": ""
            },
            "columns": [
                { "data": "file_name" },
                { 
                    "data": "file_type",
                    "render": function(data, type, row) {
                        return '<span class="badge badge-' + data.toLowerCase() + '">' + data.toUpperCase() + '</span>';
                    }
                },
                { "data": "section_name" },
                { "data": "uploaded_by" },
                { "data": "upload_date" },
                { 
                    "data": "status",
                    "render": function(data, type, row) {
                        return '<span class="badge badge-' + data.toLowerCase() + '">' + data.charAt(0).toUpperCase() + data.slice(1) + '</span>';
                    }
                },
                {
                    "data": null,
                    "render": function(data, type, row) {
                        return `
                            <button class="btn btn-sm btn-info view-file-btn" data-id="${row.file_id}">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-warning download-file-btn" data-id="${row.file_id}">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-file-btn" data-id="${row.file_id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        `;
                    }
                }
            ],
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#filesTable_wrapper .col-md-6:eq(0)');
        
        // Save folder button handler
        $('#saveFolderBtn').click(function() {
            const folderName = $('#folderName').val();
            const folderSection = $('#folderSection').val();
            const folderDescription = $('#folderDescription').val();
            
            if (!folderName || !folderSection) {
                alert('Please fill in all required fields.');
                return;
            }
            
            // AJAX call to create folder
            $.ajax({
                url: 'create_folder.php',
                method: 'POST',
                data: {
                    folder_name: folderName,
                    section_id: folderSection,
                    description: folderDescription
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Folder created successfully!'
                        });
                        $('#createFolderModal').modal('hide');
                        $('#folderForm')[0].reset();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message || 'Failed to create folder'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Failed to create folder. Please try again.'
                    });
                }
            });
        });
        
        // Quick folder creation from section cards
        $('.create-folder-btn').click(function() {
            const sectionId = $(this).data('section-id');
            const sectionName = $(this).data('section-name');
            
            $('#folderSection').val(sectionId);
            $('#folderName').focus();
            $('#createFolderModal').modal('show');
            
            // Update modal title to show which section
            $('#createFolderModalLabel').text('Create Folder in ' + sectionName);
        });
        
        // File action handlers
        $(document).on('click', '.view-file-btn', function() {
            const fileId = $(this).data('id');
            window.location.href = 'view_file.php?id=' + fileId;
        });
        
        $(document).on('click', '.download-file-btn', function() {
            const fileId = $(this).data('id');
            window.location.href = 'download_file.php?id=' + fileId;
        });
        
        $(document).on('click', '.delete-file-btn', function() {
            const fileId = $(this).data('id');
            
            Swal.fire({
                title: 'Delete File?',
                text: 'Are you sure you want to delete this file?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'delete_file.php',
                        method: 'POST',
                        data: { file_id: fileId },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: 'File deleted successfully!'
                                });
                                $('#filesTable').DataTable().ajax.reload();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: response.message || 'Failed to delete file'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Failed to delete file. Please try again.'
                            });
                        }
                    });
                }
            });
        });
    });
</script>
</body>
</html>