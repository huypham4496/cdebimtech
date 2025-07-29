<?php
// pages/register.php
// UTF-8 no BOM
session_start();
require_once __DIR__ . '/../includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = registerUser(
        trim($_POST['username']),
        trim($_POST['first_name']),
        trim($_POST['last_name']),
        trim($_POST['dob']),
        trim($_POST['address']),
        trim($_POST['company']),
        trim($_POST['phone']),
        trim($_POST['invite_code']),
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
    <div class="login-right">
      <img class="logo" src="../assets/images/logo-login.png" alt="CDE Bimtech Logo">
      <h2>Register for CDE Bimtech</h2>
      <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" class="login-form">
        <!-- username, names, dob, address, company, phone, invite, email, passwords -->
        <!-- ... as previously defined ... -->
      </form>
      <p class="register">Already have an account? <a href="login.php" class="text-primary">Login here</a></p>
    </div>
  </div>
  <div class="footer-link-wrapper">
    &copy; 2025 a product of <a href="https://bimtech.edu.vn" class="footer-link">Bimtech</a>
  </div>
</body>
</html>