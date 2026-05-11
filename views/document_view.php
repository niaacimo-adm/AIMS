<?php
ob_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { header('Location: document_list.php'); exit; }

$database = new Database();
$db = $database->getConnection();

// FIX: was joining 'document_sections' (wrong table) — correct table is 'section' with PK section_id
$stmt = $db->prepare("
    SELECT dr.*,
           dt.type_name,
           s1.section_name AS from_section, s1.section_code AS from_code,
           s2.section_name AS to_section,   s2.section_code AS to_code,
           us1.unit_name   AS from_unit,
           us2.unit_name   AS to_unit,
           CONCAT(TRIM(e1.first_name),' ',TRIM(e1.last_name)) AS forwarded_by_name_emp,
           CONCAT(TRIM(e2.first_name),' ',TRIM(e2.last_name)) AS forwarded_to_name_emp
    FROM document_records dr
    LEFT JOIN document_types dt  ON dr.document_type_id        = dt.id
    LEFT JOIN section        s1  ON dr.from_section_id         = s1.section_id
    LEFT JOIN section        s2  ON dr.forwarded_to_section_id = s2.section_id
    LEFT JOIN unit_section   us1 ON dr.from_unit_id            = us1.unit_id
    LEFT JOIN unit_section   us2 ON dr.forwarded_to_unit_id    = us2.unit_id
    LEFT JOIN employee       e1  ON dr.forwarded_by_emp_id     = e1.emp_id
    LEFT JOIN employee       e2  ON dr.forwarded_to_emp_id     = e2.emp_id
    WHERE dr.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) { header('Location: document_list.php'); exit; }

$kind_colors = ['incoming' => '#0d6efd', 'outgoing' => '#198754', 'internal' => '#6f42c1'];
$kind_color  = $kind_colors[$doc['kind']] ?? '#1a3c5e';
$kind_icons  = ['incoming' => 'fa-inbox', 'outgoing' => 'fa-paper-plane', 'internal' => 'fa-exchange-alt'];
$kind_icon   = $kind_icons[$doc['kind']] ?? 'fa-file-alt';

