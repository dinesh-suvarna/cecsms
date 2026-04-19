<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

$page_title = "Labs & Facilities";

$error = "";
$success = "";

/* ================= HELPER FUNCTION FOR BADGE COLORS ================= */
function getUnitBadgeClass($type) {
    $type = strtolower($type);
    switch ($type) {
        case 'lab': return 'bg-primary text-white';
        case 'classroom': return 'bg-success text-white';
        case 'hodcabin': return 'bg-info text-dark';
        case 'staffroom': return 'bg-warning text-dark';
        case 'office': return 'bg-secondary text-white';
        case 'library': return 'bg-dark text-white';
        default: return 'bg-light text-muted border';
    }
}
// function getUnitBadgeClass($type) {
//     return 'bg-primary bg-opacity-10 text-primary border-0';
// }

/* ================= ADD UNIT LOGIC ================= */
if(isset($_POST['add_unit'])){
    $division_id = intval($_POST['division_id']);
    $unit_code = strtoupper(trim($_POST['unit_code']));
    $unit_name = ucwords(strtolower(trim($_POST['unit_name'])));
    $unit_type = $_POST['unit_type'];
    $location = trim($_POST['location']);
    $area_sqmt = !empty($_POST['area_sqmt']) ? floatval($_POST['area_sqmt']) : NULL;

    if(empty($unit_name) || empty($division_id)){
        $_SESSION['error'] = "Unit name and Division are required.";
    } else {
        $check = $conn->prepare("SELECT id, status FROM units WHERE division_id=? AND (LOWER(unit_name)=LOWER(?) OR LOWER(unit_code)=LOWER(?))");
        $check->bind_param("iss", $division_id, $unit_name, $unit_code);
        $check->execute();
        $resultCheck = $check->get_result();

        if($resultCheck->num_rows > 0){
            $row = $resultCheck->fetch_assoc();
            if($row['status'] == 'Active'){
                $_SESSION['error'] = "Unit already exists.";
            } else {
                $restore = $conn->prepare("UPDATE units SET status='Active', unit_type=?, location=?, area_sqmt=? WHERE id=?");
                $restore->bind_param("ssdi", $unit_type, $location, $area_sqmt, $row['id']);
                $restore->execute();
                $_SESSION['success'] = "Unit restored successfully.";
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO units (division_id, unit_code, unit_name, unit_type, location, area_sqmt) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssd", $division_id, $unit_code, $unit_name, $unit_type, $location, $area_sqmt);
            $stmt->execute();
            $_SESSION['success'] = "Unit added successfully.";
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$success = $_SESSION['success'] ?? "";
$error = $_SESSION['error'] ?? "";
unset($_SESSION['success'], $_SESSION['error']);

/* ================= DATA PREPARATION ================= */
$where = " WHERE 1 ";
$params = [];
$types = "";
if($role !== 'SuperAdmin'){
    $where .= " AND i.id=? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

$sql = "SELECT u.*, d.division_name, d.id AS div_id, i.institution_name, i.id AS inst_id
        FROM units u
        JOIN divisions d ON u.division_id=d.id
        JOIN institutions i ON d.institution_id=i.id
        $where
        ORDER BY i.institution_name, d.division_name, u.unit_name";

$stmt = $conn->prepare($sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$typeCounts = [];
$tQuery = $conn->query("SELECT division_id, unit_type, COUNT(*) as total FROM units WHERE status = 'Active' GROUP BY division_id, unit_type");
while($tRow = $tQuery->fetch_assoc()){
    $typeCounts[$tRow['division_id']][] = ['type' => $tRow['unit_type'], 'count' => $tRow['total']];
}

$instDivCounts = [];
$idvQuery = $conn->query("SELECT institution_id, COUNT(*) as total FROM divisions GROUP BY institution_id");
while($cRow = $idvQuery->fetch_assoc()){ $instDivCounts[$cRow['institution_id']] = $cRow['total']; }

ob_start(); 
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm rounded-4 border-0 h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-plus-circle text-primary me-2"></i>Add Facility</h5>
                
                <?php if($success): ?> <div class="alert alert-success small py-2"><?= $success ?></div> <?php endif; ?>
                <?php if($error): ?> <div class="alert alert-danger small py-2"><?= $error ?></div> <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="small fw-bold">Institution</label>
                        <select id="institution" class="form-select form-select-sm" required <?= $role !== 'SuperAdmin' ? 'disabled' : '' ?>>
                            <option value="">Select Institution</option>
                            <?php
                            $res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
                            while($iRow = $res->fetch_assoc()){ 
                                $sel = ($iRow['id'] == $user_institution_id) ? 'selected' : '';
                                echo "<option value='{$iRow['id']}' $sel>{$iRow['institution_name']}</option>"; 
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold">Department</label>
                        <select name="division_id" id="division" class="form-select form-select-sm" required>
                            <option value="">Select Department</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="small fw-bold">Code</label>
                            <input type="text" name="unit_code" class="form-control form-control-sm" placeholder="CSL01">
                        </div>
                        <div class="col-8">
                            <label class="small fw-bold">Name</label>
                            <input type="text" name="unit_name" class="form-control form-control-sm" placeholder="Computer Lab 01" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold">Type</label>
                        <select name="unit_type" class="form-select form-select-sm">
                            <option value="lab">Lab</option>
                            <option value="office">Office</option>
                            <option value="store">Store</option>
                            <option value="classroom">Classroom</option>
                            <option value="hodcabin">Hod Cabin</option>
                            <option value="staffroom">Staff Room</option>
                            <option value="library">Library</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold">Location</label>
                        <input type="text" name="location" class="form-control form-control-sm" placeholder="Block A, 2nd Floor">
                    </div>

                    <div class="mb-4">
                        <label class="small fw-bold">Area (Sq. Mt.)</label>
                        <input type="number" step="0.01" name="area_sqmt" class="form-control form-control-sm">
                    </div>

                    <button name="add_unit" class="btn btn-primary w-100 rounded-pill fw-bold">Save Facility</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm rounded-4 border-0 p-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h4 class="fw-bold mb-0">All Labs & Facilities</h4>
                    <!-- <p class="text-muted small mb-0">Settings • Management • <span class="text-primary fw-bold"><?= $result->num_rows ?> Units</span></p> -->
                </div>
                <button id="collapseAllBtn" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm">
                    <i class="bi bi-arrows-collapse me-1"></i> Collapse All
                </button>
            </div>

            <div class="row g-2 mb-4">
                <div class="col-12">
                    <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                        <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                        <input type="text" id="unitSearch" class="form-control border-0 py-3" placeholder="Search facility code, name, or location...">
                        <button class="btn btn-white border-0 text-muted px-3" id="resetSearch"><i class="bi bi-arrow-clockwise fs-5"></i></button>
                    </div>
                </div>
            </div>

            <div class="accordion accordion-flush" id="instAccordion">
                <?php
                $currentInst = ''; $currentDiv = ''; $firstInst = true; $firstDiv = true;

                if($result->num_rows > 0):
                    while($row = $result->fetch_assoc()):
                        $inst = $row['institution_name'];
                        $div  = $row['division_name'];
                        $instId = "inst_" . md5($inst);
                        $divId = "div_" . $row['div_id'];

                        if($currentInst != $inst):
                            if(!$firstInst) echo '</tbody></table></div></div></div></div></div></div>'; 

                        // Inside your while loop
$unit_type = strtolower($row['unit_type']);

// Define a mapping for colors
$badge_styles = [
    'lab'       => 'background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;', // Refined Blue
    'classroom' => 'background: #f0fdf4; color: #166534; border: 1px solid #dcfce7;', // Green
    'office'    => 'background: #fff7ed; color: #9a3412; border: 1px solid #ffedd5;', // Orange
    'store'     => 'background: #fafafa; color: #525252; border: 1px solid #e5e5e5;', // Gray
    'library'   => 'background: #1e293b; color: #ffffff; border: 1px solid #0f172a;', // Dark
];

// Fallback style if type doesn't match
$current_style = $badge_styles[$unit_type] ?? 'background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;';
                ?>
                    <div class="accordion-item border border-light rounded-4 mb-3 overflow-hidden shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button inst-header collapsed px-4" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $instId ?>">
                                <div class="d-flex align-items-center w-100">
                                    <div class="icon-box me-3 bg-primary bg-opacity-10 p-2 rounded-3 text-primary"><i class="bi bi-building"></i></div>
                                    <span class="fw-bold text-dark"><?= $inst ?></span>
                                    <span class="badge bg-primary bg-opacity-10 text-primary ms-auto me-3 rounded-pill py-2 px-3"><?= $instDivCounts[$row['inst_id']] ?? 0 ?> Depts</span>
                                </div>
                            </button>
                        </h2>
                        <div id="<?= $instId ?>" class="accordion-collapse collapse" data-bs-parent="#instAccordion">
                            <div class="accordion-body p-3 bg-light bg-opacity-50">
                                <div class="accordion accordion-flush" id="divAcc_<?= $instId ?>">
                <?php 
                            $currentInst = $inst; $currentDiv = ''; $firstInst = false; $firstDiv = true;
                        endif;

                        if($currentDiv != $div):
                            if(!$firstDiv) echo '</tbody></table></div></div></div>'; 
                ?>
                    <div class="accordion-item border-0 shadow-sm rounded-3 mb-2 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button div-header collapsed bg-white px-4" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $divId ?>">
                                <div class="d-flex align-items-center flex-wrap gap-2 w-100">
                                    <i class="bi bi-folder2 text-muted me-2"></i>
                                    <span class="fw-semibold text-dark me-auto"><?= $div ?></span>
                                   

<div class="d-flex gap-1 flex-wrap pe-3">
    <?php 
    if(isset($typeCounts[$row['div_id']])):
        foreach($typeCounts[$row['div_id']] as $tInfo): 
            $count = $tInfo['count'];
            $typeLabel = ucfirst($tInfo['type']);
            
            // Pluralization logic: add 's' if count is not 1
            // Handles "Room" -> "Rooms", "Lab" -> "Labs" etc.
            $displayName = ($count == 1) ? $typeLabel : $typeLabel . 's';
            
            // Specific case for "Library" -> "Libraries" if you use that type
            if($count != 1 && strtolower($tInfo['type']) == 'library') {
                $displayName = "Libraries";
            }
    ?>
        <span class="badge bg-light text-muted border fw-normal" style="font-size: 0.65rem; border-radius: 6px;">
            <?= $count ?> <?= $displayName ?>
        </span>
    <?php 
        endforeach;
    endif; 
    ?>
</div>
                                </div>
                            </button>
                        </h2>
                        <div id="<?= $divId ?>" class="accordion-collapse collapse" data-bs-parent="#divAcc_<?= $instId ?>">
                            <div class="accordion-body p-0 bg-white">
                                <table class="table table-hover mb-0 align-middle inner-unit-table" style="font-size: 0.85rem;">
                                    <thead class="bg-light text-muted">
                                        <tr>
                                            <th>Code / Name</th>
                                            <th>Location / Type</th>
                                            <th>Area</th>
                                            <th class="text-end pe-4">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                <?php 
                            $currentDiv = $div; $firstDiv = false;
                        endif; 
                ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="text-primary fw-bold" style="font-size: 0.75rem;"><?= $row['unit_code'] ?></div>
                                <div class="fw-semibold text-dark"><?= $row['unit_name'] ?></div>
                            </td>
                            <td>
    <div class="small text-dark"><?= $row['location'] ?: '<span class="text-muted">N/A</span>' ?></div>
    <span class="badge-saas" style="<?= $current_style ?> font-size: 0.7rem; padding: 2px 8px; border-radius: 6px; display: inline-block; margin-top: 4px;">
        <?= ucfirst($row['unit_type']) ?>
    </span>
</td>
                            
                            <td class="fw-medium text-muted"><?= $row['area_sqmt'] ? $row['area_sqmt']." m²" : "-" ?></td>
                            <td class="text-end pe-4">
                                <a href="edit_unit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light border-0 text-warning"><i class="bi bi-pencil-square"></i></a>
                                <button type="button" class="btn btn-sm btn-light border-0 <?= $row['status'] == 'Active' ? 'text-danger' : 'text-success' ?>" 
                                        onclick="handleStatus(<?= $row['id'] ?>, '<?= addslashes($row['unit_name']) ?>', '<?= $row['status'] == 'Active' ? 'Deactivate' : 'Activate' ?>')">
                                    <i class="bi <?= $row['status'] == 'Active' ? 'bi-trash' : 'bi-check-circle' ?>"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php echo '</tbody></table></div></div></div></div></div></div>'; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted bg-light rounded-4"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>No units registered yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // 1. Initialize DataTables for inner tables (10 rows pagination)
    $('.inner-unit-table').DataTable({
        "pageLength": 10,
        "dom": 'tp', // Only show table and pagination
        "ordering": true,
        "info": false,
        "lengthChange": false,
        "language": { "paginate": { "previous": "<", "next": ">" } }
    });

    // 2. Automated Search with Accordion Control
    $('#unitSearch').on('keyup', function() {
        let value = $(this).val().toLowerCase();
        
        // If search is cleared, collapse everything
        if (value === "") { 
            $('.accordion-collapse.show').each(function() {
                bootstrap.Collapse.getOrCreateInstance(this).hide();
            });
            $('#instAccordion .accordion-item, #instAccordion tr').show(); 
            return; 
        }

        // Filter Logic
        $('#instAccordion > .accordion-item').each(function() {
            let $instItem = $(this);
            let instMatch = false;

            $instItem.find('.accordion-body .accordion-item').each(function() {
                let $divItem = $(this);
                let divMatch = false;

                $divItem.find('tbody tr').each(function() {
                    if ($(this).text().toLowerCase().indexOf(value) > -1) { 
                        $(this).show(); divMatch = true; instMatch = true; 
                    } else { $(this).hide(); }
                });

                if (divMatch) {
                    $divItem.show();
                    bootstrap.Collapse.getOrCreateInstance($divItem.find('.accordion-collapse')[0], {toggle: false}).show();
                } else { $divItem.hide(); }
            });

            if (instMatch) {
                $instItem.show();
                bootstrap.Collapse.getOrCreateInstance($instItem.find('> .accordion-collapse')[0], {toggle: false}).show();
            } else { $instItem.hide(); }
        });
    });

    // 3. Reset Button Functionality
    $('#resetSearch').click(function() {
        $('#unitSearch').val('').trigger('keyup');
    });

    // 4. Manual Collapse All
    $('#collapseAllBtn').click(function() {
        $('.accordion-collapse.show').each(function() {
            bootstrap.Collapse.getOrCreateInstance(this).hide();
        });
    });
    
    // 5. Institution/Division Dynamic Loading
    $("#institution").change(function(){
        let id = $(this).val();
        if(id) $.post("fetch_divisions.php",{institution_id:id}, function(data){ $("#division").html(data); });
    });
});

function handleStatus(id, name, action) {
    const isDeactivating = (action === 'Deactivate');
    Swal.fire({
        title: `${action} Unit?`,
        text: `Proceed with ${action.toLowerCase()} for "${name}"?`,
        icon: isDeactivating ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: isDeactivating ? '#ef4444' : '#10b981',
        confirmButtonText: `Yes, ${action} it!`,
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST'; form.action = 'unit_delete.php';
            const idInput = document.createElement('input'); idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = id;
            const actInput = document.createElement('input'); actInput.type = 'hidden'; actInput.name = 'status_action'; actInput.value = action;
            form.appendChild(idInput); form.appendChild(actInput);
            document.body.appendChild(form); form.submit();
        }
    });
}
</script>

<style>
/* Modern UI Styles */
.inst-header:not(.collapsed) { background: #fff !important; color: #0d6efd !important; border-bottom: 1px solid #f1f5f9; }
.div-header:not(.collapsed) { background: #f8fafc !important; border-left: 4px solid #0d6efd !important; }
.div-header { border-left: 4px solid transparent; transition: 0.3s; }
.accordion-button:focus { box-shadow: none; }
.icon-box { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
.dataTables_paginate { padding: 10px; font-size: 0.8rem; }
.pagination { margin-bottom: 0; justify-content: center; }
.table thead th { border: none !important; font-size: 0.65rem; padding: 12px; }
.table td { border-bottom: 1px solid #f8fafc; }
.badge-primary-soft {
    background-color: rgba(13, 110, 253, 0.1) !important;
    color: #0d6efd !important;
    font-weight: 500;
    border: none;
}
</style>

<?php 
$content = ob_get_clean();
include "../master/masterlayout.php";
?>