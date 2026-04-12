<?php
require_once "../admin/auth.php"; 
include "../config/db.php";

$role = $_SESSION["role"] ?? 'User'; 
$user_division = $_SESSION['division_id'] ?? 0;
$page_title = "Furniture Analytics";

// 1. Get Total Quantity from Stock
$total_qty_sql = "SELECT SUM(s.total_qty) as total 
                  FROM furniture_stock s 
                  JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $total_qty_sql .= " WHERE u.division_id = '$user_division'";
}
$total_assets = $conn->query($total_qty_sql)->fetch_assoc()['total'] ?? 0;

// 2. Get Distribution based on Stock Quantities
$cat_query = "SELECT i.item_name, SUM(s.total_qty) as count 
              FROM furniture_stock s
              JOIN furniture_items i ON s.furniture_item_id = i.id
              JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $cat_query .= " WHERE u.division_id = '$user_division'";
}
$cat_query .= " GROUP BY i.item_name ORDER BY count DESC";
$categories = $conn->query($cat_query);

// 3. Get Recent Activities
$recent_activities = $conn->query("SELECT fa.asset_tag, i.item_name, fa.created_at FROM furniture_assets fa JOIN furniture_stock s ON fa.stock_id = s.id JOIN furniture_items i ON s.furniture_item_id = i.id JOIN units u ON s.unit_id = u.id " . ($role !== 'SuperAdmin' ? " WHERE u.division_id = '$user_division'" : "") . " ORDER BY fa.id DESC LIMIT 5");

ob_start();
?>

<style>
    :root {
        --emerald-primary: #059669;
        --emerald-soft: #ecfdf5;
        --glass-bg: rgba(255, 255, 255, 0.8);
        --slate-900: #0f172a;
    }
    body { background-color: #f1f5f9; font-family: 'Inter', sans-serif; }
    
    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        transition: all 0.3s ease;
    }
    .glass-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
    }
    .hero-stat {
        background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
        color: white;
        border-radius: 24px;
        position: relative;
        overflow: hidden;
    }
    .progress-pulse {
        height: 8px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }
    .progress-bar-animated {
        background: linear-gradient(90deg, #10b981, #34d399);
    }
    .activity-dot {
        width: 8px; height: 8px;
        background: var(--emerald-primary);
        border-radius: 50%;
        display: inline-block;
    }
</style>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-5">
        <div class="col-md-8">
            <h2 class="fw-bold text-slate-900">Furniture Analytics Dashboard</h2>
            <p class="text-muted">Managing assets across <?= $role === 'SuperAdmin' ? 'all organizational units' : 'your division' ?>.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="tag_assets.php" class="btn shadow-lg px-4 py-3 rounded-4 fw-bold" style="background: #0f172a; color: white;">
                <i class="bi bi-tags"></i> Deploy Tags
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="hero-stat p-4 h-100 d-flex flex-column justify-content-between shadow-lg" style="min-height: 350px;">
                <div>
                    <i class="bi bi-command fs-1 opacity-50"></i>
                    <p class="mt-4 mb-0 opacity-75">Live Assets Managed</p>
                    <h1 class="display-1 fw-bold mb-0"><?= number_format($total_assets) ?></h1>
                </div>
                <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                    <small class="opacity-75"><i class="bi bi-arrow-up-right me-1"></i> Tracking real-time inventory</small>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="mb-3">
                <h5 class="fw-bold text-slate-900 mb-1">Asset Distribution</h5>
                <p class="text-muted small">Detailed breakdown of current stock levels</p>
            </div>
            <div class="row g-3">
                <?php 
                $categories->data_seek(0); 
                $count = 0;
                while($cat = $categories->fetch_assoc()): 
                    $percentage = ($total_assets > 0) ? ($cat['count'] / $total_assets) * 100 : 0;
                    $count++;
                    if($count <= 6): 
                ?>
                    <div class="col-md-6">
                        <div class="glass-card p-3 bg-white border shadow-sm rounded-4 h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="fw-bold text-dark small pe-2"><?= htmlspecialchars($cat['item_name']) ?></span>
                                <span class="badge text-white rounded-pill flex-shrink-0" style="font-size: 0.7rem; background-color: var(--emerald-primary);">
                                    <?= $cat['count'] ?> units
                                </span>
                            </div>
                            <div class="progress-pulse mt-auto">
                                <div class="progress-bar progress-bar-animated h-100" style="width: <?= $percentage ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endif; endwhile; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <?php 
        $categories->data_seek(0);
        $idx = 0;
        while($cat = $categories->fetch_assoc()): 
            $idx++;
            if($idx > 6): 
                $percentage = ($total_assets > 0) ? ($cat['count'] / $total_assets) * 100 : 0;
        ?>
            <div class="col-lg-4 col-md-6">
                <div class="glass-card p-3 bg-white border shadow-sm rounded-4 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="fw-bold text-dark small pe-2"><?= htmlspecialchars($cat['item_name']) ?></span>
                        <span class="badge text-white rounded-pill flex-shrink-0" style="font-size: 0.7rem; background-color: var(--emerald-primary);">
                            <?= $cat['count'] ?> units
                        </span>
                    </div>
                    <div class="progress-pulse mt-auto">
                        <div class="progress-bar progress-bar-animated h-100" style="width: <?= $percentage ?>%"></div>
                    </div>
                </div>
            </div>
        <?php endif; endwhile; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="glass-card p-4 bg-white shadow-sm h-100">
                <h5 class="fw-bold text-slate-900 mb-4">Recent Asset Activity</h5>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle">
                        <thead class="text-muted small border-bottom">
                            <tr>
                                <th class="pb-3">ASSET TAG</th>
                                <th class="pb-3">ITEM</th>
                                <th class="pb-3 text-end">DEPLOYED</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($log = $recent_activities->fetch_assoc()): ?>
                            <tr class="border-bottom border-light">
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-dot me-3"></div>
                                        <span class="fw-bold text-emerald-700"><?= $log['asset_tag'] ?></span>
                                    </div>
                                </td>
                                <td class="text-slate-900 fw-medium small"><?= $log['item_name'] ?></td>
                                <td class="text-end text-muted small">
                                    <?= date('M d, H:i', strtotime($log['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="glass-card p-4 bg-white shadow-sm h-100">
                <h5 class="fw-bold text-slate-900 mb-4">Quick Actions</h5>
                <div class="d-grid gap-3">
                    <a href="view_assets.php" class="p-3 border rounded-4 d-flex align-items-center text-decoration-none shadow-sm">
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                            <i class="bi bi-search fs-5"></i>
                        </div>
                        <div>
                            <p class="mb-0 fw-bold text-dark">Audit Registry</p>
                            <small class="text-muted">Search and filter every item</small>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </a>
                    <a href="manage_furniture_types.php" class="p-3 border rounded-4 d-flex align-items-center text-decoration-none shadow-sm">
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 me-3">
                            <i class="bi bi-layers fs-5"></i>
                        </div>
                        <div>
                            <p class="mb-0 fw-bold text-dark">Manage Types</p>
                            <small class="text-muted">Add or edit furniture categories</small>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>