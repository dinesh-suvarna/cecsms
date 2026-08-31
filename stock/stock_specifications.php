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

$page_title = "Hardware & Asset Specifications";
$page_icon  = "bi-sliders";

/* ---------- HELPER: DYNAMIC CATEGORY ICONS ---------- */
function getCategoryIcon(string $category): string {
    $cat = strtolower(trim($category));
    if (str_contains($cat, 'computer') || str_contains($cat, 'pc') || str_contains($cat, 'laptop')) {
        return 'bi-pc-display';
    } elseif (str_contains($cat, 'accessory') || str_contains($cat, 'peripherals')) {
        return 'bi-keyboard';
    } elseif (str_contains($cat, 'network') || str_contains($cat, 'router') || str_contains($cat, 'switch')) {
        return 'bi-diagram-3';
    } elseif (str_contains($cat, 'component') || str_contains($cat, 'hardware')) {
        return 'bi-cpu';
    } elseif (str_contains($cat, 'furniture')) {
        return 'bi-lamp';
    } elseif (str_contains($cat, 'mobile') || str_contains($cat, 'phone')) {
        return 'bi-phone';
    }
    return 'bi-folder';
}

/* ---------------- ADD MODEL ---------------- */
if(isset($_POST['add_model'])){
    $item_id      = intval($_POST['item_id']);
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
        notify("danger", "Cannot delete. Model is linked to existing stock records.");
    } else {
        $stmt = $conn->prepare("DELETE FROM item_models WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        notify("success", "Specification model deleted successfully.");
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
        notify("success", "Specification model updated successfully!");
    }
    header("Location: stock_specifications.php");
    exit;
}

/* ---------------- DATA FETCHING ---------------- */
$items = $conn->query("SELECT id, item_name, category FROM items_master WHERE status='Active' ORDER BY item_name ASC");

$raw_models = $conn->query("
    SELECT 
        m.*, 
        i.item_name, 
        i.category,
        (SELECT COUNT(*) FROM stock_details sd WHERE sd.model_id = m.id) as stock_linked
    FROM item_models m 
    JOIN items_master i ON i.id = m.item_id 
    ORDER BY i.category ASC, i.item_name ASC, m.model_name ASC
");

$categories = [];
$totalModels = 0;
$computerSpecsCount = 0;
$itemsConfigured = [];

while($row = $raw_models->fetch_assoc()){
    $cat = !empty($row['category']) ? $row['category'] : 'Uncategorized';
    $categories[$cat][] = $row;
    $totalModels++;
    $itemsConfigured[$row['item_id']] = true;
    if (strtolower(trim($cat)) === 'computer') {
        $computerSpecsCount++;
    }
}

ob_start(); 
?>

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
    width: 48px; height: 48px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #edf3f8 0%, #e2ecf5 100%);
    border: 1px solid #cddde9; border-radius: 8px;
    color: var(--erp-navy); font-size: 1.35rem;
    box-shadow: 0 2px 4px rgba(23, 63, 99, 0.05);
}
.inst-header h3 { margin: 0; color: var(--erp-navy-dark); font-size: 1.25rem; font-weight: 700; }
.inst-header p { margin: 3px 0 0; color: var(--erp-muted); font-size: .8rem; }

/* Stat Cards */
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
.cat-icon-box {
    width: 36px; height: 36px;
    border-radius: 6px;
    background: #f0f5fa;
    border: 1px solid #d4e2ed;
    color: var(--erp-navy);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1rem;
}

/* Panels & Accordion Stack */
.inst-panel {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 8px;
    box-shadow: var(--erp-shadow);
}
.cat-stack-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 8px !important;
    margin-bottom: 0.85rem;
    background: #ffffff;
    box-shadow: var(--erp-shadow);
    overflow: hidden;
}
.cat-header-btn {
    background-color: #ffffff !important;
    border: none;
    padding: 0.85rem 1.25rem;
    box-shadow: none !important;
}
.cat-header-btn:not(.collapsed) {
    background-color: #f1f5f9 !important;
    border-bottom: 1px solid var(--erp-border);
}

