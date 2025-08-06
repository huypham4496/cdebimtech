<?php
// pages/export_stats_org_excel.php
session_start();
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['user']['id'])) {
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
// Tham số
$orgId = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;
$month = isset($_GET['month'])  ? (int)$_GET['month']  : date('n');
$year  = isset($_GET['year'])   ? (int)$_GET['year']   : date('Y');

// Kết nối DB
$pdo = new PDO(
    "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);

// Lấy thông tin tổ chức và thành viên
$stmt = $pdo->prepare("SELECT name, department FROM organizations WHERE id = :oid");
$stmt->execute([':oid'=>$orgId]);
$org = $stmt->fetch();
$orgName = strtoupper($org['name'] ?? '');
$dept    = strtoupper($org['department'] ?? '');

$stmt = $pdo->prepare(
    "SELECT u.id, COALESCE(p.full_name,u.email) AS full_name, p.position AS position
     FROM organization_members m
       JOIN users u ON u.id = m.user_id
       LEFT JOIN organization_member_profiles p ON p.member_id=m.id
     WHERE m.organization_id=:oid
     ORDER BY u.email"
);
$stmt->execute([':oid'=>$orgId]);
$members = $stmt->fetchAll();

function fetchEntries($uid, $start, $end) {
    global $pdo;
    $q = $pdo->prepare(
      "SELECT entry_date,period,content
       FROM work_diary_entries
       WHERE user_id=:u AND entry_date BETWEEN :s AND :e"
    );
    $q->execute([':u'=>$uid, ':s'=>$start, ':e'=>$end]);
    return $q->fetchAll();
}

$startDate   = sprintf('%04d-%02d-01', $year, $month);
$endDate     = date('Y-m-t', strtotime($startDate));
$daysInMonth = (int)date('t', strtotime($startDate));

// Tạo Spreadsheet
$ss = new Spreadsheet();
$ss->getDefaultStyle()->getFont()->setName('Times New Roman');
$ss->getProperties()
   ->setTitle('BẢNG CHẤM CÔNG SN PHẨM')
   ->setCreator($_SESSION['user']['email'] ?? '')
   ->setCreated(time());

function initSheet($ss, $index, $title) {
    global $daysInMonth, $orgName, $dept, $month, $year;

    if ($index === 0) {
        $sh = $ss->getActiveSheet();
    } else {
        $sh = $ss->createSheet($index);
        $ss->setActiveSheetIndex($index);
    }
    $sh->setTitle($title);

    // Kích thước cột
    $sh->getColumnDimension('B')->setWidth(20.5);
    $sh->getColumnDimension('C')->setWidth(9);
    for ($col = 'D'; $col !== Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('AH')+1); $col++) {
        $sh->getColumnDimension($col)->setWidth(4.5);
    }

    // Chiều cao hàng
    $sh->getRowDimension(2)->setRowHeight(45);
    $sh->getRowDimension(6)->setRowHeight(114);

    // Header bảng (hàng 5) in đậm
    $sh->getStyle('A5:AN5')->getFont()->setBold(true);

    // Page setup A4 ngang
    $ps = $sh->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
       ->setPaperSize(PageSetup::PAPERSIZE_A4)
       ->setFitToPage(true)
       ->setFitToWidth(1)
       ->setFitToHeight(0);
    $m = $sh->getPageMargins();
    $m->setLeft(0.16)->setTop(0.16)->setRight(0.17)->setBottom(0.18)
      ->setHeader(0.16)->setFooter(0.16);

    // Header merges & styles
    $sh->mergeCells('A2:K2')->setCellValue('A2', "TÊN ĐƠN VỊ: $orgName");
    $s = $sh->getStyle('A2');
    $s->getFont()->setSize(12)->setBold(true);
    $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $sh->mergeCells('A3:K3')->setCellValue('A3', "BỘ PHẬN: $dept");
    $s = $sh->getStyle('A3');
    $s->getFont()->setSize(12)->setBold(true);
    $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $sh->mergeCells('L2:AH2')->setCellValue('L2', $title);
    $s = $sh->getStyle('L2');
    $s->getFont()->setSize(22)->setBold(true);
    $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

    $sh->mergeCells('AI2:AN2')->setCellValue('AI2', 'Mẫu số: 01a - LĐTL (QĐ 48/2006/QĐ-BTC 14/9/2006)');
    $s = $sh->getStyle('AI2');
    $s->getFont()->setSize(10)->setBold(true);
    $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

$sh->mergeCells('L3:AH3')
    ->setCellValue('L3', sprintf('Tháng %d năm %d', $month, $year));
$style = $sh->getStyle('L3');
$style->getFont()->setSize(13)->setBold(true);
$style->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER)
      ->setVertical(Alignment::VERTICAL_CENTER);

    // Table header rows 5-6
    $sh->mergeCells('A5:A6')->setCellValue('A5','TT');
    $sh->mergeCells('B5:B6')->setCellValue('B5','Họ và tên');
    $sh->mergeCells('C5:C6')->setCellValue('C5','Ngạch, bậc lương hoặc cấp bậc chức vụ');
    // Căn giữa cả ô A5, B5, C5
    $sh->getStyle('A5:C5')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $endCol = Coordinate::stringFromColumnIndex(34);
    $sh->mergeCells("D5:{$endCol}5")->setCellValue('D5','Ngày trong tháng');
    $sh->getStyle('D5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Ngày trong tháng (hàng 6)
    for ($i = 1; $i <= $daysInMonth; $i++) {
        $col = Coordinate::stringFromColumnIndex(3 + $i);
        $sh->setCellValue("{$col}6", $i);
    }
    $sh->getStyle('A6:AN6')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);

    // Quy ra công header
    $sh->mergeCells('AI5:AN5')->setCellValue('AI5','Quy ra công');
    $sh->getStyle('AI5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $labels = ['Số công hưởng lương sản phẩm','Số công hưởng thời gian','Số công nghỉ việc, ngừng việc hưởng 100% lương','Số công nghỉ việc, ngừng việc hưởng ...... lương','Số công hưởng BHXH','Hệ số thành tích tháng'];
    foreach ($labels as $k => $v) {
        $c = Coordinate::stringFromColumnIndex(35 + $k);
        $sh->setCellValue("{$c}6", $v);
    }

    return $sh;
}

// Sheet1
$sh1 = initSheet($ss, 0, 'BẢNG CHẤM CÔNG SẢN PHẨM');
$row = 8;
foreach ($members as $idx => $m) {
    $sh1->setCellValue("A{$row}", $idx + 1);
    $sh1->setCellValue("B{$row}", $m['full_name']);
    // Ngạch, bậc lương hoặc cấp bậc chức vụ (cột C)
    $sh1->setCellValue("C{$row}", $m['position']);
    $ents = fetchEntries($m['id'], $startDate, $endDate);
    $work = []; $even = [];
    foreach ($ents as $r) {
        $d = (new DateTime($r['entry_date']))->format('Y-m-d');
        $c = trim($r['content']);
        if (stripos($c, 'evening') !== false) {
            $even[$d] = 'K/2';
        } elseif (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu', $c)) {
            $work[$d] = [];
        } else {
            $work[$d][] = $r['period'];
        }
    }
    for ($i = 1; $i <= $daysInMonth; $i++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $i);
        $wd = (int)date('N', strtotime($date));
        $val = '';
        if (isset($even[$date])) {
            $val = $even[$date];
        } elseif (isset($work[$date])) {
            $morn = in_array('morning', $work[$date], true);
            $aft  = in_array('afternoon', $work[$date], true);
            if ($wd === 6) {
                $val = $morn ? 'K/2' : '';
            } elseif ($wd !== 7) {
                $val = $morn && $aft ? 'K' : ($morn || $aft ? 'K/2' : '');
            }
        }
        $col = Coordinate::stringFromColumnIndex(3 + $i);
        $sh1->setCellValue("{$col}{$row}", $val);
        // Công thức tính tổng công cho mỗi dòng
        $sh1->setCellValue("AI{$row}", "=COUNTIF(D{$row}:AH{$row},\"K\") + COUNTIF(D{$row}:AH{$row},\"K/2\")/2");
        // In đậm và định dạng nếu 0 hiển thị '-'
        $styleAI = $sh1->getStyle("AI{$row}");
        $styleAI->getFont()->setBold(true);
        $styleAI->getNumberFormat()->setFormatCode('[=0]"-";General');
        // Công thức tính số ký hiệu + và L cho cột AJ
        $sh1->setCellValue("AJ{$row}", "=COUNTIF(D{$row}:AH{$row},\"+\") + COUNTIF(D{$row}:AH{$row},\"L\")");
        // In đậm và định dạng nếu 0 hiển thị '-'
        $styleAJ = $sh1->getStyle("AJ{$row}");
        $styleAJ->getFont()->setBold(true);
        $styleAJ->getNumberFormat()->setFormatCode('[=0]"-";General');
        // Công thức tính ký hiệu P cho cột AM
        $sh1->setCellValue("AM{$row}", "=COUNTIF(D{$row}:AH{$row},\"P\")");
        // In đậm và định dạng nếu 0 hiển thị '-'
        $styleAM = $sh1->getStyle("AM{$row}");
        $styleAM->getFont()->setBold(true);
        $styleAM->getNumberFormat()->setFormatCode('[=0]"-";General');
    }
    $row++;
}

