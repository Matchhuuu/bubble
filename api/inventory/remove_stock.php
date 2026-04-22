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

    $sql = "   DELETE FROM unified_inventory
                WHERE ITEM_ID = $id";

    $result = $connection->query($sql);
}   

header("location: /bubble/inventory/inventory_list.php");
exit;

?>