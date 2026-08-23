<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Department Overview";
$page_icon  = "bi-speedometer2";

$division_id = $_SESSION['division_id'] ?? 0;
$role        = $_SESSION['role'] ?? '';
$user_id     = $_SESSION['user_id'] ?? 0;

/* ================= HELPERS ================= */
function getAssetIcon(string $itemName, $category = '') {
    $name = strtolower($itemName);
    $cat  = strtolower($category);
    
    switch (true) {
        case (strpos($name, 'computer') !== false || strpos($name, 'desktop') !== false || $cat === 'computer'):
            return 'bi-pc-display';
        case (strpos($name, 'laptop') !== false):
            return 'bi-laptop';
        case (strpos($name, 'monitor') !== false):
            return 'bi-display';
        case (strpos($name, 'rack') !== false || strpos($name, 'server') !== false):
            return 'bi-hdd-rack';
        case (strpos($name, 'switch') !== false || strpos($name, 'hub') !== false):
            return 'bi-hdd-stack';
        case (strpos($name, 'router') !== false):
            return 'bi-router';
        case (strpos($name, 'printer') !== false):
            return 'bi-printer';
        case (strpos($name, 'keyboard') !== false):
            return 'bi-keyboard';
        case (strpos($name, 'mouse') !== false):
            return 'bi-mouse3';
        case (strpos($name, 'projector') !== false):
            return 'bi-projector';
        case (strpos($name, 'ups') !== false || strpos($name, 'battery') !== false):
            return 'bi-lightning-charge';
        case (strpos($name, 'table') !== false || strpos($name, 'desk') !== false):
            return 'bi-table';
        case (strpos($name, 'chair') !== false):
            return 'bi-person-workspace';
        case (strpos($name, 'camera') !== false || strpos($name, 'cctv') !== false):
            return 'bi-camera-video';
        default:
            return 'bi-box-seam';
    }
}

/* ================= FETCH DIVISION DETAILS ================= */
$division_name = "Central System Registry";
if ($division_id > 0) {
    $div_stmt = $conn->prepare("SELECT division_name FROM divisions WHERE id = ?");
    $div_stmt->bind_param("i", $division_id);
    $div_stmt->execute();
    $div_res = $div_stmt->get_result()->fetch_assoc();
    if ($div_res && !empty($div_res['division_name'])) {
        $division_name = $div_res['division_name'];
    }
}

/* ================= FETCH ANALYTICS ================= */
// 1. KPI Metrics
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

// 2. Asset Distribution (Categories & Icons)
$dist_query = "SELECT im.item_name, im.category, COUNT(*) as count 
    FROM division_assets da
    JOIN stock_details sd ON sd.id = da.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    LEFT JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    LEFT JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE da.status = 'assigned' " . ($role !== 'SuperAdmin' ? " AND dm.division_id = $division_id" : "") . "
    GROUP BY im.item_name, im.category ORDER BY count DESC";
$distribution = $conn->query($dist_query);

// 3. Pending Lifecycle Requests
$req_query = "SELECT da.id, da.division_asset_id, im.item_name, im.category, da.status, da.assigned_at
    FROM division_assets da
    JOIN stock_details sd ON sd.id = da.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    LEFT JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    LEFT JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE da.status LIKE '%_requested' " . ($role !== 'SuperAdmin' ? " AND dm.division_id = $division_id" : "") . "
    ORDER BY da.id DESC LIMIT 6";
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

// 5. Recent Activity Logs
$log_query = "SELECT al.action_type, al.created_at, im.item_name, al.notes
    FROM asset_logs al
    JOIN stock_details sd ON al.asset_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN division_assets da ON sd.id = da.stock_detail_id
    LEFT JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    LEFT JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE 1=1 " . ($role !== 'SuperAdmin' ? " AND (dm.division_id = $division_id OR al.performed_by = {$user_id})" : "") . "
    ORDER BY al.created_at DESC LIMIT 6";
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
    --erp-shadow: 0 1px 3px rgba(20,45,70,.05);
    --erp-shadow-hover: 0 4px 12px rgba(20,45,70,.09);
}

.container-fluid {
    max-width: 1440px;
    padding: 24px 28px 36px;
}

.dash-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 6px !important;
    background: var(--erp-panel);
    transition: all .18s ease-in-out;
    box-shadow: var(--erp-shadow) !important;
}

.dash-card:hover {
    border-color: var(--erp-border-dark) !important;
    box-shadow: var(--erp-shadow-hover) !important;
}

.icon-shape {
    width: 44px;
    height: 44px;
    min-width: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 1.15rem;
    border: 1px solid rgba(18,59,99,.08);
}