// — GHI THỨ TRONG TUẦN CHO CÁC NGÀY D7→AH7 —
$daysInMonth = (int) date('t', strtotime($startDate));
$dowMap = [
    1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5',
    5 => 'T6', 6 => 'T7', 7 => 'CN',
];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $weekday = (int) date('N', strtotime($dateStr)); // 1=Mon...7=Sun
    $label   = $dowMap[$weekday];
    // cột D (4) tương ứng d=1 → 4; nên dùng 3 + $d
    $col     = Coordinate::stringFromColumnIndex(3 + $d);
    $sh1->setCellValue("{$col}7", $label);
}

// — GHI NHÃN AI7→AN7: 'D','E','F','G','H','I' —
$extraLabels = ['D','E','F','G','H','I'];
$startExtra   = Coordinate::columnIndexFromString('AI');
foreach ($extraLabels as $i => $lbl) {
    $col = Coordinate::stringFromColumnIndex($startExtra + $i);
    $sh1->setCellValue("{$col}7", $lbl);
}
// Tô nền xám cho các cột CN (Chủ Nhật) trong dữ liệu user (row 8→row-1)
// Số ngày trong tháng
$daysInMonth   = (int) date('t', strtotime($startDate));
// Xác định vùng dữ liệu user: từ row 8 đến row cuối cùng ($row - 1)
$dataStartRow  = 8;
$dataEndRow    = $row - 1;

