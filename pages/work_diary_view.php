<?php
session_start();
require_once __DIR__ . '/../config.php';

// Kiểm tra đăng nhập
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$currentUser = $_SESSION['user']['id'];
$viewUser    = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $currentUser;

// Kết nối DB
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Xác định tháng để xem (YYYY-MM)
if (isset($_GET['month'])) {
    $month = $_GET['month'];
} elseif (isset($_GET['date'])) {
    $month = substr($_GET['date'], 0, 7);
} else {
    $month = date('Y-m');
}
$start = $month . '-01';
$end   = date('Y-m-t', strtotime($start));

// Tiêu đề
$title = 'Work Diary of ' .
    ($viewUser === $currentUser ? 'You' : 'User #' . $viewUser) .
    ' for ' . date('F Y', strtotime($start));

// Lấy dữ liệu cả tháng
$stmt = $pdo->prepare(
    "SELECT entry_date, period, content
       FROM work_diary_entries
      WHERE user_id = ? AND entry_date BETWEEN ? AND ?
      ORDER BY entry_date, FIELD(period,'morning','afternoon','evening')"
);
$stmt->execute([$viewUser, $start, $end]);
$entries = $stmt->fetchAll();

// Nhóm dữ liệu theo ngày và period
$periods = ['morning','afternoon','evening'];
$grouped = [];
$current = $start;
while ($current <= $end) {
    $grouped[$current] = array_fill_keys($periods, []);
    $current = date('Y-m-d', strtotime($current . ' +1 day'));
}
foreach ($entries as $row) {
    $grouped[$row['entry_date']][$row['period']][] = $row['content'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/work_diary_view.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        integrity="sha512-dynvDxJ5aVF6oU1i6zfoalvVYvNvKcJste/0q5u+P%2FgPm4jG3E5s3UeJ8V+RaH59RUW2YCiMzZ6pyRrg58F3CA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="layout diary-view-page">
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    <div class="sidebar">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
    </div>
    <div class="main-content" id="diaryViewContent">
        <div class="container-fluid">
            <!-- Chọn tháng -->
            <form method="get" class="view-form">
                <input type="hidden" name="user_id" value="<?= $viewUser ?>">
                <label for="month">Select month:</label>
                <input type="month" id="month" name="month" value="<?= htmlspecialchars($month) ?>">
                <button type="submit">View Month</button>
            </form>

            <h1><?= htmlspecialchars($title) ?></h1>

            <?php foreach ($grouped as $date => $periodsArr): ?>
                <div class="day-block">
                    <h2 class="date-title"><?= date('F j, Y', strtotime($date)) ?></h2>
                    <?php foreach ($periods as $period): ?>
                        <section class="period <?= $period ?>">
                            <h3><?= ucfirst($period) ?></h3>
                            <p><?= nl2br(htmlspecialchars(implode("\n", $periodsArr[$period] ?? []))) ?></p>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script src="../assets/js/work_diary.js"></script>
</body>
</html>