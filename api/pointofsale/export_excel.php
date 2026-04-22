<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// DB connection
$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "bh";
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$month = $_POST['month'] ?? '';
$day   = $_POST['day'] ?? '';

$where = [];
if (!empty($month)) $where[] = "DATE_FORMAT(created_at, '%Y-%m') = '" . $conn->real_escape_string($month) . "'";
if (!empty($day))   $where[] = "DATE(created_at) = '" . $conn->real_escape_string($day) . "'";
$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";


// ----------------------
// Orders
// ----------------------
$sqlOrders = "SELECT
                    id,
                    order_id,
                    menu_item_id as menu_item,
                    size_id,
                    flavor,
                    quantity,
                    price,
                    (quantity * price) AS total_price,
                    created_at
                FROM customer_order_items $whereSql 
                ORDER BY created_at DESC";
$resultOrders = $conn->query($sqlOrders);

// ----------------------
// Summary per Item
// ----------------------
$sqlSummary = "SELECT
                    menu_item_id as menu_item,
                    SUM(quantity) AS total_qty,
                    SUM(quantity * price) AS total_sale
                FROM customer_order_items $whereSql 
                GROUP BY menu_item_id
                ORDER BY total_sale DESC    ";
$resultSummary = $conn->query($sqlSummary);

// ----------------------
// Top 3 Most Frequent
// ----------------------
$sqlTopMost = "SELECT
                    menu_item_id as menu_name,
                    SUM(quantity) AS qty,
                    SUM(quantity * price) AS total_sale
                FROM customer_order_items $whereSql 
                GROUP BY menu_item_id
                ORDER BY total_sale DESC
                LIMIT 3";
$resultTopMost = $conn->query($sqlTopMost);

// ----------------------
// Top 3 Least Ordered
// ----------------------
$sqlTopLeast = "SELECT
                    menu_item_id as menu_name,
                    SUM(quantity) AS qty,
                    SUM(quantity * price) AS total_sale
                FROM customer_order_items $whereSql 
                GROUP BY menu_item_id
                ORDER BY total_sale ASC
                LIMIT 3";
$resultTopLeast = $conn->query($sqlTopLeast);


// Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Sales Report");

// ----------------------
// Add Logo + Header Title
// ----------------------
$logo = new Drawing();
$logo->setName('Logo');
$logo->setDescription('Bubble Hideout Logo');
$logo->setPath(__DIR__ . '/../media/BUBBLE.jpg'); // 👈 path to your logo
$logo->setHeight(60);
$logo->setCoordinates('A1');
$logo->setWorksheet($sheet);

// Title on same row
$sheet->mergeCells("B1:H1");
$sheet->setCellValue("B1", "Bubble Hideout Sales Report");
$sheet->getStyle("B1")->getFont()->setBold(true)->setSize(16);
$sheet->getStyle("B1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                                    ->setVertical(Alignment::VERTICAL_CENTER);

// Add a bit of row height so the logo and title look aligned
$sheet->getRowDimension(1)->setRowHeight(50);

// Move starting row down
$row = 3;


// ----------------------
// Table: Orders
// ----------------------
$headers = ["ID","Order ID","Menu Item","Size","Flavor","Quantity","Price","Total","Created At"];
$col = "A";
foreach ($headers as $header) {
    $sheet->setCellValue("$col$row", $header);
    $sheet->getStyle("$col$row")->getFont()->setBold(true);
    $col++;
}
$row++;

$grandTotal = 0;
$ordersStart = $row; // for border start
while ($order = $resultOrders->fetch_assoc()) {
    $sheet->setCellValue("A$row", $order['id']);
    $sheet->setCellValue("B$row", $order['order_id']);
    $sheet->setCellValue("C$row", $order['menu_item']);
    $sheet->setCellValue("D$row", $order['size_id']);
    $sheet->setCellValue("E$row", $order['size_id']);
    $sheet->setCellValue("F$row", $order['quantity']);
    $sheet->setCellValue("G$row", $order['price']);
    $sheet->setCellValue("H$row", $order['total_price']);
    $sheet->setCellValue("I$row", $order['created_at']);
    $grandTotal += $order['total_price'];
    $row++;
}
$sheet->setCellValue("G$row", "TOTAL SALES");
$sheet->setCellValue("H$row", $grandTotal);
$sheet->getStyle("G$row:H$row")->getFont()->setBold(true);
$ordersEnd = $row; // for border end
$row += 2;

// Outline border for Orders table
$sheet->getStyle("A" . ($ordersStart - 1) . ":I$ordersEnd")->applyFromArray([
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN]]
]);

// ----------------------
// Summary
// ----------------------
$sheet->setCellValue("A$row", "Summary Per Item");
$sheet->getStyle("A$row")->getFont()->setBold(true);
$row++;

$sheet->setCellValue("A$row", "Menu Item");
$sheet->setCellValue("B$row", "Total Qty");
$sheet->setCellValue("C$row", "Total Sale");
$sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
$row++;

$summaryStart = $row;
while ($sum = $resultSummary->fetch_assoc()) {
    $sheet->setCellValue("A$row", $sum['menu_item']);
    $sheet->setCellValue("B$row", $sum['total_qty']);
    $sheet->setCellValue("C$row", $sum['total_sale']);
    $row++;
}
$summaryEnd = $row - 1;
$row += 2;

// Outline border for Summary
$sheet->getStyle("A" . ($summaryStart - 1) . ":C$summaryEnd")->applyFromArray([
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN]]
]);

// ----------------------
// Top 3 Most/Least
// ----------------------
$sheet->setCellValue("A$row", "Top 3 Most Ordered");
$sheet->getStyle("A$row")->getFont()->setBold(true);
$sheet->setCellValue("E$row", "Top 3 Least Ordered");
$sheet->getStyle("E$row")->getFont()->setBold(true);
$row++;

$startRowMost = $row;
while ($top = $resultTopMost->fetch_assoc()) {
    $sheet->setCellValue("A$row", $top['menu_name']);
    $sheet->setCellValue("B$row", $top['qty']);
    $row++;
}
$endRowMost = $row - 1;

$row = $startRowMost;
while ($least = $resultTopLeast->fetch_assoc()) {
    $sheet->setCellValue("E$row", $least['menu_name']);
    $sheet->setCellValue("F$row", $least['qty']);
    $row++;
}
$endRowLeast = $row - 1;

// Outline borders
$sheet->getStyle("A" . ($startRowMost - 1) . ":B$endRowMost")->applyFromArray([
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN]]
]);

$sheet->getStyle("E" . ($startRowMost - 1) . ":F$endRowLeast")->applyFromArray([
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN]]
]);

// Auto-size
foreach (range('A','H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ----------------------
// Output
// ----------------------
date_default_timezone_set("Asia/Manila");
$date = date('Y-m-d');

ob_clean();
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment;filename=BubbleHideout_SalesReport_[" . $date . "].xlsx");
header("Cache-Control: max-age=0");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");

$conn->close();
exit;