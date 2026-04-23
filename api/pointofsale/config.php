<?php
define('DB_HOST', "mysql-20229225-binssente-18bc.h.aivencloud.com");
define('DB_USER', "avnadmin");
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD'));

define('DB_NAME', "defaultdb");
define('DB_PORT', "13029");

