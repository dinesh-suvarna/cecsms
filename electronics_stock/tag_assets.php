<?php
include "../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin','Admin'])) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

/* ================= FETCH PENDING ================= */
$query = "
SELECT s.id, s.total_qty, s.bill_no, i.item_name, u.division_id,
(SELECT COUNT(ea.id) FROM electronics_assets ea WHERE ea.stock_id = s.id) as assigned_count
FROM electronics_stock s
JOIN electronics_items i ON s.electronics_item_id = i.id
JOIN units u ON s.unit_id = u.id
WHERE 1=1";

if ($user_role !== 'SuperAdmin') {
    $query .= " AND u.division_id='$user_division'";
}

$query .= " GROUP BY s.id HAVING assigned_count < s.total_qty ORDER BY s.id ASC";

$res = $conn->query($query);

$pending_list = [];
while($row=$res->fetch_assoc()){
    $pending_list[]=$row;
}

/* ================= ACTIVE STOCK ================= */
$stock_id = isset($_GET['stock_id']) ? (int)$_GET['stock_id'] : 0;

if ($stock_id===0 && !empty($pending_list)) {
    $stock_id = $pending_list[0]['id'];
}

$stock = null;
$current_assets = 0;

if ($stock_id>0) {
    $q = $conn->query("SELECT s.*, i.item_name FROM electronics_stock s 
                       JOIN electronics_items i ON s.electronics_item_id=i.id 
                       WHERE s.id=$stock_id");

    $stock = $q->fetch_assoc();

    $c = $conn->query("SELECT COUNT(id) as cnt FROM electronics_assets WHERE stock_id=$stock_id");
    $current_assets = $c->fetch_assoc()['cnt'];
}

/* ================= GENERATE TAGS ================= */
$error_message="";

if ($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['generate_tags']) && $stock) {

    $qty = (int)$stock['total_qty'];

    if ($current_assets < $qty) {

        $prefix = strtoupper(mysqli_real_escape_string($conn,$_POST['prefix']));
        $start_no = (int)$_POST['start_no'];
        $remaining = $qty - $current_assets;

        $duplicates=[];

        for ($i=0;$i<$remaining;$i++) {

            $num = str_pad($start_no+$i,2,'0',STR_PAD_LEFT);
            $tag = $prefix.$num;

            $check = $conn->query("SELECT id FROM electronics_assets WHERE asset_tag='$tag'");

            if ($check->num_rows>0) {
                $duplicates[]=$tag;
            } else {
                $conn->query("INSERT INTO electronics_assets (stock_id,asset_tag) VALUES ($stock_id,'$tag')");
            }
        }

        if (!empty($duplicates)) {
            $error_message = "Already used: ".implode(", ",$duplicates);
        } else {
            header("Location: tag_assets.php?msg=success");
            exit();
        }
    }
}

$page_title="Electronics Asset Tagging";
ob_start();
?>

<div class="container-fluid py-4 px-4">
<div class="row g-4">

<!-- LEFT QUEUE -->
<div class="col-lg-4">
<div class="card border-0 shadow-sm rounded-4 h-100">

<div class="card-header bg-white border-0 py-3">
<h6 class="fw-bold m-0">
<i class="bi bi-cpu me-2 text-primary"></i>Pending Queue
</h6>
</div>

<div class="card-body p-0">
<div class="list-group list-group-flush">

<?php if(empty($pending_list)): ?>
<div class="p-4 text-center text-muted small">
<i class="bi bi-check2-all d-block fs-2 mb-2"></i>
No pending electronics.
</div>
<?php else: ?>

<?php foreach($pending_list as $p): ?>
<a href="tag_assets.php?stock_id=<?= $p['id'] ?>"
class="list-group-item list-group-item-action p-3 border-0 <?= ($stock_id==$p['id'])?'bg-primary-subtle border-start border-primary border-4':'' ?>">

<div class="d-flex justify-content-between">

<div class="text-truncate">
<div class="fw-bold small text-uppercase"><?= htmlspecialchars($p['item_name']) ?></div>
<div class="text-muted extra-small">Bill: #<?= $p['bill_no'] ?></div>
</div>

