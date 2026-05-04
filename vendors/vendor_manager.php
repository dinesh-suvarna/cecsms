<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";

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

$category_type = $_GET['type'] ?? ''; 
$page_title = "Register " . $category_type . " Vendor";

// --- ADD / UPDATE LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_vendor'])) {
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    $assigned_category = (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin' && isset($_POST['category'])) ? $_POST['category'] : $category_type;
    $edit_id = $_POST['vendor_id'] ?? '';

    if (empty($vendor_name)) {
        $error_msg = "Vendor Name is required.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } else {
        if (!empty($edit_id)) {
            $stmt = $conn->prepare("UPDATE vendors SET vendor_name = ?, contact_person = ?, phone_number = ?, email = ?, address = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $vendor_name, $contact_person, $phone_number, $email, $address, $edit_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Vendor updated successfully!";
                header("Location: view_vendors.php?type=" . urlencode($category_type));
                exit();
            }
        } else {
            $check = $conn->prepare("SELECT id FROM vendors WHERE LOWER(vendor_name) = LOWER(?) AND category = ?");
            $check->bind_param("ss", $vendor_name, $assigned_category);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error_msg = "This vendor is already registered for " . $assigned_category;
            } else {
                $stmt = $conn->prepare("INSERT INTO vendors (vendor_name, category, contact_person, phone_number, email, address) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $vendor_name, $assigned_category, $contact_person, $phone_number, $email, $address);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Vendor added successfully!"; 
                    header("Location: view_vendors.php?type=" . urlencode($category_type));
                    exit();
                }
            }
        }
    }
}

$edit_data = null;
if(isset($_GET['edit'])){
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
}

ob_start();
?>

<style>
    :root { --primary-gradient: linear-gradient(135deg, #0d6efd, #0a58ca); }
    .bg-emerald-soft { background: rgba(16, 185, 129, 0.1); }
    .fw-800 { font-weight: 800 !important; letter-spacing: -0.5px; }
    .full-height-card { min-height: 80vh; }
    .form-control:invalid:focus { border-color: #dc3545; box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25); }
    .required-dot { color: #dc3545; margin-left: 3px; }
    <?php if(isset($_GET['edit'])): ?>
        #registryCard {
        border: 2px solid #dc3545 !important;
        transition: border 0.3s ease-in-out;
        }
    <?php endif; ?>
</style>

<div class="container-fluid py-4">
    <?php if($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 full-height-card" id="registryCard">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-emerald-soft p-3 rounded-3 text-success">
                            <i class="bi bi-people-fill fs-3"></i>
                        </div>
                        <div>
                            <h4 class="fw-800 text-dark mb-0"><?= $edit_data ? "Modify Vendor" : $category_type . " Vendor Registry" ?></h4>
                            <p class="text-muted mb-0">Provide complete information for accurate inventory tracking.</p>
                        </div>
                    </div>
                </div>
                <hr class="mx-4 my-0 text-muted opacity-25">
                <div class="card-body p-4 p-lg-5">
                    <form method="POST" id="vendorForm" class="needs-validation" novalidate>
                        <input type="hidden" name="vendor_id" value="<?= $edit_data['id'] ?? '' ?>">
                        <div class="row g-4">
                            <div class="col-lg-7">
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Vendor Name <span class="required-dot">*</span></label>
                                    <input type="text" name="vendor_name" class="form-control form-control-lg shadow-none" 
                                           placeholder="e.g. Global Tech Solutions" 
                                           value="<?= htmlspecialchars($edit_data['vendor_name'] ?? '') ?>" required>
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-uppercase text-muted">Contact Person</label>
                                        <input type="text" name="contact_person" class="form-control shadow-none" 
                                               placeholder="Name of representative" 
                                               value="<?= htmlspecialchars($edit_data['contact_person'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-uppercase text-muted">Phone Number</label>
                                        <input type="tel" name="phone_number" class="form-control shadow-none" 
                                               pattern="[0-9+ \-]{7,}" 
                                               placeholder="e.g. 92345 6587" 
                                               value="<?= htmlspecialchars($edit_data['phone_number'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Email Address</label>
                                    <input type="email" name="email" class="form-control shadow-none" 
                                           placeholder="vendor@example.com" 
                                           value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-lg-5 border-start-lg ps-lg-4">
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Physical Address</label>
                                    <textarea name="address" class="form-control shadow-none" rows="5" 
                                              placeholder="Building, Street, City, Zip"><?= htmlspecialchars($edit_data['address'] ?? '') ?></textarea>
                                </div>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin'): ?>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Stock Category Assignment</label>
                                    <select name="category" class="form-select shadow-none" required>
                                        <option value="Computer" <?= ($edit_data['category'] ?? $category_type) == 'Computer' ? 'selected' : '' ?>>Computer Stock</option>
                                        <option value="Furniture" <?= ($edit_data['category'] ?? $category_type) == 'Furniture' ? 'selected' : '' ?>>Furniture Stock</option>
                                        <option value="Electricals" <?= ($edit_data['category'] ?? $category_type) == 'Electricals' ? 'selected' : '' ?>>Electrical Stock</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="mt-5 d-flex gap-2">
                                    <button type="submit" name="save_vendor" class="btn <?= $edit_data ? 'btn-primary' : 'btn-success' ?> flex-grow-1 rounded-pill py-3 fw-bold">
                                        <i class="bi <?= $edit_data ? 'bi-arrow-repeat' : 'bi-plus-lg' ?> me-1"></i> <?= $edit_data ? "Update Vendor" : "Register Vendor" ?>
                                    </button>
                                    <a href="view_vendors.php?type=<?= urlencode($category_type) ?>" class="btn btn-light rounded-pill px-4 py-3 fw-bold">Cancel</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>

<?php
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>