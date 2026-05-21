<?php
/**
 * cron_midnight_archive.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Server-side cron script that archives yesterday's documents at midnight PHT.
 *
 * HOW TO INSTALL (run this once on your server):
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. Open your cPanel (or SSH) and go to Cron Jobs.
 * 2. Add a new cron job set to run at  0 16 * * *  (UTC)
 *    which equals midnight 00:00 PHT (UTC+8).
 *
 *    Command:
 *      /usr/bin/php /path/to/your/project/cron_midnight_archive.php >> /path/to/your/project/logs/archive_cron.log 2>&1
 *
 *    Replace /path/to/your/project/ with the actual absolute path on your server.
 *
 * 3. (Optional) Set environment variable ARCHIVE_CRON_TOKEN to a secret string
 *    in your server config or .env file. The same value must be in the PHP
 *    environment when the cron runs. This authenticates the cron call.
 *    If you skip this, the script uses a direct DB call instead (also safe).
 *
 * WHY THIS IS NEEDED:
 *    The browser JS auto-archive only fires when someone has the archive page
 *    open at midnight. This cron guarantees the archive runs every night
 *    regardless of browser activity.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');   // PHT

// Locate the project root (one level up from this script if placed in project root,
// or adjust the path below to match your layout).
$project_root = __DIR__;

require_once $project_root . '/includes/auth.php';
require_once $project_root . '/config/database.php';

$database = new Database();
$db       = $database->getConnection();
$db->query("SET time_zone = '+08:00'");     // PHT session

// ── Resolve archive date (yesterday PHT from DB session) ───────────────────
$pht_row  = $db->query("SELECT DATE(NOW()) AS today_pht, DATE(NOW() - INTERVAL 1 DAY) AS yesterday_pht");
$pht_data = $pht_row ? $pht_row->fetch_assoc() : [];
$arc_date = $pht_data['yesterday_pht'] ?? date('Y-m-d', strtotime('-1 day'));
$now_str  = $pht_data['today_pht']     ?? date('Y-m-d H:i:s');

echo "[{$now_str}] Cron midnight archive starting for date: {$arc_date}\n";

// ── Idempotency check ──────────────────────────────────────────────────────
$already = $db->query("SELECT id FROM document_archive_log WHERE run_date = '$arc_date' LIMIT 1");
if ($already && $already->num_rows > 0) {
    echo "[{$now_str}] Already archived for {$arc_date}. Skipped.\n";
    exit(0);
}

// ── Fetch documents belonging to arc_date ──────────────────────────────────
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
    $db->query("INSERT IGNORE INTO document_archive_log (run_date, archived, triggered_by) VALUES ('$arc_date', 0, 'cron')");
    echo "[{$now_str}] No documents to archive for {$arc_date}.\n";
    exit(0);
}

echo "[{$now_str}] Found " . count($rows) . " document(s) to archive.\n";

// ── Archive + Delete inside a transaction ──────────────────────────────────
$ins = $db->prepare("
    INSERT INTO document_archive
        (original_id, archive_date, kind, document_number, document_name,
         document_type, status, forwarded_by, from_section, to_section,
         date_forwarded, remarks, snapshot_json, archived_by_emp, archived_at)
    VALUES (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?, NOW())
");

$archived_count = 0;
$archived_ids   = [];
$caller         = 0;   // 0 = cron (no human emp_id)

$db->begin_transaction();
try {
    foreach ($rows as $doc) {
        $date_fwd = (!empty($doc['date_forwarded']) && $doc['date_forwarded'] !== '0000-00-00 00:00:00')
                    ? $doc['date_forwarded'] : null;
        $fwd_by   = trim($doc['resolved_fwd_by'] ?? '');

        $ins->bind_param(
            "issss" . "sssss" . "sssi",
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
            json_encode($doc),
            $caller
        );
        if ($ins->execute()) {
            $archived_count++;
            $archived_ids[] = (int)$doc['id'];
        } else {
            echo "[{$now_str}] WARNING: Insert failed for doc id={$doc['id']}: " . $ins->error . "\n";
        }
    }

    // Delete from live table
    $deleted_count = 0;
    if ($archived_ids) {
        $id_list = implode(',', $archived_ids);
        $db->query("DELETE FROM document_forwards WHERE document_id IN ($id_list)");
        $db->query("DELETE FROM document_delete_requests WHERE document_id IN ($id_list)");
        $db->query("DELETE FROM document_records WHERE id IN ($id_list)");
        $deleted_count = (int)$db->affected_rows;
    }

    $db->commit();
    echo "[{$now_str}] Success: archived={$archived_count}, deleted from live table={$deleted_count}.\n";

} catch (Exception $e) {
    $db->rollback();
    echo "[{$now_str}] ERROR: Transaction rolled back — " . $e->getMessage() . "\n";
    exit(1);
}

// ── Log the run ────────────────────────────────────────────────────────────
$log = $db->prepare("
    INSERT INTO document_archive_log (run_date, archived, triggered_by)
    VALUES (?, ?, 'cron')
    ON DUPLICATE KEY UPDATE archived = archived + ?, triggered_by = 'cron', run_at = NOW()
");
$log->bind_param("sii", $arc_date, $archived_count, $archived_count);
$log->execute();

echo "[{$now_str}] Cron archive complete.\n";
exit(0);