<?php
// pages/admin/subscription_features.php
session_start();
require_once __DIR__ . '/../../config.php';

// (Optional) check admin role here if bạn có phân quyền admin
// if (empty($_SESSION['user']['is_admin'])) { header('Location: ../home.php'); exit; }

// PDO
$pdo = new PDO(
  "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
  DB_USER,
  DB_PASS,
  [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]
);

// Handle submission (safe reads + both new flags)
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['features']) && is_array($_POST['features'])) {
    $sql = "
        UPDATE subscriptions
           SET max_storage_gb             = :max_storage_gb,
               max_projects               = :max_projects,
               max_company_members        = :max_company_members,
               allow_organization_manage  = :allow_org_manage,
               allow_organization_members = :allow_org_members,
               allow_work_diary           = :allow_diary
         WHERE id = :id
    ";
    $stmt = $pdo->prepare($sql);

    $pdo->beginTransaction();
    try {
        foreach ($_POST['features'] as $id => $values) {
            // ensure array
            $values = is_array($values) ? $values : [];

            // numeric limits: blank => 0 (unlimited)
            $maxStorage = (isset($values['max_storage_gb'])      && $values['max_storage_gb']      !== '') ? (int)$values['max_storage_gb']      : 0;
            $maxProj    = (isset($values['max_projects'])         && $values['max_projects']         !== '') ? (int)$values['max_projects']         : 0;
            $maxMembers = (isset($values['max_company_members'])  && $values['max_company_members']  !== '') ? (int)$values['max_company_members']  : 0;

            // checkboxes: present => 1, missing => 0
            $allowOrgManage  = !empty($values['allow_organization_manage'])  ? 1 : 0;
            $allowOrgMembers = !empty($values['allow_organization_members']) ? 1 : 0;
            $allowDiary      = !empty($values['allow_work_diary'])           ? 1 : 0;

            $stmt->execute([
                ':max_storage_gb'      => $maxStorage,
                ':max_projects'        => $maxProj,
                ':max_company_members' => $maxMembers,
                ':allow_org_manage'    => $allowOrgManage,
                ':allow_org_members'   => $allowOrgMembers,
                ':allow_diary'         => $allowDiary,
                ':id'                  => (int)$id,
            ]);
        }
        $pdo->commit();
        $message = 'Features updated successfully.';
    } catch (Throwable $e) {
        $pdo->rollBack();
        // Bạn có thể log $e->getMessage()
        $message = 'Update failed. Please try again.';
    }
}

// Fetch plans
$plans = $pdo->query("
    SELECT id, name,
           COALESCE(max_storage_gb,0)      AS max_storage_gb,
           COALESCE(max_projects,0)        AS max_projects,
           COALESCE(max_company_members,0) AS max_company_members,
           COALESCE(allow_work_diary,0)           AS allow_work_diary,
           COALESCE(allow_organization_manage,0)  AS allow_organization_manage,
           COALESCE(allow_organization_members,0) AS allow_organization_members
      FROM subscriptions
  ORDER BY id
")->fetchAll();

// Include header + sidebar (giữ layout hệ thống)
$ROOT = dirname(__DIR__, 2);
if (is_file($ROOT . '/includes/header.php'))  require $ROOT . '/includes/header.php';
if (is_file($ROOT . '/pages/admin/sidebar_admin.php'))    require $ROOT . '/pages/admin/sidebar_admin.php';

?>
<!-- Font Awesome (đầy đủ) -->
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css"
      integrity="sha512-olb1y6Rv7uyYCRykpY7ZZ6vqpKILvQPWZG1aJeyeWQ1m/5nYy8WpuM8aOQW4ZcStSz2/fW+N5hWwcX96Iqb0FQ=="
      crossorigin="anonymous" referrerpolicy="no-referrer"/>
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?=filemtime(__DIR__.'/../../assets/css/sidebar_admin.css')?>">
  <link rel="stylesheet" href="../../assets/css/subscription_features.css?v=<?=filemtime(__DIR__.'/../../assets/css/subscription_features.css')?>">

<main class="sf-wrap">
  <div class="sf-card">
    <h1 class="sf-title"><i class="fa-solid fa-sliders"></i> Subscription Features</h1>

    <?php if (!empty($message)): ?>
      <div class="sf-msg <?= strpos($message, 'successfully') !== false ? 'success' : 'error' ?>">
        <i class="fa-solid <?= strpos($message, 'successfully') !== false ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" action="subscription_features.php" autocomplete="off">
      <table class="sf-table">
        <thead>
          <tr>
            <th style="width:60px;">ID</th>
            <th>Plan</th>
            <th title="0 means unlimited"><i class="fa-solid fa-database"></i> Max storage (GB) <div class="hint">(0 = unlimited)</div></th>
            <th title="0 means unlimited"><i class="fa-solid fa-diagram-project"></i> Max projects <div class="hint">(0 = unlimited)</div></th>
            <th title="0 means unlimited"><i class="fa-solid fa-users"></i> Max company members <div class="hint">(0 = unlimited)</div></th>
            <th style="width:140px;"><i class="fa-solid fa-clipboard-list"></i> Work Diary</th>
            <th style="width:200px;"><i class="fa-solid fa-sitemap"></i> Organization Management</th>
            <th style="width:200px;"><i class="fa-solid fa-user-group"></i> Organization Members</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($plans as $p): ?>
            <tr>
              <td style="text-align:center;"><?= (int)$p['id'] ?></td>
              <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>

              <td style="text-align:center;">
                <input type="number" min="0" name="features[<?= (int)$p['id'] ?>][max_storage_gb]"
                       value="<?= (int)$p['max_storage_gb'] ?>">
              </td>
              <td style="text-align:center;">
                <input type="number" min="0" name="features[<?= (int)$p['id'] ?>][max_projects]"
                       value="<?= (int)$p['max_projects'] ?>">
              </td>
              <td style="text-align:center;">
                <input type="number" min="0" name="features[<?= (int)$p['id'] ?>][max_company_members]"
                       value="<?= (int)$p['max_company_members'] ?>">
              </td>

              <!-- Work Diary -->
              <td style="text-align:center;">
                <input type="checkbox"
                       name="features[<?= (int)$p['id'] ?>][allow_work_diary]"
                       value="1"
                       <?= ((int)($p['allow_work_diary'] ?? 0) === 1 ? 'checked' : '') ?>>
              </td>

              <!-- Organization Management -->
              <td style="text-align:center;">
                <input type="checkbox"
                       name="features[<?= (int)$p['id'] ?>][allow_organization_manage]"
                       value="1"
                       <?= ((int)($p['allow_organization_manage'] ?? 0) === 1 ? 'checked' : '') ?>>
              </td>

              <!-- Organization Members -->
              <td style="text-align:center;">
                <input type="checkbox"
                       name="features[<?= (int)$p['id'] ?>][allow_organization_members]"
                       value="1"
                       <?= ((int)($p['allow_organization_members'] ?? 0) === 1 ? 'checked' : '') ?>>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="sf-actions">
        <button type="submit" class="btn-save">
          <i class="fa-solid fa-floppy-disk"></i> Save changes
        </button>
      </div>
    </form>
  </div>
</main>

<?php
if (is_file($ROOT . '/includes/footer.php')) require $ROOT . '/includes/footer.php';
