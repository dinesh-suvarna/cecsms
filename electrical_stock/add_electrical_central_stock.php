<?php
require_once __DIR__ . "/../config/db.php";
session_start();

// --- 1. SESSION & ROLE CHECK ---
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$message = "";
$user_role = $_SESSION['role'];

// --- 2. THE "FLASH" LOGIC ---
$display_swal = false;
if (isset($_SESSION['swal_msg'])) {
    $display_swal = true;
    $swal_text = $_SESSION['swal_msg'];
    $swal_type = $_SESSION['swal_type'] ?? 'success';
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
}

// --- 3. EDIT FETCH LOGIC ---
$edit_data = null;
$is_edit = false;

if (isset($_POST['trigger_edit'])) {
    $id = (int)$_POST['trigger_edit'];
    $edit_res = $conn->query("SELECT * FROM electrical_central_stock WHERE id = $id");
    $edit_data = $edit_res->fetch_assoc();
    if ($edit_data) {
        $is_edit = true;
    }
}

// --- 4. INSERT / UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_stock'])) {
    $item_id = (int)$_POST['electrical_item_id'];
    $qty = (int)$_POST['total_qty'];
    $bill_no = mysqli_real_escape_string($conn, $_POST['bill_no']);
    $bill_date = $_POST['bill_date'];
    $vendor_id = (int)$_POST['vendor_id'];
    $price = (float)$_POST['unit_price'];

    if ($price <= 0 || $qty <= 0) {
        $message = "error";
    } else {
        if (!empty($_POST['edit_id'])) {
            $edit_id = (int)$_POST['edit_id'];
            // Update Logic
            $sql = "UPDATE electrical_central_stock SET 
                    electrical_item_id='$item_id', total_qty='$qty', 
                    bill_no='$bill_no', bill_date='$bill_date', vendor_id='$vendor_id', 
                    unit_price='$price' 
                    WHERE id=$edit_id";
            
            if ($conn->query($sql)) {
                $_SESSION['swal_msg'] = "Electrical stock updated successfully!";
                $_SESSION['swal_type'] = "success";
                header("Location: view_electrical_central_stock.php");
                exit();
            } else { $message = "error"; }
        } else {
            // Initial save: remaining_qty = total_qty
            $sql = "INSERT INTO electrical_central_stock (electrical_item_id, total_qty, remaining_qty, bill_no, bill_date, vendor_id, unit_price) 
                    VALUES ('$item_id', '$qty', '$qty', '$bill_no', '$bill_date', '$vendor_id', '$price')";
            
            if ($conn->query($sql)) {
                $_SESSION['swal_msg'] = "Stock saved to Electrical Central Store!";
                $_SESSION['swal_type'] = "success";
                header("Location: add_electrical_central_stock.php");
                exit(); 
            } else { $message = "error"; }
        }
    }
}

// --- 5. DATA FETCHING ---
$items = $conn->query("SELECT * FROM electrical_items ORDER BY item_name");
$vendors = $conn->query("SELECT * FROM vendors WHERE category='Electricals' OR category='General' ORDER BY vendor_name");

