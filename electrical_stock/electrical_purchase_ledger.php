<?php
require_once __DIR__ . "/../config/db.php";
session_start();

// --- 1. SESSION & ROLE CHECK ---
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

// 1. Filtered by category
$max_sl_query = $conn->query("SELECT MAX(CAST(master_sl_no AS UNSIGNED)) as max_sl 
                             FROM purchase_ledger 
                             WHERE category = 'Electrical'");

// 2. Fetch the associative array
$max_row = $max_sl_query->fetch_assoc();

// 3. Calculate the next number
$next_sl_no = ($max_row['max_sl'] ?? 0) + 1;

// Fetch Vendors filtered for Electrical
$vendors = $conn->query("SELECT id, vendor_name FROM vendors WHERE category IN ('Electricals', 'General') ORDER BY vendor_name ASC");

$page_title = "Electrical Purchase Registry";
ob_start();
?>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'duplicate'): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Duplicate SL No!</strong> Reference <b><?= htmlspecialchars($_GET['sl']) ?></b> already exists in the Electrical Ledger.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="container-fluid py-4 px-4">
    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1">Electrical Purchase Registry</h4>
        <p class="text-muted small">Log procurement for wiring, switches, and electrical infrastructure.</p>
    </div>

    <form action="process_electrical_purchase.php" method="POST" id="purchaseForm">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted text-uppercase">SL. No (Ref)</label>
                                <input type="text" name="master_sl_no" 
                                    class="form-control rounded-3 border-warning-subtle fw-bold" 
                                    value="<?= $next_sl_no ?>" required>
                                <div class="form-text" style="font-size: 0.7rem;">Next available Electrical ID</div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold text-muted text-uppercase">Purchase Date</label>
                                <input type="date" name="purchase_date" id="purchase_date"
                                    class="form-control rounded-3 border-light-subtle text-dark" 
                                    value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" 
                                    onchange="validateDate(this)" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Electrical Vendor</label>
                                <select name="vendor_id" class="form-select rounded-3 border-light-subtle" required>
                                    <option value="">Select Vendor</option>
                                    <?php while($v = $vendors->fetch_assoc()): ?>
                                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vendor_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Invoice / Bill No.</label>
                                <input type="text" name="bill_no" class="form-control rounded-3 border-light-subtle" placeholder="Enter Invoice Number" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold m-0 text-warning"><i class="bi bi-lightning-fill me-2"></i>Material Details</h6>
                        <button type="button" onclick="addRow()" class="btn btn-sm btn-warning rounded-pill px-3 text-white">
                            <i class="bi bi-plus-circle me-1"></i> Add Material
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-muted small fw-bold">DESCRIPTION</th>
                                    <th style="width: 100px;" class="py-3 text-muted small fw-bold">QTY</th>
                                    <th style="width: 150px;" class="py-3 text-muted small fw-bold text-end">UNIT PRICE</th>
                                    <th style="width: 150px;" class="py-3 text-muted small fw-bold text-end text-warning">SUBTOTAL</th>
                                    <th style="width: 100px;" class="py-3 text-muted small fw-bold text-center">GST %</th>
                                    <th style="width: 150px;" class="py-3 text-muted small fw-bold text-end">GRAND TOTAL</th>
                                    <th style="width: 50px;" class="pe-4 text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="itemBody">
                                <tr class="item-row">
                                    <td class="ps-4">
                                        <input type="text" name="item_name[]" class="form-control border-0 bg-light py-2" placeholder="e.g. 1.5mm Wire Red" required>
                                    </td>
                                    <td><input type="number" name="qty[]" class="form-control qty-input border-light-subtle" min="0.01" step="any" required></td>
                                    <td><input type="number" name="unit_price[]" class="form-control price-input border-light-subtle text-end" step="0.01" required></td>
                                    <td class="text-end"><span class="row-subtotal fw-medium text-dark">₹0.00</span></td>
                                    <td><input type="number" name="gst_percent[]" class="form-control gst-input text-center" value="18" step="0.01"></td>
                                    <td class="text-end fw-bold text-warning pe-3"><span class="row-grand">₹0.00</span></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer bg-white border-top-0 p-4">
                        <div class="row justify-content-end text-end">
                            <div class="col-md-4">
                                <div class="p-4 rounded-4 bg-light shadow-sm border border-warning-subtle">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted small">Total Taxable Value:</span>
                                        <span class="fw-bold" id="final_subtotal">₹0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted small">Total GST Amount:</span>
                                        <span class="fw-bold text-danger" id="final_gst">+₹0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">Discount (₹):</span>
                                        <input type="number" name="global_discount" id="global_discount" 
                                               class="form-control form-control-sm border-danger-subtle text-end fw-bold text-danger shadow-sm" 
                                               style="width: 120px;" value="0" step="0.01" oninput="calculateAll()">
                                    </div>
                                    <hr class="my-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-dark fw-bold">Payable Total:</span>
                                        <span class="fs-3 fw-bold text-warning" id="final_grand_total">₹0.00</span>
                                    </div>
                                </div>
                                <div class="mt-4 d-grid">
                                    <button type="submit" id="submitBtn" class="btn btn-warning btn-lg rounded-pill shadow px-5 fw-bold text-white">
                                        <i class="bi bi-file-earmark-post me-2"></i> Post to Electrical Ledger
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // Logic for Row Addition and Calculations
    function addRow() {
        const tbody = document.getElementById('itemBody');
        const newRow = document.createElement('tr');
        newRow.className = 'item-row border-top';
        newRow.innerHTML = `
            <td class="ps-4"><input type="text" name="item_name[]" class="form-control border-0 bg-light py-2" required></td>
            <td><input type="number" name="qty[]" class="form-control qty-input border-light-subtle" step="any" required></td>
            <td><input type="number" name="unit_price[]" class="form-control price-input text-end" step="0.01" required></td>
            <td class="text-end"><span class="row-subtotal fw-medium">₹0.00</span></td>
            <td><input type="number" name="gst_percent[]" class="form-control gst-input text-center" value="18"></td>
            <td class="text-end fw-bold text-warning pe-3"><span class="row-grand">₹0.00</span></td>
            <td class="pe-4 text-center">
                <button type="button" onclick="this.closest('tr').remove(); calculateAll();" class="btn btn-link text-danger p-0"><i class="bi bi-trash3-fill"></i></button>
            </td>`;
        tbody.appendChild(newRow);
        attachListeners(newRow);
    }

    function calculateAll() {
        let totalNet = 0, totalTax = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const unitPrice = parseFloat(row.querySelector('.price-input').value) || 0;
            const gstP = parseFloat(row.querySelector('.gst-input').value) || 0;
            const rowNet = qty * unitPrice;
            const rowTax = (rowNet * gstP) / 100;
            const rowGrand = rowNet + rowTax;
            row.querySelector('.row-subtotal').textContent = '₹' + rowNet.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            row.querySelector('.row-grand').textContent = '₹' + rowGrand.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            totalNet += rowNet; totalTax += rowTax;
        });
        const disc = parseFloat(document.getElementById('global_discount').value) || 0;
        const finalTotal = Math.max(0, (totalNet + totalTax) - disc);
        document.getElementById('final_subtotal').textContent = '₹' + totalNet.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        document.getElementById('final_gst').textContent = '+₹' + totalTax.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        document.getElementById('final_grand_total').textContent = '₹' + finalTotal.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    }

    function attachListeners(row) {
        row.querySelectorAll('input').forEach(input => input.addEventListener('input', calculateAll));
    }

    // Double-submit prevention
    document.getElementById('purchaseForm').onsubmit = function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
    };

    document.querySelectorAll('.item-row').forEach(attachListeners);
</script>

<style>
    .form-control:focus { border-color: #ffc107; box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.15); }
    .text-warning { color: #ff9800 !important; }
    .btn-warning { background-color: #ff9800 !important; border-color: #ff9800 !important; }
    .border-warning-subtle { border-color: #ffe69c !important; }
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
</style>

<?php 
$content = ob_get_clean(); 
include "electricalslayout.php"; 
?>