.bg-emerald-soft { background-color: #eef5f1 !important; color: var(--erp-green) !important; }
.bg-amber-soft   { background-color: #f7f2e8 !important; color: var(--erp-amber) !important; }
.bg-blue-soft    { background-color: #edf3f8 !important; color: var(--erp-blue) !important; }
.bg-purple-soft  { background-color: #f3eefa !important; color: #6b46c1 !important; }

.extra-small { font-size: .72rem; }

.container-fluid .text-success { color: var(--erp-green) !important; }
.container-fluid .text-warning { color: var(--erp-amber) !important; }
.container-fluid .text-info    { color: var(--erp-info) !important; }
.container-fluid .text-muted   { color: var(--erp-muted) !important; }
.container-fluid .text-dark    { color: var(--erp-text) !important; }

.progress-thin {
    height: 6px;
    border-radius: 3px;
    background-color: #e9eef2;
}

#dashTab {
    border: 1px solid var(--erp-border);
    border-radius: 6px !important;
    background: #cfd9e3 !important;
    padding: 3px !important;
}

#dashTab .nav-link {
    color: var(--erp-text-soft);
    border-radius: 4px !important;
    font-size: .78rem !important;
    font-weight: 600 !important;
    padding: 7px 12px !important;
    transition: all 0.15s ease;
}

#dashTab .nav-link:hover { color: var(--erp-navy); }
#dashTab .nav-link.active {
    background: var(--erp-panel) !important;
    color: var(--erp-navy) !important;
    box-shadow: 0 1px 3px rgba(20,45,70,.08);
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
    top: 2px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--erp-navy);
    border: 2px solid var(--erp-panel);
}

.request-item {
    border-left: 3px solid var(--erp-amber);
    padding: 10px 12px;
    background: var(--erp-panel-soft);
    border-top: 1px solid #edf0f3;
    border-right: 1px solid #edf0f3;
    border-bottom: 1px solid #edf0f3;
    border-radius: 0 4px 4px 0;
    margin-bottom: 8px;
    transition: background-color 0.15s ease;
}

