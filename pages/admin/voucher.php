<?php
// File: pages/admin/voucher.php
session_start();

// Điều chỉnh đường dẫn đến config và includes
require_once __DIR__ . '/../../config.php';

// Kiểm tra quyền admin
if (
    !isset($_SESSION['user']) 
    || !isset($_SESSION['user']['role']) 
    || $_SESSION['user']['role'] !== 'admin'
) {
    // Nếu chưa login hoặc không phải admin, chuyển về trang login
    header('Location: ../../pages/login.php');
    exit;
}

// Kết nối DB
$pdo = getPDO();

// Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $code     = trim($_POST['code']);
        $discount = floatval($_POST['discount']);
        $expiry   = $_POST['expiry'];
        $stmt = $pdo->prepare(
            "INSERT INTO vouchers (code, discount, expiry_date) VALUES (?, ?, ?)"
        );
        $stmt->execute([$code, $discount, $expiry]);
    }
    if (isset($_POST['delete'])) {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM vouchers WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: voucher.php');
    exit;
}

// Lấy danh sách vouchers
$stmt    = $pdo->query("SELECT * FROM vouchers ORDER BY created_at DESC");
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Voucher</title>
    <link rel="stylesheet" href="../../assets/css/header.css">
    <link rel="stylesheet" href="../../assets/css/voucher.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="voucher-container">
        <h1>Quản lý Voucher</h1>
        <form class="voucher-form" method="post">
            <input type="text" name="code" placeholder="Mã voucher" required>
            <input type="number" name="discount" placeholder="% Giảm giá" step="0.01" required>
            <input type="date" name="expiry" required>
            <button type="submit" name="add">Thêm Voucher</button>
        </form>

        <table class="voucher-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Mã</th>
                    <th>Giảm giá</th>
                    <th>Hết hạn</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vouchers as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['id']) ?></td>
                    <td><?= htmlspecialchars($v['code']) ?></td>
                    <td><?= htmlspecialchars($v['discount']) ?>%</td>
                    <td><?= htmlspecialchars($v['expiry_date']) ?></td>
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
</body>
</html>
