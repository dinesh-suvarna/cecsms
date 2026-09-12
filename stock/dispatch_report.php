<?php
ob_start();
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? '';

$page_title = "Dispatch Report";
$page_icon  = "bi-clipboard-data";

/* Role Restriction */
if($user_role !== 'SuperAdmin'){
    echo "<div class='container mt-5'><div class='alert alert-danger text-center'><h5>Access Denied</h5><p>Only Superadmin can view this report.</p></div></div>";
    exit;
}

/* Filters */
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$institution_filter = $_GET['institution_id'] ?? '';
$where = "WHERE 1=1";

if(!empty($from_date) && !empty($to_date)){
    $where .= " AND dm.dispatch_date BETWEEN '$from_date' AND '$to_date'";
}
if(!empty($institution_filter)){
    $where .= " AND dm.institution_id = ".(int)$institution_filter;
}

$institutions = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name ASC");

/* Query */
$query = "
SELECT 
    dm.id AS dispatch_id, dm.status, dm.dispatch_date, 
    dd.quantity, im.model_name, si.item_name, sd.id AS stock_detail_id, sd.serial_number, si.category,
    i.institution_name, dm.institution_id,
    d.division_name, dm.division_id,
    un.unit_name, dm.unit_id, un.unit_code
FROM dispatch_details dd
LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
LEFT JOIN stock_details sd ON dd.stock_detail_id = sd.id
LEFT JOIN items_master si ON sd.stock_item_id = si.id
LEFT JOIN item_models im ON sd.model_id = im.id
LEFT JOIN institutions i ON dm.institution_id = i.id
LEFT JOIN divisions d ON dm.division_id = d.id
LEFT JOIN units un ON dm.unit_id = un.id
$where
ORDER BY dm.id DESC";

$result = $conn->query($query);

/* Grouping Logic (Hierarchical Grouping with Model Name) */
$grouped = [];
while($row = $result->fetch_assoc()){
    $inst = $row['institution_name'] ?? 'Unknown';
    $div  = $row['division_name'] ?? 'Unknown';
    $unit_label = ($row['unit_code'] ? $row['unit_code'] . " - " : "") . ($row['unit_name'] ?? 'General/Unassigned');
    
    $model_group = !empty($row['model_name']) ? $row['model_name'] : (!empty($row['item_name']) ? $row['item_name'] : 'Unknown Model');

    $grouped[$inst]['id'] = $row['institution_id'];
    $grouped[$inst]['computer_total'] ??= 0;
    
    $grouped[$inst]['divisions'][$div]['id'] = $row['division_id'];
    $grouped[$inst]['divisions'][$div]['computer_total'] ??= 0;

    $grouped[$inst]['divisions'][$div]['units'][$unit_label]['id'] = $row['unit_id'];
    $grouped[$inst]['divisions'][$div]['units'][$unit_label]['computer_total'] ??= 0;
    
    $grouped[$inst]['divisions'][$div]['units'][$unit_label]['models'][$model_group]['rows'][] = $row;
    $grouped[$inst]['divisions'][$div]['units'][$unit_label]['models'][$model_group]['total_qty'] ??= 0;

    $qty = !empty($row['serial_number']) ? 1 : (int)$row['quantity'];
    
    $grouped[$inst]['divisions'][$div]['units'][$unit_label]['models'][$model_group]['total_qty'] += $qty;
    
    if($row['category'] === 'Computer'){
        $grouped[$inst]['computer_total'] += $qty;
        $grouped[$inst]['divisions'][$div]['computer_total'] += $qty;
        $grouped[$inst]['divisions'][$div]['units'][$unit_label]['computer_total'] += $qty;
    }
}
?>

