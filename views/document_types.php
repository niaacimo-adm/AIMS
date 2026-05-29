<?php
ob_start();
date_default_timezone_set('Asia/Manila');
require_once '../includes/auth.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$page_title = 'Document Types';

// Check if current user is Masteradmin (role_id = 1)
$logged_emp_id = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
$isMasteradmin = false;
if ($logged_emp_id) {
    $maStmt = $db->prepare("SELECT 1 FROM users u JOIN user_roles ur ON u.role_id = ur.id WHERE u.employee_id = ? AND ur.id = 1 LIMIT 1");
    if ($maStmt) {
        $maStmt->bind_param("i", $logged_emp_id);
        $maStmt->execute();
        $isMasteradmin = $maStmt->get_result()->num_rows > 0;
    }
    if (!$isMasteradmin) {
        $session_user_id = (int)($_SESSION['user_id'] ?? 0);
        if ($session_user_id) {
            $maFb = $db->prepare("SELECT 1 FROM users u JOIN user_roles ur ON u.role_id = ur.id WHERE u.id = ? AND ur.id = 1 LIMIT 1");
            if ($maFb) { $maFb->bind_param("i", $session_user_id); $maFb->execute(); $isMasteradmin = $maFb->get_result()->num_rows > 0; }
        }
    }
}

