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

/* ================= HANDLE ASSET ACTION (Return/Repair/Dispose) ================= */
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
$query = "
    SELECT 
        da.id, da.division_asset_id, im.item_name, sd.serial_number, 
        IFNULL(u.unit_name, 'General/Unassigned') as unit_name,
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
$query .= " ORDER BY u.unit_name ASC, da.assigned_at DESC";

$result = $conn->query($query);
$units = [];
while ($row = $result->fetch_assoc()) { $units[$row['unit_name']][] = $row; }

ob_start();
?>

<style>
    :root { --saas-blue: #2563eb; --saas-emerald: #10b981; --saas-slate: #64748b; }

    /* SaaS Style Accordion */
    .unit-accordion .accordion-item {
        border: 1px solid #eef2f6;
        margin-bottom: 1rem;
        border-radius: 12px !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        background: #fff;
    }
    .unit-accordion .accordion-button {
        padding: 1.25rem;
        font-weight: 600;
        border-left: 4px solid transparent;
    }
    .unit-accordion .accordion-button:not(.collapsed) {
        background-color: #f8fafc;
        border-left: 4px solid var(--saas-emerald);
        color: var(--saas-emerald);
        box-shadow: none;
    }

    /* Table & Typography */
    .table thead th { 
        background: #f8fafc; 
        text-transform: uppercase; 
        font-size: 0.75rem; 
        letter-spacing: 0.05em; 
        color: var(--saas-slate);
        padding: 1rem;
    }
    .item-info-title { font-weight: 700; color: #1e293b; font-size: 0.9rem; }
    .model-detail-text { font-weight: 600; color: #334155; font-size: 0.85rem; display: block; }
    .sn-label { color: var(--saas-slate); font-size: 0.75rem; font-family: 'JetBrains Mono', monospace; }
    
    .config-tag {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        background: #f1f5f9;
        border-radius: 20px;
        font-size: 0.75rem;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .asset-id-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #eff6ff;
        color: var(--saas-blue);
        padding: 4px 12px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.8rem;
        border: 1px solid #dbeafe;
    }

    .btn-edit-inline {
        padding: 2px 6px;
        font-size: 0.7rem;
        border-radius: 4px;
        color: var(--saas-slate);
        transition: all 0.2s;
    }
    .btn-edit-inline:hover { color: var(--saas-blue); background: #f1f5f9; }

    .btn-saas-manage {
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.5rem 1.2rem;
        border-radius: 8px;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Unit Asset Registry</h4>
            <p class="text-muted small">Real-time inventory for assigned laboratory units.</p>
        </div>
    </div>

    <div class="accordion unit-accordion" id="unitAccordion">
        <?php $i = 0; foreach ($units as $unit_name => $assets): $i++; $collapseId = "unitCollapse" . $i; ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $i > 1 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                    <div class="d-flex align-items-center w-100">
                        <div class="bg-light p-2 rounded-3 me-3"><i class="bi bi-layers text-success"></i></div>
                        <div>
                            <div class="text-dark fw-bold mb-0"><?= htmlspecialchars($unit_name) ?></div>
                            <div class="text-muted extra-small fw-normal"><?= count($assets) ?> Assets active</div>
                        </div>
                    </div>
                </button>
            </h2>
            <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $i == 1 ? 'show' : '' ?>" data-bs-parent="#unitAccordion">
                <div class="accordion-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Asset Info</th>
                                    <th>Model Detail</th>
                                    <th>Hardware Config</th>
                                    <th>Asset ID</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $sl = 1; foreach ($assets as $asset): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= $sl++ ?></td>
                                    <td><span class="item-info-title"><?= htmlspecialchars($asset['item_name']) ?></span></td>
                                    <td>
                                        <span class="model-detail-text"><?= htmlspecialchars($asset['model_name'] ?: 'Standard Model') ?></span>
                                        <span class="sn-label">S/N: <?= htmlspecialchars($asset['serial_number'] ?: 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <div class="config-tag">
                                            <i class="bi bi-cpu me-2"></i><?= htmlspecialchars($asset['full_config'] ?: 'Base Config') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="asset-id-pill">
                                            <span><?= htmlspecialchars($asset['division_asset_id']) ?></span>
                                            <button class="btn btn-edit-inline" title="Edit ID" onclick="openEditIdModal(<?= $asset['id'] ?>, '<?= $asset['division_asset_id'] ?>')">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-saas-manage btn-outline-dark" onclick="openManageModal(<?= $asset['id'] ?>, '<?= $asset['division_asset_id'] ?>', '<?= $asset['item_name'] ?>')">
                                            Manage
                                        </button>
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
    </div>
</div>

<div class="modal fade" id="editIdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0"><h6 class="fw-bold mb-0">Edit Asset ID</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="db_id" id="edit_db_id">
                    <label class="small text-muted mb-2">New Asset Tag / ID</label>
                    <input type="text" name="new_asset_tag" id="edit_asset_tag" class="form-control fw-bold border-2" required>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" name="update_asset_id" class="btn btn-primary w-100 rounded-3 fw-bold">Update ID</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0"><h5 class="fw-bold mb-0">Lifecycle Action</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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
                        <button type="submit" name="asset_action" value="dispose" class="btn btn-outline-danger p-3 rounded-3 text-start text-dark fw-bold border-opacity-25 shadow-sm" onclick="return confirm('Request disposal?')">
                             <div class="d-flex align-items-center"><i class="bi bi-trash3 fs-4 me-3"></i><div><div>Dispose Asset</div><small class="fw-normal opacity-75">End of life</small></div></div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
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

<?php $content = ob_get_clean(); include "../divisions/divisionslayout.php"; ?>