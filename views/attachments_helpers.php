<?php
/**
 * Shared helpers for the Attachments Monitoring module
 * (attachments_monitoring.php, attachments_export.php, attachments_import.php).
 *
 * Place this file in the same folder as those three files.
 */

// Appointment statuses that are monitored (same list the monitoring page uses):
// Casual-SP, Casual-PC, Regular, CARP Co-Terminus, Permanent, Temp-Regular, CARP-Contractual
const AM_TARGET_APPOINTMENT_STATUSES = [5, 6, 8, 9, 10, 11, 39];

// Stored value => label written to the Excel template
const AM_STATUS_LABELS = [
    'NO ATTACHMENTS'      => 'No Attachment',
    'COMPLETE'            => 'Complete',
    'COMPLETE AND LATE'   => 'Complete and Late',
    'LACKING INFORMATION' => 'Lacking Information',
    'FOR REVIEW'          => 'For Review',
];

const AM_COMPLIANCE_LABELS = [
    'ON-TIME COMPLIANCE' => 'On-Time Compliance',
    'LATE COMPLIANCE'    => 'Late Compliance',
];

const AM_FILING_VALUES = ['FORWARDED', 'NOT FORWARDED'];

/* -------------------------------------------------------------------------
 * Value normalisation
 * Each returns the canonical stored value, $default when blank, or false
 * when the value is not recognised.
 * ---------------------------------------------------------------------- */

function am_clean_text($v)
{
    return trim(preg_replace('/\s+/u', ' ', (string)$v));
}

function am_normalize_status($v, $default = null)
{
    $k = strtoupper(am_clean_text($v));
    if ($k === '') {
        return $default;
    }
    if ($k === 'NO ATTACHMENT') {
        $k = 'NO ATTACHMENTS';
    }
    return isset(AM_STATUS_LABELS[$k]) ? $k : false;
}

function am_normalize_compliance($v)
{
    $k = strtolower(am_clean_text($v));
    if ($k === '') {
        return null;
    }
    if (strpos($k, 'late') !== false) {
        return 'LATE COMPLIANCE';
    }
    if (strpos($k, 'time') !== false || strpos($k, 'advance') !== false) {
        return 'ON-TIME COMPLIANCE';
    }
    return false;
}

function am_normalize_filing($v)
{
    $k = strtoupper(am_clean_text($v));
    if ($k === '') {
        return null;
    }
    return in_array($k, AM_FILING_VALUES, true) ? $k : false;
}

