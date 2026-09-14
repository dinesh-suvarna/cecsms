<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

/**
 * 1. MANDATORY SECURITY LOCKDOWN
 * Permit SuperAdmin and division-scoped Admin accounts to view the page.
 */
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    $_SESSION['error_msg'] = "Access Denied: You do not have permissions to view the E-Waste registry.";
    header("Location: ../dashboard.php"); 
    exit;
}

$page_title = "E-Waste Management Panel";
$page_icon  = "bi-trash3-fill";

// Build division scope filter if the user is a division-scoped Admin
$division_filter_sql = "";
$division_id = null;

if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin' && !empty($_SESSION['division_id'])) {
    $division_id = $_SESSION['division_id'];
    $division_filter_sql = " WHERE (dm_active.division_id = ? OR dm_history.division_id = ?) ";
}

/* ================= HANDLE STATUS UPDATE ================= */
if (isset($_POST['update_ewaste_status'])) {
    // Restrict processing capabilities strictly to SuperAdmin
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SuperAdmin') {
        $_SESSION['swal_type'] = "error";
        $_SESSION['swal_msg'] = "Access Denied: Only SuperAdmins can process e-waste updates.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $ewaste_id  = (int)$_POST['update_ewaste_status']; 
    $new_status = $_POST['new_status'];
    
    // Start transaction to keep both ewaste tracking and asset registry synchronized
    $conn->begin_transaction();
    try {
        // 1. Update the status inside the E-Waste tracking table
        $stmt = $conn->prepare("UPDATE ewaste_items SET status = ? WHERE ewaste_id = ?");
        $stmt->bind_param("si", $new_status, $ewaste_id);
        $stmt->execute();
        
        // 2. Fetch the linked stock_detail_id to update the inventory master lifecycle
        $stmt_fetch = $conn->prepare("SELECT stock_detail_id FROM ewaste_items WHERE ewaste_id = ?");
        $stmt_fetch->bind_param("i", $ewaste_id);
        $stmt_fetch->execute();
        $res = $stmt_fetch->get_result()->fetch_assoc();
        
       if ($res) {
        $stock_id = $res['stock_detail_id'];
        
        if ($new_status === 'Scrapped') {
            $stmt_stock = $conn->prepare("UPDATE stock_details SET status = 'disposed' WHERE id = ?");
            $stmt_stock->bind_param("i", $stock_id);
            $stmt_stock->execute();
        } elseif ($new_status === 'Refurbished') {
            // 1. Restore item to central inventory pool
            $stmt_stock = $conn->prepare("UPDATE stock_details SET status = 'available' WHERE id = ?");
            $stmt_stock->bind_param("i", $stock_id);
            $stmt_stock->execute();

            // 2. Clear any lingering division assignment mapping
            $stmt_clear_alloc = $conn->prepare("DELETE FROM division_assets WHERE stock_detail_id = ?");
            $stmt_clear_alloc->bind_param("i", $stock_id);
            $stmt_clear_alloc->execute();
        }
    } else {
        throw new Exception("Linked stock detail record not found.");
    }

    $conn->commit();
    $_SESSION['swal_type'] = "success";
    $_SESSION['swal_msg'] = "E-waste status updated to " . str_replace('_', ' ', $new_status);

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['swal_type'] = "error";
        $_SESSION['swal_msg'] = "Failed to update e-waste ledger record: " . $e->getMessage();
    }

header("Location: " . $_SERVER['PHP_SELF']);
exit;
}

