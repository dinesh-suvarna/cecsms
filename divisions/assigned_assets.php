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
if (isset($_POST['asset_action'])) {
    $asset_id = (int)$_POST['asset_id'];
    $action   = $_POST['asset_action'];
    $status_map = ["return" => "return_requested", "repair" => "repair_requested", "dispose" => "dispose_requested"];
    $status = $status_map[$action] ?? 'assigned';

    $stmt = $conn->prepare("UPDATE division_assets SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $asset_id);
    $stmt->execute();

    $_SESSION['swal_type'] = "success";
    $_SESSION['swal_msg'] = "Request for " . $action . " submitted.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* ================= FETCH DATA ================= */
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
                                                    <i class="bi bi-tag-fill me-2" style="color: #10b981;"></i>
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
                                            <button class="btn btn-saas-manage" onclick="openManageModal(<?= $asset['id'] ?>, '<?= $asset['division_asset_id'] ?>', '<?= $asset['item_name'] ?>')">
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

<script>
    function openEditIdModal(id, currentTag) {
        document.getElementById('edit_db_id').value = id;
        document.getElementById('edit_asset_tag').value = currentTag;
        new bootstrap.Modal(document.getElementById('editIdModal')).show();
    }
    function openManageModal(id, assetId, itemName) {
        document.getElementById('hidden_asset_id').value = id;
        document.getElementById('disp_asset_id').innerText = assetId;
        document.getElementById('disp_item_name').innerText = itemName;
        new bootstrap.Modal(document.getElementById('manageModal')).show();
    }
</script>

<?php 
$content = ob_get_clean(); 

/* ================= MANAGE MODAL ================= */
$modal_html = '
<div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold mb-0" style="color: #059669;">Lifecycle Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light rounded-4 p-3 mb-4 border text-center">
                    <div class="fw-bold text-primary fs-5" id="disp_asset_id"></div>
                    <div class="small fw-medium text-dark mt-1" id="disp_item_name"></div>
                </div>
                <form method="POST">
                    <input type="hidden" name="asset_id" id="hidden_asset_id">
                    <div class="d-grid gap-3">
                        <button type="submit" name="asset_action" value="return" class="btn btn-outline-warning p-3 rounded-3 text-start text-dark fw-bold border-opacity-25 shadow-sm">
                             <div class="d-flex align-items-center"><i class="bi bi-arrow-left-right fs-4 me-3"></i><div><div>Return Asset</div><small class="fw-normal opacity-75">Send to store</small></div></div>
                        </button>
                        <button type="submit" name="asset_action" value="repair" class="btn btn-outline-info p-3 rounded-3 text-start text-dark fw-bold border-opacity-25 shadow-sm">
                             <div class="d-flex align-items-center"><i class="bi bi-tools fs-4 me-3"></i><div><div>Request Repair</div><small class="fw-normal opacity-75">Fix hardware issue</small></div></div>
                        </button>
                        <button type="submit" name="asset_action" value="dispose" class="btn btn-outline-danger p-3 rounded-3 text-start text-dark fw-bold border-opacity-25 shadow-sm" onclick="return confirm(\'Request disposal?\')">
                             <div class="d-flex align-items-center"><i class="bi bi-trash3 fs-4 me-3"></i><div><div>Dispose Asset</div><small class="fw-normal opacity-75">End of life</small></div></div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editIdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0"><h6 class="fw-bold mb-0">Update Asset ID</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="db_id" id="edit_db_id">
                    <input type="text" name="new_asset_tag" id="edit_asset_tag" class="form-control fw-bold border-2" required>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" name="update_asset_id" class="btn btn-primary w-100 rounded-3 fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>';

include "../divisions/divisionslayout.php"; 
?>