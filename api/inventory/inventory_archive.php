<?php
// Start session at the very beginning, before any output
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';

// Database connection
include "db_conn.php"; $connection = $conn;

// ============================================================================
// ============================================================================

/**
 * Check ingredient availability for a menu item
 * Equivalent to MySQL: CheckIngredientAvailability procedure
 */
function checkIngredientAvailability($connection, $menu_item_id, $size_id, $quantity) {
    $results = [];
    
    $query = "SELECT ums.ingredient_id, ums.quantity_required, ui.item_name, ui.current_quantity, ui.unit
              FROM unified_menu_system ums
              JOIN unified_inventory_archive ui ON ums.ingredient_id = ui.item_id
              WHERE ums.menu_item_id = ? 
              AND ums.size_id = ?
              AND ums.ingredient_id IS NOT NULL";
    
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        return ['error' => 'Prepare failed: ' . $connection->error];
    }

    $stmt->bind_param("ss", $menu_item_id, $size_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $total_needed = $row['quantity_required'] * $quantity;
        $status = ($row['current_quantity'] >= $total_needed) ? 'AVAILABLE' : 'INSUFFICIENT';
        
        $results[] = [
            'ingredient_name' => $row['item_name'],
            'required_quantity' => $total_needed,
            'current_stock' => $row['current_quantity'],
            'unit' => $row['unit'],
            'status' => $status
        ];
    }

    $stmt->close();
    return $results;
}

/**
 * Process order with inventory deduction
 * Equivalent to MySQL: ProcessOrderWithDeduction procedure
 */
function processOrderWithDeduction($connection, $menu_item_id, $size_id, $quantity, $order_id) {
    $connection->autocommit(false);

    try {
        // First, check if all ingredients are available
        $query = "SELECT ums.ingredient_id, ums.quantity_required, ui.item_name, ui.is_liquid, ui.unit, ui.current_quantity
                  FROM unified_menu_system ums
                  JOIN unified_inventory_archive ui ON ums.ingredient_id = ui.item_id
                  WHERE ums.menu_item_id = ? 
                  AND ums.size_id = ?
                  AND ums.ingredient_id IS NOT NULL";
        
        $stmt = $connection->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connection->error);
        }
        
        $stmt->bind_param("ss", $menu_item_id, $size_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $ingredients = [];
        while ($row = $result->fetch_assoc()) {
            $total_deduction = $row['quantity_required'] * $quantity;
            
            if ($row['current_quantity'] < $total_deduction) {
                throw new Exception("Insufficient stock for: " . $row['item_name'] . 
                    ". Need " . $total_deduction . " " . $row['unit'] . 
                    " but only have " . $row['current_quantity'] . " " . $row['unit']);
            }
            
            $ingredients[] = [
                'ingredient_id' => $row['ingredient_id'],
                'total_deduction' => $total_deduction
            ];
        }
        $stmt->close();
        
        // Deduct from inventory
        foreach ($ingredients as $ingredient) {
            $update_query = "UPDATE unified_inventory_archive
                           SET current_quantity = current_quantity - ? 
                           WHERE item_id = ?";
            
            $update_stmt = $connection->prepare($update_query);
            if (!$update_stmt) {
                throw new Exception("Prepare failed: " . $connection->error);
            }
            
            $update_stmt->bind_param("di", $ingredient['total_deduction'], $ingredient['ingredient_id']);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Error deducting inventory: " . $update_stmt->error);
            }
            $update_stmt->close();
        }
        
        $connection->commit();
        $connection->autocommit(true);
        
        return [
            'success' => true,
            'message' => "Order " . $order_id . " processed successfully for " . $quantity . " x " . $menu_item_id . " (" . $size_id . ")"
        ];
        
    } catch (Exception $e) {
        $connection->rollback();
        $connection->autocommit(true);
        return ['error' => $e->getMessage()];
    }
}

// ============================================================================
// END OF CONVERTED FUNCTIONS
// ============================================================================

$error_message = "";
$success_message = "";

