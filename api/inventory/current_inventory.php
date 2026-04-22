<?php
include "db_conn.php";
$connection = $conn;


function getExpirationStatus($expirationDate) {
    if (empty($expirationDate)) {
        return ['status' => 'N/A', 'daysLeft' => null, 'badge' => '<span style="color: #666;">N/A</span>'];
    }
    
    $today = new DateTime();
    $expDate = new DateTime($expirationDate);
    $interval = $today->diff($expDate);
    $daysLeft = $interval->invert === 1 ? -1 : $interval->days; 
    
    if ($expDate < $today) {
        return [
            'status' => 'EXPIRED',
            'daysLeft' => -1,
            'badge' => '<span class="expiration-expired">🔴 EXPIRED</span>'
        ];
    } elseif ($daysLeft <= 3) {
        $dayText = $daysLeft === 1 ? 'day' : 'days';
        return [
            'status' => 'CRITICAL',
            'daysLeft' => $daysLeft,
            'badge' => '<span class="expiration-critical">🔴 ' . $daysLeft . ' ' . $dayText . ' left</span>'
        ];
    } elseif ($daysLeft <= 20) {
        $dayText = $daysLeft === 1 ? 'day' : 'days';
        return [
            'status' => 'WARNING',
            'daysLeft' => $daysLeft,
            'badge' => '<span class="expiration-warning">🟡 ' . $daysLeft . ' ' . $dayText . ' left</span>'
        ];
    } else {
        return [
            'status' => 'GOOD',
            'daysLeft' => $daysLeft,
            'badge' => '<span class="expiration-good">✅ ' . $daysLeft . ' days</span>'
        ];
    }
}


$error_message = '';
$success_message = '';

$categories_query = "SELECT DISTINCT category FROM unified_inventory ORDER BY category ASC";
$categories_result = $connection->query($categories_query);
$inventory_categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $inventory_categories[] = $row['category'];
    }
} else {
    $error_message = "Error fetching inventory categories: " . $connection->error;
}

$menu_categories_query = "SELECT DISTINCT category, section FROM unified_menu_system WHERE menu_item_id IS NOT NULL AND menu_item_name IS NOT NULL ORDER BY section, category";
$menu_categories_result = $connection->query($menu_categories_query);
$menu_categories = [];
if ($menu_categories_result) {
    while ($row = $menu_categories_result->fetch_assoc()) {
        $menu_categories[] = $row;
    }
} else {
    $error_message = "Error fetching menu categories: " . $connection->error;
}

$units_query = "SELECT DISTINCT unit FROM unified_inventory ORDER BY unit ASC";
$units_result = $connection->query($units_query);
$units = [];
if ($units_result) {
    while ($row = $units_result->fetch_assoc()) {
        $units[] = $row['unit'];
    }
} else {
    $error_message = "Error fetching units: " . $connection->error;
}

$solid_stats_query = "SELECT 
                        COUNT(*) as total_items,
                        COALESCE(SUM(current_quantity * cost_per_unit), 0) as total_value,
                        SUM(CASE WHEN current_quantity <= 10 THEN 1 ELSE 0 END) as critical_items,
                        SUM(CASE WHEN current_quantity > 10 AND current_quantity <= 50 THEN 1 ELSE 0 END) as low_items
                      FROM unified_inventory
                      WHERE is_liquid = 0";
$solid_stats_result = $connection->query($solid_stats_query);
$solid_stats = $solid_stats_result ? $solid_stats_result->fetch_assoc() : ['total_items' => 0, 'total_value' => 0, 'critical_items' => 0, 'low_items' => 0];


$liquid_stats_query = "SELECT 
                        COUNT(*) as total_items,
                        COALESCE(SUM(current_quantity/1900 * cost_per_unit), 0) as total_value,
                        SUM(CASE WHEN current_quantity <= 100 THEN 1 ELSE 0 END) as critical_items,
                        SUM(CASE WHEN current_quantity > 100 AND current_quantity <= 800 THEN 1 ELSE 0 END) as low_items
                      FROM unified_inventory
                      WHERE is_liquid = 1";
