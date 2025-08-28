<?php
/**
 * pages/partials/file_preview.php
 * DWG preview = self-hosted DXF viewer (auto-generate DXF if missing).
 * - Office (doc/xls/ppt): Microsoft Office Online Viewer
 * - DWG: try to find DXF; if absent, auto-convert via dwg2dxf (LibreDWG) then view
 * - PDF / Images / Video / Audio / Text: native tags
 *
 * Requirements for auto-convert:
 *   - Install LibreDWG CLI `dwg2dxf` on your server
 *   - Set one of:
 *       putenv('DWG2DXF_BIN=/usr/bin/dwg2dxf');  // Linux
 *       putenv('DWG2DXF_BIN="C:\\Program Files\\LibreDWG\\dwg2dxf.exe"'); // Windows
 *     or define('DWG2DXF_BIN', '...') in config.php
 */

@header_remove('X-Powered-By');
mb_internal_encoding('UTF-8');
set_time_limit(120);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function scheme(){
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) return $_SERVER['HTTP_X_FORWARDED_PROTO'];
  return 'http';
}
function host(){ return $_SERVER['HTTP_HOST'] ?? 'localhost'; }
function baseurl(){ return scheme() . '://' . host(); }
function norm_web_path($p){ $p = '/' . ltrim((string)$p, '/'); return strtok($p, '?#'); }

$FS_ROOT = realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2);
// Try config.php in both common locations
if (file_exists($FS_ROOT . '/config.php')) {
  require_once $FS_ROOT . '/config.php';
} elseif (file_exists($FS_ROOT . '/includes/config.php')) {
  require_once $FS_ROOT . '/includes/config.php';
}

// Build $pdo if config didn't provide it
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    try { $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch (Throwable $e) { $pdo = null; }
  } else {
    $pdo = null;
  }
}

// ---------------- Resolve file ----------------
$webPath = null;
$fileId  = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (isset($_GET['file']) && $_GET['file'] !== '') {
  $webPath = norm_web_path($_GET['file']);
} elseif ($fileId > 0 && $pdo instanceof PDO) {
  try {
    $st = $pdo->prepare("SELECT filename, current_version FROM project_files WHERE id=? AND is_deleted=0");
    $st->execute([$fileId]);
    $fileRow = $st->fetch(PDO::FETCH_ASSOC);
    if ($fileRow) {
      $filename_db = (string)$fileRow['filename'];
      $cur = (int)($fileRow['current_version'] ?? 0);
      if ($cur <= 0) {
        $st2 = $pdo->prepare("SELECT MAX(version) FROM file_versions WHERE file_id=?");
        $st2->execute([$fileId]);
        $cur = (int)($st2->fetchColumn() ?: 0);
      }
      if ($cur > 0) {
        $st3 = $pdo->prepare("SELECT storage_path FROM file_versions WHERE file_id=? AND version=?");
        $st3->execute([$fileId, $cur]);
        $vr = $st3->fetch(PDO::FETCH_ASSOC);
        if ($vr && !empty($vr['storage_path'])) {
          $webPath = '/' . ltrim($vr['storage_path'], '/\\');
        }
      }
    }
  } catch (Throwable $e) {
    // ignore
  }
}

$filename = $webPath ? basename($webPath) : ($fileId ? ('#'.$fileId) : 'Unknown');
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$absUrl = $webPath ? (baseurl() . $webPath) : '';
$absFs  = $webPath ? ($FS_ROOT . '/' . ltrim($webPath, '/')) : '';

// Helpers
function is_image_ext($e){ return in_array($e, ['png','jpg','jpeg','gif','webp','bmp','svg']); }
function is_video_ext($e){ return in_array($e, ['mp4','webm','ogv','mov']); }
function is_audio_ext($e){ return in_array($e, ['mp3','ogg','wav','m4a','aac']); }
function is_text_ext($e){ return in_array($e, ['txt','log','csv','json','xml','yml','yaml','md','html','css','js','php']); }

// Ensure preview dir
$PREVIEW_DIR_WEB = '/uploads/_previews';
$PREVIEW_DIR_FS  = $FS_ROOT . $PREVIEW_DIR_WEB;
if (!is_dir($PREVIEW_DIR_FS)) {
  @mkdir($PREVIEW_DIR_FS, 0775, true);
  @mkdir($PREVIEW_DIR_FS . '/_logs', 0775, true);
}

// Auto-convert function (dwg -> dxf)
function auto_convert_dwg_to_dxf($dwgFs, $dxfFs, &$logMsg){
  $logMsg = '';
  if (!is_file($dwgFs)) { $logMsg = "DWG not found: $dwgFs"; return false; }
  $bin = null;
  if (defined('DWG2DXF_BIN') && DWG2DXF_BIN) $bin = DWG2DXF_BIN;
  if (!$bin) $bin = getenv('DWG2DXF_BIN') ?: null;
  if (!$bin) $bin = 'dwg2dxf'; // hope PATH has it

  // Ensure target dir
  $dir = dirname($dxfFs);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);

  // Windows: escapeshellarg uses double-quotes; OK.
  $cmd = $bin . ' ' . escapeshellarg($dwgFs) . ' ' . escapeshellarg($dxfFs);
  $out = []; $code = 127;
  if (function_exists('exec')) {
    @exec($cmd . ' 2>&1', $out, $code);
  } elseif (function_exists('shell_exec')) {
    $res = @shell_exec($cmd . ' 2>&1');
    $out = explode("\n", (string)$res);
    $code = is_file($dxfFs) ? 0 : 1;
  } else {
    $logMsg = "exec/shell_exec disabled in PHP. Can't run dwg2dxf.";
    return false;
  }
  $logMsg = "CMD: $cmd\n" . implode("\n", $out);
  clearstatcache(true, $dxfFs);
  return ($code === 0 && is_file($dxfFs) && filesize($dxfFs) > 0);
}

