<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!class_exists('ZipArchive')) {
    ?><!DOCTYPE html><html><head><title>ZIP Required</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    </head><body><div class="container mt-5"><div class="card border-warning">
    <div class="card-header bg-warning"><h4><i class="fas fa-exclamation-triangle"></i> ZipArchive Extension Required</h4></div>
    <div class="card-body">
    <p>Enable <code>extension=zip</code> in <code>php.ini</code> and restart Apache.</p>
    <a href="javascript:history.back()" class="btn btn-primary">Back</a>
    </div></div></div></body></html><?php
    exit();
}

/* ── DB ── */
$database = new Database();
$db       = $database->getConnection();

$emp_id       = intval($_SESSION['emp_id']  ?? 0);
$user_role_id = intval($_SESSION['role_id'] ?? 0);
$is_hr        = in_array($user_role_id, [1, 2, 12, 14, 25]);
$download     = isset($_GET['download']);

$leave_request_id = intval($_GET['leave_request_id'] ?? 0);
if (!$leave_request_id) die('<p style="font-family:Arial;padding:20px;color:red">No leave request specified.</p>');

/* ── Fetch ── */
$stmt = $db->prepare("
    SELECT lr.*,
           lt.leave_type_name,
           lt.is_main AS lt_is_main,
           e.first_name, e.last_name, e.middle_name, e.id_number,
           e.emp_id   AS employee_emp_id,
           pos.position_name,
           COALESCE(s.section_name, s2.section_name)   AS section_name,
           COALESCE(s.section_id,   s2.section_id)     AS section_id,
           ap.status_name AS appointment_status,
           CONCAT(hr.first_name,' ',hr.last_name) AS approved_by_name
    FROM leave_request lr
    LEFT JOIN leave_type          lt  ON lr.leave_type_id        = lt.leave_type_id
    LEFT JOIN employee            e   ON lr.emp_id               = e.emp_id
    LEFT JOIN position            pos ON e.position_id           = pos.position_id
    LEFT JOIN section             s   ON e.section_id            = s.section_id
    LEFT JOIN unit_section        us  ON e.unit_section_id       = us.unit_id
    LEFT JOIN section             s2  ON us.section_id           = s2.section_id
    LEFT JOIN appointment_status  ap  ON e.appointment_status_id = ap.appointment_id
    LEFT JOIN employee            hr  ON lr.approved_by          = hr.emp_id
    WHERE lr.leave_request_id = ?
");
$stmt->bind_param('i', $leave_request_id);
$stmt->execute();
$d = $stmt->get_result()->fetch_assoc();

if (!$d) die('<p style="font-family:Arial;padding:20px;color:red">Leave request not found.</p>');

/* ── Access control ── */
if (!$is_hr && intval($d['employee_emp_id']) !== $emp_id) {
    die('<p style="font-family:Arial;padding:20px;color:red">Access denied.</p>');
}

/* ── Template directory ── */
define('TEMPLATE_DIR', realpath(__DIR__ . '/../public/templates/'));

$section_lc = strtolower($d['section_name'] ?? '');
if (str_contains($section_lc, 'admin')) {
    $tpl_file = 'Leave_Form_Admin.docx';
} elseif (str_contains($section_lc, 'engineer')) {
    $tpl_file = 'Leave_Form_Eng.docx';
} elseif (str_contains($section_lc, 'finance')) {
    $tpl_file = 'Leave_Form_Fin.docx';
} else {
    $tpl_file = 'Leave_Form_OM.docx';
}
$template_path = TEMPLATE_DIR ? TEMPLATE_DIR . DIRECTORY_SEPARATOR . $tpl_file : '';

/* ── Derived values ── */
$last_name     = strtoupper(trim($d['last_name']));
$first_name    = strtoupper(trim($d['first_name']));
$middle_ini    = strtoupper(trim($d['middle_name']));
$filed_date    = $d['created_at']  ? date('F d, Y', strtotime($d['created_at']))  : date('F d, Y');
$approved_date = $d['approved_at'] ? date('F d, Y', strtotime($d['approved_at'])) : '';
$position      = $d['position_name'] ?? '';
$inclusive     = $d['inclusive_dates'] ?? '';
$num_days      = intval($d['number_of_days'] ?? 0);
$reason        = $d['reason'] ?? '';
$hr_remarks    = $d['hr_remarks'] ?? '';
$status        = strtolower($d['status'] ?? '');
$is_approved   = ($status === 'approved');
$is_rejected   = in_array($status, ['rejected', 'disapproved']);
$lt_lc         = strtolower($d['leave_type_name'] ?? '');
$lt_is_main    = (int)($d['lt_is_main'] ?? 1); // 0 = user-specified "Others" type

/* ── Fetch VL (leave_type_id=1) and SL (leave_type_id=2) balances for 7.A ── */
$bal_year = (int) date('Y', strtotime($d['created_at'] ?? 'now'));
$bal_stmt = $db->prepare("
    SELECT leave_type_id,
           total_credits,
           used_days,
           remaining_days
    FROM leave_balance
    WHERE emp_id = ? AND leave_type_id IN (1, 2) AND year = ?
");
$bal_stmt->bind_param('ii', $d['employee_emp_id'], $bal_year);
$bal_stmt->execute();
$bal_res = $bal_stmt->get_result();
$vl_bal  = ['total_credits' => 0, 'used_days' => 0, 'remaining_days' => 0];
$sl_bal  = ['total_credits' => 0, 'used_days' => 0, 'remaining_days' => 0];
while ($brow = $bal_res->fetch_assoc()) {
    if ((int)$brow['leave_type_id'] === 1) $vl_bal = $brow;
    if ((int)$brow['leave_type_id'] === 2) $sl_bal = $brow;
}
$bal_stmt->close();

// Which column does "Less This Application" apply to?
$is_vl_request = $lt_is_main && (str_contains($lt_lc, 'vacation') || str_contains($lt_lc, 'mandatory') || str_contains($lt_lc, 'forced'));
$is_sl_request = $lt_is_main && str_contains($lt_lc, 'sick');

// Format: whole numbers show as integer, decimals show 3 places
function fmt_days($v): string {
    $v = (float) $v;
    return ($v == (int)$v) ? number_format($v, 0) : rtrim(number_format($v, 3), '0');
}

/* ── Map leave type name to the checkbox label in the DOCX ── */
$leave_checkbox_map = [
    'vacation'                     => 'VACATION LEAVE',
    'mandatory'                    => 'MANDATORY LEAVE',
    'forced'                       => 'MANDATORY LEAVE',
    'sick'                         => 'SICK LEAVE',
    'maternity'                    => 'MATERNITY LEAVE',
    'paternity'                    => 'PATERNITY LEAVE',
    'special privilege'            => 'SPECIAL PRIVILEGE LEAVE',
    'solo parent'                  => 'SOLO PARENT LEAVE',
    'study'                        => 'STUDY LEAVE',
    'vawc'                         => '10-DAY VAWC LEAVE',
    '10-day'                       => '10-DAY VAWC LEAVE',
    'rehabilitation'               => 'REHABILITATION PRIVILEGE',
    'special leave benefits for women' => 'SPECIAL LEAVE BENEFITS FOR WOMEN',
    'special emergency'            => 'SPECIAL EMERGENCY (CALAMITY) LEAVE',
    'calamity'                     => 'SPECIAL EMERGENCY (CALAMITY) LEAVE',
    'adoption'                     => 'ADOPTION LEAVE',
    'wellness'                     => 'WELLNESS LEAVE',
    'monetization'                 => 'Monetization of Leave Credits',
    'terminal'                     => 'Terminal Leave',
];
$matched_leave = '';
if ($lt_is_main) {
    foreach ($leave_checkbox_map as $kw => $label) {
        if (str_contains($lt_lc, $kw)) { $matched_leave = $label; break; }
    }
}
// $matched_leave === '' at this point means: use the OTHERS checkbox + fill the label.
/* ══════════════════════════════════════════════════════
   HELPER: inject text into an empty floating text box
   The box has: <w:txbxContent><w:p ...><w:pPr>...</w:pPr></w:p></w:txbxContent>
   We add a <w:r><w:t>VALUE</w:t></w:r> before </w:p>.
   limit=1 so only the mc:Choice copy is modified (not the mc:Fallback duplicate).
══════════════════════════════════════════════════════ */
function inject_textbox(string $xml, string $box_id, string $value): string
{
    return preg_replace_callback(
        '/(<wp:docPr id="' . preg_quote($box_id, '/') . '"[^\/]*\/>)(.*?)(<w:txbxContent>)(\s*<w:p\b[^>]*>)(.*?)(<\/w:p>)(\s*<\/w:txbxContent>)/s',
        function ($m) use ($value) {
            $inner = $m[5];
            $has_run  = strpos($inner, '<w:r>') !== false || strpos($inner, '<w:r ') !== false;
            // Must match <w:t> or <w:t  (text element) but NOT <w:tab/> or <w:tbl> etc.
            $has_text = preg_match('/<w:t[\s>]/', $inner) === 1;
            // Inject if: no run at all, OR run exists but contains only a <w:tab/> placeholder (no real text)
            if (!$has_run || ($has_run && !$has_text)) {
                // Strip any tab-only runs so the box is clean before injecting
                $inner = preg_replace('/<w:r\b[^>]*>(?:(?!<w:t[\s>]).)*?<w:tab\/>(?:(?!<w:t[\s>]).)*?<\/w:r>/s', '', $inner);
                $inner .= '<w:r><w:t xml:space="preserve">' . htmlspecialchars($value, ENT_XML1, 'UTF-8') . '</w:t></w:r>';
            }
            return $m[1] . $m[2] . $m[3] . $m[4] . $inner . $m[6] . $m[7];
        },
        $xml,
        1
    );
}

/* ══════════════════════════════════════════════════════
   HELPER: inject text into an empty table cell paragraph
   identified by w14:paraId. Skips if the paragraph
   already has a run (safety guard).
══════════════════════════════════════════════════════ */
function inject_para(string $xml, string $para_id, string $value): string
{
    if ($value === '') return $xml;
    $escaped = htmlspecialchars($value, ENT_XML1, 'UTF-8');
    return preg_replace_callback(
        '/(<w:p\b[^>]*w14:paraId="' . preg_quote($para_id, '/') . '"[^>]*>)(.*?)(<\/w:p>)/s',
        function ($m) use ($escaped) {
            // Guard: skip if a real <w:r> run already exists (not just <w:rPr> inside <w:pPr>)
            if (preg_match('/<w:r[\s>]/', $m[2])) return $m[0];
            $run = '<w:r><w:rPr>'
                 . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
                 . '<w:b/><w:sz w:val="20"/><w:szCs w:val="20"/><w:lang w:val="en-US"/>'
                 . '</w:rPr><w:t>' . $escaped . '</w:t></w:r>';
            return $m[1] . $m[2] . $run . $m[3];
        },
        $xml,
        1
    );
}

/* ══════════════════════════════════════════════════════
   FILL THE DOCX TEMPLATE
══════════════════════════════════════════════════════ */
function fill_docx(string $tpl, array $o): string
{
    if (!$tpl || !file_exists($tpl)) {
        die('<p style="font-family:Arial;padding:20px;color:red">Template not found: <code>' .
            htmlspecialchars($tpl) . '</code><br>' .
            'Place the four leave form DOCX templates in <code>/public/templates/</code></p>');
    }

    $out = tempnam(sys_get_temp_dir(), 'lvf_') . '.docx';
    copy($tpl, $out);

    $zip = new ZipArchive();
    if ($zip->open($out) !== true) { @unlink($out); die('Cannot open template.'); }

    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false) { $zip->close(); @unlink($out); die('Cannot read document.xml'); }

    /* 1 ── Tick the correct leave-type checkbox
             FIX: The ☐ character and its label are in SEPARATE table cells (<w:tc>),
             so the boundary must be </w:tr> (end of row) not </w:tc> (end of cell).
             Pattern: ☐ in one <w:t> run, followed within ~1100 chars by the label text,
             allowing the match to cross cell boundaries within the same row.            */
    if ($o['matched_leave']) {
        $escaped = preg_quote($o['matched_leave'], '/');
        $xml = preg_replace(
            '/(<w:t[^>]*>)(☐)(<\/w:t>)((?:(?!<\/w:tr>).){0,1100})(' . $escaped . ')/s',
            '$1☑$3$4$5',
            $xml, 1
        );
    } else {
        $others_pos = strpos($xml, 'OTHERS:');
        if ($others_pos !== false) {
            $before_others = substr($xml, 0, $others_pos);
            $after_others  = substr($xml, $others_pos);


            $xml = $before_others . $after_others;
        }

        // Fill the OTHERS label - method depends on which template is being used
        if (!empty($o['others_label'])) {
            $safe_label = htmlspecialchars($o['others_label'], ENT_XML1, 'UTF-8');
            $is_eng = str_contains($o['tpl_file'], 'Eng');
            if ($is_eng) {
                // Eng template: inject into floating textbox that overlays the underline
                $xml = inject_textbox($xml, '1698696659', $o['others_label']);
            } else {
                // Admin/Fin/OM: replace the plain _____+ text run after OTHERS:
                $others_pos2 = strpos($xml, 'OTHERS:');
                if ($others_pos2 !== false) {
                    $before2 = substr($xml, 0, $others_pos2);
                    $after2  = substr($xml, $others_pos2);
                    $after2  = preg_replace(
                        '/<w:t[^>]*>_____+<\/w:t>/',
                        '<w:t>' . $safe_label . '</w:t>',
                        $after2, 1
                    );
                    $xml = $before2 . $after2;
                }
            }
        }
    }

    /* 2 ── DATE OF FILING / POSITION line ── */
    $xml = str_replace(
        '3. DATE OF FILING ___________________    4. POSITION _____________________ 5. SALARY ___________',
        '3. DATE OF FILING ' . htmlspecialchars($o['filed_date'], ENT_XML1, 'UTF-8') .
        '    4. POSITION '   . htmlspecialchars($o['position'],   ENT_XML1, 'UTF-8') .
        '    5. SALARY ___________',
        $xml
    );

    /* 3 ── NAME into text boxes
            Admin / Finance / OM: box 5 = Last, First   box 7 = Middle
            Engineering:          box 47278592 = full name in one box              */
    $xml = inject_textbox($xml, '5',          $o['last_name'] . ', ' . $o['first_name']);
    $xml = inject_textbox($xml, '7',          $o['middle_ini']);
    $xml = inject_textbox($xml, '47278592',   $o['last_name'] . ', ' . $o['first_name'] . '  ' . $o['middle_ini']);

    /* 4 ── Number of days (box 11) ── */
    $xml = inject_textbox($xml, '11', (string)$o['num_days']);

    /* 5 ── Inclusive dates (box 12) ── */
    $xml = inject_textbox($xml, '12', $o['inclusive']);

    /* 6 ── Section 7A: "As of" ── */
    if ($o['is_approved'] && $o['approved_date']) {
        $xml = str_replace(
            'As of _________________________',
            'As of ' . htmlspecialchars($o['approved_date'], ENT_XML1, 'UTF-8'),
            $xml
        );
    }

    /* 6b ── Section 7A: leave credit values
       paraId map (identical across all 4 DOCX templates):
         TOTAL EARNED      VL = 3CA9525C   SL = 46C96731
         LESS THIS APP     VL = 74835759   SL = 6DBAFA00
         BALANCE           VL = 4A4D7751   SL = 18E745C1  */
    $xml = inject_para($xml, '3CA9525C', $o['vl_total']);    // Total Earned – VL
    $xml = inject_para($xml, '46C96731', $o['sl_total']);    // Total Earned – SL
    $xml = inject_para($xml, '74835759', $o['vl_less']);     // Less This App – VL (blank if SL request)
    $xml = inject_para($xml, '6DBAFA00', $o['sl_less']);     // Less This App – SL (blank if VL request)
    $xml = inject_para($xml, '4A4D7751', $o['vl_balance']);  // Balance – VL
    $xml = inject_para($xml, '18E745C1', $o['sl_balance']);  // Balance – SL

    /* 7 ── Section 7B checkboxes ── */
    if ($o['is_approved']) {
        $xml = preg_replace(
            '/(<w:t[^>]*>)(☐)(<\/w:t>)((?:(?!<\/w:tr>).){0,1100})(For approval)/s',
            '$1☑$3$4$5', $xml, 1
        );
    } elseif ($o['is_rejected']) {
        $xml = preg_replace(
            '/(<w:t[^>]*>)(☐)(<\/w:t>)((?:(?!<\/w:tr>).){0,1100})(For disapproval due to)/s',
            '$1☑$3$4$5', $xml, 1
        );
        if ($o['hr_remarks']) {
            // "For disapproval due to" and the following "___" line live in TWO separate
            // <w:t> runs (split across sibling <w:r> elements), so a plain string match
            // across both never hits. Match run-by-run instead, allowing any run markup
            // in between (but not crossing the row boundary).
            $remark_xml = htmlspecialchars($o['hr_remarks'], ENT_XML1, 'UTF-8');
            $xml = preg_replace_callback(
                '/(<w:t[^>]*>)For disapproval due to(<\/w:t>)((?:(?!<\/w:tr>).){0,300}?<w:t[^>]*>)\s*_+\s*(<\/w:t>)/s',
                function ($m) use ($remark_xml) {
                    return $m[1] . 'For disapproval:' . $m[2] . $m[3] . ' ' . $remark_xml . $m[4];
                },
                $xml, 1
            );
        }
    }

    /* 8 ── Section 7C: approved days ── */
    if ($o['is_approved'] && $o['num_days'] > 0) {
        $days_val = htmlspecialchars((string)$o['num_days'], ENT_XML1, 'UTF-8');
        // The DOCX has a standalone <w:t>_____</w:t> run right before "days with pay"
        $xml = preg_replace('/<w:t>_____<\/w:t>/', '<w:t>' . $days_val . '</w:t>', $xml, 1);
    }

    /* 9 ── Section 7D: disapproval reason line ──
       The first blank line after "DUE TO:" is ALSO split into two runs: a
       whitespace run (xml:space="preserve") followed by a plain underscore run.
       Locate it via the "DUE TO:" label so only the first blank line (not the
       later, identical-looking blank lines below it) gets filled.             */
    if ($o['is_rejected'] && $o['hr_remarks']) {
        $due_pos = strpos($xml, 'DUE TO:');
        if ($due_pos !== false) {
            $before = substr($xml, 0, $due_pos);
            $after  = substr($xml, $due_pos);
            $remark_xml = htmlspecialchars($o['hr_remarks'], ENT_XML1, 'UTF-8');
            $after = preg_replace_callback(
                '/<w:t[^>]*>\s*<\/w:t><\/w:r><w:r[^>]*><w:rPr>(?:(?!<\/w:rPr>).)*<\/w:rPr><w:t[^>]*>_+<\/w:t><\/w:r>/s',
                function ($m) use ($remark_xml) {
                    // Collapse both runs' text into the first run, empty out the second
                    $collapsed = preg_replace('/<w:t[^>]*>_+<\/w:t>(<\/w:r>)$/', '<w:t xml:space="preserve"></w:t>$1', $m[0]);
                    return preg_replace('/<w:t[^>]*>\s*<\/w:t>/', '<w:t xml:space="preserve"> ' . $remark_xml . '</w:t>', $collapsed, 1);
                },
                $after, 1
            );
            $xml = $before . $after;
        }
    }


    /* ── Save ── */
    $zip->deleteName('word/document.xml');
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();

    return $out;
}

/* ══════════════════════════════════════════════════════
   DOWNLOAD MODE
══════════════════════════════════════════════════════ */
if ($download) {
    // Pre-compute 7A values
    $vl_total   = fmt_days($vl_bal['total_credits']);
    $sl_total   = fmt_days($sl_bal['total_credits']);
    $vl_less    = $is_vl_request ? fmt_days($num_days) : '';
    $sl_less    = $is_sl_request ? fmt_days($num_days) : '';
    $vl_balance = fmt_days($vl_bal['remaining_days']);
    $sl_balance = fmt_days($sl_bal['remaining_days']);

    $opts = compact(
        'last_name','first_name','middle_ini',
        'filed_date','approved_date','position',
        'inclusive','num_days','hr_remarks',
        'matched_leave','is_approved','is_rejected',
        'vl_total','sl_total','vl_less','sl_less','vl_balance','sl_balance'
    );
    $opts['others_label'] = $d['leave_type_name'] ?? '';
    $opts['tpl_file']     = $tpl_file;
    $filled   = fill_docx($template_path, $opts);
    $safe     = preg_replace('/[^A-Za-z0-9_\-]/', '_', $last_name . '_' . $first_name);
    $filename = 'LeaveForm_' . $safe . '_' . date('Ymd') . '.docx';

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filled));
    if (ob_get_level()) ob_end_clean();
    flush();
    readfile($filled);
    @unlink($filled);
    exit();
}

