// pages/home.php
<?php
session_start();
// Chuyển về login nếu chưa đăng nhập
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
// Include config và db connection
require_once __DIR__ . '/../config.php';
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB Error');
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
  <div class="sidebar">
    <div class="logo">CDE Bimtech</div>
    <nav>
      <ul>
        <li class="active"><a href="#">Home</a></li>
        <li><a href="#">Projects</a></li>
        <li><a href="#">Common Data</a></li>
        <li><a href="#">Inventory Asset</a></li>
      </ul>
    </nav>
    <div class="user-info">
      <span><?= htmlspecialchars($_SESSION['user']['first_name']) ?> <?= htmlspecialchars($_SESSION['user']['last_name']) ?></span>
      <a href="login.php?logout=1">Sign out</a>
    </div>
  </div>
  <div class="main">
    <header>
      <h1>Dashboard</h1>
      <p>Welcome, <?= htmlspecialchars($_SESSION['user']['first_name']) ?></p>
    </header>
    <section class="tiles">
      <div class="tile storage">
        <h2>Total Storage</h2>
        <div class="bar"><div class="used" style="width:5.45%"></div></div>
        <div class="info">
          <span>Used: 442.93GB</span>
          <span>Remaining: 8126.00GB</span>
        </div>
      </div>
      <div class="tile tasks">
        <h2>Tasks</h2>
        <p>No data</p>
      </div>
      <div class="tile approvals">
        <h2>Approvals</h2>
        <p>No data</p>
      </div>
    </section>
    <section class="charts">
      <div class="chart">
        <h3>5 Most Popular Projects</h3>
        <!-- Placeholder for pie chart -->
        <div class="chart-placeholder">[Pie Chart]</div>
      </div>
      <div class="chart">
        <h3>Upcoming Tasks by Assignee</h3>
        <p>No data</p>
      </div>
    </section>
  </div>
</body>
</html>
