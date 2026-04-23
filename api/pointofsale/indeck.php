<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';
include "db_conn.php";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function generateOrderId()
{
    return 'ORD' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
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

function processOrder($orderId, $menuItemId, $sizeId, $quantity)
{
    global $conn;

    // Start transaction for data consistency
    $conn->begin_transaction();

    try {
        // Step 1: Fetch all ingredients required for this menu item and size
        $sql = "
            SELECT ums.ingredient_id, ums.quantity_required, ui.item_name, ui.current_quantity, ui.unit, ui.cost_per_unit
            FROM unified_menu_system ums
            JOIN unified_inventory ui ON ums.ingredient_id = ui.item_id
            WHERE ums.menu_item_id = ? AND ums.size_id = ? AND ums.ingredient_id IS NOT NULL
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        $stmt->bind_param("ss", $menuItemId, $sizeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ingredients = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Step 2: Validate that sufficient stock exists for all ingredients
        foreach ($ingredients as $ing) {
            $required = floatval($ing['quantity_required']) * floatval($quantity);
            $checkStmt = $conn->prepare("SELECT current_quantity FROM unified_inventory WHERE item_id = ? LIMIT 1");
            if (!$checkStmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            $checkStmt->bind_param("i", $ing['ingredient_id']);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
            $current = isset($checkRes['current_quantity']) ? floatval($checkRes['current_quantity']) : 0;
            
            if ($current < $required) {
                throw new Exception("Insufficient stock for ingredient: " . $ing['item_name']);
            }
        }

        // Step 3: Deduct inventory and record depletion history for each ingredient
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');

        foreach ($ingredients as $ing) {
            $deduct = floatval($ing['quantity_required']) * floatval($quantity);
            $ingredientId = intval($ing['ingredient_id']);
            $ingredientName = $ing['item_name'];
            $baseUnit = $ing['unit'];
            $costPerUnit = floatval($ing['cost_per_unit']);
            $totalCost = $deduct * $costPerUnit;

            $updateStmt = $conn->prepare("UPDATE unified_inventory SET current_quantity = current_quantity - ? WHERE item_id = ?");
            if (!$updateStmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            $updateStmt->bind_param("di", $deduct, $ingredientId);
            $ok = $updateStmt->execute();
            $updateStmt->close();
            
            if ($ok === false) {
                throw new Exception("Error updating inventory for ingredient: " . $ingredientName);
            }

            // For POS orders, we treat the ordered quantity as 1 unit of the base unit
            // UNIT_ORDERED = base unit (since it's a direct deduction)
            // STOCK_PER_UNIT = 1 (no conversion needed)
            // QTY_ORDERED = the deducted amount
            $insertStmt = $conn->prepare("
                INSERT INTO depleted_history 
                (PROD_ID, PROD_NAME, QTY_ORDERED, UNIT_ORDERED, STOCK_PER_UNIT, QTY_DEPLETED, BASE_UNIT, TOT_PRICE, DATE_DEP, TIME_DEP)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if (!$insertStmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }

            $qtyOrdered = $deduct;
            $unitOrdered = $baseUnit;
            $stockPerUnit = 1.0;
            $qtyDepleted = $deduct;

            $insertStmt->bind_param(
                "isdddddsss",
                $ingredientId,
                $ingredientName,
                $qtyOrdered,
                $unitOrdered,
                $stockPerUnit,
                $qtyDepleted,
                $baseUnit,
                $totalCost,
                $currentDate,
                $currentTime
            );

            if (!$insertStmt->execute()) {
                throw new Exception("Error recording depletion history: " . $insertStmt->error);
            }
            $insertStmt->close();
        }

        // Step 4: Commit transaction
        $conn->commit();
        return true;

    } catch (Exception $e) {
        // Rollback transaction on any error
        $conn->rollback();
        error_log("processOrder Error: " . $e->getMessage());
        return false;
    }
}


function getMenuPrice($menuItemId, $sizeId)
{
    global $conn;

    $stmt = $conn->prepare("SELECT price FROM unified_menu_system WHERE menu_item_id = ? AND size_id = ? LIMIT 1");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("ss", $menuItemId, $sizeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return isset($row['price']) ? $row['price'] : 0;
}

function addOrderRecord($orderId, $total, $discount, $amountPaid, $orderType, $discountType = null)
{
    global $conn;

    $createOrdersTable = "
        CREATE TABLE IF NOT EXISTS customer_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(50) UNIQUE NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            discount DECIMAL(10,2) DEFAULT 0,
            discount_type VARCHAR(50) DEFAULT NULL,
            amount_paid DECIMAL(10,2) NOT NULL,
            order_type ENUM('dine_in', 'takeout') NOT NULL DEFAULT 'dine_in',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $conn->query($createOrdersTable);

    $checkColumn = $conn->query("SHOW COLUMNS FROM customer_orders LIKE 'discount_type'");
    if ($checkColumn->num_rows == 0) {
        $conn->query("ALTER TABLE customer_orders ADD COLUMN discount_type VARCHAR(50) DEFAULT NULL AFTER discount");
    }

    $stmt = $conn->prepare("INSERT INTO customer_orders (order_id, total, discount, discount_type, amount_paid, order_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sddsds", $orderId, $total, $discount, $discountType, $amountPaid, $orderType);
    $stmt->execute();
    $stmt->close();
}

function addOrderItemRecord($orderId, $menuItemId, $sizeId, $quantity, $price, $flavor = null, $sinkers = null, $base = null, $refills = 0)
{
    global $conn;

    $createOrderItemsTable = "
        CREATE TABLE IF NOT EXISTS customer_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(50) NOT NULL,
            menu_item_id VARCHAR(50) NOT NULL,
            size_id VARCHAR(50) NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            flavor VARCHAR(100) DEFAULT NULL,
            sinkers TEXT DEFAULT NULL,
            base VARCHAR(100) DEFAULT NULL,
            refills INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $conn->query($createOrderItemsTable);

    $checkSinkersColumn = $conn->query("SHOW COLUMNS FROM customer_order_items LIKE 'sinkers'");
    if ($checkSinkersColumn->num_rows == 0) {
        $conn->query("ALTER TABLE customer_order_items ADD COLUMN sinkers TEXT DEFAULT NULL AFTER flavor");
    }

    $checkBaseColumn = $conn->query("SHOW COLUMNS FROM customer_order_items LIKE 'base'");
    if ($checkBaseColumn->num_rows == 0) {
        $conn->query("ALTER TABLE customer_order_items ADD COLUMN base VARCHAR(100) DEFAULT NULL AFTER sinkers");
    }

    $checkRefillsColumn = $conn->query("SHOW COLUMNS FROM customer_order_items LIKE 'refills'");
    if ($checkRefillsColumn->num_rows == 0) {
        $conn->query("ALTER TABLE customer_order_items ADD COLUMN refills INT DEFAULT 0 AFTER base");
    }

    $stmt = $conn->prepare("INSERT INTO customer_order_items (order_id, menu_item_id, size_id, quantity, price, flavor, sinkers, base, refills) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiisssi", $orderId, $menuItemId, $sizeId, $quantity, $price, $flavor, $sinkers, $base, $refills);
    $stmt->execute();
    $stmt->close();
}

function generateReceipt($orderId, $cart, $subtotal, $discount, $total, $amount_paid, $change, $orderType, $discountType = null)
{
    
    $receipt = "<h3>Order ID: $orderId</h3>";
    $receipt .= "<h4>Order Type: " . ucfirst(str_replace('_', ' ', $orderType)) . "</h4>";
    $receipt .= "<h4>Order Details:</h4>";
    $receipt .= "<table>";
    $receipt .= "<tr><th>Item</th><th>Size</th><th>Quantity</th><th>Price</th></tr>";

    $totalWingsConsumed = 0;

    foreach ($cart as $item) {
        $receipt .= "<tr>";
        $itemDetails = $item['name'];

        if (isset($item['flavor']) && !empty($item['flavor'])) {
            $itemDetails .= " ({$item['flavor']})";
        }
        if (isset($item['sinkers']) && !empty($item['sinkers'])) {
            $itemDetails .= " [Sinkers: {$item['sinkers']}]";
        }
        if (isset($item['base']) && !empty($item['base'])) {
            $itemDetails .= " [Base: {$item['base']}]";
        }

        if (isset($item['refills']) && $item['refills'] > 0 && $item['size'] === 'UNLI' && stripos($item['name'], 'wings') !== false) {
            $baseWings = 10 * $item['quantity'];
            $refillWings = $item['refills'] * 5;
            $itemTotalWings = $baseWings + $refillWings;
            $totalWingsConsumed += $itemTotalWings;

            $receipt .= "<td>{$itemDetails}</td>";
            $receipt .= "<td>{$item['size']}</td>";
            $receipt .= "<td>{$item['quantity']} (Refills: {$item['refills']}) <br><strong>Total Wings: {$itemTotalWings} pcs</strong></td>";
            $receipt .= "<td>₱" . number_format($item['total_price'], 2) . "</td>";
            $receipt .= "</tr>";
        } else if ($item['size'] === 'UNLI' && stripos($item['name'], 'wings') !== false) {
            $baseWings = 10 * $item['quantity'];
            $totalWingsConsumed += $baseWings;

            $receipt .= "<td>{$itemDetails}</td>";
            $receipt .= "<td>{$item['size']}</td>";
            $receipt .= "<td>{$item['quantity']} <br><strong>Total Wings: {$baseWings} pcs</strong></td>";
            $receipt .= "<td>₱" . number_format($item['total_price'], 2) . "</td>";
            $receipt .= "</tr>";
        } else {
            $receipt .= "<td>{$itemDetails}</td>";
            $receipt .= "<td>{$item['size']}</td>";
            $receipt .= "<td>{$item['quantity']}</td>";
            $receipt .= "<td>₱" . number_format($item['total_price'], 2) . "</td>";
            $receipt .= "</tr>";
        }
    }
    $receipt .= "</table>";

    if ($totalWingsConsumed > 0) {
        $receipt .= "<div style='background: #fff3cd; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #ffc107;'>";
        $receipt .= "<p style='margin: 0; font-size: 18px; font-weight: bold; color: #856404;'>";
        $receipt .= "🍗 TOTAL WINGS CONSUMED: {$totalWingsConsumed} pieces";
        $receipt .= "</p></div>";
    }

    $receipt .= "<p><strong>Subtotal:</strong> ₱" . number_format($subtotal, 2) . "</p>";

    if ($discount > 0 && $discountType) {
        $receipt .= "<p><strong>Discount (" . htmlspecialchars($discountType) . "):</strong> -₱" . number_format($discount, 2) . "</p>";
    } else if ($discount > 0) {
        $receipt .= "<p><strong>Discount:</strong> -₱" . number_format($discount, 2) . "</p>";
    }

    $receipt .= "<p><strong>Total:</strong> ₱" . number_format($total, 2) . "</p>";
    $receipt .= "<p><strong>Amount Paid:</strong> ₱" . number_format($amount_paid, 2) . "</p>";
    $receipt .= "<p><strong>Change:</strong> ₱" . number_format($change, 2) . "</p>";
    return $receipt;
}

$createVoidLogsTable = "
    CREATE TABLE IF NOT EXISTS void_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(100) NOT NULL,
        size VARCHAR(50) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        flavor VARCHAR(100) DEFAULT NULL,
        void_type VARCHAR(20) NOT NULL,
        voided_by VARCHAR(50) NOT NULL,
        pin_used VARCHAR(255) NOT NULL,
        void_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
";
$conn->query($createVoidLogsTable);

$createDiscountLogsTable = "
    CREATE TABLE IF NOT EXISTS discount_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        discount_percentage DECIMAL(5,2) NOT NULL,
        discount_type VARCHAR(50) DEFAULT NULL,
        pwd_id VARCHAR(100) DEFAULT NULL,
        item_name VARCHAR(100) DEFAULT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        discount_amount DECIMAL(10,2) NOT NULL,
        applied_by VARCHAR(50) NOT NULL,
        pin_used VARCHAR(255) NOT NULL,
        applied_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
";
$conn->query($createDiscountLogsTable);

$checkDiscountColumn = $conn->query("SHOW COLUMNS FROM discount_logs LIKE 'discount_type'");
if ($checkDiscountColumn && $checkDiscountColumn->num_rows == 0) {
    $conn->query("ALTER TABLE discount_logs ADD COLUMN discount_type VARCHAR(50) DEFAULT NULL AFTER discount_percentage");
}

$checkPwdIdColumn = $conn->query("SHOW COLUMNS FROM discount_logs LIKE 'pwd_id'");
if ($checkPwdIdColumn && $checkPwdIdColumn->num_rows == 0) {
    $conn->query("ALTER TABLE discount_logs ADD COLUMN pwd_id VARCHAR(100) DEFAULT NULL AFTER discount_type");
}

$checkItemNameColumn = $conn->query("SHOW COLUMNS FROM discount_logs LIKE 'item_name'");
if ($checkItemNameColumn && $checkItemNameColumn->num_rows == 0) {
    $conn->query("ALTER TABLE discount_logs ADD COLUMN item_name VARCHAR(100) DEFAULT NULL AFTER pwd_id");
}

function logVoidOperation($item, $voidType, $pin)
{
    global $conn;

    if (!isset($item['name']) || !isset($item['size']) || !isset($item['quantity']) || !isset($item['total_price'])) {
        error_log("Invalid item data for void operation: " . print_r($item, true));
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO void_logs (item_name, size, quantity, price, flavor, void_type, voided_by, pin_used) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $voidedBy = "Manager";
    $flavor = isset($item['flavor']) ? $item['flavor'] : null;
    $stmt->bind_param("ssiissss", $item['name'], $item['size'], $item['quantity'], $item['total_price'], $flavor, $voidType, $voidedBy, $pin);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function logDiscountOperation($discountPercentage, $subtotal, $discountAmount, $pin, $discountType = null, $pwdId = null)
{
    global $conn;

    $stmt = $conn->prepare("INSERT INTO discount_logs (discount_percentage, discount_type, pwd_id, subtotal, discount_amount, applied_by, pin_used) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $appliedBy = "Manager";
    $stmt->bind_param("dssddss", $discountPercentage, $discountType, $pwdId, $subtotal, $discountAmount, $appliedBy, $pin);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function verifyPin($pin)
{
    $validPins = ['1234', '5678', '0000'];
    return in_array($pin, $validPins);
}

$active_section = isset($_GET['section']) ? $_GET['section'] : 'drinks';
$total = 0;
$subtotal = 0;
$payment_message = '';
$change = 0;
$discount = 0;
$discount_message = '';

if (!isset($_SESSION['order_type'])) {
    $_SESSION['order_type'] = 'dine_in';
}

if (isset($_POST['set_order_type'])) {
    $_SESSION['order_type'] = $_POST['order_type'];
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$menu = [];

$sections_query = "SELECT DISTINCT section FROM unified_menu_system WHERE is_available = 1 ORDER BY section";
$sections_result = $conn->query($sections_query);

if ($sections_result) {
    while ($section_row = $sections_result->fetch_assoc()) {
        $section = $section_row['section'];

        $categories_query = "SELECT DISTINCT category FROM unified_menu_system WHERE section = ? AND is_available = 1 ORDER BY category";
        $categories_stmt = $conn->prepare($categories_query);
        $categories_stmt->bind_param("s", $section);
        $categories_stmt->execute();
        $categories_result = $categories_stmt->get_result();

        while ($category_row = $categories_result->fetch_assoc()) {
            $category = $category_row['category'];
            $menu[$section][$category] = [];

            $items_query = "SELECT DISTINCT menu_item_id, menu_item_name, size_id, price 
                            FROM unified_menu_system 
                            WHERE section = ? AND category = ? AND is_available = 1
                            ORDER BY menu_item_name, 
                            CASE size_id 
                                WHEN 'REG' THEN 1 
                                WHEN 'GRANDE' THEN 2 
                                WHEN 'VENTI' THEN 3 
                                WHEN 'UNLI' THEN 4 
                                ELSE 5 
                            END";
            $items_stmt = $conn->prepare($items_query);
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

$available_sinkers = ['Pearl', 'Crystal', 'Coffee Jelly'];
$available_bases = ['Water', 'Coffee', 'Tea'];
$chicken_flavors = ['Buffalo', 'BBQ', 'Honey Garlic', 'Spicy', 'Original', 'Teriyaki', 'Salted Egg'];

if (isset($_POST['add_to_cart'])) {
    $item = $_POST['item'];
    $category = $_POST['category'];
    $section = $_POST['section'];
    $size = $_POST['size'];

    if (isset($menu[$section][$category][$item][$size])) {
        $menu_item_id = $menu[$section][$category][$item][$size]['id'];
        $price = $menu[$section][$category][$item][$size]['price'];
        $flavor = isset($_POST['flavor']) ? $_POST['flavor'] : '';

        $sinkers = '';
        $base = '';
        if ($section === 'drinks') {
            if (isset($_POST['sinkers']) && is_array($_POST['sinkers'])) {
                $sinkers = implode(', ', $_POST['sinkers']);
            }
            $base = isset($_POST['base']) ? $_POST['base'] : '';
        }

        $availability_check = $conn->prepare("SELECT is_available FROM unified_menu_system WHERE menu_item_id = ? AND size_id = ? LIMIT 1");
        $availability_check->bind_param("ss", $menu_item_id, $size);
        $availability_check->execute();
        $availability_result = $availability_check->get_result();
        $is_available = $availability_result->fetch_assoc()['is_available'] ?? 0;
        $availability_check->close();

        if (!$is_available) {
            echo "<script>alert('This item is no longer available. The menu has been updated in the management system.');</script>";
        } else if (checkStockAvailability($menu_item_id, $size, 1)) {
            $item_index = false;

            foreach ($_SESSION['cart'] as $index => $cart_item) {
                $same_sinkers = ($cart_item['sinkers'] ?? '') === $sinkers;
                $same_base = ($cart_item['base'] ?? '') === $base;
                $same_flavor = ($cart_item['flavor'] ?? '') === $flavor;

                if (
                    $cart_item['id'] === $menu_item_id &&
                    $cart_item['size'] === $size &&
                    $same_flavor &&
                    $same_sinkers &&
                    $same_base
                ) {
                    $item_index = $index;
                    break;
                }
            }

            if ($item_index !== false) {
                if (checkStockAvailability($menu_item_id, $size, $_SESSION['cart'][$item_index]['quantity'] + 1)) {
                    $_SESSION['cart'][$item_index]['quantity']++;
                    $_SESSION['cart'][$item_index]['total_price'] += $price;
                } else {
                    echo "<script>alert('Not enough stock available for " . addslashes($item) . "');</script>";
                }
            } else {
                $_SESSION['cart'][] = [
                    'id' => $menu_item_id,
                    'name' => $item,
                    'size' => $size,
                    'price' => $price,
                    'quantity' => 1,
                    'total_price' => $price,
                    'flavor' => $flavor,
                    'sinkers' => $sinkers,
                    'base' => $base,
                    'refills' => 0
                ];
            }
        } else {
            echo "<script>alert('Not enough stock available for " . addslashes($item) . "');</script>";
        }
    }
}

if (isset($_POST['add_refill'])) {
    $index = intval($_POST['index']);
    if (isset($_SESSION['cart'][$index])) {
        $item = $_SESSION['cart'][$index];

        if ($item['size'] === 'UNLI' && stripos($item['name'], 'wings') !== false) {
            if (!isset($_SESSION['cart'][$index]['refills'])) {
                $_SESSION['cart'][$index]['refills'] = 0;
            }

            $_SESSION['cart'][$index]['refills']++;
            $_SESSION['cart'][$index]['total_price'] += 5;

            $baseWings = 10 * $item['quantity'];
            $totalWings = $baseWings + ($_SESSION['cart'][$index]['refills'] * 5);

            echo "<script>alert('Refill added! +5 wings\\nTotal refills: " . $_SESSION['cart'][$index]['refills'] . "\\nTotal wings: {$totalWings} pieces');</script>";
        } else {
            echo "<script>alert('Refills are only available for Unli Wings!');</script>";
        }
    }
}

if (isset($_POST['void_item'])) {
    $index = intval($_POST['index']);
    $pin = $_POST['void_pin'];

    if (verifyPin($pin)) {
        if (isset($_SESSION['cart']) && isset($_SESSION['cart'][$index])) {
            if (logVoidOperation($_SESSION['cart'][$index], 'Single Item', $pin)) {
                unset($_SESSION['cart'][$index]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
                echo "<script>alert('Item voided successfully!');</script>";
            } else {
                echo "<script>alert('Error logging void operation.');</script>";
            }
        } else {
            echo "<script>alert('Invalid item selected for void.');</script>";
        }
    } else {
        echo "<script>alert('Invalid PIN. Void operation cancelled.');</script>";
    }
}

if (isset($_POST['void_all'])) {
    $pin = $_POST['void_all_pin'];

    if (verifyPin($pin)) {
        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            $allLogged = true;
            foreach ($_SESSION['cart'] as $item) {
                if (!logVoidOperation($item, 'Void All', $pin)) {
                    $allLogged = false;
                }
            }

            if ($allLogged) {
                $_SESSION['cart'] = [];
                echo "<script>alert('All items voided successfully!');</script>";
            } else {
                echo "<script>alert('Error logging some void operations.');</script>";
            }
        } else {
            echo "<script>alert('No items in cart to void.');</script>";
        }
    } else {
        echo "<script>alert('Invalid PIN. Void all operation cancelled.');</script>";
    }
}

if (isset($_POST['increase_quantity'])) {
    $index = intval($_POST['index']);
    if (isset($_SESSION['cart'][$index])) {
        $menu_item_id = $_SESSION['cart'][$index]['id'];
        $size = $_SESSION['cart'][$index]['size'];

        $availability_check = $conn->prepare("SELECT is_available FROM unified_menu_system WHERE menu_item_id = ? AND size_id = ? LIMIT 1");
        $availability_check->bind_param("ss", $menu_item_id, $size);
        $availability_check->execute();
        $availability_result = $availability_check->get_result();
        $is_available = $availability_result->fetch_assoc()['is_available'] ?? 0;
        $availability_check->close();

        if (!$is_available) {
            echo "<script>alert('This item is no longer available. Please remove it from your cart.');</script>";
        } else if (checkStockAvailability($menu_item_id, $size, $_SESSION['cart'][$index]['quantity'] + 1)) {
            $_SESSION['cart'][$index]['quantity']++;
            $_SESSION['cart'][$index]['total_price'] += $_SESSION['cart'][$index]['price'];
        } else {
            echo "<script>alert('Not enough stock available for " . addslashes($_SESSION['cart'][$index]['name']) . "');</script>";
        }
    }
}

if (isset($_POST['decrease_quantity'])) {
    $index = intval($_POST['index']);
    if (isset($_SESSION['cart'][$index])) {
        if ($_SESSION['cart'][$index]['quantity'] > 1) {
            $_SESSION['cart'][$index]['quantity']--;
            $_SESSION['cart'][$index]['total_price'] -= $_SESSION['cart'][$index]['price'];
        } else {
            echo "<script>
                if (confirm('Do you want to void this item?')) {
                    document.getElementById('voidItemIndex').value = " . $index . ";
                    document.getElementById('voidItemModal').style.display = 'block';
                }
            </script>";
        }
    }
}

if (isset($_POST['apply_discount'])) {
    $discount_percentage = floatval($_POST['discount']);
    $pin = $_POST['discount_pin'];
    $pwd_id = isset($_POST['pwd_id']) && !empty($_POST['pwd_id']) ? $_POST['pwd_id'] : null;

    if (verifyPin($pin)) {
        $current_subtotal = 0;
        if (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                $current_subtotal += $item['total_price'];
            }
        }

        $discount_amount = $current_subtotal * ($discount_percentage / 100);

        $discount_type = 'Custom ' . $discount_percentage . '%';
        if (logDiscountOperation($discount_percentage, $current_subtotal, $discount_amount, $pin, $discount_type, $pwd_id)) {
            $_SESSION['discount'] = $discount_percentage;
            $_SESSION['discount_type'] = $discount_type;
            $_SESSION['pwd_id'] = $pwd_id;
            $discount_message = "Discount of {$discount_percentage}% applied successfully!";
            if ($pwd_id) {
                $discount_message .= " (PWD ID: " . htmlspecialchars($pwd_id) . ")";
            }
            echo "<script>alert('Discount applied successfully!');</script>";
        } else {
            echo "<script>alert('Error logging discount operation.');</script>";
        }
    } else {
        echo "<script>alert('Invalid PIN. Discount operation cancelled.');</script>";
    }
}

if (isset($_POST['apply_senior_pwd_discount'])) {
    $pin = $_POST['senior_pwd_pin'];
    $discount_type = $_POST['senior_pwd_type'];
    $pwd_id = isset($_POST['pwd_id']) && !empty($_POST['pwd_id']) ? $_POST['pwd_id'] : null;

    if (verifyPin($pin)) {
        $current_subtotal = 0;
        if (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                $current_subtotal += $item['total_price'];
            }
        }

        $discount_percentage = 20;
        $discount_amount = $current_subtotal * 0.20;

        if (logDiscountOperation($discount_percentage, $current_subtotal, $discount_amount, $pin, $discount_type, $pwd_id)) {
            $_SESSION['discount'] = $discount_percentage;
            $_SESSION['discount_type'] = $discount_type;
            $_SESSION['pwd_id'] = $pwd_id;
            $discount_message = "$discount_type 20% discount applied successfully!";
            if ($pwd_id) {
                $discount_message .= " (PWD ID: " . htmlspecialchars($pwd_id) . ")";
            }
            echo "<script>alert('$discount_type 20% discount applied successfully!');</script>";
        } else {
            echo "<script>alert('Error logging discount operation.');</script>";
        }
    } else {
        echo "<script>alert('Invalid PIN. Discount operation cancelled.');</script>";
    }
}

$total = 0;
$subtotal = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['total_price'];
    }
    $total = $subtotal;
    if (isset($_SESSION['discount'])) {
        $discount = $_SESSION['discount'];
        $total = $subtotal - ($subtotal * ($discount / 100));
    }
}

if (isset($_POST['pay'])) {
    $amount_paid = floatval($_POST['amount_paid']);
    if ($amount_paid >= $total) {
        $change = $amount_paid - $total;
        $payment_message = "Payment successful! Change: ₱" . number_format($change, 2);
    } else {
        $payment_message = "Insufficient payment. Please pay the full amount.";
    }
}

if (isset($_POST['complete_order'])) {
    $change = floatval($_POST['change']);
    $amount_paid = floatval($_POST['amount_paid']);
    $discount_amount = $subtotal - $total;
    $orderId = generateOrderId();
    $orderType = $_SESSION['order_type'];
    $discountType = isset($_SESSION['discount_type']) ? $_SESSION['discount_type'] : null;

    $conn->begin_transaction();

    try {
        addOrderRecord($orderId, $total, $discount_amount, $amount_paid, $orderType, $discountType);

        foreach ($_SESSION['cart'] as $item) {
            $final_check = $conn->prepare("SELECT is_available FROM unified_menu_system WHERE menu_item_id = ? AND size_id = ? LIMIT 1");
            $final_check->bind_param("ss", $item['id'], $item['size']);
            $final_check->execute();
            $final_result = $final_check->get_result();
            $still_available = $final_result->fetch_assoc()['is_available'] ?? 0;
            $final_check->close();

            if (!$still_available) {
                throw new Exception("Item " . $item['name'] . " is no longer available. Order cancelled. Please check the menu management system.");
            }

            if (checkStockAvailability($item['id'], $item['size'], $item['quantity'])) {
                if (!processOrder($orderId, $item['id'], $item['size'], $item['quantity'])) {
                    throw new Exception("Failed to process order for " . $item['name']);
                }

                addOrderItemRecord(
                    $orderId,
                    $item['id'],
                    $item['size'],
                    $item['quantity'],
                    $item['price'],
                    $item['flavor'] ?? null,
                    $item['sinkers'] ?? null,
                    $item['base'] ?? null,
                    $item['refills'] ?? 0
                );
            } else {
                throw new Exception("Not enough stock available for " . $item['name']);
            }
        }

        $conn->commit();

        $receipt_content = generateReceipt($orderId, $_SESSION['cart'], $subtotal, $discount_amount, $total, $amount_paid, $change, $orderType, $discountType);

        $payment_message = "Order completed. Change given: ₱" . number_format($change, 2);

        $_SESSION['receipt'] = $receipt_content;

        $_SESSION['cart'] = [];
        unset($_SESSION['discount']);
        unset($_SESSION['discount_type']);
        unset($_SESSION['pwd_id']);
        $_SESSION['order_type'] = 'dine_in';
        $total = 0;
        $subtotal = 0;
        $change = 0;
        $discount = 0;
        $discount_message = '';
    } catch (Exception $e) {
        $conn->rollback();
        $payment_message = "Error: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bubble Hideout POS</title>
    <link rel="stylesheet" href="/fonts/fonts.css">
    </link>

</head>

<style>
    :root {
        --primary-color: #4a90e2;
        --secondary-color: #f5a623;
        --background-color: #f8f9fa;
        --text-color: #333;
        --border-color: #e0e0e0;
        --success-color: #41af50;
        --error-color: #f44336;
    }

    body {
        font-family: "Poppins", sans-serif;
        margin: 0;
        padding: 0;
        background-color: var(--background-color);
        color: var(--text-color);
    }

    .container {
        display: flex;
        min-height: calc(100vh - 60px);
    }

    .menu-section {
        flex: 3;
        padding: 20px;
        background-color: #fff;
        overflow-y: auto;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .order-section {
        flex: 2;
        padding: 20px;
        background-color: #fff;
        border-left: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
    }

    h1,
    h2,
    h3 {
        margin-top: 0;
        color: #337609;
    }

    .category-buttons,
    .section-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }

    .category-button,
    .section-button {
        padding: 10px 15px;
        background-color: #337609;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        text-decoration: none;
        color: #fff;
    }

    .category-button.active,
    .section-button.active {
        background-color: #798378;
        color: white;
    }

    /* Fix menu category display */
    .menu-category {
        margin-bottom: 20px;
    }

    /* Fix menu items display */
    .menu-items {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .menu-item {
        background-color: #fff !important;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .item-name {
        font-weight: 600;
        margin-bottom: 10px;
        color: #337609;
        display: block;
    }

    .size-buttons {
        display: flex;
        justify-content: center;
        gap: 5px;
        flex-wrap: wrap;
    }

    .size-button {
        padding: 8px 12px;
        background-color: #337609;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        font-weight: 500;
        margin-top: 5px;
    }

    .size-button:hover {
        background-color: #2a5e07;
    }

    .cart-items {
        flex-grow: 1;
        overflow-y: auto;
        max-height: 300px;
        margin-bottom: 15px;
    }

    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        margin-bottom: 10px;
        background-color: #f9f9f9;
        border-radius: 8px;
        border-left: 4px solid #337609;
        transition: all 0.3s ease;
        flex-wrap: wrap;
    }

    .cart-item:hover {
        background-color: #f5f5f5;
        transform: translateX(2px);
    }

    .cart-item-info {
        flex: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-right: 15px;
    }

    .cart-item-details {
        font-weight: 500;
        color: #333;
    }

    .cart-item-price {
        font-weight: 600;
        color: #337609;
        font-size: 16px;
    }

    .remove-button {
        background-color: var(--error-color);
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .remove-button:hover {
        background-color: #d32f2f;
    }

    /* Enhanced Void Button Styles */
    .void-button {
        background: linear-gradient(135deg, #ff4757, #ff3742);
        color: white;
        border: none;
        border-radius: 6px;
        padding: 8px 12px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(255, 71, 87, 0.3);
        min-width: 70px;
    }

    .void-button:hover {
        background: linear-gradient(135deg, #ff3742, #ff2f3a);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(255, 71, 87, 0.4);
    }

    .void-button:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(255, 71, 87, 0.3);
    }

    .void-all-container {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #e0e0e0;
        text-align: center;
    }

    .void-all-button {
        background: linear-gradient(135deg, #c0392b, #a93226);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 24px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        transition: all 0.3s ease;
        box-shadow: 0 3px 6px rgba(192, 57, 43, 0.4);
        min-width: 120px;
    }

    .void-all-button:hover {
        background: linear-gradient(135deg, #a93226, #922b21);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(192, 57, 43, 0.5);
    }

    .void-all-button:active {
        transform: translateY(0);
        box-shadow: 0 3px 6px rgba(192, 57, 43, 0.4);
    }

    .total {
        font-size: 24px;
        font-weight: bold;
        margin-top: 20px;
        text-align: right;
        color: var(--primary-color);
    }

    .checkout-form {
        margin-top: 20px;
    }

    .checkout-form input {
        width: 100%;
        padding: 12px;
        margin-bottom: 10px;
        border: 1px solid var(--border-color);
        border-radius: 5px;
        font-size: 16px;
    }

    .checkout-form button {
        width: 100%;
        padding: 12px;
        background-color: var(--success-color);
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        transition: background-color 0.3s ease;
    }

    .checkout-form button:hover {
        background-color: #45a049;
    }

    .message {
        margin-top: 10px;
        padding: 10px;
        background-color: #e8f5e9;
        border-radius: 5px;
        color: var(--success-color);
        font-weight: 500;
    }

    .discount-button {
        background-color: #a2c39b;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        transition: background-color 0.3s ease;
        margin-bottom: 10px;
        width: 100%;
    }

    .discount-button:hover {
        background-color: #798378;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.6);
    }

    .modal-content {
        background-color: #fff;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 90%;
        max-width: 400px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .modal-content h2 {
        margin-top: 0;
        color: #a2c39b;
    }

    .modal-content input {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        box-sizing: border-box;
    }

    .modal-content button {
        width: 100%;
        padding: 10px;
        background-color: #a2c39b;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        transition: background-color 0.3s ease;
    }

    .modal-content button:hover {
        background-color: #798378;
    }

    /* Enhanced Modal Button Styling */
    .modal-content button[type="submit"] {
        background: linear-gradient(135deg, #337609, #2a5e07);
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(51, 118, 9, 0.3);
    }

    .modal-content button[type="submit"]:hover {
        background: linear-gradient(135deg, #2a5e07, #1e4205);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(51, 118, 9, 0.4);
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
    }

    .subtotal,
    .discount {
        font-size: 18px;
        margin-bottom: 5px;
    }

    .modal-content table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }

    .modal-content table th,
    .modal-content table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }

    .modal-content table th {
        background-color: #f2f2f2;
    }

    .quantity-controls {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-right: 15px;
    }

    .quantity-button {
        background: linear-gradient(135deg, #337609, #2a5e07);
        color: white;
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(51, 118, 9, 0.3);
    }

    .quantity-button:hover {
        background: linear-gradient(135deg, #2a5e07, #1e4205);
        transform: scale(1.1);
        box-shadow: 0 3px 6px rgba(51, 118, 9, 0.4);
    }

    .quantity {
        font-weight: 600;
        font-size: 16px;
        min-width: 30px;
        text-align: center;
        color: #333;
    }

    .header {
        background-color: #8b4513;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .back-button {
        background-color: #337609;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 20px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
    }

    .employee-text {
        color: white;
        font-weight: 500;
    }

    .flavor-select {
        margin-top: 10px;
        padding: 5px;
        border-radius: 4px;
        border: 1px solid #ccc;
        width: 100%;
    }

    .view-orders-button {
        background-color: #337609;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 20px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
    }

    .void_logs-button:hover {
        background-color: #337609;
    }

    /* Fix responsive layout for mobile */
    @media (max-width: 768px) {
        .container {
            flex-direction: column;
        }

        .menu-section,
        .order-section {
            flex: none;
            width: 100%;
        }

        .cart-items {
            max-height: 300px;
        }

        .cart-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .quantity-controls {
            margin: 10px 0;
        }

        .void-button {
            margin-top: 10px;
            width: 100%;
        }

        .void-all-button {
            width: 100%;
        }
    }

    /* Fix for menu category display */
    .menu-category {
        display: none;
        /* Initially hidden */
    }

    .menu-category h2 {
        margin-bottom: 20px;
    }

    /* Fix for form display in menu items */
    .menu-item form {
        display: block;
        width: 100%;
    }

    .view-orders-button {
        display: inline-block;
        padding: 12px 20px;
        background-color: #f39c12;
        color: white;
        text-decoration: none;
        border-radius: 25px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11);
    }

    .view-orders-button:hover {
        background-color: #e67e22;
        transform: translateY(-2px);
        box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1);
    }
</style>

<body>
    <div class="header">
        <a href="/interface/homepage.php" class="back-button">Back</a>
        <a href="void_logs.php" class="view-orders-button">View Void Logs</a>
        <a href="discount_logs.php" class="view-orders-button">View Discount Logs</a>
    </div>
    <div class="container">
        <div class="menu-section">
            <h1>Bubble Hideout POS</h1>

            <div class="order-type-section">
                <h3>Order Type</h3>
                <form method="post" class="order-type-form">
                    <label class="order-type-option">
                        <input type="radio" name="order_type" value="dine_in" <?php echo $_SESSION['order_type'] == 'dine_in' ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <span class="order-type-label">Dine In</span>
                    </label>
                    <label class="order-type-option">
                        <input type="radio" name="order_type" value="takeout" <?php echo $_SESSION['order_type'] == 'takeout' ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <span class="order-type-label">Takeout</span>
                    </label>
                    <input type="hidden" name="set_order_type" value="1">
                </form>
            </div>

            <div class="section-buttons">
                <a href="?section=drinks"
                    class="section-button <?php echo $active_section == 'drinks' ? 'active' : ''; ?>">Drinks</a>
                <a href="?section=food"
                    class="section-button <?php echo $active_section == 'food' ? 'active' : ''; ?>">Food</a>
                <a href="?section=addons"
                    class="section-button <?php echo $active_section == 'addons' ? 'active' : ''; ?>">Add ons</a>
            </div>

            <?php if (isset($menu[$active_section]) && !empty($menu[$active_section])): ?>
                <div class="category-buttons">
                    <?php foreach ($menu[$active_section] as $category => $items): ?>
                        <button class="category-button"
                            onclick="showCategory('<?php echo htmlspecialchars($category); ?>')"><?php echo htmlspecialchars($category); ?></button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($menu[$active_section] as $category => $items): ?>
                    <div class="menu-category" id="<?php echo htmlspecialchars($category); ?>" style="display: none;">
                        <h2><?php echo htmlspecialchars($category); ?></h2>
                        <div class="menu-items">
                            <?php foreach ($items as $item => $sizes): ?>
                                <div class="menu-item">
                                    <div class="item-name"><?php echo htmlspecialchars($item); ?></div>
                                    <div class="size-buttons">
                                        <?php foreach ($sizes as $size => $data): ?>
                                            <?php if ($active_section === 'drinks'): ?>
                                                <button type="button" class="size-button"
                                                    onclick="openDrinkCustomizationModal('<?php echo htmlspecialchars($item); ?>', '<?php echo htmlspecialchars($category); ?>', '<?php echo htmlspecialchars($active_section); ?>', '<?php echo htmlspecialchars($size); ?>', <?php echo $data['price']; ?>)">
                                                    <?php echo htmlspecialchars($size); ?><br>₱<?php echo number_format($data['price'], 2); ?>
                                                </button>
                                            <?php else: ?>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="item" value="<?php echo htmlspecialchars($item); ?>">
                                                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                                                    <input type="hidden" name="section"
                                                        value="<?php echo htmlspecialchars($active_section); ?>">
                                                    <input type="hidden" name="size" value="<?php echo htmlspecialchars($size); ?>">
                                                    <?php if (strpos(strtolower($category), 'wings') !== false || strpos(strtolower($category), 'chicken') !== false): ?>
                                                        <select name="flavor" class="flavor-select" required>
                                                            <option value="">Select Flavor</option>
                                                            <?php foreach ($chicken_flavors as $flavor): ?>
                                                                <option value="<?php echo htmlspecialchars($flavor); ?>">
                                                                    <?php echo htmlspecialchars($flavor); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php endif; ?>
                                                    <button type="submit" name="add_to_cart" class="size-button">
                                                        <?php echo htmlspecialchars($size); ?><br>₱<?php echo number_format($data['price'], 2); ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No menu items available for this section. Please check the menu management system.</p>
            <?php endif; ?>
        </div>

        <div class="order-section">
            <h2>Current Order</h2>
            <div class="current-order-type">
                <strong>Order Type: <?php echo ucfirst(str_replace('_', ' ', $_SESSION['order_type'])); ?></strong>
            </div>
            <div class="cart-items">
                <?php if (empty($_SESSION['cart'])): ?>
                    <p>Your cart is empty.</p>
                <?php else: ?>
                    <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                        <div class="cart-item">
                            <div class="cart-item-info">
                                <div class="cart-item-details">
                                    <?php
                                    echo htmlspecialchars($item['name']) . ' (' . htmlspecialchars($item['size']) . ')';
                                    if (!empty($item['flavor'])) {
                                        echo ' - ' . htmlspecialchars($item['flavor']);
                                    }
                                    if (!empty($item['sinkers'])) {
                                        echo '<br><small>Sinkers: ' . htmlspecialchars($item['sinkers']) . '</small>';
                                    }
                                    if (!empty($item['base'])) {
                                        echo '<br><small>Base: ' . htmlspecialchars($item['base']) . '</small>';
                                    }
                                    if (isset($item['refills']) && $item['refills'] > 0 && $item['size'] === 'UNLI' && stripos($item['name'], 'wings') !== false) {
                                        $baseWings = 10 * $item['quantity'];
                                        $totalWings = $baseWings + ($item['refills'] * 5);
                                        echo '<br><small class="refills-info">Refills: ' . $item['refills'] . ' (₱' . number_format($item['refills'] * 5, 2) . ')</small>';
                                        echo '<br><small class="wings-count">🍗 Total Wings: ' . $totalWings . ' pieces</small>';
                                    } else if ($item['size'] === 'UNLI' && stripos($item['name'], 'wings') !== false) {
                                        $baseWings = 10 * $item['quantity'];
                                        echo '<br><small class="wings-count">🍗 Total Wings: ' . $baseWings . ' pieces</small>';
                                    }
                                    ?>
                                </div>
                                <div class="cart-item-price">₱<?php echo number_format($item['total_price'], 2); ?></div>
                            </div>
                            <div class="quantity-controls">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                    <button type="submit" name="decrease_quantity" class="quantity-button">−</button>
                                </form>
                                <span class="quantity"><?php echo $item['quantity']; ?></span>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                    <button type="submit" name="increase_quantity" class="quantity-button">+</button>
                                </form>
                            </div>
                            <div class="cart-item-actions">
                                <button onclick="confirmVoidItem(<?php echo $index; ?>)" class="void-button">Void Item</button>
                                <button onclick="openSeniorPwdModal()" class="senior-pwd-button-inline">Senior/PWD 20%</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="void-all-container">
                        <button onclick="confirmVoidAll()" class="void-all-button">Void All</button>
                    </div>
                <?php endif; ?>
            </div>
            <button id="discountBtn" class="discount-button">Apply Discount</button>
            <?php if (isset($discount_message) && !empty($discount_message)): ?>
                <div class="message"><?php echo htmlspecialchars($discount_message); ?></div>
            <?php endif; ?>
            <div class="total">
                <?php if (isset($_SESSION['discount']) && $_SESSION['discount'] > 0): ?>
                    <div class="subtotal">Subtotal: ₱<?php echo number_format($subtotal, 2); ?></div>
                    <div class="discount">
                        Discount
                        (<?php echo isset($_SESSION['discount_type']) ? htmlspecialchars($_SESSION['discount_type']) : $_SESSION['discount'] . '%'; ?>):
                        -₱<?php echo number_format($subtotal - $total, 2); ?>
                    </div>
                <?php endif; ?>
                Total: ₱<?php echo number_format($total, 2); ?>
            </div>
            <div class="checkout-form">
                <form method="post">
                    <input type="number" name="amount_paid" step="0.01" min="0" placeholder="Amount Paid" required>
                    <button type="submit" name="pay">Process Payment</button>
                </form>
            </div>
            <?php if (!empty($payment_message)): ?>
                <div class="message"><?php echo htmlspecialchars($payment_message); ?></div>
            <?php endif; ?>
            <?php if (isset($change) && $change >= 0 && isset($_POST['pay'])): ?>
                <form method="post" class="checkout-form" id="completeOrderForm">
                    <input type="hidden" name="change" value="<?php echo $change; ?>">
                    <input type="hidden" name="amount_paid" value="<?php echo $amount_paid; ?>">
                    <button type="submit" name="complete_order">Complete Order</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div id="drinkCustomizationModal" class="modal">
        <div class="modal-content drink-modal-content">
            <span class="close" onclick="closeDrinkCustomizationModal()">&times;</span>
            <h2 id="drinkModalTitle">Customize Your Drink</h2>
            <form method="post" id="drinkCustomizationForm">
                <input type="hidden" name="item" id="drinkItem">
                <input type="hidden" name="category" id="drinkCategory">
                <input type="hidden" name="section" id="drinkSection">
                <input type="hidden" name="size" id="drinkSize">

                <div class="customization-section">
                    <h3>Select Sinkers (Optional - Multiple Selection)</h3>
                    <div class="sinkers-grid">
                        <?php foreach ($available_sinkers as $sinker): ?>
                            <label class="sinker-option">
                                <input type="checkbox" name="sinkers[]" value="<?php echo htmlspecialchars($sinker); ?>">
                                <span><?php echo htmlspecialchars($sinker); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" name="add_to_cart" class="add-to-cart-button">Add to Cart</button>
            </form>
        </div>
    </div>

    <div id="discountModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Apply Discount - Enter PIN</h2>
            <form method="post">
                <input type="number" name="discount" min="0" max="100" step="0.01" placeholder="Discount %" required>
                <input type="password" name="discount_pin" placeholder="Enter PIN" required>
                <input type="text" name="pwd_id" placeholder="Enter PWD ID (Optional)">
                <button type="submit" name="apply_discount">Apply Discount</button>
            </form>
        </div>
    </div>

    <div id="seniorPwdModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeSeniorPwdModal()">&times;</span>
            <h2>Apply Senior/PWD 20% Discount</h2>
            <form method="post">
                <div class="discount-type-selection">
                    <label class="discount-type-radio">
                        <input type="radio" name="senior_pwd_type" value="Senior" required>
                        <span>Senior Citizen</span>
                    </label>
                    <label class="discount-type-radio">
                        <input type="radio" name="senior_pwd_type" value="PWD" required onchange="togglePwdIdField()">
                        <span>PWD (Person with Disability)</span>
                    </label>
                </div>
                <div id="pwdIdField" style="display: none; margin: 15px 0;">
                    <label for="pwd_id_input" style="display: block; margin-bottom: 8px; font-weight: 600;">PWD ID
                        Number:</label>
                    <input type="text" id="pwd_id_input" name="pwd_id" placeholder="Enter PWD ID"
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>
                <input type="password" name="senior_pwd_pin" placeholder="Enter PIN" required>
                <button type="submit" name="apply_senior_pwd_discount">Apply Discount</button>
            </form>
        </div>
    </div>

    <div id="receiptModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Receipt</h2>
            <div id="receiptContent"></div>
        </div>
    </div>

    <div id="voidItemModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeVoidItemModal()">&times;</span>
            <h2>Enter PIN to Void Item</h2>
            <form method="post">
                <input type="hidden" id="voidItemIndex" name="index" value="">
                <input type="password" name="void_pin" placeholder="Enter PIN" required>
                <button type="submit" name="void_item">Confirm Void</button>
            </form>
        </div>
    </div>

    <div id="voidAllModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeVoidAllModal()">&times;</span>
            <h2>Enter PIN to Void All Items</h2>
            <form method="post">
                <input type="password" name="void_all_pin" placeholder="Enter PIN" required>
                <button type="submit" name="void_all">Confirm Void All</button>
            </form>
        </div>
    </div>

    <script>
        function showCategory(category) {
            var categories = document.getElementsByClassName('menu-category');
            for (var i = 0; i < categories.length; i++) {
                categories[i].style.display = 'none';
            }
            document.getElementById(category).style.display = 'block';
            var buttons = document.getElementsByClassName('category-button');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].classList.remove('active');
                if (buttons[i].textContent === category) {
                    buttons[i].classList.add('active');
                }
            }
        }

        function showFirstCategory() {
            var firstCategoryButton = document.querySelector('.category-button');
            if (firstCategoryButton) {
                firstCategoryButton.click();
            }
        }

        window.onload = showFirstCategory;

        function openDrinkCustomizationModal(item, category, section, size, price) {
            document.getElementById('drinkItem').value = item;
            document.getElementById('drinkCategory').value = category;
            document.getElementById('drinkSection').value = section;
            document.getElementById('drinkSize').value = size;
            document.getElementById('drinkModalTitle').textContent = 'Customize Your ' + item + ' (' + size + ' - ₱' + price.toFixed(2) + ')';

            document.getElementById('drinkCustomizationForm').reset();
            document.getElementById('drinkItem').value = item;
            document.getElementById('drinkCategory').value = category;
            document.getElementById('drinkSection').value = section;
            document.getElementById('drinkSize').value = size;

            document.getElementById('drinkCustomizationModal').style.display = 'block';
        }

        function closeDrinkCustomizationModal() {
            document.getElementById('drinkCustomizationModal').style.display = 'none';
        }

        var discountModal = document.getElementById("discountModal");
        var discountBtn = document.getElementById("discountBtn");
        var discountSpan = discountModal.getElementsByClassName("close")[0];

        discountBtn.onclick = function () {
            discountModal.style.display = "block";
        }

        discountSpan.onclick = function () {
            discountModal.style.display = "none";
        }

        var receiptModal = document.getElementById("receiptModal");
        var receiptSpan = receiptModal.getElementsByClassName("close")[0];

        receiptSpan.onclick = function () {
            receiptModal.style.display = "none";
        }

        function confirmVoidItem(index) {
            if (confirm("Are you sure you want to void this item?")) {
                document.getElementById('voidItemIndex').value = index;
                document.getElementById('voidItemModal').style.display = 'block';
            }
        }

        function closeVoidItemModal() {
            document.getElementById('voidItemModal').style.display = 'none';
        }

        function confirmVoidAll() {
            if (confirm("Are you sure you want to void ALL items in the cart?")) {
                document.getElementById('voidAllModal').style.display = 'block';
            }
        }

        function closeVoidAllModal() {
            document.getElementById('voidAllModal').style.display = 'none';
        }

        function openSeniorPwdModal() {
            document.getElementById('seniorPwdModal').style.display = 'block';
        }

        function closeSeniorPwdModal() {
            document.getElementById('seniorPwdModal').style.display = 'none';
        }

        function togglePwdIdField() {
            var pwdRadio = document.querySelector('input[name="senior_pwd_type"][value="PWD"]');
            var pwdIdField = document.getElementById('pwdIdField');
            if (pwdRadio.checked) {
                pwdIdField.style.display = 'block';
            } else {
                pwdIdField.style.display = 'none';
            }
        }

        window.onclick = function (event) {
            if (event.target == discountModal) {
                discountModal.style.display = "none";
            }
            if (event.target == receiptModal) {
                receiptModal.style.display = "none";
            }
            if (event.target == document.getElementById('voidItemModal')) {
                document.getElementById('voidItemModal').style.display = 'none';
            }
            if (event.target == document.getElementById('voidAllModal')) {
                document.getElementById('voidAllModal').style.display = 'none';
            }
            if (event.target == document.getElementById('seniorPwdModal')) {
                document.getElementById('seniorPwdModal').style.display = 'none';
            }
            if (event.target == document.getElementById('drinkCustomizationModal')) {
                document.getElementById('drinkCustomizationModal').style.display = 'none';
            }
        }

        <?php if (isset($_SESSION['receipt'])): ?>
            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('receiptContent').innerHTML = <?php echo json_encode($_SESSION['receipt']); ?>;
                document.getElementById('receiptModal').style.display = 'block';
                <?php unset($_SESSION['receipt']); ?>
            });
        <?php endif; ?>
    </script>

    <style>
        .order-type-section {
            background: #f8f9fa;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }

        .order-type-section h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
        }

        .order-type-form {
            display: flex;
            gap: 20px;
        }

        .order-type-option {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 10px 15px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .order-type-option:hover {
            border-color: #337609;
            background: #f0f8ff;
        }

        .order-type-option input[type="radio"] {
            margin-right: 8px;
            transform: scale(1.2);
        }

        .order-type-option input[type="radio"]:checked+.order-type-label {
            color: #337609;
            font-weight: 600;
        }

        .order-type-option:has(input[type="radio"]:checked) {
            border-color: #337609;
            background: #e7f3ff;
        }

        .order-type-label {
            font-size: 16px;
            font-weight: 500;
        }

        .current-order-type {
            background: #e7f3ff;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 6px;
            border-left: 4px solid #337609;
            color: #337609;
        }

        .senior-pwd-button-inline {
            background: #ff9800;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-left: 10px;
        }

        .senior-pwd-button-inline:hover {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .void-all-container {
            display: flex;
            justify-content: center;
            margin-top: 15px;
        }

        .void-all-button {
            width: 100%;
        }

        .drink-modal-content {
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .customization-section {
            margin: 25px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .customization-section h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }

        .sinkers-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .sinker-option {
            display: flex;
            align-items: center;
            padding: 12px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sinker-option:hover {
            border-color: #337609;
            background: #f0f8ff;
        }

        .sinker-option input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.3);
            cursor: pointer;
        }

        .sinker-option input[type="checkbox"]:checked+span {
            color: #337609;
            font-weight: 600;
        }

        .sinker-option:has(input[type="checkbox"]:checked) {
            border-color: #337609;
            background: #e7f3ff;
        }

        .add-to-cart-button {
            width: 100%;
            padding: 15px;
            background: #337609;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .add-to-cart-button:hover {
            background: #2a5f07;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(51, 118, 9, 0.3);
        }

        .cart-item-details small {
            color: #666;
            font-size: 12px;
            display: block;
            margin-top: 4px;
        }

        .discount-type-selection {
            margin: 20px 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .discount-type-radio {
            display: flex;
            align-items: center;
            padding: 12px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .discount-type-radio:hover {
            border-color: #ff9800;
            background: #fff3e0;
        }

        .discount-type-radio input[type="radio"] {
            margin-right: 10px;
            transform: scale(1.3);
            cursor: pointer;
        }

        .discount-type-radio input[type="radio"]:checked+span {
            color: #ff9800;
            font-weight: 600;
        }

        .discount-type-radio:has(input[type="radio"]:checked) {
            border-color: #ff9800;
            background: #fff3e0;
        }
    </style>
</body>

</html>
