<?php
/**
 * pages/partials/project_tab_colors.php
 * - Single PHP entry for UI (embedded in project_view) + AJAX endpoints (?action=...)
 * - Still uses external CSS/JS: /assets/css/project_tab_colors.css, /assets/js/project_tab_colors.js
 * - No duplicate session_start / helpers; will try to include init/config only if needed (when called directly).
 */

if (!defined('CDE_PARTIAL_COLORS')) {
    define('CDE_PARTIAL_COLORS', true);
}

/* -----------------------------------------------------------
 * 0) Bootstrap when called directly (AJAX) – try to load $pdo, session, helpers
 * ----------------------------------------------------------- */
$__ROOT = realpath(__DIR__ . '/../../'); // .../pages
$__APP  = realpath(__DIR__ . '/../../'); // same
$__BASE = realpath(__DIR__ . '/../../'); // compat

if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Try includes from app root: /includes/init.php then /includes/config.php + helpers.php
    $rootCandidate = realpath(__DIR__ . '/../../');             // .../pages -> up to htdocs
    $rootCandidate = dirname($rootCandidate);                    // htdocs
    $incPath = $rootCandidate . DIRECTORY_SEPARATOR . 'includes';

    if (is_dir($incPath)) {
        if (file_exists($incPath . '/init.php')) {
            include_once $incPath . '/init.php';
        } else {
            if (file_exists($incPath . '/config.php')) include_once $incPath . '/config.php';
            if (file_exists($incPath . '/helpers.php')) include_once $incPath . '/helpers.php';
        }
    }
}

/* -----------------------------------------------------------
 * 1) Helpers (safe, no name clash)
 * ----------------------------------------------------------- */
function _colors_json($data, int $code = 200) {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function _colors_get_user_id(): ?int {
    // Try several common buckets from the app
    if (isset($GLOBALS['current_user']['id']))     return (int)$GLOBALS['current_user']['id'];
    if (isset($GLOBALS['user']['id']))             return (int)$GLOBALS['user']['id'];
    if (isset($_SESSION['user']['id']))            return (int)$_SESSION['user']['id'];
    if (isset($_SESSION['user_id']))               return (int)$_SESSION['user_id'];
    if (isset($GLOBALS['auth_user']['id']))        return (int)$GLOBALS['auth_user']['id'];
    return null;
}

function _colors_get_project_id(): ?int {
    if (isset($_GET['id'])) return (int)$_GET['id'];
    if (isset($GLOBALS['project']['id'])) return (int)$GLOBALS['project']['id'];
    if (isset($GLOBALS['project_id'])) return (int)$GLOBALS['project_id'];
    return null;
}

/**
 * Ensure color tables exist (safe IF NOT EXISTS).
 * Groups: id, project_id, name, created_by, created_at
 * Items : id, project_id, group_id, label, color_hex, created_by, created_at
 */
function _colors_ensure_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_color_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            name VARCHAR(191) NOT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pcg_project_id (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_color_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            group_id INT NOT NULL,
            label VARCHAR(191) NOT NULL,
            color_hex CHAR(7) NOT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pci_project (project_id),
            INDEX idx_pci_group (group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/** Detect column name for HEX: prefer color_hex; fallback hex_code if exists in old DB */
function _colors_hex_col(PDO $pdo): string {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM project_color_items");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (in_array('color_hex', $cols, true)) return 'color_hex';
        if (in_array('hex_code', $cols, true))  return 'hex_code';
    } catch (Throwable $e) {}
    return 'color_hex';
}

/** Check manager permission for this project */
function _colors_user_can_manage(PDO $pdo, int $projectId, int $userId): bool {
    // 1) Find manager group id in this project
    $sqlGroup = "SELECT id FROM project_groups WHERE project_id = ? AND name = 'manager' LIMIT 1";
    $stmt = $pdo->prepare($sqlGroup);
    $stmt->execute([$projectId]);
    $groupId = (int)$stmt->fetchColumn();
    if (!$groupId) return false;

    // 2) Check membership
    $sqlMem  = "SELECT 1 FROM project_group_members WHERE project_id = ? AND group_id = ? AND user_id = ? LIMIT 1";
    $stmt2 = $pdo->prepare($sqlMem);
    $stmt2->execute([$projectId, $groupId, $userId]);
    return (bool)$stmt2->fetchColumn();
}

/** Basic HEX sanitizer (#RRGGBB) */
function _colors_sanitize_hex(?string $hex): string {
    $hex = trim((string)$hex);
    if ($hex === '') return '#000000';
    if ($hex[0] !== '#') $hex = '#' . $hex;
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
        // Try expand shorthand #RGB
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $hex, $m)) {
            $hex = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        } else {
            $hex = '#000000';
        }
    }
    return strtoupper($hex);
}

/* -----------------------------------------------------------
 * 2) Resolve dependencies ($pdo, project/user IDs)
 * ----------------------------------------------------------- */
$projectId = _colors_get_project_id();
$userId    = _colors_get_user_id();

