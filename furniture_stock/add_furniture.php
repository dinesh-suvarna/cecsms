<?php
include "../config/db.php";
session_start();

// Check if user is logged in
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$message = "";
$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

// --- 1. EDIT FETCH LOGIC ---
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

// --- 2. INSERT / UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_stock'])) {
    $item_id = (int)$_POST['furniture_item_id'];
    $qty = (int)$_POST['quantity'];
    $bill_no = mysqli_real_escape_string($conn, $_POST['bill_no']);
    $bill_date = $_POST['bill_date'];
    $vendor_id = (int)$_POST['vendor_id'];
    $unit_id = (int)$_POST['unit_id'];
    $price = (float)$_POST['unit_price'];

    // Move the validation to the VERY TOP of the processing logic
    if ($price <= 0 || $qty <= 0) {
        $message = "error"; 
    } else {
        if (!empty($_POST['edit_id'])) {
            // UPDATE EXISTING RECORD
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
            // INSERT NEW RECORD
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

// --- 3. DATA FETCHING FOR SELECTS ---
$items = $conn->query("SELECT * FROM furniture_items ORDER BY item_name");
$vendors = $conn->query("SELECT * FROM vendors ORDER BY vendor_name");
if ($user_role === 'SuperAdmin') {
    $units = $conn->query("SELECT id, unit_name FROM units ORDER BY unit_name");
} else {
    $units = $conn->query("SELECT id, unit_name FROM units WHERE division_id = '$user_division' ORDER BY unit_name");
}

$page_title = $is_edit ? "Edit Furniture Stock" : "Add Furniture Stock"; 
ob_start();
?>

<div class="container-fluid py-4">
    <div class="form-card-container"> 
        <div class="card main-card">
            <div class="card-body p-4 p-md-5">
                
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3 text-primary">
                            <i class="bi <?= $is_edit ? 'bi-pencil-square' : 'bi-plus-circle' ?> fs-4"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0"><?= $is_edit ? "Modify Item" : "Stock Entry" ?></h4>
                            <p class="text-muted mb-0 small"><?= $is_edit ? "Modify existing inventory details." : "Record new furniture inventory arrivals." ?></p>
                        </div>
                    </div>
                    <?php if($is_edit): ?>
                        <span class="edit-badge">Edit Mode</span>
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?? '' ?>">

                    <div class="row g-4">
        <div class="col-md-6 col-lg-6">
            <label class="form-label">Furniture Item Type</label>
            <select name="furniture_item_id" class="form-select" required>
                <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Select item...</option>
                <?php while($row = $items->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= ($is_edit && $edit_data['furniture_item_id'] == $row['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['item_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-6 col-lg-6">
            <label class="form-label">Bill / Invoice Number</label>
            <input type="text" name="bill_no" class="form-control" 
                   value="<?= $edit_data['bill_no'] ?? '' ?>" 
                   pattern=".*\S.*" title="Bill number cannot be empty or just spaces"
                   required>
        </div>

        <div class="col-md-3 col-lg-3">
            <label class="form-label">Total Quantity</label>
            <div class="input-group">
                <span class="input-group-text">#</span>
                <input type="number" name="quantity" class="form-control" 
                       value="<?= $edit_data['total_qty'] ?? '' ?>" 
                       min="1" step="1" 
                       oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                       required>
            </div>
        </div>

        <div class="col-md-3 col-lg-3">
            <label class="form-label">Unit Price</label>
            <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" name="unit_price" class="form-control" 
                       value="<?= $edit_data['unit_price'] ?? '' ?>" 
                       step="0.01" min="0.01" 
                       oninput="if(this.value < 0) this.value = 0;"
                       required>
            </div>
        </div>

        <div class="col-md-6 col-lg-6">
            <label class="form-label">Purchase Date</label>
            <input type="date" name="bill_date" class="form-control" 
                   value="<?= $edit_data['bill_date'] ?? date('Y-m-d') ?>" 
                   max="<?= date('Y-m-d') ?>"
                   required>
        </div>
        
        

                        <div class="col-md-6">
                            <label class="form-label">Supplier / Vendor</label>
                            <select name="vendor_id" class="form-select" required>
                                <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Select vendor...</option>
                                <?php while($v = $vendors->fetch_assoc()): ?>
                                    <option value="<?= $v['id'] ?>" <?= ($is_edit && $edit_data['vendor_id'] == $v['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['vendor_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Receiving Unit</label>
                            <select name="unit_id" class="form-select" required>
                                <option value="" disabled <?= !$is_edit ? 'selected' : '' ?>>Select unit...</option>
                                <?php while($u = $units->fetch_assoc()): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($is_edit && $edit_data['unit_id'] == $u['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['unit_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <hr class="my-5 opacity-25">

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

<style>
    .form-card-container {
        width: 100%;
    }

    .card.main-card {
        border-radius: 20px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03) !important;
        width: 100%; 
    }

    .form-label { 
        font-size: 0.72rem; 
        font-weight: 700; 
        color: #6c757d; 
        text-transform: uppercase; 
        margin-bottom: 6px; 
        letter-spacing: 0.3px; 
    }

    .form-control, .form-select { 
        border-radius: 10px; 
        border: 1px solid #eef0f2; 
        padding: 10px 14px; 
        font-size: 0.95rem; 
    }

    
    .btn-pill { 
        padding: 12px 35px; 
        border-radius: 50px; 
        font-weight: 700; 
        min-width: 180px; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        border: none;
        transition: all 0.3s ease; 
        color: white !important;  
    }

    
    .btn-update { 
        background: #0d6efd; 
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2); 
    }
    .btn-update:hover { 
        background: #198754 !important; 
        box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
        transform: translateY(-1px);
    }

    
    .btn-discard { 
        background: #e33e4d; 
    }
    .btn-discard:hover { 
        background: #b91d2b !important; 
        box-shadow: 0 4px 15px rgba(227, 62, 77, 0.3);
        transform: translateY(-1px);
    }

    .edit-badge { 
        background: #0d6efd; 
        color: white; 
        font-size: 15px; 
        font-weight: 800; 
        padding: 10px 20px; 
        border-radius: 8px; 
        text-transform: uppercase; 
        letter-spacing: 0.5px;
    }

    .action-area { margin-top: 30px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // 1. Success Message (From Session)
    <?php if (isset($_SESSION['swal_msg'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['swal_type'] ?? 'success' ?>',
            title: '<?= $_SESSION['swal_type'] == 'success' ? 'Great!' : 'Notice' ?>',
            text: '<?= $_SESSION['swal_msg'] ?>',
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['swal_msg'], $_SESSION['swal_type']); ?>
    <?php endif; ?>

    // 2. Error Message (From Local Variable)
    <?php if($message == "error"): ?>
        Swal.fire({ 
            icon: 'error', 
            title: 'Oops...', 
            text: 'Could not process stock entry. Please check your data.' 
        });
    <?php endif; ?>

    // 3. Confirm Discard/Cancel
    document.querySelector('.btn-discard').addEventListener('click', function(e) {
        e.preventDefault();
        const url = this.getAttribute('href');
        
        Swal.fire({
            title: 'Are you sure?',
            text: "Any unsaved changes will be lost!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e33e4d',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, discard it!',
            cancelButtonText: 'Stay here'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    });

    // 4. Client-side Validation before Submit
    document.querySelector('form').addEventListener('submit', function(e) {
        const qty = parseInt(document.querySelector('input[name="quantity"]').value);
        const price = parseFloat(document.querySelector('input[name="unit_price"]').value);

        if (qty <= 0 || price <= 0) {
            e.preventDefault(); 
            Swal.fire({
                icon: 'error',
                title: 'Invalid Input',
                text: 'Quantity and Unit Price must be greater than zero.'
            });
        }
    });
    
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>