<?php
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
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pwd = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
        $sql = 'UPDATE users SET username=?, first_name=?, last_name=?, email=?, role=?, dob=?, address=?, company=?, phone=?';
        $params = [
          $_POST['username'], $_POST['first_name'], $_POST['last_name'],
          $_POST['email'], $_POST['role'], $_POST['dob'], $_POST['address'],
          $_POST['company'], $_POST['phone']
        ];
        if ($pwd) {
            $sql .= ', password_hash=?';
            $params[] = $pwd;
        }
        $sql .= ' WHERE id=?';
        $params[] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        header('Location: index.php'); exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('DB Error: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit User</title>
  <link rel="stylesheet" href="../../assets/css/admincp.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/admincp.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/sidebar_admin.css?v=<?php echo filemtime(__DIR__ . '/../../assets/css/sidebar_admin.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>
  <div class="main">
    <header><h1>Edit User</h1></header>
    <form method="post" class="create-user-form">
      <div class="form-group"><label>Username</label><input name="username" value="<?= htmlspecialchars($user['username']); ?>" required></div>
      <div class="form-group"><label>Password (leave blank to keep)</label><input type="password" name="password"></div>
      <div class="form-group"><label>First Name</label><input name="first_name" value="<?= htmlspecialchars($user['first_name']); ?>" required></div>
      <div class="form-group"><label>Last Name</label><input name="last_name" value="<?= htmlspecialchars($user['last_name']); ?>" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required></div>
      <div class="form-group"><label>Role</label><select name="role"><option value="user"<?= $user['role']==='user'?' selected':''; ?>>User</option><option value="admin"<?= $user['role']==='admin'?' selected':''; ?>>Admin</option></select></div>
      <div class="form-group"><label>Date of Birth</label><input type="date" name="dob" value="<?= htmlspecialchars($user['dob']); ?>"></div>
      <div class="form-group"><label>Address</label><input name="address" value="<?= htmlspecialchars($user['address']); ?>"></div>
      <div class="form-group"><label>Company</label><input name="company" value="<?= htmlspecialchars($user['company']); ?>"></div>
      <div class="form-group"><label>Phone</label><input name="phone" value="<?= htmlspecialchars($user['phone']); ?>"></div>
      <button type="submit">Save Changes</button>
    </form>
  </div>
</body>
</html>