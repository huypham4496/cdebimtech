<?php
// File: pages/admin/voucher.php

session_start();
require_once __DIR__ . '/../../config.php';

// Kết nối MySQLi theo mẫu subscriptions_info.php
$connect = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$connect) {
    die('Kết nối CSDL thất bại: ' . mysqli_connect_error());
}

// Kiểm tra login & quyền admin
if (
    empty($_SESSION['user'])
    || empty($_SESSION['user']['role'])
    || $_SESSION['user']['role'] !== 'admin'
) {
    header('Location: /pages/login.php');
    exit;
}

$message = '';
$error   = '';

// Xử lý POST thêm/xóa voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Thêm voucher
    if (isset($_POST['add'])) {
        $code     = mysqli_real_escape_string($connect, substr(trim($_POST['code']), 0, 50));
        $discount = number_format(floatval($_POST['discount']), 2, '.', '');
        $expiry   = $_POST['expiry'];

        $sql = "
            INSERT INTO vouchers (code, discount, expiry_date)
            VALUES ('$code', '$discount', '$expiry')
        ";
        if (mysqli_query($connect, $sql)) {
            $message = 'Thêm voucher thành công.';
        } else {
            if (mysqli_errno($connect) === 1062) {
                $error = 'Mã voucher đã tồn tại.';
            } else {
                $error = 'Lỗi thêm voucher: ' . mysqli_error($connect);
            }
        }
    }

    // Xóa voucher
    if (isset($_POST['delete'])) {
        $id = intval($_POST['id']);
        mysqli_query($connect, "DELETE FROM vouchers WHERE id = $id");
        $message = 'Xóa voucher thành công.';
    }

    // Chuyển hướng lại để hiển thị thông báo
    header(
        'Location: /pages/admin/voucher.php'
        . '?msg=' . urlencode($message)
        . '&err=' . urlencode($error)
    );
    exit;
}

// Nhận thông báo từ query string
$message = $_GET['msg'] ?? '';
$error   = $_GET['err'] ?? '';

// Lấy danh sách vouchers
$result   = mysqli_query($connect, "SELECT * FROM vouchers ORDER BY created_at DESC");
$vouchers = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Voucher</title>
<link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/sidebar_admin.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/voucher.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/voucher.css'); ?>">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="admin-container">
        <?php include __DIR__ . '/sidebar_admin.php'; ?>

        <div class="main-content">
            <div class="voucher-container">
                <h1>Quản lý Voucher</h1>

                <?php if ($message): ?>
                    <div class="alert success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form class="voucher-form" method="post">
                    <input type="text"   name="code"     placeholder="Mã voucher" maxlength="50" required>
                    <input type="number" name="discount" placeholder="% Giảm giá" step="0.01" min="0" required>
                    <input type="date"   name="expiry"   required>
                    <button type="submit" name="add">Thêm Voucher</button>
                </form>

                <table class="voucher-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mã</th>
                            <th>Giảm giá</th>
                            <th>Hết hạn</th>
                            <th>Ngày tạo</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vouchers as $v): ?>
                        <tr>
                            <td><?= $v['id'] ?></td>
                            <td><?= htmlspecialchars($v['code']) ?></td>
                            <td><?= htmlspecialchars($v['discount']) ?>%</td>
                            <td><?= htmlspecialchars($v['expiry_date']) ?></td>
                            <td><?= htmlspecialchars($v['created_at']) ?></td>
                            <td>
                                <form method="post"
                                      onsubmit="return confirm('Xóa voucher này?');"
                                      style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                    <button type="submit" name="delete">Xóa</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
