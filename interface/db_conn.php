<?php
$sname = "localhost";
$unmae = "root";
$password = "";
$db_name = "bh";

$conn = mysqli_connect($sname, $unmae, $password, $db_name);

if (!$conn) {
    die("❌ Connection Failed: " . mysqli_connect_error());
} else {
    echo "";
}
?>
