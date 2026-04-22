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
        header("location: /interface/admin_homepage.php");
        exit;
    }

    $id = $_GET["id"];

    $sql1 = "   INSERT INTO acc_archive (ACC_ID,FNAME,LNAME, ROLE, EMAIL,ORIG_PASS, PASS,CNTC_NUM,BDAY)
                SELECT ACC_ID,FNAME,LNAME, ROLE, EMAIL,ORIG_PASS, PASS,CNTC_NUM,BDAY
                FROM accounts
                WHERE ACC_ID = $id";

    $result1 = $connection->query($sql1);

    $sql = "   DELETE FROM accounts
                WHERE ACC_ID = $id";

    $result = $connection->query($sql);
}   

header("location: /bubble/interface/user_accounts.php");
exit;

?>