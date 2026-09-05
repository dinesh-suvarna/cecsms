<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/crypto.php";
require_once __DIR__ . "/../includes/functions.php";
$page_title = "View Stock Details";
$page_icon  = "bi-clipboard-data";

/* ---------- HELPER: DYNAMIC CATEGORY ICONS ---------- */
function getCategoryIcon(string $category): string {
    $cat = strtolower(trim($category));
    if (str_contains($cat, 'computer') || str_contains($cat, 'pc') || str_contains($cat, 'laptop')) {
        return 'bi-pc-display';
    } elseif (str_contains($cat, 'accessory') || str_contains($cat, 'peripherals')) {
        return 'bi-keyboard';
    } elseif (str_contains($cat, 'network') || str_contains($cat, 'router') || str_contains($cat, 'switch')) {
        return 'bi-diagram-3';
    } elseif (str_contains($cat, 'component') || str_contains($cat, 'hardware')) {
        return 'bi-cpu';
    } elseif (str_contains($cat, 'furniture')) {
        return 'bi-lamp';
    } elseif (str_contains($cat, 'mobile') || str_contains($cat, 'phone')) {
        return 'bi-phone';
    }
    return 'bi-folder';
}

/* ================== FETCH DATA WITH DISPATCH MATH ================== */
$query = "
SELECT 
    sd.id,
    sd.quantity AS total_quantity,
    im.item_name,
    IFNULL(NULLIF(im.category, ''), 'Uncategorized') AS category_name,
    sd.serial_number,
    sd.bill_no,
    sd.bill_date,
    sd.po_number,
    v.vendor_name,
    sd.amount,
    sd.status,
    im.stock_type,
    IFNULL((SELECT SUM(quantity - IFNULL(returned_quantity,0)) 
            FROM dispatch_details 
            WHERE stock_detail_id = sd.id), 0) AS dispatched_qty,
    (
        SELECT dd.dispatch_id 
        FROM dispatch_details dd
        WHERE dd.stock_detail_id = sd.id
        ORDER BY dd.dispatch_id DESC
        LIMIT 1
    ) AS last_dispatch_id
FROM stock_details sd
LEFT JOIN items_master im ON sd.stock_item_id = im.id
LEFT JOIN vendors v ON sd.vendor_id = v.id
ORDER BY category_name ASC, im.item_name ASC, sd.bill_date DESC, sd.id DESC
";

$result = $conn->query($query);

/* ================== 3-TIER GROUPING & KPI COUNTS ================== */
$grouped = [];
$totalUnitsCount = 0;
$totalSerialCount = 0;
$totalBulkBatches = 0;
$totalAvailableUnits = 0;

while($row = $result->fetch_assoc()){
    $catName    = !empty($row['category_name']) ? $row['category_name'] : 'Uncategorized';
    $itemName   = $row['item_name'] ?? 'Uncategorized Item';
    $billNo     = !empty($row['bill_no']) ? $row['bill_no'] : 'N/A';
    $vendorName = !empty($row['vendor_name']) ? $row['vendor_name'] : 'Unknown Vendor';
    
    $invoiceKey = md5($billNo . '_' . $vendorName . '_' . $row['bill_date']);
    
    $grouped[$catName][$itemName][$invoiceKey]['meta'] = [
        'bill_no'     => $billNo,
        'vendor_name' => $vendorName,
        'bill_date'   => $row['bill_date'],
        'po_number'   => $row['po_number']
    ];
    $grouped[$catName][$itemName][$invoiceKey]['items'][] = $row;

    // KPI Metrics calculation
    if ($row['stock_type'] === 'serial') {
        $totalUnitsCount += 1;
        $totalSerialCount += 1;
        if ($row['status'] === 'available') {
            $totalAvailableUnits += 1;
        }
    } else {
        $qty = (int)$row['total_quantity'];
        $dispatched = (int)$row['dispatched_qty'];
        $rem = max(0, $qty - $dispatched);
        
        $totalUnitsCount += $qty;
        $totalBulkBatches += 1;
        if ($row['status'] !== 'disposed') {
            $totalAvailableUnits += $rem;
        }
    }
}

$categoriesCount = count($grouped);

ob_start();
?>

<style>
:root {
    --erp-border: #e2e8f0;
    --erp-bg-body: #f8fafc;
    --erp-navy: #173f63;
    --erp-navy-accent: #004085;
    --erp-blue-bg: #dbeafe;
    --erp-blue-text: #1d4ed8;
    --erp-text-muted: #64748b;
}

