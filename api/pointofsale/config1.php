<?php


include "db_conn.php";


// Function to generate order ID
function generateOrderId() {
    return 'TBL' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Function to check stock availability
function checkStockAvailability($menuItemId, $sizeId, $quantity) {
    global $conn;
    
    $stmt = $conn->prepare("CALL CheckIngredientAvailability(?, ?, ?)");
    $stmt->bind_param("ssi", $menuItemId, $sizeId, $quantity);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $available = true;
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'INSUFFICIENT') {
            $available = false;
            break;
        }
    }
    
    $stmt->close();
    return $available;
}

// Function to get menu price
function getMenuPrice($menuItemId, $sizeId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT GetMenuPrice(?, ?) as price");
    $stmt->bind_param("ss", $menuItemId, $sizeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['price'] ?? 0;
}

// Function to process customer order
function processCustomerOrder($orderId, $menuItemId, $sizeId, $quantity) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("CALL ProcessOrderWithDeduction(?, ?, ?, ?)");
        $stmt->bind_param("sssi", $menuItemId, $sizeId, $quantity, $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stmt->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Function to add customer order record
function addCustomerOrderRecord($orderId, $orderType, $total, $status = 'pending') {
    global $conn;
    
    // Create customer_orders table if it doesn't exist
    $createOrdersTable = "
        CREATE TABLE IF NOT EXISTS customer_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(50) UNIQUE NOT NULL,
            order_type ENUM('dine_in', 'takeout', 'delivery') NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            amount_paid DECIMAL(10,2) DEFAULT 0,
            status ENUM('pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    $conn->query($createOrdersTable);
    
    $stmt = $conn->prepare("INSERT INTO customer_orders (order_id, order_type, total, amount_paid, status) VALUES (?, ?, ?, ?, ?)");
    $amountPaid = 0;
    $stmt->bind_param("ssdds", $orderId, $orderType, $total, $amountPaid, $status);
    $stmt->execute();
    $stmt->close();
}

// Function to add customer order items
function addCustomerOrderItemRecord($orderId, $menuItemId, $sizeId, $quantity, $price, $flavor = null) {
    global $conn;
    
    // Create customer_order_items table if it doesn't exist
    $createOrderItemsTable = "
        CREATE TABLE IF NOT EXISTS customer_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(50) NOT NULL,
            menu_item_id VARCHAR(50) NOT NULL,
            size_id VARCHAR(50) NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            flavor VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES customer_orders(order_id)
        )
    ";
    $conn->query($createOrderItemsTable);
    
    $stmt = $conn->prepare("INSERT INTO customer_order_items (order_id, menu_item_id, size_id, quantity, price, flavor) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiis", $orderId, $menuItemId, $sizeId, $quantity, $price, $flavor);
    $stmt->execute();
    $stmt->close();
}

// Function to generate customer receipt
function generateCustomerReceipt($orderId, $cart, $total, $orderType) {
    $receipt = "<div class='receipt-header'>";
    $receipt .= "<h2>Bubble Hideout</h2>";
    $receipt .= "<p>Thank you for your order!</p>";
    $receipt .= "</div>";
    
    $receipt .= "<div class='receipt-details'>";
    $receipt .= "<p><strong>Order ID:</strong> $orderId</p>";
    $receipt .= "<p><strong>Order Type:</strong> " . ucfirst(str_replace('_', ' ', $orderType)) . "</p>";
    $receipt .= "<p><strong>Order Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
    $receipt .= "</div>";
    
    $receipt .= "<div class='receipt-items'>";
    $receipt .= "<h3>Order Items:</h3>";
    $receipt .= "<table class='receipt-table'>";
    $receipt .= "<tr><th>Item</th><th>Size</th><th>Qty</th><th>Price</th></tr>";
    
    foreach ($cart as $item) {
        $receipt .= "<tr>";
        $receipt .= "<td>{$item['name']}" . (isset($item['flavor']) && !empty($item['flavor']) ? " ({$item['flavor']})" : "") . "</td>";
        $receipt .= "<td>{$item['size']}</td>";
        $receipt .= "<td>{$item['quantity']}</td>";
        $receipt .= "<td>₱" . number_format($item['total_price'], 2) . "</td>";
        $receipt .= "</tr>";
    }
    
    $receipt .= "</table>";
    $receipt .= "</div>";
    
    $receipt .= "<div class='receipt-total'>";
    $receipt .= "<p><strong>Total: ₱" . number_format($total, 2) . "</strong></p>";
    $receipt .= "</div>";
    
    $receipt .= "<div class='receipt-footer'>";
    $receipt .= "<p>Your order is being processed. You will be notified when it's ready!</p>";
    $receipt .= "<p>Estimated preparation time: 15-20 minutes</p>";
    $receipt .= "</div>";
    
    return $receipt;
}
?>
