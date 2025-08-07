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
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die('<p>Chưa cài PhpSpreadsheet. Vui lòng chạy <code>composer require phpoffice/phpspreadsheet</code></p>');
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
// — Khóa nếu đã có 1 notification gửi báo cáo cho tháng này —
$startMonth = "$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-01";
$endMonth   = date('Y-m-t', strtotime($startMonth));
$stmtLock = $pdo->prepare("
  SELECT 1
    FROM notifications
   WHERE sender_id  = ?
     AND entry_date BETWEEN ? AND ?
   LIMIT 1
");
$stmtLock->execute([$userId, $startMonth, $endMonth]);
$locked = (bool)$stmtLock->fetchColumn();

// — Handle POST —
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

        // === Thuộc tính Excel ===
    // 3) Send headers for .xlsx
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"Nhật ký công việc tháng_{$month}_{$year}.xlsx\"");

    // 4) Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // A4 portrait + repeat header rows 1–2 when printing
    $ps = $sheet->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
       ->setPaperSize(PageSetup::PAPERSIZE_A4)
       ->setRowsToRepeatAtTopByStartAndEnd(1, 2);

    // Default font for body
    $spreadsheet->getDefaultStyle()
                ->getFont()
                ->setName('Times New Roman')
                ->setSize(10);

    // Title (A1:E1)
    $title = mb_strtoupper("Nhật ký làm việc tháng {$month} năm {$year}", 'UTF-8');
    $sheet->mergeCells('A1:E1');
    $sheet->setCellValue('A1', $title);
    $sheet->getRowDimension(1)->setRowHeight(30);
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 12],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
        ],
    ]);

    // Header row (A2:E2)
    $headers = [
        'A2' => 'TUẦN',
        'B2' => 'STT',
        'C2' => 'NGÀY LÀM VIỆC',
        'D2' => 'NỘI DUNG CÔNG VIỆC',
        'E2' => 'GHI CHÚ',
    ];
    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }
    $sheet->getStyle('A2:E2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'name' => 'Times New Roman'],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ],
    ]);

    // 5) Column widths for A4 layout
    $sheet->getColumnDimension('A')->setWidth(8.5);
    $sheet->getColumnDimension('B')->setWidth(5.5);
    $sheet->getColumnDimension('C')->setWidth(16);
    $sheet->getColumnDimension('D')->setWidth(45);
    $sheet->getColumnDimension('E')->setWidth(20.5);

    // 6) Fill data in 4-row clusters per date
    $row            = 3;
    $currentIsoWeek = null;
    $weekIndex      = 0;
    $weekStart      = $row;
    $weekdayLabels  = [
        '0' => 'Chủ Nhật','1' => 'Thứ Hai','2' => 'Thứ Ba','3' => 'Thứ Tư',
        '4' => 'Thứ Năm','5' => 'Thứ Sáu','6' => 'Thứ Bảy',
    ];

    foreach ($grouped as $date => $periods) {
        $dt      = new DateTime($date);
        $isoWeek = $dt->format('W');

        // new week?
        if ($currentIsoWeek !== $isoWeek) {
            if ($currentIsoWeek !== null) {
                // merge & label previous week
                $sheet->mergeCells("A{$weekStart}:A" . ($row - 1));
                $sheet->getStyle("A{$weekStart}:A" . ($row - 1))
                      ->getAlignment()
                      ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                      ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->setCellValue("A{$weekStart}", "Tuần {$weekIndex}");
            }
            $currentIsoWeek = $isoWeek;
            $weekIndex++;
            $weekStart = $row;
        }

        // --- Row 1: date ---
        $wk        = $weekdayLabels[$dt->format('w')];
        $dateLabel = "{$wk} " . $dt->format('d/m');
        $sheet->setCellValue("B{$row}", $dt->format('j'));
        $sheet->setCellValue("C{$row}", $dateLabel);
        // bold STT & date
        $sheet->getStyle("B{$row}:C{$row}")
              ->getFont()->setBold(true);
        $row++;

        // --- Rows 2–4: morning, afternoon, evening ---
        $parts = ['morning' => 'Buổi Sáng', 'afternoon' => 'Buổi Chiều', 'evening' => 'Buổi Tối'];
        $i     = 1;
        foreach ($parts as $key => $label) {
            $sheet->setCellValue("B{$row}", "{$dt->format('j')}.{$i}");
            $sheet->setCellValue("C{$row}", $label);
            $sheet->setCellValue("D{$row}", $periods[$key] ?? '');
            // dashed between sessions, solid after evening
            $style = ($i < 3);
            $sheet->getStyle("A{$row}:E{$row}");
            $row++; $i++;
        }
    }

    // merge & label last week
    $sheet->mergeCells("A{$weekStart}:A" . ($row - 1));
    $sheet->getStyle("A{$weekStart}:A" . ($row - 1))
          ->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_CENTER)
          ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->setCellValue("A{$weekStart}", "Tuần {$weekIndex}");

    // 7) Table borders: outside double, inside thin
    $endRow = $row - 1;
    $sheet->getStyle("A1:E{$endRow}")->applyFromArray([
        'borders' => [
            'outline' => ['borderStyle' => Border::BORDER_DOUBLE],
            'inside' => ['borderStyle' => Border::BORDER_THIN],
        ],
    ]);
