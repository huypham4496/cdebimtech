<?php
// pages/admin/payments.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

require_once __DIR__ . '/sidebar_admin.php';
require_once __DIR__ . '/../../config.php';

// Fetch supported banks from API or fallback
$apiUrl = 'https://qr.ecaptcha.vn/api/banks';
$banks = [];
try {
    $json = @file_get_contents($apiUrl);
    $list = $json ? json_decode($json, true) : [];
    if (is_array($list)) {
        foreach ($list as $bank) {
            if (!empty($bank['code']) && !empty($bank['name'])) {
                $banks[$bank['code']] = $bank['name'];
            }
        }
    }
} catch (Exception $e) {
    // ignore, fallback below
}
if (empty($banks)) {
    $banks = [
        'acb'        => 'ACB',
        'agribank'   => 'Agribank',
        'bidv'       => 'BIDV',
        'mbbank'     => 'MB Bank',
        'sacombank'  => 'Sacombank',
        'techcombank'=> 'Techcombank',
        'vcb'        => 'Vietcombank',
        'vietinbank' => 'VietinBank',
        'vpbank'     => 'VPBank'
    ];
}
// Sort banks by code alphabetically
ksort($banks, SORT_STRING);

// Database connection
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('DB Connection Error: ' . htmlspecialchars($e->getMessage()));
}

// Handle form submission
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountName   = trim($_POST['account_name'] ?? '');
    $bankCode      = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $amountRaw     = str_replace('.', '', trim($_POST['amount'] ?? ''));
    $noteRaw       = trim($_POST['note'] ?? '');

    if (!$accountName || !$bankCode || !$accountNumber || !$amountRaw) {
        $success = 'Please fill all required fields.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO payment_settings
               (id, account_name, bank_name, account_number, amount, note) VALUES (1,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               account_name=VALUES(account_name), bank_name=VALUES(bank_name),
               account_number=VALUES(account_number), amount=VALUES(amount), note=VALUES(note)'
        );
        $stmt->execute([$accountName, $bankCode, $accountNumber, $amountRaw, $noteRaw]);
        $success = 'Payment settings saved.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Fetch current settings or defaults
$stmt = $pdo->query('SELECT account_name, bank_name, account_number, amount, note FROM payment_settings WHERE id=1');
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'account_name'   => '',
    'bank_name'      => '',
    'account_number' => '',
    'amount'         => 50000,
    'note'           => 'TestChucNangThanhToan'
];

// Prepare QR via quicklink API
$bankCode = $settings['bank_name'];
$amountRaw = str_replace('.', '', (string)$settings['amount']);
$memo = preg_replace('/[^A-Za-z0-9 ]/', '', $settings['note']);
$memo = substr($memo, 0, 25);
$qrUrl = '';
if ($settings['account_number'] && isset($banks[$bankCode]) && (int)$amountRaw >= 1000) {
    $qrUrl = sprintf(
        'https://qr.ecaptcha.vn/api/generate/%s/%s/VIETQR.CC?amount=%s&memo=%s&is_mask=0',
        $bankCode,
        $settings['account_number'],
        $amountRaw,
        urlencode($memo)
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
          <input name="account_name" type="text" required value="<?=htmlspecialchars($settings['account_name'])?>">
        </div>
        <div class="form-group">
          <label>Bank</label>
          <select name="bank_name" required>
            <?php foreach ($banks as $code => $name): ?>
              <option value="<?=htmlspecialchars($code)?>" <?php if ($settings['bank_name'] === $code) echo 'selected'; ?>>
                <?=strtoupper(htmlspecialchars($code))?> - <?=htmlspecialchars($name)?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Account Number</label>
          <input name="account_number" type="text" required value="<?=htmlspecialchars($settings['account_number'])?>">
        </div>
        <div class="form-group">
          <label>Amount (VND)</label>
          <input id="amountInput" name="amount" type="text" required value="<?=number_format($settings['amount'],0,',','.')?>">
        </div>
        <div class="form-group">
          <label>Note</label>
          <input name="note" type="text" value="<?=htmlspecialchars($settings['note'])?>">
        </div>
        <button type="submit" class="btn-save">Save Settings</button>
      </form>
      <div class="qr-container">
        <div class="qr-frame">
          <?php if ($qrUrl): ?>
            <img src="<?=$qrUrl?>" alt="QR Code" class="qr-code">
          <?php endif; ?>
        </div>
        <div class="qr-details">
          <p><strong>Account Holder:</strong> <?=htmlspecialchars($settings['account_name'])?></p>
          <p><strong>Bank:</strong> <?=strtoupper(htmlspecialchars($settings['bank_name']))?> - <?=htmlspecialchars($banks[$settings['bank_name']])?></p>
          <p><strong>Acct No:</strong> <?=htmlspecialchars($settings['account_number'])?></p>
          <p><strong>Amount:</strong> <?=number_format($settings['amount'],0,',','.')?></p>
          <?php if ($settings['note']): ?>
            <p><strong>Note:</strong> <?=htmlspecialchars($settings['note'])?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.getElementById('amountInput').addEventListener('input', function(e){
      let v = e.target.value.replace(/\D/g,'');
      e.target.value = v.replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    });
  </script>
</body>
</html>