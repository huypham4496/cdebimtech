<?php
// pages/organization_manage.php
session_start();
require_once __DIR__ . '/../config.php';

// --- Database Connection ---
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Exception $e) {
    die('Database connection error');
}

// --- Authentication ---
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
guardOrganizationManageAccess($pdo, (int)$userId);
$userId = $_SESSION['user']['id'];

// --- Access Control ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM organizations WHERE created_by = :uid");
$stmt->execute([':uid' => $userId]);
$isOwner = (bool)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM organization_members WHERE user_id = :uid AND role = 'admin'");
$stmt->execute([':uid' => $userId]);
$isAdmin = (bool)$stmt->fetchColumn();

$denied = !($isOwner || $isAdmin);

// --- Handle Actions (only if permitted) ---
if (! $denied) {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'create_org':
            $pdo->prepare("
                INSERT INTO organizations (name, abbreviation, address, department, created_by)
                VALUES (:name, :abbr, :address, :dept, :uid)
            ")->execute([
                ':name'    => trim($_POST['name']),
                ':abbr'    => trim($_POST['abbreviation']),
                ':address' => trim($_POST['address']),
                ':dept'    => trim($_POST['department']),
                ':uid'     => $userId
            ]);
            break;

        case 'edit_org':
            $pdo->prepare("
                UPDATE organizations SET
                  name = :name,
                  abbreviation = :abbr,
                  address = :address,
                  department = :dept
                WHERE id = :id AND created_by = :uid
            ")->execute([
                ':name'    => trim($_POST['name']),
                ':abbr'    => trim($_POST['abbreviation']),
                ':address' => trim($_POST['address']),
                ':dept'    => trim($_POST['department']),
                ':id'      => (int)$_POST['org_id'],
                ':uid'     => $userId
            ]);
            break;

        case 'delete_org':
            $pdo->prepare("DELETE FROM organizations WHERE id = :id AND created_by = :uid")
                ->execute([':id' => (int)$_POST['org_id'], ':uid' => $userId]);
            break;

        case 'invite':
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("
                INSERT INTO organization_invitations (organization_id, token, status)
                VALUES (:oid, :tok, 'pending')
            ")->execute([
                ':oid' => (int)$_POST['org_id'],
                ':tok' => $token
            ]);
            break;

        case 'delete_invite':
            $pdo->prepare("DELETE FROM organization_invitations WHERE id = :id")
                ->execute([':id' => (int)$_POST['inv_id']]);
            break;

        case 'remove_member':
            $pdo->prepare("DELETE FROM organization_members WHERE id = :id")
                ->execute([':id' => (int)$_POST['member_id']]);
            break;

        case 'update_role':
            $pdo->prepare("UPDATE organization_members SET role = :role WHERE id = :mid")
                ->execute([
                    ':role' => $_POST['role'],
                    ':mid'  => (int)$_POST['member_id']
                ]);
            break;

        case 'edit_profile':
            $fields = ['full_name','expertise','position','dob','hometown','residence','phone','monthly_performance'];
            $params = [':mid' => (int)$_POST['member_id']];
            $sets = [];
            foreach ($fields as $f) {
                $sets[] = "$f = :$f";
                $params[":$f"] = $_POST[$f] ?? null;
            }
            $sql = "
                INSERT INTO organization_member_profiles (member_id," . implode(',', $fields) . ")
                VALUES (:mid," . implode(',', array_map(fn($f) => ":$f", $fields)) . ")
                ON DUPLICATE KEY UPDATE " . implode(',', $sets);
            $pdo->prepare($sql)->execute($params);
            break;
            case 'toggle_share':
