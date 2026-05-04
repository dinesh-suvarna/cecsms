<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";

$page_title = "Categorized Vendor Directory";

function getCategoryData($conn, $category) {
    $v_stmt = $conn->prepare("SELECT * FROM vendors WHERE category = ? ORDER BY vendor_name ASC");
    $v_stmt->bind_param("s", $category);
    $v_stmt->execute();
    $vendors = $v_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $results = [];

    foreach ($vendors as $vendor) {
        $vendor_id = $vendor['id'];
        
        if ($category === 'Computer') {
            $query = "SELECT bill_date, bill_no, item_name, 'Computer' as cat, quantity as qty, amount as price 
                      FROM stock_details sd JOIN items_master im ON sd.stock_item_id = im.id WHERE sd.vendor_id = ?";
        } elseif ($category === 'Furniture') {
            $query = "SELECT bill_date, bill_no, item_name, 'Furniture' as cat, total_qty as qty, unit_price as price 
                      FROM furniture_stock fs JOIN furniture_items fi ON fs.furniture_item_id = fi.id WHERE fs.vendor_id = ?";
        } else { // Electrical
            $query = "SELECT bill_date, bill_no, item_name, 'Electrical' as cat, total_qty as qty, unit_price as price 
                      FROM electrical_stock es JOIN electrical_items ei ON es.electrical_item_id = ei.id WHERE es.vendor_id = ?";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    'Computer' => getCategoryData($conn, 'Computer'),
    'Furniture' => getCategoryData($conn, 'Furniture'),
    'Electrical' => getCategoryData($conn, 'Electricals')
];

ob_start();
?>

<style>
    .fw-800 { font-weight: 800 !important; letter-spacing: -0.5px; }
    .text-xxs { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05rem; }
    
    /* Tabs Styling */
    .nav-pills .nav-link { color: #64748b; font-weight: 600; border-radius: 12px; padding: 12px 24px; border: 1px solid transparent; transition: all 0.2s; }
    .nav-pills .nav-link.active { background: #0f172a; color: white; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15); }
    .nav-pills .nav-link:hover:not(.active) { background: #f1f5f9; border-color: #e2e8f0; }

    /* Accordion Styling */
    .accordion-item { border: 1px solid #f1f5f9 !important; border-radius: 16px !important; margin-bottom: 1rem; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .accordion-button { background: white !important; padding: 1.5rem; }
    .accordion-button:not(.collapsed) { border-bottom: 1px solid #f1f5f9; box-shadow: none; }
    .accordion-button::after { background-size: 1rem; }
    
    /* Table Styling */
    .table thead th { background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .bg-computer { background: #e0f2fe; color: #0369a1; }
    .bg-furniture { background: #fef3c7; color: #92400e; }
    .bg-electrical { background: #dcfce7; color: #166534; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-800 text-dark mb-0">Vendor Directory</h4>
            <p class="text-muted small mb-0">Comprehensive vendor data and service history organized for quick access.</p>
        </div>
        <a href="view_vendors.php" class="btn btn-light rounded-pill px-4 fw-bold border shadow-sm">
            <i class="bi bi-arrow-left me-2"></i>Back to Registry
        </a>
    </div>

    <!-- Category Tabs -->
    <ul class="nav nav-pills mb-4 gap-2" id="sectorTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-computer">Computer</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-furniture">Furniture</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-electrical">Electrical</button>
        </li>
    </ul>

    <div class="tab-content">
        <?php $firstTab = true; foreach ($categories as $catName => $vendorList): ?>
            <div class="tab-pane fade <?= $firstTab ? 'show active' : '' ?>" id="tab-<?= strtolower($catName) ?>">
                
                <div class="accordion" id="acc-<?= strtolower($catName) ?>">
                    <?php if (empty($vendorList)): ?>
                        <div class="text-center py-5 bg-white rounded-4 border">
                            <p class="text-muted mb-0">No vendors found in this category.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($vendorList as $index => $data): 
                            $vendor = $data['info'];
                            $history = $data['history'];
                            $stats = $data['stats'];
                            $accId = "collapse-" . strtolower($catName) . "-" . $vendor['id'];
                        ?>
                            <div class="accordion-item shadow-sm">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accId ?>">
                                        <div class="row align-items-center w-100 me-3">
                                            <div class="col">
                                                <div class="fw-800 text-dark"><?= htmlspecialchars($vendor['vendor_name']) ?></div>
                                                <div class="text-muted text-xxs text-uppercase">
                                                    <i class="bi bi-telephone me-1"></i> <?= $vendor['phone_number'] ?: 'No Phone' ?>
                                                </div>
                                            </div>
                                            <div class="col-auto text-end">
                                                <span class="d-block text-xxs text-muted text-uppercase">Total Spend</span>
                                                <span class="fw-800 text-primary">₹<?= number_format($stats['spend'], 0) ?></span>
                                            </div>
                                            <div class="col-auto text-end border-start ps-3 ms-3">
                                                <span class="d-block text-xxs text-muted text-uppercase">Supplies</span>
                                                <span class="fw-800 text-dark"><?= $stats['count'] ?></span>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="<?= $accId ?>" class="accordion-collapse collapse" data-bs-parent="#acc-<?= strtolower($catName) ?>">
                                    <div class="accordion-body p-0">
                                        <div class="p-3 bg-light-subtle border-bottom">
                                            <div class="row small text-muted">
                                                <div class="col-md-4"><i class="bi bi-envelope me-2"></i><?= $vendor['email'] ?: 'No Email' ?></div>
                                                <div class="col-md-8"><i class="bi bi-geo-alt me-2"></i><?= $vendor['address'] ?: 'No Address' ?></div>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table align-middle mb-0">
                                                <thead>
                                                    <tr class="text-muted text-xxs text-uppercase">
                                                        <th class="ps-4 py-3">Date</th>
                                                        <th class="py-3">Bill #</th>
                                                        <th class="py-3">Item Name</th>
                                                        <th class="py-3 text-center">Qty</th>
                                                        <th class="text-end pe-4 py-3">Unit Price</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($history)): ?>
                                                        <tr><td colspan="5" class="text-center py-4 text-muted">No historical data available.</td></tr>
                                                    <?php else: ?>
                                                        <?php foreach ($history as $row): ?>
                                                            <tr>
                                                                <td class="ps-4 small text-muted"><?= date('M d, Y', strtotime($row['bill_date'])) ?></td>
                                                                <td><span class="fw-bold">#<?= htmlspecialchars($row['bill_no']) ?></span></td>
                                                                <td class="fw-600 text-dark"><?= htmlspecialchars($row['item_name']) ?></td>
                                                                <td class="text-center"><?= $row['qty'] ?></td>
                                                                <td class="text-end pe-4 fw-800 text-dark">₹<?= number_format($row['price'], 2) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
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

<?php 
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>