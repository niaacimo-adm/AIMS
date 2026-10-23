<?php
/* ════════════════════════════════════════════════════════════════════════════════════════
   Travel Order engine  (used by views/travel_order.php)

   Everything that does NOT need the database lives here: the format registry, form
   normalising / validation, filling the Word templates, merging several travel orders
   into one .docx, the HTML print preview and the printable report.

   One saved travel order can be printed in ANY of the three formats:
     standard   TRAVEL-ORDER_STANDARD      "proceed to DESTINATION on DATE to PURPOSE"
     with_ca    Travel_Order-CA            itinerary table + Certificate of Appearance
     itinerary  ITINERARY-INTERNAL-NEW     Orig / Rev rows + Prepared / Recommended / Approved

   Templates are normal Word files in /public/templates with {{PLACEHOLDERS}}.
   You can restyle them in Word freely - just keep the {{...}} words (type each one in one go).
   ════════════════════════════════════════════════════════════════════════════════════════ */

const TO_DEFAULT_TEMPLATE = 'standard';
const TO_DEFAULT_CODE     = 'ADM';
const TO_MAX_BATCH        = 100;     // most travel orders in one print / download

/* ───────────────────── format registry ─────────────────────
   Add a new format here + drop its .docx (with placeholders) in /public/templates.
   'page' = paper size in pt (w x h) used by the print preview.                       */
function to_templates() {
  static $T = null;
  if ($T !== null) return $T;
  $T = [
    'standard' => [
      'label'    => 'Standard Travel Order',
      'short'    => 'Standard',
      'file'     => 'TO_STANDARD_READY.docx',
      'desc'     => 'One short paragraph: "proceed to DESTINATION on DATE to PURPOSE". Best for a single trip.',
      'approver' => ['Engr. MARK CLOYD G.SO', 'Acting Division Manager'],
      'page'     => ['w' => 595.3, 'h' => 841.9, 'name' => 'A4 portrait'],
    ],
    'with_ca' => [
      'label'    => 'Travel Order with Certificate of Appearance',
      'short'    => 'With CA',
      'file'     => 'TO_CA_READY.docx',
      'desc'     => 'Itinerary table (date / location / purpose / duration), expected output, guard-on-duty lines and a Certificate of Appearance at the bottom.',
      'approver' => ['Engr. MARK CLOYD G. SO, MPA', 'Acting Division Manager'],
      'page'     => ['w' => 612, 'h' => 936, 'name' => '8.5 x 13 in portrait'],
    ],
    'itinerary' => [
      'label'    => 'Itinerary of Travel (Internal)',
      'short'    => 'Itinerary',
      'file'     => 'TO_ITINERARY_READY.docx',
      'desc'     => 'Landscape itinerary with an Original and a Revised line for every date, plus Prepared / Recommended / Approved by.',
      'approver' => ['Engr. MARK CLOYD G. SO, MPA', 'Acting Division Manager'],
      'page'     => ['w' => 936, 'h' => 612, 'name' => '13 x 8.5 in landscape'],
    ],
  ];
  return $T;
}

function to_template_dir() { return __DIR__ . '/../public/templates/'; }

/* ───────────────────────── small helpers ───────────────────────── */
function to_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function to_upper($s) { return function_exists('mb_strtoupper') ? mb_strtoupper((string)$s, 'UTF-8') : strtoupper((string)$s); }

/* Text that goes INSIDE a <w:t>: XML-escaped, invalid characters dropped, line breaks become <w:br/>. */
function to_text($s) {
  $s = (string)$s;
  $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';
  $s = htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
  return preg_replace('/\r\n|\r|\n/', '</w:t><w:br/><w:t xml:space="preserve">', $s);
}

function to_valid_date($d) {
  if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
  [$y, $m, $dd] = array_map('intval', explode('-', $d));
  return checkdate($m, $dd, $y);
}

function to_fmt_date($d) { return to_valid_date((string)$d) ? date('F j, Y', strtotime($d)) : ''; }

/* "October 21, 2026" | "October 21-23, 2026" | "October 30 - November 2, 2026" | across years */
function to_fmt_range($from, $to) {
  if (!to_valid_date((string)$from)) return '';
  if (!to_valid_date((string)$to) || $to === $from) return to_fmt_date($from);
  $a = strtotime($from); $b = strtotime($to);
  if (date('Y-m', $a) === date('Y-m', $b)) return date('F j', $a) . "\u{2013}" . date('j, Y', $b);
  if (date('Y', $a) === date('Y', $b))     return date('F j', $a) . " \u{2013} " . date('F j, Y', $b);
  return date('F j, Y', $a) . " \u{2013} " . date('F j, Y', $b);
}

/* ["A"] -> A   ["A","B"] -> A and B   ["A","B","C"] -> A, B and C */
function to_join(array $a, $sep = ', ', $last = ' and ') {
  $a = array_values(array_filter(array_unique(array_map('trim', $a)), function ($v) { return $v !== ''; }));
  if (count($a) <= 1) return $a ? $a[0] : '';
  return implode($sep, array_slice($a, 0, -1)) . $last . end($a);
}

function to_number(array $m) { return $m['to_code'] . '-TO-' . $m['to_seq']; }

/* How an employee's name is written on a travel order: FIRST M. LAST EXT   e.g. "Myra Manrique ETCOBANEZ" -> MYRA M. ETCOBANEZ */
function to_employee_name(array $e) {
  $clean = function ($v) { return trim(preg_replace('/\s+/u', ' ', (string)$v)); };
  $first = $clean($e['first_name'] ?? ''); $last = $clean($e['last_name'] ?? ''); $ext = $clean($e['ext_name'] ?? '');
  $mid = $clean($e['middle_name'] ?? '');
  $mi = '';
  if ($mid !== '') $mi = to_upper(function_exists('mb_substr') ? mb_substr($mid, 0, 1, 'UTF-8') : substr($mid, 0, 1)) . '.';
  return to_upper(trim(implode(' ', array_filter([$first, $mi, $last, $ext], function ($v) { return $v !== ''; }))));
}

/* ───────────────────────── form -> normalised record ───────────────────────── */
const TO_ITEM_FIELDS = ['date_from', 'date_to', 'location', 'purpose', 'travel_time', 'duration',
                        'rev_location', 'rev_purpose', 'rev_travel_time', 'rev_duration'];

