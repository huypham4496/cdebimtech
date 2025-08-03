<?php
// pages/notifications.php
session_start();
require_once __DIR__ . '/../config.php';

// — DB Connection —
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);

// — Auth check —
$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}

// — Đánh dấu tất cả notifications đã đọc —
$mark = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE receiver_id = ?");
$mark->execute([$userId]);

// — Lấy danh sách notifications —
$q = $pdo->prepare(
    "SELECT n.id, n.sender_id, n.entry_date, n.created_at,
            u.first_name, u.last_name
       FROM notifications n
       JOIN users u ON u.id = n.sender_id
      WHERE n.receiver_id = ?
      ORDER BY n.created_at DESC"
);
$q->execute([$userId]);
$notes = $q->fetchAll();

// — Render header & sidebar —
$root = dirname(__DIR__);
$vS = filemtime(__DIR__ . '/../assets/css/sidebar.css');
include $root . '/includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/notifications.css?v=<?= $vS ?>" />
<link rel="stylesheet" href="../assets/css/sidebar.css?v=<?= $vS ?>" />
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css"
      integrity="sha512-..."
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
/>
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
  <div class="card-block">
    <h2>Your Notifications</h2>
    <?php if (empty($notes)): ?>
      <p>No new notifications.</p>
    <?php else: ?>
      <ul class="notifications-list">
        <?php foreach ($notes as $n): ?>
          <li>
            <strong><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name'], ENT_QUOTES) ?></strong>
            sent a work diary on <em><?= htmlspecialchars($n['entry_date'], ENT_QUOTES) ?></em>
            at <?= htmlspecialchars($n['created_at'], ENT_QUOTES) ?>.
            <a href="work_diary_view.php?user_id=<?= $n['sender_id'] ?>&date=<?= $n['entry_date'] ?>">
              Click here to view
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</main>