/* ══════════════════════════════════════════════════════
   PREVIEW MODE
══════════════════════════════════════════════════════ */
$status_color = match ($status) {
    'approved'                      => '#2a9863',
    'rejected','disapproved'        => '#c92a2a',
    'cancelled'                     => '#64748b',
    default                         => '#b45309',
};
$status_label    = ucfirst($d['status']);
$emp_display     = htmlspecialchars($d['first_name'] . ' ' . $d['last_name']);
$section_display = htmlspecialchars($d['section_name'] ?? 'No Section');
$lt_display      = htmlspecialchars($d['leave_type_name'] ?? 'N/A');
$download_url    = '?leave_request_id=' . $leave_request_id . '&download=1';
$back_url        = $is_hr ? 'hr_leave_monitoring.php' : 'leave_request.php';
$tpl_missing     = !($template_path && file_exists($template_path));

function pf($label, $value) {
    return '<div class="pf-item"><span class="pf-lbl">' . htmlspecialchars($label) .
           '</span><span class="pf-val">' . ($value !== '' ? $value : '<span style="color:#cbd5e1">—</span>') . '</span></div>';
}
function chkbx($on) { return $on ? '<span style="color:#099268;font-size:1.05rem">☑</span>' : '<span style="color:#94a3b8;font-size:1.05rem">☐</span>'; }

