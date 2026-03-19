<?php
include "../config/db.php";
include "../includes/session.php";

// 1. STOCK OVERVIEW (From stock_details)
$stock_stats = $conn->query("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN status != 'dispatched' THEN 1 ELSE 0 END) as available_serial_items,
        SUM(CASE WHEN status = 'dispatched' THEN 1 ELSE 0 END) as fully_dispatched_items,
        SUM(amount) as total_inventory_value
    FROM stock_details
")->fetch_assoc();

// 2. DISPATCH ANALYTICS (Hierarchical)
$dispatch_stats = $conn->query("
    SELECT 
        COUNT(DISTINCT institution_id) as active_institutions,
        COUNT(id) as total_dispatch_events,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as total_returns
    FROM dispatch_master
")->fetch_assoc();

// 3. COMPUTER CATEGORY SPECIFIC (Using your category logic)
$comp_stats = $conn->query("
    SELECT SUM(dd.quantity) as total_comps
    FROM dispatch_details dd
    JOIN stock_details sd ON dd.stock_detail_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    WHERE im.category = 'Computer'
")->fetch_assoc();

ob_start();
?>
<style>
    .glass-card {
        background: #ffffff;
        border: 1px solid #ebeef2;
        border-radius: 1.25rem;
        transition: all 0.3s ease;
    }
    .glass-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -8px rgba(0,0,0,0.1);
    }
    .metric-value { font-size: 1.8rem; font-weight: 700; color: #1e293b; }
    .metric-label { font-size: 0.85rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .icon-box { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">System Executive Overview</h4>
            <p class="text-muted small">Tracking Asset Lifecycle from Procurement to Dispatch</p>
        </div>
        <div class="btn-group">
            <a href="view_stock_details.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 me-2">Stock Master</a>
            <a href="dispatch_report.php" class="btn btn-primary btn-sm rounded-pill px-3">Dispatch Logs</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="glass-card p-4 shadow-sm h-100">
                <div class="d-flex justify-content-between mb-3">
                    <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-currency-dollar fs-4"></i></div>
                    <span class="badge bg-success-subtle text-success rounded-pill h-50">Active</span>
                </div>
                <div class="metric-label">Inventory Value</div>
                <div class="metric-value">₹<?= number_format($stock_stats['total_inventory_value'] / 100000, 2) ?>L</div>
                <div class="progress mt-3" style="height: 5px;">
                    <div class="progress-bar bg-primary" style="width: 75%"></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="glass-card p-4 shadow-sm h-100">
                <div class="d-flex justify-content-between mb-3">
                    <div class="icon-box bg-success-subtle text-success"><i class="bi bi-truck fs-4"></i></div>
                    <span class="badge bg-info-subtle text-info rounded-pill h-50"><?= $dispatch_stats['total_dispatch_events'] ?> Trx</span>
                </div>
                <div class="metric-label">Dispatched Assets</div>
                <div class="metric-value"><?= number_format($stock_stats['fully_dispatched_items']) ?></div>
                <p class="text-muted small mt-2 mb-0">Units currently off-site</p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="glass-card p-4 shadow-sm h-100">
                <div class="d-flex justify-content-between mb-3">
                    <div class="icon-box bg-purple-subtle text-purple"><i class="bi bi-cpu fs-4"></i></div>
                    <i class="bi bi-info-circle text-muted" title="Total Computers in Units"></i>
                </div>
                <div class="metric-label">Computers Deployed</div>
                <div class="metric-value"><?= number_format($comp_stats['total_comps'] ?? 0) ?></div>
                <p class="text-muted small mt-2 mb-0">Across all institutions</p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="glass-card p-4 shadow-sm h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between mb-3">
                    <div class="icon-box bg-warning-subtle text-warning"><i class="bi bi-archive fs-4"></i></div>
                    <span class="text-warning fw-bold small">Ready</span>
                </div>
                <div class="metric-label">Warehouse Availability</div>
                <div class="metric-value"><?= number_format($stock_stats['available_serial_items']) ?></div>
                <p class="text-muted small mt-2 mb-0">Items available for dispatch</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-card shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold mb-0">Institution Distribution (Top 5)</h6>
                    <span class="text-muted small">By Computer Count</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Institution</th>
                                <th>Total Assets</th>
                                <th>Computers</th>
                                <th>Utilization</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Query based on your Dispatch Report logic
                            $top_inst = $conn->query("
                                SELECT i.institution_name, COUNT(dd.id) as total_assets,
                                SUM(CASE WHEN im.category = 'Computer' THEN 1 ELSE 0 END) as computer_count
                                FROM dispatch_master dm
                                JOIN institutions i ON dm.institution_id = i.id
                                JOIN dispatch_details dd ON dm.id = dd.dispatch_id
                                JOIN stock_details sd ON dd.stock_detail_id = sd.id
                                JOIN items_master im ON sd.stock_item_id = im.id
                                GROUP BY i.id ORDER BY computer_count DESC LIMIT 5
                            ");
                            while($row = $top_inst->fetch_assoc()):
                                $perc = ($row['total_assets'] > 0) ? ($row['computer_count'] / $row['total_assets']) * 100 : 0;
                            ?>
                            <tr>
                                <td class="fw-bold"><?= $row['institution_name'] ?></td>
                                <td><?= $row['total_assets'] ?></td>
                                <td><span class="badge bg-purple-subtle text-purple"><?= $row['computer_count'] ?></span></td>
                                <td style="width: 150px;">
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-primary" style="width: <?= $perc ?>%"></div>
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
            <div class="glass-card shadow-sm p-4 mb-4 bg-dark">
                <h6 class="text-white fw-bold mb-3">Quick Dispatch</h6>
                <p class="text-white-50 small">Initiate a new transfer of items from Master Stock to a Unit.</p>
                <a href="dispatch.php" class="btn btn-primary w-100 rounded-pill">Create New Dispatch</a>
            </div>

            <div class="glass-card shadow-sm p-4">
                <h6 class="fw-bold mb-3">Recent Stock Inflow</h6>
                <?php
                $recent_stock = $conn->query("
                    SELECT im.item_name, sd.bill_date, sd.quantity 
                    FROM stock_details sd 
                    JOIN items_master im ON sd.stock_item_id = im.id 
                    ORDER BY sd.id DESC LIMIT 3
                ");
                while($rs = $recent_stock->fetch_assoc()):
                ?>
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0 bg-light p-2 rounded text-primary">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="ms-3">
                        <div class="fw-bold small"><?= $rs['item_name'] ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;"><?= date('d M, Y', strtotime($rs['bill_date'])) ?> • <?= $rs['quantity'] ?> Units</div>
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