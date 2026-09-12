<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Asset Audit Logs";
$page_icon  = "bi-clock-history";

$division_id = $_SESSION['division_id'] ?? 0;
$role        = $_SESSION['role'] ?? '';

/* ================= ICON HELPER FUNCTIONS ================= */
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
        $cat  = strtolower(trim($category ?? ''));
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
            case (str_contains($cat, 'computer')):
                return 'bi-pc-display';
            case (str_contains($cat, 'network')):
                return 'bi-box-seam';
            case (str_contains($cat, 'biometric')):
                return 'bi-person-bounding-box';
            case (str_contains($cat, 'mobile') || str_contains($name, 'phone')):
                return 'bi-phone';
            default:
                return 'bi-box-seam';
        }
    }
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
        COALESCE(
            al.asset_tag, 
            da.division_asset_id, 
            (
                SELECT al_sub.asset_tag 
                FROM asset_logs al_sub 
                WHERE al_sub.asset_id = al.asset_id AND al_sub.asset_tag IS NOT NULL 
                ORDER BY al_sub.id DESC LIMIT 1
            ),
            'STOCK'
        ) AS display_tag,
        NULLIF(al.unit_name, '0') AS snapshot_unit,
        un_active.unit_name AS active_unit,
        un_history.unit_name AS history_unit,
        u.username AS staff_name,
        u.role AS user_role
    FROM asset_logs al
    JOIN stock_details sd ON al.asset_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN users u ON al.performed_by = u.id
    LEFT JOIN division_assets da ON sd.id = da.stock_detail_id
    LEFT JOIN dispatch_details dd_active ON da.dispatch_detail_id = dd_active.id
    LEFT JOIN dispatch_master dm_active ON dd_active.dispatch_id = dm_active.id
    LEFT JOIN units un_active ON dm_active.unit_id = un_active.id
    LEFT JOIN dispatch_details dd_history ON sd.id = dd_history.stock_detail_id
    LEFT JOIN dispatch_master dm_history ON dd_history.dispatch_id = dm_history.id
    LEFT JOIN units un_history ON dm_history.unit_id = un_history.id
    WHERE 1=1
";

if ($role !== 'SuperAdmin') { 
    $query .= " AND (dm_active.division_id = $division_id OR dm_history.division_id = $division_id OR al.performed_by = {$_SESSION['user_id']})"; 
}

$query .= " GROUP BY al.id ORDER BY al.created_at DESC";
$logs = $conn->query($query);

// Retrieve available units for unit-wise report filter dropdown
$units_query = "SELECT id, unit_name FROM units WHERE division_id = $division_id ORDER BY unit_name ASC";
$units_res   = $conn->query($units_query);

ob_start();
?>

<style>
  .audit-table {
    font-size: 0.85rem;
  }
  /* ERP Navy Header Theme */
  .audit-table th {
    font-size: 0.725rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #ffffff;
    background-color: #1e293b; /* Corporate ERP Dark Navy */
    border-bottom: 2px solid #0f172a;
    padding: 0.85rem 1rem;
    vertical-align: middle;
  }
  .audit-table td {
    padding: 0.85rem 1rem;
  }
  .btn-navy {
    background-color: #1e293b;
    color: #ffffff;
    border: none;
  }
  .btn-navy:hover, .btn-navy:focus {
    background-color: #0f172a;
    color: #ffffff;
  }
</style>

