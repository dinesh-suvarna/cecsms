<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";

date_default_timezone_set('Asia/Kolkata'); 

$page_title = "Electrical Asset Ledger";
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
$filter_display = !empty($filter_parts) ? implode(" <i class='bi bi-chevron-right mx-2 small text-muted'></i> ", $filter_parts) : "Individual Tag-Level Inventory Report";

// 2. Fetch Data
$sql = "SELECT 
            inst.institution_name,
            d.division_name,
            u.unit_name, u.unit_code,
            ei.item_name,
            ea.asset_tag,
            es.bill_no,
            es.bill_date
        FROM electrical_assets ea
        JOIN electrical_stock es ON ea.stock_id = es.id
        JOIN electrical_items ei ON es.electrical_item_id = ei.id
        JOIN units u ON es.unit_id = u.id
        JOIN divisions d ON u.division_id = d.id
        JOIN institutions inst ON d.institution_id = inst.id
        WHERE 1=1";

if($f_inst) $sql .= " AND inst.id = '" . $conn->real_escape_string($f_inst) . "'";
if($f_dept) $sql .= " AND d.id = '" . $conn->real_escape_string($f_dept) . "'";
if($f_unit) $sql .= " AND u.id = '" . $conn->real_escape_string($f_unit) . "'";

$sql .= " ORDER BY inst.institution_name ASC, u.unit_code ASC, ei.item_name ASC";
$result = $conn->query($sql);

$report_data = [];
while($row = $result->fetch_assoc()){
    $inst = $row['institution_name'];
    $div  = $row['division_name'];
    $unit = ($row['unit_code']) ? $row['unit_code'] . " - " . $row['unit_name'] : $row['unit_name'];
    $type = $row['item_name'];
    $report_data[$inst][$div][$unit][$type][] = $row;
}

ob_start();
?>

