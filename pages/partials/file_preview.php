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


<?php elseif ($ext === 'ifc' || $ext === 'xkt'): ?>
  <!-- IFC/XKT Viewer (xeokit v2.6.89) + Tools: Measure, Probe Elevation, Section Cuts -->
  <style>
    #xeokitCanvas { width: 100%; height: calc(100vh - 64px); display:block; background:#f7f7f9; }
    .xk-toolbar { position:absolute; top:56px; left:12px; z-index:10; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .xk-toolbar button{ padding:8px 10px; border:1px solid #ddd; background:#fff; border-radius:8px; cursor:pointer; }
    .xk-toolbar button.active{ background:#0ea5e9; color:#fff; border-color:#0284c7; }
    .xk-toolbar .group{ display:flex; gap:6px; align-items:center; }
    .xk-progress { padding:6px 10px; border:1px solid #eee; background:#fff; border-radius:8px; font-size:12px; display:none; }
  </style>
  <div class="viewer-host" style="position:relative;">
    <div class="xk-toolbar">
      <div id="xkProgress" class="xk-progress"><span id="xkProgressLabel">Loading…</span></div>
      <div class="group">
        <button id="xkFit" title="Fit to view">Fit</button>
        <button id="xkReset" title="Reset view">Reset</button>
      </div>
      <div class="group">
        <button id="xkMeasure" title="Measure distance (2 clicks)">Measure</button>
        <button id="xkProbe" title="Probe elevation (click to drop a marker)">Probe Z</button>
      </div>
      <div class="group">
        <button id="xkCutX" title="Add section plane along +X">Cut X</button>
        <button id="xkCutY" title="Add section plane along +Y">Cut Y</button>
        <button id="xkCutZ" title="Add section plane along +Z">Cut Z</button>
        <button id="xkFaceCut" title="Click a face to create face-aligned cut">Face Cut</button>
        <button id="xkClearCuts" title="Remove all section planes">Clear</button>
      </div>
    </div>
    <canvas id="xeokitCanvas"></canvas>
  </div>

  <script>window.__ABS_URL__ = <?php echo json_encode($absUrl); ?>;</script>

  <!-- Import map: resolve html2canvas bare specifier used inside xeokit dist -->
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
    import { DistanceMeasurementsPlugin } from "../../xeokit/src/plugins/DistanceMeasurementsPlugin/DistanceMeasurementsPlugin.js";
    import { DistanceMeasurementsMouseControl } from "../../xeokit/src/plugins/DistanceMeasurementsPlugin/DistanceMeasurementsMouseControl.js";
    import { AnnotationsPlugin } from "../../xeokit/src/plugins/AnnotationsPlugin/AnnotationsPlugin.js";
    import { SectionPlanesPlugin } from "../../xeokit/src/plugins/SectionPlanesPlugin/SectionPlanesPlugin.js";
    import { FaceAlignedSectionPlanesPlugin } from "../../xeokit/src/plugins/FaceAlignedSectionPlanesPlugin/FaceAlignedSectionPlanesPlugin.js";
    import { PointerLens } from "../../xeokit/src/extras/PointerLens/PointerLens.js";
    import * as WebIFC from "../../xeokit/dist/web-ifc/web-ifc-api.js";

    const srcURL = window.__ABS_URL__;
    const viewer = new Viewer({ canvasId: "xeokitCanvas", transparent: true });
    if (viewer?.scene?.edgeMaterial) viewer.scene.edgeMaterial.edges = true;

    // Tools
    const distanceMeasurements = new DistanceMeasurementsPlugin(viewer, {});
    const distanceCtrl = new DistanceMeasurementsMouseControl(distanceMeasurements, { snapping: true, pointerLens: new PointerLens(viewer) });
    distanceCtrl.deactivate();

    const annotations = new AnnotationsPlugin(viewer, {
      labelShown: true
    });

    const sectionPlanes = new SectionPlanesPlugin(viewer, {});
    const faceAligned = new FaceAlignedSectionPlanesPlugin(viewer, {});

    // Units (optional): show meters
    try { viewer.scene.metrics.units = "meters"; } catch(e){}

    // UI helpers
    const ui = {
      bar: document.getElementById('xkProgress'),
      label: document.getElementById('xkProgressLabel'),
      btn: (id) => document.getElementById(id),
      canvas: viewer.scene.canvas.canvas
    };
    const setProgress = msg => { if (ui.bar) ui.bar.style.display='block'; if (ui.label) ui.label.textContent=msg; };
    const hideProgress = () => { if (ui.bar) ui.bar.style.display='none'; };
    const toggleBtn = (btn, on) => { if(!btn) return; btn.classList.toggle('active', !!on); };

    function flyToSafe(target){ try { viewer.cameraFlight.flyTo(target); } catch(e){} }

    // Modes
    let mode = "none"; // none | measure | probe | facecut
    function setMode(m){
      mode = m;
      toggleBtn(ui.btn('xkMeasure'), mode==='measure');
      toggleBtn(ui.btn('xkProbe'), mode==='probe');
      toggleBtn(ui.btn('xkFaceCut'), mode==='facecut');

      // Activate the proper tool
      if (mode === 'measure') {
        distanceCtrl.activate();
      } else {
        distanceCtrl.deactivate();
      }
    }

    // Load model (XKT first, else IFC)
    const isXKT = srcURL.toLowerCase().endsWith(".xkt");
    if (isXKT) {
      const xkt = new XKTLoaderPlugin(viewer);
      const model = xkt.load({ src: srcURL });
      if (model && model.on) {
        model.on("loaded", () => { hideProgress(); flyToSafe(model); });
        model.on("error", (e) => { console.error("XKT load error:", e); setProgress("Không thể nạp XKT."); });
      }
    } else {
      // IFC
      setProgress("Loading IFC…");
      const ifcAPI = new WebIFC.IfcAPI();
      if (ifcAPI.SetWasmPath) ifcAPI.SetWasmPath("../../xeokit/dist/web-ifc/");
      await ifcAPI.Init();
      const ifc = new WebIFCLoaderPlugin(viewer, { WebIFC, IfcAPI: ifcAPI, wasmPath: "../../xeokit/dist/web-ifc/" });
      const model = ifc.load({ src: srcURL });
      if (model && model.on) {
        model.on("loaded", () => { hideProgress(); flyToSafe(model); });
        model.on("error", (e) => { console.error("IFC load error:", e); setProgress("Không thể nạp IFC."); });
      }
    }

    // Toolbar events
    ui.btn('xkFit')?.addEventListener('click', () => flyToSafe(viewer.scene));
    ui.btn('xkReset')?.addEventListener('click', () => {
      try { viewer.camera.eye=[8,8,8]; viewer.camera.look=[0,0,0]; viewer.camera.up=[0,1,0]; } catch(e){}
    });

    ui.btn('xkMeasure')?.addEventListener('click', () => {
      setMode(mode==='measure' ? 'none' : 'measure');
    });

    // Probe elevation mode
    ui.btn('xkProbe')?.addEventListener('click', () => {
      setMode(mode==='probe' ? 'none' : 'probe');
    });

    // Section planes (axis-aligned)
    function addAxisCut(dir){
      try {
        sectionPlanes.createSectionPlane({
          pos: viewer.scene.center.slice ? viewer.scene.center.slice() : [0,0,0],
          dir: dir
        });
      } catch(e){ console.error(e); }
    }
    ui.btn('xkCutX')?.addEventListener('click', () => addAxisCut([1,0,0]));
    ui.btn('xkCutY')?.addEventListener('click', () => addAxisCut([0,1,0]));
    ui.btn('xkCutZ')?.addEventListener('click', () => addAxisCut([0,0,1]));
    ui.btn('xkClearCuts')?.addEventListener('click', () => { try { sectionPlanes.clear(); } catch(e){} });

    // Face-aligned cut: click on a face to create plane
    ui.btn('xkFaceCut')?.addEventListener('click', () => {
      setMode(mode==='facecut' ? 'none' : 'facecut');
    });

    // Canvas click handler for probe & facecut
    ui.canvas.addEventListener('click', (e) => {
      if (mode!=='probe' && mode!=='facecut') return;
      const rect = ui.canvas.getBoundingClientRect();
      const canvasPos = [e.clientX - rect.left, e.clientY - rect.top];
      let hit = null;
      try { hit = viewer.scene.pick({ canvasPos, pickSurface: true }); } catch(err){ console.warn(err); }
      if (!hit || !hit.worldPos) { return; }

      if (mode==='probe') {
        const p = hit.worldPos;
        const elev = (p[1]||0);
        const title = `Cao độ: ${elev.toFixed(3)} m`;
        annotations.createAnnotation({
          worldPos: p,
          markerShown: true,
          labelShown: true,
          occludable: true,
          values: { title, description: `X:${(p[0]||0).toFixed(3)}  Y:${(p[1]||0).toFixed(3)}  Z:${(p[2]||0).toFixed(3)}` }
        });
      } else if (mode==='facecut') {
        const p = hit.worldPos;
        const n = hit.worldNormal || [1,0,0];
        try {
          sectionPlanes.createSectionPlane({ pos: p, dir: n });
        } catch(err){ console.error(err); }
      }
    }, { capture: true });

  </script>

  <div class="note muted">
    Công cụ đã bật: <strong>Đo chiều dài</strong> (Measure), <strong>Probe cao độ</strong>, <strong>Cắt</strong> (Section planes).
    Nếu kết quả đo lệch đơn vị, bạn có thể điều chỉnh <code>viewer.scene.metrics.units</code> theo mô hình (m, cm, mm).
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
