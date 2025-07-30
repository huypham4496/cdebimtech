<?php
// pages/admin/index.php
session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Tạo mới user form inline

// Lấy danh sách người dùng
try {
    \$pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    \$users = \$pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException \$e) {
    die('DB Error: ' . htmlspecialchars(\$e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminCP | User Management</title>
  <link rel="stylesheet" href="../../assets/css/admincp.css?v=<?php echo filemtime(__DIR__.'/../../assets/css/admincp.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?php echo filemtime(__DIR__.'/../../assets/css/sidebar_admin.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>

  <div class="main">
    <header><h1>User Management</h1></header>

    <!-- Create New User Inline -->
    <div class="create-user-section">
      <form method="post" action="create_user.php" class="create-user-form">
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

    <!-- User Table -->
    <table class="admin-table">
      <thead>
        <tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach (\$users as \$u): ?>
        <tr>
          <td><?=htmlspecialchars(\$u['id'])?></td>
          <td><?=htmlspecialchars(\$u['username'])?></td>
          <td><?=htmlspecialchars(\$u['first_name'].' '.\$u['last_name'])?></td>
          <td><?=htmlspecialchars(\$u['email'])?></td>
          <td><?=htmlspecialchars(\$u['role'])?></td>
          <td><a href="edit_user.php?id=<?=\$u['id']?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>