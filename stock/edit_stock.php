<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/crypto.php"; 
session_start();

if(!isset($_GET['id'])){
    header("Location: view_stock_details.php");
    exit;
}

// Decrypt incoming ID safely
$enc_id = $_GET['id'] ?? '';
$id = decrypt_id($enc_id);

if(!$id || !is_numeric($id)){
    header("Location: view_stock_details.php");
    exit;
}

$successMsg = "";
$errorMsg = "";

/* Fetch stock details with item type */
$stmt = $conn->prepare("
    SELECT sd.*, im.stock_type, im.item_name
    FROM stock_details sd
    LEFT JOIN items_master im ON sd.stock_item_id = im.id
    WHERE sd.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if(!$data){
    header("Location: view_stock_details.php");
    exit;
}

/* Fetch Vendors */
$vendors = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");

/* Update Logic */
if(isset($_POST['update'])){

    $user_id  = $_SESSION['user_id'] ?? 0;

    $serial = trim($_POST['serial_number'] ?? '');
    if($data['stock_type'] === 'non_serial'){
        $serial = NULL;
    }
    $bill_no  = trim($_POST['bill_no']);
    $bill_date = $_POST['bill_date'] ?: NULL;
    $po       = trim($_POST['po_number']);
    $amount   = !empty($_POST['amount']) ? (float)$_POST['amount'] : NULL;
    $warranty = $_POST['warranty_upto'] ?: NULL;
    $vendor   = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : NULL;

    /* Duplicate serial check */
    if($data['stock_type'] === 'serial' && !empty($serial)){
        $check = $conn->prepare("
            SELECT id FROM stock_details 
            WHERE stock_item_id = ? 
            AND serial_number = ? 
            AND id != ?
        ");
        $check->bind_param("isi", $data['stock_item_id'], $serial, $id);
        $check->execute();
        $dup = $check->get_result();
        if($dup->num_rows > 0){
            $errorMsg = "Serial number already exists for this item.";
        }
        $check->close();
    }

    if(empty($errorMsg)) {
        /* Begin Transaction */
        $conn->begin_transaction();

        try {
            /* Store old values for log */
            $old_serial = $data['serial_number'];
            $old_vendor = $data['vendor_id'];

            /* Update stock */
            $update = $conn->prepare("
                UPDATE stock_details SET
                    serial_number = ?,
                    bill_no = ?,
                    bill_date = ?,
                    po_number = ?,
                    vendor_id = ?,
                    amount = ?,
                    warranty_upto = ?
                WHERE id = ?
            ");

            $update->bind_param(
                "ssssidsi",
                $serial,
                $bill_no,
                $bill_date,
                $po,
                $vendor,
                $amount,
                $warranty,
                $id
            );

            $update->execute();
            $update->close();

            /* Insert Audit Log */
            $log = $conn->prepare("
                INSERT INTO stock_edit_logs
                (stock_detail_id, edited_by, old_serial, new_serial, old_vendor, new_vendor)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $log->bind_param(
                "iissii",
                $id,
                $user_id,
                $old_serial,
                $serial,
                $old_vendor,
                $vendor
            );

            $log->execute();
            $log->close();

            $conn->commit();

            /* Refresh updated data */
            $data['serial_number'] = $serial;
            $data['bill_no'] = $bill_no;
            $data['bill_date'] = $bill_date;
            $data['po_number'] = $po;
            $data['vendor_id'] = $vendor;
            $data['amount'] = $amount;
            $data['warranty_upto'] = $warranty;

            $successMsg = "Stock entry updated successfully.";

        } catch(Exception $e){
            $conn->rollback();
            $errorMsg = "Something went wrong. Please try again.";
        }
    }
}

ob_start();
?>

<style>
/* =========================================================
   ENTERPRISE ERP STYLES (MATCHING EDIT DIVISION UI)
========================================================= */
:root {
    --erp-navy: #173f63;
    --erp-navy-dark: #102f4a;
    --erp-blue: #0d6efd;
    --erp-text: #263746;
    --erp-muted: #71808f;
    --erp-border: #dce3e9;
    --erp-bg: #f5f7f9;
    --erp-white: #ffffff;
    --erp-shadow: 0 1px 3px rgba(20, 40, 60, .06);
}

.edit-stock-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 26px 30px 40px;
}

/* HEADER */
.inst-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding-bottom: 20px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--erp-border);
}
.inst-header-left { display: flex; align-items: center; gap: 14px; }
.inst-header-icon {
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    background: #edf3f8; border: 1px solid #dce6ee; border-radius: 5px;
    color: var(--erp-navy); font-size: 1.1rem;
}
.inst-header h3 { margin: 0; color: var(--erp-navy-dark); font-size: 1.18rem; font-weight: 650; }
.inst-header p { margin: 3px 0 0; color: var(--erp-muted); font-size: .76rem; }

/* FORM PANEL */
.inst-form-panel {
    background: #f9fafb; border: 1px solid var(--erp-border); border-radius: 5px;
    margin-bottom: 18px; box-shadow: var(--erp-shadow);
}
.inst-form-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 13px 18px; border-bottom: 1px solid var(--erp-border); background: #f5f7f9;
}
.inst-form-title { display: flex; align-items: center; gap: 8px; color: var(--erp-navy-dark); font-size: .82rem; font-weight: 650; }
.inst-form-body { padding: 22px; }

.inst-form-panel .form-label { 
    color: #536575; font-size: .65rem; font-weight: 700; 
    text-transform: uppercase; letter-spacing: .045em; margin-bottom: 6px; 
}

