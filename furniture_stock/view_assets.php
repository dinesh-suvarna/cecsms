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
                                                                                                        <button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold" onclick="toggleInlineEdit(<?= $asset['asset_db_id'] ?>, true)">Edit</button>
                                                                                                        <button class="btn btn-success btn-sm rounded-pill px-3 fw-semibold" onclick="verifyAsset(<?= $asset['asset_db_id'] ?>)">Verify</button>
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
</style>

<script>
// JS logic remains the same as your working version
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
        if(data.success) { document.getElementById('tag-text-' + id).innerText = newTag; toggleInlineEdit(id, false); }
    });
}

function verifyAsset(id) {
    fetch('update_asset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=verify&id=${id}`
    }).then(res => res.json()).then(data => {
        if(data.success) document.getElementById('verify-cell-' + id).innerHTML = `<span class="text-success fw-medium"><i class="bi bi-check-circle-fill me-1"></i>${data.new_date}</span>`;
    });
}

async function bulkVerify(idArray) {
    if(!confirm(`Verify all ${idArray.length} items?`)) return;
    for (const id of idArray) {
        await fetch('update_asset.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=verify&id=${id}` });
    }
    location.reload();
}
</script>

<?php $content = ob_get_clean(); include "furniturelayout.php"; ?>