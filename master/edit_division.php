<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'] ?? null;
$user_institution_id = $_SESSION['institution_id'] ?? null;
$error = "";
$success = "";

// 1. Get token and decrypt it safely
$token = $_GET['token'] ?? null;
$division_id = $token ? decrypt_id($token) : false;

// 2. Security Check: If decryption fails or returns non-numeric ID
if (!$division_id || !is_numeric($division_id)) {
    header("Location: divisions.php");
    exit;
}

$division_id = intval($division_id);

// 3. Fetch division details
$stmt = $conn->prepare("
    SELECT * FROM divisions 
    WHERE id=? AND status='Active'
");
$stmt->bind_param("i", $division_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: divisions.php");
    exit;
}

$division = $result->fetch_assoc();
$stmt->close();

// Security: prevent editing other institution division
if ($role != 'SuperAdmin' && $division['institution_id'] != $user_institution_id) {
    header("Location: divisions.php");
    exit;
}

$division_name = $division['division_name'];
$division_type = $division['division_type'];

// ================= UPDATE LOGIC =================
if (isset($_POST['update_division'])) {

    $division_name = ucwords(trim($_POST['division_name']));
    $division_type = $_POST['division_type'];

    if (empty($division_name)) {
        $error = "Division name is required.";
    } else {

        // Duplicate check excluding current ID
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

        if ($dup->num_rows > 0) {
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

            if ($update->execute()) {
                $success = "Division updated successfully.";
            } else {
                $error = "Failed to update division.";
            }

            $update->close();
        }

        $check->close();
    }
}

// Fetch Institution Name
$inst = $conn->prepare("SELECT institution_name FROM institutions WHERE id=?");
$inst->bind_param("i", $division['institution_id']);
$inst->execute();
$inst_res = $inst->get_result()->fetch_assoc();
$inst_name = $inst_res['institution_name'] ?? 'N/A';
$inst->close();

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

.edit-division-page {
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

<div class="edit-division-page">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi bi-pencil-square"></i>
            </div>
            <div>
                <h3>Edit Department</h3>
                <p>Modify department organizational details and system classification.</p>
            </div>
        </div>
        <a href="divisions.php" class="btn btn-form-cancel px-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Directory
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
                <i class="bi bi-diagram-3-fill text-primary"></i> Edit Department Details
            </div>
        </div>

        <div class="inst-form-body">
            <form method="POST">

                <!-- Institution (Readonly) -->
                <div class="mb-3">
                    <label class="form-label">Institution</label>
                    <input type="text"
                           class="form-control"
                           value="<?= htmlspecialchars($inst_name) ?>"
                           readonly>
                </div>

                <!-- Division Name -->
                <div class="mb-3">
                    <label class="form-label">Department Name <span class="text-danger">*</span></label>
                    <input type="text"
                           name="division_name"
                           value="<?= htmlspecialchars($division_name) ?>"
                           class="form-control"
                           required>
                </div>

                <!-- Division Type -->
                <div class="mb-4">
                    <label class="form-label">Category Type <span class="text-danger">*</span></label>
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

                <!-- Actions -->
                <div class="d-flex justify-content-end gap-2">
                    <a href="divisions.php" class="btn btn-form-cancel px-4">
                        Cancel
                    </a>
                    <button type="submit" 
                            name="update_division" 
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