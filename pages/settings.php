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
        'SELECT username, first_name, last_name, email, avatar, dob, address, company, phone, password_hash
         FROM users WHERE id = ?'
    );
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    die('DB Error: ' . htmlspecialchars($e->getMessage()));
}

// Messages
$successProfile = '';
$successPassword = '';
$errorPassword   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Profile (including avatar)
    if (isset($_POST['update_profile'])) {
        $first   = trim($_POST['first_name']);
        $last    = trim($_POST['last_name']);
        $dob     = $_POST['dob'] ?: null;
        $address = trim($_POST['address']);
        $company = trim($_POST['company']);
        $phone   = trim($_POST['phone']);

        // Handle avatar upload
        if (!empty($_FILES['avatar']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../uploads/avatar';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext      = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $_SESSION['user']['id'] . '.' . $ext;
            $dest     = $uploadDir . '/' . $filename;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                $avatarFilename = $filename;
            }
        }

        // Build SQL to update user
        $sql = 'UPDATE users SET first_name = :fn, last_name = :ln, dob = :dob,
                address = :addr, company = :comp, phone = :phone'
             . (isset($avatarFilename) ? ', avatar = :av' : '')
             . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $params = [
            ':fn'    => $first,
            ':ln'    => $last,
            ':dob'   => $dob,
            ':addr'  => $address,
            ':comp'  => $company,
            ':phone' => $phone,
            ':id'    => $_SESSION['user']['id'],
        ];
        if (isset($avatarFilename)) {
            $params[':av'] = $avatarFilename;
        }
        $stmt->execute($params);
        $successProfile = 'Your settings have been saved.';

        // Refresh local and session values
        $user['first_name'] = $first;
        $user['last_name']  = $last;
        $user['dob']        = $dob;
        $user['address']    = $address;
        $user['company']    = $company;
        $user['phone']      = $phone;
        if (isset($avatarFilename)) {
            $user['avatar'] = $avatarFilename;
            $_SESSION['user']['avatar'] = $avatarFilename;
        }
    }

    // Change Password
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (password_verify($current, $user['password_hash'])) {
            if ($new === $confirm) {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE users SET password_hash = :ph WHERE id = :id');
                $upd->execute([
                    ':ph' => $newHash,
                    ':id' => $_SESSION['user']['id'],
                ]);
                $successPassword = 'Password changed successfully.';
            } else {
                $errorPassword = 'New password and confirmation do not match.';
            }
        } else {
            $errorPassword = 'Current password is incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/settings.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/settings.css'); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/sidebar.css'); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main">
    <header><h1>Settings</h1></header>

    <?php if ($successProfile): ?><div class="alert success"><?= htmlspecialchars($successProfile) ?></div><?php endif; ?>
    <?php if ($successPassword): ?><div class="alert success"><?= htmlspecialchars($successPassword) ?></div><?php endif; ?>
    <?php if ($errorPassword):   ?><div class="alert error"><?= htmlspecialchars($errorPassword) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="settings-form">
      <!-- Account Details -->
      <div class="section">
        <h2>Account Details</h2>
        <div class="form-row">
          <div class="form-group"><label>Username</label>
            <input type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly class="readonly"></div>
          <div class="form-group"><label>Email</label>
            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly class="readonly"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>First Name</label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>"></div>
          <div class="form-group"><label>Last Name</label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>DOB</label>
            <input type="date" name="dob" value="<?= htmlspecialchars($user['dob']) ?>"></div>
          <div class="form-group"><label>Phone</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>"></div>
        </div>
        <div class="form-row"><div class="form-group full"><label>Address</label>
            <input type="text" name="address" value="<?= htmlspecialchars($user['address']) ?>"></div></div>
        <div class="form-row"><div class="form-group full"><label>Company</label>
            <input type="text" name="company" value="<?= htmlspecialchars($user['company']) ?>"></div></div>
        <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
      </div>

      <!-- Avatar Section -->
      <div class="section">
        <h2>Avatar</h2>
        <div class="avatar-group">
          <?php if (!empty($user['avatar'])): ?>
            <img src="../uploads/avatar/<?= htmlspecialchars($user['avatar']) ?>" class="avatar-preview" alt="Avatar">
          <?php endif; ?>
          <input type="file" name="avatar" accept="image/*">
        </div>
        <button type="submit" name="update_profile" class="btn-apply">Apply</button>
      </div>

      <!-- Change Password Section -->
      <div class="section">
        <h2>Change Password</h2>
        <div class="form-row"><div class="form-group full"><label>Current Password</label>
            <input type="password" name="current_password"></div></div>
        <div class="form-row"><div class="form-group full"><label>New Password</label>
            <input type="password" name="new_password"></div></div>
        <div class="form-row"><div class="form-group full"><label>Confirm Password</label>
            <input type="password" name="confirm_password"></div></div>
        <button type="submit" name="change_password" class="btn-change">Change Password</button>
      </div>
    </form>
  </div>
</body>
</html>