<?php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';

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
// Orders (with price from order_items)
// ----------------------
$sqlOrders = "  SELECT
                    id,
                    order_id,
                    menu_item_id as menu_item,
                    size_id,
                    flavor,
                    quantity,
                    price,
                    (quantity * price) AS total_price,
                    created_at
                FROM customer_order_items
                $whereSql
                ORDER BY created_at DESC;
                ";
$resultOrders = $conn->query($sqlOrders);

// ----------------------
// Summary per Item
// ----------------------
$sqlSummary = " SELECT
                    menu_item_id as menu_item,
                    SUM(quantity) AS total_qty,
                    SUM(quantity * price) AS total_sale
                FROM customer_order_items $whereSql
                GROUP BY menu_item_id
                ORDER BY total_sale DESC;
";
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
                LIMIT 3;";
$resultTopMost = $conn->query($sqlTopMost);

// ----------------------
// Top 3 Least Ordered
// ----------------------
$sqlTopLeast = "SELECT
                    menu_item_id as menu_name,
                    SUM(quantity) AS qty,
                    SUM(quantity * price) AS total_sale
                FROM customer_order_items  $whereSql
                GROUP BY menu_item_id
                ORDER BY total_sale ASC
                LIMIT 3";
$resultTopLeast = $conn->query($sqlTopLeast);

// ----------------------
// PDF
// ----------------------
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);

// Header with Logo and Title
// Logo
$pdf->Image(__DIR__ . '/../media/BUBBLE.jpg', 10, 8, 30); // (x, y, width)

// Title (next to logo)
$pdf->SetFont('Arial', 'B', 30); // bigger font
$pdf->SetXY(50, 12);             // move right of logo
$pdf->Cell(0, 10, 'Bubble Hideout Sales Report', 0, 1, 'L');

// Add spacing before line
$pdf->Ln(20);

// Horizontal line
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());

// Add spacing after line
$pdf->Ln(8);


// ----------------------
// Table 1: All Orders
// ----------------------
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(20, 8, 'ORD_ID', 1);
$pdf->Cell(45, 8, 'MENU ITEM', 1);
$pdf->Cell(15, 8, 'SIZE', 1);
$pdf->Cell(15, 8, 'FLAVOR', 1);
$pdf->Cell(15, 8, 'QTY', 1);
$pdf->Cell(20, 8, 'PRICE', 1);
$pdf->Cell(25, 8, 'TOTAL', 1);
$pdf->Cell(35, 8, 'CREATED AT', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 9);
$grandTotal = 0;
while ($row = $resultOrders->fetch_assoc()) {
    $pdf->Cell(20, 8, $row['order_id'], 1);
    $pdf->Cell(45, 8, substr($row['menu_item'], 0, 20), 1);
    $pdf->Cell(15, 8, $row['size_id'], 1);
    $pdf->Cell(15, 8, $row['flavor'], 1);
    $pdf->Cell(15, 8, $row['quantity'], 1);
    $pdf->Cell(20, 8, number_format($row['price'], 2), 1);
    $pdf->Cell(25, 8, number_format($row['total_price'], 2), 1);
    $pdf->Cell(35, 8, $row['created_at'], 1);
    $pdf->Ln();

    $grandTotal += $row['total_price'];
}

// Total row
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(130, 8, 'TOTAL SALES', 1, 0, 'R');
$pdf->Cell(60, 8, number_format($grandTotal, 2), 1, 0, 'R');


$pdf->Ln(15);


// ----------------------
// Table 2: Summary per Item
// ----------------------
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Summary Per Item', 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(80, 8, 'Menu Item', 1);
$pdf->Cell(30, 8, 'Quantity', 1);
$pdf->Cell(40, 8, 'Total Sale', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);
while ($row = $resultSummary->fetch_assoc()) {
    $pdf->Cell(80, 8, substr($row['menu_item'], 0, 35), 1);
    $pdf->Cell(30, 8, $row['total_qty'], 1);
    $pdf->Cell(40, 8, number_format($row['total_sale'], 2), 1);
    $pdf->Ln();
}
$pdf->Ln(10);

// ----------------------
// Table 3: Rankings
// ----------------------
// --- Top 3 Section Title ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Top 3 Items', 0, 1, 'C');
$pdf->Ln(3);

// Save starting Y
$startY = $pdf->GetY();

// ================= Combined Most & Least Ordered =================
$pdf->SetXY(20, $startY);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(55, 8, 'Most Ordered', 1, 0, 'C');
$pdf->Cell(25, 8, 'Qty', 1, 0, 'C');
$pdf->Cell(55, 8, 'Least Ordered', 1, 0, 'C');
$pdf->Cell(25, 8, 'Qty', 1, 0, 'C');
$pdf->Ln();

$pdf->SetFont('Arial', '', 9);

// Fetch both result sets into arrays
$rowsMost = [];
while ($row = $resultTopMost->fetch_assoc()) {
    $rowsMost[] = $row;
}

$rowsLeast = [];
while ($row = $resultTopLeast->fetch_assoc()) {
    $rowsLeast[] = $row;
}

// Use the max row count between the two lists
$maxRows = max(count($rowsMost), count($rowsLeast), 3);

for ($i = 0; $i < $maxRows; $i++) {
    $pdf->SetX(20);

    // Most Ordered columns
    if (isset($rowsMost[$i])) {
        $pdf->Cell(55, 8, substr($rowsMost[$i]['menu_name'], 0, 22), 1, 0, 'L', false);
        $pdf->Cell(25, 8, $rowsMost[$i]['qty'], 1, 0, 'C', false);
    } else {
        $pdf->Cell(55, 8, '-', 1, 0, 'L');
        $pdf->Cell(25, 8, '-', 1, 0, 'C');
    }

    // Least Ordered columns
    if (isset($rowsLeast[$i])) {
        $pdf->Cell(55, 8, substr($rowsLeast[$i]['menu_name'], 0, 22), 1, 0, 'L', false);
        $pdf->Cell(25, 8, $rowsLeast[$i]['qty'], 1, 0, 'C', false);
    } else {
        $pdf->Cell(55, 8, '-', 1, 0, 'L');
        $pdf->Cell(25, 8, '-', 1, 0, 'C');
    }

    $pdf->Ln();
}


date_default_timezone_set("Asia/Manila");
$date = date('Y-m-d');
$pdf->Output('D', 'BubbleHideout_SaleReport_[' . $date . '].pdf');

$conn->close();
?>