<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = realpath(__DIR__ . '/..');

require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/permissions.php';
require_once $ROOT . '/includes/helpers.php';
require_once $ROOT . '/includes/projects.php';
require_once $ROOT . '/includes/files.php';

/** Ensure $pdo exists (fallback if config didn't set it) */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (function_exists('getPDO')) { $pdo = getPDO(); }
  elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.(defined('DB_CHARSET')?DB_CHARSET:'utf8mb4');
    $pdo = new PDO($dsn, DB_USER, defined('DB_PASS')?DB_PASS:'', [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } else { http_response_code(500); echo 'DB config missing.'; exit; }
}

/** Get current user id (requires login) */
function currentUserIdOrExit(): int {
  $cands = [
    $_SESSION['user_id'] ?? null,
    $_SESSION['id'] ?? null,
    $_SESSION['user']['id'] ?? null,
    $_SESSION['auth']['user_id'] ?? null,
    $_SESSION['auth']['id'] ?? null,
  ];
  foreach ($cands as $v) if (is_numeric($v) && (int)$v > 0) return (int)$v;
  header('Location: /index.php'); exit;
}

/** Subscription by users.subscription_id -> subscriptions.id */
function subscriptionFromUser(PDO $pdo, int $userId): ?array {
  try {
    // Read users.subscription_id
    $get = $pdo->prepare("SELECT subscription_id FROM users WHERE id=:uid LIMIT 1");
    $get->execute([':uid'=>$userId]);
    $sid = (int)($get->fetchColumn() ?: 0);
    if ($sid <= 0) return null;

    // Resolve to subscriptions row by id
    $stm = $pdo->prepare("SELECT * FROM subscriptions WHERE id=:sid LIMIT 1");
    $stm->execute([':sid'=>$sid]);
    $row = $stm->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  } catch (Throwable $e) {
    return null;
  }
}

/** Enforce limit from subscription.max_projects */
function projectLimitInfo(PDO $pdo, int $userId): array {
  $sub = subscriptionFromUser($pdo, $userId);
  $max = $sub && array_key_exists('max_projects', $sub) ? (int)$sub['max_projects'] : 0; // 0 = unlimited

  // Count how many projects this user has created
  $count = 0;
  try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE created_by=:uid");
    $cnt->execute([':uid'=>$userId]);
    $count = (int)$cnt->fetchColumn();
  } catch (Throwable $e) { $count = 0; }

  $reached = ($max > 0 && $count >= $max);
  return ['max'=>$max, 'count'=>$count, 'reached'=>$reached];
}

/** Resolve organization id for new project (best effort; optional in your schema) */
function resolveOrganizationId(PDO $pdo, int $userId): int {
  try {
    $stm = $pdo->prepare("SELECT organization_id FROM organization_members WHERE user_id=:uid ORDER BY organization_id LIMIT 1");
    $stm->execute([':uid'=>$userId]);
    $orgId = (int)($stm->fetchColumn() ?: 0);
    if ($orgId > 0) return $orgId;
  } catch (Throwable $e) {}
  try {
    $stm = $pdo->prepare("SELECT organization_id FROM users WHERE id=:uid");
    $stm->execute([':uid'=>$userId]);
    $orgId = (int)($stm->fetchColumn() ?: 0);
    if ($orgId > 0) return $orgId;
  } catch (Throwable $e) {}
  return 1;
}

$userId = currentUserIdOrExit();
$limit = projectLimitInfo($pdo, $userId);

