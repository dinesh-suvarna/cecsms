<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";

$page_title = "Edit Service Entry";
$page_icon  = "bi-pencil-square";

$id = intval($_GET['id']); 

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
        $update_msg = '<div class="alert alert-success border-0 shadow-sm rounded-3">
                        <i class="bi bi-check-circle-fill me-2"></i> Record updated successfully!
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

<div class="container-fluid animate-fade-in px-0">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <?= $update_msg ?>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-800 text-dark mb-0">Update Service Details</h5>
                        <p class="text-muted small mb-0">Modify information for Record ID: #<?= $id ?></p>
                    </div>
                    <a href="view_services.php" class="btn btn-light btn-sm rounded-pill px-3 border fw-bold text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Back to List
                    </a>
                </div>

                <div class="card-body p-4">
                    <form method="POST" class="row g-4">
                        
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase text-muted">Service/Bill Date</label>
                            <input type="date" name="service_date" value="<?= $data['service_date'] ?>" class="form-control rounded-3 border-light bg-light shadow-none" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase text-muted">Bill Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 text-muted">#</span>
                                <input type="text" name="bill_number" value="<?= htmlspecialchars($data['bill_number']) ?>" class="form-control rounded-3 border-light bg-light shadow-none" placeholder="INV-000">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase text-muted">Amount (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 text-muted">₹</span>
                                <input type="number" step="0.01" name="amount" value="<?= $data['amount'] ?>" class="form-control rounded-3 border-light bg-light shadow-none fw-bold" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Item Name</label>
                            <input type="text" name="item_name" value="<?= htmlspecialchars($data['item_name']) ?>" class="form-control rounded-3 border-light bg-light shadow-none" placeholder="e.g. HP Printer" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Service Type</label>
                            <input type="text" name="service_type" value="<?= htmlspecialchars($data['service_type']) ?>" class="form-control rounded-3 border-light bg-light shadow-none" placeholder="e.g. Refilling / Repair">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-uppercase text-muted">Vendor / Provider</label>
                            <select name="vendor_id" class="form-select rounded-3 border-light bg-light shadow-none select-custom" required>
                                <option value="">Select Vendor</option>
                                <?php foreach($vendors as $vendor): ?>
                                    <option value="<?= $vendor['id'] ?>" <?= ($vendor['id'] == $data['vendor_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($vendor['vendor_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 mt-5">
                            <hr class="opacity-50 mb-4">
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="submit" name="update" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">
                                    <i class="bi bi-save me-2"></i> Save Changes
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.fw-800 { font-weight: 800 !important; }
.form-control, .form-select { padding: 0.75rem 1rem; border-width: 2px; }
.form-control:focus, .form-select:focus { background-color: #fff !important; border-color: #10b981 !important; }
.input-group-text { border: 2px solid #f8fafc; }
</style>

<?php
$content = ob_get_clean();
include "layout.php";
?>