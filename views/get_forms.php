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
                        <a href="download.php?id=<?= (int)$form['id'] ?>" class="btn btn-primary btn-xs" title="Download">
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
/* Hide the built-in search/count header — handled by login.php modal */
.forms-control { display: none !important; }

/* Compact Forms Grid */
.forms-grid-compact {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 5px;
}

/* Compact Form Card — theme-aware */
.form-card-compact {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border: 1px solid var(--fm-border);
    border-radius: 10px;
    background: rgba(36,231,143,.06);
    transition: border-color .2s, background .2s, transform .15s;
    gap: 12px;
}

.form-card-compact:hover {
    border-color: var(--green-mid);
    background: rgba(36,231,143,.10);
    transform: translateY(-1px);
}

[data-theme="light"] .form-card-compact {
    background: rgba(20,133,90,.05);
}

[data-theme="light"] .form-card-compact:hover {
    background: rgba(20,133,90,.09);
    border-color: var(--green-dark);
}

.form-card-header {
    display: flex;
    align-items: center;
    flex: 1;
    gap: 10px;
    min-width: 0;
}

.form-icon-small { flex-shrink: 0; }
.form-icon-small i { font-size: 1.2rem; }

.form-info {
    flex: 1;
    min-width: 0;
}

.form-title {
    color: var(--fm-body-text);
    font-weight: 600;
    margin: 0 0 2px 0;
    font-size: 0.9rem;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.form-desc {
    color: var(--subtitle-color);
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
    border-radius: 6px;
}

/* Override Bootstrap btn colors to match theme */
.form-actions-compact .btn-primary {
    background: var(--green-dark) !important;
    border-color: var(--green-dark) !important;
    color: #fff !important;
}
.form-actions-compact .btn-primary:hover {
    background: var(--green-mid) !important;
    border-color: var(--green-mid) !important;
}
.form-actions-compact .btn-outline-secondary {
    color: var(--fm-body-text) !important;
    border-color: var(--fm-border) !important;
    background: transparent !important;
}
.form-actions-compact .btn-outline-secondary:hover {
    background: rgba(36,231,143,.10) !important;
}

/* Scrollbar styling */
.forms-grid-compact::-webkit-scrollbar { width: 5px; }
.forms-grid-compact::-webkit-scrollbar-track { background: transparent; border-radius: 3px; }
.forms-grid-compact::-webkit-scrollbar-thumb { background: var(--fm-border); border-radius: 3px; }
.forms-grid-compact::-webkit-scrollbar-thumb:hover { background: var(--green-dark); }

/* Responsive adjustments */
@media (max-width: 576px) {
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
