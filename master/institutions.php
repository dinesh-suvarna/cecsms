<?php
require_once "../config/db.php";
require_once "../includes/session.php";
require_once "../admin/auth.php";
requireRole([ROLE_SUPERADMIN]);

$page_title = "Institutions";
$page_icon  = "bi-building";

/* ================= ADD ================= */
$success = false;
$error = "";

if(isset($_POST['submit'])){
    $name = trim($_POST['institution_name']);

    if(empty($name)){
        $error = "Institution name is required.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO institutions (institution_name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $success = true;
        } catch (mysqli_sql_exception $e) {
            $error = ($e->getCode() == 1062) ? "Institution already exists!" : "Database error";
        }
    }
}

/* ================= DELETE ================= */
if(isset($_POST['delete_id'])){
    $id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("UPDATE institutions SET status='Inactive' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: institutions.php"); exit;
}

/* ================= RESTORE ================= */
if(isset($_POST['restore_id'])){
    $id = intval($_POST['restore_id']);
    $stmt = $conn->prepare("UPDATE institutions SET status='Active' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: institutions.php"); exit;
}

/* ================= UPDATE ================= */
if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $new_name = trim($_POST['institution_name']);

    $check = $conn->prepare("SELECT id FROM institutions WHERE institution_name=? AND id!=?");
    $check->bind_param("si", $new_name, $id);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        echo "<script>alert('Institute name already exists!');</script>";
    } else {
        $stmt = $conn->prepare("UPDATE institutions SET institution_name=? WHERE id=?");
        $stmt->bind_param("si", $new_name, $id);
        $stmt->execute();
        header("Location: institutions.php"); exit;
    }
}

/* ================= FETCH ================= */
$result = $conn->query("SELECT * FROM institutions ORDER BY created_at DESC");

/* ================= EDIT FETCH ================= */
$editData = null;
if(isset($_GET['edit'])){
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM institutions WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

ob_start();
?>

<div class="row g-4">

<!-- LEFT: ADD -->
<div class="col-lg-4">
<div class="card shadow-sm border-0 rounded-4">
<div class="card-body p-4">

<h5 class="fw-bold mb-4">
<i class="bi bi-building-add text-success me-2"></i>Add Institution
</h5>

<?php if($success): ?>
<div class="alert alert-success">Added successfully!</div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
<div class="mb-4">
<label class="small fw-bold text-muted">Institution Name</label>
<input type="text" name="institution_name" class="form-control rounded-3" required>
</div>

<button type="submit" name="submit" class="btn btn-success w-100 rounded-pill fw-bold">
Save Institution
</button>
</form>

</div>
</div>
</div>

<!-- RIGHT: TABLE -->
<div class="col-lg-8">
<div class="card shadow-sm border-0 rounded-4 p-4">

<div class="d-flex justify-content-between align-items-center mb-4">
<h5 class="fw-bold m-0">Institutions</h5>
<input type="text" id="search" class="form-control form-control-sm w-50 rounded-pill" placeholder="Search...">
</div>

<!-- EDIT FORM -->
<?php if($editData): ?>
<div class="card mb-4 border-warning rounded-4">
<div class="card-body">
<h6 class="text-warning fw-bold">Edit Institution</h6>

<form method="POST">
<input type="hidden" name="id" value="<?= $editData['id'] ?>">

<div class="mb-3">
<input type="text" name="institution_name"
class="form-control rounded-3"
value="<?= htmlspecialchars($editData['institution_name']) ?>" required>
</div>

<button name="update" class="btn btn-success btn-sm">Update</button>
<a href="institutions.php" class="btn btn-secondary btn-sm">Cancel</a>
</form>

</div>
</div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-hover align-middle" id="tbl">
<thead class="bg-light small text-muted">
<tr>
<th>#</th>
<th>Institution</th>
<th>Status</th>
<th class="text-end">Action</th>
</tr>
</thead>

<tbody>
<?php $i=1; while($row=$result->fetch_assoc()): ?>
<tr>
<td><?= $i++ ?></td>
<td class="fw-bold name"><?= htmlspecialchars($row['institution_name']) ?></td>

<td>
<?php if($row['status']=='Active'): ?>
<span class="badge bg-success">Active</span>
<?php else: ?>
<span class="badge bg-secondary">Inactive</span>
<?php endif; ?>
</td>

<td class="text-end">

<?php if($row['status']=='Active'): ?>

<a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-white border">✏️</a>

<button class="btn btn-sm btn-warning"
onclick="deleteInstitute(<?= $row['id'] ?>)">
Deactivate
</button>

<?php else: ?>

<button class="btn btn-sm btn-success"
onclick="restoreInstitute(<?= $row['id'] ?>)">
Restore
</button>

<?php endif; ?>

</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

</div>
</div>

</div>

<!-- HIDDEN FORMS -->
<form method="POST" id="deleteForm">
<input type="hidden" name="delete_id" id="delete_id">
</form>

<form method="POST" id="restoreForm">
<input type="hidden" name="restore_id" id="restore_id">
</form>

<script>
function deleteInstitute(id){
    Swal.fire({
        title: 'Deactivate Institute?',
        text: 'You can restore it later anytime.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, deactivate'
    }).then(r=>{
        if(r.isConfirmed){
            delete_id.value=id;
            deleteForm.submit();
        }
    });
}

function restoreInstitute(id){
    Swal.fire({
        title:'Restore?',
        icon:'question',
        showCancelButton:true
    }).then(r=>{
        if(r.isConfirmed){
            restore_id.value=id;
            restoreForm.submit();
        }
    });
}

// SEARCH
search.onkeyup=function(){
let f=this.value.toLowerCase();
document.querySelectorAll("#tbl tbody tr").forEach(r=>{
r.style.display = r.innerText.toLowerCase().includes(f)?'':'none';
});
}
</script>

<?php
$main_content = ob_get_clean();
include "../master/masterlayout.php";
?>