// pages/subscriptions.php
<?php
// Display available subscriptions to logged-in users
session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php'); exit;
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Connect to DB
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

// Fetch subscriptions
$stmt = $pdo->query('SELECT id, name, price, description FROM subscriptions ORDER BY id');
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subscriptions | CDE Bimtech</title>
  <link rel="stylesheet" href="assets/css/subscriptions.css">
</head>
<body>
  <?php include __DIR__.'/pages/sidebar.php'; ?>
  <div class="main">
    <header><h1>Subscriptions</h1></header>
    <div class="subscriptions-container">
      <?php foreach ($subscriptions as $sub): ?>
      <div class="subscription-card">
        <h2><?=htmlspecialchars($sub['name'])?></h2>
        <p><?=nl2br(htmlspecialchars($sub['description']))?></p>
        <p class="price"><?=number_format($sub['price'],0,',','.')?> VND / năm</p>
        <a href="subscribe.php?sub_id=<?=$sub['id']?>" class="btn-choose">Choose</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>