function to_collect(array $src) {
  $T   = to_templates();
  $key = (string)($src['template_key'] ?? TO_DEFAULT_TEMPLATE);
  if (!isset($T[$key])) $key = TO_DEFAULT_TEMPLATE;
  $s = function ($k) use ($src) { return trim((string)($src[$k] ?? '')); };

  // personnel (parallel arrays p_name[] / p_position[])
  $people = [];
  $names = (array)($src['p_name'] ?? []); $pos = (array)($src['p_position'] ?? []); $eids = (array)($src['p_emp_id'] ?? []);
  foreach ($names as $i => $n) {
    $n = to_upper(trim((string)$n)); $g = trim((string)($pos[$i] ?? ''));
    if ($n === '' && $g === '') continue;                 // untouched blank row
    $eid = (int)($eids[$i] ?? 0);                         // employee.emp_id when picked from the employee list, else none
    $people[] = ['name' => $n, 'position' => $g, 'emp_id' => $eid > 0 ? $eid : null];
  }

  // itinerary rows (parallel arrays i_<field>[])
  $cols = [];
  foreach (TO_ITEM_FIELDS as $f) $cols[$f] = (array)($src['i_' . $f] ?? []);
  $n_rows = 0; foreach ($cols as $c) $n_rows = max($n_rows, count($c));
  $items = [];
  for ($i = 0; $i < $n_rows; $i++) {
    $it = [];
    foreach (TO_ITEM_FIELDS as $f) $it[$f] = trim((string)($cols[$f][$i] ?? ''));
    if (implode('', $it) === '') continue;                // untouched blank row
    $items[] = $it;
  }

  $ap = $T[$key]['approver'];
  $seq = (int)($src['to_seq'] ?? 0);
  return [
    'to_code'          => to_upper(preg_replace('/[^A-Za-z0-9]/', '', $s('to_code'))) ?: TO_DEFAULT_CODE,
    'to_seq'           => $seq,
    'series_year'      => (int)($src['series_year'] ?? date('Y')),
    'to_date'          => $s('to_date'),
    'template_key'     => $key,
    'people'           => $people,
    'items'            => $items,
    'expected_output'  => $s('expected_output'),
    'remarks'          => $s('remarks'),
    'approver_name'    => $s('approver_name')  ?: $ap[0],
    'approver_title'   => $s('approver_title') ?: $ap[1],
    'recommender_name' => $s('recommender_name'),
    'recommender_title'=> $s('recommender_title'),
    'prepared_name'    => $s('prepared_name'),
    'prepared_title'   => $s('prepared_title'),
  ];
}

function to_validate(array $d) {
  if ($d['to_seq'] < 1)                                   return 'Please enter the travel order number.';
  if ($d['series_year'] < 2000 || $d['series_year'] > 2100) return 'Please enter a valid series year.';
  if (!to_valid_date($d['to_date']))                      return 'Please enter the date of the travel order.';
  if (!$d['people'])                                      return 'Please add at least one person to travel.';
  foreach ($d['people'] as $i => $p) {
    if ($p['name'] === '' || $p['position'] === '')       return 'Person ' . ($i + 1) . ': please enter both the name and the position.';
  }
  if (!$d['items'])                                       return 'Please add at least one destination.';
  foreach ($d['items'] as $i => $it) {
    $r = 'Destination ' . ($i + 1) . ': ';
    if (!to_valid_date($it['date_from']))                 return $r . 'please enter the date of travel.';
    if ($it['date_to'] !== '' && !to_valid_date($it['date_to'])) return $r . 'the end date is not valid.';
    if ($it['date_to'] !== '' && $it['date_to'] < $it['date_from']) return $r . 'the end date cannot be earlier than the start date.';
    if ($it['location'] === '' || $it['purpose'] === '')  return $r . 'please enter both the location and the purpose.';
  }
  return null;
}

/* travel_start / travel_end of a record (used for sorting and for the yearly / monthly report) */
function to_span(array $items) {
  $lo = null; $hi = null;
  foreach ($items as $it) {
    $a = $it['date_from']; $b = $it['date_to'] !== '' ? $it['date_to'] : $a;
    if ($lo === null || $a < $lo) $lo = $a;
    if ($hi === null || $b > $hi) $hi = $b;
  }
  return [$lo, $hi];
}

/* database row -> the same normalised shape the fillers use */
function to_model(array $r) {
  $dec = function ($j) { $a = json_decode((string)$j, true); return is_array($a) ? $a : []; };
  $items = [];
  foreach ($dec($r['itinerary'] ?? '') as $it) {
    $n = [];
    foreach (TO_ITEM_FIELDS as $f) $n[$f] = trim((string)($it[$f] ?? ''));
    $items[] = $n;
  }
  $people = [];
  foreach ($dec($r['personnel'] ?? '') as $p) {
    $eid = (int)($p['emp_id'] ?? 0);
    $people[] = ['name' => trim((string)($p['name'] ?? '')), 'position' => trim((string)($p['position'] ?? '')), 'emp_id' => $eid > 0 ? $eid : null];
  }
  $m = $r;
  $m['people'] = $people;
  $m['items']  = $items;
  $m['to_code'] = (string)($r['to_code'] ?? TO_DEFAULT_CODE);
  $m['to_seq']  = (int)($r['to_seq'] ?? 0);
  $m['series_year'] = (int)($r['series_year'] ?? date('Y'));
  foreach (['expected_output', 'remarks', 'recommender_name', 'recommender_title', 'prepared_name', 'prepared_title', 'approver_name', 'approver_title'] as $f) {
    $m[$f] = (string)($r[$f] ?? '');
  }
  $m['template_key'] = isset(to_templates()[$r['template_key'] ?? '']) ? $r['template_key'] : TO_DEFAULT_TEMPLATE;
  return $m;
}

/* Signing officer. A built-in default officer follows the chosen format (Standard writes "G.SO", the others "G. SO, MPA"). */
function to_approver(array $m, array $tpl) {
  $n = $m['approver_name']; $t = $m['approver_title'];
  if ($n === '') return $tpl['approver'];
  foreach (to_templates() as $x) {
    if ($n === $x['approver'][0] && $t === $x['approver'][1]) return $tpl['approver'];
  }
  return [$n, $t !== '' ? $t : $tpl['approver'][1]];
}

