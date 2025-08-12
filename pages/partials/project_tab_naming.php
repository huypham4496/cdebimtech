<?php
declare(strict_types=1);
/**
 * Tab "Naming Rule"
 * - Include từ project_view.php: dùng $pdo, $project có sẵn.
 * - Khi gọi trực tiếp qua AJAX (POST + action): tự bootstrap qua /config.php.
 * - Quyền: creator của project HOẶC thuộc group name='manager' của dự án.
 * - Actions (POST-only): list | get | create | update | delete
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* ---------- JSON helper ---------- */
function json_out(array $arr, int $code = 200): void {
  if (!headers_sent()) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode($arr);
  exit;
}

/* ---------- Detect AJAX ---------- */
$isAjax = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']));

/* ---------- Bootstrap (khi gọi trực tiếp) ---------- */
if ($isAjax) {
  $ROOT = realpath(__DIR__ . '/../..'); // /pages/partials -> project root
  if ($ROOT && is_file($ROOT . '/config.php'))               require_once $ROOT . '/config.php';
  if ($ROOT && is_file($ROOT . '/includes/permissions.php')) require_once $ROOT . '/includes/permissions.php';
  if ($ROOT && is_file($ROOT . '/includes/helpers.php'))     require_once $ROOT . '/includes/helpers.php';

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
        json_out(['ok'=>false, 'error'=>'Cannot connect to database: '.$e->getMessage()], 500);
      }
    } else {
      json_out(['ok'=>false, 'error'=>'Database config is missing.'], 500);
    }
  }

  // user id
  $userId = 0;
  foreach ([
    $_SESSION['user_id'] ?? null,
    $_SESSION['id'] ?? null,
    $_SESSION['auth']['user_id'] ?? null,
    $_SESSION['auth']['id'] ?? null,
    $_SESSION['user']['id'] ?? null,
  ] as $cand) {
    if ($cand) { $userId = (int)$cand; break; }
  }
  if ($userId <= 0) { json_out(['ok'=>false, 'error'=>'Not authenticated.'], 401); }

  // project id từ POST
  $project_id = (int)($_POST['project_id'] ?? 0);
  if ($project_id <= 0) { json_out(['ok'=>false, 'error'=>'Missing project_id.'], 400); }

} else {
  // Render mode (include trong project_view.php)
  if (!isset($pdo) || !isset($project)) {
    echo '<div class="alert">Naming tab missing context.</div>';
    return;
  }
  $project_id = (int)($project['id'] ?? 0);
  $userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['auth']['user_id'] ?? $_SESSION['auth']['id'] ?? $_SESSION['user']['id'] ?? 0);
}

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function pad4($n){ $n = (int)$n; if ($n < 1) $n = 1; return str_pad((string)$n, 4, '0', STR_PAD_LEFT); }

/** Ánh xạ tiếng Việt -> ASCII (không rơi chữ) */
function vn_to_ascii(string $s): string {
  static $map = null, $search = null, $replace = null;
  if ($map === null) {
    $map = [
      'a' => ['á','à','ả','ã','ạ','ă','ắ','ằ','ẳ','ẵ','ặ','â','ấ','ầ','ẩ','ẫ','ậ'],
      'A' => ['Á','À','Ả','Ã','Ạ','Ă','Ắ','Ằ','Ẳ','Ẵ','Ặ','Â','Ấ','Ầ','Ẩ','Ẫ','Ậ'],
      'd' => ['đ'], 'D' => ['Đ'],
      'e' => ['é','è','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ'],
      'E' => ['É','È','Ẻ','Ẽ','Ẹ','Ê','Ế','Ề','Ể','Ễ','Ệ'],
      'i' => ['í','ì','ỉ','ĩ','ị'], 'I' => ['Í','Ì','Ỉ','Ĩ','Ị'],
      'o' => ['ó','ò','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ'],
      'O' => ['Ó','Ò','Ỏ','Õ','Ọ','Ô','Ố','Ồ','Ổ','Ỗ','Ộ','Ơ','Ớ','Ờ','Ở','Ỡ','Ợ'],
      'u' => ['ú','ù','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự'],
      'U' => ['Ú','Ù','Ủ','Ũ','Ụ','Ư','Ứ','Ừ','Ử','Ữ','Ự'],
      'y' => ['ý','ỳ','ỷ','ỹ','ỵ'], 'Y' => ['Ý','Ỳ','Ỷ','Ỹ','Ỵ'],
    ];
    $search = []; $replace = [];
    foreach ($map as $ascii => $chars) {
      foreach ($chars as $ch) { $search[] = $ch; $replace[] = $ascii; }
    }
  }
  return str_replace($search, $replace, $s);
}

