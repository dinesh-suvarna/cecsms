<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";
require_once "../admin/auth.php";

/* ---------- NOTIFY ---------- */
if (!function_exists('notify')) {
    function notify(string $type, string $msg): void {
        $_SESSION['swal_type'] = ($type === 'danger') ? 'error' : $type; 
        $_SESSION['swal_msg']  = $msg;
    }
}

$page_title = "Asset Registry";
$page_icon  = "bi-boxes";

/* ---------- UPDATE ---------- */
if(isset($_POST['update'])){
    $id   = intval($_POST['id']);
    $name = trim($_POST['item_name']);
    $cat  = $_POST['category'];
    $type = $_POST['stock_type'] ?? null;

    if(!empty($name)){
        try {
            if($type) {
                $stmt = $conn->prepare("UPDATE items_master SET item_name=?, category=?, stock_type=? WHERE id=?");
                $stmt->bind_param("sssi", $name, $cat, $type, $id);
            } else {
                $stmt = $conn->prepare("UPDATE items_master SET item_name=?, category=? WHERE id=?");
                $stmt->bind_param("ssi", $name, $cat, $id);
            }
            $stmt->execute();
            notify("success", "Updated successfully!");
        } catch(mysqli_sql_exception $e) {
            notify("danger", "Error updating record.");
        }
    }
    header("Location: items_master.php");
    exit;
}

/* ---------- ADD ITEM ---------- */
if(isset($_POST['submit'])){
    $item_name  = trim($_POST['item_name']);
    $category   = $_POST['category'] ?? '';
    $stock_type = $_POST['stock_type'] ?? 'serial';

    if(empty($item_name)){
        notify("danger","Item name is required.");
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO items_master (item_name, category, stock_type) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $item_name, $category, $stock_type);
            $stmt->execute();
            notify("success","Item added successfully!");
        } catch(mysqli_sql_exception $e){
            notify("danger", $e->getCode()==1062 ? "Item already exists!" : "Database error.");
        }
    }
    header("Location: items_master.php");
    exit;
}

/* ---------- DELETE ---------- */
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    $check = $conn->prepare("SELECT id FROM stock_details WHERE stock_item_id=? LIMIT 1");
    $check->bind_param("i",$id);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        notify("danger","Cannot delete. Linked to existing stock records.");
    } else {
        $stmt = $conn->prepare("DELETE FROM items_master WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        notify("success","Deleted successfully.");
    }
    header("Location: items_master.php");
    exit;
}

/* ---------- DATA QUERY ---------- */
$query = "
    SELECT 
        im.*,
        (SELECT COUNT(*) FROM stock_details sd_count WHERE sd_count.stock_item_id = im.id) as stock_exists,
        IFNULL((SELECT SUM(quantity) FROM stock_details sd_sum WHERE sd_sum.stock_item_id = im.id), 0) as total_purchased,
        IFNULL((
            SELECT SUM(dd.quantity - IFNULL(dd.returned_quantity, 0))
            FROM dispatch_details dd
            JOIN stock_details sd2 ON dd.stock_detail_id = sd2.id
            WHERE sd2.stock_item_id = im.id
        ), 0) as total_dispatched
    FROM items_master im
    ORDER BY im.category ASC, im.item_name ASC
";
$itemsResult = $conn->query($query);

/* Group items by category */
$categories = [];
while ($row = $itemsResult->fetch_assoc()) {
    $cat = !empty($row['category']) ? $row['category'] : 'Uncategorized';
    $categories[$cat][] = $row;
}

ob_start();
?>

<style>
/* Enterprise UI Theme Tokens */
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

/* Page Layout Container */
.erp-page-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 20px 40px;
}

/* Header Styling */
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
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    background: #edf3f8; border: 1px solid #dce6ee; border-radius: 5px;
    color: var(--erp-navy); font-size: 1.1rem;
}
.inst-header h3 { margin: 0; color: var(--erp-navy-dark); font-size: 1.18rem; font-weight: 650; }
.inst-header p { margin: 3px 0 0; color: var(--erp-muted); font-size: .76rem; }

/* Panels & Containers */
.inst-panel {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 5px;
    box-shadow: var(--erp-shadow);
}
.inst-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 18px;
    border-bottom: 1px solid var(--erp-border);
    background: #f5f7f9;
}
.inst-panel-title { color: var(--erp-navy-dark); font-size: .82rem; font-weight: 650; }

/* Toolbar */
.erp-toolbar {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 5px;
    padding: 8px 12px;
    box-shadow: var(--erp-shadow);
}

