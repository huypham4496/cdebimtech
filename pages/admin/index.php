<?php
session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Lấy danh sách user và xử lý xóa
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    // Xóa user nếu có yêu cầu
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $delId = (int) $_POST['delete_id'];
        $delStmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $delStmt->execute([':id' => $delId]);
        // Tránh resubmit form
        header('Location: index.php');
        exit;
    }
    // Lấy danh sách users
    $users = $pdo->query(
        'SELECT id, username, first_name, last_name, email, role, dob, address, company, phone FROM users'
    )->fetchAll(PDO::FETCH_ASSOC);
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
  <link rel="stylesheet" href="../../assets/css/admincp.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/admincp.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/sidebar_admin.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>

  <div class="main">
    <header><h1>User Management</h1></header>
  <div class="excel-actions" style="margin:1.5rem 0;">
    <!-- Export -->
    <a href="import_export.php?action=export" class="btn btn-primary">
      <i class="fas fa-file-export"></i> Export Users
    </a>
    <!-- Import: mở modal hoặc form upload -->
    <form method="post" action="import_export.php?action=import" enctype="multipart/form-data" style="display:inline-block; margin-left:1rem;">
      <label class="btn btn-secondary">
        <i class="fas fa-file-import"></i> Import Users
        <input type="file" name="excel_file" accept=".xlsx,.xls" hidden required>
      </label>
      <button type="submit" class="btn btn-secondary">Upload</button>
    </form>
  </div>
    <!-- Create New User Inline -->
    <div class="create-user-section">
      <form method="post" action="create_user.php" class="create-user-form">
        <div class="form-group"><label>Username</label><input name="username" required></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
        <div class="form-group"><label>First Name</label><input name="first_name" required></div>
        <div class="form-group"><label>Last Name</label><input name="last_name" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
        <div class="form-group"><label>Role</label><select name="role"><option value="user">User</option><option value="admin">Admin</option></select></div>
        <div class="form-group"><label>Date of Birth</label><input type="date" name="dob"></div>
        <div class="form-group"><label>Address</label><input name="address"></div>
        <div class="form-group"><label>Company</label><input name="company"></div>
        <div class="form-group"><label>Phone</label><input name="phone"></div>
        <button type="submit">Create User</button>
      </form>
    </div>

    <!-- User Table -->
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['id']) ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['role']) ?></td>
          <td>
            <a class="btn-edit" href="edit_user.php?id=<?= $u['id'] ?>">
              <i class="fas fa-edit"></i> Edit
            </a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa user này?');">
              <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn-delete">
                <i class="fas fa-trash-alt"></i> Delete
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
