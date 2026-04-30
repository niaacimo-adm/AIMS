<?php
ob_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$kind = isset($_GET['kind']) && in_array($_GET['kind'], ['incoming','outgoing','internal']) ? $_GET['kind'] : '';
$page_title = $kind ? ucfirst($kind) . ' Documents' : 'All Documents';

// ── Logged-in user info ──────────────────────────────────────────────────────
$logged_emp_id = $_SESSION['emp_id'] ?? $_SESSION['user_id'] ?? 0;

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

$seq_res = $db->prepare("SELECT document_number FROM document_records WHERE document_number LIKE ? ORDER BY id DESC LIMIT 1");
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
$doc_types    = $db->query("SELECT * FROM document_types ORDER BY type_name");
$all_sections = $db->query("SELECT section_id AS id, section_name, section_code FROM section ORDER BY section_name");
$sections_arr = [];
while ($sr = $all_sections->fetch_assoc()) { $sections_arr[] = $sr; }

// ── Build document list query ─────────────────────────────────────────────
$where  = "WHERE 1=1";
$params = [];
$types_str = '';
if ($kind) {
    $where    .= " AND dr.kind = ?";
    $params[]  = $kind;
    $types_str = 's';
}

$query = "
    SELECT dr.*,
           dt.type_name,
           s1.section_name AS from_section,
           s2.section_name AS to_section,
           us1.unit_name   AS from_unit,
           us2.unit_name   AS to_unit
    FROM document_records dr
    LEFT JOIN document_types dt ON dr.document_type_id = dt.id
    LEFT JOIN section      s1  ON dr.from_section_id          = s1.section_id
    LEFT JOIN section      s2  ON dr.forwarded_to_section_id  = s2.section_id
    LEFT JOIN unit_section us1 ON dr.from_unit_id             = us1.unit_id
    LEFT JOIN unit_section us2 ON dr.forwarded_to_unit_id     = us2.unit_id
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
while ($ur = $units_res->fetch_assoc()) { $units_arr[] = $ur; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page_title ?> | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* your existing styles remain unchanged */
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
        .fwd-timeline { list-style:none;padding:0;margin:0; }
        .fwd-timeline li { position:relative;padding:8px 0 8px 28px;border-left:2px solid #dee2e6;margin-left:8px; }
        .fwd-timeline li::before { content:'';position:absolute;left:-7px;top:14px;width:12px;height:12px;border-radius:50%;background:#1a3c5e;border:2px solid #fff;box-shadow:0 0 0 2px #1a3c5e; }
        .fwd-timeline li:last-child { border-left-color:transparent; }
        body.dark-mode .kind-incoming { background:#1e3a5f;color:#93c5fd; }
        body.dark-mode .kind-outgoing { background:#14532d;color:#86efac; }
        body.dark-mode .kind-internal { background:#2e1065;color:#c4b5fd; }
        body.dark-mode .status-pending  { background:#431407;color:#fdba74; }
        body.dark-mode .status-received { background:#1e3a5f;color:#93c5fd; }
        body.dark-mode .status-completed{ background:#064e3b;color:#6ee7b7; }
        body.dark-mode .filter-pill { border-color:var(--card-border);color:var(--text-primary); }
        body.dark-mode .page-section-title { color:#7aabdf;border-color:#7aabdf; }
        body.dark-mode .kind-option { background:var(--input-bg);color:var(--text-primary);border-color:var(--input-border); }
        body.dark-mode .fwd-timeline li { border-left-color:#374151; }
        body.dark-mode .fwd-timeline li::before { background:#7aabdf;box-shadow:0 0 0 2px #7aabdf; }
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
                            <?php if ($kind==='incoming'):  ?><i class="fas fa-inbox mr-2"        style="color:var(--doc-incoming);"></i>
                            <?php elseif ($kind==='outgoing'): ?><i class="fas fa-paper-plane mr-2" style="color:var(--doc-outgoing);"></i>
                            <?php elseif ($kind==='internal'): ?><i class="fas fa-exchange-alt mr-2" style="color:var(--doc-internal);"></i>
                            <?php else: ?><i class="fas fa-file-alt mr-2"></i><?php endif; ?>
                            <?= $page_title ?>
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="document_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active"><?= $page_title ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="mb-3 d-flex align-items-center flex-wrap" style="gap:8px;">
                    <a href="document_list.php"                class="filter-pill <?= !$kind?'active-all':''       ?>"><i class="fas fa-folder-open"></i> All</a>
                    <a href="document_list.php?kind=incoming"  class="filter-pill <?= $kind==='incoming'?'active-incoming':'' ?>"><i class="fas fa-inbox"></i> Incoming</a>
                    <a href="document_list.php?kind=outgoing"  class="filter-pill <?= $kind==='outgoing'?'active-outgoing':'' ?>"><i class="fas fa-paper-plane"></i> Outgoing</a>
                    <a href="document_list.php?kind=internal"  class="filter-pill <?= $kind==='internal'?'active-internal':'' ?>"><i class="fas fa-exchange-alt"></i> Internal</a>
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
                                        <th style="width:120px;">Actions</th>
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
                                            <?php if ($doc['from_section']): ?>
                                            <small class="text-muted"><i class="fas fa-building" style="font-size:.65rem;"></i> <?= htmlspecialchars($doc['from_section']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($doc['from_unit']): ?>
                                            <br><small class="text-muted"><i class="fas fa-layer-group" style="font-size:.65rem;"></i> <?= htmlspecialchars($doc['from_unit']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($doc['forwarded_to']) ?></div>
                                            <?php if ($doc['to_section']): ?>
                                            <small class="text-muted"><i class="fas fa-building" style="font-size:.65rem;"></i> <?= htmlspecialchars($doc['to_section']) ?></small><br>
                                            <?php endif; ?>
                                            <?php if ($doc['to_unit']): ?>
                                            <small class="text-muted"><i class="fas fa-layer-group" style="font-size:.65rem;"></i> <?= htmlspecialchars($doc['to_unit']) ?></small><br>
                                            <?php endif; ?>
                                            <small class="text-muted"><i class="fas fa-calendar" style="font-size:.65rem;"></i> <?= date('M d, Y h:i A', strtotime($doc['date_forwarded'])) ?></small>
                                        </td>
                                        <td><span class="status-badge status-<?= $doc['status'] ?>"><?= ucfirst($doc['status']) ?></span></td>
                                        <td style="max-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($doc['remarks'] ?? '') ?>">
                                            <?= htmlspecialchars($doc['remarks'] ?? '—') ?>
                                        </td>
                                        <td>
                                            <a href="document_view.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-info action-btn" title="View"><i class="fas fa-eye"></i></a>
                                            <button class="btn btn-sm btn-warning action-btn ml-1" title="Edit" onclick="editDocument(<?= $doc['id'] ?>)"><i class="fas fa-pencil-alt"></i></button>
                                            <button class="btn btn-sm btn-success action-btn ml-1" title="Forward to Section" onclick="openForwardModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['document_number'])) ?>')"><i class="fas fa-share"></i></button>
                                            <button class="btn btn-sm btn-danger  action-btn ml-1" title="Delete" onclick="deleteDocument(<?= $doc['id'] ?>)"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                    <?php endwhile; endif; ?>
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

<!-- ========== MODALS (unchanged except for IDs) ========== -->
<!-- ADD DOCUMENT MODAL (simplified) -->
<div class="modal fade" id="addDocumentModal" tabindex="-1" role="dialog" aria-labelledby="addDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3c5e;color:#fff;">
                <h5 class="modal-title" id="addDocumentModalLabel"><i class="fas fa-plus-circle mr-2"></i>Add Document Record</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="addDocumentForm">
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
                                <?php if ($doc_types): $doc_types->data_seek(0); while ($t = $doc_types->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                                <?php endwhile; endif; ?>
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

<!-- VIEW DOCUMENT MODAL -->
<div class="modal fade" id="viewDocumentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3c5e;color:#fff;">
                <h5 class="modal-title"><i class="fas fa-eye mr-2"></i>View Document — <span id="viewDocModalNum"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
                    <input type="hidden" name="action" value="forward">
                    <input type="hidden" name="id"     id="fwdDocId">
                    <p class="mb-3">Forwarding: <strong id="fwdDocNumber" class="text-primary"></strong></p>
                    <div class="form-group">
                        <label class="font-weight-bold">Forwarded By</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user_info['full_name'] ?? '') ?>" readonly>
                        <input type="hidden" name="fwd_to_emp_id" id="fwdToEmpIdHidden">
                        <small class="text-muted"><?= htmlspecialchars(($user_info['section_name'] ?? '')) ?> <?= ($user_info['unit_name'] ? ' · ' . htmlspecialchars($user_info['unit_name']) : '') ?></small>
                    </div>
                    <div class="form-group position-relative">
                        <label class="font-weight-bold">Forward To (Person) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="fwdToNameInput" name="fwd_to_name" placeholder="Type employee name..." required autocomplete="off">
                        <input type="hidden" name="fwd_to_section_id" id="fwdToSectionHidden">
                        <input type="hidden" name="fwd_to_unit_id"    id="fwdToUnitHidden">
                        <div id="fwdEmployeeSuggestions" style="display:none;position:absolute;z-index:9999;background:#fff;border:1px solid #ced4da;border-radius:4px;width:100%;max-height:220px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15);"></div>
                        <small class="text-muted" id="fwdAutoFillInfo"></small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="fwd_date" value="<?= date('Y-m-d\TH:i') ?>" required>
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
// ── Helper: filter units by section ─────────────────────────────────────────
function filterUnits(selectId, sectionId) {
    const $sel = $('#' + selectId);
    const prev = $sel.val();
    $sel.find('option').each(function() {
        const ds = $(this).data('section');
        if (!ds) return;
        $(this).toggle(!sectionId || String(ds) === String(sectionId));
    });
    if (prev && (!sectionId || $sel.find('option[value="'+prev+'"]:visible').length === 0)) {
        $sel.val('').trigger('change');
    }
}

$(document).ready(function() {
    // ── DataTable initialization with custom empty message ────────────────────
    $('#documentsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        columnDefs: [{ orderable: false, targets: [9] }],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            search: '',
            searchPlaceholder: 'Search documents...',
            lengthMenu: 'Show _MENU_ entries',
            emptyTable: 'No documents found. <a href="#" data-toggle="modal" data-target="#addDocumentModal">Add one now.</a>'
        }
    });

    // ── Select2 init ──────────────────────────────────────────────────────────
    $('.select2').select2({ theme:'bootstrap4', dropdownParent: $('body') });

    // ── Unit filtering for modals ─────────────────────────────────────────────
    $('#addToSection').on('change', function() {
        filterUnits('addToUnit', $(this).val());
        $('#addToUnit').select2({ theme:'bootstrap4', dropdownParent:$('body') });
    });
    filterUnits('addToUnit', '');

    // fwdToSection and fwdToUnit are now display-only (auto-filled from employee autocomplete)

    // ── Save Document (Add) ───────────────────────────────────────────────────
    $('#saveDocumentBtn').on('click', function() {
        const form = $('#addDocumentForm');
        if (!$('#addDocumentForm input[name="kind"]:checked').val()) {
            toastr.warning('Please select the kind of document.'); return;
        }
        if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
        const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.post('document_actions.php', form.serialize() + '&action=add', function(r) {
            if (r.success) {
                toastr.success('Document saved successfully!');
                $('#addDocumentModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else { toastr.error(r.message || 'Failed to save.'); }
        }, 'json').fail(function(xhr) {
            toastr.error('Server error. Check console for details.');
            console.error(xhr.responseText);
        }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Document'));
    });

    $('#confirmForwardBtn').on('click', function() {
        const form = $('#forwardDocumentForm');
        const toEmpId = $('#fwdToEmpIdHidden').val();
        
        // Validate that an employee was actually selected
        if (!toEmpId || toEmpId == '0') {
            toastr.error('Please select a valid employee from the suggestions (type at least 2 letters and click on a name).');
            return;
        }
        
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Forwarding...');
        
        $.post('document_actions.php', form.serialize(), function(r) {
            if (r.success) {
                toastr.success('Document forwarded successfully!');
                $('#forwardModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error(r.message || 'Forward failed.');
            }
        }, 'json').fail(function(xhr) {
            toastr.error('Server error. Check console for details.');
            console.error(xhr.responseText);
        }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-share mr-1"></i> Forward Document'));
    });

    // ── Reset add form on modal close ─────────────────────────────────────────
    $('#addDocumentModal').on('hidden.bs.modal', function() {
        $('#addDocumentForm')[0].reset();
        $('.kind-option').css({borderColor:'#dee2e6', background:'#f8f9fa', color:'#495057'});
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

            const sectionsArr = <?= json_encode($sections_arr) ?>;
            const unitsArr    = <?= json_encode($units_arr) ?>;
            const docTypesArr = <?= json_encode(array_map(fn($t) => $t, iterator_to_array(
                (function() use ($doc_types) {
                    $doc_types->data_seek(0);
                    $arr = [];
                    while ($row = $doc_types->fetch_assoc()) $arr[] = $row;
                    return new ArrayIterator($arr);
                })()
            ))) ?>;

            const kindOpts = ['incoming','outgoing','internal'];
            const statusOpts = ['pending','received','returned','completed','archived'];

            let sectionOpts = '<option value="">-- Select Section --</option>';
            sectionsArr.forEach(s => {
                sectionOpts += `<option value="${s.id}" ${d.from_section_id == s.id ? 'selected':''}>${s.section_name}</option>`;
            });
            let toSectionOpts = '<option value="">-- Select Section --</option>';
            sectionsArr.forEach(s => {
                toSectionOpts += `<option value="${s.id}" ${d.forwarded_to_section_id == s.id ? 'selected':''}>${s.section_name}</option>`;
            });
            let toUnitOpts = '<option value="">-- Select Unit (optional) --</option>';
            unitsArr.forEach(u => {
                toUnitOpts += `<option value="${u.id}" data-section="${u.section_id}" ${d.forwarded_to_unit_id == u.id ? 'selected':''}>${u.unit_name}</option>`;
            });
            let typeOpts = '<option value="">-- Select Type --</option>';
            docTypesArr.forEach(t => {
                typeOpts += `<option value="${t.id}" ${d.document_type_id == t.id ? 'selected':''}>${t.type_name}</option>`;
            });
            let statusOpsHtml = '';
            statusOpts.forEach(s => {
                statusOpsHtml += `<option value="${s}" ${d.status===s?'selected':''}>${s.charAt(0).toUpperCase()+s.slice(1)}</option>`;
            });

            const kindHtml = kindOpts.map(k => `
                <label style="flex:1;">
                    <input type="radio" name="kind" value="${k}" class="kind-radio" style="display:none;" ${d.kind===k?'checked':''}>
                    <div class="kind-option kind-opt-${k}">
                        <i class="fas ${k==='incoming'?'fa-inbox':k==='outgoing'?'fa-paper-plane':'fa-exchange-alt'} fa-lg mb-1"></i><br>${k.charAt(0).toUpperCase()+k.slice(1)}
                    </div>
                </label>`).join('');

            $('#editModalBody').html(`
                <form id="editDocumentForm">
                    <input type="hidden" name="id"     value="${d.id}">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="forwarded_by_name"       value="${d.forwarded_by_name||''}">
                    <input type="hidden" name="from_section_id"         value="${d.from_section_id||''}">
                    <input type="hidden" name="forwarded_to"            value="${d.forwarded_to||''}">
                    <input type="hidden" name="forwarded_to_section_id" value="${d.forwarded_to_section_id||''}">
                    <input type="hidden" name="forwarded_to_unit_id"    value="${d.forwarded_to_unit_id||''}">
                    <input type="hidden" name="date_forwarded"          value="${d.date_forwarded||''}">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Kind of Document <span class="text-danger">*</span></label>
                            <div class="d-flex" style="gap:8px;">${kindHtml}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Document Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="document_number" value="${d.document_number}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Document Type <span class="text-danger">*</span></label>
                            <select class="form-control select2e" name="document_type_id" required>${typeOpts}</select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Document Name / Particulars <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="document_name" rows="2" required>${d.document_name}</textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Date Received</label>
                            <input type="datetime-local" class="form-control" name="date_received" value="${d.date_received ? d.date_received.replace(' ','T').substring(0,16) : ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Status</label>
                            <select class="form-control" name="status">${statusOpsHtml}</select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="font-weight-bold">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2">${d.remarks||''}</textarea>
                        </div>
                    </div>
                </form>
            `);

            $('#editDocumentModal .select2e').select2({ theme:'bootstrap4', dropdownParent:$('#editDocumentModal') });
            $('#updateDocumentBtn').show();
        } catch(e) { $('#editModalBody').html('<div class="alert alert-danger">Failed to load document: ' + e.message + '</div>'); }
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
        } else { toastr.error(r.message || 'Update failed.'); }
    }, 'json').fail(function(xhr) {
        toastr.error('Server error. Check console for details.');
        console.error(xhr.responseText);
    }).always(() => $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Changes'));
});

// ── View Document Modal ───────────────────────────────────────────────────────
function viewDocument(id) {
    $('#viewModalBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#viewDocumentModal').modal('show');

    $.get('document_actions.php', { action: 'get', id: id }, function(r) {
        try {
            if (!r.success) { toastr.error(r.message || 'Failed to load.'); return; }
            const d = r.data;
            $('#viewDocModalNum').text(d.document_number);

            const kindColors = { incoming:'#0d6efd', outgoing:'#198754', internal:'#6f42c1' };
            const statusColors = { pending:'#c2410c', received:'#1d4ed8', returned:'#9d174d', completed:'#065f46', archived:'#374151' };

            let historyHtml = '<p class="text-muted small mb-0">No forwarding history recorded.</p>';
            if (r.history && r.history.length > 0) {
                historyHtml = '<ul class="fwd-timeline">';
                r.history.forEach(h => {
                    historyHtml += `
                        <li>
                            <strong>${h.fwd_by_name||'—'}</strong>
                            <i class="fas fa-arrow-right mx-1 text-muted" style="font-size:.75rem;"></i>
                            <strong>${h.fwd_to_name||'—'}</strong>
                            <span class="badge badge-secondary ml-1">${h.to_section_name||''}</span>
                            ${h.to_unit_name?`<span class="badge badge-light ml-1">${h.to_unit_name}</span>`:''}
                            <br><small class="text-muted">${h.fwd_date||''}</small>
                            ${h.fwd_remarks?`<br><small class="text-muted fst-italic">${h.fwd_remarks}</small>`:''}
                        </li>`;
                });
                historyHtml += '</ul>';
            }

            $('#viewModalBody').html(`
                <div class="row">
                    <div class="col-md-6 mb-2"><small class="text-muted d-block">Document Number</small><code>${d.document_number}</code></div>
                    <div class="col-md-6 mb-2"><small class="text-muted d-block">Kind</small>
                        <span style="padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;background:${kindColors[d.kind]}22;color:${kindColors[d.kind]};">${d.kind?.toUpperCase()}</span>
                    </div>
                    <div class="col-md-6 mb-2"><small class="text-muted d-block">Document Type</small>${d.type_name||'—'}</div>
                    <div class="col-md-6 mb-2"><small class="text-muted d-block">Status</small>
                        <span style="padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;background:${(statusColors[d.status]||'#374151')}22;color:${(statusColors[d.status]||'#374151')};">${d.status?.toUpperCase()}</span>
                    </div>
                    <div class="col-12 mb-2"><small class="text-muted d-block">Document Name / Particulars</small>${d.document_name}</div>
                    <div class="col-md-6 mb-2"><small class="text-muted d-block">Forwarded By</small>${d.forwarded_by_name||'—'}</div>
                    <div class="col-md-6 mb-2"><small class="text-muted d-block">From Section</small>${d.from_section_name||'—'}</div>
                    <div class="col-md-6 mb-2"><small class="text-muted d-block">Forwarded To</small>${d.forwarded_to||'—'}</div>
                    <div class="col-md-6 mb-2"><small class="text-muted d-block">To Section</small>${d.to_section_name||'—'} ${d.to_unit_name?'· '+d.to_unit_name:''}</div>
                    <div class="col-md-6 mb-2"><small class="text-muted d-block">Date Forwarded</small>${d.date_forwarded||'—'}</div>
                    <div class="col-md-6 mb-2"><small class="text-muted d-block">Date Received</small>${d.date_received||'—'}</div>
                    <div class="col-12 mb-2"><small class="text-muted d-block">Remarks</small>${d.remarks||'—'}</div>
                </div>
                <hr>
                <p class="page-section-title mb-2" style="font-size:.9rem;border-left:3px solid #1a3c5e;padding-left:8px;">
                    <i class="fas fa-history mr-1"></i> Forwarding History
                </p>
                ${historyHtml}
            `);
        } catch(e) { $('#viewModalBody').html('<div class="alert alert-danger">Failed to load document: ' + e.message + '</div>'); }
    }, 'json');
}

function openForwardModal(id, docNum) {
    $('#fwdDocId').val(id);
    $('#fwdDocNumber').text(docNum);
    $('#forwardDocumentForm')[0].reset();
    $('#fwdToEmpIdHidden').val('');
    $('#fwdToSectionHidden').val('');
    $('#fwdToUnitHidden').val('');
    $('#forwardModal').modal('show');
}

// ── Employee Autocomplete for Forward Modal (fixed) ──────────────────────────
let fwdEmpDebounce = null;
let currentEmployees = []; // store latest search results

$(document).on('input', '#fwdToNameInput', function() {
    const q = $(this).val().trim();
    // Reset hidden fields when user starts typing
    $('#fwdToEmpIdHidden').val('');
    $('#fwdToSectionHidden').val('');
    $('#fwdToUnitHidden').val('');
    $('#fwdAutoFillInfo').text('');

    if (q.length < 2) {
        $('#fwdEmployeeSuggestions').hide();
        return;
    }

    clearTimeout(fwdEmpDebounce);
    fwdEmpDebounce = setTimeout(function() {
        $.get('document_actions.php', { action: 'search_employee', q: q }, function(r) {
            const $box = $('#fwdEmployeeSuggestions').empty();
            if (!r.success || !r.employees.length) {
                $box.html('<div style="padding:8px 12px;color:#6c757d;">No employees found</div>').show();
                currentEmployees = [];
                return;
            }
            currentEmployees = r.employees;
            r.employees.forEach(emp => {
                const line = [emp.section_name, emp.unit_name].filter(Boolean).join(' · ');
                $('<div>').css({padding:'8px 12px', cursor:'pointer', borderBottom:'1px solid #f0f0f0'})
                    .html(`<strong>${emp.full_name}</strong><br><small>${line || 'No section'}</small>`)
                    .on('mousedown', function(e) {
                        e.preventDefault();
                        selectEmployee(emp);
                    })
                    .appendTo($box);
            });
            $box.show();
        }, 'json');
    }, 300);
});

// Function to select an employee and fill all fields
function selectEmployee(emp) {
    $('#fwdToNameInput').val(emp.full_name);
    $('#fwdToEmpIdHidden').val(emp.emp_id);
    $('#fwdToSectionHidden').val(emp.section_id || '');
    $('#fwdToUnitHidden').val(emp.unit_id || '');
    const line = [emp.section_name, emp.unit_name].filter(Boolean).join(' · ');
    $('#fwdAutoFillInfo').html('<i class="fas fa-check-circle text-success mr-1"></i> ' + (line || 'Employee selected'));
    $('#fwdEmployeeSuggestions').hide();
}

// When user presses Enter or Tab, auto‑select the first suggestion if available
$(document).on('keydown', '#fwdToNameInput', function(e) {
    if (e.which === 13 || e.which === 9) { // Enter or Tab
        if (currentEmployees.length > 0 && !$('#fwdToEmpIdHidden').val()) {
            selectEmployee(currentEmployees[0]);
            e.preventDefault(); // prevent form submit on Enter
        }
    }
});

// On blur, if the name matches an employee exactly, select it automatically
$(document).on('blur', '#fwdToNameInput', function() {
    const name = $(this).val().trim().toLowerCase();
    if (name && !$('#fwdToEmpIdHidden').val()) {
        // Try to find exact match in currentEmployees
        const match = currentEmployees.find(emp => emp.full_name.toLowerCase() === name);
        if (match) {
            selectEmployee(match);
        } else {
            // Also try to clear if name doesn't match any employee (optional)
            setTimeout(() => $('#fwdEmployeeSuggestions').hide(), 200);
        }
    } else {
        setTimeout(() => $('#fwdEmployeeSuggestions').hide(), 200);
    }
});

$(document).on('focus', '#fwdToNameInput', function() {
    if ($('#fwdEmployeeSuggestions').children().length) $('#fwdEmployeeSuggestions').show();
});

// Reset forward modal on open
$('#forwardModal').on('show.bs.modal', function() {
    $('#fwdToSectionHidden').val('');
    $('#fwdToUnitHidden').val('');
    $('#fwdToEmpIdHidden').val('');
    $('#fwdAutoFillInfo').text('');
    $('#fwdEmployeeSuggestions').hide().empty();
    currentEmployees = [];
});

// ── CSV Export (replaces broken DataTables button) ────────────────────────────
function exportTableToCSV() {
    let csv = [];
    const rows = document.querySelectorAll('#documentsTable tr');
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length; j++) {
            let text = cols[j].innerText.replace(/,/g, ';'); // replace commas to avoid CSV break
            row.push('"' + text + '"');
        }
        csv.push(row.join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'documents_export.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

// ── Delete Document ───────────────────────────────────────────────────────────
function deleteDocument(id) {
    Swal.fire({
        title: 'Delete Document?', text: 'This action cannot be undone.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then(result => {
        if (result.isConfirmed) {
            $.post('document_actions.php', { action: 'delete', id }, function(r) {
                if (r.success) { toastr.success('Document deleted.'); setTimeout(() => location.reload(), 1000); }
                else { toastr.error(r.message || 'Delete failed.'); }
            }, 'json').fail(function(xhr) {
                toastr.error('Server error. Check console for details.');
                console.error(xhr.responseText);
            });
        }
    });
}
</script>
<?php
// Re‑seed document types for any further use (optional)
$doc_types->data_seek(0);
?>
<?php include '../includes/footer.php'; ?>
</body>
</html>