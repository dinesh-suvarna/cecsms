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
// 1. Get TOTAL assets from Items Master
if ($role == 'SuperAdmin') {
    $total_q = "SELECT COUNT(*) as total FROM items_master WHERE status='Active'";
    $total_res = $conn->query($total_q);
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM items_master WHERE status='Active' AND institution_id=?");
    $stmt->bind_param("i", $user_institution_id);
    $stmt->execute();
    $total_res = $stmt->get_result();
}
$total_assets = $total_res->fetch_assoc()['total'] ?? 0;

// 2. Get DISPATCHED assets from Dispatch Master
if ($role == 'SuperAdmin') {
    $disp_q = "SELECT COUNT(*) as total FROM dispatch_master WHERE status='Active'";
    $disp_res = $conn->query($disp_q);
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM dispatch_master WHERE status='Active' AND institution_id=?");
    $stmt->bind_param("i", $user_institution_id);
    $stmt->execute();
    $disp_res = $stmt->get_result();
}
$dispatched_assets = $disp_res->fetch_assoc()['total'] ?? 0;

// 3. Calculate Percentage for Progress Bar
$avail_percent = ($total_assets > 0) ? round(($dispatched_assets / $total_assets) * 100) : 0;

?>
<?php ob_start(); ?>

<style>
    :root {
        --card-bg: #ffffff;
        --glass-bg: rgba(255, 255, 255, 0.7);
    }

    /* --- STATS CARDS --- */
    .stat-widget {
        background: var(--card-bg);
        border: 1px solid #eef2f6;
        border-radius: 20px;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .stat-widget:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.08);
        border-color: var(--accent-color);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        background: var(--soft-bg);
        color: var(--accent-color);
        margin-bottom: 1rem;
    }

    .stat-label {
        color: #64748b;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0.25rem 0;
    }

    /* --- QUICK ACTIONS --- */
    .action-card {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 1px dashed #cbd5e1;
        border-radius: 15px;
        padding: 1rem;
        text-align: center;
        text-decoration: none !important;
        color: #475569;
        transition: 0.2s;
    }

    .action-card:hover {
        background: #fff;
        border-style: solid;
        border-color: var(--primary-accent);
        color: var(--primary-accent);
    }

    /* --- ACCENT COLORS --- */
    .accent-green { --accent-color: #10b981; --soft-bg: rgba(16, 185, 129, 0.1); }
    .accent-blue { --accent-color: #3b82f6; --soft-bg: rgba(59, 130, 246, 0.1); }
    .accent-purple { --accent-color: #8b5cf6; --soft-bg: rgba(139, 92, 246, 0.1); }
    .accent-slate { --accent-color: #64748b; --soft-bg: rgba(100, 116, 139, 0.1); }

    .text-purple { color: #8b5cf6 !important; }
    .bg-purple-subtle { background-color: rgba(139, 92, 246, 0.1) !important; }
    .border-purple-subtle { border-color: rgba(139, 92, 246, 0.2) !important; }
</style>

<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h3 class="fw-800 mb-0">Master Dashboard</h3>
            <p class="text-muted mb-0">Overview of system entities and master stock logs.</p>
        </div>
        <span class="badge bg-white text-dark border py-2 px-3 rounded-pill shadow-sm">
            <i class="bi bi-calendar3 me-2 text-primary"></i> <?= date('F Y') ?>
        </span>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-sm-6">
            <div class="stat-widget accent-green shadow-sm">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon"><i class="bi bi-cpu-fill"></i></div>
                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">
                        <?= $avail_percent ?>% Dispatched
                    </span>
                </div>
                
                <div class="mt-2">
                    <div class="stat-label">Total Assets</div>
                    <div class="stat-value"><?= number_format($total_assets) ?></div>
                </div>

                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                        <span class="text-muted">In Stock: <?= ($total_assets - $dispatched_assets) ?></span>
                        <span class="text-dark fw-bold">Used: <?= $dispatched_assets ?></span>
                    </div>
                    <div class="progress" style="height: 6px; background-color: #e2e8f0;">
                        <div class="progress-bar bg-success" role="progressbar" 
                            style="width: <?= $avail_percent ?>%; border-radius: 10px;" 
                            aria-valuenow="<?= $avail_percent ?>" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                </div>
                
                <a href="items_master.php" class="stretched-link"></a>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="stat-widget accent-blue shadow-sm h-100 d-flex flex-column justify-content-between">
                
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon"><i class="bi bi-building-fill"></i></div>
                    <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle">
                        System Active
                    </span>
                </div>
                
                <div class="mt-2">
                    <div class="stat-label">Institutions</div>
                    <div class="stat-value"><?= number_format($total_institutions) ?></div>
                </div>

                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                        <span class="text-muted">Managed Entities</span>
                        <span class="text-dark fw-bold">Active</span>
                    </div>
                    <div class="progress" style="height: 6px; background-color: rgba(59, 130, 246, 0.05);">
                        <div class="progress-bar bg-primary" role="progressbar" 
                            style="width: 100%; opacity: 0.3; border-radius: 10px;">
                        </div>
                    </div>
                </div>
                
                <a href="institutions.php" class="stretched-link"></a>
            </div>
        </div>

       <div class="col-xl-3 col-sm-6">
            <div class="stat-widget accent-purple shadow-sm h-100 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon"><i class="bi bi-diagram-3-fill"></i></div>
                    <span class="badge rounded-pill bg-purple-subtle text-purple border border-purple-subtle" style="background-color: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                        Organization
                    </span>
                </div>
                
                <div class="mt-2">
                    <div class="stat-label">Total Departments</div>
                    <div class="stat-value"><?= number_format($total_divisions) ?></div>
                </div>

                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                        <span class="text-muted">Structural Units</span>
                        <span class="text-dark fw-bold">Active</span>
                    </div>
                    <div class="progress" style="height: 6px; background-color: rgba(139, 92, 246, 0.05);">
                        <div class="progress-bar" role="progressbar" 
                            style="width: 100%; background-color: #8b5cf6; opacity: 0.3; border-radius: 10px;">
                        </div>
                    </div>
                </div>
                <a href="divisions.php" class="stretched-link"></a>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="stat-widget accent-slate shadow-sm h-100 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon"><i class="bi bi-grid-3x3-gap-fill"></i></div>
                    <span class="badge rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle">
                        Facilities
                    </span>
                </div>
                
                <div class="mt-2">
                    <div class="stat-label">Total Labs & Facilities</div>
                    <div class="stat-value"><?= number_format($total_units) ?></div>
                </div>

                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                        <span class="text-muted">End-point Locations</span>
                        <span class="text-dark fw-bold">Tracked</span>
                    </div>
                    <div class="progress" style="height: 6px; background-color: rgba(100, 116, 139, 0.05);">
                        <div class="progress-bar" role="progressbar" 
                            style="width: 100%; background-color: #64748b; opacity: 0.3; border-radius: 10px;">
                        </div>
                    </div>
                </div>
                <a href="units.php" class="stretched-link"></a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h6 class="fw-bold mb-4"><i class="bi bi-lightning-charge-fill text-warning me-2"></i> Quick System Setup</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="items_master.php" class="action-card d-block">
                            <i class="bi bi-plus-circle mb-2 d-block fs-4"></i>
                            Asset Registry
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="institutions.php" class="action-card d-block">
                            <i class="bi bi-plus-square mb-2 d-block fs-4"></i>
                            Add Institution
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="divisions.php" class="action-card d-block">
                            <i class="bi bi-node-plus mb-2 d-block fs-4"></i>
                            Add Departments
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="units.php" class="action-card d-block">
                            <i class="bi bi-plus-lg mb-2 d-block fs-4"></i>
                            Add Labs and Facilities
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>