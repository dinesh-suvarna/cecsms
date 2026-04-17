<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$error = "";
$success = "";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

// Validate ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: units.php");
    exit;
}

$unit_id = intval($_GET['id']);

// Fetch unit - Updated to include new columns
$stmt = $conn->prepare("
    SELECT * FROM units 
    WHERE id=? AND status='Active'
");
$stmt->bind_param("i", $unit_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    header("Location: units.php");
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
        header("Location: units.php");
        exit;
    }
}

// Set variables for form
$unit_code = $unit['unit_code'];
$unit_name = $unit['unit_name'];
$unit_type = $unit['unit_type'];
$location  = $unit['location'];
$area_sqmt = $unit['area_sqmt'];
$division_id = $unit['division_id'];


// ================= UPDATE LOGIC =================
if(isset($_POST['update_unit'])){

    $unit_code   = strtoupper(trim($_POST['unit_code']));
    $unit_name   = ucwords(trim($_POST['unit_name']));
    $division_id = intval($_POST['division_id']);
    $unit_type   = $_POST['unit_type'];
    $location    = trim($_POST['location']);
    $area_sqmt   = !empty($_POST['area_sqmt']) ? floatval($_POST['area_sqmt']) : NULL;

    if(empty($unit_code)){
        $error = "Unit code is required.";
    } elseif(empty($unit_name)){
        $error = "Unit name is required.";
    } else {
        // Check for duplicates excluding current ID
        $check = $conn->prepare("
            SELECT id FROM units 
            WHERE division_id=? 
            AND (LOWER(unit_name)=LOWER(?) OR LOWER(unit_code)=LOWER(?))
            AND id != ?
        ");
        $check->bind_param("issi", $division_id, $unit_name, $unit_code, $unit_id);
        $check->execute();
        $dup = $check->get_result();

        if($dup->num_rows > 0){
            $error = "Unit code or name already exists in this division.";
        } else {
            // Updated Update Query with new columns
            $update = $conn->prepare("
                UPDATE units 
                SET unit_code=?, unit_name=?, unit_type=?, location=?, area_sqmt=?, division_id=? 
                WHERE id=?
            ");

            $update->bind_param(
                "ssssdii",
                $unit_code,
                $unit_name,
                $unit_type,
                $location,
                $area_sqmt,
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
    <div class="card shadow rounded-4 border-0">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Lab/Facility</h5>

            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="small fw-bold">Institution</label>
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
                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($inst_name['institution_name']) ?>" readonly>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold">Department / Division</label>
                    <select name="division_id" class="form-select" required>
                        <?php
                        if($role == 'SuperAdmin'){
                            $divisions = $conn->query("SELECT id, division_name FROM divisions WHERE status='Active' ORDER BY division_name");
                        } else {
                            $divisions = $conn->prepare("SELECT id, division_name FROM divisions WHERE institution_id=? AND status='Active' ORDER BY division_name");
                            $divisions->bind_param("i", $user_institution_id);
                            $divisions->execute();
                            $divisions = $divisions->get_result();
                        }
                        while($div = $divisions->fetch_assoc()):
                        ?>
                            <option value="<?= $div['id'] ?>" <?= $division_id == $div['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($div['division_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="small fw-bold">Unit Code</label>
                        <input type="text" name="unit_code" value="<?= htmlspecialchars($unit_code) ?>" class="form-control" required>
                    </div>

                    <div class="col-md-8 mb-3">
                        <label class="small fw-bold">Unit Name</label>
                        <input type="text" name="unit_name" value="<?= htmlspecialchars($unit_name) ?>" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold">Type</label>
                        <select name="unit_type" class="form-select">
                            <?php 
                            $types = ['lab', 'office', 'store', 'room', 'classroom', 'other'];
                            foreach($types as $t): ?>
                                <option value="<?= $t ?>" <?= $unit_type == $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="small fw-bold">Area (Sq. Mt.)</label>
                        <input type="number" step="0.01" name="area_sqmt" value="<?= htmlspecialchars($area_sqmt) ?>" class="form-control">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="small fw-bold">Physical Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($location) ?>" class="form-control" placeholder="e.g. Block A, 2nd Floor">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="update_unit" class="btn btn-primary px-4 rounded-pill">
                        <i class="bi bi-check-circle me-1"></i> Update Unit
                    </button>
                    <a href="units.php" class="btn btn-light border px-4 rounded-pill">
                        Back to List
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