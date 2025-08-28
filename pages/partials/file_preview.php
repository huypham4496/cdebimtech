<?php
// partials/file_preview.php — OFFLINE-ONLY viewer (PhpSpreadsheet/PhpWord).
// Update: Word paper size now follows the document's page size & orientation (not fixed A4).
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

// ---- Autoloaders (Composer -> includes/vendor -> standalone folders) ----
$__autoloads = [
    $__root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    $__root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    $__root . DIRECTORY_SEPARATOR . 'phpspreadsheet' . DIRECTORY_SEPARATOR . 'autoload.php', // for Excel
    $__root . DIRECTORY_SEPARATOR . 'phpword' . DIRECTORY_SEPARATOR . 'autoload.php',        // for Word
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

// LibreOffice finder (optional for .doc conversion/repair)
function find_soffice_binary(){
    $candidates = [
        'soffice',
        'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        'C:\\Program Files\\OpenOffice 4\\program\\soffice.exe',
        '/usr/bin/soffice',
        '/usr/local/bin/soffice',
        '/Applications/LibreOffice.app/Contents/MacOS/soffice',
    ];
    foreach ($candidates as $p) {
        if ($p === 'soffice') return $p;
        if (is_file($p)) return $p;
    }
    return null;
}

// ---- Inputs ----
$mode = $_GET['mode'] ?? 'view';
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
    $dl = isset($_GET['dl']) ? (int)$_GET['dl'] : 0;
    $disp = $dl ? 'attachment' : 'inline';
    header('Content-Disposition: '.$disp.'; filename="'.rawurlencode($filename).'"');
    header('Content-Length: ' . filesize($abs));
    readfile($abs);
    exit;
}

// ---- Offline converters ----
function excel_multi_offline($abs, $file_id){
    if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) return [false, [], [], 'PhpSpreadsheet not installed'];
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($abs);
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
                $writer->setSheetIndex($i);
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
    // Robust Word offline: supports .docx natively; .doc via MsDoc or soffice conversion
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    global $__root;
    $tmpDir = $__root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tmp';
    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);
    $targetHtmlRel = '/uploads/tmp/preview_word_' . $file_id . '.html';
    $targetHtmlAbs = $__root . DIRECTORY_SEPARATOR . ltrim($targetHtmlRel, '/\\');

    if (!class_exists('\\PhpOffice\\PhpWord\\IOFactory')) {
        return [false, null, 'PhpWord not installed'];
    }
    try {
        $phpWord = null;
        if ($ext === 'docx') {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($abs);
        } elseif ($ext === 'doc') {
            if (class_exists('\\PhpOffice\\PhpWord\\Reader\\MsDoc')) {
                $reader = new \PhpOffice\PhpWord\Reader\MsDoc();
                $phpWord = $reader->load($abs);
            } else {
                $soffice = find_soffice_binary();
                if ($soffice) {
                    $outDocxAbs = $tmpDir . DIRECTORY_SEPARATOR . 'converted_' . $file_id . '.docx';
                    @unlink($outDocxAbs);
                    $cmd = escapeshellarg($soffice) . ' --headless --convert-to docx --outdir ' . escapeshellarg($tmpDir) . ' ' . escapeshellarg($abs) . ' 2>&1';
                    $output = shell_exec($cmd);
                    if (!is_file($outDocxAbs)) {
                        $outPdfAbs = $tmpDir . DIRECTORY_SEPARATOR . 'converted_' . $file_id . '.pdf';
                        @unlink($outPdfAbs);
                        $cmd2 = escapeshellarg($soffice) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($tmpDir) . ' ' . escapeshellarg($abs) . ' 2>&1';
                        $output2 = shell_exec($cmd2);
                        if (is_file($outPdfAbs)) {
                            $rel = '/uploads/tmp/converted_' . $file_id . '.pdf';
                            return [true, $rel, null, 'pdf'];
                        }
                        return [false, null, 'DOC->DOCX/PDF conversion failed via soffice.'];
                    }
                    $phpWord = \PhpOffice\PhpWord\IOFactory::load($outDocxAbs);
                } else {
                    return [false, null, 'DOC not supported without MsDoc/LibreOffice.'];
                }
            }
        } else {
            return [false, null, 'Unsupported word extension: ' . $ext];
        }

        // Detect first section's page size (twip) -> mm
        $pageWmm = null; $pageHmm = null; $orientation = null;
        try {
            $sections = method_exists($phpWord, 'getSections') ? $phpWord->getSections() : [];
            if ($sections && isset($sections[0])) {
                $style = $sections[0]->getStyle();
                if ($style && method_exists($style, 'getPageSizeW')) {
                    $twW = (float)$style->getPageSizeW();
                    $twH = (float)$style->getPageSizeH();
                    $orientation = method_exists($style, 'getOrientation') ? $style->getOrientation() : null;
                    if (class_exists('\\PhpOffice\\PhpWord\\Shared\\Converter')) {
                        $pageWmm = \PhpOffice\PhpWord\Shared\Converter::twipToMillimeter($twW);
                        $pageHmm = \PhpOffice\PhpWord\Shared\Converter::twipToMillimeter($twH);
                    } else {
                        // 1 twip = 1/1440 inch; 1 inch = 25.4 mm
                        $pageWmm = round($twW * 25.4 / 1440, 2);
                        $pageHmm = round($twH * 25.4 / 1440, 2);
                    }
                }
            }
        } catch (\Throwable $eMeta) { /* ignore meta errors */ }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $writer->save($targetHtmlAbs);
        // return with meta
        return [true, $targetHtmlRel, null, 'html', ['w_mm'=>$pageWmm, 'h_mm'=>$pageHmm, 'orientation'=>$orientation]];
    } catch (\Throwable $e) {
        if ($ext === 'docx' && strpos((string)$e->getMessage(), 'error code: 19') !== false) {
            $soffice = find_soffice_binary();
            if ($soffice) {
                $outDocxAbs = $tmpDir . DIRECTORY_SEPARATOR . 'repair_' . $file_id . '.docx';
                @unlink($outDocxAbs);
                $cmd = escapeshellarg($soffice) . ' --headless --convert-to docx --outdir ' . escapeshellarg($tmpDir) . ' ' . escapeshellarg($abs) . ' 2>&1';
                $output = shell_exec($cmd);
                if (is_file($outDocxAbs)) {
                    try {
                        $phpWord2 = \PhpOffice\PhpWord\IOFactory::load($outDocxAbs);
                        $writer2 = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord2, 'HTML');
                        $writer2->save($targetHtmlAbs);
                        return [true, $targetHtmlRel, null];
                    } catch (\Throwable $e2) {
                        return [false, null, 'After repair: ' . $e2->getMessage()];
                    }
                }
            }
        }
        return [false, null, $e->getMessage()];
    }
}

