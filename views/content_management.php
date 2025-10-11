<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Administrator') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle carousel image upload
    if (isset($_POST['add_carousel'])) {
        $caption = $_POST['caption'] ?? '';
        $display_order = $_POST['display_order'] ?? 0;
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload_dir = '../uploads/carousel/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $file_name = time() . '_' . basename($_FILES['image']['name']);
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $stmt = $db->prepare("INSERT INTO carousel_images (image_name, image_path, caption, display_order) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $_FILES['image']['name'], $target_path, $caption, $display_order);
                    
                    if ($stmt->execute()) {
                        $message = 'Carousel image uploaded successfully!';
                    } else {
                        $error = 'Failed to save image information to database.';
                    }
                } else {
                    $error = 'Failed to upload image.';
                }
            } else {
                $error = 'Invalid file type. Only JPG, PNG, and GIF images are allowed.';
            }
        } else {
            $error = 'Please select an image file.';
        }
    }
    
    // Handle company info update
    if (isset($_POST['update_company_info'])) {
        $section_name = $_POST['section_name'] ?? '';
        $content = $_POST['content'] ?? '';
        $id = $_POST['id'] ?? 0;
        
        if (!empty($section_name) && !empty($content)) {
            if ($id > 0) {
                // Update existing section
                $stmt = $db->prepare("UPDATE company_info SET section_name = ?, content = ? WHERE id = ?");
                $stmt->bind_param("ssi", $section_name, $content, $id);
            } else {
                // Check if section exists
                $check_stmt = $db->prepare("SELECT id FROM company_info WHERE section_name = ?");
                $check_stmt->bind_param("s", $section_name);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing section
                    $stmt = $db->prepare("UPDATE company_info SET content = ? WHERE section_name = ?");
                    $stmt->bind_param("ss", $content, $section_name);
                } else {
                    // Insert new section
                    $stmt = $db->prepare("INSERT INTO company_info (section_name, content) VALUES (?, ?)");
                    $stmt->bind_param("ss", $section_name, $content);
                }
            }
            
            if ($stmt->execute()) {
                $message = 'Company information updated successfully!';
            } else {
                $error = 'Failed to update company information.';
            }
        } else {
            $error = 'Section name and content are required.';
        }
    }
    
    // Handle form upload
    if (isset($_POST['add_form'])) {
        $form_name = $_POST['form_name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (isset($_FILES['form_file']) && $_FILES['form_file']['error'] === 0) {
            $upload_dir = '../uploads/forms/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['form_file']['name']);
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['form_file']['tmp_name'], $target_path)) {
                $stmt = $db->prepare("INSERT INTO company_forms (form_name, file_path, description) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $form_name, $target_path, $description);
                
                if ($stmt->execute()) {
                    $message = 'Form uploaded successfully!';
                } else {
                    $error = 'Failed to save form information to database.';
                }
            } else {
                $error = 'Failed to upload form file.';
            }
        } else {
            $error = 'Please select a form file.';
        }
    }
    
    // Handle delete operations
    if (isset($_POST['delete_carousel'])) {
        $id = $_POST['id'] ?? 0;
        
        // Get file path first
        $stmt = $db->prepare("SELECT image_path FROM carousel_images WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Delete file from server
            if (file_exists($row['image_path'])) {
                unlink($row['image_path']);
            }
            
            // Delete from database
            $delete_stmt = $db->prepare("DELETE FROM carousel_images WHERE id = ?");
            $delete_stmt->bind_param("i", $id);
            
            if ($delete_stmt->execute()) {
                $message = 'Carousel image deleted successfully!';
            } else {
                $error = 'Failed to delete image from database.';
            }
        }
    }
    
    if (isset($_POST['delete_form'])) {
        $id = $_POST['id'] ?? 0;
        
        // Get file path first
        $stmt = $db->prepare("SELECT file_path FROM company_forms WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Delete file from server
            if (file_exists($row['file_path'])) {
                unlink($row['file_path']);
            }
            
            // Delete from database
            $delete_stmt = $db->prepare("DELETE FROM company_forms WHERE id = ?");
            $delete_stmt->bind_param("i", $id);
            
            if ($delete_stmt->execute()) {
                $message = 'Form deleted successfully!';
            } else {
                $error = 'Failed to delete form from database.';
            }
        }
    }
    
    // Toggle active status
    if (isset($_POST['toggle_status'])) {
        $table = $_POST['table'] ?? '';
        $id = $_POST['id'] ?? 0;
        $current_status = $_POST['current_status'] ?? 0;
        
        if (in_array($table, ['carousel_images', 'company_info', 'company_forms'])) {
            $new_status = $current_status ? 0 : 1;
            $stmt = $db->prepare("UPDATE $table SET is_active = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_status, $id);
            
            if ($stmt->execute()) {
                $message = 'Status updated successfully!';
            } else {
                $error = 'Failed to update status.';
            }
        }
    }
}

