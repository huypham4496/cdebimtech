<?php
session_start();
require_once __DIR__ . '/../config.php';
$root = dirname(__DIR__);

// — DB Connection —
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);

// — Auth & Company —
$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}
$stmt = $pdo->prepare("SELECT first_name, last_name, company FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$userName = htmlspecialchars("{$user['first_name']} {$user['last_name']}", ENT_QUOTES);
$company  = $user['company'];

// — Colleagues for notification —
$colleagues = [];
if ($company) {
    $cq = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE company = ? AND id <> ?");
    $cq->execute([$company, $userId]);
    $colleagues = $cq->fetchAll();
}

// — Date context —
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
$date  = $_GET['date'] ?? date('Y-m-d');
$prev  = (new DateTime("$year-$month-01"))->modify('-1 month');
$next  = (new DateTime("$year-$month-01"))->modify('+1 month');

// — Handle POST —
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // === Export Excel (HTML → .xls) ===
    if (isset($_POST['export_excel'])) {
        // 1) Fetch entries
        $stmt = $pdo->prepare(
            "SELECT entry_date, period, content
               FROM work_diary_entries
              WHERE user_id = ?
                AND MONTH(entry_date) = ?
                AND YEAR(entry_date)  = ?
              ORDER BY entry_date, FIELD(period,'morning','afternoon','evening')"
        );
        $stmt->execute([$userId, $month, $year]);
        $rows = $stmt->fetchAll();

        // 2) Group by date
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['entry_date']][$r['period']] = $r['content'];
        }

        // 3) Send headers
        header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"work_diary_{$month}_{$year}.xls\"");

        // 4) Output HTML → Excel
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
           . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
           . 'xmlns="http://www.w3.org/TR/REC-html40"><head>'
           . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>'
           . '<style>'
           . 'body, table, th, td { font-family:"Times New Roman"; }'
           . '@page{size:A4;margin:0.5in 0.25in 0.25in 0.75in;}'
           . 'tr.title-row td{border:none;height:40px;vertical-align:middle;'
             . 'font-size:13pt;font-weight:bold;text-align:center;}'
           . 'table.data{width:100%;border-collapse:collapse;border:3px double #000;}'
           . 'table.data tr.header-row th, table.data tr.data-row td{'
           .   'border-top:thin dashed #000;'
           .   'border-bottom:thin dashed #000;'
           .   'border-left:thin solid #000;'
           .   'border-right:thin solid #000;'
           . '}'
           . 'table.data tr.header-row th{'
           .   'font-size:11pt;'
           .   'font-weight:bold;'
           .   'text-align:center;'
           .   'height:40px;'
           . '}'
           . 'table.data tr.data-row td{'
           .   'font-size:10pt;'
           .   'white-space:normal;'
           .   'word-wrap:break-word;'
           . '}'
           . '.signature{font-size:10pt;}'
           . '.signature.bold{font-weight:bold;font-size:11pt;}'
           . '.signature-block-table, .signature-block-table td{border:none;}'
           . '</style>'
           . '<!--[if gte mso 9]><xml>'
           . '<x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>'
           . '<x:Name>Sheet1</x:Name><x:WorksheetOptions>'
           . '<x:Print><x:FitWidth>1</x:FitWidth></x:Print>'
           . '<x:FreezePanes/><x:FrozenNoSplit/><x:SplitHorizontal>2</x:SplitHorizontal>'
           . '<x:TopRowBottomPane>2</x:TopRowBottomPane>'
           . '</x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets>'
           . '</x:ExcelWorkbook></xml><![endif]-->'
           . '</head><body>';

        // Title
        echo '<table><tr class="title-row"><td colspan="4">'
           . "NHẬT KÝ CÔNG VIỆC THÁNG {$month}/{$year}"
           . '</td></tr></table>';

        // Data table
        echo '<table class="data">';
        echo '<colgroup><col/><col style="width:110px;"/><col style="width:340px;"/><col style="width:150px;"/></colgroup>';
        echo '<tr class="header-row"><th>STT</th><th>NGÀY LÀM VIỆC</th><th>TASK</th><th>GHI CHÚ</th></tr>';
        $weekday = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
        $i = 0;
        foreach ($grouped as $day => $periods) {
            $i++;
            $w = date('w', strtotime($day));
            $dstr = $weekday[$w] . ', ' . date('d/m', strtotime($day));
            echo '<tr class="data-row">'
               . '<td style="font-weight:bold;font-style:italic;border-top:thin solid #000;">' . $i . '</td>'
               . '<td style="font-weight:bold;font-style:italic;border-top:thin solid #000;">' . $dstr . '</td>'
               . '<td style="border-top:thin solid #000;"></td><td style="border-top:thin solid #000;"></td>'
               . '</tr>';
            $subs = ['morning'=>'Buổi sáng','afternoon'=>'Buổi trưa','evening'=>'Buổi tối'];
            foreach ($subs as $key => $label) {
                if (!empty($periods[$key])) {
                    echo '<tr class="data-row">'
                       . '<td></td>'
                       . '<td>' . $label . '</td>'
                       . '<td>' . nl2br(htmlspecialchars($periods[$key], ENT_QUOTES)) . '</td>'
                       . '<td></td>'
                       . '</tr>';
                }
            }
        }
        echo '</table>';

        // Spacer
        echo '<table style="border:none;"><tr><td colspan="4" style="height:20px;"></td></tr></table>';

        // Signature block
        echo '<table class="signature-block-table" style="border:none;"><tr>'
           . '<td class="signature bold" colspan="2" style="text-align:center;">Người lập bảng</td>'
           . '<td></td><td class="signature bold" style="text-align:center;">Phòng thiết kế</td>'
           . '</tr>'
           . '<tr><td colspan="4" style="height:15px;"></td></tr>'
           . '<tr><td colspan="4" style="height:15px;"></td></tr>'
           . '<tr><td colspan="4" style="height:15px;"></td></tr>'
           . '<tr>'
           . '<td class="signature bold" colspan="2" style="text-align:center;">' . $userName . '</td>'
           . '<td></td><td></td>'
           . '</tr>'
           . '</table>';
        echo '</body></html>';
        exit;
    }

    // === Send Report & Notifications ===
    if (isset($_POST['send_report'])) {
        if (!empty($_POST['notify_users'])) {
            $nq = $pdo->prepare(
              "INSERT INTO notifications (sender_id, receiver_id, entry_date) VALUES (?, ?, ?)"
            );
            foreach ($_POST['notify_users'] as $rid) {
                $nq->execute([$userId, (int)$rid, $date]);
            }
        }
        $notifyMsg = "Report sent and notifications queued.";
    }

    // === Save Diary Entries ===
    if (isset($_POST['save_diary'])) {
        $up = $pdo->prepare(
            "REPLACE INTO work_diary_entries (user_id,entry_date,period,content)
             VALUES (?,?,?,?)"
        );
        $del = $pdo->prepare(
            "DELETE FROM work_diary_entries WHERE user_id=? AND entry_date=? AND period=?"
        );
        // Morning & Afternoon
        foreach (['morning','afternoon'] as $prd) {
            $break = !empty($_POST["{$prd}_break"]);
            $late  = !empty($_POST["{$prd}_late"]);
            $txt   = trim($_POST["{$prd}_task"] ?? '');
            if ($break) {
                $up->execute([$userId,$date,$prd,'Break']);
            } elseif ($late) {
                $up->execute([$userId,$date,$prd,"Late: {$txt}"]);
            } elseif ($txt !== '') {
                $up->execute([$userId,$date,$prd,$txt]);
            } else {
                $del->execute([$userId,$date,$prd]);
            }
        }
        // Evening
        $eb = !empty($_POST['evening_break']);
        if ($eb) {
            $up->execute([$userId,$date,'evening','Break']);
        } else {
            $ts = $_POST['evening_start'] ?? '17:00';
            $te = $_POST['evening_end']   ?? '19:30';
            $tk = trim($_POST['evening_task'] ?? '');
            if ($tk !== '') {
                $up->execute([$userId,$date,'evening',"{$ts}-{$te}: {$tk}"]);
            } else {
                $del->execute([$userId,$date,'evening']);
            }
        }
        $saveMsg = "Successfully updated";
    }
}

