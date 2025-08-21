<?php
// pages/meeting_detail.php
// Meeting Detail Page for CDE
// Requirements: show summary (KV1), rich-text notes (KV2), attendees management + notifications + export Word (KV3)

// --- Bootstrap & Auth ---
// Expect a PDO instance $pdo and a logged-in user id in $_SESSION['user_id']
// Adjust these includes to your app structure if needed.
if (session_status() === PHP_SESSION_NONE) session_start();
$ROOT = dirname(__DIR__);
$BASE = '..';

// If your project provides a shared bootstrap that defines $pdo, include it here.
// Example:
// require_once $ROOT . '/../includes/bootstrap.php';

if (!isset($pdo)) {
    // Fallback: quick PDO bootstrap using env-style constants if available.
    // Define DB creds in your environment or replace these with your local settings.
    $db_host = getenv('DB_HOST') ?: '127.0.0.1';
    $db_name = getenv('DB_NAME') ?: 'cde';
    $db_user = getenv('DB_USER') ?: 'root';
    $db_pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo "Database connection failed.";
        exit;
    }
}

$current_user_id = $_SESSION['user_id'] ?? 0;

// Utilities
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
function ensure_member_of_project(PDO $pdo, $project_id, $user_id) {
    $stmt = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?");
    $stmt->execute([$project_id, $user_id]);
    return (bool)$stmt->fetchColumn();
}

