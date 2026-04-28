<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Asset Requests";
$page_icon  = "bi-arrow-repeat";


/* ================= SUPERADMIN APPROVAL ================= */
if(isset($_POST['approve_return'])){

$id = (int)$_POST['id'];

$conn->query("
UPDATE division_assets 
SET status='returned'
WHERE id=$id
");

header("Location: ".$_SERVER['PHP_SELF']);
exit;

}

if(isset($_POST['approve_repair'])){

$id = (int)$_POST['id'];

$conn->query("
UPDATE division_assets 
SET status='repair'
WHERE id=$id
");

header("Location: ".$_SERVER['PHP_SELF']);
exit;

}

if(isset($_POST['approve_dispose'])){

$id = (int)$_POST['id'];

$conn->query("
UPDATE division_assets 
SET status='disposed'
WHERE id=$id
");

header("Location: ".$_SERVER['PHP_SELF']);
exit;

}

if(isset($_POST['reject_request'])){

$id = (int)$_POST['id'];

$conn->query("
UPDATE division_assets 
SET status='assigned'
WHERE id=$id
");

header("Location: ".$_SERVER['PHP_SELF']);
exit;

}


function fetchAssets($conn,$status){

$query = "
SELECT 
    da.id,
    da.division_asset_id,
    im.item_name,
    sd.serial_number,
    d.division_name,
    u.unit_name,
    da.status,
    da.assigned_at
FROM division_assets da
JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
JOIN dispatch_master dm ON dm.id = dd.dispatch_id
JOIN stock_details sd ON sd.id = da.stock_detail_id
JOIN items_master im ON im.id = sd.stock_item_id
JOIN divisions d ON d.id = dm.division_id
LEFT JOIN units u ON u.id = dm.unit_id
WHERE da.status = '$status'
ORDER BY da.assigned_at DESC
";

return $conn->query($query);

}

$return_requests = fetchAssets($conn,'return_requested');
$returned = fetchAssets($conn,'returned');
$repair_requests  = fetchAssets($conn,'repair_requested');
$dispose_requests = fetchAssets($conn,'dispose_requested');
$disposed_assets  = fetchAssets($conn,'disposed');

ob_start();
?>

<div class="container-fluid mt-4">

<div class="card shadow-sm border-0 rounded-4">

<div class="card-body">

<h5 class="mb-4">
<i class="bi <?= $page_icon ?>"></i> Asset Requests
</h5>


<!-- Tabs -->

<ul class="nav nav-tabs mb-3">

<li class="nav-item">
<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#return_requests">
Return Requests
</button>
</li>

<li class="nav-item">
<button class="nav-link" data-bs-toggle="tab" data-bs-target="#returned">
Returned
</button>
</li>

<li class="nav-item">
<button class="nav-link" data-bs-toggle="tab" data-bs-target="#repair">
Repair Requests
</button>
</li>

<li class="nav-item">
<button class="nav-link" data-bs-toggle="tab" data-bs-target="#dispose">
Dispose Requests
</button>
</li>

<li class="nav-item">
<button class="nav-link" data-bs-toggle="tab" data-bs-target="#disposed">
Disposed Assets
</button>
</li>

</ul>


<div class="tab-content">

<!-- RETURN REQUESTS -->
<div class="tab-pane fade show active" id="return_requests">
<?php renderTable($return_requests); ?>
</div>

<!-- RETURNED -->
<div class="tab-pane fade" id="returned">
<?php renderTable($returned); ?>
</div>

<!-- REPAIR REQUESTS -->
<div class="tab-pane fade" id="repair">
<?php renderTable($repair_requests); ?>
</div>

<!-- DISPOSE REQUESTS -->
<div class="tab-pane fade" id="dispose">
<?php renderTable($dispose_requests); ?>
</div>

<!-- FINAL DISPOSED -->
<div class="tab-pane fade" id="disposed">
<?php renderTable($disposed_assets); ?>
</div>

</div>

</div>

</div>

</div>


<?php
$content = ob_get_clean();
include "../stock/stocklayout.php";
?>

<?php

function renderTable($result){


?>

<div class="table-responsive">

<table class="table table-sm table-hover">

<thead>

<tr>
<th>#</th>
<th>Item</th>
<th>Serial</th>
<th>Division</th>
<th>Unit</th>
<th>Asset ID</th>
<th>Status</th>
<th>Action</th>
</tr>

</thead>

<tbody>

<?php

$sl=1;

if(!$result || $result->num_rows==0){
echo "<tr><td colspan='8' class='text-center text-muted py-3'>No records</td></tr>";
return;
}

while($row=$result->fetch_assoc()){

?>

<tr>

<td><?= $sl++ ?></td>

<td><?= htmlspecialchars($row['item_name']) ?></td>

<td><?= htmlspecialchars($row['serial_number']) ?></td>

<td><?= htmlspecialchars($row['division_name']) ?></td>

<td><?= htmlspecialchars($row['unit_name']) ?></td>

<td><?= htmlspecialchars($row['division_asset_id']) ?></td>

<td>

<?php

$status = $row['status'];

if($status=="returned"){
echo "<span class='badge bg-warning text-dark'>Returned</span>";
}
elseif($status=="repair_requested"){
echo "<span class='badge bg-info'>Repair Request</span>";
}
elseif($status=="dispose_requested"){
echo "<span class='badge bg-danger'>Dispose Request</span>";
}
elseif($status=="repair"){
echo "<span class='badge bg-success'>Repair Approved</span>";
}
elseif($status=="disposed"){
echo "<span class='badge bg-dark'>Disposed</span>";
}
elseif($status=="return_requested"){
echo "<span class='badge bg-warning text-dark'>Return Request</span>";
}

?>

</td>

<td>

<form method="POST">

<input type="hidden" name="id" value="<?= $row['id'] ?>">

<?php if($row['status']=="return_requested"){ ?>

<button 
name="approve_return"
class="btn btn-sm btn-success">

Approve Return

</button>

<button 
name="reject_request"
class="btn btn-sm btn-secondary">

Reject

</button>

<?php } ?>

<?php if($row['status']=="repair_requested"){ ?>

<button 
name="approve_repair"
class="btn btn-sm btn-success">

Approve Repair

</button>

<button 
name="reject_request"
class="btn btn-sm btn-secondary">

Reject

</button>

<?php } ?>

<?php if($row['status']=="dispose_requested"){ ?>

<button 
name="approve_dispose"
class="btn btn-sm btn-danger">

Approve Dispose

</button>

<button 
name="reject_request"
class="btn btn-sm btn-secondary">

Reject

</button>

<?php } ?>

</form>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

<?php } ?>