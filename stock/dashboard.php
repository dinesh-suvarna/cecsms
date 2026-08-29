<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";
require_once __DIR__ . "/../includes/functions.php";

// 1. FINANCIAL OVERVIEW
$stock_stats = $conn->query("
    SELECT 
        SUM(amount) as total_inventory_value,
        COUNT(*) as total_records
    FROM stock_details
")->fetch_assoc();

// 2. OPERATIONAL VELOCITY 
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
$velocity = $conn->query("
    SELECT SUM(dd.quantity) as moved_items 
    FROM dispatch_details dd
    JOIN dispatch_master dm ON dd.dispatch_id = dm.id
    WHERE dm.dispatch_date >= '$thirty_days_ago'
")->fetch_assoc();

// 3. COMPUTER CATEGORY SPECIFIC
$comp_stats = $conn->query("
    SELECT SUM(dd.quantity) as total_comps
    FROM dispatch_details dd
    JOIN stock_details sd ON dd.stock_detail_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    WHERE im.category = 'Computer'
")->fetch_assoc();

// 4. CATEGORY DIVERSITY 
$cat_stats = $conn->query("
    SELECT COUNT(DISTINCT category) as cat_count FROM items_master
")->fetch_assoc();

$page_title = "Executive Asset Overview";
$page_icon  = "bi-shield-check";

ob_start();
?>

<style>
/* Enterprise UI Theme Tokens */
:root {
    --erp-navy: #123b63;
    --erp-navy-dark: #0b2942;
    --erp-text: #1e293b;
    --erp-muted: #64748b;
    --erp-border: #e2e8f0;
    --erp-bg: #f8fafc;
    --erp-card-bg: #ffffff;
    --erp-shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    
    /* Semantic Colors */
    --erp-success: #10b981;
    --erp-success-bg: #ecfdf5;
    --erp-warning: #f59e0b;
    --erp-warning-bg: #fffbeb;
    --erp-info: #0284c7;
    --erp-info-bg: #f0f9ff;
    --erp-purple: #7c3aed;
    --erp-purple-bg: #f5f3ff;
}

.inst-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding-bottom: 16px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--erp-border, #e2e8f0);
}

.inst-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.inst-header-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #edf3f8;
    border: 1px solid #dce6ee;
    border-radius: 6px;
    color: var(--erp-navy, #123b63);
    font-size: 1.15rem;
    flex-shrink: 0;
}

.erp-dashboard-page {
    max-width: 1600px;
    margin: 0 auto;
    padding: 10px 28px 36px; /* Reduced top padding to tighten navbar space */
    font-variant-numeric: tabular-nums;
}

/* Enterprise Metric Cards */
.erp-card {
    background: var(--erp-card-bg);
    border: 1px solid var(--erp-border);
    border-radius: 8px;
    box-shadow: var(--erp-shadow-sm);
    transition: border-color 0.2s ease;
}

.erp-card:hover {
    border-color: #cbd5e1;
}

.metric-card {
    padding: 1.1rem 1.25rem;
    position: relative;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

/* Left Accent Border Instead of Top Line */
.metric-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 4px;
}

.metric-card.primary::after { background-color: var(--erp-navy); }
.metric-card.success::after { background-color: var(--erp-success); }
.metric-card.info::after { background-color: var(--erp-info); }
.metric-card.warning::after { background-color: var(--erp-warning); }

.metric-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.metric-title {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--erp-muted);
}

.metric-value-lg {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--erp-navy-dark);
    line-height: 1.2;
}

.metric-divider {
    border: 0;
    border-top: 1px solid var(--erp-border);
    margin: 0.75rem 0 0.5rem 0;
    opacity: 0.75;
}

.metric-footer {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.73rem;
    color: var(--erp-muted);
}

/* Table Styling */
.erp-table-header {
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--erp-border);
    background: #f8fafc;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.erp-table {
    font-size: 0.82rem;
    margin: 0;
}

