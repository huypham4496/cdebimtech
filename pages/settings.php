<?php
// pages/settings.php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: pages/login.php');
    exit;
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Fetch current user data
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->prepare(
        'SELECT username, first_name, last_name, email, avatar, dob, address, company, phone FROM users WHERE id = ?'
    );
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('DB Error: ' . htmlspecialchars($e->getMessage()));
}

// Handle form submission
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $first = trim($_POST['first_name']);
    $last = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $dob = $_POST['dob'];
    $address = trim($_POST['address']);
    $company = trim($_POST['company']);
    $phone = trim($_POST['phone']);

    // Avatar upload
    if (!empty($_FILES['avatar']['tmp_name'])) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $avatarFile = 'avatar_' . $_SESSION['user']['id'] . '.' . $ext;
        move_uploaded_file(
            $_FILES['avatar']['tmp_name'],
            __DIR__ . '/../assets/uploads/' . $avatarFile
        );
    } else {
        $avatarFile = $user['avatar'];
    }

    // Update DB
    $upd = $pdo->prepare(
        'UPDATE users SET username=?, first_name=?, last_name=?, email=?, avatar=?, dob=?, address=?, company=?, phone=? WHERE id=?'
    );
    $upd->execute([
        $username, $first, $last, $email, $avatarFile,
        $dob, $address, $company, $phone,
        $_SESSION['user']['id']
    ]);

    // Update session & local variable
    $_SESSION['user']['name'] = $first;
    $_SESSION['user']['avatar'] = $avatarFile;
    $user = array_merge($user, [
        'username' => $username,
        'first_name' => $first,
        'last_name'  => $last,
        'email'      => $email,
        'avatar'     => $avatarFile,
        'dob'        => $dob,
        'address'    => $address,
        'company'    => $company,
        'phone'      => $phone
    ]);
    $success = 'Your settings have been saved.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/settings.css?v=<?php echo filemtime(__DIR__.'/../assets/css/settings.css'); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime(__DIR__.'/../assets/css/sidebar.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main">
    <header><h1>Settings</h1></header>

    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="settings-form">
      <!-- Account Details -->
      <div class="section">
        <h2>Account Details</h2>
        <div class="form-row">
          <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" readonly class="readonly">
          </div>
        </div>
      </div>

      <!-- Personal Information -->
      <div class="section">
        <h2>Personal Information</h2>
        <div class="form-row">
          <div class="form-group"><label>First Name</label><input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required></div>
          <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Date of Birth</label><input type="date" name="dob" value="<?= htmlspecialchars($user['dob']) ?>"></div>
          <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group full"><label>Address</label><input type="text" name="address" value="<?= htmlspecialchars($user['address']) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group full"><label>Company</label><input type="text" name="company" value="<?= htmlspecialchars($user['company']) ?>"></div>
        </div>
      </form>

      <!-- Avatar Section -->
      <div class="section">
        <h2>Avatar</h2>
        <div class="avatar-group">
          <img src="../assets/uploads/<?= htmlspecialchars($user['avatar']) ?>" class="avatar-preview" alt="Avatar">
          <input type="file" name="avatar" accept="image/*">
        </div>
      </div>

      <button type="submit" class="btn-save">Save Changes</button>
    </form>
  </div>
</body>
</html>