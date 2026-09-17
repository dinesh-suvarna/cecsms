<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/crypto.php"; // Required for e_url()
include "../admin/auth.php";
include "../includes/session.php";

if (($_SESSION['role'] ?? '') !== 'SuperAdmin') {
    $_SESSION['error_msg'] = "Access Denied.";
    header("Location: ../admin/dashboard.php");
    exit;
}

// Fetch assets waiting in the queue, ordered by division name
$query = "
    SELECT 
        da.id as division_asset_id_pk,
        da.division_asset_id AS asset_tag,
        im.item_name,
        sd.serial_number,
        COALESCE(d.division_name, 'General / Unassigned Division') AS division_name,
        un.unit_name,
        un.unit_code,
        COALESCE(
            NULLIF((SELECT al.notes FROM asset_logs al WHERE al.asset_id = sd.id AND al.action_type IN ('service_requested', 'repair_requested') ORDER BY al.id DESC LIMIT 1), ''),
            NULLIF(dm.remarks, ''),
            'No remarks provided'
        ) AS original_notes
    FROM division_assets da
    JOIN stock_details sd ON da.stock_detail_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
    LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
    LEFT JOIN divisions d ON dm.division_id = d.id
    LEFT JOIN units un ON dm.unit_id = un.id
    WHERE da.status = 'under_repair'
    ORDER BY division_name ASC, da.id DESC
";
$result = $conn->query($query);

// Group results division-wise
$grouped_repairs = [];
$total_pending = 0;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $grouped_repairs[$row['division_name']][] = $row;
        $total_pending++;
    }
}

$page_title = "Pending Repair Queue";
$page_icon  = "bi-list-check";
ob_start();
?>

<style>
    /* Custom styling for solid navy active accordion headers */
    .accordion-button.collapsed {
        background-color: #ffffff;
        color: #123b63;
    }
    .accordion-button:not(.collapsed) {
        background-color: #123b63 !important;
        color: #ffffff !important;
    }
    .accordion-button:not(.collapsed)::after {
        filter: brightness(0) invert(1);
    }
    /* Custom light shade background for table headers */
    .table-custom-header th {
        background-color: #f1f5f9 !important;
        color: #123b63 !important;
        font-weight: 700;
        border-bottom: 2px solid #e2e8f0;
    }
</style>

<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0" style="color: #123b63;">
                <i class="bi bi-tools me-2" style="color: #123b63;"></i>Awaiting Repair Processing 
            </h5>
            <span class="badge px-3 py-2 text-white fw-bold" style="background-color: #f82b2b;">Total Pending: <?= $total_pending ?></span>
        </div>
    </div>

    <?php if (!empty($grouped_repairs)): ?>
        <div class="accordion shadow-sm rounded-4 overflow-hidden" id="repairDivisionAccordion">
            <?php 
            $index = 0;
            foreach ($grouped_repairs as $division_name => $items): 
                $collapse_id = "collapseDiv" . $index;
                $heading_id = "headingDiv" . $index;
            ?>
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header" id="<?= $heading_id ?>">
                        <button class="accordion-button collapsed fw-bold py-3" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#<?= $collapse_id ?>" 
                                aria-expanded="false" 
                                aria-controls="<?= $collapse_id ?>">
                            <span><i class="bi bi-building me-2"></i> <?= htmlspecialchars($division_name) ?></span>
                            <span class="badge ms-3 bg-white text-dark fw-bold border" style="font-size: 11px;">
                                <?= count($items) ?> <?= count($items) === 1 ? 'Item' : 'Items' ?>
                            </span>
                        </button>
                    </h2>
                    <div id="<?= $collapse_id ?>" 
                         class="accordion-collapse collapse" 
                         aria-labelledby="<?= $heading_id ?>" 
                         data-bs-parent="#repairDivisionAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-custom-header text-uppercase fs-7">
                                        <tr>
                                            <th class="ps-4" style="width: 28%;">Item & Asset Tag</th>
                                            <th style="width: 14%;">Serial Number</th>
                                            <th style="width: 26%;">Unit Location</th>
                                            <th style="width: 16%;">Issue / Remarks</th>
                                            <th class="text-end pe-4" style="width: 16%;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $row): ?>
                                            <tr>
                                                <td class="ps-4 py-3">
                                                    <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['item_name']) ?></div>
                                                    <div class="text-muted small text-break" style="font-size: 0.8rem;"><?= htmlspecialchars($row['asset_tag']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="small text-secondary fw-medium"><?= htmlspecialchars($row['serial_number'] ?: 'N/A') ?></span>
                                                </td>
                                                <td>
                                                    <div class="mb-1">
                                                        <span class="badge text-white fw-bold px-2 py-1" style="background-color: #123b63; font-size: 10.5px;">
                                                            <?= htmlspecialchars(strtoupper($row['unit_code'] ?? 'N/A')) ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-dark small lh-sm">
                                                        <?= htmlspecialchars($row['unit_name'] ?? 'General Unit') ?>
                                                    </div>
                                                </td>
                                                <td class="small text-muted" style="max-width: 160px;">
                                                    <?php if ($row['original_notes'] === 'No remarks provided'): ?>
                                                        <span class="text-muted fst-italic">No remarks</span>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($row['original_notes']) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="d-inline-flex align-items-center justify-content-end">
                                                        <a href="<?= e_url('repair_handler.php', $row['division_asset_id_pk'], 'token') ?>" 
                                                           class="btn btn-sm fw-bold text-nowrap text-white py-1 px-3" 
                                                           style="background-color: #123b63; border-color: #123b63; font-size: 11px;"
                                                           title="Process and Route Repair">
                                                            <i class="bi bi-tools me-1"></i> Process Repair
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                $index++;
            endforeach; 
            ?>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-check-circle display-6 d-block mb-2" style="color: #123b63;"></i>
                The repair queue is completely clear! No pending items.
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include "layout.php";
?>