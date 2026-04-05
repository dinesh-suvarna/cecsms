<?php
include "../config/db.php";
session_start();

/* AUTH */
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin','Admin'])) {
    header("Location: ../index.php");
    exit();
}

$message = "";
$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

/* ================= EDIT FETCH ================= */
$edit_data = null;
$is_edit = false;

if (isset($_POST['trigger_edit'])) {
    $id = (int)$_POST['trigger_edit'];
    $res = $conn->query("SELECT * FROM electronics_stock WHERE id=$id");
    $edit_data = $res->fetch_assoc();
    if ($edit_data) $is_edit = true;
}

/* ================= INSERT / UPDATE ================= */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_stock'])) {

    $item_id = (int)$_POST['electronics_item_id'];
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

            $sql = "UPDATE electronics_stock SET
                electronics_item_id='$item_id',
                total_qty='$qty',
                available_qty='$qty',
                bill_no='$bill_no',
                bill_date='$bill_date',
                vendor_id='$vendor_id',
                unit_id='$unit_id',
                unit_price='$price',
                WHERE id=$edit_id";

            if ($conn->query($sql)) {
                $_SESSION['swal_msg'] = "Stock updated successfully!";
                $_SESSION['swal_type'] = "success";
                header("Location: view_electronics.php");
                exit();
            } else $message="error";

        } else {

            $sql = "INSERT INTO electronics_stock
            (electronics_item_id,total_qty,available_qty,bill_no,bill_date,vendor_id,unit_id,unit_price)
            VALUES
            ('$item_id','$qty','$qty','$bill_no','$bill_date','$vendor_id','$unit_id','$price')";

            if ($conn->query($sql)) {
                $_SESSION['swal_msg']="Stock added successfully!";
                $_SESSION['swal_type']="success";
                header("Location: add_electronics.php");
                exit();
            } else $message="error";
        }
    }
}

/* ================= FETCH DATA ================= */
$items = $conn->query("SELECT * FROM electronics_items ORDER BY item_name");
$vendors = $conn->query("SELECT * FROM vendors ORDER BY vendor_name");

if ($user_role === 'SuperAdmin') {
    $units = $conn->query("SELECT id, unit_name FROM units ORDER BY unit_name");
} else {
    $units = $conn->query("SELECT id, unit_name FROM units WHERE division_id='$user_division'");
}

$page_title = $is_edit ? "Edit Electronics Stock" : "Add Electronics Stock";
ob_start();
?>

<div class="container-fluid py-4">
<div class="form-card-container">

<div class="card main-card">
<div class="card-body p-4 p-md-5">

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-5">
<div class="d-flex align-items-center">
<div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3 text-primary">
<i class="bi <?= $is_edit ? 'bi-pencil-square' : 'bi-cpu' ?> fs-4"></i>
</div>

<div>
<h4 class="fw-bold mb-0"><?= $is_edit ? "Modify Device" : "Stock Entry" ?></h4>
<p class="text-muted mb-0 small">
<?= $is_edit ? "Modify existing device details." : "Record new electronic inventory." ?>
</p>
</div>
</div>

<?php if($is_edit): ?>
<span class="edit-badge">Edit Mode</span>
<?php endif; ?>
</div>

<form method="POST">
<input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?? '' ?>">

<div class="row g-4">

<!-- ITEM -->
<div class="col-md-6">
<label class="form-label">Device Type</label>
<select name="electronics_item_id" class="form-select" required>
<option disabled <?= !$is_edit?'selected':'' ?>>Select device...</option>
<?php while($row=$items->fetch_assoc()): ?>
<option value="<?= $row['id'] ?>"
<?= ($is_edit && $edit_data['electronics_item_id']==$row['id'])?'selected':'' ?>>
<?= htmlspecialchars($row['item_name']) ?>
</option>
<?php endwhile; ?>
</select>
</div>

<!-- BILL -->
<div class="col-md-6">
<label class="form-label">Invoice Number</label>
<input type="text" name="bill_no" class="form-control"
value="<?= $edit_data['bill_no'] ?? '' ?>" required>
</div>

<!-- QTY -->
<div class="col-md-3">
<label class="form-label">Quantity</label>
<div class="input-group">
<span class="input-group-text">#</span>
<input type="number" name="quantity" class="form-control"
value="<?= $edit_data['total_qty'] ?? '' ?>" min="1" required>
</div>
</div>

