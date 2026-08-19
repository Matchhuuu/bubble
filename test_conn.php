<?php
// Quick connection test - run: php test_conn.php
echo "Enter your Aiven password: ";
$pass = trim(fgets(STDIN));
echo "Testing connection...\n";

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

if (!mysqli_real_connect($conn, 'mysql-35594af1-reapquizon-ff22.h.aivencloud.com', 'avnadmin', $pass, 'defaultdb', 22331, NULL, 64)) {
    die("FAIL: " . mysqli_connect_error() . "\n");
}
echo "SUCCESS! Password works.\n";
echo "Now paste this exact password into Vercel DB_PASSWORD.\n";
$conn->close();
