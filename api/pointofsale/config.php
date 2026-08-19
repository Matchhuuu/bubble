<?php
define('DB_HOST', "mysql-35594af1-reapquizon-ff22.h.aivencloud.com");
define('DB_USER', "avnadmin");
$_db_pass = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null;
if (!$_db_pass) { die('DB_PASSWORD environment variable is not set.'); }
define('DB_PASS', $_db_pass);

define('DB_NAME', "defaultdb");
define('DB_PORT', "22331");

