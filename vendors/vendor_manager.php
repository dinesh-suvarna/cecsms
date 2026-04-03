<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

// 1. Get the category from URL, default to 'Computer'
$category_type = $_GET['type'] ?? 'Computer'; 

$page_title = $category_type . " Vendor Management";
$page_icon  = "bi-people-fill"; 
$success_msg = "";
$error_msg = "";

// ✅ HANDLE ADD VENDOR 
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_vendor'])) {
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    if (!empty($vendor_name)) {
        $check = $conn->prepare("SELECT id FROM vendors WHERE LOWER(vendor_name) = LOWER(?) AND category = ?");
        $check->bind_param("ss", $vendor_name, $category_type);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error_msg = "This vendor is already registered for " . $category_type;
        } else {
            $stmt = $conn->prepare("INSERT INTO vendors (vendor_name, category) VALUES (?, ?)");
            $stmt->bind_param("ss", $vendor_name, $category_type);
            if ($stmt->execute()) $success_msg = "Vendor added to $category_type successfully!";
            $stmt->close();
        }
        $check->close();
    }
}

// ✅ HANDLE EDIT VENDOR
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_vendor'])) {
    $v_id = $_POST['vendor_id'];
    $v_name = trim($_POST['vendor_name']);
    
    if (!empty($v_name)) {
        $stmt = $conn->prepare("UPDATE vendors SET vendor_name = ? WHERE id = ?");
        $stmt->bind_param("si", $v_name, $v_id);
        if ($stmt->execute()) $success_msg = "Vendor updated successfully!";
        else $error_msg = "Update failed.";
        $stmt->close();
    }
}

// ✅ FETCH ONLY VENDORS FOR THIS CATEGORY 
// REMOVED: The old $result = $conn->query(...) line that was here.
$stmt = $conn->prepare("SELECT * FROM vendors WHERE category = ? ORDER BY id DESC");
$stmt->bind_param("s", $category_type);
$stmt->execute();
$result = $stmt->get_result();

ob_start();
?>

<div class="container-fluid px-0">
    
    <?php if($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 mb-4 fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <?php if($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3 py-3 mb-4 fade show">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden sticky-top" style="top: 20px; z-index: 10;">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-emerald-soft p-2 rounded-3 text-success">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-800 text-dark mb-0"><?= $category_type ?> Registry</h5>
                            <p class="text-muted small mb-0">Create a new <?= strtolower($category_type) ?> provider</p>
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
                                <input type="text" name="vendor_name" class="form-control rounded-3 border-light bg-light shadow-none p-3" placeholder="e.g. Reliance Digital" required>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="add_vendor" class="btn btn-success rounded-pill py-2 fw-bold shadow-sm">
                                <i class="bi bi-check-lg me-1"></i> Save Vendor
                            </button>
                        </div>
                    </form>
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
                                <th class="ps-4 py-3" style="width: 80px;">REF ID</th>
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
                                        <button class="btn btn-outline-primary btn-sm rounded-pill border-0 px-3 me-1" 
                                                onclick='openEditModal(<?= $row["id"]; ?>, <?= json_encode($row["vendor_name"]); ?>)'>
                                            <i class="bi bi-pencil-square me-1"></i> <span style="font-size: 11px;">Edit</span>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm rounded-pill border-0 px-3 delete-vendor-btn" 
                                                data-id="<?= $row['id']; ?>" 
                                                data-name="<?= htmlspecialchars($row['vendor_name']); ?>">
                                            <i class="bi bi-trash3 me-1"></i> <span style="font-size: 11px;">Remove</span>
                                        </button>
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

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-800 mb-0" id="editModalLabel">Update Vendor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="vendor_id" id="edit_vendor_id">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Vendor Name</label>
                        <input type="text" name="vendor_name" id="edit_vendor_name" class="form-control rounded-3 border-light bg-light p-3 shadow-none" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_vendor" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let editModalInstance;

    function openEditModal(id, name) {
        document.getElementById('edit_vendor_id').value = id;
        document.getElementById('edit_vendor_name').value = name;
        
        const modalEl = document.getElementById('editModal');
        document.body.appendChild(modalEl);
        
        if (!editModalInstance) {
            editModalInstance = new bootstrap.Modal(modalEl);
        }
        editModalInstance.show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // --- 1. MODAL BACKDROP FIX ---
        const modalEl = document.getElementById('editModal');
        modalEl.addEventListener('hidden.bs.modal', function () {
            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            document.body.style.overflow = 'auto';
        });

        // --- 2. AUTO-HIDE ALERTS ---
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(a => {
                const bsAlert = new bootstrap.Alert(a);
                bsAlert.close();
            });
        }, 3000);

        // --- 3. DELETE CONFIRMATION ---
        document.querySelectorAll('.delete-vendor-btn').forEach(button => {
            button.addEventListener('click', function() {
                const vendorId = this.getAttribute('data-id');
                const vendorName = this.getAttribute('data-name');
                const currentType = "<?= $category_type ?>"; // Injects Computer, Furniture, etc.

                Swal.fire({
                    title: 'Are you sure?',
                    text: `Removing "${vendorName}" will delete them from the registry.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, delete it!',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `delete_vendor.php?id=${vendorId}&type=${currentType}`;
                    }
                });
            });
        });

        // --- 4. HANDLE FEEDBACK & SMART URL CLEANUP ---
        const urlParams = new URLSearchParams(window.location.search);
        
        // Handle Errors
        if (urlParams.has('error')) {
            const errorType = urlParams.get('error');
            let errorText = "System error: Could not complete deletion.";
            if (errorType === 'used_in_services') errorText = "This vendor is linked to existing service/bill records.";
            if (errorType === 'used_in_stock') errorText = "This vendor is linked to items currently in your stock/inventory.";

            Swal.fire({
                title: 'Action Denied',
                text: errorText + " Please reassign or remove those records first.",
                icon: 'error',
                confirmButtonColor: '#10b981'
            });
        }

        // Handle Success
        if (urlParams.has('success')) {
            Swal.fire({
                title: 'Deleted!',
                text: 'Vendor has been removed.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }

        // --- THE FIX: SMART URL CLEANUP ---
        if (urlParams.has('error') || urlParams.has('success')) {
            const cleanParams = new URLSearchParams(window.location.search);
            cleanParams.delete('success');
            cleanParams.delete('error');
            
            // Rebuild the URL string (this keeps ?type=Furniture if it exists)
            const newRelativePathQuery = window.location.pathname + (cleanParams.toString() ? '?' + cleanParams.toString() : '');
            window.history.replaceState({}, document.title, newRelativePathQuery);
        }
    });
</script>

<style>
    .fw-800 { font-weight: 800 !important; }
    .bg-emerald-soft { background-color: rgba(16, 185, 129, 0.1) !important; }
    .table tbody tr:hover { background-color: #f8fafc; }
    /* Ensure the modal is always above the sidebar */
    .modal { z-index: 2050 !important; }
    .modal-backdrop { z-index: 2040 !important; }
</style>

<?php 
$content = ob_get_clean();
include "../admin/adminlayout.php"; 
?>