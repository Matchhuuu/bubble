<?php

$servername = "localhost"; 
$username = "root";        
$password = "";            
$db = "bh"; 

$conn = new mysqli($servername, $username, $password, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Delete Receipt Orders After File Creation
$sql1 = "DELETE FROM order_items";

$result1 = $conn->query($sql1);

header("Location: receipt_records.php");
exit();