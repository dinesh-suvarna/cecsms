<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$page_title = "Assign Asset ID";
$page_icon  = "bi-tag";

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
        // Save the currently opened unit to session so it stays expanded on reload
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

            // 2. Get total original quantity vs total dispatched across ALL records
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

            // 3. Update stock status based on exhaustion of physical stock
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
if ($role === 'SuperAdmin') {
    $query = "
    SELECT
        dd.id AS dispatch_detail_id,
        sd.id AS stock_detail_id,
        sd.serial_number,
        sd.bill_no,
        v.vendor_name,
        dm.dispatch_date,
        im.item_name,
        im.stock_type,
        dd.quantity,
        u.unit_name,
        u.unit_code,
        IFNULL(da_assigned.assigned_count,0) AS assigned_count
        FROM dispatch_details dd
        JOIN dispatch_master dm ON dm.id = dd.dispatch_id
        JOIN units u ON u.id = dm.unit_id
        JOIN stock_details sd ON sd.id = dd.stock_detail_id
        JOIN items_master im ON sd.stock_item_id = im.id
        JOIN vendors v ON v.id = sd.vendor_id
        LEFT JOIN (
            SELECT dispatch_detail_id, COUNT(*) AS assigned_count FROM division_assets GROUP BY dispatch_detail_id
        ) da_assigned ON da_assigned.dispatch_detail_id = dd.id
        ORDER BY dm.dispatch_date DESC";
    $result = $conn->query($query);
} else {
    $stmt = $conn->prepare("
        SELECT
            dd.id AS dispatch_detail_id,
            sd.id AS stock_detail_id,
            sd.serial_number,
            sd.bill_no,
            v.vendor_name,
            dm.dispatch_date,
            im.item_name,
            im.stock_type,
            dd.quantity,
            u.unit_name,
            u.unit_code,
            IFNULL(da_assigned.assigned_count,0) AS assigned_count
        FROM dispatch_details dd
        JOIN dispatch_master dm ON dm.id = dd.dispatch_id
        JOIN units u ON u.id = dm.unit_id
        JOIN stock_details sd ON sd.id = dd.stock_detail_id
        JOIN items_master im ON sd.stock_item_id = im.id
        JOIN vendors v ON v.id = sd.vendor_id
        LEFT JOIN (
            SELECT dispatch_detail_id, COUNT(*) AS assigned_count FROM division_assets GROUP BY dispatch_detail_id
        ) da_assigned ON da_assigned.dispatch_detail_id = dd.id
        WHERE dm.division_id=? ORDER BY dm.dispatch_date DESC");
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
    
    if ($row['stock_type'] === 'non_serial') {
        for ($i = 1; $i <= $row['quantity']; $i++) {
            if ($i > $row['assigned_count']) {
                $rowCopy = $row; 
                $rowCopy['unit_index'] = $i;
                $grouped[$unit_code][$item_name][] = $rowCopy;
                $total_rows_count++;
            }
        }
    } else {
        if ((int)$row['assigned_count'] === 0) {
            $row['unit_index'] = 0; 
            $grouped[$unit_code][$item_name][] = $row;
            $total_rows_count++;
        }
    }
}

// Sort main multi-dimensional array keys (unit_code) in ascending order
ksort($grouped);

ob_start();
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                <span class="p-2 bg-primary-subtle text-primary rounded-3 d-inline-flex">
                    <i class="bi <?= $page_icon ?> fs-5"></i>
                </span>
                Assign Asset Identifiers
            </h4>
            <p class="text-muted small m-0 mt-1">Map localized asset numbers to dispatched inventory units organized by Unit Facility.</p>
        </div>
        <div class="search-container position-relative">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y text-muted ms-3"></i>
            <input type="text" id="assetSearch" class="form-control form-control-custom ps-5" placeholder="Filter facility or items...">
        </div>
    </div>

    <?php if (empty($grouped)): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center py-5 text-muted">
            <div class="py-4">
                <i class="bi bi-check2-circle text-success display-4 d-block mb-3"></i>
                <span class="fw-semibold d-block text-dark mb-1">All clear!</span>
                All available records are currently assigned.
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
                
                <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden unit-accordion-group" data-search-term="<?= htmlspecialchars(strtolower($unit_code . ' ' . $first_item_in_unit['unit_name'])) ?>">
                    <h2 class="accordion-header" id="heading-unit-<?= $unitIndex ?>">
                        <button class="accordion-button <?= $is_opened ? '' : 'collapsed' ?> bg-light px-4 py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-unit-<?= $unitIndex ?>" aria-expanded="<?= $is_opened ? 'true' : 'false' ?>" aria-controls="collapse-unit-<?= $unitIndex ?>">
                            <div class="w-100 me-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-building text-primary fs-5"></i>
                                    <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($unit_code) ?></span>
                                    <span class="badge bg-primary rounded-pill"><?= $total_unit_pending ?> Pending Allocation</span>
                                </div>
                                <div class="small text-muted fw-normal">
                                    <strong>Facility Name:</strong> <?= htmlspecialchars($first_item_in_unit['unit_name']) ?>
                                </div>
                            </div>
                        </button>
                    </h2>
                    
                    <div id="collapse-unit-<?= $unitIndex ?>" class="accordion-collapse collapse <?= $is_opened ? 'show' : '' ?>" aria-labelledby="heading-unit-<?= $unitIndex ?>" data-bs-parent="#unitAccordion">
                        <div class="accordion-body p-4 bg-white d-flex flex-column gap-4">
                            
                            <?php foreach ($items_by_name as $item_name => $items): 
                                // Restart SL counter for every item block type
                                $sl = 1; 

                                $lowerItem = strtolower($item_name);
                                if (str_contains($lowerItem, 'mouse')) { $itemIcon = 'bi-mouse3'; }
                                elseif (str_contains($lowerItem, 'keyboard')) { $itemIcon = 'bi-keyboard'; }
                                elseif (str_contains($lowerItem, 'computer') || str_contains($lowerItem, 'desktop') || str_contains($lowerItem, 'monitor')) { $itemIcon = 'bi-pc-display'; }
                                elseif (str_contains($lowerItem, 'printer')) { $itemIcon = 'bi-printer'; }
                                elseif (str_contains($lowerItem, 'scanner')) { $itemIcon = 'bi-qr-code-scan'; }
                                elseif (str_contains($lowerItem, 'cctv') || str_contains($lowerItem, 'camera')) { $itemIcon = 'bi-camera-video'; }
                                elseif (str_contains($lowerItem, 'ups') || str_contains($lowerItem, 'battery')) { $itemIcon = 'bi-lightning-charge'; }
                                else { $itemIcon = 'bi-box'; }
                                
                                $first = $items[0];
                                ?>
                                <div class="item-block border rounded-3 overflow-hidden">
                                    <div class="bg-light-subtle px-3 py-2 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                                        <div>
                                            <i class="bi <?= $itemIcon ?> text-secondary me-1"></i>
                                            <span class="fw-bold text-dark sub-item-title"><?= htmlspecialchars($item_name) ?></span>
                                            <span class="badge bg-secondary-subtle text-secondary border ms-1 rounded-pill"><?= count($items) ?> items</span>
                                        </div>
                                        <div class="font-xs text-muted">
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
                                                    <td class="ps-3 text-secondary font-monospace sl-cell"><?= sprintf("%02d", $sl++) ?></td>
                                                    <td>
                                                        <?php if($row['stock_type'] === 'non_serial'): ?>
                                                            <span class="badge badge-custom bg-light text-dark border-dashed"><i class="bi bi-box-seam me-1 text-muted"></i>Bulk Unit (Idx: <?= $row['unit_index'] ?>)</span>
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
                                                                    <span class="input-group-text bg-light fw-bold text-primary unit-code-badge font-xs" 
                                                                          data-bs-toggle="tooltip" 
                                                                          data-bs-placement="top" 
                                                                          title="<?= htmlspecialchars($row['unit_name']) ?>"
                                                                          style="cursor: pointer;">
                                                                        <?= htmlspecialchars($row['unit_code']) ?>
                                                                    </span>

                                                                    <input type="text" name="division_asset_id" class="form-control asset-id-input text-uppercase fw-medium" placeholder="CEC/CSE/CSL01/2026-27/01" required autocomplete="off">

                                                                    <input type="hidden" name="dispatch_detail_id" value="<?= $row['dispatch_detail_id'] ?>">
                                                                    <input type="hidden" name="stock_detail_id" value="<?= $row['stock_detail_id'] ?>">
                                                                    <input type="hidden" name="unit_index" value="<?= $row['unit_index'] ?>">
                                                                    <input type="hidden" name="opened_unit" value="<?= htmlspecialchars($unit_code) ?>">

                                                                    <button type="submit" name="assign" class="btn btn-primary px-4 fw-semibold">Assign</button>
                                                                </div>
                                                                <div class="form-text text-muted mt-1 ps-1 font-xs d-flex align-items-center gap-1">
                                                                    <i class="bi bi-info-circle-fill text-primary-subtle"></i> 
                                                                    Hover over the facility code (<span class="fw-semibold"><?= htmlspecialchars($row['unit_code']) ?></span>) to see the full facility name.
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
    .form-control-custom { border-radius: 10px; border: 1px solid #e2e8f0; padding: 0.55rem 1rem; width: 280px; transition: all 0.2s ease; font-size: 0.875rem; background: #fff; }
    .form-control-custom:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    
    .table-custom thead Th { background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 0.725rem; font-weight: 700; letter-spacing: 0.05em; padding: 0.75rem 0.75rem; }
    .table-custom tbody tr.asset-row { border-bottom: 1px solid #f1f5f9; transition: background-color 0.15s ease; }
    .table-custom tbody tr.asset-row:hover { background-color: #f8fafc; }
    .table-custom tbody td { padding: 0.75rem 0.75rem; }
    
    .accordion-button:not(.collapsed) { background-color: #eff6ff !important; color: inherit !important; box-shadow: none !important; }
    .accordion-button::after { background-size: 1.15rem; }
    .accordion-item { border: 1px solid #e2e8f0 !important; }
    .bg-light-subtle { background-color: #f8fafc; }

    .serial-badge { font-family: var(--bs-font-monospace); font-size: 0.825rem; font-weight: 700; color: #2563eb; background-color: #eff6ff; padding: 0.35rem 0.65rem; display: inline-block; border-radius: 6px; border: 1px solid #bfdbfe; }
    .badge-custom { font-size: 0.75rem; padding: 0.35rem 0.6rem; border-radius: 6px; font-weight: 500; }
    .border-dashed { border-style: dashed !important; }
    
    .input-group-merge { border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05); max-width: 500px; }
    .input-group-merge .form-control { border: 1px solid #cbd5e1; font-size: 0.875rem; }
    .input-group-merge .input-group-text { border: 1px solid #cbd5e1; background: #f8fafc; color: #64748b; min-width: 65px; justify-content: center; }
    .input-group-merge .form-control:focus { border-color: #3b82f6; z-index: 3; }
    .input-group-merge .btn { border-top-right-radius: 8px !important; border-bottom-right-radius: 8px !important; font-size: 0.875rem; }
    
    .tracking-wider { letter-spacing: 0.04em; }
    .font-xs { font-size: 0.75rem; }

    .unit-code-badge {
        transition: all 0.2s ease-in-out !important;
        border-right: 1px solid #cbd5e1 !important;
        cursor: pointer;
    }
    .unit-code-badge:hover {
        background-color: #eff6ff !important;
        color: #2563eb !important;
        border-color: #bfdbfe !important;
    }
</style>

<?php
$content = ob_get_clean();
include "../divisions/divisionslayout.php";
?>