<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";
require_once "../admin/auth.php";

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
:root {
    --saas-border: #e2e8f0;
    --saas-primary: #2563eb;
    --saas-text-muted: #64748b;
    --saas-bg-subtle: #f8fafc;
}

#editSpecModal { z-index: 1056 !important; }

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

/* Category Accordion Styling matching Asset Registry */
.category-accordion .accordion-item {
    border: 1px solid var(--saas-border);
    border-radius: 10px !important;
    overflow: hidden;
    margin-bottom: 0.75rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.category-accordion .accordion-button {
    background-color: #ffffff;
    color: #1e293b;
    font-weight: 700;
    font-size: 0.95rem;
    padding: 0.85rem 1.25rem;
    box-shadow: none !important;
}
.category-accordion .accordion-button:not(.collapsed) {
    background-color: #ffffff;
    color: var(--saas-primary);
}
.category-accordion .accordion-button::after {
    background-size: 1rem;
}
.category-accordion .accordion-body {
    padding: 0;
    border-top: 1px solid var(--saas-border);
    background-color: #ffffff;
}

.folder-icon-box {
    width: 32px;
    height: 32px;
    background-color: #e0e7ff;
    color: #4338ca;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 0.95rem;
}

.item-badge {
    background-color: #e0e7ff;
    color: #3730a3;
    font-weight: 600;
    font-size: 0.725rem;
    padding: 3px 10px;
    border-radius: 12px;
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
    padding: 0.65rem 1.25rem;
}
.saas-table td {
    padding: 0.65rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.saas-table tbody tr:last-child td { border-bottom: none; }

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

.spec-accordion .accordion-item {
    border: 1px solid var(--saas-border);
    border-radius: 8px !important;
}
.spec-accordion .accordion-button {
    font-size: 0.825rem;
    font-weight: 600;
    color: #334155;
    background-color: #f8fafc;
    padding: 0.6rem 1rem;
}
</style>

<!-- PAGE HEADER -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h4 class="fw-bold m-0 text-dark">
            <i class="<?= $page_icon ?> text-primary me-2"></i><?= $page_title ?>
        </h4>
        <p class="text-muted small m-0">Master catalog of specifications, grouped by category.</p>
    </div>
    <button class="btn btn-primary btn-sm px-3 py-2 rounded-2 shadow-sm fw-semibold" style="background-color: var(--saas-primary); border: none;" type="button" data-bs-toggle="collapse" data-bs-target="#addSpecCollapse">
        <i class="bi bi-plus-lg me-1"></i> Add Specification
    </button>
</div>

<!-- COLLAPSIBLE ADD SPECIFICATION FORM -->
<div class="collapse mb-3" id="addSpecCollapse">
    <div class="saas-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold m-0 text-dark">
                <i class="bi bi-cpu text-primary me-1.5"></i> Register Specification Model
            </h6>
            <button type="button" class="btn-close small" data-bs-toggle="collapse" data-bs-target="#addSpecCollapse"></button>
        </div>
        <form method="POST" id="specForm">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold text-secondary">Select Main Item <span class="text-danger">*</span></label>
                    <select name="item_id" id="itemSelect" class="form-select form-select-sm rounded-2" required>
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
                    <input type="text" name="model_name" class="form-control form-control-sm rounded-2" placeholder="e.g. Veriton M200-H510" required>
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
                                    <input type="text" name="processor" class="form-control form-control-sm rounded-2" placeholder="e.g. i5-1145G7">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold text-secondary">RAM</label>
                                    <input type="text" name="ram" class="form-control form-control-sm rounded-2" placeholder="e.g. 16GB">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-secondary">Storage Type</label>
                                    <select name="storage_type" class="form-select form-select-sm rounded-2">
                                        <option value="">Select Type</option>
                                        <option value="SSD">SSD</option>
                                        <option value="HDD">HDD</option>
                                        <option value="NVMe">NVMe</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-secondary">Storage Size</label>
                                    <input type="text" name="storage_size" class="form-control form-control-sm rounded-2" placeholder="e.g. 512GB">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-sm btn-light border rounded-2 px-3" data-bs-toggle="collapse" data-bs-target="#addSpecCollapse">Cancel</button>
                <button type="submit" name="add_model" class="btn btn-sm btn-primary rounded-2 px-3" style="background-color: var(--saas-primary); border: none;">
                    <i class="bi bi-check-lg me-1"></i> Save Specification
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SEARCH & CONTROL TOOLBAR -->
<div class="saas-toolbar mb-3">
    <div class="row g-2 align-items-center">
        <div class="col flex-grow-1">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="specSearch" class="form-control border-0 bg-transparent shadow-none" placeholder="Filter specifications by name or category...">
            </div>
        </div>
        <div class="col-auto d-flex gap-2">
            <button id="resetSpecSearch" class="btn btn-sm btn-light border text-secondary px-2.5" title="Clear Search">
                <i class="bi bi-x-lg"></i>
            </button>
            <button id="btnToggleCollapse" class="btn btn-sm btn-light border text-secondary fw-semibold px-2.5 d-flex align-items-center gap-1.5" type="button">
                <i class="bi bi-arrows-expand"></i> <span id="toggleCollapseText">Expand All</span>
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
            <div class="accordion-item category-group-item" data-category="<?= htmlspecialchars(strtolower($categoryName)) ?>">
                <h2 class="accordion-header" id="heading_<?= $accordionId ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>" aria-expanded="false" aria-controls="<?= $accordionId ?>">
                        <div class="d-flex align-items-center justify-content-between w-100 me-3">
                            <div class="d-flex align-items-center">
                                <span class="folder-icon-box"><i class="bi bi-folder-fill"></i></span>
                                <span class="category-title"><?= htmlspecialchars($categoryName) ?></span>
                            </div>
                            <span class="item-badge"><?= count($catModels) ?> <?= count($catModels) === 1 ? 'Item' : 'Items' ?></span>
                        </div>
                    </button>
                </h2>
                <div id="<?= $accordionId ?>" class="accordion-collapse collapse" aria-labelledby="heading_<?= $accordionId ?>">
                    <div class="table-responsive">
                        <table class="table saas-table align-middle text-nowrap">
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
                                        <div class="fw-semibold text-dark model-name"><?= htmlspecialchars($m['model_name']) ?></div>
                                        <div class="text-muted item-name" style="font-size: 0.75rem;"><?= htmlspecialchars($m['item_name']) ?></div>
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
                                            <button type="button" class="action-btn-saas" title="Edit Specification" onclick='openEditSpecModal(<?= json_encode($m) ?>)'>
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button type="button" class="action-btn-saas danger delete-spec-btn" data-id="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['model_name']) ?>" title="Delete Model">
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
        <?php endforeach; ?>
    <?php else: ?>
        <div class="saas-card p-4 text-center text-muted small">
            No specifications recorded yet. Click "Add Specification" above to create one.
        </div>
    <?php endif; ?>
</div>

<!-- EDIT MODAL STRUCTURE -->
<div class="modal fade" id="editSpecModal" tabindex="-1" aria-labelledby="editSpecModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold m-0" id="editSpecModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Specification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary">Model Name</label>
                        <input type="text" name="model_name" id="edit_model_name" class="form-control form-control-sm rounded-2" required>
                    </div>

                    <!-- Dynamic Container for Tech Specs in Modal -->
                    <div id="modalTechSpecsContainer">
                        <div class="mb-3">
                            <label class="small fw-semibold text-secondary">Processor</label>
                            <input type="text" name="processor" id="edit_processor" class="form-control form-control-sm rounded-2">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-semibold text-secondary">RAM</label>
                                <input type="text" name="ram" id="edit_ram" class="form-control form-control-sm rounded-2">
                            </div>
                            <div class="col-6">
                                <label class="small fw-semibold text-secondary">Storage Type</label>
                                <select name="storage_type" id="edit_storage_type" class="form-select form-select-sm rounded-2">
                                    <option value="">Select Storage Type</option>
                                    <option value="SSD">SSD</option>
                                    <option value="HDD">HDD</option>
                                    <option value="NVMe">NVMe</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-semibold text-secondary">Storage Size</label>
                            <input type="text" name="storage_size" id="edit_storage_size" class="form-control form-control-sm rounded-2">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-sm btn-light border rounded-2 px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_model" class="btn btn-sm btn-primary rounded-2 px-3" style="background-color: var(--saas-primary); border: none;">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
if(isset($_SESSION['swal_msg'])): 
    $type = $_SESSION['swal_type'];
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