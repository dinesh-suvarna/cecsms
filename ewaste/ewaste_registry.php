<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

/**
 * 1. MANDATORY SECURITY LOCKDOWN
 * Only SuperAdmin accounts are authorized to read or manipulate the E-Waste Ledger.
 */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SuperAdmin') {
    $_SESSION['error_msg'] = "Access Denied: You do not have permissions to view the E-Waste registry.";
    header("Location: ../dashboard.php"); 
    exit;
}

$page_title = "E-Waste Management Panel";
$page_icon  = "bi-trash3-fill";

/* ================= HANDLE STATUS UPDATE ================= */
if (isset($_POST['update_ewaste_status'])) {
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
                // Keep master stock history accurate
                $stmt_stock = $conn->prepare("UPDATE stock_details SET status = 'disposed' WHERE id = ?");
                $stmt_stock->bind_param("i", $stock_id);
                $stmt_stock->execute();
            } elseif ($new_status === 'Refurbished') {
                // Item salvaged! Put it back in master inventory circulation pools
                $stmt_stock = $conn->prepare("UPDATE stock_details SET status = 'available' WHERE id = ?");
                $stmt_stock->bind_param("i", $stock_id);
                $stmt_stock->execute();
            }
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

/* ================= FETCH UNCOMMENTED & ADJUSTED DATA ================= */
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
        CONCAT_WS(' | ', mo.processor, mo.ram, CONCAT(mo.storage_size, ' ', mo.storage_type)) as full_config,
        NULL as partner_name 
    FROM ewaste_items ew
    JOIN stock_details sd ON sd.id = ew.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    LEFT JOIN item_models mo ON sd.model_id = mo.id
    ORDER BY ew.logged_at DESC
";
$result = $conn->query($query);

// Start capturing the main content
ob_start();
?>

<style>
    .ewaste-card {
        border-radius: 14px;
        border: 1px solid #eef2f6;
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
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-trash3 me-2 text-danger"></i>E-Waste Management Ledger</h4>
            <p class="text-muted small mb-0">Decommissioned items pending structural sorting, lifecycle updates, or collection dispatches.</p>
        </div>
    </div>

    <div class="card ewaste-card shadow-sm">
        <div class="table-responsive">
            <table class="table table-ewaste align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Logged Date</th>
                        <th>Asset Tag / ID</th>
                        <th>Item Details</th>
                        <th>Hardware Configuration</th>
                        <th>Disposal Reason</th>
                        <th>Pipeline Status</th>
                        <th class="text-end pe-4">Manage</th>
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
                                <span class="badge bg-light text-primary border fw-bold px-2 py-1.5"><?= htmlspecialchars($row['division_asset_id']) ?></span>
                                <small class="d-block text-muted mt-1" style="font-size: 0.72rem;">S/N: <b><?= htmlspecialchars($row['serial_number'] ?: 'N/A') ?></b></small>
                            </td>
                            <td>
                                <span class="fw-bold text-dark d-block" style="font-size: 0.85rem;"><?= htmlspecialchars($row['item_name']) ?></span>
                                <small class="text-muted"><?= htmlspecialchars($row['model_name'] ?: 'Standard Model') ?></small>
                            </td>
                            <td>
                                <small class="text-secondary fw-medium"><?= htmlspecialchars($row['full_config'] ?: 'No Hardware Spec Profile') ?></small>
                            </td>
                            <td>
                                <span class="reason-text" data-bs-toggle="tooltip" title="<?= htmlspecialchars($row['disposal_reason']) ?>">
                                    <?= htmlspecialchars($row['disposal_reason']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $status_class ?> px-3 py-2 small" style="font-size: 0.7rem;">
                                    <?= $display_status ?>
                                </span>
                                <?php if(!empty($row['partner_name'])): ?>
                                    <small class="d-block text-muted small mt-1" style="font-size:0.65rem;"><i class="bi bi-truck me-1"></i><?= htmlspecialchars($row['partner_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-dark fw-bold rounded-3 px-3" 
                                        onclick="openUpdateStatusModal(<?= $row['ewaste_id'] ?>, '<?= $row['ewaste_status'] ?>', '<?= addslashes($row['division_asset_id']) ?>')">
                                    <i class="bi bi-gear-fill me-1 text-secondary"></i> Process
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
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

// Start capturing the modal layout separately so layout.php can handle structural placement
ob_start();
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