<?php
ob_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { header('Location: document_list.php'); exit; }

$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT dr.*, dt.type_name, ds1.section_name as from_section, ds1.section_code as from_code, ds2.section_name as to_section, ds2.section_code as to_code
    FROM document_records dr
    LEFT JOIN document_types dt ON dr.document_type_id = dt.id
    LEFT JOIN document_sections ds1 ON dr.from_section_id = ds1.id
    LEFT JOIN document_sections ds2 ON dr.forwarded_to_section_id = ds2.id
    WHERE dr.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();

if (!$doc) { header('Location: document_list.php'); exit; }

$kind_colors = ['incoming' => '#0d6efd', 'outgoing' => '#198754', 'internal' => '#6f42c1'];
$kind_color = $kind_colors[$doc['kind']] ?? '#1a3c5e';
$kind_icons = ['incoming' => 'fa-inbox', 'outgoing' => 'fa-paper-plane', 'internal' => 'fa-exchange-alt'];
$kind_icon = $kind_icons[$doc['kind']] ?? 'fa-file-alt';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View Document #<?= $doc['id'] ?> | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        :root { --doc-primary: #1a3c5e; }
        .doc-header-band {
            background: linear-gradient(135deg, #1a3c5e 0%, <?= $kind_color ?> 100%);
            color: #fff; border-radius: 12px 12px 0 0; padding: 28px 32px;
        }
        .doc-kind-icon { width:60px;height:60px;border-radius:14px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:26px; }
        .doc-meta-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; color:#6c757d; font-weight:600; margin-bottom:2px; }
        .doc-meta-value { font-size:.94rem; font-weight:500; }
        .doc-detail-card { border-radius:0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08); border:1px solid #e9ecef; border-top:none; }
        .info-section { padding: 24px 32px; border-bottom: 1px solid #f0f0f0; }
        .info-section:last-child { border-bottom: none; }
        .info-section-title { font-size:.78rem; text-transform:uppercase; letter-spacing:.1em; color:#6c757d; font-weight:700; margin-bottom:16px; }
        .kind-badge { padding:4px 14px;border-radius:20px;font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em; }
        .kind-incoming { background:#dbeafe;color:#1d4ed8; }
        .kind-outgoing { background:#dcfce7;color:#166534; }
        .kind-internal { background:#ede9fe;color:#5b21b6; }
        .status-badge { padding:4px 14px;border-radius:20px;font-size:.76rem;font-weight:700; }
        .status-pending { background:#ffedd5;color:#c2410c; }
        .status-received { background:#dbeafe;color:#1d4ed8; }
        .status-returned { background:#fce7f3;color:#9d174d; }
        .status-completed { background:#d1fae5;color:#065f46; }
        .status-archived { background:#f3f4f6;color:#374151; }
        .flow-arrow { display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#adb5bd;margin:0 12px; }
        .flow-node { flex:1;background:#f8f9fa;border:1px solid #e9ecef;border-radius:10px;padding:14px 16px;text-align:center; }
        .flow-node .node-label { font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#6c757d;font-weight:600; }
        .flow-node .node-value { font-size:.9rem;font-weight:600;color:#212529; }
        .flow-node .node-sub { font-size:.75rem;color:#6c757d; }
        body.dark-mode .info-section { border-color: var(--card-border); }
        body.dark-mode .doc-detail-card { border-color: var(--card-border); background: var(--card-bg); }
        body.dark-mode .flow-node { background: var(--table-stripe); border-color: var(--card-border); }
        body.dark-mode .flow-node .node-value { color: var(--text-primary); }
        body.dark-mode .flow-node .node-sub { color: var(--text-muted); }
        body.dark-mode .doc-meta-label { color: var(--text-muted); }
        body.dark-mode .info-section-title { color: var(--text-muted); }
        body.dark-mode .kind-incoming { background: #1e3a5f; color: #93c5fd; }
        body.dark-mode .kind-outgoing { background: #14532d; color: #86efac; }
        body.dark-mode .kind-internal { background: #2e1065; color: #c4b5fd; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_document.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0" style="font-size:1.3rem;font-weight:700;color:var(--doc-primary);">
                            <i class="fas fa-file-alt mr-2"></i>Document Detail
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="document_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="document_list.php">Documents</a></li>
                            <li class="breadcrumb-item active">#<?= $doc['id'] ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <!-- Header Band -->
                        <div class="doc-header-band">
                            <div class="d-flex align-items-center">
                                <div class="doc-kind-icon mr-4">
                                    <i class="fas <?= $kind_icon ?>"></i>
                                </div>
                                <div>
                                    <div style="font-size:.8rem;opacity:.7;font-weight:500;text-transform:uppercase;letter-spacing:.08em;">
                                        <?= htmlspecialchars($doc['type_name'] ?? 'Document') ?>
                                    </div>
                                    <div style="font-size:1.25rem;font-weight:700;line-height:1.3;max-width:500px;">
                                        <?= htmlspecialchars($doc['document_name']) ?>
                                    </div>
                                    <div class="mt-2 d-flex align-items-center gap-3" style="gap:10px;">
                                        <code style="background:rgba(255,255,255,.2);color:#fff;padding:2px 10px;border-radius:6px;font-size:.85rem;">
                                            <?= htmlspecialchars($doc['document_number']) ?>
                                        </code>
                                        <span class="kind-badge kind-<?= $doc['kind'] ?> ml-2"><?= ucfirst($doc['kind']) ?></span>
                                        <span class="status-badge status-<?= $doc['status'] ?> ml-1"><?= ucfirst($doc['status']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detail Card -->
                        <div class="doc-detail-card">
                            <!-- Document Flow -->
                            <div class="info-section">
                                <div class="info-section-title"><i class="fas fa-route mr-1"></i> Document Flow</div>
                                <div class="d-flex align-items-center">
                                    <div class="flow-node">
                                        <div class="node-label">From</div>
                                        <div class="node-value"><?= htmlspecialchars($doc['forwarded_by_name'] ?: '—') ?></div>
                                        <div class="node-sub"><?= htmlspecialchars($doc['from_section'] ?: 'External / Not Specified') ?></div>
                                    </div>
                                    <div class="flow-arrow"><i class="fas fa-long-arrow-alt-right"></i></div>
                                    <div class="flow-node">
                                        <div class="node-label">To</div>
                                        <div class="node-value"><?= htmlspecialchars($doc['forwarded_to']) ?></div>
                                        <div class="node-sub"><?= htmlspecialchars($doc['to_section'] ?: 'Not Specified') ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dates -->
                            <div class="info-section">
                                <div class="info-section-title"><i class="fas fa-calendar-alt mr-1"></i> Dates & Timeline</div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="doc-meta-label">Date & Time Forwarded</div>
                                        <div class="doc-meta-value">
                                            <i class="fas fa-calendar mr-1 text-primary"></i>
                                            <?= date('F d, Y h:i A', strtotime($doc['date_forwarded'])) ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="doc-meta-label">Date Received</div>
                                        <div class="doc-meta-value">
                                            <?php if ($doc['date_received']): ?>
                                            <i class="fas fa-check-circle mr-1 text-success"></i>
                                            <?= date('F d, Y h:i A', strtotime($doc['date_received'])) ?>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="doc-meta-label">Record Created</div>
                                        <div class="doc-meta-value"><?= date('F d, Y h:i A', strtotime($doc['created_at'])) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="doc-meta-label">Last Updated</div>
                                        <div class="doc-meta-value"><?= date('F d, Y h:i A', strtotime($doc['updated_at'])) ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="info-section">
                                <div class="info-section-title"><i class="fas fa-comment-alt mr-1"></i> Remarks</div>
                                <div style="background:#f8f9fa;border-radius:8px;padding:14px;min-height:60px;font-size:.9rem;">
                                    <?= $doc['remarks'] ? htmlspecialchars($doc['remarks']) : '<span class="text-muted">No remarks provided.</span>' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Actions -->
                    <div class="col-lg-4 mb-4">
                        <div class="card" style="border-radius:12px;">
                            <div class="card-header"><strong>Actions</strong></div>
                            <div class="card-body">
                                <a href="document_edit.php?id=<?= $doc['id'] ?>" class="btn btn-warning btn-block mb-2">
                                    <i class="fas fa-pencil-alt mr-2"></i> Edit Document
                                </a>
                                <a href="document_list.php" class="btn btn-outline-secondary btn-block mb-2">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                                </a>
                                <button class="btn btn-outline-primary btn-block mb-2" onclick="window.print()">
                                    <i class="fas fa-print mr-2"></i> Print Record
                                </button>
                                <hr>
                                <!-- Quick Status Update -->
                                <div class="doc-meta-label mb-2">Update Status</div>
                                <select class="form-control form-control-sm mb-2" id="quickStatusSelect">
                                    <option value="pending" <?= $doc['status']==='pending'?'selected':'' ?>>Pending</option>
                                    <option value="received" <?= $doc['status']==='received'?'selected':'' ?>>Received</option>
                                    <option value="returned" <?= $doc['status']==='returned'?'selected':'' ?>>Returned</option>
                                    <option value="completed" <?= $doc['status']==='completed'?'selected':'' ?>>Completed</option>
                                    <option value="archived" <?= $doc['status']==='archived'?'selected':'' ?>>Archived</option>
                                </select>
                                <button class="btn btn-success btn-sm btn-block" onclick="updateStatus()">
                                    <i class="fas fa-check mr-1"></i> Update Status
                                </button>
                                <hr>
                                <button class="btn btn-danger btn-block btn-sm" onclick="deleteDocument(<?= $doc['id'] ?>)">
                                    <i class="fas fa-trash mr-2"></i> Delete Document
                                </button>
                            </div>
                        </div>

                        <!-- Meta Card -->
                        <div class="card mt-3" style="border-radius:12px;">
                            <div class="card-header"><strong>Document Info</strong></div>
                            <div class="card-body py-3">
                                <div class="mb-3">
                                    <div class="doc-meta-label">Document ID</div>
                                    <div class="doc-meta-value">#<?= $doc['id'] ?></div>
                                </div>
                                <div class="mb-3">
                                    <div class="doc-meta-label">Document Number</div>
                                    <div class="doc-meta-value"><code><?= htmlspecialchars($doc['document_number']) ?></code></div>
                                </div>
                                <div class="mb-3">
                                    <div class="doc-meta-label">Document Type</div>
                                    <div class="doc-meta-value"><?= htmlspecialchars($doc['type_name'] ?? '—') ?></div>
                                </div>
                                <div class="mb-3">
                                    <div class="doc-meta-label">Kind</div>
                                    <span class="kind-badge kind-<?= $doc['kind'] ?>"><?= ucfirst($doc['kind']) ?></span>
                                </div>
                                <div>
                                    <div class="doc-meta-label">Status</div>
                                    <span class="status-badge status-<?= $doc['status'] ?>"><?= ucfirst($doc['status']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<script>
function updateStatus() {
    const status = $('#quickStatusSelect').val();
    $.post('document_actions.php', { action: 'update_status', id: <?= $doc['id'] ?>, status: status }, function(res) {
        try {
            const r = JSON.parse(res);
            if (r.success) {
                toastr.success('Status updated to: ' + status);
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error(r.message || 'Update failed.');
            }
        } catch(e) { toastr.error('Error.'); }
    });
}

function deleteDocument(id) {
    Swal.fire({
        title: 'Delete Document?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('document_actions.php', { action: 'delete', id: id }, function(res) {
                try {
                    const r = JSON.parse(res);
                    if (r.success) {
                        toastr.success('Document deleted.');
                        setTimeout(() => window.location.href = 'document_list.php', 1000);
                    } else {
                        toastr.error(r.message || 'Delete failed.');
                    }
                } catch(e) { toastr.error('Error.'); }
            });
        }
    });
}
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>