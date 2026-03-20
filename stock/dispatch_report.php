<?php
ob_start();
include "../config/db.php";
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
    dd.quantity, si.item_name, sd.serial_number, si.category,
    i.institution_name, dm.institution_id,
    d.division_name, dm.division_id,
    un.unit_name, dm.unit_id
FROM dispatch_details dd
LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
LEFT JOIN stock_details sd ON dd.stock_detail_id = sd.id
LEFT JOIN items_master si ON sd.stock_item_id = si.id
LEFT JOIN institutions i ON dm.institution_id = i.id
LEFT JOIN divisions d ON dm.division_id = d.id
LEFT JOIN units un ON dm.unit_id = un.id
$where
ORDER BY dm.id DESC";

$result = $conn->query($query);

/* Grouping Logic */
$grouped = [];
while($row = $result->fetch_assoc()){
    $inst = $row['institution_name'] ?? 'Unknown';
    $div  = $row['division_name'] ?? 'Unknown';
    $unit = $row['unit_name'] ?? 'Unknown';

    $grouped[$inst]['id'] = $row['institution_id'];
    $grouped[$inst]['computer_total'] ??= 0;
    
    $grouped[$inst]['divisions'][$div]['id'] = $row['division_id'];
    $grouped[$inst]['divisions'][$div]['computer_total'] ??= 0;

    $grouped[$inst]['divisions'][$div]['units'][$unit]['id'] = $row['unit_id'];
    $grouped[$inst]['divisions'][$div]['units'][$unit]['computer_total'] ??= 0;
    $grouped[$inst]['divisions'][$div]['units'][$unit]['rows'][] = $row;

    $qty = !empty($row['serial_number']) ? 1 : (int)$row['quantity'];
    if($row['category'] === 'Computer'){
        $grouped[$inst]['computer_total'] += $qty;
        $grouped[$inst]['divisions'][$div]['computer_total'] += $qty;
        $grouped[$inst]['divisions'][$div]['units'][$unit]['computer_total'] += $qty;
    }
}
?>