// — Load today's entries & render UI data —

// Fetch today's diary entries
$f = $pdo->prepare("
    SELECT period, content
      FROM work_diary_entries
     WHERE user_id = ? AND entry_date = ?
");
$f->execute([$userId, $date]);
$rows = $f->fetchAll();
$diary = ['morning'=>'','afternoon'=>'','evening'=>''];
foreach ($rows as $r) {
    $diary[$r['period']] = $r['content'];
}

// Build calendar weeks
$first = new DateTime("$year-$month-01");
$days  = (int)$first->format('t');
$weeks = []; 
$w = array_fill(1,7,null);
for ($i = 1; $i <= $days; $i++) {
    $d = (int)(new DateTime("$year-$month-$i"))->format('N');
    $w[$d] = $i;
    if ($d === 7 || $i === $days) {
        $weeks[] = $w;
        $w = array_fill(1,7,null);
    }
}
$current   = new DateTime($date);
$dayNum    = $current->format('j');
$monthName = strtolower($current->format('F'));

// — Render UI —
$vS = filemtime(__DIR__.'/../assets/css/sidebar.css');
$vD = filemtime(__DIR__.'/../assets/css/work_diary.css');
include $root.'/includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/sidebar.css?v=<?= $vS ?>" />
<link rel="stylesheet" href="../assets/css/work_diary.css?v=<?= $vD ?>" />
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer"/>

<?php include __DIR__ . '/sidebar.php'; ?>

<?php if (!empty($notifyMsg)): ?>
  <div class="alert-abs"><?= htmlspecialchars($notifyMsg, ENT_QUOTES) ?></div>
<?php endif; ?>
<?php if (!empty($saveMsg)): ?>
  <div class="alert-abs"><?= htmlspecialchars($saveMsg, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="main-content">
  <!-- Calendar -->
  <div class="calendar-container card-block" id="calendar">
    <div class="calendar-header">
      <div class="calendar-day"><?= $dayNum ?></div>
      <div class="calendar-month"><?= ucfirst($monthName) ?></div>
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
        <?php foreach ($weeks as $row): ?>
          <tr>
            <?php for ($d = 1; $d <= 7; $d++): ?>
              <?php if ($row[$d]): 
                $ds  = sprintf('%04d-%02d-%02d',$year,$month,$row[$d]);
                $sel = $ds === $date ? 'selected' : '';
              ?>
                <td class="<?= $sel ?>">
                  <a href="?month=<?= $month ?>&year=<?= $year ?>&date=<?= $ds ?>">
                    <?= $row[$d] ?>
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

  <!-- Entry Panel -->
  <form method="post" class="entry-panel" id="entryPanel">
    <div class="card-block notify-panel">
      <div class="company-label">Company: <span><?= htmlspecialchars($company, ENT_QUOTES) ?></span></div>
      <div class="notify-label">Notify colleagues:</div>
      <div class="colleague-list">
        <?php foreach ($colleagues as $c): ?>
          <label>
            <input type="checkbox" name="notify_users[]" value="<?= $c['id'] ?>">
            <?= htmlspecialchars($c['first_name'].' '.$c['last_name'], ENT_QUOTES) ?>
            <small><?= htmlspecialchars($c['email'], ENT_QUOTES) ?></small>
          </label><br>
        <?php endforeach; ?>
      </div>
      <button class="btn-send-report" name="send_report">
        <i class="fas fa-bell"></i> Send Report
      </button>
    </div>

    <!-- Morning & Afternoon -->
    <?php foreach (['morning','afternoon'] as $prd):
      $val = $diary[$prd] ?? '';
      $isB = ($val === 'Break');
      $isL = (!$isB && preg_match('/^Late:\s*(.*)$/', $val, $m));
      $txt = $isL ? $m[1] : $val;
    ?>
      <div class="card-block period" data-period="<?= $prd ?>">
        <label><?= ucfirst($prd) ?></label>
        <button type="button" class="btn-toggle break <?= $isB?'active':'' ?>">Break</button>
        <button type="button" class="btn-toggle late <?= $isL?'active':'' ?>">Late</button>
        <textarea name="<?= $prd ?>_task" class="autoexpand" <?= $isB?'disabled':'' ?>><?= htmlspecialchars($txt, ENT_QUOTES) ?></textarea>
        <input type="hidden" name="<?= $prd ?>_break" value="<?= $isB?1:0 ?>">
        <input type="hidden" name="<?= $prd ?>_late" value="<?= $isL?1:0 ?>">
      </div>
    <?php endforeach; ?>

    <!-- Evening -->
    <?php
      $e    = $diary['evening'] ?? '';
      $isEB = ($e === 'Break');
      if (!$isEB && preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2}):\s*(.*)$/', $e, $z)) {
        [, $ts, $te, $tkt] = $z;
      } else {
        $ts='17:00'; $te='19:30'; $tkt='';
      }
    ?>
    <div class="card-block period evening" data-period="evening">
      <label>Evening</label>
      <button type="button" class="btn-toggle break <?= $isEB?'active':'' ?>">Break</button>
      <input type="hidden" name="evening_break" value="<?= $isEB?1:0 ?>">
      <input type="time" name="evening_start" class="start" value="<?= $ts ?>" <?= $isEB?'disabled':'' ?>>
      <input type="time" name="evening_end" class="end" value="<?= $te ?>" <?= $isEB?'disabled':'' ?>>
      <textarea name="evening_task" class="autoexpand" <?= $isEB?'disabled':'' ?>><?= htmlspecialchars($tkt, ENT_QUOTES) ?></textarea>
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
// Toggle Break/Late buttons
document.querySelectorAll('.period').forEach(panel => {
  const pr = panel.dataset.period;
  const ta = panel.querySelector('textarea');
  const hb = panel.querySelector(`[name="${pr}_break"]`);
  const hl = panel.querySelector(`[name="${pr}_late"]`);
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

// Match calendar height to entry panel
function matchHeight() {
  const cal = document.getElementById('calendar'),
        ep  = document.getElementById('entryPanel');
  if (cal && ep) cal.style.height = ep.offsetHeight + 'px';
}
window.addEventListener('load', matchHeight);
window.addEventListener('resize', matchHeight);
</script>
