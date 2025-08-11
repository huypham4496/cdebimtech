<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = realpath(__DIR__ . '/..');

require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/permissions.php';
require_once $ROOT . '/includes/helpers.php';
require_once $ROOT . '/includes/projects.php';
require_once $ROOT . '/includes/files.php';

/** Ensure $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (function_exists('getPDO')) { $pdo = getPDO(); }
  elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.(defined('DB_CHARSET')?DB_CHARSET:'utf8mb4');
    $pdo = new PDO($dsn, DB_USER, defined('DB_PASS')?DB_PASS:'', [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } else { http_response_code(500); echo 'DB config missing.'; exit; }
}

/** User */
$cands = [$_SESSION['user_id'] ?? null, $_SESSION['id'] ?? null, $_SESSION['user']['id'] ?? null, $_SESSION['auth']['user_id'] ?? null, $_SESSION['auth']['id'] ?? null];
$userId = 0; foreach ($cands as $v) { if (is_numeric($v) && (int)$v>0) { $userId = (int)$v; break; } }
if (!$userId) { header('Location: /index.php'); exit; }

$projectId = (int)($_GET['id'] ?? 0);
$project = getProject($pdo, $projectId);
if (!$project || !canViewProject($pdo, $userId, $projectId)) {
  http_response_code(404); echo "Project not found or access denied."; exit;
}

$current = 'projects.php';
$tabs = [
  'overview'=>'Overview',
  'files'=>'Files',
  'federation'=>'Federated Model',
  'issues'=>'Issues',
  'gis'=>'GIS Model',
  'kmz'=>'Google KMZ',
  'approvals'=>'Approvals',
  'daily'=>'Daily Logs',
  'materials'=>'Materials',
  'naming'=>'Naming Rules',
  'colors'=>'Colors',
  'meetings'=>'Meetings',
  'members'=>'Members',
  'history'=>'History'
];
$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) $tab = 'overview';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($project['name']) ?> · Projects</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/permissions.css">
  <link rel="stylesheet" href="../assets/css/projects.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
  <script src="../assets/js/projects.js" defer></script>
</head>
<body>
<?php if (is_file($ROOT . '/pages/sidebar.php')) require $ROOT . '/pages/sidebar.php'; ?>
<main class="container">
  <section class="card">
    <div class="header">
      <div class="h-title"><?= htmlspecialchars($project['name']) ?> <span class="badge">Code: <?= htmlspecialchars($project['code']) ?></span></div>
      <div class="muted">Status: <strong><?= htmlspecialchars($project['status']) ?></strong></div>
    </div>
    <nav class="tabs">
      <?php foreach ($tabs as $k=>$label): ?>
        <a class="tab <?= $tab===$k ? 'active':'' ?>" href="project_view.php?id=<?= (int)$projectId ?>&tab=<?= $k ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div style="margin-top:12px">
      <?php include $ROOT . '/pages/partials/project_tab_' . $tab . '.php'; ?>
    </div>
  </section>
</main>
</body>
</html>
