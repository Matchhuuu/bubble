<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$database = "bh";


$connection = new mysqli($servername, $username, $password, $database);

if(isset($_SESSION['ACC_ID'])  && isset($_SESSION['EMAIL'])){ 

    $sqlsum = "SELECT SUM(total) as total FROM customer_orders
                WHERE status = 'Completed'";

    $result = $connection->query($sqlsum);
         
    $row= mysqli_fetch_array($result);

    $sum= $row['total'];


    
    date_default_timezone_set("Asia/Manila");
    $date = date('Y/m/d/');
    $id = $_SESSION['ACC_ID'];

    $sql1 = "   INSERT INTO sale_records (DATE_OF_SALE, TOTAL_SALE, LAST_TRANSACT)
                VALUES ('$date','$sum','$id')";

    $result1 = $connection->query($sql1);

    $sql2 = "   INSERT INTO order_archive (order_id, order_type, total, status, created_at, updated_at)
                SELECT order_id, order_type, total, status, created_at, updated_at
                FROM customer_orders
                WHERE status = 'Completed';";

    $result2 = $connection->query($sql2);

    $sql3 = "   UPDATE customer_orders
                SET status = 'Done'
                WHERE status = 'Completed';";

    $result3 = $connection->query($sql3);

}

header("location: /bubble/interface/homepage.php");
exit;