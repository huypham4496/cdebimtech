<?php
declare(strict_types=1);

/**
 * helpers.php — merged utilities + subscription resolver
 * Rule: currentUserSubscription() reads users.subscription_id -> subscriptions.id
 * Fallbacks remain for compatibility if schema differs.
 */

/** ---------- Basic session/user helpers ---------- */
if (!function_exists('userIdOrRedirect')) {
  function userIdOrRedirect(): int {
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    $cands = [
      $_SESSION['user_id'] ?? null,
      $_SESSION['id'] ?? null,
      $_SESSION['user']['id'] ?? null,
      $_SESSION['auth']['user_id'] ?? null,
      $_SESSION['auth']['id'] ?? null,
    ];
    foreach ($cands as $v) if (is_numeric($v) && (int)$v > 0) return (int)$v;
    $target = '/pages/home.php';
    if (!isset($_SERVER['DOCUMENT_ROOT']) || !is_file($_SERVER['DOCUMENT_ROOT'] . $target)) $target = '/index.php';
    header('Location: ' . $target); exit;
  }
}

/** ---------- IO / util helpers ---------- */
if (!function_exists('ensureProjectRoot')) {
  function ensureProjectRoot(): string {
    $ROOT = realpath(__DIR__ . '/..');
    $dataDir = $ROOT . '/data';
    if (!is_dir($dataDir)) { @mkdir($dataDir, 0775, true); }
    return $dataDir;
  }
}
if (!function_exists('generateProjectCode')) {
  function generateProjectCode(PDO $pdo): string {
    $stmt = $pdo->query("SELECT MAX(id) FROM projects");
    $max = (int)$stmt->fetchColumn();
    $next = $max + 1;
    return 'PRJ' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
  }
}
if (!function_exists('formatBytes')) {
  function formatBytes(int $bytes): string {
    $units = ['B','KB','MB','GB','TB']; $i = 0;
    while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
    return sprintf('%.2f %s', $bytes, $units[$i]);
  }
}
if (!function_exists('respondJSON')) {
  function respondJSON($data, int $code=200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
  }
}
if (!function_exists('addActivity')) {
  function addActivity(PDO $pdo, int $projectId, int $userId, string $action, ?string $detail=null): void {
    $stm = $pdo->prepare("INSERT INTO project_activities(project_id,user_id,action,detail) VALUES (:pid,:uid,:ac,:dt)");
    @$stm->execute([':pid'=>$projectId, ':uid'=>$userId, ':ac'=>$action, ':dt'=>$detail]);
  }
}
if (!function_exists('prjCodeDir')) { function prjCodeDir(string $project_code): string { return ensureProjectRoot() . '/' . $project_code; } }
if (!function_exists('ensureProjectDir')) {
  function ensureProjectDir(string $project_code): string {
    $dir = prjCodeDir($project_code);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir;
  }
}

