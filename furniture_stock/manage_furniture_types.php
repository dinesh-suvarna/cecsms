<?php
require_once __DIR__ . "/../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$user_role =$_SESSION['role'];
$user_division =$_SESSION['division_id'] ?? 0;

$message = "";

// --- DELETE LOGIC ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $check_stock =$conn->query("SELECT id FROM furniture_stock WHERE furniture_item_id = $delete_id LIMIT 1");
    
    if ($check_stock->num_rows > 0) {$message = "usage_error"; 
    } else {
        if ($conn->query("DELETE FROM furniture_items WHERE id = $delete_id")) {
            $message = "deleted";
        }
    }
}

// --- ADD / UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_type'])) {
    $raw_name = mysqli_real_escape_string($conn, $_POST['item_name']);$name = ucwords(strtolower(trim($raw_name)));$item_code = mysqli_real_escape_string($conn, strtoupper(trim($_POST['item_code'])));
    
    if (!empty($_POST['edit_id'])) {
        $edit_id = (int)$_POST['edit_id'];
        if ($conn->query("UPDATE furniture_items SET item_name = '$name', item_code = '$item_code' WHERE id =$edit_id")) {
            $message = "updated";
        }
    } else {
        $check =$conn->query("SELECT id FROM furniture_items WHERE item_name = '$name' OR (item_code = '$item_code' AND item_code != '')");
        if ($check->num_rows > 0) {$message = "exists";
        } else {
            if ($conn->query("INSERT INTO furniture_items (item_name, item_code) VALUES ('$name', '$item_code')")) {
                $message = "success";
            }
        }
    }
}

// --- DATA QUERY & STATS ---
$stats_query =$conn->query("
    SELECT 
        COUNT(im.id) as total_types,
        SUM(CASE WHEN (SELECT COUNT(*) FROM furniture_stock fs WHERE fs.furniture_item_id = im.id) > 0 THEN 1 ELSE 0 END) as used_types,
        IFNULL((SELECT SUM(total_qty) FROM furniture_stock), 0) as total_quantity
    FROM furniture_items im
");
$stats =$stats_query->fetch_assoc();

$items =$conn->query("
    SELECT 
        im.*,
        (SELECT COUNT(*) FROM furniture_stock fs WHERE fs.furniture_item_id = im.id) as stock_exists,
        IFNULL((SELECT SUM(fs.total_qty) FROM furniture_stock fs WHERE fs.furniture_item_id = im.id), 0) as total_stock
    FROM furniture_items im 
    ORDER BY im.item_name ASC
");

$page_title = "Furniture Item Registry";
ob_start(); 
?>

<!-- DataTables CSS CDN -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
:root {
    --erp-navy: #173f63;
    --erp-navy-dark: #102f4a;
    --erp-text: #263746;
    --erp-muted: #71808f;
    --erp-border: #dce3e9;
    --erp-bg: #f5f7f9;
    --erp-white: #ffffff;
    --erp-shadow: 0 1px 3px rgba(20, 40, 60, .06);
}

.erp-page-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 20px 40px;
}
.furniture-icon svg {
    width: 35px;
    height: 35px;
}

/* Header */
.inst-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding-bottom: 20px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--erp-border);
}
.inst-header-left { display: flex; align-items: center; gap: 14px; }
.inst-header-icon {
    width: 48px; height: 48px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #edf3f8 0%, #e2ecf5 100%);
    border: 1px solid #cddde9; border-radius: 8px;
    color: var(--erp-navy); font-size: 1.35rem;
    box-shadow: 0 2px 4px rgba(23, 63, 99, 0.05);
}
.inst-header h3 { margin: 0; color: var(--erp-navy-dark); font-size: 1.25rem; font-weight: 700; }
.inst-header p { margin: 3px 0 0; color: var(--erp-muted); font-size: .8rem; }

/* Enhanced Stat Cards */
.stat-widget-card {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 8px;
    padding: 14px 18px;
    box-shadow: var(--erp-shadow);
    position: relative;
    overflow: hidden;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-widget-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(20, 40, 60, .08);
}
.stat-widget-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 4px;
    background: var(--card-accent, var(--erp-navy));
}
.stat-widget-card .title { 
    font-size: 0.7rem; 
    text-transform: uppercase; 
    font-weight: 700; 
    color: var(--erp-muted); 
    letter-spacing: 0.5px; 
}
.stat-widget-card .value { 
    font-size: 1.4rem; 
    font-weight: 700; 
    color: var(--erp-navy-dark); 
    margin-top: 4px; 
}
.stat-widget-icon {
    position: absolute;
    right: 14px;
    bottom: 12px;
    font-size: 1.6rem;
    opacity: 0.15;
    color: var(--card-accent, var(--erp-navy));
}

