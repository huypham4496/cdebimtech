<?php
// --- BEGIN one-time guard to avoid re-defining constants / re-connecting PDO ---
if (defined('CDE_CONFIG_LOADED')) {
    return;
}
define('CDE_CONFIG_LOADED', 1);
// --- END guard ---

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'cde');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
