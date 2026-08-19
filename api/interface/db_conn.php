<?php
$sname = "mysql-35594af1-reapquizon-ff22.h.aivencloud.com";
$unmae = "avnadmin";
$password = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD');

$db_name = "defaultdb";
$port = "22331";

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

$ssl_flag = defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT') ? MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT : 64;

if (!mysqli_real_connect($conn, $sname, $unmae, $password, $db_name, (int)$port, NULL, $ssl_flag)) {
    die("❌ Connection Failed: " . mysqli_connect_error());
}

$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

?>