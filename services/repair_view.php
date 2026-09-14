<?php
require_once __DIR__ . "/../admin/auth.php";
require_once __DIR__ . "/../config/db.php";

$role = $_SESSION["role"] ?? 'User';
if (!in_array($role, ['SuperAdmin', 'Admin'], true)) {
    $_SESSION['error_msg'] = "Access Denied.";
    header("Location: index.php");
    exit;
}

$page_title = "Repair Handler & Tracking";

// Handle form actions (Mark Completed / Return to Origin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $repair_id = intval($_POST['repair_id'] ?? 0);
    $action = $_POST['action'];
    $admin_id = $_SESSION['user_id'] ?? 1;

    if ($repair_id > 0) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT * FROM repairs WHERE id = ?");
            $stmt->bind_param("i", $repair_id);
            $stmt->execute();
            $repair = $stmt->get_result()->fetch_assoc();

            if ($repair) {
                $stock_detail_id = $repair['stock_detail_id'];
                $asset_tag = $repair['division_asset_id'];

                if ($action === 'complete') {
                    $final_cost       = floatval($_POST['final_cost'] ?? 0);
                    $resolution_notes = trim($_POST['resolution_notes'] ?? 'Repair completed successfully.');

                    // Update repair record with final cost and completion status
                    $up_rep = $conn->prepare("UPDATE repairs SET status = 'completed', repair_cost = ?, issue_description = CONCAT(issue_description, ' | Resolution: ', ?) WHERE id = ?");
                    $up_rep->bind_param("dsi", $final_cost, $resolution_notes, $repair_id);
                    $up_rep->execute();

                    // Log action into asset history / remarks
                    $log_notes = "Repair Completed. Work Done: " . $resolution_notes . " | Final Cost: $" . number_format($final_cost, 2);
                    $log_stmt  = $conn->prepare("INSERT INTO asset_logs (asset_id, asset_tag, action_type, performed_by, notes) VALUES (?, ?, 'repair_completed', ?, ?)");
                    $log_stmt->bind_param("isis", $stock_detail_id, $asset_tag, $admin_id, $log_notes);
                    $log_stmt->execute();

                    $_SESSION['success_msg'] = "Repair marked as completed with logs updated.";
                } elseif ($action === 'return_origin') {
                    // Update repair status
                    $up_rep = $conn->prepare("UPDATE repairs SET status = 'returned' WHERE id = ?");
                    $up_rep->bind_param("i", $repair_id);
                    $up_rep->execute();

                    // Restore division asset status back to assigned
                    $up_da = $conn->prepare("UPDATE division_assets SET status = 'assigned' WHERE division_asset_id = ? AND stock_detail_id = ?");
                    $up_da->bind_param("si", $asset_tag, $stock_detail_id);
                    $up_da->execute();

                    // Restore stock details status back to active
                    $up_sd = $conn->prepare("UPDATE stock_details SET status = 'active' WHERE id = ?");
                    $up_sd->bind_param("i", $stock_detail_id);
                    $up_sd->execute();

                    // Log action
                    $log_stmt = $conn->prepare("INSERT INTO asset_logs (asset_id, asset_tag, action_type, performed_by, notes) VALUES (?, ?, 'repair_returned_to_origin', ?, 'Asset returned to originating division/unit after repair')");
                    $log_stmt->bind_param("isi", $stock_detail_id, $asset_tag, $admin_id);
                    $log_stmt->execute();

                    $_SESSION['success_msg'] = "Asset successfully returned to its originating division and unit.";
                }
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_msg'] = "Error: " . $e->getMessage();
        }
    }
    header("Location: repair_view.php");
    exit;
}

// Fetch all repairs with related item, division, and unit data
$query = "
    SELECT 
        r.*, 
        sd.serial_number, 
        im.item_name, 
        d.division_name, 
        un.unit_name,
        u.username as technician_name
    FROM repairs r
    JOIN stock_details sd ON r.stock_detail_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN divisions d ON r.origin_division_id = d.id
    LEFT JOIN units un ON r.origin_unit_id = un.id
    LEFT JOIN users u ON r.performed_by = u.id
    ORDER BY r.created_at DESC
";
$repairs_res = $conn->query($query);

ob_start();
?>