/* Prepared by defaults to the first person on the order. */
function to_prepared(array $m) {
  if ($m['prepared_name'] !== '') return [$m['prepared_name'], $m['prepared_title']];
  $p = $m['people'][0] ?? ['name' => '', 'position' => ''];
  return [$p['name'], $p['position']];
}

/* What the Standard sentence says when an order holds several destinations. */
function to_standard_text(array $m) {
  $loc = []; $dates = []; $pur = [];
  foreach ($m['items'] as $it) {
    $loc[]   = preg_replace('/\s*[\r\n]+\s*/', ', ', $it['location']);      // a sentence has no line breaks
    $dates[] = to_fmt_range($it['date_from'], $it['date_to']);
    $pur[]   = preg_replace('/\s*[\r\n]+\s*/', ' ', $it['purpose']);
  }
  return [to_join($loc), to_join($dates), to_join($pur, '; ', '; ')];
}

/* ───────────────────────── Word: repeat + fill ───────────────────────── */
/* Start of the <w:tr> / <w:p> element that encloses position $idx. */
function to_open_pos($xml, $tag, $idx) {
  $head = substr($xml, 0, $idx);
  $a = strrpos($head, '<' . $tag . '>');
  $b = strrpos($head, '<' . $tag . ' ');
  return (int)max($a === false ? -1 : $a, $b === false ? -1 : $b);
}

/* Repeats the block (<w:tr> or <w:p>) that runs from the element holding $start_marker to the one holding $end_marker,
   once per set in $sets (each set = [placeholder => value]).  Zero sets removes the block. */
function to_repeat($xml, $tag, $start_marker, $end_marker, array $sets) {
  $a = strpos($xml, $start_marker);
  if ($a === false) return $xml;
  $b = strpos($xml, $end_marker, $a);
  if ($b === false) return $xml;
  $s = to_open_pos($xml, $tag, $a);
  $close = '</' . $tag . '>';
  $e = strpos($xml, $close, $b);
  if ($s < 0 || $e === false) return $xml;
  $e += strlen($close);
  $proto = substr($xml, $s, $e - $s);
  $out = '';
  foreach ($sets as $set) {
    $r = [];
    foreach ($set as $k => $v) $r[$k] = to_text($v);
    $out .= strtr($proto, $r);
  }
  return substr($xml, 0, $s) . $out . substr($xml, $e);
}

/* Fills the placeholders of ONE travel order inside a chunk of document.xml. */
function to_fill_body($xml, array $m, $key) {
  $T = to_templates(); $tpl = $T[$key];
  [$ap_name, $ap_title] = to_approver($m, $tpl);
  $map = [
    '{{TO_NUMBER}}'     => to_number($m),
    '{{SERIES}}'        => (string)$m['series_year'],
    '{{DATE}}'          => to_fmt_date($m['to_date']),
    '{{APPROVER}}'      => $ap_name,
    '{{APPROVER_TITLE}}'=> $ap_title,
  ];

  $people = [];
  foreach ($m['people'] as $p) $people[] = ['{{P_NAME}}' => $p['name'], '{{P_POSITION}}' => $p['position']];

  if ($key === 'standard') {
    $xml = to_repeat($xml, 'w:tr', '{{P_NAME}}', '{{P_POSITION}}', $people);
    [$dest, $dates, $purpose] = to_standard_text($m);
    $map += ['{{DESTINATION}}' => $dest, '{{TRAVEL_DATES}}' => $dates, '{{PURPOSE}}' => $purpose];

  } elseif ($key === 'with_ca') {
    $xml = to_repeat($xml, 'w:p', '{{P_NAME}}', '{{P_POSITION}}', $people);
    $rows = [];
    foreach ($m['items'] as $it) {
      $rows[] = ['{{I_DATE}}' => to_fmt_range($it['date_from'], $it['date_to']), '{{I_LOCATION}}' => $it['location'],
                 '{{I_PURPOSE}}' => $it['purpose'], '{{I_TRAVEL}}' => $it['travel_time'], '{{I_DURATION}}' => $it['duration']];
    }
    $xml = to_repeat($xml, 'w:tr', '{{I_DATE}}', '{{I_DURATION}}', $rows);
    $names = []; foreach ($m['people'] as $p) $names[] = $p['name'];
    $map += ['{{EXPECTED_OUTPUT}}' => $m['expected_output'], '{{REMARKS}}' => $m['remarks'], '{{CERT_NAMES}}' => to_join($names)];

  } else { // itinerary
    $groups = [];
    foreach ($m['items'] as $it) {
      $groups[] = ['{{I_DATE}}' => to_fmt_range($it['date_from'], $it['date_to']), '{{I_LOCATION}}' => $it['location'],
                   '{{I_PURPOSE}}' => $it['purpose'], '{{I_TRAVEL}}' => $it['travel_time'], '{{I_DURATION}}' => $it['duration'],
                   '{{R_LOCATION}}' => $it['rev_location'], '{{R_PURPOSE}}' => $it['rev_purpose'],
                   '{{R_TRAVEL}}' => $it['rev_travel_time'], '{{R_DURATION}}' => $it['rev_duration']];
    }
    while (count($groups) < 5) {                        // the printed form always has room for 5 dates
      $groups[] = ['{{I_DATE}}' => '', '{{I_LOCATION}}' => '', '{{I_PURPOSE}}' => '', '{{I_TRAVEL}}' => '', '{{I_DURATION}}' => '',
                   '{{R_LOCATION}}' => '', '{{R_PURPOSE}}' => '', '{{R_TRAVEL}}' => '', '{{R_DURATION}}' => ''];
    }
    $xml = to_repeat($xml, 'w:tr', '{{I_DATE}}', '{{R_DURATION}}', $groups);
    [$pb, $pp] = to_prepared($m);
    $map += ['{{PREPARED_BY}}' => $pb, '{{PREPARED_POS}}' => $pp,
             '{{RECOMMENDED_BY}}' => $m['recommender_name'], '{{RECOMMENDED_TITLE}}' => $m['recommender_title']];
  }

  $r = [];
  foreach ($map as $k => $v) $r[$k] = to_text($v);
  return strtr($xml, $r);
}

