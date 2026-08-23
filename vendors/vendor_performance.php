<?php
session_start();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

$page_title = "Vendor Performance Analytics";

/**
 * Helper function to retrieve vendor analytics filtered by sector category
 */
function getVendorAnalyticsByCategory(mysqli $conn, string $category) {
    $stmt = $conn->prepare("
        SELECT 
            v.id, v.vendor_name, v.category, v.email,
            -- Sector-specific total spend calculations
            COALESCE((SELECT SUM(amount) FROM stock_details WHERE vendor_id = v.id), 0) + 
            COALESCE((SELECT SUM(total_qty * unit_price) FROM furniture_stock WHERE vendor_id = v.id), 0) + 
            COALESCE((SELECT SUM(total_qty * unit_price) FROM electrical_stock WHERE vendor_id = v.id), 0) as total_spend,

            -- Sector-specific total transactions count
            (SELECT COUNT(*) FROM stock_details WHERE vendor_id = v.id) + 
            (SELECT COUNT(*) FROM furniture_stock WHERE vendor_id = v.id) + 
            (SELECT COUNT(*) FROM electrical_stock WHERE vendor_id = v.id) as total_orders,

            (SELECT COUNT(*) FROM services WHERE vendor_id = v.id) as service_calls,
            COALESCE((SELECT SUM(amount) FROM services WHERE vendor_id = v.id), 0) as service_costs,
            (SELECT COUNT(*) FROM stock_details WHERE vendor_id = v.id AND status = 'maintenance') as repair_count
        FROM vendors v
        WHERE v.category = ?
        GROUP BY v.id
        ORDER BY total_spend DESC
    ");
    
    $stmt->bind_param("s", $category);
    $stmt->execute();
    return $stmt->get_result();
}

// Map the 3 primary sectors to their query parameters
$categories = [
    'Computer'   => getVendorAnalyticsByCategory($conn, 'Computer'),
    'Furniture'  => getVendorAnalyticsByCategory($conn, 'Furniture'),
    'Electrical' => getVendorAnalyticsByCategory($conn, 'Electricals') // Matches database string
];

ob_start();
?>

<style>
    /* ERP Design System Tokens */
    :root {
        --erp-navy: #123b63;
        --erp-bg: #f8fafc;
        --erp-card-bg: #ffffff;
        --erp-border: #d9e0e7;
        --erp-border-subtle: #f1f5f9;
        --erp-text-main: #20384d;
        --erp-text-muted: #64748b;
        --erp-shadow-sm: 0 1px 3px rgba(20,45,70,.05);
    }

    /* Page Header */
    .erp-header-title {
        font-weight: 800;
        color: var(--erp-text-main);
        letter-spacing: -0.01em;
        font-size: 1.35rem;
    }

    .erp-header-sub {
        font-size: 0.8125rem;
        color: var(--erp-text-muted);
    }

    /* Search Input Styling */
    .global-search-wrapper {
        position: relative;
        max-width: 320px;
        width: 100%;
    }

    .global-search-input {
        border-radius: 6px;
        border: 1px solid var(--erp-border);
        padding: 0.45rem 0.85rem 0.45rem 2.25rem;
        font-size: 0.85rem;
        width: 100%;
        background: #ffffff;
        transition: all 0.15s ease;
    }

    .global-search-input:focus {
        border-color: var(--erp-navy);
        box-shadow: 0 0 0 3px rgba(18, 59, 99, 0.12);
        outline: none;
    }

    .global-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--erp-text-muted);
        font-size: 0.85rem;
    }

    /* Tabs Styling */
    .erp-tabs .nav-link { 
        color: var(--erp-text-muted); 
        font-weight: 600; 
        border-radius: 6px; 
        padding: 0.55rem 1.25rem; 
        font-size: 0.85rem;
        background: #ffffff;
        border: 1px solid var(--erp-border); 
        transition: all 0.2s ease; 
    }

    .erp-tabs .nav-link.active { 
        background: var(--erp-navy) !important; 
        color: #ffffff !important; 
        border-color: var(--erp-navy) !important;
        box-shadow: var(--erp-shadow-sm); 
    }

    .erp-tabs .nav-link:hover:not(.active) { 
        background: #f8fafc; 
        color: var(--erp-text-main); 
    }

    /* Enterprise Accordion Card Styles */
    .erp-accordion-item {
        border: 1px solid var(--erp-border);
        background: var(--erp-card-bg);
        border-radius: 10px !important;
        box-shadow: var(--erp-shadow-sm);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    /* Highlighted state for active Service History inner card */
    .perf-card.perf-card-active-service {
        background-color: #e3e8ee; 
        border-color: #cbd5e1;
        border-left: 3px solid var(--erp-navy);
    }

    .erp-accordion-item:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .erp-accordion-button {
        background: transparent !important;
        box-shadow: none !important;
        padding: 1rem 1.25rem;
    }

    .erp-accordion-button:not(.collapsed) {
        border-bottom: 1px solid var(--erp-border-subtle);
    }

    /* Typography & Analytics Metrics */
    .stat-label { 
        font-size: 0.6875rem; 
        text-transform: uppercase; 
        letter-spacing: 0.05em; 
        font-weight: 700;
        color: var(--erp-text-muted); 
        margin-bottom: 2px;
    }

    .vendor-title {
        font-weight: 800;
        color: var(--erp-navy);
        font-size: 1rem;
        letter-spacing: -0.01em;
    }

    .metric-value {
        font-size: 0.875rem;
        font-weight: 700;
    }

    /* Inner Performance Metric Cards */
    .perf-card { 
        border-radius: 8px; 
        border: 1px solid var(--erp-border); 
        background: #ffffff; 
        padding: 12px 16px; 
        text-align: left; 
    }

    .perf-card .metric-header {
        font-size: 0.9375rem;
        font-weight: 800;
        color: var(--erp-text-main);
    }

    /* ERP Status Badges */
    .erp-status-badge {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
    }

    .erp-status-stable {
        background-color: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .erp-status-warning {
        background-color: #fffbeb;
        color: #92400e;
        border: 1px solid #fef08a;
    }

    .maint-pulse-badge {
        font-size: 0.725rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        background-color: #fef2f2;
        color: #991b1b;
        border: 1px solid #fca5a5;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* Pulsing red dot */
    .maint-pulse-badge::before {
        content: '';
        display: inline-block;
        width: 6px;
        height: 6px;
        background-color: #dc2626;
        border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.5);
        animation: maint-pulse 1.8s infinite;
    }

    @keyframes maint-pulse {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.6);
        }
        70% {
            transform: scale(1);
            box-shadow: 0 0 0 5px rgba(220, 38, 38, 0);
        }
        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(220, 38, 38, 0);
        }
    }

    .empty-state-card {
        border: 1px dashed var(--erp-border);
        background: var(--erp-card-bg);
        border-radius: 10px;
    }
