<?php
require_once "../config/db.php";
require_once "../includes/session.php";

if (!isset($_GET['id'])) {
    header("Location: division_list.php");
    exit;
}

$division_id = intval($_GET['id']);

// Fetch division details
$stmt = $conn->prepare("
    SELECT d.*, i.institution_name
    FROM divisions d
    JOIN institutions i ON d.institution_id=i.id
    WHERE d.id=? AND d.status='Active'
");
$stmt->bind_param("i", $division_id);
$stmt->execute();
$division = $stmt->get_result()->fetch_assoc();

if (!$division) {
    header("Location: division_list.php");
    exit;
}

// Fetch related units
$unitsStmt = $conn->prepare("
    SELECT * FROM units WHERE division_id=? AND status='Active'
");
$unitsStmt->bind_param("i", $division_id);
$unitsStmt->execute();
$units = $unitsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch dispatches
$dispatchStmt = $conn->prepare("
    SELECT dm.*, i.institution_name AS inst_name, u.unit_name, sd.id AS stock_id, im.item_name, sd.quantity
    FROM dispatch_master dm
    JOIN institutions i ON dm.institution_id=i.id
    JOIN units u ON dm.unit_id=u.id
    JOIN dispatch_details dd ON dm.id=dd.dispatch_id
    JOIN stock_details sd ON dd.stock_detail_id=sd.id
    JOIN items_master im ON sd.stock_item_id=im.id
    WHERE dm.division_id=? AND dm.status='active'
    ORDER BY dm.dispatch_date DESC
");
$dispatchStmt->bind_param("i", $division_id);
$dispatchStmt->execute();
$dispatches = $dispatchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php ob_start(); ?>

<div class="container mt-4">
    <h4>Division Details - <?= htmlspecialchars($division['division_name']) ?></h4>
    <hr>

    <table class="table table-bordered w-50 mb-4">
        <tr>
            <th>Institution</th>
            <td><?= htmlspecialchars($division['institution_name']) ?></td>
        </tr>
        <tr>
            <th>Division Type</th>
            <td><?= ucfirst(htmlspecialchars($division['division_type'])) ?></td>
        </tr>
        <tr>
            <th>Total Units</th>
            <td><?= count($units) ?></td>
        </tr>
        <tr>
            <th>Total Dispatches</th>
            <td><?= count($dispatches) ?></td>
        </tr>
    </table>

    <?php if(count($units) > 0): ?>
    <h5>Units</h5>
    <table class="table table-hover table-bordered mb-4">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Unit Name</th>
                <th>Type</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($units as $index => $unit): ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($unit['unit_name']) ?></td>
                <td><?= ucfirst(htmlspecialchars($unit['unit_type'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if(count($dispatches) > 0): ?>
    <h5>Dispatches</h5>
    <div class="table-responsive">
        <table class="table table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Dispatch Date</th>
                    <th>Unit</th>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($dispatches as $index => $disp): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($disp['dispatch_date']) ?></td>
                    <td><?= htmlspecialchars($disp['unit_name']) ?></td>
                    <td><?= htmlspecialchars($disp['item_name']) ?></td>
                    <td><?= htmlspecialchars($disp['quantity']) ?></td>
                    <td><?= htmlspecialchars($disp['remarks']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted">No dispatches found for this division.</p>
    <?php endif; ?>

    <a href="division_list.php" class="btn btn-secondary mt-3">Back to Division List</a>
</div>

<?php
$content = ob_get_clean();
include "../master/masterlayout.php";
?>