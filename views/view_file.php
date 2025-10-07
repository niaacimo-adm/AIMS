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

// Get file ID from request
$file_id = isset($_GET['id']) ? $_GET['id'] : (isset($_POST['file_id']) ? $_POST['file_id'] : null);

if (!$file_id) {
    die('File ID is required');
}

// Fetch file details
$stmt = $db->prepare("SELECT f.*, 
                             CONCAT(e.first_name, ' ', e.last_name) as uploaded_by_name,
                             fl.folder_name, s.section_name
                      FROM files f
                      LEFT JOIN employee e ON f.uploaded_by = e.emp_id
                      LEFT JOIN folders fl ON f.folder_id = fl.folder_id
                      LEFT JOIN section s ON f.section_id = s.section_id
                      WHERE f.file_id = ?");
$stmt->bind_param("i", $file_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('File not found');
}

$file = $result->fetch_assoc();

// Handle file update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_file') {
    // Check if user has edit permission for this file's folder
    $user_emp_id = $_SESSION['user_emp_id'] ?? $_SESSION['user_id'];
    
    if ($file['folder_id'] && !hasFolderPermission($db, $file['folder_id'], $user_emp_id, 'edit')) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this file.']);
        exit();
    }
    
    $file_name = trim($_POST['file_name']);
    $description = trim($_POST['description'] ?? '');
    
    // Update file record
    $update_stmt = $db->prepare("UPDATE files SET file_name = ?, description = ? WHERE file_id = ?");
    $update_stmt->bind_param("ssi", $file_name, $description, $file_id);
    
    if ($update_stmt->execute()) {
        // Log activity
        $log_stmt = $db->prepare("INSERT INTO file_activity_logs (file_id, emp_id, activity_type, description, ip_address) 
                                VALUES (?, ?, 'updated', ?, ?)");
        $log_description = "File '{$file_name}' updated";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("iiss", $file_id, $user_emp_id, $log_description, $ip);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'File updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update file: ' . $db->error]);
    }
    exit();
}

