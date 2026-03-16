<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$error = "";
$success = "";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

// Validate ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: unit_list.php");
    exit;
}

$unit_id = intval($_GET['id']);

// Fetch unit
$stmt = $conn->prepare("
    SELECT * FROM units 
    WHERE id=? AND status='Active'
");
$stmt->bind_param("i", $unit_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    header("Location: unit_list.php");
    exit;
}

$unit = $result->fetch_assoc();
$stmt->close();

// 🔐 Security: prevent editing other institution unit
if($role != 'SuperAdmin'){

    $check = $conn->prepare("
        SELECT d.institution_id 
        FROM divisions d
        WHERE d.id=?
    ");
    $check->bind_param("i", $unit['division_id']);
    $check->execute();
    $inst_data = $check->get_result()->fetch_assoc();
    $check->close();

    if($inst_data['institution_id'] != $user_institution_id){
        header("Location: unit_list.php");
        exit;
    }
}

$unit_name = $unit['unit_name'];
$division_id = $unit['division_id'];


// ================= UPDATE =================
if(isset($_POST['update_unit'])){

    $unit_name = ucwords(trim($_POST['unit_name']));
    $division_id = intval($_POST['division_id']);

    if(empty($unit_name)){
        $error = "Unit name is required.";
    } else {

        // ✅ Duplicate check inside same division
        $check = $conn->prepare("
            SELECT id 
            FROM units 
            WHERE division_id=? 
            AND LOWER(unit_name)=LOWER(?) 
            AND id != ?
        ");
        $check->bind_param(
            "isi",
            $division_id,
            $unit_name,
            $unit_id
        );
        $check->execute();
        $dup = $check->get_result();

        if($dup->num_rows > 0){
            $error = "Another unit with this name already exists in this division.";
        } else {

            $update = $conn->prepare("
                UPDATE units 
                SET unit_name=?, division_id=? 
                WHERE id=?
            ");
            $update->bind_param(
                "sii",
                $unit_name,
                $division_id,
                $unit_id
            );

            if($update->execute()){
                $success = "Unit updated successfully.";
            } else {
                $error = "Failed to update unit.";
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

<h5 class="mb-4">Edit Unit</h5>

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
    $inst = $conn->prepare("
        SELECT i.institution_name 
        FROM institutions i
        JOIN divisions d ON i.id=d.institution_id
        WHERE d.id=?
    ");
    $inst->bind_param("i", $division_id);
    $inst->execute();
    $inst_name = $inst->get_result()->fetch_assoc();
    ?>
    <input type="text"
           class="form-control"
           value="<?= htmlspecialchars($inst_name['institution_name']) ?>"
           readonly>
</div>

<!-- Division Dropdown -->
<div class="mb-3">
    <label class="form-label">Division</label>
    <select name="division_id" class="form-select" required>

        <?php
        if($role == 'SuperAdmin'){
            $divisions = $conn->query("
                SELECT id, division_name 
                FROM divisions 
                WHERE status='Active'
                ORDER BY division_name
            ");
        } else {
            $divisions = $conn->prepare("
                SELECT id, division_name 
                FROM divisions 
                WHERE institution_id=? 
                AND status='Active'
                ORDER BY division_name
            ");
            $divisions->bind_param("i", $user_institution_id);
            $divisions->execute();
            $divisions = $divisions->get_result();
        }

        while($div = $divisions->fetch_assoc()):
        ?>
            <option value="<?= $div['id'] ?>"
                <?= $division_id == $div['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($div['division_name']) ?>
            </option>
        <?php endwhile; ?>

    </select>
</div>

<!-- Unit Name -->
<div class="mb-3">
    <label class="form-label">Unit Name</label>
    <input type="text"
           name="unit_name"
           value="<?= htmlspecialchars($unit_name) ?>"
           class="form-control"
           required>
</div>

<div class="d-flex gap-2">
    <button type="submit" 
            name="update_unit" 
            class="btn btn-success">
        Update
    </button>

    <a href="unit_list.php" class="btn btn-secondary">
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