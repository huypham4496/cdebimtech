<?php
/**
 * Meetings Tab (PDO-only, legacy-friendly)
 * File: pages/partials/project_tab_meetings.php
 * 
 * - Expects $pdo (PDO) provided by project_view.php (which already included config.php).
 * - This file DOES NOT include config.php.
 * - Only project members may view; only role='control' can create/update/delete, and only their own meetings.
 * - All user-facing strings here are in English per request.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* ---------- Resolve $pdo ---------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (!isset($_GET['ajax']) && !isset($_POST['ajax'])) {
        echo '<div class="cde-alert cde-alert-danger">Database connection is not available. Expected $pdo (PDO).</div>';
        return;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok'=>false,'error'=>'DB_NOT_AVAILABLE','message'=>'Database connection is not available. Expected $pdo (PDO).'));
        exit;
    }
}

/* ---------- Robust context detection ---------- */
function _pick($arr) {
    foreach ($arr as $v) { if (isset($v) && $v !== '' && $v !== null) return $v; }
    return null;
}

$project_id = null;
$project_id = _pick(array(
    isset($project_id)?$project_id:null,
    isset($project) && is_array($project) && isset($project['id']) ? $project['id'] : null,
    isset($proj) && is_array($proj) && isset($proj['id']) ? $proj['id'] : null,
    isset($current_project) && is_array($current_project) && isset($current_project['id']) ? $current_project['id'] : null,
    isset($currentProject) && is_array($currentProject) && isset($currentProject['id']) ? $currentProject['id'] : null,
    isset($_GET['project_id']) ? $_GET['project_id'] : null,
    isset($_POST['project_id']) ? $_POST['project_id'] : null,
    // Common pattern: project_view.php?id=...
    isset($_GET['id']) ? $_GET['id'] : null
));
$project_id = intval($project_id);

$current_user_id = null;
$current_user_id = _pick(array(
    isset($current_user_id)?$current_user_id:null,
    isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
    isset($_SESSION['id']) ? $_SESSION['id'] : null,
    isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null,
    isset($_SESSION['auth']) && is_array($_SESSION['auth']) && isset($_SESSION['auth']['user_id']) ? $_SESSION['auth']['user_id'] : null,
    isset($_SESSION['auth']) && is_array($_SESSION['auth']) && isset($_SESSION['auth']['id']) ? $_SESSION['auth']['id'] : null,
    isset($user) && is_array($user) && isset($user['id']) ? $user['id'] : null,
    isset($authUser) && is_array($authUser) && isset($authUser['id']) ? $authUser['id'] : null,
    isset($_GET['user_id']) ? $_GET['user_id'] : null,
    isset($_POST['user_id']) ? $_POST['user_id'] : null
));
$current_user_id = intval($current_user_id);

