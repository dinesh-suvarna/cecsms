<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Division Overview";
$page_icon  = "bi-speedometer2";

$division_id = $_SESSION['division_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

/* ================= FETCH ANALYTICS ================= */
$stats_query = "SELECT 
    COUNT(da.id) as total,
    SUM(CASE WHEN da.status = 'assigned' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN da.status LIKE '%_requested' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN da.status = 'under_repair' THEN 1 ELSE 0 END) as in_repair
    FROM division_assets da
    LEFT JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    LEFT JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE 1=1 ";

if ($role !== 'SuperAdmin') {
    $stats_query .= " AND dm.division_id = $division_id";
}

$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total'=>0, 'active'=>0, 'pending'=>0, 'in_repair'=>0];

// 2. Asset Distribution (Categories)
$dist_query = "SELECT im.item_name, COUNT(*) as count 
    FROM division_assets da
    JOIN stock_details sd ON sd.id = da.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    LEFT JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    LEFT JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE da.status = 'assigned' " . ($role !== 'SuperAdmin' ? " AND dm.division_id = $division_id" : "") . "
    GROUP BY im.item_name ORDER BY count DESC";
$distribution = $conn->query($dist_query);

// 3. Pending Lifecycle Requests
$req_query = "SELECT da.division_asset_id, im.item_name, da.status, da.assigned_at
    FROM division_assets da
    JOIN stock_details sd ON sd.id = da.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    LEFT JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    LEFT JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE da.status LIKE '%_requested' " . ($role !== 'SuperAdmin' ? " AND dm.division_id = $division_id" : "") . "
    ORDER BY da.id DESC LIMIT 5";
$recent_requests = $conn->query($req_query);

// 4. Asset Health Metrics
$health_query = "SELECT 
    SUM(CASE WHEN da.status = 'assigned' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN da.status = 'under_repair' THEN 1 ELSE 0 END) as repairing,
    SUM(CASE WHEN da.status IN ('return_requested', 'repair_requested', 'dispose_requested') THEN 1 ELSE 0 END) as outgoing
    FROM division_assets da
    LEFT JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    LEFT JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE 1=1 " . ($role !== 'SuperAdmin' ? " AND dm.division_id = $division_id" : "");
$health_data = $conn->query($health_query)->fetch_assoc();

// 5. Recent Activity Logs - FIXED JOIN (Joining on stock_detail_id is safer)
$log_query = "SELECT al.action_type, al.created_at, im.item_name, al.notes
    FROM asset_logs al
    JOIN stock_details sd ON al.asset_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN division_assets da ON sd.id = da.stock_detail_id
    LEFT JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    LEFT JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE 1=1 " . ($role !== 'SuperAdmin' ? " AND (dm.division_id = $division_id OR al.performed_by = {$_SESSION['user_id']})" : "") . "
    ORDER BY al.created_at DESC LIMIT 5";
$recent_logs = $conn->query($log_query);

ob_start();
?>

<style>
:root {
    --erp-navy: #123b63;
    --erp-navy-dark: #0b2942;
    --erp-blue: #2b628f;
    --erp-green: #3f755e;
    --erp-amber: #9a6b22;
    --erp-red: #9a4a4a;
    --erp-info: #426f8f;
    --erp-bg: #f3f5f7;
    --erp-panel: #ffffff;
    --erp-panel-soft: #f7f9fb;
    --erp-border: #d9e0e7;
    --erp-border-dark: #c6d0da;
    --erp-text: #20384d;
    --erp-text-soft: #526679;
    --erp-muted: #718191;
    --erp-shadow: 0 1px 2px rgba(20,45,70,.06);
    --erp-shadow-hover: 0 4px 12px rgba(20,45,70,.09);
}

.container-fluid {
    max-width: 1440px;
    padding: 28px 32px 36px;
}

.container-fluid > .row:first-child {
    padding-bottom: 17px;
    border-bottom: 1px solid var(--erp-border);
}

.container-fluid > .row:first-child h4 {
    color: var(--erp-navy-dark);
    font-size: 1.2rem;
    font-weight: 650 !important;
}

.container-fluid > .row:first-child h4 span {
    color: var(--erp-navy) !important;
}

.container-fluid > .row:first-child p {
    color: var(--erp-muted) !important;
    font-size: .82rem !important;
    margin-bottom: 0;
}

.dash-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 6px !important;
    background: var(--erp-panel);
    transition: border-color .18s ease, box-shadow .18s ease;
    box-shadow: var(--erp-shadow) !important;
}

