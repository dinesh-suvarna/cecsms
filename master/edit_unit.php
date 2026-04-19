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

// Fetch unit
$stmt = $conn->prepare("SELECT * FROM units WHERE id=? AND status='Active'");
$stmt->bind_param("i", $unit_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    header("Location: units.php");
    exit;
}

$unit = $result->fetch_assoc();
$stmt->close();

// 🔐 Security check
if($role != 'SuperAdmin'){
    $check = $conn->prepare("SELECT d.institution_id FROM divisions d WHERE d.id=?");
    $check->bind_param("i", $unit['division_id']);
    $check->execute();
    $inst_data = $check->get_result()->fetch_assoc();
    $check->close();

    if($inst_data['institution_id'] != $user_institution_id){
        header("Location: units.php");
        exit;
    }
}


$unit_code = $unit['unit_code'];
$unit_name = $unit['unit_name'];
$unit_type = $unit['unit_type'];
$location  = $unit['location'];
$area_sqmt = $unit['area_sqmt'];
$division_id = $unit['division_id'];

// Fetch Institution 
$inst_stmt = $conn->prepare("
    SELECT i.institution_name 
    FROM institutions i
    JOIN divisions d ON i.id=d.institution_id
    WHERE d.id=?
");
$inst_stmt->bind_param("i", $division_id);
$inst_stmt->execute();
$inst_result = $inst_stmt->get_result()->fetch_assoc();
$display_inst_name = $inst_result['institution_name'] ?? 'N/A';
$inst_stmt->close();

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
        $check = $conn->prepare("SELECT id FROM units WHERE division_id=? AND (LOWER(unit_name)=LOWER(?) OR LOWER(unit_code)=LOWER(?)) AND id != ?");
        $check->bind_param("issi", $division_id, $unit_name, $unit_code, $unit_id);
        $check->execute();
        $dup = $check->get_result();

        if($dup->num_rows > 0){
            $error = "Unit code or name already exists in this division.";
        } else {
            $update = $conn->prepare("UPDATE units SET unit_code=?, unit_name=?, unit_type=?, location=?, area_sqmt=?, division_id=? WHERE id=?");
            $update->bind_param("ssssdii", $unit_code, $unit_name, $unit_type, $location, $area_sqmt, $division_id, $unit_id);

            if($update->execute()){
                $success = "Unit details updated successfully.";
            } else {
                $error = "Database error: Failed to update unit.";
            }
            $update->close();
        }
        $check->close();
    }
}
?>

<?php ob_start(); ?>

<div class="container-fluid py-3" style="max-width: 1200px;">
    <div class="card shadow-lg rounded-4 border-0 position-relative overflow-hidden main-card">
        
        <div class="edit-corner-flag"></div>

        <div class="card-body p-4 p-md-5">
            <div class="mb-4">
                <h4 class="fw-bold" style="color: #64b1ff;">
                    <i class="bi bi-pencil-square me-2"></i>Edit Lab/Facility
                </h4>
                <p class="text-muted small">ID: #<?= $unit_id ?> | Managing records for <strong><?= htmlspecialchars($unit_name) ?></strong></p>
            </div>

            <form method="POST" id="editUnitForm">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="small fw-bold text-muted mb-1">Institution</label>
                        <input type="text" class="form-control bg-light border-0 py-2" value="<?= htmlspecialchars($display_inst_name) ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="small fw-bold text-muted mb-1">Department</label>
                        <select name="division_id" class="form-select py-2" required>
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

                    <div class="col-md-4">
                        <label class="small fw-bold text-muted mb-1">Code</label>
                        <input type="text" name="unit_code" value="<?= htmlspecialchars($unit_code) ?>" class="form-control py-2" required>
                    </div>

                    <div class="col-md-8">
                        <label class="small fw-bold text-muted mb-1">Facility Name</label>
                        <input type="text" name="unit_name" value="<?= htmlspecialchars($unit_name) ?>" class="form-control py-2" required>
                    </div>

                    <div class="col-md-6">
                        <label class="small fw-bold text-muted mb-1">Facility Type</label>
                        <select name="unit_type" class="form-select py-2">
                            <?php 
                            $types = ['lab', 'office', 'store room', 'classroom', 'room', 'hod cabin', 'staffroom', 'library','other'];
                            foreach($types as $t): ?>
                                <option value="<?= $t ?>" <?= $unit_type == $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="small fw-bold text-muted mb-1">Area (Sq. Mt.)</label>
                        <input type="number" step="0.01" name="area_sqmt" value="<?= htmlspecialchars($area_sqmt) ?>" class="form-control py-2">
                    </div>

                    <div class="col-12">
                        <label class="small fw-bold text-muted mb-1">Location</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($location) ?>" class="form-control py-2" placeholder="e.g. Block A, 2nd Floor">
                    </div>
                </div>

                <div class="d-flex gap-3 mt-5">
                    <button type="submit" name="update_unit" class="btn text-white px-5 py-2 rounded-pill fw-bold shadow-sm" style="background-color: #64b1ff; border: none;">
                        <i class="bi bi-check-circle me-1"></i> Update Record
                    </button>
                    <a href="units.php" class="btn btn-light border px-5 py-2 rounded-pill fw-bold text-muted">
                        Back to List
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const GlassAlert = Swal.mixin({
        background: 'rgba(255, 255, 255, 0.9)',
        backdrop: `rgba(100, 177, 255, 0.12) blur(10px)`, // Glass blur effect
        customClass: {
            popup: 'glass-popup-style',
            confirmButton: 'rounded-pill px-5 py-2 fw-bold shadow-sm'
        },
        buttonsStyling: true
    });

    
    <?php if($success): ?>
        GlassAlert.fire({
            icon: 'success',
            iconColor: '#64b1ff',
            title: '<span style="color: #334155;">Success!</span>',
            html: '<p class="text-muted mb-0"><?= $success ?></p>',
            confirmButtonColor: '#64b1ff',
            timer: 3000,
            timerProgressBar: true,
            didClose: () => {
                window.location.href = 'units.php';
            }
        });
    <?php endif; ?>

    // Handle Error
    <?php if($error): ?>
        GlassAlert.fire({
            icon: 'error',
            iconColor: '#ef4444',
            title: '<span style="color: #334155;">Entry Error</span>',
            text: '<?= $error ?>',
            confirmButtonColor: '#ef4444'
        });
    <?php endif; ?>
});
</script>

<style>
.main-card {
    min-height: 80vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.edit-corner-flag {
    position: absolute;
    top: 0;
    right: 0;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 0 80px 80px 0;
    border-color: transparent #ef4444 transparent transparent;
    z-index: 10;
}

.edit-corner-flag::before {
    content: "EDIT";
    position: absolute;
    top: 10px;
    right: -75px;
    color: white;
    font-size: 0.75rem;
    font-weight: 900;
    transform: rotate(45deg);
    width: 80px;
    text-align: center;
    letter-spacing: 1.5px;
}

.form-control, .form-select {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    transition: all 0.25s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #64b1ff;
    box-shadow: 0 0 0 4px rgba(100, 177, 255, 0.15);
    background-color: #fff;
}


.glass-popup-style {
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08) !important;
    border-radius: 28px !important;
}

.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
}

.swal2-show {
    animation: swal2-show 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
}
</style>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>