/* Panels */
.inst-panel {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 8px;
    box-shadow: var(--erp-shadow);
    padding: 20px;
}

/* DataTables Custom Styling Adjustments */
.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid var(--erp-border);
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 0.88rem;
    outline: none;
    box-shadow: none;
}
.dataTables_wrapper .dataTables_filter input:focus,
.dataTables_wrapper .dataTables_length select:focus {
    border-color: var(--erp-navy);
    box-shadow: 0 0 0 3px rgba(23, 63, 99, 0.1);
}
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
    font-size: 0.85rem;
    padding-top: 15px !important;
}
.dataTables_wrapper .dataTables_length select.form-select,
.dataTables_wrapper .dataTables_length select {
    padding-right: 2rem !important;
    background-position: right 0.5rem center !important;
    background-size: 14px 12px !important;
    text-indent: 0.01px;
    text-overflow: '';
}
.page-item.active .page-link {
    background-color: var(--erp-navy) !important;
    border-color: var(--erp-navy) !important;
}
.page-link {
    color: var(--erp-navy);
    border-radius: 4px;
    margin: 0 2px;
}

/* Tables */
.table-erp { font-size: 0.92rem; margin: 0; width: 100% !important; }
.table-erp thead th {
    background: #f5f7f9; color: #536575; font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--erp-border);
    padding: 14px 18px;
}
.table-erp tbody td { padding: 16px 18px; border-bottom: 1px solid var(--erp-border); vertical-align: middle; }

