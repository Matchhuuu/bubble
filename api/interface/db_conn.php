<?php
$_cfg = dirname(__DIR__) . '/db_config.php';
if (file_exists($_cfg)) { require_once $_cfg; }

$sname    = defined('_DB_HOST') ? _DB_HOST : ($_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'mysql-35594af1-reapquizon-ff22.h.aivencloud.com');
$unmae    = defined('_DB_USER') ? _DB_USER : ($_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'avnadmin');
$password = defined('_DB_PASS') ? _DB_PASS : ($_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null);
$db_name  = defined('_DB_NAME') ? _DB_NAME : ($_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: 'defaultdb');
$port     = defined('_DB_PORT') ? _DB_PORT : ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: '22331');

if (!$password) { die('❌ DB_PASSWORD is not configured. Set it in api/db_config.php or as an environment variable.'); }

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

$ssl_flag = defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT') ? MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT : 64;

if (!mysqli_real_connect($conn, $sname, $unmae, $password, $db_name, (int)$port, NULL, $ssl_flag)) {
    die("❌ Connection Failed: " . mysqli_connect_error());
}

$mode_res = $conn->query("SELECT @@sql_mode as mode");
if ($mode_res && $row = $mode_res->fetch_assoc()) {
    $clean_modes = array_filter(explode(',', $row['mode']), function($m) {
        return trim($m) !== '' && trim($m) !== 'ONLY_FULL_GROUP_BY';
    });
    $conn->query("SET SESSION sql_mode='" . implode(',', $clean_modes) . "'");
}
?>