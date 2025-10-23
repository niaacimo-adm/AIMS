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
$document_counts = $documentFunctions->getDocumentCounts();

// Get recent documents
$recent_documents = [];
$incoming_docs = $documentFunctions->getDocumentsByType('incoming');
$outgoing_docs = $documentFunctions->getDocumentsByType('outgoing');
$internal_docs = $documentFunctions->getDocumentsByType('internal');

$recent_documents = array_merge(
    array_slice($incoming_docs, 0, 3),
    array_slice($outgoing_docs, 0, 3),
    array_slice($internal_docs, 0, 3)
);

// Sort by creation date
usort($recent_documents, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$recent_documents = array_slice($recent_documents, 0, 10);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Dashboard - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .modern-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            background: #f8f9fa;
        }
        .modern-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .stat-card {
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            color: #f8f9fa;
            border-radius: 16px;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transform: rotate(30deg);
        }
        .stat-card.incoming { background: linear-gradient(135deg, #556b2f 0%, #3a4720 100%); }
        .stat-card.outgoing { background: linear-gradient(135deg, #465823 0%, #2b2b2b 100%); }
        .stat-card.internal { background: linear-gradient(135deg, #3a4720 0%, #556b2f 100%); }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
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
        .tracking-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #556b2f;
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
        .text-gradient {
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
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
    </style>
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php include '../includes/mainheader.php'; ?>
        <?php include '../includes/sidebar_document.php'; ?>
        <div class="content-wrapper">
            <div class="content">
                <!-- Navbar -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1 fw-bold text-gradient">Document Monitoring Dashboard</h2>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stat-card incoming">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="fw-bold display-6"><?= $document_counts['incoming'] ?></h3>
                                        <p class="mb-0 opacity-8">Incoming Documents</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-inbox"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card outgoing">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="fw-bold display-6"><?= $document_counts['outgoing'] ?></h3>
                                        <p class="mb-0 opacity-8">Outgoing Documents</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-paper-plane"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card internal">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="fw-bold display-6"><?= $document_counts['internal'] ?></h3>
                                        <p class="mb-0 opacity-8">Internal Documents</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-exchange-alt"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Documents -->
                <div class="modern-card">
                    <div class="card-header bg-transparent border-bottom-0 py-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">
                                <i class="fas fa-history me-2 text-gradient"></i> Recent Documents
                            </h5>
                            <span class="badge bg-olive rounded-pill"><?= count($recent_documents) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-modern table-hover">
                                <thead>
                                    <tr>
                                        <th>Tracking No.</th>
                                        <th>Type</th>
                                        <th>Document</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Signature</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_documents)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No documents found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_documents as $doc): 
                                            $badge_class = '';
                                            switch($doc['document_type']) {
                                                case 'incoming': $badge_class = 'bg-olive'; break;
                                                case 'outgoing': $badge_class = 'bg-success'; break;
                                                case 'internal': $badge_class = 'bg-info'; break;
                                            }
                                            
                                            $section_color = '';
                                            switch($doc['section_code']) {
                                                case 'ADM': $section_color = 'badge-admin'; break;
                                                case 'FIN': $section_color = 'badge-finance'; break;
                                                case 'ENG': $section_color = 'badge-engineering'; break;
                                                case 'OMS': $section_color = 'badge-oms'; break;
                                            }
                                        ?>
                                        <tr>
                                            <td><span class="tracking-number"><?= $doc['tracking_no'] ?></span></td>
                                            <td><span class="badge <?= $badge_class ?> rounded-pill"><?= ucfirst($doc['document_type']) ?></span></td>
                                            <td class="fw-medium"><?= $doc['document_name'] ?></td>
                                            <td><?= $doc['from_section'] ?></td>
                                            <td><span class="section-badge <?= $section_color ?>"><?= $doc['section_name'] ?></span></td>
                                            <td><span class="fw-medium text-dark"><?= $doc['for_signature'] ?></span></td>
                                            <td><span class="text-muted"><?= date('M j, Y', strtotime($doc['created_at'])) ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-olive rounded-circle">
                                                    <i class="fas fa-eye"></i>
                                                </button>
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
</body>
</html>