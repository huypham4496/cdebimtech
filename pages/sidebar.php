<?php
// pages/sidebar.php
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
      <li class="active"><a href="home.php"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="projects.php"><i class="fas fa-project-diagram"></i> Projects</a></li>
      <li><a href="members.php"><i class="fas fa-users"></i> Members</a></li>
      <li><a href="meetings.php"><i class="fas fa-file-alt"></i> Meetings</a></li>
      <li><a href="diary.php"><i class="fas fa-book"></i> Work Diary</a></li>
      <li><a href="history.php"><i class="fas fa-history"></i> Activity History</a></li>
      <li><a href="rules.php"><i class="fas fa-gavel"></i> Rule</a></li>
      <li><a href="org_members.php"><i class="fas fa-building"></i> Organization Members</a></li>
    </ul>
  </nav>

  <!-- Footer: User Info & Actions -->
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar">
        <?php if (!empty($_SESSION['user']['avatar'])): ?>
          <img src="../uploads/avatar/<?= htmlspecialchars($_SESSION['user']['avatar']) ?>" alt="User Avatar" class="avatar-preview">
        <?php else: ?>
          <div class="avatar-placeholder"><?= htmlspecialchars(substr($_SESSION['user']['first_name'], 0, 1)) ?></div>
        <?php endif; ?>
      </div>
      <div class="user-details">
        <div class="user-name"><?= htmlspecialchars($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']) ?></div>
        <div class="user-email"><?= htmlspecialchars($_SESSION['user']['email']) ?></div>
      </div>
    </div>
    <div class="sidebar-actions">
      <a href="#"><i class="fas fa-bell"></i> Notification</a>
      <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
      <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'): ?>
        <a href="admin/index.php"><i class="fas fa-user-shield"></i> AdminCP</a>
      <?php endif; ?>
      <a href="home.php?logout=1" class="sign-out"><i class="fas fa-sign-out-alt"></i> Sign out</a>
      <div class="version">Version: 0.0.1</div>
    </div>
  </div>
</div>