$section_rec = [
    'name'  => '',
    'title' => '',
];
if (str_contains($section_lc, 'admin')) {
    $section_rec = ['name'=>'IAN FELICIANO P. BERDIN III','title'=>'Acting Section Head – Administrative Section'];
} elseif (str_contains($section_lc, 'engineer')) {
    $section_rec = ['name'=>'ENGR. LECH FIDEL C. PANTE','title'=>'Acting Section Head – Engineering Section'];
} elseif (str_contains($section_lc, 'finance')) {
    $section_rec = ['name'=>'MAUREEN R. DURAN','title'=>'Corporate Accounts Analyst'];
} else {
    $section_rec = ['name'=>'IAN FELICIANO III P. BERDIN','title'=>'O.I.C. – O&M Section'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Form – <?= $emp_display ?> | NIA-ACIMO</title>
    <?php include '../includes/header.php'; ?>
    <style>
        :root {
            --lf-bg:#eef7f2; --lf-card:#fff; --lf-border:rgba(42,152,99,0.18);
            --lf-text:#0f2d1e; --lf-muted:#4a7a5e;
            --lf-primary:#2a9863; --lf-accent:#1a5c38;
            --lf-success:#2a9863; --lf-danger:#c92a2a;
        }
        body.dark-mode {
            --lf-bg:#0b1f17; --lf-card:#102f22; --lf-border:rgba(36,231,143,0.12);
            --lf-text:#d4f5e5; --lf-muted:#6aad8a;
        }
        /* ── Toolbar ── */
        .lf-bar {
            background:linear-gradient(135deg,#0f2d1e 0%,#1c4d38 55%,#2a9863 100%);
            padding:13px 22px; display:flex; align-items:center;
            justify-content:space-between; flex-wrap:wrap; gap:10px;
            position:sticky; top:57px; z-index:200;
            box-shadow:0 2px 12px rgba(15,45,30,.3);
        }
        .lf-bar-info h2 { color:#fff; font-size:.97rem; font-weight:800; margin:0 0 1px; }
        .lf-bar-info p  { color:rgba(255,255,255,.72); font-size:.76rem; margin:0; }
        .lf-status {
            display:inline-flex; align-items:center; gap:4px;
            padding:2px 11px; border-radius:20px; font-size:.72rem; font-weight:800;
            color:#fff; background:<?= $status_color ?>;
        }
        .lf-btns { display:flex; gap:7px; flex-wrap:wrap; }
        .lf-btn {
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 16px; border-radius:7px; font-weight:700; font-size:.82rem;
            cursor:pointer; text-decoration:none; border:none; transition:all .15s;
        }
        .btn-back  { background:rgba(255,255,255,.14); color:#fff; border:1px solid rgba(255,255,255,.25); }
        .btn-back:hover { background:rgba(255,255,255,.25); color:#fff; }
        .btn-print { background:linear-gradient(135deg,#2a9863,#24e78f); color:#fff; }
        .btn-print:hover { opacity:.87; }
        .btn-dl    { background:#fff; color:#1a5c38; }
        .btn-dl:hover { background:#e6f7ef; color:#1a5c38; }
        /* ── Wrap ── */
        .lf-wrap { max-width:820px; margin:22px auto; padding:0 16px 48px; }
        .lf-card {
            background:var(--lf-card); border:1px solid var(--lf-border);
            border-radius:14px; overflow:hidden;
            box-shadow:0 4px 24px rgba(42,152,99,.09);
        }
        /* ── Form header ── */
        .form-head {
            background:var(--lf-card); border-bottom:3px solid var(--lf-primary);
            padding:16px 24px 12px; position:relative; overflow:hidden;
        }
        .form-head::before {
            content:''; position:absolute; inset:0;
            background:linear-gradient(135deg,rgba(42,152,99,.04),transparent);
        }
        .form-head-top {
            display:flex; justify-content:space-between; align-items:flex-start;
            font-size:.68rem; color:var(--lf-muted); font-style:italic; margin-bottom:6px;
        }
        .form-head-center { text-align:center; position:relative; }
        .form-head-center h3 { font-size:.88rem; font-weight:700; margin:0; color:var(--lf-text); }
        .form-head-center p  { font-size:.75rem; color:var(--lf-muted); margin:1px 0; }
        .form-title { font-size:1.2rem; font-weight:900; text-transform:uppercase;
            letter-spacing:2px; color:var(--lf-primary); margin:7px 0 2px; }
        .form-docno { font-size:.65rem; font-style:italic; color:var(--lf-muted); }
        .form-section-chip {
            display:inline-block; background:var(--lf-primary); color:#fff;
            padding:3px 14px; border-radius:20px; font-size:.7rem; font-weight:700;
            letter-spacing:.4px; margin-top:6px; text-transform:uppercase;
        }
        /* ── Body ── */
        .form-body { padding:18px 22px; }
        /* ── Section blocks ── */
        .fsec { border:1px solid var(--lf-border); border-radius:10px; margin-bottom:14px; overflow:hidden; }
        .fsec-head {
            background:#e6f7ef; padding:7px 14px;
            font-size:.68rem; font-weight:800; text-transform:uppercase;
            letter-spacing:.5px; color:var(--lf-primary); border-bottom:1px solid var(--lf-border);
        }
        body.dark-mode .fsec-head { background:#0e2619; }
        .fsec-body { padding:14px 16px; }
        /* ── Field items ── */
        .pf-grid  { display:grid; grid-template-columns:1fr 1fr; gap:10px 24px; }
        .pf-grid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px 16px; }
        .pf-item  { display:flex; flex-direction:column; gap:2px; }
        .pf-lbl   { font-size:.63rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--lf-muted); }
        .pf-val   { font-size:.87rem; font-weight:600; color:var(--lf-text); padding:5px 0; border-bottom:1px solid var(--lf-border); }
        /* ── Leave type grid ── */
        .lt-grid { display:grid; grid-template-columns:1fr 1fr; gap:3px 16px; }
        .lt-item { display:flex; align-items:flex-start; gap:5px; font-size:.79rem; padding:2px 0; }
        .lt-txt  { line-height:1.35; color:var(--lf-text); }
        /* ── Action blocks ── */
        .act-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .act-block { background:#f0faf5; border:1px solid var(--lf-border); border-radius:8px; padding:12px 14px; }
        body.dark-mode .act-block { background:#0e2619; }
        .act-block h6 { font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--lf-muted); margin:0 0 8px; }
        .sig-line { border-bottom:1px solid var(--lf-text); margin:22px 10px 3px; }
        .sig-name  { font-size:.79rem; font-weight:800; text-align:center; color:var(--lf-text); }
        .sig-title { font-size:.7rem; font-style:italic; color:var(--lf-muted); text-align:center; }
        .leave-credits-tbl { width:100%; border-collapse:collapse; font-size:.73rem; margin:6px 0 10px; }
        .leave-credits-tbl td { border:1px solid var(--lf-border); padding:3px 7px; color:var(--lf-text); }
        .leave-credits-tbl .th { font-weight:700; background:#e6f7ef; text-align:center; }
        body.dark-mode .leave-credits-tbl .th { background:#102f22; }
        /* ── Reason box ── */
        .reason-box {
            background:#f0faf5; border:1px solid var(--lf-border); border-radius:7px;
            padding:9px 13px; font-size:.84rem; color:var(--lf-text);
            line-height:1.6; white-space:pre-wrap;
        }
        body.dark-mode .reason-box { background:#0e2619; }
        /* ── Warning notice ── */
        .lf-warn {
            background:#fff8e1; border:1px solid #ffd43b; border-radius:8px;
            padding:10px 14px; margin-bottom:14px; font-size:.79rem; color:#7c5800;
            display:flex; align-items:flex-start; gap:8px;
        }
        /* ── Print ── */
        @media print {
            .lf-bar, .lf-warn { display:none !important; }
            body { background:#fff; }
            .lf-wrap { max-width:100%; margin:0; padding:0; }
            .lf-card { border:none; box-shadow:none; border-radius:0; }
            .form-head { border-bottom:2px solid #000; }
            .fsec { border:1px solid #000; }
            .fsec-head { background:#eee !important; }
        }
        @media(max-width:640px){
            .pf-grid, .pf-grid3, .lt-grid, .act-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
<?php include '../includes/mainheader.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper" style="background:var(--lf-bg,#f0f4ff);">

<!-- ── Toolbar ── -->
<div class="lf-bar">
    <div class="lf-bar-info">
        <h2><i class="fas fa-file-contract mr-2"></i>Leave Form Preview</h2>
        <p>
            <?= $emp_display ?> &bull; <?= $section_display ?> &bull; <?= $lt_display ?>
            &nbsp;<span class="lf-status"><?= $status_label ?></span>
        </p>
    </div>
    <div class="lf-btns">
        <a href="<?= $back_url ?>" class="lf-btn btn-back">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button onclick="window.print()" class="lf-btn btn-print">
            <i class="fas fa-print"></i> Print
        </button>
        <?php if (!$tpl_missing): ?>
        <a href="<?= $download_url ?>" class="lf-btn btn-dl">
            <i class="fas fa-file-word"></i> Download DOCX
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="lf-wrap">

    <?php if ($tpl_missing): ?>
    <div class="lf-warn">
        <i class="fas fa-exclamation-triangle mt-1"></i>
        <span>
            <strong>Template DOCX not found.</strong> Place the four leave form templates in
            <code><?= htmlspecialchars(TEMPLATE_DIR ?: dirname(__DIR__).'/public/templates/') ?>/</code>.
            The preview below is available but Download will be enabled once the templates are in place.
        </span>
    </div>
    <?php endif; ?>

    <div class="lf-card">

        <!-- ══ Official Form Header ══ -->
        <div class="form-head">
            <div class="form-head-top">
                <span>ANNEX A</span>
                <span style="text-align:right;">Civil Service Form No. 6<br><em>Revised 2020</em></span>
            </div>
            <div class="form-head-center">
                <h3>Republic of the Philippines</h3>
                <h3>OFFICE OF THE PRESIDENT</h3>
                <h3>National Irrigation Administration</h3>
                <p>Albay-Catanduanes Irrigation Management Office &bull; Tuburan, Ligao City, Albay</p>
                <div class="form-title">Application for Leave</div>
                <div class="form-docno"><em>NIA-ACIMO-ADM-INT-Form01 Rev06</em></div>
                <div class="form-section-chip"><?= $section_display ?></div>
            </div>
        </div>

        <div class="form-body">

            <!-- ── 1–5: Employee Info ── -->
            <div class="fsec">
                <div class="fsec-head"><i class="fas fa-user mr-1"></i>Employee Information</div>
                <div class="fsec-body">
                    <div class="pf-grid3">
                        <?= pf('1. Office / Department', $section_display) ?>
                        <?= pf('2. Name (Last, First, Middle)', htmlspecialchars("$last_name, $first_name $middle_ini")) ?>
                        <?= pf('3. Date of Filing', htmlspecialchars($filed_date)) ?>
                        <?= pf('4. Position', htmlspecialchars($position)) ?>
                        <?= pf('5. Salary', '___________') ?>
                        <?= pf('Appointment Status', htmlspecialchars($d['appointment_status'] ?? '')) ?>
                    </div>
                </div>
            </div>

            <!-- ── Section 6: Application Details ── -->
            <div class="fsec">
                <div class="fsec-head"><i class="fas fa-clipboard-list mr-1"></i>6. Details of Application</div>
                <div class="fsec-body">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px;">
                        <!-- 6A: Leave type -->
                        <div>
                            <div class="pf-lbl" style="margin-bottom:7px;">6.A Type of Leave</div>
                            <div class="lt-grid">
                                <?php
                                $leaves = [
                                    'VACATION LEAVE'                    => $lt_is_main && str_contains($lt_lc,'vacation'),
                                    'MANDATORY LEAVE'                   => $lt_is_main && (str_contains($lt_lc,'mandatory')||str_contains($lt_lc,'forced')),
                                    'SICK LEAVE'                        => $lt_is_main && str_contains($lt_lc,'sick'),
                                    'MATERNITY LEAVE'                   => $lt_is_main && str_contains($lt_lc,'maternity'),
                                    'PATERNITY LEAVE'                   => $lt_is_main && str_contains($lt_lc,'paternity'),
                                    'SPECIAL PRIVILEGE LEAVE'           => $lt_is_main && str_contains($lt_lc,'special privilege'),
                                    'SOLO PARENT LEAVE'                 => $lt_is_main && str_contains($lt_lc,'solo parent'),
                                    'STUDY LEAVE'                       => $lt_is_main && str_contains($lt_lc,'study'),
                                    '10-DAY VAWC LEAVE'                 => $lt_is_main && (str_contains($lt_lc,'vawc')||str_contains($lt_lc,'10-day')),
                                    'REHABILITATION PRIVILEGE'          => $lt_is_main && str_contains($lt_lc,'rehabilitation'),
                                    'SPECIAL LEAVE BENEFITS FOR WOMEN'  => $lt_is_main && (str_contains($lt_lc,'women')||str_contains($lt_lc,'special leave b')),
                                    'SPECIAL EMERGENCY (CALAMITY) LEAVE'=> $lt_is_main && (str_contains($lt_lc,'calamity')||str_contains($lt_lc,'emergency')),
                                    'ADOPTION LEAVE'                    => $lt_is_main && str_contains($lt_lc,'adoption'),
                                    'WELLNESS LEAVE'                    => $lt_is_main && str_contains($lt_lc,'wellness'),
                                    'Monetization of Leave Credits'     => $lt_is_main && str_contains($lt_lc,'monetization'),
                                    'Terminal Leave'                    => $lt_is_main && str_contains($lt_lc,'terminal'),
                                ];
                                $has_std_match = (array_sum($leaves) > 0);
                                // OTHERS row – ticked only when nothing above matches
                                $others_label_preview = $has_std_match
                                    ? 'OTHERS: _____________________'
                                    : 'OTHERS: ' . htmlspecialchars($d['leave_type_name']);
                                $leaves[$others_label_preview] = !$has_std_match;
                                foreach ($leaves as $label => $on): ?>
                                <div class="lt-item">
                                    <?= chkbx($on) ?>
                                    <span class="lt-txt <?= $on?'fw-bold':'' ?>"><?= $label ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- 6B: Leave detail -->
                        <div>
                            <div class="pf-lbl" style="margin-bottom:7px;">6.B Details of Leave</div>
                            <div style="font-size:.72rem;color:var(--lf-muted);font-style:italic;margin-bottom:4px;">In case of Vacation/Special Privilege Leave:</div>
                            <div class="lt-item" style="font-size:.8rem;margin-bottom:2px;">
                                <?= chkbx(str_contains($lt_lc,'vacation')||str_contains($lt_lc,'special privilege')) ?>
                                <span>Within Philippines ______________________</span>
                            </div>
                            <div class="lt-item" style="font-size:.8rem;margin-bottom:10px;">
                                <?= chkbx(false) ?><span>Abroad ______________________________</span>
                            </div>
                            <div style="font-size:.72rem;color:var(--lf-muted);font-style:italic;margin-bottom:4px;">In case of Sick Leave:</div>
                            <div class="lt-item" style="font-size:.8rem;margin-bottom:2px;">
                                <?= chkbx(str_contains($lt_lc,'sick')) ?>
                                <span>In Hospital (Specify Illness):
                                    <?php if(str_contains($lt_lc,'sick') && $reason): ?>
                                    <em><?= htmlspecialchars($reason) ?></em>
                                    <?php else: ?>____________<?php endif; ?>
                                </span>
                            </div>
                            <div class="lt-item" style="font-size:.8rem;margin-bottom:10px;">
                                <?= chkbx(false) ?><span>Out of Hospital: ____________</span>
                            </div>
                            <div style="font-size:.72rem;color:var(--lf-muted);font-style:italic;margin-bottom:4px;">In case of Study Leave:</div>
                            <div class="lt-item" style="font-size:.8rem;margin-bottom:2px;"><?= chkbx(false) ?><span>Completion of Master&#x2019;s Degree</span></div>
                            <div class="lt-item" style="font-size:.8rem;margin-bottom:10px;"><?= chkbx(false) ?><span>Bar/Board Examination Review</span></div>
                            <div style="font-size:.72rem;color:var(--lf-muted);font-style:italic;margin-bottom:4px;">Other Purpose:</div>
                            <div class="lt-item" style="font-size:.8rem;margin-bottom:2px;"><?= chkbx(str_contains($lt_lc,'monetization')) ?><span>Monetization of Leave Credits</span></div>
                            <div class="lt-item" style="font-size:.8rem;"><?= chkbx(str_contains($lt_lc,'terminal')) ?><span>Terminal Leave</span></div>
                        </div>
                    </div>

                    <!-- 6C & 6D -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px;">
                        <div>
                            <div class="pf-lbl" style="margin-bottom:5px;">6.C Number of Working Days Applied For</div>
                            <div style="font-size:1.4rem;font-weight:900;color:var(--lf-primary);"><?= $num_days ?> day<?= $num_days != 1 ? 's' : '' ?></div>
                            <div class="pf-lbl" style="margin-top:8px;margin-bottom:4px;">Inclusive Dates</div>
                            <div style="font-size:.85rem;font-weight:600;color:var(--lf-text);"><?= htmlspecialchars($inclusive) ?></div>
                        </div>
                        <div>
                            <div class="pf-lbl" style="margin-bottom:6px;">6.D Commutation</div>
                            <div class="lt-item" style="font-size:.82rem;margin-bottom:4px;"><?= chkbx(false) ?><span>Not Requested</span></div>
                            <div class="lt-item" style="font-size:.82rem;"><?= chkbx(false) ?><span>Requested</span></div>
                        </div>
                    </div>

                    <!-- Reason -->
                    <div>
                        <div class="pf-lbl" style="margin-bottom:6px;">Reason / Details of Leave</div>
                        <div class="reason-box"><?= htmlspecialchars($reason) ?></div>
                    </div>
                </div>
            </div>

            <!-- ── Section 7: Action ── -->
            <div class="fsec">
                <div class="fsec-head"><i class="fas fa-gavel mr-1"></i>7. Details of Action on Application</div>
                <div class="fsec-body">
                    <div class="act-grid">

                        <!-- 7A: Certification -->
                        <div class="act-block">
                            <h6>7.A Certification of Leave Credits</h6>
                            <div style="font-size:.75rem;color:var(--lf-muted);margin-bottom:8px;">
                                As of <?= $approved_date ? htmlspecialchars($approved_date) : '________________________' ?>
                            </div>
                            <table class="leave-credits-tbl">
                                <tr>
                                    <td>&nbsp;</td>
                                    <td class="th">Vacation Leave</td>
                                    <td class="th">Sick Leave</td>
                                </tr>
                                <tr>
                                    <td style="font-style:italic;">Total Earned</td>
                                    <td style="text-align:center;font-weight:600;"><?= fmt_days($vl_bal['total_credits']) ?></td>
                                    <td style="text-align:center;font-weight:600;"><?= fmt_days($sl_bal['total_credits']) ?></td>
                                </tr>
                                <tr>
                                    <td style="font-style:italic;">Less This Application</td>
                                    <td style="font-weight:700;text-align:center;">
                                        <?= $is_vl_request ? fmt_days($num_days) : '&nbsp;' ?>
                                    </td>
                                    <td style="font-weight:700;text-align:center;">
                                        <?= $is_sl_request ? fmt_days($num_days) : '&nbsp;' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-style:italic;">Balance</td>
                                    <td style="text-align:center;font-weight:700;color:#1a7a3f;">
                                        <?= fmt_days($vl_bal['remaining_days']) ?>
                                    </td>
                                    <td style="text-align:center;font-weight:700;color:#1a7a3f;">
                                        <?= fmt_days($sl_bal['remaining_days']) ?>
                                    </td>
                                </tr>
                            </table>
                            <div class="sig-line"></div>
                            <div class="sig-name">MYRA M. ETCOBANEZ</div>
                            <div class="sig-title"><em>Administrative Services Officer B</em></div>
                        </div>

                        <!-- 7B: Recommendation -->
                        <div class="act-block">
                            <h6>7.B Recommendation</h6>
                            <div class="lt-item" style="font-size:.82rem;margin-bottom:6px;">
                                <?= chkbx($is_approved) ?>
                                <span <?= $is_approved?'style="font-weight:700;"':'' ?>>For approval</span>
                            </div>
                            <div class="lt-item" style="font-size:.82rem;margin-bottom:10px;">
                                <?= chkbx($is_rejected) ?>
                                <span <?= $is_rejected?'style="font-weight:700;"':'' ?>>
                                    For disapproval<?= ($is_rejected && $hr_remarks) ? ': <em>' . htmlspecialchars($hr_remarks) . '</em>' : ' due to _______________' ?>
                                </span>
                            </div>
                            <div class="sig-line"></div>
                            <div class="sig-name"><?= htmlspecialchars($section_rec['name']) ?></div>
                            <div class="sig-title"><em><?= htmlspecialchars($section_rec['title']) ?></em></div>
                        </div>

                        <!-- 7C: Approved -->
                        <div class="act-block">
                            <h6>7.C Approved For</h6>
                            <div style="font-size:.82rem;margin-bottom:10px;">
                                <strong><?= $is_approved ? $num_days : '___' ?></strong> days with pay &nbsp;&nbsp;
                                <strong>___</strong> days without pay &nbsp;&nbsp;
                                <strong>___</strong> others
                            </div>
                            <div class="sig-line"></div>
                            <div class="sig-name">ENGR. MARK CLOYD G. SO</div>
                            <div class="sig-title"><em>Acting Division Manager</em></div>
                        </div>

                        <!-- 7D: Disapproved -->
                        <div class="act-block">
                            <h6>7.D Disapproved Due To</h6>
                            <?php if ($is_rejected && $hr_remarks): ?>
                                <div class="reason-box" style="font-size:.8rem;"><?= htmlspecialchars($hr_remarks) ?></div>
                            <?php else: ?>
                                <div style="color:#cbd5e1;font-size:.82rem;line-height:2.2;">
                                    ___________________________<br>
                                    ___________________________<br>
                                    ___________________________
                                </div>
                            <?php endif; ?>
                        </div>

                    </div><!-- .act-grid -->

                    <?php if ($d['approved_by_name']): ?>
                    <div style="margin-top:12px;" class="pf-grid">
                        <?= pf('Processed By', htmlspecialchars($d['approved_by_name'])) ?>
                        <?= pf('Processed On', $approved_date) ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div><!-- .form-body -->
    </div><!-- .lf-card -->
</div><!-- .lf-wrap -->

</div><!-- .content-wrapper -->
<?php include '../includes/mainfooter.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>