// If it's an AJAX request for modal, return JSON
if (isset($_GET['ajax']) && $_GET['ajax'] == 'true') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'file' => [
            'file_id' => $file['file_id'],
            'file_name' => $file['file_name'],
            'file_type' => $file['file_type'],
            'file_size' => $file['file_size'],
            'description' => $file['description'] ?? '',
            'uploaded_by' => $file['uploaded_by_name'],
            'created_at' => $file['created_at'],
            'folder_name' => $file['folder_name'] ?? 'Root',
            'section_name' => $file['section_name'] ?? 'Manager\'s Office'
        ]
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($file['file_name']) ?> - View File</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .file-preview {
            max-width: 100%;
            margin: 20px 0;
        }
        .file-info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .file-icon-large {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        .preview-container {
            text-align: center;
            padding: 20px;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            margin: 20px 0;
        }
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
                        <h1 class="m-0">View File</h1>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="<?= getFileIconClass($file['file_type']) ?> mr-2"></i>
                                    <?= htmlspecialchars($file['file_name']) ?>
                                </h3>
                                <div class="card-tools">
                                    <a href="download_file.php?id=<?= $file['file_id'] ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-download mr-1"></i> Download
                                    </a>
                                    <button class="btn btn-primary btn-sm ml-1" onclick="editFile()">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                    <button class="btn btn-secondary btn-sm ml-1" onclick="window.history.back()">
                                        <i class="fas fa-arrow-left mr-1"></i> Back
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="preview-container">
                                    <?= getFilePreview($file) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="file-info-card">
                            <h5>File Information</h5>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td><?= htmlspecialchars($file['file_name']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td><?= strtoupper($file['file_type']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Size:</strong></td>
                                    <td><?= formatFileSize($file['file_size']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Uploaded By:</strong></td>
                                    <td><?= htmlspecialchars($file['uploaded_by_name']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Date:</strong></td>
                                    <td><?= date('M j, Y H:i', strtotime($file['created_at'])) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Location:</strong></td>
                                    <td><?= htmlspecialchars($file['folder_name'] ?? 'Root') ?></td>
                                </tr>
                                <?php if (!empty($file['description'])): ?>
                                <tr>
                                    <td><strong>Description:</strong></td>
                                    <td><?= htmlspecialchars($file['description']) ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Edit File Modal -->
<div class="modal fade" id="editFileModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit File</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editFileForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editFileName">File Name</label>
                        <input type="text" class="form-control" id="editFileName" name="file_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editFileDescription">Description</label>
                        <textarea class="form-control" id="editFileDescription" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" name="file_id" value="<?= $file['file_id'] ?>">
                    <input type="hidden" name="action" value="update_file">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update File</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
function editFile() {
    // Populate the edit form with current data
    $('#editFileName').val('<?= addslashes($file['file_name']) ?>');
    $('#editFileDescription').val('<?= addslashes($file['description'] ?? '') ?>');
    $('#editFileModal').modal('show');
}

// Handle edit form submission
$('#editFileForm').submit(function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'view_file.php',
        type: 'POST',
        data: $(this).serialize(),
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    $('#editFileModal').modal('hide');
                    Swal.fire({
                        title: 'Success!',
                        text: result.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', result.message, 'error');
                }
            } catch (e) {
                Swal.fire('Error!', 'Invalid server response', 'error');
            }
        }
    });
});
</script>
</body>
</html>

<?php
// Helper functions for file preview and icons
function getFileIconClass($fileType) {
    $type = strtolower($fileType);
    $icons = [
        'pdf' => 'fas fa-file-pdf text-danger',
        'doc' => 'fas fa-file-word text-primary',
        'docx' => 'fas fa-file-word text-primary',
        'xls' => 'fas fa-file-excel text-success',
        'xlsx' => 'fas fa-file-excel text-success',
        'ppt' => 'fas fa-file-powerpoint text-warning',
        'pptx' => 'fas fa-file-powerpoint text-warning',
        'jpg' => 'fas fa-file-image text-info',
        'jpeg' => 'fas fa-file-image text-info',
        'png' => 'fas fa-file-image text-info',
        'gif' => 'fas fa-file-image text-info',
        'zip' => 'fas fa-file-archive text-secondary',
        'rar' => 'fas fa-file-archive text-secondary',
        'txt' => 'fas fa-file-alt text-dark',
        'mp4' => 'fas fa-file-video text-danger',
        'avi' => 'fas fa-file-video text-danger',
        'mov' => 'fas fa-file-video text-danger',
        'mp3' => 'fas fa-file-audio text-info'
    ];
    
    return $icons[$type] ?? 'fas fa-file text-secondary';
}

function getFilePreview($file) {
    $type = strtolower($file['file_type']);
    $filePath = '../uploads/' . $file['file_path'];
    
    switch ($type) {
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return '<img src="' . $filePath . '" class="img-fluid" alt="' . htmlspecialchars($file['file_name']) . '" style="max-height: 500px;">';
        
        case 'pdf':
            return '<iframe src="' . $filePath . '" width="100%" height="600px" style="border: none;"></iframe>';
        
        case 'txt':
            if (file_exists($filePath) && filesize($filePath) < 102400) { // 100KB limit
                $content = htmlspecialchars(file_get_contents($filePath));
                return '<pre class="text-left p-3 bg-light" style="max-height: 400px; overflow: auto;">' . $content . '</pre>';
            }
            return '<p class="text-muted">File content too large to preview. Please download to view.</p>';
        
        default:
            return '<div class="file-icon-large ' . getFileIconClass($file['file_type']) . '"></div>' .
                   '<p class="text-muted">Preview not available for this file type.</p>' .
                   '<p><a href="download_file.php?id=' . $file['file_id'] . '" class="btn btn-primary">Download File</a></p>';
    }
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>