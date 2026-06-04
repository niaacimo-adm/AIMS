<?php
/**
 * document_archive_cron.php  (FIXED v2)
 * ──────────────────────────────────────
 * Called by a server cron job once per day, just after midnight PHT.
 *
 * Recommended cron entry (runs at 00:05 PHT = 16:05 UTC):
 *   5 16 * * * php /path/to/your/project/document_archive_cron.php >> /var/log/doc_archive.log 2>&1
 *
 * Manual backfill for a specific date:
 *   php document_archive_cron.php --date=2026-05-29
 *
 * ── FIXES IN THIS VERSION ─────────────────────────────────────────────────────
 *
 * FIX A — BACKFILL LOOP
 *   The old cron only ever targeted yesterday. Any missed run left records
 *   stranded forever. Now we collect every date that has document_records rows
 *   but no document_archive_log entry with archived > 0, and process them all.
 *   You can also pass --date=YYYY-MM-DD (CLI) or ?date=YYYY-MM-DD (HTTP) to
 *   force a specific date regardless of log state.
 *
 * FIX B — IDEMPOTENCY CHECK NOW CHECKS archived > 0
 *   The old check exited immediately if ANY log row existed for the date,
 *   even one with archived = 0 (written after a failed or empty run). This
 *   meant a date that failed once could never be retried. Now we skip only
 *   if the log says archived > 0 AND no unarchived records remain for that date.
 *
 * FIX C — DUPLICATE-SAFE ARCHIVE INSERT
 *   Added a UNIQUE KEY on (original_id, archive_date) in the archive table DDL
 *   and changed the INSERT to INSERT IGNORE so a duplicate row (same doc picked
 *   up by both created_at and date_forwarded in the same batch) doesn't cause
 *   a silent failure that leaves the document in the live table.
 *
 * FIX D — PROPER MySQLi ERROR PROPAGATION
 *   $db->query() returns false on error but does NOT throw a PHP Exception.
 *   The old catch(Exception) therefore never fired on DELETE failures (e.g.
 *   FK constraint violations). Now every query result is checked; if it is
 *   false, we throw a RuntimeException with $db->error so the rollback fires.
 *
 * FIX E — DEFINE CRON_RUNNING + .env token loading (carried over from v1)
 */

// ── FIX 1 (v1): Mark this as a legitimate cron entry point ───────────────────
define('CRON_RUNNING', true);

// ── CLI or HTTP with a cron token ────────────────────────────────────────────
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
require_once $base . '/includes/auth_bypass.php';
require_once $base . '/config/database.php';

date_default_timezone_set('Asia/Manila');

$database = new Database();
$db       = $database->getConnection();

// Set session timezone to PHT. DATE(col) on TIMESTAMP columns returns PHT date.
$db->query("SET time_zone = '+08:00'");

$triggered_by = $is_cli ? 'cron' : 'http';

