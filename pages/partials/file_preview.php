<?php
/**
 * pages/partials/file_preview.php
 * Restore DWG viewing via ShareCAD (with clear notice: <50 MB limit & speed depends on ShareCAD).
 * - Office (doc/xls/ppt): Microsoft Office Online Viewer
 * - DWG: ShareCAD iframe
 * - PDF / Images / Video / Audio / Text: native tags
 *
 * Inputs:
 *   ?id={file_id}         -> map via DB (project_files + file_versions.storage_path) to /uploads/... path
 *   ?file=/uploads/...    -> direct web path
 */

@header_remove('X-Powered-By');
mb_internal_encoding('UTF-8');

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

// Optional size check for DWG
$dwgSize = ($ext==='dwg' && $absFs && is_file($absFs)) ? filesize($absFs) : 0;
$exceedsSharecad = ($dwgSize > 50*1024*1024 && $dwgSize > 0);

?><!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Preview – <?php echo h($filename); ?></title>
  <link rel="stylesheet" href="/assets/css/file_preview.css">
  <style>
    .warn { color:#b91c1c; font-weight:600; }
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
      <?php if ($exceedsSharecad): ?>
      <div class="note warn">
        Tệp DWG có dung lượng <?php echo number_format($dwgSize/1048576, 1); ?> MB &gt; 50 MB. ShareCAD có thể KHÔNG hiển thị được.
      </div>
      <?php endif; ?>
      <iframe src="https://sharecad.org/cadframe/load?url=<?php echo urlencode($absUrl); ?>&lang=en&zoom=1"
              allowfullscreen loading="lazy"></iframe>
      <div class="note muted">
        Xem DWG qua <strong>ShareCAD Viewer</strong>.<br>
        Giới hạn: <strong>&lt; 50 MB</strong>. Tốc độ tải &amp; hiển thị <strong>phụ thuộc vào dịch vụ ShareCAD</strong>.<br>
        Nếu không hiển thị, hãy đảm bảo URL file truy cập được từ Internet.
      </div>

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