/* ================= FETCH SCOPED DATA ================= */
$query = "
    SELECT 
        ew.ewaste_id, 
        ew.division_asset_id, 
        ew.status as ewaste_status, 
        ew.disposal_reason, 
        ew.logged_at,
        im.item_name, 
        sd.serial_number,
        mo.model_name,
        COALESCE(
            un_active.unit_name, 
            un_history.unit_name, 
            'Central Stock / Unassigned'
        ) as source_unit,
        COALESCE(
            dm_active_div.division_name, 
            dm_history_div.division_name, 
            'Central Stock / Unassigned'
        ) as source_division
    FROM ewaste_items ew
    JOIN stock_details sd ON sd.id = ew.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    LEFT JOIN item_models mo ON sd.model_id = mo.id
    
    -- Active Division Assets Link
    LEFT JOIN division_assets da ON sd.id = da.stock_detail_id
    LEFT JOIN dispatch_details dd_active ON da.dispatch_detail_id = dd_active.id
    LEFT JOIN dispatch_master dm_active ON dd_active.dispatch_id = dm_active.id
    LEFT JOIN divisions dm_active_div ON dm_active.division_id = dm_active_div.id
    LEFT JOIN units un_active ON dm_active.unit_id = un_active.id

    -- Historical Dispatch Link (Fallback if division_assets is cleared/changed)
    LEFT JOIN dispatch_details dd_history ON sd.id = dd_history.stock_detail_id
    LEFT JOIN dispatch_master dm_history ON dd_history.dispatch_id = dm_history.id
    LEFT JOIN divisions dm_history_div ON dm_history.division_id = dm_history_div.id
    LEFT JOIN units un_history ON dm_history.unit_id = un_history.id
    
    $division_filter_sql
    GROUP BY ew.ewaste_id
    ORDER BY ew.logged_at DESC
";

if (!empty($division_filter_sql)) {
    $stmt_fetch_all = $conn->prepare($query);
    $stmt_fetch_all->bind_param("ii", $division_id, $division_id);
    $stmt_fetch_all->execute();
    $result = $stmt_fetch_all->get_result();
} else {
    $result = $conn->query($query);
}

// Start capturing the main content
ob_start();
?>

