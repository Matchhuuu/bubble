<?php

include "db_conn.php";

$sql = "SELECT * FROM customer_order_items"; 
$result = $conn->query($sql);

$totalsale_disc = 0.0;
$total_sale_query = "SELECT total FROM customer_orders WHERE status = 'Completed'";
$total_sale = $conn->query($total_sale_query);

if ($total_sale) {
    while($row = mysqli_fetch_array($total_sale)){
        $totalsale_disc += (float)$row['total'];
    }
}

date_default_timezone_set("Asia/Manila");
$date = date('Y-m-d');

//Create File in system temp directory (writable on serverless & local)
$file = sys_get_temp_dir() . "/BH RECEIPT RECORDS FOR " . $date . ".txt";
$txt = fopen($file, "w") or die("Unable to open file!");

fwrite($txt, "BUBBLE HIDEOUT RECEIPT RECORDS FOR "); 
fwrite($txt, $date); 
fwrite($txt, " \n\n"); 

$headers = ["OrderID", "Items Ordered", "Size", "Qty", "Price", "Misc", "Date and Time"]; 

$rowFormat = "%-8s %-20s %-10s %-5s %-10s %-15s %-20s\n"; 
fwrite($txt, sprintf($rowFormat, ...$headers));

fwrite($txt, str_repeat("-", 103) . "\n"); 

$total_sale1 = 0.0;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $item_total = (float)$row['quantity'] * (float)$row['price'];
        fwrite($txt, sprintf($rowFormat, $row['order_id'], $row['menu_item_id'], $row['size_id'], $row['quantity'], $item_total, $row['flavor'], $row['created_at']));
        $total_sale1 += $item_total;
    }
} 
else {
    fwrite($txt, "No data found\n");
}
fwrite($txt, str_repeat("-", 100) . "\n\n"); 
fwrite($txt, "Total Sale: Php " . number_format((float)$total_sale1, 2) . "\n");  

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

