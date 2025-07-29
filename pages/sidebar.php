<?php
// pages/sidebar.php
session_start();
?>
<div class="sidebar">
  <!-- Header -->
  <div class="sidebar-header">
    <img src="../assets/images/logo-login.png" alt="ADSCivil CDE Logo" class="sidebar-logo">
    <div class="sidebar-title">
      <h2>ADSCivil CDE</h2>
      <p>Your Infrastructure BIM software partner</p>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <ul>
      <li class="active"><a href="home.php"><i class="fas fa-home"></i> Home</a></li>
      <li><a href="projects.php"><i class="fas fa-project-diagram"></i> Projects</a></li>
      <li><a href="common-data.php"><i class="fas fa-database"></i> Common data</a></li>
      <li><a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory asset</a></li>
    </ul>
  </nav>

  <!-- Footer: User Info & Actions -->
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?php echo htmlspecialchars(substr(
        $_SESSION['user']['first_name'], 0, 1)
      )); ?></div>
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
      <a href="#"><i class="fas fa-cog"></i> Settings</a>
      <a href="home.php?logout=1" class="sign-out"><i class="fas fa-sign-out-alt"></i> Sign out</a>
    </div>
  </div>
</div>