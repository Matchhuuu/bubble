<?php
define('DB_HOST', "mysql-35594af1-reapquizon-ff22.h.aivencloud.com");
define('DB_USER', "avnadmin");
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD'));

define('DB_NAME', "defaultdb");
define('DB_PORT', "22331");

