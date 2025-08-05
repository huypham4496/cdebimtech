<?php
// pages/organization_members.php
session_start();
require_once __DIR__ . '/../config.php';

// 0. Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// 1. Kiểm tra đăng nhập
if (empty($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user']['id'];

// 2. Lấy org_id từ GET hoặc mặc định tổ đầu tiên user quản lý
$orgId = isset($_GET['org_id']) ? (int)$_GET['org_id'] : null;
if (!$orgId) {
    $q = $pdo->prepare("
        SELECT id FROM organizations WHERE created_by = :uid
        UNION
        SELECT organization_id FROM organization_members 
         WHERE user_id = :uid AND role = 'admin'
        LIMIT 1
    ");
    $q->execute([':uid' => $userId]);
    $orgId = $q->fetchColumn();
}

// 3. Tháng, năm
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

// 4. Lấy dữ liệu thành viên + profile
$stmt = $pdo->prepare("
    SELECT u.email,
           m.role,
           COALESCE(p.full_name, '')             AS full_name,
           COALESCE(p.expertise, '')            AS expertise,
           COALESCE(p.position, '')             AS position,
           COALESCE(p.dob, '')                  AS dob,
           COALESCE(p.hometown, '')             AS hometown,
           COALESCE(p.residence, '')            AS residence,
           COALESCE(p.phone, '')                AS phone,
           COALESCE(p.monthly_performance, '')  AS monthly_performance
      FROM organization_members m
      JOIN users u ON u.id = m.user_id
 LEFT JOIN organization_member_profiles p ON p.member_id = m.id
     WHERE m.organization_id = :oid
     ORDER BY u.email
");
$stmt->execute([':oid' => $orgId]);
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Members & Profiles</title>
  <!-- CSS gốc -->
  <link rel="stylesheet" href="../assets/css/sidebar.css">
  <link rel="stylesheet" href="../assets/css/organization.css">
  <!-- Font Awesome -->
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />

</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <h1><i class="fas fa-users"></i> Members & Profiles</h1>

    <form method="get" action="stats_org_detail.php" class="filter-form">
      <input type="hidden" name="org_id" value="<?= htmlspecialchars($orgId) ?>">
      <label>
        Tháng
        <select name="month">
          <?php for ($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>>
              <?= sprintf('%02d',$m) ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>
      <label>
        Năm
        <select name="year">
          <?php for ($y=date('Y')-1; $y<=date('Y'); $y++): ?>
            <option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>>
              <?= $y ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>
      <button type="submit" class="btn-summary">
        <i class="fas fa-chart-bar"></i> Xem tổng hợp
      </button>
    </form>

    <table class="members-table">
      <thead>
        <tr>
          <th>Email</th><th>Role</th><th>Full Name</th><th>Expertise</th>
          <th>Position</th><th>DOB</th><th>Hometown</th><th>Residence</th>
          <th>Phone</th><th>Performance</th>
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