<div class="container-fluid py-2">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Styled Section Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center justify-content-center rounded-3 bg-light border text-primary" style="width: 42px; height: 42px; color: #123b63 !important;">
                <i class="bi bi-tools fs-5"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1" style="color: #123b63; font-size: 1.25rem;">Repair Tracking & Management</h4>
                <p class="text-muted small mb-0">Monitor internal and external repairs, track vendor assignments, and return fixed assets to origin.</p>
            </div>
        </div>
        <div>
            <a href="add_service.php" class="btn btn-primary btn-sm fw-semibold px-3 py-2" style="background-color: #123b63; border-color: #123b63;">
                <i class="bi bi-plus-lg me-1"></i> Log New Repair
            </a>
        </div>
    </div>
    <hr class="text-muted opacity-25 mb-4">

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase extra-small fw-bold">
                        <tr>
                            <th>Asset Tag / Item</th>
                            <th>Origin Location</th>
                            <th>Type & Vendor</th>
                            <th>Issue Description</th>
                            <th>Cost</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $modals_html = '';
                        if ($repairs_res && $repairs_res->num_rows > 0): 
                            while($row = $repairs_res->fetch_assoc()): 
                        ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['division_asset_id']) ?></div>
                                        <div class="text-muted extra-small"><?= htmlspecialchars($row['item_name']) ?> (S/N: <?= htmlspecialchars($row['serial_number'] ?? 'N/A') ?>)</div>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark"><?= htmlspecialchars($row['division_name'] ?? 'N/A') ?></div>
                                        <div class="text-muted extra-small"><?= htmlspecialchars($row['unit_name'] ?? 'N/A') ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                            $badge_bg = 'bg-secondary-subtle text-secondary';
                                            $type_label = ucfirst(str_replace('_', ' ', $row['repair_type']));
                                            if ($row['repair_type'] === 'internal') $badge_bg = 'bg-info-subtle text-info';
                                            elseif ($row['repair_type'] === 'external_warranty') $badge_bg = 'bg-warning-subtle text-warning';
                                            elseif ($row['repair_type'] === 'external_paid') $badge_bg = 'bg-danger-subtle text-danger';
                                        ?>
                                        <span class="badge <?= $badge_bg ?> mb-1"><?= $type_label ?></span>
                                        <?php if (!empty($row['vendor_name'])): ?>
                                            <div class="text-muted extra-small"><i class="bi bi-building me-1"></i><?= htmlspecialchars($row['vendor_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($row['issue_description']) ?>">
                                            <?= htmlspecialchars($row['issue_description']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-semibold text-dark">
                                        $<?= number_format($row['repair_cost'], 2) ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $status = $row['status'];
                                            $status_badge = 'bg-secondary-subtle text-secondary';
                                            if ($status === 'in_progress') $status_badge = 'bg-warning-subtle text-warning';
                                            elseif ($status === 'completed') $status_badge = 'bg-success-subtle text-success';
                                            elseif ($status === 'returned') $status_badge = 'bg-emerald-soft';
                                        ?>
                                        <span class="badge <?= $status_badge ?> text-uppercase" style="font-size: 10px;">
                                            <?= str_replace('_', ' ', $status) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                <?php if ($status === 'in_progress'): ?>
                                                    <li>
                                                        <button type="button" class="dropdown-item text-success fw-semibold extra-small py-2" data-bs-toggle="modal" data-bs-target="#completeModal<?= $row['id'] ?>">
                                                            <i class="bi bi-check-circle me-2"></i> Mark as Completed
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if ($status === 'completed'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="repair_id" value="<?= $row['id'] ?>">
                                                            <input type="hidden" name="action" value="return_origin">
                                                            <button type="submit" class="dropdown-item text-primary fw-semibold extra-small py-2">
                                                                <i class="bi bi-arrow-return-left me-2"></i> Return to Origin
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>

                                <?php if ($status === 'in_progress'): 
                                    // Capture modal HTML outside the table to prevent backdrop/fade issues
                                    ob_start();
                                ?>
                                <div class="modal fade text-start" id="completeModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content border-0 shadow rounded-4">
                                            <form method="POST">
                                                <div class="modal-header border-bottom-0 pb-0">
                                                    <h5 class="fw-bold text-dark fs-6">Complete Repair: <?= htmlspecialchars($row['division_asset_id']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="repair_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="action" value="complete">

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Work Done / Resolution Notes (e.g., Motherboard replaced)</label>
                                                        <textarea name="resolution_notes" class="form-control" rows="3" placeholder="Describe what was repaired or replaced..." required></textarea>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Final Repair Cost ($)</label>
                                                        <input type="number" step="0.01" name="final_cost" class="form-control" value="<?= htmlspecialchars($row['repair_cost']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-top-0 pt-0">
                                                    <button type="button" class="btn btn-light btn-sm border px-3" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success btn-sm px-3 fw-bold">Save & Mark Completed</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                    $modals_html .= ob_get_clean();
                                    endif; 
                                endwhile; 
                            else: 
                            ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-tools fs-1 opacity-25 d-block mb-2"></i>
                                    No repair records found.
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
$extra_html = $modals_html ?? '';
$content = ob_get_clean();
include "layout.php";
?>