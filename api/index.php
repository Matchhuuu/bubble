<?php
define('SYSTEM_VERSION', '2.1.0');
define('API_VERSION', '2.1.0');

// Expose API version headers
header('X-API-Version: ' . API_VERSION);
header('X-System-Version: ' . SYSTEM_VERSION);

$request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Normalize API version endpoint
if ($request_uri === '/version' || $request_uri === '/api/version' || $request_uri === '/version.php' || $request_uri === '/api/version.php') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'system' => 'BubbleAid POS & Inventory Management System',
        'system_version' => SYSTEM_VERSION,
        'api_version' => API_VERSION,
        'php_version' => PHP_VERSION,
        'status' => 'operational',
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
    exit;
}

// Normalize /api/ prefix if present
if (strncmp($request_uri, '/api/', 5) === 0) {
    $request_uri = substr($request_uri, 4);
}

// Helper to resolve PHP file target path
function resolve_target_file($file_path) {
    if (is_file($file_path)) {
        return $file_path;
    }
    if (is_file($file_path . '.php')) {
        return $file_path . '.php';
    }
    return null;
}

$target_file = null;
if (strncmp($request_uri, '/interface/ordersystem', 22) === 0) {
    $sub = substr($request_uri, 22);
    $target_file = resolve_target_file(__DIR__ . '/pointofsale/ordersystem' . $sub);
} elseif (strncmp($request_uri, '/interface/', 11) === 0) {
    $sub = substr($request_uri, 11);
    $target_file = resolve_target_file(__DIR__ . '/interface/' . $sub);
} elseif (strncmp($request_uri, '/inventory/', 11) === 0) {
    $sub = substr($request_uri, 11);
    $target_file = resolve_target_file(__DIR__ . '/inventory/' . $sub);
} elseif (strncmp($request_uri, '/pointofsale/', 13) === 0) {
    $sub = substr($request_uri, 13);
    $target_file = resolve_target_file(__DIR__ . '/pointofsale/' . $sub);
} elseif ($request_uri !== '/' && $request_uri !== '/index.php' && $request_uri !== '/api/index.php') {
    $sub = ltrim($request_uri, '/');
    $target_file = resolve_target_file(__DIR__ . '/' . $sub);
    if (!$target_file) {
        $target_file = resolve_target_file(__DIR__ . '/interface/' . $sub)
                    ?? resolve_target_file(__DIR__ . '/pointofsale/' . $sub)
                    ?? resolve_target_file(__DIR__ . '/inventory/' . $sub);
    }
}

// Execute target file in global scope
if ($target_file) {
    chdir(dirname($target_file));
    require $target_file;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bubble Hideout Login</title>

    <!-- ✅ Correct favicon path -->
    <link rel="icon" href="/media/BUBBLE.jpg">

    <!-- ✅ Correct font path -->
    <link rel="stylesheet" href="/fonts/fonts.css">

    <style>
        .box {
            position: relative;
            padding: 10px;
            border-radius: 15px;
            margin: auto;
            width: 25%;
        }

        label {
            color: black;
            position: relative;
            text-indent: 40%;
            font-weight: bold;
            font-size: 15px;
        }

        .input {
            border-color: transparent;
            border-radius: 25px;
            background-color: #337609;
            margin-bottom: 10px;
            color: #f0f0f0;
            height: 30px;
            width: 100%;
            text-indent: 10px;
            outline: none;
            box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
        }

        ::placeholder {
            color: #f0f0f0;
        }

        .btn {
            margin-top: 40px;
            background: #2c2c2c;
            width: 100px;
            height: 40px;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
            cursor: pointer;
        }

        .btn:hover {
            background: #181818;
        }

        .img-bbl img {
            width: 300px;
            height: 300px;
        }

        .footer {
            color: #181818;
            text-align: center;
            font-size: 12px;
            width: 100%;
            height: 7%;
            position: absolute;
            bottom: 0;
            left: 0;
            z-index: 10;
        }

        .check {
            width: 17px;
            height: 17px;
            accent-color: #337609;
        }

        .toggle {
            display: flex;
            justify-content: right;
        }

        .aa {
            position: relative;
            display: flex;
            justify-content: center;
        }

        .bb {
            position: relative;
            height: 10px;
        }
    </style>
</head>

<body>

    <center>
        <div class="img-bbl">
            <!-- ✅ Fixed image path -->
            <img src="/media/BUBBLE.jpg" alt="Bubble Logo">
        </div>
    </center>

    <br>

    <div class="box">
        <center><h1>WELCOME TO BUBBLEAID</h1></center>
        <center><p>Smart Solutions for your Food Business</p></center>

        <!-- ✅ Correct form action -->
        <center>
            <form action="/interface/login.php" method="get">
                <button type="submit" class="btn">ENTER</button>
            </form>
            <p style="font-size: 11px; color: #888; margin-top: 15px;">API Version <?php echo API_VERSION; ?></p>
        </center>
    </div>


    <script>
        function Toggle() {
            var x = document.getElementById("pass_input");
            if (x.type === "password") {
                x.type = "text";
            } else {
                x.type = "password";
            }
        }
    </script>

</body>
</html>
