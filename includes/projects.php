<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function createProject(PDO $pdo, int $creatorId, int $organizationId, array $data): int {
  $code = generateProjectCode($pdo);
  ensureProjectDir($code);
  $sql = "INSERT INTO projects
    (organization_id, name, code, status, start_date, end_date, manager_id, visibility, description, location, tags, created_by)
    VALUES (:oid, :name, :code, :status, :sd, :ed, :mid, :vis, :desc, :loc, :tags, :cb)";
  $stm = $pdo->prepare($sql);
  $stm->execute([
    ':oid'=>$organizationId, ':name'=>trim($data['name'] ?? ''), ':code'=>$code,
    ':status'=>$data['status'] ?? 'active', ':sd'=>$data['start_date'] ?? null, ':ed'=>$data['end_date'] ?? null,
    ':mid'=>$data['manager_id'] ?? $creatorId, ':vis'=>$data['visibility'] ?? 'org',
    ':desc'=>$data['description'] ?? null, ':loc'=>$data['location'] ?? null, ':tags'=>$data['tags'] ?? null,
    ':cb'=>$creatorId
  ]);
  $pid = (int)$pdo->lastInsertId();
  $stm = $pdo->prepare("INSERT INTO project_members(project_id,user_id,role) VALUES(:pid,:uid,'owner')");
  $stm->execute([':pid'=>$pid, ':uid'=>$creatorId]);
  addActivity($pdo, $pid, $creatorId, 'project.create', $data['name'] ?? '');
  return $pid;
}
function userProjectLimitReached(PDO $pdo, int $userId): bool {
  $sub = currentUserSubscription($pdo, $userId);
  if (!$sub) return false;
  $max = (int)($sub['max_projects'] ?? 0);
  if ($max <= 0) return false;
  $stm = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE created_by=:uid");
  $stm->execute([':uid'=>$userId]);
  return (int)$stm->fetchColumn() >= $max;
}
function listProjectsForUser(PDO $pdo, int $userId): array {
  $sql = "SELECT DISTINCT p.*,
            (SELECT COUNT(*) FROM project_members pm2 WHERE pm2.project_id=p.id) AS member_count
          FROM projects p
          LEFT JOIN project_members pm ON pm.project_id=p.id
          WHERE pm.user_id=:uid OR p.created_by=:uid
          ORDER BY p.updated_at DESC";
  $stm = $pdo->prepare($sql); $stm->execute([':uid'=>$userId]);
  return $stm->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function getProject(PDO $pdo, int $projectId): ?array {
  $stm = $pdo->prepare("SELECT * FROM projects WHERE id=:id");
  $stm->execute([':id'=>$projectId]);
  $row = $stm->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}
function projectOwnerId(PDO $pdo, int $projectId): ?int {
  $stm = $pdo->prepare("SELECT created_by FROM projects WHERE id=:id");
  $stm->execute([':id'=>$projectId]); $id = $stm->fetchColumn();
  return $id !== false ? (int)$id : null;
}
function storageSummaryForProjectOwner(PDO $pdo, int $projectId): array {
  $ownerId = projectOwnerId($pdo, $projectId);
  if (!$ownerId) return ['allowed_gb'=>0, 'used_this'=>0, 'used_others'=>0];
  $sub = currentUserSubscription($pdo, $ownerId);
  $allowed_gb = (int)($sub['max_storage_gb'] ?? 0);
  $sql = "
    SELECT p.id AS pid, COALESCE(SUM(fv.size_bytes),0) AS used
    FROM projects p
    JOIN project_files f ON f.project_id=p.id
    JOIN file_versions fv ON fv.file_id=f.id
    WHERE p.created_by=:owner
      AND fv.version=(SELECT MAX(v2.version) FROM file_versions v2 WHERE v2.file_id=f.id)
    GROUP BY p.id";
  $stm = $pdo->prepare($sql); $stm->execute([':owner'=>$ownerId]);
  $byProject = $stm->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
  $used_this = (int)($byProject[$projectId] ?? 0);
  $total_used = array_sum(array_map('intval', $byProject));
  $used_others = max(0, $total_used - $used_this);
  return ['allowed_gb'=>$allowed_gb, 'used_this'=>$used_this, 'used_others'=>$used_others];
}
