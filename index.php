<?php
// index.php
session_start();

// Chưa cấu hình: vào installer
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php'); exit;
}
// Chưa đăng nhập: vào login
if (empty($_SESSION['user'])) {
    header('Location: pages/login.php'); exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Kết nối và tạo bảng users nếu cần
try {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tbl = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($tbl->rowCount()===0) {
        $sql = file_get_contents(__DIR__.'/schema.sql');
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo '<h1>500 Internal Server Error</h1>';
    echo '<p>DB Error: '.htmlspecialchars($e->getMessage()).'</p>';
    exit;
}

// Nội dung ở index redirect tới home
header('Location: pages/home.php'); exit;