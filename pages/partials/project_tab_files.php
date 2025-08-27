<?php

// ===== Helpers for aligned Name column (thumb + ext + title) =====
if (!function_exists('cde_file_name_parts')) {
    function cde_file_name_parts($filename) {
        $fn = (string)$filename;
        $ext = pathinfo($fn, PATHINFO_EXTENSION);
        $name = pathinfo($fn, PATHINFO_FILENAME);
        $ext_disp = $ext !== '' ? ('.' . strtoupper($ext)) : '';
        return [$name, $ext_disp, strtolower($ext)];
    }
}
if (!function_exists('cde_build_name_html')) {
    function cde_build_name_html($filename) {
        list($title, $ext_disp, $ext_raw) = cde_file_name_parts($filename);
        $ext_raw_h = htmlspecialchars($ext_raw, ENT_QUOTES, 'UTF-8');
        $ext_disp_h = htmlspecialchars($ext_disp, ENT_QUOTES, 'UTF-8');
        $full_h = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
        return '<div class="ft-wrap"><span class="ft-thumb" data-ext="'.$ext_raw_h.'"></span><span class="ft-ext">'.$ext_disp_h.'</span><span class="ft-title">'.$full_h.'</span></div>';
    }
}
/**
 * Files Tab (project_tab_files.php)
 * - Folder tree (left), file list (right), toolbar with search/filter/upload (top)
 * - Versioning via file_versions table
 * - Permissions check using project_group_members (group_id=1 => manager). Others need 'control' role to write.
 * Storage:
 *   uploads/{PROJECT_CODE}/files/{folder_id}/{filename}__v{N}.{ext}
 *
 * NOTE: This tab expects DB schema from cde.sql. Folder/file permissions per-group are stubbed;
 *       you can enable advanced per-folder permissions later (see TODO markers).
 */

// Bootstrap
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config.php'; // defines $pdo
ini_set('display_errors', '0');
// Build $pdo from DB_* constants if config.php didn't create it
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            // Defer to AJAX JSON handler to report cleanly
        }
    }
}

date_default_timezone_set('Asia/Bangkok');

function ensure_current_version_column($pdo){
    try {
        $st = $pdo->query("SHOW COLUMNS FROM project_files LIKE 'current_version'");
        if(!$st->fetch()){
            $pdo->exec("ALTER TABLE project_files ADD COLUMN current_version INT NULL DEFAULT NULL");
        }
    } catch (Throwable $e) { /* ignore if not permitted */ }
}


function base_root(){ return realpath(__DIR__ . '/../../'); }
function to_abs($rel){
    $root = rtrim(base_root(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $rel = ltrim($rel, '/\\');
    return $root . $rel;
}
function to_rel($abs){
    $root = rtrim(base_root(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $abs = str_replace(['\\','//'], ['/', '/'], $abs);
    $root2 = str_replace(['\\','//'], ['/', '/'], $root);
    if(strpos($abs, $root2)===0) return substr($abs, strlen($root2));
    return $abs;
}
function ensure_dir($p){ if(!is_dir($p)) @mkdir($p, 0775, true); return $p; }


$__CDE_IS_AJAX__ = isset($_GET['ajax']);
if ($__CDE_IS_AJAX__) {
    // Ensure no HTML leaks into JSON even on fatal
    ini_set('display_errors', '0');
    header('X-CDE-Files', 'ajax');
    if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
    register_shutdown_function(function(){
        $e = error_get_last();
        if($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])){
            while(ob_get_level()>0){ @ob_end_clean(); }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false,'error'=>'Fatal server error','detail'=>$e['message'],'line'=>$e['line'],'file'=>$e['file']]);
        }
    });
}


header_register_callback(function(){ /* ensure headers can still change later */ });

function json_resp($ok=true, $data=[], $code=200){
    while(ob_get_level()>0){ @ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>$ok] + (is_array($data)?$data:['data'=>$data]));
    exit;
}

function require_param($key, $method='POST'){
    $src = $method === 'GET' ? $_GET : $_POST;
    if(!isset($src[$key])) json_resp(false, ['error'=>"Missing parameter: $key"], 400);
    return $src[$key];
}

