<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/document_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();
$documentFunctions = new DocumentFunctions();

$document_id = $_GET['id'] ?? 0;
$document = $documentFunctions->getDocumentById($document_id);
$actions = $documentFunctions->getDocumentActions($document_id);

if (!$document) {
    $_SESSION['error_message'] = "Document not found";
    header('Location: received_documents.php');
    exit();
}

// Check if user has permission to view this document
$current_user_id = $_SESSION['emp_id'];
$current_user_section = $documentFunctions->getCurrentUserSection($current_user_id);

if ($document['received_by'] != $current_user_id && $document['to_section_id'] != $current_user_section['section_id']) {
    $_SESSION['error_message'] = "You don't have permission to view this document";
    header('Location: received_documents.php');
    exit();
}

$document_counts = $documentFunctions->getDocumentCounts();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Details - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .document-header {
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .info-card {
            border-left: 4px solid #556b2f;
            background: #f8f9fa;
        }
        .action-timeline {
            border-left: 3px solid #556b2f;
            margin-left: 20px;
        }
        .timeline-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #556b2f;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php include '../includes/mainheader.php'; ?>
        <?php include '../includes/sidebar_document.php'; ?>
        <div class="content-wrapper">
            <div class="content">
                <!-- Document Header -->
                <div class="document-header">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="mb-2"><?= $document['document_name'] ?></h4>
                            <p class="mb-1">Tracking No: <strong><?= $document['tracking_no'] ?></strong></p>
                            <p class="mb-0">Document Type: <?= ucfirst($document['document_type']) ?> - <?= $document['type_of_document'] ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php
                            $status_class = '';
                            switch($document['status']) {
                                case 'pending': $status_class = 'bg-warning'; break;
                                case 'in_progress': $status_class = 'bg-info'; break;
                                case 'completed': $status_class = 'bg-success'; break;
                                case 'forwarded': $status_class = 'bg-primary'; break;
                                case 'needs_clarification': $status_class = 'bg-danger'; break;
                                case 'cancelled': $status_class = 'bg-secondary'; break;
                            }
                            ?>
                            <span class="badge <?= $status_class ?> fs-6"><?= ucfirst(str_replace('_', ' ', $document['status'])) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Document Information -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card info-card">
                            <div class="card-header bg-transparent">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Document Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>From:</strong></td>
                                        <td><?= $document['from_section_name'] ?? $document['from_section'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>To:</strong></td>
                                        <td><?= $document['section_name'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>For Signature:</strong></td>
                                        <td><?= $document['for_signature'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Received By:</strong></td>
                                        <td><?= $document['receiver_first'] . ' ' . $document['receiver_last'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Created By:</strong></td>
                                        <td><?= $document['creator_first'] . ' ' . $document['creator_last'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date Created:</strong></td>
                                        <td><?= date('M j, Y g:i A', strtotime($document['created_at'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card info-card">
                            <div class="card-header bg-transparent">
                                <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Remarks</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($document['remarks']): ?>
                                    <p><?= nl2br(htmlspecialchars($document['remarks'])) ?></p>
                                <?php else: ?>
                                    <p class="text-muted">No remarks provided</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Timeline -->
                <div class="card">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Document History & Actions</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($actions)): ?>
                            <p class="text-muted">No actions recorded for this document yet.</p>
                        <?php else: ?>
                            <div class="action-timeline">
                                <?php foreach ($actions as $action): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-1"><?= ucfirst(str_replace('_', ' ', $action['action'])) ?></h6>
                                            <small class="text-muted"><?= date('M j, Y g:i A', strtotime($action['action_date'])) ?></small>
                                        </div>
                                        <p class="mb-1"><strong>By:</strong> <?= $action['first_name'] . ' ' . $action['last_name'] ?></p>
                                        <?php if ($action['remarks']): ?>
                                            <p class="mb-0"><strong>Remarks:</strong> <?= nl2br(htmlspecialchars($action['remarks'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <?php if ($document['received_by'] == $current_user_id && $document['status'] != 'completed' && $document['status'] != 'cancelled'): ?>
                    <div class="mt-4 text-center">
                        <?php if ($document['status'] == 'pending'): ?>
                            <a href="received_documents.php" class="btn btn-success">
                                <i class="fas fa-check me-2"></i>Acknowledge Document
                            </a>
                        <?php elseif ($document['status'] == 'in_progress'): ?>
                            <a href="received_documents.php" class="btn btn-primary me-2">
                                <i class="fas fa-flag-checkered me-2"></i>Mark Complete
                            </a>
                            <a href="received_documents.php" class="btn btn-warning">
                                <i class="fas fa-question me-2"></i>Need Clarification
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include '../includes/mainfooter.php'; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>