for ($d = 1; $d <= $daysInMonth; $d++) {
    // Tạo chuỗi ngày 2025-08-03...
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
    // Kiểm tra xem có phải Chủ Nhật không (N = 7)
    if ((int) date('N', strtotime($dateStr)) === 7) {
        // Tính cột Excel: D là 3+1, E là 3+2, ..., AH là 3+31
        $col   = Coordinate::stringFromColumnIndex(3 + $d);
        $range = "{$col}{$dataStartRow}:{$col}{$dataEndRow}";
        // Áp style tô nền
        $sh1->getStyle($range)
            ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                    ->setARGB('D9D9D9');
    }
}
// Áp vertical center cho toàn vùng A7→AN<dataEndRow>
$sh1->getStyle("A7:AN{$dataEndRow}")
    ->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER);

// — CANH CHỈNH CĂN GIỮA CHO A7:AN7 —
$range = 'A7:AN7';
$sh1->getStyle($range)
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
// Thêm dòng Tổng
$totalRow = $row;
$sh1->setCellValue("B{$totalRow}", "TỔNG CỘNG:");
$sh1->getStyle("A{$totalRow}:AN{$totalRow}")
    ->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER);
// — ÁP DỤNG CHIỀU CAO DÒNG 22 CHO TỪ 7 → Tổng cộng —
$ss->setActiveSheetIndex(0);
$sh1 = $ss->getActiveSheet();
for ($r = 7; $r <= $totalRow; $r++) {
    $sh1->getRowDimension($r)->setRowHeight(22);
}
// Style cho chữ Tổng: cỡ 11, đậm, căn giữa cả ngang & dọc
$styleTotal = $sh1->getStyle("B{$totalRow}");
$styleTotal->getFont()->setSize(11)->setBold(true);
$styleTotal->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
// Xác định hàng dữ liệu đầu và cuối
$dataStartRow = 5;
$dataEndRow   = $totalRow - 1;

// Ghi công thức SUM cho các cột AI→AM trên hàng Tổng
foreach (['AI', 'AJ', 'AK', 'AL', 'AM'] as $col) {
    // Ví dụ: =SUM(AI5:AI12) nếu $dataEndRow là 12
    $sh1->setCellValue(
        "{$col}{$totalRow}",
        "=SUM({$col}{$dataStartRow}:{$col}{$dataEndRow})"
    );
    // In đậm kết quả
    $sh1->getStyle("{$col}{$totalRow}")
        ->getFont()->setBold(true);
}
// Cập nhật lại lastRow để bao gồm cả dòng Tổng
$lastRow = $totalRow;
// --- Chèn 1 dòng trống ngay dưới dòng Tổng ---
$row++;


// VÙNG 1: Header A5→AN7, tất cả border nét liền
$sh1->getStyle("A5:AN7")
    ->getBorders()
    ->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);
// VÙNG 2: User data từ A8 → AN{totalRow-1}
$dataStart = 8;
$dataEnd   = $totalRow - 1;

// 1) Áp viền ngoài (outline) và line dọc (vertical) nét liền cho toàn vùng
$sh1->getStyle("A{$dataStart}:AN{$dataEnd}")
    ->applyFromArray([
        'borders' => [
            'outline'  => ['borderStyle' => Border::BORDER_THIN],
            'vertical' => ['borderStyle' => Border::BORDER_THIN],
        ],
    ]);

// 2) Dùng loop để gán bottom border dashed cho mỗi dòng user
for ($r = $dataStart; $r < $dataEnd; $r++) {
    $sh1->getStyle("A{$r}:AN{$r}")
        ->getBorders()
        ->getBottom()
        ->setBorderStyle(Border::BORDER_DASHED);
}
// VÙNG 3: Dòng “Tổng cộng” A{totalRow}→AN{totalRow}, tất cả border nét liền
$sh1->getStyle("A{$totalRow}:AN{$totalRow}")
    ->getBorders()
    ->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);

