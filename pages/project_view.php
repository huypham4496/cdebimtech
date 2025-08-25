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
if (session_status() === PHP_SESSION_NONE) session_start();

/** Lấy userId từ session (giữ logic đa nguồn của bạn nếu muốn) */
$cands = [
  $_SESSION['user_id'] ?? null,
  $_SESSION['id'] ?? null,
  $_SESSION['user']['id'] ?? null,
  $_SESSION['auth']['user_id'] ?? null,
  $_SESSION['auth']['id'] ?? null
];
$userId = 0; foreach ($cands as $v) { if (is_numeric($v) && (int)$v>0) { $userId = (int)$v; break; } }
if (!$userId) { header('Location: /index.php'); exit; }
// ==== GATEWAY for Meetings AJAX (place BEFORE any HTML output) ====
if (isset($_GET['ajax_meetings']) || isset($_POST['ajax_meetings'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Cấp context cho partial nếu request có truyền
    $project_id = isset($_REQUEST['project_id']) ? (int)$_REQUEST['project_id'] : (isset($project_id)?(int)$project_id:0);
    $current_user_id = isset($_REQUEST['user_id']) ? (int)$_REQUEST['user_id']
        : (isset($current_user_id)?(int)$current_user_id : (isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:0));

    require __DIR__ . '/partials/project_tab_meetings.php';
    exit; // trả JSON, không render HTML
}
/** ===== AJAX proxy for Daily Logs (MUST be before any HTML or guards) ===== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'daily') {
    // Nhận project id từ project_id hoặc id (ưu tiên project_id)
    if (!isset($projectId)) {
        $projectId = (int)($_GET['project_id'] ?? $_GET['id'] ?? 0);
    } else {
        if (isset($_GET['project_id'])) $projectId = (int)$_GET['project_id'];
        elseif (isset($_GET['id']))     $projectId = (int)$_GET['id'];
    }

    // Dọn buffer và tắt hiển thị warning để JSON không bị bẩn
    while (ob_get_level()) { ob_end_clean(); }
    ini_set('display_errors', '0');

    // Require partial trực tiếp từ /pages/partials (KHÔNG qua helpers)
    $partial = __DIR__ . '/partials/project_tab_daily.php';
    if (!is_file($partial)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'message'=>"Partial not found: $partial"]);
        exit;
    }
    require $partial;
    exit;
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
$meetingId  = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;
if (!isset($tabs[$tab])) $tab = 'overview';

$statusClass = ($project['status'] ?? 'active') === 'completed' ? 'completed' : 'active';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($project['name']) ?> · Projects</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/permissions.css">
  <link rel="stylesheet" href="../assets/css/projects.css">
  <link rel="stylesheet" href="../assets/css/sidebar.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
  <script src="../assets/js/projects.js" defer></script>
</head>
<body>
<?php if (is_file($ROOT . '/pages/sidebar.php')) require $ROOT . '/pages/sidebar.php'; ?>

<main class="pv-container with-sidebar">
  <header class="pv-header">
    <div class="pv-title">
      <h1><?= htmlspecialchars($project['name']) ?></h1>
      <div class="pv-meta">
        <span class="pv-code">Code: <strong><?= htmlspecialchars($project['code']) ?></strong></span>
        <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($project['status'])) ?></span>
      </div>
    </div>
    <div class="pv-actions">
      <!-- Actions (edit, export, etc.) -->
    </div>
  </header>

  <nav class="pv-tabs" role="tablist">
    <?php foreach ($tabs as $k=>$label): ?>
      <a class="pv-tab <?= $tab===$k ? 'active':'' ?>" href="project_view.php?id=<?= (int)$projectId ?>&tab=<?= $k ?>" role="tab" aria-selected="<?= $tab===$k ? 'true':'false' ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
    <span class="pv-tab-indicator"></span>
  </nav>

<section class="pv-content">
  <?php
    if ($tab === 'meetings' && $meetingId > 0) {
      // Khi có meeting_id → hiển thị trang chi tiết cuộc họp
      include $ROOT . '/pages/partials/project_tab_meetings_detail.php';
    } else {
      // Mặc định: hiển thị partial theo tab
      include $ROOT . '/pages/partials/project_tab_' . $tab . '.php';
    }
  ?>
</section>
</main>

<script>
// Move underline to active tab
(function(){
  const nav = document.querySelector('.pv-tabs');
  const indicator = nav?.querySelector('.pv-tab-indicator');
  function positionIndicator(){
    const active = nav?.querySelector('.pv-tab.active');
    if (!nav || !indicator || !active) return;
    const a = active.getBoundingClientRect();
    const n = nav.getBoundingClientRect();
    indicator.style.width = a.width + 'px';
    indicator.style.transform = 'translateX(' + (a.left - n.left) + 'px)';
  }
  positionIndicator();
  window.addEventListener('resize', positionIndicator);
})();
</script>

</body>
</html>
