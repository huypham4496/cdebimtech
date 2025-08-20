<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/**
 * pages/projects.php
 * - List: projects where current user is owner OR member
 * - Create: INSERT -> generate code PRJ00001 (id padded 5) -> UPDATE code -> mkdir uploads/{code}
 * - Edit: quick-edit modal (NO editing 'code')
 * - Delete: cascade via helpers::deleteProjectCascade()
 * - Manage: project_view.php?id=...&tab=overview
 */

$ROOT = dirname(__DIR__); // project root (siblings: index.php, config.php, includes/, pages/)
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/helpers.php';

/** Build PDO from config.php if not provided */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}

$userId = userIdOrRedirect();

/** Flash helpers (messages in English) */
function flash_get(string $k): ?string {
  if (!isset($_SESSION['flash'][$k])) return null;
  $v = (string)$_SESSION['flash'][$k];
  unset($_SESSION['flash'][$k]);
  return $v;
}
function flash_set(string $k, string $v): void { $_SESSION['flash'][$k] = $v; }

/** Generate project code like PRJ00001 (id padded to 5) */
function prj_code_from_id(int $id): string {
  return 'PRJ' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

/** Ensure uploads/{code} directory exists */
function ensure_project_upload_dir(string $code): void {
  // Default to document root; fallback to repo root
  $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), '/\\');
  $uploads = $docRoot . DIRECTORY_SEPARATOR . 'uploads';
  if (!is_dir($uploads)) { @mkdir($uploads, 0775, true); }
  $dir = $uploads . DIRECTORY_SEPARATOR . $code;
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
}

/** Create project: insert -> set code from id -> mkdir uploads/{code} */
function createProjectDynamic(PDO $pdo, int $userId, array $in): int {
  if (!cde_table_exists($pdo, 'projects')) {
    throw new RuntimeException('Table `projects` does not exist');
  }
  $name = trim((string)($in['name'] ?? ''));
  if ($name === '') { throw new InvalidArgumentException('Project name is required'); }

  $cols = [];
  $vals = [];
  $params = [];

  // Insert only columns that exist (NOTE: we DO NOT set 'code' here)
  $try = [
    'name'        => $name,
    'status'      => (string)($in['status'] ?? 'active'),
    'start_date'  => ($in['start_date'] ?? null) ?: null,
    'end_date'    => ($in['end_date'] ?? null) ?: null,
    'visibility'  => (string)($in['visibility'] ?? 'org'),
    'location'    => (string)($in['location'] ?? ''),
    'tags'        => (string)($in['tags'] ?? ''), // selected tag
    'description' => (string)($in['description'] ?? ''),
  ];

  foreach ($try as $col => $val) {
    if (!cde_column_exists($pdo, 'projects', $col)) continue;
    $cols[] = "`$col`";
    $vals[] = ":$col";
    $params[":$col"] = ($val === '') ? null : $val;
  }

  // created_by / manager_id / created_at / updated_at (if present)
  if (cde_column_exists($pdo, 'projects', 'created_by')) { $cols[] = "`created_by`"; $vals[] = ":created_by"; $params[':created_by'] = $userId; }
  if (cde_column_exists($pdo, 'projects', 'manager_id')) { $cols[] = "`manager_id`"; $vals[] = ":manager_id"; $params[':manager_id'] = $userId; }
  $now = date('Y-m-d H:i:s');
  if (cde_column_exists($pdo, 'projects', 'created_at')) { $cols[] = "`created_at`"; $vals[] = ":created_at"; $params[':created_at'] = $now; }
  if (cde_column_exists($pdo, 'projects', 'updated_at')) { $cols[] = "`updated_at`"; $vals[] = ":updated_at"; $params[':updated_at'] = $now; }

  if (!$cols) { throw new RuntimeException('No valid columns to INSERT'); }

  // 1) INSERT (without code)
  $sql = "INSERT INTO `projects` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
  $pdo->prepare($sql)->execute($params);
  $newId = (int)$pdo->lastInsertId();

  // 2) Generate code PRJ00001 from id
  $code = prj_code_from_id($newId);

  // 3) UPDATE code (if column exists)
  if (cde_column_exists($pdo, 'projects', 'code')) {
    $pdo->prepare("UPDATE `projects` SET `code` = :code WHERE id = :id")->execute([':code'=>$code, ':id'=>$newId]);
  }

  // 4) Create uploads/{code}
  try { ensure_project_upload_dir($code); } catch (Throwable $e) { /* ignore folder errors */ }

  // 5) Log
  try { addActivity($pdo, $newId, $userId, 'create', 'Create via list modal'); } catch (Throwable $e) {}

  return $newId;
}

