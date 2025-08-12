<?php
// pages/partials/project_tab_colors.php
// Purpose: Colors tab for a project. View-only for regular members; CRUD for project managers.
// Notes:
//  - This file serves both as an AJAX endpoint (POST + action) and as an HTML partial (when included from project_view.php).
//  - Messages and comments are in English per request.

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/** Safe JSON output helper */
function json_out($arr, int $code = 200): void {
  if (!headers_sent()) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

/** Detect AJAX calls */
$isAjax = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']));

/* --------------------------------------------------------------------------
   AJAX BOOTSTRAP: reconstruct $pdo, $userId, $project, $projectId if not provided
----------------------------------------------------------------------------- */
if ($isAjax) {
  $ROOT = realpath(__DIR__ . '/../..'); // from /pages/partials up to project root

  // Try to load the same includes as project_view.php would
  if (is_file($ROOT . '/config.php'))                require_once $ROOT . '/config.php';
  if (is_file($ROOT . '/includes/permissions.php'))  require_once $ROOT . '/includes/permissions.php';
  if (is_file($ROOT . '/includes/helpers.php'))      require_once $ROOT . '/includes/helpers.php';
  if (is_file($ROOT . '/includes/projects.php'))     require_once $ROOT . '/includes/projects.php';
  if (is_file($ROOT . '/includes/files.php'))        require_once $ROOT . '/includes/files.php';

  // Ensure $pdo is available
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('getPDO')) {
      $pdo = getPDO();
    } elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
      $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
      try {
        $pdo = new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '', [
          PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
      } catch (Throwable $e) {
        json_out(['ok' => false, 'msg' => 'Cannot connect to database: ' . $e->getMessage()], 500);
      }
    } else {
      json_out(['ok' => false, 'msg' => 'Database config is missing (DB_HOST/DB_NAME/DB_USER).'], 500);
    }
  }

  // Resolve userId from session (robust)
  $userId = 0;
  $candidates = [
    $_SESSION['user_id'] ?? null,
    $_SESSION['id'] ?? null,
    $_SESSION['auth']['user_id'] ?? null,
    $_SESSION['auth']['id'] ?? null,
    $_SESSION['user']['id'] ?? null,
    $_SESSION['user']['user_id'] ?? null,
    $_SESSION['account']['id'] ?? null,
    $_SESSION['member']['id'] ?? null,
    $_SESSION['login']['id'] ?? null,
    $_SESSION['profile']['id'] ?? null,
    $_SESSION['uid'] ?? null,
  ];
  foreach ($candidates as $v) {
    if (is_numeric($v) && (int)$v > 0) { $userId = (int)$v; break; }
  }
  if ($userId <= 0) {
    json_out(['ok' => false, 'msg' => 'You are not logged in.'], 401);
  }

  // Resolve projectId from POST/GET and load project
  $projectId = (int)($_POST['project_id'] ?? 0);
  if ($projectId <= 0) { $projectId = (int)($_GET['project_id'] ?? 0); }
  if ($projectId <= 0) {
    json_out(['ok' => false, 'msg' => 'Missing project_id.'], 422);
  }

  $stm = $pdo->prepare("SELECT * FROM projects WHERE id = :id LIMIT 1");
  $stm->execute([':id' => $projectId]);
  $project = $stm->fetch(PDO::FETCH_ASSOC);
  if (!$project) {
    json_out(['ok' => false, 'msg' => 'Project not found.'], 404);
  }
}
/* --------------------------------------------------------------------------
   HTML PARTIAL: included inside project_view.php with $pdo, $project, $userId
----------------------------------------------------------------------------- */
else {
  if (!isset($pdo) || !isset($project) || !isset($userId)) {
    echo '<div class="alert">Colors tab missing context.</div>';
    return;
  }
  $projectId = (int)($project['id'] ?? 0);
  $userId    = (int)$userId;
}

/* --------------------------------------------------------------------------
   Helpers
----------------------------------------------------------------------------- */
function is_project_manager(PDO $pdo, int $projectId, int $userId, array $project): bool {
  // Owner has full permission
  if ((int)($project['created_by'] ?? 0) === $userId) return true;

  // Members in group "manager" or group_id = 1 have manage permission
  $sql = "SELECT 1
          FROM project_group_members pgm
          JOIN project_groups pg ON pg.id = pgm.group_id
          WHERE pgm.project_id = :pid
            AND pgm.user_id = :uid
            AND (pg.name = 'manager' OR pgm.group_id = 1)
          LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([':pid' => $projectId, ':uid' => $userId]);
  return (bool)$st->fetchColumn();
}

