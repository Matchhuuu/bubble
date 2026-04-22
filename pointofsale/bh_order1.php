<?php
require_once 'config1.php';

$active_section = isset($_GET['section']) ? $_GET['section'] : 'drinks';
$total = 0;
$order_message = '';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fetch menu items from the unified database
$menu = [];

// Get all sections and categories
$sections_query = "SELECT DISTINCT section FROM unified_menu_system WHERE is_available = 1 ORDER BY section";
$sections_result = $conn->query($sections_query);

if ($sections_result) {
    while ($section_row = $sections_result->fetch_assoc()) {
        $section = $section_row['section'];
        
        // Get categories for this section
        $categories_query = "SELECT DISTINCT category FROM unified_menu_system WHERE section = ? AND is_available = 1 ORDER BY category";
        $categories_stmt = $conn->prepare($categories_query);
        $categories_stmt->bind_param("s", $section);
        $categories_stmt->execute();
        $categories_result = $categories_stmt->get_result();
        
        while ($category_row = $categories_result->fetch_assoc()) {
            $category = $category_row['category'];
            $menu[$section][$category] = [];
            
            // Get menu items for this category
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

// Define chicken flavors
$chicken_flavors = ['Buffalo', 'BBQ', 'Honey Garlic', 'Spicy', 'Original', 'Teriyaki'];

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $item = $_POST['item'];
    $category = $_POST['category'];
    $section = $_POST['section'];
    $size = $_POST['size'];
    
    if (isset($menu[$section][$category][$item][$size])) {
        $menu_item_id = $menu[$section][$category][$item][$size]['id'];
        $price = $menu[$section][$category][$item][$size]['price'];
        $flavor = isset($_POST['flavor']) ? $_POST['flavor'] : '';
        
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
                } else {
                    echo "<script>alert('Sorry, not enough stock available for " . addslashes($item) . "');</script>";
                }
            } else {
                $_SESSION['cart'][] = [
                    'id' => $menu_item_id,
                    'name' => $item,
                    'size' => $size,
                    'price' => $price,
                    'quantity' => 1,
                    'total_price' => $price,
                    'flavor' => $flavor
                ];
            }
        } else {
            echo "<script>alert('Sorry, " . addslashes($item) . " is currently out of stock.');</script>";
        }
    }
}

// Handle quantity changes
if (isset($_POST['increase_quantity'])) {
    $index = intval($_POST['index']);
    if (isset($_SESSION['cart'][$index])) {
        $menu_item_id = $_SESSION['cart'][$index]['id'];
        $size = $_SESSION['cart'][$index]['size'];
        if (checkStockAvailability($menu_item_id, $size, $_SESSION['cart'][$index]['quantity'] + 1)) {
            $_SESSION['cart'][$index]['quantity']++;
            $_SESSION['cart'][$index]['total_price'] += $_SESSION['cart'][$index]['price'];
        } else {
            echo "<script>alert('Sorry, not enough stock available.');</script>";
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
            unset($_SESSION['cart'][$index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }
    }
}

// Handle remove item
if (isset($_POST['remove_item'])) {
    $index = intval($_POST['index']);
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
}

// Calculate total
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['total_price'];
    }
}

