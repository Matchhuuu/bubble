<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$database = "bh";

$connection = new mysqli($servername, $username, $password, $database);

if ($connection->connect_error) {
    echo json_encode(['error' => $connection->connect_error]);
    exit;
}

$sql_inventory = "SELECT 
    I.ITEM_ID,
    I.ITEM_NAME,
    I.CATEGORIES,
    I.UNIT,
    I.CURRENT_QUANTITY,
    I.PRICE,
    GROUP_CONCAT(
        CONCAT(R.recipe_id, ':', R.quantity_required)
        SEPARATOR ';'
    ) as recipe_details
    FROM INVENTORY I
    LEFT JOIN recipe R ON I.ITEM_ID = R.item_id
    GROUP BY I.ITEM_ID
    ORDER BY I.TIME_ADDED DESC";

$result_inventory = $connection->query($sql_inventory);

if (!$result_inventory) {
    echo json_encode(['error' => $connection->error]);
    exit;
}

$inventory_data = [];
while ($row = $result_inventory->fetch_assoc()) {
    $row['total_price'] = $row['PRICE'] * $row['CURRENT_QUANTITY'];
    $row['recipe_details'] = $row['recipe_details'] ?? "Not used in recipes";
    $inventory_data[] = $row;
}

echo json_encode($inventory_data);
?>
