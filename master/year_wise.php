<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";

date_default_timezone_set('Asia/Kolkata'); 

$page_title = "Year-Wise Inventory Report";


$role = $_SESSION['role'] ?? '';
$session_division_id = $_SESSION['division_id'] ?? null;

$is_admin_view = ($role === 'SuperAdmin');


if (!isset($_SESSION['role']) || (!$is_admin_view && empty($session_division_id))) {
    header("Location: ../index.php");
    exit();
}
 
if (!$is_admin_view) {
    $f_inst = '';
    $f_dept = $session_division_id;
} else {
    $f_inst = $_GET['inst'] ?? '';
    $f_dept = $_GET['dept'] ?? '';
}
$f_unit = $_GET['unit'] ?? '';
$f_year = $_GET['year'] ?? '';


$f_cats = $_GET['cat'] ?? []; 
if (!is_array($f_cats) && !empty($f_cats)) {
    $f_cats = [$f_cats];
}


$report_title = "Year-Wise Inventory Stock Report";
if (!empty($f_cats)) {
    $cat_names = [];
    if (in_array('computer', $f_cats)) $cat_names[] = "Computer/IT";
    if (in_array('furniture', $f_cats)) $cat_names[] = "Furniture";
    if (in_array('electrical', $f_cats)) $cat_names[] = "Electrical";
    
    if (!empty($cat_names)) {
        $report_title = "Year-Wise " . implode(" & ", $cat_names) . " Stock Report";
    }
}


if (!empty($f_cats) && count($f_cats) === 1) {
    $badge_text = ucfirst($f_cats[0]) . " Year-Wise Summary";
} else {
    $badge_text = "Year-Wise Consolidated Summary";
}


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
if ($f_year) {
    $filter_parts[] = "Year: " . htmlspecialchars($f_year);
}

$filter_display = !empty($filter_parts) ? implode(" | ", $filter_parts) : ($is_admin_view ? "All Institutions" : "Division Inventory");

$union_queries = [];

$inst_cond_dm = $f_inst ? " AND dm.institution_id = '$f_inst'" : "";
$dept_cond_dm = $f_dept ? " AND dm.division_id = '$f_dept'" : "";
$unit_cond_dm = $f_unit ? " AND dm.unit_id = '$f_unit'" : "";
$year_cond_sd = $f_year ? " AND sd.procurement_year = '$f_year'" : "";

// Computer / IT Stock Query with Procurement Year Grouping
if (empty($f_cats) || in_array('computer', $f_cats)) {
    $union_queries[] = "
        SELECT 
            'Computer/IT' AS stock_type, 
            COALESCE(sd.procurement_year, 'Unassigned') AS proc_year,
            im.item_name, 
            COUNT(da.id) AS total_quantity
        FROM division_assets da
        JOIN stock_details sd ON da.stock_detail_id = sd.id
        JOIN items_master im ON sd.stock_item_id = im.id
        JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
        JOIN dispatch_master dm ON dd.dispatch_id = dm.id
        WHERE da.status = 'assigned' 
          AND sd.status = 'dispatched'
          $inst_cond_dm $dept_cond_dm $unit_cond_dm $year_cond_sd
        GROUP BY sd.procurement_year, im.id, im.item_name
        HAVING total_quantity > 0
    ";
}

$result = false;
if (!empty($union_queries)) {
    $full_sql = implode(" UNION ALL ", $union_queries) . " ORDER BY proc_year DESC, stock_type ASC, item_name ASC";
    $result = $conn->query($full_sql);
}

$grouped_by_year = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $grouped_by_year[$row['proc_year']][] = $row;
    }
}

ob_start();
?>

