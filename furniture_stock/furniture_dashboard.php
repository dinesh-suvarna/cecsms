<?php
require_once "../admin/auth.php"; 
include "../config/db.php";

$role = $_SESSION["role"] ?? 'User'; 
$user_division = $_SESSION['division_id'] ?? 0;
$page_title = "Furniture Overview";

// --- DASHBOARD DATA LOGIC ---

// 1. Total Assets (Division Aware)
$asset_query = "SELECT COUNT(fa.id) as total FROM furniture_assets fa 
                JOIN furniture_stock s ON fa.stock_id = s.id 
                JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $asset_query .= " WHERE u.division_id = '$user_division'";
}
$total_assets = $conn->query($asset_query)->fetch_assoc()['total'];

// 2. Pending Tags (Correctly calculates [Total Expected - Currently Tagged] for Bulk Quantity)
$pending_query = "
    SELECT (SUM(s.total_qty) - SUM(IFNULL(asset_counts.cnt, 0))) as pending
    FROM furniture_stock s
    JOIN units u ON s.unit_id = u.id
    LEFT JOIN (
        SELECT stock_id, COUNT(id) as cnt 
        FROM furniture_assets 
        GROUP BY stock_id
    ) asset_counts ON s.id = asset_counts.stock_id";

if ($role !== 'SuperAdmin') {
    $pending_query .= " WHERE u.division_id = '$user_division'";
}

$pending_res = $conn->query($pending_query);
$pending_row = $pending_res->fetch_assoc();
$pending_tags = ($pending_row['pending'] > 0) ? $pending_row['pending'] : 0;

// 3. Recent Activity (Remains the same, but ensures division filtering)
$recent_query = "SELECT fa.asset_tag, i.item_name, fa.last_verified_date 
                 FROM furniture_assets fa
                 JOIN furniture_stock s ON fa.stock_id = s.id
                 JOIN furniture_items i ON s.furniture_item_id = i.id
                 JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $recent_query .= " WHERE u.division_id = '$user_division'";
}
$recent_query .= " ORDER BY fa.id DESC LIMIT 5";
$recent_activities = $conn->query($recent_query);


ob_start();
?>

<div class="container-fluid py-4">
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3">
                        <i class="bi bi-box-seam fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small fw-bold">TOTAL ASSETS</h6>
                        <h3 class="fw-bold mb-0"><?= number_format($total_assets) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3">
                        <i class="bi bi-tag fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small fw-bold">PENDING REGISTRATION</h6>
                        <h3 class="fw-bold mb-0"><?= number_format($pending_tags) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-end">
                <a href="tag_assets.php" class="btn btn-primary rounded-pill w-100 py-3 fw-bold">
                    <i class="bi bi-plus-lg me-2"></i> Quick Tag New Assets
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="fw-bold mb-0">Recently Added Assets</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 border-0">Asset Tag</th>
                                    <th class="border-0">Item Name</th>
                                    <th class="border-0">Last Verified</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $recent_activities->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($row['asset_tag']) ?></td>
                                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= $row['last_verified_date'] ?: 'Never' ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 bg-dark text-white">
                <div class="card-body p-4">
                    <h6 class="text-secondary small fw-bold mb-3">CURRENT SESSION</h6>
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="bg-white bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-person-badge fs-3"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($_SESSION['username']) ?></h5>
                            <span class="badge bg-success"><?= htmlspecialchars($role) ?></span>
                        </div>
                    </div>
                    <hr class="opacity-10">
                    <p class="small text-secondary mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        You are currently viewing data restricted to your assigned division profile.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>