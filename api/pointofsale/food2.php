<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';

include "db_conn.php";


// Set charset to utf8
$conn->set_charset("utf8");

// Initialize messages
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_category':
                $category_name = trim($_POST['category_name']);
                $section = $_POST['section'];
                
                if (empty($category_name)) {
                    $error_message = "Category name cannot be empty!";
                } else {
                    // Check if category already exists in this section
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM unified_menu_system WHERE category = ? AND section = ?");
                    $check_stmt->bind_param("ss", $category_name, $section);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $exists = $check_result->fetch_assoc()['count'] > 0;
                    $check_stmt->close();
                    
                    if ($exists) {
                        $error_message = "Category '$category_name' already exists in " . ucfirst($section) . " section!";
                    } else {
                        // Create a placeholder item to establish the category
                        $placeholder_id = strtoupper(str_replace([' ', '-', '.'], '', $category_name)) . '_PLACEHOLDER';
                        $placeholder_name = $category_name . ' - Sample Item';
                        $size_id = 'REG';
                        $price = 0.00;
                        $description = 'This is a placeholder item. Please add your actual items and delete this one.';
                        
                        $stmt = $conn->prepare("INSERT INTO unified_menu_system (menu_item_id, menu_item_name, category, section, size_id, price, description, is_available, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())");
                        $stmt->bind_param("sssssds", $placeholder_id, $placeholder_name, $category_name, $section, $size_id, $price, $description);
                        
                        if ($stmt->execute()) {
                            $success_message = "Category '$category_name' created successfully in " . ucfirst($section) . " section! A placeholder item has been added - you can now add your actual items and delete the placeholder.";
                        } else {
                            $error_message = "Error creating category: " . $conn->error;
                        }
                        $stmt->close();
                    }
                }
                break;
                
            case 'add_item':
                $menu_item_id = strtoupper(str_replace([' ', '-', '.'], '', $_POST['name']));
                $name = trim($_POST['name']);
                $category = $_POST['category'];
                $section = $_POST['section']; // Now properly using the section from form
                $description = trim($_POST['description'] ?? '');
                
                if ($section === 'drinks' && isset($_POST['sizes']) && is_array($_POST['sizes'])) {
                    $sizes = $_POST['sizes'];
                    $prices = $_POST['prices'];
                    
                    // Check if item already exists
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM unified_menu_system WHERE menu_item_id = ?");
                    $check_stmt->bind_param("s", $menu_item_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $exists = $check_result->fetch_assoc()['count'] > 0;
                    $check_stmt->close();
                    
                    if ($exists) {
                        $error_message = "Item with this name already exists!";
                    } else {
                        $success = true;
                        foreach ($sizes as $index => $size) {
                            if (!empty($size) && !empty($prices[$index])) {
                                $price = floatval($prices[$index]);
                                $stmt = $conn->prepare("INSERT INTO unified_menu_system (menu_item_id, menu_item_name, category, section, size_id, price, description, is_available, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                                $stmt->bind_param("sssssds", $menu_item_id, $name, $category, $section, $size, $price, $description);
                                
                                if (!$stmt->execute()) {
                                    $success = false;
                                    $error_message = "Error adding item: " . $conn->error;
                                    break;
                                }
                                $stmt->close();
                            }
                        }
                        
                        if ($success) {
                            $success_message = "Drink '$name' added successfully with multiple sizes!";
                        }
                    }
                } else {
                    $price = floatval($_POST['price']);
                    $size_id = 'REG'; // Default size for food and addons
                    
                    // Check if item already exists
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM unified_menu_system WHERE menu_item_id = ? AND size_id = ?");
                    $check_stmt->bind_param("ss", $menu_item_id, $size_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $exists = $check_result->fetch_assoc()['count'] > 0;
                    $check_stmt->close();
                    
                    if ($exists) {
                        $error_message = "Item with this name already exists!";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO unified_menu_system (menu_item_id, menu_item_name, category, section, size_id, price, description, is_available, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                        $stmt->bind_param("sssssds", $menu_item_id, $name, $category, $section, $size_id, $price, $description);
                        
                        if ($stmt->execute()) {
                            $success_message = "Item '$name' added successfully!";
                        } else {
                            $error_message = "Error adding item: " . $conn->error;
                        }
                        $stmt->close();
                    }
                }
                break;
                
            case 'delete_item':
                if (!isset($_POST['menu_item_id']) || empty($_POST['menu_item_id'])) {
                    $error_message = "Invalid item ID provided.";
                    break;
                }
                
                $menu_item_id = $_POST['menu_item_id'];
                
                // Get item name for confirmation message
                $name_stmt = $conn->prepare("SELECT menu_item_name FROM unified_menu_system WHERE menu_item_id = ? LIMIT 1");
                $name_stmt->bind_param("s", $menu_item_id);
                $name_stmt->execute();
                $name_result = $name_stmt->get_result();
                $item_name = $name_result->fetch_assoc()['menu_item_name'] ?? 'Unknown';
                $name_stmt->close();
                
                $stmt = $conn->prepare("DELETE FROM unified_menu_system WHERE menu_item_id = ?");
                $stmt->bind_param("s", $menu_item_id);
                
                if ($stmt->execute()) {
                    $success_message = "Item '$item_name' deleted successfully!";
                } else {
                    $error_message = "Error deleting item: " . $conn->error;
                }
                $stmt->close();
                break;
                
            case 'update_item':
                if (!isset($_POST['menu_item_id']) || empty($_POST['menu_item_id'])) {
                    $error_message = "Invalid item ID provided.";
                    break;
                }
                
                $menu_item_id = $_POST['menu_item_id'];
                $size_id = $_POST['size_id'];
                $name = trim($_POST['name']);
                $price = floatval($_POST['price']);
                $description = trim($_POST['description'] ?? '');
                $is_available = isset($_POST['is_available']) ? 1 : 0;
                
                $stmt = $conn->prepare("UPDATE unified_menu_system SET menu_item_name = ?, price = ?, description = ?, is_available = ? WHERE menu_item_id = ? AND size_id = ?");
                $stmt->bind_param("sdsiss", $name, $price, $description, $is_available, $menu_item_id, $size_id);
                
                if ($stmt->execute()) {
                    $availability_text = $is_available ? "available" : "unavailable";
                    $success_message = "Item '$name' updated successfully! Item is now $availability_text.";
                } else {
                    $error_message = "Error updating item: " . $conn->error;
                }
                $stmt->close();
                break;
        }
    }
}

function getMenuItems($conn, $section) {
    $stmt = $conn->prepare("
        SELECT 
            menu_item_id,
            menu_item_name, 
            category,
            section,
            size_id,
            price, 
            description, 
            is_available, 
            MIN(created_at) as created_at
        FROM unified_menu_system 
        WHERE section = ?
        GROUP BY menu_item_id, menu_item_name, category, section, size_id, price, description, is_available
        ORDER BY menu_item_name, 
        CASE size_id 
            WHEN 'REG' THEN 1 
            WHEN 'GRANDE' THEN 2 
            WHEN 'VENTI' THEN 3 
            WHEN 'UNLI' THEN 4 
            ELSE 5 
        END
    ");
    $stmt->bind_param("s", $section);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

function getCategoriesBySection($conn, $section) {
    $stmt = $conn->prepare("SELECT DISTINCT category FROM unified_menu_system WHERE section = ? ORDER BY category");
    $stmt->bind_param("s", $section);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
    $stmt->close();
    return $categories;
}

function getMenuStats($conn) {
    $stats = [];
    
    // Total unique items
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT menu_item_id) as total FROM unified_menu_system");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_items'] = $result->fetch_assoc()['total'];
    $stmt->close();
    
    // Available unique items
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT menu_item_id) as available FROM unified_menu_system WHERE is_available = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['available_items'] = $result->fetch_assoc()['available'];
    $stmt->close();
    
    // Categories count
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT category) as categories FROM unified_menu_system");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_categories'] = $result->fetch_assoc()['categories'];
    $stmt->close();
    
    return $stats;
}

