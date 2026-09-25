<?php
// repair_view.php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

if (!in_array($_SESSION['role'] ?? '', ['SuperAdmin', 'Admin'], true)) {
    $_SESSION['error_msg'] = "Access Denied.";
    header("Location: ../admin/dashboard.php");
    exit;
}

// --- HANDLE ACTIONS (MARK COMPLETED / RETURN TO ORIGIN / RETURN TO MAIN STOCK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $repair_id = intval($_POST['repair_id'] ?? 0);
    $action    = $_POST['action'];
    $admin_id  = $_SESSION['user_id'] ?? 1;

    if ($repair_id > 0) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT * FROM repairs WHERE id = ?");
            $stmt->bind_param("i", $repair_id);
            $stmt->execute();
            $repair = $stmt->get_result()->fetch_assoc();

            if ($repair) {
                $stock_detail_id    = $repair['stock_detail_id'];
                $asset_tag          = $repair['division_asset_id'];
                $origin_division_id = $repair['origin_division_id'];

               if ($action === 'complete') {
                    $final_cost       = floatval($_POST['final_cost'] ?? 0);
                    $resolution_notes = trim($_POST['resolution_notes'] ?? 'Repair completed.');
                    $bill_number      = trim($_POST['bill_number'] ?? ''); 

                    // 1. Update the repair ticket to completed status
                    $up_rep = $conn->prepare("
                        UPDATE repairs 
                        SET status = 'completed', repair_cost = ?, resolution_notes = ?, completed_at = NOW() 
                        WHERE id = ?
                    ");
                    $up_rep->bind_param("dsi", $final_cost, $resolution_notes, $repair_id);
                    $up_rep->execute();
                    $up_rep->close();

                    // 2. Fetch repair info including stock_detail_id and repair_type
                    $get_details = $conn->prepare("
                        SELECT r.stock_detail_id, im.item_name, r.vendor_name, r.repair_type 
                        FROM repairs r
                        JOIN stock_details sd ON r.stock_detail_id = sd.id
                        JOIN items_master im ON sd.stock_item_id = im.id
                        WHERE r.id = ?
                    ");
                    $get_details->bind_param("i", $repair_id);
                    $get_details->execute();
                    $repair_info = $get_details->get_result()->fetch_assoc();
                    $get_details->close();

                    if ($repair_info && in_array($repair_info['repair_type'], ['external_paid', 'external_warranty'])) {
                        $item_name    = $repair_info['item_name']; 
                        $stock_id     = $repair_info['stock_detail_id'];
                        $vendor_name  = $repair_info['vendor_name'];
                        $service_type = ($repair_info['repair_type'] === 'external_paid') ? 'EXTERNAL PAID' : 'EXTERNAL WARRANTY';
                        $bill_status  = ($repair_info['repair_type'] === 'external_paid') ? 'Unpaid' : 'Warranty';

                        // 3. Look up vendor ID
                        $vendor_id = null;
                        if (!empty($vendor_name)) {
                            $v_stmt = $conn->prepare("SELECT id FROM vendors WHERE vendor_name = ?");
                            $v_stmt->bind_param("s", $vendor_name);
                            $v_stmt->execute();
                            $v_res = $v_stmt->get_result()->fetch_assoc();
                            if ($v_res) {
                                $vendor_id = (int)$v_res['id'];
                            }
                            $v_stmt->close();
                        }

                        // 4. Insert into centralized 'services' table with resolution notes & stock mapping
                        $ins_service = $conn->prepare("
                            INSERT INTO services (vendor_id, stock_detail_id, service_date, item_name, service_type, amount, bill_status, bill_number, resolution_notes, created_at) 
                            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $ins_service->bind_param("iissdsss", 
                            $vendor_id, 
                            $stock_id,
                            $item_name, 
                            $service_type, 
                            $final_cost, 
                            $bill_status,
                            $bill_number,
                            $resolution_notes
                        );
                        $ins_service->execute();
                        $ins_service->close();
                    }

                    $_SESSION['success_msg'] = "Repair completed and successfully logged into service records!";


                } elseif ($action === 'return_origin') {
                    // 1. Update repair status to returned
                    $up_rep = $conn->prepare("UPDATE repairs SET status = 'returned' WHERE id = ?");
                    $up_rep->bind_param("i", $repair_id);
                    $up_rep->execute();

                    // 2. Restore asset status back to assigned/active pool
                    $up_da = $conn->prepare("UPDATE division_assets SET status = 'assigned' WHERE division_asset_id = ? AND stock_detail_id = ?");
                    $up_da->bind_param("si", $asset_tag, $stock_detail_id);
                    $up_da->execute();

                    $up_sd = $conn->prepare("UPDATE stock_details SET status = 'assigned' WHERE id = ?");
                    $up_sd->bind_param("i", $stock_detail_id);
                    $up_sd->execute();

                    // 3. Fetch original transaction reference for audit trail consistency
                    $trx_stmt = $conn->prepare("SELECT notes FROM asset_logs WHERE asset_id = ? AND asset_tag = ? ORDER BY created_at ASC LIMIT 1");
                    $trx_stmt->bind_param("is", $stock_detail_id, $asset_tag);
                    $trx_stmt->execute();
                    $trx_res = $trx_stmt->get_result()->fetch_assoc();
                    
                    $existing_ref = "";
                    if ($trx_res && preg_match('/(TRX-\d+|\[.*?#\d+\])/', $trx_res['notes'], $matches)) {
                        $existing_ref = $matches[1] . " ";
                    }

                    // 4. Insert final concluding audit log entry
                    $log_notes = $existing_ref . "Asset returned to originating division/unit after repair";
                    $log_stmt = $conn->prepare("INSERT INTO asset_logs (asset_id, asset_tag, action_type, performed_by, notes) VALUES (?, ?, 'repair_returned_to_origin', ?, ?)");
                    $log_stmt->bind_param("isis", $stock_detail_id, $asset_tag, $admin_id, $log_notes);
                    $log_stmt->execute();

                    $_SESSION['success_msg'] = "Asset successfully returned to its originating division and unit.";

                } elseif ($action === 'return_main_stock') {
                    // 1. Update repair status to returned
                    $up_rep = $conn->prepare("UPDATE repairs SET status = 'returned' WHERE id = ?");
                    $up_rep->bind_param("i", $repair_id);
                    $up_rep->execute();

                    // 2. Clear assignment from division_assets (mark as returned to stock / inactive for that division tag)
                    $up_da = $conn->prepare("UPDATE division_assets SET status = 'returned_to_stock' WHERE division_asset_id = ? AND stock_detail_id = ?");
                    $up_da->bind_param("si", $asset_tag, $stock_detail_id);
                    $up_da->execute();

                    // 3. Return stock_details back to available main inventory pool
                    $up_sd = $conn->prepare("UPDATE stock_details SET status = 'available' WHERE id = ?");
                    $up_sd->bind_param("i", $stock_detail_id);
                    $up_sd->execute();

                    // 4. Insert audit log entry for returning to main stock
                    $log_notes = "Asset returned to main stock pool after repair (replacement already provided to division)";
                    $log_stmt = $conn->prepare("INSERT INTO asset_logs (asset_id, asset_tag, action_type, performed_by, notes) VALUES (?, ?, 'repair_returned_to_main_stock', ?, ?)");
                    $log_stmt->bind_param("isis", $stock_detail_id, $asset_tag, $admin_id, $log_notes);
                    $log_stmt->execute();

                    $_SESSION['success_msg'] = "Asset successfully returned to Main Stock inventory.";
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

// Fetch active and completed repairs ordered by division name
$query = "
    SELECT 
        r.*, 
        sd.serial_number, 
        im.item_name, 
        COALESCE(d.division_name, 'General / Unassigned Division') AS division_name, 
        un.unit_name,
        un.unit_code,
        u.username as technician_name
    FROM repairs r
    JOIN stock_details sd ON r.stock_detail_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN divisions d ON r.origin_division_id = d.id
    LEFT JOIN units un ON r.origin_unit_id = un.id
    LEFT JOIN users u ON r.performed_by = u.id
    WHERE r.status IN ('in_progress', 'completed')
    ORDER BY division_name ASC, r.created_at DESC
";
$repairs_res = $conn->query($query);

// Separate results into In Progress vs Completed groups
$in_progress_grouped = [];
$completed_grouped = [];
$count_in_progress = 0;
$count_completed = 0;

if ($repairs_res && $repairs_res->num_rows > 0) {
    while ($row = $repairs_res->fetch_assoc()) {
        if ($row['status'] === 'in_progress') {
            $in_progress_grouped[$row['division_name']][] = $row;
            $count_in_progress++;
        } elseif ($row['status'] === 'completed') {
            $completed_grouped[$row['division_name']][] = $row;
            $count_completed++;
        }
    }
}

$page_title = "Repair Management & Resolution";
$page_icon  = "bi-tools";
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
    /* Custom styling for tabs */
    .nav-pills .nav-link.active {
        background-color: #123b63 !important;
        color: #ffffff !important;
    }
    .nav-pills .nav-link {
        color: #123b63;
        font-weight: 600;
    }
</style>

<div class="container-fluid py-3">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Header & Navigation Tabs -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <h5 class="fw-bold mb-0" style="color: #123b63;">
                <i class="bi bi-tools me-2" style="color: #123b63;"></i>Repair Tickets Dashboard
            </h5>
            
            <!-- Tabs Navigation -->
            <ul class="nav nav-pills gap-2" id="repairTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-4 py-2" id="inprogress-tab" data-bs-toggle="pill" data-bs-target="#inprogress-pane" type="button" role="tab" aria-controls="inprogress-pane" aria-selected="true">
                        <i class="bi bi-hourglass-split me-1"></i> In Progress 
                        <span class="badge bg-white text-dark ms-2 border"><?= $count_in_progress ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-4 py-2 border bg-light text-dark" id="completed-tab" data-bs-toggle="pill" data-bs-target="#completed-pane" type="button" role="tab" aria-controls="completed-pane" aria-selected="false">
                        <i class="bi bi-check2-all me-1 text-success"></i> Completed / Pending Return 
                        <span class="badge bg-success text-white ms-2"><?= $count_completed ?></span>
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="repairTabsContent">
        
        <!-- TAB 1: IN PROGRESS REPAIRS -->
        <div class="tab-pane fade show active" id="inprogress-pane" role="tabpanel" aria-labelledby="inprogress-tab" tabindex="0">
            <?php 
            $modals_html = '';
            if (!empty($in_progress_grouped)): 
            ?>
                <div class="accordion shadow-sm rounded-4 overflow-hidden" id="inProgressAccordion">
                    <?php 
                    $index = 0;
                    foreach ($in_progress_grouped as $division_name => $items): 
                        $collapse_id = "collapseIPDiv" . $index;
                        $heading_id = "headingIPDiv" . $index;
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
                                        <?= count($items) ?> <?= count($items) === 1 ? 'Ticket' : 'Tickets' ?>
                                    </span>
                                </button>
                            </h2>
                            <div id="<?= $collapse_id ?>" 
                                 class="accordion-collapse collapse" 
                                 aria-labelledby="<?= $heading_id ?>" 
                                 data-bs-parent="#inProgressAccordion">
                                <div class="accordion-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-custom-header text-uppercase fs-7">
                                                <tr>
                                                    <th class="ps-4" style="width: 25%;">Item &amp; Asset Tag</th>
                                                    <th style="width: 18%;">Origin Location</th>
                                                    <th style="width: 15%;">Type &amp; Vendor</th>
                                                    <th style="width: 20%;">Issue Description</th>
                                                    <th style="width: 10%;">Cost</th>
                                                    <th style="width: 10%;">Status</th>
                                                    <th class="text-end pe-4" style="width: 12%;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $row): ?>
                                                    <tr>
                                                        <td class="ps-4 py-3">
                                                            <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['item_name']) ?></div>
                                                            <div class="text-muted small text-break fw-semibold" style="font-size: 0.8rem;"><?= htmlspecialchars($row['division_asset_id']) ?></div>
                                                            <div class="text-secondary" style="font-size: 0.75rem;">S/N: <?= htmlspecialchars($row['serial_number'] ?: 'N/A') ?></div>
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
                                                        <td>
                                                            <?php 
                                                                $badge_bg = 'bg-secondary-subtle text-secondary';
                                                                $type_label = ucfirst(str_replace('_', ' ', $row['repair_type']));
                                                                if ($row['repair_type'] === 'internal') $badge_bg = 'bg-info-subtle text-info';
                                                                elseif ($row['repair_type'] === 'external_warranty') $badge_bg = 'bg-warning-subtle text-warning';
                                                                elseif ($row['repair_type'] === 'external_paid') $badge_bg = 'bg-danger-subtle text-danger';
                                                            ?>
                                                            <span class="badge <?= $badge_bg ?> mb-1"><?= $type_label ?></span>
                                                            <div class="small fw-medium text-secondary"><?= htmlspecialchars($row['vendor_name'] ?: 'Internal Tech') ?></div>
                                                        </td>
                                                        <td class="small text-muted" style="max-width: 200px;">
                                                            <div><?= htmlspecialchars($row['issue_description']) ?></div>
                                                        </td>
                                                        <td class="fw-semibold text-dark">
                                                            ₹<?= number_format($row['repair_cost'], 2) ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-warning-subtle text-warning text-uppercase fw-bold" style="font-size: 10px;">
                                                                In Progress
                                                            </span>
                                                        </td>
                                                        <td class="text-end pe-4">
                                                            <button type="button" class="btn btn-sm btn-success fw-bold text-nowrap py-1 px-3" style="font-size: 11px;" data-bs-toggle="modal" data-bs-target="#completeModal<?= $row['id'] ?>">
                                                                <i class="bi bi-check-circle me-1"></i> Resolve &amp; Close
                                                            </button>
                                                        </td>
                                                    </tr>

                                                    <?php 
                                                        ob_start();
                                                    ?>
                                                    <div class="modal fade" id="completeModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content border-0 shadow rounded-4">
                                                                <form method="POST">
                                                                    <div class="modal-header border-0 pb-0">
                                                                        <h5 class="fw-bold text-dark">Log Repair Resolution</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body text-start">
                                                                        <input type="hidden" name="repair_id" value="<?= $row['id'] ?>">
                                                                        <input type="hidden" name="action" value="complete">

                                                                        <div class="mb-3 p-3 bg-light rounded-3">
                                                                            <div class="small text-muted">Asset Tag: <strong><?= htmlspecialchars($row['division_asset_id']) ?></strong></div>
                                                                            <div class="small text-muted">Item: <strong><?= htmlspecialchars($row['item_name']) ?></strong></div>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold small">Actions Taken / Resolution Notes <span class="text-danger">*</span></label>
                                                                            <textarea name="resolution_notes" class="form-control" rows="3" placeholder="e.g., Replaced hard drive and reloaded OS. Tested OK." required></textarea>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold small">Final Cost (₹)</label>
                                                                            <input type="number" step="0.01" name="final_cost" class="form-control" value="<?= htmlspecialchars($row['repair_cost']) ?>">
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer border-0 pt-0">
                                                                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-success fw-bold px-4">Complete &amp; Close Ticket</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php 
                                                        $modals_html .= ob_get_clean();
                                                    ?>
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
                        <i class="bi bi-clipboard-check display-6 d-block mb-2" style="color: #123b63;"></i>
                        No active in-progress repairs found.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: COMPLETED / PENDING RETURN -->
        <div class="tab-pane fade" id="completed-pane" role="tabpanel" aria-labelledby="completed-tab" tabindex="0">
            <?php if (!empty($completed_grouped)): ?>
                <div class="accordion shadow-sm rounded-4 overflow-hidden" id="completedAccordion">
                    <?php 
                    $index_c = 0;
                    foreach ($completed_grouped as $division_name => $items): 
                        $collapse_id_c = "collapseCompDiv" . $index_c;
                        $heading_id_c = "headingCompDiv" . $index_c;
                    ?>
                        <div class="accordion-item border-0 border-bottom">
                            <h2 class="accordion-header" id="<?= $heading_id_c ?>">
                                <button class="accordion-button collapsed fw-bold py-3" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#<?= $collapse_id_c ?>" 
                                        aria-expanded="false" 
                                        aria-controls="<?= $collapse_id_c ?>">
                                    <span><i class="bi bi-building me-2"></i> <?= htmlspecialchars($division_name) ?></span>
                                    <span class="badge ms-3 bg-white text-dark fw-bold border" style="font-size: 11px;">
                                        <?= count($items) ?> <?= count($items) === 1 ? 'Ticket' : 'Tickets' ?>
                                    </span>
                                </button>
                            </h2>
                            <div id="<?= $collapse_id_c ?>" 
                                 class="accordion-collapse collapse" 
                                 aria-labelledby="<?= $heading_id_c ?>" 
                                 data-bs-parent="#completedAccordion">
                                <div class="accordion-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-custom-header text-uppercase fs-7">
                                                <tr>
                                                    <th class="ps-4" style="width: 22%;">Item &amp; Asset Tag</th>
                                                    <th style="width: 15%;">Origin Location</th>
                                                    <th style="width: 13%;">Type &amp; Vendor</th>
                                                    <th style="width: 18%;">Resolution Notes</th>
                                                    <th style="width: 9%;">Cost</th>
                                                    <th style="width: 9%;">Status</th>
                                                    <th class="text-end pe-4" style="width: 14%;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $row): ?>
                                                    <tr>
                                                        <td class="ps-4 py-3">
                                                            <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['item_name']) ?></div>
                                                            <div class="text-muted small text-break fw-semibold" style="font-size: 0.8rem;"><?= htmlspecialchars($row['division_asset_id']) ?></div>
                                                            <div class="text-secondary" style="font-size: 0.75rem;">S/N: <?= htmlspecialchars($row['serial_number'] ?: 'N/A') ?></div>
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
                                                        <td>
                                                            <?php 
                                                                $badge_bg = 'bg-secondary-subtle text-secondary';
                                                                $type_label = ucfirst(str_replace('_', ' ', $row['repair_type']));
                                                                if ($row['repair_type'] === 'internal') $badge_bg = 'bg-info-subtle text-info';
                                                                elseif ($row['repair_type'] === 'external_warranty') $badge_bg = 'bg-warning-subtle text-warning';
                                                                elseif ($row['repair_type'] === 'external_paid') $badge_bg = 'bg-danger-subtle text-danger';
                                                            ?>
                                                            <span class="badge <?= $badge_bg ?> mb-1"><?= $type_label ?></span>
                                                            <div class="small fw-medium text-secondary"><?= htmlspecialchars($row['vendor_name'] ?: 'Internal Tech') ?></div>
                                                        </td>
                                                        <td class="small text-muted" style="max-width: 180px;">
                                                            <div class="text-success"><?= htmlspecialchars($row['resolution_notes']) ?></div>
                                                        </td>
                                                        <td class="fw-semibold text-dark">
                                                            ₹<?= number_format($row['repair_cost'], 2) ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success-subtle text-success text-uppercase fw-bold" style="font-size: 10px;">
                                                                Completed
                                                            </span>
                                                        </td>
                                                        <td class="text-end pe-4">
                                                            <div class="d-flex flex-column gap-1 align-items-end">
                                                                <!-- Return to Origin Button -->
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="repair_id" value="<?= $row['id'] ?>">
                                                                    <input type="hidden" name="action" value="return_origin">
                                                                    <button type="submit" class="btn btn-sm fw-bold text-nowrap text-white py-1 px-2 w-100" style="background-color: #123b63; border-color: #123b63; font-size: 10.5px;">
                                                                        <i class="bi bi-arrow-return-left me-1"></i> Return to Origin
                                                                    </button>
                                                                </form>

                                                                <!-- Return to Main Stock Button -->
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to return this asset to Main Stock? (Use this if a replacement PC was already given to the division).');">
                                                                    <input type="hidden" name="repair_id" value="<?= $row['id'] ?>">
                                                                    <input type="hidden" name="action" value="return_main_stock">
                                                                    <button type="submit" class="btn btn-sm fw-bold text-nowrap btn-outline-secondary py-1 px-2 w-100" style="font-size: 10.5px;">
                                                                        <i class="bi bi-box-seam me-1"></i> Return to Main Stock
                                                                    </button>
                                                                </form>
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
                        $index_c++;
                    endforeach; 
                    ?>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="bi bi-check-circle display-6 d-block mb-2 text-success"></i>
                        No completed repairs waiting to be returned.
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
$extra_html = $modals_html ?? '';
include "layout.php";
?>