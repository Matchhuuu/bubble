<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

echo json_encode(['success' => true]);
?>
