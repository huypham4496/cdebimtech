<?php
// pages/admin/sidebar_admin.php

// Include Font Awesome for icons
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-dynvDxJ5aVF6oU1i6zfoalvVYvNvKcJste/0q5u+P%2FgPm4jG3E5s3UeJ8V+RaH59RUW2YCiMzZ6pyRrg58F3CA==" crossorigin="anonymous" referrerpolicy="no-referrer" />';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine current page for active highlight
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>

<div class="sidebar-admin">
  <!-- Header -->
  <div class="sidebar-header">
    <a href="../home.php" class="sidebar-logo-link">
      <img src="../../assets/images/logo-login.png" alt="CDE NextInfra Logo" class="sidebar-logo">
      <div class="sidebar-title">
        <h2>AdminCP</h2>
        <p>Control Panel</p>
      </div>
    </a>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav-admin">
    <ul>
      <li class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"><a href="index.php"><i class="fas fa-users-cog"></i> User Management</a></li>
      <li class="<?= $currentPage === 'subscriptions_info.php' ? 'active' : '' ?>"><a href="subscriptions_info.php"><i class="fas fa-info-circle"></i> Subscriptions Info</a></li>
      <li class="<?= $currentPage === 'subscriptions.php' ? 'active' : '' ?>"><a href="subscriptions.php"><i class="fas fa-file-contract"></i> Subscriptions</a></li>
      <li class="<?= $currentPage === 'subscription_features.php' ? 'active' : '' ?>"><a href="subscription_features.php"><i class="fas fa-sliders-h"></i> Subscription Features</a></li>
      <li class="<?= $currentPage === 'payments.php' ? 'active' : '' ?>"><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
      <li class="<?= $currentPage === 'payment_requests.php' ? 'active' : '' ?>"><a href="payment_requests.php"><i class="fas fa-hand-holding-usd"></i> Payment Requests</a></li>
    <li class="<?= $currentPage === 'voucher.php' ? 'active' : '' ?>"><a href="voucher.php"><i class="fas fa-ticket-alt"></i> Vouchers</a></li>
    </ul>
  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <a href="../home.php" class="sign-out"><i class="fas fa-home"></i> Back to Home</a>
    <div class="version">Version: 0.0.1</div>
  </div>
</div>
