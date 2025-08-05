<?php
// pages/export_stats_org_excel.php
session_start();
require_once __DIR__ . '/../config.php';

// Nếu chưa đăng nhập
if (empty($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

// Kiểm tra autoload
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die('<p>Chưa cài PhpSpreadsheet. Vui lòng chạy <code>composer require phpoffice/phpspreadsheet</code></p>');
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// Tham số
$orgId       = isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0;
$month       = isset($_GET['month'])  ? (int)$_GET['month']  : date('n');
$year        = isset($_GET['year'])   ? (int)$_GET['year']   : date('Y');

// Kết nối DB
$pdo = new PDO(
    "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);
// Lấy tên đơn vị + bộ phận
$stmt = $pdo->prepare("SELECT name, department FROM organizations WHERE id = :oid");
$stmt->execute([':oid'=>$orgId]);
$org = $stmt->fetch();
$orgName = strtoupper($org['name'] ?? '');
$dept    = strtoupper($org['department'] ?? '');

// Lấy members
$stmt = $pdo->prepare("
    SELECT u.id, COALESCE(p.full_name,u.email) AS full_name
    FROM organization_members m
      JOIN users u ON u.id = m.user_id
      LEFT JOIN organization_member_profiles p ON p.member_id=m.id
    WHERE m.organization_id=:oid
    ORDER BY u.email
");
$stmt->execute([':oid'=>$orgId]);
$members = $stmt->fetchAll();

// Lấy entries
function fetchEntries($uid,$start,$end){
    global $pdo;
    $q = $pdo->prepare("
      SELECT entry_date,period,content
      FROM work_diary_entries
      WHERE user_id=:u AND entry_date BETWEEN :s AND :e
    ");
    $q->execute([':u'=>$uid,':s'=>$start,':e'=>$end]);
    return $q->fetchAll();
}

// Build holiday map
$startDate   = sprintf('%04d-%02d-01',$year,$month);
$endDate     = date('Y-m-t',strtotime($startDate));
$daysInMonth = (int)date('t',strtotime($startDate));
$holidays = [];
foreach($members as $m){
    foreach(fetchEntries($m['id'],$startDate,$endDate) as $r){
        $d = (new DateTime($r['entry_date']))->format('Y-m-d');
        if (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu',trim($r['content']))) {
            $holidays[$d]=true;
        }
    }
}

// Tạo spreadsheet
$ss = new Spreadsheet();
$ss->getProperties()
   ->setTitle('Bảng chấm công')
   ->setCreator($_SESSION['user']['email'] ?? '')
   ->setCreated(time());

// Tạo hàm khởi tạo sheet chung
function initSheet($ss,$index,$title){
    global $daysInMonth,$orgName,$dept,$month,$year;
    $sh = $ss->createSheet($index);
    $ss->setActiveSheetIndex($index);
    $sh->setTitle($title);

    // Page setup
    $ps = $sh->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
       ->setPaperSize(PageSetup::PAPERSIZE_A4)
       ->setScale(64);
    $m = $sh->getPageMargins();
    $m->setLeft(0.16)->setTop(0.16)->setRight(0.17)->setBottom(0.18)
      ->setHeader(0.16)->setFooter(0.16);

    // Header merges & styles
    // A2:G2 Tên đơn vị
    $sh->mergeCells('A2:G2');
    $sh->setCellValue('A2',"TÊN ĐƠN VỊ: $orgName");
    $sh->getStyle('A2')->getFont()->setSize(12);
    $sh->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    // Bold phần name
    $start = strpos($sh->getCell('A2')->getValue(),$orgName);
    $sh->getStyle("A2")->getFont()->setBold(true);

    // A3:G3 Bộ phận
    $sh->mergeCells('A3:G3');
    $sh->setCellValue('A3',"BỘ PHẬN: $dept");
    $sh->getStyle('A3')->getFont()->setSize(12)->setBold(true);
    $sh->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // L2:AH2 Title
    $sh->mergeCells('L2:AH2');
    $sh->setCellValue('L2',$title);
    $sh->getStyle('L2')->getFont()->setSize(22)->setBold(true);
    $sh->getStyle('L2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // AI2:AN2 mẫu số
    $sh->mergeCells('AI2:AN2');
    $sh->setCellValue('AI2','Mẫu số: 01a - LĐTL (Ban hành theo QĐ số: 48/2006/QĐ-BTC ngày 14/9/2006 của BTC)');
    $sh->getStyle('AI2')->getFont()->setSize(10)->setBold(true);
    $sh->getStyle('AI2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // L3:AH3 ngày tháng
    $sh->mergeCells('L3:AH3');
    $sh->setCellValue('L3',"Ngày ".date('d')." tháng ".date('m')." năm ".date('Y'));
    $sh->getStyle('L3')->getFont()->setSize(13)->setBold(true);
    $sh->getStyle('L3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Bảng: từ row 5 header
    // A5:A6 STT
    $sh->mergeCells('A5:A6');
    $sh->setCellValue('A5','STT');
    // B5:B6 Họ và tên
    $sh->mergeCells('B5:B6');
    $sh->setCellValue('B5','Họ và tên');
    // C5:C6 Ngạch...
    $sh->mergeCells('C5:C6');
    $sh->setCellValue('C5','Ngạch, bậc lương hoặc chức vụ');
    // D5:AH5 Ngày trong tháng
    $sh->mergeCells("D5:".\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(34)."5");
    $sh->setCellValue('D5','Ngày trong tháng');
    $sh->getStyle('D5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Days row D6:AH6
    for($i=1;$i<=$daysInMonth;$i++){
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3+$i);
        $sh->setCellValue($col.'6',$i);
    }
    // Quy ra công header AI5:AN5
    $sh->mergeCells('AI5:AN5');
    $sh->setCellValue('AI5','Quy ra công');
    $sh->getStyle('AI5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    // AI6:AN6 labels
    $labels = ['Số công hưởng sản phẩm','Số công hưởng sản phẩm CN','Số công hưởng thời gian','Số công nghỉ việc hưởng 100% lương','Số công nghỉ việc hưởng ...% lương','Số công hưởng BHXH'];
    foreach($labels as $k=>$v){
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(35+$k);
        $sh->setCellValue($col.'6',$v);
    }
    // Row7 A7:C7 A,B,C
    $sh->setCellValue('A7','A');
    $sh->setCellValue('B7','B');
    $sh->setCellValue('C7','C');
    // D7:AH7 weekday abbreviations
    for($i=1;$i<=$daysInMonth;$i++){
        $date = sprintf('%04d-%02d-%02d',$year,$month,$i);
        $wd = ['CN','2','3','4','5','6','7'][(int)date('N',strtotime($date))%7];
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3+$i);
        $sh->setCellValue($col.'7',$wd);
    }
    // AI7:AN7 32..37
    for($i=0;$i<6;$i++){
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(35+$i);
        $sh->setCellValue($col.'7',32+$i);
    }

    return $sh;
}

// Khởi tạo Sheet1
$sh1 = initSheet($ss,0,'Bảng chấm công sản phẩm');
// Ghi dữ liệu bắt đầu row 8
$row=8;
foreach($members as $idx=>$m){
    $sh1->setCellValue("A{$row}",$idx+1);
    $sh1->setCellValue("B{$row}",$m['full_name']);
    $sh1->setCellValue("C{$row}",''); // nếu có chức vụ, map vào đây

    // fetch entries
    $ents = fetchEntries($m['id'],$startDate,$endDate);
    $work=[]; $even=[]; 
    foreach($ents as $r){
        $d=(new DateTime($r['entry_date']))->format('Y-m-d');
        $p = $r['period'];
        $c = trim($r['content']);
        if (stripos($c,'evening')!==false) $even[$d]='K/2';
        elseif (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu',$c)) $work[$d]='';
        else {
            // morning/afternoon
            $m1 = in_array('morning',$work[$d]??[]); 
            $a1 = in_array('afternoon',$work[$d]??[]);
            $work[$d][]=$p;
        }
    }
    // ngày 1–n
    for($i=1;$i<=$daysInMonth;$i++){
        $date = sprintf('%04d-%02d-%02d',$year,$month,$i);
        $wd   = (int)date('N',strtotime($date));
        $val='';
        if (!isset($work[$date]) && !isset($even[$date])) {
            $val='';
        } elseif (isset($even[$date])) {
            $val=$even[$date];
        } else {
            $morn = in_array('morning',$work[$date]??[],true);
            $aft  = in_array('afternoon',$work[$date]??[],true);
            if ($wd===6) {
                $val = $morn?'K/2':'';
            } elseif ($wd===7) {
                $val = '';
            } else {
                $val = $morn&&$aft?'K':($morn||$aft?'K/2':'');
            }
        }
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3+$i);
        $sh1->setCellValue($col.$row,$val);
    }
    // TODO: tính các cột 32–37 và set vào AI–AN
    $row++;
}

// Khởi tạo Sheet2
$sh2 = initSheet($ss,1,'Bảng chấm công làm thêm giờ');
// tương tự ghi dữ liệu vào $sh2 theo cấu trúc sheet2

// Xuất file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="thong_ke_to_'.$orgId.'_'.$month.'_'.$year.'.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
