<?php
session_start();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

$page_title = "Global Purchase Audit Ledger";

// Session parameters for role & division scoping
$role = $_SESSION['role'] ?? '';
$division_id = (int)($_SESSION['division_id'] ?? 0);
$is_super_admin = ($role === 'SuperAdmin' || $division_id === 0);

/**
 * Fetch Item-Grouped Transactions by Category Domain (Aggregated by Bill + Item)
 */
function getItemLedgerData(mysqli $conn, string $category, bool $is_super_admin, int $division_id): array {
    $stmt = null;

    if ($category === 'Computer') {
        if ($is_super_admin) {
            $query = "SELECT sd.bill_date as purchase_date, v.id as vendor_id, v.vendor_name, im.id as item_id, im.item_name, 'Computer' as category, sd.bill_no, 'COMP-' as prefix, 
                             SUM(sd.quantity) as qty, 
                             (SUM(sd.quantity * sd.amount) / SUM(sd.quantity)) as unit_price, 
                             SUM(sd.quantity * sd.amount) as total_amount 
                      FROM stock_details sd 
                      JOIN vendors v ON sd.vendor_id = v.id 
                      JOIN items_master im ON sd.stock_item_id = im.id 
                      GROUP BY sd.bill_no, im.id, sd.vendor_id
                      ORDER BY im.item_name ASC, MAX(sd.bill_date) DESC";
            $stmt = $conn->prepare($query);
        } else {
            $query = "SELECT sd.bill_date as purchase_date, v.id as vendor_id, v.vendor_name, im.id as item_id, im.item_name, 'Computer' as category, sd.bill_no, 'COMP-' as prefix, 
                             SUM(dd.quantity) as qty, 
                             (SUM(dd.quantity * sd.amount) / SUM(dd.quantity)) as unit_price, 
                             SUM(dd.quantity * sd.amount) as total_amount 
                      FROM stock_details sd 
                      JOIN vendors v ON sd.vendor_id = v.id 
                      JOIN items_master im ON sd.stock_item_id = im.id 
                      JOIN dispatch_details dd ON sd.id = dd.stock_detail_id 
                      JOIN dispatch_master dm ON dd.dispatch_id = dm.id 
                      WHERE dm.division_id = ? 
                      GROUP BY sd.bill_no, im.id, sd.vendor_id
                      ORDER BY im.item_name ASC, MAX(sd.bill_date) DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $division_id);
        }
    } elseif ($category === 'Furniture') {
        if ($is_super_admin) {
            $query = "SELECT MAX(fs.bill_date) as purchase_date, v.id as vendor_id, v.vendor_name, fi.id as item_id, fi.item_name, 'Furniture' as category, fs.bill_no, 'FURN-' as prefix, 
                             SUM(fs.total_qty) as qty, 
                             (SUM(fs.total_qty * fs.unit_price) / SUM(fs.total_qty)) as unit_price, 
                             SUM(fs.total_qty * fs.unit_price) as total_amount 
                      FROM furniture_stock fs 
                      JOIN vendors v ON fs.vendor_id = v.id 
                      JOIN furniture_items fi ON fs.furniture_item_id = fi.id 
                      GROUP BY fs.bill_no, fi.id, fs.vendor_id
                      ORDER BY fi.item_name ASC, MAX(fs.bill_date) DESC";
            $stmt = $conn->prepare($query);
        } else {
            $query = "SELECT MAX(fs.bill_date) as purchase_date, v.id as vendor_id, v.vendor_name, fi.id as item_id, fi.item_name, 'Furniture' as category, fs.bill_no, 'FURN-' as prefix, 
                             SUM(fs.total_qty) as qty, 
                             (SUM(fs.total_qty * fs.unit_price) / SUM(fs.total_qty)) as unit_price, 
                             SUM(fs.total_qty * fs.unit_price) as total_amount 
                      FROM furniture_stock fs 
                      JOIN vendors v ON fs.vendor_id = v.id 
                      JOIN furniture_items fi ON fs.furniture_item_id = fi.id 
                      JOIN units u ON fs.unit_id = u.id 
                      WHERE u.division_id = ? 
                      GROUP BY fs.bill_no, fi.id, fs.vendor_id
                      ORDER BY fi.item_name ASC, MAX(fs.bill_date) DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $division_id);
        }
    } elseif ($category === 'Electrical' || $category === 'Electricals') {
        if ($is_super_admin) {
            $query = "SELECT MAX(es.bill_date) as purchase_date, v.id as vendor_id, v.vendor_name, ei.id as item_id, ei.item_name, 'Electricals' as category, es.bill_no, 'ELEC-' as prefix, 
                             SUM(es.total_qty) as qty, 
                             (SUM(es.total_qty * es.unit_price) / SUM(es.total_qty)) as unit_price, 
                             SUM(es.total_qty * es.unit_price) as total_amount 
                      FROM electrical_stock es 
                      JOIN vendors v ON es.vendor_id = v.id 
                      JOIN electrical_items ei ON es.electrical_item_id = ei.id 
                      GROUP BY es.bill_no, ei.id, es.vendor_id
                      ORDER BY ei.item_name ASC, MAX(es.bill_date) DESC";
            $stmt = $conn->prepare($query);
        } else {
            $query = "SELECT MAX(es.bill_date) as purchase_date, v.id as vendor_id, v.vendor_name, ei.id as item_id, ei.item_name, 'Electricals' as category, es.bill_no, 'ELEC-' as prefix, 
                             SUM(es.total_qty) as qty, 
                             (SUM(es.total_qty * es.unit_price) / SUM(es.total_qty)) as unit_price, 
                             SUM(es.total_qty * es.unit_price) as total_amount 
                      FROM electrical_stock es 
                      JOIN vendors v ON es.vendor_id = v.id 
                      JOIN electrical_items ei ON es.electrical_item_id = ei.id 
                      JOIN units u ON es.unit_id = u.id 
                      WHERE u.division_id = ? 
                      GROUP BY es.bill_no, ei.id, es.vendor_id
                      ORDER BY ei.item_name ASC, MAX(es.bill_date) DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $division_id);
        }
    }

    if (!$stmt) {
        return [];
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Grouping by Item ID
    $grouped = [];
    foreach ($rows as $row) {
        $itemId = $row['item_id'];
        if (!isset($grouped[$itemId])) {
            $grouped[$itemId] = [
                'item_name'   => $row['item_name'],
                'total_qty'   => 0,
                'total_spend' => 0,
                'bills'       => []
            ];
        }
        $grouped[$itemId]['total_qty']   += $row['qty'];
        $grouped[$itemId]['total_spend'] += $row['total_amount'];
        $grouped[$itemId]['bills'][]      = $row;
    }
    return $grouped;
}

