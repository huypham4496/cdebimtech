<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection error: ' . htmlspecialchars($e->getMessage()));
}

// Handle payment confirmation
if (isset($_POST['confirm'])) {
    $userId         = $_SESSION['user']['id'];
    $subscriptionId = (int)($_GET['sub_id'] ?? 0);
    $duration       = $_POST['dur'] ?? '';
    $voucherCode    = trim($_POST['voucher_code'] ?? '');
    $discountPct    = floatval($_POST['discount_percent'] ?? 0);
    $amountPaid     = floatval($_POST['final_price'] ?? 0);
    $memo           = $_POST['memo'] ?? '';

    // Insert into subscription_orders (create this table if not exists)
    $stmt = $pdo->prepare("
        INSERT INTO subscription_orders
        (user_id, subscription_id, duration, voucher_code, discount_percent, amount_paid, memo, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId,
        $subscriptionId,
        $duration,
        $voucherCode,
        $discountPct,
        $amountPaid,
        $memo
    ]);

    header('Location: thank_you.php'); // or wherever
    exit;
}

// Voucher check API endpoint
if (isset($_GET['check_voucher'])) {
    header('Content-Type: application/json');
    $code  = trim($_GET['voucher_code'] ?? '');
    $today = date('Y-m-d');
    $stmtV = $pdo->prepare(
        'SELECT discount, expiry_date FROM vouchers WHERE code = ? LIMIT 1'
    );
    $stmtV->execute([$code]);
    $v = $stmtV->fetch(PDO::FETCH_ASSOC);
    if (!$v) {
        echo json_encode(['status' => 'invalid']);
    } elseif ($v['expiry_date'] < $today) {
        echo json_encode(['status' => 'expired']);
    } else {
        echo json_encode([
            'status'   => 'success',
            'discount' => floatval($v['discount'])
        ]);
    }
    exit;
}

