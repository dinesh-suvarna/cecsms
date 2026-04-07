<?php
include "../config/db.php";
include "../includes/session.php";

date_default_timezone_set('Asia/Kolkata'); 

$page_title = "Furniture Inventory Master Report";
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

// 1. Get Filters
$f_inst = $_GET['inst'] ?? '';
$f_dept = $_GET['dept'] ?? '';
$f_unit = $_GET['unit'] ?? '';

// --- DYNAMIC HEADER LOGIC ---
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
    if($row = $res->fetch_assoc()) $filter_parts[] = $row['unit_code'] . " - " . $row['unit_name'];
}
$filter_display = !empty($filter_parts) ? implode(" | ", $filter_parts) : "All Institutions";

// 2. Fetch Data Grouped
$sql = "SELECT 
            inst.institution_name,
            d.division_name,
            u.unit_name,
            u.unit_code,
            fi.item_name,
            'Furniture' as item_type,
            fa.asset_tag,
            fs.bill_no,
            fs.bill_date
        FROM furniture_assets fa
        JOIN furniture_stock fs ON fa.stock_id = fs.id
        JOIN furniture_items fi ON fs.furniture_item_id = fi.id
        JOIN units u ON fs.unit_id = u.id
        JOIN divisions d ON u.division_id = d.id
        JOIN institutions inst ON d.institution_id = inst.id
        WHERE 1=1";

if($f_inst) $sql .= " AND inst.id = '" . $conn->real_escape_string($f_inst) . "'";
if($f_dept) $sql .= " AND d.id = '" . $conn->real_escape_string($f_dept) . "'";
if($f_unit) $sql .= " AND u.id = '" . $conn->real_escape_string($f_unit) . "'";

$sql .= " ORDER BY inst.institution_name, d.division_name, u.unit_name, fi.item_name ASC";
$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . $conn->error);
}

$report_data = [];
while($row = $result->fetch_assoc()){
    $inst = $row['institution_name'];
    $div  = $row['division_name'];
    $unit = ($row['unit_code']) ? $row['unit_code'] . " - " . $row['unit_name'] : $row['unit_name'];
    $type = $row['item_name']; // Grouping by the specific item name as the "Type"
    $report_data[$inst][$div][$unit][$type][] = $row;
}

ob_start();
?>

