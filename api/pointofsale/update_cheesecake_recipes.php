<?php
// Connect to database
require_once 'config.php';
include "db_conn.php";

// Begin transaction
$conn->begin_transaction();

try {
    // First ensure all cheesecake menu items exist
    $conn->query("INSERT IGNORE INTO menu_items (id, category_id, name) VALUES 
        ('DARKCHOCO_CHSCAKE', 'CHSCAKE', 'Dark Chocolate'),
        ('MATCHA_CHSCAKE', 'CHSCAKE', 'Matcha'),
        ('OREO_CHSCAKE', 'CHSCAKE', 'Oreo'),
        ('REDVLVT_CHSCAKE', 'CHSCAKE', 'Red Velvet'),
        ('TARO_CHSCAKE', 'CHSCAKE', 'Taro')");

    // Ensure prices exist for all cheesecake items
    $conn->query("INSERT IGNORE INTO prices (id, menu_item_id, size_id, price, section, categories) VALUES 
        ('DARKCHOCO_CHSCAKE_GRANDE', 'DARKCHOCO_CHSCAKE', 'GRANDE', 109.00, 'drinks', 'CHSCAKE'),
        ('DARKCHOCO_CHSCAKE_REG', 'DARKCHOCO_CHSCAKE', 'REG', 119.00, 'drinks', 'CHSCAKE'),
        ('MATCHA_CHSCAKE_GRANDE', 'MATCHA_CHSCAKE', 'GRANDE', 109.00, 'drinks', 'CHSCAKE'),
        ('MATCHA_CHSCAKE_REG', 'MATCHA_CHSCAKE', 'REG', 119.00, 'drinks', 'CHSCAKE'),
        ('OREO_CHSCAKE_GRANDE', 'OREO_CHSCAKE', 'GRANDE', 109.00, 'drinks', 'CHSCAKE'),
        ('OREO_CHSCAKE_REG', 'OREO_CHSCAKE', 'REG', 119.00, 'drinks', 'CHSCAKE'),
        ('REDVLVT_CHSCAKE_GRANDE', 'REDVLVT_CHSCAKE', 'GRANDE', 109.00, 'drinks', 'CHSCAKE'),
        ('REDVLVT_CHSCAKE_REG', 'REDVLVT_CHSCAKE', 'REG', 119.00, 'drinks', 'CHSCAKE'),
        ('TARO_CHSCAKE_GRANDE', 'TARO_CHSCAKE', 'GRANDE', 109.00, 'drinks', 'CHSCAKE'),
        ('TARO_CHSCAKE_REG', 'TARO_CHSCAKE', 'REG', 119.00, 'drinks', 'CHSCAKE')");

    // Add recipe entries for cream cheese tracking
    // Each cheesecake uses 0.100 units of cream cheese (item_id: 2024123124)
    $conn->query("INSERT IGNORE INTO recipe (recipe_id, item_id, quantity_required) VALUES 
        ('DARKCHOCO_CHSCAKE', 2024123124, 0.100),
        ('MATCHA_CHSCAKE', 2024123124, 0.100),
        ('OREO_CHSCAKE', 2024123124, 0.100),
        ('REDVLVT_CHSCAKE', 2024123124, 0.100),
        ('TARO_CHSCAKE', 2024123124, 0.100)");

    // Update the inventory tracking function to handle cheesecake items
    function updateCheesecakeInventory($conn, $menuItemId, $quantity) {
        $recipe = getRecipe($conn, $menuItemId);
        foreach ($recipe as $itemId => $requiredQuantity) {
            $stmt = $conn->prepare("UPDATE inventory SET current_quantity = current_quantity - ? WHERE item_id = ?");
            $totalRequired = $requiredQuantity * $quantity;
            $stmt->bind_param("di", $totalRequired, $itemId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Verify all recipes are properly set up
    $result = $conn->query("SELECT COUNT(*) as count FROM recipe WHERE recipe_id LIKE '%CHSCAKE%'");
    $row = $result->fetch_assoc();
    if ($row['count'] == 5) { // Should have 5 cheesecake recipes
        $conn->commit();
        echo "All cheesecake recipes and inventory tracking successfully updated!";
    } else {
        throw new Exception("Not all cheesecake recipes were added correctly.");
    }

} catch (Exception $e) {
    $conn->rollback();
    echo "Error updating cheesecake recipes: " . $e->getMessage();
}

// Update the checkStockAvailability function to properly check cream cheese inventory
function checkCheesecakeStockAvailability($conn, $menuItemId, $quantity) {
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

// Close the database connection
$conn->close();
?>
