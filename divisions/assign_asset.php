<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$page_title = "Assign Asset ID";
$page_icon  = "bi-tag";

/* ================= HELPER FUNCTIONS ================= */
if (!function_exists('getCategoryIcon')) {
    function getCategoryIcon(string $category): string {
        $cat = strtolower(trim($category));
        if (str_contains($cat, 'computer') || str_contains($cat, 'pc') || str_contains($cat, 'laptop')) {
            return 'bi-pc-display';
        } elseif (str_contains($cat, 'accessory') || str_contains($cat, 'peripherals')) {
            return 'bi-keyboard';
        } elseif (str_contains($cat, 'network') || str_contains($cat, 'router') || str_contains($cat, 'switch')) {
            return 'bi-box-seam';
        } elseif (str_contains($cat, 'component') || str_contains($cat, 'hardware')) {
            return 'bi-cpu';
        } elseif (str_contains($cat, 'furniture')) {
            return 'bi-lamp';
        } elseif (str_contains($cat, 'mobile') || str_contains($cat, 'phone')) {
            return 'bi-phone';
        }
        return 'bi-folder';
    }
}

if (!function_exists('getItemDetailIcon')) {
    function getItemDetailIcon(?string $itemName, ?string $category = ''): string {
        $name = strtolower(trim($itemName ?? ''));
        $cleanName = str_replace([' ', '-', '_'], '', $name);

        switch (true) {
            case (str_contains($cleanName, 'accesspoint') || str_contains($cleanName, 'ipcom') || str_contains($name, 'wifi')):
                return 'bi-wifi';
            case (str_contains($name, 'rack') || str_contains($name, 'server')):
                return 'bi-hdd-rack'; 
            case (str_contains($name, 'switch') || str_contains($name, 'patch panel') || str_contains($name, 'hub')):
                return 'bi-hdd-stack'; 
            case (str_contains($name, 'router')):
                return 'bi-router';
            case (str_contains($name, 'computer') || str_contains($name, 'desktop')):
                return 'bi-pc-display';
            case (str_contains($name, 'laptop')):
                return 'bi-laptop';
            case (str_contains($name, 'monitor') || str_contains($name, 'display')):
                return 'bi-display';
            case (str_contains($name, 'printer')):
                return 'bi-printer';
            case (str_contains($name, 'keyboard')):
                return 'bi-keyboard';
            case (str_contains($name, 'mouse')):
                return 'bi-mouse3';
            case (str_contains($name, 'projector')):
                return 'bi-projector'; 
            case (str_contains($name, 'biometric') || str_contains($name, 'fingerprint')):
                return 'bi-person-bounding-box';
            case (str_contains($name, 'ups') || str_contains($name, 'battery')):
                return 'bi-lightning-charge';
            case (str_contains($name, 'table') || str_contains($name, 'desk')):
                return 'bi-table';
            case (str_contains($name, 'chair')):
                return 'bi-person-workspace';
            case (str_contains($name, 'camera') || str_contains($name, 'cctv')):
                return 'bi-camera-video';
            default:
                return 'bi-box-seam';
        }
    }
}

/* ================= CURRENT USER INFO ================= */
$role = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

