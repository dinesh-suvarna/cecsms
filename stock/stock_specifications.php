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

$page_title = "Stock Specifications";
$page_icon  = "bi-sliders";

/* ---------------- ADD MODEL ---------------- */
if(isset($_POST['add_model'])){
    $item_id      = $_POST['item_id'];
    $model_name   = trim(strtoupper($_POST['model_name']));
    $processor    = trim($_POST['processor'] ?? '');
    $ram          = trim($_POST['ram'] ?? '');
    $storage_type = $_POST['storage_type'] ?? '';
    $storage_size = trim($_POST['storage_size'] ?? '');

    $check = $conn->prepare("SELECT id FROM item_models WHERE item_id=? AND model_name=?");
    $check->bind_param("is", $item_id, $model_name);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        notify("warning", "Model '$model_name' already exists for this item.");
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO item_models (item_id, model_name, processor, ram, storage_type, storage_size) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $item_id, $model_name, $processor, $ram, $storage_type, $storage_size);
            
            if($stmt->execute()){
                notify("success", "Specification model added successfully!");
            }
        } catch (Exception $e) {
            notify("danger", "Database Error: " . $e->getMessage());
        }
    }
    header("Location: stock_specifications.php");
    exit;
}

/* ---------------- DELETE MODEL ---------------- */
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    $check = $conn->prepare("SELECT id FROM stock_details WHERE model_id = ? LIMIT 1");
    $check->bind_param("i", $id);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        notify("danger", "Cannot delete. Linked to existing assets.");
    } else {
        $stmt = $conn->prepare("DELETE FROM item_models WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        notify("success", "Specification deleted successfully.");
    }
    header("Location: stock_specifications.php");
    exit;
}

/* ---------------- UPDATE MODEL ---------------- */
if(isset($_POST['update_model'])){
    $id           = intval($_POST['id']);
    $model_name   = trim(strtoupper($_POST['model_name']));
    $processor    = trim($_POST['processor'] ?? '');
    $ram          = trim($_POST['ram'] ?? '');
    $storage_type = $_POST['storage_type'] ?? '';
    $storage_size = trim($_POST['storage_size'] ?? '');

    $stmt = $conn->prepare("UPDATE item_models SET model_name=?, processor=?, ram=?, storage_type=?, storage_size=? WHERE id=?");
    $stmt->bind_param("sssssi", $model_name, $processor, $ram, $storage_type, $storage_size, $id);
    
    if($stmt->execute()){
        notify("success", "Updated successfully!");
    }
    header("Location: stock_specifications.php");
    exit;
}

/* ---------------- DATA FETCHING ---------------- */
$items = $conn->query("SELECT id, item_name, category FROM items_master WHERE status='Active' ORDER BY item_name ASC");

// Fetch models grouped by Category
$raw_models = $conn->query("SELECT m.*, i.item_name, i.category FROM item_models m JOIN items_master i ON i.id = m.item_id ORDER BY i.category ASC, i.item_name ASC, m.model_name ASC");