<style>
    .ewaste-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #fff;
    }
    .badge-pending { background-color: #fef3c7; color: #d97706; font-weight: 700; }
    .badge-store { background-color: #e0f2fe; color: #0284c7; font-weight: 700; }
    .badge-scrapped { background-color: #fee2e2; color: #dc2626; font-weight: 700; }
    .badge-refurbished { background-color: #dcfce7; color: #15803d; font-weight: 700; }
    
    .table-ewaste th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background-color: #f8fafc;
        color: #64748b;
        padding: 14px;
    }
    .reason-text {
        max-width: 220px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: inline-block;
        font-size: 0.8rem;
        color: #64748b;
    }

    /* ===== E-WASTE HEADER - MATCH REFERENCE UI ===== */
    .ewaste-header {
        padding: 0 0 14px 0;
        margin-bottom: 22px;
        border-bottom: 1px solid #e3e8ed;
    }

    .ewaste-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .ewaste-header-icon {
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f4f7f9;
        border: 1px solid #dfe5ea;
        border-radius: 5px;
        color: #123b63;
        font-size: 18px;
        flex-shrink: 0;
    }

    .ewaste-header-title {
        color: #123b63;
        font-size: 23px;
        font-weight: 700;
        line-height: 1.2;
        margin: 0 0 3px 0;
    }

    .ewaste-header-subtitle {
        color: #737d87;
        font-size: 12px;
        line-height: 1.4;
        margin: 0;
    }
</style>

<div class="container-fluid px-2 px-md-4 py-4">
    <!-- HEADER -->
    <div class="ewaste-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="ewaste-header-left">
                <div class="ewaste-header-icon" style="color: #dc2626 !important;">
                    <i class="bi bi-trash3-fill text-danger"></i>
                </div>
                <div>
                    <h3 class="ewaste-header-title">
                        E-Waste Management Ledger
                    </h3>
                    <p class="ewaste-header-subtitle">
                        Decommissioned items pending structural sorting, lifecycle updates, or collection dispatches.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card ewaste-card shadow-sm">
        <div class="table-responsive">
            <table class="table table-ewaste align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 16%;">Logged Date</th>
                        <th style="width: 24%;">Asset Tag & Origin</th>
                        <th style="width: 22%;">Item Details</th>
                        <th style="width: 18%;">Disposal Reason</th>
                        <th style="<?= (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') ? 'width: 10%;' : 'width: 20%;'; ?>">Status</th>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin'): ?>
                            <th class="text-end pe-4" style="width: 10%;">Manage</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $status_class = 'badge-pending';
                            $display_status = str_replace('_', ' ', $row['ewaste_status']);
                            if ($row['ewaste_status'] === 'In_Ewaste_Store') $status_class = 'badge-store';
                            if ($row['ewaste_status'] === 'Scrapped') $status_class = 'badge-scrapped';
                            if ($row['ewaste_status'] === 'Refurbished') $status_class = 'badge-refurbished';
                        ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-semibold text-dark small d-block"><?= date('M d, Y', strtotime($row['logged_at'])) ?></span>
                                <small class="text-muted" style="font-size: 0.7rem;"><?= date('h:i A', strtotime($row['logged_at'])) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-primary border fw-bold px-2 py-1"><?= htmlspecialchars($row['division_asset_id']) ?></span>
                                <small class="d-block text-secondary mt-1" style="font-size: 0.7rem;">
                                    <i class="bi bi-building me-1"></i><?= htmlspecialchars($row['source_division']) ?> 
                                    <span class="text-muted">(<?= htmlspecialchars($row['source_unit']) ?>)</span>
                                </small>
                            </td>
                            <td>
                                <span class="fw-bold text-dark d-block" style="font-size: 0.85rem;"><?= htmlspecialchars($row['item_name']) ?></span>
                                <small class="text-muted"><?= htmlspecialchars($row['model_name'] ?: 'Standard Model') ?></small>
                            </td>
                            <td>
                                <div class="text-wrap text-break reason-text" style="max-width: 250px; font-size: 0.85rem;">
                                    <?= htmlspecialchars($row['disposal_reason']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $status_class ?> px-2.5 py-1.5" style="font-size: 0.68rem;">
                                    <?= $display_status ?>
                                </span>
                            </td>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin'): ?>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-dark fw-bold rounded-3 px-2.5 py-1" 
                                            onclick="openUpdateStatusModal(<?= $row['ewaste_id'] ?>, '<?= $row['ewaste_status'] ?>', '<?= addslashes($row['division_asset_id']) ?>')">
                                        <i class="bi bi-gear-fill me-1 text-secondary"></i> Process
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') ? 6 : 5; ?>" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-opacity-20"></i>
                                No items found in the recycling pipeline.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
// Save main content layout variable
$content = ob_get_clean(); 

// Start capturing the modal layout separately so layout.php can handle structural placement (Only for SuperAdmin)
ob_start();
if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin'):
?>
<div class="modal fade" id="updateEwasteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-arrow-left-right text-success me-2"></i>Pipeline Routing</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <div class="modal-body">
                    <p class="text-muted small">Update status track for asset ID: <span class="fw-bold text-primary" id="modal_asset_tag"></span></p>
                    <input type="hidden" name="update_ewaste_status" id="modal_ewaste_id">
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-secondary text-uppercase" style="font-size:0.65rem;">Pipeline Status Target</label>
                        <select name="new_status" id="modal_status_select" class="form-select border-2 fw-semibold text-dark">
                            <option value="Pending_Verification">Pending Verification</option>
                            <option value="In_Ewaste_Store">In E-Waste Store</option>
                            <option value="Scrapped">Scrapped (Raw Recycle)</option>
                            <option value="Refurbished">Refurbished / Reused</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn btn-success w-100 rounded-3 fw-bold">Update State</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php 
endif;
// Pass modal content to layout injection hook variable
$modal_html = ob_get_clean(); 

// Append Javascript to main content string variable safely
ob_start();
?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });

    function openUpdateStatusModal(id, currentStatus, assetTag) {
        document.getElementById('modal_ewaste_id').value = id;
        document.getElementById('modal_asset_tag').innerText = assetTag;
        document.getElementById('modal_status_select').value = currentStatus;
        
        // Use getOrCreateInstance to prevent stacking identical overlay backdrops
        var modalEl = document.getElementById('updateEwasteModal');
        var modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.show();
    }
</script>
<?php 
$content .= ob_get_clean();
include "ewastelayout.php"; 
?>