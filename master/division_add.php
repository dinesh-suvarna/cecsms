<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$error = "";
$success = "";
$division_name = ""; // ✅ prevent undefined variable

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

if(isset($_POST['add_division'])){

    $institution_id = ($role == 'SuperAdmin') 
                        ? intval($_POST['institution_id']) 
                        : $user_institution_id;

    // ✅ Format properly
    $division_name = ucwords(trim($_POST['division_name']));
    $division_type = $_POST['division_type'];

    if(empty($division_name)){
        $error = "Division name is required.";
    } elseif(empty($institution_id)){
        $error = "Institution is required.";
    } else {

        // ✅ Case-insensitive duplicate check
        $check = $conn->prepare("
            SELECT id, status 
            FROM divisions 
            WHERE institution_id=? 
            AND LOWER(division_name)=LOWER(?)
        ");
        $check->bind_param("is", $institution_id, $division_name);
        $check->execute();
        $result = $check->get_result();

        if($result->num_rows > 0){

            $row = $result->fetch_assoc();

            if($row['status'] === 'Active'){
                $error = "Division already exists in this institution.";
            } else {

                // ✅ Restore deleted
                $update = $conn->prepare("
                    UPDATE divisions 
                    SET status='Active', division_type=? 
                    WHERE id=?
                ");
                $update->bind_param("si", $division_type, $row['id']);

                if($update->execute()){
                    $success = "Division restored successfully.";
                } else {
                    $error = "Failed to restore division.";
                }
                $update->close();
            }

        } else {

            // ✅ Insert new
            $stmt = $conn->prepare("
                INSERT INTO divisions 
                (institution_id, division_name, division_type, status) 
                VALUES (?, ?, ?, 'Active')
            ");

            $stmt->bind_param("iss", $institution_id, $division_name, $division_type);

            if($stmt->execute()){
                $success = "Division added successfully.";
                $division_name = ""; // clear after success
            } else {
                $error = "Something went wrong.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<?php ob_start(); ?>



<div class="container mt-4">
<div class="card shadow rounded-4">
<div class="card-body">

<h5 class="mb-4">Add Division</h5>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

<?php if($role == 'SuperAdmin'): ?>
    
<div class="mb-3">
    <label class="form-label">Institution</label>
    <select name="institution_id" class="form-select" required>
        <option value="">Select Institution</option>
        <?php
        $res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
        while($row = $res->fetch_assoc()){
            echo "<option value='{$row['id']}'>{$row['institution_name']}</option>";
        }
        ?>
    </select>
</div>
<?php else: ?>

<?php
$stmt = $conn->prepare("SELECT institution_name FROM institutions WHERE id=?");
$stmt->bind_param("i", $user_institution_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
?>

<div class="mb-3">
    <label class="form-label">Institution</label>
    <input type="text" class="form-control" 
           value="<?= $result['institution_name']; ?>" readonly>
</div>

<input type="hidden" name="institution_id" value="<?= $user_institution_id; ?>">

<?php endif; ?>

<div class="mb-3">
    <label class="form-label">Division Name</label>
    <input type="text" 
           name="division_name"
           value="<?php echo htmlspecialchars($division_name); ?>"
           class="form-control" 
           autocomplete="off" 
           required>
</div>

<div class="mb-3">
    <label class="form-label">Division Type</label>
    <select name="division_type" class="form-select" required>
        <option value="academic">Academic</option>
        <option value="administrative">Administrative</option>
        <option value="support">Support</option>
        <option value="other">Other</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" name="add_division" class="btn btn-primary">
        Save
    </button>

    <a href="division_list.php" class="btn btn-secondary">
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