<?php
$__upload_ajax = $_GET['ajax'] ?? $_POST['ajax'] ?? null;
$__is_multipart = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;
$__is_image_upload = ($__upload_ajax === 'upload_image') || (
    $_SERVER['REQUEST_METHOD'] === 'POST' && $__is_multipart && isset($_FILES['file'])
);

if ($__is_image_upload) {
    // Trả JSON và dừng tại đây để không “rơi” xuống phần HTML/logic khác
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    // 1) Lấy meeting_id từ GET/POST (không đụng gì thêm)
    $meeting_id = (int)($_GET['meeting_id'] ?? $_POST['meeting_id'] ?? 0);
    if (!$meeting_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing meeting_id']); exit;
    }

    // 2) Kiểm tra có nhận file không
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        http_response_code(405);
        echo json_encode(['error' => 'Use POST multipart/form-data (field "file")']); exit;
    }
    try {
 // Lấy project_id từ POST (JS gửi kèm) hoặc fallback từ Referer (?id=...) / GET
        $project_id = (int)($_POST['project_id'] ?? $_GET['project_id'] ?? 0);
        if (!$project_id && !empty($_SERVER['HTTP_REFERER'])) {
            $refQuery = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
            if ($refQuery) {
                parse_str($refQuery, $refArr);
                if (!empty($refArr['id'])) {
                    $project_id = (int)$refArr['id'];
                }
            }
        }
        if ($project_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing project_id (send via POST project_id hoặc URL referer có ?id=...)']); exit;
        }

        // Quy tắc thư mục PRJxxxxx theo id project
        $projCode = 'PRJ' . str_pad((string)$project_id, 5, '0', STR_PAD_LEFT);
        $projCode = preg_replace('/[^A-Za-z0-9_\-]/', '_', $projCode);

        // 4) Thư mục lưu ảnh: /uploads/PRJxxxxx/meetings_img/
        // uploads nằm NGANG HÀNG thư mục pages => __DIR__ . '/../../uploads'
        $uploadsRoot = realpath(__DIR__ . '/../../uploads') ?: (__DIR__ . '/../../uploads');
        $targetDir   = $uploadsRoot . '/' . $projCode . '/meetings_img';

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Cannot create uploads folder', 'dir' => $targetDir]); exit;
        }

        // 5) Validate & tạo tên file an toàn
        $orig = $_FILES['file']['name'] ?? 'image';
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION) ?: 'jpg');
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) $ext = 'jpg';

        // Giới hạn size 20MB (tuỳ chỉnh)
        $size = (int)($_FILES['file']['size'] ?? 0);
        if ($size <= 0) { http_response_code(400); echo json_encode(['error'=>'Empty file']); exit; }
        if ($size > 20*1024*1024) { http_response_code(413); echo json_encode(['error'=>'File too large (max 20MB)']); exit; }

        $fname = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dst   = $targetDir . '/' . $fname;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dst)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move uploaded file']); exit;
        }

        // 6) URL public để TinyMCE dùng lại (giữ nguyên mapping /uploads -> thư mục uploads)
        $publicUrl = '/uploads/' . rawurlencode($projCode) . '/meetings_img/' . rawurlencode($fname);
        echo json_encode(['ok' => true, 'location' => $publicUrl]); exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]); exit;
    }
}
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
    // --- FIXED export_doc: inline images + safe filename, keep other code untouched ---
    $meeting_id = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;
    if ($meeting_id <= 0) {
        header('Content-Type: text/plain; charset=utf-8'); http_response_code(400);
        echo "Missing meeting_id"; exit;
    }

    // Load meeting & project
    try {
        $stm = $pdo->prepare("SELECT m.*, p.name AS project_name FROM project_meetings m JOIN projects p ON p.id=m.project_id WHERE m.id=? LIMIT 1");
        $stm->execute([$meeting_id]);
        $meeting = $stm->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $meeting = null;
    }
    if (!$meeting) {
        header('Content-Type: text/plain; charset=utf-8'); http_response_code(404);
        echo "Meeting not found"; exit;
    }

    // Load content_html
    $content_html = '';
    try {
        $stm2 = $pdo->prepare("SELECT content_html FROM project_meeting_details WHERE meeting_id=? LIMIT 1");
        $stm2->execute([$meeting_id]);
        $row2 = $stm2->fetch(PDO::FETCH_ASSOC);
        if ($row2 && isset($row2['content_html'])) $content_html = (string)$row2['content_html'];
    } catch (Throwable $e) {}

    // Ensure <img> do not overflow when opened in Word
    $content_html = preg_replace_callback('#<img\b([^>]*)>#i', function($m){
        $attrs = $m[1];
        if (stripos($attrs, 'style=') !== false) {
            $attrs = preg_replace('/style=("|\')(.*?)\1/si', function($mm){
                $q = $mm[1]; $v = $mm[2];
                if (stripos($v, 'max-width') === false) $v .= ' max-width:100%;';
                if (stripos($v, 'height:') === false)   $v .= ' height:auto;';
                return 'style=' . $q . trim($v) . $q;
            }, $attrs, 1);
        } else {
            $attrs .= ' style="max-width:100%; height:auto;"';
        }
        return '<img' . $attrs . '>';
    }, $content_html);

    if (!function_exists('mt_guess_mime')) {
        function mt_guess_mime($path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            return match ($ext) {
                'jpg','jpeg' => 'image/jpeg',
                'png'        => 'image/png',
                'gif'        => 'image/gif',
                'webp'       => 'image/webp',
                'bmp'        => 'image/bmp',
                'svg'        => 'image/svg+xml',
                default      => 'application/octet-stream',
            };
        }
    }
    if (!function_exists('mt_public_to_fs')) {
        function mt_public_to_fs($url) {
            $url = explode('?', $url, 2)[0];
            $url = explode('#', $url, 2)[0];
            if (strpos($url, '/uploads/') !== 0) return null;
            // Prefer DOCUMENT_ROOT if present
            if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                $p = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $url;
                if (is_file($p)) return $p;
            }
            // Fallback: relative to this file
            $base = realpath(__DIR__ . '/../../uploads');
            if (!$base) $base = __DIR__ . '/../../uploads';
            $rel  = substr($url, strlen('/uploads/'));
            return $base . '/' . str_replace(['..','\\'], ['','/'], $rel);
        }
    }

    // ---- BEGIN: CLEAN & INLINE PREP (remove fixed sizes, add fit-to-page styles & width attr) ----
