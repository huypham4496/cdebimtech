<?php
// partials/file_preview.php — Excel multi-sheet FIX v3: use Html::setSheetIndex($i) safely; avoid cross-sheet errors
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// ---- Locate project root + config ----
$__dir = __DIR__;
$__root = null;
$__config = null;
for ($i = 0; $i < 8; $i++) {
    if (is_file($__dir . DIRECTORY_SEPARATOR . 'config.php')) { $__root = $__dir; $__config = $__dir . DIRECTORY_SEPARATOR . 'config.php'; break; }
    if (is_file($__dir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php')) { $__root = $__dir; $__config = $__dir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php'; break; }
    $__dir = dirname($__dir);
}
if (!$__config) { http_response_code(500); header('Content-Type: text/plain'); echo "Cannot locate config.php\n"; exit; }
require_once $__config;

// ---- Autoloaders ----
$__autoloads = [
    $__root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    $__root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    $__root . DIRECTORY_SEPARATOR . 'phpspreadsheet' . DIRECTORY_SEPARATOR . 'autoload.php', // user-provided
    $__root . DIRECTORY_SEPARATOR . 'phpword' . DIRECTORY_SEPARATOR . 'autoload.php',
];
foreach ($__autoloads as $__auto) { if (is_file($__auto)) { require_once $__auto; } }

// ---- Ensure $pdo ----
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Database is not configured.";
        exit;
    }
}

// ---- Helpers ----
function json_resp($ok, $data=[], $code=200){
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>$ok] + $data);
    exit;
}
function normalize_path($p){
    if (!$p) return $p;
    $isAbs = (preg_match('/^[A-Za-z]:\\\\\\\\|^\\\\\\\\\\\\\\\\|^\\//', $p) === 1);
    if ($isAbs) return $p;
    global $__root;
    $p = str_replace(['\\\\','/'], DIRECTORY_SEPARATOR, $p);
    $root = rtrim(str_replace(['\\\\','/'], DIRECTORY_SEPARATOR, $__root), DIRECTORY_SEPARATOR);
    return $root . DIRECTORY_SEPARATOR . ltrim($p, DIRECTORY_SEPARATOR);
}
function guess_mime($ext){
    $ext = strtolower($ext);
    $map = [
        'pdf'=>'application/pdf',
        'doc'=>'application/msword',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'=>'application/vnd.ms-excel',
        'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv'=>'text/csv',
        'txt'=>'text/plain',
        'png'=>'image/png',
        'jpg'=>'image/jpeg',
        'jpeg'=>'image/jpeg',
        'gif'=>'image/gif',
        'bmp'=>'image/bmp',
        'svg'=>'image/svg+xml',
        'json'=>'application/json'
    ];
    return $map[$ext] ?? 'application/octet-stream';
}
function resolve_user_id_from_session(): int {
    $paths = [
        ['user_id'], ['auth','user_id'], ['auth','user','id'], ['user','id'], ['login','id'],
    ];
    foreach ($paths as $p) {
        $ref = $_SESSION; $ok = true;
        foreach ($p as $k) { if (!isset($ref[$k])) { $ok=false; break; } $ref = $ref[$k]; }
        if ($ok && is_scalar($ref)) return (int)$ref;
    }
    return 0;
}
function is_project_member_safe(PDO $pdo, $user_id, $file_id): bool {
    $st = $pdo->prepare("SELECT project_id FROM project_files WHERE id=? LIMIT 1");
    $st->execute([(int)$file_id]);
    $pid = (int)($st->fetchColumn() ?: 0);
    if ($pid <= 0) return false;
    if (function_exists('is_project_member')) return (bool)is_project_member($pdo, $pid, (int)$user_id);
    if (function_exists('can_view_project')) return (bool)can_view_project($pid, (int)$user_id);
    return true; // dev/local fallback
}

// ---- Inputs ----
$mode = $_GET['mode'] ?? 'view';
$force = $_GET['force'] ?? ''; // 'web' or 'local'
$file_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sheetParam = isset($_GET['sheet']) ? (int)$_GET['sheet'] : 0;
if ($file_id <= 0) json_resp(false, ['error'=>'Missing id'], 400);

// ---- Permissions ----
$user_id = resolve_user_id_from_session();
if (!is_project_member_safe($pdo, (int)$user_id, $file_id)) json_resp(false, ['error'=>'Forbidden'], 403);

