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
    :root {
        --primary-gradient: linear-gradient(135deg, #0d6efd, #0a58ca);
    }
    .edit-mode-active {
        border: 2px solid #0d6efd !important;
        box-shadow: 0 0 15px rgba(13, 110, 253, 0.15) !important;
        transform: scale(1.01);
        transition: all 0.3s ease;
        animation: pulse-blue 2s infinite;
    }
    @keyframes pulse-blue {
        0% { border-color: #0d6efd; }
        50% { border-color: #70b0ff; }
        100% { border-color: #0d6efd; }
    }
    
    .nav-pills .nav-link {
        border-radius: 10px;
        color: #64748b;
        font-weight: 600;
        transition: all 0.3s;
        border: 1px solid transparent;
    }
    .nav-pills .nav-link.active {
        background: var(--primary-gradient);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
    }
    
    .dataTables_wrapper .row { margin: 0; padding: 1rem 0.5rem; }
    .dataTables_filter input { border-radius: 20px; padding: 0.4rem 1rem; border: 1px solid #e2e8f0; }
    
    .table-responsive { max-height: 600px; overflow-y: auto; }
    thead th { position: sticky; top: 0; background: #f8fafc !important; z-index: 10; }
    
    .bg-emerald-soft { background: rgba(16, 185, 129, 0.1); }
    .fw-800 { font-weight: 800 !important; letter-spacing: -0.5px; }

    .dataTables_filter {
        text-align: right;
        margin-bottom: 15px;
    }

    .dataTables_filter input {
        width: 250px !important;
        border-radius: 20px !important;
        padding: 8px 15px !important;
        border: 1px solid #dee2e6 !important;
        outline: none;
    }

    /* Style Pagination buttons */
    .pagination .page-link {
        border: none;
        color: #64748b;
        margin: 0 2px;
        border-radius: 8px !important;
    }

    .pagination .active .page-link {
        background: var(--primary-gradient) !important;
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.2);
    }

    /* 1. Remove Search Bar Focus Outline (Blue Glow) */
    .dataTables_filter input:focus {
        border-color: #dee2e6 !important; /* Keep the original border color */
        box-shadow: none !important;      /* Remove blue glow */
        outline: none !important;
    }

    /* 2. Style the length dropdown focus as well */
    .dataTables_length select:focus {
        box-shadow: none !important;
        outline: none !important;
    }

    /* 3. Adjust Pagination Colors (Lighten the dark active state) */
    .pagination .active .page-link {
        /* Changing from the dark primary-gradient to a softer blue */
        background: #e0e7ff !important; 
        color: #4338ca !important;
        border: 1px solid #c7d2fe !important;
        box-shadow: none !important;
    }

    .pagination .page-link:hover {
        background: #f8fafc !important;
        color: #0d6efd !important;
    }

    /* 4. Fix for Length and Search Alignment */
    .dataTables_wrapper .d-flex {
        padding: 0.5rem 1rem;
        background: #fdfdfd;
        border-bottom: 1px solid #f1f5f9;
    }

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
                                <h5 class="fw-800 text-dark mb-0" id="formTitle"><?= $category_type ?> Vendor Registry</h5>
                                <p class="text-muted small mb-0" id="formSubtitle">Add a new verified provider</p>
                            </div>
                        </div>
                        <span id="editBadge" class="badge bg-primary edit-badge animate__animated animate__fadeIn d-none">EDIT MODE</span>
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
                                    <option value="Electricals" <?= $category_type == 'Electricals' ? 'selected' : '' ?>>Electrical Stock</option>
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
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-800 text-dark mb-0">Service Partners</h6>
                            <p class="text-muted small mb-0">Authorized vendor directory</p>
                        </div>
                    </div>

                    <ul class="nav nav-pills gap-2 mb-3" id="vendorTabs" role="tablist">
                        <?php 
                        $tabs = (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') 
                                ? ['Computer', 'Furniture', 'Electricals'] 
                                : [$category_type];
                        
                        foreach($tabs as $index => $tab): 
                        ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($index === 0) ? 'active' : '' ?> px-4" 
                                    id="<?= $tab ?>-tab" data-bs-toggle="pill" 
                                    data-bs-target="#tab-<?= $tab ?>" type="button">
                                <?= $tab ?>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="tab-content" id="vendorTabContent">
                    <?php foreach($tabs as $index => $tab): ?>
                    <div class="tab-pane fade <?= ($index === 0) ? 'show active' : '' ?>" id="tab-<?= $tab ?>">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 vendorDataTable" style="width:100%">
                                <thead class="bg-light">
                                    <tr class="text-muted small text-uppercase fw-bold" style="font-size: 10px;">
                                        <th class="ps-4 py-3">REF</th>
                                        <th class="py-3">Provider Name</th>
                                        <th class="text-end pe-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Reset pointer and filter results for this tab
                                    $result->data_seek(0);
                                    $count = 1;
                                    while($row = $result->fetch_assoc()): 
                                        if($row['category'] !== $tab) continue;
                                    ?>
                                    <tr>
                                        <td class="ps-4 text-muted small">#<?= str_pad($count++, 2, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-emerald-soft text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                                    <i class="bi bi-person-vcard"></i>
                                                </div>
                                                <span class="fw-bold text-dark small"><?= htmlspecialchars($row['vendor_name']); ?></span>
                                            </div>
                                        </td>
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
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Global UI Functions
function prepareEditVendor(data) {
    document.getElementById('vendor_id').value = data.id;
    document.getElementById('vendor_name').value = data.vendor_name;
    if(document.getElementById('categorySelect')) document.getElementById('categorySelect').value = data.category;
    
    document.getElementById('registryCard').classList.add('edit-mode-active');
    const btn = document.getElementById('submitBtn');
    btn.classList.replace('btn-success', 'btn-primary');
    btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Update Vendor';
    
    document.getElementById('formTitle').innerText = "Modify Vendor";
    document.getElementById('cancelBtn').classList.remove('d-none');
    document.getElementById('editBadge').classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetVendorForm() {
    document.getElementById('vendorForm').reset();
    document.getElementById('vendor_id').value = "";
    document.getElementById('registryCard').classList.remove('edit-mode-active');
    const btn = document.getElementById('submitBtn');
    btn.classList.replace('btn-primary', 'btn-success');
    btn.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Register Vendor';
    document.getElementById('cancelBtn').classList.add('d-none');
    document.getElementById('editBadge').classList.add('d-none');
}

$(document).ready(function() {
    // 1. Initialize Tables
    const allTables = $('.vendorDataTable').DataTable({
        "pageLength": 10,
        "dom": '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
        "language": {
            "lengthMenu": "_MENU_",
            "search": "",
            "searchPlaceholder": "Search across all tabs...",
            "paginate": {
                "previous": "<i class='bi bi-chevron-left'></i>",
                "next": "<i class='bi bi-chevron-right'></i>"
            }
        },
        "drawCallback": function() {
            attachDeleteEvents();
        }
    });

    // 2. Cross-Tab Search Logic
    $('.dataTables_filter input').on('keyup', function () {
        allTables.search(this.value).draw();
    });

    // 3. Tab Refresh
    $('button[data-bs-toggle="pill"]').on('shown.bs.tab', function() {
        allTables.columns.adjust();
    });

    // 4. Delete Logic
    function attachDeleteEvents() {
        $('.delete-vendor-btn').off('click').on('click', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            Swal.fire({
                title: 'Delete Vendor?',
                text: `Removing "${name}" cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, remove'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete_vendor.php?id=${id}&type=<?= urlencode($category_type) ?>`;
                }
            });
        });
    }

    // 5. Notifications
    <?php if($success_msg): ?>
        Swal.fire({ icon: 'success', title: 'Success', text: '<?= $success_msg ?>', timer: 2000, showConfirmButton: false });
    <?php endif; ?>

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        Swal.fire({ icon: 'success', title: 'Deleted', text: 'Vendor removed successfully.', timer: 2000, showConfirmButton: false });
    }

    // URL Cleanup
    setTimeout(() => {
        window.history.replaceState({}, document.title, window.location.pathname + "?type=<?= urlencode($category_type) ?>");
    }, 500);
});
</script>

<?php
$content = ob_get_clean();
$type = strtolower($category_type);

if ($type === 'furniture') {
    include "../furniture_stock/furniturelayout.php";
} 
elseif ($type === 'electrical') {
    include "../electrical_stock/electricalslayout.php";
} 
else {
    // Default layouts based on role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') {
        include "../admin/adminlayout.php";
    } else {
        include "../divisions/divisionslayout.php";
    }
}
?>