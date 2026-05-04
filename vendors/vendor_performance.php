<?php
session_start();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";

$page_title = "Vendor Performance Analytics";

// Performance Query: Dynamically calculates Spend and Orders from specific category tables
$perf_query = "
    SELECT 
        v.id, v.vendor_name, v.category, v.email,
        -- Combined Total Spend from Stock, Furniture, and Electrical
        COALESCE((
            SELECT SUM(amount) FROM stock_details WHERE vendor_id = v.id
        ), 0) + 
        COALESCE((
            SELECT SUM(total_qty * unit_price) FROM furniture_stock WHERE vendor_id = v.id
        ), 0) + 
        COALESCE((
            SELECT SUM(total_qty * unit_price) FROM electrical_stock WHERE vendor_id = v.id
        ), 0) as total_spend,

        -- Combined Total Transactions/Orders count
        (SELECT COUNT(*) FROM stock_details WHERE vendor_id = v.id) + 
        (SELECT COUNT(*) FROM furniture_stock WHERE vendor_id = v.id) + 
        (SELECT COUNT(*) FROM electrical_stock WHERE vendor_id = v.id) as total_orders,

        (SELECT COUNT(*) FROM services WHERE vendor_id = v.id) as service_calls,
        (SELECT SUM(amount) FROM services WHERE vendor_id = v.id) as service_costs,
        (SELECT COUNT(*) FROM stock_details WHERE vendor_id = v.id AND status = 'maintenance') as repair_count
    FROM vendors v
    GROUP BY v.id
    ORDER BY total_spend DESC";

$perf_result = $conn->query($perf_query);

ob_start();
?>

<style>
    .fw-800 { font-weight: 800 !important; }
    .stat-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
    .accordion-button:after { background-size: 1rem; }
    .perf-card { border-radius: 12px; border: 1px solid #f1f5f9; background: #f8fafc; padding: 10px 15px; text-align: center; }
</style>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="fw-800 text-dark mb-0">Vendor Performance Dashboard</h4>
        <p class="text-muted small">Analytics based on actual stock procurement and service history.</p>
    </div>

    <div class="accordion" id="perfAccordion">
        <?php while($v = $perf_result->fetch_assoc()): ?>
        <div class="accordion-item border-0 mb-3 shadow-sm rounded-4 overflow-hidden">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed py-4" type="button" data-bs-toggle="collapse" data-bs-target="#v-<?= $v['id'] ?>">
                    <div class="row w-100 align-items-center">
                        <div class="col-md-4">
                            <span class="badge bg-dark mb-1 small"><?= $v['category'] ?></span>
                            <div class="fw-800 text-dark fs-5"><?= htmlspecialchars($v['vendor_name']) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-label">Total Spend (Stock)</div>
                            <div class="fw-bold text-primary">₹<?= number_format($v['total_spend'], 2) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-label">Stock Entries</div>
                            <div class="fw-bold"><?= $v['total_orders'] ?> Items Procured</div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-label">Reliability</div>
                            <?php 
                                // Simple logic: if service calls are high relative to orders, mark as maintenance heavy
                                if($v['service_calls'] > 5) {
                                    echo '<div class="text-warning fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>High Maint.</div>';
                                } else {
                                    echo '<div class="text-success fw-bold"><i class="bi bi-shield-check me-1"></i>Stable</div>';
                                }
                            ?>
                        </div>
                    </div>
                </button>
            </h2>
            <div id="v-<?= $v['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#perfAccordion">
                <div class="accordion-body bg-white border-top">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="perf-card">
                                <div class="stat-label">Service History</div>
                                <div class="h5 fw-800 mb-0"><?= $v['service_calls'] ?> Calls</div>
                                <div class="small text-muted">₹<?= number_format($v['service_costs'], 2) ?> total service cost</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="perf-card">
                                <div class="stat-label">Maintenance Load</div>
                                <div class="h5 fw-800 mb-0 <?= $v['repair_count'] > 0 ? 'text-danger' : '' ?>"><?= $v['repair_count'] ?> Units</div>
                                <div class="small text-muted">Assets currently in repair</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="perf-card">
                                <div class="stat-label">Primary Email</div>
                                <div class="h5 fw-800 mb-0" style="font-size: 0.9rem;"><?= $v['email'] ?: 'N/A' ?></div>
                                <div class="small text-muted">For procurement & service logs</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php 
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>