/* Buttons */
.btn-erp-primary {
    height: 38px; background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 6px !important; font-size: .78rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.btn-erp-primary:hover { background: var(--erp-navy-dark); color: #fff; }

.btn-erp-cancel {
    height: 38px; border: 1px solid #c8d2db; background: #fff;
    color: #596b7a; border-radius: 6px !important; font-size: .78rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-erp-cancel:hover { background: #f5f7f9; color: #334451; }

.action-btn-erp {
    width: 32px; height: 32px;
    border-radius: 4px;
    display: inline-flex; align-items: center; justify-content: center;
    color: #64748b; border: 1px solid var(--erp-border); background: #ffffff;
    transition: all 0.15s ease;
}
.action-btn-erp:hover { background: #f5f7f9; color: var(--erp-navy-dark); }
.action-btn-erp.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

#editModal { z-index: 1056 !important; }
</style>

<div class="erp-page-container">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <div class="icon-wrapper furniture-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10 22h40"/>
                        <path d="M14 22v27"/>
                        <path d="M46 22v27"/>
                        <path d="M37 29v13"/>
                        <path d="M37 29h10"/>
                        <path d="M47 29v13"/>
                        <path d="M34 42h16"/>
                        <path d="M37 42v10"/>
                        <path d="M47 42v10"/>
                        <path d="M14 43h32"/>
                    </svg>
                </div>
            </div>
            <div>
                <h3 class="mb-0"><?= htmlspecialchars($page_title) ?></h3>
                <p>Manage and verify furniture classifications and identification codes.</p>
            </div>
        </div>
        <button class="btn btn-erp-primary px-3" type="button" data-bs-toggle="collapse" data-bs-target="#addFurnitureCollapse">
            <i class="bi bi-plus-lg me-1.5"></i> Add Furniture Item
        </button>
    </div>

    <!-- STATS SUMMARY BAR -->
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-6">
            <div class="stat-widget-card" style="--card-accent: #173f63;">
                <div class="title">Total Item Types</div>
                <div class="value"><?= $stats['total_types'] ?? 0 ?></div>
                <i class="bi bi-boxes stat-widget-icon"></i>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="stat-widget-card" style="--card-accent: #2563eb;">
                <div class="title">Active in Stock</div>
                <div class="value text-primary"><?= $stats['used_types'] ?? 0 ?></div>
                <i class="bi bi-shield-check stat-widget-icon"></i>
            </div>
        </div>
        <div class="col-md-4 col-12">
            <div class="stat-widget-card" style="--card-accent: #16a34a;">
                <div class="title">Total Stock Quantity</div>
                <div class="value text-success"><?= $stats['total_quantity'] ?? 0 ?></div>
                <i class="bi bi-stack stat-widget-icon"></i>
            </div>
        </div>
    </div>

    <!-- COLLAPSIBLE ADD FORM -->
    <div class="collapse mb-4" id="addFurnitureCollapse">
        <div class="inst-panel">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="fw-bold text-dark">
                    <i class="bi bi-plus-circle me-1.5 text-primary"></i> Register New Furniture Item
                </div>
                <button type="button" class="btn-close small" data-bs-toggle="collapse" data-bs-target="#addFurnitureCollapse"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="save_type" value="1">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" class="form-control form-control-sm" placeholder="e.g. Office Chair" required style="text-transform: capitalize;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Item Code <span class="text-danger">*</span></label>
                        <input type="text" name="item_code" class="form-control form-control-sm" placeholder="e.g. CHR-01" required style="text-transform: uppercase;">
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                    <button type="button" class="btn btn-erp-cancel px-3" data-bs-toggle="collapse" data-bs-target="#addFurnitureCollapse">Cancel</button>
                    <button type="submit" class="btn btn-erp-primary px-3">
                        <i class="bi bi-check-lg me-1"></i> Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLE PANEL WITH DATATABLES -->
    <div class="inst-panel">
        <div class="table-responsive">
            <table class="table table-erp align-middle mb-0" id="furnitureTable">
                <thead>
                    <tr>
                        <th class="ps-3" style="width: 70px;">SL.NO</th>
                        <th>ITEM NAME</th>
                        <th>ITEM CODE</th>
                        <th class="text-center">STOCK COUNT</th>
                        <th class="text-end pe-3" style="width: 120px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if($items->num_rows > 0):$sl = 1;
                        while($row =$items->fetch_assoc()): 
                    ?>
                    <tr>
                        <td class="ps-3 text-muted"><?= $sl++ ?></td>
                        <td class="fw-semibold text-dark">
                            <?= htmlspecialchars($row['item_name']) ?>
                            <?php if($row['stock_exists'] > 0): ?>
                                <i class="bi bi-lock-fill text-primary small ms-1" title="Stock records exist."></i>
                            <?php endif; ?>
                        </td>
                        <td><span class="text-dark fw-semibold"><?= htmlspecialchars($row['item_code']) ?></span></td>
                        <td class="text-center fw-semibold text-dark"><?= $row['total_stock'] ?></td>
                        <td class="text-end pe-3">
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="action-btn-erp" title="Edit Item" 
                                        onclick="editItem(<?= $row['id'] ?>, '<?= addslashes($row['item_name']) ?>', '<?= addslashes($row['item_code']) ?>')">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="action-btn-erp danger delete-btn" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['item_name'], ENT_QUOTES) ?>" title="Delete Item">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border shadow-sm">
            <form method="POST" id="editForm">
                <input type="hidden" name="save_type" value="1">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-header border-bottom p-3">
                    <h6 class="fw-bold m-0"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Furniture Item</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary mb-1">Item Name</label>
                        <input type="text" name="item_name" id="edit_item_name" class="form-control form-control-sm rounded-1" required style="text-transform: capitalize;">
                    </div>
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary mb-1">Item Code</label>
                        <input type="text" name="item_code" id="edit_item_code" class="form-control form-control-sm rounded-1" required style="text-transform: uppercase;">
                    </div>
                </div>
                <div class="modal-footer border-top p-2 px-3">
                    <button type="button" class="btn btn-erp-cancel px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-erp-primary px-3">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {$('#editModal').appendTo('body');

    // Initialize DataTables with 10 entries per page default
    $('#furnitureTable').DataTable({
        "pageLength": 10,
        "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
        "language": {
            "search": "_INPUT_",
            "searchPlaceholder": "Search items...",
            "lengthMenu": "Show _MENU_ entries"
        },
        "columnDefs": [
            { "orderable": false, "targets": 4 } // Disable sorting on Actions column
        ]
    });

    // Delete handler
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete "${name}".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `?delete_id=${id}`;
            }
        });
    });
});

function editItem(id, name, code) {
    $('#edit_id').val(id);
    $('#edit_item_name').val(name);
    $('#edit_item_code').val(code);
    
    var modalElement = document.getElementById('editModal');
    var editModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    editModal.show();
}

<?php if($message == "success"): ?>
    Swal.fire({ icon: 'success', title: 'Added', text: 'Furniture type registered!', timer: 1500, showConfirmButton: false });
<?php elseif($message == "updated"): ?>
    Swal.fire({ icon: 'success', title: 'Updated', text: 'Changes saved!', timer: 1500, showConfirmButton: false });
<?php elseif($message == "deleted"): ?>
    Swal.fire({ icon: 'success', title: 'Deleted', text: 'Item removed.', timer: 1500, showConfirmButton: false });
<?php elseif($message == "usage_error"): ?>
    Swal.fire({ icon: 'error', title: 'Blocked', text: 'Item is linked to stock records.' });
<?php elseif($message == "exists"): ?>
    Swal.fire({ icon: 'warning', title: 'Duplicate', text: 'Item Name or Code already exists.' });
<?php endif; ?>
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>