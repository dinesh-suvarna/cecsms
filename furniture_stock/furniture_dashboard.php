<?php
require_once "../admin/auth.php"; 
include "../config/db.php";

$role = $_SESSION["role"] ?? 'User'; 
$user_division = $_SESSION['division_id'] ?? 0;
$page_title = "Furniture Dashboard";

// 1. Logic for Total Assets
$total_qty_sql = "SELECT SUM(s.total_qty) as total 
                  FROM furniture_stock s 
                  JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $total_qty_sql .= " WHERE u.division_id = '$user_division'";
}
$total_assets = $conn->query($total_qty_sql)->fetch_assoc()['total'] ?? 0;

// 2. Logic for Inventory Breakdown List
$cat_query = "SELECT i.item_name, SUM(s.total_qty) as count 
              FROM furniture_stock s
              JOIN furniture_items i ON s.furniture_item_id = i.id
              JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $cat_query .= " WHERE u.division_id = '$user_division'";
}
$cat_query .= " GROUP BY i.item_name ORDER BY count DESC";
$categories = $conn->query($cat_query);

// 3. Logic for Recent Activity
$recent_sql = "SELECT fa.asset_tag, i.item_name, fa.created_at 
               FROM furniture_assets fa 
               JOIN furniture_stock s ON fa.stock_id = s.id 
               JOIN furniture_items i ON s.furniture_item_id = i.id 
               JOIN units u ON s.unit_id = u.id";
if ($role !== 'SuperAdmin') {
    $recent_sql .= " WHERE u.division_id = '$user_division'";
}
$recent_sql .= " ORDER BY fa.id DESC LIMIT 5";
$recent_activities = $conn->query($recent_sql);

