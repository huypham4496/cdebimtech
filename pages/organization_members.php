<?php
// pages/organization_members.php
session_start();
require_once __DIR__ . '/../config.php';

// — DB connection —
$pdo = new PDO(
    "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);

// — Auth —
$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}

// — Fetch organizations admin’d by user —
$stmt = $pdo->prepare("
    SELECT o.id, o.name
      FROM organizations o
     WHERE o.created_by = :uid
    UNION
    SELECT o.id, o.name
      FROM organizations o
      JOIN organization_members m ON m.organization_id = o.id
     WHERE m.user_id = :uid AND m.role = 'admin'
    ORDER BY name
");
$stmt->execute([':uid'=>$userId]);
$orgs = $stmt->fetchAll();

// — Selected org, month, year —
$orgId = isset($_GET['org_id']) ? (int)$_GET['org_id'] : ($orgs[0]['id']??null);
$month = isset($_GET['month'])  ? (int)$_GET['month']  : date('n');
$year  = isset($_GET['year'])   ? (int)$_GET['year']   : date('Y');

// — Fetch members —
$memStmt = $pdo->prepare("
    SELECT u.id, u.email, m.role,
           COALESCE(p.full_name, u.email) AS full_name
      FROM organization_members m
      JOIN users u ON u.id = m.user_id
 LEFT JOIN organization_member_profiles p ON p.member_id = m.id
     WHERE m.organization_id = :oid
     ORDER BY u.email
");
$memStmt->execute([':oid'=>$orgId]);
$members = $memStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Members & Profiles</title>
  <link rel="stylesheet" href="../assets/css/sidebar.css">
  <link rel="stylesheet" href="../assets/css/organization.css">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        integrity="sha512-dyZ88n1lW8v+TqF1uFHtwJ0hK9iJ/ocZLSoG5Z9V1IFGpF1r2T1o2z+g0I9+Z1EUfJ/1GOUdZ1sxvJyVfqG+Eg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    .filter-form {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      align-items: flex-end;
      margin-bottom: 1.5rem;
    }
    .filter-form label {
      font-size: 0.95rem;
      color: var(--text);
    }
    .filter-form select {
      padding: 0.5rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-family: inherit;
    }
    .btn-summary {
      margin-left: auto;
      background: var(--primary);
      color: #fff;
      padding: 0.6rem 1.2rem;
      border-radius: var(--radius);
      text-decoration: none;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: background 0.3s;
    }
    .btn-summary:hover {
      background: darken(var(--primary), 10%);
    }
    .members-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-bg);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 4px 16px var(--shadow);
      font-family: var(--font-base);
    }
    .members-table thead {
      background: var(--primary);
    }
    .members-table thead th {
      color: #fff;
      text-transform: uppercase;
      font-size: 0.85rem;
      padding: 1rem;
      text-align: left;
    }
    .members-table tbody tr:nth-child(even) {
      background: #fafafa;
    }
    .members-table tbody tr:hover {
      background: rgba(252,164,21,0.1);
    }
    .members-table td {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #eee;
      color: var(--text);
      font-size: 0.95rem;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <h1><i class="fas fa-users"></i> Members & Profiles</h1>

    <form method="get" class="filter-form">
      <label>
        Organization<br>
        <select name="org_id" onchange="this.form.submit()">
          <?php foreach($orgs as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $o['id']==$orgId?'selected':''?>>
              <?= htmlspecialchars($o['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Month<br>
        <select name="month">
          <?php for($m=1;$m<=12;$m++): ?>
            <option value="<?= $m?>" <?= $m===$month?'selected':''?>>
              <?= sprintf('%02d',$m) ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>

      <label>
        Year<br>
        <select name="year">
          <?php for($y=date('Y')-1;$y<=date('Y');$y++): ?>
            <option value="<?= $y?>" <?= $y===$year?'selected':''?>>
              <?= $y ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>

      <a href="stats_org_detail.php?org_id=<?= $orgId ?>&month=<?= $month ?>&year=<?= $year ?>"
         class="btn-summary">
        <i class="fas fa-chart-bar"></i> Xem tổng hợp
      </a>
    </form>

    <table class="members-table">
      <thead>
        <tr>
          <th>Full Name</th>
          <th>Email</th>
          <th>Role</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($members as $m): ?>
        <tr>
          <td><?= htmlspecialchars($m['full_name']) ?></td>
          <td><?= htmlspecialchars($m['email']) ?></td>
          <td><?= htmlspecialchars(ucfirst($m['role'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
