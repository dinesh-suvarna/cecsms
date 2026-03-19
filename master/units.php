<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

$error = "";
$success = "";
$unit_name = "";

/* ================= ADD UNIT LOGIC ================= */
if(isset($_POST['add_unit'])){
    $division_id = intval($_POST['division_id']);
    $unit_code = strtoupper(trim($_POST['unit_code']));
    $unit_name = ucwords(strtolower(trim($_POST['unit_name'])));
    $unit_type = $_POST['unit_type'];

    if(empty($unit_name)){
        $_SESSION['error'] = "Unit name is required.";
    } elseif(empty($division_id)){
        $_SESSION['error'] = "Division is required.";
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
                $restore = $conn->prepare("UPDATE units SET status='Active', unit_type=? WHERE id=?");
                $restore->bind_param("si", $unit_type, $row['id']);
                $restore->execute();
                $_SESSION['success'] = "Unit restored successfully.";
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO units (division_id, unit_code, unit_name, unit_type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $division_id, $unit_code, $unit_name, $unit_type);
            $stmt->execute();
            $_SESSION['success'] = "Unit added successfully.";
        }
    }
    // REDIRECT TO SELF TO CLEAR POST DATA
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Extract messages from session, then clear them
$success = $_SESSION['success'] ?? "";
$error = $_SESSION['error'] ?? "";
unset($_SESSION['success'], $_SESSION['error']);
/* ================= FETCH DATA LOGIC ================= */
$where  = " WHERE 1 ";
$params = [];
$types  = "";

