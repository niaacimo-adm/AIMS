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

// Get document history
$documents = $documentFunctions->getDocumentHistory($_SESSION['emp_id']);
?>

<?php if (empty($documents)): ?>
    <div class="text-center text-muted p-4">
        <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
        <p>No document history found.</p>
    </div>
<?php else: ?>
    <div class="document-history">
        <?php foreach ($documents as $doc): 
            // Determine section color
            $section_color = '';
            switch($doc['section_code'] ?? '') {
                case 'ADM': $section_color = 'badge-admin'; break;
                case 'FIN': $section_color = 'badge-finance'; break;
                case 'ENG': $section_color = 'badge-engineering'; break;
                case 'OMS': $section_color = 'badge-oms'; break;
                default: $section_color = 'badge-secondary'; break;
            }
            
            // Determine document type badge color
            $type_badge = '';
            switch($doc['document_type']) {
                case 'incoming': $type_badge = 'badge-primary'; break;
                case 'outgoing': $type_badge = 'badge-success'; break;
                case 'internal': $type_badge = 'badge-info'; break;
                default: $type_badge = 'badge-secondary'; break;
            }
        ?>
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="card-title mb-0 text-truncate flex-grow-1 me-2">
                            <?= htmlspecialchars($doc['document_name']) ?>
                        </h6>
                        <span class="badge <?= $type_badge ?>">
                            <?= ucfirst($doc['document_type']) ?>
                        </span>
                    </div>
                    
                    <p class="card-text small text-muted mb-1">
                        <strong>Tracking No:</strong> 
                        <span class="font-monospace"><?= htmlspecialchars($doc['tracking_no']) ?></span>
                    </p>
                    
                    <div class="row small text-muted mb-2">
                        <div class="col-6">
                            <strong>From:</strong> <?= htmlspecialchars($doc['from_section']) ?>
                        </div>
                        <div class="col-6">
                            <strong>To:</strong> 
                            <span class="badge <?= $section_color ?> badge-sm">
                                <?= htmlspecialchars($doc['to_section_name']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="row small text-muted mb-2">
                        <div class="col-6">
                            <strong>Type:</strong> <?= htmlspecialchars($doc['type_of_document'] ?? 'N/A') ?>
                        </div>
                        <div class="col-6">
                            <strong>Signature:</strong> <?= htmlspecialchars($doc['for_signature']) ?>
                        </div>
                    </div>
                    
                    <p class="card-text small text-muted mb-2">
                        <strong>Date:</strong> <?= date('M d, Y h:i A', strtotime($doc['created_at'])) ?>
                    </p>
                    
                    <?php if (!empty($doc['remarks'])): ?>
                        <p class="card-text small">
                            <strong>Remarks:</strong> <?= htmlspecialchars($doc['remarks']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="mt-2 pt-2 border-top">
                        <small class="text-muted">
                            Created by: <?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?>
                        </small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>