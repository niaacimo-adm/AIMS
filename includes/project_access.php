<?php
// Shared project-membership helpers for the scrum module.
// Include this from any ajax handler that already has $db (a mysqli
// connection) available: require_once __DIR__ . '/project_access.php';
//
// These enforce the rule that only members of a project may be assigned
// its tasks, or view/act on its board — everyone else is treated as
// having no access to that project at all.

/**
 * True if $empId is a member of $projectId, in any role (owner, manager,
 * or plain member).
 */
function isProjectMember($db, int $projectId, int $empId): bool {
    $stmt = $db->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND emp_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("isProjectMember: prepare failed: " . $db->error);
        return false;
    }
    $stmt->bind_param("ii", $projectId, $empId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Fetch the emp_ids of every member of a project, so a proposed list of
 * task assignees can be validated server-side (never trust the client to
 * only have offered member IDs in the first place).
 */
function getProjectMemberIds($db, int $projectId): array {
    $stmt = $db->prepare("SELECT emp_id FROM project_members WHERE project_id = ?");
    $stmt->bind_param("i", $projectId);
    $stmt->execute();
    $result = $stmt->get_result();

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['emp_id'];
    }
    return $ids;
}

/**
 * Given a project id and a list of proposed assignee emp_ids, return only
 * the ones that are NOT members of the project. An empty array means the
 * whole list is valid.
 */
function getNonMemberAssignees($db, int $projectId, array $assigneeIds): array {
    if (empty($assigneeIds)) {
        return [];
    }
    $memberIds = getProjectMemberIds($db, $projectId);
    return array_values(array_diff($assigneeIds, $memberIds));
}