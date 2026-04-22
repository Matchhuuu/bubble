<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bh");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create void_logs table if it doesn't exist
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

// Get all void logs
$query = "SELECT * FROM void_logs ORDER BY void_time DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Void Logs - Bubble Hideout POS</title>
    <link rel="icon" href=/media/BUBBLE.jpg></link>
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

        .void-title {
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

        .price {
            font-weight: 600;
            color: #2e7d32;
        }

        .void-type {
            background-color: #fff3e0;
            color: #f57c00;
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

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding: 20px;
            }

            .void-title {
                font-size: 32px;
            }

            .main-content {
                padding: 20px;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h1 class="void-title">Void<br>Logs</h1>
            <a href="indeck.php" class="back-button">Back to POS</a>
        </div>
        
        <div class="main-content">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item</th>
                            <th>Size</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Flavor</th>
                            <th>Void Type</th>
                            <th>Voided By</th>
                            <th>PIN Used</th>
                            <th>Void Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['size']); ?></td>
                                    <td><?php echo $row['quantity']; ?></td>
                                    <td class="price">₱<?php echo number_format($row['price'], 2); ?></td>
                                    <td><?php echo $row['flavor'] ? htmlspecialchars($row['flavor']) : 'N/A'; ?></td>
                                    <td><span class="void-type"><?php echo htmlspecialchars($row['void_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['voided_by']); ?></td>
                                    <td class="pin-masked"><?php echo str_repeat('*', 4); ?></td>
                                    <td class="date-time"><?php echo date('Y-m-d H:i:s', strtotime($row['void_time'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="no-data">No void logs found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>