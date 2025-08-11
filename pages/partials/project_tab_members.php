<?php
/**
 * pages/partials/project_tab_members.php
 * - Responsive auto-resize (minmax grids, no horizontal scroll)
 * - Sidebar always above content (z-index), width respected (250px)
 * - "Invites & Direct Add" refined layout (two-column forms + separate Links table)
 * - Group names display: MANAGER / UNCATEGORIZED (default groups are locked from deletion)
 * - English labels (Deploy / Control)
 * - Single "Update" button for Role + Group
 * - "Remove" member button
 * - "Delete Group" for non-default, empty groups
 * - Invite links multi-use until revoked; expired links still show Revoke
 * - All buttons use .btn .btn-primary (use your global button styles)
 */

if (!isset($pdo) || !isset($project) || !isset($userId)) { echo '<div class="alert">Context missing.</div>'; return; }

// Load dedicated CSS for this tab
echo '<link rel="stylesheet" href="../assets/css/projects_members.css">';

// ---------- helpers (local) ----------
function cde_table_exists_local(PDO $pdo, string $table): bool {
  try { $stm = $pdo->prepare("SHOW TABLES LIKE :t"); $stm->execute([':t'=>$table]); return (bool)$stm->fetchColumn(); }
  catch (Throwable $e) { return false; }
}
function cde_column_exists_local(PDO $pdo, string $table, string $col): bool {
  try { $stm = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c"); $stm->execute([':c'=>$col]); return (bool)$stm->fetch(PDO::FETCH_ASSOC); }
  catch (Throwable $e) { return false; }
}
function users_name_email_expr(PDO $pdo): array {
  $nameExpr = null;
  $hasFirst = cde_column_exists_local($pdo, 'users','first_name');
  $hasLast  = cde_column_exists_local($pdo, 'users','last_name');
  if ($hasFirst || $hasLast) {
    $first = $hasFirst ? "u.first_name" : "''";
    $last  = $hasLast  ? "u.last_name"  : "''";
    $nameExpr = "NULLIF(TRIM(CONCAT($first, ' ', $last)), '')";
  }
  foreach (['name','full_name','fullname','display_name','username'] as $c) {
    if (cde_column_exists_local($pdo, 'users', $c)) {
      $col = "u.`$c`";
      $nameExpr = $nameExpr ? "COALESCE($nameExpr, NULLIF($col,''))" : "NULLIF($col,'')";
    }
  }
  if (!$nameExpr) { $nameExpr = "NULL"; }
  $nameExpr = "COALESCE($nameExpr, CONCAT('User #', u.id)) AS name";

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
  // Mark expired (still revocable in UI)
  $pdo->prepare("UPDATE project_invites SET status='expired' WHERE status='active' AND expires_at < :now")->execute([':now'=>$now]);
  $stm = $pdo->prepare("SELECT * FROM project_invites WHERE project_id=:pid ORDER BY id DESC");
  $stm->execute([':pid'=>$projectId]);
  return $stm->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
function display_group_name(string $name): string {
  $lower = mb_strtolower($name, 'UTF-8');
  if ($lower === 'manager') return 'MANAGER';
  if ($lower === 'chưa phân loại' || $lower === 'chua phan loai') return 'UNCATEGORIZED';
  return $name;
}
function is_default_group(string $name): bool {
  $lower = mb_strtolower($name, 'UTF-8');
  return $lower === 'manager' || $lower === 'chưa phân loại' || $lower === 'chua phan loai';
}

// Ensure tables + default groups + owner in manager
ensure_members_tables($pdo);
$groups = ensure_default_groups($pdo, (int)$project['id']);
add_member_to_group($pdo, (int)$project['id'], $groups['manager'], (int)$project['created_by'], 'control');

$isManager = is_project_manager($pdo, (int)$project['id'], (int)$userId, $project);
$flash = ['ok'=>[], 'err'=>[]];

// Accept invite (multi-use until revoked; expired cannot be used)
if (($_POST['action'] ?? '') === 'accept_invite' && isset($_POST['token'])) {
  $token = trim($_POST['token']);
  try {
    $stm = $pdo->prepare("SELECT * FROM project_invites WHERE token=:t AND project_id=:pid LIMIT 1");
    $stm->execute([':t'=>$token, ':pid'=>(int)$project['id']]);
    $row = $stm->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $flash['err'][] = 'Invite not found.'; }
    else if ($row['status'] === 'revoked') { $flash['err'][] = 'Invite has been revoked.'; }
    else if (strtotime($row['expires_at']) < time()) { $flash['err'][] = 'Invite has expired.'; }
    else {
      add_member_to_group($pdo, (int)$project['id'], $groups['uncat'], (int)$userId, 'deploy');
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

  if ($act === 'update_member' && isset($_POST['member_uid'])) {
    try {
      $mem = (int)$_POST['member_uid'];
      $role = ($_POST['role'] ?? '') === 'control' ? 'control' : 'deploy';
      $toGroup = (int)($_POST['to_group'] ?? 0);
      if ($toGroup > 0) add_member_to_group($pdo, (int)$project['id'], $toGroup, $mem, $role);
      else {
        $upd = $pdo->prepare("UPDATE project_group_members SET role=:r WHERE project_id=:pid AND user_id=:uid");
        $upd->execute([':r'=>$role, ':pid'=>(int)$project['id'], ':uid'=>$mem]);
      }
      $flash['ok'][] = 'Member updated.';
    } catch (Throwable $e) { $flash['err'][] = 'Could not update member.'; }
  }

  if ($act === 'remove_member' && isset($_POST['member_uid'])) {
    try {
      $mem = (int)$_POST['member_uid'];
      $del = $pdo->prepare("DELETE FROM project_group_members WHERE project_id=:pid AND user_id=:uid");
      $del->execute([':pid'=>(int)$project['id'], ':uid'=>$mem]);
      $flash['ok'][] = 'Member removed from project.';
    } catch (Throwable $e) { $flash['err'][] = 'Could not remove member.'; }
  }

  if ($act === 'delete_group' && isset($_POST['group_id'])) {
    try {
      // Check group and members
      $gid = (int)$_POST['group_id'];
      $stm = $pdo->prepare("SELECT name FROM project_groups WHERE id=:gid AND project_id=:pid");
      $stm->execute([':gid'=>$gid, ':pid'=>(int)$project['id']]);
      $gname = $stm->fetchColumn();
      if (!$gname) throw new Exception('Group not found');
      if (is_default_group($gname)) throw new Exception('Default groups cannot be deleted.');
      $cnt = $pdo->prepare("SELECT COUNT(*) FROM project_group_members WHERE project_id=:pid AND group_id=:gid");
      $cnt->execute([':pid'=>(int)$project['id'], ':gid'=>$gid]);
      if ((int)$cnt->fetchColumn() > 0) throw new Exception('Group is not empty. Move members first.');
      $del = $pdo->prepare("DELETE FROM project_groups WHERE id=:gid AND project_id=:pid");
      $del->execute([':gid'=>$gid, ':pid'=>(int)$project['id']]);
      $flash['ok'][] = 'Group deleted.';
    } catch (Throwable $e) { $flash['err'][] = $e->getMessage() ?: 'Could not delete group.'; }
  }
}

// Data for rendering
$invites = list_invites($pdo, (int)$project['id']);
$orgUsers = $isManager ? org_users_for_manager($pdo, (int)$userId) : [];
$groupsList = list_groups_with_members($pdo, (int)$project['id']);

// Build invite join URL pattern (same page accept)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$joinBase = $baseUrl . dirname($_SERVER['REQUEST_URI']) . '/project_view.php?id=' . (int)$project['id'] . '&tab=members';
?>
<div class="tab-members">

  <!-- Section 1: Invites & Direct add -->
  <div class="section">
    <div class="title">Invites & Direct Add</div>

    <?php foreach ($flash['ok'] as $m): ?><div class="alert" style="background:#ecfdf5;color:#065f46;border-color:#a7f3d0"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>
    <?php foreach ($flash['err'] as $m): ?><div class="alert" style="background:#fef2f2;color:#991b1b;border-color:#fecaca"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>

    <?php if ($isManager): ?>
    <div class="grid-split-2" style="margin-bottom:12px">
      <form method="post" class="row">
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

      <form method="post" class="row">
        <input type="hidden" name="action" value="add_direct">
        <label for="user_add">Add from your organization</label>
        <select class="control" id="user_add" name="user_add" style="min-width:240px">
          <?php if (!$orgUsers): ?><option value="">No colleagues detected</option><?php endif; ?>
          <?php foreach ($orgUsers as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?><?= !empty($u['email'])?' · '.htmlspecialchars($u['email']):'' ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit"><i class="fas fa-user-plus"></i> Add</button>
      </form>
    </div>
    <?php else: ?>
      <div class="muted">Only project managers can create invites or add members.</div>
    <?php endif; ?>

    <!-- Links list -->
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>Link</th><th>Expires</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($invites as $iv): ?>
          <?php $link = $joinBase . '&token=' . urlencode($iv['token']); ?>
          <tr class="invite-item">
            <td data-th="Link"><input class="control" value="<?= htmlspecialchars($link) ?>" readonly></td>
            <td data-th="Expires"><?= htmlspecialchars($iv['expires_at']) ?></td>
            <td data-th="Status"><?= htmlspecialchars($iv['status']) ?></td>
            <td data-th="Action">
              <?php if ($isManager && $iv['status'] !== 'revoked'): ?>
              <form method="post" onsubmit="return confirm('Revoke this invite?')" style="margin:0; display:inline">
                <input type="hidden" name="action" value="revoke_invite">
                <input type="hidden" name="invite_id" value="<?= (int)$iv['id'] ?>">
                <button class="btn btn-primary" type="submit"><i class="fas fa-times"></i> Revoke</button>
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
      <div class="section" style="margin-top:10px">
        <form method="post" class="row" style="margin:0">
          <input type="hidden" name="action" value="accept_invite">
          <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">
          <span>Invite token detected.</span>
          <button class="btn btn-primary" type="submit"><i class="fas fa-door-open"></i> Join project</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <!-- Row: Create group + Members -->
  <div class="grid-2">

    <!-- Section 2: Create group -->
    <div class="section">
      <div class="title">Create Group</div>
      <?php if ($isManager): ?>
      <form method="post" class="row" style="margin:0">
        <input type="hidden" name="action" value="create_group">
        <input class="control" name="group_name" type="text" placeholder="Enter group name" required>
        <button class="btn btn-primary" type="submit"><i class="fas fa-plus"></i> Create</button>
      </form>
      <?php else: ?>
        <div class="muted">Only managers can create groups.</div>
      <?php endif; ?>
    </div>

    <!-- Section 3: Members by group -->
    <div class="section">
      <div class="title">Members</div>
      <?php $groupsList = list_groups_with_members($pdo, (int)$project['id']); ?>
      <?php foreach ($groupsList as $g): ?>
        <?php $isDefault = is_default_group($g['group']['group_name']); ?>
        <div class="group-block" style="margin-bottom:10px">
          <div class="group-name">
            <span><?= htmlspecialchars(display_group_name($g['group']['group_name'])) ?></span>
            <?php if ($isManager && !$isDefault && empty($g['members'])): ?>
              <form method="post" style="margin:0" onsubmit="return confirm('Delete this group?')">
                <input type="hidden" name="action" value="delete_group">
                <input type="hidden" name="group_id" value="<?= (int)$g['group']['group_id'] ?>">
                <button class="btn btn-primary" type="submit"><i class="fas fa-trash"></i> Delete Group</button>
              </form>
            <?php endif; ?>
          </div>
          <div class="table-responsive" style="margin-top:8px">
            <table class="table">
              <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Group</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($g['members'] as $m): ?>
                  <tr>
                    <td data-th="User"><?= htmlspecialchars($m['name']) ?> (ID #<?= (int)$m['user_id'] ?>)</td>
                    <td data-th="Email"><?= htmlspecialchars($m['email'] ?? '—') ?></td>
                    <td data-th="Role">
                      <?php if ($isManager): ?>
                        <form method="post" class="row" style="margin:0">
                          <input type="hidden" name="member_uid" value="<?= (int)$m['user_id'] ?>">
                          <input type="hidden" name="action" value="update_member">
                          <select class="control" name="role">
                            <option value="deploy" <?= ($m['role'] ?? '')==='deploy'?'selected':'' ?>>Deploy</option>
                            <option value="control" <?= ($m['role'] ?? '')==='control'?'selected':'' ?>>Control</option>
                          </select>
                      <?php else: ?>
                        <span class="badge"><?= ($m['role'] ?? '')==='control' ? 'Control' : 'Deploy' ?></span>
                      <?php endif; ?>
                    </td>
                    <td data-th="Group">
                      <?php if ($isManager): ?>
                          <select class="control" name="to_group">
                            <?php foreach ($groupsList as $gg): ?>
                              <option value="<?= (int)$gg['group']['group_id'] ?>" <?= $gg['group']['group_id']==$g['group']['group_id']?'selected':'' ?>>
                                <?= htmlspecialchars(display_group_name($gg['group']['group_name'])) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                      <?php else: ?>
                        <?= htmlspecialchars(display_group_name($g['group']['group_name'])) ?>
                      <?php endif; ?>
                    </td>
                    <td data-th="Actions">
                      <?php if ($isManager): ?>
                          <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Update</button>
                        </form>
                        <form method="post" style="display:inline" onsubmit="return confirm('Remove this member from project?')">
                          <input type="hidden" name="action" value="remove_member">
                          <input type="hidden" name="member_uid" value="<?= (int)$m['user_id'] ?>">
                          <button class="btn btn-primary" type="submit"><i class="fas fa-user-minus"></i> Remove</button>
                        </form>
                      <?php else: ?>
                        —
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$g['members']): ?>
                  <tr><td colspan="5"><em>No members in this group.</em></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>
