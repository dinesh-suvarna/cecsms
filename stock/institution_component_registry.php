<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Institution & Department Component Registry";
$page_icon  = "bi-tags";

$role = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;

// Restrict to SuperAdmin or redirect if needed, or handle accordingly
if ($role !== 'SuperAdmin') {
    header("Location: view_components.php");
    exit();
}

/* ================= HELPERS ================= */
function getAssetIcon(string $itemName, $category = '') {
    $name = strtolower($itemName);
    $cat  = strtolower($category);
    
    switch (true) {
        case (strpos($name, 'oscilloscope') !== false || strpos($name, 'power scope') !== false):
            return 'bi-activity';
        case (strpos($name, 'function generator') !== false):
            return 'bi-soundwave';
        case (strpos($name, 'power supply') !== false):
            return 'bi-plug-fill';
        case (strpos($name, 'trainer kit') !== false):
            return 'bi-laptop';
        case (strpos($name, 'ic tester') !== false):
            return 'bi-cpu-fill';
        case (strpos($name, 'ram') !== false || $cat === 'ram'):
            return 'bi-memory';
        case (strpos($name, 'ssd') !== false || strpos($name, 'hdd') !== false || strpos($name, 'storage') !== false):
            return 'bi-hdd';
        case (strpos($name, 'processor') !== false || strpos($name, 'cpu') !== false):
            return 'bi-cpu';
        case (strpos($name, 'motherboard') !== false):
            return 'bi-motherboard';
        case (strpos($name, 'smps') !== false || strpos($name, 'power') !== false):
            return 'bi-lightning-charge';
        case (strpos($name, 'fan') !== false || strpos($name, 'cooler') !== false):
            return 'bi-fan';
        default:
            return 'bi-box-seam';
    }
}

/* ================= FETCH DATA WITH INSTITUTION HIERARCHY ================= */
$query = "
    SELECT 
        ca.id AS asset_id,
        ca.asset_tag,
        cs.item_name,
        cs.category,
        cs.specification,
        u.unit_name, 
        u.unit_code,
        d.division_name,
        i.institution_name
    FROM component_assets ca
    JOIN component_stock cs ON ca.stock_id = cs.id
    LEFT JOIN units u ON cs.unit_id = u.id
    LEFT JOIN divisions d ON cs.division_id = d.id
    LEFT JOIN institutions i ON d.institution_id = i.id
    ORDER BY i.institution_name ASC, d.division_name ASC, u.unit_code ASC, cs.item_name ASC, ca.asset_tag ASC
";

$result = $conn->query($query);
$institutions = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inst_name  = $row['institution_name'] ?? 'General Institution';
        $div_name   = $row['division_name'] ?? 'General Division';
        $unit_label = ($row['unit_code'] ? $row['unit_code'] . " - " : "") . ($row['unit_name'] ?? 'General/Unassigned');

        // Grouping: Institution -> Division -> Unit -> Item Name -> Assets
        $institutions[$inst_name][$div_name][$unit_label][$row['item_name']][] = $row;
    }
}

ob_start();
?>

