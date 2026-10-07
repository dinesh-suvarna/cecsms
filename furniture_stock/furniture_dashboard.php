<?php
require_once "../admin/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

$role = $_SESSION["role"] ?? 'User';
$user_division = $_SESSION['division_id'] ?? 0;
$page_title = "Furniture Dashboard";

/* =========================
   1. TOTAL FURNITURE
   ========================= */
$total_qty_sql = "SELECT SUM(s.total_qty) as total 
                  FROM furniture_stock s 
                  JOIN units u ON s.unit_id = u.id";

if ($role !== 'SuperAdmin') {
    $total_qty_sql .= " WHERE u.division_id = '$user_division'";
}

$total_assets = $conn->query($total_qty_sql)->fetch_assoc()['total'] ?? 0;

/* =========================
   2. INVENTORY BREAKDOWN
   ========================= */
$cat_query = "SELECT i.item_name, SUM(s.total_qty) as count 
              FROM furniture_stock s
              JOIN furniture_items i ON s.furniture_item_id = i.id
              JOIN units u ON s.unit_id = u.id";

if ($role !== 'SuperAdmin') {
    $cat_query .= " WHERE u.division_id = '$user_division'";
}

$cat_query .= " GROUP BY i.item_name ORDER BY count DESC";
$categories = $conn->query($cat_query);

