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
    /* SaaS Typography & Layout */
    .fw-800 { font-weight: 800 !important; letter-spacing: -0.5px; }
    .text-xxs { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05rem; }
    
    /* Advanced Navigation */
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
        background: #0f172a; /* Dark SaaS Theme */
        color: #fff; 
        border-color: #0f172a;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    /* Table & Row Styling */
    .table thead th { background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .vendor-row { transition: background-color 0.2s; border-bottom: 1px solid #f1f5f9 !important; }
    .vendor-row:hover { background-color: #f8fafc !important; }

    .vendor-avatar {
        width: 42px; height: 42px;
        background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
        color: #4338ca;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px;
    }

    .vendor-name-btn { 
        background: none; border: none; padding: 0; text-align: left;
        color: #1e293b; font-weight: 700; font-size: 0.95rem;
        transition: color 0.2s;
    }
    .vendor-name-btn:hover { color: #3b82f6; }

    /* Buttons */
    .btn-action {
        width: 36px; height: 36px; padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px; transition: all 0.2s;
        border: 1px solid #e2e8f0; background: white;
    }
    .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }

    /* Modal SaaS Styling */
    .modal-content { border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
    .detail-card { background: #f8fafc; border-radius: 16px; padding: 20px; }
    .detail-label { font-size: 0.7rem; color: #94a3b8; font-weight: 800; margin-bottom: 4px; display: block; text-transform: uppercase;}
    .detail-value { font-size: 1rem; color: #1e293b; font-weight: 600; }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h4 class="fw-800 text-dark mb-1">Service Partners</h4>
            <p class="text-muted small mb-0">Managing <span class="badge bg-light text-dark border"><?= $category_type ?></span> vendor relationships</p>
        </div>
        <div class="col-auto">
            <a href="vendor_manager.php?type=<?= urlencode($category_type) ?>" class="btn btn-primary rounded-3 px-4 py-2 fw-bold shadow-sm">
                <i class="bi bi-plus-lg me-2"></i>New Vendor
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 p-4">
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
                        $initial = strtoupper(substr($row['vendor_name'], 0, 1));
                    ?>
                    <tr class="vendor-row">
                        <td class="ps-4">
                            <span class="badge bg-light text-muted rounded-pill">#<?= str_pad($count++, 2, '0', STR_PAD_LEFT); ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="vendor-avatar"><?= $initial ?></div>
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

<!-- VENDOR DETAILS MODAL - Moved outside container for stability -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-body p-5">
                <div class="text-center mb-4">
                    <div class="vendor-avatar mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.5rem;" id="det_initial">?</div>
                    <h4 class="fw-800 text-dark mb-1" id="det_name">Vendor Name</h4>
                    <span class="badge bg-soft-primary text-primary" id="det_cat">Category</span>
                </div>

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
                            <span class="detail-value" style="font-size: 0.9rem;" id="det_address">--</span>
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

<!-- JS Dependencies -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Initializing Modal via JS for better control
let vendorModal;

document.addEventListener('DOMContentLoaded', function() {
    vendorModal = new bootstrap.Modal(document.getElementById('detailsModal'));
});

function viewVendorDetails(data) {
    document.getElementById('det_name').innerText = data.vendor_name;
    document.getElementById('det_initial').innerText = data.vendor_name.charAt(0).toUpperCase();
    document.getElementById('det_cat').innerText = data.category;
    document.getElementById('det_contact').innerText = data.contact_person || 'Not specified';
    document.getElementById('det_phone').innerText = data.phone_number || 'No phone recorded';
    document.getElementById('det_email').innerText = data.email || 'No email recorded';
    document.getElementById('det_address').innerText = data.address || 'Address not listed in system';
    
    vendorModal.show();
}

$(document).ready(function() {
    $('#saasVendorTable').DataTable({
        "dom": '<"p-4 d-flex justify-content-between align-items-center"lf>rt<"p-4 d-flex justify-content-between"ip>',
        "language": { 
            "search": "", 
            "searchPlaceholder": "Filter vendors...",
            "paginate": {
                "previous": "<i class='bi bi-chevron-left'></i>",
                "next": "<i class='bi bi-chevron-right'></i>"
            }
        },
        "pageLength": 10,
        "ordering": false // Custom order is handled by PHP
    });

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
</script>

<?php
$content = ob_get_clean();
$type = strtolower($category_type);

if ($type === 'furniture') { include "../furniture_stock/furniturelayout.php"; } 
elseif ($type === 'electrical' || $type === 'electricals') { include "../electrical_stock/electricalslayout.php"; } 
else {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'SuperAdmin') { include "../vendors/vendorlayout.php"; } 
    else { include "../divisions/divisionslayout.php"; }
}
?>