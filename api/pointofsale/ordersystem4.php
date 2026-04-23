<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';

include "db_conn.php";


function generateOrderId()
{
    return 'TBL4-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function checkStockAvailability($menuItemId, $sizeId, $quantity)
{
    global $conn;

    $sql = "
        SELECT ums.ingredient_id, ums.quantity_required, ui.item_name, ui.current_quantity, ui.unit
        FROM unified_menu_system ums
        JOIN unified_inventory ui ON ums.ingredient_id = ui.item_id
        WHERE ums.menu_item_id = ? AND ums.size_id = ? AND ums.ingredient_id IS NOT NULL
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $menuItemId, $sizeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $available = true;
    while ($row = $result->fetch_assoc()) {
        $required_qty = floatval($row['quantity_required']) * floatval($quantity);
        $current_stock = floatval($row['current_quantity']);
        if ($current_stock < $required_qty) {
            $available = false;
            break;
        }
    }
    $stmt->close();
    return $available;
}

function deductIngredients($menuItemId, $sizeId, $quantity)
{
    global $conn;

    $query = "SELECT ums.ingredient_id, ums.quantity_required, ui.item_id
              FROM unified_menu_system ums
              JOIN unified_inventory ui ON ums.ingredient_id = ui.item_id
              WHERE ums.menu_item_id = ? AND ums.size_id = ? AND ums.ingredient_id IS NOT NULL";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ss", $menuItemId, $sizeId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $deduct = floatval($row['quantity_required']) * floatval($quantity);
        $ingredient_id = $row['ingredient_id'];

        $update = $conn->prepare("UPDATE unified_inventory SET current_quantity = current_quantity - ? WHERE item_id = ?");
        if (!$update) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $update->bind_param("ds", $deduct, $ingredient_id);
        if (!$update->execute()) {
            throw new Exception("Failed to deduct ingredient: " . $update->error);
        }
        $update->close();
    }
    $stmt->close();
}

