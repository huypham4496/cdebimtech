<?php
// pages/install.php
// UTF-8 no BOM
// Prevent reinstall if already configured and tables exist
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
    // connect to database
    try {
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // check if any tables exist
        $stmt = $pdo->query("SHOW TABLES");
        if ($stmt->rowCount() > 0) {
            exit('Application is already installed.');
        }
    } catch (PDOException $e) {
        // continue to installer if cannot connect
    }
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host']);
    $db_name = trim($_POST['db_name']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    // Build config content
    $cfg = "<?php
";
    $cfg .= "define('DB_HOST','".addslashes($db_host)."');
";
    $cfg .= "define('DB_NAME','".addslashes($db_name)."');
";
    $cfg .= "define('DB_USER','".addslashes($db_user)."');
";
    $cfg .= "define('DB_PASS','".addslashes($db_pass)."');
";
    $cfg .= "
// charset and options
";
    if (@file_put_contents(__DIR__.'/../config.php', $cfg) !== false) {
        header('Location: create_admin.php'); exit;
    } else {
        $error = 'Cannot write to config.php. Check permissions.';
    }
}
```php
<?php
// pages/install.php
// UTF-8 no BOM
if (file_exists(__DIR__ . '/../config.php')) {
    header('Location: ../index.php'); exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host']);
    $db_name = trim($_POST['db_name']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    // Create config content
    $cfg = "<?php\ndefine('DB_HOST','".addslashes($db_host)."');\n";
    $cfg .= "define('DB_NAME','".addslashes($db_name)."');\n";
    $cfg .= "define('DB_USER','".addslashes($db_user)."');\n";
    $cfg .= "define('DB_PASS','".addslashes($db_pass)."');\n";
    if (@file_put_contents(__DIR__.'/../config.php', $cfg)) {
        header('Location: create_admin.php'); exit;
    } else {
        $error = 'Cannot write to config.php. Check permissions.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Install | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/install.css?v=<?php echo filemtime(__DIR__.'/../assets/css/install.css'); ?>">
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <img src="../assets/images/login-bg.jpg" alt="Background">
      <div class="overlay">
        <h1 class="text-primary">Installer</h1>
        <p>Enter database connection details.</p>
      </div>
    </div>
    <div class="login-right">
      <h2>DB Configuration</h2>
      <?php if($error): ?><div class="error-msg"><?=htmlspecialchars($error)?></div><?php endif;?>
      <form method="post" class="login-form">
        <label for="db_host">Host</label>
        <input id="db_host" name="db_host" type="text" required>
        <label for="db_name">DB Name</label>
        <input id="db_name" name="db_name" type="text" required>
        <label for="db_user">User</label>
        <input id="db_user" name="db_user" type="text" required>
        <label for="db_pass">Password</label>
        <input id="db_pass" name="db_pass" type="password">
        <button type="submit">Save</button>
      </form>
    </div>
  </div>
  <div class="footer-link-wrapper">
    &copy; 2025 a product of <a href="https://bimtech.edu.vn" class="footer-link">Bimtech</a>
  </div>
</body>
</html>