<style>
    :root {
        --brand-primary: #123b63;
        --brand-navy: #0b2942;
        --brand-white: #ffffff;
        --card-border: #d9e0e7;
    }

    .filter-card-modern {
        background: #ffffff;
        border: 1px solid var(--card-border);
        border-radius: 6px;
        box-shadow: 0 1px 2px rgba(20, 45, 70, 0.06);
        overflow: hidden;
    }

    .filter-card-header {
        background-color: var(--brand-navy);
        color: var(--brand-white);
        padding: 0.75rem 1.25rem;
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

   
    .form-select-custom, .auto-resize-select {
        border-radius: 4px;
        border: 1px solid var(--card-border);
        padding: 0.5rem 0.8rem;
        font-size: 0.85rem;
        font-weight: 500;
        color: #18344d;
        background-color: #f8fafc;
        transition: all 0.18s ease;
        max-width: none !important;
        min-width: 140px;
        box-sizing: border-box;
    }

    .form-select-custom:focus, .auto-resize-select:focus {
        border-color: var(--brand-primary);
        box-shadow: 0 0 0 3px rgba(18, 59, 99, 0.12);
        background-color: #fff;
    }

    .category-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.45rem 0.85rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: #4b5f72;
        background-color: #f6f8fa;
        border: 1px solid var(--card-border);
        border-radius: 4px;
        cursor: pointer;
    }

    .btn-navy {
        background-color: var(--brand-primary);
        color: var(--brand-white) !important;
        border: 1px solid var(--brand-navy);
        border-radius: 4px;
        font-size: 0.85rem;
        padding: 0.5rem 1.2rem;
    }

    .btn-outline-navy {
        background-color: #ffffff;
        color: var(--brand-primary) !important;
        border: 1px solid var(--card-border);
        border-radius: 4px;
        font-size: 0.85rem;
        padding: 0.5rem 1.2rem;
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

    .year-header-row {
        background-color: #e2e8f0 !important;
        font-weight: bold;
    }

    .remarks-cell { display: none; } 
    .pdf-export .remarks-cell { display: table-cell !important; height: 38px; }
    .pdf-export .pdf-signature-area { display: block !important; }
    .sig-line-box { width: 100%; height: 2px; margin-bottom: 6px; }

    @media print {
        header, footer, nav, .sidebar, .navbar, .no-print, .btn, .topbar { display: none !important; }
        body, .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; background: #fff !important; }
        .table-clean tr { page-break-inside: avoid !important; }
        .signature-block { page-break-inside: avoid !important; margin-top: 50px !important; }
        .pdf-export .remarks-cell { display: table-cell !important; }
        .pdf-export .pdf-signature-area { display: block !important; }
        @page { size: A4 portrait; margin: 1cm; }
    }
</style>

<div class="container-fluid mt-4">
    <!-- Filter Card -->
    <div class="card mb-4 no-print filter-card-modern">
        <div class="filter-card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold text-white d-flex align-items-center gap-2">
                <i class="bi bi-calendar-range"></i> Year-Wise Consolidated Inventory Report
            </h6>
            <span class="badge bg-light text-dark fw-semibold px-2 py-1" style="font-size: 0.72rem; border-radius: 4px;"><?= $badge_text ?></span>
        </div>
        <div class="card-body p-3">
            <form method="GET" id="filterForm" class="p-3">
                
                <?php if ($is_admin_view): ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label-custom"><i class="bi bi-building me-1"></i>Institution</label>
                        <select name="inst" class="form-select auto-resize-select w-100" onchange="this.form.submit()">
                            <option value="">All Institutions</option>
                            <?php 
                            $insts =$conn->query("SELECT id, institution_name FROM institutions");
                            while($i =$insts->fetch_assoc()) echo "<option value='{$i['id']}' ".($f_inst==$i['id']?'selected':'').">{$i['institution_name']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom"><i class="bi bi-diagram-3 me-1"></i>Division / Dept</label>
                        <select name="dept" class="form-select auto-resize-select w-100" onchange="this.form.submit()">
                            <option value="">All Divisions</option>
                            <?php 
                            $d_where =$f_inst ? "WHERE institution_id = '$f_inst'" : "";
                            $depts =$conn->query("SELECT id, division_name FROM divisions $d_where");
                            while($d =$depts->fetch_assoc()) echo "<option value='{$d['id']}' ".($f_dept==$d['id']?'selected':'').">{$d['division_name']}</option>";
                            ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label-custom"><i class="bi bi-door-open me-1"></i>Unit / Lab</label>
                        <select name="unit" class="form-select auto-resize-select w-100" onchange="this.form.submit()">
                            <option value="">All Units</option>
                            <?php 
                            $u_where = $is_admin_view ? ($f_dept ? "WHERE division_id = '$f_dept'" : "") : "WHERE division_id = '$session_division_id'";
                            $units =$conn->query("SELECT id, unit_name, unit_code FROM units $u_where");
                            while($u = $units->fetch_assoc()) {$u_label = $u['unit_code'] ?$u['unit_code'] . " - " . $u['unit_name'] :$u['unit_name'];
                                echo "<option value='{$u['id']}' ".($f_unit==$u['id']?'selected':'').">{$u_label}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label-custom"><i class="bi bi-calendar-event me-1"></i>Procurement Year</label>
                        <select name="year" class="form-select auto-resize-select w-100" onchange="this.form.submit()">
                            <option value="">All Years</option>
                            <?php 
                            $years =$conn->query("SELECT DISTINCT procurement_year FROM stock_details WHERE procurement_year IS NOT NULL ORDER BY procurement_year DESC");
                            while($y =$years->fetch_assoc()) {
                                echo "<option value='{$y['procurement_year']}' ".($f_year==$y['procurement_year']?'selected':'').">{$y['procurement_year']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-custom"><i class="bi bi-tags me-1"></i>Categories</label>
                        <div class="category-pills-container">
                            <label class="category-pill">
                                <input type="checkbox" name="cat[]" value="computer" class="form-check-input" onchange="this.form.submit()" <?= (empty($f_cats) || in_array('computer',$f_cats)) ? 'checked' : '' ?>>
                                IT / Comp
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top flex-wrap gap-2">
                    <button type="submit" class="btn btn-navy"><i class="bi bi-filter me-1"></i> Apply Filters</button>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" onclick="exportToExcel()" class="btn btn-outline-navy"><i class="bi bi-file-earmark-excel me-1"></i> Export Excel</button>
                        <button type="button" onclick="downloadPDF()" class="btn btn-outline-navy"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</button>
                        <button type="button" onclick="triggerPrint()" class="btn btn-navy"><i class="bi bi-printer me-1"></i> Print Report</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Printable Report Section -->
    <div class="card p-4 border-0 shadow-none bg-white" id="printableReport">
        <div class="text-center mb-4">
            <img src="../admin/assets/header.PNG" alt="Header" style="width:100%; max-width:850px;" class="mb-3">
            <h4 class="fw-bold text-uppercase mb-1"><?= $report_title ?></h4>
            <h6 class="text-dark fw-bold mb-1"><?= $filter_display ?></h6>
            <p class="text-muted small">Report Generated: <?= date('d-m-Y h:i A') ?></p>
        </div>

        <table class="table table-bordered table-clean align-middle" id="reportTable">
            <thead>
                <tr>
                    <th class="text-center" width="8%">Sl.No</th>
                    <th width="57%">Item Description</th>
                    <th class="text-center" width="15%">Quantity</th>
                    <th class="remarks-header remarks-cell" width="20%">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sl = 1;
                if (!empty($grouped_by_year)):
                    foreach ($grouped_by_year as $year =>$rows): 
                        $year_total = array_sum(array_column($rows, 'total_quantity'));
                ?>
                    <tr class="year-header-row">
                        <td colspan="2" class="text-dark fw-bold">
                            <i class="bi bi-calendar-event me-2"></i>Procurement Year: <?= htmlspecialchars($year) ?>
                        </td>
                        <td class="text-center fw-bold text-dark">Total: <?= $year_total ?></td>
                        <td class="remarks-cell"></td>
                    </tr>
                    <?php foreach ($rows as$row): ?>
                    <tr>
                        <td class="text-center"><?= $sl++ ?></td>
                        <td class="ps-4 fw-semibold"><?= htmlspecialchars($row['item_name']) ?></td>
                        <td class="text-center fw-bold"><?= $row['total_quantity'] ?></td>
                        <td class="remarks-cell"></td>
                    </tr>
                    <?php endforeach; ?>
                <?php 
                    endforeach;
                else: 
                ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted">No records found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Signatures Area -->
        <div class="d-none d-print-block pdf-signature-area signature-block mt-5">
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
    const dynamicDropdowns = document.querySelectorAll('.auto-resize-select');
    dynamicDropdowns.forEach(select => {
        autoResizeSelect(select);
        select.addEventListener('change', (e) => autoResizeSelect(e.target));
    });
});

function exportToExcel() {
    let htmlContent = document.getElementById('printableReport').innerHTML;
    let blob = new Blob(['\ufeff' + htmlContent], { type: 'application/vnd.ms-excel' });
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = 'Year_Wise_Inventory_Report.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function downloadPDF() {
    const element = document.getElementById('printableReport');
    element.classList.add('pdf-export');
    window.print();
    setTimeout(() => { element.classList.remove('pdf-export'); }, 1000);
}

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