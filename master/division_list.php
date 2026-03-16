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

/* ================= BUILD QUERY ================= */
$params = [];
$types  = "";
$where  = " WHERE d.status='Active' ";

// Role filter
if($role !== 'SuperAdmin'){
    $where .= " AND d.institution_id=? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

// Search filter
if(!empty($search)){
    $where .= " AND d.division_name LIKE ? ";
    $params[] = "%$search%";
    $types .= "s";
}

// Count total records
$countSql = "SELECT COUNT(*) as total
             FROM divisions d
             JOIN institutions i ON d.institution_id=i.id
             $where";

$stmt = $conn->prepare($countSql);
if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$totalRows   = $totalResult['total'];
$totalPages  = ceil($totalRows / $limit);

// Fetch divisions
$sql = "SELECT d.*, i.institution_name,
               COALESCE(du.dispatch_count,0) AS dispatch_count,
               COALESCE(uu.units_count,0) AS units_count
        FROM divisions d
        JOIN institutions i ON d.institution_id=i.id
        LEFT JOIN (
            SELECT division_id, COUNT(*) AS dispatch_count
            FROM dispatch_master
            GROUP BY division_id
        ) du ON d.id = du.division_id
        LEFT JOIN (
            SELECT division_id, COUNT(*) AS units_count
            FROM units
            GROUP BY division_id
        ) uu ON d.id = uu.division_id
        $where
        ORDER BY i.institution_name, d.division_name
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
    <h5 class="mb-0">Division List</h5>
    <a href="division_add.php" class="btn btn-primary btn-sm">
        + Add Division
    </a>
</div>

<!-- SEARCH -->
<form method="GET" class="mb-3">
<div class="row g-2">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control"
               placeholder="Search Division..."
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
    <th>Division Name</th>
    <th>Type</th>
    <th width="200">Action</th>
</tr>
</thead>
<tbody>

<?php if($result->num_rows > 0): ?>
<?php while($row = $result->fetch_assoc()): ?>

<tr>
    <td><?= htmlspecialchars($row['institution_name']) ?></td>
    <td><?= htmlspecialchars($row['division_name']) ?></td>

    <td>
        <?php
        $type = $row['division_type'];
        $badgeClass = match($type){
            'academic' => 'primary',
            'administrative' => 'dark',
            'support' => 'success',
            default => 'secondary'
        };
        ?>
        <span class="badge bg-<?= $badgeClass ?>">
            <?= ucfirst(htmlspecialchars($type)) ?>
        </span>
    </td>

    <td>
        <!-- VIEW BUTTON -->
        <a href="view_divisions.php?id=<?= $row['id'] ?>" 
           class="btn btn-sm btn-info" title="View Division">
            View
        </a>

        <!-- EDIT BUTTON -->
        <a href="edit_division.php?id=<?= $row['id'] ?>" 
           class="btn btn-sm btn-warning">
           Edit
        </a>

        <!-- DELETE BUTTON -->
        <?php 
$disabled = ($row['dispatch_count'] > 0 || $row['units_count'] > 0);
?>

<form method="POST" action="division_delete.php"
      style="display:inline-block;"
      onsubmit="return confirm('Are you sure you want to delete this division?');">

    <input type="hidden" name="id" value="<?= $row['id'] ?>">

    <?php if($disabled): ?>
        
        <span class="d-inline-block"
              tabindex="0"
              data-bs-toggle="tooltip"
              title="Cannot delete. Dispatch: <?= $row['dispatch_count'] ?> | Units: <?= $row['units_count'] ?>">
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
            Delete
        </button>

    <?php endif; ?>

</form>

    </td>
</tr>

<?php endwhile; ?>
<?php else: ?>
<tr>
    <td colspan="4" class="text-center text-muted">
        No divisions found.
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