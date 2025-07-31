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
    <link rel="stylesheet" href="../assets/css/subscribe.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="subscribe-wrapper">
        <!-- Primary block: Package, Duration, Price, Banner, Instructions -->
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
            <button class="confirm-btn" onclick="confirmPayment()">Confirm Payment</button>
        </div>

        <!-- Order card: Voucher + Summary -->
        <div class="order-card">
            <h3>Order Details</h3>
            <div class="voucher-group">
                <input id="voucher" type="text" placeholder="Enter voucher code">
                <button type="button" onclick="applyVoucher()">Apply</button>
            </div>
            <ul class="summary">
                <li><span>Original Price</span><span id="origPrice"></span></li>
                <li><span>Discount</span><span id="discount">0%</span></li>
                <li class="total"><span>Total</span><span id="finalPrice"></span></li>
            </ul>
        </div>

        <!-- Payment card: QR & Account Info -->
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
        const basePrice = <?= (int)$sub['price'] ?>;
        const promoEl = document.getElementById('promoBanner');
        const priceEl = document.getElementById('priceInfo');
        const origEl = document.getElementById('origPrice');
        const finalEl = document.getElementById('finalPrice');
        const finalInfoEl = document.getElementById('finalPriceInfo');
        const memoEl = document.getElementById('orderMemo');
        const qrEl = document.getElementById('qrImg');

        function fmt(amount) {
            return amount.toLocaleString('vi-VN') + ' VND';
        }

        function updatePrice() {
            const y = parseInt(document.getElementById('dur').value, 10);
            const multiplier = y === 0 ? 30 : y;
            const amount = multiplier * basePrice;
            priceEl.textContent = fmt(amount) + (y ? ' / year' : '');
            promoEl.textContent = y === 0
                ? 'Enjoy exclusive benefits for future services'
                : y >= 5
                    ? 'Bulk purchase bonus'
                    : 'Great value!';
            origEl.textContent = fmt(amount);
            finalEl.textContent = fmt(amount);
            finalInfoEl.textContent = fmt(amount);
            const memo = `${<?= $_SESSION['user']['id'] ?>}_${y || 'LT'}_${Date.now()}`;
            memoEl.textContent = memo;
            qrEl.src =
                `https://qr.ecaptcha.vn/api/generate/${'<?= strtolower($pay['bank_name'])?>'}/${'<?= $pay['account_number']?>'}/${memo}` +
                `?amount=${amount}&memo=${memo}&is_mask=0`;
        }
        updatePrice();

        function confirmPayment() {
            alert('Payment confirmed!');
        }

        function applyVoucher() {
            // future feature: calculate discount
            alert('Voucher feature coming soon!');
        }
    </script>
</body>
</html>