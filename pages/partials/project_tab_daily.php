<?php
/**
 * Daily Logs Tab (partial)
 * - $pdo và $projectId được project_view.php cấp sẵn.
 * - Xem list/chi tiết: ai đăng nhập cũng xem được.
 * - Tạo/Sửa/Xóa/Approve: chỉ thành viên project.
 * - Approved: không cho sửa/xóa.
 * - Ảnh: uploads/PRJ{code}/_daily_logs (tối đa 4 ảnh).
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($pdo) || !($pdo instanceof PDO) || !isset($projectId)) {
  http_response_code(500);
  echo "<div class='alert alert-danger'>Missing \$pdo or \$projectId. Ensure project_view.php sets them.</div>";
  exit;
}

/* ---- Current user ---- */
$currentUserId =
  $_SESSION['user']['id'] ?? $_SESSION['auth']['id'] ??
  $_SESSION['user_id']    ?? $_SESSION['id']        ?? 0;

/* ---- Helpers ---- */
function json_out($data, $code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data); exit;
}
function is_member(PDO $pdo, int $projectId, int $userId): bool {
  if ($userId<=0) return false;
  $s=$pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND user_id=? LIMIT 1");
  $s->execute([$projectId,$userId]); return (bool)$s->fetchColumn();
}
function assert_member_or_die(PDO $pdo, int $projectId, int $userId){
  if (!is_member($pdo,$projectId,$userId)) json_out(['ok'=>false,'message'=>'Access denied: you are not a member of this project.'],403);
}
function get_project_code(PDO $pdo, int $projectId): ?string {
  $s=$pdo->prepare("SELECT code FROM projects WHERE id=?"); $s->execute([$projectId]);
  $c=$s->fetchColumn(); return $c?:null;
}
function ensure_daily_tables(PDO $pdo){
  $pdo->exec("CREATE TABLE IF NOT EXISTS project_daily_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    code VARCHAR(32) NOT NULL,
    entry_date DATE NOT NULL,
    name VARCHAR(255) NOT NULL,
    weather_morning ENUM('sunny','cloudy','rainy') DEFAULT NULL,
    weather_afternoon ENUM('sunny','cloudy','rainy') DEFAULT NULL,
    weather_evening ENUM('clear','cloudy','rainy') DEFAULT NULL,
    weather_night ENUM('clear','cloudy','rainy') DEFAULT NULL,
    work_details TEXT DEFAULT NULL,
    cleanliness ENUM('good','normal','poor') NOT NULL DEFAULT 'normal',
    safety ENUM('good','normal','poor') NOT NULL DEFAULT 'normal',
    approval_group_id INT DEFAULT NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    approved_by INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_code_date (project_id, code, entry_date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS project_daily_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    daily_log_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    INDEX (daily_log_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS project_daily_labor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    daily_log_id INT NOT NULL,
    labor_name VARCHAR(255) NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    INDEX (daily_log_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS project_daily_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    daily_log_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    INDEX (daily_log_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
ensure_daily_tables($pdo);

/* ---- AJAX endpoints ---- */
$action = $_GET['action'] ?? null;
if ($action) {
  // LIST (no member restriction)
  if ($action === 'list') {
    $q = trim($_GET['q'] ?? '');
    $sql = "SELECT dl.*, u.first_name, u.last_name, pg.name AS group_name
            FROM project_daily_logs dl
            LEFT JOIN users u ON u.id=dl.created_by
            LEFT JOIN project_groups pg ON pg.id=dl.approval_group_id
            WHERE dl.project_id=? ";
    $params = [$projectId];
    if ($q !== '') { $sql .= " AND dl.name LIKE ? "; $params[] = "%$q%"; }
    $sql .= " ORDER BY dl.entry_date DESC, dl.id DESC LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_out(['ok'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
  }

  // READ (no member restriction)
  if ($action === 'read') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM project_daily_logs WHERE id=? AND project_id=?");
    $stmt->execute([$id,$projectId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$log) json_out(['ok'=>false,'message'=>'Not found'], 404);

    $eq = $pdo->prepare("SELECT id,item_name,qty FROM project_daily_equipment WHERE daily_log_id=?"); $eq->execute([$id]);
    $lb = $pdo->prepare("SELECT id,labor_name,qty FROM project_daily_labor WHERE daily_log_id=?"); $lb->execute([$id]);
    $im = $pdo->prepare("SELECT id,file_path FROM project_daily_images WHERE daily_log_id=?"); $im->execute([$id]);

    $isCreator = ((int)$log['created_by'] === (int)$currentUserId);
    $inApprovalGroup = false;
    if ($currentUserId && $log['approval_group_id']) {
      $stmt2 = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND group_id=? AND user_id=? LIMIT 1");
      $stmt2->execute([$projectId, (int)$log['approval_group_id'], $currentUserId]);
      $inApprovalGroup = (bool)$stmt2->fetchColumn();
    }

    json_out([
      'ok'=>true,
      'log'=>$log,
      'equipment'=>$eq->fetchAll(PDO::FETCH_ASSOC),
      'labor'=>$lb->fetchAll(PDO::FETCH_ASSOC),
      'images'=>$im->fetchAll(PDO::FETCH_ASSOC),
      'canEdit'=> $isCreator && !$log['is_approved'],
      'canApprove'=> !$log['is_approved'] && $inApprovalGroup,
      'isCreator'=>$isCreator
    ]);
  }

  // CREATE
  if ($action === 'create' && $_SERVER['REQUEST_METHOD']==='POST') {
    assert_member_or_die($pdo, $projectId, (int)$currentUserId);

    $code = trim($_POST['code'] ?? '');
    $entry_date = $_POST['entry_date'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $approval_group_id = (int)($_POST['approval_group_id'] ?? 0);
    $work_details = trim($_POST['work_details'] ?? '');
    $cleanliness = $_POST['cleanliness'] ?? 'normal';
    $safety = $_POST['safety'] ?? 'normal';
    $wm = $_POST['weather_morning'] ?? null;
    $wa = $_POST['weather_afternoon'] ?? null;
    $we = $_POST['weather_evening'] ?? null;
    $wn = $_POST['weather_night'] ?? null;

    if ($code==='' || $entry_date==='' || $name==='') {
      json_out(['ok'=>false,'message'=>'Please fill required fields (Code, Entry date, Name).'], 422);
    }

    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("INSERT INTO project_daily_logs
        (project_id,code,entry_date,name,weather_morning,weather_afternoon,weather_evening,weather_night,work_details,cleanliness,safety,approval_group_id,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $stmt->execute([$projectId,$code,$entry_date,$name,$wm,$wa,$we,$wn,$work_details,$cleanliness,$safety,$approval_group_id,$currentUserId]);
      $dailyId = (int)$pdo->lastInsertId();

      $equipment = json_decode($_POST['equipment'] ?? '[]', true) ?: [];
      if ($equipment) {
        $ins = $pdo->prepare("INSERT INTO project_daily_equipment (daily_log_id,item_name,qty) VALUES (?,?,?)");
        foreach ($equipment as $row) {
          if (trim($row['name'] ?? '')==='') continue;
          $ins->execute([$dailyId, trim($row['name']), (int)($row['qty'] ?? 0)]);
        }
      }

      $labor = json_decode($_POST['labor'] ?? '[]', true) ?: [];
      if ($labor) {
        $ins = $pdo->prepare("INSERT INTO project_daily_labor (daily_log_id,labor_name,qty) VALUES (?,?,?)");
        foreach ($labor as $row) {
          if (trim($row['name'] ?? '')==='') continue;
          $ins->execute([$dailyId, trim($row['name']), (int)($row['qty'] ?? 0)]);
        }
      }

      // images (max 4)
      $saved = [];
      if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $projectCode = get_project_code($pdo, $projectId) ?? ('PRJ'.str_pad((string)$projectId,6,'0',STR_PAD_LEFT));
        $baseDir = __DIR__ . '/../../uploads/' . $projectCode . '/_daily_logs';
        if (!is_dir($baseDir)) { @mkdir($baseDir, 0775, true); }
        $count = min(4, count($_FILES['images']['name']));
        for ($i=0;$i<$count;$i++) {
          if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
          $tmp = $_FILES['images']['tmp_name'][$i];
          $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
          if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
          $fname = 'daily_'.$dailyId.'_'.time().'_'.($i+1).'.'.$ext;
          $target = $baseDir . '/' . $fname;
          if (@move_uploaded_file($tmp, $target)) {
            $rel = 'uploads/' . $projectCode . '/_daily_logs/' . $fname;
            $saved[] = $rel;
          }
        }
        if ($saved) {
          $ins = $pdo->prepare("INSERT INTO project_daily_images (daily_log_id,file_path) VALUES (?,?)");
          foreach ($saved as $rel) $ins->execute([$dailyId, $rel]);
        }
      }

      // notify approval group
      if ($approval_group_id > 0) {
        $members = $pdo->prepare("SELECT user_id FROM project_group_members WHERE project_id=? AND group_id=?");
        $members->execute([$projectId, $approval_group_id]);
        $recv = $members->fetchAll(PDO::FETCH_COLUMN);
        if ($recv) {
          $insn = $pdo->prepare("INSERT INTO notifications (sender_id, receiver_id, entry_date, is_read) VALUES (?,?,?,0)");
          foreach ($recv as $rid) {
            if ((int)$rid === (int)$currentUserId) continue;
            $insn->execute([$currentUserId, (int)$rid, $entry_date]);
          }
        }
      }

      $pdo->commit();
      json_out(['ok'=>true,'message'=>'Daily log created successfully.']);
    } catch (Throwable $e) {
      $pdo->rollBack();
      json_out(['ok'=>false,'message'=>'Create failed: '.$e->getMessage()], 500);
    }
  }

  // UPDATE
  if ($action === 'update' && $_SERVER['REQUEST_METHOD']==='POST') {
    assert_member_or_die($pdo, $projectId, (int)$currentUserId);
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM project_daily_logs WHERE id=? AND project_id=?");
    $stmt->execute([$id,$projectId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$log) json_out(['ok'=>false,'message'=>'Not found'], 404);
    if ((int)$log['created_by'] !== (int)$currentUserId) json_out(['ok'=>false,'message'=>'Only creator can update.'], 403);
    if ((int)$log['is_approved'] === 1) json_out(['ok'=>false,'message'=>'Approved log cannot be updated.'], 409);

    $code = trim($_POST['code'] ?? $log['code']);
    $entry_date = $_POST['entry_date'] ?? $log['entry_date'];
    $name = trim($_POST['name'] ?? $log['name']);
    $approval_group_id = (int)($_POST['approval_group_id'] ?? $log['approval_group_id']);
    $work_details = trim($_POST['work_details'] ?? $log['work_details']);
    $cleanliness = $_POST['cleanliness'] ?? $log['cleanliness'];
    $safety = $_POST['safety'] ?? $log['safety'];
    $wm = $_POST['weather_morning'] ?? $log['weather_morning'];
    $wa = $_POST['weather_afternoon'] ?? $log['weather_afternoon'];
    $we = $_POST['weather_evening'] ?? $log['weather_evening'];
    $wn = $_POST['weather_night'] ?? $log['weather_night'];

    $pdo->beginTransaction();
    try {
      $up = $pdo->prepare("UPDATE project_daily_logs SET code=?, entry_date=?, name=?, weather_morning=?, weather_afternoon=?, weather_evening=?, weather_night=?, work_details=?, cleanliness=?, safety=?, approval_group_id=? WHERE id=?");
      $up->execute([$code,$entry_date,$name,$wm,$wa,$we,$wn,$work_details,$cleanliness,$safety,$approval_group_id,$id]);

      $pdo->prepare("DELETE FROM project_daily_equipment WHERE daily_log_id=?")->execute([$id]);
      $equipment = json_decode($_POST['equipment'] ?? '[]', true) ?: [];
      if ($equipment) {
        $ins = $pdo->prepare("INSERT INTO project_daily_equipment (daily_log_id,item_name,qty) VALUES (?,?,?)");
        foreach ($equipment as $row) {
          if (trim($row['name'] ?? '')==='') continue;
          $ins->execute([$id, trim($row['name']), (int)($row['qty'] ?? 0)]);
        }
      }

      $pdo->prepare("DELETE FROM project_daily_labor WHERE daily_log_id=?")->execute([$id]);
      $labor = json_decode($_POST['labor'] ?? '[]', true) ?: [];
      if ($labor) {
        $ins = $pdo->prepare("INSERT INTO project_daily_labor (daily_log_id,labor_name,qty) VALUES (?,?,?)");
        foreach ($labor as $row) {
          if (trim($row['name'] ?? '')==='') continue;
          $ins->execute([$id, trim($row['name']), (int)($row['qty'] ?? 0)]);
        }
      }

      // append images up to 4
      $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM project_daily_images WHERE daily_log_id=?");
      $cntStmt->execute([$id]); $existing = (int)$cntStmt->fetchColumn();
      $slots = max(0, 4 - $existing);
      if ($slots>0 && !empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $projectCode = get_project_code($pdo, $projectId) ?? ('PRJ'.str_pad((string)$projectId,6,'0',STR_PAD_LEFT));
        $baseDir = __DIR__ . '/../../uploads/' . $projectCode . '/_daily_logs';
        if (!is_dir($baseDir)) { @mkdir($baseDir, 0775, true); }
        $add = min($slots, count($_FILES['images']['name']));
        $insImg = $pdo->prepare("INSERT INTO project_daily_images (daily_log_id,file_path) VALUES (?,?)");
        for ($i=0; $i<$add; $i++) {
          if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
          $tmp = $_FILES['images']['tmp_name'][$i];
          $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
          if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
          $fname = 'daily_'.$id.'_'.time().'_'.($i+1).'.'.$ext;
          $target = $baseDir . '/' . $fname;
          if (@move_uploaded_file($tmp, $target)) {
            $rel = 'uploads/' . $projectCode . '/_daily_logs/' . $fname;
            $insImg->execute([$id, $rel]);
          }
        }
      }

      $pdo->commit();
      json_out(['ok'=>true,'message'=>'Daily log updated successfully.']);
    } catch (Throwable $e) {
      $pdo->rollBack();
      json_out(['ok'=>false,'message'=>'Update failed: '.$e->getMessage()], 500);
    }
  }

  // DELETE
  if ($action === 'delete' && $_SERVER['REQUEST_METHOD']==='POST') {
    assert_member_or_die($pdo, $projectId, (int)$currentUserId);
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id,created_by,is_approved FROM project_daily_logs WHERE id=? AND project_id=?");
    $stmt->execute([$id,$projectId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$log) json_out(['ok'=>false,'message'=>'Not found'], 404);
    if ((int)$log['created_by'] !== (int)$currentUserId) json_out(['ok'=>false,'message'=>'Only creator can delete.'], 403);
    if ((int)$log['is_approved'] === 1) json_out(['ok'=>false,'message'=>'Approved log cannot be deleted.'], 409);

    $pdo->beginTransaction();
    try {
      $imgStmt = $pdo->prepare("SELECT file_path FROM project_daily_images WHERE daily_log_id=?");
      $imgStmt->execute([$id]);
      foreach ($imgStmt->fetchAll(PDO::FETCH_COLUMN) as $rel) {
        $abs = __DIR__ . '/../../' . $rel;
        if (is_file($abs)) @unlink($abs);
      }
      $pdo->prepare("DELETE FROM project_daily_images WHERE daily_log_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM project_daily_equipment WHERE daily_log_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM project_daily_labor WHERE daily_log_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM project_daily_logs WHERE id=?")->execute([$id]);

      $pdo->commit();
      json_out(['ok'=>true,'message'=>'Daily log deleted.']);
    } catch (Throwable $e) {
      $pdo->rollBack();
      json_out(['ok'=>false,'message'=>'Delete failed: '.$e->getMessage()], 500);
    }
  }

  // APPROVE (transaction + FOR UPDATE + rowCount check)
  if ($action === 'approve' && $_SERVER['REQUEST_METHOD']==='POST') {
    assert_member_or_die($pdo, $projectId, (int)$currentUserId);
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_out(['ok'=>false,'message'=>'Invalid daily log id.'], 422);

    try {
      $pdo->beginTransaction();

      $stmt = $pdo->prepare("SELECT id, project_id, approval_group_id, is_approved
                             FROM project_daily_logs
                             WHERE id=? AND project_id=? FOR UPDATE");
      $stmt->execute([$id, $projectId]);
      $log = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$log) { $pdo->rollBack(); json_out(['ok'=>false,'message'=>'Not found'], 404); }
      if ((int)$log['is_approved'] === 1) { $pdo->rollBack(); json_out(['ok'=>false,'message'=>'Already approved.'], 409); }

      $groupId = (int)($log['approval_group_id'] ?? 0);
      if ($groupId > 0) {
        $chk = $pdo->prepare("SELECT 1 FROM project_group_members
                              WHERE project_id=? AND group_id=? AND user_id=? LIMIT 1");
        $chk->execute([$projectId, $groupId, $currentUserId]);
        if (!$chk->fetchColumn()) { $pdo->rollBack(); json_out(['ok'=>false,'message'=>'You are not in the approval group.'], 403); }
      } else {
        $pdo->rollBack();
        json_out(['ok'=>false,'message'=>'Approval group is not set for this log.'], 422);
      }

      $up = $pdo->prepare("UPDATE project_daily_logs
                           SET is_approved=1, approved_by=?, approved_at=NOW()
                           WHERE id=? AND project_id=? AND is_approved=0");
      $up->execute([$currentUserId, $id, $projectId]);
      if ($up->rowCount() !== 1) { $pdo->rollBack(); json_out(['ok'=>false,'message'=>'Approve failed (no rows updated).'], 500); }

      $ts = $pdo->query("SELECT approved_at FROM project_daily_logs WHERE id=".$pdo->quote($id))->fetchColumn();

      $pdo->commit();
      json_out(['ok'=>true,'message'=>'Approved successfully.','approved_at'=>$ts ?: null]);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      json_out(['ok'=>false,'message'=>'Approve error: '.$e->getMessage()], 500);
    }
  }

  // EXPORT (members only)
  if ($action === 'export') {
    if (!is_member($pdo,$projectId,(int)$currentUserId)) { http_response_code(403); echo "Access denied"; exit; }
    $q = trim($_GET['q'] ?? '');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=daily_logs_project_'.$projectId.'.csv');
    $out = fopen('php://output','w');
    fputcsv($out, ['Code','Entry Date','Name','Created By','Approval Unit','Status']);
    $sql = "SELECT dl.*, CONCAT(u.first_name,' ',u.last_name) as creator, pg.name as group_name
            FROM project_daily_logs dl
            LEFT JOIN users u ON u.id=dl.created_by
            LEFT JOIN project_groups pg ON pg.id=dl.approval_group_id
            WHERE dl.project_id=? ";
    $params = [$projectId];
    if ($q!=='') { $sql.=" AND dl.name LIKE ?"; $params[]="%$q%"; }
    $sql.=" ORDER BY dl.entry_date DESC, dl.id DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
      fputcsv($out, [
        $r['code'], $r['entry_date'], $r['name'],
        $r['creator'] ?: '', $r['group_name'] ?: '',
        ((int)$r['is_approved']===1 ? 'Approved' : 'Pending')
      ]);
    }
    fclose($out); exit;
  }

  json_out(['ok'=>false,'message'=>'Unknown action'], 400);
  exit;
}

/* ---- HTML (tab content) ---- */
?>
<link rel="stylesheet" href="../assets/css/project_tab_daily.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div id="daily-logs-root"
     data-project-id="<?=htmlspecialchars($projectId)?>"
     data-current-user="<?=htmlspecialchars((string)$currentUserId)?>">

  <div class="daily-toolbar">
    <button id="btn-create-daily" class="btn primary">
      <i class="fa-solid fa-plus"></i> Create
    </button>
    <button id="btn-export" class="btn">
      <i class="fa-solid fa-file-export"></i> Export CSV
    </button>
    <div class="search-wrap">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input id="daily-search" type="text" placeholder="Search by log name...">
    </div>
  </div>

  <div class="daily-table-wrap">
    <table class="daily-table" id="daily-logs-table">
      <thead>
        <tr>
          <th style="width:110px;">Code</th>
          <th style="width:120px;">Entry Date</th>
          <th>Name</th>
          <th style="width:180px;">Created By</th>
          <th style="width:180px;">Approval Unit</th>
          <th style="width:120px;">Status</th>
          <th style="width:60px;">Delete</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div class="empty-hint" id="daily-empty" style="display:none;">
      <i class="fa-regular fa-folder-open"></i>
      <span>No daily logs found.</span>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="daily-modal-backdrop" id="daily-modal" hidden>
  <div class="daily-modal">
    <div class="modal-header">
      <h3 id="modal-title"><i class="fa-regular fa-calendar-check"></i> New Daily Log</h3>
      <button class="icon-btn" id="modal-close"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <form id="daily-form" enctype="multipart/form-data">
      <input type="hidden" name="id" id="f-id">
      <div class="modal-body">
        <fieldset class="kv">
          <legend>Basic Info</legend>
          <div class="grid g3">
            <label>Code*<input name="code" id="f-code" type="text" required></label>
            <label>Entry date*<input name="entry_date" id="f-entry-date" type="date" required></label>
            <label>Name*<input name="name" id="f-name" type="text" required></label>
          </div>
        </fieldset>

        <fieldset class="kv">
          <legend>Weather</legend>
          <div class="grid g4">
            <label>Morning
              <select name="weather_morning" id="f-wm">
                <option value="">--</option><option value="sunny">Sunny</option><option value="cloudy">Cloudy</option><option value="rainy">Rainy</option>
              </select>
            </label>
            <label>Afternoon
              <select name="weather_afternoon" id="f-wa">
                <option value="">--</option><option value="sunny">Sunny</option><option value="cloudy">Cloudy</option><option value="rainy">Rainy</option>
              </select>
            </label>
            <label>Evening
              <select name="weather_evening" id="f-we">
                <option value="">--</option><option value="clear">Clear</option><option value="cloudy">Cloudy</option><option value="rainy">Rainy</option>
              </select>
            </label>
            <label>Night
              <select name="weather_night" id="f-wn">
                <option value="">--</option><option value="clear">Clear</option><option value="cloudy">Cloudy</option><option value="rainy">Rainy</option>
              </select>
            </label>
          </div>
        </fieldset>

        <fieldset class="kv">
          <legend><i class="fa-solid fa-truck-front"></i> Equipment</legend>
          <div id="equip-rows" class="rows"></div>
          <button type="button" class="btn subtle" id="btn-add-eq"><i class="fa-solid fa-plus"></i> Add equipment</button>
        </fieldset>

        <fieldset class="kv">
          <legend><i class="fa-solid fa-helmet-safety"></i> Labor</legend>
          <div id="labor-rows" class="rows"></div>
          <button type="button" class="btn subtle" id="btn-add-lb"><i class="fa-solid fa-plus"></i> Add labor</button>
        </fieldset>

        <fieldset class="kv">
          <legend>Work details</legend>
          <textarea name="work_details" id="f-details" rows="5" placeholder="Describe tasks and notes..."></textarea>
        </fieldset>

        <fieldset class="kv">
          <legend><i class="fa-regular fa-images"></i> Site images (max 4)</legend>
          <div class="file-input">
            <input type="file" name="images[]" id="f-images" accept=".jpg,.jpeg,.png,.webp" multiple>
            <label for="f-images" class="file-trigger">
              <i class="fa-regular fa-images"></i> Choose images
            </label>
            <span id="file-chosen" class="file-chosen">No files selected</span>
          </div>
          <small class="muted">Images will be stored under PRJ.../_daily_logs and hidden from the Files tab.</small>
          <div id="image-preview" class="img-grid"></div>
        </fieldset>

        <fieldset class="kv">
          <legend>Site status</legend>
          <div class="grid g2">
            <label><i class="fa-solid fa-broom"></i> Cleanliness
              <select name="cleanliness" id="f-clean">
                <option value="good">Good</option><option value="normal" selected>Normal</option><option value="poor">Poor</option>
              </select>
            </label>
            <label><i class="fa-solid fa-shield-heart"></i> Labor safety
              <select name="safety" id="f-safety">
                <option value="good">Good</option><option value="normal" selected>Normal</option><option value="poor">Poor</option>
              </select>
            </label>
          </div>
        </fieldset>

        <fieldset class="kv">
          <legend>Approval unit</legend>
          <select name="approval_group_id" id="f-approval">
            <option value="0">-- Select a group --</option>
            <?php
            $g = $pdo->prepare("SELECT id,name FROM project_groups WHERE project_id=? ORDER BY name ASC");
            $g->execute([$projectId]);
            foreach ($g->fetchAll(PDO::FETCH_ASSOC) as $row) {
              echo '<option value="'.(int)$row['id'].'">'.htmlspecialchars($row['name']).'</option>';
            }
            ?>
          </select>
          <small class="muted">Members of the selected group will receive a notification.</small>
        </fieldset>
      </div>

      <div class="modal-footer">
        <div class="left">
          <button type="button" class="btn success" id="btn-approve" hidden><i class="fa-solid fa-check"></i> Approve</button>
        </div>
        <div class="right">
          <button type="button" class="btn" id="btn-cancel">Cancel</button>
          <button type="submit" class="btn primary" id="btn-submit">Create</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div id="daily-toast" class="daily-toast" hidden></div>
<script src="../assets/js/project_tab_daily.js"></script>