.dash-card:hover {
    transform: none;
    border-color: var(--erp-border-dark) !important;
    box-shadow: var(--erp-shadow-hover) !important;
}

.container-fluid .border-start {
    border-left-width: 3px !important;
}

.container-fluid .border-primary { border-color: var(--erp-blue) !important; }
.container-fluid .border-success { border-color: var(--erp-green) !important; }
.container-fluid .border-warning { border-color: var(--erp-amber) !important; }
.container-fluid .border-info { border-color: var(--erp-info) !important; }

.icon-shape {
    width: 42px;
    height: 42px;
    min-width: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    font-size: 1.08rem;
    border: 1px solid rgba(18,59,99,.08);
}

.bg-emerald-soft {
    background-color: #eef5f1 !important;
    color: var(--erp-green) !important;
}

.bg-amber-soft {
    background-color: #f7f2e8 !important;
    color: var(--erp-amber) !important;
}

.bg-blue-soft {
    background-color: #edf3f8 !important;
    color: var(--erp-blue) !important;
}

.bg-light { background-color: #f5f7f9 !important; }

.extra-small { font-size: .7rem; }

.container-fluid .text-success { color: var(--erp-green) !important; }
.container-fluid .text-warning { color: var(--erp-amber) !important; }
.container-fluid .text-info { color: var(--erp-info) !important; }
.container-fluid .text-muted { color: var(--erp-muted) !important; }
.container-fluid .text-dark { color: var(--erp-text) !important; }

.container-fluid .card h4 {
    color: var(--erp-text);
    font-size: 1.35rem;
    font-weight: 650 !important;
}

.container-fluid .row.g-3 > .col-md-4 > .card,
.container-fluid .row.g-3 > .col-md-8 > .card {
    border-radius: 6px !important;
}

.container-fluid .card h6 {
    color: var(--erp-text);
    font-size: .86rem;
    font-weight: 650 !important;
}

.container-fluid .border-top {
    border-color: var(--erp-border) !important;
}

.progress-thin {
    height: 5px;
    border-radius: 2px;
    background-color: #e9eef2;
}

.progress-bar.bg-success {
    background-color: var(--erp-green) !important;
}

#dashTab {
    border: 1px solid var(--erp-border);
    border-radius: 5px !important;
    background: #f5f7f9 !important;
    padding: 3px !important;
}

#dashTab .nav-link {
    color: var(--erp-text-soft);
    border-radius: 3px !important;
    font-size: .76rem !important;
    font-weight: 600 !important;
    padding: 8px 10px !important;
}

#dashTab .nav-link:hover {
    color: var(--erp-navy);
}

#dashTab .nav-link.active {
    background: var(--erp-panel) !important;
    color: var(--erp-navy) !important;
    box-shadow: 0 1px 2px rgba(20,45,70,.08);
}

.timeline-item {
    border-left: 2px solid #dce3e9;
    padding-left: 14px;
    padding-bottom: 16px;
    position: relative;
}

.timeline-item::before {
    content: "";
    position: absolute;
    left: -5px;
    top: 1px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--erp-navy);
    border: 2px solid var(--erp-panel);
    box-shadow: 0 0 0 1px var(--erp-border);
}

.timeline-item strong { color: var(--erp-text); }

