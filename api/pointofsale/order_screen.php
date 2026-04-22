<?php
session_start();
include "db_conn.php";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function getOrders($limit = 10, $offset = 0) {
    global $conn;
    $orders = [];
    $query = "SELECT * FROM customer_orders WHERE status ='Pending' ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $orderId = $row['order_id'];
        $orders[$orderId] = $row;
        $orders[$orderId]['items'] = [];
        
        $itemsQuery = "SELECT oi.*, 
                       (SELECT menu_item_name FROM unified_menu_system WHERE menu_item_id = oi.menu_item_id LIMIT 1) AS item_name
                       FROM customer_order_items oi 
                       WHERE oi.order_id = ?";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bind_param("s", $orderId);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        
        while ($item = $itemsResult->fetch_assoc()) {
            $orders[$orderId]['items'][] = $item;
        }
        
        $itemsStmt->close();
    }
    
    $stmt->close();
    return $orders;
}

function getOrderDetails($orderId) {
    global $conn;
    $order = [];
    $query = "SELECT * FROM customer_orders WHERE order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    
    if ($order) {
        $itemsQuery = "SELECT oi.*, 
                       (SELECT menu_item_name FROM unified_menu_system WHERE menu_item_id = oi.menu_item_id LIMIT 1) AS item_name
                       FROM customer_order_items oi 
                       WHERE oi.order_id = ?";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bind_param("s", $orderId);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        
        $order['items'] = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $order['items'][] = $item;
        }
        
        $itemsStmt->close();
    }
    
    $stmt->close();
    return $order;
}



function updateOrderStatus($orderId, $status) {
    global $conn;
    $query = "UPDATE customer_orders SET status = ? WHERE order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $status, $orderId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function deleteOrder($orderId) {
    global $conn;
    
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // Delete order items
        $deleteItemsQuery = "DELETE FROM customer_order_items WHERE order_id = ?";
        $stmt = $conn->prepare($deleteItemsQuery);
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $stmt->close();
        
        // Delete the order
        $deleteOrderQuery = "DELETE FROM customer_orders WHERE order_id = ?";
        $stmt = $conn->prepare($deleteOrderQuery);
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $stmt->close();
        
        // Commit the transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback the transaction if an error occurred
        $conn->rollback();
        return false;
    }
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {
    $orderId = $_POST['order_id'];
    updateOrderStatus($orderId, 'Order Ready');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $orderId = $_POST['order_id'];
    if (deleteOrder($orderId)) {
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        
    }
}

$orders = getOrders($limit, $offset);

