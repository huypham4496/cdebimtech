<?php
// pages/login.php
session_start();

// If already logged in, redirect
if (!empty($_SESSION['user'])) {
    header('Location: ../pages/home.php');
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login']);
    $password = $_POST['password'];
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // 1) Authenticate user
        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE username = ? OR email = ?'
        );
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            $_SESSION['user'] = $user;

            // 2) Check for expired subscription and reset if needed
            //    Find default plan (smallest id)
            $minPlan = $pdo->query('SELECT id FROM subscriptions ORDER BY id ASC LIMIT 1')
                           ->fetchColumn();

            //    Only if user has an expiry date and it's in the past
            if (!empty($user['subscription_expires_at']) 
                && $user['subscription_expires_at'] < date('Y-m-d')) 
            {
                $pdo->prepare(
                    'UPDATE users SET subscription_id = ?, subscription_expires_at = NULL WHERE id = ?'
                )->execute([ $minPlan, $user['id'] ]);

                // Update session value so UI reflects the change immediately
                $_SESSION['user']['subscription_id']        = $minPlan;
                $_SESSION['user']['subscription_expires_at'] = null;
            }

            header('Location: ../index.php');
            exit;
        } else {
            $error = 'Username/Email or password is incorrect.';
        }
    } catch (PDOException $e) {
        $error = 'Database connection failed.';
    }
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
        <p>Empower your workflow with real-time 3D visualization, full data ownership, and powerful BIM data analysis. Secure, immersive, and built for limitless collaboration.</p>
      </div>
    </div>
    <div class="login-right">
      <img class="logo" src="../assets/images/logo-login.png" alt="Logo">
      <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <form action="" method="post" class="login-form">
        <label for="login">Username or Email</label>
        <input id="login" name="login" type="text" required autofocus>
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
