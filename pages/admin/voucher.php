<?php
session_start();
require_once __DIR__ . '/../../config.php';

// Connect with PDO
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Only admins may access
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: ../../pages/login.php');
    exit;
}

$msg   = '';
$error = '';

// Handle creation & deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $code     = trim($_POST['code']);
        $discount = (float)$_POST['discount'];
        $expiry   = $_POST['expiry'];
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO vouchers (code, discount, expiry_date) VALUES (?, ?, ?)'
            );
            $stmt->execute([$code, $discount, $expiry]);
            $msg = 'Voucher added successfully.';
        } catch (PDOException $e) {
            if ($e->errorInfo[1] === 1062) {
                $error = 'Voucher code already exists.';
            } else {
                $error = 'Error adding voucher: ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['delete'])) {
        $id   = (int)$_POST['id'];
        $stmt = $pdo->prepare('DELETE FROM vouchers WHERE id = ?');
        $stmt->execute([$id]);
        $msg = 'Voucher deleted successfully.';
    }

    header('Location: voucher.php?msg=' . urlencode($msg) . '&error=' . urlencode($error));
    exit;
}

$msg   = $_GET['msg']   ?? '';
$error = $_GET['error'] ?? '';

$vouchers = $pdo
    ->query('SELECT id, code, discount, expiry_date, created_at FROM vouchers ORDER BY created_at DESC')
    ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Voucher Management</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?=filemtime(__DIR__.'/../../assets/css/sidebar_admin.css')?>">
  <link rel="stylesheet" href="/assets/css/voucher.css?v=<?=filemtime(__DIR__.'/../../assets/css/voucher.css')?>">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>

  <div class="main-content">
    <div class="voucher-container">
      <h1>Voucher Management</h1>

      <?php if ($msg): ?>
        <div class="alert success"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="voucher-form" method="post">
        <input type="text" name="code" placeholder="Voucher Code" maxlength="50" required>
        <input type="number" name="discount" placeholder="Discount (%)" step="0.01" min="0" required>
        <input type="date" name="expiry" required>
        <button type="submit" name="add">Add Voucher</button>
      </form>

      <div class="voucher-grid">
        <?php foreach ($vouchers as $v): ?>
          <div class="voucher-card">
            <div class="voucher-info">
              <span class="voucher-id">#<?= $v['id'] ?></span>
              <span class="voucher-code"><?= htmlspecialchars($v['code']) ?></span>
              <span class="voucher-discount"><?= htmlspecialchars($v['discount']) ?>% off</span>
              <span class="voucher-expiry">Expires <?= htmlspecialchars($v['expiry_date']) ?></span>
            </div>
            <form method="post" onsubmit="return confirm('Delete this voucher?');">
              <input type="hidden" name="id" value="<?= $v['id'] ?>">
              <button type="submit" name="delete" class="btn-delete">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</body>
</html>
