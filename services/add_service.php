<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

$page_title = "Add Service";
$today = date("Y-m-d");
$success = false;
$error_msg = "";

// Logic for submission
if(isset($_POST['submit'])){
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

        if($stmt->execute()){
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
if($result) {
    while($row = $result->fetch_assoc()) { $vendors[] = $row; }
}

/* ✅ START BUFFER */
ob_start();
?>

<div class="container-fluid animate-fade-in px-0">

    <?php if(!empty($error_msg)): ?>
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 mx-3">
            <i class="bi bi-exclamation-octagon-fill me-2"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div class="row g-0">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="bg-success" style="height: 5px; background-color: #10b981 !important;"></div>
                
                <div class="card-body p-4 p-lg-5">
                    <form method="POST" action="">
                        <div class="row g-4">
                            
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small text-uppercase mb-2">Entry Date</label>
                                <input type="date" name="tdate" value="<?= $today ?>" max="<?= $today ?>" class="form-control form-control-lg rounded-3 border-light bg-light shadow-none" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small text-uppercase mb-2">Equipment / Item</label>
                                <select name="item_name" class="form-select form-select-lg rounded-3 border-light bg-light shadow-none" required>
                                    <option value="">-- Select Item --</option>
                                    <?php 
                                    $items = ["Printer", "Projector", "Motherboard", "SMPS", "UPS", "Network", "CCTV"];
                                    foreach($items as $i) echo "<option value='$i'>$i</option>";
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small text-uppercase mb-2">Service Type</label>
                                <input type="text" name="service_type" placeholder="e.g. Repair" class="form-control form-control-lg rounded-3 border-light bg-light shadow-none" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small text-uppercase mb-2">Vendor Name</label>
                                <select name="vendor_id" class="form-select form-select-lg rounded-3 border-light bg-light shadow-none" required>
                                    <option value="">-- Select Vendor --</option>
                                    <?php foreach($vendors as $vendor): ?>
                                        <option value="<?= $vendor['id'] ?>"><?= htmlspecialchars($vendor['vendor_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small text-uppercase mb-2">Bill Number</label>
                                <input type="text" name="bill_number" pattern="[A-Za-z0-9\-\/]+" placeholder="Enter Bill No." class="form-control form-control-lg rounded-3 border-light bg-light shadow-none" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small text-uppercase mb-2">Bill Date</label>
                                <input type="date" name="service_date" value="<?= $today ?>" max="<?= $today ?>" class="form-control form-control-lg rounded-3 border-light bg-light shadow-none" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small text-uppercase mb-2">Total Amount (₹)</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-light text-success fw-bold">₹</span>
                                    <input type="number" name="amount" step="0.01" min="0" placeholder="0.00" class="form-control rounded-end-3 border-light bg-light shadow-none" required>
                                </div>
                            </div>

                            <div class="col-12 mt-5 border-top pt-4">
                                <div class="d-flex align-items-center justify-content-between">
                                    <a href="view_services.php" class="btn btn-link text-decoration-none text-muted fw-bold">
                                        <i class="bi bi-arrow-left me-2"></i> View All Records
                                    </a>
                                    <div class="d-flex gap-3">
                                        <button type="reset" class="btn btn-light rounded-pill px-4 fw-bold text-secondary border-0">
                                            Reset Form
                                        </button>
                                        <button type="submit" name="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm py-2" style="background-color: #10b981; border:none;">
                                            Submit Service Record
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if($success): ?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
  <div id="successToast" class="toast align-items-center text-bg-success border-0 rounded-4 shadow-lg" role="alert">
    <div class="d-flex">
      <div class="toast-body p-3">
        <i class="bi bi-check-circle-fill me-2"></i> Record saved successfully!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
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
/* ✅ STORE CONTENT & LOAD LAYOUT */
$content = ob_get_clean();
//$conn->close();
include "layout.php";
?>