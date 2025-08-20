<?php
/**
 * /pages/partials/project_tab_meetings.php
 * Assumptions (prefer same style as Materials tab):
 *   - Normally included from project_view.php where $pdo, $projectId, $userId are already defined.
 *   - This file can also be hit directly via AJAX; in that case we try to bootstrap $pdo from config.php/getPDO().
 */

declare(strict_types=1);

/** --------- Ensure $pdo even if called directly (fallback) --------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  // Try to include config to get PDO
  $ROOT = realpath(__DIR__ . '/..');
  if ($ROOT && is_file($ROOT . '/config.php')) {
    require_once $ROOT . '/config.php';
  }
  if (!isset($pdo) && function_exists('getPDO')) {
    $pdo = getPDO();
  }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); echo "PDO not set"; return; }

/** --------- Resolve context $projectId, $userId (same naming as Materials) --------- */
$projectId = isset($projectId) ? (int)$projectId : (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);

/* Try to get userId from variables/session if not provided by parent */
$userId = isset($userId) ? (int)$userId : 0;
if ($userId <= 0) {
  if (session_status() === PHP_SESSION_NONE) { @session_start(); }
  $cands = [
    $_SESSION['user_id'] ?? null,
    $_SESSION['CURRENT_USER_ID'] ?? null,
    $_SESSION['auth']['user_id'] ?? null,
    $_SESSION['auth']['id'] ?? null,
  ];
  foreach ($cands as $v) { if (is_numeric($v) && (int)$v>0) { $userId = (int)$v; break; } }
}

if ($projectId <= 0) { echo "<div class='mt-warn'>Missing projectId.</div>"; return; }
if ($userId <= 0)    { echo "<div class='mt-access-denied'>⚠️ Không xác định được người dùng hiện tại. Vui lòng đăng nhập lại.</div>"; return; }

/* ----------------------- Permission helpers ----------------------- */
function mt_isProjectMember(PDO $pdo, int $projectId, int $userId): bool {
  $stmt = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id = ? AND user_id = ? LIMIT 1");
  $stmt->execute([$projectId, $userId]);
  return (bool)$stmt->fetchColumn();
}
function mt_hasControlRole(PDO $pdo, int $projectId, int $userId): bool {
  $stmt = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id = ? AND user_id = ? AND role = 'control' LIMIT 1");
  $stmt->execute([$projectId, $userId]);
  return (bool)$stmt->fetchColumn();
}

/* ----------------------- AJAX Router ----------------------- */
$isAjax = isset($_GET['ajax']) || isset($_GET['ajax_meetings']) || 
          (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($isAjax) {
  // Clean output buffer to avoid BOM/HTML corrupting JSON
  while (ob_get_level()) { ob_end_clean(); }
  header('Content-Type: application/json; charset=utf-8');

  if (!mt_isProjectMember($pdo, $projectId, $userId)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'ACCESS_DENIED','message'=>'You are not a member of this project.']);
    return;
  }

  $action = $_POST['action'] ?? $_GET['action'] ?? 'list';

  try {
    if ($action === 'list') {
      $q         = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));
      $date_from = trim((string)($_POST['date_from'] ?? $_GET['date_from'] ?? ''));
      $date_to   = trim((string)($_POST['date_to'] ?? $_GET['date_to'] ?? ''));

      $sortDir = strtoupper(trim((string)($_POST['sort'] ?? $_GET['sort'] ?? 'ASC')));
      if ($sortDir !== 'DESC') { $sortDir = 'ASC'; }

