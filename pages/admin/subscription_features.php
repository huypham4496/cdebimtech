<?php
// pages/admin/subscription_features.php
session_start();

// chỉ cho admin
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config.php';

// --- KẾT NỐI DATABASE ---
try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database Connection Failed: ' . $e->getMessage());
}

// xử lý form khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['features'])) {
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

    foreach ($_POST['features'] as $id => $f) {
        $stmt->execute([
            ':max_storage_gb'      => (int)$f['max_storage_gb'],
            ':max_projects'        => (int)$f['max_projects'],
            ':max_company_members' => (int)$f['max_company_members'],
            ':allow_org'           => isset($f['allow_organization_members']) ? 1 : 0,
            ':allow_diary'         => isset($f['allow_work_diary'])           ? 1 : 0,
            ':id'                  => (int)$id,
        ]);
    }
    $message = 'Cập nhật tính năng thành công.';
}

// lấy danh sách các gói subscription
$plans = $pdo->query("SELECT * FROM subscriptions ORDER BY id")->fetchAll();

// cache-busting CSS
$cssFile = __DIR__ . '/../../assets/css/admin/subscription_features.css';
$version = file_exists($cssFile) ? filemtime($cssFile) : time();

// include header chung (nơi xuất <head>, <body> mở)
include __DIR__ . '/../../includes/header.php';
?>

<!-- Font Awesome & CSS riêng -->
<link
  rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css"
  integrity="sha512-olb1y6Rv7uyYCRykpY7ZZ6vqpKILvQPWZG1aJeyeWQ1m/5nYy8WpuM8aOQW4ZcStSz2/fW+N5hWwcX96Iqb0FQ=="
  crossorigin="anonymous"
  referrerpolicy="no-referrer"
/>
<link
  rel="stylesheet"
  href="../../assets/css/admin/subscription_features.css?v=<?= $version ?>"
/>

<div class="sidebar-admin">
  <a href="dashboard.php">
    <i class="fas fa-tachometer-alt"></i> Dashboard
  </a>
  <a href="subscription_features.php" class="active">
    <i class="fas fa-sliders-h"></i> Subscription Features
  </a>
  <a href="users.php">
    <i class="fas fa-users"></i> Users
  </a>
  <a href="projects.php">
    <i class="fas fa-folder-open"></i> Projects
  </a>
  <!-- … thêm các mục khác nếu cần … -->
</div>

<div class="main-content">
  <h1>
    <i class="fas fa-sliders-h feature-icon"></i>
    Quản lý Tính năng Subscription
  </h1>

  <?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="post">
    <table class="features-table">
      <thead>
        <tr>
          <th>Gói</th>
          <th><i class="fas fa-hdd feature-icon"></i> Storage (GB)</th>
          <th><i class="fas fa-project-diagram feature-icon"></i> Dự án</th>
          <th><i class="fas fa-user-friends feature-icon"></i> Thành viên</th>
          <th class="text-center">
            <i class="fas fa-sitemap feature-icon"></i> Org Members
          </th>
          <th class="text-center">
            <i class="fas fa-book feature-icon"></i> Work Diary
          </th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($plans as $plan): ?>
        <tr>
          <td><?= htmlspecialchars($plan['name']) ?></td>
          <td>
            <input type="number"
                   name="features[<?= $plan['id'] ?>][max_storage_gb]"
                   value="<?= $plan['max_storage_gb'] ?>"
                   min="0" />
          </td>
          <td>
            <input type="number"
                   name="features[<?= $plan['id'] ?>][max_projects]"
                   value="<?= $plan['max_projects'] ?>"
                   min="0" />
          </td>
          <td>
            <input type="number"
                   name="features[<?= $plan['id'] ?>][max_company_members]"
                   value="<?= $plan['max_company_members'] ?>"
                   min="0" />
          </td>
          <td class="text-center">
            <input type="checkbox"
                   name="features[<?= $plan['id'] ?>][allow_organization_members]"
                   <?= $plan['allow_organization_members'] ? 'checked' : '' ?> />
          </td>
          <td class="text-center">
            <input type="checkbox"
                   name="features[<?= $plan['id'] ?>][allow_work_diary]"
                   <?= $plan['allow_work_diary'] ? 'checked' : '' ?> />
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <button type="submit" class="btn-save">
      <i class="fas fa-save"></i> Lưu thay đổi
    </button>
  </form>
</div>

<?php
// include footer chung (đóng </body>, </html>)
include __DIR__ . '/../../includes/footer.php';
