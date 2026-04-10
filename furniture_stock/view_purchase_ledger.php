<?php
include "../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Query to get all ledger entries with their item counts
$query_str = "
    SELECT l.*, v.vendor_name, COUNT(i.id) as total_items
    FROM purchase_ledger l
    JOIN vendors v ON l.vendor_id = v.id
    LEFT JOIN purchase_items i ON l.id = i.ledger_id";

if (!empty($search)) {
    $query_str .= " WHERE l.master_sl_no LIKE '%$search%' 
                    OR v.vendor_name LIKE '%$search%' 
                    OR l.bill_no LIKE '%$search%'";
}

$query_str .= " GROUP BY l.id ORDER BY v.vendor_name ASC, l.purchase_date DESC";
$result = $conn->query($query_str);

// --- GROUP DATA BY VENDOR ---
$grouped_data = [];
while ($row = $result->fetch_assoc()) {
    $grouped_data[$row['vendor_name']][] = $row;
}

$page_title = "Purchase Management";
ob_start();
?>

<div class="container-fluid py-4 px-4">
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h4 class="fw-bold text-dark mb-1">Purchase Records</h4>
            <p class="text-muted small">Grouped by Vendor. Click a vendor to see their bills.</p>
        </div>
        <div class="col-md-6">
            <form action="" method="GET" class="d-flex justify-content-md-end">
                <div class="input-group shadow-sm border bg-white" style="max-width: 350px;">
                    <span class="input-group-text border-0 bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-0 ps-0 text-dark" placeholder="Search Vendor, Bill or SL..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary px-4" type="submit">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($grouped_data)): ?>
        <div class="card p-5 text-center text-muted">No records found.</div>
    <?php else: ?>
        <div class="accordion accordion-flush" id="vendorAccordion">
            <?php 
            $v_index = 0;
            foreach ($grouped_data as $vendor_name => $bills): 
                $v_index++;
                $vendor_id_attr = "vendor_" . preg_replace('/[^A-Za-z0-9\-]/', '', $v_index);
            ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white p-0 border-0">
                        <button class="btn w-100 text-start p-3 d-flex justify-content-between align-items-center vendor-toggle collapsed" 
                                type="button" data-bs-toggle="collapse" data-bs-target="#<?= $vendor_id_attr ?>">
                            <div>
                                <i class="bi bi-shop me-2 text-primary"></i>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($vendor_name) ?></span>
                                <span class="ms-2 badge bg-light text-dark border fw-normal small"><?= count($bills) ?> Bills</span>
                            </div>
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </button>
                    </div>

                    <div id="<?= $vendor_id_attr ?>" class="collapse <?= !empty($search) ? 'show' : '' ?>" data-bs-parent="#vendorAccordion">
                        <div class="card-body p-0 border-top">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr class="small text-uppercase">
                                            <th style="width: 50px;"></th>
                                            <th class="py-2 text-dark fw-bold">Ref SL.No</th>
                                            <th class="py-2 text-dark fw-bold">Date</th>
                                            <th class="py-2 text-dark fw-bold text-center">Bill No</th>
                                            <th class="py-2 text-dark fw-bold text-center">Items</th>
                                            <th class="py-2 text-dark fw-bold text-end pe-4">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bills as $row): 
                                            $l_id = $row['id'];
                                            $items_res = $conn->query("SELECT * FROM purchase_items WHERE ledger_id = $l_id");
                                            $total_taxable = 0; $bill_grand_total = 0;
                                        ?>
                                            <tr class="master-row border-bottom" data-bs-toggle="collapse" data-bs-target="#details_<?= $l_id ?>">
                                                <td class="ps-4"><i class="bi bi-chevron-right chevron-icon"></i></td>
                                                <td><span class="fw-bold text-primary"><?= htmlspecialchars($row['master_sl_no']) ?></span></td>
                                                <td class="text-dark small"><?= date('d-m-Y', strtotime($row['purchase_date'])) ?></td>
                                                <td class="text-center text-dark"><?= htmlspecialchars($row['bill_no']) ?></td>
                                                <td class="text-center text-dark"><?= $row['total_items'] ?></td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-sm btn-link text-primary text-decoration-none fw-bold">Items Details</button>
                                                </td>
                                            </tr>

                                            <tr class="collapse-row">
                                                <td colspan="6" class="p-0 border-0">
                                                    <div class="collapse" id="details_<?= $l_id ?>">
                                                        <div class="p-4 bg-light bg-opacity-25">
                                                            <table class="table table-sm table-bordered bg-white shadow-sm rounded-3 overflow-hidden">
                                                                <thead class="table-dark">
                                                                    <tr class="small text-uppercase">
                                                                        <th class="ps-3 py-2">Item</th>
                                                                        <th class="text-center py-2">Qty</th>
                                                                        <th class="text-end py-2">Price</th>
                                                                        <th class="text-end py-2">Taxable</th>
                                                                        <th class="text-center py-2">GST%</th>
                                                                        <th class="text-end py-2">GST Amt</th>
                                                                        <th class="text-end pe-3 py-2">Total</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="text-dark">
                                                                    <?php while($item = $items_res->fetch_assoc()): 
                                                                        $item_taxable = $item['qty'] * $item['unit_price'];
                                                                        $item_gst_amt = ($item_taxable * $item['gst_percent']) / 100;
                                                                        $total_taxable += $item_taxable;
                                                                        $bill_grand_total += $item['grand_total'];
                                                                    ?>
                                                                    <tr class="small">
                                                                        <td class="ps-3"><?= htmlspecialchars($item['item_name']) ?></td>
                                                                        <td class="text-center"><?= $item['qty'] + 0 ?></td>
                                                                        <td class="text-end">₹<?= number_format($item['unit_price'], 2) ?></td>
                                                                        <td class="text-end">₹<?= number_format($item_taxable, 2) ?></td>
                                                                        <td class="text-center"><?= $item['gst_percent'] ?>%</td>
                                                                        <td class="text-end">₹<?= number_format($item_gst_amt, 2) ?></td>
                                                                        <td class="text-end pe-3 fw-bold">₹<?= number_format($item['grand_total'], 2) ?></td>
                                                                    </tr>
                                                                    <?php endwhile; ?>
                                                                </tbody>
                                                                <tfoot class="bg-light text-dark fw-bold small">
                                                                    <tr>
                                                                        <td colspan="3" class="text-end py-2">BILL SUMMARY:</td>
                                                                        <td class="text-end">₹<?= number_format($total_taxable, 2) ?></td>
                                                                        <td class="text-center">Tax: ₹<?= number_format($bill_grand_total - $total_taxable, 2) ?></td>
                                                                        <td colspan="2" class="text-end pe-3 text-primary">GRAND TOTAL: ₹<?= number_format($bill_grand_total, 2) ?></td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    </div>
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
        </div>
    <?php endif; ?>
</div>

<style>
    .vendor-toggle { border-radius: 0; transition: all 0.2s; border-bottom: 1px solid #eee; }
    .vendor-toggle:not(.collapsed) { background-color: #f8faff; border-bottom: 2px solid #0d6efd; }
    .vendor-toggle .toggle-icon { transition: transform 0.3s; }
    .vendor-toggle:not(.collapsed) .toggle-icon { transform: rotate(180deg); color: #0d6efd; }
    
    .master-row { cursor: pointer; }
    .master-row:hover { background-color: #fcfcfc !important; }
    .master-row[aria-expanded="true"] .chevron-icon { transform: rotate(90deg); color: #0d6efd; }
    .chevron-icon { transition: transform 0.2s ease; color: #adb5bd; font-size: 0.75rem; }
    
    .table thead th { font-size: 0.7rem; letter-spacing: 0.5px; }
    .accordion-flush .card { border: 1px solid #eee !important; overflow: hidden; }
</style>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>