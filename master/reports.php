<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";

$page_title = "Inventory Reports";
if ($_SESSION['role'] !== 'SuperAdmin') { header("Location: ../index.php"); exit(); }

// 1. Get Filters
$f_inst = $_GET['inst'] ?? '';
$f_dept = $_GET['dept'] ?? '';
$f_unit = $_GET['unit'] ?? '';

// 2. Build Query
$sql = "SELECT * FROM report_inventory_list WHERE 1=1";
if($f_inst) $sql .= " AND institution = (SELECT institution_name FROM institutions WHERE id = '$f_inst')";
if($f_dept) $sql .= " AND department = (SELECT division_name FROM divisions WHERE id = '$f_dept')";
if($f_unit) $sql .= " AND unit = (SELECT unit_name FROM units WHERE id = '$f_unit')";
$sql .= " ORDER BY item_name ASC";

$result = $conn->query($sql);

// 3. Fetch data for header display
$unit_data = ($f_unit) ? $conn->query("SELECT unit_name, unit_code FROM units WHERE id='$f_unit'")->fetch_assoc() : null;
$unit_display_header = ($unit_data) ? $unit_data['unit_code'] . " - " . $unit_data['unit_name'] : "All Units";

$display_inst = "All Institutions";
if ($f_inst) {
    $res = $conn->query("SELECT institution_name FROM institutions WHERE id='$f_inst'");
    if ($row = $res->fetch_assoc()) {
        $display_inst = $row['institution_name'];
    }
}

$display_dept = "All Departments";
if ($f_dept) {
    $res = $conn->query("SELECT division_name FROM divisions WHERE id='$f_dept'");
    if ($row = $res->fetch_assoc()) {
        $display_dept = $row['division_name'];
    }
}

ob_start();
?>

<style>
    :root {
        --emerald-600: #059669;
        --emerald-700: #047857;
        --slate-900: #0f172a;
    }

    .report-card { border-radius: 12px; border: 1px solid #e2e8f0 !important; background: #fff; }
    .table thead { background-color: var(--slate-900); color: white; text-transform: uppercase; font-size: 0.75rem; }
    .table th { padding: 15px 12px !important; }
    .table td { padding: 12px !important; border-color: #f1f5f9 !important; }

    .header-info-bar {
        background: linear-gradient(to right, var(--emerald-600), var(--emerald-700));
        color: white;
        border-radius: 8px;
    }
    
    .btn-emerald {
        background-color: var(--emerald-600);
        border-color: var(--emerald-600);
        color: #fff !important;
        transition: all 0.2s ease;
    }

    .btn-emerald:hover {
        background-color: var(--emerald-700);
        border-color: var(--emerald-700);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2);
        transform: translateY(-1px);
    }

    
    @media print {
    .no-print, .sidebar, .navbar, .header-container, .top-nav, header, nav { 
        display: none !important; 
    }

    .filter-summary-box { 
        display: none !important; 
    }

    body { background: #fff !important; padding: 0 !important; }
    .container-fluid { width: 100% !important; padding: 0 !important; margin: 0 !important; }
    .report-card { border: none !important; box-shadow: none !important; }
    
    .header-info-bar { 
        background: #f8fafc !important; 
        color: black !important; 
        border: 1px solid #e2e8f0 !important; 
        
        -webkit-print-color-adjust: exact; 
        print-color-adjust: exact;         

    .asset-id-badge {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

</style>

<div class="container-fluid mt-4">
    <div class="card mb-4 no-print shadow-sm border-emerald-100">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="small fw-bold text-muted mb-1">Institution</label>
                    <select name="inst" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Institutions</option>
                        <?php 
                        $insts = $conn->query("SELECT id, institution_name FROM institutions");
                        while($i = $insts->fetch_assoc()) echo "<option value='{$i['id']}' ".($f_inst==$i['id']?'selected':'').">{$i['institution_name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-muted mb-1">Department</label>
                    <select name="dept" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php 
                        $d_where = $f_inst ? "WHERE institution_id = '$f_inst'" : "";
                        $depts = $conn->query("SELECT id, division_name FROM divisions $d_where");
                        while($d = $depts->fetch_assoc()) echo "<option value='{$d['id']}' ".($f_dept==$d['id']?'selected':'').">{$d['division_name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-muted mb-1">Unit / Lab</label>
                    <select name="unit" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Units</option>
                        <?php 
                        $u_where = $f_dept ? "WHERE division_id = '$f_dept'" : "";
                        $units = $conn->query("SELECT id, unit_name FROM units $u_where");
                        while($u = $units->fetch_assoc()) echo "<option value='{$u['id']}' ".($f_unit==$u['id']?'selected':'').">{$u['unit_name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <button type="button" onclick="window.print()" class="btn btn-emerald btn-sm px-4">
                        <i class="bi bi-printer-fill me-2"></i>Print Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card report-card border-0">
        <div class="card-header bg-white border-0 pt-4 pb-0 text-center">
            <img src="../admin/assets/header.PNG" alt="Header" style="width:100%; max-width:850px; height:auto;" class="mb-3">

            <h3 class="fw-bold text-dark mb-1 text-uppercase">Inventory Master Report</h3>
            <p class="text-muted small mb-4">Generated: <?= date('d M, Y • h:i A') ?></p>
            
            <div class="row m-0 py-3 header-info-bar shadow-sm">
                <div class="col-4 small border-end border-white border-opacity-25">
                    <span class="opacity-75 text-uppercase" style="font-size: 0.65rem;">Institution</span><br>
                    <strong><?= $display_inst ?></strong>
                </div>
                <div class="col-4 small border-end border-white border-opacity-25">
                    <span class="opacity-75 text-uppercase" style="font-size: 0.65rem;">Department</span><br>
                    <strong><?= $display_dept ?></strong>
                </div>
                <div class="col-4 small">
                    <span class="opacity-75 text-uppercase" style="font-size: 0.65rem;">Lab / Facilities</span><br>
                    <strong><?= $unit_display_header ?></strong>
                </div>
            </div>
        </div>

        <div class="card-body px-0 pt-4">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="text-center" width="60">SL.No</th>
                        <th>Item Details</th>
                        <th>Serial Number</th>
                        <th>Configuration</th>
                        <th class="text-center">Asset Id</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sl = 1;
                    if($result && $result->num_rows > 0):
                        while($row = $result->fetch_assoc()): ?>
                        <tr class="align-middle">
                            <td class="text-center text-muted fw-bold"><?= $sl++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= $row['item_name'] ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;"><?= $row['model_name'] ?></div>
                            </td>
                            <td class="font-monospace" style="font-size: 0.8rem;"><?= $row['serial_number'] ?></td>
                            <td class="small text-muted"><?= $row['hardware_config'] ?: 'Standard' ?></td>
                            <td class="text-center">
                                <span><?= $row['asset_id'] ?></span>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
include "../admin/adminlayout.php"; 
?>