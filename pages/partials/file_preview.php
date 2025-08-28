
<?php
/**
 * pages/partials/file_preview.php
 * Preview viewer for CDE Files
 * - Office (doc/xls/ppt): Microsoft Office Online Viewer
 * - DWG: ShareCAD Viewer
 * - PDF / Images / Video / Audio / Text: native tags
 *
 * INPUTS:
 *   - Preferred: ?id={file_id}   (maps to latest/current version via DB -> /uploads/... path)
 *   - Also supported: ?file=/uploads/PRJxxxx/path/to/file.ext (direct web path)
 *
 * NOTE:
 *   This file keeps existing logic intact. Only adds DWG support and robust ?id -> web path mapping.
 */

@header_remove('X-Powered-By');
mb_internal_encoding('UTF-8');

// ---------------- Env helpers ----------------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function scheme(){
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) return $_SERVER['HTTP_X_FORWARDED_PROTO'];
  return 'http';
}
function host(){ return $_SERVER['HTTP_HOST'] ?? 'localhost'; }
function baseurl(){ return scheme() . '://' . host(); }
function norm_web_path($p){
  $p = '/' . ltrim((string)$p, '/');
  $p = strtok($p, '?#');
  return $p;
}

// ---------------- DB bootstrap ----------------
$FS_ROOT = realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2);
require_once $FS_ROOT . '/config.php'; // expects $pdo

// Build $pdo if config.php didn't create it (fallback)
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    try { $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch (Throwable $e) { $pdo = null; }
  } else {
    $pdo = null;
  }
}

// ---------------- Resolve input ----------------
$webPath = null;
$legacyId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (isset($_GET['file']) && $_GET['file'] !== '') {
  $webPath = norm_web_path($_GET['file']);
} elseif ($legacyId > 0 && $pdo instanceof PDO) {
  // Map id -> storage_path (prefer current_version; fallback MAX(version))
  try {
    // 1) Get filename + optionally current_version
    $st = $pdo->prepare("SELECT filename, current_version FROM project_files WHERE id=? AND is_deleted=0");
    $st->execute([$legacyId]);
    $fileRow = $st->fetch(PDO::FETCH_ASSOC);
    if ($fileRow) {
      $filename = (string)$fileRow['filename'];
      $cur = isset($fileRow['current_version']) ? (int)$fileRow['current_version'] : 0;

      // 2) Choose version
      if ($cur <= 0) {
        $st2 = $pdo->prepare("SELECT MAX(version) AS v FROM file_versions WHERE file_id=?");
        $st2->execute([$legacyId]);
        $cur = (int)($st2->fetchColumn() ?: 0);
      }

      // 3) Fetch storage_path of chosen version
      if ($cur > 0) {
        $st3 = $pdo->prepare("SELECT storage_path FROM file_versions WHERE file_id=? AND version=?");
        $st3->execute([$legacyId, $cur]);
        $vr = $st3->fetch(PDO::FETCH_ASSOC);
        if ($vr && !empty($vr['storage_path'])) {
          // storage_path is relative to project root (e.g., uploads/PRJ00001/files/...)
          $webPath = '/' . ltrim($vr['storage_path'], '/\\');
        }
      } else {
        // No version found – try to infer main path (non-versioned)
        // NOTE: This is conservative; most flows should have versions.
        // If your storage keeps the latest file as plain /uploads/.../{filename}, set it here if known.
        $webPath = null;
      }
    }
  } catch (Throwable $e) {
    // leave $webPath = null
  }
}

// Filename / extension
$filename = $webPath ? basename($webPath) : ($legacyId ? ('#' . $legacyId) : 'Unknown');
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Abs URL for external viewers
$absUrl = $webPath ? (baseurl() . $webPath) : '';

// Group helpers
function is_image_ext($e){ return in_array($e, ['png','jpg','jpeg','gif','webp','bmp','svg']); }
function is_video_ext($e){ return in_array($e, ['mp4','webm','ogv','mov']); }
function is_audio_ext($e){ return in_array($e, ['mp3','ogg','wav','m4a','aac']); }
function is_text_ext($e){ return in_array($e, ['txt','log','csv','json','xml','yml','yaml','md','html','css','js','php']); }

?><!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Preview – <?php echo h($filename); ?></title>
  <link rel="stylesheet" href="/assets/css/file_preview.css">
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div class="file"><?php echo h($filename); ?></div>
      <div class="controls">
        <?php if ($webPath): ?>
          <a class="btn" href="<?php echo h($webPath); ?>" download>Download</a>
        <?php elseif ($legacyId): ?>
          <a class="btn" href="<?php echo h('/pages/partials/project_tab_files.php?action=download_one&file_id='.$legacyId); ?>" target="_blank" rel="noopener">Download</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="viewer">
<?php if (!$webPath): ?>
      <div class="note muted">
        Missing file path.<br>
        Hãy gọi: <code>file_preview.php?file=/uploads/PRJxxxx/yourfile.ext</code> hoặc mở từ bảng Files (sẽ truyền <code>id</code>) để tự ánh xạ sang đường dẫn web.<br>
        <?php if ($legacyId): ?>
          Nhận được <code>id=<?php echo h($legacyId); ?></code> nhưng chưa tìm thấy storage_path (có thể file chưa có phiên bản?).
        <?php endif; ?>
      </div>
<?php else: ?>

<?php if (in_array($ext, ['doc','docx','xls','xlsx','ppt','pptx'])): ?>
      <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=<?php echo urlencode($absUrl); ?>" allowfullscreen loading="lazy"></iframe>
      <div class="note muted">Đang xem qua Microsoft Office Online Viewer.</div>

<?php elseif ($ext === 'dwg'): ?>
      <iframe src="https://sharecad.org/cadframe/load?url=<?php echo urlencode($absUrl); ?>&lang=en&zoom=1"
              allowfullscreen loading="lazy"></iframe>
      <div class="note muted">Đang xem DWG qua ShareCAD Viewer. Nếu không hiển thị, hãy đảm bảo URL tệp truy cập được từ Internet.</div>

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
      // Show text files from filesystem if allowed (resolve to absolute path under project root)
      $absFs = $FS_ROOT . '/' . ltrim($webPath, '/');
      if (is_file($absFs) && is_readable($absFs)) {
        $txt = file_get_contents($absFs);
        echo '<pre class="code">'.h($txt).'</pre>';
      } else {
        echo '<div class="note muted">Không đọc được nội dung văn bản.</div>';
      }
?>
<?php else: ?>
      <div class="note muted">
        Không có trình xem trực tuyến cho định dạng <strong><?php echo h(strtoupper($ext)); ?></strong>.<br>
        Bạn có thể <a href="<?php echo h($webPath); ?>" download>tải xuống</a> để xem bằng ứng dụng tương ứng.
      </div>
<?php endif; ?>

<?php endif; // $webPath ?>
    </div>
  </div>
</body>
</html>
