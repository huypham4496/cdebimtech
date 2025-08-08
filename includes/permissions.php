<?php
// includes/permissions.php
declare(strict_types=1);

/**
 * Usage in protected pages (examples):
 *
 * // pages/work_diary.php (top of file, after session + $pdo)
 * require_once __DIR__ . '/../includes/permissions.php';
 * guardWorkDiaryAccess($pdo, (int)$_SESSION['user']['id']);
 *
 * // pages/organization_manage.php
 * require_once __DIR__ . '/../includes/permissions.php';
 * guardOrganizationManageAccess($pdo, (int)$_SESSION['user']['id']);
 *
 * // pages/organization_members.php
 * require_once __DIR__ . '/../includes/permissions.php';
 * guardOrganizationMembersAccess($pdo, (int)$_SESSION['user']['id']);
 */

function getUserPlanInfo(PDO $pdo, int $userId): ?array {
    $sql = "SELECT s.name AS plan_name, s.allow_work_diary
              FROM users u
              JOIN subscriptions s ON s.id = u.subscription_id
             WHERE u.id = :uid
             LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':uid' => $userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Cache subscriptions columns */
function getSubscriptionsColumns(PDO $pdo): array {
    static $cols = null;
    if ($cols !== null) return $cols;

    $cols = [];
    try {
        $q = $pdo->query("SHOW COLUMNS FROM `subscriptions`");
        while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($r['Field'])) $cols[] = $r['Field'];
        }
    } catch (\Throwable $e) {
        // ignore, will behave as "not configured"
    }
    return $cols;
}

/** Pick the first existing column from a list of candidates */
function resolveFeatureColumn(PDO $pdo, array $candidates): ?string {
    $cols = getSubscriptionsColumns($pdo);
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

/** Generic guard that tries multiple candidate columns safely */
function guardFeatureAccessFlexible(PDO $pdo, int $userId, array $candidateColumns, string $featureLabel): void {
    $col = resolveFeatureColumn($pdo, $candidateColumns);

    // Fallback plan name for message
    $info     = getUserPlanInfo($pdo, $userId);
    $planName = $info['plan_name'] ?? 'Unknown';

    if ($col === null) {
        // Feature column not configured => block with helpful message
        renderNoAccessPage($pdo, $planName, $featureLabel . ' (not configured)');
    }

    // Safe, because $col is resolved from actual table columns (no injection)
    $sql = "SELECT s.name AS plan_name, s.`$col` AS allowed
              FROM users u
              JOIN subscriptions s ON s.id = u.subscription_id
             WHERE u.id = :uid
             LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->execute([':uid' => $userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $planName = $row['plan_name'] ?? $planName;
    $allowed  = isset($row['allowed']) ? (int)$row['allowed'] : 0;

    if ($allowed !== 1) {
        renderNoAccessPage($pdo, $planName, $featureLabel);
    }
}

/** Existing guard for Work Diary (kept for compatibility) */
function guardFeatureAccess(PDO $pdo, int $userId, string $featureColumn, string $featureLabel): void {
    // 1) Lấy tên gói (fallback dùng cho UI)
    $info     = getUserPlanInfo($pdo, $userId);
    $planName = $info['plan_name'] ?? 'Unknown';

    // 2) Kiểm tra cột quyền: xem subscriptions.`$featureColumn` có = 1 không
    //    Dùng fetchColumn() cho gọn, ép kiểu về int, NULL coi như 0.
    $sql = "SELECT s.`$featureColumn`
              FROM users u
              JOIN subscriptions s ON s.id = u.subscription_id
             WHERE u.id = :uid
             LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->execute([':uid' => $userId]);
    $allowed = (int)($st->fetchColumn() ?? 0);

    if ($allowed !== 1) {
        renderNoAccessPage($pdo, $planName, $featureLabel);
    }
}

/** ---------------- Specific guards (fixed columns) ---------------- */
function guardWorkDiaryAccess(PDO $pdo, int $userId): void {
    // subscriptions.allow_work_diary = 1 mới được vào
    guardFeatureAccess($pdo, $userId, 'allow_work_diary', 'Work Diary');
}

function guardOrganizationManageAccess(PDO $pdo, int $userId): void {
    // subscriptions.allow_organization_manage = 1
    guardFeatureAccess($pdo, $userId, 'allow_organization_manage', 'Organization Manage');
}

function guardOrganizationMembersAccess(PDO $pdo, int $userId): void {
    // subscriptions.allow_organization_members = 1
    guardFeatureAccess($pdo, $userId, 'allow_organization_members', 'Organization Members');
}
/** Render 403 with your header + pages/sidebar.php (PDO passed for sidebar usage) */
function renderNoAccessPage(PDO $pdo, string $planName, string $featureLabel): void {
    http_response_code(403);

    $ROOT = dirname(__DIR__);

    // Keep your existing layout
    if (is_file($ROOT . '/includes/header.php'))  require $ROOT . '/includes/header.php';
    if (is_file($ROOT . '/pages/sidebar.php'))    require $ROOT . '/pages/sidebar.php';

    // Icons + scoped CSS (does not touch sidebar.css)
    $homeHref    = 'home.php';
    $upgradeHref = 'subscriptions.php'; // English menu agreed

    ?>
    <link rel="stylesheet" href="../assets/css/permissions.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/permissions.css'); ?>">
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/sidebar.css'); ?>">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        integrity="sha512-dynvDxJ5aVF6oU1i6zfoalvVYvNvKcJste/0q5u+P%2FgPm4jG3E5s3UeJ8V+RaH59RUW2YCiMzZ6pyRrg58F3CA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <main class="perm-guard">
      <section class="perm-card" role="alert" aria-live="polite">
        <div class="perm-badge">403 Forbidden</div>

        <div class="perm-icon" aria-hidden="true">
          <i class="fa-solid fa-lock"></i>
        </div>

        <h1 class="perm-title">Access denied</h1>
        <p class="perm-desc">
          Your current plan <strong><?= htmlspecialchars($planName, ENT_QUOTES, 'UTF-8') ?></strong>
          does not include <strong><?= htmlspecialchars($featureLabel, ENT_QUOTES, 'UTF-8') ?></strong>.
        </p>

        <div class="perm-meta" aria-label="Plan and feature">
          <div class="meta-item">
            <i class="fa-solid fa-box-open" aria-hidden="true"></i>
            <span>Plan: <b><?= htmlspecialchars($planName, ENT_QUOTES, 'UTF-8') ?></b></span>
          </div>
          <div class="meta-item">
            <i class="fa-solid fa-toolbox" aria-hidden="true"></i>
            <span>Feature: <b><?= htmlspecialchars($featureLabel, ENT_QUOTES, 'UTF-8') ?></b></span>
          </div>
        </div>

        <div class="perm-actions">
          <a class="btn btn-primary" href="<?= htmlspecialchars($upgradeHref, ENT_QUOTES, 'UTF-8') ?>">
            Upgrade plan
          </a>
          <a class="btn btn-ghost" href="<?= htmlspecialchars($homeHref, ENT_QUOTES, 'UTF-8') ?>">
            Back to Home
          </a>
        </div>

        <div class="perm-tip">
          Need help? <a href="contact.php">Contact administrator</a>.
        </div>
      </section>
    </main>
    <?php

    if (is_file($ROOT . '/includes/footer.php')) require $ROOT . '/includes/footer.php';
    exit;
}
