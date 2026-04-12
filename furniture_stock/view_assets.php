<?php
include "../config/db.php";
session_start();

// Security Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

// Corrected Query with institution_name
$assets_query = "
    SELECT 
        fa.id as asset_db_id, fa.asset_tag, fa.status, fa.last_verified_date,
        s.bill_no, s.bill_date, i.item_name, v.vendor_name,
        u.unit_name, u.unit_code, d.division_name, inst.institution_name
    FROM furniture_assets fa
    JOIN furniture_stock s ON fa.stock_id = s.id
    JOIN furniture_items i ON s.furniture_item_id = i.id
    JOIN vendors v ON s.vendor_id = v.id
    JOIN units u ON s.unit_id = u.id
    JOIN divisions d ON u.division_id = d.id
    JOIN institutions inst ON d.institution_id = inst.id";

if ($user_role !== 'SuperAdmin') {
    $assets_query .= " WHERE u.division_id = '$user_division'";
}
$assets_query .= " ORDER BY inst.institution_name ASC, d.division_name ASC, u.unit_code ASC, i.item_name ASC, fa.asset_tag ASC";
$result = $conn->query($assets_query);

$registry = [];
while ($row = $result->fetch_assoc()) {
    $registry[$row['institution_name']][$row['division_name']][strtoupper($row['unit_code']) . " - " . $row['unit_name']][$row['item_name']][$row['bill_no'] . " | " . $row['vendor_name']][] = $row;
}

