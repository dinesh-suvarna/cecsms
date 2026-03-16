<?php
include "../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Assigned Assets";
$page_icon  = "bi-check-circle";

$role = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;




/* ================= HANDLE ASSET ACTION ================= */

if (isset($_POST['asset_action'])) {

    $asset_id = (int)$_POST['asset_id'];
    $action   = $_POST['asset_action'];

    if ($action === "return") {
    $status = "return_requested";
}
elseif ($action === "repair") {
    $status = "repair_requested";
}
elseif ($action === "dispose") {
    $status = "dispose_requested";
}

    $stmt = $conn->prepare("
        UPDATE division_assets
        SET status = ?
        WHERE id = ?
    ");

    $stmt->bind_param("si", $status, $asset_id);
    $stmt->execute();

    $_SESSION['toast_message'] = "Asset action completed successfully!";
    $_SESSION['toast_type'] = "success";

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


/* ================= FETCH ASSIGNED DATA ================= */

$query = "
    SELECT 
        da.id,
        da.division_asset_id,
        da.unit_index,
        im.item_name,
        sd.serial_number,
        d.division_name,
        u.unit_name
    FROM division_assets da
    JOIN dispatch_details dd ON dd.id = da.dispatch_detail_id
    JOIN dispatch_master dm ON dm.id = dd.dispatch_id
    JOIN stock_details sd ON sd.id = da.stock_detail_id
    JOIN items_master im ON im.id = sd.stock_item_id
    JOIN divisions d ON d.id = dm.division_id
    LEFT JOIN units u ON u.id = dm.unit_id
    WHERE da.status = 'assigned'
";

if ($role !== 'SuperAdmin') {
    $query .= " AND dm.division_id = $division_id";
}

$query .= " ORDER BY da.assigned_at DESC";

$result = $conn->query($query);

if (!$result) {
    die("SQL Error: " . $conn->error);
}


ob_start();
?>

<div class="container-fluid mt-4">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
            <h5 class="fw-semibold mb-3">
                <i class="bi <?= $page_icon ?> me-2 text-success"></i>
                Assigned Assets
            </h5>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Sl.No</th>
                            <th>Item</th>
                            <th>Serial / Unit</th>
                            <th>division</th>
                            <th>Division Asset ID</th>
                            <?php if ($role === 'SuperAdmin'): ?>
                            <?php endif; ?>
                            <th>Unit</th>
                            <th>action</th>
                            
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sl = 1;
                        if (!$result || $result->num_rows == 0) {
                            echo "<tr>
                                    <td colspan='7' class='text-center text-muted py-4'>
                                        No assigned assets found.
                                    </td>
                                </tr>";
                        }

                        while ($row = $result->fetch_assoc()) {
                        ?>
                        <tr>
                            <form method="POST">
                                <td><?= $sl++ ?></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td>
                                    <?= $row['serial_number'] 
                                        ? htmlspecialchars($row['serial_number']) 
                                        : "Unit " . $row['unit_index'] ?>
                                </td>
                                <td><?= htmlspecialchars($row['division_name']) ?></td>
                                <td><?= htmlspecialchars($row['division_asset_id']) ?></td>
                                <td><?= htmlspecialchars($row['unit_name'] ?? '-') ?></td>
                                <td>
                                    <input type="hidden" name="asset_id" value="<?= $row['id'] ?>">
                                    <button 
type="button"
class="btn btn-sm btn-danger"
data-bs-toggle="modal"
data-bs-target="#actionModal<?= $row['id'] ?>">

<i class="bi bi-arrow-return-left"></i> Asset Action

</button>
                                </td>
                            </form>
                        </tr>
                        <!-- ASSET ACTION MODAL -->

<div class="modal fade" id="actionModal<?= $row['id'] ?>" tabindex="-1">

<div class="modal-dialog">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">
Asset Action - <?= htmlspecialchars($row['division_asset_id']) ?>
</h5>

<button type="button" class="btn-close" data-bs-dismiss="modal"></button>

</div>

<div class="modal-body text-center">

<form method="POST">

<input type="hidden" name="asset_id" value="<?= $row['id'] ?>">

<p class="mb-3">Select action for this asset</p>

<div class="d-flex justify-content-center gap-2">

<button 
type="submit"
name="asset_action"
value="return"
class="btn btn-warning">

<i class="bi bi-arrow-return-left"></i> Return to Store

</button>

<button 
type="submit"
name="asset_action"
value="repair"
class="btn btn-info">

<i class="bi bi-tools"></i> Request Repair
</button>

<button 
type="submit"
name="asset_action"
value="dispose"
class="btn btn-danger"
onclick="return confirm('Dispose this asset permanently?')">

<i class="bi bi-trash"></i> Request Dispose

</button>

</div>

</form>

</div>

</div>

</div>

</div>
                        <?php } ?>

                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../stock/stocklayout.php";
?>