// ---- Load file + version ----
$st = $pdo->prepare("SELECT id, filename FROM project_files WHERE id=? LIMIT 1");
$st->execute([$file_id]);
$file = $st->fetch(PDO::FETCH_ASSOC);
if (!$file) json_resp(false, ['error'=>'File not found'], 404);
$filename = (string)$file['filename'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

$st = $pdo->prepare("SELECT MAX(version) FROM file_versions WHERE file_id=?");
$st->execute([$file_id]);
$v = (int)($st->fetchColumn() ?: 0);
if ($v === 0) json_resp(false, ['error'=>'No version available'], 404);
$st = $pdo->prepare("SELECT version, storage_path, size_bytes, uploaded_by, created_at FROM file_versions WHERE file_id=? AND version=?");
$st->execute([$file_id, $v]);
$vi = $st->fetch(PDO::FETCH_ASSOC);
if (!$vi) json_resp(false, ['error'=>'No version row'], 404);
$abs = normalize_path($vi['storage_path'] ?? '');
if (!is_file($abs)) json_resp(false, ['error'=>'Storage missing'], 404);

// ---- Raw streaming ----
if ($mode === 'raw') {
    $mime = guess_mime($ext);
    @ob_end_clean();
    header('Content-Type: '.$mime);
    header('Content-Disposition: inline; filename="'.rawurlencode($filename).'"');
    header('Content-Length: ' . filesize($abs));
    readfile($abs);
    exit;
}

// ---- URLs ----
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/partials/file_preview.php'), '/\\');
$rawUrl = $scheme . '://' . $host . $scriptDir . '/file_preview.php?mode=raw&id=' . $file_id;

// ---- Offline converters ----
function excel_multi_offline($abs, $file_id){
    if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) return [false, [], [], 'PhpSpreadsheet not installed'];
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($abs);
        // Clear caches to avoid stale data
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Calculation\\Calculation')) {
            \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($spreadsheet)->clearCalculationCache();
        }
        $spreadsheet->garbageCollect();

        $sheetCount = $spreadsheet->getSheetCount();
        $titles = []; $files = [];
        global $__root;

        for ($i = 0; $i < $sheetCount; $i++) {
            $titles[] = $spreadsheet->getSheet($i)->getTitle();

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Html($spreadsheet);
            if (method_exists($writer, 'setSheetIndex')) {
                $writer->setSheetIndex($i); // render that sheet only
            } else {
                $spreadsheet->setActiveSheetIndex($i);
            }
            if (method_exists($writer, 'setEmbedImages')) $writer->setEmbedImages(true);
            if (method_exists($writer, 'setPreCalculateFormulas')) $writer->setPreCalculateFormulas(true);

            $rel = '/uploads/tmp/preview_excel_' . $file_id . '_sheet' . $i . '.html';
            $out = $__root . DIRECTORY_SEPARATOR . ltrim($rel, '/\\');
            $dir = dirname($out);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $writer->save($out);
            $files[] = $rel;
            unset($writer);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [true, $files, $titles, null];
    } catch (\Throwable $e) {
        return [false, [], [], $e->getMessage()];
    }
}
function try_word_offline($abs, $file_id){
    if (!class_exists('\\PhpOffice\\PhpWord\\IOFactory')) return [false, null, 'PhpWord not installed'];
    try {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($abs);
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        global $__root;
        $rel = '/uploads/tmp/preview_word_' . $file_id . '.html';
        $out = $__root . DIRECTORY_SEPARATOR . ltrim($rel, '/\\');
        $dir = dirname($out);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $writer->save($out);
        return [true, $rel, null];
    } catch (\Throwable $e) { return [false, null, $e->getMessage()]; }
}

$libs_present = class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory') || class_exists('\\PhpOffice\\PhpWord\\IOFactory');
$prefer_offline = ($force === 'local') || $libs_present || in_array($host, ['localhost','127.0.0.1','::1']);

