<?php
include "../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Lifecycle Approvals";
$page_icon  = "bi-shield-check";

// Get role and ID from session
$role = $_SESSION['role'] ?? '';
$division_id = $_SESSION['division_id'] ?? 0;

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
            da.assigned_at
        FROM division_assets da
        JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
        JOIN dispatch_master dm ON dd.dispatch_id = dm.id
        JOIN stock_details sd ON da.stock_detail_id = sd.id
        JOIN items_master im ON sd.stock_item_id = im.id
        JOIN divisions d ON dm.division_id = d.id
        LEFT JOIN units u ON dm.unit_id = u.id 
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
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-approve shadow-sm" 
                                        onclick="processItem('<?= $row['id'] ?>', '<?= $label ?>', '<?= $row['division_asset_id'] ?>')">
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

<script>
function processItem(id, type, assetTag) {
    let confirmText = "";
    let iconColor = "#10b981";

    // Dynamic messaging based on request type
    if(type === 'RETURN') {
        confirmText = "Confirm that " + assetTag + " has been received and will be moved back to available stock.";
    } else if(type === 'REPAIR') {
        confirmText = "Mark " + assetTag + " for maintenance. This will lock it from further dispatch.";
        iconColor = "#0ea5e9";
    } else {
        confirmText = "Proceed with decommissioning " + assetTag + ". This move is permanent.";
        iconColor = "#ef4444";
    }

    Swal.fire({
        title: 'Approve ' + type + '?',
        text: confirmText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: iconColor,
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Process It',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        customClass: {
            popup: 'rounded-4 shadow-lg border-0',
            confirmButton: 'rounded-3 px-4 py-2 fw-bold',
            cancelButton: 'rounded-3 px-4 py-2'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Optional: Show a loading state before redirect
            Swal.fire({
                title: 'Updating Records...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading() }
            });
            window.location.href = 'process_request.php?id=' + id + '&action=approve';
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include "../stock/stocklayout.php";
?>