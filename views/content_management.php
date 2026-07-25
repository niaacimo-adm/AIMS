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

    // Handle delete form
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

        if ($table === 'company_forms') {
            $new_status = $current_status ? 0 : 1;
            $stmt = $db->prepare("UPDATE company_forms SET is_active = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_status, $id);

            if ($stmt->execute()) {
                $message = 'Status updated successfully!';
            } else {
                $error = 'Failed to update status.';
            }
        }
    }
}

// Fetch all forms
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
        .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }

        .modern-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);
        }

        .form-control-modern {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .form-control-modern:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            background: #ffffff;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .btn-modern {
            background: linear-gradient(135deg, #4f46e5 0%, #7c73e6 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.4);
            background: linear-gradient(135deg, #4338ca 0%, #6d63e0 100%);
            color: white;
        }

        .section-title {
            color: #4f46e5;
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eef2ff;
            position: relative;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: #4f46e5;
            border-radius: 2px;
        }

        .required-field::after {
            content: " *";
            color: #ef4444;
        }

        .file-drop-area {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s ease;
            background: #fafafa;
            cursor: pointer;
        }

        .file-drop-area:hover {
            border-color: #4f46e5;
            background: #f0f4ff;
        }

        .file-drop-area.dragover {
            border-color: #4f46e5;
            background: #f0f4ff;
            transform: scale(1.01);
        }

        .file-drop-area.error {
            border-color: #ef4444 !important;
            background: #fef2f2 !important;
        }

        .file-drop-area .file-name {
            font-weight: 600;
            color: #4f46e5;
        }

        .validation-error {
            color: #ef4444;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .table th {
            background: linear-gradient(135deg, #4f46e5, #7c73e6);
            color: white;
            font-weight: 600;
            border: none;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(79, 70, 229, 0.05);
        }

        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .alert-success {
            border-left: 4px solid #28a745;
            background-color: #f8fff9;
        }

        .alert-danger {
            border-left: 4px solid #dc3545;
            background-color: #fff8f8;
        }

        .modal-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c73e6 100%);
            color: white;
        }

        .modal-header .close {
            color: white;
            opacity: 0.8;
        }

        .modal-header .close:hover {
            opacity: 1;
        }

        .modal-lg {
            max-width: 80%;
        }

        .dataTables_paginate .page-item.active .page-link {
            background: linear-gradient(135deg, #4f46e5, #7c73e6);
            border-color: #4f46e5;
        }

        .dataTables_paginate .page-link {
            color: #4f46e5;
        }

        .dataTables_paginate .page-link:hover {
            background-color: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }

        .dataTables_filter input:focus,
        .dataTables_length select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }

        /* ══ HERO — login-style animated mesh + orbs + rings ══ */
        @keyframes pgHeroMeshDrift {
            0%   { transform:translate(0,0)   rotate(0deg); }
            100% { transform:translate(3%,2%) rotate(2deg); }
        }
        @keyframes pgHeroOrbFloat {
            0%,100% { opacity:.4; transform:translate(0,0)       scale(1);    }
            33%      { opacity:.7; transform:translate(18px,-26px) scale(1.05); }
            66%      { opacity:.5; transform:translate(-12px,16px) scale(.95);  }
        }
        @keyframes pgHeroRingPulse {
            0%,100% { opacity:.45; transform:scale(1);    }
            50%      { opacity:.85; transform:scale(1.04); }
        }
        .pg-hero {
            background:#0b1f17;
            padding:36px 28px 66px; position:relative; overflow:hidden;
        }
        .pg-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:pgHeroMeshDrift 22s ease-in-out infinite alternate;
            z-index:0;
        }
        .pg-hero-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .pg-orb { position:absolute; border-radius:50%; filter:blur(60px); animation:pgHeroOrbFloat 18s ease-in-out infinite; }
        .pg-orb-1 { width:280px; height:280px; background:rgba(36,231,143,.11); top:-80px;    left:-60px;  animation-duration:21s; }
        .pg-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.10);  bottom:-50px; right:-40px; animation-delay:-7s; animation-duration:17s; }
        .pg-orb-3 { width:160px; height:160px; background:rgba(212,175,55,.06); top:40%;      right:20%;   animation-delay:-13s; animation-duration:24s; }
        .pg-orb-4 { width:120px; height:120px; background:rgba(36,231,143,.07); bottom:15%;   left:15%;    animation-delay:-4s;  animation-duration:15s; }
        .pg-hero-dots {
            position:absolute; inset:0; z-index:0; pointer-events:none;
            background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px);
            background-size:36px 36px;
        }
        .pg-hero-hex {
            position:absolute; inset:0; pointer-events:none; opacity:.045; z-index:0;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V16L28 0l28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V50l28-16 28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3C/svg%3E");
            background-size:56px 100px;
        }
        .pg-hero-rings {
            position:absolute; top:50%; right:6%;
            transform:translateY(-50%);
            width:240px; height:240px; pointer-events:none; z-index:0;
        }
        .pg-ring {
            position:absolute; inset:0; border-radius:50%;
            border:1px solid rgba(36,231,143,.10);
            animation:pgHeroRingPulse 4s ease-in-out infinite;
        }
        .pg-ring:nth-child(2) { inset:28px; animation-delay:.8s;  opacity:.7; }
        .pg-ring:nth-child(3) { inset:56px; animation-delay:1.6s; opacity:.5; }
        .pg-hero-arc {
            position:absolute; top:-50px; right:-50px;
            width:200px; height:200px; border-radius:50%;
            background:radial-gradient(circle,rgba(36,231,143,.18) 0%,transparent 70%);
            pointer-events:none; z-index:0;
        }
        .pg-hero::after {
            content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px;
            background:var(--body-bg, #eef7f2); clip-path:ellipse(58% 100% at 50% 100%); z-index:1;
        }
        body.dark-mode .pg-hero::after { background:var(--body-bg, #0b1f17); }
        .pg-hero-inner { position:relative; z-index:2; }
        .pg-hero-title {
            color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px;
            letter-spacing:-.3px; text-shadow:0 2px 14px rgba(0,0,0,.45);
            display:flex; align-items:center; gap:10px;
        }
        .pg-hero-sub  { color:rgba(212,245,229,.75); margin:0 0 14px; font-size:.9rem; }
        .pg-hero-divider {
            width:48px; height:2px; border-radius:2px; margin:0 0 12px;
            background:linear-gradient(90deg,transparent,#24e78f,transparent);
        }
        .pg-hero-layout {
            display:flex; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap; gap:14px; position:relative; z-index:2;
        }
        .mh-logo-watermark {
            position:absolute; top:50%; right:3%;
            transform:translateY(-50%);
            width:180px; height:auto; pointer-events:none; z-index:0;
            opacity:0.50;
        }

        /* =========================================================
           DARK MODE OVERRIDES — applied via body.dark-mode
           ========================================================= */
        body.dark-mode { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
        body.dark-mode .content-wrapper { background-color: var(--body-bg) !important; color: var(--text-primary) !important; }
        body.dark-mode .card { background: var(--card-bg) !important; border-color: var(--card-border) !important; color: var(--text-primary) !important; }
        body.dark-mode .card-body { background: var(--card-bg) !important; color: var(--text-primary) !important; }
        body.dark-mode .modal-content { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
        body.dark-mode .modal-body { background: var(--modal-bg) !important; color: var(--text-primary) !important; }
        body.dark-mode .modal-footer { background: var(--modal-bg) !important; border-color: var(--card-border) !important; }
        body.dark-mode .table { background: var(--table-bg) !important; color: var(--text-primary) !important; }
        body.dark-mode .table thead th { background: var(--table-stripe) !important; color: var(--text-primary) !important; border-color: var(--table-border) !important; }
        body.dark-mode .table td, body.dark-mode .table th { border-color: var(--table-border) !important; color: var(--text-primary) !important; }
        body.dark-mode .table-striped tbody tr:nth-of-type(odd) { background: var(--table-stripe) !important; }
        body.dark-mode .table-hover tbody tr:hover { background: var(--notification-unread-bg) !important; }
        body.dark-mode .table-bordered { border-color: var(--table-border) !important; }
        body.dark-mode .form-control { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
        body.dark-mode .form-control:focus { border-color: #5a7fa8 !important; box-shadow: 0 0 0 0.2rem rgba(90,127,168,.25) !important; }
        body.dark-mode label, body.dark-mode .form-label { color: var(--text-primary) !important; }
        body.dark-mode .text-muted { color: var(--text-muted) !important; }
        body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 { color: var(--text-primary) !important; }
        body.dark-mode .alert { border-color: var(--card-border) !important; }
        body.dark-mode .alert-success { background: #1a2e1e !important; color: #86efac !important; }
        body.dark-mode .alert-danger { background: #2e1515 !important; color: #fca5a5 !important; }
        body.dark-mode .page-item .page-link { background: var(--card-bg) !important; color: var(--text-primary) !important; border-color: var(--card-border) !important; }
        body.dark-mode .page-item.active .page-link { background: var(--sidebar-active-bg) !important; border-color: var(--sidebar-active-bg) !important; }
        body.dark-mode hr { border-color: var(--card-border) !important; }
        body.dark-mode .dataTables_wrapper { color: var(--text-primary) !important; }
        body.dark-mode .dataTables_filter input, body.dark-mode .dataTables_length select { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
        body.dark-mode .dataTables_info { color: var(--text-muted) !important; }
        body.dark-mode .modern-card { background: var(--card-bg) !important; color: var(--text-primary) !important; }
        body.dark-mode .form-control-modern { background: var(--input-bg) !important; color: var(--input-color) !important; border-color: var(--input-border) !important; }
        body.dark-mode .form-control-modern:focus { background: var(--input-bg) !important; }
        body.dark-mode .file-drop-area { background: var(--table-stripe) !important; border-color: var(--input-border) !important; }
        body.dark-mode .file-drop-area:hover { background: var(--notification-unread-bg) !important; }
        body.dark-mode .section-title { color: #7aabdf !important; border-color: var(--card-border) !important; }
        body.dark-mode .section-title:after { background: #7aabdf !important; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <!-- Page Hero -->
        <div class="pg-hero">
            <div class="pg-hero-mesh"></div>
            <div class="pg-hero-dots"></div>
            <div class="pg-hero-hex"></div>
            <div class="pg-hero-orbs">
                <div class="pg-orb pg-orb-1"></div>
                <div class="pg-orb pg-orb-2"></div>
                <div class="pg-orb pg-orb-3"></div>
                <div class="pg-orb pg-orb-4"></div>
            </div>
            <div class="pg-hero-rings">
                <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
            </div>
            <div class="pg-hero-arc"></div>
            <div class="pg-hero-layout">
                <div class="pg-hero-inner">
                    <div class="pg-hero-title"><i class="fas fa-paperclip"></i>Content Management</div>
                    <div class="pg-hero-divider"></div>
                    <p class="pg-hero-sub">Manage forms and documents available on the landing page</p>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- Forms & Documents Management -->
                <div class="row">
                    <!-- Upload Form -->
                    <div class="col-md-6">
                        <div class="card modern-card">
                            <div class="card-header" style="background: linear-gradient(135deg, #4f46e5 0%, #7c73e6 100%); border-radius: 12px 12px 0 0;">
                                <h3 class="card-title" style="font-weight: 600;">
                                    <i class="fas fa-file-alt mr-2"></i>Forms &amp; Documents
                                </h3>
                            </div>
                            <div class="card-body" style="padding: 30px;">
                                <h4 class="section-title">Upload New Form</h4>
                                <form method="POST" enctype="multipart/form-data" id="formUploadForm">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <label for="form_name" class="form-label required-field">Form Name</label>
                                            <input type="text" id="form_name" name="form_name" class="form-control form-control-modern" placeholder="Enter form name" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea id="description" name="description" class="form-control form-control-modern" rows="2" placeholder="Optional description"></textarea>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <label class="form-label required-field">Form File</label>
                                            <div class="file-drop-area" id="fileDropArea" onclick="document.getElementById('form_file').click()">
                                                <input type="file" class="d-none" id="form_file" name="form_file" accept=".pdf,.doc,.docx" onchange="previewFormFile(this.files)">
                                                <div id="fileUploadPlaceholder">
                                                    <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #9ca3af;"></i>
                                                    <p class="mb-1" style="color: #6b7280; font-weight: 500;">Click to upload or drag and drop</p>
                                                    <p class="small text-muted mb-0">PDF, DOC, DOCX up to 10MB</p>
                                                </div>
                                                <div id="fileSelectedPreview" style="display: none;">
                                                    <i class="fas fa-file-alt fa-3x mb-2" style="color: #4f46e5;"></i>
                                                    <p class="file-name mb-1" id="fileNameDisplay"></p>
                                                    <button type="button" class="btn btn-outline-danger btn-sm mt-2" onclick="removeFormFile(event)">
                                                        <i class="fas fa-trash mr-1"></i> Remove File
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="fileError" class="validation-error">
                                                <i class="fas fa-exclamation-circle mr-1"></i>Please select a form file
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 text-right">
                                            <button type="submit" name="add_form" class="btn btn-modern">
                                                <i class="fas fa-upload mr-2"></i>Upload Form
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Current Forms -->
                    <div class="col-md-6">
                        <div class="card modern-card">
                            <div class="card-header" style="background: linear-gradient(135deg, #4f46e5 0%, #7c73e6 100%); border-radius: 12px 12px 0 0;">
                                <h3 class="card-title" style="font-weight: 600;">
                                    <i class="fas fa-list mr-2"></i>Current Forms
                                </h3>
                            </div>
                            <div class="card-body" style="padding: 30px;">
                                <div class="table-responsive">
                                    <table id="formsTable" class="table table-bordered table-striped table-hover">
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
                                                        <form method="POST" class="d-inline delete-form-confirm">
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

<?php include '../includes/footer.php'; ?>
<!-- DataTables JavaScript -->
<script>
$(document).ready(function() {
    // Set admin theme
    setAdminTheme();

    function setAdminTheme() {
        localStorage.setItem('currentTheme', 'admin');
        $('body').addClass('theme-admin');
    }

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

    // View Description
    $(document).on('click', '.view-description', function() {
        var formName = $(this).data('form');
        var description = $(this).data('description');
        $('#viewModalLabel').text('Form Description - ' + formName);
        $('#modalContent').html('<p><strong>Form Name:</strong> ' + formName + '</p><p><strong>Description:</strong></p><p style="font-size: 16px; line-height: 1.6;">' + description + '</p>');
        $('#viewModal').modal('show');
    });

    // Success / Error feedback via SweetAlert2
    <?php if ($message): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: <?= json_encode($message) ?>,
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true,
            confirmButtonColor: '#4f46e5'
        });
    <?php endif; ?>

    <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: <?= json_encode($error) ?>,
            confirmButtonColor: '#4f46e5'
        });
    <?php endif; ?>

    // Delete confirmation via SweetAlert2
    $(document).on('submit', '.delete-form-confirm', function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            icon: 'warning',
            title: 'Delete this form?',
            text: 'This action cannot be undone.',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6b7280'
        }).then(function(result) {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
<!-- Form File Drag & Drop -->
<script>
    var fileDropArea = document.getElementById('fileDropArea');
    var formFileInput = document.getElementById('form_file');

    // Show the selected file's name and swap to the "selected" state
    function previewFormFile(files) {
        var uploadPlaceholder = document.getElementById('fileUploadPlaceholder');
        var selectedPreview = document.getElementById('fileSelectedPreview');
        var fileNameDisplay = document.getElementById('fileNameDisplay');
        var fileError = document.getElementById('fileError');

        if (files && files.length > 0) {
            fileNameDisplay.textContent = files[0].name;
            uploadPlaceholder.style.display = 'none';
            selectedPreview.style.display = 'block';

            fileError.style.display = 'none';
            fileDropArea.classList.remove('error');
        }
    }

    // Clear the selected file and go back to the upload placeholder
    function removeFormFile(event) {
        event.stopPropagation();

        var uploadPlaceholder = document.getElementById('fileUploadPlaceholder');
        var selectedPreview = document.getElementById('fileSelectedPreview');

        formFileInput.value = '';
        selectedPreview.style.display = 'none';
        uploadPlaceholder.style.display = 'block';
    }

    // Drag and drop support
    ['dragenter', 'dragover'].forEach(function(eventName) {
        fileDropArea.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileDropArea.classList.add('dragover');
        });
    });

    ['dragleave', 'dragend'].forEach(function(eventName) {
        fileDropArea.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileDropArea.classList.remove('dragover');
        });
    });

    fileDropArea.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileDropArea.classList.remove('dragover');

        var droppedFiles = e.dataTransfer.files;
        if (droppedFiles && droppedFiles.length > 0) {
            formFileInput.files = droppedFiles;
            previewFormFile(droppedFiles);
        }
    });

    // Validate a file was chosen before allowing submit
    document.getElementById('formUploadForm').addEventListener('submit', function(e) {
        var fileError = document.getElementById('fileError');

        if (!formFileInput.files || formFileInput.files.length === 0) {
            e.preventDefault();
            fileError.style.display = 'block';
            fileDropArea.classList.add('error');
            fileDropArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
</body>
</html>