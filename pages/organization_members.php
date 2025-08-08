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
// — Feature gate: Organization Manage —
$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/permissions.php';
guardOrganizationMembersAccess($pdo, (int)$userId);
$userId = $_SESSION['user']['id'];

// 2. Lấy org_id từ GET hoặc mặc định tổ đầu tiên user quản lý
$orgId = isset($_GET['org_id']) ? (int)$_GET['org_id'] : null;
if (!$orgId) {
    $q = $pdo->prepare("
        SELECT id FROM organizations WHERE created_by = :uid
        UNION
        SELECT organization_id FROM organization_members 
         WHERE user_id = :uid 
        LIMIT 1
    ");
    $q->execute([':uid' => $userId]);
    $orgId = $q->fetchColumn();
}
// 2b. Lấy danh sách TỔ mà user sở hữu/tham gia để render vào select
$userOrganizations = [];
$qo = $pdo->prepare("
    SELECT id, name FROM organizations WHERE created_by = :uid
    UNION
    SELECT o.id, o.name
      FROM organizations o
      JOIN organization_members om ON om.organization_id = o.id
     WHERE om.user_id = :uid
  GROUP BY id, name
  ORDER BY name
");
$qo->execute([':uid' => $userId]);
$userOrganizations = $qo->fetchAll(PDO::FETCH_ASSOC);

// Nếu orgId hiện tại không nằm trong danh sách, ép về tổ đầu tiên (nếu có)
if ($orgId && !in_array($orgId, array_column($userOrganizations, 'id'))) {
    $orgId = (int)($userOrganizations[0]['id'] ?? $orgId);
}
// Nếu vẫn chưa có orgId và có danh sách tổ -> chọn phần tử đầu
if (!$orgId && !empty($userOrganizations)) {
    $orgId = (int)$userOrganizations[0]['id'];
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
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime(__DIR__.'/../assets/css/sidebar.css'); ?>">
  <link rel="stylesheet" href="../assets/css/organization.css?v=<?php echo filemtime(__DIR__.'/../assets/css/organization.css'); ?>">

  <!-- Font Awesome -->
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />

</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <h1><i class="fas fa-users"></i> Members & Profiles</h1>

    <form method="get" action="organization_members.php" class="filter-form">
  <!-- Chọn TỔ mà user tham gia/sở hữu -->
  <label>
    Tổ chức
    <select name="org_id" onchange="this.form.submit()">
      <?php foreach ($userOrganizations as $org): ?>
        <option value="<?= (int)$org['id'] ?>" <?= ((int)$org['id'] === (int)$orgId) ? 'selected' : '' ?>>
          <?= htmlspecialchars($org['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>

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
      <?php $curY = (int)date('Y'); for ($y=$curY-2; $y<=$curY+2; $y++): ?>
        <option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>>
          <?= $y ?>
        </option>
      <?php endfor; ?>
    </select>
  </label>

  <!-- Link “Xem tổng hợp” luôn build đúng org_id/tháng/năm đã chọn -->
  <a class="btn-summary" 
     href="stats_org_detail.php?org_id=<?= urlencode($orgId) ?>&month=<?= urlencode($month) ?>&year=<?= urlencode($year) ?>">
    <i class="fas fa-chart-bar"></i> Xem tổng hợp
  </a>

  <!-- Dự phòng nếu tắt JS hoặc muốn lọc thủ công -->
  <noscript>
    <button type="submit" class="btn-summary">
      <i class="fas fa-chart-bar"></i> Lọc / Xem tổng hợp
    </button>
  </noscript>
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
