<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";

date_default_timezone_set('Asia/Kolkata'); 

$page_title = "Consolidated Inventory Report";
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

// 1. Get Filters
$f_inst = $_GET['inst'] ?? '';
$f_dept = $_GET['dept'] ?? '';
$f_unit = $_GET['unit'] ?? '';

// Handle category as an array from checkboxes
$f_cats = $_GET['cat'] ?? []; 
if (!is_array($f_cats) && !empty($f_cats)) {
    $f_cats = [$f_cats];
}

// --- DYNAMIC REPORT TITLE LOGIC ---
$report_title = "Consolidated Inventory Stock Report";
if (!empty($f_cats)) {
    $cat_names = [];
    if (in_array('computer', $f_cats)) $cat_names[] = "Computer/IT";
    if (in_array('furniture', $f_cats)) $cat_names[] = "Furniture";
    if (in_array('electrical', $f_cats)) $cat_names[] = "Electrical";
    
    if (!empty($cat_names)) {
        $report_title = "Consolidated " . implode(" & ", $cat_names) . " Stock Report";
    }
}

// Dynamic badge text
if (!empty($f_cats) && count($f_cats) === 1) {
    $badge_text = ucfirst($f_cats[0]) . " Inventory";
} else {
    $badge_text = "Consolidated Inventory";
}

// --- LOGIC FOR DYNAMIC FILTER HEADER WITH UNIT CODE ---
$filter_parts = [];

if ($f_dept) {
    $res = $conn->query("SELECT division_name FROM divisions WHERE id = '$f_dept'");
    if($row = $res->fetch_assoc()) $filter_parts[] = $row['division_name'];
}
if ($f_unit) {
    $res = $conn->query("SELECT unit_code, unit_name FROM units WHERE id = '$f_unit'");
    if($row = $res->fetch_assoc()) {
        $filter_parts[] = htmlspecialchars(($row['unit_code'] ? $row['unit_code'] . " - " : "") . $row['unit_name']);
    }
}

$filter_display = !empty($filter_parts) ? implode(" | ", $filter_parts) : "All Institutions";

// 2. Fetch Consolidated Stock Counts Logic
$union_queries = [];

// Apply filter conditions
$inst_cond_dm = $f_inst ? " AND dm.institution_id = '$f_inst'" : "";
$inst_cond_d  = $f_inst ? " AND d.institution_id = '$f_inst'" : "";

$dept_cond_dm = $f_dept ? " AND dm.division_id = '$f_dept'" : "";
$dept_cond_u  = $f_dept ? " AND u.division_id = '$f_dept'" : "";

$unit_cond_dm = $f_unit ? " AND dm.unit_id = '$f_unit'" : "";
$unit_cond_fs = $f_unit ? " AND fs.unit_id = '$f_unit'" : "";
$unit_cond_es = $f_unit ? " AND es.unit_id = '$f_unit'" : "";

// Computer / IT Stock Query 
if (empty($f_cats) || in_array('computer', $f_cats)) {
    $union_queries[] = "
        SELECT 
            'Computer/IT' AS stock_type, 
            im.item_name, 
            COUNT(da.id) AS total_quantity
        FROM division_assets da
        JOIN stock_details sd ON da.stock_detail_id = sd.id
        JOIN items_master im ON sd.stock_item_id = im.id
        JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
        JOIN dispatch_master dm ON dd.dispatch_id = dm.id
        WHERE da.status = 'assigned' 
          AND sd.status = 'dispatched'
          $inst_cond_dm $dept_cond_dm $unit_cond_dm
        GROUP BY im.id, im.item_name
        HAVING total_quantity > 0
    ";
}

// Furniture Stock Query
if (empty($f_cats) || in_array('furniture', $f_cats)) {
    $union_queries[] = "
        SELECT 'Furniture' AS stock_type, fi.item_name, SUM(fs.available_qty) AS total_quantity
        FROM furniture_stock fs
        JOIN furniture_items fi ON fs.furniture_item_id = fi.id
        JOIN units u ON fs.unit_id = u.id
        JOIN divisions d ON u.division_id = d.id
        WHERE 1=1 $inst_cond_d $dept_cond_u $unit_cond_fs
        GROUP BY fi.id, fi.item_name
    ";
}

