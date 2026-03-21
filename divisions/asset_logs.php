<?php
include "../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Asset Audit Logs";
$page_icon  = "bi-clock-history";

$division_id = $_SESSION['division_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

/* ================= HELPERS ================= */
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

/* ================= SQL QUERY ================= */
// Enhanced to handle historical locations and "Main Stock" transitions
$query = "
    SELECT 
        al.id as log_id,
        al.created_at, 
        al.action_type, 
        al.notes,
        im.item_name, 
        sd.serial_number,
        COALESCE(da.division_asset_id, 'STOCK') AS display_tag,
        u.username AS staff_name,
        COALESCE(un.unit_name, 'Main Stock / Returned') AS unit_name
    FROM asset_logs al
    JOIN stock_details sd ON al.asset_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN users u ON al.performed_by = u.id
    LEFT JOIN division_assets da ON sd.id = da.stock_detail_id
    LEFT JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
    LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
    LEFT JOIN units un ON dm.unit_id = un.id
    WHERE 1=1
";

if ($role !== 'SuperAdmin') { 
    $query .= " AND (dm.division_id = $division_id OR al.performed_by = {$_SESSION['user_id']})"; 
}

$query .= " GROUP BY al.id ORDER BY al.created_at DESC";
$logs = $conn->query($query);

ob_start();
?>

<style>
    /* Custom Badge Colors */
    .bg-warning-subtle { background-color: #fef3c7 !important; color: #92400e !important; }
    .bg-info-subtle { background-color: #e0f2fe !important; color: #0369a1 !important; }
    .bg-danger-subtle { background-color: #fee2e2 !important; color: #991b1b !important; }
    .bg-success-subtle { background-color: #dcfce7 !important; color: #15803d !important; }

    /* Highlight Rejected Rows */
    .table-row-rejected {
        background-color: rgba(254, 226, 226, 0.3) !important; /* Very light red tint */
        transition: background-color 0.3s ease;
    }
    .table-row-rejected:hover {
        background-color: rgba(254, 226, 226, 0.6) !important;
    }
    .rejection-text {
        color: #dc3545;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .table thead th {
        font-size: 0.65rem;
        letter-spacing: 0.05em;
        font-weight: 700;
        background-color: #f8fafc;
    }
</style>

<div class="container-fluid py-4">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1">Asset Audit Trail</h4>
            <p class="text-muted small mb-0">Historical record of all unit asset movements.</p>
        </div>
        <div class="badge bg-white border text-dark px-3 py-2 rounded-3 shadow-sm">
            <i class="bi bi-filter me-2 text-primary"></i>Showing Latest Logs
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4 py-3 text-muted text-uppercase">Timestamp</th>
                        <th class="py-3 text-muted text-uppercase">Asset</th>
                        <th class="py-3 text-muted text-uppercase">Unit / Lab</th>
                        <th class="py-3 text-muted text-uppercase">Action</th>
                        <th class="py-3 text-muted text-uppercase">By User</th>
                        <th class="py-3 text-muted text-uppercase">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while($row = $logs->fetch_assoc()): 
                            $status = $row['action_type'];
                            $notes = $row['notes'] ?? '';
                            
                            // 1. Detect Rejection/Denial Logic
                            $is_rejected = (stripos($notes, 'Rejected') !== false || stripos($notes, 'Deny') !== false);
                            
                            if ($is_rejected) {
                                $badge_class = 'bg-danger-subtle text-danger-emphasis';
                                $status_label = "REJECTED";
                                $row_class = "table-row-rejected";
                            } else {
                                $row_class = "";
                                $status_label = strtoupper(str_replace('_', ' ', $status));
                                $badge_class = [
                                    'return_requested' => 'bg-warning-subtle text-warning-emphasis',
                                    'repair_requested' => 'bg-info-subtle text-info-emphasis',
                                    'dispose_requested' => 'bg-danger-subtle text-danger-emphasis',
                                    'completed'         => 'bg-success-subtle text-success-emphasis'
                                ][$status] ?? 'bg-secondary-subtle text-secondary';
                            }
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td class="ps-4 small">
                                <div class="fw-bold"><?= date('d M, Y', strtotime($row['created_at'])) ?></div>
                                <div class="text-muted opacity-75" style="font-size: 0.7rem;"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="p-2 bg-light rounded-3 me-2 border shadow-sm" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi <?= getAssetIcon($row['item_name']) ?> text-success"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold small"><?= htmlspecialchars($row['item_name']) ?></div>
                                        <div class="text-primary fw-bold" style="font-size: 0.65rem;">
                                            SN: <?= htmlspecialchars($row['serial_number'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark">
                                    <?= htmlspecialchars($row['unit_name']) ?>
                                </div>
                                <div class="extra-small text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">
                                    ID: <?= htmlspecialchars($row['display_tag']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $badge_class ?>" style="font-size: 0.65rem; font-weight: 700; padding: 0.4em 0.8em;">
                                    <?= $status_label ?>
                                </span>
                            </td>
                            <td class="small fw-medium">
                                <i class="bi bi-person-circle me-1 opacity-50"></i>
                                <?= htmlspecialchars($row['staff_name'] ?: 'System') ?>
                            </td>
                            <td class="small">
                                <?php if ($is_rejected): ?>
                                    <span class="rejection-text">
                                        <i class="bi bi-x-circle-fill"></i>
                                        <?= htmlspecialchars($notes) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted italic"><?= htmlspecialchars($notes ?: '--') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No activity logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean();
include "../divisions/divisionslayout.php"; 
?>