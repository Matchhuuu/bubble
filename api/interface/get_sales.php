<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';

include "db_conn.php"; $connection = $conn;

if(isset($_SESSION['ACC_ID'])  && isset($_SESSION['EMAIL'])){ 

    $sqlsum = "SELECT COALESCE(SUM(total), 0) as total FROM customer_orders
                WHERE status = 'Completed'";

    $result = $connection->query($sqlsum);
         
    $row = mysqli_fetch_array($result);

    $sum = (float)($row['total'] ?? 0);

    date_default_timezone_set("Asia/Manila");
    $date = date('Y-m-d');
    $id = intval($_SESSION['ACC_ID']);

    $sql1 = "   INSERT INTO sale_records (DATE_OF_SALE, TOTAL_SALE, LAST_TRANSACT)
                VALUES ('$date','$sum','$id')";

    $result1 = $connection->query($sql1);

    // Ensure order_archive schema can accept Completed status and all order fields
    $col_check = $connection->query("SHOW COLUMNS FROM order_archive LIKE 'status'");
    if ($col_check && $col = $col_check->fetch_assoc()) {
        if (stripos($col['Type'], 'enum') !== false && stripos($col['Type'], 'Completed') === false) {
            @$connection->query("ALTER TABLE order_archive MODIFY COLUMN status VARCHAR(50) DEFAULT 'Completed'");
            @$connection->query("ALTER TABLE order_archive MODIFY COLUMN order_type VARCHAR(50) NOT NULL DEFAULT 'dine_in'");
            @$connection->query("ALTER TABLE order_archive MODIFY COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            @$connection->query("ALTER TABLE order_archive MODIFY COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        }
    }

    $sql2 = "   INSERT INTO order_archive (order_id, order_type, total, discount, discount_type, amount_paid, status, created_at, updated_at)
                SELECT order_id, 
                       IF(order_type IN ('dine_in','takeout'), order_type, 'dine_in'), 
                       total, 
                       COALESCE(discount, 0.00), 
                       discount_type, 
                       COALESCE(amount_paid, 0.00), 
                       'Completed', 
                       created_at, 
                       updated_at
                FROM customer_orders
                WHERE status = 'Completed';";

    try {
        $result2 = $connection->query($sql2);
    } catch (mysqli_sql_exception $e) {
        // Fallback for older ENUM definition if ALTER TABLE didn't run
        $sql2_fallback = "INSERT INTO order_archive (order_id, order_type, total, discount, discount_type, amount_paid, status, created_at, updated_at)
                          SELECT order_id, 
                                 IF(order_type IN ('dine_in','takeout'), order_type, 'dine_in'), 
                                 total, 
                                 COALESCE(discount, 0.00), 
                                 discount_type, 
                                 COALESCE(amount_paid, 0.00), 
                                 'ready', 
                                 created_at, 
                                 updated_at
                          FROM customer_orders
                          WHERE status = 'Completed';";
        $result2 = $connection->query($sql2_fallback);
    }

    $sql3 = "   UPDATE customer_orders
                SET status = 'Done'
                WHERE status = 'Completed';";

    $result3 = $connection->query($sql3);

}

header("location: /interface/homepage.php");
exit;

