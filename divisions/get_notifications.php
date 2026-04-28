<?php
require_once __DIR__ . "/../config/db.php"; 
session_start();

$notif_division_id = $_SESSION['division_id'] ?? 0;

// Query
$notif_query = "
    /* 1. Only fetch UNREAD Repair/Return status updates */
    (SELECT 
        al.id AS ref_id, al.action_type, al.created_at, im.item_name, 'log' AS notif_source
     FROM asset_logs al
     INNER JOIN stock_details sd ON al.asset_id = sd.id
     INNER JOIN items_master im ON sd.stock_item_id = im.id
     LEFT JOIN division_assets da ON sd.id = da.stock_detail_id
     LEFT JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
     LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
     WHERE (dm.division_id = $notif_division_id OR al.performed_by IN (SELECT id FROM users WHERE division_id = $notif_division_id))
     AND al.action_type IN ('assigned', 'return_requested', 'repair_requested')
     AND al.is_read = 0) /* THE KEY ADDITION */

    UNION ALL

    /* 2. New Dispatches (These clear automatically when da.id is created) */
    (SELECT 
        dm.id AS ref_id, 'NEW_DISPATCH' AS action_type, dm.created_at AS created_at, 'Inventory Stock' AS item_name, 'dispatch' AS notif_source
     FROM dispatch_master dm
     INNER JOIN dispatch_details dd ON dm.id = dd.dispatch_id
     LEFT JOIN division_assets da ON dd.id = da.dispatch_detail_id
     WHERE dm.division_id = $notif_division_id AND dm.status = 'active' AND da.id IS NULL
     GROUP BY dm.id)
    ORDER BY created_at DESC";

$result = $conn->query($notif_query);
$count = $result->num_rows;
$html = '';

if($count > 0) {
    while($n = $result->fetch_assoc()) {
        $type = $n['action_type'];
        $ref_id = $n['ref_id']; // Captured from query
        $source = $n['notif_source']; // Captured from query ('log' or 'dispatch')
        
        $is_dispatch = ($type === 'NEW_DISPATCH');
        $is_rejected = strpos($type, 'REJECTED') !== false;

        // --- UI & LINK CONFIGURATION ---
        if ($is_dispatch) {
            $icon = 'bi-box-seam-fill text-primary';
            $bg = 'rgba(13, 110, 253, 0.05)';
            // Dispatches clear automatically once Asset ID is assigned
            $link = 'assign_asset.php'; 
            $title = "New Dispatch Received";
            $message = "Items arrived. Please <strong>Assign Asset IDs</strong>.";
        } else {
            $icon = $is_rejected ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success';
            $bg = $is_rejected ? 'rgba(239, 68, 68, 0.05)' : 'rgba(16, 185, 129, 0.05)';
            
            // ROUTING THROUGH TRACKER: 
            // point to mark_notif_read.php so the DB updates before the redirect
            $link = "mark_notif_read.php?id=$ref_id"; 
            
            $title = str_replace('_', ' ', $type);
            $message = "Request for <strong>" . $n['item_name'] . "</strong> updated.";
        }
        
        $time = date('M d, H:i', strtotime($n['created_at']));
        
        $html .= "<li>
            <a class='dropdown-item p-3 border-bottom d-flex gap-3 align-items-start' href='$link' style='background: $bg; white-space: normal;'>
                <i class='bi $icon fs-5 mt-1'></i>
                <div>
                    <p class='mb-1 small fw-bold text-dark'>" . strtoupper($title) . "</p>
                    <p class='mb-1 extra-small text-muted' style='font-size: 11px;'>$message</p>
                    <span class='text-muted' style='font-size: 10px;'>$time</span>
                </div>
            </a>
        </li>";
    }
} else {
    $html = '<li class="p-4 text-center text-muted small">
                <i class="bi bi-bell-slash d-block fs-2 opacity-25 mb-2"></i>
                No new updates.
             </li>';
}

echo json_encode(['count' => $count, 'html' => $html]);