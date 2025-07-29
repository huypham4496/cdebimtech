<?php
// pages/login.php
session_start();
require_once __DIR__ . '/../includes/functions.php';

// Nếu đã đăng nhập, chuyển về trang chủ
if (!empty($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Xử lý logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login']);
    $password = $_POST['password'];
    // Thử đăng nhập bằng username hoặc email
    $user = loginUser($login, $password);
    if ($user) {
        $_SESSION['user'] = $user;
        header('Location: ../index.php');
        exit;
    }
    $error = 'Username/Email hoặc mật khẩu không đúng.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <img src="../assets/images/login-bg.jpg" alt="Background">
      <div class="overlay">
        <h1 class="text-primary">CDE Bimtech</h1>
        <p>Empower your workflow with real-time 3D visualization...</p>
      </div>
    </div>
    <div class="login-right">
      <img class="logo" src="../assets/images/logo-login.png" alt="Logo">
      <?php if ($error): ?><div class="error-msg"><?=htmlspecialchars($error)?></div><?php endif; ?>
      <form method="post" class="login-form">
        <label for="login">Username or Email</label>
        <input id="login" name="login" type="text" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
        <button type="submit">Login</button>
      </form>
      <p>Don't have an account? <a href="register.php" class="text-primary">Sign up</a></p>
    </div>
  </div>
  <div class="footer-link-wrapper">
    &copy; 2025 a product of <a href="https://bimtech.edu.vn" class="footer-link">Bimtech</a>
  </div>
</body>
</html>
```php
<?php
// pages/login.php
session_start();
require_once __DIR__ . '/../includes/functions.php';

// Nếu đã login, chuyển về trang chủ
if (!empty(\$_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Xử lý logout
if (isset(\$_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

\$error = '';
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    // loginUser trả về mảng user hoặc false
    \$user = loginUser(\$_POST['email'], \$_POST['password']);
    if (\$user) {
        \$_SESSION['user'] = \$user;
        header('Location: ../index.php');
        exit;
    }
    \$error = 'Email hoặc mật khẩu không đúng.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <img src="../assets/images/login-bg.jpg" alt="Background">
      <div class="overlay">
        <h1 class="text-primary">CDE Bimtech</h1>
        <p>Empower your workflow with real-time 3D visualization...</p>
      </div>
    </div>
    <div class="login-right">
      <img class="logo" src="../assets/images/logo-login.png" alt="Logo">
      <?php if (\$error): ?><div class="error-msg"><?=htmlspecialchars(\$error)?></div><?php endif; ?>
      <form method="post" class="login-form">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
        <button type="submit">Login</button>
      </form>
      <p>Don't have an account? <a href="register.php" class="text-primary">Sign up</a></p>
    </div>
  </div>
  <div class="footer-link-wrapper">
    &copy; 2025 a product of <a href="https://bimtech.edu.vn" class="footer-link">Bimtech</a>
  </div>
</body>
</html>