<?php
// pages/export_stats_org_excel.php
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
$daysInMonth = (int)date('t', strtotime($startDate));
$endDate     = date('Y-m-t', strtotime($startDate));

// — DB & data fetch —
$pdo = new PDO(
    "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);

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

// Tính ngày lễ
$orgHolidayDates = [];
foreach ($members as $m) {
    foreach (fetchEntries($m['id'],$startDate,$endDate) as $r) {
        $d=(new DateTime($r['entry_date']))->format('Y-m-d');
        if (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu',trim($r['content']))) {
            $orgHolidayDates[$d]=true;
        }
    }
}

// Xuất header Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="thong_ke_to_'.$orgId.'_'. $month .'_'. $year .'.xls"');
echo "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />";

// Hàm in bảng
function renderTable($title,$members,$year,$month,$days,$holidays,$mode) {
    echo "<table border='1' cellpadding='3' cellspacing='0'>";
    echo "<tr><th colspan='".($days+1)."' style='background:#ddd;font-weight:bold;'>$title</th></tr>";
    echo "<tr><th>Thành viên</th>";
    for($d=1;$d<=$days;$d++){
        echo "<th>".sprintf('%02d',$d)."</th>";
    }
    echo "</tr>";
    foreach($members as $m){
        echo "<tr><td>".htmlspecialchars($m['full_name'])."</td>";
        $ents = fetchEntries($m['id'],sprintf('%04d-%02d-01',$year,$month),date('Y-m-t',strtotime("$year-$month-01")));
        $workPeriods = [];
        foreach($ents as $r){
            $dt=(new DateTime($r['entry_date']))->format('Y-m-d');
            $c = trim($r['content']);
            if (preg_match('/^(Ngày lễ|Nghỉ lễ)\b/iu',$c)) {
                $workPeriods[$dt]['holiday']=true;
            } elseif ($mode==='prod' && !preg_match('/^\s*Nghỉ\b/iu',$c)) {
                $workPeriods[$dt][]=$r['period'];
            } elseif ($mode==='evening' && stripos($c,'evening')!==false) {
                $workPeriods[$dt][]='evening';
            }
        }
        for($d=1;$d<=$days;$d++){
            $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
            $val='';
            if($mode==='prod'){
                if(!empty($workPeriods[$date]['holiday'])){
                    $val='';
                } else {
                    $w=(int)date('N',strtotime($date));
                    $p=$workPeriods[$date]??[];
                    if($w===6){
                        $val=in_array('morning',$p,true)?'K/2':'';
                    } elseif($w===7){
                        $val='';
                    } else {
                        $morn=in_array('morning',$p,true);
                        $aft =in_array('afternoon',$p,true);
                        $val=$morn&&$aft?'K':($morn||$aft?'K/2':'');
                    }
                }
            } else {
                if(!empty($workPeriods[$date]['holiday']) || in_array('evening',$workPeriods[$date]??[],true)){
                    $val='K/2';
                }
            }
            echo "<td>$val</td>";
        }
        echo "</tr>";
    }
    echo "</table><br/>";
}

// In hai bảng
renderTable('Sản phẩm (T2–T7)',$members,$year,$month,$daysInMonth,$orgHolidayDates,'prod');
renderTable('Buổi tối (T2–CN)',$members,$year,$month,$daysInMonth,$orgHolidayDates,'evening');

exit;
