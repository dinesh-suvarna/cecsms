<?php
include "../config/db.php";
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
/* ================= HANDLE ASSET ACTION ================= */
if (isset($_POST['asset_action'])) {
    $db_id    = (int)$_POST['asset_id']; // This is the ID from division_assets
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
    // This is required to satisfy the Foreign Key constraint in asset_logs
    $stmt_fetch = $conn->prepare("SELECT stock_detail_id FROM division_assets WHERE id = ?");
    $stmt_fetch->bind_param("i", $db_id);
    $stmt_fetch->execute();
    $res_fetch = $stmt_get_result = $stmt_fetch->get_result();
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
        // Use $permanent_stock_id instead of $db_id
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
function getAssetIcon($itemName) {
    $name = strtolower($itemName);
    
    switch (true) {
        case (strpos($name, 'computer') !== false || strpos($name, 'desktop') !== false):
            return 'bi-pc-display';
        case (strpos($name, 'laptop') !== false):
            return 'bi-laptop';
        case (strpos($name, 'monitor') !== false):
            return 'bi-display';
        case (strpos($name, 'printer') !== false):
            return 'bi-printer';
        case (strpos($name, 'keyboard') !== false):
            return 'bi-keyboard';
        case (strpos($name, 'mouse') !== false):
            return 'bi-mouse3';
        case (strpos($name, 'ups') !== false || strpos($name, 'battery') !== false):
            return 'bi-lightning-charge';
        case (strpos($name, 'table') !== false || strpos($name, 'desk') !== false):
            return 'bi-table';
        case (strpos($name, 'chair') !== false):
            return 'bi-person-workspace';
        case (strpos($name, 'camera') !== false || strpos($name, 'cctv') !== false):
            return 'bi-camera-video';
        default:
            return 'bi-box-seam'; // Fallback icon
    }
}

/* ================= FETCH DATA ================= */
$query = "
    SELECT 
        da.id, da.division_asset_id, im.item_name, sd.serial_number, 
        u.unit_name,
        u.unit_code, -- Fetching the code here
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
$query .= " ORDER BY u.unit_name ASC, im.item_name ASC, da.assigned_at DESC";

$result = $conn->query($query);
$units = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Create the combined label
        $display_label = ($row['unit_code'] ? $row['unit_code'] . " - " : "") . ($row['unit_name'] ?? 'General/Unassigned');
        // Group by this new label
        $units[$display_label][$row['item_name']][] = $row;
    }
}


ob_start();
?>

