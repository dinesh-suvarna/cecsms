<?php
include "../config/db.php";
session_start();

// --- 1. SESSION & ROLE CHECK ---
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$message = "";
$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

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
    $edit_res = $conn->query("SELECT * FROM furniture_stock WHERE id = $id");
    $edit_data = $edit_res->fetch_assoc();
    if ($edit_data) {
        $is_edit = true;
    }
}

// --- 4. INSERT / UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_stock'])) {
    $item_id = (int)$_POST['furniture_item_id'];
    $qty = (int)$_POST['quantity'];
    $bill_no = mysqli_real_escape_string($conn, $_POST['bill_no']);
    $bill_date = $_POST['bill_date'];
    $vendor_id = (int)$_POST['vendor_id'];
    $unit_id = (int)$_POST['unit_id'];
    $price = (float)$_POST['unit_price'];

    if ($price <= 0 || $qty <= 0) {
        $message = "error";
    } else {
        if (!empty($_POST['edit_id'])) {
            $edit_id = (int)$_POST['edit_id'];
            $sql = "UPDATE furniture_stock SET 
                    furniture_item_id='$item_id', total_qty='$qty', available_qty='$qty', 
                    bill_no='$bill_no', bill_date='$bill_date', vendor_id='$vendor_id', 
                    unit_id='$unit_id', unit_price='$price' 
                    WHERE id=$edit_id";
            
            if ($conn->query($sql)) {
                $_SESSION['swal_msg'] = "Stock updated successfully!";
                $_SESSION['swal_type'] = "success";
                header("Location: view_furniture.php");
                exit();
            } else { $message = "error"; }
        } else {
            $sql = "INSERT INTO furniture_stock (furniture_item_id, total_qty, available_qty, bill_no, bill_date, vendor_id, unit_id, unit_price) 
                    VALUES ('$item_id', '$qty', '$qty', '$bill_no', '$bill_date', '$vendor_id', '$unit_id', '$price')";
            
            if ($conn->query($sql)) {
                $_SESSION['swal_msg'] = "Stock added successfully!";
                $_SESSION['swal_type'] = "success";
                header("Location: add_furniture.php");
                exit(); 
            } else { $message = "error"; }
        }
    }
}

// --- 5. DATA FETCHING ---
$items = $conn->query("SELECT * FROM furniture_items ORDER BY item_name");

// Logic: Show all Electrical vendors PLUS the one currently saved in the record (if editing)
$current_v_id = ($is_edit) ? (int)$edit_data['vendor_id'] : 0;
$vendors = $conn->query("SELECT * FROM vendors WHERE category = 'Furniture' OR id = $current_v_id ORDER BY vendor_name");

if ($user_role === 'SuperAdmin') {
    $divisions = $conn->query("SELECT * FROM divisions ORDER BY division_name");
    $units_res = $conn->query("SELECT u.id, u.unit_name, u.unit_code, u.division_id FROM units u ORDER BY u.unit_name ASC");
} else {
    $units_res = $conn->query("SELECT id, unit_name, unit_code, division_id FROM units WHERE division_id = '$user_division' ORDER BY unit_name ASC");
}

