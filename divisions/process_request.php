<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

/**
 * 1. MANDATORY SECURITY
 */
if ($_SESSION['role'] !== 'SuperAdmin') {
    $_SESSION['error_msg'] = "Access Denied: Only SuperAdmins can approve lifecycle changes.";
    header("Location: returned_assets.php");
    exit;
}

$status_icon = 'info';
$status_title = 'Processing...';
$status_text = 'Initializing request.';
$redirect = "returned_assets.php";

if (isset($_GET['id']) && isset($_GET['action'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action']; // 'approve' or 'deny'
    $deny_reason = isset($_GET['reason']) ? trim($_GET['reason']) : '';

    // Fetch asset details
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
        $admin_id = $_SESSION['user_id'];

        $conn->begin_transaction();

        try {
            if ($action === 'deny') {
                /* ================= DENIAL LOGIC ================= */
                $stmt_revert = $conn->prepare("UPDATE division_assets SET status = 'assigned' WHERE id = ?");
                $stmt_revert->bind_param("i", $id);
                $stmt_revert->execute();

                // Log the rejection so it shows up in red in the Audit Trail
                $log_notes = "Request Rejected: " . $deny_reason;
                $log_stmt = $conn->prepare("INSERT INTO asset_logs (asset_id, action_type, performed_by, notes) VALUES (?, 'completed', ?, ?)");
                $log_stmt->bind_param("iis", $stock_id, $admin_id, $log_notes);
                $log_stmt->execute();

                $status_icon = 'error';
                $status_title = 'Request Denied';
                $status_text = "The request for $asset_tag has been rejected and reverted to assigned status.";

            } else {
                /* ================= APPROVAL LOGIC ================= */
                if ($request_type == 'return_requested') {
                    $log_notes = "Asset $asset_tag returned to stock. Approved by Admin.";
                    $log_stmt = $conn->prepare("INSERT INTO asset_logs (asset_id, action_type, performed_by, notes) VALUES (?, 'completed', ?, ?)");
                    $log_stmt->bind_param("iis", $stock_id, $admin_id, $log_notes);
                    $log_stmt->execute();

                    $conn->query("UPDATE stock_details SET status = 'available' WHERE id = $stock_id");
                    
                    $res = $conn->query("SELECT dispatch_detail_id FROM division_assets WHERE id = $id");
                    $dd_id = $res->fetch_assoc()['dispatch_detail_id'] ?? null;

                    $conn->query("DELETE FROM division_assets WHERE id = $id");
                    if ($dd_id) { $conn->query("DELETE FROM dispatch_details WHERE id = $dd_id"); }

                    $status_icon = 'success';
                    $status_title = 'Return Approved';
                    $status_text = "Asset $asset_tag is now back in available stock.";

                } elseif ($request_type == 'repair_requested') {
                    // Log the transfer to repair
                    $log_notes = "Repair approved. Asset $asset_tag moved to maintenance.";
                    $log_stmt = $conn->prepare("INSERT INTO asset_logs (asset_id, action_type, performed_by, notes) VALUES (?, 'repair_requested', ?, ?)");
                    $log_stmt->bind_param("iis", $stock_id, $admin_id, $log_notes);
                    $log_stmt->execute();

                    $conn->query("UPDATE stock_details SET status = 'maintenance' WHERE id = $stock_id");
                    $conn->query("UPDATE division_assets SET status = 'under_repair' WHERE id = $id");
                    
                    $status_icon = 'warning';
                    $status_title = 'Repair Authorized';
                    $status_text = "Asset $asset_tag is now marked as under repair.";

                // } elseif ($request_type == 'dispose_requested') {
                //     $log_notes = "Asset $asset_tag decommissioned and sent to E-Waste.";
                //     $log_stmt = $conn->prepare("INSERT INTO asset_logs (asset_id, action_type, performed_by, notes) VALUES (?, 'dispose_requested', ?, ?)");
                //     $log_stmt->bind_param("iis", $stock_id, $admin_id, $log_notes);
                //     $log_stmt->execute();

                //     $conn->query("UPDATE stock_details SET status = 'disposed' WHERE id = $stock_id");
                //     $conn->query("DELETE FROM division_assets WHERE id = $id");
                    
                //     $status_icon = 'info';
                //     $status_title = 'Asset Disposed';
                //     $status_text = "Asset $asset_tag has been moved to e-waste records.";
                // }
                } elseif ($request_type == 'dispose_requested') {
    // 1. Get the department's original reason/remarks from your asset logs to save as the disposal reason
    $remark_stmt = $conn->prepare("SELECT notes FROM asset_logs WHERE asset_id = ? AND action_type = 'dispose_requested' ORDER BY id DESC LIMIT 1");
    $remark_stmt->bind_param("i", $stock_id);
    $remark_stmt->execute();
    $remark_res = $remark_stmt->get_result()->fetch_assoc();
    $disposal_reason = $remark_res['notes'] ?? 'Decommissioned by Department';

    // 2. Change master inventory stock status to 'disposed' so it's taken out of service
    $update_stock = $conn->prepare("UPDATE stock_details SET status = 'disposed' WHERE id = ?");
    $update_stock->bind_param("i", $stock_id);
    $update_stock->execute();

    // 3. LOG TO E-WASTE: Insert the item straight into your e-waste registry table
    $ewaste_stmt = $conn->prepare("INSERT INTO ewaste_items (stock_detail_id, division_asset_id, disposal_reason, status) VALUES (?, ?, ?, 'Pending_Verification')");
    $ewaste_stmt->bind_param("iss", $stock_id, $asset_tag, $disposal_reason);
    $ewaste_stmt->execute();

    // 4. Remove the asset instance completely from the active department allocation view
    $delete_asset = $conn->prepare("DELETE FROM division_assets WHERE id = ?");
    $delete_asset->bind_param("i", $id);
    $delete_asset->execute();
    
    // Setup SweetAlert parameters for success screen
    $status_icon = 'success';
    $status_title = 'Asset Sent to E-Waste';
    $status_text = "Asset $asset_tag has been successfully logged into the E-Waste registry.";
}
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
        $status_text = "Request no longer exists or was already processed.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Processing...</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
    </style>
</head>
<body>
    <script>
        Swal.fire({
            icon: '<?= $status_icon ?>',
            title: '<?= $status_title ?>',
            text: '<?= $status_text ?>',
            confirmButtonColor: '<?= ($status_icon == 'error') ? '#ef4444' : '#10b981' ?>',
            confirmButtonText: 'Return to List',
            allowOutsideClick: false,
            customClass: {
                popup: 'rounded-4 shadow-lg border-0',
                confirmButton: 'rounded-3 px-4 py-2 fw-bold'
            }
        }).then(() => {
            window.location.href = '<?= $redirect ?>';
        });
    </script>
</body>
</html>
