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
$divisions = null;

// Logic: Show all Furniture vendors PLUS the one currently saved in the record (if editing)
$current_v_id = ($is_edit) ? (int)$edit_data['vendor_id'] : 0;
$vendors = $conn->query("SELECT * FROM vendors WHERE category = 'Furniture' OR id = $current_v_id ORDER BY vendor_name");

if ($user_role === 'SuperAdmin') {
    $divisions = $conn->query("SELECT * FROM divisions ORDER BY division_name");
    $units_res = $conn->query("SELECT u.id, u.unit_name, u.unit_code, u.division_id FROM units u ORDER BY u.unit_name ASC");
} else {
    $units_res = $conn->query("SELECT id, unit_name, unit_code, division_id FROM units WHERE division_id = '$user_division' ORDER BY unit_name ASC");
}

$page_title = $is_edit ? "Edit Furniture Stock" : "Add Furniture Stock"; 
$page_icon  = $is_edit ? "bi-pencil-square" : "bi-box-seam";
ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
/* Enterprise UI Local Overrides & Scaled Font Sizing */
.stock-form-card {
    border: 1px solid var(--erp-border, #dce3e9) !important;
    background: #ffffff;
    border-radius: 6px !important;
}

.stock-card-header {
    background: #f8fafc;
    border-bottom: 1px solid var(--erp-border, #dce3e9);
    padding: 0.85rem 1.25rem;
}

.stock-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--erp-navy-dark, #102f4a);
}

.form-label-erp {
    font-size: 0.82rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.35rem;
}

.form-control-erp, .form-select-erp {
    font-size: 0.9rem !important;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    color: #1e293b;
    transition: all 0.15s ease-in-out;
}

.form-control-erp:focus, .form-select-erp:focus {
    border-color: var(--erp-navy, #173f63) !important;
    box-shadow: 0 0 0 3px rgba(23, 63, 99, 0.1) !important;
}

.input-group-text-erp {
    font-size: 0.9rem;
    background-color: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #64748b;
    font-weight: 600;
}

.btn-erp-primary {
    background-color: var(--erp-navy, #173f63);
    border-color: var(--erp-navy, #173f63);
    color: #ffffff;
    font-size: 0.9rem;
    font-weight: 600;
    padding: 0.6rem 2rem;
    border-radius: 4px;
    transition: all 0.15s ease;
}

.btn-erp-primary:hover {
    background-color: var(--erp-navy-dark, #102f4a);
    border-color: var(--erp-navy-dark, #102f4a);
    color: #ffffff;
}

.select2-container--bootstrap-5.select2-container--focus .select2-selection,
.select2-container--bootstrap-5.select2-container--open .select2-selection {
    border-color: var(--erp-navy, #173f63) !important;
    box-shadow: 0 0 0 3px rgba(23, 63, 99, 0.1) !important;
}

.select2-container--bootstrap-5 .select2-search__field:focus {
    border-color: var(--erp-navy, #173f63) !important;
    box-shadow: none !important;
}

.select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
    background-color: var(--erp-navy, #173f63) !important;
    color: #ffffff !important;
}

<?php if($is_edit): ?>
.stock-form-card { border-top: 4px solid #e33e4d !important; }
.edit-indicator { color: #e33e4d; font-weight: 800; font-size: 0.8rem; }
<?php endif; ?>
</style>

<div class="container-fluid mt-4 px-4">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
        <div>
            <h4 class="fw-bold mb-1 text-dark" style="font-size: 1.25rem;">
                <i class="bi <?= $is_edit ? 'bi-pencil-square text-danger' : 'bi-box-seam me-2' ?>" style="<?= !$is_edit ? 'color: var(--erp-navy, #173f63);' : '' ?>"></i>
                <?= $is_edit ? "Modify Furniture Stock Record" : "Add Furniture Stock Entry" ?>
            </h4>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">Record incoming furniture stock items, quantities, pricing, and facility assignment.</p>
        </div>
        <?php if($is_edit): ?>
            <span class="edit-indicator"><i class="bi bi-shield-exclamation me-1"></i> EDITING RECORD #<?= $edit_data['id'] ?></span>
        <?php endif; ?>
    </div>

    <form method="POST" id="furnitureForm" autocomplete="off">
        <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?? '' ?>">
        
        <!-- Section 1: Item Identity & Logistics -->
        <div class="card stock-form-card shadow-sm mb-4">
            <div class="stock-card-header">
                <span class="stock-card-title"><i class="bi bi-tags me-2"></i>1. Item Identity & Logistics</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label form-label-erp">Furniture Item Type <span class="text-danger">*</span></label>
                        <select name="furniture_item_id" class="form-select form-select-erp searchable-select" required>
                            <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Search or select item...</option>
                            <?php while($row = $items->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>" <?= ($is_edit && $edit_data['furniture_item_id'] == $row['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['item_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-erp">Invoice / Bill Number <span class="text-danger">*</span></label>
                        <input type="text" name="bill_no" class="form-control form-control-erp" value="<?= htmlspecialchars($edit_data['bill_no'] ?? '') ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Quantity & Pricing -->
        <div class="card stock-form-card shadow-sm mb-4">
            <div class="stock-card-header">
                <span class="stock-card-title"><i class="bi bi-cash-stack me-2"></i>2. Quantity & Pricing</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label form-label-erp">Total Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control form-control-erp" value="<?= htmlspecialchars($edit_data['total_qty'] ?? '') ?>" min="1" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-erp">Unit Price (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text input-group-text-erp">₹</span>
                            <input type="number" step="0.01" name="unit_price" class="form-control form-control-erp border-start-0" value="<?= htmlspecialchars($edit_data['unit_price'] ?? '') ?>" min="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-erp">Purchase Date <span class="text-danger">*</span></label>
                        <input type="date" name="bill_date" class="form-control form-control-erp" value="<?= htmlspecialchars($edit_data['bill_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Unit & Vendor Attribution -->
        <div class="card stock-form-card shadow-sm mb-4">
            <div class="stock-card-header">
                <span class="stock-card-title"><i class="bi bi-building me-2"></i>3. Unit & Vendor Attribution</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <?php if ($user_role === 'SuperAdmin'): ?>
                        <div class="col-md-6">
                            <label class="form-label form-label-erp">Filter by Division</label>
                            <select id="division_filter" class="form-select form-select-erp searchable-select">
                                <option value="">All Divisions</option>
                                <?php while($d = $divisions->fetch_assoc()): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="<?= ($user_role === 'SuperAdmin') ? 'col-md-6' : 'col-md-12' ?>">
                        <label class="form-label form-label-erp">Receiving Unit <span class="text-danger">*</span></label>
                        <select name="unit_id" id="unit_select" class="form-select form-select-erp searchable-select" required>
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

                    <div class="col-md-12">
                        <label class="form-label form-label-erp">Supplier / Vendor <span class="text-danger">*</span></label>
                        <select name="vendor_id" class="form-select form-select-erp searchable-select" required>
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
        </div>

        <!-- Submit Button Area -->
        <div class="d-flex justify-content-end gap-3 mb-5">
            <a href="view_furniture.php" class="btn btn-light px-4 text-muted discard-btn border" style="border-radius:4px; font-size: 0.9rem; font-weight: 600;">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <button type="submit" name="save_stock" class="btn btn-erp-primary shadow-sm">
                <i class="bi bi-check2-circle me-1.5"></i> <?= $is_edit ? "Update Changes" : "Save Stock Entry" ?>
            </button>
        </div>
    </form>
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
        if($('#furnitureForm').serialize().length > 50) { 
            e.preventDefault();
            const url = $(this).attr('href');
            Swal.fire({
                title: 'Unsaved Changes',
                text: "Discard current input?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--erp-navy, #173f63)',
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