<?php
/**
 * Meetings Tab (PDO-only, legacy-friendly)
 * - Uses $pdo provided by project_view.php (already included config.php)
 * - Does NOT include config.php on its own
 * - All messages are in English (as requested)
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* ---------- Guard: PDO presence ---------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (!isset($_GET['ajax']) && !isset($_POST['ajax'])) {
        echo '<div class="cde-alert cde-alert-danger">Database connection is not available. Expected $pdo (PDO).</div>';
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok'=>false,'error'=>'DB_NOT_AVAILABLE','message'=>'Database connection is not available. Expected $pdo (PDO).'));
    exit;
}

/* ---------- Context detection (legacy-friendly) ---------- */
$project_id = isset($project_id) ? intval($project_id) : 0;
if (!$project_id && isset($project) && is_array($project) && isset($project['id'])) $project_id = intval($project['id']);
if (!$project_id && isset($proj) && is_array($proj) && isset($proj['id'])) $project_id = intval($proj['id']);
if (!$project_id && isset($current_project) && is_array($current_project) && isset($current_project['id'])) $project_id = intval($current_project['id']);
if (!$project_id && isset($currentProject) && is_array($currentProject) && isset($currentProject['id'])) $project_id = intval($currentProject['id']);
if (!$project_id && isset($_GET['project_id'])) $project_id = intval($_GET['project_id']);
if (!$project_id && isset($_POST['project_id'])) $project_id = intval($_POST['project_id']);
if (!$project_id && isset($_GET['id'])) $project_id = intval($_GET['id']);

