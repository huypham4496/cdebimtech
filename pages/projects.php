<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
// Thiết lập kết nối PDO để sidebar sử dụng
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('DB Error: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Projects | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/projects.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/projects.css'); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/sidebar.css'); ?>">
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dashboard.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main">
    <div class="projects-container">
      <div class="project-card">
        <h2>Under Development</h2>
        <p>Chức năng Quản lý Dự án đang được phát triển. Vui lòng quay lại sau để cập nhật tính năng mới!</p>
      </div>
    </div>
  </div>
</body>
</html>