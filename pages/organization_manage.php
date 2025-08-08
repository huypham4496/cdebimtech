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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Organization Management</title>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer"/>
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime(__DIR__.'/../assets/css/sidebar.css'); ?>">
  <link rel="stylesheet" href="../assets/css/organization.css?v=<?php echo filemtime(__DIR__.'/../assets/css/organization.css'); ?>">
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
              <th>Name</th><th>Abbr.</th><th>Address</th><th>Dept.</th><th>Actions</th>
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
        <h2><i class="fas fa-users-cog"></i> Members & Profiles</h2>
        <ul class="list-members">
          <?php foreach ($members as $m): ?>
          <li>
            <select class="role-select" data-member-id="<?= $m['id'] ?>">
              <option value="admin"  <?= $m['role'] === 'admin'  ? 'selected' : '' ?>>Admin</option>
              <option value="member" <?= $m['role'] === 'member' ? 'selected' : '' ?>>Member</option>
            </select>
            <div class="member-info">
              <p class="member-name"><i class="fas fa-user"></i>
                <?= htmlspecialchars($m['full_name'] ?: $m['email']) ?>
              </p>
              <p class="text-muted"><?= htmlspecialchars($m['email']) ?></p>
            </div>
            <button class="btn-action btn-edit"
                    onclick="openProfile(
                      <?= $m['id'] ?>,
                      '<?= addslashes($m['full_name']) ?>',
                      '<?= addslashes($m['expertise']) ?>',
                      '<?= addslashes($m['position']) ?>',
                      '<?= $m['dob'] ?>',
                      '<?= addslashes($m['hometown']) ?>',
                      '<?= addslashes($m['residence']) ?>',
                      '<?= addslashes($m['phone']) ?>',
                      '<?= $m['monthly_performance'] ?>'
                    )">
              <i class="fas fa-user-edit"></i> Edit Profile
            </button>
            <button class="btn-icon btn-delete" onclick="removeMember(<?= $m['id'] ?>)">
              <i class="fas fa-user-minus"></i>
            </button>
          </li>
          <?php endforeach; ?>
        </ul>

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

  <script>
    // Copy invite link
    document.addEventListener('click', e => {
      const btn = e.target.closest('.copy-btn');
      if (btn) {
        const inp = document.getElementById(btn.dataset.target);
        inp.select(); document.execCommand('copy');
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i>', 1500);
      }
    });

    // Open edit organization form
    function openEdit(id,name,abbr,address,dept){
      document.getElementById('orgAction').value='edit_org';
      document.getElementById('orgId').value=id;
      document.getElementById('name').value=name;
      document.getElementById('abbreviation').value=abbr;
      document.getElementById('address').value=address;
      document.getElementById('department').value=dept;
      document.getElementById('orgSubmit').innerHTML='<i class="fas fa-save"></i> Update Org';
      document.getElementById('orgCancel').style.display='inline-block';
    }
    function resetForm(){
      document.getElementById('orgAction').value='create_org';
      document.getElementById('orgForm').reset();
      document.getElementById('orgSubmit').innerHTML='<i class="fas fa-plus-circle"></i> Create Org';
      document.getElementById('orgCancel').style.display='none';
    }

    // Change role
    document.addEventListener('change', e => {
      if (e.target.matches('.role-select')) {
        const sel = e.target;
        fetch('organization_manage.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:`action=update_role&member_id=${sel.dataset.memberId}&role=${sel.value}`
        }).then(()=>sel.classList.add('updated'))
          .then(()=>setTimeout(()=>sel.classList.remove('updated'),800));
      }
    });

    // Open profile edit
    function openProfile(id,fn,ex,pos,dob,ht,rs,ph,mp){
      document.getElementById('profileForm').style.display='block';
      document.getElementById('profileMemberId').value=id;
      ['full_name','expertise','position','dob','hometown','residence','phone','monthly_performance']
        .forEach((f,i)=>document.getElementById(f).value=[fn,ex,pos,dob,ht,rs,ph,mp][i]);
    }
    function resetProfile(){
      document.getElementById('profileForm').style.display='none';
    }

    // Remove member
    function removeMember(id){
      if (!confirm('Remove this member?')) return;
      fetch('organization_manage.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=remove_member&member_id=${id}`
      }).then(()=>location.reload());
    }
  </script>
</body>
</html>
