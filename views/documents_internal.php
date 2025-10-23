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
$documents = $documentFunctions->getDocumentsByType('internal');
$document_counts = $documentFunctions->getDocumentCounts();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Documents - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .modern-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        .table-modern {
            border-radius: 12px;
            overflow: hidden;
            background: #f8f9fa;
        }
        .table-modern thead th {
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            color: #f8f9fa;
            border: none;
            font-weight: 600;
            padding: 15px 12px;
        }
        .table-modern tbody tr {
            transition: all 0.3s ease;
            background: #ffffff;
        }
        .table-modern tbody tr:hover {
            background-color: rgba(85, 107, 47, 0.05);
            transform: translateX(5px);
        }
        .section-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .badge-admin { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); color: white; } /* Blue */
        .badge-finance { background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: white; } /* Green */
        .badge-engineering { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); color: white; } /* Red */
        .badge-oms { background: linear-gradient(135deg, #f97316 0%, #c2410c 100%); color: white; } /* Orange */
        .btn-olive {
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            border: none;
            border-radius: 10px;
            color: #f8f9fa;
            font-weight: 500;
            padding: 12px 25px;
        }

        .btn-olive:hover {
            background: linear-gradient(135deg, #465823 0%, #1a1a1a 100%);
            color: #f8f9fa;
        }
        .text-gradient {
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php include '../includes/mainheader.php'; ?>
        <?php include '../includes/sidebar_document.php'; ?>
        <div class="content-wrapper">
            <!-- Main Content -->
            <div class="content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1 fw-bold text-gradient">Internal Communication Documents</h2>
                        <p class="text-muted">Manage all internal communication documents</p>
                    </div>
                    <div>
                        <a href="document_monitoring.php" class="btn btn-olive">
                            <i class="fas fa-plus me-2"></i> Create New Document
                        </a>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="modern-card">
                    <div class="card-header bg-transparent border-bottom-0 py-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">
                                <i class="fas fa-exchange-alt me-2 text-gradient"></i> Internal Communication Documents 
                                <span class="badge bg-olive rounded-pill ms-2"><?= count($documents) ?></span>
                            </h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-modern table-hover" id="internalDocumentsTable">
                                <thead>
                                    <tr>
                                        <th>Tracking No.</th>
                                        <th>Document Type</th>
                                        <th>Document</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Signature</th>
                                        <th>Received By</th>
                                        <th>Date Created</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($documents)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-5">
                                                <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No internal communication documents found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $doc): 
                                            $section_color = '';
                                            switch($doc['section_code']) {
                                                case 'ADM': $section_color = 'badge-admin'; break;
                                                case 'FIN': $section_color = 'badge-finance'; break;
                                                case 'ENG': $section_color = 'badge-engineering'; break;
                                                case 'OMS': $section_color = 'badge-oms'; break;
                                            }
                                            
                                            $status_class = '';
                                            switch($doc['status']) {
                                                case 'pending': $status_class = 'bg-warning'; break;
                                                case 'in_progress': $status_class = 'bg-info'; break;
                                                case 'completed': $status_class = 'bg-success'; break;
                                                case 'cancelled': $status_class = 'bg-danger'; break;
                                            }
                                        ?>
                                        <tr>
                                            <td><span class="fw-bold text-olive"><?= $doc['tracking_no'] ?></span></td>
                                            <td><?= $doc['type_of_document'] ?></td>
                                            <td class="fw-medium"><?= $doc['document_name'] ?></td>
                                            <td><?= $doc['from_section'] ?></td>
                                            <td><span class="section-badge <?= $section_color ?>"><?= $doc['section_name'] ?></span></td>
                                            <td><span class="fw-medium"><?= $doc['for_signature'] ?></span></td>
                                            <td><?= $doc['creator_first'] . ' ' . $doc['creator_last'] ?></td>
                                            <td><span class="text-muted"><?= date('M j, Y g:i A', strtotime($doc['created_at'])) ?></span></td>
                                            <td><span class="badge <?= $status_class ?> rounded-pill"><?= ucfirst(str_replace('_', ' ', $doc['status'])) ?></span></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-olive rounded view-document" data-id="<?= $doc['document_id'] ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-success rounded edit-document" data-id="<?= $doc['document_id'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../includes/mainfooter.php'; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#internalDocumentsTable').DataTable({
                "order": [[7, "desc"]],
                "pageLength": 25
            });
        });
    </script>
</body>
</html>