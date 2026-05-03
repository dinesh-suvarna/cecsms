<?php
session_start();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";

$page_title = "Vendor Performance Analytics";

// Performance Query: Combines Procurement, Services, and Stock Status
$perf_query = "
    SELECT 
        v.id, v.vendor_name, v.category, v.email,
        COUNT(DISTINCT pl.id) as total_orders,
        SUM(pl.final_invoice_amount) as total_spend,
        (SELECT COUNT(*) FROM services WHERE vendor_id = v.id) as service_calls,
        (SELECT SUM(amount) FROM services WHERE vendor_id = v.id) as service_costs,
        (SELECT COUNT(*) FROM stock_details WHERE vendor_id = v.id AND status = 'maintenance') as repair_count
    FROM vendors v
    LEFT JOIN purchase_ledger pl ON v.id = pl.vendor_id
    GROUP BY v.id
    ORDER BY total_spend DESC";

$perf_result = $conn->query($perf_query);

ob_start();
?>

<style>
    .fw-800 { font-weight: 800 !important; }
    .stat-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
    .accordion-button:after { background-size: 1rem; }
    .perf-card { border-radius: 12px; border: 1px solid #f1f5f9; background: #f8fafc; padding: 10px 15px; }
</style>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="fw-800 text-dark mb-0">Vendor Performance Dashboard</h4>
        <p class="text-muted small">Analytical overview of vendor reliability and financial impact.</p>
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
                            <div class="stat-label">Total Spend</div>
                            <div class="fw-bold text-primary">₹<?= number_format($v['total_spend'], 2) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-label">Orders</div>
                            <div class="fw-bold"><?= $v['total_orders'] ?> Transactions</div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-label">Health Score</div>
                            <div class="text-success fw-bold"><i class="bi bi-shield-check me-1"></i>Reliable</div>
                        </div>
                    </div>
                </button>
            </h2>
            <div id="v-<?= $v['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#perfAccordion">
                <div class="accordion-body bg-white border-top">
                    <div class="row g-3 text-center">
                        <div class="col-md-4">
                            <div class="perf-card">
                                <div class="stat-label">Service History</div>
                                <div class="h5 fw-800 mb-0"><?= $v['service_calls'] ?> Calls</div>
                                <div class="small text-muted">₹<?= number_format($v['service_costs'], 2) ?> total service cost</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="perf-card">
                                <div class="stat-label">Active Repairs</div>
                                <div class="h5 fw-800 mb-0 text-danger"><?= $v['repair_count'] ?> Units</div>
                                <div class="small text-muted">Assets currently in maintenance</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="perf-card">
                                <div class="stat-label">Vendor Contact</div>
                                <div class="h5 fw-800 mb-0"><?= $v['email'] ?: 'N/A' ?></div>
                                <div class="small text-muted">Primary Communication Channel</div>
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