<?php
// pages/notifications.php
session_start();
require_once __DIR__ . '/../config.php';

// — PDO Connection —
$pdo = new PDO(
  "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
  DB_USER, DB_PASS,
  [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]
);

// — Auth check —
$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}

// — Mark all as read —
$mark = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE receiver_id = :uid");
$mark->execute([':uid' => $userId]);

// — Fetch notifications —
$q = $pdo->prepare("
    SELECT
      n.id,
      n.sender_id,
      n.entry_date,
      n.created_at,
      n.is_read,
      u.first_name,
      u.last_name
    FROM notifications n
    JOIN users u ON u.id = n.sender_id
    WHERE n.receiver_id = :uid
    ORDER BY n.created_at DESC
");
$q->execute([':uid' => $userId]);
$notes = $q->fetchAll();

// — Header & Sidebar & CSS —
$verCSS = filemtime(__DIR__ . '/../assets/css/notifications.css');
include dirname(__DIR__) . '/includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/sidebar.css?v=<?= $verCSS ?>">
<link rel="stylesheet" href="../assets/css/notifications.css?v=<?= $verCSS ?>">
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
  <div class="card-block">
    <h2>Thông báo của bạn</h2>

    <?php if (empty($notes)): ?>
      <p class="no-data">Bạn chưa có thông báo nào.</p>
    <?php else: ?>
      <ul class="notifications-list">
        <?php foreach ($notes as $n): 
            $cls = $n['is_read'] ? 'read' : 'unread';
        ?>
          <li class="<?= $cls ?>">
            <strong><?= htmlspecialchars("$n[first_name] $n[last_name]", ENT_QUOTES) ?></strong>
            gửi nhật ký công việc ngày
            <em><?= date('d/m/Y', strtotime($n['entry_date'])) ?></em>
            lúc <?= date('H:i d/m/Y', strtotime($n['created_at'])) ?>.
            <!-- chuyển sang stats_days_off.php với sender_id -->
            <a href="stats_days_off.php?uid=<?= $n['sender_id'] ?>">
              Xem thống kê
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</main>