<style>
    /* SaaS Style Accordion */
    .unit-accordion .accordion-item {
        border: 1px solid #eef2f6;
        margin-bottom: 1rem;
        border-radius: 12px !important;
        background: #fff;
    }
    .unit-accordion .accordion-button:not(.collapsed) {
    background-color: #f0fdf4; /* Very light emerald tint */
    border-left: 4px solid #10b981;
    color: #059669; /* Slightly darker emerald for text readability */
    box-shadow: none;
}

    /* --- REFINED PREMIUM TEXT HIERARCHY --- */
    .item-info-title { 
        font-weight: 700; 
        color: #0f172a; /* Slate 900 */
        font-size: 0.875rem; 
        letter-spacing: -0.01em;
    }

    .model-detail-text { 
        font-weight: 600; 
        color: #334155; /* Slate 800 - slightly darker for better readability */
        font-size: 0.825rem; 
        display: block; 
        margin-bottom: 3px;
    }

    .sn-label { 
        font-weight: 600; /* Increased to 600 for better stroke definition */
        color: #64748b; /* Slate 500 - Deep enough for white BG, but still "muted" */
        font-size: 0.7rem; 
        font-family: 'Inter', sans-serif; 
        text-transform: uppercase; 
        letter-spacing: 0.05em;
        
        /* The "SaaS Touch": A tiny bit of padding and a soft left border */
        display: inline-flex;
        align-items: center;
        border-left: 2px solid #e2e8f0; /* Very light separator */
        padding-left: 8px;
        margin-top: 2px;
    }
    
    /* Synchronized Typography for Config and Asset ID */
    .saas-info-text {
        font-size: 0.82rem;
        font-weight: 600;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Asset ID specific alignment */
    .asset-id-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* --- ITEM TYPE SEPARATOR --- */
    .item-group-separator {
        background-color: #f0fdf4 !important; /* Very soft Emerald tint */
        padding: 12px 24px !important;
        border-top: 1px solid #dcfce7 !important;
        border-bottom: 1px solid #dcfce7 !important;
    }

    .category-label {
        color: #059669; /* Darker green for text */
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: flex;
        align-items: center;
    }


    /* Make the numbering column look distinct but subtle */
    td:first-child {
        border-right: 1px solid #f8fafc;
    }

    /* Subtle row hover for premium feel */
    tbody tr:hover {
        background-color: #fafbfc;
    }

    .btn-edit-link {
        padding: 0;
        font-size: 0.85rem;
        color: #94a3b8;
        border: none;
        background: none;
        transition: color 0.2s;
    }
    .btn-edit-link:hover { color: #2563eb; }

    .btn-saas-manage {
        font-weight: 700;
        font-size: 0.72rem;
        padding: 0.5rem 1.2rem;
        border-radius: 8px;
        border: 1.5px solid #10b981;
        color: #10b981;
        background: transparent;
        transition: all 0.2s ease;
    }

    .btn-saas-manage:hover {
        background-color: #10b981;
        color: white;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }

    /* Color tweak for the Tag Icon in the separator */
    .item-group-separator i {
        color: #10b981;
    }
</style>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="fw-bold mb-1">Unit Asset Registry</h4>
        <p class="text-muted small">Real-time inventory for assigned laboratory units.</p>
    </div>

    <div class="accordion unit-accordion" id="unitAccordion">
    <?php $i = 0; foreach ($units as $display_label => $assets): $i++; $collapseId = "unitCollapse" . $i; ?>
    <div class="accordion-item shadow-sm">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                <div class="d-flex align-items-center w-100">
                    <div class="bg-light p-2 rounded-3 me-3"><i class="bi bi-layers text-success"></i></div>
                    <div>
                        <div class="text-dark fw-bold mb-0"><?= htmlspecialchars($display_label) ?></div>
                        
                    </div>
                </div>
            </button>
            </h2>
            <div id="<?= $collapseId ?>" class="accordion-collapse collapse" data-bs-parent="#unitAccordion">
                <div class="accordion-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="bg-light">
                                    <th class="ps-4 py-3 text-muted small text-uppercase" style="width:70px;">Sl.No</th>
                                    <th class="py-3 text-muted small text-uppercase">Asset Info</th>
                                    <th class="py-3 text-muted small text-uppercase">Model Detail</th>
                                    <th class="py-3 text-muted small text-uppercase">Hardware Config</th>
                                    <th class="py-3 text-muted small text-uppercase">Asset ID</th>
                                    <th class="text-end pe-4 py-3 text-muted small text-uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Loop through Item Groups (e.g., Keyboard, Computer)
                                foreach ($assets as $item_type => $grouped_items): 
                                    // RESET COUNTER FOR EVERY NEW CATEGORY
                                    $category_sl = 1; 
                                ?>
                                    <tr>
                                        <td colspan="6" class="item-group-separator">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="category-label">
                                                    <i class="bi <?= getAssetIcon($item_type) ?> me-2"></i>
                                                    <?= htmlspecialchars($item_type) ?>
                                                </div>
                                                
                                                <span class="badge rounded-pill border-0 fw-bold" 
                                                    style="font-size: 0.65rem; background-color: #10b981; color: white; padding: 6px 12px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.1);">
                                                    <?= count($grouped_items) ?> Total
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    <?php foreach ($grouped_items as $asset): ?>
                                    <tr>
                                        <td class="ps-4 text-muted fw-medium" style="font-size: 0.8rem;">
                                            <?= $category_sl++ ?>
                                        </td>
                                        
                                        <td><span class="item-info-title"><?= htmlspecialchars($asset['item_name']) ?></span></td>
                                        
                                        <td>
                                            <span class="model-detail-text"><?= htmlspecialchars($asset['model_name'] ?: 'Standard') ?></span>
                                            <div class="sn-label">
                                                <span style="color: #94a3b8; font-weight: 500; margin-right: 5px;">S/N</span>
                                                <span style="color: #475569;"><?= htmlspecialchars($asset['serial_number'] ?: '---') ?></span>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="saas-info-text">
                                                <?= htmlspecialchars($asset['full_config'] ?: 'Base Config') ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="asset-id-wrapper">
                                                <span class="saas-info-text"><?= htmlspecialchars($asset['division_asset_id']) ?></span>
                                                <button class="btn-edit-link" onclick="openEditIdModal(<?= $asset['id'] ?>, '<?= $asset['division_asset_id'] ?>')">
                                                    <i class="bi bi-pencil-square" style="color: #10b981; opacity: 0.6;"></i>
                                                </button>
                                            </div>
                                        </td>

                                        <td class="text-end pe-4">
                                            <button class="btn btn-saas-manage" 
                                                onclick="openManageModal(
                                                    <?= $asset['id'] ?>, 
                                                    '<?= addslashes($asset['division_asset_id']) ?>', 
                                                    '<?= addslashes($asset['item_name']) ?>', 
                                                    '<?= addslashes($asset['model_name'] ?: 'Standard') ?>', 
                                                    '<?= addslashes($asset['serial_number'] ?: 'N/A') ?>',
                                                    '<?= getAssetIcon($asset['item_name']) ?>' /* ADD THIS LINE */
                                                )">
                                                Manage
                                            </button>
                                                
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Asset ID Edit Modal Logic
    function openEditIdModal(id, tag) {
        document.getElementById('edit_db_id').value = id;
        document.getElementById('edit_asset_tag').value = tag;
        new bootstrap.Modal(document.getElementById('editIdModal')).show();
    }

    // Lifecycle Action Modal Logic - Now includes Model and Serial
    function openManageModal(id, assetId, name, model, serial, iconClass) {
    document.getElementById('hidden_asset_id').value = id;
    document.getElementById('disp_asset_id').innerText = assetId;
    document.getElementById('disp_item_name').innerText = name;
    document.getElementById('disp_model_name').innerText = model;
    document.getElementById('disp_serial_number').innerText = serial;
    document.getElementById('action_remarks').value = '';
    
    // Update the Icon Class dynamically
    const iconElement = document.getElementById('disp_item_icon');
    iconElement.className = 'bi fs-4 text-success ' + iconClass;
    
    new bootstrap.Modal(document.getElementById('manageModal')).show();
}

    // Function to capture remarks and then trigger SweetAlert
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
        text: `Asset: ${assetTag}. Proceed with this request?`,
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
                'remarks': remarks // Passing the textarea content
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
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold mb-0 text-success">Lifecycle Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light rounded-4 p-3 mb-3 border shadow-sm">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-white p-2 rounded-3 shadow-sm me-3 border">
                            <i id="disp_item_icon" class="bi fs-4 text-success"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" id="disp_item_name"></h6>
                            <small class="text-muted" id="disp_model_name"></small>
                        </div>
                    </div>
                    <div class="row g-0 pt-2 border-top">
                        <div class="col-6">
                            <small class="text-uppercase fw-bold text-muted d-block" style="font-size: 0.6rem;">Serial Number</small>
                            <div class="fw-semibold small text-dark" id="disp_serial_number"></div>
                        </div>
                        <div class="col-6 border-start ps-3">
                            <small class="text-uppercase fw-bold text-muted d-block" style="font-size: 0.6rem;">System Asset ID</small>
                            <div class="fw-bold text-primary small" id="disp_asset_id"></div>
                        </div>
                    </div>
                </div>

                <div class="form-floating mb-4">
                    <textarea class="form-control border-2" placeholder="Leave a remark here" id="action_remarks" style="height: 100px; resize: none;"></textarea>
                    <label for="action_remarks" class="text-muted small fw-bold">ACTION REMARKS / REASON</label>
                </div>

                <input type="hidden" id="hidden_asset_id">
                
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-warning p-3 rounded-3 text-start text-dark fw-bold shadow-sm border-2" 
                            onclick="prepareAction(\'return\')">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-arrow-left-right fs-4 me-3"></i>
                            <div>Return Asset<br><small class="fw-normal opacity-75">Release back to main store</small></div>
                        </div>
                    </button>
                    
                    <button type="button" class="btn btn-outline-info p-3 rounded-3 text-start text-dark fw-bold shadow-sm border-2" 
                            onclick="prepareAction(\'repair\')">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-tools fs-4 me-3"></i>
                            <div>Request Repair<br><small class="fw-normal opacity-75">Submit for technical service</small></div>
                        </div>
                    </button>
                    
                    <button type="button" class="btn btn-outline-danger p-3 rounded-3 text-start text-dark fw-bold shadow-sm border-2" 
                            onclick="prepareAction(\'dispose\')">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-trash3 fs-4 me-3"></i>
                            <div>Decommission Asset<br><small class="fw-normal opacity-75">Mark for disposal/scrapping</small></div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editIdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0"><h6 class="fw-bold mb-0">Update Asset ID</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body"><input type="hidden" name="db_id" id="edit_db_id"><input type="text" name="new_asset_tag" id="edit_asset_tag" class="form-control fw-bold border-2" required></div>
                <div class="modal-footer border-0 pt-0"><button type="submit" name="update_asset_id" class="btn btn-primary w-100 rounded-3 fw-bold">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>';

include "../divisions/divisionslayout.php"; 
?>