/* Resolves which format to build. $key = null -> the format saved with the first record. */
function to_resolve_template(array $first, $key = null) {
  $T = to_templates();
  if (!$key || !isset($T[$key])) $key = $first['template_key'] ?? TO_DEFAULT_TEMPLATE;
  if (!isset($T[$key])) $key = TO_DEFAULT_TEMPLATE;
  $file = to_template_dir() . $T[$key]['file'];
  if (!is_file($file))          { http_response_code(500); exit('Template file not found: ' . to_h($T[$key]['file'])); }
  if (!class_exists('ZipArchive')) { http_response_code(500); exit('PHP ZipArchive extension is not enabled.'); }
  return [$key, $T[$key], $file];
}

/* Ends one travel order's pages with a "next page" section break that repeats the template's page setup. */
function to_end_section($part, $sect) {
  $pe = strrpos($part, '</w:p>');
  if ($pe === false || trim(substr($part, $pe + 6)) !== '') {
    return $part . '<w:p><w:pPr>' . $sect . '</w:pPr></w:p>';            // body ends in a table etc.
  }
  $before = substr($part, 0, $pe);
  $ps = max((int)strrpos($before, '<w:p '), (int)strrpos($before, '<w:p>'));
  $open_end = strpos($part, '>', $ps) + 1;                                // end of the <w:p ...> tag
  if (substr($part, $open_end, 8) === '<w:pPr/>') {
    return substr($part, 0, $open_end) . '<w:pPr>' . $sect . '</w:pPr>' . substr($part, $open_end + 8);
  }
  if (substr($part, $open_end, 7) === '<w:pPr>') {
    $close = strpos($part, '</w:pPr>', $open_end);
    return substr($part, 0, $close) . $sect . substr($part, $close);
  }
  return substr($part, 0, $open_end) . '<w:pPr>' . $sect . '</w:pPr>' . substr($part, $open_end);
}

/* Numbered cells ("1.", "2.") would carry on counting from one travel order into the next; give every copy
   after the first its own list that starts again at 1.  Returns the new numbering.xml (or null if untouched). */
function to_fresh_lists($body, &$numbering) {
  if (!preg_match_all('/<w:numId w:val="(\d+)"\/>/', $body, $mm)) return $body;
  $ids = array_values(array_unique($mm[1]));
  $max = 0;
  if (preg_match_all('/<w:num w:numId="(\d+)"/', $numbering, $nn)) $max = max(array_map('intval', $nn[1]));
  $new = ''; $swap = [];
  foreach ($ids as $id) {
    if (!preg_match('/<w:num w:numId="' . $id . '"[^>]*>\s*<w:abstractNumId w:val="(\d+)"\/>/', $numbering, $am)) continue;
    $nid = ++$max;
    $new .= '<w:num w:numId="' . $nid . '"><w:abstractNumId w:val="' . $am[1] . '"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num>';
    $swap['<w:numId w:val="' . $id . '"/>'] = '<w:numId w:val="' . $nid . '"/>';
  }
  if ($new === '') return $body;
  $pos = strpos($numbering, '<w:numIdMacAtCleanup');
  if ($pos === false) $pos = strrpos($numbering, '</w:numbering>');
  $numbering = substr($numbering, 0, $pos) . $new . substr($numbering, $pos);
  return strtr($body, $swap);
}

/* Builds ONE .docx holding every travel order (each starts on a new page) in the chosen format.
   Returns [temp path, download filename]. */
function to_build_docx(array $models, $key = null) {
  [$key, $tpl, $template] = to_resolve_template($models[0], $key);

  $tmp = tempnam(sys_get_temp_dir(), 'to_');
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
  $body = substr($xml, $bs, $ss - $bs);      // the travel order itself
  $sect = substr($xml, $ss, $se - $ss);      // the template's page setup
  $tail = substr($xml, $se);                 // </w:body></w:document>

  $numbering = $zip->getFromName('word/numbering.xml');
  $numbering_changed = false;
  $out = ''; $img_id = 1000; $last = count($models) - 1;
  foreach ($models as $i => $m) {
    $part = to_fill_body($body, $m, $key);
    if ($i > 0) {
      // keep ids unique across the repeated copies
      $part = preg_replace('/ (?:w14:(?:paraId|textId)|wp14:(?:anchorId|editId))="[^"]*"/', '', $part);
      $part = preg_replace_callback('/<wp:docPr id="\d+"/', function () use (&$img_id) { return '<wp:docPr id="' . (++$img_id) . '"'; }, $part);
      if ($numbering !== false) {
        $before = $numbering;
        $part = to_fresh_lists($part, $numbering);
        if ($numbering !== $before) $numbering_changed = true;
      }
    }
    $out .= ($i < $last) ? to_end_section($part, $sect) : $part;
  }

  $zip->addFromString('word/document.xml', $head . $out . $sect . $tail);
  if ($numbering_changed) $zip->addFromString('word/numbering.xml', $numbering);
  $zip->close();

  $suffix = ['standard' => '', 'with_ca' => '_CA', 'itinerary' => '_Itinerary'][$key];
  if (count($models) === 1) {
    $m = $models[0];
    $filename = 'Travel_Order_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', to_number($m)) . '_s' . $m['series_year'] . $suffix . '.docx';
  } else {
    $filename = 'Travel_Orders_' . count($models) . 'pcs_' . date('Ymd') . $suffix . '.docx';
  }
  return [$tmp, $filename];
}

/* Streams a filled .docx to the browser. */
function to_download_docx(array $models, $key = null) {
  [$tmp, $filename] = to_build_docx($models, $key);
  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . filesize($tmp));
  header('Cache-Control: no-cache, must-revalidate');
  readfile($tmp);
  unlink($tmp);
  exit();
}

/* ════════════════════════ print preview (HTML, ready to print) ════════════════════════
   Needs nothing installed on the server. The letterhead (logos, swoosh, address footer) is a picture of the
   template's own header/footer: public/templates/to_letterhead_<format>.png. The text is laid out in HTML on top
   of it, one travel order per page, and printed by the browser (Margins: None, Scale: 100%).
   Measurements below were taken from the Word files themselves (pt).                                       */
