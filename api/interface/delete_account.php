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

    $sql1 = "   DELETE FROM acc_archive
                WHERE ID = $id";

    $result1 = $connection->query($sql1);
}   

header("location: /interface/archive_accounts.php");
exit;

?>

