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

// 2. Get Distribution
$cat_query = "SELECT i.item_name, SUM(s.total_qty) as count 
              FROM furniture_stock s
              JOIN furniture_items i ON s.furniture_item_id = i.id
              JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $cat_query .= " WHERE u.division_id = '$user_division'";
}
$cat_query .= " GROUP BY i.item_name ORDER BY count DESC";
$categories = $conn->query($cat_query);

// 3. Recent Activities
$recent_activities = $conn->query("SELECT fa.asset_tag, i.item_name, fa.created_at FROM furniture_assets fa JOIN furniture_stock s ON fa.stock_id = s.id JOIN furniture_items i ON s.furniture_item_id = i.id JOIN units u ON s.unit_id = u.id " . ($role !== 'SuperAdmin' ? " WHERE u.division_id = '$user_division'" : "") . " ORDER BY fa.id DESC LIMIT 5");

ob_start();
?>

<style>
    :root {
        --emerald-600: #059669;
        --slate-50: #f8fafc;
        --slate-200: #e2e8f0;
        --slate-900: #0f172a;
    }

    /* Distribution List Styling */
    .stock-item-row {
        transition: all 0.2s ease;
        border-left: 3px solid transparent;
    }
    .stock-item-row:hover {
        background-color: var(--slate-50);
        border-left: 3px solid var(--emerald-600);
        padding-left: 10px !important;
    }
    
    .count-pill {
        background: #f1f5f9;
        color: var(--slate-900);
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 8px;
        min-width: 60px;
        text-align: center;
        border: 1px solid var(--slate-200);
    }

    .search-input-group {
        position: relative;
    }
    .search-input-group i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }
    .search-control {
        padding-left: 38px !important;
        border-radius: 12px !important;
        border: 1px solid var(--slate-200) !important;
        background: var(--slate-50);
    }

    .hero-stat {
        background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
        color: white;
        border-radius: 24px;
        min-height: 380px;
    }

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
                        <i class="bi bi-command fs-1 opacity-50"></i>
                        <span class="badge bg-white bg-opacity-20 rounded-pill">Real-time</span>
                    </div>
                    <p class="mt-4 mb-0 opacity-75 fw-medium">Live Assets Managed</p>
                    <h1 class="display-1 fw-bold mb-0"><?= number_format($total_assets) ?></h1>
                </div>
                <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                    <div class="d-flex align-items-center gap-2">
                        <div class="spinner-grow spinner-grow-sm text-light" role="status"></div>
                        <small class="opacity-75">Syncing with active inventory...</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <h5 class="fw-bold text-slate-900 mb-0">Furniture Inventory Breakdown</h5>
                            <p class="text-muted small mb-0">Instant search by item name</p>
                        </div>
                        <div class="search-input-group">
                            <i class="bi bi-search"></i>
                            <input type="text" id="itemSearch" class="form-control search-control" placeholder="Search items...">
                        </div>
                    </div>
                </div>
                
                <div class="card-body px-4">
                    <div class="custom-scroll" id="stockContainer">
                        <?php 
                        $categories->data_seek(0);
                        if($categories->num_rows > 0):
                            while($cat = $categories->fetch_assoc()): 
                        ?>
                            <div class="stock-item-row d-flex justify-content-between align-items-center p-3 mb-2 rounded-3 border-bottom border-light">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-success bg-opacity-10 text-success rounded-3 p-2 me-3">
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
                                <i class="bi bi-search text-muted display-4"></i>
                                <p class="text-muted mt-2">No items found.</p>
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
                    <h5 class="fw-bold text-slate-900 mb-4">Recent Asset Activity</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="text-muted small border-bottom">
                                <tr>
                                    <th>ASSET TAG</th>
                                    <th>ITEM</th>
                                    <th class="text-end">DEPLOYED</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($log = $recent_activities->fetch_assoc()): ?>
                                <tr class="border-bottom border-light">
                                    <td class="py-3 fw-bold text-emerald-600">#<?= $log['asset_tag'] ?></td>
                                    <td class="text-slate-900 fw-medium"><?= $log['item_name'] ?></td>
                                    <td class="text-end text-muted small"><?= date('M d, H:i', strtotime($log['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-slate-900 mb-4">Quick Navigation</h5>
                    <div class="d-grid gap-3">
                        <a href="view_assets.php" class="p-3 border rounded-4 d-flex align-items-center text-decoration-none shadow-sm hover-bg-light">
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3 me-3">
                                <i class="bi bi-search"></i>
                            </div>
                            <div>
                                <p class="mb-0 fw-bold text-dark">Audit Registry</p>
                                <small class="text-muted">Detailed asset tracking</small>
                            </div>
                            <i class="bi bi-chevron-right ms-auto text-muted"></i>
                        </a>
                        
                        <?php if ($role === 'SuperAdmin'): ?>
                        <a href="manage_furniture_types.php" class="p-3 border rounded-4 d-flex align-items-center text-decoration-none shadow-sm">
                            <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 me-3">
                                <i class="bi bi-layers"></i>
                            </div>
                            <div>
                                <p class="mb-0 fw-bold text-dark">Master Registry</p>
                                <small class="text-muted">Configure furniture types</small>
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
                // Get the text from the specific span inside the row
                const itemName = item.querySelector('.item-name').textContent.toLowerCase();
                
                if (itemName.includes(searchValue)) {
                    item.setAttribute('style', 'display: flex !important');
                } else {
                    item.setAttribute('style', 'display: none !important');
                }
            });
        });
    } else {
        console.error("Search input with ID 'itemSearch' not found!");
    }
});
</script>


<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>