function to_letterhead_file($key) {
  return to_template_dir() . 'to_letterhead_' . preg_replace('/[^a-z0-9_]/i', '', (string)$key) . '.png';
}

function to_nl($s) { return nl2br(to_h($s), false); }

/* ---- Standard ---- */
function to_prev_standard(array $m, array $tpl) {
  [$ap, $apt] = to_approver($m, $tpl);
  [$dest, $dates, $purpose] = to_standard_text($m);
  $rows = '';
  foreach ($m['people'] as $p) $rows .= '<tr><td class="b">' . to_h($p['name']) . '</td><td class="i">' . to_h($p['position']) . '</td></tr>';
  return
    '<div class="s-title">TRAVEL ORDER</div>'
  . '<div class="s-no">' . to_h(to_number($m)) . ' s. ' . (int)$m['series_year'] . '</div>'
  . '<div class="s-date">' . to_h(to_fmt_date($m['to_date'])) . '</div>'
  . '<div class="s-memo">MEMORANDUM TO:</div>'
  . '<table class="s-ppl">' . $rows . '</table>'
  . '<p class="s-p1">You are hereby directed to proceed to <b>' . to_h($dest) . '</b> on <b>' . to_h($dates) . '</b> to <b>' . to_h($purpose)
  .   '</b> and expected to return to the office on the next working day.</p>'
  . '<p class="s-p2">You shall use land/air transportation and your travelling expenses shall be charged to available fund, subject to the usual accounting and auditing rules and regulations.</p>'
  . '<div class="s-appr">APPROVED:</div>'
  . '<div class="s-an b">' . to_h($ap) . '</div><div class="s-at i">' . to_h($apt) . '</div>';
}

/* ---- Travel Order + Certificate of Appearance ---- */
function to_prev_ca(array $m, array $tpl) {
  [$ap, $apt] = to_approver($m, $tpl);
  $ppl = ''; $names = [];
  foreach ($m['people'] as $p) { $ppl .= '<div class="c-person"><b>' . to_h($p['name']) . '</b>, <i>' . to_h($p['position']) . '</i></div>'; $names[] = $p['name']; }
  $rows = ''; $n = 0;
  foreach ($m['items'] as $it) {
    $n++;
    $rows .= '<tr><td>' . to_h(to_fmt_range($it['date_from'], $it['date_to'])) . '</td>'
           . '<td class="li"><span class="no">' . $n . '.</span><span class="tx">' . to_nl($it['location']) . '</span></td>'
           . '<td class="li"><span class="no">' . $n . '.</span><span class="tx">' . to_nl($it['purpose']) . '</span></td>'
           . '<td class="ct">' . to_nl($it['travel_time']) . '</td><td class="ct">' . to_nl($it['duration']) . '</td></tr>';
  }
  $box = function ($l1, $l2) { return '<div class="c-box"><div>_____________ ' . $l1 . '</div><div>_____________ ' . $l2 . '</div></div>'; };
  return
    '<div class="c-title">TRAVEL ORDER</div>'
  . '<div class="c-no">' . to_h(to_number($m)) . ' S. ' . (int)$m['series_year'] . '</div>'
  . '<div class="c-date">' . to_h(to_fmt_date($m['to_date'])) . '</div>'
  . '<div class="c-memo">MEMORANDUM TO:</div>' . $ppl
  . '<div class="c-office">This Office</div>'
  . '<p class="c-dir">You are hereby directed to proceed to the below stated locations with the following purposes, to wit;</p>'
  . '<table class="c-tbl"><colgroup><col style="width:99.25pt"><col style="width:139pt"><col style="width:151.35pt"><col style="width:85.3pt"><col style="width:63.8pt"></colgroup>'
  . '<thead><tr class="h1"><th rowspan="2">DATE</th><th rowspan="2">LOCATION</th><th rowspan="2">PURPOSE</th><th colspan="2">ESTIMATED DURATION OF TRAVEL</th></tr>'
  . '<tr class="h2"><th class="sm">TRAVEL TIME (TO &amp; FROM OFFICIAL STATION)</th><th>DURATION OF PURPOSE</th></tr></thead>'
  . '<tbody>' . $rows
  . '<tr class="lab"><td colspan="2">Expected Output:</td><td colspan="3">Remarks:</td></tr>'
  . '<tr class="val"><td colspan="2">' . to_nl($m['expected_output']) . '</td><td colspan="3">' . to_nl($m['remarks']) . '</td></tr></tbody></table>'
  . '<p class="c-use">You shall use land/air transportation and you\'re travelling expenses shall be charged to any available funds of this office subject to the usual accounting and auditing rules and regulations.</p>'
  . '<div class="c-appr">APPROVED:</div><div class="c-an b">' . to_h($ap) . '</div><div class="c-at">' . to_h($apt) . '</div>'
  . '<div class="c-boxes">' . $box('Departure Time (From ACIMO)', 'Guard On Duty') . $box('Arrival Time (Returned to ACIMO)', 'Guard On Duty') . '</div>'
  . '<div class="c-ctitle">CERTIFICATE OF APPEARANCE</div>'
  . '<div class="c-twmc">TO WHOM IT MAY CONCERN:</div>'
  . '<p class="c-cert">This is to certify that <b>' . to_h(to_join($names)) . '</b> from the office of the <b><i>National Irrigation Administration Albay/Catanduanes IMO</i></b>, appeared and conducted official business in accordance with the purpose/s stated in the travel order.</p>'
  . '<div class="c-arr"><span class="l">DATE OF ARRIVAL:</span><span class="ln"></span></div>'
  . '<div class="c-arr"><span class="l">DATE OF DEPARTURE:</span><span class="ln"></span></div>'
  . '<div class="c-sigs">' . str_repeat('<div><div class="sl"></div><div class="cap">(Signature Over Printed Name)</div><div class="dt">Date:<span class="dl"></span></div></div>', 3) . '</div>'
  . '<div class="c-rem">Remarks:</div><div class="c-remln"></div>';
}