// Fetch subscription and payment data
$id = isset($_GET['sub_id']) ? (int)$_GET['sub_id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE id = ?');
$stmt->execute([$id]);
$sub = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sub) {
    die('Invalid subscription.');
}
$pay = $pdo->query('SELECT * FROM payment_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Subscribe - <?= htmlspecialchars($sub['name']) ?></title>
  <link rel="stylesheet" href="../assets/fonts/font_inter.css?v=<?php echo filemtime('../assets/fonts/font_inter.css'); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime('../assets/css/sidebar.css'); ?>">
  <link rel="stylesheet" href="../assets/css/subscribe.css?v=<?php echo filemtime('../assets/css/subscribe.css'); ?>">
  <link rel="stylesheet" href="../assets/css/all.min.css?v=<?php echo filemtime('../assets/css/all.min.css'); ?>">
  <style>
    .voucher-message { margin:1rem 0; color:#d9534f; font-weight:500; }
    .voucher-message.success { color:#5cb85c; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="subscribe-wrapper">
    <!-- Primary block -->
    <div class="primary-card">
      <h2><?= htmlspecialchars($sub['name']) ?></h2>
      <div class="duration-group">
        <label for="dur">Duration:</label>
        <select id="dur" onchange="updatePrice()">
          <?php foreach ([1,2,5,10,0] as $y): ?>
            <option value="<?= $y ?>"><?= $y ? "{$y} years" : 'Lifetime' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="priceInfo" class="price-info"></div>
      <div id="promoBanner" class="promo"></div>
      <div class="instructions">
                After payment, please click the <strong>Confirm Payment</strong> button. This is crucial for our system to record payment. On success, activation occurs within 5 minutes to 24 hours. You may also screenshot payment and contact us for faster activation. Hotline: 0888.121.496
      </div>
      <!-- Confirmation form -->
      <form id="confirmForm" method="post" style="display:inline;">
        <input type="hidden" name="dur" id="inputDur">
        <input type="hidden" name="voucher_code" id="inputVoucher">
        <input type="hidden" name="discount_percent" id="inputDiscountPercent">
        <input type="hidden" name="final_price" id="inputFinalPrice">
        <input type="hidden" name="memo" id="inputMemo">
        <button type="button" class="confirm-btn" onclick="confirmPayment()">Confirm Payment</button>
        <input type="hidden" name="confirm" value="1">
      </form>
    </div>

    <!-- Order card: Voucher + Summary -->
    <div class="order-card">
      <h3>Order Details</h3>
      <div class="voucher-group">
        <input id="voucher" type="text" placeholder="Enter voucher code">
        <button type="button" onclick="applyVoucher()">Apply</button>
      </div>
      <div id="voucherMessage" class="voucher-message"></div>
      <ul class="summary">
        <li><span>Original Price</span><span id="origPrice"></span></li>
        <li><span>Discount</span><span id="discount">0%</span></li>
        <li class="total"><span>Total</span><span id="finalPrice"></span></li>
      </ul>
    </div>

    <!-- Payment card -->
    <div class="payment-card">
      <h3>Payment</h3>
      <div class="qr-frame">
        <img id="qrImg" src="" alt="QR Code">
      </div>
      <div class="acct-info">
        <p>Account Name: <?= htmlspecialchars($pay['account_name']) ?></p>
        <p>Account Number: <?= htmlspecialchars($pay['account_number']) ?></p>
        <p>Bank: <?= htmlspecialchars($pay['bank_name']) ?></p>
        <p>Amount: <span id="finalPriceInfo"></span></p>
        <p>Memo: <strong id="orderMemo"></strong></p>
      </div>
    </div>
  </div>

  <script>
    const basePrice  = <?= (int)$sub['price'] ?>;
    const subId      = <?= json_encode($id) ?>;
    const planName   = <?= json_encode($sub['name']) ?>;
    const userId     = <?= json_encode($_SESSION['user']['id']) ?>;
    const promoEl    = document.getElementById('promoBanner');
    const priceEl    = document.getElementById('priceInfo');
    const origEl     = document.getElementById('origPrice');
    const discountEl = document.getElementById('discount');
    const finalEl    = document.getElementById('finalPrice');
    const finalInfoEl= document.getElementById('finalPriceInfo');
    const memoEl     = document.getElementById('orderMemo');
    const qrEl       = document.getElementById('qrImg');
    const voucherMsgEl = document.getElementById('voucherMessage');

    const inputDur = document.getElementById('inputDur');
    const inputVoucher = document.getElementById('inputVoucher');
    const inputDiscount = document.getElementById('inputDiscountPercent');
    const inputFinal = document.getElementById('inputFinalPrice');
    const inputMemo = document.getElementById('inputMemo');

    function fmt(amount) {
      return amount.toLocaleString('vi-VN') + ' VND';
    }
    function random4() {
      return Math.random().toString(36).substr(2,4).toUpperCase();
    }

    function updatePrice() {
      const y = parseInt(document.getElementById('dur').value,10);
      let multiplier;
      if (y===5) multiplier=4;
      else if (y===10) multiplier=7;
      else if (y===0) multiplier=30;
      else multiplier=y;

      const totalAmt = multiplier * basePrice;
      const years = y===0?1:y;
      const perYear = totalAmt/years;

      priceEl.textContent = fmt(perYear)+(y?' / year':' (Lifetime)');
      promoEl.textContent = y===0?'Exclusive benefits':'Great value!';
      origEl.textContent = fmt(totalAmt);
      discountEl.textContent = '0%';
      finalEl.textContent = fmt(totalAmt);
      finalInfoEl.textContent = fmt(totalAmt);
      voucherMsgEl.textContent = '';
      voucherMsgEl.classList.remove('success');

      const durLabel = y?y+'y':'LT';
      const memo = `BT_${userId}_${planName}_${durLabel}_${random4()}`;
      memoEl.textContent = memo;
      qrEl.src = `https://qr.ecaptcha.vn/api/generate/<?= strtolower($pay['bank_name']) ?>/<?= $pay['account_number'] ?>/${memo}`+
                 `?amount=${totalAmt}&memo=${memo}&is_mask=0`;
    }
    updatePrice();

    function confirmPayment() {
      // populate hidden inputs then submit
      const sel = document.getElementById('dur');
      const y = sel.value;
      inputDur.value = y;
      inputVoucher.value = document.getElementById('voucher').value.trim();
      inputDiscount.value = discountEl.textContent.replace('%','');
      inputFinal.value = finalInfoEl.textContent.replace(/[^\d]/g,'');
      inputMemo.value = memoEl.textContent;
      document.getElementById('confirmForm').submit();
    }

    async function applyVoucher() {
      const code = document.getElementById('voucher').value.trim();
      if (!code) {
        voucherMsgEl.textContent = 'Please enter a voucher code.';
        voucherMsgEl.classList.remove('success');
        return;
      }
      try {
        const resp = await fetch(`?sub_id=${subId}&check_voucher=1&voucher_code=`+encodeURIComponent(code));
        const data = await resp.json();
        const orig = parseInt(origEl.textContent.replace(/[^\d]/g,''),10);
        if (data.status==='invalid') {
          voucherMsgEl.textContent='Invalid voucher code.';
          voucherMsgEl.classList.remove('success');
          discountEl.textContent='0%';
          finalEl.textContent=fmt(orig);
          finalInfoEl.textContent=fmt(orig);
        } else if (data.status==='expired') {
          voucherMsgEl.textContent='Voucher expired.';
          voucherMsgEl.classList.remove('success');
          discountEl.textContent='0%';
          finalEl.textContent=fmt(orig);
          finalInfoEl.textContent=fmt(orig);
        } else {
          voucherMsgEl.textContent=`Voucher applied: -${data.discount}%`;
          voucherMsgEl.classList.add('success');
          discountEl.textContent=`${data.discount}%`;
          const discountAmt=orig*(data.discount/100);
          const finalAmt=orig-discountAmt;
          finalEl.textContent=fmt(finalAmt);
          finalInfoEl.textContent=fmt(finalAmt);
        }
      } catch(e){
        console.error(e);
      }
    }
  </script>
</body>
</html>
