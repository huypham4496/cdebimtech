<?php
defined('BASEPATH') OR exit('No direct script access allowed');
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once __DIR__ . '/../includes/functions.php';

// Đăng xuất
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = loginUser($_POST['email'], $_POST['password']);
    if ($user) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['first_name'],
            'avatar' => $user['avatar']
        ];
        header('Location: ../index.php');
        exit;
    }
    $error = 'Email hoặc mật khẩu không đúng.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập | CDE</title>
  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body class="login-page">
  <div class="login-wrapper">
    <div class="login-card">
      <div class="brand">
        <img src="../assets/images/logo-login.png" alt="CDE Logo">
      </div>
      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars(\$error) ?></div>
      <?php endif; ?>
      <form method="post" class="login-form">
        <input name="email" type="email" placeholder="Email" required>
        <input name="password" type="password" placeholder="Password" required>
        <button type="submit">Đăng nhập</button>
      </form>
    </div>
  </div>
</body>
</html>
```php
<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once __DIR__ . '/../includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = loginUser($_POST['email'], $_POST['password']);
    if ($user) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['first_name'],
            'avatar' => $user['avatar']
        ];
        header('Location: ../index.php');
        exit;
    }
    $error = 'Email hoặc password không đúng.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Đăng nhập</title>
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
  <div class="auth-container">
    <form method="post" class="auth-form">
      <h2>Đăng nhập CDE</h2>
      <?php if ($error): ?> <p class="error"><?= htmlspecialchars($error) ?></p> <?php endif; ?>
      <?php if (isset($_GET['registered'])): ?>
        <p class="success">Đăng ký thành công! Vui lòng đăng nhập.</p>
      <?php endif; ?>
      <input name="email" type="email" placeholder="Email" required>
      <input name="password" type="password" placeholder="Password" required>
      <button type="submit">Đăng nhập</button>
      <p>Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
    </form>
  </div>
</body>
</html>