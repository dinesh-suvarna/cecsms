<?php
ob_start();
$page_title = "Institute Management";
include "../config/db.php";
include "../includes/session.php";
include  "../includes/role_admin.php";

/* ================= DELETE (SOFT DELETE - POST) ================= */
if(isset($_POST['delete_id'])){
    $id = intval($_POST['delete_id']);

    $stmt = $conn->prepare("UPDATE institutions SET status='Inactive' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: edit_delete_institute.php");
    exit;
}

/* ================= RESTORE ================= */
if(isset($_POST['restore_id'])){
    $id = intval($_POST['restore_id']);

    $stmt = $conn->prepare("UPDATE institutions SET status='Active' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: edit_delete_institute.php");
    exit;
}

/* ================= UPDATE ================= */
if(isset($_POST['update'])){

    $id       = intval($_POST['id']);
    $new_name = trim($_POST['institution_name']);

    // Check duplicate
    $check = $conn->prepare("SELECT id FROM institutions WHERE institution_name=? AND id!=?");
    $check->bind_param("si", $new_name, $id);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        echo "<script>alert('Institute name already exists!');</script>";
    } else {
        $stmt = $conn->prepare("
            UPDATE institutions 
            SET institution_name=? 
            WHERE id=?
        ");
        $stmt->bind_param("si", $new_name, $id);
        $stmt->execute();

        header("Location: edit_delete_institute.php");
        exit;
    }
}

/* ================= FETCH ALL ================= */
$result = $conn->query("SELECT * FROM institutions ORDER BY created_at DESC");

/* ================= FETCH EDIT DATA ================= */
$editData = null;
if(isset($_GET['edit'])){
    $id = intval($_GET['edit']);

    $stmt = $conn->prepare("SELECT * FROM institutions WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Institute Management</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>


<div class="container">

<div class="card shadow rounded-4">
<div class="card-body">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">Institute Management</h5>

    <a href="add_institute.php" 
       class="btn btn-success btn-sm rounded-pill px-3">
        Add Institute
    </a>
</div>

<!-- ================= EDIT FORM ================= -->
<?php if($editData): ?>
<div class="card mb-4 border-warning">
<div class="card-body">

<h6 class="mb-3 text-warning">Edit Institute</h6>

<form method="POST">

<input type="hidden" name="id" value="<?= $editData['id'] ?>">

<div class="row">
<div class="col-md-4 mb-3">
<label>Institute Name</label>
<input type="text" name="institution_name" class="form-control"
value="<?= htmlspecialchars($editData['institution_name']) ?>" required>
</div>
</div>

<button type="submit" name="update" class="btn btn-success btn-sm">Update</button>
<a href="edit_delete_institute.php" class="btn btn-secondary btn-sm">Cancel</a>

</form>

</div>
</div>
<?php endif; ?>

<!-- ================= TABLE ================= -->
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
    <th>Sl.No</th>
    <th>Institute</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php
if($result->num_rows > 0){
    $i = 1;
    while($row = $result->fetch_assoc()){
?>
<tr>
    <td><?= $i++ ?></td>
    <td class="fw-medium"><?= htmlspecialchars($row['institution_name']) ?></td>
    <td>
        <?php if($row['status'] == 'Active'): ?>
            <span class="badge bg-success">Active</span>
        <?php else: ?>
            <span class="badge bg-secondary">Inactive</span>
        <?php endif; ?>
    </td>
    <td>

        <?php if($row['status'] == 'Active'): ?>

        <a href="edit_delete_institute.php?edit=<?= $row['id'] ?>"
           class="btn btn-sm btn-warning">Edit</a>

        <button class="btn btn-sm btn-danger"
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
<?php
    }
}else{
    echo "<tr><td colspan='4' class='text-center text-muted'>No Institutes Found</td></tr>";
}
?>

</tbody>
</table>
</div>

</div>
</div>

</div>

<!-- Hidden Forms -->
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
        text: "Institute will be marked Inactive.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, deactivate'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}

function restoreInstitute(id){
    Swal.fire({
        title: 'Restore Institute?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Yes, restore'
    }).then((result) => {
        if (result.isConfirmed) {
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