<?php
// /pages/partials/project_tab_daily.php
// Yêu cầu: file này được include từ project_view.php (đã có $pdo, $projectId, $userId)

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "DB context missing. Please include this partial via project_view.php.";
    exit;
}

/* ========================
   Helpers & Schema ensure
======================== */
function ensureDailyLogTables(PDO $pdo)
{
    // 1) Tạo bảng nếu chưa có (khung tối thiểu, KHÔNG chứa các cột tuỳ chọn)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_daily_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            code VARCHAR(64) NOT NULL,
            entry_date DATE NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX(project_id), INDEX(entry_date), INDEX(code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2) Vá cột còn thiếu (dò trong INFORMATION_SCHEMA)
    $colExists = function(string $table, string $col) use ($pdo): bool {
        $st = $pdo->prepare("
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $st->execute([$table, $col]);
        return (bool)$st->fetchColumn();
    };
    $add = function(string $sql) use ($pdo) { $pdo->exec($sql); };

    // --- các cột logic của Daily Logs ---
    if (!$colExists('project_daily_logs','approval_group_id')) $add("ALTER TABLE project_daily_logs ADD COLUMN approval_group_id INT NULL AFTER created_by");
    if (!$colExists('project_daily_logs','status'))            $add("ALTER TABLE project_daily_logs ADD COLUMN status ENUM('pending','approved') NOT NULL DEFAULT 'pending' AFTER approval_group_id");

    if (!$colExists('project_daily_logs','weather_morning'))   $add("ALTER TABLE project_daily_logs ADD COLUMN weather_morning ENUM('sunny','cloudy','rain') NULL AFTER status");
    if (!$colExists('project_daily_logs','weather_afternoon')) $add("ALTER TABLE project_daily_logs ADD COLUMN weather_afternoon ENUM('sunny','cloudy','rain') NULL AFTER weather_morning");
    if (!$colExists('project_daily_logs','weather_evening'))   $add("ALTER TABLE project_daily_logs ADD COLUMN weather_evening ENUM('cloudy','rain') NULL AFTER weather_afternoon");
    if (!$colExists('project_daily_logs','weather_night'))     $add("ALTER TABLE project_daily_logs ADD COLUMN weather_night   ENUM('cloudy','rain') NULL AFTER weather_evening");

    if (!$colExists('project_daily_logs','site_cleanliness'))  $add("ALTER TABLE project_daily_logs ADD COLUMN site_cleanliness ENUM('good','normal','poor') NOT NULL DEFAULT 'normal' AFTER weather_night");
    if (!$colExists('project_daily_logs','labor_safety'))      $add("ALTER TABLE project_daily_logs ADD COLUMN labor_safety     ENUM('good','normal','poor') NOT NULL DEFAULT 'normal' AFTER site_cleanliness");

    if (!$colExists('project_daily_logs','work_detail'))       $add("ALTER TABLE project_daily_logs ADD COLUMN work_detail TEXT NULL AFTER labor_safety");

    // 3) Bảng chi tiết: thiết bị, lao động, ảnh (tạo nếu chưa có)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_daily_log_equipment (
            id INT AUTO_INCREMENT PRIMARY KEY,
            daily_log_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            qty DECIMAL(18,3) NOT NULL DEFAULT 0,
            INDEX(daily_log_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_daily_log_labor (
            id INT AUTO_INCREMENT PRIMARY KEY,
            daily_log_id INT NOT NULL,
            person_name VARCHAR(255) NOT NULL,
            qty DECIMAL(18,3) NOT NULL DEFAULT 0,
            INDEX(daily_log_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_daily_log_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            daily_log_id INT NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            INDEX(daily_log_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 4) (tuỳ chọn) thêm index cho status nếu thiếu
    try { $add("CREATE INDEX idx_pdl_status ON project_daily_logs (status)"); } catch (\Throwable $e) {}
}
function isProjectMember(PDO $pdo, int $projectId, int $userId): bool {
    // là member của project (có trong project_group_members)
    $st = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND user_id=? LIMIT 1");
    $st->execute([$projectId, $userId]);
    if ($st->fetchColumn()) return true;

    // cho phép owner project (nếu schema có) – không bắt buộc
    try {
        $st2 = $pdo->prepare("SELECT 1 FROM projects WHERE id=? AND (created_by=? OR owner_id=?) LIMIT 1");
        $st2->execute([$projectId, $userId, $userId]);
        if ($st2->fetchColumn()) return true;
    } catch (\Throwable $e) {}
    return false;
}
function getUserFullName(PDO $pdo, int $userId): string {
    $st = $pdo->prepare("SELECT CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) FROM users WHERE id=? LIMIT 1");
    $st->execute([$userId]);
    return trim((string)($st->fetchColumn() ?: 'Unknown'));
}
function getProjectCode(PDO $pdo, int $projectId): string {
    $st = $pdo->prepare("SELECT code FROM projects WHERE id=? LIMIT 1");
    $st->execute([$projectId]);
    return (string)($st->fetchColumn() ?: ('PRJ'.str_pad((string)$projectId,6,'0',STR_PAD_LEFT)));
}
function approvalGroups(PDO $pdo, int $projectId): array {
    try {
        $st = $pdo->prepare("SELECT id,name FROM project_groups WHERE project_id=? ORDER BY name");
        $st->execute([$projectId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) { return []; }
}
function uploadsDirForProject(PDO $pdo, int $projectId): string {
    $code = getProjectCode($pdo, $projectId);
    $root = realpath(__DIR__.'/../../..'); // project root
    $dir  = $root.'/uploads/projects/'.$code.'/daily_logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}
function sendApprovalNotifications(PDO $pdo, int $senderId, int $groupId, string $entryDate): void {
    $mem = $pdo->prepare("SELECT user_id FROM project_group_members WHERE group_id=?");
    $mem->execute([$groupId]);
    $users = $mem->fetchAll(PDO::FETCH_COLUMN,0);
    if (!$users) return;
    $ins = $pdo->prepare("INSERT INTO notifications (sender_id, receiver_id, entry_date, created_at, is_read) VALUES (:s,:r,:d, CURRENT_TIMESTAMP, 0)");
    foreach ($users as $uid) {
        if ((int)$uid === (int)$senderId) continue;
        $ins->execute([':s'=>$senderId, ':r'=>(int)$uid, ':d'=>$entryDate]);
    }
}

/* ========================
   Bootstrap & permissions
======================== */
ensureDailyLogTables($pdo);
$canEdit     = isProjectMember($pdo, (int)$projectId, (int)$userId);
$groups      = approvalGroups($pdo, (int)$projectId);
$projectCode = htmlspecialchars(getProjectCode($pdo, (int)$projectId));

/* ========================
   AJAX (qua project_view.php?ajax=daily)
   Lưu ý: Danh sách KHÔNG dùng AJAX (render sẵn).
======================== */
$__isAjax = (isset($_GET['ajax']) && $_GET['ajax']==='daily') || (isset($_GET['action']) && $_GET['action']!=='');
if ($__isAjax) {
    while (ob_get_level()) { ob_end_clean(); }
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');

    // Debug quick-check (tùy chọn): ?ajax=daily&action=whoami&project_id=...
    if (($_GET['action'] ?? '') === 'whoami') {
        $st = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id = ? AND user_id = ? LIMIT 1");
        $st->execute([(int)$projectId, (int)$userId]);
        echo json_encode([
            'ok'=>true,
            'project_id'=>(int)$projectId,
            'user_id'=>(int)$userId,
            'is_member'=> (bool)$st->fetchColumn()
        ]);
        exit;
    }

    $action = $_GET['action'] ?? '';
    if ($action === 'get_log') {
        $id = (int)($_GET['id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM project_daily_logs WHERE id=? AND project_id=? LIMIT 1");
        $st->execute([$id, $projectId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['ok'=>false,'message'=>'Log not found']); exit; }

        $eq = $pdo->prepare("SELECT item_name, qty FROM project_daily_log_equipment WHERE daily_log_id=?");
        $eq->execute([$id]); $equipment = $eq->fetchAll(PDO::FETCH_ASSOC);
        $lb = $pdo->prepare("SELECT person_name, qty FROM project_daily_log_labor WHERE daily_log_id=?");
        $lb->execute([$id]); $labor = $lb->fetchAll(PDO::FETCH_ASSOC);
        $im = $pdo->prepare("SELECT file_path FROM project_daily_log_images WHERE daily_log_id=?");
        $im->execute([$id]); $images = $im->fetchAll(PDO::FETCH_COLUMN,0);

        $editable = ($row['status']==='pending' && (int)$row['created_by']===(int)$userId);
        echo json_encode(['ok'=>true,'data'=>$row,'equipment'=>$equipment,'labor'=>$labor,'images'=>$images,'editable'=>$editable]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!$canEdit) { echo json_encode(['ok'=>false,'message'=>'Access denied.']); exit; }

        $dlAction = $_POST['dl_action'] ?? '';
        if ($dlAction === 'create') {
            $code = trim($_POST['code'] ?? '');
            $entry_date = $_POST['entry_date'] ?? date('Y-m-d');
            $name = trim($_POST['name'] ?? '');
            $approval_group_id = (int)($_POST['approval_group_id'] ?? 0);
            $w_m = $_POST['weather_morning'] ?? null;
            $w_a = $_POST['weather_afternoon'] ?? null;
            $w_e = $_POST['weather_evening'] ?? null;
            $w_n = $_POST['weather_night'] ?? null;
            $site_cleanliness = $_POST['site_cleanliness'] ?? 'normal';
            $labor_safety     = $_POST['labor_safety'] ?? 'normal';
            $work_detail      = $_POST['work_detail'] ?? null;

            if ($code==='' || $name==='') { echo json_encode(['ok'=>false,'message'=>'Code and Name are required.']); exit; }

            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO project_daily_logs
                    (project_id, code, entry_date, name, created_by, approval_group_id, status,
                     weather_morning, weather_afternoon, weather_evening, weather_night,
                     site_cleanliness, labor_safety, work_detail)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute([
                    $projectId, $code, $entry_date, $name, $userId, $approval_group_id ?: null, 'pending',
                    $w_m ?: null, $w_a ?: null, $w_e ?: null, $w_n ?: null,
                    $site_cleanliness, $labor_safety, $work_detail
                ]);
                $dailyId = (int)$pdo->lastInsertId();

                // equipment
                $eqNames = $_POST['eq_name'] ?? [];
                $eqQtys  = $_POST['eq_qty'] ?? [];
                if (is_array($eqNames)) {
                    $insEq = $pdo->prepare("INSERT INTO project_daily_log_equipment (daily_log_id, item_name, qty) VALUES (?,?,?)");
                    foreach ($eqNames as $i=>$nm) {
                        $nm = trim((string)$nm); if ($nm==='') continue;
                        $qty = (float)($eqQtys[$i] ?? 0);
                        $insEq->execute([$dailyId, $nm, $qty]);
                    }
                }
                // labor
                $lbNames = $_POST['lb_name'] ?? [];
                $lbQtys  = $_POST['lb_qty'] ?? [];
                if (is_array($lbNames)) {
                    $insLb = $pdo->prepare("INSERT INTO project_daily_log_labor (daily_log_id, person_name, qty) VALUES (?,?,?)");
                    foreach ($lbNames as $i=>$nm) {
                        $nm = trim((string)$nm); if ($nm==='') continue;
                        $qty = (float)($lbQtys[$i] ?? 0);
                        $insLb->execute([$dailyId, $nm, $qty]);
                    }
                }
                // images (max 4)
                if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
                    $dir = uploadsDirForProject($pdo, (int)$projectId);
                    $count = 0;
                    for ($i=0;$i<count($_FILES['images']['name']);$i++) {
                        if ($count >= 4) break;
                        $err = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                        if ($err !== UPLOAD_ERR_OK) continue;
                        $tmp  = $_FILES['images']['tmp_name'][$i];
                        $orig = basename((string)$_FILES['images']['name'][$i]);
                        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                        $safe = 'dl_' . $dailyId . '_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                        $dest = rtrim($dir,'/') . '/' . $safe;
                        if (@move_uploaded_file($tmp, $dest)) {
                            $rel = '/uploads/projects/' . getProjectCode($pdo, (int)$projectId) . '/daily_logs/' . $safe;
                            $pdo->prepare("INSERT INTO project_daily_log_images (daily_log_id, file_path) VALUES (?,?)")
                                ->execute([$dailyId, $rel]);
                            $count++;
                        }
                    }
                }

                $pdo->commit();
                if ($approval_group_id) {
                    sendApprovalNotifications($pdo, (int)$userId, (int)$approval_group_id, $entry_date);
                }
                echo json_encode(['ok'=>true,'message'=>'Daily log created successfully.']);
            } catch (\Throwable $e) {
                $pdo->rollBack();
                echo json_encode(['ok'=>false,'message'=>'Failed to create daily log: '.$e->getMessage()]);
            }
            exit;
        }

        if ($dlAction === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare("SELECT * FROM project_daily_logs WHERE id=? AND project_id=? LIMIT 1");
            $st->execute([$id, $projectId]);
            $cur = $st->fetch(PDO::FETCH_ASSOC);
            if (!$cur) { echo json_encode(['ok'=>false,'message'=>'Log not found']); exit; }
            if ($cur['status']!=='pending') { echo json_encode(['ok'=>false,'message'=>'This log has been approved and cannot be edited.']); exit; }
            if ((int)$cur['created_by'] !== (int)$userId) { echo json_encode(['ok'=>false,'message'=>'Only the creator can edit this log.']); exit; }

            $code = trim($_POST['code'] ?? $cur['code']);
            $entry_date = $_POST['entry_date'] ?? $cur['entry_date'];
            $name = trim($_POST['name'] ?? $cur['name']);
            $approval_group_id = (int)($_POST['approval_group_id'] ?? ($cur['approval_group_id'] ?? 0));
            $w_m = $_POST['weather_morning'] ?? $cur['weather_morning'];
            $w_a = $_POST['weather_afternoon'] ?? $cur['weather_afternoon'];
            $w_e = $_POST['weather_evening'] ?? $cur['weather_evening'];
            $w_n = $_POST['weather_night'] ?? $cur['weather_night'];
            $site_cleanliness = $_POST['site_cleanliness'] ?? $cur['site_cleanliness'];
            $labor_safety     = $_POST['labor_safety'] ?? $cur['labor_safety'];
            $work_detail      = $_POST['work_detail'] ?? $cur['work_detail'];

            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    UPDATE project_daily_logs SET
                        code=?, entry_date=?, name=?, approval_group_id=?,
                        weather_morning=?, weather_afternoon=?, weather_evening=?, weather_night=?,
                        site_cleanliness=?, labor_safety=?, work_detail=?
                    WHERE id=? AND project_id=?
                ")->execute([
                    $code, $entry_date, $name, $approval_group_id ?: null,
                    $w_m ?: null, $w_a ?: null, $w_e ?: null, $w_n ?: null,
                    $site_cleanliness, $labor_safety, $work_detail,
                    $id, $projectId
                ]);

                // replace lines
                $pdo->prepare("DELETE FROM project_daily_log_equipment WHERE daily_log_id=?")->execute([$id]);
                $eqNames = $_POST['eq_name'] ?? [];
                $eqQtys  = $_POST['eq_qty'] ?? [];
                if (is_array($eqNames)) {
                    $insEq = $pdo->prepare("INSERT INTO project_daily_log_equipment (daily_log_id, item_name, qty) VALUES (?,?,?)");
                    foreach ($eqNames as $i=>$nm) {
                        $nm = trim((string)$nm); if ($nm==='') continue;
                        $qty = (float)($eqQtys[$i] ?? 0);
                        $insEq->execute([$id, $nm, $qty]);
                    }
                }

                $pdo->prepare("DELETE FROM project_daily_log_labor WHERE daily_log_id=?")->execute([$id]);
                $lbNames = $_POST['lb_name'] ?? [];
                $lbQtys  = $_POST['lb_qty'] ?? [];
                if (is_array($lbNames)) {
                    $insLb = $pdo->prepare("INSERT INTO project_daily_log_labor (daily_log_id, person_name, qty) VALUES (?,?,?)");
                    foreach ($lbNames as $i=>$nm) {
                        $nm = trim((string)$nm); if ($nm==='') continue;
                        $qty = (float)($lbQtys[$i] ?? 0);
                        $insLb->execute([$id, $nm, $qty]);
                    }
                }

                // images (append lên tối đa 4)
                $curCount = (int)$pdo->query("SELECT COUNT(*) FROM project_daily_log_images WHERE daily_log_id={$id}")->fetchColumn();
                if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
                    $dir = uploadsDirForProject($pdo, (int)$projectId);
                    for ($i=0;$i<count($_FILES['images']['name']);$i++) {
                        if ($curCount >= 4) break;
                        $err = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                        if ($err !== UPLOAD_ERR_OK) continue;
                        $tmp  = $_FILES['images']['tmp_name'][$i];
                        $orig = basename((string)$_FILES['images']['name'][$i]);
                        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                        $safe = 'dl_' . $id . '_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                        $dest = rtrim($dir,'/') . '/' . $safe;
                        if (@move_uploaded_file($tmp, $dest)) {
                            $rel = '/uploads/projects/' . getProjectCode($pdo, (int)$projectId) . '/daily_logs/' . $safe;
                            $pdo->prepare("INSERT INTO project_daily_log_images (daily_log_id, file_path) VALUES (?,?)")
                                ->execute([$id, $rel]);
                            $curCount++;
                        }
                    }
                }

                $pdo->commit();
                echo json_encode(['ok'=>true,'message'=>'Daily log updated successfully.']);
            } catch (\Throwable $e) {
                $pdo->rollBack();
                echo json_encode(['ok'=>false,'message'=>'Failed to update: '.$e->getMessage()]);
            }
            exit;
        }

        if ($dlAction === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare("SELECT * FROM project_daily_logs WHERE id=? AND project_id=? LIMIT 1");
            $st->execute([$id, $projectId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['ok'=>false,'message'=>'Log not found']); exit; }
            if ($row['status']!=='pending') { echo json_encode(['ok'=>false,'message'=>'Approved log cannot be deleted.']); exit; }
            if ((int)$row['created_by'] !== (int)$userId) { echo json_encode(['ok'=>false,'message'=>'Only the creator can delete this log.']); exit; }

            $pdo->beginTransaction();
            try {
                $imgs = $pdo->prepare("SELECT file_path FROM project_daily_log_images WHERE daily_log_id=?");
                $imgs->execute([$id]);
                $root = realpath(__DIR__.'/../../..');
                foreach ($imgs->fetchAll(PDO::FETCH_COLUMN,0) as $rel) {
                    $abs = $root . $rel;
                    @unlink($abs);
                }
                $pdo->prepare("DELETE FROM project_daily_log_images WHERE daily_log_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM project_daily_log_equipment WHERE daily_log_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM project_daily_log_labor WHERE daily_log_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM project_daily_logs WHERE id=?")->execute([$id]);
                $pdo->commit();
                echo json_encode(['ok'=>true,'message'=>'Daily log deleted.']);
            } catch (\Throwable $e) {
                $pdo->rollBack();
                echo json_encode(['ok'=>false,'message'=>'Delete failed: '.$e->getMessage()]);
            }
            exit;
        }

        echo json_encode(['ok'=>false,'message'=>'Unknown action.']);
        exit;
    }

    if ($action === 'export') {
        header_remove('Content-Type');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="DailyLogs_'.$projectId.'_'.date('Ymd').'.csv"');

        $q = trim($_GET['q'] ?? '');
        $sql = "SELECT * FROM project_daily_logs WHERE project_id = :pid";
        $args = [':pid'=>$projectId];
        if ($q !== '') { $sql .= " AND name LIKE :q"; $args[':q'] = "%{$q}%"; }
        $sql .= " ORDER BY entry_date DESC, id DESC";
        $stm = $pdo->prepare($sql); $stm->execute($args);

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Code','Date','Name','Created By','Approval Group','Status']);
        while ($r = $stm->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $r['code'], $r['entry_date'], $r['name'],
                getUserFullName($pdo, (int)$r['created_by']),
                (int)($r['approval_group_id'] ?? 0),
                strtoupper($r['status'])
            ]);
        }
        fclose($out);
        exit;
    }

    echo json_encode(['ok'=>false,'message'=>'Unknown action.']);
    exit;
}

/* ========================
   Render danh sách sẵn trong PHP
======================== */
$sql = "SELECT * FROM project_daily_logs WHERE project_id = :pid ORDER BY entry_date DESC, id DESC";
$stm = $pdo->prepare($sql); $stm->execute([':pid'=>$projectId]);
$rows = $stm->fetchAll(PDO::FETCH_ASSOC);

$selfUrl = $_SERVER['PHP_SELF']; // dùng làm base cho ajax/export
?>
<link rel="stylesheet" href="../assets/css/project_tab_daily.css?v=<?= time() ?>">

<div id="daily-tab"
     data-project-id="<?= (int)$projectId ?>"
     data-can-edit="<?= $canEdit ? '1' : '0' ?>"
     data-ajax-base="<?= htmlspecialchars($selfUrl) ?>">

  <?php if (!$canEdit): ?>
    <div class="dl-alert dl-alert-warn">
      <i class="fas fa-lock"></i> You are not a member of this project. Editing is disabled; you can only view logs.
    </div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="dl-toolbar">
    <div class="dl-left">
      <button type="button" class="dl-btn dl-btn-primary" id="dl-btn-create" <?= !$canEdit?'disabled':'' ?>>
        <i class="fas fa-plus-circle"></i> New Log
      </button>
      <a class="dl-btn dl-btn-secondary" id="dl-btn-export"
         href="<?= htmlspecialchars($selfUrl) ?>?ajax=daily&action=export&project_id=<?= (int)$projectId ?>" target="_blank">
        <i class="fas fa-file-export"></i> Export
      </a>
    </div>
    <div class="dl-right">
      <input id="dl-search" class="dl-search" type="text" placeholder="Search by Log Name...">
    </div>
  </div>

  <!-- Table -->
  <div class="dl-table-wrap">
    <table class="dl-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Date</th>
          <th>Name</th>
          <th>Created By</th>
          <th>Approve Unit</th>
          <th>Status</th>
          <th>Delete</th>
        </tr>
      </thead>
      <tbody id="dl-tbody">
        <?php if (!$rows): ?>
          <tr><td colspan="7"><em>No data</em></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r):
            $creator = htmlspecialchars(getUserFullName($pdo, (int)$r['created_by']));
            $statusBadge = $r['status']==='approved'
              ? '<span class="dl-badge dl-badge-ok"><i class="fas fa-check-circle"></i> Approved</span>'
              : '<span class="dl-badge dl-badge-warn"><i class="fas fa-hourglass-half"></i> Pending</span>';
          ?>
          <tr class="dl-row"
              data-id="<?= (int)$r['id'] ?>"
              data-name="<?= htmlspecialchars($r['name']) ?>"
              data-person="<?= $creator ?>"
              data-status="<?= htmlspecialchars($r['status']) ?>">
            <td><?= htmlspecialchars($r['code']) ?></td>
            <td><?= htmlspecialchars($r['entry_date']) ?></td>
            <td><a href="#" class="dl-open" data-id="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></a></td>
            <td><?= $creator ?></td>
            <td><?= (int)($r['approval_group_id'] ?? 0) ?></td>
            <td><?= $statusBadge ?></td>
            <td>
              <button type="button" class="dl-btn dl-btn-danger dl-btn-xs dl-delete"
                      data-id="<?= (int)$r['id'] ?>"
                      <?= ($r['status']!=='pending' || (int)$r['created_by']!==(int)$userId) ? 'disabled':'' ?>>
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div id="dl-modal" class="dl-modal" style="display:none" aria-hidden="true">
  <div class="dl-modal-dialog">
    <div class="dl-modal-header">
      <h3><i class="fas fa-book"></i> Daily Log</h3>
      <button type="button" class="dl-close" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <form id="dl-form" enctype="multipart/form-data">
      <input type="hidden" name="dl_action" value="create">
      <input type="hidden" name="id" value="">

      <!-- KV1 -->
      <div class="dl-grid">
        <div class="kv">
          <label>Code</label>
          <input name="code" type="text" required placeholder="e.g. <?= $projectCode ?>-DL-001">
        </div>
        <div class="kv">
          <label>Date</label>
          <input name="entry_date" type="date" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="kv" style="grid-column:span 2;">
          <label>Name</label>
          <input name="name" type="text" required placeholder="Daily log title">
        </div>
      </div>

      <!-- KV2 Weather -->
      <fieldset class="dl-fieldset">
        <legend><i class="fas fa-cloud-sun"></i> Weather</legend>
        <div class="dl-grid dl-grid-4">
          <div class="kv">
            <label>Morning</label>
            <select name="weather_morning">
              <option value="">--</option><option value="sunny">Sunny</option><option value="cloudy">Cloudy</option><option value="rain">Rain</option>
            </select>
          </div>
          <div class="kv">
            <label>Afternoon</label>
            <select name="weather_afternoon">
              <option value="">--</option><option value="sunny">Sunny</option><option value="cloudy">Cloudy</option><option value="rain">Rain</option>
            </select>
          </div>
          <div class="kv">
            <label>Evening</label>
            <select name="weather_evening">
              <option value="">--</option><option value="cloudy">Cloudy</option><option value="rain">Rain</option>
            </select>
          </div>
          <div class="kv">
            <label>Night</label>
            <select name="weather_night">
              <option value="">--</option><option value="cloudy">Cloudy</option><option value="rain">Rain</option>
            </select>
          </div>
        </div>
      </fieldset>

      <!-- KV3 Equipment -->
      <fieldset class="dl-fieldset">
        <legend><i class="fas fa-truck-loading"></i> Equipment</legend>
        <div id="dl-eq-list"></div>
        <button type="button" class="dl-btn dl-btn-link" id="dl-eq-add"><i class="fas fa-plus"></i> Add equipment</button>
      </fieldset>

      <!-- KV4 Labor -->
      <fieldset class="dl-fieldset">
        <legend><i class="fas fa-hard-hat"></i> Labor</legend>
        <div id="dl-lb-list"></div>
        <button type="button" class="dl-btn dl-btn-link" id="dl-lb-add"><i class="fas fa-plus"></i> Add labor</button>
      </fieldset>

      <!-- KV5 -->
      <div class="kv">
        <label><i class="fas fa-tasks"></i> Work Details</label>
        <textarea name="work_detail" rows="4" placeholder="Describe the work done..."></textarea>
      </div>

      <!-- KV6 Images -->
      <div class="kv">
        <label><i class="fas fa-images"></i> Site Photos (max 4)</label>
        <input name="images[]" type="file" accept="image/*" multiple>
        <div class="dl-images-hint">Images are stored privately under <?= htmlspecialchars('/uploads/projects/'.$projectCode.'/daily_logs/') ?> and not shown in Files tab.</div>
      </div>

      <!-- KV7 -->
      <fieldset class="dl-fieldset">
        <legend><i class="fas fa-clipboard-check"></i> Site Conditions</legend>
        <div class="dl-grid dl-grid-2">
          <div class="kv">
            <label><i class="fas fa-broom"></i> Site Cleanliness</label>
            <select name="site_cleanliness">
              <option value="good">Good</option><option value="normal" selected>Normal</option><option value="poor">Poor</option>
            </select>
          </div>
          <div class="kv">
            <label><i class="fas fa-shield-alt"></i> Labor Safety</label>
            <select name="labor_safety">
              <option value="good">Good</option><option value="normal" selected>Normal</option><option value="poor">Poor</option>
            </select>
          </div>
        </div>
      </fieldset>

      <!-- KV8 -->
      <div class="kv">
        <label><i class="fas fa-users-cog"></i> Approve Unit (group)</label>
        <select name="approval_group_id">
          <option value="">-- Select a group --</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="dl-modal-actions">
        <button type="submit" class="dl-btn dl-btn-primary"><i class="fas fa-save"></i> Create</button>
        <button type="button" class="dl-btn dl-btn-secondary dl-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script defer src="../assets/js/project_tab_daily.js?v=<?= time() ?>"></script>
