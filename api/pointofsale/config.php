<?php
$_cfg = dirname(__DIR__) . '/db_config.php';
if (file_exists($_cfg)) { require_once $_cfg; }

define('DB_HOST', defined('_DB_HOST') ? _DB_HOST : 'mysql-35594af1-reapquizon-ff22.h.aivencloud.com');
define('DB_USER', defined('_DB_USER') ? _DB_USER : 'avnadmin');
define('DB_NAME', defined('_DB_NAME') ? _DB_NAME : 'defaultdb');
define('DB_PORT', defined('_DB_PORT') ? _DB_PORT : '22331');

$_db_pass = defined('_DB_PASS') ? _DB_PASS : ($_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null);
if (!$_db_pass) { die('❌ DB_PASSWORD is not configured. Set it in api/db_config.php or as an environment variable.'); }
define('DB_PASS', $_db_pass);