<div class="container-fluid py-0">
    <!-- Top Action Bar for Generate Report -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
        <h6 class="fw-bold text-secondary mb-0">Audit Logs Trail</h6>
        <div class="dropdown">
            <button class="btn btn-navy btn-sm dropdown-toggle shadow-sm d-flex align-items-center gap-2" type="button" id="reportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-file-earmark-arrow-down-fill"></i> Generate Report
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="reportDropdown">
                <li><h6 class="dropdown-header text-uppercase extra-small">Export Options</h6></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="generate_audit_report.php?type=full">
                        <i class="bi bi-building text-primary"></i> Full Division Report
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header text-uppercase extra-small">Unit-Wise Report</h6></li>
                <?php if ($units_res && $units_res->num_rows > 0): ?>
                    <?php while($u = $units_res->fetch_assoc()): ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="generate_audit_report.php?type=unit&unit_id=<?= $u['id'] ?>">
                                <i class="bi bi-geo-alt text-secondary"></i> <?= htmlspecialchars($u['unit_name']) ?>
                            </a>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li><span class="dropdown-item text-muted small">No units assigned</span></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="card dash-card border-0 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table audit-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Transaction ID | Timestamp</th>
                        <th>Asset Details</th>
                        <th>Unit / Laboratory</th>
                        <th class="text-center">Status / Lifecycle Event</th>
                        <th class="text-center">Executed By</th>
                        <th>Remarks & Audit Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while($row = $logs->fetch_assoc()): 
                            $status       = $row['action_type'];
                            $notes        = $row['notes'] ?? '';
                            $cat_name     = ''; 
                            $icon_class   = getItemDetailIcon($row['item_name'], $cat_name);
                            $final_unit   = $row['snapshot_unit'] ?? $row['active_unit'] ?? $row['history_unit'] ?? 'Main Stock / Returned';
                            
                            if (preg_match('/\[REF:#(\d+)\]\s*/', $notes, $matches)) {
                                $ref_id = "TRX-" . str_pad($matches[1], 5, '0', STR_PAD_LEFT);
                                $clean_notes = preg_replace('/\[REF:#\d+\]\s*/', '', $notes);
                            } else {
                                $ref_id = "TRX-" . str_pad($row['log_id'], 5, '0', STR_PAD_LEFT);
                                $clean_notes = $notes;
                            }
                            
                            switch ($status) {
                                case 'return_requested':
                                    $status_label = "SERVICE REQUESTED";
                                    $badge_class  = "bg-warning-subtle text-warning-emphasis border-warning-subtle";
                                    break;
                                case 'repair_requested':
                                case 'repair_approved':
                                    $status_label = "SENT TO REPAIR";
                                    $badge_class  = "bg-info-subtle text-info-emphasis border-info-subtle";
                                    break;
                                case 'dispose_requested':
                                case 'disposal_approved':
                                    $status_label = "DECOMMISSIONED";
                                    $badge_class  = "bg-danger-subtle text-danger border-danger-subtle";
                                    break;
                                case 'return_approved':
                                case 'completed':
                                    $status_label = "RETURNED TO STOCK";
                                    $badge_class  = "bg-success-subtle text-success-emphasis border-success-subtle";
                                    break;
                                case 'request_rejected':
                                case 'return_rejected':
                                    $status_label = "REQUEST REJECTED";
                                    $badge_class  = "bg-danger-subtle text-danger border-danger-subtle";
                                    break;
                                default:
                                    $status_label = !empty($status) ? strtoupper(str_replace('_', ' ', $status)) : "N/A";
                                    $badge_class  = "bg-secondary-subtle text-secondary-emphasis border-secondary-subtle";
                                    break;
                            }
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div><span class="fw-medium text-dark"><?= $ref_id ?></span></div>
                                <div class="fw-medium text-dark mt-1"><?= date('d M, Y', strtotime($row['created_at'])) ?></div>
                                <div class="text-muted small"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="icon-box me-2">
                                        <i class="bi <?= $icon_class ?> fs-5 text-secondary"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($row['item_name']) ?></div>
                                        <div class="fw-semibold text-primary small">
                                            SN: <?= htmlspecialchars($row['serial_number'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-medium text-dark">
                                    <?= htmlspecialchars($final_unit) ?>
                                </div>
                                <div class="small text-muted">
                                    ID: <?= htmlspecialchars($row['display_tag']) ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge border <?= $badge_class ?> text-uppercase px-2 py-1" style="font-size: 0.65rem; font-weight: 700;">
                                    <?= $status_label ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center text-secondary">
                                    <i class="bi bi-person me-1"></i>
                                    <span class="fw-medium text-dark"><?= htmlspecialchars($row['staff_name'] ?: 'System') ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted small"><?= htmlspecialchars($clean_notes ?: 'No notes recorded.') ?></span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No audit logs found.</td>
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