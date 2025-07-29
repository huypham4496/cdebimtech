<?php
// index.php
// UTF-8 no BOM
session_start();

// Nếu chưa đăng nhập, chuyển hướng về trang login
if (!isset($_SESSION['user'])) {
    header('Location: pages/login.php');
    exit;
}

// Tiếp tục hiển thị dashboard
require_once __DIR__ . '/includes/header.php';
?>

<main class="dashboard-container">
  <aside class="sidebar">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
  </aside>

  <section class="main-content">
    <h1>Overview</h1>
    <div class="widgets">
      <div class="widget">Total Projects: <!-- <?php echo getProjectCount(); ?> -->0</div>
      <div class="widget">Total Users: <!-- <?php echo getUserCount(); ?> -->0</div>
      <!-- Thêm các widget khác tương tự -->
    </div>
  </section>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';