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

// Manager's Office files count (files without section_id or with special manager section)
$stmt = $db->prepare("SELECT COUNT(*) as manager_files FROM files WHERE section_id IS NULL OR section_id = 0");
$stmt->execute();
$stats['manager_files'] = $stmt->get_result()->fetch_assoc()['manager_files'];

// Remove the old manager_staff query since we don't need it anymore
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Management Dashboard</title>
    
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/file.css">
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
                                    <!-- Manager's Office Section Card -->
                                    <div class="row">
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
                                                    
                                                    <div class="col-md-6 col-lg-3 mb-2">
                                                        <div class="card section-card dashboard-card">
                                                            <div class="card-body">
                                                                <div class="section-info">
                                                                    <h5 class="font-weight-bold">Manager's Office</h5>
                                                                    <div class="mb-2">
                                                                        <span class="badge badge-primary section-badge">IMO</span>
                                                                        <span class="badge badge-secondary section-badge">Executive</span>
                                                                    </div>
                                                                    
                                                                    <div class="section-details">
                                                                        <p class="mb-1">
                                                                            <i class="fas fa-file mr-2 text-primary"></i>
                                                                            <strong><?= $stats['manager_files'] ?></strong> files
                                                                        </p>
                                                                        
                                                                        <p class="mb-1">
                                                                            <i class="fas fa-user-tie mr-2 text-info"></i>
                                                                            Office Manager:
                                                                        </p>
                                                                        
                                                                        <p class="mb-0 text-muted small">
                                                                            <i class="fas fa-calendar mr-1"></i>
                                                                            Executive Office
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="section-actions mt-3">
                                                                    <div class="btn-group w-100">
                                                                        <a href="section_files.php?section_id=manager" 
                                                                        class="btn btn-primary-custom btn-sm">
                                                                            <i class="fas fa-eye mr-1"></i> View Files
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php foreach ($sections as $section): ?>
                                                        <div class="col-md-6 col-lg-3 mb-2">
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

        <?php include '../includes/mainfooter.php'; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>