<?php
// pages/admin/payments.php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session & enforce admin
session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Include sidebar navigation
require_once __DIR__ . '/sidebar_admin.php';
// Load database configuration
require_once __DIR__ . '/../../config.php';

// Connect to DB
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('DB Connection Error: ' . htmlspecialchars($e->getMessage()));
}

// Process form submission
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $holder        = trim($_POST['account_name'] ?? '');
    $bank          = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $amountRaw     = str_replace('.', '', trim($_POST['amount'] ?? ''));
    $note          = trim($_POST['note'] ?? '');

    if ($holder === '' || $bank === '' || $accountNumber === '' || $amountRaw === '') {
        $success = 'Please fill all required fields.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO payment_settings
                   (id, account_name, bank_name, account_number, amount, note)
                 VALUES (1, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   account_name=VALUES(account_name),
                   bank_name=VALUES(bank_name),
                   account_number=VALUES(account_number),
                   amount=VALUES(amount),
                   note=VALUES(note)'
            );
            $stmt->execute([
                $holder, $bank, $accountNumber, $amountRaw, $note
            ]);
            $success = 'Payment settings saved.';
            // Redirect to avoid resubmission
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } catch (PDOException $e) {
            $success = 'Error saving settings: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch settings
try {
    $stmt = $pdo->query(
        'SELECT account_name, bank_name, account_number, amount, note
           FROM payment_settings WHERE id=1'
    );
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = false;
}
if (!$settings) {
    $settings = [
        'account_name'   => '',
        'bank_name'      => '',
        'account_number' => '',
        'amount'         => 50000,
        'note'           => 'TestChucNangThanhToan'
    ];
}

// CRC16 CCITT function
function crc16_ccitt(string $data): string {
    $crc = 0xFFFF;
    for ($i = 0, $len = strlen($data); $i < $len; $i++) {
        $crc ^= ord($data[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

// Build QR URL
$qrUrl = '';
if ($settings['account_name'] && $settings['bank_name'] && $settings['account_number']) {
    $amt = (string)$settings['amount'];
    $msg = $settings['note'];
    $p00 = '000201';
    $gui = '0016A000000677010111';
    $mid = '01' . str_pad(strlen($settings['account_number']), 2, '0', STR_PAD_LEFT) . $settings['account_number'];
    $mai = '26' . str_pad(strlen($gui . $mid), 2, '0', STR_PAD_LEFT) . $gui . $mid;
    $p52 = '52040000';
    $p53 = '5303704';
    $p54 = '54' . str_pad(strlen($amt), 2, '0', STR_PAD_LEFT) . $amt;
    $p58 = '5802VN';
    $name = '59' . str_pad(strlen($settings['account_name']), 2, '0', STR_PAD_LEFT) . $settings['account_name'];
    $city = '60' . str_pad(strlen($settings['bank_name']), 2, '0', STR_PAD_LEFT) . $settings['bank_name'];
    $msgD = '01' . str_pad(strlen($msg), 2, '0', STR_PAD_LEFT) . $msg;
    $msgF = '62' . str_pad(strlen($msgD), 2, '0', STR_PAD_LEFT) . $msgD;
    $base = $p00 . $mai . $p52 . $p53 . $p54 . $p58 . $name . $city . $msgF . '6304';
    $crc  = crc16_ccitt($base);
    $payload = $base . $crc;
    $qrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($payload);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment Settings | AdminCP</title>
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/sidebar_admin.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/payments.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/payments.css'); ?>">
</head>
<body>
  <div class="main-admin">
    <header><h1>Payment Settings</h1></header>

    <?php if ($success): ?>
      <div class="alert-banner"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="payments-container">
      <form method="post" class="payments-form">
        <div class="form-group">
          <label>Account Holder</label>
          <input type="text" name="account_name" value="<?php echo htmlspecialchars($settings['account_name']); ?>" required>
        </div>
        <div class="form-group">
          <label>Bank Name</label>
          <input type="text" name="bank_name" value="<?php echo htmlspecialchars($settings['bank_name']); ?>" required>
        </div>
        <div class="form-group">
          <label>Account Number</label>
          <input type="text" name="account_number" value="<?php echo htmlspecialchars($settings['account_number']); ?>" required>
        </div>
        <div class="form-group">
          <label>Amount (VND)</label>
          <input id="amountInput" type="text" name="amount" value="<?php echo number_format($settings['amount'], 0, ',', '.'); ?>" required>
        </div>
        <div class="form-group">
          <label>Note</label>
          <input type="text" name="note" value="<?php echo htmlspecialchars($settings['note']); ?>">
        </div>
        <button type="submit" class="btn-save">Save Settings</button>
      </form>
      <div class="qr-container">
        <div class="qr-frame">
          <?php if ($qrUrl): ?>
            <img src="<?php echo $qrUrl; ?>" alt="QR Code" class="qr-code">
          <?php endif; ?>
        </div>
        <div class="qr-details">
          <p><strong>Account Holder:</strong> <?php echo htmlspecialchars($settings['account_name']); ?></p>
          <p><strong>Bank:</strong> <?php echo htmlspecialchars($settings['bank_name']); ?></p>
          <p><strong>Acct No:</strong> <?php echo htmlspecialchars($settings['account_number']); ?></p>
          <p><strong>Amount:</strong> <?php echo number_format($settings['amount'], 0, ',', '.'); ?></p>
          <?php if ($settings['note']): ?>
            <p><strong>Note:</strong> <?php echo htmlspecialchars($settings['note']); ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    </div>
  </div>

  <script>
    document.getElementById('amountInput').addEventListener('input', function(e) {
      let v = e.target.value.replace(/\D/g, '');
      e.target.value = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });
  </script>
</body>
</html>