<?php
// File: pages/partials/project_tab_meetings_detail.php
// Trang chi tiết cuộc họp (KV1/KV2/KV3) + API AJAX load/save/export_doc
// ĐÃ SỬA: kiểm tra quyền bằng project_group_members (fallback project_members)

/* ===================== BOOTSTRAP PDO (dán nguyên khối này lên đầu file) ===================== */
if (session_status() === PHP_SESSION_NONE) session_start();

$PAGES_DIR = dirname(__DIR__);        // .../pages
$APP_ROOT  = dirname($PAGES_DIR);     // server root (chứa /pages, /assets, /includes, ...)

$__pdo_tried = []; $__pdo_err = [];
$candidates = [
  $APP_ROOT . '/includes/bootstrap.php',
  $APP_ROOT . '/includes/db.php',
  $APP_ROOT . '/includes/database.php',
  $APP_ROOT . '/includes/pdo.php',
  $APP_ROOT . '/includes/init.php',
  $APP_ROOT . '/includes/config.php',
  $APP_ROOT . '/includes/connection.php',
  $APP_ROOT . '/includes/db_connect.php',
  $APP_ROOT . '/config.php',
  $APP_ROOT . '/config/database.php',
  // 👉 Nếu project_tab_meetings.php đang require file cụ thể khác, THÊM đường dẫn đó vào mảng này
  // $APP_ROOT . '/includes/my_bootstrap.php',
];
foreach ($candidates as $inc) {
  if (!isset($pdo) && is_file($inc)) { $__pdo_tried[] = $inc; try { require_once $inc; } catch (Throwable $e) { $__pdo_err[] = $inc.' => '.$e->getMessage(); } }
}
if (!isset($pdo) && function_exists('getPDO')) { try { $pdo = getPDO(); } catch (Throwable $e) { $__pdo_err[] = 'getPDO() => '.$e->getMessage(); } }
if (!isset($pdo) && defined('DB_DSN') && defined('DB_USER')) {
  try { $pdo = new PDO(DB_DSN, DB_USER, defined('DB_PASS')?DB_PASS:'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
  catch (Throwable $e) { $__pdo_err[] = 'DB_DSN => '.$e->getMessage(); }
}
if (!isset($pdo) && defined('DB_HOST') && defined('DB_NAME')) {
  $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
  try { $pdo = new PDO($dsn, defined('DB_USER')?DB_USER:'root', defined('DB_PASS')?DB_PASS:'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
  catch (Throwable $e) { $__pdo_err[] = 'DB_HOST/DB_NAME => '.$e->getMessage(); }
}
if (!isset($pdo)) {
  $dbUrl = getenv('DATABASE_URL') ?: getenv('JAWSDB_URL') ?: getenv('CLEARDB_DATABASE_URL');
  if ($dbUrl) {
    $p = parse_url($dbUrl);
    if ($p && isset($p['scheme']) && in_array($p['scheme'], ['mysql','mariadb'])) {
      $dsn = 'mysql:host='.$p['host'].($p['port']?';port='.$p['port']:'').';dbname='.ltrim($p['path'],'/').';charset=utf8mb4';
      try { $pdo = new PDO($dsn, $p['user'] ?? '', $p['pass'] ?? '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
      catch (Throwable $e) { $__pdo_err[] = 'DATABASE_URL(mysql) => '.$e->getMessage(); }
    }
  }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (isset($_GET['debug_boot']) && $_GET['debug_boot']=='1') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "⚠️ PDO NOT SET\nTried to include:\n- ".implode("\n- ", $__pdo_tried)."\n\n";
    if ($__pdo_err) echo "Errors:\n- ".implode("\n- ", $__pdo_err)."\n\n";
    echo "HINT: Copy y hệt dòng require bootstrap ở pages/partials/project_tab_meetings.php vào file này.\n";
    exit;
  }
  http_response_code(500);
  echo "Database is not initialized. Please include the same bootstrap used by project_tab_meetings.php.";
  exit;
}
/* ===================== HẾT BOOTSTRAP ===================== */

/* ===== Lấy CURRENT_USER_ID từ session ===== */
$CURRENT_USER_ID = 0;
foreach ([
  $_SESSION['user_id'] ?? null,
  $_SESSION['CURRENT_USER_ID'] ?? null,
  $_SESSION['auth']['user_id'] ?? null,
  $_SESSION['auth']['id'] ?? null,
] as $v) { if (is_numeric($v) && (int)$v>0) { $CURRENT_USER_ID = (int)$v; break; } }

/* ===== Helpers ===== */
function json_response($data, int $code=200) {
  http_response_code($code); header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}

/** Kiểm tra thành viên dự án: ưu tiên project_group_members, fallback project_members */
function jd_isProjectMember(PDO $pdo, int $projectId, int $userId): bool {
  try {
    $s = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id = ? AND user_id = ? LIMIT 1");
    $s->execute([$projectId, $userId]);
    if ($s->fetchColumn()) return true;
  } catch (Throwable $e) { /* bảng không tồn tại -> fallback */ }

  try {
    $s = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
    $s->execute([$projectId, $userId]);
    return (bool)$s->fetchColumn();
  } catch (Throwable $e) { return false; }
}

/** Lấy danh sách thành viên dự án để tick: ưu tiên project_group_members, fallback project_members */
function jd_fetchProjectMembers(PDO $pdo, int $projectId): array {
  try {
    $q = $pdo->prepare("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS full_name, u.email
                        FROM project_group_members gm
                        JOIN users u ON u.id = gm.user_id
                        WHERE gm.project_id = ?
                        ORDER BY full_name ASC");
    $q->execute([$projectId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) return $rows;
  } catch (Throwable $e) {}

  $q = $pdo->prepare("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS full_name, u.email
                      FROM project_members pm
                      JOIN users u ON u.id = pm.user_id
                      WHERE pm.project_id = ?
                      ORDER BY full_name ASC");
  $q->execute([$projectId]);
  return $q->fetchAll(PDO::FETCH_ASSOC);
}

/* ===== Router (AJAX) ===== */
$ajax = $_GET['ajax'] ?? $_POST['ajax'] ?? null;
$meeting_id = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : (isset($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : 0);

if ($ajax) {
  if ($meeting_id<=0) json_response(['error'=>'Missing meeting_id'], 400);

  // Lấy thông tin meeting + project
  $stmt = $pdo->prepare("SELECT pm.*, p.name AS project_name
                         FROM project_meetings pm
                         JOIN projects p ON p.id = pm.project_id
                         WHERE pm.id = ?");
  $stmt->execute([$meeting_id]);
  $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$meeting) json_response(['error'=>'Meeting not found'], 404);

  $project_id = (int)$meeting['project_id'];
  if ($CURRENT_USER_ID<=0 || !jd_isProjectMember($pdo, $project_id, $CURRENT_USER_ID)) {
    json_response(['error'=>'⚠️ Bạn không có quyền truy cập cuộc họp này (chỉ thành viên trong dự án mới xem/sửa).'], 403);
  }

  if ($ajax === 'load') {
    // Nội dung chi tiết (có thể chưa tồn tại)
    $detail = null;
    try {
      $q = $pdo->prepare("SELECT content_html, updated_by, updated_at FROM project_meeting_details WHERE meeting_id = ?");
      $q->execute([$meeting_id]);
      $detail = $q->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* bảng chưa có -> sẽ auto-create khi save */ }

    // Attendees hiện tại
    $att = $pdo->prepare("SELECT * FROM project_meeting_attendees WHERE meeting_id = ? ORDER BY id ASC");
    $att->execute([$meeting_id]);
    $attendees = $att->fetchAll(PDO::FETCH_ASSOC);

    // Thành viên nội bộ để tick (ưu tiên group_members)
    $members = jd_fetchProjectMembers($pdo, $project_id);

    json_response([
      'meeting' => $meeting,
      'detail' => $detail,
      'attendees' => $attendees,
      'members' => $members,
      'needs_migration' => ($detail === false || $detail === null),
    ]);
  }
  elseif ($ajax === 'save') {
    $payload_raw = file_get_contents('php://input');
    $payload = json_decode($payload_raw, true);
    if (!is_array($payload)) $payload = $_POST;

    $content_html = $payload['content_html'] ?? '';
    $selected_user_ids = $payload['selected_user_ids'] ?? [];
    $external_participants = $payload['external_participants'] ?? [];

    $pdo->beginTransaction();
    try {
      // Tạo bảng lưu chi tiết nếu chưa có
      $pdo->exec("CREATE TABLE IF NOT EXISTS project_meeting_details (
        meeting_id INT(11) NOT NULL PRIMARY KEY,
        content_html LONGTEXT NULL,
        updated_by INT(11) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_pmd_meeting FOREIGN KEY (meeting_id) REFERENCES project_meetings(id) ON DELETE CASCADE,
        CONSTRAINT fk_pmd_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

      // Upsert nội dung
      $ins = $pdo->prepare("INSERT INTO project_meeting_details (meeting_id, content_html, updated_by, updated_at)
                            VALUES (?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE content_html=VALUES(content_html), updated_by=VALUES(updated_by), updated_at=NOW()");
      $ins->execute([$meeting_id, $content_html, $CURRENT_USER_ID]);

      // Reset attendees
      $pdo->prepare("DELETE FROM project_meeting_attendees WHERE meeting_id = ?")->execute([$meeting_id]);

      // Nội bộ
      if (is_array($selected_user_ids)) {
        $ins1 = $pdo->prepare("INSERT INTO project_meeting_attendees (meeting_id, user_id, is_external) VALUES (?, ?, 0)");
        foreach ($selected_user_ids as $uid) { $uid=(int)$uid; if ($uid>0) $ins1->execute([$meeting_id, $uid]); }
      }
      // Bên ngoài
      if (is_array($external_participants)) {
        $ins2 = $pdo->prepare("INSERT INTO project_meeting_attendees (meeting_id, external_name, external_email, is_external) VALUES (?, ?, ?, 1)");
        foreach ($external_participants as $ep) {
          $name = trim($ep['name'] ?? ''); $email = trim($ep['email'] ?? '');
          if ($name==='' && $email==='') continue;
          $ins2->execute([$meeting_id, $name, $email]);
        }
      }

      // Thông báo cho nội bộ đã tick
      if (!empty($selected_user_ids)) {
        $msg = 'Bạn đã được thêm vào cuộc họp: ' . ($meeting['title'] ?? ('#'.$meeting_id));
        $notif = $pdo->prepare("INSERT INTO project_meeting_notifications (project_id, meeting_id, sender_id, receiver_id, message, created_at, is_read)
                                VALUES (?, ?, ?, ?, ?, NOW(), 0)");
        foreach ($selected_user_ids as $uid) { $notif->execute([$project_id, $meeting_id, $CURRENT_USER_ID, (int)$uid, $msg]); }
      }

      $pdo->commit();
      json_response(['ok'=>true]);
    } catch (Throwable $e) {
      $pdo->rollBack();
      json_response(['error'=>'Save failed','detail'=>$e->getMessage()], 500);
    }
  }
  elseif ($ajax === 'export_doc') {
    header("Content-Type: application/msword; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"Meeting-Minutes-{$meeting_id}.doc\"");

    // Lấy attendees
    $attStmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name, u.email
                              FROM project_meeting_attendees a
                              LEFT JOIN users u ON u.id = a.user_id
                              WHERE a.meeting_id = ? ORDER BY a.id ASC");
    $attStmt->execute([$meeting_id]);
    $attendees = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    // Nội dung chi tiết
    $content_html = '';
    try {
      $q = $pdo->prepare("SELECT content_html FROM project_meeting_details WHERE meeting_id = ?");
      $q->execute([$meeting_id]);
      $row = $q->fetch(PDO::FETCH_ASSOC);
      if ($row) $content_html = $row['content_html'] ?? '';
    } catch (Throwable $e) {}

    ob_start(); ?>
    <html>
    <head>
      <meta charset="utf-8">
      <title>Meeting Minutes</title>
      <style>
        body { font-family: 'Times New Roman', serif; }
        h1 { font-size: 22pt; margin-bottom: 6pt; }
        .meta { margin-bottom: 12pt; font-size: 11pt; }
        .meta strong { display:inline-block; width: 120px; }
        .section-title { font-weight: bold; font-size: 12pt; margin: 14pt 0 6pt; }
        table { border-collapse: collapse; width: 100%; font-size: 11pt; }
        th, td { border: 1px solid #000; padding: 6pt; }
      </style>
    </head>
    <body>
      <h1>Meeting Minutes</h1>
      <div class="meta">
        <div><strong>Project:</strong> <?= htmlspecialchars($meeting['project_name'] ?? '') ?></div>
        <div><strong>Title:</strong> <?= htmlspecialchars($meeting['title'] ?? '') ?></div>
        <div><strong>Start Time:</strong> <?= htmlspecialchars($meeting['start_time'] ?? '') ?></div>
        <div><strong>Location:</strong> <?= htmlspecialchars($meeting['location'] ?? '') ?></div>
        <div><strong>Online Link:</strong> <?= htmlspecialchars($meeting['online_link'] ?? '') ?></div>
      </div>

      <div class="section-title">Attendees</div>
      <table>
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Type</th></tr></thead>
        <tbody>
          <?php foreach ($attendees as $i => $a): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= $a['is_external'] ? htmlspecialchars($a['external_name']) : htmlspecialchars(trim(($a['first_name']??'').' '.($a['last_name']??''))) ?></td>
              <td><?= $a['is_external'] ? htmlspecialchars($a['external_email']) : htmlspecialchars($a['email']??'') ?></td>
              <td><?= $a['is_external'] ? 'External' : 'Project Member' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="section-title">Details</div>
      <div><?= $content_html ?></div>
    </body>
    </html>
    <?php echo ob_get_clean(); exit;
  }
  else {
    json_response(['error'=>'Unknown action'], 400);
  }
  exit;
}

/* ===== Page render (HTML) ===== */
$meeting_id = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;
if ($meeting_id<=0) { http_response_code(400); echo "Missing meeting_id"; exit; }

$stmt = $pdo->prepare("SELECT pm.*, p.name AS project_name
                       FROM project_meetings pm
                       JOIN projects p ON p.id = pm.project_id
                       WHERE pm.id = ?");
$stmt->execute([$meeting_id]);
$meeting = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$meeting) { http_response_code(404); echo "Meeting not found"; exit; }

if ($CURRENT_USER_ID<=0 || !jd_isProjectMember($pdo, (int)$meeting['project_id'], $CURRENT_USER_ID)) {
  http_response_code(403);
  echo "⚠️ Bạn không có quyền truy cập cuộc họp này (chỉ thành viên trong dự án mới xem/sửa).";
  exit;
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Meeting Detail - <?= htmlspecialchars($meeting['title'] ?? '') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- assets: /assets ngang hàng /pages => từ /pages/partials đi ra 2 cấp -->
  <link rel="stylesheet" href="../../assets/css/project_tab_meetings_detail.css?v=<?= time() ?>">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="md-body">
  <div class="md-container">
    <!-- KV1: Tóm tắt -->
    <section class="card md-summary">
      <div class="card-head">
        <h1><i class="fas fa-handshake"></i> <?= htmlspecialchars($meeting['title'] ?? '') ?></h1>
        <div class="actions">
          <a class="btn secondary" href="./project_tab_meetings.php?project_id=<?= (int)$meeting['project_id'] ?>">&larr; Back</a>
          <button id="btn-export" class="btn"><i class="far fa-file-word"></i> Xuất biên bản (Word)</button>
        </div>
      </div>
      <div class="grid meta">
        <div>
          <div class="label">Project</div>
          <div class="value"><?= htmlspecialchars($meeting['project_name'] ?? '') ?></div>
        </div>
        <div>
          <div class="label">Start time</div>
          <div class="value"><span id="md-start-time">—</span></div>
        </div>
        <div>
          <div class="label">Location</div>
          <div class="value"><span id="md-location">—</span></div>
        </div>
        <div>
          <div class="label">Online link</div>
          <div class="value"><a id="md-online" href="#" target="_blank">—</a></div>
        </div>
        <div class="full">
          <div class="label">Short description</div>
          <div class="value"><span id="md-short">—</span></div>
        </div>
      </div>
    </section>

    <!-- KV2: Nội dung chi tiết -->
    <section class="card md-editor">
      <div class="card-head">
        <h2><i class="fas fa-align-left"></i> Nội dung chi tiết</h2>
        <div class="toolbar" id="editor-toolbar">
          <button data-cmd="bold" title="Bold"><i class="fas fa-bold"></i></button>
          <button data-cmd="italic" title="Italic"><i class="fas fa-italic"></i></button>
          <button data-cmd="underline" title="Underline"><i class="fas fa-underline"></i></button>
          <button data-cmd="strikeThrough" title="Strike"><i class="fas fa-strikethrough"></i></button>
          <span class="sep"></span>
          <button data-cmd="foreColor" data-value="#d90429" title="Text Color"><i class="fas fa-font"></i></button>
          <button data-cmd="backColor" data-value="#fff3bf" title="Highlight"><i class="fas fa-highlighter"></i></button>
          <span class="sep"></span>
          <select id="font-size">
            <option value="">Size</option>
            <option value="3">Normal</option>
            <option value="4">Large</option>
            <option value="5">Larger</option>
            <option value="6">Huge</option>
          </select>
          <select id="font-name">
            <option value="">Font</option>
            <option>Arial</option>
            <option>Times New Roman</option>
            <option>Tahoma</option>
            <option>Verdana</option>
            <option>Courier New</option>
          </select>
          <span class="sep"></span>
          <button id="btn-insert-table" title="Insert table"><i class="fas fa-table"></i></button>
        </div>
      </div>
      <div id="editor" class="editor" contenteditable="true" spellcheck="false"></div>
    </section>

    <!-- KV3: Thành viên tham gia -->
    <section class="card md-attendees">
      <div class="card-head">
        <h2><i class="fas fa-users"></i> Thành viên tham gia</h2>
      </div>
      <div class="att-grid">
        <div class="att-block">
          <h3>Thành viên trong dự án</h3>
          <div id="member-list" class="member-list"></div>
        </div>
        <div class="att-block">
          <h3>Khách mời bên ngoài</h3>
          <div id="external-list" class="external-list"></div>
          <button id="btn-add-external" class="btn small"><i class="fas fa-user-plus"></i> Thêm</button>
        </div>
      </div>
      <div class="actions right">
        <button id="btn-save" class="btn primary"><i class="far fa-save"></i> Lưu & gửi thông báo</button>
      </div>
    </section>
  </div>

  <script>window.MEETING_ID = <?= (int)$meeting_id ?>;</script>
  <script src="../../assets/js/project_tab_meetings_detail.js?v=<?= time() ?>"></script>
</body>
</html>