/** TitleCase + bỏ dấu + bỏ phân cách -> nối liền (Trụ cầu t1 -> TruCauT1) */
function vn_titlecase_join(string $s): string {
  $s = vn_to_ascii($s);
  $chunks = preg_split('/[^A-Za-z0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
  $fixed  = array_map(function($c){
    $first = substr($c,0,1);
    $rest  = substr($c,1);
    return strtoupper($first) . strtolower($rest);
  }, $chunks);
  $joined = implode('', $fixed);
  return preg_replace('/[^A-Za-z0-9_\-]/','', $joined) ?? '';
}

function sanitize_extension($ext){
  $e = strtolower(trim((string)$ext));
  if ($e === '') return '';
  return preg_match('/^[a-z0-9]{1,10}$/', $e) ? $e : '';
}

/** GHÉP giống Preview (không xử lý lại title) */
function compose_filename($project_name, $originator, $system_code, $level_code, $type_code, $role_code, $number_seq, $file_title, $extension=''){
  $parts = [
    strtoupper(trim($project_name)),
    strtoupper(trim($originator)),
    strtoupper(trim($system_code)),
    strtoupper(trim($level_code)),
    strtoupper(trim($type_code)),
    strtoupper(trim($role_code)),
    pad4($number_seq),
    trim((string)$file_title),
  ];
  $joined = implode('-', $parts);
  $ext = sanitize_extension($extension);
  return $ext !== '' ? ($joined . '.' . $ext) : $joined;
}

/** Cắt title/ext từ input thô giống hệt Preview */
function split_title_ext_from_raw(string $raw): array {
  $raw = trim($raw);
  if ($raw === '') return ['', ''];
  $pos = strrpos($raw, '.');
  if ($pos !== false && $pos > 0 && $pos < strlen($raw) - 1) {
    $left = substr($raw, 0, $pos);
    $right = substr($raw, $pos + 1);
    $title = vn_titlecase_join($left);
    $ext   = sanitize_extension($right);
    return [$title, $ext];
  }
  return [vn_titlecase_join($raw), ''];
}

function is_project_manager(PDO $pdo, int $projectId, int $userId): bool {
  if ($projectId <= 0 || $userId <= 0) return false;

  $st = $pdo->prepare("SELECT created_by FROM projects WHERE id=:pid LIMIT 1");
  $st->execute([':pid'=>$projectId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if ($r && (int)$r['created_by'] === $userId) return true;

  $st = $pdo->prepare("
    SELECT 1
    FROM project_group_members pgm
    JOIN project_groups pg ON pg.id = pgm.group_id
    WHERE pgm.project_id = :pid
      AND pgm.user_id   = :uid
      AND LOWER(pg.name) = 'manager'
    LIMIT 1
  ");
  $st->execute([':pid'=>$projectId, ':uid'=>$userId]);
  return (bool)$st->fetch();
}

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

/* ---------- AJAX (POST-only) ---------- */
$action = $isAjax ? ($_POST['action'] ?? '') : '';

if ($isAjax) {
  if (!isset($pdo) || !($pdo instanceof PDO)) json_out(['ok'=>false, 'error'=>'Database connection (PDO) is not available.'], 500);
  if ($project_id <= 0) json_out(['ok'=>false, 'error'=>'Missing project_id.'], 400);

  ensure_naming_table($pdo);
  $is_manager = is_project_manager($pdo, $project_id, (int)$userId);

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
    json_out(['ok'=>true, 'data'=>$rows, 'is_manager'=>$is_manager]);
  }

  if ($action === 'get') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_out(['ok'=>false, 'error'=>'Missing id.'], 400);
    $stm = $pdo->prepare("SELECT * FROM project_naming_rules WHERE id=:id AND project_id=:pid LIMIT 1");
    $stm->execute([':id'=>$id, ':pid'=>$project_id]);
    $row = $stm->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(['ok'=>false, 'error'=>'Record not found.'], 404);
    json_out(['ok'=>true, 'data'=>$row, 'is_manager'=>$is_manager]);
  }

  if ($action === 'create' || $action === 'update') {
    if (!$is_manager) json_out(['ok'=>false, 'error'=>'Forbidden: you are not a project manager.'], 403);

    // Mã viết HOA, số, v.v...
    $project_name = strtoupper(trim((string)($_POST['project_name'] ?? '')));
    $originator   = strtoupper(trim((string)($_POST['originator'] ?? '')));
    $system_code  = strtoupper(trim((string)($_POST['system_code'] ?? 'ZZ')));
    $level_code   = strtoupper(trim((string)($_POST['level_code'] ?? 'ZZ')));
    $type_code    = strtoupper(trim((string)($_POST['type_code'] ?? 'M3')));
    $role_code    = strtoupper(trim((string)($_POST['role_code'] ?? 'Z')));
    $number_seq   = (int)($_POST['number_seq'] ?? 1);
    if ($number_seq < 1) $number_seq = 1;

    // Ưu tiên input thô để đồng bộ Preview
    $raw_full = (string)($_POST['file_title_raw'] ?? '');
    if (trim($raw_full) !== '') {
      [$file_title, $extension] = split_title_ext_from_raw($raw_full);
    } else {
      $file_title = vn_titlecase_join((string)($_POST['file_title'] ?? ''));
      $extension  = sanitize_extension($_POST['extension'] ?? '');
    }

    $errors = [];
    if ($project_name === '') $errors[] = 'Project name is required.';
    if ($originator === '')   $errors[] = 'Originator is required.';
    if ($file_title === '')   $errors[] = 'File title is required.';
    if ($errors) json_out(['ok'=>false, 'error'=>implode(' ', $errors)], 422);

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
        ':uid'=>(int)$userId, ':uid2'=>(int)$userId
      ]);
      if (!$ok) json_out(['ok'=>false, 'error'=>'Insert failed.'], 500);
      json_out(['ok'=>true, 'id'=>$pdo->lastInsertId(), 'computed_filename'=>$computed]);
    }

    if ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) json_out(['ok'=>false, 'error'=>'Missing id.'], 400);
      $stm = $pdo->prepare("
        UPDATE project_naming_rules SET
          project_name=:pname, originator=:org, system_code=:sys, level_code=:lvl, type_code=:typ, role_code=:rol,
          number_seq=:num, file_title=:ftitle, extension=:ext, computed_filename=:cf, updated_by=:uid
        WHERE id=:id AND project_id=:pid
      ");
      $ok = $stm->execute([
        ':pname'=>$project_name, ':org'=>$originator, ':sys'=>$system_code, ':lvl'=>$level_code,
        ':typ'=>$type_code, ':rol'=>$role_code, ':num'=>$number_seq, ':ftitle'=>$file_title,
        ':ext'=>$extension, ':cf'=>$computed, ':uid'=>(int)$userId, ':id'=>$id, ':pid'=>$project_id
      ]);
      if (!$ok) json_out(['ok'=>false, 'error'=>'Update failed.'], 500);
      json_out(['ok'=>true, 'id'=>$id, 'computed_filename'=>$computed]);
    }
  }

  if ($action === 'delete') {
    if (!$is_manager) json_out(['ok'=>false, 'error'=>'Forbidden: you are not a project manager.'], 403);
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_out(['ok'=>false, 'error'=>'Missing id.'], 400);
    $stm = $pdo->prepare("DELETE FROM project_naming_rules WHERE id=:id AND project_id=:pid");
    $ok = $stm->execute([':id'=>$id, ':pid'=>$project_id]);
    if (!$ok) json_out(['ok'=>false, 'error'=>'Delete failed.'], 500);
    json_out(['ok'=>true]);
  }

  json_out(['ok'=>false, 'error'=>'Invalid action.'], 400);
}