<style>
    :root {
        --brand-primary: #123b63;
        --brand-navy: #0b2942;
        --brand-white: #ffffff;
        --bg-surface: #f8fafc;
        --card-bg: #ffffff;
        --card-border: #e2e8f0;
        --card-border-hover: #cbd5e1;
        --text-primary: #1e293b;
        --text-body: #475569;
        --text-muted: #64748b;
        --shadow-subtle: 0 1px 3px rgba(15, 23, 42, 0.05);
        --shadow-hover: 0 4px 12px rgba(15, 23, 42, 0.08);
        --transition-smooth: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    html { overflow-y: scroll; scrollbar-gutter: stable; }

    /* Sticky Filter Card */
    .filter-card-modern {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 8px;
        box-shadow: var(--shadow-subtle);
        overflow: hidden;
    }

    .filter-card-header {
        background-color: var(--brand-navy);
        color: var(--brand-white);
        padding: 0.75rem 1.25rem;
    }

    .form-label-custom {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--brand-navy);
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .form-control-custom, .form-select-custom {
        border-radius: 6px;
        border: 1px solid var(--card-border);
        padding: 0.45rem 0.75rem;
        font-size: 0.84rem;
        font-weight: 500;
        color: var(--text-primary);
        background-color: #f8fafc;
        transition: var(--transition-smooth);
    }

    .form-control-custom:focus, .form-select-custom:focus {
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 3px rgba(18, 59, 99, 0.12);
        background-color: #fff;
    }

    .btn-navy {
        background-color: var(--brand-primary);
        color: var(--brand-white) !important;
        border: 1px solid var(--brand-navy);
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.84rem;
        padding: 0.45rem 1rem;
        transition: var(--transition-smooth);
    }

    .btn-navy:hover {
        background-color: var(--brand-navy);
        box-shadow: var(--shadow-subtle);
    }

    .btn-outline-navy {
        background-color: var(--card-bg);
        color: var(--brand-primary) !important;
        border: 1px solid var(--card-border);
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.84rem;
        padding: 0.45rem 1rem;
        transition: var(--transition-smooth);
    }

    .btn-outline-navy:hover {
        background-color: #f1f5f9;
        color: var(--brand-navy) !important;
        border-color: var(--card-border-hover);
    }

    /* Hierarchy Accordion Core UI */
    .institution-card { 
        border: 1px solid var(--card-border) !important;
        border-radius: 8px !important;
        background: var(--card-bg);
        box-shadow: var(--shadow-subtle);
        transition: var(--transition-smooth);
    }

    .inst-header {
        background-color: #ffffff !important;
        border-left: 5px solid var(--brand-navy) !important;
        padding: 0.9rem 1.25rem;
        transition: background 0.2s ease;
    }
    .inst-header:hover { background-color: #f8fafc !important; }

    .division-header { 
        background-color: #f8fafc !important; 
        border: 1px solid var(--card-border);
        border-left: 4px solid var(--brand-primary) !important;
        border-radius: 6px;
        margin: 8px 0;
        padding: 10px 14px !important;
        transition: var(--transition-smooth);
    }
    .division-header:hover { background-color: #f1f5f9 !important; }

    /* Collapsible Unit Card */
    .unit-block {
        background-color: #ffffff;
        border: 1px solid var(--card-border);
        border-left: 3px solid #0284c7;
        border-radius: 6px;
        margin: 6px 0 10px 16px; 
        box-shadow: var(--shadow-subtle);
        overflow: hidden;
    }

    .unit-header {
        padding: 10px 14px;
        background-color: #ffffff;
        transition: background 0.2s ease;
    }
    .unit-header:hover { background-color: #f8fafc; }

    .category-section {
        border: 1px solid var(--card-border);
        border-radius: 6px;
        margin-bottom: 8px;
        overflow: hidden;
    }

    .category-header {
        background-color: #f8fafc;
        border-bottom: 1px solid var(--card-border);
        padding: 8px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--brand-navy);
        transition: background 0.2s ease;
    }
    .category-header:hover {
        background-color: #f1f5f9;
    }

    /* Modern Table UI */
    .table-custom {
        table-layout: fixed !important;
        width: 100% !important;
        margin-bottom: 0;
    }

    .table-custom th {
        background-color: #f8fafc !important;
        color: var(--text-muted) !important;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.70rem;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--card-border);
        padding: 8px 12px;
    }

    .table-custom td {
        padding: 8px 12px;
        font-size: 0.82rem;
        border-bottom: 1px solid #f1f5f9;
        color: var(--text-primary);
    }

    .toggle-icon {
        transition: transform 0.25s ease;
        font-size: 0.8rem;
        color: var(--text-muted); 
    }

    [aria-expanded="true"] .toggle-icon {
        transform: rotate(90deg);
        color: var(--brand-primary);
    }

    .badge-navy {
        background-color: #eff6ff;
        color: var(--brand-primary);
        border: 1px solid #bfdbfe;
        font-weight: 600;
        font-size: 0.75rem;
    }

    .status-dispatched {
        color: #0d9488;
        font-weight: 700;
        font-size: 0.75rem;
        letter-spacing: 0.03em;
    }

    .match-group-highlight {
        background: rgba(255, 193, 7, 0.12); 
        border-left: 4px solid #ffc107 !important;
        border-radius: 6px;
    }

    .report-row.match-highlight {
        background-color: #fef3c7 !important;
    }

    @media print { 
        .no-print { display: none !important; }
        .collapse { display: block !important; height: auto !important; overflow: visible !important; }
        .toggle-icon { display: none !important; }
        .sticky-top { position: static !important; }
    }
</style>

<div class="container-fluid mt-4 mb-5">

    <!-- Filter Control Bar -->
    <div class="sticky-top no-print mb-4" style="top: 0; z-index: 1030;">
        <div class="card filter-card-modern">
            <div class="filter-card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold text-white d-flex align-items-center gap-2">
                    <i class="bi <?= $page_icon ?>"></i> <?= $page_title ?>
                </h6>
                <span class="badge bg-light text-dark fw-semibold px-2 py-1" style="font-size: 0.72rem;">Dispatch Audit</span>
            </div>
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end border-bottom pb-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label-custom"><i class="bi bi-calendar-event me-1"></i>From Date</label>
                        <input type="date" name="from_date" class="form-control form-control-custom w-100" value="<?= $from_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom"><i class="bi bi-calendar-event me-1"></i>To Date</label>
                        <input type="date" name="to_date" class="form-control form-control-custom w-100" value="<?= $to_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom"><i class="bi bi-building me-1"></i>Institution</label>
                        <select name="institution_id" class="form-select form-select-custom w-100">
                            <option value="">All Institutions</option>
                            <?php $institutions->data_seek(0); while($inst_row = $institutions->fetch_assoc()): ?>
                                <option value="<?= $inst_row['id'] ?>" <?= $institution_filter == $inst_row['id'] ? 'selected' : '' ?>><?= $inst_row['institution_name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-navy flex-fill">
                            <i class="bi bi-filter me-1"></i> Apply
                        </button>
                        <a href="dispatch_report.php" class="btn btn-outline-navy flex-fill text-center">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </a>
                    </div>
                </form>

                <div class="row g-2 align-items-center">
                    <div class="col-md-8">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="reportSearch" class="form-control form-control-custom border-start-0 ps-0" placeholder="Search by Model, Item, or Serial Number...">
                        </div>
                    </div>
                    <div class="col-md-4 d-flex justify-content-end gap-2">
                        <button type="button" id="globalToggleBtn" class="btn btn-outline-navy w-100" onclick="handleGlobalToggle()">
                            <i class="bi bi-arrows-angle-expand me-1"></i> <span id="toggleText">Expand All</span>
                        </button>
                        <button id="clearHighlightBtn" class="btn btn-warning btn-sm text-nowrap" style="display:none;">
                            <i class="bi bi-x-circle me-1"></i> Clear Match
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Accordion Report Hierarchy -->
    <div id="reportContent">
        <?php foreach($grouped as $institution => $instData): 
            $inst_id = "inst_" . md5($institution); 
        ?>
        <div class="card institution-card overflow-hidden mb-3">
            <div class="card-header inst-header d-flex justify-content-between align-items-center toggle-header" 
                 data-bs-toggle="collapse" data-bs-target="#body_<?= $inst_id ?>" style="cursor:pointer;" aria-expanded="false">
                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2" style="color: var(--brand-navy);">
                    <i class="bi bi-chevron-right toggle-icon"></i>
                    <i class="bi bi-building me-1" style="color: var(--brand-primary);"></i> <?= htmlspecialchars($institution) ?>
                </h6>
                <span class="badge badge-navy rounded-pill px-3 py-1"><?= $instData['computer_total'] ?> PCs</span>
            </div>

            <div id="body_<?= $inst_id ?>" class="collapse">
                <div class="card-body p-3 border-top">
                    <?php foreach($instData['divisions'] as $division => $divData): 
                        $div_id = "div_" . md5($institution . $division);
                    ?>
                        <div class="division-header d-flex justify-content-between align-items-center toggle-header" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#div_body_<?= $div_id ?>" 
                            style="cursor:pointer;" aria-expanded="false">
                            
                            <div class="fw-bold d-flex align-items-center">
                                <i class="bi bi-chevron-right me-2 toggle-icon"></i>
                                <span class="text-dark me-2">
                                    <i class="bi bi-diagram-3 me-2 opacity-50"></i><?= htmlspecialchars($division) ?>
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-white text-dark border px-2 py-1 me-2" style="font-size: 0.72rem;">
                                    <?= $divData['computer_total'] ?> computers
                                </span>
                                
                                <a href="print_report.php?type=division&id=<?= $divData['id'] ?>" 
                                target="_blank" 
                                class="btn btn-navy btn-sm"
                                onclick="event.stopPropagation(); window.open(this.href, '_blank'); return false;">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> Division Report
                                </a>
                            </div>
                        </div>

                        <div id="div_body_<?= $div_id ?>" class="collapse">
                            <div class="px-2 py-2">
                                <?php foreach($divData['units'] as $unit => $unitData): 
                                    $unit_id = "unit_" . md5($institution . $division . $unit);
                                ?>
                                    <!-- LEVEL 3: UNIT COLLAPSIBLE CONTAINER -->
                                    <div class="unit-block">
                                        <div class="unit-header d-flex justify-content-between align-items-center toggle-header" 
                                             data-bs-toggle="collapse" data-bs-target="#unit_container_<?= $unit_id ?>" style="cursor:pointer;" aria-expanded="false">
                                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2" style="font-size: 0.86rem;">
                                                <i class="bi bi-chevron-right toggle-icon"></i>
                                                <i class="bi bi-geo-alt text-secondary"></i>
                                                <?= htmlspecialchars($unit) ?>
                                            </h6>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="badge bg-light text-dark border" style="font-size:0.7rem;"><?= $unitData['computer_total'] ?> PCs</span>
                                                <a href="print_unit_report.php?id=<?= $unitData['id'] ?>" 
                                                target="_blank" 
                                                class="btn btn-outline-navy btn-sm no-print px-2 py-0" 
                                                onclick="event.stopPropagation(); window.open(this.href, '_blank'); return false;">
                                                    <i class="bi bi-printer me-1"></i> Print Voucher
                                                </a>
                                            </div>
                                        </div>

                                        <div id="unit_container_<?= $unit_id ?>" class="collapse p-3 border-top bg-light">
                                            <?php foreach($unitData['models'] as $modelName => $modelData): 
                                                $model_md5 = md5($institution . $division . $unit . $modelName);
                                                
                                                $firstRow   = reset($modelData['rows']);
                                                $lowerCat   = strtolower($firstRow['category'] ?? '');
                                                $lowerItem  = strtolower($firstRow['item_name'] ?? '');

                                                if (str_contains($lowerCat, 'mouse') || str_contains($lowerItem, 'mouse')) { 
                                                    $itemIcon = 'bi-mouse3'; 
                                                } elseif (str_contains($lowerCat, 'keyboard') || str_contains($lowerItem, 'keyboard')) { 
                                                    $itemIcon = 'bi-keyboard'; 
                                                } elseif (
                                                    str_contains($lowerCat, 'computer') || 
                                                    str_contains($lowerCat, 'desktop') || 
                                                    str_contains($lowerItem, 'computer') || 
                                                    str_contains($lowerItem, 'desktop')
                                                ) { 
                                                    $itemIcon = 'bi-pc-display'; 
                                                } elseif (str_contains($lowerCat, 'monitor') || str_contains($lowerItem, 'monitor')) { 
                                                    $itemIcon = 'bi-display'; 
                                                } elseif (str_contains($lowerCat, 'printer') || str_contains($lowerItem, 'printer')) { 
                                                    $itemIcon = 'bi-printer'; 
                                                } elseif (str_contains($lowerCat, 'scanner') || str_contains($lowerItem, 'scanner')) { 
                                                    $itemIcon = 'bi-qr-code-scan'; 
                                                } elseif (
                                                    str_contains($lowerCat, 'cctv') || str_contains($lowerCat, 'camera') || 
                                                    str_contains($lowerItem, 'cctv') || str_contains($lowerItem, 'camera')
                                                ) { 
                                                    $itemIcon = 'bi-camera-video'; 
                                                } elseif (
                                                    str_contains($lowerCat, 'ups') || str_contains($lowerCat, 'battery') || str_contains($lowerCat, 'power') || 
                                                    str_contains($lowerItem, 'ups') || str_contains($lowerItem, 'battery') || str_contains($lowerItem, 'power')
                                                ) { 
                                                    $itemIcon = 'bi-lightning-charge'; 
                                                } elseif (str_contains($lowerCat, 'rack') || str_contains($lowerItem, 'rack')) { 
                                                    $itemIcon = 'bi-hdd-rack'; 
                                                } else { 
                                                    $itemIcon = 'bi-box'; 
                                                }
                                            ?>
                                                <!-- LEVEL 4: MODEL / CATEGORY COLLAPSIBLE SECTION (DEFAULT COLLAPSED) -->
                                                <div class="category-section bg-white">
                                                    <div class="category-header d-flex justify-content-between align-items-center toggle-header" 
                                                         data-bs-toggle="collapse" data-bs-target="#table_<?= $model_md5 ?>" style="cursor: pointer;" aria-expanded="false">
                                                        <span class="tracking-wider d-flex align-items-center">
                                                            <i class="bi bi-chevron-right me-2 toggle-icon"></i>
                                                            <i class="bi <?= $itemIcon ?> me-2" style="color: var(--brand-primary);"></i><?= htmlspecialchars($modelName) ?>
                                                        </span>
                                                        <span class="badge bg-secondary text-white rounded-pill" style="font-size:0.7rem;"><?= $modelData['total_qty'] ?> Qty</span>
                                                    </div>
                                                    
                                                    <div id="table_<?= $model_md5 ?>" class="collapse bg-white">
                                                        <div class="table-responsive">
                                                            <table class="table table-custom align-middle searchable-table">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 15%;" class="ps-3">ID</th>
                                                                        <th style="width: 20%;">Date</th>
                                                                        <th style="width: 35%;">Item Detail</th>
                                                                        <th style="width: 15%;">Serial / Qty</th>
                                                                        <th style="width: 15%;">Status</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach($modelData['rows'] as $row): ?>
                                                                    <tr class="report-row" data-stock-id="<?= $row['stock_detail_id'] ?>">
                                                                        <td class="ps-3 text-muted font-monospace">DSP-<?= str_pad($row['dispatch_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                                                        <td class="text-muted"><?= date("d M, Y", strtotime($row['dispatch_date'])) ?></td>
                                                                        <td class="fw-bold item-name" style="color: var(--brand-primary);">
                                                                            <a href="view_stock_details.php?highlight_id=<?= $row['stock_detail_id'] ?>" 
                                                                            class="text-decoration-none hover-link" style="color: var(--brand-primary);">
                                                                                <i class="bi <?= $itemIcon ?> small me-1"></i>
                                                                                <?= htmlspecialchars($row['model_name'] ?? $row['item_name']) ?>
                                                                            </a>
                                                                        </td>
                                                                        <td>
                                                                            <?php if(!empty($row['serial_number'])): ?>
                                                                                <span class="text-dark font-monospace fw-normal serial-text"><?= htmlspecialchars($row['serial_number']) ?></span>
                                                                            <?php else: ?>
                                                                                <span class="fw-bold" style="color: var(--brand-primary);"><?= $row['quantity'] ?></span> <small class="text-muted">Units</small>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td class="text-nowrap">
                                                                            <div class="d-flex align-items-center">
                                                                                <i class="bi bi-truck fs-6 me-1" style="color: #0d9488;"></i> 
                                                                                <span class="status-dispatched">DISPATCHED</span>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let isAllExpanded = false; 

    // Global toggle button logic
    window.handleGlobalToggle = function() {
        isAllExpanded = !isAllExpanded;
        updateToggleUI(isAllExpanded);
    };

    function updateToggleUI(show) {
        const allCollapsibles = document.querySelectorAll('#reportContent .collapse');
        const btn = document.getElementById('globalToggleBtn');
        const txt = document.getElementById('toggleText');
        const icon = btn.querySelector('i');

        allCollapsibles.forEach(el => {
            let bsCollapse = bootstrap.Collapse.getInstance(el) || new bootstrap.Collapse(el, { toggle: false });
            show ? bsCollapse.show() : bsCollapse.hide();
        });

        if (show) {
            txt.innerText = "Collapse All";
            icon.classList.replace('bi-arrows-angle-expand', 'bi-arrows-angle-contract');
            btn.classList.replace('btn-outline-navy', 'btn-navy');
        } else {
            txt.innerText = "Expand All";
            icon.classList.replace('bi-arrows-angle-contract', 'bi-arrows-angle-expand');
            btn.classList.replace('btn-navy', 'btn-outline-navy');
        }
        isAllExpanded = show;
    }

    // Dynamic Search & Smart Filtered Accordion Expansion Logic
    document.getElementById('reportSearch').addEventListener('input', function() {
        let filter = this.value.toUpperCase().trim();
        let rows = document.querySelectorAll('.report-row');

        if (filter.length === 0) {
            // Reset state: Hide all collapsibles and show all structural containers
            document.querySelectorAll('#reportContent .collapse').forEach(el => {
                let bs = bootstrap.Collapse.getInstance(el) || new bootstrap.Collapse(el, { toggle: false });
                bs.hide();
            });
            document.querySelectorAll('.report-row, .category-section, .unit-block, .division-header, .institution-card').forEach(el => {
                el.style.display = "";
            });
            updateToggleUI(false); 
            return;
        }

        // Hide structural blocks initially during search
        document.querySelectorAll('.institution-card, .unit-block, .category-section').forEach(el => {
            el.style.display = "none";
        });

        rows.forEach(row => {
            let itemName = row.querySelector('.item-name').textContent.toUpperCase();
            let serial = row.querySelector('.serial-text') ? row.querySelector('.serial-text').textContent.toUpperCase() : "";
            
            if (itemName.indexOf(filter) > -1 || serial.indexOf(filter) > -1) {
                row.style.display = "";
                
                // Show matching row's immediate section and parent blocks
                let catSection = row.closest('.category-section');
                if (catSection) catSection.style.display = "";

                let unitBlock = row.closest('.unit-block');
                if (unitBlock) unitBlock.style.display = "";

                let instCard = row.closest('.institution-card');
                if (instCard) instCard.style.display = "";

                expandMatchingParents(row);
            } else {
                row.style.display = "none";
            }
        });
    });

    // Recursively expand parent accordions for matched elements
    function expandMatchingParents(el) {
        let parent = el.closest('.collapse');
        while (parent) {
            let bsCollapse = bootstrap.Collapse.getInstance(parent) || new bootstrap.Collapse(parent, { toggle: false });
            bsCollapse.show();
            parent = parent.parentElement.closest('.collapse');
        }
    }
});

// Deep link highlight handler from stock details page
window.addEventListener("load", function(){
    const params = new URLSearchParams(window.location.search);
    const stockId = params.get("stock_id");

    if(stockId){
        setTimeout(() => {
            const rows = document.querySelectorAll(`.report-row[data-stock-id="${stockId}"]`);
            if(rows.length > 0){
                let affectedUnits = new Set();
                rows.forEach(row => {
                    let parent = row.closest('.collapse');
                    while(parent){
                        let bsCollapse = bootstrap.Collapse.getInstance(parent) || new bootstrap.Collapse(parent, { toggle: false });
                        bsCollapse.show();
                        parent = parent.parentElement.closest('.collapse');
                    }

                    row.classList.add("match-highlight");

                    if(!row.querySelector('.match-badge')){
                        row.insertAdjacentHTML("beforeend", "<span class='badge bg-warning text-dark ms-2 match-badge'>Matched</span>");
                    }

                    let unitBlock = row.closest('.unit-block');
                    if(unitBlock){ affectedUnits.add(unitBlock); }
                });

                affectedUnits.forEach(unit => { unit.classList.add("match-group-highlight"); });

                rows[0].scrollIntoView({ behavior: "smooth", block: "center" });

                document.getElementById("clearHighlightBtn").style.display = "inline-block";
            }
        }, 400);
    }
});

document.getElementById("clearHighlightBtn").addEventListener("click", function(){
    document.querySelectorAll('.match-highlight').forEach(row => {
        row.classList.remove("match-highlight");
        let badge = row.querySelector('.match-badge');
        if(badge) badge.remove();
    });
    document.querySelectorAll('.match-group-highlight').forEach(unit => {
        unit.classList.remove("match-group-highlight");
    });
    this.style.display = "none";
});
</script>

<?php
$content = ob_get_clean();
include "stocklayout.php";
?>