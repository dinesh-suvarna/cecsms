<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";

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
    --erp-navy: #173f63;
    --erp-navy-dark: #102f4a;
    --erp-text: #263746;
    --erp-muted: #71808f;
    --erp-border: #dce3e9;
    --erp-bg: #f5f7f9;
    --erp-white: #ffffff;
    --erp-shadow: 0 1px 3px rgba(20, 40, 60, .06);
    --erp-accent-green: #10b981;
    --erp-accent-blue: #3b82f6;
    --erp-accent-purple: #8b5cf6;
    --erp-accent-amber: #f59e0b;
}

/* Page Container */
.erp-dashboard-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 20px 40px;
}

/* Header Styling */
.inst-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding-bottom: 20px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--erp-border);
}
.inst-header-left { display: flex; align-items: center; gap: 14px; }
.inst-header-icon {
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    background: #edf3f8; border: 1px solid #dce6ee; border-radius: 5px;
    color: var(--erp-navy); font-size: 1.1rem;
}
.inst-header h3 { margin: 0; color: var(--erp-navy-dark); font-size: 1.18rem; font-weight: 650; }
.inst-header p { margin: 3px 0 0; color: var(--erp-muted); font-size: .76rem; }

/* Panels */
.inst-panel {
    background: #ffffff; border: 1px solid var(--erp-border); border-radius: 5px;
    box-shadow: var(--erp-shadow);
}
.inst-panel-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 18px; border-bottom: 1px solid var(--erp-border); background: #f5f7f9;
}
.inst-panel-title { color: var(--erp-navy-dark); font-size: .82rem; font-weight: 650; }

/* Metric Cards */
.metric-box {
    background: #fff; border: 1px solid var(--erp-border); border-radius: 5px;
    padding: 16px 18px; display: flex; flex-direction: column; justify-content: space-between;
    box-shadow: var(--erp-shadow); height: 100%; position: relative; overflow: hidden;
}
.metric-box::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
}
.metric-box.primary::before { background-color: var(--erp-navy); }
.metric-box.success::before { background-color: var(--erp-accent-green); }
.metric-box.info::before { background-color: var(--erp-accent-blue); }
.metric-box.warning::before { background-color: var(--erp-accent-amber); }

.metric-label {
    font-size: .65rem; font-weight: 700; color: var(--erp-muted);
    text-transform: uppercase; letter-spacing: 0.04em;
}
.metric-value {
    font-size: 1.45rem; font-weight: 700; color: var(--erp-navy-dark); margin: 6px 0 4px;
}
.metric-sub {
    font-size: .72rem; color: var(--erp-muted); display: flex; align-items: center; gap: 4px;
}

