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
    :root {
        --erp-navy: #123b63;
        --erp-navy-dark: #0b2942;
        --erp-bg: #f3f5f7;
        --erp-card-bg: #ffffff;
        --erp-border: #d9e0e7;
        --erp-text-main: #20384d;
        --erp-text-muted: #64748b;
        --erp-shadow-sm: 0 1px 3px rgba(20,45,70,.05);
        --erp-shadow-hover: 0 6px 16px rgba(18,59,99,.08);
    }

    body { 
        background-color: var(--erp-bg); 
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: var(--erp-text-main);
    }

    .erp-card {
        background: var(--erp-card-bg);
        border: 1px solid var(--erp-border);
        border-radius: 8px;
        box-shadow: var(--erp-shadow-sm);
    }

    .icon-box-header {
        width: 44px;
        height: 44px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #edf3f8;
        color: var(--erp-navy);
        font-size: 1.25rem;
    }

    .form-label-erp {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--erp-text-muted);
        margin-bottom: 0.35rem;
    }

    .form-control-erp, .form-select-erp {
        border: 1px solid var(--erp-border);
        border-radius: 6px;
        padding: 0.6rem 0.85rem;
        font-size: 0.875rem;
        color: var(--erp-text-main);
        background-color: #ffffff;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-control-erp:focus, .form-select-erp:focus {
        border-color: var(--erp-navy);
        box-shadow: 0 0 0 3px rgba(18, 59, 99, 0.12);
        outline: none;
    }

    .required-dot { color: #dc3545; margin-left: 2px; }

    .btn-erp-primary {
        background: var(--erp-navy);
        border-color: var(--erp-navy);
        color: #ffffff;
        font-weight: 600;
        border-radius: 6px;
        padding: 0.65rem 1.25rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
    }

    .btn-erp-primary:hover {
        background: var(--erp-navy-dark);
        border-color: var(--erp-navy-dark);
        color: #ffffff;
    }

    .btn-erp-light {
        background: #f1f5f9;
        border: 1px solid var(--erp-border);
        color: var(--erp-text-main);
        font-weight: 600;
        border-radius: 6px;
        padding: 0.65rem 1.25rem;
        font-size: 0.875rem;
    }

    .btn-erp-light:hover {
        background: #e2e8f0;
        color: var(--erp-text-main);
    }

    <?php if(isset($_GET['edit'])): ?>
        #registryCard {
            border: 1.5px solid #dc3545 !important;
        }
    <?php endif; ?>
</style>

<div class="container-fluid p-0">
    <?php if($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-2 py-2 px-3 mb-3 d-flex align-items-center extra-small">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-6"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="erp-card" id="registryCard">
                <!-- Header Block -->
                <div class="p-4 border-bottom bg-white d-flex align-items-center gap-3">
                    <div class="icon-box-header flex-shrink-0">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold tracking-tight mb-1" style="color: var(--erp-text-main); letter-spacing: -0.01em;">
                        <?= $edit_data ? "Modify Vendor" : $category_type . " Vendor Registry" ?></h5>
                        <p class="text-muted mb-0 extra-small">Provide complete information for accurate inventory tracking.</p>
                    </div>
                </div>

                <!-- Form Section -->
                <div class="p-4 p-lg-5">
                    <form method="POST" id="vendorForm" class="needs-validation" novalidate>
                        <input type="hidden" name="vendor_id" value="<?= $edit_data['id'] ?? '' ?>">
                        <div class="row g-4">
                            <!-- Left Column: Primary Vendor Information -->
                            <div class="col-lg-7">
                                <div class="mb-4">
                                    <label class="form-label-erp">Vendor Name <span class="required-dot">*</span></label>
                                    <input type="text" name="vendor_name" class="form-control form-control-erp" 
                                           placeholder="e.g. Global Tech Solutions" 
                                           value="<?= htmlspecialchars($edit_data['vendor_name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label-erp">Contact Person</label>
                                        <input type="text" name="contact_person" class="form-control form-control-erp" 
                                               placeholder="Name of representative" 
                                               value="<?= htmlspecialchars($edit_data['contact_person'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-erp">Phone Number</label>
                                        <input type="tel" name="phone_number" class="form-control form-control-erp" 
                                               pattern="[0-9+ \-]{7,}" 
                                               placeholder="e.g. 92345 6587" 
                                               value="<?= htmlspecialchars($edit_data['phone_number'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label-erp">Email Address</label>
                                    <input type="email" name="email" class="form-control form-control-erp" 
                                           placeholder="vendor@example.com" 
                                           value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Right Column: Address and Administration -->
                            <div class="col-lg-5 border-start-lg ps-lg-4">
                                <div class="mb-4">
                                    <label class="form-label-erp">Physical Address</label>
                                    <textarea name="address" class="form-control form-control-erp" rows="5" 
                                              placeholder="Building, Street, City, Zip"><?= htmlspecialchars($edit_data['address'] ?? '') ?></textarea>
                                </div>

                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin'): ?>
                                <div class="mb-4">
                                    <label class="form-label-erp">Stock Category Assignment</label>
                                    <select name="category" class="form-select form-select-erp" required>
                                        <option value="Computer" <?= ($edit_data['category'] ?? $category_type) == 'Computer' ? 'selected' : '' ?>>Computer Stock</option>
                                        <option value="Furniture" <?= ($edit_data['category'] ?? $category_type) == 'Furniture' ? 'selected' : '' ?>>Furniture Stock</option>
                                        <option value="Electricals" <?= ($edit_data['category'] ?? $category_type) == 'Electricals' ? 'selected' : '' ?>>Electrical Stock</option>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <div class="mt-4 pt-2 d-flex gap-2">
                                    <button type="submit" name="save_vendor" class="btn btn-erp-primary flex-grow-1">
                                        <i class="bi <?= $edit_data ? 'bi-arrow-repeat' : 'bi-plus-lg' ?> me-1"></i> <?= $edit_data ? "Update Vendor" : "Register Vendor" ?>
                                    </button>
                                    <a href="view_vendors.php?type=<?= urlencode($category_type) ?>" class="btn btn-erp-light px-4">Cancel</a>
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