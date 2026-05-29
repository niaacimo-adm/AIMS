<?php
/**
 * document_archive_cron.php  (FIXED)
 * ─────────────────────────
 * Called by a server cron job once per day, just after midnight PHT.
 *
 * Recommended cron entry (runs at 00:05 PHT = 16:05 UTC):
 *   5 16 * * * php /path/to/your/project/document_archive_cron.php >> /var/log/doc_archive.log 2>&1
 *
 * Protect with a shared secret set in your environment:
 *   ARCHIVE_CRON_TOKEN=some-long-random-secret  (in .env or server env)
 *
 * Or call it directly from the web with the token:
 *   https://yoursite.com/document_archive_cron.php?cron_token=<secret>
 *
 * ── FIXES APPLIED ────────────────────────────────────────────────────────────
 * FIX 1: Define CRON_RUNNING before requiring auth_bypass.php so the stub
 *         knows it's being legitimately included (not direct web access).
 * FIX 2: Drop CONVERT_TZ() from the archive SELECT — because the session
 *         timezone is already SET to '+08:00', MariaDB's NOW()/CURRENT_TIMESTAMP
 *         returns PHT time, and TIMESTAMP columns are stored/read in the session
 *         timezone. Using CONVERT_TZ(col, '+00:00', '+08:00') when the session tz
 *         is already PHT causes a double-conversion that skips valid rows.
 *         Use DATE(col) directly — MariaDB applies the session tz automatically.
 * FIX 3: Load .env ARCHIVE_CRON_TOKEN from file if getenv() returns nothing
 *         (some shared-hosting setups don't propagate env vars to CLI).
 */

// ── FIX 1: Mark this as a legitimate cron entry point ────────────────────────
define('CRON_RUNNING', true);

// ── Allow CLI or HTTP with a cron token ──────────────────────────────────────
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    $expected = _loadCronToken();
    $provided = $_GET['cron_token'] ?? $_POST['cron_token'] ?? '';

    if (!$expected || $provided !== $expected) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
$base = dirname(__FILE__);
require_once $base . '/includes/auth_bypass.php';   // now works — CRON_RUNNING is defined
require_once $base . '/config/database.php';

date_default_timezone_set('Asia/Manila');

$database = new Database();
$db       = $database->getConnection();

// Set session timezone to PHT. After this, DATE(col) on any TIMESTAMP column
// returns the PHT date automatically — no CONVERT_TZ needed.
$db->query("SET time_zone = '+08:00'");

// ── Determine yesterday in PHT ───────────────────────────────────────────────
// Use DATE(NOW() - INTERVAL 1 DAY) — since session tz = PHT, NOW() is already
// PHT time, so this is always yesterday in Manila regardless of server clock.
$pht_row      = $db->query("SELECT DATE(NOW() - INTERVAL 1 DAY) AS yesterday_pht, DATE(NOW()) AS today_pht");
$pht_data     = $pht_row ? $pht_row->fetch_assoc() : [];
$arc_date     = $pht_data['yesterday_pht'] ?? date('Y-m-d', strtotime('-1 day'));
$triggered_by = 'cron';