/* Buttons */
.btn-erp-primary {
    height: 34px; background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-erp-primary:hover { background: var(--erp-navy-dark); color: #fff; }

.btn-erp-cancel {
    height: 34px; border: 1px solid #c8d2db; background: #fff;
    color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-erp-cancel:hover { background: #f5f7f9; color: #334451; }

/* Tables & ERP Elements */
.table-erp { font-size: .78rem; margin: 0; }
.table-erp thead th {
    background: #f5f7f9; color: #536575; font-size: .65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--erp-border);
    padding: 10px 16px;
}
.table-erp tbody td { padding: 10px 16px; border-bottom: 1px solid var(--erp-border); vertical-align: middle; }

.badge-erp { font-size: .65rem; font-weight: 600; padding: 3px 8px; border-radius: 4px; display: inline-block; }
.badge-erp-purple { background: #f3e8ff; color: #6b21a8; border: 1px solid #e9d5ff; }
.badge-erp-neutral { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.badge-erp-info { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

.progress-erp { height: 6px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; }
.progress-bar-erp { background-color: var(--erp-accent-purple); height: 100%; }

.action-card-dark {
    background: var(--erp-navy-dark); color: #fff; border-radius: 5px; padding: 18px; border: 1px solid var(--erp-navy);
}

/* Dark Mode Overrides */
[data-bs-theme="dark"] {
    --erp-bg: #101a24;
    --erp-white: #172534;
    --erp-text: #edf3f7;
    --erp-muted: #9aabb9;
    --erp-border: #2d3e4e;
    --erp-navy: #8eafc9;
    --erp-navy-dark: #dce8f0;
}
[data-bs-theme="dark"] .inst-header h3 { color: #edf3f7; }
[data-bs-theme="dark"] .inst-header-icon { background: #203445; border-color: #33495a; color: #b8d0e2; }
[data-bs-theme="dark"] .inst-panel,
[data-bs-theme="dark"] .metric-box { background: #142230 !important; }
[data-bs-theme="dark"] .inst-panel-header { background: #101a24; border-color: var(--erp-border); }
[data-bs-theme="dark"] .table-erp thead th { background: #101a24; border-color: var(--erp-border); color: var(--erp-muted); }
[data-bs-theme="dark"] .table-erp tbody td { border-color: var(--erp-border); color: var(--erp-text); }
[data-bs-theme="dark"] .btn-erp-cancel { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
[data-bs-theme="dark"] .action-card-dark { background: #172534; border-color: var(--erp-border); }
[data-bs-theme="dark"] .progress-erp { background-color: #2d3e4e; }
</style>

<div class="erp-dashboard-page">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi <?= $page_icon ?>"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($page_title) ?></h3>
                <p>Centralized Inventory Management & Institutional Allocation Tracking</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="view_stock_details.php" class="btn btn-erp-cancel px-3">
                <i class="bi bi-boxes me-1"></i> Stock Master
            </a>
            <a href="dispatch_report.php" class="btn btn-erp-primary px-3">
                <i class="bi bi-file-earmark-text me-1"></i> Dispatch Logs
            </a>
        </div>
    </div>

    <!-- METRICS GRID -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="metric-box primary">
                <div>
                    <span class="metric-label">Procurement Value</span>
                    <div class="metric-value">₹<?= number_format($stock_stats['total_inventory_value'] / 100000, 2) ?>L</div>
                </div>
                <div class="metric-sub">
                    <i class="bi bi-wallet2"></i> Cumulative valuation across units
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="metric-box success">
                <div>
                    <span class="metric-label">30-Day Movement</span>
                    <div class="metric-value"><?= number_format($velocity['moved_items'] ?? 0) ?> <span class="fs-6 text-muted fw-normal">Units</span></div>
                </div>
                <div class="metric-sub text-success">
                    <i class="bi bi-truck"></i> Total institutional allocations
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="metric-box info">
                <div>
                    <span class="metric-label">Total PCs Deployed</span>
                    <div class="metric-value"><?= number_format($comp_stats['total_comps'] ?? 0) ?></div>
                </div>
                <div class="metric-sub">
                    <i class="bi bi-display"></i> Operational computers in field
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="metric-box warning">
                <div>
                    <span class="metric-label">Item Categories</span>
                    <div class="metric-value"><?= $cat_stats['cat_count'] ?> <span class="fs-6 text-muted fw-normal">Types</span></div>
                </div>
                <div class="metric-sub">
                    <i class="bi bi-tags"></i> Registered asset classifications
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN BODY SECTION -->
    <div class="row g-4">
        <!-- DISTRIBUTION TABLE -->
        <div class="col-lg-8">
            <div class="inst-panel h-100">
                <div class="inst-panel-header">
                    <div class="inst-panel-title">
                        <i class="bi bi-diagram-3 me-2 text-secondary"></i>Distribution by Institution
                    </div>
                    <span class="badge-erp badge-erp-neutral">Top 5 by Computer Count</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-erp table-hover align-middle">
                        <thead>
                            <tr>
                                <th class="ps-3">Institution</th>
                                <th class="text-center">Total Qty</th>
                                <th class="text-center">Computers</th>
                                <th style="width: 35%;">PC Density Ratio</th>
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
                            while($row = $top_inst->fetch_assoc()):
                                $perc = ($row['total_assets'] > 0) ? ($row['computer_count'] / $row['total_assets']) * 100 : 0;
                            ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-dark"><?= htmlspecialchars($row['institution_name']) ?></td>
                                <td class="text-center fw-medium"><?= number_format($row['total_assets']) ?></td>
                                <td class="text-center">
                                    <span class="badge-erp badge-erp-purple"><?= number_format($row['computer_count']) ?></span>
                                </td>
                                <td class="pe-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress-erp flex-grow-1">
                                            <div class="progress-bar-erp" style="width: <?= $perc ?>%"></div>
                                        </div>
                                        <span class="fw-semibold text-muted" style="font-size: .7rem; min-width: 32px;"><?= round($perc) ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT ACTION & ANALYTICAL PANELS -->
        <div class="col-lg-4">
            <!-- QUICK DISPATCH ACTION -->
            <div class="action-card-dark mb-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-box-arrow-up-right text-info"></i>
                    <h6 class="fw-bold mb-0" style="font-size: .88rem;">Quick Asset Transfer</h6>
                </div>
                <p class="text-muted small mb-3" style="font-size: .75rem; color: #9aabb9 !important;">
                    Allocate new assets directly to target institutions and maintain a digital paper trail.
                </p>
                <a href="dispatch.php" class="btn btn-erp-primary w-100">
                    <i class="bi bi-plus-lg me-1"></i> New Dispatch Transfer
                </a>
            </div>

            <!-- CATEGORY DISTRIBUTION BREAKDOWN -->
            <div class="inst-panel">
                <div class="inst-panel-header">
                    <div class="inst-panel-title">
                        <i class="bi bi-pie-chart me-2 text-primary"></i>Category Asset Share
                    </div>
                </div>
                <div class="p-3">
                    <?php
                    $cat_distribution = $conn->query("
                        SELECT im.category, SUM(dd.quantity) as total_qty
                        FROM dispatch_details dd
                        JOIN stock_details sd ON dd.stock_detail_id = sd.id
                        JOIN items_master im ON sd.stock_item_id = im.id
                        GROUP BY im.category
                        ORDER BY total_qty DESC LIMIT 4
                    ");
                    if($cat_distribution && $cat_distribution->num_rows > 0):
                        while($cat = $cat_distribution->fetch_assoc()):
                    ?>
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div>
                            <div class="fw-bold text-dark" style="font-size: .78rem;"><?= htmlspecialchars($cat['category']) ?></div>
                            <span class="text-muted" style="font-size: .68rem;">Allocated Dispatches</span>
                        </div>
                        <span class="badge-erp badge-erp-info"><?= number_format($cat['total_qty']) ?> Units</span>
                    </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div class="text-center text-muted py-3" style="font-size: .78rem;">No categories dispatch data found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>