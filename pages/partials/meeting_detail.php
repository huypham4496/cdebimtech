<?php
// pages/partials/meeting_detail.php
if (!defined('CDE_BOOTSTRAPPED')) {
    $ROOT = dirname(__DIR__, 2);
    if (is_file($ROOT . '/config.php')) require_once $ROOT . '/config.php';
}
header('X-Content-Type-Options: nosniff');

if (!isset($pdo)) { http_response_code(500); echo 'Database not initialized.'; exit; }
session_start();
$CURRENT_USER = isset($CURRENT_USER) ? $CURRENT_USER : (isset($_SESSION['user']) ? $_SESSION['user'] : null);
if (!$CURRENT_USER) { http_response_code(401); echo 'Please sign in.'; exit; }

$BASE = isset($BASE) ? $BASE : '';
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : (isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0);
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

function is_project_member(PDO $pdo, int $projectId, int $userId): bool {
    $st = $pdo->prepare('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1');
    $st->execute([$projectId, $userId]);
    return (bool)$st->fetchColumn();
}
function json_out($p){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; }

$userId = (int)$CURRENT_USER['id'];
if ($projectId<=0 || $id<=0) { http_response_code(400); echo 'Invalid meeting.'; exit; }
if (!is_project_member($pdo, $projectId, $userId)) { http_response_code(403); echo 'Permission denied'; exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Export Word using PHPWord
if ($action === 'export_word') {
    try {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        $q = $pdo->prepare('
            SELECT m.*, u.fullname as creator_name
            FROM project_meetings m
            LEFT JOIN users u ON u.id = m.created_by
            WHERE m.id = ? AND m.project_id = ?
        ');
        $q->execute([$id, $projectId]);
        $m = $q->fetch(PDO::FETCH_ASSOC);
        if (!$m) { throw new Exception('Meeting not found'); }

        $c = $pdo->prepare('SELECT content_html FROM project_meeting_contents WHERE meeting_id = ?');
        $c->execute([$id]);
        $content = (string)($c->fetchColumn() ?: '');

        $p = $pdo->prepare('
            SELECT p.*, u.fullname FROM project_meeting_participants p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE p.meeting_id = ? ORDER BY p.id ASC
        ');
        $p->execute([$id]);
        $participants = $p->fetchAll(PDO::FETCH_ASSOC);

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addTitle('Meeting Minutes', 1);
        $section->addText('Title: ' . ($m['title'] ?? ''));
        $section->addText('Start time: ' . ($m['start_at'] ?? ''));
        $section->addText('Location: ' . ($m['location'] ?? ''));
        $section->addText('Online link: ' . ($m['online_link'] ?? ''));
        $section->addText('Created by: ' . ($m['creator_name'] ?? ''));
        $section->addTextBreak(1);
        $section->addTitle('Attendees', 2);
        if ($participants) {
            foreach ($participants as $row) {
                $name = $row['fullname'] ?: $row['external_name'];
                $section->addListItem($name);
            }
        } else {
            $section->addText('No attendees recorded.');
        }
        $section->addTextBreak(1);
        $section->addTitle('Details', 2);
        // Import HTML content (basic)
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $content, false, false);

        $file = sys_get_temp_dir() . '/meeting_' . $id . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($file);

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="meeting_' . $id . '.docx"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        unlink($file);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Export failed: ' . htmlspecialchars($e->getMessage());
        exit;
    }
}

// Save content and participants
if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); json_out(['ok'=>false,'error'=>'Method not allowed']); }
    $content_html = $_POST['content_html'] ?? '';
    $members = $_POST['members'] ?? ''; // comma-separated user IDs
    $externals = $_POST['externals'] ?? ''; // comma-separated names

    // Save content
    $pdo->prepare('INSERT INTO project_meeting_contents (meeting_id, content_html, updated_by, updated_at)
                   VALUES (?, ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE content_html=VALUES(content_html), updated_by=VALUES(updated_by), updated_at=NOW()')
        ->execute([$id, $content_html, $userId]);

    // Reset participants
    $pdo->prepare('DELETE FROM project_meeting_participants WHERE meeting_id = ?')->execute([$id]);

    $ins = $pdo->prepare('INSERT INTO project_meeting_participants (meeting_id, user_id, external_name) VALUES (?, ?, ?)');

    $memberIds = array_filter(array_map('trim', explode(',', (string)$members)));
    foreach ($memberIds as $uid) {
        if ($uid !== '') $ins->execute([$id, (int)$uid, null]);
    }
    $externalNames = array_filter(array_map('trim', explode(',', (string)$externals)));
    foreach ($externalNames as $name) {
        if ($name !== '') $ins->execute([$id, null, $name]);
    }

    // Create notifications for members
    if (!empty($memberIds)) {
        $notify = $pdo->prepare('INSERT INTO project_meeting_notifications (meeting_id, user_id, created_at) VALUES (?, ?, NOW())');
        foreach ($memberIds as $uid) {
            $notify->execute([$id, (int)$uid]);
        }
    }

    json_out(['ok'=>true]);
}

// Fetch meeting details
$q = $pdo->prepare('SELECT m.*, u.fullname as creator_name FROM project_meetings m LEFT JOIN users u ON u.id = m.created_by WHERE m.id = ? AND m.project_id = ?');
$q->execute([$id, $projectId]);
$m = $q->fetch(PDO::FETCH_ASSOC);
if (!$m) { http_response_code(404); echo 'Meeting not found.'; exit; }

$c = $pdo->prepare('SELECT content_html FROM project_meeting_contents WHERE meeting_id = ?');
$c->execute([$id]);
$content = (string)($c->fetchColumn() ?: '');

// Project members
$mem = $pdo->prepare('SELECT u.id, u.fullname, u.email FROM project_members pm INNER JOIN users u ON u.id = pm.user_id WHERE pm.project_id = ? ORDER BY u.fullname ASC');
$mem->execute([$projectId]);
$members = $mem->fetchAll(PDO::FETCH_ASSOC);

// Current participants to pre-check
$cur = $pdo->prepare('SELECT user_id, external_name FROM project_meeting_participants WHERE meeting_id = ?');
$cur->execute([$id]);
$curRows = $cur->fetchAll(PDO::FETCH_ASSOC);
$checkedUsers = array_map(function($r){return (int)$r['user_id'];}, array_filter($curRows, function($r){return !is_null($r['user_id']);}));
$externalNames = array_map(function($r){return $r['external_name'];}, array_filter($curRows, function($r){return !is_null($r['external_name']);}));

?>
<link rel="stylesheet" href="<?= $BASE ?>/../assets/css/meeting_detail.css">
<div class="mtg-detail-wrap" data-project-id="<?= htmlspecialchars($projectId) ?>" data-id="<?= htmlspecialchars($id) ?>">
  <div class="kv1 card">
    <h2>Meeting Summary</h2>
    <div class="grid two">
      <div><label>Title</label><div class="value"><?= htmlspecialchars($m['title']) ?></div></div>
      <div><label>Start time</label><div class="value"><?= htmlspecialchars($m['start_at']) ?></div></div>
      <div><label>Location</label><div class="value"><?= htmlspecialchars($m['location']) ?: '—' ?></div></div>
      <div><label>Online link</label><div class="value"><?= $m['online_link'] ? '<a href="'.htmlspecialchars($m['online_link']).'" target="_blank" rel="noopener">Open</a>' : '—' ?></div></div>
    </div>
  </div>

  <div class="kv2 card">
    <div class="kv2-head">
      <h2>Details</h2>
      <div class="toolbar" id="editor-toolbar">
        <button data-cmd="bold" title="Bold"><i class="fas fa-bold"></i></button>
        <button data-cmd="italic" title="Italic"><i class="fas fa-italic"></i></button>
        <button data-cmd="underline" title="Underline"><i class="fas fa-underline"></i></button>
        <button data-cmd="strikeThrough" title="Strikethrough"><i class="fas fa-strikethrough"></i></button>
        <button data-cmd="foreColor" data-value="#ef4444" title="Text color"><i class="fas fa-palette"></i></button>
        <button data-cmd="fontName" data-value="Arial" title="Font"><i class="fas fa-font"></i></button>
        <button data-cmd="fontSize" data-value="4" title="Font size"><i class="fas fa-text-height"></i></button>
        <button data-cmd="insertUnorderedList" title="Bulleted list"><i class="fas fa-list-ul"></i></button>
        <button data-cmd="insertOrderedList" title="Numbered list"><i class="fas fa-list-ol"></i></button>
        <button id="btn-insert-table" title="Insert table"><i class="fas fa-table"></i></button>
        <button data-cmd="backColor" data-value="#ffff00" title="Highlight"><i class="fas fa-highlighter"></i></button>
      </div>
    </div>
    <div id="editor" class="editor" contenteditable="true"><?= $content ?></div>
  </div>

  <div class="kv3 card">
    <h2>Attendees</h2>
    <div class="att-grid">
      <div class="att-block">
        <h3>Project members</h3>
        <div class="att-list">
          <?php foreach ($members as $u): $checked = in_array((int)$u['id'], $checkedUsers, true); ?>
            <label class="att-item">
              <input type="checkbox" class="att-chk" value="<?= (int)$u['id'] ?>" <?= $checked ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($u['fullname']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="att-block">
        <h3>External attendees</h3>
        <p class="muted">Enter names separated by commas.</p>
        <input type="text" id="att-externals" placeholder="e.g., John Doe, Jane Smith" value="<?= htmlspecialchars(implode(', ', $externalNames)) ?>">
      </div>
    </div>
    <div class="actions">
      <button class="btn btn-secondary" id="btn-back">Back</button>
      <div class="spacer"></div>
      <a class="btn btn-outline" href="?action=export_word&project_id=<?= $projectId ?>&id=<?= $id ?>">Export Word</a>
      <button class="btn btn-primary" id="btn-save">Save</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
window.CDE = window.CDE || {};
CDE.detail = {
  saveUrl: '?action=save&project_id=<?= $projectId ?>&id=<?= $id ?>',
  backUrl: '<?= $BASE ?>/pages/project_view.php?project_id=<?= $projectId ?>#tab=meetings'
};
</script>
<script src="<?= $BASE ?>/../assets/js/meeting_detail.js"></script>