// Check for session messages first (from redirects)
if (isset($_SESSION["success_message"])) {
    $success_message = $_SESSION["success_message"];
    unset($_SESSION["success_message"]);
}
if (isset($_SESSION["error_message"])) {
    $error_message = $_SESSION["error_message"];
    unset($_SESSION["error_message"]);
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Handle Connect Existing Stock to Menu
    if (isset($_POST["action"]) && $_POST["action"] === "connect_to_menu") {
        $item_id = intval($_POST["item_id"]);
        $item_name = trim($_POST["item_name"]);
        $is_liquid = intval($_POST["is_liquid"]);

        // Get selected menu items and quantities
        $selected_menu_items = isset($_POST["menu_items"]) ? $_POST["menu_items"] : [];
        $quantities = isset($_POST["quantities"]) ? $_POST["quantities"] : [];

        if ($item_id > 0 && !empty($selected_menu_items)) {
            $connection->autocommit(false);

            try {
                $connected_count = 0;
                
                foreach ($selected_menu_items as $menu_key) {
                    if (isset($quantities[$menu_key]) && floatval($quantities[$menu_key]) > 0) {
                        $menu_parts = explode('_', $menu_key);
                        if (count($menu_parts) >= 2) {
                            $menu_item_id = $menu_parts[0];
                            $size_id = $menu_parts[1];
                            $quantity_required = floatval($quantities[$menu_key]);
                            
                            // Get menu item details
                            $menu_details_stmt = $connection->prepare("SELECT menu_item_name, category, section, price FROM unified_menu_system WHERE menu_item_id = ? AND size_id = ? LIMIT 1");
                            if (!$menu_details_stmt) {
                                throw new Exception("Prepare failed for menu details: " . $connection->error);
                            }
                            
                            $menu_details_stmt->bind_param("ss", $menu_item_id, $size_id);
                            $menu_details_stmt->execute();
                            $menu_details_result = $menu_details_stmt->get_result();
                            $menu_details = $menu_details_result->fetch_assoc();
                            
                            if ($menu_details) {
                                // Check if connection already exists
                                $check_stmt = $connection->prepare("SELECT id FROM unified_menu_system WHERE menu_item_id = ? AND size_id = ? AND ingredient_id = ?");
                                $check_stmt->bind_param("ssi", $menu_item_id, $size_id, $item_id);
                                $check_stmt->execute();
                                $check_result = $check_stmt->get_result();
                                
                                if ($check_result->num_rows > 0) {
                                    // Update existing connection
                                    $update_stmt = $connection->prepare("UPDATE unified_menu_system SET quantity_required = ?, is_liquid_ingredient = ? WHERE menu_item_id = ? AND size_id = ? AND ingredient_id = ?");
                                    $update_stmt->bind_param("dissi", $quantity_required, $is_liquid, $menu_item_id, $size_id, $item_id);
                                    if ($update_stmt->execute()) {
                                        $connected_count++;
                                    }
                                    $update_stmt->close();
                                } else {
                                    // Insert new connection
                                    $menu_insert_stmt = $connection->prepare("INSERT INTO unified_menu_system (menu_item_id, menu_item_name, category, section, size_id, price, ingredient_id, quantity_required, is_liquid_ingredient, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                                    if (!$menu_insert_stmt) {
                                        throw new Exception("Prepare failed for menu insert: " . $connection->error);
                                    }
                                    
                                    $menu_insert_stmt->bind_param("sssssiddi", 
                                        $menu_item_id, 
                                        $menu_details['menu_item_name'],
                                        $menu_details['category'],
                                        $menu_details['section'],
                                        $size_id,
                                        $menu_details['price'],
                                        $item_id, 
                                        $quantity_required, 
                                        $is_liquid
                                    );
                                    
                                    if ($menu_insert_stmt->execute()) {
                                        $connected_count++;
                                    }
                                    $menu_insert_stmt->close();
                                }
                                $check_stmt->close();
                            }
                            $menu_details_stmt->close();
                        }
                    }
                }
                
                $connection->commit();
                $connection->autocommit(true);
                
                $_SESSION['success_message'] = "Successfully connected \"{$item_name}\" to {$connected_count} menu item(s)";
                
            } catch (Exception $e) {
                $connection->rollback();
                $connection->autocommit(true);
                $_SESSION['error_message'] = $e->getMessage();
            }
        } else {
            $_SESSION["error_message"] = "Please select at least one menu item to connect";
        }

        // Redirect after processing
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    }

    // Handle Restock
    elseif (isset($_POST["restock_quantity"])) {
        $item_id = intval($_POST["item_id"]);
        $restock_quantity = floatval($_POST["restock_quantity"]);

        if ($item_id > 0 && $restock_quantity > 0) {
            $update_query = "UPDATE unified_inventory_archive SET current_quantity = current_quantity + ? WHERE item_id = ?";
            $update_stmt = $connection->prepare($update_query);
            
            if ($update_stmt) {
                $update_stmt->bind_param("di", $restock_quantity, $item_id);
                if ($update_stmt->execute()) {
                    $_SESSION['success_message'] = "Item restocked successfully with " . $restock_quantity . " units";
                } else {
                    $_SESSION['error_message'] = "Error restocking item: " . $update_stmt->error;
                }
                $update_stmt->close();
            } else {
                $_SESSION['error_message'] = "Prepare failed: " . $connection->error;
            }
        } else {
            $_SESSION["error_message"] = "Please provide valid item ID and restock quantity";
        }

        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    }
}