// Giữ font-size chung cho A7→AN{lastRow}
$sh1->getStyle("A7:AN{$lastRow}")
    ->getFont()
    ->setSize(10);

// Giữ font-size cho toàn vùng từ A7→AN{lastRow}
$sh1->getStyle("A7:AN{$lastRow}")
    ->getFont()
    ->setSize(10);
// Tính hàng bắt đầu và kết thúc của dữ liệu TT
$firstDataRow = 7;
$lastDataRow  = $row - 1;  // $row đã đếm xong, nên row-1 là dòng cuối

// In đậm & căn giữa cột A từ hàng 7 tới hàng cuối dữ liệu
$sh1->getStyle("A{$firstDataRow}:A{$lastDataRow}")
    ->getFont()->setBold(true);
$sh1->getStyle("A{$firstDataRow}:A{$lastDataRow}")
    ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
// --- TIẾP THEO: Bold cột Họ và tên (B) ---
$sh1->getStyle("B{$firstDataRow}:B{$lastDataRow}")
    ->getFont()->setBold(true);
// Thêm ký duyệt và các mục dưới bảng
$commentRow1 = $lastRow + 2;
$commentRow2 = $lastRow + 3;
// Để dòng merge trống chuyển xuống dòng 38, tức là lastRow + 6
$commentRow3 = $lastRow + 6;
// Dòng 'Ký hiệu chấm công' ngay sau dòng merge trống
$codeRow     = $commentRow3 + 1;

// Dòng 1: tiêu đề ký duyệt (chuyển sang cột B)
$sh1->setCellValue("B{$commentRow1}", 'Người chấm công');
$sh1->mergeCells("R{$commentRow1}:W{$commentRow1}")->setCellValue("R{$commentRow1}", 'Phụ trách bộ phận');
$sh1->mergeCells("AK{$commentRow1}:AN{$commentRow1}")->setCellValue("AK{$commentRow1}", 'Người duyệt');
$sh1->getStyle("B{$commentRow1}:AN{$commentRow1}")->getFont()->setBold(true);
$sh1->getStyle("B{$commentRow1}:AN{$commentRow1}")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

// Dòng 2: ký tên (nghiêng)
$sh1->setCellValue("B{$commentRow2}", '(Ký, họ tên)');
$sh1->mergeCells("R{$commentRow2}:W{$commentRow2}")->setCellValue("R{$commentRow2}", '(Ký, họ tên)');
$sh1->mergeCells("AK{$commentRow2}:AN{$commentRow2}")->setCellValue("AK{$commentRow2}", '(Ký, họ tên)');
$sh1->getStyle("B{$commentRow2}:AN{$commentRow2}")->getFont()->setItalic(true);
$sh1->getStyle("B{$commentRow2}:AN{$commentRow2}")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
// Dòng 3: merge R-W & AK-AN, giữ đậm, center
$sh1->mergeCells("R{$commentRow3}:W{$commentRow3}");
$sh1->mergeCells("AK{$commentRow3}:AN{$commentRow3}");
$sh1->getStyle("R{$commentRow3}:AN{$commentRow3}")->getFont()->setBold(true);
$sh1->getStyle("R{$commentRow3}:AN{$commentRow3}")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

// Dòng 4: merge B-K, center, đậm, ghi 'Ký hiệu chấm công:'
$sh1->mergeCells("B{$codeRow}:K{$codeRow}")->setCellValue("B{$codeRow}", 'Ký hiệu chấm công:');
$sh1->getStyle("B{$codeRow}:K{$codeRow}")->getFont()->setBold(true);
$sh1->getStyle("B{$codeRow}:K{$codeRow}")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    ->setVertical(Alignment::VERTICAL_CENTER);
// Dòng tiếp theo: liệt kê các mục ở cột B
$items = [
    'Lương SP:',
    'Lương thời gian:',
    'Ốm, điều dưỡng:',
    'Con ốm:',
    'Thai sản:',
    'Tai nạn:'
];
$startItemRow = $codeRow + 1;
foreach ($items as $i => $text) {
    $sh1->setCellValue("B" . ($startItemRow + $i), $text);
}
// Dòng tiếp theo: liệt kê các mục ở cột C
$items = [
    'SP',
    '+',
    'Ô',
    'Cô',
    'TS',
    'T'
];
$startItemRow = $codeRow + 1;
foreach ($items as $i => $text) {
    $sh1->setCellValue("C" . ($startItemRow + $i), $text);
}
// Dòng tiếp theo sau các mục: liệt kê các mục nghỉ eigener
$negItems = [
    'Nghỉ phép:',
    'Hội nghị, học tập:',
    'Nghỉ bù:',
    'Nghỉ không lương:',
    'Ngừng việc:',
    'Lao động nghĩa vụ:'
];
foreach ($negItems as $i => $text) {
    $r = $startItemRow + $i; 
    // cùng phạm vi với Lương SP items
    $sh1->mergeCells("E{$r}:K{$r}")->setCellValue("E{$r}", $text);
    $sh1->getStyle("E{$r}:K{$r}")
        ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
}
        // Dòng tiếp theo: liệt kê các mục ở cột L
