<?php
// pages/stats_days_off.php
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
$loggedIn = $_SESSION['user']['id'] ?? null;
if (!$loggedIn) {
    header('Location: login.php');
    exit;
}

// — Determine whose stats to show —
$viewUser = isset($_GET['uid'])
    ? (int)$_GET['uid']
    : $loggedIn;

// Fetch user name
$stmtU = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = :id");
$stmtU->execute([':id' => $viewUser]);
$userInfo = $stmtU->fetch();
$fullName = $userInfo
    ? "{$userInfo['first_name']} {$userInfo['last_name']}"
    : "User #{$viewUser}";

// — Month/Year from GET or default —
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// — First/Last day of month —
$start = sprintf('%04d-%02d-01', $year, $month);
$end   = date('Y-m-t', strtotime($start));

// 1) Fetch all entries for the month
$sqlAll = "
    SELECT entry_date, period, content
    FROM work_diary_entries
    WHERE user_id    = :uid
      AND entry_date BETWEEN :start AND :end
";
$stmtAll = $pdo->prepare($sqlAll);
$stmtAll->execute([
    ':uid'   => $viewUser,
    ':start' => $start,
    ':end'   => $end,
]);
$all = $stmtAll->fetchAll();

// Build schedule map: date → [ period => content ]
$schedule = [];
foreach ($all as $row) {
    $schedule[$row['entry_date']][$row['period']] = $row['content'];
}

// 2) Count attendance types
$sqlCounts = "
    SELECT
      SUM(CASE 
            WHEN content LIKE 'Nghỉ%' 
                 AND content NOT LIKE 'Nghỉ lễ %' THEN 1 
            ELSE 0 END) AS cnt_breaks,
      SUM(CASE 
            WHEN DAYOFWEEK(entry_date) BETWEEN 2 AND 6
                 AND (content NOT LIKE 'Nghỉ%' OR content LIKE 'Nghỉ lễ %')
            THEN 1 ELSE 0 END) AS cnt_weekdays,
      SUM(CASE 
            WHEN DAYOFWEEK(entry_date)=7
                 AND (content NOT LIKE 'Nghỉ%' OR content LIKE 'Nghỉ lễ %')
            THEN 1 ELSE 0 END) AS cnt_saturdays,
      SUM(CASE 
            WHEN DAYOFWEEK(entry_date)=1
                 AND (content NOT LIKE 'Nghỉ%' OR content LIKE 'Nghỉ lễ %')
            THEN 1 ELSE 0 END) AS cnt_sundays
    FROM work_diary_entries
    WHERE user_id    = :uid
      AND entry_date BETWEEN :start AND :end
";
$stmtC = $pdo->prepare($sqlCounts);
$stmtC->execute([
    ':uid'   => $viewUser,
    ':start' => $start,
    ':end'   => $end,
]);
$cnt = $stmtC->fetch();

// Prepare data for Chart.js
$dataLabels = json_encode(['Nghỉ','Ngày thường','Thứ 7','Chủ Nhật'], JSON_UNESCAPED_UNICODE);
$dataValues = json_encode([
    (int)$cnt['cnt_breaks'],
    (int)$cnt['cnt_weekdays'],
    (int)$cnt['cnt_saturdays'],
    (int)$cnt['cnt_sundays']
]);

// Cache-busting CSS version
$ver = filemtime(__DIR__ . '/../assets/css/stats_days_off.css');

// Include header & styles
include dirname(__DIR__) . '/includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/sidebar.css?v=<?= $ver ?>">
<link rel="stylesheet" href="../assets/css/stats_days_off.css?v=<?= $ver ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
  <div class="card-block">
    <h2>
      Thống kê buổi làm việc tháng <?= sprintf('%02d',$month) ?>/<?= $year ?> 
      của <?= htmlspecialchars($fullName, ENT_QUOTES) ?>
    </h2>

    <!-- Filter form -->
    <form method="get" class="filter-form">
      <input type="hidden" name="uid" value="<?= $viewUser ?>">
      <label>
        Tháng:
        <select name="month">
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>
              <?= sprintf('%02d', $m) ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>
      <label>
        Năm:
        <select name="year">
          <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
            <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>>
              <?= $y ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>
      <button type="submit">Lọc</button>
    </form>

    <!-- Stats table -->
    <table class="stats-table week-table">
      <thead>
        <tr>
          <th>Ngày</th>
          <th>Buổi sáng</th>
          <th>Buổi chiều</th>
          <th>Buổi tối</th>
        </tr>
      </thead>
      <tbody>
        <?php
        // Helper to pick CSS class
        function getCellClass(string $content, string $period, int $dow): string {
            $c = trim($content);

            // 1) Special holiday with task: "Nghỉ lễ" followed by space or colon then text
            if (preg_match('/^Nghỉ lễ(?:(?:\s|:).+)/u', $c)) {
                return 'cell-holiday-work';
            }
            // 2) All other "Nghỉ…" (including pure "Nghỉ lễ")
            if (preg_match('/^Nghỉ\b/u', $c)) {
                return 'cell-break';
            }
            // 3) Evening entries
            if ($period === 'evening' && $c !== '') {
                return 'cell-evening';
            }
            // 4) Sunday entries
            if ($dow === 7 && $c !== '') {
                return 'cell-sunday';
            }
            // 5) Saturday afternoon
            if ($dow === 6 && $period === 'afternoon' && $c !== '') {
                return 'cell-sat-afternoon';
            }
            // 6) Weekday entries (Mon–Fri any period, Sat morning)
            if (in_array($dow, [1,2,3,4,5], true) && $c !== '') {
                return 'cell-weekday';
            }
            if ($dow === 6 && $period === 'morning' && $c !== '') {
                return 'cell-weekday';
            }

            return '';
        }

        $dt    = new DateTime($start);
        $endDt = new DateTime($end);

        while ($dt <= $endDt):
            $d   = $dt->format('Y-m-d');
            $dow = (int)$dt->format('N');

            $mor = trim($schedule[$d]['morning']   ?? '');
            $aft = trim($schedule[$d]['afternoon'] ?? '');
            $eve = trim($schedule[$d]['evening']   ?? '');

            $clsMor = getCellClass($mor, 'morning',   $dow);
            $clsAft = getCellClass($aft, 'afternoon', $dow);
            $clsEve = getCellClass($eve, 'evening',   $dow);
        ?>
        <tr>
          <td class="cell-date"><?= $dt->format('d/m (D)') ?></td>
          <td class="<?= $clsMor ?>"><?= htmlspecialchars($mor, ENT_QUOTES) ?></td>
          <td class="<?= $clsAft ?>"><?= htmlspecialchars($aft, ENT_QUOTES) ?></td>
          <td class="<?= $clsEve ?>"><?= htmlspecialchars($eve, ENT_QUOTES) ?></td>
        </tr>
        <?php
            $dt->modify('+1 day');
        endwhile;
        ?>
      </tbody>
    </table>

    <!-- Button to detail report -->
    <div class="detail-button">
      <a href="stats_days_off_detail.php?uid=<?= $viewUser ?>&month=<?= $month ?>&year=<?= $year ?>" class="btn-detail">
        <i class="fas fa-chart-line"></i> Xem chi tiết
      </a>
    </div>
