<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

if (($_SESSION['role'] ?? '') !== 'SuperAdmin') {
    $_SESSION['error_msg'] = "Access Denied.";
    header("Location: ../admin/dashboard.php");
    exit;
}

// Fetch only repair-related audit logs from the asset_logs table
$query = "
    SELECT al.*, im.item_name, sd.serial_number 
    FROM asset_logs al
    LEFT JOIN stock_details sd ON al.asset_id = sd.id
    LEFT JOIN items_master im ON sd.stock_item_id = im.id
    WHERE al.action_type IN ('repair_requested', 'repair_approved', 'repair_returned_to_origin')
    ORDER BY al.created_at DESC
";
$result = $conn->query($query);

$page_title = "Repair Audit Logs";
$page_icon  = "bi-clock-history";
ob_start();
?>

<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-tools me-2 text-primary"></i>Maintenance & Repair Audit Trail</h5>
            <span class="badge bg-light text-dark border px-3 py-2">Total Repair Logs: <?= $result->num_rows ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase fs-7">
                        <tr>
                            <th class="ps-4">Transaction ID | Timestamp</th>
                            <th>Asset Details</th>
                            <th>Unit / Laboratory</th>
                            <th>Lifecycle Event</th>
                            <th>Executed By</th>
                            <th class="pe-4">Remarks & Audit Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <!-- Transaction ID & Timestamp -->
                                    <td class="ps-4">
                                        <?php 
                                            // Extract TRX- ID or fallback to Log ID reference
                                            preg_match('/(TRX-\d+)/', $row['notes'], $matches);
                                            $trx_id = $matches[1] ?? ('REF-#' . $row['id']);
                                        ?>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($trx_id) ?></div>
                                        <div class="text-muted small"><?= date('d M, Y', strtotime($row['created_at'])) ?> <br><span class="extra-small"><?= date('h:i A', strtotime($row['created_at'])) ?></span></div>
                                    </td>

                                    <!-- Asset Details -->
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['item_name'] ?? 'Unknown Item') ?></div>
                                        <div class="text-secondary small font-monospace">Tag: <?= htmlspecialchars($row['asset_tag']) ?></div>
                                    </td>

                                    <!-- Unit / Location -->
                                    <td>
                                        <div class="text-dark small fw-medium"><?= htmlspecialchars($row['unit_name'] ?? 'General Unit') ?></div>
                                    </td>

                                    <!-- Lifecycle Event Badge -->
                                    <td>
                                        <?php 
                                            $badge_bg = 'bg-secondary';
                                            $event_text = ucwords(str_replace('_', ' ', $row['action_type']));
                                            if ($row['action_type'] === 'repair_requested') {
                                                $badge_bg = 'bg-warning text-dark';
                                                $event_text = 'Service Requested';
                                            } elseif ($row['action_type'] === 'repair_approved') {
                                                $badge_bg = 'bg-info text-dark';
                                                $event_text = 'Sent To Repair';
                                            } elseif ($row['action_type'] === 'repair_returned_to_origin') {
                                                $badge_bg = 'bg-success';
                                                $event_text = 'Returned to Origin';
                                            }
                                        ?>
                                        <span class="badge <?= $badge_bg ?> px-2 py-1"><?= $event_text ?></span>
                                    </td>

                                    <!-- Executed By -->
                                    <td>
                                        <div class="text-dark small">
                                            <i class="bi bi-person-fill text-muted me-1"></i> 
                                            <?= htmlspecialchars($row['performed_by'] ?? 'System') ?>
                                        </div>
                                    </td>

                                    <!-- Remarks -->
                                    <td class="pe-4 text-dark small">
                                        <?= nl2br(htmlspecialchars($row['notes'])) ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-folder2-open display-6 d-block mb-2 text-secondary"></i>
                                    No historical repair logs found in the system.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include "layout.php";
?>