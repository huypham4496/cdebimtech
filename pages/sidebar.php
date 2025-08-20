<?php
// pages/sidebar.php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // thử tự nạp DB nếu cần
    $dbBoot = __DIR__ . '/../includes/db.php';
    if (is_readable($dbBoot)) {
        require_once $dbBoot;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Không có DB -> hiển thị sidebar rút gọn, không query
    echo '<aside class="sidebar">
            <div class="sidebar-section">Menu</div>
            <!-- DB unavailable: showing minimal sidebar -->
          </aside>';
    return; // đừng chạy các query bên dưới nữa
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current = basename($_SERVER['SCRIPT_NAME']);
$user    = $_SESSION['user'] ?? [];
$avatar  = $user['avatar'] ?? '';
// Đường dẫn avatar upload
$uploadPath = __DIR__ . '/../uploads/avatar/' . $avatar;
// Đường dẫn default avatar
$defaultAvatarUrl = '../assets/images/default-avatar.jpg';

// Xác định URL avatar
if (!empty($avatar) && file_exists($uploadPath)) {
    $avatarUrl = '../uploads/avatar/' . htmlspecialchars($avatar);
} else {
    $avatarUrl = $defaultAvatarUrl;
}

// Đếm thông báo chưa đọc
$userId = $user['id'] ?? null;
$unreadCount = 0;
if ($userId) {
    $nq = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE receiver_id = ? AND is_read = 0");
    $nq->execute([$userId]);
    $unreadCount = (int)$nq->fetchColumn();
}
?>
<div id="cde-sidebar" class="sidebar">
  <!-- Header -->
   
  <div class="sidebar-header">
    <a href="home.php" class="sidebar-logo-link">
      <img src="../assets/images/logo-login.png" alt="CDE Bimtech Logo" class="sidebar-logo">
      <div class="sidebar-title">
        <h2>CDE Bimtech</h2>
        <p>Transform Your Workflow</p>
      </div>
    </a>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <ul>
      <li class="<?= $current=== 'home.php' ? 'active' : '' ?>">
        <a href="home.php"><i class="fas fa-home"></i> Home</a>
      </li>
      <li class="<?= $isProjectsCtx ? 'active' : '' ?>">
        <a href="projects.php"><i class="fas fa-project-diagram"></i> Projects</a>
      </li>
      <li class="<?= $current=== 'work_diary.php' ? 'active' : '' ?>">
        <a href="work_diary.php"><i class="fas fa-book"></i> Work Diary</a>
      </li>
      <li class="<?= $current=== 'subscriptions.php' ? 'active' : '' ?>">
        <a href="subscriptions.php"><i class="fas fa-file-contract"></i> Subscriptions</a>
      </li>
      <li class="<?= $current=== 'organization_manage.php' ? 'active' : '' ?>">
        <a href="organization_manage.php"><i class="fas fa-file-contract"></i> Organization</a>
      </li>
      <li class="<?= $current=== 'organization_members.php' ? 'active' : '' ?>">
        <a href="organization_members.php"><i class="fas fa-users"></i> Organization Members</a>
      </li>
    </ul>
  </nav>

  <!-- Footer: User Info & Actions -->
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar">
        <img src="<?= $avatarUrl ?>" alt="User Avatar">
      </div>
      <div class="user-details">
        <div class="user-name">
          <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
        </div>
        <div class="user-email">
          <?= htmlspecialchars($user['email'] ?? '') ?>
        </div>
      </div>
    </div>
    <div class="sidebar-actions">
      <a href="notifications.php"><i class="fas fa-bell"></i> Notifications
        <?php if ($unreadCount > 0): ?>
          <span class="notification-badge blink"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
      <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
      <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
        <a href="admin/index.php"><i class="fas fa-user-shield"></i> AdminCP</a>
      <?php endif; ?>
      <a href="home.php?logout=1" class="sign-out"><i class="fas fa-sign-out-alt"></i> Sign out</a>
      <div class="version">Version: 0.0.1</div>
    </div>
  </div>
</div>
