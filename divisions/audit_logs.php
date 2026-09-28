<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$page_title = "Historical Audit Records";
$page_icon  = "bi-journal-text";

$role        = $_SESSION['role'] ?? '';
$session_division_id = $_SESSION['division_id'] ?? 0;

/* ================= CAPTURE FILTER INPUTS ================= */
$filter_institution = $_GET['institution_id'] ?? '';
$filter_division    = $_GET['division_id'] ?? ($role !== 'SuperAdmin' ? $session_division_id : '');
$filter_unit        = $_GET['unit_id'] ?? '';

$is_filtered = (!empty($filter_institution) || (!empty($filter_division) && $role === 'SuperAdmin' && $filter_division != $session_division_id) || !empty($filter_unit));

/* ================= HELPERS: DYNAMIC CATEGORY & ITEM ICONS ================= */
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

        // Remove spaces, hyphens, underscores to match variations like "ip com", "access point", etc.
        $cleanName = str_replace([' ', '-', '_'], '', $name);

        switch (true) {
            // 1. HIGH-PRIORITY SPECIFIC ITEM MATCHES
            case (
                str_contains($cleanName, 'accesspoint') || 
                str_contains($cleanName, 'ipcom') || 
                str_contains($name, 'wifi')
            ):
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

            // 2. CATEGORY FALLBACKS 
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

/* ================= RENDER ACCORDION ITEM HELPER ================= */
if (!function_exists('renderAccordionItem')) {
    function renderAccordionItem(string $trx_id, array $transactions, string $unique_id, string $parent_accordion_id) {
        $latest_trx = $transactions[0]; 
        $primary_item = htmlspecialchars($latest_trx['item_name']);
        $icon_class = getItemDetailIcon($latest_trx['item_name'], $latest_trx['category'] ?? '');
        
        $raw_unit_name = $latest_trx['snapshot_unit'] ?? $latest_trx['active_unit'] ?? $latest_trx['history_unit'] ?? 'Main Stock / Returned';
        $unit_code = $latest_trx['active_unit_code'] ?? $latest_trx['history_unit_code'] ?? '';
        $final_unit = !empty($unit_code) ? "{$unit_code} - {$raw_unit_name}" : $raw_unit_name;
        
        $division_name = $latest_trx['division_name'] ?? 'Division';
        
        $raw_action = $latest_trx['action_type'];
        switch ($raw_action) {
            case 'service_requested': case 'return_requested': $latest_action_label = "Service Requested"; break;
            case 'repair_requested': case 'repair_approved': $latest_action_label = "Sent to Repair"; break;
            case 'dispose_requested': case 'disposal_approved': $latest_action_label = "Decommissioned"; break;
            case 'return_approved': case 'completed': $latest_action_label = "Returned to Stock"; break;
            case 'repair_returned_to_origin': $latest_action_label = "Repair Returned to Origin"; break;
            case 'repair_returned_to_main_stock': $latest_action_label = "Repair Returned to Stock"; break;
            case 'request_rejected': case 'return_rejected': $latest_action_label = "Request Rejected"; break;
            default: $latest_action_label = !empty($raw_action) ? ucwords(str_replace('_', ' ', $raw_action)) : "Activity Logged"; break;
        }
        ?>
        <div class="accordion-item border-0 mb-3 shadow-sm rounded-3 overflow-hidden">
            <h2 class="accordion-header" id="heading_<?= $unique_id ?>">
                <button class="accordion-button collapsed bg-white py-3 px-4 d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?= $unique_id ?>" aria-expanded="false" aria-controls="collapse_<?= $unique_id ?>">
                    <div class="d-flex align-items-center gap-3 w-100 pe-3">
                        <div class="badge bg-light text-dark border px-3 py-2 fw-bold font-monospace">
                            <?= $trx_id ?>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark"><?= $primary_item ?></h6>
                            <span class="text-muted" style="font-size: 0.75rem;">
                                <?= strtoupper($latest_action_label) ?> &bull; <?= date('d M, Y h:i A', strtotime($latest_trx['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                </button>
            </h2>
            <div id="collapse_<?= $unique_id ?>" class="accordion-collapse collapse" aria-labelledby="heading_<?= $unique_id ?>" data-bs-parent="#<?= $parent_accordion_id ?>">
                <div class="accordion-body bg-light p-3">
                    
                    <!-- SUMMARY HEADER WITH DIVISION & UNIT CODE -->
                    <div class="bg-white p-3 rounded-3 border mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3 shadow-sm">
                        <div class="d-flex align-items-center">
                            <div class="icon-box me-3 flex-shrink-0 bg-light p-2 rounded">
                                <i class="bi <?= $icon_class ?> fs-4 text-secondary"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($latest_trx['item_name']) ?></div>
                                <div class="text-primary small fw-semibold">SN: <?= htmlspecialchars($latest_trx['serial_number'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                        <div class="border-start ps-3">
                            <div class="text-muted small d-flex align-items-center gap-1">
                                <i class="bi bi-building"></i> <span><?= htmlspecialchars($division_name) ?></span>
                            </div>
                            <div class="fw-medium text-dark"><?= htmlspecialchars($final_unit) ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;">ID: <?= htmlspecialchars($latest_trx['display_tag']) ?></div>
                        </div>
                    </div>

                    <!-- TABLE -->
                    <div class="table-responsive bg-white rounded-3 border shadow-sm">
                        <table class="table audit-table align-middle mb-0">
                            <colgroup>
                                <col style="width: 18%;">
                                <col style="width: 22%;">
                                <col style="width: 20%;">
                                <col style="width: 40%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="ps-4">Timestamp</th>
                                    <th class="text-center">Service State</th>
                                    <th class="text-center">Executed By</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $row): 
                                    $status = $row['action_type'];
                                    $notes = $row['notes'] ?? '';
                                    $clean_notes = preg_replace('/\[REF:#\d+\]\s*/', '', $notes);

                                    switch ($status) {
                                        case 'service_requested': case 'return_requested':
                                            $status_label = "SERVICE REQUESTED";
                                            $badge_class = "bg-warning-subtle text-warning-emphasis border-warning-subtle";
                                            break;
                                        case 'repair_requested': case 'repair_approved':
                                            $status_label = "SENT TO REPAIR";
                                            $badge_class = "bg-info-subtle text-info-emphasis border-info-subtle";
                                            break;
                                        case 'dispose_requested': case 'disposal_approved':
                                            $status_label = "DECOMMISSIONED";
                                            $badge_class = "bg-danger-subtle text-danger border-danger-subtle";
                                            break;
                                        case 'return_approved': case 'completed':
                                            $status_label = "RETURNED TO STOCK";
                                            $badge_class = "bg-success-subtle text-success-emphasis border-success-subtle";
                                            break;
                                        case 'repair_returned_to_origin':
                                            $status_label = "REPAIR RETURNED TO ORIGIN";
                                            $badge_class = "bg-success-subtle text-success-emphasis border-success-subtle";
                                            break;
                                        case 'repair_returned_to_main_stock':
                                            $status_label = "REPAIR RETURNED TO MAIN STOCK";
                                            $badge_class = "bg-primary-subtle text-primary-emphasis border-primary-subtle";
                                            break;
                                        case 'request_rejected': case 'return_rejected':
                                            $status_label = "REQUEST REJECTED";
                                            $badge_class = "bg-danger-subtle text-danger border-danger-subtle";
                                            break;
                                        default:
                                            $status_label = !empty($status) ? strtoupper(str_replace('_', ' ', $status)) : "N/A";
                                            $badge_class = "bg-secondary-subtle text-secondary-emphasis border-secondary-subtle";
                                            break;
                                    }
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-medium text-dark"><?= date('d M', strtotime($row['created_at'])) ?></div>
                                        <div class="text-muted small fw-semibold"><?= date('Y', strtotime($row['created_at'])) ?></div>
                                        <div class="text-muted small mt-1"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge border <?= $badge_class ?> text-uppercase px-2 py-1 lh-sm d-inline-block" style="font-size: 0.62rem; font-weight: 700;">
                                            <?= $status_label ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center text-secondary text-break">
                                            <i class="bi bi-person me-1 flex-shrink-0"></i>
                                            <span class="fw-medium text-dark"><?= htmlspecialchars($row['staff_name'] ?: 'System') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $disposal_reason = '';
                                            if (in_array($status, ['dispose_requested', 'disposal_approved'])) {
                                                if (preg_match('/Reason:\s*(.*)$/i', $clean_notes, $matches)) {
                                                    $disposal_reason = trim($matches[1]);
                                                    $clean_notes = trim(preg_replace('/\.?\s*Reason:\s*.*$/i', '', $clean_notes));
                                                }
                                            }
                                        ?>
                                        <span class="text-muted small text-break d-block"><?= htmlspecialchars($clean_notes ?: 'No notes recorded.') ?></span>
                    
                                        <?php if (in_array($status, ['repair_requested', 'repair_approved'])): ?>
                                            <?php if (!empty($row['repair_type']) && strtolower($row['repair_type']) === 'internal'): ?>
                                                <div class="mt-2 pt-1 border-top">
                                                    <span class="text-primary text-break d-block" style="font-size: 0.6rem;">
                                                        <i class="bi bi-tools me-1"></i> Maintenance (Internal)
                                                    </span>
                                                </div>
                                            <?php elseif (!empty($row['vendor_name'])): ?>
                                                <div class="mt-2 pt-1 border-top">
                                                    <span class="text-primary text-break d-block" style="font-size: 0.6rem;">
                                                        <i class="bi bi-person-vcard me-1"></i> <?= htmlspecialchars($row['vendor_name']) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (in_array($status, ['dispose_requested', 'disposal_approved']) && !empty($disposal_reason)): ?>
                                            <div class="mt-2 pt-1 border-top">
                                                <span class="text-danger text-break d-block" style="font-size: 0.6rem;">
                                                    <i class="bi bi-exclamation-octagon me-1"></i> Reason: <?= htmlspecialchars($disposal_reason) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($status === 'repair_returned_to_origin' && !empty($row['resolution_notes'])): ?>
                                            <div class="mt-2 pt-1 border-top">
                                                <span class="text-success text-break d-block" style="font-size: 0.6rem;">
                                                    <i class="bi bi-check2-circle me-1"></i> Resolution: <?= htmlspecialchars($row['resolution_notes']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
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
    }
}

/* ================= FETCH DROPDOWN OPTIONS ================= */
$institutions_res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name ASC");
$divisions_res    = $conn->query("SELECT id, division_name FROM divisions" . (!empty($filter_institution) ? " WHERE institution_id = " . intval($filter_institution) : "") . " ORDER BY division_name ASC");
$units_res_filter = $conn->query("SELECT id, unit_name FROM units" . (!empty($filter_division) ? " WHERE division_id = " . intval($filter_division) : "") . " ORDER BY unit_name ASC");

/* ================= 2. FETCH AUDIT LOGS ================= */
$logs_query = "
    SELECT 
        al.id as log_id,
        al.asset_id,
        al.created_at, 
        al.action_type, 
        al.notes,
        im.item_name, 
        im.category,
        sd.serial_number,
        r.vendor_name,
        r.repair_type,
        r.resolution_notes,
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
        un_active.unit_code AS active_unit_code,
        un_history.unit_name AS history_unit,
        un_history.unit_code AS history_unit_code,
        COALESCE(d_active.division_name, d_history.division_name, 'Main Division') AS division_name,
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
    LEFT JOIN divisions d_active ON dm_active.division_id = d_active.id
    LEFT JOIN institutions i_active ON d_active.institution_id = i_active.id
    LEFT JOIN dispatch_details dd_history ON sd.id = dd_history.stock_detail_id
    LEFT JOIN dispatch_master dm_history ON dd_history.dispatch_id = dm_history.id
    LEFT JOIN units un_history ON dm_history.unit_id = un_history.id
    LEFT JOIN divisions d_history ON dm_history.division_id = d_history.id
    LEFT JOIN institutions i_history ON d_history.institution_id = i_history.id
    LEFT JOIN repairs r ON sd.id = r.stock_detail_id
    WHERE 1=1
";

// Apply dynamic filter conditions
if (!empty($filter_institution)) {
    $logs_query .= " AND (d_active.institution_id = " . intval($filter_institution) . " OR d_history.institution_id = " . intval($filter_institution) . ")";
}
if (!empty($filter_division)) {
    $logs_query .= " AND (dm_active.division_id = " . intval($filter_division) . " OR dm_history.division_id = " . intval($filter_division) . ")";
}
if (!empty($filter_unit)) {
    $logs_query .= " AND (dm_active.unit_id = " . intval($filter_unit) . " OR dm_history.unit_id = " . intval($filter_unit) . ")";
}

// Role restriction safeguard if not SuperAdmin
if ($role !== 'SuperAdmin') { 
    $logs_query .= " AND (dm_active.division_id = $session_division_id OR dm_history.division_id = $session_division_id OR al.performed_by = {$_SESSION['user_id']})"; 
}

$global_logs_query = $logs_query . " GROUP BY al.id ORDER BY al.created_at ASC, al.id ASC";
$global_logs_res = $conn->query($global_logs_query);

$logs_query .= " GROUP BY al.id ORDER BY al.created_at DESC";
$logs_res = $conn->query($logs_query);

// Retrieve units for report dropdown header button
$units_query = "SELECT id, unit_name FROM units WHERE division_id = " . ($role === 'SuperAdmin' ? "1" : $session_division_id) . " ORDER BY unit_name ASC";
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
    .audit-table { font-size: 0.85rem; table-layout: fixed; width: 100%; }
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
    .audit-table td { padding: 0.85rem 1rem; word-wrap: break-word; overflow-wrap: break-word; vertical-align: middle; }

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
    .btn-navy { background-color: #1e293b; color: #ffffff; border: none; }
    .btn-navy:hover { background-color: #0f172a; color: #ffffff; }

    /* Custom Navigation Tabs Customization */
    .nav-tabs .nav-link {
        color: #64748b;
        font-weight: 600;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 0.75rem 1.25rem;
    }
    .nav-tabs .nav-link:hover {
        color: #1e293b;
        border-color: transparent;
    }
    .nav-tabs .nav-link.active {
        color: #1e293b;
        background-color: transparent;
        border-bottom: 3px solid #1e293b;
    }

    .auto-resize-select {
        border-radius: 4px;
        border: 1px solid #d9e0e7;
        padding: 0.35rem 0.8rem;
        font-size: 0.85rem;
        font-weight: 500;
        color: #18344d;
        background-color: #f8fafc;
        transition: all 0.18s ease;
        max-width: none !important;
        min-width: 140px;
        box-sizing: border-box;
    }
    .auto-resize-select:focus {
        border-color: #123b63;
        box-shadow: 0 0 0 3px rgba(18, 59, 99, 0.12);
        background-color: #fff;
    }
</style>

<div class="container-fluid pt-2 pb-4">
    <!-- Header with Return Button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="returned_assets.php" class="text-decoration-none text-muted small fw-semibold d-inline-flex align-items-center mb-2">
                <i class="bi bi-arrow-left me-1"></i> Back to Pending Requests
            </a>
            <h4 class="fw-bold text-dark mb-1">Service History & Audit</h4>
            <p class="text-muted small mb-0">Grouped by Service ID. Click any service to expand its full service history.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- Filter Toggle Button -->
            <button class="btn btn-outline-secondary btn-sm shadow-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapseSection" aria-expanded="<?= $is_filtered ? 'true' : 'false' ?>" aria-controls="filterCollapseSection">
                <i class="bi bi-funnel-fill"></i> Filter Options
            </button>
            
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
    </div>

    <!-- COLLAPSIBLE FILTER SECTION -->
    <div class="collapse <?= $is_filtered ? 'show' : '' ?> mb-4" id="filterCollapseSection">
        <div class="card card-custom shadow-sm p-3">
            <form method="GET" action="" class="row g-3 align-items-end">
                
                <!-- ROW 1: Institution & Division -->
                <?php if ($role === 'SuperAdmin'): ?>
                <div class="col-md-6">
                    <label for="institution_id" class="form-label small fw-bold text-muted">Institution</label>
                    <select name="institution_id" id="institution_id" class="form-select form-select-sm auto-resize-select w-100" onchange="this.form.submit()">
                        <option value="">All Institutions</option>
                        <?php if ($institutions_res && $institutions_res->num_rows > 0): ?>
                            <?php while($inst = $institutions_res->fetch_assoc()): ?>
                                <option value="<?= $inst['id'] ?>" <?= $filter_institution == $inst['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['institution_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="<?= $role === 'SuperAdmin' ? 'col-md-6' : 'col-md-12' ?>">
                    <label for="division_id" class="form-label small fw-bold text-muted">Division</label>
                    <select name="division_id" id="division_id" class="form-select form-select-sm auto-resize-select w-100" onchange="this.form.submit()" <?= $role !== 'SuperAdmin' ? 'disabled' : '' ?>>
                        <option value="">All Divisions</option>
                        <?php if ($divisions_res && $divisions_res->num_rows > 0): ?>
                            <?php while($div = $divisions_res->fetch_assoc()): ?>
                                <option value="<?= $div['id'] ?>" <?= $filter_division == $div['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($div['division_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- ROW 2: Unit / Lab & Buttons -->
                <div class="col-md-6">
                    <label for="unit_id" class="form-label small fw-bold text-muted">Unit / Lab</label>
                    <select name="unit_id" id="unit_id" class="form-select form-select-sm auto-resize-select w-100" onchange="this.form.submit()">
                        <option value="">All Units / Labs</option>
                        <?php if ($units_res_filter && $units_res_filter->num_rows > 0): ?>
                            <?php while($unit = $units_res_filter->fetch_assoc()): ?>
                                <option value="<?= $unit['id'] ?>" <?= $filter_unit == $unit['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($unit['unit_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-navy btn-sm flex-grow-1 shadow-sm">
                        <i class="bi bi-filter"></i> Apply Filter
                    </button>
                    <a href="?" class="btn btn-outline-secondary btn-sm px-3" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>

            </form>
        </div>
    </div>

    <?php 
        $grouped_logs = [];
        $global_log_to_transaction = [];

        if ($global_logs_res && $global_logs_res->num_rows > 0) {
            $global_rows = [];
            while ($row = $global_logs_res->fetch_assoc()) {
                $global_rows[] = $row;
            }

            usort($global_rows, function($a, $b) {
                return strtotime($a['created_at']) - strtotime($b['created_at']) ?: ($a['log_id'] - $b['log_id']);
            });

            $asset_active_session = [];
            $sessions = [];
            $session_counter = 0;

            foreach ($global_rows as $row) {
                $asset_id = $row['asset_id'];
                $action   = $row['action_type'];
                $log_id   = $row['log_id'];

                $is_start_action = in_array($action, ['service_requested', 'return_requested']);
                $is_end_action = in_array($action, [
                    'return_approved',
                    'completed',
                    'repair_returned_to_main_stock',
                    'repair_returned_to_origin'
                ]);

                if ($is_start_action || !isset($asset_active_session[$asset_id])) {
                    $session_counter++;
                    $asset_active_session[$asset_id] = 'SESSION_' . $log_id . '_' . $session_counter;
                }

                $current_session_key = $asset_active_session[$asset_id];

                if (!isset($sessions[$current_session_key])) {
                    $sessions[$current_session_key] = [
                        'min_time' => $row['created_at'],
                        'rows' => []
                    ];
                }

                $sessions[$current_session_key]['rows'][] = $row;

                if ($is_end_action) {
                    unset($asset_active_session[$asset_id]);
                }
            }

            uasort($sessions, function($a, $b) {
                return strtotime($a['min_time']) - strtotime($b['min_time']);
            });

            $seq = 0;
            foreach ($sessions as $s_data) {
                $seq++;
                $formatted_num = ($seq < 100) ? str_pad($seq, 2, '0', STR_PAD_LEFT) : $seq;
                $trx_id = "CECSID" . $formatted_num;

                foreach ($s_data['rows'] as $row) {
                    $global_log_to_transaction[(int)$row['log_id']] = $trx_id;
                }
            }
        }

        if ($logs_res && $logs_res->num_rows > 0) {
            $logs_res->data_seek(0);
            $visible_rows = [];

            while ($row = $logs_res->fetch_assoc()) {
                $log_id = (int)$row['log_id'];

                if (isset($global_log_to_transaction[$log_id])) {
                    $row['_trx_id'] = $global_log_to_transaction[$log_id];
                    $visible_rows[] = $row;
                }
            }

            foreach ($visible_rows as $row) {
                $trx_id = $row['_trx_id'];
                unset($row['_trx_id']);
                $grouped_logs[$trx_id][] = $row;
            }

            uksort($grouped_logs, function($a, $b) {
                preg_match('/\d+/', $a, $numA);
                preg_match('/\d+/', $b, $numB);
                return intval($numB[0] ?? 0) - intval($numA[0] ?? 0);
            });
        }

        // Partition grouped logs into 3 categories based on the latest transaction action
        $tab_service_requested = [];
        $tab_under_repair = [];
        $tab_completed = [];

        foreach ($grouped_logs as $trx_id => $transactions) {
            usort($transactions, function($x, $y) {
                return strtotime($y['created_at']) - strtotime($x['created_at']);
            });
            $latest_action = $transactions[0]['action_type'];

            if (in_array($latest_action, ['service_requested', 'return_requested'])) {
                $tab_service_requested[$trx_id] = $transactions;
            } elseif (in_array($latest_action, ['repair_requested', 'repair_approved'])) {
                $tab_under_repair[$trx_id] = $transactions;
            } else {
                $tab_completed[$trx_id] = $transactions;
            }
        }
    ?>

    <!-- Search Bar Container -->
    <div class="row mb-4">
        <div class="col-md-5">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="globalAuditSearch" class="form-control border-start-0 ps-0" placeholder="Search Service ID (e.g. CECSID01)">
            </div>
        </div>
    </div>

    <!-- Bootstrap Nav Tabs Header -->
    <ul class="nav nav-tabs mb-4 border-bottom" id="auditTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active d-flex align-items-center gap-2" id="service-tab" data-bs-toggle="tab" data-bs-target="#service-pane" type="button" role="tab" aria-controls="service-pane" aria-selected="true">
                <i class="bi bi-clock-history"></i> Service Requested 
                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill ms-1"><?= count($tab_service_requested) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link d-flex align-items-center gap-2" id="repair-tab" data-bs-toggle="tab" data-bs-target="#repair-pane" type="button" role="tab" aria-controls="repair-pane" aria-selected="false">
                <i class="bi bi-tools"></i> Under Repair 
                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill ms-1"><?= count($tab_under_repair) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link d-flex align-items-center gap-2" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed-pane" type="button" role="tab" aria-controls="completed-pane" aria-selected="false">
                <i class="bi bi-check-circle"></i> Completed 
                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill ms-1"><?= count($tab_completed) ?></span>
            </button>
        </li>
    </ul>

    <!-- Tab Contents Container -->
    <div class="tab-content" id="auditTabsContent">
        
        <!-- TAB 1: SERVICE REQUESTED -->
        <div class="tab-pane fade show active" id="service-pane" role="tabpanel" aria-labelledby="service-tab">
            <?php if (!empty($tab_service_requested)): ?>
                <div class="accordion shadow-sm" id="accordionService">
                    <?php $index = 0; foreach ($tab_service_requested as $trx_id => $transactions): $index++; ?>
                        <?php renderAccordionItem($trx_id, $transactions, "service_$index", "accordionService"); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card card-custom shadow-sm p-4 text-center text-muted">
                    No service requested audit logs found for the selected filter.
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: UNDER REPAIR -->
        <div class="tab-pane fade" id="repair-pane" role="tabpanel" aria-labelledby="repair-tab">
            <?php if (!empty($tab_under_repair)): ?>
                <div class="accordion shadow-sm" id="accordionRepair">
                    <?php $index = 0; foreach ($tab_under_repair as $trx_id => $transactions): $index++; ?>
                        <?php renderAccordionItem($trx_id, $transactions, "repair_$index", "accordionRepair"); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card card-custom shadow-sm p-4 text-center text-muted">
                    No records currently under repair for the selected filter.
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 3: COMPLETED -->
        <div class="tab-pane fade" id="completed-pane" role="tabpanel" aria-labelledby="completed-tab">
            <?php if (!empty($tab_completed)): ?>
                <div class="accordion shadow-sm" id="accordionCompleted">
                    <?php $index = 0; foreach ($tab_completed as $trx_id => $transactions): $index++; ?>
                        <?php renderAccordionItem($trx_id, $transactions, "completed_$index", "accordionCompleted"); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card card-custom shadow-sm p-4 text-center text-muted">
                    No completed historical records found for the selected filter.
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
function autoResizeSelect(selectElement) {
    if (!selectElement) return;

    const tempSpan = document.createElement('span');
    tempSpan.style.visibility = 'hidden';
    tempSpan.style.position = 'absolute';
    tempSpan.style.whiteSpace = 'nowrap';

    const style = window.getComputedStyle(selectElement);
    tempSpan.style.font = style.font;
    tempSpan.style.fontSize = style.fontSize;
    tempSpan.style.fontFamily = style.fontFamily;
    tempSpan.style.fontWeight = style.fontWeight;

    const selectedText = selectElement.options[selectElement.selectedIndex]?.text || '';
    tempSpan.textContent = selectedText;

    document.body.appendChild(tempSpan);

    const calculatedWidth = Math.ceil(tempSpan.getBoundingClientRect().width) + 50;
    selectElement.style.width = `${calculatedWidth}px`;

    document.body.removeChild(tempSpan);
}

document.addEventListener('DOMContentLoaded', function () {
    // Auto-resize dropdown initialization
    const dynamicDropdowns = document.querySelectorAll('.auto-resize-select');
    dynamicDropdowns.forEach(select => {
        autoResizeSelect(select);
        select.addEventListener('change', (e) => autoResizeSelect(e.target));
    });

    // Re-adjust select widths when filter collapse section is toggled open
    const filterCollapse = document.getElementById('filterCollapseSection');
    if (filterCollapse) {
        filterCollapse.addEventListener('shown.bs.collapse', function () {
            dynamicDropdowns.forEach(select => autoResizeSelect(select));
        });
    }

    const searchInput = document.getElementById('globalAuditSearch');
    
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const allPanes = document.querySelectorAll('.tab-pane');
            
            const counts = {
                service: 0,
                repair: 0,
                completed: 0
            };

            let firstPaneWithMatch = null;
            let activePaneHasMatch = false;
            const activePane = document.querySelector('.tab-pane.active');

            allPanes.forEach(pane => {
                const paneId = pane.id;
                const items = pane.querySelectorAll('.accordion-item');
                let paneMatchCount = 0;

                items.forEach(item => {
                    const itemText = item.textContent.toLowerCase();
                    if (query === '' || itemText.includes(query)) {
                        item.style.display = ''; 
                        paneMatchCount++;
                    } else {
                        item.style.display = 'none'; 
                    }
                });

                if (paneId === 'service-pane') counts.service = paneMatchCount;
                if (paneId === 'repair-pane') counts.repair = paneMatchCount;
                if (paneId === 'completed-pane') counts.completed = paneMatchCount;

                if (paneMatchCount > 0) {
                    if (!firstPaneWithMatch) {
                        firstPaneWithMatch = paneId;
                    }
                    if (pane === activePane) {
                        activePaneHasMatch = true;
                    }
                }
            });

            // Update the count badges on the nav tabs
            const serviceBadge = document.querySelector('#service-tab .badge');
            const repairBadge = document.querySelector('#repair-tab .badge');
            const completedBadge = document.querySelector('#completed-tab .badge');

            if (serviceBadge) serviceBadge.textContent = counts.service;
            if (repairBadge) repairBadge.textContent = counts.repair;
            if (completedBadge) completedBadge.textContent = counts.completed;

            // Automatically switch to the tab containing the match if the current tab has no matches
            if (query !== '' && !activePaneHasMatch && firstPaneWithMatch) {
                let targetTabBtnId = '';
                if (firstPaneWithMatch === 'service-pane') targetTabBtnId = '#service-tab';
                if (firstPaneWithMatch === 'repair-pane') targetTabBtnId = '#repair-tab';
                if (firstPaneWithMatch === 'completed-pane') targetTabBtnId = '#completed-tab';

                if (targetTabBtnId) {
                    const tabTrigger = document.querySelector(targetTabBtnId);
                    if (tabTrigger) {
                        const tab = bootstrap.Tab.getOrCreateInstance(tabTrigger);
                        tab.show();
                    }
                }
            }
        });
    }
});
</script>

<?php
$content = ob_get_clean();
    include __DIR__ . "/../admin/adminlayout.php";
?>