// Get all inventory items
$inventory_query = "SELECT item_id, item_name, category, current_quantity, unit, cost_per_unit, is_liquid FROM unified_inventory_archive ORDER BY item_name";
$inventory_result = $connection->query($inventory_query);
$inventory_items = [];
if ($inventory_result) {
    while ($row = $inventory_result->fetch_assoc()) {
        $inventory_items[] = $row;
    }
}

// Get units
$units_result = $connection->query("SELECT DISTINCT unit FROM unified_inventory_archive ORDER BY unit");
$units = [];
while ($row = $units_result->fetch_assoc()) {
    $units[] = $row['unit'];
}

// Get statistics for liquid items
$liquid_stats_query = "SELECT 
    COUNT(*) as total_items,
    COALESCE(SUM(current_quantity * cost_per_unit), 0) as total_value,
    SUM(CASE WHEN current_quantity <= 10 THEN 1 ELSE 0 END) as critical_items,
    SUM(CASE WHEN current_quantity > 10 AND current_quantity <= 50 THEN 1 ELSE 0 END) as low_items
FROM unified_inventory_archive
WHERE is_liquid = 1";

$liquid_stats_result = $connection->query($liquid_stats_query);
$liquid_stats = $liquid_stats_result ? $liquid_stats_result->fetch_assoc() : ['total_items' => 0, 'total_value' => 0, 'critical_items' => 0, 'low_items' => 0];

// Get statistics for solid items
$solid_stats_query = "SELECT 
    COUNT(*) as total_items,
    COALESCE(SUM(current_quantity * cost_per_unit), 0) as total_value,
    SUM(CASE WHEN current_quantity <= 10 THEN 1 ELSE 0 END) as critical_items,
    SUM(CASE WHEN current_quantity > 10 AND current_quantity <= 50 THEN 1 ELSE 0 END) as low_items
FROM unified_inventory_archive
WHERE is_liquid = 0";

$solid_stats_result = $connection->query($solid_stats_query);
$solid_stats = $solid_stats_result ? $solid_stats_result->fetch_assoc() : ['total_items' => 0, 'total_value' => 0, 'critical_items' => 0, 'low_items' => 0];

// Get menu categories that use ingredients
$menu_categories_query = "SELECT DISTINCT TRIM(ums.category) as category, TRIM(ums.section) as section
FROM unified_menu_system ums
WHERE ums.ingredient_id IS NOT NULL
AND ums.category IS NOT NULL
AND TRIM(ums.category) != ''
ORDER BY ums.section, ums.category";

$menu_categories_result = $connection->query($menu_categories_query);
$menu_categories = [];
if ($menu_categories_result) {
    while ($row = $menu_categories_result->fetch_assoc()) {
        $menu_categories[] = $row;
    }
}

// Get distinct categories for the add stock form
$inventory_categories_query = "SELECT DISTINCT category FROM unified_inventory_archive WHERE category IS NOT NULL AND category != '' ORDER BY category";
$inventory_categories_result = $connection->query($inventory_categories_query);
$inventory_categories = [];
if ($inventory_categories_result) {
    while ($row = $inventory_categories_result->fetch_assoc()) {
        $inventory_categories[] = $row['category'];
    }
}

