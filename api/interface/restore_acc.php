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

    $sql1 = "   INSERT INTO accounts (FNAME,LNAME, ROLE, EMAIL, ORIG_PASS, PASS,CNTC_NUM,BDAY)
                SELECT FNAME,LNAME, ROLE, EMAIL,ORIG_PASS, PASS,CNTC_NUM,BDAY
                FROM acc_archive
                WHERE ACC_ID = $id";

    $result1 = $connection->query($sql1);

    $sql = "   DELETE FROM acc_archive
                WHERE ACC_ID = $id";

    $result = $connection->query($sql);
}   

header("location: /interface/user_accounts.php");
exit;

?>

