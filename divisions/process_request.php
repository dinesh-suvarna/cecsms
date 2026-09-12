<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

if (($_SESSION['role'] ?? '') !== 'SuperAdmin') {
    $_SESSION['error_msg'] = "Access Denied: Only SuperAdmins can approve lifecycle changes.";
    header("Location: returned_assets.php");
    exit;
}

$status_icon  = 'info';
$status_title = 'Processing...';
$status_text  = 'Initializing request.';
$redirect     = "returned_assets.php";

if (isset($_GET['id']) && isset($_GET['action'])) {
    $id          = intval($_GET['id']);
    $action      = $_GET['action']; 
    $deny_reason = isset($_GET['reason']) ? trim($_GET['reason']) : '';

    // Fetch asset, asset_tag, dispatch info AND unit name BEFORE deletion
    $stmt = $conn->prepare("
        SELECT 
            da.stock_detail_id, 
            da.status, 
            da.division_asset_id AS asset_tag,
            da.dispatch_detail_id,
            im.item_name,
            COALESCE(un.unit_name, dm.unit_id) AS fetched_unit_name
        FROM division_assets da
        JOIN stock_details sd ON da.stock_detail_id = sd.id
        JOIN items_master im ON sd.stock_item_id = im.id
        LEFT JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
        LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
        LEFT JOIN units un ON dm.unit_id = un.id
        WHERE da.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();

    if ($asset) {
        $stock_id   = $asset['stock_detail_id'];
        $asset_tag  = $asset['asset_tag'] ?? null;
        
        // Ensure unit_name is a non-zero human-readable string
        $unit_name  = (!empty($asset['fetched_unit_name']) && $asset['fetched_unit_name'] !== '0') 
                      ? $asset['fetched_unit_name'] 
                      : 'Main Stock / Division';
                      
        $dd_id      = $asset['dispatch_detail_id'] ?? null;
        $admin_id   = $_SESSION['user_id'] ?? null;

        $conn->begin_transaction();

        try {
            // STEP 1: Fetch the original pending request log ID to link transaction tags
            $ref_stmt = $conn->prepare("
                SELECT id FROM asset_logs 
                WHERE asset_id = ? AND action_type IN ('return_requested', 'dispose_requested', 'repair_requested') 
                ORDER BY id DESC LIMIT 1
            ");
            $ref_stmt->bind_param("i", $stock_id);
            $ref_stmt->execute();
            $ref_res       = $ref_stmt->get_result()->fetch_assoc();
            $parent_log_id = $ref_res['id'] ?? null;
            $ref_prefix    = $parent_log_id ? "[REF:#$parent_log_id] " : "";

            if ($action === 'deny') {
                $stmt_revert = $conn->prepare("UPDATE division_assets SET status = 'assigned' WHERE id = ?");
                $stmt_revert->bind_param("i", $id);
                $stmt_revert->execute();

                $log_notes = $ref_prefix . "Request Rejected: " . ($deny_reason ?: 'No reason provided.');
                $log_stmt  = $conn->prepare("
                    INSERT INTO asset_logs (asset_id, asset_tag, unit_name, action_type, performed_by, notes) 
                    VALUES (?, ?, ?, 'request_rejected', ?, ?)
                ");
                $log_stmt->bind_param("isiss", $stock_id, $asset_tag, $unit_name, $admin_id, $log_notes);
                $log_stmt->execute();

                $status_icon  = 'error';
                $status_title = 'Request Denied';
                $status_text  = "The request for asset $asset_tag has been rejected.";

            } elseif ($action === 'return_requested') {
                $log_notes = $ref_prefix . "Return approved by Admin. Asset returned to stock.";
                $log_stmt  = $conn->prepare("
                    INSERT INTO asset_logs (asset_id, asset_tag, unit_name, action_type, performed_by, notes) 
                    VALUES (?, ?, ?, 'return_approved', ?, ?)
                ");
                $log_stmt->bind_param("isiss", $stock_id, $asset_tag, $unit_name, $admin_id, $log_notes);
                $log_stmt->execute();

                $conn->query("UPDATE stock_details SET status = 'available' WHERE id = $stock_id");
                $conn->query("DELETE FROM division_assets WHERE id = $id");

                if ($dd_id) { 
                    $conn->query("UPDATE dispatch_details SET returned_quantity = IFNULL(returned_quantity, 0) + 1 WHERE id = $dd_id"); 
                }

                $status_icon  = 'success';
                $status_title = 'Return Approved';
                $status_text  = "Asset $asset_tag has been returned back to available inventory.";

            } elseif ($action === 'repair_requested') {
                $log_notes = $ref_prefix . "Repair authorized by Admin. Asset $asset_tag moved to maintenance.";
                $log_stmt  = $conn->prepare("
                    INSERT INTO asset_logs (asset_id, asset_tag, unit_name, action_type, performed_by, notes) 
                    VALUES (?, ?, ?, 'repair_approved', ?, ?)
                ");
                $log_stmt->bind_param("isiss", $stock_id, $asset_tag, $unit_name, $admin_id, $log_notes);
                $log_stmt->execute();

                $conn->query("UPDATE stock_details SET status = 'maintenance' WHERE id = $stock_id");
                $conn->query("UPDATE division_assets SET status = 'under_repair' WHERE id = $id");

                $status_icon  = 'warning';
                $status_title = 'Repair Authorized';
                $status_text  = "Asset $asset_tag is now marked as under repair.";

            } elseif ($action === 'dispose_requested') {
                $remark_stmt = $conn->prepare("SELECT notes FROM asset_logs WHERE asset_id = ? AND action_type = 'dispose_requested' ORDER BY id DESC LIMIT 1");
                $remark_stmt->bind_param("i", $stock_id);
                $remark_stmt->execute();
                $remark_res      = $remark_stmt->get_result()->fetch_assoc();
                $disposal_reason = $remark_res['notes'] ?? 'Decommissioned by Admin';

                $log_notes = $ref_prefix . "Asset $asset_tag decommissioned and sent to E-Waste.";
                $log_stmt  = $conn->prepare("
                    INSERT INTO asset_logs (asset_id, asset_tag, unit_name, action_type, performed_by, notes) 
                    VALUES (?, ?, ?, 'disposal_approved', ?, ?)
                ");
                $log_stmt->bind_param("isiss", $stock_id, $asset_tag, $unit_name, $admin_id, $log_notes);
                $log_stmt->execute();

                $conn->query("UPDATE stock_details SET status = 'disposed' WHERE id = $stock_id");

                $ewaste_stmt = $conn->prepare("INSERT INTO ewaste_items (stock_detail_id, division_asset_id, disposal_reason, status) VALUES (?, ?, ?, 'Pending_Verification')");
                $ewaste_stmt->bind_param("iss", $stock_id, $asset_tag, $disposal_reason);
                $ewaste_stmt->execute();

                $conn->query("DELETE FROM division_assets WHERE id = $id");

                $status_icon  = 'success';
                $status_title = 'Asset Sent to E-Waste';
                $status_text  = "Asset $asset_tag decommissioned successfully.";
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $status_icon  = 'error';
            $status_title = 'Database Error';
            $status_text  = $e->getMessage();
        }
    } else {
        $status_icon  = 'error';
        $status_title = 'Record Not Found';
        $status_text  = "Request no longer exists or was already processed.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Processing...</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        Swal.fire({
            icon: '<?= $status_icon ?>',
            title: '<?= $status_title ?>',
            text: '<?= $status_text ?>',
            confirmButtonColor: '<?= ($status_icon == 'error') ? '#ef4444' : '#10b981' ?>'
        }).then(() => {
            window.location.href = '<?= $redirect ?>';
        });
    </script>
</body>
</html>