if (isset($_GET['action'])) {
    // AJAX branch
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        _colors_json(['ok' => false, 'error' => 'db_missing'], 500);
    }

    try { _colors_ensure_tables($pdo); } catch (Throwable $e) {
        _colors_json(['ok'=>false,'error'=>'table_init_failed','message'=>$e->getMessage()], 500);
    }

    $hexCol = _colors_hex_col($pdo);
    $action = $_GET['action'];

    // List groups (read-only OK)
    if ($action === 'list_groups') {
        if (!$projectId) _colors_json(['ok'=>false,'error'=>'missing_project_id'], 400);
        try {
            $sql = "SELECT g.id, g.name, g.created_at,
                           (SELECT COUNT(*) FROM project_color_items i WHERE i.group_id = g.id) AS items_count
                    FROM project_color_groups g
                    WHERE g.project_id = ?
                    ORDER BY g.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$projectId]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            _colors_json(['ok'=>true,'groups'=>$groups]);
        } catch (Throwable $e) {
            _colors_json(['ok'=>false,'error'=>'sql_error','message'=>$e->getMessage()], 500);
        }
    }

    // Create group (need manage)
    if ($action === 'create_group') {
        if (!$projectId) _colors_json(['ok'=>false,'error'=>'missing_project_id'], 400);
        if (!$userId)    _colors_json(['ok'=>false,'error'=>'not_authenticated'], 401);
        if (!_colors_user_can_manage($pdo, $projectId, $userId)) {
            _colors_json(['ok'=>false,'error'=>'forbidden'], 403);
        }
        $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
        if ($name === '') _colors_json(['ok'=>false,'error'=>'invalid_name'], 422);

        try {
            $stmt = $pdo->prepare("INSERT INTO project_color_groups (project_id, name, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$projectId, $name, $userId]);
            $newId = (int)$pdo->lastInsertId();
            _colors_json(['ok'=>true,'id'=>$newId,'name'=>$name]);
        } catch (Throwable $e) {
            _colors_json(['ok'=>false,'error'=>'sql_error','message'=>$e->getMessage()], 500);
        }
    }

    // List items in a group (read-only OK)
    if ($action === 'list_items') {
        $groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
        if (!$projectId || !$groupId) _colors_json(['ok'=>false,'error'=>'missing_params'], 400);

        try {
            // Ensure the group belongs to the project
            $chk = $pdo->prepare("SELECT 1 FROM project_color_groups WHERE id=? AND project_id=?");
            $chk->execute([$groupId,$projectId]);
            if (!$chk->fetchColumn()) _colors_json(['ok'=>true,'items'=>[]]);

            $stmt = $pdo->prepare("SELECT id, label, {$hexCol} AS hex, created_at
                                   FROM project_color_items
                                   WHERE group_id = ?
                                   ORDER BY id ASC");
            $stmt->execute([$groupId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            _colors_json(['ok'=>true,'items'=>$items]);
        } catch (Throwable $e) {
            _colors_json(['ok'=>false,'error'=>'sql_error','message'=>$e->getMessage()], 500);
        }
    }

    // Create item (need manage)
    if ($action === 'create_item') {
        if (!$projectId) _colors_json(['ok'=>false,'error'=>'missing_project_id'], 400);
        if (!$userId)    _colors_json(['ok'=>false,'error'=>'not_authenticated'], 401);
        if (!_colors_user_can_manage($pdo, $projectId, $userId)) {
            _colors_json(['ok'=>false,'error'=>'forbidden'], 403);
        }

        $groupId = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
        $label   = isset($_POST['label']) ? trim((string)$_POST['label']) : '';
        $hex     = _colors_sanitize_hex($_POST['hex'] ?? '');

        if (!$groupId || $label === '') _colors_json(['ok'=>false,'error'=>'invalid_params'], 422);

        try {
            // Ensure group belongs to this project
            $chk = $pdo->prepare("SELECT 1 FROM project_color_groups WHERE id = ? AND project_id = ?");
            $chk->execute([$groupId,$projectId]);
            if (!$chk->fetchColumn()) _colors_json(['ok'=>false,'error'=>'group_not_found'], 404);

            $sql = "INSERT INTO project_color_items (project_id, group_id, label, {$hexCol}, created_by)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$projectId, $groupId, $label, $hex, $userId]);
            $id = (int)$pdo->lastInsertId();
            _colors_json(['ok'=>true,'item'=>['id'=>$id,'label'=>$label,'hex'=>$hex]]);
        } catch (Throwable $e) {
            _colors_json(['ok'=>false,'error'=>'sql_error','message'=>$e->getMessage()], 500);
        }
    }

    // Update item (need manage)
    if ($action === 'update_item') {
        if (!$projectId) _colors_json(['ok'=>false,'error'=>'missing_project_id'], 400);
        if (!$userId)    _colors_json(['ok'=>false,'error'=>'not_authenticated'], 401);
        if (!_colors_user_can_manage($pdo, $projectId, $userId)) {
            _colors_json(['ok'=>false,'error'=>'forbidden'], 403);
        }

        $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $label  = isset($_POST['label']) ? trim((string)$_POST['label']) : null;
        $hex    = isset($_POST['hex']) ? _colors_sanitize_hex($_POST['hex']) : null;

        if (!$itemId) _colors_json(['ok'=>false,'error'=>'invalid_item_id'], 422);

        try {
            // Ensure item belongs to this project
            $chk = $pdo->prepare("SELECT group_id FROM project_color_items WHERE id=? AND project_id=?");
            $chk->execute([$itemId,$projectId]);
            $groupId = (int)$chk->fetchColumn();
            if (!$groupId) _colors_json(['ok'=>false,'error'=>'item_not_found'], 404);

            // Build dynamic SQL
            $fields = [];
            $params = [];
            if ($label !== null) { $fields[] = "label = ?"; $params[] = $label; }
            if ($hex   !== null) { $fields[] = "{$hexCol} = ?"; $params[] = $hex; }
            if (!$fields) _colors_json(['ok'=>false,'error'=>'nothing_to_update'], 422);
            $params[] = $itemId;

            $sql = "UPDATE project_color_items SET ".implode(", ", $fields)." WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            _colors_json(['ok'=>true]);
        } catch (Throwable $e) {
            _colors_json(['ok'=>false,'error'=>'sql_error','message'=>$e->getMessage()], 500);
        }
    }

    // Delete item (need manage)
    if ($action === 'delete_item') {
        if (!$projectId) _colors_json(['ok'=>false,'error'=>'missing_project_id'], 400);
        if (!$userId)    _colors_json(['ok'=>false,'error'=>'not_authenticated'], 401);
        if (!_colors_user_can_manage($pdo, $projectId, $userId)) {
            _colors_json(['ok'=>false,'error'=>'forbidden'], 403);
        }

        $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        if (!$itemId) _colors_json(['ok'=>false,'error'=>'invalid_item_id'], 422);

        try {
            $stmt = $pdo->prepare("DELETE FROM project_color_items WHERE id=? AND project_id=?");
            $stmt->execute([$itemId,$projectId]);
            _colors_json(['ok'=>true]);
        } catch (Throwable $e) {
            _colors_json(['ok'=>false,'error'=>'sql_error','message'=>$e->getMessage()], 500);
        }
    }

    // Unknown action
    _colors_json(['ok'=>false,'error'=>'unknown_action'], 400);
    // (exit)
}

/* -----------------------------------------------------------
 * 3) Normal render (included in project_view.php)
 * ----------------------------------------------------------- */
?>
<?php if (!isset($pdo) || !($pdo instanceof PDO)): ?>
    <div class="cde-alert cde-alert-error">DB chưa sẵn sàng. Vui lòng tải lại trang.</div>
    <?php return; ?>
<?php endif; ?>
<?php
try { _colors_ensure_tables($pdo); } catch (Throwable $e) { /* ignore UI fail-soft */ }
$hexCol = _colors_hex_col($pdo);
$uid    = $userId;
$pid    = $projectId;
$canManage = ($uid && $pid) ? _colors_user_can_manage($pdo, $pid, $uid) : false;
?>

<link rel="stylesheet" href="/assets/css/project_tab_colors.css?v=1" />

<div id="cde-color-tab"
     data-project-id="<?= htmlspecialchars((string)$pid) ?>"
     data-can-manage="<?= $canManage ? '1':'0' ?>"
     class="cde-colors-wrap">

    <!-- Khu vực 1: Tạo nhóm màu (chỉ manager thấy) -->
    <?php if ($canManage): ?>
    <div class="cde-color-group-create">
        <h4>Nhóm màu sắc</h4>
        <form id="form-create-color-group" method="post" action="/pages/partials/project_tab_colors.php?action=create_group">
            <input type="hidden" name="project_id" value="<?= (int)$pid ?>">
            <div class="cde-field-row">
                <input type="text" name="name" placeholder="Tên nhóm màu..." required />
                <button type="submit" class="cde-btn cde-btn-primary">Lưu nhóm</button>
            </div>
            <div class="cde-help">Chỉ thành viên thuộc nhóm <b>manager</b> mới tạo/sửa.</div>
        </form>
        <div class="cde-line"></div>
    </div>
    <?php else: ?>
        <div class="cde-note-readonly">Bạn đang xem ở chế độ chỉ đọc. (Chỉ quản lý dự án – thuộc group <b>manager</b> – mới có thể tạo/sửa.)</div>
        <div class="cde-line"></div>
    <?php endif; ?>

    <!-- Khu vực 2: Danh sách nhóm + items -->
    <div class="cde-color-groups">
        <div class="cde-groups-header">
            <h4>Danh sách nhóm màu</h4>
            <div class="cde-groups-loading" style="display:none">Đang tải...</div>
            <div class="cde-groups-error cde-alert cde-alert-error" style="display:none"></div>
        </div>
        <div id="cde-groups-list"></div>
    </div>
</div>

<script>
window.CDE_COLORS_ENDPOINT = "/pages/partials/project_tab_colors.php";
</script>
<script src="/assets/js/project_tab_colors.js?v=1"></script>