/* Page Spacing */
.stock-page-wrapper {
    padding: 0.5rem 1.25rem 2rem 1.25rem;
    /* background-color: var(--erp-bg-body); */
}

/* Header UI */
.page-header-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--erp-navy);
}

.header-icon-box {
    width: 42px;
    height: 42px;
    background-color: #eff6ff;
    color: var(--erp-navy-accent);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    border: 1px solid #dbeafe;
}

/* KPI Stat Cards */
.kpi-card {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 8px;
    padding: 1rem 1.25rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.kpi-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
}

.kpi-card.kpi-navy::before { background-color: #0f172a; }
.kpi-card.kpi-blue::before { background-color: #2563eb; }
.kpi-card.kpi-slate::before { background-color: #475569; }
.kpi-card.kpi-green::before { background-color: #16a34a; }

.kpi-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--erp-text-muted);
}

.kpi-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--erp-navy);
    line-height: 1.2;
}

.kpi-icon {
    font-size: 1.6rem;
    opacity: 0.25;
}

/* Toolbar Controls */
.erp-toolbar {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.erp-toolbar .form-control, 
.erp-toolbar .form-select {
    font-size: 0.88rem !important;
    border-color: #cbd5e1;
    border-radius: 6px;
    padding: 0.45rem 0.75rem;
}

.erp-toolbar .form-control:focus, 
.erp-toolbar .form-select:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
}

/* Outer Category Accordion Cards */
.cat-accordion-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 8px !important;
    margin-bottom: 0.75rem;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.cat-accordion-btn {
    background: #ffffff !important;
    padding: 0.9rem 1.25rem;
    border: none;
    box-shadow: none !important;
}

.cat-accordion-btn:not(.collapsed) {
    background: #ffffff !important;
    border-bottom: 1px solid var(--erp-border);
}

.folder-icon-box {
    width: 38px;
    height: 38px;
    background-color: #eff6ff;
    color: var(--erp-navy-accent);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

.cat-title-text {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
}

.badge-cat-count {
    background-color: var(--erp-blue-bg) !important;
    color: var(--erp-blue-text) !important;
    font-weight: 600;
    font-size: 0.82rem;
    padding: 0.4rem 0.85rem;
    border-radius: 50rem;
    border: none;
}

/* Nested Items Master Accordion */
.item-accordion-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 6px !important;
    margin-bottom: 0.6rem;
    background: #ffffff;
}

.item-accordion-btn {
    padding: 0.75rem 1rem;
    background: #ffffff !important;
    font-size: 0.9rem;
}

/* Sub-Group Invoice Block */
.invoice-block {
    border: 1px solid var(--erp-border);
    border-radius: 6px;
    background: #ffffff;
    margin-bottom: 0.85rem;
}

.invoice-header {
    background-color: #f8fafc;
    padding: 0.6rem 0.9rem;
    font-size: 0.84rem;
    border-bottom: 1px solid var(--erp-border);
    color: #334155;
}

/* Tables & Action Buttons */
.erp-subtable {
    margin-bottom: 0;
    font-size: 0.88rem;
}

.erp-subtable thead th {
    background-color: #ffffff;
    color: var(--erp-text-muted);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--erp-border);
    padding: 0.6rem 0.9rem;
}