.request-item {
    border-left: 3px solid var(--erp-amber);
    padding: 11px 13px;
    background: var(--erp-panel-soft);
    border-top: 1px solid #edf0f3;
    border-right: 1px solid #edf0f3;
    border-bottom: 1px solid #edf0f3;
    border-radius: 0 4px 4px 0;
    margin-bottom: 8px;
}

.request-item .badge {
    border-radius: 3px !important;
    font-weight: 600;
}

.container-fluid a.text-success {
    color: var(--erp-navy) !important;
    letter-spacing: .02em;
}

.container-fluid a.text-success:hover {
    color: var(--erp-blue) !important;
}

.overflow-auto::-webkit-scrollbar {
    width: 5px;
    height: 5px;
}

.overflow-auto::-webkit-scrollbar-thumb {
    background: #cbd5dd;
    border-radius: 3px;
}

[data-bs-theme="dark"] {
    --erp-bg: #101a24;
    --erp-panel: #172534;
    --erp-panel-soft: #1b2b3a;
    --erp-border: #2c3d4d;
    --erp-border-dark: #3d5162;
    --erp-text: #edf3f7;
    --erp-text-soft: #c1ced8;
    --erp-muted: #99aab8;
    --erp-navy: #8fb1cc;
    --erp-navy-dark: #dce8f0;
}

[data-bs-theme="dark"] .dash-card { background: var(--erp-panel); }
[data-bs-theme="dark"] #dashTab,
[data-bs-theme="dark"] .bg-light { background: var(--erp-panel-soft) !important; }

[data-bs-theme="dark"] #dashTab .nav-link.active {
    background: var(--erp-panel) !important;
    color: var(--erp-navy-dark) !important;
}

[data-bs-theme="dark"] .request-item {
    background: var(--erp-panel-soft);
    border-color: var(--erp-border);
}

@media (max-width: 991.98px) {
    .container-fluid { padding: 22px 20px 30px; }
}

