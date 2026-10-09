<?php
require_once __DIR__ . "/../config/db.php";
session_start();

// Security Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

/* ================= HANDLE ASSET TAG UPDATE ================= */
if (isset($_POST['update_asset_tag'])) {
    $db_id = (int)$_POST['db_id'];
    $new_asset_tag = trim($_POST['new_asset_tag']);

    if (!empty($new_asset_tag)) {
        $stmt = $conn->prepare("UPDATE furniture_assets SET asset_tag = ? WHERE id = ?");
        $stmt->bind_param("si", $new_asset_tag, $db_id);
        $stmt->execute();
        $_SESSION['swal_type'] = "success";
        $_SESSION['swal_msg'] = "Asset Tag updated successfully.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

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

$assets_query .= "
    ORDER BY
        inst.institution_name ASC,
        d.division_name ASC,
        u.unit_code ASC,
        i.item_name ASC,
        fa.asset_tag ASC";

$result = $conn->query($assets_query);
$registry = [];

while ($row = $result->fetch_assoc()) {
    $registry[$row['institution_name']]
             [$row['division_name']]
             [strtoupper($row['unit_code']) . " - " . $row['unit_name']]
             [$row['item_name']]
             [$row['bill_no'] . " | " . $row['vendor_name']][] = $row;
}

$page_title = "Asset Registry";
ob_start();
?>

<!-- FURNITURE ASSET REGISTRY -->

<div class="container-fluid p-0 pb-4">
    <!-- PAGE HEADER -->
    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
        <div>
            <h4 class="fw-bold mb-1 text-dark" style="font-size: 1.25rem;">
                <i class="bi bi-lamp me-2" style="color: var(--erp-navy, #173f63);"></i>
                Asset ID Registry
            </h4>
            <p class="text-muted mb-0 small">
                Complete furniture asset tracking across institutions, divisions and units.
            </p>
        </div>
        <a href="tag_assets.php" class="btn btn-erp-outline d-inline-flex align-items-center gap-2">
            <i class="bi bi-tag"></i> View Queue
        </a>
    </div>
    <?php
$isSuperAdmin = (($_SESSION['role'] ?? '') === 'SuperAdmin');
?>

<!-- INSTITUTION ACCORDION -->
<?php if ($isSuperAdmin): ?>
<div class="accordion unit-accordion" id="level1_inst">
<?php endif; ?>

<?php $idx1 = 0; ?>

<?php foreach ($registry as $inst_name => $divisions): ?>
    <?php $idx1++; ?>

    <?php if ($isSuperAdmin): ?>
    <div class="accordion-item shadow-sm">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#inst_<?= $idx1 ?>"
                    aria-expanded="false">
                <div class="d-flex align-items-center w-100 me-3">
                    <i class="bi bi-bank2 me-2"
                       style="color: var(--erp-navy, #173f63);"></i>
                    <span><?= htmlspecialchars($inst_name) ?></span>
                </div>
            </button>
        </h2>

        <div id="inst_<?= $idx1 ?>"
             class="accordion-collapse collapse"
             data-bs-parent="#level1_inst">
            <div class="accordion-body p-3 bg-light">
    <?php endif; ?>

    <!-- DIVISION ACCORDION -->
    <?php if ($isSuperAdmin): ?>
    <div class="accordion unit-accordion"
         id="level2_div_<?= $idx1 ?>">
    <?php endif; ?>

    <?php $idx2 = 0; ?>

    <?php foreach ($divisions as $div_name => $units): ?>
        <?php $idx2++; ?>

        <?php if ($isSuperAdmin): ?>
        <div class="accordion-item shadow-sm">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#div_<?= $idx1 ?>_<?= $idx2 ?>"
                        aria-expanded="false">
                    <div class="d-flex align-items-center w-100 me-3">
                        <i class="bi bi-diagram-3 me-2"
                           style="color: var(--erp-navy, #173f63);"></i>
                        <span><?= htmlspecialchars($div_name) ?></span>
                    </div>
                </button>
            </h2>

            <div id="div_<?= $idx1 ?>_<?= $idx2 ?>"
                 class="accordion-collapse collapse"
                 data-bs-parent="#level2_div_<?= $idx1 ?>">
                <div class="accordion-body p-3 bg-light">
        <?php endif; ?>

        <!-- UNIT ACCORDION: KEEP YOUR EXISTING UNIT AND ASSET MARKUP HERE -->
                                            <!-- UNIT ACCORDION -->
                                            <div class="accordion unit-accordion" id="level3_unit_<?= $idx1 ?>_<?= $idx2 ?>">
                                                <?php $idx3 = 0; ?>
                                                <?php foreach ($units as $unit_label => $items): ?>
                                                    <?php $idx3++; ?>
                                                    <div class="accordion-item shadow-sm">
                                                        <h2 class="accordion-header">
                                                            <button class="accordion-button collapsed" type="button"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#unit_<?= $idx1 ?>_<?= $idx2 ?>_<?= $idx3 ?>"
                                                                    aria-expanded="false">
                                                                <div class="d-flex align-items-center w-100 me-3">
                                                                    <i class="bi bi-building me-2" style="color: var(--erp-navy, #173f63);"></i>
                                                                    <span><?= htmlspecialchars($unit_label) ?></span>
                                                                </div>
                                                            </button>
                                                        </h2>

                                                        <div id="unit_<?= $idx1 ?>_<?= $idx2 ?>_<?= $idx3 ?>" class="accordion-collapse collapse">
                                                            <div class="accordion-body p-3 bg-light">
                                                                <!-- FURNITURE ITEM GROUPS -->
                                                                <?php $idx4 = 0; ?>
                                                                <?php foreach ($items as $item_name => $bills): ?>
                                                                    <?php
                                                                    $idx4++;
                                                                    $item_collapse_id = "itemCollapse_" . $idx1 . "_" . $idx2 . "_" . $idx3 . "_" . $idx4;
                                                                    $all_ids = [];

                                                                    foreach ($bills as $bl) {
                                                                        foreach ($bl as $ast) {
                                                                            $all_ids[] = $ast['asset_db_id'];
                                                                        }
                                                                    }
                                                                    ?>

                                                                    <div class="asset-group-card shadow-sm">
                                                                        <!-- ITEM HEADER -->
                                                                        <button type="button"
                                                                                class="asset-group-button collapsed d-flex align-items-center justify-content-between flex-wrap gap-2"
                                                                                data-bs-toggle="collapse"
                                                                                data-bs-target="#<?= $item_collapse_id ?>"
                                                                                aria-expanded="false">
                                                                            <div class="d-flex align-items-center flex-wrap gap-2 me-3">
                                                                                <i class="bi bi-lamp fs-5 me-1" style="color: var(--erp-navy, #173f63);"></i>
                                                                                <span class="fw-bold text-dark me-2" style="font-size: 0.95rem;">
                                                                                    <?= htmlspecialchars($item_name) ?>
                                                                                </span>
                                                                            </div>
                                                                            <div class="d-flex align-items-center gap-2 me-3">
                                                                                <span class="badge bg-secondary-subtle text-secondary-emphasis border fw-bold"
                                                                                      style="font-size:0.7rem;">
                                                                                    Total: <?= count($all_ids) ?>
                                                                                </span>
                                                                                <span class="btn btn-sm btn-verify-all"
                                                                                      onclick="event.stopPropagation(); bulkVerify(<?= htmlspecialchars(json_encode($all_ids)) ?>)">
                                                                                    <i class="bi bi-shield-check me-1"></i> Verify All
                                                                                </span>
                                                                            </div>
                                                                        </button>

                                                                        <!-- ITEM CONTENT -->
                                                                        <div id="<?= $item_collapse_id ?>" class="collapse">
                                                                            <div class="p-2">
                                                                                <!-- BILL / VENDOR GROUPS -->
                                                                                <?php foreach ($bills as $bill_info => $assets_list): ?>
                                                                                    <?php $parts = explode(" | ", $bill_info); ?>

                                                                                    <div class="bill-group-card">
                                                                                        <!-- BILL INFORMATION -->
                                                                                        <div class="bill-info-row">
                                                                                            <div class="d-flex align-items-center flex-wrap gap-3">
                                                                                                <span class="bill-info-item">
                                                                                                    <i class="bi bi-person-badge me-1"></i>
                                                                                                    <strong>Vendor:</strong> <?= htmlspecialchars($parts[1]) ?>
                                                                                                </span>
                                                                                                <span class="bill-info-item">
                                                                                                    <i class="bi bi-receipt me-1"></i>
                                                                                                    <strong>Invoice:</strong> #<?= htmlspecialchars($parts[0]) ?>
                                                                                                </span>
                                                                                                <span class="bill-info-item text-secondary">
                                                                                                    <i class="bi bi-calendar3 me-1"></i>
                                                                                                    <?= date('d M, Y', strtotime($assets_list[0]['bill_date'])) ?>
                                                                                                </span>
                                                                                            </div>
                                                                                        </div>

                                                                                        <!-- ASSET TABLE -->
                                                                                        <div class="table-responsive">
                                                                                            <table class="table align-middle table-erp-minimal mb-0">
                                                                                                <thead>
                                                                                                    <tr>
                                                                                                        <th style="width:70px;">Sl. No</th>
                                                                                                        <th>Asset Tag ID</th>
                                                                                                        <th class="text-center">Status</th>
                                                                                                        <th class="text-center">Last Verified</th>
                                                                                                        <th class="text-end">Asset Action</th>
                                                                                                    </tr>
                                                                                                </thead>
                                                                                                <tbody>
                                                                                                    <?php $sl_no = 1; ?>
                                                                                                    <?php foreach ($assets_list as $asset): ?>
                                                                                                        <tr class="hover-row">
                                                                                                            <!-- SL NO -->
                                                                                                            <td class="text-muted fw-semibold"><?= $sl_no++ ?></td>

                                                                                                            <!-- ASSET TAG -->
                                                                                                            <td>
                                                                                                                <span class="asset-tag-text" id="tag-text-<?= $asset['asset_db_id'] ?>">
                                                                                                                    <?= htmlspecialchars($asset['asset_tag']) ?>
                                                                                                                </span>
                                                                                                                <div id="edit-container-<?= $asset['asset_db_id'] ?>" class="d-none mt-1">
                                                                                                                    <div class="input-group input-group-sm" style="max-width:250px;">
                                                                                                                        <input type="text" id="input-tag-<?= $asset['asset_db_id'] ?>"
                                                                                                                               class="form-control fw-bold text-primary"
                                                                                                                               value="<?= htmlspecialchars($asset['asset_tag']) ?>">
                                                                                                                        <button class="btn btn-primary"
                                                                                                                                onclick="saveInlineEdit(<?= $asset['asset_db_id'] ?>)">
                                                                                                                            <i class="bi bi-check"></i>
                                                                                                                        </button>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                            </td>

                                                                                                            <!-- STATUS -->
                                                                                                            <td class="text-center">
                                                                                                                <?php
                                                                                                                $sc = [
                                                                                                                    'Available' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                                                                                                                    'Issued'    => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                                                                                                    'Damaged'   => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                                                                                                    'Disposed'  => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle'
                                                                                                                ][$asset['status']] ?? 'bg-secondary-subtle text-secondary-emphasis border';
                                                                                                                ?>
                                                                                                                <span class="badge status-badge <?= $sc ?>">
                                                                                                                    <?= htmlspecialchars($asset['status']) ?>
                                                                                                                </span>
                                                                                                            </td>

                                                                                                            <!-- LAST VERIFIED -->
                                                                                                            <td class="text-center" id="verify-cell-<?= $asset['asset_db_id'] ?>">
                                                                                                                <?= $asset['last_verified_date']
                                                                                                                    ? '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>' . date('d/m/y', strtotime($asset['last_verified_date'])) . '</span>'
                                                                                                                    : '<span class="text-muted fst-italic">Never</span>' ?>
                                                                                                            </td>

                                                                                                            <!-- ACTIONS -->
                                                                                                            <td>
                                                                                                                <div class="d-flex align-items-center justify-content-end gap-1">
                                                                                                                    <button class="btn btn-sm btn-action btn-verify"
                                                                                                                            onclick="verifyAsset(<?= $asset['asset_db_id'] ?>)"
                                                                                                                            title="Verify Asset">
                                                                                                                        <i class="bi bi-shield-check"></i>
                                                                                                                    </button>
                                                                                                                    <button class="btn btn-sm btn-action btn-edit"
                                                                                                                            onclick="openEditModal(<?= $asset['asset_db_id'] ?>, '<?= addslashes($asset['asset_tag']) ?>')"
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
                                                                                                                            onclick="deleteAsset(
                                                                                                                                <?= $asset['asset_db_id'] ?>,
                                                                                                                                '<?= addslashes($asset['asset_tag']) ?>'
                                                                                                                            )"
                                                                                                                            title="Delete">
                                                                                                                        <i class="bi bi-trash3"></i>
                                                                                                                    </button>
                                                                                                                </div>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                    <?php endforeach; ?>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                                <!-- END UNIT ACCORDION -->

        <?php if ($isSuperAdmin): ?>
                </div><!-- End division accordion body -->
            </div><!-- End division collapse -->
        </div><!-- End division accordion item -->
        <?php endif; ?>

    <?php endforeach; ?>

    <?php if ($isSuperAdmin): ?>
    </div><!-- End division accordion -->
            </div><!-- End institution accordion body -->
        </div><!-- End institution collapse -->
    </div><!-- End institution accordion item -->
    <?php endif; ?>

<?php endforeach; ?>

<?php if ($isSuperAdmin): ?>
</div><!-- End institution accordion -->
<?php endif; ?>

<!-- UI STYLES -->
<style>
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

.unit-accordion .accordion-button:focus {
    box-shadow: none;
}

/* ITEM GROUP */
.asset-group-card {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background-color: #ffffff;
    margin-bottom: 0.75rem;
    overflow: hidden;
}

.asset-group-button {
    width: 100%;
    background-color: #f0f4f8;
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

/* BILL INFORMATION */
.bill-group-card {
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    background: #ffffff;
    margin-bottom: 0.75rem;
    overflow: hidden;
}

.bill-info-row {
    padding: 0.65rem 1rem;
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.bill-info-item {
    font-size: 0.78rem;
    color: #475569;
}

.bill-info-item i {
    color: #64748b;
}

/* TABLE */
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
    background-color: #e2e8f0 !important;
}

/* ASSET TAG */
.asset-tag-text {
    font-family: 'Monaco', 'Consolas', monospace;
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--erp-navy, #173f63);
}

/* STATUS */
.status-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.35rem 0.65rem;
    border-radius: 4px;
}

/* ACTION BUTTONS */
.btn-action {
    width: 30px;
    height: 30px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    border: 1px solid transparent;
    background-color: transparent;
    transition: all 0.15s ease-in-out;
}

.btn-action i {
    font-size: 0.9rem;
}

.btn-verify {
    color: #198754;
}

.btn-verify:hover {
    background-color: #e8f5e9;
    border-color: #b7dfbd;
    color: #146c43;
}

.btn-edit {
    color: #64748b;
}

.btn-edit:hover {
    background-color: #e2e8f0;
    border-color: #cbd5e1;
    color: var(--erp-navy, #173f63);
}

.btn-manage {
    color: #0891b2;
}

.btn-manage:hover {
    background-color: #e0f7fa;
    border-color: #a5e5ed;
    color: #087f96;
}

.btn-delete {
    color: #dc3545;
}

.btn-delete:hover {
    background-color: #fce8e8;
    border-color: #f1b5b5;
    color: #b02a37;
}

/* VERIFY ALL */
.btn-verify-all {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.65rem;
    border: 1px solid #198754;
    border-radius: 4px;
    background: transparent;
    color: #198754;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}

.btn-verify-all:hover {
    background-color: #198754;
    color: #ffffff;
}

/* ERP OUTLINE BUTTON */
.btn-erp-outline {
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.4rem 0.8rem;
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

/* CURSOR */
.cursor-pointer {
    cursor: pointer;
}

/* MOBILE */
@media (max-width: 768px) {
    .unit-accordion .accordion-button {
        padding: 0.75rem 0.9rem;
        font-size: 0.88rem;
    }

    .asset-group-button {
        padding: 0.7rem 0.75rem;
    }

    .bill-info-row .d-flex {
        gap: 0.5rem !important;
    }

    .table-erp-minimal th,
    .table-erp-minimal td {
        padding: 0.55rem 0.65rem;
    }

    .btn-action {
        width: 28px;
        height: 28px;
    }

    .asset-tag-text {
        font-size: 0.8rem;
    }
}
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
    if (!hiddenInput) return;

    hiddenInput.value = id;
    document.getElementById('disp_asset_id').innerText = tag;
    document.getElementById('disp_item_name').innerText = name;
    document.getElementById('disp_vendor_name').innerText = vendor;
    document.getElementById('disp_invoice_no').innerText = invoice;
    document.getElementById('action_remarks').value = '';

    manageModal.show();
}

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
        return: { title: 'Return Asset?', color: '#f59e0b' },
        repair: { title: 'Request Repair?', color: '#0dcaf0' },
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
            Swal.fire({
                title: 'Processing...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send via AJAX to update_asset.php
            fetch('update_asset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=lifecycle&id=${assetId}&type=${actionType}&remarks=${encodeURIComponent(remarks)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', 'Asset status updated.', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Update failed', 'error');
                }
            });
        } else {
            if (modalInstance) modalInstance.show();
        }
    });
};

