<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: ../pages/login.php');
    exit;
}
$userName = htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['username']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Thank You!</title>
  <link rel="stylesheet" href="../assets/css/thank_you.css">
</head>
<body>
  <div class="thankyou-container">
    <div class="thankyou-card">
      <div class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" class="checkmark" viewBox="0 0 52 52">
          <circle cx="26" cy="26" r="25" fill="none"/>
          <path fill="none" d="M14 27 l10 10 l14 -14" />
        </svg>
      </div>
      <h1>Thank you, <?= $userName ?>!</h1>
      <p>Your subscription has been received and is being processed. You’ll receive a confirmation email shortly.</p>
      <a href="/index.php" class="btn-home">Go to Home</a>
    </div>
  </div>
</body>
</html>