$categories = [
    'Computer' => getItemLedgerData($conn, 'Computer', $is_super_admin, $division_id),
    'Furniture' => getItemLedgerData($conn, 'Furniture', $is_super_admin, $division_id),
    'Electrical' => getItemLedgerData($conn, 'Electrical', $is_super_admin, $division_id)
];

ob_start();
?>

<style>
    :root {
        --erp-navy: #123b63;
        --erp-bg: #f3f5f7;
        --erp-border: #d9e0e7;
        --erp-text-main: #20384d;
        --erp-text-muted: #64748b;
        --erp-shadow-sm: 0 1px 3px rgba(20,45,70,.05);
    }

    body { background-color: var(--erp-bg); font-family: 'Inter', sans-serif; color: var(--erp-text-main); }

    /* Nav Tabs */
    .erp-tabs .nav-link { 
        color: var(--erp-text-muted); font-weight: 600; border-radius: 6px; 
        padding: 0.55rem 1.25rem; font-size: 0.85rem; background: #ffffff; border: 1px solid var(--erp-border); 
    }
    .erp-tabs .nav-link.active { background: var(--erp-navy); color: #ffffff; border-color: var(--erp-navy); }

    /* Accordion Item Card */
    .accordion-item { border: 1px solid var(--erp-border) !important; border-radius: 8px !important; margin-bottom: 0.85rem; background: #ffffff; overflow: hidden; }
    .accordion-button { background: #ffffff !important; padding: 1rem 1.25rem; }
    .accordion-button:not(.collapsed) { border-bottom: 1px solid var(--erp-border); background: #f8fafc !important; }

    .table-scroll-container { max-height: 400px; overflow-y: auto; }
    
    .custom-ledger-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .custom-ledger-table thead th { 
        position: sticky; top: 0; z-index: 5; background-color: #f8fafc; color: var(--erp-text-muted); 
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase; padding: 0.85rem 1rem; border-bottom: 1px solid var(--erp-border); 
    }
    .custom-ledger-table tbody td { padding: 0.85rem 1rem; border-bottom: 1px solid var(--erp-border); font-size: 0.85rem; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-800 text-dark mb-0">Global Purchase Audit Ledger</h4>
            <p class="text-muted small mb-0">Itemized bill aggregation grouped by inventory item.</p>
        </div>
        <a href="vendor_details.php" class="btn btn-white border fw-bold text-dark shadow-sm">
            <i class="bi bi-people me-2"></i>Vendor Directory
        </a>
    </div>

    <!-- Category Tabs Filter -->
    <ul class="nav nav-pills erp-tabs mb-4 gap-2" id="ledgerCategoryTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-computer"><i class="bi bi-pc-display me-1"></i> Computer</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-furniture"><i class="bi bi-boxes me-1"></i> Furniture</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-electrical"><i class="bi bi-plug-fill me-1"></i> Electrical</button>
        </li>
    </ul>

    <div class="tab-content">
        <?php $firstTab = true; foreach ($categories as $catName => $itemList): ?>
            <div class="tab-pane fade <?= $firstTab ? 'show active' : '' ?>" id="tab-<?= strtolower($catName) ?>">
                
                <div class="accordion" id="acc-<?= strtolower($catName) ?>">
                    <?php if (empty($itemList)): ?>
                        <div class="text-center py-5 bg-white rounded-3 border">
                            <i class="bi bi-inbox text-muted fs-2 opacity-50"></i>
                            <p class="text-muted extra-small mb-0 mt-2">No purchase records found in this category domain.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($itemList as $itemId => $itemData): 
                            $accId = "collapse-" . strtolower($catName) . "-" . $itemId;
                        ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accId ?>">
                                        <div class="row align-items-center w-100 me-3">
                                            <div class="col">
                                                <div class="fw-bold text-dark extra-small"><?= htmlspecialchars($itemData['item_name']) ?></div>
                                                <div class="text-muted extra-small mt-1" style="font-size: 0.72rem;">
                                                    <i class="bi bi-receipt me-1"></i> <?= count($itemData['bills']) ?> Consolidated Bill Entry/Entries
                                                </div>
                                            </div>
                                            <div class="col-auto text-end">
                                                <span class="d-block text-uppercase text-muted fw-bold" style="font-size: 0.65rem;">Total Outlay</span>
                                                <span class="fw-bold text-primary extra-small"><?= inr($itemData['total_spend'], true) ?></span>
                                            </div>
                                            <div class="col-auto text-end border-start ps-3 ms-3">
                                                <span class="d-block text-uppercase text-muted fw-bold" style="font-size: 0.65rem;">Total Units</span>
                                                <span class="fw-bold text-dark extra-small"><?= number_format($itemData['total_qty'], 0) ?></span>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="<?= $accId ?>" class="accordion-collapse collapse" data-bs-parent="#acc-<?= strtolower($catName) ?>">
                                    <div class="accordion-body p-0">
                                        <div class="table-scroll-container">
                                            <table class="custom-ledger-table align-middle">
                                                <thead>
                                                    <tr>
                                                        <th class="ps-4">Date</th>
                                                        <th>Bill Reference</th>
                                                        <th>Vendor</th>
                                                        <th class="text-center">Total Qty</th>
                                                        <th class="text-end">Unit Price</th>
                                                        <th class="text-end">Total Amount</th>
                                                        <th class="text-center pe-4">Audit</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($itemData['bills'] as $row): ?>
                                                        <tr>
                                                            <td class="ps-4">
                                                                <span class="fw-semibold text-dark extra-small"><?= date('d M, Y', strtotime($row['purchase_date'])) ?></span>
                                                            </td>
                                                            <td>
                                                                <div class="fw-bold text-dark extra-small">#<?= htmlspecialchars($row['bill_no']) ?></div>
                                                            </td>
                                                            <td class="fw-semibold text-dark extra-small"><?= htmlspecialchars($row['vendor_name']) ?></td>
                                                            <td class="text-center fw-bold text-dark extra-small"><?= number_format($row['qty'], 0) ?></td>
                                                            <td class="text-end fw-semibold text-dark extra-small"><?= inr($row['unit_price'], true) ?></td>
                                                            <td class="text-end fw-bold text-primary extra-small"><?= inr($row['total_amount'], true) ?></td>
                                                            <td class="text-center pe-4">
                                                                <!-- Deep Link directly to Vendor Accordion in Vendor Directory -->
                                                                <a href="vendor_details.php?cat=<?= strtolower($catName) ?>&vendor_id=<?= $row['vendor_id'] ?>" 
                                                                   class="btn btn-sm btn-outline-secondary" 
                                                                   title="View Vendor Profile">
                                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
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