// Get distinct units for the add stock form
$units_query = "SELECT DISTINCT unit FROM unified_inventory_archive WHERE unit IS NOT NULL AND unit != '' ORDER BY unit";
$units_result = $connection->query($units_query);
$units = [];
if ($units_result) {
    while ($row = $units_result->fetch_assoc()) {
        $units[] = $row['unit'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/fonts/fonts.css">
    <title>Inventory Archive</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f8fffe 0%, #e8f5e8 100%);
            min-height: 100vh;
            color: #2d5016;
            line-height: 1.6;
        }

        /* Navigation Styles */
        .navbar {
            background: linear-gradient(135deg, #7e5832 0%, #5a4026 100%);
            width: 100%;
            height: 80px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .navbar-right {
            display: flex;
            align-items: center;
        }

        .btn {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(135deg, #337609 0%, #2d5016 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(51, 118, 9, 0.3);
        }

        .btn:hover {
            background: linear-gradient(135deg, #2d5016 0%, #1a2f0a 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(51, 118, 9, 0.4);
        }

        .dropbtn {
            background: transparent;
            color: #ffffff;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }

        .dropbtn:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .dropdown {
            position: relative;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #ffffff;
            min-width: 120px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            z-index: 1001;
            border-radius: 8px;
            overflow: hidden;
            top: 100%;
            margin-top: 5px;
        }

        .dropdown-content a {
            color: #2d5016;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .dropdown-content a:hover {
            background-color: #f8fff8;
        }

        .show {
            display: block;
        }

        /* Main Content */
        .main-content {
            margin-top: 100px;
            padding: 0 20px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 2.5em;
            color: #2d5016;
            margin: 0;
            font-weight: 700;
        }

        .message {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .search-container {
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 12px 15px;
            border: 2px solid #d0e8d0;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Poppins', sans-serif;
        }

        .search-input:focus {
            outline: none;
            border-color: #337609;
            box-shadow: 0 0 5px rgba(51, 118, 9, 0.3);
        }

        .category-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .category-btn {
            padding: 8px 16px;
            background-color: #e8f5e8;
            color: #2d5016;
            border: 2px solid #d0e8d0;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .category-btn.active {
            background-color: #337609;
            color: #ffffff;
            border-color: #337609;
        }

        .category-btn:hover {
            border-color: #337609;
        }

        .inventory-section {
            margin-bottom: 50px;
        }

        .section-title {
            font-size: 1.8em;
            color: #2d5016;
            margin-bottom: 20px;
            font-weight: 700;
            border-bottom: 3px solid #337609;
            padding-bottom: 10px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 2em;
            font-weight: 700;
            color: #337609;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.9em;
            color: #666;
            font-weight: 500;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #337609;
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        tbody tr:hover {
            background-color: #f8fffe;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-critical {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-low {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-ok {
            background-color: #d4edda;
            color: #155724;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-left">
            <h2 style="color: white; margin: 0;">Inventory System</h2>
        </div>
        <div class="navbar-right">
            <div class="dropdown">
                <button class="dropbtn" onclick="toggleDropdown()">Menu ▼</button>
                <div id="myDropdown" class="dropdown-content">
                    <a href="#home">Home</a>
                    <a href="#about">About</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <?php
        if (!empty($error_message)) {
            echo "<div class='message error-message'>❌ " . htmlspecialchars($error_message) . "</div>";
        }

        if (!empty($success_message)) {
            echo "<div class='message success-message'>✅ " . htmlspecialchars($success_message) . "</div>";
        }
        ?>

        <header class="page-header">
            <h1 class="page-title">Current Inventory</h1>
        </header>

        <!-- Search and Filter -->
        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Search by item name or ID...">
        </div>

        <!-- Category Filters -->
        <div class="category-filters">
            <button class="category-btn active" data-category="all">All Categories</button>
            <?php foreach ($menu_categories as $cat): ?>
                <button class="category-btn" data-category="<?php echo htmlspecialchars($cat['category']); ?>">
                    <?php echo htmlspecialchars($cat['category']); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Liquid Items Section -->
        <section class="inventory-section">
            <h2 class="section-title">Liquid Items</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Cost/Unit</th>
                            <th>Total Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="liquid-inventory-tbody">
                        <?php
                        $liquid_items = array_filter($inventory_items, function($item) {
                            return $item['is_liquid'] == 1;
                        });
                        
                        if (empty($liquid_items)) {
                            echo "<tr><td colspan='8' class='no-data'>No liquid items in inventory</td></tr>";
                        } else {
                            foreach ($liquid_items as $item) {
                                $status = 'ok';
                                if ($item['current_quantity'] <= 10) {
                                    $status = 'critical';
                                } elseif ($item['current_quantity'] <= 50) {
                                    $status = 'low';
                                }
                                $status_class = 'status-' . $status;
                                $status_text = ucfirst($status);
                                
                                echo "<tr data-item-id='" . htmlspecialchars($item['item_id']) . "' data-item-name='" . htmlspecialchars(strtolower($item['item_name'])) . "'>";
                                echo "<td>" . htmlspecialchars($item['item_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['item_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['category']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['current_quantity']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['unit']) . "</td>";
                                echo "<td>$" . number_format($item['cost_per_unit'], 2) . "</td>";
                                echo "<td>$" . number_format($item['current_quantity'] * $item['cost_per_unit'], 2) . "</td>";
                                echo "<td><span class='status-badge $status_class'>$status_text</span></td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Solid Items Section -->
        <section class="inventory-section">
            <h2 class="section-title">Solid Items</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Cost/Unit</th>
                            <th>Total Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="solid-inventory-tbody">
                        <?php
                        $solid_items = array_filter($inventory_items, function($item) {
                            return $item['is_liquid'] == 0;
                        });
                        
                        if (empty($solid_items)) {
                            echo "<tr><td colspan='8' class='no-data'>No solid items in inventory</td></tr>";
                        } else {
                            foreach ($solid_items as $item) {
                                $status = 'ok';
                                if ($item['current_quantity'] <= 10) {
                                    $status = 'critical';
                                } elseif ($item['current_quantity'] <= 50) {
                                    $status = 'low';
                                }
                                $status_class = 'status-' . $status;
                                $status_text = ucfirst($status);
                                
                                echo "<tr data-item-id='" . htmlspecialchars($item['item_id']) . "' data-item-name='" . htmlspecialchars(strtolower($item['item_name'])) . "'>";
                                echo "<td>" . htmlspecialchars($item['item_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['item_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['category']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['current_quantity']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['unit']) . "</td>";
                                echo "<td>$" . number_format($item['cost_per_unit'], 2) . "</td>";
                                echo "<td>$" . number_format($item['current_quantity'] * $item['cost_per_unit'], 2) . "</td>";
                                echo "<td><span class='status-badge $status_class'>$status_text</span></td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        // Search and filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const categoryButtons = document.querySelectorAll('.category-btn');
            
            // Search functionality
            searchInput.addEventListener('keyup', function() {
                filterTable();
            });
            
            // Category filter functionality
            categoryButtons.forEach(button => {
                button.addEventListener('click', function() {
                    categoryButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    filterTable();
                });
            });
            
            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase();
                const activeCategory = document.querySelector('.category-btn.active').getAttribute('data-category');
                
                // Filter liquid items
                const liquidRows = document.querySelectorAll('#liquid-inventory-tbody tr');
                liquidRows.forEach(row => {
                    const itemName = row.getAttribute('data-item-name') || '';
                    const itemId = row.getAttribute('data-item-id') || '';
                    
                    let matchesSearch = itemName.includes(searchTerm) || itemId.includes(searchTerm);
                    let matchesCategory = activeCategory === 'all';
                    
                    row.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
                });
                
                // Filter solid items
                const solidRows = document.querySelectorAll('#solid-inventory-tbody tr');
                solidRows.forEach(row => {
                    const itemName = row.getAttribute('data-item-name') || '';
                    const itemId = row.getAttribute('data-item-id') || '';
                    
                    let matchesSearch = itemName.includes(searchTerm) || itemId.includes(searchTerm);
                    let matchesCategory = activeCategory === 'all';
                    
                    row.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
                });
            }
        });

        // Dropdown toggle functionality
        document.addEventListener('click', function(event) {
            const dropdowns = document.querySelectorAll('.dropdown-content');
            if (!event.target.matches('.dropbtn')) {
                dropdowns.forEach(dropdown => {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                    }
                });
            }
        });

        function toggleDropdown() {
            document.getElementById("myDropdown").classList.toggle("show");
        }
    </script>
</body>
</html>

