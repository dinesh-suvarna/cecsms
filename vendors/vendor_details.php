<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

$page_title = "Categorized Vendor Directory";

// Fetch session parameters
$role = $_SESSION['role'] ?? '';
$division_id = (int)($_SESSION['division_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);

/**
 * Retrieves vendor data filtered by category and division role.
 */
function getCategoryData(mysqli $conn, string $category, string $role, int $division_id) {
    // 1. Fetch Vendors
    if ($role === 'SuperAdmin' || $division_id === 0) {
        $v_stmt = $conn->prepare("SELECT DISTINCT v.* FROM vendors v WHERE v.category = ? ORDER BY v.vendor_name ASC");
        $v_stmt->bind_param("s", $category);
    } else {
        if ($category === 'Computer') {
            $v_query = "SELECT DISTINCT v.* 
                        FROM vendors v
                        JOIN stock_details sd ON v.id = sd.vendor_id
                        JOIN dispatch_details dd ON sd.id = dd.stock_detail_id
                        JOIN dispatch_master dm ON dd.dispatch_id = dm.id
                        WHERE v.category = ? AND dm.division_id = ?
                        ORDER BY v.vendor_name ASC";
        } elseif ($category === 'Furniture') {
            $v_query = "SELECT DISTINCT v.* 
                        FROM vendors v
                        JOIN furniture_stock fs ON v.id = fs.vendor_id
                        JOIN units u ON fs.unit_id = u.id
                        WHERE v.category = ? AND u.division_id = ?
                        ORDER BY v.vendor_name ASC";
        } elseif ($category === 'Electricals') {
            $v_query = "SELECT DISTINCT v.* 
                        FROM vendors v
                        JOIN electrical_stock es ON v.id = es.vendor_id
                        JOIN units u ON es.unit_id = u.id
                        WHERE v.category = ? AND u.division_id = ?
                        ORDER BY v.vendor_name ASC";
        } else {
            return [];
        }
        $v_stmt = $conn->prepare($v_query);
        $v_stmt->bind_param("si", $category, $division_id);
    }

    $v_stmt->execute();
    $vendors = $v_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $v_stmt->close();

    $results = [];

    // 2. Fetch Transaction History per Vendor
    foreach ($vendors as $vendor) {
        $vendor_id = $vendor['id'];
        $stmt = null; 

        if ($category === 'Computer') {
            if ($role === 'SuperAdmin' || $division_id === 0) {
                $query = "SELECT 
                            MAX(sd.bill_date) as bill_date, 
                            sd.bill_no, 
                            im.item_name, 
                            'Computer' as cat, 
                            SUM(sd.quantity) as qty, 
                            (SUM(sd.quantity * sd.amount) / SUM(sd.quantity)) as price 
                          FROM stock_details sd 
                          JOIN items_master im ON sd.stock_item_id = im.id 
                          WHERE sd.vendor_id = ? 
                          GROUP BY sd.bill_no, im.id
                          ORDER BY MAX(sd.bill_date) DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $vendor_id);
            } else {
                $query = "SELECT 
                            MAX(sd.bill_date) as bill_date, 
                            sd.bill_no, 
                            im.item_name, 
                            'Computer' as cat, 
                            SUM(dd.quantity) as qty, 
                            (SUM(dd.quantity * sd.amount) / SUM(dd.quantity)) as price 
                          FROM stock_details sd 
                          JOIN items_master im ON sd.stock_item_id = im.id 
                          JOIN dispatch_details dd ON sd.id = dd.stock_detail_id
                          JOIN dispatch_master dm ON dd.dispatch_id = dm.id
                          WHERE sd.vendor_id = ? AND dm.division_id = ?
                          GROUP BY sd.bill_no, im.id
                          ORDER BY MAX(sd.bill_date) DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $vendor_id, $division_id);
            }
        } elseif ($category === 'Furniture') {
            if ($role === 'SuperAdmin' || $division_id === 0) {
                $query = "SELECT 
                            MAX(fs.bill_date) as bill_date, 
                            fs.bill_no, 
                            fi.item_name, 
                            'Furniture' as cat, 
                            SUM(fs.total_qty) as qty, 
                            (SUM(fs.total_qty * fs.unit_price) / SUM(fs.total_qty)) as price 
                          FROM furniture_stock fs 
                          JOIN furniture_items fi ON fs.furniture_item_id = fi.id 
                          WHERE fs.vendor_id = ? 
                          GROUP BY fs.bill_no, fi.id
                          ORDER BY MAX(fs.bill_date) DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $vendor_id);
            } else {
                $query = "SELECT 
                            MAX(fs.bill_date) as bill_date, 
                            fs.bill_no, 
                            fi.item_name, 
                            'Furniture' as cat, 
                            SUM(fs.total_qty) as qty, 
                            (SUM(fs.total_qty * fs.unit_price) / SUM(fs.total_qty)) as price 
                          FROM furniture_stock fs 
                          JOIN furniture_items fi ON fs.furniture_item_id = fi.id 
                          JOIN units u ON fs.unit_id = u.id
                          WHERE fs.vendor_id = ? AND u.division_id = ?
                          GROUP BY fs.bill_no, fi.id
                          ORDER BY MAX(fs.bill_date) DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $vendor_id, $division_id);
            }
        } elseif ($category === 'Electricals') {
            if ($role === 'SuperAdmin' || $division_id === 0) {
                $query = "SELECT 
                            MAX(es.bill_date) as bill_date, 
                            es.bill_no, 
                            ei.item_name, 
                            'Electricals' as cat, 
                            SUM(es.total_qty) as qty, 
                            (SUM(es.total_qty * es.unit_price) / SUM(es.total_qty)) as price 
                          FROM electrical_stock es 
                          JOIN electrical_items ei ON es.electrical_item_id = ei.id 
                          WHERE es.vendor_id = ? 
                          GROUP BY es.bill_no, ei.id
                          ORDER BY MAX(es.bill_date) DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $vendor_id);
            } else {
                $query = "SELECT 
                            MAX(es.bill_date) as bill_date, 
                            es.bill_no, 
                            ei.item_name, 
                            'Electricals' as cat, 
                            SUM(es.total_qty) as qty, 
                            (SUM(es.total_qty * es.unit_price) / SUM(es.total_qty)) as price 
                          FROM electrical_stock es 
                          JOIN electrical_items ei ON es.electrical_item_id = ei.id 
                          JOIN units u ON es.unit_id = u.id
                          WHERE es.vendor_id = ? AND u.division_id = ?
                          GROUP BY es.bill_no, ei.id
                          ORDER BY MAX(es.bill_date) DESC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $vendor_id, $division_id);
            }
        }

        if (!$stmt) {
            continue;
        }

        $stmt->execute();
        $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $total_spend = 0;
        foreach ($history as $item) {
            $total_spend += ($item['qty'] * $item['price']);
        }

        $results[] = [
            'info' => $vendor,
            'history' => $history,
            'stats' => [
                'count' => count($history),
                'spend' => $total_spend
            ]
        ];
    }

    return $results;
}

