<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "bh";

// Create Connection
$connection = new mysqli($servername, $username, $password, $database);


$id = "";

if ($_SERVER['REQUEST_METHOD'] =='GET'){
    //GET

    if ( !isset($_GET['id'])) {
        header("location: /bubble/interface/admin_homepage.php");
        exit;
    }

    $id = $_GET["id"];


    $sql1 = "   UPDATE customer_orders
                SET status = 'Completed'
                WHERE order_id = '$id'";

    $result1 = $connection->query($sql1);
}   

header("location: /bubble/interface/homepage.php");
exit;

?>