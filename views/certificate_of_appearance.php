<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$database = new Database();
$db = $database->getConnection();

$module_name = 'Certificate of Appearance';
$check_stmt = $db->prepare("SELECT is_under_maintenance FROM system_modules WHERE module_name = ?");
$check_stmt->bind_param("s", $module_name);
$check_stmt->execute();
$result = $check_stmt->get_result();
if ($result->num_rows > 0) {
  $module = $result->fetch_assoc();
  if ($module['is_under_maintenance'] && !hasPermission('manage_settings')) {
    $_SESSION['error'] = "The $module_name module is currently under maintenance. Please try again later.";
    header("Location: ../unauthorized.php");
    exit();
  }
}

if (!hasPermission('manage_employees')) {
  $_SESSION['error'] = "You do not have permission to access this module.";
  header("Location: ../unauthorized.php");
  exit();
}

$positions = [];
$pr = $db->query("SELECT position_name FROM position ORDER BY position_name ASC");
while ($row = $pr->fetch_assoc()) { $positions[] = $row['position_name']; }

/* ───────────────────── template registry ─────────────────────
   Add a new template here + drop the .docx in /public/templates.
   type 'sym'       = Wingdings checkbox symbols (detailed meals)
   type 'numbering' = bullet-list checkboxes (Lodging + Meal incl. breakfast/snacks/lunch/dinner) */
$CA_TEMPLATES = [
  'standard' => [
    'label'     => 'Standard CA Format',
    'file'      => 'CA_TEMPLATE_READY.docx',
    'type'      => 'sym',
    'detailed'  => true,
    'signatory' => ['MYRA M. ETCOBANEZ', 'Acting Administrative Unit Head'],
  ],
  'imo_manager' => [
    'label'     => 'Central Office (CA Format)',
    'file'      => 'CA_TEMPLATE_CO_READY.docx',
    'type'      => 'numbering',
    'detailed'  => true,
    'signatory' => ['MYRA M. ETCOBANEZ', 'Acting Administrative Unit Head'],
  ],
];
const CA_DEFAULT_TEMPLATE = 'standard';

/* ───────────────────────── helpers ───────────────────────── */
function ca_bool($key) { return isset($_POST[$key]) && $_POST[$key] ? 1 : 0; }

function ca_collect() {
  global $CA_TEMPLATES;
  $tpl = $_POST['template_key'] ?? CA_DEFAULT_TEMPLATE;
  if (!isset($CA_TEMPLATES[$tpl])) $tpl = CA_DEFAULT_TEMPLATE;
  $lodging = ca_bool('lodging_provided');
  $meal    = ca_bool('meal_provided');
  return [
    'person_name'     => strtoupper(trim($_POST['person_name'] ?? '')),
    'designation'     => trim($_POST['designation'] ?? ''),
    'office_from'     => trim($_POST['office_from'] ?? ''),
    'date_arrival'    => $_POST['date_arrival'] ?? '',
    'date_departure'  => $_POST['date_departure'] ?? '',
    'purpose'         => trim($_POST['purpose'] ?? ''),
    'lodging_provided'=> $lodging,
    'meal_provided'   => $meal,
    // meals only count when "Provided" is ticked
    'breakfast'       => $meal ? ca_bool('breakfast') : 0,
    'am_snack'        => $meal ? ca_bool('am_snack')  : 0,
    'lunch'           => $meal ? ca_bool('lunch')     : 0,
    'pm_snack'        => $meal ? ca_bool('pm_snack')  : 0,
    'dinner'          => $meal ? ca_bool('dinner')    : 0,
    'signatory_name'  => trim($_POST['signatory_name'] ?? '') ?: $CA_TEMPLATES[$tpl]['signatory'][0],
    'signatory_title' => trim($_POST['signatory_title'] ?? '') ?: $CA_TEMPLATES[$tpl]['signatory'][1],
    'template_key'    => $tpl,
  ];
}

function ca_validate($d) {
  foreach (['person_name','designation','office_from','date_arrival','date_departure','purpose'] as $f) {
    if ($d[$f] === '') return 'Please fill in all required fields.';
  }
  if (strtotime($d['date_departure']) < strtotime($d['date_arrival'])) {
    return 'Date of departure cannot be earlier than the date of arrival.';
  }
  return null;
}

function ca_xml($s) { return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

/* Next ACIMO tracking number: ACIMO-CA-<month number><count of CAs issued that month>, e.g. ACIMO-CA-101
   = October, 1st certificate. Uses the database clock so it matches created_at. If a number is already
   taken in the same year (e.g. Jan #11 vs Nov #1 both give "111"), the count moves up to the next free one. */
function ca_next_tracking_no($db) {
  $r = $db->query("SELECT MONTH(NOW()) AS m, YEAR(NOW()) AS y,
      (SELECT COUNT(*) FROM certificate_of_appearance
        WHERE YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())) AS c")->fetch_assoc();
  $m = (int)$r['m']; $y = (int)$r['y']; $n = (int)$r['c'] + 1;
  $chk = $db->prepare("SELECT 1 FROM certificate_of_appearance WHERE tracking_no = ? AND YEAR(created_at) = ? LIMIT 1");
  while (true) {
    $tn = 'ACIMO-CA-' . $m . $n;
    $chk->bind_param('si', $tn, $y);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) break;
    $n++;
  }
  $chk->close();
  return $tn;
}

/* Resolves which template (key + definition) a certificate should be built with. */
function ca_resolve_template($row, $tpl_key = null) {
  global $CA_TEMPLATES;
  if (!$tpl_key || !isset($CA_TEMPLATES[$tpl_key])) {
    $tpl_key = isset($CA_TEMPLATES[$row['template_key'] ?? '']) ? $row['template_key'] : CA_DEFAULT_TEMPLATE;
  }
  $tpl = $CA_TEMPLATES[$tpl_key];
  $template = __DIR__ . '/../public/templates/' . $tpl['file'];
  if (!file_exists($template)) { http_response_code(500); exit('Template file not found: ' . htmlspecialchars($tpl['file'])); }
  if (!class_exists('ZipArchive')) { http_response_code(500); exit('PHP ZipArchive extension is not enabled.'); }
  return [$tpl_key, $tpl, $template];
}

/* Fills the {{placeholders}} and ticks the checkboxes of ONE certificate inside a chunk of document.xml. */
function ca_fill_xml($xml, $row, $tpl) {
  global $CA_TEMPLATES;

  // If the signatory is still one of the built-in defaults, use the chosen template's signatory
  $sig_name  = $row['signatory_name'];
  $sig_title = $row['signatory_title'];
  foreach ($CA_TEMPLATES as $t) {
    if ($sig_name === $t['signatory'][0] && $sig_title === $t['signatory'][1]) {
      $sig_name = $tpl['signatory'][0]; $sig_title = $tpl['signatory'][1]; break;
    }
  }

  $map = [
    '{{NAME}}'            => $row['person_name'],
    '{{DESIGNATION}}'     => $row['designation'],
    '{{OFFICE}}'          => $row['office_from'],
    '{{ARRIVAL}}'         => date('F j, Y', strtotime($row['date_arrival'])),
    '{{DEPARTURE}}'       => date('F j, Y', strtotime($row['date_departure'])),
    '{{PURPOSE}}'         => $row['purpose'],
    '{{SIGNATORY}}'       => $sig_name,
    '{{SIGNATORY_TITLE}}' => $sig_title,
  ];
  foreach ($map as $k => $v) { $xml = str_replace($k, ca_xml($v), $xml); }

  if ($tpl['type'] === 'sym') {
    // Wingdings checkboxes appear in this order in the template (F0A1..F0A9)
    $checked = [
      1 => $row['lodging_provided'] == 1,                         // Lodging: Provided
      2 => $row['meal_provided'] == 1,                            // Meal: Provided
      3 => $row['lodging_provided'] == 0,                         // Lodging: Not Provided
      4 => $row['meal_provided'] == 1 && $row['breakfast'] == 1,  // Breakfast
      5 => $row['meal_provided'] == 1 && $row['lunch'] == 1,      // Lunch
      6 => $row['meal_provided'] == 1 && $row['dinner'] == 1,     // Dinner
      7 => $row['meal_provided'] == 0,                            // Meal: Not Provided
      8 => $row['meal_provided'] == 1 && $row['am_snack'] == 1,   // AM Snack
      9 => $row['meal_provided'] == 1 && $row['pm_snack'] == 1,   // PM Snack
    ];
    $xml = preg_replace_callback(
      '/<w:sym w:font="Wingdings" w:char="F0([A-F0-9]{2})"\/>/',
      function ($m) use ($checked) {
        $i = hexdec($m[1]) - 0xA0;
        $on = !empty($checked[$i]);
        return '<w:sym w:font="Wingdings" w:char="' . ($on ? 'F0FE' : 'F0A8') . '"/>';
      },
      $xml
    );
  } else {
    // Bullet-list checkboxes: numId 5 = empty box, numId 6 = ticked box.
    // Order in the document: Lodging-Provided, Lodging-Not, Meal-Provided,
    // Breakfast, AM Snack, Lunch, PM Snack, Dinner, Meal-Not
    $flags = [
      $row['lodging_provided'] == 1,
      $row['lodging_provided'] == 0,
      $row['meal_provided'] == 1,
      $row['meal_provided'] == 1 && $row['breakfast'] == 1,
      $row['meal_provided'] == 1 && $row['am_snack'] == 1,
      $row['meal_provided'] == 1 && $row['lunch'] == 1,
      $row['meal_provided'] == 1 && $row['pm_snack'] == 1,
      $row['meal_provided'] == 1 && $row['dinner'] == 1,
      $row['meal_provided'] == 0,
    ];
    $n = 0;
    $xml = preg_replace_callback(
      '/<w:numId w:val="[56]"\/>/',
      function ($m) use ($flags, &$n) {
        $on = !empty($flags[$n]);
        $n++;
        return '<w:numId w:val="' . ($on ? 6 : 5) . '"/>';
      },
      $xml
    );
  }
  return $xml;
}

/* Fills the chosen template (a .docx with {{placeholders}}) into a temp file. Returns [temp path, download filename]. */
function ca_build_docx($row, $tpl_key = null) {
  [$tpl_key, $tpl, $template] = ca_resolve_template($row, $tpl_key);

  $tmp = tempnam(sys_get_temp_dir(), 'ca_');
  copy($template, $tmp);
  $zip = new ZipArchive();
  if ($zip->open($tmp) !== true) { http_response_code(500); exit('Unable to open template.'); }
  $xml = ca_fill_xml($zip->getFromName('word/document.xml'), $row, $tpl);

  $zip->addFromString('word/document.xml', $xml);
  $zip->close();

  $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $row['person_name']);
  $filename = 'Certificate_of_Appearance_' . $safe . '_' . date('Ymd', strtotime($row['date_arrival'])) . '.docx';
  return [$tmp, $filename];
}

/* Ends a certificate's pages with a "next page" section break that repeats the template's own page
   setup (header/footer/first-page rules), so every certificate looks exactly like a single printout. */
function ca_end_section($part, $sect) {
  $pe = strrpos($part, '</w:p>');
  if ($pe === false || trim(substr($part, $pe + 6)) !== '') {
    return $part . '<w:p><w:pPr>' . $sect . '</w:pPr></w:p>';   // body ends in a table etc.
  }
  $before = substr($part, 0, $pe);
  $ps = max((int)strrpos($before, '<w:p '), (int)strrpos($before, '<w:p>'));
  $open_end = strpos($part, '>', $ps) + 1;                       // end of the <w:p ...> tag
  if (substr($part, $open_end, 8) === '<w:pPr/>') {
    return substr($part, 0, $open_end) . '<w:pPr>' . $sect . '</w:pPr>' . substr($part, $open_end + 8);
  }
  if (substr($part, $open_end, 7) === '<w:pPr>') {
    $close = strpos($part, '</w:pPr>', $open_end);
    return substr($part, 0, $close) . $sect . substr($part, $close);
  }
  return substr($part, 0, $open_end) . '<w:pPr>' . $sect . '</w:pPr>' . substr($part, $open_end);
}

