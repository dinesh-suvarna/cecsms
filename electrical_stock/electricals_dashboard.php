<?php
require_once "../admin/auth.php"; 
require_once __DIR__ . "/../config/db.php";

$role = $_SESSION["role"] ?? 'User'; 
$user_division = $_SESSION['division_id'] ?? 0;
$page_title = "Electrical Analytics";

// 1. Get Total Quantity from Electrical Stock
$total_qty_sql = "SELECT SUM(s.total_qty) as total 
                  FROM electrical_stock s 
                  JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $total_qty_sql .= " WHERE u.division_id = '$user_division'";
}
$total_assets = $conn->query($total_qty_sql)->fetch_assoc()['total'] ?? 0;

// 2. Get Distribution by Electrical Item Name
$cat_query = "SELECT i.item_name, SUM(s.total_qty) as count 
              FROM electrical_stock s
              JOIN electrical_items i ON s.electrical_item_id = i.id
              JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $cat_query .= " WHERE u.division_id = '$user_division'";
}
$cat_query .= " GROUP BY i.item_name ORDER BY count DESC";
$categories = $conn->query($cat_query);

// 3. Recent Activities (Electrical Assets)
$recent_activities_sql = "
    SELECT ea.asset_tag, i.item_name, ea.created_at 
    FROM electrical_assets ea 
    JOIN electrical_stock s ON ea.stock_id = s.id 
    JOIN electrical_items i ON s.electrical_item_id = i.id 
    JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $recent_activities_sql .= " WHERE u.division_id = '$user_division'";
}
$recent_activities_sql .= " ORDER BY ea.id DESC LIMIT 5";
$recent_activities = $conn->query($recent_activities_sql);

ob_start();
?>

