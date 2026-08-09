<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

$page_title = "Labs & Facilities";
$page_icon  = "bi-door-open";

$error = "";
$success = "";

/* ================= ADD UNIT LOGIC ================= */
if (isset($_POST['add_unit'])) {
    $division_id = intval($_POST['division_id']);
    $unit_code = strtoupper(trim($_POST['unit_code']));
    $unit_name = ucwords(strtolower(trim($_POST['unit_name'])));
    $unit_type = $_POST['unit_type'];
    $location = trim($_POST['location']);
    $area_sqmt = !empty($_POST['area_sqmt']) ? floatval($_POST['area_sqmt']) : NULL;

    if (empty($unit_name) || empty($division_id)) {
        $_SESSION['error'] = "Unit name and Division are required.";
    } else {
        $check = $conn->prepare("SELECT id, status FROM units WHERE division_id=? AND (LOWER(unit_name)=LOWER(?) OR LOWER(unit_code)=LOWER(?))");
        $check->bind_param("iss", $division_id, $unit_name, $unit_code);
        $check->execute();
        $resultCheck = $check->get_result();

        if ($resultCheck->num_rows > 0) {
            $row = $resultCheck->fetch_assoc();
            if ($row['status'] == 'Active') {
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
if ($role !== 'SuperAdmin') {
    $where .= " AND i.id=? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

/* Natural sorting applied: LENGTH(u.unit_code) ensures CSL2 comes before CSL10 */
$sql = "SELECT u.*, d.division_name, d.id AS div_id, i.institution_name, i.id AS inst_id
        FROM units u
        JOIN divisions d ON u.division_id=d.id
        JOIN institutions i ON d.institution_id=i.id
        $where
        ORDER BY i.institution_name ASC, d.division_name ASC, LENGTH(u.unit_code) ASC, u.unit_code ASC, u.unit_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$typeCounts = [];
$tQuery = $conn->query("SELECT division_id, unit_type, COUNT(*) as total FROM units WHERE status = 'Active' GROUP BY division_id, unit_type");
while ($tRow = $tQuery->fetch_assoc()) {
    $typeCounts[$tRow['division_id']][] = ['type' => $tRow['unit_type'], 'count' => $tRow['total']];
}

$instDivCounts = [];
$idvQuery = $conn->query("SELECT institution_id, COUNT(*) as total FROM divisions GROUP BY institution_id");
while ($cRow = $idvQuery->fetch_assoc()) { 
    $instDivCounts[$cRow['institution_id']] = $cRow['total']; 
}

ob_start(); 
?>

<style>
/* Modern SaaS Design Tokens & Card Stacking */
:root {
    --saas-border: #e2e8f0;
    --saas-bg-subtle: #f8fafc;
    --saas-primary: #0d6efd;
    --saas-primary-light: #eeef2e;
    --saas-text-dark: #0f172a;
    --saas-text-muted: #64748b;
}

/* Base Card Containers */
.saas-card {
    background: #ffffff;
    border: 1px solid var(--saas-border);
    border-radius: 12px;
    box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.03), 0 1px 2px -1px rgba(15, 23, 42, 0.03);
}

.saas-toolbar {
    background: #ffffff;
    border: 1px solid var(--saas-border);
    border-radius: 10px;
    padding: 6px 12px;
}

/* Accordion Outer Stack */
.inst-stack-card {
    border: 1px solid var(--saas-border) !important;
    border-radius: 12px !important;
    overflow: hidden;
    margin-bottom: 0.75rem;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
}
.inst-stack-card:last-child { margin-bottom: 0; }

.inst-header-btn {
    background-color: #ffffff !important;
    border: none;
    padding: 0.85rem 1.15rem;
    transition: background-color 0.15s ease;
}
.inst-header-btn:not(.collapsed) {
    background-color: #f8fafc !important;
    border-bottom: 1px solid var(--saas-border);
}

.inst-icon-box {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #e0e7ff;
    color: #4338ca;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

/* Inner Division Stack */
/* Department Sub-Card Layout */
.div-stack-card {
    border: 1px solid #e2e8f0 !important;
    border-radius: 10px !important;
    margin-bottom: 0.65rem !important; /* Proper visual gap between department rows */
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: all 0.2s ease-in-out;
    overflow: hidden;
}

.div-stack-card:hover {
    border-color: #cbd5e1 !important;
    box-shadow: 0 3px 6px -1px rgba(0, 0, 0, 0.05);
}

.div-header-btn {
    background-color: #ffffff !important;
    padding: 0.75rem 1rem !important;
    border: none !important;
    border-left: 3px solid transparent !important;
}

.div-header-btn:not(.collapsed) {
    background-color: #f8fafc !important;
    border-left-color: #4f46e5 !important; /* Soft indigo accent indicator on active expansion */
}

/* Department Badge Micro Icon Box (Replaces generic folder icon) */
.dept-icon-box {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.div-header-btn:not(.collapsed) .dept-icon-box {
    background: #e0e7ff;
    color: #4338ca;
}

/* Micro Count Pills */
.dept-pill-count {
    font-size: 0.68rem;
    font-weight: 500;
    padding: 3px 9px;
    border-radius: 6px;
    background-color: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

/* Facility Table & Micro Badges */
.saas-table {
    margin-bottom: 0;
    font-size: 0.83rem;
}
.saas-table thead th {
    background-color: #f8fafc;
    color: var(--saas-text-muted);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--saas-border);
    padding: 0.55rem 1rem;
}
.saas-table tbody tr {
    transition: background-color 0.12s ease;
}
.saas-table tbody tr:hover {
    background-color: #f8fafc;
}
.saas-table td {
    padding: 0.6rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.saas-table tbody tr:last-child td { border-bottom: none; }

/* Code Badge & Micro Tags */
.code-chip {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.73rem;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 5px;
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}

.type-pill-saas {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
    display: inline-block;
    letter-spacing: 0.01em;
}

/* Subtle Color Tokens for Facility Types */
.pill-lab        { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.pill-classroom  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.pill-office     { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.pill-library    { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
.pill-default    { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

.action-btn-saas {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    border: 1px solid transparent;
    transition: all 0.15s ease;
}
.action-btn-saas:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
}
.action-btn-saas.danger:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}
</style>

<!-- ALERT NOTIFICATIONS -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-3 py-2 px-3 small border-0 bg-emerald-50" role="alert">
        <i class="bi bi-check-circle-fill me-2 text-success"></i><?= $success ?>
        <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-3 py-2 px-3 small border-0" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
        <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- PAGE HEADER & ACTION BUTTON -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h4 class="fw-bold m-0 text-dark" style="letter-spacing: -0.02em;">
            <i class="<?= $page_icon ?> text-indigo me-2"></i><?= $page_title ?>
        </h4>
        <p class="text-muted small m-0">Directory of registered laboratories, classrooms, and administrative spaces.</p>
    </div>
    <button class="btn btn-primary btn-sm px-3 py-2 rounded-2 shadow-sm fw-semibold" style="background-color: var(--saas-primary); border: none;" type="button" data-bs-toggle="collapse" data-bs-target="#addFacilityCollapse" aria-expanded="false">
        <i class="bi bi-plus-lg me-1"></i> Add Facility
    </button>
</div>

<!-- COLLAPSIBLE ADD FACILITY FORM -->
<div class="collapse mb-3 <?= (!empty($error)) ? 'show' : '' ?>" id="addFacilityCollapse">
    <div class="saas-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold m-0 text-dark">
                <i class="bi bi-plus-circle text-primary me-1.5"></i> Register New Facility
            </h6>
            <button type="button" class="btn-close small" data-bs-toggle="collapse" data-bs-target="#addFacilityCollapse"></button>
        </div>
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-secondary">Institution <span class="text-danger">*</span></label>
                    <select id="institution" class="form-select form-select-sm" required <?= $role !== 'SuperAdmin' ? 'disabled' : '' ?>>
                        <option value="">Select Institution</option>
                        <?php
                        $res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
                        while ($iRow = $res->fetch_assoc()) { 
                            $sel = ($iRow['id'] == $user_institution_id) ? 'selected' : '';
                            echo "<option value='{$iRow['id']}' $sel>{$iRow['institution_name']}</option>"; 
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-secondary">Department <span class="text-danger">*</span></label>
                    <select name="division_id" id="division" class="form-select form-select-sm" required>
                        <option value="">Select Department</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">Facility Code</label>
                    <input type="text" name="unit_code" class="form-control form-control-sm" placeholder="e.g. CSL01">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">Type</label>
                    <select name="unit_type" class="form-select form-select-sm">
                        <option value="lab">Lab</option>
                        <option value="office">Office</option>
                        <option value="store room">Store Room</option>
                        <option value="classroom">Classroom</option>
                        <option value="room">Room</option>
                        <option value="hod cabin">HoD Cabin</option>
                        <option value="staffroom">Staffroom</option>
                        <option value="professor cabin">Professor Cabin</option>
                        <option value="library">Library</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label small fw-semibold text-secondary">Facility Name <span class="text-danger">*</span></label>
                    <input type="text" name="unit_name" class="form-control form-control-sm" placeholder="e.g. Advanced AI Lab" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-secondary">Location</label>
                    <input type="text" name="location" class="form-control form-control-sm" placeholder="e.g. Library Block, 3rd Floor">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary">Area (Sq. Mt.)</label>
                    <input type="number" step="0.01" name="area_sqmt" class="form-control form-control-sm" placeholder="e.g. 120.50">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-sm btn-light border rounded-2 px-3" data-bs-toggle="collapse" data-bs-target="#addFacilityCollapse">Cancel</button>
                <button type="submit" name="add_unit" class="btn btn-sm btn-primary rounded-2 px-3" style="background-color: var(--saas-primary); border: none;">
                    <i class="bi bi-check-lg me-1"></i> Save Facility
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SEARCH & TOOLBAR -->
<div class="saas-toolbar mb-3">
    <div class="row g-2 align-items-center">
        <div class="col flex-grow-1">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="unitSearch" class="form-control border-0 bg-transparent shadow-none" placeholder="Filter by code (e.g. CSL01), name, or type...">
            </div>
        </div>
        <div class="col-auto d-flex gap-1">
            <button id="resetSearch" class="btn btn-sm btn-light border text-secondary px-2.5" title="Clear Search">
                <i class="bi bi-x-lg"></i>
            </button>
            <button id="collapseAllBtn" class="btn btn-sm btn-light border text-secondary px-3" title="Collapse All Accordions">
                <i class="bi bi-arrows-collapse me-1"></i> Collapse All
            </button>
        </div>
    </div>
</div>

<!-- DATA DIRECTORY STACK -->
<div id="instAccordion">
    <?php
    $currentInst = ''; 
    $currentDiv = ''; 
    $firstInst = true; 
    $firstDiv = true;

    if ($result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
            $inst = $row['institution_name'];
            $div_formatted = ucwords(strtolower($row['division_name']));
            $div = str_replace(" And ", " and ", $div_formatted);
            $instId = "inst_" . md5($inst);
            $divId = "div_" . $row['div_id'];

            if ($currentInst != $inst):
                if (!$firstInst) echo '</tbody></table></div></div></div></div></div>'; 
    ?>
        <div class="accordion-item inst-stack-card">
            <h2 class="accordion-header">
                <button class="accordion-button inst-header-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $instId ?>">
                    <div class="d-flex justify-content-between align-items-center w-100 me-2">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="inst-icon-box">
                                <i class="bi bi-building"></i>
                            </div>
                            <span class="fw-bold text-dark fs-6" style="letter-spacing: -0.01em;"><?= htmlspecialchars($inst) ?></span>
                        </div>
                        <span class="badge rounded-pill bg-indigo-subtle text-indigo border border-indigo-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.7rem; background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe;">
                            <?= $instDivCounts[$row['inst_id']] ?? 0 ?> Depts
                        </span>
                    </div>
                </button>
            </h2>
            <div id="<?= $instId ?>" class="accordion-collapse collapse" data-bs-parent="#instAccordion">
                <div class="accordion-body p-2 bg-slate-50" style="background-color: #f8fafc;">
                    <div class="accordion accordion-flush" id="divAcc_<?= $instId ?>">
    <?php 
                $currentInst = $inst; 
                $currentDiv = ''; 
                $firstInst = false; 
                $firstDiv = true;
            endif;

            if ($currentDiv != $div):
                if (!$firstDiv) echo '</tbody></table></div></div></div>'; 
    ?>
        <div class="accordion-item div-stack-card">
    <h2 class="accordion-header">
        <button class="accordion-button div-header-btn collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $divId ?>">
            <div class="d-flex justify-content-between align-items-center w-100 me-2">
                
                <div class="d-flex align-items-center gap-2.5">
                    <div class="dept-icon-box">
                        <i class="bi bi-grid-1x2"></i>
                    </div>
                    <span class="fw-semibold text-dark fs-7" style="font-size: 0.88rem; letter-spacing: -0.01em;">
                        <?= htmlspecialchars($div) ?>
                    </span>
                </div>

                <div class="d-flex gap-1.5 flex-wrap">
                    <?php if (isset($typeCounts[$row['div_id']])): 
                        foreach ($typeCounts[$row['div_id']] as $tInfo): 
                            $count = $tInfo['count'];
                            $typeLabel = ucfirst($tInfo['type']);
                            $displayName = ($count == 1) ? $typeLabel : ($typeLabel == 'Library' ? 'Libraries' : $typeLabel . 's');
                    ?>
                        <span class="dept-pill-count">
                            <span class="fw-bold text-dark"><?= $count ?></span> <?= $displayName ?>
                        </span>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </button>
    </h2>
    <!-- Notice data-parent-id saved for dynamic removal during filtering -->
    <div id="<?= $divId ?>" class="accordion-collapse collapse" data-bs-parent="#divAcc_<?= $instId ?>" data-parent-id="#divAcc_<?= $instId ?>">
        <div class="accordion-body p-0 bg-white border-top">
            <div class="table-responsive">
                <table class="table saas-table align-middle text-nowrap">
                            <thead>
                                <tr>
                                    <th style="width: 140px;">Code</th>
                                    <th>Facility Name</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Area</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
    <?php 
                $currentDiv = $div;
                $firstDiv = false;
            endif;

            $unit_type_val = strtolower($row['unit_type']);
            $badge_class = match($unit_type_val) {
                'lab' => 'pill-lab',
                'classroom' => 'pill-classroom',
                'office', 'hod cabin', 'professor cabin' => 'pill-office',
                'library', 'staffroom' => 'pill-library',
                default => 'pill-default'
            };
    ?>
        <tr>
            <td>
                <?php if (!empty($row['unit_code'])): ?>
                    <span class="code-chip" data-code="<?= strtolower(trim($row['unit_code'])) ?>">
                        <?= htmlspecialchars($row['unit_code']) ?>
                    </span>
                <?php else: ?>
                    <span class="text-muted small" data-code="">—</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="fw-semibold text-dark"><?= htmlspecialchars($row['unit_name']) ?></div>
            </td>
            <td>
                <span class="type-pill-saas <?= $badge_class ?>">
                    <?= ucfirst($row['unit_type']) ?>
                </span>
            </td>
            <td class="text-muted small">
                <?= htmlspecialchars($row['location'] ?: '—') ?>
            </td>
            <td class="text-muted small">
                <?= $row['area_sqmt'] ? number_format($row['area_sqmt'], 2) . " m²" : "—" ?>
            </td>
            <td class="text-end pe-3">
                <div class="d-inline-flex gap-1">
                    <a href="edit_unit.php?id=<?= $row['id'] ?>" class="action-btn-saas" title="Edit Facility">
                        <i class="bi bi-pencil-square"></i>
                    </a>
                    <button type="button" class="action-btn-saas danger" 
                            onclick="handleStatus(<?= $row['id'] ?>, '<?= addslashes($row['unit_name']) ?>', '<?= $row['status'] == 'Active' ? 'Deactivate' : 'Activate' ?>')"
                            title="<?= $row['status'] == 'Active' ? 'Deactivate' : 'Activate' ?>">
                        <i class="bi <?= $row['status'] == 'Active' ? 'bi-trash3' : 'bi-check-circle' ?>"></i>
                    </button>
                </div>
            </td>
        </tr>
    <?php endwhile; ?>
    <?php echo '</tbody></table></div></div></div></div></div></div>'; ?>
    <?php else: ?>
        <div class="saas-card p-4 text-center text-muted">
            <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
            <p class="mb-0 small fw-medium">No facility records found.</p>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    $('#unitSearch').on('input', function() {
        let query = $(this).val().trim().toLowerCase();
        
        // 1. Reset state if search query is empty
        if (query === "") { 
            $('.accordion-collapse').each(function() {
                let parentId = $(this).data('parent-id');
                if (parentId) {
                    $(this).attr('data-bs-parent', parentId);
                }
            });
            
            // Hide all collapses and reset display styles
            $('.accordion-collapse').removeClass('show');
            $('.inst-stack-card, .div-stack-card, .saas-table tbody tr').css('display', '');
            return; 
        }

        // 2. Temporarily remove data-bs-parent while searching to prevent accordion conflicts
        $('.div-stack-card .accordion-collapse').removeAttr('data-bs-parent');

        // 3. Process each Institution Stack
        $('.inst-stack-card').each(function() {
            let $instCard = $(this);
            let instMatches = 0;

            // Process each Department within this Institution
            $instCard.find('.div-stack-card').each(function() {
                let $divCard = $(this);
                let deptName = $divCard.find('.dept-icon-box').next('span').text().trim().toLowerCase();
                let isDeptMatch = deptName.length > 0 && deptName.includes(query);
                
                let matchingRowsCount = 0;

                $divCard.find('.saas-table tbody tr').each(function() {
                    let $row = $(this);
                    let $codeChip = $row.find('[data-code]');
                    let unitCode = $codeChip.length ? ($codeChip.attr('data-code') || '').trim().toLowerCase() : '';
                    
                    let facilityName = $row.find('td:nth-child(2)').text().trim().toLowerCase();
                    let facilityType = $row.find('td:nth-child(3)').text().trim().toLowerCase();
                    let locationText = $row.find('td:nth-child(4)').text().trim().toLowerCase();

                    let isRowMatch = false;

                    if (unitCode !== '' && (unitCode === query || unitCode.startsWith(query))) {
                        isRowMatch = true;
                    } else if (facilityName.includes(query) || facilityType.includes(query) || locationText.includes(query)) {
                        isRowMatch = true;
                    } else if (isDeptMatch) {
                        isRowMatch = true;
                    }

                    if (isRowMatch) {
                        $row.css('display', '');
                        matchingRowsCount++;
                    } else {
                        $row.css('display', 'none');
                    }
                });

                // Display department ONLY if it has matching rows
                if (matchingRowsCount > 0) {
                    $divCard.css('display', 'block');
                    let divCollapse = $divCard.find('.accordion-collapse');
                    divCollapse.addClass('show');
                    instMatches++;
                } else {
                    $divCard.css('display', 'none');
                    let divCollapse = $divCard.find('.accordion-collapse');
                    divCollapse.removeClass('show');
                }
            });

            // Display institution ONLY if it contains matching departments
            if (instMatches > 0) {
                $instCard.css('display', 'block');
                $instCard.find('> .accordion-collapse').addClass('show');
            } else {
                $instCard.css('display', 'none');
                $instCard.find('> .accordion-collapse').removeClass('show');
            }
        });
    });

    $('#resetSearch').click(function() { 
        $('#unitSearch').val('').trigger('input'); 
    });

    $('#collapseAllBtn').click(function() { 
        $('.accordion-collapse').removeClass('show'); 
    });
    
    $("#institution").change(function(){
        let id = $(this).val();
        if(id) $.post("fetch_divisions.php", {institution_id: id}, function(data){ 
            $("#division").html(data); 
        });
    });
});

function handleStatus(id, name, action) {
    const isDeactivating = (action === 'Deactivate');
    Swal.fire({
        title: `${action} Facility?`,
        text: `Are you sure you want to ${action.toLowerCase()} "${name}"?`,
        icon: isDeactivating ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: isDeactivating ? '#ef4444' : '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, ${action}!`,
        customClass: { popup: 'rounded-4 border-0 shadow-lg' }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST'; 
            form.action = 'unit_delete.php';
            form.innerHTML = `<input type="hidden" name="id" value="${id}"><input type="hidden" name="status_action" value="${action}">`;
            document.body.appendChild(form); 
            form.submit();
        }
    });
}
</script>

<?php 
$content = ob_get_clean();
include "../master/masterlayout.php";
?>