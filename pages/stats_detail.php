<?php
// pages/stats_days_off_detail.php
session_start();
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

// --- Params ---
$viewUser    = isset($_GET['uid'])   ? (int)$_GET['uid']   : $_SESSION['user']['id'];
$month       = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year        = isset($_GET['year'])  ? (int)$_GET['year']  : date('Y');
$startDate   = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth = (int)date('t', strtotime($startDate));
$endDate     = date('Y-m-t', strtotime($startDate));

// --- DB connect & fetch name ---
$pdo = new PDO(
    "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = :uid");
$stmt->execute([':uid'=>$viewUser]);
$user = $stmt->fetch();
$fullName = $user ? "{$user['first_name']} {$user['last_name']}" : "User #{$viewUser}";

// --- Fetch entries ---
$stmt = $pdo->prepare("
    SELECT entry_date, period, content
    FROM work_diary_entries
    WHERE user_id = :uid
      AND entry_date BETWEEN :start AND :end
");
$stmt->execute([':uid'=>$viewUser,':start'=>$startDate,':end'=>$endDate]);
$rows = $stmt->fetchAll();

// --- Classify ---
$workEntries    = [];
$holidayEntries = [];
$holidayDates   = [];
foreach ($rows as $r) {
    $d = (new DateTime($r['entry_date']))->format('Y-m-d');
    $c = trim($r['content']);
    // pure "Nghỉ lễ" or "Ngày lễ" => skip entirely
    if (preg_match('/^(Nghỉ lễ|Ngày lễ)\s*$/iu', $c)) {
        continue;
    }
    // special holiday-work: starts with those but has extra
    if (preg_match('/^(Nghỉ lễ|Ngày lễ)\b/iu', $c)) {
        $holidayEntries[$d][] = $r['period'];
        $holidayDates[$d]     = true;
        continue;
    }
    // skip any other "Nghỉ..." (e.g. Nghỉ mát)
    if (preg_match('/^\s*Nghỉ\b/iu', $c)) {
        continue;
    }
    // normal work
    $workEntries[$d][] = $r['period'];
}

// --- Build date arrays ---
$periodRange = new DatePeriod(
    new DateTime($startDate),
    new DateInterval('P1D'),
    (new DateTime($endDate))->modify('+1 day')
);
$prodDates = []; $otEven = []; $wkndDates = [];
foreach ($periodRange as $dt) {
    $d = $dt->format('Y-m-d');
    $w = (int)$dt->format('N');
    if ($w >= 1 && $w <= 6) $prodDates[]  = $d;
    $otEven[]    = $d;
    if ($w >= 6) $wkndDates[] = $d;
}
foreach (array_keys($holidayDates) as $d) {
    if (!in_array($d, $wkndDates, true)) {
        $wkndDates[] = $d;
    }
}
sort($wkndDates);

// --- Render helpers ---
function prodCell($date, $work, $holidayDates, $weekday) {
    if (isset($holidayDates[$date])) return '';
    $p = $work[$date] ?? [];
    if ($weekday === 6) {
        return in_array('morning', $p, true) ? 'K/2' : '';
    }
    if ($weekday === 7) {
        return '';
    }
    $m = in_array('morning',   $p, true);
    $a = in_array('afternoon', $p, true);
    if ($m && $a) return 'K';
    if ($m || $a) return 'K/2';
    return '';
}
function eveCell($date, $work, $holiday) {
    $p = array_merge($work[$date] ?? [], $holiday[$date] ?? []);
    return in_array('evening', $p, true) ? 'K/2' : '';
}
function wkndCell($date, $work, $holiday, $weekday) {
    if (isset($holiday[$date])) {
        $p = $holiday[$date];
        $m = in_array('morning',   $p, true);
        $a = in_array('afternoon', $p, true);
        if ($m && $a) return 'K';
        if ($m || $a) return 'K/2';
        return '';
    }
    $p = $work[$date] ?? [];
    if ($weekday === 6) {
        return in_array('afternoon', $p, true) ? 'K/2' : '';
    }
    if ($weekday === 7) {
        $m = in_array('morning',   $p, true);
        $a = in_array('afternoon', $p, true);
        if ($m && $a) return 'CN';
        if ($m || $a) return 'CN/2';
        return '';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Chi tiết công – <?= htmlspecialchars($fullName) ?></title>
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>
  <link rel="stylesheet" href="../assets/css/sidebar.css">
  <link rel="stylesheet" href="../assets/css/stats_detail.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <h1><i class="fas fa-chart-line"></i>
      <?= "Chi tiết công tháng ".sprintf('%02d',$month)."/{$year} – {$fullName}" ?>
    </h1>

    <!-- 1) Sản phẩm -->
    <section class="card">
      <h2><i class="fas fa-box-open"></i> Sản phẩm (T2–T7)</h2>
      <table class="stats-table">
        <thead><tr>
          <?php for ($i=1; $i<=31; $i++):
            $date = sprintf('%04d-%02d-%02d',$year,$month,$i);
            $w    = (int)date('N',strtotime($date));
            $cls  = [];
            if ($i > $daysInMonth)        $cls[]='disabled';
            if ($w===7)                   $cls[]='sun';
            if (isset($holidayDates[$date])) $cls[]='holiday';
          ?>
            <th class="<?= implode(' ',$cls) ?>"><?= sprintf('%02d',$i) ?></th>
          <?php endfor; ?>
        </tr></thead>
        <tbody><tr>
          <?php for ($i=1; $i<=31; $i++):
            $date = sprintf('%04d-%02d-%02d',$year,$month,$i);
            $w    = (int)date('N',strtotime($date));
            $cls  = $i > $daysInMonth ? 'disabled' : '';
            $cell = prodCell($date,$workEntries,$holidayDates,$w);
            echo "<td class=\"$cls\">$cell</td>";
          endfor; ?>
        </tr></tbody>
      </table>
    </section>

    <!-- 2) Làm thêm giờ -->
    <section class="card">
      <h2><i class="fas fa-clock"></i> Làm thêm giờ</h2>

      <h3>Buổi tối (T2–CN)</h3>
      <table class="stats-table">
        <thead><tr>
          <?php for ($i=1; $i<=31; $i++):
            $date = sprintf('%04d-%02d-%02d',$year,$month,$i);
            $w    = (int)date('N',strtotime($date));
            $cls  = [];
            if ($i > $daysInMonth)        $cls[]='disabled';
            if ($w===7)                   $cls[]='sun';
            if (isset($holidayDates[$date])) $cls[]='holiday';
          ?>
            <th class="<?= implode(' ',$cls) ?>"><?= sprintf('%02d',$i) ?></th>
          <?php endfor; ?>
        </tr></thead>
        <tbody><tr>
          <?php for ($i=1; $i<=31; $i++):
            $date = sprintf('%04d-%02d-%02d',$year,$month,$i);
            $cls  = $i > $daysInMonth ? 'disabled' : '';
            $cell = eveCell($date,$workEntries,$holidayEntries);
            echo "<td class=\"$cls\">$cell</td>";
          endfor; ?>
        </tr></tbody>
      </table>

      <h3>Chiều T7, CN & Ngày lễ</h3>
      <table class="stats-table">
        <thead><tr>
          <?php for ($i=1; $i<=31; $i++):
            $date = sprintf('%04d-%02d-%02d',$year,$month,$i);
            $w    = (int)date('N',strtotime($date));
            $cls  = [];
            if ($i > $daysInMonth)        $cls[]='disabled';
            if ($w===7)                   $cls[]='sun';
            if (isset($holidayDates[$date])) $cls[]='holiday';
          ?>
            <th class="<?= implode(' ',$cls) ?>"><?= sprintf('%02d',$i) ?></th>
          <?php endfor; ?>
        </tr></thead>
        <tbody><tr>
          <?php for ($i=1; $i<=31; $i++):
            $date = sprintf('%04d-%02d-%02d',$year,$month,$i);
            $w    = (int)date('N',strtotime($date));
            $cls  = $i > $daysInMonth ? 'disabled' : '';
            $cell = wkndCell($date,$workEntries,$holidayEntries,$w);
            echo "<td class=\"$cls\">$cell</td>";
          endfor; ?>
        </tr></tbody>
      </table>
    </section>
  </div>
</body>
</html>