// ── Idempotency: skip if already ran today for this date ─────────────────────
$db->query("
    CREATE TABLE IF NOT EXISTS `document_archive_log` (
        `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `run_date`     DATE         NOT NULL UNIQUE,
        `archived`     INT UNSIGNED NOT NULL DEFAULT 0,
        `triggered_by` VARCHAR(20)  NOT NULL DEFAULT 'auto',
        `run_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$already = $db->query("SELECT id FROM document_archive_log WHERE run_date = '$arc_date' LIMIT 1");
if ($already && $already->num_rows > 0) {
    $msg = "[" . date('Y-m-d H:i:s') . "] Already archived for {$arc_date}. Skipped.\n";
    echo $msg;
    exit(0);
}

// ── Ensure archive table exists ───────────────────────────────────────────────
$db->query("
    CREATE TABLE IF NOT EXISTS `document_archive` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `original_id`     INT UNSIGNED NOT NULL,
        `archive_date`    DATE         NOT NULL,
        `kind`            VARCHAR(20)  NOT NULL,
        `document_number` VARCHAR(100) NOT NULL,
        `document_name`   VARCHAR(255) NOT NULL,
        `document_type`   VARCHAR(100) DEFAULT NULL,
        `status`          VARCHAR(50)  DEFAULT NULL,
        `forwarded_by`    VARCHAR(150) DEFAULT NULL,
        `from_section`    VARCHAR(150) DEFAULT NULL,
        `to_section`      VARCHAR(150) DEFAULT NULL,
        `date_forwarded`  DATETIME     DEFAULT NULL,
        `remarks`         TEXT         DEFAULT NULL,
        `snapshot_json`   LONGTEXT     DEFAULT NULL,
        `archived_by_emp` INT UNSIGNED DEFAULT NULL,
        `archived_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_archive_date` (`archive_date`),
        INDEX `idx_original_id`  (`original_id`),
        INDEX `idx_kind`         (`kind`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Fetch all documents belonging to yesterday ────────────────────────────────
// FIX 2: Use DATE(col) directly — session tz is PHT so MariaDB already returns
// PHT dates. CONVERT_TZ(col, '+00:00', '+08:00') was causing a double-conversion
// because the session tz is NOT '+00:00'; it's already '+08:00'.
//
// Also handle the 0000-00-00 sentinel: MariaDB stores this when date_forwarded
// was never set. We exclude it explicitly.
$sel = $db->prepare("
    SELECT dr.*,
           dt.type_name,
           COALESCE(
               CONCAT(TRIM(fbe.first_name),' ',TRIM(fbe.last_name)),
               dr.forwarded_by_name
           ) AS resolved_fwd_by,
           s1.section_name AS from_section_name,
           s2.section_name AS to_section_name,
           us1.unit_name   AS from_unit_name,
           us2.unit_name   AS to_unit_name
    FROM document_records dr
    LEFT JOIN document_types dt  ON dr.document_type_id         = dt.id
    LEFT JOIN employee       fbe ON dr.forwarded_by_emp_id      = fbe.emp_id
    LEFT JOIN section        s1  ON dr.from_section_id          = s1.section_id
    LEFT JOIN section        s2  ON dr.forwarded_to_section_id  = s2.section_id
    LEFT JOIN unit_section   us1 ON dr.from_unit_id             = us1.unit_id
    LEFT JOIN unit_section   us2 ON dr.forwarded_to_unit_id     = us2.unit_id
    WHERE (
        DATE(dr.created_at) = ?
        OR (
            dr.date_forwarded IS NOT NULL
            AND dr.date_forwarded != '0000-00-00 00:00:00'
            AND DATE(dr.date_forwarded) = ?
        )
    )
    AND dr.id NOT IN (
        SELECT original_id FROM document_archive WHERE archive_date = ?
    )
");
$sel->bind_param("sss", $arc_date, $arc_date, $arc_date);
$sel->execute();
$rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$rows) {
    $db->query("INSERT IGNORE INTO document_archive_log (run_date, archived, triggered_by) VALUES ('$arc_date', 0, '$triggered_by')");
    $msg = "[" . date('Y-m-d H:i:s') . "] No documents to archive for {$arc_date}.\n";
    echo $msg;
    exit(0);
}

// ── Archive + delete inside a transaction ────────────────────────────────────
$ins = $db->prepare("
    INSERT INTO document_archive
        (original_id, archive_date, kind, document_number, document_name,
         document_type, status, forwarded_by, from_section, to_section,
         date_forwarded, remarks, snapshot_json, archived_by_emp, archived_at)
    VALUES (?,?,?,?,?, ?,?,?,?,?, ?,?,?,0, NOW())
");

$archived_count = 0;
$archived_ids   = [];

$db->begin_transaction();
try {
    foreach ($rows as $doc) {
        $date_fwd = (!empty($doc['date_forwarded']) && $doc['date_forwarded'] !== '0000-00-00 00:00:00')
                    ? $doc['date_forwarded'] : null;
        $fwd_by   = trim($doc['resolved_fwd_by'] ?? '');

        $ins->bind_param(
            "issss" . "sssss" . "sss",
            $doc['id'],
            $arc_date,
            $doc['kind'],
            $doc['document_number'],
            $doc['document_name'],
            $doc['type_name'],
            $doc['status'],
            $fwd_by,
            $doc['from_section_name'],
            $doc['to_section_name'],
            $date_fwd,
            $doc['remarks'],
            json_encode($doc)
        );
        if ($ins->execute()) {
            $archived_count++;
            $archived_ids[] = (int)$doc['id'];
        }
    }

    $deleted_count = 0;
    if ($archived_ids) {
        $id_list = implode(',', $archived_ids);
        $db->query("DELETE FROM document_forwards WHERE document_id IN ($id_list)");
        $db->query("DELETE FROM document_delete_requests WHERE document_id IN ($id_list)");
        $del           = $db->query("DELETE FROM document_records WHERE id IN ($id_list)");
        $deleted_count = $del ? (int)$db->affected_rows : 0;
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    $msg = "[" . date('Y-m-d H:i:s') . "] Archive FAILED for {$arc_date}: " . $e->getMessage() . "\n";
    echo $msg;
    exit(1);
}

// ── Log the run ───────────────────────────────────────────────────────────────
$log = $db->prepare("
    INSERT INTO document_archive_log (run_date, archived, triggered_by)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE archived = archived + ?, triggered_by = ?, run_at = NOW()
");
$log->bind_param("sisss", $arc_date, $archived_count, $triggered_by, $archived_count, $triggered_by);
$log->execute();

$msg = "[" . date('Y-m-d H:i:s') . "] Archived {$archived_count} doc(s) for {$arc_date}. Deleted {$deleted_count} from live table.\n";
echo $msg;
exit(0);

// ── Helper: load ARCHIVE_CRON_TOKEN from env or .env file ────────────────────
// FIX 3: Some hosting environments don't expose env vars to CLI. Fall back to
// reading a .env file in the project root so the token is always available.
function _loadCronToken(): string {
    $token = getenv('ARCHIVE_CRON_TOKEN');
    if ($token) return $token;

    // Try to load from a .env file in the project root
    $envFile = dirname(__FILE__) . '/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                if (trim($key) === 'ARCHIVE_CRON_TOKEN') {
                    return trim($val, " \t\n\r\0\x0B\"'");
                }
            }
        }
    }
    return '';
}