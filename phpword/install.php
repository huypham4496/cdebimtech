<?php
/**
 * Installer for PHPWord (manual hosting without Composer).
 * - Downloads the official ZIP from GitHub (or custom URL) and extracts /src/PhpWord into /phpword/src/PhpWord
 * - Requirements: allow_url_fopen OR cURL; ZipArchive extension for automatic extraction
 *
 * Usage:
 *   1) Upload this file to /phpword/install.php on your host (already included in the zip I gave you)
 *   2) Open it in browser: http(s)://your-host/phpword/install.php
 *   3) It will attempt to download and extract the library.
 */

@ini_set('max_execution_time', '600');
@ini_set('memory_limit', '512M');

$BASE_DIR = __DIR__;
$ROOT_DIR = dirname($BASE_DIR);

// Choose a source: you can change to a specific tag
// Example tag: https://api.github.com/repos/PHPOffice/PHPWord/zipball/v1.4.1
$SOURCE_URL = isset($_GET['url']) && $_GET['url'] ? $_GET['url'] : 'https://github.com/PHPOffice/PHPWord/archive/refs/heads/master.zip';
$TMP_ZIP   = $BASE_DIR . '/PHPWord.zip';
$TARGET    = $BASE_DIR . '/src';

function out($s){ echo $s . "<br>\n"; @ob_flush(); @flush(); }

out("<b>PHPWord manual installer</b>");
out("Download URL: " . htmlspecialchars($SOURCE_URL));

// 1) Download ZIP
$data = false;

// Try cURL
if (function_exists('curl_init')) {
    $ch = curl_init($SOURCE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'PHPWord-Installer',
        CURLOPT_TIMEOUT => 120,
    ]);
    $data = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data === false || $code >= 400) {
        out("cURL failed: HTTP $code - $err");
        $data = false;
    }
}

// Try file_get_contents
if ($data === false && ini_get('allow_url_fopen')) {
    $ctx = stream_context_create([
        'http' => ['method'=>'GET','header'=>"User-Agent: PHPWord-Installer\r\n"],
        'https'=> ['method'=>'GET','header'=>"User-Agent: PHPWord-Installer\r\n"],
    ]);
    $data = @file_get_contents($SOURCE_URL, false, $ctx);
    if ($data === false) {
        out("file_get_contents failed.");
    }
}

if ($data === false) {
    out("<span style='color:red'>Download failed. Please download the ZIP manually and upload to /phpword/PHPWord.zip then refresh this page.</span>");
    exit;
}

if (@file_put_contents($TMP_ZIP, $data) === false) {
    out("<span style='color:red'>Cannot write ZIP to $TMP_ZIP</span>");
    exit;
}
out("Downloaded ZIP: " . basename($TMP_ZIP) . " (" . number_format(filesize($TMP_ZIP)) . " bytes)");

// 2) Extract ZIP
if (!class_exists('ZipArchive')) {
    out("<span style='color:orange'>ZipArchive not available. Please extract PHPWord.zip locally and upload /src/PhpWord into /phpword/src/PhpWord</span>");
    exit;
}

$zip = new ZipArchive();
if ($zip->open($TMP_ZIP) !== true) {
    out("<span style='color:red'>Failed to open ZIP.</span>");
    exit;
}

// Find the top-level folder in the ZIP
$top = null;
for ($i=0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    $name = $stat['name'];
    if (substr($name, -1) === '/' && substr_count($name, '/') === 1) { $top = $name; break; }
}
if ($top === null) { $top = $zip->getNameIndex(0); }

// Extract to a temp dir
$tmpExtract = $BASE_DIR . '/_tmp_extract_' . uniqid();
@mkdir($tmpExtract, 0775, true);
if (!$zip->extractTo($tmpExtract)) {
    out("<span style='color:red'>Failed to extract ZIP.</span>");
    $zip->close();
    exit;
}
$zip->close();
out("Extracted to temp: " . basename($tmpExtract));

// Move src/PhpWord into /phpword/src/PhpWord
$srcPhpWord = rtrim($tmpExtract, '/\\') . '/' . rtrim($top, '/\\') . '/src/PhpWord';
if (!is_dir($srcPhpWord)) {
    // Some zips may not have the same layout; try alternative
    $alt = rtrim($tmpExtract, '/\\') . '/' . rtrim($top, '/\\') . '/PhpWord/src/PhpWord';
    if (is_dir($alt)) $srcPhpWord = $alt;
}

if (!is_dir($srcPhpWord)) {
    out("<span style='color:red'>Cannot locate src/PhpWord inside the ZIP.</span>");
    exit;
}

@mkdir($TARGET, 0775, true);

// Recursively copy
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcPhpWord, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($it as $item) {
    $dest = $TARGET . substr($item->getPathname(), strlen($srcPhpWord));
    if ($item->isDir()) {
        if (!is_dir($dest)) @mkdir($dest, 0775, true);
    } else {
        @copy($item->getPathname(), $dest);
    }
}

out("Copied PHPWord src into: " . htmlspecialchars($TARGET . '/PhpWord'));
out("<b>DONE.</b> Now include: <code>require __DIR__.'/autoload.php';</code> and use PhpOffice\\PhpWord.");

@unlink($TMP_ZIP);
// Clean temp
function rrmdir($dir){ if (!is_dir($dir)) return; $files = array_diff(scandir($dir), ['.','..']); foreach ($files as $f){ $p="$dir/$f"; is_dir($p)?rrmdir($p):@unlink($p);} @rmdir($dir); }
rrmdir($tmpExtract);
