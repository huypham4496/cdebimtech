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
    die('DB Connection Error');
}

// Fetch subscription
$id = isset($_GET['sub_id']) ? (int)$_GET['sub_id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE id = ?');
$stmt->execute([$id]);
$sub = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sub) {
    die('Invalid subscription.');
}

// Fetch payment settings
$pay = $pdo->query('SELECT * FROM payment_settings WHERE id = 1')
          ->fetch(PDO::FETCH_ASSOC);
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
        <!-- Block 1: Subscription Info -->
        <div class="block1">
            <h2><?= htmlspecialchars($sub['name']) ?></h2>
            <div class="duration-group">
                <label for="dur">Thời hạn mua gói:</label>
                <select id="dur" onchange="updatePrice()">
                    <?php foreach ([1,2,5,10,0] as $y): ?>
                        <option value="<?= $y ?>"><?=
 $y ? "{$y} năm" : 'Vĩnh viễn' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="priceInfo" class="price-info"></div>
            <div id="promoBanner" class="banner"></div>
        </div>
        <!-- Block 2: Order Summary -->
        <div class="block2">
            <h3>Chi tiết đơn hàng</h3>
            <div class="voucher-group">
                <input type="text" placeholder="Nhập mã voucher">
                <button type="button">Apply</button>
            </div>
            <ul class="summary">
                <li><span>Giá gốc</span><span id="origPrice"></span></li>
                <li><span>Voucher</span><span id="discount">0%</span></li>
                <li class="total"><span>Còn lại</span><span id="finalPrice"></span></li>
            </ul>
        </div>
        <!-- Block 3: QR Payment -->
        <div class="block3">
            <h3>Thanh toán</h3>
            <div class="qr-frame">
                <img id="qrImg" src="" alt="QR Code">
            </div>
            <div class="acct-info">
                <p>Chủ tài khoản: <?= htmlspecialchars($pay['account_name']) ?></p>
                <p>Số tài khoản: <?= htmlspecialchars($pay['account_number']) ?></p>
                <p>Ngân hàng: <?= htmlspecialchars($pay['bank_name']) ?></p>
                <p>Số tiền: <span id="finalPriceInfo"></span></p>
                <p>Ghi chú: Mã đơn hàng <strong id="orderMemo"></strong></p>
            </div>
        </div>
    </div>
<script>
    const basePrice = <?= (int)$sub['price'] ?>;
    function fmt(amount) {
        return amount.toLocaleString('vi-VN') + ' VND';
    }
    function updatePrice() {
        const y = +document.getElementById('dur').value;
        const multiplier = y === 0 ? 30 : y;
        const amount = multiplier * basePrice;
        document.getElementById('priceInfo').textContent = fmt(amount) + (y ? ' / năm' : '');
        const banner = document.getElementById('promoBanner');
        if (y === 0) {
            banner.textContent = 'Được ưu đãi khi mua các dịch vụ sau này';
            banner.classList.add('lifetime');
        } else if (y >= 5) {
            banner.textContent = 'Buy more, save more';
            banner.classList.remove('lifetime');
        } else {
            banner.textContent = 'Great value!';
            banner.classList.remove('lifetime');
        }
        document.getElementById('origPrice').textContent = fmt(amount);
        document.getElementById('finalPrice').textContent = fmt(amount);
        document.getElementById('finalPriceInfo').textContent = fmt(amount);
        const memo = `${<?= $_SESSION['user']['id'] ?>}_${y || 'LT'}_${Date.now()}`;
        document.getElementById('orderMemo').textContent = memo;
        document.getElementById('qrImg').src =
            `https://qr.ecaptcha.vn/api/generate/${'<?= strtolower($pay['bank_name'])?>'}/${'<?= $pay['account_number']?>'}/${memo}` +
            `?amount=${amount}&memo=${memo}&is_mask=0`;
    }
    updatePrice();
</script>
</body>
</html>