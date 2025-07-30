<?php
// pages/admin/sidebar_admin.php
// Session đã được start ở file cha
?>
<div class="sidebar-admin">
  <!-- Header -->
  <div class="sidebar-header">
    <a href="../home.php" class="sidebar-logo-link">
      <img src="../../assets/images/logo-login.png" alt="CDE Bimtech Logo" class="sidebar-logo">
      <div class="sidebar-title">
        <h2>AdminCP</h2>
        <p>Control Panel</p>
      </div>
    </a>
  </div>
  <!-- Navigation -->
  <nav class="sidebar-nav-admin">
    <ul>
      <li class="active"><a href="index.php"><i class="fas fa-users-cog"></i> User Management</a></li>
      <li><a href="subscriptions_info.php"><i class="fas fa-info-circle"></i> Subscriptions Info</a></li>
      <li><a href="subscriptions.php"><i class="fas fa-file-contract"></i> Subscriptions</a></li>
      <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
      <li><a href="payment_requests.php"><i class="fas fa-hand-holding-usd"></i> Payment Requests</a></li>
    </ul>
  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <a href="../home.php" class="sign-out"><i class="fas fa-home"></i> Back to Home</a>
    <div class="version">Version: 0.0.1</div>
  </div>
</div>