// Electrical Stock Query
if (empty($f_cats) || in_array('electrical', $f_cats)) {
    $union_queries[] = "
        SELECT 'Electrical' AS stock_type, ei.item_name, SUM(es.available_qty) AS total_quantity
        FROM electrical_stock es
        JOIN electrical_items ei ON es.electrical_item_id = ei.id
        JOIN units u ON es.unit_id = u.id
        JOIN divisions d ON u.division_id = d.id
        WHERE 1=1 $inst_cond_d $dept_cond_u $unit_cond_es
        GROUP BY ei.id, ei.item_name
    ";
}

// Combine selected categories into single UNION Query
$result = false;
if (!empty($union_queries)) {
    $full_sql = implode(" UNION ALL ", $union_queries) . " ORDER BY stock_type ASC, item_name ASC";
    $result = $conn->query($full_sql);
}

ob_start();
?>

<style>
    :root {
        --brand-primary: #123b63;
        --brand-navy: #0b2942;
        --brand-white: #ffffff;
        --bg-surface: #f3f5f7;
        --card-bg: #ffffff;
        --card-border: #d9e0e7;
        --card-border-hover: #b8c5d1;
        --text-primary: #18344d;
        --text-body: #4b5f72;
        --text-muted: #6b7c8c;
        --shadow-subtle: 0 1px 2px rgba(20, 45, 70, 0.06);
        --shadow-hover: 0 4px 12px rgba(20, 45, 70, 0.10);
        --transition-smooth: all 0.18s ease;
    }

    /* Standard ERP Filter Card Design */
    .filter-card-modern {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 6px;
        box-shadow: var(--shadow-subtle);
        overflow: hidden;
    }

    .filter-card-header {
        background-color: var(--brand-navy);
        color: var(--brand-white);
        padding: 0.75rem 1.25rem;
        border-top-left-radius: 5px;
        border-top-right-radius: 5px;
    }

    .form-label-custom {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--brand-navy);
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .form-control-custom, .form-select-custom, .auto-resize-select {
        border-radius: 4px;
        border: 1px solid var(--card-border);
        padding: 0.5rem 0.8rem;
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-primary);
        background-color: #f8fafc;
        transition: var(--transition-smooth);
        max-width: none !important;
        min-width: 140px;
        box-sizing: border-box;
    }

    .form-control-custom:focus, .form-select-custom:focus {
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 3px rgba(18, 59, 99, 0.12);
        background-color: #fff;
    }

    /* Clear Interactive Category Select/Deselect Checkbox Pills */
    .category-pills-container {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .category-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.45rem 0.85rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-body);
        background-color: #f6f8fa;
        border: 1px solid var(--card-border);
        border-radius: 4px;
        cursor: pointer;
        user-select: none;
        transition: var(--transition-smooth);
    }

    .category-pill:hover {
        border-color: var(--card-border-hover);
        background-color: #eef3f7;
        color: var(--brand-navy);
    }

    /* Style when checkbox is checked */
    .category-pill:has(.custom-check-input:checked) {
        background-color: #edf2f7;
        border-color: var(--brand-primary);
        color: var(--brand-navy);
        box-shadow: var(--shadow-subtle);
    }

    .custom-check-input {
        cursor: pointer;
        accent-color: var(--brand-primary);
        width: 16px;
        height: 16px;
        margin: 0;
    }

    /* Buttons */
    .btn-navy {
        background-color: var(--brand-primary);
        color: var(--brand-white) !important;
        border: 1px solid var(--brand-navy);
        border-radius: 4px;
        font-weight: 500;
        font-size: 0.85rem;
        padding: 0.5rem 1.2rem;
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
        border-radius: 4px;
        font-weight: 500;
        font-size: 0.85rem;
        padding: 0.5rem 1.2rem;
        transition: var(--transition-smooth);
    }

    .btn-outline-navy:hover {
        background-color: #eef3f7;
        color: var(--brand-navy) !important;
        border-color: var(--card-border-hover);
    }

    /* --- Report & Print Styles --- */
    .report-card { 
        border: none !important; 
        border-radius: 0; 
        background: #fff; 
        box-shadow: none !important;
    }
    
    .table-clean { 
        border-collapse: collapse !important; 
        width: 100%; 
    }
    
    .table-clean th, .table-clean td { 
        border: 1px solid #000000 !important; 
        padding: 8px 12px;
        color: #000000 !important;
    }
    
    .table-clean thead th { 
        background-color: #ffffff !important; 
        text-transform: uppercase; 
        font-size: 0.85rem;
        font-weight: 700;
    }
    
    .remarks-header { width: 25%; }
    .remarks-cell { display: none; } 

    .pdf-export .remarks-cell {
        display: table-cell !important;
        height: 38px;
    }

    .pdf-export .pdf-signature-area {
        display: block !important;
    }

    .sig-line-box {
        width: 100%;
        height: 2px;
        margin-bottom: 6px;
    }

    @media print {
        header, footer, nav, .sidebar, .navbar, .no-print, .btn, .topbar, #sidebar-wrapper, .nav-container { 
            display: none !important; 
        }

        body, .main-content, #page-content-wrapper, .content-wrapper, #content {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            left: 0 !important;
            position: relative !important;
            background: #fff !important;
        }

        .container-fluid { width: 100% !important; max-width: 100% !important; padding: 0 !important; }
        .report-card { border: none !important; padding: 0 !important; }

        .table-clean tr {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .table-clean thead {
            display: table-header-group !important;
        }

        .signature-block {
            page-break-before: auto !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            margin-top: 50px !important;
            padding-top: 0 !important;
            border: none !important;
        }

        .pdf-export .remarks-cell {
            display: table-cell !important;
        }

        .pdf-export .pdf-signature-area {
            display: block !important;
        }

        @page { 
            size: A4 portrait;
            margin: 1cm; 
        }
    }
