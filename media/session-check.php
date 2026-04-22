<?php
session_start();

// Define 10 minutes timeout (in seconds)
define('INACTIVITY_TIMEOUT', 600);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check for inactivity
$current_time = time();

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = $current_time;
} else {
    $time_since_activity = $current_time - $_SESSION['last_activity'];
    
    // If 10 minutes of inactivity, logout
    if ($time_since_activity >= INACTIVITY_TIMEOUT) {
        session_destroy();
        header('Location: logout.php?timeout=1');
        exit();
    }
}

// Update last activity time
$_SESSION['last_activity'] = $current_time;
?>
