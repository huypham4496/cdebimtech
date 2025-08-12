<?php
// /pages/partials/project_tab_materials.php
// Assumptions: $pdo, $projectId, $userId are available from project_view.php before including this partial.

// ---------- helpers ----------
function ensureMaterialsTables(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_material_in (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(64) NOT NULL,
            supplier VARCHAR(255) DEFAULT NULL,
            warehouse VARCHAR(255) DEFAULT NULL,
            qty_in DECIMAL(18,3) NOT NULL DEFAULT 0,
            unit VARCHAR(32) NOT NULL,
            received_date DATE NOT NULL,
            receiver_user_id INT NOT NULL,
            created_by INT NOT NULL,
            updated_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX(project_id), INDEX(code), INDEX(received_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_material_out (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(64) NOT NULL,
            qty_out DECIMAL(18,3) NOT NULL DEFAULT 0,
            unit VARCHAR(32) NOT NULL,
            content TEXT DEFAULT NULL,
            out_date DATE DEFAULT NULL,
            issuer_user_id INT DEFAULT NULL,
            created_by INT NOT NULL,
            updated_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_project_code_name (project_id, code, name, unit),
            INDEX(project_id), INDEX(code), INDEX(out_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

function isProjectMember(PDO $pdo, int $projectId, int $userId): bool
{
    $st = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id = ? AND user_id = ? LIMIT 1");
    $st->execute([$projectId, $userId]);
    return (bool)$st->fetchColumn();
}
function getUserFullName(PDO $pdo, int $userId): string
{
    $st = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
    $st->execute([$userId]);
    return (string)($st->fetchColumn() ?: 'Unknown');
}
function sumOutByCode(PDO $pdo, int $projectId): array
{
    $st = $pdo->prepare("SELECT code, COALESCE(SUM(qty_out),0) AS total_out FROM project_material_out WHERE project_id = ? GROUP BY code");
    $st->execute([$projectId]);
    $map = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $map[$r['code']] = (float)$r['total_out'];
    }
    return $map;
}

// ---------- bootstrap ----------
ensureMaterialsTables($pdo);
$canEdit = isProjectMember($pdo, (int)$projectId, (int)$userId);
$currentUserFullname = getUserFullName($pdo, (int)$userId);

$errors = [];
$messages = [];

function requireCanEdit($canEdit)
{
    if (!$canEdit) {
        throw new RuntimeException("Bạn không có quyền thực hiện thao tác này (chỉ thành viên trong dự án).");
    }
}

// ---------- handle POST actions (non-AJAX, postback to project_view) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['materials_action'])) {
    try {
        $action = $_POST['materials_action'];

        if ($action === 'create_in') {
            requireCanEdit($canEdit);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $supplier = trim($_POST['supplier'] ?? '');
            $warehouse = trim($_POST['warehouse'] ?? '');
            $unit = trim($_POST['unit'] ?? '');
            $qty_in = (float)($_POST['qty_in'] ?? 0);
            $received_date = $_POST['received_date'] ?? date('Y-m-d');

            if ($name === '' || $code === '' || $unit === '' || $qty_in < 0) {
                throw new InvalidArgumentException("Thiếu hoặc sai dữ liệu bắt buộc.");
            }

            $st = $pdo->prepare("INSERT INTO project_material_in
                (project_id, name, code, supplier, warehouse, qty_in, unit, received_date, receiver_user_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $st->execute([$projectId, $name, $code, $supplier, $warehouse, $qty_in, $unit, $received_date, $userId, $userId]);

            // ensure a corresponding OUT row exists with qty_out = 0 for this code+name+unit
            $st2 = $pdo->prepare("INSERT IGNORE INTO project_material_out
                (project_id, name, code, qty_out, unit, content, out_date, issuer_user_id, created_by)
                VALUES (?, ?, ?, 0, ?, NULL, NULL, NULL, ?)");
            $st2->execute([$projectId, $name, $code, $unit, $userId]);

            $messages[] = "Đã tạo bản ghi Nhập vật tư.";
        }
        elseif ($action === 'update_in') {
            requireCanEdit($canEdit);
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $supplier = trim($_POST['supplier'] ?? '');
            $warehouse = trim($_POST['warehouse'] ?? '');
            $unit = trim($_POST['unit'] ?? '');
            $qty_in = (float)($_POST['qty_in'] ?? 0);
            $received_date = $_POST['received_date'] ?? date('Y-m-d');

            if ($id <= 0 || $name === '' || $code === '' || $unit === '' || $qty_in < 0) {
                throw new InvalidArgumentException("Thiếu hoặc sai dữ liệu cập nhật.");
            }

            $st = $pdo->prepare("UPDATE project_material_in
                SET name=?, code=?, supplier=?, warehouse=?, qty_in=?, unit=?, received_date=?, updated_by=?
                WHERE id=? AND project_id=?");
            $st->execute([$name, $code, $supplier, $warehouse, $qty_in, $unit, $received_date, $userId, $id, $projectId]);

            // also upsert an OUT row shell for the (code,name,unit)
            $st2 = $pdo->prepare("INSERT IGNORE INTO project_material_out
                (project_id, name, code, qty_out, unit, created_by)
                VALUES (?, ?, ?, 0, ?, ?)");
            $st2->execute([$projectId, $name, $code, $unit, $userId]);

            $messages[] = "Đã cập nhật bản ghi Nhập vật tư.";
        }
        elseif ($action === 'delete_in') {
            requireCanEdit($canEdit);
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException("Thiếu ID để xoá.");
            $st = $pdo->prepare("DELETE FROM project_material_in WHERE id=? AND project_id=?");
            $st->execute([$id, $projectId]);
            $messages[] = "Đã xoá bản ghi Nhập vật tư.";
        }
        elseif ($action === 'create_out') {
            requireCanEdit($canEdit);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $unit = trim($_POST['unit'] ?? '');
            $qty_out = (float)($_POST['qty_out'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $out_date = $_POST['out_date'] ?: date('Y-m-d');

            if ($name === '' || $code === '' || $unit === '' || $qty_out < 0) {
                throw new InvalidArgumentException("Thiếu hoặc sai dữ liệu bắt buộc (Xuất).");
            }

            // Try update existing shell row if exists (same project+code+name+unit and qty_out currently 0)
            $stChk = $pdo->prepare("SELECT id FROM project_material_out WHERE project_id=? AND code=? AND name=? AND unit=? LIMIT 1");
            $stChk->execute([$projectId, $code, $name, $unit]);
            $existingId = (int)($stChk->fetchColumn() ?: 0);

            if ($existingId) {
                $st = $pdo->prepare("UPDATE project_material_out
                    SET qty_out=?, content=?, out_date=?, issuer_user_id=?, updated_by=?
                    WHERE id=? AND project_id=?");
                $st->execute([$qty_out, $content, $out_date, $userId, $userId, $existingId, $projectId]);
            } else {
                $st = $pdo->prepare("INSERT INTO project_material_out
                    (project_id, name, code, qty_out, unit, content, out_date, issuer_user_id, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $st->execute([$projectId, $name, $code, $qty_out, $unit, $content, $out_date, $userId, $userId]);
            }

            $messages[] = "Đã tạo/cập nhật bản ghi Xuất vật tư.";
        }
        elseif ($action === 'update_out') {
            requireCanEdit($canEdit);
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $unit = trim($_POST['unit'] ?? '');
            $qty_out = (float)($_POST['qty_out'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $out_date = $_POST['out_date'] ?: date('Y-m-d');

            if ($id <= 0 || $name === '' || $code === '' || $unit === '' || $qty_out < 0) {
                throw new InvalidArgumentException("Thiếu hoặc sai dữ liệu cập nhật (Xuất).");
            }

            $st = $pdo->prepare("UPDATE project_material_out
                SET name=?, code=?, qty_out=?, unit=?, content=?, out_date=?, issuer_user_id=?, updated_by=?
                WHERE id=? AND project_id=?");
            $st->execute([$name, $code, $qty_out, $unit, $content, $out_date, $userId, $userId, $id, $projectId]);

            $messages[] = "Đã cập nhật bản ghi Xuất vật tư.";
        }
        else {
            throw new InvalidArgumentException("Hành động không hợp lệ.");
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// ---------- Query list data ----------
$sumOutMap = sumOutByCode($pdo, (int)$projectId);

// Nhập vật tư
$stIn = $pdo->prepare("
    SELECT i.*, u.first_name, u.last_name
    FROM project_material_in i
    LEFT JOIN users u ON u.id = i.receiver_user_id
    WHERE i.project_id = ?
    ORDER BY i.received_date DESC, i.id DESC
");
$stIn->execute([$projectId]);
$rowsIn = $stIn->fetchAll(PDO::FETCH_ASSOC);

// Xuất vật tư
$stOut = $pdo->prepare("
    SELECT o.*, u.first_name, u.last_name
    FROM project_material_out o
    LEFT JOIN users u ON u.id = o.issuer_user_id
    WHERE o.project_id = ?
    ORDER BY o.out_date DESC, o.id DESC
");
$stOut->execute([$projectId]);
$rowsOut = $stOut->fetchAll(PDO::FETCH_ASSOC);

// ---------- Access gate for non-members ----------
if (!$canEdit) {
    ?>
    <div class="materials-access-denied">
        <p>⚠️ Bạn không có quyền truy cập Tab Materials của dự án này (chỉ thành viên trong dự án mới được sửa/cập nhật).</p>
    </div>
    <?php
    // vẫn cho xem CSS để đồng bộ giao diện
    echo '<link rel="stylesheet" href="../assets/css/project_tab_materials.css?v='.time().'">';
    return;
}
?>

<link rel="stylesheet" href="../assets/css/project_tab_materials.css?v=<?= time() ?>">
<div id="materials-tab" data-project-id="<?= (int)$projectId ?>">

  <?php if ($errors): ?>
    <div class="mtl-alert mtl-alert-error">
      <?php foreach ($errors as $er): ?>
        <div><?= htmlspecialchars($er) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($messages): ?>
    <div class="mtl-alert mtl-alert-ok">
      <?php foreach ($messages as $ms): ?>
        <div><?= htmlspecialchars($ms) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Area 1: Controls -->
  <div class="mtl-toolbar">
    <div class="mtl-left">
      <label class="mtl-label">Chế độ:</label>
      <select id="mtl-mode" class="mtl-select">
        <option value="in">Nhập vật tư</option>
        <option value="out">Xuất vật tư</option>
      </select>
    </div>
    <div class="mtl-right">
      <input id="mtl-search" type="text" class="mtl-search" placeholder="Tìm theo Tên, Kho hoặc Người nhận / Người xuất...">
      <button type="button" class="mtl-btn mtl-btn-primary" id="mtl-btn-create">Create</button>
    </div>
  </div>

  <!-- Area 2: Tables -->
  <div class="mtl-tables">
    <!-- IN table -->
    <div id="mtl-table-in">
      <table class="mtl-table">
        <thead>
          <tr>
            <th>Tên</th>
            <th>Mã</th>
            <th>Đơn vị cung cấp</th>
            <th>Số lượng nhập</th>
            <th>Tồn kho</th>
            <th>Đơn vị</th>
            <th>Kho</th>
            <th>Ngày nhập</th>
            <th>Người nhận</th>
            <th>Xoá</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rowsIn as $r):
              $totalOut = $sumOutMap[$r['code']] ?? 0;
              $stock = (float)$r['qty_in'] - (float)$totalOut;
              $receiver = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
          ?>
            <tr class="mtl-row"
                data-mode="in"
                data-name="<?= htmlspecialchars($r['name']) ?>"
                data-warehouse="<?= htmlspecialchars($r['warehouse'] ?? '') ?>"
                data-person="<?= htmlspecialchars($receiver) ?>">
              <td>
                <a href="#" class="mtl-edit-in"
                   data-id="<?= (int)$r['id'] ?>"
                   data-name="<?= htmlspecialchars($r['name']) ?>"
                   data-code="<?= htmlspecialchars($r['code']) ?>"
                   data-supplier="<?= htmlspecialchars($r['supplier'] ?? '') ?>"
                   data-warehouse="<?= htmlspecialchars($r['warehouse'] ?? '') ?>"
                   data-qty_in="<?= htmlspecialchars($r['qty_in']) ?>"
                   data-unit="<?= htmlspecialchars($r['unit']) ?>"
                   data-received_date="<?= htmlspecialchars($r['received_date']) ?>"
                ><?= htmlspecialchars($r['name']) ?></a>
              </td>
              <td><?= htmlspecialchars($r['code']) ?></td>
              <td><?= htmlspecialchars($r['supplier'] ?? '') ?></td>
              <td class="ta-right"><?= number_format((float)$r['qty_in'], 3) ?></td>
              <td class="ta-right <?= $stock < 0 ? 'neg' : '' ?>"><?= number_format($stock, 3) ?></td>
              <td><?= htmlspecialchars($r['unit']) ?></td>
              <td><?= htmlspecialchars($r['warehouse'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['received_date']) ?></td>
              <td><?= htmlspecialchars($receiver) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Xoá bản ghi nhập này?')">
                  <input type="hidden" name="materials_action" value="delete_in">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="mtl-btn mtl-btn-danger mtl-btn-xs" type="submit">Xoá</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- OUT table -->
    <div id="mtl-table-out" style="display:none">
      <table class="mtl-table">
        <thead>
          <tr>
            <th>Tên</th>
            <th>Mã</th>
            <th>Số lượng xuất</th>
            <th>Đơn vị</th>
            <th>Nội dung xuất</th>
            <th>Ngày xuất</th>
            <th>Người xuất</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rowsOut as $r):
              $issuer = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
          ?>
            <tr class="mtl-row"
                data-mode="out"
                data-name="<?= htmlspecialchars($r['name']) ?>"
                data-person="<?= htmlspecialchars($issuer) ?>"
                data-content="<?= htmlspecialchars($r['content'] ?? '') ?>">
              <td>
                <a href="#" class="mtl-edit-out"
                   data-id="<?= (int)$r['id'] ?>"
                   data-name="<?= htmlspecialchars($r['name']) ?>"
                   data-code="<?= htmlspecialchars($r['code']) ?>"
                   data-qty_out="<?= htmlspecialchars($r['qty_out']) ?>"
                   data-unit="<?= htmlspecialchars($r['unit']) ?>"
                   data-content="<?= htmlspecialchars($r['content'] ?? '') ?>"
                   data-out_date="<?= htmlspecialchars($r['out_date'] ?? '') ?>"
                ><?= htmlspecialchars($r['name']) ?></a>
              </td>
              <td><?= htmlspecialchars($r['code']) ?></td>
              <td class="ta-right"><?= number_format((float)$r['qty_out'], 3) ?></td>
              <td><?= htmlspecialchars($r['unit']) ?></td>
              <td><?= htmlspecialchars($r['content'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['out_date'] ?? '') ?></td>
              <td><?= htmlspecialchars($issuer) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div> <!-- /tables -->
</div> <!-- /materials-tab -->

<!-- Modal (Create/Edit, reused) -->
<div id="mtl-modal" class="mtl-modal" style="display:none">
  <div class="mtl-modal-dialog">
    <div class="mtl-modal-head">
      <h3 id="mtl-modal-title">Create</h3>
      <button type="button" class="mtl-modal-close" id="mtl-modal-close">×</button>
    </div>
    <form id="mtl-form" method="post" class="mtl-form">
      <input type="hidden" name="materials_action" id="mtl-action" value="">
      <input type="hidden" name="id" id="mtl-id" value="">

      <div class="mtl-form-grid">
        <label>Name *</label>
        <input type="text" name="name" id="mtl-name" required>

        <label>Code *</label>
        <input type="text" name="code" id="mtl-code" required>

        <div class="mtl-group mtl-group-in">
          <label>Supplier</label>
          <input type="text" name="supplier" id="mtl-supplier">
        </div>

        <div class="mtl-group mtl-group-in">
          <label>Warehouse</label>
          <input type="text" name="warehouse" id="mtl-warehouse">
        </div>

        <label class="mtl-group-in">Qty In *</label>
        <input class="mtl-group-in" type="number" step="0.001" name="qty_in" id="mtl-qty-in">

        <label class="mtl-group-out">Qty Out *</label>
        <input class="mtl-group-out" type="number" step="0.001" name="qty_out" id="mtl-qty-out">

        <label>Unit *</label>
        <input type="text" name="unit" id="mtl-unit" required>

        <div class="mtl-group mtl-group-in">
          <label>Received date *</label>
          <input type="date" name="received_date" id="mtl-received-date">
        </div>

        <div class="mtl-group mtl-group-out">
          <label>Out date *</label>
          <input type="date" name="out_date" id="mtl-out-date">
        </div>

        <div class="mtl-group mtl-group-out" style="grid-column: 1 / -1;">
          <label>Out content</label>
          <textarea name="content" id="mtl-content" rows="2"></textarea>
        </div>

        <div class="mtl-readonly mtl-group-in">
          <label>Receiver</label>
          <input type="text" value="<?= htmlspecialchars($currentUserFullname) ?>" disabled>
        </div>

        <div class="mtl-readonly mtl-group-out">
          <label>Issuer</label>
          <input type="text" value="<?= htmlspecialchars($currentUserFullname) ?>" disabled>
        </div>
      </div>

      <div class="mtl-modal-foot">
        <button type="button" class="mtl-btn" id="mtl-cancel">Cancel</button>
        <button type="submit" class="mtl-btn mtl-btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/project_tab_materials.js?v=<?= time() ?>"></script>
