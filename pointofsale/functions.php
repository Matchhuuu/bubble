<?php
require_once 'config.php';

function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateSize($size) {
    return in_array(strtoupper($size), ['GRANDE', 'REG']) ? strtoupper($size) : 'REG';
}

function validateMenuAccess($menu, $section, $category, $item, $size) {
    return isset($menu[$section]) &&
           isset($menu[$section][$category]) &&
           isset($menu[$section][$category][$item]) &&
           isset($menu[$section][$category][$item][$size]);
}

function handleDatabaseError($error) {
    error_log("Database Error: " . $error->getMessage());
    return "An error occurred while processing your request. Please try again.";
}

function generateOrderId($conn) {
    $attempts = 0;
    $max_attempts = 10;

    do {
        $orderId = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_id = ?");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $exists = $row['count'] > 0;
        $stmt->close();

        $attempts++;
    } while ($exists && $attempts < $max_attempts);

    if ($attempts >= $max_attempts) {
        throw new Exception("Unable to generate a unique order ID after {$max_attempts} attempts.");
    }

    return $orderId;
}

function getRecipe($conn, $menuItemId) {
    $stmt = $conn->prepare("SELECT item_id, quantity_required FROM recipe WHERE recipe_id = ?");
    $stmt->bind_param("s", $menuItemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $recipe = [];
    while ($row = $result->fetch_assoc()) {
        $recipe[$row['item_id']] = $row['quantity_required'];
    }
    $stmt->close();
    return $recipe;
}

function checkStockAvailability($conn, $menuItemId, $quantity) {
    $recipe = getRecipe($conn, $menuItemId);
    foreach ($recipe as $itemId => $requiredQuantity) {
        $stmt = $conn->prepare("SELECT current_quantity FROM inventory WHERE item_id = ?");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $currentStock = $row['current_quantity'];
        $stmt->close();
        
        if ($currentStock < ($requiredQuantity * $quantity)) {
            return false;
        }
    }
    return true;
}

function updateStockQuantity($conn, $menuItemId, $quantity) {
    $recipe = getRecipe($conn, $menuItemId);
    foreach ($recipe as $itemId => $requiredQuantity) {
        $stmt = $conn->prepare("UPDATE inventory SET current_quantity = current_quantity - ? WHERE item_id = ?");
        $totalRequired = $requiredQuantity * $quantity;
        $stmt->bind_param("di", $totalRequired, $itemId);
        $stmt->execute();
        $stmt->close();
    }
}

function addOrder($conn, $orderId, $total, $discount, $amountPaid) {
    $stmt = $conn->prepare("INSERT INTO orders (order_id, total, discount, amount_paid) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sddd", $orderId, $total, $discount, $amountPaid);
    $stmt->execute();
    $stmt->close();
}

function addOrderItem($conn, $orderId, $menuItemId, $sizeId, $quantity, $price, $flavor = null) {
    $stmt = $conn->prepare("SELECT id FROM sizes WHERE id = ?");
    $stmt->bind_param("s", $sizeId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        $sizeId = 'REG';
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, size_id, quantity, price, flavor) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiis", $orderId, $menuItemId, $sizeId, $quantity, $price, $flavor);
    $stmt->execute();
    $stmt->close();

    updateStockQuantity($conn, $menuItemId, $quantity);
}

function generateReceipt($orderId, $cart, $subtotal, $discount, $total, $amount_paid, $change) {
    $receipt = "<h3>Order ID: " . sanitizeInput($orderId) . "</h3>";
    $receipt .= "<h4>Order Details:</h4>";
    $receipt .= "<table>";
    $receipt .= "<thead><tr><th>Item</th><th>Size</th><th>Quantity</th><th>Price</th></tr></thead><tbody>";
    foreach ($cart as $item) {
        $receipt .= "<tr>";
        $receipt .= "<td>" . sanitizeInput($item['name']) . (isset($item['flavor']) && !empty($item['flavor']) ? " (" . sanitizeInput($item['flavor']) . ")" : "") . "</td>";
        $receipt .= "<td>" . sanitizeInput($item['size']) . "</td>";
        $receipt .= "<td>" . sanitizeInput($item['quantity']) . "</td>";
        $receipt .= "<td>₱" . number_format($item['total_price'], 2) . "</td>";
        $receipt .= "</tr>";
    }
    $receipt .= "</tbody></table>";
    $receipt .= "<p><strong>Subtotal:</strong> ₱" . number_format($subtotal, 2) . "</p>";
    $receipt .= "<p><strong>Discount:</strong> ₱" . number_format($discount, 2) . "</p>";
    $receipt .= "<p><strong>Total:</strong> ₱" . number_format($total, 2) . "</p>";
    $receipt .= "<p><strong>Amount Paid:</strong> ₱" . number_format($amount_paid, 2) . "</p>";
    $receipt .= "<p><strong>Change:</strong> ₱" . number_format($change, 2) . "</p>";
    return $receipt;
}