/* =========================================================
 * ================== POST HANDLERS: START =================
 * =======================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // CREATE
  if ($action === 'create') {
    try {
      $newId = createProjectDynamic($pdo, $userId, $_POST);
      header('Location: project_view.php?id=' . $newId . '&tab=overview');
      exit;
    } catch (Throwable $e) {
      flash_set('err', 'Could not create project: ' . $e->getMessage());
      header('Location: ' . $_SERVER['REQUEST_URI']); exit;
    }
  }

  // UPDATE (quick edit) — DO NOT update 'code'
  if ($action === 'update') {
    $pid = (int)($_POST['project_id'] ?? 0);
    if ($pid <= 0) { flash_set('err','Missing project_id'); header('Location: '.$_SERVER['REQUEST_URI']); exit; }

    // Permission: owner or manager only
    $chk = $pdo->prepare("SELECT id FROM projects WHERE id=:pid AND (created_by=:uid OR manager_id=:uid) LIMIT 1");
    $chk->execute([':pid'=>$pid, ':uid'=>$userId]);
    if (!$chk->fetchColumn()) {
      flash_set('err',"You don't have permission to edit this project.");
      header('Location: '.$_SERVER['REQUEST_URI']); exit;
    }

    $allowed = ['name','status','start_date','end_date','visibility','location','tags','description'];
    $set = [];
    $params = [':id'=>$pid];
    foreach ($allowed as $col) {
      if (array_key_exists($col, $_POST) && cde_column_exists($pdo,'projects',$col)) {
        $set[] = "`$col` = :$col";
        $params[":$col"] = ($_POST[$col] === '') ? null : trim((string)$_POST[$col]);
      }
    }
    if (cde_column_exists($pdo,'projects','updated_at')) { $set[] = "updated_at = NOW()"; }

    if ($set) {
      $sql = "UPDATE projects SET ".implode(',', $set)." WHERE id=:id";
      $pdo->prepare($sql)->execute($params);
      try { addActivity($pdo, $pid, $userId, 'update', 'Quick edit'); } catch (Throwable $e) {}
      flash_set('ok','Changes saved.');
    } else {
      flash_set('err','No fields to update.');
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  // DELETE (owner-only)
  if ($action === 'delete') {
    $pid = (int)($_POST['project_id'] ?? 0);
    if ($pid <= 0) { flash_set('err','Missing project_id'); header('Location: '.$_SERVER['REQUEST_URI']); exit; }

    $own = $pdo->prepare("SELECT created_by FROM projects WHERE id=:pid");
    $own->execute([':pid'=>$pid]);
    $ownerId = (int)($own->fetchColumn() ?: 0);
    if ($ownerId !== $userId) {
      flash_set('err',"You don't have permission to delete this project.");
      header('Location: '.$_SERVER['REQUEST_URI']); exit;
    }

    try {
      deleteProjectCascade($pdo, $pid, true);
      flash_set('ok', 'Project and related data deleted.');
    } catch (Throwable $e) {
      flash_set('err', 'Delete failed: ' . $e->getMessage());
    }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
  }
}
/* =========================================================
 * =================== POST HANDLERS: END ==================
 * =======================================================*/

/** Fetch list: owner OR member */
$sql = "
  SELECT DISTINCT p.*
  FROM projects p
  LEFT JOIN project_group_members gm ON gm.project_id = p.id AND gm.user_id = :uid
  WHERE p.created_by = :uid OR gm.user_id IS NOT NULL
  ORDER BY ".(cde_column_exists($pdo,'projects','updated_at') ? 'p.updated_at DESC,' : '')." p.id DESC
