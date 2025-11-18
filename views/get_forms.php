<?php
// get_forms.php - COMPACT VERSION
session_start();
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $forms_stmt = $db->prepare("SELECT * FROM company_forms WHERE is_active = TRUE ORDER BY created_at DESC");
    $forms_stmt->execute();
    $company_forms = $forms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading forms: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="forms-container">
    <?php if (!empty($company_forms)): ?>
        <!-- Compact Search Header -->
        <div class="forms-control mb-3">
            <div class="search-box">
                <div class="input-group input-group-sm"> <!-- Smaller input -->
                    <input type="text" id="modal-forms-search" class="form-control" placeholder="Search forms...">
                    <div class="input-group-append">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="forms-info small"> <!-- Smaller text -->
                <span id="modal-forms-total"><?= count($company_forms) ?></span> forms
            </div>
        </div>

        <!-- Compact Forms Grid -->
        <div class="forms-grid-compact" id="modal-forms-grid">
            <?php foreach ($company_forms as $form): ?>
                <?php 
                    $filePath = $form['file_path'];
                    $fileName = $form['form_name'];
                    $description = $form['description'] ?: 'No description available';
                    $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
                    
                    $fileIcon = 'fa-file';
                    $iconColor = 'text-secondary';
                    
                    switch(strtolower($fileExtension)) {
                        case 'pdf':
                            $fileIcon = 'fa-file-pdf';
                            $iconColor = 'text-danger';
                            break;
                        case 'doc':
                        case 'docx':
                            $fileIcon = 'fa-file-word';
                            $iconColor = 'text-primary';
                            break;
                        case 'xls':
                        case 'xlsx':
                            $fileIcon = 'fa-file-excel';
                            $iconColor = 'text-success';
                            break;
                        case 'ppt':
                        case 'pptx':
                            $fileIcon = 'fa-file-powerpoint';
                            $iconColor = 'text-warning';
                            break;
                        case 'zip':
                        case 'rar':
                            $fileIcon = 'fa-file-archive';
                            $iconColor = 'text-secondary';
                            break;
                        case 'jpg':
                        case 'jpeg':
                        case 'png':
                        case 'gif':
                            $fileIcon = 'fa-file-image';
                            $iconColor = 'text-info';
                            break;
                    }
                ?>
                <!-- Compact Form Card -->
                <div class="form-card-compact" data-form-name="<?= htmlspecialchars(strtolower($fileName)) ?>" data-form-desc="<?= htmlspecialchars(strtolower($description)) ?>">
                    <div class="form-card-header">
                        <div class="form-icon-small">
                            <i class="fas <?= $fileIcon ?> <?= $iconColor ?>"></i>
                        </div>
                        <div class="form-info">
                            <h6 class="form-title"><?= htmlspecialchars($fileName) ?></h6>
                            <p class="form-desc"><?= htmlspecialchars($description) ?></p>
                        </div>
                    </div>
                    <div class="form-actions-compact">
                        <a href="<?= htmlspecialchars($filePath) ?>" class="btn btn-primary btn-xs" target="_blank" download="<?= htmlspecialchars($fileName) ?>" title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                        <?php if (in_array(strtolower($fileExtension), ['pdf', 'jpg', 'jpeg', 'png', 'gif'])): ?>
                            <a href="<?= htmlspecialchars($filePath) ?>" class="btn btn-outline-secondary btn-xs" target="_blank" title="Preview">
                                <i class="fas fa-eye"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- No results message -->
        <div id="modal-no-results" class="text-center py-4" style="display: none;">
            <i class="fas fa-search fa-2x text-muted mb-2"></i>
            <p class="text-muted small mb-0">No forms found</p>
        </div>

    <?php else: ?>
        <div class="text-center py-4">
            <i class="fas fa-file-alt fa-2x text-muted mb-2"></i>
            <p class="text-muted small mb-0">No forms available</p>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Forms search functionality
    $('#modal-forms-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase().trim();
        let visibleCount = 0;
        
        $('.form-card-compact').each(function() {
            const formName = $(this).data('form-name');
            const formDesc = $(this).data('form-desc');
            
            if (formName.includes(searchTerm) || formDesc.includes(searchTerm)) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });
        
        // Update counter
        $('#modal-forms-total').text(visibleCount);
        
        // Show/hide no results message
        if (visibleCount === 0 && searchTerm !== '') {
            $('#modal-no-results').show();
        } else {
            $('#modal-no-results').hide();
        }
    });
    
    // Clear search when modal is closed
    $(document).on('hidden.bs.modal', '#formsModal', function() {
        $('#modal-forms-search').val('').trigger('input');
    });
});
</script>

<style>
/* Compact Forms Grid */
.forms-grid-compact {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 70vh;
    overflow-y: auto;
    padding: 5px;
}

/* Compact Form Card */
.form-card-compact {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: white;
    transition: all 0.2s ease;
    gap: 12px;
}

.form-card-compact:hover {
    border-color: #007bff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

.form-card-header {
    display: flex;
    align-items: center;
    flex: 1;
    gap: 10px;
    min-width: 0; /* Allow text truncation */
}

.form-icon-small {
    flex-shrink: 0;
}

.form-icon-small i {
    font-size: 1.2rem;
}

.form-info {
    flex: 1;
    min-width: 0; /* Allow text truncation */
}

.form-title {
    color: #343a40;
    font-weight: 600;
    margin: 0 0 2px 0;
    font-size: 0.9rem;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.form-desc {
    color: #6c757d;
    margin: 0;
    font-size: 0.8rem;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.form-actions-compact {
    display: flex;
    gap: 5px;
    flex-shrink: 0;
}

.btn-xs {
    padding: 4px 8px;
    font-size: 0.75rem;
    line-height: 1;
    border-radius: 3px;
}

/* Compact Controls */
.forms-control {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 15px;
}

.search-box {
    flex: 1;
    min-width: 180px;
}

.forms-info {
    color: #6c757d;
    font-size: 0.8rem;
    white-space: nowrap;
    margin-left: 10px;
}

.input-group-sm .form-control {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.input-group-sm .input-group-text {
    padding: 0.25rem 0.5rem;
}

/* Scrollbar styling */
.forms-grid-compact::-webkit-scrollbar {
    width: 6px;
}

.forms-grid-compact::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.forms-grid-compact::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.forms-grid-compact::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Responsive adjustments */
@media (max-width: 576px) {
    .forms-control {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .search-box {
        min-width: auto;
    }
    
    .forms-info {
        margin-left: 0;
        text-align: center;
    }
    
    .form-card-compact {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .form-actions-compact {
        align-self: flex-end;
    }
}
</style>