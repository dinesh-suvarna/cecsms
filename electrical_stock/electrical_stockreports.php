<?php
include "../config/db.php";
include "../includes/session.php";

date_default_timezone_set('Asia/Kolkata'); 

$page_title = "Consolidated Electrical Report";
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

// 1. Get Filters
$f_inst = $_GET['inst'] ?? '';
$f_dept = $_GET['dept'] ?? '';
$f_unit = $_GET['unit'] ?? '';

// --- LOGIC FOR DYNAMIC FILTER HEADER ---
$filter_parts = [];

if ($f_inst) {
    $res = $conn->query("SELECT institution_name FROM institutions WHERE id = '$f_inst'");
    if($row = $res->fetch_assoc()) $filter_parts[] = $row['institution_name'];
}
if ($f_dept) {
    $res = $conn->query("SELECT division_name FROM divisions WHERE id = '$f_dept'");
    if($row = $res->fetch_assoc()) $filter_parts[] = $row['division_name'];
}
if ($f_unit) {
    $res = $conn->query("SELECT unit_code, unit_name FROM units WHERE id = '$f_unit'");
    if($row = $res->fetch_assoc()) {
        $filter_parts[] = htmlspecialchars($row['unit_code'] . " - " . $row['unit_name']);
    }
}

$filter_display = !empty($filter_parts) ? implode(" | ", $filter_parts) : "All Institutions";

// 2. Build Grouped Query for Electrical Stock
$sql = "SELECT 
            ei.item_name,
            SUM(s.total_qty) as total_quantity
        FROM electrical_stock s
        JOIN electrical_items ei ON s.electrical_item_id = ei.id
        JOIN units u ON s.unit_id = u.id
        JOIN divisions d ON u.division_id = d.id
        JOIN institutions inst ON d.institution_id = inst.id
        WHERE 1=1";

if($f_inst) $sql .= " AND inst.id = '$f_inst'";
if($f_dept) $sql .= " AND d.id = '$f_dept'";
if($f_unit) $sql .= " AND u.id = '$f_unit'";

$sql .= " GROUP BY ei.item_name ORDER BY ei.item_name ASC";
$result = $conn->query($sql);

ob_start();
?>

<style>
    .report-card { border: 1px solid #dee2e6; border-radius: 8px; background: #fff; }
    .table thead { background-color: #f0f7ff; color: #000; text-transform: uppercase; font-size: 0.8rem; }
    
    .remarks-header { width: 25%; }
    .remarks-cell { display: none; } 

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
        }

        .container-fluid { width: 100% !important; max-width: 100% !important; padding: 0 !important; }
        .report-card { border: none !important; padding: 0 !important; }

        .remarks-cell { 
            display: table-cell !important; 
            border-bottom: 1px dotted #999 !important; 
            height: 40px; 
        }

        @page { margin: 0.8cm; }
    }
</style>

<div class="container-fluid mt-4">
    <div class="card mb-4 no-print border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select name="inst" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Institutions</option>
                        <?php 
                        $insts = $conn->query("SELECT id, institution_name FROM institutions");
                        while($i = $insts->fetch_assoc()) echo "<option value='{$i['id']}' ".($f_inst==$i['id']?'selected':'').">{$i['institution_name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="dept" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Divisions</option>
                        <?php 
                        $d_where = $f_inst ? "WHERE institution_id = '$f_inst'" : "";
                        $depts = $conn->query("SELECT id, division_name FROM divisions $d_where");
                        while($d = $depts->fetch_assoc()) echo "<option value='{$d['id']}' ".($f_dept==$d['id']?'selected':'').">{$d['division_name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="unit" class="form-select form-select-sm" onchange="this.form.submit()">
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
                <div class="col-md-3 text-end">
                    <button type="button" onclick="window.print()" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-printer me-2"></i>Print Electrical Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="report-card p-4">
        <div class="text-center mb-4">
            <img src="../admin/assets/header.PNG" alt="Header" style="width:100%; max-width:850px;" class="mb-3">
            <h4 class="fw-bold text-uppercase mb-1">Consolidated Electrical Stock Report</h4>
            
            <h6 class="text-primary fw-bold mb-1"><?= $filter_display ?></h6>
            
            <p class="text-muted small">Report Generated: <?= date('d-m-Y h:i A') ?></p>
        </div>

        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th class="text-center" width="8%">Sl.No</th>
                    <th width="50%">Electrical Item Description</th>
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
                        <td class="text-center text-muted"><?= $sl++ ?></td>
                        <td class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></td>
                        <td class="text-center">
                            <span class="fw-bold text-dark"><?= $row['total_quantity'] ?></span>
                        </td>
                        <td class="remarks-cell"></td>
                    </tr>
                <?php endwhile; 
                else: ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted">No electrical records found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="d-none d-print-block mt-5">
            <div class="d-flex justify-content-between">
                <div class="text-center" style="width: 200px;">
                    <hr class="mb-1">
                    <small>Verified By</small>
                </div>
                <div class="text-center" style="width: 200px;">
                    <hr class="mb-1">
                    <small>Authorized Signatory</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
include "../electrical_stock/electricalslayout.php"; 
?>