/* Builds ONE .docx holding every certificate (one per page) from the same template.
   Returns [temp path, download filename]. */
function ca_build_merged_docx($rows, $tpl_key = null) {
  [$tpl_key, $tpl, $template] = ca_resolve_template($rows[0], $tpl_key);

  $tmp = tempnam(sys_get_temp_dir(), 'ca_');
  copy($template, $tmp);
  $zip = new ZipArchive();
  if ($zip->open($tmp) !== true) { http_response_code(500); exit('Unable to open template.'); }
  $xml = $zip->getFromName('word/document.xml');

  $bs = strpos($xml, '<w:body>');
  $ss = strrpos($xml, '<w:sectPr');
  $se = $ss === false ? false : strpos($xml, '</w:sectPr>', $ss);
  if ($bs === false || $ss === false || $se === false) { http_response_code(500); exit('Unexpected template layout.'); }
  $bs += strlen('<w:body>');
  $se += strlen('</w:sectPr>');

  $head = substr($xml, 0, $bs);              // ... up to and including <w:body>
  $body = substr($xml, $bs, $ss - $bs);      // the certificate itself
  $sect = substr($xml, $ss, $se - $ss);      // page setup of the template
  $tail = substr($xml, $se);                 // </w:body></w:document>

  $out = ''; $img_id = 1000; $last = count($rows) - 1;
  foreach ($rows as $i => $row) {
    $part = ca_fill_xml($body, $row, $tpl);
    // keep ids unique across the repeated copies
    $part = preg_replace('/ (?:w14:(?:paraId|textId)|wp14:(?:anchorId|editId))="[^"]*"/', '', $part);
    $part = preg_replace_callback('/<wp:docPr id="\d+"/', function () use (&$img_id) {
      return '<wp:docPr id="' . (++$img_id) . '"';
    }, $part);
    $out .= ($i < $last) ? ca_end_section($part, $sect) : $part;
  }

  $zip->addFromString('word/document.xml', $head . $out . $sect . $tail);
  $zip->close();

  $filename = 'Certificates_of_Appearance_' . date('Ymd', strtotime($rows[0]['date_arrival'])) . '_' . count($rows) . 'pax.docx';
  return [$tmp, $filename];
}

/* Streams a single filled .docx to the browser. */
function ca_download_docx($row, $tpl_key = null) {
  [$tmp, $filename] = ca_build_docx($row, $tpl_key);
  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . filesize($tmp));
  header('Cache-Control: no-cache, must-revalidate');
  readfile($tmp);
  unlink($tmp);
  exit();
}

/* Streams ONE combined .docx (a certificate per page) -- used by bulk generate. */
function ca_download_merged($rows, $tpl_key = null) {
  [$tmp, $filename] = ca_build_merged_docx($rows, $tpl_key);
  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . filesize($tmp));
  header('Cache-Control: no-cache, must-revalidate');
  readfile($tmp);
  unlink($tmp);
  exit();
}

