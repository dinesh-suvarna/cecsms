<?php
require_once __DIR__ . "/../config/db.php";
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

// Security check
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

ob_start();
?>

<style>
/* =========================================================
   ENTERPRISE ERP STYLES
========================================================= */
:root {
    --erp-navy: #173f63;
    --erp-navy-dark: #102f4a;
    --erp-blue: #0d6efd;
    --erp-text: #263746;
    --erp-muted: #71808f;
    --erp-border: #dce3e9;
    --erp-bg: #f5f7f9;
    --erp-white: #ffffff;
    --erp-shadow: 0 1px 3px rgba(20, 40, 60, .06);
}

.edit-unit-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 26px 30px 40px;
}

/* HEADER */
.inst-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding-bottom: 20px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--erp-border);
}
.inst-header-left { display: flex; align-items: center; gap: 14px; }
.inst-header-icon {
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    background: #edf3f8; border: 1px solid #dce6ee; border-radius: 5px;
    color: var(--erp-navy); font-size: 1.1rem;
}
.inst-header h3 { margin: 0; color: var(--erp-navy-dark); font-size: 1.18rem; font-weight: 650; }
.inst-header p { margin: 3px 0 0; color: var(--erp-muted); font-size: .76rem; }

/* FORM PANEL */
.inst-form-panel {
    background: #f9fafb; border: 1px solid var(--erp-border); border-radius: 5px;
    margin-bottom: 18px; box-shadow: var(--erp-shadow);
}
.inst-form-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 13px 18px; border-bottom: 1px solid var(--erp-border); background: #f5f7f9;
}
.inst-form-title { display: flex; align-items: center; gap: 8px; color: var(--erp-navy-dark); font-size: .82rem; font-weight: 650; }
.inst-form-body { padding: 22px; }

.inst-form-panel .form-label { 
    color: #536575; font-size: .65rem; font-weight: 700; 
    text-transform: uppercase; letter-spacing: .045em; margin-bottom: 6px; 
}

.inst-form-panel .form-control, 
.inst-form-panel .form-select {
    height: 39px; border: 1px solid var(--erp-border); border-radius: 4px !important;
    color: var(--erp-text); background: #fff; font-size: .8rem;
    box-shadow: none !important;
}

.inst-form-panel .form-control[readonly] {
    background: #eef2f5 !important;
    color: #617382;
}

/* BUTTONS */
.btn-form-save {
    height: 39px; background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
}
.btn-form-save:hover { background: var(--erp-navy-dark); border-color: var(--erp-navy-dark); color: #fff; }

.btn-form-cancel {
    height: 39px; border: 1px solid #c8d2db; background: #fff;
    color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-form-cancel:hover { background: #f5f7f9; color: #334451; }

.inst-alert { border-radius: 4px !important; font-size: .76rem; }

/* DARK MODE */
[data-bs-theme="dark"] {
    --erp-bg: #101a24; --erp-white: #172534; --erp-text: #edf3f7; --erp-muted: #9aabb9;
    --erp-border: #2d3e4e; --erp-navy: #8eafc9; --erp-navy-dark: #dce8f0;
}
[data-bs-theme="dark"] .inst-header h3 { color: #edf3f7; }
[data-bs-theme="dark"] .inst-header-icon { background: #203445; border-color: #33495a; color: #b8d0e2; }
[data-bs-theme="dark"] .inst-form-panel, [data-bs-theme="dark"] .inst-form-header { background: #142230; }
[data-bs-theme="dark"] .inst-form-panel .form-control, 
[data-bs-theme="dark"] .inst-form-panel .form-select { 
    background: #172534 !important; color: var(--erp-text); border-color: var(--erp-border); 
}
[data-bs-theme="dark"] .inst-form-panel .form-control[readonly] { background: #0f1923 !important; color: #7f93a4; }
[data-bs-theme="dark"] .btn-form-cancel { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
</style>

<div class="edit-unit-page">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi bi-pencil-square"></i>
            </div>
            <div>
                <h3>Edit Facility Details</h3>
                <p>ID: #<?= $unit_id ?> | Managing configuration for <strong><?= htmlspecialchars($unit_name) ?></strong></p>
            </div>
        </div>
        <a href="units.php" class="btn btn-form-cancel px-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Facilities
        </a>
    </div>

    <!-- ALERTS -->
    <?php if ($success): ?>
        <div class="alert alert-success inst-alert alert-dismissible fade show mb-3">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger inst-alert alert-dismissible fade show mb-3">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- EDIT FORM PANEL -->
    <div class="inst-form-panel">
        <div class="inst-form-header">
            <div class="inst-form-title">
                <i class="bi bi-door-open-fill text-primary"></i> Edit Facility Specifications
            </div>
        </div>

        <div class="inst-form-body">
            <form method="POST">
                <div class="row g-3">
                    
                    <!-- Institution (Readonly) -->
                    <div class="col-md-6">
                        <label class="form-label">Institution</label>
                        <input type="text" 
                               class="form-control" 
                               value="<?= htmlspecialchars($display_inst_name) ?>" 
                               readonly>
                    </div>

                    <!-- Department -->
                    <div class="col-md-6">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
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

                    <!-- Facility Code -->
                    <div class="col-md-4">
                        <label class="form-label">Facility Code <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="unit_code" 
                               value="<?= htmlspecialchars($unit_code) ?>" 
                               class="form-control" 
                               placeholder="e.g. CSL01"
                               required>
                    </div>

                    <!-- Facility Name -->
                    <div class="col-md-8">
                        <label class="form-label">Facility Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="unit_name" 
                               value="<?= htmlspecialchars($unit_name) ?>" 
                               class="form-control" 
                               placeholder="e.g. Advanced AI Lab"
                               required>
                    </div>

                    <!-- Facility Type -->
                    <div class="col-md-6">
                        <label class="form-label">Facility Type <span class="text-danger">*</span></label>
                        <select name="unit_type" class="form-select" required>
                            <?php 
                            $types = ['lab', 'office', 'store room', 'classroom', 'room', 'hod cabin', 'staffroom', 'library', 'other'];
                            foreach($types as $t): ?>
                                <option value="<?= $t ?>" <?= $unit_type == $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Area -->
                    <div class="col-md-6">
                        <label class="form-label">Area (Sq. Mt.)</label>
                        <input type="number" 
                               step="0.01" 
                               name="area_sqmt" 
                               value="<?= htmlspecialchars($area_sqmt) ?>" 
                               class="form-control"
                               placeholder="e.g. 120.50">
                    </div>

                    <!-- Location -->
                    <div class="col-12">
                        <label class="form-label">Location</label>
                        <input type="text" 
                               name="location" 
                               value="<?= htmlspecialchars($location) ?>" 
                               class="form-control" 
                               placeholder="e.g. Block A, 2nd Floor">
                    </div>

                </div>

                <!-- Actions -->
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="units.php" class="btn btn-form-cancel px-4">
                        Cancel
                    </a>
                    <button type="submit" 
                            name="update_unit" 
                            class="btn btn-form-save px-4">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>