$categories = [
    'Computer' => getCategoryData($conn, 'Computer', $role, $division_id),
    'Furniture' => getCategoryData($conn, 'Furniture', $role, $division_id),
    'Electrical' => getCategoryData($conn, 'Electricals', $role, $division_id)
];

ob_start();
?>

<style>
    :root {
        --erp-navy: #123b63;
        --erp-navy-dark: #0b2942;
        --erp-bg: #f3f5f7;
        --erp-card-bg: #ffffff;
        --erp-border: #d9e0e7;
        --erp-text-main: #20384d;
        --erp-text-muted: #64748b;
        --erp-shadow-sm: 0 1px 3px rgba(20,45,70,.05);
    }

    body { 
        background-color: var(--erp-bg); 
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: var(--erp-text-main);
    }

    /* Search Box Styling */
    .global-search-wrapper {
        position: relative;
        max-width: 360px;
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
        background: var(--erp-navy); 
        color: #ffffff; 
        border-color: var(--erp-navy);
        box-shadow: var(--erp-shadow-sm); 
    }
    .erp-tabs .nav-link:hover:not(.active) { 
        background: #f8fafc; 
        color: var(--erp-text-main); 
    }

    /* Accordion Styling */
    .accordion-item { 
        border: 1px solid var(--erp-border) !important; 
        border-radius: 8px !important; 
        margin-bottom: 0.85rem; 
        overflow: hidden; 
        box-shadow: var(--erp-shadow-sm);
        background: #ffffff;
    }
    .accordion-button { 
        background: #ffffff !important; 
        padding: 1rem 1.25rem; 
    }
    .accordion-button:not(.collapsed) { 
        border-bottom: 1px solid var(--erp-border); 
        box-shadow: none; 
        background: #f8fafc !important; 
    }
    
    .vendor-info-bar {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #f8fafc;
        border-bottom: 1px solid var(--erp-border);
    }

    .table-scroll-container {
        max-height: 450px;
        overflow-y: auto;
    }

    .custom-vendor-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .custom-vendor-table thead th { 
        position: sticky;
        top: 0;
        z-index: 5;
        background-color: #f8fafc; 
        color: var(--erp-text-muted); 
        font-size: 0.68rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        letter-spacing: 0.05em; 
        padding: 0.85rem 1rem; 
        border-bottom: 1px solid var(--erp-border); 
    }

    .custom-vendor-table tbody tr { transition: background-color 0.15s ease-in-out; }
    .custom-vendor-table tbody tr:hover { background-color: #f1f5f9; }
    .custom-vendor-table tbody td { 
        padding: 0.85rem 1rem; 
        border-bottom: 1px solid var(--erp-border); 
        font-size: 0.85rem; 
    }
    .custom-vendor-table tfoot td {
        background-color: #f8fafc;
        border-top: 2px solid var(--erp-border);
        padding: 0.85rem 1rem;
    }

    .btn-erp-action {
        border-radius: 6px;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 0.35rem 0.85rem;
    }
</style>

<div class="container-fluid p-0">
    <!-- Header Block -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold tracking-tight mb-1" style="color: var(--erp-text-main); letter-spacing: -0.01em;">Categorized Vendor Directory</h4>
            <p class="text-muted extra-small mb-0">Comprehensive vendor profiles and transaction history organized by stock domain.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- Global Search Input -->
            <div class="global-search-wrapper">
                <i class="bi bi-search global-search-icon"></i>
                <input type="text" id="detailsGlobalSearch" class="global-search-input" placeholder="Search vendors, bills, items...">
            </div>
            <a href="view_vendors.php" class="btn btn-light btn-sm fw-semibold border rounded-2 px-3">
                <i class="bi bi-arrow-left me-1"></i> Registry View
            </a>
        </div>
    </div>

    <!-- Category Tabs -->
    <ul class="nav nav-pills erp-tabs mb-4 gap-2" id="sectorTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-computer"><i class="bi bi-pc-display me-1"></i> Computer</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-furniture"><i class="bi bi-box-seam me-1"></i> Furniture</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-electrical"><i class="bi bi-plug-fill me-1"></i> Electrical</button>
        </li>
    </ul>

    <div class="tab-content">
        <?php $firstTab = true; foreach ($categories as $catName => $vendorList): ?>
            <div class="tab-pane fade <?= $firstTab ? 'show active' : '' ?>" id="tab-<?= strtolower($catName) ?>">
                
                <div class="accordion" id="acc-<?= strtolower($catName) ?>">
                    <?php if (empty($vendorList)): ?>
                        <div class="text-center py-5 bg-white rounded-2 border">
                            <i class="bi bi-inbox text-muted fs-2 opacity-50"></i>
                            <p class="text-muted extra-small mb-0 mt-2">No active vendors found in this category domain.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($vendorList as $index => $data): 
                            $vendor = $data['info'];
                            $history = $data['history'];
                            $stats = $data['stats'];
                            $accId = "collapse-" . strtolower($catName) . "-" . $vendor['id'];
                            $targetTableId = "table-" . strtolower($catName) . "-" . $vendor['id'];
                        ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accId ?>">
                                        <div class="row align-items-center w-100 me-3">
                                            <div class="col">
                                                <div class="fw-bold text-dark extra-small"><?= htmlspecialchars($vendor['vendor_name']) ?></div>
                                                <div class="text-muted extra-small mt-1" style="font-size: 0.72rem;">
                                                    <i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($vendor['phone_number'] ?: 'No Phone') ?>
                                                </div>
                                            </div>
                                            <div class="col-auto text-end">
                                                <span class="d-block text-uppercase text-muted fw-bold" style="font-size: 0.65rem;">Total Spend</span>
                                                <span class="fw-bold text-primary extra-small"><?= inr($stats['spend'], true) ?></span>
                                            </div>
                                            <div class="col-auto text-end border-start ps-3 ms-3">
                                                <span class="d-block text-uppercase text-muted fw-bold" style="font-size: 0.65rem;">Orders</span>
                                                <span class="fw-bold text-dark extra-small"><?= $stats['count'] ?></span>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="<?= $accId ?>" class="accordion-collapse collapse" data-bs-parent="#acc-<?= strtolower($catName) ?>">
                                    <div class="accordion-body p-0">
                                        <div class="p-3 vendor-info-bar d-flex flex-wrap justify-content-between align-items-center gap-2">
                                            <div class="row extra-small text-muted g-2 flex-grow-1">
                                                <div class="col-md-4">
                                                    <i class="bi bi-envelope me-2 text-secondary"></i><?= htmlspecialchars($vendor['email'] ?: 'No Email Specified') ?>
                                                </div>
                                                <div class="col-md-8">
                                                    <i class="bi bi-geo-alt me-2 text-secondary"></i><?= htmlspecialchars($vendor['address'] ?: 'No Address Specified') ?>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button 
                                                    type="button" 
                                                    class="btn btn-outline-secondary btn-erp-action btn-print-vendor"
                                                    data-container-id="<?= $accId ?>"
                                                    data-vendor-name="<?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES) ?>"
                                                    data-phone="<?= htmlspecialchars($vendor['phone_number'] ?? 'N/A', ENT_QUOTES) ?>"
                                                    data-email="<?= htmlspecialchars($vendor['email'] ?? 'N/A', ENT_QUOTES) ?>"
                                                    data-address="<?= htmlspecialchars(preg_replace('/\s+/', ' ', $vendor['address'] ?? 'N/A'), ENT_QUOTES) ?>">
                                                    <i class="bi bi-printer me-1"></i> Print / PDF
                                                </button>
                                                <button onclick="exportTableToCSV('<?= $targetTableId ?>', '<?= preg_replace('/[^a-zA-Z0-9]/', '_', $vendor['vendor_name']) ?>_history.csv')" class="btn btn-outline-success btn-erp-action">
                                                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="table-scroll-container">
                                            <table id="<?= $targetTableId ?>" class="custom-vendor-table align-middle">
                                                <thead>
                                                    <tr>
                                                        <th class="ps-4">Transaction Date</th>
                                                        <th>Bill Reference</th>
                                                        <th>Item Description</th>
                                                        <th class="text-center">Qty</th>
                                                        <th class="text-end">Unit Price</th>
                                                        <th class="text-end pe-4">Total Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($history)): ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4 text-muted extra-small">
                                                                <i class="bi bi-folder-x me-1"></i>No transaction history recorded for this vendor.
                                                            </td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($history as $row): 
                                                            $line_total = $row['qty'] * $row['price'];
                                                            $date_formatted = date('M d, Y', strtotime($row['bill_date']));
                                                        ?>
                                                            <tr>
                                                                <td class="ps-4">
                                                                    <span class="fw-semibold text-dark extra-small"><?= $date_formatted ?></span>
                                                                </td>
                                                                <td>
                                                                    <span class="fw-semibold text-dark extra-small">#<?= htmlspecialchars($row['bill_no']) ?></span>
                                                                </td>
                                                                <td>
                                                                    <span class="fw-semibold text-dark extra-small"><?= htmlspecialchars($row['item_name']) ?></span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="text-dark extra-small"><?= $row['qty'] ?></span>
                                                                </td>
                                                                <td class="text-end fw-semibold text-dark extra-small">
                                                                    <?= inr($row['price'], true) ?>
                                                                </td>
                                                                <td class="text-end pe-4 fw-bold text-dark extra-small">
                                                                    <?= inr($line_total, true) ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                                <?php if (!empty($history)): ?>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="5" class="ps-4 fw-bold text-dark extra-small">Total Expenditure:</td>
                                                        <td class="text-end pe-4 fw-bold text-primary extra-small"><?= inr($stats['spend'], true) ?></td>
                                                    </tr>
                                                </tfoot>
                                                <?php endif; ?>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        <?php $firstTab = false; endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Print logic
    document.querySelectorAll('.btn-print-vendor').forEach(button => {
        button.addEventListener('click', function (e) {
            e.stopPropagation();
            const containerId = this.getAttribute('data-container-id');
            const vendorName = this.getAttribute('data-vendor-name');
            const phone = this.getAttribute('data-phone');
            const email = this.getAttribute('data-email');
            const address = this.getAttribute('data-address');

            const container = document.getElementById(containerId);
            const tableHTML = container.querySelector('table').outerHTML;

            const printWindow = window.open('', '', 'height=700,width=900');
            printWindow.document.write('<html><head><title>Vendor Statement - ' + vendorName + '</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; color: #333; }');
            printWindow.document.write('.header-box { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 15px; }');
            printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px 12px; font-size: 13px; }');
            printWindow.document.write('th { background-color: #f8f9fa; text-transform: uppercase; font-size: 11px; }');
            printWindow.document.write('.text-end { text-align: right; }');
            printWindow.document.write('.text-center { text-align: center; }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            
            printWindow.document.write('<div class="header-box">');
            printWindow.document.write('<h2>' + vendorName + '</h2>');
            printWindow.document.write('<p class="mb-1"><strong>Phone:</strong> ' + phone + ' | <strong>Email:</strong> ' + email + '</p>');
            printWindow.document.write('<p class="mb-0"><strong>Address:</strong> ' + address + '</p>');
            printWindow.document.write('</div>');
            
            printWindow.document.write('<h5>Supply & Transaction Details Statement</h5>');
            printWindow.document.write(tableHTML);
            
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        });
    });

    // Default tab tracking (defaults to Computer tab)
    let defaultTabBtn = document.querySelector('#sectorTabs button[data-bs-target="#tab-computer"]');

    // Update active tab manually when user clicks
    document.querySelectorAll('#sectorTabs button').forEach(button => {
        button.addEventListener('click', function() {
            if (!document.getElementById('detailsGlobalSearch').value.trim()) {
                defaultTabBtn = this;
            }
        });
    });

    // REAL-TIME GLOBAL CROSS-TAB SEARCH
    const searchInput = document.getElementById('detailsGlobalSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const tabPanes = document.querySelectorAll('.tab-pane');
            let firstTabWithMatches = null;

            tabPanes.forEach(pane => {
                const accordionItems = pane.querySelectorAll('.accordion-item');
                let matchCountInPane = 0;

                accordionItems.forEach(item => {
                    const text = item.innerText.toLowerCase();
                    if (query === '' || text.includes(query)) {
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
                // If active tab has no matches, switch to the first tab that has matches
                const activePane = document.querySelector('.tab-pane.show.active');
                const activeMatches = activePane ? activePane.querySelectorAll('.accordion-item:not([style*="display: none"])').length : 0;

                if (activeMatches === 0 && firstTabWithMatches) {
                    const targetBtn = document.querySelector(`#sectorTabs button[data-bs-target="#${firstTabWithMatches}"]`);
                    if (targetBtn) {
                        const tabTrigger = bootstrap.Tab.getOrCreateInstance(targetBtn);
                        tabTrigger.show();
                    }
                }
            } else {
                // Search cleared: Switch back to default tab (Computer)
                if (defaultTabBtn) {
                    const tabTrigger = bootstrap.Tab.getOrCreateInstance(defaultTabBtn);
                    tabTrigger.show();
                }
            }
        });
    }

    // URL Param handler
    const urlParams = new URLSearchParams(window.location.search);
    const cat = urlParams.get('cat');
    const vendorId = urlParams.get('vendor_id');

    if (cat && vendorId) {
        const tabTrigger = document.querySelector(`button[data-bs-target="#tab-${cat}"]`);
        if (tabTrigger) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
        }

        const targetAccordionId = `collapse-${cat}-${vendorId}`;
        const targetAccordion = document.getElementById(targetAccordionId);
        if (targetAccordion) {
            const collapse = new bootstrap.Collapse(targetAccordion, { show: true });
            setTimeout(() => {
                targetAccordion.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 300);
        }
    }
});

function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    let csv = [];
    const rows = table.querySelectorAll("tr");

    for (let i = 0; i < rows.length; i++) {
        let row = [];
        const cols = rows[i].querySelectorAll("td, th");
        
        for (let j = 0; j < cols.length; j++) {
            let col = cols[j];
            let text = col.innerText.replace(/₹/g, '').replace(/,/g, '').trim();
            let colspan = col.colSpan || 1;
            
            row.push('"' + text + '"');
            
            for (let k = 1; k < colspan; k++) {
                row.push('""');
            }
        }
        csv.push(row.join(","));
    }

    const csvFile = new Blob([csv.join("\n")], { type: "text/csv" });
    const downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<?php 
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>