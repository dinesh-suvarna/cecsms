<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Institution & Department Asset Registry";
$page_icon  = "bi-bank";

$role = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;

/* ================= HELPERS ================= */
function getAssetIcon(string $itemName, $category = '') {
    $name = strtolower($itemName);
    $cat  = strtolower($category);
    
    switch (true) {
        case (strpos($name, 'computer') !== false || strpos($name, 'desktop') !== false || $cat === 'computer'):
            return 'bi-pc-display';
        case (strpos($name, 'laptop') !== false):
            return 'bi-laptop';
        case (strpos($name, 'monitor') !== false):
            return 'bi-display';
        case (strpos($name, 'rack') !== false || strpos($name, 'server') !== false):
            return 'bi-hdd-rack'; 
        case (strpos($name, 'switch') !== false || strpos($name, 'hub') !== false):
            return 'bi-hdd-stack'; 
        case (strpos($name, 'router') !== false):
            return 'bi-router';
        case ($cat === 'networking'):
            return 'bi-diagram-3';
        case (strpos($name, 'printer') !== false):
            return 'bi-printer';
        case (strpos($name, 'keyboard') !== false):
            return 'bi-keyboard';
        case (strpos($name, 'mouse') !== false):
            return 'bi-mouse3';
        case (strpos($name, 'projector') !== false):
            return 'bi-projector';  
        case (strpos($name, 'ups') !== false || strpos($name, 'battery') !== false):
            return 'bi-lightning-charge';
        case (strpos($name, 'table') !== false || strpos($name, 'desk') !== false):
            return 'bi-table';
        case (strpos($name, 'chair') !== false):
            return 'bi-person-workspace';
        case (strpos($name, 'camera') !== false || strpos($name, 'cctv') !== false):
            return 'bi-camera-video';
        default:
            return 'bi-box-seam';
    }
}

/* ================= FETCH DATA ================= */
$query = "
    SELECT 
        da.id, da.division_asset_id, im.item_name, im.category, sd.serial_number, 
        u.unit_name, u.unit_code,
        d.division_name,
        i.institution_name,
        mo.model_name,
        CONCAT_WS(' | ', mo.processor, mo.ram, CONCAT(mo.storage_size, ' ', mo.storage_type)) as full_config 
    FROM division_assets da
    JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    JOIN stock_details sd ON sd.id = da.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    LEFT JOIN units u ON u.id = dm.unit_id
    LEFT JOIN divisions d ON d.id = dm.division_id
    LEFT JOIN institutions i ON i.id = d.institution_id
    LEFT JOIN item_models mo ON sd.model_id = mo.id
    WHERE da.status = 'assigned'
";