<style>
    .report-card { border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; }
    
    /* Nesting Styles */
    .inst-header { background: #0f172a !important; color: white !important; font-weight: 700; }
    .div-header { background: #f1f5f9 !important; color: #1e293b !important; font-weight: 700; padding-left: 2.5rem !important; }
    .unit-header { background: #f8fafc !important; color: #0284c7 !important; font-weight: 600; padding-left: 4rem !important; }
    .type-header { background: #ffffff !important; color: #475569 !important; font-weight: 600; padding-left: 5.5rem !important; font-size: 0.9rem; }

    .accordion-button:not(.collapsed) { box-shadow: none; }
    .accordion-button:focus { box-shadow: none; border: none; }
    
    /* Clean up borders for nested items */
    .accordion-item { border: 1px solid #e2e8f0 !important; margin-bottom: 2px; }

    @media print {
        header, footer, nav, .sidebar, .navbar, .no-print, .btn, .topbar { display: none !important; }
        .accordion-collapse { display: block !important; }
        .accordion-button::after { display: none !important; }
        .accordion-button { background: none !important; color: black !important; padding: 5px 0 !important; }
        .report-card { border: none !important; }
    }
</style>

<div class="container-fluid mt-4">
    <div class="card mb-4 no-print shadow-sm border-0">
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
                            $label = ($u['unit_code']) ? $u['unit_code']." - ".$u['unit_name'] : $u['unit_name'];
                            echo "<option value='{$u['id']}' ".($f_unit==$u['id']?'selected':'').">{$label}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <button type="button" onclick="window.print()" class="btn btn-dark btn-sm px-4">
                        <i class="bi bi-printer me-2"></i>Print Master Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="report-card p-4">
        <div class="text-center mb-4">
            <img src="../admin/assets/header.PNG" alt="Header" style="width:100%; max-width:850px;" class="mb-3">
            <h4 class="fw-bold text-uppercase mb-1">Furniture Inventory Master Report</h4>
            <h6 class="text-dark fw-bold mb-1"><?= $filter_display ?></h6>
            <p class="text-muted small">Report Generated: <?= date('d-m-Y h:i A') ?></p>
        </div>

        <div class="accordion" id="masterAccordion">
            <?php $i_idx = 0; foreach($report_data as $inst_name => $divisions): $i_idx++; ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button inst-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#inst-<?= $i_idx ?>">
                            <i class="bi bi-bank me-2"></i> <?= htmlspecialchars($inst_name) ?>
                        </button>
                    </h2>
                    <div id="inst-<?= $i_idx ?>" class="accordion-collapse collapse" data-bs-parent="#masterAccordion">
                        <div class="accordion-body p-0">
                            
                            <div class="accordion accordion-flush" id="divAccordion-<?= $i_idx ?>">
                                <?php $d_idx = 0; foreach($divisions as $div_name => $units): $d_idx++; ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button div-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#div-<?= $i_idx ?>-<?= $d_idx ?>">
                                                 <?= htmlspecialchars($div_name) ?>
                                            </button>
                                        </h2>
                                        <div id="div-<?= $i_idx ?>-<?= $d_idx ?>" class="accordion-collapse collapse" data-bs-parent="#divAccordion-<?= $i_idx ?>">
                                            <div class="accordion-body p-0">

                                                <div class="accordion accordion-flush" id="unitAccordion-<?= $i_idx ?>-<?= $d_idx ?>">
                                                    <?php $u_idx = 0; foreach($units as $unit_name => $types): $u_idx++; ?>
                                                        <div class="accordion-item">
                                                            <h2 class="accordion-header">
                                                                <button class="accordion-button unit-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#unit-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>">
                                                                     <?= htmlspecialchars($unit_name) ?>
                                                                </button>
                                                            </h2>
                                                            <div id="unit-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>" class="accordion-collapse collapse" data-bs-parent="#unitAccordion-<?= $i_idx ?>-<?= $d_idx ?>">
                                                                <div class="accordion-body p-0">

                                                                    <div class="accordion accordion-flush" id="typeAccordion-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>">
                                                                        <?php $t_idx = 0; foreach($types as $type_name => $items): $t_idx++; ?>
                                                                            <div class="accordion-item">
                                                                                <h2 class="accordion-header">
                                                                                    <button class="accordion-button type-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#type-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>-<?= $t_idx ?>">
                                                                                        <i class="bi bi-box-seam me-2"></i> <?= htmlspecialchars($type_name) ?> (<?= count($items) ?>)
                                                                                    </button>
                                                                                </h2>
                                                                                <div id="type-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>-<?= $t_idx ?>" class="accordion-collapse collapse" data-bs-parent="#typeAccordion-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>">
                                                                                    <div class="p-3">
                                                                                        <div class="table-responsive">
                                                                                            <table class="table table-bordered table-sm mb-0">
                                                                                                <thead>
                                                                                                    <tr>
                                                                                                        <th class="text-center" width="50">Sl</th>
                                                                                                        <th>Bill No & Date</th>
                                                                                                        <th class="text-center">Asset Tag</th>
                                                                                                    </tr>
                                                                                                </thead>
                                                                                                <tbody>
                                                                                                    <?php foreach($items as $idx => $row): ?>
                                                                                                        <tr>
                                                                                                            <td class="text-center small"><?= $idx + 1 ?></td>
                                                                                                            <td class="small">
                                                                                                                <b><?= htmlspecialchars($row['bill_no']) ?></b><br>
                                                                                                                <span class="text-muted"><?= ($row['bill_date'] != '0000-00-00') ? date('d-m-Y', strtotime($row['bill_date'])) : 'N/A' ?></span>
                                                                                                            </td>
                                                                                                            <td class="text-center font-monospace small fw-bold text-primary">
                                                                                                                <?= htmlspecialchars($row['asset_tag']) ?>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                    <?php endforeach; ?>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
include "../furniture_stock/furniturelayout.php"; 
?>