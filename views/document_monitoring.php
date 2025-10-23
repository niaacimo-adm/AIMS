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

$sections = $documentFunctions->getSections();
$current_user = $documentFunctions->getUserById($_SESSION['emp_id']);
$current_user_section = $documentFunctions->getCurrentUserSection($_SESSION['emp_id']);

// Get appropriate "From" options based on user type
$from_options = $documentFunctions->getFromOptions($_SESSION['emp_id']);

// Get employees from current user's section for "Received by" dropdown
$section_employees = [];
if ($current_user_section) {
    $section_employees = $documentFunctions->getEmployeesBySection($current_user_section['section_id']);
}

// Handle form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'create_document') {
    // Validate required fields
    $required_fields = ['document_name', 'to_section_id', 'received_by'];
    
    // Add from_emp_id to required fields if user is Manager Staff
    if ($from_options['type'] === 'imo_staff') {
        $required_fields[] = 'from_emp_id';
    }
    
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $_SESSION['error_message'] = "Please fill in all required fields: " . implode(', ', $missing_fields);
        header('Location: document_monitoring.php');
        exit();
    }
    
    if ($from_options['type'] === 'imo_staff') {
        // For Manager Staff, use "IMO Office" as the from section
        $from_section = 'IMO Office';
        $document_data['from_emp_id'] = $_SESSION['emp_id']; // Use current user's ID
    } else {
        // For regular employees, use their section
        $from_section = $current_user_section ? $current_user_section['section_name'] : 'Unknown Section';
    }
    
    // Generate tracking number based on FROM section
    $tracking_no = $documentFunctions->generateTrackingNumberFromSection(
        $current_user_section['section_id'], 
        $_POST['document_type']
    );
    
    // Get signature based on TO section
    $for_signature = $documentFunctions->getToSectionHeadInitials($_POST['to_section_id']);
    
    $document_data = [
        'tracking_no' => $tracking_no,
        'document_type' => $_POST['document_type'],
        'type_of_document' => $_POST['type_of_document'] ?? '',
        'from_section' => $from_section,
        'document_name' => $_POST['document_name'],
        'to_section_id' => $_POST['to_section_id'],
        'for_signature' => $for_signature,
        'received_by' => $_POST['received_by'], // Now using the selected employee
        'remarks' => $_POST['remarks'] ?? '',
        'created_by' => $_SESSION['emp_id']
    ];
    
    // Add from_emp_id if it's a Manager Staff
    if ($from_options['type'] === 'imo_staff') {
        $document_data['from_emp_id'] = $_POST['from_emp_id'];
    }
    
    if ($documentFunctions->createDocument($document_data)) {
        $_SESSION['success_message'] = "Document created successfully with tracking number: " . $tracking_no;
        header('Location: document_monitoring.php');
        exit();
    } else {
        $_SESSION['error_message'] = "Failed to create document. Please try again.";
    }
}