if ($role !== 'SuperAdmin') { 
    $query .= " AND dm.division_id = $division_id"; 
}
$query .= " ORDER BY i.institution_name ASC, d.division_name ASC, u.unit_code ASC, im.item_name ASC, da.division_asset_id ASC";

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
.asset-group-button { width: 100%; background-color: #f0f4f8; border: none; border-bottom: 1px solid #cbd5e1; padding: 0.75rem 1rem; text-align: left; transition: background-color 0.15s ease-in-out; }
.asset-group-button:hover { background-color: #e2e8f0; }
.asset-group-button:focus { outline: none; box-shadow: none; }
.asset-group-button.collapsed { border-bottom: none; }
.asset-group-button::after { flex-shrink: 0; width: 1.25rem; height: 1.25rem; margin-left: auto; content: ""; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23173f63'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-size: 1.25rem; transition: transform 0.2s ease-in-out; }
.asset-group-button:not(.collapsed)::after { transform: rotate(-180deg); }

.table-erp-minimal th { background-color: #ffffff; color: #475569; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 0.6rem 1rem; border-bottom: 1px solid #e2e8f0; }
.table-erp-minimal td { padding: 0.65rem 1rem; font-size: 0.85rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
.btn-erp-outline { font-weight: 600; font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 4px; border: 1px solid #173f63; color: #173f63; background: transparent; }
.btn-erp-outline:hover { background-color: #173f63; color: #ffffff; }
@media print {
    body * {
        visibility: hidden;
    }
    #barcodeTagModal, #barcodeTagModal * {
        visibility: visible;
    }
    #barcodeTagModal {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        background: white !important;
    }
    .modal-backdrop, .modal-header .btn-close, .modal-footer {
        display: none !important;
    }
}
</style>

<div class="container-fluid p-0 pb-4">
    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-dark" style="font-size: 1.25rem;">
                <i class="bi bi-bank me-2 text-primary"></i>Institution & Department Asset Registry
            </h4>
            <p class="text-muted mb-0 small">Hierarchical overview of institution inventory and labs/facilities allocations.</p>
        </div>
        <div style="min-width: 280px; max-width: 350px; flex: 1;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="assetLiveSearch" class="form-control" placeholder="Search item name or serial...">
                <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" title="Clear Search"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
    </div>

    <!-- SUPERADMIN VIEW: Institution -> Division Accordions -->
    <div class="accordion institution-accordion" id="institutionAccordion">
        <?php $i = 0; foreach ($institutions as $inst_name => $divisions): $i++; $instCollapse = "instCollapse" . $i; ?>
        <div class="accordion-item shadow-sm">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $instCollapse ?>">
                    <i class="bi bi-bank me-2 text-primary"></i> <?= htmlspecialchars($inst_name) ?>
                </button>
            </h2>
            <div id="<?= $instCollapse ?>" class="accordion-collapse collapse" data-bs-parent="#institutionAccordion">
                <div class="accordion-body p-3 bg-light">
                    
                    <!-- Divisions Accordion Level -->
                    <div class="accordion division-accordion" id="divAccordion<?= $i ?>">
                        <?php $j = 0; foreach ($divisions as $div_name => $units): $j++; $divCollapse = "divCollapse_" . $i . "_" . $j; ?>
                        <div class="accordion-item shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $divCollapse ?>">
                                    <i class="bi bi-building fs-5 me-2 text-secondary"></i> <?= htmlspecialchars($div_name) ?>
                                </button>
                            </h2>
                            <div id="<?= $divCollapse ?>" class="accordion-collapse collapse" data-bs-parent="#divAccordion<?= $i ?>">
                                <div class="accordion-body p-3 bg-white">
                                    
                                    <!-- CALL UNITS DATA & RENDER TABLES HERE -->
                                    <?php renderUnitsAccordion($units, "inst_" . $i . "_div_" . $j, $inst_name); ?>

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
</div>

<!-- QR Code Tag Modal -->
<div class="modal fade" id="barcodeTagModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark" style="font-size: 1rem;">
                    <i class="bi bi-qr-code-scan text-primary me-2"></i>Asset QR Code Tag
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body text-center p-4">
                <!-- TOP: Institution / Unit Name -->
                <div class="mb-3 pb-2 border-bottom">
                    <span id="modalInstName" class="d-block fw-bold text-secondary text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;"></span>
                    <span id="modalUnitName" class="d-block fw-semibold text-dark" style="font-size: 13px;"></span>
                </div>

                <!-- MIDDLE: The Compact QR Code Graphic Container -->
                <div class="p-3 bg-white border rounded-3 d-inline-block shadow-sm mb-3">
                    <div id="qrcodeContainer" class="d-flex justify-content-center"></div>
                </div>

                <div class="bg-light p-2 rounded border border-secondary-subtle text-start mx-auto" style="max-width: 340px; font-size: 12px;">
                    <div class="d-flex mb-1">
                        <span class="text-muted me-1">Asset ID: </span>
                        <span id="modalTextAssetId" class="fw-bold text-primary"></span>
                    </div>
                    <div class="d-flex mb-1">
                        <span class="text-muted me-1">Serial:</span>
                        <span id="modalTextSerial" class="fw-semibold text-dark"></span>
                    </div>
                    <div class="d-flex">
                        <span class="text-muted me-1">Model:</span>
                        <span id="modalTextModel" class="fw-semibold text-dark text-truncate" style="max-width: 200px;"></span>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print();">
                    <i class="bi bi-printer me-1"></i> Print Tag
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('assetLiveSearch');
    const clearBtn = document.getElementById('clearSearchBtn');
    
    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        let query = this.value.toLowerCase().trim();
        let institutionItems = document.querySelectorAll('.institution-accordion > .accordion-item');

        institutionItems.forEach(function(instItem) {
            let instHasMatch = false;
            let divisionItems = instItem.querySelectorAll('.division-accordion > .accordion-item');

            divisionItems.forEach(function(divItem) {
                let divHasMatch = false;
                let unitCards = divItem.querySelectorAll('.asset-group-card');

                unitCards.forEach(function(unitCard) {
                    let groupCards = unitCard.querySelectorAll('.asset-group-card');
                    
                    if (groupCards.length > 0) {
                        let unitHasMatch = false;
                        groupCards.forEach(function(groupCard) {
                            let matchFound = evaluateGroupCard(groupCard, query);
                            if (matchFound) {
                                unitHasMatch = true;
                                divHasMatch = true;
                                instHasMatch = true;
                            }
                        });

                        if (query === '' || unitHasMatch) {
                            unitCard.style.display = '';
                        } else {
                            unitCard.style.display = 'none';
                        }
                    } else {
                        let matchFound = evaluateGroupCard(unitCard, query);
                        if (matchFound) {
                            divHasMatch = true;
                            instHasMatch = true;
                        }
                    }
                });

                let divCollapseEl = divItem.querySelector('.accordion-collapse');
                let divButton = divItem.querySelector('.accordion-button');
                let bsDivCollapse = bootstrap.Collapse.getInstance(divCollapseEl) || new bootstrap.Collapse(divCollapseEl, { toggle: false });

                if (query === '') {
                    divItem.style.display = '';
                    bsDivCollapse.hide(); 
                    divButton.classList.add('collapsed');
                } else if (divHasMatch) {
                    divItem.style.display = '';
                    bsDivCollapse.show(); 
                    divButton.classList.remove('collapsed');
                } else {
                    divItem.style.display = 'none';
                }
            });

            let instCollapseEl = instItem.querySelector('.accordion-collapse');
            let instButton = instItem.querySelector('.accordion-button');
            let bsInstCollapse = bootstrap.Collapse.getInstance(instCollapseEl) || new bootstrap.Collapse(instCollapseEl, { toggle: false });

            if (query === '') {
                instItem.style.display = '';
                bsInstCollapse.hide(); 
                instButton.classList.add('collapsed');
            } else if (instHasMatch) {
                instItem.style.display = '';
                bsInstCollapse.show(); 
                instButton.classList.remove('collapsed');
            } else {
                instItem.style.display = 'none';
            }
        });
    });

    function evaluateGroupCard(groupCard, query) {
        let groupButton = groupCard.querySelector('.asset-group-button');
        let tableRows = groupCard.querySelectorAll('tbody tr');
        let groupCollapseEl = groupCard.querySelector('.accordion-collapse');
        
        if (!groupButton) return false;

        let headerText = groupButton.innerText.toLowerCase();
        let itemHeaderMatch = headerText.includes(query);

        let rowMatchedAny = false;
        tableRows.forEach(function(row) {
            let rowText = row.innerText.toLowerCase();
            if (query === '' || itemHeaderMatch || rowText.includes(query)) {
                row.style.display = '';
                rowMatchedAny = true;
            } else {
                row.style.display = 'none';
            }
        });

        let bsGroupCollapse = groupCollapseEl ? (bootstrap.Collapse.getInstance(groupCollapseEl) || new bootstrap.Collapse(groupCollapseEl, { toggle: false })) : null;

        if (query === '') {
            groupCard.style.display = '';
            if (bsGroupCollapse) bsGroupCollapse.hide();
            if (groupButton) groupButton.classList.add('collapsed');
            return false;
        }

        if (itemHeaderMatch || rowMatchedAny) {
            groupCard.style.display = '';
            if (bsGroupCollapse) bsGroupCollapse.show(); 
            if (groupButton) groupButton.classList.remove('collapsed');
            return true;
        } else {
            groupCard.style.display = 'none';
            return false;
        }
    }

    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
        searchInput.focus();
    });
});
</script>

