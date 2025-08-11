<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = realpath(__DIR__ . '/..');

require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/permissions.php';
require_once $ROOT . '/includes/helpers.php';
require_once $ROOT . '/includes/projects.php';
require_once $ROOT . '/includes/files.php';

/** Ensure $pdo is available (fallback if config.php didn't assign it) */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (function_exists('getPDO')) {
    $pdo = getPDO();
  } elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    try {
      $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
      $pdo = new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo 'DB connection failed: ' . htmlspecialchars($e->getMessage());
      exit;
    }
  } else {
    http_response_code(500);
    echo 'DB config not found. Make sure DB_* constants are defined in config.php';
    exit;
  }
}

// Always-on Projects: only check login (support multiple session keys)
$userId = userIdOrRedirect();

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='create') {
  $name = trim($_POST['name'] ?? '');
  $orgId = (int)($_POST['organization_id'] ?? 0);
  if ($name === '' || $orgId <= 0) {
    $errors[] = 'Thiếu Tên dự án hoặc Tổ chức (ID).';
  } else {
    $pid = createProject($pdo, $userId, $orgId, [
      'name'=>$name,
      'status'=>($_POST['status'] ?? 'active')==='completed' ? 'completed':'active',
      'start_date'=>$_POST['start_date'] ?? null,
      'end_date'=>$_POST['end_date'] ?? null,
      'manager_id'=>$userId,
      'visibility'=>$_POST['visibility'] ?? 'org',
      'description'=>$_POST['description'] ?? null,
      'location'=>$_POST['location'] ?? null,
      'tags'=>$_POST['tags'] ?? null,
    ]);
    header('Location: ./project_view.php?id=' . $pid);
    exit;
  }
}

$projects = listProjectsForUser($pdo, $userId);
$current = 'projects.php';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Projects</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/projects.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../assets/css/permissions.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo time(); ?>">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer"/>
</head>
<body>
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
          <select name="status">
            <option value="active">Đang hoạt động</option>
            <option value="completed">Đã hoàn thành</option>
          </select>
          <select name="visibility">
            <option value="org">Trong tổ chức</option>
            <option value="private">Riêng tư</option>
            <option value="public">Công khai</option>
          </select>
          <input type="text" name="tags" placeholder="Tag (VD: Feasibility Study)">
          <button class="btn btn-primary" type="submit">
            <i class="fas fa-plus-circle"></i> Create
          </button>
        </div>
      </form>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <table class="org-table" style="margin-top:12px;">
      <thead>
        <tr>
          <th>Mã</th><th>Tên</th><th>Trạng thái</th><th>Vị trí</th><th>Tags</th><th>Tạo bởi</th><th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($projects as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['code']) ?></td>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><?= htmlspecialchars($p['status']) ?></td>
            <td><?= htmlspecialchars($p['location'] ?? '') ?></td>
            <td><?= htmlspecialchars($p['tags'] ?? '') ?></td>
            <td>#<?= (int)$p['created_by'] ?></td>
            <td>
              <a class="btn btn-sm" href="project_view.php?id=<?= (int)$p['id'] ?>">
                <i class="fas fa-eye"></i> Quản lý
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$projects): ?>
          <tr><td colspan="7"><em>Chưa có project.</em></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>
</main>
</body>
</html>