// Handle order placement
if (isset($_POST['place_order'])) {
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $customer_email = trim($_POST['customer_email']);
    $order_type = $_POST['order_type'];
    
    if (!empty($customer_name) && !empty($customer_phone) && !empty($_SESSION['cart'])) {
        $orderId = generateOrderId();
        
        $conn->begin_transaction();
        try {
            // Add customer order record
            addCustomerOrderRecord($orderId, $customer_name, $customer_phone, $customer_email, $order_type, $total);
            
            // Process each cart item
            foreach ($_SESSION['cart'] as $item) {
                if (checkStockAvailability($item['id'], $item['size'], $item['quantity'])) {
                    // Process the order (deduct inventory)
                    if (!processCustomerOrder($orderId, $item['id'], $item['size'], $item['quantity'])) {
                        throw new Exception("Failed to process order for " . $item['name']);
                    }
                    
                    // Add order item record
                    addCustomerOrderItemRecord($orderId, $item['id'], $item['size'], $item['quantity'], $item['price'], $item['flavor'] ?? null);
                } else {
                    throw new Exception("Sorry, " . $item['name'] . " is no longer available in the requested quantity.");
                }
            }
            
            $conn->commit();
            
            // Generate receipt
            $customer_info = [
                'name' => $customer_name,
                'phone' => $customer_phone,
                'email' => $customer_email
            ];
            
            $receipt_content = generateCustomerReceipt($orderId, $customer_info, $_SESSION['cart'], $total, $order_type);
            
            $_SESSION['receipt'] = $receipt_content;
            $_SESSION['order_id'] = $orderId;
            $_SESSION['cart'] = [];
            $total = 0;
            
            $order_message = "Order placed successfully! Your order ID is: " . $orderId;
            
        } catch (Exception $e) {
            $conn->rollback();
            $order_message = "Error: " . $e->getMessage();
        }
    } else {
        $order_message = "Please fill in all required fields and add items to your cart.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bubble Hideout - Order Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="customer-style.css">
</head>

<style>
  * {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: "Poppins", sans-serif;
  background: #ffffff;
  min-height: 100vh;
}

.header {
  background: #8b4513;
  padding: 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  position: sticky;
  top: 0;
  z-index: 100;
}

.logo h1 {
  color: #ffffff;
  font-size: 1.8rem;
  margin-bottom: 0.2rem;
  font-weight: 600;
}

.logo p {
  color: #ffffff;
  font-size: 0.9rem;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.track-order-btn {
  background: #228b22;
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 500;
  transition: all 0.3s ease;
}

.track-order-btn:hover {
  background: #1e7b1e;
}

.cart-summary {
  background: #228b22;
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  transition: all 0.3s ease;
}

.cart-summary:hover {
  background: #1e7b1e;
}

.cart-count {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
}

.container {
  display: flex;
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
  gap: 2rem;
}

.menu-section {
  flex: 1;
  background: white;
  border-radius: 10px;
  padding: 2rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.section-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
}

.section-tab {
  background: #a9a9a9;
  color: white;
  padding: 0.75rem 1.5rem;
  text-decoration: none;
  font-weight: 500;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.section-tab.active {
  background: #228b22;
}

.section-tab:hover {
  background: #228b22;
}

.category-nav {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
  flex-wrap: wrap;
}

.category-btn {
  background: #a9a9a9;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
}

.category-btn:hover,
.category-btn.active {
  background: #228b22;
}

.category-title {
  color: #228b22;
  margin-bottom: 1.5rem;
  font-size: 1.5rem;
  font-weight: 600;
}

.menu-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
  border-color: #228b22;
  box-shadow: 0 4px 15px rgba(34, 139, 34, 0.2);
}

.item-image {
  height: 120px;
  background: #f8f9fa;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #666;
  font-size: 3rem;
}

.item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.item-info {
  padding: 1rem;
}

.item-name {
  color: #333;
  margin-bottom: 1rem;
  font-size: 1.1rem;
  font-weight: 500;
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
}

.add-to-cart-btn {
  background: #228b22;
  color: white;
  border: none;
  padding: 0.75rem 1rem;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.add-to-cart-btn:hover {
  background: #1e7b1e;
}

.cart-section {
  width: 400px;
  background: white;
  border-radius: 10px;
  padding: 2rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
  position: sticky;
  top: 120px;
  height: fit-content;
  max-height: calc(100vh - 140px);
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
  color: #228b22;
  font-weight: 600;
}

.close-cart {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #666;
  display: none;
}

.empty-cart {
  text-align: center;
  padding: 2rem;
  color: #666;
}

.cart-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 1rem;
}

.item-details {
  flex: 1;
}

.item-details h4 {
  color: #333;
  margin-bottom: 0.25rem;
  font-weight: 500;
}

.item-specs {
  color: #666;
  font-size: 0.8rem;
  margin-bottom: 0.25rem;
}

.item-price {
  color: #228b22;
  font-weight: 600;
}

.quantity-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.qty-btn {
  background: #228b22;
  color: white;
  border: none;
  width: 30px;
  height: 30px;
  border-radius: 5px;
  cursor: pointer;
  font-weight: bold;
}

.qty-btn:hover {
  background: #1e7b1e;
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
  padding: 0.5rem;
  border-radius: 5px;
  cursor: pointer;
}

.remove-btn:hover {
  background: #c82333;
}

.cart-total {
  text-align: center;
  padding: 1rem;
  background: #228b22;
  color: white;
  border-radius: 8px;
  margin: 1rem 0;
}

.checkout-btn {
  width: 100%;
  background: #228b22;
  color: white;
  border: none;
  padding: 1rem;
  border-radius: 8px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.checkout-btn:hover {
  background: #1e7b1e;
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
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  background-color: white;
  margin: 5% auto;
  padding: 2rem;
  border-radius: 10px;
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
  font-weight: 500;
  color: #333;
}

.form-group input,
.form-group select {
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 1rem;
}

.order-summary {
  background: #f8f9fa;
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
  font-weight: 600;
}

.place-order-btn {
  background: #228b22;
  color: white;
  border: none;
  padding: 1rem;
  border-radius: 8px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.place-order-btn:hover {
  background: #1e7b1e;
}

.order-message {
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 1rem;
  text-align: center;
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

/* Receipt Styles */
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
  background: #f8f9fa;
  font-weight: 600;
}

.receipt-total {
  text-align: center;
  font-size: 1.2rem;
  margin: 1rem 0;
  padding: 1rem;
  background: #228b22;
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
  font-weight: 500;
}

.print-btn {
  background: #228b22;
  color: white;
}

.close-btn {
  background: #6c757d;
  color: white;
}

/* Responsive Design */
@media (max-width: 768px) {
  .container {
    flex-direction: column;
    padding: 1rem;
  }

  .cart-section {
    width: 100%;
    position: fixed;
    top: 0;
    right: -100%;
    height: 100vh;
    z-index: 200;
    transition: right 0.3s ease;
    border-radius: 0;
  }

  .cart-section.active {
    right: 0;
  }

  .close-cart {
    display: block;
  }

  .menu-grid {
    grid-template-columns: 1fr;
  }

  .header {
    padding: 1rem;
  }

  .logo h1 {
    font-size: 1.4rem;
  }

  .section-tabs {
    flex-wrap: wrap;
  }

  .category-nav {
    justify-content: center;
  }
}

.no-items {
  text-align: center;
  padding: 3rem;
  color: #666;
}
</style>

<body>
    <div class="header">
        <div class="logo">
            <h1>Bubble Hideout</h1>
            <p>Order your favorite drinks & food online!</p>
        </div>

            <div class="cart-summary" onclick="toggleCart()">
                <span class="cart-count"><?php echo count($_SESSION['cart']); ?></span>
                <span class="cart-total">₱<?php echo number_format($total, 2); ?></span>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="menu-section">
            <div class="section-tabs">
                <a href="?section=drinks" class="section-tab <?php echo $active_section == 'drinks' ? 'active' : ''; ?>">
                     Drinks
                </a>
                <a href="?section=food" class="section-tab <?php echo $active_section == 'food' ? 'active' : ''; ?>">
                     Food
                </a>
                <a href="?section=addons" class="section-tab <?php echo $active_section == 'addons' ? 'active' : ''; ?>">
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
                                                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                                                    <input type="hidden" name="section" value="<?php echo htmlspecialchars($active_section); ?>">
                                                    <input type="hidden" name="size" value="<?php echo htmlspecialchars($size); ?>">
                                                    
                                                    <?php if (strpos(strtolower($category), 'wings') !== false || strpos(strtolower($category), 'chicken') !== false): ?>
                                                        <select name="flavor" class="flavor-select" required>
                                                            <option value="">Choose Flavor</option>
                                                            <?php foreach ($chicken_flavors as $flavor): ?>
                                                                <option value="<?php echo htmlspecialchars($flavor); ?>"><?php echo htmlspecialchars($flavor); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php endif; ?>
                                                    
                                                    <button type="submit" name="add_to_cart" class="add-to-cart-btn">
                                                        <span class="size-name"><?php echo htmlspecialchars($size); ?></span>
                                                        <span class="size-price">₱<?php echo number_format($data['price'], 2); ?></span>
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

    <!-- Checkout Modal -->
    <div id="checkoutModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCheckoutModal()">&times;</span>
            <h2>Complete Your Order</h2>
            
            <?php if (!empty($order_message)): ?>
                <div class="order-message <?php echo strpos($order_message, 'Error') !== false ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($order_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" class="checkout-form">
                <div class="form-group">
                    <label for="customer_name">Full Name *</label>
                    <input type="text" id="customer_name" name="customer_name" required>
                </div>
                
                <div class="form-group">
                    <label for="customer_phone">Phone Number *</label>
                    <input type="tel" id="customer_phone" name="customer_phone" required>
                </div>
                
                <div class="form-group">
                    <label for="customer_email">Email (Optional)</label>
                    <input type="email" id="customer_email" name="customer_email">
                </div>
                
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
                                <span><?php echo htmlspecialchars($item['name']); ?> (<?php echo htmlspecialchars($item['size']); ?>) x<?php echo $item['quantity']; ?></span>
                                <span>₱<?php echo number_format($item['total_price'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="summary-total">
                            <strong>Total: ₱<?php echo number_format($total, 2); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" name="place_order" class="place-order-btn" >Place Order</button>
            </form>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div id="receiptModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeReceiptModal()">&times;</span>
            <div id="receiptContent"></div>
            <div class="receipt-actions">
                <button onclick="window.print()" class="print-btn">Print Receipt</button>
                <button onclick="closeReceiptModal()" class="close-btn">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Category navigation
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

        // Show first category on load
        function showFirstCategory() {
            var firstCategoryBtn = document.querySelector('.category-btn');
            if (firstCategoryBtn) {
                firstCategoryBtn.click();
            }
        }

        // Cart toggle
        function toggleCart() {
            var cartSection = document.getElementById('cartSection');
            cartSection.classList.toggle('active');
        }

        // Checkout modal
        function showCheckoutForm() {
            document.getElementById('checkoutModal').style.display = 'block';
        }

        function closeCheckoutModal() {
            document.getElementById('checkoutModal').style.display = 'none';
        }

        // Receipt modal
        function closeReceiptModal() {
            document.getElementById('receiptModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            var checkoutModal = document.getElementById('checkoutModal');
            var receiptModal = document.getElementById('receiptModal');
            
            if (event.target == checkoutModal) {
                checkoutModal.style.display = 'none';
            }
            if (event.target == receiptModal) {
                receiptModal.style.display = 'none';
            }
        }

        // Initialize page
        window.onload = function() {
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