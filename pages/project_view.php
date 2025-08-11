<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = realpath(__DIR__ . '/..'); $BASE = $BASE ?? '';
require $ROOT . '/config.php'; require $ROOT . '/includes/permissions.php';
require $ROOT . '/includes/helpers.php'; require $ROOT . '/includes/projects.php'; require $ROOT . '/includes/files.php';
$userId = $_SESSION['user_id'] ?? 0; if (!$userId) { header('Location: /index.php'); exit; }
$projectId = (int)($_GET['id'] ?? 0); $project = getProject($pdo, $projectId);
if (!$project || !canViewProject($pdo, $userId, $projectId)) { http_response_code(404); echo "Project not found or access denied."; exit; }
guardProjectsAccess($pdo, $userId);
$tabs = ['overview'=>'Tổng quan','files'=>'Tập tin','federation'=>'Mô hình tổng hợp','issues'=>'Vấn đề','gis'=>'Mô hình GIS','kmz'=>'Google KMZ','approvals'=>'Phê duyệt','daily'=>'Nhật ký thi công','materials'=>'Quản lý vật tư','naming'=>'Danh sách file','colors'=>'Màu sắc','meetings'=>'Biên bản họp','members'=>'Thành viên','history'=>'Lịch sử'];
$tab = $_GET['tab'] ?? 'overview'; if (!isset($tabs[$tab])) $tab = 'overview';
?><!doctype html><html lang="vi"><head>
  <meta charset="utf-8"><title><?= htmlspecialchars($project['name']) ?> · Projects</title><meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= $BASE ?>/../assets/css/sidebar.css"><link rel="stylesheet" href="<?= $BASE ?>/../assets/css/permissions.css"><link rel="stylesheet" href="<?= $BASE ?>/../assets/css/projects.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/><script src="<?= $BASE ?>/../assets/js/projects.js" defer></script>
</head><body>
<?php if (is_file($ROOT . '/pages/sidebar.php')) require $ROOT . '/pages/sidebar.php'; ?>
<main class="container"><section class="card">
  <h2 style="margin-bottom:8px;"><?= htmlspecialchars($project['name']) ?> <small style="font-weight:400;color:#94a3b8;">(<?= htmlspecialchars($project['code']) ?>)</small></h2>
  <nav class="tabs"><?php foreach ($tabs as $k=>$label): ?><a class="tab <?= $tab===$k ? 'active':'' ?>" href="<?= $BASE ?>/pages/project_view.php?id=<?= (int)$projectId ?>&tab=<?= $k ?>"><?= $label ?></a><?php endforeach; ?></nav>
  <div class="tab-content" style="margin-top:16px;"><?php include $ROOT . '/pages/partials/project_tab_' . $tab . '.php'; ?></div>
</section></main></body></html>
