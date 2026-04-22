<?php
session_start();

include "db_conn.php";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : '';

if (empty($order_id)) {
    header("Location: order_history.php");
    exit();
}

// Get order details
$order_query = "SELECT * FROM customer_orders WHERE order_id = ?";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param("s", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    header("Location: order_history.php");
    exit();
}

$order = $order_result->fetch_assoc();

$items_query = "SELECT oi.id, oi.order_id, oi.menu_item_id, oi.size_id, oi.quantity, oi.price, 
                       (SELECT ums.menu_item_name FROM unified_menu_system ums 
                        WHERE ums.menu_item_id = oi.menu_item_id AND ums.size_id = oi.size_id LIMIT 1) as menu_item_name,
                       (SELECT ums.category FROM unified_menu_system ums 
                        WHERE ums.menu_item_id = oi.menu_item_id AND ums.size_id = oi.size_id LIMIT 1) as category,
                       (SELECT ums.section FROM unified_menu_system ums 
                        WHERE ums.menu_item_id = oi.menu_item_id AND ums.size_id = oi.size_id LIMIT 1) as section
                FROM customer_order_items oi 
                WHERE oi.order_id = ? 
                ORDER BY oi.id";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("s", $order_id);
$items_result = null;

// Try to execute the query, if it fails (table doesn't exist), we'll use fallback
try {
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
} catch (mysqli_sql_exception $e) {
    // order_items table doesn't exist, we'll use fallback data
    $items_result = null;
}

