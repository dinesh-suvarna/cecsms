<?php
include "../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Division Overview";
$page_icon  = "bi-speedometer2";

$division_id = $_SESSION['division_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

/* ================= FETCH ANALYTICS ================= */

// 1. General Stats - FIXED JOIN AND COUNT LOGIC
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
    SUM(CASE WHEN da.status = 'assigned' THEN 1 ELSE 0 END) as healthy,
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
    .dash-card { border: none; border-radius: 16px; transition: all 0.3s ease; background: #fff; }
    .dash-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    
    .icon-shape {
        width: 48px; height: 48px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px; font-size: 1.25rem;
    }
    
    .bg-emerald-soft { background-color: #f0fdf4; color: #10b981; }
    .bg-amber-soft { background-color: #fffbeb; color: #f59e0b; }
    .bg-blue-soft { background-color: #eff6ff; color: #3b82f6; }
    
    .progress-thin { height: 6px; border-radius: 10px; background-color: #f1f5f9; }
    .request-item { border-left: 3px solid #10b981; padding: 10px 15px; background: #f8fafc; border-radius: 0 8px 8px 0; margin-bottom: 8px; }
    
    .extra-small { font-size: 0.72rem; }
    .italic { font-style: italic; }

    .overflow-auto::-webkit-scrollbar { width: 4px; }
    .overflow-auto::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

    .timeline-item { border-left: 2px solid #e2e8f0; padding-left: 15px; position: relative; padding-bottom: 15px; }
    .timeline-item::before {
        content: ""; position: absolute; left: -6px; top: 0;
        width: 10px; height: 10px; border-radius: 50%; background: #10b981;
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
                        <div class="text-success fw-bold h5 mb-0"><?= $health_data['healthy'] ?? 0 ?></div>
                        <small class="text-muted extra-small fw-bold">HEALTHY</small>
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