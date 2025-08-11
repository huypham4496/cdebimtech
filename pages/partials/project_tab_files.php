<?php
$folderId = (int)($_GET['folder'] ?? 0);
if ($folderId === 0) {
  $stm = $pdo->prepare("SELECT id FROM project_folders WHERE project_id=:pid AND parent_id IS NULL");
  $stm->execute([':pid'=>$projectId]);
  $rootId = (int)($stm->fetchColumn() ?: 0);
  if (!$rootId) { $rootId = createFolder($pdo, $projectId, null, $project['code'], $userId); }
  $folderId = $rootId;
}
$search = $_GET['q'] ?? null; $tag = $_GET['tag'] ?? null;
$tree = listFolderTree($pdo, $projectId); $files = listFolderFiles($pdo, $projectId, $folderId, $search, $tag);
$canManage = canManageProject($pdo, $userId, $projectId);
?>
<div style="display:grid;grid-template-columns:260px 1fr;gap:12px;">
  <aside class="card-sm">
    <form method="get">
      <input type="hidden" name="id" value="<?= (int)$projectId ?>"><input type="hidden" name="tab" value="files">
      <input type="text" name="q" placeholder="Tìm (*abc, .pdf)" value="<?= htmlspecialchars($search ?? '') ?>" style="width:100%;margin-bottom:6px;">
      <select name="tag" style="width:100%;margin-bottom:6px;">
        <option value="">--Tag--</option><?php foreach (['WIP','Shared','Published','Archived'] as $t): ?><option value="<?= $t ?>" <?= $tag===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
      </select><button class="btn btn-primary" type="submit">Lọc</button>
    </form><hr><div><strong>Thư mục</strong><ul style="list-style:none;padding-left:0;">
      <?php foreach ($tree as $f): ?><li><a href="<?= $BASE ?>/pages/project_view.php?id=<?= (int)$projectId ?>&tab=files&folder=<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['name']) ?></a></li><?php endforeach; ?>
    </ul>
    <?php if ($canManage): ?><form method="post" action="<?= $BASE ?>/pages/project_files.php" style="margin-top:8px;">
      <input type="hidden" name="action" value="create_folder"><input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
      <input type="hidden" name="parent_id" value="<?= (int)$folderId ?>"><input type="text" name="name" placeholder="Tên thư mục">
      <button class="btn btn-primary" type="submit">Create folder</button></form><?php endif; ?>
  </aside>
  <section class="card-sm">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <?php if ($canManage): ?><form method="post" action="<?= $BASE ?>/pages/project_files.php" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center;">
        <input type="hidden" name="action" value="upload"><input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
        <input type="hidden" name="folder_id" value="<?= (int)$folderId ?>"><input type="file" name="files[]" multiple required>
        <button class="btn btn-primary" type="submit">Upload</button></form>
      <button class="btn" disabled title="Sắp có">Download (zip)</button><button class="btn" disabled title="Sắp có">Delete</button><?php endif; ?>
    </div>
    <table class="org-table" style="margin-top:8px;"><thead><tr>
      <th>Select</th><th>Quan trọng</th><th>Tag</th><th>Tên</th><th>Version</th><th>Người upload</th><th>Kích thước</th><th>Ngày đổi</th><th>Hành động</th>
    </tr></thead><tbody>
      <?php foreach ($files as $f): ?><tr>
        <td><input type="checkbox" name="sel[]" value="<?= (int)$f['id'] ?>"></td>
        <td><?= $f['is_important'] ? '★' : '' ?></td><td><?= htmlspecialchars($f['tag']) ?></td>
        <td><?= htmlspecialchars($f['filename']) ?></td><td><?= (int)($f['latest_version'] ?? 1) ?></td>
        <td><?= htmlspecialchars($f['uploader_name'] ?? '') ?></td><td><?= htmlspecialchars(formatBytes((int)($f['size_bytes'] ?? 0))) ?></td>
        <td><?= htmlspecialchars($f['last_changed'] ?? '') ?></td><td><a href="#" onclick="alert('Sắp có');return false;">⋮</a></td>
      </tr><?php endforeach; ?><?php if (!$files): ?><tr><td colspan="9"><em>Thư mục trống.</em></td></tr><?php endif; ?>
    </tbody></table>
  </section>
</div>
