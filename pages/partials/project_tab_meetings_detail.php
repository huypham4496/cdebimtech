<?php
// pages/partials/project_tab_meetings_detail.php
if (session_status() === PHP_SESSION_NONE) session_start();

$PAGES_DIR = dirname(__DIR__);
$APP_ROOT  = dirname($PAGES_DIR);
require_once $APP_ROOT . '/config.php';

/* ------------------------- PDO bootstrap ------------------------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (function_exists('getPDO')) { try { $pdo = getPDO(); } catch (Throwable $e) {} }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (defined('DB_DSN') && defined('DB_USER')) {
    try {
      $pdo = new PDO(DB_DSN, DB_USER, defined('DB_PASS')?DB_PASS:'', [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
      ]);
    } catch (Throwable $e) {}
  }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (defined('DB_HOST') && defined('DB_NAME')) {
    $user = defined('DB_USER') ? DB_USER : (defined('DB_USERNAME')?DB_USERNAME:'root');
    $pass = defined('DB_PASS') ? DB_PASS : (defined('DB_PASSWORD')?DB_PASSWORD:'');
    $port = defined('DB_PORT') ? DB_PORT : null;
    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
    $dsn = 'mysql:host='.DB_HOST.($port?';port='.$port:'').';dbname='.DB_NAME.';charset='.$charset;
    try {
      $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
      ]);
    } catch (Throwable $e) {}
  }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $dbUrl = getenv('DATABASE_URL') ?: getenv('JAWSDB_URL') ?: getenv('CLEARDB_DATABASE_URL');
  if ($dbUrl) {
    $p = parse_url($dbUrl);
    if ($p && in_array($p['scheme']??'', ['mysql','mariadb'])) {
      $host=$p['host']??'localhost'; $port=$p['port']??null;
      $user=$p['user']??''; $pass=$p['pass']??''; $name=isset($p['path'])?ltrim($p['path'],'/'):'';
      $dsn='mysql:host='.$host.($port?';port='.$port:'').';dbname='.$name.';charset=utf8mb4';
      try {
        $pdo = new PDO($dsn,$user,$pass,[
          PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
        ]);
      } catch (Throwable $e) {}
    }
  }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo "PDO not set (config.php chưa khởi tạo).";
  exit;
}

/* ------------------------- Utils ------------------------- */
function md_user_id(): int {
  foreach ([
    $_SESSION['user_id'] ?? null,
    $_SESSION['CURRENT_USER_ID'] ?? null,
    $_SESSION['auth']['user_id'] ?? null,
    $_SESSION['auth']['id'] ?? null,
    $_SESSION['user']['id'] ?? null,
    $_SESSION['user']['user_id'] ?? null,
    $_SESSION['uid'] ?? null,
  ] as $v) if (is_numeric($v) && (int)$v>0) return (int)$v;
  if (isset($_GET['user_id_override']) && is_numeric($_GET['user_id_override'])) return (int)$_GET['user_id_override'];
  return 0;
}
$CURRENT_USER_ID = md_user_id();