$items = [
    'P',
    'H',
    'NB',
    'KL',
    'N',
    'LĐ'
];
$startItemRow = $codeRow + 1;
foreach ($items as $i => $text) {
    $sh1->setCellValue("L" . ($startItemRow + $i), $text);
}
// Chuyển về Sheet1
$ss->setActiveSheetIndex(0);
$sh1 = $ss->getActiveSheet();

// Xác định dòng “Người duyệt” (giả sử bạn đã có $commentRow1)
$dateRow = $commentRow1 - 1; // dòng ngay trên “Người duyệt”

// Gộp AK→AN, ghi ngày tháng năm
$sh1->mergeCells("AK{$dateRow}:AN{$dateRow}")
    ->setCellValue("AK{$dateRow}", sprintf(
        'Ngày %02d tháng %02d năm %04d',
        date('d'), date('m'), date('Y')
    ));

// Font nghiêng size 11, căn giữa cả chiều ngang lẫn dọc
$style = $sh1->getStyle("AK{$dateRow}:AN{$dateRow}");
$style->getFont()
    ->setItalic(true)
    ->setSize(11);
$style->getAlignment()
    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

// Đặt chiều cao dòng = 30
$sh1->getRowDimension($dateRow)->setRowHeight(30);

// --- Khởi tạo Sheet 2 ---
// --- Khởi tạo Sheet 2 và copy A1:AN6 như đã hướng dẫn ---
$sh2 = initSheet($ss, 1, 'BẢNG CHẤM CÔNG LÀM THÊM GIỜ');
$src = $ss->getSheet(0);
// (Copy column widths, row heights, merges, styles cho A1:AN6 – code như trước đây)

// --- Điều chỉnh nhãn AI5–AN6 ---
$sh2->mergeCells('AI5:AN5')
    ->setCellValue('AI5', 'Quy ra công');
$sh2->getStyle('AI5')
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$labels2 = [
    'Số công hưởng lương sản phẩm',
    'Số công hưởng lương sản phẩm CN',
    'Số công hưởng thời gian',
    'Số công nghỉ việc hưởng 100% lương',
    'Số công nghỉ việc,  hưởng ...... lương',
    'Số công hưởng BHXH',
];
$startColIdx = Coordinate::columnIndexFromString('AI');
foreach ($labels2 as $i => $text) {
    $col  = Coordinate::stringFromColumnIndex($startColIdx + $i);
    $cell = $col . '6';
    $sh2->setCellValue($cell, $text);
    $sh2->getStyle($cell)
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// --- Row 7: tiêu đề khung dữ liệu ---
// A7, B7, C7 lần lượt là 'A', 'B', 'C'
$sh2->setCellValue('A7', 'A');
$sh2->setCellValue('B7', 'B');
$sh2->setCellValue('C7', 'C');

// D7→AH7: ghi thứ trong tuần tương ứng với mỗi ngày
$daysInMonth = (int)date('t', strtotime($startDate));
$dowMap = [
    1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5',
    5 => 'T6', 6 => 'T7', 7 => 'CN',
];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $weekday = (int)date('N', strtotime($dateStr)); // 1=Mon...7=Sun
    $label   = $dowMap[$weekday];
    $col     = Coordinate::stringFromColumnIndex(3 + $d); // D is 4 => d=1→col=4
    $sh2->setCellValue("{$col}7", $label);
}

// AI7→AN7: ghi lần lượt 'D', 'E', 'F', 'G', 'H', 'I'
$extraLabels = ['D','E','F','G','H','I'];
$startExtra   = Coordinate::columnIndexFromString('AI');
foreach ($extraLabels as $i => $lbl) {
    $col = Coordinate::stringFromColumnIndex($startExtra + $i);
    $sh2->setCellValue("{$col}7", $lbl);
}
$range = 'A7:AN7';
$sh2->getStyle($range)
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
// --- Row 8: Merge A8:AN8, center, font size 11, bold, text "Làm tối" ---
$sh2->mergeCells('A8:AH8')
    ->setCellValue('A8', 'Làm tối');
$sh2->getStyle('A8')
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
$sh2->getStyle('A8')
    ->getFont()
    ->setSize(11)
    ->setBold(true);
 // --- Bắt đầu từ hàng 9: lấy dữ liệu user cho Buổi tối (T2–CN) ---
$dataStartRow = 9;
$daysInMonth  = (int)date('t', strtotime($startDate));

