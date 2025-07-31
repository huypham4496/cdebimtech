<?php
// pages/subscriptions.php

session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Nạp config và helper
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Kết nối PDO
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('DB Connection Error: ' . htmlspecialchars($e->getMessage()));
}

// Lấy danh sách subscriptions
$stmt = $pdo->query('SELECT id, name, price, description FROM subscriptions ORDER BY id ASC');
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Subscriptions | CDE Bimtech</title>
  <?php
    // Cache-busting CSS mỗi khi thay đổi
    $cssFile = __DIR__ . '/../assets/css/subscriptions.css';
    $ver = file_exists($cssFile) ? filemtime($cssFile) : time();
  ?>
  <link rel="stylesheet" href="../assets/css/subscriptions.css?v=<?= $ver ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-dynvDxJ5aVF6oU1i6zfoalvVYvNvKcJste/0q5u+P%2FgPm4jG3E5s3UeJ8V+RaH59RUW2YCiMzZ6pyRrg58F3CA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo filemtime('../assets/css/dashboard.css'); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime('../assets/css/sidebar.css'); ?>">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main">
    <header><h1>Subscriptions</h1></header>
    <div class="subscriptions-container">
      <?php foreach ($subscriptions as $sub): ?>
      <div class="subscription-card">
        <h2><?= htmlspecialchars($sub['name']) ?></h2>
        <p><?= nl2br(htmlspecialchars($sub['description'])) ?></p>
        <p class="price"><?= number_format($sub['price'],0,',','.') ?> VND / năm</p>
        <a href="subscribe.php?sub_id=<?= $sub['id'] ?>" class="btn-choose">Choose</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
