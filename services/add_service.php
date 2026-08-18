<?php
require_once __DIR__ . "/../config/db.php";

session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$page_title = "Add Service Record";
$today = date("Y-m-d");
$success = false;
$error_msg = "";

// Logic for submission
if (isset($_POST['submit'])) {
    $date        = $_POST['tdate'] ?? '';
    $item        = trim($_POST['item_name'] ?? '');
    $type        = trim($_POST['service_type'] ?? '');
    $vendor_id   = (int)($_POST['vendor_id'] ?? 0);
    $bill        = trim($_POST['bill_number'] ?? '');
    $servicedate = $_POST['service_date'] ?? '';
    $amount      = (float)($_POST['amount'] ?? 0);

    if ($date > $today || $servicedate > $today) {
        $error_msg = "Future dates are not allowed.";
    } elseif ($vendor_id <= 0) {
        $error_msg = "Please select a valid vendor.";
    } elseif ($amount <= 0) {
        $error_msg = "Amount must be greater than 0.";
    } elseif (empty($bill)) {
        $error_msg = "Bill number is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO services (date, item_name, service_type, vendor_id, bill_number, service_date, amount) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sssissd", $date, $item, $type, $vendor_id, $bill, $servicedate, $amount);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $error_msg = "Database Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch Vendors
$vendors = [];
$result = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) { 
        $vendors[] = $row; 
    }
}

/* START BUFFER */
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

/* Buttons */
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
                <i class="bi bi-tools"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($page_title) ?></h3>
                <p>Register new maintenance, repair, and equipment service logs into the system.</p>
            </div>
        </div>
        <a href="index.php" class="btn btn-erp-cancel px-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <!-- ERROR NOTIFICATION -->
    <?php if(!empty($error_msg)): ?>
        <div class="alert alert-danger border-0 rounded-1 shadow-sm mb-4 d-flex align-items-center gap-2" style="font-size: .8rem;">
            <i class="bi bi-exclamation-octagon-fill fs-6"></i> 
            <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- FORM PANEL -->
    <div class="inst-form-panel">
        <div class="inst-form-header">
            <div class="inst-form-title">
                <i class="bi bi-plus-circle text-primary me-1"></i> New Service Entry Details
            </div>
            <!-- <span class="badge-erp badge-erp-neutral">Data Entry Form</span> -->
        </div>
        <div class="inst-form-body">
            <form method="POST" action="">
                <div class="row g-3">
                    
                    <div class="col-md-4">
                        <label class="form-label">Entry Date</label>
                        <input type="date" name="tdate" value="<?= $today ?>" max="<?= $today ?>" class="form-control" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Equipment / Item</label>
                        <select name="item_name" class="form-select" required>
                            <option value="">-- Select Item --</option>
                            <?php 
                            $items = ["Printer", "Projector", "Motherboard", "SMPS", "UPS", "Network", "CCTV"];
                            foreach($items as $i) {
                                $selected = (isset($_POST['item_name']) && $_POST['item_name'] === $i) ? 'selected' : '';
                                echo "<option value='$i' $selected>$i</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Service Type</label>
                        <input type="text" name="service_type" value="<?= htmlspecialchars($_POST['service_type'] ?? '') ?>" placeholder="e.g. Repair, Maintenance" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Vendor Name</label>
                        <select name="vendor_id" class="form-select" required>
                            <option value="">-- Select Vendor --</option>
                            <?php foreach($vendors as $vendor): ?>
                                <?php $selected = (isset($_POST['vendor_id']) && (int)$_POST['vendor_id'] === (int)$vendor['id']) ? 'selected' : ''; ?>
                                <option value="<?= $vendor['id'] ?>" <?= $selected ?>><?= htmlspecialchars($vendor['vendor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Bill Number</label>
                        <input type="text" name="bill_number" value="<?= htmlspecialchars($_POST['bill_number'] ?? '') ?>" pattern="[A-Za-z0-9\-\/]+" placeholder="e.g. 2024/001" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Bill Date</label>
                        <input type="date" name="service_date" value="<?= $_POST['service_date'] ?? $today ?>" max="<?= $today ?>" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Total Amount (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0">₹</span>
                            <input type="number" name="amount" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" step="0.01" min="0" placeholder="0.00" class="form-control border-start-0" required>
                        </div>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top">
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="view_services.php" class="btn btn-erp-cancel px-3">
                                <i class="bi bi-list-ul me-1"></i> View All Logs
                            </a>
                            <div class="d-flex gap-2">
                                <button type="reset" class="btn btn-erp-cancel px-3">
                                    Reset Form
                                </button>
                                <button type="submit" name="submit" class="btn btn-erp-primary px-4">
                                    <i class="bi bi-check-lg me-1"></i> Submit Service Record
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- SUCCESS TOAST IMPLEMENTATION -->
<?php if($success): ?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
  <div id="successToast" class="toast align-items-center text-bg-success border-0 rounded-1 shadow" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body p-3" style="font-size: .8rem;">
        <i class="bi bi-check-circle-fill me-2"></i> Record saved successfully!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function(){
    var toast = new bootstrap.Toast(document.getElementById('successToast'));
    toast.show();
});
</script>
<?php endif; ?>

<?php
/* STORE CONTENT & LOAD LAYOUT */
$content = ob_get_clean();
include "layout.php";
?>