<?php
$sname = "mysql-20229225-binssente-18bc.h.aivencloud.com";
$unmae = "avnadmin";
$password = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD');

$db_name = "defaultdb";
$port = "13029";

$ca_cert = __DIR__ . '/../ca.pem';

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, $ca_cert, NULL, NULL);

if (!mysqli_real_connect($conn, $sname, $unmae, $password, $db_name, $port)) {
    die("❌ Connection Failed: " . mysqli_connect_error());
}
?>