// ── Ensure schema tables exist ────────────────────────────────────────────────
$db->query("
    CREATE TABLE IF NOT EXISTS `document_archive_log` (
        `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `run_date`     DATE         NOT NULL UNIQUE,
        `archived`     INT UNSIGNED NOT NULL DEFAULT 0,
        `triggered_by` VARCHAR(20)  NOT NULL DEFAULT 'auto',
        `run_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// FIX C: Added UNIQUE KEY on (original_id, archive_date) so INSERT IGNORE is
// effective when the same document is matched by both created_at and date_forwarded.
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
        UNIQUE KEY `uq_original_archive` (`original_id`, `archive_date`),
        INDEX `idx_archive_date` (`archive_date`),
        INDEX `idx_original_id`  (`original_id`),
        INDEX `idx_kind`         (`kind`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Add UNIQUE KEY to existing table if it was created without it (safe on re-run)
$db->query("
    ALTER TABLE `document_archive`
    ADD UNIQUE KEY `uq_original_archive` (`original_id`, `archive_date`)
");
// Ignore error — key may already exist.

// ── FIX A: Determine which dates need archiving ───────────────────────────────
//
// Priority 1: explicit --date / ?date override
// Priority 2: backfill — all distinct created_at / date_forwarded dates in
//             document_records that have no log entry with archived > 0
// Priority 3: yesterday (normal nightly run)

$force_date = null;
if ($is_cli) {
    foreach ($argv as $arg) {
        if (preg_match('/^--date=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
            $force_date = $m[1];
        }
    }
} else {
    $d = $_GET['date'] ?? $_POST['date'] ?? '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        $force_date = $d;
    }
}

if ($force_date) {
    // Forced single date — ignore log state
    $dates_to_process = [$force_date];
    $triggered_by     = 'manual';
} else {
    // FIX A: collect every date that has live records but no successful archive log
    $dates_to_process = [];

    // Yesterday is always included (the normal nightly target)
    $yesterday_row = $db->query("SELECT DATE(NOW() - INTERVAL 1 DAY) AS d");
    $yesterday     = $yesterday_row ? $yesterday_row->fetch_assoc()['d'] : date('Y-m-d', strtotime('-1 day'));
    $dates_to_process[] = $yesterday;

    // Find any additional dates in document_records with no log row (archived > 0)
    $backfill_res = $db->query("
        SELECT DISTINCT dr_date FROM (
            SELECT DATE(created_at)    AS dr_date FROM document_records
            UNION
            SELECT DATE(date_forwarded) AS dr_date FROM document_records
            WHERE date_forwarded IS NOT NULL
              AND date_forwarded != '0000-00-00 00:00:00'
        ) AS all_dates
        WHERE dr_date IS NOT NULL
          AND dr_date < DATE(NOW())      -- never archive today
          AND dr_date NOT IN (
              SELECT run_date FROM document_archive_log WHERE archived > 0
          )
        ORDER BY dr_date ASC
    ");

    if ($backfill_res) {
        while ($row = $backfill_res->fetch_assoc()) {
            $d = $row['dr_date'];
            if (!in_array($d, $dates_to_process, true)) {
                $dates_to_process[] = $d;
            }
        }
    }

    sort($dates_to_process); // oldest first
}

// ── Process each date ─────────────────────────────────────────────────────────
foreach ($dates_to_process as $arc_date) {

    // FIX B: Skip only if log says archived > 0 AND no unarchived rows remain
    if (!$force_date) {
        $log_row = $db->query("SELECT archived FROM document_archive_log WHERE run_date = '$arc_date' LIMIT 1");
        if ($log_row && $log_row->num_rows > 0) {
            $log_data = $log_row->fetch_assoc();
            if ((int)$log_data['archived'] > 0) {
                // Double-check: are there any unarchived records still remaining?
                $remaining = $db->query("
                    SELECT COUNT(*) AS cnt FROM document_records
                    WHERE DATE(created_at) = '$arc_date'
                       OR (date_forwarded IS NOT NULL
                           AND date_forwarded != '0000-00-00 00:00:00'
                           AND DATE(date_forwarded) = '$arc_date')
                ");
                $rem_cnt = $remaining ? (int)$remaining->fetch_assoc()['cnt'] : 0;
                if ($rem_cnt === 0) {
                    $msg = "[" . date('Y-m-d H:i:s') . "] {$arc_date}: already fully archived. Skipped.\n";
                    echo $msg;
                    continue;
                }
                // Some remain — fall through and re-archive
                $msg = "[" . date('Y-m-d H:i:s') . "] {$arc_date}: partially archived ({$rem_cnt} remaining). Re-running.\n";
                echo $msg;
            }
            // archived = 0 means it ran before but got nothing or failed — retry
        }
    }

    // ── Fetch all documents belonging to this date ────────────────────────────
    // FIX 2 (v1): Use DATE(col) directly — session tz is PHT, no CONVERT_TZ needed.
    // FIX C: Exclude by original_id globally (not scoped to archive_date) so a
    //        document archived under a different archive_date is still excluded.
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
        AND dr.id NOT IN (SELECT original_id FROM document_archive)
    ");
    $sel->bind_param("ss", $arc_date, $arc_date);
    $sel->execute();
    $rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!$rows) {
        $db->query("
            INSERT INTO document_archive_log (run_date, archived, triggered_by)
            VALUES ('$arc_date', 0, '$triggered_by')
            ON DUPLICATE KEY UPDATE triggered_by = '$triggered_by', run_at = NOW()
        ");
        $msg = "[" . date('Y-m-d H:i:s') . "] {$arc_date}: no documents to archive.\n";
        echo $msg;
        continue;
    }

    // FIX C: INSERT IGNORE — skip if (original_id, archive_date) already exists
    $ins = $db->prepare("
        INSERT IGNORE INTO document_archive
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

            if (!$ins->execute()) {
                // FIX D: throw so rollback fires
                throw new RuntimeException("Archive INSERT failed for doc #{$doc['id']}: " . $ins->error);
            }

            // affected_rows = 0 means INSERT IGNORE skipped a duplicate — still safe,
            // document was already archived; include its id for deletion.
            $archived_count++;
            $archived_ids[] = (int)$doc['id'];
        }

        $deleted_count = 0;
        if ($archived_ids) {
            $id_list = implode(',', $archived_ids);

            // FIX D: check every DELETE result and throw on failure
            $r1 = $db->query("DELETE FROM document_forwards WHERE document_id IN ($id_list)");
            if ($r1 === false) {
                throw new RuntimeException("DELETE document_forwards failed: " . $db->error);
            }

            $r2 = $db->query("DELETE FROM document_delete_requests WHERE document_id IN ($id_list)");
            if ($r2 === false) {
                throw new RuntimeException("DELETE document_delete_requests failed: " . $db->error);
            }

            // Add DELETE statements for any other child tables here, e.g.:
            // $db->query("DELETE FROM document_notifications WHERE document_id IN ($id_list)");

            $del = $db->query("DELETE FROM document_records WHERE id IN ($id_list)");
            if ($del === false) {
                throw new RuntimeException("DELETE document_records failed: " . $db->error);
            }
            $deleted_count = (int)$db->affected_rows;
        }

        $db->commit();

    } catch (Exception $e) {
        $db->rollback();
        $msg = "[" . date('Y-m-d H:i:s') . "] {$arc_date}: FAILED — " . $e->getMessage() . "\n";
        echo $msg;
        continue; // try next date
    }

    // ── Log the run ───────────────────────────────────────────────────────────
    $log = $db->prepare("
        INSERT INTO document_archive_log (run_date, archived, triggered_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            archived     = archived + ?,
            triggered_by = ?,
            run_at       = NOW()
    ");
    $log->bind_param("sisss", $arc_date, $archived_count, $triggered_by, $archived_count, $triggered_by);
    $log->execute();

    $msg = "[" . date('Y-m-d H:i:s') . "] {$arc_date}: archived {$archived_count} doc(s), deleted {$deleted_count} from live table.\n";
    echo $msg;
}

exit(0);

// ── Helper: load ARCHIVE_CRON_TOKEN from env or .env file ────────────────────
function _loadCronToken(): string {
    $token = getenv('ARCHIVE_CRON_TOKEN');
    if ($token) return $token;

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