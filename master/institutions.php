<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";
require_once "../admin/auth.php";
requireRole([ROLE_SUPERADMIN]);

$page_title = "Institutions Directory";
$page_icon  = "bi-building";

/* ================= ADD ================= */
$success = false;
$error = "";

if (isset($_POST['submit'])) {
    $name = trim($_POST['institution_name']);

    if (empty($name)) {
        $error = "Institution name is required.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO institutions (institution_name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $success = true;
        } catch (mysqli_sql_exception $e) {
            $error = ($e->getCode() == 1062) ? "Institution already exists!" : "Database error";
        }
    }
}

/* ================= DELETE ================= */
if (isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("UPDATE institutions SET status='Inactive' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: institutions.php"); exit;
}

/* ================= RESTORE ================= */
if (isset($_POST['restore_id'])) {
    $id = intval($_POST['restore_id']);
    $stmt = $conn->prepare("UPDATE institutions SET status='Active' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: institutions.php"); exit;
}

/* ================= UPDATE ================= */
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $new_name = trim($_POST['institution_name']);

    $check = $conn->prepare("SELECT id FROM institutions WHERE institution_name=? AND id!=?");
    $check->bind_param("si", $new_name, $id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Institute name already exists!";
    } else {
        $stmt = $conn->prepare("UPDATE institutions SET institution_name=? WHERE id=?");
        $stmt->bind_param("si", $new_name, $id);
        $stmt->execute();
        header("Location: institutions.php"); exit;
    }
}

/* ================= EDIT FETCH ================= */
$editData = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM institutions WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

/* ================= FETCH ALL ================= */
$result = $conn->query("SELECT * FROM institutions ORDER BY created_at DESC");

ob_start();
?>

<!-- ALERT NOTIFICATIONS -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>Institution added successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- PAGE HEADER & ACTION BUTTON -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold m-0"><i class="<?= $page_icon ?> text-primary me-2"></i><?= $page_title ?></h3>
        <p class="text-muted small m-0">Manage multi-tenant campus entities, status, and organization names.</p>
    </div>
    <?php if (!$editData): ?>
        <button class="btn btn-primary px-4 py-2 rounded-pill shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addInstitutionCollapse" aria-expanded="false" aria-controls="addInstitutionCollapse">
            <i class="bi bi-plus-lg me-1"></i> Add Institution
        </button>
    <?php endif; ?>
</div>

<!-- COLLAPSIBLE EDIT FORM DRAWER (TRIGGERED VIA GET) -->
<?php if ($editData): ?>
<div class="card border-0 shadow-sm rounded-4 bg-light mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0 text-warning-emphasis">
                <i class="bi bi-pencil-square me-2"></i>Edit Institution
            </h5>
            <a href="institutions.php" class="btn-close"></a>
        </div>
        <form method="POST" action="institutions.php">
            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label class="form-label small fw-bold text-muted">Institution Name <span class="text-danger">*</span></label>
                    <input type="text" name="institution_name" class="form-control bg-white" value="<?= htmlspecialchars($editData['institution_name']) ?>" required>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" name="update" class="btn btn-warning w-100 rounded-pill text-white fw-bold">
                        <i class="bi bi-check-lg me-1"></i> Update
                    </button>
                    <a href="institutions.php" class="btn btn-outline-secondary rounded-pill px-3">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- COLLAPSIBLE ADD FORM DRAWER -->
<div class="collapse mb-4 <?= (!empty($error) && !$editData) ? 'show' : '' ?>" id="addInstitutionCollapse">
    <div class="card border-0 shadow-sm rounded-4 bg-light">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0 text-primary">
                    <i class="bi bi-building-add me-2"></i>New Institution Registration
                </h5>
                <button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#addInstitutionCollapse"></button>
            </div>
            <form method="POST" action="">
                <div class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label small fw-bold text-muted">Institution Name <span class="text-danger">*</span></label>
                        <input type="text" name="institution_name" class="form-control bg-white" placeholder="e.g. Canara Engineeering College" required>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary rounded-pill w-100" data-bs-toggle="collapse" data-bs-target="#addInstitutionCollapse">Cancel</button>
                        <button type="submit" name="submit" class="btn btn-primary rounded-pill w-100">
                            <i class="bi bi-check-lg me-1"></i> Save
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SEARCH & FILTER TOOLBAR -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
    <div class="row g-2 align-items-center">
        <div class="col flex-grow-1">
            <div class="input-group">
                <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="search" class="form-control border-0 bg-light" placeholder="Type to filter institutions instantly...">
            </div>
        </div>
    </div>
</div>

<!-- DATA TABLE CARD -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-nowrap" id="tbl">
            <thead class="bg-light">
                <tr class="text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <th class="ps-4 py-3" style="width: 70px;">#</th>
                    <th class="py-3">Institution Name</th>
                    <th class="py-3">Status</th>
                    <th class="text-end pe-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 text-muted fw-bold"><?= $i++ ?></td>
                            <td class="fw-semibold text-dark name"><?= htmlspecialchars($row['institution_name']) ?></td>
                            <td>
                                <?php if ($row['status'] == 'Active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-dot"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-dot"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group shadow-sm rounded-pill overflow-hidden border bg-white">
                                    <?php if ($row['status'] == 'Active'): ?>
                                        <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-light text-primary border-0 px-3" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button class="btn btn-sm btn-light text-danger border-0 px-3" onclick="deleteInstitute(<?= $row['id'] ?>)" title="Deactivate">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light text-success border-0 px-3" onclick="restoreInstitute(<?= $row['id'] ?>)" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="p-5 text-center text-muted bg-white">
                            <i class="bi bi-building-x fs-1 d-block mb-2 text-secondary"></i>
                            <h5>No Institutions Found</h5>
                            <p class="small m-0">Start by adding a new institution using the button above.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- HIDDEN ACTION FORMS -->
<form method="POST" id="deleteForm">
    <input type="hidden" name="delete_id" id="delete_id">
</form>

<form method="POST" id="restoreForm">
    <input type="hidden" name="restore_id" id="restore_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function deleteInstitute(id) {
    Swal.fire({
        title: 'Deactivate Institution?',
        text: 'This will set the institution status to inactive. You can restore it later.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, deactivate'
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}

function restoreInstitute(id) {
    Swal.fire({
        title: 'Restore Institution?',
        text: 'Re-activate this institution.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, restore'
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('restore_id').value = id;
            document.getElementById('restoreForm').submit();
        }
    });
}

// LIVE SEARCH
document.getElementById('search').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    document.querySelectorAll("#tbl tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>

<?php
$main_content = ob_get_clean();
include "../master/masterlayout.php";
?>