</style>

<div class="container-fluid p-0">
    <!-- Header Block (Matched exact design) -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="erp-header-title mb-1">Vendor Performance Analytics</h4>
            <p class="erp-header-sub mb-0">Comparative procurement and operational performance matrix by sector.</p>
        </div>
        <div>
            <!-- Global Vendor Search Bar -->
            <div class="global-search-wrapper">
                <i class="bi bi-search global-search-icon"></i>
                <input type="text" id="analyticsVendorSearch" class="global-search-input" placeholder="Search vendor name...">
            </div>
        </div>
    </div>

    <!-- Category Tabs (Positioned Bottom Left below header title) -->
    <ul class="nav nav-pills erp-tabs mb-4 gap-2" id="vendorCategoryTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="computer-tab" data-bs-toggle="pill" data-bs-target="#tab-computer" type="button" role="tab">
                <i class="bi bi-pc-display me-1"></i> Computer
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="furniture-tab" data-bs-toggle="pill" data-bs-target="#tab-furniture" type="button" role="tab">
                <i class="bi bi-box-seam me-1"></i> Furniture
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="electrical-tab" data-bs-toggle="pill" data-bs-target="#tab-electrical" type="button" role="tab">
                <i class="bi bi-plug-fill me-1"></i> Electricals
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="vendorCategoryContent">
        <?php 
        $first_tab = true;
        foreach ($categories as $catKey => $vendor_result): 
            $tab_id = strtolower($catKey);
        ?>
            <div class="tab-pane fade <?= $first_tab ? 'show active' : '' ?>" id="tab-<?= $tab_id ?>" role="tabpanel">
                
                <?php if ($vendor_result->num_rows > 0): ?>
                    <div class="accordion" id="accordion-<?= $tab_id ?>">
                        <?php while($v = $vendor_result->fetch_assoc()): ?>
                            <div class="accordion-item erp-accordion-item mb-3 overflow-hidden <?= $v['service_calls'] > 0 ? 'has-service-history' : '' ?>">
                                <h2 class="accordion-header">
                                    <button class="accordion-button erp-accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#v-<?= $tab_id ?>-<?= $v['id'] ?>">
                                        <div class="row w-100 align-items-center g-2">
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <!-- Class vendor-title used for specific JS name matching -->
                                                    <span class="vendor-title"><?= htmlspecialchars($v['vendor_name']) ?></span>
                                                    
                                                    <!-- Collapsed Maintenance Warning Indicator -->
                                                    <?php if ($v['repair_count'] > 0): ?>
                                                        <span class="maint-pulse-badge" title="Active items under maintenance">
                                                            <i class="bi bi-tools me-1"></i><?= $v['repair_count'] ?> 
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($v['email'] ?: 'No primary contact recorded') ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="stat-label">Total Spend</div>
                                                <div class="metric-value text-dark"><?= inr($v['total_spend'], true) ?></div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="stat-label">Stock Volume</div>
                                                <div class="metric-value text-secondary"><?= $v['total_orders'] ?> Items Procured</div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="stat-label">Status</div>
                                                <?php 
                                                    if($v['service_calls'] > 5) {
                                                        echo '<span class="erp-status-badge erp-status-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>High Maint.</span>';
                                                    } else {
                                                        echo '<span class="erp-status-badge erp-status-stable"><i class="bi bi-check-circle-fill me-1"></i>Stable</span>';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="v-<?= $tab_id ?>-<?= $v['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#accordion-<?= $tab_id ?>">
                                    <div class="accordion-body bg-white border-top p-3">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="perf-card <?= $v['service_calls'] > 0 ? 'perf-card-active-service' : '' ?>">
                                                    <div class="stat-label">Service History</div>
                                                    <div class="metric-header"><?= $v['service_calls'] ?> Calls Logged</div>
                                                    <div class="small text-muted mt-0.5"><?= inr($v['service_costs'], true) ?> cumulative spend</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="perf-card">
                                                    <div class="stat-label">Maintenance Load</div>
                                                    <div class="metric-header <?= $v['repair_count'] > 0 ? 'text-danger' : '' ?>"><?= $v['repair_count'] ?> Active Units</div>
                                                    <div class="small text-muted mt-0.5">Assets currently undergoing repair</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="perf-card">
                                                    <div class="stat-label">Contact Details</div>
                                                    <div class="metric-header text-truncate" style="font-size: 0.875rem;"><?= htmlspecialchars($v['email'] ?: 'N/A') ?></div>
                                                    <div class="small text-muted mt-0.5">Official communication dispatch</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state-card text-center py-5">
                        <div class="card-body">
                            <i class="bi bi-inbox fs-2 text-muted mb-2 d-block"></i>
                            <h6 class="fw-bold text-secondary mb-1">No Vendors Registered</h6>
                            <p class="text-muted small mb-0">There are no records associated with this procurement category.</p>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php 
            $first_tab = false;
        endforeach; 
        ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Reference to default tab button (Computer tab)
    let defaultTabBtn = document.querySelector('#vendorCategoryTabs button[data-bs-target="#tab-computer"]');

    // Keep track of active tab when manually clicked
    document.querySelectorAll('#vendorCategoryTabs button').forEach(button => {
        button.addEventListener('click', function() {
            if (!document.getElementById('analyticsVendorSearch').value.trim()) {
                defaultTabBtn = this;
            }
        });
    });

    // REAL-TIME VENDOR NAME CROSS-TAB SEARCH
    const searchInput = document.getElementById('analyticsVendorSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const tabPanes = document.querySelectorAll('.tab-pane');
            let firstTabWithMatches = null;

            tabPanes.forEach(pane => {
                const accordionItems = pane.querySelectorAll('.accordion-item');
                let matchCountInPane = 0;

                accordionItems.forEach(item => {
                    // Filter strictly by the vendor_name element text
                    const vendorTitleEl = item.querySelector('.vendor-title');
                    const vendorName = vendorTitleEl ? vendorTitleEl.innerText.toLowerCase() : '';

                    if (query === '' || vendorName.includes(query)) {
                        item.style.display = '';
                        matchCountInPane++;
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (matchCountInPane > 0 && !firstTabWithMatches) {
                    firstTabWithMatches = pane.getAttribute('id');
                }
            });

            if (query.length > 0) {
                // If current active tab has zero matches, switch to the first tab that has matches
                const activePane = document.querySelector('.tab-pane.show.active');
                const activeMatches = activePane ? activePane.querySelectorAll('.accordion-item:not([style*="display: none"])').length : 0;

                if (activeMatches === 0 && firstTabWithMatches) {
                    const targetBtn = document.querySelector(`#vendorCategoryTabs button[data-bs-target="#${firstTabWithMatches}"]`);
                    if (targetBtn) {
                        const tabTrigger = bootstrap.Tab.getOrCreateInstance(targetBtn);
                        tabTrigger.show();
                    }
                }
            } else {
                // Reset search: Revert active tab back to default
                if (defaultTabBtn) {
                    const tabTrigger = bootstrap.Tab.getOrCreateInstance(defaultTabBtn);
                    tabTrigger.show();
                }
            }
        });
    }
});
</script>

<?php 
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>