if (!empty($content_html)) {
    // Remove width/height attributes on <img>
    $content_html = preg_replace(
        '/\s(width|height)\s*=\s*("[^"]*"|\'[^\']*\'|\S+)/i',
        '',
        $content_html
    );

    // Remove width/height in inline style of <img>
    $content_html = preg_replace_callback(
        '#<img\b[^>]*\bstyle\s*=\s*("|\')(.*?)\1[^>]*>#i',
        function($m){
            $q = $m[1];
            $style = $m[2];
            // strip any width/height from style
            $style = preg_replace('/\b(width|height)\s*:\s*[^;]+;?/i', '', $style);
            // collapse redundant semicolons/spaces
            $style = trim(preg_replace('/\s*;+\s*/', '; ', $style), " ;");
            // replace original style
            $tag = $m[0];
            return preg_replace('/\bstyle\s*=\s*("|\')(.*?)\1/i', 'style='.$q.$style.$q, $tag, 1);
        },
        $content_html
    );

    // Ensure every <img> fits within ~650px and has width attr so Word enforces it
    $content_html = preg_replace_callback(
        '#<img\b([^>]*)>#i',
        function($m){
            $attrs = $m[1];
            // remove old width/height attrs
            $attrs = preg_replace('/\s(width|height)\s*=\s*("[^"]*"|\'[^\']*\'|\S+)/i', '', $attrs);
            if (stripos($attrs, 'style=') !== false) {
                $attrs = preg_replace_callback('/style=("|\')(.*?)\1/si', function($mm){
                    $q = $mm[1];
                    $v = trim($mm[2]);
                    if ($v !== '' && substr($v, -1) !== ';') { $v .= ';'; }
                    $v .= ' max-width:650px; height:auto; display:block;';
                    return 'style=' . $q . trim($v) . $q;
                }, $attrs, 1);
            } else {
                $attrs .= ' style="max-width:650px; height:auto; display:block;"';
            }
            // add width attr to enforce in Word
            $attrs .= ' width="650"';
            return '<img' . $attrs . '>';
        },
        $content_html
    );
}
// ---- END: CLEAN & INLINE PREP ----

// Inline local images as data URIs
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content_html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    $imgs = $dom->getElementsByTagName('img');
    foreach ($imgs as $img) {
        $src = $img->getAttribute('src');
        if (!$src || stripos($src, 'data:') === 0) continue;
        if (stripos($src, 'http://') === 0 || stripos($src, 'https://') === 0) {
            // keep remote images as-is
            continue;
        }
        $fs = mt_public_to_fs($src);
        if ($fs && is_file($fs)) {
            $bin = @file_get_contents($fs);
            if ($bin !== false) {
                $mime = mt_guess_mime($fs);
                $img->setAttribute('src', 'data:' . $mime . ';base64,' . base64_encode($bin));
            }
        }
    }
    $content_html_inlined = $dom->saveHTML();