";
$stm = $pdo->prepare($sql);
$stm->execute([':uid'=>$userId]);
$projects = $stm->fetchAll(PDO::FETCH_ASSOC) ?: [];

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Projects</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/fonts/font_inter.css?v=<?php echo filemtime('../assets/fonts/font_inter.css'); ?>">
  <link rel="stylesheet" href="../assets/css/all.min.css?v=<?php echo filemtime('../assets/css/all.min.css'); ?>">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=<?php echo filemtime('../assets/css/sidebar.css'); ?>">
  <link rel="stylesheet" href="../assets/css/projects.css?v=<?php echo filemtime('../assets/css/projects.css'); ?>">
</head>
<body>

<?php
// include sidebar if present (pages/sidebar.php)
$sidebar = __DIR__ . '/sidebar.php';
if (is_file($sidebar)) include $sidebar;
?>

<div class="pv-container with-sidebar">
  <header class="pv-header">
    <div class="pv-title">
      <h1>Project Management</h1>
      <div class="pv-meta">
        <?php if ($m = flash_get('ok')): ?>
          <span class="status-badge active"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($m) ?></span>
        <?php endif; ?>
        <?php if ($m = flash_get('err')): ?>
          <span class="status-badge"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($m) ?></span>
        <?php endif; ?>
      </div>
      <div>
        <button type="button" class="btn btn-primary" id="btnOpenCreate"><i class="fas fa-plus-circle"></i> New Project</button>
      </div>
    </div>
  </header>

  <nav class="pv-tabs">
    <a class="pv-tab" href="#"><i class="fas fa-list"></i> List</a>
    <span class="pv-tab-indicator" style="width:90px;transform:translateX(0)"></span>
  </nav>

  <main class="pv-content">
    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Name</th>
            <th>Code</th>
            <th>Status</th>
            <th>Location</th>
            <th>Tags</th>
            <th style="width:300px">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
          <tr data-project='<?= json_encode($p, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>'>
            <td>#<?= (int)$p['id'] ?></td>
            <td><?= htmlspecialchars($p['name'] ?? '') ?></td>
            <td><?= htmlspecialchars($p['code'] ?? '') ?></td>
            <td><?= htmlspecialchars($p['status'] ?? '') ?></td>
            <td><?= htmlspecialchars($p['location'] ?? '') ?></td>
            <td><?= htmlspecialchars($p['tags'] ?? '') ?></td>
            <td>
              <div class="actions">
                <!-- Manage: open Overview tab -->
                <a class="btn btn-ghost" href="project_view.php?id=<?= (int)$p['id'] ?>&tab=overview">
                  <i class="fas fa-sitemap"></i> Manage
                </a>

                <!-- Edit: quick modal -->
                <button type="button" class="btn js-edit"><i class="fas fa-pen-square"></i> Edit</button>

                <!-- Delete -->
                <form method="post" onsubmit="return confirm('Delete this project and all related data?');" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-danger" type="submit"><i class="fas fa-trash"></i> Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$projects): ?>
          <tr><td colspan="7"><em>No projects yet.</em></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- Modal: Create (NO "Code" field) -->
<div class="modal" id="createModal" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post">
      <div class="modal-header">
        <strong><i class="fas fa-plus-circle"></i> Create Project</strong>
        <button type="button" class="btn btn-ghost" data-close>Close</button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="create">
        <div class="grid">
          <label class="kv">
            <span>Project name <span style="color:#b91c1c">*</span></span>
            <input class="control" name="name" id="cr_name" required>
          </label>

          <label class="kv">
            <span>Status</span>
            <select class="control" name="status" id="cr_status">
              <option value="active">active</option>
              <option value="completed">completed</option>
            </select>
          </label>

          <label class="kv">
            <span>Visibility</span>
            <input class="control" name="visibility" id="cr_visibility" placeholder="org/public/private" value="org">
          </label>

          <label class="kv">
            <span>Start date</span>
            <input class="control" type="date" name="start_date" id="cr_start">
          </label>

          <label class="kv">
            <span>End date</span>
            <input class="control" type="date" name="end_date" id="cr_end">
          </label>

          <label class="kv">
            <span>Location</span>
            <input class="control" name="location" id="cr_location">
          </label>

          <label class="kv">
            <span>Tags</span>
            <select class="control" name="tags" id="cr_tags">
              <option value="">-- Select --</option>
              <option>Pre-Feasibility Study</option>
              <option>Technical–Economic Report</option>
              <option>Feasibility Study</option>
              <option>Technical Design</option>
              <option>Construction Drawings</option>
            </select>
          </label>
        </div>

        <label class="kv" style="margin-top:10px">
          <span>Description</span>
          <textarea class="control" rows="3" name="description" id="cr_desc"></textarea>
        </label>
      </div>
      <div class="modal-footer">
        <button class="btn" type="submit"><i class="fas fa-save"></i> Create</button>
        <button class="btn btn-ghost" type="button" data-close>Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Edit (NO "Code" field) -->
