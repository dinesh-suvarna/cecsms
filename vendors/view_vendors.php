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

$active_tab = $_GET['type'] ?? 'Computer'; 
$page_title = "Service Partners";

// --- FETCH ALL VENDORS FOR GLOBAL SEARCH & TAB ISOLATION ---
$stmt = $conn->prepare("SELECT * FROM vendors ORDER BY vendor_name ASC");
$stmt->execute();
$all_vendors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group vendors by category
$grouped_vendors = [
    'Computer' => [],
    'Furniture' => [],
    'Electricals' => []
];

foreach ($all_vendors as $vendor) {
    $cat = $vendor['category'] ?? 'Computer';
    if (isset($grouped_vendors[$cat])) {
        $grouped_vendors[$cat][] = $vendor;
    } else {
        $grouped_vendors['Computer'][] = $vendor;
    }
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
    }

    body { 
        background-color: var(--erp-bg); 
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: var(--erp-text-main);
    }

    .page-wrapper {
        padding: 24px 28px 36px;
    }

    .inst-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        padding-bottom: 20px;
        margin-bottom: 24px;
        border-bottom: 1px solid var(--erp-border, #d9e0e7); 
    }

    /* Header left side flex layout */
    .inst-header-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    /* Header icon box */
    .inst-header-icon {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #edf3f8;
        border: 1px solid #dce6ee;
        border-radius: 6px;
        color: var(--erp-navy);
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .badge-category {
        background: #edf3f8;
        color: var(--erp-navy);
        border: 1px solid var(--erp-border);
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.68rem;
    }

    .erp-tabs .nav-link { 
        border-radius: 6px; 
        color: var(--erp-text-muted); 
        font-weight: 600; 
        padding: 0.5rem 1.25rem;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        border: 1px solid var(--erp-border);
        background: #ffffff;
    }
    .erp-tabs .nav-link.active { 
        background: var(--erp-navy); 
        color: #ffffff; 
        border-color: var(--erp-navy);
        box-shadow: var(--erp-shadow-sm);
    }

    .global-search-wrapper {
        position: relative;
        max-width: 420px;
    }

    .global-search-input {
        border-radius: 6px;
        border: 1px solid var(--erp-border);
        padding: 0.55rem 0.85rem 0.55rem 2.25rem;
        font-size: 0.85rem;
        width: 100%;
        background: #ffffff;
        transition: all 0.15s ease;
    }

    .global-search-input:focus {
        border-color: var(--erp-navy);
        box-shadow: 0 0 0 3px rgba(18, 59, 99, 0.12);
        outline: none;
    }

    .global-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--erp-text-muted);
        font-size: 0.85rem;
    }

    .table thead th { 
        background-color: #f8fafc; 
        border-bottom: 1px solid var(--erp-border);
        color: var(--erp-text-muted);
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.85rem 1rem;
    }

    .vendor-row { transition: background-color 0.15s ease; border-bottom: 1px solid var(--erp-border) !important; }
    .vendor-row:hover { background-color: #f1f5f9 !important; }
    .vendor-row.search-match { background-color: #e0f2fe !important; }

    .vendor-avatar {
        width: 38px; height: 38px;
        background: #edf3f8;
        color: var(--erp-navy);
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        border-radius: 6px;
        border: 1px solid var(--erp-border);
        font-size: 0.78rem;
    }

    .vendor-name-btn { 
        background: none; border: none; padding: 0; text-align: left;
        color: var(--erp-text-main); font-weight: 700; font-size: 0.875rem;
    }
    .vendor-name-btn:hover {
        color: var(--erp-navy);
        text-decoration: underline;
    }

    .btn-action {
        width: 32px; height: 32px; display: inline-flex; 
        align-items: center; justify-content: center;
        border-radius: 6px; border: 1px solid var(--erp-border); background: #ffffff;
        font-size: 0.85rem;
    }

    .btn-erp-primary {
        background: var(--erp-navy);
        border-color: var(--erp-navy);
        color: #ffffff;
        font-weight: 600;
        border-radius: 6px;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }

    .detail-card {
        background: #f8fafc;
        border: 1px solid var(--erp-border);
        border-radius: 6px;
        padding: 12px 16px;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .detail-label {
        font-size: 0.68rem;
        color: var(--erp-text-muted);
        font-weight: 700;
        text-transform: uppercase;
        display: block;
    }

    .detail-value {
        font-size: 0.875rem;
        color: var(--erp-text-main);
        font-weight: 600;
        display: block;
        word-break: break-word;
    }
</style>

<div class="container-fluid page-wrapper">

    <!-- Header Block -->
    <div class="inst-header">
        <div class="inst-header-left">
            <!-- Icon Box -->
            <div class="inst-header-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1" style="color: var(--erp-text-main);">Service Partners</h4>
                <p class="text-muted extra-small mb-0">Unified vendor directory with global cross-category lookup.</p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Global Cross-Category Search Bar -->
            <div class="global-search-wrapper">
                <i class="bi bi-search global-search-icon"></i>
                <input type="text" id="globalVendorSearch" class="global-search-input" placeholder="Search vendors...">
            </div>
            <a href="vendor_manager.php?type=<?= urlencode($active_tab) ?>" class="btn btn-erp-primary shadow-sm" id="btnRegisterVendor">
                <i class="bi bi-plus-lg me-1"></i> Register Vendor
            </a>
        </div>
    </div>

    <div class="card border rounded-2 shadow-sm overflow-hidden" style="background: var(--erp-card-bg); border-color: var(--erp-border) !important;">
        <!-- Category Navigation Tabs -->
        <div class="card-header bg-white border-bottom p-3 pb-3" style="border-color: var(--erp-border) !important;">
            <ul class="nav nav-pills erp-tabs gap-2" id="vendorTabs">
                <?php foreach (['Computer', 'Furniture', 'Electricals'] as $tab): ?>
                    <li class="nav-item">
                        <button class="nav-link <?= ($tab === $active_tab) ? 'active' : '' ?>" 
                                data-bs-toggle="pill" 
                                data-bs-target="#tab-<?= strtolower($tab) ?>" 
                                data-category="<?= $tab ?>">
                            <?= $tab ?> 
                            <span class="badge bg-light text-dark border ms-1 extra-small"><?= count($grouped_vendors[$tab]) ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Tab Panes & Tables -->
        <div class="card-body p-0">
            <div class="tab-content">
                <?php foreach ($grouped_vendors as $cat => $vendors): ?>
                    <div class="tab-pane fade <?= ($cat === $active_tab) ? 'show active' : '' ?>" id="tab-<?= strtolower($cat) ?>">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 vendor-table" id="table-<?= strtolower($cat) ?>">
                                <thead>
                                    <tr>
                                        <th class="ps-4 py-3">Ref</th>
                                        <th class="py-3">Vendor Information</th>
                                        <th class="py-3">Primary Contact</th>
                                        <th class="text-end pe-4 py-3">Operations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vendors)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted extra-small">No vendors registered in <?= $cat ?> stock.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $count = 1;
                                        foreach ($vendors as $row): 
                                            $words = explode(" ", $row['vendor_name']);
                                            $initials = "";
                                            foreach ($words as $w) { $initials .= strtoupper(substr($w, 0, 1)); }
                                            $initials = substr($initials, 0, 3);
                                        ?>
                                        <tr class="vendor-row" data-vendor-id="<?= $row['id'] ?>">
                                            <td class="ps-4">
                                                <span class="badge bg-light text-muted border px-2 py-1 extra-small">#<?= str_pad($count++, 2, '0', STR_PAD_LEFT); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="vendor-avatar"><?= $initials ?></div>
                                                    <div>
                                                        <button class="vendor-name-btn" onclick='viewVendorDetails(<?= json_encode($row) ?>)'>
                                                            <?= htmlspecialchars($row['vendor_name']); ?>
                                                        </button>
                                                        <div class="text-muted extra-small" style="font-size: 0.75rem;"><?= htmlspecialchars($row['email'] ?: 'No email provided'); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark extra-small"><?= htmlspecialchars($row['contact_person'] ?: 'N/A'); ?></div>
                                                <div class="text-muted extra-small"><?= htmlspecialchars($row['phone_number'] ?: '--'); ?></div>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="vendor_manager.php?edit=<?= $row['id'] ?>&type=<?= urlencode($cat) ?>" 
                                                   class="btn-action text-primary me-1" title="Edit Vendor">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <button class="btn-action text-danger delete-vendor-btn" 
                                                        data-id="<?= $row['id']; ?>" 
                                                        data-type="<?= urlencode($cat); ?>"
                                                        data-name="<?= htmlspecialchars($row['vendor_name']); ?>" title="Delete">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- VENDOR DETAILS MODAL -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2 border-0 shadow-lg">
            <div class="modal-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="vendor-avatar mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.1rem;" id="det_initial">?</div>
                    <h5 class="fw-bold text-dark mb-1" id="det_name">Vendor Name</h5>
                    <span class="badge-category" id="det_cat">Category</span>
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
                            <span class="detail-value" id="det_address">--</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-2">
                    <button type="button" class="btn btn-light border w-100 rounded-2 py-2 fw-semibold extra-small" data-bs-dismiss="modal">Close Profile</button>
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
let dataTables = {};

$(document).ready(function() {
    if(document.getElementById('detailsModal')){
        vendorModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    }

    // Initialize individual DataTables per Category Tab
    $('.vendor-table').each(function() {
        let tableId = $(this).attr('id');
        dataTables[tableId] = $(this).DataTable({
            "dom": 'rt<"p-3 d-flex justify-content-between align-items-center extra-small text-muted"ip>',
            "pageLength": 10,
            "order": [[1, 'asc']],
            "columnDefs": [
                { "orderable": false, "targets": [0, 3] }
            ]
        });
    });

    // Track active category and tab pane
    let defaultTabPaneId = $('#vendorTabs button.active').data('bs-target') || ('#tab-' + '<?= strtolower($active_tab) ?>');

    // Update defaultTabPaneId when user manually clicks a tab
    $('#vendorTabs button').on('click', function() {
        defaultTabPaneId = $(this).data('bs-target');
        let cat = $(this).data('category');
        $('#btnRegisterVendor').attr('href', 'vendor_manager.php?type=' + encodeURIComponent(cat));
    });

    // GLOBAL SEARCH LOGIC ACROSS ALL TABS
    $('#globalVendorSearch').on('keyup input', function() {
        let query = $(this).val().trim();
        
        // 1. Filter all DataTables instances
        $.each(dataTables, function(id, table) {
            table.search(query).draw();
        });

        if (query.length > 0) {
            let currentActiveTabPaneId = $('#vendorTabs button.active').data('bs-target');
            let currentActiveTableId = $(currentActiveTabPaneId).find('.vendor-table').attr('id');
            
            let activeMatchCount = dataTables[currentActiveTableId] ? dataTables[currentActiveTableId].rows({ search: 'applied' }).count() : 0;

            // If active tab has NO matches, switch to the first tab that does
            if (activeMatchCount === 0) {
                $.each(dataTables, function(tableId, table) {
                    let matchingRows = table.rows({ search: 'applied' }).count();
                    
                    if (matchingRows > 0) {
                        let parentPaneId = $('#' + tableId).closest('.tab-pane').attr('id');
                        // FIXED: Added '#' prefix to parentPaneId
                        let targetTabButton = $('#vendorTabs button[data-bs-target="#' + parentPaneId + '"]');
                        
                        if (targetTabButton.length) {
                            let tabTrigger = bootstrap.Tab.getOrCreateInstance(targetTabButton[0]);
                            tabTrigger.show();
                        }
                        return false; // Stop checking further tabs
                    }
                });
            }
        } else {
            // 2. SEARCH CLEARED: Revert back to the default tab
            let defaultTabButton = $('#vendorTabs button[data-bs-target="' + defaultTabPaneId + '"]');
            if (defaultTabButton.length && !defaultTabButton.hasClass('active')) {
                let tabTrigger = bootstrap.Tab.getOrCreateInstance(defaultTabButton[0]);
                tabTrigger.show();
            }
        }
    });

    // Delete confirmation handler
    $(document).on('click', '.delete-vendor-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const cat = $(this).data('type');

        Swal.fire({
            title: 'Terminate Partner?',
            text: `Are you sure you want to remove ${name}? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#123b63',
            cancelButtonColor: '#f1f5f9',
            cancelButtonText: '<span style="color: #64748b">Keep Partner</span>',
            confirmButtonText: 'Yes, Remove',
            customClass: {
                confirmButton: 'rounded-2 px-4',
                cancelButton: 'rounded-2 px-4'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `delete_vendor.php?id=${id}&type=${cat}`;
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