<?php
declare(strict_types=1);

/**
 * Trả về user_id từ nhiều dạng session phổ biến.
 * Nếu không có -> redirect về home.php (nếu tồn tại) hoặc index.php.
 */
function userIdOrRedirect(): int {
  $cands = [
    $_SESSION['user_id'] ?? null,
    $_SESSION['id'] ?? null,
    $_SESSION['user']['id'] ?? null,
    $_SESSION['auth']['user_id'] ?? null,
    $_SESSION['auth']['id'] ?? null,
  ];
  foreach ($cands as $v) {
    if (is_numeric($v) && (int)$v > 0) return (int)$v;
  }
  // Redirect đích: ưu tiên /pages/home.php nếu tồn tại, ngược lại /index.php
  $target = '/pages/home.php';
  if (!isset($_SERVER['DOCUMENT_ROOT']) || !is_file($_SERVER['DOCUMENT_ROOT'] . $target)) {
    $target = '/index.php';
  }
  header('Location: ' . $target);
  exit;
}

function ensureProjectRoot(): string {
  $ROOT = realpath(__DIR__ . '/..');
  $dataDir = $ROOT . '/data';
  if (!is_dir($dataDir)) { @mkdir($dataDir, 0775, true); }
  return $dataDir;
}
function generateProjectCode(PDO $pdo): string {
  $stmt = $pdo->query("SELECT MAX(id) FROM projects");
  $max = (int)$stmt->fetchColumn();
  $next = $max + 1;
  return 'PRJ' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}
function formatBytes(int $bytes): string {
  $units = ['B','KB','MB','GB','TB']; $i = 0;
  while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
  return sprintf('%.2f %s', $bytes, $units[$i]);
}
function respondJSON($data, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function addActivity(PDO $pdo, int $projectId, int $userId, string $action, ?string $detail=null): void {
  $stm = $pdo->prepare("INSERT INTO project_activities(project_id,user_id,action,detail) VALUES (:pid,:uid,:ac,:dt)");
  @$stm->execute([':pid'=>$projectId, ':uid'=>$userId, ':ac'=>$action, ':dt'=>$detail]);
}
function prjCodeDir(string $project_code): string { return ensureProjectRoot() . '/' . $project_code; }
function ensureProjectDir(string $project_code): string {
  $dir = prjCodeDir($project_code);
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  return $dir;
}
