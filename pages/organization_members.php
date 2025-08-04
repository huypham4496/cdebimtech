<?php
// pages/organization_members.php
session_start();
require_once __DIR__ . '/../config.php';

// --- Database Connection ---
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (Exception $e) {
    die('Database connection error');
}

// --- Authentication ---
if (empty($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user']['id'];

// --- Fetch Members & Profiles ---
$stmt = $pdo->prepare("
    SELECT
      u.email,
      om.role,
      COALESCE(omp.full_name, '')            AS full_name,
      COALESCE(omp.expertise, '')            AS expertise,
      COALESCE(omp.position, '')             AS position,
      COALESCE(omp.dob, '')                  AS dob,
      COALESCE(omp.hometown, '')             AS hometown,
      COALESCE(omp.residence, '')            AS residence,
      COALESCE(omp.phone, '')                AS phone,
      COALESCE(omp.monthly_performance, '')  AS monthly_performance
    FROM organization_members om
    JOIN users u ON om.user_id = u.id
    LEFT JOIN organization_member_profiles omp
      ON omp.member_id = om.id
    WHERE om.organization_id IN (
      SELECT id FROM organizations WHERE created_by = :uid
    )
    ORDER BY u.email
");
$stmt->execute([':uid' => $userId]);
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Organization Members</title>
  <!-- Font Awesome -->
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        integrity="sha512-dyZ88n1lW8v+TqF1uFHtwJ0hK9iJ/ocZLSoG5Z9V1IFGpF1r2T1o2z+g0I9+Z1EUfJ/1GOUdZ1sxvJyVfqG+Eg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
  <!-- Sidebar CSS -->
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime(__DIR__.'/../assets/css/sidebar.css'); ?>">
  <!-- Members Table CSS -->
  <link rel="stylesheet" href="../assets/css/members.css?v=<?php echo filemtime(__DIR__.'/../assets/css/members.css'); ?>">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <h1><i class="fas fa-users"></i> Organization Members</h1>
    <table class="members-table">
      <thead>
        <tr>
          <th>Email</th>
          <th>Role</th>
          <th>Full Name</th>
          <th>Expertise</th>
          <th>Position</th>
          <th>DOB</th>
          <th>Hometown</th>
          <th>Residence</th>
          <th>Phone</th>
          <th>Performance</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
          <td><?= htmlspecialchars($m['email']) ?></td>
          <td><?= htmlspecialchars(ucfirst($m['role'])) ?></td>
          <td><?= htmlspecialchars($m['full_name']) ?></td>
          <td><?= htmlspecialchars($m['expertise']) ?></td>
          <td><?= htmlspecialchars($m['position']) ?></td>
          <td><?= htmlspecialchars($m['dob']) ?></td>
          <td><?= htmlspecialchars($m['hometown']) ?></td>
          <td><?= htmlspecialchars($m['residence']) ?></td>
          <td><?= htmlspecialchars($m['phone']) ?></td>
          <td><?= htmlspecialchars($m['monthly_performance']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
