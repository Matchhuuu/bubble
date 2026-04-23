<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';
include "db_conn.php";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all orders with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$order_type_filter = isset($_GET['order_type']) ? $_GET['order_type'] : '';

$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "order_id LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(created_at) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

if (!empty($order_type_filter)) {
    $where_conditions[] = "order_type = ?";
    $params[] = $order_type_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM orders $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_orders = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $limit);

// Get orders
$query = "SELECT * FROM orders $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - Bubble Hideout POS</title>
    <link rel="icon" href=/media/BUBBLE.jpg></link>
    <link rel="stylesheet" href="/fonts/fonts.css">
    <style>
        :u412787669_soshe {
            --primary-color: #4caf50;
            --secondary-color: #f39c12;
            --background-color: #f4f7f9;
            --text-color: #333;
            --light-text-color: #7f8c8d;
            --border-color: #e0e0e0;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.4;
            color: var(--text-color);
            background-color: var(--background-color);
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow-x: auto;
        }
        
        .nav-header {
            background-color: #8B4513;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-height: 60px;
        }
        
        .back-button {
            display: inline-block;
            padding: 8px 16px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
        }
        
        .back-button:hover {
            background-color: #333;
            transform: translateY(-1px);
        }
        
        .nav-header h2 {
            color: white;
            margin: 0;
            font-size: 1.4em;
        }
        
        .container {
            padding: 15px;
            max-width: 100%;
            height: calc(100vh - 60px);
            overflow-y: auto;
        }
        
        h1 {
            color: var(--primary-color);
            margin: 0 0 20px 0;
            text-align: center;
            font-size: 2em;
        }
        
        .search-filters {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        
        /* Updated grid layout to include order type filter */
        .search-filters form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 10px;
            align-items: center;
        }
        
        .search-filters input, .search-filters select, .search-filters button, .search-filters a {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            height: 36px;
        }
        
        .search-filters button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .search-filters button:hover {
            background-color: #45a049;
        }
        
        .search-filters a {
            background: #6c757d;
            color: white;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .orders-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            height: calc(100vh - 200px);
            overflow-y: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
        }
        
        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--primary-color);
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        /* Updated column widths to include order type */
        th:nth-child(1), td:nth-child(1) { width: 10%; } 
        th:nth-child(2), td:nth-child(2) { width: 16%; }
        th:nth-child(3), td:nth-child(3) { width: 10%; }
        th:nth-child(4), td:nth-child(4) { width: 12%; } 
        th:nth-child(5), td:nth-child(5) { width: 10%; } 
        th:nth-child(6), td:nth-child(6) { width: 12%; } 
        th:nth-child(7), td:nth-child(7) { width: 10%; } 
        th:nth-child(8), td:nth-child(8) { width: 20%; }
        
        tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        
        .view-receipt-btn {
            display: inline-block;
            padding: 6px 12px;
            background-color: var(--secondary-color);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .view-receipt-btn:hover {
            background-color: #e67e22;
            transform: translateY(-1px);
        }

        /* Added order type badge styling */
        .order-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .order-type-badge.dine-in {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .order-type-badge.takeout {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 15px;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 6px 10px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s ease;
            font-size: 14px;
            min-width: 32px;
            text-align: center;
        }
        
        .pagination a:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination .current {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .no-orders {
            text-align: center;
            padding: 40px 20px;
            color: var(--light-text-color);
            font-size: 1.1em;
        }
        
        /* Enhanced responsive design for better mobile space usage */
        @media (max-width: 1024px) {
            .search-filters form {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 8px;
            }
            
            .search-filters input:first-child {
                grid-column: 1 / -1;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .nav-header {
                padding: 8px 10px;
                flex-direction: column;
                gap: 8px;
                min-height: auto;
            }
            
            .nav-header h2 {
                font-size: 1.2em;
            }
            
            h1 {
                font-size: 1.6em;
                margin-bottom: 15px;
            }
            
            .search-filters form {
                grid-template-columns: 1fr;
            }
            
            .orders-table {
                height: calc(100vh - 180px);
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
                font-size: 12px;
            }
            
            th, td {
                padding: 6px 8px;
            }
            
            .view-receipt-btn {
                padding: 4px 8px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .nav-header {
                padding: 6px 8px;
            }
            
            .container {
                padding: 8px;
            }
            
            h1 {
                font-size: 1.4em;
            }
            
            .search-filters {
                padding: 10px;
            }
            
            .orders-table {
                height: calc(100vh - 160px);
            }
            
            table {
                min-width: 750px;
                font-size: 11px;
            }
            
            th, td {
                padding: 4px 6px;
            }
        }
    </style>
</head>
<body>
    <div class="nav-header">
        <a href="indeck.php" class="back-button">← Back to POS</a>
        <h2>Order History</h2>
    </div>
    
    <div class="container">
        <h1>Order History</h1>
        
        <div class="search-filters">
            <form method="GET">
                <input type="text" name="search" placeholder="Search by Order ID" value="<?php echo htmlspecialchars($search); ?>">
                <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                <!-- Added order type filter dropdown -->
                <select name="order_type">
                    <option value="">All Types</option>
                    <option value="dine_in" <?php echo $order_type_filter === 'dine_in' ? 'selected' : ''; ?>>Dine In</option>
                    <option value="takeout" <?php echo $order_type_filter === 'takeout' ? 'selected' : ''; ?>>Takeout</option>
                </select>
                <button type="submit">Search</button>
                <a href="order_history.php">Clear</a>
            </form>
        </div>
        
        <div class="orders-table">
            <?php if ($orders->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date & Time</th>
                            <!-- Added Order Type column -->
                            <th>Type</th>
                            <th>Total Amount</th>
                            <th>Discount</th>
                            <th>Amount Paid</th>
                            <th>Change</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_id']); ?></strong></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></td>
                                <!-- Added order type display with badge styling -->
                                <td>
                                    <?php 
                                    $order_type = isset($order['order_type']) ? $order['order_type'] : 'dine_in';
                                    $badge_class = $order_type === 'takeout' ? 'takeout' : 'dine-in';
                                    $display_text = $order_type === 'takeout' ? 'Takeout' : 'Dine In';
                                    ?>
                                    <span class="order-type-badge <?php echo $badge_class; ?>">
                                        <?php echo $display_text; ?>
                                    </span>
                                </td>
                                <td>₱<?php echo number_format($order['total'], 2); ?></td>
                                <td>₱<?php echo number_format($order['discount'], 2); ?></td>
                                <td>₱<?php echo number_format($order['amount_paid'], 2); ?></td>
                                <td>₱<?php echo number_format($order['amount_paid'] - $order['total'], 2); ?></td>
                                <td>
                                    <a href="view_receipt.php?order_id=<?php echo urlencode($order['order_id']); ?>" 
                                       class="view-receipt-btn">View Receipt</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo urlencode($date_filter); ?>&order_type=<?php echo urlencode($order_type_filter); ?>">← Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo urlencode($date_filter); ?>&order_type=<?php echo urlencode($order_type_filter); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo urlencode($date_filter); ?>&order_type=<?php echo urlencode($order_type_filter); ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-orders">
                    <h3>No orders found</h3>
                    <p>No orders match your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
