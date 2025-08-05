<?php
// pages/stats_org_detail.php
session_start();
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

// — Params —
$orgId       = isset($_GET['org_id']) ? (int)$_GET['org_id'] : null;
$month       = isset($_GET['month'])  ? (int)$_GET['month']  : date('n');
$year        = isset($_GET['year'])   ? (int)$_GET['year']   : date('Y');
$startDate   = sprintf('%04d-%02d-01', $year, $month);
$endDate     = date('Y-m-t', strtotime($startDate));
$daysInMonth = (int)date('t', strtotime($startDate));

// — DB & fetch org name —
$pdo = new PDO(
  "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
  DB_USER, DB_PASS,
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);
$o = $pdo->prepare("SELECT name FROM organizations WHERE id = :oid");
$o->execute([':oid'=>$orgId]);
$orgName = $o->fetchColumn() ?: '—';

// — Helper to fetch one user’s diary entries for the month —
function fetchEntries($uid, $start, $end) {
    global $pdo;
    $s = $pdo->prepare("
      SELECT entry_date, period, content
        FROM work_diary_entries
       WHERE user_id = :uid
         AND entry_date BETWEEN :start AND :end
    ");
    $s->execute([':uid'=>$uid, ':start'=>$start, ':end'=>$end]);
    return $s->fetchAll();
}

// — Fetch members —
$ms = $pdo->prepare("
  SELECT u.id, COALESCE(p.full_name, u.email) AS full_name
    FROM organization_members m
    JOIN users u ON u.id = m.user_id
LEFT JOIN organization_member_profiles p ON p.member_id = m.id
   WHERE m.organization_id = :oid
   ORDER BY u.email
");
$ms->execute([':oid'=>$orgId]);
$members = $ms->fetchAll();

// — Build org-level holiday dates (union of all members) —
$orgHolidayDates = [];
foreach ($members as $m) {
    $ents = fetchEntries($m['id'], $startDate, $endDate);
    foreach ($ents as $r) {
        $d = (new DateTime($r['entry_date']))->format('Y-m-d');
        if (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu', trim($r['content']))) {
            $orgHolidayDates[$d] = true;
        }
    }
}

// — Render helpers —
function prodCell($date, $work, $holidays, $weekday) {
    if (isset($holidays[$date])) return '';
    $p = $work[$date] ?? [];
    if ($weekday === 6) return in_array('morning',$p,true)?'K/2':'';
    if ($weekday === 7) return '';
    $m = in_array('morning',$p,true);
    $a = in_array('afternoon',$p,true);
    if ($m && $a) return 'K';
    if ($m || $a) return 'K/2';
    return '';
}
function eveCell($date, $work, $holidays) {
    $p = array_merge($work[$date] ?? [], $holidays[$date] ?? []);
    return in_array('evening',$p,true)?'K/2':'';
}
function wkndCell($date, $work, $holidays, $weekday) {
    if (isset($holidays[$date])) {
        $p = $holidays[$date];
        $m = in_array('morning',$p,true);
        $a = in_array('afternoon',$p,true);
        if ($m && $a) return 'K';
        if ($m || $a) return 'K/2';
        return '';
    }
    $p = $work[$date] ?? [];
    if ($weekday === 6) return in_array('afternoon',$p,true)?'K/2':'';
    if ($weekday === 7) {
        $m = in_array('morning',$p,true);
        $a = in_array('afternoon',$p,true);
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
  <title>Thống kê <?= htmlspecialchars($orgName) ?> – <?= sprintf('%02d/%04d',$month,$year) ?></title>
  <link rel="stylesheet" href="../assets/css/sidebar.css">
  <link rel="stylesheet" href="../assets/css/stats_org_detail.css">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        crossorigin="anonymous" />
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <h1>
      <i class="fas fa-chart-bar"></i>
      Thống kê <?= htmlspecialchars($orgName) ?>
      (<?= sprintf('%02d/%04d',$month,$year) ?>)
    </h1>

    <!-- 1) Sản phẩm -->
    <section class="card">
      <h2><i class="fas fa-box-open"></i> Sản phẩm (T2–T7)</h2>
      <table class="stats-table">
        <thead>
          <tr>
            <th>Thành viên</th>
            <?php for ($d=1; $d<=31; $d++):
              $date = sprintf('%04d-%02d-%02d',$year,$month,$d);
              $w     = (int)date('N', strtotime($date));
              $cls   = [];
              if ($d > $daysInMonth)          $cls[] = 'disabled';
              if ($w === 7)                   $cls[] = 'sun';
              if (isset($orgHolidayDates[$date])) $cls[] = 'holiday';
              $cls = implode(' ',$cls);
            ?>
              <th class="<?= $cls ?>"><?= sprintf('%02d',$d) ?></th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $m):
            $entries  = fetchEntries($m['id'], $startDate, $endDate);
            $work     = []; $holidays = [];
            foreach ($entries as $r) {
                $d = (new DateTime($r['entry_date']))->format('Y-m-d');
                $c = trim($r['content']);
                if (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu',$c)) {
                    $holidays[$d][] = $r['period'];
                } elseif (!preg_match('/^\s*Nghỉ\b/iu',$c)) {
                    $work[$d][] = $r['period'];
                }
            }
          ?>
          <tr>
            <td style="text-align:left; white-space:normal;"><?= htmlspecialchars($m['full_name']) ?></td>
            <?php for ($d=1; $d<=31; $d++):
              $date = sprintf('%04d-%02d-%02d',$year,$month,$d);
              $w     = (int)date('N', strtotime($date));
              $cls   = $d > $daysInMonth ? 'disabled' : '';
              $val   = prodCell($date, $work, $holidays, $w);
            ?>
              <td class="<?= $cls ?>"><?= $val ?></td>
            <?php endfor; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <!-- 2) Buổi tối -->
    <section class="card">
      <h2><i class="fas fa-clock"></i> Buổi tối (T2–CN)</h2>
      <table class="stats-table">
        <thead>
          <tr>
            <th>Thành viên</th>
            <?php for ($d=1; $d<=31; $d++):
              $date = sprintf('%04d-%02d-%02d',$year,$month,$d);
              $w     = (int)date('N', strtotime($date));
              $cls   = [];
              if ($d > $daysInMonth)          $cls[] = 'disabled';
              if ($w === 7)                   $cls[] = 'sun';
              if (isset($orgHolidayDates[$date])) $cls[] = 'holiday';
              $cls = implode(' ',$cls);
            ?>
              <th class="<?= $cls ?>"><?= sprintf('%02d',$d) ?></th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $m):
            $entries  = fetchEntries($m['id'], $startDate, $endDate);
            $work     = []; $holidays = [];
            foreach ($entries as $r) {
                $d = (new DateTime($r['entry_date']))->format('Y-m-d');
                $c = trim($r['content']);
                if (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu',$c)) {
                    $holidays[$d][] = $r['period'];
                } elseif (!preg_match('/^\s*Nghỉ\b/iu',$c)) {
                    $work[$d][] = $r['period'];
                }
            }
          ?>
          <tr>
            <td style="text-align:left; white-space:normal;"><?= htmlspecialchars($m['full_name']) ?></td>
            <?php for ($d=1; $d<=31; $d++):
              $date = sprintf('%04d-%02d-%02d',$year,$month,$d);
              $w     = (int)date('N', strtotime($date));
              $cls   = $d > $daysInMonth ? 'disabled' : '';
              $val   = eveCell($date, $work, $holidays);
            ?>
              <td class="<?= $cls ?>"><?= $val ?></td>
            <?php endfor; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <!-- 3) Chiều T7, CN & Ngày lễ -->
    <section class="card">
      <h2><i class="fas fa-calendar-day"></i> Chiều T7, CN & Ngày lễ</h2>
      <table class="stats-table">
        <thead>
          <tr>
            <th>Thành viên</th>
            <?php for ($d=1; $d<=31; $d++):
              $date = sprintf('%04d-%02d-%02d',$year,$month,$d);
              $w     = (int)date('N', strtotime($date));
              $cls   = [];
              if ($d > $daysInMonth)          $cls[] = 'disabled';
              if ($w === 7)                   $cls[] = 'sun';
              if (isset($orgHolidayDates[$date])) $cls[] = 'holiday';
              $cls = implode(' ',$cls);
            ?>
              <th class="<?= $cls ?>"><?= sprintf('%02d',$d) ?></th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $m):
            $entries  = fetchEntries($m['id'], $startDate, $endDate);
            $work     = []; $holidays = [];
            foreach ($entries as $r) {
                $d = (new DateTime($r['entry_date']))->format('Y-m-d');
                $c = trim($r['content']);
                if (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu',$c)) {
                    $holidays[$d][] = $r['period'];
                } elseif (!preg_match('/^\s*Nghỉ\b/iu',$c)) {
                    $work[$d][] = $r['period'];
                }
            }
          ?>
          <tr>
            <td style="text-align:left; white-space:normal;"><?= htmlspecialchars($m['full_name']) ?></td>
            <?php for ($d=1; $d<=31; $d++):
              $date = sprintf('%04d-%02d-%02d',$year,$month,$d);
              $w     = (int)date('N', strtotime($date));
              $cls   = $d > $daysInMonth ? 'disabled' : '';
              $val   = wkndCell($date, $work, $holidays, $w);
            ?>
              <td class="<?= $cls ?>"><?= $val ?></td>
            <?php endfor; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
<!-- Xuất Excel nút dưới cùng -->
    <?php
      $exportUrl = sprintf(
        'export_stats_org_excel.php?org_id=%d&month=%d&year=%d',
        $orgId, $month, $year
      );
    ?>
    <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn-export">
      <i class="fas fa-file-excel"></i> Xuất Excel
    </a>

  </div>
</body>
</html>

