<?php
// pages/create_admin.php
// UTF-8 no BOM
session_start();

// Redirect if not installed
if (!file_exists(__DIR__ . '/../config.php')) {
    header('Location: install.php'); exit;
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username']);
    $firstName  = trim($_POST['first_name']);
    $lastName   = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert admin user
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?, "admin")'
    );
    if ($stmt->execute([$username, $firstName, $lastName, $email, $passwordHash])) {
        header('Location: login.php'); exit;
    } else {
        $error = 'Failed to create admin user.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Admin | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/create_admin.css?v=<?php echo filemtime(__DIR__.'/../assets/css/create_admin.css'); ?>">
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <img src="../assets/images/login-bg.jpg" alt="Background">
      <div class="overlay">
        <h1 class="text-primary">CDE Bimtech Setup</h1>
        <p>Create the first administrator account to manage the application.</p>
      </div>
    </div>
    <div class="login-right">
      <img class="logo" src="../assets/images/logo-login.png" alt="CDE Bimtech Logo">
      <h2>Create Admin User</h2>
      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" class="login-form">
        <label class="required" for="username">Username</label>
        <input id="username" name="username" type="text" placeholder="Enter username" required>

        <label class="required" for="first_name">First Name</label>
        <input id="first_name" name="first_name" type="text" placeholder="Enter first name" required>

        <label class="required" for="last_name">Last Name</label>
        <input id="last_name" name="last_name" type="text" placeholder="Enter last name" required>

        <label class="required" for="email">Email</label>
        <input id="email" name="email" type="email" placeholder="Enter email" required>

        <label class="required" for="password">Password</label>
        <input id="password" name="password" type="password" placeholder="Enter password" required>

        <button type="submit">Create Admin</button>
      </form>
    </div>
  </div>
  <div class="footer-link-wrapper">
    &copy; 2025 a product of <a href="https://bimtech.edu.vn" class="footer-link">Bimtech</a>
  </div>
</body>
</html>
```php
<?php
// pages/create_admin.php
// UTF-8 no BOM
session_start();

// Redirect if not installed
if (!file_exists(__DIR__ . '/../config.php')) {
    header('Location: install.php'); exit;
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username']);
    $firstName  = trim($_POST['first_name']);
    $lastName   = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert admin user
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?, "admin")'
    );
    if ($stmt->execute([$username, $firstName, $lastName, $email, $passwordHash])) {
        header('Location: login.php'); exit;
    } else {
        $error = 'Failed to create admin user.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Admin | CDE Bimtech</title>
  <link rel="stylesheet" href="assets/css/login.css?v=<?php echo filemtime(__DIR__.'/assets/css/login.css'); ?>">
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <img src="assets/images/login-bg.jpg" alt="Background">
      <div class="overlay">
        <h1 class="text-primary">CDE Bimtech Setup</n1>
        <p>Create the first administrator account to manage the application.</p>
      </div>
    </div>
    <div class="login-right">
      <img class="logo" src="assets/images/logo-login.png" alt="CDE Bimtech Logo">
      <h2>Create Admin User</h2>
      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" class="login-form">
        <label class="required" for="username">Username</label>
        <input id="username" name="username" type="text" placeholder="Enter username" required>

        <label class="required" for="first_name">First Name</label>
        <input id="first_name" name="first_name" type="text" placeholder="Enter first name" required>

        <label class="required" for="last_name">Last Name</label>
        <input id="last_name" name="last_name" type="text" placeholder="Enter last name" required>

        <label class="required" for="email">Email</label>
        <input id="email" name="email" type="email" placeholder="Enter email" required>

        <label class="required" for="password">Password</label>
        <input id="password" name="password" type="password" placeholder="Enter password" required>

        <button type="submit">Create Admin</button>
      </form>
    </div>
  </div>
  <div class="footer-link-wrapper">
    &copy; 2025 a product of <a href="https://bimtech.edu.vn" class="footer-link">Bimtech</a>
  </div>
</body>
</html>