function getMenuPrice($menuItemId, $sizeId)
{
    global $conn;
    $stmt = $conn->prepare("SELECT price FROM unified_menu_system WHERE menu_item_id = ? AND size_id = ?");
    $stmt->bind_param("ss", $menuItemId, $sizeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['price'] : 0;
}

function addCustomerOrderRecord($orderId, $orderType, $total, $status = 'pending')
{
    global $conn;
    $conn->query("CREATE TABLE IF NOT EXISTS customer_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(50) UNIQUE NOT NULL,
        order_type ENUM('dine_in','takeout','delivery') NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        amount_paid DECIMAL(10,2) DEFAULT 0,
        status ENUM('pending','confirmed','preparing','ready','completed','cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $columnCheck = $conn->query("SHOW COLUMNS FROM customer_orders LIKE 'amount_paid'");
    if ($columnCheck && $columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE customer_orders ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT 0 AFTER total");
    }
    $stmt = $conn->prepare("INSERT INTO customer_orders (order_id, order_type, total, amount_paid, status) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $amountPaid = 0;
    $stmt->bind_param("ssdds", $orderId, $orderType, $total, $amountPaid, $status);
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert order: " . $stmt->error);
    }
    $stmt->close();
}

function addCustomerOrderItemRecord($orderId, $menuItemId, $sizeId, $quantity, $price, $flavor = null)
{
    global $conn;
    $conn->query("CREATE TABLE IF NOT EXISTS customer_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(50) NOT NULL,
        menu_item_id VARCHAR(50) NOT NULL,
        size_id VARCHAR(50) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        flavor VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES customer_orders(order_id)
    )");
    $stmt = $conn->prepare("INSERT INTO customer_order_items (order_id, menu_item_id, size_id, quantity, price, flavor) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sssiis", $orderId, $menuItemId, $sizeId, $quantity, $price, $flavor);
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert order item: " . $stmt->error);
    }
    $stmt->close();
}

function generateCustomerReceipt($orderId, $cart, $total, $orderType)
{
    $receipt = "<div class='receipt-header'><h2>Bubble Hideout</h2><p>Thank you for your order!</p></div>";
    $receipt .= "<div class='receipt-details'><p><strong>Order ID:</strong> $orderId</p><p><strong>Order Type:</strong> " . ucfirst(str_replace('_', ' ', $orderType)) . "</p><p><strong>Order Time:</strong> " . date('Y-m-d H:i:s') . "</p></div>";
    $receipt .= "<div class='receipt-items'><h3>Order Items:</h3><table class='receipt-table'><tr><th>Item</th><th>Size</th><th>Qty</th><th>Price</th></tr>";
    foreach ($cart as $item) {
        $receipt .= "<tr><td>{$item['name']}" . (!empty($item['flavor']) ? " ({$item['flavor']})" : "") . "</td><td>{$item['size']}</td><td>{$item['quantity']}</td><td>₱" . number_format($item['total_price'], 2) . "</td></tr>";
    }
    $receipt .= "</table></div><div class='receipt-total'><p><strong>Total: ₱" . number_format($total, 2) . "</strong></p></div>";
    $receipt .= "<div class='receipt-footer'><p>Your order is being processed. You will be notified when it's ready!</p><p>Estimated preparation time: 15-20 minutes</p></div>";
    return $receipt;
}

$active_section = $_GET['section'] ?? 'drinks';
$total = 0;
$order_message = '';
if (!isset($_SESSION['cart']))
    $_SESSION['cart'] = [];

$menu = [];
$sections_result = $conn->query("SELECT DISTINCT section FROM unified_menu_system WHERE is_available = 1 ORDER BY section");
if ($sections_result) {
    while ($section_row = $sections_result->fetch_assoc()) {
        $section = $section_row['section'];
        $categories_stmt = $conn->prepare("SELECT DISTINCT category FROM unified_menu_system WHERE section = ? AND is_available = 1 ORDER BY category");
        $categories_stmt->bind_param("s", $section);
        $categories_stmt->execute();
        $categories_result = $categories_stmt->get_result();
        while ($category_row = $categories_result->fetch_assoc()) {
            $category = $category_row['category'];
            $menu[$section][$category] = [];
            $items_stmt = $conn->prepare("SELECT DISTINCT menu_item_id, menu_item_name, size_id, price
                FROM unified_menu_system
                WHERE section = ? AND category = ? AND is_available = 1
                ORDER BY menu_item_name,
                CASE size_id WHEN 'REG' THEN 1 WHEN 'GRANDE' THEN 2 WHEN 'VENTI' THEN 3 WHEN 'UNLI' THEN 4 ELSE 5 END");
            $items_stmt->bind_param("ss", $section, $category);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            while ($item = $items_result->fetch_assoc()) {
                $menu[$section][$category][$item['menu_item_name']][$item['size_id']] = [
                    'price' => $item['price'],
                    'id' => $item['menu_item_id']
                ];
            }
            $items_stmt->close();
        }
        $categories_stmt->close();
    }
}

$chicken_flavors = ['Buffalo', 'BBQ', 'Honey Garlic', 'Spicy', 'Original', 'Teriyaki'];

if (isset($_POST['add_to_cart'])) {
    $item = $_POST['item'];
    $category = $_POST['category'];
    $section = $_POST['section'];
    $size = $_POST['size'];
    if (isset($menu[$section][$category][$item][$size])) {
        $menu_item_id = $menu[$section][$category][$item][$size]['id'];
        $price = $menu[$section][$category][$item][$size]['price'];
        $flavor = $_POST['flavor'] ?? '';
        if (checkStockAvailability($menu_item_id, $size, 1)) {
            $item_index = false;
            foreach ($_SESSION['cart'] as $index => $cart_item) {
                if ($cart_item['id'] === $menu_item_id && $cart_item['size'] === $size && $cart_item['flavor'] === $flavor) {
                    $item_index = $index;
                    break;
                }
            }
            if ($item_index !== false) {
                if (checkStockAvailability($menu_item_id, $size, $_SESSION['cart'][$item_index]['quantity'] + 1)) {
                    $_SESSION['cart'][$item_index]['quantity']++;
                    $_SESSION['cart'][$item_index]['total_price'] += $price;
                } else
                    echo "<script>alert('Sorry, not enough stock available for " . addslashes($item) . "');</script>";
            } else {
                $_SESSION['cart'][] = ['id' => $menu_item_id, 'name' => $item, 'size' => $size, 'price' => $price, 'quantity' => 1, 'total_price' => $price, 'flavor' => $flavor];
            }
        } else
            echo "<script>alert('Sorry, " . addslashes($item) . " is currently out of stock.');</script>";
    }
}

if (isset($_POST['increase_quantity'])) {
    $i = intval($_POST['index']);
    if (isset($_SESSION['cart'][$i])) {
        $id = $_SESSION['cart'][$i]['id'];
        $size = $_SESSION['cart'][$i]['size'];
        if (checkStockAvailability($id, $size, $_SESSION['cart'][$i]['quantity'] + 1)) {
            $_SESSION['cart'][$i]['quantity']++;
            $_SESSION['cart'][$i]['total_price'] += $_SESSION['cart'][$i]['price'];
        } else
            echo "<script>alert('Sorry, not enough stock available.');</script>";
    }
}

if (isset($_POST['decrease_quantity'])) {
    $i = intval($_POST['index']);
    if (isset($_SESSION['cart'][$i])) {
        if ($_SESSION['cart'][$i]['quantity'] > 1) {
            $_SESSION['cart'][$i]['quantity']--;
            $_SESSION['cart'][$i]['total_price'] -= $_SESSION['cart'][$i]['price'];
        } else {
            unset($_SESSION['cart'][$i]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }
    }
}

if (isset($_POST['remove_item'])) {
    $i = intval($_POST['index']);
    if (isset($_SESSION['cart'][$i])) {
        unset($_SESSION['cart'][$i]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
}

foreach ($_SESSION['cart'] as $item)
    $total += $item['total_price'];

if (isset($_POST['place_order'])) {
    $order_type = $_POST['order_type'] ?? '';

    if (empty($order_type)) {
        $order_message = "Error: Please select an order type.";
    } elseif (empty($_SESSION['cart'])) {
        $order_message = "Error: Your cart is empty. Please add items before placing an order.";
    } else {
        $orderId = generateOrderId();
        $conn->begin_transaction();
        try {
            addCustomerOrderRecord($orderId, $order_type, $total);

            foreach ($_SESSION['cart'] as $item) {
                if (!checkStockAvailability($item['id'], $item['size'], $item['quantity'])) {
                    throw new Exception("Insufficient stock for {$item['name']} ({$item['size']})");
                }

                deductIngredients($item['id'], $item['size'], $item['quantity']);
                addCustomerOrderItemRecord($orderId, $item['id'], $item['size'], $item['quantity'], $item['price'], $item['flavor'] ?? null);
            }

            $conn->commit();

            $_SESSION['receipt'] = generateCustomerReceipt($orderId, $_SESSION['cart'], $total, $order_type);
            $_SESSION['order_id'] = $orderId;
            $_SESSION['cart'] = [];
            $total = 0;
            $order_message = "success|Order placed successfully! Your order ID is: " . $orderId;

        } catch (Exception $e) {
            $conn->rollback();
            $order_message = "error|Order Processing Error: " . $e->getMessage();
            error_log("Order Error for Order ID: " . $orderId . " - " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bubble Hideout - Order System</title>
    <link rel="stylesheet" href="/fonts/fonts.css">
</head>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: "Poppins", sans-serif;
        background: #f8f7f4;
        min-height: 100vh;
    }

    /* Enhanced header with logo integration */
    .header {
        background: linear-gradient(135deg, #d4a574 0%, #c9945f 100%);
        padding: 0.75rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        position: sticky;
        top: 0;
        z-index: 100;
        gap: 1rem;
    }

    .logo-section {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
        min-width: 0;
    }

    .logo-img {
        width: 50px;
        height: 50px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .logo-text h1 {
        color: #ffffff;
        font-size: 1.4rem;
        margin-bottom: 0.1rem;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .logo-text p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.75rem;
        font-weight: 500;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-shrink: 0;
    }

    .cart-summary {
        background: #2d5016;
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .cart-summary:hover {
        background: #1f3810;
        transform: translateY(-2px);
    }

    .cart-count {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .container {
        display: flex;
        max-width: 1400px;
        margin: 0 auto;
        padding: 1.5rem;
        gap: 1.5rem;
    }

    .menu-section {
        flex: 1;
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .section-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .section-tab {
        background: #e8e8e8;
        color: #333;
        padding: 0.6rem 1.2rem;
        text-decoration: none;
        font-weight: 600;
        border-radius: 6px;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }

    .section-tab.active {
        background: #2d5016;
        color: white;
    }

    .section-tab:hover {
        background: #2d5016;
        color: white;
    }

    .category-nav {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .category-btn {
        background: #e8e8e8;
        color: #333;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .category-btn:hover,
    .category-btn.active {
        background: #2d5016;
        color: white;
    }

    .category-title {
        color: #2d5016;
        margin-bottom: 1.5rem;
        font-size: 1.6rem;
        font-weight: 700;
    }

    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1.5rem;
    }

    .menu-item-card {
        background: white;
        border: 2px solid #f0f0f0;
        border-radius: 10px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .menu-item-card:hover {
        border-color: #2d5016;
        box-shadow: 0 4px 12px rgba(45, 80, 22, 0.15);
        transform: translateY(-4px);
    }

    .item-info {
        padding: 1rem;
    }

    .item-name {
        color: #333;
        margin-bottom: 0.75rem;
        font-size: 1rem;
        font-weight: 600;
    }

    .size-options {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .size-form {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .flavor-select {
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 0.9rem;
        font-family: "Poppins", sans-serif;
    }

    .add-to-cart-btn {
        background: #2d5016;
        color: white;
        border: none;
        padding: 0.7rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        font-size: 0.95rem;
    }

    .add-to-cart-btn:hover {
        background: #1f3810;
        transform: translateY(-2px);
    }

    /* Improved cart section for mobile */
    .cart-section {
        width: 380px;
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        position: sticky;
        top: 90px;
        height: fit-content;
        max-height: calc(100vh - 110px);
        overflow-y: auto;
    }

    .cart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }

    .cart-header h2 {
        color: #2d5016;
        font-weight: 700;
        font-size: 1.3rem;
    }

    .close-cart {
        background: none;
        border: none;
        font-size: 1.8rem;
        cursor: pointer;
        color: #666;
        display: none;
    }

    .empty-cart {
        text-align: center;
        padding: 2rem;
        color: #999;
    }

    .cart-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem;
        background: #f8f7f4;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .item-details {
        flex: 1;
        min-width: 0;
    }

    .item-details h4 {
        color: #333;
        margin-bottom: 0.25rem;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .item-specs {
        color: #666;
        font-size: 0.8rem;
        margin-bottom: 0.25rem;
    }

    .item-price {
        color: #2d5016;
        font-weight: 700;
        font-size: 0.95rem;
    }

    .quantity-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .qty-btn {
        background: #2d5016;
        color: white;
        border: none;
        width: 28px;
        height: 28px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.2s ease;
    }

    .qty-btn:hover {
        background: #1f3810;
    }

    .quantity {
        min-width: 30px;
        text-align: center;
        font-weight: 600;
    }

    .remove-btn {
        background: #dc3545;
        color: white;
        border: none;
        padding: 0.4rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }

    .remove-btn:hover {
        background: #c82333;
    }

    .cart-total {
        text-align: center;
        padding: 1rem;
        background: linear-gradient(135deg, #2d5016 0%, #1f3810 100%);
        color: white;
        border-radius: 8px;
        margin: 1rem 0;
    }

    .cart-total h3 {
        font-size: 1.3rem;
        font-weight: 700;
    }

    .checkout-btn {
        width: 100%;
        background: linear-gradient(135deg, #2d5016 0%, #1f3810 100%);
        color: white;
        border: none;
        padding: 1rem;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .checkout-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(45, 80, 22, 0.3);
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 2rem;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
    }

    .close {
        position: absolute;
        right: 1rem;
        top: 1rem;
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover {
        color: #000;
    }

    .checkout-form {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        font-weight: 600;
        color: #333;
    }

    .form-group input,
    .form-group select {
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 1rem;
        font-family: "Poppins", sans-serif;
    }

    .order-summary {
        background: #f8f7f4;
        padding: 1rem;
        border-radius: 8px;
        margin: 1rem 0;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .summary-total {
        border-top: 1px solid #ddd;
        padding-top: 0.5rem;
        margin-top: 0.5rem;
        font-weight: 700;
    }

    .place-order-btn {
        background: linear-gradient(135deg, #2d5016 0%, #1f3810 100%);
        color: white;
        border: none;
        padding: 1rem;
        border-radius: 8px;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .place-order-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(45, 80, 22, 0.3);
    }

    .order-message {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        text-align: center;
        font-weight: 600;
    }

    .order-message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .order-message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .receipt-header {
        text-align: center;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #eee;
    }

    .receipt-details {
        margin-bottom: 1rem;
    }

    .receipt-table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
    }

    .receipt-table th,
    .receipt-table td {
        padding: 0.5rem;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    .receipt-table th {
        background: #f8f7f4;
        font-weight: 700;
    }

    .receipt-total {
        text-align: center;
        font-size: 1.2rem;
        margin: 1rem 0;
        padding: 1rem;
        background: linear-gradient(135deg, #2d5016 0%, #1f3810 100%);
        color: white;
        border-radius: 8px;
    }

    .receipt-footer {
        text-align: center;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 2px solid #eee;
        color: #666;
    }

    .receipt-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 1rem;
    }

    .print-btn,
    .close-btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
    }

    .print-btn {
        background: #2d5016;
        color: white;
    }

    .close-btn {
        background: #6c757d;
        color: white;
    }

    .no-items {
        text-align: center;
        padding: 3rem;
        color: #666;
    }

    .box {
        position: relative;
        padding: 10px;
        border-radius: 15px;
        margin: auto;
        width: 100%;
        z-index: 10;
        box-sizing: border-box;
    }

    .sec3 {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        z-index: 5;
    }

    .info-parent {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 20px;
    }

    .stars {
        display: flex;
        flex-direction: row-reverse;
        justify-content: center;
        gap: 5px;
    }

    .stars input {
        display: none;
    }

    .stars label {
        font-size: 2.5rem;
        color: #ccc;
        cursor: pointer;
        transition: color 0.2s;
    }

    .stars input:checked~label {
        color: #ffc700;
    }

    .stars label:hover,
    .stars label:hover~label {
        color: #ffdd66;
    }

    .info-contact1 {
        background-color: white;
        align-content: center;
        border: #7e5832 solid 2px;
        color: #1a1a1a;
        text-align: justify;
        padding: 20px;
        border-radius: 8px;
        margin: 10px;
        width: 20%;
        max-width: 500px;
        height: auto;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        word-wrap: break-word;
        box-sizing: border-box;
    }

    .info-contact2 {
        background-color: white;
        border: #7e5832 solid 2px;
        color: #1a1a1a;
        text-align: justify;
        padding: 20px;
        border-radius: 8px;
        margin: 10px;
        width: 70%;
        height: auto;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        word-wrap: break-word;
        box-sizing: border-box;
    }

    #name,
    #comment {
        width: 95%;
    }

    #comm_btn {
        width: 30%;
    }

    .rate-btn {
        font-family: Poppins;
        font-size: 15px;
        background-color: transparent;
        padding: 10px;
        border: #1a1a1a solid 2px;
        width: 70%;
        border-radius: 24px;
        transition: ease 0.5s;
    }

    .rate-btn:hover {
        font-weight: bolder;
        border-radius: 30px;
        transition: ease 0.5s;
    }

    .feedback-form {
        width: 100%;
        margin: 0 auto;
        padding: 20px;
        border-radius: 10px;
        font-family: Poppins;
    }

    .feedback-form h3 {
        text-align: center;
        margin-bottom: 20px;
    }

    .feedback-form label {
        display: block;
        margin-top: 10px;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .feedback-form input,
    .feedback-form textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #1a1a1a;
        border-radius: 5px;
        font-family: inherit;
        font-size: 1rem;
        box-sizing: border-box;
    }

    .feedback-form input:focus,
    .feedback-form textarea:focus {
        outline: none;
        box-shadow: none;
    }

    .feedback-form button {
        margin-top: 15px;
        font-family: Poppins;
        width: 100%;
        padding: 12px;
        color: #1a1a1a;
        border-radius: 6px;
        font-size: 1rem;
        cursor: pointer;
        background-color: transparent;
        border: #1a1a1a solid 2px;
        transition: background-color 0.2s ease;
    }

    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 999;
    }

    .modal-overlay .modal-content {
        background: white;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        font-size: 1.2rem;
    }

    .modal-overlay .modal-content button {
        margin-top: 15px;
        padding: 8px 20px;
        font-family: Poppins;
        background-color: #2d5016;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }

    /* Enhanced mobile responsiveness */
    @media (max-width: 1024px) {
        .container {
            flex-direction: column;
            padding: 1rem;
        }

        .cart-section {
            width: 100%;
            position: fixed;
            right: -100%;
            height: 100vh;
            z-index: 200;
            transition: right 0.3s ease;
            border-radius: 0;
            max-height: 100vh;
            top: 10px;
        }

        .cart-section.active {
            right: 0;
        }

        .close-cart {
            display: block;
        }

        .menu-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }

        .header {
            padding: 0.5rem 0.75rem;
        }

        .logo-img {
            width: 45px;
            height: 45px;
        }

        .logo-text h1 {
            font-size: 1.2rem;
        }

        .logo-text p {
            font-size: 0.7rem;
        }

        .section-tabs {
            flex-wrap: wrap;
        }

        .category-nav {
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        .header {
            padding: 0.5rem;
            gap: 0.5rem;
        }

        .logo-img {
            width: 40px;
            height: 40px;
        }

        .logo-text h1 {
            font-size: 1rem;
        }

        .logo-text p {
            font-size: 0.65rem;
        }

        .track-order-btn {
            padding: 0.4rem 0.6rem;
            font-size: 0.75rem;
        }

        .cart-summary {
            padding: 0.4rem 0.6rem;
            font-size: 0.8rem;
        }

        .container {
            padding: 0.75rem;
            gap: 1rem;
        }

        .menu-section {
            padding: 1rem;
        }

        .menu-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }

        .category-title {
            font-size: 1.3rem;
        }

        .item-name {
            font-size: 0.9rem;
        }

        .add-to-cart-btn {
            padding: 0.6rem 0.8rem;
            font-size: 0.85rem;
        }

        .info-contact1,
        .info-contact2 {
            width: 95%;
            max-width: 95%;
            margin: 10px 0;
        }

        .rate-btn,
        #comm_btn {
            width: 80%;
            max-width: 250px;
            padding: 12px;
            font-size: 16px;
            border-radius: 10px;
        }

        h1 {
            font-size: 24px;
        }

        h2,
        h3 {
            font-size: 20px;
        }
    }

    @media (max-width: 480px) {
        .header {
            padding: 0.4rem;
        }

        .logo-img {
            width: 35px;
            height: 35px;
        }

        .logo-text h1 {
            font-size: 0.9rem;
        }

        .logo-text p {
            display: none;
        }

        .track-order-btn {
            display: none;
        }

        .cart-summary {
            padding: 0.35rem 0.5rem;
            font-size: 0.75rem;
        }

        .container {
            padding: 0.5rem;
            gap: 0.5rem;
        }

        .menu-section {
            padding: 0.75rem;
        }

        .menu-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .category-title {
            font-size: 1.1rem;
        }

        .item-name {
            font-size: 0.8rem;
        }

        .add-to-cart-btn {
            padding: 0.5rem 0.6rem;
            font-size: 0.75rem;
        }

        .section-tab {
            padding: 0.5rem 0.8rem;
            font-size: 0.8rem;
        }

        .category-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .info-contact1,
        .info-contact2 {
            padding: 15px;
        }

        h1 {
            font-size: 22px;
        }

        h2,
        h3 {
            font-size: 18px;
        }

        .rate-btn,
        #comm_btn {
            width: 100%;
            padding: 12px;
            font-size: 16px;
        }
    }
</style>

<body>
    <div id="mainOrderUI">
        <!-- Redesigned header with logo integration -->
        <div class="header">
            <div class="logo-section">
                <img src="/media/BUBBLE.jpg" alt="Bubble Hideout Logo" class="logo-img">
                <div class="logo-text">
                    <h1>Bubble Hideout</h1>
                    <p>Order Online</p>
                </div>
            </div>

            <div class="header-actions">
                <div class="cart-summary" onclick="toggleCart()">
                    <span class="cart-count"><?php echo count($_SESSION['cart']); ?></span>
                    <span>₱<?php echo number_format($total, 2); ?></span>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="menu-section">
                <div class="section-tabs">
                    <a href="?section=drinks"
                        class="section-tab <?php echo $active_section == 'drinks' ? 'active' : ''; ?>">
                        Drinks
                    </a>
                    <a href="?section=food"
                        class="section-tab <?php echo $active_section == 'food' ? 'active' : ''; ?>">
                        Food
                    </a>
                    <a href="?section=addons"
                        class="section-tab <?php echo $active_section == 'addons' ? 'active' : ''; ?>">
                        Add-ons
                    </a>
                </div>

                <?php if (isset($menu[$active_section]) && !empty($menu[$active_section])): ?>
                    <div class="category-nav">
                        <?php foreach ($menu[$active_section] as $category => $items): ?>
                            <button class="category-btn" onclick="showCategory('<?php echo htmlspecialchars($category); ?>')">
                                <?php echo htmlspecialchars($category); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($menu[$active_section] as $category => $items): ?>
                        <div class="menu-category" id="<?php echo htmlspecialchars($category); ?>" style="display: none;">
                            <h2 class="category-title"><?php echo htmlspecialchars($category); ?></h2>
                            <div class="menu-grid">
                                <?php foreach ($items as $item => $sizes): ?>
                                    <div class="menu-item-card">
                                        <div class="item-info">
                                            <h3 class="item-name"><?php echo htmlspecialchars($item); ?></h3>
                                            <div class="size-options">
                                                <?php foreach ($sizes as $size => $data): ?>
                                                    <form method="post" class="size-form">
                                                        <input type="hidden" name="item" value="<?php echo htmlspecialchars($item); ?>">
                                                        <input type="hidden" name="category"
                                                            value="<?php echo htmlspecialchars($category); ?>">
                                                        <input type="hidden" name="section"
                                                            value="<?php echo htmlspecialchars($active_section); ?>">
                                                        <input type="hidden" name="size" value="<?php echo htmlspecialchars($size); ?>">

                                                        <?php if (strpos(strtolower($category), 'wings') !== false || strpos(strtolower($category), 'chicken') !== false): ?>
                                                            <select name="flavor" class="flavor-select" required>
                                                                <option value="">Choose Flavor</option>
                                                                <?php foreach ($chicken_flavors as $flavor): ?>
                                                                    <option value="<?php echo htmlspecialchars($flavor); ?>">
                                                                        <?php echo htmlspecialchars($flavor); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        <?php endif; ?>

                                                        <button type="submit" name="add_to_cart" class="add-to-cart-btn">
                                                            <span class="size-name"><?php echo htmlspecialchars($size); ?></span>
                                                            <span
                                                                class="size-price">₱<?php echo number_format($data['price'], 2); ?></span>
                                                        </button>
                                                    </form>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-items">
                        <p>No menu items available for this section.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="cart-section" id="cartSection">
                <div class="cart-header">
                    <h2>Your Order</h2>
                    <button class="close-cart" onclick="toggleCart()">×</button>
                </div>

                <div class="cart-items">
                    <?php if (empty($_SESSION['cart'])): ?>
                        <div class="empty-cart">
                            <p>Your cart is empty</p>
                            <p>Add some delicious items to get started!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                            <div class="cart-item">
                                <div class="item-details">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p class="item-specs">
                                        Size: <?php echo htmlspecialchars($item['size']); ?>
                                        <?php if (!empty($item['flavor'])): ?>
                                            | Flavor: <?php echo htmlspecialchars($item['flavor']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="item-price">₱<?php echo number_format($item['total_price'], 2); ?></p>
                                </div>
                                <div class="quantity-controls">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                                        <button type="submit" name="decrease_quantity" class="qty-btn">−</button>
                                    </form>
                                    <span class="quantity"><?php echo $item['quantity']; ?></span>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                                        <button type="submit" name="increase_quantity" class="qty-btn">+</button>
                                    </form>
                                </div>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                    <button type="submit" name="remove_item" class="remove-btn">✖</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($_SESSION['cart'])): ?>
                    <div class="cart-total">
                        <h3>Total: ₱<?php echo number_format($total, 2); ?></h3>
                    </div>

                    <div class="checkout-section">
                        <button onclick="showCheckoutForm()" class="checkout-btn">Proceed to Checkout</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="checkoutModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeCheckoutModal()">&times;</span>
                <h2>Complete Your Order</h2>

                <?php if (!empty($order_message)): ?>
                    <?php
                    $msg_parts = explode('|', $order_message);
                    $msg_type = $msg_parts[0] ?? '';
                    $msg_text = $msg_parts[1] ?? $order_message;
                    ?>
                    <div class="order-message <?php echo $msg_type; ?>">
                        <?php echo htmlspecialchars($msg_text); ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="checkout-form">
                    <div class="form-group">
                        <label for="order_type">Order Type *</label>
                        <select id="order_type" name="order_type" required>
                            <option value="">Select Order Type</option>
                            <option value="dine_in">Dine In</option>
                            <option value="takeout">Takeout</option>
                        </select>
                    </div>

                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        <?php if (!empty($_SESSION['cart'])): ?>
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <div class="summary-item">
                                    <span><?php echo htmlspecialchars($item['name']); ?>
                                        (<?php echo htmlspecialchars($item['size']); ?>)
                                        x<?php echo $item['quantity']; ?></span>
                                    <span>₱<?php echo number_format($item['total_price'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="summary-total">
                                <strong>Total: ₱<?php echo number_format($total, 2); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" name="place_order" class="place-order-btn">Place Order</button>
                </form>
            </div>
        </div>

        <div id="receiptModal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close" onclick="closeOrderSummaryAndExpire()">&times;</span>
                <div id="receiptContent"></div>
                <div class="receipt-actions">
                    <button onclick="closeOrderSummaryAndExpire()" class="close-btn">Close</button>
                </div>
            </div>
        </div>

        <script>
            function showCategory(category) {
                var categories = document.getElementsByClassName('menu-category');
                for (var i = 0; i < categories.length; i++) {
                    categories[i].style.display = 'none';
                }
                document.getElementById(category).style.display = 'block';

                var buttons = document.getElementsByClassName('category-btn');
                for (var i = 0; i < buttons.length; i++) {
                    buttons[i].classList.remove('active');
                    if (buttons[i].textContent.trim() === category) {
                        buttons[i].classList.add('active');
                    }
                }
            }

            function showFirstCategory() {
                var firstCategoryBtn = document.querySelector('.category-btn');
                if (firstCategoryBtn) {
                    firstCategoryBtn.click();
                }
            }

            function toggleCart() {
                var cartSection = document.getElementById('cartSection');
                cartSection.classList.toggle('active');
            }

            function showCheckoutForm() {
                document.getElementById('checkoutModal').style.display = 'block';
            }

            function closeCheckoutModal() {
                document.getElementById('checkoutModal').style.display = 'none';
            }

            function closeOrderSummaryAndExpire() {
                sessionStorage.setItem('orderExpired', 'true');
                document.body.innerHTML = `
                <center> <div class="img-bbl">
                <img src="/media/BUBBLE.jpg" width="200px" height="200px"> 
            </div>
            <br></center>

            <div class="box">
                <center><h1>Thank You for Order</h1></center>
                <center><p>Please wait 15 - 20 minutes for your order!</p></center>

                <section class="sec3" id="contacts">
                    
                <div class="info-parent">

                    <div class="info-contact1">
                        <form class="star-rating-form" id="starRating" method="POST">
                        <center><h3 style="font-size: 30px; margin-top: -10px; margin-bottom: -5px;">Rate Us</h3></center>

                        <div class="stars">
                            <input type="radio" name="rating" id="star5" value="5">
                            <label for="star5" title="5 stars">★</label>

                            <input type="radio" name="rating" id="star4" value="4">
                            <label for="star4" title="4 stars">★</label>

                            <input type="radio" name="rating" id="star3" value="3">
                            <label for="star3" title="3 stars">★</label>

                            <input type="radio" name="rating" id="star2" value="2">
                            <label for="star2" title="2 stars">★</label>

                            <input type="radio" name="rating" id="star1" value="1">
                            <label for="star1" title="1 star">★</label>
                        </div>

                        <br><br>

                        <center><button class="rate-btn" type="submit">Submit</button></center>
                        </form>
                    </div>
                    
                    <div class="info-contact2">
                        <h2 style="font-size: 30px; margin-top: -10px; margin-bottom: -10px;">LET US HEAR YOUR FEEDBACK!</h2>
                        
                        <form class="feedback-form" id="feedbackForm" method="POST">
                        
                        <label for="name">Your Name: <i style="color: gray; opacity: 0.5;">Optional</i></label>
                        <input type="text" id="name" name="name" placeholder="Enter your name (optional)" autocomplete="off">

                        <label for="comment">Your Comment:</label>
                        <textarea id="comment" name="comment" rows="5" placeholder="Write your feedback here..." required></textarea>

                        <center><button id="comm_btn" type="submit">Submit</button></center>
                        </form>
                      
                    </div>
                </div>
                </section>   
                <div id="thankYouModal" class="modal-overlay">
                  <div class="modal-content">
                    <p>Thank you for your response!</p>
                    <button onclick="closeModal()">Close</button>
                  </div>
                </div>

                <div id="starThankYouModal" class="modal-overlay">
                  <div class="modal-content">
                    <p>Thank you for rating us!</p>
                    <button onclick="closeStarModal()">Close</button>
                  </div>
                </div> 
            </div>
            `;

                document.getElementById("feedbackForm").addEventListener("submit", function (e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    fetch("feedback-to-database.php", {
                        method: "POST",
                        body: formData
                    })
                        .then(response => response.text())
                        .then(data => {
                            document.getElementById("feedbackForm").reset();
                            showModal();
                        })
                        .catch(error => {
                            console.error("Submission error:", error);
                            alert("Something went wrong. Please try again.");
                        });
                });

                function showModal() {
                    document.getElementById("thankYouModal").style.display = "flex";
                }

                document.getElementById("starRating").addEventListener("submit", function (e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    fetch("submit-rating.php", {
                        method: "POST",
                        body: formData
                    })
                        .then(res => res.text())
                        .then(data => {
                            document.getElementById("starRating").reset();
                            showStarModal();
                        })
                        .catch(err => {
                            console.error("Error submitting rating:", err);
                            alert("Something went wrong.");
                        });
                });

                function showStarModal() {
                    document.getElementById("starThankYouModal").style.display = "flex";
                }

                document.addEventListener("click", function (e) {
                    if (e.target.matches("#thankYouModal button")) {
                        document.getElementById("thankYouModal").style.display = "none";
                    }
                    if (e.target.matches("#starThankYouModal button")) {
                        document.getElementById("starThankYouModal").style.display = "none";
                    }
                });

                history.pushState(null, null, location.href);
                window.onpopstate = function () {
                    history.go(1);
                };
            }

            window.onclick = function (event) {
                var checkoutModal = document.getElementById('checkoutModal');
                var receiptModal = document.getElementById('receiptModal');

                if (event.target == checkoutModal) {
                    checkoutModal.style.display = 'none';
                }
                if (event.target == receiptModal) {
                    receiptModal.style.display = 'none';
                }
            }

            window.onload = function () {
                if (sessionStorage.getItem('orderExpired') === 'true') {
                    closeOrderSummaryAndExpire();
                    return;
                }

                showFirstCategory();

                <?php if (isset($_SESSION['receipt'])): ?>
                    document.getElementById('receiptContent').innerHTML = <?php echo json_encode($_SESSION['receipt']); ?>;
                    document.getElementById('receiptModal').style.display = 'block';
                    <?php unset($_SESSION['receipt']); ?>
                <?php endif; ?>
            };
        </script>
</body>

</html>
