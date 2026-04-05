<?php
include "../config/db.php";
session_start();

/* AUTH */
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin','Admin'])) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

/* ================= FETCH ================= */
$query = "
SELECT 
    ea.id as asset_db_id,
    ea.asset_tag,
    ea.status,
    ea.last_verified_date,
    s.bill_no,
    s.bill_date,
    i.item_name,
    v.vendor_name,
    u.unit_name,
    u.unit_code
FROM electronics_assets ea
JOIN electronics_stock s ON ea.stock_id = s.id
JOIN electronics_items i ON s.electronics_item_id = i.id
JOIN vendors v ON s.vendor_id = v.id
JOIN units u ON s.unit_id = u.id";

if ($user_role !== 'SuperAdmin') {
    $query .= " WHERE u.division_id='$user_division'";
}

$query .= " ORDER BY u.unit_code, i.item_name, s.bill_no, ea.asset_tag";

$res = $conn->query($query);

/* ================= GROUP ================= */
$registry = [];

while($row=$res->fetch_assoc()){
    $unit_key = strtoupper($row['unit_code'])." - ".$row['unit_name'];
    $item_key = $row['item_name'];
    $bill_key = $row['bill_no']." | ".$row['vendor_name'];

    $registry[$unit_key][$item_key][$bill_key][] = $row;
}

$page_title="Electronics Asset Registry";
ob_start();
?>

<div class="container-fluid py-4 px-4">

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
<div>
<h3 class="fw-bold mb-0">Asset ID Registry</h3>
<p class="text-muted mb-0">Electronic asset tracking by Unit</p>
</div>

<a href="tag_assets.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
<i class="bi bi-upc-scan me-2"></i>View Queue
</a>
</div>

<?php if(empty($registry)): ?>

<div class="card border-0 shadow-sm rounded-4 py-5 text-center">
<i class="bi bi-inbox fs-1 opacity-25"></i>
<p class="text-muted mt-3">No assets registered yet.</p>
</div>

<?php else: ?>

<div class="accordion border-0 shadow-sm rounded-4 overflow-hidden">

<?php $u=0; foreach($registry as $unit=>$items): $u++; ?>
<?php $uid="unit".$u; ?>

<div class="accordion-item border-0 border-bottom">

<h2 class="accordion-header">
<button class="accordion-button collapsed fw-bold fs-5"
data-bs-toggle="collapse"
data-bs-target="#<?= $uid ?>">

<i class="bi bi-building text-primary me-3"></i>
<?= htmlspecialchars($unit) ?>
</button>
</h2>

<div id="<?= $uid ?>" class="accordion-collapse collapse">

<div class="accordion-body p-0">
<div class="list-group list-group-flush">

<?php $i=0; foreach($items as $item=>$bills): $i++; ?>
<?php $iid="item".$u."_".$i; ?>

<?php 
$all_ids=[];
foreach($bills as $b){
foreach($b as $a){ $all_ids[]=$a['asset_db_id']; }
}
?>

<div class="list-group-item p-0 border-0">

<!-- ITEM HEADER -->
<div class="d-flex justify-content-between px-4 py-3 bg-light border-bottom">

<div class="fw-bold cursor-pointer"
data-bs-toggle="collapse"
data-bs-target="#<?= $iid ?>">

<i class="bi bi-chevron-right me-2"></i>
<?= htmlspecialchars($item) ?>
<span class="badge bg-dark ms-2"><?= count($all_ids) ?></span>
</div>

<button class="btn btn-sm btn-success rounded-pill"
onclick='bulkVerify(<?= json_encode($all_ids) ?>)'>
<i class="bi bi-check-all me-1"></i>Verify All
</button>

</div>

<!-- TABLE -->
<div id="<?= $iid ?>" class="collapse">

<div class="table-responsive px-3 py-2">
<table class="table table-hover align-middle">

<tbody>

<?php foreach($bills as $bill=>$assets): 
$parts = explode(" | ",$bill);
?>

<tr class="table-secondary-subtle">
<td colspan="4" class="ps-4">

<div class="d-flex gap-4">
<span><strong>Vendor:</strong> <?= $parts[1] ?></span>
<span><strong>Bill:</strong> #<?= $parts[0] ?></span>
<span><?= date('d M Y',strtotime($assets[0]['bill_date'])) ?></span>
</div>

</td>
</tr>

<tr class="small fw-bold text-muted">
<td class="ps-5">TAG</td>
<td class="text-center">STATUS</td>
<td class="text-center">VERIFIED</td>
<td class="text-end">ACTION</td>
</tr>

<?php foreach($assets as $a): ?>

<tr>

<td class="ps-5">

<span id="tag-text-<?= $a['asset_db_id'] ?>" class="asset-tag-text">
<?= $a['asset_tag'] ?>
</span>

<div id="edit-<?= $a['asset_db_id'] ?>" class="d-none">
<input id="input-<?= $a['asset_db_id'] ?>" class="form-control form-control-sm"
value="<?= $a['asset_tag'] ?>">
</div>

</td>

<td class="text-center">
<span class="badge bg-success"><?= $a['status'] ?></span>
</td>

<td class="text-center" id="verify-<?= $a['asset_db_id'] ?>">
<?= $a['last_verified_date'] ?? '—' ?>
</td>

<td class="text-end">

<button class="btn btn-outline-primary btn-sm"
onclick="editTag(<?= $a['asset_db_id'] ?>)">Edit</button>

<button class="btn btn-success btn-sm"
onclick="verify(<?= $a['asset_db_id'] ?>)">Verify</button>

</td>

</tr>

<?php endforeach; ?>

<?php endforeach; ?>

</tbody>
</table>
</div>

</div>

</div>

<?php endforeach; ?>

</div>
</div>
</div>
</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

function verify(id){
fetch('update_asset.php',{
method:'POST',
headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:`action=verify&id=${id}`
})
.then(r=>r.json())
.then(d=>{
if(d.success){
document.getElementById('verify-'+id).innerText=d.new_date;
}
});
}

function bulkVerify(ids){
ids.forEach(id=>verify(id));
}

</script>

<style>
.asset-tag-text{
font-family:monospace;
font-weight:700;
color:#2563eb;
}
.cursor-pointer{cursor:pointer;}
.table-secondary-subtle{background:#f1f5f9;}
</style>

<?php
$content=ob_get_clean();
include "electronicslayout.php";
?>