<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Add Procurement Year";
$page_icon  = "bi-bank";

$role = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;

/* ================= AJAX HANDLER FOR YEAR UPDATES & RESETS ================= */
if (isset($_POST['ajax_action']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    header('Content-Type: application/json');
    $action_type = $_POST['action_type'] ?? '';
    
    if ($action_type === 'individual_year') {
        $stock_detail_id = (int)$_POST['stock_detail_id'];
        $proc_year = trim($_POST['procurement_year']);
        
        if (!preg_match('/^\d{4}$/', $proc_year)) {
            echo json_encode(['status' => 'error', 'message' => 'A valid 4-digit year is required.']);
            exit;
        }

        $safe_year = $proc_year === '' ? "NULL" : "'" . $conn->real_escape_string($proc_year) . "'";
        $update_sql = "UPDATE stock_details SET procurement_year = $safe_year WHERE id = $stock_detail_id";
        
        if ($conn->query($update_sql)) {
            echo json_encode(['status' => 'success', 'message' => 'Year updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
        }
        exit;
    } 
    elseif ($action_type === 'bulk_year') {
        $proc_year = trim($_POST['bulk_procurement_year']);
        $stock_ids = $_POST['stock_detail_ids'] ?? [];
        
        if (!preg_match('/^\d{4}$/', $proc_year)) {
            echo json_encode(['status' => 'error', 'message' => 'A valid 4-digit year is required.']);
            exit;
        }

        if (!empty($stock_ids)) {
            $safe_ids = array_map('intval', $stock_ids);
            $ids_list = implode(',', $safe_ids);
            
            $safe_year = $proc_year === '' ? "NULL" : "'" . $conn->real_escape_string($proc_year) . "'";
            $update_sql = "UPDATE stock_details SET procurement_year = $safe_year WHERE id IN ($ids_list)";
            
            if ($conn->query($update_sql)) {
                echo json_encode(['status' => 'success', 'message' => 'Batch years updated successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Batch update failed.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No items selected for batch update.']);
        }
        exit;
    }
    elseif ($action_type === 'reset_batch') {
        $stock_ids = $_POST['stock_detail_ids'] ?? [];
        
        if (!empty($stock_ids)) {
            $safe_ids = array_map('intval', $stock_ids);
            $ids_list = implode(',', $safe_ids);
            
            $update_sql = "UPDATE stock_details SET procurement_year = NULL WHERE id IN ($ids_list)";
            
            if ($conn->query($update_sql)) {
                echo json_encode(['status' => 'success', 'message' => 'Batch years reset successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Batch reset failed.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No items selected for reset.']);
        }
        exit;
    }
}

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
        da.id, da.division_asset_id, da.stock_detail_id, im.item_name, im.category, sd.serial_number, sd.procurement_year, sd.bill_no, sd.bill_date,
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
$query .= " ORDER BY i.institution_name ASC, d.division_name ASC, u.unit_code ASC, im.item_name ASC, sd.bill_no ASC, da.division_asset_id ASC";

$result = $conn->query($query);
$institutions = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inst_name  = $row['institution_name'] ?? 'General Institution';
        $div_name   = $row['division_name'] ?? 'General Division';
        $unit_label = ($row['unit_code'] ? $row['unit_code'] . " - " : "") . ($row['unit_name'] ?? 'General/Unassigned');

        $bill_tag = !empty($row['bill_no']) ? " (Bill: " . $row['bill_no'] . ")" : " (Direct Stock)";
        $group_title = $row['item_name'] . $bill_tag;

        $institutions[$inst_name][$div_name][$unit_label][$group_title][] = $row;
    }
}

ob_start();
?>

<style>
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
.asset-group-button::after { flex-shrink: 0; width: 1.25rem; height: 1.25rem; margin-left: auto; content: ""; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23173f63'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708 z'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-size: 1.25rem; transition: transform 0.2s ease-in-out; }
.asset-group-button:not(.collapsed)::after { transform: rotate(-180deg); }

.table-erp-minimal th { background-color: #ffffff; color: #475569; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 0.6rem 1rem; border-bottom: 1px solid #e2e8f0; }
.table-erp-minimal td { padding: 0.65rem 1rem; font-size: 0.85rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; }


.inline-feedback {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    animation: fadeInOut 0.3s ease-in-out;
}
.inline-feedback.success { background-color: #d1e7dd; color: #0f5132; }
.inline-feedback.error { background-color: #f8d7da; color: #842029; }

@keyframes fadeInOut {
    from { opacity: 0; transform: translateY(-3px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="container-fluid p-0 pb-4">
    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-dark" style="font-size: 1.25rem;">
                <i class="bi bi-bank me-2 text-primary"></i>Institution & Department Asset Registry
            </h4>
            <p class="text-muted mb-0 small">Hierarchical overview of institution inventory, labs/facilities allocations, and procurement year tracking.</p>
        </div>
        <div style="min-width: 280px; max-width: 350px; flex: 1;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="assetLiveSearch" class="form-control" placeholder="Search item name or serial...">
                <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" title="Clear Search"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
    </div>

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
                                    <?php renderUnitsAccordion($units, "inst_" . $i . "_div_" . $j); ?>
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

    document.addEventListener('input', function(e) {
        if (e.target && e.target.classList.contains('individual-year-input')) {
            const form = e.target.closest('form');
            const btn = form.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }
    });
});

// Function to show feedback text right next to the active form
function showInlineFeedback(form, message, type = 'success') {
    // Remove any existing feedback in this form first
    const existing = form.querySelector('.inline-feedback');
    if (existing) existing.remove();

    const feedback = document.createElement('span');
    feedback.className = `inline-feedback ${type}`;
    feedback.innerHTML = `<i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'}"></i> ${message}`;
    
    form.appendChild(feedback);

    setTimeout(() => {
        feedback.style.transition = 'opacity 0.5s ease';
        feedback.style.opacity = '0';
        setTimeout(() => feedback.remove(), 500);
    }, 3000);
}

document.addEventListener('submit', function(e) {
    if (e.target && e.target.classList.contains('ajax-year-form')) {
        e.preventDefault();
        const form = e.target;
        const actionTypeInput = form.querySelector('input[name="action_type"]');
        const actionType = actionTypeInput ? actionTypeInput.value : '';

       
        if (actionType === 'reset_batch') {
            if (!window.confirm('Are you sure you want to reset the procurement year for all items in this batch?')) {
                return;
            }
        }

        
        let yearInput = null;
        let yearVal = '';
        if (actionType !== 'reset_batch') {
            yearInput = form.querySelector('input[name="procurement_year"]') || form.querySelector('input[name="bulk_procurement_year"]');
            yearVal = yearInput ? yearInput.value.trim() : '';

            if (yearVal !== '' && !/^\d{4}$/.test(yearVal)) {

            if (!/^\d{4}$/.test(yearVal)) {
                showInlineFeedback(form, 'Procurement year is required (4 digits)', 'error');
                if (yearInput) yearInput.focus();
                return;
            }
        }
        }

        const formData = new FormData(form);
        formData.append('ajax_action', '1');

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        submitBtn.disabled = true;

        fetch('', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;

            if (data.status === 'success') {
                showInlineFeedback(form, data.message, 'success');

                if (actionType === 'individual_year') {
                    submitBtn.innerHTML = '<i class="bi bi-check-lg text-white"></i>';
                    submitBtn.classList.remove('btn-outline-primary');
                    submitBtn.classList.add('btn-success');
                } else {
                    submitBtn.innerHTML = originalHtml;
                }

                
                if (actionType === 'bulk_year') {
                    const groupCard = form.closest('.asset-group-card');
                    groupCard.querySelectorAll('input.individual-year-input').forEach(input => {
                        input.value = yearVal;
                        const rowForm = input.closest('form');
                        const rowBtn = rowForm.querySelector('button[type="submit"]');
                        rowBtn.innerHTML = '<i class="bi bi-check-lg text-white"></i>';
                        rowBtn.classList.remove('btn-outline-primary');
                        rowBtn.classList.add('btn-success');
                    });
                }
                
                
                if (actionType === 'reset_batch') {
                    const groupCard = form.closest('.asset-group-card');
                    const bulkInput = groupCard.querySelector('input[name="bulk_procurement_year"]');
                    if (bulkInput) bulkInput.value = '';
                    
                    groupCard.querySelectorAll('input.individual-year-input').forEach(input => {
                        input.value = '';
                        const rowForm = input.closest('form');
                        const rowBtn = rowForm.querySelector('button[type="submit"]');
                        rowBtn.innerHTML = '<i class="bi bi-check-lg"></i>';
                        rowBtn.classList.remove('btn-success');
                        rowBtn.classList.add('btn-outline-primary');
                    });
                }
            } else {
                submitBtn.innerHTML = originalHtml;
                showInlineFeedback(form, data.message, 'error');
            }
        })
        .catch(error => {
            submitBtn.innerHTML = originalHtml;
            submitBtn.disabled = false;
            showInlineFeedback(form, 'Network error occurred.', 'error');
            console.error('Error:', error);
        });
    }
});
</script>

<?php 
function renderUnitsAccordion(array $units, string $prefix) {$k = 0; 
    foreach ($units as $unit_label =>$grouped_batches): 
        $k++;$unitCollapseId = "unitCollapse_" . $prefix . "_" . $k;
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
                    <?php 
                    $j = 0; 
                    foreach ($grouped_batches as$group_title => $assets):$j++;
                        $groupCollapseId = "groupCollapse_" . $prefix . "_" . $k . "_" . $j;
                        $first_asset = $assets[0];$model_name = $first_asset['model_name'] ?: 'Standard Model';$full_config = $first_asset['full_config'] ?: 'Standard Hardware Config';$stock_detail_ids = array_column($assets, 'stock_detail_id');$category_sl = 1;
                    ?>
                    <div class="asset-group-card shadow-sm mb-2 border">
                        <button class="asset-group-button collapsed d-flex align-items-center justify-content-between flex-wrap gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $groupCollapseId ?>">
                            <div class="d-flex align-items-center flex-wrap gap-2 me-3">
                                <i class="bi <?= getAssetIcon($group_title,$first_asset['category'] ?? '') ?> fs-5 me-1 text-primary"></i>
                                <span class="fw-bold text-dark me-2"><?= htmlspecialchars($group_title) ?></span>
                                <span class="badge bg-white text-dark border fw-semibold me-2"><?= htmlspecialchars($model_name) ?></span>
                                <span class="text-secondary small"><i class="bi bi-cpu me-1"></i><?= htmlspecialchars($full_config) ?></span>
                            </div>
                            <div>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border fw-bold">Total: <?= count($assets) ?></span>
                            </div>
                        </button>
                        
                        <div id="<?= $groupCollapseId ?>" class="collapse" data-bs-parent="#<?= $groupAccordionId ?>">
                            <!-- BULK YEAR ASSIGNMENT & RESET BAR -->
                            <div class="p-3 bg-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <form class="ajax-year-form row g-2 align-items-center mb-0 position-relative">
                                    <input type="hidden" name="action_type" value="bulk_year">
                                    <?php foreach ($stock_detail_ids as$sd_id): ?>
                                        <input type="hidden" name="stock_detail_ids[]" value="<?= $sd_id ?>">
                                    <?php endforeach; ?>
                                    
                                    <div class="col-auto">
                                        <span class="fw-semibold text-secondary small"><i class="bi bi-calendar-event me-1"></i> Batch Year (YYYY):</span>
                                    </div>
                                    <div class="col-auto" style="width: 130px;">
                                        <input type="text" name="bulk_procurement_year" class="form-control form-control-sm font-monospace" placeholder="YYYY" maxlength="4" value="<?= htmlspecialchars($first_asset['procurement_year'] ?? '') ?>">
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-dark btn-sm px-3" style="font-size: 0.75rem;">Apply to All</button>
                                    </div>
                                </form>

                                <form class="ajax-year-form mb-0 position-relative">
                                    <input type="hidden" name="action_type" value="reset_batch">
                                    <?php foreach ($stock_detail_ids as$sd_id): ?>
                                        <input type="hidden" name="stock_detail_ids[]" value="<?= $sd_id ?>">
                                    <?php endforeach; ?>
                                    <button type="submit" class="btn btn-outline-danger btn-sm px-3" style="font-size: 0.75rem;">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Batch
                                    </button>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table align-middle table-erp-minimal mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">Sl. No</th>
                                            <th>Serial Number</th>
                                            <th>Asset Tag / ID</th>
                                            <th style="width: 250px;" class="text-end">Procurement Year (YYYY)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assets as$asset): 
                                            $has_year = !empty($asset['procurement_year']);
                                        ?>
                                        <tr>
                                            <td class="text-muted fw-semibold"><?= $category_sl++ ?></td>
                                            <td class="fw-semibold"><i class="bi bi-barcode me-1 text-muted"></i><?= htmlspecialchars($asset['serial_number'] ?: 'N/A') ?></td>
                                            <td><span class="fw-semibold text-primary"><?= htmlspecialchars($asset['division_asset_id']) ?></span></td>
                                            <td class="text-end">
                                                <form class="ajax-year-form d-inline-flex gap-1 justify-content-end align-items-center mb-0 position-relative">
                                                    <input type="hidden" name="action_type" value="individual_year">
                                                    <input type="hidden" name="stock_detail_id" value="<?= $asset['stock_detail_id'] ?>">
                                                    <input type="text" name="procurement_year" class="form-control form-control-sm text-center font-monospace individual-year-input" style="width: 100px;" maxlength="4" value="<?= htmlspecialchars($asset['procurement_year'] ?? '') ?>" placeholder="YYYY">
                                                    <button type="submit" class="btn <?= $has_year ? 'btn-success' : 'btn-outline-primary' ?> btn-sm px-2" title="Save Year">
                                                        <i class="bi <?= $has_year ? 'bi-check-lg text-white' : 'bi-check-lg' ?>"></i>
                                                    </button>
                                                </form>
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