$page_title = "Asset Registry";
ob_start();
?>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0 text-dark">Asset ID Registry</h3>
            <p class="text-muted mb-0">Full organizational hardware tracking</p>
        </div>
        <a href="tag_assets.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
            <i class="bi bi-tag me-2"></i>View Queue
        </a>
    </div>

    <div class="accordion border-0 shadow-sm rounded-4 overflow-hidden" id="level1_inst">
        <?php $idx1 = 0; foreach ($registry as $inst_name => $divisions): $idx1++; ?>
            <div class="accordion-item border-0 border-bottom">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed fw-bold py-3 fs-5" type="button" data-bs-toggle="collapse" data-bs-target="#inst_<?= $idx1 ?>">
                        <i class="bi bi-bank2 text-primary me-3"></i> <?= htmlspecialchars($inst_name) ?>
                    </button>
                </h2>
                <div id="inst_<?= $idx1 ?>" class="accordion-collapse collapse" data-bs-parent="#level1_inst">
                    <div class="accordion-body p-0 bg-light">
                        
                        <div class="accordion accordion-flush" id="level2_div_<?= $idx1 ?>">
                            <?php $idx2 = 0; foreach ($divisions as $div_name => $units): $idx2++; ?>
                                <div class="accordion-item bg-transparent">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed fw-semibold py-3 ps-5" type="button" data-bs-toggle="collapse" data-bs-target="#div_<?= $idx1 ?>_<?= $idx2 ?>">
                                            <i class="bi bi-diagram-3 me-2"></i> <?= htmlspecialchars($div_name) ?>
                                        </button>
                                    </h2>
                                    <div id="div_<?= $idx1 ?>_<?= $idx2 ?>" class="accordion-collapse collapse" data-bs-parent="#level2_div_<?= $idx1 ?>">
                                        <div class="accordion-body p-0">

                                            <div class="accordion accordion-flush" id="level3_unit_<?= $idx1 ?>_<?= $idx2 ?>">
                                                <?php $idx3 = 0; foreach ($units as $unit_label => $items): $idx3++; ?>
                                                    <div class="accordion-item bg-white border-bottom">
                                                        <h2 class="accordion-header">
                                                            <button class="accordion-button collapsed fw-bold py-3 ps-5 text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#unit_<?= $idx1 ?>_<?= $idx2 ?>_<?= $idx3 ?>">
                                                                <i class="bi bi-building-fill text-secondary me-3"></i> <?= htmlspecialchars($unit_label) ?>
                                                            </button>
                                                        </h2>
                                                        <div id="unit_<?= $idx1 ?>_<?= $idx2 ?>_<?= $idx3 ?>" class="accordion-collapse collapse">
                                                            <div class="accordion-body p-0">
                                                                
                                                                <div class="list-group list-group-flush">
                                                                    <?php $idx4 = 0; foreach ($items as $item_name => $bills): $idx4++; 
                                                                        $item_collapse_id = "itemCollapse_" . $idx1 . "_" . $idx2 . "_" . $idx3 . "_" . $idx4;
                                                                        $all_ids = []; foreach($bills as $bl) { foreach($bl as $ast) { $all_ids[] = $ast['asset_db_id']; } }
                                                                    ?>
                                                                        <div class="list-group-item border-0 p-0">
                                                                            <div class="d-flex justify-content-between align-items-center px-4 py-3 bg-light border-bottom">
                                                                                <div class="fw-bold fs-6 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#<?= $item_collapse_id ?>">
                                                                                    <i class="bi bi-chevron-right small me-2"></i>
                                                                                    <?= htmlspecialchars($item_name) ?>
                                                                                    <span class="badge bg-dark rounded-pill px-3 ms-2 fs-7"><?= count($all_ids) ?> Items</span>
                                                                                </div>
                                                                                <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="bulkVerify(<?= htmlspecialchars(json_encode($all_ids)) ?>)">Verify All</button>
                                                                            </div>

                                                                            <div id="<?= $item_collapse_id ?>" class="collapse bg-white">
                                                                                <div class="table-responsive px-3 py-2">
                                                                                    <table class="table table-hover align-middle mb-0 mt-2">
                                                                                        <?php foreach ($bills as $bill_info => $assets_list): 
                                                                                            $parts = explode(" | ", $bill_info);
                                                                                        ?>
                                                                                            <tr class="table-secondary-subtle">
                                                                                                <td colspan="4" class="py-2 ps-4">
                                                                                                    <div class="d-flex align-items-center gap-4">
                                                                                                        <span><i class="bi bi-person-badge me-1"></i> <strong>Vendor:</strong> <?= htmlspecialchars($parts[1]) ?></span>
                                                                                                        <span><i class="bi bi-receipt me-1"></i> <strong>Invoice:</strong> #<?= htmlspecialchars($parts[0]) ?></span>
                                                                                                        <span class="text-muted"><i class="bi bi-calendar3 me-1"></i> <?= date('d M, Y', strtotime($assets_list[0]['bill_date'])) ?></span>
                                                                                                    </div>
                                                                                                </td>
                                                                                            </tr>
                                                                                            <tr class="bg-white small fw-bold text-muted border-bottom">
                                                                                                <td class="ps-5 py-2">ASSET TAG ID</td>
                                                                                                <td class="text-center">STATUS</td>
                                                                                                <td class="text-center">LAST VERIFIED</td>
                                                                                                <td class="text-end pe-4">ACTIONS</td>
                                                                                            </tr>
                                                                                            <?php foreach ($assets_list as $asset): ?>
                                                                                                <tr>
                                                                                                    <td class="ps-5 py-3">
                                                                                                        <span class="asset-tag-text" id="tag-text-<?= $asset['asset_db_id'] ?>"><?= htmlspecialchars($asset['asset_tag']) ?></span>
                                                                                                        <div id="edit-container-<?= $asset['asset_db_id'] ?>" class="d-none">
                                                                                                            <div class="input-group input-group-sm" style="max-width: 250px;">
                                                                                                                <input type="text" id="input-tag-<?= $asset['asset_db_id'] ?>" class="form-control fw-bold text-primary" value="<?= htmlspecialchars($asset['asset_tag']) ?>">
                                                                                                                <button class="btn btn-primary" onclick="saveInlineEdit(<?= $asset['asset_db_id'] ?>)"><i class="bi bi-check"></i></button>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="text-center">
                                                                                                        <?php $sc = ['Available'=>'bg-success','Issued'=>'bg-info','Damaged'=>'bg-warning text-dark','Disposed'=>'bg-danger'][$asset['status']] ?? 'bg-secondary'; ?>
                                                                                                        <span class="badge <?= $sc ?> rounded-pill px-3 py-2"><?= $asset['status'] ?></span>
                                                                                                    </td>
                                                                                                    <td class="text-center fs-7" id="verify-cell-<?= $asset['asset_db_id'] ?>">
                                                                                                        <?= $asset['last_verified_date'] ? '<span class="text-success fw-medium"><i class="bi bi-check-circle-fill me-1"></i>'.date('d/m/y', strtotime($asset['last_verified_date'])).'</span>' : '<span class="text-muted fst-italic">Never</span>' ?>
                                                                                                    </td>
                                                                                                    <td class="text-end pe-4">
                                                                                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                                                                                            <button class="btn btn-sm btn-action btn-verify" 
                                                                                                                    onclick="verifyAsset(<?= $asset['asset_db_id'] ?>)" 
                                                                                                                    title="Verify Asset">
                                                                                                                <i class="bi bi-shield-check"></i>
                                                                                                            </button>

                                                                                                            <button class="btn btn-sm btn-action btn-edit" 
                                                                                                                    onclick="toggleInlineEdit(<?= $asset['asset_db_id'] ?>, true)" 
                                                                                                                    title="Edit Tag">
                                                                                                                <i class="bi bi-pencil-square"></i>
                                                                                                            </button>

                                                                                                            <button class="btn btn-sm btn-action btn-manage" 
                                                                                                                    onclick="openManageModal(
                                                                                                                        <?= $asset['asset_db_id'] ?>, 
                                                                                                                        '<?= addslashes($asset['asset_tag']) ?>', 
                                                                                                                        '<?= addslashes($asset['item_name']) ?>', 
                                                                                                                        '<?= addslashes($parts[1]) ?>', 
                                                                                                                        '<?= addslashes($parts[0]) ?>'
                                                                                                                    )" 
                                                                                                                    title="Manage Lifecycle">
                                                                                                                <i class="bi bi-gear"></i>
                                                                                                            </button>

                                                                                                            <button class="btn btn-sm btn-action btn-delete" 
                                                                                                                    onclick="deleteAsset(<?= $asset['asset_db_id'] ?>, '<?= addslashes($asset['asset_tag']) ?>')" 
                                                                                                                    title="Delete">
                                                                                                                <i class="bi bi-trash3"></i>
                                                                                                            </button>
                                                                                                        </div>
                                                                                                    </td>    
                                                                                                </tr>
                                                                                            <?php endforeach; ?>
                                                                                            <tr style="height: 10px;"><td colspan="4"></td></tr>
                                                                                        <?php endforeach; ?>
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
<style>
    .asset-tag-text { font-family: 'Monaco', 'Consolas', monospace; font-weight: 700; font-size: 1.05rem; color: #0d6efd; }
    .table-secondary-subtle { background-color: #f8fafc; font-size: 0.95rem; border-left: 4px solid #64748b; }
    .accordion-button:not(.collapsed) { background-color: #f8fafc; color: #0d6efd; box-shadow: none; }
    .cursor-pointer { cursor: pointer; }
    .fs-7 { font-size: 0.85rem; }
    
    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: none;
        transition: all 0.2s ease;
        background-color: #f8fafc; /* Light gray base */
    }

    /* Icon Specific Colors & Hovers */
    .btn-edit { color: #0d6efd; }
    .btn-edit:hover { background-color: #e7f1ff; color: #0a58ca; }

    .btn-verify { color: #198754; }
    .btn-verify:hover { background-color: #e8f5e9; color: #146c43; }

    .btn-manage { color: #0dcaf0; }
    .btn-manage:hover { background-color: #e0f7fa; color: #0891b2; }

    .btn-delete { color: #dc3545; }
    .btn-delete:hover { background-color: #f8d7da; color: #b02a37; }

    /* Ensure icons are a consistent size */
    .btn-action i { font-size: 1.1rem; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let manageModal;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Modal Instance safely after DOM loads
    manageModal = new bootstrap.Modal(document.getElementById('manageModal'));
});

function openManageModal(id, tag, name, vendor, invoice) {
    const hiddenInput = document.getElementById('hidden_asset_id');
    if(!hiddenInput) return; // Prevent "null" errors

    hiddenInput.value = id;
    document.getElementById('disp_asset_id').innerText = tag;
    document.getElementById('disp_item_name').innerText = name;
    document.getElementById('disp_vendor_name').innerText = vendor;
    document.getElementById('disp_invoice_no').innerText = invoice;
    document.getElementById('action_remarks').value = '';
    
    manageModal.show();
}




// Use this to ensure the function is globally available
window.prepareAction = function(type) {
    const assetId = document.getElementById('hidden_asset_id').value;
    const assetTag = document.getElementById('disp_asset_id').innerText;
    const remarks = document.getElementById('action_remarks').value;

    if (!remarks && type !== 'return') {
        Swal.fire('Required', 'Please provide remarks for this action.', 'info');
        return;
    }

    handleAssetAction(type, assetId, assetTag, remarks);
};

window.handleAssetAction = function(actionType, assetId, assetTag, remarks) {
    const manageModalEl = document.getElementById('manageModal');
    const modalInstance = bootstrap.Modal.getInstance(manageModalEl);
    if (modalInstance) modalInstance.hide();

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
            
            // Send via AJAX to update_asset.php
            fetch('update_asset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=lifecycle&id=${assetId}&type=${actionType}&remarks=${encodeURIComponent(remarks)}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.fire('Success', 'Asset status updated.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Update failed', 'error');
                }
            });
        } else {
            if (modalInstance) modalInstance.show();
        }
    });
};

function processAction(type) {
    const id = document.getElementById('hidden_asset_id').value;
    const remarks = document.getElementById('action_remarks').value;
    
    if(!remarks && type !== 'return') {
        alert("Please provide remarks for this action.");
        return;
    }

    if(confirm(`Are you sure you want to ${type} this asset?`)) {
        fetch('update_asset.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=lifecycle&id=${id}&type=${type}&remarks=${encodeURIComponent(remarks)}`
        }).then(res => res.json()).then(data => {
            if(data.success) location.reload();
        });
    }
}

function deleteAsset(id, tag) {
    Swal.fire({
        title: 'Are you sure?',
        text: `CRITICAL: You are about to DELETE asset ${tag}. This cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch('update_asset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.fire('Deleted!', 'Asset has been removed.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Deletion failed', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Network error or server down', 'error');
            });
        }
    });
}

function toggleInlineEdit(id, isEditing) {
    const textSpan = document.getElementById('tag-text-' + id);
    const editDiv = document.getElementById('edit-container-' + id);
    if (isEditing) { textSpan.classList.add('d-none'); editDiv.classList.remove('d-none'); } 
    else { textSpan.classList.remove('d-none'); editDiv.classList.add('d-none'); }
}

function saveInlineEdit(id) {
    const newTag = document.getElementById('input-tag-' + id).value.toUpperCase();
    fetch('update_asset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=edit_tag&id=${id}&tag=${encodeURIComponent(newTag)}`
    }).then(res => res.json()).then(data => {
        if(data.success) { 
            document.getElementById('tag-text-' + id).innerText = newTag; 
            toggleInlineEdit(id, false); 
        }
    });
}

function verifyAsset(id) {
    fetch('update_asset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=verify&id=${id}`
    }).then(res => res.json()).then(data => {
        if(data.success) {
            document.getElementById('verify-cell-' + id).innerHTML = `<span class="text-success fw-medium"><i class="bi bi-check-circle-fill me-1"></i>${data.new_date}</span>`;
        }
    });
}

async function bulkVerify(idArray) {
    if(!confirm(`Verify all ${idArray.length} items?`)) return;
    for (const id of idArray) {
        await fetch('update_asset.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
            body: `action=verify&id=${id}` 
        });
    }
    location.reload();
}
</script>

<?php $content = ob_get_clean(); 
$modal_html = '
<div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold mb-0 text-primary">Asset Lifecycle Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light rounded-4 p-3 mb-3 border shadow-sm">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-white p-2 rounded-3 shadow-sm me-3 border">
                            <i class="bi bi-box-seam fs-4 text-primary"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" id="disp_item_name"></h6>
                            <small class="text-muted" id="disp_vendor_name"></small>
                        </div>
                    </div>
                    <div class="row g-0 pt-2 border-top">
                        <div class="col-6">
                            <small class="text-uppercase fw-bold text-muted d-block" style="font-size: 0.6rem;">Invoice #</small>
                            <div class="fw-semibold small text-dark" id="disp_invoice_no"></div>
                        </div>
                        <div class="col-6 border-start ps-3">
                            <small class="text-uppercase fw-bold text-muted d-block" style="font-size: 0.6rem;">Asset Tag ID</small>
                            <div class="fw-bold text-primary small" id="disp_asset_id"></div>
                        </div>
                    </div>
                </div>

                <div class="form-floating mb-4">
                    <textarea class="form-control border-2" placeholder="Reason for action" id="action_remarks" style="height: 100px; resize: none;"></textarea>
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
</div>';
include "furniturelayout.php"; ?>