/* ---------- Render HTML ---------- */
$__ver = '1.0.8';
$is_manager = (isset($pdo) && $pdo instanceof PDO && $project_id > 0 && $userId > 0)
  ? is_project_manager($pdo, $project_id, (int)$userId)
  : false;
?>
<link rel="stylesheet" href="../assets/css/project_tab_naming.css?v=<?= $__ver ?>">
<div id="tab-naming-root"
     data-project-id="<?= (int)$project_id ?>"
     data-is-manager="<?= $is_manager ? '1' : '0' ?>"
     data-endpoint="partials/project_tab_naming.php">

  <!-- Preview -->
  <div class="naming-preview-card">
    <div class="naming-preview-label">Preview</div>
    <div class="naming-preview-value" id="namingPreview">CLL-NCC-ZZ-ZZ-M3-S-0001-TruCauT2.dwg</div>
    <div class="naming-preview-help">Rule: [Project]-[Originator]-[System]-[Level]-[Type]-[Role]-[Number]-[FileName]</div>
  </div>

  <!-- Form -->
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
      <input type="text" id="nf_title" placeholder="VD: Trụ cầu t1.dwg → TruCauT1.dwg" <?= $is_manager ? '' : 'disabled' ?>>
    </div>

    <div class="form-actions">
      <?php if ($is_manager): ?>
        <button id="btnSaveNaming" class="btn-primary">Save</button>
        <button id="btnCancelEdit" class="btn-ghost" style="display:none">Cancel</button>
      <?php else: ?>
        <div class="readonly-note">Bạn không thuộc nhóm <strong>manager</strong> của dự án, nên chỉ có quyền xem.</div>
      <?php endif; ?>
      <input type="hidden" id="nf_id" value="">
    </div>
  </div>

  <!-- List -->
  <div class="naming-list-wrap">
    <div class="list-head">
      <h3>Saved Naming Rules</h3>
      <a class="btn-export" href="partials/project_tab_naming_export.php?project_id=<?= (int)$project_id ?>" target="_blank" rel="noopener">Export Excel</a>
    </div>
    <table class="naming-table" id="namingTable">
      <thead>
        <tr>
          <th style="width:26%">Filename</th>
          <th>Project</th>
          <th>Originator</th>
          <th>System</th>
          <th>Level</th>
          <th>Type</th>
          <th>Role</th>
          <th>No.</th>
          <th>Updated</th>
          <?php if ($is_manager): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script src="../assets/js/project_tab_naming.js?v=<?= $__ver ?>"></script>