.erp-subtable td {
    padding: 0.65rem 0.9rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

.action-btn-erp {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    border: 1px solid var(--erp-border);
    background: #ffffff;
    font-size: 0.88rem;
    transition: all 0.15s ease;
}

.action-btn-erp:hover {
    background: #f1f5f9;
    color: var(--erp-navy);
    border-color: #cbd5e1;
}

.badge-erp {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.35rem 0.65rem;
    border-radius: 4px;
}
</style>

<div class="stock-page-wrapper">
    <!-- PAGE HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pt-2 mb-3 pb-2 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <div class="header-icon-box">
                <i class="<?= $page_icon ?>"></i>
            </div>
            <div>
                <h4 class="page-header-title m-0"><?= $page_title ?></h4>
                <p class="text-muted m-0" style="font-size: 0.85rem;">Detailed registry grouped by category, item catalog, and procurement invoice.</p>
            </div>
        </div>
        <div>
            <a href="add_stock_details.php" class="btn btn-primary d-inline-flex align-items-center gap-1.5 px-3 py-2 fw-semibold" style="font-size: 0.88rem; background-color: var(--erp-navy); border-color: var(--erp-navy);">
                <i class="bi bi-plus-lg"></i> Add Stock Entry
            </a>
        </div>
    </div>

    <!-- KPI STAT CARDS -->
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card kpi-navy">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="kpi-title">Total Units Tracked</div>
                        <div class="kpi-value mt-1"><?= number_format($totalUnitsCount) ?></div>
                    </div>
                    <i class="bi bi-box-seam kpi-icon text-dark"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card kpi-blue">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="kpi-title">Serialized Items</div>
                        <div class="kpi-value mt-1 text-primary"><?= number_format($totalSerialCount) ?></div>
                    </div>
                    <i class="bi bi-qr-code-scan kpi-icon text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card kpi-slate">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="kpi-title">Categories Covered</div>
                        <div class="kpi-value mt-1"><?= number_format($categoriesCount) ?></div>
                    </div>
                    <i class="bi bi-tags kpi-icon text-secondary"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card kpi-green">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="kpi-title">Available Units</div>
                        <div class="kpi-value mt-1 text-success"><?= number_format($totalAvailableUnits) ?></div>
                    </div>
                    <i class="bi bi-check-circle kpi-icon text-success"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- SEARCH & FILTER TOOLBAR -->
    <div class="erp-toolbar mb-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-5 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0 shadow-none" placeholder="Search serial, bill, PO, or vendor...">
                    <button id="clearSearchBtn" class="btn btn-outline-secondary border-start-0 border-end border-top border-bottom bg-white text-muted d-none" type="button">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 ms-auto">
                <select id="statusFilter" class="form-select shadow-none">
                    <option value="all">All Statuses</option>
                    <option value="available">Available</option>
                    <option value="partial">Partially Dispatched</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="maintenance">Under Repair / Maintenance</option>
                    <option value="disposed">Scrapped / Disposed</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button id="toggleExpandAll" class="btn btn-outline-secondary px-3" style="font-size: 0.88rem;" title="Expand / Collapse All">
                    <i class="bi bi-arrows-expand me-1"></i> <span id="expandBtnText">Expand All</span>
                </button>
                <button id="resetSearch" class="btn btn-light border text-secondary px-3" style="font-size: 0.88rem;" title="Clear Filters">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                </button>
            </div>
        </div>
    </div>

    <!-- CATEGORY ACCORDION ROOT -->
    <div id="categoryRootAccordion">
        <?php if (!empty($grouped)): ?>
            <?php foreach ($grouped as $catName => $itemsList): 
                $catId = "cat_" . md5($catName);
                
                // Calculate Total Units inside Category
                $catTotalQty = 0;
                foreach ($itemsList as $itemName => $invoices) {
                    foreach ($invoices as $invData) {
                        foreach ($invData['items'] as $s) {
                            $catTotalQty += ($s['stock_type'] === 'serial') ? 1 : (int)$s['total_quantity'];
                        }
                    }
                }
            ?>
                <!-- CATEGORY CARD -->
                <div class="accordion-item cat-accordion-card">
                    <h2 class="accordion-header">
                        <button class="accordion-button cat-accordion-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $catId ?>">
                            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="folder-icon-box">
                                        <i class="bi <?= getCategoryIcon($catName) ?>"></i>
                                    </div>
                                    <span class="cat-title-text"><?= htmlspecialchars($catName) ?></span>
                                </div>
                                <div>
                                    <span class="badge badge-cat-count">
                                        <?= $catTotalQty ?> Items
                                    </span>
                                </div>
                            </div>
                        </button>
                    </h2>

                    <div id="<?= $catId ?>" class="accordion-collapse collapse">
                        <div class="accordion-body p-3" style="background: var(--erp-bg-body);">
                            
                            <!-- NESTED ITEM ACCORDION -->
                            <div id="itemAccordion_<?= $catId ?>">
                                <?php foreach ($itemsList as $itemName => $invoices): 
                                    $itemId = "item_" . md5($catName . '_' . $itemName);
                                    
                                    $itemTotalQty = 0;
                                    $itemTotalRemaining = 0;
                                    foreach ($invoices as $invKey => $invData) {
                                        foreach ($invData['items'] as $s) {
                                            if ($s['stock_type'] === 'serial') {
                                                $itemTotalQty += 1;
                                                if ($s['status'] !== 'dispatched' && $s['status'] !== 'disposed') {
                                                    $itemTotalRemaining += 1;
                                                }
                                            } else {
                                                $t = (int)$s['total_quantity'];
                                                $itemTotalQty += $t;
                                                $rem = $t - (int)$s['dispatched_qty'];
                                                $itemTotalRemaining += max(0, $rem);
                                            }
                                        }
                                    }
                                ?>
                                    <div class="accordion-item item-accordion-card shadow-sm">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button item-accordion-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $itemId ?>">
                                                <div class="d-flex justify-content-between align-items-center w-100 me-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-box-seam text-secondary fs-6"></i>
                                                        <span class="fw-bold text-dark" style="font-size: 0.92rem;"><?= htmlspecialchars($itemName) ?></span>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-light text-dark border rounded-pill px-2.5 py-1" style="font-size: 0.78rem;">
                                                            <?= $itemTotalQty ?> Total
                                                        </span>
                                                        <span class="badge <?= $itemTotalRemaining > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-2.5 py-1" style="font-size: 0.78rem;">
                                                            <?= $itemTotalRemaining ?> Available
                                                        </span>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>

                                        <div id="<?= $itemId ?>" class="accordion-collapse collapse">
                                            <div class="accordion-body p-3 bg-white">
                                                
                                                <?php foreach ($invoices as $invKey => $invData): 
                                                    $meta = $invData['meta'];
                                                    $items = $invData['items'];
                                                ?>
                                                    <!-- INVOICE SUB-GROUP CARD -->
                                                    <div class="invoice-block shadow-sm invoice-card-item">
                                                        <div class="invoice-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                            <div class="d-flex flex-wrap align-items-center gap-3">
                                                                <span><i class="bi bi-file-earmark-text text-secondary me-1"></i><strong>Bill:</strong> <?= htmlspecialchars($meta['bill_no']) ?></span>
                                                                <span><i class="bi bi-calendar3 text-secondary me-1"></i><strong>Date:</strong> <?= htmlspecialchars($meta['bill_date']) ?></span>
                                                                <span><i class="bi bi-building text-secondary me-1"></i><strong>Vendor:</strong> <?= htmlspecialchars($meta['vendor_name']) ?></span>
                                                                <?php if (!empty($meta['po_number'])): ?>
                                                                    <span><i class="bi bi-receipt text-secondary me-1"></i><strong>PO:</strong> <?= htmlspecialchars($meta['po_number']) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <span class="badge bg-white text-dark border" style="font-size: 0.75rem;">
                                                                <?= count($items) ?> Batch Record(s)
                                                            </span>
                                                        </div>

                                                        <!-- SUB-TABLE -->
                                                        <div class="table-responsive">
                                                            <table class="table erp-subtable align-middle">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 50px;">#</th>
                                                                        <th>Serial Number / Tracking</th>
                                                                        <th class="text-center">Total Qty</th>
                                                                        <th class="text-center">Dispatched</th>
                                                                        <th class="text-center">Remaining</th>
                                                                        <th class="text-end">Unit Cost</th>
                                                                        <th class="text-center">Status</th>
                                                                        <th class="text-end pe-3">Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php 
                                                                        $subSl = 1;
                                                                        foreach ($items as $row): 
                                                                            $stockId = (int)$row['id'];
                                                                            
                                                                            // 2. ENCRYPT IDs FOR LINKS
                                                                            $enc_stock_id = encrypt_id($stockId);
                                                                            $enc_dispatch_id = !empty($row['last_dispatch_id']) ? encrypt_id($row['last_dispatch_id']) : '';

                                                                            $calcRemaining = (int)$row['total_quantity'] - (int)$row['dispatched_qty'];
                                                                        
                                                                        if ($row['stock_type'] === 'serial') {
                                                                            if ($row['status'] === 'dispatched') {
                                                                                $remainingQty = 0;
                                                                                $dynamicStatus = "dispatched";
                                                                            } elseif ($row['status'] === 'disposed') {
                                                                                $remainingQty = 0;
                                                                                $dynamicStatus = "disposed";
                                                                            } elseif ($row['status'] === 'maintenance') {
                                                                                $remainingQty = 0;
                                                                                $dynamicStatus = "maintenance";
                                                                            } else {
                                                                                $remainingQty = 1;
                                                                                $dynamicStatus = "available";
                                                                            }
                                                                        } else {
                                                                            if ($row['status'] === 'disposed') {
                                                                                $remainingQty = 0;
                                                                                $dynamicStatus = "disposed";
                                                                            } else {
                                                                                $remainingQty = max(0, $calcRemaining);
                                                                                $dispatchedQty = (int)$row['dispatched_qty'];

                                                                                if ($remainingQty == 0) {
                                                                                    $dynamicStatus = "dispatched";
                                                                                } elseif ($dispatchedQty > 0) {
                                                                                    $dynamicStatus = "partial";
                                                                                } else {
                                                                                    $dynamicStatus = "available";
                                                                                }
                                                                            }
                                                                        }
                                                                    ?>
                                                                        <tr id="row-<?= $stockId ?>" class="unit-data-row" data-status="<?= $dynamicStatus ?>">
                                                                            <td class="text-muted"><?= $subSl++ ?></td>
                                                                            <td class="fw-semibold text-dark">
                                                                                <?php if ($row['stock_type'] === 'serial'): ?>
                                                                                    <span class="text-uppercase font-monospace" style="font-size: 0.9rem;"><?= htmlspecialchars($row['serial_number']) ?></span>
                                                                                <?php else: ?>
                                                                                    <span class="text-muted fst-italic" style="font-size: 0.85rem;">Non-Serialized (Bulk)</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="text-center fw-semibold"><?= inr($row['total_quantity']) ?></td>
                                                                            <td class="text-center text-danger fw-semibold"><?= inr($row['dispatched_qty']) ?></td>
                                                                            <td class="text-center text-success fw-semibold"><?= inr($remainingQty) ?></td>
                                                                            <td class="text-end fw-semibold">₹<?= number_format((float)$row['amount'], 2) ?></td>
                                                                            <td class="text-center">
                                                                                <?php if ($dynamicStatus === 'dispatched'): ?>
                                                                                    <!-- Encrypted Dispatch & Stock Parameters -->
                                                                                    <a href="dispatch_report.php?stock_id=<?= urlencode($enc_stock_id) ?>&dispatch_id=<?= urlencode($enc_dispatch_id) ?>" class="badge badge-erp bg-danger text-decoration-none">
                                                                                        <i class="bi bi-truck me-1"></i> Dispatched
                                                                                    </a>
                                                                                <?php elseif ($dynamicStatus === 'partial'): ?>
                                                                                    <a href="dispatch_report.php?stock_id=<?= urlencode($enc_stock_id) ?>" class="badge badge-erp bg-warning text-dark text-decoration-none">
                                                                                        Partially Dispatched (<?= $row['dispatched_qty'] ?>)
                                                                                    </a>
                                                                                <?php elseif ($dynamicStatus === 'disposed'): ?>
                                                                                    <span class="badge badge-erp bg-dark"><i class="bi bi-trash3 me-1"></i> Scrapped</span>
                                                                                <?php elseif ($dynamicStatus === 'maintenance'): ?>
                                                                                    <span class="badge badge-erp bg-warning text-dark"><i class="bi bi-tools me-1"></i> Repair</span>
                                                                                <?php else: ?>
                                                                                    <span class="badge badge-erp bg-success"><i class="bi bi-check-circle me-1"></i> Available</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="text-end pe-3">
                                                                                <div class="d-inline-flex gap-1">
                                                                                    <!-- Encrypted Edit Link -->
                                                                                    <a href="edit_stock.php?id=<?= urlencode($enc_stock_id) ?>" class="action-btn-erp" title="Edit Record">
                                                                                        <i class="bi bi-pencil-square"></i>
                                                                                    </a>
                                                                                    <?php if ($dynamicStatus === 'dispatched'): ?>
                                                                                        <a href="#" class="action-btn-erp text-success" title="Move to E-Waste">
                                                                                            <i class="bi bi-recycle"></i>
                                                                                        </a>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>

                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card border p-4 text-center text-muted rounded-2 bg-white">
                <i class="bi bi-inbox fs-2 d-block mb-1 opacity-50"></i>
                <p class="mb-0 fw-medium" style="font-size: 0.9rem;">No stock records found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let isAllExpanded = false;

    function applyFilters() {
        const filterVal = $('#statusFilter').val();
        const query = $('#searchInput').val().trim().toLowerCase();
        const isFiltering = (filterVal !== 'all' || query !== "");

        $('#clearSearchBtn').toggleClass('d-none', query === '');

        // Step 1: Filter individual rows
        $('.unit-data-row').each(function() {
            const $row = $(this);
            const rowStatus = $row.attr('data-status');
            
            const rowText = $row.text().toLowerCase();
            const $invoiceHeader = $row.closest('.invoice-card-item').find('.invoice-header');
            const invoiceText = $invoiceHeader.length ? $invoiceHeader.text().toLowerCase() : '';

            const matchesStatus = (filterVal === 'all' || rowStatus === filterVal);
            const matchesSearch = (query === "" || rowText.includes(query) || invoiceText.includes(query));

            if (matchesStatus && matchesSearch) {
                $row.removeClass('d-none').show();
            } else {
                $row.addClass('d-none').hide();
            }
        });

        // Step 2: Filter Invoice Blocks based on visible rows
        $('.invoice-card-item').each(function() {
            const visibleRows = $(this).find('.unit-data-row:not(.d-none)').length;
            if (visibleRows > 0) {
                $(this).removeClass('d-none').show();
            } else {
                $(this).addClass('d-none').hide();
            }
        });

        // Step 3: Filter Item Accordion Cards & Control Collapse States
        $('.item-accordion-card').each(function() {
            const $itemCard = $(this);
            const visibleInvoices = $itemCard.find('.invoice-card-item:not(.d-none)').length;
            const $collapseElem = $itemCard.find('.accordion-collapse');
            const $button = $itemCard.find('> .accordion-header > .accordion-button');
            
            if (visibleInvoices > 0) {
                $itemCard.removeClass('d-none').show();
                if (isFiltering || isAllExpanded) {
                    $collapseElem.addClass('show').css('display', '');
                    $button.removeClass('collapsed');
                }
            } else {
                $itemCard.addClass('d-none').hide();
            }

            if (!isFiltering && !isAllExpanded) {
                $collapseElem.removeClass('show').css('display', '');
                $button.addClass('collapsed');
            }
        });

        // Step 4: Filter Category Accordion Cards & Control Collapse States
        $('.cat-accordion-card').each(function() {
            const $catCard = $(this);
            const visibleItems = $catCard.find('.item-accordion-card:not(.d-none)').length;
            const $collapseElem = $catCard.find('> .accordion-collapse');
            const $button = $catCard.find('> .accordion-header > .accordion-button');

            if (visibleItems > 0) {
                $catCard.removeClass('d-none').show();
                if (isFiltering || isAllExpanded) {
                    $collapseElem.addClass('show').css('display', '');
                    $button.removeClass('collapsed');
                }
            } else {
                $catCard.addClass('d-none').hide();
            }

            if (!isFiltering && !isAllExpanded) {
                $collapseElem.removeClass('show').css('display', '');
                $button.addClass('collapsed');
            }
        });
    }

    // Expand / Collapse All Toggle Button
    $('#toggleExpandAll').on('click', function() {
        isAllExpanded = !isAllExpanded;
        
        if (isAllExpanded) {
            $('#expandBtnText').text('Collapse All');
            $(this).find('i').removeClass('bi-arrows-expand').addClass('bi-arrows-collapse');
            $('.accordion-collapse').addClass('show').css('display', '');
            $('.accordion-button').removeClass('collapsed');
        } else {
            $('#expandBtnText').text('Expand All');
            $(this).find('i').removeClass('bi-arrows-collapse').addClass('bi-arrows-expand');
            $('.accordion-collapse').removeClass('show').css('display', '');
            $('.accordion-button').addClass('collapsed');
        }
    });

    // Event Listeners
    $('#statusFilter').on('change', applyFilters);
    $('#searchInput').on('input', applyFilters);

    $('#clearSearchBtn').on('click', function() {
        $('#searchInput').val('');
        applyFilters();
    });

    $('#resetSearch').on('click', function() {
        $('#searchInput').val('');
        $('#statusFilter').val('all');
        isAllExpanded = false;
        $('#expandBtnText').text('Expand All');
        $('#toggleExpandAll').find('i').removeClass('bi-arrows-collapse').addClass('bi-arrows-expand');
        applyFilters();
    });

    // Deep-Link Highlight Handler
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight_id');

    if (highlightId) {
        setTimeout(() => {
            const $targetRow = $('#row-' + highlightId);
            if ($targetRow.length) {
                $targetRow.parents('.accordion-collapse').addClass('show').css('display', '');
                $targetRow.parents('.accordion-item').find('> .accordion-header > .accordion-button').removeClass('collapsed');
                $targetRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                $targetRow.css({'background-color': '#fff3cd', 'outline': '2px solid #ffc107'});
                
                setTimeout(() => {
                    $targetRow.css({'transition': 'all 2s ease', 'background-color': '', 'outline': 'none'});
                }, 3000);
            }
        }, 300);
    }
});
</script>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>