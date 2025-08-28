<?php
// pages/create_admin.php
session_start();

// Redirect if not configured
if (!file_exists(__DIR__ . '/../config.php')) {
    header('Location: ../install.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Connect to database
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Create users table if not exists
    $tables = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($tables->rowCount() === 0) {
        $schema = file_get_contents(__DIR__ . '/../schema.sql');
        $pdo->exec($schema);
    }
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username']);
    $first_name   = trim($_POST['first_name']);
    $last_name    = trim($_POST['last_name']);
    $email        = trim($_POST['email']);
    $password     = $_POST['password'];
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, first_name, last_name, email, password_hash, role)"
         . " VALUES (:username, :first_name, :last_name, :email, :password_hash, 'admin')";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([
        ':username'     => $username,
        ':first_name'   => $first_name,
        ':last_name'    => $last_name,
        ':email'        => $email,
        ':password_hash'=> $password_hash,
    ])) {
        header('Location: login.php');
        exit;
    }
    $error = 'Failed to create admin.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Admin | CDE NextInfra</title>
  <link rel="stylesheet" href="../assets/css/create_admin.css">
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <img src="../assets/images/login-bg.jpg" alt="Background">
      <div class="overlay">
        <h1 class="text-primary">CDE NextInfra Setup</h1>
        <p>Create the first administrator account to manage the application.</p>
      </div>
    </div>
    <div class="login-right">
      <img class="logo" src="../assets/images/logo-login.png" alt="Logo">
      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" class="login-form">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required>
        <label for="first_name">First Name</label>
        <input id="first_name" name="first_name" type="text" required>
        <label for="last_name">Last Name</label>
        <input id="last_name" name="last_name" type="text" required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
        <button type="submit">Create Admin</button>
      </form>
    </div>
  </div>
  <div class="footer-link-wrapper">
    &copy; 2025 a product of <a href="https://nextinfra.vn" class="footer-link">NextInfra</a>
  </div>
</body>
</html>