$current_user_id = 0;
if (isset($current_user_id)) $current_user_id = intval($current_user_id);
if (!$current_user_id && isset($_SESSION['user_id'])) $current_user_id = intval($_SESSION['user_id']);
if (!$current_user_id && isset($_SESSION['id'])) $current_user_id = intval($_SESSION['id']);
if (!$current_user_id && isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id'])) $current_user_id = intval($_SESSION['user']['id']);
if (!$current_user_id && isset($_SESSION['auth']) && is_array($_SESSION['auth'])) {
    if (isset($_SESSION['auth']['user_id'])) $current_user_id = intval($_SESSION['auth']['user_id']);
    if (!$current_user_id && isset($_SESSION['auth']['id'])) $current_user_id = intval($_SESSION['auth']['id']);
}
if (!$current_user_id && isset($user) && is_array($user) && isset($user['id'])) $current_user_id = intval($user['id']);
if (!$current_user_id && isset($authUser) && is_array($authUser) && isset($authUser['id'])) $current_user_id = intval($authUser['id']);
if (!$current_user_id && isset($_GET['user_id'])) $current_user_id = intval($_GET['user_id']);
if (!$current_user_id && isset($_POST['user_id'])) $current_user_id = intval($_POST['user_id']);

/* ---------- Bootstrap / migrations ---------- */
bootstrap_tables($pdo); // create if not exists
// Ensure essential columns exist to avoid "Unknown column" errors on existing DBs
ensure_meetings_columns($pdo);

/* ---------- Non-AJAX: render HTML ---------- */
if (!isset($_GET['ajax']) && !isset($_POST['ajax'])) {
    // Permission gates for page view
    if (!$project_id || !$current_user_id) {
        echo '<div class="cde-alert cde-alert-warning">Missing project or user context. Please open this tab from the Project page.</div>';
        return;
    }
    if (!user_in_project($pdo, $project_id, $current_user_id)) {
        echo '<div class="cde-alert cde-alert-danger">Access denied: you are not a member of this project.</div>';
        return;
    }
    $can_control = user_can_control($pdo, $project_id, $current_user_id);
    ?>
    <link rel="stylesheet" href="../assets/css/project_tab_meetings.css?v=13" />
    <div id="cde-meetings" class="cde-meetings"
        data-project-id="<?php echo htmlspecialchars($project_id); ?>"
        data-user-id="<?php echo htmlspecialchars($current_user_id); ?>"
        data-can-control="<?php echo $can_control ? '1' : '0'; ?>">
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
                <button class="btn btn-primary" id="mt-btn-create" <?php echo $can_control ? '' : 'disabled'; ?>>
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
                            <th>Start time</th><th>Online link</th><th>Location</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="mt-tbody">
                        <tr><td colspan="7" class="txt-center muted">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal (hidden by default) -->
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

        <!-- Drawer (hidden by default) -->
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
    </div>
    <script src="../assets/js/project_tab_meetings.js?v=13"></script>
    <?php
    return;
}

/* ---------- AJAX handling ---------- */
header('Content-Type: application/json; charset=utf-8');

if (!$project_id || !$current_user_id) {
    echo json_encode(array('ok'=>false,'error'=>'MISSING_CONTEXT','message'=>'Missing project or user context.')); exit;
}
if (!user_in_project($pdo, $project_id, $current_user_id)) {
    echo json_encode(array('ok'=>false,'error'=>'FORBIDDEN','message'=>'Only project members can access this tab.')); exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'list';

if ($action === 'list') {
    $q = isset($_REQUEST['q']) ? trim($_REQUEST['q']) : '';
    $date = isset($_REQUEST['date']) ? $_REQUEST['date'] : '';
    $params = array(':pid'=>$project_id);
    $where = "WHERE m.project_id = :pid";
    if ($q !== '') { $where .= " AND (m.title LIKE :q OR m.short_desc LIKE :q)"; $params[':q'] = '%'.$q.'%'; }
    if ($date !== '') { $where .= " AND DATE(m.created_at) = :d"; $params[':d'] = $date; }

    $creatorCol = get_meeting_creator_col($pdo); // usually 'created_by'
    if ($creatorCol) {
        $sql = "SELECT m.*, CONCAT(u.first_name,' ',u.last_name) AS creator_name, m.$creatorCol AS created_by
                FROM project_meetings m
                LEFT JOIN users u ON u.id = m.$creatorCol
                $where
                ORDER BY COALESCE(m.updated_at, m.created_at) DESC";
    } else {
        // No creator column — still list safely
        $sql = "SELECT m.*, NULL AS created_by, NULL AS creator_name
                FROM project_meetings m
                $where
                ORDER BY COALESCE(m.updated_at, m.created_at) DESC";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array('ok'=>true,'items'=>$items,'can_control'=>user_can_control($pdo,$project_id,$current_user_id),'user_id'=>$current_user_id));
    exit;
}

if ($action === 'create') {  if ($start_at === '') { echo json_encode(array('ok'=>false,'error'=>'VALIDATION','message'=>'Start time is required.')); exit; }
  $start_at = isset($_POST['start_at']) ? trim($_POST['start_at']) : '';

    if (!user_can_control($pdo, $project_id, $current_user_id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only control role can create meetings.')); exit; }
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    if ($title === '') { echo json_encode(array('ok'=>false,'error'=>'VALIDATION','message'=>'Meeting title is required.')); exit; }
    $short = isset($_POST['short_desc']) ? trim($_POST['short_desc']) : '';
    $start = isset($_POST['start_at']) ? $_POST['start_at'] : null;
    $loc   = isset($_POST['location']) ? trim($_POST['location']) : '';
    $url   = isset($_POST['online_link']) ? trim($_POST['online_link']) : '';

    $creatorCol = get_meeting_creator_col($pdo);
    if ($creatorCol) {
        $sql = "INSERT INTO project_meetings (project_id, title, short_desc, start_at, location, online_link, $creatorCol, created_at) VALUES (:pid,:t,:s,:st,:loc,:url,:uid,NOW())";
        $params = array(':pid'=>$project_id, ':t'=>$title, ':s'=>$short, ':st'=>$start, ':loc'=>$loc, ':url'=>$url, ':uid'=>$current_user_id);
    } else {
        $sql = "INSERT INTO project_meetings (project_id, title, short_desc, start_at, location, online_link, created_at) VALUES (:pid,:t,:s,:st,:loc,:url,NOW())";
        $params = array(':pid'=>$project_id, ':t'=>$title, ':s'=>$short, ':st'=>$start, ':loc'=>$loc, ':url'=>$url);
    }
    $ok = $pdo->prepare($sql)->execute($params);
    echo json_encode(array('ok'=>$ok,'id'=>$pdo->lastInsertId())); exit;
}

if ($action === 'get') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $creatorCol = get_meeting_creator_col($pdo);

    if ($creatorCol) {
        $sql = "SELECT m.*, CONCAT(u.first_name,' ',u.last_name) AS creator_name, m.$creatorCol AS created_by
                FROM project_meetings m
                LEFT JOIN users u ON u.id=m.$creatorCol
                WHERE m.id=:id AND m.project_id=:pid";
    } else {
        $sql = "SELECT m.*, NULL AS created_by, NULL AS creator_name
                FROM project_meetings m
                WHERE m.id=:id AND m.project_id=:pid";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':id'=>$id, ':pid'=>$project_id));
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$meeting) { echo json_encode(array('ok'=>false,'error'=>'NOT_FOUND')); exit; }

    $stmt = $pdo->prepare("SELECT
  m.id,
  m.title AS title,
  COALESCE(u.fullname, '') AS creator_name,
  m.created_at AS created_at,
  COALESCE(m.start_at, m.start_time) AS start_at,
  COALESCE(m.online_link, m.online_url) AS online_link,
  m.location AS location
FROM project_meetings m
LEFT JOIN users u ON u.id = COALESCE(m.created_by, m.creator_id)
WHERE m.project_id = ?
ORDER BY COALESCE(m.start_at, m.start_time, m.created_at) DESC, m.id DESC
LIMIT 1000");
    $stmt->execute(array(':id'=>$id));
    $content = $stmt->fetchColumn();
    if (!$content) $content = '';

    $stmt = $pdo->prepare("SELECT * FROM project_meeting_participants WHERE meeting_id=:id ORDER BY id");
    $stmt->execute(array(':id'=>$id));
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $creatorId = $creatorCol && isset($meeting[$creatorCol]) ? intval($meeting[$creatorCol]) : (isset($meeting['created_by'])?intval($meeting['created_by']):0);

    echo json_encode(array(
        'ok'=>true,
        'meeting'=>$meeting,
        'content'=>$content,
        'participants'=>$parts,
        'can_control'=>user_can_control($pdo,$project_id,$current_user_id),
        'creator_id'=>$creatorId,
        'user_id'=>$current_user_id
    ));
    exit;
}

if ($action === 'update') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!is_owner_and_control($pdo, $project_id, $current_user_id, $id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only the creator with control role can update.')); exit; }
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    if ($title === '') { echo json_encode(array('ok'=>false,'error'=>'VALIDATION','message'=>'Meeting title is required.')); exit; }
    $short = isset($_POST['short_desc']) ? trim($_POST['short_desc']) : '';
    $start = isset($_POST['start_at']) ? $_POST['start_at'] : null;
    $loc   = isset($_POST['location']) ? trim($_POST['location']) : '';
    $url   = isset($_POST['online_link']) ? trim($_POST['online_link']) : '';

    $sql = "UPDATE project_meetings SET title=:t, short_desc=:s, start_at=:st, location=:loc, online_link=:url, updated_at=NOW() WHERE id=:id AND project_id=:pid";
    $ok = $pdo->prepare($sql)->execute(array(':t'=>$title, ':s'=>$short, ':st'=>$start, ':loc'=>$loc, ':url'=>$url, ':id'=>$id, ':pid'=>$project_id));
    echo json_encode(array('ok'=>$ok)); exit;
}

if ($action === 'delete') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!is_owner_and_control($pdo, $project_id, $current_user_id, $id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only the creator with control role can delete.')); exit; }
    $pdo->prepare("DELETE FROM project_meeting_participants WHERE meeting_id=:id")->execute(array(':id'=>$id));
    $pdo->prepare("DELETE FROM project_meeting_contents WHERE meeting_id=:id")->execute(array(':id'=>$id));
    $ok = $pdo->prepare("DELETE FROM project_meetings WHERE id=:id AND project_id=:pid")->execute(array(':id'=>$id, ':pid'=>$project_id));
    echo json_encode(array('ok'=>$ok)); exit;
}

if ($action === 'members') {
    $sql = "SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS name
            FROM project_group_members pgm
            JOIN users u ON u.id=pgm.user_id
            WHERE pgm.project_id=:pid
            ORDER BY name";
    $st = $pdo->prepare($sql);
    $st->execute(array(':pid'=>$project_id));
    echo json_encode(array('ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC))); exit;
}

if ($action === 'save_content') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!is_owner_and_control($pdo, $project_id, $current_user_id, $id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only the creator with control role can save content.')); exit; }
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $sql = "INSERT INTO project_meeting_contents (meeting_id, content, updated_by, updated_at)
            VALUES (:id,:c,:uid,NOW())
            ON DUPLICATE KEY UPDATE content=VALUES(content), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)";
    $ok = $pdo->prepare($sql)->execute(array(':id'=>$id, ':c'=>$content, ':uid'=>$current_user_id));
    echo json_encode(array('ok'=>$ok)); exit;
}

if ($action === 'save_participants') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!is_owner_and_control($pdo, $project_id, $current_user_id, $id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only the creator with control role can save participants.')); exit; }
    $internalJson = isset($_POST['internal']) ? $_POST['internal'] : '[]';
    $externalText = isset($_POST['external']) ? $_POST['external'] : '';
    $internals = json_decode($internalJson, true);
    if (!is_array($internals)) $internals = array();

    $pdo->prepare("DELETE FROM project_meeting_participants WHERE meeting_id=:id")->execute(array(':id'=>$id));

    if (!empty($internals)) {
        $ins = $pdo->prepare("INSERT INTO project_meeting_participants (meeting_id, user_id, is_internal) VALUES (:mid,:uid,1)");
        foreach ($internals as $uid2) { $ins->execute(array(':mid'=>$id, ':uid'=>intval($uid2))); }
    }
    $lines = preg_split('/\r\n|\r|\n/', $externalText);
    $insE = $pdo->prepare("INSERT INTO project_meeting_participants (meeting_id, external_name, external_contact, is_internal) VALUES (:mid,:name,:contact,0)");
    foreach ($lines as $ln) {
        $ln = trim($ln);
        if ($ln !== '') { $insE->execute(array(':mid'=>$id, ':name'=>$ln, ':contact'=>$ln)); }
    }
    echo json_encode(array('ok'=>true)); exit;
}

if ($action === 'notify') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!is_owner_and_control($pdo, $project_id, $current_user_id, $id)) { echo json_encode(array('ok'=>false,'error'=>'NO_PERMISSION','message'=>'Only the creator with control role can notify.')); exit; }
    $msg = "Meeting #".$id." has been updated. Please check details.";
    $sql1 = "INSERT INTO project_meeting_notifications (meeting_id, sender_id, receiver_id, message)
             SELECT :mid, :sid, p.user_id, :msg
             FROM project_meeting_participants p
             WHERE p.meeting_id=:mid AND p.is_internal=1 AND p.user_id IS NOT NULL";
    $pdo->prepare($sql1)->execute(array(':mid'=>$id, ':sid'=>$current_user_id, ':msg'=>$msg));

    $sql2 = "INSERT INTO project_meeting_notifications (meeting_id, sender_id, external_contact, message)
             SELECT :mid, :sid, p.external_contact, :msg
             FROM project_meeting_participants p
             WHERE p.meeting_id=:mid AND p.is_internal=0 AND p.external_contact IS NOT NULL";
    $pdo->prepare($sql2)->execute(array(':mid'=>$id, ':sid'=>$current_user_id, ':msg'=>$msg));

    echo json_encode(array('ok'=>true,'message'=>'Notifications stored (simulation).')); exit;
}

/* ---------- Fallback ---------- */
echo json_encode(array('ok'=>false,'error'=>'UNKNOWN_ACTION')); exit;


/* ===================== Helpers ===================== */

function bootstrap_tables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        title VARCHAR(255) NOT NULL
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

function ensure_meetings_columns($pdo) {
    $cols = array(
        'short_desc' => "ADD COLUMN short_desc VARCHAR(500) NULL",
        'start_at'   => "ADD COLUMN start_at DATETIME NULL",
        'location'   => "ADD COLUMN location VARCHAR(255) NULL",
        'online_link'=> "ADD COLUMN online_link VARCHAR(500) NULL",
        'created_by' => "ADD COLUMN created_by INT NULL",
        'created_at' => "ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "ADD COLUMN updated_at DATETIME NULL"
    );
    foreach ($cols as $c => $ddl) {
        if (!column_exists($pdo, 'project_meetings', $c)) {
            $pdo->exec("ALTER TABLE project_meetings ".$ddl);
        }
    }
}

function column_exists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
        $stmt->execute(array(':t'=>$table, ':c'=>$column));
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        // Fallback: try DESCRIBE
        try {
            $rs = $pdo->query("DESCRIBE ".$table);
            while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['Field']) && $row['Field'] === $column) return true;
            }
        } catch (Exception $e2) {}
    }
    return false;
}

