<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

$page_title = "Vendor Management";
$page_icon  = "bi-people-fill"; 
$success = false;
$error_msg = "";

// ✅ LOGIC UNCHANGED: Handle Add Vendor
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_vendor'])){
    $vendor_name = trim($_POST['vendor_name'] ?? '');

    if(!empty($vendor_name)){
        $check = $conn->prepare("SELECT id FROM vendors WHERE LOWER(vendor_name) = LOWER(?)");
        $check->bind_param("s", $vendor_name);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0) {
            $error_msg = "This vendor is already registered.";
        } else {
            $stmt = $conn->prepare("INSERT INTO vendors (vendor_name) VALUES (?)");
            $stmt->bind_param("s", $vendor_name);
            if($stmt->execute()){
                $success = true;
            }
            $stmt->close();
        }
        $check->close();
    }
}

/* ✅ LOGIC UNCHANGED: Fetch List */
$result = $conn->query("SELECT * FROM vendors ORDER BY id DESC");

ob_start();
?>

<div class="container-fluid animate-fade-in px-0">
    
    <div id="alert-container">
        <?php if($error_msg): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error_msg ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['error']) && $_GET['error'] == 'used'): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-3 py-3 mb-4">
                <i class="bi bi-exclamation-octagon-fill me-2"></i>
                <strong>Access Denied:</strong> Vendor is linked to services and cannot be deleted.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['success']) && $_GET['success'] == 'deleted'): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3 py-3 mb-4">
                <i class="bi bi-check-circle-fill me-2"></i> Vendor removed successfully.
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden sticky-top" style="top: 20px;">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-emerald-soft p-2 rounded-3 text-success">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-800 text-dark mb-0">Vendor Registry</h5>
                            <p class="text-muted small mb-0">Create a new provider entry</p>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-muted">Vendor Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 text-muted">
                                    <i class="bi bi-people-fill"></i>
                                </span>
                                <input type="text" name="vendor_name" 
                                       class="form-control rounded-3 border-light bg-light shadow-none p-3" 
                                       placeholder="e.g. Reliance Digital" 
                                       required 
                                       oninvalid="this.setCustomValidity('Please add vendor name')"
                                       oninput="this.setCustomValidity('')">
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="add_vendor" class="btn btn-success rounded-pill py-2 fw-bold shadow-sm">
                                <i class="bi bi-check-lg me-1"></i> Save Vendor
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="text-muted small mb-0">
                            <i class="bi bi-info-circle me-1"></i> 
                            New entries appear instantly in the list.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h6 class="fw-800 text-dark mb-0">Service Partners</h6>
            <p class="text-muted small mb-0">Authorized vendor directory</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-muted small text-uppercase fw-bold" style="font-size: 10px; letter-spacing: 0.5px;">
                        <th class="ps-4 py-3 text-nowrap" style="width: 110px;">REF ID</th>
                        <th class="py-3">Provider Name</th>
                        <th class="text-end pe-4 py-3">Management</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if ($result && $result->num_rows > 0): 
                        $i = 1;
                        while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge bg-light text-muted rounded-pill fw-normal">#<?= str_pad($i++, 2, '0', STR_PAD_LEFT); ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-emerald-soft text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="bi bi-person-vcard-fill"></i>
                                    </div>
                                    <span class="fw-bold text-dark small text-uppercase"><?= htmlspecialchars($row['vendor_name']); ?></span>
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <a href="delete_vendor.php?id=<?= $row['id']; ?>" 
                                   class="btn btn-outline-danger btn-sm rounded-pill border-0 px-3"
                                   onclick="return confirm('Remove this provider from the registry?');">
                                    <i class="bi bi-trash3 me-1"></i> <span style="font-size: 11px;">Remove</span>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="3" class="text-center py-5 text-muted small">No partners registered.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if($success): ?>
<script>
    Swal.fire({
        icon: 'success', title: 'Vendor Added!', text: 'Information saved to registry.',
        confirmButtonColor: '#10b981', timer: 2500, showConfirmButton: false
    }).then(() => { window.location = "vendor_manager.php"; });
</script>
<?php endif; ?>

<script>
    // ✅ AUTO-HIDE ALERTS AFTER 3 SECONDS
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(a => {
            a.style.transition = "opacity 0.6s ease";
            a.style.opacity = "0";
            setTimeout(() => a.remove(), 600);
        });
    }, 3000);
</script>

<style>
    .fw-800 { font-weight: 800 !important; }
    .bg-emerald-soft { background-color: rgba(16, 185, 129, 0.1) !important; }
    .input-group-text { border: 2px solid #f8fafc; border-right: none; }
    .form-control:focus { border-color: #10b981 !important; background: #fff !important; box-shadow: none; }
    .table tbody tr:hover { background-color: #f8fafc; }
</style>

<?php 
$content = ob_get_clean();
$conn->close();
include "layout.php"; 
?>