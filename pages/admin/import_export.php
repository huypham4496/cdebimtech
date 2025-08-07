<?php
session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Kết nối database
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('DB Error: ' . htmlspecialchars($e->getMessage()));
}

$action = $_GET['action'] ?? '';

// == EXPORT ==
if ($action === 'export') {
    $stmt = $pdo->query('SELECT username, first_name, last_name, email, role, dob, address, company, phone FROM users');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    // Header
    $headers = ['Username','Pass','First Name','Last Name','Email','Role','DOB','Address','Company','Phone'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', $h);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }
    // Data rows
    $rowNum = 2;
    foreach ($rows as $r) {
        $col = 'A';
        // Username
        $sheet->setCellValue($col . $rowNum, $r['username']); $col++;
        // Pass (leave blank)
        $sheet->setCellValue($col . $rowNum, ''); $col++;
        // First Name
        $sheet->setCellValue($col . $rowNum, $r['first_name']); $col++;
        // Last Name
        $sheet->setCellValue($col . $rowNum, $r['last_name']); $col++;
        // Email
        $sheet->setCellValue($col . $rowNum, $r['email']); $col++;
        // Role
        $sheet->setCellValue($col . $rowNum, $r['role']); $col++;
        // DOB
        if ($r['dob']) {
            $sheet->setCellValue($col . $rowNum, Date::PHPToExcel(new \DateTime($r['dob'])));
            $sheet->getStyle($col . $rowNum)
                  ->getNumberFormat()
                  ->setFormatCode('yyyy-mm-dd');
        } else {
            $sheet->setCellValue($col . $rowNum, '');
        }
        $col++;
        // Address
        $sheet->setCellValue($col . $rowNum, $r['address']); $col++;
        // Company
        $sheet->setCellValue($col . $rowNum, $r['company']); $col++;
        // Phone
        $sheet->setCellValue($col . $rowNum, $r['phone']); $col++;

        $rowNum++;
    }
    // Xuất file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="users_export_'.date('Ymd_His').'.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

// == IMPORT ==
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['excel_file']['tmp_name'])) {
        die('Vui lòng chọn file Excel để import.');
    }
    try {
        $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
    } catch (Exception $e) {
        die('Lỗi load file Excel: ' . htmlspecialchars($e->getMessage()));
    }

    $maxRow = $sheet->getHighestRow();
    for ($row = 2; $row <= $maxRow; $row++) {
        $username   = trim((string)$sheet->getCell("A{$row}")->getValue());
        $plainPass  = trim((string)$sheet->getCell("B{$row}")->getValue());
        $first_name = trim((string)$sheet->getCell("C{$row}")->getValue());
        $last_name  = trim((string)$sheet->getCell("D{$row}")->getValue());
        $email      = trim((string)$sheet->getCell("E{$row}")->getValue());
        $role       = trim((string)$sheet->getCell("F{$row}")->getValue()) ?: 'user';
        $dobVal     = $sheet->getCell("G{$row}")->getValue();
        $address    = trim((string)$sheet->getCell("H{$row}")->getValue());
        $company    = trim((string)$sheet->getCell("I{$row}")->getValue());
        $phone      = trim((string)$sheet->getCell("J{$row}")->getValue());

        if (!$username || !$email) continue;

        // Hash mật khẩu plain
        $passwordHash = password_hash($plainPass ?: '1', PASSWORD_DEFAULT);

        // Xử lý DOB
        $dob = null;
        if ($dobVal) {
            $cell = $sheet->getCell("G{$row}");
            if (Date::isDateTime($cell)) {
                $dob = date('Y-m-d', Date::excelToTimestamp($dobVal));
            } else {
                $dob = date('Y-m-d', strtotime($dobVal));
            }
        }

        // Insert hoặc update
        $sql = "INSERT INTO users
                  (username, password_hash, first_name, last_name, email, role, dob, address, company, phone)
                VALUES
                  (:username, :password_hash, :first_name, :last_name, :email, :role, :dob, :address, :company, :phone)
                ON DUPLICATE KEY UPDATE
                  password_hash = VALUES(password_hash),
                  first_name    = VALUES(first_name),
                  last_name     = VALUES(last_name),
                  email         = VALUES(email),
                  role          = VALUES(role),
                  dob           = VALUES(dob),
                  address       = VALUES(address),
                  company       = VALUES(company),
                  phone         = VALUES(phone)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':username'      => $username,
            ':password_hash' => $passwordHash,
            ':first_name'    => $first_name,
            ':last_name'     => $last_name,
            ':email'         => $email,
            ':role'          => $role,
            ':dob'           => $dob,
            ':address'       => $address,
            ':company'       => $company,
            ':phone'         => $phone,
        ]);
    }

    header('Location: index.php?import=success');
    exit;
}

// Nếu không export/import
header('Location: index.php');
exit;
