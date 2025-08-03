<?php
// pages/work_diary_view.php
session_start();
require_once __DIR__ . '/../config.php';

// — DB Connection —
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);

// — Auth —
$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}

// — Params validation —
$senderId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$date     = $_GET['date'] ?? '';
if (!$senderId || !$date) {
    exit('Invalid parameters.');
}

// — Get sender info —
$u = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$u->execute([$senderId]);
$user   = $u->fetch();
$sender = htmlspecialchars("{$user['first_name']} {$user['last_name']}", ENT_QUOTES);

// — Get diary entries —
$e = $pdo->prepare(
    "SELECT period, content
       FROM work_diary_entries
      WHERE user_id = ? AND entry_date = ?
      ORDER BY FIELD(period,'morning','afternoon','evening')"
);
$e->execute([$senderId, $date]);
$entries = $e->fetchAll();

// — Render header & sidebar —
$root = dirname(__DIR__);
$vS   = filemtime(__DIR__ . '/../assets/css/sidebar.css');
$vD   = filemtime(__DIR__ . '/../assets/css/work_diary.css');
include $root . '/includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/sidebar.css?v=<?= $vS ?>" />
<link rel="stylesheet" href="../assets/css/work_diary.css?v=<?= $vD ?>" />
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css"
      integrity="sha512-..."
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
/>
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
  <div class="card-block">
    <h2>Work Diary of <?= $sender ?> on <?= htmlspecialchars($date, ENT_QUOTES) ?></h2>

    <?php if (empty($entries)): ?>
      <p>No entries found for this date.</p>
    <?php else: ?>
      <?php foreach ($entries as $row): ?>
        <section class="period <?= htmlspecialchars($row['period'], ENT_QUOTES) ?>">
          <h3><?= ucfirst(htmlspecialchars($row['period'], ENT_QUOTES)) ?></h3>
          <p><?= nl2br(htmlspecialchars($row['content'], ENT_QUOTES)) ?></p>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>