function openEditModal(id, tag) {
    document.getElementById('edit_db_id').value = id;
    document.getElementById('edit_asset_tag').value = tag;
    new bootstrap.Modal(document.getElementById('editIdModal')).show();
}

function processAction(type) {
    const id = document.getElementById('hidden_asset_id').value;
    const remarks = document.getElementById('action_remarks').value;

    if (!remarks && type !== 'return') {
        alert("Please provide remarks for this action.");
        return;
    }

    if (confirm(`Are you sure you want to ${type} this asset?`)) {
        fetch('update_asset.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=lifecycle&id=${id}&type=${type}&remarks=${encodeURIComponent(remarks)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) location.reload();
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
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('update_asset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', 'Asset has been removed.', 'success')
                        .then(() => location.reload());
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

    if (isEditing) {
        textSpan.classList.add('d-none');
        editDiv.classList.remove('d-none');
    } else {
        textSpan.classList.remove('d-none');
        editDiv.classList.add('d-none');
    }
}

function saveInlineEdit(id) {
    const newTag = document.getElementById('input-tag-' + id).value.toUpperCase();

    fetch('update_asset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=edit_tag&id=${id}&tag=${encodeURIComponent(newTag)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
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
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('verify-cell-' + id).innerHTML =
                `<span class="text-success fw-medium"><i class="bi bi-check-circle-fill me-1"></i>${data.new_date}</span>`;
        }
    });
}

async function bulkVerify(idArray) {
    if (!confirm(`Verify all ${idArray.length} items?`)) return;

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
</div>

<div class="modal fade" id="editIdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom p-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-pencil-square me-2"></i>Update Asset Tag ID</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="db_id" id="edit_db_id">
                    <label class="form-label small fw-bold text-secondary">Asset Tag / ID</label>
                    <input type="text" name="new_asset_tag" id="edit_asset_tag" class="form-control fw-bold form-control-lg fs-6" required>
                </div>
                <div class="modal-footer border-0 p-3 pt-0">
                    <button type="submit" name="update_asset_tag" class="btn btn-primary w-100 fw-bold" style="background-color: var(--erp-navy, #173f63); border-color: var(--erp-navy, #173f63);">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>';

include "furniturelayout.php";
?>