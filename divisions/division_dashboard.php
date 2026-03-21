<?php
include "../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Division Overview";
$page_icon  = "bi-speedometer2";

$division_id = $_SESSION['division_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

/* ================= FETCH ANALYTICS ================= */
// 1. General Stats
/* ================= FETCH ANALYTICS ================= */
// 1. General Stats - Corrected with 'da.' prefix to avoid ambiguity
$stats_query = "SELECT 
    COUNT(da.id) as total,
    SUM(CASE WHEN da.status = 'assigned' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN da.status LIKE '%_requested' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN da.status = 'repair_requested' THEN 1 ELSE 0 END) as in_repair
    FROM division_assets da
    JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE 1=1 " . ($role !== 'SuperAdmin' ? " AND dm.division_id = $division_id" : "");

$stats_result = $conn->query($stats_query);
if (!$stats_result) {
    die("Query Failed: " . $conn->error);
}
$stats = $stats_result->fetch_assoc();

// 2. Asset Distribution (by Category)
$dist_query = "SELECT im.item_name, COUNT(*) as count 
    FROM division_assets da
    JOIN stock_details sd ON sd.id = da.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE da.status = 'assigned' " . ($role !== 'SuperAdmin' ? " AND dm.division_id = $division_id" : "") . "
    GROUP BY im.item_name ORDER BY count DESC";
$distribution = $conn->query($dist_query);

// 3. Recent Pending Requests
// 3. Recent Pending Requests - Changed updated_at to assigned_at
$req_query = "SELECT da.division_asset_id, im.item_name, da.status, da.assigned_at
    FROM division_assets da
    JOIN stock_details sd ON sd.id = da.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    WHERE da.status LIKE '%_requested' " . ($role !== 'SuperAdmin' ? " AND dm.division_id = $division_id" : "") . "
    ORDER BY da.id DESC LIMIT 5"; // Ordering by ID DESC shows the newest requests
$recent_requests = $conn->query($req_query);

ob_start();
?>

<style>
    .dash-card { border: none; border-radius: 16px; transition: all 0.3s ease; }
    .dash-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    
    .icon-shape {
        width: 50px; height: 50px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px; font-size: 1.5rem;
    }
    
    .bg-emerald-soft { background-color: #f0fdf4; color: #10b981; }
    .bg-amber-soft { background-color: #fffbeb; color: #f59e0b; }
    .bg-blue-soft { background-color: #eff6ff; color: #3b82f6; }
    
    .progress-thin { height: 6px; border-radius: 10px; }
    .request-item { border-left: 3px solid #10b981; padding: 10px 15px; background: #f8fafc; border-radius: 0 8px 8px 0; margin-bottom: 10px; }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold">
                Welcome back, <span style="color: #10b981;"><?= explode(' ', $_SESSION['name'] ?? 'Admin')[0] ?></span>! 👋
            </h4>
            <p class="text-muted">Here is what's happening with your unit assets today.</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card dash-card shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-blue-soft me-3"><i class="bi bi-layers"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase italic">Total Registry</small>
                        <h3 class="fw-bold mb-0"><?= $stats['total'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dash-card shadow-sm p-3" style="border-bottom: 3px solid #10b981;">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-emerald-soft me-3"><i class="bi bi-check2-all"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase">Active Units</small>
                        <h3 class="fw-bold mb-0 text-success"><?= $stats['active'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dash-card shadow-sm p-3" style="border-bottom: 3px solid #f59e0b;">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-amber-soft me-3"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase">Pending Action</small>
                        <h3 class="fw-bold mb-0 text-warning"><?= $stats['pending'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dash-card shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-shape bg-light me-3"><i class="bi bi-tools"></i></div>
                    <div>
                        <small class="text-muted fw-bold text-uppercase">Under Repair</small>
                        <h3 class="fw-bold mb-0"><?= $stats['in_repair'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card dash-card shadow-sm h-100 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Asset Distribution</h5>
                    <a href="assigned_assets.php" class="btn btn-sm btn-light rounded-pill px-3">View Registry</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php while($row = $distribution->fetch_assoc()): 
                        $percentage = ($stats['active'] > 0) ? ($row['count'] / $stats['active']) * 100 : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold text-dark small"><?= $row['item_name'] ?></span>
                            <span class="text-muted small"><?= $row['count'] ?> Units</span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar bg-success" style="width: <?= $percentage ?>%; opacity: 0.8;"></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card dash-card shadow-sm h-100 p-4">
                <h5 class="fw-bold mb-4">Lifecycle Requests</h5>
                <?php if ($recent_requests->num_rows > 0): ?>
                    <?php while($req = $recent_requests->fetch_assoc()): 
                        $badge_class = strpos($req['status'], 'repair') !== false ? 'bg-info' : (strpos($req['status'], 'return') !== false ? 'bg-warning' : 'bg-danger');
                    ?>
                    <div class="request-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold text-dark small"><?= $req['division_asset_id'] ?></div>
                                <div class="text-muted extra-small"><?= $req['item_name'] ?></div>
                            </div>
                            <span class="badge <?= $badge_class ?> rounded-pill" style="font-size: 0.6rem;">
                                <?= str_replace('_', ' ', $req['status']) ?>
                            </span>
                        </div>
                        <div class="text-end mt-1">
                            <small class="text-muted" style="font-size: 0.65rem;">
                                Assigned: <?= date('d M, H:i', strtotime($req['assigned_at'])) ?>
                            </small>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-shield-check text-light display-1"></i>
                        <p class="text-muted small mt-2">All assets are currently healthy.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
include "../divisions/divisionslayout.php"; 
?>