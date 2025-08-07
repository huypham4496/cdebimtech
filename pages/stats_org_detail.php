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

// — DB Connection —
$pdo = new PDO(
  "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
  DB_USER, DB_PASS,
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);

// — Fetch organization name —
$o = $pdo->prepare("SELECT name FROM organizations WHERE id = :oid");
$o->execute([':oid'=>$orgId]);
$orgName = $o->fetchColumn() ?: '—';

// — Helper: fetch diary entries —
function fetchEntries($uid, $start, $end) {
    global $pdo;
    $s = $pdo->prepare(
      "SELECT entry_date, period, content
         FROM work_diary_entries
        WHERE user_id = :uid
          AND entry_date BETWEEN :start AND :end"
    );
    $s->execute([':uid'=>$uid, ':start'=>$start, ':end'=>$end]);
    return $s->fetchAll();
}

// — Fetch members —
$ms = $pdo->prepare(
  "SELECT u.id, COALESCE(p.full_name, u.email) AS full_name, COALESCE(p.position,'') AS position
     FROM organization_members m
     JOIN users u ON u.id = m.user_id
LEFT JOIN organization_member_profiles p ON p.member_id = m.id
    WHERE m.organization_id = :oid
 ORDER BY u.email"
);
$ms->execute([':oid'=>$orgId]);
$members = $ms->fetchAll();

// — Build array of org-level holiday dates —
$orgHolidayDates = [];
foreach ($members as $m) {
    foreach (fetchEntries($m['id'], $startDate, $endDate) as $r) {
        $d = (new DateTime($r['entry_date']))->format('Y-m-d');
        $txt = trim($r['content']);
        if (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu', $txt)) {
            $orgHolidayDates[$d] = true;
        }
    }
}

// — prodCell: Sản phẩm (T2–T7), no skip for holidays —
function prodCell($date, $work, $mixed, $weekday) {
    // mixed: "Nghỉ lễ:" entries
    // for weekday 1–5
    if ($weekday >=1 && $weekday <=5) {
        if (!empty($mixed[$date])) {
            $h = $mixed[$date];
            $m = in_array('morning', $h, true);
            $a = in_array('afternoon', $h, true);
            if ($m && $a) return 'L';
            if ($m || $a) return 'L/2';
            return '';
        }
        $w = $work[$date] ?? [];
        $m = in_array('morning', $w, true);
        $a = in_array('afternoon', $w, true);
        if ($m && $a) return 'SP';
        if ($m || $a) return 'SP/2';
        return '';
    }
    // Saturday: only morning → SP/2 or L/2
    if ($weekday === 6) {
        if (!empty($mixed[$date])) {
            return in_array('morning', $mixed[$date], true) ? 'L/2' : '';
        }
        return in_array('morning', $work[$date] ?? [], true) ? 'SP/2' : '';
    }
    // Sunday: not in prodCell
    return '';
}

// — eveCell: Buổi tối (T2–CN) —
function eveCell($date, $work, $mixed) {
    // merge work + mixed
    $p = array_merge($work[$date] ?? [], $mixed[$date] ?? []);
    return in_array('evening', $p, true) ? 'SP/2' : '';
}

// — wkndCell: Chiều T7, CN & Ngày lễ —
function wkndCell($date, $work, $mixed, $weekday) {
    // Saturday afternoon
    if ($weekday === 6) {
        if (!empty($mixed[$date])) {
            return in_array('afternoon', $mixed[$date], true) ? 'L/2' : '';
        }
        return in_array('afternoon', $work[$date] ?? [], true) ? 'SP/2' : '';
    }
    // Sunday
    if ($weekday === 7) {
        if (!empty($mixed[$date])) {
            $h=$mixed[$date];
            $m=in_array('morning',$h,true);
            $a=in_array('afternoon',$h,true);
            if ($m && $a) return 'L';
            if ($m || $a) return 'L/2';
            return '';
        }
        $w=$work[$date]??[];
        $m=in_array('morning',$w,true);
        $a=in_array('afternoon',$w,true);
        if ($m && $a) return 'CN';
        if ($m || $a) return 'CN/2';
    }
    // Holidays (org-level)
    if (isset($GLOBALS['orgHolidayDates'][$date])) {
        // if mixed had content, L/L/2 shown above
        return '';
    }
    return '';
}

?><!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Thống kê <?= htmlspecialchars($orgName) ?> – <?= sprintf('%02d/%04d',$month,$year) ?></title>
  <link rel="stylesheet" href="./../assets/css/sidebar.css">
  <link rel="stylesheet" href="./../assets/css/stats_org_detail.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" />
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <h1><i class="fas fa-chart-bar"></i> Thống kê <?= htmlspecialchars($orgName) ?> (<?= sprintf('%02d/%04d',$month,$year) ?>)</h1>

    <!-- 1) Sản phẩm (T2–T7) -->
    <section class="card">
      <h2><i class="fas fa-box-open"></i> Sản phẩm (T2–T7)</h2>
      <table class="stats-table">
        <thead><tr><th>Thành viên</th>
<?php for($d=1;$d<=$daysInMonth;$d++):
    $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
    $w=(int)date('N',strtotime($date));
    $cls=[];
    if($w===7)$cls[]='sun';
    if(isset($orgHolidayDates[$date]))$cls[]='holiday';
    $cls=trim(implode(' ',$cls));
?>
          <th class="<?= $cls ?>"><?= sprintf('%02d',$d) ?></th>
