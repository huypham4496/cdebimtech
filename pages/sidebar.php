<?php
// pages/sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current = basename($_SERVER['SCRIPT_NAME']);
$user    = $_SESSION['user'] ?? [];
$avatar  = $user['avatar'] ?? '';
?>
<div class="sidebar">
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
      <li class="<?= $current==='home.php' ? 'active' : '' ?>"><a href="home.php"><i class="fas fa-home"></i> Home</a></li>
      <li class="<?= $current==='projects.php' ? 'active' : '' ?>"><a href="projects.php"><i class="fas fa-project-diagram"></i> Projects</a></li>
      <li class="<?= $current==='members.php' ? 'active' : '' ?>"><a href="members.php"><i class="fas fa-users"></i> Members</a></li>
      <li class="<?= $current==='meetings.php' ? 'active' : '' ?>"><a href="meetings.php"><i class="fas fa-file-alt"></i> Meetings</a></li>
      <li class="<?= $current==='work_diary.php' ? 'active' : '' ?>"><a href="work_diary.php"><i class="fas fa-book"></i> Work Diary</a></li>
      <li class="<?= $current==='activity_history.php' ? 'active' : '' ?>"><a href="activity_history.php"><i class="fas fa-history"></i> Activity History</a></li>
      <li class="<?= $current==='rule.php' ? 'active' : '' ?>"><a href="rule.php"><i class="fas fa-gavel"></i> Rule</a></li>
      <li class="<?= $current==='organization_members.php' ? 'active' : '' ?>"><a href="organization_members.php"><i class="fas fa-building"></i> Organization Members</a></li>
      <li class="<?= $current==='subscriptions.php' ? 'active' : '' ?>"><a href="subscriptions.php"><i class="fas fa-file-contract"></i> Subscriptions</a></li>
    </ul>
  </nav>

  <!-- Footer: User Info & Actions -->
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar">
        <?php if ($avatar && file_exists(__DIR__ . '/../uploads/avatar/' . $avatar)): ?>
          <img src="../uploads/avatar/<?= htmlspecialchars($avatar) ?>" alt="Avatar">
        <?php else: ?>
          <span><?= htmlspecialchars(substr($user['first_name'] ?? 'U', 0, 1)) ?></span>
        <?php endif; ?>
      </div>
      <div class="user-details">
        <div class="user-name"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></div>
        <div class="user-email"><?= htmlspecialchars($user['email'] ?? '') ?></div>
      </div>
    </div>
    <div class="sidebar-actions">
      <a href="#"><i class="fas fa-bell"></i> Notification</a>
      <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
      <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
        <a href="admin/index.php"><i class="fas fa-user-shield"></i> AdminCP</a>
      <?php endif; ?>
      <a href="home.php?logout=1" class="sign-out"><i class="fas fa-sign-out-alt"></i> Sign out</a>
      <div class="version">Version: 0.0.1</div>
    </div>
  </div>
</div>
