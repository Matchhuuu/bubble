<?php
// Database Importer / Setup endpoint for Vercel deployment
header('Content-Type: text/html; charset=utf-8');

$_cfg = __DIR__ . '/db_config.php';
if (file_exists($_cfg)) { require_once $_cfg; }

$sname    = defined('_DB_HOST') ? _DB_HOST : ($_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'mysql-35594af1-reapquizon-ff22.h.aivencloud.com');
$unmae    = defined('_DB_USER') ? _DB_USER : ($_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'avnadmin');
$password = defined('_DB_PASS') ? _DB_PASS : ($_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null);
$db_name  = defined('_DB_NAME') ? _DB_NAME : ($_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: 'defaultdb');
$port     = defined('_DB_PORT') ? _DB_PORT : ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: '22331');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - BubbleAid</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a1a; color: #f0f0f0; padding: 20px; }
        .card { max-width: 700px; margin: 30px auto; background: #2a2a2a; border-radius: 12px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
        h1 { color: #4CAF50; margin-top: 0; }
        .log { background: #111; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 13px; max-height: 350px; overflow-y: auto; line-height: 1.5; }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        .btn { display: inline-block; background: #337609; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 15px; }
        .btn:hover { background: #265906; }
    </style>
</head>
<body>
<div class="card">
    <h1>🛠️ BubbleAid Database Setup</h1>
<?php

if (!$password) {
    echo "<p class='error'>❌ <strong>Error:</strong> DB_PASSWORD environment variable is not configured.</p>";
    echo "<p>Please add <code>DB_PASSWORD</code> in your Vercel Environment Variables and redeploy.</p></div></body></html>";
    exit;
}

echo "<p>Connecting to MySQL: <code>$unmae@$sname:$port/$db_name</code>...</p>";

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
$ssl_flag = defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT') ? MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT : 64;

if (!mysqli_real_connect($conn, $sname, $unmae, $password, $db_name, (int)$port, NULL, $ssl_flag)) {
    echo "<p class='error'>❌ <strong>Connection Failed:</strong> " . htmlspecialchars(mysqli_connect_error()) . "</p></div></body></html>";
    exit;
}

echo "<p class='success'>✅ Connected successfully to MySQL!</p>";

// Ensure sessions table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `sessions` (
        `id`        VARCHAR(128) NOT NULL,
        `data`      MEDIUMTEXT   NOT NULL,
        `timestamp` INT(11)      NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$sqlFile = __DIR__ . '/bh.sql';
if (!file_exists($sqlFile)) {
    $sqlFile = dirname(__DIR__) . '/bh.sql';
}

if (!file_exists($sqlFile)) {
    echo "<p class='error'>❌ Could not find <code>bh.sql</code> file.</p></div></body></html>";
    exit;
}

echo "<p>Importing database dump from <code>bh.sql</code>...</p>";
echo "<div class='log'>";

$sql = file_get_contents($sqlFile);

// Remove SQL comments
$lines = explode("\n", $sql);
$cleanSql = '';
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (empty($trimmed) || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
        continue;
    }
    $cleanSql .= $line . "\n";
}

$statements = array_filter(
    array_map('trim', explode(';', $cleanSql)),
    fn($s) => !empty($s)
);

$okCount = 0;
$errCount = 0;
$createdTables = [];

foreach ($statements as $stmt) {
    if (empty($stmt)) continue;

    if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $stmt, $m)) {
        $tableName = $m[1];
        $createdTables[] = $tableName;
    }

    if ($conn->query($stmt) === false) {
        if ($conn->errno !== 1050 && $conn->errno !== 1062) {
            echo "<span class='error'>[Error " . $conn->errno . "] " . htmlspecialchars($conn->error) . "</span><br>";
            $errCount++;
        }
    } else {
        $okCount++;
    }
}

echo "</div>";

echo "<p class='success'>🎉 <strong>Import Complete!</strong> Executed $okCount statements successfully ($errCount warnings/errors).</p>";
if (!empty($createdTables)) {
    echo "<p><strong>Tables detected:</strong> " . implode(', ', array_unique($createdTables)) . "</p>";
}

echo "<a href='/interface/login.php' class='btn'>👉 Go to Login Page</a>";

$conn->close();
?>
</div>
</body>
</html>