ob_start();
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

    :root {
        --brand-indigo: #4f46e5;
        --brand-slate: #1e293b;
        --brand-emerald: #10b981;
        --bg-main: #f0f2f5;
    }

    body { background: var(--bg-main); font-family: 'Inter', sans-serif; color: var(--brand-slate); }

    /* Hero Section (From File 2) */
    .hero-stat-card {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: white;
        border-radius: 24px;
        padding: 2.5rem;
        position: relative;
        overflow: hidden;
        height: 100%;
    }

    .hero-stat-card::after {
        content: '';
        position: absolute;
        bottom: -20px; right: -20px;
        width: 150px; height: 150px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }

    /* Breakdown Card */
    .bento-card {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        height: 100%;
    }

    .stock-item-row {
        transition: all 0.2s ease;
        border-radius: 12px;
        padding: 10px;
        margin-bottom: 5px;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .count-pill {
        background: var(--brand-slate);
        color: white;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    /* Functional Modules (Bento Style from File 1) */
    .feature-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        border: 1px solid transparent;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: block;
        text-decoration: none !important;
        color: inherit;
    }

    .feature-card:hover {
        transform: translateY(-5px);
        border-color: var(--brand-indigo);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .icon-box {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.5rem;
    }

    .section-label {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #94a3b8;
        margin-bottom: 1.5rem;
        display: flex; align-items: center; gap: 10px;
    }
    .section-label::after { content: ""; height: 1px; flex-grow: 1; background: #cbd5e1; }

    .custom-scroll { max-height: 300px; overflow-y: auto; }
    .custom-scroll::-webkit-scrollbar { width: 4px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    .activity-feed { background: #1e293b; color: white; border-radius: 24px; padding: 24px; }
</style>

<div class="container-fluid pt-2 pb-4 px-lg-5">
    
    <div class="row mb-3 align-items-center">
        <div class="col-md-7">
            <h2 class="fw-bold mb-0">System Overview</h2>
            <p class="text-muted mb-0">Welcome back. Here is what’s happening with your inventory today.</p>
        </div>
    </div>
    
    <div class="row g-4 mb-5">
        <div class="col-lg-4">
            <div class="hero-stat-card shadow-sm d-flex flex-column justify-content-between">
                <div>
                    <span class="badge bg-success px-3 py-2 rounded-pill mb-3">Live Sync Active</span>
                    <p class="opacity-75 mb-1 fw-medium">Total Furniture Managed</p>
                    <h1 class="display-3 fw-bold mb-0"><?= number_format($total_assets) ?></h1>
                </div>
                <div class="mt-4 pt-3 border-top border-white border-opacity-10 d-flex align-items-center gap-3">
                    <div class="spinner-grow spinner-grow-sm text-success" role="status"></div>
                    <div>
                        <small class="d-block opacity-75">Furniture Tracking System</small>
                        <small class="extra-small opacity-50" style="font-size: 0.7rem;">Monitoring <?= $categories->num_rows ?> categories</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="bento-card p-4 shadow-sm">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="fw-bold mb-0">Inventory Breakdown</h5>
                        <p class="text-muted small mb-0">Instant stock lookup</p>
                    </div>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" id="itemSearch" class="form-control rounded-pill ps-5 border-light bg-light" placeholder="Search items...">
                    </div>
                </div>
                
                <div class="custom-scroll px-1" id="stockContainer">
                    <?php if($categories->num_rows > 0): 
                        while($cat = $categories->fetch_assoc()): ?>
                        <div class="stock-item-row d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-primary bg-opacity-10 text-primary mb-0 me-3" style="width:35px; height:35px; font-size:1rem;">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                                <span class="fw-bold text-dark item-name"><?= htmlspecialchars($cat['item_name']) ?></span>
                            </div>
                            <div class="count-pill"><?= number_format($cat['count']) ?></div>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="text-center text-muted py-4">No categories found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-5">
        <div class="col-12"><div class="section-label">Core Inventory & Assets</div></div>
        
        <div class="col-md-3">
            <a href="add_furniture.php" class="feature-card shadow-sm">
                <div class="icon-box bg-primary bg-opacity-10 text-primary"><i class="bi bi-plus-circle"></i></div>
                <div class="feature-title">Add Stock</div>
                <div class="feature-desc">Inbound furniture entry & volume management.</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="tag_assets.php" class="feature-card shadow-sm">
                <div class="icon-box bg-indigo bg-opacity-10 text-indigo" style="color: #6366f1;"><i class="bi bi-qr-code"></i></div>
                <div class="feature-title">Asset Tagging</div>
                <div class="feature-desc">Generate and assign unique IDs to items.</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="view_furniture.php" class="feature-card shadow-sm">
                <div class="icon-box bg-info bg-opacity-10 text-info"><i class="bi bi-box-seam"></i></div>
                <div class="feature-title">Stock Registry</div>
                <div class="feature-desc">Live overview of available furniture types.</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="view_assets.php" class="feature-card shadow-sm">
                <div class="icon-box bg-dark bg-opacity-10 text-dark"><i class="bi bi-search"></i></div>
                <div class="feature-title">Audit Assets</div>
                <div class="feature-desc">Track location and status of tagged assets.</div>
            </a>
        </div>
         <?php if(in_array($role, ['SuperAdmin'])): ?>               
        <div class="col-12 mt-5"><div class="section-label">Logistics & Intelligence</div></div>
        
        <div class="col-lg-8">
            <div class="row g-4">
                <div class="col-md-6">
                    <a href="dispatch_furniture.php" class="feature-card d-flex align-items-center gap-3 shadow-sm">
                        <div class="icon-box bg-teal bg-opacity-10 text-teal mb-0"><i class="bi bi-truck"></i></div>
                        <div>
                            <div class="feature-title mb-0">Dispatch Hub</div>
                            <div class="feature-desc small">Transfer furniture to units.</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="view_furniture_central_stock.php" class="feature-card d-flex align-items-center gap-3 shadow-sm">
                        <div class="icon-box bg-warning bg-opacity-10 text-warning mb-0"><i class="bi bi-building"></i></div>
                        <div>
                            <div class="feature-title mb-0">Central Stock</div>
                            <div class="feature-desc small">Main warehouse reserves.</div>
                        </div>
                    </a>
                </div>
                <div class="col-12">
                    <div class="p-4 rounded-4 shadow-sm" style="background: linear-gradient(90deg, #4f46e5, #818cf8); color: white;">
                        <h5 class="fw-bold">Furniture Registry Master</h5>
                        <p class="mb-3 opacity-75 small">Manage the taxonomy of furniture items across the organization.</p>
                        <div class="d-flex gap-2">
                            <a href="view_purchase_ledger.php" class="btn btn-light btn-sm fw-bold px-4 rounded-pill">Open Registry</a>
                            <a href="furniture_stockreports.php" class="btn btn-outline-light btn-sm fw-bold px-4 rounded-pill">View Analytics</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        

        <div class="col-lg-4">
            <div class="activity-feed shadow-lg h-100">
                <div class="mb-4 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold">Recent Deployments</h6>
                    <span class="badge bg-primary rounded-pill">Live</span>
                </div>
                <div class="custom-scroll">
                    <?php while($log = $recent_activities->fetch_assoc()): ?>
                    <div class="d-flex gap-3 mb-4">
                        <div class="border-start border-2 border-primary ps-3">
                            <div class="small fw-bold text-white">#<?= $log['asset_tag'] ?></div>
                            <div class="extra-small opacity-50"><?= $log['item_name'] ?></div>
                            <div class="extra-small text-primary" style="font-size: 0.7rem;"><?= date('H:i', strtotime($log['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
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