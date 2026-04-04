<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

$success_msg = "";
$error_msg = "";

if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}

$delete_status = $_GET['status'] ?? '';

$category_type = $_GET['type'] ?? 'Computer'; 
$page_title = $category_type . " Vendor Management";

// --- ADD / UPDATE LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_vendor'])) {
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $assigned_category = (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin' && isset($_POST['category'])) ? $_POST['category'] : $category_type;
    $edit_id = $_POST['vendor_id'] ?? '';

    if (!empty($vendor_name)) {
        if (!empty($edit_id)) {
            // UPDATE LOGIC
            $stmt = $conn->prepare("UPDATE vendors SET vendor_name = ? WHERE id = ?");
            $stmt->bind_param("si", $vendor_name, $edit_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Vendor updated successfully!";
                header("Location: vendor_manager.php?type=" . urlencode($category_type));
                exit();
            }
        } else {
            // ADD LOGIC
            $check = $conn->prepare("SELECT id FROM vendors WHERE LOWER(vendor_name) = LOWER(?) AND category = ?");
            $check->bind_param("ss", $vendor_name, $assigned_category);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error_msg = "This vendor is already registered for " . $assigned_category;
            } else {
                $stmt = $conn->prepare("INSERT INTO vendors (vendor_name, category) VALUES (?, ?)");
                $stmt->bind_param("ss", $vendor_name, $assigned_category);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Vendor added successfully!"; 
                    header("Location: vendor_manager.php?type=" . urlencode($category_type));
                    exit();
                }
            }
        }
    }
}

// --- FETCH LOGIC ---
if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') {
    $stmt = $conn->prepare("SELECT * FROM vendors ORDER BY category ASC, id DESC");
} else {
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE category = ? ORDER BY id DESC");
    $stmt->bind_param("s", $category_type);
}
$stmt->execute();
$result = $stmt->get_result();

ob_start();
?>

<style>
    /* Inline Edit Styling from furniture types */
    .edit-mode-active {
        border: 2px solid #0d6efd !important;
        box-shadow: 0 0 15px rgba(13, 110, 253, 0.15) !important;
        transform: scale(1.01);
        transition: all 0.3s ease;
    }
    .edit-badge { display: none; }
    .edit-mode-active .edit-badge { display: inline-block; }
    .bg-emerald-soft { background: rgba(16, 185, 129, 0.1); }
    .fw-800 { font-weight: 800 !important; letter-spacing: -0.5px; }
</style>

