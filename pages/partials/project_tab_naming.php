<?php
/**
 * Tab "Naming Rule"
 * - Expects $pdo from project_view.php; if missing (AJAX direct hit), fallback to /config.php.
 * - Permissions: project creator OR member of project_groups.name = 'manager'.
 * - AJAX: list | get | create | update (JSON)
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/** Fallback: ensure $pdo exists when this file is called directly via AJAX */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $ROOT = realpath(__DIR__ . '/../../'); // from /pages/partials -> project root
  if ($ROOT && is_file($ROOT . '/config.php')) {
    require_once $ROOT . '/config.php';
  }
  if (!isset($pdo) && function_exists('getPDO')) {
    $pdo = getPDO();
  }
}

$project_id = (int)($project['id'] ?? $_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$current_user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

/** Helpers */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function pad4($n){ $n = (int)$n; if ($n < 1) $n = 1; return str_pad((string)$n, 4, '0', STR_PAD_LEFT); }
function sanitize_extension($ext){
  $e = strtolower(trim((string)$ext));
  if ($e === '') return '';
  return preg_match('/^[a-z0-9]{1,10}$/', $e) ? $e : '';
}
function sanitize_title($s){
  // remove diacritics + spaces + special chars (keep [A-Za-z0-9_-])
  $s = (string)$s;
  $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
  $s = preg_replace('/\s+/', '', $s);
  $s = preg_replace('/[^A-Za-z0-9_\-]/', '', $s);
  return $s ?? '';
}
function compose_filename($project_name, $originator, $system_code, $level_code, $type_code, $role_code, $number_seq, $file_title, $extension=''){
  $parts = [
    strtoupper(trim($project_name)),
    strtoupper(trim($originator)),
    strtoupper(trim($system_code)),
    strtoupper(trim($level_code)),
    strtoupper(trim($type_code)),
    strtoupper(trim($role_code)),
    pad4($number_seq),
    trim($file_title),
  ];
  $joined = implode('-', $parts);
  $ext = sanitize_extension($extension);
  return $ext !== '' ? ($joined . '.' . $ext) : $joined;
}

/** Permission: project creator or member of 'manager' group */
function is_project_manager(PDO $pdo, int $projectId, int $userId): bool {
  if ($projectId <= 0 || $userId <= 0) return false;

  $stm = $pdo->prepare("SELECT created_by FROM projects WHERE id=:pid LIMIT 1");
  $stm->execute([':pid'=>$projectId]);
  $row = $stm->fetch(PDO::FETCH_ASSOC);
  if ($row && (int)$row['created_by'] === $userId) return true;

  $stm = $pdo->prepare("
    SELECT 1
    FROM project_group_members pgm
    JOIN project_groups pg ON pg.id = pgm.group_id
    WHERE pgm.project_id = :pid
      AND pgm.user_id   = :uid
      AND LOWER(pg.name) = 'manager'
    LIMIT 1
  ");
  $stm->execute([':pid'=>$projectId, ':uid'=>$userId]);
  return (bool)$stm->fetch();
}

/** Ensure table */
function ensure_naming_table(PDO $pdo): void {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS project_naming_rules (
      id INT AUTO_INCREMENT PRIMARY KEY,
      project_id INT NOT NULL,
      project_name VARCHAR(64) NOT NULL,
      originator VARCHAR(64) NOT NULL,
      system_code VARCHAR(2) NOT NULL,
      level_code VARCHAR(2) NOT NULL,
      type_code VARCHAR(2) NOT NULL,
      role_code VARCHAR(2) NOT NULL,
      number_seq INT NOT NULL DEFAULT 1,
      file_title VARCHAR(255) NOT NULL,
      extension VARCHAR(10) NOT NULL DEFAULT 'dwg',
      computed_filename VARCHAR(300) NOT NULL,
      created_by INT NOT NULL,
      updated_by INT DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_project (project_id),
      KEY idx_filename (computed_filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
}

/** AJAX */
$action = $_GET['action'] ?? $_POST['action'] ?? null;
if ($action) {
  header('Content-Type: application/json; charset=utf-8');

  if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'Database connection (PDO) is not available.']);
    exit;
  }
  if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'Missing project_id.']);
    exit;
  }

  ensure_naming_table($pdo);
  $is_manager = is_project_manager($pdo, $project_id, $current_user_id);

  if ($action === 'list') {
    $stm = $pdo->prepare("
      SELECT id, project_id, project_name, originator, system_code, level_code, type_code, role_code,
             number_seq, file_title, extension, computed_filename, created_by, updated_at, created_at
      FROM project_naming_rules
      WHERE project_id = :pid
      ORDER BY id DESC
    ");
    $stm->execute([':pid'=>$project_id]);
    $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true, 'data'=>$rows, 'is_manager'=>$is_manager]);
    exit;
  }

  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
      http_response_code(400);
      echo json_encode(['ok'=>false, 'error'=>'Missing id.']);
      exit;
    }
    $stm = $pdo->prepare("SELECT * FROM project_naming_rules WHERE id=:id AND project_id=:pid LIMIT 1");
    $stm->execute([':id'=>$id, ':pid'=>$project_id]);
    $row = $stm->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      http_response_code(404);
      echo json_encode(['ok'=>false, 'error'=>'Record not found.']);
      exit;
    }
    echo json_encode(['ok'=>true, 'data'=>$row, 'is_manager'=>$is_manager]);
    exit;
  }

  if ($action === 'create' || $action === 'update') {
    if (!$is_manager) {
      http_response_code(403);
      echo json_encode(['ok'=>false, 'error'=>'Forbidden: you are not a project manager.']);
      exit;
    }

    $project_name = trim($_POST['project_name'] ?? '');
    $originator   = trim($_POST['originator'] ?? '');
    $system_code  = strtoupper(trim($_POST['system_code'] ?? 'ZZ'));
    $level_code   = strtoupper(trim($_POST['level_code'] ?? 'ZZ'));
    $type_code    = strtoupper(trim($_POST['type_code'] ?? 'M3'));
    $role_code    = strtoupper(trim($_POST['role_code'] ?? 'Z'));
    $number_seq   = (int)($_POST['number_seq'] ?? 1);
    if ($number_seq < 1) $number_seq = 1;

    // Title + optional extension are sent separately by JS; sanitize again on server
    $file_title   = sanitize_title($_POST['file_title'] ?? '');
    $extension    = sanitize_extension($_POST['extension'] ?? '');

    $errors = [];
    if ($project_name === '') $errors[] = 'Project name is required.';
    if ($originator === '')   $errors[] = 'Originator is required.';
    if ($file_title === '')   $errors[] = 'File title is required.';
    if ($errors) {
      http_response_code(422);
      echo json_encode(['ok'=>false, 'error'=>implode(' ', $errors)]);
      exit;
    }

    $computed = compose_filename(
      $project_name, $originator, $system_code, $level_code, $type_code, $role_code,
      $number_seq, $file_title, $extension
    );

    if ($action === 'create') {
      $stm = $pdo->prepare("
        INSERT INTO project_naming_rules
          (project_id, project_name, originator, system_code, level_code, type_code, role_code,
           number_seq, file_title, extension, computed_filename, created_by, updated_by)
        VALUES
          (:pid, :pname, :org, :sys, :lvl, :typ, :rol,
           :num, :ftitle, :ext, :cf, :uid, :uid2)
      ");
      $ok = $stm->execute([
        ':pid'=>$project_id, ':pname'=>$project_name, ':org'=>$originator, ':sys'=>$system_code,
        ':lvl'=>$level_code, ':typ'=>$type_code, ':rol'=>$role_code,
        ':num'=>$number_seq, ':ftitle'=>$file_title, ':ext'=>$extension, ':cf'=>$computed,
        ':uid'=>$current_user_id, ':uid2'=>$current_user_id
      ]);
      if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok'=>false, 'error'=>'Insert failed.']);
        exit;
      }
      echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId(), 'computed_filename'=>$computed]);
      exit;
    }

    if ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok'=>false, 'error'=>'Missing id.']);
        exit;
      }
      $stm = $pdo->prepare("
        UPDATE project_naming_rules SET
          project_name=:pname, originator=:org, system_code=:sys, level_code=:lvl, type_code=:typ, role_code=:rol,
          number_seq=:num, file_title=:ftitle, extension=:ext, computed_filename=:cf, updated_by=:uid
        WHERE id=:id AND project_id=:pid
      ");
      $ok = $stm->execute([
        ':pname'=>$project_name, ':org'=>$originator, ':sys'=>$system_code, ':lvl'=>$level_code,
        ':typ'=>$type_code, ':rol'=>$role_code, ':num'=>$number_seq, ':ftitle'=>$file_title,
        ':ext'=>$extension, ':cf'=>$computed, ':uid'=>$current_user_id, ':id'=>$id, ':pid'=>$project_id
      ]);
      if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok'=>false, 'error'=>'Update failed.']);
        exit;
      }
      echo json_encode(['ok'=>true, 'id'=>$id, 'computed_filename'=>$computed]);
      exit;
    }
  }

  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'Invalid action.']);
  exit;
}

