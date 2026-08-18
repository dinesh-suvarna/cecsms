<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;
$institution_filter = $_GET['institution_id'] ?? '';

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

/* ================= FETCH LIST & STATS ================= */
$search = $_GET['search'] ?? '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 100;
$start  = ($page - 1) * $limit;

$params = [];
$types  = "";
$where  = " WHERE 1 ";

if ($role !== 'SuperAdmin') {
    $where .= " AND d.institution_id = ? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

if (!empty($search)) {
    $where .= " AND d.division_name LIKE ? ";
    $params[] = "%$search%";
    $types .= "s";
}

if ($role == 'SuperAdmin' && !empty($institution_filter)) {
    $where .= " AND d.institution_id = ? ";
    $params[] = $institution_filter;
    $types .= "i";
}

/* COUNT */
$countSql = "SELECT COUNT(*) as total FROM divisions d $where";
$stmt = $conn->prepare($countSql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRows = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

/* DATA SQL */
$sql = "SELECT d.*, i.institution_name,
        (SELECT COUNT(*) FROM divisions WHERE institution_id = d.institution_id AND status = 'Active') as dept_count
        FROM divisions d
        JOIN institutions i ON d.institution_id = i.id
        $where
        ORDER BY i.institution_name, d.division_name
        LIMIT ?, ?";

$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$page_title = "Departments Directory";
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

/* BUTTONS */
.btn-erp-primary {
    background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .78rem; font-weight: 600; padding: 8px 14px;
}
.btn-erp-primary:hover { background: var(--erp-navy-dark); border-color: var(--erp-navy-dark); color: #fff; }

.btn-form-save {
    height: 39px; background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
}
.btn-form-save:hover { background: var(--erp-navy-dark); color: #fff; }

.btn-form-cancel {
    height: 39px; border: 1px solid #c8d2db; background: #fff;
    color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
}

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
.inst-form-body { padding: 18px; }
.inst-form-panel .form-label { color: #536575; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .045em; margin-bottom: 6px; }
.inst-form-panel .form-control, .inst-form-panel .form-select {
    height: 39px; border: 1px solid var(--erp-border); border-radius: 4px !important;
    color: var(--erp-text); background: #fff; font-size: .8rem;
}

/* TABLE TOOLBAR */
.inst-table-panel {
    background: var(--erp-white); border: 1px solid var(--erp-border); border-radius: 5px; overflow: hidden; box-shadow: var(--erp-shadow);
}
.inst-toolbar {
    display: flex; align-items: center; justify-content: space-between; gap: 15px; padding: 13px 16px; border-bottom: 1px solid var(--erp-border); background: #fff;
}
.inst-toolbar-title { color: var(--erp-text); font-size: .82rem; font-weight: 650; }
.inst-toolbar-count { color: var(--erp-muted); font-size: .7rem; font-weight: 500; }

.inst-filter-control { height: 34px; background: #f7f9fa; border: 1px solid var(--erp-border); font-size: .76rem; border-radius: 4px; }

/* ACCORDION ERP STYLE */
.accordion-item { border: 0; border-bottom: 1px solid var(--erp-border) !important; }
.accordion-button { background: #f7f9fa; color: var(--erp-navy-dark); font-size: .85rem; font-weight: 650; padding: 12px 18px; }
.accordion-button:not(.collapsed) { background: #edf3f8; color: var(--erp-navy); box-shadow: none; }

/* TABLES */
.inst-table { width: 100%; margin: 0; border-collapse: separate; border-spacing: 0; }
.inst-table thead th { background: #f7f9fa; color: #667786; border-bottom: 1px solid var(--erp-border); padding: 11px 14px; font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .045em; }
.inst-table tbody td { padding: 11px 14px; color: var(--erp-text); border-bottom: 1px solid #edf1f4; font-size: .78rem; vertical-align: middle; }
.inst-table tbody tr:hover { background: #f9fbfc; }

/* BADGES & STATUS */
.inst-status { display: inline-flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 650; }
.inst-status-dot { width: 7px; height: 7px; border-radius: 50%; }
.inst-status.active { color: var(--erp-green); }
.inst-status.active .inst-status-dot { background: var(--erp-green); }
.inst-status.inactive { color: var(--erp-red); }
.inst-status.inactive .inst-status-dot { background: var(--erp-red); }

/* ACTIONS */
.inst-actions { display: flex; justify-content: flex-end; gap: 5px; }
.inst-action { width: 31px; height: 29px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--erp-border); background: #fff; border-radius: 4px; font-size: .76rem; }
.inst-action-edit { color: var(--erp-blue); }
.inst-action-edit:hover { background: #edf5ff; border-color: #b9d5f7; }
.inst-action-delete { color: var(--erp-red); }
.inst-action-delete:hover { background: #fff1f1; border-color: #edc4c4; }
.inst-action-restore { color: var(--erp-green); }
.inst-action-restore:hover { background: #edf8f2; border-color: #b9dfcb; }

/* ALERTS & EMPTY */
.inst-alert { border-radius: 4px !important; font-size: .76rem; }
.inst-empty { padding: 55px 20px !important; text-align: center; color: var(--erp-muted); }

/* DARK MODE */
[data-bs-theme="dark"] {
    --erp-bg: #101a24; --erp-white: #172534; --erp-text: #edf3f7; --erp-muted: #9aabb9;
    --erp-border: #2d3e4e; --erp-border-light: #263847; --erp-navy: #8eafc9; --erp-navy-dark: #dce8f0;
}
[data-bs-theme="dark"] .inst-header h3 { color: #edf3f7; }
[data-bs-theme="dark"] .inst-header-icon { background: #203445; border-color: #33495a; color: #b8d0e2; }
[data-bs-theme="dark"] .inst-form-panel, [data-bs-theme="dark"] .inst-form-header { background: #142230; }
[data-bs-theme="dark"] .inst-table-panel, [data-bs-theme="dark"] .inst-toolbar { background: var(--erp-white); }
[data-bs-theme="dark"] .accordion-button { background: #142230; color: #edf3f7; }
[data-bs-theme="dark"] .accordion-button:not(.collapsed) { background: #1b2b3a; color: #8eafc9; }
[data-bs-theme="dark"] .inst-table thead th { background: #142230; color: #9aabb9; }
[data-bs-theme="dark"] .inst-table tbody tr:hover { background: #1b2b3a; }
[data-bs-theme="dark"] .inst-action, [data-bs-theme="dark"] .btn-form-cancel { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
[data-bs-theme="dark"] .inst-form-panel .form-control, [data-bs-theme="dark"] .inst-form-panel .form-select, [data-bs-theme="dark"] .inst-filter-control { background: #172534 !important; color: var(--erp-text); border-color: var(--erp-border); }
</style>

<div class="division-page">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi bi-diagram-3"></i>
            </div>
            <div>
                <h3>Department Directory</h3>
                <p>Manage organizational divisions, academic branches, and administrative units.</p>
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

                        <div class="<?= ($role == 'SuperAdmin') ? 'col-md-5' : 'col-md-7' ?>">
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

    <!-- MAIN DATA CONTAINER -->
    <div class="inst-table-panel">
        
        <!-- TOOLBAR & FILTERS -->
        <div class="inst-toolbar">
            <div>
                <span class="inst-toolbar-title">Department Directory Records</span>
                <span class="inst-toolbar-count ms-2"><?= number_format($totalRows) ?> total</span>
            </div>

            <form method="GET" class="m-0 d-flex gap-2 align-items-center">
                <?php if ($role == 'SuperAdmin'): ?>
                    <select name="institution_id" class="form-select inst-filter-control" onchange="this.form.submit()">
                        <option value="">All Institutions</option>
                        <?php
                        $res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
                        while ($irow = $res->fetch_assoc()) {
                            $selected = ($institution_filter == $irow['id']) ? 'selected' : '';
                            echo "<option value='{$irow['id']}' $selected>{$irow['institution_name']}</option>";
                        }
                        ?>
                    </select>
                <?php endif; ?>

                <div class="input-group">
                    <input type="text" id="liveSearch" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control inst-filter-control" placeholder="Search departments...">
                </div>

                <button type="submit" class="btn btn-sm btn-erp-primary" title="Filter"><i class="bi bi-filter"></i></button>
                <a href="divisions.php" class="btn btn-sm btn-form-cancel" title="Clear Filters"><i class="bi bi-arrow-clockwise"></i></a>
            </form>
        </div>

        <!-- ACCORDION CONTENT LIST -->
        <div class="accordion accordion-flush" id="deptAccordion">
            <?php 
            $current_institution = "";
            $first = true;

            if ($result->num_rows > 0):
                while ($row = $result->fetch_assoc()): 
                    $div_formatted = ucwords(strtolower($row['division_name']));
                    $formatted_division_name = str_replace(" And ", " and ", $div_formatted);

                    if ($current_institution !== $row['institution_name']): 
                        if (!$first) echo '</tbody></table></div></div></div>'; 
                        $current_institution = $row['institution_name'];
                        $acc_id = "inst_" . $row['institution_id'];
                        $show_class = (!empty($search)) ? 'show' : '';
                        $button_class = (!empty($search)) ? '' : 'collapsed';
            ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $button_class ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $acc_id ?>">
                            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                <span>
                                    <i class="bi bi-building me-2 text-primary"></i>
                                    <?= htmlspecialchars($current_institution) ?>
                                </span>
                                <span class="badge bg-light text-dark border px-2 py-1 fs-xs fw-normal">
                                    <?= $row['dept_count'] ?> Active
                                </span>
                            </div>
                        </button>
                    </h2>
                    <div id="<?= $acc_id ?>" class="accordion-collapse collapse <?= $show_class ?>" data-bs-parent="#deptAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="inst-table">
                                    <thead>
                                        <tr>
                                            <th>Department Name</th>
                                            <th style="width: 160px;">Type</th>
                                            <th style="width: 140px;">Status</th>
                                            <th class="text-end" style="width: 120px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                <?php 
                        $first = false;
                    endif; 
                ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($formatted_division_name) ?></td>
                        <td>
                            <span class="text-capitalize text-muted small"><?= htmlspecialchars($row['division_type']) ?></span>
                        </td>
                        <td>
                            <?php if ($row['status'] == 'Active'): ?>
                                <span class="inst-status active"><span class="inst-status-dot"></span> Active</span>
                            <?php else: ?>
                                <span class="inst-status inactive"><span class="inst-status-dot"></span> Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="inst-actions">
                                <?php if ($row['status'] == 'Active'): ?>
                                    <a href="<?= e_url('edit_division.php', $row['id']) ?>" class="inst-action inst-action-edit" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button onclick="deactivateDivision(<?= $row['id'] ?>)" class="inst-action inst-action-delete" title="Deactivate">
                                        <i class="bi bi-person-dash"></i>
                                    </button>
                                <?php else: ?>
                                    <button onclick="restoreDivision(<?= $row['id'] ?>)" class="inst-action inst-action-restore" title="Restore">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                    </tbody></table></div></div></div> 
            <?php else: ?>
                <div class="inst-empty">
                    <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary"></i>
                    <h5>No Departments Found</h5>
                    <p class="small m-0">Try adjusting your search criteria or target filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination pagination-sm m-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&institution_id=<?= urlencode($institution_filter) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>

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
        reverseButtons: true
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
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('restore_id').value = id;
            document.getElementById('restoreForm').submit();
        }
    });
}

// Live Search Script
document.getElementById('liveSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let accordionItems = document.querySelectorAll('#deptAccordion .accordion-item');

    accordionItems.forEach(item => {
        let rows = item.querySelectorAll('tbody tr');
        let hasVisibleRow = false;

        rows.forEach(row => {
            let deptName = row.cells[0].textContent.toLowerCase();
            let deptType = row.cells[1].textContent.toLowerCase();

            if (deptName.includes(filter) || deptType.includes(filter)) {
                row.style.display = "";
                hasVisibleRow = true;
            } else {
                row.style.display = "none";
            }
        });

        if (hasVisibleRow) {
            item.style.display = "";
            if (filter.length > 0) {
                const collapseElement = item.querySelector('.accordion-collapse');
                const bsCollapse = new bootstrap.Collapse(collapseElement, {toggle: false});
                bsCollapse.show();
            }
        } else {
            item.style.display = "none";
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>