$sheet->getStyle("B2:C{$endRow}")
      ->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_CENTER)
          ->setVertical(Alignment::VERTICAL_CENTER);
    // 8) Wrap text for all cells A2:E{endRow}
    $sheet->getStyle("A2:E{$endRow}")
          ->getAlignment()->setWrapText(true);
// 11) In đậm cột A
$sheet->getStyle("A2:A{$endRow}")
      ->getFont()
          ->setBold(true);
    // 9) Footer: 1 row gap after table
    $footerRow = $endRow + 2;
    $sheet->setCellValue("C{$footerRow}", 'Người lập');
    $sheet->setCellValue("E{$footerRow}", 'Phòng thiết kế');
    $sheet->getStyle("C{$footerRow}:E{$footerRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'name' => 'Times New Roman'],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
        ],
    ]);

    // 10) Signature: 3 rows below footer, in C
    $sigRow = $footerRow + 4;
    $sheet->setCellValue("C{$sigRow}", $userName);
    $sheet->getStyle("C{$sigRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'name' => 'Times New Roman'],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
        ],
    ]);

    // 11) Print area covers through signature
    $ps->setPrintArea("A1:E{$sigRow}");

    // 12) Export file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
// === Hết Thuộc tính Excel ===

    // === Send Report & Notifications ===
    if (isset($_POST['send_report'])) {
        if (!empty($_POST['notify_users'])) {
            $nq = $pdo->prepare(
              "INSERT INTO notifications (sender_id, receiver_id, entry_date) VALUES (?, ?, ?)"
            );
            $reportDate = $startMonth; 
        foreach ($_POST['notify_users'] as $rid) {
            $nq->execute([$userId, (int)$rid, $reportDate]);
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
    // Chỉ coi là “checked” khi value thực sự là '1'
    $holiday = isset($_POST["{$prd}_holiday"]) 
               && $_POST["{$prd}_holiday"] === '1';
    $break   = isset($_POST["{$prd}_break"]) 
               && $_POST["{$prd}_break"]   === '1';
    $late    = isset($_POST["{$prd}_late"]) 
               && $_POST["{$prd}_late"]    === '1';
    $txt     = trim($_POST["{$prd}_task"] ?? '');
// ==== Sửa lại để lưu trọn vẹn nội dung textarea ====
// $txt đã là trim($_POST["{$prd}_task"]) – có chứa prefix + phần nhập thêm
if ($break) {
    // Chỉ nghỉ hoàn toàn
    $content = 'Nghỉ';
} elseif ($txt !== '') {
    // Holiday và Late đều đã có prefix trong $txt, cộng thêm phần nhập thêm
    $content = $txt;
} else {
    // Không có nội dung nào cả → xóa bản ghi
    $del->execute([$userId, $date, $prd]);
    continue;  // bỏ qua gọi $up
}
// Lưu hoặc cập nhật
$up->execute([$userId, $date, $prd, $content]);
}
// Evening
$holidayE = isset($_POST['evening_holiday']) 
            && $_POST['evening_holiday'] === '1';
$breakE   = isset($_POST['evening_break']) 
            && $_POST['evening_break']   === '1';
$lateE    = isset($_POST['evening_late']) 
            && $_POST['evening_late']    === '1';
$ts       = $_POST['evening_start'] ?? '17:00';
$te       = $_POST['evening_end']   ?? '19:30';
$taskE    = trim($_POST['evening_task'] ?? '');

// Ưu tiên textarea trước
if ($taskE !== '') {
    $up->execute([$userId, $date, 'evening', "{$ts}-{$te}: {$taskE}"]);
} elseif ($holidayE) {
    $up->execute([$userId, $date, 'evening', 'Nghỉ lễ']);
} elseif ($breakE) {
    $up->execute([$userId, $date, 'evening', 'Nghỉ']);
} else {
    $del->execute([$userId, $date, 'evening']);
}
        }
        $saveMsg = "Successfully updated";
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
$vS = filemtime(__DIR__ . '/../assets/css/sidebar.css');
$vD = filemtime(__DIR__ . '/../assets/css/work_diary.css');
include $root . '/includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/sidebar.css?v=<?= $vS ?>" />
<link rel="stylesheet" href="../assets/css/work_diary.css?v=<?= $vD ?>" />
<link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dashboard.css'); ?>">
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer"/>
<?php include __DIR__ . '/sidebar.php'; ?>

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
<?php
  // Cache‐bust JS khi work_diary.js thay đổi
  $vJs = filemtime(__DIR__ . '/../assets/js/work_diary.js');
?>
<script src="../assets/js/work_diary.js?v=<?= $vJs ?>"></script>
<?php if ($locked): ?>
  <div class="alert-abs">Báo cáo tháng <?= sprintf('%02d',$month) ?>/<?= $year ?> đã gửi, không thể chỉnh sửa.</div>
<?php endif; ?>
  <!-- Entry Panel -->
<fieldset <?= $locked ? 'disabled' : '' ?> class="entry-wrapper">
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
      $val      = $diary[$prd] ?? '';
      $isH      = isset($_POST["{$prd}_holiday"]) && $_POST["{$prd}_holiday"] === '1';
      $isB      = isset($_POST["{$prd}_break"])   && $_POST["{$prd}_break"]   === '1';
      $isL      = isset($_POST["{$prd}_late"])    && $_POST["{$prd}_late"]    === '1';
      $txt      = htmlspecialchars($val, ENT_QUOTES);
    ?>
      <div class="card-block period" data-period="<?= $prd ?>">
        <label><?= ucfirst($prd) ?></label>
        <button type="button" class="btn-toggle holiday <?= $isH?'active':'' ?>">Holiday</button>
        <button type="button" class="btn-toggle break   <?= $isB?'active':'' ?>">Break</button>
        <button type="button" class="btn-toggle late    <?= $isL?'active':'' ?>">Late</button>
        <textarea name="<?= $prd ?>_task" class="autoexpand"><?= $txt ?></textarea>
        <input type="hidden" name="<?= $prd ?>_holiday" value="<?= $isH?1:0 ?>">
        <input type="hidden" name="<?= $prd ?>_break"   value="<?= $isB?1:0 ?>">
        <input type="hidden" name="<?= $prd ?>_late"    value="<?= $isL?1:0 ?>">
      </div>
    <?php endforeach; ?>

    <!-- Evening -->
    <?php
      $valE   = $diary['evening'] ?? '';
      $isEH   = isset($_POST['evening_holiday']) && $_POST['evening_holiday'] === '1';
      $isEB   = isset($_POST['evening_break'])   && $_POST['evening_break']   === '1';
      if (preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2}):\s*(.*)$/', $valE, $m)) {
        [, $ts, $te, $taskE] = $m;
      } else {
        $ts    = '17:00';
        $te    = '19:30';
        $taskE = trim($valE);
      }
    ?>
    <div class="card-block period evening" data-period="evening">
      <label>Evening</label>
      <button type="button" class="btn-toggle break   <?= $isEB?'active':'' ?>">Break</button>
      <input type="hidden" name="evening_break"   value="<?= $isEB?1:0 ?>">
      <input type="time" name="evening_start" class="start" value="<?= $ts ?>">
      <input type="time" name="evening_end"   class="end"   value="<?= $te ?>">
      <textarea name="evening_task" class="autoexpand"><?= htmlspecialchars($taskE, ENT_QUOTES) ?></textarea>
    </div>

    <!-- Actions -->
    <div class="actions">
      <?php if (!$locked): ?>
        <button type="submit" name="export_excel" class="export">
          <i class="fas fa-file-excel"></i> Export CSV
        </button>
        <button type="submit" name="save_diary" class="save">
          <i class="fas fa-save"></i> Save
        </button>
      <?php endif; ?>
    </div>
  </form>
</fieldset>