function current_user_id(){
    if(isset($_SESSION['user_id'])) return intval($_SESSION['user_id']);
    if(isset($_SESSION['id'])) return intval($_SESSION['id']);
    if(isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id'])) return intval($_SESSION['user']['id']);
    if(isset($_SESSION['auth']) && is_array($_SESSION['auth']) && isset($_SESSION['auth']['id'])) return intval($_SESSION['auth']['id']);
    return 0;
}

function get_project($pdo, $project_id){
    $stmt = $pdo->prepare("SELECT id, code, name FROM projects WHERE id=?");
    $stmt->execute([$project_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function is_project_member($pdo, $project_id, $user_id){
    // either in project_group_members OR project_members (fallback)
    $stmt = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND user_id=? LIMIT 1");
    $stmt->execute([$project_id, $user_id]);
    if($stmt->fetchColumn()) return true;
    $stmt2 = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id=? AND user_id=? LIMIT 1");
    $stmt2->execute([$project_id, $user_id]);
    return (bool)$stmt2->fetchColumn();
}


function safe_lower($s){ return function_exists('mb_strtolower') ? mb_strtolower($s,'UTF-8') : strtolower($s); }
function manager_group_ids($pdo, $project_id){
    // Try to discover manager group(s) for this project.
    // Strategy:
    // 1) Look into project_groups for names like 'manager'.
    // 2) Fallback to id = 1.
    $ids = [];
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM project_groups WHERE project_id=?");
        $stmt->execute([$project_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $r){
            $n = safe_lower(trim($r['name']));
            if($n==='manager' || $n==='managers' || strpos($n, 'manager')===0 || $n==='quản lý' || $n==='quan ly'){
                $ids[] = intval($r['id']);
            }
        }
    } catch(Exception $e){
        // table may not exist; ignore
    }
    if(empty($ids)){
        // safe fallback
        $ids[] = 1;
    }
    return $ids;
}

function can_write($pdo, $project_id, $user_id){
    $mgrIds = manager_group_ids($pdo, $project_id);
    $in = implode(',', array_fill(0, count($mgrIds), '?'));
    $params = array_merge([$project_id, $user_id], $mgrIds);
    $sql = "SELECT 1 FROM project_group_members WHERE project_id=? AND user_id=? AND group_id IN ($in) LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function ensure_root_folder($pdo, $project_id){
    // Create root folder if missing (name = project code)
    $project = get_project($pdo, $project_id);
    if(!$project) return 0;
    $stmt = $pdo->prepare("SELECT id FROM project_folders WHERE project_id=? AND parent_id IS NULL LIMIT 1");
    $stmt->execute([$project_id]);
    $id = $stmt->fetchColumn();
    if($id) return intval($id);
    $ins = $pdo->prepare("INSERT INTO project_folders (project_id, parent_id, name, created_by) VALUES (?,?,?,?)");
    $ins->execute([$project_id, null, $project['code'], current_user_id()]);
    return intval($pdo->lastInsertId());
}

function get_project_code($pdo, $project_id){
    $p = get_project($pdo, $project_id);
    if($p && !empty($p['code'])) return $p['code'];
    return 'PRJ'.str_pad((string)$project_id, 5, '0', STR_PAD_LEFT);
}


function folder_segments($pdo, $project_id, $folder_id){
    // Build array of folder names from ROOT(child of project) -> ... -> current (exclude root project code)
    $segs = [];
    if(!$folder_id) return $segs;
    $root_id = ensure_root_folder($pdo, $project_id);
    $cur = intval($folder_id);
    while($cur){
        $stmt = $pdo->prepare("SELECT id, parent_id, name FROM project_folders WHERE id=? AND project_id=?");
        $stmt->execute([$cur, $project_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row) break;
        if($row['parent_id'] === null){ // root folder (project code) -> do not include
            break;
        }
        array_unshift($segs, sanitize_filename($row['name']));
        $cur = $row['parent_id'] ? intval($row['parent_id']) : 0;
    }
    return $segs;
}
function folder_dir($pdo, $project_id, $folder_id){
    $base = storage_base_path($pdo, $project_id); // uploads/PRJxxxxx/files
    $segs = folder_segments($pdo, $project_id, $folder_id);
    $dir = $base;
    foreach($segs as $s){
        $dir = path_join($dir, $s);
        if(!is_dir($dir)) @mkdir($dir, 0775, true);
    }
    return $dir;
}

function old_versions_dir($pdo, $project_id){
    $base = storage_base_path($pdo, $project_id);
    $old = path_join($base, 'old_version');
    ensure_dir($old);
    return $old;
}

function storage_base_path($pdo, $project_id){
    $code = get_project_code($pdo, $project_id);
    $base = __DIR__ . "/../../uploads/" . $code . "/files";
    if(!is_dir($base)){
        @mkdir($base, 0775, true);
    }
    return realpath($base) ?: $base;
}

function move_version_to_old($pdo, $project_id, $file_id, $vi){
    if(!$vi || empty($vi['storage_path'])) return false;
    $oldDir = old_versions_dir($pdo, $project_id); ensure_dir($oldDir);
    $srcFull = to_abs($vi['storage_path']);
    if(!is_file($srcFull)) return false;
    $safe = sanitize_filename((string)$file_id).'__v'.intval($vi['version']).'__'.basename($srcFull);
    $dstFull = $oldDir . DIRECTORY_SEPARATOR . $safe;
    $moved = @rename($srcFull, $dstFull);
    if(!$moved){
        if(@copy($srcFull, $dstFull)){
            @unlink($srcFull); // ensure old file removed when copy was used
            $moved = true;
        }
    }
    if($moved){
        $rel = to_rel($dstFull);
        $pdo->prepare("UPDATE file_versions SET storage_path=? WHERE file_id=? AND version=?")
            ->execute([$rel, $file_id, intval($vi['version'])]);
        return true;
    }
    return false;
}

function latest_version_info($pdo, $file_id){
    $stmt = $pdo->prepare("SELECT MAX(version) AS v FROM file_versions WHERE file_id=?");
    $stmt->execute([$file_id]);
    $v = intval($stmt->fetchColumn() ?: 0);
    if($v===0) return null;
    $stmt2 = $pdo->prepare("SELECT version, storage_path, size_bytes, uploaded_by, created_at FROM file_versions WHERE file_id=? AND version=?");
    $stmt2->execute([$file_id, $v]);
    return $stmt2->fetch(PDO::FETCH_ASSOC) + ['version'=>$v];
}

function path_join(...$parts){
    $p = join(DIRECTORY_SEPARATOR, $parts);
    return preg_replace('#[\\/]+#','/',$p);
}

function sanitize_filename($name){
    return preg_replace('/[^\w\-. ]+/u','_', $name);
}

function safe_send_file($fullpath, $download_name=null){
    if(!is_file($fullpath)) json_resp(false, ['error'=>'File not found'], 404);
    $download_name = $download_name ?: basename($fullpath);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.rawurlencode($download_name).'"');
    header('Content-Length: '.filesize($fullpath));
    readfile($fullpath);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($__CDE_IS_AJAX__) && $__CDE_IS_AJAX__) {
        while(ob_get_level()>0){ @ob_end_clean(); }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'DB connection ($pdo) not initialized']);
        exit;
    } else {
        echo '<div style="padding:12px;border:1px solid #fecaca;background:#fff1f2;color:#991b1b;border-radius:12px;">DB connection ($pdo) not initialized. Check config.php include and ensure it defines $pdo.</div>';
        return;
    }
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


function ensure_files_schema($pdo){
    // Create minimal tables if they do not exist to avoid 500s.
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_folders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        parent_id INT NULL,
        name VARCHAR(255) NOT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(project_id), INDEX(parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        folder_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        tag ENUM('WIP','Shared','Published','Archived') DEFAULT 'WIP',
        is_important TINYINT(1) DEFAULT 0,
        is_deleted TINYINT(1) DEFAULT 0,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(project_id), INDEX(folder_id), INDEX(is_deleted), INDEX(tag), INDEX(filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS file_versions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT NOT NULL,
        version INT NOT NULL,
        storage_path VARCHAR(1024) NOT NULL,
        size_bytes BIGINT DEFAULT 0,
        uploaded_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (file_id, version),
        INDEX(file_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_files_schema($pdo);


$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : (isset($_POST['project_id']) ? intval($_POST['project_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0));
$user_id = current_user_id();
$root_folder_id = $project_id ? ensure_root_folder($pdo, $project_id) : 0;

// ---- AJAX HANDLERS ----

if(isset($_GET['ajax'])){
    try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    if($action==='ping'){
        json_resp(true, ['pong'=>true, 'project_id'=>$project_id]);
    }
    if($action==='whoami'){
        $is_member = $project_id ? is_project_member($pdo, $project_id, $user_id) : false;
        $mgrIds = manager_group_ids($pdo, $project_id);
        $can_admin = can_write($pdo, $project_id, $user_id);
        json_resp(true, [
            'user_id'=>$user_id,
            'project_id'=>$project_id,
            'is_member'=>$is_member,
            'manager_group_ids'=>$mgrIds,
            'can_admin'=>$can_admin
        ]);
    }
    if($action==='peek'){
        $folder_id = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : $root_folder_id;
        $dir = folder_dir($pdo, $project_id, $folder_id);
        $stmt1 = $pdo->prepare("SELECT id, name FROM project_folders WHERE project_id=? AND (parent_id ".($folder_id?"= ?":"IS NULL").") ORDER BY name");
        $folderParams = $folder_id ? [$project_id, $folder_id] : [$project_id];
        $stmt1->execute($folderParams);
        $folders = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        $stmt2 = $pdo->prepare("SELECT id, filename, tag FROM project_files WHERE project_id=? AND folder_id=? AND is_deleted=0 ORDER BY updated_at DESC");
        $stmt2->execute([$project_id, $folder_id ?: $root_folder_id]);
        $files = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        json_resp(true, ['folder_id'=>$folder_id, 'dir'=>$dir, 'folders'=>$folders, 'files'=>$files]);
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    if(!$project_id) json_resp(false, ['error'=>'Missing project_id'], 400);
    if(!is_project_member($pdo, $project_id, $user_id)){
        json_resp(false, ['error'=>'⚠️ Bạn không có quyền truy cập Tab Files của dự án này (chỉ thành viên trong dự án mới được xem/cập nhật).'], 403);
    }

    // LIST TREE
    if($action==='list_tree'){
        $stmt = $pdo->prepare("SELECT id, parent_id, name FROM project_folders WHERE project_id=? ORDER BY name");
        $stmt->execute([$project_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_resp(true, ['tree'=>$rows, 'root_id'=>$root_folder_id]);
    }

    // LIST ITEMS in folder
    if($action==='list_items'){
        $folder_id = intval(require_param('folder_id', 'GET'));
        // Folders
        $stmt1 = $pdo->prepare("SELECT id, name, created_at FROM project_folders WHERE project_id=? AND (parent_id ".($folder_id?'= ?':'IS NULL').") ORDER BY name");
        $stmt1->execute($folder_id ? [$project_id, $folder_id] : [$project_id]);
        $folders = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        // Files (not deleted)
        $stmt2 = $pdo->prepare("SELECT f.id, f.filename, f.tag, f.is_important, f.created_by, f.created_at, f.updated_at, f.current_version
                                FROM project_files f
                                WHERE f.project_id=? AND f.folder_id=? AND f.is_deleted=0
                                ORDER BY f.updated_at DESC");
        $stmt2->execute([$project_id, $folder_id ?: $root_folder_id]);
        $files = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        // Attach latest version info
        foreach($files as &$r){

// Compute total_versions and normalize current_version
$st2 = $pdo->prepare("SELECT COUNT(*) FROM file_versions WHERE file_id=?");
$st2->execute([$r['id']]);
$r['total_versions'] = (int)($st2->fetchColumn() ?: 0);
$r['current_version'] = isset($r['current_version']) ? (int)$r['current_version'] : 0;

            $vi = latest_version_info($pdo, $r['id']);


            // ===== Build preview URL + Name HTML (anchor) =====
            $__open_url = 'partials/file_preview.php?id='.(int)$r['id'];
            $r['open_url'] = $__open_url;
            // Only set name_html; DO NOT wrap $r['filename'] to avoid breaking ext parsing on frontend
            $r['name_html'] = '<a class="ft-open" target="_blank" rel="noopener" href="'.htmlspecialchars($__open_url, ENT_QUOTES, 'UTF-8').'">'.cde_build_name_html($r['filename']).'</a>';
            // Expose extension in a raw form for clients that want it
            list($__title, $__ext_disp, $__ext_raw) = cde_file_name_parts($r['filename']);
            $r['ext_raw'] = $__ext_raw;
            // Name column fields (do not alter raw filename)
            list($___title, $___ext_disp, $___ext_raw) = cde_file_name_parts($r['filename']);
            $r['file_ext']   = $___ext_disp;         // e.g., .PDF
            $r['file_title'] = $___title;            // filename without extension
            $r['ext_raw']    = $___ext_raw;          // e.g., pdf
            $r['name_html'] = '<a class="ft-open" target="_blank" rel="noopener" href="'.htmlspecialchars($__open_url, ENT_QUOTES, 'UTF-8').'">'.cde_build_name_html($r['filename']).'</a>';
            $r['version'] = $vi ? intval($vi['version']) : 0;
            $r['size_bytes'] = $vi ? intval($vi['size_bytes']) : 0;
            $r['storage_path'] = $vi ? $vi['storage_path'] : null;
        }
        json_resp(true, ['folders'=>$folders, 'files'=>$files]);
    }

    // SEARCH
    if($action==='search'){
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $tag = isset($_GET['tag']) ? $_GET['tag'] : null;
        $important = (isset($_GET['important']) && $_GET['important'] !== '') ? intval($_GET['important']) : null;

        $patterns = [];
        if($q !== ''){
            // support "*abc", "abc*", ".pdf"
            if($q[0]==='*'){
                $patterns[] = "%".substr($q,1)."%";
            } elseif(substr($q,-1)==='*'){
                $patterns[] = substr($q,0,-1)."%";
            } else {
                $patterns[] = "%".$q."%";
            }
        } else {
            $patterns[] = '%';
        }

        $sql = "SELECT f.id, f.folder_id, f.filename, f.tag, f.is_important, f.created_by, f.updated_at, f.current_version
         FROM project_files f
                WHERE f.project_id=? AND f.is_deleted=0 AND (";
        $conds = [];
        $params = [$project_id];
        foreach($patterns as $p){
            $conds[] = "f.filename LIKE ?";
            $params[] = $p;
        }
        $sql .= implode(" OR ", $conds) . ")";
        if($tag){
            $sql .= " AND f.tag = ?";
            $params[] = $tag;
        }
        if($important!==null){
            $sql .= " AND f.is_important = ?";
            $params[] = $important;
        }
        $sql .= " ORDER BY f.updated_at DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$r){
            $vi = latest_version_info($pdo, $r['id']);


            // ===== Build preview URL + Name HTML (anchor) =====
            $__open_url = 'partials/file_preview.php?id='.(int)$r['id'];
            $r['open_url'] = $__open_url;
            // Only set name_html; DO NOT wrap $r['filename'] to avoid breaking ext parsing on frontend
            $r['name_html'] = '<a class="ft-open" target="_blank" rel="noopener" href="'.htmlspecialchars($__open_url, ENT_QUOTES, 'UTF-8').'">'.cde_build_name_html($r['filename']).'</a>';
            // Expose extension in a raw form for clients that want it
            list($__title, $__ext_disp, $__ext_raw) = cde_file_name_parts($r['filename']);
            $r['ext_raw'] = $__ext_raw;
            // Name column fields (do not alter raw filename)
            list($___title, $___ext_disp, $___ext_raw) = cde_file_name_parts($r['filename']);
            $r['file_ext']   = $___ext_disp;         // e.g., .PDF
            $r['file_title'] = $___title;            // filename without extension
            $r['ext_raw']    = $___ext_raw;          // e.g., pdf
            $r['name_html'] = '<a class="ft-open" target="_blank" rel="noopener" href="'.htmlspecialchars($__open_url, ENT_QUOTES, 'UTF-8').'">'.cde_build_name_html($r['filename']).'</a>';
            $r['version'] = $vi ? intval($vi['version']) : 0;
            // Attach latest version info
foreach ($files as &$r) {
    $vi = latest_version_info($pdo, $r['id']);


            // ===== Build preview URL + Name HTML (anchor) =====
            $__open_url = 'partials/file_preview.php?id='.(int)$r['id'];
            $r['open_url'] = $__open_url;
            // Only set name_html; DO NOT wrap $r['filename'] to avoid breaking ext parsing on frontend
            $r['name_html'] = '<a class="ft-open" target="_blank" rel="noopener" href="'.htmlspecialchars($__open_url, ENT_QUOTES, 'UTF-8').'">'.cde_build_name_html($r['filename']).'</a>';
            // Expose extension in a raw form for clients that want it
            list($__title, $__ext_disp, $__ext_raw) = cde_file_name_parts($r['filename']);
            $r['ext_raw'] = $__ext_raw;
            // Name column fields (do not alter raw filename)
            list($___title, $___ext_disp, $___ext_raw) = cde_file_name_parts($r['filename']);
            $r['file_ext']   = $___ext_disp;         // e.g., .PDF
            $r['file_title'] = $___title;            // filename without extension
            $r['ext_raw']    = $___ext_raw;          // e.g., pdf
            $r['name_html'] = '<a class="ft-open" target="_blank" rel="noopener" href="'.htmlspecialchars($__open_url, ENT_QUOTES, 'UTF-8').'">'.cde_build_name_html($r['filename']).'</a>';
    $r['version']       = $vi ? intval($vi['version']) : 0;   // max version (giữ nguyên)
    $r['size_bytes']    = $vi ? intval($vi['size_bytes']) : 0;
    $r['storage_path']  = $vi ? $vi['storage_path'] : null;

    // NEW: tổng số phiên bản
    $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM file_versions WHERE file_id=?");
    $stmtCnt->execute([$r['id']]);
    $r['total_versions'] = intval($stmtCnt->fetchColumn() ?: 0);
    // current_version đã có từ SELECT (có thể null nếu data cũ)
    $r['current_version'] = isset($r['current_version']) ? intval($r['current_version']) : 0;
}
            $r['size_bytes'] = $vi ? intval($vi['size_bytes']) : 0;
            $r['storage_path'] = $vi ? $vi['storage_path'] : null;
        }
        json_resp(true, ['results'=>$rows]);
    }

    // CREATE FOLDER
    if($action==='create_folder'){
        if(!can_write($pdo, $project_id, $user_id)) json_resp(false, ['error'=>'Bạn không có quyền tạo thư mục.'], 403);
        $parent_id = isset($_POST['parent_id']) ? (strlen($_POST['parent_id'])?intval($_POST['parent_id']):null) : null;
        $name = trim(require_param('name'));
        if($name==='') json_resp(false, ['error'=>'Tên thư mục không được để trống'], 422);
        $stmt = $pdo->prepare("INSERT INTO project_folders (project_id, parent_id, name, created_by) VALUES (?,?,?,?)");
        $stmt->execute([$project_id, $parent_id, $name, $user_id]);
        $new_id = intval($pdo->lastInsertId());
        // Ensure physical directory exists now
        $dir = folder_dir($pdo, $project_id, $new_id);
        json_resp(true, ['id'=>$new_id, 'dir_created'=>is_dir($dir)]);
    }

    // TOGGLE IMPORTANT
    
    // GET VERSIONS
    
    // RESTORE VERSION: copy <base>_vN.ext from old_version back to main as original name (no new version)
    if($action==='restore_version' && $_SERVER['REQUEST_METHOD']==='POST'){
        if(!can_write($pdo, $project_id, $user_id)) json_resp(false, ['error'=>'Bạn không có quyền khôi phục phiên bản.'], 403);
        $file_id = intval(require_param('file_id'));
        $version = intval(require_param('version'));
        $frow = $pdo->prepare("SELECT folder_id, filename FROM project_files WHERE id=? AND is_deleted=0");
        $frow->execute([$file_id]);
        $fr = $frow->fetch(PDO::FETCH_ASSOC);
        if(!$fr) json_resp(false, ['error'=>'File không tồn tại'], 404);

        $dest = folder_dir($pdo, $project_id, intval($fr['folder_id'])) . DIRECTORY_SEPARATOR . $fr['filename'];
        $ext = pathinfo($fr['filename'], PATHINFO_EXTENSION);
        $base = pathinfo($fr['filename'], PATHINFO_FILENAME);
        $from = old_versions_dir($pdo, $project_id) . DIRECTORY_SEPARATOR . $base . "_v" . $version . ($ext?(".".$ext):"");

        if(!is_file($from)){
            // fallback to DB-stored path
            $stmt = $pdo->prepare("SELECT storage_path FROM file_versions WHERE file_id=? AND version=?");
            $stmt->execute([$file_id, $version]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row) json_resp(false, ['error'=>'Không tìm thấy phiên bản cần khôi phục'], 404);
            $from = to_abs($row['storage_path']);
            if(!is_file($from)) json_resp(false, ['error'=>'Không thể khôi phục tệp (thiếu file nguồn)'], 404);
        }
        if(!@copy($from, $dest)) json_resp(false, ['error'=>'Không thể khôi phục tệp'], 500);
        // set current version pointer
        try { $pdo->prepare("UPDATE project_files SET current_version=? WHERE id=?")->execute([$version, $file_id]); } catch (Throwable $e) {}
        json_resp(true, ['restored'=>basename($dest)]);
    }
if($action==='get_versions'){
        $file_id = isset($_GET['file_id'])?intval($_GET['file_id']): (isset($_POST['file_id'])?intval($_POST['file_id']):0);
        if(!$file_id){ json_resp(true, ['versions'=>[]]); }
        $stmt = $pdo->prepare("SELECT version, storage_path, size_bytes, uploaded_by, created_at FROM file_versions WHERE file_id=? ORDER BY version DESC");
        $stmt->execute([$file_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_resp(true, ['versions'=>$rows]);
    }
if($action==='toggle_important'){
        if(!can_write($pdo, $project_id, $user_id)) json_resp(false, ['error'=>'Bạn không có quyền thay đổi.'], 403);
        $file_id = intval(require_param('file_id'));
        $stmt = $pdo->prepare("UPDATE project_files SET is_important = 1 - is_important WHERE id=? AND project_id=?");
        $stmt->execute([$file_id, $project_id]);
        json_resp(true);
    }

    // SET TAG
    if($action==='set_tag'){
        if(!can_write($pdo, $project_id, $user_id)) json_resp(false, ['error'=>'Bạn không có quyền thay đổi tag.'], 403);
        $file_id = intval(require_param('file_id'));
        $tag = require_param('tag');
        $allowed = ['WIP','Shared','Published','Archived'];
        if(!in_array($tag, $allowed, true)) json_resp(false, ['error'=>'Tag không hợp lệ'], 422);
        $stmt = $pdo->prepare("UPDATE project_files SET tag=? WHERE id=? AND project_id=?");
        $stmt->execute([$tag, $file_id, $project_id]);
        json_resp(true);
    }

    // UPLOAD (multi)
    
    // UPLOAD (keep original filename in main folder; archive copy as base_vN in old_version)
    if($action==='upload' && $_SERVER['REQUEST_METHOD']==='POST'){
        if(!can_write($pdo, $project_id, $user_id)) json_resp(false, ['error'=>'Bạn không có quyền upload.'], 403);
        $folder_id = intval($_POST['folder_id'] ?? $root_folder_id);
        if(!isset($_FILES['files'])) json_resp(false, ['error'=>'Không có tệp nào được chọn'], 400);
        $dest_dir = folder_dir($pdo, $project_id, $folder_id); ensure_dir($dest_dir);
        $oldDir = old_versions_dir($pdo, $project_id); ensure_dir($oldDir);

        $uploaded = [];
        foreach($_FILES['files']['name'] as $i=>$origName){
            $tmp = $_FILES['files']['tmp_name'][$i];
            $err = $_FILES['files']['error'][$i];
            $size = $_FILES['files']['size'][$i];
            if($err !== UPLOAD_ERR_OK){
                $uploaded[] = ['name'=>$origName, 'ok'=>false, 'error'=>"Upload error $err"];
                continue;
            }
            $origName = trim($origName);
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            $baseName = pathinfo($origName, PATHINFO_FILENAME);

            // ensure DB row
            $stmt = $pdo->prepare("SELECT id FROM project_files WHERE project_id=? AND folder_id=? AND filename=? AND is_deleted=0 LIMIT 1");
            $stmt->execute([$project_id, $folder_id, $origName]);
            $file_id = intval($stmt->fetchColumn());
            if(!$file_id){
                $pdo->prepare("INSERT INTO project_files (project_id, folder_id, filename, created_by) VALUES (?,?,?,?)")
                    ->execute([$project_id, $folder_id, $origName, $user_id]);
                $file_id = intval($pdo->lastInsertId());
            }
            // next version number
            $vi = latest_version_info($pdo, $file_id);
            
            $__open_url = 'partials/file_preview.php?id='.(int)$r['id'];
            $r['open_url'] = $__open_url;
$next = ($vi ? intval($vi['version']) : 0) + 1;

            // 1) write MAIN (overwrite same name)
            $toMain = $dest_dir . DIRECTORY_SEPARATOR . $origName;
            if(!@move_uploaded_file($tmp, $toMain)){
                @copy($tmp, $toMain);
            }

            // 2) write ARCHIVE copy to old_version: base_vN.ext
            $archivedName = $baseName . "_v" . $next . ($ext?(".".$ext):"");
            $toArchive = $oldDir . DIRECTORY_SEPARATOR . $archivedName;
            @copy($toMain, $toArchive);

            // record version row pointing to archived copy
            $rel = to_rel($toArchive);
            $pdo->prepare("INSERT INTO file_versions (file_id, version, storage_path, size_bytes, uploaded_by) VALUES (?,?,?,?,?)")
                ->execute([$file_id, $next, $rel, @filesize($toArchive) ?: $size, $user_id]);
            // set current version pointer
            try { $pdo->prepare("UPDATE project_files SET current_version=? WHERE id=?")->execute([$next, $file_id]); } catch (Throwable $e) {}

            $uploaded[] = ['file_id'=>$file_id, 'version'=>$next, 'name'=>$origName];
        }
        json_resp(true, ['uploaded'=>$uploaded]);
    }

    
    // DELETE (files & folders) - physical and DB
    if($action==='delete' && $_SERVER['REQUEST_METHOD']==='POST'){
        if(!can_write($pdo, $project_id, $user_id)) json_resp(false, ['error'=>'Bạn không có quyền xóa.'], 403);
        $confirm = $_POST['confirm'] ?? '';
        if($confirm !== 'DELETE') json_resp(false, ['error'=>'Bạn phải nhập "DELETE" để xác nhận'], 422);
        $items = isset($_POST['items']) ? $_POST['items'] : '[]';
        $list = json_decode($items, true) ?: [];

        $deleted = ['files'=>0,'folders'=>0];

        $delFile = function($fid) use ($pdo, $project_id, &$deleted){
            $frow = $pdo->prepare("SELECT folder_id, filename FROM project_files WHERE id=?");
            $frow->execute([$fid]);
            $fr = $frow->fetch(PDO::FETCH_ASSOC);
            if($fr){
                $main = folder_dir($pdo, $project_id, intval($fr['folder_id'])) . DIRECTORY_SEPARATOR . $fr['filename'];
                if(is_file($main)) @unlink($main);
                $ext = pathinfo($fr['filename'], PATHINFO_EXTENSION);
                $base = pathinfo($fr['filename'], PATHINFO_FILENAME);
                $old = old_versions_dir($pdo, $project_id);
                foreach(glob($old.DIRECTORY_SEPARATOR.$base."_v*".($ext?(".".$ext):"")) as $f){
                    if(is_file($f)) @unlink($f);
                }
            }
            $stmt = $pdo->prepare("SELECT storage_path FROM file_versions WHERE file_id=?");
            $stmt->execute([$fid]);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
                $full = to_abs($r['storage_path']); if(is_file($full)) @unlink($full);
            }
            $pdo->prepare("DELETE FROM file_versions WHERE file_id=?")->execute([$fid]);
            $pdo->prepare("DELETE FROM project_files WHERE id=?")->execute([$fid]);
            $deleted['files']++;
        };

        $delFolder = function($folder_id) use ($pdo, $project_id, &$deleted, &$delFile, &$delFolder){
            $stmt = $pdo->prepare("SELECT id FROM project_files WHERE folder_id=? AND project_id=?");
            $stmt->execute([$folder_id, $project_id]);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){ $delFile(intval($r['id'])); }
            $stmt2 = $pdo->prepare("SELECT id FROM project_folders WHERE parent_id=? AND project_id=?");
            $stmt2->execute([$folder_id, $project_id]);
            foreach($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r){ $delFolder(intval($r['id'])); }
            $dir = folder_dir($pdo, $project_id, $folder_id);
            if(is_dir($dir)) @rmdir($dir);
            $pdo->prepare("DELETE FROM project_folders WHERE id=?")->execute([$folder_id]);
            $deleted['folders']++;
        };

        foreach($list as $it){
            if(($it['type'] ?? '')==='file'){ $delFile(intval($it['id'])); }
            if(($it['type'] ?? '')==='folder'){ $delFolder(intval($it['id'])); }
        }
        json_resp(true, ['deleted'=>$deleted]);
    }


    // MOVE or COPY
    if($action==='move_copy'){
        if(!can_write($pdo, $project_id, $user_id)) json_resp(false, ['error'=>'Bạn không có quyền di chuyển/sao chép.'], 403);
        $dest_folder_id = intval(require_param('dest_folder_id'));
        $op = $_POST['op'] ?? 'move'; // 'move' or 'copy'
        $items = json_decode($_POST['items'] ?? '[]', true) ?: [];
        $dest_dir = folder_dir($pdo, $project_id, $dest_folder_id);
        if(!is_dir($dest_dir)) @mkdir($dest_dir, 0775, true);

        foreach($items as $it){
            if($it['type']==='file'){
                $fid = intval($it['id']);
                // fetch source file row
                $f = $pdo->prepare("SELECT id, folder_id, filename FROM project_files WHERE id=? AND project_id=?");
                $f->execute([$fid, $project_id]);
                $file = $f->fetch(PDO::FETCH_ASSOC);
                if(!$file) continue;
                // check exist at dest
                $stmt = $pdo->prepare("SELECT id FROM project_files WHERE project_id=? AND folder_id=? AND filename=? AND is_deleted=0 LIMIT 1");
                $stmt->execute([$project_id, $dest_folder_id, $file['filename']]);
                $dest_file_id = $stmt->fetchColumn();
                if(!$dest_file_id){
                    // create new file (for copy) or update folder (for move)
                    if($op==='copy'){
                        $pdo->prepare("INSERT INTO project_files (project_id, folder_id, filename, tag, is_important, is_deleted, created_by) SELECT project_id, ?, filename, tag, is_important, 0, ? FROM project_files WHERE id=?")
                            ->execute([$dest_folder_id, $user_id, $fid]);
                        $dest_file_id = intval($pdo->lastInsertId());
                        $nextVersion = 1;
                    } else {
                        // move: simply change folder unless conflict rules say reset version
                        $pdo->prepare("UPDATE project_files SET folder_id=? WHERE id=?")->execute([$dest_folder_id, $fid]);
                        $dest_file_id = $fid;
                        // no version change on move if unique name
                        continue;
                    }
                } else {
                    // conflict: create new version at dest
                    $vi = latest_version_info($pdo, $dest_file_id);
                    
            $__open_url = 'partials/file_preview.php?id='.(int)$r['id'];
            $r['open_url'] = $__open_url;
if($vi){ move_version_to_old($pdo, $project_id, $dest_file_id, $vi); }
                    $nextVersion = ($vi ? intval($vi['version']) : 0) + 1;
                }

                // copy bytes from latest version of source to dest new version
                $src_vi = latest_version_info($pdo, $fid);
                if($src_vi && is_file(path_join(__DIR__, '../../', $src_vi['storage_path']))){
                    $ext = pathinfo($file['filename'], PATHINFO_EXTENSION);
                    $baseNameOnly = pathinfo($file['filename'], PATHINFO_FILENAME);
                    $storedName = sanitize_filename($baseNameOnly) . "__v" . $nextVersion . ($ext?(".".$ext):"");
                    $toFull = path_join($dest_dir, $storedName);
                    @copy(path_join(__DIR__, '../../', $src_vi['storage_path']), $toFull);
                    $relPath = to_rel($toFull);
                    $pdo->prepare("INSERT INTO file_versions (file_id, version, storage_path, size_bytes, uploaded_by) VALUES (?,?,?,?,?)")
                        ->execute([$dest_file_id, $nextVersion, $relPath, intval($src_vi['size_bytes']), $user_id]);
                    $pdo->prepare("UPDATE project_files SET updated_at=NOW() WHERE id=?")->execute([$dest_file_id]);
                }
            }
            // TODO: handle folder copy/move (MVP: skip for brevity)
        }
        json_resp(true);
    }

    // DOWNLOAD (single or multi as zip)
    if($action==='download_one'){
        $fid = isset($_GET['file_id'])?intval($_GET['file_id']):0;
        if(!$fid){ json_resp(false, ['error'=>'Missing file_id'], 400); }
        // get main path
        $frow = $pdo->prepare("SELECT folder_id, filename FROM project_files WHERE id=? AND is_deleted=0");
        $frow->execute([$fid]);
        $fr = $frow->fetch(PDO::FETCH_ASSOC);
        if($fr){
            $mainFull = folder_dir($pdo, $project_id, intval($fr['folder_id'])) . DIRECTORY_SEPARATOR . $fr['filename'];
            if(is_file($mainFull)){
                while(ob_get_level()>0){ @ob_end_clean(); }
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="'.$fr['filename'].'"');
                header('Content-Length: '.filesize($mainFull));
                readfile($mainFull); exit;
            }
        }
        // fallback to latest archived version
        $vi = latest_version_info($pdo, $fid);
        
            $__open_url = 'partials/file_preview.php?id='.(int)$r['id'];
            $r['open_url'] = $__open_url;
if(!$vi){ json_resp(false, ['error'=>'File not found'], 404); }
        $full = to_abs($vi['storage_path']);
        if(!is_file($full)){ json_resp(false, ['error'=>'File not found'], 404); }
        $name = basename($full);
        while(ob_get_level()>0){ @ob_end_clean(); }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$name.'"');
        header('Content-Length: '.filesize($full));
        readfile($full); exit;
    }

    elseif ($action === 'download') {
    $raw = $_POST['items'] ?? '[]';
    $items = json_decode($raw, true);
    if (!is_array($items)) $items = [];

    if (empty($items)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'Không có mục nào được chọn']);
        exit;
    }

    if (!class_exists('ZipArchive')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'ZipArchive PHP extension chưa bật']);
        exit;
    }

    $getAllFilesInFolder = function($folderId) use ($pdo, &$getAllFilesInFolder) {
        $fileIds = [];
        $st = $pdo->prepare("SELECT id FROM project_files WHERE folder_id=?");
        $st->execute([$folderId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $fid) {
            $fileIds[] = (int)$fid;
        }
        $st = $pdo->prepare("SELECT id FROM file_folders WHERE parent_id=?");
        $st->execute([$folderId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $subId) {
            $fileIds = array_merge($fileIds, $getAllFilesInFolder((int)$subId));
        }
        return $fileIds;
    };

    $pickActiveVersion = function($fileId) use ($pdo) {
        $st = $pdo->prepare("SELECT filename, current_version FROM project_files WHERE id=? LIMIT 1");
        $st->execute([$fileId]);
        $pf = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pf) return null;

        $cur = (int)($pf['current_version'] ?? 0);
        if ($cur <= 0) {
            $st2 = $pdo->prepare("SELECT version FROM file_versions WHERE file_id=? ORDER BY version DESC LIMIT 1");
            $st2->execute([$fileId]);
            $cur = (int)($st2->fetchColumn() ?: 0);
            if ($cur <= 0) return null;
        }

        $stm = $pdo->prepare("SELECT storage_path, size_bytes FROM file_versions WHERE file_id=? AND version=? LIMIT 1");
        $stm->execute([$fileId, $cur]);
        $v = $stm->fetch(PDO::FETCH_ASSOC);
        if (!$v) return null;

        return [
            'abs_path' => $v['storage_path'],
            'download_name' => $pf['filename'],
            'size_bytes' => (int)$v['size_bytes'],
        ];
    };

    $fileRecords = [];
    foreach ($items as $it) {
        $type = $it['type'] ?? '';
        $id   = (int)($it['id'] ?? 0);
        if ($id <= 0) continue;

        if ($type === 'file') {
            $fileRecords[] = $id;
        } elseif ($type === 'folder') {
            $fileRecords = array_merge($fileRecords, $getAllFilesInFolder($id));
        }
    }
    $fileRecords = array_values(array_unique(array_map('intval', $fileRecords)));

    if (empty($fileRecords)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'Không có tệp nào trong mục đã chọn']);
        exit;
    }

    $bundleName = 'CDE_Files_' . date('Ymd_His') . '.zip';
    $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $bundleName;
    @unlink($zipPath);
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'Không tạo được file zip']);
        exit;
    }

    foreach ($fileRecords as $fid) {
        $info = $pickActiveVersion($fid);
        if (!$info) continue;
        if (is_readable($info['abs_path'])) {
            $zip->addFile($info['abs_path'], $info['download_name']);
        }
    }
    $zip->close();

    if (!is_file($zipPath)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'Zip thất bại']);
        exit;
    }

    @ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.$bundleName.'"; filename*=UTF-8\'\'' . rawurlencode($bundleName));
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}
    } catch (Throwable $e) {
        json_resp(false, ['error'=>'Server error', 'detail'=>$e->getMessage()], 500);
    }
}

