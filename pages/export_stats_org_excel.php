<?php
// pages/export_stats_org_excel.php
session_start();
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

// — Params —
$orgId       = (int)($_GET['org_id'] ?? 0);
$month       = (int)($_GET['month']  ?? date('n'));
$year        = (int)($_GET['year']   ?? date('Y'));
$startDate   = sprintf('%04d-%02d-01', $year, $month);
$endDate     = date('Y-m-t', strtotime($startDate));
$daysInMonth = (int)date('t', strtotime($startDate));

// — DB —
$pdo = new PDO(
  "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
  DB_USER, DB_PASS,
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);

// fetch members
$stmt = $pdo->prepare("
  SELECT u.id, COALESCE(p.full_name,u.email) AS full_name
  FROM organization_members m
  JOIN users u ON u.id=m.user_id
  LEFT JOIN organization_member_profiles p ON p.member_id=m.id
  WHERE m.organization_id=:oid
  ORDER BY u.email
");
$stmt->execute([':oid'=>$orgId]);
$members = $stmt->fetchAll();

// fetch entries helper
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

// build holiday map
$holidays = [];
foreach($members as $m){
  foreach(fetchEntries($m['id'],$startDate,$endDate) as $r){
    $d = (new DateTime($r['entry_date']))->format('Y-m-d');
    if (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu',trim($r['content']))) {
      $holidays[$d] = true;
    }
  }
}

// prepare headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=UTF-8');
header('Content-Disposition: attachment;filename="thong_ke_to_'.$orgId.'_'.$month.'_'.$year.'.xlsx"');
echo '<?xml version="1.0"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook 
  xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
>
  <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
    <Author><?= htmlspecialchars($_SESSION['user']['email'] ?? '') ?></Author>
    <Created><?= date('c') ?></Created>
  </DocumentProperties>
  <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
    <WindowHeight>9000</WindowHeight>
    <WindowWidth>15000</WindowWidth>
    <ProtectStructure>False</ProtectStructure>
    <ProtectWindows>False</ProtectWindows>
  </ExcelWorkbook>

  <!-- Styles -->
  <Styles>
    <Style ss:ID="hdr">
      <Font ss:Bold="1" ss:Size="12"/>
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
      <Interior ss:Color="#DDD" ss:Pattern="Solid"/>
    </Style>
    <Style ss:ID="c">
      <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    </Style>
  </Styles>

  <!-- Sheet1: Sản phẩm -->
  <Worksheet ss:Name="Sản phẩm (T2–T7)">
    <Table>
      <!-- Header row -->
      <Row ss:StyleID="hdr">
        <Cell><Data ss:Type="String">Thành viên</Data></Cell>
<?php for($d=1;$d<=$daysInMonth;$d++): ?>
        <Cell ss:StyleID="c"><Data ss:Type="String"><?= sprintf('%02d',$d) ?></Data></Cell>
<?php endfor; ?>
      </Row>
<?php foreach($members as $m): 
    $ents = fetchEntries($m['id'],$startDate,$endDate);
    $work=[]; 
    foreach($ents as $r){
      $dt = (new DateTime($r['entry_date']))->format('Y-m-d');
      $c  = trim($r['content']);
      if (!preg_match('/^\s*Nghỉ\b/iu',$c) && !preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu',$c)) {
        $work[$dt][] = $r['period'];
      }
    }
?>
      <Row>
        <Cell><Data ss:Type="String"><?= htmlspecialchars($m['full_name']) ?></Data></Cell>
<?php for($d=1;$d<=$daysInMonth;$d++):
    $date = sprintf('%04d-%02d-%02d',$year,$month,$d);
    $wd   = (int)date('N',strtotime($date));
    if (!empty($holidays[$date])) {
      $val = '';
    } elseif ($wd===6) {
      $val = in_array('morning',$work[$date]??[],true)?'K/2':'';
    } elseif ($wd===7) {
      $val = '';
    } else {
      $morn = in_array('morning',$work[$date]??[],true);
      $aft  = in_array('afternoon',$work[$date]??[],true);
      $val  = $morn&&$aft?'K':($morn||$aft?'K/2':'');
    }
?>
        <Cell ss:StyleID="c"><Data ss:Type="String"><?= $val ?></Data></Cell>
<?php endfor; ?>
      </Row>
<?php endforeach; ?>
    </Table>
  </Worksheet>

  <!-- Sheet2: Buổi tối & Cuối tuần -->
  <Worksheet ss:Name="Buổi tối & Cuối tuần">
    <Table>
      <!-- Buổi tối header -->
      <Row ss:StyleID="hdr">
        <Cell><Data ss:Type="String">Thành viên</Data></Cell>
<?php for($d=1;$d<=$daysInMonth;$d++): ?>
        <Cell ss:StyleID="c"><Data ss:Type="String"><?= sprintf('%02d',$d) ?></Data></Cell>
<?php endfor; ?>
      </Row>
<?php foreach($members as $m):
    $ents = fetchEntries($m['id'],$startDate,$endDate);
    $ev=[]; 
    foreach($ents as $r){
      $dt=(new DateTime($r['entry_date']))->format('Y-m-d');
      if(stripos($r['content'],'evening')!==false || !empty($holidays[$dt])){
        $ev[$dt]=true;
      }
    }
?>
      <Row>
        <Cell><Data ss:Type="String"><?= htmlspecialchars($m['full_name']) ?></Data></Cell>
<?php for($d=1;$d<=$daysInMonth;$d++):
    $date = sprintf('%04d-%02d-%02d',$year,$month,$d);
    $val  = !empty($ev[$date])?'K/2':''; 
?>
        <Cell ss:StyleID="c"><Data ss:Type="String"><?= $val ?></Data></Cell>
<?php endfor; ?>
      </Row>
<?php endforeach; ?>

      <!-- blank row -->
      <Row/><Row/>

      <!-- Chiều Thứ7, CN & Lễ header -->
      <Row ss:StyleID="hdr">
        <Cell><Data ss:Type="String">Thành viên</Data></Cell>
<?php for($d=1;$d<=$daysInMonth;$d++): ?>
        <Cell ss:StyleID="c"><Data ss:Type="String"><?= sprintf('%02d',$d) ?></Data></Cell>
<?php endfor; ?>
      </Row>
<?php foreach($members as $m):
    $ents = fetchEntries($m['id'],$startDate,$endDate);
    $wk=[]; 
    foreach($ents as $r){
      $dt=(new DateTime($r['entry_date']))->format('Y-m-d');
      if (!preg_match('/^\s*Nghỉ\b/iu',$r['content'])) {
        $wk[$dt][] = $r['period'];
      }
    }
?>
      <Row>
        <Cell><Data ss:Type="String"><?= htmlspecialchars($m['full_name']) ?></Data></Cell>
<?php for($d=1;$d<=$daysInMonth;$d++):
    $date = sprintf('%04d-%02d-%02d',$year,$month,$d);
    $wd   = (int)date('N',strtotime($date));
    $val  = '';
    if (!empty($holidays[$date])) {
      $morn = in_array('morning',$wk[$date]??[],true);
      $aft  = in_array('afternoon',$wk[$date]??[],true);
      $val  = $morn&&$aft?'K':($morn||$aft?'K/2':'');
    } elseif ($wd===6) {
      $val = in_array('afternoon',$wk[$date]??[],true)?'K/2':'';
    } elseif ($wd===7) {
      $morn = in_array('morning',$wk[$date]??[],true);
      $aft  = in_array('afternoon',$wk[$date]??[],true);
      $val  = ($morn&&$aft)?'CN':($morn||$aft?'CN/2':'');
    }
?>
        <Cell ss:StyleID="c"><Data ss:Type="String"><?= $val ?></Data></Cell>
<?php endfor; ?>
      </Row>
<?php endforeach; ?>

    </Table>
  </Worksheet>

</Workbook>