.request-item:hover { background-color: #f0f4f8; }

.hover-row { transition: background-color 0.15s ease; }
.hover-row:hover td { background-color: #f1f5f9 !important; }

.overflow-auto::-webkit-scrollbar { width: 4px; }
.overflow-auto::-webkit-scrollbar-thumb { background: #cbd5dd; border-radius: 3px; }
</style>

<div class="container-fluid py-0">
    <!-- Professional Header Section -->
    <div class="row mb-4 pb-3 border-bottom align-items-center">
        <div class="col-12">
            <h4 class="fw-bold mb-1" style="color: var(--erp-navy-dark); font-size: 1.25rem;">
                <span style="color: var(--erp-navy);"><?= htmlspecialchars($division_name) ?></span>
            </h4>
            <p class="text-muted small mb-0">Overview of your laboratory unit inventory and lifecycle status.</p>
        </div>
    </div>

    <!-- Top KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card dash-card p-3 border-start border-primary border-4">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-blue-soft me-3"><i class="bi bi-layers"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase extra-small">Total Assets</small>
                        <h4 class="fw-bold mb-0 text-dark"><?= inr($stats['total']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card dash-card p-3 border-start border-success border-4">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-emerald-soft me-3"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase extra-small">Active</small>
                        <h4 class="fw-bold mb-0 text-success"><?= inr($stats['active']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card dash-card p-3 border-start border-warning border-4">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-amber-soft me-3"><i class="bi bi-clock-history"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase extra-small">Pending</small>
                        <h4 class="fw-bold mb-0 text-warning"><?= inr($stats['pending']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card dash-card p-3 border-start border-info border-4">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-purple-soft me-3"><i class="bi bi-tools"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase extra-small">In Repair</small>
                        <h4 class="fw-bold mb-0 text-dark"><?= inr($stats['in_repair']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Section -->
    <div class="row g-3">
        <!-- Asset Health & Breakdown -->
        <div class="col-lg-5 col-md-5">
        <div class="card dash-card p-4 h-100" style="max-height: 480px;">
                <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-heart-pulse me-2 text-primary"></i>Asset Health Overview</h6>
                <div class="d-flex justify-content-between mb-4 mt-2">
                    <div class="text-center">
                        <div class="text-success fw-bold h5 mb-0"><?= $health_data['active'] ?? 0 ?></div>
                        <small class="text-muted extra-small fw-bold">ACTIVE</small>
                    </div>
                    <div class="text-center border-start border-end px-3">
                        <div class="text-info fw-bold h5 mb-0"><?= $health_data['repairing'] ?? 0 ?></div>
                        <small class="text-muted extra-small fw-bold">REPAIRING</small>
                    </div>
                    <div class="text-center">
                        <div class="text-danger fw-bold h5 mb-0"><?= $health_data['outgoing'] ?? 0 ?></div>
                        <small class="text-muted extra-small fw-bold">REQUESTED</small>
                    </div>
                </div>

                <h6 class="fw-bold mb-3 pt-3 border-top text-dark"><i class="bi bi-pie-chart me-2 text-primary"></i>Category Distribution</h6>
                <div class="overflow-auto pe-1" style="max-height: 220px;">
                    <?php if($distribution && $distribution->num_rows > 0): 
                        while($row = $distribution->fetch_assoc()): 
                        $bar_width = ($stats['active'] > 0) ? round(($row['count'] / $stats['active']) * 100) : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-dark extra-small fw-bold d-flex align-items-center">
                                <i class="bi <?= getAssetIcon($row['item_name'], $row['category'] ?? '') ?> me-2 text-muted"></i>
                                <?= htmlspecialchars($row['item_name']) ?>
                            </span>
                            <span class="fw-bold extra-small text-secondary"><?= $row['count'] ?></span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar" style="width: <?= $bar_width ?>%; background-color: var(--erp-blue);"></div>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="text-center py-4 text-muted extra-small">No assigned items found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Activity & Request Tabs -->
        <div class="col-lg-7 col-md-7">
        <div class="card dash-card h-100" style="max-height: 480px;">
                <div class="card-header bg-transparent border-0 p-3 pb-0">
                    <ul class="nav nav-pills nav-fill" id="dashTab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#tab-logs">
                                <i class="bi bi-journal-text me-1"></i> Recent Activity
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tab-requests">
                                <i class="bi bi-hourglass-split me-1"></i> Pending Requests
                                <?php if($stats['pending'] > 0): ?>
                                    <span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size:0.65rem;"><?= $stats['pending'] ?></span>
                                <?php endif; ?>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content p-4 pt-3">
                    <!-- Audit Activity Logs -->
                    <div class="tab-pane fade show active" id="tab-logs">
                        <div class="overflow-auto pe-2" style="max-height: 330px;">
                            <?php if ($recent_logs && $recent_logs->num_rows > 0): ?>
                                <div class="ps-2">
                                <?php while($log = $recent_logs->fetch_assoc()): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <small class="fw-bold text-dark text-uppercase" style="font-size: 0.72rem;">
                                                <?= str_replace('_', ' ', $log['action_type']) ?>
                                            </small>
                                            <small class="text-muted extra-small"><i class="bi bi-clock me-1"></i><?= date('M d, g:i A', strtotime($log['created_at'])) ?></small>
                                        </div>
                                        <div class="text-muted extra-small mt-1">
                                            <strong class="text-dark"><?= htmlspecialchars($log['item_name']) ?>:</strong> <?= htmlspecialchars($log['notes'] ?: 'Action processed') ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted small">
                                    <i class="bi bi-inbox fs-3 text-muted d-block mb-2"></i>
                                    No recent activity recorded.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pending Requests -->
                    <div class="tab-pane fade" id="tab-requests">
                        <div class="overflow-auto pe-2" style="max-height: 330px;">
                            <?php if ($recent_requests && $recent_requests->num_rows > 0): ?>
                                <?php while($req = $recent_requests->fetch_assoc()): 
                                    $status_label = str_replace('_', ' ', $req['status']);
                                    $badge = strpos($req['status'], 'repair') !== false ? 'bg-info' : (strpos($req['status'], 'dispose') !== false ? 'bg-danger' : 'bg-warning text-dark');
                                ?>
                                <div class="request-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi <?= getAssetIcon($req['item_name'], $req['category'] ?? '') ?> fs-5 text-secondary"></i>
                                            <div>
                                                <span class="fw-bold text-dark small d-block"><?= htmlspecialchars($req['division_asset_id']) ?></span>
                                                <small class="text-muted extra-small"><?= htmlspecialchars($req['item_name']) ?></small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?= $badge ?> rounded-pill mb-1" style="font-size: 0.62rem;">
                                                <?= strtoupper($status_label) ?>
                                            </span>
                                            <div class="extra-small text-muted"><i class="bi bi-calendar-event me-1"></i><?= date('M d, H:i', strtotime($req['assigned_at'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                                <div class="text-center mt-3">
                                    <a href="assigned_assets_2.php" class="extra-small fw-bold text-decoration-none" style="color: var(--erp-navy);">
                                        GO TO ASSET REGISTRY TO MANAGE →
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-shield-check text-success display-5"></i>
                                    <p class="text-muted small mt-2">All assets healthy. No pending lifecycle requests.</p>
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