<?php
// pages/subscribe.php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Nạp config từ thư mục gốc
require_once __DIR__ . '/../config.php';

// Kết nối database
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('DB Error: ' . htmlspecialchars($e->getMessage()));
}

// Lấy danh sách gói
$stmt = $pdo->query('SELECT id, name, price, description FROM subscriptions ORDER BY id ASC');
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xử lý khi bấm “Choose”
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'], $_POST['years'])) {
    $planId = (int)$_POST['plan_id'];
    $years  = (int)$_POST['years'];
    if (!in_array($years, [1,2,5,10,0], true)) {
        $years = 1;
    }
    // Tìm gói đã chọn
    foreach ($plans as $p) {
        if ($p['id'] === $planId) {
            $plan = $p;
            break;
        }
    }
    if (isset($plan)) {
        $_SESSION['checkout'] = [
            'plan_id'    => $plan['id'],
            'plan_name'  => $plan['name'],
            'unit_price' => $plan['price'],
            'years'      => $years,
            'total'      => $years === 0 ? 0 : $plan['price'] * $years,
            'note'       => $_SESSION['user']['id'] 
                           . $plan['name'] 
                           . ($years === 0 ? 'perm' : $years) 
                           . substr(bin2hex(random_bytes(2)), 0, 4)
        ];
        header('Location: purchase.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subscriptions | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/subscriptions.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-dynvDxJ5aVF6oU1i6zfoalvVYvNvKcJste/0q5u+P%2FgPm4jG3E5s3UeJ8V+RaH59RUW2YCiMzZ6pyRrg58F3CA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo filemtime('../assets/css/dashboard.css'); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime('../assets/css/sidebar.css'); ?>">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main">
    <header><h1>Choose a Subscription</h1></header>
    <div class="plans-list">
      <?php foreach ($plans as $p): ?>
      <div class="plan-card">
        <h2><?= htmlspecialchars($p['name']) ?></h2>
        <p><?= nl2br(htmlspecialchars($p['description'])) ?></p>
        <p class="price"><?= number_format($p['price'],0,',','.') ?> VND / year</p>
        <form method="post">
          <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
          <label>Years:
            <select name="years">
              <option value="1">1 year</option>
              <option value="2">2 years</option>
              <option value="5">5 years</option>
              <option value="10">10 years</option>
              <option value="0">Forever</option>
            </select>
          </label>
          <button type="submit" class="btn-choose">Choose</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
