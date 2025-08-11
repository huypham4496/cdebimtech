<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
function normalizeLike(string $s): string { return str_replace('*', '%', $s); }
function createFolder(PDO $pdo, int $projectId, ?int $parentId, string $name, int $userId): int {
  $stm = $pdo->prepare("INSERT INTO project_folders(project_id,parent_id,name,created_by) VALUES (:pid,:pp,:name,:uid)");
  $stm->execute([':pid'=>$projectId, ':pp'=>$parentId, ':name'=>$name, ':uid'=>$userId]);
  $id = (int)$pdo->lastInsertId(); addActivity($pdo, $projectId, $userId, 'folder.create', $name); return $id;
}
function registerFile(PDO $pdo, int $projectId, int $folderId, string $filename, int $userId, string $tag='WIP', bool $important=false): int {
  $stm = $pdo->prepare("INSERT INTO project_files(project_id,folder_id,filename,tag,is_important,created_by) VALUES (:pid,:fid,:fn,:tag,:imp,:uid)");
  $stm->execute([':pid'=>$projectId, ':fid'=>$folderId, ':fn'=>$filename, ':tag'=>$tag, ':imp'=>$important?1:0, ':uid'=>$userId]);
  $fileId = (int)$pdo->lastInsertId(); addActivity($pdo, $projectId, $userId, 'file.register', $filename); return $fileId;
}
function addFileVersion(PDO $pdo, int $fileId, string $path, int $sizeBytes, int $userId): int {
  $stm = $pdo->prepare("SELECT COALESCE(MAX(version),0)+1 FROM file_versions WHERE file_id=:fid");
  $stm->execute([':fid'=>$fileId]); $ver = (int)$stm->fetchColumn();
  $stm = $pdo->prepare("INSERT INTO file_versions(file_id,version,storage_path,size_bytes,uploaded_by) VALUES (:fid,:v,:sp,:sz,:uid)");
  $stm->execute([':fid'=>$fileId, ':v'=>$ver, ':sp'=>$path, ':sz'=>$sizeBytes, ':uid'=>$userId]);
  return $ver;
}
function listFolderTree(PDO $pdo, int $projectId): array {
  $stm = $pdo->prepare("SELECT * FROM project_folders WHERE project_id=:pid ORDER BY parent_id, name");
  $stm->execute([':pid'=>$projectId]); return $stm->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function listFolderFiles(PDO $pdo, int $projectId, int $folderId, ?string $search=null, ?string $tag=null): array {
  $conds = ["f.project_id=:pid", "f.folder_id=:fid", "f.is_deleted=0"]; $params = [':pid'=>$projectId, ':fid'=>$folderId];
  if ($search) { $conds[] = "f.filename LIKE :q"; $params[':q'] = normalizeLike($search); }
  if ($tag && in_array($tag, ['WIP','Shared','Published','Archived'], true)) { $conds[] = "f.tag=:tag"; $params[':tag'] = $tag; }
  $sql = "SELECT f.*, 
            (SELECT MAX(version) FROM file_versions v WHERE v.file_id=f.id) AS latest_version,
            (SELECT size_bytes FROM file_versions v WHERE v.file_id=f.id AND v.version=(SELECT MAX(version) FROM file_versions v2 WHERE v2.file_id=f.id)) AS size_bytes,
            (SELECT CONCAT(u.first_name,' ',u.last_name) FROM users u WHERE u.id=f.created_by) AS uploader_name,
            (SELECT MAX(created_at) FROM file_versions v WHERE v.file_id=f.id) AS last_changed
          FROM project_files f WHERE " . implode(' AND ', $conds) . " ORDER BY f.filename";
  $stm = $pdo->prepare($sql); $stm->execute($params); return $stm->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function deleteFile(PDO $pdo, int $fileId, int $userId): bool {
  $stm = $pdo->prepare("SELECT project_id, filename FROM project_files WHERE id=:id");
  $stm->execute([':id'=>$fileId]); $row = $stm->fetch(PDO::FETCH_ASSOC); if (!$row) return false;
  addActivity($pdo, (int)$row['project_id'], $userId, 'file.delete', $row['filename']);
  $stm = $pdo->prepare("UPDATE project_files SET is_deleted=1 WHERE id=:id"); return $stm->execute([':id'=>$fileId]);
}
