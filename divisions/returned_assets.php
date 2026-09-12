<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Lifecycle Management & Audit Logs";
$page_icon  = "bi-shield-check";

$role        = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;

/* ================= HELPERS & ICONS ================= */
if (!function_exists('getAssetIcon')) {
    function getAssetIcon($itemName) {
        $name = strtolower($itemName ?? '');
        switch (true) {
            case (str_contains($name, 'computer') || str_contains($name, 'desktop')):
                return 'bi-pc-display';
            case (str_contains($name, 'laptop')):
                return 'bi-laptop';
            case (str_contains($name, 'monitor')):
                return 'bi-display';
            case (str_contains($name, 'printer')):
                return 'bi-printer';
            case (str_contains($name, 'keyboard')):
                return 'bi-keyboard';
            case (str_contains($name, 'mouse')):
                return 'bi-mouse3';
            case (str_contains($name, 'ups') || str_contains($name, 'battery')):
                return 'bi-lightning-charge';
            case (str_contains($name, 'table') || str_contains($name, 'desk')):
                return 'bi-table';
            case (str_contains($name, 'chair')):
                return 'bi-person-workspace';
            case (str_contains($name, 'camera') || str_contains($name, 'cctv')):
                return 'bi-camera-video';
            default:
                return 'bi-box-seam';
        }
    }
}

/* ================= 1. FETCH PENDING REQUESTS (TAB 1) ================= */
$pending_query = "SELECT 
            da.id,
            da.division_asset_id,
            im.item_name,
            sd.serial_number,
            d.division_name AS department,
            u.unit_code,
            u.unit_name,
            da.status,
            da.assigned_at,
            al.notes
        FROM division_assets da
        JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
        JOIN dispatch_master dm ON dd.dispatch_id = dm.id
        JOIN stock_details sd ON da.stock_detail_id = sd.id
        JOIN items_master im ON sd.stock_item_id = im.id
        JOIN divisions d ON dm.division_id = d.id
        LEFT JOIN units u ON dm.unit_id = u.id 
        LEFT JOIN asset_logs al ON al.id = (
            SELECT log_sub.id 
            FROM asset_logs log_sub 
            WHERE log_sub.asset_id = sd.id 
              AND log_sub.action_type = da.status
            ORDER BY log_sub.created_at DESC 
            LIMIT 1
        )
        WHERE da.status = 'return_requested' ";

if ($role !== 'SuperAdmin') {
    $pending_query .= " AND dm.division_id = " . intval($division_id);
}
$pending_query .= " ORDER BY da.assigned_at DESC";
$pending_res = $conn->query($pending_query);

/* ================= 2. FETCH AUDIT LOGS (TAB 2) ================= */
$logs_query = "
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
    $logs_query .= " AND (dm_active.division_id = $division_id OR dm_history.division_id = $division_id OR al.performed_by = {$_SESSION['user_id']})"; 
}

$logs_query .= " GROUP BY al.id ORDER BY al.created_at DESC";
$logs_res = $conn->query($logs_query);

// Retrieve available units for report dropdown
$units_query = "SELECT id, unit_name FROM units WHERE division_id = $division_id ORDER BY unit_name ASC";
$units_res   = $conn->query($units_query);

ob_start();
?>

