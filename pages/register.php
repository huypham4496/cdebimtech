<?php
header('Content-Type: text/html; charset=utf-8');
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
  <title>Đăng ký</title>
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
  <div class="auth-container">
    <form method="post" enctype="multipart/form-data" class="auth-form">
      <h2>Đăng ký thành viên CDE</h2>
      <?php if ($error): ?> <p class="error"><?= htmlspecialchars($error) ?></p> <?php endif; ?>
      <div class="row-flex">
        <input name="first_name" placeholder="First name" required>
        <input name="last_name" placeholder="Last name" required>
      </div>
      <input name="phone" placeholder="Phone number" required>
      <input name="email" type="email" placeholder="Email" required>
      <input name="cccd_number" placeholder="Số CCCD">
      <input name="invite_code" placeholder="Mã invite (nếu có)">
      <label>Upload ảnh CCCD (nếu không có mã invite):</label>
      <input name="cccd_image" type="file" accept="image/*">
      <input name="company" placeholder="Company" required>
      <input name="dob" type="date" required>
      <input name="address" placeholder="Address" required>
      <input name="password" type="password" placeholder="Password" required>
      <input name="confirm_password" type="password" placeholder="Confirm Password" required>
      <button type="submit">Đăng ký</button>
      <p>Bạn đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
    </form>
  </div>
</body>
</html>