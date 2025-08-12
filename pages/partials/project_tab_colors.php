<?php
// pages/partials/project_tab_colors.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/** Always-safe JSON out */
function json_out($arr, $code = 200) {
  if (!headers_sent()) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

$isAjax = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']));

/* -----------------------------------------------------------------------------
   BOOTSTRAP (AJAX): Bắt chước project_view.php để có $ROOT, $pdo, $userId, $project
----------------------------------------------------------------------------- */
if ($isAjax) {
  // Lấy $ROOT trỏ tới thư mục gốc (từ /pages/partials -> lên 2 cấp)
  $ROOT = realpath(__DIR__ . '/../..');

  // Nạp các file giống project_view.php
  if (is_file($ROOT . '/config.php'))                require_once $ROOT . '/config.php';
  if (is_file($ROOT . '/includes/permissions.php'))  require_once $ROOT . '/includes/permissions.php';
  if (is_file($ROOT . '/includes/helpers.php'))      require_once $ROOT . '/includes/helpers.php';
  if (is_file($ROOT . '/includes/projects.php'))     require_once $ROOT . '/includes/projects.php';
  if (is_file($ROOT . '/includes/files.php'))        require_once $ROOT . '/includes/files.php';

  /** Ensure $pdo (giống project_view.php) */
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('getPDO')) {
      $pdo = getPDO();
    } elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
      $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
      try {
        $pdo = new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '', [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
      } catch (Throwable $e) {
        json_out(['ok' => false, 'msg' => 'Không kết nối được CSDL: ' . $e->getMessage()], 500);
      }
    } else {
      json_out(['ok' => false, 'msg' => 'Thiếu cấu hình DB (DB_HOST/DB_NAME/DB_USER).'], 500);
    }
  }

  /** Lấy $userId giống cách làm “mạnh tay” ở project_view.php */
// 2) $userId từ session (robust)
function _pick_int($v) { return (is_numeric($v) && (int)$v > 0) ? (int)$v : 0; }

$userId = 0;
$tryList = [
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

foreach ($tryList as $v) {
  $userId = _pick_int($v);
  if ($userId) break;
}

// fallback: quét sâu mảng session để tìm khóa phổ biến
if ($userId <= 0) {
  $stack = [$_SESSION];
  while ($stack) {
    $node = array_pop($stack);
    if (!is_array($node)) continue;
    foreach ($node as $k => $v) {
      if (is_array($v)) { $stack[] = $v; continue; }
      if (in_array($k, ['user_id','uid','id'], true)) {
        $userId = _pick_int($v);
        if ($userId) break 2;
      }
    }
  }
}

if ($userId <= 0) {
  json_out(['ok' => false, 'msg' => 'Bạn chưa đăng nhập.'], 401);
}

  /** Lấy $project theo project_id gửi lên */
  $projectId = (int)($_POST['project_id'] ?? 0);
  if ($projectId <= 0) { $projectId = (int)($_GET['project_id'] ?? 0); }
  if ($projectId <= 0) { json_out(['ok' => false, 'msg' => 'Thiếu project_id.'], 422); }

  $stm = $pdo->prepare("SELECT * FROM projects WHERE id = :id LIMIT 1");
  $stm->execute([':id' => $projectId]);
  $project = $stm->fetch(PDO::FETCH_ASSOC);
  if (!$project) {
    json_out(['ok' => false, 'msg' => 'Dự án không tồn tại.'], 404);
  }
}
/* -----------------------------------------------------------------------------
   KHI ĐƯỢC INCLUDE TRONG project_view.php (HTML)
----------------------------------------------------------------------------- */
else {
  if (!isset($pdo) || !isset($project) || !isset($userId)) {
    echo '<div class="alert">Context missing for Colors tab.</div>';
    return;
  }
  $projectId = (int)($project['id'] ?? 0);
  $userId    = (int)$userId;
}

/* -----------------------------------------------------------------------------
   Helpers
----------------------------------------------------------------------------- */
function is_project_manager(PDO $pdo, int $projectId, int $userId, array $project): bool {
  if ((int)($project['created_by'] ?? 0) === $userId) return true;
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
  if (!$st->fetchColumn()) json_out(['ok' => false, 'msg' => 'Color group không thuộc dự án.'], 404);
}

function get_item_group_if_belongs(PDO $pdo, int $itemId, int $projectId): int {
  $sql = "SELECT i.group_id
          FROM project_color_items i
          JOIN project_color_groups g ON g.id = i.group_id
          WHERE i.id = :iid AND g.project_id = :pid";
  $st = $pdo->prepare($sql);
  $st->execute([':iid' => $itemId, ':pid' => $projectId]);
  $gid = (int)$st->fetchColumn();
  if (!$gid) json_out(['ok' => false, 'msg' => 'Color item không thuộc dự án.'], 404);
  return $gid;
}

