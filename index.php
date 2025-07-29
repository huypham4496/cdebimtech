<?php
// index.php
session_start();

// Check if config.php exists
if (!file_exists(__DIR__ . '/config.php')) {
    // Configuration missing, redirect to installer
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Check if any tables exist
    $stmt = $pdo->query("SHOW TABLES");
    if ($stmt->rowCount() === 0) {
        // No tables, run schema creation
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        $pdo->exec($schema);
    }
} catch (PDOException $e) {
    // Database error
    http_response_code(500);
    echo '<h1>500 Internal Server Error</h1>';
    echo '<p>Database connection failed. Please check your configuration.</p>';
    exit;
}

// Normal application logic follows
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CDE Bimtech Dashboard</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <?php include __DIR__ . '/pages/includes/header.php'; ?>

  <main>
    <h1>Welcome to CDE Bimtech</h1>
    <!-- Dashboard content here -->
  </main>

  <?php include __DIR__ . '/pages/includes/footer.php'; ?>
</body>
</html>