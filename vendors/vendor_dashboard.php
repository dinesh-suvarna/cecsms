<?php
session_start();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";

$page_title = "Vendor Intelligence Dashboard";

// 1. Unified Metrics Query
$metrics_query = "
    SELECT 
        (SELECT COUNT(*) FROM vendors) as total_vendors,
        (SELECT COUNT(DISTINCT vendor_id) FROM (
            SELECT vendor_id FROM stock_details WHERE vendor_id IS NOT NULL
            UNION SELECT vendor_id FROM furniture_stock WHERE vendor_id IS NOT NULL
            UNION SELECT vendor_id FROM electrical_stock WHERE vendor_id IS NOT NULL
        ) as active) as active_vendors,
        (SELECT COALESCE(SUM(amount), 0) FROM stock_details) + 
        (SELECT COALESCE(SUM(total_qty * unit_price), 0) FROM furniture_stock) + 
        (SELECT COALESCE(SUM(total_qty * unit_price), 0) FROM electrical_stock) as grand_total_spend";
$metrics = $conn->query($metrics_query)->fetch_assoc();

// Calculate Max Spend for relative progress bar calculation
$max_spend_query = "
    SELECT MAX(total_spend) as max_spend FROM (
        SELECT 
            (COALESCE((SELECT SUM(amount) FROM stock_details WHERE vendor_id = v.id), 0) + 
             COALESCE((SELECT SUM(total_qty * unit_price) FROM furniture_stock WHERE vendor_id = v.id), 0) + 
             COALESCE((SELECT SUM(total_qty * unit_price) FROM electrical_stock WHERE vendor_id = v.id), 0)) as total_spend
        FROM vendors v
    ) as spends";
$max_spend_res = $conn->query($max_spend_query)->fetch_assoc();
$max_spend = $max_spend_res['max_spend'] > 0 ? $max_spend_res['max_spend'] : 1;

// 2. Performance Data
$perf_query = "
    SELECT 
        v.id, v.vendor_name, v.category, v.phone_number, v.email,
        (COALESCE((SELECT SUM(amount) FROM stock_details WHERE vendor_id = v.id), 0) + 
         COALESCE((SELECT SUM(total_qty * unit_price) FROM furniture_stock WHERE vendor_id = v.id), 0) + 
         COALESCE((SELECT SUM(total_qty * unit_price) FROM electrical_stock WHERE vendor_id = v.id), 0)) as total_spend,
        ((SELECT COUNT(*) FROM stock_details WHERE vendor_id = v.id) + 
         (SELECT COUNT(*) FROM furniture_stock WHERE vendor_id = v.id) + 
         (SELECT COUNT(*) FROM electrical_stock WHERE vendor_id = v.id)) as total_orders
    FROM vendors v
    ORDER BY total_spend DESC LIMIT 10";
$perf_result = $conn->query($perf_query);

ob_start();
?>

