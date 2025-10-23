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

// Get current user info
$current_user_id = $_SESSION['emp_id'];
$current_user_section = $documentFunctions->getCurrentUserSection($current_user_id);

// Get documents for user's section AND documents created by user
$section_documents = [];
$created_documents = [];

if ($current_user_section) {
    // Documents assigned to user's section
    $section_documents = $documentFunctions->getDocumentsForUserSection($current_user_section['section_id']);
    
    // Documents created by current user (for monitoring)
    $created_documents = $documentFunctions->getDocumentsCreatedByUser($current_user_id);
}

// Combine both arrays for display
$all_documents = array_merge($section_documents, $created_documents);

// Get all sections for forwarding dropdown
$all_sections = $documentFunctions->getSections();

// Handle document actions
if ($_POST && isset($_POST['action_type'])) {
    $document_id = $_POST['document_id'];
    $action_type = $_POST['action_type'];
    $remarks = $_POST['remarks'] ?? '';
    $forward_to_section = $_POST['forward_to_section'] ?? '';
    $forward_to_employee = $_POST['forward_to_employee'] ?? '';
    
    // Validate that user has permission to act on this document
    $document = $documentFunctions->getDocumentById($document_id);
    
    if ($document && ($document['to_section_id'] == $current_user_section['section_id'])) {
        
        $new_status = '';
        $action_message = '';
        
        switch($action_type) {
            case 'acknowledge':
                $new_status = 'in_progress';
                $action_message = 'Document acknowledged and in progress';
                // Update received_by when acknowledging
                $documentFunctions->updateDocumentReceiver($document_id, $current_user_id);
                break;
                
            case 'complete':
                $new_status = 'completed';
                $action_message = 'Document processing completed';
                break;
                
            case 'forward':
                $new_status = 'forwarded';
                $action_message = 'Document forwarded to next step';
                
                // ACTUALLY FORWARD THE DOCUMENT TO NEW SECTION
                if (!empty($forward_to_section)) {
                    // Reset received_by when forwarding (new section will assign someone)
                    $documentFunctions->updateDocumentSection($document_id, $forward_to_section);
                    $documentFunctions->updateDocumentReceiver($document_id, null);
                }
                break;
                
            case 'need_clarification':
                $new_status = 'needs_clarification';
                $action_message = 'Document needs clarification';
                break;
        }
        
        if ($new_status) {
            // Update document status
            if ($documentFunctions->updateDocumentStatus($document_id, $new_status, $current_user_id)) {
                // Add action to history
                $documentFunctions->addDocumentAction($document_id, $action_type, $remarks, $current_user_id);
                
                $_SESSION['success_message'] = "Document action completed: " . $action_message;
            } else {
                $_SESSION['error_message'] = "Failed to update document status";
            }
        }
    } else {
        $_SESSION['error_message'] = "You don't have permission to perform this action on this document";
    }
    
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
    <title>Received Documents - NIA ACIMO</title>
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
        }
        .btn-olive {
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            border: none;
            border-radius: 10px;
            color: #f8f9fa;
            font-weight: 500;
            padding: 8px 15px;
        }
        .btn-olive:hover {
            background: linear-gradient(135deg, #465823 0%, #1a1a1a 100%);
            color: #f8f9fa;
        }
        .action-buttons .btn {
            margin: 2px;
            font-size: 0.8rem;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .document-priority {
            font-size: 0.7rem;
            padding: 2px 6px;
        }
        .priority-high { background-color: #dc3545; color: white; }
        .priority-medium { background-color: #ffc107; color: black; }
        .priority-low { background-color: #28a745; color: white; }
        .forward-dropdown {
            min-width: 200px;
        }
        .assigned-to-you {
            background-color: rgba(85, 107, 47, 0.05);
            border-left: 3px solid #556b2f;
        }
        /* Replace the existing forward-dropdown styles with these */
.forward-dropdown {
    min-width: 320px !important;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border: 1px solid #e0e0e0;
    left: auto !important;
    right: 0;
    transform: translateX(0) !important;
}

.forward-dropdown .dropdown-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px 8px 0 0;
    margin: -12px -12px 12px -12px;
    padding: 12px 15px;
    border-bottom: 1px solid #dee2e6;
}

.forward-form .form-control,
.forward-form .form-select {
    border: 1px solid #ced4da;
    transition: all 0.3s ease;
}

.forward-form .form-control:focus,
.forward-form .form-select:focus {
    border-color: #556b2f;
    box-shadow: 0 0 0 0.2rem rgba(85, 107, 47, 0.25);
}

.forward-form .btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    border-radius: 8px;
    font-weight: 500;
}

.forward-form .btn-outline-secondary {
    border-radius: 8px;
    font-weight: 500;
}

/* Improve dropdown toggle appearance */
.btn-info {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
    border: none;
    border-radius: 8px;
}

.btn-info:hover {
    background: linear-gradient(135deg, #138496 0%, #5a2d9e 100%);
}

/* Ensure dropdown stays open when interacting */
.dropdown-menu.show {
    display: block;
}

/* Fix dropdown positioning for the last items in table */
.action-buttons .dropdown {
    position: static;
}

.action-buttons .dropdown-menu {
    position: absolute;
    z-index: 1000;
}

/* Better positioning for forward dropdown */
.action-buttons .forward-dropdown {
    position: fixed !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    z-index: 1060;
}

.dropdown-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1050;
    display: none;
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
                        <h2 class="mb-1 fw-bold text-gradient">Received Documents</h2>
                        <p class="text-muted">Manage documents assigned to your section</p>
                    </div>
                    <div>
                        <span class="badge bg-info">Section Documents: <?= count($section_documents) ?></span>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <div><?= $_SESSION['success_message']; ?></div>
                        </div>
                        <button type="button" class="btn-close" data-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div><?= $_SESSION['error_message']; ?></div>
                        </div>
                        <button type="button" class="btn-close" data-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Documents in My Section & Created by Me -->
                <div class="modern-card">
                    <div class="card-header bg-transparent border-bottom-0 py-4">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-file-alt me-2 text-info"></i> 
                            Documents - My Section & Created by Me
                            <span class="badge bg-info rounded-pill ms-2"><?= count($all_documents) ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_documents)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No documents in your section or created by you</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-modern table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tracking No.</th>
                                            <th>Document</th>
                                            <th>Type</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Received By</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_documents as $doc): 
                                            $status_class = '';
                                            $row_class = '';
                                            $document_type = '';
                                            
                                            switch($doc['status']) {
                                                case 'pending': $status_class = 'bg-warning'; break;
                                                case 'in_progress': $status_class = 'bg-info'; break;
                                                case 'completed': $status_class = 'bg-success'; break;
                                                case 'forwarded': $status_class = 'bg-primary'; break;
                                                case 'needs_clarification': $status_class = 'bg-danger'; break;
                                                case 'cancelled': $status_class = 'bg-secondary'; break;
                                            }
                                            
                                            // Determine document type and row highlighting
                                            if ($doc['created_by'] == $current_user_id) {
                                                $document_type = 'created';
                                                $row_class = 'created-by-you';
                                            } elseif ($doc['to_section_id'] == $current_user_section['section_id']) {
                                                $document_type = 'section';
                                                if ($doc['received_by'] == $current_user_id) {
                                                    $row_class = 'assigned-to-you';
                                                }
                                            }
                                        ?>
                                        <tr class="<?= $row_class ?>">
                                            <td>
                                                <span class="fw-bold text-olive"><?= $doc['tracking_no'] ?></span>
                                                <?php if ($document_type == 'created'): ?>
                                                    <br><small class="text-muted">(Created by you)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-medium"><?= $doc['document_name'] ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?= $doc['document_type'] ?> - <?= $doc['type_of_document'] ?>
                                                </span>
                                            </td>
                                            <td><?= $doc['from_section'] ?? 'N/A' ?></td>
                                            <td><?= $doc['to_section_name'] ?? 'N/A' ?></td>
                                            <td>
                                                <?php if ($doc['received_by'] == $current_user_id): ?>
                                                    <span class="badge bg-olive">You</span>
                                                <?php elseif (!empty($doc['receiver_first'])): ?>
                                                    <?= $doc['receiver_first'] . ' ' . $doc['receiver_last'] ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="text-muted"><?= date('M j, Y', strtotime($doc['created_at'])) ?></span></td>
                                            <td><span class="badge <?= $status_class ?> status-badge"><?= ucfirst(str_replace('_', ' ', $doc['status'])) ?></span></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-olive view-document" data-id="<?= $doc['document_id'] ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    
                                                    <?php if ($document_type == 'section'): ?>
                                                        <!-- Actions only for documents in user's section -->
                                                        <?php if ($doc['received_by'] == $current_user_id): ?>
                                                            <!-- Actions for documents assigned to current user -->
                                                            <?php if ($doc['status'] == 'pending'): ?>
                                                                <button class="btn btn-sm btn-success acknowledge-document" data-id="<?= $doc['document_id'] ?>">
                                                                    <i class="fas fa-check"></i> Acknowledge
                                                                </button>
                                                            <?php elseif (in_array($doc['status'], ['in_progress', 'forwarded', 'needs_clarification'])): ?>
                                                                <!-- Show action buttons for active documents (not completed/cancelled) -->
                                                                <button class="btn btn-sm btn-primary complete-document" data-id="<?= $doc['document_id'] ?>">
                                                                    <i class="fas fa-flag-checkered"></i> Complete
                                                                </button>
                                                                <button class="btn btn-sm btn-warning need-clarification" data-id="<?= $doc['document_id'] ?>">
                                                                    <i class="fas fa-question"></i> Clarify
                                                                </button>
                                                                <div class="dropdown d-inline-block">
                                                                    <button class="btn btn-sm btn-info dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">
                                                                        <i class="fas fa-share"></i> Forward
                                                                    </button>
                                                                    <div class="dropdown-menu forward-dropdown p-3" style="min-width: 320px; transform: translateX(-50px);">
                                                                        <h6 class="dropdown-header fw-bold text-dark mb-2">
                                                                            <i class="fas fa-share me-1"></i> Forward Document
                                                                        </h6>
                                                                        <form method="POST" class="forward-form">
                                                                            <input type="hidden" name="document_id" value="<?= $doc['document_id'] ?>">
                                                                            <input type="hidden" name="action_type" value="forward">
                                                                            
                                                                            <div class="mb-3">
                                                                                <label class="form-label small fw-bold">Forward to Section:</label>
                                                                                <select class="form-select form-select-sm section-select" name="forward_to_section" required style="border-radius: 8px;">
                                                                                    <option value="">Select Section</option>
                                                                                    <?php foreach ($all_sections as $section): ?>
                                                                                        <!-- Exclude current section from options -->
                                                                                        <?php if ($section['section_id'] != $current_user_section['section_id']): ?>
                                                                                            <option value="<?= $section['section_id'] ?>"><?= $section['section_name'] ?></option>
                                                                                        <?php endif; ?>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            
                                                                            <div class="mb-3">
                                                                                <label class="form-label small fw-bold">Remarks:</label>
                                                                                <textarea class="form-control form-control-sm" name="remarks" rows="3" placeholder="Add forwarding remarks..." style="border-radius: 8px; resize: vertical;"></textarea>
                                                                            </div>
                                                                            
                                                                            <div class="d-grid gap-2">
                                                                                <button type="submit" class="btn btn-success btn-sm">
                                                                                    <i class="fas fa-paper-plane me-1"></i> Forward Document
                                                                                </button>
                                                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="dropdown">
                                                                                    <i class="fas fa-times me-1"></i> Cancel
                                                                                </button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php elseif ($doc['status'] == 'pending'): ?>
                                                            <!-- Acknowledge button for unassigned documents in the section -->
                                                            <button class="btn btn-sm btn-success acknowledge-document" data-id="<?= $doc['document_id'] ?>">
                                                                <i class="fas fa-check"></i> Take & Acknowledge
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <!-- For created documents, show view-only message -->
                                                        <small class="text-muted">View only</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../includes/mainfooter.php'; ?>
    </div>

    <!-- Action Modals -->
    <!-- Acknowledge Modal -->
    <div class="modal fade" id="acknowledgeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check me-2"></i>Acknowledge Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p>Are you sure you want to acknowledge receipt of this document?</p>
                        <input type="hidden" name="document_id" id="acknowledge_document_id">
                        <input type="hidden" name="action_type" value="acknowledge">
                        <div class="mb-3">
                            <label for="acknowledge_remarks" class="form-label">Remarks (Optional)</label>
                            <textarea class="form-control" id="acknowledge_remarks" name="remarks" rows="3" placeholder="Add any remarks..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Acknowledge Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Complete Modal -->
    <div class="modal fade" id="completeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-flag-checkered me-2"></i>Complete Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p>Mark this document as completed?</p>
                        <input type="hidden" name="document_id" id="complete_document_id">
                        <input type="hidden" name="action_type" value="complete">
                        <div class="mb-3">
                            <label for="complete_remarks" class="form-label">Completion Remarks</label>
                            <textarea class="form-control" id="complete_remarks" name="remarks" rows="3" placeholder="Describe completion details..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as Complete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Need Clarification Modal -->
    <div class="modal fade" id="clarificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-question me-2"></i>Need Clarification</h5>
                    <button type="button" class="btn-close" data-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p>Request clarification for this document?</p>
                        <input type="hidden" name="document_id" id="clarification_document_id">
                        <input type="hidden" name="action_type" value="need_clarification">
                        <div class="mb-3">
                            <label for="clarification_remarks" class="form-label">Clarification Needed</label>
                            <textarea class="form-control" id="clarification_remarks" name="remarks" rows="3" placeholder="What clarification do you need?" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Request Clarification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        $(document).ready(function() {
            // Acknowledge document
            $('.acknowledge-document').click(function() {
                const documentId = $(this).data('id');
                $('#acknowledge_document_id').val(documentId);
                $('#acknowledgeModal').modal('show');
            });

            // Complete document
            $('.complete-document').click(function() {
                const documentId = $(this).data('id');
                $('#complete_document_id').val(documentId);
                $('#completeModal').modal('show');
            });

            // Need clarification
            $('.need-clarification').click(function() {
                const documentId = $(this).data('id');
                $('#clarification_document_id').val(documentId);
                $('#clarificationModal').modal('show');
            });

            // View document details
            $('.view-document').click(function() {
                const documentId = $(this).data('id');
                window.location.href = 'document_details.php?id=' + documentId;
            });
           
        });
        // Replace the existing forward dropdown JavaScript with this simplified version
        $(document).ready(function() {
            // Initialize Bootstrap dropdowns
            $('.dropdown-toggle').dropdown();
            
            // Handle forward dropdown as modal
            $(document).on('show.bs.dropdown', '.dropdown', function(e) {
                const $dropdown = $(this);
                const $menu = $dropdown.find('.dropdown-menu');
                
                if ($menu.hasClass('forward-dropdown')) {
                    // Create backdrop
                    $('body').append('<div class="dropdown-backdrop"></div>');
                    $('.dropdown-backdrop').fadeIn(200);
                    
                    // Position as modal
                    $menu.css({
                        'position': 'fixed',
                        'top': '50%',
                        'left': '50%',
                        'transform': 'translate(-50%, -50%)',
                        'z-index': '1060'
                    });
                }
            });
            
            $(document).on('hide.bs.dropdown', '.dropdown', function(e) {
                const $dropdown = $(this);
                const $menu = $dropdown.find('.dropdown-menu');
                
                if ($menu.hasClass('forward-dropdown')) {
                    // Remove backdrop
                    $('.dropdown-backdrop').remove();
                    
                    // Reset positioning
                    $menu.css({
                        'position': '',
                        'top': '',
                        'left': '',
                        'transform': '',
                        'z-index': ''
                    });
                }
            });
            
            // Close dropdown when clicking backdrop
            $(document).on('click', '.dropdown-backdrop', function() {
                $('.dropdown-toggle').dropdown('hide');
            });

            // Keep dropdown open when clicking inside
            $(document).on('click', '.forward-dropdown, .forward-form', function(e) {
                e.stopPropagation();
            });

            // Close dropdown when cancel button is clicked
            $(document).on('click', '.forward-form .btn-outline-secondary', function() {
                $(this).closest('.dropdown-menu').prev('.dropdown-toggle').dropdown('hide');
            });

            // Handle form submission - removed employee validation
            $(document).on('submit', '.forward-form', function(e) {
                const sectionSelected = $(this).find('.section-select').val();
                if (!sectionSelected) {
                    e.preventDefault();
                    alert('Please select a section to forward to.');
                    return false;
                }
            });
        });
    </script>
</body>
</html>