function get_meeting_creator_col($pdo) {
    $candidates = array('created_by','creator_id','user_id','owner_id','created_user_id','createdby');
    foreach ($candidates as $c) {
        if (column_exists($pdo, 'project_meetings', $c)) return $c;
    }
    return null;
}

function user_in_project($pdo, $project_id, $user_id) {
    if (!$project_id || !$user_id) return false;
    $st = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=:p AND user_id=:u LIMIT 1");
    $st->execute(array(':p'=>$project_id, ':u'=>$user_id));
    return $st->fetchColumn() ? true : false;
}

function user_can_control($pdo, $project_id, $user_id) {
    if (!$project_id || !$user_id) return false;
    $st = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=:p AND user_id=:u AND role='control' LIMIT 1");
    $st->execute(array(':p'=>$project_id, ':u'=>$user_id));
    return $st->fetchColumn() ? true : false;
}

function is_owner_and_control($pdo, $project_id, $user_id, $meeting_id) {
    if (!$project_id || !$user_id || !$meeting_id) return false;
    $creatorCol = get_meeting_creator_col($pdo);
    if ($creatorCol) {
        $st = $pdo->prepare("SELECT ".$creatorCol." FROM project_meetings WHERE id=:id AND project_id=:pid");
        $st->execute(array(':id'=>$meeting_id, ':pid'=>$project_id));
        $creator = $st->fetchColumn();
        if (!$creator || intval($creator) !== intval($user_id)) return false;
        return user_can_control($pdo, $project_id, $user_id);
    }
    // If there is no creator column, deny modifications (only listing is allowed)
    return false;
}