// --- AJAX endpoints ---
$ajax = $_GET['ajax'] ?? $_POST['ajax'] ?? null;
$meeting_id = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : (isset($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : 0);

if ($ajax) {
    if (!$meeting_id) json_response(['error' => 'Missing meeting_id'], 400);

    // Load meeting & project id
    $stmt = $pdo->prepare("SELECT pm.*, p.name AS project_name FROM project_meetings pm JOIN projects p ON p.id = pm.project_id WHERE pm.id = ?");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch();
    if (!$meeting) json_response(['error' => 'Meeting not found'], 404);

    $project_id = (int)$meeting['project_id'];
    if (!$current_user_id || !ensure_member_of_project($pdo, $project_id, $current_user_id)) {
        json_response(['error' => 'Bạn không có quyền truy cập cuộc họp này.'], 403);
    }

    if ($ajax === 'load') {
        // Notes
        $stmt = $pdo->prepare("SELECT content_html, updated_by, updated_at FROM project_meeting_details WHERE meeting_id = ?");
        try {
            $stmt->execute([$meeting_id]);
            $detail = $stmt->fetch();
        } catch (Exception $e) {
            // If table doesn't exist yet, hint to migrate
            $detail = null;
        }

        // Attendees
        $attStmt = $pdo->prepare("SELECT * FROM project_meeting_attendees WHERE meeting_id = ? ORDER BY id ASC");
        $attStmt->execute([$meeting_id]);
        $attendees = $attStmt->fetchAll();

        // Project members (for selection)
        $memStmt = $pdo->prepare("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS full_name, u.email
                                  FROM project_members pm
                                  JOIN users u ON u.id = pm.user_id
                                  WHERE pm.project_id = ?
                                  ORDER BY full_name ASC");
        $memStmt->execute([$project_id]);
        $members = $memStmt->fetchAll();

        json_response([
            'meeting' => $meeting,
            'detail' => $detail,
            'attendees' => $attendees,
            'members' => $members,
            'needs_migration' => $detail === null ? true : false
        ]);
    }
    elseif ($ajax === 'save') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) $payload = $_POST;

        $content_html = $payload['content_html'] ?? '';
        $selected_user_ids = $payload['selected_user_ids'] ?? [];
        $external_participants = $payload['external_participants'] ?? []; // [{name, email}]

        // Upsert notes
        $pdo->beginTransaction();
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS project_meeting_details (
                meeting_id INT(11) NOT NULL PRIMARY KEY,
                content_html LONGTEXT NULL,
                updated_by INT(11) NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_pmd_meeting FOREIGN KEY (meeting_id) REFERENCES project_meetings(id) ON DELETE CASCADE,
                CONSTRAINT fk_pmd_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            $stmt = $pdo->prepare("INSERT INTO project_meeting_details (meeting_id, content_html, updated_by, updated_at)
                                   VALUES (?, ?, ?, NOW())
                                   ON DUPLICATE KEY UPDATE content_html = VALUES(content_html), updated_by = VALUES(updated_by), updated_at = NOW()");
            $stmt->execute([$meeting_id, $content_html, $current_user_id]);

            // Reset attendees
            $del = $pdo->prepare("DELETE FROM project_meeting_attendees WHERE meeting_id = ?");
            $del->execute([$meeting_id]);

            // Insert internal attendees
            if (is_array($selected_user_ids)) {
                $ins = $pdo->prepare("INSERT INTO project_meeting_attendees (meeting_id, user_id, is_external) VALUES (?, ?, 0)");
                foreach ($selected_user_ids as $uid) {
                    if (!$uid) continue;
                    $ins->execute([$meeting_id, (int)$uid]);
                }
            }

            // Insert external attendees
            if (is_array($external_participants)) {
                $ins2 = $pdo->prepare("INSERT INTO project_meeting_attendees (meeting_id, external_name, external_email, is_external) VALUES (?, ?, ?, 1)");
                foreach ($external_participants as $ep) {
                    $name = trim($ep['name'] ?? '');
                    $email = trim($ep['email'] ?? '');
                    if ($name === '' && $email === '') continue;
                    $ins2->execute([$meeting_id, $name, $email]);
                }
            }

            // Send notifications to internal attendees
            if (is_array($selected_user_ids) && count($selected_user_ids) > 0) {
                $msg = 'Bạn đã được thêm vào cuộc họp: ' . $meeting['title'];
                $notif = $pdo->prepare("INSERT INTO project_meeting_notifications (project_id, meeting_id, sender_id, receiver_id, message, created_at, is_read)
                                        VALUES (?, ?, ?, ?, ?, NOW(), 0)");
                foreach ($selected_user_ids as $uid) {
                    $notif->execute([$project_id, $meeting_id, $current_user_id, (int)$uid, $msg]);
                }
            }

            $pdo->commit();
            json_response(['ok' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            json_response(['error' => 'Save failed', 'detail' => $e->getMessage()], 500);
        }
    }
    elseif ($ajax === 'export_doc') {
        // Export a Word-compatible .doc via HTML
        header("Content-Type: application/msword; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"Meeting-Minutes-{$meeting_id}.doc\"");

        // Load notes
        $stmt = $pdo->prepare("SELECT content_html FROM project_meeting_details WHERE meeting_id = ?");
        $content_html = '';
        try {
            $stmt->execute([$meeting_id]);
            $row = $stmt->fetch();
            if ($row) $content_html = $row['content_html'];
        } catch (Exception $e) {}

        // Load attendees
        $attStmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name, u.email FROM project_meeting_attendees a
                                  LEFT JOIN users u ON u.id = a.user_id WHERE a.meeting_id = ?");
        $attStmt->execute([$meeting_id]);
        $attendees = $attStmt->fetchAll();

        ob_start();
        ?>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Meeting Minutes</title>
            <style>
                body { font-family: 'Times New Roman', serif; }
                h1 { font-size: 22pt; margin-bottom: 6pt; }
                .meta { margin-bottom: 12pt; font-size: 11pt; }
                .meta strong { display:inline-block; width: 120px; }
                .section-title { font-weight: bold; font-size: 12pt; margin: 14pt 0 6pt; }
                table { border-collapse: collapse; width: 100%; font-size: 11pt; }
                th, td { border: 1px solid #000; padding: 6pt; }
            </style>
        </head>
        <body>
            <h1>Meeting Minutes</h1>
            <div class="meta">
                <div><strong>Project:</strong> <?= htmlspecialchars($meeting['project_name']) ?></div>
                <div><strong>Title:</strong> <?= htmlspecialchars($meeting['title']) ?></div>
                <div><strong>Start Time:</strong> <?= htmlspecialchars($meeting['start_time']) ?></div>
                <div><strong>Location:</strong> <?= htmlspecialchars($meeting['location'] ?? '') ?></div>
                <div><strong>Online Link:</strong> <?= htmlspecialchars($meeting['online_link'] ?? '') ?></div>
            </div>

            <div class="section-title">Attendees</div>
            <table>
                <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Type</th></tr></thead>
                <tbody>
                <?php foreach ($attendees as $i => $a): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= $a['is_external'] ? htmlspecialchars($a['external_name']) : htmlspecialchars(trim(($a['first_name'] ?? '').' '.($a['last_name'] ?? ''))) ?></td>
                        <td><?= $a['is_external'] ? htmlspecialchars($a['external_email']) : htmlspecialchars($a['email'] ?? '') ?></td>
                        <td><?= $a['is_external'] ? 'External' : 'Project Member' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="section-title">Details</div>
            <div><?= $content_html ?></div>
        </body>
        </html>
        <?php
        echo ob_get_clean();
        exit;
    }
    else {
        json_response(['error' => 'Unknown action'], 400);
    }
    exit;
}

// --- Regular page render ---
$meeting_id = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;
if (!$meeting_id) {
    http_response_code(400);
    echo "Missing meeting_id";
    exit;
}

// Minimal meeting fetch to render header (others via AJAX)
$stmt = $pdo->prepare("SELECT pm.*, p.name AS project_name FROM project_meetings pm JOIN projects p ON p.id = pm.project_id WHERE pm.id = ?");
$stmt->execute([$meeting_id]);
$meeting = $stmt->fetch();
if (!$meeting) {
    http_response_code(404);
    echo "Meeting not found";
    exit;
}

// Security: only members can view
$project_id = (int)$meeting['project_id'];
if (!$current_user_id || !ensure_member_of_project($pdo, $project_id, $current_user_id)) {
    http_response_code(403);
    echo "⚠️ Bạn không có quyền truy cập cuộc họp này (chỉ thành viên trong dự án mới xem/sửa).";
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Meeting Detail - <?= htmlspecialchars($meeting['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= $BASE ?>/../assets/css/meeting_detail.css?v=<?= time() ?>">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        integrity="sha512-dynvDxJ5aVF6oU1i6zfoalvVYvNvKcJste/0q5u+P%2FgPm4jG3E5s3UeJ8V+RaH59RUW2YCiMzZ6pyRrg58F3CA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* Fallback if asset not found during local preview */
    </style>
</head>
<body class="md-body">
    <div class="md-container">
        <!-- KV1: Summary -->
        <section class="card md-summary">
            <div class="card-head">
                <h1><i class="fas fa-handshake"></i> <?= htmlspecialchars($meeting['title']) ?></h1>
                <div class="actions">
                    <a class="btn secondary" href="<?= $BASE ?>/partials/project_tab_meetings.php?project_id=<?= (int)$project_id ?>">&larr; Back</a>
                    <button id="btn-export" class="btn"><i class="far fa-file-word"></i> Xuất biên bản (Word)</button>
                </div>
            </div>
            <div class="grid meta">
                <div>
                    <div class="label">Project</div>
                    <div class="value"><?= htmlspecialchars($meeting['project_name']) ?></div>
                </div>
                <div>
                    <div class="label">Start time</div>
                    <div class="value"><span id="md-start-time">—</span></div>
                </div>
                <div>
                    <div class="label">Location</div>
                    <div class="value"><span id="md-location">—</span></div>
                </div>
                <div>
                    <div class="label">Online link</div>
                    <div class="value"><a id="md-online" href="#" target="_blank">—</a></div>
                </div>
                <div class="full">
                    <div class="label">Short description</div>
                    <div class="value"><span id="md-short">—</span></div>
                </div>
            </div>
        </section>

        <!-- KV2: Rich text details -->
        <section class="card md-editor">
            <div class="card-head">
                <h2><i class="fas fa-align-left"></i> Nội dung chi tiết</h2>
                <div class="toolbar" id="editor-toolbar">
                    <button data-cmd="bold" title="Bold"><i class="fas fa-bold"></i></button>
                    <button data-cmd="italic" title="Italic"><i class="fas fa-italic"></i></button>
                    <button data-cmd="underline" title="Underline"><i class="fas fa-underline"></i></button>
                    <button data-cmd="strikeThrough" title="Strike"><i class="fas fa-strikethrough"></i></button>
                    <span class="sep"></span>
                    <button data-cmd="foreColor" data-value="#d90429" title="Text Color"><i class="fas fa-font"></i></button>
                    <button data-cmd="backColor" data-value="#fff3bf" title="Highlight"><i class="fas fa-highlighter"></i></button>
                    <span class="sep"></span>
                    <select id="font-size">
                        <option value="">Size</option>
                        <option value="3">Normal</option>
                        <option value="4">Large</option>
                        <option value="5">Larger</option>
                        <option value="6">Huge</option>
                    </select>
                    <select id="font-name">
                        <option value="">Font</option>
                        <option>Arial</option>
                        <option>Times New Roman</option>
                        <option>Tahoma</option>
                        <option>Verdana</option>
                        <option>Courier New</option>
                    </select>
                    <span class="sep"></span>
                    <button id="btn-insert-table" title="Insert table"><i class="fas fa-table"></i></button>
                </div>
            </div>
            <div id="editor" class="editor" contenteditable="true" spellcheck="false"></div>
        </section>

        <!-- KV3: Attendees -->
        <section class="card md-attendees">
            <div class="card-head">
                <h2><i class="fas fa-users"></i> Thành viên tham gia</h2>
            </div>
            <div class="att-grid">
                <div class="att-block">
                    <h3>Thành viên trong dự án</h3>
                    <div id="member-list" class="member-list"></div>
                </div>
                <div class="att-block">
                    <h3>Khách mời bên ngoài</h3>
                    <div id="external-list" class="external-list"></div>
                    <button id="btn-add-external" class="btn small"><i class="fas fa-user-plus"></i> Thêm</button>
                </div>
            </div>
            <div class="actions right">
                <button id="btn-save" class="btn primary"><i class="far fa-save"></i> Lưu & gửi thông báo</button>
            </div>
        </section>
    </div>

    <script>
        window.MEETING_ID = <?= (int)$meeting_id ?>;
        window.BASE = "<?= $BASE ?>";
    </script>
    <script src="<?= $BASE ?>/../assets/js/meeting_detail.js?v=<?= time() ?>"></script>
</body>
</html>