if($role !== 'SuperAdmin'){
    $where .= " AND i.id=? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

// Main Query (Search removed for cleaner accordion navigation)
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

/* ================= START PAGE CONTENT ================= */
ob_start(); 
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm rounded-4 border-0">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-building text-primary me-2"></i>Add Labs and Facilities</h5>
                
                <?php if($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>
                <?php if($error): ?> <div class="alert alert-danger"><?= $error ?></div> <?php endif; ?>

                <form method="POST">
                    <?php if($role == 'SuperAdmin'): ?>
                        <div class="mb-3">
                            <label class="small fw-bold">Institution</label>
                            <select id="institution" class="form-select" required>
                                <option value="">Select</option>
                                <?php
                                $res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
                                while($iRow = $res->fetch_assoc()){ echo "<option value='{$iRow['id']}'>{$iRow['institution_name']}</option>"; }
                                ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" id="institution" value="<?= $user_institution_id ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="small fw-bold">Division</label>
                        <select name="division_id" id="division" class="form-select" required>
                            <option value="">Select Division</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="small fw-bold">Code</label>
                            <input type="text" name="unit_code" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold">Unit Name</label>
                            <input type="text" name="unit_name" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="small fw-bold">Type</label>
                        <select name="unit_type" class="form-select">
                            <option value="lab">Lab</option>
                            <option value="office">Office</option>
                            <option value="store">Store</option>
                            <option value="room">Room</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <button name="add_unit" class="btn btn-primary w-100 rounded-pill">Save Unit</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
    <div class="card shadow-sm rounded-4 border-0 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0">
                <i class="bi bi-door-open text-primary me-2"></i>Departmental Units
            </h5>
            <button id="collapseAllBtn" class="btn btn-sm btn-outline-secondary rounded-pill px-3" style="font-size: 0.75rem;">
                <i class="bi bi-arrows-collapse me-1"></i> Collapse All
            </button>
        </div>

        <div class="row g-2 mb-4">
            <div class="col-md-9 flex-grow-1">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="unitSearch" class="form-control border-0 bg-light" placeholder="Search for labs, offices, or departments...">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-light border w-100 shadow-sm rounded-3" title="Reset All">
                    <i class="bi bi-arrow-clockwise me-1"></i> Reset
                </a>
            </div>
        </div>

        <div class="accordion accordion-flush" id="instAccordion">
                <?php
                $currentInst = '';
                $currentDiv  = '';
                $firstInst   = true;
                $firstDiv    = true;

                // Pre-fetch counts for badges
                $divisionCounts = [];
                $divQuery = $conn->query("SELECT division_id, COUNT(*) as total FROM units WHERE status = 'Active' GROUP BY division_id");
                while($cRow = $divQuery->fetch_assoc()){ $divisionCounts[$cRow['division_id']] = $cRow['total']; }

                $instDivCounts = [];
                $idvQuery = $conn->query("SELECT institution_id, COUNT(*) as total FROM divisions GROUP BY institution_id");
                while($cRow = $idvQuery->fetch_assoc()){ $instDivCounts[$cRow['institution_id']] = $cRow['total']; }

                if($result->num_rows > 0):
                    while($row = $result->fetch_assoc()):
                        $inst = $row['institution_name'];
                        $div  = $row['division_name'];
                        $instId = "inst_" . md5($inst);
                        $divId = "div_" . $row['div_id'];

                        if($currentInst != $inst):
                            if(!$firstInst) echo '</tbody></table></div></div></div></div></div></div>'; 
                ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button inst-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $instId ?>">
                                <div class="d-flex align-items-center w-100">
                                    <div class="icon-box me-3 bg-light p-2 rounded-3"><i class="bi bi-building text-primary"></i></div>
                                    <span class="fw-bold"><?= $inst ?></span>
                                    <span class="badge-saas badge-inst ms-auto me-3"><?= $instDivCounts[$row['inst_id']] ?? 0 ?> Divisions</span>
                                </div>
                            </button>
                        </h2>
                        <div id="<?= $instId ?>" class="accordion-collapse collapse" data-bs-parent="#instAccordion">
                            <div class="accordion-body p-3">
                                <div class="accordion accordion-flush" id="divAcc_<?= $instId ?>">
                <?php 
                            $currentInst = $inst;
                            $currentDiv = ''; 
                            $firstInst = false;
                            $firstDiv = true;
                        endif;

                        if($currentDiv != $div):
                            if(!$firstDiv) echo '</tbody></table></div></div></div>'; 
                ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button div-header collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $divId ?>">
                                <i class="bi bi-folder2 text-muted me-2"></i>
                                <span class="fw-semibold text-dark"><?= $div ?></span>
                                <span class="badge-saas badge-div ms-2"><?= $divisionCounts[$row['div_id']] ?? 0 ?> Units</span>
                            </button>
                        </h2>
                        <div id="<?= $divId ?>" class="accordion-collapse collapse" data-bs-parent="#divAcc_<?= $instId ?>">
                            <div class="accordion-body p-0 pt-2">
                                <table class="table table-saas mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th class="ps-3">Code</th>
                                            <th>Unit Name</th>
                                            <th>Type</th>
                                            <th class="text-end pe-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                <?php 
                            $currentDiv = $div;
                            $firstDiv = false;
                        endif; 
                ?>
                        <tr>
                            <td class="ps-3 text-muted small"><?= $row['unit_code'] ?></td>
                            <td class="fw-semibold small"><?= $row['unit_name'] ?></td>
                            <td><span class="badge-saas" style="background:#f0fdf4; color:#166534; border: 1px solid #dcfce7;"><?= ucfirst($row['unit_type']) ?></span></td>
                            <td class="text-end pe-3">
                                
                                    <a href="edit_unit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light rounded-pill text-warning shadow-xs px-3">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </a>

                                    <button type="button" 
                                            class="btn btn-sm btn-light rounded-pill px-3 <?= $row['status'] == 'Active' ? 'text-danger' : 'text-success' ?>" 
                                            onclick="handleStatus(<?= $row['id'] ?>, '<?= addslashes($row['unit_name']) ?>', '<?= $row['status'] == 'Active' ? 'Deactivate' : 'Activate' ?>')">
                                        <i class="bi <?= $row['status'] == 'Active' ? 'bi-x-circle' : 'bi-check-circle' ?> me-1"></i>
                                        <?= $row['status'] == 'Active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php echo '</tbody></table></div></div></div></div></div></div>'; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3"></i>No units registered yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function loadDivisions(id){
    if(!id) return;
    $.post("fetch_divisions.php",{institution_id:id},function(data){
        $("#division").html(data);
    });
}

function handleStatus(id, name, action) {
    const isDeactivating = (action === 'Deactivate');
    Swal.fire({
        title: `${action} Unit?`,
        text: isDeactivating ? `Are you sure you want to deactivate "${name}"?` : `Reactivate "${name}"?`,
        icon: isDeactivating ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: isDeactivating ? '#f59e0b' : '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, ${action} it!`,
        borderRadius: '15px'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'unit_delete.php';
            const idInput = document.createElement('input');
            idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = id;
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden'; actionInput.name = 'status_action'; actionInput.value = action;
            form.appendChild(idInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
}

$(document).ready(function() {
    const STORAGE_KEY_PREFIX = "units_accordion_";

    // 1. INITIALIZE SELECTS
    $("#institution").change(function(){ loadDivisions($(this).val()); });
    var initialInst = $("#institution").val();
    if(initialInst){ loadDivisions(initialInst); }

    // 2. RESTORE ACCORDION STATE
    $('.accordion-collapse').each(function() {
        var id = $(this).attr('id');
        if (sessionStorage.getItem(STORAGE_KEY_PREFIX + id) === 'show') {
            var bsCollapse = new bootstrap.Collapse(this, { toggle: false });
            bsCollapse.show();
            $('button[data-bs-target="#' + id + '"]').removeClass('collapsed');
        }
    });

    // 3. SAVE STATE ON INTERACTION
    $('#instAccordion').on('shown.bs.collapse', '.accordion-collapse', function (e) {
        sessionStorage.setItem(STORAGE_KEY_PREFIX + e.target.id, 'show');
        e.stopPropagation(); 
    });

    $('#instAccordion').on('hidden.bs.collapse', '.accordion-collapse', function (e) {
        sessionStorage.removeItem(STORAGE_KEY_PREFIX + e.target.id);
        e.stopPropagation(); 
    });

    // 4. COLLAPSE ALL FUNCTIONALITY
    $('#collapseAllBtn').click(function() {
        // Find all open accordions and hide them
        $('.accordion-collapse.show').each(function() {
            var bsCollapse = bootstrap.Collapse.getInstance(this);
            if (bsCollapse) {
                bsCollapse.hide();
            } else {
                new bootstrap.Collapse(this).hide();
            }
        });

        // Clear all accordion keys from sessionStorage
        Object.keys(sessionStorage).forEach(key => {
            if (key.startsWith(STORAGE_KEY_PREFIX)) {
                sessionStorage.removeItem(key);
            }
        });
    });

    // 5. CLEANUP ON MODULE EXIT
    $('.sidebar-link, .nav-link').not('[href*="units.php"]').click(function() {
        Object.keys(sessionStorage).forEach(key => {
            if (key.startsWith(STORAGE_KEY_PREFIX)) {
                sessionStorage.removeItem(key);
            }
        });
    });
    // LIVE SEARCH LOGIC
$('#unitSearch').on('keyup', function() {
    let value = $(this).val().toLowerCase();
    
    // 1. If search is empty, show everything and stop
    if (value === "") {
        $('#instAccordion .accordion-item, #instAccordion tr').show();
        return;
    }

    // 2. Loop through top-level Institution items ONLY (using the > selector)
    $('#instAccordion > .accordion-item').each(function() {
        let $instItem = $(this);
        let institutionMatch = false;

        // 3. Loop through Divisions inside this Institution
        $instItem.find('.accordion-body .accordion-item').each(function() {
            let $divItem = $(this);
            let divisionMatch = false;
            let $rows = $divItem.find('tbody tr');

            // 4. Check each Unit (Table Row)
            $rows.each(function() {
                let text = $(this).text().toLowerCase();
                if (text.indexOf(value) > -1) {
                    $(this).show();
                    divisionMatch = true;
                    institutionMatch = true;
                } else {
                    $(this).hide();
                }
            });

            // 5. Handle Division Visibility & Auto-Expand
            if (divisionMatch) {
                $divItem.show();
                let $divCollapse = $divItem.find('.accordion-collapse').first();
                if (!$divCollapse.hasClass('show')) {
                    bootstrap.Collapse.getOrCreateInstance($divCollapse[0], { toggle: false }).show();
                }
            } else {
                $divItem.hide();
            }
        });

        // 6. Handle Institution Visibility & Auto-Expand
        if (institutionMatch) {
            $instItem.show();
            let $instCollapse = $instItem.find('> .accordion-collapse');
            if (!$instCollapse.hasClass('show')) {
                bootstrap.Collapse.getOrCreateInstance($instCollapse[0], { toggle: false }).show();
            }
        } else {
            $instItem.hide();
        }
    });
});
                });
</script>

<style>
#instAccordion { --accent-color: #0d6efd; --hover-bg: #f8fafc; }
.accordion-item { border: none !important; margin-bottom: 0.75rem !important; }
.inst-header { background: white !important; border-radius: 12px !important; border: 1px solid #edf2f7 !important; transition: 0.2s; }
.inst-header:not(.collapsed) { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.div-header { background: #ffffff !important; border-radius: 8px !important; font-size: 0.9rem !important; border-left: 3px solid transparent !important; }
.div-header:not(.collapsed) { border-left: 3px solid var(--accent-color) !important; background: #f1f5f9 !important; }
.badge-saas { font-weight: 500; padding: 0.4em 0.8em; border-radius: 6px; font-size: 0.75rem; }
.badge-inst { background: #e0e7ff; color: #4338ca; }
.badge-div { background: #f1f5f9; color: #475569; }
.table-saas thead th { text-transform: uppercase; font-size: 0.7rem; color: #94a3b8; border: none; padding: 1rem; }
.table-saas tbody td { padding: 1rem; border-bottom: 1px solid #f1f5f9; }
</style>

<?php 
$content = ob_get_clean();
include "../master/masterlayout.php";
?>