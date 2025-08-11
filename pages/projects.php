<?php
declare(strict_types=1);
session_start();
$ROOT = realpath(__DIR__ . '/..');
$BASE = $BASE ?? '';

require $ROOT . '/config.php';
require $ROOT . '/includes/permissions.php';
require $ROOT . '/includes/helpers.php';
require $ROOT . '/includes/projects.php';
require $ROOT . '/includes/files.php';

$userId = userIdOrRedirect();
guardProjectsAccess($pdo, $userId);

$limitReached = userProjectLimitReached($pdo, $userId);
$sub = currentUserSubscription($pdo, $userId);
$maxProjects = (int)($sub['max_projects'] ?? 0);

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='create') {
  if ($limitReached) { $errors[] = 'Đã đạt giới hạn số lượng project theo gói của bạn.'; }
  else {
    $name = trim($_POST['name'] ?? ''); $orgId = (int)($_POST['organization_id'] ?? 0);
    if ($name === '' || $orgId <= 0) { $errors[] = 'Thiếu Tên dự án hoặc Tổ chức (ID).'; }
    else {
      $pid = createProject($pdo, $userId, $orgId, [
        'name'=>$name,'status'=>($_POST['status'] ?? 'active')==='completed' ? 'completed':'active',
        'start_date'=>$_POST['start_date'] ?? null,'end_date'=>$_POST['end_date'] ?? null,'manager_id'=>$userId,
        'visibility'=>$_POST['visibility'] ?? 'org','description'=>$_POST['description'] ?? null,
        'location'=>$_POST['location'] ?? null,'tags'=>$_POST['tags'] ?? null,
      ]);
      header('Location: ' . $BASE . '/pages/project_view.php?id=' . $pid); exit;
    }
  }
}
$projects = listProjectsForUser($pdo, $userId);
?><!doctype html>
<html lang="vi"><head>
  <meta charset="utf-8"><title>Projects</title><meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= $BASE ?>/../assets/css/sidebar.css">
  <link rel="stylesheet" href="<?= $BASE ?>/../assets/css/permissions.css">
  <link rel="stylesheet" href="<?= $BASE ?>/../assets/css/projects.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
</head><body>
<?php if (is_file($ROOT . '/pages/sidebar.php')) require $ROOT . '/pages/sidebar.php'; ?>
<main class="container">
  <section class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <h2><i class="fas fa-folder-open"></i> Dự án của bạn</h2>
      <form method="post" style="margin:0;">
        <input type="hidden" name="action" value="create">
        <div class="inline-form">
          <input type="text" name="name" placeholder="Tên Project" required>
          <input type="number" name="organization_id" placeholder="Tổ chức (ID)" required min="1">
          <input type="text" name="location" placeholder="Vị trí">
          <select name="status"><option value="active">Đang hoạt động</option><option value="completed">Đã hoàn thành</option></select>
          <select name="visibility"><option value="org">Trong tổ chức</option><option value="private">Riêng tư</option><option value="public">Công khai</option></select>
          <input type="text" name="tags" placeholder="Tag (VD: Feasibility Study)">
          <button class="btn btn-primary" type="submit" <?= $limitReached ? 'disabled' : '' ?>><i class="fas fa-plus-circle"></i> Create</button>
        </div>
        <?php if ($limitReached): ?><div class="alert alert-warning" style="margin-top:8px;">
          Đã đạt giới hạn số lượng project theo gói (max: <?= htmlspecialchars((string)$maxProjects) ?>). Nâng cấp để tiếp tục tạo mới.
        </div><?php endif; ?>
      </form>
    </div>
    <?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>
    <table class="org-table" style="margin-top:12px;">
      <thead><tr><th>Mã</th><th>Tên</th><th>Trạng thái</th><th>Vị trí</th><th>Tags</th><th>Tạo bởi</th><th>Hành động</th></tr></thead>
      <tbody>
        <?php foreach ($projects as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['code']) ?></td>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><?= htmlspecialchars($p['status']) ?></td>
            <td><?= htmlspecialchars($p['location'] ?? '') ?></td>
            <td><?= htmlspecialchars($p['tags'] ?? '') ?></td>
            <td>#<?= (int)$p['created_by'] ?></td>
            <td><a class="btn btn-sm" href="<?= $BASE ?>/pages/project_view.php?id=<?= (int)$p['id'] ?>"><i class="fas fa-eye"></i> Quản lý</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$projects): ?><tr><td colspan="7"><em>Chưa có project.</em></td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</main></body></html>
