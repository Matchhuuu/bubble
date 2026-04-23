<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';
include "db_conn.php";

$response = ['total' => 0, 'total1' => 0];

// Calculate $total
$sql_returned_goods = "SELECT CURRENT_QUANTITY, is_liquid FROM unified_inventory";
$result_returned_goods = $conn->query($sql_returned_goods);

if ($result_returned_goods) {
    while ($row = $result_returned_goods->fetch_assoc()) {
        if ($row['CURRENT_QUANTITY'] <= 50)  {
            $response['total'] += 1;
        }
        if (($row['CURRENT_QUANTITY'] <= 800) && ($row['is_liquid'] == 1)) {
            $response['total'] += 1;
        }
    }
}

// Calculate $total1
$notif = "SELECT CURRENT_QUANTITY FROM unified_inventory";
$result = mysqli_query($conn, $notif);

if ($result) {
    while ($row = mysqli_fetch_array($result)) {
        $response['total1'] += $row['CURRENT_QUANTITY'];
    }
    // Round to 2 decimal places after summing
    $response['total1'] = round($response['total1'], 2);
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