/** ---------- Tiny schema helpers ---------- */
if (!function_exists('cde_table_exists')) {
  function cde_table_exists(PDO $pdo, string $table): bool {
    try { $stm = $pdo->prepare("SHOW TABLES LIKE :t"); $stm->execute([':t'=>$table]); return (bool)$stm->fetchColumn(); }
    catch (Throwable $e) { return false; }
  }
}
if (!function_exists('cde_column_exists')) {
  function cde_column_exists(PDO $pdo, string $table, string $column): bool {
    try { $stm = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c"); $stm->execute([':c'=>$column]); return (bool)$stm->fetch(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return false; }
  }
}
if (!function_exists('cde_first_existing_column')) {
  function cde_first_existing_column(PDO $pdo, string $table, array $candidates): ?string {
    foreach ($candidates as $c) if (cde_column_exists($pdo, $table, $c)) return $c;
    return null;
  }
}

/** ---------- Organization helpers ---------- */
if (!function_exists('userOrgIds')) {
  function userOrgIds(PDO $pdo, int $userId): array {
    $ids = [];
    if (cde_table_exists($pdo,'organization_members') && cde_column_exists($pdo,'organization_members','organization_id') && cde_column_exists($pdo,'organization_members','user_id')) {
      $stm = $pdo->prepare("SELECT organization_id FROM organization_members WHERE user_id=:uid");
      $stm->execute([':uid'=>$userId]);
      $ids = array_map('intval', array_column($stm->fetchAll(), 'organization_id'));
    }
    if (!$ids && cde_table_exists($pdo,'users') && cde_column_exists($pdo,'users','organization_id')) {
      $stm = $pdo->prepare("SELECT organization_id FROM users WHERE id=:uid");
      $stm->execute([':uid'=>$userId]);
      $oid = (int)($stm->fetchColumn() ?: 0);
      if ($oid) $ids = [$oid];
    }
    if (!$ids && cde_table_exists($pdo,'organizations') && cde_column_exists($pdo,'organizations','owner_id')) {
      $stm = $pdo->prepare("SELECT id FROM organizations WHERE owner_id=:uid");
      $stm->execute([':uid'=>$userId]);
      $ids = array_map('intval', array_column($stm->fetchAll(), 'id'));
    }
    return array_values(array_unique(array_filter($ids)));
  }
}

/** ---------- Subscription resolver (per your rule) ----------
 * Primary rule:
 *   users.subscription_id  --> subscriptions.id
 * Fallbacks:
 *   - direct mapping columns in subscriptions (user_id/account_id/member_id/owner_id/customer_id)
 *   - org-based mapping (organization_id/org_id with user's orgs)
 *   - latest subscription row
 */
if (!function_exists('currentUserSubscription')) {
  function currentUserSubscription(PDO $pdo, int $userId): ?array {
    // Primary: users.subscription_id -> subscriptions.id
    if (cde_table_exists($pdo, 'users') && cde_column_exists($pdo, 'users', 'subscription_id')) {
      $stm = $pdo->prepare("SELECT subscription_id FROM users WHERE id=:uid LIMIT 1");
      $stm->execute([':uid'=>$userId]);
      $sid = (int)($stm->fetchColumn() ?: 0);
      if ($sid > 0 && cde_table_exists($pdo, 'subscriptions') && cde_column_exists($pdo, 'subscriptions', 'id')) {
        $stm2 = $pdo->prepare("SELECT * FROM subscriptions WHERE id=:sid LIMIT 1");
        $stm2->execute([':sid'=>$sid]);
        $row = $stm2->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
      }
    }

    // Fallback 1: direct user mapping
    if (cde_table_exists($pdo, 'subscriptions')) {
      $userKey = cde_first_existing_column($pdo, 'subscriptions', ['user_id','account_id','member_id','owner_id','customer_id']);
      if ($userKey) {
        $stm = $pdo->prepare("SELECT * FROM subscriptions WHERE `$userKey`=:val ORDER BY id DESC LIMIT 1");
        $stm->execute([':val'=>$userId]);
        $row = $stm->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
      }
      // Fallback 2: org mapping
      $orgKey = cde_first_existing_column($pdo, 'subscriptions', ['organization_id','org_id']);
      if ($orgKey) {
        $orgIds = userOrgIds($pdo, $userId);
        if ($orgIds) {
          $in = implode(',', array_fill(0, count($orgIds), '?'));
          $stm = $pdo->prepare("SELECT * FROM subscriptions WHERE `$orgKey` IN ($in) ORDER BY id DESC LIMIT 1");
          $stm->execute($orgIds);
          $row = $stm->fetch(PDO::FETCH_ASSOC);
          if ($row) return $row;
        }
      }
      // Fallback 3: latest
      $stm = $pdo->query("SELECT * FROM subscriptions ORDER BY id DESC LIMIT 1");
      $row = $stm->fetch(PDO::FETCH_ASSOC);
      if ($row) return $row;
    }
    return null;
  }
}