// DXF preview resolution (and auto-generate if missing)
$dxfWeb = '';
$autoLog = '';
if ($ext === 'dwg' && $webPath && $absFs) {
  $targetWeb = '';
  if ($fileId > 0) {
    $targetWeb = $PREVIEW_DIR_WEB . '/' . $fileId . '.dxf';
  } else {
    // same folder, same name .dxf
    $targetWeb = preg_replace('/\.dwg$/i', '.dxf', $webPath);
  }
  $targetFs = $FS_ROOT . $targetWeb;

  // If not exist, try to auto-convert
  if (!is_file($targetFs)) {
    $ok = auto_convert_dwg_to_dxf($absFs, $targetFs, $autoLog);
    // Write log
    $logFile = $PREVIEW_DIR_FS . '/_logs/' . ($fileId ?: ('manual_' . md5($webPath))) . '.log';
    @file_put_contents($logFile, '['.date('c')."]\n".$autoLog."\n\n", FILE_APPEND);
  }

  if (is_file($targetFs)) $dxfWeb = $targetWeb;
}

?><!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Preview – <?php echo h($filename); ?></title>
  <link rel="stylesheet" href="/assets/css/file_preview.css">
  <style>
    #dxf-view { width:100%; height:calc(100vh - 160px); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Courier New", monospace; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div class="file"><?php echo h($filename); ?></div>
      <div class="controls">
        <?php if ($webPath): ?>
          <a class="btn" href="<?php echo h($webPath); ?>" download>Download</a>
        <?php elseif ($fileId): ?>
          <a class="btn" href="<?php echo h('/pages/partials/project_tab_files.php?action=download_one&file_id='.$fileId); ?>" target="_blank" rel="noopener">Download</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="viewer">
<?php if (!$webPath): ?>
      <div class="note muted">
        Missing file path.<br>
        Hãy mở từ bảng Files (truyền <code>id</code>) hoặc gọi: <code>file_preview.php?file=/uploads/PRJxxxx/yourfile.ext</code>.
        <?php if ($fileId): ?> (Không tìm thấy đường dẫn web cho id=<?php echo h($fileId); ?>)<?php endif; ?>
      </div>
<?php else: ?>

<?php if (in_array($ext, ['doc','docx','xls','xlsx','ppt','pptx'])): ?>
      <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=<?php echo urlencode($absUrl); ?>" allowfullscreen loading="lazy"></iframe>
      <div class="note muted">Đang xem qua Microsoft Office Online Viewer.</div>

<?php elseif ($ext === 'dwg'): ?>

  <?php if ($dxfWeb): ?>
      <div id="dxf-view"></div>
      <div class="note muted">DXF preview (tự host). Nguồn: <code><?php echo h($dxfWeb); ?></code></div>
      <script>window.CDE_DXF_SRC = <?php echo json_encode($dxfWeb, JSON_UNESCAPED_SLASHES); ?>;</script>
      <!-- libs: three.js + OrbitControls + dxf-parser + three-dxf -->
      <script src="https://unpkg.com/three@0.152.2/build/three.min.js"></script>
      <script src="https://unpkg.com/three@0.152.2/examples/js/controls/OrbitControls.js"></script>
      <script src="https://unpkg.com/dxf-parser@1.1.4/dist/dxf-parser.min.js"></script>
      <script src="https://unpkg.com/three-dxf@1.1.1/build/three-dxf.min.js"></script>
      <script src="/assets/js/dxf_viewer.js?v=<?php echo time(); ?>"></script>

  <?php else: ?>
      <div class="note muted">
        Không tạo được DXF preview tự động cho DWG này.<br>
        Kiểm tra log: <code><?php echo h($PREVIEW_DIR_WEB . '/_logs/' . ($fileId ?: ('manual_' . md5($webPath))) . '.log'); ?></code><br>
        Gợi ý: cài LibreDWG và cấu hình biến môi trường/constant <code>DWG2DXF_BIN</code> (ví dụ <code>/usr/bin/dwg2dxf</code> hoặc <code>C:\Program Files\LibreDWG\dwg2dxf.exe</code>).
      </div>
      <?php if (!empty($autoLog)): ?>
      <pre class="code mono"><?php echo h($autoLog); ?></pre>
      <?php endif; ?>
  <?php endif; ?>

<?php elseif ($ext === 'pdf'): ?>
      <embed src="<?php echo h($webPath); ?>" type="application/pdf">
      <div class="note muted">PDF preview.</div>

<?php elseif (is_image_ext($ext)): ?>
      <img class="img-preview" src="<?php echo h($webPath); ?>" alt="<?php echo h($filename); ?>">

<?php elseif (is_video_ext($ext)): ?>
      <video src="<?php echo h($webPath); ?>" controls></video>

<?php elseif (is_audio_ext($ext)): ?>
      <audio src="<?php echo h($webPath); ?>" controls></audio>

<?php elseif (is_text_ext($ext)): ?>
<?php
      $txt = '';
      if ($absFs && is_file($absFs) && is_readable($absFs)) $txt = file_get_contents($absFs);
      echo '<pre class="code">'.h($txt ?: 'Không đọc được nội dung văn bản.').'</pre>';
?>
<?php else: ?>
      <div class="note muted">
        Không có trình xem trực tuyến cho định dạng <strong><?php echo h(strtoupper($ext)); ?></strong>.<br>
        Bạn có thể <a href="<?php echo h($webPath); ?>" download>tải xuống</a> để xem bằng ứng dụng tương ứng.
      </div>
<?php endif; ?>

<?php endif; ?>
    </div>
  </div>
</body>
</html>
