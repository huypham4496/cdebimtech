<?php
// includes/sidebar.php

/** Render sidebar navigation */
function renderSidebar() {
    $user = $_SESSION['user'];
    return <<<HTML
<aside class="sidebar">
  <div class="brand">
    <img src="assets/images/logo.png" alt="CDE Logo">
    <span>Nova CDE</span>
  </div>
  <nav class="nav-menu">
    <ul>
      <li class="active"><a href="index.php">Overview</a></li>
      <li><a href="#">Project</a></li>
      <li><a href="#">Notification</a></li>
      <li><a href="#">Member</a></li>
      <li><a href="#">Meetings</a></li>
      <li><a href="#">Work diary</a></li>
      <li><a href="#">Activity History</a></li>
      <li><a href="#">Settings</a></li>
    </ul>
  </nav>
  <div class="user-info">
    <img src="{$user['avatar']}" alt="Avatar">
    <span>".htmlspecialchars($user['name'])."</span>
    <a href="pages/login.php?logout=1">Sign out</a>
  </div>
</aside>
HTML;
}