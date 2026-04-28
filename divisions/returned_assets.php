<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Lifecycle Approvals";
$page_icon  = "bi-shield-check";


$role = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;

/* ================= HELPERS ================= */
function getAssetIcon($itemName) {
    $name = strtolower($itemName);
    
    switch (true) {
        case (strpos($name, 'computer') !== false || strpos($name, 'desktop') !== false):
            return 'bi-pc-display';
        case (strpos($name, 'laptop') !== false):
            return 'bi-laptop';
        case (strpos($name, 'monitor') !== false):
            return 'bi-display';
        case (strpos($name, 'printer') !== false):
            return 'bi-printer';
        case (strpos($name, 'keyboard') !== false):
            return 'bi-keyboard';
        case (strpos($name, 'mouse') !== false):
            return 'bi-mouse3';
        case (strpos($name, 'ups') !== false || strpos($name, 'battery') !== false):
            return 'bi-lightning-charge';
        case (strpos($name, 'table') !== false || strpos($name, 'desk') !== false):
            return 'bi-table';
        case (strpos($name, 'chair') !== false):
            return 'bi-person-workspace';
        case (strpos($name, 'camera') !== false || strpos($name, 'cctv') !== false):
            return 'bi-camera-video';
        default:
            return 'bi-box-seam'; // Fallback icon
    }
}
/* ================= FETCH REQUESTED ASSETS ================= */
$query = "SELECT 
            da.id,
            da.division_asset_id,
            im.item_name,
            sd.serial_number,
            d.division_name AS department,
            u.unit_code,
            u.unit_name,
            da.status,
            da.assigned_at,
            al.notes -- Added to fetch the remarks
        FROM division_assets da
        JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
        JOIN dispatch_master dm ON dd.dispatch_id = dm.id
        JOIN stock_details sd ON da.stock_detail_id = sd.id
        JOIN items_master im ON sd.stock_item_id = im.id
        JOIN divisions d ON dm.division_id = d.id
        LEFT JOIN units u ON dm.unit_id = u.id 
        -- Join asset_logs to get the notes for the specific requested action
        LEFT JOIN asset_logs al ON sd.id = al.asset_id AND al.action_type = da.status
        WHERE da.status IN ('return_requested', 'repair_requested', 'dispose_requested') ";

// Security: Non-SuperAdmins only see their own division's requests
if ($role !== 'SuperAdmin') {
    $query .= " AND dm.division_id = " . intval($division_id);
}

$query .= " ORDER BY da.assigned_at DESC";

$result = $conn->query($query);
ob_start();
?>