<!-- PRICE -->
<div class="col-md-3">
<label class="form-label">Unit Price</label>
<div class="input-group">
<span class="input-group-text">₹</span>
<input type="number" name="unit_price" class="form-control"
value="<?= $edit_data['unit_price'] ?? '' ?>" step="0.01" min="0.01" required>
</div>
</div>

<!-- DATE -->
<div class="col-md-6">
<label class="form-label">Purchase Date</label>
<input type="date" name="bill_date" class="form-control"
value="<?= $edit_data['bill_date'] ?? date('Y-m-d') ?>"
max="<?= date('Y-m-d') ?>" required>
</div>

<!-- VENDOR -->
<div class="col-md-6">
<label class="form-label">Supplier</label>
<select name="vendor_id" class="form-select" required>
<option disabled>Select vendor...</option>
<?php while($v=$vendors->fetch_assoc()): ?>
<option value="<?= $v['id'] ?>"
<?= ($is_edit && $edit_data['vendor_id']==$v['id'])?'selected':'' ?>>
<?= htmlspecialchars($v['vendor_name']) ?>
</option>
<?php endwhile; ?>
</select>
</div>

<!-- UNIT -->
<div class="col-md-6">
<label class="form-label">Receiving Unit</label>
<select name="unit_id" class="form-select" required>
<option disabled>Select unit...</option>
<?php while($u=$units->fetch_assoc()): ?>
<option value="<?= $u['id'] ?>"
<?= ($is_edit && $edit_data['unit_id']==$u['id'])?'selected':'' ?>>
<?= htmlspecialchars($u['unit_name']) ?>
</option>
<?php endwhile; ?>
</select>
</div>

</div>

<!-- ACTION -->
<div class="d-flex justify-content-center gap-3 action-area">
<button type="submit" name="save_stock" class="btn btn-pill btn-update">
<i class="bi <?= $is_edit?'bi-arrow-repeat':'bi-check-lg' ?> me-2"></i>
<?= $is_edit?"Update Device":"Save Stock" ?>
</button>

<a href="view_electronics.php" class="btn btn-pill btn-discard text-decoration-none">
<?= $is_edit?"Discard Changes":"Cancel" ?>
</a>
</div>

</form>
</div>
</div>
</div>
</div>

<style>
.form-card-container { width:100%; }

.card.main-card{
border-radius:20px;
border:none;
box-shadow:0 10px 30px rgba(0,0,0,0.03);
}

.form-label{
font-size:0.72rem;
font-weight:700;
color:#6c757d;
text-transform:uppercase;
margin-bottom:6px;
}

.form-control,.form-select{
border-radius:10px;
border:1px solid #eef0f2;
padding:10px 14px;
font-size:0.95rem;
}

.btn-pill{
padding:12px 35px;
border-radius:50px;
font-weight:700;
min-width:180px;
display:flex;
justify-content:center;
align-items:center;
border:none;
color:#fff;
}

.btn-update{
background:#2563eb;
}

.btn-update:hover{
background:#16a34a;
transform:translateY(-1px);
}

.btn-discard{
background:#e33e4d;
}

.btn-discard:hover{
background:#b91d2b;
transform:translateY(-1px);
}

.edit-badge{
background:#2563eb;
color:#fff;
font-weight:800;
padding:10px 20px;
border-radius:8px;
}

.action-area{ margin-top:30px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

<?php if(isset($_SESSION['swal_msg'])): ?>
Swal.fire({
icon:'<?= $_SESSION['swal_type'] ?>',
title:'Success',
text:'<?= $_SESSION['swal_msg'] ?>',
timer:3000,
showConfirmButton:false
});
<?php unset($_SESSION['swal_msg'],$_SESSION['swal_type']); endif; ?>

<?php if($message=="error"): ?>
Swal.fire({icon:'error',title:'Error',text:'Invalid data'});
<?php endif; ?>

document.querySelector('.btn-discard').addEventListener('click',function(e){
e.preventDefault();
let url=this.href;

Swal.fire({
title:'Discard changes?',
icon:'warning',
showCancelButton:true,
confirmButtonText:'Yes'
}).then(res=>{
if(res.isConfirmed) window.location=url;
});
});

</script>

<?php
$content = ob_get_clean();
include "electronicslayout.php";
?>