<?php
declare(strict_types=1);
/**
 * Export Excel - Naming Rules cho 1 Project
 * GET: ?project_id=#
 * Yêu cầu: PhpSpreadsheet (vendor/autoload.php)
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$ROOT = realpath(__DIR__ . '/../..'); // /pages/partials -> project root
if ($ROOT && is_file($ROOT . '/config.php'))               require_once $ROOT . '/config.php';
if ($ROOT && is_file($ROOT . '/includes/helpers.php'))     require_once $ROOT . '/includes/helpers.php';
if ($ROOT && is_file($ROOT . '/vendor/autoload.php'))      require_once $ROOT . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;

function fail($msg, $code=400){
  http_response_code($code);
  header('Content-Type: text/plain; charset=utf-8');
  echo $msg;
  exit;
}

try {
  // PDO
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('getPDO')) {
      $pdo = getPDO();
    } elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
      $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
      $pdo = new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '', [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
      ]);
    } else {
      fail('Database config is missing', 500);
    }
  }

  $project_id = (int)($_GET['project_id'] ?? 0);
  if ($project_id <= 0) fail('Missing project_id');

  // Lấy tên project để đặt tên file
  $stmP = $pdo->prepare("SELECT name FROM projects WHERE id = :id LIMIT 1");
  $stmP->execute([':id' => $project_id]);
  $project_name = (string)($stmP->fetchColumn() ?: ('Project_' . $project_id));

  // Lấy dữ liệu xuất
  $stm = $pdo->prepare("
    SELECT id, project_id, project_name, originator, system_code, level_code, type_code, role_code,
           number_seq, file_title, extension, computed_filename
    FROM project_naming_rules
    WHERE project_id = :pid
    ORDER BY id ASC
  ");
  $stm->execute([':pid'=>$project_id]);
  $rows = $stm->fetchAll();

  $spreadsheet = new Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();
  $sheet->setTitle('Naming Rules');

  // ===== Header (TIẾNG VIỆT, VIẾT HOA) =====
  // A: STT, B: TÊN FILE (đầy đủ), C: TÊN DỰ ÁN, D: ĐƠN VỊ, E: KHỐI TÍCH/HỆ THỐNG,
  // F: CAO TRÌNH/VỊ TRÍ/LÝ TRÌNH, G: LOẠI, H: VAI TRÒ, I: SỐ, J: TÊN FILE (CƠ SỞ), K: ĐUÔI
  $headers = [
    'A1' => 'STT',
    'B1' => 'TÊN FILE',
    'C1' => 'TÊN DỰ ÁN',
    'D1' => 'ĐƠN VỊ',
    'E1' => 'KHỐI TÍCH/HỆ THỐNG',
    'F1' => 'CAO TRÌNH/VỊ TRÍ/LÝ TRÌNH',
    'G1' => 'LOẠI',
    'H1' => 'VAI TRÒ',
    'I1' => 'SỐ',
    'J1' => 'TÊN FILE',
    'K1' => 'LOẠI FILE',
  ];
  foreach ($headers as $cell => $text) {
    $sheet->setCellValue($cell, $text);
  }

  // ===== Data =====
  $r = 2; $stt = 1;
  foreach ($rows as $row) {
    $sheet->setCellValue("A{$r}", $stt++);                                            // STT
    $sheet->setCellValue("B{$r}", $row['computed_filename']);                         // Tên file đầy đủ
    $sheet->setCellValue("C{$r}", $row['project_name']);                              // Tên dự án
    $sheet->setCellValue("D{$r}", $row['originator']);                                // Đơn vị
    $sheet->setCellValue("E{$r}", $row['system_code']);                               // Hệ thống
    $sheet->setCellValue("F{$r}", $row['level_code']);                                // Cao trình/Vị trí/Lý trình
    $sheet->setCellValue("G{$r}", $row['type_code']);                                 // Loại
    $sheet->setCellValue("H{$r}", $row['role_code']);                                 // Vai trò
    $sheet->setCellValue("I{$r}", str_pad((string)($row['number_seq'] ?? 1), 4, '0', STR_PAD_LEFT)); // Số
    $sheet->setCellValue("J{$r}", $row['file_title']);                                // File base
    $sheet->setCellValue("K{$r}", $row['extension']);                                 // Đuôi
    $r++;
  }
  $lastRow = max(1, $r - 1);

  // ===== Layout / Styles =====
  // Hàng 1: cao 25, đậm, căn giữa & giữa
  $sheet->getRowDimension(1)->setRowHeight(25);
  $sheet->getStyle("A1:K1")->getFont()->setBold(true);
  $sheet->getStyle("A1:K1")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);

  // Căn giữa:
  if ($lastRow >= 1) {
    // - Cột A và C..K: middle & center
    $sheet->getStyle("A1:A{$lastRow}")->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_CENTER)
          ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("C1:K{$lastRow}")->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_CENTER)
          ->setVertical(Alignment::VERTICAL_CENTER);
    // - Cột B: chỉ vertical middle
    $sheet->getStyle("B1:B{$lastRow}")->getAlignment()
          ->setVertical(Alignment::VERTICAL_CENTER);
  }

  // ===== Viền (borders) theo yêu cầu =====
  $black = new Color('FF000000');
  $tableRange = "A1:K{$lastRow}";

  // 1) Vạch dọc phân tách cột: THIN (liền mảnh) cho toàn bảng
  $sheet->getStyle($tableRange)->getBorders()->getVertical()
        ->setBorderStyle(Border::BORDER_THIN)
        ->setColor($black);

  // 2) Vạch ngang phân tách hàng:
  //    - Giữa hàng 1 và 2: THIN (liền mảnh) → đáy hàng 1
  if ($lastRow >= 2) {
    $sheet->getStyle("A1:K1")->getBorders()->getBottom()
          ->setBorderStyle(Border::BORDER_THIN)
          ->setColor($black);
  }
  //    - Từ đáy hàng 2 đến đáy hàng áp chót: DASHED (đứt mảnh) — kẻ THEO TỪNG HÀNG
  if ($lastRow >= 3) {
    for ($ri = 2; $ri <= $lastRow - 1; $ri++) {
      $sheet->getStyle("A{$ri}:K{$ri}")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_DASHED)
            ->setColor($black);
    }
  }

  // 3) Viền bao quanh OUTLINE: DOUBLE (đôi mảnh) — đặt SAU cùng
  $sheet->getStyle($tableRange)->getBorders()->getOutline()
        ->setBorderStyle(Border::BORDER_DOUBLE)
        ->setColor($black);

  // Auto width
  foreach (range('A','K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
  }

  // ===== Tên file: "Danh sách file {Project}.xlsx" (giữ Unicode) =====
  $filename = "Danh sách file {$project_name}.xlsx";
  $asciiFallback = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
  if (!$asciiFallback) $asciiFallback = 'Danh_sach_file.xlsx';

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="'.$asciiFallback.'"; filename*=UTF-8\'\''.rawurlencode($filename));
  header('Cache-Control: max-age=0');

  $writer = new Xlsx($spreadsheet);
  $writer->save('php://output');
  exit;

} catch (Throwable $e) {
  fail('Export failed: ' . $e->getMessage(), 500);
}