$params = [$projectId];
      $where = " WHERE m.project_id = ? ";

      if ($q !== '') {
        $where .= " AND (m.title LIKE ? OR m.short_desc LIKE ? OR m.location LIKE ? OR m.online_link LIKE ? OR DATE_FORMAT(m.start_time, '%Y-%m-%d %H:%i') LIKE ? OR DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i') LIKE ? OR DATE_FORMAT(m.start_time, '%d-%m-%Y %H:%i') LIKE ? OR DATE_FORMAT(m.created_at, '%d-%m-%Y %H:%i') LIKE ?) ";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
      }
      if ($date_from !== '') { $where .= " AND DATE(m.start_time) >= ? "; $params[] = $date_from; }
      if ($date_to   !== '') { $where .= " AND DATE(m.start_time) <= ? "; $params[] = $date_to; }

      $sql = "SELECT m.id, m.title, m.short_desc, m.online_link, m.location, m.start_time, m.created_by, m.created_at,
                     CONCAT(u.first_name,' ',u.last_name) AS creator_name
              FROM project_meetings m
              LEFT JOIN users u ON u.id = m.created_by
              $where
              ORDER BY m.start_time {$sortDir}, m.created_at {$sortDir}
              LIMIT 500";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $canControl = mt_hasControlRole($pdo, $projectId, $userId);
      foreach ($rows as &$r) {
        $r['can_edit'] = $canControl && ((int)$r['created_by'] === (int)$userId);
      }
      echo json_encode(['ok'=>true,'data'=>$rows]);
      return;
    }

    if ($action === 'create') {
      if (!mt_hasControlRole($pdo, $projectId, $userId)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'NO_PRIVILEGE','message'=>'Only control role can create.']);
        return;
      }
      $title      = trim((string)($_POST['title'] ?? ''));
      $short_desc = trim((string)($_POST['short_desc'] ?? ''));
      $online_link= trim((string)($_POST['online_link'] ?? ''));
      $location   = trim((string)($_POST['location'] ?? ''));
      $start_time = trim((string)($_POST['start_time'] ?? '')); // 'YYYY-MM-DD HH:MM[:SS]'
      if ($title === '' || $start_time === '') {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'VALIDATION','message'=>'Title and Start time are required.']);
        return;
      }
      if (strlen($start_time) === 16) $start_time .= ':00';

      $stmt = $pdo->prepare("INSERT INTO project_meetings
        (project_id, title, short_desc, online_link, location, start_time, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
      $stmt->execute([$projectId, $title, $short_desc, $online_link, $location, $start_time, $userId]);

      echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
      return;
    }

    if ($action === 'update') {
      if (!mt_hasControlRole($pdo, $projectId, $userId)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'NO_PRIVILEGE','message'=>'Only control role can update.']);
        return;
      }
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'BAD_ID']); return; }

      $own = $pdo->prepare("SELECT created_by FROM project_meetings WHERE id = ? AND project_id = ?");
      $own->execute([$id, $projectId]);
      $creator = $own->fetchColumn();
      if (!$creator || (int)$creator !== (int)$userId) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'NOT_OWNER','message'=>'You can only edit your meetings.']);
        return;
      }

      $title      = trim((string)($_POST['title'] ?? ''));
      $short_desc = trim((string)($_POST['short_desc'] ?? ''));
      $online_link= trim((string)($_POST['online_link'] ?? ''));
      $location   = trim((string)($_POST['location'] ?? ''));
      $start_time = trim((string)($_POST['start_time'] ?? ''));
      if (strlen($start_time) === 16) $start_time .= ':00';

      $stmt = $pdo->prepare("UPDATE project_meetings
                             SET title=?, short_desc=?, online_link=?, location=?, start_time=?
                             WHERE id=? AND project_id=?");
      $stmt->execute([$title, $short_desc, $online_link, $location, $start_time, $id, $projectId]);
      echo json_encode(['ok'=>true]);
      return;
    }

    if ($action === 'delete') {
      if (!mt_hasControlRole($pdo, $projectId, $userId)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'NO_PRIVILEGE','message'=>'Only control role can delete.']);
        return;
      }
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'BAD_ID']); return; }

      $own = $pdo->prepare("SELECT created_by FROM project_meetings WHERE id = ? AND project_id = ?");
      $own->execute([$id, $projectId]);
      $creator = $own->fetchColumn();
      if (!$creator || (int)$creator !== (int)$userId) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'NOT_OWNER','message'=>'You can only delete your meetings.']);
        return;
      }

      $pdo->prepare("DELETE FROM project_meetings WHERE id=? AND project_id=?")->execute([$id, $projectId]);
      $pdo->prepare("DELETE FROM project_meeting_attendees WHERE meeting_id=?")->execute([$id]); // fallback if no FK
      echo json_encode(['ok'=>true]);
      return;
    }

    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'UNKNOWN_ACTION']);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'SERVER','message'=>$e->getMessage()]);
  }
  return;
}

