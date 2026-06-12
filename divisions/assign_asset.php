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

    if (!empty($division_asset_id)) {
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
        SELECT dd.id AS dispatch_detail_id, sd.id AS stock_detail_id, sd.serial_number, sd.bill_no,
               v.vendor_name, dm.dispatch_date, im.item_name, im.stock_type, dd.quantity,
               IFNULL(da_assigned.assigned_count,0) AS assigned_count
        FROM dispatch_details dd
        JOIN dispatch_master dm ON dm.id = dd.dispatch_id
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
        SELECT dd.id AS dispatch_detail_id, sd.id AS stock_detail_id, sd.serial_number, sd.bill_no,
               v.vendor_name, dm.dispatch_date, im.item_name, im.stock_type, dd.quantity,
               IFNULL(da_assigned.assigned_count,0) AS assigned_count
        FROM dispatch_details dd
        JOIN dispatch_master dm ON dm.id = dd.dispatch_id
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

$grouped = [];
$total_rows_count = 0;
while ($row = $result->fetch_assoc()) {
    if ($row['stock_type'] === 'non_serial') {
        for ($i = 1; $i <= $row['quantity']; $i++) {
            if ($i > $row['assigned_count']) {
                $rowCopy = $row; $rowCopy['unit_index'] = $i;
                $grouped[$row['item_name']][] = $rowCopy;
                $total_rows_count++;
            }
        }
    } else {
        if ((int)$row['assigned_count'] === 0) {
            $row['unit_index'] = 0; $grouped[$row['item_name']][] = $row;
            $total_rows_count++;
        }
    }
}

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
            <p class="text-muted small m-0 mt-1">Map localized asset numbers to dispatched inventory units.</p>
        </div>
        <div class="search-container position-relative">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y text-muted ms-3"></i>
            <input type="text" id="assetSearch" class="form-control form-control-custom ps-5" placeholder="Filter parameters...">
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0" id="assetTable">
                <thead>
                    <tr class="text-uppercase tracking-wider">
                        <th class="ps-4" width="70">SL</th>
                        <th>Item Details</th>
                        <th>Serial / Unit</th>
                        <th>Bill / Vendor</th>
                        <th>Dispatch Date</th>
                        <?php if ($role !== 'SuperAdmin'): ?>
                            <th width="320">Internal Asset ID</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sl = 1;
                    if (empty($grouped)) {
                        echo "<tr><td colspan='7' class='text-center py-5 text-muted'><div class='py-4'><i class='bi bi-check2-circle text-success display-4 d-block mb-3'></i><span class='fw-semibold d-block text-dark mb-1'>All clear!</span>All available records are currently assigned.</div></td></tr>";
                    }

                    foreach ($grouped as $item_name => $items) {
                        // --- DYNAMIC ICON LOGIC ---
                        $lowerItem = strtolower($item_name);
                        if (str_contains($lowerItem, 'mouse')) { $itemIcon = 'bi-mouse3'; }
                        elseif (str_contains($lowerItem, 'keyboard')) { $itemIcon = 'bi-keyboard'; }
                        elseif (str_contains($lowerItem, 'computer') || str_contains($lowerItem, 'desktop') || str_contains($lowerItem, 'monitor')) { $itemIcon = 'bi-pc-display'; }
                        elseif (str_contains($lowerItem, 'printer')) { $itemIcon = 'bi-printer'; }
                        elseif (str_contains($lowerItem, 'scanner')) { $itemIcon = 'bi-qr-code-scan'; }
                        elseif (str_contains($lowerItem, 'cctv') || str_contains($lowerItem, 'camera')) { $itemIcon = 'bi-camera-video'; }
                        elseif (str_contains($lowerItem, 'ups') || str_contains($lowerItem, 'battery')) { $itemIcon = 'bi-lightning-charge'; }
                        else { $itemIcon = 'bi-box'; }
                        
                        echo "<tr class='group-header'><td colspan='7' class='ps-4'><div class='d-flex align-items-center gap-2 text-dark'><i class='bi " . $itemIcon . " text-primary fs-5'></i>" . htmlspecialchars($item_name) . " <span class='badge bg-light text-secondary border rounded-pill font-monospace font-sm'>" . count($items) . " items pending</span></div></td></tr>";
                        foreach ($items as $row) {
                    ?>
                    <tr class="asset-row">
                        <?php if ($role !== 'SuperAdmin'): ?>
                            <td colspan="6" class="p-0 border-0" style="display: contents;">
                                <form method="POST" class="d-contents">
                        <?php endif; ?>

                                    <td class="ps-4 text-secondary font-monospace"><?= sprintf("%02d", $sl++) ?></td>

                                    <td>
                                        <div class="text-dark"><?= htmlspecialchars($row['item_name'] ?? '-') ?></div>
                                    </td>

                                    <td>
                                        <?php if($row['stock_type'] === 'non_serial'): ?>
                                            <span class="badge badge-custom bg-light text-dark border-dashed"><i class="bi bi-box-seam me-1 text-muted"></i>Bulk Unit (Idx: <?= $row['unit_index'] ?>)</span>
                                        <?php else: ?>
                                            <span class="serial-badge text-uppercase">
                                                <?= htmlspecialchars(strtoupper($row['serial_number'] ?? '-')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="text-dark d-flex align-items-center gap-1 font-sm"><?= htmlspecialchars($row['bill_no'] ?? '-') ?></div>
                                        <div class="text-muted text-xs"><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></div>
                                    </td>

                                    <td class="font-sm text-dark">
                                        <div class="d-flex align-items-center gap-1"><?= date('d M, Y', strtotime($row['dispatch_date'])) ?></div>
                                    </td>

                                    <?php if ($role !== 'SuperAdmin'): ?>
                                        <td class="pe-4">
                                            <div class="input-group input-group-merge">
                                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-qr-code"></i></span>
                                                <input type="text" name="division_asset_id" class="form-control border-start-0 text-uppercase font-sm fw-medium" placeholder="Ex: CEC/CSE/CSL01/2021-22" required autocomplete="off">
                                                <input type="hidden" name="dispatch_detail_id" value="<?= $row['dispatch_detail_id'] ?>">
                                                <input type="hidden" name="stock_detail_id" value="<?= $row['stock_detail_id'] ?>">
                                                <input type="hidden" name="unit_index" value="<?= $row['unit_index'] ?>">
                                                <button type="submit" name="assign" class="btn btn-primary px-3 fw-semibold">
                                                    Assign
                                                </button>
                                            </div>
                                        </td>
                                    <?php endif; ?>

                        <?php if ($role !== 'SuperAdmin'): ?>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
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
    let rows = document.querySelectorAll('.asset-row');
    let headers = document.querySelectorAll('.group-header');
    
    headers.forEach(h => h.style.display = filter ? 'none' : '');
    
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>

<style>
    /* Framework Context Overrides */
    .form-control-custom { border-radius: 10px; border: 1px solid #e2e8f0; padding: 0.55rem 1rem; width: 280px; transition: all 0.2s ease; font-size: 0.875rem; background: #fff; }
    .form-control-custom:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    
    /* Table Visual Restyling Rules */
    .table-custom thead th { background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; padding: 1rem 0.75rem; }
    .table-custom tbody tr.asset-row { border-bottom: 1px solid #f1f5f9; transition: background-color 0.15s ease; }
    .table-custom tbody tr.asset-row:hover { background-color: #f8fafc; }
    .table-custom tbody td { padding: 1rem 0.75rem; }
    
    /* Document Categories / Folder Rows styling */
    .group-header { border-bottom: 1px solid #e2e8f0; }
    .group-header td { background-color: #f1f5f9 !important; padding: 0.65rem 1rem !important; font-size: 0.85rem; font-weight: 600; }
    
    /* Utility Styling Components */
    .serial-badge { font-family: var(--bs-font-monospace); font-size: 0.825rem; font-weight: 700; color: #2563eb; background-color: #eff6ff; padding: 0.35rem 0.65rem; display: inline-block; border-radius: 6px; border: 1px solid #bfdbfe; }
    .badge-custom { font-size: 0.75rem; padding: 0.35rem 0.6rem; border-radius: 6px; font-weight: 500; }
    .border-dashed { border-style: dashed !important; }
    .input-group-merge { border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .input-group-merge .form-control { border: 1px solid #cbd5e1; }
    .input-group-merge .input-group-text { border: 1px solid #cbd5e1; background: #f8fafc; color: #94a3b8; }
    .input-group-merge .form-control:focus { border-color: #3b82f6; z-index: 3; }
    .input-group-merge .btn { border-top-right-radius: 8px !important; border-bottom-right-radius: 8px !important; }
    
    /* Utility Classes */
    .font-sm { font-size: 0.85rem; }
    .text-xs { font-size: 0.75rem; }
    .tracking-wider { letter-spacing: 0.04em; }
    .d-contents { display: contents; }
</style>

<?php
$content = ob_get_clean();
include "../divisions/divisionslayout.php";
?>

n