<?php
// /pages/partials/project_tab_daily.php
// expects $pdo, $projectId, $userId, $BASE

if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); echo "DB missing."; exit; }

/* ================= Helpers & Schema ================= */
function json_out($arr, $status=200){
  while (ob_get_level()>0) @ob_end_clean();
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}
function has_table(PDO $pdo, $table){
  $q=$pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
  $q->execute([$table]); return (int)$q->fetchColumn() > 0;
}
function has_column(PDO $pdo, $table, $col){
  $q=$pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
  $q->execute([$table,$col]);
  return (int)$q->fetchColumn() > 0;
}
function ensure_tables(PDO $pdo){
  $pdo->exec('CREATE TABLE IF NOT EXISTS project_daily_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    code VARCHAR(64) NOT NULL,
    entry_date DATE NOT NULL,
    name VARCHAR(255) NOT NULL,
    approval_group_id INT NULL,
    status ENUM("pending","approved","rejected") NOT NULL DEFAULT "pending",
    weather_morning VARCHAR(32) NULL,
    weather_afternoon VARCHAR(32) NULL,
    weather_evening VARCHAR(32) NULL,
    weather_night VARCHAR(32) NULL,
    site_cleanliness VARCHAR(32) NULL,
    labor_safety VARCHAR(32) NULL,
    work_detail MEDIUMTEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_code (project_id, code)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS project_daily_log_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    daily_log_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    qty DECIMAL(18,3) NOT NULL DEFAULT 0,
    INDEX(daily_log_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS project_daily_log_labor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    daily_log_id INT NOT NULL,
    person_name VARCHAR(255) NOT NULL,
    qty DECIMAL(18,3) NOT NULL DEFAULT 0,
    INDEX(daily_log_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  if (!has_table($pdo,'project_daily_log_images')) {
    $pdo->exec('CREATE TABLE project_daily_log_images (
      id INT AUTO_INCREMENT PRIMARY KEY,
      daily_log_id INT NOT NULL,
      file_path VARCHAR(512) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(daily_log_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  }
  if (!has_column($pdo,'project_daily_log_images','file_name')) {
    try { $pdo->exec('ALTER TABLE project_daily_log_images ADD COLUMN file_name VARCHAR(255) NULL AFTER file_path'); } catch (Throwable $e) {}
  }
  $pdo->exec('CREATE TABLE IF NOT EXISTS project_daily_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    daily_log_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message VARCHAR(500) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(project_id), INDEX(daily_log_id), INDEX(receiver_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function slugify_filename($name){
  $name=preg_replace('/[\\\/#?&\s]+/u','_',$name);
  $name=trim($name,'_'); if($name==='')$name='file'; return $name;
}
function project_code(PDO $pdo,$projectId){
  $st=$pdo->prepare('SELECT code FROM projects WHERE id=? LIMIT 1'); $st->execute([$projectId]);
  $code=$st->fetchColumn(); return $code ?: ('PRJ'.str_pad((string)$projectId,5,'0',STR_PAD_LEFT));
}
function ensure_upload_dir(PDO $pdo,$projectId){
  $code=project_code($pdo,$projectId);
  $base=dirname(__DIR__,2);
  $root=$base.DIRECTORY_SEPARATOR.'uploads'; if(!is_dir($root)) @mkdir($root,0775,true);
  $proj=$root.DIRECTORY_SEPARATOR.$code;     if(!is_dir($proj)) @mkdir($proj,0775,true);
  $daily=$proj.DIRECTORY_SEPARATOR.'daily_logs'; if(!is_dir($daily)) @mkdir($daily,0775,true);
  return [$code,$daily];
}
/* Membership: try multiple schemas to be compatible */
function is_project_member(PDO $pdo,int $projectId,int $userId){
  if ($userId<=0) return false;
  if (has_table($pdo,'project_members') && has_column($pdo,'project_members','project_id') && has_column($pdo,'project_members','user_id')) {
    $q=$pdo->prepare('SELECT 1 FROM project_members WHERE project_id=? AND user_id=? LIMIT 1');
    $q->execute([$projectId,$userId]); if ($q->fetchColumn()) return true;
  }
  if (has_table($pdo,'projects_users')) {
    $q=$pdo->prepare('SELECT 1 FROM projects_users WHERE project_id=? AND user_id=? LIMIT 1');
    $q->execute([$projectId,$userId]); if ($q->fetchColumn()) return true;
  }
  if (has_table($pdo,'project_user')) {
    $q=$pdo->prepare('SELECT 1 FROM project_user WHERE project_id=? AND user_id=? LIMIT 1');
    $q->execute([$projectId,$userId]); if ($q->fetchColumn()) return true;
  }
  if (has_table($pdo,'project_group_members') && has_table($pdo,'project_groups')) {
    $q=$pdo->prepare('SELECT 1 FROM project_group_members pgm JOIN project_groups g ON g.id=pgm.group_id WHERE g.project_id=? AND pgm.user_id=? LIMIT 1');
    $q->execute([$projectId,$userId]); if ($q->fetchColumn()) return true;
  }
  return false;
}
function save_images(PDO $pdo,$dailyLogId,$projectId){
  if (empty($_FILES['images']) || !is_array($_FILES['images']['name'])) return [];
  [, $dailyDir]=ensure_upload_dir($pdo,$projectId); $saved=[];
  $count=count($_FILES['images']['name']);
  $hasFileName = has_column($pdo,'project_daily_log_images','file_name');
  for($i=0;$i<$count;$i++){
    $name=$_FILES['images']['name'][$i]; $tmp=$_FILES['images']['tmp_name'][$i]??null;
    $err=$_FILES['images']['error'][$i]??UPLOAD_ERR_NO_FILE; if($err!==UPLOAD_ERR_OK || !is_uploaded_file($tmp)) continue;
    $ext=pathinfo($name,PATHINFO_EXTENSION); $base=slugify_filename(pathinfo($name,PATHINFO_FILENAME));
    $final=$base.'_'.date('Ymd_His').'_'.substr(md5($name.microtime(true)),0,6); if($ext) $final.='.'.strtolower($ext);
    $dest=$dailyDir.DIRECTORY_SEPARATOR.$final;
    if (@move_uploaded_file($tmp,$dest)){
      $rel='uploads/'.project_code($pdo,$projectId).'/daily_logs/'.$final;
      if ($hasFileName){
        $ins=$pdo->prepare('INSERT INTO project_daily_log_images (daily_log_id,file_path,file_name) VALUES (?,?,?)');
        $ins->execute([$dailyLogId,$rel,$final]);
      } else {
        $ins=$pdo->prepare('INSERT INTO project_daily_log_images (daily_log_id,file_path) VALUES (?,?)');
        $ins->execute([$dailyLogId,$rel]);
      }
      $saved[]=$rel;
    }
  }
  return $saved;
}
function notify_group(PDO $pdo,int $projectId,?int $groupId,int $dailyLogId,int $senderId,string $title,string $entryDate){
  if(!$groupId) return;
  $mem=$pdo->prepare('SELECT user_id FROM project_group_members WHERE group_id=?'); $mem->execute([$groupId]);
  $users=$mem->fetchAll(PDO::FETCH_COLUMN,0); if(!$users) return;
  $msg='Daily Log "'.$title.'" dated '.$entryDate.' requires your attention.';
  $ins=$pdo->prepare('INSERT INTO project_daily_notifications (project_id,daily_log_id,sender_id,receiver_id,message,created_at,is_read) VALUES (:p,:d,:s,:r,:m,CURRENT_TIMESTAMP,0)');
  foreach($users as $uid){ $uid=(int)$uid; if($uid===$senderId) continue; $ins->execute([':p'=>$projectId,':d'=>$dailyLogId,':s'=>$senderId,':r'=>$uid,':m'=>$msg]); }
}

/* ================= AJAX ================= */
if (isset($_GET['ajax']) && $_GET['ajax']==='daily'){
  @ini_set('display_errors','0'); @ini_set('log_errors','1');
  try{
    ensure_tables($pdo);
    $projectId=(int)($_GET['project_id']??0); if($projectId<=0) json_out(['ok'=>false,'message'=>'Invalid project id'],400);
    $userId = (int)($GLOBALS['userId'] ?? 0);
    $isMember = is_project_member($pdo,$projectId,$userId);
    $action=$_POST['dl_action'] ?? ($_GET['action']??'');

    if (!$isMember && !in_array($action, ['get_log'], true)) {
      json_out(['ok'=>false,'message'=>'⚠️ Bạn không có quyền truy cập Tab Materials của dự án này (chỉ thành viên trong dự án mới được sửa/cập nhật).'],403);
    }

    if ($action==='create' || $action==='update'){
      $id=(int)($_POST['id']??0);
      $code=trim($_POST['code']??''); $name=trim($_POST['name']??''); $date=trim($_POST['entry_date']??date('Y-m-d'));
      $group=!empty($_POST['approval_group_id'])?(int)$_POST['approval_group_id']:null;
      $wm=$_POST['weather_morning']??null; $wa=$_POST['weather_afternoon']??null; $we=$_POST['weather_evening']??null; $wn=$_POST['weather_night']??null;
      $clean=$_POST['site_cleanliness']??null; $safety=$_POST['labor_safety']??null; $detail=$_POST['work_detail']??null;
      if($code===''||$name==='') json_out(['ok'=>false,'message'=>'Missing required fields (code, name).'],422);

      if ($action==='create'){
        $st=$pdo->prepare('INSERT INTO project_daily_logs (project_id,code,entry_date,name,approval_group_id,status,weather_morning,weather_afternoon,weather_evening,weather_night,site_cleanliness,labor_safety,work_detail,created_by)
                           VALUES (?,?,?,?,?,"pending",?,?,?,?,?,?,?,?)');
        $st->execute([$projectId,$code,$date,$name,$group,$wm,$wa,$we,$wn,$clean,$safety,$detail,$userId]);
        $dailyId=(int)$pdo->lastInsertId();

        $en=$_POST['eq_name']??[]; $eq=$_POST['eq_qty']??[];
        $insE=$pdo->prepare('INSERT INTO project_daily_log_equipment (daily_log_id,item_name,qty) VALUES (?,?,?)');
        for($i=0;$i<count($en);$i++){ $n=trim((string)($en[$i]??'')); if($n==='') continue; $q=(float)($eq[$i]??0); $insE->execute([$dailyId,$n,$q]); }

        $ln=$_POST['lb_name']??[]; $lq=$_POST['lb_qty']??[];
        $insL=$pdo->prepare('INSERT INTO project_daily_log_labor (daily_log_id,person_name,qty) VALUES (?,?,?)');
        for($i=0;$i<count($ln);$i++){ $n=trim((string)($ln[$i]??'')); if($n==='') continue; $q=(float)($lq[$i]??0); $insL->execute([$dailyId,$n,$q]); }

        save_images($pdo,$dailyId,$projectId);
        notify_group($pdo,$projectId,$group,$dailyId,$userId,$name,$date);
        json_out(['ok'=>true,'message'=>'Created','id'=>$dailyId]);
      } else {
        if($id<=0) json_out(['ok'=>false,'message'=>'Invalid id'],400);
        $own=$pdo->prepare('SELECT created_by FROM project_daily_logs WHERE id=? AND project_id=?');
        $own->execute([$id,$projectId]);
        $owner=(int)($own->fetchColumn() ?: 0);
        if ($owner !== $userId) json_out(['ok'=>false,'message'=>'Bạn chỉ có thể sửa nhật ký do bạn tạo.'],403);

        $st=$pdo->prepare('UPDATE project_daily_logs SET code=?,entry_date=?,name=?,approval_group_id=?,weather_morning=?,weather_afternoon=?,weather_evening=?,weather_night=?,site_cleanliness=?,labor_safety=?,work_detail=? WHERE id=? AND project_id=?');
        $st->execute([$code,$date,$name,$group,$wm,$wa,$we,$wn,$clean,$safety,$detail,$id,$projectId]);

        $pdo->prepare('DELETE FROM project_daily_log_equipment WHERE daily_log_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM project_daily_log_labor WHERE daily_log_id=?')->execute([$id]);
        $en=$_POST['eq_name']??[]; $eq=$_POST['eq_qty']??[];
        $ln=$_POST['lb_name']??[]; $lq=$_POST['lb_qty']??[];
        $insE=$pdo->prepare('INSERT INTO project_daily_log_equipment (daily_log_id,item_name,qty) VALUES (?,?,?)');
        for($i=0;$i<count($en);$i++){ $n=trim((string)($en[$i]??'')); if($n==='') continue; $q=(float)($eq[$i]??0); $insE->execute([$id,$n,$q]); }
        $insL=$pdo->prepare('INSERT INTO project_daily_log_labor (daily_log_id,person_name,qty) VALUES (?,?,?)');
        for($i=0;$i<count($ln);$i++){ $n=trim((string)($ln[$i]??'')); if($n==='') continue; $q=(float)($lq[$i]??0); $insL->execute([$id,$n,$q]); }

        save_images($pdo,$id,$projectId);
        json_out(['ok'=>true,'message'=>'Updated','id'=>$id]);
      }
    }
    elseif ($action==='delete'){
      $id=(int)($_POST['id']??0); if($id<=0) json_out(['ok'=>false,'message'=>'Invalid id'],400);
      $own=$pdo->prepare('SELECT created_by FROM project_daily_logs WHERE id=? AND project_id=?');
      $own->execute([$id,$projectId]);
      $owner=(int)($own->fetchColumn() ?: 0);
      if ($owner !== (int)($GLOBALS['userId'] ?? 0)) json_out(['ok'=>false,'message'=>'Bạn chỉ có thể xóa nhật ký do bạn tạo.'],403);

      $imgs=$pdo->prepare('SELECT file_path FROM project_daily_log_images WHERE daily_log_id=?'); $imgs->execute([$id]);
      foreach($imgs->fetchAll(PDO::FETCH_COLUMN,0) as $rel){
        $abs=dirname(__DIR__,2).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) @unlink($abs);
      }
      $pdo->prepare('DELETE FROM project_daily_log_images WHERE daily_log_id=?')->execute([$id]);
      $pdo->prepare('DELETE FROM project_daily_log_equipment WHERE daily_log_id=?')->execute([$id]);
      $pdo->prepare('DELETE FROM project_daily_log_labor WHERE daily_log_id=?')->execute([$id]);
      $pdo->prepare('DELETE FROM project_daily_logs WHERE id=? AND project_id=?')->execute([$id,(int)$_GET['project_id']]);
      json_out(['ok'=>true,'message'=>'Deleted']);
    }
    elseif ($action==='get_log'){
      $id=(int)($_GET['id']??0); if($id<=0) json_out(['ok'=>false,'message'=>'Invalid id'],400);
      $st=$pdo->prepare('SELECT *, (created_by = ?) AS editable FROM project_daily_logs WHERE id=? AND project_id=?');
      $st->execute([(int)($GLOBALS['userId']??0), $id, $projectId]);
      $row=$st->fetch(PDO::FETCH_ASSOC); if(!$row) json_out(['ok'=>false,'message'=>'Not found'],404);

      $eq=$pdo->prepare('SELECT item_name, qty FROM project_daily_log_equipment WHERE daily_log_id=?'); $eq->execute([$id]);
      $lb=$pdo->prepare('SELECT person_name, qty FROM project_daily_log_labor WHERE daily_log_id=?'); $lb->execute([$id]);
      if (has_column($pdo,'project_daily_log_images','file_name')) {
        $im=$pdo->prepare('SELECT file_path AS path, file_name FROM project_daily_log_images WHERE daily_log_id=?');
      } else {
        $im=$pdo->prepare('SELECT file_path AS path, SUBSTRING_INDEX(file_path,"/",-1) AS file_name FROM project_daily_log_images WHERE daily_log_id=?');
      }
      $im->execute([$id]);
      json_out(['ok'=>true,'data'=>$row,'equipment'=>$eq->fetchAll(PDO::FETCH_ASSOC),'labor'=>$lb->fetchAll(PDO::FETCH_ASSOC),'images'=>$im->fetchAll(PDO::FETCH_ASSOC),'editable'=> (bool)$row['editable'] ]);
    }
    else { json_out(['ok'=>false,'message'=>'Unknown action'],400); }
  } catch(Throwable $e){ json_out(['ok'=>false,'message'=>'Server error: '.$e->getMessage()],500); }
}

/* ================= Render (Server-side permissions) ================= */
$ajaxBase = $ajaxBase ?? (($_SERVER['PHP_SELF'] ?? ''));
$isMember = is_project_member($pdo,(int)$projectId,(int)($GLOBALS['userId'] ?? 0));
$canEdit = $isMember; // only project members can create; per-log edit/delete checked via owner

?>
<section id="daily-tab"
  data-project-id="<?= (int)$projectId ?>"
  data-can-edit="<?= $canEdit ? '1' : '0' ?>"
  data-ajax-base="<?= htmlspecialchars($ajaxBase) ?>"
  data-upload-prefix="<?= htmlspecialchars($BASE) ?>/../">

  <?php if(!$isMember): ?>
    <div class="dl-alert dl-alert-warn" style="margin-bottom:12px;border:1px dashed #f59e0b;background:#fff7ed;color:#9a3412;border-radius:10px;padding:10px 12px;">
      ⚠️ Bạn không có quyền truy cập Tab Materials của dự án này (chỉ thành viên trong dự án mới được sửa/cập nhật).
    </div>
  <?php endif; ?>

  <div class="dl-toolbar">
    <div class="dl-left">
      <input id="dl-search" class="dl-search" type="search" placeholder="Search logs...">
    </div>
    <div class="dl-right">
      <?php if ($canEdit): ?>
      <button id="dl-btn-create" class="dl-btn dl-btn-primary"><i class="fas fa-plus"></i> New Log</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if($isMember): ?>
  <div class="dl-table-wrap">
    <table class="dl-table">
      <thead>
        <tr>
          <th style="width:120px">Date</th>
          <th style="width:120px">Code</th>
          <th>Name</th>
          <th style="width:160px">Status</th>
          <th style="width:160px">Actions</th>
        </tr>
      </thead>
      <tbody id="dl-tbody">
        <?php
        $st=$pdo->prepare('SELECT id, entry_date, code, name, status, created_by FROM project_daily_logs WHERE project_id=? ORDER BY entry_date DESC, id DESC');
        $st->execute([(int)$projectId]);
        $uid = (int)($GLOBALS['userId'] ?? 0);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r): ?>
          <tr class="dl-row" data-name="<?= htmlspecialchars($r['name']) ?>" data-code="<?= htmlspecialchars($r['code']) ?>" data-date="<?= htmlspecialchars($r['entry_date']) ?>">
            <td><?= htmlspecialchars($r['entry_date']) ?></td>
            <td><?= htmlspecialchars($r['code']) ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td>
              <?php if ($r['status']==='approved'): ?>
                <span class="dl-badge dl-badge-ok"><i class="fas fa-check-circle"></i> Approved</span>
              <?php elseif ($r['status']==='pending'): ?>
                <span class="dl-badge dl-badge-warn"><i class="fas fa-clock"></i> Pending</span>
              <?php else: ?>
                <span class="dl-badge dl-badge-warn"><i class="fas fa-exclamation-circle"></i> Rejected</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="#" class="dl-btn dl-btn-xs dl-btn-secondary dl-open" data-id="<?= (int)$r['id'] ?>"><i class="fas fa-eye"></i> Open</a>
              <?php if ($uid === (int)$r['created_by']): ?>
                <button class="dl-btn dl-btn-xs dl-btn-danger dl-delete" data-id="<?= (int)$r['id'] ?>"><i class="fas fa-trash"></i></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
<?php endif; ?>

  <!-- Modal -->
  <div id="dl-modal" class="dl-modal" aria-hidden="true">
    <div class="dl-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dl-modal-title">
      <div class="dl-modal-header">
        <h3 id="dl-modal-title">Daily Log</h3>
        <button type="button" class="dl-close" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <form id="dl-form" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($ajaxBase) ?>?ajax=daily&project_id=<?= (int)$projectId ?>">
        <input type="hidden" name="dl_action" value="create">
        <input type="hidden" name="id" value="">

        <!-- Row 1: Code, Date, Name -->
        <div class="dl-grid" style="margin-top:8px;">
          <div class="kv"><label>Code *</label><input type="text" name="code" placeholder="DL-0001"></div>
          <div class="kv"><label>Date *</label><input type="date" name="entry_date" value="<?= date('Y-m-d') ?>"></div>
          <div class="kv"><label>Name *</label><input type="text" name="name" placeholder="Work summary"></div>
        </div>

        <!-- Row 2: Weather (4 in one row) -->
        <div class="dl-grid-4" style="margin-top:12px;">
          <div class="kv"><label>Weather (Morning)</label><select name="weather_morning"><option value="">--</option><option>sunny</option><option>cloudy</option><option>rain</option></select></div>
          <div class="kv"><label>Weather (Afternoon)</label><select name="weather_afternoon"><option value="">--</option><option>sunny</option><option>cloudy</option><option>rain</option></select></div>
          <div class="kv"><label>Weather (Evening)</label><select name="weather_evening"><option value="">--</option><option>sunny</option><option>cloudy</option><option>rain</option></select></div>
          <div class="kv"><label>Weather (Night)</label><select name="weather_night"><option value="">--</option><option>sunny</option><option>cloudy</option><option>rain</option></select></div>
        </div>

        <!-- Row 3: Cleanliness + Safety -->
        <div class="dl-grid-2" style="margin-top:12px;">
          <div class="kv"><label>Site Cleanliness</label><select name="site_cleanliness"><option value="normal">normal</option><option value="good">good</option><option value="bad">bad</option></select></div>
          <div class="kv"><label>Labor Safety</label><select name="labor_safety"><option value="normal">normal</option><option value="good">good</option><option value="bad">bad</option></select></div>
        </div>

        <!-- Equipment -->
        <fieldset class="dl-fieldset">
          <legend>Equipment</legend>
          <div id="dl-eq-list"></div>
          <button type="button" id="dl-eq-add" class="dl-btn dl-btn-link"><i class="fas fa-plus"></i> Add equipment</button>
        </fieldset>

        <!-- Labor -->
        <fieldset class="dl-fieldset">
          <legend>Labor</legend>
          <div id="dl-lb-list"></div>
          <button type="button" id="dl-lb-add" class="dl-btn dl-btn-link"><i class="fas fa-plus"></i> Add labor</button>
        </fieldset>

        <!-- Work Detail -->
        <fieldset class="dl-fieldset">
          <legend>Work Detail</legend>
          <textarea name="work_detail" rows="5" placeholder="Describe works done today..."></textarea>
        </fieldset>

        <!-- Row LAST: Approval Group + Images -->
        <div class="dl-grid-2" style="margin-top:12px;">
          <div class="kv">
            <label>Approval Group</label>
            <select name="approval_group_id">
              <option value="">-- None --</option>
              <?php
              if (has_table($pdo,'project_groups')) {
                $gs=$pdo->prepare('SELECT id,name FROM project_groups WHERE project_id=? ORDER BY name');
                $gs->execute([(int)$projectId]);
                foreach($gs->fetchAll(PDO::FETCH_ASSOC) as $g){
                  echo '<option value="'.(int)$g['id'].'">'.htmlspecialchars($g['name']).'</option>';
                }
              }
              ?>
            </select>
          </div>
          <div class="kv">
            <label>Images</label>
            <input id="dl-images-input" type="file" name="images[]" multiple accept="image/*">
            <div id="dl-images-view"></div>
          </div>
        </div>

        <div class="dl-modal-actions">
          <button type="button" class="dl-btn dl-btn-secondary dl-cancel">Cancel</button>
          <button type="submit" class="dl-btn dl-btn-primary"><i class="fas fa-save"></i> Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Lightbox -->
  <div id="dl-lightbox"><span class="close">&times;</span><img id="dl-lightbox-img" src="" alt=""></div>
</section>
<link rel="stylesheet" href="/../assets/css/project_tab_daily.css?v=14">
<script src="/../assets/js/project_tab_daily.js?v=14"></script>