case 'toggle_share':
    // INPUT: org_id (int), share (0|1)
    $orgId = (int)($_POST['org_id'] ?? 0);
    $share = (int)($_POST['share'] ?? 0);

    if ($orgId <= 0 || ($share !== 0 && $share !== 1)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Bad request']);
        exit;
    }

    // Kiểm tra quyền admin của org này
    $currentUserId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT role FROM organization_members WHERE organization_id = ? AND user_id = ?");
    $stmt->execute([$orgId, $currentUserId]);
    $role = $stmt->fetchColumn();
    if ($role !== 'admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'Forbidden']);
        exit;
    }

    // Lấy gói đang dùng của admin (để share cho member)
    $stmt = $pdo->prepare("SELECT subscription_id FROM users WHERE id = ?");
    $stmt->execute([$currentUserId]);
    $adminSubId = (int)$stmt->fetchColumn();

    try {
        $pdo->beginTransaction();

        // 1) Cập nhật cờ share của tổ chức
        $stmt = $pdo->prepare("UPDATE organizations SET share_subscription = ? WHERE id = ?");
        $stmt->execute([$share, $orgId]);

        // 2) Đồng bộ member
        if ($share === 1) {
            if ($adminSubId <= 0) {
                throw new Exception("Admin chưa có gói để share.");
            }
            // set is_shared=1, gán subscribed_id = gói của admin cho toàn bộ member (trừ admin)
            $stmt = $pdo->prepare("
                UPDATE organization_members 
                   SET is_shared = 1, subscribed_id = ?
                 WHERE organization_id = ? AND user_id <> ?
            ");
            $stmt->execute([$adminSubId, $orgId, $currentUserId]);

            // (Tuỳ hệ thống) Nếu logic quyền dựa vào users.subscription_id thì có thể cập nhật luôn:
            // $pdo->prepare("
            //     UPDATE users u 
            //     JOIN organization_members m ON m.user_id = u.id AND m.organization_id = ?
            //        SET u.subscription_id = ?
            //      WHERE u.id <> ?
            // ")->execute([$orgId, $adminSubId, $currentUserId]);

        } else {
            // Share OFF → trả về Free
            $FREE_ID = 1;
            $stmt = $pdo->prepare("
                UPDATE organization_members 
                   SET is_shared = 0, subscribed_id = ?
                 WHERE organization_id = ? AND user_id <> ?
            ");
            $stmt->execute([$FREE_ID, $orgId, $currentUserId]);

            // (Tuỳ hệ thống)
            // $pdo->prepare("
            //     UPDATE users u 
            //     JOIN organization_members m ON m.user_id = u.id AND m.organization_id = ?
            //        SET u.subscription_id = NULL
            //      WHERE u.id <> ?
            // ")->execute([$orgId, $currentUserId]);
        }

        $pdo->commit();
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
    }
}

// --- Fetch Data for Display ---
$orgStmt = $pdo->prepare("SELECT * FROM organizations WHERE created_by = :uid");
$orgStmt->execute([':uid' => $userId]);
$organizations = $orgStmt->fetchAll();

$invStmt = $pdo->prepare("
    SELECT oi.id, oi.token, oi.status, o.id AS org_id, o.name AS org_name
    FROM organization_invitations oi
    JOIN organizations o ON oi.organization_id = o.id
    WHERE o.created_by = :uid
");
$invStmt->execute([':uid' => $userId]);
$invitations = $invStmt->fetchAll();

$memStmt = $pdo->prepare("
    SELECT om.id, u.email, om.role,
           COALESCE(omp.full_name,'')           AS full_name,
           COALESCE(omp.expertise,'')           AS expertise,
           COALESCE(omp.position,'')            AS position,
           COALESCE(omp.dob,'')                 AS dob,
           COALESCE(omp.hometown,'')            AS hometown,
           COALESCE(omp.residence,'')           AS residence,
           COALESCE(omp.phone,'')               AS phone,
           COALESCE(omp.monthly_performance,'') AS monthly_performance
    FROM organization_members om
    JOIN users u ON om.user_id = u.id
    LEFT JOIN organization_member_profiles omp ON omp.member_id = om.id
    WHERE om.organization_id IN (
      SELECT id FROM organizations WHERE created_by = :uid
    )
    ORDER BY u.email
");
$memStmt->execute([':uid' => $userId]);
$members = $memStmt->fetchAll();
$membersOrgId = isset($_GET['members_org_id']) ? (int)$_GET['members_org_id'] : 0;

// Nếu chưa chọn, mặc định chọn tổ chức đầu tiên (nếu có)
if ($membersOrgId === 0 && !empty($organizations)) {
    $membersOrgId = (int)$organizations[0]['id'];
}

// Lấy danh sách members theo tổ chức đã chọn
$members = [];
if ($membersOrgId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                om.id       AS member_row_id,
                om.user_id  AS id,
                om.role     AS role,
                u.email     AS email,
                CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS full_name
            FROM organization_members om
            JOIN users u ON u.id = om.user_id
            WHERE om.organization_id = :oid
            ORDER BY 
                CASE WHEN om.role='admin' THEN 0 ELSE 1 END,
                full_name, email
        ");
        $stmt->execute([':oid' => $membersOrgId]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Tránh vỡ trang nếu có lỗi CSDL; ghi log để kiểm tra
        error_log('Members query failed: ' . $e->getMessage());
        $members = [];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Organization Management</title>
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        integrity="sha512-dynvDxJ5aVF6oU1i6zfoalvVYvNvKcJste/0q5u+P%2FgPm4jG3E5s3UeJ8V+RaH59RUW2YCiMzZ6pyRrg58F3CA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime(__DIR__.'/../assets/css/sidebar.css'); ?>">
  <link rel="stylesheet" href="../assets/css/organization_manage.css?v=<?php echo filemtime(__DIR__.'/../assets/css/organization_manage.css'); ?>">
  <link rel="stylesheet" href="../assets/css/org_manage.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <?php if ($denied): ?>
      <div class="access-denied">Bạn không có quyền truy cập trang này.</div>
    <?php else: ?>
      <h1><i class="fas fa-building"></i> Organization Management</h1>

      <!-- Your Organizations -->
      <section class="card">
        <h2><i class="fas fa-list"></i> Your Organizations</h2>
        <table class="org-table">
          <thead>
            <tr>
              <th>Name</th><th>Abbr.</th><th>Address</th><th>Dept.</th><th>Plan Sharing</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($organizations as $o): ?>
            <tr>
              <td><?= htmlspecialchars($o['name']) ?></td>
              <td><?= htmlspecialchars($o['abbreviation']) ?></td>
              <td><?= htmlspecialchars($o['address']) ?></td>
              <td><?= htmlspecialchars($o['department']) ?></td>
               <td>
      <?php if ((int)$o['share_subscription'] === 1): ?>
        <span class="badge badge-success">Shared</span>
      <?php else: ?>
        <span class="badge badge-muted">Private</span>
      <?php endif; ?>

      <form method="POST" style="display:inline; margin-left:8px;">
        <input type="hidden" name="action" value="toggle_share">
        <input type="hidden" name="organization_id" value="<?= (int)$o['id'] ?>">
        <input type="hidden" name="share" value="<?= ((int)$o['share_subscription'] === 1) ? 0 : 1 ?>">
        <button type="submit" class="btn-action btn-primary">
          <?= ((int)$o['share_subscription'] === 1) ? 'Unshare' : 'Share Plan' ?>
        </button>
      </form>
    </td>
              <td>
                <button class="btn-action btn-secondary"
                        onclick="openEdit(
                          <?= $o['id'] ?>,
                          '<?= addslashes($o['name']) ?>',
                          '<?= addslashes($o['abbreviation']) ?>',
                          '<?= addslashes($o['address']) ?>',
                          '<?= addslashes($o['department']) ?>'
                        )">
                  <i class="fas fa-edit"></i> Edit
                </button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="delete_org">
                  <input type="hidden" name="org_id" value="<?= $o['id'] ?>">
                  <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>

      <!-- Create/Edit Organization -->
      <form id="orgForm" method="POST" class="card form-card">
        <input type="hidden" name="action" id="orgAction" value="create_org">
        <input type="hidden" name="org_id" id="orgId" value="">
        <div class="form-group">
          <label for="name"><i class="fas fa-briefcase"></i> Company Name</label>
          <input id="name" name="name" type="text" required>
        </div>
        <div class="form-group">
          <label for="abbreviation"><i class="fas fa-font"></i> Abbreviation</label>
          <input id="abbreviation" name="abbreviation" type="text" required>
        </div>
        <div class="form-group">
          <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
          <input id="address" name="address" type="text" required>
        </div>
        <div class="form-group">
          <label for="department"><i class="fas fa-sitemap"></i> Department</label>
          <input id="department" name="department" type="text">
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-action btn-primary" id="orgSubmit">
            <i class="fas fa-plus-circle"></i> Create Organization
          </button>
          <button type="button" class="btn-action btn-secondary" id="orgCancel" style="display:none" onclick="resetForm()">
            <i class="fas fa-times"></i> Cancel
          </button>
        </div>
      </form>

      <!-- Invitation Links -->
      <section class="card">
        <h2><i class="fas fa-link"></i> Invitation Links</h2>
        <form method="POST" class="inline-form">
          <input type="hidden" name="action" value="invite">
          <select name="org_id" required>
            <option value="" disabled selected>Select organization</option>
            <?php foreach ($organizations as $o): ?>
              <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-action btn-secondary">
            <i class="fas fa-paper-plane"></i> Generate Link
          </button>
        </form>
        <ul class="list-invitations">
          <?php foreach ($invitations as $inv): ?>
          <li>
            <span class="inv-org"><i class="fas fa-building"></i> <?= htmlspecialchars($inv['org_name']) ?></span>
            <?php $link = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/organization_invite.php?token=' . $inv['token']; ?>
            <span class="inv-link">
              <input type="text" id="link-<?= $inv['id'] ?>" readonly value="<?= htmlspecialchars($link) ?>">
              <button type="button" class="btn-icon copy-btn" data-target="link-<?= $inv['id'] ?>">
                <i class="fas fa-copy"></i>
              </button>
            </span>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="delete_invite">
              <input type="hidden" name="inv_id" value="<?= $inv['id'] ?>">
              <button class="btn-action btn-delete"><i class="fas fa-times-circle"></i></button>
            </form>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <!-- Members & Profiles -->
<section class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
    <h2 style="margin:0;">
      <i class="fas fa-users-cog"></i> Members & Profiles
    </h2>

    <!-- Bộ lọc chọn tổ chức (GET) -->
    <form method="GET" class="inline-form" style="margin:0;">
      <!-- Giữ lại các tham số GET khác nếu cần -->
      <?php foreach ($_GET as $k => $v): if ($k === 'members_org_id') continue; ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
      <?php endforeach; ?>

      <label class="visually-hidden" for="members_org_id">Chọn tổ chức</label>
      <select id="members_org_id" name="members_org_id" onchange="this.form.submit()">
        <?php foreach ($organizations as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= (int)$o['id'] === (int)$membersOrgId ? 'selected' : '' ?>>
            <?= htmlspecialchars($o['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <noscript>
        <button type="submit" class="btn-action btn-secondary">
          <i class="fas fa-check"></i> Lọc
        </button>
      </noscript>
    </form>
  </div>

  <?php if (empty($organizations)): ?>
    <p class="text-muted" style="margin-top:10px;">Chưa có tổ chức nào để hiển thị.</p>
  <?php else: ?>
    <?php if (empty($members)): ?>
      <p class="text-muted" style="margin-top:10px;">Tổ chức này chưa có thành viên.</p>
    <?php else: ?>
      <ul class="list-members">
        <?php foreach ($members as $m): ?>
          <li>
            <!-- Role selector -->
            <select class="role-select" 
                    data-member-id="<?= (int)$m['id'] ?>" 
                    data-org-id="<?= (int)$membersOrgId ?>">
              <option value="admin"  <?= $m['role'] === 'admin'  ? 'selected' : '' ?>>Admin</option>
              <option value="member" <?= $m['role'] === 'member' ? 'selected' : '' ?>>Member</option>
            </select>

            <!-- Info -->
            <div class="member-info">
              <p class="member-name">
                <i class="fas fa-user"></i>
                <?= htmlspecialchars($m['full_name'] ?: $m['email']) ?>
              </p>
              <p class="text-muted"><?= htmlspecialchars($m['email']) ?></p>
            </div>

            <!-- Actions -->
            <div class="member-actions">
              <button class="btn-action btn-edit"
                      onclick="openProfile(
                        '<?= (int)$m['id'] ?>',
                        '<?= htmlspecialchars($m['full_name'] ?: '', ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($m['email'], ENT_QUOTES) ?>'
                      )">
                <i class="fas fa-user-edit"></i> Profile
              </button>
              <button class="btn-action btn-delete" onclick="removeMember(<?= (int)$m['id'] ?>)">
                <i class="fas fa-user-minus"></i>
              </button>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>
</section>


        <!-- Profile Edit Form -->
        <form id="profileForm" method="POST" class="card form-card" style="display:none">
          <input type="hidden" name="action" value="edit_profile">
          <input type="hidden" name="member_id" id="profileMemberId">
          <?php
          $fields = [
            'full_name'=>'Full Name','expertise'=>'Expertise','position'=>'Position',
            'dob'=>'Date of Birth','hometown'=>'Hometown','residence'=>'Residence',
            'phone'=>'Phone','monthly_performance'=>'Monthly Performance'
          ];
          foreach ($fields as $k => $label): ?>
            <div class="form-group">
              <label for="<?= $k ?>"><?= $label ?></label>
              <input id="<?= $k ?>" name="<?= $k ?>" type="<?= $k === 'dob' ? 'date' : 'text' ?>">
            </div>
          <?php endforeach; ?>
          <div class="form-actions">
            <button type="submit" class="btn-action btn-primary">
              <i class="fas fa-save"></i> Update Profile
            </button>
            <button type="button" class="btn-action btn-secondary" onclick="resetProfile()">
              <i class="fas fa-times"></i> Cancel
            </button>
          </div>
        </form>
      </section>
    <?php endif; ?>
  </div>

  
  <script src="../assets/js/org_manage.js" defer></script>
</body>
</html>
