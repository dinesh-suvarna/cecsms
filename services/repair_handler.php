<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/crypto.php";
include "../admin/auth.php";
include "../includes/session.php";

$encrypted_token = $_GET['token'] ?? '';
$decrypted_id = decrypt_id($encrypted_token);

$division_asset_id = ($decrypted_id !== false) ? intval($decrypted_id) : 0;

if (($_SESSION['role'] ?? '') !== 'SuperAdmin') {
    $_SESSION['error_msg'] = "Access Denied.";
    header("Location: returned_assets.php");
    exit;
}

// Fetch asset info securely using the COALESCE remarks logic
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
        un.unit_code,
        un.unit_name,
        COALESCE(
            NULLIF((SELECT al.notes FROM asset_logs al WHERE al.asset_id = sd.id AND al.action_type IN ('service_requested', 'repair_requested') ORDER BY al.id DESC LIMIT 1), ''),
            NULLIF(dm.remarks, ''),
            'No remarks provided'
        ) AS original_notes
    FROM division_assets da
    JOIN stock_details sd ON da.stock_detail_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
    LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
    LEFT JOIN divisions d ON dm.division_id = d.id
    LEFT JOIN units un ON dm.unit_id = un.id
    WHERE da.id = ? AND da.status IN ('assigned', 'under_repair', 'returned', 'pending_repair')
");
$stmt->bind_param("i", $division_asset_id);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();