<style>
    :root {
        --emerald-500: #10b981;
        --emerald-600: #059669;
        --emerald-50: #f0fdf4;
    }
    .card-custom { border: none; border-radius: 1.25rem; background: #ffffff; overflow: hidden; }
    .table thead th {
        background-color: var(--emerald-50);
        color: var(--emerald-600);
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
        font-weight: 700;
        padding: 1.2rem 1rem;
        border: none;
    }
    .badge-request {
        padding: 0.5em 0.8em;
        font-weight: 700;
        font-size: 0.65rem;
        border-radius: 6px;
        display: inline-block;
    }
    .status-repair { background-color: #e0f2fe; color: #0369a1; }
    .status-return { background-color: #fef3c7; color: #92400e; }
    .status-dispose { background-color: #fee2e2; color: #991b1b; }
    .btn-approve { 
        background-color: var(--emerald-500); 
        color: white; 
        border: none; 
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 8px;
    }
    .btn-approve:hover { background-color: var(--emerald-600); color: white; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Lifecycle Management</h4>
            <p class="text-muted small mb-0">Reviewing assets flagged by departments for transitions.</p>
        </div>
        <div class="bg-white px-3 py-2 rounded-3 shadow-sm border border-emerald-100">
            <span class="text-emerald-600 fw-bold small">
                <i class="bi bi-tools me-2"></i> <?= $result->num_rows ?> Requests
            </span>
        </div>
    </div>

    <div class="card card-custom shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Asset ID</th>
                        <th>Item Details</th>
                        <th>Department</th>
                        <th>Lab / Facility</th>
                        <th>Request Type</th>
                        <?php if ($role === 'SuperAdmin'): ?>
                            <th class="text-end pe-4">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $status = $row['status'];
                            $label = strtoupper(str_replace('_requested', '', $status));
                            $badge_class = ($status == 'repair_requested') ? 'status-repair' : (($status == 'return_requested') ? 'status-return' : 'status-dispose');

                            $unit_display = "Unassigned";
                            if (!empty($row['unit_name'])) {
                                $code_prefix = !empty($row['unit_code']) ? strtoupper($row['unit_code']) . " - " : "";
                                $unit_display = '<span class="fw-semibold text-dark">' . $code_prefix . htmlspecialchars($row['unit_name']) . '</span>';
                            }
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark"><?= $row['division_asset_id'] ?></td>
                            <td>
                                <div class="fw-semibold small"><?= htmlspecialchars($row['item_name']) ?></div>
                                <div class="text-muted extra-small">SN: <?= $row['serial_number'] ?: '---' ?></div>
                            </td>
                            <td><div class="small text-secondary"><?= htmlspecialchars($row['department']) ?></div></td>
                            <td><div class="small"><?= $unit_display ?></div></td>
                            <td><span class="badge-request <?= $badge_class ?>"><?= $label ?></span></td>
                            
                            <?php if ($role === 'SuperAdmin'): ?>
                            <?php 
                            // Prepare the unit display string for JS
                            $unitFullName = (!empty($row['unit_code']) ? $row['unit_code'] . " - " : "") . $row['unit_name'];
                            ?>
                            <td>
                            <button type="button" class="btn btn-approve shadow-sm" 
                                    onclick="processItem(
                                        '<?= $row['id'] ?>', 
                                        '<?= $label ?>', 
                                        '<?= $row['division_asset_id'] ?>', 
                                        '<?= addslashes($row['item_name']) ?>', 
                                        '<?= $row['serial_number'] ?>', 
                                        '<?= addslashes($row['notes'] ?? "") ?>',
                                        '<?= getAssetIcon($row['item_name']) ?>',
                                        '<?= addslashes($unitFullName) ?>',
                                        '<?= addslashes($row['department']) ?>' // New: Division Name
                                    )">
                                <i class="bi bi-check2-circle me-1"></i> Process
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

function processItem(id, type, assetTag, itemName, serial, notes, iconClass, unitName, divisionName) {
    let iconColor = (type === 'REPAIR') ? '#0ea5e9' : (type === 'RETURN') ? '#10b981' : '#ef4444';
    const displayNotes = notes ? notes : "No remarks provided by department.";
    
    // Combine Division and Unit for a breadcrumb effect
    const locationPath = `${divisionName} <i class="bi bi-chevron-right mx-1" style="font-size: 0.6rem;"></i> ${unitName}`;

    Swal.fire({
        title: '<div class="text-start fw-bold mb-0">Lifecycle Action</div>',
        html: `
            <div class="text-start mt-3">
                <div class="p-3 border rounded-4 bg-light mb-3">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="bg-white p-2 rounded-3 border shadow-sm">
                            <i class="bi ${iconClass} fs-3 text-emerald-600"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">${itemName}</h6>
                            <small class="text-muted">ID: ${assetTag} | SN: ${serial}</small>
                        </div>
                    </div>
                    
                    <div class="d-inline-flex align-items-center bg-white border rounded-pill px-3 py-1 shadow-sm">
                        <i class="bi bi-geo-alt-fill text-danger me-2" style="font-size: 0.75rem;"></i>
                        <span class="fw-bold text-dark" style="font-size: 0.7rem; letter-spacing: 0.02em;">
                            ${locationPath}
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="extra-small text-uppercase fw-bold text-muted mb-1">Action Remarks / Reason</label>
                    <div class="p-3 border rounded-3 bg-white italic small text-secondary shadow-sm">
                        "${displayNotes}"
                    </div>
                </div>

                <div id="denySection" style="display:none;">
                    <label class="extra-small text-uppercase fw-bold text-danger mb-1">Reason for Rejection</label>
                    <textarea id="denyReason" class="form-control form-control-sm border-danger shadow-sm" placeholder="Enter reason to notify department..."></textarea>
                </div>
            </div>
        `,
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Approve ' + type,
        denyButtonText: 'Deny Request',
        confirmButtonColor: iconColor,
        preDeny: () => {
            const denySection = document.getElementById('denySection');
            if (denySection.style.display === 'none') {
                denySection.style.display = 'block';
                return false; 
            }
            const reason = document.getElementById('denyReason').value;
            if (!reason) {
                Swal.showValidationMessage('Please provide a reason for denial');
                return false;
            }
            return { action: 'deny', reason: reason };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `process_request.php?id=${id}&action=approve`;
        } else if (result.isDenied) {
            window.location.href = `process_request.php?id=${id}&action=deny&reason=${encodeURIComponent(result.value.reason)}`;
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include "../admin/adminlayout.php";
?>