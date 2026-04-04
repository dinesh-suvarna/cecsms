<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;
$institution_filter = $_GET['institution_id'] ?? '';

/* ================= ADD DIVISION ================= */
$error = "";
$success = "";
$division_name = "";

if(isset($_POST['add_division'])){

    $institution_id = ($role == 'SuperAdmin') 
                        ? intval($_POST['institution_id']) 
                        : $user_institution_id;

    $division_name = ucwords(trim($_POST['division_name']));
    $division_type = $_POST['division_type'];

    if(empty($division_name)){
        $error = "Division name is required.";
    } elseif(empty($institution_id)){
        $error = "Institution is required.";
    } else {

        $check = $conn->prepare("
            SELECT id, status 
            FROM divisions 
            WHERE institution_id=? 
            AND LOWER(division_name)=LOWER(?)
        ");
        $check->bind_param("is", $institution_id, $division_name);
        $check->execute();
        $resultCheck = $check->get_result();

        if($resultCheck->num_rows > 0){
            $row = $resultCheck->fetch_assoc();

            if($row['status'] === 'Active'){
                $_SESSION['error'] = "Division already exists.";
                
            } else {
                $update = $conn->prepare("
                    UPDATE divisions 
                    SET status='Active', division_type=? 
                    WHERE id=?
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
    // REDIRECT TO SELF TO CLEAR POST DATA
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
// Extract messages from session, then clear them
$success = $_SESSION['success'] ?? "";
$error = $_SESSION['error'] ?? "";
unset($_SESSION['success'], $_SESSION['error']);

/* ================= FETCH LIST ================= */
$search = $_GET['search'] ?? '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 100;
$start  = ($page - 1) * $limit;

$params = [];
$types  = "";
$where  = " WHERE 1 ";

if($role !== 'SuperAdmin'){
    $where .= " AND d.institution_id=? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

if(!empty($search)){
    $where .= " AND d.division_name LIKE ? ";
    $params[] = "%$search%";
    $types .= "s";
}

if($role == 'SuperAdmin' && !empty($institution_filter)){
    $where .= " AND d.institution_id=? ";
    $params[] = $institution_filter;
    $types .= "i";
}

/* COUNT */
$countSql = "SELECT COUNT(*) as total FROM divisions d $where";
$stmt = $conn->prepare($countSql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRows = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

/* UPDATED DATA SQL with COUNT */
$sql = "SELECT d.*, i.institution_name,
        (SELECT COUNT(*) FROM divisions WHERE institution_id = d.institution_id AND status = 'Active') as dept_count
        FROM divisions d
        JOIN institutions i ON d.institution_id=i.id
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

$page_title = "Departments";
ob_start();
?>

<div class="row g-4">

<!-- LEFT: ADD -->
<div class="col-lg-4">
<div class="card shadow-sm rounded-4 border-0">
<div class="card-body p-4">

<h5 class="fw-bold mb-4">
<i class="bi bi-diagram-3 text-primary me-2"></i>Add Department
</h5>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

<?php if($role == 'SuperAdmin'): ?>
<div class="mb-3">
<label class="small fw-bold">Institution</label>
<select name="institution_id" class="form-select" required>
<option value="">Select</option>
<?php
$res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
while($row = $res->fetch_assoc()){
echo "<option value='{$row['id']}'>{$row['institution_name']}</option>";
}
?>
</select>
</div>
<?php else: ?>
<input type="hidden" name="institution_id" value="<?= $user_institution_id; ?>">
<?php endif; ?>

<div class="mb-3">
<label class="small fw-bold">Department Name</label>
<input type="text" name="division_name"
value="<?= htmlspecialchars($division_name) ?>"
class="form-control" required>
</div>

<div class="mb-4">
<label class="small fw-bold">Type</label>
<select name="division_type" class="form-select">
<option value="academic">Academic</option>
<option value="administrative">Administrative</option>
<option value="support">Support</option>
<option value="other">Other</option>
</select>
</div>

<button name="add_division" class="btn btn-primary w-100 rounded-pill">
Save Division
</button>

</form>

</div>
</div>
</div>

<!-- RIGHT: LIST -->


<div class="col-lg-8">
    <div class="card shadow-sm rounded-4 border-0 p-4 mb-3">
        <div class="d-flex justify-content-between mb-3 align-items-center">
            <h5 class="fw-bold m-0"><i class="bi bi-list-check text-primary me-2"></i>Department Directory</h5>
            
        </div>

        <form method="GET" class="mb-0">
            <div class="row g-2">
                <?php if($role == 'SuperAdmin'): ?>
                <div class="col-md-6">
                    <select name="institution_id" class="form-select border-0 bg-light" onchange="this.form.submit()">
                        <option value="">All Institutions</option>
                        <?php
                        $res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
                        while($irow = $res->fetch_assoc()){
                            $selected = ($institution_filter == $irow['id']) ? 'selected' : '';
                            echo "<option value='{$irow['id']}' $selected>{$irow['institution_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-3 flex-grow-1">
                    <input type="text" id="liveSearch" name="search" 
                    value="<?= htmlspecialchars($search) ?>" 
                    class="form-control border-0 bg-light" 
                    placeholder="search">
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-filter"></i>
                    </button>
                    <a href="divisions.php" class="btn btn-outline-secondary" title="Reset All">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="accordion accordion-flush shadow-sm rounded-4 overflow-hidden" id="deptAccordion">
    <?php 
    $current_institution = "";
    $first = true;

    if($result->num_rows > 0):
        while($row = $result->fetch_assoc()): 
            if ($current_institution !== $row['institution_name']): 
                if (!$first) echo '</tbody></table></div></div></div>'; 
                $current_institution = $row['institution_name'];
                $acc_id = "inst_" . $row['institution_id'];
                
                $show_class = (!empty($search)) ? 'show' : '';
$button_class = (!empty($search)) ? '' : 'collapsed';
    ?>
        <div class="accordion-item border-bottom">
        <h2 class="accordion-header">
            <button class="accordion-button <?= $button_class ?> fw-bold py-3 bg-white" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#<?= $acc_id ?>">
                <div class="d-flex justify-content-between align-items-center w-100 me-3">
                    <span>
                        <i class="bi bi-building me-2 text-primary"></i>
                        <?= htmlspecialchars($current_institution) ?>
                    </span>
                    <span class="badge rounded-pill bg-light text-muted border fw-normal" style="font-size: 0.7rem;">
                        <?= $row['dept_count'] ?> Depts
                    </span>
                </div>
            </button>
        </h2>
        <div id="<?= $acc_id ?>" 
             class="accordion-collapse collapse <?= $show_class ?>" 
             data-bs-parent="#deptAccordion">
            <div class="accordion-body p-0">
                    <table class="table table-hover align-middle mb-0 text-nowrap">
                        <thead class="bg-light">
                            <tr style="font-size: 11px; text-transform: uppercase;">
                                <th class="ps-4">Department</th>
                                <th>Type</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
        <?php 
                $first = false;
            endif; 
        ?>
            <tr>
                <td class="ps-4 fw-medium"><?= htmlspecialchars($row['division_name']) ?></td>
                <td><span class="badge bg-light text-primary border"><?= ucfirst($row['division_type']) ?></span></td>
                <td class="text-end pe-4">
                    <div class="btn-group shadow-sm rounded-pill overflow-hidden border">
                        <?php if($row['status'] == 'Active'): ?>
                            <a href="edit_division.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-white text-warning border-0"><i class="bi bi-pencil-square"></i></a>
                            <button onclick="deactivateDivision(<?= $row['id'] ?>)" class="btn btn-sm btn-white text-danger border-0"><i class="bi bi-trash"></i></button>
                        <?php else: ?>
                            <button onclick="restoreDivision(<?= $row['id'] ?>)" class="btn btn-sm btn-white text-success border-0"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
            </tbody></table></div></div></div> 
    <?php else: ?>
        <?php endif; ?>
</div>
</div>

<!-- PAGINATION -->
<?php if($totalPages > 1): ?>
<ul class="pagination justify-content-center mt-3">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<li class="page-item <?= ($i==$page)?'active':'' ?>">
<a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&institution_id=<?= urlencode($institution_filter) ?>">
    <?= $i ?>
</a>
<?= $i ?>
</a>
</li>
<?php endfor; ?>
</ul>
<?php endif; ?>

</div>
</div>

</div>
<form method="POST" id="deleteForm" action="division_delete.php">
<input type="hidden" name="id" id="delete_id">
</form>

<form method="POST" id="restoreForm" action="division_restore.php">
<input type="hidden" name="id" id="restore_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function deactivateDivision(id){
    Swal.fire({
        title: 'Deactivate Division?',
        text: "Division will be marked inactive.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, deactivate'
    }).then((result) => {
        if(result.isConfirmed){
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}

function restoreDivision(id){
    Swal.fire({
        title: 'Restore Division?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Yes, restore'
    }).then((result) => {
        if(result.isConfirmed){
            document.getElementById('restore_id').value = id;
            document.getElementById('restoreForm').submit();
        }
    });
}

document.getElementById('liveSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let accordionItems = document.querySelectorAll('#deptAccordion .accordion-item');

    accordionItems.forEach(item => {
        let rows = item.querySelectorAll('tbody tr');
        let hasVisibleRow = false;

        rows.forEach(row => {
            // Search inside the Department Name (1st column) and Type (2nd column)
            let deptName = row.cells[0].textContent.toLowerCase();
            let deptType = row.cells[1].textContent.toLowerCase();

            if (deptName.includes(filter) || deptType.includes(filter)) {
                row.style.display = ""; // Show row
                hasVisibleRow = true;
            } else {
                row.style.display = "none"; // Hide row
            }
        });

        // UI Logic: If an institution has matching departments, show it and expand it.
        // If not, hide the whole institution accordion item.
        if (hasVisibleRow) {
            item.style.display = "";
            if (filter.length > 0) {
                // Auto-expand the accordion if user is typing
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
<style>
    @media (min-width: 992px) {
    .col-lg-4 {
        position: sticky;
        top: 2rem;
        height: fit-content;
        z-index: 10;
    }
}
</style>


<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>