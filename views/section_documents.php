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

// Get current user's section
$current_user_section = $documentFunctions->getCurrentUserSection($_SESSION['emp_id']);
$document_type = $_GET['type'] ?? 'incoming';

// Validate document type
if (!in_array($document_type, ['incoming', 'outgoing', 'internal'])) {
    $document_type = 'incoming';
}

// Get documents for current user's section
$documents = [];
if ($current_user_section) {
    $query = "SELECT d.*, s.section_name, s.section_code, 
                     creator.first_name as creator_first, creator.last_name as creator_last,
                     receiver.first_name as receiver_first, receiver.last_name as receiver_last
              FROM document_monitoring d
              LEFT JOIN section s ON d.to_section_id = s.section_id
              LEFT JOIN employee creator ON d.created_by = creator.emp_id
              LEFT JOIN employee receiver ON d.received_by = receiver.emp_id
              WHERE d.document_type = ? AND d.to_section_id = ?
              ORDER BY d.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("si", $document_type, $current_user_section['section_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
}

$document_counts = $documentFunctions->getDocumentCounts();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($document_type) ?> Documents - <?= $current_user_section['section_name'] ?? 'My Section' ?> - NIA ACIMO</title>
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
        .badge-admin { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); color: white; }
        .badge-finance { background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: white; }
        .badge-engineering { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); color: white; }
        .badge-oms { background: linear-gradient(135deg, #f97316 0%, #c2410c 100%); color: white; }
        .badge-imo { background: linear-gradient(135deg, #8b4513 0%, #654321 100%); color: white; }
        
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
        .section-header {
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php include '../includes/mainheader.php'; ?>
        <?php include '../includes/sidebar_document.php'; ?>
        <div class="content-wrapper">
            <div class="content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1 fw-bold text-gradient">
                            <?= ucfirst($document_type) ?> Documents - <?= $current_user_section['section_name'] ?? 'My Section' ?>
                        </h2>
                        <p class="text-muted">Documents specifically for your section</p>
                    </div>
                    <div>
                        <a href="document_monitoring.php" class="btn btn-olive">
                            <i class="fas fa-plus me-2"></i> Create New Document
                        </a>
                    </div>
                </div>

                <!-- Section Info Card -->
                <div class="section-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-1">
                                <i class="fas fa-building me-2"></i>
                                <?= $current_user_section['section_name'] ?> (<?= $current_user_section['section_code'] ?>)
                            </h5>
                            <p class="mb-0 opacity-8">Your assigned section documents</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group">
                                <a href="section_documents.php?type=incoming" class="btn btn-outline-light <?= $document_type == 'incoming' ? 'active' : '' ?>">
                                    Incoming
                                </a>
                                <a href="section_documents.php?type=outgoing" class="btn btn-outline-light <?= $document_type == 'outgoing' ? 'active' : '' ?>">
                                    Outgoing
                                </a>
                                <a href="section_documents.php?type=internal" class="btn btn-outline-light <?= $document_type == 'internal' ? 'active' : '' ?>">
                                    Internal
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="modern-card">
                    <div class="card-header bg-transparent border-bottom-0 py-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">
                                <i class="fas fa-<?= $document_type == 'incoming' ? 'inbox' : ($document_type == 'outgoing' ? 'paper-plane' : 'exchange-alt') ?> me-2 text-gradient"></i>
                                <?= ucfirst($document_type) ?> Documents for <?= $current_user_section['section_code'] ?>
                                <span class="badge bg-olive rounded-pill ms-2"><?= count($documents) ?></span>
                            </h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-modern table-hover" id="sectionDocumentsTable">
                                <thead>
                                    <tr>
                                        <th>Tracking No.</th>
                                        <th>Document Type</th>
                                        <th>Document</th>
                                        <th>From</th>
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
                                            <td colspan="9" class="text-center py-5">
                                                <i class="fas fa-<?= $document_type == 'incoming' ? 'inbox' : ($document_type == 'outgoing' ? 'paper-plane' : 'exchange-alt') ?> fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No <?= $document_type ?> documents found for your section</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $doc): 
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
                                            <td><span class="fw-medium"><?= $doc['for_signature'] ?></span></td>
                                            <td><?= $doc['receiver_first'] . ' ' . $doc['receiver_last'] ?></td>
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
            $('#sectionDocumentsTable').DataTable({
                "order": [[6, "desc"]],
                "pageLength": 25,
                "language": {
                    "search": "<i class='fas fa-search'></i>",
                    "searchPlaceholder": "Search section documents..."
                }
            });
        });
    </script>
</body>
</html>