/* ================= HANDLE ASSIGNMENT ================= */
if ($role !== 'SuperAdmin' && isset($_POST['assign'])) {
    $dispatch_detail_id = (int)($_POST['dispatch_detail_id'] ?? 0);
    $stock_detail_id    = (int)($_POST['stock_detail_id'] ?? 0);
    $division_asset_id  = strtoupper(trim($_POST['division_asset_id'] ?? ''));
    $unit_index         = (int)($_POST['unit_index'] ?? 0);
    $opened_unit        = trim($_POST['opened_unit'] ?? '');

    if (!empty($division_asset_id)) {
        if (!empty($opened_unit)) {
            $_SESSION['open_unit_code'] = $opened_unit;
        }

        $conn->begin_transaction(); 
        try {
            // 1. Insert into division_assets
            $insert = $conn->prepare("
                INSERT INTO division_assets 
                (dispatch_detail_id, stock_detail_id, division_asset_id, assigned_by, unit_index) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insert->bind_param("iisii", $dispatch_detail_id, $stock_detail_id, $division_asset_id, $user_id, $unit_index);
            $insert->execute();

            // 2. Check stock exhaustion
            $statusCheck = $conn->prepare("
                SELECT 
                    sd.quantity AS total_stock,
                    (SELECT SUM(dd.quantity) FROM dispatch_details dd WHERE dd.stock_detail_id = sd.id) AS total_dispatched
                FROM stock_details sd
                WHERE sd.id = ?
            ");
            $statusCheck->bind_param("i", $stock_detail_id);
            $statusCheck->execute();
            $statusRes = $statusCheck->get_result()->fetch_assoc();

            if ($statusRes['total_dispatched'] >= $statusRes['total_stock']) {
                $update = $conn->prepare("UPDATE stock_details SET status='dispatched' WHERE id=?");
            } else {
                $update = $conn->prepare("UPDATE stock_details SET status='available' WHERE id=?");
            }
            $update->bind_param("i", $stock_detail_id);
            $update->execute();

            $conn->commit();

            $_SESSION['swal_type'] = "success";
            $_SESSION['swal_msg']  = "Asset $division_asset_id assigned successfully!";
            
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $_SESSION['swal_type'] = "error";
            $_SESSION['swal_msg']  = ($e->getCode() == 1062) 
                ? "Duplicate Asset ID: $division_asset_id exists!" 
                : "Database error: " . $e->getMessage();
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

/* ================= FETCH DISPATCHED ITEMS ================= */
$whereClause = ($role === 'SuperAdmin') ? "WHERE sd.status NOT IN ('disposed')" : "WHERE dm.division_id = ? AND sd.status NOT IN ('disposed')";

$sql = "
    SELECT
        dd.id AS dispatch_detail_id,
        sd.id AS stock_detail_id,
        sd.serial_number,
        sd.bill_no,
        v.vendor_name,
        dm.dispatch_date,
        im.item_name,
        im.stock_type,
        (dd.quantity - IFNULL(dd.returned_quantity, 0)) AS effective_quantity,
        dd.quantity AS original_quantity,
        IFNULL(dd.returned_quantity, 0) AS returned_quantity,
        u.unit_name,
        u.unit_code,
        IFNULL(da_assigned.assigned_indices, '') AS assigned_indices,
        IFNULL(da_assigned.assigned_count, 0) AS assigned_count
    FROM dispatch_details dd
    JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    JOIN units u ON u.id = dm.unit_id
    JOIN stock_details sd ON sd.id = dd.stock_detail_id
    JOIN items_master im ON sd.stock_item_id = im.id
    JOIN vendors v ON v.id = sd.vendor_id
    LEFT JOIN (
        SELECT
            dispatch_detail_id,
            COUNT(*) AS assigned_count,
            GROUP_CONCAT(unit_index) AS assigned_indices
        FROM division_assets
        GROUP BY dispatch_detail_id
    ) da_assigned ON da_assigned.dispatch_detail_id = dd.id
    $whereClause
    HAVING (effective_quantity - assigned_count) > 0
    ORDER BY dm.dispatch_date DESC
";

if ($role === 'SuperAdmin') {
    $result = $conn->query($sql);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $division_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

/* ================= MULTI-DIMENSIONAL GROUPING ================= */
$grouped = [];
$total_rows_count = 0;

while ($row = $result->fetch_assoc()) {
    $unit_code = $row['unit_code'];
    $item_name = $row['item_name'];
    $assigned_indices = array_filter(explode(',', $row['assigned_indices']));

    if ($row['stock_type'] === 'non_serial') {
        $effective_qty = (int)$row['effective_quantity'];
        
        for ($i = 1; $i <= $effective_qty; $i++) {
            // Check if this specific unit index is already assigned
            if (!in_array((string)$i, $assigned_indices, true)) {
                $rowCopy = $row; 
                $rowCopy['unit_index'] = $i;
                $grouped[$unit_code][$item_name][] = $rowCopy;
                $total_rows_count++;
            }
        }
    } else {
        if (!in_array("0", $assigned_indices, true) && count($assigned_indices) === 0) {
            $row['unit_index'] = 0; 
            $grouped[$unit_code][$item_name][] = $row;
            $total_rows_count++;
        }
    }
}

ksort($grouped);

ob_start();
?>

<div class="container-fluid p-0">
    <!-- Header Block -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4 bg-white p-3 rounded-3 border">
        <div>
            <h5 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                <span class="p-2 rounded-2 d-inline-flex" style="background-color: #edf3f8; color: #123b63;">
                    <i class="bi <?= $page_icon ?> fs-5"></i>
                </span>
                Assign Asset Identifiers
            </h5>
            <p class="text-muted small m-0 mt-1">Map localized asset numbers to dispatched inventory units organized by Unit Facility.</p>
        </div>
        <div class="search-container position-relative">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y text-muted ms-3 font-xs"></i>
            <input type="text" id="assetSearch" class="form-control form-control-custom ps-5" placeholder="Filter facility or items...">
        </div>
    </div>

    <?php if (empty($grouped)): ?>
        <div class="card border-0 shadow-sm rounded-3 text-center py-5 text-muted bg-white">
            <div class="py-4">
                <i class="bi bi-check2-circle text-success display-4 d-block mb-3 opacity-75"></i>
                <span class="fw-bold d-block text-dark mb-1 fs-6">All Clear!</span>
                <span class="small">All available records are currently assigned.</span>
            </div>
        </div>
    <?php else: ?>
        <?php 
        // Retrieve and clear sticky accordion context
        $open_unit_code = $_SESSION['open_unit_code'] ?? ''; 
        unset($_SESSION['open_unit_code']); 
        ?>
        <div class="accordion d-flex flex-column gap-3" id="unitAccordion">
            <?php
            $unitIndex = 0;

            foreach ($grouped as $unit_code => $items_by_name) {
                $unitIndex++;
                
                $total_unit_pending = 0;
                foreach ($items_by_name as $items) {
                    $total_unit_pending += count($items);
                }
                
                $first_item_in_unit = reset($items_by_name)[0];
                $is_opened = ($unit_code === $open_unit_code);
                ?>
                
                <div class="accordion-item border-0 shadow-sm rounded-3 overflow-hidden unit-accordion-group" data-search-term="<?= htmlspecialchars(strtolower($unit_code . ' ' . $first_item_in_unit['unit_name'])) ?>">
                    <h2 class="accordion-header" id="heading-unit-<?= $unitIndex ?>">
                        <button class="accordion-button <?= $is_opened ? '' : 'collapsed' ?> bg-white px-4 py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-unit-<?= $unitIndex ?>" aria-expanded="<?= $is_opened ? 'true' : 'false' ?>" aria-controls="collapse-unit-<?= $unitIndex ?>">
                            <div class="w-100 me-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-building fs-5" style="color: #123b63;"></i>
                                    <span class="fw-bold fs-6 text-dark"><?= htmlspecialchars($unit_code) ?></span>
                                    <span class="badge rounded-pill fw-semibold" style="background-color: #123b63; font-size: 0.7rem;"><?= $total_unit_pending ?> Pending Allocation</span>
                                </div>
                                <div class="extra-small text-muted fw-normal">
                                    <strong>Facility Name:</strong> <?= htmlspecialchars($first_item_in_unit['unit_name']) ?>
                                </div>
                            </div>
                        </button>
                    </h2>
                    
                    <div id="collapse-unit-<?= $unitIndex ?>" class="accordion-collapse collapse <?= $is_opened ? 'show' : '' ?>" aria-labelledby="heading-unit-<?= $unitIndex ?>" data-bs-parent="#unitAccordion">
                        <div class="accordion-body p-4 bg-white d-flex flex-column gap-4 border-top">
                            
                            <?php foreach ($items_by_name as $item_name => $items): 
                                // Restart SL counter for every item block type
                                $sl = 1; 
                                $first = $items[0];
                                $itemIcon = getItemDetailIcon($item_name);
                                ?>
                                <div class="item-block border rounded-3 overflow-hidden">
                                    <div class="bg-light px-3 py-2 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                                        <div>
                                            <i class="bi <?= $itemIcon ?> text-muted me-1"></i>
                                            <span class="fw-bold text-dark sub-item-title small"><?= htmlspecialchars($item_name) ?></span>
                                            <span class="badge bg-white text-secondary border ms-1 rounded-pill extra-small"><?= count($items) ?> items</span>
                                        </div>
                                        <div class="extra-small text-muted">
                                            <strong>Dispatch Date:</strong> <span class="text-dark fw-medium"><?= !empty($first['dispatch_date']) ? date('d M Y', strtotime($first['dispatch_date'])) : '-' ?></span> | 
                                            <strong>Vendor:</strong> <?= htmlspecialchars($first['vendor_name']) ?> | 
                                            <strong>Bill:</strong> <?= htmlspecialchars($first['bill_no']) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-custom table-hover align-middle mb-0">
                                            <thead>
                                                <tr class="text-uppercase tracking-wider">
                                                    <th class="ps-3" width="70">SL</th>
                                                    <th>Serial / Unit Specifier</th>
                                                    <?php if ($role !== 'SuperAdmin'): ?>
                                                        <th class="asset-col">Internal Asset ID</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $row): ?>
                                                <tr class="asset-row">
                                                    <td class="ps-3 text-secondary font-monospace sl-cell extra-small"><?= sprintf("%02d", $sl++) ?></td>
                                                    <td>
                                                        <?php if($row['stock_type'] === 'non_serial'): ?>
                                                            <span class="badge bg-light text-dark border border-dashed extra-small"><i class="bi bi-box-seam me-1 text-muted"></i>Bulk Unit (Idx: <?= $row['unit_index'] ?>)</span>
                                                        <?php else: ?>
                                                            <span class="serial-badge text-uppercase">
                                                                <?= htmlspecialchars(strtoupper($row['serial_number'] ?? '-')) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <?php if ($role !== 'SuperAdmin'): ?>
                                                        <td class="asset-input-cell">
                                                            <form method="POST" class="m-0">
                                                                <div class="input-group input-group-merge">
                                                                    <span class="input-group-text bg-light fw-bold unit-code-badge extra-small" 
                                                                          data-bs-toggle="tooltip" 
                                                                          data-bs-placement="top" 
                                                                          title="<?= htmlspecialchars($row['unit_name']) ?>"
                                                                          style="cursor: pointer; color: #123b63;">
                                                                        <?= htmlspecialchars($row['unit_code']) ?>
                                                                    </span>

                                                                    <input type="text" name="division_asset_id" class="form-control asset-id-input text-uppercase fw-medium extra-small" placeholder="CEC/CSE/CSL01/2026-27/01" required autocomplete="off">

                                                                    <input type="hidden" name="dispatch_detail_id" value="<?= $row['dispatch_detail_id'] ?>">
                                                                    <input type="hidden" name="stock_detail_id" value="<?= $row['stock_detail_id'] ?>">
                                                                    <input type="hidden" name="unit_index" value="<?= $row['unit_index'] ?>">
                                                                    <input type="hidden" name="opened_unit" value="<?= htmlspecialchars($unit_code) ?>">

                                                                    <button type="submit" name="assign" class="btn btn-navy px-3 fw-semibold extra-small">Assign</button>
                                                                </div>
                                                                <div class="form-text text-muted mt-1 ps-1 extra-small d-flex align-items-center gap-1">
                                                                    <i class="bi bi-info-circle text-muted"></i> 
                                                                    Hover over facility code (<span class="fw-semibold"><?= htmlspecialchars($row['unit_code']) ?></span>) to see full name.
                                                                </div>
                                                            </form>
                                                        </td>
                                                    <?php endif; ?>
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
            <?php } ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if(isset($_SESSION['swal_msg'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal_type'] ?>',
        title: '<?= $_SESSION['swal_type'] == "success" ? "Success" : "Error" ?>',
        text: '<?= $_SESSION['swal_msg'] ?>',
        timer: 3000, showConfirmButton: false, toast: true, position: 'top-end'
    });
</script>
<?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); endif; ?>

<script>
document.getElementById('assetSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let unitGroups = document.querySelectorAll('.unit-accordion-group');
    
    unitGroups.forEach(group => {
        let facilityMetadata = group.getAttribute('data-search-term');
        let fullGroupContent = group.innerText.toLowerCase();
        
        if (facilityMetadata.includes(filter) || fullGroupContent.includes(filter)) {
            group.style.setProperty('display', 'block', 'important');
        } else {
            group.style.setProperty('display', 'none', 'important');
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>

<style>
    :root {
        --primary-navy: #123b63;
        --border-color: #d9e0e7;
    }

    .form-control-custom { 
        border-radius: 6px; 
        border: 1px solid var(--border-color); 
        padding: 0.45rem 0.85rem; 
        width: 280px; 
        transition: all 0.2s ease; 
        font-size: 0.85rem; 
        background: #fff; 
    }
    .form-control-custom:focus { 
        border-color: var(--primary-navy); 
        box-shadow: 0 0 0 3px rgba(18, 59, 99, 0.1); 
    }
    
    .btn-navy {
        background-color: var(--primary-navy);
        color: #ffffff;
        border: none;
        transition: background-color 0.15s ease-in-out;
    }
    .btn-navy:hover {
        background-color: #0b2942;
        color: #ffffff;
    }

    .table-custom thead th { 
        background-color: #f8fafc; 
        border-bottom: 1px solid var(--border-color); 
        color: #64748b; 
        font-size: 0.72rem; 
        font-weight: 700; 
        letter-spacing: 0.05em; 
        padding: 0.65rem 0.75rem; 
    }
    .table-custom tbody tr.asset-row { 
        border-bottom: 1px solid #edf2f7; 
        transition: background-color 0.15s ease; 
    }
    .table-custom tbody tr.asset-row:hover { 
        background-color: #f8fafc; 
    }
    .table-custom tbody td { 
        padding: 0.65rem 0.75rem; 
    }
    
    .accordion-button:not(.collapsed) { 
        background-color: #edf3f8 !important; 
        color: inherit !important; 
        box-shadow: none !important; 
    }
    .accordion-button::after { 
        background-size: 1rem; 
    }
    .accordion-item { 
        border: 1px solid var(--border-color) !important; 
    }

    .serial-badge { 
        font-family: var(--bs-font-monospace); 
        font-size: 0.78rem; 
        font-weight: 700; 
        color: var(--primary-navy); 
        background-color: #edf3f8; 
        padding: 0.3rem 0.6rem; 
        display: inline-block; 
        border-radius: 4px; 
        border: 1px solid #d0deea; 
    }
    
    .border-dashed { 
        border-style: dashed !important; 
    }
    
    .input-group-merge { 
        border-radius: 6px; 
        overflow: hidden; 
        box-shadow: 0 1px 2px rgba(0,0,0,0.03); 
        max-width: 480px; 
    }
    .input-group-merge .form-control { 
        border: 1px solid var(--border-color); 
    }
    .input-group-merge .input-group-text { 
        border: 1px solid var(--border-color); 
        background: #f8fafc; 
        min-width: 60px; 
        justify-content: center; 
    }
    .input-group-merge .form-control:focus { 
        border-color: var(--primary-navy); 
        z-index: 3; 
    }
    .input-group-merge .btn { 
        border-top-right-radius: 6px !important; 
        border-bottom-right-radius: 6px !important; 
    }
    
    .tracking-wider { letter-spacing: 0.04em; }
    .extra-small { font-size: 0.72rem; }

    .unit-code-badge {
        transition: all 0.2s ease-in-out !important;
        border-right: 1px solid var(--border-color) !important;
    }
    .unit-code-badge:hover {
        background-color: #edf3f8 !important;
        color: var(--primary-navy) !important;
    }
</style>

<?php
$content = ob_get_clean();
include "../divisions/divisionslayout.php";
?>