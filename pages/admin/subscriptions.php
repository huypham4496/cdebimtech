<?php
session_start();
require_once __DIR__ . '/../../config.php';

// Connect to the database
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}

// Ensure admin access
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: ../../pages/login.php');
    exit;
}

// Handle subscription updates
$msg   = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subscription'])) {
    $userId  = (int)$_POST['user_id'];
    $planId  = ($_POST['subscription_id'] !== '' ? (int)$_POST['subscription_id'] : null);
    $expires = trim($_POST['subscription_expires_at']) ?: null;

    $stmt = $pdo->prepare(
        'UPDATE users SET subscription_id = ?, subscription_expires_at = ? WHERE id = ?'
    );
    $stmt->execute([$planId, $expires, $userId]);

    $msg = "User #{$userId} subscription updated.";
    header('Location: subscriptions.php?msg=' . urlencode($msg));
    exit;
}

// Flash messages
$msg   = $_GET['msg']   ?? '';
$error = $_GET['error'] ?? '';

// Fetch available plans
$plans = $pdo->query('SELECT id, name FROM subscriptions')->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch users with their subscription info
$sql = <<<SQL
SELECT
  u.id,
  u.username,
  u.subscription_expires_at,
  COALESCE(s.name, '—') AS plan_name,
  u.subscription_id
FROM users u
LEFT JOIN subscriptions s ON u.subscription_id = s.id
ORDER BY u.username
SQL;
$users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manage Subscriptions</title>
  <?php
    // Cache-bust CSS
    $cssBase = __DIR__ . '/../../assets/css/';
  ?>
  <link rel="stylesheet" href="/assets/css/sidebar_admin.css?v=<?= filemtime($cssBase . 'sidebar_admin.css') ?>">
  <link rel="stylesheet" href="/assets/css/subscriptions_admin.css?v=<?= filemtime($cssBase . 'subscriptions_admin.css') ?>">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>
  <main class="main-content">
    <section class="subscriptions-container">
      <h1>User Subscriptions</h1>

      <?php if ($msg): ?>
        <div class="alert success"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="table-wrapper">
        <table class="subscriptions-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Plan</th>
              <th>Expires At</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td>
                <form method="post" class="inline-form">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <select name="subscription_id">
                    <option value="">— None —</option>
                    <?php foreach ($plans as $pid => $pname): ?>
                      <option value="<?= $pid ?>" <?= $u['subscription_id'] == $pid ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pname) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
              </td>
              <td>
                  <input type="date" name="subscription_expires_at" value="<?= htmlspecialchars($u['subscription_expires_at'] ?: '') ?>">
              </td>
              <td>
                  <button type="submit" name="update_subscription" class="btn-save">Save</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
