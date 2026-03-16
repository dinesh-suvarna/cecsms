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

        $insert = $conn->prepare("
            INSERT INTO division_assets 
            (dispatch_detail_id, stock_detail_id, division_asset_id, assigned_by, unit_index) 
            VALUES (?, ?, ?, ?, ?)
        ");

        $insert->bind_param("iisii",
            $dispatch_detail_id,
            $stock_detail_id,
            $division_asset_id,
            $user_id,
            $unit_index
        );

        try {
    $insert->execute();

    /* ===== CHECK IF ALL UNITS ASSIGNED ===== */
    $check = $conn->prepare("
        SELECT 
            dd.quantity,
            COUNT(da.id) AS assigned
        FROM dispatch_details dd
        LEFT JOIN division_assets da 
        ON da.dispatch_detail_id = dd.id
        WHERE dd.id = ?
    ");

    $check->bind_param("i", $dispatch_detail_id);
    $check->execute();
    $res = $check->get_result()->fetch_assoc();

    if ($res['assigned'] >= $res['quantity']) {

        $update = $conn->prepare("
            UPDATE stock_details 
            SET status='assigned' 
            WHERE id=?
        ");

        $update->bind_param("i", $stock_detail_id);
        $update->execute();
        $update->close();
    }

    /* ===== SUCCESS MESSAGE ===== */
    $_SESSION['toast_message'] = "Asset assigned successfully!";
    $_SESSION['toast_type'] = "success";

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;

        } catch (mysqli_sql_exception $e) {

        if ($e->getCode() == 1062) {
            $_SESSION['toast_message'] = "Duplicate Asset ID!";
        } else {
            $_SESSION['toast_message'] = "Database error!";
        }

        $_SESSION['toast_type'] = "danger";

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    }
}

/* ================= FETCH DISPATCHED ITEMS ================= */
if ($role === 'SuperAdmin') {

    $query = "
        SELECT dd.id AS dispatch_detail_id,
               sd.id AS stock_detail_id,
               sd.serial_number,
               sd.bill_no,
               v.vendor_name,
               dm.dispatch_date,
               im.item_name,
               im.stock_type,
               dd.quantity,
               IFNULL(da_assigned.assigned_count,0) AS assigned_count
        FROM dispatch_details dd
        JOIN dispatch_master dm ON dm.id = dd.dispatch_id
        JOIN stock_details sd ON sd.id = dd.stock_detail_id
        JOIN items_master im ON sd.stock_item_id = im.id
        JOIN vendors v ON v.id = sd.vendor_id
        LEFT JOIN (
            SELECT dispatch_detail_id, COUNT(*) AS assigned_count
            FROM division_assets
            GROUP BY dispatch_detail_id
        ) da_assigned ON da_assigned.dispatch_detail_id = dd.id
        ORDER BY dm.dispatch_date DESC
    ";

    $result = $conn->query($query);

} else {

    $stmt = $conn->prepare("
        SELECT dd.id AS dispatch_detail_id,
               sd.id AS stock_detail_id,
               sd.serial_number,
               sd.bill_no,
               v.vendor_name,
               dm.dispatch_date,
               im.item_name,
               im.stock_type,
               dd.quantity,
               IFNULL(da_assigned.assigned_count,0) AS assigned_count
        FROM dispatch_details dd
        JOIN dispatch_master dm ON dm.id = dd.dispatch_id
        JOIN stock_details sd ON sd.id = dd.stock_detail_id
        JOIN items_master im ON sd.stock_item_id = im.id
        JOIN vendors v ON v.id = sd.vendor_id
        LEFT JOIN (
            SELECT dispatch_detail_id, COUNT(*) AS assigned_count
            FROM division_assets
            GROUP BY dispatch_detail_id
        ) da_assigned ON da_assigned.dispatch_detail_id = dd.id
        WHERE dm.division_id=?
        ORDER BY dm.dispatch_date DESC
    ");

    $stmt->bind_param("i", $division_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

/* ================= GROUP ITEMS ================= */
$grouped = [];

while ($row = $result->fetch_assoc()) {

    // NON-SERIAL (Bulk)
    if ($row['stock_type'] === 'non_serial') {

        $remaining = $row['quantity'] - $row['assigned_count'];

        for ($i = 1; $i <= $row['quantity']; $i++) {

            if ($i > $row['assigned_count']) {
                $rowCopy = $row;
                $rowCopy['unit_index'] = $i;
                $grouped[$row['item_name']][] = $rowCopy;
            }
        }
    }

    // SERIAL
    else {

        if ((int)$row['assigned_count'] === 0) {
            $row['unit_index'] = 0;
            $grouped[$row['item_name']][] = $row;
        }
    }
}

/* ================= TOAST MESSAGE ================= */
$toast_message = $_SESSION['toast_message'] ?? '';
$toast_type = $_SESSION['toast_type'] ?? 'success';

unset($_SESSION['toast_message'], $_SESSION['toast_type']);

ob_start();
?>


<div class="container-fluid mt-4">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
            <h5 class="fw-semibold mb-3"><i class="bi <?= $page_icon ?> me-2"></i>Assign Asset ID</h5>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="border-bottom">
                        <tr>
                            <th>Sl.No</th>
                            <th>Item</th>
                            <th>Serial / Unit</th>
                            <th>Bill No</th>
                            <th>Vendor</th>
                            <th>Dispatch Date</th>
                            <?php if ($role !== 'SuperAdmin'): ?>
                                <th>Division Asset ID</th>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sl = 1;
                        if (empty($grouped)) {
                            echo "<tr><td colspan='8' class='text-center text-muted py-4'>
                                    <i class='bi bi-check-circle me-2'></i>All dispatched items are already assigned.
                                  </td></tr>";
                        }

                        foreach ($grouped as $item_name => $items) {
                            echo "<tr class='group-header'><td colspan='8'>
                                    <i class='bi bi-box-seam me-2 text-primary'></i>"
                                    . htmlspecialchars($item_name) .
                                  "</td></tr>";
                            foreach ($items as $row) {
                        ?>
                        <tr>
                            <?php if ($role !== 'SuperAdmin'): ?>
                            <form method="POST">
                            <?php endif; ?>
                                <td><?= $sl++ ?></td>
                                <td><?= htmlspecialchars($row['item_name'] ?? '-') ?></td>
                                <td>
                                    <?= $row['stock_type'] === 'non_serial'
                                        ? "Unit " . ($row['unit_index'] ?? '-') 
                                        : htmlspecialchars($row['serial_number'] ?? '-') ?>

                                </td>
                                <td><?= htmlspecialchars($row['bill_no'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['dispatch_date'] ?? '-') ?></td>

                                <?php if ($role !== 'SuperAdmin'): ?>
                                <td style="max-width:160px;">
                                    <input type="text" name="division_asset_id" class="form-control form-control-sm" placeholder="Enter Asset ID" required>
                                    <input type="hidden" name="dispatch_detail_id" value="<?= $row['dispatch_detail_id'] ?? 0 ?>">
                                    <input type="hidden" name="stock_detail_id" value="<?= $row['stock_detail_id'] ?? 0 ?>">
                                    <input type="hidden" name="unit_index" value="<?= $row['unit_index'] ?? 0 ?>">
                                </td>
                                <td>
                                    <button type="submit" name="assign" class="btn btn-success btn-sm">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            <?php if ($role !== 'SuperAdmin'): ?>
                            </form>
                            <?php endif; ?>
                        </tr>
                        <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055">
    <div id="assetToast" class="toast align-items-center text-bg-<?= $toast_type ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"><?= htmlspecialchars($toast_message) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toastEl = document.getElementById('assetToast');
    if (toastEl && toastEl.querySelector('.toast-body').textContent.trim() !== '') {
        const bsToast = new bootstrap.Toast(toastEl);
        bsToast.show();
    }
});
</script>

<style>
.table td, .table th { font-size: 13px; white-space: nowrap; }
.group-header { background: #f8f9fa; font-weight: 600; }
</style>

<?php
$content = ob_get_clean();
include "../stock/stocklayout.php";
?>