<?php
include "db_conn.php";

$months = [];
$days = [];

// Get available months
$sql_months = "SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as month FROM customer_order_items ORDER BY month DESC";
$result_months = $conn->query($sql_months);
while ($row = $result_months->fetch_assoc()) {
    $months[] = $row['month'];
}

// Get available days
$sql_days = "SELECT DISTINCT DATE(created_at) as day FROM customer_order_items ORDER BY day DESC";
$result_days = $conn->query($sql_days);
while ($row = $result_days->fetch_assoc()) {
    $days[] = $row['day'];
}

echo json_encode(["months" => $months, "days" => $days]);
?>

