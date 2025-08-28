<?php
/**
 * Lightweight autoloader wrapper for PHPWord in /phpword
 * Tries Composer first (vendor/autoload.php), then built-in autoloader from /phpword/src.
 */
$__base = __DIR__;
$__root = dirname($__base);

// 1) Composer (if you uploaded vendor/)
$composer = $__root . '/vendor/autoload.php';
if (is_file($composer)) {
    require_once $composer;
    return;
}

// 2) Built-in PHPWord autoloader (manual install)
$srcAutoloader = $__base . '/src/PhpWord/Autoloader.php';
if (is_file($srcAutoloader)) {
    require_once $srcAutoloader;
    \PhpOffice\PhpWord\Autoloader::register();
    return;
}

// Fallback: Show a helpful message
header('Content-Type: text/plain; charset=utf-8');
echo "PHPWord not found.\n";
echo "Please run phpword/install.php to download PHPWord source (or upload /phpword/src/PhpWord manually).\n";
exit(1);
