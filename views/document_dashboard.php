<?php
ob_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Fetch counts for stats cards
$counts = ['total' => 0, 'incoming' => 0, 'outgoing' => 0, 'internal' => 0, 'pending' => 0, 'completed' => 0];

$stats_query = "SELECT
    COUNT(*) as total,
    SUM(kind = 'incoming') as incoming,
    SUM(kind = 'outgoing') as outgoing,
    SUM(kind = 'internal') as internal,
    SUM(status = 'pending') as pending,
    SUM(status = 'completed') as completed
FROM document_records";
$result = $db->query($stats_query);
if ($result && $row = $result->fetch_assoc()) {
    $counts = $row;
}

// FIX: was joining 'document_sections ds1/ds2' which doesn't exist.
// Correct table is 'section' with PK 'section_id'
$recent_query = "
    SELECT dr.*, dt.type_name,
           s1.section_name AS from_section,
           s2.section_name AS to_section
    FROM document_records dr
    LEFT JOIN document_types dt ON dr.document_type_id         = dt.id
    LEFT JOIN section        s1 ON dr.from_section_id          = s1.section_id
    LEFT JOIN section        s2 ON dr.forwarded_to_section_id  = s2.section_id
    ORDER BY dr.created_at DESC
    LIMIT 10
";
$recent_docs = $db->query($recent_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Monitoring Records | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        :root {
            --doc-primary: #1a3c5e;
            --doc-incoming: #0d6efd;
            --doc-outgoing: #198754;
            --doc-internal: #6f42c1;
            --doc-pending: #fd7e14;
            --doc-completed: #20c997;
        }
        .stat-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            transition: transform .2s, box-shadow .2s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.13); }
        .stat-icon {
            width: 54px; height: 54px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: #fff;
        }
        .stat-number { font-size: 2rem; font-weight: 700; line-height: 1; }
        .stat-label  { font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; color: #6c757d; }
        .kind-badge  { padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .kind-incoming  { background: #dbeafe; color: #1d4ed8; }
        .kind-outgoing  { background: #dcfce7; color: #166534; }
        .kind-internal  { background: #ede9fe; color: #5b21b6; }
        .status-badge { padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600; }
        .status-pending   { background: #ffedd5; color: #c2410c; }
        .status-received  { background: #dbeafe; color: #1d4ed8; }
        .status-returned  { background: #fce7f3; color: #9d174d; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-archived  { background: #f3f4f6; color: #374151; }
        .action-btn { width: 30px; height: 30px; padding: 0; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; }
        .page-section-title {
            font-size: 1.05rem; font-weight: 700; color: var(--doc-primary);
            border-left: 4px solid var(--doc-primary); padding-left: 10px;
        }
        body.dark-mode .kind-incoming { background: #1e3a5f; color: #93c5fd; }
        body.dark-mode .kind-outgoing { background: #14532d; color: #86efac; }
        body.dark-mode .kind-internal { background: #2e1065; color: #c4b5fd; }
        body.dark-mode .status-pending   { background: #431407; color: #fdba74; }
        body.dark-mode .status-received  { background: #1e3a5f; color: #93c5fd; }
        body.dark-mode .status-completed { background: #064e3b; color: #6ee7b7; }
        body.dark-mode .page-section-title { color: #7aabdf; border-color: #7aabdf; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar_document.php'; ?>

    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0" style="font-size:1.4rem; font-weight:700; color: var(--doc-primary);">
                            <i class="fas fa-file-alt mr-2"></i>Document Monitoring Records
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="document_dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-6 col-md-4 col-lg-2 mb-3">
                        <div class="card stat-card h-100">
                            <div class="card-body p-3 d-flex align-items-center">
                                <div class="stat-icon mr-3" style="background:#1a3c5e;">
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <div>
                                    <div class="stat-number"><?= (int)($counts['total'] ?? 0) ?></div>
                                    <div class="stat-label">Total</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2 mb-3">
                        <div class="card stat-card h-100">
                            <div class="card-body p-3 d-flex align-items-center">
                                <div class="stat-icon mr-3" style="background:var(--doc-incoming);">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <div>
                                    <div class="stat-number"><?= (int)($counts['incoming'] ?? 0) ?></div>
                                    <div class="stat-label">Incoming</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2 mb-3">
                        <div class="card stat-card h-100">
                            <div class="card-body p-3 d-flex align-items-center">
                                <div class="stat-icon mr-3" style="background:var(--doc-outgoing);">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div>
                                    <div class="stat-number"><?= (int)($counts['outgoing'] ?? 0) ?></div>
                                    <div class="stat-label">Outgoing</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2 mb-3">
                        <div class="card stat-card h-100">
                            <div class="card-body p-3 d-flex align-items-center">
                                <div class="stat-icon mr-3" style="background:var(--doc-internal);">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div>
                                    <div class="stat-number"><?= (int)($counts['internal'] ?? 0) ?></div>
                                    <div class="stat-label">Internal</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2 mb-3">
                        <div class="card stat-card h-100">
                            <div class="card-body p-3 d-flex align-items-center">
                                <div class="stat-icon mr-3" style="background:var(--doc-pending);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <div class="stat-number"><?= (int)($counts['pending'] ?? 0) ?></div>
                                    <div class="stat-label">Pending</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2 mb-3">
                        <div class="card stat-card h-100">
                            <div class="card-body p-3 d-flex align-items-center">
                                <div class="stat-icon mr-3" style="background:var(--doc-completed);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <div class="stat-number"><?= (int)($counts['completed'] ?? 0) ?></div>
                                    <div class="stat-label">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Navigation Cards -->
                <div class="row mb-4">
                    <div class="col-12">
                        <p class="page-section-title mb-3">Quick Access</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="document_list.php?kind=incoming" class="text-decoration-none">
                            <div class="card stat-card" style="border-left: 4px solid var(--doc-incoming) !important;">
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon mr-3" style="background:var(--doc-incoming); width:44px;height:44px;font-size:18px;">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight:700; font-size:.95rem; color:var(--doc-incoming);">Incoming Documents</div>
                                            <div style="font-size:.78rem; color:#6c757d;">Documents received from external sources</div>
                                        </div>
                                        <i class="fas fa-chevron-right ml-auto text-muted"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="document_list.php?kind=outgoing" class="text-decoration-none">
                            <div class="card stat-card" style="border-left: 4px solid var(--doc-outgoing) !important;">
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon mr-3" style="background:var(--doc-outgoing); width:44px;height:44px;font-size:18px;">
                                            <i class="fas fa-paper-plane"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight:700; font-size:.95rem; color:var(--doc-outgoing);">Outgoing Documents</div>
                                            <div style="font-size:.78rem; color:#6c757d;">Documents sent to external parties</div>
                                        </div>
                                        <i class="fas fa-chevron-right ml-auto text-muted"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="document_list.php?kind=internal" class="text-decoration-none">
                            <div class="card stat-card" style="border-left: 4px solid var(--doc-internal) !important;">
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon mr-3" style="background:var(--doc-internal); width:44px;height:44px;font-size:18px;">
                                            <i class="fas fa-exchange-alt"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight:700; font-size:.95rem; color:var(--doc-internal);">Internal Documents</div>
                                            <div style="font-size:.78rem; color:#6c757d;">Inter-section communications</div>
                                        </div>
                                        <i class="fas fa-chevron-right ml-auto text-muted"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Recent Documents Table -->
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <p class="page-section-title mb-0">Recent Documents</p>
                        <div>
                            <a href="document_list.php" class="btn btn-sm btn-outline-primary mr-2">
                                <i class="fas fa-list mr-1"></i> View All
                            </a>
                            <a href="document_list.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus mr-1"></i> Add Document
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="recentDocsTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th>Doc No.</th>
                                        <th>Document Name / Particulars</th>
                                        <th>Type</th>
                                        <th>Kind</th>
                                        <th>Forwarded By / Section</th>
                                        <th>Forwarded To / Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($recent_docs && $recent_docs->num_rows > 0):
                                        $i = 1;
                                        while ($doc = $recent_docs->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><code><?= htmlspecialchars($doc['document_number']) ?></code></td>
                                        <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                                            title="<?= htmlspecialchars($doc['document_name']) ?>">
                                            <?= htmlspecialchars($doc['document_name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($doc['type_name'] ?? '—') ?></td>
                                        <td>
                                            <span class="kind-badge kind-<?= $doc['kind'] ?>">
                                                <?= ucfirst($doc['kind']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($doc['forwarded_by_name'] ?? '—') ?></div>
                                            <?php if (!empty($doc['from_section'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($doc['from_section']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($doc['forwarded_to'] ?? '—') ?></div>
                                            <?php
                                            // FIX: guard strtotime against '0000-00-00 00:00:00'
                                            $fwd_ts = strtotime($doc['date_forwarded']);
                                            echo '<small class="text-muted">' . ($fwd_ts ? date('M d, Y h:i A', $fwd_ts) : '—') . '</small>';
                                            ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $doc['status'] ?>">
                                                <?= ucfirst($doc['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="document_view.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-info action-btn" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="document_list.php?edit=<?= $doc['id'] ?>" class="btn btn-sm btn-warning action-btn ml-1" title="Edit">
                                                <i class="fas fa-pencil-alt"></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger action-btn ml-1" onclick="deleteDocument(<?= $doc['id'] ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td colspan="9" class="text-center py-4 text-muted">No documents found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<script>
$(document).ready(function() {
    $('#recentDocsTable').DataTable({
        pageLength: 10,
        ordering: true,
        searching: false,
        paging: false,
        info: false,
        columnDefs: [{ orderable: false, targets: [8] }]
    });
});

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
            // FIX: use 'json' dataType so jQuery auto-parses the response
            $.post('document_actions.php', { action: 'delete', id: id }, function(r) {
                if (r.success) {
                    toastr.success('Document deleted.');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    toastr.error(r.message || 'Delete failed.');
                }
            }, 'json').fail(function(xhr) {
                toastr.error('Server error. Check console.');
                console.error(xhr.responseText);
            });
        }
    });
}
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>