$page_title = $is_edit ? "Edit Electrical Central Stock" : "Add Electrical Central Stock"; 
ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    .content-wrapper-full { width: 100%; padding: 0; }
    .main-card {
        border-radius: 15px;
        border: 1px solid #eef0f2;
        background: #fff;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02) !important;
        margin-bottom: 2rem;
    }
    .form-section-header { display: flex; align-items: center; margin: 25px 0 15px 0; }
    .form-section-header .line { flex: 1; height: 1px; background: #f1f3f5; }
    .form-section-header .text {
        padding: 0 15px;
        font-weight: 800;
        font-size: 0.85rem;
        color: #ff9f1c;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .field-wrapper {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 8px 12px;
        transition: all 0.2s ease;
    }
    .field-wrapper:focus-within {
        border-color: #ff9f1c;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(255, 159, 28, 0.05);
    }
    .field-wrapper label {
        display: block;
        font-size: 0.65rem;
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .field-wrapper .form-control, .field-wrapper .form-select {
        border: none !important;
        padding: 0 !important;
        background: transparent !important;
        font-weight: 500;
        font-size: 0.95rem;
        color: #333;
        min-height: auto;
    }
    .field-wrapper .form-control:focus { box-shadow: none !important; }
    
    .btn-save-stock {
        background: #ff9f1c;
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .btn-save-stock:hover {
        background: #e68a00;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 159, 28, 0.2);
    }
    <?php if($is_edit): ?>
    .main-card { border-top: 4px solid #dc3545; }
    .edit-indicator { color: #dc3545; font-weight: 800; font-size: 0.8rem; }
    <?php endif; ?>
</style>

<div class="container-fluid p-0 mt-3 content-wrapper-full">
    <div class="card main-card">
        <div class="card-body p-4 p-md-5">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi <?= $is_edit ? 'bi-pencil-square text-danger' : 'bi-lightning-charge text-warning' ?> me-2"></i>
                        <?= $is_edit ? "Modify Electrical Central Record" : "Electrical Central Stock Entry" ?>
                    </h4>
                    <p class="text-muted small mb-0">Log bulk electrical supply deliveries into the central warehouse.</p>
                </div>
                <?php if($is_edit): ?>
                    <span class="edit-indicator"><i class="bi bi-shield-exclamation me-1"></i> EDITING STOCK #<?= $edit_data['id'] ?></span>
                <?php endif; ?>
            </div>

            <form method="POST" id="electricalForm">
                <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?? '' ?>">
                
                <div class="form-section-header">
                    <span class="text">Product & Supplier</span>
                    <div class="line"></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="field-wrapper">
                            <label>Electrical Item Name</label>
                            <select name="electrical_item_id" class="form-select searchable-select" required>
                                <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Search or select item...</option>
                                <?php while($row = $items->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>" <?= ($is_edit && $edit_data['electrical_item_id'] == $row['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['item_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field-wrapper">
                            <label>Manufacturer / Vendor</label>
                            <select name="vendor_id" class="form-select searchable-select" required>
                                <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Select vendor...</option>
                                <?php $vendors->data_seek(0); while($v = $vendors->fetch_assoc()): ?>
                                    <option value="<?= $v['id'] ?>" <?= ($is_edit && $edit_data['vendor_id'] == $v['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['vendor_name']) ?> 
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section-header">
                    <span class="text">Purchase Details</span>
                    <div class="line"></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="field-wrapper">
                            <label>Quantity Received</label>
                            <input type="number" name="total_qty" class="form-control" value="<?= $edit_data['total_qty'] ?? '' ?>" min="1" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="field-wrapper">
                            <label>Unit Price (₹)</label>
                            <input type="number" name="unit_price" class="form-control" value="<?= $edit_data['unit_price'] ?? '' ?>" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="field-wrapper">
                            <label>Invoice/Bill No.</label>
                            <input type="text" name="bill_no" class="form-control" value="<?= $edit_data['bill_no'] ?? '' ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="field-wrapper">
                            <label>Date of Bill</label>
                            <input type="date" name="bill_date" class="form-control" value="<?= $edit_data['bill_date'] ?? date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-5 pt-3 border-top">
                    <a href="view_electrical_central_stock.php" class="btn btn-light px-4 text-muted discard-btn" style="border-radius:10px;">
                        <i class="bi bi-arrow-left me-1"></i> Back to Inventory
                    </a>
                    <button type="submit" name="save_stock" class="btn btn-save-stock">
                        <i class="bi bi-check2-circle me-1"></i> <?= $is_edit ? "Update Entry" : "Save Entry" ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    $('.searchable-select').select2({ theme: 'bootstrap-5', width: '100%' });

    <?php if ($display_swal): ?>
        Swal.fire({
            icon: '<?= $swal_type ?>',
            title: 'Success',
            text: '<?= $swal_text ?>',
            timer: 2000,
            showConfirmButton: false
        });
    <?php endif; ?>

    <?php if($message == "error"): ?>
        Swal.fire({ icon: 'error', title: 'Error', text: 'Please ensure all fields are filled correctly.' });
    <?php endif; ?>

    $('.discard-btn').on('click', function(e) {
        if($('#electricalForm').serialize().length > 50) {
            e.preventDefault();
            const url = $(this).attr('href');
            Swal.fire({
                title: 'Unsaved Progress',
                text: "Discard changes and return to the list?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff9f1c',
                confirmButtonText: 'Yes, Discard'
            }).then((result) => { if (result.isConfirmed) window.location.href = url; });
        }
    });
});
</script>

<?php 
$content = ob_get_clean(); 
include "electricalslayout.php"; 
?>