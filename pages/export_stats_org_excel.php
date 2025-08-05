<?php
// pages/export_stats_org_excel.php
session_start();
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

$autoload = __DIR__ . '/../lib/autoload.php';
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
    "SELECT u.id, COALESCE(p.full_name,u.email) AS full_name
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
   ->setTitle('Bảng chấm công')
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
    $sh->getColumnDimension('B')->setWidth(30);
    $sh->getColumnDimension('C')->setWidth(9);
    for ($col = 'D'; $col !== Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('AH')+1); $col++) {
        $sh->getColumnDimension($col)->setWidth(4);
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
    $sh->mergeCells('A2:G2')->setCellValue('A2', "TÊN ĐƠN VỊ: $orgName");
    $s = $sh->getStyle('A2');
    $s->getFont()->setSize(12)->setBold(true);
    $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $sh->mergeCells('A3:G3')->setCellValue('A3', "BỘ PHẬN: $dept");
    $s = $sh->getStyle('A3');
    $s->getFont()->setSize(12)->setBold(true);
    $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $sh->mergeCells('L2:AH2')->setCellValue('L2', $title);
    $s = $sh->getStyle('L2');
    $s->getFont()->setSize(22)->setBold(true);
    $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sh->mergeCells('AI2:AN2')->setCellValue('AI2', 'Mẫu số: 01a - LĐTL (QĐ 48/2006/QĐ-BTC 14/9/2006)');
    $s = $sh->getStyle('AI2');
    $s->getFont()->setSize(10)->setBold(true);
    $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

    $sh->mergeCells('L3:AH3')->setCellValue('L3', "Ngày " . date('d') . " tháng " . date('m') . " năm " . date('Y'));
    $s = $sh->getStyle('L3');
    $s->getFont()->setSize(13)->setBold(true);
    $s->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Table header rows 5-6
    $sh->mergeCells('A5:A6')->setCellValue('A5','STT');
    $sh->mergeCells('B5:B6')->setCellValue('B5','Họ và tên');
    $sh->mergeCells('C5:C6')->setCellValue('C5','Ngạch, bậc');
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
    $labels = ['SP','SP CN','TG','Nghỉ100%','Nghỉ…%','BHXH'];
    foreach ($labels as $k => $v) {
        $c = Coordinate::stringFromColumnIndex(35 + $k);
        $sh->setCellValue("{$c}6", $v);
    }

    return $sh;
}

// Sheet1
$sh1 = initSheet($ss, 0, 'Bảng chấm công sản phẩm');
$row = 8;
foreach ($members as $idx => $m) {
    $sh1->setCellValue("A{$row}", $idx + 1);
    $sh1->setCellValue("B{$row}", $m['full_name']);
    $sh1->setCellValue("C{$row}", '');

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

/// Đóng border và set font size cho bảng
$lastRow = $row - 1;
$range = "A6:AN{$lastRow}";
$sh1->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sh1->getStyle("A7:AN{$lastRow}")->getFont()->setSize(10);
$sh1->getStyle($range)->getAlignment()->setWrapText(true);
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
    'SP:',
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
    $sh1->mergeCells("E{$r}:J{$r}")->setCellValue("E{$r}", $text);
    $sh1->getStyle("E{$r}:J{$r}")
        ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
}
        // Dòng tiếp theo: liệt kê các mục ở cột C
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
    $sh1->setCellValue("K" . ($startItemRow + $i), $text);
}
// Sheet2
$sh2 = initSheet($ss, 1, 'BẢNG CHẤM CÔNG LÀM THÊM GIỜ');
// TODO: tương tự cho sheet2

// Xuất file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="thong_ke_to_{$orgId}_{$month}_{$year}.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;