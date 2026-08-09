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
:root {
    --saas-border: #e2e8f0;
    --saas-primary: #0d6efd;
    --saas-text-muted: #64748b;
}

/* Ensure body can hold full screen overlays correctly */
#editModal {
    z-index: 1056 !important;
}

.saas-card {
    background: #ffffff;
    border: 1px solid var(--saas-border);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.saas-toolbar {
    background: #ffffff;
    border: 1px solid var(--saas-border);
    border-radius: 10px;
    padding: 6px 12px;
}

.cat-stack-card {
    border: 1px solid var(--saas-border) !important;
    border-radius: 12px !important;
    margin-bottom: 0.75rem;
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.cat-header-btn {
    background-color: #ffffff !important;
    border: none;
    padding: 0.85rem 1.15rem;
}
.cat-header-btn:not(.collapsed) {
    background-color: #f8fafc !important;
    border-bottom: 1px solid var(--saas-border);
}

.cat-icon-box {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #e0e7ff;
    color: #4338ca;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.saas-table {
    margin-bottom: 0;
    font-size: 0.83rem;
}
.saas-table thead th {
    background-color: #f8fafc;
    color: var(--saas-text-muted);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--saas-border);
    padding: 0.55rem 1rem;
}
.saas-table td {
    padding: 0.65rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.saas-table tbody tr:last-child td { border-bottom: none; }

.type-pill {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px;
    text-transform: uppercase;
}
.pill-serial { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.pill-nonserial { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

.action-btn-saas {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    border: 1px solid transparent;
    background: transparent;
    transition: all 0.15s ease;
}
.action-btn-saas:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
}
.action-btn-saas.danger:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}
</style>

<!-- PAGE HEADER -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h4 class="fw-bold m-0 text-dark">
            <i class="<?= $page_icon ?> text-primary me-2"></i><?= $page_title ?>
        </h4>
        <p class="text-muted small m-0">Master catalog of registerable assets, grouped by category.</p>
    </div>
    <button class="btn btn-primary btn-sm px-3 py-2 rounded-2 shadow-sm fw-semibold" style="background-color: var(--saas-primary); border: none;" type="button" data-bs-toggle="collapse" data-bs-target="#addAssetCollapse">
        <i class="bi bi-plus-lg me-1"></i> Add Asset Category
    </button>
</div>

<!-- COLLAPSIBLE ADD FORM -->
<div class="collapse mb-3" id="addAssetCollapse">
    <div class="saas-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold m-0 text-dark">
                <i class="bi bi-plus-circle text-primary me-1.5"></i> Register New Asset Type
            </h6>
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

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-sm btn-light border rounded-2 px-3" data-bs-toggle="collapse" data-bs-target="#addAssetCollapse">Cancel</button>
                <button type="submit" name="submit" class="btn btn-sm btn-primary rounded-2 px-3" style="background-color: var(--saas-primary); border: none;">
                    <i class="bi bi-check-lg me-1"></i> Save Asset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SEARCH TOOLBAR -->
<div class="saas-toolbar mb-3">
    <div class="row g-2 align-items-center">
        <div class="col flex-grow-1">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="assetSearch" class="form-control border-0 bg-transparent shadow-none" placeholder="Filter assets by name, category, or tracking type...">
            </div>
        </div>
        <div class="col-auto d-flex gap-1">
            <button id="resetSearch" class="btn btn-sm btn-light border text-secondary px-2.5" title="Clear Search">
                <i class="bi bi-x-lg"></i>
            </button>
            <button id="collapseAllBtn" class="btn btn-sm btn-light border text-secondary px-3" title="Collapse All">
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
                            <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="font-size: 0.72rem; background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;">
                                <?= count($catItems) ?> Items
                            </span>
                        </div>
                    </button>
                </h2>
                <div id="<?= $catId ?>" class="accordion-collapse collapse" data-bs-parent="#categoryAccordion" data-parent-id="#categoryAccordion">
                    <div class="accordion-body p-0 bg-white border-top">
                        <div class="table-responsive">
                            <table class="table saas-table align-middle text-nowrap">
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
                                                <span class="badge rounded-pill <?= ($available > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger') ?> px-2.5 py-1">
                                                    <?= number_format($available) ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" class="action-btn-saas" title="Edit Asset"
                                                            onclick="editItem(<?= $row['id'] ?>,'<?= addslashes($row['item_name']) ?>','<?= htmlspecialchars($row['category'], ENT_QUOTES) ?>','<?= $row['stock_type'] ?>', <?= $row['stock_exists'] ?>)">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button type="button" class="action-btn-saas danger delete-btn" data-id="<?= $row['id'] ?>" title="Delete Asset">
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
        <div class="saas-card p-4 text-center text-muted">
            <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
            <p class="mb-0 small fw-medium">No asset records registered.</p>
        </div>
    <?php endif; ?>
</div>

<!-- EDIT MODAL STRUCTURE -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST" id="editForm">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold m-0" id="editModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Item Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="eid">
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary">Item Name</label>
                        <input type="text" name="item_name" id="ename" class="form-control form-control-sm rounded-2" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary">Category</label>
                        <select name="category" id="ecat" class="form-select form-select-sm rounded-2">
                            <option>Computer</option>
                            <option>Accessory</option>
                            <option>Component</option>
                            <option>Networking</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary">Stock Tracking Type</label>
                        <select name="stock_type" id="etype" class="form-select form-select-sm rounded-2">
                            <option value="serial">Serialized (Track by Serial No.)</option>
                            <option value="non_serial">Non-Serialized (Bulk Quantity)</option>
                        </select>
                        <small id="typeWarning" class="text-muted" style="font-size: 11px;"></small>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-sm btn-light border rounded-2 px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update" class="btn btn-sm btn-primary rounded-2 px-3" style="background-color: var(--saas-primary); border: none;">Update Item</button>
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
            $('.cat-stack-card, .saas-table tbody tr').css('display', '');
            return; 
        }

        $('.cat-stack-card .accordion-collapse').removeAttr('data-bs-parent');

        $('.cat-stack-card').each(function() {
            let $catCard = $(this);
            let catName = $catCard.find('.cat-icon-box').next('span').text().trim().toLowerCase();
            let isCatMatch = catName.includes(query);
            let matchingRowsCount = 0;

            $catCard.find('.saas-table tbody tr').each(function() {
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