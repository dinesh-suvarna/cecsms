<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

if (($_SESSION['role'] ?? '') !== 'SuperAdmin') {
    $_SESSION['error_msg'] = "Access Denied.";
    header("Location: returned_assets.php");
    exit;
}

$division_asset_id = intval($_GET['asset_id'] ?? 0);

// Fetch asset info safely
$stmt = $conn->prepare("
    SELECT 
        da.id as division_asset_id_pk,
        da.stock_detail_id,
        da.division_asset_id AS asset_tag,
        im.item_name,
        sd.serial_number,
        dm.division_id,
        d.division_name,
        dm.unit_id,
        un.unit_name,
        dm.remarks AS original_notes
    FROM division_assets da
    JOIN stock_details sd ON da.stock_detail_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
    LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
    LEFT JOIN divisions d ON dm.division_id = d.id
    LEFT JOIN units un ON dm.unit_id = un.id
    WHERE da.id = ? AND da.status IN ('assigned', 'under_repair')
");
$stmt->bind_param("i", $division_asset_id);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();

if (!$asset) {
    die("Asset not found or already processed.");
}

$repair_type  = $_POST['repair_type'] ?? 'internal';
$vendor_name  = $_POST['vendor_name'] ?? '';
$repair_cost  = $_POST['repair_cost'] ?? '0.00';
$issue_desc   = $_POST['issue_description'] ?? ($asset['original_notes'] ?? '');
$error_msg    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repair_type     = $_POST['repair_type'];
    $vendor_name     = trim($_POST['vendor_name'] ?? '');
    $repair_cost     = floatval($_POST['repair_cost'] ?? 0);
    $issue_desc      = trim($_POST['issue_description'] ?? '');
    $admin_id        = $_SESSION['user_id'];
    
    $stock_detail_id = $asset['stock_detail_id'];
    $asset_tag       = $asset['asset_tag'];
    $unit_name       = $asset['unit_name'] ?? 'General Unit';

    $conn->begin_transaction();

    try {
        // Lock the row to prevent race conditions and duplicate form submissions
        $lock_stmt = $conn->prepare("SELECT status FROM division_assets WHERE id = ? FOR UPDATE");
        $lock_stmt->bind_param("i", $division_asset_id);
        $lock_stmt->execute();
        $current_status = $lock_stmt->get_result()->fetch_assoc()['status'] ?? '';

        if (!in_array($current_status, ['assigned', 'under_repair'])) {
            throw new Exception("This asset has already been processed.");
        }

        // 1. Insert repair record
        $insert_repair = $conn->prepare("
            INSERT INTO repairs (stock_detail_id, division_asset_id, origin_division_id, origin_unit_id, repair_type, vendor_name, issue_description, repair_cost, status, performed_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in_progress', ?, NOW())
        ");
        $insert_repair->bind_param(
            "isiisssdi", 
            $asset['stock_detail_id'], 
            $asset['asset_tag'], 
            $asset['division_id'], 
            $asset['unit_id'], 
            $repair_type, 
            $vendor_name, 
            $issue_desc, 
            $repair_cost, 
            $admin_id
        );
        $insert_repair->execute();

        // 2. Update statuses
        $update_da = $conn->prepare("UPDATE division_assets SET status = 'in_repair' WHERE id = ?");
        $update_da->bind_param("i", $division_asset_id);
        $update_da->execute();

        $update_sd = $conn->prepare("UPDATE stock_details SET status = 'in_repair' WHERE id = ?");
        $update_sd->bind_param("i", $asset['stock_detail_id']);
        $update_sd->execute();

        // 3. Extract the original Transaction ID / Reference (e.g., TRX-00057 or [REF:#26]) from the first log entry
        $trx_stmt = $conn->prepare("SELECT notes FROM asset_logs WHERE asset_id = ? AND asset_tag = ? ORDER BY created_at ASC LIMIT 1");
        $trx_stmt->bind_param("is", $stock_detail_id, $asset_tag);
        $trx_stmt->execute();
        $trx_res = $trx_stmt->get_result()->fetch_assoc();
        
        $existing_ref = "";
        if ($trx_res && preg_match('/(TRX-\d+|\[.*?#\d+\])/', $trx_res['notes'], $matches)) {
            $existing_ref = $matches[1] . " ";
        }

        // 4. Construct log notes retaining the original transaction identifier
        $log_notes = $existing_ref . "Repair authorized by Admin. Asset redirected to repair module.";

        // 5. Insert single clean audit log
        $log_stmt = $conn->prepare("
            INSERT INTO asset_logs (asset_id, asset_tag, action_type, performed_by, notes, unit_name) 
            VALUES (?, ?, 'repair_requested', ?, ?, ?)
        ");
        $log_stmt->bind_param("isiss", $stock_detail_id, $asset_tag, $admin_id, $log_notes, $unit_name);
        $log_stmt->execute();

        $conn->commit();

        $_SESSION['success_msg'] = "Repair ticket successfully logged and assigned.";
        header("Location: repair_view.php"); 
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error processing repair: " . $e->getMessage();
    }
}

$page_title = "Repair Handling & Vendor Assignment";
$page_icon  = "bi-tools";
ob_start();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold text-dark mb-1">Repair Intake & Routing</h4>
                    <p class="text-muted small mb-4">Configure repair routing, vendor assignment, and warranty tracking for this asset.</p>

                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
                    <?php endif; ?>

                    <!-- Asset Summary Box -->
                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <span class="text-muted extra-small text-uppercase fw-bold">Asset Tag / Item</span>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($asset['asset_tag']) ?> — <?= htmlspecialchars($asset['item_name']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted extra-small text-uppercase fw-bold">Serial Number</span>
                                <div class="fw-semibold text-secondary"><?= htmlspecialchars($asset['serial_number'] ?: 'N/A') ?></div>
                            </div>
                            <div class="col-sm-12 mt-2">
                                <span class="text-muted extra-small text-uppercase fw-bold">Originating Location</span>
                                <div class="fw-medium text-dark">
                                    <?= htmlspecialchars($asset['division_name'] ?? 'Main Division') ?> 
                                    <i class="bi bi-chevron-right small mx-1"></i> 
                                    <?= htmlspecialchars($asset['unit_name'] ?? 'General Unit') ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Repair Form -->
                    <form method="POST" action="repair_handler.php?asset_id=<?= $division_asset_id ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Repair Handling Type</label>
                            <select name="repair_type" id="repairType" class="form-select" required onchange="toggleVendorField()">
                                <option value="internal" <?= $repair_type === 'internal' ? 'selected' : '' ?>>Internal Maintenance Team</option>
                                <option value="external_warranty" <?= $repair_type === 'external_warranty' ? 'selected' : '' ?>>External Vendor (Under Warranty)</option>
                                <option value="external_paid" <?= $repair_type === 'external_paid' ? 'selected' : '' ?>>External Vendor (Paid Service)</option>
                            </select>
                        </div>

                        <div class="mb-3" id="vendorFieldWrapper" style="display: <?= in_array($repair_type, ['external_warranty', 'external_paid']) ? 'block' : 'none' ?>;">
                            <label class="form-label fw-bold small">Vendor / Service Center Name</label>
                            <input type="text" name="vendor_name" class="form-control" value="<?= htmlspecialchars($vendor_name) ?>" placeholder="e.g., Dell Authorized Service Center">
                        </div>

                        <div class="mb-3" id="costFieldWrapper" style="display: <?= $repair_type === 'external_paid' ? 'block' : 'none' ?>;">
                            <label class="form-label fw-bold small">Estimated Cost ($)</label>
                            <input type="number" step="0.01" name="repair_cost" class="form-control" value="<?= htmlspecialchars($repair_cost) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Diagnosis / Issue Description</label>
                            <textarea name="issue_description" class="form-control" rows="3" placeholder="Describe the fault or maintenance requirements..."><?= htmlspecialchars($issue_desc) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="returned_assets.php" class="btn btn-light border px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary fw-bold px-4">Save & Dispatch to Repair</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleVendorField() {
    const type = document.getElementById('repairType').value;
    const vendorWrapper = document.getElementById('vendorFieldWrapper');
    const costWrapper = document.getElementById('costFieldWrapper');

    if (type === 'external_warranty' || type === 'external_paid') {
        vendorWrapper.style.display = 'block';
    } else {
        vendorWrapper.style.display = 'none';
        document.querySelector('input[name="vendor_name"]').value = '';
    }

    if (type === 'external_paid') {
        costWrapper.style.display = 'block';
    } else {
        costWrapper.style.display = 'none';
        document.querySelector('input[name="repair_cost"]').value = '0.00';
    }
}
</script>

<?php
$content = ob_get_clean();
include "layout.php";
?>