foreach ($members as $i => $m) {
    $row = $dataStartRow + $i;

    // STT, Họ và tên, Chức vụ
    $sh2->setCellValue("A{$row}", $i + 1);
    $sh2->setCellValue("B{$row}", $m['full_name']);
    $sh2->setCellValue("C{$row}", $m['position']);

    // Fetch entries và lọc Buổi tối
    $ents    = fetchEntries($m['id'], $startDate, $endDate);
    $evening = [];
    foreach ($ents as $e) {
        if ($e['period'] === 'evening') {
            $dStr = (new DateTime($e['entry_date']))->format('Y-m-d');
            $evening[$dStr] = 'K/2';
        }
    }

    // Điền vào cột D→AH (ngày 1→$daysInMonth)
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $col   = Coordinate::stringFromColumnIndex(3 + $d);  // D=4 => d=1→col=4
        $date  = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $sh2->setCellValue("{$col}{$row}", $evening[$date] ?? '');
    }
}


// --- 2) Dòng sau đó: merge A→AN, ghi “Chiều thứ 7 + Chủ Nhật + Ngày lễ” ---
$labelRow = $totalRow + 1;
$sh2->mergeCells("A{$labelRow}:AH{$labelRow}")
    ->setCellValue("A{$labelRow}", 'Chiều thứ 7 + Chủ Nhật + Ngày lễ');
    // --- Ghi “T7” và “CN” ở Zone 3 ---
$sh2->setCellValue("AI{$labelRow}", "T7");
$sh2->setCellValue("AJ{$labelRow}", "CN");
$style = $sh2->getStyle("AI{$labelRow}:AJ{$labelRow}");
$style->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
// --- Style đậm & cỡ 11 cho cả hai ô ---
$sh2->getStyle("AI{$labelRow}:AJ{$labelRow}")
    ->getFont()
        ->setBold(true)
        ->setSize(11);
        
// Canh giữa ngang & dọc, font size 11, bold
$sh2->getStyle("A{$labelRow}")
    ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
$sh2->getStyle("A{$labelRow}")
    ->getFont()
        ->setSize(11)
        ->setBold(true);

// --- 3) Đổ dữ liệu phần “Chiều T7, CN & Ngày lễ” ---
$weekendDataStart   = $labelRow + 1;
$weekendLastDataRow = $weekendDataStart + count($members) - 1;

foreach ($members as $i => $m) {
    $r = $weekendDataStart + $i;
    // STT, Họ tên, Chức vụ
    $sh2->setCellValue("A{$r}", $i + 1);
    $sh2->setCellValue("B{$r}", $m['full_name']);
    $sh2->setCellValue("C{$r}", $m['position']);

    // Lấy entries & lọc chiều cuối tuần
    $ents    = fetchEntries($m['id'], $startDate, $endDate);
    $weekend = [];
    foreach ($ents as $e) {
        $wd = (int) date('N', strtotime($e['entry_date']));
        if ($e['period'] === 'afternoon' && ($wd === 6 || $wd === 7)) {
            $dStr = (new DateTime($e['entry_date']))->format('Y-m-d');
            $weekend[$dStr] = 'K/2';
        }
    }
    // Ghi vào D→AH
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $col  = Coordinate::stringFromColumnIndex(3 + $d);
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $sh2->setCellValue("{$col}{$r}", $weekend[$date] ?? '');
    }
}
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
    if ((int)date('N', strtotime($dateStr)) === 7) {  // 7 = Chủ Nhật
        $col   = Coordinate::stringFromColumnIndex(3 + $d);
        $range = "{$col}{$dataStartRow}:{$col}{$weekendLastDataRow}";
        $sh2->getStyle($range)
            ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('D9D9D9');
    }
}

// --- 4) Dòng “Tổng cộng” cho phần chiều cuối tuần ---
$weekendTotalRow = $weekendLastDataRow + 1;
$sh2->setCellValue("B{$weekendTotalRow}", 'TỔNG CỘNG:');
$sh2->getStyle("B{$weekendTotalRow}")
    ->getFont()
        ->setBold(true);
// Xác định lastRow tương ứng với dòng “Tổng cộng” cuối cùng
$lastRow = $weekendTotalRow;
// 1) Định nghĩa $endRow dựa trên dòng Tổng cộng
$endRow = $weekendTotalRow;
// 1) Đặt cỡ chữ 10 cho tất cả nội dung giữa header và tổng cộng
$startContentRow = 9;
$endContentRow   = $endRow - 1;  // trước dòng Tổng cộng
$sh2->getStyle("A{$startContentRow}:AN{$endContentRow}")
    ->getFont()->setSize(10);

// 2) Căn giữa toàn bộ dòng “Tổng cộng” (hàng $endRow)
$sh2->getStyle("A{$endRow}:AN{$endRow}")
    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
// 2) Wrap text cho toàn bộ vùng A5:AN{$endRow}
$sh2->getStyle("A5:AN{$endRow}")
    ->getAlignment()
        ->setWrapText(true);

// 3) Cột A (A5→A{$endRow}): căn giữa & đậm
$sh2->getStyle("A5:A{$endRow}")
    ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
$sh2->getStyle("A5:A{$endRow}")
    ->getFont()
        ->setBold(true);