// Fetch all document types
$types_res = $db->query("SELECT id, type_name, description, created_at FROM document_types ORDER BY type_name ASC");
$types_arr = [];
if ($types_res) {
    while ($r = $types_res->fetch_assoc()) { $types_arr[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* ── Design tokens (matching document_list.php palette) ── */
        :root {
            --green:        #24e78f;
            --green-dark:   #2a9863;
            --green-mid:    #1a7a4e;
            --navy:         #1a3c5e;
            --page-bg:      #f4f6f9;
            --card-radius:  12px;
            --shadow-card:  0 2px 12px rgba(0,0,0,.08);
        }

        body { background: var(--page-bg); }

        /* ── Page header ── */
        .page-header-band {
            background: linear-gradient(135deg, var(--navy) 0%, var(--green-mid) 100%);
            border-radius: var(--card-radius);
            padding: 22px 28px;
            color: #fff;
            margin-bottom: 22px;
            box-shadow: var(--shadow-card);
        }
        .page-header-band h4 { font-weight: 700; margin: 0; font-size: 1.25rem; letter-spacing: .02em; }
        .page-header-band p  { margin: 4px 0 0; font-size: .84rem; opacity: .78; }

        /* ── Card ── */
        .dt-card {
            background: #fff;
            border-radius: var(--card-radius);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }
        .dt-card-header {
            padding: 14px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .dt-card-header-title { font-weight: 700; font-size: .95rem; color: var(--navy); }

        /* ── Table ── */
        #typesTable thead th {
            background: var(--navy);
            color: #fff;
            font-size: .8rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            border: none;
            padding: 10px 14px;
            white-space: nowrap;
        }
        #typesTable tbody td { padding: 10px 14px; vertical-align: middle; font-size: .86rem; color: #374151; }
        #typesTable tbody tr:hover { background: #f0faf5; }

        /* ── Badge for type name ── */
        .type-name-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e9f5ef;
            color: var(--green-mid);
            font-weight: 700;
            font-size: .82rem;
            border-radius: 20px;
            padding: 4px 12px;
            border: 1px solid rgba(42,152,99,.25);
        }

        /* ── Action buttons ── */
        .btn-act { border-radius: 6px; padding: 4px 11px; font-size: .78rem; font-weight: 600; border: none; cursor: pointer; transition: all .18s; }
        .btn-act-edit   { background: #fff3cd; color: #856404; }
        .btn-act-edit:hover   { background: #fde68a; }
        .btn-act-delete { background: #fee2e2; color: #991b1b; }
        .btn-act-delete:hover { background: #fca5a5; }

        /* ── Add/Edit modal ── */
        .modal-header-green { background: linear-gradient(135deg, var(--navy), var(--green-mid)); color: #fff; }
        .form-label-bold { font-weight: 600; font-size: .87rem; color: #374151; margin-bottom: 5px; }
        .form-control:focus { border-color: var(--green-dark); box-shadow: 0 0 0 .2rem rgba(42,152,99,.2); }

        /* ── Empty state ── */
        .empty-state { padding: 50px 20px; text-align: center; color: #9ca3af; }
        .empty-state i { font-size: 2.5rem; opacity: .35; margin-bottom: 12px; display: block; }

        /* DataTables tweaks */
        .dataTables_wrapper .dataTables_filter input { border-radius: 8px; border: 1px solid #d1d5db; padding: 5px 12px; font-size: .85rem; }
        .dataTables_wrapper .dataTables_length select { border-radius: 8px; border: 1px solid #d1d5db; padding: 4px 8px; font-size: .85rem; }
        div.dataTables_wrapper div.dataTables_paginate ul.pagination .page-item.active .page-link { background-color: var(--green-dark); border-color: var(--green-dark); }
        div.dataTables_wrapper div.dataTables_paginate ul.pagination .page-link { color: var(--green-dark); }

        /* Dark mode */
        body.dark-mode .dt-card  { background: #1e2a38; color: #e2e8f0; }
        body.dark-mode .dt-card-header { border-bottom-color: #2d3e50; }
        body.dark-mode .dt-card-header-title { color: #93c5fd; }
        body.dark-mode #typesTable tbody td  { color: #cbd5e1; }
        body.dark-mode #typesTable tbody tr:hover { background: #253244; }
        body.dark-mode .type-name-badge { background: #1e3a2a; color: #6ee7b7; border-color: rgba(110,231,183,.2); }
        body.dark-mode .form-control { background: #253244; border-color: #2d3e50; color: #e2e8f0; }
        body.dark-mode .modal-content { background: #1e2a38; color: #e2e8f0; }
        body.dark-mode .modal-footer { background: #172030; border-top-color: #2d3e50; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_document.php'; ?>

    <div class="content-wrapper" style="min-height:100vh;">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-1">
                    <div class="col-sm-6">
                        <h1 class="m-0" style="font-size:1.3rem;font-weight:700;color:var(--navy);">
                            <i class="fas fa-tags mr-2" style="color:var(--green-dark);"></i>Document Types
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="document_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Document Types</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- Page header -->
                <div class="page-header-band">
                    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:10px;">
                        <div>
                            <h4><i class="fas fa-tags mr-2"></i>Document Types</h4>
                            <p>Manage the list of document types used across all document records.</p>
                        </div>
                        <button class="btn btn-light font-weight-bold" id="openAddTypeBtn" style="border-radius:8px;font-size:.85rem;">
                            <i class="fas fa-plus-circle mr-1" style="color:var(--green-dark);"></i> Add Document Type
                        </button>
                    </div>
                </div>

                <!-- Stats row -->
                <div class="row mb-3">
                    <div class="col-md-4 col-sm-6 mb-3">
                        <div class="dt-card p-3 d-flex align-items-center" style="gap:14px;">
                            <div style="width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#1a3c5e,#2a9863);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-tags text-white" style="font-size:1.1rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:1.55rem;font-weight:800;color:var(--navy);line-height:1;"><?= count($types_arr) ?></div>
                                <div style="font-size:.77rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Total Types</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main table card -->
                <div class="dt-card">
                    <div class="dt-card-header">
                        <span class="dt-card-header-title"><i class="fas fa-list mr-2" style="color:var(--green-dark);"></i>All Document Types</span>
                        <div class="d-flex" style="gap:8px;">
                            <button class="btn btn-sm btn-outline-secondary" onclick="refreshTypes()" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-3">
                        <?php if (empty($types_arr)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <div style="font-size:.95rem;font-weight:600;color:#6b7280;">No document types found</div>
                            <div style="font-size:.8rem;margin-top:4px;">Click <strong>Add Document Type</strong> to get started.</div>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table id="typesTable" class="table table-bordered table-hover" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th>Type Name</th>
                                        <th>Description</th>
                                        <!-- <th style="width:140px;">Date Added</th> -->
                                        <th style="width:110px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($types_arr as $i => $t): ?>
                                    <tr data-id="<?= $t['id'] ?>">
                                        <td class="text-center text-muted" style="font-size:.78rem;"><?= $i + 1 ?></td>
                                        <td>
                                            <span class="type-name-badge">
                                                <i class="fas fa-tag" style="font-size:.7rem;"></i>
                                                <?= htmlspecialchars($t['type_name']) ?>
                                            </span>
                                        </td>
                                        <td style="color:#6b7280;font-size:.83rem;"><?= $t['description'] ? htmlspecialchars($t['description']) : '<span class="text-muted fst-italic" style="font-size:.78rem;">—</span>' ?></td>
                                       
                                        <td>
                                            <div class="d-flex" style="gap:5px;">
                                                <button class="btn-act btn-act-edit"
                                                        onclick="openEditType(<?= $t['id'] ?>, <?= htmlspecialchars(json_encode($t['type_name'])) ?>, <?= htmlspecialchars(json_encode($t['description'] ?? '')) ?>)"
                                                        title="Edit">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <button class="btn-act btn-act-delete"
                                                        onclick="confirmDeleteType(<?= $t['id'] ?>, <?= htmlspecialchars(json_encode($t['type_name'])) ?>)"
                                                        title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
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

            </div><!-- /.container-fluid -->
        </div><!-- /.content -->
    </div><!-- /.content-wrapper -->

    <?php include '../includes/mainfooter.php'; ?>
</div><!-- /.wrapper -->

<!-- ══════════════ ADD DOCUMENT TYPE MODAL ══════════════ -->
<div class="modal fade" id="addTypeModal" tabindex="-1" role="dialog" aria-labelledby="addTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header modal-header-green">
                <h5 class="modal-title" id="addTypeModalLabel"><i class="fas fa-plus-circle mr-2"></i>Add Document Type</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label-bold">Type Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="addTypeName" placeholder="e.g. Memorandum" maxlength="100" required>
                    <small class="text-muted">Maximum 100 characters.</small>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label-bold">Description <small class="text-muted font-weight-normal">(optional)</small></label>
                    <textarea class="form-control" id="addTypeDesc" rows="3" placeholder="Brief description of this document type..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="saveAddTypeBtn">
                    <i class="fas fa-save mr-1"></i> Save Type
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════ EDIT DOCUMENT TYPE MODAL ══════════════ -->
<div class="modal fade" id="editTypeModal" tabindex="-1" role="dialog" aria-labelledby="editTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#856404;color:#fff;">
                <h5 class="modal-title" id="editTypeModalLabel"><i class="fas fa-pencil-alt mr-2"></i>Edit Document Type</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editTypeId">
                <div class="form-group">
                    <label class="form-label-bold">Type Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="editTypeName" placeholder="e.g. Memorandum" maxlength="100" required>
                    <small class="text-muted">Maximum 100 characters.</small>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label-bold">Description <small class="text-muted font-weight-normal">(optional)</small></label>
                    <textarea class="form-control" id="editTypeDesc" rows="3" placeholder="Brief description of this document type..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="saveEditTypeBtn">
                    <i class="fas fa-save mr-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Dark mode
    if (localStorage.getItem('darkMode') === '1') $('body').addClass('dark-mode');
    $('#darkModeToggle').on('click', function(e) {
        e.preventDefault();
        $('body').toggleClass('dark-mode');
        localStorage.setItem('darkMode', $('body').hasClass('dark-mode') ? '1' : '0');
    });

    // DataTable
    if ($.fn.DataTable.isDataTable('#typesTable')) $('#typesTable').DataTable().destroy();
    $('#typesTable').DataTable({
        pageLength: 25,
        order: [[1, 'asc']],
        columnDefs: [
            { orderable: false, targets: [0, 3] },
            { searchable: false, targets: [0] }
        ],
        dom: '<"row align-items-center mb-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row mt-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            search: '', searchPlaceholder: 'Search types...',
            lengthMenu: 'Show _MENU_ entries',
            zeroRecords: '<div class="text-center py-4 text-muted"><i class="fas fa-search fa-2x d-block mb-2" style="opacity:.3;"></i>No matching document types</div>',
            emptyTable: '<div class="text-center py-4 text-muted"><i class="fas fa-tags fa-2x d-block mb-2" style="opacity:.3;"></i>No document types found</div>',
        },
        rowCallback: function(row, data, idx) {
            $('td:first', row).html('<span style="color:#9ca3af;font-size:.78rem;">' + (idx + 1) + '</span>');
        }
    });

    // Open Add modal
    $('#openAddTypeBtn').on('click', function() {
        $('#addTypeName').val('');
        $('#addTypeDesc').val('');
        $('#addTypeModal').modal('show');
        setTimeout(() => $('#addTypeName').focus(), 400);
    });

    // Save new type
    $('#saveAddTypeBtn').on('click', function() {
        const name = $('#addTypeName').val().trim();
        const desc = $('#addTypeDesc').val().trim();
        if (!name) { $('#addTypeName').focus(); return Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter the type name.' }); }
        const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.post('document_actions.php', { action: 'add_type', type_name: name, description: desc }, function(r) {
            if (r.success) {
                $('#addTypeModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Type Added!', text: 'The document type has been added.', timer: 1600, showConfirmButton: false, timerProgressBar: true })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Failed to add type.' });
            }
        }, 'json').fail(function() {
            Swal.fire({ icon: 'error', title: 'Server Error', text: 'An unexpected error occurred.' });
        }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Type'));
    });

    // Save edit
    $('#saveEditTypeBtn').on('click', function() {
        const id   = $('#editTypeId').val();
        const name = $('#editTypeName').val().trim();
        const desc = $('#editTypeDesc').val().trim();
        if (!name) { $('#editTypeName').focus(); return Swal.fire({ icon: 'warning', title: 'Required', text: 'Type name cannot be empty.' }); }
        const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.post('document_actions.php', { action: 'update_type', id: id, type_name: name, description: desc }, function(r) {
            if (r.success) {
                $('#editTypeModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Updated!', text: 'Document type has been updated.', timer: 1600, showConfirmButton: false, timerProgressBar: true })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Failed to update type.' });
            }
        }, 'json').fail(function() {
            Swal.fire({ icon: 'error', title: 'Server Error', text: 'An unexpected error occurred.' });
        }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Changes'));
    });

    // Enter key submits
    $('#addTypeName, #addTypeDesc').on('keydown', function(e) { if (e.ctrlKey && e.key === 'Enter') $('#saveAddTypeBtn').click(); });
    $('#editTypeName, #editTypeDesc').on('keydown', function(e) { if (e.ctrlKey && e.key === 'Enter') $('#saveEditTypeBtn').click(); });
});

function openEditType(id, name, desc) {
    $('#editTypeId').val(id);
    $('#editTypeName').val(name);
    $('#editTypeDesc').val(desc);
    $('#editTypeModal').modal('show');
    setTimeout(() => $('#editTypeName').focus(), 400);
}

function confirmDeleteType(id, name) {
    Swal.fire({
        icon: 'warning',
        title: 'Delete Document Type?',
        html: `Are you sure you want to delete <strong>${escHtml(name)}</strong>?<br><small class="text-danger">Existing document records using this type will not be affected.</small>`,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Yes, Delete',
        cancelButtonText: 'Cancel',
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('document_actions.php', { action: 'delete_type', id: id }, function(r) {
            if (r.success) {
                Swal.fire({ icon: 'success', title: 'Deleted!', text: 'The document type has been removed.', timer: 1500, showConfirmButton: false, timerProgressBar: true })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: r.message || 'Failed to delete type.' });
            }
        }, 'json').fail(function() {
            Swal.fire({ icon: 'error', title: 'Server Error', text: 'An unexpected error occurred.' });
        });
    });
}

function refreshTypes() { location.reload(); }

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>