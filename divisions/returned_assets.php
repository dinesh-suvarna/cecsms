<?php
include "../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Returned Assets";
$page_icon  = "bi-arrow-return-left";

$role = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;

/* ================= FETCH RETURNED ASSETS ================= */

$query = "
SELECT 
    da.id,
    da.division_asset_id,
    im.item_name,
    sd.serial_number,
    d.division_name,
    da.status,
    da.assigned_at
FROM division_assets da
JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
JOIN dispatch_master dm ON dm.id = dd.dispatch_id
JOIN stock_details sd ON sd.id = da.stock_detail_id
JOIN items_master im ON im.id = sd.stock_item_id
JOIN divisions d ON d.id = dm.division_id
WHERE da.status = 'returned'
";

if ($role !== 'SuperAdmin') {
    $query .= " AND dm.division_id = $division_id";
}

$query .= " ORDER BY da.assigned_at DESC";

$result = $conn->query($query);

ob_start();
?>

<div class="container-fluid mt-4">
<div class="card shadow-sm border-0 rounded-4">
<div class="card-body">

<h5 class="fw-semibold mb-3">
<i class="bi <?= $page_icon ?> me-2 text-danger"></i>
Returned Assets
</h5>

<div class="table-responsive">

<table class="table table-sm table-hover align-middle">

<thead>
<tr>
<th>Sl.No</th>
<th>Asset ID</th>
<th>Item</th>
<th>Serial / Unit</th>
<th>Division</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php
$sl = 1;

if($result->num_rows == 0){
echo "<tr><td colspan='7' class='text-center text-muted'>No returned assets found</td></tr>";
}

while($row = $result->fetch_assoc()){
?>

<tr>

<td><?= $sl++ ?></td>

<td><?= htmlspecialchars($row['division_asset_id']) ?></td>

<td><?= htmlspecialchars($row['item_name']) ?></td>

<td>
<?= $row['serial_number'] 
? htmlspecialchars($row['serial_number']) 
: "-" ?>
</td>

<td><?= htmlspecialchars($row['division_name']) ?></td>

<td>
<span class="badge bg-warning text-dark">
Returned
</span>
</td>

<td>

<a href="asset_action.php?id=<?= $row['id'] ?>&action=repair"
class="btn btn-sm btn-info"
onclick="return confirm('Send asset for repair?')">

<i class="bi bi-tools"></i> Repair
</a>

<a href="asset_action.php?id=<?= $row['id'] ?>&action=dispose"
class="btn btn-sm btn-danger"
onclick="return confirm('Dispose this asset?')">

<i class="bi bi-trash"></i> Dispose
</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>
</div>
</div>

<?php
$content = ob_get_clean();
include "../stock/stocklayout.php";
?>