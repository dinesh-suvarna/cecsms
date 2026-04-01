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

// Fetch assets with joins - Filtered by Division for Admins
$assets_query = "
    SELECT 
        fa.id as asset_db_id, 
        fa.asset_tag, 
        fa.status,
        fa.last_verified_date,
        s.bill_no, 
        s.bill_date,
        i.item_name,
        v.vendor_name,
        u.unit_name,
        u.unit_code
    FROM furniture_assets fa
    JOIN furniture_stock s ON fa.stock_id = s.id
    JOIN furniture_items i ON s.furniture_item_id = i.id
    JOIN vendors v ON s.vendor_id = v.id
    JOIN units u ON s.unit_id = u.id";

// Apply Division Filter
if ($user_role !== 'SuperAdmin') {
    $assets_query .= " WHERE u.division_id = '$user_division'";
}

$assets_query .= " ORDER BY u.unit_code ASC, i.item_name ASC, s.bill_no ASC, fa.asset_tag ASC";

$result = $conn->query($assets_query);

// Group Data logic 
$registry = [];
while ($row = $result->fetch_assoc()) {
    $unit_key = strtoupper($row['unit_code']) . " - " . $row['unit_name'];
    $item_key = $row['item_name'];
    $bill_key = $row['bill_no'] . " | " . $row['vendor_name'];
    $registry[$unit_key][$item_key][$bill_key][] = $row;
}

$page_title = "Asset Registry";
ob_start();
?>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0 text-dark">Asset ID Registry</h3>
            <p class="text-muted mb-0">Detailed hardware tracking by Unit and Item</p>
        </div>
        <a href="tag_assets.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
            <i class="bi bi-tag me-2"></i>View Queue
        </a>
    </div>

    <?php if (empty($registry)): ?>
        <div class="card border-0 shadow-sm rounded-4 py-5 text-center">
            <i class="bi bi-inbox fs-1 opacity-25"></i>
            <p class="text-muted mt-3">No assets registered yet.</p>
        </div>
    <?php else: ?>
        <div class="accordion border-0 shadow-sm rounded-4 overflow-hidden" id="mainRegistry">
            <?php 
            $u_idx = 0;
            foreach ($registry as $unit_label => $items): 
                $u_idx++;
                $unit_collapse_id = "unitCollapse" . $u_idx;
            ?>
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold py-3 fs-5" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $unit_collapse_id ?>">
                            <i class="bi bi-building-fill text-primary me-3"></i>
                            <?= htmlspecialchars($unit_label) ?>
                        </button>
                    </h2>
                    <div id="<?= $unit_collapse_id ?>" class="accordion-collapse collapse" data-bs-parent="#mainRegistry">
                        <div class="accordion-body p-0">
                            <div class="list-group list-group-flush">
                                <?php 
                                $i_idx = 0;
                                foreach ($items as $item_name => $bills): 
                                    $i_idx++;
                                    $item_collapse_id = "itemCollapse" . $u_idx . "_" . $i_idx;
                                    
                                    // Collect all IDs for bulk verification in this category
                                    $all_ids_in_cat = [];
                                    foreach($bills as $bl) {
                                        foreach($bl as $ast) { $all_ids_in_cat[] = $ast['asset_db_id']; }
                                    }
                                ?>
                                    <div class="list-group-item border-0 p-0">
                                        <div class="d-flex justify-content-between align-items-center px-4 py-3 bg-light border-bottom">
                                            <div class="fw-bold fs-6 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#<?= $item_collapse_id ?>">
                                                <i class="bi bi-chevron-right small me-2"></i>
                                                <?= htmlspecialchars($item_name) ?>
                                                <span class="badge bg-dark rounded-pill px-3 ms-2 fs-7"><?= count($all_ids_in_cat) ?> Items</span>
                                            </div>
                                            <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" 
                                                    onclick="bulkVerify(<?= htmlspecialchars(json_encode($all_ids_in_cat)) ?>)">
                                                <i class="bi bi-check-all me-1"></i> Verify All
                                            </button>
                                        </div>

                                        <div id="<?= $item_collapse_id ?>" class="collapse">
                                            <div class="table-responsive bg-white px-3 py-2">
                                                <table class="table table-hover align-middle mb-0 mt-2">
                                                    <tbody>
                                                        <?php foreach ($bills as $bill_info => $assets_list): 
                                                            $parts = explode(" | ", $bill_info);
                                                            $bill_no = $parts[0];
                                                            $vendor = $parts[1];
                                                            $bill_date = $assets_list[0]['bill_date'];
                                                        ?>
                                                            <tr class="table-secondary-subtle">
                                                                <td colspan="4" class="py-2 ps-4">
                                                                    <div class="d-flex align-items-center gap-4">
                                                                        <span><i class="bi bi-person-badge me-1"></i> <strong>Vendor:</strong> <?= htmlspecialchars($vendor) ?></span>
                                                                        <span><i class="bi bi-receipt me-1"></i> <strong>Invoice:</strong> #<?= htmlspecialchars($bill_no) ?></span>
                                                                        <span class="text-muted"><i class="bi bi-calendar3 me-1"></i> <?= date('d M, Y', strtotime($bill_date)) ?></span>
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
                                                                        <span class="asset-tag-text" id="tag-text-<?= $asset['asset_db_id'] ?>">
                                                                            <?= htmlspecialchars($asset['asset_tag']) ?>
                                                                        </span>
                                                                        <div id="edit-container-<?= $asset['asset_db_id'] ?>" class="d-none">
                                                                            <div class="input-group input-group-sm" style="max-width: 280px;">
                                                                                <input type="text" id="input-tag-<?= $asset['asset_db_id'] ?>" class="form-control fw-bold font-monospace text-primary border-primary" value="<?= htmlspecialchars($asset['asset_tag']) ?>" style="text-transform: uppercase;">
                                                                                <button class="btn btn-primary" onclick="saveInlineEdit(<?= $asset['asset_db_id'] ?>)"><i class="bi bi-check-lg"></i></button>
                                                                                <button class="btn btn-outline-secondary" onclick="toggleInlineEdit(<?= $asset['asset_db_id'] ?>, false)"><i class="bi bi-x-lg"></i></button>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <?php
                                                                        $status_class = ['Available' => 'bg-success', 'Issued' => 'bg-info', 'Damaged' => 'bg-warning text-dark', 'Disposed' => 'bg-danger'][$asset['status']] ?? 'bg-secondary';
                                                                        ?>
                                                                        <span class="badge <?= $status_class ?> rounded-pill px-3 py-2"><?= $asset['status'] ?></span>
                                                                    </td>
                                                                    <td class="text-center fs-7" id="verify-cell-<?= $asset['asset_db_id'] ?>">
                                                                        <?php if ($asset['last_verified_date']): ?>
                                                                            <span class="text-success fw-medium"><i class="bi bi-check-circle-fill me-1"></i><?= date('d/m/y', strtotime($asset['last_verified_date'])) ?></span>
                                                                        <?php else: ?>
                                                                            <span class="text-muted fst-italic">Never</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-end pe-4">
                                                                        <div class="d-flex justify-content-end gap-2" id="action-btns-<?= $asset['asset_db_id'] ?>">
                                                                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold" onclick="toggleInlineEdit(<?= $asset['asset_db_id'] ?>, true)"><i class="bi bi-pencil-square me-1"></i> Edit</button>
                                                                            <button class="btn btn-success btn-sm rounded-pill px-3 fw-semibold" id="btn-verify-<?= $asset['asset_db_id'] ?>" onclick="verifyAsset(<?= $asset['asset_db_id'] ?>)"><i class="bi bi-shield-check me-1"></i> Verify</button>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            <tr style="height: 15px;"><td colspan="4"></td></tr>
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
    <?php endif; ?>