<div class="container mt-4">
    <div class="sticky-top no-print" style="top: 0; z-index: 1050; background: #f8f9fa; padding-top: 10px; padding-bottom: 10px;">
    <div class="card shadow border-0 rounded-3">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end border-bottom pb-3 mb-3">
                <div class="col-md-3">
                    <label class="small fw-bold text-muted">From</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= $from_date ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-muted">To</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= $to_date ?>">
                </div>
                <div class="col-md-4">
                    <label class="small fw-bold text-muted">Institution</label>
                    <select name="institution_id" class="form-select form-select-sm">
                        <option value="">All Institutions</option>
                        <?php $institutions->data_seek(0); while($inst_row = $institutions->fetch_assoc()): ?>
                            <option value="<?= $inst_row['id'] ?>" <?= $institution_filter == $inst_row['id'] ? 'selected' : '' ?>><?= $inst_row['institution_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm w-100 shadow-sm">Apply</button>
                </div>
            </form>

            <div class="row g-2 align-items-center">
                <div class="col-md-9">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="reportSearch" class="form-control bg-light border-start-0 ps-0" placeholder="Type to search">
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <button type="button" id="globalToggleBtn" class="btn btn-outline-secondary btn-sm w-100" onclick="handleGlobalToggle()">
                        <i class="bi bi-arrows-angle-expand me-1"></i> <span id="toggleText">Expand All</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
   
</div>

    <div id="reportContent">
        <?php foreach($grouped as $institution => $instData): 
            $inst_id = "inst_" . md5($institution); 
        ?>
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-3 institution-card">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center toggle-header border-start border-4 border-primary" 
                 data-bs-toggle="collapse" data-bs-target="#body_<?= $inst_id ?>" style="cursor:pointer;">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-caret-right-fill me-2 toggle-icon small text-muted"></i>
                    <i class="bi bi-building me-1 text-primary"></i> <?= htmlspecialchars($institution) ?>
                </h5>
                <span class="badge bg-light text-primary border border-primary rounded-pill"><?= $instData['computer_total'] ?> PCs</span>
            </div>

            <div id="body_<?= $inst_id ?>" class="collapse">
                <div class="card-body p-0 border-top">
                    <?php foreach($instData['divisions'] as $division => $divData): 
                        $div_id = "div_" . md5($institution . $division);
                    ?>
                        <div class="division-header d-flex justify-content-between align-items-center toggle-header" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#div_body_<?= $div_id ?>" 
                            style="cursor:pointer;">
                            
                            <div class="fw-bold d-flex align-items-center">
                                <i class="bi bi-caret-right-fill me-3 toggle-icon"></i>
                                <span class="text-dark">
                                    <i class="bi bi-diagram-3 me-2 opacity-50"></i><?= htmlspecialchars($division) ?>
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill bg-white text-dark border px-3 py-2 me-2">
                                    <?= $divData['computer_total'] ?> computers
                                </span>
                                
                                <a href="print_report.php?type=division&id=<?= $divData['id'] ?>" 
                                target="_blank" 
                                class="btn btn-primary btn-sm"
                                onclick="event.stopPropagation(); window.open(this.href, '_blank'); return false;">
                                    <i class="bi bi-file-earmark-pdf me-2"></i> Division Report
                                </a>
                            </div>
                        </div>

                        <div id="div_body_<?= $div_id ?>" class="collapse">
                            <div class="px-4 py-3 bg-white">
                                <?php foreach($divData['units'] as $unit => $unitData): 
                                    $unit_id = "unit_" . md5($institution . $division . $unit);
                                ?>
                                    <div class="unit-block mb-3 ps-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2 toggle-header" 
                                             data-bs-toggle="collapse" data-bs-target="#unit_table_<?= $unit_id ?>" style="cursor:pointer;">
                                            <h6 class="fw-bold text-dark mb-0">
                                                <i class="bi bi-caret-right-fill me-1 toggle-icon small"></i>
                                                <?= htmlspecialchars($unit) ?>
                                            </h6>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="text-muted small"><?= $unitData['computer_total'] ?> PCs</span>
                                                <a href="print_unit_report.php?id=<?= $unitData['id'] ?>" 
                                                target="_blank" 
                                                class="btn btn-outline-info btn-xs no-print px-2 py-0" 
                                                onclick="event.stopPropagation(); window.open(this.href, '_blank'); return false;">
                                                <i class="bi bi-printer me-1"></i> Print Voucher
                                                </a>
                                            </div>
                                        </div>

                                        <div id="unit_table_<?= $unit_id ?>" class="collapse">
                                            <div class="table-responsive rounded-3 border bg-white mt-2">
                                                <table class="table table-hover mb-0 align-middle searchable-table">
                                                    <thead class="table-light">
                                                        <tr class="small text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">
                                                            <th style="width: 15%;" class="ps-3">ID</th>
                                                            <th style="width: 20%;">Date</th>
                                                            <th style="width: 35%;">Item Name</th>
                                                            <th style="width: 15%;">Serial / Qty</th>
                                                            <th style="width: 15%;">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach($unitData['rows'] as $row): ?>
                                                        <tr style="font-size: 0.85rem;" class="report-row">
                                                            <td class="ps-3 text-muted">DSP-<?= str_pad($row['dispatch_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                                            <td class="text-muted"><?= date("d M, Y", strtotime($row['dispatch_date'])) ?></td>
                                                            <td class="fw-bold text-dark item-name"><?= htmlspecialchars($row['item_name']) ?></td>
                                                            <td>
                                                                <?php if(!empty($row['serial_number'])): ?>
                                                                    <span class=" text-dark font-monospace fw-normal serial-text"><?= htmlspecialchars($row['serial_number']) ?></span>
                                                                <?php else: ?>
                                                                    <span class="fw-bold text-primary"><?= $row['quantity'] ?></span> <small class="text-muted">Units</small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-nowrap">
                                                                <div class="d-flex align-items-center">
                                                                    <i class="bi bi-truck text-emerald fs-5 me-2"></i> 
                                                                    <span class="text-emerald fw-bold" style="letter-spacing: 0.3px;">DISPATCHED</span>
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
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let isAllExpanded = false; // Track state for the toggle button

    // 1. BOOTSTRAP COLLAPSE LISTENERS (Icons & Glitch Fix)
    const collapseElements = document.querySelectorAll('.collapse');
    collapseElements.forEach(el => {
        el.addEventListener('show.bs.collapse', function (e) {
            e.stopPropagation();
            this.style.overflow = 'hidden'; // Restored glitch fix
            const header = document.querySelector(`[data-bs-target="#${this.id}"]`);
            if (header) {
                const icon = header.querySelector('.toggle-icon');
                if (icon) icon.classList.replace('bi-caret-right-fill', 'bi-chevron-down');
            }
        });

        el.addEventListener('hide.bs.collapse', function (e) {
            e.stopPropagation();
            const header = document.querySelector(`[data-bs-target="#${this.id}"]`);
            if (header) {
                const icon = header.querySelector('.toggle-icon');
                if (icon) icon.classList.replace('bi-chevron-down', 'bi-caret-right-fill');
            }
        });
    });

    // 2. UNIFIED TOGGLE BUTTON LOGIC
    window.handleGlobalToggle = function() {
        isAllExpanded = !isAllExpanded;
        updateToggleUI(isAllExpanded);
    };

    function updateToggleUI(show) {
        const allCollapsibles = document.querySelectorAll('.collapse');
        const btn = document.getElementById('globalToggleBtn');
        const txt = document.getElementById('toggleText');
        const icon = btn.querySelector('i');

        allCollapsibles.forEach(el => {
            let bsCollapse = bootstrap.Collapse.getInstance(el) || new bootstrap.Collapse(el, { toggle: false });
            show ? bsCollapse.show() : bsCollapse.hide();
        });

        // Update Button Appearance
        if (show) {
            txt.innerText = "Collapse All";
            icon.classList.replace('bi-arrows-angle-expand', 'bi-arrows-angle-contract');
            btn.classList.replace('btn-outline-secondary', 'btn-secondary');
        } else {
            txt.innerText = "Expand All";
            icon.classList.replace('bi-arrows-angle-contract', 'bi-arrows-angle-expand');
            btn.classList.replace('btn-secondary', 'btn-outline-secondary');
        }
        isAllExpanded = show;
    }

    // 3. SMART LIVE SEARCH LOGIC
    document.getElementById('reportSearch').addEventListener('input', function() {
        let filter = this.value.toUpperCase();
        let rows = document.querySelectorAll('.report-row');

        // IF SEARCH IS EMPTY: Collapse all and reset rows
        if (filter.length === 0) {
            updateToggleUI(false); 
            rows.forEach(row => row.style.display = ""); 
            return;
        }

        // IF SEARCH HAS VALUE: Show matches and expand parents
        rows.forEach(row => {
            let itemName = row.querySelector('.item-name').textContent.toUpperCase();
            let serial = row.querySelector('.serial-text') ? row.querySelector('.serial-text').textContent.toUpperCase() : "";
            
            if (itemName.indexOf(filter) > -1 || serial.indexOf(filter) > -1) {
                row.style.display = "";
                expandParents(row);
            } else {
                row.style.display = "none";
            }
        });
    });

    // Helper to expand parents during search
    function expandParents(el) {
        let parent = el.closest('.collapse');
        while(parent) {
            let bsCollapse = bootstrap.Collapse.getInstance(parent) || new bootstrap.Collapse(parent, { toggle: false });
            bsCollapse.show();
            parent = parent.parentElement.closest('.collapse');
        }
    }
});
</script>

<style>
/* 1. BRAND COLORS & ROOT VARIABLES */
:root {
    --brand-emerald: #10b981;    /* Vibrant accent */
    --brand-forest: #065f46;     /* High-contrast readable text */
    --brand-hover: rgba(16, 185, 129, 0.05);
    --div-bg: #f9fafb;           /* Ultra-light grey for division */
}

/* 2. LAYOUT & STICKY HEADER */
html { overflow-y: scroll; scrollbar-gutter: stable; }

.sticky-top {
    background-color: #f8f9fa; 
    padding: 10px 0 5px 0;
    z-index: 1030 !important;
    transition: all 0.3s ease;
}

.sticky-top .card {
    box-shadow: 0 8px 30px rgba(0,0,0,0.12) !important;
    border: 1px solid rgba(16, 185, 129, 0.1) !important;
    border-bottom: 2px solid var(--brand-emerald) !important;
}

/* 3. GROUPING HIERARCHY */
.institution-card { 
    border: 1px solid #eef0f3 !important;
     transition: box-shadow 0.3s ease;
}

.division-header { 
    background-color: var(--div-bg) !important; 
    border-left: 4px solid #6c757d !important;
    border-bottom: 1px solid #f3f4f6;
}

.unit-block { 
    border-left: 3px solid var(--brand-emerald); 
    transition: background 0.2s; 
}
.unit-block:hover { background-color: var(--brand-hover); }

/* 4. ANIMATION & TABLE FIXES (The Glitch Fix) */
.collapse { 
    transition: height 0.35s cubic-bezier(0.4, 0, 0.2, 1); 
    will-change: height; 
}
.collapsing { 
    position: relative;
    height: 0;
    overflow: hidden !important; 
    transition: height 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.searchable-table {
    table-layout: fixed !important; 
    width: 100% !important;
}

/* 5. UI ELEMENTS & TEXT */
.text-forest { color: var(--brand-forest) !important; }
.text-emerald { color: var(--brand-emerald) !important; }
.bg-emerald { background-color: var(--brand-emerald) !important; }
.badge.bg-primary { background-color: var(--brand-emerald) !important; }

.btn-primary { 
    background-color: var(--brand-emerald) !important; 
    border-color: var(--brand-emerald) !important; 
}
.border-primary {
    --bs-border-opacity: 1;
    border-color: rgb(16 185 129) !important;
}
.btn-primary:hover { background-color: #059669 !important; }

/* MODERN ARROW ANIMATION */
.toggle-icon {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.8rem;
    color: #64748b; /* Muted slate */
}

.division-header .btn {
    position: relative;
    z-index: 10; /* Ensures the button sits "above" the collapse trigger layer */
    white-space: nowrap;
}

/* Rotation logic for when the parent is NOT collapsed */
[aria-expanded="true"] .toggle-icon {
    transform: rotate(90deg);
    color: var(--brand-forest);
}

/* DIVISION HEADER: Modern Slate Bar */
.division-header {
    background-color: #f1f5f9 !important; /* Modern Light Slate */
    border-left: 5px solid #475569 !important;
    border-radius: 6px;
    margin: 8px 0;
    padding: 12px 20px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
}

.division-header:hover {
    background-color: #e2e8f0 !important;
}

.division-header .btn {
    white-space: nowrap;
    font-weight: 500;
    transition: transform 0.2s ease;
}

.division-header .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1) !important;
}

/* Make sure the badge looks clean next to the button */
.division-header .badge {
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

/* UNIT DATA BLOCK: The "Clean Nest" Look */
.unit-block {
    background-color: #ffffff;
    border: 1px solid #eef0f3;
    border-left: 4px solid var(--brand-emerald);
    border-radius: 8px;
    margin: 5px 0 15px 30px; /* Indented to show hierarchy */
    padding: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04); /* Soft "Floating" effect */
}

/* Ensure the table inside units looks integrated */
.unit-block .table {
    margin-bottom: 0;
    background: transparent;
}

.unit-block .table thead th {
    background-color: #f8fafc;
    border-top: none;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
}

/* Status Row Icon Animation */
.report-row:hover .bi-truck {
    transform: translateX(3px);
    transition: transform 0.2s ease-in-out;
    display: inline-block;
}

/* 6. SCROLLBAR & PRINT */
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: #f1f1f1; }
::-webkit-scrollbar-thumb { background: var(--brand-emerald); border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #059669; }

@media print { 
    .no-print { display: none !important; }
    .collapse { display: block !important; height: auto !important; overflow: visible !important; }
    .toggle-icon { display: none !important; }
    .sticky-top { position: static !important; }
}
</style>



<?php
$content = ob_get_clean();
include "stocklayout.php";
?>