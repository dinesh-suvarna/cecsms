<?php
include "../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$ledger_res = $conn->query("SELECT * FROM purchase_ledger WHERE id = $id");
if ($ledger_res->num_rows == 0) { die("Record not found."); }
$ledger = $ledger_res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $p_date = $_POST['purchase_date'];
    $vendor_id = $_POST['vendor_id'];
    $bill_no = $_POST['bill_no'];
    $global_discount = floatval($_POST['global_discount']);
    $category = $_POST['category'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE purchase_ledger SET purchase_date=?, vendor_id=?, bill_no=?, discount_amount=? WHERE id=?");
        $stmt->bind_param("sisdi", $p_date, $vendor_id, $bill_no, $global_discount, $id);
        $stmt->execute();

        $conn->query("DELETE FROM purchase_items WHERE ledger_id = $id");

        $running_total = 0;
        $item_stmt = $conn->prepare("INSERT INTO purchase_items (ledger_id, item_name, qty, unit_price, gst_percent, net_total, grand_total) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($_POST['item_name'] as $key => $name) {
            $qty = floatval($_POST['qty'][$key]);
            $price = floatval($_POST['unit_price'][$key]);
            $gst_p = floatval($_POST['gst_percent'][$key]);
            $net = $qty * $price;
            $tax = ($net * $gst_p / 100);
            $grand = $net + $tax;
            $running_total += $grand;
            $item_stmt->bind_param("isddddd", $id, $name, $qty, $price, $gst_p, $net, $grand);
            $item_stmt->execute();
        }

        $final_amount = max(0, $running_total - $global_discount);
        $conn->query("UPDATE purchase_ledger SET final_invoice_amount = $final_amount WHERE id = $id");
        $conn->commit();
        
        $redirect = ($category == 'Electrical') ? "electrical_registry.php" : "view_purchase_ledger.php";
        header("Location: $redirect?msg=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}

$page_title = "Edit Purchase Record";
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root { --saas-blue: #0061f2; --saas-gray: #f8f9fc; }
    body { background-color: #f4f5f8; font-family: 'Inter', sans-serif; }
    .card { border: none; border-radius: 12px; box-shadow: 0 0.15rem 1.75rem 0 rgba(33, 40, 50, 0.05); }
    .form-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.025em; color: #69707a; margin-bottom: 0.5rem; }
    .form-control, .form-select { border-radius: 8px; padding: 0.6rem 0.75rem; border: 1px solid #d0d7de; }
    .form-control:focus { box-shadow: 0 0 0 0.25rem rgba(0, 97, 242, 0.1); border-color: var(--saas-blue); }
    .table thead th { background: var(--saas-gray); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #4e73df; border-top: none; }
    .item-row { transition: background-color 0.2s ease; }
    .item-row:hover { background-color: #fdfdfd; }
    .btn-add { color: var(--saas-blue); font-weight: 600; font-size: 0.85rem; padding: 0.5rem 1rem; border-radius: 8px; border: 1px dashed var(--saas-blue); background: transparent; }
    .btn-add:hover { background: rgba(0, 97, 242, 0.05); }
    .summary-box { background: var(--saas-gray); border-radius: 12px; padding: 20px; }
    .grand-total-label { font-size: 1.1rem; font-weight: 700; color: #212529; }
    .grand-total-amount { font-size: 1.5rem; font-weight: 800; color: var(--saas-blue); }
</style>

<div class="container-fluid py-4 px-lg-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-0">Edit Purchase</h3>
            <p class="text-muted small mb-0">Master ID: #<?= $ledger['master_sl_no'] ?> | Category: <?= $ledger['category'] ?></p>
        </div>
        <button type="button" onclick="confirmDiscard()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Registry
        </button>
    </div>

    <form method="POST" id="purchaseForm">
        <input type="hidden" name="category" value="<?= $ledger['category'] ?>">
        
        <div class="row g-4">
            <div class="col-lg-9">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Purchase Date</label>
                                <input type="date" name="purchase_date" class="form-control" value="<?= $ledger['purchase_date'] ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Vendor Name</label>
                                <select name="vendor_id" class="form-select shadow-none" required>
                                    <?php
                                    $vendors = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name");
                                    while($v = $vendors->fetch_assoc()) {
                                        $sel = ($v['id'] == $ledger['vendor_id']) ? 'selected' : '';
                                        echo "<option value='{$v['id']}' $sel>{$v['vendor_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Bill / Invoice No</label>
                                <input type="text" name="bill_no" class="form-control" value="<?= htmlspecialchars($ledger['bill_no']) ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="itemTable">
                            <thead>
                                <tr>
                                    <th class="ps-4">Item Details</th>
                                    <th style="width: 100px;">Qty</th>
                                    <th style="width: 160px;">Unit Price (₹)</th>
                                    <th style="width: 100px;">GST %</th>
                                    <th style="width: 160px;">Total Amount</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $items_res = $conn->query("SELECT * FROM purchase_items WHERE ledger_id = $id");
                                while($item = $items_res->fetch_assoc()):
                                ?>
                                <tr class="item-row">
                                    <td class="ps-4"><input type="text" name="item_name[]" class="form-control border-0 shadow-none bg-transparent" value="<?= htmlspecialchars($item['item_name']) ?>" placeholder="e.g. Office Chair" required></td>
                                    <td><input type="number" step="any" name="qty[]" class="form-control form-control-sm qty text-center" value="<?= $item['qty'] ?>" required></td>
                                    <td><input type="number" step="any" name="unit_price[]" class="form-control form-control-sm price" value="<?= $item['unit_price'] ?>" required></td>
                                    <td><input type="number" step="any" name="gst_percent[]" class="form-control form-control-sm gst text-center" value="<?= $item['gst_percent'] ?>" required></td>
                                    <td><input type="text" class="form-control form-control-sm item-total border-0 bg-transparent fw-bold text-dark" readonly value="<?= number_format($item['grand_total'], 2) ?>"></td>
                                    <td class="pe-3"><button type="button" class="btn btn-link text-danger p-0 remove-row"><i class="bi-trash3-fill"></i></button></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white border-top-0 py-3 text-center">
                        <button type="button" class="btn btn-add px-4" id="addRow">
                            <i class="bi bi-plus-lg me-1"></i> Add Another Item
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-4">Summary</h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Global Discount (₹)</label>
                            <input type="number" name="global_discount" id="global_discount" class="form-control" value="<?= $ledger['discount_amount'] ?>">
                        </div>

                        <hr class="my-4 opacity-10">

                        <div class="summary-box">
                            <div class="d-flex justify-content-between mb-2 text-muted">
                                <span class="small">Subtotal</span>
                                <span class="small fw-bold" id="subTotal">₹0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3 text-muted">
                                <span class="small">Applied Discount</span>
                                <span class="small text-danger fw-bold" id="appliedDisc">₹0.00</span>
                            </div>
                            <div class="pt-3 border-top border-2">
                                <div class="grand-total-label mb-1">Final Amount</div>
                                <div class="grand-total-amount">₹<span id="finalTotal">0.00</span></div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary py-2 fw-bold rounded-3 shadow-sm">Save Changes</button>
                            <button type="button" onclick="confirmDiscard()" class="btn btn-light py-2 small">Discard Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Configure Toast Settings
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

function confirmDiscard() {
    Swal.fire({
        title: 'Discard Changes?',
        text: "Any unsaved modifications will be lost.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0061f2',
        cancelButtonColor: '#69707a',
        confirmButtonText: 'Yes, discard',
        cancelButtonText: 'Stay here',
        customClass: { popup: 'rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "<?= ($ledger['category'] == 'Electrical') ? 'electrical_registry.php' : 'view_purchase_ledger.php' ?>";
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('#itemTable tbody');
    const purchaseForm = document.getElementById('purchaseForm');
    
    function calculate() {
        let total = 0;
        document.querySelectorAll('#itemTable tbody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty').value) || 0;
            const price = parseFloat(row.querySelector('.price').value) || 0;
            const gst = parseFloat(row.querySelector('.gst').value) || 0;
            
            const net = qty * price;
            const grand = net + (net * gst / 100);
            
            row.querySelector('.item-total').value = grand.toLocaleString('en-IN', {minimumFractionDigits: 2});
            total += grand;
        });
        
        const disc = parseFloat(document.getElementById('global_discount').value) || 0;
        const final = Math.max(0, total - disc);
        
        document.getElementById('subTotal').innerText = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('appliedDisc').innerText = '₹' + disc.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('finalTotal').innerText = final.toLocaleString('en-IN', {minimumFractionDigits: 2});
    }

    document.getElementById('addRow').addEventListener('click', () => {
        const newRow = `<tr class="item-row">
            <td class="ps-4"><input type="text" name="item_name[]" class="form-control border-0 shadow-none bg-transparent" required placeholder="Item description..."></td>
            <td><input type="number" step="any" name="qty[]" class="form-control form-control-sm qty text-center" required></td>
            <td><input type="number" step="any" name="unit_price[]" class="form-control form-control-sm price" required></td>
            <td><input type="number" step="any" name="gst_percent[]" class="form-control form-control-sm gst text-center" value="18" required></td>
            <td><input type="text" class="form-control form-control-sm item-total border-0 bg-transparent fw-bold text-dark" readonly></td>
            <td class="pe-3"><button type="button" class="btn btn-link text-danger p-0 remove-row"><i class="bi bi-x-circle-fill"></i></button></td>
        </tr>`;
        tableBody.insertAdjacentHTML('beforeend', newRow);
        Toast.fire({ icon: 'success', title: 'New item added' });
        calculate();
    });

    tableBody.addEventListener('input', calculate);
    document.getElementById('global_discount').addEventListener('input', calculate);
    
    tableBody.addEventListener('click', (e) => {
        if(e.target.closest('.remove-row')) {
            const row = e.target.closest('tr');
            
            Swal.fire({
                title: 'Remove this item?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#69707a',
                confirmButtonText: 'Delete',
                customClass: { popup: 'rounded-4' }
            }).then((result) => {
                if (result.isConfirmed) {
                    row.remove();
                    calculate();
                    Toast.fire({ icon: 'info', title: 'Item removed' });
                }
            });
        }
    });

    calculate();
});
</script>

<?php 
$content = ob_get_clean();
if ($ledger['category'] == 'Electrical') {
    include "electricalslayout.php";
} else {
    include "furniturelayout.php";
}
?>