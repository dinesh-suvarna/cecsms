<?php
$page_title = "Add Institute";
include "../config/db.php";
require_once "../admin/auth.php";
requireRole([ROLE_SUPERADMIN]);

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

            if($e->getCode() == 1062){
                $error = "Institution already exists!";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Add Institute</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>


<div class="container">
<div class="card shadow rounded-4">
<div class="card-body">

<h5 class="mb-4">Add Institute</h5>

<?php if($success): ?>
<div class="alert alert-success">Institute Added Successfully</div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">



<div class="mb-3">
<label>Institute Name</label>
<input type="text" name="institution_name" class="form-control" utocomplete="off" required>
</div>




<div class="d-flex gap-2">

    <button type="submit" name="submit" class="btn btn-primary">
        Save
    </button>

    <a href="edit_delete_institute.php" class="btn btn-secondary">
        View
    </a>

</div>


</form>

</div>
</div>
</div>
<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>
