<?php
require_once "../admin/auth.php"; 
include "../config/db.php";

$role = $_SESSION["role"] ?? 'User'; 
$user_division = $_SESSION['division_id'] ?? 0;
$page_title = "Electricals Control Center";

// --- 1. DYNAMIC DATA FETCHING ---
// Total Active Assets
$total_assets_query = "SELECT COUNT(ea.id) as total FROM electrical_assets ea 
                       JOIN electrical_stock s ON ea.stock_id = s.id 
                       JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') $total_assets_query .= " WHERE u.division_id = '$user_division'";
$total_assets = $conn->query($total_assets_query)->fetch_assoc()['total'];

// Category Distribution for the "Top Categories" Card
$cat_query = "SELECT i.item_name, COUNT(ea.id) as count 
              FROM electrical_assets ea
              JOIN electrical_stock s ON ea.stock_id = s.id
              JOIN electrical_items i ON s.electrical_item_id = i.id
              JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') $cat_query .= " WHERE u.division_id = '$user_division'";
$cat_query .= " GROUP BY i.item_name ORDER BY count DESC LIMIT 4";
$categories = $conn->query($cat_query);

// --- 2. PENDING TAGGING CALCULATION ---
$pending_query = "
    SELECT COUNT(*) as total FROM (
        SELECT s.id
        FROM electrical_stock s
        JOIN units u ON s.unit_id = u.id
        LEFT JOIN electrical_assets ea ON s.id = ea.stock_id
        WHERE 1=1";

if ($role !== 'SuperAdmin') {
    $pending_query .= " AND u.division_id = '$user_division'";
}

$pending_query .= " GROUP BY s.id, s.total_qty
    HAVING COUNT(ea.id) < s.total_qty
) as pending_queue";

$pending_res = $conn->query($pending_query);
$pending_count = ($pending_res) ? $pending_res->fetch_assoc()['total'] : 0;

ob_start();
?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.95);
        --accent-glow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        --saas-blue: #2563eb;
    }

    body { background-color: #f1f5f9; }

    .stat-card {
        background: var(--glass-bg);
        border: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: var(--accent-glow);
        border-radius: 20px;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.12);
    }

    .icon-box {
        width: 54px;
        height: 54px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
    }

    .action-pill {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 15px;
        padding: 20px;
        text-decoration: none;
        transition: 0.2s;
    }

    .action-pill:hover {
        background: #f8fafc;
        border-color: var(--saas-blue);
    }

    .category-bar {
        height: 8px;
        border-radius: 10px;
        background: #f1f5f9;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #2563eb, #7c3aed);
    }
</style>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="fw-800 text-dark mb-0">electricals <span class="text-primary">Hub</span></h2>
            <p class="text-muted small mb-0">Live inventory monitoring and asset lifecycle</p>
        </div>
        <div class="col-auto">
            <a href="add_electricals.php" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                <i class="bi bi-plus-circle me-2"></i> Register New Stock
            </a>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-lg-8">
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="stat-card p-4">
                        <div class="d-flex justify-content-between mb-3">
                            <div class="icon-box bg-primary-subtle text-primary">
                                <i class="bi bi-cpu fs-4"></i>
                            </div>
                            <span class="badge rounded-pill bg-success-subtle text-success h-50">+12% vs last month</span>
                        </div>
                        <h6 class="text-muted fw-bold text-uppercase small">Total Assets Tracked</h6>
                        <h2 class="fw-bold mb-0"><?= number_format($total_assets) ?></h2>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stat-card p-4">
                        <div class="d-flex justify-content-between mb-3">
                            <div class="icon-box bg-warning-subtle text-warning">
                                <i class="bi bi-tag fs-4"></i>
                            </div>
                            <a href="tag_assets.php" class="text-decoration-none small fw-bold">View List</a>
                        </div>
                        <h6 class="text-muted fw-bold text-uppercase small">Pending Tagging</h6>
                        <h2 class="fw-bold mb-0 text-warning"><?= $pending_count ?></h2>
                    </div>
                </div>
            </div>

            <div class="stat-card p-4">
                <h5 class="fw-bold mb-4">Inventory Distribution</h5>
                <div class="row">
                    <?php while($cat = $categories->fetch_assoc()): 
                        $percent = ($total_assets > 0) ? ($cat['count'] / $total_assets) * 100 : 0;
                    ?>
                        <div class="col-md-6 mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-bold small text-dark"><?= $cat['item_name'] ?></span>
                                <span class="text-muted extra-small"><?= $cat['count'] ?> units</span>
                            </div>
                            <div class="category-bar">
                                <div class="progress-fill" style="width: <?= $percent ?>%"></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="stat-card p-4 h-100">
                <h5 class="fw-bold mb-4">Quick Navigation</h5>
                
                <div class="d-grid gap-3">
                    <a href="view_electricals_assets.php" class="action-pill d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4 me-3">
                            <i class="bi bi-shield-check fs-4"></i>
                        </div>
                        <div>
                            <span class="d-block fw-bold text-dark">Audit Registry</span>
                            <span class="text-muted extra-small">Verify asset conditions</span>
                        </div>
                    </a>

                    <a href="view_electricals.php" class="action-pill d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded-4 me-3">
                            <i class="bi bi-box-seam fs-4"></i>
                        </div>
                        <div>
                            <span class="d-block fw-bold text-dark">Stock History</span>
                            <span class="text-muted extra-small">Inward & outward logs</span>
                        </div>
                    </a>

                    <a href="manage_electricals_type.php" class="action-pill d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 text-info p-3 rounded-4 me-3">
                            <i class="bi bi-sliders fs-4"></i>
                        </div>
                        <div>
                            <span class="d-block fw-bold text-dark">System Config</span>
                            <span class="text-muted extra-small">Manage item categories</span>
                        </div>
                    </a>
                </div>

                <div class="mt-5 p-4 rounded-4 bg-dark text-white shadow-lg">
                    <h6 class="fw-bold mb-2 small"><i class="bi bi-lightning-fill text-warning me-2"></i>System Notice</h6>
                    <p class="extra-small mb-0 text-white-50">Next scheduled inventory audit for this division starts in 4 days.</p>
                </div>
            </div>
        </div>

    </div>
</div>

<?php 
$content = ob_get_clean(); 
include "electricalslayout.php"; 
?>