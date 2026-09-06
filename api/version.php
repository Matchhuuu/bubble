<?php
header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: 2.1.0');
header('X-System-Version: 2.1.0');

echo json_encode([
    'system' => 'BubbleAid POS & Inventory Management System',
    'system_version' => '2.1.0',
    'api_version' => '2.1.0',
    'php_version' => PHP_VERSION,
    'status' => 'operational',
    'timestamp' => date('c')
], JSON_PRETTY_PRINT);
?>
