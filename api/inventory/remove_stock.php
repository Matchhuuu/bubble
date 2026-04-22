<?php
include "db_conn.php"; $connection = $conn;


$id = "";

if ($_SERVER['REQUEST_METHOD'] =='GET'){
    //GET

    if ( !isset($_GET['id'])) {
        header("location: /interface/admin_homepage.php");
        exit;
    }

    $id = $_GET["id"];

    $sql = "   DELETE FROM unified_inventory
                WHERE ITEM_ID = $id";

    $result = $connection->query($sql);
}   

header("location: /inventory/inventory_list.php");
exit;

?>

