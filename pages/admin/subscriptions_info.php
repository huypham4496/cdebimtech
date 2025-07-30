<?php
// pages/admin/subscriptions_info.php

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
// Only admin can access
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Load configuration and helpers
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

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

// Handle form submissions before fetching subscriptions
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        // Create new subscription
        $name        = trim($_POST['name'] ?? '');
        // Remove thousand separators before saving
        $priceRaw    = str_replace('.', '', trim($_POST['price'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        if ($name !== '' && $priceRaw !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO subscriptions (name, price, description) VALUES (?, ?, ?)'
            );
            $stmt->execute([$name, $priceRaw, $description]);
            $success = 'New subscription added successfully.';
            header('Location: ' . $_SERVER['REQUEST_URI']); exit;
        }
    } elseif ($action === 'update' && isset($_POST['sub_id'])) {
        // Update existing subscription
        $id          = (int) $_POST['sub_id'];
        $name        = trim($_POST['name']);
        $priceRaw    = str_replace('.', '', trim($_POST['price']));
        $description = trim($_POST['description']);
        $stmt = $pdo->prepare(
            'UPDATE subscriptions SET name = ?, price = ?, description = ? WHERE id = ?'
        );
        $stmt->execute([$name, $priceRaw, $description, $id]);
        $success = 'Subscription updated successfully.';
        header('Location: ' . $_SERVER['REQUEST_URI']); exit;
    } elseif ($action === 'delete' && isset($_POST['sub_id'])) {
        // Delete subscription
        $id = (int) $_POST['sub_id'];
        $stmt = $pdo->prepare('DELETE FROM subscriptions WHERE id = ?');
        $stmt->execute([$id]);
        $success = 'Subscription deleted.';
        header('Location: ' . $_SERVER['REQUEST_URI']); exit;
    }
}

// Fetch all subscriptions
try {
    $stmt          = $pdo->query('SELECT id, name, price, description FROM subscriptions ORDER BY id ASC');
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Query Error: ' . htmlspecialchars($e->getMessage()));
}

// Determine if new-card should be disabled (max 4 allowed)
$disableNew = count($subscriptions) >= 4;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subscriptions Info | AdminCP</title>
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/sidebar_admin.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/subscriptions_info.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/subscriptions_info.css'); ?>">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>

  <div class="main-admin">
    <header><h1>Subscriptions Info</h1></header>

    <?php if ($success): ?>
      <div class="alert-banner"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="subscriptions-container">
      <!-- New subscription form -->
      <div class="subscription-card new-card<?= $disableNew ? ' disabled' : '' ?>">
        <form method="post" class="subscription-form">
          <input type="hidden" name="action" value="create">
          <div class="card-id">New</div>
          <div class="card-field">
            <label>Name</label>
            <input type="text" name="name" required placeholder="Subscription name" <?= $disableNew ? 'disabled' : '' ?>>
          </div>
          <div class="card-field">
            <label>Price (VND)</label>
            <input type="text" name="price" required placeholder="e.g. 6.000.000" <?= $disableNew ? 'disabled' : '' ?>>
          </div>
          <div class="card-field full">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Description" <?= $disableNew ? 'disabled' : '' ?>></textarea>
          </div>
          <button type="submit" class="btn-create" <?= $disableNew ? 'disabled' : '' ?>>Add Subscription</button>
        </form>
      </div>

      <!-- Existing subscriptions -->
      <?php if (empty($subscriptions)): ?>
        <p>No subscriptions yet.</p>
      <?php endif; ?>

      <?php foreach ($subscriptions as $sub): ?>
        <div class="subscription-card">
          <form method="post" class="subscription-form">
            <div class="card-id">#<?= $sub['id'] ?></div>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
            <div class="card-field">
              <label>Name</label>
              <input type="text" name="name" value="<?= htmlspecialchars($sub['name']) ?>" required>
            </div>
            <div class="card-field">
              <label>Price (VND)</label>
              <input type="text" name="price" value="<?= number_format($sub['price'], 0, ',', '.') ?>" required>
            </div>
            <div class="card-field full">
              <label>Description</label>
              <textarea name="description" rows="3" required><?= htmlspecialchars($sub['description']) ?></textarea>
            </div>
            <div class="card-actions">
              <button type="submit" class="btn-update">Update</button>
              <button type="submit" name="action" value="delete" class="btn-delete">Delete</button>
            </div>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
    // Format input to thousand separators
    document.querySelectorAll('.subscription-form input[name="price"]').forEach(input => {
      input.addEventListener('input', e => {
        let value = e.target.value.replace(/\D/g, '');
        e.target.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      });
      input.addEventListener('blur', e => {
        // No-op, keep formatting
      });
    });
  </script>
</body>
</html>