<style>
    :root {
        --emerald-500: #10b981;
        --emerald-600: #059669;
        --emerald-50: #f0fdf4;
    }
    .card-custom { border: none; border-radius: 1.25rem; background: #ffffff; overflow: hidden; }
    
    /* Lifecycle Table Styling */
    .lifecycle-table thead th {
        background-color: var(--emerald-50);
        color: var(--emerald-600);
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
        font-weight: 700;
        padding: 1.2rem 1rem;
        border: none;
    }
    
    /* Audit Table Styling */
    .audit-table { font-size: 0.85rem; }
    .audit-table th {
        font-size: 0.725rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #ffffff;
        background-color: #1e293b;
        border-bottom: 2px solid #0f172a;
        padding: 0.85rem 1rem;
        vertical-align: middle;
    }
    .audit-table td { padding: 0.85rem 1rem; }

    .badge-request {
        padding: 0.5em 0.8em;
        font-weight: 700;
        font-size: 0.65rem;
        border-radius: 6px;
        display: inline-block;
    }
    .status-return { background-color: #fef3c7; color: #92400e; }
    .btn-approve { 
        background-color: #173f63; 
        color: white; 
        border: none; 
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 8px;
    }
    .btn-approve:hover { background-color: #0f2c47; color: white; }
    .swal-action-btn {
        width: 100%;
        text-align: left;
        padding: 10px 14px;
        margin-bottom: 8px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
    }
    .swal-action-btn:hover { background: #f8fafc; border-color: #173f63; }
    .btn-navy { background-color: #1e293b; color: #ffffff; border: none; }
    .btn-navy:hover { background-color: #0f172a; color: #ffffff; }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Lifecycle Management & Audit Logs</h4>
            <p class="text-muted small mb-0">Review pending department transitions and track full asset transaction history.</p>
        </div>
        <div class="bg-white px-3 py-2 rounded-3 shadow-sm border border-emerald-100">
            <span class="text-emerald-600 fw-bold small">
                <i class="bi bi-shield-check me-2"></i> <?= $pending_res->num_rows ?> Pending Requests
            </span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-3 gap-2" id="lifecycleTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold border shadow-sm px-4" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending-content" type="button" role="tab">
                <i class="bi bi-hourglass-split me-2"></i>Pending Requests (<?= $pending_res->num_rows ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold border shadow-sm px-4" id="history-tab" data-bs-toggle="pill" data-bs-target="#history-content" type="button" role="tab">
                <i class="bi bi-journal-text me-2"></i>Audit Logs Trail
            </button>
        </li>
    </ul>

    <!-- Tab Content Container -->
    <div class="tab-content" id="lifecycleTabsContent">
        
        <!-- ================= TAB 1: PENDING REQUESTS QUEUE ================= -->
        <div class="tab-pane fade show active" id="pending-content" role="tabpanel">
            <div class="card card-custom shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 lifecycle-table">
                        <thead>
                            <tr>
                                <th class="ps-4">Asset ID</th>
                                <th>Item Details</th>
                                <th>Department</th>
                                <th>Lab / Facility</th>
                                <th>Status</th>
                                <?php if ($role === 'SuperAdmin'): ?>
                                    <th class="text-end pe-4">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($pending_res->num_rows > 0): ?>
                                <?php while($row = $pending_res->fetch_assoc()): 
                                    $unit_display = "Unassigned";
                                    if (!empty($row['unit_name'])) {
                                        $code_prefix = !empty($row['unit_code']) ? strtoupper($row['unit_code']) . " - " : "";
                                        $unit_display = '<span class="fw-semibold text-dark">' . $code_prefix . htmlspecialchars($row['unit_name']) . '</span>';
                                    }
                                    $unitFullName = (!empty($row['unit_code']) ? $row['unit_code'] . " - " : "") . $row['unit_name'];
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?= $row['division_asset_id'] ?></td>
                                    <td>
                                        <div class="fw-semibold small"><?= htmlspecialchars($row['item_name']) ?></div>
                                        <div class="text-muted extra-small">SN: <?= $row['serial_number'] ?: '---' ?></div>
                                    </td>
                                    <td><div class="small text-secondary"><?= htmlspecialchars($row['department']) ?></div></td>
                                    <td><div class="small"><?= $unit_display ?></div></td>
                                    <td><span class="badge-request status-return">PENDING REVIEW</span></td>
                                    
                                    <?php if ($role === 'SuperAdmin'): ?>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-approve shadow-sm" 
                                                onclick="processItem(
                                                    '<?= $row['id'] ?>', 
                                                    '<?= $row['division_asset_id'] ?>', 
                                                    '<?= addslashes($row['item_name']) ?>', 
                                                    '<?= $row['serial_number'] ?>', 
                                                    '<?= addslashes($row['notes'] ?? "") ?>',
                                                    '<?= getAssetIcon($row['item_name']) ?>',
                                                    '<?= addslashes($unitFullName) ?>',
                                                    '<?= addslashes($row['department']) ?>'
                                                )">
                                            <i class="bi bi-gear-fill me-1"></i> Process Request
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= ($role === 'SuperAdmin') ? '6' : '5' ?>" class="text-center py-5 text-muted">No pending lifecycle requests.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================= TAB 2: PROCESSED AUDIT LOGS TRAIL ================= -->
        <div class="tab-pane fade" id="history-content" role="tabpanel">
            
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-secondary mb-0">Historical Audit Records</h6>
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

            <div class="card card-custom shadow-sm">
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
                            <?php if ($logs_res && $logs_res->num_rows > 0): ?>
                                <?php while($row = $logs_res->fetch_assoc()): 
                                    $status       = $row['action_type'];
                                    $notes        = $row['notes'] ?? '';
                                    $icon_class   = getAssetIcon($row['item_name']);
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

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function processItem(id, assetTag, itemName, serial, notes, iconClass, unitName, divisionName) {
    const displayNotes = notes ? notes : "No remarks provided by department.";
    const locationPath = `${divisionName} <i class="bi bi-chevron-right mx-1" style="font-size: 0.6rem;"></i> ${unitName}`;

    Swal.fire({
        title: '<div class="text-start fw-bold mb-0" style="font-size:1.1rem;">Lifecycle Action Request</div>',
        html: `
            <div class="text-start mt-2">
                <div class="p-3 border rounded-3 bg-light mb-3">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="bg-white p-2 rounded-3 border shadow-sm">
                            <i class="bi ${iconClass} fs-3 text-emerald-600"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">${itemName}</h6>
                            <small class="text-muted">Tag: ${assetTag} | SN: ${serial}</small>
                        </div>
                    </div>
                    
                    <div class="d-inline-flex align-items-center bg-white border rounded-pill px-3 py-1 shadow-sm mt-1">
                        <i class="bi bi-geo-alt-fill text-danger me-2" style="font-size: 0.75rem;"></i>
                        <span class="fw-bold text-dark" style="font-size: 0.7rem;">
                            ${locationPath}
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="extra-small text-uppercase fw-bold text-muted mb-1">Division Admin Reason / Justification</label>
                    <div class="p-2.5 border rounded-3 bg-white italic small text-secondary shadow-sm">
                        "${displayNotes}"
                    </div>
                </div>

                <label class="extra-small text-uppercase fw-bold text-dark mb-2">Select 1 of 3 Actions to Execute:</label>
                
                <button type="button" class="swal-action-btn" onclick="executeAction('${id}', 'return_requested')">
                    <div class="fw-bold text-success"><i class="bi bi-box-arrow-in-left me-1"></i> 1. Return to Stock</div>
                    <div class="text-muted extra-small">Restore item back into active unassigned stock.</div>
                </button>

                <button type="button" class="swal-action-btn" onclick="executeAction('${id}', 'repair_requested')">
                    <div class="fw-bold text-primary"><i class="bi bi-tools me-1"></i> 2. Send for Repair</div>
                    <div class="text-muted extra-small">Mark status as under maintenance/repair.</div>
                </button>

                <button type="button" class="swal-action-btn" onclick="executeAction('${id}', 'dispose_requested')">
                    <div class="fw-bold text-danger"><i class="bi bi-trash3 me-1"></i> 3. Scrap / Dispose</div>
                    <div class="text-muted extra-small">Decommission item and log to E-Waste registry.</div>
                </button>

                <div id="denySection" class="mt-3" style="display:none;">
                    <label class="extra-small text-uppercase fw-bold text-danger mb-1">Reason for Rejection</label>
                    <textarea id="denyReason" class="form-control form-control-sm border-danger" placeholder="Provide reason for denying request..."></textarea>
                    <button type="button" class="btn btn-sm btn-danger w-100 mt-2 fw-bold" onclick="submitRejection('${id}')">Confirm Denial</button>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        showDenyButton: true,
        denyButtonText: 'Reject Request',
        denyButtonColor: '#64748b',
        cancelButtonText: 'Close',
        preDeny: () => {
            const denySection = document.getElementById('denySection');
            if (denySection.style.display === 'none') {
                denySection.style.display = 'block';
                return false;
            }
        }
    });
}

function executeAction(id, actionType) {
    window.location.href = `process_request.php?id=${id}&action=${actionType}`;
}

function submitRejection(id) {
    const reason = document.getElementById('denyReason').value.trim();
    if (!reason) {
        Swal.showValidationMessage('Please provide a reason for rejection.');
        return;
    }
    window.location.href = `process_request.php?id=${id}&action=deny&reason=${encodeURIComponent(reason)}`;
}
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (window.location.hash === '#history-content') {
        const historyTabBtn = document.querySelector('#history-tab');
        if (historyTabBtn) {
            const tab = new bootstrap.Tab(historyTabBtn);
            tab.show();
        }
    }
});
</script>

<?php
$content = ob_get_clean();
// Check if user is SuperAdmin to load adminlayout, otherwise load divisionslayout
if (($role ?? '') === ROLE_SUPERADMIN || ($role ?? '') === 'SuperAdmin') {
    include __DIR__ . "/../admin/adminlayout.php";
} else {
    include __DIR__ . "/../divisions/divisionslayout.php";
}
?>