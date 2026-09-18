<?php
require_once __DIR__ . "/auth.php"; 
require_once __DIR__ . "/../config/db.php";

$role = $_SESSION["role"] ?? 'User';
$pending_count = 0;
$html = '';

if (in_array($role, [ROLE_SUPERADMIN, ROLE_ADMIN], true)) {
    // Get count
    $count_res = $conn->query("SELECT COUNT(*) as total FROM division_assets WHERE status IN ('service_requested','return_requested', 'repair_requested', 'dispose_requested')");
    if ($count_res) {
        $pending_count = (int)$count_res->fetch_assoc()['total'];
    }

    // Get records
    $notif_query = "SELECT da.*, d.division_name, im.item_name, sd.serial_number, da.updated_at
                    FROM division_assets da
                    JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
                    JOIN dispatch_master dm ON dd.dispatch_id = dm.id
                    JOIN divisions d ON dm.division_id = d.id
                    JOIN stock_details sd ON da.stock_detail_id = sd.id
                    JOIN items_master im ON sd.stock_item_id = im.id
                    WHERE da.status IN ('service_requested', 'return_requested', 'repair_requested', 'dispose_requested')
                    ORDER BY da.updated_at DESC LIMIT 10";
    $notif_res = $conn->query($notif_query);

    if ($pending_count > 0 && $notif_res && $notif_res->num_rows > 0) {
        while($n = $notif_res->fetch_assoc()) {
            $type = strtoupper(str_replace('_requested', '', $n['status']));
            $icon = ($type == 'REPAIR') ? 'bi-tools text-info' : (($type == 'RETURN') ? 'bi-arrow-left-circle text-warning' : 'bi-trash text-danger');
            $bg = ($type == 'REPAIR') ? 'bg-info-subtle' : (($type == 'RETURN') ? 'bg-warning-subtle' : 'bg-danger-subtle');
            $time = date('H:i', strtotime($n['updated_at']));
            
            $html .= '<a href="/cecsms/divisions/returned_assets.php" class="dropdown-item p-3 border-bottom d-flex gap-3 align-items-start whitespace-normal">
                        <div class="'.$bg.' rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                            <i class="bi '.$icon.'"></i>
                        </div>
                        <div class="w-100">
                            <div class="d-flex justify-content-between">
                                <p class="mb-0 extra-small fw-bold text-dark">'.htmlspecialchars($n['division_name']).'</p>
                                <span class="text-muted" style="font-size: 9px;">'.$time.'</span>
                            </div>
                            <p class="mb-1 text-muted extra-small">
                                <strong>'.$type.':</strong> '.htmlspecialchars($n['item_name']).'
                            </p>
                        </div>
                      </a>';
        }
    } else {
        $html = '<div class="p-4 text-center">
                    <i class="bi bi-check2-circle fs-3 text-muted opacity-50"></i>
                    <p class="text-muted extra-small mt-2 mb-0">No pending stock transitions.</p>
                 </div>';
    }
}

echo json_encode(['count' => $pending_count, 'html' => $html]);