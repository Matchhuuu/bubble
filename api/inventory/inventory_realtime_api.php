<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';

// Database connection
include "db_conn.php"; $connection = $conn;

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Get optional filters (sanitized)
$search = isset($_GET['search']) ? $connection->real_escape_string(trim($_GET['search'])) : '';
$category = isset($_GET['category']) ? $connection->real_escape_string(trim($_GET['category'])) : '';

$filter_conditions = "";
if ($search !== "") {
    // search on item name (case-insensitive depends on collation)
    $filter_conditions .= " AND ui.item_name LIKE '%$search%'";
}
// <CHANGE> Make category comparison case-insensitive
// <CHANGE> Filter by ums.category instead of ui.category (categories are in unified_menu_system)
if ($category !== "") {
    $filter_conditions .= " AND LOWER(ui.category) = LOWER('$category')";
}

if ($action === 'get_inventory_stats') {
    // <CHANGE> Updated liquid stats query to use 1900 conversion factor for liquid measurements
    $liquid_stats_query = "SELECT 
                        COUNT(*) as total_items,
                        COALESCE(SUM(current_quantity/1900 * cost_per_unit), 0) as total_value,
                        SUM(CASE WHEN current_quantity <= 100 THEN 1 ELSE 0 END) as critical_items,
                        SUM(CASE WHEN current_quantity > 100 AND current_quantity <= 800 THEN 1 ELSE 0 END) as low_items
                    FROM unified_inventory 
                    WHERE is_liquid = 1";
    
    $liquid_result = $connection->query($liquid_stats_query);
    $liquid_stats = $liquid_result ? $liquid_result->fetch_assoc() : ['total_items' => 0, 'total_value' => 0, 'critical_items' => 0, 'low_items' => 0];
    
    // <CHANGE> Updated solid stats query to use COALESCE for null safety
    $solid_stats_query = "SELECT 
                        COUNT(*) as total_items,
                        COALESCE(SUM(current_quantity * cost_per_unit), 0) as total_value,
                        SUM(CASE WHEN current_quantity <= 10 THEN 1 ELSE 0 END) as critical_items,
                        SUM(CASE WHEN current_quantity > 10 AND current_quantity <= 50 THEN 1 ELSE 0 END) as low_items
                    FROM unified_inventory 
                    WHERE is_liquid = 0";
    
    $solid_result = $connection->query($solid_stats_query);
    $solid_stats = $solid_result ? $solid_result->fetch_assoc() : ['total_items' => 0, 'total_value' => 0, 'critical_items' => 0, 'low_items' => 0];
    
    echo json_encode([
        'liquid_stats' => $liquid_stats,
        'solid_stats' => $solid_stats
    ]);
}
elseif ($action === 'get_liquid_inventory') {
    // append filter_conditions to WHERE clause
    $query = "SELECT 
                ui.item_id as id,
    ui.item_name,
    ui.category,
    ui.current_quantity,
    ui.unit,
    ui.cost_per_unit,
    (ui.current_quantity/1900 * ui.cost_per_unit) as total_value,
    ui.expiration_date,
    ui.is_liquid,
                GROUP_CONCAT(DISTINCT CONCAT(TRIM(ums.category), ' (', TRIM(ums.section), ')') SEPARATOR ', ') as used_in_menu_display,
                CASE 
                    WHEN ui.current_quantity <= 100 THEN 'CRITICAL'
                    WHEN ui.current_quantity <= 800 THEN 'LOW'
                    ELSE 'GOOD'
                END as status
            FROM unified_inventory ui
            LEFT JOIN unified_menu_system ums ON ui.item_id = ums.ingredient_id
            WHERE ui.is_liquid = 1
            $filter_conditions
            GROUP BY ui.item_id, ui.item_name, ui.category, ui.current_quantity, ui.unit, ui.cost_per_unit
            ORDER BY
                CASE 
                    WHEN ui.current_quantity <= 100 THEN 1
                    WHEN ui.current_quantity <= 800 THEN 2
                    ELSE 3
                END,
                ui.item_name";
    
    $result = $connection->query($query);
    $items = [];
    
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    echo json_encode(['items' => $items]);
}
elseif ($action === 'get_solid_inventory') {
    $query = "SELECT 
                ui.item_id as id,
                ui.item_name,
                ui.category,
                ui.current_quantity,
                ui.unit,
                ui.cost_per_unit,
                (ui.current_quantity * ui.cost_per_unit) as total_value,
                GROUP_CONCAT(DISTINCT CONCAT(TRIM(ums.category), ' (', TRIM(ums.section), ')') SEPARATOR ', ') as used_in_menu_display,
                CASE 
                    WHEN ui.current_quantity <= 10 THEN 'CRITICAL'
                    WHEN ui.current_quantity <= 50 THEN 'LOW'
                    ELSE 'GOOD'
                END as status
            FROM unified_inventory ui
            LEFT JOIN unified_menu_system ums ON ui.item_id = ums.ingredient_id
            WHERE ui.is_liquid = 0
            $filter_conditions
            GROUP BY ui.item_id, ui.item_name, ui.category, ui.current_quantity, ui.unit, ui.cost_per_unit
            ORDER BY
                CASE 
                    WHEN ui.current_quantity <= 10 THEN 1
                    WHEN ui.current_quantity <= 50 THEN 2
                    ELSE 3
                END,
                ui.item_name";
    
    $result = $connection->query($query);
    $items = [];
    
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    echo json_encode(['items' => $items]);
}
else {
    echo json_encode(['error' => 'Invalid action']);
}

$connection->close();
?>