if (!$asset) {
    $page_title = "Repair Error";
    ob_start();
    ?>
    <style>
        :root {
            --erp-navy: #173f63; --erp-navy-dark: #102f4a; --erp-text: #263746;
            --erp-muted: #71808f; --erp-border: #dce3e9; --erp-bg: #f5f7f9; --erp-shadow: 0 1px 3px rgba(20, 40, 60, .06);
        }
        .service-form-page { max-width: 1200px; margin: 0 auto; padding: 10px 20px 40px; }
        .inst-form-panel { background: #f9fafb; border: 1px solid var(--erp-border); border-radius: 5px; box-shadow: var(--erp-shadow); }
        .btn-erp-cancel { height: 38px; border: 1px solid #c8d2db; background: #fff; color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        .btn-erp-cancel:hover { background: #f5f7f9; color: #334451; }
    </style>
    <div class="service-form-page">
        <div class="inst-form-panel p-4">
            <div class="d-flex align-items-start gap-3">
                <div class="bg-warning-subtle rounded-1 p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; flex-shrink: 0;">
                    <i class="bi bi-exclamation-triangle text-warning fs-5"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 1.1rem;">Asset Not Found</h4>
                    <p class="text-muted small mb-3">The requested asset could not be found, or it has already been processed and moved out of the pending repair queue.</p>
                    <a href="returned_assets.php" class="btn btn-erp-cancel px-3">
                        <i class="bi bi-arrow-left me-1"></i>Back to Returned Assets
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    include "layout.php";
    exit;
}

// Fetch Computer Category Vendors for Dropdown
$vendors_result = $conn->query("SELECT id, vendor_name FROM vendors WHERE category = 'Computer' ORDER BY vendor_name ASC");
$vendors = $vendors_result->fetch_all(MYSQLI_ASSOC);

// --- FORM SUBMISSION HANDLER ---
$repair_type  = $_POST['repair_type'] ?? 'internal';
$vendor_id    = intval($_POST['vendor_id'] ?? 0);
$repair_cost  = $_POST['repair_cost'] ?? '0.00';
$issue_desc   = $_POST['issue_description'] ?? ''; // Set to blank by default for manual entry
$error_msg    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repair_type     = $_POST['repair_type'];
    $vendor_id       = intval($_POST['vendor_id'] ?? 0);
    $repair_cost     = floatval($_POST['repair_cost'] ?? 0);
    $issue_desc      = trim($_POST['issue_description'] ?? '');
    $admin_id        = $_SESSION['user_id'];
    
    // --- NEW VALIDATION CHECK ---
    if (empty($issue_desc)) {
        $error_msg = "Diagnosis / Issue Description is required and cannot be blank.";
    } elseif (in_array($repair_type, ['external_warranty', 'external_paid']) && $vendor_id <= 0) {
        $error_msg = "Please select a valid vendor for external repairs.";
    } else {
        // Safe to proceed with transaction
        $vendor_name = null;
        if (in_array($repair_type, ['external_warranty', 'external_paid']) && $vendor_id > 0) {
            $v_stmt = $conn->prepare("SELECT vendor_name FROM vendors WHERE id = ?");
            $v_stmt->bind_param("i", $vendor_id);
            $v_stmt->execute();
            $v_res = $v_stmt->get_result()->fetch_assoc();
            $vendor_name = $v_res['vendor_name'] ?? '';
        }

        $conn->begin_transaction();

        try {
            $lock_stmt = $conn->prepare("SELECT status FROM division_assets WHERE id = ? FOR UPDATE");
            $lock_stmt->bind_param("i", $division_asset_id);
            $lock_stmt->execute();
            $current_status = $lock_stmt->get_result()->fetch_assoc()['status'] ?? '';

            if (!in_array($current_status, ['assigned', 'under_repair'])) {
                throw new Exception("This asset has already been processed.");
            }

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

            $update_da = $conn->prepare("UPDATE division_assets SET status = 'in_repair' WHERE id = ?");
            $update_da->bind_param("i", $division_asset_id);
            $update_da->execute();

            $update_sd = $conn->prepare("UPDATE stock_details SET status = 'in_repair' WHERE id = ?");
            $update_sd->bind_param("i", $asset['stock_detail_id']);
            $update_sd->execute();

            $conn->commit();

            $_SESSION['success_msg'] = "Repair ticket successfully logged and assigned.";
            header("Location: repair_queue.php"); 
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Error processing repair: " . $e->getMessage();
        }
    }
}

$page_title = "Repair Handling & Vendor Assignment";
ob_start();
?>

<style>
:root {
    --erp-navy: #173f63;
    --erp-navy-dark: #102f4a;
    --erp-text: #263746;
    --erp-muted: #71808f;
    --erp-border: #dce3e9;
    --erp-bg: #f5f7f9;
    --erp-shadow: 0 1px 3px rgba(20, 40, 60, .06);
}

.service-form-page { max-width: 1200px; margin: 0 auto; padding: 8px 20px 40px; }

.inst-header {
    display: flex; justify-content: space-between; align-items: center;
    gap: 20px; padding-bottom: 16px; margin-bottom: 18px; border-bottom: 1px solid var(--erp-border);
}
.inst-header-left { display: flex; align-items: center; gap: 14px; }
.inst-header-icon {
    width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;
    background: #edf3f8; border: 1px solid #dce6ee; border-radius: 5px; color: var(--erp-navy); font-size: 1.1rem;
}
.inst-header h3 { margin: 0; color: var(--erp-navy-dark); font-size: 1.18rem; font-weight: 650; }
.inst-header p { margin: 3px 0 0; color: var(--erp-muted); font-size: .76rem; }

.inst-form-panel { background: #f9fafb; border: 1px solid var(--erp-border); border-radius: 5px; box-shadow: var(--erp-shadow); }
.inst-form-header { display: flex; align-items: center; padding: 12px 18px; border-bottom: 1px solid var(--erp-border); background: #f5f7f9; }
.inst-form-title { display: flex; align-items: center; gap: 8px; color: var(--erp-navy-dark); font-size: .82rem; font-weight: 650; }
.inst-form-body { padding: 20px; }

.inst-form-panel .form-label { 
    color: #536575; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .045em; margin-bottom: 6px; 
}
.inst-form-panel .form-control, .inst-form-panel .form-select {
    height: 38px; border: 1px solid var(--erp-border); border-radius: 4px !important; color: var(--erp-text); background: #fff; font-size: .8rem; box-shadow: none !important;
}
.inst-form-panel .input-group-text {
    height: 38px; border: 1px solid var(--erp-border); background: #f5f7f9; color: var(--erp-navy); font-size: .8rem; border-radius: 4px 0 0 4px !important;
}

.btn-erp-primary {
    height: 38px; background: var(--erp-navy); border: 1px solid var(--erp-navy); color: #fff; border-radius: 4px !important; font-size: .76rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;
}
.btn-erp-primary:hover { background: var(--erp-navy-dark); border-color: var(--erp-navy-dark); color: #fff; }

.btn-erp-cancel {
    height: 38px; border: 1px solid #c8d2db; background: #fff; color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-erp-cancel:hover { background: #f5f7f9; color: #334451; }

[data-bs-theme="dark"] {
    --erp-bg: #101a24; --erp-text: #edf3f7; --erp-muted: #9aabb9; --erp-border: #2d3e4e; --erp-navy: #8eafc9; --erp-navy-dark: #dce8f0;
}
[data-bs-theme="dark"] .inst-header h3 { color: #edf3f7; }
[data-bs-theme="dark"] .inst-header-icon { background: #203445; border-color: #33495a; color: #b8d0e2; }
[data-bs-theme="dark"] .inst-form-panel, [data-bs-theme="dark"] .inst-form-header { background: #142230 !important; }
[data-bs-theme="dark"] .inst-form-panel .form-control, [data-bs-theme="dark"] .inst-form-panel .form-select { background: #172534 !important; color: var(--erp-text); border-color: var(--erp-border); }
[data-bs-theme="dark"] .btn-erp-cancel { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
</style>

<div class="service-form-page">
    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi bi-tools"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($page_title) ?></h3>
                <p>Configure repair routing, vendor assignment, and warranty tracking for this asset.</p>
            </div>
        </div>
        <a href="returned_assets.php" class="btn btn-erp-cancel px-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Returned Assets
        </a>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger border-0 rounded-1 shadow-sm mb-4" style="font-size: .8rem;">
            <i class="bi bi-exclamation-octagon-fill me-2"></i><?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- FORM PANEL -->
    <div class="inst-form-panel">
        <div class="inst-form-header">
            <div class="inst-form-title">
                <i class="bi bi-gear-fill text-primary me-1"></i> Asset Maintenance Routing
            </div>
        </div>
        <div class="inst-form-body">
            <!-- Asset Summary Box -->
            <div class="p-3 bg-white rounded-1 mb-4 border">
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="text-muted extra-small text-uppercase fw-bold" style="font-size:.65rem;">Asset Tag / Item</span>
                        <div class="fw-bold text-dark" style="font-size:.85rem;"><?= htmlspecialchars($asset['item_name']) ?> — <?= htmlspecialchars($asset['asset_tag']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted extra-small text-uppercase fw-bold" style="font-size:.65rem;">Serial Number</span>
                        <div class="fw-semibold text-secondary" style="font-size:.85rem;"><?= htmlspecialchars($asset['serial_number'] ?: 'N/A') ?></div>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted extra-small text-uppercase fw-bold" style="font-size:.65rem;">Originating Location</span>
                        <div class="fw-medium text-dark" style="font-size:.85rem;">
                            <?= htmlspecialchars($asset['division_name'] ?? 'Main Division') ?> 
                            <i class="bi bi-chevron-right small mx-1"></i> 
                            <?php 
                                $unit_display = 'General Unit';
                                if (!empty($asset['unit_name'])) {
                                    $unit_display = !empty($asset['unit_code']) 
                                        ? $asset['unit_code'] . ' - ' . $asset['unit_name'] 
                                        : $asset['unit_name'];
                                }
                                echo htmlspecialchars($unit_display);
                            ?>
                        </div>
                    </div>
                    <!-- Division Remarks Box -->
                    <div class="col-md-6">
                        <span class="text-muted extra-small text-uppercase fw-bold" style="font-size:.65rem;">Department Remarks / Queue Notes</span>
                        <div class="p-2 bg-light rounded-1 border text-secondary" style="font-size:.8rem; min-height: 36px;">
                            <?php if ($asset['original_notes'] === 'No remarks provided'): ?>
                                <span class="text-muted fst-italic">No remarks provided by department.</span>
                            <?php else: ?>
                                <?= htmlspecialchars($asset['original_notes']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Repair Form -->
            <form method="POST" action="<?= e_url('repair_handler.php', $division_asset_id, 'token') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Repair Handling Type</label>
                        <select name="repair_type" id="repairType" class="form-select" required onchange="toggleVendorField()">
                            <option value="internal" <?= $repair_type === 'internal' ? 'selected' : '' ?>>Internal Maintenance Team</option>
                            <option value="external_warranty" <?= $repair_type === 'external_warranty' ? 'selected' : '' ?>>External Vendor (Under Warranty)</option>
                            <option value="external_paid" <?= $repair_type === 'external_paid' ? 'selected' : '' ?>>External Vendor (Paid Service)</option>
                        </select>
                    </div>

                    <!-- Vendor Selection & Redirect to Vendor Manager -->
                    <div class="col-md-6" id="vendorFieldWrapper" style="display: <?= in_array($repair_type, ['external_warranty', 'external_paid']) ? 'block' : 'none' ?>;">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Vendor / Service Center</span>
                            <a href="../vendors/vendor_manager.php" target="_blank" class="text-decoration-none" style="font-size: .65rem;">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Add New Vendor
                            </a>
                        </label>
                        <select name="vendor_id" id="vendorSelect" class="form-select">
                            <option value="">-- Select Vendor --</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= $vendor_id == $v['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['vendor_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6" id="costFieldWrapper" style="display: <?= $repair_type === 'external_paid' ? 'block' : 'none' ?>;">
                        <label class="form-label">Estimated Cost (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0">₹</span>
                            <input type="number" step="0.01" name="repair_cost" class="form-control border-start-0" value="<?= htmlspecialchars($repair_cost) ?>">
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Diagnosis / Issue Description <span class="text-danger">*</span></label>
                        <textarea name="issue_description" class="form-control" rows="3" style="height: auto;" placeholder="Describe the fault or maintenance requirements..." required><?= htmlspecialchars($issue_desc) ?></textarea>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="returned_assets.php" class="btn btn-erp-cancel px-3">
                                <i class="bi bi-arrow-left me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-erp-primary px-4">
                                <i class="bi bi-check-lg me-1"></i> Save & Dispatch to Repair
                            </button>
                        </div>
                    </div>
                </div>
            </form>
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
        document.getElementById('vendorSelect').value = '';
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