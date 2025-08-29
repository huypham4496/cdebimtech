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

<?php elseif ($ext === 'ifc' || $ext === 'xkt'): ?>
  <!-- IFC/XKT Viewer via xeokit v2.6.89 - plugins from ../../xeokit/src/plugins/ -->
  <style>
    #xeokitCanvas { width: 100%; height: calc(100vh - 64px); display: block; background: #f7f7f9; }
    .xk-toolbar { position:absolute; top:56px; left:12px; z-index:10; display:flex; gap:8px; }
    .xk-toolbar button{ padding:8px 10px; border:1px solid #ddd; background:#fff; border-radius:8px; cursor:pointer; }
  </style>
  <div class="viewer-host" style="position:relative;">
    <div class="xk-toolbar">
      <button id="xkFit">Fit</button>
      <button id="xkXray">X-Ray</button>
      <button id="xkEdges">Edges</button>
      <button id="xkReset">Reset</button>
    </div>
    <canvas id="xeokitCanvas"></canvas>
  </div>

  <script>window.__ABS_URL__ = <?php echo json_encode($absUrl); ?>;</script>

  <!-- Import map to resolve html2canvas bare specifier from xeokit dist -->
  <script type="importmap">
    {
      "imports": {
        "html2canvas/dist/html2canvas.esm.js": "../../xeokit/vendor/html2canvas/html2canvas.esm.js"
      }
    }
  </script>

  
  <script type="module">
    import { Viewer } from "../../xeokit/dist/xeokit-sdk.min.es.js";
    import { XKTLoaderPlugin } from "../../xeokit/src/plugins/XKTLoaderPlugin/XKTLoaderPlugin.js";
    import { WebIFCLoaderPlugin } from "../../xeokit/src/plugins/WebIFCLoaderPlugin/WebIFCLoaderPlugin.js";
    import * as WebIFC from "../../xeokit/dist/web-ifc/web-ifc-api.js";

    const srcURL = window.__ABS_URL__;

    const init = async () => {
      const viewer = new Viewer({
        canvasId: "xeokitCanvas",
        transparent: true
      });

      if (viewer?.scene?.edgeMaterial) viewer.scene.edgeMaterial.edges = true;

      function flyTo(target){
        try { viewer.cameraFlight.flyTo(target); } catch(e){}
      }

      function bindToolbar(){
        document.getElementById('xkFit')?.addEventListener('click', () => flyTo(viewer.scene));
        let xrayed = false; document.getElementById('xkXray')?.addEventListener('click', ()=>{
          xrayed = !xrayed;
          try { viewer.scene?.setObjectsXRayed?.(viewer.scene?.objectIds||[], xrayed); } catch(e){}
        });
        let edged = true; document.getElementById('xkEdges')?.addEventListener('click', ()=>{
          edged = !edged;
          if (viewer?.scene?.edgeMaterial) viewer.scene.edgeMaterial.edges = edged;
        });
        document.getElementById('xkReset')?.addEventListener('click', ()=>{
          try {
            viewer.camera.eye = [8,8,8];
            viewer.camera.look = [0,0,0];
            viewer.camera.up = [0,1,0];
            if (viewer?.cameraControl) viewer.cameraControl.pivotPos = [0,0,0];
          } catch(e){}
        });
      }
      bindToolbar();

      const isXKT = srcURL.toLowerCase().endsWith(".xkt");

      if (isXKT) {
        const xkt = new XKTLoaderPlugin(viewer);
        const xktModel = xkt.load({ src: srcURL });
        if (xktModel && xktModel.on) {
          xktModel.on("loaded", () => flyTo(xktModel));
          xktModel.on("error", (e) => { console.warn("XKT error", e); tryIFC(viewer); });
        } else {
          // If loader returned synchronously, just attempt IFC fallback
          tryIFC(viewer);
        }
      } else {
        tryIFC(viewer);
      }

      async function tryIFC(viewer){
        try {
          const ifcAPI = new WebIFC.IfcAPI();
          if (ifcAPI.SetWasmPath) { ifcAPI.SetWasmPath("../../xeokit/dist/web-ifc/"); }
          await ifcAPI.Init();
          const ifc = new WebIFCLoaderPlugin(viewer, {
            WebIFC,
            IfcAPI: ifcAPI,
            wasmPath: "../../xeokit/dist/web-ifc/"
          });
          const ifcModel = ifc.load({ src: srcURL });
          if (ifcModel && ifcModel.on) {
            ifcModel.on("loaded", () => flyTo(ifcModel));
            ifcModel.on("error", (e) => {
              const msg = document.createElement("div");
              msg.className = "note warn"; msg.style.padding = "12px";
              msg.innerHTML = "Không thể nạp IFC (có thể do schema không được hỗ trợ, ví dụ IFC4X3_ADD2). Hãy chuyển sang <code>.xkt</code> hoặc xuất lại IFC (IFC4/IFC2x3).";
              document.querySelector(".viewer-host").prepend(msg);
              console.error("IFC load error:", e);
            });
          } else {
            const msg = document.createElement("div");
            msg.className = "note warn"; msg.style.padding = "12px";
            msg.innerHTML = "WebIFC loader không trả về model. Vui lòng chuyển đổi IFC sang XKT để xem mượt hơn.";
            document.querySelector(".viewer-host").prepend(msg);
          }
        } catch (e) {
          const msg = document.createElement("div");
          msg.className = "note warn"; msg.style.padding = "12px";
          msg.innerHTML = "Khởi tạo WebIFC thất bại. Vui lòng kiểm tra <code>web-ifc.wasm</code> và đường dẫn.";
          document.querySelector(".viewer-host").prepend(msg);
          console.error("IFC init error:", e);
        }
      }
    };

    init();
  </script>


  <div class="note muted">
    Đang xem mô hình qua <strong>xeokit v2.6.89</strong> (plugins từ <code>src/plugins</code>).
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

<?php elseif ($ext === 'ifc' || $ext === 'xkt'): ?>
  <!-- IFC/XKT Viewer via xeokit v2.6.89 - plugins from ../../xeokit/src/plugins/ -->
  <style>
    #xeokitCanvas { width: 100%; height: calc(100vh - 64px); display: block; background: #f7f7f9; }
    .xk-toolbar { position:absolute; top:56px; left:12px; z-index:10; display:flex; gap:8px; }
    .xk-toolbar button{ padding:8px 10px; border:1px solid #ddd; background:#fff; border-radius:8px; cursor:pointer; }
  </style>
  <div class="viewer-host" style="position:relative;">
    <div class="xk-toolbar">
      <button id="xkFit">Fit</button>
      <button id="xkXray">X-Ray</button>
      <button id="xkEdges">Edges</button>
      <button id="xkReset">Reset</button>
    </div>
    <canvas id="xeokitCanvas"></canvas>
  </div>

  <script>window.__ABS_URL__ = <?php echo json_encode($absUrl); ?>;</script>

  <!-- Import map to resolve html2canvas bare specifier from xeokit dist -->
  <script type="importmap">
    {
      "imports": {
        "html2canvas/dist/html2canvas.esm.js": "../../xeokit/vendor/html2canvas/html2canvas.esm.js"
      }
    }
  </script>

  <script type="module">
    import { Viewer } from "../../xeokit/dist/xeokit-sdk.min.es.js";
    import { XKTLoaderPlugin } from "../../xeokit/src/plugins/XKTLoaderPlugin/XKTLoaderPlugin.js";
    import { WebIFCLoaderPlugin } from "../../xeokit/src/plugins/WebIFCLoaderPlugin/WebIFCLoaderPlugin.js";
    import * as WebIFC from "../../xeokit/dist/web-ifc/web-ifc-api.js";

    const srcURL = window.__ABS_URL__;
    const viewer = new Viewer({
      canvasId: "xeokitCanvas",
      transparent: true
    });

    if (viewer?.scene?.edgeMaterial) viewer.scene.edgeMaterial.edges = true;

    const isXKT = srcURL.toLowerCase().endsWith(".xkt");

    function flyTo(target){
      try { viewer.cameraFlight.flyTo(target); } catch(e){}
    }

    function bindToolbar(){
      document.getElementById('xkFit')?.addEventListener('click', () => flyTo(viewer.scene));
      let xrayed = false; document.getElementById('xkXray')?.addEventListener('click', ()=>{
        xrayed = !xrayed;
        try { viewer.scene?.setObjectsXRayed?.(viewer.scene?.objectIds||[], xrayed); } catch(e){}
      });
      let edged = true; document.getElementById('xkEdges')?.addEventListener('click', ()=>{
        edged = !edged;
        if (viewer?.scene?.edgeMaterial) viewer.scene.edgeMaterial.edges = edged;
      });
      document.getElementById('xkReset')?.addEventListener('click', ()=>{
        try {
          viewer.camera.eye = [8,8,8];
          viewer.camera.look = [0,0,0];
          viewer.camera.up = [0,1,0];
          if (viewer?.cameraControl) viewer.cameraControl.pivotPos = [0,0,0];
        } catch(e){}
      });
    }
    bindToolbar();

    if (isXKT) {
      const xkt = new XKTLoaderPlugin(viewer);
      xkt.load({ src: srcURL }).then(model => {
        flyTo(model);
      }).catch(err => {
        console.warn("XKT load failed, trying IFC...", err);
        tryIFC();
      });
    } else {
      tryIFC();
    }

    function tryIFC(){
      const ifc = new WebIFCLoaderPlugin(viewer, {
        WebIFC,
        wasmPath: "../../xeokit/dist/web-ifc/"
      });
      ifc.load({ src: srcURL }).then(model => {
        flyTo(model);
      }).catch(err => {
        const msg = document.createElement('div');
        msg.className = 'note warn';
        msg.style.padding = '12px';
        msg.innerHTML = 'Không thể nạp IFC. Hãy kiểm tra tệp hoặc chuyển qua <code>.xkt</code> để xem nhanh hơn.';
        document.querySelector('.viewer-host').prepend(msg);
        console.error('IFC load error:', err);
      });
    }
  </script>

  <div class="note muted">
    Đang xem mô hình qua <strong>xeokit v2.6.89</strong> (plugins từ <code>src/plugins</code>).
  </div>

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
