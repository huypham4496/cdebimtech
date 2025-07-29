<?php
// index.php
session_start();

// Nếu chưa có config, chuyển về installer
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

// Nếu chưa đăng nhập, chuyển tới login
if (empty($_SESSION['user'])) {
    header('Location: pages/login.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Kết nối database và tạo bảng users nếu cần
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tables = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($tables->rowCount() === 0) {
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        $pdo->exec($schema);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo '<h1>500 Internal Server Error</h1>';
    echo '<p>Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Nội dung trang chính
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CDE Bimtech</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Welcome, <?= htmlspecialchars($_SESSION['user']['first_name']) ?></h1>
        <p>This is your dashboard.</p>
        <p><a href="pages/login.php?logout=1">Logout</a></p>
    </header>
    <main>
        <!-- Dashboard content here -->
    </main>
</body>
</html>