<?php
// pages/sidebar.php
// UTF-8 no BOM
// Assumes session is started and user is authenticated
?>
<div class="sidebar">
  <div class="logo">CDE Bimtech</div>
  <nav>
    <ul>
      <li class="active"><a href="home.php">Home</a></li>
      <li><a href="#">Projects</a></li>
      <li><a href="#">Common Data</a></li>
      <li><a href="#">Inventory Asset</a></li>
    </ul>
  </nav>
  <div class="user-info">
    <span><?= htmlspecialchars($_SESSION['user']['first_name']) ?> <?= htmlspecialchars($_SESSION['user']['last_name']) ?></span>
    <a href="home.php?logout=1">Sign out</a>
  </div>
</div>