<?php
session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../../config.php';

// DB connection
$charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, $charset),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Database connection failed: '.$e->getMessage());
}

// Handle submission
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['features'])) {
    $sql = "
        UPDATE subscriptions
           SET max_storage_gb             = :max_storage_gb,
               max_projects               = :max_projects,
               max_company_members        = :max_company_members,
               allow_organization_members = :allow_org,
               allow_work_diary           = :allow_diary
         WHERE id = :id
    ";
    $stmt = $pdo->prepare($sql);

    foreach ($_POST['features'] as $id => $values) {
        // if left blank (or zero), treat as unlimited => store 0
        $maxStorage = ($values['max_storage_gb'] !== '' ? (int)$values['max_storage_gb'] : 0);
        $maxProj    = ($values['max_projects']    !== '' ? (int)$values['max_projects']    : 0);
        $maxMembers = ($values['max_company_members'] !== '' ? (int)$values['max_company_members'] : 0);

        $stmt->execute([
            ':max_storage_gb'      => $maxStorage,
            ':max_projects'        => $maxProj,
            ':max_company_members' => $maxMembers,
            ':allow_org'           => isset($values['allow_organization_members']) ? 1 : 0,
            ':allow_diary'         => isset($values['allow_work_diary'])           ? 1 : 0,
            ':id'                  => (int)$id,
        ]);
    }
    $message = 'Features updated successfully.';
}

// Fetch plans
$plans = $pdo->query("SELECT * FROM subscriptions ORDER BY id")->fetchAll();

// versioning
$cssDir     = __DIR__ . '/../../assets/css';
$verPage    = file_exists("$cssDir/subscription_features.css") ? filemtime("$cssDir/subscription_features.css") : time();
$verSidebar = file_exists("$cssDir/sidebar_admin.css")       ? filemtime("$cssDir/sidebar_admin.css")       : time();

// header
include __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css"
      integrity="sha512-olb1y6Rv7uyYCRykpY7ZZ6vqpKILvQPWZG1aJeyeWQ1m/5nYy8WpuM8aOQW4ZcStSz2/fW+N5hWwcX96Iqb0FQ=="
      crossorigin="anonymous" referrerpolicy="no-referrer"
/>
<link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?= $verSidebar ?>"/>
<link rel="stylesheet" href="../../assets/css/subscription_features.css?v=<?= $verPage ?>"/>

<?php include __DIR__ . '/sidebar_admin.php'; ?>

<div class="main-content">
  <h1><i class="fas fa-sliders-h feature-icon"></i> Manage Subscription Features</h1>

  <?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="post">
    <table class="features-table">
      <thead>
        <tr>
          <th>Plan</th>
          <th><i class="fas fa-hdd"></i> Storage (GB)</th>
          <th><i class="fas fa-project-diagram"></i> Projects</th>
          <th><i class="fas fa-user-friends"></i> Members</th>
          <th class="text-center"><i class="fas fa-sitemap"></i> Org Members</th>
          <th class="text-center"><i class="fas fa-book"></i> Work Diary</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plans as $plan): ?>
        <tr>
          <td><?= htmlspecialchars($plan['name']) ?></td>
          <td>
            <input
              type="number"
              name="features[<?= $plan['id'] ?>][max_storage_gb]"
              value="<?= $plan['max_storage_gb']>0 ? $plan['max_storage_gb'] : '' ?>"
              placeholder="Unlimited"
              min="0"
            />
          </td>
          <td>
            <input
              type="number"
              name="features[<?= $plan['id'] ?>][max_projects]"
              value="<?= $plan['max_projects']>0 ? $plan['max_projects'] : '' ?>"
              placeholder="Unlimited"
              min="0"
            />
          </td>
          <td>
            <input
              type="number"
              name="features[<?= $plan['id'] ?>][max_company_members]"
              value="<?= $plan['max_company_members']>0 ? $plan['max_company_members'] : '' ?>"
              placeholder="Unlimited"
              min="0"
            />
          </td>
          <td class="text-center">
            <input
              type="checkbox"
              name="features[<?= $plan['id'] ?>][allow_organization_members]"
              <?= $plan['allow_organization_members'] ? 'checked' : '' ?>
            />
          </td>
          <td class="text-center">
            <input
              type="checkbox"
              name="features[<?= $plan['id'] ?>][allow_work_diary]"
              <?= $plan['allow_work_diary'] ? 'checked' : '' ?>
            />
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <button type="submit" class="btn-save">
      <i class="fas fa-save"></i> Save Changes
    </button>
  </form>
</div>

</body>
</html>