/* Buttons */
.btn-erp-primary {
    height: 34px; background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-erp-primary:hover { background: var(--erp-navy-dark); color: #fff; }

.btn-erp-cancel {
    height: 34px; border: 1px solid #c8d2db; background: #fff;
    color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-erp-cancel:hover { background: #f5f7f9; color: #334451; }

/* Accordion Stack */
.cat-stack-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 5px !important;
    margin-bottom: 0.75rem;
    background: #ffffff;
    box-shadow: var(--erp-shadow);
    overflow: hidden;
}

.cat-header-btn {
    background-color: #ffffff !important;
    border: none;
    padding: 0.85rem 1.15rem;
    box-shadow: none !important;
}
.cat-header-btn:not(.collapsed) {
    background-color: #f5f7f9 !important;
    border-bottom: 1px solid var(--erp-border);
}

.cat-icon-box {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    background: #edf3f8;
    border: 1px solid #dce6ee;
    color: var(--erp-navy);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

/* Data Tables */
.table-erp { font-size: .78rem; margin: 0; }
.table-erp thead th {
    background: #f5f7f9; color: #536575; font-size: .65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--erp-border);
    padding: 9px 16px;
}
.table-erp tbody td { padding: 9px 16px; border-bottom: 1px solid var(--erp-border); vertical-align: middle; }
.table-erp tbody tr:last-child td { border-bottom: none; }

/* Badges & Pills */
.badge-erp { font-size: .65rem; font-weight: 600; padding: 3px 8px; border-radius: 4px; display: inline-block; }
.badge-erp-neutral { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.badge-erp-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.badge-erp-danger { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

.type-pill {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
}
.pill-serial { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.pill-nonserial { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

/* Action Buttons */
.action-btn-erp {
    width: 28px;
    height: 28px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    border: 1px solid var(--erp-border);
    background: #ffffff;
    transition: all 0.15s ease;
}
.action-btn-erp:hover {
    background: #f5f7f9;
    color: var(--erp-navy-dark);
}
.action-btn-erp.danger:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}

#editModal { z-index: 1056 !important; }

/* Dark Mode Overrides */
[data-bs-theme="dark"] {
    --erp-bg: #101a24;
    --erp-white: #172534;
    --erp-text: #edf3f7;
    --erp-muted: #9aabb9;
    --erp-border: #2d3e4e;
    --erp-navy: #8eafc9;
    --erp-navy-dark: #dce8f0;
}
[data-bs-theme="dark"] .inst-header h3 { color: #edf3f7; }
[data-bs-theme="dark"] .inst-header-icon { background: #203445; border-color: #33495a; color: #b8d0e2; }
[data-bs-theme="dark"] .inst-panel,
[data-bs-theme="dark"] .erp-toolbar,
[data-bs-theme="dark"] .cat-stack-card { background: #142230 !important; }
[data-bs-theme="dark"] .inst-panel-header,
[data-bs-theme="dark"] .cat-header-btn:not(.collapsed) { background: #101a24 !important; border-color: var(--erp-border); }
[data-bs-theme="dark"] .cat-header-btn { background-color: #142230 !important; }
[data-bs-theme="dark"] .table-erp thead th { background: #101a24; border-color: var(--erp-border); color: var(--erp-muted); }
[data-bs-theme="dark"] .table-erp tbody td { border-color: var(--erp-border); color: var(--erp-text); }
[data-bs-theme="dark"] .btn-erp-cancel,
[data-bs-theme="dark"] .action-btn-erp { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
[data-bs-theme="dark"] .modal-content { background: #142230; border-color: var(--erp-border); color: #edf3f7; }
</style>

<div class="erp-page-container">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi <?= $page_icon ?>"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($page_title) ?></h3>
                <p>Master catalog of registerable assets, grouped by category.</p>
            </div>
        </div>
        <button class="btn btn-erp-primary px-3" type="button" data-bs-toggle="collapse" data-bs-target="#addAssetCollapse">
            <i class="bi bi-plus-lg me-1.5"></i> Add Asset Category
        </button>
    </div>

    <!-- COLLAPSIBLE ADD FORM -->
    <div class="collapse mb-4" id="addAssetCollapse">
        <div class="inst-panel p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="inst-panel-title">
                    <i class="bi bi-plus-circle me-1.5 text-primary"></i> Register New Asset Type
                </div>
                <button type="button" class="btn-close small" data-bs-toggle="collapse" data-bs-target="#addAssetCollapse"></button>
            </div>
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-secondary">Tracking Type</label>
                        <select name="stock_type" class="form-select form-select-sm">
                            <option value="serial">Serialized (Track by Serial No.)</option>
                            <option value="non_serial">Non-Serialized (Bulk Quantity)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-secondary">Category Group</label>
                        <select name="category" class="form-select form-select-sm">
                            <option>Computer</option>
                            <option>Accessory</option>
                            <option>Component</option>
                            <option>Networking</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold text-secondary">Item Description / Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" class="form-control form-control-sm" placeholder="e.g. Dell Latitude 3420" required>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                    <button type="button" class="btn btn-erp-cancel px-3" data-bs-toggle="collapse" data-bs-target="#addAssetCollapse">Cancel</button>
                    <button type="submit" name="submit" class="btn btn-erp-primary px-3">
                        <i class="bi bi-check-lg me-1"></i> Save Asset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SEARCH TOOLBAR -->
    <div class="erp-toolbar mb-3">
        <div class="row g-2 align-items-center">
            <div class="col flex-grow-1">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="assetSearch" class="form-control border-0 bg-transparent shadow-none" placeholder="Filter assets by name, category, or tracking type...">
                </div>
            </div>
            <div class="col-auto d-flex gap-1">
                <button id="resetSearch" class="btn btn-erp-cancel px-2.5" title="Clear Search">
                    <i class="bi bi-x-lg"></i>
                </button>
                <button id="collapseAllBtn" class="btn btn-erp-cancel px-3" title="Collapse All">
                    <i class="bi bi-arrows-collapse me-1"></i> Collapse All
                </button>
            </div>
        </div>
    </div>

    <!-- ACCORDION STACK -->
    <div id="categoryAccordion">
        <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $catName => $catItems): 
                $catId = "cat_" . md5($catName);
            ?>
                <div class="accordion-item cat-stack-card">
                    <h2 class="accordion-header">
                        <button class="accordion-button cat-header-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $catId ?>">
                            <div class="d-flex justify-content-between align-items-center w-100 me-2">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="cat-icon-box">
                                        <i class="bi bi-folder2-open"></i>
                                    </div>
                                    <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($catName) ?></span>
                                </div>
                                <span class="badge-erp badge-erp-neutral">
                                    <?= count($catItems) ?> Items
                                </span>
                            </div>
                        </button>
                    </h2>
                    <div id="<?= $catId ?>" class="accordion-collapse collapse" data-bs-parent="#categoryAccordion" data-parent-id="#categoryAccordion">
                        <div class="accordion-body p-0 bg-white border-top">
                            <div class="table-responsive">
                                <table class="table table-erp align-middle text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Asset Name</th>
                                            <th>Tracking Type</th>
                                            <th class="text-center">Total In</th>
                                            <th class="text-center">Dispatched</th>
                                            <th class="text-center">Available</th>
                                            <th class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($catItems as $row): 
                                            $available = $row['total_purchased'] - $row['total_dispatched'];
                                            $isSerial = ($row['stock_type'] == 'serial');
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold text-dark me-2 d-inline-block"><?= htmlspecialchars($row['item_name']) ?></div>
                                                    <?php if($row['stock_exists'] > 0): ?>
                                                        <i class="bi bi-lock-fill text-muted small" title="Stock records exist. Type locked."></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="type-pill <?= $isSerial ? 'pill-serial' : 'pill-nonserial' ?>">
                                                        <?= $isSerial ? 'Serialized' : 'Non-Serialized' ?>
                                                    </span>
                                                </td>
                                                <td class="text-center fw-semibold text-dark"><?= number_format($row['total_purchased']) ?></td>
                                                <td class="text-center text-danger fw-semibold"><?= number_format($row['total_dispatched']) ?></td>
                                                <td class="text-center">
                                                    <span class="badge-erp <?= ($available > 0 ? 'badge-erp-success' : 'badge-erp-danger') ?>">
                                                        <?= number_format($available) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <div class="d-inline-flex gap-1">
                                                        <button type="button" class="action-btn-erp" title="Edit Asset"
                                                                onclick="editItem(<?= $row['id'] ?>,'<?= addslashes($row['item_name']) ?>','<?= htmlspecialchars($row['category'], ENT_QUOTES) ?>','<?= $row['stock_type'] ?>', <?= $row['stock_exists'] ?>)">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <button type="button" class="action-btn-erp danger delete-btn" data-id="<?= $row['id'] ?>" title="Delete Asset">
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="inst-panel p-4 text-center text-muted">
                <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
                <p class="mb-0 small fw-medium">No asset records registered.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- EDIT MODAL STRUCTURE -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2 border shadow-sm">
            <form method="POST" id="editForm">
                <div class="modal-header border-bottom p-3">
                    <h6 class="fw-bold m-0" id="editModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Item Details</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <input type="hidden" name="id" id="eid">
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary">Item Name</label>
                        <input type="text" name="item_name" id="ename" class="form-control form-control-sm rounded-1" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary">Category</label>
                        <select name="category" id="ecat" class="form-select form-select-sm rounded-1">
                            <option>Computer</option>
                            <option>Accessory</option>
                            <option>Component</option>
                            <option>Networking</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary">Stock Tracking Type</label>
                        <select name="stock_type" id="etype" class="form-select form-select-sm rounded-1">
                            <option value="serial">Serialized (Track by Serial No.)</option>
                            <option value="non_serial">Non-Serialized (Bulk Quantity)</option>
                        </select>
                        <small id="typeWarning" class="text-muted" style="font-size: 11px;"></small>
                    </div>
                </div>
                <div class="modal-footer border-top p-2 px-3">
                    <button type="button" class="btn btn-erp-cancel px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update" class="btn btn-erp-primary px-3">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if(isset($_SESSION['swal_msg'])): 
    $type = $_SESSION['swal_type'] == 'danger' ? 'error' : $_SESSION['swal_type'];
    $msg  = $_SESSION['swal_msg'];
    unset($_SESSION['swal_type'], $_SESSION['swal_msg']);
?>
<script>
    Swal.fire({
        icon: '<?= $type ?>',
        title: '<?= ($type == "success" ? "Done!" : "Notice") ?>',
        text: '<?= htmlspecialchars($msg) ?>',
        timer: 3000,
        showConfirmButton: false
    });
</script>
<?php endif; ?>

<script>
$(document).ready(function() {
    // CRITICAL FIX: Move modal to document body to break out of layout wrapper/overflow clipping
    $('#editModal').appendTo('body');

    // Search filter
    $('#assetSearch').on('input', function() {
        let query = $(this).val().trim().toLowerCase();
        
        if (query === "") { 
            $('.accordion-collapse').each(function() {
                let parentId = $(this).data('parent-id');
                if (parentId) {
                    $(this).attr('data-bs-parent', parentId);
                }
            });
            $('.accordion-collapse').removeClass('show');
            $('.cat-stack-card, .table-erp tbody tr').css('display', '');
            return; 
        }

        $('.cat-stack-card .accordion-collapse').removeAttr('data-bs-parent');

        $('.cat-stack-card').each(function() {
            let $catCard = $(this);
            let catName = $catCard.find('.cat-icon-box').next('span').text().trim().toLowerCase();
            let isCatMatch = catName.includes(query);
            let matchingRowsCount = 0;

            $catCard.find('.table-erp tbody tr').each(function() {
                let $row = $(this);
                let itemName = $row.find('td:nth-child(1)').text().trim().toLowerCase();
                let trackingType = $row.find('td:nth-child(2)').text().trim().toLowerCase();

                if (itemName.includes(query) || trackingType.includes(query) || isCatMatch) {
                    $row.css('display', '');
                    matchingRowsCount++;
                } else {
                    $row.css('display', 'none');
                }
            });

            if (matchingRowsCount > 0) {
                $catCard.css('display', 'block');
                $catCard.find('.accordion-collapse').addClass('show');
            } else {
                $catCard.css('display', 'none');
                $catCard.find('.accordion-collapse').removeClass('show');
            }
        });
    });

    $('#resetSearch').click(function() { 
        $('#assetSearch').val('').trigger('input'); 
    });

    $('#collapseAllBtn').click(function() { 
        $('.accordion-collapse').removeClass('show'); 
    });

    // Delete handler
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete Asset?',
            text: "This item will be deleted if no stock records exist.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `items_master.php?delete=${id}`;
            }
        });
    });
});

function editItem(id, name, cat, type, stockCount) {
    $('#eid').val(id);
    $('#ename').val(name);
    $('#ecat').val(cat);
    
    const $typeSelect = $('#etype');
    const $typeWarning = $('#typeWarning');
    
    $typeSelect.val(type);

    if (stockCount > 0) {
        $typeSelect.prop('disabled', true);
        $typeWarning.html("<i class='bi bi-lock-fill'></i> Type locked: " + stockCount + " stock record(s) exist.").attr('class', 'text-danger small d-block mt-1');
    } else {
        $typeSelect.prop('disabled', false);
        $typeWarning.text("No stock linked. You can safely change the type.").attr('class', 'text-muted small d-block mt-1');
    }
    
    // Explicit Modal Call using raw DOM element to prevent jQuery state conflicts
    var modalElement = document.getElementById('editModal');
    var editModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    editModal.show();
}
</script>

<?php
$main_content = ob_get_clean();
include "stocklayout.php";
?>