$categories = [];
while($row = $raw_models->fetch_assoc()){
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

/* Panels & Cards */
.inst-panel {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 5px;
    box-shadow: var(--erp-shadow);
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

/* Inner Nested Tech Specs Accordion */
.spec-accordion .accordion-item {
    border: 1px solid var(--erp-border);
    border-radius: 5px !important;
}
.spec-accordion .accordion-button {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--erp-navy-dark);
    background-color: #f5f7f9;
    padding: 0.6rem 1rem;
}

#editSpecModal { z-index: 1056 !important; }

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
[data-bs-theme="dark"] .cat-header-btn:not(.collapsed) { background: #101a24 !important; border-color: var(--erp-border); }
[data-bs-theme="dark"] .cat-header-btn { background-color: #142230 !important; }
[data-bs-theme="dark"] .table-erp thead th { background: #101a24; border-color: var(--erp-border); color: var(--erp-muted); }
[data-bs-theme="dark"] .table-erp tbody td { border-color: var(--erp-border); color: var(--erp-text); }
[data-bs-theme="dark"] .btn-erp-cancel,
[data-bs-theme="dark"] .action-btn-erp { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
[data-bs-theme="dark"] .modal-content { background: #142230; border-color: var(--erp-border); color: #edf3f7; }
[data-bs-theme="dark"] .spec-accordion .accordion-button { background-color: #101a24; color: var(--erp-navy-dark); }
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
                <p>Master catalog of specifications, grouped by category.</p>
            </div>
        </div>
        <button class="btn btn-erp-primary px-3" type="button" data-bs-toggle="collapse" data-bs-target="#addSpecCollapse">
            <i class="bi bi-plus-lg me-1.5"></i> Add Specification
        </button>
    </div>

    <!-- COLLAPSIBLE ADD SPECIFICATION FORM -->
    <div class="collapse mb-4" id="addSpecCollapse">
        <div class="inst-panel p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="inst-panel-title">
                    <i class="bi bi-cpu me-1.5 text-primary"></i> Register Specification Model
                </div>
                <button type="button" class="btn-close small" data-bs-toggle="collapse" data-bs-target="#addSpecCollapse"></button>
            </div>
            <form method="POST" id="specForm">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Select Main Item <span class="text-danger">*</span></label>
                        <select name="item_id" id="itemSelect" class="form-select form-select-sm" required>
                            <option value="">Choose Item...</option>
                            <?php 
                            if($items && $items->num_rows > 0):
                                while($row = $items->fetch_assoc()): 
                            ?>
                                <option value="<?= $row['id'] ?>" data-category="<?= htmlspecialchars($row['category']) ?>">
                                    <?= htmlspecialchars($row['item_name']) ?> (<?= htmlspecialchars($row['category']) ?>)
                                </option>
                            <?php 
                                endwhile; 
                            endif;
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Model Name <span class="text-danger">*</span></label>
                        <input type="text" name="model_name" class="form-control form-control-sm" placeholder="e.g. Veriton M200-H510" required>
                    </div>
                </div>

                <!-- HARDWARE SPECIFICATIONS ACCORDION (For Computer category only) -->
                <div class="accordion spec-accordion mb-3" id="techSpecsAccordionWrapper" style="display: none;">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTechSpecs">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTechSpecs" aria-expanded="false" aria-controls="collapseTechSpecs">
                                <i class="bi bi-memory me-2"></i> Hardware Specifications (Processor, RAM, Storage)
                            </button>
                        </h2>
                        <div id="collapseTechSpecs" class="accordion-collapse collapse" aria-labelledby="headingTechSpecs">
                            <div class="accordion-body p-3">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-secondary">Processor</label>
                                        <input type="text" name="processor" class="form-control form-control-sm" placeholder="e.g. i5-1145G7">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-semibold text-secondary">RAM</label>
                                        <input type="text" name="ram" class="form-control form-control-sm" placeholder="e.g. 16GB">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-secondary">Storage Type</label>
                                        <select name="storage_type" class="form-select form-select-sm">
                                            <option value="">Select Type</option>
                                            <option value="SSD">SSD</option>
                                            <option value="HDD">HDD</option>
                                            <option value="NVMe">NVMe</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-secondary">Storage Size</label>
                                        <input type="text" name="storage_size" class="form-control form-control-sm" placeholder="e.g. 512GB">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                    <button type="button" class="btn btn-erp-cancel px-3" data-bs-toggle="collapse" data-bs-target="#addSpecCollapse">Cancel</button>
                    <button type="submit" name="add_model" class="btn btn-erp-primary px-3">
                        <i class="bi bi-check-lg me-1"></i> Save Specification
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SEARCH & CONTROL TOOLBAR -->
    <div class="erp-toolbar mb-3">
        <div class="row g-2 align-items-center">
            <div class="col flex-grow-1">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="specSearch" class="form-control border-0 bg-transparent shadow-none" placeholder="Filter specifications by name or category...">
                </div>
            </div>
            <div class="col-auto d-flex gap-1">
                <button id="resetSpecSearch" class="btn btn-erp-cancel px-2.5" title="Clear Search">
                    <i class="bi bi-x-lg"></i>
                </button>
                <button id="btnToggleCollapse" class="btn btn-erp-cancel px-3" type="button">
                    <i class="bi bi-arrows-expand me-1"></i> <span id="toggleCollapseText">Expand All</span>
                </button>
            </div>
        </div>
    </div>

    <!-- GROUPED CATEGORY ACCORDIONS -->
    <div class="accordion category-accordion" id="categoryAccordion">
        <?php if(!empty($categories)): ?>
            <?php 
            $catIndex = 0;
            foreach($categories as $categoryName => $catModels): 
                $catIndex++;
                $accordionId = "categoryCollapse_" . $catIndex;
                $isComputer = (strtolower(trim($categoryName)) === 'computer');
            ?>
                <div class="accordion-item cat-stack-card category-group-item" data-category="<?= htmlspecialchars(strtolower($categoryName)) ?>">
                    <h2 class="accordion-header" id="heading_<?= $accordionId ?>">
                        <button class="accordion-button cat-header-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>" aria-expanded="false" aria-controls="<?= $accordionId ?>">
                            <div class="d-flex align-items-center justify-content-between w-100 me-2">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="cat-icon-box">
                                        <i class="bi bi-folder2-open"></i>
                                    </div>
                                    <span class="fw-bold text-dark fs-6 category-title"><?= htmlspecialchars($categoryName) ?></span>
                                </div>
                                <span class="badge-erp badge-erp-neutral"><?= count($catModels) ?> <?= count($catModels) === 1 ? 'Item' : 'Items' ?></span>
                            </div>
                        </button>
                    </h2>
                    <div id="<?= $accordionId ?>" class="accordion-collapse collapse" aria-labelledby="heading_<?= $accordionId ?>">
                        <div class="accordion-body p-0 bg-white border-top">
                            <div class="table-responsive">
                                <table class="table table-erp align-middle text-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="ps-3">Item / Model</th>
                                            <?php if($isComputer): ?>
                                                <th>Processor</th>
                                                <th>RAM</th>
                                                <th>Storage</th>
                                            <?php endif; ?>
                                            <th class="text-end pe-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($catModels as $m): ?>
                                        <tr class="spec-row">
                                            <td class="ps-3">
                                                <div class="fw-semibold text-dark model-name"><?= htmlspecialchars($m['item_name']) ?></div>
                                                <div class="text-muted item-name" style="font-size: 0.75rem;"><?= htmlspecialchars($m['model_name']) ?></div>
                                            </td>
                                            <?php if($isComputer): ?>
                                                <td class="small text-secondary"><?= htmlspecialchars($m['processor']) ?: '-' ?></td>
                                                <td class="small text-secondary"><?= htmlspecialchars($m['ram']) ?: '-' ?></td>
                                                <td class="small text-secondary">
                                                    <?= htmlspecialchars($m['storage_size']) ?> <?= htmlspecialchars($m['storage_type']) ?: '-' ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="text-end pe-3">
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" class="action-btn-erp" title="Edit Specification" onclick='openEditSpecModal(<?= json_encode($m) ?>)'>
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button type="button" class="action-btn-erp danger delete-spec-btn" data-id="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['model_name']) ?>" title="Delete Model">
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
                <p class="mb-0 small fw-medium">No specifications recorded yet. Click "Add Specification" above to create one.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- EDIT MODAL STRUCTURE -->
<div class="modal fade" id="editSpecModal" tabindex="-1" aria-labelledby="editSpecModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2 border shadow-sm">
            <form method="POST">
                <div class="modal-header border-bottom p-3">
                    <h6 class="fw-bold m-0" id="editSpecModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Specification</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary">Model Name</label>
                        <input type="text" name="model_name" id="edit_model_name" class="form-control form-control-sm rounded-1" required>
                    </div>

                    <!-- Dynamic Container for Tech Specs in Modal -->
                    <div id="modalTechSpecsContainer">
                        <div class="mb-3">
                            <label class="small fw-semibold text-secondary">Processor</label>
                            <input type="text" name="processor" id="edit_processor" class="form-control form-control-sm rounded-1">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-semibold text-secondary">RAM</label>
                                <input type="text" name="ram" id="edit_ram" class="form-control form-control-sm rounded-1">
                            </div>
                            <div class="col-6">
                                <label class="small fw-semibold text-secondary">Storage Type</label>
                                <select name="storage_type" id="edit_storage_type" class="form-select form-select-sm rounded-1">
                                    <option value="">Select Storage Type</option>
                                    <option value="SSD">SSD</option>
                                    <option value="HDD">HDD</option>
                                    <option value="NVMe">NVMe</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-semibold text-secondary">Storage Size</label>
                            <input type="text" name="storage_size" id="edit_storage_size" class="form-control form-control-sm rounded-1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-2 px-3">
                    <button type="button" class="btn btn-erp-cancel px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_model" class="btn btn-erp-primary px-3">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
if(isset($_SESSION['swal_msg'])): 
    $type = $_SESSION['swal_type'] == 'danger' ? 'error' : $_SESSION['swal_type'];
    $msg  = $_SESSION['swal_msg'];
    unset($_SESSION['swal_type'], $_SESSION['swal_msg']);
?>
<script>
    Swal.fire({
        icon: '<?= $type ?>',
        title: '<?= ($type == "success" ? "Success!" : "Notice") ?>',
        text: '<?= htmlspecialchars($msg) ?>',
        timer: 2500,
        showConfirmButton: false
    });
</script>
<?php endif; ?>

<script>
$(document).ready(function() {
    $('#editSpecModal').appendTo('body');

    // Global Search across Accordions & Rows
    $('#specSearch').on('keyup input', function() {
        let filter = $(this).val().toLowerCase().trim();
        
        $('.category-group-item').each(function() {
            let $group = $(this);
            let hasVisibleRows = false;
            let groupCategoryName = $group.data('category') || '';

            $group.find('.spec-row').each(function() {
                let rowText = $(this).text().toLowerCase();
                if (filter === '' || rowText.indexOf(filter) > -1 || groupCategoryName.indexOf(filter) > -1) {
                    $(this).show();
                    hasVisibleRows = true;
                } else {
                    $(this).hide();
                }
            });

            if (hasVisibleRows) {
                $group.show();
                if (filter !== '') {
                    // Expand matching accordions while searching
                    $group.find('.accordion-collapse').addClass('show');
                    $group.find('.accordion-button').removeClass('collapsed').attr('aria-expanded', 'true');
                } else {
                    // Collapse all accordions when search is cleared/deleted
                    $group.find('.accordion-collapse').removeClass('show');
                    $group.find('.accordion-button').addClass('collapsed').attr('aria-expanded', 'false');
                }
            } else {
                $group.hide();
            }
        });

        // Sync toggle button state when search is deleted
        if (filter === '') {
            allCollapsed = true;
            $('#toggleCollapseText').text('Expand All');
            $('#btnToggleCollapse i').removeClass('bi-arrows-collapse').addClass('bi-arrows-expand');
        }
    });

    $('#resetSpecSearch').on('click', function() {
        $('#specSearch').val('').trigger('keyup');
    });

    // Collapse All / Expand All Toggle Logic
    let allCollapsed = true;
    $('#toggleCollapseText').text('Expand All');
    $('#btnToggleCollapse i').removeClass('bi-arrows-collapse').addClass('bi-arrows-expand');
    $('#btnToggleCollapse').on('click', function() {
        if (!allCollapsed) {
            $('.category-accordion .accordion-collapse').removeClass('show');
            $('.category-accordion .accordion-button').addClass('collapsed').attr('aria-expanded', 'false');
            $('#toggleCollapseText').text('Expand All');
            $(this).find('i').removeClass('bi-arrows-collapse').addClass('bi-arrows-expand');
            allCollapsed = true;
        } else {
            $('.category-accordion .accordion-collapse').addClass('show');
            $('.category-accordion .accordion-button').removeClass('collapsed').attr('aria-expanded', 'true');
            $('#toggleCollapseText').text('Collapse All');
            $(this).find('i').removeClass('bi-arrows-expand').addClass('bi-arrows-collapse');
            allCollapsed = false;
        }
    });

    // Show/Hide Hardware Spec Wrapper when Computer is selected (kept collapsed by default)
    $('#itemSelect').on('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const category = selectedOption.getAttribute('data-category');
        const $accordionWrapper = $('#techSpecsAccordionWrapper');

        if (category && category.toLowerCase() === 'computer') {
            $accordionWrapper.slideDown(150);
            $accordionWrapper.find('input, select').prop('disabled', false);
        } else {
            $accordionWrapper.slideUp(150);
            $accordionWrapper.find('input, select').prop('disabled', true);
        }
    });

    // Delete Confirmation
    $(document).on('click', '.delete-spec-btn', function() {
        const id = $(this).attr('data-id');
        const name = $(this).attr('data-name');
        
        Swal.fire({
            title: 'Delete Specification?',
            text: `Are you sure you want to remove ${name}? This cannot be undone if linked assets exist.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `stock_specifications.php?delete=${id}`;
            }
        });
    });
});

function openEditSpecModal(data) {
    $('#edit_id').val(data.id);
    $('#edit_model_name').val(data.model_name);

    // Show or hide tech spec fields in Modal based on category
    if (data.category && data.category.toLowerCase() === 'computer') {
        $('#modalTechSpecsContainer').show();
        $('#edit_processor').val(data.processor);
        $('#edit_ram').val(data.ram);
        $('#edit_storage_type').val(data.storage_type);
        $('#edit_storage_size').val(data.storage_size);
    } else {
        $('#modalTechSpecsContainer').hide();
        $('#edit_processor').val('');
        $('#edit_ram').val('');
        $('#edit_storage_type').val('');
        $('#edit_storage_size').val('');
    }

    var modalElement = document.getElementById('editSpecModal');
    var modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
    modalInstance.show();
}
</script>

<?php
$main_content = ob_get_clean();
include "stocklayout.php";
?>