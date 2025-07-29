<?php
// pages/home.php
session_start();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
// Redirect to login if not authenticated
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main">
    <!-- Alert banner -->
    <div class="alert-banner">
      <span class="alert-icon">⚠️</span>
      Gói của bạn là Admin plan và không giới hạn thời gian sử dụng
    </div>

    <!-- Stats cards -->
    <div class="stats-cards">
      <div class="card">
        <div class="card-icon project-icon"></div>
        <div class="card-body">
          <div class="card-title">Tổng dự án</div>
          <div class="card-value">31</div>
          <div class="card-sub">Tổng số dự án</div>
        </div>
        <div class="card-change">↑ 0%</div>
      </div>
      <div class="card">
        <div class="card-icon user-icon"></div>
        <div class="card-body">
          <div class="card-title">Tổng người dùng</div>
          <div class="card-value">70</div>
          <div class="card-sub">Tổng số người dùng</div>
        </div>
        <div class="card-change">↑ 0%</div>
      </div>
      <div class="card">
        <div class="card-icon org-icon"></div>
        <div class="card-body">
          <div class="card-title">Trong tổ chức</div>
          <div class="card-value">63</div>
          <div class="card-sub">Tổng số người dùng trong tổ chức</div>
        </div>
        <div class="card-change">↑ 0%</div>
      </div>
      <div class="card">
        <div class="card-icon external-icon"></div>
        <div class="card-body">
          <div class="card-title">Ngoài tổ chức</div>
          <div class="card-value">5</div>
          <div class="card-sub">Tổng số người dùng ngoài tổ chức</div>
        </div>
        <div class="card-change">↑ 0%</div>
      </div>
    </div>

    <!-- Memory usage chart -->
    <div class="chart-container">
      <div class="chart-header">Sử dụng bộ nhớ</div>
      <div class="chart-content">
        <!-- Placeholder for donut chart -->
        <div class="donut-chart">10% / 90%</div>
      </div>
      <div class="chart-legend">
        <div><span class="legend-dot used"></span> Dung lượng đã sử dụng</div>
        <div><span class="legend-dot remaining"></span> Dung lượng còn lại</div>
      </div>
    </div>

  </div>
</body>
</html>