<style>
    :root {
        --saas-bg: #f8fafc;
        --accent: #0284c7; 
        --dark-slate: #0f172a;
    }

    body { background-color: var(--saas-bg); font-family: 'Inter', sans-serif; color: #334155; }

    .glass-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .accordion-item { border: none; margin-bottom: 8px; background: transparent; }
    .accordion-button { border-radius: 10px !important; font-weight: 600; transition: all 0.2s; box-shadow: none !important; }

    .btn-inst { background: var(--dark-slate) !important; color: white !important; font-size: 1.1rem; }
    .btn-div { background: #ffffff !important; color: var(--dark-slate) !important; border: 1px solid #e2e8f0; margin-left: 1rem; width: calc(100% - 1rem); }
    .btn-unit { background: #f0f9ff !important; color: var(--accent) !important; border: 1px solid #e0f2fe; margin-left: 2rem; width: calc(100% - 2rem); }
    .btn-item { background: #ffffff !important; color: #64748b !important; border-bottom: 2px solid #f1f5f9; margin-left: 3rem; width: calc(100% - 3rem); font-size: 0.9rem; }

    .accordion-button:not(.collapsed) { 
        background: #fff !important; 
        color: var(--accent) !important;
        border-left: 5px solid var(--accent) !important;
    }

    .btn-inst:not(.collapsed) { background: var(--dark-slate) !important; color: white !important; border-left: 5px solid #0ea5e9 !important; }

    .table-modern { font-size: 0.85rem; border: 1px solid #f1f5f9; border-radius: 8px; overflow: hidden; }
    .table-modern thead { background: #f8fafc; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05rem; }
    .tag-mono { background: #f0f9ff; color: var(--accent); padding: 3px 8px; border-radius: 5px; font-family: monospace; font-weight: bold; border: 1px solid #e0f2fe; }

   
    .search-input-group { position: relative; margin-bottom: 20px; }
    .search-input-group .bi-search { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .live-search-field { padding-left: 45px !important; height: 50px; border-radius: 12px; border: 2px solid #e2e8f0; transition: border-color 0.2s; }
    .live-search-field:focus { border-color: var(--accent); box-shadow: none; }

    @media print {
        .no-print { display: none !important; }
        .accordion-collapse { display: block !important; }
        .accordion-button::after { display: none !important; }
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-end mb-4 no-print">
        <div>
            <h3 class="fw-bold text-dark mb-1">Electrical Asset Ledger</h3>
            <p class="text-muted small mb-0 d-flex align-items-center"><?= $filter_display ?></p>
        </div>
        <button type="button" onclick="window.print()" class="btn btn-white border shadow-sm px-4">
            <i class="bi bi-printer me-2"></i>Print PDF
        </button>
    </div>

    <div class="glass-card p-3 mb-4 no-print">
        <form method="GET" class="row g-3 mb-3 border-bottom pb-3">
            <div class="col-md-4">
                <label class="small fw-bold text-muted">Institution</label>
                <select name="inst" class="form-select border-0 bg-light" onchange="this.form.submit()">
                    <option value="">All Institutions</option>
                    <?php 
                    $insts = $conn->query("SELECT id, institution_name FROM institutions");
                    while($i = $insts->fetch_assoc()) echo "<option value='{$i['id']}' ".($f_inst==$i['id']?'selected':'').">{$i['institution_name']}</option>";
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="small fw-bold text-muted">Division</label>
                <select name="dept" class="form-select border-0 bg-light" onchange="this.form.submit()">
                    <option value="">All Divisions</option>
                    <?php 
                    $d_where = $f_inst ? "WHERE institution_id = '$f_inst'" : "";
                    $depts = $conn->query("SELECT id, division_name FROM divisions $d_where");
                    while($d = $depts->fetch_assoc()) echo "<option value='{$d['id']}' ".($f_dept==$d['id']?'selected':'').">{$d['division_name']}</option>";
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="small fw-bold text-muted">Unit</label>
                <select name="unit" class="form-select border-0 bg-light" onchange="this.form.submit()">
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
        </form>

        <div class="search-input-group">
            <i class="bi bi-search"></i>
            <input type="text" id="masterSearch" class="form-control live-search-field" placeholder="Search by Unit Name or Code...">
        </div>
    </div>

    <div class="accordion" id="masterAcc">
        <?php $i_idx = 0; foreach($report_data as $inst_name => $divisions): $i_idx++; 
            $is_i_active = ($f_inst && strpos($filter_display, $inst_name) !== false) || (!$f_inst && count($report_data) == 1);
        ?>
            <div class="accordion-item inst-wrapper" data-inst-name="<?= htmlspecialchars(strtolower($inst_name)) ?>">
                <h2 class="accordion-header">
                    <button class="accordion-button btn-inst <?= $is_i_active ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#inst-<?= $i_idx ?>">
                        <i class="bi bi-lightning-charge-fill me-3"></i> <?= htmlspecialchars($inst_name) ?>
                    </button>
                </h2>
                <div id="inst-<?= $i_idx ?>" class="accordion-collapse collapse <?= $is_i_active ? 'show' : '' ?>" data-bs-parent="#masterAcc">
                    <div class="accordion-body p-0 pt-2">
                        <div class="accordion accordion-flush" id="divAcc-<?= $i_idx ?>">
                            <?php $d_idx = 0; foreach($divisions as $div_name => $units): $d_idx++; 
                                $is_d_active = ($f_dept && strpos($filter_display, $div_name) !== false) || ($f_inst && !$f_dept && count($divisions) == 1);
                            ?>
                                <div class="accordion-item div-wrapper" data-div-name="<?= htmlspecialchars(strtolower($div_name)) ?>">
                                    <button class="accordion-button btn-div <?= $is_d_active ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#div-<?= $i_idx ?>-<?= $d_idx ?>">
                                        <i class="bi bi-layers me-2 text-muted"></i> <?= htmlspecialchars($div_name) ?>
                                    </button>
                                    <div id="div-<?= $i_idx ?>-<?= $d_idx ?>" class="accordion-collapse collapse <?= $is_d_active ? 'show' : '' ?>" data-bs-parent="#divAcc-<?= $i_idx ?>">
                                        <div class="accordion-body p-0 pt-2">
                                            <div class="accordion" id="unitAcc-<?= $i_idx ?>-<?= $d_idx ?>">
                                                <?php $u_idx = 0; foreach($units as $unit_name => $items_grouped): $u_idx++; 
                                                    $is_u_active = ($f_unit && strpos($unit_name, $f_unit) !== false) || ($f_dept && !$f_unit && count($units) == 1);
                                                ?>
                                                    <div class="accordion-item unit-wrapper" data-unit-name="<?= htmlspecialchars(strtolower($unit_name)) ?>">
                                                        <button class="accordion-button btn-unit <?= $is_u_active ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#unit-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>">
                                                            <i class="bi bi-geo-alt me-2 small"></i> <?= htmlspecialchars($unit_name) ?>
                                                        </button>
                                                        <div id="unit-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>" class="accordion-collapse collapse <?= $is_u_active ? 'show' : '' ?>" data-bs-parent="#unitAcc-<?= $i_idx ?>-<?= $d_idx ?>">
                                                            <div class="accordion-body p-0 pt-2">
                                                                <div class="accordion" id="itemAcc-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>">
                                                                    <?php $it_idx = 0; foreach($items_grouped as $item_name => $rows): $it_idx++; ?>
                                                                        <div class="accordion-item">
                                                                            <button class="accordion-button btn-item collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#item-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>-<?= $it_idx ?>">
                                                                                <div class="d-flex justify-content-between w-100 pe-3">
                                                                                    <span><i class="bi bi-plug me-2"></i> <?= htmlspecialchars($item_name) ?></span>
                                                                                    <span class="badge rounded-pill bg-white text-dark border"><?= count($rows) ?> Assets</span>
                                                                                </div>
                                                                            </button>
                                                                            <div id="item-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>-<?= $it_idx ?>" class="accordion-collapse collapse" data-bs-parent="#itemAcc-<?= $i_idx ?>-<?= $d_idx ?>-<?= $u_idx ?>">
                                                                                <div class="accordion-body bg-white ms-5 border rounded-3 p-3 my-2">
                                                                                    <div class="table-responsive">
                                                                                        <table class="table table-modern table-hover mb-0">
                                                                                            <thead>
                                                                                                <tr>
                                                                                                    <th class="text-center" width="50">#</th>
                                                                                                    <th>Bill Details</th>
                                                                                                    <th class="text-center">Asset Tag</th>
                                                                                                </tr>
                                                                                            </thead>
                                                                                            <tbody>
                                                                                                <?php foreach($rows as $count => $r): ?>
                                                                                                    <tr>
                                                                                                        <td class="text-center text-muted"><?= $count + 1 ?></td>
                                                                                                        <td>
                                                                                                            <div class="fw-bold"><?= htmlspecialchars($r['bill_no']) ?></div>
                                                                                                            <div class="small text-muted"><?= ($r['bill_date'] != '0000-00-00') ? date('d-m-Y', strtotime($r['bill_date'])) : 'N/A' ?></div>
                                                                                                        </td>
                                                                                                        <td class="text-center">
                                                                                                            <span class="tag-mono"><?= htmlspecialchars($r['asset_tag']) ?></span>
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

<script>
document.getElementById('masterSearch').addEventListener('input', function(e) {
    let term = e.target.value.toLowerCase().trim();
    let unitWrappers = document.querySelectorAll('.unit-wrapper');
    let divWrappers = document.querySelectorAll('.div-wrapper');
    let instWrappers = document.querySelectorAll('.inst-wrapper');

    if (term === "") {
        document.querySelectorAll('.accordion-item').forEach(el => el.style.display = 'block');
        instWrappers.forEach(inst => {
            let instCollapse = inst.querySelector(':scope > .accordion-collapse');
            if (instCollapse) bootstrap.Collapse.getOrCreateInstance(instCollapse).show();
        });
        divWrappers.forEach(div => {
            let divCollapse = div.querySelector(':scope > .accordion-collapse');
            if (divCollapse) bootstrap.Collapse.getOrCreateInstance(divCollapse).hide();
        });
        return;
    }

    unitWrappers.forEach(u => u.style.display = 'none');
    divWrappers.forEach(d => d.style.display = 'none');
    instWrappers.forEach(i => i.style.display = 'none');

    unitWrappers.forEach(unit => {
        let unitName = unit.getAttribute('data-unit-name');
        if (unitName.includes(term)) {
            unit.style.display = 'block';
            let parentDiv = unit.closest('.div-wrapper');
            let parentInst = unit.closest('.inst-wrapper');

            if(parentDiv) {
                parentDiv.style.display = 'block';
                let divCollapse = parentDiv.querySelector(':scope > .accordion-collapse');
                if (divCollapse) bootstrap.Collapse.getOrCreateInstance(divCollapse).show();
            }
            if(parentInst) {
                parentInst.style.display = 'block';
                let instCollapse = parentInst.querySelector(':scope > .accordion-collapse');
                if (instCollapse) bootstrap.Collapse.getOrCreateInstance(instCollapse).show();
            }
        }
    });
});
</script>

<?php 
$content = ob_get_clean(); 
include "../electrical_stock/electricalslayout.php"; 
?>