/* ───────────────────── download (GET) ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download') {
  $id = (int)($_GET['id'] ?? 0);
  $stmt = $db->prepare("SELECT * FROM certificate_of_appearance WHERE ca_id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) { http_response_code(404); exit('Certificate not found.'); }
  ca_download_docx($row, $_GET['template'] ?? null);
}

/* ───────────────────── report (GET): print-ready yearly report ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'report') {
  $year  = (int)($_GET['year'] ?? date('Y'));
  if ($year < 2000 || $year > 2100) $year = (int)date('Y');
  $month = (int)($_GET['month'] ?? 0);
  $by_month = $month >= 1 && $month <= 12;

  $sql = "SELECT * FROM certificate_of_appearance WHERE YEAR(date_arrival) = ?"
       . ($by_month ? " AND MONTH(date_arrival) = ?" : "")
       . " ORDER BY created_at ASC, ca_id ASC";
  $stmt = $db->prepare($sql);
  if ($by_month) { $stmt->bind_param('ii', $year, $month); } else { $stmt->bind_param('i', $year); }
  $stmt->execute();
  $rpt = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  $period = $by_month ? strtoupper(date('F', mktime(0, 0, 0, $month, 1))) . ' ' . $year : 'CY ' . $year;
  $mark = function ($v) { return $v ? '&#10003;' : ''; };
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate of Appearance Report - <?= htmlspecialchars($period) ?></title>
<style id="pageStyle">@page { size: 13in 8.5in; margin: 10mm; }</style>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000; margin: 0; background: #e9ecef; }
  .toolbar { position: sticky; top: 0; background: #1f7a4d; color: #fff; padding: 8px 16px; display: flex; gap: 10px; align-items: center; z-index: 5; }
  .toolbar button, .toolbar select { font-size: 13px; padding: 6px 12px; border-radius: 6px; border: 0; }
  .toolbar button { background: #fff; color: #1f7a4d; font-weight: 700; cursor: pointer; }
  .toolbar .spacer { flex: 1; }
  .sheet { background: #fff; margin: 14px auto; padding: 10mm; width: 13in; max-width: 100%; box-shadow: 0 2px 10px rgba(0,0,0,.2); }
  .head { text-align: center; line-height: 1.3; margin-bottom: 8px; }
  .head .small { font-size: 9pt; }
  .head .agency { font-size: 12pt; font-weight: 700; }
  .head .title { font-size: 12pt; font-weight: 700; margin-top: 6px; text-decoration: underline; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; overflow-wrap: anywhere; word-break: break-word; }
  th { background: #eee; text-align: center; font-size: 8pt; text-transform: uppercase; }
  th.mk { font-size: 6.8pt; padding: 3px 1px; overflow-wrap: normal; word-break: normal; }
  td.c { text-align: center; }
  td.nw { white-space: nowrap; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  .foot { margin-top: 8px; font-size: 8.5pt; display: flex; justify-content: space-between; }
  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .sheet { margin: 0; padding: 0; width: auto; box-shadow: none; }
    th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <button type="button" onclick="window.print()">&#128424; Print</button>
  <label>Paper:
    <select id="paper">
      <option value="13in 8.5in">Long bond (8.5 x 13) landscape</option>
      <option value="297mm 210mm">A4 landscape</option>
      <option value="11in 8.5in">Letter landscape</option>
    </select>
  </label>
  <span class="spacer"></span>
  <span><?= count($rpt) ?> certificate<?= count($rpt) === 1 ? '' : 's' ?> &mdash; <?= htmlspecialchars($period) ?></span>
</div>

<div class="sheet">
  <div class="head">
    <div class="small">Republic of the Philippines</div>
    <div class="small">OFFICE OF THE PRESIDENT</div>
    <div class="agency">NATIONAL IRRIGATION ADMINISTRATION</div>
    <div class="small">ALBAY-CATANDUANES IRRIGATION MANAGEMENT OFFICE</div>
    <div class="title">REPORT ON CERTIFICATES OF APPEARANCE () <?= htmlspecialchars($period) ?></div>
  </div>

  <table>
    <colgroup>
      <col style="width:8.5%"><col style="width:12%"><col style="width:10%"><col style="width:9.5%">
      <col style="width:7%"><col style="width:7%"><col>
      <col style="width:4.8%"><col style="width:4.8%"><col style="width:4.8%"><col style="width:4.8%"><col style="width:4.8%"><col style="width:4.8%">
    </colgroup>
    <thead>
      <tr>
        <th rowspan="2" style="width:8.5%">Tracking No.</th>
        <th rowspan="2" style="width:12%">Name</th>
        <th rowspan="2" style="width:10%">Designation</th>
        <th rowspan="2" style="width:9.5%">Agency / Office</th>
        <th rowspan="2" style="width:7%">Arrival</th>
        <th rowspan="2" style="width:7%">Departure</th>
        <th rowspan="2">Purpose</th>
        <th colspan="6">Lodging and Meals</th>
      </tr>
      <tr>
        <th class="mk" style="width:4.8%">Lodging</th>
        <th class="mk" style="width:4.8%">Breakfast</th>
        <th class="mk" style="width:4.8%">AM Snack</th>
        <th class="mk" style="width:4.8%">Lunch</th>
        <th class="mk" style="width:4.8%">PM Snack</th>
        <th class="mk" style="width:4.8%">Dinner</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rpt): ?>
        <tr><td colspan="13" class="c" style="padding:14px;">No certificates of appearance found for <?= htmlspecialchars($period) ?>.</td></tr>
      <?php endif; ?>
      <?php foreach ($rpt as $c): $m = (int)$c['meal_provided']; ?>
      <tr>
        <td class="nw"><strong><?= htmlspecialchars($c['tracking_no'] ?? '') ?></strong></td>
        <td><?= htmlspecialchars($c['person_name']) ?></td>
        <td><?= htmlspecialchars($c['designation']) ?></td>
        <td><?= htmlspecialchars($c['office_from']) ?></td>
        <td class="nw"><?= date('M d, Y', strtotime($c['date_arrival'])) ?></td>
        <td class="nw"><?= date('M d, Y', strtotime($c['date_departure'])) ?></td>
        <td><?= htmlspecialchars($c['purpose']) ?></td>
        <td class="c"><?= $mark($c['lodging_provided']) ?></td>
        <td class="c"><?= $mark($m && $c['breakfast']) ?></td>
        <td class="c"><?= $mark($m && $c['am_snack']) ?></td>
        <td class="c"><?= $mark($m && $c['lunch']) ?></td>
        <td class="c"><?= $mark($m && $c['pm_snack']) ?></td>
        <td class="c"><?= $mark($m && $c['dinner']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="foot">
    <span>&#10003; = Provided &nbsp;|&nbsp; Blank = Not provided</span>
    <span>Total: <strong><?= count($rpt) ?></strong> &nbsp;|&nbsp; Printed: <?= date('F d, Y') ?></span>
  </div>
</div>
<script>
  document.getElementById('paper').addEventListener('change', function () {
    document.getElementById('pageStyle').textContent = '@page { size: ' + this.value + '; margin: 10mm; }';
    document.querySelector('.sheet').style.width = this.value.split(' ')[0];
  });
</script>
</body>
</html>
<?php
  exit();
}

/* ───────────────────── bulk download (GET): all selected certificates in ONE .docx ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download_bulk') {
  $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))))));
  $ids = array_slice($ids, 0, 100);
  if (!$ids) { http_response_code(400); exit('No certificates selected.'); }
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $db->prepare("SELECT * FROM certificate_of_appearance WHERE ca_id IN ($ph) ORDER BY ca_id ASC");
  $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  if (!$rows) { http_response_code(404); exit('Certificates not found.'); }
  ca_download_merged($rows, $_GET['template'] ?? null);
}

/* ───────────────────── AJAX (POST) ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');

  try {
    if ($_POST['action'] === 'create' || $_POST['action'] === 'update') {
      $d = ca_collect();
      if ($err = ca_validate($d)) {
        echo json_encode(['success' => false, 'message' => $err]);
        exit();
      }

      if ($_POST['action'] === 'create') {
        $created_by  = (int)$_SESSION['user_id'];
        $tracking_no = ca_next_tracking_no($db);
        $stmt = $db->prepare("INSERT INTO certificate_of_appearance
          (tracking_no, person_name, designation, office_from, date_arrival, date_departure, purpose,
           lodging_provided, meal_provided, breakfast, am_snack, lunch, pm_snack, dinner, signatory_name, signatory_title, template_key, created_by)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssssiiiiiiisssi",
          $tracking_no, $d['person_name'], $d['designation'], $d['office_from'], $d['date_arrival'], $d['date_departure'], $d['purpose'],
          $d['lodging_provided'], $d['meal_provided'], $d['breakfast'], $d['am_snack'], $d['lunch'], $d['pm_snack'], $d['dinner'],
          $d['signatory_name'], $d['signatory_title'], $d['template_key'], $created_by);
        if ($stmt->execute()) {
          echo json_encode(['success' => true, 'message' => 'Certificate of Appearance saved! Tracking No.: ' . $tracking_no, 'id' => $db->insert_id, 'tracking_no' => $tracking_no]);
        } else {
          echo json_encode(['success' => false, 'message' => 'Failed to save certificate.']);
        }
      } else {
        $id = (int)($_POST['ca_id'] ?? 0);
        $stmt = $db->prepare("UPDATE certificate_of_appearance SET
          person_name=?, designation=?, office_from=?, date_arrival=?, date_departure=?, purpose=?,
          lodging_provided=?, meal_provided=?, breakfast=?, am_snack=?, lunch=?, pm_snack=?, dinner=?, signatory_name=?, signatory_title=?, template_key=?
          WHERE ca_id=?");
        $stmt->bind_param("ssssssiiiiiiisssi",
          $d['person_name'], $d['designation'], $d['office_from'], $d['date_arrival'], $d['date_departure'], $d['purpose'],
          $d['lodging_provided'], $d['meal_provided'], $d['breakfast'], $d['am_snack'], $d['lunch'], $d['pm_snack'], $d['dinner'],
          $d['signatory_name'], $d['signatory_title'], $d['template_key'], $id);
        if ($stmt->execute()) {
          echo json_encode(['success' => true, 'message' => 'Certificate updated successfully!']);
        } else {
          echo json_encode(['success' => false, 'message' => 'Failed to update certificate.']);
        }
      }
      exit();
    }

    if ($_POST['action'] === 'bulk_create') {
      // Shared details (template, office, dates, purpose, lodging/meals, signatory) apply to every person.
      $d = ca_collect();
      foreach (['office_from','date_arrival','date_departure','purpose'] as $f) {
        if ($d[$f] === '') {
          echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
          exit();
        }
      }
      if (strtotime($d['date_departure']) < strtotime($d['date_arrival'])) {
        echo json_encode(['success' => false, 'message' => 'Date of departure cannot be earlier than the date of arrival.']);
        exit();
      }

      $names  = (array)($_POST['bulk_name'] ?? []);
      $desigs = (array)($_POST['bulk_designation'] ?? []);
      $people = [];
      foreach ($names as $i => $n) {
        $n = strtoupper(trim((string)$n));
        $g = trim((string)($desigs[$i] ?? ''));
        if ($n === '' && $g === '') continue;               // untouched blank row
        if ($n === '' || $g === '') {
          echo json_encode(['success' => false, 'message' => 'Row ' . ((int)$i + 1) . ': please enter both the name and the designation.']);
          exit();
        }
        $people[] = [$n, $g];
      }
      if (!$people) {
        echo json_encode(['success' => false, 'message' => 'Please add at least one person.']);
        exit();
      }
      if (count($people) > 100) {
        echo json_encode(['success' => false, 'message' => 'Maximum of 100 certificates per batch.']);
        exit();
      }

      $created_by = (int)$_SESSION['user_id'];
      $tracking_no = '';
      $stmt = $db->prepare("INSERT INTO certificate_of_appearance
        (tracking_no, person_name, designation, office_from, date_arrival, date_departure, purpose,
         lodging_provided, meal_provided, breakfast, am_snack, lunch, pm_snack, dinner, signatory_name, signatory_title, template_key, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $stmt->bind_param("sssssssiiiiiiisssi",
        $tracking_no, $d['person_name'], $d['designation'], $d['office_from'], $d['date_arrival'], $d['date_departure'], $d['purpose'],
        $d['lodging_provided'], $d['meal_provided'], $d['breakfast'], $d['am_snack'], $d['lunch'], $d['pm_snack'], $d['dinner'],
        $d['signatory_name'], $d['signatory_title'], $d['template_key'], $created_by);

      $ids = [];
      $db->begin_transaction();
      try {
        foreach ($people as $pp) {
          $d['person_name'] = $pp[0];
          $d['designation'] = $pp[1];
          $tracking_no = ca_next_tracking_no($db);   // each person in the batch gets the next number
          if (!$stmt->execute()) throw new Exception('Failed to save the certificate for ' . $pp[0] . '.');
          $ids[] = (int)$db->insert_id;
        }
        $db->commit();
      } catch (Exception $e) {
        $db->rollback();
        throw $e;
      }
      echo json_encode([
        'success'  => true,
        'message'  => count($ids) . ' Certificate' . (count($ids) === 1 ? '' : 's') . ' of Appearance saved!',
        'ids'      => $ids,
        'template' => $d['template_key'],
      ]);
      exit();
    }

    if ($_POST['action'] === 'bulk_delete') {
      $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
      if (!$ids) {
        echo json_encode(['success' => false, 'message' => 'No certificates selected.']);
        exit();
      }
      $ph = implode(',', array_fill(0, count($ids), '?'));
      $stmt = $db->prepare("DELETE FROM certificate_of_appearance WHERE ca_id IN ($ph)");
      $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
      if ($stmt->execute()) {
        $n = $stmt->affected_rows;
        echo json_encode(['success' => true, 'count' => $n, 'message' => $n . ' certificate' . ($n === 1 ? '' : 's') . ' deleted successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete the selected certificates.']);
      }
      exit();
    }

    if ($_POST['action'] === 'delete_all') {
      if (($_POST['confirm'] ?? '') !== 'DELETE') {
        echo json_encode(['success' => false, 'message' => 'Confirmation text did not match.']);
        exit();
      }
      if ($db->query("DELETE FROM certificate_of_appearance")) {
        $n = $db->affected_rows;
        echo json_encode(['success' => true, 'count' => $n, 'message' => 'All certificates (' . $n . ') deleted successfully!']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete certificates.']);
      }
      exit();
    }

    if ($_POST['action'] === 'delete') {
      $id = (int)($_POST['ca_id'] ?? 0);
      $stmt = $db->prepare("DELETE FROM certificate_of_appearance WHERE ca_id = ?");
      $stmt->bind_param("i", $id);
      echo json_encode($stmt->execute()
        ? ['success' => true,  'message' => 'Certificate deleted successfully!']
        : ['success' => false, 'message' => 'Failed to delete certificate.']);
      exit();
    }

    if ($_POST['action'] === 'get') {
      $id = (int)($_POST['ca_id'] ?? 0);
      $stmt = $db->prepare("SELECT * FROM certificate_of_appearance WHERE ca_id = ?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $res = $stmt->get_result();
      echo json_encode($res->num_rows > 0
        ? ['success' => true, 'data' => $res->fetch_assoc()]
        : ['success' => false, 'message' => 'Certificate not found.']);
      exit();
    }
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
  }
}

// ── List ──
$certs = [];
$r = $db->query("SELECT * FROM certificate_of_appearance ORDER BY date_arrival DESC, ca_id DESC");
while ($row = $r->fetch_assoc()) { $certs[] = $row; }

/* Shared form fields (used by Add + Edit modals). $p = id prefix */
function ca_form_fields($p, $positions, $bulk = false) { global $CA_TEMPLATES; ob_start(); ?>
  <div class="form-group">
    <label>Certificate Template <span class="text-danger">*</span></label>
    <select name="template_key" id="<?= $p ?>template_key" class="form-control ca-template" required>
      <?php foreach ($CA_TEMPLATES as $key => $t): ?>
        <option value="<?= $key ?>"><?= htmlspecialchars($t['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <small class="form-text ca-template-note"></small>
  </div>
<?php if (!$bulk): ?>
  <div class="form-group">
    <label>Complete Name of Person <span class="text-danger">*</span></label>
    <input type="text" name="person_name" id="<?= $p ?>person_name" class="form-control" placeholder="e.g. JUAN D. DELA CRUZ" required>
  </div>
<?php endif; ?>
  <div class="row">
<?php if (!$bulk): ?>
    <div class="col-md-6">
      <div class="form-group">
        <label>Designation <span class="text-danger">*</span></label>
        <select name="designation" id="<?= $p ?>designation" class="form-control ca-designation" required>
          <option value=""></option>
          <?php foreach ($positions as $pos): ?>
            <option value="<?= htmlspecialchars($pos) ?>"><?= htmlspecialchars($pos) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
<?php endif; ?>
    <div class="col-md-<?= $bulk ? 12 : 6 ?>">
      <div class="form-group">
        <label>From Office <span class="text-danger">*</span></label>
        <input type="text" name="office_from" id="<?= $p ?>office_from" class="form-control" placeholder="e.g. NIA Regional Office V" required>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-6">
      <div class="form-group">
        <label>Date of Arrival <span class="text-danger">*</span></label>
        <input type="date" name="date_arrival" id="<?= $p ?>date_arrival" class="form-control" required>
      </div>
    </div>
    <div class="col-md-6">
      <div class="form-group">
        <label>Date of Departure <span class="text-danger">*</span></label>
        <input type="date" name="date_departure" id="<?= $p ?>date_departure" class="form-control" required>
      </div>
    </div>
  </div>
  <div class="form-group">
    <label>Purpose <span class="text-danger">*</span></label>
    <textarea name="purpose" id="<?= $p ?>purpose" class="form-control" rows="2" required></textarea>
  </div>
  <div class="row">
    <div class="col-md-5">
      <div class="form-group">
        <label>Lodging</label>
        <div class="custom-control custom-checkbox">
          <input type="checkbox" class="custom-control-input" name="lodging_provided" id="<?= $p ?>lodging_provided" value="1">
          <label class="custom-control-label" for="<?= $p ?>lodging_provided" style="text-transform:none;">Provided</label>
        </div>
        <small class="form-text text-muted">Unchecked prints "Not Provided".</small>
      </div>
    </div>
    <div class="col-md-7">
      <div class="form-group">
        <label>Meal</label>
        <div class="custom-control custom-checkbox">
          <input type="checkbox" class="custom-control-input meal-toggle" name="meal_provided" id="<?= $p ?>meal_provided" value="1">
          <label class="custom-control-label" for="<?= $p ?>meal_provided" style="text-transform:none;">Provided</label>
        </div>
        <div class="ca-meals" id="<?= $p ?>meals">
          <?php foreach (['breakfast'=>'Breakfast','am_snack'=>'AM Snack','lunch'=>'Lunch','pm_snack'=>'PM Snack','dinner'=>'Dinner'] as $k=>$lbl): ?>
          <div class="custom-control custom-checkbox custom-control-inline ca-check">
            <input type="checkbox" class="custom-control-input" name="<?= $k ?>" id="<?= $p.$k ?>" value="1">
            <label class="custom-control-label" for="<?= $p.$k ?>" style="text-transform:none;"><?= $lbl ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-6">
      <div class="form-group">
        <label>Signatory</label>
        <input type="text" name="signatory_name" id="<?= $p ?>signatory_name" class="form-control" value="MYRA M. ETCOBANEZ">
      </div>
    </div>
    <div class="col-md-6">
      <div class="form-group">
        <label>Signatory Title</label>
        <input type="text" name="signatory_title" id="<?= $p ?>signatory_title" class="form-control" value="Acting Administrative Section Head">
      </div>
    </div>
  </div>
<?php if ($bulk): ?>
  <div class="form-group mb-0">
    <label>Persons <span class="text-danger">*</span>
      <small class="text-muted" style="text-transform:none;">&mdash; one certificate per person; everything above is shared.</small>
    </label>
    <div class="bulk-people">
      <div id="bulk_rows"></div>
      <button type="button" class="btn btn-sm btn-outline-primary" id="bulk_add_row"><i class="fas fa-user-plus"></i> Add person</button>
    </div>
  </div>
<?php endif; ?>
<?php return ob_get_clean(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificate of Appearance</title>
  <?php include '../includes/header.php'; ?>
  <style>
    :root {
      --h-bg:       #eef7f2;
      --h-card:     #ffffff;
      --h-card-alt: #f0faf5;
      --h-surface3: #e2f3ea;
      --h-border:   rgba(42,152,99,0.18);
      --h-text:     #0f2d1e;
      --h-muted:    #4a7a5e;
      --h-primary:  #2a9863;
      --h-primary-dim: rgba(42,152,99,.10);
      --h-accent:   #24e78f;
      --h-success:  #2a9863;
      --h-warning:  #e67700;
      --h-danger:   #c92a2a;
      --h-blue:     #2a9863;
      --h-shadow:   0 4px 24px rgba(42,152,99,.12);
      --h-shadow-sm:0 2px 8px rgba(42,152,99,.07);
    }
    body.dark-mode {
      --h-bg:       #0b1f17;
      --h-card:     #102f22;
      --h-card-alt: #0e2619;
      --h-surface3: #16352a;
      --h-border:   rgba(36,231,143,0.12);
      --h-text:     #d4f5e5;
      --h-muted:    #6aad8a;
      --h-primary:  #24e78f;
      --h-primary-dim: rgba(36,231,143,.12);
      --h-accent:   #2a9863;
      --h-success:  #24e78f;
      --h-warning:  #ffd43b;
      --h-danger:   #ff6b6b;
      --h-blue:     #24e78f;
      --h-shadow:   0 4px 24px rgba(0,0,0,.35);
      --h-shadow-sm:0 2px 8px rgba(0,0,0,.25);
    }

    .content-wrapper { background: var(--h-bg) !important; }

    /* CRITICAL Z-INDEX FIX FOR MODAL OVERLAP */
    .main-header.navbar, .main-header, nav.main-header, header.main-header {
        z-index: 1000 !important;
    }

    .content { padding:0 20px; margin-top:-38px; position:relative; z-index:3; }
    
    .main-sidebar, aside.main-sidebar {
        z-index: 999 !important;
    }
    .modal-backdrop {
        z-index: 1040 !important;
    }
    .modal {
        z-index: 1050 !important;
    }
    .modal-dialog {
        z-index: 1051 !important;
    }
    .modal-content {
        z-index: 1052 !important;
    }
    .select2-container, .select2-dropdown {
        z-index: 1055 !important;
    }
  
        .pg-hero-breadcrumb {
            background:transparent; padding:0; margin:0;
            display:flex; flex-wrap:wrap; gap:2px;
        }
        .pg-hero-breadcrumb .breadcrumb-item + .breadcrumb-item::before { color:rgba(212,245,229,.45); }
        .pg-hero-bc-link   { color:rgba(212,245,229,.65); text-decoration:none; font-size:.8rem; }
        .pg-hero-bc-link:hover { color:#24e78f; }
        .pg-hero-bc-active { color:rgba(212,245,229,.9); font-size:.8rem; }

        /* ══ HERO — login-style animated mesh + orbs + rings ══ */
        @keyframes pgHeroMeshDrift {
            0%   { transform:translate(0,0)   rotate(0deg); }
            100% { transform:translate(3%,2%) rotate(2deg); }
        }
        @keyframes pgHeroOrbFloat {
            0%,100% { opacity:.4; transform:translate(0,0)       scale(1);    }
            33%      { opacity:.7; transform:translate(18px,-26px) scale(1.05); }
            66%      { opacity:.5; transform:translate(-12px,16px) scale(.95);  }
        }
        @keyframes pgHeroRingPulse {
            0%,100% { opacity:.45; transform:scale(1);    }
            50%      { opacity:.85; transform:scale(1.04); }
        }
        .pg-hero {
            background:#0b1f17;
            padding:36px 28px 66px; position:relative; overflow:hidden;
        }
        .pg-hero-mesh {
            position:absolute; inset:-50%; width:200%; height:200%;
            background:
                radial-gradient(ellipse 60% 55% at 18% 28%, rgba(36,231,143,.16) 0%, transparent 58%),
                radial-gradient(ellipse 55% 60% at 82% 72%, rgba(42,152,99,.13) 0%, transparent 58%),
                radial-gradient(ellipse 40% 38% at 52%  8%, rgba(212,175,55,.07) 0%, transparent 50%),
                linear-gradient(160deg,#0f2d1e 0%,#071510 55%,#1c4d38 100%);
            animation:pgHeroMeshDrift 22s ease-in-out infinite alternate;
            z-index:0;
        }
        .pg-hero-orbs { position:absolute; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        .pg-orb { position:absolute; border-radius:50%; filter:blur(60px); animation:pgHeroOrbFloat 18s ease-in-out infinite; }
        .pg-orb-1 { width:280px; height:280px; background:rgba(36,231,143,.11); top:-80px;    left:-60px;  animation-duration:21s; }
        .pg-orb-2 { width:220px; height:220px; background:rgba(42,152,99,.10);  bottom:-50px; right:-40px; animation-delay:-7s; animation-duration:17s; }
        .pg-orb-3 { width:160px; height:160px; background:rgba(212,175,55,.06); top:40%;      right:20%;   animation-delay:-13s; animation-duration:24s; }
        .pg-orb-4 { width:120px; height:120px; background:rgba(36,231,143,.07); bottom:15%;   left:15%;    animation-delay:-4s;  animation-duration:15s; }
        .pg-hero-dots {
            position:absolute; inset:0; z-index:0; pointer-events:none;
            background-image:radial-gradient(circle, rgba(36,231,143,.06) 1px, transparent 1px);
            background-size:36px 36px;
        }
        .pg-hero-hex {
            position:absolute; inset:0; pointer-events:none; opacity:.045; z-index:0;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='100'%3E%3Cpath d='M28 66L0 50V16L28 0l28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3Cpath d='M28 100L0 84V50l28-16 28 16v34z' fill='none' stroke='%2324e78f' stroke-width='1'/%3E%3C/svg%3E");
            background-size:56px 100px;
        }
        .pg-hero-rings {
            position:absolute; top:50%; right:6%;
            transform:translateY(-50%);
            width:240px; height:240px; pointer-events:none; z-index:0;
        }
        .pg-ring {
            position:absolute; inset:0; border-radius:50%;
            border:1px solid rgba(36,231,143,.10);
            animation:pgHeroRingPulse 4s ease-in-out infinite;
        }
        .pg-ring:nth-child(2) { inset:28px; animation-delay:.8s;  opacity:.7; }
        .pg-ring:nth-child(3) { inset:56px; animation-delay:1.6s; opacity:.5; }
        .pg-hero-arc {
            position:absolute; top:-50px; right:-50px;
            width:200px; height:200px; border-radius:50%;
            background:radial-gradient(circle,rgba(36,231,143,.18) 0%,transparent 70%);
            pointer-events:none; z-index:0;
        }
        .pg-hero::after {
            content:''; position:absolute; bottom:-32px; left:0; right:0; height:64px;
            background:var(--body-bg, #eef7f2); clip-path:ellipse(58% 100% at 50% 100%); z-index:1;
        }
        body.dark-mode .pg-hero::after { background:var(--body-bg, #0b1f17); }
        .pg-hero-inner { position:relative; z-index:2; }
        .pg-hero-title {
            color:#fff; font-size:1.75rem; font-weight:800; margin:0 0 6px;
            letter-spacing:-.3px; text-shadow:0 2px 14px rgba(0,0,0,.45);
            display:flex; align-items:center; gap:10px;
        }
        .pg-hero-sub  { color:rgba(212,245,229,.75); margin:0 0 14px; font-size:.9rem; }
        .pg-hero-divider {
            width:48px; height:2px; border-radius:2px; margin:0 0 12px;
            background:linear-gradient(90deg,transparent,#24e78f,transparent);
        }
        .pg-hero-actions {
            position:relative; z-index:2;
            display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; margin-top:4px;
        }
        .pg-hero-date { color:rgba(212,245,229,.65); font-size:.82rem; align-self:center; }
        .pg-hero-btn {
            background:rgba(36,231,143,.1); backdrop-filter:blur(8px);
            border:1px solid rgba(36,231,143,.3); color:#d4f5e5;
            border-radius:10px; padding:8px 16px;
            font-size:.84rem; font-weight:700; cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center; gap:7px;
            transition:background .2s, transform .18s, box-shadow .2s;
        }
        .pg-hero-btn:hover {
            background:rgba(36,231,143,.22); border-color:rgba(36,231,143,.55);
            transform:translateY(-2px); box-shadow:0 4px 16px rgba(36,231,143,.2);
            color:#d4f5e5; text-decoration:none;
        }
        .pg-hero-layout {
            display:flex; align-items:flex-start; justify-content:space-between;
            flex-wrap:wrap; gap:14px; position:relative; z-index:2;
        }
        .mh-logo-watermark {
            position:absolute; top:50%; right:3%;
            transform:translateY(-50%);
            width:180px; height:auto; pointer-events:none; z-index:0;
            opacity:0.50;
        }

        /* ── Card (mirrors the Scrum > Projects "card" look) ── */
        .card {
            background: var(--h-card) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 12px !important;
            box-shadow: var(--h-shadow-sm) !important;
        }
        .card-header {
            background: var(--h-card) !important;
            border-bottom: 1px solid var(--h-border) !important;
            padding: 14px 20px !important;
        }
        .card-title {
            font-weight: 700 !important;
            font-size: .9rem !important;
            color: var(--h-text) !important;
            letter-spacing: -.2px;
        }
        .card-body { background: var(--h-card) !important; padding: 18px !important; }
        .card-tools .btn-primary {
            background: var(--h-primary) !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: .8rem !important;
            box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .card-tools .btn-primary:hover { filter: brightness(1.1) !important; }

        /* ── Table ── */
        #caTable.table { color: var(--h-text) !important; }
        #caTable.table thead th {
            background: var(--h-card-alt) !important;
            border: none !important;
            border-bottom: 1px solid var(--h-border) !important;
            color: var(--h-muted) !important;
            font-size: .68rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .6px !important;
            padding: 10px 14px !important;
        }
        #caTable.table tbody tr { transition: background .12s; }
        #caTable.table tbody tr:hover { background: var(--h-card-alt) !important; }
        #caTable.table tbody td {
            border-top: 1px solid var(--h-border) !important;
            border-left: none !important;
            border-right: none !important;
            padding: 12px 14px !important;
            vertical-align: middle !important;
            font-size: .85rem !important;
            color: var(--h-text) !important;
        }
        #caTable.table-bordered { border: none !important; }

        /* Status badges */
        .badge-secondary { background: var(--h-surface3) !important; color: var(--h-muted) !important; border-radius: 5px !important; }
        .badge-warning   { background: rgba(230,119,0,.15) !important; color: var(--h-warning) !important; border-radius: 5px !important; }
        .badge-info      { background: rgba(42,152,99,.12) !important; color: var(--h-blue) !important; border-radius: 5px !important; }
        .badge-primary   { background: rgba(167,139,250,.15) !important; color: #7c5cd6 !important; border-radius: 5px !important; }
        .badge-success   { background: rgba(42,152,99,.15) !important; color: var(--h-success) !important; border-radius: 5px !important; }
        .badge-danger    { background: rgba(201,34,46,.15) !important; color: var(--h-danger) !important; border-radius: 5px !important; }

        /* Row action buttons */
        #caTable .btn-sm.btn-info    { background: rgba(42,152,99,.12) !important; color: var(--h-blue) !important; border: 1px solid rgba(42,152,99,.25) !important; border-radius: 7px !important; }
        #caTable .btn-sm.btn-warning { background: rgba(230,119,0,.12) !important; color: var(--h-warning) !important; border: 1px solid rgba(230,119,0,.25) !important; border-radius: 7px !important; }
        #caTable .btn-sm.btn-danger  { background: rgba(201,34,46,.12) !important; color: var(--h-danger) !important; border: 1px solid rgba(201,34,46,.25) !important; border-radius: 7px !important; }
        #caTable .btn-sm:hover { filter: brightness(1.15); transform: translateY(-1px); }

        /* ── Scrum-style modal (mirrors the "Create New Project" modal in
           Scrum > Projects) — applied to Add/Edit/View Applicant ── */
        .scrum-style-modal .modal-content {
            background: var(--h-card) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 14px !important;
            box-shadow: 0 24px 80px rgba(0,0,0,.25) !important;
            color: var(--h-text) !important;
        }
        .scrum-style-modal .modal-header {
            background: var(--h-card-alt) !important;
            border-bottom: 1px solid var(--h-border) !important;
            border-radius: 14px 14px 0 0 !important;
            padding: 16px 20px !important;
        }
        .scrum-style-modal .modal-title {
            font-weight: 700 !important;
            font-size: .95rem !important;
            color: var(--h-text) !important;
        }
        .scrum-style-modal .modal-title i { color: var(--h-primary) !important; margin-right: 8px; }
        .scrum-style-modal .modal-header .close {
            color: var(--h-muted) !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }
        .scrum-style-modal .modal-header .close:hover { opacity: .7 !important; }
        .scrum-style-modal .modal-body { padding: 20px !important; background: var(--h-card) !important; }
        .scrum-style-modal .modal-footer {
            border-top: 1px solid var(--h-border) !important;
            padding: 14px 20px !important;
            background: var(--h-card-alt) !important;
            border-radius: 0 0 14px 14px !important;
        }
        .scrum-style-modal label {
            color: var(--h-muted) !important;
            font-size: .72rem !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: .5px !important;
            margin-bottom: 5px !important;
        }
        .scrum-style-modal label .text-danger { text-transform: none !important; }
        .scrum-style-modal .form-control,
        .scrum-style-modal .form-control:focus {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 8px !important;
            font-size: .875rem !important;
        }
        .scrum-style-modal .form-control::placeholder { color: var(--h-muted) !important; }
        .scrum-style-modal .form-control:focus {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }
        .scrum-style-modal select.form-control option { background: var(--h-card) !important; color: var(--h-text) !important; }
        .scrum-style-modal hr { border-color: var(--h-border) !important; }
        .scrum-style-modal h6 {
            color: var(--h-muted) !important;
            font-size: .72rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .5px !important;
        }
        .scrum-style-modal .form-text.text-muted { color: var(--h-muted) !important; }
        .scrum-style-modal .info-box {
            background: var(--h-card-alt) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 8px !important;
            padding: 10px 14px !important;
            font-size: .8rem !important;
            color: var(--h-text) !important;
        }

        /* Bootstrap custom-file input */
        .scrum-style-modal .custom-file-label {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 8px !important;
        }
        .scrum-style-modal .custom-file-label::after {
            background: var(--h-surface3) !important;
            color: var(--h-muted) !important;
            border-left: 1.5px solid var(--h-border) !important;
            border-radius: 0 8px 8px 0 !important;
        }
        .scrum-style-modal .custom-file-input:focus ~ .custom-file-label {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }

        /* Select2 (default theme) — Position Applied picker */
        .scrum-style-modal .select2-container--default .select2-selection--multiple {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            border-radius: 8px !important;
            min-height: 38px !important;
        }
        .scrum-style-modal .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: var(--h-primary-dim) !important;
            border: 1px solid var(--h-primary) !important;
            color: var(--h-text) !important;
            border-radius: 5px !important;
        }
        .scrum-style-modal .select2-container--default .select2-selection__choice__remove { color: var(--h-muted) !important; }
        .scrum-style-modal .select2-container--default .select2-selection__choice__remove:hover { color: var(--h-danger) !important; }
        .scrum-style-modal .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }
        .scrum-style-modal .select2-dropdown {
            background: var(--h-card-alt) !important;
            border: 1px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 8px !important;
            box-shadow: var(--h-shadow) !important;
        }
        .scrum-style-modal .select2-search--dropdown .select2-search__field {
            background: var(--h-card) !important;
            border: 1px solid var(--h-border) !important;
            color: var(--h-text) !important;
            border-radius: 6px !important;
        }
        .scrum-style-modal .select2-results__option { color: var(--h-text) !important; background: transparent !important; }
        .scrum-style-modal .select2-results__option--highlighted[aria-selected] {
            background: var(--h-primary) !important;
            color: #fff !important;
        }

        /* Select2 (single) — Designation picker, themed to match the form inputs */
        .scrum-style-modal .select2-container { width: 100% !important; }
        .scrum-style-modal .select2-container--default .select2-selection--single {
            background: var(--h-card-alt) !important;
            border: 1.5px solid var(--h-border) !important;
            border-radius: 8px !important;
            height: 38px !important;
            display: flex; align-items: center;
        }
        .scrum-style-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--h-text) !important;
            font-size: .875rem !important;
            line-height: 36px !important;
            padding-left: 12px !important;
            padding-right: 30px !important;
        }
        .scrum-style-modal .select2-container--default .select2-selection--single .select2-selection__placeholder { color: var(--h-muted) !important; }
        .scrum-style-modal .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; right: 6px !important; }
        .scrum-style-modal .select2-container--default .select2-selection--single .select2-selection__arrow b { border-color: var(--h-primary) transparent transparent transparent !important; }
        .scrum-style-modal .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b { border-color: transparent transparent var(--h-primary) transparent !important; }
        .scrum-style-modal .select2-container--default.select2-container--focus .select2-selection--single,
        .scrum-style-modal .select2-container--default.select2-container--open .select2-selection--single {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 3px var(--h-primary-dim) !important;
        }
        .scrum-style-modal .select2-results__options { max-height: 220px; }
        .scrum-style-modal .select2-results__option { padding: 8px 12px !important; font-size: .875rem; }
        .scrum-style-modal .select2-results__option[aria-selected=true] {
            background: var(--h-primary-dim) !important;
            color: var(--h-primary) !important;
            font-weight: 600;
        }
        .scrum-style-modal .select2-results__options::-webkit-scrollbar { width: 6px; }
        .scrum-style-modal .select2-results__options::-webkit-scrollbar-thumb { background: var(--h-primary); border-radius: 6px; }
        .scrum-style-modal .select2-results__options::-webkit-scrollbar-track { background: transparent; }
        .scrum-style-modal .select2-search--dropdown { padding: 8px !important; }

        /* Buttons */
        .scrum-style-modal .btn-secondary {
            background: var(--h-card-alt) !important;
            color: var(--h-muted) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
        }
        .scrum-style-modal .btn-primary,
        .scrum-style-modal .btn-warning {
            background: var(--h-primary) !important;
            color: #fff !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 12px var(--h-primary-dim) !important;
        }
        .scrum-style-modal .btn-primary:hover,
        .scrum-style-modal .btn-warning:hover { filter: brightness(1.1) !important; color: #fff !important; }

        /* View Applicant detail content */
        .scrum-style-modal #viewApplicantContent p { color: var(--h-text) !important; }
        .scrum-style-modal #viewApplicantContent strong { color: var(--h-muted) !important; font-weight: 700 !important; }
        .scrum-style-modal #viewApplicantContent a { color: var(--h-primary) !important; }
        .scrum-style-modal #viewApplicantContent hr { border-color: var(--h-border) !important; }

        /* Applicant Details tabs */
        .applicant-detail-tabs {
            border-bottom: 1px solid var(--h-border) !important;
            margin: -20px -20px 18px !important;
            padding: 0 20px !important;
        }
        .applicant-detail-tabs .nav-link {
            border: none !important;
            border-bottom: 2px solid transparent !important;
            color: var(--h-muted) !important;
            font-size: .82rem !important;
            font-weight: 700 !important;
            padding: 12px 14px !important;
            border-radius: 0 !important;
            transition: color .15s, border-color .15s;
        }
        .applicant-detail-tabs .nav-link:hover { color: var(--h-text) !important; }
        .applicant-detail-tabs .nav-link.active {
            color: var(--h-primary) !important;
            background: transparent !important;
            border-bottom-color: var(--h-primary) !important;
        }
        .applicant-detail-tab-content { padding-top: 2px; }

        /* Uploaded documents nav list */
        .doc-nav-list { display: flex; flex-direction: column; gap: 8px; }
        .doc-nav-item {
            display: flex; align-items: center; gap: 12px;
            background: var(--h-card-alt) !important;
            border: 1px solid var(--h-border) !important;
            border-radius: 10px !important;
            padding: 10px 14px !important;
            text-decoration: none !important;
            transition: border-color .15s, transform .15s, box-shadow .15s;
        }
        .doc-nav-item:not(.doc-nav-item-empty):hover {
            border-color: var(--h-primary) !important;
            box-shadow: 0 0 0 1px var(--h-primary-dim) !important;
            transform: translateY(-1px);
        }
        .doc-nav-item-empty { opacity: .6; cursor: default; }
        .doc-nav-icon {
            width: 34px; height: 34px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: var(--h-surface3) !important;
            border-radius: 8px !important;
            font-size: .95rem;
        }
        .doc-nav-info { display: flex; flex-direction: column; min-width: 0; flex: 1; }
        .doc-nav-label {
            font-size: .68rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: var(--h-muted) !important;
        }
        .doc-nav-file {
            font-size: .84rem; color: var(--h-text) !important; font-weight: 500;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .doc-nav-item-empty .doc-nav-file { color: var(--h-muted) !important; font-style: italic; }
        .doc-nav-open {
            color: var(--h-muted) !important;
            font-size: .8rem; flex-shrink: 0;
        }
        .doc-nav-item:hover .doc-nav-open { color: var(--h-primary) !important; }
  
    /* ── Distinct badge colors: Provided = green, Not Provided = red; templates = blue / purple ── */
    #caTable .badge { font-size: .7rem; font-weight: 700; padding: 5px 9px; border-radius: 6px !important; letter-spacing: .2px; }
    .badge-ca-yes { background: rgba(42,152,99,.16) !important; color: #1f7a4d !important; border: 1px solid rgba(42,152,99,.35); }
    .badge-ca-no  { background: rgba(201,42,42,.12) !important; color: #c92a2a !important; border: 1px solid rgba(201,42,42,.30); }
    .badge-tpl-standard    { background: rgba(37,99,235,.12) !important; color: #1d4ed8 !important; border: 1px solid rgba(37,99,235,.30); }
    .badge-tpl-imo_manager { background: rgba(124,58,237,.12) !important; color: #6d28d9 !important; border: 1px solid rgba(124,58,237,.30); }
    .badge-tpl { white-space: normal; }
    body.dark-mode .badge-ca-yes { color: #24e78f !important; background: rgba(36,231,143,.14) !important; border-color: rgba(36,231,143,.35); }
    body.dark-mode .badge-ca-no  { color: #ff8787 !important; background: rgba(255,107,107,.14) !important; border-color: rgba(255,107,107,.35); }
    body.dark-mode .badge-tpl-standard    { color: #7cb0ff !important; background: rgba(96,150,255,.15) !important; border-color: rgba(96,150,255,.35); }
    body.dark-mode .badge-tpl-imo_manager { color: #c4a5ff !important; background: rgba(167,139,250,.15) !important; border-color: rgba(167,139,250,.35); }

    /* ── Match Applicant Databank look: header buttons, row buttons, dropdowns, checkboxes ── */
    .card-tools .btn { border-radius: 8px !important; font-weight: 700 !important; font-size: .8rem !important; }
    .card-tools .btn-success,
    .card-tools .btn-outline-success {
        background: var(--h-primary-dim) !important;
        color: var(--h-primary) !important;
        border: 1px solid var(--h-primary) !important;
        box-shadow: none !important;
    }
    .card-tools .btn-success:hover,
    .card-tools .btn-outline-success:hover:not(:disabled) { background: var(--h-primary) !important; color: #fff !important; }
    .card-tools .btn-outline-success:disabled { opacity: .5; cursor: not-allowed; }
    .card-tools .btn-outline-danger {
        background: rgba(201,34,46,.10) !important; color: var(--h-danger) !important;
        border: 1px solid rgba(201,34,46,.40) !important; box-shadow: none !important;
    }
    .card-tools .btn-outline-danger:hover:not(:disabled) { background: var(--h-danger) !important; color: #fff !important; }
    .card-tools .btn-outline-danger:disabled { opacity: .5; cursor: not-allowed; }
    .dropdown-menu .dropdown-item.disabled { opacity: .45; pointer-events: none; }
    .dropdown-menu .dropdown-item.text-danger:hover { background: rgba(201,34,46,.12) !important; color: var(--h-danger) !important; }
    #caTable .btn-sm.btn-success { background: rgba(42,152,99,.12) !important; color: var(--h-success) !important; border: 1px solid rgba(42,152,99,.25) !important; border-radius: 7px !important; }
    #caTable .btn-group { white-space: nowrap; }
    #caTable .btn-group .btn-sm { margin-right: 0; }
    #caTable td:last-child { white-space: nowrap; }
    #caTable td:last-child > .btn { margin-left: 2px; }
    #caTable input[type=checkbox], .ca-select { accent-color: var(--h-primary); width: 15px; height: 15px; cursor: pointer; }
    #caTable thead th:first-child { width: 30px; text-align: center; }
    #caTable tbody td:first-child { text-align: center; }
    .dropdown-menu {
        background: var(--h-card) !important;
        border: 1px solid var(--h-border) !important;
        border-radius: 10px !important;
        box-shadow: var(--h-shadow) !important;
        padding: 6px !important;
        z-index: 1060 !important;
    }
    .dropdown-menu .dropdown-header {
        color: var(--h-muted) !important;
        font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
    }
    .dropdown-menu .dropdown-item {
        color: var(--h-text) !important;
        font-size: .84rem; border-radius: 7px; padding: 7px 12px;
    }
    .dropdown-menu .dropdown-item:hover,
    .dropdown-menu .dropdown-item:focus { background: var(--h-primary-dim) !important; color: var(--h-primary) !important; }
    .scrum-style-modal .btn-success {
        background: var(--h-primary) !important; color: #fff !important; border: none !important;
        border-radius: 8px !important; font-weight: 700 !important; box-shadow: 0 2px 12px var(--h-primary-dim) !important;
    }
    .scrum-style-modal .btn-success:hover { filter: brightness(1.1) !important; color: #fff !important; }
    .scrum-style-modal .btn-outline-primary,
    .scrum-style-modal .btn-outline-danger { border-radius: 8px !important; font-weight: 600 !important; background: transparent !important; }
    .scrum-style-modal .btn-outline-primary { color: var(--h-primary) !important; border: 1px solid var(--h-primary) !important; }
    .scrum-style-modal .btn-outline-primary:hover { background: var(--h-primary-dim) !important; }
    .scrum-style-modal .btn-outline-danger { color: var(--h-danger) !important; border: 1px solid rgba(201,34,46,.4) !important; }
    .scrum-style-modal .btn-outline-danger:hover:not(:disabled) { background: rgba(201,34,46,.12) !important; }
    .scrum-style-modal .custom-control-label { color: var(--h-text) !important; font-size: .85rem !important; font-weight: 500 !important; }
    .scrum-style-modal .custom-control-input:checked ~ .custom-control-label::before {
        background-color: var(--h-primary) !important; border-color: var(--h-primary) !important;
    }
    .scrum-style-modal .custom-control-label::before { background: var(--h-card-alt); border: 1.5px solid var(--h-border); }
    .scrum-style-modal .custom-control-input:focus ~ .custom-control-label::before { box-shadow: 0 0 0 3px var(--h-primary-dim) !important; }
    .bulk-people .form-control { background: var(--h-card) !important; }
    .scrum-style-modal #viewCaContent .ca-detail-row:last-child { border-bottom: none; }
    .ca-meals { display:none; padding:10px 14px; border:1.5px dashed var(--h-border); border-radius:8px; margin-top:6px; }
    .ca-check { margin-right:18px; }
    .no-meal-detail .ca-meals { display:none !important; }
    .ca-template-note { color: var(--h-muted) !important; }
    .bulk-people { position:relative; border:1.5px solid var(--h-border); border-radius:8px; padding:12px; background: var(--h-card-alt); }
    .bulk-row { margin-bottom:8px; }
    /* Fixed grid so name / designation columns never resize when a designation is picked */
    .bulk-row > .d-flex {
        display:grid !important;
        grid-template-columns: 30px minmax(0,1fr) minmax(0,1fr) 34px;
        gap:8px; align-items:center;
    }
    .bulk-row > .d-flex > .flex-fill { padding-right:0 !important; min-width:0; width:100%; }
    .bulk-row .bulk-name { width:100%; }
    .bulk-row .select2-container { width:100% !important; min-width:0; }
    .bulk-row .select2-container--default .select2-selection--single {
        display:flex !important; align-items:center; position:relative; overflow:hidden;
    }
    .bulk-row .select2-container--default .select2-selection--single .select2-selection__rendered {
        flex:1 1 auto; min-width:0; display:block !important;
        white-space:nowrap !important; overflow:hidden !important; text-overflow:ellipsis !important;
        padding-left:12px !important; padding-right:46px !important;
    }
    .bulk-row .select2-container--default .select2-selection--single .select2-selection__clear {
        position:absolute; right:28px; top:0; height:36px; line-height:36px;
        margin:0; padding:0; float:none; color:var(--h-muted);
    }
    .bulk-row .select2-container--default .select2-selection--single .select2-selection__clear:hover { color:var(--h-danger); }
    .bulk-row .bulk-remove { width:34px; height:34px; padding:0; display:flex; align-items:center; justify-content:center; }
    .bulk-people .select2-dropdown { min-width:260px; }
    .bulk-people .select2-results__option { white-space:normal; }
    .bulk-num { width:30px; font-weight:700; color:var(--h-muted); }
    .bulk-people .select2-container { width:100% !important; }
    .ca-detail-row { display:flex; align-items:flex-start; padding:6px 0; border-bottom:1px solid var(--h-border); font-size:.875rem; }
    .ca-detail-row .k { width:160px; color:var(--h-muted); font-weight:600; flex-shrink:0; }
    /* long purposes / unbroken text wrap inside the modal instead of overflowing it */
    .ca-detail-row .v { color:var(--h-text); flex:1 1 auto; min-width:0; overflow-wrap:anywhere; word-break:break-word; white-space:pre-line; }
    #caTable td { overflow-wrap:anywhere; }

    /* ── View modal: read-only "certificate card" (looks different from the Edit form) ── */
    .cav-top { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 16px; border-radius:10px;
               background: var(--h-primary); color:#fff; }
    .cav-tn-label { font-size:.68rem; letter-spacing:.8px; text-transform:uppercase; opacity:.85; font-weight:700; }
    .cav-tn { font-size:1.35rem; font-weight:800; letter-spacing:.5px; line-height:1.15; }
    .cav-tpl { font-size:.75rem; font-weight:700; background:rgba(255,255,255,.2); padding:5px 10px; border-radius:20px; white-space:nowrap; }
    .cav-person { display:flex; align-items:center; gap:14px; padding:16px 4px 6px; }
    .cav-avatar { width:54px; height:54px; border-radius:50%; background:var(--h-primary-dim); color:var(--h-primary);
                  display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
    .cav-person-text { min-width:0; }
    .cav-name { font-size:1.15rem; font-weight:800; color:var(--h-text); overflow-wrap:anywhere; line-height:1.2; }
    .cav-desig { font-size:.9rem; color:var(--h-text); }
    .cav-office { font-size:.8rem; color:var(--h-muted); overflow-wrap:anywhere; margin-top:2px; }
    .cav-dates { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:12px 0; padding:12px 16px;
                 border:1.5px solid var(--h-border); border-radius:10px; background:var(--h-card-alt); }
    .cav-date { display:flex; flex-direction:column; }
    .cav-date span { font-size:.68rem; letter-spacing:.6px; text-transform:uppercase; color:var(--h-muted); font-weight:700; }
    .cav-date strong { font-size:.98rem; color:var(--h-text); }
    .cav-date:last-child { text-align:right; }
    .cav-arrow { display:flex; flex-direction:column; align-items:center; color:var(--h-primary); font-size:.95rem; }
    .cav-arrow small { font-size:.68rem; font-weight:700; color:var(--h-muted); }
    .cav-section { border:1.5px solid var(--h-border); border-radius:10px; padding:10px 14px; margin-bottom:12px; min-width:0; }
    .cav-title { font-size:.7rem; letter-spacing:.7px; text-transform:uppercase; font-weight:800; color:var(--h-primary); margin-bottom:6px; }
    .cav-title i { margin-right:5px; }
    .cav-purpose { color:var(--h-text); font-size:.92rem; overflow-wrap:anywhere; word-break:break-word; white-space:pre-line; }
    .cav-grid { display:grid; grid-template-columns: 1fr 2fr; gap:12px; }
    .cav-grid .cav-section { margin-bottom:0; }
    .cav-status { font-size:.78rem !important; padding:6px 12px !important; border-radius:20px !important; font-weight:700; }
    .cav-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .cav-chip { font-size:.74rem; font-weight:700; padding:4px 10px; border-radius:20px; border:1px solid var(--h-border); }
    .cav-chip.on  { background:var(--h-primary-dim); color:var(--h-primary); border-color:var(--h-primary); }
    .cav-chip.off { color:var(--h-muted); opacity:.55; text-decoration:line-through; }
    .cav-sign { display:flex; justify-content:space-between; align-items:flex-end; gap:12px; margin-top:12px; padding-top:10px; border-top:1px dashed var(--h-border); }
    .cav-sign span { display:block; font-size:.68rem; letter-spacing:.6px; text-transform:uppercase; color:var(--h-muted); font-weight:700; }
    .cav-sign strong { color:var(--h-text); }
    .cav-sign small { display:block; color:var(--h-muted); }
    .cav-created { font-size:.74rem; color:var(--h-muted); text-align:right; white-space:nowrap; }
    @media (max-width:576px) { .cav-grid { grid-template-columns:1fr; } .cav-top { flex-direction:column; align-items:flex-start; } }
</style>
</head>
<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <?php include '../includes/mainheader.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">

      <!-- Page Hero -->
      <div class="pg-hero">
        <div class="pg-hero-mesh"></div>
        <div class="pg-hero-dots"></div>
        <div class="pg-hero-hex"></div>
        <div class="pg-hero-orbs">
          <div class="pg-orb pg-orb-1"></div>
          <div class="pg-orb pg-orb-2"></div>
          <div class="pg-orb pg-orb-3"></div>
          <div class="pg-orb pg-orb-4"></div>
        </div>
        <div class="pg-hero-rings">
          <img src="../dist/img/nialogo.png" alt="NIA" class="mh-logo-watermark">
        </div>
        <div class="pg-hero-arc"></div>
        <div class="pg-hero-layout">
          <div class="pg-hero-inner">
            <div class="pg-hero-title"><i class="fas fa-file-signature"></i>Certificate of Appearance</div>
            <div class="pg-hero-divider"></div>
            <p class="pg-hero-sub">Issue and download Certificates of Appearance for visiting personnel</p>
          </div>
        </div>
      </div>

      <div class="content">
        <div class="container-fluid">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Manage Certificates</h3>
              <div class="card-tools">
                <div class="btn-group mr-1">
                  <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="bulkDownloadBtn" data-toggle="dropdown" data-boundary="window" disabled>
                    <i class="fas fa-file-word"></i> Download Selected (<span id="selCount">0</span>)
                  </button>
                  <div class="dropdown-menu dropdown-menu-right">
                    <h6 class="dropdown-header">Download all selected in 1 Word file</h6>
                    <a class="dropdown-item bulk-dl-item" data-template="" href="#">Use each certificate's saved template</a>
                    <?php foreach ($CA_TEMPLATES as $key => $t): ?>
                      <a class="dropdown-item bulk-dl-item" data-template="<?= $key ?>" href="#"><?= htmlspecialchars($t['label']) ?></a>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="btn-group mr-1">
                  <button type="button" class="btn btn-outline-danger btn-sm dropdown-toggle" id="bulkDeleteBtn" data-toggle="dropdown" data-boundary="window"<?= $certs ? '' : ' disabled' ?>>
                    <i class="fas fa-trash"></i> Delete
                  </button>
                  <div class="dropdown-menu dropdown-menu-right">
                    <h6 class="dropdown-header">Delete certificates</h6>
                    <a class="dropdown-item disabled" id="delSelItem" href="#"><i class="fas fa-check-square mr-1"></i>Delete Selected (<span id="delSelCount">0</span>)</a>
                    <a class="dropdown-item text-danger" id="delAllItem" href="#" data-total="<?= count($certs) ?>"><i class="fas fa-exclamation-triangle mr-1"></i>Delete All (<?= count($certs) ?>)</a>
                  </div>
                </div>
                <button type="button" class="btn btn-outline-success btn-sm mr-1" data-toggle="modal" data-target="#reportCaModal">
                  <i class="fas fa-print"></i> Report
                </button>
                <button type="button" class="btn btn-success btn-sm mr-1" data-toggle="modal" data-target="#bulkCaModal">
                  <i class="fas fa-users"></i> Bulk Certificates
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addCaModal">
                  <i class="fas fa-plus"></i> New Certificate
                </button>
              </div>
            </div>
            <div class="card-body">
              <table id="caTable" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th class="no-sort" style="width:30px;"><input type="checkbox" id="caSelectAll" title="Select all on this page"></th>
                    <th>Tracking No.</th>
                    <th>Name</th>
                    <th>Designation</th>
                    <th>From Office</th>
                    <th>Arrival</th>
                    <th>Departure</th>
                    <!-- <th>Lodging</th>
                    <th>Meals</th>
                    <th>Template</th> -->
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($certs as $c):
                    $meals = [];
                    if ($c['meal_provided']) {
                      if ($c['breakfast']) $meals[] = 'Breakfast';
                      if ($c['am_snack'])  $meals[] = 'AM Snack';
                      if ($c['lunch'])     $meals[] = 'Lunch';
                      if ($c['pm_snack'])  $meals[] = 'PM Snack';
                      if ($c['dinner'])    $meals[] = 'Dinner';
                    }
                  ?>
                  <tr>
                    <td><input type="checkbox" class="ca-select" value="<?= (int)$c['ca_id'] ?>"></td>
                    <td class="text-nowrap"><strong><?= htmlspecialchars($c['tracking_no'] ?? '') ?></strong></td>
                    <td><?= htmlspecialchars($c['person_name']) ?></td>
                    <td><?= htmlspecialchars($c['designation']) ?></td>
                    <td><?= htmlspecialchars($c['office_from']) ?></td>
                    <td class="text-nowrap" data-order="<?= $c['date_arrival'] ?>"><?= date('M d, Y', strtotime($c['date_arrival'])) ?></td>
                    <td class="text-nowrap" data-order="<?= $c['date_departure'] ?>"><?= date('M d, Y', strtotime($c['date_departure'])) ?></td>
                    <td>
                      <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-toggle="dropdown" data-boundary="window" title="Download Word file">
                          <i class="fas fa-file-word"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                          <h6 class="dropdown-header">Download using template</h6>
                          <?php foreach ($CA_TEMPLATES as $key => $t): ?>
                            <a class="dropdown-item" href="certificate_of_appearance.php?action=download&id=<?= $c['ca_id'] ?>&template=<?= $key ?>">
                              <?= htmlspecialchars($t['label']) ?>
                              <?php if (($c['template_key'] ?? CA_DEFAULT_TEMPLATE) === $key): ?><small class="text-muted">(saved)</small><?php endif; ?>
                            </a>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <button class="btn btn-sm btn-info view-btn" data-id="<?= $c['ca_id'] ?>" title="View"><i class="fas fa-eye"></i></button>
                      <button class="btn btn-sm btn-warning edit-btn" data-id="<?= $c['ca_id'] ?>" title="Edit"><i class="fas fa-edit"></i></button>
                      <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $c['ca_id'] ?>" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php include '../includes/mainfooter.php'; ?>
  </div>

  <!-- Add Modal -->
  <div class="modal fade scrum-style-modal" id="addCaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="addCaForm">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-file-signature"></i>New Certificate of Appearance</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body"><?= ca_form_fields('add_', $positions) ?></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save Certificate</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bulk Modal -->
  <div class="modal fade scrum-style-modal" id="bulkCaModal" tabindex="-1" data-backdrop="static">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="bulkCaForm">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-users"></i>Bulk Certificates of Appearance</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body"><?= ca_form_fields('bulk_', $positions, true) ?></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" id="bulkSubmitBtn"><i class="fas fa-layer-group"></i> Generate Certificates</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Report Modal -->
  <?php
    $report_years = [(int)date('Y')];
    foreach ($certs as $c) { $report_years[] = (int)date('Y', strtotime($c['date_arrival'])); }
    $report_years = array_unique($report_years); rsort($report_years);
  ?>
  <div class="modal fade scrum-style-modal" id="reportCaModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="get" action="certificate_of_appearance.php" target="_blank">
          <input type="hidden" name="action" value="report">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-print"></i>Certificate of Appearance Report</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Year (by arrival date)</label>
                  <select name="year" class="form-control">
                    <?php foreach ($report_years as $y): ?><option value="<?= $y ?>"><?= $y ?></option><?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Month</label>
                  <select name="month" class="form-control">
                    <option value="0">Whole year</option>
                    <?php for ($mo = 1; $mo <= 12; $mo++): ?><option value="<?= $mo ?>"><?= date('F', mktime(0, 0, 0, $mo, 1)) ?></option><?php endfor; ?>
                  </select>
                </div>
              </div>
            </div>
            <small class="form-text text-muted">Opens a print-ready page with Tracking No., Name, Designation, Agency/Office, Arrival, Departure, Purpose, Lodging and Meals.</small>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-print"></i> Open Report</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade scrum-style-modal" id="editCaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="editCaForm">
          <input type="hidden" name="ca_id" id="edit_ca_id">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-edit"></i>Edit Certificate of Appearance</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body"><?= ca_form_fields('edit_', $positions) ?></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Update Certificate</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Modal -->
  <div class="modal fade scrum-style-modal" id="viewCaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-file-alt"></i>Certificate of Appearance</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body"><div id="viewCaContent"></div></div>
        <div class="modal-footer">
          <div class="btn-group dropup">
            <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown"><i class="fas fa-file-word"></i> Download .docx</button>
            <div class="dropdown-menu" id="viewCaDownloadMenu">
              <?php foreach ($CA_TEMPLATES as $key => $t): ?>
                <a class="dropdown-item" data-template="<?= $key ?>" href="#"><?= htmlspecialchars($t['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
  $(document).ready(function() {
    const caTable = $('#caTable').DataTable({
      responsive: true,
      order: [[5, 'desc']],
      columnDefs: [{ targets: 0, orderable: false, searchable: false }]
    });

    // ── Select certificates & download them again as ONE combined Word file ──
    let selectedIds = {};
    function refreshSelection() {
      let ids = Object.keys(selectedIds);
      $('#selCount').text(ids.length);
      $('#delSelCount').text(ids.length);
      $('#delSelItem').toggleClass('disabled', ids.length === 0);
      $('#bulkDownloadBtn').prop('disabled', ids.length === 0)
        .toggleClass('btn-outline-success', ids.length === 0)
        .toggleClass('btn-success', ids.length > 0);
      let $boxes = $('#caTable tbody .ca-select');
      $('#caSelectAll').prop('checked', $boxes.length > 0 && $boxes.filter(':checked').length === $boxes.length);
    }
    function syncVisibleChecks() {
      $('#caTable tbody .ca-select').each(function() { this.checked = !!selectedIds[this.value]; });
      refreshSelection();
    }
    $(document).on('change', '.ca-select', function() {
      if (this.checked) selectedIds[this.value] = true; else delete selectedIds[this.value];
      refreshSelection();
    });
    $('#caSelectAll').on('change', function() {
      let on = this.checked;
      $('#caTable tbody .ca-select').each(function() {
        this.checked = on;
        if (on) selectedIds[this.value] = true; else delete selectedIds[this.value];
      });
      refreshSelection();
    });
    caTable.on('draw', syncVisibleChecks);

    $(document).on('click', '.bulk-dl-item', function(e) {
      e.preventDefault();
      let ids = Object.keys(selectedIds).map(Number).sort(function(a, b) { return a - b; });
      if (!ids.length) return;
      if (ids.length > 100) { Swal.fire('Too many selected', 'You can download up to 100 certificates at a time.', 'warning'); return; }
      let tpl = $(this).data('template');
      window.location.href = 'certificate_of_appearance.php?action=download_bulk&ids=' + ids.join(',') + (tpl ? '&template=' + encodeURIComponent(tpl) : '');
    });

    // ── Delete several selected certificates ──
    $('#delSelItem').on('click', function(e) {
      e.preventDefault();
      let ids = Object.keys(selectedIds).map(Number);
      if (!ids.length) { Swal.fire('Nothing selected', 'Tick the certificates you want to delete first.', 'info'); return; }
      Swal.fire({
        title: 'Delete ' + ids.length + ' certificate' + (ids.length === 1 ? '' : 's') + '?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete them!'
      }).then(function(r) {
        if (!r.isConfirmed) return;
        $.post('certificate_of_appearance.php', { action: 'bulk_delete', ids: ids }, function(res) {
          if (res.success) Swal.fire('Deleted!', res.message, 'success').then(() => location.reload());
          else Swal.fire('Error!', res.message, 'error');
        }, 'json').fail(function() {
          Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
        });
      });
    });

    // ── Delete ALL certificates (must type DELETE to confirm) ──
    $('#delAllItem').on('click', function(e) {
      e.preventDefault();
      let total = $(this).data('total');
      if (!total) { Swal.fire('Nothing to delete', 'There are no certificates.', 'info'); return; }
      Swal.fire({
        title: 'Delete ALL ' + total + ' certificates?',
        html: 'This permanently removes every certificate and cannot be undone.<br>Type <b>DELETE</b> to confirm.',
        icon: 'warning',
        input: 'text',
        inputPlaceholder: 'DELETE',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete all',
        preConfirm: function(v) {
          if ($.trim(v) !== 'DELETE') { Swal.showValidationMessage('Please type DELETE exactly.'); return false; }
          return true;
        }
      }).then(function(r) {
        if (!r.isConfirmed) return;
        $.post('certificate_of_appearance.php', { action: 'delete_all', confirm: 'DELETE' }, function(res) {
          if (res.success) Swal.fire('Deleted!', res.message, 'success').then(() => location.reload());
          else Swal.fire('Error!', res.message, 'error');
        }, 'json').fail(function() {
          Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
        });
      });
    });

    // Themed Designation picker (type to search; new designations can be typed in)
    $('.ca-designation').each(function() {
      let $modal = $(this).closest('.modal');
      $(this).select2({
        placeholder: '-- Select or type a designation --',
        allowClear: true,
        tags: true,
        dropdownParent: $modal,
        width: '100%',
        createTag: function(params) {
          let term = $.trim(params.term);
          return term === '' ? null : { id: term, text: term, newTag: true };
        },
        templateResult: function(d) {
          return d.newTag ? $('<span><i class="fas fa-plus-circle mr-1"></i>Use "' + $('<div>').text(d.text).html() + '"</span>') : d.text;
        }
      });
    });
    function setDesignation($el, value) {
      if (value && !$el.find('option').filter(function() { return this.value === value; }).length) {
        $el.append(new Option(value, value, false, false));
      }
      $el.val(value || null).trigger('change');
    }

    const CA_TEMPLATES = <?= json_encode($CA_TEMPLATES, JSON_UNESCAPED_UNICODE) ?>;
    const CA_DEFAULT = <?= json_encode(CA_DEFAULT_TEMPLATE) ?>;
    const CA_POSITIONS = <?= json_encode($positions, JSON_UNESCAPED_UNICODE) ?>;

    // Template chooser: swap default signatory, hide meal breakdown for templates that don't have it
    function applyTemplate($select, swapSignatory) {
      let key = $select.val(), t = CA_TEMPLATES[key]; if (!t) return;
      let $form = $select.closest('form'), p = $select.attr('id').replace('template_key', '');
      $form.toggleClass('no-meal-detail', !t.detailed);
      $form.find('.ca-template-note').text(t.detailed ? '' : 'This template only shows Provided / Not Provided for lodging and meals (no breakfast, snack, lunch or dinner breakdown).');
      if (swapSignatory) {
        let $n = $('#' + p + 'signatory_name'), $t = $('#' + p + 'signatory_title');
        let isDefault = $.map(CA_TEMPLATES, function(x) { return x.signatory[0]; }).indexOf($.trim($n.val())) !== -1 || $.trim($n.val()) === '';
        if (isDefault) { $n.val(t.signatory[0]); $t.val(t.signatory[1]); }
      }
    }
    $(document).on('change', '.ca-template', function() { applyTemplate($(this), true); });

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function fmtDate(d) {
      if (!d) return '';
      return new Date(d + 'T00:00:00').toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    }

    // Show meal options only when "Meal: Provided" is ticked
    $(document).on('change', '.meal-toggle', function() {
      let box = $(this).closest('.form-group').find('.ca-meals');
      if (this.checked) { box.slideDown(120); }
      else { box.slideUp(120).find('input').prop('checked', false); }
    });

    // Departure can't precede arrival
    $(document).on('change', '[id$="date_arrival"]', function() {
      let p = this.id.replace('date_arrival', '');
      $('#' + p + 'date_departure').attr('min', this.value);
    });

    // Reset the add form each time it opens
    $('#addCaModal').on('show.bs.modal', function() {
      $('#addCaForm')[0].reset();
      $('#add_designation').val(null).trigger('change');
      $('#add_template_key').val(CA_DEFAULT);
      $('#add_signatory_name').val(CA_TEMPLATES[CA_DEFAULT].signatory[0]);
      $('#add_signatory_title').val(CA_TEMPLATES[CA_DEFAULT].signatory[1]);
      applyTemplate($('#add_template_key'), false);
      $('#add_meals').hide();
    });

    function submitForm(formSel, action) {
      let data = $(formSel).serialize() + '&action=' + action;
      $.post('certificate_of_appearance.php', data, function(res) {
        if (res.success) {
          Swal.fire('Success!', res.message, 'success').then(() => location.reload());
        } else {
          Swal.fire('Error!', res.message, 'error');
        }
      }, 'json').fail(function() {
        Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
      });
    }

    $('#addCaForm').on('submit', function(e) { e.preventDefault(); submitForm(this, 'create'); });
    $('#editCaForm').on('submit', function(e) { e.preventDefault(); submitForm(this, 'update'); });

    function getCert(id, cb) {
      $.post('certificate_of_appearance.php', { action: 'get', ca_id: id }, function(res) {
        if (res.success) cb(res.data); else Swal.fire('Error!', res.message, 'error');
      }, 'json');
    }

    // ── Bulk certificates: shared details + a list of (name, designation) ──
    const BULK_START_ROWS = 3;
    function updateBulkCount() {
      let n = $('#bulk_rows .bulk-name').filter(function() { return $.trim(this.value) !== ''; }).length;
      $('#bulkSubmitBtn').html('<i class="fas fa-layer-group"></i> Generate ' + (n ? n + ' ' : '') + 'Certificate' + (n === 1 ? '' : 's'));
    }
    function renumberBulk() {
      let $rows = $('#bulk_rows .bulk-row');
      $rows.each(function(i) { $(this).find('.bulk-num').text((i + 1) + '.'); });
      $rows.find('.bulk-remove').prop('disabled', $rows.length <= 1);
      updateBulkCount();
    }
    function addBulkRow() {
      let $row = $('<div class="bulk-row"><div class="d-flex align-items-center">' +
        '<div class="bulk-num"></div>' +
        '<div class="flex-fill pr-2"><input type="text" name="bulk_name[]" class="form-control bulk-name" placeholder="Complete name e.g. JUAN D. DELA CRUZ" autocomplete="off"></div>' +
        '<div class="flex-fill pr-2"><select name="bulk_designation[]" class="form-control bulk-designation"><option value=""></option></select></div>' +
        '<button type="button" class="btn btn-sm btn-outline-danger bulk-remove" title="Remove"><i class="fas fa-times"></i></button>' +
        '</div></div>');
      let $sel = $row.find('select');
      CA_POSITIONS.forEach(function(pos) { $sel.append($('<option>').val(pos).text(pos)); });
      $('#bulk_rows').append($row);
      $sel.select2({
        placeholder: '-- Designation --',
        allowClear: true,
        tags: true,
        dropdownParent: $('#bulk_rows').closest('.bulk-people'),
        width: '100%',
        createTag: function(params) {
          let term = $.trim(params.term);
          return term === '' ? null : { id: term, text: term, newTag: true };
        }
      });
      renumberBulk();
      return $row;
    }
    $('#bulk_add_row').on('click', function() { addBulkRow().find('.bulk-name').focus(); });
    $(document).on('click', '.bulk-remove', function() {
      let $row = $(this).closest('.bulk-row');
      $row.find('select').select2('destroy');
      $row.remove();
      renumberBulk();
    });
    $(document).on('input', '.bulk-name', updateBulkCount);
    // Enter in a name box jumps to the next row (adding one if it's the last) instead of submitting
    $(document).on('keydown', '.bulk-name', function(e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      let $next = $(this).closest('.bulk-row').next('.bulk-row');
      if (!$next.length) $next = addBulkRow();
      $next.find('.bulk-name').focus();
    });

    $('#bulkCaModal').on('show.bs.modal', function() {
      $('#bulkCaForm')[0].reset();
      $('#bulk_rows').find('select').select2('destroy');
      $('#bulk_rows').empty();
      for (let i = 0; i < BULK_START_ROWS; i++) addBulkRow();
      $('#bulk_template_key').val(CA_DEFAULT);
      $('#bulk_signatory_name').val(CA_TEMPLATES[CA_DEFAULT].signatory[0]);
      $('#bulk_signatory_title').val(CA_TEMPLATES[CA_DEFAULT].signatory[1]);
      applyTemplate($('#bulk_template_key'), false);
      $('#bulk_meals').hide();
      $('#bulkSubmitBtn').prop('disabled', false);
    }).on('shown.bs.modal', function() {
      $('#bulk_rows .bulk-name').first().focus();
    });

    $('#bulkCaForm').on('submit', function(e) {
      e.preventDefault();
      let $btn = $('#bulkSubmitBtn').prop('disabled', true);
      $.post('certificate_of_appearance.php', $(this).serialize() + '&action=bulk_create', function(res) {
        if (!res.success) { $btn.prop('disabled', false); Swal.fire('Error!', res.message, 'error'); return; }
        Swal.fire({
          icon: 'success',
          title: 'Saved!',
          text: res.message,
          showCancelButton: true,
          confirmButtonText: '<i class="fas fa-file-word"></i> Download 1 Word file (' + res.ids.length + ' pages)',
          cancelButtonText: 'Close',
          allowOutsideClick: false
        }).then(function(r) {
          if (r.isConfirmed) {
            window.location.href = 'certificate_of_appearance.php?action=download_bulk&ids=' + res.ids.join(',') + '&template=' + encodeURIComponent(res.template);
            setTimeout(function() { location.reload(); }, 1500);
          } else {
            location.reload();
          }
        });
      }, 'json').fail(function() {
        $btn.prop('disabled', false);
        Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
      });
    });

    // Edit
    $(document).on('click', '.edit-btn', function() {
      getCert($(this).data('id'), function(d) {
        $('#edit_ca_id').val(d.ca_id);
        ['person_name','office_from','date_arrival','date_departure','purpose','signatory_name','signatory_title']
          .forEach(function(f) { $('#edit_' + f).val(d[f]); });
        $('#edit_template_key').val(CA_TEMPLATES[d.template_key] ? d.template_key : CA_DEFAULT);
        applyTemplate($('#edit_template_key'), false);
        setDesignation($('#edit_designation'), d.designation);
        $('#edit_date_departure').attr('min', d.date_arrival);
        $('#edit_lodging_provided').prop('checked', d.lodging_provided == 1);
        $('#edit_meal_provided').prop('checked', d.meal_provided == 1);
        ['breakfast','am_snack','lunch','pm_snack','dinner'].forEach(function(m) { $('#edit_' + m).prop('checked', d[m] == 1); });
        $('#edit_meals').toggle(d.meal_provided == 1);
        $('#editCaModal').modal('show');
      });
    });

    // View
    $(document).on('click', '.view-btn', function() {
      getCert($(this).data('id'), function(d) {
        let meals = [];
        if (d.meal_provided == 1) {
          if (d.breakfast == 1) meals.push('Breakfast');
          if (d.am_snack == 1) meals.push('AM Snack');
          if (d.lunch == 1) meals.push('Lunch');
          if (d.pm_snack == 1) meals.push('PM Snack');
          if (d.dinner == 1) meals.push('Dinner');
        }
        let tpl   = (CA_TEMPLATES[d.template_key] || CA_TEMPLATES[CA_DEFAULT]).label;
        let days  = Math.round((new Date(d.date_departure) - new Date(d.date_arrival)) / 86400000) + 1;
        let on    = d.meal_provided == 1;
        let chips = [['breakfast','Breakfast'],['am_snack','AM Snack'],['lunch','Lunch'],['pm_snack','PM Snack'],['dinner','Dinner']].map(function(m) {
          let yes = on && d[m[0]] == 1;
          return '<span class="cav-chip ' + (yes ? 'on' : 'off') + '"><i class="fas fa-' + (yes ? 'check' : 'minus') + '"></i> ' + m[1] + '</span>';
        }).join('');
        let status = function(yes) {
          return '<span class="badge cav-status ' + (yes ? 'badge-ca-yes' : 'badge-ca-no') + '"><i class="fas fa-' + (yes ? 'check' : 'times') + '"></i> ' + (yes ? 'Provided' : 'Not Provided') + '</span>';
        };
        let created = d.created_at ? 'Issued ' + fmtDate(String(d.created_at).substr(0, 10)) : '';
        $('#viewCaContent').html(
          '<div class="cav-top"><div><div class="cav-tn-label">Tracking No.</div><div class="cav-tn">' + esc(d.tracking_no || '—') + '</div></div>' +
            '<div class="cav-tpl"><i class="fas fa-file-word"></i> ' + esc(tpl) + '</div></div>' +
          '<div class="cav-person"><div class="cav-avatar"><i class="fas fa-user"></i></div><div class="cav-person-text">' +
            '<div class="cav-name">' + esc(d.person_name) + '</div>' +
            '<div class="cav-desig">' + esc(d.designation) + '</div>' +
            '<div class="cav-office"><i class="fas fa-building"></i> ' + esc(d.office_from) + '</div></div></div>' +
          '<div class="cav-dates"><div class="cav-date"><span>Arrival</span><strong>' + fmtDate(d.date_arrival) + '</strong></div>' +
            '<div class="cav-arrow"><i class="fas fa-long-arrow-alt-right"></i><small>' + days + ' day' + (days === 1 ? '' : 's') + '</small></div>' +
            '<div class="cav-date"><span>Departure</span><strong>' + fmtDate(d.date_departure) + '</strong></div></div>' +
          '<div class="cav-section"><div class="cav-title"><i class="fas fa-bullseye"></i>Purpose of Travel</div><div class="cav-purpose">' + esc(d.purpose) + '</div></div>' +
          '<div class="cav-grid">' +
            '<div class="cav-section"><div class="cav-title"><i class="fas fa-bed"></i>Lodging</div>' + status(d.lodging_provided == 1) + '</div>' +
            '<div class="cav-section"><div class="cav-title"><i class="fas fa-utensils"></i>Meals</div>' + status(on) + '<div class="cav-chips">' + chips + '</div></div>' +
          '</div>' +
          '<div class="cav-sign"><div><span>Signatory</span><strong>' + esc(d.signatory_name) + '</strong><small>' + esc(d.signatory_title) + '</small></div>' +
            '<div class="cav-created">' + created + '</div></div>'
        );
        $('#viewCaDownloadMenu a').each(function() {
          $(this).attr('href', 'certificate_of_appearance.php?action=download&id=' + d.ca_id + '&template=' + $(this).data('template'));
        });
        $('#viewCaModal').modal('show');
      });
    });

    // Delete
    $(document).on('click', '.delete-btn', function() {
      let id = $(this).data('id');
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (!result.isConfirmed) return;
        $.post('certificate_of_appearance.php', { action: 'delete', ca_id: id }, function(res) {
          if (res.success) Swal.fire('Deleted!', res.message, 'success').then(() => location.reload());
          else Swal.fire('Error!', res.message, 'error');
        }, 'json');
      });
    });
  });
  </script>
</body>
</html>