<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

$page_title = "Labs & Facilities";

$error = "";
$success = "";

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
                        <label class="small fw-bold text-muted">Institution</label>
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
                        <label class="small fw-bold text-muted">Department</label>
                        <select name="division_id" id="division" class="form-select form-select-sm" required>
                            <option value="">Select Department</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="small fw-bold text-muted">Code</label>
                            <input type="text" name="unit_code" class="form-control form-control-sm" placeholder="eg.,CSL01">
                        </div>
                        <div class="col-8">
                            <label class="small fw-bold text-muted">Facility Name</label>
                            <input type="text" name="unit_name" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Facility Type</label>
                        <select name="unit_type" class="form-select form-select-sm">
                            <option value="lab">Lab</option>
                            <option value="office">Office</option>
                            <option value="store room">Store Room</option>
                            <option value="classroom">Classroom</option>
                            <option value="room">Room</option>
                            <option value="hod cabin">HoD Cabin</option>
                            <option value="staffroom">Staffroom</option>
                            <option value="library">Library</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Location</label>
                        <input type="text" name="location" class="form-control form-control-sm" placeholder="eg.,Library Block, 3rd Floor">
                    </div>
                    <div class="mb-4">
                        <label class="small fw-bold text-muted">Area (Sq. Mt.)</label>
                        <input type="number" step="0.01" name="area_sqmt" class="form-control form-control-sm">
                    </div>
                    <button name="add_unit" class="btn btn-primary w-100 rounded-pill fw-bold py-2 shadow-sm">Save Facility</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm rounded-4 border-0 p-4">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                    <h4 class="fw-bold mb-0">Facility Directory</h4>
                    <p class="text-muted small">Manage and view all registered laboratories, classrooms, and administrative <br>offices across institutions.</p>
                </div>
                <button id="collapseAllBtn" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm mt-1">
                    <i class="bi bi-arrows-collapse me-1"></i> Collapse All
                </button>
            </div>

            <div class="search-container mb-4 mt-2">
                <div class="search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="unitSearch" class="search-input" placeholder="Search by name, code, or type (e.g. 'Lab')...">
                    <button class="search-clear-btn" id="resetSearch" title="Reset Filters">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>

            <div class="accordion accordion-flush" id="instAccordion">
                <?php
                $currentInst = ''; 
                $currentDiv = ''; 
                $firstInst = true; 
                $firstDiv = true;
                $divCounter = 0; // For Zebra Striping

                if($result->num_rows > 0):
                    while($row = $result->fetch_assoc()):
                        $inst = $row['institution_name'];
                        $div  = $row['division_name'];
                        $instId = "inst_" . md5($inst);
                        $divId = "div_" . $row['div_id'];

                        if($currentInst != $inst):
                            if(!$firstInst) echo '</tbody></table></div></div></div></div></div>'; 
                            $divCounter = 0; // Reset counter for each institution
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
                            $currentInst = $inst; 
                            $currentDiv = ''; 
                            $firstInst = false; 
                            $firstDiv = true;
                        endif;

                        if($currentDiv != $div):
                            $divCounter++;
                            $bgClass = ($divCounter % 2 == 0) ? 'bg-alternate' : 'bg-alternate-gray';
                            if(!$firstDiv) echo '</tbody></table></div></div></div>'; 
                ?>
                    <div class="accordion-item border-0 shadow-sm rounded-3 mb-2 overflow-hidden <?= $bgClass ?>">
                        <h2 class="accordion-header">
                            <button class="accordion-button div-header collapsed px-4 <?= $bgClass ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $divId ?>">
                                <div class="d-flex align-items-center flex-wrap gap-2 w-100">
                                    <i class="bi bi-folder2 text-muted me-2"></i>
                                    <span class="fw-semibold text-dark me-auto"><?= $div ?></span>
                                    <div class="d-flex gap-1 flex-wrap pe-3">
                                        <?php if(isset($typeCounts[$row['div_id']])): 
                                            foreach($typeCounts[$row['div_id']] as $tInfo): 
                                                $count = $tInfo['count'];
                                                $typeLabel = ucfirst($tInfo['type']);
                                                $displayName = ($count == 1) ? $typeLabel : ($typeLabel == 'Library' ? 'Libraries' : $typeLabel . 's');
                                        ?>
                                            <span class="badge bg-white text-muted border fw-normal shadow-xs" style="font-size: 0.65rem; border-radius: 6px;">
                                                <?= $count ?> <?= $displayName ?>
                                            </span>
                                        <?php endforeach; endif; ?>
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
                            $currentDiv = $div;
                            $firstDiv = false;
                        endif;

                        $unit_type_val = strtolower($row['unit_type']);
                        $badge_styles = [
                            'lab'        => 'background: #ffffff; color: #0369a1; border: 1px solid #bae6fd; font-weight: 500; font-size: 0.65rem; border-radius: 6px;',
                            'classroom'  => 'background: #ffffff; color: #15803d; border: 1px solid #bbf7d0; font-weight: 500; font-size: 0.65rem; border-radius: 6px;',
                            'office'     => 'background: #ffffff; color: #b45309; border: 1px solid #fef3c7; font-weight: 500; font-size: 0.65rem; border-radius: 6px;',
                            'store room' => 'background: #ffffff; color: #4b5563; border: 1px solid #e5e7eb; font-weight: 500; font-size: 0.65rem; border-radius: 6px;',
                            'library'    => 'background: #ffffff; color: #4338ca; border: 1px solid #e0e7ff; font-weight: 500; font-size: 0.65rem; border-radius: 6px;',
                            'staffroom'  => 'background: #ffffff; color: #6d28d9; border: 1px solid #ede9fe; font-weight: 500; font-size: 0.65rem; border-radius: 6px;',
                            'hod cabin'  => 'background: #ffffff; color: #be185d; border: 1px solid #fce7f3; font-weight: 500; font-size: 0.65rem; border-radius: 6px;',
                            'other'      => 'background: #ffffff; color: #64748b; border: 1px solid #f1f5f9; font-weight: 500; font-size: 0.65rem; border-radius: 6px;',
                        ];
                        $current_style = $badge_styles[$unit_type_val] ?? 'background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;';
                ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="text-primary fw-bold" style="font-size: 0.75rem;"><?= htmlspecialchars($row['unit_code']) ?></div>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($row['unit_name']) ?></div>
                        </td>
                        <td>
                            <div class="small text-dark"><?= $row['location'] ?: '<span class="text-muted">N/A</span>' ?></div>
                            <span class="badge" style="<?= $current_style ?> font-size: 0.7rem; padding: 2px 8px; border-radius: 6px; display: inline-block; margin-top: 4px; font-weight: 500;">
                                <?= ucfirst($row['unit_type']) ?>
                            </span>
                        </td>
                        <td class="fw-medium text-muted"><?= $row['area_sqmt'] ? $row['area_sqmt']." Sq. Mt" : "-" ?></td>
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
    $('.inner-unit-table').DataTable({
        "pageLength": 10, "dom": 'tp', "ordering": true, "info": false, "lengthChange": false,
        "language": { "paginate": { "previous": "<", "next": ">" } }
    });

    $('#unitSearch').on('keyup', function() {
        let value = $(this).val().toLowerCase();
        
        if (value === "") { 
            $('.accordion-collapse').collapse('hide');
            $('.accordion-item').show();
            $('.inner-unit-table').DataTable().search('').draw();
            return; 
        }

        $('#instAccordion > .accordion-item').hide();

        $('.inner-unit-table').each(function() {
            let table = $(this).DataTable();
            let $divItem = $(this).closest('.accordion-item'); 
            let $instItem = $divItem.closest('.accordion-collapse').closest('.accordion-item');

            table.search(value).draw();

            if (table.rows({ filter: 'applied' }).count() > 0) {
                $divItem.show();
                $instItem.show();
                
                let divCollapse = $divItem.find('.accordion-collapse')[0];
                if (divCollapse) bootstrap.Collapse.getOrCreateInstance(divCollapse, {toggle: false}).show();
                
                let instCollapse = $instItem.find('> .accordion-collapse')[0];
                if (instCollapse) bootstrap.Collapse.getOrCreateInstance(instCollapse, {toggle: false}).show();
            } else {
                $divItem.hide();
            }
        });
    });

    $('#resetSearch').click(function() { $('#unitSearch').val('').trigger('keyup'); });
    $('#collapseAllBtn').click(function() { $('.accordion-collapse.show').each(function() { bootstrap.Collapse.getOrCreateInstance(this).hide(); }); });
    
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
            form.innerHTML = `<input type="hidden" name="id" value="${id}"><input type="hidden" name="status_action" value="${action}">`;
            document.body.appendChild(form); form.submit();
        }
    });
}
</script>