.erp-table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--erp-border);
}

.erp-table tbody td {
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--erp-border);
    color: var(--erp-text);
}

.erp-table tbody tr:last-child td {
    border-bottom: none;
}

.erp-table tbody tr:hover {
    background-color: #f1f5f9;
}

/* Micro Components */
.erp-progress-sm {
    height: 6px;
    background-color: #e2e8f0;
    border-radius: 99px;
    overflow: hidden;
}

.erp-progress-bar {
    background-color: var(--erp-purple);
    border-radius: 99px;
    height: 100%;
}

.erp-badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    border: 1px solid transparent;
}
.erp-hover-card {
    transition: all 0.2s ease;
}
.erp-hover-card:hover {
    border-color: var(--erp-navy) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.erp-badge-purple { background: var(--erp-purple-bg); color: var(--erp-purple); border-color: #e9d5ff; }
.erp-badge-slate { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.erp-badge-blue { background: var(--erp-info-bg); color: var(--erp-info); border-color: #bae6fd; }

/* Dark Theme Overrides */
[data-bs-theme="dark"] .erp-dashboard-page {
    --erp-card-bg: #1e293b;
    --erp-border: #334155;
    --erp-navy-dark: #f8fafc;
    --erp-text: #cbd5e1;
    --erp-muted: #94a3b8;
}
[data-bs-theme="dark"] .erp-table-header,
[data-bs-theme="dark"] .erp-table thead th {
    background: #0f172a;
}
[data-bs-theme="dark"] .erp-table tbody tr:hover {
    background-color: #334155;
}
[data-bs-theme="dark"] .erp-progress-sm { background-color: #334155; }
[data-bs-theme="dark"] .metric-divider { border-top-color: var(--erp-border); }
</style>

<div class="erp-dashboard-page">

    <!-- COMMAND & TOOLBAR HEADER WITH BOTTOM BORDER LINE -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi <?= $page_icon ?>"></i>
            </div>
            <div>
                <h4 class="fw-bold tracking-tight mb-1" style="color: var(--erp-navy-dark, #0b2942); letter-spacing: -0.01em;"><?= htmlspecialchars($page_title) ?></h4>
                <p class="text-muted extra-small mb-0">Centralized Inventory Valuation & Institutional Allocation Tracking</p>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <a href="view_stock_details.php" class="btn btn-sm btn-outline-secondary fw-semibold px-3">
                <i class="bi bi-boxes me-1"></i> Stock Registry
            </a>
            <a href="dispatch.php" class="btn btn-sm btn-primary fw-semibold px-3" style="background-color: var(--erp-navy); border-color: var(--erp-navy);">
                <i class="bi bi-plus-lg me-1"></i> New Transfer
            </a>
        </div>
    </div>

    <!-- METRICS GRID -->
    <div class="row g-3 mb-4">
        <!-- Valuation -->
        <div class="col-xl-3 col-md-6">
            <div class="erp-card metric-card primary">
                <div>
                    <div class="metric-header">
                        <span class="metric-title">Valuation (INR)</span>
                        <i class="bi bi-currency-rupee text-muted fs-5"></i>
                    </div>
                    <div class="metric-value-lg">
                        <?= inr($stock_stats['total_inventory_value'] / 100000, true) ?> Lakhs
                    </div>
                </div>
                <div>
                    <hr class="metric-divider">
                    <div class="metric-footer">
                        <span>Across <?= inr($stock_stats['total_records']) ?> total ledger entries</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 30-Day Velocity -->
        <div class="col-xl-3 col-md-6">
            <div class="erp-card metric-card success">
                <div>
                    <div class="metric-header">
                        <span class="metric-title">30-Day Operational Movement</span>
                        <i class="bi bi-arrow-up-right-circle text-success fs-5"></i>
                    </div>
                    <div class="metric-value-lg">
                        <?= inr($velocity['moved_items'] ?? 0) ?> <span class="fs-6 text-muted fw-normal">Units</span>
                    </div>
                </div>
                <div>
                    <hr class="metric-divider">
                    <div class="metric-footer">
                        <span>Dispatched in last 30 days</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deployed Workstations -->
        <div class="col-xl-3 col-md-6">
            <div class="erp-card metric-card info">
                <div>
                    <div class="metric-header">
                        <span class="metric-title">PC Units Deployed</span>
                        <i class="bi bi-pc-display text-info fs-5"></i>
                    </div>
                    <div class="metric-value-lg">
                        <?= inr($comp_stats['total_comps'] ?? 0) ?> <span class="fs-6 text-muted fw-normal">Active</span>
                    </div>
                </div>
                <div>
                    <hr class="metric-divider">
                    <div class="metric-footer">
                        <span>Allocated desktop systems</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Asset Classes -->
        <div class="col-xl-3 col-md-6">
            <div class="erp-card metric-card warning">
                <div>
                    <div class="metric-header">
                        <span class="metric-title">Asset Classifications</span>
                        <i class="bi bi-tags text-warning fs-5"></i>
                    </div>
                    <div class="metric-value-lg">
                        <?= $cat_stats['cat_count'] ?> <span class="fs-6 text-muted fw-normal">Categories</span>
                    </div>
                </div>
                <div>
                    <hr class="metric-divider">
                    <div class="metric-footer">
                        <span>Active item master types</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN PANELS -->
    <div class="row g-3 align-items-stretch mb-3">
        <!-- INSTITUTION DISPATCH DISTRIBUTION TABLE -->
        <div class="col-lg-8">
            <div class="erp-card h-100">
                <div class="erp-table-header">
                    <div class="fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                        <i class="bi bi-building text-secondary"></i> Top Institutional Allocations
                    </div>
                    <span class="erp-badge erp-badge-slate">Ranked by PC Density</span>
                </div>
                <div class="table-responsive">
                    <table class="table erp-table align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Institution Name</th>
                                <th class="text-center">Total Assets</th>
                                <th class="text-center">PC Systems</th>
                                <th class="pe-4" style="width: 35%;">Computer Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $top_inst = $conn->query("
                                SELECT i.institution_name, 
                                SUM(dd.quantity) as total_assets,
                                SUM(CASE WHEN im.category = 'Computer' THEN dd.quantity ELSE 0 END) as computer_count
                                FROM dispatch_master dm
                                JOIN institutions i ON dm.institution_id = i.id
                                JOIN dispatch_details dd ON dm.id = dd.dispatch_id
                                JOIN stock_details sd ON dd.stock_detail_id = sd.id
                                JOIN items_master im ON sd.stock_item_id = im.id
                                GROUP BY i.id 
                                ORDER BY computer_count DESC LIMIT 5
                            ");
                            if ($top_inst && $top_inst->num_rows > 0):
                                while($row = $top_inst->fetch_assoc()):
                                    $perc = ($row['total_assets'] > 0) ? ($row['computer_count'] / $row['total_assets']) * 100 : 0;
                            ?>
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-bank text-muted"></i>
                                        <?= htmlspecialchars($row['institution_name']) ?>
                                    </div>
                                </td>
                                <td class="text-center fw-medium"><?= inr($row['total_assets']) ?></td>
                                <td class="text-center">
                                    <span class="erp-badge erp-badge-purple"><?= inr($row['computer_count']) ?></span>
                                </td>
                                <td class="pe-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="erp-progress-sm flex-grow-1">
                                            <div class="erp-progress-bar" style="width: <?= $perc ?>%"></div>
                                        </div>
                                        <span class="fw-semibold text-muted" style="font-size: 0.72rem; min-width: 35px; text-align: right;"><?= round($perc) ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No institutional asset allocations registered.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDEBAR ACTION CENTER (MATCHED HEIGHT) -->
        <div class="col-lg-4">
            <div class="erp-card p-4 text-white h-100 d-flex flex-column justify-content-between" style="background: var(--erp-navy-dark);">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle extra-small fw-bold px-2 py-1">Action Center</span>
                        <i class="bi bi-send-check text-white-50 fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Dispatch Assets</h5>
                    <p class="text-white-50 small mb-0" style="line-height: 1.5;">
                        Initiate immediate stock transfer vouchers to department divisions or external institutional units with real-time audit logging.
                    </p>
                </div>
                <div class="pt-3">
                    <a href="dispatch.php" class="btn btn-light w-100 fw-bold text-dark d-flex align-items-center justify-content-center gap-2 py-2">
                        <i class="bi bi-box-arrow-up-right"></i> Process Voucher Transfer
                    </a>
                </div>
            </div>
        </div>
    </div>
<!-- HORIZONTAL QUICK LINKS PANEL -->
    <div class="row">
        <div class="col-12">
            <div class="erp-card">
                <div class="erp-table-header">
                    <div class="fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                        <i class="bi bi-lightning-charge text-warning fs-6"></i> System Navigation & Quick Actions
                    </div>
                </div>
                <div class="p-3">
                    <div class="row g-3 align-items-stretch">
                        <!-- Asset Registry -->
                        <div class="col-xl-3 col-md-6">
                            <a href="items_master.php" class="d-flex align-items-center justify-content-between p-3 rounded text-decoration-none border erp-hover-card bg-white h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-2 bg-primary-subtle text-primary flex-shrink-0" style="width: 42px; height: 42px;">
                                        <i class="bi bi-journal-text fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.83rem; line-height: 1.2;">Asset Registry</div>
                                        <div class="text-muted extra-small mt-1">Item Master Directory</div>
                                    </div>
                                </div>
                                <i class="bi bi-arrow-right text-muted flex-shrink-0 ms-2"></i>
                            </a>
                        </div>

                        <!-- Device Configurations -->
                        <div class="col-xl-3 col-md-6">
                            <a href="stock_specifications.php" class="d-flex align-items-center justify-content-between p-3 rounded text-decoration-none border erp-hover-card bg-white h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-2 bg-info-subtle text-info flex-shrink-0" style="width: 42px; height: 42px;">
                                        <i class="bi bi-cpu fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.83rem; line-height: 1.2;">Device Configurations</div>
                                        <div class="text-muted extra-small mt-1">Hardware Specs</div>
                                    </div>
                                </div>
                                <i class="bi bi-arrow-right text-muted flex-shrink-0 ms-2"></i>
                            </a>
                        </div>

                        <!-- Add Stock Details -->
                        <div class="col-xl-3 col-md-6">
                            <a href="add_stock_details.php" class="d-flex align-items-center justify-content-between p-3 rounded text-decoration-none border erp-hover-card bg-white h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-2 bg-success-subtle text-success flex-shrink-0" style="width: 42px; height: 42px;">
                                        <i class="bi bi-plus-circle fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.83rem; line-height: 1.2;">Add Stock Details</div>
                                        <div class="text-muted extra-small mt-1">Inventory Inflow</div>
                                    </div>
                                </div>
                                <i class="bi bi-arrow-right text-muted flex-shrink-0 ms-2"></i>
                            </a>
                        </div>

                        <!-- View Stock Details -->
                        <div class="col-xl-3 col-md-6">
                            <a href="view_stock_details.php" class="d-flex align-items-center justify-content-between p-3 rounded text-decoration-none border erp-hover-card bg-white h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-2 bg-warning-subtle text-warning flex-shrink-0" style="width: 42px; height: 42px;">
                                        <i class="bi bi-boxes fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.83rem; line-height: 1.2;">View Stock Details</div>
                                        <div class="text-muted extra-small mt-1">Inventory Records</div>
                                    </div>
                                </div>
                                <i class="bi bi-arrow-right text-muted flex-shrink-0 ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
           
</div>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>