<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

/* ================= ADD DIVISION ================= */
$error = "";
$success = "";
$division_name = "";

if (isset($_POST['add_division'])) {
    $institution_id = ($role == 'SuperAdmin') 
                        ? intval($_POST['institution_id']) 
                        : $user_institution_id;

    $division_name = ucwords(trim($_POST['division_name']));
    $division_type = $_POST['division_type'];

    if (empty($division_name)) {
        $_SESSION['error'] = "Division name is required.";
    } elseif (empty($institution_id)) {
        $_SESSION['error'] = "Institution is required.";
    } else {
        $check = $conn->prepare("
            SELECT id, status 
            FROM divisions 
            WHERE institution_id = ? 
            AND LOWER(division_name) = LOWER(?)
        ");
        $check->bind_param("is", $institution_id, $division_name);
        $check->execute();
        $resultCheck = $check->get_result();

        if ($resultCheck->num_rows > 0) {
            $row = $resultCheck->fetch_assoc();

            if ($row['status'] === 'Active') {
                $_SESSION['error'] = "Division already exists.";
            } else {
                $update = $conn->prepare("
                    UPDATE divisions 
                    SET status = 'Active', division_type = ? 
                    WHERE id = ?
                ");
                $update->bind_param("si", $division_type, $row['id']);
                $update->execute();
                $_SESSION['success'] = "Division restored successfully.";
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO divisions 
                (institution_id, division_name, division_type, status) 
                VALUES (?, ?, ?, 'Active')
            ");
            $stmt->bind_param("iss", $institution_id, $division_name, $division_type);
            $stmt->execute();
            $_SESSION['success'] = "Division added successfully.";
            $division_name = "";
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$success = $_SESSION['success'] ?? "";
$error = $_SESSION['error'] ?? "";
unset($_SESSION['success'], $_SESSION['error']);

/* ================= FETCH DATA & STATS ================= */
$params = [];
$types  = "";
$where  = " WHERE 1 ";

if ($role !== 'SuperAdmin') {
    $where .= " AND d.institution_id = ? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

/* FETCH ALL DIVISIONS DIRECTLY WITHOUT PAGINATION OR ACCORDION ACCORDING TO INSTITUTION DESIGN */
$sql = "SELECT d.*, i.institution_name
        FROM divisions d
        JOIN institutions i ON d.institution_id = i.id
        $where
        ORDER BY d.division_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$page_title = "Departments Directory";
ob_start();
?>

<style>
/* =========================================================
   ENTERPRISE ERP STYLES (MATCHED TO INSTITUTIONS DIRECTORY)
========================================================= */

:root {
    --erp-navy: #173f63;
    --erp-navy-dark: #102f4a;
    --erp-blue: #0d6efd;
    --erp-text: #263746;
    --erp-muted: #71808f;
    --erp-border: #dce3e9;
    --erp-border-light: #e9eef2;
    --erp-bg: #f5f7f9;
    --erp-white: #ffffff;
    --erp-green: #287a55;
    --erp-red: #a74747;
    --erp-shadow: 0 1px 3px rgba(20, 40, 60, .06);
}

.division-page {
    max-width: 1500px;
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

/* PRIMARY BUTTON */
.btn-erp-primary {
    background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .78rem; font-weight: 600; padding: 8px 14px;
    box-shadow: none !important;
}
.btn-erp-primary:hover, .btn-erp-primary:focus {
    background: var(--erp-navy-dark); border-color: var(--erp-navy-dark); color: #fff;
}

/* SUMMARY BAR */
.inst-summary {
    display: flex; align-items: stretch; background: var(--erp-white);
    border: 1px solid var(--erp-border); border-radius: 5px; margin-bottom: 18px;
    box-shadow: var(--erp-shadow); overflow: hidden;
}
.inst-summary-item { min-width: 190px; padding: 13px 18px; border-right: 1px solid var(--erp-border-light); }
.inst-summary-item:last-child { border-right: 0; }
.inst-summary-label { color: var(--erp-muted); font-size: .65rem; font-weight: 600; text-transform: uppercase; letter-spacing: .045em; margin-bottom: 3px; }
.inst-summary-value { color: var(--erp-text); font-size: 1.05rem; font-weight: 700; }
.inst-summary-value.active { color: var(--erp-green); }

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
.inst-form-title i { color: var(--erp-blue); }
.inst-form-body { padding: 18px; }

.inst-form-panel .form-label { color: #536575; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .045em; margin-bottom: 6px; }
.inst-form-panel .form-control, .inst-form-panel .form-select {
    height: 39px; border: 1px solid var(--erp-border); border-radius: 4px !important;
    color: var(--erp-text); background: #fff; font-size: .8rem; box-shadow: none !important;
}
.inst-form-panel .form-control:focus, .inst-form-panel .form-select:focus {
    border-color: var(--erp-blue); box-shadow: 0 0 0 2px rgba(13,110,253,.08) !important;
}

.btn-form-save {
    height: 39px; background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
}
.btn-form-save:hover { background: var(--erp-navy-dark); border-color: var(--erp-navy-dark); color: #fff; }

.btn-form-cancel {
    height: 39px; border: 1px solid var(--erp-border-dark, #c8d2db); background: #fff;
    color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
}

/* TABLE TOOLBAR */
.inst-table-panel {
    background: var(--erp-white); border: 1px solid var(--erp-border); border-radius: 5px;
    overflow: hidden; box-shadow: var(--erp-shadow);
}
.inst-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 15px; padding: 13px 16px; border-bottom: 1px solid var(--erp-border); background: #fff;
}
.inst-toolbar-title { color: var(--erp-text); font-size: .82rem; font-weight: 650; }
.inst-toolbar-count { color: var(--erp-muted); font-size: .7rem; font-weight: 500; }

.inst-search { width: 280px; }
.inst-search .input-group-text {
    background: #f7f9fa; border: 1px solid var(--erp-border); border-right: 0;
    color: #82909c; font-size: .78rem;
}
.inst-search .form-control {
    height: 34px; background: #f7f9fa; border: 1px solid var(--erp-border); border-left: 0;
    font-size: .76rem; box-shadow: none;
}
.inst-search .form-control:focus { background: #fff; border-color: var(--erp-blue); box-shadow: none; }

/* TABLE */
.inst-table { width: 100%; margin: 0; border-collapse: separate; border-spacing: 0; }
.inst-table thead th {
    background: #f7f9fa; color: #667786; border-bottom: 1px solid var(--erp-border);
    padding: 11px 14px; font-size: .64rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .045em; white-space: nowrap;
}
.inst-table tbody td {
    padding: 13px 14px; color: var(--erp-text); border-bottom: 1px solid #edf1f4;
    font-size: .78rem; vertical-align: middle;
}
.inst-table tbody tr:last-child td { border-bottom: 0; }
.inst-table tbody tr { transition: background .12s ease; }
.inst-table tbody tr:hover { background: #f9fbfc; }

.inst-index { color: #8a98a5; font-size: .72rem; font-weight: 600; }

.inst-identity { display: flex; align-items: center; gap: 11px; min-width: 250px; }
.inst-avatar {
    width: 34px; height: 34px; flex: 0 0 34px; display: flex; align-items: center;
    justify-content: center; background: #edf3f8; border: 1px solid #d9e5ed;
    border-radius: 4px; color: var(--erp-navy); font-size: .85rem;
}
.inst-name { color: var(--erp-text); font-weight: 600; font-size: 0.92rem !important; line-height: 1.35; }

/* STATUS */
.inst-status { display: inline-flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 650; }
.inst-status-dot { width: 7px; height: 7px; border-radius: 50%; }
.inst-status.active { color: var(--erp-green); }
.inst-status.active .inst-status-dot { background: var(--erp-green); }
.inst-status.inactive { color: var(--erp-red); }
.inst-status.inactive .inst-status-dot { background: var(--erp-red); }

/* ACTIONS */
.inst-actions { display: flex; justify-content: flex-end; gap: 5px; }
.inst-action {
    width: 31px; height: 29px; display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--erp-border); background: #fff; border-radius: 4px;
    font-size: .76rem; transition: all .15s ease;
}
.inst-action-edit { color: var(--erp-blue); }
.inst-action-edit:hover { background: #edf5ff; border-color: #b9d5f7; color: #0a58ca; }
.inst-action-delete { color: var(--erp-red); }
.inst-action-delete:hover { background: #fff1f1; border-color: #edc4c4; color: #8f3838; }
.inst-action-restore { color: var(--erp-green); }
.inst-action-restore:hover { background: #edf8f2; border-color: #b9dfcb; color: #216646; }

/* EMPTY STATE */
.inst-empty { padding: 55px 20px !important; text-align: center; color: var(--erp-muted); }
.inst-empty-icon {
    width: 48px; height: 48px; margin: 0 auto 12px; display: flex; align-items: center;
    justify-content: center; background: #f1f4f6; border: 1px solid var(--erp-border);
    border-radius: 5px; color: #8493a0; font-size: 1.2rem;
}
.inst-empty h5 { color: var(--erp-text); font-size: .85rem; font-weight: 650; margin-bottom: 4px; }
.inst-empty p { font-size: .73rem; margin: 0; }

/* ALERTS */
.inst-alert { border-radius: 4px !important; border-width: 1px; font-size: .76rem; box-shadow: none !important; }

/* DARK MODE */
[data-bs-theme="dark"] {
    --erp-bg: #101a24; --erp-white: #172534; --erp-text: #edf3f7; --erp-muted: #9aabb9;
    --erp-border: #2d3e4e; --erp-border-light: #263847; --erp-navy: #8eafc9; --erp-navy-dark: #dce8f0;
}
[data-bs-theme="dark"] .inst-header h3 { color: #edf3f7; }
[data-bs-theme="dark"] .inst-header-icon, [data-bs-theme="dark"] .inst-avatar { background: #203445; border-color: #33495a; color: #b8d0e2; }
[data-bs-theme="dark"] .inst-summary, [data-bs-theme="dark"] .inst-table-panel { background: var(--erp-white); }
[data-bs-theme="dark"] .inst-summary-item { border-color: var(--erp-border); }
[data-bs-theme="dark"] .inst-form-panel, [data-bs-theme="dark"] .inst-form-header { background: #142230; }
[data-bs-theme="dark"] .inst-form-panel .form-control, [data-bs-theme="dark"] .inst-form-panel .form-select, [data-bs-theme="dark"] .inst-search .form-control { background: #172534 !important; color: var(--erp-text); border-color: var(--erp-border); }
[data-bs-theme="dark"] .inst-table thead th { background: #142230; color: #9aabb9; }
[data-bs-theme="dark"] .inst-table tbody tr:hover { background: #1b2b3a; }
[data-bs-theme="dark"] .inst-action, [data-bs-theme="dark"] .btn-form-cancel { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
[data-bs-theme="dark"] .inst-search .input-group-text { background: #172534; border-color: var(--erp-border); }

/* RESPONSIVE */
@media (max-width: 991.98px) {
    .division-page { padding: 20px; }
    .inst-summary-item { flex: 1; min-width: 0; }
    .inst-search { width: 230px; }
}
@media (max-width: 767.98px) {
    .division-page { padding: 18px 14px 30px; }
    .inst-header { align-items: flex-start; flex-direction: column; }
    .inst-header .btn-erp-primary { width: 100%; }
    .inst-summary { flex-direction: column; }
    .inst-summary-item { border-right: 0; border-bottom: 1px solid var(--erp-border-light); }
    .inst-summary-item:last-child { border-bottom: 0; }
    .inst-toolbar { align-items: stretch; flex-direction: column; }
    .inst-search { width: 100%; }
    .inst-table { min-width: 700px; }
}
</style>

<div class="division-page">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div>
                <h3>Departments Directory</h3>
                <p>Manage institutional departments, academic branches, and administrative units.</p>
            </div>
        </div>

        <button class="btn btn-erp-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addDepartmentCollapse" aria-expanded="false" aria-controls="addDepartmentCollapse">
            <i class="bi bi-plus-lg me-2"></i> Add Department
        </button>
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

    <!-- SUMMARY METRICS BAR -->
    <?php
    $totalDepartments = $result->num_rows;
    $activeDepartments = 0;

    if ($result->num_rows > 0) {
        $result->data_seek(0);
        while ($summaryRow = $result->fetch_assoc()) {
            if ($summaryRow['status'] === 'Active') {
                $activeDepartments++;
            }
        }
        $result->data_seek(0);
    }
    $inactiveDepartments = $totalDepartments - $activeDepartments;
    ?>

    <div class="inst-summary">
        <div class="inst-summary-item">
            <div class="inst-summary-label">Total Departments</div>
            <div class="inst-summary-value"><?= inr($totalDepartments) ?></div>
        </div>
        <div class="inst-summary-item">
            <div class="inst-summary-label">Active</div>
            <div class="inst-summary-value active"><?= inr($activeDepartments) ?></div>
        </div>
        <div class="inst-summary-item">
            <div class="inst-summary-label">Inactive</div>
            <div class="inst-summary-value"><?= inr($inactiveDepartments) ?></div>
        </div>
    </div>

    <!-- ADD FORM PANEL -->
    <div class="collapse mb-3 <?= (!empty($error)) ? 'show' : '' ?>" id="addDepartmentCollapse">
        <div class="inst-form-panel">
            <div class="inst-form-header">
                <div class="inst-form-title">
                    <i class="bi bi-diagram-3-fill"></i> New Department Registration
                </div>
                <button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#addDepartmentCollapse"></button>
            </div>
            <div class="inst-form-body">
                <form method="POST" action="">
                    <div class="row g-3 align-items-end">
                        <?php if ($role == 'SuperAdmin'): ?>
                            <div class="col-md-4">
                                <label class="form-label">Target Institution <span class="text-danger">*</span></label>
                                <select name="institution_id" class="form-select" required>
                                    <option value="">Select Institution</option>
                                    <?php
                                    $res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
                                    while ($row = $res->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>{$row['institution_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="institution_id" value="<?= $user_institution_id; ?>">
                        <?php endif; ?>

                        <div class="<?= ($role == 'SuperAdmin') ? 'col-md-5' : 'col-md-6' ?>">
                            <label class="form-label">Department Name <span class="text-danger">*</span></label>
                            <input type="text" name="division_name" class="form-control" placeholder="e.g. Computer Science & Engineering" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Category Type</label>
                            <select name="division_type" class="form-select">
                                <option value="academic">Academic</option>
                                <option value="administrative">Administrative</option>
                                <option value="support">Support</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-form-cancel px-4" data-bs-toggle="collapse" data-bs-target="#addDepartmentCollapse">Cancel</button>
                            <button type="submit" name="add_division" class="btn btn-form-save px-4">
                                <i class="bi bi-check-lg me-1"></i> Save Department
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MAIN DATA TABLE -->
    <div class="inst-table-panel">

        <div class="inst-toolbar">
            <div>
                <span class="inst-toolbar-title">Department Records</span>
                <span class="inst-toolbar-count ms-2"><?= inr($totalDepartments) ?> records</span>
            </div>

            <div class="inst-search">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="search" class="form-control" placeholder="Search departments...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="inst-table" id="tbl">
                <thead>
                    <tr>
                        <th style="width: 70px;">#</th>
                        <th>Department</th>
                        <!-- <?php if ($role == 'SuperAdmin'): ?>
                            <th>Institution</th>
                        <?php endif; ?> -->
                        <th style="width: 160px;">Type</th>
                        <th style="width: 140px;">Status</th>
                        <th class="text-end" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php 
                        $i = 1;
                        while ($row = $result->fetch_assoc()):
                            $div_formatted = ucwords(strtolower($row['division_name']));
                            $formatted_division_name = str_replace(" And ", " and ", $div_formatted);
                        ?>
                            <tr>
                                <!-- INDEX -->
                                <td>
                                    <span class="inst-index"><?= $i++ ?></span>
                                </td>

                                <!-- DEPARTMENT IDENTITY -->
                                <td>
                                    <div class="inst-identity">
                                        <!-- <div class="inst-avatar">
                                            <i class="bi bi-diagram-3"></i>
                                        </div> -->
                                        <div class="inst-name">
                                            <?= htmlspecialchars($formatted_division_name) ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- INSTITUTION (VISIBLE TO SUPERADMIN) -->
                                <!-- <?php if ($role == 'SuperAdmin'): ?>
                                    <td>
                                        <span class="text-secondary small fw-medium"><?= htmlspecialchars($row['institution_name']) ?></span>
                                    </td>
                                <?php endif; ?> -->

                                <!-- TYPE -->
                                <td>
                                    <span class="text-capitalize text-muted small fw-medium"><?= htmlspecialchars($row['division_type']) ?></span>
                                </td>

                                <!-- STATUS -->
                                <td>
                                    <?php if ($row['status'] == 'Active'): ?>
                                        <span class="inst-status active"><span class="inst-status-dot"></span> Active</span>
                                    <?php else: ?>
                                        <span class="inst-status inactive"><span class="inst-status-dot"></span> Inactive</span>
                                    <?php endif; ?>
                                </td>

                                <!-- ACTIONS -->
                                <td>
                                    <div class="inst-actions">
                                        <?php if ($row['status'] == 'Active'): ?>
                                            <a href="<?= e_url('edit_division.php', $row['id']) ?>" class="inst-action inst-action-edit" title="Edit department">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <button type="button" onclick="deactivateDivision(<?= $row['id'] ?>)" class="inst-action inst-action-delete" title="Deactivate department">
                                                <i class="bi bi-person-dash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" onclick="restoreDivision(<?= $row['id'] ?>)" class="inst-action inst-action-restore" title="Restore department">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= ($role == 'SuperAdmin') ? '6' : '5' ?>" class="inst-empty">
                                <div class="inst-empty-icon">
                                    <i class="bi bi-folder-x"></i>
                                </div>
                                <h5>No Departments Found</h5>
                                <p>Add a department to begin managing organizational records.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- ACTION FORMS -->
<form method="POST" id="deleteForm" action="division_delete.php">
    <input type="hidden" name="id" id="delete_id">
</form>

<form method="POST" id="restoreForm" action="division_restore.php">
    <input type="hidden" name="id" id="restore_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function deactivateDivision(id) {
    Swal.fire({
        title: 'Deactivate Division?',
        text: "This division will be marked as inactive.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#a74747',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Deactivate',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        customClass: { popup: 'shadow' }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}

function restoreDivision(id) {
    Swal.fire({
        title: 'Restore Division?',
        text: "This division will be re-activated.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#287a55',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Restore',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        customClass: { popup: 'shadow' }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('restore_id').value = id;
            document.getElementById('restoreForm').submit();
        }
    });
}

/* LIVE SEARCH */
const searchInput = document.getElementById('search');
if (searchInput) {
    searchInput.addEventListener('input', function () {
        const filter = this.value.trim().toLowerCase();
        document.querySelectorAll('#tbl tbody tr').forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
}
</script>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>