<style>
.search-container { position: relative; }
.search-box { 
    display: flex; align-items: center; background: #fff; 
    border: 1px solid #e2e8f0; border-radius: 12px; padding: 5px 15px;
    transition: all 0.3s ease; box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}
.search-box:focus-within { border-color: #0d6efd; box-shadow: 0 0 0 4px rgba(13,110,253,0.1); }
.search-icon { color: #94a3b8; font-size: 1.1rem; }
.search-input { 
    border: none; background: transparent; padding: 10px 15px; 
    width: 100%; outline: none; font-size: 0.95rem; color: #1e293b;
}
.search-clear-btn { 
    background: #f1f5f9; border: none; color: #64748b; 
    border-radius: 8px; width: 32px; height: 32px; 
    display: flex; align-items: center; justify-content: center; transition: 0.2s;
}
.search-clear-btn:hover { background: #e2e8f0; color: #0f172a; }

.bg-alternate-gray { background-color: #dee2e6 !important; }

.badge {
    padding: 0.5em 1em;
    border-radius: 50px; 
    border: none;        
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    transition: transform 0.2s ease;
}

.badge:hover {
    transform: translateY(-1px);
    filter: brightness(1.1);
}

.bg-alternate { background-color: #64b1ff !important; }
.inst-header:not(.collapsed) { background: #fff !important; color: #0d6efd !important; border-bottom: 1px solid #f1f5f9; }
.div-header:not(.collapsed) { border-left: 4px solid #0d6efd !important; }
.div-header { border-left: 4px solid transparent; transition: 0.3s; }
.accordion-button:focus { box-shadow: none; }
.icon-box { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
.table thead th { border: none !important; font-size: 0.65rem; padding: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
.table td { border-bottom: 1px solid #f1f5f9; }
.shadow-xs { box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
</style>

<?php 
$content = ob_get_clean();
include "../master/masterlayout.php";
?>