/* Data Tables */
.table-erp { font-size: .83rem; margin: 0; }
.table-erp thead th {
    background: #f8fafc; color: #475569; font-size: .7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid var(--erp-border);
    padding: 12px 18px;
}
.table-erp tbody td { 
    padding: 14px 18px; 
    border-bottom: 1px solid var(--erp-border); 
    vertical-align: middle; 
    color: #334155;
}
.table-erp tbody tr:last-child td { border-bottom: none; }

.item-title {
    font-weight: 700;
    color: #0f172a;
    font-size: 0.85rem;
    letter-spacing: 0.01em;
}
.item-subtitle {
    font-size: 0.78rem;
    color: #64748b;
    margin-top: 2px;
    font-weight: 500;
}

/* Count Badge on Accordion */
.count-badge-outline {
    font-size: 0.73rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
}

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
    border-radius: 6px;
    display: inline-flex; align-items: center; justify-content: center;
    color: #64748b; border: 1px solid #cbd5e1; background: #ffffff;
    transition: all 0.15s ease;
}
.action-btn-erp:hover { background: #f8fafc; color: var(--erp-navy-dark); border-color: #94a3b8; }
.action-btn-erp.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fca5a5; }

/* Tech Specs Sub-Accordion */
.spec-accordion .accordion-item { border: 1px solid var(--erp-border); border-radius: 6px !important; }
.spec-accordion .accordion-button {
    font-size: 0.78rem; font-weight: 600; color: var(--erp-navy-dark);
    background-color: #f8fafc; padding: 0.65rem 1rem;
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
[data-bs-theme="dark"] .stat-widget-card,
[data-bs-theme="dark"] .erp-toolbar,
[data-bs-theme="dark"] .cat-stack-card { background: #142230 !important; }
[data-bs-theme="dark"] .cat-header-btn:not(.collapsed) { background: #101a24 !important; border-color: var(--erp-border); }
[data-bs-theme="dark"] .cat-header-btn { background-color: #142230 !important; }
[data-bs-theme="dark"] .table-erp thead th { background: #101a24; border-color: var(--erp-border); color: var(--erp-muted); }
[data-bs-theme="dark"] .table-erp tbody td { border-color: var(--erp-border); color: var(--erp-text); }
[data-bs-theme="dark"] .item-title { color: #f8fafc; }
[data-bs-theme="dark"] .item-subtitle { color: #94a3b8; }
[data-bs-theme="dark"] .count-badge-outline { background: #1e293b; border-color: #334155; color: #cbd5e1; }
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
                <div class="d-flex align-items-center gap-2">
                    <h3 class="mb-0"><?= htmlspecialchars($page_title) ?></h3>
                </div>
                <p>Configure hardware specifications & model variants for catalog items.</p>
            </div>
        </div>
        <button class="btn btn-erp-primary px-3" type="button" data-bs-toggle="collapse" data-bs-target="#addSpecCollapse">
            <i class="bi bi-plus-lg me-1.5"></i> Add Specification
        </button>
    </div>

    <!-- STATS SUMMARY BAR -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-widget-card" style="--card-accent: #173f63;">
                <div class="title">Total Model Specs</div>
                <div class="value"><?= $totalModels ?></div>
                <i class="bi bi-cpu stat-widget-icon"></i>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-widget-card" style="--card-accent: #2563eb;">
                <div class="title">Computer Variants</div>
                <div class="value text-primary"><?= $computerSpecsCount ?></div>
                <i class="bi bi-pc-display stat-widget-icon"></i>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-widget-card" style="--card-accent: #64748b;">
                <div class="title">Categories Covered</div>
                <div class="value"><?= count($categories) ?></div>
                <i class="bi bi-tags-fill stat-widget-icon"></i>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-widget-card" style="--card-accent: #16a34a;">
                <div class="title">Configured Items</div>
                <div class="value text-success"><?= count($itemsConfigured) ?></div>
                <i class="bi bi-boxes stat-widget-icon"></i>
            </div>
        </div>
    </div>

    <!-- COLLAPSIBLE ADD SPECIFICATION FORM -->
    <div class="collapse mb-4" id="addSpecCollapse">
        <div class="inst-panel p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="inst-panel-title fw-bold text-dark">
                    <i class="bi bi-plus-circle me-1.5 text-primary"></i> Register Specification Model
                </div>
                <button type="button" class="btn-close small" data-bs-toggle="collapse" data-bs-target="#addSpecCollapse"></button>
            </div>
            <form method="POST" id="specForm">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Select Target Catalog Item <span class="text-danger">*</span></label>
                        <select name="item_id" id="itemSelect" class="form-select form-select-sm" required>
                            <option value="">Choose Catalog Item...</option>
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
                        <label class="form-label small fw-semibold text-secondary">Model / Variant Name <span class="text-danger">*</span></label>
                        <input type="text" name="model_name" class="form-control form-control-sm" placeholder="e.g. VERITON M200-H510 or LATITUDE 3420" required>
                    </div>
                </div>

                <!-- HARDWARE SPECIFICATIONS ACCORDION (For Computer category only) -->
                <div class="accordion spec-accordion mb-3" id="techSpecsAccordionWrapper" style="display: none;">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTechSpecs">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTechSpecs" aria-expanded="false">
                                <i class="bi bi-memory me-2"></i> Technical Hardware Specifications (CPU, RAM, Storage)
                            </button>
                        </h2>
                        <div id="collapseTechSpecs" class="accordion-collapse collapse">
                            <div class="accordion-body p-3">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-secondary">Processor / CPU</label>
                                        <input type="text" name="processor" class="form-control form-control-sm" placeholder="e.g. I5 or PENTIUM DUAL CORE">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-semibold text-secondary">RAM Size</label>
                                        <input type="text" name="ram" class="form-control form-control-sm" placeholder="e.g. 8GB">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-secondary">Storage Type</label>
                                        <select name="storage_type" class="form-select form-select-sm">
                                            <option value="">Select Storage Type</option>
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
    <div class="erp-toolbar mb-3 p-2 bg-white rounded-3 border">
        <div class="row g-2 align-items-center">
            <div class="col flex-grow-1">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="specSearch" class="form-control border-0 bg-transparent shadow-none" placeholder="Filter specifications by model, item name, processor or category...">
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
                $catIcon = getCategoryIcon($categoryName);
                $isComputer = (strtolower(trim($categoryName)) === 'computer');
            ?>
                <div class="accordion-item cat-stack-card category-group-item" data-category="<?= htmlspecialchars(strtolower($categoryName)) ?>">
                    <h2 class="accordion-header" id="heading_<?= $accordionId ?>">
                        <button class="accordion-button cat-header-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId ?>">
                            <div class="d-flex align-items-center justify-content-between w-100 me-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="cat-icon-box">
                                        <i class="bi <?= $catIcon ?>"></i>
                                    </div>
                                    <span class="fw-bold text-dark fs-6 category-title ms-1"><?= htmlspecialchars($categoryName) ?></span>
                                </div>
                                <span class="count-badge-outline">
                                    <?= count($catModels) ?> <?= count($catModels) === 1 ? 'Item' : 'Items' ?>
                                </span>
                            </div>
                        </button>
                    </h2>
                    <div id="<?= $accordionId ?>" class="accordion-collapse collapse" aria-labelledby="heading_<?= $accordionId ?>">
                        <div class="accordion-body p-0 bg-white border-top">
                            <div class="table-responsive">
                                <table class="table table-erp align-middle text-nowrap">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 240px;">ITEM / MODEL</th>
                                            <?php if($isComputer): ?>
                                                <th style="min-width: 150px;">PROCESSOR</th>
                                                <th style="min-width: 100px;">RAM</th>
                                                <th style="min-width: 180px;">STORAGE</th>
                                            <?php endif; ?>
                                            <th class="text-end pe-3" style="min-width: 90px;">ACTIONS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($catModels as $m): ?>
                                        <tr class="spec-row">
                                            <td>
                                                <div class="item-title"><?= htmlspecialchars(strtoupper($m['item_name'])) ?></div>
                                                <div class="item-subtitle"><?= htmlspecialchars($m['model_name']) ?></div>
                                            </td>
                                            <?php if($isComputer): ?>
                                                <td>
                                                    <?= !empty($m['processor']) ? htmlspecialchars($m['processor']) : '-' ?>
                                                </td>
                                                <td>
                                                    <?= !empty($m['ram']) ? htmlspecialchars($m['ram']) : '-' ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $storage_str = trim(implode(' ', array_filter([$m['storage_size'], $m['storage_type']])));
                                                        echo !empty($storage_str) ? htmlspecialchars($storage_str) : '-';
                                                    ?>
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
                <p class="mb-0 small fw-medium">No model specifications recorded yet. Click "Add Specification" above to register one.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- EDIT MODAL STRUCTURE -->
<div class="modal fade" id="editSpecModal" tabindex="-1" aria-labelledby="editSpecModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 border shadow-sm">
            <form method="POST">
                <div class="modal-header border-bottom p-3">
                    <h6 class="fw-bold m-0" id="editSpecModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Specification Model</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="small fw-semibold text-secondary mb-1">Model Name</label>
                        <input type="text" name="model_name" id="edit_model_name" class="form-control form-control-sm rounded-1" required>
                    </div>

                    <!-- Dynamic Hardware Specs Container -->
                    <div id="modalTechSpecsContainer">
                        <div class="mb-3">
                            <label class="small fw-semibold text-secondary mb-1">Processor</label>
                            <input type="text" name="processor" id="edit_processor" class="form-control form-control-sm rounded-1">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-semibold text-secondary mb-1">RAM Size</label>
                                <input type="text" name="ram" id="edit_ram" class="form-control form-control-sm rounded-1">
                            </div>
                            <div class="col-6">
                                <label class="small fw-semibold text-secondary mb-1">Storage Type</label>
                                <select name="storage_type" id="edit_storage_type" class="form-select form-select-sm rounded-1">
                                    <option value="">Select Type</option>
                                    <option value="SSD">SSD</option>
                                    <option value="HDD">HDD</option>
                                    <option value="NVMe">NVMe</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-semibold text-secondary mb-1">Storage Size</label>
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
        title: '<?= ($type == "success" ? "Done!" : "Notice") ?>',
        text: '<?= htmlspecialchars($msg) ?>',
        timer: 3000,
        showConfirmButton: false
    });
</script>
<?php endif; ?>

<script>
$(document).ready(function() {
    $('#editSpecModal').appendTo('body');

    // Global Live Search Across Categories and Specifications
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
                    $group.find('.accordion-collapse').addClass('show');
                    $group.find('.accordion-button').removeClass('collapsed').attr('aria-expanded', 'true');
                } else {
                    $group.find('.accordion-collapse').removeClass('show');
                    $group.find('.accordion-button').addClass('collapsed').attr('aria-expanded', 'false');
                }
            } else {
                $group.hide();
            }
        });

        if (filter === '') {
            allCollapsed = true;
            $('#toggleCollapseText').text('Expand All');
            $('#btnToggleCollapse i').removeClass('bi-arrows-collapse').addClass('bi-arrows-expand');
        }
    });

    $('#resetSpecSearch').on('click', function() {
        $('#specSearch').val('').trigger('keyup');
    });

    // Expand / Collapse All Toggle
    let allCollapsed = true;
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

    // Show/Hide Hardware Spec Wrapper when Computer category is selected
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

    // Delete Confirmation via SweetAlert2
    $(document).on('click', '.delete-spec-btn', function() {
        const id = $(this).attr('data-id');
        const name = $(this).attr('data-name');
        
        Swal.fire({
            title: 'Delete Model Spec?',
            text: `Are you sure you want to remove model "${name}"?`,
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