<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = realpath(__DIR__ . '/..'); $BASE = $BASE ?? '';
require $ROOT . '/config.php'; require $ROOT . '/includes/permissions.php';
require $ROOT . '/includes/helpers.php'; require $ROOT . '/includes/projects.php'; require $ROOT . '/includes/files.php';
$uid = $_SESSION['user_id'] ?? 0; if (!$uid) { header('Location: /index.php'); exit; }
$action = $_POST['action'] ?? ''; $pid = (int)($_POST['project_id'] ?? 0);
$project = getProject($pdo, $pid); if (!$project || !canViewProject($pdo, $uid, $pid)) { http_response_code(403); echo "Forbidden"; exit; }
if ($action === 'create_folder') {
  if (!canManageProject($pdo, $uid, $pid)) { http_response_code(403); exit('No permission'); }
  $name = trim($_POST['name'] ?? ''); $parent = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
  if ($name !== '') { createFolder($pdo, $pid, $parent, $name, $uid); }
  header('Location: ' . $BASE . '/pages/project_view.php?id='.$pid.'&tab=files&folder='.(int)$parent); exit;
}
if ($action === 'upload') {
  if (!canManageProject($pdo, $uid, $pid)) { http_response_code(403); exit('No permission'); }
  $folderId = (int)($_POST['folder_id'] ?? 0); $dir = ensureProjectDir($project['code']);
  if (!empty($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
    for ($i=0; $i<count($_FILES['files']['name']); $i++) {
      $name = $_FILES['files']['name'][$i]; $tmp = $_FILES['files']['tmp_name'][$i]; $size = (int)$_FILES['files']['size'][$i];
      if (!is_uploaded_file($tmp)) continue;
      $stm = $pdo->prepare("SELECT id FROM project_files WHERE project_id=:pid AND folder_id=:fid AND filename=:fn AND is_deleted=0");
      $stm->execute([':pid'=>$pid, ':fid'=>$folderId, ':fn'=>$name]); $fileId = (int)($stm->fetchColumn() ?: 0);
      if (!$fileId) { $fileId = registerFile($pdo, $pid, $folderId, $name, $uid, 'WIP', false); }
      $dot = strrpos($name, '.'); $base = $dot!==false ? substr($name,0,$dot) : $name; $ext = $dot!==false ? substr($name,$dot) : '';
      $version = (int)($pdo->query("SELECT COALESCE(MAX(version),0)+1 FROM file_versions WHERE file_id=".(int)$fileId)->fetchColumn());
      $destVersioned = $dir . '/' . $base . '_v' . $version . $ext;
      if (!@move_uploaded_file($tmp, $destVersioned)) { @copy($tmp, $destVersioned); }
      addFileVersion($pdo, $fileId, $destVersioned, $size, $uid);
    }
  }
  header('Location: ' . $BASE . '/pages/project_view.php?id='.$pid.'&tab=files&folder='.$folderId); exit;
}
http_response_code(400); echo "Unknown action";