</style>

<div class="container-fluid mt-4">
    <div class="card mb-4 no-print filter-card-modern">
        <div class="filter-card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold text-white d-flex align-items-center gap-2">
                <i class="bi bi-box-seam"></i> Consolidated Inventory Stock Report
            </h6>
            <span class="badge bg-light text-dark fw-semibold px-2 py-1" style="font-size: 0.72rem; border-radius: 4px;"><?= $badge_text ?></span>
        </div>
        <div class="card-body p-3">
            <form method="GET" id="filterForm" class="row g-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label-custom"><i class="bi bi-building me-1"></i>Institution</label>
                    <select name="inst" class="form-select form-select-custom auto-resize-select" onchange="this.form.submit()" title="Select Institution">
                        <option value="">All Institutions</option>
                        <?php 
                        $insts = $conn->query("SELECT id, institution_name FROM institutions");
                        while($i = $insts->fetch_assoc()) echo "<option value='{$i['id']}' ".($f_inst==$i['id']?'selected':'').">{$i['institution_name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label-custom"><i class="bi bi-diagram-3 me-1"></i>Division / Dept</label>
                    <select name="dept" class="form-select form-select-custom auto-resize-select" onchange="this.form.submit()" title="Select Division">
                        <option value="">All Divisions</option>
                        <?php 
                        $d_where = $f_inst ? "WHERE institution_id = '$f_inst'" : "";
                        $depts = $conn->query("SELECT id, division_name FROM divisions $d_where");
                        while($d = $depts->fetch_assoc()) echo "<option value='{$d['id']}' ".($f_dept==$d['id']?'selected':'').">{$d['division_name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label-custom"><i class="bi bi-door-open me-1"></i>Unit / Lab</label>
                    <select name="unit" class="form-select form-select-custom auto-resize-select" onchange="this.form.submit()" title="Select Unit">
                        <option value="">All Units</option>
                        <?php 
                        $u_where = $f_dept ? "WHERE division_id = '$f_dept'" : "";
                        $units = $conn->query("SELECT id, unit_name, unit_code FROM units $u_where");
                        while($u = $units->fetch_assoc()) {
                            $u_label = $u['unit_code'] ? $u['unit_code'] . " - " . $u['unit_name'] : $u['unit_name'];
                            echo "<option value='{$u['id']}' ".($f_unit==$u['id']?'selected':'').">{$u_label}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-xl-4 col-md-6">
                    <label class="form-label-custom"><i class="bi bi-tags me-1"></i>Categories (Select Multiple)</label>
                    <div class="category-pills-container">
                        <label class="category-pill">
                            <input type="checkbox" name="cat[]" value="computer" class="custom-check-input" onchange="this.form.submit()" <?= (empty($f_cats) || in_array('computer', $f_cats)) ? 'checked' : '' ?>>
                             IT / Comp
                        </label>
                        <label class="category-pill">
                            <input type="checkbox" name="cat[]" value="furniture" class="custom-check-input" onchange="this.form.submit()" <?= (empty($f_cats) || in_array('furniture', $f_cats)) ? 'checked' : '' ?>>
                             Furniture
                        </label>
                        <label class="category-pill"> 
                            <input type="checkbox" name="cat[]" value="electrical" class="custom-check-input" onchange="this.form.submit()" <?= (empty($f_cats) || in_array('electrical', $f_cats)) ? 'checked' : '' ?>>
                             Electrical
                        </label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-filter me-1"></i> Apply Filters
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" onclick="downloadPDF()" class="btn btn-outline-navy">
                            <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                        </button>
                        <button type="button" onclick="triggerPrint()" class="btn btn-navy">
                            <i class="bi bi-printer me-1"></i> Print Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Printable & PDF Export Area -->
    <div class="report-card p-4" id="printableReport">
        <div class="text-center mb-4">
            <img src="../admin/assets/header.PNG" alt="Header" style="width:100%; max-width:850px;" class="mb-3">
            
            <h4 class="fw-bold text-uppercase mb-1"><?= $report_title ?></h4>
            <h6 class="text-dark fw-bold mb-1"><?= $filter_display ?></h6>
            <p class="text-muted small">Report Generated: <?= date('d-m-Y h:i A') ?></p>
        </div>

        <table class="table table-bordered table-clean align-middle">
            <thead>
                <tr>
                    <th class="text-center" width="8%">Sl.No</th>
                    <th width="57%">Item Description</th>
                    <th class="text-center" width="15%">Total Quantity</th>
                    <th class="remarks-header remarks-cell">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sl = 1;
                if($result && $result->num_rows > 0):
                    while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="text-center"><?= $sl++ ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($row['item_name']) ?></td>
                        <td class="text-center fw-bold"><?= $row['total_quantity'] ?></td>
                        <td class="remarks-cell"></td>
                    </tr>
                <?php endwhile; 
                else: ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted">No records found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Clean Signatures Area -->
        <div class="d-none d-print-block pdf-signature-area signature-block">
            <div class="d-flex justify-content-between">
                <div class="text-center" style="width: 200px;">
                    <svg class="sig-line-box"><line x1="0" y1="1" x2="200" y2="1" stroke="#000000" stroke-width="1.5"/></svg>
                    <small class="fw-bold">Lab Incharge</small>
                </div>
                <div class="text-center" style="width: 200px;">
                    <svg class="sig-line-box"><line x1="0" y1="1" x2="200" y2="1" stroke="#000000" stroke-width="1.5"/></svg>
                    <small class="fw-bold">Physical Lab Incharge</small>
                </div>
                <div class="text-center" style="width: 200px;">
                    <svg class="sig-line-box"><line x1="0" y1="1" x2="200" y2="1" stroke="#000000" stroke-width="1.5"/></svg>
                    <small class="fw-bold">HoD</small>
                </div>
            </div>
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

document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filterForm');
    
    // Check if page was reloaded (Refreshed)
    const navEntries = performance.getEntriesByType('navigation');
    const isReload = navEntries.length > 0 && navEntries[0].type === 'reload';

    if (isReload && filterForm) {
        // Reset all select elements to the first default option
        const dynamicDropdowns = filterForm.querySelectorAll('.auto-resize-select');
        dynamicDropdowns.forEach(select => {
            select.selectedIndex = 0;
            autoResizeSelect(select);
        });
        
        // Reset category checkboxes
        const checkboxes = filterForm.querySelectorAll('.custom-check-input');
        checkboxes.forEach(cb => cb.checked = true);
        
        // Strip the query parameters from URL without reloading
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    } else {
        // Standard load
        const dynamicDropdowns = document.querySelectorAll('.auto-resize-select');
        dynamicDropdowns.forEach(select => {
            autoResizeSelect(select);
            select.addEventListener('change', (e) => autoResizeSelect(e.target));
        });
    }
});

// Download PDF Action
function downloadPDF() {
    const element = document.getElementById('printableReport');
    element.classList.add('pdf-export');

    window.print();

    setTimeout(() => {
        element.classList.remove('pdf-export');
    }, 1000);
}

// Direct Print Action
function triggerPrint() {
    const element = document.getElementById('printableReport');
    element.classList.remove('pdf-export');
    window.print();
}
</script>

<?php 
$content = ob_get_clean(); 
include "../admin/adminlayout.php"; 
?>