<?php
// pages/admin/subscriptions_info.php

// Hiển thị lỗi để debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
// Chỉ admin mới được truy cập
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Load config và helper
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Kết nối database
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('DB Error: ' . htmlspecialchars($e->getMessage()));
}

// Xử lý form Update
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sub_id'])) {
    $id          = (int) $_POST['sub_id'];
    $name        = trim($_POST['name']);
    $price       = trim($_POST['price']);
    $description = trim($_POST['description']);

    $stmt = $pdo->prepare(
        'UPDATE subscriptions 
           SET name = ?, price = ?, description = ?
         WHERE id = ?'
    );
    $stmt->execute([$name, $price, $description, $id]);
    $success = 'Subscription updated successfully.';
}

// Lấy danh sách subscriptions
try {
    $stmt          = $pdo->query('SELECT id, name, price, description FROM subscriptions');
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Query Error: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subscriptions Info | AdminCP</title>
  <link rel="stylesheet" href="../assets/css/sidebar_admin.css?v=<?=filemtime(__DIR__.'/../assets/css/sidebar_admin.css')?>">
  <link rel="stylesheet" href="../assets/css/subscriptions_info.css?v=<?=filemtime(__DIR__.'/../assets/css/subscriptions_info.css')?>">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>

  <div class="main-admin">
    <header><h1>Subscriptions Info</h1></header>

    <?php if ($success): ?>
      <div class="alert-banner"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="subscriptions-container">
      <?php if (empty($subscriptions)): ?>
        <p>No subscriptions found. Please add entries via database setup.</p>
      <?php endif; ?>

      <?php foreach ($subscriptions as $sub): ?>
        <div class="subscription-card">
          <form method="post">
            <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">

            <div class="card-field">
              <label>Name</label>
              <input type="text" name="name" value="<?= htmlspecialchars($sub['name']) ?>" required>
            </div>

            <div class="card-field">
              <label>Price</label>
              <input type="text" name="price" value="<?= htmlspecialchars($sub['price']) ?>" required>