<script src="../admin/assets/js/qrcode.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const modalEl = document.getElementById('barcodeTagModal');
    if (!modalEl) return;

    document.body.appendChild(modalEl);
    const barcodeModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    let qrCodeInstance = null;

    document.querySelectorAll('.generate-code-btn').forEach(button => {
        button.addEventListener('click', function() {
            const assetId = this.getAttribute('data-asset-id');
            const serial = this.getAttribute('data-serial');
            const model = this.getAttribute('data-model');
            const instName = this.getAttribute('data-institution');
            const unitName = this.getAttribute('data-unit');

            // Populate Modal Texts
            document.getElementById('modalInstName').innerText = instName;
            document.getElementById('modalUnitName').innerText = unitName;
            document.getElementById('modalTextAssetId').innerText = assetId;
            document.getElementById('modalTextSerial').innerText = serial;
            document.getElementById('modalTextModel').innerText = model;

           
            const qrContainer = document.getElementById('qrcodeContainer');
            qrContainer.innerHTML = "";

            // Generate QR code
            try {
                qrCodeInstance = new QRCode(qrContainer, {
                    text: assetId,
                    width: 140,
                    height: 140,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {
                console.error("QR Code generation error: ", e);
            }

            barcodeModal.show();
        });
    });
});
</script>

<?php 
/* ================= UNITS DATA RENDER FUNCTION ================= */
function renderUnitsAccordion(array $units, string $prefix, string $inst_name) {
    $k = 0; 
    foreach ($units as $unit_label => $assets): 
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
                    <?php $j = 0; foreach ($assets as $item_type => $grouped_items): $j++;
                        $groupCollapseId = "groupCollapse_" . $prefix . "_" . $k . "_" . $j;
                        $first_asset = $grouped_items[0];
                        $model_name = $first_asset['model_name'] ?: 'Standard Model';
                        $full_config = $first_asset['full_config'] ?: 'Standard Hardware Config';
                        $category_sl = 1;
                    ?>
                    <div class="asset-group-card shadow-sm mb-2 border">
                        <button class="asset-group-button collapsed d-flex align-items-center justify-content-between flex-wrap gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $groupCollapseId ?>">
                            <div class="d-flex align-items-center flex-wrap gap-2 me-3">
                                <i class="bi <?= getAssetIcon($item_type, $first_asset['category'] ?? '') ?> fs-5 me-1 text-primary"></i>
                                <span class="fw-bold text-dark me-2"><?= htmlspecialchars($item_type) ?></span>
                                <span class="badge bg-white text-dark border fw-semibold me-2"><?= htmlspecialchars($model_name) ?></span>
                                <span class="text-secondary small"><i class="bi bi-cpu me-1"></i><?= htmlspecialchars($full_config) ?></span>
                            </div>
                            <div>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border fw-bold">Total: <?= count($grouped_items) ?></span>
                            </div>
                        </button>
                        <div id="<?= $groupCollapseId ?>" class="collapse" data-bs-parent="#<?= $groupAccordionId ?>">
                            <div class="table-responsive">
                                <table class="table align-middle table-erp-minimal">
                                    <thead>
                                        <tr>
                                            <th style="width: 70px;">Sl. No</th>
                                            <th>Serial Number</th>
                                            <th>Asset Tag / ID</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grouped_items as $asset): ?>
                                        <tr>
                                            <td class="text-muted fw-semibold"><?= $category_sl++ ?></td>
                                            <td class="fw-semibold"><i class="bi bi-barcode me-1 text-muted"></i><?= htmlspecialchars($asset['serial_number'] ?: 'N/A') ?></td>
                                            <td><span class="fw-semibold text-primary"><?= htmlspecialchars($asset['division_asset_id']) ?></span></td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-erp-outline btn-sm generate-code-btn" 
                                                        data-asset-id="<?= htmlspecialchars($asset['division_asset_id']) ?>"
                                                        data-serial="<?= htmlspecialchars($asset['serial_number'] ?: 'N/A') ?>"
                                                        data-model="<?= htmlspecialchars($model_name) ?>"
                                                        data-item="<?= htmlspecialchars($item_type) ?>"
                                                        data-institution="<?= htmlspecialchars($inst_name) ?>"
                                                        data-unit="<?= htmlspecialchars($unit_label) ?>"
                                                        title="Generate QR Code">
                                                    <i class="bi bi-qr-code me-1"></i> QR Code
                                                </button>
                                            </td>
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

if ($role === 'SuperAdmin') {
    include "../stock/stocklayout.php"; 
} else {
    include "../divisions/divisionslayout.php";
}
?>