// Get total number of orders for pagination
$totalOrdersQuery = "SELECT COUNT(*) as total FROM customer_orders";
$totalOrdersResult = $conn->query($totalOrdersQuery);
$totalOrders = $totalOrdersResult->fetch_assoc()['total'];
$totalPages = ceil($totalOrders / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href=/media/BUBBLE.jpg></link>
    <title>View Orders - Bubble Hideout POS</title>
    <link rel="stylesheet" href="/fonts/fonts.css">
</head>
<style>
body {
    font-family: 'Poppins', sans-serif;
    line-height: 1.6;
    color: #333;
    background-color: #f4f4f4;
    margin: 0;
    padding: 0;
}
.nav-header {
    background-color: #8B4513;
    padding: 10px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1000;
}
.back-button, .view-history-button {
    background-color: #337609;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 20px;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;

}
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
h1 {
    color: #2c3e50;
    margin-bottom: 10px;
}
.view-receipt-button{
    position: relative;
    top: -4.5px;
    display: inline-block;
    align-content: center;
    text-align: center;
    width: 100px;
    height: 40px;
    background-color: #4caf50;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    margin-bottom: 20px;
    transition: background-color 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.complete-order-button {
    
    display: inline-block;
    top: -10px;
    width: 40px;
    height: 40px;
    justify-content: center;
    text-align: center;
    background-color: #4caf50;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    margin-bottom: 20px;
    transition: background-color 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 20px;
}
.view-receipt-button:hover, .complete-order-button:hover {
    background-color: #333;
}
.complete-order-button {
    background-color: #27ae60;
}
.complete-order-button:hover {
    background-color: #2ecc71;
}
.order-list {
    display: flex;
    width: 100%;
    height: 500px;
    overflow: scroll;
    overflow-x: hidden;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;

}
.order-item {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.28);
    padding: 20px;
    width: 500px;
    margin: 20px;
    overflow: auto;
    overflow-x: hidden;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.order-id {
    font-size: 1.2em;
    font-weight: 600;
    color: #2c3e50;
}
.order-date {
    color: #7f8c8d;
}
.order-summary {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}
.order-summary > div {
    flex: 1;
}
.order-summary h3 {
    margin: 0;
    font-size: 0.9em;
    color: #7f8c8d;
}
.order-summary p {
    margin: 5px 0 0;
    font-weight: 600;
}
.order-status {
    font-weight: bold;
    text-transform: uppercase;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 0.8em;
}
.status-pending {
    background-color: #f39c12;
    color: white;
}
.status-completed {
    background-color: #2ecc71;
    color: white;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ecf0f1;
}
th {
    background-color: #f9f9f9;
    font-weight: 600;
    color: #2c3e50;
}
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}
.pagination a {
    color: #3498db;
    padding: 8px 16px;
    text-decoration: none;
    transition: background-color 0.3s;
    border: 1px solid #ddd;
    margin: 0 4px;
}
.pagination a.active {
    background-color: #3498db;
    color: white;
    border: 1px solid #3498db;
}
.pagination a:hover:not(.active) {
    background-color: #ddd;
}
.delete-order-button {
    position: relative;
    top: -2.5px;
    background-color: #e74c3c;
    color: white;
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    margin-left: 1px;
    
}
.delete-order-button:hover {
    background-color: #c0392b;
}
@media (max-width: 768px) {
    .order-summary {
        flex-direction: column;
    }
    .order-summary > div {
        margin-bottom: 10px;
    } }

    .left-img{
    position: relative;
    
    width: 45%;
    display: flex;
    justify-content: space-evenly;
    align-items: center;
}

.right-button{
    position: relative;
    
    width: 55%;
    height: 400px;
    display: flex;
    justify-content: space-evenly;
    align-items: center;
}

    .menu {
    position: relative;
    top: 100px;
    
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 90%;
    height: 500px;
    }   
    .navbar {
    background-color: #7e5832;
    width: 100%;
    height: 80px;
    position: absolute; 
    top: 0; 
    left: 0; 
    z-index: 10;
    box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
    display: flex;
    justify-content:left;

}
.navbar-right {
    width: 50%;
    height: 80px;
    position: absolute; 
    top: 0; 
    right: 0; 
    z-index: 11;
    display: flex;
    justify-content:right;

}
.buttons{
    position: relative; 
    width: 330px;
    left: 30px;

    display: flex;
    justify-content:space-between;
    align-items:center;
}

.btn{

    font-family: Poppins;
    font-weight: bold;
    color: #f0f0f0;
    box-shadow: 3px 4px 11px 1px rgba(0,0,0,0.28);

    height: 40px;
    width: 150px;
    background-color: #337609;
    border: none;
    border-radius: 25px;
    transition: 0.5s;
}

.btn:hover {
    background-color: #326810;
    transition: 0.5s;
}

/* Dropdown Button */
.dropbtn {
    background-color: transparent;
    color: #f0f0f0;
    padding: 16px;
    font-size: 16px;
    min-width: 110px;
    border: none;
    cursor: pointer;

    font-family: Poppins;
    font-weight: bolder;
  }
  
  .dropbtn:hover {
    background-color: #5a4026;

  }

  
  /* The container <div> - needed to position the dropdown content */
  .dropdown {
    position: relative; 
    width: 490px;
    display: flex;
    justify-content: right;
  }
  
  /* Dropdown Content (Hidden by Default) */
  .dropdown-content {
    display: none;
    position: absolute;
    background-color: #f1f1f1;
    min-width: 110px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1;
    top:80px;

    border-bottom-left-radius: 5px;
    border-bottom-right-radius: 5px;
  }
  
  /* Links inside the dropdown */
  .dropdown-content a {
    color: black;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
  }
  
  /* Change color of dropdown links on hover */
  .dropdown-content a:hover {
    background-color: #ddd;
    
    border-bottom-left-radius: 5px;
    border-bottom-right-radius: 5px;
}
  
  /* Show the dropdown menu (use JS to add this class to the .dropdown-content container when the user clicks on the dropdown button) */
  .show {display:block;}
</style>
<body>
        <div class="navbar">
            <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
            <div class="buttons">
                <form action="/interface/homepage.php"><button type="submit" class="btn"> Back </button></form>
            </div>
            
        </div>

        <div class="navbar-right">
            <div class="dropdown">
                <button onclick="myFunction()" class="dropbtn">Employee</button>
                <div id="myDropdown" class="dropdown-content">
                    <a href="logout.php">Logout</a>
                </div>
            </div>
            <div style="position: relative; width: 20px; right: 30px; display: flex; align-items: center;"></div>
        </div>

    <div class="container" style="position: relative; top: 90px;">
        <div class="order-list" >
            <?php foreach ($orders as $orderId => $order): ?>
                <div class="order-item" >
                    <div class="order-header">
                        <span class="order-id">Order ID: <?php echo $orderId; ?></span>
                        <span class="order-date"><?php echo $order['created_at']; ?></span>
                    </div>
                    <div class="order-summary">
                        <div>
                            <h3>Total</h3>
                            <p>Php <?php echo number_format($order['total'], 2); ?></p>
                        </div>
                        <div>
                            <h3>Discount</h3>
                            <p>Php <?php echo number_format($order['discount'], 2); ?></p>
                        </div>
                        <div>
                            <h3>Amount Paid</h3>
                            <p>Php <?php echo number_format($order['amount_paid'], 2); ?></p>
                        </div>
                        <div>
                            <h3>Status</h3>
                            <p>
                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo $order['status']; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="order-details">
                        <h3>Order Items:</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Size</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Flavor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                    <tr>
                                        <td><?php echo $item['item_name']; ?></td>
                                        <td><?php echo $item['size_id']; ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo $item['flavor'] ? $item['flavor'] : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 15px;">
                        <a href="view_receipt.php?order_id=<?php echo $orderId; ?>" class="view-receipt-button">Full Receipt</a>
                        <?php if ($order['status'] !== 'Completed'): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                <button type="submit" name="complete_order" class="complete-order-button">✔</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <button type="submit" name="delete_order" class="delete-order-button" onclick="return confirm('Are you sure you want to delete this order?')">✖</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" <?php echo ($page == $i) ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>