<style>
    :root {
        --amber-500: rgb(245, 158, 11); 
        --amber-600: rgb(217, 119, 6);  
        --amber-50: #fffbeb;
        --slate-50: #f8fafc;
        --slate-200: #e2e8f0;
        --slate-900: #0f172a;
    }

    .stock-item-row {
        transition: all 0.2s ease;
        border-left: 3px solid transparent;
    }
    .stock-item-row:hover {
        background-color: var(--amber-50);
        border-left: 3px solid var(--amber-500);
        padding-left: 10px !important;
    }
    
    .count-pill {
        background: var(--amber-50);
        color: var(--amber-600);
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 8px;
        min-width: 60px;
        text-align: center;
        border: 1px solid #fde68a; 
    }

   
    .search-input-group {
        position: relative; 
        width: 100%;
        max-width: 300px; 
    }

    .search-input-group i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        z-index: 5; 
        display: flex;
        align-items: center;
    }

    .search-control {
        padding-left: 40px !important; 
        border-radius: 12px !important;
        border: 1px solid var(--slate-200) !important;
        background: var(--slate-50);
        height: 42px; 
    }
    .search-control:focus {
        border-color: var(--amber-500) !important;
        box-shadow: 0 0 0 0.25rem rgba(245, 158, 11, 0.25) !important;
    }

    .hero-stat {
        /* Gradient using the theme color */
        background: linear-gradient(135deg, rgb(217, 119, 6) 0%, rgb(245, 158, 11) 100%); 
        color: white;
        border-radius: 24px;
        min-height: 380px;
    }

    /* Text and Icon Accents */
    .btn-primary { background-color: var(--amber-500); border-color: var(--amber-500); }

    .custom-scroll {
        max-height: 300px;
        overflow-y: auto;
        overflow-x: hidden;
    }
    .custom-scroll::-webkit-scrollbar { width: 5px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<div class="container-fluid py-4">
    <div class="row g-4">
        
        <div class="col-lg-4">
            <div class="hero-stat p-4 shadow-lg d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-start">
                        <i class="bi bi-lightning-charge fs-1 opacity-50"></i>
                        <span class="badge bg-white bg-opacity-20 rounded-pill">System Online</span>
                    </div>
                    <p class="mt-4 mb-0 opacity-75 fw-medium">Live Electrical Units</p>
                    <h1 class="display-1 fw-bold mb-0"><?= number_format($total_assets) ?></h1>
                </div>
                <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                    <div class="d-flex align-items-center gap-2">
                        <div class="spinner-grow spinner-grow-sm text-light" role="status"></div>
                        <small class="opacity-75">Monitoring infrastructure...</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <h5 class="fw-bold text-slate-900 mb-0">Electrical Inventory Breakdown</h5>
                            <p class="text-muted small mb-0">Filtered by item classification</p>
                        </div>
                        <div class="search-input-group">
                            <i class="bi bi-search"></i>
                            <input type="text" id="itemSearch" class="form-control search-control" placeholder="Search components...">
                        </div>
                    </div>
                </div>
                
                <div class="card-body px-4">
                    <div class="custom-scroll" id="stockContainer">
                        <?php 
                        if($categories && $categories->num_rows > 0):
                            $categories->data_seek(0);
                            while($cat = $categories->fetch_assoc()): 
                        ?>
                            <div class="stock-item-row d-flex justify-content-between align-items-center p-3 mb-2 rounded-3 border-bottom border-light">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-3">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                    <span class="fw-bold text-slate-900 item-name"><?= htmlspecialchars($cat['item_name']) ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="text-muted small d-none d-sm-block">Quantity:</span>
                                    <div class="count-pill"><?= number_format($cat['count']) ?></div>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-plug text-muted display-4"></i>
                                <p class="text-muted mt-2">No electrical items registered.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-4 mt-2">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-slate-900 mb-4">Recent Asset Assignments</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="text-muted small border-bottom">
                                <tr>
                                    <th>TAG ID</th>
                                    <th>COMPONENT</th>
                                    <th class="text-end">TIMESTAMP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($recent_activities && $recent_activities->num_rows > 0): 
                                    while($log = $recent_activities->fetch_assoc()): ?>
                                <tr class="border-bottom border-light">
                                    <td class="py-3 fw-bold text-primary">E-<?= $log['asset_tag'] ?></td>
                                    <td class="text-slate-900 fw-medium"><?= $log['item_name'] ?></td>
                                    <td class="text-end text-muted small"><?= date('M d, H:i', strtotime($log['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="3" class="text-center py-4 text-muted small">No recent activity detected</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-slate-900 mb-4">Electrical Controls</h5>
                    <div class="d-grid gap-3">
                        <a href="view_electrical_assets.php" class="p-3 border rounded-4 d-flex align-items-center text-decoration-none shadow-sm transition">
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div>
                                <p class="mb-0 fw-bold text-dark">Asset Registry</p>
                                <small class="text-muted">Lifecycle tracking</small>
                            </div>
                            <i class="bi bi-chevron-right ms-auto text-muted"></i>
                        </a>
                        
                        <?php if ($role === 'SuperAdmin'): ?>
                        <a href="manage_electrical_items.php" class="p-3 border rounded-4 d-flex align-items-center text-decoration-none shadow-sm transition">
                            <div class="bg-info bg-opacity-10 text-info p-3 rounded-3 me-3">
                                <i class="bi bi-gear"></i>
                            </div>
                            <div>
                                <p class="mb-0 fw-bold text-dark">Hardware Configuration</p>
                                <small class="text-muted">Manage item master</small>
                            </div>
                            <i class="bi bi-chevron-right ms-auto text-muted"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('itemSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const items = document.querySelectorAll('.stock-item-row');
            items.forEach(item => {
                const itemName = item.querySelector('.item-name').textContent.toLowerCase();
                if (itemName.includes(searchValue)) {
                    item.setAttribute('style', 'display: flex !important');
                } else {
                    item.setAttribute('style', 'display: none !important');
                }
            });
        });
    }
});
</script>

<?php 
$content = ob_get_clean(); 
include "electricalslayout.php"; 
?>