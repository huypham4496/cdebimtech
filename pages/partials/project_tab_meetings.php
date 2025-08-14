<?php
// project_tab_meetings.php
// NOTE: This file is meant to be INCLUDED by project_view.php (which already includes config.php & starts session).
// All AJAX requests should call THIS file directly with ?ajax=1 to avoid header issues from parent output.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect DB connection coming from parent include (config.php in project_view.php)
$conn = isset($conn) ? $conn : (isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null);

// Current user and project
$current_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : (isset($GLOBALS['project_id']) ? intval($GLOBALS['project_id']) : 0);

// Utility: safe header set (only when this script is called directly for AJAX)
function maybe_set_json_header() {
    if (!headers_sent() && (isset($_GET['ajax']) || (isset($_POST['action']) && basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)))) {
        header('Content-Type: application/json; charset=utf-8');
    }
}

// Early return if this is an AJAX call
if ((isset($_GET['ajax']) || (isset($_POST['action']) && basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)))) {
    if (!$conn) {
        maybe_set_json_header();
        echo json_encode(['ok' => false, 'error' => 'DB_NOT_CONNECTED']);
        exit;
    }
    if (!$current_user_id) {
        maybe_set_json_header();
        echo json_encode(['ok' => false, 'error' => 'NOT_LOGGED_IN']);
        exit;
    }
    if (!$project_id) {
        maybe_set_json_header();
        echo json_encode(['ok' => false, 'error' => 'MISSING_PROJECT_ID']);
        exit;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->set_charset('utf8mb4');

    // Ensure tables exist (idempotent)
    $conn->query("CREATE TABLE IF NOT EXISTS project_meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        short_desc VARCHAR(500) DEFAULT NULL,
        online_link VARCHAR(500) DEFAULT NULL,
        location VARCHAR(255) DEFAULT NULL,
        start_time DATETIME DEFAULT NULL,
        detail MEDIUMTEXT DEFAULT NULL,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX (project_id),
        INDEX (created_by),
        INDEX (start_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS project_meeting_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        user_id INT DEFAULT NULL,       -- internal participant (users.id)
        external_name VARCHAR(255) DEFAULT NULL, -- external participant name/email/phone
        type ENUM('internal','external') NOT NULL DEFAULT 'internal',
        UNIQUE KEY uniq_internal (meeting_id, user_id),
        INDEX (meeting_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS project_meeting_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        meeting_id INT NOT NULL,
        sender_id INT NOT NULL,
        receiver_id INT DEFAULT NULL,      -- null means external receiver; see receiver_name/email
        receiver_name VARCHAR(255) DEFAULT NULL,
        receiver_email VARCHAR(255) DEFAULT NULL,
        message VARCHAR(500) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        INDEX (project_id),
        INDEX (meeting_id),
        INDEX (receiver_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Access helpers
    $stmt = $conn->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND user_id=? LIMIT 1");
    $stmt->bind_param("ii", $project_id, $current_user_id);
    $stmt->execute();
    $is_member = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$is_member) {
        maybe_set_json_header();
        echo json_encode(['ok' => false, 'error' => 'ACCESS_DENIED']);
        exit;
    }

    $stmt = $conn->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND user_id=? AND role='control' LIMIT 1");
    $stmt->bind_param("ii", $project_id, $current_user_id);
    $stmt->execute();
    $is_control = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    try {
        switch ($action) {
            case 'list':
                // Filters
                $kw = trim($_GET['q'] ?? '');
                $date = trim($_GET['date'] ?? '');

                $sql = "SELECT m.id, m.title, m.created_at, m.location, u.first_name, u.last_name
                        FROM project_meetings m
                        LEFT JOIN users u ON u.id = m.created_by
                        WHERE m.project_id = ?";
                $params = [$project_id];
                $types = "i";

                if ($kw !== '') {
                    $sql .= " AND (m.title LIKE CONCAT('%', ?, '%') OR m.short_desc LIKE CONCAT('%', ?, '%'))";
                    $params[] = $kw; $params[] = $kw; $types .= "ss";
                }
                if ($date !== '') {
                    $sql .= " AND DATE(m.created_at) = ?";
                    $params[] = $date; $types .= "s";
                }
                $sql .= " ORDER BY COALESCE(m.start_time, m.created_at) DESC";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                maybe_set_json_header();
                echo json_encode(['ok' => true, 'data' => $rows]);
                break;

            case 'get':
                $meeting_id = intval($_GET['id'] ?? 0);
                $stmt = $conn->prepare("SELECT m.*, CONCAT(u.first_name,' ',u.last_name) AS creator_name
                                        FROM project_meetings m
                                        LEFT JOIN users u ON u.id=m.created_by
                                        WHERE m.id=? AND m.project_id=?");
                $stmt->bind_param("ii", $meeting_id, $project_id);
                $stmt->execute();
                $meeting = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // participants
                $stmt = $conn->prepare("SELECT p.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, u.email 
                                        FROM project_meeting_participants p
                                        LEFT JOIN users u ON u.id=p.user_id
                                        WHERE p.meeting_id=? ORDER BY p.id");
                $stmt->bind_param("i", $meeting_id);
                $stmt->execute();
                $parts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                maybe_set_json_header();
                echo json_encode(['ok' => true, 'meeting' => $meeting, 'participants' => $parts, 'is_control' => $is_control, 'current_user_id' => $current_user_id]);
                break;

            case 'create':
                if (!$is_control) { maybe_set_json_header(); echo json_encode(['ok'=>false,'error'=>'NO_PERMISSION']); break; }
                $title = trim($_POST['title'] ?? '');
                $short_desc = trim($_POST['short_desc'] ?? '');
                $online_link = trim($_POST['online_link'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $start_time = trim($_POST['start_time'] ?? '');
                if ($title === '') { maybe_set_json_header(); echo json_encode(['ok'=>false,'error'=>'TITLE_REQUIRED']); break; }

                $stmt = $conn->prepare("INSERT INTO project_meetings (project_id,title,short_desc,online_link,location,start_time,created_by) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param("isssssi", $project_id, $title, $short_desc, $online_link, $location, $start_time, $current_user_id);
                $stmt->execute();
                $new_id = $stmt->insert_id;
                $stmt->close();

                maybe_set_json_header();
                echo json_encode(['ok'=>true,'id'=>$new_id]);
                break;

            case 'update':
                $meeting_id = intval($_POST['id'] ?? 0);

                // allow only if creator == current user and user is control
                $stmt = $conn->prepare("SELECT created_by FROM project_meetings WHERE id=? AND project_id=?");
                $stmt->bind_param("ii", $meeting_id, $project_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$row || !$is_control || intval($row['created_by']) !== $current_user_id) {
                    maybe_set_json_header(); echo json_encode(['ok'=>false,'error'=>'NO_PERMISSION']); break;
                }

                $title = trim($_POST['title'] ?? '');
                $short_desc = trim($_POST['short_desc'] ?? '');
                $online_link = trim($_POST['online_link'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $start_time = trim($_POST['start_time'] ?? '');
                $detail = trim($_POST['detail'] ?? '');

                $stmt = $conn->prepare("UPDATE project_meetings SET title=?, short_desc=?, online_link=?, location=?, start_time=?, detail=? WHERE id=? AND project_id=?");
                $stmt->bind_param("ssssssii", $title, $short_desc, $online_link, $location, $start_time, $detail, $meeting_id, $project_id);
                $stmt->execute();
                $stmt->close();

                maybe_set_json_header(); echo json_encode(['ok'=>true]); break;

            case 'delete':
                $meeting_id = intval($_POST['id'] ?? 0);
                // allow only if creator == current user and user is control
                $stmt = $conn->prepare("SELECT created_by FROM project_meetings WHERE id=? AND project_id=?");
                $stmt->bind_param("ii", $meeting_id, $project_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$row || !$is_control || intval($row['created_by']) !== $current_user_id) {
                    maybe_set_json_header(); echo json_encode(['ok'=>false,'error'=>'NO_PERMISSION']); break;
                }

                $stmt = $conn->prepare("DELETE FROM project_meeting_participants WHERE meeting_id=?");
                $stmt->bind_param("i", $meeting_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM project_meetings WHERE id=? AND project_id=?");
                $stmt->bind_param("ii", $meeting_id, $project_id);
                $stmt->execute();
                $stmt->close();

                maybe_set_json_header(); echo json_encode(['ok'=>true]); break;

            case 'save_participants_and_notify':
                $meeting_id = intval($_POST['id'] ?? 0);
                // allow only if creator == current user and user is control
                $stmt = $conn->prepare("SELECT title, start_time, online_link, location, created_by FROM project_meetings WHERE id=? AND project_id=?");
                $stmt->bind_param("ii", $meeting_id, $project_id);
                $stmt->execute();
                $meeting = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$meeting || !$is_control || intval($meeting['created_by']) !== $current_user_id) {
                    maybe_set_json_header(); echo json_encode(['ok'=>false,'error'=>'NO_PERMISSION']); break;
                }

                $internal_ids = $_POST['internal_ids'] ?? []; // array of user IDs
                $external_names = $_POST['external_names'] ?? []; // array of strings
                if (!is_array($internal_ids)) $internal_ids = [];
                if (!is_array($external_names)) $external_names = [];

                // reset participants
                $stmt = $conn->prepare("DELETE FROM project_meeting_participants WHERE meeting_id=?");
                $stmt->bind_param("i", $meeting_id);
                $stmt->execute();
                $stmt->close();

                // add internal
                if (!empty($internal_ids)) {
                    $stmt = $conn->prepare("INSERT INTO project_meeting_participants (meeting_id, user_id, type) VALUES (?,?, 'internal')");
                    foreach ($internal_ids as $uid) {
                        $uid = intval($uid);
                        if ($uid > 0) { $stmt->bind_param("ii", $meeting_id, $uid); $stmt->execute(); }
                    }
                    $stmt->close();
                }
                // add external
                if (!empty($external_names)) {
                    $stmt = $conn->prepare("INSERT INTO project_meeting_participants (meeting_id, external_name, type) VALUES (?, ?, 'external')");
                    foreach ($external_names as $name) {
                        $name = trim((string)$name);
                        if ($name !== '') { $stmt->bind_param("is", $meeting_id, $name); $stmt->execute(); }
                    }
                    $stmt->close();
                }

                // notifications for internal participants
                $message = "Cuộc họp: ".$meeting['title']." | Thời gian: ".($meeting['start_time'] ?: 'Chưa xác định')." | Địa điểm: ".($meeting['location'] ?: '—')." | Online: ".($meeting['online_link'] ?: '—');
                if (!empty($internal_ids)) {
                    $stmt = $conn->prepare("INSERT INTO project_meeting_notifications (project_id, meeting_id, sender_id, receiver_id, message) VALUES (?,?,?,?,?)");
                    foreach ($internal_ids as $uid) {
                        $uid = intval($uid);
                        if ($uid > 0) { $stmt->bind_param("iiiis", $project_id, $meeting_id, $current_user_id, $uid, $message); $stmt->execute(); }
                    }
                    $stmt->close();
                }
                // external notifications: store names only (optional email later)
                if (!empty($external_names)) {
                    $stmt = $conn->prepare("INSERT INTO project_meeting_notifications (project_id, meeting_id, sender_id, receiver_id, receiver_name, message) VALUES (?,?,?,?,?,?)");
                    foreach ($external_names as $name) {
                        $name = trim((string)$name);
                        if ($name !== '') { $null = null; $stmt->bind_param("iiiiss", $project_id, $meeting_id, $current_user_id, $null, $name, $message); $stmt->execute(); }
                    }
                    $stmt->close();
                }

                maybe_set_json_header(); echo json_encode(['ok'=>true]); break;

            case 'export_word':
                // Export a super-simple .docx (actually .doc HTML) for quick download
                $meeting_id = intval($_GET['id'] ?? 0);
                // Basic fetch
                $stmt = $conn->prepare("SELECT m.*, CONCAT(u.first_name,' ',u.last_name) AS creator_name
                                        FROM project_meetings m
                                        LEFT JOIN users u ON u.id = m.created_by
                                        WHERE m.id=? AND m.project_id=?");
                $stmt->bind_param("ii", $meeting_id, $project_id);
                $stmt->execute();
                $m = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$m) {
                    maybe_set_json_header(); echo json_encode(['ok'=>false,'error'=>'NOT_FOUND']); break;
                }

                // Word-compatible HTML
                $filename = 'meeting_'.$meeting_id.'.doc';
                if (!headers_sent()) {
                    header("Content-Type: application/msword; charset=utf-8");
                    header("Content-Disposition: attachment; filename=\"$filename\"");
                }
                echo "<html><head><meta charset='utf-8'></head><body>";
                echo "<h2>Biên bản cuộc họp</h2>";
                echo "<p><strong>Tên:</strong> ".htmlspecialchars($m['title'])."</p>";
                echo "<p><strong>Thời gian:</strong> ".htmlspecialchars($m['start_time'] ?? '')."</p>";
                echo "<p><strong>Địa điểm:</strong> ".htmlspecialchars($m['location'] ?? '')."</p>";
                echo "<p><strong>Online:</strong> ".htmlspecialchars($m['online_link'] ?? '')."</p>";
                echo "<hr>";
                echo "<div>".($m['detail'] ?? '')."</div>";
                echo "</body></html>";
                break;

            default:
                maybe_set_json_header(); echo json_encode(['ok'=>false,'error'=>'UNKNOWN_ACTION']); break;
        }
    } catch (Throwable $e) {
        maybe_set_json_header();
        echo json_encode(['ok'=>false,'error'=>'EXCEPTION','message'=>$e->getMessage()]);
    }
    exit;
}

// -------------- Render HTML for the tab (when included) ----------------

?>
<div id="meetings-tab" class="meetings-tab">
    <?php if (!$conn): ?>
        <div class="alert alert-danger">Không thể kết nối cơ sở dữ liệu. Hãy đảm bảo <code>config.php</code> đã được include trong <code>project_view.php</code> và thiết lập biến <code>$conn</code>.</div>
    <?php elseif (!$current_user_id): ?>
        <div class="alert alert-danger">Bạn chưa đăng nhập.</div>
    <?php elseif (!$project_id): ?>
        <div class="alert alert-danger">Thiếu <code>project_id</code> khi include tab Meetings.</div>
    <?php else: ?>
        <?php
        // Check membership (view permission)
        $stmt = $conn->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND user_id=? LIMIT 1");
        $stmt->bind_param("ii", $project_id, $current_user_id);
        $stmt->execute();
        $is_member = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        $stmt = $conn->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND user_id=? AND role='control' LIMIT 1");
        $stmt->bind_param("ii", $project_id, $current_user_id);
        $stmt->execute();
        $is_control = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        ?>

        <?php if (!$is_member): ?>
            <div class="alert alert-warning">Bạn không thuộc dự án này. Truy cập bị từ chối.</div>
        <?php else: ?>
            <link rel="stylesheet" href="assets/css/project_tab_meetings.css">
            <div class="mt-toolbar">
                <div class="left">
                    <input type="date" id="mt-filter-date">
                    <input type="text" id="mt-filter-q" placeholder="Tìm theo tiêu đề...">
                    <button id="mt-btn-refresh">Tìm kiếm</button>
                </div>
                <div class="right">
                    <?php if ($is_control): ?>
                        <button id="mt-btn-create">Tạo cuộc họp</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-table-wrap">
                <table class="mt-table" id="mt-table">
                    <thead>
                        <tr>
                            <th>Tên cuộc họp</th>
                            <th>Người tạo</th>
                            <th>Ngày tạo</th>
                            <th>Địa điểm</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- Modal Create/Edit -->
            <div class="mt-modal" id="mt-modal" style="display:none">
                <div class="mt-modal-content">
                    <h3 id="mt-modal-title">Tạo cuộc họp</h3>
                    <label>Tên cuộc họp *</label>
                    <input type="text" id="mt-title">
                    <label>Mô tả ngắn</label>
                    <input type="text" id="mt-short-desc">
                    <label>Thời gian bắt đầu</label>
                    <input type="datetime-local" id="mt-start-time">
                    <label>Đường dẫn Online</label>
                    <input type="url" id="mt-online-link" placeholder="https://...">
                    <label>Địa điểm tổ chức</label>
                    <input type="text" id="mt-location">
                    <div class="mt-modal-actions">
                        <button id="mt-save">Lưu</button>
                        <button id="mt-cancel">Hủy</button>
                    </div>
                </div>
            </div>

            <!-- Detail Drawer -->
            <div class="mt-detail" id="mt-detail" style="display:none">
                <div class="mt-detail-content">
                    <div class="mt-detail-head">
                        <h3 id="md-title">Chi tiết cuộc họp</h3>
                        <button id="md-close">Đóng</button>
                    </div>
                    <!-- KV1 -->
                    <div class="md-kv1">
                        <div><strong>Thời gian:</strong> <span id="md-start"></span></div>
                        <div><strong>Địa điểm:</strong> <span id="md-location"></span></div>
                        <div><strong>Online:</strong> <a href="#" id="md-online" target="_blank">Mở</a></div>
                    </div>
                    <!-- KV2: Rich text -->
                    <div class="md-kv2">
                        <label>Nội dung chi tiết</label>
                        <div class="md-toolbar">
                            <button data-cmd="bold">B</button>
                            <button data-cmd="italic"><em>I</em></button>
                            <button data-cmd="underline"><u>U</u></button>
                            <button data-cmd="strikeThrough"><s>S</s></button>
                            <button data-cmd="insertOrderedList">OL</button>
                            <button data-cmd="insertUnorderedList">UL</button>
                            <button data-cmd="formatBlock" data-value="h3">H3</button>
                            <button data-cmd="backColor" data-value="yellow">HL</button>
                            <button data-cmd="foreColor" data-value="#d00">Red</button>
                            <button id="md-insert-table">Table 3x3</button>
                        </div>
                        <div id="md-editor" contenteditable="true" class="md-editor"></div>
                    </div>
                    <!-- KV3: Participants -->
                    <div class="md-kv3">
                        <div class="md-part-columns">
                            <div>
                                <label>Thành viên trong dự án</label>
                                <div id="md-internal-list" class="md-list"></div>
                            </div>
                            <div>
                                <label>Thành viên ngoài dự án</label>
                                <div id="md-external-list" class="md-list"></div>
                                <button id="md-add-external">+ Thêm dòng</button>
                            </div>
                        </div>
                        <div class="md-actions">
                            <button id="md-save-all">Lưu & Gửi thông báo</button>
                            <a id="md-export" href="#" class="md-export">Xuất biên bản (Word)</a>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                window.MEETINGS_CFG = {
                    project_id: <?php echo intval($project_id); ?>,
                    ajax_url: 'pages/partials/project_tab_meetings.php?ajax=1&project_id=<?php echo intval($project_id); ?>'
                };
            </script>
            <script src="assets/js/project_tab_meetings.js"></script>
        <?php endif; ?>
    <?php endif; ?>
</div>
