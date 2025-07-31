// pages/subscribe.php
<?php
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
require_once __DIR__.'/config.php';
// DB
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

// Fetch subscription
$id = (int)($_GET['sub_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE id=?');
$stmt->execute([$id]);
$sub = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sub) { echo "Subscription not found."; exit; }

// Payment settings
$ps = $pdo->query('SELECT * FROM payment_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);

// Handle confirmation
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $years = (int)($_POST['years'] ?? 1);
    $total = $sub['price'] * ($years>0?$years:1);
    $userId = $_SESSION['user']['id'];
    // Generate memo: userID_name_years_XXXX
    $rand = strtoupper(substr(bin2hex(random_bytes(2)),0,4));
    $memo = $userId.'_'.$sub['name'].'_'.$years.'_'.$rand;
    // Insert purchase
    $ins = $pdo->prepare('INSERT INTO purchases (user_id, subscription_id, years, amount, memo) VALUES (?,?,?,?,?)');
    $ins->execute([$userId, $id, $years, $total, $memo]);
    header('Location: subscription_success.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subscribe: <?=htmlspecialchars($sub['name'])?></title>
  <link rel="stylesheet" href="assets/css/subscribe.css">
</head>
<body>
  <?php include __DIR__.'/pages/sidebar.php'; ?>
  <div class="main">
    <header><h1>Subscribe: <?=htmlspecialchars($sub['name'])?></h1></header>
    <div class="subscribe-container">
      <div class="sub-details">
        <p><?=nl2br(htmlspecialchars($sub['description']))?></p>
        <p>Unit Price: <?=number_format($sub['price'],0,',','.')?> VND / năm</p>
        <form method="post">
          <label>Số năm mua:
            <select name="years">
              <option value="1">1 năm</option>
              <option value="2">2 năm</option>
              <option value="5">5 năm</option>
              <option value="10">10 năm</option>
              <option value="0">Vĩnh viễn</option>
            </select>
          </label>
          <p id="total">Tổng: <?=number_format($sub['price'],0,',','.')?> VND</p>
          <button type="submit" class="btn-confirm">Xác nhận đã thanh toán</button>
        </form>
      </div>
      <div class="payment-info">
        <h2>Payment Information</h2>
        <p><strong>Account:</strong> <?=htmlspecialchars($ps['account_name'])?> - <?=htmlspecialchars($ps['bank_name'])?> (<?=htmlspecialchars($ps['account_number'])?>)</p>
        <p><strong>Note:</strong> Sử dụng memo tự động tạo</p>
        <div class="qr-frame"><img src="<?=$qrUrl?>" class="qr-code"></div>
      </div>
    </div>
  </div>
  <script>
    const price = <?=$sub['price']?>;
    const sel = document.querySelector('select[name="years"]');
    const totalP = document.getElementById('total');
    sel.addEventListener('change', ()=>{
      let y=Number(sel.value)||1;
      let t= y>0? price*y : price;
      totalP.textContent = 'Tổng: '+t.toLocaleString()+' VND';
    });
  </script>
</body>
</html>