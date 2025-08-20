<?php
// pages/home.php
session_start();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Redirect if not authenticated
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config.php';

// Connect to database
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed.');
}

// Pull user data from session
$user    = $_SESSION['user'];
$userId  = $user['id'];
$role    = $user['role'] ?? 'user';

// Default to Free plan until we see otherwise
$planName = 'Free';
$expires  = null;

// If they have a subscription_id, load its name & expiry
if (!empty($user['subscription_id'])) {
    // Fetch plan name
    $stmt = $pdo->prepare('SELECT name FROM subscriptions WHERE id = ?');
    $stmt->execute([ (int)$user['subscription_id'] ]);
    $planName = $stmt->fetchColumn() ?: $planName;

    // Read expiry from session if present
    if (isset($user['subscription_expires_at'])) {
        $expires = $user['subscription_expires_at'];
    } else {
        // Fallback: query users table directly
        $stmt2 = $pdo->prepare('SELECT subscription_expires_at FROM users WHERE id = ?');
        $stmt2->execute([$userId]);
        $expires = $stmt2->fetchColumn();
    }
}

// Build the banner text
if ($role === 'admin') {
    $bannerText = 'Your account is on the Admin plan with unlimited access.';
}
elseif (strcasecmp($planName, 'Free') === 0) {
    $bannerText = 'You are using the Free plan.';
}
else {
    // If expiry is empty or zero-date, treat as unlimited
    if (empty($expires) || $expires === '0000-00-00') {
        $bannerText = "You are using the {$planName} plan with unlimited access.";
    } else {
        // Show exact expiry date
        $bannerText = "You are using the {$planName} plan until {$expires}.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/all.min.css?v=<?php echo filemtime('../assets/css/all.min.css'); ?>">
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo filemtime('../assets/css/dashboard.css'); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime('../assets/css/sidebar.css'); ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="../assets/js/dashboard.js?v=<?php echo filemtime('../assets/js/dashboard.js'); ?>" defer></script>
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main">
    <!-- Info banner -->
    <div class="alert-banner info-banner">
      <span class="alert-icon"><i class="fas fa-info-circle"></i></span>
      <?= htmlspecialchars($bannerText) ?>
    </div>

    <!-- Stats cards -->
    <div class="stats-cards">
      <div class="card">
        <div class="card-icon project-icon"></div>
        <div class="card-body">
          <div class="card-title">Total Projects</div>
          <div class="card-value">31</div>
          <div class="card-sub">Overall number of projects</div>
        </div>
        <div class="card-change">↑ 0%</div>
      </div>
      <div class="card">
        <div class="card-icon user-icon"></div>
        <div class="card-body">
          <div class="card-title">Total Users</div>
          <div class="card-value">70</div>
          <div class="card-sub">Overall number of users</div>
        </div>
        <div class="card-change">↑ 0%</div>
      </div>
      <div class="card">
        <div class="card-icon org-icon"></div>
        <div class="card-body">
          <div class="card-title">In Organization</div>
          <div class="card-value">63</div>
          <div class="card-sub">Users within organization</div>
        </div>
        <div class="card-change">↑ 0%</div>
      </div>
      <div class="card">
        <div class="card-icon external-icon"></div>
        <div class="card-body">
          <div class="card-title">External Users</div>
          <div class="card-value">5</div>
          <div class="card-sub">Users outside organization</div>
        </div>
        <div class="card-change">↑ 0%</div>
      </div>
    </div>

    <!-- Chart container -->
    <div class="chart-container multi">
      <div class="chart-header">Storage Usage</div>
      <div class="chart-content">
        <canvas id="doughnutChart"></canvas>
        <div class="chart-center-text-large">1.66M</div>
      </div>
      <div class="chart-legend-list">
        <div><span class="legend-color lithuania"></span> Lithuania <span class="legend-value">34.9%</span></div>
        <div><span class="legend-color czechia"></span> Czechia <span class="legend-value">21.0%</span></div>
        <div><span class="legend-color ireland"></span> Ireland <span class="legend-value">14.0%</span></div>
        <div><span class="legend-color germany"></span> Germany <span class="legend-value">11.5%</span></div>
        <div><span class="legend-color australia"></span> Australia <span class="legend-value">9.7%</span></div>
        <div><span class="legend-color austria"></span> Austria <span class="legend-value">8.9%</span></div>
      </div>
    </div>
  </div>
</body>
</html>