// FIX: also correct the forwarding-history query — was joining 'document_sections' instead of 'section'
$fhstmt = $db->prepare("
    SELECT df.*,
           CONCAT(TRIM(eb.first_name),' ',TRIM(eb.last_name)) AS fwd_by_name,
           CONCAT(TRIM(et.first_name),' ',TRIM(et.last_name)) AS fwd_to_name,
           s.section_name  AS to_section_name,
           us.unit_name    AS to_unit_name,
           o.office_name   AS to_office_name
    FROM document_forwards df
    LEFT JOIN employee       eb ON df.fwd_by_emp_id     = eb.emp_id
    LEFT JOIN employee       et ON df.fwd_to_emp_id     = et.emp_id
    LEFT JOIN section        s  ON df.fwd_to_section_id = s.section_id
    LEFT JOIN unit_section   us ON df.fwd_to_unit_id    = us.unit_id
    LEFT JOIN office         o  ON df.fwd_to_office_id  = o.office_id
    WHERE df.document_id = ?
    ORDER BY df.id ASC
");
$fhstmt->bind_param("i", $id);
$fhstmt->execute();
$fwd_history = $fhstmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper: safe date format
function safeDate($dateStr, $format = 'F d, Y h:i A') {
    if (empty($dateStr) || $dateStr === '0000-00-00 00:00:00') return null;
    $ts = strtotime($dateStr);
    return $ts ? date($format, $ts) : null;
}
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
        .status-pending   { background:#ffedd5;color:#c2410c; }
        .status-received  { background:#dbeafe;color:#1d4ed8; }
        .status-returned  { background:#fce7f3;color:#9d174d; }
        .status-completed { background:#d1fae5;color:#065f46; }
        .status-archived  { background:#f3f4f6;color:#374151; }
        .flow-node { flex:1;background:#f8f9fa;border:1px solid #e9ecef;border-radius:10px;padding:14px 16px;text-align:center; }
        .flow-node .node-label { font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#6c757d;font-weight:600; }
        .flow-node .node-value { font-size:.9rem;font-weight:600;color:#212529; }
        .flow-node .node-sub   { font-size:.75rem;color:#6c757d; }
        .flow-arrow { display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#adb5bd;margin:0 12px; }
        /* ── Forwarding Trail ── */
        .fwd-trail { display:flex;flex-direction:column;gap:0; }
        .fwd-trail-item { display:flex;gap:12px;align-items:flex-start; }
        .fwd-trail-connector { display:flex;flex-direction:column;align-items:center;flex-shrink:0;width:32px; }
        .fwd-trail-dot { width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0;z-index:1; }
        .fwd-trail-line { width:2px;flex:1;min-height:16px;background:#e5e7eb;margin:4px 0; }
        .fwd-trail-card { flex:1;background:#fff;border:1px solid #e9ecef;border-radius:10px;padding:12px 14px;margin-bottom:8px;box-shadow:0 1px 4px rgba(0,0,0,.04); }
        .fwd-trail-who { font-size:.88rem;font-weight:600;color:#212529; }
        .fwd-trail-from { color:#374151; }
        .fwd-trail-to   { color:#1a3c5e; }
        .fwd-dest-chip  { display:inline-flex;align-items:center;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:600; }
        .fwd-trail-remarks { font-size:.78rem;color:#6c757d;margin-top:5px;font-style:italic; }
        .fwd-trail-date { font-size:.72rem;color:#6c757d;white-space:nowrap;padding-top:2px; }
        body.dark-mode .fwd-trail-card { background:var(--card-bg);border-color:var(--card-border); }
        body.dark-mode .fwd-trail-line { background:var(--card-border); }
        body.dark-mode .fwd-trail-who  { color:var(--text-primary); }
        body.dark-mode .info-section        { border-color: var(--card-border); }
        body.dark-mode .doc-detail-card     { border-color: var(--card-border); background: var(--card-bg); }
        body.dark-mode .flow-node           { background: var(--table-stripe); border-color: var(--card-border); }
        body.dark-mode .flow-node .node-value { color: var(--text-primary); }
        body.dark-mode .flow-node .node-sub   { color: var(--text-muted); }
        body.dark-mode .doc-meta-label      { color: var(--text-muted); }
        body.dark-mode .info-section-title  { color: var(--text-muted); }
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
                    <!-- Main Content -->
                    <div class="col-lg-8 mb-4">
                        <!-- Header Band -->
                        <div class="doc-header-band">
                            <div class="d-flex align-items-center">
                                <div class="doc-kind-icon mr-4">
                                    <i class="fas <?= $kind_icon ?>"></i>
                                </div>
                                <div style="min-width:0;">
                                    <div style="font-size:.8rem;opacity:.7;font-weight:500;text-transform:uppercase;letter-spacing:.08em;">
                                        <?= htmlspecialchars($doc['type_name'] ?? 'Document') ?>
                                    </div>
                                    <div style="font-size:1.2rem;font-weight:700;line-height:1.3;">
                                        <?= htmlspecialchars($doc['document_name']) ?>
                                    </div>
                                    <div class="mt-2 d-flex flex-wrap align-items-center" style="gap:8px;">
                                        <code style="background:rgba(255,255,255,.2);color:#fff;padding:2px 10px;border-radius:6px;font-size:.82rem;">
                                            <?= htmlspecialchars($doc['document_number']) ?>
                                        </code>
                                        <span class="kind-badge kind-<?= $doc['kind'] ?>"><?= ucfirst($doc['kind']) ?></span>
                                        <span class="status-badge status-<?= $doc['status'] ?>"><?= ucfirst($doc['status']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detail Card -->
                        <div class="doc-detail-card">

                            <!-- Document Flow -->
                            <div class="info-section">
                                <div class="info-section-title"><i class="fas fa-route mr-1"></i> Document Flow</div>
                                <div class="d-flex align-items-stretch">
                                    <div class="flow-node">
                                        <div class="node-label">From</div>
                                        <div class="node-value"><?= htmlspecialchars($doc['forwarded_by_name_emp'] ?: ($doc['forwarded_by_name'] ?: '—')) ?></div>
                                        <div class="node-sub"><?= htmlspecialchars($doc['from_section'] ?: 'External / Not Specified') ?></div>
                                        <?php if (!empty($doc['from_unit'])): ?>
                                        <div class="node-sub"><?= htmlspecialchars($doc['from_unit']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flow-arrow"><i class="fas fa-long-arrow-alt-right"></i></div>
                                    <div class="flow-node">
                                        <div class="node-label">To</div>
                                        <div class="node-value"><?= htmlspecialchars($doc['forwarded_to_name_emp'] ?: ($doc['forwarded_to'] ?: '—')) ?></div>
                                        <div class="node-sub"><?= htmlspecialchars($doc['to_section'] ?: 'Not Specified') ?></div>
                                        <?php if (!empty($doc['to_unit'])): ?>
                                        <div class="node-sub"><?= htmlspecialchars($doc['to_unit']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Forwarding History -->
                            <div class="info-section">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="info-section-title mb-0"><i class="fas fa-history mr-1"></i> Forwarding History <span class="badge badge-primary ml-1"><?= count($fwd_history) ?></span></div>
                                    <button class="btn btn-sm btn-success" onclick="openForwardModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')">
                                        <i class="fas fa-share mr-1"></i> Forward Again
                                    </button>
                                </div>
                                <?php if (!empty($fwd_history)): ?>
                                <div class="fwd-trail">
                                <?php foreach ($fwd_history as $idx => $h):
                                    $fwdDateStr = safeDate($h['fwd_date'], 'M d, Y h:i A') ?? null;
                                    // Determine destination label
                                    if (!empty($h['to_office_name'])) {
                                        $destIcon  = 'fa-star';
                                        $destColor = '#0d6efd';
                                        $destBg    = '#dbeafe';
                                        $destLabel = $h['to_office_name'];
                                        $destSub   = 'IMO Office';
                                    } elseif (!empty($h['to_section_name'])) {
                                        $destIcon  = 'fa-building';
                                        $destColor = '#198754';
                                        $destBg    = '#d1fae5';
                                        $destLabel = $h['to_section_name'];
                                        $destSub   = !empty($h['to_unit_name']) ? $h['to_unit_name'] : 'Entire Section';
                                    } else {
                                        $destIcon  = 'fa-user';
                                        $destColor = '#6f42c1';
                                        $destBg    = '#ede9fe';
                                        $destLabel = $h['fwd_to_name'] ?: '—';
                                        $destSub   = 'Direct';
                                    }
                                ?>
                                    <div class="fwd-trail-item">
                                        <div class="fwd-trail-connector">
                                            <div class="fwd-trail-dot" style="background:<?= $destColor ?>;box-shadow:0 0 0 3px <?= $destBg ?>;"><?= $idx + 1 ?></div>
                                            <?php if ($idx < count($fwd_history) - 1): ?><div class="fwd-trail-line"></div><?php endif; ?>
                                        </div>
                                        <div class="fwd-trail-card">
                                            <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:6px;">
                                                <div>
                                                    <div class="fwd-trail-who">
                                                        <span class="fwd-trail-from"><?= htmlspecialchars($h['fwd_by_name'] ?: '—') ?></span>
                                                        <i class="fas fa-long-arrow-alt-right mx-2 text-muted"></i>
                                                        <span class="fwd-trail-to"><?= htmlspecialchars($h['fwd_to_name'] ?: '—') ?></span>
                                                    </div>
                                                    <div class="mt-1 d-flex align-items-center flex-wrap" style="gap:5px;">
                                                        <span class="fwd-dest-chip" style="background:<?= $destBg ?>;color:<?= $destColor ?>;">
                                                            <i class="fas <?= $destIcon ?> mr-1"></i><?= htmlspecialchars($destLabel) ?>
                                                        </span>
                                                        <?php if ($destSub !== 'Entire Section' || !empty($h['to_unit_name'])): ?>
                                                        <span class="fwd-dest-chip" style="background:#f3f4f6;color:#374151;">
                                                            <?= htmlspecialchars($destSub) ?>
                                                        </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($h['fwd_remarks'])): ?>
                                                    <div class="fwd-trail-remarks"><i class="fas fa-comment-alt mr-1 text-muted" style="font-size:.65rem;"></i><?= htmlspecialchars($h['fwd_remarks']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="fwd-trail-date">
                                                    <?php if ($fwdDateStr): ?>
                                                    <i class="fas fa-clock mr-1"></i><?= $fwdDateStr ?>
                                                    <?php else: ?>
                                                    <span class="text-muted">No date recorded</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-3 text-muted" style="background:#f8f9fa;border-radius:8px;">
                                    <i class="fas fa-share-alt fa-2x mb-2 d-block" style="opacity:.3;"></i>
                                    No forwarding history yet.
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Dates -->
                            <div class="info-section">
                                <div class="info-section-title"><i class="fas fa-calendar-alt mr-1"></i> Dates &amp; Timeline</div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="doc-meta-label">Date &amp; Time Forwarded</div>
                                        <div class="doc-meta-value">
                                            <?php $fwdDate = safeDate($doc['date_forwarded']); ?>
                                            <?php if ($fwdDate): ?>
                                            <i class="fas fa-calendar mr-1 text-primary"></i><?= $fwdDate ?>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="doc-meta-label">Date Received</div>
                                        <div class="doc-meta-value">
                                            <?php $rcvDate = safeDate($doc['date_received']); ?>
                                            <?php if ($rcvDate): ?>
                                            <i class="fas fa-check-circle mr-1 text-success"></i><?= $rcvDate ?>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="doc-meta-label">Record Created</div>
                                        <div class="doc-meta-value"><?= safeDate($doc['created_at']) ?? '—' ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="doc-meta-label">Last Updated</div>
                                        <div class="doc-meta-value"><?= safeDate($doc['updated_at']) ?? '—' ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="info-section">
                                <div class="info-section-title"><i class="fas fa-comment-alt mr-1"></i> Remarks</div>
                                <div style="background:#f8f9fa;border-radius:8px;padding:14px;min-height:60px;font-size:.9rem;">
                                    <?= !empty($doc['remarks']) ? htmlspecialchars($doc['remarks']) : '<span class="text-muted">No remarks provided.</span>' ?>
                                </div>
                            </div>

                        </div><!-- /.doc-detail-card -->
                    </div><!-- /.col-lg-8 -->

                    <!-- Sidebar Actions -->
                    <div class="col-lg-4 mb-4">
                        <div class="card" style="border-radius:12px;">
                            <div class="card-header"><strong>Actions</strong></div>
                            <div class="card-body">
                                <button class="btn btn-success btn-block mb-2"
                                    onclick="openForwardModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')">
                                    <i class="fas fa-share mr-2"></i> Forward Document
                                </button>
                                <button class="btn btn-warning btn-block mb-2"
                                    onclick="editDocumentFromView(<?= $doc['id'] ?>)">
                                    <i class="fas fa-pencil-alt mr-2"></i> Edit Document
                                </button>
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
                                    <option value="pending"   <?= $doc['status']==='pending'   ? 'selected':'' ?>>Pending</option>
                                    <option value="received"  <?= $doc['status']==='received'  ? 'selected':'' ?>>Received</option>
                                    <option value="returned"  <?= $doc['status']==='returned'  ? 'selected':'' ?>>Returned</option>
                                    <option value="completed" <?= $doc['status']==='completed' ? 'selected':'' ?>>Completed</option>
                                    <option value="archived"  <?= $doc['status']==='archived'  ? 'selected':'' ?>>Archived</option>
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

                        <!-- Meta Info Card -->
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
                                    <span class="status-badge status-<?= $doc['status'] ?>" id="statusBadge"><?= ucfirst($doc['status']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div><!-- /.col-lg-4 -->

                </div><!-- /.row -->
            </div>
        </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
</div>

<!-- ═══════════════════════════════════════════════════════
     FORWARD MODAL (same logic as document_list.php)
════════════════════════════════════════════════════════════ -->
<?php
// Pre-load dropdowns for the forward modal
$view_sec_list = $db->query("SELECT section_id, section_name, section_code FROM section ORDER BY section_name");
$view_sec_arr  = $view_sec_list ? $view_sec_list->fetch_all(MYSQLI_ASSOC) : [];
$view_off_list = $db->query("SELECT office_id, office_name, is_main_office FROM office ORDER BY is_main_office DESC, office_name");
$view_off_arr  = $view_off_list ? $view_off_list->fetch_all(MYSQLI_ASSOC) : [];
$view_user_stmt = $db->prepare("
    SELECT CONCAT(TRIM(e.first_name),' ',TRIM(e.last_name)) AS full_name,
           s.section_name, s.section_code, us.unit_name
    FROM employee e
    LEFT JOIN section s ON e.section_id = s.section_id
    LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
    WHERE e.emp_id = ? LIMIT 1
");
$view_logged = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);
$view_user   = [];
if ($view_logged) {
    $view_user_stmt->bind_param("i", $view_logged);
    $view_user_stmt->execute();
    $view_user = $view_user_stmt->get_result()->fetch_assoc() ?? [];
}
?>
<div class="modal fade" id="forwardModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#198754;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-share mr-2"></i>Forward Document</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="forwardDocumentForm">
                    <input type="hidden" name="action"     value="forward">
                    <input type="hidden" name="id"         id="fwdDocId">
                    <input type="hidden" name="forward_to" id="forwardDestType" value="section">

                    <p class="mb-3">Forwarding: <strong id="fwdDocNumber" class="text-primary"></strong></p>

                    <div class="form-group">
                        <label class="font-weight-bold">Forwarded By</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($view_user['full_name'] ?? '') ?>" readonly>
                        <small class="text-muted"><?= htmlspecialchars($view_user['section_name'] ?? '') ?><?= !empty($view_user['unit_name']) ? ' &middot; ' . htmlspecialchars($view_user['unit_name']) : '' ?></small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Destination Type</label>
                        <div class="d-flex" style="gap:8px;">
                            <label style="flex:1;cursor:pointer;margin:0;">
                                <input type="radio" name="_fwd_dest_type" value="section" checked style="display:none;">
                                <div class="fwd-dest-btn-v text-center py-2 rounded border" id="fwdVBtnSection" style="font-size:.82rem;font-weight:600;border-color:#198754!important;background:#d1fae5;color:#065f46;">
                                    <i class="fas fa-building mr-1"></i> Section / Unit
                                </div>
                            </label>
                            <label style="flex:1;cursor:pointer;margin:0;">
                                <input type="radio" name="_fwd_dest_type" value="imo" style="display:none;">
                                <div class="fwd-dest-btn-v text-center py-2 rounded border" id="fwdVBtnImo" style="font-size:.82rem;font-weight:600;border-color:#dee2e6;background:#f8f9fa;color:#495057;">
                                    <i class="fas fa-star mr-1"></i> IMO Office
                                </div>
                            </label>
                        </div>
                    </div>

                    <div id="fwdVSectionGroup">
                        <div class="form-group">
                            <label class="font-weight-bold">Section <span class="text-danger">*</span></label>
                            <select class="form-control" name="fwd_to_section_id" id="fwdVToSectionSelect">
                                <option value="">-- Select Section --</option>
                                <?php foreach ($view_sec_arr as $sl): ?>
                                <option value="<?= $sl['section_id'] ?>"><?= htmlspecialchars($sl['section_name']) ?> (<?= htmlspecialchars($sl['section_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="fwdVUnitGroup" style="display:none;">
                            <label class="font-weight-bold">Unit <small class="text-muted font-weight-normal">(optional)</small></label>
                            <select class="form-control" name="fwd_to_unit_id" id="fwdVToUnitSelect">
                                <option value="">-- Entire Section --</option>
                            </select>
                        </div>
                    </div>

                    <div id="fwdVImoGroup" style="display:none;">
                        <div class="form-group">
                            <label class="font-weight-bold">Office <span class="text-danger">*</span></label>
                            <select class="form-control" name="fwd_to_office_id" id="fwdVToOfficeSelect">
                                <option value="">-- Select Office --</option>
                                <?php foreach ($view_off_arr as $ol): ?>
                                <option value="<?= $ol['office_id'] ?>"><?= htmlspecialchars($ol['office_name']) ?><?= $ol['is_main_office'] ? ' ⭐' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted mt-1 d-block"><i class="fas fa-info-circle text-info mr-1"></i>Routes to the office manager or focal person.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="fwd_date" id="fwdVDate" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Remarks</label>
                        <textarea class="form-control" name="fwd_remarks" rows="2" placeholder="Reason or notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmVForwardBtn">
                    <i class="fas fa-share mr-1"></i> Forward Document
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Forward Modal (view page) ────────────────────────────────────────────────
function openForwardModal(id, docNum) {
    $('#fwdDocId').val(id);
    $('#fwdDocNumber').text(docNum);
    $('#forwardDocumentForm')[0].reset();
    $('#fwdVDate').val(new Date().toISOString().slice(0, 16));
    // Reset to Section mode
    $('#fwdVSectionGroup').show();
    $('#fwdVImoGroup').hide();
    $('#fwdVBtnSection').css({ borderColor: '#198754', background: '#d1fae5', color: '#065f46' });
    $('#fwdVBtnImo').css({ borderColor: '#dee2e6', background: '#f8f9fa', color: '#495057' });
    $('input[name="_fwd_dest_type"][value="section"]').prop('checked', true);
    $('#forwardDestType').val('section');
    $('#fwdVToUnitSelect').html('<option value="">-- Entire Section --</option>');
    $('#fwdVUnitGroup').hide();
    $('#fwdVToSectionSelect, #fwdVToOfficeSelect').prop('disabled', false);
    $('#forwardModal').modal('show');
}

$(document).ready(function() {
    // Destination type toggle
    $(document).on('click', '.fwd-dest-btn-v', function() {
        $(this).closest('label').find('input[type="radio"]').prop('checked', true).trigger('change');
    });
    $(document).on('change', 'input[name="_fwd_dest_type"]', function() {
        if ($(this).val() === 'section') {
            $('#fwdVSectionGroup').show(); $('#fwdVImoGroup').hide();
            $('#fwdVBtnSection').css({ borderColor: '#198754', background: '#d1fae5', color: '#065f46' });
            $('#fwdVBtnImo').css({ borderColor: '#dee2e6', background: '#f8f9fa', color: '#495057' });
            $('#forwardDestType').val('section');
        } else {
            $('#fwdVSectionGroup').hide(); $('#fwdVImoGroup').show();
            $('#fwdVBtnImo').css({ borderColor: '#0d6efd', background: '#dbeafe', color: '#1d4ed8' });
            $('#fwdVBtnSection').css({ borderColor: '#dee2e6', background: '#f8f9fa', color: '#495057' });
            $('#forwardDestType').val('imo');
        }
    });

    // Section change → load units
    $(document).on('change', '#fwdVToSectionSelect', function() {
        const secId = $(this).val();
        $('#fwdVToUnitSelect').html('<option value="">-- Entire Section --</option>');
        if (!secId) { $('#fwdVUnitGroup').hide(); return; }
        $.get('document_actions.php', { action: 'get_units', section_id: secId }, function(r) {
            if (r.success && r.units.length) {
                r.units.forEach(u => $('#fwdVToUnitSelect').append($('<option>').val(u.id).text(u.unit_name + ' (' + u.unit_code + ')')));
                $('#fwdVUnitGroup').show();
            } else { $('#fwdVUnitGroup').hide(); }
        }, 'json');
    });

    // Submit forward
    $('#confirmVForwardBtn').on('click', function() {
        const destType = $('input[name="_fwd_dest_type"]:checked').val();
        const secId    = $('#fwdVToSectionSelect').val();
        const officeId = $('#fwdVToOfficeSelect').val();
        if (destType === 'section' && !secId) { toastr.error('Please select a destination section.'); return; }
        if (destType === 'imo' && !officeId)  { toastr.error('Please select an IMO Office.'); return; }

        if (destType === 'section') {
            $('#fwdVToSectionSelect, #fwdVToUnitSelect').prop('disabled', false);
            $('#fwdVToOfficeSelect').prop('disabled', true);
        } else {
            $('#fwdVToSectionSelect, #fwdVToUnitSelect').prop('disabled', true);
            $('#fwdVToOfficeSelect').prop('disabled', false);
        }

        const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Forwarding...');
        $.post('document_actions.php', $('#forwardDocumentForm').serialize(), function(r) {
            if (r.success) {
                let msg = 'Forwarded to <strong>' + (r.destination || 'destination') + '</strong>.';
                if (r.focal_person) msg += '<br><small>Assigned to: ' + r.focal_person + '</small>';
                toastr.success(msg);
                $('#forwardModal').modal('hide');
                setTimeout(() => location.reload(), 1200);
            } else {
                toastr.error(r.message || 'Forward failed.');
            }
        }, 'json').fail(function(xhr) {
            toastr.error('Server error. Check console.');
            console.error(xhr.responseText);
        }).always(() => {
            $('#fwdVToSectionSelect, #fwdVToUnitSelect, #fwdVToOfficeSelect').prop('disabled', false);
            $btn.prop('disabled', false).html('<i class="fas fa-share mr-1"></i> Forward Document');
        });
    });
});

// FIX: updateStatus now uses 'json' dataType (no manual JSON.parse needed)
function updateStatus() {
    const status = $('#quickStatusSelect').val();
    const $btn   = $('button[onclick="updateStatus()"]').prop('disabled', true)
                       .html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

    $.post('document_actions.php', { action: 'update_status', id: <?= $doc['id'] ?>, status: status },
    function(r) {
        if (r.success) {
            toastr.success('Status updated to: ' + status.charAt(0).toUpperCase() + status.slice(1));
            // Update badge inline without full reload
            $('#statusBadge')
                .attr('class', 'status-badge status-' + status)
                .text(status.charAt(0).toUpperCase() + status.slice(1));
            setTimeout(() => location.reload(), 1200);
        } else {
            toastr.error(r.message || 'Update failed.');
        }
    }, 'json').fail(function(xhr) {
        toastr.error('Server error.');
        console.error(xhr.responseText);
    }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Update Status'));
}

// FIX: delete uses 'json' dataType
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
            $.post('document_actions.php', { action: 'delete', id: id }, function(r) {
                if (r.success) {
                    toastr.success('Document deleted.');
                    setTimeout(() => window.location.href = 'document_list.php', 1000);
                } else {
                    toastr.error(r.message || 'Delete failed.');
                }
            }, 'json').fail(function(xhr) {
                toastr.error('Server error.');
                console.error(xhr.responseText);
            });
        }
    });
}

// Redirect to list page with edit intent (list page handles the modal)
function editDocumentFromView(id) {
    window.location.href = 'document_list.php?edit=' + id;
}
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>