/** Date from a form field (YYYY-MM-DD) => 'Y-m-d' or null. */
function am_post_date($v)
{
    $v = trim((string)$v);
    if ($v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

/** Status coming from a form <select>, falling back to $default when invalid. */
function am_post_status($v, $default)
{
    $n = am_normalize_status($v, $default);
    return $n === false ? $default : $n;
}

function am_post_compliance($v)
{
    $n = am_normalize_compliance($v);
    return $n === false ? null : $n;
}

function am_post_filing($v)
{
    $n = am_normalize_filing($v);
    return ($n === null || $n === false) ? 'NOT FORWARDED' : $n;
}

/* -------------------------------------------------------------------------
 * Payroll period helpers
 * ---------------------------------------------------------------------- */

function am_month_number($name)
{
    static $map = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
                   'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
    return $map[strtolower(substr($name, 0, 3))] ?? null;
}

/**
 * Parse a period out of free text. Understands:
 *   "10/1-15/2026", "9/16-10/15/2026"          (FOR PAYROLL header)
 *   "October 1-15, 2026", "Oct 1 - Nov 15, 2026"  (title / old template)
 *   "Oct 1 - Oct 15"  (no year -> uses $defaultYear, otherwise null)
 * Returns ['Y-m-d' start, 'Y-m-d' end] or null.
 */
function am_parse_period_text($text, $defaultYear = null)
{
    $text = am_clean_text($text);
    if ($text === '') {
        return null;
    }

    // Numeric: m/d-d/Y  or  m/d-m/d/Y
    if (preg_match('#(\d{1,2})/(\d{1,2})\s*-\s*(?:(\d{1,2})/)?(\d{1,2})/(\d{4})#', $text, $m)) {
        $m1 = (int)$m[1];
        $d1 = (int)$m[2];
        $m2 = $m[3] !== '' ? (int)$m[3] : $m1;
        $d2 = (int)$m[4];
        $y  = (int)$m[5];
        return am_build_period($y, $m1, $d1, $m2, $d2);
    }

    // Month names: "October 1-15, 2026" / "Oct 1 - Oct 15" / "Oct 1 - Oct 15, 2026"
    if (preg_match('/([A-Za-z]{3,9})\.?\s+(\d{1,2})\s*(?:-|–|—|to)\s*(?:([A-Za-z]{3,9})\.?\s+)?(\d{1,2})(?:\s*,?\s*(\d{4}))?/u', $text, $m)) {
        $m1 = am_month_number($m[1]);
        $m2 = $m[3] !== '' ? am_month_number($m[3]) : $m1;
        $y  = !empty($m[5]) ? (int)$m[5] : ($defaultYear ? (int)$defaultYear : null);
        if ($m1 && $m2 && $y) {
            return am_build_period($y, $m1, (int)$m[2], $m2, (int)$m[4]);
        }
    }
    return null;
}

function am_build_period($year, $m1, $d1, $m2, $d2)
{
    if (!checkdate($m1, $d1, $year)) {
        return null;
    }
    $endYear = ($m2 < $m1) ? $year + 1 : $year;   // period that crosses New Year
    if (!checkdate($m2, $d2, $endYear)) {
        return null;
    }
    return [sprintf('%04d-%02d-%02d', $year, $m1, $d1), sprintf('%04d-%02d-%02d', $endYear, $m2, $d2)];
}

/** Label stored in attachments_monitoring.payroll_period, e.g. "Oct 1 - Oct 15, 2026". */
function am_period_label($start, $end)
{
    $s = new DateTime($start);
    $e = new DateTime($end);
    if ($s->format('Y') !== $e->format('Y')) {
        return $s->format('M j, Y') . ' - ' . $e->format('M j, Y');
    }
    return $s->format('M j') . ' - ' . $e->format('M j, Y');
}

/** Old label format without the year, e.g. "Oct 1 - Oct 15" (for matching legacy rows). */
function am_period_label_legacy($start, $end)
{
    return (new DateTime($start))->format('M j') . ' - ' . (new DateTime($end))->format('M j');
}

/** "October 1-15, 2026" (title cell A7). */
function am_period_title($start, $end)
{
    $s = new DateTime($start);
    $e = new DateTime($end);
    if ($s->format('Y-m') === $e->format('Y-m')) {
        return $s->format('F j') . '-' . $e->format('j, Y');
    }
    if ($s->format('Y') === $e->format('Y')) {
        return $s->format('F j') . ' - ' . $e->format('F j, Y');
    }
    return $s->format('F j, Y') . ' - ' . $e->format('F j, Y');
}

/** "10/1-15/2026" (the FOR PAYROLL header, I8). */
function am_period_code($start, $end)
{
    $s = new DateTime($start);
    $e = new DateTime($end);
    if ($s->format('Y-m') === $e->format('Y-m')) {
        return $s->format('n/j') . '-' . $e->format('j/Y');
    }
    return $s->format('n/j') . '-' . $e->format('n/j/Y');
}

/* -------------------------------------------------------------------------
 * Employee names
 * ---------------------------------------------------------------------- */

/** Name of the employee middle-name column if the table has one, else null. */
function am_employee_middle_column($db)
{
    $res = $db->query("SHOW COLUMNS FROM employee");
    if (!$res) {
        return null;
    }
    $candidates = ['middle_name', 'middle_initial', 'middlename', 'middle_init', 'mname'];
    while ($row = $res->fetch_assoc()) {
        if (in_array(strtolower($row['Field']), $candidates, true)) {
            return $row['Field'];
        }
    }
    return null;
}

/** Template style: "ABILA, Ela Mae S." */
function am_format_employee_name($last, $first, $middle = '')
{
    $last   = mb_strtoupper(trim((string)$last), 'UTF-8');
    $first  = trim((string)$first);
    $middle = trim((string)$middle);
    $mi     = $middle !== '' ? mb_strtoupper(mb_substr($middle, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '';
    return $last . ', ' . $first . ($mi !== '' ? ' ' . $mi : '');
}

/** Lower-case, accent-free, letters/digits/spaces only. */
function am_norm_name($s)
{
    $s = (string)$s;
    if (class_exists('Normalizer')) {
        $s = Normalizer::normalize($s, Normalizer::FORM_D);
        $s = preg_replace('/\p{Mn}+/u', '', $s);
    } else {
        $s = strtr($s, [
            'ñ' => 'n', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ü' => 'u', 'Ü' => 'U',
        ]);
    }
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim($s);
}

function am_tokens($s)
{
    $skip = ['jr', 'sr', 'ii', 'iii', 'iv'];
    $out = [];
    foreach (explode(' ', am_norm_name($s)) as $t) {
        if ($t !== '' && !in_array($t, $skip, true)) {
            $out[] = $t;
        }
    }
    return $out;
}

function am_key($s)
{
    return implode(' ', am_tokens($s));
}

/**
 * Load employees ready for matching.
 * Returns ['target' => [...], 'all' => [...]] ; 'target' only holds the monitored appointment statuses.
 */
function am_load_employee_pools($db)
{
    $mid = am_employee_middle_column($db);
    $sql = "SELECT emp_id, id_number, first_name, last_name, appointment_status_id"
         . ($mid ? ", `$mid` AS middle_name" : ", '' AS middle_name")
         . " FROM employee";
    $res = $db->query($sql);
    $all = [];
    $target = [];
    while ($r = $res->fetch_assoc()) {
        $first  = (string)$r['first_name'];
        $last   = (string)$r['last_name'];
        $middle = (string)$r['middle_name'];
        $firstTokens = am_tokens($first . ' ' . $middle);
        $e = [
            'emp_id'    => (int)$r['emp_id'],
            'id_number' => $r['id_number'],
            'last_key'  => am_key($last),
            'words'     => array_values(array_unique(array_filter($firstTokens, function ($t) { return strlen($t) > 1; }))),
            'initials'  => array_values(array_unique(array_merge(
                array_filter($firstTokens, function ($t) { return strlen($t) === 1; }),
                $middle !== '' ? [substr(am_norm_name($middle), 0, 1)] : []
            ))),
            'full_keys' => [
                am_key($first . ' ' . $last),
                am_key($last . ' ' . $first),
                am_key($first . ' ' . $middle . ' ' . $last),
            ],
        ];
        $all[] = $e;
        if (in_array((int)$r['appointment_status_id'], AM_TARGET_APPOINTMENT_STATUSES, true)) {
            $target[] = $e;
        }
    }
    return ['target' => $target, 'all' => $all];
}

/**
 * Match one name from the sheet against one pool.
 * Returns [employee|null, 'notfound'|'ambiguous: ...'|null].
 */
function am_match_employee($raw, array $pool)
{
    $raw = trim((string)$raw);

    if (strpos($raw, ',') !== false) {
        // "LAST, First M."
        list($l, $f) = array_map('trim', explode(',', $raw, 2));
        $lastKey = am_key($l);
        $tokens  = am_tokens($f);

        $cand = [];
        foreach ($pool as $e) {
            if ($e['last_key'] === $lastKey) {
                $cand[] = $e;
            }
        }
        if (!$cand) {
            return [null, 'notfound'];
        }
        if (count($cand) === 1) {
            return [$cand[0], null];
        }

        $words = array_unique(array_filter($tokens, function ($t) { return strlen($t) > 1; }));
        $inits = array_unique(array_filter($tokens, function ($t) { return strlen($t) === 1; }));
        $best = 0;
        $bestList = [];
        foreach ($cand as $e) {
            $score = count(array_intersect($words, $e['words'])) + 0.5 * count(array_intersect($inits, $e['initials']));
            if ($score > $best) {
                $best = $score;
                $bestList = [$e];
            } elseif ($score == $best && $score > 0) {
                $bestList[] = $e;
            }
        }
        if (count($bestList) === 1) {
            return [$bestList[0], null];
        }
        $ids = array_map(function ($e) { return $e['id_number'] ?: ('#' . $e['emp_id']); }, $cand);
        return [null, 'ambiguous: more than one employee matches (' . implode(', ', $ids) . ')'];
    }

    // No comma: "First Last" (old format) or just a surname
    $key = am_key($raw);
    foreach (['full', 'last'] as $mode) {
        $cand = [];
        foreach ($pool as $e) {
            if (($mode === 'full' && in_array($key, $e['full_keys'], true)) || ($mode === 'last' && $e['last_key'] === $key)) {
                $cand[] = $e;
            }
        }
        if (count($cand) === 1) {
            return [$cand[0], null];
        }
        if (count($cand) > 1) {
            $ids = array_map(function ($e) { return $e['id_number'] ?: ('#' . $e['emp_id']); }, $cand);
            return [null, 'ambiguous: more than one employee matches (' . implode(', ', $ids) . ')'];
        }
    }
    return [null, 'notfound'];
}

/** Monitored employees first, then everyone else. Returns [employee|null, error|null]. */
function am_find_employee($raw, array $pools)
{
    list($e, $err) = am_match_employee($raw, $pools['target']);
    if ($e || ($err && strpos($err, 'ambiguous') === 0)) {
        return [$e, $err];
    }
    return am_match_employee($raw, $pools['all']);
}

/* -------------------------------------------------------------------------
 * Save one monitoring record (insert, or update the existing row for the
 * same employee + payroll period).
 * ---------------------------------------------------------------------- */

/**
 * @param array $fields any of: status, filing_status, submission_date, remarks,
 *                      date_informed, compliance_status, date_complied, payroll_status
 *                      Only the keys you pass are changed on an existing record.
 * @return string 'inserted' | 'updated' | 'unchanged'
 */
function am_upsert_record($db, $empId, $start, $end, array $fields)
{
    $allowed = ['status', 'filing_status', 'submission_date', 'remarks',
                'date_informed', 'compliance_status', 'date_complied', 'payroll_status'];
    $fields = array_intersect_key($fields, array_flip($allowed));

    $label  = am_period_label($start, $end);
    $legacy = am_period_label_legacy($start, $end);
    $empId  = (int)$empId;

    $stmt = $db->prepare(
        "SELECT * FROM attachments_monitoring
         WHERE emp_id = ?
           AND ((period_start = ? AND period_end = ?) OR payroll_period = ? OR payroll_period = ?)
         LIMIT 1"
    );
    $stmt->bind_param('issss', $empId, $start, $end, $label, $legacy);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Nothing to do if every value is already the same
        $changed = ($existing['payroll_period'] !== $label)
                || ((string)$existing['period_start'] !== $start)
                || ((string)$existing['period_end'] !== $end);
        foreach ($fields as $col => $val) {
            if ((string)($existing[$col] ?? '') !== (string)($val ?? '')) {
                $changed = true;
                break;
            }
        }
        if (!$changed) {
            return 'unchanged';
        }

        $sets = ['payroll_period = ?', 'period_start = ?', 'period_end = ?'];
        $vals = [$label, $start, $end];
        foreach ($fields as $col => $val) {
            $sets[] = "$col = ?";
            $vals[] = $val;
        }
        $sets[] = 'updated_at = NOW()';
        $id = (int)$existing['monitoring_id'];
        $vals[] = $id;
        $types = str_repeat('s', count($vals) - 1) . 'i';

        $u = $db->prepare('UPDATE attachments_monitoring SET ' . implode(', ', $sets) . ' WHERE monitoring_id = ?');
        $u->bind_param($types, ...$vals);
        if (!$u->execute()) {
            throw new Exception('Database error: ' . $u->error);
        }
        $u->close();
        return 'updated';
    }

    $row = array_merge([
        'status'            => 'FOR REVIEW',
        'filing_status'     => 'NOT FORWARDED',
        'submission_date'   => null,
        'remarks'           => '',
        'date_informed'     => null,
        'compliance_status' => null,
        'date_complied'     => null,
        'payroll_status'    => null,
    ], $fields);

    $cols = array_merge(['emp_id', 'payroll_period', 'period_start', 'period_end'], array_keys($row));
    $vals = array_merge([$empId, $label, $start, $end], array_values($row));
    $ph   = implode(', ', array_fill(0, count($cols), '?'));
    $types = 'i' . str_repeat('s', count($cols) - 1);

    $i = $db->prepare('INSERT INTO attachments_monitoring (' . implode(', ', $cols) . ') VALUES (' . $ph . ')');
    $i->bind_param($types, ...$vals);
    if (!$i->execute()) {
        throw new Exception('Database error: ' . $i->error);
    }
    $i->close();
    return 'inserted';
}