<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";
include "../includes/session.php";

date_default_timezone_set('Asia/Kolkata'); 

$page_title = "Detailed Computer Configuration Report";
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

// 1. Get Filters
$f_inst = $_GET['inst'] ?? '';
$f_dept = $_GET['dept'] ?? '';
$f_unit = $_GET['unit'] ?? '';
$f_search = trim($_GET['search'] ?? '');

// Dynamic Title & Header details
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
$filter_display = !empty($filter_parts) ? implode(" | ", $filter_parts) : "All Institutions & Units";

// 2. Querying Stock, Assets & Hardware Specifications based on Actual Schema
$inst_cond = $f_inst ? " AND dm.institution_id = '$f_inst'" : "";
$dept_cond = $f_dept ? " AND dm.division_id = '$f_dept'" : "";
$unit_cond = $f_unit ? " AND dm.unit_id = '$f_unit'" : "";

$search_cond = "";
if ($f_search) {
    $search_cond = " AND (
        im.item_name LIKE '%$f_search%' OR 
        m.model_name LIKE '%$f_search%' OR 
        m.processor LIKE '%$f_search%' OR 
        ss.processor LIKE '%$f_search%' OR 
        sd.serial_number LIKE '%$f_search%' OR
        da.division_asset_id LIKE '%$f_search%'
    )";
}

// Query fetching each individual unit (Row-by-Row)
$query = "
    SELECT 
        im.item_name,
        COALESCE(m.model_name, 'Generic Model') AS model_name,
        COALESCE(ss.processor, m.processor, 'N/A') AS processor,
        COALESCE(ss.ram, m.ram, 'N/A') AS ram,
        CONCAT_WS(' ', COALESCE(ss.storage_size, m.storage_size, ''), COALESCE(ss.storage_type, m.storage_type, '')) AS storage_info,
        sd.serial_number,
        COALESCE(da.division_asset_id, 'N/A') AS division_asset_id
    FROM dispatch_master dm
    JOIN dispatch_details dd ON dm.id = dd.dispatch_id
    JOIN stock_details sd ON dd.stock_detail_id = sd.id
    LEFT JOIN division_assets da ON dd.id = da.dispatch_detail_id AND sd.id = da.stock_detail_id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN item_models m ON sd.model_id = m.id
    LEFT JOIN stock_specifications ss ON sd.id = ss.stock_detail_id
    LEFT JOIN units u ON dm.unit_id = u.id
    WHERE im.category = 'Computer' $inst_cond $dept_cond $unit_cond $search_cond
    ORDER BY im.item_name ASC, da.division_asset_id ASC
";

$result = $conn->query($query);
$config_rows = [];
$total_systems = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if(trim($row['storage_info']) === '') {
            $row['storage_info'] = 'N/A';
        }
        $config_rows[] = $row;
        $total_systems++;
    }
}

ob_start();
?>