.inst-form-panel .form-control, 
.inst-form-panel .form-select {
    height: 39px; border: 1px solid var(--erp-border); border-radius: 4px !important;
    color: var(--erp-text); background: #fff; font-size: .8rem;
    box-shadow: none !important;
}

.inst-form-panel .form-control[readonly] {
    background: #eef2f5 !important;
    color: #617382;
}

/* BUTTONS */
.btn-form-save {
    height: 39px; background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
}
.btn-form-save:hover { background: var(--erp-navy-dark); border-color: var(--erp-navy-dark); color: #fff; }

.btn-form-cancel {
    height: 39px; border: 1px solid #c8d2db; background: #fff;
    color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-form-cancel:hover { background: #f5f7f9; color: #334451; }

.inst-alert { border-radius: 4px !important; font-size: .76rem; }

/* DARK MODE SUPPORT */
[data-bs-theme="dark"] {
    --erp-bg: #101a24; --erp-white: #172534; --erp-text: #edf3f7; --erp-muted: #9aabb9;
    --erp-border: #2d3e4e; --erp-navy: #8eafc9; --erp-navy-dark: #dce8f0;
}
[data-bs-theme="dark"] .inst-header h3 { color: #edf3f7; }
[data-bs-theme="dark"] .inst-header-icon { background: #203445; border-color: #33495a; color: #b8d0e2; }
[data-bs-theme="dark"] .inst-form-panel, [data-bs-theme="dark"] .inst-form-header { background: #142230; }
[data-bs-theme="dark"] .inst-form-panel .form-control, 
[data-bs-theme="dark"] .inst-form-panel .form-select { 
    background: #172534 !important; color: var(--erp-text); border-color: var(--erp-border); 
}
[data-bs-theme="dark"] .inst-form-panel .form-control[readonly] { background: #0f1923 !important; color: #7f93a4; }
[data-bs-theme="dark"] .btn-form-cancel { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
</style>

<div class="edit-stock-page">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi bi-pencil-square"></i>
            </div>
            <div>
                <h3>Edit Stock Entry</h3>
                <p>Modify inventory details, procurement data, and warranty tracking.</p>
            </div>
        </div>
        <a href="view_stock_details.php" class="btn btn-form-cancel px-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Stock Registry
        </a>
    </div>

    <!-- ALERTS -->
    <?php if ($successMsg): ?>
        <div class="alert alert-success inst-alert alert-dismissible fade show mb-3">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($successMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger inst-alert alert-dismissible fade show mb-3">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- EDIT FORM PANEL -->
    <div class="inst-form-panel">
        <div class="inst-form-header">
            <div class="inst-form-title">
                <i class="bi bi-box-seam-fill text-primary"></i> Edit Item Details
            </div>
        </div>

        <div class="inst-form-body">
            <form method="POST">

                <!-- Item Name (Readonly) -->
                <div class="mb-3">
                    <label class="form-label">Item Catalog Name</label>
                    <input type="text"
                           class="form-control"
                           value="<?= htmlspecialchars($data['item_name'] ?? 'N/A') ?>"
                           readonly>
                </div>

                <!-- Serial Number (If Serialized Item) -->
                <?php if($data['stock_type'] === 'serial'): ?>
                <div class="mb-3">
                    <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                    <input type="text"
                           name="serial_number"
                           class="form-control"
                           value="<?= htmlspecialchars($data['serial_number'] ?? '') ?>"
                           required>
                </div>
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <!-- Bill Number -->
                    <div class="col-md-6">
                        <label class="form-label">Bill / Invoice No</label>
                        <input type="text"
                               name="bill_no"
                               class="form-control"
                               value="<?= htmlspecialchars($data['bill_no'] ?? '') ?>">
                    </div>

                    <!-- Bill Date -->
                    <div class="col-md-6">
                        <label class="form-label">Bill Date</label>
                        <input type="date"
                               name="bill_date"
                               class="form-control"
                               value="<?= htmlspecialchars($data['bill_date'] ?? '') ?>">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <!-- PO Number -->
                    <div class="col-md-6">
                        <label class="form-label">PO Number</label>
                        <input type="text"
                               name="po_number"
                               class="form-control"
                               value="<?= htmlspecialchars($data['po_number'] ?? '') ?>">
                    </div>

                    <!-- Vendor -->
                    <div class="col-md-6">
                        <label class="form-label">Vendor / Supplier</label>
                        <select name="vendor_id" class="form-select">
                            <option value="">Select Vendor</option>
                            <?php 
                            if ($vendors) {
                                mysqli_data_seek($vendors, 0);
                                while($v = $vendors->fetch_assoc()): 
                            ?>
                                <option value="<?= $v['id'] ?>" <?= ($v['id'] == $data['vendor_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['vendor_name']) ?>
                                </option>
                            <?php 
                                endwhile; 
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <!-- Amount -->
                    <div class="col-md-6">
                        <label class="form-label">Unit Cost (₹)</label>
                        <input type="number"
                               step="0.01"
                               name="amount"
                               class="form-control"
                               value="<?= htmlspecialchars($data['amount'] ?? '') ?>">
                    </div>

                    <!-- Warranty Upto -->
                    <div class="col-md-6">
                        <label class="form-label">Warranty Expiry Date</label>
                        <input type="date"
                               name="warranty_upto"
                               class="form-control"
                               value="<?= htmlspecialchars($data['warranty_upto'] ?? '') ?>">
                    </div>
                </div>

                <!-- Actions -->
                <div class="d-flex justify-content-end gap-2">
                    <a href="view_stock_details.php" class="btn btn-form-cancel px-4">
                        Cancel
                    </a>
                    <button type="submit" 
                            name="update" 
                            class="btn btn-form-save px-4">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>