<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$error = "";
$success = "";

// Get role & institution
$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

// Handle form submit
if(isset($_POST['add_unit'])){

    $institution_id = ($role == 'SuperAdmin') 
                        ? intval($_POST['institution_id']) 
                        : $user_institution_id;

    $division_id = intval($_POST['division_id']);
    $unit_name   = trim($_POST['unit_name']);
    $unit_type   = $_POST['unit_type'];

    // Check duplicate unit under same division
    // Format name properly
$unit_name = ucwords(strtolower(trim($_POST['unit_name'])));

// Check if unit exists (including Deleted)
$check = $conn->prepare("
    SELECT id, status 
    FROM units 
    WHERE division_id=? 
    AND LOWER(unit_name)=LOWER(?)
");
$check->bind_param("is", $division_id, $unit_name);
$check->execute();
$result = $check->get_result();

if($result->num_rows > 0){

    $row = $result->fetch_assoc();

    if($row['status'] == 'Deleted'){

        // 🔥 Restore instead of insert
        $restore = $conn->prepare("
            UPDATE units 
            SET status='Active', unit_type=? 
            WHERE id=?
        ");
        $restore->bind_param("si", $unit_type, $row['id']);
        $restore->execute();

        $success = "Unit restored successfully.";

    } else {

        $error = "Unit already exists in this division.";
    }

} else {

    // Normal insert
    $stmt = $conn->prepare("
        INSERT INTO units (division_id, unit_name, unit_type) 
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iss", $division_id, $unit_name, $unit_type);

    if($stmt->execute()){
        $success = "Unit added successfully.";
    } else {
        $error = "Something went wrong.";
    }
}
}
?>

<?php ob_start(); ?>

<div class="container mt-4">
<div class="card shadow rounded-4">
<div class="card-body">

<h5 class="mb-4">Add Unit</h5>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

<?php if($role == 'SuperAdmin'){ ?>

<div class="mb-3">
<label class="form-label">Institution</label>
<select name="institution_id" id="institution" class="form-select" required>
    <option value="">Select Institution</option>
    <?php
    $res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
    while($row = $res->fetch_assoc()){
        echo "<option value='{$row['id']}'>{$row['institution_name']}</option>";
    }
    ?>
</select>
</div>

<?php } else { ?>

<input type="hidden" id="institution" value="<?php echo $user_institution_id; ?>">

<?php } ?>

<div class="mb-3" id="divisionWrapper">
    <label class="form-label">Division</label>
    <div class="d-flex gap-2">
        <select name="division_id" id="division" class="form-select" required>
            <option value="">Select Division</option>
        </select>

        
    </div>
</div>

<div class="alert alert-warning d-none" id="noDivisionMsg">
    No divisions found for this institution.
    <a href="../master/division_add.php" class="btn btn-sm btn-primary ms-2">
        Add Division
    </a>
</div>

<div class="mb-3">
<label class="form-label">Unit Name</label>
<input type="text" name="unit_name" class="form-control" autocomplete="off" required>
</div>

<div class="mb-3">
<label class="form-label">Unit Type</label>
<select name="unit_type" class="form-select" required>
    <option value="lab">Lab</option>
    <option value="office">Office</option>
    <option value="store">Store</option>
    <option value="room">Room</option>
    <option value="other">Other</option>
</select>
</div>

<div class="d-flex gap-2">
    <button type="submit" name="add_unit" class="btn btn-primary">
        Save
    </button>

    <a href="unit_list.php" class="btn btn-secondary">
        View
    </a>
</div>

</form>

</div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){

    function loadDivisions(institution_id){
    $.post("fetch_divisions.php", {institution_id: institution_id}, function(data){

        if(data.trim() === '<option value="">Select Division</option>'){
            
            $("#division").html('');
            $("#divisionWrapper").hide();
            $("#noDivisionMsg").show();

        } else {

            $("#division").html(data);
            $("#divisionWrapper").show();
            $("#noDivisionMsg").hide();

        }
    });
}

    var institution_id = $("#institution").val();
    if(institution_id){
        loadDivisions(institution_id);
    }

    $("#institution").change(function(){
        loadDivisions($(this).val());
    });

});
</script>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>