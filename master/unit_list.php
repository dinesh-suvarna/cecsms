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

// Role filter - Logic Preserved
if($role !== 'SuperAdmin'){
    $where .= " AND i.id=? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

// Search filter - Logic Preserved
if(!empty($search)){
    $where .= " AND (u.unit_name LIKE ? OR u.location LIKE ? OR u.unit_code LIKE ?) ";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
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

// Updated SQL to include location and area_sqmt
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
        ORDER BY i.institution_name ASC, d.division_name ASC, u.unit_code ASC
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
    <div class="card shadow rounded-4 border-0">
        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0"><i class="bi bi-list-ul text-primary me-2"></i>Unit List</h5>
                <a href="unit_add.php" class="btn btn-primary rounded-pill px-3">
                    <i class="bi bi-plus-lg me-1"></i> Add Unit
                </a>
            </div>

            <form method="GET" class="mb-4">
                <div class="row g-2">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control bg-light border-start-0"
                                   placeholder="Search Unit, Code, or Location..."
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-dark w-100 rounded-3">Search</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Institution / Dept</th>
                            <th>Unit Info</th>
                            <th>Location</th>
                            <th>Area</th>
                            <th>Type</th>
                            <th width="120" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="fw-bold small"><?= htmlspecialchars($row['institution_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($row['division_name']) ?></div>
                            </td>
                            <td>
                                <div class="fw-bold text-primary"><?= htmlspecialchars($row['unit_code']) ?></div>
                                <div class="text-dark small"><?= htmlspecialchars($row['unit_name']) ?></div>
                            </td>
                            <td class="small text-muted">
                                <?= $row['location'] ? htmlspecialchars($row['location']) : '<span class="text-light-emphasis italic">Not Set</span>' ?>
                            </td>
                            <td class="small fw-medium">
                                <?= $row['area_sqmt'] ? $row['area_sqmt'] . " m²" : "-" ?>
                            </td>
                            <td>
                                <?php
                                $type = $row['unit_type'];
                                $badgeClass = match($type){
                                    'lab' => 'primary',
                                    'office' => 'dark',
                                    'store' => 'success',
                                    'room' => 'info',
                                    'classroom' => 'warning',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $badgeClass ?> bg-opacity-10 text-<?= $badgeClass ?> border border-<?= $badgeClass ?> rounded-pill px-2" style="font-size: 0.7rem;">
                                    <?= ucfirst(htmlspecialchars($type)) ?>
                                </span>
                            </td>

                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="edit_unit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning rounded-circle" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <?php $disabled = ($row['dispatch_count'] > 0); ?>
                                    <form method="POST" action="unit_delete.php" onsubmit="return confirm('Are you sure you want to delete this unit?');">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <?php if($disabled): ?>
                                            <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Used in <?= $row['dispatch_count'] ?> records">
                                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" disabled>
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </span>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle">
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
                            <td colspan="6" class="text-center py-5 text-muted">No units found.</td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <?php if($totalPages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center mt-3">
                    <?php for($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
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