$subtotal = 0;
if ($items_result && $items_result->num_rows > 0) {
    $items_result->data_seek(0); // Reset pointer
    while ($item = $items_result->fetch_assoc()) {
        $subtotal += ($item['price'] * $item['quantity']);
    }
    $items_result->data_seek(0); // Reset pointer again for display
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Order <?php echo htmlspecialchars($order_id); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Courier Prime', monospace;
            background-color: #337609;
            padding: 20px;
            line-height: 1.2;
        }

        .receipt-container {
            max-width: 320px;
            margin: 0 auto;
            background-color: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .receipt-container::before {
            content: '';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 280px;
            height: 20px;
            background: repeating-linear-gradient(
                90deg,
                transparent,
                transparent 8px,
                #ddd 8px,
                #ddd 12px
            );
        }

        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .business-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .business-info {
            font-size: 11px;
            line-height: 1.3;
            margin-bottom: 10px;
        }

        .order-number-section {
            text-align: center;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px dashed #333;
            border-bottom: 1px dashed #333;
        }

        .order-number-label {
            font-size: 12px;
            margin-bottom: 5px;
        }

        .order-number {
            font-size: 32px;
            font-weight: bold;
            margin: 5px 0;
        }

        .order-type-section {
            text-align: center;
            margin: 10px 0;
            padding: 8px 0;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .order-type-label {
            font-size: 11px;
            color: #666;
            margin-bottom: 3px;
        }

        .order-type-value {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            color: #333;
        }

        .receipt-details {
            font-size: 11px;
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .items-section {
            margin: 15px 0;
            border-top: 1px dashed #333;
            padding-top: 10px;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }

        .item-name {
            flex: 1;
        }

        .item-price {
            margin-left: 10px;
        }

        .item-details {
            font-size: 10px;
            color: #666;
            margin-left: 15px;
            margin-bottom: 2px;
        }

        .quantity {
            font-size: 10px;
            margin-left: 5px;
        }

        .totals-section {
            border-top: 1px dashed #333;
            padding-top: 10px;
            margin-top: 15px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }

        .total-row.final-total {
            font-weight: bold;
            font-size: 12px;
            border-top: 1px solid #333;
            padding-top: 5px;
            margin-top: 5px;
        }

        .payment-section {
            margin-top: 15px;
            border-top: 1px dashed #333;
            padding-top: 10px;
        }

        .footer-section {
            text-align: center;
            margin-top: 20px;
            border-top: 2px dashed #333;
            padding-top: 15px;
            font-size: 10px;
            line-height: 1.4;
        }

        .feedback-text {
            margin-bottom: 5px;
        }

        .contact-info {
            margin-top: 10px;
            font-size: 8px;
        }

        .back-button {
            display: block;
            text-align: center;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #4caf50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            max-width: 200px;
            font-family: 'Poppins', sans-serif;
        }

        .back-button:hover {
            background-color: #45a049;
        }

        .print-button {
            display: block;
            text-align: center;
            margin: 10px auto;
            padding: 10px 20px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            max-width: 200px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            border: none;
        }

        .print-button:hover {
            background-color: #1976D2;
        }

        .discount-type {
            font-size: 9px;
            background-color: #fff3cd;
            color: #856404;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
            font-weight: bold;
        }

        @media print {
            body {
                background-color: white;
                padding: 0;
                margin: 0;
            }
            
            .receipt-container {
                border-radius: 0;
                max-width: 220px;
                width: 45mm;
                margin: 0;
                font-size: 8px;
                padding:5px;
            }
            
            .receipt-container::before {
                display: none;
            }
            
            .back-button, .print-button {
                display: none;
            }

            .business-name {
                font-size: 14px;
            }

            .order-number {
                font-size: 20px;
            }

            .order-type-section {
                background-color: #f0f0f0;
            }
        }

        @media (max-width: 200px) {
            body {
                padding: 10px;
            }
            
            .receipt-container {
                max-width: 100%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <div class="business-name">Bubble Hideout</div>
            <div class="business-info">
                Floor 3DBP, 2nd, Naga Rd, Las Piñas, 1774 Metro Manila<br>
                <br>
                GST ID No: 001234567891<br>
                Business Registration: BH-2024-001<br>
                TAX INVOICE<br><br>
                THIS IS NOT AN OFFICAL RECEIPT<br>
                
            </div>
        </div>

        <div class="order-number-section">
            <div class="order-number-label">Your order number is</div>
            <div class="order-number"><?php echo htmlspecialchars($order_id); ?></div>
        </div>

        <div class="order-type-section">
            <div class="order-type-label">Order Type</div>
            <div class="order-type-value">
                <?php 
                $order_type = isset($order['order_type']) ? $order['order_type'] : 'dine_in';
                echo $order_type === 'takeout' ? 'TAKEOUT' : 'DINE IN';
                ?>
            </div>
        </div>

        <div class="receipt-details">
            <div class="detail-row">
                <span>Date:</span>
                <span><?php echo date('d/m/Y H:i:s', strtotime($order['created_at'])); ?></span>
            </div>
            <div class="detail-row">
                <span>Cashier:</span>
                <span>Staff 01</span>
            </div>
            <div class="detail-row">
                <span>Terminal:</span>
                <span>POS-001</span>
            </div>
            <div class="detail-row">
                <span>Service:</span>
                <span><?php echo $order_type === 'takeout' ? 'Takeout' : 'Dine In'; ?></span>
            </div>
        </div>

        <div class="items-section">
            <div class="detail-row" style="font-weight: bold; margin-bottom: 8px;">
                <span>QTY ITEM</span>
                <span>TOTAL</span>
            </div>
            
            <?php 
            if ($items_result && $items_result->num_rows > 0): ?>
                <?php while ($item = $items_result->fetch_assoc()): ?>
                    <div class="item-row">
                        <div class="item-name">
                            <?php echo $item['quantity']; ?> 
                            <?php 
                            $display_name = !empty($item['menu_item_name']) ? $item['menu_item_name'] : $item['menu_item_id'];
                            echo htmlspecialchars($display_name);
                            ?>
                            <?php if ($item['size_id'] !== 'REG'): ?>
                                <span class="quantity">(<?php echo htmlspecialchars($item['size_id']); ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($item['flavor'])): ?>
                                <span class="quantity">- <?php echo htmlspecialchars($item['flavor']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="item-price">
                            ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        </div>
                    </div>  
                <?php endwhile; ?>
            <?php else: ?>
                <div class="item-row">
                    <span>No items found</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal</span>
                <span>₱<?php echo number_format($subtotal > 0 ? $subtotal : $order['total'], 2); ?></span>
            </div>
            <?php if ($order['discount'] > 0): ?>
            <div class="total-row">
                <span>
                    Discount
                    <?php 
                    if (isset($order['discount_type']) && !empty($order['discount_type'])) {
                        $discount_label = '';
                        $discount_percentage = '';
                        
                        if ($order['discount_type'] === 'Senior') {
                            $discount_label = 'SENIOR';
                            $discount_percentage = '20%';
                        } elseif ($order['discount_type'] === 'PWD') {
                            $discount_label = 'PWD';
                            $discount_percentage = '20%';
                        }
                        
                        if ($discount_label) {
                            echo '<span class="discount-type">' . $discount_label . ' ' . $discount_percentage . '</span>';
                        }
                    }
                    ?>
                </span>
                <span>-₱<?php echo number_format($order['discount'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row">
                <span><?php echo $order_type === 'takeout' ? 'Takeout' : 'Eat-In'; ?> Total (Incl GST)</span>
                <span>₱<?php echo number_format($order['total'], 2); ?></span>
            </div>
            <div class="total-row">
                <span>Rounding Adjust</span>
                <span>0.00</span>
            </div>
            <div class="total-row">
                <span>Total Rounded</span>
                <span>₱<?php echo number_format($order['total'], 2); ?></span>
            </div>
        </div>

        <div class="payment-section">
            <div class="total-row">
                <span>Cash Tendered</span>
                <span>₱<?php echo number_format($order['amount_paid'], 2); ?></span>
            </div>
            <div class="total-row">
                <span>Change</span>
                <span>₱<?php echo number_format($order['amount_paid'] - $order['total'], 2); ?></span>
            </div>
        </div>

        <div class="totals-section">
            <div class="total-row">
                <span>TOTAL INCLUDES GST</span>
                <span>₱<?php echo number_format($order['total'] * 0.12, 2); ?></span>
            </div>
        </div>

        <div class="footer-section">
            <div class="feedback-text">
                We'd love to hear your feedback!
                <br>
                Visit www.facebook.com/bubblehideout
                <br>
                to share your experience
                <br>
                Thank You and Please Come Again.
            </div>
            
            <div style="border-top: 1px dashed #333; padding-top: 10px; margin-top: 15px;">
                <!-- Contact info here -->
            </div>
        </div>
    </div>

    <button onclick="window.print()" class="print-button">Print Receipt</button>
    <a href="/interface/homepage.php" class="back-button">← Back to Order History</a>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add any additional JavaScript functionality here
        });
    </script>
</body>
</html>
