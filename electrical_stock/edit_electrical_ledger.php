<?php
include "../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch existing record
$ledger_res = $conn->query("SELECT * FROM purchase_ledger WHERE id = $id AND category = 'Electrical'");
if ($ledger_res->num_rows == 0) { die("Electrical record not found."); }
$ledger = $ledger_res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $p_date = $_POST['purchase_date'];
    $vendor_id = $_POST['vendor_id'];
    $bill_no = $_POST['bill_no'];
    $global_discount = floatval($_POST['global_discount']);

    $conn->begin_transaction();
    try {
        // Update Main Ledger
        $stmt = $conn->prepare("UPDATE purchase_ledger SET purchase_date=?, vendor_id=?, bill_no=?, discount_amount=? WHERE id=?");
        $stmt->bind_param("sisdi", $p_date, $vendor_id, $bill_no, $global_discount, $id);
        $stmt->execute();

        // Refresh Items (Delete and Re-insert)
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
        header("Location: view_electrical_ledger.php?msg=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error updating record: " . $e->getMessage();
    }
}

$page_title = "Edit Electrical Record";
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
    :root { --elec-warning: #f6c23e; --elec-dark: #2c3e50; --soft-gray: #f8f9fc; }
    body { background-color: var(--soft-gray); font-family: 'Inter', sans-serif; }
    
    .card { border: none; border-radius: 15px; box-shadow: 0 0.5rem 1.5rem 0 rgba(0, 0, 0, 0.05); transition: transform 0.2s; }
    .form-label { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #5a5c69; margin-bottom: 0.5rem; }
    
    /* Premium Table Styling */
    .table thead th { background: #ffffff; color: var(--elec-dark); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #f1f1f1; padding: 15px 10px; }
    .item-row:hover { background-color: #fffdf5; }
    
    /* Input Styling */
    .form-control { border-radius: 8px; border: 1px solid #d1d3e2; }
    .form-control:focus { border-color: var(--elec-warning); box-shadow: 0 0 0 0.25rem rgba(246, 194, 62, 0.15); }
    
    /* Custom Buttons */
    .btn-add-item { border: 2px dashed #d1d3e2; color: #858796; font-weight: 600; background: transparent; padding: 12px; border-radius: 10px; }
    .btn-add-item:hover { border-color: var(--elec-warning); color: var(--elec-warning); background: #fffdf5; }
    
    .summary-card { background: linear-gradient(135deg, #ffffff 0%, #fffef0 100%); border-left: 5px solid var(--elec-warning); }
    .grand-total-display { font-size: 1.75rem; font-weight: 800; color: var(--elec-dark); }
</style>

<div class="container-fluid py-4 px-lg-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Edit Electrical Bill</h3>
            <div class="mt-1">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Reference ID: #<?= $ledger['master_sl_no'] ?></span>
            </div>
        </div>
        <button type="button" onclick="confirmDiscard()" class="btn btn-outline-dark btn-sm rounded-pill px-4 shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Ledger
        </button>
    </div>

    <form method="POST" id="purchaseForm">
        <div class="row g-4">
            <div class="col-lg-9">
                <div class="card mb-4 border-top border-warning border-4 shadow-sm">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" name="purchase_date" class="form-control" value="<?= $ledger['purchase_date'] ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vendor Entity</label>
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
                                <label class="form-label">Invoice / Bill No</label>
                                <input type="text" name="bill_no" class="form-control" value="<?= htmlspecialchars($ledger['bill_no']) ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="itemTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="min-width: 300px;">Item Description</th>
                                    <th style="width: 100px;" class="text-center">Qty</th>
                                    <th style="width: 140px;">Rate (₹)</th>
                                    <th style="width: 100px;" class="text-center">GST %</th>
                                    <th style="width: 140px;" class="text-end pe-4">Total Amount</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $items_res = $conn->query("SELECT * FROM purchase_items WHERE ledger_id = $id");
                                while($item = $items_res->fetch_assoc()):
                                ?>
                                <tr class="item-row">
                                    <td class="ps-4">
                                        <input type="text" name="item_name[]" class="form-control border-0 bg-transparent fw-semibold" value="<?= htmlspecialchars($item['item_name']) ?>" placeholder="e.g. 2.5sqmm Wire Coil" required>
                                    </td>
                                    <td><input type="number" step="any" name="qty[]" class="form-control form-control-sm qty text-center" value="<?= $item['qty'] ?>" required></td>
                                    <td><input type="number" step="any" name="unit_price[]" class="form-control form-control-sm price" value="<?= $item['unit_price'] ?>" required></td>
                                    <td><input type="number" step="any" name="gst_percent[]" class="form-control form-control-sm gst text-center" value="<?= $item['gst_percent'] ?>" required></td>
                                    <td class="text-end pe-4">
                                        <input type="text" class="form-control form-control-sm item-total border-0 bg-transparent text-end fw-bold text-dark" readonly value="<?= number_format($item['grand_total'], 2) ?>">
                                    </td>
                                    <td class="pe-3">
                                        <button type="button" class="btn btn-link text-danger p-0 remove-row"><i class="bi bi-trash3-fill"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white border-top-0 py-4 text-center">
                        <button type="button" class="btn btn-add-item w-50" id="addRow">
                            <i class="bi bi-plus-circle-dotted me-2"></i>Add Another Component
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="card sticky-top shadow-sm" style="top: 20px;">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-4 text-uppercase small letter-spacing-1">Order Summary</h6>
                        
                        <div class="mb-4">
                            <label class="form-label">Global Discount (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">₹</span>
                                <input type="number" name="global_discount" id="global_discount" class="form-control form-control-lg fw-bold text-danger" value="<?= $ledger['discount_amount'] ?>">
                            </div>
                        </div>

                        <div class="summary-card p-3 rounded-4 mb-4 shadow-sm">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Taxable Subtotal:</span>
                                <span class="fw-bold" id="subTotal">₹0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted small">Discount Total:</span>
                                <span class="text-danger fw-bold" id="appliedDisc">₹0.00</span>
                            </div>
                            <div class="pt-3 border-top border-dark border-opacity-10">
                                <div class="text-muted small mb-1 fw-bold text-uppercase" style="font-size: 0.65rem;">Net Payable Amount</div>
                                <div class="grand-total-display">₹<span id="finalTotal">0.00</span></div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning py-3 fw-bold shadow-sm rounded-3 text-dark" id="saveBtn">
                                <i class="bi bi-cloud-arrow-up-fill me-2"></i>Save Bill Changes
                            </button>
                            <button type="button" onclick="confirmDiscard()" class="btn btn-light btn-sm text-muted">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Notification Engine
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true
});

// UI: Confirm Page Exit
function confirmDiscard() {
    Swal.fire({
        title: 'Discard modifications?',
        text: "Any unsaved changes to this electrical bill will be lost permanently.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2c3e50',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, discard',
        cancelButtonText: 'Keep editing',
        customClass: { popup: 'rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "view_electrical_ledger.php";
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('#itemTable tbody');
    const form = document.getElementById('purchaseForm');
    
    // Core Calculation Logic
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

    // Row Addition
    document.getElementById('addRow').addEventListener('click', () => {
        const newRow = `<tr class="item-row">
            <td class="ps-4"><input type="text" name="item_name[]" class="form-control border-0 bg-transparent fw-semibold" required placeholder="Component name..."></td>
            <td><input type="number" step="any" name="qty[]" class="form-control form-control-sm qty text-center" required></td>
            <td><input type="number" step="any" name="unit_price[]" class="form-control form-control-sm price" required></td>
            <td><input type="number" step="any" name="gst_percent[]" class="form-control form-control-sm gst text-center" value="18" required></td>
            <td class="text-end pe-4"><input type="text" class="form-control form-control-sm item-total border-0 bg-transparent text-end fw-bold text-dark" readonly></td>
            <td class="pe-3"><button type="button" class="btn btn-link text-danger p-0 remove-row"><i class="bi bi-x-circle-fill"></i></button></td>
        </tr>`;
        tableBody.insertAdjacentHTML('beforeend', newRow);
        Toast.fire({ icon: 'success', title: 'Line item added' });
        calculate();
    });

    // Real-time Calculation Listeners
    tableBody.addEventListener('input', calculate);
    document.getElementById('global_discount').addEventListener('input', calculate);
    
    // Row Removal with SweetAlert Confirmation
    tableBody.addEventListener('click', (e) => {
        if(e.target.closest('.remove-row')) {
            const row = e.target.closest('tr');
            
            Swal.fire({
                title: 'Remove item?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#2c3e50',
                confirmButtonText: 'Remove',
                customClass: { popup: 'rounded-4' }
            }).then((result) => {
                if (result.isConfirmed) {
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        calculate();
                        Toast.fire({ icon: 'info', title: 'Item removed from list' });
                    }, 200);
                }
            });
        }
    });

    // Form Submission UI Enhancement
    form.addEventListener('submit', function() {
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating Ledger...';
    });

    calculate(); // Initial calculation on load
});
</script>

<?php 
$content = ob_get_clean();
include "electricalslayout.php";
?>