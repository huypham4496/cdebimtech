<?php
// pages/login.php
// UTF-8 no BOM
session_start();
require_once __DIR__ . '/../includes/functions.php';

// Đăng xuất
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = loginUser($_POST['email'], $_POST['password']);
    if ($user) {
        $_SESSION['user'] = [
            'id'     => $user['id'],
            'name'   => $user['first_name'],
            'avatar' => $user['avatar'],
        ];
        header('Location: ../index.php');
        exit;
    }
    $error = 'Email hoặc mật khẩu không đúng.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login to ADSCivil CDE</title>
    <link rel="stylesheet" href="../assets/css/login.css?v=<?php echo filemtime(__DIR__.'/../assets/css/login.css'); ?>">
</head>
<body>
<div class="login-container">
    <div class="login-left">
        <img src="../assets/images/login-bg.jpg" alt="Background">
        <div class="overlay">
            <h1>ADSCivil CDE - CDE-DEV solution for BIM projects</h1>
            <p>Empower your workflow with real-time 3D visualization, full data ownership, and powerful BIM data analysis. Secure, immersive, and built for limitless collaboration.</p>
            
        </div>
    </div>
    <h1>CDE Bimtech</h1>
</div>
</body>
</html>