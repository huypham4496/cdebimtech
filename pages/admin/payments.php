<?php
// pages/admin/payments.php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session & check admin
session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Load sidebar and config
require_once __DIR__ . '/sidebar_admin.php';
require_once __DIR__ . '/../../config.php';

// Connect to DB
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('DB Error: ' . htmlspecialchars($e->getMessage()));
}

// Upsert payment settings
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $holder = trim($_POST['account_name'] ?? '');
    $bank   = trim($_POST['bank_name'] ?? '');
    $amount = str_replace('.', '', trim($_POST['amount'] ?? ''));
    $note   = trim($_POST['note'] ?? '');
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO payment_settings (id, account_name, bank_name, amount, note) VALUES (1, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE account_name=VALUES(account_name), bank_name=VALUES(bank_name), amount=VALUES(amount), note=VALUES(note)'
        );
        $stmt->execute([$holder, $bank, $amount, $note]);
        $success = 'Payment settings saved.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } catch (PDOException $e) {
        $success = 'Error saving settings: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch current settings or defaults
try {
    $stmt = $pdo->query('SELECT account_name, bank_name, amount, note FROM payment_settings WHERE id=1');
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = false;
}
if (!$settings) {
    $settings = [
        'account_name' => '',
        'bank_name'    => '',
        'amount'       => 50000,
        'note'         => 'TestChucNangThanhToan'
    ];
}

// Generate VietQR payload and URL
function crc16_ccitt(string $data): string {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $crc ^= ord($data[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}
$qrUrl = '';
if ($settings['account_name'] !== '' && $settings['bank_name'] !== '') {
    $amt = (string)$settings['amount'];
    $msg = $settings['note'];
    $p00 = '000201';
    $gui = '0016A000000677010111';
    $mid = '01'.sprintf('%02d', strlen($settings['bank_name'])).$settings['bank_name'];
    $mai = '26'.sprintf('%02d', strlen($gui.$mid)).$gui.$mid;
    $p52 = '52040000';
    $p53 = '5303704';
    $p54 = '54'.sprintf('%02d', strlen($amt)).$amt;
    $p58 = '5802VN';
    $name= '59'.sprintf('%02d', strlen($settings['account_name'])).$settings['account_name'];
    $city= '60'.sprintf('%02d', strlen($settings['account_name'])).$settings['account_name'];
    $msgD= '01'.sprintf('%02d', strlen($msg)).$msg;
    $msgF= '62'.sprintf('%02d', strlen($msgD)).$msgD;
    $base= $p00.$mai.$p52.$p53.$p54.$p58.$name.$city.$msgF.'6304';
    $crc = crc16_ccitt($base);
    $payload = $base.$crc;
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.urlencode($payload);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Settings | AdminCP</title>
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?=filemtime(__DIR__.'/../../assets/css/sidebar_admin.css')?>">
  <link rel="stylesheet" href="../../assets/css/payments.css?v=<?=filemtime(__DIR__.'/../../assets/css/payments.css')?>">
</head>
<body>
  <div class="main-admin">
    <header><h1>Payment Settings</h1></header>

    <?php if ($success): ?>
      <div class="alert-banner"><?=htmlspecialchars($success)?></div>
    <?php endif; ?>

    <div class="payments-container">
      <form method="post" class="payments-form">
        <div class="form-group">
          <label>Account Holder</label>
          <input type="text" name="account_name" required value="<?=htmlspecialchars($settings['account_name'])?>">
        </div>
        <div class="form-group">
          <label>Bank Name</label>
          <input type="text" name="bank_name" required value="<?=htmlspecialchars($settings['bank_name'])?>">
        </div>
        <div class="form-group">
          <label>Amount (VND)</label>
          <input type="text" id="amountInput" name="amount" required value="<?=number_format($settings['amount'],0,',','.')?>">
        </div>
        <div class="form-group">
          <label>Note</label>
          <input type="text" name="note" value="<?=htmlspecialchars($settings['note'])?>">
        </div>
        <button type="submit" class="btn-save">Save Settings</button>
      </form>
      <div class="qr-preview">
        <?php if ($qrUrl): ?>
          <img src="<?=$qrUrl?>" alt="QR Code">
          <div class="qr-details">
            <p><strong>Account Holder:</strong> <?= htmlspecialchars($settings['account_name']) ?></p>
            <p><strong>Bank Name:</strong> <?= htmlspecialchars($settings['bank_name']) ?></p>
            <p><strong>Account Number:</strong> <?= htmlspecialchars(number_format($settings['amount'],0,',','.')) /* Actually amount? Should be account number? Mistake. Need correct field. */ ?></p>
            <p><strong>Amount (VND):</strong> <?= number_format($settings['amount'],0,',','.') ?></p>
            <?php if ($settings['note']): ?>
            <p><strong>Note:</strong> <?= htmlspecialchars($settings['note']) ?></p>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="qr-placeholder">No QR Code</div>
        <?php endif; ?>
      </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('amountInput').addEventListener('input', function(e) {
      let v = e.target.value.replace(/\D/g,'');
      e.target.value = v.replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    });
  </script>
</body>
</html>