</div>

<script>
function toggleInlineEdit(id, isEditing) {
    const textSpan = document.getElementById('tag-text-' + id);
    const editDiv = document.getElementById('edit-container-' + id);
    const actionBtns = document.getElementById('action-btns-' + id);
    if (isEditing) {
        textSpan.classList.add('d-none');
        editDiv.classList.remove('d-none');
        actionBtns.classList.add('invisible');
        document.getElementById('input-tag-' + id).focus();
    } else {
        textSpan.classList.remove('d-none');
        editDiv.classList.add('d-none');
        actionBtns.classList.remove('invisible');
    }
}

function saveInlineEdit(id) {
    const input = document.getElementById('input-tag-' + id);
    const newTag = input.value.toUpperCase();
    if (!newTag) { Swal.fire('Warning', 'Tag cannot be empty', 'warning'); return; }

    fetch('update_asset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=edit_tag&id=${id}&tag=${encodeURIComponent(newTag)}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            document.getElementById('tag-text-' + id).innerText = newTag;
            toggleInlineEdit(id, false);
            Swal.fire({ icon: 'success', title: 'Updated', timer: 800, showConfirmButton: false, toast: true, position: 'top-end' });
        } else { Swal.fire('Error', data.message, 'error'); }
    });
}

// Single Verification Logic
function verifyAsset(assetId) {
    const btn = document.getElementById('btn-verify-' + assetId);
    btn.disabled = true;
    fetch('update_asset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=verify&id=${assetId}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            document.getElementById('verify-cell-' + assetId).innerHTML = `<span class="text-success fw-medium"><i class="bi bi-check-circle-fill me-1"></i>${data.new_date}</span>`;
            Swal.fire({ icon: 'success', title: 'Verified!', timer: 800, showConfirmButton: false, toast: true, position: 'top-end' });
        }
        btn.disabled = false;
    });
}

// Bulk Verification Logic
async function bulkVerify(idArray) {
    const result = await Swal.fire({
        title: 'Bulk Verify?',
        text: `You are about to verify all ${idArray.length} items in this category.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Verify All',
        confirmButtonColor: '#10b981'
    });

    if (result.isConfirmed) {
        Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        let successCount = 0;
        for (const id of idArray) {
            try {
                const response = await fetch('update_asset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=verify&id=${id}`
                });
                const data = await response.json();
                if (data.success) {
                    document.getElementById('verify-cell-' + id).innerHTML = `<span class="text-success fw-medium"><i class="bi bi-check-circle-fill me-1"></i>${data.new_date}</span>`;
                    successCount++;
                }
            } catch (err) { console.error("Failed ID: " + id); }
        }

        Swal.fire({ icon: 'success', title: 'Completed', text: `${successCount} items verified successfully!` });
    }
}
</script>

<style>
    .asset-tag-text { font-family: 'Monaco', 'Consolas', monospace; font-weight: 700; font-size: 1.05rem; color: #0d6efd; letter-spacing: 0.5px; }
    .fs-7 { font-size: 0.85rem; }
    .table-secondary-subtle { background-color: #f1f5f9; font-size: 0.95rem; border-left: 4px solid #64748b; }
    .accordion-button { font-size: 1.15rem !important; }
    .btn-sm { padding: 5px 15px; font-size: 0.85rem; }
    .accordion-button:not(.collapsed) { background-color: #f8fafc; color: #10b981; box-shadow: none; }
    .table td { border-bottom: 1px solid #f1f5f9; }
    .cursor-pointer { cursor: pointer; }
</style>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>