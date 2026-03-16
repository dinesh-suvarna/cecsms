<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

/* ===================== COUNTS ===================== */

// Institutions
if($role == 'SuperAdmin'){
    $inst_query = "SELECT COUNT(*) as total FROM institutions WHERE status='Active'";
    $inst_result = $conn->query($inst_query);
    $total_institutions = $inst_result->fetch_assoc()['total'];
} else {
    $total_institutions = 1;
}

// Divisions
if($role == 'SuperAdmin'){
    $div_query = "SELECT COUNT(*) as total FROM divisions WHERE status='Active'";
    $div_result = $conn->query($div_query);
} else {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM divisions 
        WHERE status='Active' 
        AND institution_id=?
    ");
    $stmt->bind_param("i", $user_institution_id);
    $stmt->execute();
    $div_result = $stmt->get_result();
}
$total_divisions = $div_result->fetch_assoc()['total'];

// Units
if($role == 'SuperAdmin'){
    $unit_query = "SELECT COUNT(*) as total FROM units WHERE status='Active'";
    $unit_result = $conn->query($unit_query);
} else {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM units u
        JOIN divisions d ON u.division_id=d.id
        WHERE u.status='Active'
        AND d.institution_id=?
    ");
    $stmt->bind_param("i", $user_institution_id);
    $stmt->execute();
    $unit_result = $stmt->get_result();
}
$total_units = $unit_result->fetch_assoc()['total'];

// Master Stock
if($role == 'SuperAdmin'){
    $stock_query = "SELECT COUNT(*) as total FROM dispatch_master WHERE status='Active'";
    $stock_result = $conn->query($stock_query);
} else {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM dispatch_master 
        WHERE status='Active' 
        AND institution_id=?
    ");
    $stmt->bind_param("i", $user_institution_id);
    $stmt->execute();
    $stock_result = $stmt->get_result();
}
$total_stock = $stock_result->fetch_assoc()['total'];

?>
<?php ob_start(); ?>

<style>
.dashboard-card {
    transition: 0.2s ease-in-out;
}
.dashboard-card:hover {
    transform: translateY(-5px);
}
</style>

<div class="container mt-4">

<h4 class="mb-4">Dashboard Overview</h4>

<div class="row g-4">

    <!-- Master Stock -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 rounded-4 dashboard-card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted">Master Stock</h6>
                    <h3 class="fw-bold"><?= $total_stock ?></h3>
                </div>
                <i class="bi bi-box-seam fs-1 text-success"></i>
            </div>
            <div class="card-footer bg-transparent border-0 text-center">
                <a href="manage_items_master.php" class="btn btn-sm btn-outline-success rounded-pill">
                    View
                </a>
            </div>
        </div>
    </div>

    <!-- Institutions -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 rounded-4 dashboard-card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted">Institutions</h6>
                    <h3 class="fw-bold"><?= $total_institutions ?></h3>
                </div>
                <i class="bi bi-building fs-1 text-primary"></i>
            </div>
            <div class="card-footer bg-transparent border-0 text-center">
                <a href="edit_delete_institute.php" class="btn btn-sm btn-outline-primary rounded-pill">
                    View
                </a>
            </div>
        </div>
    </div>

    <!-- Divisions -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 rounded-4 dashboard-card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted">Divisions</h6>
                    <h3 class="fw-bold"><?= $total_divisions ?></h3>
                </div>
                <i class="bi bi-diagram-3 fs-1 text-info"></i>
            </div>
            <div class="card-footer bg-transparent border-0 text-center">
                <a href="division_list.php" class="btn btn-sm btn-outline-info rounded-pill">
                    View
                </a>
            </div>
        </div>
    </div>

    <!-- Units -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 rounded-4 dashboard-card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted">Units</h6>
                    <h3 class="fw-bold"><?= $total_units ?></h3>
                </div>
                <i class="bi bi-grid fs-1 text-secondary"></i>
            </div>
            <div class="card-footer bg-transparent border-0 text-center">
                <a href="unit_list.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                    View
                </a>
            </div>
        </div>
    </div>

</div>
</div>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>