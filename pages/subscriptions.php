<?php
// pages/subscriptions.php
// UTF-8 no BOM

session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Connect to database
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('DB Connection Error: ' . htmlspecialchars($e->getMessage()));
}

// Fetch current user's subscription_id
$stmt = $pdo->prepare('SELECT subscription_id FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user']['id']]);
$currentSub = (int)$stmt->fetchColumn();

// Fetch all subscriptions
$stmt = $pdo->query('SELECT id, name, price, description FROM subscriptions ORDER BY id ASC');
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cache-bust CSS
$verSub = file_exists(__DIR__ . '/../assets/css/subscriptions.css')
    ? filemtime(__DIR__ . '/../assets/css/subscriptions.css')
    : time();
$verSidebar = file_exists(__DIR__ . '/../assets/css/sidebar.css')
    ? filemtime(__DIR__ . '/../assets/css/sidebar.css')
    : time();
$verDash = file_exists(__DIR__ . '/../assets/css/dashboard.css')
    ? filemtime(__DIR__ . '/../assets/css/dashboard.css')
    : time();
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
  <link rel="stylesheet" href="../assets/css/subscriptions.css?v=<?= $verSub ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?= $verSidebar ?>">

</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main">
    <section class="plans-grid">
      <?php foreach ($subscriptions as $sub):
        $id = (int)$sub['id'];
        if ($id < $currentSub) {
          $state = 'included';
          $buttonText = 'Included';
          $disabled = 'disabled';
        } elseif ($id === $currentSub) {
          $state = 'current';
          $buttonText = 'Current Plan';
          $disabled = 'disabled';
        } else {
          $state = '';
          $buttonText = 'Choose Plan';
          $disabled = '';
        }
      ?>
      <div class="plan-card <?= $state ?>">
        <div class="plan-header">
          <div class="plan-price">
            <?= number_format($sub['price'], 0, ',', '.') ?>
            <span>/ year</span>
          </div>
          <h3 class="plan-name"><?= htmlspecialchars($sub['name']) ?></h3>
        </div>
        <ul class="plan-features">
          <?php foreach (explode("\n", $sub['description']) as $feat): ?>
            <li><?= htmlspecialchars(trim($feat)) ?></li>
          <?php endforeach; ?>
        </ul>
        <button
          class="plan-choose"
          <?= $disabled ?>
          <?= $id > $currentSub ? "onclick=\"location.href='subscribe.php?sub_id=$id'\"" : '' ?>
        >
          <?= $buttonText ?>
        </button>
      </div>
      <?php endforeach; ?>
    </section>
  </div>
</body>
</html>