// Mirror phẳng sang project_colors (không để vỡ flow nếu schema khác)
function resync_group_flat(PDO $pdo, int $projectId, int $groupId, int $userId): void {
  try {
    $stm = $pdo->prepare("SELECT id, label, hex_color, sort_order
                          FROM project_color_items
                          WHERE group_id = :gid
                          ORDER BY sort_order ASC, id ASC");
    $stm->execute([':gid' => $groupId]);
    $items = $stm->fetchAll(PDO::FETCH_ASSOC);

    $pdo->prepare("DELETE FROM project_colors WHERE project_id = :pid AND group_id = :gid")
        ->execute([':pid' => $projectId, ':gid' => $groupId]);

    if ($items) {
      $ins = $pdo->prepare("INSERT INTO project_colors
        (project_id, group_id, label, hex_code, sort_order, created_by)
        VALUES (:pid, :gid, :label, :hex, :so, :uid)");
      foreach ($items as $it) {
        $hex = strtoupper((string)$it['hex_color']);
        if (preg_match('/^#([0-9A-F]{3})$/i', $hex, $m)) {
          $c = strtoupper($m[1]);
          $hex = '#' . $c[0] . $c[0] . $c[1] . $c[1] . $c[2] . $c[2];
        }
        if (!preg_match('/^#[0-9A-F]{6}$/i', $hex)) continue;
        $ins->execute([
          ':pid' => $projectId,
          ':gid' => $groupId,
          ':label' => $it['label'],
          ':hex'   => $hex,
          ':so'    => (int)$it['sort_order'],
          ':uid'   => $userId
        ]);
      }
    }
  } catch (Throwable $e) { /* ignore mirror errors */ }
}

$canManage = is_project_manager($pdo, $projectId, $userId, $project);

/* -----------------------------------------------------------------------------
   AJAX API
----------------------------------------------------------------------------- */
if ($isAjax) {
  $action = $_POST['action'] ?? '';

  $mutating = ['add_group','delete_group','add_item','update_item','delete_item','reorder_items'];
  if (in_array($action, $mutating, true) && !$canManage) {
    json_out(['ok' => false, 'msg' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
  }

  try {
    if ($action === 'list') {
      $st = $pdo->prepare("SELECT id, name, created_by, created_at
                           FROM project_color_groups
                           WHERE project_id = :pid
                           ORDER BY id ASC");
      $st->execute([':pid' => $projectId]);
      $groups = $st->fetchAll(PDO::FETCH_ASSOC);

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
      if ($name === '') json_out(['ok' => false, 'msg' => 'Tên group không được để trống.'], 422);

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
      $pdo->prepare("DELETE FROM project_colors WHERE project_id = :pid AND group_id = :gid")
          ->execute([':pid' => $projectId, ':gid' => $groupId]);
      $pdo->commit();

      json_out(['ok' => true]);
    }

    if ($action === 'add_item') {
      $groupId   = (int)($_POST['group_id'] ?? 0);
      $label     = trim($_POST['label'] ?? '');
      $hex       = strtoupper(trim($_POST['hex_color'] ?? ''));
      $sortOrder = (int)($_POST['sort_order'] ?? 0);

      ensure_group_belongs($pdo, $groupId, $projectId);
      if ($label === '') json_out(['ok' => false, 'msg' => 'Label không được để trống.'], 422);
      if (!preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/i', $hex)) json_out(['ok' => false, 'msg' => 'Mã màu không hợp lệ. Ví dụ: #1A2B3C'], 422);
      if (preg_match('/^#([0-9A-F]{3})$/i', $hex, $m)) {
        $c = strtoupper($m[1]); $hex = "#{$c[0]}{$c[0]}{$c[1]}{$c[1]}{$c[2]}{$c[2]}";
      }

      $pdo->beginTransaction();
      $st = $pdo->prepare("INSERT INTO project_color_items (group_id, label, hex_color, sort_order)
                           VALUES (:gid, :label, :hex, :so)");
      $st->execute([':gid' => $groupId, ':label' => $label, ':hex' => $hex, ':so' => $sortOrder]);
      $newId = (int)$pdo->lastInsertId();

      resync_group_flat($pdo, $projectId, $groupId, $userId);
      $pdo->commit();

      json_out(['ok' => true, 'item' => [
        'id' => $newId, 'group_id' => $groupId, 'label' => $label, 'hex_color' => $hex, 'sort_order' => $sortOrder
      ]]);
    }

    if ($action === 'update_item') {
      $itemId = (int)($_POST['id'] ?? 0);
      $label  = trim($_POST['label'] ?? '');
      $hex    = strtoupper(trim($_POST['hex_color'] ?? ''));

      $groupId = get_item_group_if_belongs($pdo, $itemId, $projectId);
      if ($label === '') json_out(['ok' => false, 'msg' => 'Label không được để trống.'], 422);
      if (!preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/i', $hex)) json_out(['ok' => false, 'msg' => 'Mã màu không hợp lệ.'], 422);
      if (preg_match('/^#([0-9A-F]{3})$/i', $hex, $m)) { $c = strtoupper($m[1]); $hex = "#{$c[0]}{$c[0]}{$c[1]}{$c[1]}{$c[2]}{$c[2]}"; }

      $pdo->beginTransaction();
      $st = $pdo->prepare("UPDATE project_color_items SET label = :label, hex_color = :hex, updated_at = NOW() WHERE id = :id");
      $st->execute([':label' => $label, ':hex' => $hex, ':id' => $itemId]);

      resync_group_flat($pdo, $projectId, $groupId, $userId);
      $pdo->commit();

      json_out(['ok' => true]);
    }

    if ($action === 'delete_item') {
      $itemId = (int)($_POST['id'] ?? 0);
      $groupId = get_item_group_if_belongs($pdo, $itemId, $projectId);

      $pdo->beginTransaction();
      $pdo->prepare("DELETE FROM project_color_items WHERE id = :id")->execute([':id' => $itemId]);
      resync_group_flat($pdo, $projectId, $groupId, $userId);
      $pdo->commit();

      json_out(['ok' => true]);
    }

    if ($action === 'reorder_items') {
      $payload = json_decode($_POST['items'] ?? '[]', true);
      if (!is_array($payload)) $payload = [];

      $ids = array_values(array_filter(array_map(fn($i) => (int)($i['id'] ?? 0), $payload)));
      $found = [];
      if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT i.id, i.group_id
                FROM project_color_items i
                JOIN project_color_groups g ON g.id = i.group_id
                WHERE i.id IN ($in) AND g.project_id = ?";
        $st = $pdo->prepare($sql);
        $st->execute([...$ids, $projectId]);
        $found = $st->fetchAll(PDO::FETCH_KEY_PAIR); // id => group_id
        if (count($found) !== count($ids)) json_out(['ok' => false, 'msg' => 'Có item không hợp lệ.'], 422);
      }

      $pdo->beginTransaction();
      $st = $pdo->prepare("UPDATE project_color_items SET sort_order = :so, updated_at = NOW() WHERE id = :id");
      foreach ($payload as $it) {
        $st->execute([':so' => (int)$it['sort_order'], ':id' => (int)$it['id']]);
      }
      foreach (array_unique(array_values($found)) as $gid) {
        resync_group_flat($pdo, $projectId, (int)$gid, $userId);
      }
      $pdo->commit();

      json_out(['ok' => true]);
    }

    json_out(['ok' => false, 'msg' => 'Action không hợp lệ.'], 400);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_out(['ok' => false, 'msg' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
  }
  exit;
}

/* -----------------------------------------------------------------------------
   HTML (include trong project_view.php)
----------------------------------------------------------------------------- */
?>
<link rel="stylesheet" href="../assets/css/project_tab_colors.css">
<div id="project-colors"
     class="cde-colors"
     data-project-id="<?= htmlspecialchars((string)$projectId) ?>"
     data-can-manage="<?= $canManage ? '1' : '0' ?>"
     data-endpoint="partials/project_tab_colors.php">
  <?php if ($canManage): ?>
    <div class="colors-section colors-create-group">
      <h4>Thêm nhóm màu sắc</h4>
      <div class="form-inline">
        <input type="text" id="color-group-name" class="input" placeholder="Tên group (ví dụ: Sơn hoàn thiện)" maxlength="255">
        <button class="btn btn-primary" id="btn-save-group">Lưu group</button>
      </div>
      <div class="hint">Chỉ thành viên thuộc nhóm <em>manager</em> của dự án mới có quyền thêm/sửa/xóa.</div>
    </div>
  <?php else: ?>
    <div class="colors-section colors-create-group disabled">
      <h4>Nhóm màu sắc</h4>
      <div class="hint">Bạn chỉ có quyền xem.</div>
    </div>
  <?php endif; ?>

  <div class="colors-section">
    <div id="color-groups-list" class="groups-list"><!-- JS render --></div>
  </div>
</div>
<script src="../assets/js/project_tab_colors.js"></script>