<span class="badge rounded-pill bg-white text-primary border">
<?= $p['total_qty'] - $p['assigned_count'] ?> Left
</span>

</div>
</a>
<?php endforeach; ?>

<?php endif; ?>

</div>
</div>
</div>
</div>

<!-- RIGHT PANEL -->
<div class="col-lg-8">
<div class="card border-0 shadow-sm rounded-4 min-vh-50">
<div class="card-body p-5">

<?php if(!$stock || $current_assets >= (int)$stock['total_qty']): ?>

<!-- EMPTY -->
<div class="text-center py-5">
<div class="mb-4">
<div class="bg-success bg-opacity-10 d-inline-flex p-4 rounded-circle">
<i class="bi bi-check-lg text-success display-4"></i>
</div>
</div>

<h4 class="fw-bold">Queue Cleared</h4>
<p class="text-muted">All electronics tagged.</p>

<a href="view_electronics_assets.php" class="btn btn-outline-dark rounded-pill px-4">
Go to Registry
</a>
</div>

<?php else: ?>

<!-- HEADER -->
<div class="d-flex align-items-center mb-4">
<i class="bi bi-upc-scan text-primary fs-3 me-3"></i>
<div>
<h5 class="fw-bold m-0">Assign Asset ID</h5>
<p class="text-muted small m-0">Finalize device tagging</p>
</div>
</div>

<!-- INFO -->
<div class="alert bg-light border-0 rounded-4 p-4 mb-4">
<div class="row align-items-center">

<div class="col-sm-8">
<div class="small text-muted text-uppercase fw-bold">Active Device</div>
<h5 class="fw-bold text-primary"><?= htmlspecialchars($stock['item_name']) ?></h5>
<div class="small">Bill: <strong><?= $stock['bill_no'] ?></strong></div>
</div>

<div class="col-sm-4 text-end">
<div class="display-6 fw-bold"><?= $stock['total_qty'] - $current_assets ?></div>
<div class="small text-muted">Remaining</div>
</div>

</div>
</div>

<!-- FORM -->
<form method="POST">
<div class="row g-4">

<div class="col-md-8">
<label class="form-label">Prefix</label>
<input type="text" id="prefixInput" name="prefix"
class="form-control form-control-lg bg-light fw-bold"
placeholder="EX: CEC/LAB/PC/"
required>

<div class="mt-2">
<span id="prefixPreview" class="badge bg-primary-subtle text-primary" style="display:none;"></span>
</div>
</div>

<div class="col-md-4">
<label class="form-label">Start No</label>
<input type="number" name="start_no" class="form-control form-control-lg" value="1">
</div>

</div>

<button type="submit" name="generate_tags"
class="btn btn-primary btn-lg rounded-pill mt-5 px-5">
<i class="bi bi-cpu-fill me-2"></i> Generate
</button>
</form>

<?php endif; ?>

</div>
</div>
</div>

</div>
</div>

<style>
.extra-small{font-size:0.75rem;}
.min-vh-50{min-height:60vh;}

.list-group-item{transition:0.2s;}
.list-group-item:hover{background:#f8fafc;}

.bg-primary-subtle{background:#eef2ff;}

</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

/* LIVE PREVIEW */
const input=document.getElementById('prefixInput');

if(input){
input.addEventListener('input',function(){
this.value=this.value.toUpperCase();

let preview=document.getElementById('prefixPreview');

if(this.value.length>0){
preview.style.display='inline-block';
preview.textContent=this.value+"01";
}else{
preview.style.display='none';
}
});
}

/* SUCCESS */
if(new URLSearchParams(window.location.search).get('msg')==='success'){
Swal.fire({icon:'success',title:'Done',text:'Tags generated',timer:2000,showConfirmButton:false});
}

/* ERROR */
<?php if(!empty($error_message)): ?>
Swal.fire({icon:'error',title:'Duplicate',text:'<?= addslashes($error_message) ?>'});
<?php endif; ?>

</script>

<?php
$content = ob_get_clean();
include "electronicslayout.php";
?>