function ensure_group_belongs(PDO $pdo, int $groupId, int $projectId): void {
  $st = $pdo->prepare("SELECT id FROM project_color_groups WHERE id = :gid AND project_id = :pid");
  $st->execute([':gid' => $groupId, ':pid' => $projectId]);
  if (!$st->fetchColumn()) {
    json_out(['ok' => false, 'msg' => 'Color group does not belong to the project.'], 404);
  }
}

function get_item_group_if_belongs(PDO $pdo, int $itemId, int $projectId): int {
  $sql = "SELECT i.group_id
          FROM project_color_items i
          JOIN project_color_groups g ON g.id = i.group_id
          WHERE i.id = :iid AND g.project_id = :pid";
  $st = $pdo->prepare($sql);
  $st->execute([':iid' => $itemId, ':pid' => $projectId]);
  $gid = (int)$st->fetchColumn();
  if (!$gid) {
    json_out(['ok' => false, 'msg' => 'Color item does not belong to the project.'], 404);
  }
  return $gid;
}

$canManage = is_project_manager($pdo, $projectId, $userId, $project);

/* --------------------------------------------------------------------------
   AJAX API
----------------------------------------------------------------------------- */
if ($isAjax) {
  $action = $_POST['action'] ?? '';

  $mutating = ['add_group','delete_group','add_item','update_item','delete_item','reorder_items'];
  if (in_array($action, $mutating, true) && !$canManage) {
    json_out(['ok' => false, 'msg' => 'You do not have permission to perform this action.'], 403);
  }

  try {
    if ($action === 'list') {
      // Fetch groups
      $st = $pdo->prepare("SELECT id, name, created_by, created_at
                           FROM project_color_groups
                           WHERE project_id = :pid
                           ORDER BY id ASC");
      $st->execute([':pid' => $projectId]);
      $groups = $st->fetchAll(PDO::FETCH_ASSOC);

      // Fetch items by group
      $itemsByGroup = [];
      if ($groups) {
        $ids = array_column($groups, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stm = $pdo->prepare("SELECT id, group_id, label, hex_color, sort_order
                              FROM project_color_items
                              WHERE group_id IN ($in)
                              ORDER BY sort_order ASC, id ASC");
        $stm->execute($ids);
        while ($row = $stm->fetch(PDO::FETCH_ASSOC)) {
          $itemsByGroup[(int)$row['group_id']][] = $row;
        }
      }

      json_out(['ok' => true, 'data' => [
        'groups' => $groups,
        'itemsByGroup' => $itemsByGroup,
        'can_manage' => $canManage
      ]]);
    }

    if ($action === 'add_group') {
      $name = trim($_POST['name'] ?? '');
      if ($name === '') {
        json_out(['ok' => false, 'msg' => 'Group name is required.'], 422);
      }

      $st = $pdo->prepare("INSERT INTO project_color_groups (project_id, name, created_by)
                           VALUES (:pid, :name, :uid)");
      $st->execute([':pid' => $projectId, ':name' => $name, ':uid' => $userId]);

      json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'name' => $name]);
    }

    if ($action === 'delete_group') {
      $groupId = (int)($_POST['group_id'] ?? 0);
      ensure_group_belongs($pdo, $groupId, $projectId);

      $pdo->beginTransaction();
      $pdo->prepare("DELETE FROM project_color_items WHERE group_id = :gid")->execute([':gid' => $groupId]);
      $pdo->prepare("DELETE FROM project_color_groups WHERE id = :gid")->execute([':gid' => $groupId]);
      $pdo->commit();

      json_out(['ok' => true]);
    }

    if ($action === 'add_item') {
      $groupId   = (int)($_POST['group_id'] ?? 0);
      $label     = trim($_POST['label'] ?? '');
      $hex       = strtoupper(trim($_POST['hex_color'] ?? ''));
      $sortOrder = (int)($_POST['sort_order'] ?? 0);

      ensure_group_belongs($pdo, $groupId, $projectId);
      if ($label === '') {
        json_out(['ok' => false, 'msg' => 'Label is required.'], 422);
      }
      if (!preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/i', $hex)) {
        json_out(['ok' => false, 'msg' => 'Invalid HEX code. Example: #1A2B3C'], 422);
      }
      // Normalize #RGB -> #RRGGBB
      if (preg_match('/^#([0-9A-F]{3})$/i', $hex, $m)) {
        $c = strtoupper($m[1]);
        $hex = "#{$c[0]}{$c[0]}{$c[1]}{$c[1]}{$c[2]}{$c[2]}";
      }

      $st = $pdo->prepare("INSERT INTO project_color_items (group_id, label, hex_color, sort_order)
                           VALUES (:gid, :label, :hex, :so)");
      $st->execute([':gid' => $groupId, ':label' => $label, ':hex' => $hex, ':so' => $sortOrder]);

      json_out(['ok' => true, 'item' => [
        'id' => (int)$pdo->lastInsertId(), 'group_id' => $groupId, 'label' => $label, 'hex_color' => $hex, 'sort_order' => $sortOrder
      ]]);
    }

    if ($action === 'update_item') {
      $itemId = (int)($_POST['id'] ?? 0);
      $label  = trim($_POST['label'] ?? '');
      $hex    = strtoupper(trim($_POST['hex_color'] ?? ''));

      $groupId = get_item_group_if_belongs($pdo, $itemId, $projectId);

      if ($label === '') {
        json_out(['ok' => false, 'msg' => 'Label is required.'], 422);
      }
      if (!preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/i', $hex)) {
        json_out(['ok' => false, 'msg' => 'Invalid HEX code.'], 422);
      }
      // Normalize #RGB -> #RRGGBB
      if (preg_match('/^#([0-9A-F]{3})$/i', $hex, $m)) {
        $c = strtoupper($m[1]);
        $hex = "#{$c[0]}{$c[0]}{$c[1]}{$c[1]}{$c[2]}{$c[2]}";
      }

      $st = $pdo->prepare("UPDATE project_color_items
                           SET label = :label, hex_color = :hex, updated_at = NOW()
                           WHERE id = :id");
      $st->execute([':label' => $label, ':hex' => $hex, ':id' => $itemId]);

      json_out(['ok' => true]);
    }

    if ($action === 'delete_item') {
      $itemId = (int)($_POST['id'] ?? 0);
      if ($itemId <= 0) {
        json_out(['ok' => false, 'msg' => 'Invalid color item id.'], 422);
      }
      // Ensure the item belongs to this project (and get its group)
      get_item_group_if_belongs($pdo, $itemId, $projectId);

      $pdo->prepare("DELETE FROM project_color_items WHERE id = :id")->execute([':id' => $itemId]);
      json_out(['ok' => true]);
    }

    if ($action === 'reorder_items') {
      // Payload: items = JSON [{id, sort_order}, ...]
      $payload = json_decode($_POST['items'] ?? '[]', true);
      if (!is_array($payload)) $payload = [];

      // Verify all items belong to the project
      $ids = array_values(array_filter(array_map(fn($i) => (int)($i['id'] ?? 0), $payload)));
      if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT i.id
                FROM project_color_items i
                JOIN project_color_groups g ON g.id = i.group_id
                WHERE i.id IN ($in) AND g.project_id = ?";
        $st = $pdo->prepare($sql);
        $st->execute([...$ids, $projectId]);
        $valid = $st->fetchAll(PDO::FETCH_COLUMN);
        if (count($valid) !== count($ids)) {
          json_out(['ok' => false, 'msg' => 'Some items do not belong to this project.'], 422);
        }
      }

      $st = $pdo->prepare("UPDATE project_color_items SET sort_order = :so, updated_at = NOW() WHERE id = :id");
      foreach ($payload as $it) {
        $st->execute([':so' => (int)$it['sort_order'], ':id' => (int)$it['id']]);
      }

      json_out(['ok' => true]);
    }

    json_out(['ok' => false, 'msg' => 'Unknown action.'], 400);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    json_out(['ok' => false, 'msg' => 'Server error: ' . $e->getMessage()], 500);
  }
  exit;
}

/* --------------------------------------------------------------------------
   HTML (when included from project_view.php)
----------------------------------------------------------------------------- */
?>

  <link rel="stylesheet" href="../assets/css/project_tab_colors.css?v=<?php echo time(); ?>">
<div id="project-colors"
     class="cde-colors"
     data-project-id="<?= htmlspecialchars((string)$projectId) ?>"
     data-can-manage="<?= $canManage ? '1' : '0' ?>"
     data-endpoint="partials/project_tab_colors.php">

  <?php if ($canManage): ?>
    <div class="colors-section colors-create-group">
      <h4>Add color group</h4>
      <div class="form-inline">
        <input type="text" id="color-group-name" class="input" placeholder="Group name (e.g., Finishes)" maxlength="255">
        <button class="btn btn-primary" id="btn-save-group">Save group</button>
      </div>
      <div class="hint">Only members in the <em>manager</em> group can add/edit/delete.</div>
    </div>
  <?php else: ?>
    <div class="colors-section colors-create-group disabled">
      <h4>Color groups</h4>
      <div class="hint">You have view-only access.</div>
    </div>
  <?php endif; ?>

  <div class="colors-section">
    <div id="color-groups-list" class="groups-list"><!-- Rendered by JS --></div>
  </div>
</div>
<script src="../assets/js/project_tab_colors.js"></script>
