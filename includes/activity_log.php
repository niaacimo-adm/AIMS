<?php
// Shared activity-log helpers for the scrum module.
// Include this from any ajax handler that already has $db (a mysqli
// connection) available: require_once __DIR__ . '/activity_log.php';

/**
 * Record one activity log entry. Never throws — logging should never be
 * allowed to break the primary action (creating/updating a task, etc.), so
 * failures are only written to error_log.
 */
function logActivity($db, string $entityType, int $entityId, int $projectId, int $empId, string $action, string $description, $meta = null): bool {
    $metaJson = $meta !== null ? json_encode($meta) : null;

    $stmt = $db->prepare("INSERT INTO activity_log (entity_type, entity_id, project_id, emp_id, action, description, meta) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("logActivity: prepare failed: " . $db->error);
        return false;
    }

    $stmt->bind_param("siiisss", $entityType, $entityId, $projectId, $empId, $action, $description, $metaJson);

    if (!$stmt->execute()) {
        error_log("logActivity: execute failed: " . $stmt->error);
        return false;
    }
    return true;
}

/**
 * Fetch activity log entries for a single task or project, newest first,
 * joined with the employee who performed the action.
 */
function getActivityLog($db, string $entityType, int $entityId, int $limit = 50) {
    $stmt = $db->prepare("
        SELECT al.*, e.first_name, e.last_name, e.picture
        FROM activity_log al
        LEFT JOIN employee e ON al.emp_id = e.emp_id
        WHERE al.entity_type = ? AND al.entity_id = ?
        ORDER BY al.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("sii", $entityType, $entityId, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Fetch every activity for a project — its own project-level events plus
 * every task's events under it — for a single project-wide activity feed.
 */
function getProjectActivityLog($db, int $projectId, int $limit = 100) {
    $stmt = $db->prepare("
        SELECT al.*, e.first_name, e.last_name, e.picture
        FROM activity_log al
        LEFT JOIN employee e ON al.emp_id = e.emp_id
        WHERE al.project_id = ?
        ORDER BY al.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $projectId, $limit);
    $stmt->execute();
    return $stmt->get_result();
}