/* -------------------- Non-AJAX render -------------------- */
if (!isset($_GET['ajax']) && !isset($_POST['ajax'])) {
    ?>
    <link rel="stylesheet" href="../assets/css/project_tab_meetings.css?v=10" />
    <div id="cde-meetings"
         class="cde-meetings"
         data-project-id="<?php echo htmlspecialchars($project_id); ?>"
         data-user-id="<?php echo htmlspecialchars($current_user_id); ?>"
         data-can-control="<?php echo htmlspecialchars(user_can_control($pdo, $project_id, $current_user_id) ? '1' : '0'); ?>">
        <?php if (!$project_id || !$current_user_id): ?>
            <div class="cde-alert cde-alert-warning">Missing project or user context. Please open this tab from the Project page.</div>
        <?php elseif (!user_in_project($pdo, $project_id, $current_user_id)): ?>
            <div class="cde-alert cde-alert-danger">Access denied: you are not a member of this project.</div>
        <?php else: ?>
            <div class="cde-meetings__header">
                <div class="cde-meetings__title">
                    <h3>Meetings</h3>
                    <p class="muted">Manage all meetings for this project</p>
                </div>
                <div class="cde-meetings__actions">
                    <div class="search-group">
                        <input type="text" id="mt-search-text" placeholder="Search by title..." />
                        <input type="date" id="mt-search-date" />
                        <button class="btn btn-outline" id="mt-btn-search" title="Search">
                            <span class="icon">🔎</span><span>Search</span>
                        </button>
                        <button class="btn btn-ghost" id="mt-btn-clear">Clear</button>
                    </div>
                    <button class="btn btn-primary" id="mt-btn-create" <?php echo user_can_control($pdo, $project_id, $current_user_id) ? '' : 'disabled'; ?>>
                        <span class="icon">＋</span>Create meeting
                    </button>
                </div>
            </div>

            <div class="cde-card">
                <div class="table-responsive">
                    <table class="cde-table" id="mt-table">
                        <thead>
                            <tr>
                                <th>Meeting title</th>
                                <th>Creator</th>
                                <th>Created at</th>
                                <th>Location</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="mt-tbody">
                            <tr><td colspan="5" class="txt-center muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal: Create/Update (hidden by default) -->
            <div class="cde-modal" id="mt-modal" hidden>
                <div class="cde-modal__dialog">
                    <div class="cde-modal__header">
                        <h4 id="mt-modal-title">Create meeting</h4>
                        <button class="icon-btn" data-close="mt-modal">✕</button>
                    </div>
                    <div class="cde-modal__body">
                        <form id="mt-form">
                            <input type="hidden" name="id" id="mt-id" />
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Meeting title <span class="req">*</span></label>
                                    <input type="text" name="title" id="mt-title" required />
                                </div>
                                <div class="form-group">
                                    <label>Start time</label>
                                    <input type="datetime-local" name="start_at" id="mt-start-at" />
                                </div>
                            </div>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Location</label>
                                    <input type="text" name="location" id="mt-location" placeholder="Room, address..." />
                                </div>
                                <div class="form-group">
                                    <label>Online link</label>
                                    <input type="url" name="online_link" id="mt-online-link" placeholder="https://..." />
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Short description</label>
                                <textarea name="short_desc" id="mt-short-desc" rows="3" placeholder="Objectives, key points..."></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="cde-modal__footer">
                        <button class="btn btn-ghost" data-close="mt-modal">Cancel</button>
                        <button class="btn btn-primary" id="mt-btn-save">Save</button>
                    </div>
                </div>
            </div>

            <!-- Drawer: Detail (hidden by default) -->
            <div class="cde-drawer" id="mt-detail" hidden>
                <div class="cde-drawer__panel">
                    <div class="cde-drawer__header">
                        <div>
                            <h4 id="dt-title">Meeting details</h4>
                            <p class="muted" id="dt-meta"></p>
                        </div>
                        <div class="right">
                            <button class="btn btn-outline" id="dt-btn-export">Export (Word)</button>
                            <button class="icon-btn" id="dt-btn-close">✕</button>
                        </div>
                    </div>
                    <div class="cde-drawer__body">
                        <section class="dt-section">
                            <h5>KV1. Summary</h5>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Start time</label>
                                    <input type="datetime-local" id="dt-start-at" />
                                </div>
                                <div class="form-group">
                                    <label>Location</label>
                                    <input type="text" id="dt-location" />
                                </div>
                                <div class="form-group">
                                    <label>Online link</label>
                                    <input type="url" id="dt-online-link" placeholder="https://..." />
                                </div>
                                <div class="form-group">
                                    <label>Short description</label>
                                    <input type="text" id="dt-short-desc" />
                                </div>
                            </div>
                        </section>

                        <section class="dt-section">
                            <h5>KV2. Content</h5>
                            <div class="editor-toolbar" id="dt-editor-toolbar">
                                <button data-cmd="bold" title="Bold"><b>B</b></button>
                                <button data-cmd="italic" title="Italic"><i>I</i></button>
                                <button data-cmd="underline" title="Underline"><u>U</u></button>
                                <button data-cmd="strikeThrough" title="Strike">S</button>
                                <button data-cmd="insertUnorderedList" title="Bullet">•</button>
                                <button data-cmd="insertOrderedList" title="Number">1.</button>
                                <select id="dt-font-size">
                                    <option value="">Font size</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                </select>
                                <input type="color" id="dt-forecolor" title="Text color" />
                                <button data-cmd="formatBlock" data-value="h1">H1</button>
                                <button data-cmd="formatBlock" data-value="h2">H2</button>
                                <button data-cmd="formatBlock" data-value="p">P</button>
                                <button id="dt-insert-table" title="Insert table">▦</button>
                            </div>
                            <div id="dt-editor" class="rich-editor" contenteditable="true"></div>
                            <div class="right mt-8">
                                <button class="btn btn-primary" id="dt-btn-save-content">Save content</button>
                            </div>
                        </section>

                        <section class="dt-section">
                            <h5>KV3. Participants</h5>
                            <div class="grid-2">
                                <div>
                                    <label>Project members</label>
                                    <div id="dt-project-members" class="people-list">Loading...</div>
                                </div>
                                <div>
                                    <label>External participants (one per line: name, email, phone)</label>
                                    <textarea id="dt-external" rows="6" placeholder="One person per line"></textarea>
                                </div>
                            </div>
                            <div class="right mt-8">
                                <button class="btn btn-outline" id="dt-btn-notify">Save & Notify</button>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="../assets/js/project_tab_meetings.js?v=10"></script>
    <?php
    return;
}

