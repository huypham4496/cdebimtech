<?php
// pages/register.php
// UTF-8 no BOM
session_start();
require_once __DIR__ . '/../includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hàm registerUser cần được định nghĩa trong functions.php
    $result = registerUser(
        trim($_POST['first_name']),
        trim($_POST['last_name']),
        trim($_POST['email']),
        $_POST['password'],
        $_POST['confirm_password']
    );
    if ($result['success']) {
        header('Location: login.php');
        exit;
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/login.css?v=<?php echo filemtime(__DIR__.'/../assets/css/login.css'); ?>">
</head>
<body>
  <div class="login-container">

    <!-- Bên trái giữ nguyên overlay -->
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

    <!-- Bên phải: form đăng ký -->
    <div class="login-right">
      <img class="logo" src="../assets/images/logo-login.png" alt="CDE Bimtech Logo">
      <h2>Register for CDE Bimtech</h2>
      <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="login-form">
        <label class="required" for="first_name">First Name</label>
        <input id="first_name" name="first_name" type="text" placeholder="Enter your first name" required>

        <label class="required" for="last_name">Last Name</label>
        <input id="last_name" name="last_name" type="text" placeholder="Enter your last name" required>

        <label class="required" for="email">Email</label>
        <input id="email" name="email" type="email" placeholder="Enter your email" required>

        <label class="required" for="password">Password</label>
        <input id="password" name="password" type="password" placeholder="Enter a password" required>

        <label class="required" for="confirm_password">Confirm Password</label>
        <input id="confirm_password" name="confirm_password" type="password" placeholder="Confirm your password" required>

        <button type="submit">Register</button>
      </form>

      <p class="register">Already have an account? <a href="login.php" class="text-primary">Login here</a></p>
    </div>

  </div>

  <!-- Footer bottom-left giữ nguyên -->
  <div class="footer-link-wrapper">
    &copy; 2025 a product of <a href="https://bimtech.edu.vn" class="footer-link">Bimtech</a>
  </div>
</body>
</html>