<div class="container-fluid px-0">
    <?php if($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <?php if($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3 py-3 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 20px;" id="registryCard">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-emerald-soft p-2 rounded-3 text-success" id="formIconBox">
                                <i class="bi bi-people-fill fs-4" id="formIcon"></i>
                            </div>
                            <div>
                                <h5 class="fw-800 text-dark mb-0" id="formTitle"><?= $category_type ?> Registry</h5>
                                <p class="text-muted small mb-0" id="formSubtitle">Add a new verified provider</p>
                            </div>
                        </div>
                        <span class="badge bg-primary edit-badge animate__animated animate__fadeIn">EDIT MODE</span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="vendorForm">
                        <input type="hidden" name="vendor_id" id="vendor_id">
                        
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-muted">Vendor Name</label>
                            <input type="text" name="vendor_name" id="vendor_name" class="form-control p-3 shadow-none" placeholder="Enter name..." required>
                            
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin'): ?>
                            <div class="mt-4" id="categoryContainer">
                                <label class="form-label small fw-bold text-uppercase text-muted">Stock Category</label>
                                <select name="category" id="categorySelect" class="form-select p-3 shadow-none" required>
                                    <option value="Computer" <?= $category_type == 'Computer' ? 'selected' : '' ?>>Computer Stock</option>
                                    <option value="Furniture" <?= $category_type == 'Furniture' ? 'selected' : '' ?>>Furniture Stock</option>
                                    <option value="Electronics">Electronics Stock</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" name="save_vendor" id="submitBtn" class="btn btn-success w-100 rounded-pill py-2 fw-bold shadow-sm">
                            <i class="bi bi-plus-lg me-1"></i> Register Vendor
                        </button>
                        
                        <button type="button" onclick="resetVendorForm()" class="btn btn-danger w-100 rounded-pill mt-2 border-0 d-none" id="cancelBtn">
                            Discard Changes
                        </button>
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
                                <th class="ps-4 py-3">REF</th>
                                <th class="py-3">Provider Name</th>
                                <?php if ($_SESSION['role'] === 'SuperAdmin'): ?>
                                    <th class="py-3">Category</th>
                                <?php endif; ?>
                                <th class="text-end pe-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): 
                                $i = 1;
                                while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="text-muted small">#<?= str_pad($i++, 2, '0', STR_PAD_LEFT); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-emerald-soft text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                <i class="bi bi-building"></i>
                                            </div>
                                            <span class="fw-bold text-dark small text-uppercase"><?= htmlspecialchars($row['vendor_name']); ?></span>
                                        </div>
                                    </td>
                                    
                                    <?php if ($_SESSION['role'] === 'SuperAdmin'): ?>
                                    <td>
                                        <?php 
                                        // Define the style mapping for each category
                                        $cat_styles = [
                                            'Computer'    => ['bg' => '#eef2ff', 'text' => '#4f46e5', 'icon' => 'bi-pc-display'],
                                            'Furniture'   => ['bg' => '#fff7ed', 'text' => '#c2410c', 'icon' => 'bi-lamp'],
                                            'Electronics' => ['bg' => '#f0fdf4', 'text' => '#15803d', 'icon' => 'bi-lightning-charge'],
                                            'Default'     => ['bg' => '#f8fafc', 'text' => '#64748b', 'icon' => 'bi-tag']
                                        ];
                                        $style = $cat_styles[$row['category']] ?? $cat_styles['Default'];
                                        ?>
                                        <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill border" 
                                            style="background-color: <?= $style['bg'] ?>; border-color: rgba(0,0,0,0.05) !important;">
                                            <i class="<?= $style['icon'] ?>" style="color: <?= $style['text'] ?>; font-size: 12px;"></i>
                                            <span style="color: <?= $style['text'] ?>; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                                <?= htmlspecialchars($row['category']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <?php endif; ?>

                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-light border rounded-pill px-3 shadow-sm" 
                                                onclick='prepareEditVendor(<?= json_encode($row) ?>)'>
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light border rounded-pill px-3 ms-1 shadow-sm delete-vendor-btn" 
                                                data-id="<?= $row['id']; ?>" data-name="<?= htmlspecialchars($row['vendor_name']); ?>">
                                            <i class="bi bi-trash3 text-danger"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="<?= ($_SESSION['role'] === 'SuperAdmin') ? '5' : '4' ?>" class="text-center py-5 text-muted small">No partners found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// --- FORM TRANSITIONS ---
function prepareEditVendor(data) {
    document.getElementById('vendor_id').value = data.id;
    document.getElementById('vendor_name').value = data.vendor_name;
    
    const catSelect = document.getElementById('categorySelect');
    if(catSelect) catSelect.value = data.category;

    const card = document.getElementById('registryCard');
    const submitBtn = document.getElementById('submitBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const formTitle = document.getElementById('formTitle');
    const formSubtitle = document.getElementById('formSubtitle');

    card.classList.add('edit-mode-active');
    submitBtn.classList.replace('btn-success', 'btn-primary');
    submitBtn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Update Vendor';
    
    formTitle.innerText = "Modify Vendor";
    formSubtitle.innerText = "Updating details for " + data.vendor_name;
    
    cancelBtn.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetVendorForm() {
    document.getElementById('vendorForm').reset();
    document.getElementById('vendor_id').value = "";
    
    const card = document.getElementById('registryCard');
    const submitBtn = document.getElementById('submitBtn');
    const cancelBtn = document.getElementById('cancelBtn');

    card.classList.remove('edit-mode-active');
    submitBtn.classList.replace('btn-primary', 'btn-success');
    submitBtn.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Register Vendor';
    
    document.getElementById('formTitle').innerText = "<?= $category_type ?> Registry";
    document.getElementById('formSubtitle').innerText = "Add a new verified provider";
    
    cancelBtn.classList.add('d-none');
}

// --- MAIN INITIALIZATION ---
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. ATTACH DELETE CLICK EVENTS (This makes the buttons clickable again)
    document.querySelectorAll('.delete-vendor-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            Swal.fire({
                title: 'Delete Vendor?',
                text: `Removing "${name}" cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, remove',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete_vendor.php?id=${id}&type=<?= urlencode($category_type) ?>`;
                }
            });
        });
    });

    // 2. HANDLE ALERTS (Based on Session and URL)
    const urlParams = new URLSearchParams(window.location.search);

    // Session Success
    <?php if($success_msg): ?>
        Swal.fire({ icon: 'success', title: 'Done!', text: '<?= $success_msg ?>', timer: 1500, showConfirmButton: false });
    <?php endif; ?>

    // URL Error (Access Denied)
    if (urlParams.has('error')) {
        const errorType = urlParams.get('error');
        let errorText = "This action could not be completed.";

        if (errorType === 'used_in_services') {
            errorText = "This vendor is linked to existing Service records and cannot be removed.";
        } else if (errorType === 'used_in_stock') {
            errorText = "This vendor is currently linked to active Stock items and cannot be removed.";
        }

        Swal.fire({ icon: 'error', title: 'Access Denied', text: errorText, confirmButtonColor: '#6b7280' });
    }

    // URL Success (After Delete)
    if (urlParams.get('success') === '1') {
        Swal.fire({ icon: 'success', title: 'Removed', text: 'Vendor successfully deleted.', timer: 1500, showConfirmButton: false });
    }

    // 3. CLEANUP URL 
    setTimeout(() => {
        window.history.replaceState({}, document.title, window.location.pathname + "?type=<?= urlencode($category_type) ?>");
    }, 500);
});
</script>

<?php

$content = ob_get_clean();





if (strtolower($category_type) === 'furniture') {

    include "../furniture_stock/furniturelayout.php";

} else {

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') {

        include "../admin/adminlayout.php";

    } else {

        include "../divisions/divisionslayout.php";

    }

}

?>