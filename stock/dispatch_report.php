<?php
ob_start();
include "../config/db.php";
include "../includes/session.php";
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? '';

$page_title = "Dispatch Report";
$page_icon  = "bi-clipboard-data";
/* Role Restriction */
if($user_role !== 'SuperAdmin'){
    echo "<div class='container mt-5'>
            <div class='alert alert-danger text-center'>
                <h5>Access Denied</h5>
                <p>Only Superadmin can dispatch stock.</p>
            </div>
          </div>";
    exit;
}

/* Filters */
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$institution_filter = $_GET['institution_id'] ?? '';

$where = "WHERE 1=1";

if(!empty($from_date) && !empty($to_date)){
    $where .= " AND dm.dispatch_date BETWEEN '$from_date' AND '$to_date'";
}

if(!empty($institution_filter)){
    $where .= " AND dm.institution_id = ".(int)$institution_filter;
}

$stock_filter = isset($_GET['stock_id']) ? (int)$_GET['stock_id'] : 0;


if($stock_filter > 0){
    $where .= " AND dd.stock_detail_id = $stock_filter";
    echo "<div class='alert alert-info'>
            Showing dispatch history for selected stock item
          </div>";
}

/* Fetch Institutions for filter */
$institutions = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name ASC");

/* Fetch Report Data */
$query = "
SELECT 
    dm.id AS dispatch_id,
    dm.status,
    dm.dispatch_date,
    dm.created_at,

    u.username AS dispatched_by,

    dd.stock_detail_id,
    dd.quantity,
    si.item_name,
    sd.serial_number,
    si.category,

    i.institution_name,
    d.division_name,
    un.unit_name

FROM dispatch_details dd

LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
LEFT JOIN users u ON dm.user_id = u.id

LEFT JOIN stock_details sd ON dd.stock_detail_id = sd.id
LEFT JOIN items_master si ON sd.stock_item_id = si.id

LEFT JOIN institutions i ON dm.institution_id = i.id
LEFT JOIN divisions d ON dm.division_id = d.id
LEFT JOIN units un ON dm.unit_id = un.id

$where

ORDER BY dm.id DESC
";



$result = $conn->query($query);

/* ================= GROUP DATA ================= */
$grouped = [];
while($row = $result->fetch_assoc()){

    $institution = $row['institution_name'] ?? 'Unknown';
    $division    = $row['division_name'] ?? 'Unknown';
    $unit        = $row['unit_name'] ?? 'Unknown';
    $item        = $row['item_name'] ?? 'Unknown';
    $category    = $row['category'] ?? '';

    // Initialize structure
    $grouped[$institution] ??= [
        'computer_total'=>0,
        'divisions'=>[]
    ];

    $grouped[$institution]['divisions'][$division] ??= [
        'computer_total'=>0,
        'units'=>[]
    ];

    $grouped[$institution]['divisions'][$division]['units'][$unit] ??= [
        'computer_total'=>0,
        'items'=>[]
    ];

    $grouped[$institution]['divisions'][$division]['units'][$unit]['items'][$item] ??= [
        'total'=>0
    ];

    // Quantity rule
    $qty = !empty($row['serial_number']) ? 1 : (int)$row['quantity'];

    // Store row
    $grouped[$institution]['divisions'][$division]['units'][$unit]['items'][$item][] = $row;

    // Item total
    $grouped[$institution]['divisions'][$division]['units'][$unit]['items'][$item]['total'] += $qty;

    // Count only Computer category
    if($category === 'Computer'){

        // Institution computer total
        $grouped[$institution]['computer_total'] += $qty;

        // Division computer total
        $grouped[$institution]['divisions'][$division]['computer_total'] += $qty;

        // Unit computer total  👈 THIS IS NEW
        $grouped[$institution]['divisions'][$division]['units'][$unit]['computer_total'] += $qty;
    }
}
?>


<div class="container mt-4">
<div class="card shadow-sm border-0 rounded-4">
<div class="card-body">

<h5 class="mb-4 fw-semibold">Dispatch Report</h5>

<!-- Filters -->
<form method="GET" class="row mb-4">

<div class="col-md-3">
<label>From Date</label>
<input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
</div>

<div class="col-md-3">
<label>To Date</label>
<input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
</div>

