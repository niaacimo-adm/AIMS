<?php
/**
 * Certificate of Employment — Employee Self-Service Request
 * Lets any logged-in employee submit a COE request and track its status.
 * HR reviews/approves/rejects these from views/certificate_of_employment.php.
 *
 * Place this file at:  views/coe_request.php
 * Needs:                sql/certificate_of_employment_requests.sql   (run once)
 */
require_once '../includes/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$emp_id = intval($_SESSION['emp_id'] ?? 0);
if (!$emp_id) {
    header('Location: dashboard.php');
    exit;
}

$purpose_categories = [
    'Employment application / job hunting',
    'Loan application (SSS, GSIS, bank, cooperative, etc.)',
    'Scholarship / educational grant',
    'Government transaction / legal requirement',
    'Visa / travel application',
    'Other',
];

/* ── AJAX ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'submit_request') {
        $purpose_category = trim($_POST['purpose_category'] ?? '');
        $purpose_other    = trim($_POST['purpose_other'] ?? '');
        $detail_type      = trim($_POST['detail_type'] ?? '');
        $num_copies       = max(1, intval($_POST['num_copies'] ?? 1));
        $date_needed      = trim($_POST['date_needed'] ?? '');
        $contact_no       = trim($_POST['contact_no'] ?? '');

        $valid_categories = [
            'Employment application / job hunting',
            'Loan application (SSS, GSIS, bank, cooperative, etc.)',
            'Scholarship / educational grant',
            'Government transaction / legal requirement',
            'Visa / travel application',
            'Other',
        ];
        $valid_details = ['with_salary', 'without_salary', 'dates_only'];

        if (!in_array($purpose_category, $valid_categories, true)) {
            echo json_encode(['success'=>false,'message'=>'Please select a valid purpose.']); exit;
        }
        if ($purpose_category === 'Other' && $purpose_other === '') {
            echo json_encode(['success'=>false,'message'=>'Please specify your purpose.']); exit;
        }
        if (!in_array($detail_type, $valid_details, true)) {
            echo json_encode(['success'=>false,'message'=>'Please select the certificate details you need.']); exit;
        }
        if ($date_needed !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $date_needed);
            if (!$d) { echo json_encode(['success'=>false,'message'=>'Invalid date needed.']); exit; }
        } else {
            $date_needed = null;
        }

        $ins = $db->prepare("
            INSERT INTO certificate_of_employment_requests
                (emp_id, purpose_category, purpose_other, detail_type, num_copies, date_needed, contact_no, status)
            VALUES (?,?,?,?,?,?,?,'Pending')
        ");
        $ins->bind_param(
            "isssiss",
            $emp_id, $purpose_category, $purpose_other, $detail_type, $num_copies, $date_needed, $contact_no
        );
        if ($ins->execute()) {
            echo json_encode(['success'=>true, 'request_id'=>$ins->insert_id]);
        } else {
            echo json_encode(['success'=>false,'message'=>$db->error]);
        }

    } elseif ($action === 'cancel_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $u = $db->prepare("UPDATE certificate_of_employment_requests SET status='Cancelled' WHERE request_id=? AND emp_id=? AND status='Pending'");
        $u->bind_param("ii", $request_id, $emp_id);
        if ($u->execute() && $u->affected_rows > 0) {
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'message'=>'This request can no longer be cancelled.']);
        }

    } elseif ($action === 'get_request_detail') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $s = $db->prepare("
            SELECT r.*, CONCAT(hr.first_name,' ',hr.last_name) AS reviewed_by_name
            FROM certificate_of_employment_requests r
            LEFT JOIN employee hr ON r.reviewed_by = hr.emp_id
            WHERE r.request_id = ? AND r.emp_id = ?
        ");
        $s->bind_param("ii", $request_id, $emp_id);
        $s->execute();
        echo json_encode($s->get_result()->fetch_assoc());
    }
    exit;
}

/* ── My requests ── */
$stmt = $db->prepare("
    SELECT * FROM certificate_of_employment_requests
    WHERE emp_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$my_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stats = ['total'=>0,'pending'=>0,'issued'=>0,'other'=>0];
foreach ($my_requests as $r) {
    $stats['total']++;
    if ($r['status'] === 'Pending') $stats['pending']++;
    elseif ($r['status'] === 'Issued') $stats['issued']++;
    else $stats['other']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request a Certificate of Employment | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        /* ══ TOKENS — same green-forest theme as the HR COE admin page ══ */
        :root {
            --h-bg:       #eef7f2;
            --h-card:     #ffffff;
            --h-card-alt: #f0faf5;
            --h-border:   rgba(42,152,99,0.18);
            --h-text:     #0f2d1e;
            --h-muted:    #4a7a5e;
            --h-primary:  #2a9863;
            --h-accent:   #24e78f;
            --h-success:  #2a9863;
            --h-warning:  #e67700;
            --h-danger:   #c92a2a;
            --h-shadow:   0 4px 24px rgba(42,152,99,.12);
            --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
        }
        body.dark-mode {
            --h-bg:       #0b1f17;
            --h-card:     #102f22;
            --h-card-alt: #0e2619;
            --h-border:   rgba(36,231,143,0.12);
            --h-text:     #d4f5e5;
            --h-muted:    #6aad8a;
            --h-primary:  #24e78f;
            --h-accent:   #2a9863;
            --h-success:  #24e78f;
            --h-warning:  #ffd43b;
            --h-danger:   #ff6b6b;
            --h-shadow:   0 4px 24px rgba(0,0,0,.35);
            --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
        }

        .hr-page { background:var(--h-bg); min-height:calc(100vh - 57px); padding-bottom:48px; }

        @keyframes hrMeshDrift { 0%{transform:translate(0,0) rotate(0deg);} 100%{transform:translate(3%,2%) rotate(2deg);} }
        @keyframes hrOrbFloat  { 0%,100%{opacity:.4;transform:translate(0,0) scale(1);} 33%{opacity:.7;transform:translate(18px,-26px) scale(1.05);} 66%{opacity:.5;transform:translate(-12px,16px) scale(.95);} }

        .hr-hero { background:#0b1f17; padding:36px 28px 66px; position:relative; overflow:hidden; }
        .hr-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:hrMeshDrift 22s ease-in-out infinite alternate; z-index:0;
        }
        .hr-hero-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .hr-orb { position:absolute; border-radius:50%; filter:blur(60px); animation:hrOrbFloat 18s ease-in-out infinite; }
        .hr-orb-1 { width:280px; height:280px; background:rgba(36,231,143,.11); top:-80px;   left:-60px;  animation-duration:21s; }
        .hr-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.10);  bottom:-50px;right:-40px; animation-delay:-7s; animation-duration:17s; }
        .hr-orb-3 { width:160px; height:160px; background:rgba(212,175,55,.06); top:40%;     right:20%;   animation-delay:-13s; animation-duration:24s; }
        .hr-hero-dots { position:absolute; inset:0; z-index:0; pointer-events:none; background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px); background-size:36px 36px; }
        .hr-hero-rings { position:absolute; top:50%; right:6%; transform:translateY(-50%); width:260px; height:260px; pointer-events:none; z-index:0; }
        .mh-logo-watermark { width:100%; height:100%; object-fit:contain; opacity:.14; }

        .hr-hero-inner { position:relative; z-index:2; }
        .hr-hero h1 { color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px; letter-spacing:-.3px; text-shadow:0 2px 14px rgba(0,0,0,.45); }
        .hr-hero p  { color:rgba(255,255,255,.7); margin:0 0 14px; font-size:.9rem; }
        .hr-hero-divider { width:54px; height:3px; background:linear-gradient(90deg,#2a9863,#24e78f); border-radius:3px; margin-bottom:12px; }
        .hr-hero-actions { position:relative; z-index:2; display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; }
        .btn-apply-leave-hero {
            background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; border:none; border-radius:9px;
            padding:9px 18px; font-size:.85rem; font-weight:700; cursor:pointer;
            display:inline-flex; align-items:center; gap:7px; transition:transform .15s, box-shadow .15s;
        }
        .btn-apply-leave-hero:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(36,231,143,.35); color:#fff; }

        .hr-content { max-width:auto; margin:-38px auto 0; padding:0 28px; position:relative; z-index:3; }

        .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px; }
        @media(max-width:900px){ .stats-row{ grid-template-columns:repeat(2,1fr); } }
        .stat-card { background:var(--h-card); border:1px solid var(--h-border); border-radius:14px; padding:16px 18px; display:flex; align-items:center; gap:12px; box-shadow:var(--h-shadow-sm); }
        .stat-ico { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff; flex-shrink:0; }
        .si-tot  { background:linear-gradient(135deg,#2a9863,#24e78f); }
        .si-pend { background:linear-gradient(135deg,#e67700,#ffa94d); }
        .si-iss  { background:linear-gradient(135deg,#087f5b,#20c997); }
        .si-oth  { background:linear-gradient(135deg,#3b5bdb,#748ffc); }
        .stat-val { font-size:1.4rem; font-weight:800; color:var(--h-text); line-height:1.1; }
        .stat-lbl { font-size:.74rem; color:var(--h-muted); font-weight:600; text-transform:uppercase; letter-spacing:.4px; }

        .h-ctrl { width:100%; background:var(--h-card); border:1.5px solid var(--h-border); border-radius:8px; padding:8px 12px; font-size:.85rem; color:var(--h-text); transition:border-color .18s, box-shadow .18s; box-sizing:border-box; }
        .h-ctrl:focus { outline:none; border-color:var(--h-primary); box-shadow:0 0 0 3px rgba(42,152,99,.13); }
        .btn-filter { background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; border:none; border-radius:8px; padding:9px 18px; font-size:.85rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:transform .15s, box-shadow .15s; }
        .btn-filter:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(42,152,99,.35); }
        .btn-reset { background:var(--h-card); color:var(--h-muted); border:1.5px solid var(--h-border); border-radius:8px; padding:9px 14px; font-size:.85rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:background .15s; }
        .btn-reset:hover { background:var(--h-bg); color:var(--h-text); }

        .h-card { background:var(--h-card); border:1px solid var(--h-border); border-radius:14px; box-shadow:var(--h-shadow-sm); overflow:hidden; margin-bottom:24px; }
        .h-card-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:16px 20px; border-bottom:1px solid var(--h-border); background:var(--h-card-alt); }
        .h-card-head-left { display:flex; align-items:center; gap:10px; }
        .h-card-ico { width:34px; height:34px; border-radius:9px; background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; display:flex; align-items:center; justify-content:center; font-size:14px; }
        .h-card-head h5 { margin:0; font-size:.98rem; font-weight:700; color:var(--h-text); }
        .h-rec-count { font-size:.74rem; color:var(--h-muted); background:var(--h-bg); border-radius:20px; padding:3px 10px; border:1px solid var(--h-border); }

        .h-tbl { width:100%; border-collapse:collapse; }
        .h-tbl th { background:var(--h-card-alt); padding:11px 14px; font-size:.7rem; font-weight:700; color:var(--h-muted); text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid var(--h-border); white-space:nowrap; }
        .h-tbl td { padding:13px 14px; font-size:.87rem; color:var(--h-text); border-bottom:1px solid var(--h-border); vertical-align:middle; }
        .h-tbl tr:last-child td { border-bottom:none; }
        .h-tbl tbody tr { transition:background .12s; }
        .h-tbl tbody tr:hover td { background:var(--h-card-alt); }

        .h-badge { padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:600; display:inline-block; }
        .hb-pending   { background:#fff4e0; color:#b45309; }
        .hb-issued    { background:#e6fbf4; color:#087f5b; }
        .hb-rejected  { background:#fff0f0; color:#c92a2a; }
        .hb-cancelled { background:#f1f3f5; color:#495057; }
        body.dark-mode .hb-pending   { background:#3d2e00; color:#ffd43b; }
        body.dark-mode .hb-issued    { background:#0d3d2c; color:#63e6be; }
        body.dark-mode .hb-rejected  { background:#3d0f0f; color:#ff8787; }
        body.dark-mode .hb-cancelled { background:#25292c; color:#adb5bd; }

        .action-btns { display:flex; gap:5px; align-items:center; }
        .btn-act { width:30px; height:30px; border-radius:7px; border:none; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-size:12px; transition:all .15s; }
        .ba-view { background:#e6f7ef; color:#2a9863; } .ba-view:hover { background:#2a9863; color:#fff; }
        .ba-dl   { background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; text-decoration:none; } .ba-dl:hover { opacity:.85; color:#fff; }
        .ba-cancel { background:#fff0f0; color:#9b1c1c; } .ba-cancel:hover { background:#9b1c1c; color:#fff; }

        .h-empty { text-align:center; padding:50px 20px; }
        .h-empty i { font-size:46px; opacity:.2; display:block; margin-bottom:14px; color:var(--h-muted); }
        .h-empty p { color:var(--h-muted); }

        .hm-modal .modal-content { border-radius:14px; border:none; overflow:hidden; background:var(--h-card); }
        .hm-modal .modal-header { background:linear-gradient(135deg,#0f2d1e,#2a9863); color:#fff; border:none; padding:18px 24px; }
        .hm-modal .modal-header .close { color:#fff; opacity:.7; } .hm-modal .modal-header .close:hover { opacity:1; }
        .hm-modal .modal-title { font-size:1rem; font-weight:700; }
        .hm-modal .modal-body { padding:24px; }
        .hm-modal .modal-footer { border-top:1px solid var(--h-border); padding:14px 24px; background:var(--h-card-alt); }

        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
        @media(max-width:520px){ .detail-grid{ grid-template-columns:1fr; } }
        .detail-item label { font-size:.68rem; font-weight:700; color:var(--h-muted); text-transform:uppercase; letter-spacing:.4px; display:block; margin-bottom:3px; }
        .detail-item span { font-size:.9rem; color:var(--h-text); font-weight:500; }
        .info-box { background:var(--h-card-alt); border:1px solid var(--h-border); border-radius:8px; padding:12px 14px; font-size:.87rem; color:var(--h-text); margin-top:4px; }

        .purpose-opt { display:block; border:1.5px solid var(--h-border); border-radius:10px; padding:10px 14px; margin-bottom:8px; cursor:pointer; font-size:.85rem; color:var(--h-text); transition:all .15s; }
        .purpose-opt:hover { border-color:var(--h-primary); }
        .purpose-opt input { margin-right:9px; }
        .purpose-opt.active { border-color:var(--h-primary); background:var(--h-card-alt); font-weight:600; }

        .detail-opt { display:block; border:1.5px solid var(--h-border); border-radius:10px; padding:10px 14px; margin-bottom:8px; cursor:pointer; font-size:.85rem; color:var(--h-text); transition:all .15s; }
        .detail-opt:hover { border-color:var(--h-primary); }
        .detail-opt input { margin-right:9px; }
        .detail-opt.active { border-color:var(--h-primary); background:var(--h-card-alt); font-weight:600; }

        @media(max-width:768px){ .hr-content{ padding:0 14px; } .hr-hero{ padding:24px 16px 50px; } }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper hr-page">

        <!-- Hero -->
        <div class="hr-hero">
            <div class="hr-hero-mesh"></div>
            <div class="hr-hero-dots"></div>
            <div class="hr-hero-orbs">
                <div class="hr-orb hr-orb-1"></div>
                <div class="hr-orb hr-orb-2"></div>
                <div class="hr-orb hr-orb-3"></div>
            </div>
            <div class="hr-hero-rings">
                <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
            </div>

            <div class="d-flex align-items-start justify-content-between flex-wrap" style="gap:14px;position:relative;z-index:2;">
                <div class="hr-hero-inner">
                    <h1><i class="fas fa-certificate mr-2" style="opacity:.85"></i>Request a Certificate of Employment</h1>
                    <div class="hr-hero-divider"></div>
                    <p>Submit a COE request and track its status — HR will review, approve, and generate the document.</p>
                </div>
                <div class="hr-hero-actions">
                    <span style="color:rgba(212,245,229,.65);font-size:.82rem;align-self:center;">
                        <i class="fas fa-calendar mr-1"></i><?= date('F d, Y') ?>
                    </span>
                    <button class="btn-apply-leave-hero" id="btnOpenNewRequest" type="button">
                        <i class="fas fa-plus-circle"></i> New Request
                    </button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="hr-content">

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card"><div class="stat-ico si-tot"><i class="fas fa-layer-group"></i></div><div><div class="stat-val"><?= $stats['total'] ?></div><div class="stat-lbl">Total Requests</div></div></div>
                <div class="stat-card"><div class="stat-ico si-pend"><i class="fas fa-hourglass-half"></i></div><div><div class="stat-val"><?= $stats['pending'] ?></div><div class="stat-lbl">Pending</div></div></div>
                <div class="stat-card"><div class="stat-ico si-iss"><i class="fas fa-check-circle"></i></div><div><div class="stat-val"><?= $stats['issued'] ?></div><div class="stat-lbl">Issued</div></div></div>
                <div class="stat-card"><div class="stat-ico si-oth"><i class="fas fa-info-circle"></i></div><div><div class="stat-val"><?= $stats['other'] ?></div><div class="stat-lbl">Rejected / Cancelled</div></div></div>
            </div>

            <!-- Table card -->
            <div class="h-card">
                <div class="h-card-head">
                    <div class="h-card-head-left">
                        <div class="h-card-ico"><i class="fas fa-table"></i></div>
                        <h5>My Requests</h5>
                    </div>
                    <span class="h-rec-count"><?= count($my_requests) ?> record(s)</span>
                </div>
                <div class="table-responsive">
                    <?php if (empty($my_requests)): ?>
                    <div class="h-empty">
                        <i class="fas fa-file-certificate"></i>
                        <p>You haven't requested a Certificate of Employment yet.</p>
                    </div>
                    <?php else: ?>
                    <table class="h-tbl">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Purpose</th>
                                <th>Details Requested</th>
                                <th>Copies</th>
                                <th>Date Needed</th>
                                <th>Requested On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $detail_labels = [
                            'with_salary'    => 'With Salary',
                            'without_salary' => 'Without Salary',
                            'dates_only'     => 'Inclusive Dates Only',
                        ];
                        $badge_map = [
                            'Pending'   => 'hb-pending',
                            'Issued'    => 'hb-issued',
                            'Approved'  => 'hb-issued',
                            'Rejected'  => 'hb-rejected',
                            'Cancelled' => 'hb-cancelled',
                        ];
                        foreach ($my_requests as $i => $r):
                            $purposeDisplay = $r['purpose_category'] === 'Other' ? $r['purpose_other'] : $r['purpose_category'];
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($purposeDisplay) ?></td>
                            <td><?= htmlspecialchars($detail_labels[$r['detail_type']] ?? $r['detail_type']) ?></td>
                            <td><?= (int)$r['num_copies'] ?></td>
                            <td><?= $r['date_needed'] ? date('M d, Y', strtotime($r['date_needed'])) : '—' ?></td>
                            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td><span class="h-badge <?= $badge_map[$r['status']] ?? 'hb-cancelled' ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-act ba-view btn-view-request" data-id="<?= $r['request_id'] ?>" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($r['status'] === 'Issued' && $r['coe_id']): ?>
                                    <a class="btn-act ba-dl" href="generate_coe.php?coe_id=<?= (int)$r['coe_id'] ?>" target="_blank" title="Download COE (.docx)">
                                        <i class="fas fa-file-word"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($r['status'] === 'Pending'): ?>
                                    <button class="btn-act ba-cancel btn-cancel-request" data-id="<?= $r['request_id'] ?>" title="Cancel Request">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /hr-content -->

        <?php include '../includes/mainfooter.php'; ?>
    </div>

    <!-- ══ New Request Modal ══ -->
    <div class="modal fade hm-modal" id="newRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-certificate mr-2"></i>Request a Certificate of Employment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Purpose of Request</label>
                        <div id="reqPurposeOptions">
                            <?php foreach ($purpose_categories as $cat): ?>
                            <label class="purpose-opt">
                                <input type="radio" name="reqPurposeCategory" value="<?= htmlspecialchars($cat) ?>"> <?= htmlspecialchars($cat) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" id="reqPurposeOther" class="h-ctrl mt-2" placeholder="Please specify your purpose" style="display:none;">
                    </div>

                    <div class="form-group">
                        <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Certificate Details Requested</label>
                        <div id="reqDetailOptions">
                            <label class="detail-opt">
                                <input type="radio" name="reqDetailType" value="with_salary"> With salary / compensation details
                            </label>
                            <label class="detail-opt">
                                <input type="radio" name="reqDetailType" value="without_salary"> Without salary / compensation details
                            </label>
                            <label class="detail-opt">
                                <input type="radio" name="reqDetailType" value="dates_only"> With inclusive dates of service only
                            </label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Number of Copies</label>
                            <input type="number" min="1" value="1" id="reqNumCopies" class="h-ctrl">
                        </div>
                        <div class="col-md-4 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Date Needed</label>
                            <input type="date" id="reqDateNeeded" class="h-ctrl">
                        </div>
                        <div class="col-md-4 form-group">
                            <label style="font-size:.75rem;font-weight:700;color:var(--h-muted);text-transform:uppercase;">Contact No.</label>
                            <input type="text" id="reqContactNo" class="h-ctrl" placeholder="09xx-xxx-xxxx">
                        </div>
                    </div>

                    <div class="info-box">
                        <i class="fas fa-info-circle mr-1"></i>
                        Your name, position, appointment status, and section will be pulled automatically from your employee record — no need to re-type them here.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-reset" data-dismiss="modal">Cancel</button>
                    <button type="button" id="btnSubmitRequest" class="btn-filter"><i class="fas fa-paper-plane"></i> Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ Detail Modal ══ -->
    <div class="modal fade hm-modal" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div id="detailModalInner">
                    <div class="modal-header"><h5 class="modal-title">Loading…</h5></div>
                    <div class="modal-body text-center"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>
<script>
var DETAIL_LABELS = {
    with_salary: 'With Salary',
    without_salary: 'Without Salary',
    dates_only: 'Inclusive Dates Only'
};
var BADGE_CLASS = {
    Pending: 'hb-pending', Issued: 'hb-issued', Approved: 'hb-issued',
    Rejected: 'hb-rejected', Cancelled: 'hb-cancelled'
};

$(document).ready(function() {

    $('#btnOpenNewRequest').on('click', function() {
        $('input[name="reqPurposeCategory"]').prop('checked', false);
        $('input[name="reqDetailType"]').prop('checked', false);
        $('.purpose-opt, .detail-opt').removeClass('active');
        $('#reqPurposeOther').val('').hide();
        $('#reqNumCopies').val(1);
        $('#reqDateNeeded').val('');
        $('#reqContactNo').val('');
        $('#newRequestModal').modal('show');
    });

    $(document).on('change', 'input[name="reqPurposeCategory"]', function() {
        $('.purpose-opt').removeClass('active');
        $(this).closest('.purpose-opt').addClass('active');
        if ($(this).val() === 'Other') {
            $('#reqPurposeOther').show();
        } else {
            $('#reqPurposeOther').hide().val('');
        }
    });

    $(document).on('change', 'input[name="reqDetailType"]', function() {
        $('.detail-opt').removeClass('active');
        $(this).closest('.detail-opt').addClass('active');
    });

    $('#btnSubmitRequest').on('click', function() {
        var purposeCategory = $('input[name="reqPurposeCategory"]:checked').val();
        var purposeOther    = $('#reqPurposeOther').val().trim();
        var detailType       = $('input[name="reqDetailType"]:checked').val();
        var numCopies        = parseInt($('#reqNumCopies').val()) || 1;
        var dateNeeded        = $('#reqDateNeeded').val();
        var contactNo         = $('#reqContactNo').val().trim();

        if (!purposeCategory) { Swal.fire({icon:'warning',title:'Select a purpose',confirmButtonColor:'#2a9863'}); return; }
        if (purposeCategory === 'Other' && !purposeOther) { Swal.fire({icon:'warning',title:'Please specify your purpose',confirmButtonColor:'#2a9863'}); return; }
        if (!detailType) { Swal.fire({icon:'warning',title:'Select the certificate details you need',confirmButtonColor:'#2a9863'}); return; }

        $('#btnSubmitRequest').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting…');
        $.post('coe_request.php', {
            ajax:1, action:'submit_request',
            purpose_category: purposeCategory,
            purpose_other: purposeOther,
            detail_type: detailType,
            num_copies: numCopies,
            date_needed: dateNeeded,
            contact_no: contactNo
        }, function(res) {
            $('#btnSubmitRequest').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Request');
            if (res.success) {
                $('#newRequestModal').modal('hide');
                Swal.fire({icon:'success',title:'Request Submitted',text:'HR will review your request shortly.',confirmButtonColor:'#2a9863'}).then(function() {
                    location.reload();
                });
            } else {
                Swal.fire({icon:'error',title:'Error',text:res.message||'Could not submit request.',confirmButtonColor:'#c92a2a'});
            }
        }, 'json');
    });

    // Cancel a pending request
    $(document).on('click', '.btn-cancel-request', function() {
        var id = $(this).data('id');
        Swal.fire({
            icon:'warning', title:'Cancel this request?',
            showCancelButton:true, confirmButtonColor:'#c92a2a', cancelButtonColor:'#4a7a5e',
            confirmButtonText:'Yes, cancel it'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            $.post('coe_request.php', {ajax:1, action:'cancel_request', request_id:id}, function(res) {
                if (res.success) {
                    Swal.fire({icon:'success',title:'Request Cancelled',confirmButtonColor:'#2a9863'}).then(()=>location.reload());
                } else {
                    Swal.fire({icon:'error',title:'Error',text:res.message,confirmButtonColor:'#c92a2a'});
                }
            }, 'json');
        });
    });

    // View details
    $(document).on('click', '.btn-view-request', function() {
        var id = $(this).data('id');
        $('#detailModal').modal('show');
        $('#detailModalInner').html('<div class="modal-header"><h5 class="modal-title">Loading…</h5></div><div class="modal-body text-center"><i class="fas fa-spinner fa-spin"></i></div>');
        $.post('coe_request.php', {ajax:1, action:'get_request_detail', request_id:id}, function(d) {
            var purposeDisplay = d.purpose_category === 'Other' ? d.purpose_other : d.purpose_category;
            var html = ''
                + '<div class="modal-header"><h5 class="modal-title"><i class="fas fa-certificate mr-2"></i>Request Details</h5>'
                + '<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>'
                + '<div class="modal-body">'
                + '<div class="detail-grid">'
                + '<div class="detail-item"><label>Purpose</label><span>'+purposeDisplay+'</span></div>'
                + '<div class="detail-item"><label>Details Requested</label><span>'+(DETAIL_LABELS[d.detail_type]||d.detail_type)+'</span></div>'
                + '<div class="detail-item"><label>Number of Copies</label><span>'+d.num_copies+'</span></div>'
                + '<div class="detail-item"><label>Date Needed</label><span>'+(d.date_needed||'Not specified')+'</span></div>'
                + '<div class="detail-item"><label>Contact No.</label><span>'+(d.contact_no||'Not provided')+'</span></div>'
                + '<div class="detail-item"><label>Requested On</label><span>'+d.created_at+'</span></div>'
                + '<div class="detail-item"><label>Status</label><span>'+d.status+'</span></div>'
                + (d.reviewed_by_name ? '<div class="detail-item"><label>Reviewed By</label><span>'+d.reviewed_by_name+'</span></div>' : '')
                + '</div>'
                + (d.remarks ? '<div class="info-box mt-3"><strong>HR Remarks:</strong> '+d.remarks+'</div>' : '')
                + '</div>'
                + '<div class="modal-footer">'
                + (d.status === 'Issued' && d.coe_id
                    ? '<a href="generate_coe.php?coe_id='+d.coe_id+'" target="_blank" class="btn-filter" style="text-decoration:none;"><i class="fas fa-file-word"></i> Download</a>'
                    : '<button type="button" class="btn-reset" data-dismiss="modal">Close</button>')
                + '</div>';
            $('#detailModalInner').html(html);
        }, 'json');
    });

});
</script>
</body>
</html>