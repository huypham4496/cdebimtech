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

// --- Determine accessible organizations ---
// Creator OR member
$orgIds = [];

// as creator
$stmt = $pdo->prepare("SELECT id FROM organizations WHERE created_by = :uid");
$stmt->execute([':uid'=>$userId]);
while ($row = $stmt->fetch()) {
    $orgIds[] = $row['id'];
}

// as member
$stmt = $pdo->prepare("SELECT organization_id FROM organization_members WHERE user_id = :uid");
$stmt->execute([':uid'=>$userId]);
while ($row = $stmt->fetch()) {
    $orgIds[] = $row['organization_id'];
}

$orgIds = array_unique($orgIds);
if (empty($orgIds)) {
    // no access
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access Denied</title><link rel="stylesheet" href="../assets/css/organization.css"></head><body><div class="access-denied">Bạn không có tổ chức để xem thành viên.</div></body></html>';
    exit;
}

// --- Fetch members of those organizations ---
$inClause = implode(',', array_fill(0, count($orgIds), '?'));
$sql = "
    SELECT o.name AS org_name,
           u.email,
           om.role,
           COALESCE(omp.full_name,'') AS full_name,
           COALESCE(omp.expertise,'') AS expertise,
           COALESCE(omp.position,'') AS position,
           COALESCE(omp.dob,'') AS dob,
           COALESCE(omp.hometown,'') AS hometown,
           COALESCE(omp.residence,'') AS residence,
           COALESCE(omp.phone,'') AS phone,
           COALESCE(omp.monthly_performance,'') AS monthly_performance
    FROM organization_members om
    JOIN organizations o ON om.organization_id = o.id
    JOIN users u ON om.user_id = u.id
    LEFT JOIN organization_member_profiles omp ON omp.member_id = om.id
    WHERE om.organization_id IN ($inClause)
    ORDER BY o.name, u.email
";
$stmt = $pdo->prepare($sql);
$stmt->execute($orgIds);
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Organization Members</title>
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        integrity="sha512-dyZ88n1lW8v+TqF1uFHtwJ0hK9iJ/ocZLSoG5Z9V1IFGpF1r2T1o2z+g0I9+Z1EUfJ/1GOUdZ1sxvJyVfqG+Eg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime(__DIR__.'/../assets/css/sidebar.css'); ?>">
  <link rel="stylesheet" href="../assets/css/organization.css?v=<?php echo filemtime(__DIR__.'/../assets/css/organization.css'); ?>">
  
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <h1><i class="fas fa-users"></i> Organization Members</h1>
    <table class="members-table">
      <thead>
        <tr>
          <th>Organization</th><th>Email</th><th>Role</th><th>Full Name</th><th>Expertise</th>
          <th>Position</th><th>DOB</th><th>Hometown</th><th>Residence</th><th>Phone</th><th>Performance</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
          <td data-label="Organization"><?= htmlspecialchars($m['org_name']) ?></td>
          <td data-label="Email"><?= htmlspecialchars($m['email']) ?></td>
          <td data-label="Role"><?= htmlspecialchars(ucfirst($m['role'])) ?></td>
          <td data-label="Full Name"><?= htmlspecialchars($m['full_name']) ?></td>
          <td data-label="Expertise"><?= htmlspecialchars($m['expertise']) ?></td>
          <td data-label="Position"><?= htmlspecialchars($m['position']) ?></td>
          <td data-label="DOB"><?= htmlspecialchars($m['dob']) ?></td>
          <td data-label="Hometown"><?= htmlspecialchars($m['hometown']) ?></td>
          <td data-label="Residence"><?= htmlspecialchars($m['residence']) ?></td>
          <td data-label="Phone"><?= htmlspecialchars($m['phone']) ?></td>
          <td data-label="Performance"><?= htmlspecialchars($m['monthly_performance']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