// 4) Cột B (B5→B{$endRow}): in đậm
$sh2->getStyle("B5:B{$endRow}")
    ->getFont()
        ->setBold(true);
// 1) Thiết lập các dòng comment giống Sheet 1
$commentRow1 = $lastRow + 2;
$commentRow2 = $lastRow + 3;
// Dòng merge trống
$commentRow3 = $lastRow + 6;
// Dòng “Ký hiệu chấm công:”
$codeRow     = $commentRow3 + 1;

$dateRow     = $commentRow1 - 1; // sẽ là 19

// 0) Thêm hàng ngày xuất file **ở ô AK19”**
$sh2->mergeCells("AK{$dateRow}:AN{$dateRow}")
    ->setCellValue("AK{$dateRow}", sprintf(
        'Ngày %02d tháng %02d năm %04d',
        date('d'), date('m'), date('Y')
    ));
$sh2->getStyle("AK{$dateRow}:AN{$dateRow}")
    ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
$sh2->getStyle("AK{$dateRow}:AN{$dateRow}")
    ->getFont()
        ->setItalic(true)
        ->setSize(11);
$sh2->getRowDimension($dateRow)
    ->setRowHeight(30);
// 1) Dòng 1: tiêu đề ký duyệt
$sh2->setCellValue("B{$commentRow1}", 'Người chấm công');
$sh2->mergeCells("R{$commentRow1}:W{$commentRow1}")
    ->setCellValue("R{$commentRow1}", 'Phụ trách bộ phận');
$sh2->mergeCells("AK{$commentRow1}:AN{$commentRow1}")
    ->setCellValue("AK{$commentRow1}", 'Người duyệt');
$sh2->getStyle("B{$commentRow1}:AN{$commentRow1}")->getFont()->setBold(true);
$sh2->getStyle("B{$commentRow1}:AN{$commentRow1}")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

// Dòng 2: ký tên (nghiêng)
$sh2->setCellValue("B{$commentRow2}", '(Ký, họ tên)');
$sh2->mergeCells("R{$commentRow2}:W{$commentRow2}")
    ->setCellValue("R{$commentRow2}", '(Ký, họ tên)');
$sh2->mergeCells("AK{$commentRow2}:AN{$commentRow2}")
    ->setCellValue("AK{$commentRow2}", '(Ký, họ tên)');
$sh2->getStyle("B{$commentRow2}:AN{$commentRow2}")->getFont()->setItalic(true);
$sh2->getStyle("B{$commentRow2}:AN{$commentRow2}")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

// Dòng 3: merge R–W & AK–AN, giữ đậm, center
$sh2->mergeCells("R{$commentRow3}:W{$commentRow3}");
$sh2->mergeCells("AK{$commentRow3}:AN{$commentRow3}");
$sh2->getStyle("R{$commentRow3}:AN{$commentRow3}")->getFont()->setBold(true);
$sh2->getStyle("R{$commentRow3}:AN{$commentRow3}")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

// Dòng 4: merge B–K, left, đậm, ghi 'Ký hiệu chấm công:'
$sh2->mergeCells("B{$codeRow}:K{$codeRow}")
    ->setCellValue("B{$codeRow}", 'Ký hiệu chấm công:');
$sh2->getStyle("B{$codeRow}:K{$codeRow}")->getFont()->setBold(true);
$sh2->getStyle("B{$codeRow}:K{$codeRow}")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    ->setVertical(Alignment::VERTICAL_CENTER);

// Dòng tiếp theo: liệt kê các mục ở cột B và C
$itemsB = [
    'Lương SP:', 'Lương thời gian:', 'Ốm, điều dưỡng:',
    'Con ốm:', 'Thai sản:', 'Tai nạn:'
];
$itemsC = ['SP', '+', 'Ô', 'Cô', 'TS', 'T'];
$startItemRow = $codeRow + 1;
foreach ($itemsB as $i => $text) {
    $sh2->setCellValue("B" . ($startItemRow + $i), $text);
}
foreach ($itemsC as $i => $text) {
    $sh2->setCellValue("C" . ($startItemRow + $i), $text);
}