$page_title = $is_edit ? "Edit Furniture Stock" : "Add Furniture Stock"; 
ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    
    .content-wrapper-full {
        width: 100%;
        padding: 0; 
    }

    .main-card {
        border-radius: 15px;
        border: 1px solid #eef0f2;
        background: #fff;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02) !important;
        margin-bottom: 2rem;
    }

    .form-section-header {
        display: flex;
        align-items: center;
        margin: 25px 0 15px 0;
    }
    .form-section-header .line {
        flex: 1;
        height: 1px;
        background: #f1f3f5;
    }
    .form-section-header .text {
        padding: 0 15px;
        font-weight: 800;
        font-size: 0.85rem;
        color: blue;
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
        border-color: #4361ee;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.05);
    }
    .field-wrapper label {
        display: block;
        font-size: 0.65rem;
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .field-wrapper .form-control, 
    .field-wrapper .form-select {
        border: none !important;
        padding: 0 !important;
        background: transparent !important;
        font-weight: 500;
        font-size: 0.95rem;
        color: #333;
        min-height: auto;
    }
    .field-wrapper .form-control:focus { box-shadow: none !important; }

    .select2-container--bootstrap-5.select2-container--focus .select2-selection {
    border-color: #198754 !important; 
    box-shadow: none;
}
    .select2-container--bootstrap-5 .select2-selection {
        border: none !important;
        background: transparent !important;
        font-weight: 500 !important;
        padding: 0 !important;
        height: auto !important;
    }

    .btn-save-stock {
        background: #4361ee;
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .btn-save-stock:hover {
        background: #3751d5;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
    }

    
    <?php if($is_edit): ?>
    .main-card { border-top: 4px solid #e33e4d; }
    .edit-indicator { color: #e33e4d; font-weight: 800; font-size: 0.8rem; }
    <?php endif; ?>
</style>

<div class="container-fluid p-0 mt-3 content-wrapper-full">
    <div class="card main-card">
        <div class="card-body p-4 p-md-5">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi <?= $is_edit ? 'bi-pencil-square text-danger' : 'bi-plus-circle text-primary' ?> me-2"></i>
                        <?= $is_edit ? "Modify Stock Record" : "Furniture Stock Entry" ?>
                    </h4>
                    <p class="text-muted small mb-0">Formalize stock entry and facility assignment.</p>
                </div>
                <?php if($is_edit): ?>
                    <span class="edit-indicator"><i class="bi bi-shield-exclamation me-1"></i> EDITING RECORD #<?= $edit_data['id'] ?></span>
                <?php endif; ?>
            </div>

            <form method="POST" id="furnitureForm">
                <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?? '' ?>">
                
                <div class="form-section-header">
                    <span class="text">Item Identity & Logistics</span>
                    <div class="line"></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="field-wrapper">
                            <label>Furniture Item Type</label>
                            <select name="furniture_item_id" class="form-select searchable-select" required>
                                <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Search or select item...</option>
                                <?php while($row = $items->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>" <?= ($is_edit && $edit_data['furniture_item_id'] == $row['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['item_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="field-wrapper">
                            <label>Invoice / Bill Number</label>
                            <input type="text" name="bill_no" class="form-control" value="<?= $edit_data['bill_no'] ?? '' ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section-header">
                    <span class="text">Quantity & Pricing</span>
                    <div class="line"></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="field-wrapper">
                            <label>Total Quantity</label>
                            <input type="number" name="quantity" class="form-control" value="<?= $edit_data['total_qty'] ?? '' ?>" min="1" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="field-wrapper">
                            <label>Unit Price (₹)</label>
                            <input type="number" name="unit_price" class="form-control" value="<?= $edit_data['unit_price'] ?? '' ?>" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="field-wrapper">
                            <label>Purchase Date</label>
                            <input type="date" name="bill_date" class="form-control" value="<?= $edit_data['bill_date'] ?? date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section-header">
                    <span class="text">Unit & Vendor Attribution</span>
                    <div class="line"></div>
                </div>
                <div class="row g-3">
                    <?php if ($user_role === 'SuperAdmin'): ?>
                        <div class="col-md-6">
                            <div class="field-wrapper">
                                <label>Filter by Division</label>
                                <select id="division_filter" class="form-select searchable-select">
                                    <option value="">All Divisions</option>
                                    <?php while($d = $divisions->fetch_assoc()): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="<?= ($user_role === 'SuperAdmin') ? 'col-md-6' : 'col-md-12' ?>">
                        <div class="field-wrapper">
                            <label>Receiving Unit</label>
                            <select name="unit_id" id="unit_select" class="form-select searchable-select" required>
                                <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Assign to unit...</option>
                                <?php while($u = $units_res->fetch_assoc()): 
                                    $unit_label = (!empty($u['unit_code'])) ? strtoupper($u['unit_code']) . " - " . $u['unit_name'] : $u['unit_name'];
                                ?>
                                    <option value="<?= $u['id'] ?>" data-division="<?= $u['division_id'] ?>" <?= ($is_edit && $edit_data['unit_id'] == $u['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($unit_label) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="field-wrapper">
                            <label>Supplier / Vendor</label>
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

                <div class="d-flex justify-content-end gap-3 mt-5 pt-3 border-top">
                    <a href="view_furniture.php" class="btn btn-light px-4 text-muted discard-btn" style="border-radius:10px;">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </a>
                    <button type="submit" name="save_stock" class="btn btn-save-stock">
                        <i class="bi bi-check2-circle me-1"></i> <?= $is_edit ? "Update Changes" : "Save Stock Entry" ?>
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

    // 1. SuperAdmin Nested Filtering
    <?php if ($user_role === 'SuperAdmin'): ?>
    const allUnits = $('#unit_select option').clone();
    $('#division_filter').on('change', function() {
        const divId = $(this).val();
        $('#unit_select').empty().append('<option value="">Assign to unit...</option>');
        allUnits.each(function() {
            if (divId === "" || $(this).data('division') == divId) {
                $('#unit_select').append($(this).clone());
            }
        });
        $('#unit_select').trigger('change');
    });
    <?php endif; ?>

    // 2. Swal Alerts
    <?php if ($display_swal): ?>
        Swal.fire({
            icon: '<?= $swal_type ?>',
            title: 'Saved',
            text: '<?= $swal_text ?>',
            timer: 2000,
            showConfirmButton: false
        });
    <?php endif; ?>

    <?php if($message == "error"): ?>
        Swal.fire({ icon: 'error', title: 'Oops', text: 'Validation failed.' });
    <?php endif; ?>

    // 3. Confirm Back Logic
    $('.discard-btn').on('click', function(e) {
        if($('#furnitureForm').serialize().length > 50) { // Simple check if form was touched
            e.preventDefault();
            const url = $(this).attr('href');
            Swal.fire({
                title: 'Unsaved Changes',
                text: "Discard current input?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4361ee',
                confirmButtonText: 'Yes, Back'
            }).then((result) => { if (result.isConfirmed) window.location.href = url; });
        }
    });
});
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>