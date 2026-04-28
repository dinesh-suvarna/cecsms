<?php

session_start();

if (!isset($_SESSION["role"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";

$page_title = "Service Dashboard";

// Logic for statistics
$total_services = 0;
$total_amount = 0;
$result = $conn->query("SELECT COUNT(*) as total_services, IFNULL(SUM(amount),0) as total_amount FROM services");
if ($result) {
    $row = $result->fetch_assoc();
    $total_services = $row['total_services'];
    $total_amount = $row['total_amount'];
}

// Logic for Bill Search
$search_result = null;
if (isset($_GET['bill_no']) && !empty(trim($_GET['bill_no']))) {
    $bill_no = trim($_GET['bill_no']);
    $stmt = $conn->prepare("SELECT s.*, v.vendor_name FROM services s LEFT JOIN vendors v ON s.vendor_id = v.id WHERE s.bill_number = ?");
    $stmt->bind_param("s", $bill_no);
    $stmt->execute();
    $search_result = $stmt->get_result();
}

$action = $_GET['action'] ?? '';

ob_start();
?>

<div class="container-fluid animate-fade-in">

    <div class="card border-0 shadow-sm rounded-4 mb-4" 
     style="background: linear-gradient(to right, #ffffff, #f0fdf4);">
    <div class="card-body p-4 p-lg-5">
        <form method="GET" action="index.php">
            <div class="row justify-content-center text-center">
                <div class="col-lg-7">
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 mb-2">Service Database</span>
                    <h3 class="fw-800 text-dark mb-2">Find a Service Record</h3>
                    <p class="text-muted small mb-4">Search by Bill Number to view detailed breakdown and vendor information.</p>

                    <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border-2 border-success border-opacity-10">
                        <span class="input-group-text bg-white border-0 ps-4">
                            <i class="bi bi-search text-success"></i>
                        </span>
                        <input type="text" name="bill_no" class="form-control border-0 shadow-none fs-6"
                               placeholder="Enter Bill Number (e.g. 2024/001)..."
                               value="<?= isset($_GET['bill_no']) ? htmlspecialchars($_GET['bill_no']) : '' ?>">
                        <button class="btn btn-success px-4 fw-bold border-0" style="background-color: #10b981;">
                            Search
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
    <div class="row">
    <div class="col-12">
        <?php if ($search_result !== null): ?>
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden animate-fade-in">
                <div class="bg-success p-1" style="height: 4px; background-color: #10b981 !important;"></div>
                
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-emerald-soft p-2 rounded-3 text-success d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-journal-check fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-dark">Search Results</h5>
                                <p class="text-muted small mb-0">Found record for query</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <?php if ($search_result->num_rows > 0): ?>
                        <?php $bill_no_val = $_GET['bill_no']; ?>

                        <?php if ($action == ''): ?>
                            <div class="d-flex align-items-center justify-content-between p-4 rounded-4 bg-light border border-dashed border-2">
                                <div class="d-flex align-items-center gap-4">
                                    <div class="bg-white p-3 rounded-circle border shadow-sm d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                        <i class="bi bi-receipt text-success fs-3"></i>
                                    </div>
                                    <div>
                                        <span class="text-muted small fw-bold text-uppercase letter-spacing-1">Found Bill Number</span>
                                        <div class="fw-800 fs-4 text-dark"><?= htmlspecialchars($bill_no_val); ?></div>
                                    </div>
                                </div>
                                <a href="index.php?bill_no=<?= urlencode($bill_no_val); ?>&action=view" 
                                   class="btn btn-success rounded-pill px-5 py-2 shadow-sm fw-bold" style="background-color: #10b981; border:none;">
                                    <i class="bi bi-eye-fill me-2"></i> View Details
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($action == 'view'): ?>
                            <div class="row g-4">
                            <?php while($row = $search_result->fetch_assoc()): ?>
                                <div class="col-md-6 col-xl-4">
                                    <div class="card h-100 rounded-4 border border-light shadow-sm transition-hover">
                                        <div class="card-body p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-4">
                                                <span class="badge bg-emerald-soft text-success rounded-pill px-3 py-2 fw-bold" style="font-size: 11px;">
                                                    SERVICE RECORD
                                                </span>
                                                <div class="text-success fw-800 fs-4">₹<?= number_format((float)$row['amount'],2); ?></div>
                                            </div>
                                            
                                            <div class="mb-3 d-flex justify-content-between border-bottom pb-2">
                                                <span class="text-muted small fw-medium">Vendor</span>
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['vendor_name']); ?></span>
                                            </div>
                                            <div class="mb-3 d-flex justify-content-between border-bottom pb-2">
                                                <span class="text-muted small fw-medium">Service Date</span>
                                                <span class="fw-bold text-dark"><?= date("d M Y", strtotime($row['service_date'])); ?></span>
                                            </div>
                                            <div class="mt-3">
                                                <span class="text-muted small fw-bold d-block mb-1 text-uppercase">Item Description</span>
                                                <p class="text-secondary mb-0 small lh-base"><?= htmlspecialchars($row['item_name']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            </div>
                            <div class="mt-4 pt-2">
                                <a href="index.php?bill_no=<?= urlencode($bill_no_val); ?>" class="btn btn-light border-0 rounded-pill px-4 fw-bold text-muted bg-white shadow-sm">
                                    <i class="bi bi-arrow-left me-2"></i> Back to Result
                                </a>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex p-4 mb-3">
                                <i class="bi bi-search fs-1"></i>
                            </div>
                            <h5 class="fw-bold text-dark">No Records Found</h5>
                            <p class="text-muted mx-auto" style="max-width: 300px;">We couldn't find any service entries for bill number <b><?= htmlspecialchars($_GET['bill_no']) ?></b>.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($action != 'view'): ?>
<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="bg-emerald-soft p-3 rounded-4 text-success d-flex align-items-center justify-content-center" style="width: 55px; height: 55px;">
                        <i class="bi bi-gear-wide-connected fs-3"></i>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold" style="font-size: 10px;">
                        <i class="bi bi-check-circle-fill me-1"></i> ACTIVE SYSTEM
                    </span>
                </div>
                <h6 class="text-muted fw-bold small mb-1 text-uppercase letter-spacing-1">Total Services</h6>
                <h2 class="fw-800 mb-0 text-dark"><?= number_format($total_services); ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 position-relative overflow-hidden h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-4 text-primary d-flex align-items-center justify-content-center" style="width: 55px; height: 55px;">
                        <i class="bi bi-currency-rupee fs-3"></i>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold" style="font-size: 10px;">
                        TOTAL COST
                    </span>
                </div>
                <h6 class="text-muted fw-bold small mb-1 text-uppercase letter-spacing-1">Total Expenditure</h6>
                <h2 class="fw-800 mb-0 text-dark">₹<?= number_format((float)$total_amount, 2); ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-4">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); transition: transform 0.2s;">
    <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center text-center text-white">
        
        <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-4 mb-3" style="width: 65px; height: 65px;">
            <i class="bi bi-pc-display text-white" style="font-size: 32px;"></i>
        </div>

        <h5 class="fw-bold mb-2">New IT Entry?</h5>
        
        <p class="small opacity-75 mb-4">
            Log maintenance for Computers, Printers, UPS units, or other IT peripherals.
        </p>

        <a href="add_service.php" class="btn btn-white btn-lg rounded-pill px-4 fw-800 text-success shadow-sm w-100" 
           style="background: #fff; font-size: 13px; border: none;">
            <i class="bi bi-plus-circle-fill me-2"></i> ADD SERVICE RECORD
        </a>
    </div>
</div>

<style>
    /* Optional Hover Effect */
    .card:hover {
        transform: translateY(-5px);
    }
</style>
    </div>
</div>
<?php endif; ?>

</div>

<?php
$content = ob_get_clean();
//$conn->close();
include "layout.php";
?>