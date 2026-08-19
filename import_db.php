<?php
// Script to import bh.sql into Aiven MySQL database
// Run: php import_db.php

$host     = 'mysql-35594af1-reapquizon-ff22.h.aivencloud.com';
$port     = 22331;
$user     = 'avnadmin';
$password = getenv('DB_PASSWORD') ?: readline("Enter DB_PASSWORD: ");
$dbname   = 'defaultdb';
$sqlFile  = __DIR__ . '/bh.sql';

if (!file_exists($sqlFile)) {
    die("❌ bh.sql not found at: $sqlFile\n");
}

echo "🔌 Connecting to Aiven MySQL...\n";

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
$ssl_flag = defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT') ? MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT : 64;

if (!mysqli_real_connect($conn, $host, $user, $password, $dbname, $port, NULL, $ssl_flag)) {
    die("❌ Connection failed: " . mysqli_connect_error() . "\n");
}
echo "✅ Connected!\n";

// Also create sessions table
$conn->query("
    CREATE TABLE IF NOT EXISTS `sessions` (
        `id`        VARCHAR(128) NOT NULL,
        `data`      MEDIUMTEXT   NOT NULL,
        `timestamp` INT(11)      NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "📂 Reading $sqlFile...\n";
$sql = file_get_contents($sqlFile);

// Split into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => !empty($s) && $s !== "\n"
);

$total = count($statements);
echo "📋 Found $total SQL statements. Importing...\n";

$ok = 0;
$errors = 0;
foreach ($statements as $i => $stmt) {
    if (empty(trim($stmt))) continue;
    if ($conn->query($stmt) === false) {
        // Skip duplicate/already-exists errors silently
        if ($conn->errno !== 1050 && $conn->errno !== 1062) {
            echo "  ⚠️  Error on statement " . ($i+1) . ": " . $conn->error . "\n";
            $errors++;
        }
    } else {
        $ok++;
    }
}

echo "\n✅ Done! $ok statements executed, $errors errors.\n";
$conn->close();
