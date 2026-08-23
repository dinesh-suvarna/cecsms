<?php
session_start();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";

$page_title = "Vendor Intelligence Dashboard";

// Fetch Session Parameters
$role = $_SESSION['role'] ?? '';
$division_id = (int)($_SESSION['division_id'] ?? 0);

// Check if user is restricted to a specific division
$is_restricted = ($role !== 'SuperAdmin' && $division_id > 0);

// ---------------------------------------------------------------------
// 1. Unified Metrics Query
// ---------------------------------------------------------------------
if (!$is_restricted) {
    // SuperAdmin / Global View
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
    $stmt = $conn->prepare($metrics_query);
} else {
    // Division Restricted View
    $metrics_query = "
        SELECT 
            (SELECT COUNT(*) FROM vendors) as total_vendors,
            (SELECT COUNT(DISTINCT vendor_id) FROM (
                SELECT sd.vendor_id 
                FROM stock_details sd
                JOIN dispatch_details dd ON sd.id = dd.stock_detail_id
                JOIN dispatch_master dm ON dd.dispatch_id = dm.id
                WHERE sd.vendor_id IS NOT NULL AND dm.division_id = ?
                
                UNION 
                
                SELECT fs.vendor_id 
                FROM furniture_stock fs
                JOIN units u ON fs.unit_id = u.id
                WHERE fs.vendor_id IS NOT NULL AND u.division_id = ?
                
                UNION 
                
                SELECT es.vendor_id 
                FROM electrical_stock es
                JOIN units u ON es.unit_id = u.id
                WHERE es.vendor_id IS NOT NULL AND u.division_id = ?
            ) as active) as active_vendors,
            (
                SELECT COALESCE(SUM(sd.amount), 0) 
                FROM stock_details sd
                JOIN dispatch_details dd ON sd.id = dd.stock_detail_id
                JOIN dispatch_master dm ON dd.dispatch_id = dm.id
                WHERE dm.division_id = ?
            ) + 
            (
                SELECT COALESCE(SUM(fs.total_qty * fs.unit_price), 0) 
                FROM furniture_stock fs
                JOIN units u ON fs.unit_id = u.id
                WHERE u.division_id = ?
            ) + 
            (
                SELECT COALESCE(SUM(es.total_qty * es.unit_price), 0) 
                FROM electrical_stock es
                JOIN units u ON es.unit_id = u.id
                WHERE u.division_id = ?
            ) as grand_total_spend";
    $stmt = $conn->prepare($metrics_query);
    $stmt->bind_param("iiiiii", $division_id, $division_id, $division_id, $division_id, $division_id, $division_id);
}

$stmt->execute();
$metrics = $stmt->get_result()->fetch_assoc();

// ---------------------------------------------------------------------
// 2. Calculate Max Spend for Progress Bar Calculation
// ---------------------------------------------------------------------
if (!$is_restricted) {
    $max_spend_query = "
        SELECT MAX(total_spend) as max_spend FROM (
            SELECT 
                (COALESCE((SELECT SUM(amount) FROM stock_details WHERE vendor_id = v.id), 0) + 
                 COALESCE((SELECT SUM(total_qty * unit_price) FROM furniture_stock WHERE vendor_id = v.id), 0) + 
                 COALESCE((SELECT SUM(total_qty * unit_price) FROM electrical_stock WHERE vendor_id = v.id), 0)) as total_spend
            FROM vendors v
        ) as spends";
    $max_stmt = $conn->prepare($max_spend_query);
} else {
    $max_spend_query = "
        SELECT MAX(total_spend) as max_spend FROM (
            SELECT 
                (COALESCE((
                    SELECT SUM(sd.amount) 
                    FROM stock_details sd
                    JOIN dispatch_details dd ON sd.id = dd.stock_detail_id
                    JOIN dispatch_master dm ON dd.dispatch_id = dm.id
                    WHERE sd.vendor_id = v.id AND dm.division_id = ?
                ), 0) + 
                 COALESCE((
                    SELECT SUM(fs.total_qty * fs.unit_price) 
                    FROM furniture_stock fs
                    JOIN units u ON fs.unit_id = u.id
                    WHERE fs.vendor_id = v.id AND u.division_id = ?
                ), 0) + 
                 COALESCE((
                    SELECT SUM(es.total_qty * es.unit_price) 
                    FROM electrical_stock es
                    JOIN units u ON es.unit_id = u.id
                    WHERE es.vendor_id = v.id AND u.division_id = ?
                ), 0)) as total_spend
            FROM vendors v
        ) as spends";
    $max_stmt = $conn->prepare($max_spend_query);
    $max_stmt->bind_param("iii", $division_id, $division_id, $division_id);
}