// Get document counts for sidebar
$document_counts = $documentFunctions->getDocumentCounts();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Monitoring - NIA ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .modern-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        .nav-tabs-modern {
            border-bottom: 2px solid #e9ecef;
        }
        .nav-tabs-modern .nav-link {
            border: none;
            border-radius: 10px 10px 0 0;
            padding: 15px 25px;
            font-weight: 500;
            color: #ffffff; /* White text for better visibility */
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%); /* Gray gradient for inactive tabs */
            transition: all 0.3s ease;
            margin-right: 5px;
        }
        .nav-tabs-modern .nav-link.active {
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(85, 107, 47, 0.3);
        }
        .nav-tabs-modern .nav-link:hover {
            border: none;
            color: #ffffff;
            background: linear-gradient(135deg, #5a6268 0%, #3d4348 100%);
        }

        /* Modal positioning */
        .modal-right .modal-dialog {
            position: fixed;
            top: 0;
            right: 0;
            margin: 0;
            max-width: 600px;
            height: 100vh;
        }
        .modal-right .modal-content {
            height: 100%;
            border-radius: 0;
            border: none;
        }
        .modal-right .modal-header {
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            color: white;
        }
        .modal-right .modal-body {
            overflow-y: auto;
        }
        .form-modern .form-control, .form-modern .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
            background: #ffffff;
        }
        .form-modern .form-control:focus, .form-modern .form-select:focus {
            border-color: #556b2f;
            box-shadow: 0 0 0 0.2rem rgba(85, 107, 47, 0.25);
        }
        .tracking-number {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 1.1rem;
            color: #556b2f;
            background: rgba(85, 107, 47, 0.1);
            padding: 10px 15px;
            border-radius: 10px;
            display: inline-block;
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
        .badge-imo { 
            background: linear-gradient(135deg, #8b4513 0%, #654321 100%); 
            color: white; 
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
        .text-gradient {
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .badge-sm {
            font-size: 0.7em;
            padding: 3px 8px;
        }
            /* Modal positioning - FIXED */
        .modal-right .modal-dialog {
            position: fixed;
            top: 0;
            right: 0;
            margin: 0;
            max-width: 600px;
            height: 100vh;
            z-index: 1060; /* Higher than header z-index */
            transform: none !important;
        }
        
        .modal-right .modal-content {
            height: 100%;
            border-radius: 0;
            border: none;
            box-shadow: -5px 0 15px rgba(0,0,0,0.1);
        }
        
        .modal-right .modal-header {
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #556b2f 0%, #2b2b2b 100%);
            color: white;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .modal-right .modal-body {
            overflow-y: auto;
            padding: 20px;
        }
        
        .modal-right .modal-footer {
            border-top: 1px solid #e9ecef;
            position: sticky;
            bottom: 0;
            background: white;
            z-index: 1;
        }

        /* Ensure modal backdrop is properly positioned */
        .modal-backdrop {
            z-index: 1050 !important;
        }

        /* Fix for AdminLTE header z-index */
        .main-header {
            z-index: 1030 !important;
        }

        /* Ensure modal appears above everything */
        .modal {
            z-index: 1060 !important;
        }

        /* Additional fixes for document history cards */
        .document-history .card {
            border-left: 4px solid #556b2f;
            transition: transform 0.2s ease;
        }
        
        .document-history .card:hover {
            transform: translateY(-2px);
        }
        
        .badge-sm {
            font-size: 0.7em;
            padding: 3px 8px;
        }

        /* Better spacing for document history */
        .document-history {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        /* Select2 styling */
        .select2-container .select2-selection--single {
            height: 46px !important;
            border: 2px solid #e9ecef !important;
            border-radius: 10px !important;
        }

        .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            line-height: 42px !important;
            padding-left: 15px !important;
        }

        .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
            height: 44px !important;
        }

        .select2-container--bootstrap4 .select2-dropdown {
            border: 2px solid #e9ecef !important;
            border-radius: 10px !important;
        }

        .select2-container--bootstrap4 .select2-results__option--highlighted {
            background-color: #556b2f !important;
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
                        <h2 class="mb-1 fw-bold text-gradient">Document Monitoring Records</h2>
                        <p class="text-muted">Create and manage incoming, outgoing, and internal communication documents</p>
                    </div>
                    <div>
                        <button class="btn btn-olive" data-toggle="modal" data-target="#documentHistoryModal">
                            <i class="fas fa-history me-2"></i> View Document History
                        </button>
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

                <!-- Document Type Tabs -->
                <ul class="nav nav-tabs nav-tabs-modern mb-4" id="documentTypeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="incoming-tab" data-toggle="tab" data-target="#incoming" type="button" role="tab">
                            <i class="fas fa-inbox me-2"></i> Incoming Documents
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="outgoing-tab" data-toggle="tab" data-target="#outgoing" type="button" role="tab">
                            <i class="fas fa-paper-plane me-2"></i> Outgoing Documents
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="internal-tab" data-toggle="tab" data-target="#internal" type="button" role="tab">
                            <i class="fas fa-exchange-alt me-2"></i> Internal Communication
                        </button>
                    </li>
                </ul>

                <!-- Document Forms -->
                <div class="tab-content" id="documentTypeTabsContent">
                    <!-- Incoming Documents Tab -->
                    <div class="tab-pane fade show active" id="incoming" role="tabpanel">
                        <div class="modern-card">
                            <div class="card-header bg-transparent border-bottom-0 py-4">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-inbox me-2 text-olive"></i> Create Incoming Document
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="incomingDocumentForm" class="form-modern">
                                    <input type="hidden" name="action" value="create_document">
                                    <input type="hidden" name="document_type" value="incoming">
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="incomingTrackingNo" class="form-label required">Tracking No.</label>
                                            <div class="tracking-number" id="incomingTrackingNo">
                                                <?php 
                                                if ($current_user_section) {
                                                    echo $documentFunctions->generateTrackingNumberFromSection($current_user_section['section_id'], 'incoming');
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </div>
                                            <small class="form-text text-muted">Automatically generated based on your section</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="incomingDocumentType" class="form-label">Type of Document</label>
                                            <select class="form-select" id="incomingDocumentType" name="type_of_document">
                                                <option value="">Select Document Type</option>
                                                <option value="Memo">Memo</option>
                                                <option value="Letter">Letter</option>
                                                <option value="Report">Report</option>
                                                <option value="Circular">Circular</option>
                                                <option value="Others">Others</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="incomingFrom" class="form-label required">From</label>
                                            <?php if ($from_options['type'] === 'imo_staff'): ?>
                                                <!-- For Manager Staff: Show IMO Office as fixed text input -->
                                                <input type="text" class="form-control" id="incomingFrom" 
                                                    value="IMO Office" 
                                                    readonly>
                                                <input type="hidden" name="from_emp_id" value="<?= $_SESSION['emp_id'] ?>">
                                                <small class="form-text text-muted">Your position: IMO Office Staff</small>
                                            <?php else: ?>
                                                <!-- For regular employees: Show section (readonly) -->
                                                <input type="text" class="form-control" id="incomingFrom" 
                                                    value="<?= $current_user_section ? $current_user_section['section_name'] : 'Not assigned to any section' ?>" 
                                                    readonly>
                                                <small class="form-text text-muted">Your current section (auto-filled)</small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="incomingDocument" class="form-label required">Documents</label>
                                            <input type="text" class="form-control" id="incomingDocument" name="document_name" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="incomingTo" class="form-label required">To (Forward to other sections)</label>
                                            <select class="form-select section-select" id="incomingTo" name="to_section_id" required>
                                                <option value="">Select Section</option>
                                                <?php foreach ($sections as $section): 
                                                    $color_class = '';
                                                    switch($section['section_code']) {
                                                        case 'ADM': $color_class = 'badge-admin'; break;
                                                        case 'FIN': $color_class = 'badge-finance'; break;
                                                        case 'ENG': $color_class = 'badge-engineering'; break;
                                                        case 'OMS': $color_class = 'badge-oms'; break;
                                                        case 'IMO': $color_class = 'badge-imo'; break;
                                                    }
                                                ?>
                                                <option value="<?= $section['section_id'] ?>" data-color="<?= $color_class ?>">
                                                    <?= $section['section_name'] ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="incomingSignature" class="form-label required">For Signature (Section Head)</label>
                                            <input type="text" class="form-control" id="incomingSignature" name="for_signature" placeholder="Select section above" readonly>
                                            <small class="form-text text-muted">Section head of the selected "To" section</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="incomingReceivedBy" class="form-label required">Received by</label>
                                            <select class="form-select select2" id="incomingReceivedBy" name="received_by" required>
                                                <option value="">Select Employee</option>
                                                <?php foreach ($section_employees as $employee): ?>
                                                    <option value="<?= $employee['emp_id'] ?>" 
                                                        <?= $employee['emp_id'] == $_SESSION['emp_id'] ? 'selected' : '' ?>>
                                                        <?= $employee['first_name'] . ' ' . $employee['last_name'] ?>
                                                        <?= !empty($employee['middle_name']) ? ' ' . substr($employee['middle_name'], 0, 1) . '.' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Select who received the document in your section</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="incomingRemarks" class="form-label">Remarks</label>
                                            <textarea class="form-control" id="incomingRemarks" name="remarks" rows="2"></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end pt-3">
                                        <button type="reset" class="btn btn-outline-secondary me-3 rounded-pill px-4">Reset</button>
                                        <button type="submit" class="btn btn-olive rounded-pill px-4">
                                            <i class="fas fa-plus me-2"></i>Create Incoming Document
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Outgoing Documents Tab -->
                    <div class="tab-pane fade" id="outgoing" role="tabpanel">
                        <div class="modern-card">
                            <div class="card-header bg-transparent border-bottom-0 py-4">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-paper-plane me-2 text-olive"></i> Create Outgoing Document
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="outgoingDocumentForm" class="form-modern">
                                    <input type="hidden" name="action" value="create_document">
                                    <input type="hidden" name="document_type" value="outgoing">
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="outgoingTrackingNo" class="form-label required">Tracking No.</label>
                                            <div class="tracking-number" id="outgoingTrackingNo">
                                                <?php 
                                                if ($current_user_section) {
                                                    echo $documentFunctions->generateTrackingNumberFromSection($current_user_section['section_id'], 'outgoing');
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </div>
                                            <small class="form-text text-muted">Automatically generated based on your section</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="outgoingDocumentType" class="form-label">Type of Document</label>
                                            <select class="form-select" id="outgoingDocumentType" name="type_of_document">
                                                <option value="">Select Document Type</option>
                                                <option value="Memo">Memo</option>
                                                <option value="Letter">Letter</option>
                                                <option value="Report">Report</option>
                                                <option value="Circular">Circular</option>
                                                <option value="Others">Others</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="outgoingFrom" class="form-label required">From</label>
                                            <?php if ($from_options['type'] === 'imo_staff'): ?>
                                                <!-- For Manager Staff: Show IMO Office as fixed text input -->
                                                <input type="text" class="form-control" id="outgoingFrom" 
                                                    value="IMO Office" 
                                                    readonly>
                                                <input type="hidden" name="from_emp_id" value="<?= $_SESSION['emp_id'] ?>">
                                                <small class="form-text text-muted">Your position: IMO Office Staff</small>
                                            <?php else: ?>
                                                <!-- For regular employees: Show section (readonly) -->
                                                <input type="text" class="form-control" id="outgoingFrom" 
                                                    value="<?= $current_user_section ? $current_user_section['section_name'] : 'Not assigned to any section' ?>" 
                                                    readonly>
                                                <small class="form-text text-muted">Your current section (auto-filled)</small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="outgoingDocument" class="form-label required">Documents</label>
                                            <input type="text" class="form-control" id="outgoingDocument" name="document_name" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="outgoingTo" class="form-label required">To (Forward to other sections)</label>
                                            <select class="form-select section-select" id="outgoingTo" name="to_section_id" required>
                                                <option value="">Select Section</option>
                                                <?php foreach ($sections as $section): 
                                                    $color_class = '';
                                                    switch($section['section_code']) {
                                                        case 'ADM': $color_class = 'badge-admin'; break;
                                                        case 'FIN': $color_class = 'badge-finance'; break;
                                                        case 'ENG': $color_class = 'badge-engineering'; break;
                                                        case 'OMS': $color_class = 'badge-oms'; break;
                                                         case 'IMO': $color_class = 'badge-imo'; break;
                                                    }
                                                ?>
                                                <option value="<?= $section['section_id'] ?>" data-color="<?= $color_class ?>">
                                                    <?= $section['section_name'] ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="outgoingSignature" class="form-label required">For Signature (Section Head)</label>
                                            <input type="text" class="form-control" id="outgoingSignature" name="for_signature" placeholder="Select section above" readonly>
                                            <small class="form-text text-muted">Section head of the selected "To" section</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="outgoingReceivedBy" class="form-label required">Received by</label>
                                            <select class="form-select select2" id="outgoingReceivedBy" name="received_by" required>
                                                <option value="">Select Employee</option>
                                                <?php foreach ($section_employees as $employee): ?>
                                                    <option value="<?= $employee['emp_id'] ?>" 
                                                        <?= $employee['emp_id'] == $_SESSION['emp_id'] ? 'selected' : '' ?>>
                                                        <?= $employee['first_name'] . ' ' . $employee['last_name'] ?>
                                                        <?= !empty($employee['middle_name']) ? ' ' . substr($employee['middle_name'], 0, 1) . '.' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Select who received the document in your section</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="outgoingRemarks" class="form-label">Remarks</label>
                                            <textarea class="form-control" id="outgoingRemarks" name="remarks" rows="2"></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end pt-3">
                                        <button type="reset" class="btn btn-outline-secondary me-3 rounded-pill px-4">Reset</button>
                                        <button type="submit" class="btn btn-olive rounded-pill px-4">
                                            <i class="fas fa-plus me-2"></i>Create Outgoing Document
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Internal Communication Tab -->
                    <div class="tab-pane fade" id="internal" role="tabpanel">
                        <div class="modern-card">
                            <div class="card-header bg-transparent border-bottom-0 py-4">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-exchange-alt me-2 text-olive"></i> Create Internal Communication
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="internalDocumentForm" class="form-modern">
                                    <input type="hidden" name="action" value="create_document">
                                    <input type="hidden" name="document_type" value="internal">
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="internalTrackingNo" class="form-label required">Tracking No.</label>
                                            <div class="tracking-number" id="internalTrackingNo">
                                                <?php 
                                                if ($current_user_section) {
                                                    echo $documentFunctions->generateTrackingNumberFromSection($current_user_section['section_id'], 'internal');
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </div>
                                            <small class="form-text text-muted">Automatically generated based on your section</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="internalDocumentType" class="form-label">Type of Document</label>
                                            <select class="form-select" id="internalDocumentType" name="type_of_document">
                                                <option value="">Select Document Type</option>
                                                <option value="Memo">Memo</option>
                                                <option value="Letter">Letter</option>
                                                <option value="Report">Report</option>
                                                <option value="Circular">Circular</option>
                                                <option value="Others">Others</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="internalFrom" class="form-label required">From</label>
                                            <?php if ($from_options['type'] === 'imo_staff'): ?>
                                                <!-- For Manager Staff: Show IMO Office as fixed text input -->
                                                <input type="text" class="form-control" id="internalFrom" 
                                                    value="IMO Office" 
                                                    readonly>
                                                <input type="hidden" name="from_emp_id" value="<?= $_SESSION['emp_id'] ?>">
                                                <small class="form-text text-muted">Your position: IMO Office Staff</small>
                                            <?php else: ?>
                                                <!-- For regular employees: Show section (readonly) -->
                                                <input type="text" class="form-control" id="internalFrom" 
                                                    value="<?= $current_user_section ? $current_user_section['section_name'] : 'Not assigned to any section' ?>" 
                                                    readonly>
                                                <small class="form-text text-muted">Your current section (auto-filled)</small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="internalDocument" class="form-label required">Documents</label>
                                            <input type="text" class="form-control" id="internalDocument" name="document_name" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="internalTo" class="form-label required">To (Forward to other sections)</label>
                                            <select class="form-select section-select" id="internalTo" name="to_section_id" required>
                                                <option value="">Select Section</option>
                                                <?php foreach ($sections as $section): 
                                                    $color_class = '';
                                                    switch($section['section_code']) {
                                                        case 'ADM': $color_class = 'badge-admin'; break;
                                                        case 'FIN': $color_class = 'badge-finance'; break;
                                                        case 'ENG': $color_class = 'badge-engineering'; break;
                                                        case 'OMS': $color_class = 'badge-oms'; break;
                                                         case 'IMO': $color_class = 'badge-imo'; break;
                                                    }
                                                ?>
                                                <option value="<?= $section['section_id'] ?>" data-color="<?= $color_class ?>">
                                                    <?= $section['section_name'] ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="internalSignature" class="form-label required">For Signature (Section Head)</label>
                                            <input type="text" class="form-control" id="internalSignature" name="for_signature" placeholder="Select section above" readonly>
                                            <small class="form-text text-muted">Section head of the selected "To" section</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="internalReceivedBy" class="form-label required">Received by</label>
                                            <select class="form-select select2" id="internalReceivedBy" name="received_by" required>
                                                <option value="">Select Employee</option>
                                                <?php foreach ($section_employees as $employee): ?>
                                                    <option value="<?= $employee['emp_id'] ?>" 
                                                        <?= $employee['emp_id'] == $_SESSION['emp_id'] ? 'selected' : '' ?>>
                                                        <?= $employee['first_name'] . ' ' . $employee['last_name'] ?>
                                                        <?= !empty($employee['middle_name']) ? ' ' . substr($employee['middle_name'], 0, 1) . '.' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Select who received the document in your section</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="internalRemarks" class="form-label">Remarks</label>
                                            <textarea class="form-control" id="internalRemarks" name="remarks" rows="2"></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end pt-3">
                                        <button type="reset" class="btn btn-outline-secondary me-3 rounded-pill px-4">Reset</button>
                                        <button type="submit" class="btn btn-olive rounded-pill px-4">
                                            <i class="fas fa-plus me-2"></i>Create Internal Document
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- Document History Modal -->
        <div class="modal fade modal-right" id="documentHistoryModal" tabindex="-1" aria-labelledby="documentHistoryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="documentHistoryModalLabel">
                            <i class="fas fa-history me-2"></i>Document History
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="documentHistoryContent">
                            <!-- Loading spinner -->
                            <div class="text-center p-4">
                                <div class="spinner-border text-olive" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading document history...</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-olive" onclick="refreshHistory()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../includes/mainfooter.php'; ?>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
    function refreshHistory() {
        loadDocumentHistory();
    }
    </script>
    <script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: 'Select Employee',
            allowClear: true
        });
        // Generate tracking number when section is selected
        $('.section-select').change(function() {
            const sectionId = $(this).val();
            const tabId = $(this).closest('.tab-pane').attr('id');
            const signatureField = $('#' + tabId + 'Signature');
            
            if (sectionId) {
                getSignatureForSection(sectionId, signatureField);
            } else {
                signatureField.val('');
                signatureField.attr('placeholder', 'Select section above');
            }
        });
        
        function getSignatureForSection(sectionId, signatureField) {
            // Section head signatures with middle initials
            const signatures = {
                '1': 'I.B. Cruz', // ADM - Example: Initials + Last Name
                '2': 'R.M. Santos', // FIN
                '3': 'A.L. Reyes', // ENG
                '4': 'M.G. Lopez'  // OMS
            };
            
            // You can replace this with AJAX call to get actual section head data
            $.ajax({
                url: 'get_signature.php',
                type: 'POST',
                data: { section_id: sectionId },
                success: function(response) {
                    if (response.success) {
                        signatureField.val(response.signature);
                    } else {
                        // Fallback to static data
                        signatureField.val(signatures[sectionId] || 'Section Head');
                    }
                },
                error: function() {
                    // Fallback to static data
                    signatureField.val(signatures[sectionId] || 'Section Head');
                }
            });
        }
        
        // Form validation
        $('form').submit(function(e) {
            const form = $(this);
            const tabId = form.closest('.tab-pane').attr('id');
            const trackingNo = $('#' + tabId + 'TrackingNo').text();
            const signature = $('#' + tabId + 'Signature').val();
            
            // Check required fields
            const requiredFields = form.find('[required]');
            let valid = true;
            
            requiredFields.each(function() {
                if (!$(this).val().trim()) {
                    valid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields (marked with *)');
                return false;
            }
            
            if (!signature) {
                e.preventDefault();
                alert('Please select a "To" section to get the section head signature.');
                return false;
            }
        });
        
        // Remove required attribute from non-required fields
        $('select[name="type_of_document"]').removeAttr('required');
        $('textarea[name="remarks"]').removeAttr('required');
        
        // Initialize Document History Modal
        $('#documentHistoryModal').on('show.bs.modal', function() {
            loadDocumentHistory();
        });
        
        function loadDocumentHistory() {
            // AJAX call to load document history
            $.ajax({
                url: 'get_document_history.php',
                type: 'GET',
                success: function(response) {
                    $('#documentHistoryContent').html(response);
                },
                error: function() {
                    $('#documentHistoryContent').html('<div class="text-center text-muted p-4">Failed to load document history</div>');
                }
            });
        }
    });
    </script>
    
</body>
</html>