/* =========================
   3. RECENT ACTIVITY
   ========================= */
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
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    :root {
        --fd-primary: #2563eb;
        --fd-primary-dark: #1d4ed8;
        --fd-navy: #172033;
        --fd-text: #1e293b;
        --fd-muted: #64748b;
        --fd-border: #e2e8f0;
        --fd-bg: #f8fafc;
        --fd-white: #ffffff;
        --fd-success: #059669;
        --fd-warning: #d97706;
        --fd-info: #0891b2;
    }

    .furniture-dashboard {
        background: var(--fd-bg);
        min-height: calc(100vh - 70px);
        color: var(--fd-text);
        font-family: 'Inter', sans-serif;
        padding: 24px 0 40px;
    }

    .fd-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 28px;
    }

    /* Header */
    .fd-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .fd-title-wrap {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .fd-title-icon {
        width: 46px;
        height: 46px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: #eff6ff;
        color: var(--fd-primary);
        font-size: 1.35rem;
        border: 1px solid #dbeafe;
    }

    .fd-page-title {
        margin: 0;
        font-size: 1.45rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--fd-navy);
    }

    .fd-page-subtitle {
        margin: 3px 0 0;
        color: var(--fd-muted);
        font-size: 0.82rem;
    }

    .fd-live-status {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border: 1px solid #d1fae5;
        background: #ecfdf5;
        color: #047857;
        border-radius: 8px;
        font-size: 0.76rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .fd-live-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 0 3px rgba(16,185,129,.12);
    }

    /* KPI Cards */
    .fd-kpi-card {
        background: var(--fd-white);
        border: 1px solid var(--fd-border);
        border-radius: 12px;
        padding: 19px;
        height: 100%;
        box-shadow: 0 1px 2px rgba(15,23,42,.03);
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .fd-kpi-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 8px 22px rgba(15,23,42,.06);
        transform: translateY(-2px);
    }

    .fd-kpi-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .fd-kpi-label {
        color: var(--fd-muted);
        font-size: 0.76rem;
        font-weight: 600;
        margin-bottom: 7px;
    }

    .fd-kpi-value {
        color: var(--fd-navy);
        font-size: 1.65rem;
        line-height: 1;
        font-weight: 700;
        letter-spacing: -0.03em;
    }

    .fd-kpi-icon {
        width: 40px;
        height: 40px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .fd-icon-blue { background: #eff6ff; color: #2563eb; }
    .fd-icon-indigo { background: #eef2ff; color: #4f46e5; }
    .fd-icon-emerald { background: #ecfdf5; color: #059669; }
    .fd-icon-amber { background: #fffbeb; color: #d97706; }

    .fd-kpi-note {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid #f1f5f9;
        color: #94a3b8;
        font-size: 0.7rem;
    }

    /* Main panels */
    .fd-panel {
        background: var(--fd-white);
        border: 1px solid var(--fd-border);
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(15,23,42,.03);
        overflow: hidden;
    }

    .fd-panel-header {
        padding: 18px 20px;
        border-bottom: 1px solid var(--fd-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .fd-panel-title {
        margin: 0;
        font-size: 0.94rem;
        font-weight: 700;
        color: var(--fd-navy);
    }

    .fd-panel-subtitle {
        margin: 3px 0 0;
        color: #94a3b8;
        font-size: 0.72rem;
    }

    .fd-search {
        width: 230px;
        height: 36px;
        border: 1px solid var(--fd-border);
        border-radius: 8px;
        background: #fff;
        font-size: 0.78rem;
        color: var(--fd-text);
        transition: border-color .2s ease, box-shadow .2s ease;
    }

    .fd-search:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(37,99,235,.08);
    }

    .fd-search::placeholder {
        color: #94a3b8;
    }

    /* Inventory */
    .fd-inventory {
        max-height: 360px;
        overflow-y: auto;
    }

    .fd-inventory::-webkit-scrollbar {
        width: 5px;
    }

    .fd-inventory::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .fd-inventory-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        min-height: 57px;
        padding: 9px 20px;
        border-bottom: 1px solid #f1f5f9;
        transition: background .15s ease;
    }

    .fd-inventory-row:last-child {
        border-bottom: 0;
    }

    .fd-inventory-row:hover {
        background: #f8fafc;
    }

    .fd-item-info {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .fd-item-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: #f1f5f9;
        color: #475569;
        font-size: .85rem;
    }

    .fd-item-name {
        font-size: 0.8rem;
        font-weight: 600;
        color: #334155;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .fd-quantity {
        min-width: 52px;
        text-align: center;
        padding: 5px 9px;
        border-radius: 6px;
        background: #f1f5f9;
        color: #334155;
        font-size: 0.73rem;
        font-weight: 700;
    }

    .fd-empty {
        padding: 45px 20px;
        text-align: center;
        color: #94a3b8;
        font-size: .8rem;
    }

    /* Quick actions */
    .fd-section-heading {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 30px 0 14px;
    }

    .fd-section-heading h5 {
        margin: 0;
        color: var(--fd-navy);
        font-size: .92rem;
        font-weight: 700;
    }

    .fd-section-line {
        flex: 1;
        height: 1px;
        background: var(--fd-border);
    }

    .fd-action-card {
        display: flex;
        align-items: center;
        gap: 13px;
        min-height: 78px;
        padding: 14px;
        background: #fff;
        border: 1px solid var(--fd-border);
        border-radius: 10px;
        color: inherit;
        text-decoration: none !important;
        box-shadow: 0 1px 2px rgba(15,23,42,.02);
        transition: all .2s ease;
    }

    .fd-action-card:hover {
        color: inherit;
        border-color: #bfdbfe;
        box-shadow: 0 7px 18px rgba(15,23,42,.06);
        transform: translateY(-2px);
    }

    .fd-action-icon {
        width: 40px;
        height: 40px;
        flex-shrink: 0;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .fd-action-title {
        margin: 0 0 3px;
        font-size: .8rem;
        font-weight: 700;
        color: #334155;
    }

    .fd-action-desc {
        margin: 0;
        color: #94a3b8;
        font-size: .68rem;
        line-height: 1.35;
    }

    .fd-arrow {
        margin-left: auto;
        color: #cbd5e1;
        font-size: .78rem;
        transition: transform .2s ease;
    }

    .fd-action-card:hover .fd-arrow {
        transform: translateX(3px);
        color: var(--fd-primary);
    }

    /* Activity */
    .fd-activity-panel {
        height: 100%;
    }

    .fd-activity-live {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 5px;
        background: #ecfdf5;
        color: #047857;
        font-size: .65rem;
        font-weight: 700;
    }

    .fd-activity-list {
        max-height: 320px;
        overflow-y: auto;
    }

    .fd-activity-list::-webkit-scrollbar {
        width: 4px;
    }

    .fd-activity-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .fd-activity-item {
        position: relative;
        display: flex;
        gap: 12px;
        padding: 15px 20px;
        border-bottom: 1px solid #f1f5f9;
    }

    .fd-activity-item:last-child {
        border-bottom: 0;
    }

    .fd-activity-marker {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: #eff6ff;
        color: var(--fd-primary);
        font-size: .78rem;
    }

    .fd-activity-content {
        min-width: 0;
    }

    .fd-activity-tag {
        font-size: .75rem;
        font-weight: 700;
        color: #334155;
    }

    .fd-activity-item-name {
        margin-top: 2px;
        font-size: .69rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .fd-activity-time {
        margin-top: 4px;
        font-size: .64rem;
        color: #94a3b8;
    }

    .fd-no-activity {
        padding: 45px 20px;
        text-align: center;
        color: #94a3b8;
        font-size: .78rem;
    }

    /* SuperAdmin section */
    .fd-admin-panel {
        margin-top: 30px;
        background: #fff;
        border: 1px solid var(--fd-border);
        border-radius: 12px;
        overflow: hidden;
    }

    .fd-admin-header {
        padding: 17px 20px;
        border-bottom: 1px solid var(--fd-border);
    }

    .fd-admin-header h5 {
        margin: 0;
        font-size: .92rem;
        font-weight: 700;
        color: var(--fd-navy);
    }

    .fd-admin-header p {
        margin: 3px 0 0;
        color: #94a3b8;
        font-size: .7rem;
    }

    .fd-admin-body {
        padding: 20px;
    }

    .fd-registry-banner {
        padding: 20px;
        border-radius: 10px;
        background: linear-gradient(135deg, #172554 0%, #1e40af 100%);
        color: #fff;
        height: 100%;
    }

    .fd-registry-banner h5 {
        margin: 0 0 5px;
        font-size: .95rem;
        font-weight: 700;
    }

    .fd-registry-banner p {
        margin: 0 0 15px;
        color: rgba(255,255,255,.7);
        font-size: .7rem;
        line-height: 1.5;
    }

    .fd-registry-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .fd-registry-buttons .btn {
        font-size: .7rem;
        font-weight: 600;
        border-radius: 7px;
        padding: 7px 13px;
    }

    /* Admin action cards */
    .fd-admin-action {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px;
        border: 1px solid var(--fd-border);
        border-radius: 9px;
        height: 100%;
        text-decoration: none !important;
        color: inherit;
        transition: all .2s ease;
    }

    .fd-admin-action:hover {
        color: inherit;
        border-color: #bfdbfe;
        background: #f8fafc;
        transform: translateY(-1px);
    }

    .fd-admin-action-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .fd-admin-action-title {
        margin: 0 0 2px;
        font-size: .76rem;
        font-weight: 700;
        color: #334155;
    }

    .fd-admin-action-desc {
        margin: 0;
        font-size: .65rem;
        color: #94a3b8;
    }

    /* Responsive */
    @media (max-width: 991.98px) {
        .fd-container {
            padding: 0 18px;
        }

        .fd-page-header {
            align-items: flex-start;
        }

        .fd-search {
            width: 190px;
        }
    }

    @media (max-width: 767.98px) {
        .furniture-dashboard {
            padding-top: 16px;
        }

        .fd-container {
            padding: 0 12px;
        }

        .fd-page-header {
            flex-direction: column;
            margin-bottom: 18px;
        }

        .fd-live-status {
            align-self: flex-start;
        }

        .fd-panel-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .fd-search {
            width: 100%;
        }

        .fd-inventory {
            max-height: 300px;
        }

        .fd-kpi-value {
            font-size: 1.45rem;
        }
    }
</style>

<div class="furniture-dashboard">
    <div class="fd-container">

        <!-- KPI CARDS -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-3">
                <div class="fd-kpi-card">
                    <div class="fd-kpi-top">
                        <div>
                            <div class="fd-kpi-label">Total Furniture</div>
                            <div class="fd-kpi-value"><?= inr($total_assets) ?></div>
                        </div>
                        <div class="fd-kpi-icon fd-icon-blue">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                    <div class="fd-kpi-note">
                        Total furniture quantity managed
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="fd-kpi-card">
                    <div class="fd-kpi-top">
                        <div>
                            <div class="fd-kpi-label">Furniture Categories</div>
                            <div class="fd-kpi-value"><?= $categories->num_rows ?></div>
                        </div>
                        <div class="fd-kpi-icon fd-icon-indigo">
                            <i class="bi bi-collection"></i>
                        </div>
                    </div>
                    <div class="fd-kpi-note">
                        Active furniture item types
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="fd-kpi-card">
                    <div class="fd-kpi-top">
                        <div>
                            <div class="fd-kpi-label">Recent Activity</div>
                            <div class="fd-kpi-value">
                                <?= $recent_activities ? $recent_activities->num_rows : 0 ?>
                            </div>
                        </div>
                        <div class="fd-kpi-icon fd-icon-emerald">
                            <i class="bi bi-upc-scan"></i>
                        </div>
                    </div>
                    <div class="fd-kpi-note">
                        Latest deployment records shown
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="fd-kpi-card">
                    <div class="fd-kpi-top">
                        <div>
                            <div class="fd-kpi-label">Tracking Status</div>
                            <div class="fd-kpi-value" style="font-size:1.25rem;">Active</div>
                        </div>
                        <div class="fd-kpi-icon fd-icon-amber">
                            <i class="bi bi-activity"></i>
                        </div>
                    </div>
                    <div class="fd-kpi-note">
                        Furniture tracking system
                    </div>
                </div>
            </div>

        </div>

        <!-- INVENTORY + ACTIVITY -->
        <div class="row g-3">

            <div class="col-lg-8">
                <div class="fd-panel">
                    <div class="fd-panel-header">
                        <div>
                            <h5 class="fd-panel-title">Inventory Breakdown</h5>
                            <p class="fd-panel-subtitle">Current furniture quantity by item</p>
                        </div>

                        <div class="position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"
                               style="font-size:.75rem;"></i>
                            <input type="text"
                                   id="itemSearch"
                                   class="form-control fd-search ps-5"
                                   placeholder="Search furniture...">
                        </div>
                    </div>

                    <div class="fd-inventory" id="stockContainer">
                        <?php if($categories->num_rows > 0): ?>
                            <?php while($cat = $categories->fetch_assoc()): ?>
                                <div class="fd-inventory-row">
                                    <div class="fd-item-info">
                                        <div class="fd-item-icon">
                                            <i class="bi bi-box"></i>
                                        </div>
                                        <span class="fd-item-name">
                                            <?= htmlspecialchars($cat['item_name']) ?>
                                        </span>
                                    </div>

                                    <div class="fd-quantity">
                                        <?= inr($cat['count']) ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="fd-empty">
                                <i class="bi bi-inbox d-block mb-2" style="font-size:1.4rem;"></i>
                                No furniture categories found.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RECENT ACTIVITY -->
            <div class="col-lg-4">
                <div class="fd-panel fd-activity-panel">
                    <div class="fd-panel-header">
                        <div>
                            <h5 class="fd-panel-title">Recent Deployments</h5>
                            <p class="fd-panel-subtitle">Latest tagged furniture activity</p>
                        </div>

                        <span class="fd-activity-live">
                            <span class="fd-live-dot"></span>
                            Live
                        </span>
                    </div>

                    <div class="fd-activity-list">
                        <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                            <?php while($log = $recent_activities->fetch_assoc()): ?>
                                <div class="fd-activity-item">
                                    <div class="fd-activity-marker">
                                        <i class="bi bi-upc-scan"></i>
                                    </div>

                                    <div class="fd-activity-content">
                                        <div class="fd-activity-tag">
                                            #<?= htmlspecialchars($log['asset_tag']) ?>
                                        </div>

                                        <div class="fd-activity-item-name">
                                            <?= htmlspecialchars($log['item_name']) ?>
                                        </div>

                                        <div class="fd-activity-time">
                                            <i class="bi bi-clock me-1"></i>
                                            <?= date('d M Y, H:i', strtotime($log['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="fd-no-activity">
                                <i class="bi bi-clock-history d-block mb-2" style="font-size:1.35rem;"></i>
                                No recent deployments found.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- QUICK ACTIONS -->
        <div class="fd-section-heading">
            <h5>Quick Actions</h5>
            <div class="fd-section-line"></div>
        </div>

        <div class="row g-3">

            <div class="col-sm-6 col-lg-3">
                <a href="add_furniture.php" class="fd-action-card">
                    <div class="fd-action-icon fd-icon-blue">
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div>
                        <p class="fd-action-title">Add Stock</p>
                        <p class="fd-action-desc">Add inbound furniture stock and quantities.</p>
                    </div>
                    <i class="bi bi-chevron-right fd-arrow"></i>
                </a>
            </div>

            <div class="col-sm-6 col-lg-3">
                <a href="tag_assets.php" class="fd-action-card">
                    <div class="fd-action-icon fd-icon-indigo">
                        <i class="bi bi-qr-code"></i>
                    </div>
                    <div>
                        <p class="fd-action-title">Asset Tagging</p>
                        <p class="fd-action-desc">Generate and assign unique asset IDs.</p>
                    </div>
                    <i class="bi bi-chevron-right fd-arrow"></i>
                </a>
            </div>

            <div class="col-sm-6 col-lg-3">
                <a href="view_furniture.php" class="fd-action-card">
                    <div class="fd-action-icon" style="background:#ecfeff;color:#0891b2;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <p class="fd-action-title">Stock Registry</p>
                        <p class="fd-action-desc">View available furniture stock records.</p>
                    </div>
                    <i class="bi bi-chevron-right fd-arrow"></i>
                </a>
            </div>

            <div class="col-sm-6 col-lg-3">
                <a href="view_assets.php" class="fd-action-card">
                    <div class="fd-action-icon" style="background:#f1f5f9;color:#334155;">
                        <i class="bi bi-search"></i>
                    </div>
                    <div>
                        <p class="fd-action-title">Audit Assets</p>
                        <p class="fd-action-desc">Track location and status of tagged assets.</p>
                    </div>
                    <i class="bi bi-chevron-right fd-arrow"></i>
                </a>
            </div>

        </div>

        <?php if(in_array($role, ['SuperAdmin'])): ?>

            <!-- SUPERADMIN SECTION -->
            <div class="fd-admin-panel">
                <div class="fd-admin-header">
                    <h5>Administration & Logistics</h5>
                    <p>Central furniture operations and management tools</p>
                </div>

                <div class="fd-admin-body">
                    <div class="row g-3">

                        <div class="col-lg-8">
                            <div class="row g-3">

                                <div class="col-md-6">
                                    <a href="dispatch_furniture.php" class="fd-admin-action">
                                        <div class="fd-admin-action-icon fd-icon-blue">
                                            <i class="bi bi-truck"></i>
                                        </div>
                                        <div>
                                            <p class="fd-admin-action-title">Dispatch Hub</p>
                                            <p class="fd-admin-action-desc">Transfer furniture to units.</p>
                                        </div>
                                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </div>

                                <div class="col-md-6">
                                    <a href="view_furniture_central_stock.php" class="fd-admin-action">
                                        <div class="fd-admin-action-icon fd-icon-amber">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <div>
                                            <p class="fd-admin-action-title">Central Stock</p>
                                            <p class="fd-admin-action-desc">Manage main warehouse reserves.</p>
                                        </div>
                                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                                    </a>
                                </div>

                                <div class="col-12">
                                    <div class="fd-registry-banner">
                                        <h5>Furniture Registry Master</h5>
                                        <p>
                                            Manage the furniture taxonomy and review purchasing
                                            and inventory analytics across the organization.
                                        </p>

                                        <div class="fd-registry-buttons">
                                            <a href="view_purchase_ledger.php"
                                               class="btn btn-light">
                                                <i class="bi bi-journal-text me-1"></i>
                                                Open Registry
                                            </a>

                                            <a href="furniture_stockreports.php"
                                               class="btn btn-outline-light">
                                                <i class="bi bi-bar-chart-line me-1"></i>
                                                View Analytics
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="fd-panel h-100">
                                <div class="fd-panel-header">
                                    <div>
                                        <h5 class="fd-panel-title">System Tools</h5>
                                        <p class="fd-panel-subtitle">Administrative controls</p>
                                    </div>
                                </div>

                                <div class="p-3">
                                    <div class="small text-muted">
                                        Use the administration tools to control central stock,
                                        dispatch operations, registry records and analytics.
                                    </div>
                                </div>
                            </div>
                        </div>

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

            const searchValue = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.fd-inventory-row');

            items.forEach(function(item) {

                const itemNameElement = item.querySelector('.fd-item-name');

                if (!itemNameElement) return;

                const itemName = itemNameElement.textContent.toLowerCase();

                if (itemName.includes(searchValue)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }

            });
        });
    }

});
</script>

<?php
$content = ob_get_clean();
include "furniturelayout.php";
?>