@media (max-width: 575.98px) {
    .container-fluid { padding: 18px 14px 24px; }
    .icon-shape {
        width: 40px;
        height: 40px;
        min-width: 40px;
    }
}
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold mb-1">
                Welcome, <span style="color: #10b981;"><?= explode(' ', $_SESSION['name'] ?? $_SESSION['role'] ?? 'Administrator')[0] ?></span>! 👋
            </h4>
            <p class="text-muted small">Overview of your laboratory unit inventory and lifecycle status.</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card dash-card shadow-sm p-3 border-start border-primary border-4">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-blue-soft me-3"><i class="bi bi-layers"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase extra-small">Total Assets</small>
                        <h4 class="fw-bold mb-0"><?= number_format($stats['total']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dash-card shadow-sm p-3 border-start border-success border-4">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-emerald-soft me-3"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase extra-small">Active</small>
                        <h4 class="fw-bold mb-0 text-success"><?= number_format($stats['active']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dash-card shadow-sm p-3 border-start border-warning border-4">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-amber-soft me-3"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase extra-small">Pending</small>
                        <h4 class="fw-bold mb-0 text-warning"><?= number_format($stats['pending']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dash-card shadow-sm p-3 border-start border-info border-4">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-light me-3 text-info"><i class="bi bi-tools"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase extra-small">In Repair</small>
                        <h4 class="fw-bold mb-0"><?= number_format($stats['in_repair']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card dash-card shadow-sm p-4 h-100" style="max-height: 480px;">
                <h6 class="fw-bold mb-3">Asset Health</h6>
                <div class="d-flex justify-content-between mb-4 mt-2">
                    <div class="text-center">
                        <div class="text-success fw-bold h5 mb-0"><?= $health_data['active'] ?? 0 ?></div>
                        <small class="text-muted extra-small fw-bold">ACTIVE</small>
                    </div>
                    <div class="text-center border-start border-end px-3">
                        <div class="text-info fw-bold h5 mb-0"><?= $health_data['repairing'] ?? 0 ?></div>
                        <small class="text-muted extra-small fw-bold">IN REPAIR</small>
                    </div>
                    <div class="text-center">
                        <div class="text-danger fw-bold h5 mb-0"><?= $health_data['outgoing'] ?? 0 ?></div>
                        <small class="text-muted extra-small fw-bold">REQUESTED</small>
                    </div>
                </div>

                <h6 class="fw-bold mb-3 pt-3 border-top">Category Breakdown</h6>
                <div class="overflow-auto pe-2" style="max-height: 220px;">
                    <?php if($distribution && $distribution->num_rows > 0): 
                        while($row = $distribution->fetch_assoc()): 
                        $pct = ($stats['active'] > 0) ? ($row['count'] / $stats['active']) * 100 : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-dark small fw-medium"><?= htmlspecialchars($row['item_name']) ?></span>
                            <span class="fw-bold small"><?= $row['count'] ?></span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar bg-success" style="width: <?= $pct ?>%; opacity: 0.7;"></div>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="text-center py-4 text-muted extra-small">No assigned items found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card dash-card shadow-sm h-100" style="max-height: 480px;">
                <div class="card-header bg-transparent border-0 p-4 pb-0">
                    <ul class="nav nav-pills nav-fill bg-light rounded-3 p-1" id="dashTab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active py-2 fw-bold small" data-bs-toggle="tab" data-bs-target="#tab-logs">
                                Recent Activity
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link  py-2 fw-bold small" data-bs-toggle="tab" data-bs-target="#tab-requests">
                                Pending Requests
                            </button>
                        </li>
                        
                    </ul>
                </div>

                <div class="tab-content p-4 pt-3">
                    <div class="tab-pane fade show active" id="tab-logs"> <div class="overflow-auto pe-2" style="max-height: 330px;">
                            <?php if ($recent_logs && $recent_logs->num_rows > 0): ?>
                                <div class="ps-2">
                                <?php while($log = $recent_logs->fetch_assoc()): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <small class="fw-bold text-dark text-uppercase" style="font-size: 0.7rem;">
                                                <?= str_replace('_', ' ', $log['action_type']) ?>
                                            </small>
                                            <small class="text-muted extra-small"><?= date('M d', strtotime($log['created_at'])) ?></small>
                                        </div>
                                        <div class="text-muted extra-small mt-1">
                                            <strong><?= htmlspecialchars($log['item_name']) ?>:</strong> <?= htmlspecialchars($log['notes'] ?: 'Action processed') ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                </div>
                                <div class="text-center mt-2">
                                    <a href="asset_logs.php" class="text-success extra-small fw-bold text-decoration-none">VIEW FULL AUDIT LOG →</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted small">No recent activity recorded.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-requests"> <div class="overflow-auto pe-2" style="max-height: 330px;">
                            <?php if ($recent_requests && $recent_requests->num_rows > 0): ?>
                                <?php while($req = $recent_requests->fetch_assoc()): 
                                    $status_label = str_replace('_', ' ', $req['status']);
                                    $badge = strpos($req['status'], 'repair') !== false ? 'bg-info' : (strpos($req['status'], 'dispose') !== false ? 'bg-danger' : 'bg-warning');
                                ?>
                                <div class="request-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-bold text-dark small d-block"><?= htmlspecialchars($req['division_asset_id']) ?></span>
                                            <small class="text-muted extra-small"><?= htmlspecialchars($req['item_name']) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?= $badge ?> rounded-pill mb-1" style="font-size: 0.6rem;">
                                                <?= strtoupper($status_label) ?>
                                            </span>
                                            <div class="extra-small text-muted italic"><?= date('M d, H:i', strtotime($req['assigned_at'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                                <div class="text-center mt-3">
                                    <a href="assigned_assets.php" class="text-success extra-small fw-bold text-decoration-none">VIEW ALL ASSETS →</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-shield-check text-light display-4"></i>
                                    <p class="text-muted small mt-2">No pending lifecycle requests.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
include "../divisions/divisionslayout.php"; 
?>