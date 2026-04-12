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

// --- 2. THE "FLASH" LOGIC (Fixes the repeating SweetAlert) ---
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
$vendors = $conn->query("SELECT * FROM vendors ORDER BY vendor_name");

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
    /* Main Layout */
    .form-card-container { width: 100%; }
    .card.main-card {
        border-radius: 20px; border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03) !important;
        transition: all 0.3s ease;
    }

    /* RED BORDER FOR EDIT MODE */
    <?php if($is_edit): ?>
    .card.main-card {
        border: 2px solid #e33e4d !important;
        box-shadow: 0 10px 40px rgba(227, 62, 77, 0.15) !important;
    }
    <?php endif; ?>

    .form-label {
        font-size: 0.72rem; font-weight: 700; color: #6c757d;
        text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.3px;
    }

    .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection {
        border-radius: 10px !important; border: 1px solid #eef0f2 !important;
        padding: 10px 14px; font-size: 0.95rem; min-height: 45px;
    }

    /* Button Logic & Chrome Hover Fix */
    .btn-pill {
        padding: 12px 35px; border-radius: 50px; font-weight: 700;
        min-width: 180px; display: inline-flex; align-items: center;
        justify-content: center; border: none; transition: all 0.3s ease;
        color: white !important;
    }

    .btn-update { background: #0d6efd !important; box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2); }
    .btn-update:hover { 
        background: #198754 !important; /* Forces Green on Chrome */
        box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3); transform: translateY(-1px);
    }

    .btn-discard { background: #e33e4d !important; }
    .btn-discard:hover { 
        background: #b91d2b !important;
        box-shadow: 0 4px 15px rgba(227, 62, 77, 0.3); transform: translateY(-1px);
    }

    .edit-badge {
        background: #e33e4d; color: white; font-size: 13px; font-weight: 800;
        padding: 8px 16px; border-radius: 8px; text-transform: uppercase;
    }
    .action-area { margin-top: 30px; }
</style>

<div class="container-fluid py-4">
    <div class="form-card-container">
        <div class="card main-card">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3 text-primary">
                            <i class="bi <?= $is_edit ? 'bi-pencil-square' : 'bi-stack' ?> fs-4"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0"><?= $is_edit ? "Modify Item" : "Stock Entry" ?></h4>
                            <p class="text-muted mb-0 small"><?= $is_edit ? "Currently editing an existing record." : "Enter details for new stock arrival." ?></p>
                        </div>
                    </div>
                    <?php if($is_edit): ?> <span class="edit-badge"><i class="bi bi-exclamation-circle me-1"></i> Edit Mode</span> <?php endif; ?>
                </div>

                <form method="POST" id="furnitureForm">
                    <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?? '' ?>">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Furniture Item Type</label>
                            <select name="furniture_item_id" class="form-select searchable-select" required>
                                <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Select item...</option>
                                <?php while($row = $items->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>" <?= ($is_edit && $edit_data['furniture_item_id'] == $row['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['item_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Bill / Invoice Number</label>
                            <input type="text" name="bill_no" class="form-control" value="<?= $edit_data['bill_no'] ?? '' ?>" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Total Quantity</label>
                            <input type="number" name="quantity" class="form-control" value="<?= $edit_data['total_qty'] ?? '' ?>" min="1" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Unit Price (₹)</label>
                            <input type="number" name="unit_price" class="form-control" value="<?= $edit_data['unit_price'] ?? '' ?>" step="0.01" min="0.01" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" name="bill_date" class="form-control" value="<?= $edit_data['bill_date'] ?? date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <?php if ($user_role === 'SuperAdmin'): ?>
                            <div class="col-md-6">
                                <label class="form-label">Filter by Division</label>
                                <select id="division_filter" class="form-select searchable-select">
                                    <option value="">Show All Units</option>
                                    <?php while($d = $divisions->fetch_assoc()): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="<?= ($user_role === 'SuperAdmin') ? 'col-md-6' : 'col-md-12' ?>">
                            <label class="form-label">Receiving Unit</label>
                            <select name="unit_id" id="unit_select" class="form-select searchable-select" required>
                                <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Select unit...</option>
                                <?php while($u = $units_res->fetch_assoc()): 
                                    $unit_label = (!empty($u['unit_code'])) ? strtoupper($u['unit_code']) . " - " . $u['unit_name'] : $u['unit_name'];
                                ?>
                                    <option value="<?= $u['id'] ?>" data-division="<?= $u['division_id'] ?>" <?= ($is_edit && $edit_data['unit_id'] == $u['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($unit_label) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Supplier / Vendor</label>
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

                    <div class="d-flex justify-content-center gap-3 action-area">
                        <button type="submit" name="save_stock" class="btn btn-pill btn-update">
                            <i class="bi <?= $is_edit ? 'bi-arrow-repeat' : 'bi-check-lg' ?> me-2"></i> 
                            <?= $is_edit ? "Update Item" : "Save Stock" ?>
                        </button>
                        <a href="view_furniture.php" class="btn btn-pill btn-discard text-decoration-none">
                            <?= $is_edit ? "Discard Changes" : "Cancel" ?>
                        </a>
                    </div>
                </form>
            </div>
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
        $('#unit_select').empty().append('<option value="">Select unit...</option>');
        allUnits.each(function() {
            if (divId === "" || $(this).data('division') == divId) {
                $('#unit_select').append($(this).clone());
            }
        });
        $('#unit_select').trigger('change');
    });
    <?php endif; ?>

    // 2. Flash Message Logic (Only runs once)
    <?php if ($display_swal): ?>
        Swal.fire({
            icon: '<?= $swal_type ?>',
            title: '<?= $swal_type == "success" ? "Great!" : "Notice" ?>',
            text: '<?= $swal_text ?>',
            timer: 2500,
            showConfirmButton: false
        });
    <?php endif; ?>

    // 3. Error Logic
    <?php if($message == "error"): ?>
        Swal.fire({ icon: 'error', title: 'Oops...', text: 'Validation failed. Check your data.' });
    <?php endif; ?>

    // 4. Confirm Cancel Logic
    $('.btn-discard').on('click', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        Swal.fire({
            title: 'Discard Changes?',
            text: "Unsaved modifications will be lost.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e33e4d',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, discard!'
        }).then((result) => { if (result.isConfirmed) window.location.href = url; });
    });
});
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>