<div class="col-md-4">
<label>Institution</label>
<select name="institution_id" class="form-select">
<option value="">All Institutions</option>
<?php while($inst = $institutions->fetch_assoc()): ?>
<option value="<?= $inst['id'] ?>" 
<?= $institution_filter == $inst['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($inst['institution_name']) ?>
</option>
<?php endwhile; ?>
</select>
</div>

<div class="col-md-2 d-flex align-items-end">
<button class="btn btn-primary w-100">Filter</button>
</div>

</form>

<!-- Report Table -->
<div class="table-responsive">
<table class="table table-bordered table-striped align-middle">
<thead class="table-light">
<tr>
<th>Dispatch ID</th>
<th>Date</th>
<th>Institution</th>
<th>Division</th>
<th>Unit</th>
<th>Item</th>
<th>Serial / Quantity</th>
<th>Dispatched By</th>
<th>Status</th>
</tr>
</thead>

<tbody>



<!-- INSTITUTION HEADER -->
<?php foreach($grouped as $institution => $instData): 
    $inst_id = "inst_" . md5($institution);
?>

<tr class="inst-header" data-group="<?= $inst_id ?>">
    <td colspan="9" style="background:#f1f3f5; cursor:pointer;">
        <strong>
            <?= htmlspecialchars($institution) ?> 
            (<?= $instData['computer_total'] ?? 0 ?> Computers)
        </strong>
    </td>
</tr>


<!-- DIVISION HEADER -->
<?php foreach($instData['divisions'] as $division => $divData): 
    $div_id = "div_" . md5($institution.$division);
?>

<tr class="div-header group-<?= $inst_id ?>" 
    data-group="<?= $div_id ?>" 
    style="display:none;">
    <td colspan="9" style="background:#f8f9fa; padding-left:30px; cursor:pointer;">
        <strong>
            <?= htmlspecialchars($division) ?> 
            (<?= $divData['computer_total'] ?? 0 ?> Computers)
        </strong>
    </td>
</tr>



<!-- UNIT HEADER -->
<?php foreach($divData['units'] as $unit => $unitData): 
    $unit_id = "unit_" . md5($institution.$division.$unit);
?>

<tr class="unit-header group-<?= $div_id ?>" 
    data-group="<?= $unit_id ?>" 
    style="display:none;">
    <td colspan="9" style="padding-left:60px; cursor:pointer;">
        <strong>
            <?= htmlspecialchars($unit) ?> 
            (<?= $unitData['computer_total'] ?? 0 ?> Computers)
        </strong>
    </td>
</tr>


<!-- ITEM HEADER -->
<?php foreach($unitData['items'] as $itemName => $itemData): 
    $item_id = "item_" . md5($institution.$division.$unit.$itemName);
?>

<tr class="item-header group-<?= $unit_id ?>" 
    data-group="<?= $item_id ?>" 
    style="display:none;">
    <td colspan="9" style="padding-left:90px; cursor:pointer;">
        <?= htmlspecialchars($itemName) ?> 
        (<?= $itemData['total'] ?>)
    </td>
</tr>



<?php 
foreach($itemData as $key => $row): 
    if($key === 'total') continue; // skip total
?>

<tr class="group-<?= $item_id ?>" style="display:none;">
    <td><?= "DSP-" . str_pad($row['dispatch_id'],4,'0',STR_PAD_LEFT) ?></td>
    <td><?= date("d-m-Y", strtotime($row['dispatch_date'])) ?></td>
    <td><?= htmlspecialchars($row['institution_name']) ?></td>
    <td><?= htmlspecialchars($row['division_name']) ?></td>
    <td><?= htmlspecialchars($row['unit_name']) ?></td>
    <td><?= htmlspecialchars($row['item_name']) ?></td>
    <td>
        <?php
        if(!empty($row['serial_number'])){
            echo htmlspecialchars($row['serial_number']);
        } else {
            echo $row['quantity'] . " Units";
        }
        ?>
    </td>
    <td><?= htmlspecialchars($row['dispatched_by']) ?></td>
    <td>
        <?php if($row['status']=='returned'): ?>
            <span class="badge bg-success">Returned</span>
        <?php else: ?>
            <span class="badge bg-danger">Dispatched</span>
        <?php endif; ?>
    </td>
</tr>

<?php endforeach; ?>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endforeach; ?>

</tbody>

</table>

</div>


</div>
</div>

</div>
<script>
document.addEventListener("DOMContentLoaded", function() {

    // Institution Toggle
    document.querySelectorAll('.inst-header').forEach(header => {
        header.addEventListener('click', function(){
            let group = this.getAttribute('data-group');
            document.querySelectorAll('.group-' + group).forEach(row=>{
                row.style.display = row.style.display === "none" ? "" : "none";
            });
        });
    });

    // Division Toggle
    document.querySelectorAll('.div-header').forEach(header => {
        header.addEventListener('click', function(){
            let group = this.getAttribute('data-group');
            document.querySelectorAll('.group-' + group).forEach(row=>{
                row.style.display = row.style.display === "none" ? "" : "none";
            });
        });
    });

    // Unit Toggle
    document.querySelectorAll('.unit-header').forEach(header => {
        header.addEventListener('click', function(){
            let group = this.getAttribute('data-group');
            document.querySelectorAll('.group-' + group).forEach(row=>{
                row.style.display = row.style.display === "none" ? "" : "none";
            });
        });
    });

    // Item Toggle
    document.querySelectorAll('.item-header').forEach(header => {
        header.addEventListener('click', function(){
            let group = this.getAttribute('data-group');
            document.querySelectorAll('.group-' + group).forEach(row=>{
                row.style.display = row.style.display === "none" ? "" : "none";
            });
        });
    });

    });



</script>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>