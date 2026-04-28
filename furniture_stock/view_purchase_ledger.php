<?php
require_once __DIR__ . "/../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$current_category = 'Furniture'; 

// Fetch all data (JavaScript will handle filtering)
$query_str = "
    SELECT l.*, v.vendor_name, COUNT(i.id) as total_items
    FROM purchase_ledger l
    JOIN vendors v ON l.vendor_id = v.id
    LEFT JOIN purchase_items i ON l.id = i.ledger_id
    WHERE l.category = '$current_category'
    GROUP BY l.id 
    ORDER BY ABS(l.master_sl_no) ASC";

$result = $conn->query($query_str);

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
            <h4 class="fw-bold text-dark mb-1">Purchase Registry</h4>
            <p class="text-muted small">Vendor-wise summary of purchase history and tax breakdowns.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="input-group shadow-sm border bg-white ms-auto" style="max-width: 350px;">
                <span class="input-group-text border-0 bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="liveSearch" class="form-control border-0 ps-0 text-dark" placeholder="Live Search Ref No, Vendor or Bill...">
            </div>
        </div>
    </div>

    <?php if (empty($grouped_data)): ?>
        <div class="card p-5 text-center text-muted border-0 shadow-sm">No records found.</div>
    <?php else: ?>
        <div class="accordion accordion-flush" id="vendorAccordion">
            <?php 
            $v_index = 0;
            foreach ($grouped_data as $vendor_name => $bills): 
                $v_index++;
                $vendor_id_attr = "vendor_" . $v_index;
            ?>
                <div class="card border-0 shadow-sm mb-3 rounded-3 overflow-hidden vendor-card">
                    <div class="card-header bg-white p-0 border-0">
                        <button class="btn w-100 text-start p-3 d-flex justify-content-between align-items-center vendor-toggle collapsed" 
                                type="button" data-bs-toggle="collapse" data-bs-target="#<?= $vendor_id_attr ?>">
                            <div>
                                <i class="bi bi-person-vcard-fill me-2 text-primary"></i>
                                <span class="fw-bold text-dark vendor-title"><?= htmlspecialchars($vendor_name) ?></span>
                                <span class="ms-2 badge bg-light text-dark border fw-normal small"><?= count($bills) ?> Bills</span>
                            </div>
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </button>
                    </div>

                    <div id="<?= $vendor_id_attr ?>" class="collapse vendor-collapse" data-bs-parent="#vendorAccordion">
                        <div class="card-body p-0 border-top">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr class="small text-uppercase">
                                            <th style="width: 50px;"></th>
                                            <th>Ref SL.No</th>
                                            <th>Date</th>
                                            <th class="text-center">Bill No</th>
                                            <th class="text-center">Items</th>
                                            <th class="text-end pe-4">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bills as $row): 
                                            $l_id = $row['id'];
                                            $items_res = $conn->query("SELECT * FROM purchase_items WHERE ledger_id = $l_id");
                                            $sum_total = 0;
                                        ?>
                                            <tr class="master-row border-bottom purchase-row">
                                                <td class="ps-4 clickable-trigger" data-bs-toggle="collapse" data-bs-target="#details_<?= $l_id ?>"><i class="bi bi-chevron-right chevron-icon"></i></td>
                                                <td class="clickable-trigger ref-no" data-bs-toggle="collapse" data-bs-target="#details_<?= $l_id ?>"><span class="fw-bold text-primary"><?= htmlspecialchars($row['master_sl_no']) ?></span></td>
                                                <td class="text-dark small clickable-trigger" data-bs-toggle="collapse" data-bs-target="#details_<?= $l_id ?>"><?= date('d-m-Y', strtotime($row['purchase_date'])) ?></td>
                                                <td class="text-center text-dark clickable-trigger bill-no" data-bs-toggle="collapse" data-bs-target="#details_<?= $l_id ?>"><?= htmlspecialchars($row['bill_no']) ?></td>
                                                <td class="text-center text-dark clickable-trigger" data-bs-toggle="collapse" data-bs-target="#details_<?= $l_id ?>"><?= $row['total_items'] ?></td>
                                                
                                                <td class="text-end pe-4">
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <a href="edit_purchase_ledger.php?id=<?= $l_id ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil-square"></i> Edit
                                                        </a>
                                                        
                                                        <button onclick="deletePurchase(<?= $l_id ?>, '<?= htmlspecialchars($row['bill_no']) ?>')" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>

                                                        <button class="btn btn-sm btn-link text-muted text-decoration-none fw-bold" data-bs-toggle="collapse" data-bs-target="#details_<?= $l_id ?>">
                                                            View
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr class="collapse-row purchase-details-row">
                                                <td colspan="6" class="p-0 border-0">
                                                    <div class="collapse item-details-collapse" id="details_<?= $l_id ?>">
                                                        <div class="p-4 bg-light bg-opacity-25">
                                                            <table class="table table-sm table-bordered bg-white shadow-sm">
                                                                <thead class="table-dark small">
                                                                    <tr>
                                                                        <th>Item</th>
                                                                        <th class="text-center">Qty</th>
                                                                        <th class="text-end">Price</th>
                                                                        <th class="text-end">GST%</th>
                                                                        <th class="text-end pe-3">Total</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php while($item = $items_res->fetch_assoc()): 
                                                                        $sum_total += $item['grand_total'];
                                                                    ?>
                                                                    <tr class="small">
                                                                        <td class="ps-2"><?= htmlspecialchars($item['item_name']) ?></td>
                                                                        <td class="text-center"><?= $item['qty'] + 0 ?></td>
                                                                        <td class="text-end">₹<?= number_format($item['unit_price'], 2) ?></td>
                                                                        <td class="text-end"><?= $item['gst_percent'] ?>%</td>
                                                                        <td class="text-end pe-3 fw-bold">₹<?= number_format($item['grand_total'], 2) ?></td>
                                                                    </tr>
                                                                    <?php endwhile; ?>
                                                                </tbody>
                                                                <tfoot class="table-light fw-bold small">
                                                                    <tr>
                                                                        <td colspan="4" class="text-end">Final Total:</td>
                                                                        <td class="text-end pe-3 text-primary">₹<?= number_format($sum_total - $row['discount_amount'], 2) ?></td>
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
    .vendor-toggle:not(.collapsed) { background-color: #f8faff; border-bottom: 2px solid #0d6efd; }
    .vendor-toggle .toggle-icon { transition: transform 0.3s; }
    .vendor-toggle:not(.collapsed) .toggle-icon { transform: rotate(180deg); color: #0d6efd; }
    .clickable-trigger { cursor: pointer; transition: background 0.2s; }
    .master-row:hover .clickable-trigger { background-color: #f8f9fa !important; }
    .chevron-icon { transition: transform 0.2s; color: #adb5bd; }
    .master-row .btn-link[aria-expanded="true"] ~ td .chevron-icon,
    .master-row td[aria-expanded="true"] .chevron-icon { transform: rotate(90deg); color: #0d6efd; }
    
    /* Animation for auto-expansion */
    .vendor-card.highlight { border: 1px solid #0d6efd !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('liveSearch');
    const vendorCards = document.querySelectorAll('.vendor-card');

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        
        vendorCards.forEach(card => {
            const vendorName = card.querySelector('.vendor-title').textContent.toLowerCase();
            const purchaseRows = card.querySelectorAll('.purchase-row');
            const detailsRows = card.querySelectorAll('.purchase-details-row');
            const vendorCollapse = card.querySelector('.vendor-collapse');
            const vendorBtn = card.querySelector('.vendor-toggle');
            
            let hasVisiblePurchase = false;

            purchaseRows.forEach((row, index) => {
                // 1. Get the text values and clean them
                const refNo = row.querySelector('.ref-no').textContent.toLowerCase().trim();
                const billNo = row.querySelector('.bill-no').textContent.toLowerCase().trim();
                
                // 2. Get all item names in the breakdown table
                const itemNames = Array.from(detailsRows[index].querySelectorAll('td.ps-2'))
                                     .map(td => td.textContent.toLowerCase())
                                     .join(' ');

                const itemCollapse = detailsRows[index].querySelector('.item-details-collapse');

                if (query === "") {
                    // Reset: Show all, but close all collapses
                    row.style.display = "";
                    detailsRows[index].style.display = "";
                    bootstrap.Collapse.getOrCreateInstance(itemCollapse, {toggle: false}).hide();
                    bootstrap.Collapse.getOrCreateInstance(vendorCollapse, {toggle: false}).hide();
                    vendorBtn.classList.add('collapsed');
                } else {
                    // --- THE UPDATED SMART FILTERING ---
                    
                    // A. STRICT: Ref No must be an exact match (Typing '1' won't show '21')
                    const isExactRef = (refNo === query); 
                    
                    // B. SMART BILL: If query is very short (1-2 chars), check for exact match.
                    // If user types more characters, allow partial matches (like 'INV-1').
                    const isBillMatch = (query.length < 3) ? (billNo === query) : billNo.includes(query);
                    
                    // C. PARTIAL: Vendor and Items remain flexible
                    const isVendorMatch = vendorName.includes(query);
                    const isItemMatch = itemNames.includes(query);

                    // Final check: Should this row stay visible?
                    if (isExactRef || isVendorMatch || isBillMatch || isItemMatch) {
                        row.style.display = "";
                        detailsRows[index].style.display = "";
                        hasVisiblePurchase = true;

                        // Auto-expand the items ONLY if it's a specific Ref, Bill, or Item match
                        if(isExactRef || isBillMatch || isItemMatch) {
                            bootstrap.Collapse.getOrCreateInstance(itemCollapse, {toggle: false}).show();
                        }
                    } else {
                        row.style.display = "none";
                        detailsRows[index].style.display = "none";
                    }
                }
            });

            // Handle Vendor Card Container visibility
            if (query === "") {
                card.style.display = "";
            } else if (hasVisiblePurchase) {
                card.style.display = "";
                bootstrap.Collapse.getOrCreateInstance(vendorCollapse, {toggle: false}).show();
                vendorBtn.classList.remove('collapsed');
            } else {
                card.style.display = "none";
            }
        });
    });
});
</script>
<script>
    // 1. GLOBAL FUNCTION 
function deletePurchase(id, billNo) {
    Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete Bill No: ${billNo}. This cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show a "Deleting..." loading state
            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('delete_purchase.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'The record has been successfully deleted.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload(); 
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error!', 'Something went wrong with the request.', 'error');
                console.error('Error:', error);
            });
        }
    });
}
</script>
<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>