// Fetch all data
$carousel_images = $db->query("SELECT * FROM carousel_images ORDER BY display_order, created_at DESC")->fetch_all(MYSQLI_ASSOC);
$company_info = $db->query("SELECT * FROM company_info ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$company_forms = $db->query("SELECT * FROM company_forms ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Content Management - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --success-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        .image-preview {
            max-width: 120px;
            max-height: 80px;
            margin: 5px 0;
            border-radius: 4px;
            border: 2px solid #dee2e6;
            object-fit: cover;
        }
        .file-preview {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 10px 0;
        }
        .status-badge {
            cursor: pointer;
        }
        .dataTables_wrapper {
            padding: 0;
        }
        .modal-lg {
            max-width: 80%;
        }
        .table th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            border: none;
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .carousel-actions {
            white-space: nowrap;
        }
        .carousel-actions form {
            display: inline-block;
            margin: 2px;
        }
        #carouselTable td {
            vertical-align: middle;
        }
        .caption-preview {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Admin Theme Specific Styles */
        .card-primary {
            border-color: var(--primary-color);
        }

        .card-primary .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-bottom: none;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .card-header h3, .card-header .card-title {
            color: white;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-color: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border-color: #28a745;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            border-color: #ffc107;
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--accent-color), var(--success-color));
            border-color: var(--accent-color);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border-color: #dc3545;
        }

        .badge-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .badge-success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(67, 97, 238, 0.02);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .modal-header .close {
            color: white;
            opacity: 0.8;
        }

        .modal-header .close:hover {
            opacity: 1;
        }

        .alert-success {
            border-left: 4px solid #28a745;
            background-color: #f8fff9;
        }

        .alert-danger {
            border-left: 4px solid #dc3545;
            background-color: #fff8f8;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        .dataTables_paginate .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-color: var(--primary-color);
        }

        .dataTables_paginate .page-link {
            color: var(--primary-color);
        }

        .dataTables_paginate .page-link:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .dataTables_filter input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        .dataTables_length select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }

        .content-header {
            color: black;
            margin-bottom: 20px;
            border-radius: 8px;
            padding: 20px;
        }

        .content-header h1 {
            color: BLACK;
            font-weight: 700;
            margin: 0;
        }

        hr {
            border-top: 2px solid var(--primary-color);
            opacity: 0.3;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Content Management</h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Carousel Management -->
                    <div class="col-md-6">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Carousel Images</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label>Image File</label>
                                        <input type="file" name="image" class="form-control" accept="image/*" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Caption</label>
                                        <input type="text" name="caption" class="form-control" placeholder="Optional caption">
                                    </div>
                                    <div class="form-group">
                                        <label>Display Order</label>
                                        <input type="number" name="display_order" class="form-control" value="0" min="0">
                                    </div>
                                    <button type="submit" name="add_carousel" class="btn btn-primary">Upload Image</button>
                                </form>

                                <hr>
                                <h5>Current Carousel Images</h5>
                                <div class="table-responsive">
                                    <table id="carouselTable" class="table table-bordered table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th width="120">Image</th>
                                                <th width="150">Caption</th>
                                                <th width="80">Order</th>
                                                <th width="100">Status</th>
                                                <th width="120">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($carousel_images as $image): ?>
                                                <tr>
                                                    <td>
                                                        <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                                                             class="image-preview" 
                                                             alt="<?= htmlspecialchars($image['caption']) ?>"
                                                             title="Click to view larger"
                                                             style="cursor: pointer;"
                                                             onclick="viewImage('<?= htmlspecialchars($image['image_path']) ?>', '<?= htmlspecialchars($image['caption']) ?>')">
                                                    </td>
                                                    <td>
                                                        <div class="caption-preview" title="<?= htmlspecialchars($image['caption']) ?>">
                                                            <?php if (!empty($image['caption'])): ?>
                                                                <?= htmlspecialchars(substr($image['caption'], 0, 30)) . (strlen($image['caption']) > 30 ? '...' : '') ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">No caption</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($image['caption'])): ?>
                                                            <button type="button" class="btn btn-info btn-xs mt-1 view-caption" 
                                                                    data-caption="<?= htmlspecialchars($image['caption']) ?>">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-primary badge-pill"><?= $image['display_order'] ?></span>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="table" value="carousel_images">
                                                            <input type="hidden" name="id" value="<?= $image['id'] ?>">
                                                            <input type="hidden" name="current_status" value="<?= $image['is_active'] ?>">
                                                            <button type="submit" name="toggle_status" class="btn btn-sm <?= $image['is_active'] ? 'btn-success' : 'btn-secondary' ?>">
                                                                <?= $image['is_active'] ? 'Active' : 'Inactive' ?>
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <div class="carousel-actions">
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this image?');">
                                                                <input type="hidden" name="id" value="<?= $image['id'] ?>">
                                                                <button type="submit" name="delete_carousel" class="btn btn-danger btn-sm" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Company Information Management -->
                    <div class="col-md-6">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Company Information</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="companyInfoForm">
                                    <input type="hidden" name="id" id="company_info_id" value="0">
                                    <div class="form-group">
                                        <label>Section Name</label>
                                        <input type="text" name="section_name" id="section_name" class="form-control" placeholder="e.g., About Us, Mission, Vision" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Content</label>
                                        <textarea name="content" id="content" class="form-control" rows="5" placeholder="Enter content for this section" required></textarea>
                                    </div>
                                    <button type="submit" name="update_company_info" class="btn btn-primary">Save Information</button>
                                    <button type="button" id="cancelEdit" class="btn btn-secondary" style="display:none;">Cancel</button>
                                </form>

                                <hr>
                                <h5>Current Information Sections</h5>
                                <div class="table-responsive">
                                    <table id="companyInfoTable" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Section</th>
                                                <th>Content Preview</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($company_info as $info): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($info['section_name']) ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-info btn-sm view-content" 
                                                                data-section="<?= htmlspecialchars($info['section_name']) ?>" 
                                                                data-content="<?= htmlspecialchars($info['content']) ?>">
                                                            View Content
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="table" value="company_info">
                                                            <input type="hidden" name="id" value="<?= $info['id'] ?>">
                                                            <input type="hidden" name="current_status" value="<?= $info['is_active'] ?>">
                                                            <button type="submit" name="toggle_status" class="btn btn-sm <?= $info['is_active'] ? 'btn-success' : 'btn-secondary' ?>">
                                                                <?= $info['is_active'] ? 'Active' : 'Inactive' ?>
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-warning btn-sm edit-company-info" 
                                                                data-id="<?= $info['id'] ?>" 
                                                                data-section="<?= htmlspecialchars($info['section_name']) ?>" 
                                                                data-content="<?= htmlspecialchars($info['content']) ?>">
                                                            Edit
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Forms Management -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Forms & Documents</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label>Form Name</label>
                                        <input type="text" name="form_name" class="form-control" placeholder="Enter form name" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="description" class="form-control" rows="3" placeholder="Optional description"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Form File</label>
                                        <input type="file" name="form_file" class="form-control" required>
                                        <small class="form-text text-muted">Upload PDF, DOC, DOCX files</small>
                                    </div>
                                    <button type="submit" name="add_form" class="btn btn-primary">Upload Form</button>
                                </form>

                                <hr>
                                <h5>Current Forms</h5>
                                <div class="table-responsive">
                                    <table id="formsTable" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Form Name</th>
                                                <th>Description</th>
                                                <th>File Name</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($company_forms as $form): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($form['form_name']) ?></td>
                                                    <td>
                                                        <?php if (!empty($form['description'])): ?>
                                                            <button type="button" class="btn btn-info btn-sm view-description" 
                                                                    data-form="<?= htmlspecialchars($form['form_name']) ?>" 
                                                                    data-description="<?= htmlspecialchars($form['description']) ?>">
                                                                View Description
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted">No description</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars(basename($form['file_path'])) ?></td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="table" value="company_forms">
                                                            <input type="hidden" name="id" value="<?= $form['id'] ?>">
                                                            <input type="hidden" name="current_status" value="<?= $form['is_active'] ?>">
                                                            <button type="submit" name="toggle_status" class="btn btn-sm <?= $form['is_active'] ? 'btn-success' : 'btn-secondary' ?>">
                                                                <?= $form['is_active'] ? 'Active' : 'Inactive' ?>
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this form?');">
                                                            <input type="hidden" name="id" value="<?= $form['id'] ?>">
                                                            <button type="submit" name="delete_form" class="btn btn-danger btn-sm">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">View Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Image View Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Image Preview</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="" class="img-fluid" style="max-height: 70vh;">
                <div id="imageCaption" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<!-- DataTables JavaScript -->
<script>
function viewImage(imagePath, caption) {
    $('#modalImage').attr('src', imagePath);
    $('#imageCaption').html('<strong>Caption:</strong> ' + (caption ? caption : 'No caption'));
    $('#imageModal').modal('show');
}

$(document).ready(function() {
    // Set admin theme
    setAdminTheme();
    
    function setAdminTheme() {
        localStorage.setItem('currentTheme', 'admin');
        $('body').addClass('theme-admin');
    }

    // Initialize DataTables with page length 5
    $('#carouselTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 5,
        "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        "language": {
            "search": "Search images:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ images",
            "infoEmpty": "Showing 0 to 0 of 0 images",
            "infoFiltered": "(filtered from _MAX_ total images)"
        }
    });

    $('#companyInfoTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 5,
        "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]]
    });

    $('#formsTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 5,
        "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]]
    });

    // Edit Company Information functionality
    $('.edit-company-info').click(function() {
        var id = $(this).data('id');
        var section = $(this).data('section');
        var content = $(this).data('content');
        
        $('#company_info_id').val(id);
        $('#section_name').val(section);
        $('#content').val(content);
        $('#cancelEdit').show();
        
        // Scroll to form
        $('html, body').animate({
            scrollTop: $("#companyInfoForm").offset().top - 100
        }, 500);
    });

    // Cancel edit
    $('#cancelEdit').click(function() {
        $('#company_info_id').val(0);
        $('#section_name').val('');
        $('#content').val('');
        $(this).hide();
    });

    // View Caption
    $(document).on('click', '.view-caption', function() {
        var caption = $(this).data('caption');
        $('#viewModalLabel').text('Caption Details');
        $('#modalContent').html('<p><strong>Caption:</strong></p><p style="font-size: 16px; line-height: 1.6;">' + caption + '</p>');
        $('#viewModal').modal('show');
    });

    // View Content
    $(document).on('click', '.view-content', function() {
        var section = $(this).data('section');
        var content = $(this).data('content');
        $('#viewModalLabel').text('Content - ' + section);
        $('#modalContent').html('<p><strong>Section:</strong> ' + section + '</p><p><strong>Content:</strong></p><div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f8f9fa;">' + content.replace(/\n/g, '<br>') + '</div>');
        $('#viewModal').modal('show');
    });

    // View Description
    $(document).on('click', '.view-description', function() {
        var formName = $(this).data('form');
        var description = $(this).data('description');
        $('#viewModalLabel').text('Form Description - ' + formName);
        $('#modalContent').html('<p><strong>Form Name:</strong> ' + formName + '</p><p><strong>Description:</strong></p><p style="font-size: 16px; line-height: 1.6;">' + description + '</p>');
        $('#viewModal').modal('show');
    });
});
</script>
</body>
</html>