<?php
// pages/admin/edit_user.php
session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php'); exit;
}
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare("UPDATE users SET username=?, first_name=?, last_name=?, email=?, role=? WHERE id=?");
        $stmt->execute([
            $_POST['username'], $_POST['first_name'], $_POST['last_name'],
            $_POST['email'], $_POST['role'], $id
        ]);
        header('Location: index.php'); exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: header('Location: index.php');
} catch (PDOException $e) {
    die('DB Error');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit User</title>
  <link rel="stylesheet" href="../../assets/css/admincp.css?v=<?php echo filemtime(__DIR__.'/../../assets/css/admincp.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?php echo filemtime(__DIR__.'/../../assets/css/sidebar_admin.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>
  <div class="main">
    <header><h1>Edit User</h1></header>
    <form method="post" class="admin-form">
      <label>Username</label>
      <input name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
      <label>First Name</label>
      <input name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
      <label>Last Name</label>
      <input name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
      <label>Email</label>
      <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
      <label>Role</label>
      <select name="role">
        <option value="user"<?php if($user['role']==='user') echo ' selected';?>>User</option>
        <option value="admin"<?php if($user['role']==='admin') echo ' selected';?>>Admin</option>
      </select>
      <button type="submit">Save Changes</button>
    </form>
  </div>
</body>
</html>