// Dòng tiếp theo: liệt kê các mục nghỉ ở E–K
$negItems = [
    'Nghỉ phép:', 'Hội nghị, học tập:', 'Nghỉ bù:',
    'Nghỉ không lương:', 'Ngừng việc:', 'Lao động nghĩa vụ:'
];
foreach ($negItems as $i => $text) {
    $r = $startItemRow + $i;
    $sh2->mergeCells("E{$r}:K{$r}")
        ->setCellValue("E{$r}", $text);
    $sh2->getStyle("E{$r}:K{$r}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);
}
// Dòng tiếp theo: liệt kê các mã ở cột L
$codes = ['P', 'H', 'NB', 'KL', 'N', 'LĐ'];
foreach ($codes as $i => $text) {
    $sh2->setCellValue("L" . ($startItemRow + $i), $text);
}
// Giả sử $endRow đã chứa số dòng “Tổng cộng” cuối cùng
for ($row = 7; $row <= $endRow; $row++) {
    $sh2->getRowDimension($row)->setRowHeight(22);
}

// --- 1) Tính các chỉ số dòng cho Sheet2 ---
$countMembers        = count($members);               // số user mỗi phần
$dataStartRow        = 9;                             // Zone 2 bắt đầu từ A9
$nightDataEndRow     = $dataStartRow + $countMembers - 1;  // Zone 2 kết thúc
$labelRow            = $nightDataEndRow + 1;         // Zone 3 (header "Chiều thứ 7 + CN + Lễ")
$weekendDataStart    = $labelRow + 1;                // Zone 4 bắt đầu
$weekendLastDataRow  = $weekendDataStart + $countMembers - 1; // Zone 4 kết thúc
$weekendTotalRow     = $weekendLastDataRow + 1;      // Zone 5 (hàng Tổng cộng)

// --- 3) Xác định cột đầu/cuối ---
$startCol = 'A';
$endCol   = 'AN';

// --- 4) Zone 1: A5:AN8 – viền ngoài & lưới dọc/ngang LIỀN ---
$style = $sh2->getStyle("{$startCol}5:{$endCol}8");
$style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$b = $style->getBorders();
$b->getOutline()->setBorderStyle(Border::BORDER_THIN);
$b->getVertical()->setBorderStyle(Border::BORDER_THIN);
$b->getHorizontal()->setBorderStyle(Border::BORDER_THIN);

// --- 5) Zone 2: A9:AN{$nightDataEndRow} – OUTLINE trái/phải LIỀN, vertical LIỀN, horizontal ĐỨT ---
$style = $sh2->getStyle("{$startCol}{$dataStartRow}:{$endCol}{$nightDataEndRow}");
$style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$b = $style->getBorders();
// Outline biên ngoài
$b->getLeft()->setBorderStyle(Border::BORDER_THIN);
$b->getRight()->setBorderStyle(Border::BORDER_THIN);
// Kẻ dọc giữa các cột
$b->getVertical()->setBorderStyle(Border::BORDER_THIN);
// Kẻ ngang giữa các hàng (dashed)
$b->getHorizontal()->setBorderStyle(Border::BORDER_DASHED);

// --- 6) Zone 3: A{$labelRow}:AN{$labelRow} – viền ngoài & lưới LIỀN ---
$style = $sh2->getStyle("{$startCol}{$labelRow}:{$endCol}{$labelRow}");
$style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$b = $style->getBorders();
$b->getLeft()->setBorderStyle(Border::BORDER_THIN);
$b->getRight()->setBorderStyle(Border::BORDER_THIN);
$b->getTop()->setBorderStyle(Border::BORDER_THIN);
$b->getBottom()->setBorderStyle(Border::BORDER_THIN);
$b->getVertical()->setBorderStyle(Border::BORDER_THIN);
$b->getHorizontal()->setBorderStyle(Border::BORDER_THIN);
$sh2->getStyle("A{$labelRow}:AN{$labelRow}")
    ->getFont()
        ->setSize(11);

// --- 7) Zone 4: A{$weekendDataStart}:AN{$weekendLastDataRow} – OUTLINE trái/phải LIỀN, vertical LIỀN, horizontal ĐỨT ---
$style = $sh2->getStyle("{$startCol}{$weekendDataStart}:{$endCol}{$weekendLastDataRow}");
$style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$b = $style->getBorders();
$b->getLeft()->setBorderStyle(Border::BORDER_THIN);
$b->getRight()->setBorderStyle(Border::BORDER_THIN);
$b->getVertical()->setBorderStyle(Border::BORDER_THIN);
$b->getHorizontal()->setBorderStyle(Border::BORDER_DASHED);
// --- Merge & Center ở hàng Tổng cộng ---
$mergeRange = "D{$weekendTotalRow}:AH{$weekendTotalRow}";
$sh2->mergeCells($mergeRange);
$sh2->getStyle($mergeRange)
    ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
// --- 8) Zone 5: A{$weekendTotalRow}:AN{$weekendTotalRow} – viền ngoài & lưới LIỀN ---
$style = $sh2->getStyle("{$startCol}{$weekendTotalRow}:{$endCol}{$weekendTotalRow}");
$style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$b = $style->getBorders();
$b->getLeft()->setBorderStyle(Border::BORDER_THIN);
$b->getRight()->setBorderStyle(Border::BORDER_THIN);
$b->getTop()->setBorderStyle(Border::BORDER_THIN);
$b->getBottom()->setBorderStyle(Border::BORDER_THIN);
$b->getVertical()->setBorderStyle(Border::BORDER_THIN);
$b->getHorizontal()->setBorderStyle(Border::BORDER_THIN);

// 8) Quay lại Sheet1
$ss->setActiveSheetIndex(0);
$month = date('m');
$year  = date('Y');
// Xuất file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Bảng chấm công tháng ' . $month . ' năm ' . $year . '.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;