// ---- HTML RENDER (UI) ----
?>

<?php
$is_member = $project_id ? is_project_member($pdo, $project_id, $user_id) : false;
$can_admin = can_write($pdo, $project_id, $user_id);
if(!$is_member){
  echo '<div class="files-denied">⚠️ Bạn không có quyền truy cập Tab Files của dự án này (chỉ thành viên trong dự án mới được xem/cập nhật).</div>';
  return;
}
?>
<link rel="stylesheet" href="/assets/css/project_tab_files.css?v=<?php $p=__DIR__ . '/../../assets/css/project_tab_files.css'; echo @file_exists($p)?@filemtime($p):time(); ?>">
<div id="files-tab" class="files-tab <?php echo $can_admin ? 'can-admin' : ''; ?>">
  <div class="ft-toolbar">
    <div class="ft-search">
      <i class="fas fa-search"></i>
      <input type="text" id="ft-search-input" placeholder="Search *abc, abc*, .pdf …">
      <select id="ft-filter-tag">
        <option value="">All tags</option>
        <option>WIP</option>
        <option>Shared</option>
        <option>Published</option>
        <option>Archived</option>
      </select>
      <label class="ft-chk"><input type="checkbox" id="ft-important-only"> Important</label>
    </div>
    <div class="ft-actions">
      <button id="ft-upload-btn" class="btn primary"><i class="fas fa-upload"></i> Upload</button>
      <button id="ft-create-folder-btn" class="btn"><i class="fas fa-folder-plus"></i> Create folder</button>
      <button id="ft-delete-btn" class="btn danger"><i class="fas fa-trash-alt"></i> Delete</button>
      <button id="ft-download-btn" class="btn"><i class="fas fa-download"></i> Download</button>
      <input type="file" id="ft-file-input" multiple style="display:none" />
    </div>
  </div>

  <div class="ft-body">
    <div class="ft-left">
      <div class="ft-tree" id="ft-tree"></div>
    </div>
    <div class="ft-right">
      <table class="ft-table" id="ft-table">
        <thead>
          <tr>
            <th class="sel"><input type="checkbox" id="ft-select-all"></th>
            <th>!</th>
            <th>Name</th>
            <th>Tag</th>
            <th>Version</th>
            <th>Size</th>
            <th>Updated</th>
            <th>Uploader</th>
            <th class="act">Action</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <!-- Modals -->
  <div class="ft-modal" id="ft-upload-modal" hidden>
    <div class="ft-modal-dialog">
      <div class="ft-modal-header">
        <h3>Upload files</h3>
        <button class="icon close" data-close>&times;</button>
      </div>
      <div class="ft-modal-body">
        <div class="dropzone" id="ft-dropzone">Drop files here or click to select.</div>
        <ul class="ft-upload-list" id="ft-upload-list"></ul>
      </div>
      <div class="ft-modal-footer">
        <button class="btn" data-close>Close</button>
        <button class="btn primary" id="ft-start-upload">Start upload</button>
      </div>
    </div>
  </div>

  <div class="ft-modal" id="ft-create-folder-modal" hidden>
    <div class="ft-modal-dialog small">
      <div class="ft-modal-header">
        <h3>Create folder</h3>
        <button class="icon close" data-close>&times;</button>
      </div>
      <div class="ft-modal-body">
        <input type="text" id="ft-new-folder-name" placeholder="Folder name">
      </div>
      <div class="ft-modal-footer">
        <button class="btn" data-close>Cancel</button>
        <button class="btn primary" id="ft-create-folder-confirm">Create</button>
      </div>
    </div>
  </div>

  <div class="ft-modal" id="ft-delete-modal" hidden>
    <div class="ft-modal-dialog small">
      <div class="ft-modal-header">
        <h3>Confirm delete</h3>
        <button class="icon close" data-close>&times;</button>
      </div>
      <div class="ft-modal-body">
        <p>Type <b>DELETE</b> to confirm deleting the selected items.</p>
        <input type="text" id="ft-delete-confirm" placeholder="DELETE">
      </div>
      <div class="ft-modal-footer">
        <button class="btn" data-close>Cancel</button>
        <button class="btn danger" id="ft-delete-confirm-btn">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
  window.CDE_FILES = {
    projectId: <?php echo json_encode($project_id); ?>,
    ajaxUrl: "/pages/partials/project_tab_files.php?ajax=1&project_id=" + encodeURIComponent(<?php echo json_encode($project_id); ?>)
  };
</script>
<script src="/assets/js/project_tab_files.js?v=<?php $p=__DIR__ . '/../../assets/js/project_tab_files.js'; echo @file_exists($p)?@filemtime($p):time(); ?>"></script>