$sections = ['drinks', 'food', 'addons'];
$stats = getMenuStats($conn);
$menu_data = [];

foreach ($sections as $section) {
    $categories = getCategoriesBySection($conn, $section);
    $menu_data[$section] = [];
    foreach ($categories as $category) {
        $menu_data[$section][$category] = getMenuItems($conn, $section);
        // Filter items by category
        $menu_data[$section][$category] = array_filter($menu_data[$section][$category], function($item) use ($category) {
            return $item['category'] === $category;
        });
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Menu Management System</title>
     <link rel="icon" href=/media/BUBBLE.jpg>
    
    <link rel="stylesheet" href="/fonts/fonts.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #8b4513 0%, #6d5537 100%);
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-text {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .pos-link {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 25px;
            border-width: 0;
            font-family: Poppins;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .pos-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .header-stats {
            display: flex;
            gap: 30px;
            color: white;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            display: block;
        }

        .stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .main-title {
            text-align: center;
            font-size: 3rem;
            font-weight: 900;
            color: #2c3e50;
            margin: 30px 0;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .container {
            max-width: 95%;
            margin: 0 auto;
            padding: 0 20px;
        }

        .alert {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        /* Updated section tabs styling */
        .section-tabs {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 30px 0;
        }

        .section-tab {
            background: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .section-tab.active {
            background: linear-gradient(135deg, #8b4513 0%, #6d5537 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 69, 19, 0.3);
        }

        .section-content {
            display: none;
        }

        .section-content.active {
            display: block;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .category-section {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .category-header {
            background: linear-gradient(135deg, #8b4513 0%, #6d5537 100%);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-title {
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .add-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .table-container {
            background: white;
            min-height: 200px;
        }

        /* Updated table header to include size column */
        .table-header {
            background: #f8f9fa;
            padding: 15px 20px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;
            gap: 15px;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-row {
            padding: 15px 20px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr;
            gap: 15px;
            align-items: center;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s ease;
        }

        .item-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
        }

        .item-price {
            font-weight: 700;
            color: #28a745;
            font-size: 16px;
        }

        /* Added size badge styling */
        .size-badge {
            background: #e9ecef;
            color: #495057;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .item-description {
            color: #6c757d;
            font-size: 13px;
            font-style: italic;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-available {
            background: #d4edda;
            color: #155724;
        }

        .status-unavailable {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            width: 35px;
            height: 35px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            color: white;
        }

        .btn-edit {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        .modal-header {
            background: linear-gradient(135deg, #8B6F47 0%, #6d5537 100%);
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #8B6F47;
            background: white;
            box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .form-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #8B6F47;
        }

        /* Added size input styling for drinks */
        .size-inputs {
            display: none;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            background: #f8f9fa;
            margin-top: 15px;
        }

        .size-inputs.show {
            display: block;
        }

        .size-row {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 15px;
        }

        .size-row:last-child {
            margin-bottom: 0;
        }

        .size-row input[type="text"] {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .size-row input[type="number"] {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .form-buttons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .btn-primary, .btn-secondary {
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8B6F47 0%, #6d5537 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(139, 111, 71, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 111, 71, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

       

       

       
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .add-category-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
        }

        .add-category-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
        }

        .category-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        
        .search-container {
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .search-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            transition: all 0.3s ease;
            outline: none;
        }

        .search-input:focus {
            border-color: #8B6F47;
            background: white;
            box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.1);
        }

        .search-input::placeholder {
            color: #6c757d;
            font-style: italic;
        }

        .search-results-info {
            margin-top: 10px;
            color: #6c757d;
            font-size: 14px;
            text-align: center;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-style: italic;
        }

        .no-results i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        .highlight {
            background-color: #fad764ff;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 600;
        }

    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <form action="/interface/admin_homepage.php"><button type="submit" class="pos-link"> Back </button></form>

            <div class="header-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['total_items']; ?></span>
                    <span class="stat-label">Total Items</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['available_items']; ?></span>
                    <span class="stat-label">Available</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats['total_categories']; ?></span>
                    <span class="stat-label">Categories</span>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <h1 class="main-title">Menu Management System</h1>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <div class="section-tabs">
            <button class="section-tab active" onclick="showSection('drinks')">
                <i class="fas fa-coffee"></i> Drinks
            </button>
            <button class="section-tab" onclick="showSection('food')">
                <i class="fas fa-utensils"></i> Food
            </button>
            <button class="section-tab" onclick="showSection('addons')">
                <i class="fas fa-plus-circle"></i> Add-ons
            </button>
        </div>

        <?php foreach ($sections as $section): ?>
            <div id="<?php echo $section; ?>-section" class="section-content <?php echo $section === 'drinks' ? 'active' : ''; ?>">
                <!-- Added section header with Add Category button -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-<?php echo $section === 'drinks' ? 'coffee' : ($section === 'food' ? 'utensils' : 'plus-circle'); ?>"></i>
                        <?php echo ucfirst($section); ?> Management
                    </h2>
                    <button class="add-category-btn" onclick="openAddCategoryModal('<?php echo $section; ?>')">
                        <i class="fas fa-folder-plus"></i>
                        Add New Category
                    </button>
                </div>

                <!-- Added search container for each section -->
                <div class="search-container">
                    <input 
                        type="text" 
                        class="search-input" 
                        id="search-<?php echo $section; ?>" 
                        placeholder=" Search <?php echo $section; ?> by name or category " 
                        onkeyup="searchItems('<?php echo $section; ?>')"
                    >
                    <div class="search-results-info" id="results-info-<?php echo $section; ?>"></div>
                </div>
                
                <div class="categories-grid" id="categories-grid-<?php echo $section; ?>">
                    <?php if (empty($menu_data[$section])): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <h2 class="category-title">
                                    <i class="fas fa-<?php echo $section === 'drinks' ? 'coffee' : ($section === 'food' ? 'utensils' : 'plus-circle'); ?>"></i>
                                    <?php echo ucfirst($section); ?>
                                </h2>
                                <button class="add-btn" onclick="openAddModal('DEFAULT', '<?php echo $section; ?>')">
                                    <i class="fas fa-plus"></i>
                                    Add Item
                                </button>
                            </div>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <div>No items found in this section</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($menu_data[$section] as $category => $items): ?>
                            <div class="category-section">
                                <div class="category-header">
                                    <h2 class="category-title">
                                        <i class="fas fa-<?php echo $section === 'drinks' ? 'coffee' : ($section === 'food' ? 'utensils' : 'plus-circle'); ?>"></i>
                                        <?php echo htmlspecialchars($category); ?>
                                    </h2>
                                    <button class="add-btn" onclick="openAddModal('<?php echo htmlspecialchars($category); ?>', '<?php echo $section; ?>')">
                                        <i class="fas fa-plus"></i>
                                        Add Item
                                    </button>
                                </div>
                                <div class="table-container">
                                    <div class="table-header">
                                        <div>Name</div>
                                        <div>Size</div>
                                        <div>Price</div>
                                        <div>Status</div>
                                        <div>Created</div>
                                        <div>Actions</div>
                                    </div>
                                    <?php if (empty($items)): ?>
                                        <div class="empty-state">
                                            <div class="empty-icon">
                                                <i class="fas fa-inbox"></i>
                                            </div>
                                            <div>No items found in this category</div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($items as $item): ?>
                                            <div class="table-row">
                                                <div>
                                                    <div class="item-name"><?php echo htmlspecialchars($item['menu_item_name']); ?></div>
                                                    <?php if ($item['description']): ?>
                                                        <div class="item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <span class="size-badge"><?php echo htmlspecialchars($item['size_id']); ?></span>
                                                </div>
                                                <div class="item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                                                <div>
                                                    <span class="status-badge <?php echo $item['is_available'] ? 'status-available' : 'status-unavailable'; ?>">
                                                        <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
                                                    </span>
                                                </div>
                                                <div><?php echo date('M j, Y', strtotime($item['created_at'])); ?></div>
                                                <div class="action-buttons">
                                                    <button class="btn btn-edit" onclick="openEditModal('<?php echo htmlspecialchars($item['menu_item_id']); ?>', '<?php echo htmlspecialchars($item['size_id']); ?>', '<?php echo htmlspecialchars($item['menu_item_name']); ?>', <?php echo $item['price']; ?>, '<?php echo htmlspecialchars($item['description']); ?>', <?php echo $item['is_available']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                        
                                                    <button class="btn btn-delete" onclick="deleteItem('<?php echo htmlspecialchars($item['menu_item_id']); ?>', '<?php echo htmlspecialchars($item['menu_item_name']); ?>')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Added new modal for adding categories -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Category</h3>
                <span class="close" onclick="closeModal('addCategoryModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <input type="hidden" name="section" id="categorySection">
                    
                    <div class="form-group">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="category_name" class="form-input" required placeholder="Enter category name (e.g., Hot Drinks, Appetizers, Extras)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Section</label>
                        <input type="text" id="categorySectionDisplay" class="form-input" readonly style="background: #f8f9fa; color: #6c757d;">
                    </div>
                    
                    <div class="category-info">
                        <p style="color: #6c757d; font-size: 14px; margin: 15px 0;">
                            <i class="fas fa-info-circle"></i>
                            A placeholder item will be created to establish this category. You can add your actual items and delete the placeholder afterward.
                        </p>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Create Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Item</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="category" id="addCategory">
                    <input type="hidden" name="section" id="addSection">
                    
                    <div class="form-group">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="name" class="form-input" required placeholder="Enter item name">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Section</label>
                        <select name="section_display" id="sectionSelect" class="form-select" onchange="toggleSizeInputs()" disabled>
                            <option value="drinks">Drinks</option>
                            <option value="food">Food</option>
                            <option value="addons">Add-ons</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="singlePriceGroup">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" name="price" class="form-input" step="0.01" min="0" placeholder="0.00">
                    </div>

                    <div class="size-inputs" id="sizeInputs">
                        <label class="form-label">Sizes and Prices</label>
                        <div class="size-row">
                            <input type="text" name="sizes[]" placeholder="Size (e.g., REG)" value="REG">
                            <input type="number" name="prices[]" placeholder="Price" step="0.01" min="0">
                        </div>
                        <div class="size-row">
                            <input type="text" name="sizes[]" placeholder="Size (e.g., GRANDE)" value="GRANDE">
                            <input type="number" name="prices[]" placeholder="Price" step="0.01" min="0">
                        </div>
                        <div class="size-row">
                            <input type="text" name="sizes[]" placeholder="Size (e.g., VENTI)">
                            <input type="number" name="prices[]" placeholder="Price" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description (Optional)</label>
                        <textarea name="description" class="form-textarea" placeholder="Enter item description"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Item</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_item">
                    <input type="hidden" name="menu_item_id" id="editMenuItemId">
                    <input type="hidden" name="size_id" id="editSizeId">
                    
                    <div class="form-group">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="name" id="editName" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" name="price" id="editPrice" class="form-input" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editDescription" class="form-textarea"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-checkbox">
                            <input type="checkbox" name="is_available" id="editAvailable">
                            <label for="editAvailable">Available on Menu</label>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_item">
        <input type="hidden" name="menu_item_id" id="deleteMenuItemId">
    </form>

    <script>
        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.section-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.section-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(section + '-section').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
            
            // Clear search input and reset display for the new section
            const searchInput = document.getElementById(`search-${section}`);
            if (searchInput) {
                searchInput.value = '';
                searchItems(section); // Reset the display
            }
        }

        function openAddModal(category, section) {
            document.getElementById('addCategory').value = category;
            document.getElementById('addSection').value = section;
            document.getElementById('sectionSelect').value = section;
            
            toggleSizeInputs();
            document.getElementById('addModal').style.display = 'block';
        }

        function toggleSizeInputs() {
            const section = document.getElementById('sectionSelect').value;
            const sizeInputs = document.getElementById('sizeInputs');
            const singlePriceGroup = document.getElementById('singlePriceGroup');
            
            if (section === 'drinks') {
                sizeInputs.classList.add('show');
                singlePriceGroup.style.display = 'none';
                // Make size inputs required
                document.querySelectorAll('#sizeInputs input[name="prices[]"]').forEach(input => {
                    input.required = false; // Will be handled by form validation
                });
            } else {
                sizeInputs.classList.remove('show');
                singlePriceGroup.style.display = 'block';
                document.querySelector('input[name="price"]').required = true;
            }
        }

        function openEditModal(menuItemId, sizeId, name, price, description, isAvailable) {
            document.getElementById('editMenuItemId').value = menuItemId;
            document.getElementById('editSizeId').value = sizeId;
            document.getElementById('editName').value = name;
            document.getElementById('editPrice').value = price;
            document.getElementById('editDescription').value = description || '';
            document.getElementById('editAvailable').checked = isAvailable == 1;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function deleteItem(menuItemId, name) {
            if (confirm(`Are you sure you want to delete "${name}"? This will remove ALL sizes of this item from the system.`)) {
                document.getElementById('deleteMenuItemId').value = menuItemId;
                document.getElementById('deleteForm').submit();
            }
        }

        function openAddCategoryModal(section) {
            document.getElementById('categorySection').value = section;
            document.getElementById('categorySectionDisplay').value = section.charAt(0).toUpperCase() + section.slice(1);
            document.getElementById('addCategoryModal').style.display = 'block';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            const addCategoryModal = document.getElementById('addCategoryModal'); // Added category modal
            
            if (event.target == addModal) {
                addModal.style.display = 'none';
            }
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            if (event.target == addCategoryModal) { // Added category modal close
                addCategoryModal.style.display = 'none';
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);

        // Initialize the form on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleSizeInputs();
        });

        function searchItems(section) {
            const searchInput = document.getElementById(`search-${section}`);
            const searchTerm = searchInput.value.toLowerCase().trim();
            const categoriesGrid = document.getElementById(`categories-grid-${section}`);
            const resultsInfo = document.getElementById(`results-info-${section}`);
            
            // Get all category sections in this section
            const categoryCards = categoriesGrid.querySelectorAll('.category-section');
            let totalVisibleItems = 0;
            let totalVisibleCategories = 0;
            
            if (searchTerm === '') {
                // Show all items and categories
                categoryCards.forEach(card => {
                    card.style.display = 'block';
                    const rows = card.querySelectorAll('.table-row');
                    rows.forEach(row => {
                        row.style.display = 'grid';
                        // Remove any existing highlights
                        removeHighlights(row);
                    });
                    if (rows.length > 0) {
                        totalVisibleItems += rows.length;
                        totalVisibleCategories++;
                    }
                });
                resultsInfo.innerHTML = '';
                return;
            }
            
            categoryCards.forEach(card => {
                const categoryTitle = card.querySelector('.category-title').textContent.toLowerCase();
                const rows = card.querySelectorAll('.table-row');
                let hasVisibleItems = false;
                
                rows.forEach(row => {
                    const itemName = row.querySelector('.item-name').textContent.toLowerCase();
                    const itemDescription = row.querySelector('.item-description');
                    const description = itemDescription ? itemDescription.textContent.toLowerCase() : '';
                    const sizeText = row.querySelector('.size-badge').textContent.toLowerCase();
                    
                    // Check if search term matches name, category, description, or size
                    const matchesName = itemName.includes(searchTerm);
                    const matchesCategory = categoryTitle.includes(searchTerm);
                    const matchesDescription = description.includes(searchTerm);
                    const matchesSize = sizeText.includes(searchTerm);
                    
                    if (matchesName || matchesCategory || matchesDescription || matchesSize) {
                        row.style.display = 'grid';
                        hasVisibleItems = true;
                        totalVisibleItems++;
                        
                        // Remove existing highlights
                        removeHighlights(row);
                        
                        // Add highlights
                        if (matchesName) {
                            highlightText(row.querySelector('.item-name'), searchTerm);
                        }
                        if (matchesDescription && itemDescription) {
                            highlightText(itemDescription, searchTerm);
                        }
                        if (matchesSize) {
                            highlightText(row.querySelector('.size-badge'), searchTerm);
                        }
                    } else {
                        row.style.display = 'none';
                        removeHighlights(row);
                    }
                });
                
                // Show/hide category card based on whether it has visible items or matches category name
                if (hasVisibleItems || categoryTitle.includes(searchTerm)) {
                    card.style.display = 'block';
                    if (hasVisibleItems) {
                        totalVisibleCategories++;
                    }
                    
                    // Highlight category title if it matches
                    if (categoryTitle.includes(searchTerm)) {
                        const categoryTitleElement = card.querySelector('.category-title');
                        removeHighlights(categoryTitleElement);
                        highlightText(categoryTitleElement, searchTerm);
                    }
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Update results info
            if (totalVisibleItems === 0) {
                resultsInfo.innerHTML = `
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <div>No items found matching "${searchTerm}"</div>
                    </div>
                `;
            } else {
                const itemText = totalVisibleItems === 1 ? 'item' : 'items';
                const categoryText = totalVisibleCategories === 1 ? 'category' : 'categories';
                resultsInfo.innerHTML = `Found ${totalVisibleItems} ${itemText} in ${totalVisibleCategories} ${categoryText}`;
            }
        }
        
        function highlightText(element, searchTerm) {
            if (!element || !searchTerm) return;
            
            const originalText = element.textContent;
            const regex = new RegExp(`(${escapeRegExp(searchTerm)})`, 'gi');
            const highlightedText = originalText.replace(regex, '<span class="highlight">$1</span>');
            
            // Only update if there are actual matches
            if (highlightedText !== originalText) {
                element.innerHTML = highlightedText;
            }
        }
        
        function removeHighlights(element) {
            if (!element) return;
            
            const highlightedElements = element.querySelectorAll('.highlight');
            highlightedElements.forEach(highlighted => {
                const parent = highlighted.parentNode;
                parent.replaceChild(document.createTextNode(highlighted.textContent), highlighted);
                parent.normalize();
            });
        }
        
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
