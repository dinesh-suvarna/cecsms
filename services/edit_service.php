<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";

$page_title = "Edit Service Entry";
$page_icon  = "bi-pencil-square";

$id = intval($_GET['id'] ?? 0); 

// Fetch service data
$stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Record not found.");
}

// Fetch all vendors for dropdown
$vendors = [];
$v_result = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");
while($row = $v_result->fetch_assoc()){
    $vendors[] = $row;
}

$update_msg = "";

// Update Logic
if(isset($_POST['update'])){
    $stmt = $conn->prepare("UPDATE services SET 
        service_date=?, bill_number=?, item_name=?, 
        service_type=?, vendor_id=?, amount=? 
        WHERE id=?");

    $stmt->bind_param("ssssidi",
        $_POST['service_date'], $_POST['bill_number'], $_POST['item_name'],
        $_POST['service_type'], $_POST['vendor_id'], $_POST['amount'], $id
    );

    if($stmt->execute()){
        $update_msg = '<div class="alert alert-success border-0 shadow-sm rounded-1 mb-4 d-flex align-items-center gap-2" style="font-size: .8rem;">
                        <i class="bi bi-check-circle-fill fs-6"></i> Record updated successfully!
                      </div>';
        // Refresh local data to show updated values in form
        $stmt_refresh = $conn->prepare("SELECT * FROM services WHERE id = ?");
        $stmt_refresh->bind_param("i", $id);
        $stmt_refresh->execute();
        $data = $stmt_refresh->get_result()->fetch_assoc();
    }
}

ob_start();
?>

<style>
/* Enterprise UI Theme Tokens */
:root {
    --erp-navy: #173f63;
    --erp-navy-dark: #102f4a;
    --erp-text: #263746;
    --erp-muted: #71808f;
    --erp-border: #dce3e9;
    --erp-bg: #f5f7f9;
    --erp-white: #ffffff;
    --erp-shadow: 0 1px 3px rgba(20, 40, 60, .06);
    --erp-accent-green: #10b981;
}

/* Page Layout Container */
.service-form-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 20px 40px;
}

/* ERP Header Styling */
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

/* Panel Styling */
.inst-form-panel {
    background: #f9fafb; border: 1px solid var(--erp-border); border-radius: 5px;
    margin-bottom: 22px; box-shadow: var(--erp-shadow);
}
.inst-form-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 18px; border-bottom: 1px solid var(--erp-border); background: #f5f7f9;
}
.inst-form-title { display: flex; align-items: center; gap: 8px; color: var(--erp-navy-dark); font-size: .82rem; font-weight: 650; }
.inst-form-body { padding: 20px; }

/* Form Controls */
.inst-form-panel .form-label { 
    color: #536575; font-size: .65rem; font-weight: 700; 
    text-transform: uppercase; letter-spacing: .045em; margin-bottom: 6px; 
}

.inst-form-panel .form-control,
.inst-form-panel .form-select {
    height: 38px; border: 1px solid var(--erp-border); border-radius: 4px !important;
    color: var(--erp-text); background: #fff; font-size: .8rem;
    box-shadow: none !important;
}

.inst-form-panel .input-group-text {
    height: 38px; border: 1px solid var(--erp-border); background: #f5f7f9;
    color: var(--erp-navy); font-size: .8rem; border-radius: 4px 0 0 4px !important;
}

/* Standard Buttons */
.btn-erp-primary {
    height: 38px; background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center;
}
.btn-erp-primary:hover { background: var(--erp-navy-dark); border-color: var(--erp-navy-dark); color: #fff; }

.btn-erp-cancel {
    height: 38px; border: 1px solid #c8d2db; background: #fff;
    color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-erp-cancel:hover { background: #f5f7f9; color: #334451; }

.badge-erp {
    font-size: 0.67rem; font-weight: 600; padding: 3px 8px; border-radius: 4px; display: inline-block;
}
.badge-erp-neutral { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

/* Dark Mode Overrides */
[data-bs-theme="dark"] {
    --erp-bg: #101a24;
    --erp-white: #172534;
    --erp-text: #edf3f7;
    --erp-muted: #9aabb9;
    --erp-border: #2d3e4e;
    --erp-navy: #8eafc9;
    --erp-navy-dark: #dce8f0;
}
[data-bs-theme="dark"] .inst-header h3 { color: #edf3f7; }
[data-bs-theme="dark"] .inst-header-icon { background: #203445; border-color: #33495a; color: #b8d0e2; }
[data-bs-theme="dark"] .inst-form-panel, 
[data-bs-theme="dark"] .inst-form-header { background: #142230 !important; }
[data-bs-theme="dark"] .inst-form-panel .form-control,
[data-bs-theme="dark"] .inst-form-panel .form-select,
[data-bs-theme="dark"] .inst-form-panel .input-group-text { 
    background: #172534 !important; color: var(--erp-text); border-color: var(--erp-border); 
}
[data-bs-theme="dark"] .btn-erp-cancel { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
</style>

<div class="service-form-page">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi bi-pencil-square"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($page_title) ?></h3>
                <p>Modify existing information for Service Record ID: #<?= $id ?></p>
            </div>
        </div>
        <a href="view_services.php" class="btn btn-erp-cancel px-3">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <!-- NOTIFICATION ALERT -->
    <?= $update_msg ?>

    <!-- FORM PANEL -->
    <div class="inst-form-panel">
        <div class="inst-form-header">
            <div class="inst-form-title">
                <i class="bi bi-card-checklist text-primary me-1"></i> Update Record Details
            </div>
            <!-- <span class="badge-erp badge-erp-neutral">ID #<?= $id ?></span> -->
        </div>
        <div class="inst-form-body">
            <form method="POST">
                <div class="row g-3">
                    
                    <div class="col-md-4">
                        <label class="form-label">Service / Bill Date</label>
                        <input type="date" name="service_date" value="<?= $data['service_date'] ?>" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Bill Number</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0">#</span>
                            <input type="text" name="bill_number" value="<?= htmlspecialchars($data['bill_number']) ?>" class="form-control border-start-0" placeholder="INV-000">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Amount (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0">₹</span>
                            <input type="number" step="0.01" name="amount" value="<?= $data['amount'] ?>" class="form-control border-start-0 fw-semibold" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Item Name</label>
                        <input type="text" name="item_name" value="<?= htmlspecialchars($data['item_name']) ?>" class="form-control" placeholder="e.g. HP Printer" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Service Type</label>
                        <input type="text" name="service_type" value="<?= htmlspecialchars($data['service_type']) ?>" class="form-control" placeholder="e.g. Refilling / Repair">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Vendor / Provider</label>
                        <select name="vendor_id" class="form-select" required>
                            <option value="">Select Vendor</option>
                            <?php foreach($vendors as $vendor): ?>
                                <option value="<?= $vendor['id'] ?>" <?= ($vendor['id'] == $data['vendor_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($vendor['vendor_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="view_services.php" class="btn btn-erp-cancel px-3">
                                Cancel
                            </a>
                            <button type="submit" name="update" class="btn btn-erp-primary px-4">
                                <i class="bi bi-check-lg me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include "layout.php";
?>