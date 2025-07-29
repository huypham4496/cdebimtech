<?php
// pages/login.php
// UTF-8 no BOM
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
            'id'     => $user['id'],
            'name'   => $user['first_name'],
            'avatar' => $user['avatar'],
        ];
        header('Location: ../index.php');
        exit;
    }
    $error = 'Email hoặc mật khẩu không đúng.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/login.css?v=<?php echo filemtime(__DIR__.'/../assets/css/login.css'); ?>">
  
</head>
<body>
  <div class="login-container">

    <!-- Bên trái: background + overlay -->
    <div class="login-left">
      <img src="../assets/images/login-bg.jpg" alt="Background">
      <div class="overlay">
        <h1 class="text-primary">CDE Bimtech</h1>
        <p>
          Empower your workflow with real-time 3D visualization, full data ownership,
          and powerful BIM data analysis. Secure, immersive, and built for limitless collaboration.
        </p>
      </div>
    </div>

    <!-- Bên phải: form login -->
    <div class="login-right">
      <img class="logo" src="../assets/images/logo-login.png" alt="CDE Bimtech Logo">
      <h2>Login to CDE Bimtech</h2>
      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="login-form">
        <label class="required" for="email">Username</label>
        <input id="email" name="email" type="email" placeholder="Enter your email" required>

        <label class="required" for="password">Password</label>
        <div class="password-wrapper">
          <input id="password" name="password" type="password" placeholder="Enter your password" required>
          
        </div>

        <button type="submit">Login</button>
        <a href="#" class="forgot">Forgot password?</a>
      </form>

      <p class="register">Don't have an account? <a href="register.php" class="text-primary">Sign up</a></p>
    </div>

  </div>

  <!-- Footer bottom-left -->
  <div class="footer-link-wrapper">
    &copy; 2025 a product of <a href="https://bimtech.edu.vn" class="footer-link">Bimtech</a>
  </div>

</body>
</html>