<?php
// pages/subscriptions.php

session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

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

// Fetch subscriptions
$stmt = $pdo->query('SELECT id, name, price, description FROM subscriptions ORDER BY id ASC');
$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cache-busting CSS
$css = __DIR__ . '/../assets/css/subscriptions.css';
$ver = file_exists($css) ? filemtime($css) : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Subscriptions | CDE Bimtech</title>

  <!-- Font Awesome -->
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        integrity="sha512-dynvDxJ5aVF6oU1i6zfoalvVYvNvKcJste/0q5u+P%2FgPm4jG3E5s3UeJ8V+RaH59RUW2YCiMzZ6pyRrg58F3CA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Page CSS -->
  <link rel="stylesheet" href="../assets/css/subscriptions.css?v=<?= $ver ?>">
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?= filemtime(__DIR__ . '/../assets/css/sidebar.css') ?>">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main">
    <section class="plans-grid">
      <?php foreach ($subs as $sub): ?>
      <div class="plan-card">
        <div class="plan-header">
          <div class="plan-price">
            <?= number_format($sub['price'], 0, ',', '.') ?> <span>VND / năm</span>
          </div>
          <h3 class="plan-name"><?= htmlspecialchars($sub['name']) ?></h3>
        </div>
        <ul class="plan-features">
          <?php foreach (explode("\n", $sub['description']) as $feat): ?>
            <li><?= htmlspecialchars(trim($feat)) ?></li>
          <?php endforeach; ?>
        </ul>
        <button class="plan-choose"
                onclick="location.href='subscribe.php?sub_id=<?= $sub['id'] ?>'">
          Choose plan
        </button>
      </div>
      <?php endforeach; ?>
    </section>
  </div>
</body>
</html>
