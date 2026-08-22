<?php
require_once __DIR__ . "/../config/db.php";
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
:root {
    --erp-navy: #123b63;
    --erp-navy-dark: #0b2942;
    --erp-blue: #2b628f;
    --erp-green: #3f755e;
    --erp-amber: #9a6b22;
    --erp-red: #9a4a4a;
    --erp-info: #426f8f;
    --erp-bg: #f3f5f7;
    --erp-panel: #ffffff;
    --erp-panel-soft: #f7f9fb;
    --erp-border: #d9e0e7;
    --erp-border-dark: #c6d0da;
    --erp-text: #20384d;
    --erp-text-soft: #526679;
    --erp-muted: #718191;
    --erp-shadow: 0 1px 3px rgba(20,45,70,.05);
    --erp-shadow-hover: 0 4px 12px rgba(20,45,70,.09);
}

.container-fluid {
    max-width: 1440px;
    padding: 24px 28px 36px;
}

.dash-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 6px !important;
    background: var(--erp-panel);
    transition: all .18s ease-in-out;
    box-shadow: var(--erp-shadow) !important;
}

.extra-small { font-size: .72rem; }

.table thead th {
    font-size: 0.68rem;
    letter-spacing: 0.05em;
    font-weight: 700;
    color: var(--erp-text-soft);
    background-color: var(--erp-panel-soft) !important;
    border-bottom: 1px solid var(--erp-border) !important;
    padding-top: 10px;
    padding-bottom: 10px;
}

.table tbody td {
    border-bottom: 1px solid #edf0f3;
    padding-top: 10px;
    padding-bottom: 10px;
}

.hover-row { transition: background-color 0.15s ease; }
.hover-row:hover td { background-color: #f5f8fa !important; }

/* Custom Badge System */
.bg-amber-subtle   { background-color: #fef3c7 !important; color: #92400e !important; }
.bg-info-subtle    { background-color: #e0f2fe !important; color: #0369a1 !important; }
.bg-danger-subtle  { background-color: #fee2e2 !important; color: #991b1b !important; }
.bg-success-subtle { background-color: #dcfce7 !important; color: #15803d !important; }
.bg-neutral-subtle { background-color: #f1f5f9 !important; color: #475569 !important; }

.table-row-rejected {
    background-color: rgba(254, 226, 226, 0.25) !important;
}
.table-row-rejected:hover td {
    background-color: rgba(254, 226, 226, 0.45) !important;
}

.icon-box {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    background: #edf3f8;
    color: var(--erp-blue);
    border: 1px solid rgba(18,59,99,.08);
}
</style>

<div class="container-fluid py-0">
    <!-- Header Section -->
    <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-4 border-bottom gap-3">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--erp-navy-dark); font-size: 1.25rem;">
                Asset Audit Trail
            </h4>
            <p class="text-muted small mb-0">Historical log of all unit asset assignments and lifecycle events.</p>
        </div>
        <div>
            <span class="badge bg-white border text-secondary px-3 py-2 fw-semibold extra-small shadow-sm">
                <i class="bi bi-clock-history me-1 text-primary"></i> Live Activity Feed
            </span>
        </div>
    </div>

    <!-- Audit Log Table Card -->
    <div class="card dash-card overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4 py-3 text-uppercase">Timestamp</th>
                        <th class="py-3 text-uppercase">Asset Details</th>
                        <th class="py-3 text-uppercase">Unit / Laboratory</th>
                        <th class="py-3 text-uppercase">Action Type</th>
                        <th class="py-3 text-uppercase">Executed By</th>
                        <th class="py-3 text-uppercase">Remarks & Audit Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while($row = $logs->fetch_assoc()): 
                            $status = $row['action_type'];
                            $notes = $row['notes'] ?? '';
                            
                            $is_rejected = (stripos($notes, 'Rejected') !== false || stripos($notes, 'Deny') !== false);
                            
                            if ($is_rejected) {
                                $badge_class = 'bg-danger-subtle';
                                $status_label = "REJECTED";
                                $row_class = "table-row-rejected";
                            } else {
                                $row_class = "hover-row";
                                $status_label = strtoupper(str_replace('_', ' ', $status));
                                $badge_class = [
                                    'return_requested' => 'bg-amber-subtle',
                                    'repair_requested' => 'bg-info-subtle',
                                    'dispose_requested' => 'bg-danger-subtle',
                                    'completed'         => 'bg-success-subtle'
                                ][$status] ?? 'bg-neutral-subtle';
                            }
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td class="ps-4">
                                <div class="fw-bold text-dark extra-small"><?= date('d M, Y', strtotime($row['created_at'])) ?></div>
                                <div class="text-muted extra-small" style="font-size: 0.68rem;"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="icon-box me-2">
                                        <i class="bi <?= getAssetIcon($row['item_name']) ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark extra-small"><?= htmlspecialchars($row['item_name']) ?></div>
                                        <div class="fw-bold extra-small" style="color: var(--erp-blue); font-size: 0.65rem;">
                                            SN: <?= htmlspecialchars($row['serial_number'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="extra-small fw-bold text-dark">
                                    <?= htmlspecialchars($row['unit_name']) ?>
                                </div>
                                <div class="extra-small text-muted text-uppercase fw-bold" style="font-size: 0.62rem;">
                                    ID: <?= htmlspecialchars($row['display_tag']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= $badge_class ?>" style="font-size: 0.63rem; font-weight: 700; padding: 0.35em 0.75em;">
                                    <?= $status_label ?>
                                </span>
                            </td>
                            <td>
                                <span class="extra-small fw-semibold text-dark">
                                    <i class="bi bi-person me-1 text-muted"></i>
                                    <?= htmlspecialchars($row['staff_name'] ?: 'System') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_rejected): ?>
                                    <span class="text-danger extra-small fw-semibold d-flex align-items-center gap-1">
                                        <i class="bi bi-x-circle-fill"></i>
                                        <?= htmlspecialchars($notes) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted extra-small"><?= htmlspecialchars($notes ?: '--') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted extra-small">No asset activity logs recorded.</td>
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