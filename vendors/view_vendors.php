<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";

$success_msg = "";
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}

$category_type = $_GET['type'] ?? 'Computer'; 
$page_title = "Service Partners";

// --- FETCH LOGIC (Updated to sort A-Z) ---
if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') {
    $stmt = $conn->prepare("SELECT * FROM vendors ORDER BY vendor_name ASC");
} else {
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE category = ? ORDER BY vendor_name ASC");
    $stmt->bind_param("s", $category_type);
}
$stmt->execute();
$result = $stmt->get_result();

ob_start();
?>

<style>
    .fw-800 { font-weight: 800 !important; letter-spacing: -0.5px; }
    .text-xxs { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05rem; }
    
    /* Improved Category Badge Style */
    .badge-category {
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #c7d2fe;
        padding: 4px 12px;
        border-radius: 6px;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
    }

    /* Tab Styling */
    .nav-pills .nav-link { 
        border-radius: 12px; 
        color: #64748b; 
        font-weight: 600; 
        padding: 10px 24px;
        transition: all 0.25s ease;
        border: 1px solid #f1f5f9;
        background: #f8fafc;
    }
    .nav-pills .nav-link.active { 
        background: #0f172a; 
        color: #fff; 
        border-color: #0f172a;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    /* Table & Search UI Alignment Fixes */
    .dataTables_wrapper .dataTables_filter { float: none; text-align: left; }
    .dataTables_wrapper .dataTables_length { margin-bottom: 0; }
    
    .search-container {
        background: #f8fafc;
        border-radius: 12px;
        padding: 15px 20px;
        border: 1px solid #e2e8f0;
    }

    .custom-search-input {
        border-radius: 8px !important;
        border: 1px solid #cbd5e1 !important;
        padding: 8px 12px !important;
        width: 300px !important;
        background: white !important;
    }

    .table thead th { background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .vendor-row { transition: background-color 0.2s; border-bottom: 1px solid #f1f5f9 !important; }
    .vendor-row:hover { background-color: #f8fafc !important; }

    .vendor-avatar {
        width: 48px; height: 42px;
        background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
        color: #4338ca;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px;
        font-size: 0.85rem;
    }

    .vendor-name-btn { 
        background: none; border: none; padding: 0; text-align: left;
        color: #1e293b; font-weight: 700; font-size: 0.95rem;
    }

    .btn-action {
        width: 36px; height: 36px; display: inline-flex; 
        align-items: center; justify-content: center;
        border-radius: 10px; border: 1px solid #e2e8f0; background: white;
    }

    .detail-card {
    background: #f8fafc;
    border-radius: 16px;
    padding: 15px 20px;
    display: flex;
    flex-direction: column; /* This forces the stack */
    gap: 4px; /* Space between label and value */
}

.detail-label {
    font-size: 0.7rem;
    color: #94a3b8;
    font-weight: 800;
    text-transform: uppercase;
    display: block;
}

.detail-value {
    font-size: 0.95rem;
    color: #1e293b;
    font-weight: 600;
    display: block;
    word-break: break-word; /* Prevents long emails/addresses from breaking layout */
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h4 class="fw-800 text-dark mb-1">Service Partners</h4>
            <p class="text-muted small mb-0 d-flex align-items-center gap-2">
                Managing <span class="badge-category"><?= $category_type ?></span> vendor relationships
            </p>
        </div>
        <div class="col-auto">
            <a href="vendor_manager.php?type=<?= urlencode($category_type) ?>" class="btn btn-primary rounded-3 px-4 py-2 fw-bold shadow-sm">
                <i class="bi bi-plus-lg me-2"></i>New Vendor
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <!-- Tabs -->
        <div class="card-header bg-white border-0 p-4 pb-0">
            <ul class="nav nav-pills gap-2" id="vendorTabs">
                <?php 
                $tabs = (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') 
                        ? ['Computer', 'Furniture', 'Electricals'] 
                        : [$category_type];
                foreach($tabs as $tab): 
                ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($tab === $category_type) ? 'active' : '' ?>" 
                       href="?type=<?= urlencode($tab) ?>"><?= $tab ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Table UI -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="saasVendorTable">
                    <thead>
                        <tr class="text-muted text-xxs text-uppercase">
                            <th class="ps-4 py-3">Reference</th>
                            <th class="py-3">Vendor Information</th>
                            <th class="py-3">Primary Contact</th>
                            <th class="text-end pe-4 py-3">Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 1;
                        while($row = $result->fetch_assoc()): 
                            if($row['category'] !== $category_type) continue;
                            
                            $words = explode(" ", $row['vendor_name']);
                            $initials = "";
                            foreach ($words as $w) { $initials .= strtoupper(substr($w, 0, 1)); }
                            $initials = substr($initials, 0, 3);
                        ?>
                        <tr class="vendor-row">
                            <td class="ps-4">
                                <span class="badge bg-light text-muted rounded-pill">#<?= str_pad($count++, 2, '0', STR_PAD_LEFT); ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="vendor-avatar"><?= $initials ?></div>
                                    <div>
                                        <button class="vendor-name-btn" onclick='viewVendorDetails(<?= json_encode($row) ?>)'>
                                            <?= htmlspecialchars($row['vendor_name']); ?>
                                        </button>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($row['email'] ?: 'No email provided'); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-600 text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($row['contact_person'] ?: 'N/A'); ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($row['phone_number'] ?: '--'); ?></div>
                            </td>
                            <td class="text-end pe-4">
                                <a href="vendor_manager.php?edit=<?= $row['id'] ?>&type=<?= urlencode($category_type) ?>" 
                                   class="btn-action text-primary me-1" title="Edit Vendor">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <button class="btn-action text-danger delete-vendor-btn" 
                                        data-id="<?= $row['id']; ?>" data-name="<?= htmlspecialchars($row['vendor_name']); ?>" title="Delete">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- VENDOR DETAILS MODAL -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-body p-5">
                <!-- Header Section -->
                <div class="text-center mb-4">
                    <div class="vendor-avatar mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.2rem; display: flex; align-items: center; justify-content: center;" id="det_initial">?</div>
                    <h4 class="fw-800 text-dark mb-1" id="det_name">Vendor Name</h4>
                    <span class="badge-category" id="det_cat">Category</span>
                </div>

                <!-- Info Grid -->
                <div class="row g-3">
                    <div class="col-6">
                        <div class="detail-card">
                            <span class="detail-label">Representative</span>
                            <span class="detail-value" id="det_contact">--</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="detail-card">
                            <span class="detail-label">Contact Number</span>
                            <span class="detail-value" id="det_phone">--</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="detail-card">
                            <span class="detail-label">Email Address</span>
                            <span class="detail-value" id="det_email">--</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="detail-card">
                            <span class="detail-label">Physical Address</span>
                            <span class="detail-value" id="det_address">--</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-2">
                    <button type="button" class="btn btn-light w-100 rounded-pill py-2 fw-bold" data-bs-dismiss="modal">Close Profile</button>
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
let vendorModal;

$(document).ready(function() {
    // Initialize Modal
    if(document.getElementById('detailsModal')){
        vendorModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    }

    // UNIFIED DATATABLE INITIALIZATION
    // This fixes the 'show' part alignment and maintains A-Z sorting
    if ($.fn.DataTable.isDataTable('#saasVendorTable')) {
        $('#saasVendorTable').DataTable().destroy();
    }

    $('#saasVendorTable').DataTable({
        "dom": '<"search-container d-flex justify-content-between align-items-center m-4"lf>rt<"p-4 d-flex justify-content-between align-items-center"ip>',
        "language": { 
            "lengthMenu": "Show _MENU_ entries",
            "search": "", 
            "searchPlaceholder": "Filter vendors...",
            "paginate": {
                "previous": "<i class='bi bi-chevron-left'></i>",
                "next": "<i class='bi bi-chevron-right'></i>"
            }
        },
        "pageLength": 10,
        "order": [[1, 'asc']], // A-Z Sorting enabled
        "columnDefs": [
            { "orderable": false, "targets": [0, 3] }
        ],
        "drawCallback": function() {
            // Apply custom classes and ensure horizontal flex alignment
            $('.dataTables_filter input').addClass('form-control custom-search-input');
            $('.dataTables_length select').addClass('form-select form-select-sm d-inline-block w-auto');
            $('.dataTables_length label').addClass('d-flex align-items-center');
            $('.dataTables_filter label').addClass('d-flex align-items-center');
        }
    });

    // Delete Logic (SweetAlert style preserved as requested)
    $('.delete-vendor-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        Swal.fire({
            title: 'Terminate Partner?',
            text: `Are you sure you want to remove ${name}? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0f172a',
            cancelButtonColor: '#f1f5f9',
            cancelButtonText: '<span style="color: #64748b">Keep Partner</span>',
            confirmButtonText: 'Yes, Remove',
            customClass: {
                confirmButton: 'rounded-pill px-4',
                cancelButton: 'rounded-pill px-4'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `delete_vendor.php?id=${id}&type=<?= urlencode($category_type) ?>`;
            }
        });
    });

    // Success Message Toast
    <?php if($success_msg): ?>
        Swal.fire({ 
            icon: 'success', 
            title: 'Action Successful', 
            text: '<?= $success_msg ?>', 
            timer: 2500, 
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    <?php endif; ?>
});

// View Details Function
function viewVendorDetails(data) {
    document.getElementById('det_name').innerText = data.vendor_name;
    const initials = data.vendor_name.split(' ').map(word => word[0]).join('').toUpperCase().substring(0, 3);
    document.getElementById('det_initial').innerText = initials;
    document.getElementById('det_cat').innerText = data.category;
    document.getElementById('det_contact').innerText = data.contact_person || 'Not specified';
    document.getElementById('det_phone').innerText = data.phone_number || 'No phone recorded';
    document.getElementById('det_email').innerText = data.email || 'No email recorded';
    document.getElementById('det_address').innerText = data.address || 'Address not listed in system';
    vendorModal.show();
}
</script>

<?php
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>