/** Permission to create (always enabled per your request) */
$canCreate = true;
$createDisabled = (!$canCreate) || $limit['reached'];

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='create') {
  if ($createDisabled) {
    $errors[] = 'You have reached your project limit or lack permission to create.';
  } else {
    $name = trim($_POST['name'] ?? '');
    $start_date = $_POST['start_date'] ?? null; // Created Date
    $location = trim($_POST['location'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'completed' ? 'completed' : 'active';
    $tag = $_POST['tag'] ?? null;
    $description = $_POST['description'] ?? null;

    if ($name === '') { $errors[] = 'Project Name is required.'; }
    $orgId = resolveOrganizationId($pdo, $userId);

    if (!$errors) {
      $pid = createProject($pdo, $userId, $orgId, [
        'name'=>$name,
        'status'=>$status,
        'start_date'=>$start_date,
        'end_date'=>null,
        'manager_id'=>$userId,
        'visibility'=>'org',
        'description'=>$description,
        'location'=>$location,
        'tags'=>$tag
      ]);
      header('Location: ./project_view.php?id=' . $pid); exit;
    }
  }
}

$projects = listProjectsForUser($pdo, $userId);
$current = 'projects.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Projects</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/permissions.css">
  <link rel="stylesheet" href="../assets/css/projects.css">
  <link rel="stylesheet" href="../assets/css/sidebar.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
</head>
<body>
<?php if (is_file($ROOT . '/pages/sidebar.php')) require $ROOT . '/pages/sidebar.php'; ?>
<main class="container">
  <section class="card">
    <div class="header">
      <div>
        <div class="h-title"><i class="fas fa-folder-open"></i> Your Projects</div>
        <div class="muted">
          Total created by you: <strong><?= (int)$limit['count'] ?></strong>
          <?= $limit['max']>0 ? " / {$limit['max']} allowed" : " (unlimited by plan)" ?>
        </div>
      </div>
      <!-- Single Create button that submits the form below -->
      <button class="btn btn-primary" type="submit" form="createForm" <?= $createDisabled ? 'disabled title="Create disabled: plan limit reached"' : '' ?>>
        <i class="fas fa-plus-circle"></i> Create
      </button>
    </div>

    <?php if ($limit['reached']): ?>
      <div class="alert"><strong>Project limit reached.</strong> You have created <?= (int)$limit['count'] ?> projects, which equals the maximum allowed by your subscription.</div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert"><strong>Could not create project.</strong> <?= htmlspecialchars(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <!-- Create form (balanced layout). Only one submit button (in header) -->
    <form id="createForm" method="post" class="form" style="margin-top:8px">
      <input type="hidden" name="action" value="create">

      <div class="kv" style="grid-column:span 6;">
        <label for="name">Project Name<span style="color:#b91c1c">*</span></label>
        <input class="control" id="name" name="name" type="text" placeholder="Enter project name" required <?= $createDisabled?'disabled':'' ?>>
      </div>

      <div class="kv" style="grid-column:span 3;">
        <label for="start_date">Created Date</label>
        <input class="control" id="start_date" name="start_date" type="date" <?= $createDisabled?'disabled':'' ?>>
      </div>

      <div class="kv" style="grid-column:span 3;">
        <label for="status">Status</label>
        <select class="control" id="status" name="status" <?= $createDisabled?'disabled':'' ?>>
          <option value="active">Active</option>
          <option value="completed">Completed</option>
        </select>
      </div>

      <div class="kv" style="grid-column:span 6;">
        <label for="location">Location</label>
        <input class="control" id="location" name="location" type="text" placeholder="City, site, etc." <?= $createDisabled?'disabled':'' ?>>
      </div>

      <div class="kv" style="grid-column:span 6;">
        <label for="tag">Tag</label>
        <select class="control" id="tag" name="tag" <?= $createDisabled?'disabled':'' ?>>
          <option>Pre-Feasibility Study</option>
          <option>Technical–Economic Report</option>
          <option>Feasibility Study</option>
          <option>Technical Design</option>
          <option>Construction Drawings</option>
        </select>
      </div>

      <div class="kv" style="grid-column:span 12;">
        <label for="description">Description</label>
        <textarea class="control textarea" id="description" name="description" rows="4" placeholder="Describe the project..." <?= $createDisabled?'disabled':'' ?>></textarea>
      </div>
    </form>

    <div style="height:10px"></div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr><th>Code</th><th>Name</th><th>Status</th><th>Location</th><th>Tag</th><th>Owner</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
          <tr>
            <td data-th="Code"><?= htmlspecialchars($p['code']) ?></td>
            <td data-th="Name"><?= htmlspecialchars($p['name']) ?></td>
            <td data-th="Status"><?= htmlspecialchars($p['status']) ?></td>
            <td data-th="Location"><?= htmlspecialchars($p['location'] ?? '') ?></td>
            <td data-th="Tag"><?= htmlspecialchars($p['tags'] ?? '') ?></td>
            <td data-th="Owner">#<?= (int)$p['created_by'] ?></td>
            <td data-th="Action"><a class="btn btn-ghost" href="project_view.php?id=<?= (int)$p['id'] ?>"><i class="fas fa-eye"></i> Manage</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$projects): ?>
          <tr><td colspan="7"><em>No projects yet.</em></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
