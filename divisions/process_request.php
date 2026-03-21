<?php
include "../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

/**
 * 1. MANDATORY SECURITY GATE
 * Prevent non-SuperAdmins from executing this script via URL manipulation.
 */
if ($_SESSION['role'] !== 'SuperAdmin') {
    $_SESSION['error_msg'] = "Access Denied: Only SuperAdmins can approve lifecycle changes.";
    header("Location: returned_assets.php");
    exit;
}

// Default states for the SweetAlert UI
$status_icon = 'info';
$status_title = 'Processing...';
$status_text = 'Initializing request.';
$redirect = "returned_assets.php";

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // 2. Fetch details to know how to route the asset
    $stmt = $conn->prepare("
        SELECT da.stock_detail_id, da.status, im.item_name, da.division_asset_id 
        FROM division_assets da
        JOIN stock_details sd ON da.stock_detail_id = sd.id
        JOIN items_master im ON sd.stock_item_id = im.id
        WHERE da.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();

    if ($asset) {
        $stock_id = $asset['stock_detail_id'];
        $request_type = $asset['status'];
        $asset_tag = $asset['division_asset_id'];
        $item_name = $asset['item_name'];

        $conn->begin_transaction();

        try {
            if ($request_type == 'return_requested') {
            // Ensure we have the PERMANENT stock_detail_id
            $stock_id = $asset['stock_detail_id']; 
            $asset_tag = $asset['division_asset_id'];
            $admin_id = $_SESSION['user_id'];

            $conn->begin_transaction();

            try {
                // 1. Log the completion of the lifecycle
                $log_notes = "Asset $asset_tag returned to stock. Processed by SuperAdmin.";
                $log_stmt = $conn->prepare("INSERT INTO asset_logs (asset_id, action_type, performed_by, notes) VALUES (?, 'completed', ?, ?)");
                $log_stmt->bind_param("iis", $stock_id, $admin_id, $log_notes);
                $log_stmt->execute();

                // 2. Update Stock to Available
                $conn->query("UPDATE stock_details SET status = 'available' WHERE id = $stock_id");

                // 3. Remove from Active Divisions & Dispatch
                // We fetch the dispatch ID right before deleting
                $res = $conn->query("SELECT dispatch_detail_id FROM division_assets WHERE id = $id");
                $dd_id = $res->fetch_assoc()['dispatch_detail_id'] ?? null;

                $conn->query("DELETE FROM division_assets WHERE id = $id");
                if ($dd_id) {
                    $conn->query("DELETE FROM dispatch_details WHERE id = $dd_id");
                }

                $conn->commit();
                $status_icon = 'success';
            } catch (Exception $e) {
                $conn->rollback();
                $status_icon = 'error';
                $status_text = $e->getMessage();
            }




            } elseif ($request_type == 'repair_requested') {
                // ROUTE: Maintenance (Locked from dispatch)
                $conn->query("UPDATE stock_details SET status = 'maintenance' WHERE id = $stock_id");
                $conn->query("UPDATE division_assets SET status = 'under_repair' WHERE id = $id");
                
                $status_icon = 'warning';
                $status_title = 'Sent for Repair';
                $status_text = "Asset $asset_tag moved to Maintenance. Update progress in the Services module.";

            } elseif ($request_type == 'dispose_requested') {
                // ROUTE: E-Waste (Decommissioned)
                $conn->query("UPDATE stock_details SET status = 'disposed' WHERE id = $stock_id");
                $conn->query("DELETE FROM division_assets WHERE id = $id");
                
                $status_icon = 'error';
                $status_title = 'Asset Decommissioned';
                $status_text = "Asset $asset_tag has been moved to E-Waste records.";
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $status_icon = 'error';
            $status_title = 'Database Error';
            $status_text = $e->getMessage();
        }
    } else {
        $status_icon = 'error';
        $status_title = 'Record Not Found';
        $status_text = "This asset request no longer exists or has already been processed.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Processing Request</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f8fafc; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0; 
        }
    </style>
</head>
<body>
    <script>
        Swal.fire({
            icon: '<?= $status_icon ?>',
            title: '<?= $status_title ?>',
            text: '<?= $status_text ?>',
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Return to List',
            allowOutsideClick: false,
            customClass: {
                popup: 'rounded-4 shadow-lg',
                confirmButton: 'rounded-3 px-4 py-2 fw-bold'
            }
        }).then(() => {
            window.location.href = '<?= $redirect ?>';
        });
    </script>
</body>
</html>