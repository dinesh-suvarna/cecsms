<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

$error = "";
$success = "";
$unit_name = "";

/* ================= ADD UNIT ================= */
if(isset($_POST['add_unit'])){

    $division_id = intval($_POST['division_id']);
    $unit_code = strtoupper(trim($_POST['unit_code']));
    $unit_name = ucwords(strtolower(trim($_POST['unit_name'])));
    $unit_type = $_POST['unit_type'];

    if(empty($unit_name)){
        $error = "Unit name is required.";
    } elseif(empty($division_id)){
        $error = "Division is required.";
    } else {

        $check = $conn->prepare("
            SELECT id, status 
            FROM units 
            WHERE division_id=? 
            AND (LOWER(unit_name)=LOWER(?) OR LOWER(unit_code)=LOWER(?))
        ");
        $check->bind_param("iss", $division_id, $unit_name, $unit_code);
        $check->execute();
        $resultCheck = $check->get_result();

        if($resultCheck->num_rows > 0){

            $row = $resultCheck->fetch_assoc();

            if($row['status'] == 'Active'){
                $error = "Unit already exists.";
            } else {

                $restore = $conn->prepare("
                    UPDATE units 
                    SET status='Active', unit_type=? 
                    WHERE id=?
                ");
                $restore->bind_param("si", $unit_type, $row['id']);
                $restore->execute();

                $success = "Unit restored successfully.";
            }

        } else {

            $stmt = $conn->prepare("
                INSERT INTO units (division_id, unit_code, unit_name, unit_type)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $division_id, $unit_code, $unit_name, $unit_type);
            $stmt->execute();

            $success = "Unit added successfully.";
            $unit_name = "";
        }
    }
}

/* ================= FETCH ================= */

$search = $_GET['search'] ?? '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 5;
$start  = ($page - 1) * $limit;

$params = [];
$types  = "";
$where  = " WHERE 1 ";

/* Role filter */
if($role !== 'SuperAdmin'){
    $where .= " AND i.id=? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

/* Search */
if(!empty($search)){
    $where .= " AND (u.unit_name LIKE ? OR u.unit_code LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

/* COUNT */
$countSql = "SELECT COUNT(*) as total
FROM units u
JOIN divisions d ON u.division_id=d.id
JOIN institutions i ON d.institution_id=i.id
$where";

$stmt = $conn->prepare($countSql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRows = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

/* DATA */
$sql = "SELECT u.*, d.division_name, i.institution_name
FROM units u
JOIN divisions d ON u.division_id=d.id
JOIN institutions i ON d.institution_id=i.id
$where
ORDER BY i.institution_name, d.division_name, u.unit_name
LIMIT ?, ?";

$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

ob_start();
?>

<div class="row g-4">

<!-- LEFT: ADD -->
<div class="col-lg-4">
<div class="card shadow-sm rounded-4 border-0">
<div class="card-body p-4">

<h5 class="fw-bold mb-4">
<i class="bi bi-building text-primary me-2"></i>Add Unit
</h5>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

<?php if($role == 'SuperAdmin'): ?>
<div class="mb-3">
<label class="small fw-bold">Institution</label>
<select id="institution" class="form-select" required>
<option value="">Select</option>
<?php
$res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
while($row = $res->fetch_assoc()){
echo "<option value='{$row['id']}'>{$row['institution_name']}</option>";
}
?>
</select>
</div>
<?php else: ?>
<input type="hidden" id="institution" value="<?= $user_institution_id ?>">
<?php endif; ?>

<div class="mb-3">
<label class="small fw-bold">Division</label>
<select name="division_id" id="division" class="form-select" required>
<option value="">Select Division</option>
</select>
</div>

<div class="row">
    <div class="col-md-4">
        <label>Unit Code</label>
        <input type="text" name="unit_code" class="form-control" >
    </div>

    <div class="col-md-8">
        <label>Unit Name</label>
        <input type="text" name="unit_name" class="form-control" required>
    </div>
</div>

<div class="mb-4">
<label class="small fw-bold">Type</label>
<select name="unit_type" class="form-select">
<option value="lab">Lab</option>
<option value="office">Office</option>
<option value="store">Store</option>
<option value="room">Room</option>
<option value="other">Other</option>
</select>
</div>

<button name="add_unit" class="btn btn-primary w-100 rounded-pill">
Save Unit
</button>

</form>

</div>
</div>
</div>

<!-- RIGHT: LIST -->
<div class="col-lg-8">
<div class="card shadow-sm rounded-4 border-0 p-4">

<h5 class="fw-bold mb-3">Units</h5>

<form method="GET" class="mb-3">
<div class="row g-2">
<div class="col-md-10">
<input type="text" name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Search Unit...">
</div>
<div class="col-md-2">
<button class="btn btn-primary w-100">Search</button>
</div>
</div>
</form>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead class="small text-muted">
<tr>
<th>Institution</th>
<th>Division</th>
<th>Unit</th>
<th>Type</th>
<th>Status</th>
<th class="text-end">Action</th>
</tr>
</thead>

<tbody>

<?php
$currentInst = '';
$currentDiv  = '';

$divisionCounts = [];

$countQuery = $conn->query("
    SELECT d.division_name, COUNT(u.id) as total
    FROM units u
    JOIN divisions d ON u.division_id = d.id
    WHERE u.status = 'Active'
    GROUP BY d.division_name
");

while($row = $countQuery->fetch_assoc()){
    $divisionCounts[$row['division_name']] = $row['total'];
}

while($row = $result->fetch_assoc()):

    $inst = $row['institution_name'];
    $div  = $row['division_name'];
    $divId = "div_" . md5($inst . $div);

    // Institution Header
    if($currentInst != $inst):
?>
<tr class="table-primary fw-bold">
    <td colspan="6">
        <i class="bi bi-building me-2"></i><?= $inst ?>
    </td>
</tr>
<?php
    $currentInst = $inst;
    $currentDiv = '';
    endif;

    // Division Header
    
    if($currentDiv != $div):
?>
<tr class="table-light fw-semibold toggle-division" data-target="<?= $divId ?>" style="cursor:pointer;">
    <td></td>
    <td colspan="5">
        <i class="bi bi-caret-right-fill me-2 arrow"></i>
        <?= $div ?>
        <span class="badge bg-dark ms-2"><?= $divisionCounts[$div] ?? 0 ?></span>
    </td>
</tr>
<?php
    $currentDiv = $div;
    endif;
?>

<tr class="unit-row <?= $divId ?>" style="display:none;">
    <td></td>
    <td></td>
    <td class="fw-bold">
        <?= $row['unit_code'] . " - " . $row['unit_name']; ?>
    </td>
    <td>
        <span class="badge bg-primary">
            <?= ucfirst($row['unit_type']) ?>
        </span>
    </td>
    <td>
        <?php if($row['status']=='Active'): ?>
            <span class="badge bg-success">Active</span>
        <?php else: ?>
            <span class="badge bg-secondary">Inactive</span>
        <?php endif; ?>
    </td>
    <td class="text-end">
        <a href="edit_unit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
    </td>
</tr>

<?php endwhile; ?>

</tbody>
</table>
</div>

</div>
</div>

</div>

<!-- AJAX -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function loadDivisions(id){
    $.post("fetch_divisions.php",{institution_id:id},function(data){
        $("#division").html(data);
    });
}

$("#institution").change(function(){
    loadDivisions($(this).val());
});

$(document).ready(function(){
    var inst = $("#institution").val();
    if(inst){
        loadDivisions(inst);
    }
});


document.querySelectorAll('.toggle-division').forEach(row => {

    const target = row.dataset.target;
    const arrow  = row.querySelector('.arrow');

    // Load saved state
    if(localStorage.getItem(target) === 'open'){
        document.querySelectorAll('.' + target).forEach(r => r.style.display = '');
        arrow.classList.remove('bi-caret-right-fill');
        arrow.classList.add('bi-caret-down-fill');
    }

    row.addEventListener('click', () => {

        const rows = document.querySelectorAll('.' + target);
        const isOpen = rows[0].style.display !== 'none';

        rows.forEach(r => {
            r.style.display = isOpen ? 'none' : '';
        });

        // Toggle arrow
        arrow.classList.toggle('bi-caret-right-fill');
        arrow.classList.toggle('bi-caret-down-fill');

        // Save state
        localStorage.setItem(target, isOpen ? 'closed' : 'open');
    });

});

</script>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>