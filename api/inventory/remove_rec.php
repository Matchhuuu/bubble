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
$sql1 = "DELETE FROM stock_history";

$result1 = $conn->query($sql1);

header("Location: add_stock_history.php");
exit();
