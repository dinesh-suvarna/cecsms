<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    $_SESSION['error_msg'] = "Access Denied: Insufficient operational clearance.";
    header("Location: ../dashboard.php");
    exit;
}

$page_title = "E-Waste Metrics Control";
$page_icon  = "bi-speedometer2";

/* ---------- AGGREGATED METRICS ---------- */
$counts_query = "
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN status = 'Pending_Verification' THEN 1 ELSE 0 END) as pending_cnt,
        SUM(CASE WHEN status = 'In_Ewaste_Store' THEN 1 ELSE 0 END) as store_cnt,
        SUM(CASE WHEN status = 'Scrapped' THEN 1 ELSE 0 END) as scrapped_cnt,
        SUM(CASE WHEN status = 'Refurbished' THEN 1 ELSE 0 END) as refurbished_cnt
    FROM ewaste_items
";
$counts_res = $conn->query($counts_query)->fetch_assoc();

$total_items       = $counts_res['total_items'] ?? 0;
$pending_verify    = $counts_res['pending_cnt'] ?? 0;
$in_ewaste_store   = $counts_res['store_cnt'] ?? 0;
$total_scrapped    = $counts_res['scrapped_cnt'] ?? 0;
$total_refurbished = $counts_res['refurbished_cnt'] ?? 0;

$recovery_rate = $total_items > 0 ? round(($total_refurbished / $total_items) * 100, 1) : 0;

/* ---------- RECENT UNVERIFIED QUEUE STREAM (LIMIT 5) ---------- */
$recent_query = "
    SELECT ew.ewaste_id, ew.division_asset_id, ew.logged_at, im.item_name, ew.disposal_reason
    FROM ewaste_items ew
    JOIN stock_details sd ON sd.id = ew.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    WHERE ew.status = 'Pending_Verification'
    ORDER BY ew.logged_at DESC LIMIT 5
";
$recent_res = $conn->query($recent_query);

ob_start();
?>

<style>
    .saas-card { border: 1px solid #e2e8f0; background: #fff; border-radius: 12px; }
    .icon-box { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 10px; }
    .progress-micro { height: 6px; border-radius: 3px; }
</style>

<div class="container-fluid py-4">
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">E-Waste Overview</h3>
            <p class="text-muted small mb-0">High-level sustainability insights, circularity indices, and operational bottlenecks.</p>
        </div>
        <a href="ewaste_registry.php" class="btn btn-dark rounded-3 font-medium px-3 py-2 small">
            <i class="bi bi-table me-2"></i> Open Management Ledger
        </a>
    </div>

    <!-- METRICS STRIP -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card saas-card border-0 shadow-sm">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small d-block mb-1 fw-bold text-uppercase" style="font-size:0.65rem;">Verification Queue</span>
                        <h3 class="fw-bold text-dark mb-0"><?= $pending_verify ?></h3>
                    </div>
                    <div class="icon-box bg-warning-subtle text-warning"><i class="bi bi-hourglass-split fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card saas-card border-0 shadow-sm">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small d-block mb-1 fw-bold text-uppercase" style="font-size:0.65rem;">In E-Waste Store</span>
                        <h3 class="fw-bold text-dark mb-0"><?= $in_ewaste_store ?></h3>
                    </div>
                    <div class="icon-box bg-info-subtle text-info"><i class="bi bi-houses fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card saas-card border-0 shadow-sm">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small d-block mb-1 fw-bold text-uppercase" style="font-size:0.65rem;">Scrapped (Recycled)</span>
                        <h3 class="fw-bold text-dark mb-0"><?= $total_scrapped ?></h3>
                    </div>
                    <div class="icon-box bg-danger-subtle text-danger"><i class="bi bi-recycle fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card saas-card border-0 shadow-sm">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small d-block mb-1 fw-bold text-uppercase" style="font-size:0.65rem;">Circularity Index</span>
                        <h3 class="fw-bold text-success mb-0"><?= $recovery_rate ?>%</h3>
                    </div>
                    <div class="icon-box bg-success-subtle text-success"><i class="bi bi-shield-check fs-4"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- MIDDLE SUB-GRID -->
    <div class="row g-4">
        <!-- HARDWARE PIPELINE RATIO PERFORMANCE -->
        <div class="col-lg-5">
            <div class="card saas-card border-0 shadow-sm h-100 p-4">
                <h6 class="fw-bold mb-1">Circularity Framework Breakdown</h6>
                <p class="text-muted small mb-4">Visual asset volume distribution index between recycling loops.</p>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between text-dark small fw-semibold mb-1">
                        <span>Refurbished Assets Pool</span>
                        <span><?= $total_refurbished ?> items</span>
                    </div>
                    <div class="progress progress-micro"><div class="progress-bar bg-success" style="width: <?= $total_items > 0 ? ($total_refurbished/$total_items)*100 : 0 ?>%"></div></div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between text-dark small fw-semibold mb-1">
                        <span>Raw Component Scrapping</span>
                        <span><?= $total_scrapped ?> items</span>
                    </div>
                    <div class="progress progress-micro"><div class="progress-bar bg-danger" style="width: <?= $total_items > 0 ? ($total_scrapped/$total_items)*100 : 0 ?>%"></div></div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between text-dark small fw-semibold mb-1">
                        <span>In Storage Assets</span>
                        <span><?= $in_ewaste_store ?> items</span>
                    </div>
                    <div class="progress progress-micro"><div class="progress-bar bg-info" style="width: <?= $total_items > 0 ? ($in_ewaste_store/$total_items)*100 : 0 ?>%"></div></div>
                </div>
            </div>
        </div>

        <!-- RECENT ACTIONABLE QUEUE TRUCKS -->
        <div class="col-lg-7">
            <div class="card saas-card border-0 shadow-sm h-100 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Urgent Verification Feed</h6>
                        <p class="text-muted small mb-0">Latest items logged as e-waste awaiting processing actions.</p>
                    </div>
                    <span class="badge bg-warning-subtle text-warning border px-2 py-1 small"><?= $pending_verify ?> Active</span>
                </div>
                
                <div class="list-group list-group-flush">
                    <?php if($recent_res && $recent_res->num_rows > 0): ?>
                        <?php while($row = $recent_res->fetch_assoc()): ?>
                            <div class="list-group-item px-0 py-3 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold text-dark d-block" style="font-size:0.88rem;"><?= htmlspecialchars($row['item_name']) ?></span>
                                    <small class="text-muted">ID: <span class="font-monospace text-primary"><?= htmlspecialchars($row['division_asset_id']) ?></span> • Reason: <?= htmlspecialchars($row['disposal_reason']) ?></small>
                                </div>
                                <a href="ewaste_registry.php?search=<?= urlencode($row['division_asset_id']) ?>" class="btn btn-sm btn-light border fw-semibold rounded-2">
                                    Process <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-patch-check text-success fs-2 d-block mb-2"></i>
                            Verification queue is totally clear!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$main_content = ob_get_clean(); 
include "ewastelayout.php"; 
?>