/* ---- Itinerary of Travel ---- */
function to_prev_itinerary(array $m, array $tpl) {
  [$ap, $apt] = to_approver($m, $tpl);
  [$pb, $pp] = to_prepared($m);
  $items = $m['items'];
  while (count($items) < 5) $items[] = array_fill_keys(TO_ITEM_FIELDS, '');
  $rows = '';
  foreach ($items as $it) {
    $rows .= '<tr class="o"><td rowspan="2" class="dt">' . to_h(to_fmt_range($it['date_from'], $it['date_to'])) . '</td><td class="ro">Orig</td>'
           . '<td class="lc">' . to_nl($it['location']) . '</td><td class="ct">' . to_nl($it['purpose']) . '</td><td class="ct">' . to_nl($it['travel_time'])
           . '</td><td class="ct">' . to_nl($it['duration']) . '</td><td></td></tr>'
           . '<tr class="r"><td class="ro">Rev (if)</td><td class="lc">' . to_nl($it['rev_location']) . '</td><td class="ct">' . to_nl($it['rev_purpose'])
           . '</td><td class="ct">' . to_nl($it['rev_travel_time']) . '</td><td class="ct">' . to_nl($it['rev_duration']) . '</td><td></td></tr>';
  }
  return
    '<div class="i-title">ITINERARY OF TRAVEL</div>'
  . '<div class="i-no">' . to_h(to_number($m)) . ' s. ' . (int)$m['series_year'] . '</div>'
  . '<table class="i-tbl"><colgroup><col style="width:95.35pt"><col style="width:48.8pt"><col style="width:188.75pt"><col style="width:212.6pt"><col style="width:70.9pt"><col style="width:63.75pt"><col style="width:134.7pt"></colgroup>'
  . '<thead><tr class="h1"><th rowspan="2">DATE</th><th rowspan="2" colspan="2">LOCATION</th><th rowspan="2">PURPOSE</th><th colspan="2">ESTIMATED DURATION</th><th>APPEARANCE</th></tr>'
  . '<tr class="h2"><th>TRAVEL TIME<br>(TO &amp; FROM OFFICIAL STATION)</th><th>DURATION OF PURPOSE</th><th>(SIGNATURE OVER PRINTED NAME)</th></tr></thead>'
  . '<tbody>' . $rows . '</tbody></table>'
  . '<div class="i-note">*** initial of Unit Head required at Revised Row for revised location of travel</div>'
  . '<div class="i-sig"><div><div>Prepared by:</div><div class="sp"></div><div class="b">' . to_h($pb) . '</div><div>' . to_h($pp) . '</div></div>'
  . '<div><div>Recommended by:</div><div class="sp"></div><div class="b">' . to_h($m['recommender_name']) . '</div><div>' . to_h($m['recommender_title']) . '</div></div>'
  . '<div><div>Approved by:</div><div class="sp"></div><div class="b">' . to_h($ap) . '</div><div>' . to_h($apt) . '</div></div></div>';
}

/* Page geometry for the three formats: body left / top (pt), text width, and the lowest point (maxy) text may reach
   before the preview shrinks it a little (a very long order would otherwise run over the footer). */
function to_preview_geom() {
  return [
    'standard'  => ['left' => 63.9,  'top' => 99.5,  'bw' => 455.0, 'maxy' => 735],
    'with_ca'   => ['left' => 72.1,  'top' => 95.0,  'bw' => 467.9, 'maxy' => 828],
    'itinerary' => ['left' => 42.55, 'top' => 113.0, 'bw' => 851.0, 'maxy' => 535],
  ];
}

