<?php
include "../config/db.php";
include "../includes/session.php";

// 1. FINANCIAL OVERVIEW
$stock_stats = $conn->query("
    SELECT 
        SUM(amount) as total_inventory_value,
        COUNT(*) as total_records
    FROM stock_details
")->fetch_assoc();

// 2. OPERATIONAL VELOCITY (Last 30 Days)
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

// 4. CATEGORY DIVERSITY (Replacement for Stock Health)
$cat_stats = $conn->query("
    SELECT COUNT(DISTINCT category) as cat_count FROM items_master
")->fetch_assoc();

ob_start();
?>
<style>
    :root {
        --glass-bg: #ffffff;
        --accent-purple: #8b5cf6;
        --accent-emerald: #10b981;
    }
    .glass-card {
        background: var(--glass-bg);
        border: 1px solid #ebeef2;
        border-radius: 1.25rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05);
    }
    .metric-value { font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
    .metric-label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; }
    .bg-purple-subtle { background-color: #f5f3ff; color: #7c3aed; }
    .text-purple { color: #7c3aed; }
    .bg-emerald-subtle { background-color: #ecfdf5; color: #059669; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h4 class="fw-bold mb-1">Executive Asset Overview</h4>
            <p class="text-muted small mb-0"><i class="bi bi-shield-check me-1"></i> Centralized Inventory & Dispatch Monitoring</p>
        </div>
        <div class="d-flex gap-2">
            <a href="view_stock_details.php" class="btn btn-white border btn-sm rounded-pill px-3 shadow-sm">Stock Master</a>
            <a href="dispatch_report.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">Dispatch Logs</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="glass-card p-4 shadow-sm h-100 border-start border-4 border-primary">
            <div class="metric-label">Procurement Value</div>
            <div class="metric-value">₹<?= number_format($stock_stats['total_inventory_value'] / 100000, 2) ?>L</div>
            <div class="text-muted small mt-2">
                <i class="bi bi-wallet2 me-1"></i> Cumulative procurement across all institutions
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="glass-card p-4 shadow-sm h-100 border-start border-4 border-success">
            <div class="metric-label">30-Day Movement</div>
            <div class="metric-value text-success"><?= number_format($velocity['moved_items'] ?? 0) ?> <span class="fs-6 fw-normal text-muted">Units</span></div>
            <div class="text-muted small mt-2">
                <i class="bi bi-truck me-1"></i> Successful institutional allocations
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="glass-card p-4 shadow-sm h-100 border-start border-4 border-info">
            <div class="metric-label">Total PCs Deployed</div>
            <div class="metric-value"><?= number_format($comp_stats['total_comps'] ?? 0) ?></div>
            <div class="text-muted small mt-2">
                <i class="bi bi-display me-1"></i> Total PCs operational in institutions
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="glass-card p-4 shadow-sm h-100 border-start border-4 border-warning">
            <div class="metric-label">Item Categories</div>
            <div class="metric-value"><?= $cat_stats['cat_count'] ?> <span class="fs-6 fw-normal text-muted">Types</span></div>
            <div class="text-muted small mt-2">
                <i class="bi bi-tags me-1"></i> Registered asset classifications
            </div>
        </div>
    </div>
</div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-card shadow-sm p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold mb-0">Distribution by Institution</h6>
                    <span class="badge bg-light text-dark border fw-normal">Top 5 by Computer Count</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr class="small text-uppercase text-muted border-bottom">
                                <th class="py-3 border-0">Institution</th>
                                <th class="py-3 text-center border-0">Total Qty</th>
                                <th class="py-3 text-center border-0">Computers</th>
                                <th class="py-3 border-0">PC Density</th>
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
                                <td class="fw-bold"><?= $row['institution_name'] ?></td>
                                <td class="text-center"><?= number_format($row['total_assets']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-purple-subtle text-purple px-3"><?= number_format($row['computer_count']) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; border-radius: 10px;">
                                            <div class="progress-bar bg-purple" style="width: <?= $perc ?>%"></div>
                                        </div>
                                        <span class="small text-muted"><?= round($perc) ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card shadow-sm p-4 mb-4 bg-dark text-white border-0">
                <h6 class="fw-bold mb-2 text-primary">Quick Dispatch</h6>
                <p class="text-white-50 small mb-4">Allocate assets to units. Ensures digital paper trail for every unit.</p>
                <a href="dispatch.php" class="btn btn-primary w-100 rounded-pill">New Transfer</a>
            </div>

            <div class="glass-card shadow-sm p-4">
                <h6 class="fw-bold mb-4">Physical Stock Inflow</h6>
                <?php
                $recent_entry = $conn->query("
                    SELECT im.item_name, sd.quantity, sd.id 
                    FROM stock_details sd 
                    JOIN items_master im ON sd.stock_item_id = im.id 
                    ORDER BY sd.id DESC LIMIT 4
                ");
                while($rs = $recent_entry->fetch_assoc()):
                ?>
                <div class="d-flex align-items-center mb-4">
                    <div class="icon-box bg-emerald-subtle flex-shrink-0">
                        <i class="bi bi-plus-circle-fill"></i>
                    </div>
                    <div class="ms-3 w-100">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold text-dark small"><?= htmlspecialchars($rs['item_name']) ?></span>
                            <span class="small text-muted">x<?= $rs['quantity'] ?></span>
                        </div>
                        <div class="text-muted small" style="font-size: 0.7rem;">Ref: #STK-<?= str_pad($rs['id'], 4, '0', STR_PAD_LEFT) ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>