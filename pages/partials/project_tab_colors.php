<?php
/**
 * Project Tab: Colors
 * - Khu vực 1: Tạo nhóm màu (chỉ manager/owner)
 * - Khu vực 2: Liệt kê nhóm & các item màu. Manager có thể thêm/sửa/lưu; user thường chỉ xem & preview.
 *
 * Yêu cầu:
 *   - $pdo: PDO đang kết nối
 *   - $USER_ID: id user hiện tại (int)
 *   - $projectId: id project hiện tại (int). Nếu chưa có, lấy từ GET (?project_id hoặc ?id)
 *
 * Gợi ý include (tùy cách tổ chức của bạn):
 *   require_once __DIR__ . '/../../includes/permissions.php';
 *   require_once __DIR__ . '/../../includes/init.php';
 */

if (!isset($pdo)) {
  die("DB connection (\$pdo) is required.");
}

// Xác định projectId
$projectId = $projectId ?? (int)($_GET['project_id'] ?? $_GET['id'] ?? 0);
if ($projectId <= 0) {
  die("Missing project id.");
}

// Lấy user hiện tại
$USER_ID = $USER_ID ?? (int)($_SESSION['user_id'] ?? 0);
if ($USER_ID <= 0) {
  die("Not authenticated.");
}

// ----------------------------------------------------
// Tạo bảng nếu chưa có (an toàn khi cài mới)
// ----------------------------------------------------
$pdo->exec("
  CREATE TABLE IF NOT EXISTS project_color_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_project_group (project_id, name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
  CREATE TABLE IF NOT EXISTS project_color_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    hex_color VARCHAR(9) NOT NULL,  -- hỗ trợ #RRGGBB hoặc #RRGGBBAA
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    CONSTRAINT fk_color_group FOREIGN KEY (group_id)
      REFERENCES project_color_groups(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ----------------------------------------------------
// Kiểm tra quyền manager/owner của user trong project
// Tùy DB của bạn, hãy sửa lại query cho khớp bảng/field
// ----------------------------------------------------
function isProjectManager(PDO $pdo, int $userId, int $projectId): bool {
  // Ví dụ 1: bảng projects_members (user_id, project_id, role)
  $sqls = [
    "SELECT 1 FROM projects_members WHERE user_id=? AND project_id=? AND role IN ('manager','owner') LIMIT 1",
    // Ví dụ 2 (fallback): organizations_projects_members
    "SELECT 1 FROM organizations_projects_members WHERE user_id=? AND project_id=? AND role IN ('manager','owner') LIMIT 1",
  ];
  foreach ($sqls as $sql) {
    try {
      $stm = $pdo->prepare($sql);
      $stm->execute([$userId, $projectId]);
      if ($stm->fetchColumn()) return true;
    } catch (Throwable $e) {
      // bỏ qua nếu bảng không tồn tại
    }
  }
  return false;
}

$isManager = isProjectManager($pdo, $USER_ID, $projectId);

// ----------------------------------------------------
// XỬ LÝ POST
// ----------------------------------------------------
$errors = [];
$success = null;

// Tạo group màu mới
if ($isManager && ($_POST['action'] ?? '') === 'create_group') {
  $groupName = trim($_POST['group_name'] ?? '');
  if ($groupName === '') {
    $errors[] = "Vui lòng nhập tên group.";
  } else {
    try {
      $stm = $pdo->prepare("INSERT INTO project_color_groups (project_id, name, created_by) VALUES (?, ?, ?)");
      $stm->execute([$projectId, $groupName, $USER_ID]);
      $success = "Đã tạo group “" . htmlspecialchars($groupName) . "”.";
    } catch (PDOException $e) {
      if ((int)$e->errorInfo[1] === 1062) {
        $errors[] = "Group “" . htmlspecialchars($groupName) . "” đã tồn tại trong dự án.";
      } else {
        $errors[] = "Lỗi khi tạo group: " . $e->getMessage();
      }
    }
  }
}

// Lưu các item của một group (thêm/sửa/xóa)
if ($isManager && ($_POST['action'] ?? '') === 'save_items') {
  $groupId = (int)($_POST['group_id'] ?? 0);

  // Kiểm tra group thuộc project hiện tại
  $stm = $pdo->prepare("SELECT id FROM project_color_groups WHERE id=? AND project_id=?");
  $stm->execute([$groupId, $projectId]);
  $groupExists = (bool)$stm->fetchColumn();

  if (!$groupExists) {
    $errors[] = "Group không hợp lệ.";
  } else {
    // Nhận dữ liệu các dòng
    $ids        = $_POST['item_id']     ?? []; // có thể rỗng cho dòng mới
    $labels     = $_POST['item_label']  ?? [];
    $hexes      = $_POST['item_hex']    ?? [];
    $sortOrders = $_POST['item_sort']   ?? [];
    $toDelete   = $_POST['item_delete'] ?? []; // checkbox id

    // Chuẩn hóa mảng theo index
    $count = max(count($labels), count($hexes), count($ids));
    for ($i=0; $i<$count; $i++) {
      $id    = isset($ids[$i]) ? (int)$ids[$i] : 0;
      $label = trim($labels[$i] ?? '');
      $hex   = trim($hexes[$i] ?? '');
      $sort  = (int)($sortOrders[$i] ?? $i);

      // Nếu được chọn xóa
      if ($id > 0 && in_array((string)$id, $toDelete, true)) {
        $del = $pdo->prepare("DELETE FROM project_color_items WHERE id=? AND group_id=?");
        $del->execute([$id, $groupId]);
        continue;
      }

      // Bỏ qua dòng trống (không label + không hex)
      if ($label === '' && $hex === '') {
        continue;
      }

      // Validate hex (#RRGGBB hoặc #RRGGBBAA)
      if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $hex)) {
        $errors[] = "Mã màu không hợp lệ cho dòng “" . htmlspecialchars($label === '' ? $hex : $label) . "” (chấp nhận #RRGGBB hoặc #RRGGBBAA).";
        continue;
      }

      if ($id > 0) {
        // Update
        $upd = $pdo->prepare("UPDATE project_color_items
                              SET label=?, hex_color=?, sort_order=?, updated_at=NOW()
                              WHERE id=? AND group_id=?");
        $upd->execute([$label, $hex, $sort, $id, $groupId]);
      } else {
        // Insert
        $ins = $pdo->prepare("INSERT INTO project_color_items (group_id, label, hex_color, sort_order)
                              VALUES (?, ?, ?, ?)");
        $ins->execute([$groupId, $label, $hex, $sort]);
      }
    }

    if (!$errors) {
      $success = "Đã lưu các màu cho group.";
    }
  }
}

// ----------------------------------------------------
// Lấy danh sách group + items
// ----------------------------------------------------
$groups = [];
$stm = $pdo->prepare("SELECT * FROM project_color_groups WHERE project_id=? ORDER BY id DESC");
$stm->execute([$projectId]);
$groups = $stm->fetchAll(PDO::FETCH_ASSOC);

$itemsByGroup = [];
if ($groups) {
  $ids = array_column($groups, 'id');
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $sql = "SELECT * FROM project_color_items WHERE group_id IN ($in) ORDER BY sort_order ASC, id ASC";
  $stm = $pdo->prepare($sql);
  $stm->execute($ids);
  while ($row = $stm->fetch(PDO::FETCH_ASSOC)) {
    $itemsByGroup[$row['group_id']][] = $row;
  }
}

$BASE = $BASE ?? ''; // nếu có hằng BASE, dùng để link CSS
?>
<link rel="stylesheet" href="<?= htmlspecialchars($BASE) ?>/../assets/css/project_colors.css">

<div class="colors-tab">

  <?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-error">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= $e ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- KHU VỰC 1: Tạo group màu -->
  <section class="card">
    <div class="card-header">
      <h2>Color Groups</h2>
      <p class="sub">Tạo nhóm màu & quản lý các giá trị màu theo group.</p>
    </div>

    <?php if ($isManager): ?>
      <form method="POST" class="create-group-form">
        <input type="hidden" name="action" value="create_group">
        <div class="form-row">
          <label for="group_name">Tên group</label>
          <input id="group_name" name="group_name" type="text" placeholder="VD: Trạng thái tiến độ, Vật liệu, v.v.">
          <button type="submit" class="btn primary">Lưu group</button>
        </div>
      </form>
    <?php else: ?>
      <div class="note muted">Bạn không có quyền tạo/sửa. Dưới đây là danh sách nhóm màu hiện có.</div>
    <?php endif; ?>
  </section>

  <!-- KHU VỰC 2: Danh sách group + items -->
  <?php if (!$groups): ?>
    <section class="card">
      <div class="empty">Chưa có group màu nào.</div>
    </section>
  <?php else: ?>
    <?php foreach ($groups as $g): ?>
      <section class="card">
        <div class="card-header row">
          <h3 class="group-title"><?= htmlspecialchars($g['name']) ?></h3>
          <?php if ($isManager): ?>
            <button class="btn outline small add-row" type="button" data-group="<?= (int)$g['id'] ?>" title="Thêm một dòng">
              <span class="plus">+</span> Thêm dòng
            </button>
          <?php endif; ?>
        </div>

        <?php if ($isManager): ?>
          <form method="POST" class="items-form" autocomplete="off">
            <input type="hidden" name="action" value="save_items">
            <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
            <div class="table-wrap">
              <table class="color-table" data-group="<?= (int)$g['id'] ?>">
                <thead>
                  <tr>
                    <th style="width:32px;">#</th>
                    <th>Nội dung</th>
                    <th>Mã màu (#RRGGBB, #RRGGBBAA)</th>
                    <th style="width:120px;">Preview</th>
                    <th style="width:84px;">Thứ tự</th>
                    <th style="width:70px;">Xóa</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $rows = $itemsByGroup[$g['id']] ?? [];
                    if (!$rows) {
                      // hiển thị sẵn 1 dòng trống
                      $rows = [[ 'id'=>0, 'label'=>'', 'hex_color'=>'', 'sort_order'=>0 ]];
                    }
                    $idx = 0;
                    foreach ($rows as $row):
                      $idx++;
                  ?>
                    <tr>
                      <td class="index"><?= $idx ?></td>
                      <td>
                        <input type="hidden" name="item_id[]" value="<?= (int)($row['id'] ?? 0) ?>">
                        <input type="text" name="item_label[]" value="<?= htmlspecialchars($row['label'] ?? '') ?>" placeholder="VD: Đang làm, Chậm tiến độ, v.v.">
                      </td>
                      <td>
                        <input class="hex-input" type="text" name="item_hex[]" value="<?= htmlspecialchars($row['hex_color'] ?? '') ?>" placeholder="#RRGGBB">
                      </td>
                      <td>
                        <div class="swatch" data-preview></div>
                      </td>
                      <td>
                        <input class="sort-input" type="number" name="item_sort[]" value="<?= (int)($row['sort_order'] ?? 0) ?>">
                      </td>
                      <td class="t-center">
                        <?php if (!empty($row['id'])): ?>
                          <label class="chk">
                            <input type="checkbox" name="item_delete[]" value="<?= (int)$row['id'] ?>">
                            <span>Xóa</span>
                          </label>
                        <?php else: ?>
                          <span class="muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn primary">Lưu thay đổi</button>
            </div>
          </form>
        <?php else: ?>
          <div class="table-wrap">
            <table class="color-table readonly">
              <thead>
                <tr>
                  <th style="width:32px;">#</th>
                  <th>Nội dung</th>
                  <th>Mã màu</th>
                  <th style="width:140px;">Preview</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $rows = $itemsByGroup[$g['id']] ?? [];
                  $idx = 0;
                  foreach ($rows as $row):
                    $idx++;
                    $hex = htmlspecialchars($row['hex_color']);
                    $label = htmlspecialchars($row['label']);
                ?>
                  <tr>
                    <td class="index"><?= $idx ?></td>
                    <td><?= $label ?></td>
                    <td><code><?= $hex ?></code></td>
                    <td>
                      <div class="swatch large" style="--color: <?= $hex ?>"></div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                  <tr><td colspan="4" class="muted t-center">Chưa có màu nào.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if ($isManager): ?>
<script>
// Helper: áp màu preview cho 1 table row
function applyPreview(row) {
  var hexEl = row.querySelector('input.hex-input');
  var swatch = row.querySelector('[data-preview]');
  if (!hexEl || !swatch) return;
  var v = (hexEl.value || '').trim();
  // validate #RRGGBB hoặc #RRGGBBAA
  if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/.test(v)) {
    swatch.style.setProperty('--color', v);
    swatch.classList.remove('invalid');
  } else {
    swatch.style.removeProperty('--color');
    swatch.classList.add('invalid');
  }
}

// Áp preview cho tất cả rows hiện có
document.querySelectorAll('.color-table tbody tr').forEach(applyPreview);

// Lắng nghe thay đổi trường hex để cập nhật preview
document.addEventListener('input', function(e) {
  if (e.target && e.target.matches('input.hex-input')) {
    var row = e.target.closest('tr');
    if (row) applyPreview(row);
  }
});

// Nút thêm dòng mới trong group
document.querySelectorAll('.add-row').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var groupId = this.getAttribute('data-group');
    var table = document.querySelector('table.color-table[data-group="'+groupId+'"] tbody');
    if (!table) return;

    var idx = table.querySelectorAll('tr').length + 1;

    var tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="index">${idx}</td>
      <td>
        <input type="hidden" name="item_id[]" value="0">
        <input type="text" name="item_label[]" value="" placeholder="VD: Hoàn thành">
      </td>
      <td>
        <input class="hex-input" type="text" name="item_hex[]" value="" placeholder="#RRGGBB">
      </td>
      <td><div class="swatch" data-preview></div></td>
      <td><input class="sort-input" type="number" name="item_sort[]" value="${idx}"></td>
      <td class="t-center"><span class="muted">—</span></td>
    `;
    table.appendChild(tr);
    applyPreview(tr);
  });
});
</script>
<?php endif; ?>
