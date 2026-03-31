<?php
include "../config/db.php";
session_start();

// Check if user is logged in and is either SuperAdmin or Admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$message = "";
$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

// --- INSERT / UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_stock'])) {
    $item_id = (int)$_POST['furniture_item_id'];
    $qty = (int)$_POST['quantity'];
    $bill_no = mysqli_real_escape_string($conn, $_POST['bill_no']);
    $bill_date = $_POST['bill_date'];
    $vendor_id = (int)$_POST['vendor_id'];
    $unit_id = (int)$_POST['unit_id'];
    $price = (float)$_POST['unit_price'];

    if (!empty($_POST['edit_id'])) {
        $edit_id = (int)$_POST['edit_id'];
        $sql = "UPDATE furniture_stock SET 
                furniture_item_id='$item_id', total_qty='$qty', available_qty='$qty', 
                bill_no='$bill_no', bill_date='$bill_date', vendor_id='$vendor_id', 
                unit_id='$unit_id', unit_price='$price' 
                WHERE id=$edit_id";
    } else {
        $sql = "INSERT INTO furniture_stock (furniture_item_id, total_qty, available_qty, bill_no, bill_date, vendor_id, unit_id, unit_price) 
                VALUES ('$item_id', '$qty', '$qty', '$bill_no', '$bill_date', '$vendor_id', '$unit_id', '$price')";
    }

    $message = ($conn->query($sql)) ? "success" : "error";
}

// --- DATA FETCHING ---
$items = $conn->query("SELECT * FROM furniture_items ORDER BY item_name");
$vendors = $conn->query("SELECT * FROM vendors ORDER BY vendor_name");

if ($user_role === 'SuperAdmin') {
    $units = $conn->query("SELECT id, unit_name FROM units ORDER BY unit_name");
} else {
    $units = $conn->query("SELECT id, unit_name FROM units WHERE division_id = '$user_division' ORDER BY unit_name");
}

// --- EDIT FETCH LOGIC ---
$edit_data = null;
if (isset($_POST['trigger_edit'])) {
    $id = (int)$_POST['trigger_edit'];
    $edit_res = $conn->query("SELECT * FROM furniture_stock WHERE id = $id");
    $edit_data = $edit_res->fetch_assoc();
}

$page_title = "Add Furniture Stock"; 
ob_start();
?>

<div class="container-fluid py-4 px-4 mt-n3">
    <div class="row">
        <div class="col-12"> 
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-center mb-5">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="bi bi-box-seam-fill text-success fs-4"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0" id="formTitle">Stock Entry</h4>
                            <p class="text-muted mb-0">Record new furniture inventory arrivals.</p>
                        </div>
                    </div>

                    <form method="POST" id="furnitureForm">
                        <input type="hidden" name="edit_id" id="edit_id">
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Furniture Item Type</label>
                                    <select name="furniture_item_id" id="f_item" class="form-select form-select-lg rounded-3 fs-6" required>
                                        <option value="" disabled selected>Select an item type...</option>
                                        <?php $items->data_seek(0); while($row = $items->fetch_assoc()): ?>
                                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['item_name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-muted text-uppercase">Total Quantity</label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-hash"></i></span>
                                            <input type="number" name="quantity" id="f_qty" class="form-control border-start-0 rounded-end-3 fs-6" placeholder="e.g. 50" required min="1">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-muted text-uppercase">Unit Price</label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text bg-light border-end-0">₹</span>
                                            <input type="number" step="0.01" name="unit_price" id="f_price" class="form-control border-start-0 rounded-end-3 fs-6" placeholder="0.00" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Supplier / Vendor</label>
                                    <select name="vendor_id" id="f_vendor" class="form-select form-select-lg rounded-3 fs-6" required>
                                        <option value="" disabled selected>Select vendor...</option>
                                        <?php $vendors->data_seek(0); while($v = $vendors->fetch_assoc()): ?>
                                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vendor_name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Bill / Invoice Number</label>
                                    <input type="text" name="bill_no" id="f_bill" class="form-control form-control-lg rounded-3 fs-6" placeholder="INV-2024-001" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Purchase Date</label>
                                    <input type="date" name="bill_date" id="f_date" class="form-control form-control-lg rounded-3 fs-6" value="<?= date('Y-m-d') ?>" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Receiving Unit</label>
                                    <select name="unit_id" id="f_unit" class="form-select border-success-subtle form-select-lg rounded-3 fs-6" required>
                                        <option value="" disabled selected>Select destination unit...</option>
                                        <?php $units->data_seek(0); while($u = $units->fetch_assoc()): ?>
                                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['unit_name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <?php if($user_role !== 'SuperAdmin'): ?>
                                        <div class="form-text x-small text-success">Showing units for your assigned division.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5 opacity-50">

                        <div class="row justify-content-end g-3">
                            <div class="col-md-3">
                                <button type="button" onclick="resetForm()" class="btn btn-light btn-lg w-100 rounded-pill border fw-bold fs-6">Cancel</button>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="save_stock" id="submitBtn" class="btn btn-success btn-lg w-100 rounded-pill fw-bold py-3 shadow-sm fs-6">
                                    <i class="bi bi-cloud-arrow-up-fill me-2"></i> Save Stock Details
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function resetForm() {
    document.getElementById('furnitureForm').reset();
    document.getElementById('edit_id').value = "";
    document.getElementById('formTitle').innerText = "Stock Entry";
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i> Save Stock Details';
}

<?php if($message == "success"): ?>
    Swal.fire({ icon: 'success', title: 'Stock Added', text: 'Inventory records updated successfully.', timer: 2500, showConfirmButton: false });
<?php elseif($message == "error"): ?>
    Swal.fire({ icon: 'error', title: 'Error', text: 'Could not process stock entry.' });
<?php endif; ?>
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>