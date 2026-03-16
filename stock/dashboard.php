<?php
$page_title = "Dashboard";
$page_icon  = "bi-speedometer2";
include "../config/db.php";

/* Queries */
$total_items = $conn->query("SELECT COUNT(*) as total FROM items_master")->fetch_assoc()['total'];

$total_entries = $conn->query("SELECT COUNT(*) as total FROM stock_details")->fetch_assoc()['total'];

$total_quantity = $conn->query("SELECT SUM(quantity) as total FROM stock_details")->fetch_assoc()['total'] ?? 0;

$expired = $conn->query("
    SELECT COUNT(*) as total 
    FROM stock_details 
    WHERE warranty_upto < CURDATE()
")->fetch_assoc()['total'];

$expiring = $conn->query("
    SELECT COUNT(*) as total 
    FROM stock_details 
    WHERE warranty_upto BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
")->fetch_assoc()['total'];

ob_start();
?>

<div class="container-fluid mt-4">

<div class="row g-4">

    <!-- Total Items -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Stock Items</small>
                        <h4 class="fw-semibold mb-0"><?= $total_items ?></h4>
                    </div>
                    <i class="bi bi-box fs-3 text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Entries -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <small class="text-muted">Stock Entries</small>
                <h4 class="fw-semibold mb-0"><?= $total_entries ?></h4>
            </div>
        </div>
    </div>

    <!-- Total Quantity -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <small class="text-muted">Total Quantity</small>
                <h4 class="fw-semibold mb-0"><?= $total_quantity ?></h4>
            </div>
        </div>
    </div>

    <!-- Expiring Soon -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <small class="text-muted">Expiring Within 30 Days</small>
                <h4 class="fw-semibold text-warning mb-0"><?= $expiring ?></h4>
            </div>
        </div>
    </div>

    <!-- Expired -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <small class="text-muted">Expired Items</small>
                <h4 class="fw-semibold text-danger mb-0"><?= $expired ?></h4>
            </div>
        </div>
    </div>

</div>

</div>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>