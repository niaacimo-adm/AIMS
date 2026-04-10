<?php
    require_once '../config/database.php';
    require_once '../includes/auth.php';
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();

    function getUserEmployeeId($db, $session_user_id) {
        $emp_stmt = $db->prepare("SELECT emp_id FROM employee WHERE emp_id = ?");
        $emp_stmt->bind_param("i", $session_user_id);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();
        if ($emp_result->num_rows > 0) return $emp_result->fetch_assoc()['emp_id'];
        $user_stmt = $db->prepare("SELECT employee_id FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $session_user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        if ($user_result->num_rows > 0) {
            $uid = $user_result->fetch_assoc()['employee_id'];
            if ($uid) {
                $v = $db->prepare("SELECT emp_id FROM employee WHERE emp_id = ?");
                $v->bind_param("i", $uid); $v->execute();
                $vr = $v->get_result();
                if ($vr->num_rows > 0) return $vr->fetch_assoc()['emp_id'];
            }
        }
        return null;
    }

    $user_emp_id = getUserEmployeeId($db, $_SESSION['user_id']);
    if (!$user_emp_id) { header("Location: ../login.php"); exit(); }

    // ── AJAX: toggle star ────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_star') {
        $ts_file_id = intval($_POST['file_id'] ?? 0);
        $ts_star    = intval($_POST['starred']  ?? 0);
        $chk = $db->query("SHOW COLUMNS FROM files LIKE 'is_starred'");
        if ($chk && $chk->num_rows > 0) {
            $s = $db->prepare("UPDATE files SET is_starred = ? WHERE file_id = ?");
            $s->bind_param('ii', $ts_star, $ts_file_id);
            $s->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'is_starred column missing.']);
        }
        exit();
    }

    // ── Fetch all starred files for this user ────────────────────────
    $starred_files = [];
    $has_star_col  = false;
    $col_chk = $db->query("SHOW COLUMNS FROM files LIKE 'is_starred'");
    if ($col_chk && $col_chk->num_rows > 0) {
        $has_star_col = true;
        $q = "SELECT f.*,
                     CONCAT(e.first_name, ' ', e.last_name) AS owner_name,
                     fol.folder_name,
                     sec.section_name,
                     sec.section_id AS sec_id
              FROM   files f
              LEFT JOIN employee e   ON f.uploaded_by = e.emp_id
              LEFT JOIN folders  fol ON f.folder_id   = fol.folder_id
              LEFT JOIN section  sec ON fol.section_id = sec.section_id
              WHERE  f.is_starred = 1
                AND (f.is_deleted IS NULL OR f.is_deleted = 0)
                AND (
                    f.uploaded_by = ?
                    OR EXISTS (
                        SELECT 1 FROM folder_shares fs
                        WHERE fs.folder_id = f.folder_id
                          AND fs.shared_with_emp_id = ?
                          AND fs.is_active = 1
                    )
                )
              ORDER BY f.updated_at DESC, f.created_at DESC";
        $st = $db->prepare($q);
        $st->bind_param('ii', $user_emp_id, $user_emp_id);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) $starred_files[] = $row;
    }

    function sfFormatSize($bytes) {
        if (!$bytes) return '0 B';
        $k = 1024; $s = ['B','KB','MB','GB'];
        $i = (int)floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 1) . ' ' . $s[$i];
    }
    function sfIconClass($ext) {
        $m = [
            'pdf'=>'fas fa-file-pdf text-danger',
            'doc'=>'fas fa-file-word text-primary','docx'=>'fas fa-file-word text-primary',
            'xls'=>'fas fa-file-excel text-success','xlsx'=>'fas fa-file-excel text-success',
            'ppt'=>'fas fa-file-powerpoint text-warning','pptx'=>'fas fa-file-powerpoint text-warning',
            'jpg'=>'fas fa-file-image text-info','jpeg'=>'fas fa-file-image text-info',
            'png'=>'fas fa-file-image text-info','gif'=>'fas fa-file-image text-info',
            'mp4'=>'fas fa-file-video text-danger','avi'=>'fas fa-file-video text-danger',
            'mp3'=>'fas fa-file-audio text-info',
            'zip'=>'fas fa-file-archive text-secondary','rar'=>'fas fa-file-archive text-secondary',
            'txt'=>'fas fa-file-alt text-dark',
        ];
        return $m[strtolower($ext)] ?? 'fas fa-file text-secondary';
    }

    $section_id      = 0;
    $sb_section_name = 'Files';
    $folders         = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Starred Files – AIMS</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/folder_content.css">
    <style>
        .sf-page { padding: 1.25rem 1.5rem; }

        /* toolbar */
        .sf-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; margin-bottom:1.25rem; }
        .sf-heading  { display:flex; align-items:center; gap:.6rem; font-size:1.2rem; font-weight:700; color:var(--text-primary,#1e293b); }
        .sf-heading .sf-star-icon { color:#f59e0b; font-size:1.25rem; }
        .sf-count    { background:#f59e0b; color:#fff; border-radius:999px; padding:2px 10px; font-size:.78rem; font-weight:700; }
        .sf-search-wrap          { position:relative; width:260px; }
        .sf-search-wrap input    { width:100%; border:1px solid #e2e8f0; border-radius:999px; padding:7px 14px 7px 36px; font-size:.85rem; outline:none; transition:border-color .2s; background:#fff; }
        .sf-search-wrap input:focus { border-color:var(--primary-color,#800020); }
        .sf-search-wrap i        { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; }

        /* card + table */
        .sf-card  { background:#fff; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.06); }
        .sf-table { width:100%; border-collapse:collapse; }
        .sf-table thead tr { background:#f8fafc; border-bottom:2px solid #e2e8f0; }
        .sf-table th { padding:.7rem 1rem; font-size:.73rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.6px; white-space:nowrap; cursor:pointer; user-select:none; }
        .sf-table th:hover { color:#1e293b; }
        .sf-table th .sort-icon { margin-left:4px; opacity:.4; font-size:.65rem; }
        .sf-table th.active-sort .sort-icon { opacity:1; color:var(--primary-color,#800020); }
        .sf-table td { padding:.7rem 1rem; font-size:.875rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .sf-table tbody tr:last-child td { border-bottom:none; }
        .sf-table tbody tr { cursor:pointer; transition:background .12s; }
        .sf-table tbody tr:hover { background:#fffbeb; }

        /* cells */
        .sf-name-cell    { display:flex; align-items:center; gap:.6rem; }
        .sf-name-cell i  { font-size:1.35rem; flex-shrink:0; }
        .sf-name-text    { font-weight:500; color:#1e293b; word-break:break-word; }
        .sf-pill         { display:inline-block; background:#f1f5f9; border-radius:999px; padding:2px 10px; font-size:.78rem; color:#475569; white-space:nowrap; text-decoration:none; }
        .sf-star-cell    { text-align:center; width:40px; }
        .sf-star-toggle  { background:none; border:none; font-size:1.05rem; color:#f59e0b; cursor:pointer; padding:4px; border-radius:50%; transition:transform .2s; line-height:1; }
        .sf-star-toggle:hover { transform:scale(1.35); }
        .sf-actions      { display:flex; gap:.35rem; }
        .sf-table tbody tr .sf-actions { opacity:0; transition:opacity .15s; }
        .sf-table tbody tr:hover .sf-actions { opacity:1; }
        .sf-action-btn   { background:#f1f5f9; border:none; border-radius:6px; padding:5px 8px; font-size:.8rem; cursor:pointer; color:#475569; text-decoration:none; transition:background .15s; display:inline-flex; align-items:center; }
        .sf-action-btn:hover { background:#e2e8f0; color:#1e293b; }

        /* empty state */
        .sf-empty        { text-align:center; padding:4rem 2rem; }
        .sf-empty .sf-empty-icon { font-size:4rem; color:#e2e8f0; margin-bottom:1rem; display:block; }
        .sf-empty h5     { color:#64748b; margin-bottom:.4rem; }
        .sf-empty p      { color:#94a3b8; font-size:.9rem; }

        /* modal icon area */
        .sfm-icon-area { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:2.5rem 2rem; background:var(--bg-secondary,#f8fafc); gap:.5rem; }
        .sfm-big-icon  { font-size:4.5rem; margin-bottom:.5rem; }
        .sfm-badge     { display:inline-block; padding:4px 14px; border-radius:999px; background:#e2e8f0; color:#475569; font-size:.73rem; font-weight:700; letter-spacing:1.5px; }
        .sfm-sub       { font-size:.85rem; font-weight:500; color:#475569; max-width:240px; text-align:center; margin:0; }

        @media (max-width:768px) {
            .col-section,.col-size,.col-owner,.col-date { display:none; }
            .sf-search-wrap { width:100%; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<?php include '../includes/mainheader.php'; ?>
<?php include '../includes/sidebar_file.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-flex align-items-center" style="gap:.5rem;">
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="file_management.php">File Management</a></li>
                        <li class="breadcrumb-item active">Starred</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="sf-page">

                <div class="sf-toolbar">
                    <div class="sf-heading">
                        <i class="fas fa-star sf-star-icon"></i>
                        Starred
                        <span class="sf-count" id="sfCount"><?= count($starred_files) ?></span>
                    </div>
                    <div class="sf-search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="sfSearch" placeholder="Search starred files…">
                    </div>
                </div>

                <?php if (!$has_star_col): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        The <code>is_starred</code> column is missing. Please run:<br>
                        <code>ALTER TABLE files ADD COLUMN is_starred TINYINT(1) NOT NULL DEFAULT 0;</code>
                    </div>
                <?php elseif (empty($starred_files)): ?>
                    <div class="sf-card">
                        <div class="sf-empty">
                            <i class="far fa-star sf-empty-icon"></i>
                            <h5>No starred files yet</h5>
                            <p>Open any file and click the <i class="fas fa-star" style="color:#f59e0b;"></i> icon to add it here.<br>Starred files show up instantly so you can find them fast.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sf-card">
                        <table class="sf-table" id="sfTable">
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th data-col="name">Name <i class="fas fa-sort sort-icon"></i></th>
                                    <th class="col-section" data-col="section">Section <i class="fas fa-sort sort-icon"></i></th>
                                    <th class="col-folder" data-col="folder">Folder <i class="fas fa-sort sort-icon"></i></th>
                                    <th class="col-size" data-col="size">Size <i class="fas fa-sort sort-icon"></i></th>
                                    <th class="col-owner" data-col="owner">Owner <i class="fas fa-sort sort-icon"></i></th>
                                    <th class="col-date" data-col="date">Modified <i class="fas fa-sort sort-icon"></i></th>
                                    <th style="width:80px;"></th>
                                </tr>
                            </thead>
                            <tbody id="sfBody">
                            <?php foreach ($starred_files as $f):
                                $ic       = sfIconClass($f['file_type']);
                                $section  = htmlspecialchars($f['section_name'] ?? '—');
                                $folder   = htmlspecialchars($f['folder_name']  ?? 'Root');
                                $owner    = htmlspecialchars($f['owner_name']   ?? '—');
                                $size     = sfFormatSize($f['file_size']);
                                $folderId = (int)($f['folder_id'] ?? 0);
                                $secId    = (int)($f['sec_id']    ?? 0);
                                $modified = !empty($f['updated_at'])
                                    ? date('M j, Y', strtotime($f['updated_at']))
                                    : (!empty($f['created_at']) ? date('M j, Y', strtotime($f['created_at'])) : '—');
                                $rowData = json_encode([
                                    'file_id'      => $f['file_id'],
                                    'file_name'    => $f['file_name'],
                                    'file_type'    => $f['file_type'],
                                    'file_size'    => $f['file_size'],
                                    'owner_name'   => $f['owner_name']   ?? '',
                                    'folder_name'  => $f['folder_name']  ?? '',
                                    'section_name' => $f['section_name'] ?? '',
                                    'description'  => $f['description']  ?? '',
                                    'updated_at'   => $f['updated_at']   ?? '',
                                    'folder_id'    => $folderId,
                                    'section_id'   => $secId,
                                ]);
                            ?>
                            <tr class="sf-row"
                                data-file-id="<?= $f['file_id'] ?>"
                                data-name="<?= strtolower(htmlspecialchars($f['file_name'])) ?>"
                                onclick="sfOpenDetail(<?= htmlspecialchars($rowData, ENT_QUOTES) ?>)">
                                <td class="sf-star-cell" onclick="event.stopPropagation()">
                                    <button class="sf-star-toggle" title="Remove from starred"
                                            onclick="sfUnstar(<?= $f['file_id'] ?>, this)">
                                        <i class="fas fa-star"></i>
                                    </button>
                                </td>
                                <td>
                                    <div class="sf-name-cell">
                                        <i class="<?= $ic ?>"></i>
                                        <span class="sf-name-text"><?= htmlspecialchars($f['file_name']) ?></span>
                                    </div>
                                </td>
                                <td class="col-section"><span class="sf-pill"><?= $section ?></span></td>
                                <td class="col-folder">
                                    <?php if ($folderId): ?>
                                        <a class="sf-pill" href="folder_contents.php?folder_id=<?= $folderId ?>&section_id=<?= $secId ?>" onclick="event.stopPropagation()">
                                            <i class="fas fa-folder mr-1"></i><?= $folder ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="sf-pill"><?= $folder ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-size" style="color:#64748b;"><?= $size ?></td>
                                <td class="col-owner" style="color:#64748b;"><?= $owner ?></td>
                                <td class="col-date"  style="color:#64748b;"><?= $modified ?></td>
                                <td onclick="event.stopPropagation()">
                                    <div class="sf-actions">
                                        <a href="download_file.php?id=<?= $f['file_id'] ?>" class="sf-action-btn" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php if ($folderId): ?>
                                        <a href="folder_contents.php?folder_id=<?= $folderId ?>&section_id=<?= $secId ?>" class="sf-action-btn" title="Go to folder">
                                            <i class="fas fa-folder-open"></i>
                                        </a>
                                        <?php endif; ?>
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
    </section>
</div>

<?php include '../includes/mainfooter.php'; ?>
</div>

<!-- File Detail Modal -->
<div class="modal fade fv-modal" id="sfDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center" style="flex:1;min-width:0;">
                    <i id="sfmIcon" class="fas fa-file mr-2" style="font-size:20px;flex-shrink:0;"></i>
                    <h5 class="modal-title mb-0 text-truncate" id="sfmName" style="max-width:400px;">—</h5>
                </div>
                <div class="fv-header-actions">
                    <a id="sfmDownloadBtn" href="#" class="fv-header-btn" title="Download" target="_blank">
                        <i class="fas fa-download"></i>
                    </a>
                    <button class="fv-header-btn" title="Remove from Starred" onclick="sfUnstarCurrent()">
                        <i class="fas fa-star" style="color:#f59e0b;"></i>
                    </button>
                    <button type="button" class="fv-header-btn" data-dismiss="modal" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="modal-body p-0 d-flex" style="min-height:320px;">
                <!-- Icon area -->
                <div class="sfm-icon-area">
                    <i id="sfmBigIcon" class="fas fa-file sfm-big-icon"></i>
                    <span id="sfmBadge" class="sfm-badge">—</span>
                    <p id="sfmSub" class="sfm-sub">—</p>
                    <a id="sfmDownloadBtn2" href="#" class="btn btn-primary btn-sm mt-2" target="_blank">
                        <i class="fas fa-download mr-1"></i> Download File
                    </a>
                </div>
                <!-- Info panel -->
                <div class="fv-info-panel">
                    <div class="fv-info-title">File Details</div>
                    <div class="fv-info-row"><span class="fv-info-label">Type</span><span class="fv-info-value" id="sfmType">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Size</span><span class="fv-info-value" id="sfmSize">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Owner</span><span class="fv-info-value" id="sfmOwner">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Modified</span><span class="fv-info-value" id="sfmDate">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Section</span><span class="fv-info-value" id="sfmSection">—</span></div>
                    <div class="fv-info-row"><span class="fv-info-label">Folder</span><span class="fv-info-value" id="sfmFolder">—</span></div>
                    <hr style="margin:10px 0;">
                    <div class="fv-info-title">Description</div>
                    <div id="sfmDesc" style="color:var(--text-muted,#94a3b8);font-size:.82rem;margin-bottom:1rem;">—</div>
                    <hr style="margin:10px 0;">
                    <button class="btn btn-sm w-100 mb-2" onclick="sfUnstarCurrent()"
                            style="border:1px solid #f59e0b;color:#b45309;background:#fffbeb;">
                        <i class="fas fa-star mr-1" style="color:#f59e0b;"></i> Remove from Starred
                    </button>
                    <a id="sfmGoFolderBtn" href="#" class="btn btn-outline-secondary btn-sm w-100" style="display:none;">
                        <i class="fas fa-folder-open mr-1"></i> Open Folder
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
var _sfCurrent = null;

const _sfIconMap = {
    pdf:'fas fa-file-pdf text-danger',
    doc:'fas fa-file-word text-primary',docx:'fas fa-file-word text-primary',
    xls:'fas fa-file-excel text-success',xlsx:'fas fa-file-excel text-success',
    ppt:'fas fa-file-powerpoint text-warning',pptx:'fas fa-file-powerpoint text-warning',
    jpg:'fas fa-file-image text-info',jpeg:'fas fa-file-image text-info',
    png:'fas fa-file-image text-info',gif:'fas fa-file-image text-info',
    mp4:'fas fa-file-video text-danger',avi:'fas fa-file-video text-danger',
    mp3:'fas fa-file-audio text-info',
    zip:'fas fa-file-archive text-secondary',rar:'fas fa-file-archive text-secondary',
    txt:'fas fa-file-alt text-dark'
};

function sfFmtSize(b) {
    if (!b) return '0 B';
    const k=1024, s=['B','KB','MB','GB'];
    const i=Math.floor(Math.log(b)/Math.log(k));
    return parseFloat((b/Math.pow(k,i)).toFixed(1))+' '+s[i];
}

function sfOpenDetail(f) {
    _sfCurrent = f;
    const ext = (f.file_type||'').toLowerCase();
    const ic  = _sfIconMap[ext] || 'fas fa-file text-secondary';

    $('#sfmIcon').attr('class', ic + ' mr-2');
    $('#sfmBigIcon').attr('class', ic + ' sfm-big-icon');
    $('#sfmName').text(f.file_name);
    $('#sfmSub').text(f.file_name);
    $('#sfmBadge').text((f.file_type||'').toUpperCase());
    $('#sfmDownloadBtn,#sfmDownloadBtn2').attr('href', 'download_file.php?id=' + f.file_id);

    $('#sfmType').text((f.file_type||'').toUpperCase());
    $('#sfmSize').text(sfFmtSize(f.file_size));
    $('#sfmOwner').text(f.owner_name || '—');
    $('#sfmDate').text(f.updated_at ? new Date(f.updated_at).toLocaleDateString() : '—');
    $('#sfmSection').text(f.section_name || '—');
    $('#sfmFolder').text(f.folder_name || 'Root');
    $('#sfmDesc').text(f.description || '—');

    if (f.folder_id) {
        $('#sfmGoFolderBtn').attr('href', 'folder_contents.php?folder_id='+f.folder_id+'&section_id='+f.section_id).show();
    } else {
        $('#sfmGoFolderBtn').hide();
    }

    $('#sfDetailModal').modal('show');
}

function sfUnstar(fileId, btn) {
    Swal.fire({
        title: 'Remove from Starred?',
        text: 'This file will be removed from your starred list.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        confirmButtonText: 'Remove',
        cancelButtonText: 'Cancel'
    }).then(res => {
        if (!res.isConfirmed) return;
        $.post('starred_files.php', { action: 'toggle_star', file_id: fileId, starred: 0 },
        function(resp) {
            try {
                const r = typeof resp === 'string' ? JSON.parse(resp) : resp;
                if (r.success) {
                    const row = $(btn).closest('tr');
                    row.fadeOut(300, function() {
                        row.remove();
                        const vis = $('#sfBody tr:visible').length;
                        $('#sfCount').text(vis);
                        if (vis === 0) location.reload();
                    });
                    Swal.fire({ title:'Removed', icon:'success', timer:900, showConfirmButton:false });
                }
            } catch(e) {}
        });
    });
}

function sfUnstarCurrent() {
    if (!_sfCurrent) return;
    const fileId = _sfCurrent.file_id;
    $('#sfDetailModal').modal('hide');
    setTimeout(function() {
        const row = $('#sfBody tr[data-file-id="' + fileId + '"]');
        const btn = row.find('.sf-star-toggle')[0];
        if (btn) sfUnstar(fileId, btn);
    }, 350);
}

/* Search */
$('#sfSearch').on('input', function() {
    const q = this.value.toLowerCase().trim();
    let vis = 0;
    $('#sfBody tr.sf-row').each(function() {
        const match = $(this).data('name').includes(q);
        $(this).toggle(match);
        if (match) vis++;
    });
    $('#sfCount').text(vis);
});

/* Sort */
(function() {
    let sortCol = '', sortAsc = true;
    $('#sfTable thead th[data-col]').on('click', function() {
        const col = $(this).data('col');
        sortAsc = (sortCol === col) ? !sortAsc : true;
        sortCol = col;
        $('#sfTable thead th').removeClass('active-sort').find('.sort-icon').attr('class','fas fa-sort sort-icon');
        $(this).addClass('active-sort').find('.sort-icon').attr('class','fas fa-sort-'+(sortAsc?'up':'down')+' sort-icon');

        const rows = $('#sfBody tr.sf-row').toArray();
        rows.sort(function(a, b) {
            let av='', bv='';
            if (col==='name')    { av=$(a).find('.sf-name-text').text(); bv=$(b).find('.sf-name-text').text(); }
            if (col==='section') { av=$(a).find('.col-section').text().trim(); bv=$(b).find('.col-section').text().trim(); }
            if (col==='folder')  { av=$(a).find('.col-folder').text().trim();  bv=$(b).find('.col-folder').text().trim(); }
            if (col==='owner')   { av=$(a).find('.col-owner').text().trim();   bv=$(b).find('.col-owner').text().trim(); }
            if (col==='date')    { av=$(a).find('.col-date').text().trim();    bv=$(b).find('.col-date').text().trim(); }
            if (col==='size') {
                av=parseFloat($(a).find('.col-size').text())||0;
                bv=parseFloat($(b).find('.col-size').text())||0;
                return sortAsc ? av-bv : bv-av;
            }
            return sortAsc ? av.localeCompare(bv) : bv.localeCompare(av);
        });
        $.each(rows, function(i,r){ $('#sfBody').append(r); });
    });
})();
</script>
</body>
</html>