// ---- HTML Viewer (offline only) ----
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
  /* Word container (dynamic paper width via inline style) */
  .paper { margin: 12px auto; background:#ffffff; border:1px solid #e5e7eb; box-shadow: 0 8px 24px rgba(0,0,0,.06); width: var(--paper-width, 210mm); }
  .paper-frame { width:100%; height:calc(100dvh - 96px); border:0; background:#ffffff; display:block; }
  @media (max-width: 840px){
    .paper { width: 100%; border:none; box-shadow:none; }
    .paper-frame { height:calc(100dvh - 88px); }
  }
</style>
<script>
  function switchSheet(src, index){
    const iframe = document.getElementById('sheet-frame');
    if (iframe) iframe.src = src + '?t=' + Date.now();
    document.querySelectorAll('.tab').forEach((el, i)=>{
      el.classList.toggle('active', i===index);
    });
  }
</script>
<?php if (in_array($ext, ['doc','docx'])): ?>
<style>
  /* White theme for Word */
  html, body { background:#ffffff !important; color:#111827 !important; }
  .topbar { background:#ffffff !important; border-bottom:1px solid #e5e7eb !important; }
  .btn { background:#f3f4f6 !important; color:#111827 !important; }
  .btn:hover { background:#e5e7eb !important; }
  .viewport { background:#ffffff !important; }
</style>
<?php endif; ?>
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
    if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
        echo '<div class="empty">Thiếu PhpSpreadsheet. Hãy đặt <code>/phpspreadsheet/autoload.php</code> (ngang hàng thư mục pages) hoặc cài composer.</div>';
    } else {
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
        } else {
            echo '<div class="empty">Không thể xem Excel offline: '.htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8').'</div>';
        }
    }
} elseif (in_array($ext, ['doc','docx'])) {
    if (!class_exists('\\PhpOffice\\PhpWord\\IOFactory')) {
        echo '<div class="empty">Thiếu PhpWord. Hãy đặt <code>/phpword/autoload.php</code> (ngang hàng thư mục pages) hoặc cài composer.</div>';
    } else {
        $res = try_word_offline($abs, $file_id);
        if (is_array($res) && !empty($res[0])) {
            $rel = (string)$res[1];
            $meta = isset($res[4]) && is_array($res[4]) ? $res[4] : null;
            $wmm = ($meta && isset($meta['w_mm']) && $meta['w_mm']) ? (float)$meta['w_mm'] : null;
            $paperStyle = $wmm ? ' style="--paper-width: '.htmlspecialchars((string)$wmm, ENT_QUOTES, 'UTF-8').'mm; width: '.htmlspecialchars((string)$wmm, ENT_QUOTES, 'UTF-8').'mm;"' : '';
            if (isset($res[3]) && $res[3] === 'pdf') {
                echo '<div class="paper"'.$paperStyle.'><iframe class="paper-frame" src="'.htmlspecialchars($rel, ENT_QUOTES, 'UTF-8').'"></iframe></div>';
            } else {
                echo '<div class="paper"'.$paperStyle.'><iframe class="paper-frame" src="'.htmlspecialchars($rel, ENT_QUOTES, 'UTF-8').'"></iframe></div>';
            }
        } else {
            $errMsg = is_array($res) ? (string)$res[2] : 'Unknown error';
            echo '<div class="empty">Không thể chuyển đổi DOC/DOCX offline: ' . htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') . '<br/>Đảm bảo đã cài PhpWord; với .doc có thể cần LibreOffice (soffice).</div>';
        }
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
    $src = '?mode=raw&id='.$file_id;
    echo '<iframe class="viewport" src="'.$src.'"></iframe>';
}
?>
</body>
</html>
