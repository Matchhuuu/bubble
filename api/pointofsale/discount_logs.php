<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';
include "db_conn.php";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create discount_logs table if it doesn't exist
$createDiscountLogsTable = "
    CREATE TABLE IF NOT EXISTS discount_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        discount_type VARCHAR(50) DEFAULT NULL,
        discount_percentage DECIMAL(5,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        discount_amount DECIMAL(10,2) NOT NULL,
        applied_by VARCHAR(50) NOT NULL,
        pin_used VARCHAR(255) NOT NULL,
        applied_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
";
$conn->query($createDiscountLogsTable);

$checkColumn = "SHOW COLUMNS FROM discount_logs LIKE 'discount_type'";
$columnResult = $conn->query($checkColumn);
if ($columnResult->num_rows == 0) {
    $addColumn = "ALTER TABLE discount_logs ADD COLUMN discount_type VARCHAR(50) DEFAULT NULL AFTER id";
    $conn->query($addColumn);
}

// Get all discount logs
$query = "SELECT * FROM discount_logs ORDER BY applied_time DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Logs - Bubble Hideout POS</title>
    <link rel="stylesheet" href="/fonts/fonts.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            background-color: #4CAF50;
            width: 250px;
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .discount-title {
            color: white;
            font-size: 48px;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 30px;
        }

        .back-button {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s;
            display: inline-block;
        }

        .back-button:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .main-content {
            flex: 1;
            padding: 40px;
            background-color: white;
        }

        .table-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #f8f9fa;
            color: #666;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 20px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        td {
            padding: 20px 15px;
            border-bottom: 1px solid #f1f3f4;
            color: #333;
            font-size: 14px;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        tr:nth-child(even) {
            background-color: #fafafa;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }

        .amount {
            font-weight: 600;
            color: #2e7d32;
        }

        .percentage {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .pin-masked {
            font-family: monospace;
            color: #666;
        }

        .date-time {
            color: #666;
            font-size: 13px;
        }

        /* Updated discount type badge styling to handle Senior, PWD, and Custom discounts */
        .discount-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .discount-type-senior {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .discount-type-pwd {
            background-color: #f3e5f5;
            color: #6a1b9a;
        }

        .discount-type-custom {
            background-color: #fff3e0;
            color: #e65100;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding: 20px;
            }

            .discount-title {
                font-size: 32px;
            }

            .main-content {
                padding: 20px;
            }

            table {
                font-size: 12px;
            }

            th,
            td {
                padding: 10px 8px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="sidebar">
            <h1 class="discount-title">Discount<br>Logs</h1>
            <a href="indeck.php" class="back-button">Back to POS</a>
        </div>

        <div class="main-content">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Discount %</th>
                            <th>Subtotal</th>
                            <th>Discount Amount</th>
                            <th>Applied By</th>
                            <th>PIN Used</th>
                            <th>Applied Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>

                                    <td>
                                        <?php
                                        $discount_type = isset($row['discount_type']) ? $row['discount_type'] : '';

                                        // Check if it's a Senior discount
                                        if ($discount_type === 'Senior') {
                                            echo '<span class="discount-type-badge discount-type-senior">Senior</span>';
                                        }
                                        // Check if it's a PWD discount
                                        elseif ($discount_type === 'PWD') {
                                            echo '<span class="discount-type-badge discount-type-pwd">PWD</span>';
                                        }
                                        // Check if it's a custom discount (contains "Custom")
                                        elseif (strpos($discount_type, 'Custom') !== false) {
                                            echo '<span class="discount-type-badge discount-type-custom">' . htmlspecialchars($discount_type) . '</span>';
                                        }
                                        // Default case for any other discount type
                                        else {
                                            echo '<span class="discount-type-badge discount-type-custom">' . htmlspecialchars($discount_type) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><span
                                            class="percentage"><?php echo number_format($row['discount_percentage'], 2); ?>%</span>
                                    </td>
                                    <td class="amount">₱<?php echo number_format($row['subtotal'], 2); ?></td>
                                    <td class="amount">₱<?php echo number_format($row['discount_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['applied_by']); ?></td>
                                    <td class="pin-masked"><?php echo str_repeat('*', 4); ?></td>
                                    <td class="date-time"><?php echo date('Y-m-d H:i:s', strtotime($row['applied_time'])); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">No discount logs found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
