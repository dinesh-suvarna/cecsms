<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

$page_title = "Vendor Directory";
$page_icon  = "bi-people-fill";

/* Fetch Vendors */
$result = $conn->query("SELECT * FROM vendors ORDER BY id DESC");

/* ✅ START BUFFER TO PREVENT "JUMPING TO BOTTOM" */
ob_start();
?>

<div class="container-fluid animate-fade-in px-0">
    
    <div id="alert-container">
        <?php if(isset($_GET['error']) && $_GET['error'] == 'used'): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 mb-4">
                <i class="bi bi-exclamation-octagon-fill me-2"></i>
                <strong>Access Denied:</strong> This vendor is currently linked to service records and cannot be deleted.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['success']) && $_GET['success'] == 'deleted'): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3 py-3 mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                Vendor record removed successfully.
            </div>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 px-1">
        <h6 class="fw-bold text-muted mb-0"><i class="bi bi-list-ul me-2"></i>Registered Vendors</h6>
        <a href="add_vendor.php" class="btn btn-dark btn-sm rounded-pill px-3 shadow-sm fw-bold">
            <i class="bi bi-plus-lg me-1"></i> Add New Vendor
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">
                        <th class="ps-4 py-3" style="width: 80px;">#</th>
                        <th>Vendor Identity</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if ($result && $result->num_rows > 0): 
                        $i = 1;
                        while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?= $i++; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-emerald-soft text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                        <i class="bi bi-building small"></i>
                                    </div>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($row['vendor_name']); ?></span>
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <a href="delete_vendor.php?id=<?= $row['id']; ?>" 
                                   class="btn btn-outline-danger btn-sm rounded-circle border-0 p-2"
                                   onclick="return confirm('Are you sure? This action cannot be undone.');"
                                   title="Delete Vendor">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No vendors found in the database.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.transition = "opacity 0.6s ease";
            alert.style.opacity = "0";
            setTimeout(() => alert.remove(), 600);
        });
    }, 3000); // 3000ms = 3 seconds
</script>

<style>
    .fw-bold { font-weight: 700 !important; }
    .bg-emerald-soft { background-color: rgba(16, 185, 129, 0.1) !important; }
    .table tbody tr:hover { background-color: #f8fafc; }
    .btn-outline-danger:hover { background-color: #fff1f2; color: #e11d48; }
</style>

<?php 
/* ✅ STORE CONTENT & LOAD LAYOUT */
$content = ob_get_clean();
include "layout.php"; 
?>