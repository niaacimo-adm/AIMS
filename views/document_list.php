<?php
ob_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$kind = isset($_GET['kind']) && in_array($_GET['kind'], ['incoming','outgoing','internal']) ? $_GET['kind'] : '';
$page_title = $kind ? ucfirst($kind) . ' Documents' : 'All Documents';

// ── Logged-in user info ──────────────────────────────────────────────────────
$logged_emp_id = (int)($_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0);

$user_info = [];
if ($logged_emp_id) {
    $ustmt = $db->prepare("
        SELECT e.emp_id,
               CONCAT(TRIM(e.first_name),' ',TRIM(e.last_name)) AS full_name,
               s.section_id, s.section_name, s.section_code,
               us.unit_id, us.unit_name, us.unit_code
        FROM employee e
        LEFT JOIN section      s  ON e.section_id      = s.section_id
        LEFT JOIN unit_section us ON e.unit_section_id = us.unit_id
        WHERE e.emp_id = ?
        LIMIT 1
    ");
    $ustmt->bind_param("i", $logged_emp_id);
    $ustmt->execute();
    $user_info = $ustmt->get_result()->fetch_assoc() ?? [];
}

// ── Auto-generate next document number ──────────────────────────────────────
$section_code = $user_info['section_code'] ?? 'IMO';
$date_part    = date('mdY');
$prefix       = $section_code . '-' . $date_part . '-';

$seq_res  = $db->prepare("SELECT document_number FROM document_records WHERE document_number LIKE ? ORDER BY id DESC LIMIT 1");
$like_val = $prefix . '%';
$seq_res->bind_param("s", $like_val);
$seq_res->execute();
$last_row   = $seq_res->get_result()->fetch_assoc();
$next_seq   = 1;
if ($last_row) {
    $parts    = explode('-', $last_row['document_number']);
    $last_num = intval(end($parts));
    $next_seq = $last_num + 1;
}
$auto_doc_number = $prefix . str_pad($next_seq, 4, '0', STR_PAD_LEFT);

// ── Fetch lists for dropdowns ─────────────────────────────────────────────
$doc_types_res = $db->query("SELECT id, type_name FROM document_types ORDER BY type_name");
// FIX: collect doc_types into array ONCE so we can safely reuse in PHP and JSON
$doc_types_arr = [];
if ($doc_types_res) {
    while ($t = $doc_types_res->fetch_assoc()) {
        $doc_types_arr[] = $t;
    }
}

$all_sections = $db->query("SELECT section_id AS id, section_name, section_code FROM section ORDER BY section_name");
$sections_arr = [];
if ($all_sections) {
    while ($sr = $all_sections->fetch_assoc()) { $sections_arr[] = $sr; }
}

// ── Build document list query ─────────────────────────────────────────────
$where     = "WHERE 1=1";
$params    = [];
$types_str = '';
if ($kind) {
    $where    .= " AND dr.kind = ?";
    $params[]  = $kind;
    $types_str = 's';
}

$query = "
    SELECT dr.*,
           dt.type_name,
           CONCAT(TRIM(fbe.first_name),' ',TRIM(fbe.last_name)) AS forwarded_by_name,
           s1.section_name AS from_section,
           s2.section_name AS to_section,
           us1.unit_name   AS from_unit,
           us2.unit_name   AS to_unit
    FROM document_records dr
    LEFT JOIN document_types dt  ON dr.document_type_id          = dt.id
    LEFT JOIN employee       fbe ON dr.forwarded_by_emp_id       = fbe.emp_id
    LEFT JOIN section        s1  ON dr.from_section_id           = s1.section_id
    LEFT JOIN section        s2  ON dr.forwarded_to_section_id   = s2.section_id
    LEFT JOIN unit_section   us1 ON dr.from_unit_id              = us1.unit_id
    LEFT JOIN unit_section   us2 ON dr.forwarded_to_unit_id      = us2.unit_id
    $where
    ORDER BY dr.date_forwarded DESC
";

$stmt = $db->prepare($query);
if ($params) { $stmt->bind_param($types_str, ...$params); }
$stmt->execute();
$documents = $stmt->get_result();

// ── Fetch unit_sections for dropdowns (keyed by section) ─────────────────
$units_res = $db->query("SELECT us.unit_id AS id, us.unit_name, us.unit_code, us.section_id FROM unit_section us ORDER BY us.unit_name");
$units_arr = [];
if ($units_res) {
    while ($ur = $units_res->fetch_assoc()) { $units_arr[] = $ur; }
}

// ── Fetch office list for forward modal ──────────────────────────────────
$office_list_res = $db->query("SELECT office_id, office_name, is_main_office FROM office ORDER BY is_main_office DESC, office_name");
$offices_arr = [];
if ($office_list_res) {
    while ($ol = $office_list_res->fetch_assoc()) { $offices_arr[] = $ol; }
}

// ── Fetch section list for forward modal ─────────────────────────────────
$sec_list_res = $db->query("SELECT section_id, section_name, section_code FROM section ORDER BY section_name");
$sec_list_arr = [];
if ($sec_list_res) {
    while ($sl = $sec_list_res->fetch_assoc()) { $sec_list_arr[] = $sl; }
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
        :root {
            --doc-primary:  #1a3c5e;
            --doc-incoming: #0d6efd;
            --doc-outgoing: #198754;
            --doc-internal: #6f42c1;
        }
        .kind-badge   { padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em; }
        .kind-incoming  { background:#dbeafe;color:#1d4ed8; }
        .kind-outgoing  { background:#dcfce7;color:#166534; }
        .kind-internal  { background:#ede9fe;color:#5b21b6; }
        .status-badge { padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600; }
        .status-pending   { background:#ffedd5;color:#c2410c; }
        .status-received  { background:#dbeafe;color:#1d4ed8; }
        .status-returned  { background:#fce7f3;color:#9d174d; }
        .status-completed { background:#d1fae5;color:#065f46; }
        .status-archived  { background:#f3f4f6;color:#374151; }
        .filter-pill { display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;font-size:.82rem;font-weight:500;cursor:pointer;border:1px solid #dee2e6;text-decoration:none;color:#495057;transition:all .15s; }
        .filter-pill:hover { border-color:var(--doc-primary);color:var(--doc-primary); }
        .filter-pill.active-all      { background:var(--doc-primary); color:#fff;border-color:var(--doc-primary); }
        .filter-pill.active-incoming { background:var(--doc-incoming);color:#fff;border-color:var(--doc-incoming); }
        .filter-pill.active-outgoing { background:var(--doc-outgoing);color:#fff;border-color:var(--doc-outgoing); }
        .filter-pill.active-internal { background:var(--doc-internal);color:#fff;border-color:var(--doc-internal); }
        .action-btn { width:30px;height:30px;padding:0;border-radius:6px;display:inline-flex;align-items:center;justify-content:center; }
        .page-section-title { font-size:1.05rem;font-weight:700;color:var(--doc-primary);border-left:4px solid var(--doc-primary);padding-left:10px; }
        .kind-option { text-align:center;padding:12px 8px;border-radius:10px;border:2px solid #dee2e6;cursor:pointer;font-size:.82rem;font-weight:600;color:#495057;transition:all .15s;background:#f8f9fa; }
        .kind-option:hover { border-color:#adb5bd; }
        .kind-radio:checked + .kind-opt-incoming { border-color:#0d6efd;background:#dbeafe;color:#1d4ed8; }
        .kind-radio:checked + .kind-opt-outgoing { border-color:#198754;background:#dcfce7;color:#166534; }
        .kind-radio:checked + .kind-opt-internal { border-color:#6f42c1;background:#ede9fe;color:#5b21b6; }
        body.dark-mode .kind-incoming { background:#1e3a5f;color:#93c5fd; }
        body.dark-mode .kind-outgoing { background:#14532d;color:#86efac; }
        body.dark-mode .kind-internal { background:#2e1065;color:#c4b5fd; }
        body.dark-mode .status-pending   { background:#431407;color:#fdba74; }
        body.dark-mode .status-received  { background:#1e3a5f;color:#93c5fd; }
        body.dark-mode .status-completed { background:#064e3b;color:#6ee7b7; }
        body.dark-mode .filter-pill { border-color:var(--card-border);color:var(--text-primary); }
        body.dark-mode .page-section-title { color:#7aabdf;border-color:#7aabdf; }
        body.dark-mode .kind-option { background:var(--input-bg);color:var(--text-primary);border-color:var(--input-border); }
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
                        <h1 class="m-0" style="font-size:1.4rem;font-weight:700;color:var(--doc-primary);">
                            <?php if ($kind==='incoming'):  ?><i class="fas fa-inbox mr-2" style="color:var(--doc-incoming);"></i>
                            <?php elseif ($kind==='outgoing'): ?><i class="fas fa-paper-plane mr-2" style="color:var(--doc-outgoing);"></i>
                            <?php elseif ($kind==='internal'): ?><i class="fas fa-exchange-alt mr-2" style="color:var(--doc-internal);"></i>
                            <?php else: ?><i class="fas fa-file-alt mr-2"></i><?php endif; ?>
                            <?= htmlspecialchars($page_title) ?>
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="document_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($page_title) ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="mb-3 d-flex align-items-center flex-wrap" style="gap:8px;">
                    <a href="document_list.php"               class="filter-pill <?= !$kind ? 'active-all' : '' ?>"><i class="fas fa-folder-open"></i> All</a>
                    <a href="document_list.php?kind=incoming" class="filter-pill <?= $kind==='incoming' ? 'active-incoming' : '' ?>"><i class="fas fa-inbox"></i> Incoming</a>
                    <a href="document_list.php?kind=outgoing" class="filter-pill <?= $kind==='outgoing' ? 'active-outgoing' : '' ?>"><i class="fas fa-paper-plane"></i> Outgoing</a>
                    <a href="document_list.php?kind=internal" class="filter-pill <?= $kind==='internal' ? 'active-internal' : '' ?>"><i class="fas fa-exchange-alt"></i> Internal</a>
                    <div class="ml-auto d-flex" style="gap:6px;">
                        <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV()"><i class="fas fa-file-excel mr-1"></i> Export</button>
                        <button class="btn btn-sm btn-outline-danger"    onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
                        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addDocumentModal"><i class="fas fa-plus mr-1"></i> Add Document</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="documentsTable" class="table table-bordered table-hover table-striped mb-0" style="font-size:.84rem;">
                                <thead class="thead-dark">
                                    <tr>
                                        <th style="width:45px;">ID</th>
                                        <th style="min-width:145px;">Document No.</th>
                                        <th style="min-width:220px;">Document Name / Particulars</th>
                                        <th style="width:110px;">Doc Type</th>
                                        <th style="width:90px;">Kind</th>
                                        <th style="min-width:160px;">Forwarded By / Section</th>
                                        <th style="min-width:185px;">Forwarded To / Date & Time</th>
                                        <th style="width:90px;">Status</th>
                                        <th style="min-width:120px;">Remarks</th>
                                        <th style="width:130px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($documents && $documents->num_rows > 0):
                                    while ($doc = $documents->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $doc['id'] ?></td>
                                        <td><code style="font-size:.78rem;"><?= htmlspecialchars($doc['document_number']) ?></code></td>
                                        <td>
                                            <div style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($doc['document_name']) ?>">
                                                <?= htmlspecialchars($doc['document_name']) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($doc['type_name'] ?? '—') ?></td>
                                        <td><span class="kind-badge kind-<?= $doc['kind'] ?>"><?= ucfirst($doc['kind']) ?></span></td>
                                        <td>
                                            <div style="font-weight:500;"><?= htmlspecialchars($doc['forwarded_by_name'] ?? '—') ?></div>
                                            <?php if (!empty($doc['from_section'])): ?>
                                            <small class="text-muted"><i class="fas fa-building" style="font-size:.65rem;"></i> <?= htmlspecialchars($doc['from_section']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($doc['from_unit'])): ?>
                                            <br><small class="text-muted"><i class="fas fa-layer-group" style="font-size:.65rem;"></i> <?= htmlspecialchars($doc['from_unit']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($doc['forwarded_to'] ?? '—') ?></div>
                                            <?php if (!empty($doc['to_section'])): ?>
                                            <small class="text-muted"><i class="fas fa-building" style="font-size:.65rem;"></i> <?= htmlspecialchars($doc['to_section']) ?></small><br>
                                            <?php endif; ?>
                                            <?php if (!empty($doc['to_unit'])): ?>
                                            <small class="text-muted"><i class="fas fa-layer-group" style="font-size:.65rem;"></i> <?= htmlspecialchars($doc['to_unit']) ?></small><br>
                                            <?php endif; ?>
                                            <?php
                                            // FIX: guard strtotime against '0000-00-00 00:00:00'
                                            $fwd_ts = strtotime($doc['date_forwarded']);
                                            if ($fwd_ts && $fwd_ts > 0):
                                            ?>
                                            <small class="text-muted"><i class="fas fa-calendar" style="font-size:.65rem;"></i> <?= date('M d, Y h:i A', $fwd_ts) ?></small>
                                            <?php else: ?>
                                            <small class="text-muted">—</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="status-badge status-<?= $doc['status'] ?>"><?= ucfirst($doc['status']) ?></span></td>
                                        <td style="max-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($doc['remarks'] ?? '') ?>">
                                            <?= htmlspecialchars($doc['remarks'] ?: '—') ?>
                                        </td>
                                        <td>
                                            <a href="document_view.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-info action-btn" title="View"><i class="fas fa-eye"></i></a>
                                            <button class="btn btn-sm btn-warning action-btn ml-1" title="Edit" onclick="editDocument(<?= $doc['id'] ?>)"><i class="fas fa-pencil-alt"></i></button>
                                            <button class="btn btn-sm btn-success action-btn ml-1" title="Forward" onclick="openForwardModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')"><i class="fas fa-share"></i></button>
                                            <button class="btn btn-sm btn-danger  action-btn ml-1" title="Delete" onclick="deleteDocument(<?= $doc['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
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

<!-- ========== MODALS ========== -->

<!-- ADD DOCUMENT MODAL -->
<div class="modal fade" id="addDocumentModal" tabindex="-1" role="dialog" aria-labelledby="addDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3c5e;color:#fff;">
                <h5 class="modal-title" id="addDocumentModalLabel"><i class="fas fa-plus-circle mr-2"></i>Add Document Record</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="addDocumentForm">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <!-- Kind selector -->
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Kind of Document <span class="text-danger">*</span></label>
                            <div class="d-flex" style="gap:8px;">
                                <label class="kind-selector-label" style="flex:1;">
                                    <input type="radio" name="kind" value="incoming" required style="display:none;" class="kind-radio">
                                    <div class="kind-option kind-opt-incoming"><i class="fas fa-inbox fa-lg mb-1"></i><br>Incoming</div>
                                </label>
                                <label class="kind-selector-label" style="flex:1;">
                                    <input type="radio" name="kind" value="outgoing" style="display:none;" class="kind-radio">
                                    <div class="kind-option kind-opt-outgoing"><i class="fas fa-paper-plane fa-lg mb-1"></i><br>Outgoing</div>
                                </label>
                                <label class="kind-selector-label" style="flex:1;">
                                    <input type="radio" name="kind" value="internal" style="display:none;" class="kind-radio">
                                    <div class="kind-option kind-opt-internal"><i class="fas fa-exchange-alt fa-lg mb-1"></i><br>Internal</div>
                                </label>
                            </div>
                        </div>

                        <!-- Document Number -->
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Document Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="document_number" id="addDocNumber" value="<?= htmlspecialchars($auto_doc_number) ?>" required>
                                <div class="input-group-append">
                                    <span class="input-group-text" style="font-size:.72rem;color:#6c757d;" title="Auto-generated"><i class="fas fa-magic"></i></span>
                                </div>
                            </div>
                            <small class="text-muted">Format: SECTION-MMDDYYYY-SEQ</small>
                        </div>

                        <!-- Document Type -->
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Document Type <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="document_type_id" required>
                                <option value="">-- Select Type --</option>
                                <?php foreach ($doc_types_arr as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Document Name -->
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Document Name / Particulars <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="document_name" rows="2" required></textarea>
                        </div>

                        <!-- Date Received -->
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Date Received</label>
                            <input type="datetime-local" class="form-control" name="date_received">
                        </div>

                        <!-- Status -->
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Status</label>
                            <select class="form-control" name="status">
                                <option value="pending">Pending</option>
                                <option value="received">Received</option>
                                <option value="returned">Returned</option>
                                <option value="completed">Completed</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>

                        <!-- Remarks -->
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveDocumentBtn">
                    <i class="fas fa-save mr-1"></i> Save Document
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT DOCUMENT MODAL -->
<div class="modal fade" id="editDocumentModal" tabindex="-1" role="dialog" aria-labelledby="editDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#856404;color:#fff;">
                <h5 class="modal-title" id="editDocumentModalLabel"><i class="fas fa-pencil-alt mr-2"></i>Edit Document — <span id="editDocModalNum"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="editModalBody">
                <div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="updateDocumentBtn" style="display:none;">
                    <i class="fas fa-save mr-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- FORWARD MODAL -->
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

                    <!-- Forwarded By (read-only) -->
                    <div class="form-group">
                        <label class="font-weight-bold">Forwarded By</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user_info['full_name'] ?? '') ?>" readonly>
                        <small class="text-muted"><?= htmlspecialchars($user_info['section_name'] ?? '') ?><?= !empty($user_info['unit_name']) ? ' &middot; ' . htmlspecialchars($user_info['unit_name']) : '' ?></small>
                    </div>

                    <!-- Destination Type toggle -->
                    <div class="form-group">
                        <label class="font-weight-bold">Destination Type</label>
                        <div class="d-flex" style="gap:8px;">
                            <label style="flex:1;cursor:pointer;margin:0;" id="fwdTypeSectionLabel">
                                <input type="radio" name="_fwd_dest_type" value="section" checked style="display:none;" class="fwd-type-radio">
                                <div class="fwd-dest-btn text-center py-2 rounded border" id="fwdBtnSection" style="font-size:.82rem;font-weight:600;border-color:#198754!important;background:#d1fae5;color:#065f46;">
                                    <i class="fas fa-building mr-1"></i> Section / Unit
                                </div>
                            </label>
                            <label style="flex:1;cursor:pointer;margin:0;" id="fwdTypeImoLabel">
                                <input type="radio" name="_fwd_dest_type" value="imo" style="display:none;" class="fwd-type-radio">
                                <div class="fwd-dest-btn text-center py-2 rounded border" id="fwdBtnImo" style="font-size:.82rem;font-weight:600;border-color:#dee2e6;background:#f8f9fa;color:#495057;">
                                    <i class="fas fa-star mr-1"></i> IMO Office
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Section / Unit group -->
                    <div id="fwdSectionGroup">
                        <div class="form-group">
                            <label class="font-weight-bold">Section <span class="text-danger">*</span></label>
                            <select class="form-control" name="fwd_to_section_id" id="fwdToSectionSelect">
                                <option value="">-- Select Section --</option>
                                <?php foreach ($sec_list_arr as $sl): ?>
                                <option value="<?= $sl['section_id'] ?>"><?= htmlspecialchars($sl['section_name']) ?> (<?= htmlspecialchars($sl['section_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="fwdUnitGroup" style="display:none;">
                            <label class="font-weight-bold">Unit <small class="text-muted font-weight-normal">(optional)</small></label>
                            <select class="form-control" name="fwd_to_unit_id" id="fwdToUnitSelect">
                                <option value="">-- Entire Section --</option>
                            </select>
                        </div>
                    </div>

                    <!-- IMO Office group -->
                    <div id="fwdImoGroup" style="display:none;">
                        <div class="form-group">
                            <label class="font-weight-bold">Office <span class="text-danger">*</span></label>
                            <select class="form-control" name="fwd_to_office_id" id="fwdToOfficeSelect">
                                <option value="">-- Select Office --</option>
                                <?php foreach ($offices_arr as $ol): ?>
                                <option value="<?= $ol['office_id'] ?>"><?= htmlspecialchars($ol['office_name']) ?><?= $ol['is_main_office'] ? ' ⭐' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted mt-1 d-block"><i class="fas fa-info-circle text-info mr-1"></i>Will be routed to the office manager or designated focal person.</small>
                        </div>
                    </div>

                    <!-- FIX: corrected datetime-local default — single backslash \T -->
                    <div class="form-group">
                        <label class="font-weight-bold">Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="fwd_date" id="fwdDate" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Remarks</label>
                        <textarea class="form-control" name="fwd_remarks" rows="2" placeholder="Reason or notes for forwarding..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmForwardBtn">
                    <i class="fas fa-share mr-1"></i> Forward Document
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// FIX: doc types and sections now come from PHP arrays (no iterator / data_seek needed)
const sectionsArr  = <?= json_encode($sections_arr,  JSON_HEX_TAG | JSON_HEX_APOS) ?>;
const unitsArr     = <?= json_encode($units_arr,     JSON_HEX_TAG | JSON_HEX_APOS) ?>;
const docTypesArr  = <?= json_encode($doc_types_arr, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
const statusOpts   = ['pending','received','returned','completed','archived'];

$(document).ready(function() {

    $('#documentsTable').DataTable({
        destroy: true,
        pageLength: 25,
        order: [[0, 'desc']],
        columnDefs: [{ orderable: false, targets: [9] }],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            search: '',
            searchPlaceholder: 'Search documents...',
            lengthMenu: 'Show _MENU_ entries',
            emptyTable: 'No documents found.'
        }
    });

    // ── Select2 ─────────────────────────────────────────────────────────────
    $('.select2').select2({ theme: 'bootstrap4', dropdownParent: $('body') });

    // ── Save Document (Add) ──────────────────────────────────────────────────
    $('#saveDocumentBtn').on('click', function() {
        const form = $('#addDocumentForm');
        if (!form.find('input[name="kind"]:checked').val()) {
            toastr.warning('Please select the kind of document.'); return;
        }
        if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
        const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.post('document_actions.php', form.serialize(), function(r) {
            if (r.success) {
                toastr.success('Document saved successfully!');
                $('#addDocumentModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error(r.message || 'Failed to save.');
            }
        }, 'json').fail(function(xhr) {
            toastr.error('Server error. Check console for details.');
            console.error(xhr.responseText);
        }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Document'));
    });

    // ── Kind option visual feedback ─────────────────────────────────────────
    $(document).on('change', '.kind-radio', function() {
        const val = $(this).val();
        $(this).closest('.d-flex').find('.kind-option').css({border: '2px solid #dee2e6', background: '#f8f9fa', color: '#495057'});
        const colors = { incoming: ['#0d6efd','#dbeafe','#1d4ed8'], outgoing: ['#198754','#dcfce7','#166534'], internal: ['#6f42c1','#ede9fe','#5b21b6'] };
        if (colors[val]) {
            $(this).next('.kind-option').css({ borderColor: colors[val][0], background: colors[val][1], color: colors[val][2] });
        }
    });

    // ── FORWARD MODAL: toggle Section/Unit vs IMO Office ─────────────────────
    function setForwardDestType(type) {
        if (type === 'section') {
            $('#fwdSectionGroup').show();
            $('#fwdImoGroup').hide();
            $('#fwdBtnSection').css({ borderColor: '#198754', background: '#d1fae5', color: '#065f46' });
            $('#fwdBtnImo').css({ borderColor: '#dee2e6', background: '#f8f9fa', color: '#495057' });
            $('#forwardDestType').val('section');
        } else {
            $('#fwdSectionGroup').hide();
            $('#fwdImoGroup').show();
            $('#fwdBtnImo').css({ borderColor: '#0d6efd', background: '#dbeafe', color: '#1d4ed8' });
            $('#fwdBtnSection').css({ borderColor: '#dee2e6', background: '#f8f9fa', color: '#495057' });
            $('#forwardDestType').val('imo');
        }
    }

    $(document).on('change', 'input[name="_fwd_dest_type"]', function() {
        setForwardDestType($(this).val());
    });
    $(document).on('click', '.fwd-dest-btn', function() {
        var $radio = $(this).closest('label').find('input[type="radio"]');
        $radio.prop('checked', true).trigger('change');
    });

    // ── Submit Forward ────────────────────────────────────────────────────────
    $('#confirmForwardBtn').on('click', function() {
        const destType = $('input[name="_fwd_dest_type"]:checked').val();
        const secId    = $('#fwdToSectionSelect').val();
        const officeId = $('#fwdToOfficeSelect').val();

        if (destType === 'section' && !secId) {
            toastr.error('Please select a destination section.'); return;
        }
        if (destType === 'imo' && !officeId) {
            toastr.error('Please select an IMO Office.'); return;
        }

        // FIX: re-enable fields so they are included in serialise, then submit
        // For section type, ensure office field is cleared
        // For imo type, ensure section/unit fields are cleared (don't send bogus data)
        if (destType === 'section') {
            $('#fwdToSectionSelect').prop('disabled', false);
            $('#fwdToUnitSelect').prop('disabled', false);
            $('#fwdToOfficeSelect').prop('disabled', true);
        } else {
            $('#fwdToSectionSelect').prop('disabled', true);
            $('#fwdToUnitSelect').prop('disabled', true);
            $('#fwdToOfficeSelect').prop('disabled', false);
        }

        const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Forwarding...');

        $.post('document_actions.php', $('#forwardDocumentForm').serialize(), function(r) {
            if (r.success) {
                let msg = 'Document forwarded to <strong>' + (r.destination || 'destination') + '</strong>.';
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
            // Re-enable all fields after request
            $('#fwdToSectionSelect, #fwdToUnitSelect, #fwdToOfficeSelect').prop('disabled', false);
            $btn.prop('disabled', false).html('<i class="fas fa-share mr-1"></i> Forward Document');
        });
    });

    // ── Section change → load units via AJAX ─────────────────────────────────
    $(document).on('change', '#fwdToSectionSelect', function() {
        const secId = $(this).val();
        $('#fwdToUnitSelect').html('<option value="">-- Entire Section --</option>');
        if (!secId) { $('#fwdUnitGroup').hide(); return; }

        $.get('document_actions.php', { action: 'get_units', section_id: secId }, function(r) {
            if (r.success && r.units.length) {
                r.units.forEach(u => {
                    $('#fwdToUnitSelect').append($('<option>').val(u.id).text(u.unit_name + ' (' + u.unit_code + ')'));
                });
                $('#fwdUnitGroup').show();
            } else {
                $('#fwdUnitGroup').hide();
            }
        }, 'json');
    });

    // ── Reset modals on close ─────────────────────────────────────────────────
    $('#addDocumentModal').on('hidden.bs.modal', function() {
        $('#addDocumentForm')[0].reset();
        $('.kind-option').css({ border: '2px solid #dee2e6', background: '#f8f9fa', color: '#495057' });
    });

    $('#forwardModal').on('show.bs.modal', function() {
        $('#fwdToSectionSelect, #fwdToUnitSelect, #fwdToOfficeSelect').prop('disabled', false);
        $('#fwdToSectionSelect, #fwdToOfficeSelect').select2({ theme: 'bootstrap4', dropdownParent: $('#forwardModal') });
    });
});

// ── Edit Document Modal ───────────────────────────────────────────────────────
function editDocument(id) {
    $('#editModalBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#updateDocumentBtn').hide();
    $('#editDocumentModal').modal('show');

    $.get('document_actions.php', { action: 'get', id: id }, function(r) {
        try {
            if (!r.success) { toastr.error(r.message || 'Failed to load.'); return; }
            const d = r.data;
            $('#editDocModalNum').text(d.document_number);

            let typeOpts = '<option value="">-- Select Type --</option>';
            docTypesArr.forEach(t => {
                typeOpts += `<option value="${t.id}" ${d.document_type_id == t.id ? 'selected' : ''}>${t.type_name}</option>`;
            });

            let statusHtml = '';
            statusOpts.forEach(s => {
                statusHtml += `<option value="${s}" ${d.status === s ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`;
            });

            const kindIcons = { incoming: 'fa-inbox', outgoing: 'fa-paper-plane', internal: 'fa-exchange-alt' };
            const kindHtml = ['incoming','outgoing','internal'].map(k => `
                <label style="flex:1;">
                    <input type="radio" name="kind" value="${k}" class="kind-radio" style="display:none;" ${d.kind === k ? 'checked' : ''}>
                    <div class="kind-option kind-opt-${k}">
                        <i class="fas ${kindIcons[k]} fa-lg mb-1"></i><br>${k.charAt(0).toUpperCase() + k.slice(1)}
                    </div>
                </label>`).join('');

            // FIX: use textContent assignment for document_name and remarks to avoid XSS
            const docNameEscaped   = $('<div>').text(d.document_name || '').html();
            const remarksEscaped   = $('<div>').text(d.remarks || '').html();
            const docNumEscaped    = $('<div>').text(d.document_number || '').html();

            $('#editModalBody').html(`
                <form id="editDocumentForm">
                    <input type="hidden" name="id"     value="${d.id}">
                    <input type="hidden" name="action" value="update">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Kind of Document <span class="text-danger">*</span></label>
                            <div class="d-flex" style="gap:8px;">${kindHtml}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Document Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="document_number" value="${docNumEscaped}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Document Type <span class="text-danger">*</span></label>
                            <select class="form-control select2e" name="document_type_id" required>${typeOpts}</select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Document Name / Particulars <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="document_name" rows="2" required>${docNameEscaped}</textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Date Received</label>
                            <input type="datetime-local" class="form-control" name="date_received"
                                value="${d.date_received && d.date_received !== '0000-00-00 00:00:00' ? d.date_received.replace(' ','T').substring(0,16) : ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Status</label>
                            <select class="form-control" name="status">${statusHtml}</select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2">${remarksEscaped}</textarea>
                        </div>
                    </div>
                </form>
            `);

            // FIX: re-apply kind-radio visual state after dynamic HTML injection
            $('#editModalBody input[name="kind"]:checked').trigger('change');
            $('#editDocumentModal .select2e').select2({ theme: 'bootstrap4', dropdownParent: $('#editDocumentModal') });
            $('#updateDocumentBtn').show();
        } catch(e) {
            $('#editModalBody').html('<div class="alert alert-danger">Failed to load document: ' + e.message + '</div>');
        }
    }, 'json');
}

// ── Save Edit ─────────────────────────────────────────────────────────────────
$(document).on('click', '#updateDocumentBtn', function() {
    const form = $('#editDocumentForm');
    if (!form.find('input[name="kind"]:checked').val()) { toastr.warning('Please select kind.'); return; }
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

    $.post('document_actions.php', form.serialize(), function(r) {
        if (r.success) {
            toastr.success('Document updated!');
            $('#editDocumentModal').modal('hide');
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error(r.message || 'Update failed.');
        }
    }, 'json').fail(function(xhr) {
        toastr.error('Server error. Check console for details.');
        console.error(xhr.responseText);
    }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Changes'));
});

function openForwardModal(id, docNum) {
    $('#fwdDocId').val(id);
    $('#fwdDocNumber').text(docNum);
    $('#forwardDocumentForm')[0].reset();
    // FIX: use correct datetime-local format
    $('#fwdDate').val(new Date().toISOString().slice(0, 16));
    $('#fwdSectionGroup').show();
    $('#fwdImoGroup').hide();
    $('#fwdToSectionSelect, #fwdToUnitSelect, #fwdToOfficeSelect').prop('disabled', false);
    $('input[name="_fwd_dest_type"][value="section"]').prop('checked', true);
    $('#forwardDestType').val('section');
    $('#fwdToUnitSelect').html('<option value="">-- Entire Section --</option>');
    $('#fwdUnitGroup').hide();
    // Reset button styles
    $('#fwdBtnSection').css({ borderColor: '#198754', background: '#d1fae5', color: '#065f46' });
    $('#fwdBtnImo').css({ borderColor: '#dee2e6', background: '#f8f9fa', color: '#495057' });
    $('#forwardModal').modal('show');
}

// ── CSV Export ──────────────────────────────────────────────────────────────
function exportTableToCSV() {
    let csv = [];
    const rows = document.querySelectorAll('#documentsTable tr');
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length - 1; j++) { // skip Actions column
            row.push('"' + cols[j].innerText.replace(/"/g, '""').replace(/,/g, ';') + '"');
        }
        csv.push(row.join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'documents_export_<?= date('Ymd') ?>.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

// ── Delete Document ─────────────────────────────────────────────────────────
function deleteDocument(id) {
    Swal.fire({
        title: 'Delete Document?', text: 'This action cannot be undone.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then(result => {
        if (result.isConfirmed) {
            $.post('document_actions.php', { action: 'delete', id }, function(r) {
                if (r.success) {
                    toastr.success('Document deleted.');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    toastr.error(r.message || 'Delete failed.');
                }
            }, 'json').fail(function(xhr) {
                toastr.error('Server error. Check console for details.');
                console.error(xhr.responseText);
            });
        }
    });
}
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>