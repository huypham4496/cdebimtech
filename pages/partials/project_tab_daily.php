<?php
/**
 * Daily Logs Tab (CDE - PHP Partial)
 * - Requires: $pdo, $projectId from project_view.php
 * - Location: pages/partials/project_tab_daily.php
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($pdo) || !isset($projectId)) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'Missing $pdo or $projectId']);
  exit;
}

$currentUserId = $_SESSION['user']['id'] ?? $_SESSION['auth']['id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
$action = $_GET['action'] ?? null;

function json_out($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

function is_member($pdo, $projectId, $userId) {
  $stmt = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND user_id=? LIMIT 1");
  $stmt->execute([$projectId, $userId]);
  return (bool) $stmt->fetchColumn();
}

function assert_member($pdo, $projectId, $userId) {
  if (!is_member($pdo, $projectId, $userId)) {
    json_out(['ok' => false, 'message' => 'Access denied: not a member'], 403);
  }
}

function get_project_code($pdo, $projectId) {
  $stmt = $pdo->prepare("SELECT code FROM projects WHERE id=?");
  $stmt->execute([$projectId]);
  return $stmt->fetchColumn();
}

if ($action === 'list') {
  $q = trim($_GET['q'] ?? '');
  $sql = "SELECT d.*, u.first_name, u.last_name, g.name AS group_name
          FROM project_daily_logs d
          LEFT JOIN users u ON u.id = d.created_by
          LEFT JOIN project_groups g ON g.id = d.approval_group_id
          WHERE d.project_id = ?";
  $params = [$projectId];
  if ($q !== '') {
    $sql .= " AND d.name LIKE ?";
    $params[] = "%$q%";
  }
  $sql .= " ORDER BY d.entry_date DESC, d.id DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  json_out(['ok' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  assert_member($pdo, $projectId, $currentUserId);
  $id = (int) ($_POST['id'] ?? 0);
  if (!$id) json_out(['ok' => false, 'message' => 'Invalid log ID'], 400);

  try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT approval_group_id, is_approved FROM project_daily_logs WHERE id=? AND project_id=? FOR UPDATE");
    $stmt->execute([$id, $projectId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$log) json_out(['ok' => false, 'message' => 'Log not found'], 404);
    if ($log['is_approved']) json_out(['ok' => false, 'message' => 'Already approved'], 409);

    $groupId = (int) $log['approval_group_id'];
    $stmt = $pdo->prepare("SELECT 1 FROM project_group_members WHERE project_id=? AND group_id=? AND user_id=? LIMIT 1");
    $stmt->execute([$projectId, $groupId, $currentUserId]);
    if (!$stmt->fetchColumn()) json_out(['ok' => false, 'message' => 'Not in approval group'], 403);

    $stmt = $pdo->prepare("UPDATE project_daily_logs SET is_approved=1, approved_by=?, approved_at=NOW() WHERE id=?");
    $stmt->execute([$currentUserId, $id]);

    $pdo->commit();
    json_out(['ok' => true, 'message' => 'Approved successfully.']);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_out(['ok' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
  }
}

/* Default fallback */
if (!$action) {
  ?>
  <link rel="stylesheet" href="/assets/css/project_tab_daily.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />

  <div id="daily-logs-root" data-project-id="<?= htmlspecialchars($projectId) ?>" data-current-user="<?= htmlspecialchars((string)$currentUserId) ?>">
    <div class="daily-toolbar">
      <button id="btn-create-daily" class="btn primary">
        <i class="fas fa-plus"></i> Create
      </button>
      <button id="btn-export" class="btn">
        <i class="fas fa-file-export"></i> Export CSV
      </button>
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input id="daily-search" type="text" placeholder="Search by log name...">
      </div>
    </div>
    <div class="daily-table-wrap">
      <table class="daily-table" id="daily-logs-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Date</th>
            <th>Name</th>
            <th>Creator</th>
            <th>Approval</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
  <script src="/assets/js/project_tab_daily.js"></script>
<?php } ?>