function md_json($data, int $code=200) {
  while (ob_get_level()) { ob_end_clean(); }
  ini_set('display_errors','0');
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function md_isProjectMember(PDO $pdo, int $projectId, int $userId): bool {
  try { $s=$pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND user_id=? LIMIT 1");
        $s->execute([$projectId,$userId]); if ($s->fetchColumn()) return true; } catch(Throwable $e){}
  try { $s=$pdo->prepare("SELECT 1 FROM project_members WHERE project_id=? AND user_id=? LIMIT 1");
        $s->execute([$projectId,$userId]); if ($s->fetchColumn()) return true; } catch(Throwable $e){}
  return false;
}
function md_canViewMeeting(PDO $pdo, array $meeting, int $userId): bool {
  $pid=(int)$meeting['project_id'];
  if (md_isProjectMember($pdo,$pid,$userId)) return true;
  if ((int)($meeting['created_by']??0) === $userId) return true;
  try { $s=$pdo->prepare("SELECT 1 FROM project_meeting_attendees WHERE meeting_id=? AND user_id=? LIMIT 1");
        $s->execute([(int)$meeting['id'],$userId]); if ($s->fetchColumn()) return true; } catch(Throwable $e){}
  return false;
}
function md_members(PDO $pdo, int $projectId): array {
  try{
    $q=$pdo->prepare("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS full_name, u.email
                      FROM project_group_members gm JOIN users u ON u.id=gm.user_id
                      WHERE gm.project_id=? ORDER BY full_name ASC");
    $q->execute([$projectId]); $r=$q->fetchAll(PDO::FETCH_ASSOC); if ($r) return $r;
  } catch(Throwable $e){}
  $q=$pdo->prepare("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS full_name, u.email
                    FROM project_members pm JOIN users u ON u.id=pm.user_id
                    WHERE pm.project_id=? ORDER BY full_name ASC");
  $q->execute([$projectId]); return $q->fetchAll(PDO::FETCH_ASSOC);
}

/* ------------------------- AJAX router ------------------------- */
$ajax       = $_GET['ajax'] ?? $_POST['ajax'] ?? null;
$meeting_id = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : ((isset($_POST['meeting_id'])) ? (int)$_POST['meeting_id'] : 0);

if ($ajax) {
  if ($meeting_id<=0) md_json(['error'=>'Missing meeting_id'], 400);

  $st=$pdo->prepare("SELECT pm.*, p.name AS project_name
                     FROM project_meetings pm
                     JOIN projects p ON p.id=pm.project_id
                     WHERE pm.id=?");
  $st->execute([$meeting_id]);
  $meeting=$st->fetch(PDO::FETCH_ASSOC);
  if (!$meeting) md_json(['error'=>'Meeting not found'], 404);

  if ($CURRENT_USER_ID<=0 || !md_canViewMeeting($pdo,$meeting,$CURRENT_USER_ID)) {
    md_json(['error'=>'⚠️ Bạn không có quyền truy cập cuộc họp này (chỉ thành viên trong dự án / người tạo / người được mời mới xem/sửa).'], 403);
  }

  if ($ajax==='load') {
    $detail=null;
    try {
      $q=$pdo->prepare("SELECT content_html, updated_by, updated_at FROM project_meeting_details WHERE meeting_id=?");
      $q->execute([$meeting_id]); $detail=$q->fetch(PDO::FETCH_ASSOC);
    } catch(Throwable $e) {}

    $att=$pdo->prepare("SELECT * FROM project_meeting_attendees WHERE meeting_id=? ORDER BY id ASC");
    $att->execute([$meeting_id]); $attendees=$att->fetchAll(PDO::FETCH_ASSOC);

    $members = md_members($pdo, (int)$meeting['project_id']);

    md_json(['meeting'=>$meeting,'detail'=>$detail,'attendees'=>$attendees,'members'=>$members]);
  }
  elseif ($ajax==='save') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) $payload = $_POST;

    $content_html = $payload['content_html'] ?? '';
    $selected_user_ids = $payload['selected_user_ids'] ?? [];
    $external_participants = $payload['external_participants'] ?? [];

    $pdo->beginTransaction();
    try {
      $pdo->exec("CREATE TABLE IF NOT EXISTS project_meeting_details (
        meeting_id INT(11) NOT NULL PRIMARY KEY,
        content_html LONGTEXT NULL,
        updated_by INT(11) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_pmd_meeting FOREIGN KEY (meeting_id) REFERENCES project_meetings(id) ON DELETE CASCADE,
        CONSTRAINT fk_pmd_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

      $ins=$pdo->prepare("INSERT INTO project_meeting_details (meeting_id, content_html, updated_by, updated_at)
                          VALUES (?,?,?,NOW())
                          ON DUPLICATE KEY UPDATE content_html=VALUES(content_html), updated_by=VALUES(updated_by), updated_at=NOW()");
      $ins->execute([$meeting_id,$content_html,$CURRENT_USER_ID]);

      $pdo->prepare("DELETE FROM project_meeting_attendees WHERE meeting_id=?")->execute([$meeting_id]);

      if (is_array($selected_user_ids)) {
        $ins1=$pdo->prepare("INSERT INTO project_meeting_attendees (meeting_id, user_id, is_external) VALUES (?,?,0)");
        foreach ($selected_user_ids as $uid) { $uid=(int)$uid; if ($uid>0) $ins1->execute([$meeting_id,$uid]); }
      }
      if (is_array($external_participants)) {
        $ins2=$pdo->prepare("INSERT INTO project_meeting_attendees (meeting_id, external_name, external_email, is_external) VALUES (?,?,?,1)");
        foreach ($external_participants as $ep) {
          $name=trim($ep['name']??''); $email=trim($ep['email']??'');
          if ($name==='' && $email==='') continue;
          $ins2->execute([$meeting_id,$name,$email]);
        }
      }

      $pdo->exec("CREATE TABLE IF NOT EXISTS project_meeting_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        meeting_id INT NOT NULL,
        sender_id INT NULL,
        receiver_id INT NULL,
        message VARCHAR(500) NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pmn_receiver (receiver_id, is_read),
        CONSTRAINT fk_pmn_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_pmn_meeting FOREIGN KEY (meeting_id) REFERENCES project_meetings(id) ON DELETE CASCADE,
        CONSTRAINT fk_pmn_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_pmn_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

      if (!empty($selected_user_ids)) {
        $stmt=$pdo->prepare("SELECT title, project_id FROM project_meetings WHERE id=?");
        $stmt->execute([$meeting_id]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['title'=>'#'.$meeting_id,'project_id'=>null];
        $msg='Bạn đã được thêm vào cuộc họp: '.($m['title'] ?? ('#'.$meeting_id));
        $notif=$pdo->prepare("INSERT INTO project_meeting_notifications (project_id, meeting_id, sender_id, receiver_id, message, created_at, is_read)
                              VALUES (?,?,?,?,?,NOW(),0)");
        foreach ($selected_user_ids as $uid) {
          $notif->execute([(int)$m['project_id'],$meeting_id,$CURRENT_USER_ID,(int)$uid,$msg]);
        }
      }

      $pdo->commit();
      md_json(['ok'=>true]);
    } catch(Throwable $e) {
      $pdo->rollBack();
      md_json(['error'=>'Save failed','detail'=>$e->getMessage()], 500);
    }
  }
  elseif ($ajax==='export_doc') {
    while (ob_get_level()) { ob_end_clean(); }
// --- Build safe filename: "title_start_time.doc" ---
$rawTitle = $meeting['title'] ?? ('Meeting-'.$meeting_id);
$rawStart = $meeting['start_time'] ?? '';

// Loại ký tự đặc biệt khỏi title để an toàn tên file (Windows/macOS/Linux)
$safeTitle = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim($rawTitle));

// Định dạng thời gian không chứa dấu ":" (tránh lỗi tên file trên Windows)
$timePart = $rawStart ? date('Y-m-d_Hi', strtotime($rawStart)) : date('Y-m-d');

$filename = $safeTitle . '_' . $timePart . '.doc';

// --- Gửi header sau khi có $filename ---
header("Content-Type: application/msword; charset=utf-8");
header('Cache-Control: no-store');
header('Content-Disposition: attachment; filename="' . $filename . '"');

    $st=$pdo->prepare("SELECT pm.*, p.name AS project_name
                       FROM project_meetings pm
                       JOIN projects p ON p.id=pm.project_id
                       WHERE pm.id=?");
    $st->execute([$meeting_id]); $meeting=$st->fetch(PDO::FETCH_ASSOC) ?: [];

    $att=$pdo->prepare("SELECT a.*, u.first_name, u.last_name, u.email
                        FROM project_meeting_attendees a
                        LEFT JOIN users u ON u.id=a.user_id
                        WHERE a.meeting_id=? ORDER BY a.id ASC");
    $att->execute([$meeting_id]); $attendees=$att->fetchAll(PDO::FETCH_ASSOC);

    $content_html='';
    try{ $q=$pdo->prepare("SELECT content_html FROM project_meeting_details WHERE meeting_id=?");
         $q->execute([$meeting_id]); $row=$q->fetch(PDO::FETCH_ASSOC);
         if ($row) $content_html=$row['content_html'] ?? ''; } catch(Throwable $e) {}

    ?>
    <html>
    <head>
      <meta charset="utf-8">
      <title>Meeting Minutes</title>
      <style>
        @page { size: A4; margin: 20mm; }
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
          <?php foreach ($attendees as $i=>$a): ?>
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
    <?php
    exit;
  }
  else {
    md_json(['error'=>'Unknown action'], 400);
  }
  exit;
}

/* ------------------------- Non-AJAX render ------------------------- */
$meeting_id = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;
if ($meeting_id<=0) { echo "Missing meeting_id"; exit; }

$st=$pdo->prepare("SELECT pm.*, p.name AS project_name
                   FROM project_meetings pm
                   JOIN projects p ON p.id=pm.project_id
                   WHERE pm.id=?");
$st->execute([$meeting_id]); $meeting=$st->fetch(PDO::FETCH_ASSOC);
if (!$meeting) { echo "Meeting not found"; exit; }

if ($CURRENT_USER_ID<=0 || !md_canViewMeeting($pdo,$meeting,$CURRENT_USER_ID)) {
  echo "⚠️ Bạn không có quyền truy cập cuộc họp này (chỉ thành viên trong dự án / người tạo / người được mời mới xem/sửa).";
  exit;
}

$IN_PROJECT_VIEW = (basename($_SERVER['SCRIPT_NAME']) === 'project_view.php');
if (!$IN_PROJECT_VIEW) {
  $pid = (int)$meeting['project_id'];
  $url = "../project_view.php?id={$pid}&tab=meetings&meeting_id={$meeting_id}";
  header("Location: $url", true, 302);
  exit;
}

$ASSETS_PREFIX   = "../assets";
$ENDPOINT_PREFIX = "./partials";
?>
<!-- Quill CSS (theme + table plugin) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" />
<link rel="stylesheet" href="https://unpkg.com/quill-better-table@1.2.10/dist/quill-better-table.css" />

<link rel="stylesheet" href="<?= $ASSETS_PREFIX ?>/css/project_tab_meetings_detail.css?v=<?= time() ?>" />
<div class="md-container">
  <div class="md-grid">
    <!-- KV1 -->
    <section class="card md-summary">
      <div class="card-head">
        <h1><i class="fas fa-handshake"></i> <?= htmlspecialchars($meeting['title'] ?? '') ?></h1>
        <div class="actions">
          <a class="btn secondary" href="./project_view.php?id=<?= (int)$meeting['project_id'] ?>&tab=meetings">&larr; Back</a>
          <button id="btn-export" class="btn"><i class="far fa-file-word"></i> Xuất biên bản (Word)</button>
        </div>
      </div>
      <div class="meta">
        <div><div class="label">Project</div><div class="value"><?= htmlspecialchars($meeting['project_name'] ?? '') ?></div></div>
        <div><div class="label">Start time</div><div class="value"><span id="md-start-time">—</span></div></div>
        <div><div class="label">Location</div><div class="value"><span id="md-location">—</span></div></div>
        <div><div class="label">Online link</div><div class="value"><a id="md-online" href="#" target="_blank">—</a></div></div>
        <div class="full"><div class="label">Short description</div><div class="value"><span id="md-short">—</span></div></div>
      </div>
    </section>

    <!-- KV2 -->
    <section class="card md-editor">
      <div class="card-head">
        <h2><i class="fas fa-align-left"></i> Nội dung chi tiết</h2>
        <!-- Dùng làm nơi đặt toolbar của Quill (sau khi JS khởi tạo sẽ gắn vào đây) -->
        <div class="toolbar" id="editor-toolbar"></div>
      </div>
      <div id="editor" class="editor editor-a4" spellcheck="false"></div>
    </section>

    <!-- KV3 -->
    <section class="card md-attendees">
      <div class="card-head"><h2><i class="fas fa-users"></i> Thành viên tham gia</h2></div>
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
      <div class="actions">
        <button id="btn-save" class="btn primary"><i class="far fa-save"></i> Lưu & gửi thông báo</button>
      </div>
    </section>
  </div>
</div>

<script>
  window.MEETING_ID = <?= (int)$meeting_id ?>;
  window.MEETING_ENDPOINT_BASE = '<?= $ENDPOINT_PREFIX ?>/';
</script>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
      crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="<?= $ASSETS_PREFIX ?>/js/project_tab_meetings_detail.js?v=<?= time() ?>"></script>
