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

$userId = $_SESSION['user']['id'];
$role   = $_SESSION['user']['role'] ?? 'user';

if ($role === 'admin') {
    // Admin banner
    $bannerText = 'Your account is on the Admin plan with unlimited access.';
} else {
    // Fetch plan name and expiry date
    $stmt = $pdo->prepare('
        SELECT s.name AS plan_name, u.subscription_expires_at
        FROM users u
        LEFT JOIN subscriptions s ON u.subscription_id = s.id
        WHERE u.id = ?
    ');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $planName = $row['plan_name'] ?? 'Basic';
    $expires  = $row['subscription_expires_at'];

    if (is_null($expires) || $expires === '' || $expires === '0000-00-00') {
        $bannerText = "You are using the {$planName} plan with unlimited access.";
    } else {
        $today = new DateTimeImmutable('today');
        $exp   = new DateTimeImmutable($expires);
        $diff  = $today->diff($exp);
        $days  = $diff->invert ? 0 : $diff->days;
        $bannerText = "You are using the {$planName} plan with {$days} day" . ($days !== 1 ? 's' : '') . " remaining.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home | CDE Bimtech</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-dynvDxJ5aVF6oU1i6zfoalvVYvNvKcJste/0q5u+P%2FgPm4jG3E5s3UeJ8V+RaH59RUW2YCiMzZ6pyRrg58F3CA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo filemtime('../assets/css/dashboard.css'); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime('../assets/css/sidebar.css'); ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="../assets/js/dashboard.js?v=<?php echo filemtime('../assets/js/dashboard.js');?>" defer></script>
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main">
    <!-- Info banner -->
    <div class="alert-banner info-banner">
      
      <?php echo htmlspecialchars($bannerText); ?>
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
