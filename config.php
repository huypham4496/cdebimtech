<?php
// config.php
// UTF-8 no BOM

ini_set('display_errors', 0);
error_reporting(0);

$host = 'localhost';
$db   = 'cde_db';
$user = 'db_user';
$pass = 'db_pass';
$dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed.');
}