/* ----------------------- Non-AJAX HTML rendering ----------------------- */
$isMember   = mt_isProjectMember($pdo, $projectId, $userId);
$canControl = $isMember && mt_hasControlRole($pdo, $projectId, $userId);

// Compute asset versions safely
$cssPath = __DIR__ . '/../../assets/css/project_tab_meetings.css';
$jsPath  = __DIR__ . '/../../assets/js/project_tab_meetings.js';
$cssVer  = is_file($cssPath) ? filemtime($cssPath) : time();
$jsVer   = is_file($jsPath)  ? filemtime($jsPath)  : time();
?>
<link rel="stylesheet" href="../assets/css/project_tab_meetings.css?v=<?= htmlspecialchars((string)$cssVer) ?>">
<div id="meetings-root" class="mt-container" data-project="<?= htmlspecialchars((string)$projectId) ?>" data-can-control="<?= $canControl ? '1' : '0' ?>">

  <?php if (!$isMember): ?>
    <div class="mt-access-denied">
      ⚠️ Bạn không có quyền truy cập Tab Meetings của dự án này (chỉ thành viên trong dự án mới được xem).
    </div>
  <?php else: ?>
    <!-- Area 1: Search + Create -->
    <div class="mt-toolbar">
      <div class="mt-search">
        <input type="text" id="mt-q" placeholder="Search by title or description..." /> <span class="mt-tilde">~</span>  </div>
      <?php if ($canControl): ?>
        <button id="mt-btn-create" class="primary"><i class="fas fa-plus"></i> Tạo cuộc họp</button>
      <?php endif; ?>
    </div>

    <!-- Area 2: Table -->
    <div class="mt-table-wrap">
      <table class="mt-table">
        <thead>
          <tr>
            <th>Title</th>
            <th id="mt-th-start">Start Time</th>
            <th>Location</th>
            <th>Online Link</th>
            <th>Creator</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="mt-tbody">
          <tr><td colspan="6" class="muted">Loading...</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Modal: Create/Update -->
    <div id="mt-modal" class="mt-modal hidden" aria-hidden="true">
      <div class="mt-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="mt-modal-title">
        <div class="mt-modal-header">
          <h3 id="mt-modal-title">Tạo cuộc họp</h3>
          <button class="mt-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="mt-modal-body">
          <div class="mt-field">
            <label>Title <span class="req">*</span></label>
            <input type="text" id="mt-f-title" maxlength="255" />
          </div>
          <div class="mt-field">
            <label>Short Description</label>
            <textarea id="mt-f-short" rows="3" maxlength="500"></textarea>
          </div>
          <div class="mt-field two">
            <div>
              <label>Online Link</label>
              <input type="url" id="mt-f-link" placeholder="https://..." />
            </div>
            <div>
              <label>Location</label>
              <input type="text" id="mt-f-location" maxlength="255" />
            </div>
          </div>
          <div class="mt-field">
            <label>Start Time <span class="req">*</span></label>
            <input type="datetime-local" id="mt-f-start" />
          </div>
          <input type="hidden" id="mt-f-id" value="" />
        </div>
        <div class="mt-modal-footer">
          <button class="ghost mt-cancel">Cancel</button>
          <button class="primary mt-save">Save</button>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script defer src="../assets/js/project_tab_meetings.js?v=<?= htmlspecialchars((string)$jsVer) ?>"></script>