$max_stmt->execute();
$max_spend_res = $max_stmt->get_result()->fetch_assoc();
$max_spend = ($max_spend_res['max_spend'] > 0) ? $max_spend_res['max_spend'] : 1;

// ---------------------------------------------------------------------
// 3. Top Suppliers Performance Data Query
// ---------------------------------------------------------------------
if (!$is_restricted) {
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
    $perf_stmt = $conn->prepare($perf_query);
} else {
    $perf_query = "
        SELECT 
            v.id, v.vendor_name, v.category, v.phone_number, v.email,
            (COALESCE((
                SELECT SUM(sd.amount) 
                FROM stock_details sd
                JOIN dispatch_details dd ON sd.id = dd.stock_detail_id
                JOIN dispatch_master dm ON dd.dispatch_id = dm.id
                WHERE sd.vendor_id = v.id AND dm.division_id = ?
            ), 0) + 
             COALESCE((
                SELECT SUM(fs.total_qty * fs.unit_price) 
                FROM furniture_stock fs
                JOIN units u ON fs.unit_id = u.id
                WHERE fs.vendor_id = v.id AND u.division_id = ?
            ), 0) + 
             COALESCE((
                SELECT SUM(es.total_qty * es.unit_price) 
                FROM electrical_stock es
                JOIN units u ON es.unit_id = u.id
                WHERE es.vendor_id = v.id AND u.division_id = ?
            ), 0)) as total_spend,
            ((SELECT COUNT(*) FROM stock_details sd JOIN dispatch_details dd ON sd.id = dd.stock_detail_id JOIN dispatch_master dm ON dd.dispatch_id = dm.id WHERE sd.vendor_id = v.id AND dm.division_id = ?) + 
             (SELECT COUNT(*) FROM furniture_stock fs JOIN units u ON fs.unit_id = u.id WHERE fs.vendor_id = v.id AND u.division_id = ?) + 
             (SELECT COUNT(*) FROM electrical_stock es JOIN units u ON es.unit_id = u.id WHERE es.vendor_id = v.id AND u.division_id = ?)) as total_orders
        FROM vendors v
        HAVING total_spend > 0 OR total_orders > 0
        ORDER BY total_spend DESC LIMIT 10";
    $perf_stmt = $conn->prepare($perf_query);
    $perf_stmt->bind_param("iiiiii", $division_id, $division_id, $division_id, $division_id, $division_id, $division_id);
}

$perf_stmt->execute();
$perf_result = $perf_stmt->get_result();

ob_start();
?>

