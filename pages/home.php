<?php
// pages/home.php
session_start();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Redirect to login if not authenticated
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home | CDE Bimtech</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main">
    <header>
      <h1>Dashboard</h1>
      <p>Welcome, <?= htmlspecialchars($_SESSION['user']['first_name']) ?></p>
    </header>
    <main>
      <!-- Dashboard content here -->
    </main>
  </div>
</body>
</html>