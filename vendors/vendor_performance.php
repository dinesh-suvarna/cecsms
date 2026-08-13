<?php
session_start();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";

$page_title = "Vendor Performance Analytics";

/**
 * Helper function to retrieve vendor analytics filtered by sector category
 */
function getVendorAnalyticsByCategory($conn, $category) {
    $stmt = $conn->prepare("
        SELECT 
            v.id, v.vendor_name, v.category, v.email,
            -- Sector-specific total spend calculations
            COALESCE((SELECT SUM(amount) FROM stock_details WHERE vendor_id = v.id), 0) + 
            COALESCE((SELECT SUM(total_qty * unit_price) FROM furniture_stock WHERE vendor_id = v.id), 0) + 
            COALESCE((SELECT SUM(total_qty * unit_price) FROM electrical_stock WHERE vendor_id = v.id), 0) as total_spend,

            -- Sector-specific total transactions count
            (SELECT COUNT(*) FROM stock_details WHERE vendor_id = v.id) + 
            (SELECT COUNT(*) FROM furniture_stock WHERE vendor_id = v.id) + 
            (SELECT COUNT(*) FROM electrical_stock WHERE vendor_id = v.id) as total_orders,

            (SELECT COUNT(*) FROM services WHERE vendor_id = v.id) as service_calls,
            COALESCE((SELECT SUM(amount) FROM services WHERE vendor_id = v.id), 0) as service_costs,
            (SELECT COUNT(*) FROM stock_details WHERE vendor_id = v.id AND status = 'maintenance') as repair_count
        FROM vendors v
        WHERE v.category = ?
        GROUP BY v.id
        ORDER BY total_spend DESC
    ");
    
    $stmt->bind_param("s", $category);
    $stmt->execute();
    return $stmt->get_result();
}

// Map the 3 primary sectors to their query parameters
$categories = [
    'Computer'   => getVendorAnalyticsByCategory($conn, 'Computer'),
    'Furniture'  => getVendorAnalyticsByCategory($conn, 'Furniture'),
    'Electrical' => getVendorAnalyticsByCategory($conn, 'Electricals') // Matches database string
];

ob_start();
?>

<style>
    .fw-800 { font-weight: 800 !important; }
    .stat-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
    .accordion-button:after { background-size: 1rem; }
    .perf-card { border-radius: 12px; border: 1px solid #f1f5f9; background: #f8fafc; padding: 10px 15px; text-align: center; }
    .nav-pills .nav-link { font-weight: 700; color: #475569; border-radius: 8px; padding: 10px 20px; }
    .nav-pills .nav-link.active { background-color: #0d6efd; color: #ffffff; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-800 text-dark mb-0">Vendor Performance Dashboard</h4>
            <p class="text-muted small mb-0">Analytics grouped by core procurement sector.</p>
        </div>
        
        <!-- Category Navigation Tabs -->
        <ul class="nav nav-pills" id="vendorCategoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="computer-tab" data-bs-toggle="pill" data-bs-target="#tab-computer" type="button" role="tab">
                    <i class="bi bi-laptop me-1"></i> Computer
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="furniture-tab" data-bs-toggle="pill" data-bs-target="#tab-furniture" type="button" role="tab">
                    <i class="bi bi-desk me-1"></i> Furniture
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="electrical-tab" data-bs-toggle="pill" data-bs-target="#tab-electrical" type="button" role="tab">
                    <i class="bi bi-lightning-charge me-1"></i> Electricals
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="vendorCategoryContent">
        <?php 
        $first_tab = true;
        foreach ($categories as $catKey => $vendor_result): 
            $tab_id = strtolower($catKey);
        ?>
            <div class="tab-pane fade <?= $first_tab ? 'show active' : '' ?>" id="tab-<?= $tab_id ?>" role="tabpanel">
                
                <?php if ($vendor_result->num_rows > 0): ?>
                    <div class="accordion" id="accordion-<?= $tab_id ?>">
                        <?php while($v = $vendor_result->fetch_assoc()): ?>
                            <div class="accordion-item border-0 mb-3 shadow-sm rounded-4 overflow-hidden">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#v-<?= $tab_id ?>-<?= $v['id'] ?>">
                                        <div class="row w-100 align-items-center">
                                            <div class="col-md-4">
                                                <!-- <span class="badge bg-primary mb-1 small"><?= htmlspecialchars($v['category']) ?></span> -->
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
                                <div id="v-<?= $tab_id ?>-<?= $v['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#accordion-<?= $tab_id ?>">
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
                                                    <div class="h5 fw-800 mb-0" style="font-size: 0.9rem;"><?= htmlspecialchars($v['email'] ?: 'N/A') ?></div>
                                                    <div class="small text-muted">For procurement & service logs</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                        <div class="card-body">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <h6 class="fw-bold mt-2 text-secondary">No vendors found in this category</h6>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php 
            $first_tab = false;
        endforeach; 
        ?>
    </div>
</div>

<?php 
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>