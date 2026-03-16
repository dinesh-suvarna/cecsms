<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$error = "";
$success = "";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

// Validate ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: division_list.php");
    exit;
}

$division_id = intval($_GET['id']);

// Fetch division
$stmt = $conn->prepare("
    SELECT * FROM divisions 
    WHERE id=? AND status='Active'
");
$stmt->bind_param("i", $division_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    header("Location: division_list.php");
    exit;
}

$division = $result->fetch_assoc();
$stmt->close();

// Security: prevent editing other institution division
if($role != 'SuperAdmin' && $division['institution_id'] != $user_institution_id){
    header("Location: division_list.php");
    exit;
}

$division_name = $division['division_name'];
$division_type = $division['division_type'];


// ================= UPDATE =================
if(isset($_POST['update_division'])){

    $division_name = ucwords(trim($_POST['division_name']));
    $division_type = $_POST['division_type'];

    if(empty($division_name)){
        $error = "Division name is required.";
    } else {

        // ✅ Duplicate check excluding current ID
        $check = $conn->prepare("
            SELECT id 
            FROM divisions 
            WHERE institution_id=? 
            AND LOWER(division_name)=LOWER(?) 
            AND id != ?
        ");
        $check->bind_param(
            "isi",
            $division['institution_id'],
            $division_name,
            $division_id
        );
        $check->execute();
        $dup = $check->get_result();

        if($dup->num_rows > 0){
            $error = "Another division with this name already exists.";
        } else {

            $update = $conn->prepare("
                UPDATE divisions 
                SET division_name=?, division_type=? 
                WHERE id=?
            ");
            $update->bind_param(
                "ssi",
                $division_name,
                $division_type,
                $division_id
            );

            if($update->execute()){
                $success = "Division updated successfully.";
            } else {
                $error = "Failed to update division.";
            }

            $update->close();
        }

        $check->close();
    }
}
?>
<?php ob_start(); ?>

<div class="container mt-4">
<div class="card shadow rounded-4">
<div class="card-body">

<h5 class="mb-4">Edit Division</h5>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

<!-- Institution (Readonly) -->
<div class="mb-3">
    <label class="form-label">Institution</label>
    <?php
    $inst = $conn->prepare("SELECT institution_name FROM institutions WHERE id=?");
    $inst->bind_param("i", $division['institution_id']);
    $inst->execute();
    $inst_name = $inst->get_result()->fetch_assoc();
    ?>
    <input type="text"
           class="form-control"
           value="<?= htmlspecialchars($inst_name['institution_name']) ?>"
           readonly>
</div>

<!-- Division Name -->
<div class="mb-3">
    <label class="form-label">Division Name</label>
    <input type="text"
           name="division_name"
           value="<?= htmlspecialchars($division_name) ?>"
           class="form-control"
           required>
</div>

<!-- Division Type -->
<div class="mb-3">
    <label class="form-label">Division Type</label>
    <select name="division_type" class="form-select" required>
        <option value="academic" 
            <?= $division_type == 'academic' ? 'selected' : '' ?>>
            Academic
        </option>
        <option value="administrative" 
            <?= $division_type == 'administrative' ? 'selected' : '' ?>>
            Administrative
        </option>
        <option value="support" 
            <?= $division_type == 'support' ? 'selected' : '' ?>>
            Support
        </option>
        <option value="other" 
            <?= $division_type == 'other' ? 'selected' : '' ?>>
            Other
        </option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" 
            name="update_division" 
            class="btn btn-success">
        Update
    </button>

    <a href="division_list.php" class="btn btn-secondary">
        Back
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