// ---- Rendered HTML in project_view.php ----
$__ver = '1.0.3';
$is_manager = (isset($pdo) && $pdo instanceof PDO && $project_id > 0 && $current_user_id > 0)
  ? is_project_manager($pdo, $project_id, $current_user_id)
  : false;
?>
<link rel="stylesheet" href="../assets/css/project_tab_naming.css?v=<?= $__ver ?>">
<div id="tab-naming-root"
     data-project-id="<?= (int)$project_id ?>"
     data-is-manager="<?= $is_manager ? '1' : '0' ?>"
     data-endpoint="partials/project_tab_naming.php">

  <div class="naming-preview-card">
    <div class="naming-preview-label">Preview</div>
    <div class="naming-preview-value" id="namingPreview">CLL-NCC-ZZ-ZZ-M3-S-0001-TruCauT2.dwg</div>
    <div class="naming-preview-help">Quy tắc: [Tên dự án]-[Đơn vị]-[Khối tích/Hệ thống]-[Cao trình/Vị trí]-[Loại]-[Vai trò]-[Số]-[Tên file]</div>
  </div>

  <div class="naming-form <?= $is_manager ? '' : 'is-readonly' ?>">
    <div class="form-row">
      <label for="nf_project_name">Tên dự án</label>
      <input type="text" id="nf_project_name" placeholder="VD: CLL" <?= $is_manager ? '' : 'disabled' ?>>
    </div>

    <div class="form-row">
      <label for="nf_originator">Đơn vị khởi tạo</label>
      <input type="text" id="nf_originator" placeholder="VD: NCC" <?= $is_manager ? '' : 'disabled' ?>>
    </div>

    <div class="form-row">
      <label for="nf_system">Khối tích/Hệ thống</label>
      <select id="nf_system" <?= $is_manager ? '' : 'disabled' ?>>
        <option value="ZZ">ZZ – Tất cả khối tích/ hệ thống</option>
        <option value="XX">XX – Không áp dụng khối tích/hệ thống</option>
        <option value="AR">AR – Kiến trúc</option>
        <option value="BR">BR – Cầu</option>
        <option value="CS">CS – Hệ thống thông tin liên lạc</option>
        <option value="ES">ES – Hệ thống cấp điện</option>
        <option value="LS">LS – Hệ thống chiếu sáng</option>
        <option value="ME">ME – Hệ cơ điện</option>
        <option value="RS">RS – Đường hoặc đường phố</option>
        <option value="RW">RW – Hệ thống thoát nước mưa</option>
        <option value="ST">ST – Kết cấu</option>
        <option value="TE">TE – Địa hình</option>
        <option value="WS">WS – Hệ thống cấp nước</option>
        <option value="WW">WW – Hệ thống thoát nước thải</option>
      </select>
    </div>

    <div class="form-row">
      <label for="nf_level">Cao trình/Vị trí/Lý trình</label>
      <select id="nf_level" <?= $is_manager ? '' : 'disabled' ?>>
        <option value="ZZ">ZZ – Nhiều cao trình/ vị trí</option>
        <option value="XX">XX – Không áp dụng cao trình/ vị trí nào</option>
      </select>
    </div>

    <div class="form-row">
      <label for="nf_type">Loại</label>
      <select id="nf_type" <?= $is_manager ? '' : 'disabled' ?>>
        <option value="AF">AF – Hình ảnh động</option>
        <option value="BQ">BQ – Bảng khối lượng</option>
        <option value="CA">CA – Tính toán</option>
        <option value="CM">CM – Mô hình phối hợp đa bộ môn</option>
        <option value="CP">CP – Kế hoạch chi phí</option>
        <option value="CR">CR – Biểu diễn xung đột</option>
        <option value="DB">DB – Cơ sở dữ liệu</option>
        <option value="DR">DR – Biểu diễn bản vẽ</option>
        <option value="FN">FN – Chú thích tập tin</option>
        <option value="HS">HS – An toàn lao động</option>
        <option value="IE">IE – Tập tin trao đổi thông tin</option>
        <option value="M2">M2 – Mô hình 2D</option>
        <option value="M3" selected>M3 – Mô hình 3D</option>
        <option value="MI">MI – Biên bản/Ghi chú</option>
        <option value="MR">MR – Mô hình phục vụ nội dung BIM khác</option>
        <option value="MS">MS – Biện pháp</option>
        <option value="PP">PP – Thuyết trình</option>
        <option value="RI">RI – Yêu cầu thông tin</option>
        <option value="RP">RP – Báo cáo</option>
        <option value="SH">SH – Tiến độ</option>
        <option value="SP">SP – Tiêu chuẩn</option>
        <option value="SU">SU – Khảo sát</option>
        <option value="VS">VS – Trực quan hóa</option>
      </select>
    </div>

    <div class="form-row">
      <label for="nf_role">Vai trò</label>
      <select id="nf_role" <?= $is_manager ? '' : 'disabled' ?>>
        <option value="A">A – Kiến trúc sư</option>
        <option value="B">B – Giám sát công trình</option>
        <option value="C">C – Kỹ sư xây dựng</option>
        <option value="D">D – Kỹ sư thoát nước</option>
        <option value="E">E – Kỹ sư điện</option>
        <option value="F">F – Quản lý cơ sở vật chất</option>
        <option value="G">G – Khảo sát địa chất và địa hình</option>
        <option value="K">K – Chủ đầu tư</option>
        <option value="L">L – Kiến trúc sư cảnh quan</option>
        <option value="M">M – Kỹ sư cơ điện</option>
        <option value="P">P – Kỹ sư an toàn lao động</option>
        <option value="Q">Q – Kỹ sư dự toán</option>
        <option value="S" selected>S – Kỹ sư kết cấu</option>
        <option value="T">T – Kỹ sư quy hoạch</option>
        <option value="W">W – Nhà thầu</option>
        <option value="X">X – Nhà thầu phụ</option>
        <option value="Y">Y – Chuyên gia thiết kế</option>
        <option value="Z">Z – Chung (không phân bộ môn)</option>
      </select>
    </div>

    <div class="form-row">
      <label for="nf_number">Số</label>
      <input type="text" id="nf_number" value="0001" inputmode="numeric" pattern="[0-9]*" <?= $is_manager ? '' : 'disabled' ?>>
    </div>

    <div class="form-row">
      <label for="nf_title">Tên file</label>
      <input type="text" id="nf_title" placeholder="VD: TruCauT2.dwg hoặc TruCauT2.rvt" <?= $is_manager ? '' : 'disabled' ?>>
    </div>

    <div class="form-actions">
      <?php if ($is_manager): ?>
        <button id="btnSaveNaming" class="btn-primary">Save: <span id="btnSaveText">CLL-NCC-ZZ-ZZ-M3-S-0001-TruCauT2.dwg</span></button>
        <button id="btnCancelEdit" class="btn-ghost" style="display:none">Cancel edit</button>
      <?php else: ?>
        <div class="readonly-note">Bạn không thuộc nhóm <strong>manager</strong> của dự án, nên chỉ có quyền xem.</div>
      <?php endif; ?>
      <input type="hidden" id="nf_id" value="">
      <!-- removed hidden nf_extension: extension is parsed from nf_title -->
    </div>
  </div>

  <div class="naming-list-wrap">
    <div class="list-head">
      <h3>Danh sách Naming Rule đã lưu</h3>
    </div>
    <table class="naming-table" id="namingTable">
      <thead>
        <tr>
          <th style="width:45%">Tên file</th>
          <th>Đơn vị</th>
          <th>Loại</th>
          <th>Vai trò</th>
          <th>Số</th>
          <th>Cập nhật</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script src="../assets/js/project_tab_naming.js?v=<?= $__ver ?>"></script>
