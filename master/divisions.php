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

<!-- ALERT NOTIFICATIONS -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- PAGE HEADER & MAIN ACTION BUTTON -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold m-0">Department Directory</h3>
        <p class="text-muted small m-0">Manage organizational divisions, academic branches, and administrative units.</p>
    </div>
    <button class="btn btn-primary px-4 py-2 rounded-pill shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addDepartmentCollapse" aria-expanded="false" aria-controls="addDepartmentCollapse">
        <i class="bi bi-plus-lg me-1"></i> Add New Department
    </button>
</div>

<!-- COLLAPSIBLE ADD DEPARTMENT FORM (DRAWER EFFECT) -->
<div class="collapse mb-4 <?= (!empty($error)) ? 'show' : '' ?>" id="addDepartmentCollapse">
    <div class="card border-0 shadow-sm rounded-4 bg-light">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0 text-primary">
                    <i class="bi bi-diagram-3-fill me-2"></i>New Department Registration
                </h5>
                <button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#addDepartmentCollapse"></button>
            </div>
            <form method="POST" action="">
                <div class="row g-3">
                    <?php if ($role == 'SuperAdmin'): ?>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Target Institution <span class="text-danger">*</span></label>
                            <select name="institution_id" class="form-select bg-white" required>
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

                    <div class="<?= ($role == 'SuperAdmin') ? 'col-md-5' : 'col-md-8' ?>">
                        <label class="form-label small fw-bold">Department Name <span class="text-danger">*</span></label>
                        <input type="text" name="division_name" class="form-control bg-white" placeholder="e.g. Computer Science & Engineering" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Category Type</label>
                        <select name="division_type" class="form-select bg-white">
                            <option value="academic">Academic</option>
                            <option value="administrative">Administrative</option>
                            <option value="support">Support</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-toggle="collapse" data-bs-target="#addDepartmentCollapse">Cancel</button>
                    <button type="submit" name="add_division" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-check-lg me-1"></i> Save Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SEARCH AND FILTER TOOLBAR -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
    <form method="GET" class="m-0">
        <div class="row g-2 align-items-center">
            <?php if ($role == 'SuperAdmin'): ?>
                <div class="col-lg-4 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-building text-muted"></i></span>
                        <select name="institution_id" class="form-select border-0 bg-light" onchange="this.form.submit()">
                            <option value="">All Institutions</option>
                            <?php
                            $res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
                            while ($irow = $res->fetch_assoc()) {
                                $selected = ($institution_filter == $irow['id']) ? 'selected' : '';
                                echo "<option value='{$irow['id']}' $selected>{$irow['institution_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>

            <div class="col flex-grow-1">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="liveSearch" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control border-0 bg-light" placeholder="Type to filter instantly by department or type...">
                </div>
            </div>

            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary px-3" title="Apply Filter">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <a href="divisions.php" class="btn btn-outline-secondary" title="Reset Filters">
                    <i class="bi bi-arrow-clockwise"></i> Clear
                </a>
            </div>
        </div>
    </form>
</div>

<!-- DATA ACCORDION / LISTING -->
<div class="accordion accordion-flush shadow-sm rounded-4 overflow-hidden border-0" id="deptAccordion">
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
        <div class="accordion-item border-bottom">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $button_class ?> fw-bold py-3 bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $acc_id ?>">
                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                        <span class="fs-6 text-dark">
                            <i class="bi bi-building me-2 text-primary"></i>
                            <?= htmlspecialchars($current_institution) ?>
                        </span>
                        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-1">
                            <?= $row['dept_count'] ?> Departments
                        </span>
                    </div>
                </button>
            </h2>
            <div id="<?= $acc_id ?>" class="accordion-collapse collapse <?= $show_class ?>" data-bs-parent="#deptAccordion">
                <div class="accordion-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="bg-light">
                                <tr class="text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <th class="ps-4 py-3">Department Name</th>
                                    <th class="py-3">Type</th>
                                    <th class="py-3">Status</th>
                                    <th class="text-end pe-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
        <?php 
                $first = false;
            endif; 
        ?>
            <tr>
                <td class="ps-4 fw-semibold text-dark"><?= htmlspecialchars($formatted_division_name) ?></td>
                <td>
                    <?php 
                        $badge_bg = match(strtolower($row['division_type'])) {
                            'academic' => 'bg-info-subtle text-info border-info-subtle',
                            'administrative' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                            'support' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                            default => 'bg-light text-dark border'
                        };
                    ?>
                    <span class="badge <?= $badge_bg ?> border px-2 py-1 text-capitalize"><?= htmlspecialchars($row['division_type']) ?></span>
                </td>
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
                            <a href="edit_division.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light text-primary border-0 px-3" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <button onclick="deactivateDivision(<?= $row['id'] ?>)" class="btn btn-sm btn-light text-danger border-0 px-3" title="Deactivate">
                                <i class="bi bi-trash"></i>
                            </button>
                        <?php else: ?>
                            <button onclick="restoreDivision(<?= $row['id'] ?>)" class="btn btn-sm btn-light text-success border-0 px-3" title="Restore">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
            </tbody></table></div></div></div> 
    <?php else: ?>
        <div class="p-5 text-center text-muted bg-white">
            <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary"></i>
            <h5>No Departments Found</h5>
            <p class="small m-0">Try adjusting your filters or query to find what you are looking for.</p>
        </div>
    <?php endif; ?>
</div>

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center mt-4">
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-md shadow-sm rounded-pill overflow-hidden">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link border-0" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&institution_id=<?= urlencode($institution_filter) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
<?php endif; ?>

<!-- HIDDEN FORMS FOR ACTIONS -->
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
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, deactivate'
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
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, restore'
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