<?php
session_start();

if (!isset($_SESSION["role"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

$page_title = "Dashboard";

// 1. Logic remains the same
$total_services = 0;
$total_amount = 0;
$result = $conn->query("SELECT COUNT(*) as total_services, IFNULL(SUM(amount),0) as total_amount FROM services");
if ($result) {
    $row = $result->fetch_assoc();
    $total_services = $row['total_services'];
    $total_amount = $row['total_amount'];
}

$search_result = null;
if (isset($_GET['bill_no']) && !empty(trim($_GET['bill_no']))) {
    $bill_no = trim($_GET['bill_no']);
    $stmt = $conn->prepare("SELECT s.*, v.vendor_name FROM services s LEFT JOIN vendors v ON s.vendor_id = v.id WHERE s.bill_number = ?");
    $stmt->bind_param("s", $bill_no);
    $stmt->execute();
    $search_result = $stmt->get_result();
}
$action = $_GET['action'] ?? '';

// 2. Include the TOP part of your layout
include "layout.php"; 
?>

<div class="container-fluid">

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" action="index.php">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <label class="form-label fw-bold text-muted small mb-2 uppercase">Search Service by Bill Number</label>
                        <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
                            <span class="input-group-text bg-white border-0 ps-4">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" name="bill_no" class="form-control border-0 shadow-none fs-6" 
                                   placeholder="Enter Bill Number..." 
                                   value="<?= isset($_GET['bill_no']) ? htmlspecialchars($_GET['bill_no']) : '' ?>">
                            <button class="btn btn-primary px-4 fw-medium">Search</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($search_result !== null): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="fw-bold mb-0">Search Result</h5>
        </div>
        <div class="card-body p-4">
            <?php if ($search_result->num_rows > 0): ?>
                <?php $bill_no = $_GET['bill_no']; ?>

                <?php if ($action == ''): ?>
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border">
                        <div>
                            <span class="text-muted small">Bill Number</span>
                            <div class="fw-bold fs-5"><?= htmlspecialchars($bill_no); ?></div>
                        </div>
                        <a href="index.php?bill_no=<?= urlencode($bill_no); ?>&action=view" 
                           class="btn btn-indigo btn-sm rounded-pill px-4" 
                           style="background: var(--primary-indigo); color: white;">
                            <i class="bi bi-eye me-1"></i> View Details
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($action == 'view'): ?>
                    <div class="row g-3">
                    <?php while($row = $search_result->fetch_assoc()): ?>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-white shadow-sm">
                                <div class="row mb-2">
                                    <div class="col-5 text-muted small">Vendor:</div>
                                    <div class="col-7 fw-semibold"><?= htmlspecialchars($row['vendor_name']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-muted small">Service Date:</div>
                                    <div class="col-7"><?= htmlspecialchars($row['service_date']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-muted small">Item:</div>
                                    <div class="col-7"><?= htmlspecialchars($row['item_name']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-muted small">Amount:</div>
                                    <div class="col-7 fw-bold text-success">₹ <?= number_format((float)$row['amount'],2); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    </div>
                    <div class="mt-4">
                        <a href="index.php?bill_no=<?= urlencode($bill_no); ?>" class="btn btn-light border rounded-pill px-4">
                            <i class="bi bi-arrow-left me-2"></i>Back
                        </a>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-soft-warning d-flex align-items-center rounded-4 border-0 bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    No service found for this Bill Number.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($action != 'view'): ?>
    <div class="row g-4">
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary">
                            <i class="bi bi-gear-fill fs-4"></i>
                        </div>
                        <span class="text-success small fw-bold">Active Status</span>
                    </div>
                    <h6 class="text-muted fw-medium small mb-1">Total Services</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($total_services); ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success">
                            <i class="bi bi-currency-rupee fs-4"></i>
                        </div>
                        <span class="text-muted small">Cumulative</span>
                    </div>
                    <h6 class="text-muted fw-medium small mb-1">Total Expenditure</h6>
                    <h2 class="fw-bold mb-0">₹ <?= number_format((float)$total_amount, 2); ?></h2>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php 
// 4. Close your DB and Footer
$conn->close(); 
include "footer.php"; 
?>