<?php
require_once __DIR__ . "/../config/db.php";
$page_title = "View Stock Details";
$page_icon  = "bi-clipboard-data";

/* ================== FETCH DATA WITH DISPATCH MATH ================== */
// Uses im.category directly from items_master instead of joining a non-existent categories table
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

/* ================== 3-TIER GROUPING ================== */
// Hierarchy: Category Name -> Item Name -> Invoice Group (Bill No + Vendor) -> Rows
$grouped = [];
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
}

ob_start();
?>

<style>
:root {
    --saas-border: #e2e8f0;
    --saas-bg: #f8fafc;
    --saas-text-muted: #64748b;
}

.saas-card {
    background: #ffffff;
    border: 1px solid var(--saas-border);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.saas-toolbar {
    background: #ffffff;
    border: 1px solid var(--saas-border);
    border-radius: 10px;
    padding: 8px 14px;
}

/* Category Outer Cards */
.cat-accordion-card {
    border: 1px solid var(--saas-border) !important;
    border-radius: 12px !important;
    margin-bottom: 0.75rem;
    overflow: hidden;
    background: #ffffff;
}

.cat-accordion-btn {
    background: #ffffff !important;
    padding: 1rem 1.25rem;
    border: none;
    box-shadow: none !important;
}

.cat-accordion-btn:not(.collapsed) {
    background: #ffffff !important;
    border-bottom: 1px solid var(--saas-border);
}

.folder-icon-box {
    width: 36px;
    height: 36px;
    background-color: #e0e7ff;
    color: #4f46e5;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Item Nested Cards */
.item-accordion-card {
    border: 1px solid var(--saas-border) !important;
    border-radius: 8px !important;
    margin-bottom: 0.5rem;
    background: #ffffff;
}

.item-accordion-btn {
    padding: 0.75rem 1rem;
    background: #ffffff !important;
}

/* Sub-Group Invoice Block */
.invoice-block {
    border: 1px solid #edf2f7;
    border-radius: 8px;
    background: #ffffff;
    margin-bottom: 0.75rem;
}

.invoice-header {
    background-color: #f1f5f9;
    padding: 0.5rem 0.85rem;
    font-size: 0.78rem;
    border-bottom: 1px solid #e2e8f0;
}

.saas-subtable {
    margin-bottom: 0;
    font-size: 0.82rem;
}

.saas-subtable thead th {
    background-color: #ffffff;
    color: var(--saas-text-muted);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--saas-border);
    padding: 0.45rem 0.85rem;
}

.saas-subtable td {
    padding: 0.55rem 0.85rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

.action-btn-saas {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    border: 1px solid transparent;
    background: transparent;
    transition: all 0.15s ease;
}

.action-btn-saas:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
}
</style>

<!-- PAGE HEADER -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h4 class="fw-bold m-0 text-dark">
            <i class="<?= $page_icon ?> text-primary me-2"></i><?= $page_title ?>
        </h4>
        <p class="text-muted small m-0">Inventory registry grouped by category, item catalog, and procurement invoice.</p>
    </div>
</div>

<!-- SEARCH & FILTER TOOLBAR -->
<div class="saas-toolbar mb-3">
    <div class="row g-2 align-items-center">
        <div class="col-md-4 col-sm-6">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="searchInput" class="form-control border-0 bg-transparent shadow-none" placeholder="Search serial, bill, PO, or vendor...">
            </div>
        </div>
       <div class="col-md-3 col-sm-6 ms-auto">
            <select id="statusFilter" class="form-select form-select-sm shadow-none">
                <option value="all">All Statuses</option>
                <option value="available">Available</option>
                <option value="partial">Partially Dispatched</option>
                <option value="dispatched">Dispatched</option>
                <option value="maintenance">Under Repair / Maintenance</option>
                <option value="disposed">Scrapped / Disposed</option>
            </select>
        </div>
        <div class="col-auto">
            <button id="resetSearch" class="btn btn-sm btn-light border text-secondary px-2.5" title="Clear Filters">
                <i class="bi bi-x-lg"></i>
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
                                    <i class="bi bi-folder-fill fs-5"></i>
                                </div>
                                <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($catName) ?></span>
                            </div>
                            <div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-semibold">
                                    <?= $catTotalQty ?> Items
                                </span>
                            </div>
                        </div>
                    </button>
                </h2>

                <div id="<?= $catId ?>" class="accordion-collapse collapse">
                    <div class="accordion-body p-3 bg-light">
                        
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
                                <div class="accordion-item item-accordion-card">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button item-accordion-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $itemId ?>">
                                            <div class="d-flex justify-content-between align-items-center w-100 me-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-box-seam text-secondary"></i>
                                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($itemName) ?></span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-light text-dark border rounded-pill px-2">
                                                        <?= $itemTotalQty ?> Total
                                                    </span>
                                                    <span class="badge <?= $itemTotalRemaining > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-2">
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
                                                        <span class="badge bg-white text-dark border">
                                                            <?= count($items) ?> Batch Record(s)
                                                        </span>
                                                    </div>

                                                    <!-- SUB-TABLE -->
                                                    <div class="table-responsive">
                                                        <table class="table saas-subtable align-middle">
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
                                                                        <td class="text-muted small"><?= $subSl++ ?></td>
                                                                        <td class="fw-semibold text-dark">
                                                                            <?php if ($row['stock_type'] === 'serial'): ?>
                                                                                <span class="text-uppercase"><?= htmlspecialchars($row['serial_number']) ?></span>
                                                                            <?php else: ?>
                                                                                <span class="text-muted italic">Non-Serialized (Bulk)</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td class="text-center fw-semibold"><?= number_format($row['total_quantity']) ?></td>
                                                                        <td class="text-center text-danger fw-semibold"><?= number_format($row['dispatched_qty']) ?></td>
                                                                        <td class="text-center text-success fw-semibold"><?= number_format($remainingQty) ?></td>
                                                                        <td class="text-end">₹<?= number_format((float)$row['amount'], 2) ?></td>
                                                                        <td class="text-center">
                                                                            <?php if ($dynamicStatus === 'dispatched'): ?>
                                                                                <a href="dispatch_report.php?stock_id=<?= $stockId ?>&dispatch_id=<?= $row['last_dispatch_id'] ?>" class="badge bg-danger text-decoration-none">
                                                                                    <i class="bi bi-truck me-1"></i> Dispatched
                                                                                </a>
                                                                            <?php elseif ($dynamicStatus === 'partial'): ?>
                                                                                <a href="dispatch_report.php?stock_id=<?= $stockId ?>" class="badge bg-warning text-dark text-decoration-none">
                                                                                    Partially Dispatched (<?= $row['dispatched_qty'] ?>)
                                                                                </a>
                                                                            <?php elseif ($dynamicStatus === 'disposed'): ?>
                                                                                <span class="badge bg-dark"><i class="bi bi-trash3 me-1"></i> Scrapped</span>
                                                                            <?php elseif ($dynamicStatus === 'maintenance'): ?>
                                                                                <span class="badge bg-warning text-dark"><i class="bi bi-tools me-1"></i> Repair</span>
                                                                            <?php else: ?>
                                                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Available</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td class="text-end pe-3">
                                                                            <div class="d-inline-flex gap-1">
                                                                                <a href="edit_stock.php?id=<?= $stockId ?>" class="action-btn-saas" title="Edit Record">
                                                                                    <i class="bi bi-pencil-square"></i>
                                                                                </a>
                                                                                <?php if ($dynamicStatus === 'dispatched'): ?>
                                                                                    <a href="#" class="action-btn-saas text-success" title="Move to E-Waste">
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
        <div class="saas-card p-4 text-center text-muted">
            <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
            <p class="mb-0 small fw-medium">No stock records found.</p>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {

    function applyFilters() {
        const filterVal = $('#statusFilter').val();
        const query = $('#searchInput').val().trim().toLowerCase();
        const isFiltering = (filterVal !== 'all' || query !== "");

        // Step 1: Filter individual rows
        $('.unit-data-row').each(function() {
            const $row = $(this);
            const rowStatus = $row.attr('data-status');
            
            // Extract printable text from the row and its immediate invoice header
            const rowText = $row.text().toLowerCase();
            const $invoiceHeader = $row.closest('.invoice-card-item').find('.invoice-header');
            const invoiceText = $invoiceHeader.length ? $invoiceHeader.text().toLowerCase() : '';

            // Check conditions
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
            
            if (visibleInvoices > 0) {
                $itemCard.removeClass('d-none').show();
                
                // Expand if user is actively filtering, otherwise let user control collapse state
                if (isFiltering) {
                    $collapseElem.addClass('show').css('display', '');
                }
            } else {
                $itemCard.addClass('d-none').hide();
            }

            if (!isFiltering) {
                $collapseElem.removeClass('show').css('display', '');
            }
        });

        // Step 4: Filter Category Accordion Cards & Control Collapse States
        $('.cat-accordion-card').each(function() {
            const $catCard = $(this);
            const visibleItems = $catCard.find('.item-accordion-card:not(.d-none)').length;
            const $collapseElem = $catCard.find('> .accordion-collapse');

            if (visibleItems > 0) {
                $catCard.removeClass('d-none').show();

                if (isFiltering) {
                    $collapseElem.addClass('show').css('display', '');
                }
            } else {
                $catCard.addClass('d-none').hide();
            }

            if (!isFiltering) {
                $collapseElem.removeClass('show').css('display', '');
            }
        });
    }

    // Event Listeners
    $('#statusFilter').on('change', applyFilters);
    $('#searchInput').on('input', applyFilters);

    // Reset Button Handler
    $('#resetSearch').on('click', function() {
        $('#searchInput').val('');
        $('#statusFilter').val('all');
        applyFilters();
    });

    // Deep-Link Highlight Handler
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight_id');

    if (highlightId) {
        setTimeout(() => {
            const $targetRow = $('#row-' + highlightId);
            if ($targetRow.length) {
                // Expand all parent accordions up the DOM tree
                $targetRow.parents('.accordion-collapse').addClass('show').css('display', '');
                
                // Smooth scroll to target row
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