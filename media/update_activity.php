<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

echo json_encode(['success' => true]);
?>