<!-- Include Chart.js for Visual Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root {
        --erp-navy: #123b63;
        --erp-navy-dark: #0b2942;
        --erp-bg: #f3f5f7;
        --erp-card-bg: #ffffff;
        --erp-border: #d9e0e7;
        --erp-text-main: #20384d;
        --erp-text-muted: #64748b;
        --erp-shadow-sm: 0 1px 3px rgba(20,45,70,.05);
        --erp-shadow-hover: 0 6px 16px rgba(18,59,99,.08);
    }

    body { 
        background-color: var(--erp-bg); 
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: var(--erp-text-main);
    }

    .erp-card {
        background: var(--erp-card-bg);
        border: 1px solid var(--erp-border);
        border-radius: 8px;
        box-shadow: var(--erp-shadow-sm);
        transition: all 0.2s ease-in-out;
    }

    .metric-card {
        padding: 1.25rem 1.5rem;
    }

    .metric-card:hover { 
        transform: translateY(-2px);
        box-shadow: var(--erp-shadow-hover); 
        border-color: #bbc7d4; 
    }

    .metric-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .erp-table thead th {
        background-color: #f8fafc;
        text-transform: uppercase;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        color: var(--erp-text-muted);
        padding: 0.85rem 1.25rem;
        border-bottom: 1px solid var(--erp-border);
    }

    .erp-table tbody td { 
        padding: 0.9rem 1.25rem; 
        border-bottom: 1px solid var(--erp-border); 
        vertical-align: middle; 
    }

    .erp-table tbody tr:last-child td {
        border-bottom: none;
    }

    .erp-table tbody tr:hover {
        background-color: #f8fafc;
    }

    .badge-category {
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.3em 0.65em;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
    }

    .badge-comp { background: #e0f2fe; color: #0369a1; }
    .badge-furn { background: #fef3c7; color: #92400e; }
    .badge-elec { background: #dcfce7; color: #15803d; }
    .badge-default { background: #f1f5f9; color: #475569; }

    .action-tile {
        background: var(--erp-card-bg);
        border: 1px solid var(--erp-border);
        border-radius: 8px;
        padding: 0.85rem 1rem;
        display: flex;
        align-items: center;
        text-decoration: none;
        color: var(--erp-text-main);
        transition: all 0.2s ease;
        box-shadow: var(--erp-shadow-sm);
    }

    .action-tile:hover { 
        background: #ffffff; 
        border-color: var(--erp-navy);
        color: var(--erp-navy); 
        transform: translateX(3px);
        box-shadow: var(--erp-shadow-hover);
    }

    .icon-box {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f0f4f8;
        color: var(--erp-navy);
    }

    .promo-card {
        background: linear-gradient(135deg, var(--erp-navy) 0%, var(--erp-navy-dark) 100%);
        border-radius: 8px;
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }
</style>

<div class="container-fluid p-0">
    <!-- Header Block -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold tracking-tight mb-1" style="color: var(--erp-text-main); letter-spacing: -0.01em;">Vendor Intelligence Dashboard</h4>
            <p class="text-muted mb-0 extra-small">
                Real-time procurement expenditure, procurement distribution and supplier stats
                <!-- <?= $is_restricted ? ' <span class="badge bg-warning-subtle text-warning border ms-1">Division Restricted</span>' : ' <span class="badge bg-secondary-subtle text-secondary border ms-1">Global Scope</span>' ?> -->
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="vendor_manager.php" class="btn btn-primary shadow-sm rounded-2 px-3 py-2 font-semibold extra-small" style="background: var(--erp-navy); border-color: var(--erp-navy);">
                <i class="bi bi-plus-lg me-1"></i> Add Vendor
            </a>
        </div>
    </div>

    <!-- Key Metrics Grid -->
    <div class="row g-3 mb-4">
        <!-- Registered Vendors -->
        <div class="col-12 col-md-4">
            <div class="erp-card metric-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-uppercase fw-bold text-muted extra-small" style="letter-spacing: 0.05em;">Registered Vendors</span>
                    <div class="metric-icon-box" style="background: #edf3f8; color: var(--erp-navy);">
                        <i class="bi bi-person-vcard"></i>
                    </div>
                </div>
                <div>
                    <h3 class="fw-bold mb-1 text-truncate text-dark" style="letter-spacing: -0.02em;"><?= number_format($metrics['total_vendors']) ?></h3>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill extra-small fw-semibold">
                        <i class="bi bi-check-circle me-1"></i>Active In System
                    </span>
                </div>
            </div>
        </div>

        <!-- Active Suppliers -->
        <div class="col-12 col-md-4">
            <div class="erp-card metric-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-uppercase fw-bold text-muted extra-small" style="letter-spacing: 0.05em;">Active Suppliers</span>
                    <div class="metric-icon-box" style="background: #dcfce7; color: #15803d;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
                <div>
                    <h3 class="fw-bold mb-1 text-truncate text-dark" style="letter-spacing: -0.02em;"><?= number_format($metrics['active_vendors']) ?></h3>
                    <?php 
                        $active_pct = $metrics['total_vendors'] > 0 ? round(($metrics['active_vendors'] / $metrics['total_vendors']) * 100) : 0;
                    ?>
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill extra-small fw-semibold">
                        <?= $active_pct ?>% Vendor Engagement
                    </span>
                </div>
            </div>
        </div>

        <!-- Cumulative Expenditure -->
        <div class="col-12 col-md-4">
            <div class="erp-card metric-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-uppercase fw-bold text-muted extra-small" style="letter-spacing: 0.05em;">Total Expenditure</span>
                    <div class="metric-icon-box" style="background: #e0f2fe; color: #0369a1;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div>
                    <h3 class="fw-bold mb-1 text-truncate text-dark" style="letter-spacing: -0.02em;">₹<?= number_format($metrics['grand_total_spend'], 2) ?></h3>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill extra-small fw-semibold">
                        <?= $is_restricted ? 'Current Division Total' : 'System Wide Total' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Domain Filters -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="erp-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark extra-small text-uppercase" style="letter-spacing: 0.04em;">Filter by Procurement Category</h6>
                </div>
                
                <div class="row g-3">
                    <!-- IT & Computers -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <a href="view_vendors.php?type=Computer" class="action-tile h-100">
                            <div class="icon-box me-3"><i class="bi bi-pc-display"></i></div>
                            <div class="flex-grow-1">
                                <div class="fw-bold extra-small text-dark">IT & Computers</div>
                                <div class="text-muted extra-small" style="font-size: 0.72rem;">Hardware & Peripherals</div>
                            </div>
                            <i class="bi bi-chevron-right text-muted extra-small"></i>
                        </a>
                    </div>

                    <!-- Furniture Assets -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <a href="view_vendors.php?type=Furniture" class="action-tile h-100">
                            <div class="icon-box me-3" style="background: #fef3c7; color: #92400e;"><i class="bi bi-boxes"></i></div>
                            <div class="flex-grow-1">
                                <div class="fw-bold extra-small text-dark">Furniture Assets</div>
                                <div class="text-muted extra-small" style="font-size: 0.72rem;">Desks, Chairs & Fixtures</div>
                            </div>
                            <i class="bi bi-chevron-right text-muted extra-small"></i>
                        </a>
                    </div>

                    <!-- Electrical Supplies -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <a href="view_vendors.php?type=Electricals" class="action-tile h-100">
                            <div class="icon-box me-3" style="background: #dcfce7; color: #15803d;"><i class="bi bi-plug-fill"></i></div>
                            <div class="flex-grow-1">
                                <div class="fw-bold extra-small text-dark">Electrical Supplies</div>
                                <div class="text-muted extra-small" style="font-size: 0.72rem;">Fans, Lights </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted extra-small"></i>
                        </a>
                    </div>

                    <!-- Service Quick Callout -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="promo-card p-3 h-100 d-flex align-items-center">
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <div>
                                    <div class="fw-bold extra-small text-white">Maintenance</div>
                                    <div class="opacity-75 extra-small" style="font-size: 0.7rem;">Service history & logs</div>
                                </div>
                                <a href="../services/view_services.php" class="btn btn-light btn-sm fw-semibold rounded-2 px-2 py-1 flex-shrink-0 ms-2 extra-small">Logs</a>
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
            <div class="erp-card overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Top Suppliers by Procurement Volume</h6>
                        <span class="text-muted extra-small">Ranked by total spend across stock modules</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table erp-table mb-0">
                        <thead>
                            <tr>
                                <th>Vendor Details</th>
                                <th>Category</th>
                                <th class="text-center">Order Units</th>
                                <th class="text-end" style="width: 240px;">Total Expenditure</th>
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
                                            <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center me-3 fw-bold text-primary flex-shrink-0" style="width: 36px; height: 36px; font-size: 0.8rem; border-color: var(--erp-border) !important;">
                                                <?= strtoupper(substr($v['vendor_name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark mb-0 extra-small"><?= htmlspecialchars($v['vendor_name']) ?></div>
                                                <div class="text-muted extra-small" style="font-size: 0.72rem;">
                                                    <i class="bi bi-telephone me-1"></i><?= $v['phone_number'] ?: 'No Contact' ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-category <?= $cat_class ?>"><?= htmlspecialchars($v['category']) ?></span>
                                    </td>
                                    <td class="text-center fw-medium extra-small">
                                        <span class="badge bg-light text-dark border px-2 py-1 rounded-2"><?= number_format($v['total_orders']) ?> Records</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold text-dark extra-small">₹<?= number_format($v['total_spend'], 2) ?></div>
                                        <div class="progress mt-1 ms-auto" style="height: 4px; width: 100px; background-color: #f1f5f9;">
                                            <div class="progress-bar" style="width: <?= $pct ?>%; background-color: var(--erp-navy);"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted extra-small">No vendor records currently available for this selection.</td>
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
        gradient.addColorStop(0, 'rgba(18, 59, 99, 0.25)');
        gradient.addColorStop(1, 'rgba(18, 59, 99, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    label: 'Procurement Volume',
                    data: [12, 19, 15, 25, 22, 30, 45],
                    borderColor: '#123b63',
                    borderWidth: 2,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#123b63',
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } }
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