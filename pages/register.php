<?php
// pages/register.php
// UTF-8 no BOM
session_start();
require_once __DIR__ . '/../includes/functions.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = registerUser($_POST, $_FILES);
    if ($res === true) {
        header('Location: login.php?registered=1');
        exit;
    }
    $error = $res;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký | CDE</title>
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body class="register-page">
    <div class="register-wrapper">
        <div class="register-card">
            <h2>Đăng ký thành viên CDE</h2>
            <?php if ($error): ?><div class="error-msg"><?=htmlspecialchars($error)?></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="register-form">
                <div class="input-row">
                    <input name="first_name" placeholder="First name" required>
                    <input name="last_name" placeholder="Last name" required>
                </div>
                <input name="phone" placeholder="Phone number" required>
                <input name="email" type="email" placeholder="Email" required>
                <input name="cccd_number" placeholder="Số CCCD">
                <input name="invite_code" placeholder="Mã invite (nếu có)">
                <label for="cccd_image">Upload ảnh CCCD (nếu không có mã invite):</label>
                <input id="cccd_image" name="cccd_image" type="file" accept="image/*">
                <input name="company" placeholder="Company" required>
                <input name="dob" type="date" required>
                <input name="address" placeholder="Address" required>
                <input name="password" type="password" placeholder="Password" required>
                <input name="confirm_password" type="password" placeholder="Confirm Password" required>
                <button type="submit">Đăng ký</button>
                <p class="redirect">Bạn đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
            </form>
        </div>
    </div>
</body>
</html>