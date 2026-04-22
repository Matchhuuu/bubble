<?php

$servername = "localhost"; 
$username = "root";        
$password = "";            
$db = "bh"; 

$conn = new mysqli($servername, $username, $password, $db);

global $total_sale1;
global $totalsale_disc;

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM customer_order_items"; 
$result = $conn->query($sql);

$total_sale = "SELECT total FROM customer_orders
               WHERE status = 'Completed'";
$total_sale = $conn->query($total_sale);

while($row = mysqli_fetch_array($total_sale)){
    $totalsale_disc += $row['total'];
}

date_default_timezone_set("Asia/Manila");
$date = date('Y-m-d');

//Create File
$file = dirname(__DIR__) . "/BH RECEIPT RECORDS FOR " . $date . ".txt";
$txt = fopen($file, "w") or die("Unable to open file!");

fwrite($txt, "BUBBLE HIDEOUT RECEIPT RECORDS FOR "); 
fwrite($txt, $date); 
fwrite($txt, " \n\n"); 

$headers = ["OrderID", "Items Ordered", "Size", "Qty", "Price", "Misc", "Date and Time"]; 

$rowFormat = "%-8s %-20s %-10s %-5s %-10s %-15s %-20s\n"; 
fwrite($txt, sprintf($rowFormat, ...$headers));

fwrite($txt, str_repeat("-", 103) . "\n"); 

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fwrite($txt, sprintf($rowFormat, $row['order_id'], $row['menu_item_id'], $row['size_id'], $row['quantity'], $row['quantity']*$row['price'], $row['flavor'], $row['created_at']));
        $total_sale1 += $row['quantity'] * $row['price'];
    }
} 
else {
    fwrite($txt, "No data found\n");
}
fwrite($txt, str_repeat("-", 100) . "\n\n"); 
fwrite($txt, "Total Sale: Php " . $total_sale1 . "\n");  

$conn->close();
fclose($txt);

header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename=' . basename($file));
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file));
header("Content-Type: text/plain");
readfile($file);

?>
