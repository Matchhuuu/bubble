
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

$sql = "SELECT * FROM stock_history"; 
$result = $conn->query($sql);

date_default_timezone_set("Asia/Manila");
$date = date('Y-m-d');

//Create File
$file = dirname(__DIR__) . "/BH STOCK HISTORY FOR " . $date . ".txt";
$txt = fopen($file, "w") or die("Unable to open file!");

fwrite($txt, "BUBBLE HIDEOUT STOCK HISTORY FOR "); 
fwrite($txt, $date); 
fwrite($txt, " \n\n"); 

$headers = ["Prod ID", "Stock Ordered", "Quantity", "Total Price", "Date Added", "Time Added"]; 

$rowFormat = "%-12s %-17s %-10s %-15s %-15s %-20s\n"; 
fwrite($txt, sprintf($rowFormat, ...$headers));

fwrite($txt, str_repeat("-", 100) . "\n"); 

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fwrite($txt, sprintf($rowFormat, $row['PROD_ID'], $row['PROD_NAME'], $row['QTY_STCK'], $row['TOT_PRICE'], $row['DATE_ADD'], $row['TIME_ADD']));
        $total_sale1 += $row['TOT_PRICE'];
    }
} 
else {
    fwrite($txt, "No data found\n");
}
fwrite($txt, str_repeat("-", 100) . "\n\n"); 
fwrite($txt, "Total: Php " . $total_sale1 . "\n");  

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
