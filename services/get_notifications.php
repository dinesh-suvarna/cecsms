<?php
session_start();
require_once __DIR__ . "/../config/db.php"; 


$notif_query = "SELECT 
                    da.status, 
                    d.division_name, 
                    im.item_name,
                    al.notes,
                    al.created_at
                FROM division_assets da 
                JOIN stock_details sd ON da.stock_detail_id = sd.id
                JOIN items_master im ON sd.stock_item_id = im.id
                JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
                JOIN dispatch_master dm ON dd.dispatch_id = dm.id
                JOIN divisions d ON dm.division_id = d.id
                LEFT JOIN asset_logs al ON sd.id = al.asset_id 
                    AND al.action_type = da.status
                WHERE da.status IN ('return_requested', 'repair_requested', 'dispose_requested')
                GROUP BY da.id 
                ORDER BY al.created_at DESC LIMIT 5";

$result = $conn->query($notif_query);

$items = [];
while($row = $result->fetch_assoc()) {
    
    $type = strtoupper(str_replace('_requested', '', $row['status']));
    $row['message'] = "<strong>$type:</strong> " . $row['item_name'];
    $items[] = $row;
}

header('Content-Type: application/json');
echo json_encode([
    'count' => count($items),
    'items' => $items
]);