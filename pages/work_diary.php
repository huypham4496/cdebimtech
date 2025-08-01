<?php
session_start();
require_once __DIR__ . '/../config.php';
$root  = dirname(__DIR__);

// — DB Connection —
$pdo = new PDO(
  "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
  DB_USER, DB_PASS,
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);

// — Auth & Company —
$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
  header('Location: login.php');
  exit;
}
$stmt = $pdo->prepare("
  SELECT s.allow_work_diary, u.company
    FROM users u
    JOIN subscriptions s ON u.subscription_id=s.id
   WHERE u.id=?
");
$stmt->execute([$userId]);
$row     = $stmt->fetch();
$company = $row['company'];

// — Colleagues —
$colleagues = [];
if ($company) {
  $cq = $pdo->prepare("
    SELECT id, first_name, last_name, email
      FROM users
     WHERE company=? AND id<>?
  ");
  $cq->execute([$company, $userId]);
  $colleagues = $cq->fetchAll();
}

// — Date context —
$month = $_GET['month'] ?? date('n');
$year  = $_GET['year']  ?? date('Y');
$date  = $_GET['date']  ?? date('Y-m-d');
$prev  = (new DateTime("$year-$month-01"))->modify('-1 month');
$next  = (new DateTime("$year-$month-01"))->modify('+1 month');

// — Handle POST —
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (isset($_POST['export_excel'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition:attachment;filename="work_diary_'.$month.'_'.$year.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['Date','Period','Task']);
    $ents = $pdo->prepare("
      SELECT entry_date,period,content
        FROM work_diary_entries
       WHERE user_id=? AND MONTH(entry_date)=? AND YEAR(entry_date)=?
    ");
    $ents->execute([$userId,$month,$year]);
    while($e=$ents->fetch()){
      fputcsv($out,[$e['entry_date'],$e['period'],$e['content']]);
    }
    fclose($out); exit;
  }
  if (isset($_POST['send_report'])) {
    // … mailing logic …
    $notifyMsg = "Report sent.";
  }
  if (isset($_POST['save_diary'])) {
    $up  = $pdo->prepare("
      REPLACE INTO work_diary_entries (user_id,entry_date,period,content)
      VALUES (?,?,?,?)
    ");
    $del = $pdo->prepare("
      DELETE FROM work_diary_entries
       WHERE user_id=? AND entry_date=? AND period=?
    ");

    foreach (['morning','afternoon'] as $prd) {
      $break = !empty($_POST["{$prd}_break"]);
      $late  = !empty($_POST["{$prd}_late"]);
      $txt   = trim($_POST["{$prd}_task"] ?? '');
      if ($break) {
        $up->execute([$userId,$date,$prd,'Break']);
      } elseif ($late) {
        $up->execute([$userId,$date,$prd,"Late: {$txt}"]);
      } elseif ($txt!=='') {
        $up->execute([$userId,$date,$prd,$txt]);
      } else {
        $del->execute([$userId,$date,$prd]);
      }
    }

    $eb = !empty($_POST['evening_break']);
    if ($eb) {
      $up->execute([$userId,$date,'evening','Break']);
    } else {
      $ts = $_POST['evening_start'] ?? '17:00';
      $te = $_POST['evening_end']   ?? '19:30';
      $tk = trim($_POST['evening_task'] ?? '');
      if ($tk!=='') {
        $up->execute([$userId,$date,'evening',"{$ts}-{$te}: {$tk}"]);
      } else {
        $del->execute([$userId,$date,'evening']);
      }
    }

    $saveMsg = "Successfully updated";
  }
}

// — Load Today's entries —
$f = $pdo->prepare("
  SELECT period,content
    FROM work_diary_entries
   WHERE user_id=? AND entry_date=?
");
$f->execute([$userId,$date]);
$rows = $f->fetchAll();
$diary = [];
foreach ($rows as $r) {
  $diary[$r['period']] = $r['content'];
}

// — Build calendar weeks —
$first = new DateTime("$year-$month-01");
$days  = (int)$first->format('t');
$weeks = []; $w = array_fill(1,7,null);
for ($i=1; $i<=$days; $i++) {
  $d = (int)(new DateTime("$year-$month-$i"))->format('N');
  $w[$d] = $i;
  if ($d===7 || $i===$days) {
    $weeks[] = $w;
    $w = array_fill(1,7,null);
  }
}

// — Prepare header values —
$current = new DateTime($date);
$dayNum  = $current->format('j');
$monthName = strtolower($current->format('F'));

// — Render —
$vS = filemtime(__DIR__.'/../assets/css/sidebar.css');
$vD = filemtime(__DIR__.'/../assets/css/work_diary.css');
include $root.'/includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/sidebar.css?v=<?= $vS ?>" />
<link rel="stylesheet" href="../assets/css/work_diary.css?v=<?= $vD ?>" />
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css"
      integrity="sha512-…"
      crossorigin="anonymous"
      referrerpolicy="no-referrer"/>

<?php include __DIR__.'/sidebar.php'; ?>

<?php if (!empty($saveMsg)): ?>
  <div class="alert-abs"><?= htmlspecialchars($saveMsg) ?></div>
<?php endif; ?>

<div class="main-content">
  <!-- Calendar -->
  <div class="calendar-container card-block" id="calendar">
    <div class="calendar-header">
      <div class="calendar-day"><?= $dayNum ?></div>
      <div class="calendar-month"><?= $monthName ?></div>
      <div class="nav">
        <button onclick="location.href='?month=<?= $prev->format('n') ?>&year=<?= $prev->format('Y') ?>&date=<?= $date ?>'">&lt;</button>
        <button onclick="location.href='?month=<?= $next->format('n') ?>&year=<?= $next->format('Y') ?>&date=<?= $date ?>'">&gt;</button>
      </div>
    </div>
    <table class="calendar">
      <thead>
        <tr>
          <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
            <th><?= $d ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($weeks as $w): ?>
          <tr>
            <?php for ($d=1; $d<=7; $d++): ?>
              <?php if ($w[$d]): 
                $ds = sprintf('%04d-%02d-%02d',$year,$month,$w[$d]);
                $sel = $ds===$date?'selected':''; ?>
                <td class="<?= $sel ?>">
                  <a href="?month=<?= $month ?>&year=<?= $year ?>&date=<?= $ds ?>">
                    <?= $w[$d] ?>
                  </a>
                </td>
              <?php else: ?>
                <td class="empty"></td>
              <?php endif; endfor; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form method="post" class="entry-panel" id="entryPanel">
    <!-- Notify Panel -->
    <div class="card-block notify-panel">
      <div class="company-label">Company:<span><?= htmlspecialchars($company) ?></span></div>
      <div class="notify-label">Notify colleagues:</div>
      <div class="colleague-list">
        <?php foreach ($colleagues as $c): ?>
          <label>
            <input type="checkbox" name="notify_users[]" value="<?= $c['id'] ?>">
            <?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?>
            <small><?= htmlspecialchars($c['email']) ?></small>
          </label>
        <?php endforeach; ?>
      </div>
      <button class="btn-send-report" name="send_report">
        <i class="fas fa-bell"></i> Send Report
      </button>
    </div>

    <!-- Morning & Afternoon -->
    <?php foreach (['morning','afternoon'] as $prd):
      $val = $diary[$prd] ?? '';
      $isB = $val==='Break';
      $isL = (!$isB && preg_match('/^Late:\s*(.*)$/',$val,$m));
      $txt = $isL ? $m[1] : $val;
    ?>
      <div class="card-block period" data-period="<?= $prd ?>">
        <label><?= ucfirst($prd) ?></label>
        <button type="button" class="btn-toggle break <?= $isB?'active':'' ?>">Break</button>
        <button type="button" class="btn-toggle late  <?= $isL?'active':'' ?>">Late</button>
        <textarea name="<?= $prd ?>_task" class="autoexpand" <?= $isB?'disabled':'' ?>><?= htmlspecialchars($txt) ?></textarea>
        <input type="hidden" name="<?= $prd ?>_break" value="<?= $isB?1:0 ?>">
        <input type="hidden" name="<?= $prd ?>_late"  value="<?= $isL?1:0 ?>">
      </div>
    <?php endforeach; ?>

    <!-- Evening -->
    <?php
      $e    = $diary['evening'] ?? '';
      $isEB = $e==='Break';
      if (!$isEB && preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2}):\s*(.*)$/',$e,$z)) {
        [, $ts, $te, $tkt] = $z;
      } else {
        $ts='17:00'; $te='19:30'; $tkt='';
      }
    ?>
    <div class="card-block period evening" data-period="evening">
      <label>Evening</label>
      <button type="button" class="btn-toggle break <?= $isEB?'active':'' ?>">Break</button>
      <input type="hidden" name="evening_break" value="<?= $isEB?1:0 ?>">
      <input type="time"  name="evening_start" class="start" value="<?= $ts ?>" <?= $isEB?'disabled':'' ?>>
      <input type="time"  name="evening_end"   class="end"   value="<?= $te ?>" <?= $isEB?'disabled':'' ?>>
      <textarea name="evening_task" class="autoexpand" <?= $isEB?'disabled':'' ?>><?= htmlspecialchars($tkt) ?></textarea>
    </div>

    <!-- Actions -->
    <div class="actions">
      <button type="submit" name="export_excel" class="export">
        <i class="fas fa-file-excel"></i> Export Excel
      </button>
      <button type="submit" name="save_diary" class="save">
        <i class="fas fa-save"></i> Save
      </button>
    </div>
  </form>
</div>

<script>
// Toggle Break/Late
document.querySelectorAll('.period').forEach(panel => {
  const pr    = panel.dataset.period;
  const ta    = panel.querySelector('textarea');
  const hb    = panel.querySelector(`[name="${pr}_break"]`);
  const hl    = panel.querySelector(`[name="${pr}_late"]`);
  const times = panel.querySelectorAll('input[type="time"]');

  panel.querySelectorAll('.btn-toggle.break').forEach(btn => {
    btn.addEventListener('click', () => {
      const on = btn.classList.toggle('active');
      hb.value = on ? 1 : 0;
      ta.disabled = on;
      times.forEach(i => i.disabled = on);
      if (on) ta.value = '';
    });
  });
  panel.querySelectorAll('.btn-toggle.late').forEach(btn => {
    btn.addEventListener('click', () => {
      const on = btn.classList.toggle('active');
      hl.value = on ? 1 : 0;
      if (on) {
        panel.querySelector('.btn-toggle.break.active')?.classList.remove('active');
        hb.value = 0;
        times.forEach(i => i.disabled = false);
      }
    });
  });
});

// Auto-expand textareas
document.querySelectorAll('textarea.autoexpand').forEach(t => {
  t.style.overflow = 'hidden';
  const resize = () => {
    t.style.height = 'auto';
    t.style.height = t.scrollHeight + 'px';
  };
  t.addEventListener('input', resize);
  resize();
});

// Match calendar height
function matchHeight() {
  const cal = document.getElementById('calendar'),
        ep  = document.getElementById('entryPanel');
  if (cal && ep) cal.style.height = ep.offsetHeight + 'px';
}
window.addEventListener('load', matchHeight);
window.addEventListener('resize', matchHeight);
</script>