// ---- POST-INLINE FIT: ensure inlined <img> still constrained & width enforced ----
if (!empty($content_html_inlined)) {
    $content_html_inlined = preg_replace_callback(
        '#<img\b([^>]*)>#i',
        function($m){
            $attrs = $m[1];
            // remove old width/height attrs
            $attrs = preg_replace('/\s(width|height)\s*=\s*("[^"]*"|\'[^\']*\'|\S+)/i', '', $attrs);
            if (stripos($attrs, 'style=') !== false) {
                $attrs = preg_replace_callback('/style=("|\')(.*?)\1/si', function($mm){
                    $q = $mm[1];
                    $v = trim($mm[2]);
                    if ($v !== '' && substr($v, -1) !== ';') { $v .= ';'; }
                    $v .= ' max-width:650px; height:auto; display:block;';
                    return 'style=' . $q . trim($v) . $q;
                }, $attrs, 1);
            } else {
                $attrs .= ' style="max-width:650px; height:auto; display:block;"';
            }
            // add width attr to enforce in Word
            $attrs .= ' width="650"';
            return '<img' . $attrs . '>';
        },
        $content_html_inlined
    );
}
// ---- END POST-INLINE FIT ----
    libxml_clear_errors();

// === BEGIN: Build $attendees_table (copied logic from templete.php style) ===
$attendees_table = '';

