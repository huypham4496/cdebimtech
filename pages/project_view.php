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

$projectId = (int)($_GET['id'] ?? 0);
$project = getProject($pdo, $projectId);
if (!$project || !canViewProject($pdo, $userId, $projectId)) {
  http_response_code(404); echo "Project not found or access denied."; exit;
}

$current = 'projects.php';

$tabs = [
  'overview'=>'Tổng quan',
  'files'=>'Tập tin',
  'federation'=>'Mô hình tổng hợp',
  'issues'=>'Vấn đề',
  'gis'=>'Mô hình GIS',
  'kmz'=>'Google KMZ',
  'approvals'=>'Phê duyệt',
  'daily'=>'Nhật ký thi công',
  'materials'=>'Quản lý vật tư',
  'naming'=>'Danh sách file',
  'colors'=>'Màu sắc',
  'meetings'=>'Biên bản họp',
  'members'=>'Thành viên',
  'history'=>'Lịch sử'
];
$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) $tab = 'overview';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($project['name']) ?> · Projects</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/projects.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../assets/css/permissions.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo time(); ?>">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer"/>
  <script src="../assets/js/projects.js" defer></script>
</head>
<body>
<?php if (is_file($ROOT . '/pages/sidebar.php')) require $ROOT . '/pages/sidebar.php'; ?>
<main class="container">
  <section class="card">
    <h2 style="margin-bottom:8px;"><?= htmlspecialchars($project['name']) ?> <small style="font-weight:400;color:#94a3b8;">(<?= htmlspecialchars($project['code']) ?>)</small></h2>
    <nav class="tabs">
      <?php foreach ($tabs as $k=>$label): ?>
        <a class="tab <?= $tab===$k ? 'active':'' ?>" href="project_view.php?id=<?= (int)$projectId ?>&tab=<?= $k ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="tab-content" style="margin-top:16px;">
      <?php include $ROOT . '/pages/partials/project_tab_' . $tab . '.php'; ?>
    </div>
  </section>
</main>
</body>
</html>
