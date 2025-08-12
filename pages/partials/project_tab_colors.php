<?php
// Tab này được include bên trong project_view.php.
// Giả định $pdo (PDO), $_SESSION['user_id'] và $project_id đã có sẵn giống project_tab_members.php.
// Nếu không, bạn có thể thay thế $project_id = (int)($_GET['id'] ?? 0);

if (!isset($project_id)) {
    $project_id = (int)($_GET['id'] ?? 0);
}


// ------------------------- Helpers -------------------------
function json_out($arr, $code = 200) {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($arr);
    exit;
}

function is_project_manager(PDO $pdo, int $project_id, int $user_id): bool {
    // Người dùng được coi là "manager" nếu nằm trong group có name = 'manager'
    // hoặc group_id = 1 (thông thường).
    $sql = "
        SELECT 1
        FROM project_group_members pgm
        JOIN project_groups pg ON pgm.group_id = pg.id
        WHERE pgm.project_id = :pid
          AND pgm.user_id = :uid
          AND (pg.name = 'manager' OR pgm.group_id = 1)
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':pid' => $project_id, ':uid' => $user_id]);
    return (bool)$st->fetchColumn();
}

function ensure_group_belongs(PDO $pdo, int $group_id, int $project_id) {
    $st = $pdo->prepare("SELECT id FROM project_color_groups WHERE id = :gid AND project_id = :pid");
    $st->execute([':gid' => $group_id, ':pid' => $project_id]);
    if (!$st->fetchColumn()) {
        json_out(['ok' => false, 'msg' => 'Color group không thuộc về dự án.'], 404);
    }
}

function ensure_item_belongs(PDO $pdo, int $item_id, int $project_id) {
    $sql = "SELECT i.id
            FROM project_color_items i
            JOIN project_color_groups g ON i.group_id = g.id
            WHERE i.id = :iid AND g.project_id = :pid";
    $st = $pdo->prepare($sql);
    $st->execute([':iid' => $item_id, ':pid' => $project_id]);
    if (!$st->fetchColumn()) {
        json_out(['ok' => false, 'msg' => 'Color item không thuộc về dự án.'], 404);
    }
}

$can_manage = is_project_manager($pdo, $project_id, $current_user_id);

