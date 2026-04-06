<?php
include "../config/db.php";
include "../includes/session.php";

// Set correct Timezone at the top
date_default_timezone_set('Asia/Kolkata'); 

$page_title = "Furniture Inventory Reports";
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

// 1. Get Filters
$f_inst = $_GET['inst'] ?? '';
$f_dept = $_GET['dept'] ?? '';
$f_unit = $_GET['unit'] ?? '';

// 2. Build Query - Corrected Unique Aliases
$sql = "SELECT 
            fa.asset_tag, 
            fi.item_name,
            fs.bill_no,
            fs.bill_date,
            u.unit_name,
            u.unit_code,
            d.division_name as department,
            inst.institution_name as institution
        FROM furniture_assets fa
        JOIN furniture_stock fs ON fa.stock_id = fs.id
        JOIN furniture_items fi ON fs.furniture_item_id = fi.id
        JOIN units u ON fs.unit_id = u.id
        JOIN divisions d ON u.division_id = d.id
        JOIN institutions inst ON d.institution_id = inst.id
        WHERE 1=1";

if($f_inst) $sql .= " AND inst.id = '$f_inst'";
if($f_dept) $sql .= " AND d.id = '$f_dept'";
if($f_unit) $sql .= " AND u.id = '$f_unit'";

$sql .= " ORDER BY fi.item_name ASC";

$result = $conn->query($sql);

// 3. Header Display Labels
$unit_data = ($f_unit) ? $conn->query("SELECT unit_name, unit_code FROM units WHERE id='$f_unit'")->fetch_assoc() : null;
$unit_display_header = ($unit_data) ? $unit_data['unit_code'] . " - " . $unit_data['unit_name'] : "All Units";

$display_inst = "All Institutions";
if ($f_inst) {
    $res = $conn->query("SELECT institution_name FROM institutions WHERE id='$f_inst'");
    if ($row = $res->fetch_assoc()) { $display_inst = $row['institution_name']; }
}

$display_dept = "All Departments";
if ($f_dept) {
    $res = $conn->query("SELECT division_name FROM divisions WHERE id='$f_dept'");
    if ($row = $res->fetch_assoc()) { $display_dept = $row['division_name']; }
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
    }

    .font-monospace { font-family: 'Monaco', 'Consolas', monospace; font-weight: bold; }

    @media print {
        .no-print, .sidebar, .navbar { display: none !important; }
        body { background: #fff !important; }
        .header-info-bar { 
            background: #f8fafc !important; 
            color: black !important; 
            border: 1px solid #e2e8f0 !important; 
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<div class="container-fluid mt-4">
    <div class="card mb-4 no-print shadow-sm border-0">
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
            <img src="../admin/assets/header.PNG" alt="Header" style="width:100%; max-width:850px;" class="mb-3">
            <h3 class="fw-bold text-dark mb-1 text-uppercase">Furniture Inventory Master Report</h3>
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
                    <span class="opacity-75 text-uppercase" style="font-size: 0.65rem;">Unit / Lab</span><br>
                    <strong><?= $unit_display_header ?></strong>
                </div>
            </div>
        </div>

        <div class="card-body px-0 pt-4">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="text-center" width="80">SL.No</th>
                        <th>Furniture Description</th>
                        <th>Bill No.</th>
                        <th class="text-center">Asset Tag</th>
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
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></div>
                            </td>
                            
                            <td>
                                <div class="fw-medium"><?= htmlspecialchars($row['bill_no']) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;">
                                    Date: <?= ($row['bill_date'] != '0000-00-00') ? date('d-m-Y', strtotime($row['bill_date'])) : 'N/A' ?>
                                </div>
                            </td>
                            
                            <td class="text-center">
                                <span class="font-monospace text-primary fw-bold"><?= htmlspecialchars($row['asset_tag']) ?></span>
                            </td>
                        </tr>
                    <?php endwhile; 
                    else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No records found matching your criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
include "../furniture_stock/furniturelayout.php"; 
?>