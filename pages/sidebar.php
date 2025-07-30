<?php
// pages/sidebar.php
?>
<div class="sidebar">
  <!-- Header -->
  <div class="sidebar-header">
    <img src="../assets/images/logo-login.png" alt="ADSCivil CDE Logo" class="sidebar-logo">
    <div class="sidebar-title">
      <h2>CDE Bimtech</h2>
      <p>Transform Your Workflow</p>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <ul>
      <li class="active"><a href="home.php"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="#"><i class="fas fa-project-diagram"></i> Projects</a></li>
      <li><a href="#"><i class="fas fa-users"></i> Members</a></li>
      <li><a href="#"><i class="fas fa-file-alt"></i> Meetings</a></li>
      <li><a href="#"><i class="fas fa-book"></i> Work Diary</a></li>
      <li><a href="#"><i class="fas fa-history"></i> Activity History</a></li>
      <li><a href="#"><i class="fas fa-gavel"></i> Rule</a></li>
      <li><a href="#"><i class="fas fa-building"></i> Organization Members</a></li>
    </ul>
  </nav>

  <!-- Footer: User Info & Actions -->
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?php echo htmlspecialchars(
        substr($_SESSION['user']['first_name'], 0, 1)
      ); ?></div>
      <div class="user-details">
        <div class="user-name"><?php echo htmlspecialchars(
          $_SESSION['user']['first_name'] . ' ' . 
          $_SESSION['user']['last_name']
        ); ?></div>
        <div class="user-email"><?php echo htmlspecialchars(
          $_SESSION['user']['email']
        ); ?></div>
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
  </div>
</div>