// ------------------------- AJAX API -------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Chỉ manager được phép thay đổi
    $mutating_actions = ['add_group','delete_group','add_item','update_item','delete_item','reorder_items'];
    if (in_array($action, $mutating_actions, true) && !$can_manage) {
        json_out(['ok' => false, 'msg' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
    }

    try {
        if ($action === 'list') {
            // Lấy toàn bộ group + items
            $st = $pdo->prepare("SELECT id, name, created_by, created_at FROM project_color_groups WHERE project_id = :pid ORDER BY id ASC");
            $st->execute([':pid' => $project_id]);
            $groups = $st->fetchAll(PDO::FETCH_ASSOC);

            $itemsByGroup = [];
            if ($groups) {
                $groupIds = array_column($groups, 'id');
                $in = implode(',', array_fill(0, count($groupIds), '?'));
                $sql = "SELECT id, group_id, label, hex_color, sort_order
                        FROM project_color_items
                        WHERE group_id IN ($in)
                        ORDER BY sort_order ASC, id ASC";
                $stm = $pdo->prepare($sql);
                $stm->execute($groupIds);
                while ($row = $stm->fetch(PDO::FETCH_ASSOC)) {
                    $gid = (int)$row['group_id'];
                    if (!isset($itemsByGroup[$gid])) $itemsByGroup[$gid] = [];
                    $itemsByGroup[$gid][] = $row;
                }
            }

            json_out([
                'ok' => true,
                'data' => [
                    'groups' => $groups,
                    'itemsByGroup' => $itemsByGroup,
                    'can_manage' => $can_manage
                ]
            ]);
        }

        if ($action === 'add_group') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') json_out(['ok' => false, 'msg' => 'Tên group không được để trống.'], 422);

            $st = $pdo->prepare("INSERT INTO project_color_groups (project_id, name, created_by) VALUES (:pid, :name, :uid)");
            $st->execute([':pid' => $project_id, ':name' => $name, ':uid' => $current_user_id]);

            json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'name' => $name]);
        }

        if ($action === 'delete_group') {
            $group_id = (int)($_POST['group_id'] ?? 0);
            ensure_group_belongs($pdo, $group_id, $project_id);

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM project_color_items WHERE group_id = :gid")->execute([':gid' => $group_id]);
            $pdo->prepare("DELETE FROM project_color_groups WHERE id = :gid")->execute([':gid' => $group_id]);
            $pdo->commit();

            json_out(['ok' => true]);
        }

        if ($action === 'add_item') {
            $group_id   = (int)($_POST['group_id'] ?? 0);
            $label      = trim($_POST['label'] ?? '');
            $hex_color  = strtoupper(trim($_POST['hex_color'] ?? ''));
            $sort_order = (int)($_POST['sort_order'] ?? 0);

            ensure_group_belongs($pdo, $group_id, $project_id);

            if ($label === '') json_out(['ok' => false, 'msg' => 'Label không được để trống.'], 422);
            if (!preg_match('/^#([0-9A-F]{6}|[0-9A-F]{3})$/i', $hex_color)) {
                json_out(['ok' => false, 'msg' => 'Mã màu không hợp lệ. Ví dụ: #1A2B3C'], 422);
            }

            $st = $pdo->prepare("
                INSERT INTO project_color_items (group_id, label, hex_color, sort_order)
                VALUES (:gid, :label, :hex, :sort_order)
            ");
            $st->execute([':gid' => $group_id, ':label' => $label, ':hex' => $hex_color, ':sort_order' => $sort_order]);
            $id = (int)$pdo->lastInsertId();

            json_out(['ok' => true, 'item' => ['id' => $id, 'group_id' => $group_id, 'label' => $label, 'hex_color' => $hex_color, 'sort_order' => $sort_order]]);
        }

        if ($action === 'update_item') {
            $item_id   = (int)($_POST['id'] ?? 0);
            $label     = trim($_POST['label'] ?? '');
            $hex_color = strtoupper(trim($_POST['hex_color'] ?? ''));

            ensure_item_belongs($pdo, $item_id, $project_id);

            if ($label === '') json_out(['ok' => false, 'msg' => 'Label không được để trống.'], 422);
            if (!preg_match('/^#([0-9A-F]{6}|[0-9A-F]{3})$/i', $hex_color)) {
                json_out(['ok' => false, 'msg' => 'Mã màu không hợp lệ.'], 422);
            }

            $st = $pdo->prepare("
                UPDATE project_color_items
                SET label = :label, hex_color = :hex, updated_at = NOW()
                WHERE id = :id
            ");
            $st->execute([':label' => $label, ':hex' => $hex_color, ':id' => $item_id]);

            json_out(['ok' => true]);
        }

        if ($action === 'delete_item') {
            $item_id = (int)($_POST['id'] ?? 0);
            ensure_item_belongs($pdo, $item_id, $project_id);
            $pdo->prepare("DELETE FROM project_color_items WHERE id = :id")->execute([':id' => $item_id]);
            json_out(['ok' => true]);
        }

        if ($action === 'reorder_items') {
            // Nhận mảng items: [{id, sort_order}, ...]
            $payload = json_decode($_POST['items'] ?? '[]', true);
            if (!is_array($payload)) $payload = [];

            // Xác minh tất cả item thuộc về dự án
            $ids = array_map(fn($i) => (int)($i['id'] ?? 0), $payload);
            $ids = array_values(array_filter($ids));
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $sql = "SELECT i.id
                        FROM project_color_items i
                        JOIN project_color_groups g ON i.group_id = g.id
                        WHERE i.id IN ($in) AND g.project_id = ?";
                $st = $pdo->prepare($sql);
                $st->execute([...$ids, $project_id]);
                $valid = $st->fetchAll(PDO::FETCH_COLUMN);
                if (count($valid) !== count($ids)) json_out(['ok' => false, 'msg' => 'Có color item không hợp lệ.'], 422);
            }

            $pdo->beginTransaction();
            $st = $pdo->prepare("UPDATE project_color_items SET sort_order = :so, updated_at = NOW() WHERE id = :id");
            foreach ($payload as $it) {
                $st->execute([':so' => (int)$it['sort_order'], ':id' => (int)$it['id']]);
            }
            $pdo->commit();

            json_out(['ok' => true]);
        }

        json_out(['ok' => false, 'msg' => 'Action không hợp lệ.'], 400);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_out(['ok' => false, 'msg' => 'Có lỗi xảy ra: '.$e->getMessage()], 500);
    }
    exit;
}

// ------------------------- HTML (read/write dựa vào $can_manage) -------------------------
?>
<link rel="stylesheet" href="assets/css/project_tab_colors.css">
<div id="project-colors"
     class="cde-colors"
     data-project-id="<?= htmlspecialchars((string)$project_id) ?>"
     data-can-manage="<?= $can_manage ? '1' : '0' ?>">

    <!-- Khu vực 1: Tạo group màu sắc -->
    <?php if ($can_manage): ?>
    <div class="colors-section colors-create-group">
        <h4>Thêm nhóm màu sắc</h4>
        <div class="form-inline">
            <input type="text" id="color-group-name" class="input" placeholder="Tên group (ví dụ: Cấu kiện thép)" maxlength="255">
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

    <!-- Khu vực 2: Danh sách group và items -->
    <div class="colors-section">
        <div id="color-groups-list" class="groups-list">
            <!-- JS render tại assets/js/project_tab_colors.js -->
        </div>
    </div>
</div>

<script src="assets/js/project_tab_colors.js"></script>