// ---- HTML Viewer ----
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Xem file - <?php echo htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?></title>
<style>
  html, body { height:100%; margin:0; background:#0b0d10; color:#e5e7eb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; }
  .topbar { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#111827; border-bottom:1px solid #1f2937; }
  .topbar .name { font-weight:600; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
  .btn { display:inline-block; padding:7px 12px; border-radius:8px; background:#1f2937; color:#e5e7eb; text-decoration:none; }
  .btn:hover { background:#374151; }
  iframe, .viewport { width:100%; height:calc(100dvh - 88px); border:0; background:#111827; }
  .empty { padding:40px; text-align:center; color:#9ca3af; }
  .row { display:flex; align-items:center; gap:10px; justify-content:center; margin-top:8px; }
  .tabs { display:flex; gap:8px; padding:8px 12px; background:#0f172a; border-bottom:1px solid #1f2937; position:sticky; top:48px; z-index:1; }
  .tab { padding:6px 10px; border-radius:8px; background:#1f2937; cursor:pointer; user-select:none; }
  .tab.active { background:#2563eb; color:white; }
</style>
<script>
  function switchSheet(src, index){
    const iframe = document.getElementById('sheet-frame');
    if (iframe) iframe.src = src + '?t=' + Date.now(); // bust cache per switch
    document.querySelectorAll('.tab').forEach((el, i)=>{
      el.classList.toggle('active', i===index);
    });
  }
</script>
</head>
<body>
  <div class="topbar">
    <div class="name"><?php echo htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?></div>
    <div style="flex:1"></div>
    <a class="btn" href="?mode=raw&id=<?php echo $file_id; ?>&dl=1" download>Tải xuống</a>
  </div>
<?php
if ($ext === 'pdf') {
    $src = '?mode=raw&id='.$file_id;
    echo '<iframe class="viewport" src="'.$src.'"></iframe>';
} elseif (in_array($ext, ['xls','xlsx'])) {
    $shown = false;
    if ($prefer_offline && class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
        list($ok, $files, $titles, $err) = excel_multi_offline($abs, $file_id);
        if ($ok && count($files) > 0) {
            $defaultIndex = ($sheetParam >=0 && $sheetParam < count($files)) ? $sheetParam : 0;
            echo '<div class="tabs">';
            foreach ($files as $i => $rel) {
                $title = $titles[$i] ?? ('Sheet '.($i+1));
                $active = ($i === $defaultIndex) ? ' active' : '';
                $onclick = "switchSheet('".$rel."', ".$i.")";
                echo '<div class="tab'.$active.'" onclick="'.$onclick.'">'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</div>';
            }
            echo '</div>';
            $first = $files[$defaultIndex];
            echo '<iframe id="sheet-frame" class="viewport" src="'.$first.'?t='.time().'"></iframe>';
            $shown = true;
        } else if (!$ok) {
            echo '<div class="empty">Excel offline lỗi: '.htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8').'</div>';
        }
    }
    if (!$shown && $force !== 'local') {
        $office = 'https://view.officeapps.live.com/op/view.aspx?src=' . rawurlencode($rawUrl);
        echo '<iframe class="viewport" src="'.$office.'"></iframe>';
        echo '<div class="row"><a class="btn" href="?mode=view&id='.$file_id.'&force=local">Dùng chế độ offline (PhpSpreadsheet đã cài)</a></div>';
        $shown = true;
    }
    if (!$shown) {
        echo '<div class="empty">Không thể xem Excel. Vui lòng tải xuống.<br/>Hãy kiểm tra autoload tại <code>phpspreadsheet/autoload.php</code></div>';
    }
} elseif (in_array($ext, ['doc','docx'])) {
    $shown = false;
    if ($prefer_offline && class_exists('\\PhpOffice\\PhpWord\\IOFactory')) {
        list($ok, $rel, $err) = try_word_offline($abs, $file_id);
        if ($ok) { echo '<iframe class="viewport" src="'.$rel.'"></iframe>'; $shown = true; }
    }
    if (!$shown && $force !== 'local') {
        $office = 'https://view.officeapps.live.com/op/view.aspx?src=' . rawurlencode($rawUrl);
        echo '<iframe class="viewport" src="'.$office.'"></iframe>';
        echo '<div class="row"><a class="btn" href="?mode=view&id='.$file_id.'&force=local">Dùng chế độ offline (nếu đã cài PhpWord)</a></div>';
        $shown = true;
    }
    if (!$shown) {
        echo '<div class="empty">Không thể xem Word. Vui lòng tải xuống.<br/>Bạn có thể thêm <code>phpword/autoload.php</code> để xem offline.</div>';
    }
} elseif (in_array($ext, ['png','jpg','jpeg','gif','bmp','svg'])) {
    $src = '?mode=raw&id='.$file_id;
    echo '<div class="viewport" style="display:flex;align-items:center;justify-content:center;background:#0b0d10">';
    echo '<img src="'.$src.'" alt="" style="max-width:96%;max-height:96%;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.35)" />';
    echo '</div>';
} elseif (in_array($ext, ['txt','csv','json'])) {
    $src = '?mode=raw&id='.$file_id;
    echo '<iframe class="viewport" src="'.$src.'"></iframe>';
} else {
    echo '<div class="empty">Chưa hỗ trợ xem trực tiếp định dạng này. Vui lòng tải xuống để mở.</div>';
}
?>
</body>
</html>
