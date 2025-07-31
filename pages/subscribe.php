<?php
// pages/subscribe.php
// UTF-8 no BOM

session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    // Database connection
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// 1) Load subscription plan
$subId = isset($_GET['sub_id']) ? (int)$_GET['sub_id'] : 0;
$stmt  = $pdo->prepare('SELECT id, name, price, description FROM subscriptions WHERE id = ?');
$stmt->execute([$subId]);
$sub = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sub) {
    die('Subscription not found.');
}

// 2) Load merchant payment settings
$stmt = $pdo->prepare('SELECT account_name, bank_name, account_number FROM payment_settings WHERE id = 1');
$stmt->execute();
$pay = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pay) {
    die('Payment settings not configured. Please contact support.');
}

// 3) Cache-bust CSS
$cssVer = file_exists(__DIR__ . '/../assets/css/subscribe.css')
    ? filemtime(__DIR__ . '/../assets/css/subscribe.css')
    : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Subscribe: <?= htmlspecialchars($sub['name']) ?> | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/subscribe.css?v=<?= $cssVer ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-dynvDxJ5aVF6oU1i6zfoalvVYvNvKcJste/0q5u+P%2FgPm4jG3E5s3UeJ8V+RaH59RUW2YCiMzZ6pyRrg58F3CA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo filemtime('../assets/css/dashboard.css'); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime('../assets/css/sidebar.css'); ?>">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main">
    <div class="subscribe-layout">
      <!-- Subscription Details -->
      <div class="subscription-card">
        <h2><?= htmlspecialchars($sub['name']) ?></h2>
        <p><?= nl2br(htmlspecialchars($sub['description'])) ?></p>
        <div class="price" id="base-price"><?= (int)$sub['price'] ?></div>
        <div class="price-unit">VND / year</div>
      </div>

      <!-- Payment Info & Duration Selector -->
      <div class="subscription-card">
        <form id="subscribe-form" class="subscribe-form">
          <!-- readonly merchant info -->
          <label>Account Holder</label>
          <input type="text" value="<?= htmlspecialchars($pay['account_name']) ?>" readonly>

          <label>Bank Name</label>
          <input type="text" value="<?= htmlspecialchars($pay['bank_name']) ?>" readonly>

          <label>Account Number</label>
          <input type="text" value="<?= htmlspecialchars($pay['account_number']) ?>" readonly>

          <!-- Duration -->
          <label for="years">Duration</label>
          <select id="years" name="years">
            <option value="1">1 year</option>
            <option value="2">2 years</option>
            <option value="5">5 years (pay for 4)</option>
            <option value="10">10 years (pay for 8)</option>
            <option value="0">Lifetime (30×)</option>
          </select>

          <!-- Computed total -->
          <label>Total to Pay</label>
          <input type="text" id="total" readonly>

          <div id="gift-msg" class="gift-message"></div>

          <!-- QR Code framed -->
          <label>QR Payment</label>
          <div class="qr-box">
            <img id="qr-img" src="" alt="QR Code">
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    const price    = <?= (int)$sub['price'] ?>;
    const bank     = <?= json_encode(strtolower($pay['bank_name'])) ?>;
    const acct     = <?= json_encode($pay['account_number']) ?>;
    const userId   = <?= (int)$_SESSION['user']['id'] ?>;
    const subName  = <?= json_encode(str_replace(' ', '', $sub['name'])) ?>;

    const yearsSel = document.getElementById('years');
    const totalIn  = document.getElementById('total');
    const giftMsg  = document.getElementById('gift-msg');
    const qrImg    = document.getElementById('qr-img');

    function update() {
      const y = parseInt(yearsSel.value, 10);
      let payY, msg = '';
      if (y === 0) {
        payY = 30;
        msg = 'Lifetime plan: pay for 30× base price';
      } else if (y === 5) {
        payY = 4;
        msg = 'Buy 5 years, pay for 4 (1 year free)';
      } else if (y === 10) {
        payY = 8;
        msg = 'Buy 10 years, pay for 8 (2 years free)';
      } else {
        payY = y;
      }
      const amount = payY * price;
      totalIn.value = amount.toLocaleString('vi-VN') + ' VND';
      giftMsg.textContent = msg;

      const rnd = Math.random().toString(36).substring(2,6).toUpperCase();
      const memo = `${userId}_${subName}_${(y||'LT')}_${rnd}`;

      qrImg.src = `https://qr.ecaptcha.vn/api/generate/${bank}/${acct}/${memo}` +
                  `?amount=${amount}&memo=${memo}&is_mask=0`;
    }

    yearsSel.addEventListener('change', update);
    update();
  </script>
</body>
</html>
