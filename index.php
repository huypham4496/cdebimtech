<?php
// index.php
// UTF-8 no BOM
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: pages/login.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - CDE</title>
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="app-container">
    <?php echo renderSidebar(); ?>
    <div class="main-content">
      <?php echo renderHeader('Overview'); ?>
      <div class="content-body">

        <div class="alert-banner">
          Your plan is <strong>Admin plan</strong> and unlimited usage time
        </div>

        <div class="card-row">
          <div class="card">
            <h3><?php echo getProjectCount(); ?></h3>
            <p>Total Projects</p>
          </div>
          <div class="card">
            <h3><?php echo getUserCount(); ?></h3>
            <p>Total Users</p>
          </div>
          <div class="card">
            <h3><?php echo getActiveUserCount(); ?></h3>
            <p>User In</p>
          </div>
          <div class="card">
            <h3><?php echo getInactiveUserCount(); ?></h3>
            <p>User Out</p>
          </div>
        </div>

        <div class="chart-container">
          <canvas id="memoryChart"></canvas>
        </div>

      </div>
    </div>
  </div>

  <script src="assets/js/dashboard.js"></script>
</body>
</html>