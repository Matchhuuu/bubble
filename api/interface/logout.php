<?php
session_start();

$sname = "localhost";
$unmae = "root";
$password = "";
$db_name = "bh";

$conn = mysqli_connect($sname,$unmae,$password,$db_name);




if(isset($_SESSION['ACC_ID'])  && isset($_SESSION['EMAIL'])){
    $id = $_SESSION['ACC_ID'];
    date_default_timezone_set("Asia/Manila");
    $time = date('h:i A');

    $sql1 = "UPDATE login_history
            SET time_of_logout = '$time'
            WHERE ACC_ID = '$id'
            AND time_of_logout IS NULL;
";

    $result = mysqli_query($conn, $sql1);

    $sql2 = "UPDATE accounts
            SET `status` = 'Offline'
            WHERE ACC_ID = '$id'
            ;
";

    $result2 = mysqli_query($conn, $sql2);
}

session_unset();
session_destroy();

header("Location: login.php");