$liquid_stats_result = $connection->query($liquid_stats_query);
$liquid_stats = $liquid_stats_result ? $liquid_stats_result->fetch_assoc() : ['total_items' => 0, 'total_value' => 0, 'critical_items' => 0, 'low_items' => 0];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["action"])) {
        switch ($_POST["action"]) {
            case "add_stock":
                $item_name = $_POST['item_name'];
                $category = $_POST['category'];
                $new_category = $_POST['new_category'] ?? null; 
                $unit = $_POST['unit'];
                $new_unit = $_POST['new_unit'] ?? null; 
                $current_quantity = floatval($_POST['current_quantity']); 
                $cost_per_unit = floatval($_POST['cost_per_unit']); 
                $is_liquid = isset($_POST['is_liquid']) ? 1 : 0;
                $expiration_date = $_POST['expiration_date'] ?? null;

                if ($category === "NEW_CATEGORY" && !empty($new_category)) {
                    $category = trim($new_category);
                }
       
                if ($unit === "NEW_UNIT" && !empty($new_unit)) {
                    $unit = trim($new_unit);
                }
      
                if (empty($category) || empty($unit)) {
                    $error_message = "Item name, category, and unit are required.";
                    break;
                }

                $stmt_add = $connection->prepare("INSERT INTO unified_inventory (item_name, category, unit, current_quantity, cost_per_unit, is_liquid, expiration_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_add->bind_param("sssdids", $item_name, $category, $unit, $current_quantity, $cost_per_unit, $is_liquid, $expiration_date);

                if ($stmt_add->execute()) {
                    $success_message = "New stock item '{$item_name}' added successfully.";


                    if (isset($_POST['menu_items']) && is_array($_POST['menu_items'])) {
                        $new_item_id = $connection->insert_id; 
                        $stmt_connect = $connection->prepare("INSERT INTO unified_menu_system (menu_item_id, size_id, ingredient_id, quantity_required) VALUES (?, ?, ?, ?)");
                        
                        foreach ($_POST['menu_items'] as $menu_selection) {
                            list($menu_item_id, $size_id) = explode('_', $menu_selection);
                            $quantity = floatval($_POST['quantities'][$menu_selection] ?? 0);
                            
                            if ($quantity > 0) {
                                $stmt_connect->bind_param("sssd", $menu_item_id, $size_id, $new_item_id, $quantity);
                                $stmt_connect->execute();
                            }
                        }
                        $stmt_connect->close();
                    }
                } else {
                    $error_message = "Error adding stock item: " . $stmt_add->error;
                }
                $stmt_add->close();
                break;

            case "restock_item":
                $item_id = intval($_POST['item_id']);
                $restock_quantity = floatval($_POST['restock_quantity']);

                if ($item_id > 0 && $restock_quantity > 0) {
                 
                    $stmt_get_qty = $connection->prepare("SELECT current_quantity, item_name FROM unified_inventory WHERE item_id = ?");
                    $stmt_get_qty->bind_param("i", $item_id);
                    $stmt_get_qty->execute();
                    $result_qty = $stmt_get_qty->get_result();
                    $item_data = $result_qty->fetch_assoc();
                    $stmt_get_qty->close();

                    if ($item_data) {
                        $new_quantity = $item_data['current_quantity'] + $restock_quantity;
                        $stmt_update = $connection->prepare("UPDATE unified_inventory SET current_quantity = ? WHERE item_id = ?");
                        $stmt_update->bind_param("di", $new_quantity, $item_id);

                        if ($stmt_update->execute()) {
                            $success_message = "Restocked " . $restock_quantity . " units of '" . htmlspecialchars($item_data['item_name']) . "'. New quantity: " . $new_quantity;
                        } else {
                            $error_message = "Error restocking item: " . $stmt_update->error;
                        }
                        $stmt_update->close();
                    } else {
                        $error_message = "Item with ID " . $item_id . " not found.";
                    }
                } else {
                    $error_message = "Invalid input for restock quantity.";
                }
                break;

            case "edit_item":
                $item_id = intval($_POST['item_id']);
                $item_name = $_POST['item_name'];
                $cost_per_unit = floatval($_POST['cost_per_unit']);
                $expiration_date = $_POST['expiration_date'] ?? null; 

                if ($item_id > 0) {
               
                    $stmt_check_liquid = $connection->prepare("SELECT is_liquid FROM unified_inventory WHERE item_id = ?");
                    $stmt_check_liquid->bind_param("i", $item_id);
                    $stmt_check_liquid->execute();
                    $result_liquid = $stmt_check_liquid->get_result();
                    $item_info = $result_liquid->fetch_assoc();
                    $stmt_check_liquid->close();

                    $update_fields = "item_name = ?, cost_per_unit = ?";
                    $bind_types = "sd";
                    $bind_params = [$item_name, $cost_per_unit];

                    if ($item_info && $item_info['is_liquid'] == 1) {
                        $update_fields .= ", expiration_date = ?";
                        $bind_types .= "s";
                        $bind_params[] = $expiration_date;
                    }

                    $sql_update = "UPDATE unified_inventory SET {$update_fields} WHERE item_id = ?";
                    $bind_types .= "i";
                    $bind_params[] = $item_id;

                    $stmt_edit = $connection->prepare($sql_update);
                    $stmt_edit->bind_param($bind_types, ...$bind_params);

                    if ($stmt_edit->execute()) {
                        $success_message = "Item '" . htmlspecialchars($item_name) . "' updated successfully.";
                    } else {
                        $error_message = "Error updating item: " . $stmt_edit->error;
                    }
                    $stmt_edit->close();
                } else {
                    $error_message = "Invalid item ID for editing.";
                }
                break;

            case "delete_item":
                $item_id = intval($_POST['item_id']);
                if ($item_id > 0) {
        
                    $stmt_get_name = $connection->prepare("SELECT item_name FROM unified_inventory WHERE item_id = ?");
                    $stmt_get_name->bind_param("i", $item_id);
                    $stmt_get_name->execute();
                    $result_name = $stmt_get_name->get_result();
                    $item_to_delete = $result_name->fetch_assoc();
                    $stmt_get_name->close();

                    $item_name_for_msg = $item_to_delete ? htmlspecialchars($item_to_delete['item_name']) : "item with ID " . $item_id;

                    $stmt_delete_inv = $connection->prepare("DELETE FROM unified_inventory WHERE item_id = ?");
                    $stmt_delete_inv->bind_param("i", $item_id);

                    if ($stmt_delete_inv->execute()) {
                 
                        $stmt_delete_menu = $connection->prepare("DELETE FROM unified_menu_system WHERE ingredient_id = ?");
                        $stmt_delete_menu->bind_param("i", $item_id);
                        $stmt_delete_menu->execute(); 

                        $success_message = $item_name_for_msg . " deleted successfully.";
                    } else {
                        $error_message = "Error deleting item '" . $item_name_for_msg . "': " . $stmt_delete_inv->error;
                    }
                    $stmt_delete_inv->close();
                    $stmt_delete_menu->close();
                } else {
                    $error_message = "Invalid item ID for deletion.";
                }
                break;
            
            case "connect_to_menu":
                $item_id = intval($_POST['item_id']);
                $menu_items = $_POST['menu_items'] ?? [];
                $quantities = $_POST['quantities'] ?? [];

                if ($item_id > 0) {
         
                    $stmt_remove_old = $connection->prepare("DELETE FROM unified_menu_system WHERE ingredient_id = ?");
                    $stmt_remove_old->bind_param("i", $item_id);
                    $stmt_remove_old->execute();
                    $stmt_remove_old->close();

                    $success_count = 0;
                    $error_connecting = false;

        
                    $stmt_insert_conn = $connection->prepare("INSERT INTO unified_menu_system (menu_item_id, size_id, ingredient_id, quantity_required) VALUES (?, ?, ?, ?)");

                    foreach ($menu_items as $menu_selection) {
                        list($menu_item_id, $size_id) = explode('_', $menu_selection);
                        $quantity = floatval($quantities[$menu_selection] ?? 0);

                        if ($quantity > 0 && !empty($menu_item_id) && !empty($size_id)) {
                            $stmt_insert_conn->bind_param("sssd", $menu_item_id, $size_id, $item_id, $quantity);
                            if ($stmt_insert_conn->execute()) {
                                $success_count++;
                            } else {
                                $error_connecting = true;
                              
                                error_log("Error connecting item ID {$item_id} to menu item {$menu_item_id} ({$size_id}): " . $stmt_insert_conn->error);
                            }
                        }
                    }
                    $stmt_insert_conn->close();

                    if ($error_connecting) {
                        $error_message = "Some menu item connections could not be saved. Please check logs.";
                    }
                    if ($success_count > 0) {
                        $success_message = "Successfully connected " . $success_count . " menu item(s) to ingredient ID " . $item_id . ".";
                    } elseif (!$error_connecting) {
                        $success_message = "No new menu item connections were made for ingredient ID " . $item_id . ".";
                    }
                } else {
                    $error_message = "Invalid item ID for menu connection.";
                }
                break;
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management System</title>
    <link rel="stylesheet" href="/fonts/fonts.css">
    <link rel="icon" href="/media/BUBBLE.jpg">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f8fffe 0%, #e8f5e8 100%);
            min-height: 100vh;
            color: #2d5016;
            line-height: 1.6;
        }

        .expiration-expired {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 700;
            border: 2px solid #f44336;
            display: inline-block;
            font-size: 0.85rem;
        }

        .expiration-critical {
            background: linear-gradient(135deg, #ffcdd2 0%, #ef9a9a 100%);
            color: #c62828;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 700;
            border: 2px solid #f44336;
            display: inline-block;
            font-size: 0.85rem;
        }

        .expiration-warning {
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
            color: #f57f17;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 700;
            border: 2px solid #ff9800;
            display: inline-block;
            font-size: 0.85rem;
        }

        .expiration-good {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            color: #2e7d32;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 600;
            border: 2px solid #4caf50;
            display: inline-block;
            font-size: 0.85rem;
        }

        .expiration-na {
            background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
            color: #666;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 600;
            border: 2px solid #999;
            display: inline-block;
            font-size: 0.85rem;
        }


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

        .btn-add-stock {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            font-weight: 700;
        }

        .btn-add-stock:hover {
            background: linear-gradient(135deg, #e55a2b 0%, #d4821a 100%);
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

        .show { display: block; }

  
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
            font-size: 2.5rem;
            font-weight: 800;
            color: #2d5016;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: #666;
            font-weight: 400;
        }


        .inventory-section-header {
            background: linear-gradient(135deg, #337609 0%, #2d5016 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin: 30px 0 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 20px rgba(51, 118, 9, 0.3);
        }

        .inventory-section-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .inventory-section-header .icon {
            font-size: 2rem;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fff8 100%);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(51, 118, 9, 0.1);
            text-align: center;
            border: 2px solid #e8f5e8;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #337609 0%, #2d5016 100%);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: #337609;
            margin-bottom: 8px;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #2d5016;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .critical-stock {
            border-color: #ffcdd2;
        }

        .critical-stock::before {
            background: linear-gradient(135deg, #f44336 0%, #c62828 100%);
        }

        .critical-stock .stat-number {
            color: #c62828;
        }

        .low-stock {
            border-color: #ffecb3;
        }

        .low-stock::before {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        }

        .low-stock .stat-number {
            color: #f57c00;
        }


        .filters-section {
            background: #ffffff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(51, 118, 9, 0.1);
            margin-bottom: 30px;
            border: 2px solid #e8f5e8;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2d5016;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .category-btn {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: #2d5016;
            background: linear-gradient(135deg, #ffffff 0%, #f8fff8 100%);
            border: 2px solid #337609;
            padding: 10px 18px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            box-shadow: 0 2px 10px rgba(51, 118, 9, 0.1);
        }

        .category-btn:hover {
            background: linear-gradient(135deg, #337609 0%, #2d5016 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(51, 118, 9, 0.3);
        }

        .category-btn.active {
            background: linear-gradient(135deg, #337609 0%, #2d5016 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(51, 118, 9, 0.4);
        }

        .category-btn.all {
            background: linear-gradient(135deg, #7e5832 0%, #5a4026 100%);
            color: white;
            border-color: #7e5832;
            font-weight: 700;
        }

        .category-btn.all:hover {
            background: linear-gradient(135deg, #5a4026 0%, #3d2a1a 100%);
        }

        .search-container {
            position: relative;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #337609;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 500;
            background: #ffffff;
            color: #2d5016;
            outline: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(51, 118, 9, 0.1);
        }

        .search-input:focus {
            border-color: #2d5016;
            box-shadow: 0 4px 20px rgba(51, 118, 9, 0.2);
            transform: translateY(-1px);
        }

        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.2rem;
        }

       
        .table-container {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(51, 118, 9, 0.1);
            overflow: hidden;
            border: 2px solid #e8f5e8;
            margin-bottom: 30px;
        }

        .table-wrapper {
            max-height: 600px;
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th {
            background: linear-gradient(135deg, #337609 0%, #2d5016 100%);
            color: white;
            padding: 18px 15px;
            text-align: left;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e8f5e8;
            transition: background-color 0.3s ease;
        }
        
        tr:hover {
            background-color: #f8fff8;
        }

        .critical-stock-row {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%) !important;
            border-left: 4px solid #f44336;
        }

        .low-stock-row {
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%) !important;
            border-left: 4px solid #ff9800;
        }

        .good-stock-row {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%) !important;
            border-left: 4px solid #4caf50;
        }

        .btn-edit {
            background: linear-gradient(135deg, #337609 0%, #2d5016 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(51, 118, 9, 0.3);
            margin-right: 5px;
            margin-bottom: 5px;
            width:80px;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #2d5016 0%, #1a2f0a 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(51, 118, 9, 0.4);
        }

        .btn-edit-item {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .btn-edit-item:hover {
            background: linear-gradient(135deg, #f57c00 0%, #e65100 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #f44336 0%, #c62828 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);
            margin-right: 5px;
            margin-bottom: 5px;
            width:80px;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #c62828 0%, #b71c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4);
        }

        .btn-connect {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(33, 150, 243, 0.3);
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .btn-connect:hover {
            background: linear-gradient(135deg, #1976D2 0%, #0D47A1 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
        }

        .highlight {
            background-color: #90EE90;
            font-weight: bold;
            color: #2d5016;
            padding: 2px 4px;
            border-radius: 4px;
        }

        .category-tag {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            color: #2d5016;
            font-weight: 600;
            display: inline-block;
        }

      
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            display: none;
            backdrop-filter: blur(5px);
        }

        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #ffffff 0%, #f8fff8 100%);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            z-index: 2001;
            display: none;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            border: 3px solid #337609;
        }

        .modal-large {
            width: 800px;
        }

        .modal-small {
            width: 500px;
        }

        .modal-header {
            margin-bottom: 25px;
            text-align: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #337609;
            margin-bottom: 10px;
        }

        .modal-subtitle {
            color: #666;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: #2d5016;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #337609;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 500;
            background: #ffffff;
            color: #2d5016;
            outline: none;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-input:focus, .form-select:focus {
            border-color: #2d5016;
            box-shadow: 0 4px 15px rgba(51, 118, 9, 0.2);
            transform: translateY(-1px);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .checkbox-input {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e8f5e8;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }

        .btn-cancel:hover {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%);
        }

    
        .menu-connection-section {
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%);
            border: 2px solid #4a90e2;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }

        .menu-section-header {
            background: linear-gradient(135deg, #337609 0%, #2d5016 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            margin: 10px 0 5px 0;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .menu-item-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }

        .menu-item-row:hover {
            box-shadow: 0 2px 8px rgba(51, 118, 9, 0.1);
        }

        .menu-item-checkbox {
            width: 20px;
            height: 20px;
        }

        .menu-item-name {
            flex: 1;
            font-weight: 600;
            color: #2d5016;
        }

        .quantity-input {
            width: 100px;
            height: 35px;
            border: 2px solid #337609;
            border-radius: 8px;
            padding: 0 8px;
            text-align: center;
            font-weight: 600;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        .error-message {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-style: italic;
            font-size: 1.1rem;
        }
        
        .center{
           text-align: center;
            vertical-align: middle; 
        }

        .show1 { display: block !important; }

        @media (max-width: 768px) {
            .navbar {
                padding: 0 15px;
                flex-direction: column;
                height: auto;
                padding: 15px;
            }

            .navbar-left {
                flex-wrap: wrap;
                justify-content: center;
                margin-bottom: 10px;
            }

            .main-content {
                margin-top: 140px;
                padding: 0 15px;
            }

            .page-title {
                font-size: 2rem;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .modal {
                width: 95vw;
                padding: 20px;
            }

            .category-filters {
                justify-content: center;
            }

            table {
                font-size: 0.8rem;
            }

            th, td {
                padding: 10px 8px;
            }
        }

      
        .table-wrapper::-webkit-scrollbar {
            width: 8px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #337609 0%, #2d5016 100%);
            border-radius: 4px;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #2d5016 0%, #1a2f0a 100%);
        }

        
    </style>
</head>

<body>

    <nav class="navbar">
        <div class="navbar-left">
            <form action="/interface/admin_homepage.php"><button type="submit" class="btn"> Back </button></form>
            <button type="button" class="btn btn-add-stock" onclick="openAddStockModal()">Add Stock</button>
            <form action="/inventory/add_stock_history.php"><button type="submit" class="btn btn-add-stock"> Restock History </button></form>
            <form action="/inventory/depleted_stock_history.php"><button type="submit" class="btn btn-add-stock"> Depleted History </button></form>

        </div>
        
        <div class="navbar-right">
            <div class="dropdown">
                <button onclick="toggleDropdown()" class="dropbtn">Admin</button>
                <div id="myDropdown" class="dropdown-content">
                    <a href="/interface/logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>
<script>
function toggleDropdown() {
    document.getElementById("myDropdown").classList.toggle("show");
}
</script>
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

       
        <section class="filters-section">
            <h2 class="section-title">🔍︎ Filter & Search</h2>
            
            <div class="category-filters">
                <button class="category-btn all active" data-category="all">All Items</button>
                <?php foreach ($inventory_categories as $category): ?>
                    <button class="category-btn" data-category="<?php echo htmlspecialchars(trim($category)); ?>">
                        <?php echo ucwords(str_replace('_', ' ', strtolower(trim($category)))); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="search-container">
                <span class="search-icon">🔍︎</span>
                <input type="text" id="searchInput" class="search-input" placeholder="Search by Product Name or ID">
            </div>
        </section>

  
        <div class="inventory-section-header">
            <h2>💧 Liquid Stock Items</h2>
        </div>

        
        <section class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $liquid_stats['total_items']; ?></div>
                <div class="stat-label">Liquid Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">₱<?php echo number_format($liquid_stats['total_value'], 0); ?></div>
                <div class="stat-label">Liquid Value</div>
            </div>
            <div class="stat-card critical-stock">
                <div class="stat-number"><?php echo $liquid_stats['critical_items']; ?></div>
                <div class="stat-label">Critical</div>
            </div>
            <div class="stat-card low-stock">
                <div class="stat-number"><?php echo $liquid_stats['low_items']; ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
        </section>

       
        <section class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Used In Menu</th>
                            <th>Current Qty</th>
                            <th>Unit</th>
                            <th>Cost/Unit</th>
                            <th>Total Value</th>
                            <th>Expiration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="liquid-inventory-tbody">
                    <?php
                 
                    $sql_liquid = "SELECT ui.item_id,
                                        ui.item_name,
                                        ui.category as inventory_category,
                                        ui.current_quantity,
                                        ui.unit,
                                        ui.cost_per_unit,
                                        ui.is_liquid,
                                        ui.expiration_date,
                                        (ui.current_quantity/1900 * ui.cost_per_unit) as total_value,
                                        CASE 
                                            WHEN ui.current_quantity <= 100 THEN 'CRITICAL'
                                            WHEN ui.current_quantity <= 800 THEN 'LOW'
                                            ELSE 'GOOD'
                                        END as stock_status,
                                        GROUP_CONCAT(DISTINCT TRIM(ums.category) SEPARATOR '###') as used_in_menu_categories,
                                        GROUP_CONCAT(DISTINCT CONCAT(TRIM(ums.category), ' (', TRIM(ums.section), ')') SEPARATOR ', ') as used_in_menu_display
                                      FROM unified_inventory ui
                                      LEFT JOIN unified_menu_system ums ON ui.item_id = ums.ingredient_id
                                      WHERE ui.is_liquid = 1
                                      GROUP BY ui.item_id, ui.item_name, ui.category, ui.current_quantity, ui.unit, ui.cost_per_unit, ui.is_liquid, ui.expiration_date
                                      ORDER BY
                                        CASE 
                                            WHEN ui.current_quantity <= 100 THEN 1
                                            WHEN ui.current_quantity <= 800 THEN 2
                                            ELSE 3
                                        END,
                                        ui.item_name";
                    
                    $result_liquid = $connection->query($sql_liquid);

                    if (!$result_liquid) {
                        echo "<tr><td colspan='11' class='error-message'>Invalid query: " . $connection->error . "</td></tr>";
                    } else {
                        $row_count = 0;
                        while ($row = $result_liquid->fetch_assoc()) {
                            $row_count++;
                           
                            $rowClass = '';
                            switch($row['stock_status']) {
                                case 'CRITICAL':
                                    $rowClass = 'critical-stock-row';
                                    break;
                                case 'LOW':
                                    $rowClass = 'low-stock-row';
                                    break;
                                case 'GOOD':
                                    $rowClass = 'good-stock-row';
                                    break;
                            }

                            $usedInMenuDisplay = $row['used_in_menu_display'] ? $row['used_in_menu_display'] : 'Not used in menu';
                            $usedInMenuCategories = $row['used_in_menu_categories'] ? $row['used_in_menu_categories'] : '';
                            
                            $expirationDisplay = 'N/A';
                            $expirationClass = 'expiration-na';
                            
                            if ($row['expiration_date']) {
                                $expDate = new DateTime($row['expiration_date']);
                                $today = new DateTime();
                                $interval = $today->diff($expDate);
                                $daysRemaining = $interval->invert === 1 ? -1 : $interval->days; 
                                
                                if ($daysRemaining < 0) { // Expired
                                    $expirationDisplay = 'EXPIRED';
                                    $expirationClass = 'expiration-expired';
                                } elseif ($daysRemaining <= 3) {
                                    $expirationDisplay = $daysRemaining . ' days left';
                                    $expirationClass = 'expiration-critical';
                                } elseif ($daysRemaining <= 20) {
                                    $expirationDisplay = $daysRemaining . ' days left';
                                    $expirationClass = 'expiration-warning';
                                } else {
                                    $expirationDisplay = date('M d, Y', strtotime($row['expiration_date']));
                                    $expirationClass = 'expiration-good';
                                }
                            }
                            
                           
                            $safeItemName = htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8');
                            $safeCategory = htmlspecialchars($row['inventory_category'], ENT_QUOTES, 'UTF-8');
                            $safeUnit = htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8');
                            $safeExpirationDate = htmlspecialchars($row['expiration_date'] ?? '', ENT_QUOTES, 'UTF-8');

                            echo "
                            <tr class='$rowClass inventory-row liquid-row' 
                                data-menu-categories='" . htmlspecialchars($usedInMenuCategories, ENT_QUOTES, 'UTF-8') . "'
                                data-item-name='" . htmlspecialchars(strtolower($row['item_name']), ENT_QUOTES, 'UTF-8') . "'
                                data-item-id='" . htmlspecialchars($row['item_id'], ENT_QUOTES, 'UTF-8') . "'>
                                <td><strong>{$row['item_id']}</strong></td>
                                <td> " . $safeItemName . "</td>
                                <td><span class='category-tag'>" . $safeCategory . "</span></td>
                                <td><small style='color: #666;'>" . htmlspecialchars($usedInMenuDisplay, ENT_QUOTES, 'UTF-8') . "</small></td>
                                <td><strong>{$row['current_quantity']}</strong></td>
                                <td>" . $safeUnit . "</td>
                                <td>₱" . number_format($row['cost_per_unit'], 2) . "</td>
                                <td><strong>₱" . number_format($row['total_value'], 2) . "</strong></td>
                                <td><span class='{$expirationClass}'>{$expirationDisplay}</span></td>
                                <td><strong>{$row['stock_status']}</strong></td>
                                <td class='center'>
                                    <button class='btn-edit' 
                                           onclick='openRestockModal({$row['item_id']}, \"{$safeItemName}\")'>
                                        Restock
                                    </button>
                                    <button class='btn-connect' 
                                           onclick='openConnectModal({$row['item_id']}, \"{$safeItemName}\", {$row['is_liquid']})'>
                                         Connect
                                    </button>
                                    <button class='btn-edit-item' 
                                           onclick='openEditModal({$row['item_id']}, \"{$safeItemName}\", \"{$safeCategory}\", {$row['current_quantity']}, \"{$safeUnit}\", {$row['cost_per_unit']}, {$row['is_liquid']}, \"{$safeExpirationDate}\")'>
                                        Edit
                                    </button>
                                    <button class='btn-delete' 
                                           onclick='openDeleteModal({$row['item_id']}, \"{$safeItemName}\")'>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            ";
                        }
                        
                        if ($row_count === 0) {
                            echo "<tr><td colspan='11' class='no-results'>No liquid items found.</td></tr>";
                        }
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </section>

    
        <div class="inventory-section-header">
            <h2>Solid Stock Items</h2>
        </div>

        
        <section class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $solid_stats['total_items']; ?></div>
                <div class="stat-label">Solid Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">₱<?php echo number_format($solid_stats['total_value'], 0); ?></div>
                <div class="stat-label">Solid Value</div>
            </div>
            <div class="stat-card critical-stock">
                <div class="stat-number"><?php echo $solid_stats['critical_items']; ?></div>
                <div class="stat-label">Critical</div>
            </div>
            <div class="stat-card low-stock">
                <div class="stat-number"><?php echo $solid_stats['low_items']; ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
        </section>

       
        <section class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Used In Menu</th>
                            <th>Current Qty</th>
                            <th>Unit</th>
                            <th>Cost/Unit</th>
                            <th>Total Value</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="solid-inventory-tbody">
                    <?php
                    
                    $sql_solid = "SELECT ui.item_id,
                                        ui.item_name,
                                        ui.category as inventory_category,
                                        ui.current_quantity,
                                        ui.unit,
                                        ui.cost_per_unit,
                                        ui.is_liquid,
                                        (ui.current_quantity * ui.cost_per_unit) as total_value,
                                        CASE 
                                            WHEN ui.current_quantity <= 10 THEN 'CRITICAL'
                                            WHEN ui.current_quantity <= 50 THEN 'LOW'
                                            ELSE 'GOOD'
                                        END as stock_status,
                                        GROUP_CONCAT(DISTINCT TRIM(ums.category) SEPARATOR '###') as used_in_menu_categories,
                                        GROUP_CONCAT(DISTINCT CONCAT(TRIM(ums.category), ' (', TRIM(ums.section), ')') SEPARATOR ', ') as used_in_menu_display
                                      FROM unified_inventory ui
                                      LEFT JOIN unified_menu_system ums ON ui.item_id = ums.ingredient_id
                                      WHERE ui.is_liquid = 0
                                      GROUP BY ui.item_id, ui.item_name, ui.category, ui.current_quantity, ui.unit, ui.cost_per_unit, ui.is_liquid
                                      ORDER BY 
                                        CASE 
                                            WHEN ui.current_quantity <= 10 THEN 1
                                            WHEN ui.current_quantity <= 50 THEN 2
                                            ELSE 3
                                        END,
                                        ui.item_name";
                    
                    $result_solid = $connection->query($sql_solid);

                    if (!$result_solid) {
                        echo "<tr><td colspan='10' class='error-message'>Invalid query: " . $connection->error . "</td></tr>";
                    } else {
                        $row_count = 0;
                        while ($row = $result_solid->fetch_assoc()) {
                            $row_count++;
                         
                            $rowClass = '';
                            switch($row['stock_status']) {
                                case 'CRITICAL':
                                    $rowClass = 'critical-stock-row';
                                    break;
                                case 'LOW':
                                    $rowClass = 'low-stock-row';
                                    break;
                                case 'GOOD':
                                    $rowClass = 'good-stock-row';
                                    break;
                            }

                            $usedInMenuDisplay = $row['used_in_menu_display'] ? $row['used_in_menu_display'] : 'Not used in menu';
                            $usedInMenuCategories = $row['used_in_menu_categories'] ? $row['used_in_menu_categories'] : '';
                            
                        
                            $safeItemName = htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8');
                            $safeCategory = htmlspecialchars($row['inventory_category'], ENT_QUOTES, 'UTF-8');
                            $safeUnit = htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8');

                            echo "
                            <tr class='$rowClass inventory-row solid-row' 
                                data-menu-categories='" . htmlspecialchars($usedInMenuCategories, ENT_QUOTES, 'UTF-8') . "'
                                data-item-name='" . htmlspecialchars(strtolower($row['item_name']), ENT_QUOTES, 'UTF-8') . "'
                                data-item-id='" . htmlspecialchars($row['item_id'], ENT_QUOTES, 'UTF-8') . "'>
                                <td><strong>{$row['item_id']}</strong></td>
                                <td> " . $safeItemName . "</td>
                                <td><span class='category-tag'>" . $safeCategory . "</span></td>
                                <td><small style='color: #666;'>" . htmlspecialchars($usedInMenuDisplay, ENT_QUOTES, 'UTF-8') . "</small></td>
                                <td><strong>{$row['current_quantity']}</strong></td>
                                <td>" . $safeUnit . "</td>
                                <td>₱" . number_format($row['cost_per_unit'], 2) . "</td>
                                <td><strong>₱" . number_format($row['total_value'], 2) . "</strong></td>
                                <td><strong>{$row['stock_status']}</strong></td>
                                <td  class='center'>
                                    <button class='btn-edit' 
                                           onclick='openRestockModal({$row['item_id']}, \"{$safeItemName}\")'>
                                        Restock
                                    </button>
                                    <button class='btn-connect' 
                                           onclick='openConnectModal({$row['item_id']}, \"{$safeItemName}\", {$row['is_liquid']})'>
                                         Connect
                                    </button>
                                    <button class='btn-edit-item' 
                                           onclick='openEditModal({$row['item_id']}, \"{$safeItemName}\", \"{$safeCategory}\", {$row['current_quantity']}, \"{$safeUnit}\", {$row['cost_per_unit']}, {$row['is_liquid']}, \"\")'>
                                        Edit
                                    </button>
                                    <button class='btn-delete' 
                                           onclick='openDeleteModal({$row['item_id']}, \"{$safeItemName}\")'>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            ";
                        }
                        
                        if ($row_count === 0) {
                            echo "<tr><td colspan='10' class='no-results'>No solid items found.</td></tr>";
                        }
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div class="modal-overlay" id="restock-overlay"></div>
    <div class="modal-overlay" id="add-stock-overlay"></div>
    <div class="modal-overlay" id="connect-overlay"></div>
    <div class="modal-overlay" id="edit-overlay"></div>
    <div class="modal-overlay" id="delete-overlay"></div>

   
    <div class="modal modal-small" id="restock-modal">
        <div class="modal-header">
            <h3 class="modal-title"> Restock Item</h3>
            <p class="modal-subtitle">Add quantity to existing inventory</p>
        </div>
        
        <form method="POST" action="" id="form-restock">
            <input type="hidden" name="action" value="restock_item">
            <div class="form-group">
                <label class="form-label"><strong>Item ID:</strong></label>
                <input type="text" class="form-input" name="item_id" id="restock_item_id" readonly>
            </div>
            
            <div class="form-group">
                <label class="form-label"><strong>Item Name:</strong></label>
                <input type="text" class="form-input" name="item_name" id="restock_item_name" readonly>
            </div>
            
            <div class="form-group">
                <label class="form-label"><strong>Quantity to Add:</strong></label>
                <input type="number" class="form-input" name="restock_quantity" step="0.001" min="0.001" required>
            </div>
        </form>
        
        <div class="modal-buttons">
            <button type="button" class="btn btn-cancel" onclick="closeRestockModal()">&nbsp ✖ &nbsp Cancel</button>
            <button type="submit" form="form-restock" class="btn">&nbsp ✅ &nbsp Restock</button>
        </div>
    </div>

   
    <div class="modal modal-small" id="edit-modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Item</h3>
            <p class="modal-subtitle">Update item details</p>
        </div>
        
        <form method="POST" action="" id="form-edit">
            <input type="hidden" name="action" value="edit_item">
            
            <div class="form-group">
                <label class="form-label"><strong>Item ID:</strong></label>
                <input type="text" class="form-input" name="item_id" id="edit_item_id" readonly>
            </div>
            
            <div class="form-group">
                <label class="form-label"><strong>Item Name:</strong></label>
                <input type="text" class="form-input" name="item_name" id="edit_item_name" required>
            </div>
            
            <div class="form-group">
                <label class="form-label"><strong>Cost per Unit (₱):</strong></label>
                <input type="number" class="form-input" name="cost_per_unit" id="edit_cost_per_unit" step="0.01" min="0.01" required>
            </div>

        
            <div class="form-group" id="expiration-date-group" style="display: none;">
                <label class="form-label"><strong>Expiration Date:</strong></label>
                <input type="date" class="form-input" name="expiration_date" id="edit_expiration_date">
            </div>
        </form>
        
        <div class="modal-buttons">
            <button type="button" class="btn btn-cancel" onclick="closeEditModal()">&nbsp ✖ &nbsp Cancel</button>
            <button type="submit" form="form-edit" class="btn">&nbsp 💾 &nbsp Save Changes</button>
        </div>
    </div>

   
    <div class="modal modal-small" id="delete-modal">
        <div class="modal-header">
            <h3 class="modal-title">🗑️ Delete Item</h3>
            <p class="modal-subtitle">Are you sure you want to delete this item?</p>
        </div>
        
        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
            <p style="color: #856404; margin: 0;"><strong>⚠️ Warning:</strong> This action cannot be undone. The item and all its menu connections will be on Inventory archieve.</p>
        </div>
        
        <form method="POST" action="" id="form-delete">
            <input type="hidden" name="action" value="delete_item">
            <input type="hidden" name="item_id" id="delete_item_id">
            
            <div class="form-group">
                <label class="form-label"><strong>Item Name:</strong></label>
                <input type="text" class="form-input" id="delete_item_name" readonly style="background: #f5f5f5;">
            </div>
        </form>
        
        <div class="modal-buttons">
            <button type="button" class="btn btn-cancel" onclick="closeDeleteModal()">&nbsp ✖ &nbsp Cancel</button>
            <button type="submit" form="form-delete" class="btn" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">&nbsp 🗑️ &nbsp Delete Item</button>
        </div>
    </div>

    <div class="modal modal-large" id="connect-modal">
        <div class="modal-header">
            <h3 class="modal-title"> Connect Stock to Menu Items</h3>
            <p class="modal-subtitle">Link this ingredient to menu items and specify quantities</p>
        </div>
        
        <form method="POST" action="" id="form-connect">
            <input type="hidden" name="action" value="connect_to_menu">
            <input type="hidden" name="item_id" id="connect_item_id">
            <input type="hidden" name="item_name" id="connect_item_name">
            <input type="hidden" name="is_liquid" id="connect_is_liquid">
            
            <div class="form-group">
                <label class="form-label"><strong>Connecting Item:</strong></label>
                <input type="text" class="form-input" id="connect_item_display" readonly>
            </div>

       
            <div class="menu-connection-section">
                <h4 style="color: #4a90e2; margin-bottom: 15px; font-weight: 700;">🍽️ Select Menu Items</h4>
                <p style="color: #666; margin-bottom: 15px; font-size: 0.9rem;">
                    Choose which menu items use this ingredient and specify the quantity needed per serving.
                </p>
                
                <div id="connect-menu-items-container" style="max-height: 300px; overflow-y: auto;">
                    <?php
                 
                    $menu_items_query = "SELECT DISTINCT 
                                            menu_item_id, 
                                            menu_item_name, 
                                            category, 
                                            section, 
                                            size_id,
                                            price
                                        FROM unified_menu_system 
                                        WHERE is_available = 1 
                                        AND menu_item_id IS NOT NULL 
                                        AND menu_item_name IS NOT NULL
                                        ORDER BY section, category, menu_item_name, 
                                        CASE size_id 
                                            WHEN 'REG' THEN 1 
                                            WHEN 'GRANDE' THEN 2 
                                            WHEN 'VENTI' THEN 3 
                                            ELSE 4 
                                        END";
                    $menu_items_result = $connection->query($menu_items_query);
                    
                    if ($menu_items_result) {
                        $current_section = '';
                        $current_category = '';
                        
                        while ($menu_item = $menu_items_result->fetch_assoc()) {
                         
                            if ($current_section !== $menu_item['section']) {
                                $current_section = $menu_item['section'];
                                echo "<div class='menu-section-header'>" . strtoupper(htmlspecialchars($current_section)) . "</div>";
                                $current_category = ''; 
                            }
                            
                            
                            if ($current_category !== $menu_item['category']) {
                                $current_category = $menu_item['category'];
                                echo "<div style='font-weight: 600; color: #337609; margin: 8px 0 5px 10px; font-size: 0.85rem;'>" . 
                                     ucwords(str_replace('_', ' ', strtolower(htmlspecialchars($menu_item['category'])))) . "</div>";
                            }
                            
                            $menu_key = $menu_item['menu_item_id'] . '_' . $menu_item['size_id'];
                            $display_name = htmlspecialchars($menu_item['menu_item_name']) . 
                                           ($menu_item['size_id'] !== 'REG' ? ' (' . htmlspecialchars($menu_item['size_id']) . ')' : '') .
                                           ' - ₱' . number_format($menu_item['price'], 2);
                            
                            echo "<div class='menu-item-row'>
                                    <input type='checkbox' class='menu-item-checkbox connect-checkbox' 
                                           name='menu_items[]' 
                                           value='" . htmlspecialchars($menu_key) . "'
                                           id='connect_menu_" . htmlspecialchars($menu_key) . "'
                                           onchange='toggleConnectQuantityInput(this)'>
                                    <label for='connect_menu_" . htmlspecialchars($menu_key) . "' class='menu-item-name'>{$display_name}</label>
                                    <input type='number' 
                                           class='quantity-input connect-quantity' 
                                           name='quantities[" . htmlspecialchars($menu_key) . "]' 
                                           step='0.001' 
                                           min='0.001' 
                                           placeholder='0.000'
                                           disabled
                                           title='Quantity per serving'>
                                    <span style='font-size: 0.75rem; color: #666;'>per serving</span>
                                  </div>";
                        }
                    } else {
                        echo "<div style='color: #666; text-align: center; padding: 20px;'>No menu items available for connection.</div>";
                    }
                    ?>
                </div>
            </div>
        </form>
        
        <div class="modal-buttons">
            <button type="button" class="btn btn-cancel" onclick="closeConnectModal()">&nbsp ✖ &nbsp Cancel</button>
            <button type="submit" form="form-connect" class="btn btn-connect">&nbsp ⇄ &nbsp Connect to Menu</button>
        </div>
    </div>


    <div class="modal modal-large" id="add-stock-modal">
        <div class="modal-header">
            <h3 class="modal-title">➕ Add New Stock Item</h3>
        </div>
        
        <form method="POST" action="" id="form-add-stock">
            <input type="hidden" name="action" value="add_stock">
            
            <div class="ingredient-details-section">
                <h4 style="color: #ff6b35; margin-bottom: 20px; font-weight: 700;"> Ingredient Details</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><strong>Item Name:</strong></label>
                        <input type="text" class="form-input" name="item_name" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><strong>Category:</strong></label>
                        <select name="category" class="form-select" required onchange="handleCategoryChange(this, 'new-category-row')">
                            <option value="">Select Category</option>
                            <?php foreach ($inventory_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                            <option value="NEW_CATEGORY">➕ Add New Category</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="new-category-row" style="display: none;">
                    <label class="form-label"><strong>New Category Name:</strong></label>
                    <input type="text" class="form-input" name="new_category" maxlength="50">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><strong>Initial Quantity:</strong></label>
                        <input type="number" class="form-input" name="current_quantity" step="0.001" min="0.001" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><strong>Unit:</strong></label>
                        <select name="unit" class="form-select" required onchange="handleCategoryChange(this, 'new-unit-row')">
                            <option value="">Select Unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo htmlspecialchars($unit); ?>"><?php echo htmlspecialchars($unit); ?></option>
                            <?php endforeach; ?>
                            <option value="NEW_UNIT">➕ Add New Unit</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="new-unit-row" style="display: none;">
                    <label class="form-label"><strong>New Unit Name:</strong></label>
                    <input type="text" class="form-input" name="new_unit" maxlength="20">
                </div>

                <div class="form-row">
                   
                    <div class="form-group">
                        <label class="form-label"><strong>Cost per Unit (₱):</strong></label>
                        <input type="number" class="form-input" name="cost_per_unit" step="0.01" min="0.01" required>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" class="checkbox-input" name="is_liquid" id="is_liquid" value="1" onchange="toggleExpirationField()">
                    <label for="is_liquid" class="form-label"><strong>Is this a liquid item?</strong></label>
                </div>

                
                <div class="form-group" id="add-expiration-group" style="display: none;">
                    <label class="form-label"><strong>Expiration Date:</strong></label>
                    <input type="date" class="form-input" name="expiration_date" id="add_expiration_date">
                </div>
            </div>

  
            <div class="menu-connection-section">
                <h4 style="color: #4a90e2; margin-bottom: 15px; font-weight: 700;">🍽️ Connect to Menu Items</h4>
                <p style="color: #666; margin-bottom: 15px; font-size: 0.9rem;">
                    Select which menu items will use this ingredient and specify the quantity needed per serving.
                </p>
                
                <div id="menu-items-container" style="max-height: 300px; overflow-y: auto;">
                    <?php
                 
                    $menu_items_query = "SELECT DISTINCT 
                                            menu_item_id, 
                                            menu_item_name, 
                                            category, 
                                            section, 
                                            size_id,
                                            price
                                        FROM unified_menu_system 
                                        WHERE is_available = 1 
                                        AND menu_item_id IS NOT NULL 
                                        AND menu_item_name IS NOT NULL
                                        ORDER BY section, category, menu_item_name, 
                                        CASE size_id 
                                            WHEN 'REG' THEN 1 
                                            WHEN 'GRANDE' THEN 2 
                                            WHEN 'VENTI' THEN 3 
                                            ELSE 4 
                                        END";
                    $menu_items_result = $connection->query($menu_items_query);
                    
                    if ($menu_items_result) {
                        $current_section = '';
                        $current_category = '';
                        
                        while ($menu_item = $menu_items_result->fetch_assoc()) {
                        
                            if ($current_section !== $menu_item['section']) {
                                $current_section = $menu_item['section'];
                                echo "<div class='menu-section-header'>" . strtoupper(htmlspecialchars($current_section)) . "</div>";
                                $current_category = ''; 
                            }
                         
                            if ($current_category !== $menu_item['category']) {
                                $current_category = $menu_item['category'];
                                echo "<div style='font-weight: 600; color: #337609; margin: 8px 0 5px 10px; font-size: 0.85rem;'>" . 
                                     ucwords(str_replace('_', ' ', strtolower(htmlspecialchars($menu_item['category'])))) . "</div>";
                            }
                            
                            $menu_key = $menu_item['menu_item_id'] . '_' . $menu_item['size_id'];
                            $display_name = htmlspecialchars($menu_item['menu_item_name']) . 
                                           ($menu_item['size_id'] !== 'REG' ? ' (' . htmlspecialchars($menu_item['size_id']) . ')' : '') .
                                           ' - ₱' . number_format($menu_item['price'], 2);
                            
                            echo "<div class='menu-item-row'>
                                    <input type='checkbox' class='menu-item-checkbox' 
                                           name='menu_items[]' 
                                           value='" . htmlspecialchars($menu_key) . "'
                                           id='menu_" . htmlspecialchars($menu_key) . "'
                                           onchange='toggleQuantityInput(this)'>
                                    <label for='menu_" . htmlspecialchars($menu_key) . "' class='menu-item-name'>{$display_name}</label>
                                    <input type='number' 
                                           class='quantity-input' 
                                           name='quantities[" . htmlspecialchars($menu_key) . "]' 
                                           step='0.001' 
                                           min='0.001' 
                                           placeholder='0.000'
                                           disabled
                                           title='Quantity per serving'>
                                    <span style='font-size: 0.75rem; color: #666;'>per serving</span>
                                  </div>";
                        }
                    } else {
                        echo "<div style='color: #666; text-align: center; padding: 20px;'>No menu items available for connection.</div>";
                    }
                    ?>
                </div>
            </div>
        </form>
        
        <div class="modal-buttons">
            <button type="button" class="btn btn-cancel" onclick="closeAddStockModal()">&nbsp ✖ &nbsp Cancel</button>
            <button type="submit" form="form-add-stock" class="btn">&nbsp ➕ &nbsp Add Stock</button>
        </div>
    </div>


<script>

function toggleExpirationField() {
    const isLiquid = document.getElementById('is_liquid').checked;
    const expirationGroup = document.getElementById('add-expiration-group');
    if (isLiquid) {
        expirationGroup.style.display = 'block';

        const today = new Date().toISOString().split('T')[0];
        document.getElementById('add_expiration_date').value = today;
    } else {
        expirationGroup.style.display = 'none';
        document.getElementById('add_expiration_date').value = '';
    }
}

function openRestockModal(itemId, itemName) {
    document.getElementById("restock_item_id").value = itemId;
    document.getElementById("restock_item_name").value = itemName;
    document.getElementById("restock-overlay").classList.add("show1");
    document.getElementById("restock-modal").classList.add("show1");
}

function closeRestockModal() {
    document.getElementById("restock-overlay").classList.remove("show1");
    document.getElementById("restock-modal").classList.remove("show1");
    document.getElementById("form-restock").reset();
}

function openEditModal(itemId, itemName, category, currentQuantity, unit, costPerUnit, isLiquid, expirationDate) {
    document.getElementById("edit_item_id").value = itemId;
    document.getElementById("edit_item_name").value = itemName;
    document.getElementById("edit_cost_per_unit").value = costPerUnit;
    
 
    const expirationGroup = document.getElementById("expiration-date-group");
    if (isLiquid === 1) {
        expirationGroup.style.display = "block";
        
        document.getElementById("edit_expiration_date").value = expirationDate ? expirationDate : '';
    } else {
        expirationGroup.style.display = "none";
        document.getElementById("edit_expiration_date").value = '';
    }
    
    document.getElementById("edit-overlay").classList.add("show1");
    document.getElementById("edit-modal").classList.add("show1");
}

function closeEditModal() {
    document.getElementById("edit-overlay").classList.remove("show1");
    document.getElementById("edit-modal").classList.remove("show1");
    document.getElementById("form-edit").reset();
}

function openDeleteModal(itemId, itemName) {
    document.getElementById("delete_item_id").value = itemId;
    document.getElementById("delete_item_name").value = itemName;
    document.getElementById("delete-overlay").classList.add("show1");
    document.getElementById("delete-modal").classList.add("show1");
}

function closeDeleteModal() {
    document.getElementById("delete-overlay").classList.remove("show1");
    document.getElementById("delete-modal").classList.remove("show1");
}

function openConnectModal(itemId, itemName, isLiquid) {
    document.getElementById("connect_item_id").value = itemId;
    document.getElementById("connect_item_name").value = itemName;
    document.getElementById("connect_is_liquid").value = isLiquid;
    document.getElementById("connect_item_display").value = (isLiquid ? "💧 " : "📦 ") + itemName;
    document.getElementById("connect-overlay").classList.add("show1");
    document.getElementById("connect-modal").classList.add("show1");
}

function closeConnectModal() {
    document.getElementById("connect-overlay").classList.remove("show1");
    document.getElementById("connect-modal").classList.remove("show1");
    document.getElementById("form-connect").reset();
    document.querySelectorAll('.connect-quantity').forEach(input => {
        input.disabled = true;
        input.required = false;
        input.value = '';
    });
    document.querySelectorAll('.connect-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
}

function toggleConnectQuantityInput(checkbox) {
    const menuKey = checkbox.value;
    const quantityInput = document.querySelector(`input[name="quantities[${menuKey}]"].connect-quantity`);
    if (quantityInput) {
        if (checkbox.checked) {
            quantityInput.disabled = false;
            quantityInput.required = true;
            quantityInput.focus();
        } else {
            quantityInput.disabled = true;
            quantityInput.required = false;
            quantityInput.value = '';
        }
    }
}

function openAddStockModal() {
    document.getElementById("add-stock-overlay").classList.add("show1");
    document.getElementById("add-stock-modal").classList.add("show1");
}

function closeAddStockModal() {
    document.getElementById("add-stock-overlay").classList.remove("show1");
    document.getElementById("add-stock-modal").classList.remove("show1");
    document.getElementById("form-add-stock").reset();
    const newCat = document.getElementById("new-category-row");
    const newUnit = document.getElementById("new-unit-row");
    const expGroup = document.getElementById("add-expiration-group");
    if (newCat) newCat.style.display = "none";
    if (newUnit) newUnit.style.display = "none";
    if (expGroup) expGroup.style.display = "none";
    document.querySelectorAll('.quantity-input:not(.connect-quantity)').forEach(input => {
        input.disabled = true;
        input.required = false;
        input.value = '';
    });
    document.querySelectorAll('.menu-item-checkbox:not(.connect-checkbox)').forEach(checkbox => {
        checkbox.checked = false;
    });
}

function toggleQuantityInput(checkbox) {
    const menuKey = checkbox.value;
    const quantityInput = document.querySelector(`input[name="quantities[${menuKey}]"]:not(.connect-quantity)`);
    if (quantityInput) {
        if (checkbox.checked) {
            quantityInput.disabled = false;
            quantityInput.required = true;
            quantityInput.focus();
        } else {
            quantityInput.disabled = false;
            quantityInput.required = false;
            quantityInput.value = '';
        }
    }
}

function handleCategoryChange(select, targetId) {
    const target = document.getElementById(targetId);
    if (!target) return;

    if (select.value === "NEW_CATEGORY" || select.value === "NEW_UNIT") {
        target.style.display = "block";
        target.querySelector("input").required = true;
    } else {
        target.style.display = "none";
        const input = target.querySelector("input");
        if (input) {
            input.required = false;
            input.value = "";
        }
    }
}

const RealtimeInventory = {
    updateInterval: 5000,
    isUpdating: false,
    intervalId: null,
    openModals: new Set(),

    init: function() {
        console.log('[v0] Initializing real-time inventory system');
        this.startPolling();
        this.setupModalTracking();
        this.bindFilterEvents();
    },

    setupModalTracking: function() {
        const modals = [
            'restock-modal', 'edit-modal', 'delete-modal',
            'connect-modal', 'add-stock-modal'
        ];

        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                const observer = new MutationObserver(() => {
                    if (modal.classList.contains('show1')) {
                        this.openModals.add(modalId);
                    } else {
                        this.openModals.delete(modalId);
                    }
                });

                observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
            }
        });
    },

bindFilterEvents: function() {
    console.log("[v0] bindFilterEvents called");
    const searchInput = document.getElementById("searchInput");


    if (searchInput && !searchInput.dataset.bound) {
        searchInput.dataset.bound = "true";
        let timer;
        searchInput.addEventListener("input", () => {
            clearTimeout(timer);
            timer = setTimeout(() => this.updateInventory(), 250);
        });
        console.log("[v0] Search input listener bound");
    }


    const categoryButtons = document.querySelectorAll(".category-btn");
    console.log("[v0] Found category buttons:", categoryButtons.length);
    
    categoryButtons.forEach((btn, index) => {
        if (!btn.dataset.bound) {
            btn.dataset.bound = "true";
            btn.addEventListener("click", () => {
                console.log("[v0] Category button clicked:", btn.dataset.category);
                document.querySelectorAll(".category-btn").forEach(b => b.classList.remove("active"));
                btn.classList.add("active");
                console.log("[v0] Calling updateInventory from category button");
                this.updateInventory();
            });
            console.log("[v0] Bound listener to button", index, ":", btn.dataset.category);
        }
    });
},



    startPolling: function() {
        if (this.intervalId) clearInterval(this.intervalId);

        this.intervalId = setInterval(() => {
            this.updateInventory();
        }, this.updateInterval);

        this.updateInventory();
    },

    stopPolling: function() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    },

    updateInventory: function() {
    console.log("[v0] updateInventory called");
    if (this.isUpdating) {
        console.log("[v0] Already updating, skipping");
        return;
    }
    this.isUpdating = true;

    const search = document.getElementById("searchInput")?.value || '';
    const activeBtn = document.querySelector(".category-btn.active");
    const category = activeBtn?.dataset.category === "all" ? "" : (activeBtn?.dataset.category || "");
    
    console.log("[v0] updateInventory - search:", search);
    console.log("[v0] updateInventory - activeBtn:", activeBtn?.dataset.category);
    console.log("[v0] updateInventory - category:", category);

 
    this.fetchStats();


    console.log("[v0] Calling fetchLiquidInventory with search:", search, "category:", category);
    this.fetchLiquidInventory(search, category);
    
    console.log("[v0] Calling fetchSolidInventory with search:", search, "category:", category);
    this.fetchSolidInventory(search, category);

    this.isUpdating = false;
},

    fetchStats: function() {
        fetch('inventory_realtime_api.php?action=get_inventory_stats')
            .then(response => response.json())
            .then(data => {
                this.updateStatsDisplay(data);
            })
            .catch(error => console.error('[v0] Error fetching stats:', error));
    },

    updateStatsDisplay: function(data) {
        if (!data || !data.liquid_stats || !data.solid_stats) {
            console.error('[v0] Invalid data structure for stats:', data);
            return;
        }

        const liquidStats = data.liquid_stats;
        const solidStats = data.solid_stats;

        const allStatsSections = document.querySelectorAll('.stats-container');

        if (allStatsSections.length >= 1) {
            const liquidCards = allStatsSections[0].querySelectorAll('.stat-card');
            if (liquidCards.length >= 4) {
                liquidCards[0].querySelector('.stat-number').textContent = liquidStats.total_items || 0;
                liquidCards[1].querySelector('.stat-number').textContent = '₱' + (liquidStats.total_value ? Math.round(liquidStats.total_value) : 0).toLocaleString();
                liquidCards[2].querySelector('.stat-number').textContent = liquidStats.critical_items || 0;
                liquidCards[3].querySelector('.stat-number').textContent = liquidStats.low_items || 0;
            }
        }

        if (allStatsSections.length >= 2) {
            const solidCards = allStatsSections[1].querySelectorAll('.stat-card');
            if (solidCards.length >= 4) {
                solidCards[0].querySelector('.stat-number').textContent = solid_stats_or_zero(solidStats.total_items);
                solidCards[1].querySelector('.stat-number').textContent = '₱' + (solidStats.total_value ? Math.round(solidStats.total_value) : 0).toLocaleString();
                solidCards[2].querySelector('.stat-number').textContent = solidStats.critical_items || 0;
                solidCards[3].querySelector('.stat-number').textContent = solidStats.low_items || 0;
            }
        }

        function solid_stats_or_zero(v) { return v || 0; }
    },

    fetchLiquidInventory: function(search, category) {
    const url = `inventory_realtime_api.php?action=get_liquid_inventory&search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}`;
    console.log("[v0] Liquid API URL:", url);
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            console.log("[v0] Liquid API Response:", data);
            console.log("[v0] Liquid items count:", data.items ? data.items.length : 0);
            if (data.items && data.items.length > 0) {
                console.log("[v0] First item:", data.items[0]);
            }
            this.updateTableRows('liquid-inventory-tbody', data.items);
        })
        .catch(err => console.error("[v0] Liquid fetch error:", err));
},

    fetchSolidInventory: function(search, category) {
    const url = `inventory_realtime_api.php?action=get_solid_inventory&search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}`;
    console.log("[v0] Solid API URL:", url);
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            console.log("[v0] Solid API Response:", data);
            console.log("[v0] Solid items count:", data.items ? data.items.length : 0);
            if (data.items && data.items.length > 0) {
                console.log("[v0] First item:", data.items[0]);
            }
            this.updateTableRows('solid-inventory-tbody', data.items);
        })
        .catch(err => console.error("[v0] Solid fetch error:", err));
},

    sortByStatus: function(items) {
        const statusPriority = { 'CRITICAL': 1, 'LOW': 2, 'GOOD': 3 };
        return items.sort((a, b) => {
            const priorityA = statusPriority[a.status] || 999;
            const priorityB = statusPriority[b.status] || 999;
            if (priorityA !== priorityB) {
                return priorityA - priorityB;
            }
            return a.item_name.localeCompare(b.item_name);
        });
    },

    updateTableRows: function(tableBodyId, items) {
    const tbody = document.getElementById(tableBodyId);
    if (!tbody) {
        console.error('[v0] Table body not found:', tableBodyId);
        return;
    }

    tbody.innerHTML = '';

    const isLiquidTable = tableBodyId === 'liquid-inventory-tbody';
    const colspanValue = isLiquidTable ? 11 : 10;

    if (!items || items.length === 0) {
        tbody.innerHTML = `<tr><td colspan='${colspanValue}' class='no-results'>No items found.</td></tr>`;
        return;
    }

    const sortedItems = this.sortByStatus(items);

    sortedItems.forEach(item => {
        const row = document.createElement('tr');
        if (item.status === 'CRITICAL') {
            row.className = 'critical-stock-row';
        } else if (item.status === 'LOW') {
            row.className = 'low-stock-row';
        } else {
            row.className = 'good-stock-row';
        }

        const usedInMenuDisplay = item.used_in_menu_display || 'Not used in menu';

        let expirationHtml = '';
        if (isLiquidTable && item.expiration_date) {
            const expDate = new Date(item.expiration_date);
            const today = new Date();
            const timeDiff = expDate.getTime() - today.getTime();
            const daysRemaining = Math.ceil(timeDiff / (1000 * 3600 * 24));

            let expClass = 'expiration-na';
            let expText = 'N/A';

            if (daysRemaining < 0) {
                expClass = 'expiration-expired';
                expText = 'EXPIRED';
            } else if (daysRemaining <= 3) {
                expClass = 'expiration-critical';
                expText = daysRemaining + ' days left';
            } else if (daysRemaining <= 20) {
                expClass = 'expiration-warning';
                expText = daysRemaining + ' days left';
            } else {
                expClass = 'expiration-good';
                expText = expDate.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
            }
            expirationHtml = `<span class="${expClass}">${expText}</span>`;
        } else if (isLiquidTable && !item.expiration_date) {
             expirationHtml = `<span class="expiration-na">N/A</span>`;
        }


        const safeName = (item.item_name + '').replace(/'/g, "\\'");
        const safeCategory = (item.category + '').replace(/'/g, "\\'");
        const safeUnit = (item.unit + '').replace(/'/g, "\\'");
        const safeExpirationDate = (item.expiration_date || '').replace(/'/g, "\\'");


        let rowHtml = `
            <td><strong>${item.id}</strong></td>
            <td>${safeName}</td>
            <td><span class="category-tag">${safeCategory}</span></td>
            <td><small style='color: #666;'>${usedInMenuDisplay}</small></td>
            <td><strong>${item.current_quantity}</strong></td>
            <td>${safeUnit}</td>
            <td>₱${parseFloat(item.cost_per_unit).toFixed(2)}</td>
            <td>₱${parseFloat(item.total_value).toFixed(2)}</td>
            ${isLiquidTable ? `<td>${expirationHtml}</td>` : ''}
            <td><span class="status-badge ${item.status.toLowerCase()}">${item.status}</span></td>
            <td class='center'>
                <button class="btn-edit" onclick="openRestockModal(${item.id}, '${safeName}')">Restock</button>
                <button class="btn-connect" onclick="openConnectModal(${item.id}, '${safeName}', ${item.is_liquid})">Connect</button>
                <button class="btn-edit-item" onclick="openEditModal(${item.id}, '${safeName}', '${safeCategory}', ${item.current_quantity}, '${safeUnit}', ${item.cost_per_unit}, ${item.is_liquid}, '${safeExpirationDate}')">Edit</button>
                <button class="btn-delete" onclick="openDeleteModal(${item.id}, '${safeName}')">Delete</button>
            </td>
        `;

        row.innerHTML = rowHtml;
        tbody.appendChild(row);
    });
}
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        RealtimeInventory.init();
    });
} else {
    RealtimeInventory.init();
}

</script>


</body>
</html>