<style>
/* Accordion & Table Styling */
.institution-accordion .accordion-item { border: 1px solid #cbd5e1 !important; margin-bottom: 1rem; border-radius: 8px !important; background: #ffffff; overflow: hidden; }
.institution-accordion .accordion-button { background-color: #f1f5f9; color: #102f4a; font-weight: 700; font-size: 1rem; }
.institution-accordion .accordion-button:not(.collapsed) { background-color: #e2e8f0; color: #173f63; box-shadow: none; }

.division-accordion .accordion-item { border: 1px solid #cbd5e1 !important; margin-bottom: 0.75rem; border-radius: 6px !important; background: #ffffff; overflow: hidden; }
.division-accordion .accordion-button { background-color: #f8fafc; color: #1e293b; font-weight: 600; font-size: 0.9rem; }
.division-accordion .accordion-button:not(.collapsed) { background-color: #89d9df; color: #173f63; border-left: 4px solid #173f63; box-shadow: none; }

.asset-group-card { border: 1px solid #e2e8f0; border-radius: 6px; background-color: #ffffff; margin-bottom: 0.75rem; overflow: hidden; }
.asset-group-button { width: 100%; background-color: #f0f4f8; border: none; border-bottom: 1px solid #cbd5e1; padding: 0.75rem 1rem; text-align: left; transition: background-color 0.15s ease-in-out; display: flex; align-items: center; justify-content: space-between; text-decoration: none; }
.asset-group-button:hover { background-color: #e2e8f0; }
.asset-group-button:focus { outline: none; box-shadow: none; }
.asset-group-button.collapsed { border-bottom: none; }
.asset-group-button::after { flex-shrink: 0; width: 1.25rem; height: 1.25rem; margin-left: auto; content: ""; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23173f63'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-size: 1.25rem; transition: transform 0.2s ease-in-out; }
.asset-group-button:not(.collapsed)::after { transform: rotate(-180deg); }

.table-erp-minimal th { background-color: #ffffff; color: #475569; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 0.6rem 1rem; border-bottom: 1px solid #e2e8f0; }
.table-erp-minimal td { padding: 0.65rem 1rem; font-size: 0.85rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
.extra-small { font-size: .78rem; }
</style>

<div class="container-fluid p-0 pb-4">
    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
        <div>
            <h4 class="fw-bold mb-1 text-dark" style="font-size: 1.25rem;">
                <i class="bi bi-tags me-2 text-primary"></i>Institution & Department Component Registry
            </h4>
            <p class="text-muted mb-0 small">Hierarchical overview of component assets of departments.</p>
        </div>
    </div>

    <?php if (empty($institutions)): ?>
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body text-center py-5 text-muted small">
                <i class="bi bi-folder2-open display-6 d-block mb-2 opacity-50"></i>
                No component asset records found.
            </div>
        </div>
    <?php else: ?>
        <!-- SUPERADMIN VIEW: Institution -> Division Accordions -->
        <div class="accordion institution-accordion" id="institutionAccordion">
            <?php $i = 0; foreach ($institutions as $inst_name => $divisions): $i++; $instCollapse = "instCollapse_" . $i; ?>
            <div class="accordion-item shadow-sm">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $instCollapse ?>">
                        <i class="bi bi-bank me-2 text-primary"></i> <?= htmlspecialchars($inst_name) ?>
                    </button>
                </h2>
                <div id="<?= $instCollapse ?>" class="accordion-collapse collapse" data-bs-parent="#institutionAccordion">
                    <div class="accordion-body p-3 bg-light">
                        
                        <!-- Divisions Accordion Level -->
                        <div class="accordion division-accordion" id="divAccordion_<?= $i ?>">
                            <?php $j = 0; foreach ($divisions as $div_name => $units): $j++; $divCollapse = "divCollapse_" . $i . "_" . $j; ?>
                            <div class="accordion-item shadow-sm">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $divCollapse ?>">
                                        <i class="bi bi-building fs-5 me-2 text-secondary"></i> <?= htmlspecialchars($div_name) ?>
                                    </button>
                                </h2>
                                <div id="<?= $divCollapse ?>" class="accordion-collapse collapse" data-bs-parent="#divAccordion_<?= $i ?>">
                                    <div class="accordion-body p-3 bg-white">
                                        
                                        <!-- CALL UNITS DATA & RENDER TABLES HERE -->
                                        <?php renderComponentUnitsAccordion($units, "inst_" . $i . "_div_" . $j); ?>

                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php 
/* ================= UNITS DATA RENDER FUNCTION ================= */
function renderComponentUnitsAccordion(array $units, string $prefix) {
    $k = 0; 
    foreach ($units as $unit_label => $items): 
        $k++; 
        $unitCollapseId = "unitCollapse_" . $prefix . "_" . $k;
        $groupAccordionId = "groupAccordion_" . $prefix . "_" . $k;
    ?>
    <div class="asset-group-card shadow-sm mb-3">
        <button class="asset-group-button collapsed d-flex align-items-center justify-content-between" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $unitCollapseId ?>">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-building fs-5 text-secondary"></i>
                <span class="fw-bold text-dark"><?= htmlspecialchars($unit_label) ?></span>
            </div>
        </button>
        <div id="<?= $unitCollapseId ?>" class="collapse">
            <div class="p-3 bg-light">
                <div class="accordion" id="<?= $groupAccordionId ?>">
                    <?php $j = 0; foreach ($items as $item_name => $grouped_items): $j++;
                        $groupCollapseId = "groupCollapse_" . $prefix . "_" . $k . "_" . $j;
                        $first_asset = $grouped_items[0];
                        $category = $first_asset['category'] ?? 'General';
                        $specification = $first_asset['specification'] ?? 'No specifications';
                        $category_sl = 1;
                    ?>
                    <div class="asset-group-card shadow-sm mb-2 border">
                        <button class="asset-group-button collapsed d-flex align-items-center justify-content-between flex-wrap gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $groupCollapseId ?>">
                            <div class="d-flex align-items-center flex-wrap gap-2 me-3">
                                <i class="bi <?= getAssetIcon($item_name, $category) ?> fs-5 me-1 text-primary"></i>
                                <span class="fw-bold text-dark me-2"><?= htmlspecialchars($item_name) ?></span>
                                <span class="text-secondary extra-small"><i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($specification) ?></span>
                            </div>
                            <div>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border fw-bold">Total: <?= count($grouped_items) ?></span>
                            </div>
                        </button>
                        <div id="<?= $groupCollapseId ?>" class="collapse" data-bs-parent="#<?= $groupAccordionId ?>">
                            <div class="table-responsive">
                                <table class="table align-middle table-erp-minimal mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 70px;" class="ps-3">Sl. No</th>
                                            <th>Component Item</th>
                                            <th>Specifications & Category</th>
                                            <th>Asset Tag ID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grouped_items as $asset): ?>
                                        <tr>
                                            <td class="ps-3 text-muted fw-semibold"><?= $category_sl++ ?></td>
                                            <td><div class="fw-bold text-dark"><?= htmlspecialchars($item_name) ?></div></td>
                                            <td>
                                                <div class="text-secondary fw-semibold extra-small"><?= htmlspecialchars($asset['specification'] ?? 'No specifications added') ?></div>
                                                <div class="text-muted extra-small"><?= htmlspecialchars($category) ?></div>
                                            </td>
                                            <td><span class="fw-semibold text-primary"><?= htmlspecialchars($asset['asset_tag']) ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php 
    endforeach;
}

$content = ob_get_clean(); 

// Load SuperAdmin Stock Layout Wrapper
include "../stock/stocklayout.php";
?>