<!-- Include Chart.js for Visual Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root {
        --saas-primary: #4F46E5;
        --saas-primary-hover: #4338CA;
        --saas-primary-light: #EEF2FF;
        --saas-bg: #F8FAFC;
        --saas-card-bg: #FFFFFF;
        --saas-border: #E2E8F0;
        --saas-text-main: #0F172A;
        --saas-text-muted: #64748B;
        --saas-card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        --saas-card-hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
    }

    body { 
        background-color: var(--saas-bg); 
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--saas-text-main);
    }

    /* Metric Cards */
    .metric-card {
        background: var(--saas-card-bg);
        border: 1px solid var(--saas-border);
        border-radius: 16px;
        padding: 1.5rem;
        transition: all 0.25s ease-in-out;
        box-shadow: var(--saas-card-shadow);
    }

    .metric-card:hover { 
        transform: translateY(-2px);
        box-shadow: var(--saas-card-hover-shadow); 
        border-color: #CBD5E1; 
    }

    .metric-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    /* Table Styles */
    .saas-card {
        background: var(--saas-card-bg);
        border-radius: 16px;
        border: 1px solid var(--saas-border);
        box-shadow: var(--saas-card-shadow);
        overflow: hidden;
    }

    .saas-table thead th {
        background-color: #F8FAFC;
        text-transform: uppercase;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        color: var(--saas-text-muted);
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--saas-border);
    }

    .saas-table tbody td { 
        padding: 1rem 1.5rem; 
        border-bottom: 1px solid var(--saas-border); 
        vertical-align: middle; 
    }

    .saas-table tbody tr:last-child td {
        border-bottom: none;
    }

    .saas-table tbody tr:hover {
        background-color: #F8FAFC;
    }

    /* Dynamic Badges */
    .badge-category {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35em 0.75em;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
    }

    .badge-comp { background: #EEF2FF; color: #4338CA; }
    .badge-furn { background: #FEF3C7; color: #92400E; }
    .badge-elec { background: #ECFDF5; color: #065F46; }
    .badge-default { background: #F1F5F9; color: #475569; }

    /* Action Tiles */
    .action-tile {
        background: var(--saas-card-bg);
        border: 1px solid var(--saas-border);
        border-radius: 12px;
        padding: 0.875rem 1rem;
        display: flex;
        align-items: center;
        text-decoration: none;
        color: var(--saas-text-main);
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
    }

    .action-tile:hover { 
        background: #FFFFFF; 
        border-color: var(--saas-primary);
        color: var(--saas-primary); 
        transform: translateX(4px);
        box-shadow: var(--saas-card-shadow);
    }

    .icon-box {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--saas-primary-light);
        color: var(--saas-primary);
    }

    .promo-card {
        background: linear-gradient(135deg, #4F46E5 0%, #3730A3 100%);
        border-radius: 16px;
        color: #FFFFFF;
        position: relative;
        overflow: hidden;
    }
</style>

<div class="container-fluid py-4 px-lg-5">
    <!-- Header Block -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold tracking-tight mb-1" style="color: var(--saas-text-main);">Vendor Intelligence Engine</h3>
            <p class="text-muted mb-0 small">Real-time spend analytics, order distribution, and supplier metrics.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="vendor_manager.php" class="btn btn-primary shadow-sm rounded-3 px-3 py-2 font-medium small" style="background: var(--saas-primary);">
                <i class="bi bi-plus-lg me-2"></i>New Vendor
            </a>
        </div>
    </div>

    <!-- Key Metrics Grid -->
    <div class="row g-3 mb-4">
        <!-- Registered Vendors -->
        <div class="col-12 col-md-4">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">Registered Vendors</span>
                    <div class="metric-icon-wrapper" style="background: #EEF2FF; color: #4F46E5;">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
                <div>
                    <h3 class="fw-bold mb-1 text-truncate"><?= number_format($metrics['total_vendors']) ?></h3>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                        <i class="bi bi-check-circle me-1"></i>System Total
                    </span>
                </div>
            </div>
        </div>

        <!-- Active Suppliers -->
        <div class="col-12 col-md-4">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">Active Suppliers</span>
                    <div class="metric-icon-wrapper" style="background: #ECFDF5; color: #10B981;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
                <div>
                    <h3 class="fw-bold mb-1 text-truncate"><?= number_format($metrics['active_vendors']) ?></h3>
                    <?php 
                        $active_pct = $metrics['total_vendors'] > 0 ? round(($metrics['active_vendors'] / $metrics['total_vendors']) * 100) : 0;
                    ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                        <?= $active_pct ?>% Engagement
                    </span>
                </div>
            </div>
        </div>

        <!-- Cumulative Expenditure -->
        <div class="col-12 col-md-4">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">Cumulative Expenditure</span>
                    <div class="metric-icon-wrapper" style="background: #F0F9FF; color: #0284C7;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div>
                    <h3 class="fw-bold mb-1 text-truncate">₹<?= number_format($metrics['grand_total_spend'], 2) ?></h3>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                        All Divisions
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation -->
    <div class="row g-4 mb-4">
        <div class="col-xl-12">
            <div class="saas-card p-4">
                <h6 class="fw-bold mb-4">Filter by Domain</h6>
                
                <div class="row g-3">
                    <!-- IT & Computers -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <a href="view_vendors.php?type=Computer" class="action-tile h-100">
                            <div class="icon-box me-3"><i class="bi bi-cpu"></i></div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">IT & Computers</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Hardware, Systems & Peripherals</div>
                            </div>
                            <i class="bi bi-chevron-right text-muted small"></i>
                        </a>
                    </div>

                    <!-- Furniture Assets -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <a href="view_vendors.php?type=Furniture" class="action-tile h-100">
                            <div class="icon-box me-3" style="background: #FEF3C7; color: #D97706;"><i class="bi bi-lamp"></i></div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Furniture Assets</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Desks, Chairs & Fixtures</div>
                            </div>
                            <i class="bi bi-chevron-right text-muted small"></i>
                        </a>
                    </div>

                    <!-- Electrical Supplies -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <a href="view_vendors.php?type=Electricals" class="action-tile h-100">
                            <div class="icon-box me-3" style="background: #ECFDF5; color: #059669;"><i class="bi bi-lightning-charge"></i></div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Electrical Supplies</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Wiring, Components & Power</div>
                            </div>
                            <i class="bi bi-chevron-right text-muted small"></i>
                        </a>
                    </div>

                    <!-- Service Quick Callout -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="promo-card p-3 h-100 d-flex align-items-center">
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <div>
                                    <div class="fw-bold small">Service Maintenance</div>
                                    <div class="opacity-75" style="font-size: 0.75rem;">Review equipment logs</div>
                                </div>
                                <a href="../services/view_services.php" class="btn btn-light btn-sm fw-semibold rounded-2 px-3 py-1 flex-shrink-0 ms-2" style="font-size: 0.75rem;">View Logs</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Section -->
    <div class="row">
        <div class="col-12">
            <div class="saas-card">
                <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center bg-white">
                    <div>
                        <h6 class="fw-bold mb-0">Top Suppliers by Volume</h6>
                        <small class="text-muted">Ranked by total financial contribution across inventories</small>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table saas-table mb-0">
                        <thead>
                            <tr>
                                <th>Vendor Details</th>
                                <th>Category</th>
                                <th class="text-center">Order Volume</th>
                                <th class="text-end" style="width: 250px;">Total Outlay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($perf_result->num_rows > 0): ?>
                                <?php while($v = $perf_result->fetch_assoc()): 
                                    $pct = round(($v['total_spend'] / $max_spend) * 100);
                                    
                                    // Badge Style Logic
                                    $cat_class = 'badge-default';
                                    if (stristr($v['category'], 'Computer')) $cat_class = 'badge-comp';
                                    elseif (stristr($v['category'], 'Furniture')) $cat_class = 'badge-furn';
                                    elseif (stristr($v['category'], 'Electrical')) $cat_class = 'badge-elec';
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center me-3 fw-bold text-primary flex-shrink-0" style="width: 38px; height: 38px; font-size: 0.85rem;">
                                                <?= strtoupper(substr($v['vendor_name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark mb-0" style="font-size: 0.875rem;"><?= htmlspecialchars($v['vendor_name']) ?></div>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <i class="bi bi-telephone me-1"></i><?= $v['phone_number'] ?: 'No Phone' ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-category <?= $cat_class ?>"><?= htmlspecialchars($v['category']) ?></span>
                                    </td>
                                    <td class="text-center fw-medium" style="font-size: 0.875rem;">
                                        <span class="badge bg-light text-dark border px-2 py-1"><?= $v['total_orders'] ?> Units</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold text-dark" style="font-size: 0.875rem;">₹<?= number_format($v['total_spend'], 2) ?></div>
                                        <div class="progress mt-1 ms-auto" style="height: 4px; width: 100px; background-color: #F1F5F9;">
                                            <div class="progress-bar" style="width: <?= $pct ?>%; background-color: var(--saas-primary);"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No vendor records currently available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const chartEl = document.getElementById('spendChart');
    if (chartEl) {
        const ctx = chartEl.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.25)');
        gradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    label: 'Procurement Volume',
                    data: [12, 19, 15, 25, 22, 30, 45],
                    borderColor: '#4F46E5',
                    borderWidth: 2,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#4F46E5',
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    y: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 11 } } }
                }
            }
        });
    }
});
</script>

<?php 
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>