<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Unit Asset Registry";
$page_icon  = "bi-hdd-stack";

$role = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;

/* ================= HANDLE ASSET ID UPDATE ================= */
if (isset($_POST['update_asset_id'])) {
    $db_id = (int)$_POST['db_id'];
    $new_asset_tag = trim($_POST['new_asset_tag']);

    if (!empty($new_asset_tag)) {
        $stmt = $conn->prepare("UPDATE division_assets SET division_asset_id = ? WHERE id = ?");
        $stmt->bind_param("si", $new_asset_tag, $db_id);
        $stmt->execute();
        $_SESSION['swal_type'] = "success";
        $_SESSION['swal_msg'] = "Asset ID updated successfully.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* ================= HANDLE ASSET ACTION ================= */
if (isset($_POST['asset_action'])) {
    $db_id    = (int)$_POST['asset_id']; //ID from division_assets
    $action   = $_POST['asset_action'];
    $user_id  = $_SESSION['user_id'] ?? null;
    $user_remarks = trim($_POST['remarks'] ?? '');

    $status_map = [
        "return"  => "return_requested", 
        "repair"  => "repair_requested", 
        "dispose" => "dispose_requested"
    ];
    $status = $status_map[$action] ?? 'assigned';

    // 1. FETCH THE PERMANENT STOCK_DETAIL_ID FIRST
    $stmt_fetch = $conn->prepare("SELECT stock_detail_id FROM division_assets WHERE id = ?");
    $stmt_fetch->bind_param("i", $db_id);
    $stmt_fetch->execute();
    $res_fetch = $stmt_fetch->get_result();
    $asset_data = $res_fetch->fetch_assoc();

    if ($asset_data) {
        $permanent_stock_id = $asset_data['stock_detail_id'];

        // 2. UPDATE THE ASSET STATUS IN DIVISION_ASSETS
        $stmt = $conn->prepare("UPDATE division_assets SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $db_id);
        $stmt->execute();

        // 3. INSERT INTO ASSET_LOGS USING THE PERMANENT ID
        $log_notes = !empty($user_remarks) ? $user_remarks : "Lifecycle request: " . str_replace('_', ' ', $status);
        
        $log_stmt = $conn->prepare("INSERT INTO asset_logs (asset_id, action_type, performed_by, notes) VALUES (?, ?, ?, ?)");
        $log_stmt->bind_param("isis", $permanent_stock_id, $status, $user_id, $log_notes);
        $log_stmt->execute();

        $_SESSION['swal_type'] = "success";
        $_SESSION['swal_msg'] = "Request submitted successfully.";
    } else {
        $_SESSION['swal_type'] = "error";
        $_SESSION['swal_msg'] = "Asset not found in registry.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* ================= HELPERS ================= */
function getAssetIcon($itemName, $category = '') {
    $name = strtolower($itemName);
    $cat  = strtolower($category);
    
    switch (true) {
        // Computer / Desktop
        case (strpos($name, 'computer') !== false || strpos($name, 'desktop') !== false || $cat === 'computer'):
            return 'bi-pc-display';
        case (strpos($name, 'laptop') !== false):
            return 'bi-laptop';
        case (strpos($name, 'monitor') !== false):
            return 'bi-display';

        // Networking & Infrastructure
        case (strpos($name, 'rack') !== false || strpos($name, 'server') !== false):
            return 'bi-hdd-rack'; 
        case (strpos($name, 'switch') !== false || strpos($name, 'hub') !== false):
            return 'bi-hdd-stack'; 
        case (strpos($name, 'router') !== false):
            return 'bi-router';
        case ($cat === 'networking'):
            return 'bi-diagram-3'; // Fallback for general networking category

        // Peripherals & Accessories
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
        u.unit_name,
        u.unit_code,
        mo.model_name,
        CONCAT_WS(' | ', mo.processor, mo.ram, CONCAT(mo.storage_size, ' ', mo.storage_type)) as full_config 
    FROM division_assets da
    JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    JOIN stock_details sd ON sd.id = da.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    LEFT JOIN units u ON u.id = dm.unit_id
    LEFT JOIN item_models mo ON sd.model_id = mo.id
    WHERE da.status = 'assigned'
";

if ($role !== 'SuperAdmin') { $query .= " AND dm.division_id = $division_id"; }
$query .= " ORDER BY u.unit_code ASC, im.item_name ASC, da.division_asset_id ASC";

$result = $conn->query($query);
$units = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $display_label = ($row['unit_code'] ? $row['unit_code'] . " - " : "") . ($row['unit_name'] ?? 'General/Unassigned');
        $units[$display_label][$row['item_name']][] = $row;
    }
}

ob_start();
?>

<style>
/* Enterprise Division Accordion */
.unit-accordion .accordion-item {
    border: 1px solid var(--erp-border, #cbd5e1) !important;
    margin-bottom: 0.75rem;
    border-radius: 6px !important;
    background: #ffffff;
    overflow: hidden;
}

.unit-accordion .accordion-button {
    background-color: #f8fafc;
    color: var(--erp-navy-dark, #102f4a);
    font-weight: 700;
    font-size: 0.95rem;
    padding: 0.85rem 1.25rem;
}

.unit-accordion .accordion-button:not(.collapsed) {
    background-color: #89d9df;
    color: var(--erp-navy, #173f63);
    border-left: 4px solid var(--erp-navy, #173f63);
    box-shadow: none;
}

/* Nested Asset Group Accordion */
.asset-group-card {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background-color: #ffffff;
    margin-bottom: 0.75rem;
    overflow: hidden;
}

.asset-group-button {
    width: 100%;
    background-color: #f0f4f8; /* Light blue-gray tint */
    border: none;
    border-bottom: 1px solid #cbd5e1;
    padding: 0.75rem 1rem;
    text-align: left;
    transition: background-color 0.15s ease-in-out;
}

.asset-group-button:hover {
    background-color: #e2e8f0;
}

.asset-group-button:focus {
    outline: none;
    box-shadow: none;
}

.asset-group-button.collapsed {
    border-bottom: none;
}

.asset-group-button::after {
    flex-shrink: 0;
    width: 1.25rem;
    height: 1.25rem;
    margin-left: auto;
    content: "";
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23173f63'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-size: 1.25rem;
    transition: transform 0.2s ease-in-out;
}

.asset-group-button:not(.collapsed)::after {
    transform: rotate(-180deg);
}

/* Minimal Table & Normal Typography */
.table-erp-minimal {
    margin-bottom: 0;
}

.table-erp-minimal th {
    background-color: #ffffff;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.6rem 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.table-erp-minimal td {
    padding: 0.65rem 1rem;
    font-size: 0.85rem;
    color: #1e293b;
    border-bottom: 1px solid #f1f5f9;
    font-style: normal !important;
}

.table-erp-minimal tbody tr:last-child td {
    border-bottom: none;
}

.table-erp-minimal tbody tr:hover {
    background-color: #f1f5f9 !important; 
}
.hover-row:hover td {
    background-color: #e2e8f0 !important; /* Soft light blue/gray hover background */
}

.btn-erp-outline {
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    border: 1px solid var(--erp-navy, #173f63);
    color: var(--erp-navy, #173f63);
    background: transparent;
    transition: all 0.15s ease-in-out;
}

.btn-erp-outline:hover {
    background-color: var(--erp-navy, #173f63);
    color: #ffffff;
}

.btn-edit-link {
    padding: 0;
    font-size: 0.85rem;
    color: #94a3b8;
    border: none;
    background: none;
    transition: color 0.15s;
}
.btn-edit-link:hover { color: var(--erp-navy, #173f63); }
</style>

<div class="container-fluid p-0 pb-4">
    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
        <div>
            <h4 class="fw-bold mb-1 text-dark" style="font-size: 1.25rem;">
                <i class="bi bi-hdd-stack me-2" style="color: var(--erp-navy, #173f63);"></i>Unit Asset Registry
            </h4>
            <p class="text-muted mb-0 small">Real-time inventory for assigned laboratory units and hardware systems.</p>
        </div>
    </div>

    <!-- Outer Accordion: Divisions / Units -->
    <div class="accordion unit-accordion" id="unitAccordion">
        <?php $i = 0; foreach ($units as $display_label => $assets): $i++; $collapseId = "unitCollapse" . $i; ?>
        <div class="accordion-item shadow-sm">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                    <div class="d-flex align-items-center justify-content-between w-100 me-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-building me-2 text-secondary"></i>
                            <span><?= htmlspecialchars($display_label) ?></span>
                        </div>
                    </div>
                </button>
            </h2>
            
            <div id="<?= $collapseId ?>" class="accordion-collapse collapse" data-bs-parent="#unitAccordion">
                <div class="accordion-body p-3 bg-light">
                    
                    <!-- Inner Nested Accordion Container -->
                    <div class="accordion" id="groupAccordion<?= $i ?>">
                        <?php $j = 0; foreach ($assets as $item_type => $grouped_items): $j++;
                            $groupCollapseId = "groupCollapse_" . $i . "_" . $j;
                            $first_asset = $grouped_items[0];
                            $model_name = $first_asset['model_name'] ?: 'Standard Model';
                            $full_config = $first_asset['full_config'] ?: 'Standard Hardware Config';
                            $category_sl = 1;
                        ?>
                            <!-- Collapsible Group Card -->
                            <div class="asset-group-card shadow-sm">
                                <button class="asset-group-button collapsed d-flex align-items-center justify-content-between flex-wrap gap-2" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#<?= $groupCollapseId ?>">
                                    <div class="d-flex align-items-center flex-wrap gap-2 me-3">
                                        <i class="bi <?= getAssetIcon($item_type, $first_asset['category'] ?? '') ?> fs-5 me-1" style="color: var(--erp-navy, #173f63);"></i>
                                        <span class="fw-bold text-dark me-2" style="font-size: 0.95rem;"><?= htmlspecialchars($item_type) ?></span>
                                        <span class="badge bg-white text-dark border fw-semibold me-2" style="font-size: 0.75rem;"><?= htmlspecialchars($model_name) ?></span>
                                        <span class="text-secondary small fw-normal">
                                            <i class="bi bi-cpu me-1"></i><?= htmlspecialchars($full_config) ?>
                                        </span>
                                    </div>
                                    <div class="me-3">
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis border fw-bold" style="font-size: 0.7rem;">
                                            Total: <?= count($grouped_items) ?>
                                        </span>
                                    </div>
                                </button>

                                <!-- Collapsible Table Container -->
                                <div id="<?= $groupCollapseId ?>" class="collapse" data-bs-parent="#groupAccordion<?= $i ?>">
                                    <div class="table-responsive">
                                        <table class="table align-middle table-erp-minimal">
                                            <thead>
                                                <tr>
                                                    <th style="width: 70px;">Sl. No</th>
                                                    <th>Serial Number</th>
                                                    <th>Asset Tag / ID</th>
                                                    <th class="text-end">Lifecycle Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($grouped_items as $asset): ?>
                                                <tr class="hover-row">
                                                    <td class="text-muted fw-semibold"><?= $category_sl++ ?></td>
                                                    <td class="text fw-semibold">
                                                        <i class="bi bi-barcode me-1 text-muted"></i><?= htmlspecialchars($asset['serial_number'] ?: 'N/A') ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-inline-flex align-items-center gap-2">
                                                            <span class="text fw-semibold"><?= htmlspecialchars($asset['division_asset_id']) ?></span>
                                                            <button class="btn-edit-link" onclick="openEditIdModal(<?= $asset['id'] ?>, '<?= $asset['division_asset_id'] ?>')" title="Edit Tag">
                                                                <i class="bi bi-pencil-square text-danger"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="text-end">
                                                        <button class="btn btn-erp-outline" 
                                                            onclick="openManageModal(
                                                                <?= $asset['id'] ?>, 
                                                                '<?= addslashes($asset['division_asset_id']) ?>', 
                                                                '<?= addslashes($asset['item_name']) ?>', 
                                                                '<?= addslashes($asset['model_name'] ?: 'Standard') ?>', 
                                                                '<?= addslashes($asset['serial_number'] ?: 'N/A') ?>',
                                                                '<?= getAssetIcon($asset['item_name'], $asset['category'] ?? '') ?>'
                                                            )">
                                                            <i class="bi bi-sliders me-1"></i> Manage
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
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openEditIdModal(id, tag) {
        document.getElementById('edit_db_id').value = id;
        document.getElementById('edit_asset_tag').value = tag;
        new bootstrap.Modal(document.getElementById('editIdModal')).show();
    }

    function openManageModal(id, assetId, name, model, serial, iconClass) {
        document.getElementById('hidden_asset_id').value = id;
        document.getElementById('disp_asset_id').innerText = assetId;
        document.getElementById('disp_item_name').innerText = name;
        document.getElementById('disp_model_name').innerText = model;
        document.getElementById('disp_serial_number').innerText = serial;
        document.getElementById('action_remarks').value = '';
        
        const iconElement = document.getElementById('disp_item_icon');
        iconElement.className = 'bi fs-4 text-primary ' + iconClass;
        
        new bootstrap.Modal(document.getElementById('manageModal')).show();
    }

    function prepareAction(type) {
        const assetId = document.getElementById('hidden_asset_id').value;
        const assetTag = document.getElementById('disp_asset_id').innerText;
        const remarks = document.getElementById('action_remarks').value;

        handleAssetAction(type, assetId, assetTag, remarks);
    }

    function handleAssetAction(actionType, assetId, assetTag, remarks) {
        const manageModalEl = document.getElementById('manageModal');
        const manageModal = bootstrap.Modal.getInstance(manageModalEl);
        if (manageModal) { manageModal.hide(); }

        const config = {
            return:  { title: 'Return Asset?', color: '#f59e0b' },
            repair:  { title: 'Request Repair?', color: '#0dcaf0' },
            dispose: { title: 'Dispose Asset?', color: '#ef4444' }
        };
        
        const selected = config[actionType];

        Swal.fire({
            title: selected.title,
            text: `Asset Tag: ${assetTag}. Proceed with this request?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: selected.color,
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Submit'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                
                const form = document.createElement('form');
                form.method = 'POST';
                
                const fields = {
                    'asset_id': assetId,
                    'asset_action': actionType,
                    'remarks': remarks
                };

                for (const [key, value] of Object.entries(fields)) {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = key;
                    inp.value = value;
                    form.appendChild(inp);
                }

                document.body.appendChild(form); 
                form.submit();
            } else {
                if (manageModal) { manageModal.show(); }
            }
        });
    }
</script>

<?php 
$content = ob_get_clean(); 

/* ================= MODALS ================= */

$modal_html = '
<div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom p-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-gear-wide-connected me-2"></i>Asset Lifecycle Action</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light rounded-3 p-3 mb-3 border shadow-sm">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-white p-2 rounded-2 shadow-sm me-3 border">
                            <i id="disp_item_icon" class="bi fs-4 text-primary"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" id="disp_item_name"></h6>
                            <small class="text-muted" id="disp_model_name"></small>
                        </div>
                    </div>
                    <div class="row g-0 pt-2 border-top">
                        <div class="col-6">
                            <small class="text-uppercase fw-bold text-muted d-block" style="font-size: 0.65rem;">Serial Number</small>
                            <div class="fw-semibold small text-dark" id="disp_serial_number"></div>
                        </div>
                        <div class="col-6 border-start ps-3">
                            <small class="text-uppercase fw-bold text-muted d-block" style="font-size: 0.65rem;">System Asset Tag</small>
                            <div class="fw-bold text-primary small" id="disp_asset_id"></div>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="action_remarks" class="form-label small fw-bold text-secondary">Action Remarks / Justification</label>
                    <textarea class="form-control" placeholder="Provide reason or context for this action..." id="action_remarks" style="height: 80px; resize: none; font-size: 0.88rem;"></textarea>
                </div>

                <input type="hidden" id="hidden_asset_id">
                
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-warning p-2.5 rounded-2 text-start text-dark fw-bold shadow-sm" 
                            onclick="prepareAction(\'return\')">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-arrow-left-right fs-4 me-3"></i>
                            <div>Return Asset<br><small class="fw-normal opacity-75">Release back to central inventory</small></div>
                        </div>
                    </button>
                    
                    <button type="button" class="btn btn-outline-info p-2.5 rounded-2 text-start text-dark fw-bold shadow-sm" 
                            onclick="prepareAction(\'repair\')">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-tools fs-4 me-3"></i>
                            <div>Request Repair<br><small class="fw-normal opacity-75">Submit ticket for technical maintenance</small></div>
                        </div>
                    </button>
                    
                    <button type="button" class="btn btn-outline-danger p-2.5 rounded-2 text-start text-dark fw-bold shadow-sm" 
                            onclick="prepareAction(\'dispose\')">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-trash3 fs-4 me-3"></i>
                            <div>Decommission Asset<br><small class="fw-normal opacity-75">Flag for disposal or scrapping</small></div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editIdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom p-3"><h6 class="fw-bold mb-0">Update Asset Tag</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body p-3">
                    <input type="hidden" name="db_id" id="edit_db_id">
                    <label class="form-label small fw-semibold text-secondary">Asset Tag / ID</label>
                    <input type="text" name="new_asset_tag" id="edit_asset_tag" class="form-control fw-bold" required>
                </div>
                <div class="modal-footer border-0 p-3 pt-0">
                    <button type="submit" name="update_asset_id" class="btn btn-primary w-100 fw-bold" style="background-color: var(--erp-navy, #173f63); border-color: var(--erp-navy, #173f63);">Save Tag</button>
                </div>
            </form>
        </div>
    </div>
</div>';

include "../divisions/divisionslayout.php"; 
?>