<?php
// pages/register.php
// UTF-8 no BOM

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Collect and sanitize input
    $username        = trim($_POST['username']        ?? '');
    $first_name      = trim($_POST['first_name']      ?? '');
    $last_name       = trim($_POST['last_name']       ?? '');
    $dob             = trim($_POST['dob']             ?? '');
    $address         = trim($_POST['address']         ?? '');
    $company         = trim($_POST['company']         ?? '');
    $phone           = trim($_POST['phone']           ?? '');
    $invite_code     = trim($_POST['invite_code']     ?? '');
    $email           = trim($_POST['email']           ?? '');
    $password        = $_POST['password']             ?? '';
    $confirm_pass    = $_POST['confirm_password']     ?? '';

    // 2) Validate required fields
    if (!$username || !$first_name || !$last_name || !$email || !$password || !$confirm_pass) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($password !== $confirm_pass) {
        $error = 'Password and confirmation do not match.';
    } else {
        // 3) Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // 4) Attempt to register
        try {
            $pdo = getPDO();
            $ok  = registerUser(
                $pdo,
                $username,
                $first_name,
                $last_name,
                $dob,
                $address,
                $company,
                $phone,
                $invite_code,
                $email,
                $password_hash,
                'user',
                null  // no avatar on register
            );
            if ($ok) {
                $success = 'Registration successful! You can <a href="login.php">log in now</a>.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'System error. Please contact the administrator.';
        }
    }
}

// prepare cache-busted CSS link
$cssVer = file_exists(__DIR__ . '/../assets/css/login.css')
    ? filemtime(__DIR__ . '/../assets/css/login.css')
    : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Register | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/login.css?v=<?= $cssVer ?>">
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <img src="../assets/images/login-bg.jpg" alt="Background">
      <div class="overlay">
        <h1 class="text-primary">CDE Bimtech</h1>
        <p>Empower your workflow with real-time 3D visualization, full data ownership, and powerful BIM data analysis. Secure, immersive, and built for limitless collaboration.</p>
      </div>
    </div>
    <div class="login-right">
      <img class="logo" src="../assets/images/logo-login.png" alt="CDE Bimtech Logo">
      <h2>Register for CDE Bimtech</h2>

      <?php if ($error): ?>
        <div class="alert-banner error"><?= htmlspecialchars($error) ?></div>
      <?php elseif ($success): ?>
        <div class="alert-banner success"><?= $success ?></div>
      <?php endif; ?>

      <form method="post" class="login-form">
        <label for="username">Username*</label>
        <input id="username" name="username" type="text" value="<?= htmlspecialchars($username ?? '') ?>" required>

        <label for="first_name">First Name*</label>
        <input id="first_name" name="first_name" type="text" value="<?= htmlspecialchars($first_name ?? '') ?>" required>

        <label for="last_name">Last Name*</label>
        <input id="last_name" name="last_name" type="text" value="<?= htmlspecialchars($last_name ?? '') ?>" required>

        <label for="dob">Date of Birth</label>
        <input id="dob" name="dob" type="date" value="<?= htmlspecialchars($dob ?? '') ?>">

        <label for="address">Address</label>
        <input id="address" name="address" type="text" value="<?= htmlspecialchars($address ?? '') ?>">

        <label for="company">Company</label>
        <input id="company" name="company" type="text" value="<?= htmlspecialchars($company ?? '') ?>">

        <label for="phone">Phone</label>
        <input id="phone" name="phone" type="tel" value="<?= htmlspecialchars($phone ?? '') ?>">

        <label for="invite_code">Invite Code</label>
        <input id="invite_code" name="invite_code" type="text" value="<?= htmlspecialchars($invite_code ?? '') ?>">

        <label for="email">Email*</label>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($email ?? '') ?>" required>

        <label for="password">Password*</label>
        <input id="password" name="password" type="password" required>

        <label for="confirm_password">Confirm Password*</label>
        <input id="confirm_password" name="confirm_password" type="password" required>

        <button type="submit" class="btn-primary">Register</button>
      </form>

      <p class="register">Already have an account? <a href="login.php" class="text-primary">Login here</a></p>
    </div>
  </div>

  <div class="footer-link-wrapper">
    &copy; 2025 a product of <a href="https://bimtech.edu.vn" class="footer-link">Bimtech</a>
  </div>
</body>
</html>
