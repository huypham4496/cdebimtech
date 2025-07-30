<?php
// pages/admin/create_user.php
session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwdHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, first_name, last_name, email, password_hash, role, dob, address, company, phone, invite_code)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_POST['username'], $_POST['first_name'], $_POST['last_name'],
            $_POST['email'], $pwdHash, $_POST['role'],
            $_POST['dob'], $_POST['address'], $_POST['company'],
            $_POST['phone'], $_POST['invite_code']
        ]);
        header('Location: index.php'); exit;
    } catch (PDOException $e) {
        die('DB Error: ' . htmlspecialchars($e->getMessage()));
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create User</title>
  <link rel="stylesheet" href="../../assets/css/admincp.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/admincp.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/sidebar_admin.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>
  <div class="main">
    <header><h1>Create New User</h1></header>
    <form method="post" class="create-user-form">
      <div class="form-group"><label>Username</label><input name="username" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
      <div class="form-group"><label>First Name</label><input name="first_name" required></div>
      <div class="form-group"><label>Last Name</label><input name="last_name" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
      <div class="form-group"><label>Role</label><select name="role"><option value="user">User</option><option value="admin">Admin</option></select></div>
      <div class="form-group"><label>DOB</label><input type="date" name="dob"></div>
      <div class="form-group"><label>Address</label><input name="address"></div>
      <div class="form-group"><label>Company</label><input name="company"></div>
      <div class="form-group"><label>Phone</label><input name="phone"></div>
      <div class="form-group"><label>Invite Code</label><input name="invite_code"></div>
      <button type="submit">Create User</button>
    </form>
  </div>
</body>
</html>