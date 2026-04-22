<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$servername = "localhost";
$username = "root";
$password = "";
$database = "bh";

$connection = new mysqli($servername, $username, $password, $database);

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

$connection->set_charset("utf8mb4");


$create_depleted_history_sql = "CREATE TABLE IF NOT EXISTS depleted_history (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    PROD_ID INT NOT NULL,
    PROD_NAME VARCHAR(255) NOT NULL,
    QTY_ORDERED INT NOT NULL COMMENT 'Quantity ordered in selected unit',
    UNIT_ORDERED VARCHAR(50) NOT NULL COMMENT 'The unit of the order',
    STOCK_PER_UNIT DECIMAL(10, 2) NOT NULL COMMENT 'How many base units per ordered unit',
    QTY_DEPLETED DECIMAL(10, 2) NOT NULL COMMENT 'Actual quantity deducted from base stock',
    BASE_UNIT VARCHAR(50) NOT NULL COMMENT 'The base unit of inventory',
    TOT_PRICE DECIMAL(15, 0) NOT NULL COMMENT 'Total cost of depletion',
    DATE_DEP DATE NOT NULL,
    TIME_DEP TIME NOT NULL,
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (PROD_ID) REFERENCES unified_inventory(item_id) ON DELETE CASCADE,
    INDEX idx_prod_id (PROD_ID),
    INDEX idx_date_dep (DATE_DEP),
    INDEX idx_created_at (CREATED_AT)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$connection->query($create_depleted_history_sql)) {
    error_log("Error creating depleted_history table: " . $connection->error);
}


function calculateActualDeduction($qty_ordered, $stock_per_unit) {
    $actual_deduction = $qty_ordered * $stock_per_unit;
    return intval(round($actual_deduction));
}


function validateStockAvailability($current_quantity, $qty_to_deduct) {
    $resulting_stock = $current_quantity - $qty_to_deduct;
    
    if ($resulting_stock < 0) {
        return [
            'valid' => false,
            'message' => "❌ INSUFFICIENT STOCK - Available: " . number_format($current_quantity, 0) .
                        ", Required: " . number_format($qty_to_deduct, 0) . 
                        ", Shortage: " . number_format(abs($resulting_stock), 0),
            'shortage' => abs($resulting_stock)
        ];
    }
    
    return [
        'valid' => true,
        'resulting_stock' => $resulting_stock,
        'message' => "✓ Stock validation passed"
    ];
}


function recordDepletion($connection, $prod_id, $prod_name, $qty_ordered, $unit_ordered, $stock_per_unit, $date_dep, $time_dep) {
    $connection->autocommit(false);
    
    try {
        // Step 1: Fetch current inventory details
        $inventory_query = "SELECT current_quantity, cost_per_unit, unit as base_unit FROM unified_inventory WHERE item_id = ?";
        $inventory_stmt = $connection->prepare($inventory_query);
        
        if (!$inventory_stmt) {
            throw new Exception("Database prepare error: " . $connection->error);
        }
        
        $inventory_stmt->bind_param("i", $prod_id);
        $inventory_stmt->execute();
        $inventory_result = $inventory_stmt->get_result();
        
        if ($inventory_result->num_rows === 0) {
            throw new Exception("❌ Product not found in inventory system");
        }
        
        $inventory_row = $inventory_result->fetch_assoc();
        $current_quantity = intval($inventory_row['current_quantity']);
        $cost_per_unit = intval($inventory_row['cost_per_unit']);
        $base_unit = $inventory_row['base_unit'];
        
        $inventory_stmt->close();
        
        
        $qty_depleted = calculateActualDeduction($qty_ordered, $stock_per_unit);
        
        $validation = validateStockAvailability($current_quantity, $qty_depleted);
        
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }
        
        $tot_price = $qty_depleted * $cost_per_unit;
        
        $update_query = "UPDATE unified_inventory SET current_quantity = current_quantity - ? WHERE item_id = ?";
        $update_stmt = $connection->prepare($update_query);
        
        if (!$update_stmt) {
            throw new Exception("Database prepare error: " . $connection->error);
        }
        
        $update_stmt->bind_param("ii", $qty_depleted, $prod_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("❌ Error updating inventory: " . $update_stmt->error);
        }
        
        $update_stmt->close();
        
        // Step 6: Record depletion in history
        $insert_query = "INSERT INTO depleted_history 
            (PROD_ID, PROD_NAME, QTY_ORDERED, UNIT_ORDERED, STOCK_PER_UNIT, QTY_DEPLETED, BASE_UNIT, TOT_PRICE, DATE_DEP, TIME_DEP) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insert_stmt = $connection->prepare($insert_query);
        
        if (!$insert_stmt) {
            throw new Exception("Database prepare error: " . $connection->error);
        }
        
        $insert_stmt->bind_param("isiiiiisss", 
            $prod_id, 
            $prod_name, 
            $qty_ordered,
            $unit_ordered,
            $stock_per_unit,
            $qty_depleted,
            $base_unit,
            $tot_price,
            $date_dep,
            $time_dep
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception("❌ Error recording depletion: " . $insert_stmt->error);
        }
        
        $insert_stmt->close();
        
        // Step 7: Commit transaction
        $connection->commit();
        $connection->autocommit(true);
        
        $success_msg = "✓ DEPLETION RECORDED SUCCESSFULLY\n";
        $success_msg .= "Product: " . htmlspecialchars($prod_name) . "\n";
        $success_msg .= "Ordered: " . number_format($qty_ordered) . " " . htmlspecialchars($unit_ordered) . "\n";
        $success_msg .= "Conversion: 1 " . htmlspecialchars($unit_ordered) . " = " . number_format($stock_per_unit, 0) . " " . htmlspecialchars($base_unit) . "\n";
        $success_msg .= "Actual Deduction: " . number_format($qty_depleted) . " " . htmlspecialchars($base_unit) . "\n";
        $success_msg .= "Total Cost: ₱" . number_format($tot_price, 0) . "\n";
        $success_msg .= "Remaining Stock: " . number_format($validation['resulting_stock'], 0) . " " . htmlspecialchars($base_unit);
        
        return [
            'success' => true,
            'message' => $success_msg,
            'qty_depleted' => $qty_depleted,
            'remaining_stock' => $validation['resulting_stock']
        ];
        
    } catch (Exception $e) {
        $connection->rollback();
        $connection->autocommit(true);
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}