/* -------------------- AJAX handling -------------------- */
header('Content-Type: application/json; charset=utf-8');

// Re-resolve context again for AJAX (also accept ?id=)
if (!$project_id) {
    $project_id = isset($_REQUEST['project_id']) ? intval($_REQUEST['project_id']) : 0;
    if (!$project_id && isset($_REQUEST['id'])) $project_id = intval($_REQUEST['id']);
}
if (!$current_user_id) {
    $current_user_id = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0;
    if (!$current_user_id && isset($_SESSION['user_id'])) $current_user_id = intval($_SESSION['user_id']);
}

if (!$project_id || !$current_user_id) {
    echo json_encode(array('ok'=>false,'error'=>'MISSING_CONTEXT','message'=>'Missing project or user context.')); exit;
}
if (!user_in_project($pdo, $project_id, $current_user_id)) {
    echo json_encode(array('ok'=>false,'error'=>'FORBIDDEN','message'=>'Only project members can access this tab.')); exit;
}

// Ensure tables exist
bootstrap_tables($pdo);

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'list';

switch ($action) {
    case 'list':
        $q = isset($_REQUEST['q']) ? trim($_REQUEST['q']) : '';
        $date = isset($_REQUEST['date']) ? $_REQUEST['date'] : '';
        $where = "WHERE m.project_id = :pid";
        $params = array(':pid'=>$project_id);
        if ($q !== '') { $where .= " AND (m.title LIKE :q OR m.short_desc LIKE :q)"; $params[':q'] = "%".$q."%"; }
        if ($date !== '') { $where .= " AND DATE(m.created_at) = :d"; $params[':d'] = $date; }
        $sql = "SELECT m.*, CONCAT(u.first_name,' ',u.last_name) AS creator_name
                FROM project_meetings m
                LEFT JOIN users u ON u.id=m.created_by
                $where
                ORDER BY m.created_at DESC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $items = $st->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array('ok'=>true,'items'=>$items,'can_control'=>user_can_control($pdo,$project_id,$current_user_id),'user_id'=>$current_user_id));
        break;

    case 'create':
        if (!user_can_control($pdo, $project_id, $current_user_id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only control role can create meetings.')); break; }
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        if ($title === '') { echo json_encode(array('ok'=>false,'error'=>'VALIDATION','message'=>'Meeting title is required.')); break; }
        $short = isset($_POST['short_desc']) ? trim($_POST['short_desc']) : '';
        $start = isset($_POST['start_at']) ? $_POST['start_at'] : null;
        $loc   = isset($_POST['location']) ? trim($_POST['location']) : '';
        $url   = isset($_POST['online_link']) ? trim($_POST['online_link']) : '';
        $st = $pdo->prepare("INSERT INTO project_meetings(project_id,title,short_desc,start_at,location,online_link,created_by) VALUES (:pid,:t,:s,:start,:loc,:url,:uid)");
        $ok = $st->execute(array(':pid'=>$project_id,':t'=>$title,':s'=>$short,':start'=>$start? $start:null,':loc'=>$loc,':url'=>$url,':uid'=>$current_user_id));
        echo json_encode(array('ok'=>$ok,'id'=>$pdo->lastInsertId()));
        break;

    case 'get':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $st = $pdo->prepare("SELECT m.*, CONCAT(u.first_name,' ',u.last_name) AS creator_name FROM project_meetings m LEFT JOIN users u ON u.id=m.created_by WHERE m.id=:id AND m.project_id=:pid");
        $st->execute(array(':id'=>$id, ':pid'=>$project_id));
        $meeting = $st->fetch(PDO::FETCH_ASSOC);
        if (!$meeting) { echo json_encode(array('ok'=>false,'error'=>'NOT_FOUND')); break; }
        $st = $pdo->prepare("SELECT content FROM project_meeting_contents WHERE meeting_id=:id");
        $st->execute(array(':id'=>$id));
        $content = $st->fetchColumn(); if ($content === false) $content = '';
        $st = $pdo->prepare("SELECT * FROM project_meeting_participants WHERE meeting_id=:id ORDER BY id");
        $st->execute(array(':id'=>$id));
        $parts = $st->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array('ok'=>true,'meeting'=>$meeting,'content'=>$content,'participants'=>$parts,'can_control'=>user_can_control($pdo,$project_id,$current_user_id),'creator_id'=>intval($meeting['created_by']),'user_id'=>$current_user_id));
        break;

    case 'update':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!is_owner_and_control($pdo,$project_id,$current_user_id,$id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only the creator with control role can update.')); break; }
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        if ($title === '') { echo json_encode(array('ok'=>false,'error'=>'VALIDATION','message'=>'Meeting title is required.')); break; }
        $short = isset($_POST['short_desc']) ? trim($_POST['short_desc']) : '';
        $start = isset($_POST['start_at']) ? $_POST['start_at'] : null;
        $loc   = isset($_POST['location']) ? trim($_POST['location']) : '';
        $url   = isset($_POST['online_link']) ? trim($_POST['online_link']) : '';
        $st = $pdo->prepare("UPDATE project_meetings SET title=:t, short_desc=:s, start_at=:start, location=:loc, online_link=:url, updated_at=NOW() WHERE id=:id AND project_id=:pid");
        $ok = $st->execute(array(':t'=>$title,':s'=>$short,':start'=>$start? $start:null,':loc'=>$loc,':url'=>$url,':id'=>$id,':pid'=>$project_id));
        echo json_encode(array('ok'=>$ok));
        break;

    case 'delete':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!is_owner_and_control($pdo,$project_id,$current_user_id,$id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only the creator with control role can delete.')); break; }
        $pdo->prepare("DELETE FROM project_meeting_participants WHERE meeting_id=:id")->execute(array(':id'=>$id));
        $pdo->prepare("DELETE FROM project_meeting_contents WHERE meeting_id=:id")->execute(array(':id'=>$id));
        $ok = $pdo->prepare("DELETE FROM project_meetings WHERE id=:id AND project_id=:pid")->execute(array(':id'=>$id,':pid'=>$project_id));
        echo json_encode(array('ok'=>$ok));
        break;

    case 'members':
        $st = $pdo->prepare("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS name
                             FROM project_group_members pgm
                             JOIN users u ON u.id=pgm.user_id
                             WHERE pgm.project_id=:pid
                             ORDER BY name");
        $st->execute(array(':pid'=>$project_id));
        echo json_encode(array('ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC)));
        break;

    case 'save_content':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!is_owner_and_control($pdo,$project_id,$current_user_id,$id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only the creator with control role can save content.')); break; }
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $sql = "INSERT INTO project_meeting_contents (meeting_id, content, updated_by, updated_at)
                VALUES (:id,:c,:uid,NOW())
                ON DUPLICATE KEY UPDATE content=VALUES(content), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)";
        $ok = $pdo->prepare($sql)->execute(array(':id'=>$id,':c'=>$content,':uid'=>$current_user_id));
        echo json_encode(array('ok'=>$ok));
        break;

    case 'save_participants':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!is_owner_and_control($pdo,$project_id,$current_user_id,$id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only the creator with control role can save participants.')); break; }
        $internalJson = isset($_POST['internal']) ? $_POST['internal'] : '[]';
        $externalText = isset($_POST['external']) ? $_POST['external'] : '';
        $internals = json_decode($internalJson, true);
        if (!is_array($internals)) $internals = array();
        $pdo->prepare("DELETE FROM project_meeting_participants WHERE meeting_id=:id")->execute(array(':id'=>$id));
        if (!empty($internals)) {
            $ins = $pdo->prepare("INSERT INTO project_meeting_participants (meeting_id, user_id, is_internal) VALUES (:mid,:uid,1)");
            foreach ($internals as $uid2) {
                $ins->execute(array(':mid'=>$id,':uid'=>intval($uid2)));
            }
        }
        $lines = preg_split('/\r\n|\r|\n/', $externalText);
        $insE = $pdo->prepare("INSERT INTO project_meeting_participants (meeting_id, external_name, external_contact, is_internal) VALUES (:mid,:name,:contact,0)");
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln !== '') { $insE->execute(array(':mid'=>$id,':name'=>$ln,':contact'=>$ln)); }
        }
        echo json_encode(array('ok'=>true));
        break;

    case 'notify':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!is_owner_and_control($pdo,$project_id,$current_user_id,$id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only the creator with control role can notify.')); break; }
        $msg = "Meeting #".$id." has been updated. Please check details.";
        $sql1 = "INSERT INTO project_meeting_notifications (meeting_id, sender_id, receiver_id, message)
                 SELECT :mid, :sid, p.user_id, :msg FROM project_meeting_participants p WHERE p.meeting_id=:mid AND p.is_internal=1 AND p.user_id IS NOT NULL";
        $pdo->prepare($sql1)->execute(array(':mid'=>$id,':sid'=>$current_user_id,':msg'=>$msg));
        $sql2 = "INSERT INTO project_meeting_notifications (meeting_id, sender_id, external_contact, message)
                 SELECT :mid, :sid, p.external_contact, :msg FROM project_meeting_participants p WHERE p.meeting_id=:mid AND p.is_internal=0 AND p.external_contact IS NOT NULL";
        $pdo->prepare($sql2)->execute(array(':mid'=>$id,':sid'=>$current_user_id,':msg'=>$msg));
        echo json_encode(array('ok'=>true,'message'=>'Notifications stored (simulation).'));
        break;

    default:
        echo json_encode(array('ok'=>false,'error'=>'UNKNOWN_ACTION'));
}

/* ---------- Helpers (legacy-friendly) ---------- */
function bootstrap_tables(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        short_desc VARCHAR(500) NULL,
        start_at DATETIME NULL,
        location VARCHAR(255) NULL,
        online_link VARCHAR(500) NULL,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS project_meeting_contents (
        meeting_id INT PRIMARY KEY,
        content MEDIUMTEXT NULL,
        updated_by INT NULL,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS project_meeting_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        user_id INT NULL,
        external_name VARCHAR(255) NULL,
        external_contact VARCHAR(255) NULL,
        is_internal TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS project_meeting_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        sender_id INT NOT NULL,
        receiver_id INT NULL,
        external_contact VARCHAR(255) NULL,
        message VARCHAR(500) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function user_in_project(PDO $pdo, $project_id, $user_id) {
    if (!$project_id || !$user_id) return false;
    $st = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=:p AND user_id=:u LIMIT 1");
    $st->execute(array(':p'=>$project_id, ':u'=>$user_id));
    return (bool)$st->fetchColumn();
}

function user_can_control(PDO $pdo, $project_id, $user_id) {
    if (!$project_id || !$user_id) return false;
    $st = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=:p AND user_id=:u AND role='control' LIMIT 1");
    $st->execute(array(':p'=>$project_id, ':u'=>$user_id));
    return (bool)$st->fetchColumn();
}

function is_owner_and_control(PDO $pdo, $project_id, $user_id, $meeting_id) {
    if (!$project_id || !$user_id || !$meeting_id) return false;
    $st = $pdo->prepare("SELECT created_by FROM project_meetings WHERE id=:id AND project_id=:pid");
    $st->execute(array(':id'=>$meeting_id, ':pid'=>$project_id));
    $creator = $st->fetchColumn();
    if (!$creator || intval($creator) !== intval($user_id)) return false;
    return user_can_control($pdo, $project_id, $user_id);
}