<?php endfor ?>
        </tr></thead>
        <tbody>
<?php foreach($members as $m):
    $ents=fetchEntries($m['id'],$startDate,$endDate);
    $work=[]; $mixed=[];
    foreach($ents as $r){
        $d=(new DateTime($r['entry_date']))->format('Y-m-d');
        $txt=trim($r['content']);
        if(preg_match('/^\s*Nghỉ lễ\s*:?\s*$/iu',$txt)) continue;
        if(preg_match('/^\s*Nghỉ lễ\s*:?\s*\S+/iu',$txt)){
            $mixed[$d][]=$r['period'];continue;
        }
        if(preg_match('/\bNghỉ\b/iu',$txt)) continue;
        if($r['period']==='evening') continue;
        $work[$d][]=$r['period'];
    }
?>
          <tr>
            <td style="text-align:left;"><?= htmlspecialchars($m['full_name']) ?></td>
<?php for($d=1;$d<=$daysInMonth;$d++):
    $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
    $w=(int)date('N',strtotime($date));
    $val=prodCell($date,$work,$mixed,$w);
    $cls=($w===7?'sun ':'').(isset($orgHolidayDates[$date])?'holiday':'');
?>
            <td class="<?= trim($cls) ?>"><?= $val ?></td>
<?php endfor ?>
          </tr>
<?php endforeach ?>
        </tbody>
      </table>
    </section>

    <!-- 2) Buổi tối (T2–CN) -->
    <section class="card">
      <h2><i class="fas fa-clock"></i> Buổi tối (T2–CN)</h2>
      <table class="stats-table">
        <thead><tr><th>Thành viên</th>
<?php for($d=1;$d<=$daysInMonth;$d++):
    $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
    $w=(int)date('N',strtotime($date));
    $cls=($w===7?'sun ':'').(isset($orgHolidayDates[$date])?'holiday':'');
?>
          <th class="<?= trim($cls) ?>"><?= sprintf('%02d',$d) ?></th>
<?php endfor ?>
        </tr></thead>
        <tbody>
<?php foreach($members as $m):
    $ents=fetchEntries($m['id'],$startDate,$endDate);
    $work=[]; $mixed=[];
    foreach($ents as $r){
        $d=(new DateTime($r['entry_date']))->format('Y-m-d');
        $txt=trim($r['content']);
        if(preg_match('/^\s*Nghỉ lễ\s*:?\s*$/iu',$txt)) continue;
        if(preg_match('/^\s*Nghỉ lễ\s*:?\s*\S+/iu',$txt)){
            $mixed[$d][]=$r['period'];continue;
        }
        if(preg_match('/\bNghỉ\b/iu',$txt)) continue;
        $work[$d][]=$r['period'];
    }
?>
          <tr>
            <td style="text-align:left;"><?= htmlspecialchars($m['full_name']) ?></td>
<?php for($d=1;$d<=$daysInMonth;$d++):
    $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
    $val=eveCell($date,$work,$mixed);
    $cls=($d>$daysInMonth?'disabled ':'').((int)date('N',strtotime($date))===7?'sun ':'').(isset($orgHolidayDates[$date])?'holiday':'');
?>
            <td class="<?= trim($cls) ?>"><?= $val ?></td>
<?php endfor ?>
          </tr>
<?php endforeach ?>
        </tbody>
      </table>
    </section>

    <!-- 3) Chiều T7, CN & Ngày lễ -->
    <section class="card">
      <h2><i class="fas fa-calendar-day"></i> Chiều T7, CN & Ngày lễ</h2>
      <table class="stats-table">
        <thead><tr><th>Thành viên</th>
<?php for($d=1;$d<=$daysInMonth;$d++):
    $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
    $w=(int)date('N',strtotime($date));
    $cls=($d>$daysInMonth?'disabled ':'').($w===7?'sun ':'').(isset($orgHolidayDates[$date])?'holiday':'');
?>
          <th class="<?= trim($cls) ?>"><?= sprintf('%02d',$d) ?></th>
<?php endfor ?>
        </tr></thead>
        <tbody>
<?php foreach($members as $m):
    $ents=fetchEntries($m['id'],$startDate,$endDate);
    $work=[]; $mixed=[];
    foreach($ents as $r){
        $d=(new DateTime($r['entry_date']))->format('Y-m-d');
        $txt=trim($r['content']);
        if(preg_match('/^\s*Nghỉ lễ\s*:?\s*$/iu',$txt)) continue;
        if(preg_match('/^\s*Nghỉ lễ\s*:?\s*\S+/iu',$txt)){
            $mixed[$d][]=$r['period'];continue;
        }
        if(preg_match('/\bNghỉ\b/iu',$txt)) continue;
        $work[$d][]=$r['period'];
    }
?>
          <tr>
            <td style="text-align:left;"><?= htmlspecialchars($m['full_name']) ?></td>
<?php for($d=1;$d<=$daysInMonth;$d++):
    $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
    $w=(int)date('N',strtotime($date));
    $val=wkndCell($date,$work,$mixed,$w);
    $cls=($d>$daysInMonth?'disabled ':'').($w===7?'sun ':'').(isset($orgHolidayDates[$date])?'holiday':'');
?>
            <td class="<?= trim($cls) ?>"><?= $val ?></td>
<?php endfor ?>
          </tr>
<?php endforeach ?>
        </tbody>
      </table>
    </section>

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
