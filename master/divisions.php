<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;
$institution_filter = $_GET['institution_id'] ?? '';

/* ================= ADD DIVISION ================= */
$error = "";
$success = "";
$division_name = "";

if(isset($_POST['add_division'])){

    $institution_id = ($role == 'SuperAdmin') 
                        ? intval($_POST['institution_id']) 
                        : $user_institution_id;

    $division_name = ucwords(trim($_POST['division_name']));
    $division_type = $_POST['division_type'];

    if(empty($division_name)){
        $error = "Division name is required.";
    } elseif(empty($institution_id)){
        $error = "Institution is required.";
    } else {

        $check = $conn->prepare("
            SELECT id, status 
            FROM divisions 
            WHERE institution_id=? 
            AND LOWER(division_name)=LOWER(?)
        ");
        $check->bind_param("is", $institution_id, $division_name);
        $check->execute();
        $resultCheck = $check->get_result();

        if($resultCheck->num_rows > 0){
            $row = $resultCheck->fetch_assoc();

            if($row['status'] === 'Active'){
                $error = "Division already exists.";
            } else {
                $update = $conn->prepare("
                    UPDATE divisions 
                    SET status='Active', division_type=? 
                    WHERE id=?
                ");
                $update->bind_param("si", $division_type, $row['id']);
                $update->execute();
                $success = "Division restored successfully.";
            }

        } else {
            $stmt = $conn->prepare("
                INSERT INTO divisions 
                (institution_id, division_name, division_type, status) 
                VALUES (?, ?, ?, 'Active')
            ");
            $stmt->bind_param("iss", $institution_id, $division_name, $division_type);
            $stmt->execute();
            $success = "Division added successfully.";
            $division_name = "";
        }
    }
}

/* ================= FETCH LIST ================= */
$search = $_GET['search'] ?? '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 5;
$start  = ($page - 1) * $limit;

$params = [];
$types  = "";
$where  = " WHERE 1 ";

if($role !== 'SuperAdmin'){
    $where .= " AND d.institution_id=? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

if(!empty($search)){
    $where .= " AND d.division_name LIKE ? ";
    $params[] = "%$search%";
    $types .= "s";
}

if($role == 'SuperAdmin' && !empty($institution_filter)){
    $where .= " AND d.institution_id=? ";
    $params[] = $institution_filter;
    $types .= "i";
}

/* COUNT */
$countSql = "SELECT COUNT(*) as total FROM divisions d $where";
$stmt = $conn->prepare($countSql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRows = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

/* DATA */
$sql = "SELECT d.*, i.institution_name
        FROM divisions d
        JOIN institutions i ON d.institution_id=i.id
        $where
        ORDER BY i.institution_name, d.division_name
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
<i class="bi bi-diagram-3 text-primary me-2"></i>Add Division
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
<select name="institution_id" class="form-select" required>
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
<input type="hidden" name="institution_id" value="<?= $user_institution_id; ?>">
<?php endif; ?>

<div class="mb-3">
<label class="small fw-bold">Division Name</label>
<input type="text" name="division_name"
value="<?= htmlspecialchars($division_name) ?>"
class="form-control" required>
</div>

<div class="mb-4">
<label class="small fw-bold">Type</label>
<select name="division_type" class="form-select">
<option value="academic">Academic</option>
<option value="administrative">Administrative</option>
<option value="support">Support</option>
<option value="other">Other</option>
</select>
</div>

<button name="add_division" class="btn btn-primary w-100 rounded-pill">
Save Division
</button>

</form>

</div>
</div>
</div>

<!-- RIGHT: LIST -->
<div class="col-lg-8">
<div class="card shadow-sm rounded-4 border-0 p-4">

<div class="d-flex justify-content-between mb-3">
<h5 class="fw-bold m-0">Divisions</h5>
</div>

<form method="GET" class="mb-3">
<div class="row g-2">

<?php if($role == 'SuperAdmin'): ?>
<div class="col-md-6">
<select name="institution_id" class="form-select" onchange="this.form.submit()">
<option value="">All Institutions</option>
<?php
$res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
while($row = $res->fetch_assoc()){
$selected = ($institution_filter == $row['id']) ? 'selected' : '';
echo "<option value='{$row['id']}' $selected>{$row['institution_name']}</option>";
}
?>
</select>
</div>
<?php endif; ?>

<div class="col-md-4">
<input type="text" name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Search Division...">
</div>

<div class="col-md-2">
<button class="btn btn-primary w-100">Filter</button>
</div>

</div>
</form>

</div>

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success">
<?= $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<div class="alert alert-danger">
<?= $_SESSION['error']; unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead class="small text-muted">
<tr>
<th>Institution</th>
<th>Division</th>
<th>Type</th>
<th>Status</th>
<th class="text-end">Action</th>
</tr>
</thead>

<tbody>
<?php while($row=$result->fetch_assoc()): ?>
<tr>

<td><?= htmlspecialchars($row['institution_name']) ?></td>

<td class="fw-bold"><?= htmlspecialchars($row['division_name']) ?></td>

<td>
<span class="badge bg-primary">
<?= ucfirst($row['division_type']) ?>
</span>
</td>
<td>
<?php if($row['status'] == 'Active'): ?>
    <span class="badge bg-success">Active</span>
<?php else: ?>
    <span class="badge bg-secondary">Inactive</span>
<?php endif; ?>
</td>

<td class="text-end">

<?php if($row['status'] == 'Active'): ?>

<a href="edit_division.php?id=<?= $row['id'] ?>"
class="btn btn-sm btn-warning">Edit</a>

<button class="btn btn-sm btn-danger"
onclick="deactivateDivision(<?= $row['id'] ?>)">
Deactivate
</button>

<?php else: ?>

<button class="btn btn-sm btn-success"
onclick="restoreDivision(<?= $row['id'] ?>)">
Restore
</button>

<?php endif; ?>

</td>

</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<!-- PAGINATION -->
<?php if($totalPages > 1): ?>
<ul class="pagination justify-content-center mt-3">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<li class="page-item <?= ($i==$page)?'active':'' ?>">
<a class="page-link"
href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&institution_id=<?= urlencode($institution_filter) ?>"
<?= $i ?>
</a>
</li>
<?php endfor; ?>
</ul>
<?php endif; ?>

</div>
</div>

</div>
<form method="POST" id="deleteForm" action="division_delete.php">
<input type="hidden" name="id" id="delete_id">
</form>

<form method="POST" id="restoreForm" action="division_restore.php">
<input type="hidden" name="id" id="restore_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function deactivateDivision(id){
    Swal.fire({
        title: 'Deactivate Division?',
        text: "Division will be marked inactive.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, deactivate'
    }).then((result) => {
        if(result.isConfirmed){
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}

function restoreDivision(id){
    Swal.fire({
        title: 'Restore Division?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Yes, restore'
    }).then((result) => {
        if(result.isConfirmed){
            document.getElementById('restore_id').value = id;
            document.getElementById('restoreForm').submit();
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>