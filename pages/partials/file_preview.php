<?php
/**
 * file_preview.php — Preview files in CDE
 * - DOC/DOCX/XLS/XLSX/XLSM/PPT/PPTX: Microsoft Office Online Viewer
 * - PDF/Images/Video/Audio/Text: inline preview
 * - Topbar: file name + Download button
 * - Removed "Source: id/path"
 */

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$BASE_ROOT = realpath(__DIR__ . '/../../');
if ($BASE_ROOT === false) { http_response_code(500); echo "Base path not found."; exit; }
$UPLOADS_DIR = $BASE_ROOT . DIRECTORY_SEPARATOR . 'uploads';

$pdo = null;
require_once __DIR__ . '/../../config.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (defined('DB_HOST')) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {}
    }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function to_abs_from_rel($rel){
    global $BASE_ROOT;
    $rel = ltrim(str_replace(['\\'], ['/'], (string)$rel), '/');
    return $BASE_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
}
function to_public_url($rel){
    $rel = ltrim(str_replace(['\\'], ['/'], (string)$rel), '/');
    if (stripos($rel, 'uploads/') === 0) return '/' . $rel;
    return '/uploads/' . $rel;
}
function absolute_url($path){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . $host . $path;
}

$source=null;$file_id=0;$relPath='';
if (isset($_GET['id']) && is_numeric($_GET['id'])) { $file_id=(int)$_GET['id']; $source='id'; }
elseif (!empty($_GET['file'])) { $relPath=(string)$_GET['file']; $source='file'; }
else { http_response_code(400); echo "Missing ?id= or ?file= parameter."; exit; }

$absPath=null;$publicUrl=null;$displayName=null;$ext=null;
try {
    if ($source==='id') {
        if (!$pdo) throw new RuntimeException("DB not init.");
        $stmt=$pdo->prepare("SELECT MAX(version) FROM file_versions WHERE file_id=?");
        $stmt->execute([$file_id]); $v=(int)($stmt->fetchColumn()?:0);
        $stmt2=$pdo->prepare("SELECT storage_path FROM file_versions WHERE file_id=? AND version=?");
        $stmt2->execute([$file_id,$v]); $row2=$stmt2->fetch();
        $relPath=(string)$row2['storage_path'];
        $absPath=to_abs_from_rel($relPath);
        $publicUrl=to_public_url($relPath);
        $stmt3=$pdo->prepare("SELECT filename FROM project_files WHERE id=?");
        $stmt3->execute([$file_id]); $displayName=(string)($stmt3->fetchColumn()?:basename($relPath));
        $ext=strtolower(pathinfo($displayName,PATHINFO_EXTENSION));
    } else {
        $relPath=ltrim(strtok($relPath,"?#"),'/\\');
        $absPath=to_abs_from_rel($relPath);
        $publicUrl=to_public_url($relPath);
        $displayName=basename($relPath);
        $ext=strtolower(pathinfo($displayName,PATHINFO_EXTENSION));
    }
} catch(Throwable $e){ http_response_code(404); echo "File not found"; exit; }

$real=realpath($absPath);
if ($real===false || strpos($real, realpath($UPLOADS_DIR))!==0) { http_response_code(404); echo "File not found"; exit; }
if (!is_file($real)) { http_response_code(404); echo "File not found"; exit; }

$isOffice=in_array($ext,['doc','docx','xls','xlsx','xlsm','ppt','pptx'],true);
$absUrl=absolute_url($publicUrl);
$officeUrl="https://view.officeapps.live.com/op/embed.aspx?src=".rawurlencode($absUrl);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Xem file – <?php echo h($displayName); ?></title>
<link rel="stylesheet" href="/assets/css/file_preview.css">
</head>
<body>
<div class="topbar">
  <div class="file"><?php echo h($displayName); ?></div>
  <div class="controls">
    <a class="btn" href="<?php echo h($publicUrl); ?>" download="<?php echo h($displayName); ?>">Download</a>
  </div>
</div>
<div class="wrap">
  <div class="viewer">
    <?php if ($isOffice): ?>
      <iframe src="<?php echo h($officeUrl); ?>"></iframe>
      <div class="note muted">Đang hiển thị bằng <b>Microsoft Office Online Viewer</b>.</div>
    <?php elseif ($ext==='pdf'): ?>
      <embed src="<?php echo h($publicUrl); ?>" type="application/pdf" />
    <?php elseif (in_array($ext,['png','jpg','jpeg','gif','webp','bmp','svg'])): ?>
      <img class="img-preview" src="<?php echo h($publicUrl); ?>" alt="<?php echo h($displayName); ?>"/>
    <?php elseif (in_array($ext,['mp4','webm','ogg'])): ?>
      <video controls>
        <source src="<?php echo h($publicUrl); ?>" type="video/<?php echo h($ext); ?>">
      </video>
    <?php elseif (in_array($ext,['mp3','wav','m4a','oga'])): ?>
      <audio controls>
        <source src="<?php echo h($publicUrl); ?>">
      </audio>
    <?php elseif (in_array($ext,['txt','csv','md','log','json','xml','yml','yaml','ini'])): ?>
      <pre class="code"><?php echo h(@file_get_contents($real)); ?></pre>
    <?php else: ?>
      <div class="note muted">Không có bản xem trước cho <b>.<?php echo h($ext); ?></b>.</div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