<div class="modal" id="editModal" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post">
      <div class="modal-header">
        <strong><i class="fas fa-pen-square"></i> Edit Project</strong>
        <button type="button" class="btn btn-ghost" data-close>Close</button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="project_id" id="qe_id">
        <div class="grid">
          <label class="kv">
            <span>Project name</span>
            <input class="control" name="name" id="qe_name" required>
          </label>

          <label class="kv">
            <span>Status</span>
            <select class="control" name="status" id="qe_status">
              <option value="active">active</option>
              <option value="completed">completed</option>
            </select>
          </label>

          <label class="kv">
            <span>Visibility</span>
            <input class="control" name="visibility" id="qe_visibility" placeholder="public/private">
          </label>

          <label class="kv">
            <span>Start date</span>
            <input class="control" type="date" name="start_date" id="qe_start">
          </label>

          <label class="kv">
            <span>End date</span>
            <input class="control" type="date" name="end_date" id="qe_end">
          </label>

          <label class="kv">
            <span>Location</span>
            <input class="control" name="location" id="qe_location">
          </label>

          <label class="kv">
            <span>Tags</span>
            <select class="control" name="tags" id="qe_tags">
              <option value="">-- Select --</option>
              <option>Pre-Feasibility Study</option>
              <option>Technical–Economic Report</option>
              <option>Feasibility Study</option>
              <option>Technical Design</option>
              <option>Construction Drawings</option>
            </select>
          </label>
        </div>

        <label class="kv" style="margin-top:10px">
          <span>Description</span>
          <textarea class="control" rows="3" name="description" id="qe_desc"></textarea>
        </label>
      </div>
      <div class="modal-footer">
        <button class="btn" type="submit"><i class="fas fa-save"></i> Save</button>
        <button class="btn btn-ghost" type="button" data-close>Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// Modal helpers + fill Edit
(function(){
  function open(modal){ modal.classList.add('show'); modal.setAttribute('aria-hidden','false'); }
  function close(modal){ modal.classList.remove('show'); modal.setAttribute('aria-hidden','true'); }

  // Create
  const createModal = document.getElementById('createModal');
  const btnOpenCreate = document.getElementById('btnOpenCreate');
  btnOpenCreate?.addEventListener('click', ()=> open(createModal));
  createModal?.querySelectorAll('[data-close]').forEach(b=> b.addEventListener('click', ()=> close(createModal)));

  // Edit
  const editModal = document.getElementById('editModal');
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-edit'); if (!btn) return;
    const tr = btn.closest('tr'); if (!tr) return;
    const data = JSON.parse(tr.getAttribute('data-project') || '{}');

    // Fill fields (NO 'code')
    document.getElementById('qe_id').value = data.id || '';
    document.getElementById('qe_name').value = data.name || '';
    document.getElementById('qe_status').value = (data.status === 'completed' ? 'completed' : 'active');
    document.getElementById('qe_visibility').value = data.visibility || '';
    document.getElementById('qe_start').value = (data.start_date||'').slice(0,10);
    document.getElementById('qe_end').value   = (data.end_date||'').slice(0,10);
    document.getElementById('qe_location').value = data.location || '';

    // Tags select — set the exact value if matches; otherwise leave as default
    const tagVal = (data.tags || '').trim();
    const tagSelect = document.getElementById('qe_tags');
    if ([...tagSelect.options].some(o => o.value === tagVal || o.text === tagVal)) {
      tagSelect.value = tagVal;
    } else {
      tagSelect.value = '';
    }

    document.getElementById('qe_desc').value = data.description || '';
    open(editModal);
  });
  editModal?.querySelectorAll('[data-close]').forEach(b=> b.addEventListener('click', ()=> close(editModal)));
})();
</script>

</body>
</html>
