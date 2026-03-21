<?php
include "../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Asset Audit Logs";
$page_icon  = "bi-clock-history";

$division_id = $_SESSION['division_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

// --- HELPERS (Added here to prevent Fatal Errors) ---
function getAssetIcon($itemName) {
    $name = strtolower($itemName);
    if (strpos($name, 'computer') !== false || strpos($name, 'desktop') !== false) return 'bi-pc-display';
    if (strpos($name, 'laptop') !== false) return 'bi-laptop';
    if (strpos($name, 'monitor') !== false) return 'bi-display';
    if (strpos($name, 'printer') !== false) return 'bi-printer';
    if (strpos($name, 'keyboard') !== false) return 'bi-keyboard';
    if (strpos($name, 'mouse') !== false) return 'bi-mouse3';
    return 'bi-box-seam';
}

// SQL Query updated to join Units
// SQL Query updated to join Permanent Stock first
$query = "
    SELECT 
        al.id as log_id,
        al.created_at, 
        al.action_type, 
        al.notes,
        im.item_name, 
        sd.serial_number,
        da.division_asset_id,
        u.username AS staff_name,
        COALESCE(un.unit_name, 'Main Stock / Returned') AS unit_name
    FROM asset_logs al
    JOIN stock_details sd ON al.asset_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN users u ON al.performed_by = u.id
    -- We join these to get the Lab/Unit name if the asset is currently assigned
    LEFT JOIN division_assets da ON sd.id = da.stock_detail_id
    LEFT JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
    LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
    LEFT JOIN units un ON dm.unit_id = un.id
    WHERE 1=1
";

// Security: Apply filters once
if ($role !== 'SuperAdmin') { 
    $query .= " AND (dm.division_id = $division_id OR al.performed_by = {$_SESSION['user_id']})"; 
}

// Group by log ID to prevent duplicate rows if an asset has multiple assignments
$query .= " GROUP BY al.id ORDER BY al.created_at DESC";

// Execute the query
$logs = $conn->query($query);

// CHECK FOR ERRORS (Temporary debugging)
if (!$logs) {
    die("Query Failed: " . $conn->error . "<br>SQL: " . $query);
}

ob_start();
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="fw-bold mb-1">Asset Audit Trail</h4>
        <p class="text-muted small">Historical record of all unit asset movements.</p>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3 text-muted small text-uppercase">Timestamp</th>
                        <th class="py-3 text-muted small text-uppercase">Asset</th>
                        <th class="py-3 text-muted small text-uppercase">Unit / Lab</th>
                        <th class="py-3 text-muted small text-uppercase">Action</th>
                        <th class="py-3 text-muted small text-uppercase">By User</th>
                        <th class="py-3 text-muted small text-uppercase">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $logs->fetch_assoc()): 
                        $status = $row['action_type'];
                        $badge_class = [
                            'return_requested' => 'bg-warning-subtle text-warning-emphasis',
                            'repair_requested' => 'bg-info-subtle text-info-emphasis',
                            'dispose_requested' => 'bg-danger-subtle text-danger-emphasis'
                        ][$status] ?? 'bg-success-subtle text-success-emphasis';
                    ?>
                    <tr>
                        <td class="ps-4 small">
                            <div class="fw-bold"><?= date('d M, Y', strtotime($row['created_at'])) ?></div>
                            <div class="text-muted opacity-75"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="bi <?= getAssetIcon($row['item_name']) ?> me-2 text-success"></i>
                                <div>
                                    <div class="fw-bold small"><?= htmlspecialchars($row['item_name']) ?></div>
                                    <div class="text-primary fw-bold" style="font-size: 0.7rem;">
                                        S/N: <?= htmlspecialchars($row['serial_number'] ?? 'N/A') ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="fw-semibold text-dark small"><?= htmlspecialchars($row['unit_name'] ?? 'Unassigned') ?></td>
                        <td>
                            <span class="badge rounded-pill <?= $badge_class ?> small">
                                <?= strtoupper(str_replace('_', ' ', $status)) ?>
                            </span>
                        </td>
                        <td class="small fw-medium"><?= htmlspecialchars($row['staff_name'] ?: 'System') ?></td>
                        <td class="text-muted italic small"><?= htmlspecialchars($row['notes'] ?: '--') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .bg-warning-subtle { background-color: #fef3c7 !important; color: #92400e !important; }
    .bg-info-subtle { background-color: #e0f2fe !important; color: #0369a1 !important; }
    .bg-danger-subtle { background-color: #fee2e2 !important; color: #991b1b !important; }
    .bg-success-subtle { background-color: #dcfce7 !important; color: #15803d !important; }
</style>

<?php 
$content = ob_get_clean();
include "../divisions/divisionslayout.php"; 
?>