function to_preview_css() {
  return <<<'CSS'
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body { background: #525659; font-family: Cambria, Caladea, 'Times New Roman', serif; color: #000; }
  .note { background: #fff3cd; color: #664d03; font: 13px Arial, sans-serif; padding: 8px 14px; text-align: center; }
  .page { position: relative; margin: 14px auto; background: #fff; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.45); page-break-after: always; break-after: page; }
  .page:last-child { page-break-after: auto; break-after: auto; }
  .bg { position: absolute; left: 0; top: 0; width: 100%; height: 100%; }
  .body { position: absolute; transform-origin: 0 0; }
  .body :where(p, div) { margin: 0; }
  .b { font-weight: bold; } .i { font-style: italic; }
  /* ── Standard (11 pt) ── */
  .pg-standard .body { font-size: 11pt; line-height: 12.7pt; }
  .s-title { text-align: center; font-weight: bold; font-size: 18pt; line-height: 21.8pt; }
  .s-no    { text-align: center; font-weight: bold; line-height: 14.3pt; }
  .s-date  { text-align: right; line-height: 18.95pt; margin-top: 15pt; }
  .s-memo  { line-height: 18.95pt; }
  .s-ppl   { border-collapse: collapse; margin-top: 2.3pt; margin-bottom: 12pt; }
  .s-ppl td { padding: 0 5.4pt; vertical-align: bottom; line-height: 14.3pt; }
  .s-ppl td:first-child { width: 158.85pt; } .s-ppl td:last-child { width: 242.75pt; }
  .s-p1 { line-height: 18.95pt; text-align: justify; padding-left: 14.85pt; text-indent: 45pt; margin-top: 0; }
  .s-p2 { line-height: 18.95pt; text-align: justify; padding-left: 19.25pt; text-indent: 27pt; margin-top: 18.95pt !important; }
  .s-appr { text-align: center; line-height: 18.95pt; margin-top: 56.9pt !important; }
  .s-an { text-align: center; margin-top: 52.9pt !important; line-height: 12.7pt; }
  .s-at { text-align: center; line-height: 12.7pt; }
  /* ── With Certificate of Appearance (10 pt) ── */
  .pg-with_ca .body { font-size: 10pt; line-height: 11.7pt; }
  .c-title { text-align: center; font-weight: bold; font-size: 12pt; line-height: 14.06pt; }
  .c-no    { text-align: center; margin-top: 1pt !important; }
  .c-date  { text-align: right; text-decoration: underline; margin-top: .8pt !important; }
  .c-memo  { font-weight: bold; margin-top: 8.7pt !important; }
  .c-person { font-size: 11pt; line-height: 12.9pt; } .c-memo + .c-person { margin-top: 8.5pt !important; }
  .c-office { margin-top: 14.5pt !important; margin-top: 14.5pt; }
  .c-dir  { margin-top: 10.3pt !important; line-height: 17.55pt; text-indent: 36pt; text-align: justify; }
  .c-tbl  { border-collapse: collapse; table-layout: fixed; width: 538.7pt; margin: .5pt 0 0 -35.7pt; font-size: 10pt; }
  .c-tbl th, .c-tbl td { border: .5pt solid #000; padding: 0 5.4pt; line-height: 11.5pt; vertical-align: top; overflow-wrap: anywhere; }
  .c-tbl th { background: #a6a6a6; text-align: center; font-weight: bold; vertical-align: middle; }
  .c-tbl .h1 th { height: 27pt; } .c-tbl .h2 th { height: 33.5pt; font-size: 8.5pt; line-height: 10pt; padding: 0 2pt; }
  .c-tbl .h2 th.sm { font-size: 7.3pt; }
  .c-tbl tbody tr:not(.lab):not(.val) td { height: 25pt; }
  .c-tbl td.ct { text-align: center; vertical-align: middle; }
  .c-tbl td.li { display: table-cell; padding-left: 5.4pt; }
  .c-tbl td.li .no { display: inline-block; margin-left: 18pt; width: 18pt; vertical-align: top; } .c-tbl td.li .tx { display: inline-block; width: calc(100% - 38pt); vertical-align: top; }
  .c-tbl tr.lab td { background: #d0cece; text-align: center; font-weight: bold; vertical-align: middle; height: 12.1pt; }
  .c-tbl tr.val td { vertical-align: middle; height: 12.1pt; }
  .c-use  { margin-top: 10.8pt !important; text-indent: 36pt; text-align: justify; }
  .c-appr { text-align: center; margin-top: 12.2pt !important; }
  .c-an   { text-align: center; margin-top: 25.5pt !important; line-height: 12.4pt; } .c-at { text-align: center; line-height: 12.4pt; }
  .c-boxes { display: flex; justify-content: space-between; margin-top: 45.1pt !important; border-bottom: 1.5pt solid #000; padding-bottom: 6pt; }
  .c-box  { font-size: 8pt; font-weight: bold; line-height: 9.2pt; margin-left: -1.3pt; } .c-boxes .c-box:last-child { width: 173.3pt; margin-left: 0; }
  .c-ctitle { text-align: center; font-weight: bold; margin-top: 25.8pt !important; }
  .c-twmc { margin-top: 13pt !important; line-height: 12.4pt; }
  .c-cert { margin-top: 13.2pt !important; line-height: 12.45pt; text-align: justify; }
  .c-arr  { line-height: 12.4pt; } .c-cert + .c-arr { margin-top: 12.5pt !important; }
  .c-arr .l { display: inline-block; width: 108pt; } .c-arr .ln { display: inline-block; width: 89pt; border-bottom: .6pt solid #000; height: 9.5pt; }
  .c-sigs { display: flex; margin: 15pt 0 0 0; } .c-sigs > div { width: 133pt; text-align: center; } .c-sigs > div:nth-child(2) { width: 144pt; } .c-sigs > div:nth-child(3) { width: 155.6pt; }
  .c-sigs .sl { width: 107pt; height: 11.5pt; margin: 0 auto; border-bottom: .6pt solid #000; }
  .c-sigs .cap { margin-top: 7.7pt; line-height: 12.4pt; } .c-sigs .dt { margin-top: 7pt; text-align: left; padding-left: 10pt; line-height: 12.4pt; }
  .c-sigs .dl { display: inline-block; width: 66pt; border-bottom: .6pt solid #000; height: 9pt; vertical-align: baseline; }
  .c-rem  { font-weight: bold; margin-top: 7.5pt !important; line-height: 12.4pt; } .c-remln { border-bottom: .6pt solid #000; height: 22pt; }
  /* ── Itinerary (10 pt, landscape) ── */
  .pg-itinerary .body { font-size: 10pt; line-height: 11.7pt; }
  .i-title { text-align: center; font-weight: bold; font-size: 11pt; line-height: 13.6pt; } .i-no { text-align: center; font-size: 11pt; line-height: 13.6pt; }
  .i-tbl { border-collapse: collapse; table-layout: fixed; width: 815pt; margin-top: 7.4pt; }
  .i-tbl th, .i-tbl td { border: .5pt solid #000; padding: 0 5.4pt; vertical-align: middle; overflow-wrap: anywhere; line-height: 11.7pt; }
  .i-tbl th { background: #d9d9d9; text-align: center; font-weight: bold; font-size: 9pt; line-height: 10pt; padding: 0 2pt; }
  .i-tbl .h1 th { height: 15.3pt; } .i-tbl .h2 th { height: 44pt; font-size: 7.5pt; line-height: 10.5pt; }
  .i-tbl tbody td { height: 21.25pt; } .i-tbl td.ct { text-align: center; } .i-tbl td.dt { vertical-align: middle; }
  .i-note { font-size: 8pt; font-style: italic; line-height: 10.5pt; }
  .i-sig  { display: flex; margin-top: 18.5pt; } .i-sig > div { width: 283.65pt; padding-right: 5.7pt; line-height: 12.8pt; } .i-sig .sp { height: 28pt; }
  @media print { body { background: #fff; } .note { display: none; } .page { margin: 0; box-shadow: none; } }
CSS;
}

/* Streams the whole print page (one travel order per page). $key = null -> each order in its saved format.
   $lh_url = function ($format_key) returning the URL of that format's letterhead picture. */
function to_preview_page(array $models, $key, callable $lh_url) {
  $T = to_templates(); $G = to_preview_geom();
  $force = ($key && isset($T[$key])) ? $key : null;
  $pages = ''; $used = []; $missing = [];
  foreach ($models as $m) {
    $k = $force ?: $m['template_key'];
    $tpl = $T[$k]; $g = $G[$k]; $used[$k] = true;
    $has_lh = is_file(to_letterhead_file($k));
    if (!$has_lh) $missing[$k] = true;
    $html = $k === 'standard' ? to_prev_standard($m, $tpl) : ($k === 'with_ca' ? to_prev_ca($m, $tpl) : to_prev_itinerary($m, $tpl));
    $pages .= '<div class="page pg-' . $k . '" style="width:' . $tpl['page']['w'] . 'pt;height:' . $tpl['page']['h'] . 'pt;page:' . $k . '">'
            . ($has_lh ? '<img class="bg" src="' . to_h($lh_url($k)) . '" alt="">' : '')
            . '<div class="body" style="left:' . $g['left'] . 'pt;top:' . $g['top'] . 'pt;width:' . $g['bw'] . 'pt" data-bw="' . $g['bw'] . '" data-maxy="' . $g['maxy'] . '">' . $html . '</div></div>';
  }
  $atpage = '';
  foreach (array_keys($used) as $k) $atpage .= '@page ' . $k . ' { size: ' . $T[$k]['page']['w'] . 'pt ' . $T[$k]['page']['h'] . 'pt; margin: 0; }' . "\n";

  header('Content-Type: text/html; charset=utf-8');
  header('X-Frame-Options: SAMEORIGIN');
  header('Cache-Control: no-cache, must-revalidate');
  ?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Travel Order</title>
<style>
<?= $atpage ?><?= to_preview_css() ?>
</style></head><body>
<?php if ($missing): ?><div class="note">The letterhead picture for <?= to_h(implode(', ', array_keys($missing))) ?> is not installed, so it is not shown here (and will not print). See public/templates/to_letterhead_*.png</div><?php endif; ?>
<?= $pages ?>
<script>
/* Long orders (many people / destinations) would run into the footer: shrink that page's text a little (down to 70%)
   until it ends above the footer. Short orders are left untouched. The Word file itself continues on a 2nd page instead. */
(function () {
  var PX = 96 / 72;
  function fit() {
    document.querySelectorAll('.page').forEach(function (pg) {
      var b = pg.querySelector('.body'); if (!b) return;
      var bw = parseFloat(b.dataset.bw), limit = parseFloat(b.dataset.maxy) * PX;
      b.style.transform = ''; b.style.width = bw + 'pt';
      var top = pg.getBoundingClientRect().top;
      for (var k = 1; k >= 0.7; k -= 0.02) {
        b.style.transform = k < 1 ? 'scale(' + k + ')' : '';
        b.style.width = (bw / k) + 'pt';
        if (b.getBoundingClientRect().bottom - top <= limit) break;
      }
    });
  }
  /* on screen only: scale each page down to the window width (never up); printing always uses 100% */
  function zoomToWidth() {
    document.querySelectorAll('.page').forEach(function (pg) {
      pg.style.zoom = '';
      var w = pg.offsetWidth, avail = document.documentElement.clientWidth - 28;
      if (avail > 0 && w > avail) pg.style.zoom = (avail / w).toFixed(4);
    });
  }
  function screenLayout() { document.querySelectorAll('.page').forEach(function (p) { p.style.zoom = ''; }); fit(); zoomToWidth(); }
  window.addEventListener('load', screenLayout);
  window.addEventListener('resize', zoomToWidth);
  window.addEventListener('beforeprint', function () { document.querySelectorAll('.page').forEach(function (p) { p.style.zoom = ''; }); fit(); });
  window.addEventListener('afterprint', zoomToWidth);
})();
</script>
</body></html>
<?php
  exit();
}

/* ════════════════════════ printable report (yearly / monthly) ════════════════════════ */
function to_report_page(array $models, $period) {
  $T = to_templates();
  header('Content-Type: text/html; charset=utf-8');
  ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Travel Order Report - <?= to_h($period) ?></title>
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
  .head .small { font-size: 9pt; } .head .agency { font-size: 12pt; font-weight: 700; }
  .head .title { font-size: 12pt; font-weight: 700; margin-top: 6px; text-decoration: underline; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
  th { background: #eee; text-align: center; font-size: 8pt; text-transform: uppercase; vertical-align: middle; }
  td.nw { white-space: nowrap; }
  td .ln { display: block; }
  thead { display: table-header-group; } tr { page-break-inside: avoid; }
  .foot { margin-top: 8px; font-size: 8.5pt; display: flex; justify-content: space-between; }
  @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; padding: 0; width: auto; box-shadow: none; }
    th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
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
  <span><?= count($models) ?> travel order<?= count($models) === 1 ? '' : 's' ?> &mdash; <?= to_h($period) ?></span>
</div>
<div class="sheet">
  <div class="head">
    <div class="small">Republic of the Philippines</div>
    <div class="small">OFFICE OF THE PRESIDENT</div>
    <div class="agency">NATIONAL IRRIGATION ADMINISTRATION</div>
    <div class="small">ALBAY-CATANDUANES IRRIGATION MANAGEMENT OFFICE</div>
    <div class="title">REPORT ON TRAVEL ORDERS &mdash; <?= to_h($period) ?></div>
  </div>
  <table>
    <colgroup><col style="width:9%"><col style="width:8%"><col style="width:17%"><col style="width:17%"><col style="width:11%"><col><col style="width:8%"></colgroup>
    <thead><tr><th>Travel Order No.</th><th>Date</th><th>Name / Position</th><th>Destination</th><th>Travel Dates</th><th>Purpose</th><th>Format</th></tr></thead>
    <tbody>
    <?php if (!$models): ?><tr><td colspan="7" style="text-align:center;padding:14px;">No travel orders found for <?= to_h($period) ?>.</td></tr><?php endif; ?>
    <?php foreach ($models as $m): ?>
      <tr>
        <td class="nw"><strong><?= to_h(to_number($m)) ?></strong><br>s. <?= (int)$m['series_year'] ?></td>
        <td class="nw"><?= to_h(date('M d, Y', strtotime($m['to_date']))) ?></td>
        <td><?php foreach ($m['people'] as $p): ?><span class="ln"><b><?= to_h($p['name']) ?></b>, <i><?= to_h($p['position']) ?></i></span><?php endforeach; ?></td>
        <td><?php foreach ($m['items'] as $it): ?><span class="ln"><?= to_h($it['location']) ?></span><?php endforeach; ?></td>
        <td><?php foreach ($m['items'] as $it): ?><span class="ln"><?= to_h(to_fmt_range($it['date_from'], $it['date_to'])) ?></span><?php endforeach; ?></td>
        <td><?php foreach ($m['items'] as $it): ?><span class="ln"><?= to_h($it['purpose']) ?></span><?php endforeach; ?></td>
        <td><?= to_h($T[$m['template_key']]['short']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="foot"><span>Series per travel order number</span><span>Total: <strong><?= count($models) ?></strong> &nbsp;|&nbsp; Printed: <?= date('F d, Y') ?></span></div>
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