<style>
    :root {
        --theme-navy: #07116e;
        --theme-navy-light: #0d1e9e;
        --theme-navy-bg: #f4f6fb;
    }

    /* Modern Blue Filter Card Design */
    .filter-card-modern {
        background: #ffffff;
        border: none;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(7, 17, 110, 0.08);
        overflow: hidden;
    }

    .filter-card-header {
        background: linear-gradient(135deg, var(--theme-navy), var(--theme-navy-light));
        padding: 1rem 1.5rem;
        color: #ffffff;
    }

    .form-label-custom {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--theme-navy);
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .form-control-custom, .form-select-custom, .auto-resize-select {
        border-radius: 10px;
        border: 1.5px solid #dbe2ef;
        padding: 0.6rem 0.9rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #1e293b;
        background-color: #f8fafc;
        transition: width 0.15s ease-in-out, border-color 0.2s ease-in-out;
        max-width: none !important;
        min-width: 140px;
        box-sizing: border-box;
    }

    .form-control-custom:focus, .form-select-custom:focus {
        border-color: var(--theme-navy);
        box-shadow: 0 0 0 3px rgba(7, 17, 110, 0.15);
        background-color: #fff;
    }

    .btn-navy {
        background: linear-gradient(135deg, var(--theme-navy), var(--theme-navy-light));
        color: #ffffff !important;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.6rem 1.2rem;
        box-shadow: 0 4px 12px rgba(7, 17, 110, 0.2);
        transition: all 0.2s ease;
    }

    .btn-navy:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(7, 17, 110, 0.3);
    }

    .btn-outline-navy {
        background: #ffffff;
        color: var(--theme-navy) !important;
        border: 2px solid var(--theme-navy);
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.6rem 1.2rem;
        transition: all 0.2s ease;
    }

    .btn-outline-navy:hover {
        background: var(--theme-navy-bg);
        transform: translateY(-2px);
    }

    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        border-left: 4px solid var(--theme-navy);
    }

    .report-card-container {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    .table-custom {
        border-collapse: collapse !important;
        width: 100%;
        margin-bottom: 0;
    }

    .table-custom th, .table-custom td {
        border: 1px solid #cbd5e1 !important;
        padding: 8px 10px;
        color: #0f172a;
    }

    .table-custom thead {
        display: table-header-group;
    }

    .table-custom tr {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .table-custom thead th {
        background-color: var(--theme-navy-bg) !important;
        color: var(--theme-navy) !important;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.78rem;
        letter-spacing: 0.04em;
    }

    .config-badge {
        font-size: 0.75rem;
        background: #e2e8f0;
        color: #1e293b;
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-block;
        font-weight: 600;
    }

    .remarks-cell { display: none; } 

    .pdf-export .remarks-cell {
        display: table-cell !important;
        width: 15%;
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
        header, footer, nav, .sidebar, .navbar, .no-print, .btn, .topbar, #sidebar-wrapper, .nav-container, .kpi-section { 
            display: none !important; 
        }

        body, .main-content, #page-content-wrapper, .content-wrapper, #content {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background: #fff !important;
        }

        .container-fluid { width: 100% !important; max-width: 100% !important; padding: 0 !important; }
        .report-card-container { border: none !important; box-shadow: none !important; padding: 0 !important; }

        .table-custom th, .table-custom td { 
            border: 1px solid #000000 !important;
            color: #000000 !important;
        }

        .table-custom thead {
            display: table-header-group !important;
        }

        .table-custom tr {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .table-custom thead th {
            background-color: #fff !important;
            color: #000 !important;
        }

        .signature-block {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            margin-top: 50px !important;
            border: none !important;
        }

        .pdf-export .remarks-cell {
            display: table-cell !important;
        }

        .pdf-export .pdf-signature-area {
            display: block !important;
        }

        @page { 
            size: A4 landscape;
            margin: 1cm; 
        }
    }
</style>

<div class="container-fluid mt-4 mb-5">
    
    <!-- Filter Card -->
    <div class="card mb-4 no-print filter-card-modern">
        <div class="filter-card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-white d-flex align-items-center gap-2">
                <i class="bi bi-cpu"></i> Detailed Computer Hardware Configuration Report
            </h6>
            <span class="badge bg-light text-primary fw-semibold px-2 py-1">IT Inventory</span>
        </div>
        <div class="card-body p-4">
            <form method="GET" id="filterForm" class="row g-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label-custom"><i class="bi bi-building me-1"></i>Institution</label>
                    <select name="inst" class="form-select form-select-custom auto-resize-select" onchange="this.form.submit()" title="Select Institution">
                        <option value="">All Institutions</option>
                        <?php 
                        $insts = $conn->query("SELECT id, institution_name FROM institutions WHERE status='Active'");
                        while($i = $insts->fetch_assoc()) echo "<option value='{$i['id']}' ".($f_inst==$i['id']?'selected':'').">{$i['institution_name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label-custom"><i class="bi bi-diagram-3 me-1"></i>Division / Dept</label>
                    <select name="dept" class="form-select form-select-custom auto-resize-select" onchange="this.form.submit()" title="Select Division">
                        <option value="">All Divisions / Departments</option>
                        <?php 
                        $d_where = $f_inst ? "AND institution_id = '$f_inst'" : "";
                        $depts = $conn->query("SELECT id, division_name FROM divisions WHERE status='Active' $d_where");
                        while($d = $depts->fetch_assoc()) echo "<option value='{$d['id']}' ".($f_dept==$d['id']?'selected':'').">{$d['division_name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label-custom"><i class="bi bi-door-open me-1"></i>Unit / Lab</label>
                    <select name="unit" class="form-select form-select-custom auto-resize-select" onchange="this.form.submit()" title="Select Unit">
                        <option value="">All Units / Labs</option>
                        <?php 
                        $u_where = $f_dept ? "AND division_id = '$f_dept'" : "";
                        $units = $conn->query("SELECT id, unit_name, unit_code FROM units WHERE status='Active' $u_where");
                        while($u = $units->fetch_assoc()) {
                            $u_label = $u['unit_code'] ? $u['unit_code'] . " - " . $u['unit_name'] : $u['unit_name'];
                            echo "<option value='{$u['id']}' ".($f_unit==$u['id']?'selected':'').">{$u_label}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label-custom"><i class="bi bi-search me-1"></i>Search Asset ID / Serial</label>
                    <input type="text" name="search" class="form-control form-control-custom" placeholder="e.g. Asset ID, Serial, Model..." value="<?= htmlspecialchars($f_search) ?>">
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

    <!-- Analytics Card -->
    <div class="row g-3 mb-4 no-print kpi-section">
        <div class="col-md-4">
            <div class="kpi-card">
                <div class="text-muted small fw-bold text-uppercase">Total Computer Records</div>
                <div class="h3 fw-bold my-1 text-primary"><?= inr($total_systems) ?></div>
                <div class="small text-muted"><i class="bi bi-pc-display me-1"></i> Listed individual assets</div>
            </div>
        </div>
    </div>

    <!-- Report Body Container -->
    <div class="report-card-container p-4 p-md-5" id="printableReport">
        <div class="text-center mb-4">
            <img src="../admin/assets/header.PNG" alt="Header" style="width:100%; max-width:850px;" class="mb-3">
            
            <h4 class="fw-bold text-uppercase mb-1" style="color: var(--theme-navy);">Computer Hardware Specification Report</h4>
            <h6 class="text-dark fw-bold mb-1"><?= $filter_display ?></h6>
            <p class="text-muted small">Report Generated: <?= date('d-m-Y h:i A') ?></p>
        </div>

        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th class="text-center" width="5%">Sl.No</th>
                    <th width="20%">Item Name & Model</th>
                    <th width="15%">Serial Number</th>
                    <th width="16%">Asset Tag ID</th>
                    <th width="15%">Processor / CPU</th>
                    <th width="10%">RAM</th>
                    <th width="12%">Storage</th>
                    <th class="remarks-header remarks-cell">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sl = 1;
                if(!empty($config_rows)):
                    foreach($config_rows as $row): ?>
                    <tr>
                        <td class="text-center"><?= $sl++ ?></td>
                        <td class="fw-bold text-primary">
                            <?= htmlspecialchars($row['item_name']) ?>
                            <div class="small text-muted fw-normal"><?= htmlspecialchars($row['model_name']) ?></div>
                        </td>
                        <td class="font-monospace small"><?= htmlspecialchars($row['serial_number'] ?: 'N/A') ?></td>
                        <td class="fw-bold text-dark">
                            <?= htmlspecialchars($row['division_asset_id']) ?>
                        </td>
                        <td><span class="config-badge"><?= htmlspecialchars($row['processor']) ?></span></td>
                        <td><span class="config-badge"><?= htmlspecialchars($row['ram']) ?></span></td>
                        <td><span class="config-badge"><?= htmlspecialchars($row['storage_info']) ?></span></td>
                        <td class="remarks-cell"></td>
                    </tr>
                <?php endforeach; 
                else: ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No computer asset records found matching your selection.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Signatures Box -->
        <div class="d-none d-print-block pdf-signature-area signature-block">
            <div class="d-flex justify-content-between">
                <div class="text-center" style="width: 200px;">
                    <svg class="sig-line-box"><line x1="0" y1="1" x2="200" y2="1" stroke="#000000" stroke-width="1.5"/></svg>
                    <small class="fw-bold">Lab Incharge</small>
                </div>
                <div class="text-center" style="width: 200px;">
                    <svg class="sig-line-box"><line x1="0" y1="1" x2="200" y2="1" stroke="#000000" stroke-width="1.5"/></svg>
                    <small class="fw-bold">System Administrator</small>
                </div>
                <div class="text-center" style="width: 200px;">
                    <svg class="sig-line-box"><line x1="0" y1="1" x2="200" y2="1" stroke="#000000" stroke-width="1.5"/></svg>
                    <small class="fw-bold">HoD / Director</small>
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
    
    // Check if page was refreshed
    const navEntries = performance.getEntriesByType('navigation');
    const isReload = navEntries.length > 0 && navEntries[0].type === 'reload';

    if (isReload && filterForm) {
        // Reset select elements
        const dynamicDropdowns = filterForm.querySelectorAll('.auto-resize-select');
        dynamicDropdowns.forEach(select => {
            select.selectedIndex = 0;
            autoResizeSelect(select);
        });
        
        // Clear search input on hard refresh
        const searchInput = filterForm.querySelector('input[name="search"]');
        if (searchInput) searchInput.value = '';

        // Strip query parameters
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    } else {
        const dynamicDropdowns = document.querySelectorAll('.auto-resize-select');
        dynamicDropdowns.forEach(select => {
            autoResizeSelect(select);
            select.addEventListener('change', (e) => autoResizeSelect(e.target));
        });
    }
});

function downloadPDF() {
    const element = document.getElementById('printableReport');
    element.classList.add('pdf-export');
    window.print();
    setTimeout(() => {
        element.classList.remove('pdf-export');
    }, 1000);
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