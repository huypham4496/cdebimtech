<?php
// pages/admin/sidebar_admin.php
// Được include sau session_start() ở trang cha
?>
<div class="sidebar-admin">
  <!-- Logo header -->
  <div class="sidebar-header">
    <img src="../../assets/images/logo-login.png" alt="CDE Bimtech Logo" class="sidebar-logo">
    <div class="sidebar-title">
      <h2>AdminCP</h2>
      <p>Control Panel</p>
    </div>
  </div>

  <!-- Navigation tabs -->
  <nav class="sidebar-nav-admin">
    <ul>
      <li class="active"><a href="index.php"><i class="fas fa-users-cog"></i> User Management</a></li>
      <li><a href="subscriptions_info.php"><i class="fas fa-info-circle"></i> Subscriptions Info</a></li>
      <li><a href="subscriptions.php"><i class="fas fa-file-contract"></i> Subscriptions</a></li>
      <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
      <li><a href="payment_requests.php"><i class="fas fa-hand-holding-usd"></i> Payment Requests</a></li>
    </ul>
  </nav>

  <!-- Footer actions -->
  <div class="sidebar-footer">
    <a href="../../pages/login.php?logout=1" class="sign-out"><i class="fas fa-sign-out-alt"></i> Sign out</a>
    <div class="version">Version: 0.0.1</div>
  </div>
</div>