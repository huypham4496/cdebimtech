<?php
// pages/admin/index.php
session_start();
// Kiểm tra quyền admin
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Lấy danh sách người dùng
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $users = $pdo->query("SELECT id, username, first_name, last_name, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('DB Error: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AdminCP | User Management</title>
  <link rel="stylesheet" href="../../assets/css/dashboard.css?v=<?php echo filemtime(__DIR__.'/../../assets/css/dashboard.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?php echo filemtime(__DIR__.'/../../assets/css/sidebar_admin.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>
  <div class="main">
    <header>
      <h1>User Management</h1>
    </header>
    <section class="content">
      <table class="admin-table">
        <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?php echo htmlspecialchars($u['id']); ?></td>
            <td><?php echo htmlspecialchars($u['username']); ?></td>
            <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
            <td><?php echo htmlspecialchars($u['email']); ?></td>
            <td><?php echo htmlspecialchars($u['role']); ?></td>
            <td><a href="edit_user.php?id=<?php echo $u['id']; ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p><a href="create_user.php" class="btn">Create New User</a></p>
    </section>
  </div>
</body>
</html>