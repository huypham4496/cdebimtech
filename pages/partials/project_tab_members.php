<?php
/**
 * pages/partials/project_tab_members.php — robust version
 * - Dynamically detects `users` table name/email columns (name/fullname/username/display_name or first_name+last_name)
 * - Avoids referencing non-existent columns to prevent "Unknown column 'u.name'"
 */

if (!isset($pdo) || !isset($project) || !isset($userId)) { echo '<div class="alert">Context missing.</div>'; return; }

// ---------- helpers (local) ----------
function cde_table_exists_local(PDO $pdo, string $table): bool {
  try { $stm = $pdo->prepare("SHOW TABLES LIKE :t"); $stm->execute([':t'=>$table]); return (bool)$stm->fetchColumn(); }
  catch (Throwable $e) { return false; }
}
function cde_column_exists_local(PDO $pdo, string $table, string $col): bool {
  try { $stm = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c"); $stm->execute([':c'=>$col]); return (bool)$stm->fetch(PDO::FETCH_ASSOC); }
  catch (Throwable $e) { return false; }
}

/** Return SQL expressions for user's display name and email, with aliases.
 * Example: ["expr_name"=>"COALESCE(u.fullname, ... ) AS name", "expr_email"=>"u.email AS email"]
 */
function users_name_email_expr(PDO $pdo): array {
  $nameExpr = null;
  // Prefer composed first_name + last_name
  $hasFirst = cde_column_exists_local($pdo, 'users','first_name');
  $hasLast  = cde_column_exists_local($pdo, 'users','last_name');
  if ($hasFirst || $hasLast) {
    $first = $hasFirst ? "u.first_name" : "''";
    $last  = $hasLast  ? "u.last_name"  : "''";
    $nameExpr = "NULLIF(TRIM(CONCAT($first, ' ', $last)), '')";
  }
  // Try common single name columns
  $cands = ['name','full_name','fullname','display_name','username'];
  foreach ($cands as $c) {
    if (cde_column_exists_local($pdo, 'users', $c)) {
      $col = "u.`$c`";
      $nameExpr = $nameExpr ? "COALESCE($nameExpr, NULLIF($col,''))" : "NULLIF($col,'')";
      // do not break; continue to build coalesce chain to be safe
    }
  }
  if (!$nameExpr) { $nameExpr = "NULL"; }
  // Final fallback to "User #<id>"
  $nameExpr = "COALESCE($nameExpr, CONCAT('User #', u.id)) AS name";

  // email column
  $emailExpr = "NULL AS email";
  foreach (['email','email_address','mail'] as $e) {
    if (cde_column_exists_local($pdo, 'users', $e)) { $emailExpr = "u.`$e` AS email"; break; }
  }
  return ['expr_name'=>$nameExpr, 'expr_email'=>$emailExpr];
}

function ensure_members_tables(PDO $pdo): void {
  if (!cde_table_exists_local($pdo, 'project_groups')) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_groups (
      id INT AUTO_INCREMENT PRIMARY KEY,
      project_id INT NOT NULL,
      name VARCHAR(191) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_prj_name (project_id, name),
      KEY prj_id (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
  if (!cde_table_exists_local($pdo, 'project_group_members')) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_group_members (
      id INT AUTO_INCREMENT PRIMARY KEY,
      project_id INT NOT NULL,
      group_id INT NOT NULL,
      user_id INT NOT NULL,
      role ENUM('deploy','control') DEFAULT 'deploy',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_member (project_id, user_id),
      KEY prj_grp (project_id, group_id),
      KEY grp_id (group_id),
      KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
  if (!cde_table_exists_local($pdo, 'project_invites')) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_invites (
      id INT AUTO_INCREMENT PRIMARY KEY,
      project_id INT NOT NULL,
      token VARCHAR(64) NOT NULL,
      expires_at DATETIME NOT NULL,
      status ENUM('active','revoked','used','expired') DEFAULT 'active',
      created_by INT NOT NULL,
      used_by INT DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      used_at DATETIME DEFAULT NULL,
      UNIQUE KEY uniq_token (token),
      KEY prj_id (project_id),
      KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
}
function get_or_create_group(PDO $pdo, int $projectId, string $name): int {
  $stm = $pdo->prepare("SELECT id FROM project_groups WHERE project_id=:pid AND name=:n LIMIT 1");
  $stm->execute([':pid'=>$projectId, ':n'=>$name]);
  $id = (int)($stm->fetchColumn() ?: 0);
  if ($id) return $id;
  $ins = $pdo->prepare("INSERT INTO project_groups (project_id, name) VALUES (:pid, :n)");
  $ins->execute([':pid'=>$projectId, ':n'=>$name]);
  return (int)$pdo->lastInsertId();
}
function ensure_default_groups(PDO $pdo, int $projectId): array {
  $gid_manager = get_or_create_group($pdo, $projectId, 'manager');
  $gid_uncat   = get_or_create_group($pdo, $projectId, 'chưa phân loại');
  return ['manager'=>$gid_manager, 'uncat'=>$gid_uncat];
}
function is_project_manager(PDO $pdo, int $projectId, int $userId, array $project): bool {
  if ((int)($project['created_by'] ?? 0) === $userId) return true;
  $stm = $pdo->prepare("SELECT pgm.id
    FROM project_group_members pgm
    JOIN project_groups pg ON pg.id=pgm.group_id
    WHERE pgm.project_id=:pid AND pg.name='manager' AND pgm.user_id=:uid LIMIT 1");
  $stm->execute([':pid'=>$projectId, ':uid'=>$userId]);
  return (bool)$stm->fetch(PDO::FETCH_ASSOC);
}
function add_member_to_group(PDO $pdo, int $projectId, int $groupId, int $userId, string $role='deploy'): void {
  $stm = $pdo->prepare("SELECT id FROM project_group_members WHERE project_id=:pid AND user_id=:uid LIMIT 1");
  $stm->execute([':pid'=>$projectId, ':uid'=>$userId]);
  $mid = (int)($stm->fetchColumn() ?: 0);
  if ($mid) {
    $upd = $pdo->prepare("UPDATE project_group_members SET group_id=:gid, role=:r WHERE id=:id");
    $upd->execute([':gid'=>$groupId, ':r'=>$role, ':id'=>$mid]);
    return;
  }
  $ins = $pdo->prepare("INSERT INTO project_group_members (project_id, group_id, user_id, role) VALUES (:pid,:gid,:uid,:r)");
  $ins->execute([':pid'=>$projectId, ':gid'=>$groupId, ':uid'=>$userId, ':r'=>$role]);
}
function list_invites(PDO $pdo, int $projectId): array {
  $now = date('Y-m-d H:i:s');
  $pdo->prepare("UPDATE project_invites SET status='expired' WHERE status='active' AND expires_at < :now")->execute([':now'=>$now]);
  $stm = $pdo->prepare("SELECT * FROM project_invites WHERE project_id=:pid ORDER BY id DESC");
  $stm->execute([':pid'=>$projectId]);
  return $stm->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function org_users_for_manager(PDO $pdo, int $managerId): array {
  $orgIds = [];
  try {
    $stm = $pdo->prepare("SELECT organization_id FROM organization_members WHERE user_id=:uid");
    $stm->execute([':uid'=>$managerId]);
    $orgIds = array_map('intval', array_column($stm->fetchAll(), 'organization_id'));
  } catch (Throwable $e) { $orgIds = []; }
  if (!$orgIds) return [];
  $in = implode(',', array_fill(0, count($orgIds), '?'));
  $exprs = users_name_email_expr($pdo);
  try {
    $stm = $pdo->prepare("SELECT DISTINCT u.id, {$exprs['expr_name']}, {$exprs['expr_email']}
      FROM organization_members om
      JOIN users u ON u.id = om.user_id
      WHERE om.organization_id IN ($in) AND u.id <> ?
      ORDER BY name ASC");
    $params = $orgIds; $params[] = $managerId;
    $stm->execute($params);
    return $stm->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { return []; }
}
function list_groups_with_members(PDO $pdo, int $projectId): array {
  $stm = $pdo->prepare("SELECT pg.id as group_id, pg.name as group_name FROM project_groups pg WHERE pg.project_id=:pid ORDER BY pg.name");
  $stm->execute([':pid'=>$projectId]);
  $groups = $stm->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $result = [];
  $exprs = users_name_email_expr($pdo);
  foreach ($groups as $g) {
    $stm2 = $pdo->prepare("SELECT pgm.user_id, pgm.role, {$exprs['expr_name']}, {$exprs['expr_email']}
      FROM project_group_members pgm
      LEFT JOIN users u ON u.id = pgm.user_id
      WHERE pgm.project_id=:pid AND pgm.group_id=:gid
      ORDER BY name");
    $stm2->execute([':pid'=>$projectId, ':gid'=>(int)$g['group_id']]);
    $result[] = ['group'=>$g, 'members'=>$stm2->fetchAll(PDO::FETCH_ASSOC) ?: []];
  }
  return $result;
}

// Ensure tables + default groups + owner in manager
ensure_members_tables($pdo);
$groups = ensure_default_groups($pdo, (int)$project['id']);
add_member_to_group($pdo, (int)$project['id'], $groups['manager'], (int)$project['created_by'], 'control'); // owner as manager

$isManager = is_project_manager($pdo, (int)$project['id'], (int)$userId, $project);
$flash = ['ok'=>[], 'err'=>[]];

// Accept invite (any logged-in user)
if (($_POST['action'] ?? '') === 'accept_invite' && isset($_POST['token'])) {
  $token = trim($_POST['token']);
  try {
    $stm = $pdo->prepare("SELECT * FROM project_invites WHERE token=:t AND project_id=:pid LIMIT 1");
    $stm->execute([':t'=>$token, ':pid'=>(int)$project['id']]);
    $row = $stm->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $flash['err'][] = 'Invite not found.'; }
    else if ($row['status'] !== 'active') { $flash['err'][] = 'Invite is no longer active.'; }
    else if (strtotime($row['expires_at']) < time()) { $flash['err'][] = 'Invite has expired.'; }
    else {
      add_member_to_group($pdo, (int)$project['id'], $groups['uncat'], (int)$userId, 'deploy');
      $upd = $pdo->prepare("UPDATE project_invites SET status='used', used_by=:uid, used_at=NOW() WHERE id=:id");
      $upd->execute([':uid'=>$userId, ':id'=>(int)$row['id']]);
      $flash['ok'][] = 'You have joined this project.';
    }
  } catch (Throwable $e) { $flash['err'][] = 'Could not accept invite.'; }
}

// Manager-only actions
if ($isManager) {
  $act = $_POST['action'] ?? '';

  if ($act === 'create_invite') {
    $ttl = $_POST['ttl'] ?? '24h';
    $seconds = 0;
    if ($ttl === '15m') $seconds = 900;
    elseif ($ttl === '1h') $seconds = 3600;
    elseif ($ttl === '24h') $seconds = 86400;
    elseif ($ttl === '7d') $seconds = 604800;
    elseif ($ttl === '30d') $seconds = 2592000;
    else $seconds = 86400;
    $token = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', time() + $seconds);
    try {
      $ins = $pdo->prepare("INSERT INTO project_invites (project_id, token, expires_at, status, created_by) VALUES (:pid,:t,:exp,'active',:uid)");
      $ins->execute([':pid'=>(int)$project['id'], ':t'=>$token, ':exp'=>$expires, ':uid'=>(int)$userId]);
      $flash['ok'][] = 'Invite link created.';
    } catch (Throwable $e) { $flash['err'][] = 'Could not create invite.'; }
  }

  if ($act === 'revoke_invite' && isset($_POST['invite_id'])) {
    try {
      $upd = $pdo->prepare("UPDATE project_invites SET status='revoked' WHERE id=:id AND project_id=:pid");
      $upd->execute([':id'=>(int)$_POST['invite_id'], ':pid'=>(int)$project['id']]);
      $flash['ok'][] = 'Invite revoked.';
    } catch (Throwable $e) { $flash['err'][] = 'Could not revoke invite.'; }
  }

  if ($act === 'add_direct' && isset($_POST['user_add'])) {
    try {
      $uidAdd = (int)$_POST['user_add'];
      add_member_to_group($pdo, (int)$project['id'], $groups['uncat'], $uidAdd, 'deploy');
      $flash['ok'][] = 'User added to project.';
    } catch (Throwable $e) { $flash['err'][] = 'Could not add user.'; }
  }

  if ($act === 'create_group' && ($name = trim($_POST['group_name'] ?? '')) !== '') {
    try { get_or_create_group($pdo, (int)$project['id'], $name); $flash['ok'][] = 'Group created.'; }
    catch (Throwable $e) { $flash['err'][] = 'Could not create group.'; }
  }

  if ($act === 'move_member' && isset($_POST['member_uid'], $_POST['to_group'])) {
    try {
      $to = (int)$_POST['to_group'];
      $mem = (int)$_POST['member_uid'];
      add_member_to_group($pdo, (int)$project['id'], $to, $mem, 'deploy');
      $flash['ok'][] = 'Member moved.';
    } catch (Throwable $e) { $flash['err'][] = 'Could not move member.'; }
  }

  if ($act === 'set_role' && isset($_POST['member_uid'], $_POST['role'])) {
    $role = $_POST['role'] === 'control' ? 'control' : 'deploy';
    try {
      $upd = $pdo->prepare("UPDATE project_group_members SET role=:r WHERE project_id=:pid AND user_id=:uid");
      $upd->execute([':r'=>$role, ':pid'=>(int)$project['id'], ':uid'=>(int)$_POST['member_uid']]);
      $flash['ok'][] = 'Role updated.';
    } catch (Throwable $e) { $flash['err'][] = 'Could not update role.'; }
  }
}

// Data for rendering
$invites = list_invites($pdo, (int)$project['id']);
$orgUsers = is_project_manager($pdo, (int)$project['id'], (int)$userId, $project) ? org_users_for_manager($pdo, (int)$userId) : [];
$groupsList = list_groups_with_members($pdo, (int)$project['id']);

// Build invite join URL pattern (same page accept)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$joinBase = $baseUrl . dirname($_SERVER['REQUEST_URI']) . '/project_view.php?id=' . (int)$project['id'] . '&tab=members';
?>
<div class="ov-grid">

  <!-- Section 1: Invite + Direct add -->
  <section class="card" style="grid-column: span 2;">
    <h3 class="card-title">Invites & Direct Add</h3>

    <?php foreach ($flash['ok'] as $m): ?><div class="alert" style="background:#ecfdf5;color:#065f46;border-color:#a7f3d0"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>
    <?php foreach ($flash['err'] as $m): ?><div class="alert" style="background:#fef2f2;color:#991b1b;border-color:#fecaca"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>

    <?php if ($isManager): ?>
    <div class="form" style="margin-bottom:10px">
      <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="action" value="create_invite">
        <label for="ttl">Invite expiry</label>
        <select class="control" id="ttl" name="ttl" style="width:180px">
          <option value="15m">15 minutes</option>
          <option value="1h">1 hour</option>
          <option value="24h" selected>24 hours</option>
          <option value="7d">7 days</option>
          <option value="30d">30 days</option>
        </select>
        <button class="btn btn-primary" type="submit"><i class="fas fa-link"></i> Generate Link</button>
      </form>

      <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="action" value="add_direct">
        <label for="user_add">Add from your organization</label>
        <select class="control" id="user_add" name="user_add" style="min-width:260px">
          <?php if (!$orgUsers): ?><option value="">No colleagues detected</option><?php endif; ?>
          <?php foreach ($orgUsers as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?><?= !empty($u['email'])?' · '.htmlspecialchars($u['email']):'' ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><i class="fas fa-user-plus"></i> Add</button>
      </form>
    </div>
    <?php else: ?>
      <div class="muted">Only project managers can create invites or add members.</div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>Link</th><th>Expires</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($invites as $iv): ?>
          <?php $link = $joinBase . '&token=' . urlencode($iv['token']); ?>
          <tr>
            <td data-th="Link"><input class="control" style="width:100%" value="<?= htmlspecialchars($link) ?>" readonly></td>
            <td data-th="Expires"><?= htmlspecialchars($iv['expires_at']) ?></td>
            <td data-th="Status"><?= htmlspecialchars($iv['status']) ?></td>
            <td data-th="Action">
              <?php if ($isManager && $iv['status']==='active'): ?>
              <form method="post" onsubmit="return confirm('Revoke this invite?')" style="margin:0">
                <input type="hidden" name="action" value="revoke_invite">
                <input type="hidden" name="invite_id" value="<?= (int)$iv['id'] ?>">
                <button class="btn btn-ghost" type="submit"><i class="fas fa-times"></i> Revoke</button>
              </form>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$invites): ?>
          <tr><td colspan="4"><em>No invite links yet.</em></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($_GET['token'])): ?>
      <div class="card" style="margin-top:10px">
        <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0">
          <input type="hidden" name="action" value="accept_invite">
          <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">
          <span>Invite token detected.</span>
          <button class="btn btn-primary" type="submit"><i class="fas fa-door-open"></i> Join project</button>
        </form>
      </div>
    <?php endif; ?>
  </section>

  <!-- Section 2: Create group -->
  <section class="card">
    <h3 class="card-title">Create Group</h3>
    <?php if ($isManager): ?>
    <form method="post" class="form" style="margin:0;display:flex;gap:10px;align-items:center">
      <input type="hidden" name="action" value="create_group">
      <input class="control" name="group_name" type="text" placeholder="Enter group name" required>
      <button class="btn" type="submit"><i class="fas fa-plus"></i> Create</button>
    </form>
    <?php else: ?>
      <div class="muted">Only managers can create groups.</div>
    <?php endif; ?>
  </section>

  <!-- Section 3: Members by group -->
  <section class="card" style="grid-column: span 2;">
    <h3 class="card-title">Members</h3>
    <?php $groupsList = list_groups_with_members($pdo, (int)$project['id']); ?>
    <?php foreach ($groupsList as $g): ?>
      <div class="card" style="margin-bottom:10px">
        <div class="card-title"><?= htmlspecialchars($g['group']['group_name']) ?></div>
        <div class="table-responsive">
          <table class="table">
            <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Move To</th></tr></thead>
            <tbody>
              <?php foreach ($g['members'] as $m): ?>
                <tr>
                  <td data-th="User"><?= htmlspecialchars($m['name']) ?> (ID #<?= (int)$m['user_id'] ?>)</td>
                  <td data-th="Email"><?= htmlspecialchars($m['email'] ?? '—') ?></td>
                  <td data-th="Role">
                    <?php if ($isManager): ?>
                      <form method="post" style="display:flex;gap:6px;align-items:center;margin:0">
                        <input type="hidden" name="action" value="set_role">
                        <input type="hidden" name="member_uid" value="<?= (int)$m['user_id'] ?>">
                        <select class="control" name="role">
                          <option value="deploy" <?= ($m['role'] ?? '')==='deploy'?'selected':'' ?>>Triển khai</option>
                          <option value="control" <?= ($m['role'] ?? '')==='control'?'selected':'' ?>>Kiểm soát</option>
                        </select>
                        <button class="btn btn-ghost" type="submit">Save</button>
                      </form>
                    <?php else: ?>
                      <?= ($m['role'] ?? '')==='control' ? 'Kiểm soát' : 'Triển khai' ?>
                    <?php endif; ?>
                  </td>
                  <td data-th="Move To">
                    <?php if ($isManager): ?>
                      <form method="post" style="display:flex;gap:6px;align-items:center;margin:0">
                        <input type="hidden" name="action" value="move_member">
                        <input type="hidden" name="member_uid" value="<?= (int)$m['user_id'] ?>">
                        <select class="control" name="to_group">
                          <?php foreach ($groupsList as $gg): ?>
                            <option value="<?= (int)$gg['group']['group_id'] ?>" <?= $gg['group']['group_id']==$g['group']['group_id']?'selected':'' ?>>
                              <?= htmlspecialchars($gg['group']['group_name']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <button class="btn btn-ghost" type="submit">Move</button>
                      </form>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$g['members']): ?>
                <tr><td colspan="4"><em>No members in this group.</em></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

</div>
