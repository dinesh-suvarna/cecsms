<?php
include "../config/db.php";
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
    $division_asset_id  = trim($_POST['division_asset_id'] ?? '');
    $unit_index         = (int)($_POST['unit_index'] ?? 0);

    if (!empty($division_asset_id)) {
        $conn->begin_transaction(); // Use transaction to ensure both tables update
        try {
            $insert = $conn->prepare("
                INSERT INTO division_assets 
                (dispatch_detail_id, stock_detail_id, division_asset_id, assigned_by, unit_index) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insert->bind_param("iisii", $dispatch_detail_id, $stock_detail_id, $division_asset_id, $user_id, $unit_index);
            $insert->execute();

            /* ===== CHECK TOTAL PROGRESS FOR THIS DISPATCH ===== */
            // We count how many units are now in division_assets for this dispatch_detail_id
            $check = $conn->prepare("
                SELECT dd.quantity, COUNT(da.id) AS assigned_count
                FROM dispatch_details dd
                LEFT JOIN division_assets da ON da.dispatch_detail_id = dd.id
                WHERE dd.id = ?
            ");
            $check->bind_param("i", $dispatch_detail_id);
            $check->execute();
            $res = $check->get_result()->fetch_assoc();

            /* ===== UPDATE STOCK STATUS ===== */
            // If it's a serialized item, quantity is 1, so it will trigger immediately.
            // If it's bulk, it triggers once the last unit is given an ID.
            if ($res['assigned_count'] >= $res['quantity']) {
                $update = $conn->prepare("UPDATE stock_details SET status='dispatched' WHERE id=?");
                $update->bind_param("i", $stock_detail_id);
                $update->execute();
            }

            $conn->commit();

            $_SESSION['swal_type'] = "success";
            $_SESSION['swal_msg']  = "Asset $division_asset_id assigned successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $_SESSION['swal_type'] = "error";
            $_SESSION['swal_msg']  = ($e->getCode() == 1062) ? "Duplicate Asset ID: $division_asset_id exists!" : "Database error: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
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
while ($row = $result->fetch_assoc()) {
    if ($row['stock_type'] === 'non_serial') {
        for ($i = 1; $i <= $row['quantity']; $i++) {
            if ($i > $row['assigned_count']) {
                $rowCopy = $row; $rowCopy['unit_index'] = $i;
                $grouped[$row['item_name']][] = $rowCopy;
            }
        }
    } else {
        if ((int)$row['assigned_count'] === 0) {
            $row['unit_index'] = 0; $grouped[$row['item_name']][] = $row;
        }
    }
}

ob_start();
?>

<div class="container-fluid mt-4">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0"><i class="bi <?= $page_icon ?> me-2 text-primary"></i>Assign Asset ID</h5>
                <div class="input-group input-group-sm w-25">
                    <span class="input-group-text bg-white border-end-0 rounded-pill-start"><i class="bi bi-search"></i></span>
                    <input type="text" id="assetSearch" class="form-control border-start-0 rounded-pill-end" placeholder="Search item or serial...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="assetTable">
                    <thead class="bg-light">
                        <tr class="small text-muted text-uppercase">
                            <th class="ps-3">Sl</th>
                            <th>Item Details</th>
                            <th>Serial / Unit</th>
                            <th>Bill / Vendor</th>
                            <th>Dispatch Date</th>
                            <?php if ($role !== 'SuperAdmin'): ?>
                                <th width="200">Internal Asset ID</th>
                                <th class="text-end pe-3">Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sl = 1;
                        if (empty($grouped)) {
                            echo "<tr><td colspan='8' class='text-center py-5 text-muted'><i class='bi bi-check-circle-fill text-success d-block mb-2' style='font-size:2rem;'></i>All items are already assigned.</td></tr>";
                        }

                        foreach ($grouped as $item_name => $items) {
                            echo "<tr class='group-header'><td colspan='8' class='bg-primary-subtle text-primary fw-bold small ps-3'><i class='bi bi-folder2-open me-2'></i>" . htmlspecialchars($item_name) . "</td></tr>";
                            foreach ($items as $row) {
                        ?>
                        <tr class="asset-row">
                            <?php if ($role !== 'SuperAdmin'): ?><form method="POST"><?php endif; ?>
                                <td class="ps-3 text-muted"><?= $sl++ ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($row['item_name'] ?? '-') ?></td>
                                <td>
                                    <?php if($row['stock_type'] === 'non_serial'): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Unit <?= $row['unit_index'] ?></span>
                                    <?php else: ?>
                                        <code class="text-dark fw-bold"><?= htmlspecialchars($row['serial_number'] ?? '-') ?></code>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">#<?= htmlspecialchars($row['bill_no'] ?? '-') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></div>
                                </td>
                                <td class="small text-muted"><?= date('d M Y', strtotime($row['dispatch_date'])) ?></td>

                                <?php if ($role !== 'SuperAdmin'): ?>
                                <td>
                                    <input type="text" name="division_asset_id" class="form-control form-control-sm border-primary-subtle rounded-3" placeholder="Enter ID..." required>
                                    <input type="hidden" name="dispatch_detail_id" value="<?= $row['dispatch_detail_id'] ?>">
                                    <input type="hidden" name="stock_detail_id" value="<?= $row['stock_detail_id'] ?>">
                                    <input type="hidden" name="unit_index" value="<?= $row['unit_index'] ?>">
                                </td>
                                <td class="text-end pe-3">
                                    <button type="submit" name="assign" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                                        <i class="bi bi-plus-lg me-1"></i>Assign
                                    </button>
                                </td>
                            </form><?php endif; ?>
                        </tr>
                        <?php } } ?>
                    </tbody>
                </table>
            </div>
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
    document.querySelectorAll('.asset-row').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>

<style>
    .group-header { height: 35px; vertical-align: middle; }
    .asset-row td { font-size: 0.85rem; padding: 12px 8px; }
    input[name="division_asset_id"]:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.2rem rgba(13,110,253,.15); }
    .table-hover tbody tr.asset-row:hover { background-color: #fcfdfe; }
</style>

<?php
$content = ob_get_clean();
include "../divisions/divisionslayout.php";
?>