$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'record_depletion') {
        $prod_id = intval($_POST['product_id'] ?? 0);
        $prod_name = trim($_POST['product_name'] ?? '');
        $qty_ordered = intval($_POST['quantity_ordered'] ?? 0);
        $unit_ordered = trim($_POST['unit_ordered'] ?? '');
        $stock_per_unit = intval($_POST['stock_per_unit'] ?? 0);
        $date_dep = $_POST['date'] ?? date('Y-m-d');
        $time_dep = $_POST['time'] ?? date('H:i:s');

        // Validate all required fields
        if ($prod_id > 0 && !empty($prod_name) && $qty_ordered > 0 && !empty($unit_ordered) && $stock_per_unit > 0) {
            $result = recordDepletion($connection, $prod_id, $prod_name, $qty_ordered, $unit_ordered, $stock_per_unit, $date_dep, $time_dep);
            
            if ($result['success']) {
                $_SESSION['success_message'] = $result['message'];
            } else {
                $_SESSION['error_message'] = $result['message'];
            }
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['error_message'] = "❌ Please fill in all required fields correctly";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Retrieve messages from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}


// Get search and filter parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';

// Build depletion history query
$sql = "SELECT 
    dh.ID,
    dh.PROD_ID,
    dh.PROD_NAME,
    dh.STOCK_PER_UNIT,
    dh.QTY_DEPLETED,
    dh.BASE_UNIT,
    dh.TOT_PRICE,
    dh.DATE_DEP,
    dh.TIME_DEP,
    dh.CREATED_AT,
    ui.current_quantity as current_stock
FROM depleted_history dh
LEFT JOIN unified_inventory ui ON dh.PROD_ID = ui.item_id
WHERE 1=1";

$params = [];
$types = "";

if (!empty($search_query)) {
    $sql .= " AND (dh.PROD_NAME LIKE ? OR dh.PROD_ID LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($filter_date)) {
    $sql .= " AND DATE(dh.DATE_DEP) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

$sql .= " ORDER BY dh.DATE_DEP DESC, dh.TIME_DEP DESC LIMIT 500";

$stmt = $connection->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $connection->query($sql);
}

// Get inventory items for dropdown
$inventory_query = "SELECT item_id, item_name, current_quantity, unit, cost_per_unit FROM unified_inventory WHERE current_quantity > 0 ORDER BY item_name";
$inventory_result = $connection->query($inventory_query);
$inventory_items = [];
while ($row = $inventory_result->fetch_assoc()) {
    $inventory_items[] = $row;
}

// Get depletion statistics
$stats_query = "SELECT 
    COUNT(*) as total_depletion_records,
    SUM(QTY_DEPLETED) as total_qty_depleted,
    SUM(TOT_PRICE) as total_value_depleted,
    MAX(DATE_DEP) as last_depletion_date
FROM depleted_history";
$stats_result = $connection->query($stats_query);
$stats = $stats_result->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Depletion System - Unit-Based Automatic Deduction</title>
    <link rel="stylesheet" href="/bubble/fonts/fonts.css">
    <link rel="icon" href="/bubble/media/BUBBLE.jpg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Poppins;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            min-height: 100vh;
        }

        .header {
            background: #644729;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-btn {
            background-color: #2E5714;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .back-btn:hover {
            background-color: #2E5714;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .admin-link {
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            transition: color 0.3s;
        }

        .admin-link:hover {
            color: #e0e0e0;
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-title {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .page-subtitle {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 30px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #4CAF50;
        }

        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }

        .controls-section {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            align-items: center;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            padding: 12px 20px;
            border: 2px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 8px rgba(76, 175, 80, 0.3);
        }

        .filter-select {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            background-color: white;
            transition: all 0.3s;
        }

        .filter-select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 8px rgba(76, 175, 80, 0.3);
        }

        .btn-record {
            background-color: #2F5D12;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .btn-record:hover {
            background-color: #2E5714;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .table-container {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
            transition: background-color 0.2s;
        }

        .product-id {
            font-weight: bold;
            color: #495057;
        }

        .product-name {
            font-weight: 500;
            color: #333;
        }

        .quantity-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }

        .conversion-info {
            background-color: #fff3cd;
            color: #856404;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }

        .cost-value {
            color: #d32f2f;
            font-weight: 600;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 550px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-row-three {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-submit {
            background-color: #4CAF50;
            color: white;
        }

        .btn-submit:hover {
            background-color: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-cancel {
            background-color: #f44336;
            color: white;
        }

        .btn-cancel:hover {
            background-color: #da190b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .close-modal {
            cursor: pointer;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            float: right;
            line-height: 20px;
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #000;
        }

        .available-stock {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 4px;
        }

        .calculation-display {
            background-color: #f0f8ff;
            border: 1px solid #b3d9ff;
            padding: 12px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
            color: #0066cc;
            font-family: 'Poppins', monospace;
            line-height: 1.6;
        }

        .calculation-display strong {
            color: #003d99;
        }

        .calculation-display.warning {
            background-color: #ffebee;
            color: #c62828;
            border-color: #ef5350;
        }

        .calculation-display.warning strong {
            color: #d32f2f;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 32px;
            }

            .controls-section {
                flex-direction: column;
            }

            .search-box {
                min-width: 100%;
            }

            .form-row,
            .form-row-three {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <button class="back-btn" onclick="history.back()"> Back</button>
        </div>

    </div>

    <div class="container">
        <h1 class="page-title">Stock Depletion History</h1>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Depletion Records</div>
                <div class="stat-value"><?php echo number_format($stats['total_depletion_records'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Base Units Depleted</div>
                <div class="stat-value"><?php echo number_format($stats['total_qty_depleted'] ?? 0, 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Value Depleted</div>
                <div class="stat-value">₱<?php echo number_format($stats['total_value_depleted'] ?? 0, 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Last Depletion Date</div>
                <div class="stat-value"><?php echo $stats['last_depletion_date'] ? date('M d, Y', strtotime($stats['last_depletion_date'])) : 'N/A'; ?></div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Search and Filter Controls -->
        <div class="controls-section">
            <form method="GET" style="display: flex; gap: 15px; flex: 1; flex-wrap: wrap; align-items: center; width: 100%;">
                <input type="text" name="search" class="search-box" placeholder="Search by Product Name or ID" 
                    value="<?php echo htmlspecialchars($search_query); ?>">
                
                <input type="date" name="filter_date" class="filter-select" 
                    value="<?php echo htmlspecialchars($filter_date); ?>">
                
                <button type="submit" class="btn-record">Filter Results</button>
            </form>

        </div>

        <!-- Depletion History Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Actual Deduction</th>
                        <th>Total Cost</th>
                        <th>Date & Time</th>
                        <th>Current Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "
                            <tr>
                                <td class='product-id'>" . htmlspecialchars($row['ID']) . "</td>
                                <td class='product-name'>" . htmlspecialchars($row['PROD_NAME']) . "</td>
                                <td><strong>" . number_format($row['QTY_DEPLETED']) .  "</strong></td>
                                <td><span class='cost-value'>₱" . number_format($row['TOT_PRICE']) . "</span></td>
                                <td>" . htmlspecialchars($row['DATE_DEP']) . " " . htmlspecialchars($row['TIME_DEP']) . "</td>
                                <td><strong>" . htmlspecialchars($row['current_stock']) .  "</strong></td>
                            </tr>
                            ";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='no-results'>No depletion records found. Start by recording your first depletion!</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>



    <script>
        function openRecordModal() {
            document.getElementById('recordModal').classList.add('active');
        }

        function closeRecordModal() {
            document.getElementById('recordModal').classList.remove('active');
            document.querySelector('select[name="product_id"]').value = '';
            document.getElementById('product_name').value = '';
            document.querySelector('input[name="quantity_ordered"]').value = '';
            document.querySelector('input[name="unit_ordered"]').value = '';
            document.querySelector('input[name="stock_per_unit"]').value = '';
            document.getElementById('availableStock').textContent = '';
            document.getElementById('deductionCalculation').style.display = 'none';
        }

        function updateProductDetails() {
            const select = document.querySelector('select[name="product_id"]');
            const selectedOption = select.options[select.selectedIndex];
            const prodName = selectedOption.getAttribute('data-name');
            const quantity = selectedOption.getAttribute('data-quantity');
            const unit = selectedOption.getAttribute('data-unit');
            
            document.getElementById('product_name').value = prodName;
            document.querySelector('input[name="unit_ordered"]').value = unit;
            document.getElementById('availableStock').textContent = 'Current Stock: ' + parseInt(quantity).toLocaleString('en-US') + ' ' + unit;
            calculateDeduction();
        }

        function calculateDeduction() {
            const select = document.querySelector('select[name="product_id"]');
            const selectedOption = select.options[select.selectedIndex];
            const cost = parseInt(selectedOption.getAttribute('data-cost')) || 0;
            const currentStock = parseInt(selectedOption.getAttribute('data-quantity')) || 0;
            const baseUnit = selectedOption.getAttribute('data-unit');
            
            const qtyOrdered = parseInt(document.querySelector('input[name="quantity_ordered"]').value) || 0;
            const stockPerUnit = parseInt(document.querySelector('input[name="stock_per_unit"]').value) || 0;
            
            if (qtyOrdered > 0 && stockPerUnit > 0) {
                const actualDeduction = qtyOrdered * stockPerUnit;
                const estimatedCost = actualDeduction * cost;
                const resultingStock = currentStock - actualDeduction;
                
                document.getElementById('calcFormula').textContent = qtyOrdered + ' × ' + stockPerUnit + ' = ' + actualDeduction;
                document.getElementById('actualDeduction').textContent = actualDeduction + ' ' + baseUnit;
                document.getElementById('estimatedCost').textContent = '₱' + estimatedCost.toLocaleString('en-US');
                document.getElementById('remainingStock').textContent = resultingStock + ' ' + baseUnit;
                
                const calcDiv = document.getElementById('deductionCalculation');
                
                if (resultingStock < 0) {
                    calcDiv.classList.add('warning');
                    calcDiv.innerHTML = '<strong>WARNING: INSUFFICIENT STOCK!</strong><br>' +
                        '<span id="calcFormula">' + qtyOrdered + ' × ' + stockPerUnit + ' = ' + actualDeduction + '</span><br><br>' +
                        '<strong>Actual Deduction:</strong> <span id="actualDeduction">' + actualDeduction + ' ' + baseUnit + '</span><br>' +
                        '<strong>Estimated Cost:</strong> <span id="estimatedCost">₱' + estimatedCost.toLocaleString('en-US') + '</span><br>' +
                        '<strong>Current Stock:</strong> ' + currentStock + ' ' + baseUnit + '<br>' +
                        '<strong style="color: #d32f2f;">SHORTAGE:</strong> ' + Math.abs(resultingStock) + ' ' + baseUnit;
                } else {
                    calcDiv.classList.remove('warning');
                    document.getElementById('calcFormula').textContent = qtyOrdered + ' × ' + stockPerUnit + ' = ' + actualDeduction;
                    document.getElementById('actualDeduction').textContent = actualDeduction + ' ' + baseUnit;
                    document.getElementById('estimatedCost').textContent = '₱' + estimatedCost.toLocaleString('en-US');
                    document.getElementById('remainingStock').textContent = resultingStock + ' ' + baseUnit;
                }
                
                calcDiv.style.display = 'block';
            } else {
                document.getElementById('deductionCalculation').style.display = 'none';
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('recordModal');
            if (event.target === modal) {
                modal.classList.remove('active');
            }
        }
    </script>
</body>
</html>