// 1) Load attendees: join users để có first_name, last_name, email cho nội bộ
$att_list = [];
try {
    $q = $pdo->prepare("
        SELECT a.*,
               u.first_name, u.last_name, u.email AS user_email
        FROM project_meeting_attendees a
        LEFT JOIN users u ON u.id = a.user_id
        WHERE a.meeting_id=?
        ORDER BY a.id ASC
    ");
    $q->execute([$meeting_id]);
    $att_list = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $att_list = [];
}

// 2) Build rows: theo mẫu templete.php (#, Name, Email, Type)
$rows = [];
$idx  = 1;
foreach ($att_list as $a) {
    $isExt = (int)($a['is_external'] ?? 0) === 1;

    // Name
    if ($isExt) {
        $name = trim((string)($a['external_name'] ?? ''));
    } else {
        $fn = trim((string)($a['first_name'] ?? ''));
        $ln = trim((string)($a['last_name'] ?? ''));
        $name = trim($fn . ' ' . $ln);
        if ($name === '') {
            // fallback nếu không tách first/last trong DB
            $name = trim((string)($a['name'] ?? ''));
        }
    }
    // Email
    $email = $isExt
        ? trim((string)($a['external_email'] ?? ''))
        : trim((string)($a['user_email'] ?? ($a['email'] ?? '')));

    $type  = $isExt ? 'External' : 'Project Member';

    $name  = htmlspecialchars($name,  ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $type  = htmlspecialchars($type,  ENT_QUOTES, 'UTF-8');

    $rows[] = '<tr>'
            . '<td style="text-align:center;">' . ($idx++) . '</td>'
            . '<td>' . $name . '</td>'
            . '<td>' . $email . '</td>'
            . '<td style="text-align:center;">' . $type . '</td>'
            . '</tr>';
}

// 3) Compose table if any
if (!empty($rows)) {
    $attendees_table  = '<div class="section-title" style="margin:12pt 0 6pt 0;"><b>Attendees</b></div>';
    $attendees_table .= '<table class="attendees-table" style="width:100%;border-collapse:collapse;table-layout:fixed;">';
    $attendees_table .= '<thead><tr><th>#</th><th>Name</th><th>Email</th><th>Type</th></tr></thead>';
    $attendees_table .= '<tbody>' . implode('', $rows) . '</tbody></table>';
}
// === END: Build $attendees_table ===




// === BEGIN: Build Attendees & External Guests block ===
$internal = [];
$external = [];

// Try external participants table if exists
$external_guests = [];
try {
    $stmtExt = $pdo->query("SHOW TABLES LIKE 'project_meeting_external_participants'");
    if ($stmtExt && $stmtExt->fetch()) {
        $qExt = $pdo->prepare("SELECT name, role, organization, email, phone FROM project_meeting_external_participants WHERE meeting_id=? ORDER BY id ASC");
        $qExt->execute([$meeting_id]);
        $external_guests = $qExt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {}

if (!empty($external_guests)) {
    $external = $external_guests;
    $internal = isset($attendees) && is_array($attendees) ? $attendees : [];
} else {
    if (!isset($attendees) || !is_array($attendees)) { $attendees = []; }
    foreach ($attendees as $r) {
        $isExt = false;
        if (isset($r['is_external']) && (int)$r['is_external'] === 1) $isExt = true;
        if (isset($r['type']) && strtolower((string)$r['type']) === 'external') $isExt = true;
        if (isset($r['source']) && strtolower((string)$r['source']) === 'external') $isExt = true;
        if (!$isExt && empty($r['user_id'] ?? null) && empty($r['member_id'] ?? null)
            && ( !empty($r['email'] ?? '') || !empty($r['phone'] ?? '') )
        ) $isExt = true;

        if ($isExt) $external[] = $r; else $internal[] = $r;
    }
}

if (!function_exists('mt_format_person_line')) {
    function mt_format_person_line($r) {
        $parts = [];
        $name  = trim((string)($r['name'] ?? ''));
        $role  = trim((string)($r['role'] ?? ''));
        $org   = trim((string)($r['organization'] ?? ''));
        $email = trim((string)($r['email'] ?? ''));
        $phone = trim((string)($r['phone'] ?? ''));

        if ($name !== '')  $parts[] = htmlspecialchars($name,  ENT_QUOTES, 'UTF-8');
        if ($role !== '')  $parts[] = htmlspecialchars($role,  ENT_QUOTES, 'UTF-8');
        if ($org  !== '')  $parts[] = htmlspecialchars($org,   ENT_QUOTES, 'UTF-8');
        if ($email!== '')  $parts[] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        if ($phone!== '')  $parts[] = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');

        if (empty($parts)) return '';
        return '<li>'.implode(' – ', $parts).'</li>';
    }
}

$liInt = []; foreach ($internal as $r) { $ln = mt_format_person_line($r); if ($ln!=='') $liInt[] = $ln; }
$liExt = []; foreach ($external as $r) { $ln = mt_format_person_line($r); if ($ln!=='') $liExt[] = $ln; }

$attHtmlBlock = '';
if (!empty($liInt) || !empty($liExt)) {
    $attHtmlBlock .= '<div class="mt-attendees-block" style="margin:12pt 0 10pt 0;">';
    if (!empty($liInt)) {
        $attHtmlBlock .= '<div style="font-weight:bold;margin:4pt 0 2pt 0;">Thành viên tham gia:</div>'
                       . '<ul style="margin:0 0 6pt 16pt; padding:0;">'.implode('', $liInt).'</ul>';
    }
    if (!empty($liExt)) {
        $attHtmlBlock .= '<div style="font-weight:bold;margin:4pt 0 2pt 0;">Khách mời bên ngoài:</div>'
                       . '<ul style="margin:0 0 0 16pt; padding:0;">'.implode('', $liExt).'</ul>';
    }
    $attHtmlBlock .= '</div>';
}
// === END: Build Attendees & External Guests block ===

    // Build minimal Word-friendly HTML
    $title = trim((string)($meeting['title'] ?? 'Meeting'));
    if ($title === '') $title = 'Meeting';
    $safeTitle = preg_replace('/[^\p{L}\p{N}\-_. ]/u', '_', $title);

$doc = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'
     . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
     . '<style>
          body { font-family:"Times New Roman",serif; font-size:12pt; }
          img { max-width:100mm; height:auto; display:block; }
          table { border-collapse:collapse; }
          table, td, th { border:1px solid #333; }
          td, th { padding:4px 6px; }
        
img{max-width:170mm;height:auto;display:block;}</style>'
     . '</head><body>';

    $doc .= '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
    if (!empty($meeting['project_name'])) {
        $doc .= '<div><b>Project:</b> ' . htmlspecialchars($meeting['project_name'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($meeting['start_time'])) {
        $doc .= '<div><b>Start time:</b> ' . htmlspecialchars($meeting['start_time'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($meeting['location'])) {
        $doc .= '<div><b>Location:</b> ' . htmlspecialchars($meeting['location'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($meeting['online_link'])) {
        $ol = htmlspecialchars($meeting['online_link'], ENT_QUOTES, 'UTF-8');
        $doc .= '<div><b>Online link:</b> <a href="'.$ol.'">'.$ol.'</a></div>';
    }
    if (!empty($meeting['short_desc'])) {
    $doc .= '<div><b>Short description:</b> '
          . htmlspecialchars($meeting['short_desc'], ENT_QUOTES, 'UTF-8')
          . '</div>';
}

// luôn luôn chèn attendees nếu có
if (!empty($attendees_table)) {
    $doc .= $attendees_table;
}

// rồi mới tới phần nội dung chi tiết đã inline ảnh
$doc .= $content_html_inlined;
    $doc .= '</body></html>';

    // Output as .doc
    $fname = $safeTitle . '_' . date('Y-m-d_His') . '.doc';
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fname.'"');
    echo $doc;
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
