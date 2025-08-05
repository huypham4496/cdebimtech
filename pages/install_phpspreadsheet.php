<?php
// scripts/install_phpspreadsheet_zip.php
// 1. Cấu hình
$version   = '4.5.0';  // bạn có thể điều chỉnh lên phiên bản mới nhất
$zipUrl    = "https://github.com/PHPOffice/PhpSpreadsheet/archive/refs/tags/{$version}.zip";
$baseDir   = realpath(__DIR__ . '/../lib') . '/PhpSpreadsheet';
$zipFile   = __DIR__ . "/phpspreadsheet-{$version}.zip";

// 2. Tạo thư mục đích
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0755, true)) {
        die("❌ Không thể tạo thư mục {$baseDir}\n");
    }
}

// 3. Tải ZIP về
echo "📥 Đang tải PhpSpreadsheet {$version}...\n";
file_put_contents($zipFile, fopen($zipUrl, 'r'));
if (!file_exists($zipFile)) {
    die("❌ Tải ZIP thất bại từ {$zipUrl}\n");
}

// 4. Giải nén
echo "🔓 Đang giải nén...\n";
$zip = new ZipArchive;
if ($zip->open($zipFile) === true) {
    // Giải tất cả vào lib/PhpSpreadsheet, bỏ thư mục gốc trong ZIP
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        // Bỏ prefix "PhpSpreadsheet-4.5.0/" trong đường dẫn
        $local = substr($entry, strlen("PhpSpreadsheet-{$version}/"));
        if ($local === '' || substr($local, -1) === '/') {
            // thư mục
            @mkdir("{$baseDir}/{$local}", 0755, true);
        } else {
            copy("zip://{$zipFile}#{$entry}", "{$baseDir}/{$local}");
        }
    }
    $zip->close();
    echo "✅ Giải nén xong vào {$baseDir}\n";
} else {
    die("❌ Mở ZIP thất bại\n");
}

// 5. Dọn file ZIP
unlink($zipFile);
echo "🗑️ Đã xóa ZIP tạm\n";

// Hướng dẫn require
echo "\n👍 Hoàn tất! Trong code, bạn chỉ cần:\n";
echo "   require_once __DIR__ . '/../lib/PhpSpreadsheet/vendor/autoload.php';\n";
