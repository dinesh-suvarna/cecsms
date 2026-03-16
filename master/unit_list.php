<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

/* ================= SEARCH + PAGINATION ================= */

$search = $_GET['search'] ?? '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = 5;
$start  = ($page - 1) * $limit;

$params = [];
$types  = "";
$where  = " WHERE u.status='Active' ";

// Role filter
if($role !== 'SuperAdmin'){
    $where .= " AND i.id=? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

// Search filter
if(!empty($search)){
    $where .= " AND u.unit_name LIKE ? ";
    $params[] = "%$search%";
    $types .= "s";
}

/* ================= COUNT TOTAL ================= */

$countSql = "SELECT COUNT(*) as total
             FROM units u
             JOIN divisions d ON u.division_id=d.id
             JOIN institutions i ON d.institution_id=i.id
             $where";

$stmt = $conn->prepare($countSql);
if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRows  = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

/* ================= FETCH DATA ================= */

$sql = "SELECT u.*, 
               d.division_name, 
               i.institution_name,
               COALESCE(dm.dispatch_count,0) AS dispatch_count
        FROM units u
        JOIN divisions d ON u.division_id=d.id
        JOIN institutions i ON d.institution_id=i.id

        LEFT JOIN (
            SELECT unit_id, COUNT(*) AS dispatch_count
            FROM dispatch_master
            GROUP BY unit_id
        ) dm ON u.id = dm.unit_id

        $where
        ORDER BY i.institution_name, d.division_name, u.unit_name
        LIMIT ?, ?";

$params[] = $start;
$params[] = $limit;
$types   .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<?php ob_start(); ?>

<div class="container mt-4">
<div class="card shadow rounded-4">
<div class="card-body">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Unit List</h5>
    <a href="unit_add.php" class="btn btn-primary btn-sm">
        + Add Unit
    </a>
</div>

<!-- SEARCH -->
<form method="GET" class="mb-3">
<div class="row g-2">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control"
               placeholder="Search Unit..."
               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100">Search</button>
    </div>
</div>
</form>

<div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
<thead class="table-light">
<tr>
    <th>Institution</th>
    <th>Division</th>
    <th>Unit Name</th>
    <th>Type</th>
    <th width="120">Action</th>
</tr>
</thead>
<tbody>

<?php if($result->num_rows > 0): ?>
<?php while($row = $result->fetch_assoc()): ?>

<tr>
    <td><?= htmlspecialchars($row['institution_name']) ?></td>
    <td><?= htmlspecialchars($row['division_name']) ?></td>
    <td><?= htmlspecialchars($row['unit_name']) ?></td>

    <td>
        <?php
        $type = $row['unit_type'];

        $badgeClass = match($type){
            'lab' => 'primary',
            'office' => 'dark',
            'store' => 'success',
            'room' => 'info',
            default => 'secondary'
        };
        ?>

        <span class="badge bg-<?= $badgeClass ?>">
            <?= ucfirst(htmlspecialchars($type)) ?>
        </span>
    </td>

   <td>
    <div class="d-flex gap-2">

        <!-- EDIT BUTTON -->
        <a href="edit_unit.php?id=<?= $row['id'] ?>" 
           class="btn btn-sm btn-warning">
            Edit
        </a>

        <?php $disabled = ($row['dispatch_count'] > 0); ?>

        <form method="POST" action="unit_delete.php"
              onsubmit="return confirm('Are you sure you want to delete this unit?');">

            <input type="hidden" name="id" value="<?= $row['id'] ?>">

            <?php if($disabled): ?>

                <span class="d-inline-block"
                      tabindex="0"
                      data-bs-toggle="tooltip"
                      title="Cannot delete. Used in <?= $row['dispatch_count'] ?> dispatch records">

                    <button type="button"
                            class="btn btn-sm btn-secondary"
                            disabled
                            style="pointer-events: none;">
                        Delete
                    </button>

                </span>

            <?php else: ?>

                <button type="submit"
                        class="btn btn-sm btn-danger">
                    <i class="bi bi-trash"></i>
                </button>

            <?php endif; ?>

        </form>

    </div>
</td>
</tr>

<?php endwhile; ?>
<?php else: ?>

<tr>
    <td colspan="5" class="text-center text-muted">
        No units found.
    </td>
</tr>

<?php endif; ?>

</tbody>
</table>
</div>

<!-- PAGINATION -->
<?php if($totalPages > 1): ?>
<nav>
<ul class="pagination justify-content-center mt-3">

<?php for($i = 1; $i <= $totalPages; $i++): ?>
<li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
    <a class="page-link"
       href="?page=<?= $i ?>&search=<?= urlencode($_GET['search'] ?? '') ?>">
       <?= $i ?>
    </a>
</li>
<?php endfor; ?>

</ul>
</nav>
<?php endif; ?>

</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>