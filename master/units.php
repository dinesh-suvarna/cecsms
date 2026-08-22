<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

$page_title = "Labs & Facilities";
$page_icon  = "bi bi-grid-3x3-gap-fill";

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
        $_SESSION['error'] = "Unit name and Department are required.";
    } else {
        $check = $conn->prepare("SELECT id, status FROM units WHERE division_id=? AND (LOWER(unit_name)=LOWER(?) OR LOWER(unit_code)=LOWER(?))");
        $check->bind_param("iss", $division_id, $unit_name, $unit_code);
        $check->execute();
        $resultCheck = $check->get_result();

        if ($resultCheck->num_rows > 0) {
            $row = $resultCheck->fetch_assoc();
            if ($row['status'] == 'Active') {
                $_SESSION['error'] = "Facility already exists in this department.";
            } else {
                $restore = $conn->prepare("UPDATE units SET status='Active', unit_type=?, location=?, area_sqmt=? WHERE id=?");
                $restore->bind_param("ssdi", $unit_type, $location, $area_sqmt, $row['id']);
                $restore->execute();
                $_SESSION['success'] = "Facility restored successfully.";
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO units (division_id, unit_code, unit_name, unit_type, location, area_sqmt) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssd", $division_id, $unit_code, $unit_name, $unit_type, $location, $area_sqmt);
            $stmt->execute();
            $_SESSION['success'] = "Facility registered successfully.";
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

/* Natural sorting applied */
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
/* =========================================================
   ENTERPRISE ERP STYLES
========================================================= */
:root {
    --erp-navy: #173f63;
    --erp-navy-dark: #102f4a;
    --erp-blue: #0d6efd;
    --erp-text: #263746;
    --erp-muted: #71808f;
    --erp-border: #dce3e9;
    --erp-bg: #f5f7f9;
    --erp-white: #ffffff;
    --erp-shadow: 0 1px 3px rgba(20, 40, 60, .06);
}

.units-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 20px 40px;
}

/* HEADER */
.inst-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding-bottom: 20px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--erp-border);
}
.inst-header-left { display: flex; align-items: center; gap: 14px; }
.inst-header-icon {
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    background: #edf3f8; border: 1px solid #dce6ee; border-radius: 5px;
    color: var(--erp-navy); font-size: 1.1rem;
}
.inst-header h3 { margin: 0; color: var(--erp-navy-dark); font-size: 1.18rem; font-weight: 650; }
.inst-header p { margin: 3px 0 0; color: var(--erp-muted); font-size: .76rem; }

/* PANEL & FORM */
.inst-form-panel {
    background: #f9fafb; border: 1px solid var(--erp-border); border-radius: 5px;
    margin-bottom: 22px; box-shadow: var(--erp-shadow);
}
.inst-form-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 13px 18px; border-bottom: 1px solid var(--erp-border); background: #f5f7f9;
}
.inst-form-title { display: flex; align-items: center; gap: 8px; color: var(--erp-navy-dark); font-size: .82rem; font-weight: 650; }
.inst-form-body { padding: 20px; }

.inst-form-panel .form-label { 
    color: #536575; font-size: .65rem; font-weight: 700; 
    text-transform: uppercase; letter-spacing: .045em; margin-bottom: 6px; 
}

.inst-form-panel .form-control, 
.inst-form-panel .form-select {
    height: 38px; border: 1px solid var(--erp-border); border-radius: 4px !important;
    color: var(--erp-text); background: #fff; font-size: .8rem;
    box-shadow: none !important;
}

/* BUTTONS */
.btn-erp-primary {
    height: 38px; background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center;
}
.btn-erp-primary:hover { background: var(--erp-navy-dark); border-color: var(--erp-navy-dark); color: #fff; }

.btn-erp-cancel {
    height: 38px; border: 1px solid #c8d2db; background: #fff;
    color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-erp-cancel:hover { background: #f5f7f9; color: #334451; }

.inst-alert { border-radius: 4px !important; font-size: .76rem; }

/* TOOLBAR */
.erp-toolbar {
    background: #fff; border: 1px solid var(--erp-border); border-radius: 5px;
    padding: 8px 14px; margin-bottom: 18px; box-shadow: var(--erp-shadow);
}
.erp-toolbar .form-control {
    border: none; background: transparent; font-size: .8rem; color: var(--erp-text);
}

/* STACK CARDS & ACCORDION */
.inst-stack-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 5px !important;
    overflow: hidden;
    margin-bottom: 12px;
    background: #ffffff;
    box-shadow: var(--erp-shadow);
}
.inst-stack-card:last-child { margin-bottom: 0; }

.inst-header-btn {
    background-color: #ffffff !important;
    border: none;
    padding: 12px 18px;
    box-shadow: none !important;
}
.inst-header-btn:not(.collapsed) {
    background-color: #f5f7f9 !important;
    border-bottom: 1px solid var(--erp-border);
}

.inst-icon-box {
    width: 32px; height: 32px; border-radius: 4px;
    background: #edf3f8; border: 1px solid #dce6ee;
    color: var(--erp-navy); display: inline-flex;
    align-items: center; justify-content: center; font-size: 0.85rem;
}

.div-stack-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 4px !important;
    margin-bottom: 10px !important;
    background: #ffffff;
    overflow: hidden;
}
.div-header-btn {
    background-color: #ffffff !important;
    padding: 10px 14px !important;
    border: none !important;
    box-shadow: none !important;
}
.div-header-btn:not(.collapsed) {
    background-color: #f5f7f9 !important;
    border-left: 3px solid var(--erp-navy) !important;
}

.dept-icon-box {
    width: 26px; height: 26px; border-radius: 4px;
    background: #f1f5f9; color: #536575;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.78rem; flex-shrink: 0;
}

.dept-pill-count {
    font-size: 0.68rem; font-weight: 600; padding: 2px 8px;
    border-radius: 4px; background-color: #f5f7f9;
    color: #475569; border: 1px solid var(--erp-border);
    display: inline-flex; align-items: center; gap: 4px;
}

/* TABLE & CHIPS */
.saas-table { margin-bottom: 0; font-size: 0.8rem; }
.saas-table thead th {
    background-color: #f5f7f9; color: #536575;
    font-size: 0.65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.045em;
    border-bottom: 1px solid var(--erp-border); padding: 8px 14px;
}
.saas-table tbody tr { transition: background-color 0.12s ease; }
.saas-table tbody tr:hover { background-color: #f8fafc; }
.saas-table td { padding: 9px 14px; vertical-align: middle; border-bottom: 1px solid #edf2f7; }
.saas-table tbody tr:last-child td { border-bottom: none; }

.code-chip {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.72rem; font-weight: 600; padding: 2px 6px;
    border-radius: 4px; background-color: #f1f5f9; color: #334155;
    border: 1px solid #cbd5e1;
}

.type-pill-saas {
    font-size: 0.67rem; font-weight: 600; padding: 2px 8px;
    border-radius: 4px; display: inline-block; text-transform: capitalize;
}
.pill-lab        { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.pill-classroom  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.pill-office     { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.pill-library    { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
.pill-default    { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

.action-btn-saas {
    width: 28px; height: 28px; border-radius: 4px;
    display: inline-flex; align-items: center; justify-content: center;
    color: #64748b; border: 1px solid #dce3e9; background: #fff;
    transition: all 0.15s ease; text-decoration: none;
}
.action-btn-saas:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }
.action-btn-saas.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

/* DARK MODE */
[data-bs-theme="dark"] {
    --erp-bg: #101a24; --erp-white: #172534; --erp-text: #edf3f7; --erp-muted: #9aabb9;
    --erp-border: #2d3e4e; --erp-navy: #8eafc9; --erp-navy-dark: #dce8f0;
}
[data-bs-theme="dark"] .inst-header h3 { color: #edf3f7; }
[data-bs-theme="dark"] .inst-header-icon { background: #203445; border-color: #33495a; color: #b8d0e2; }
[data-bs-theme="dark"] .inst-form-panel, [data-bs-theme="dark"] .inst-form-header,
[data-bs-theme="dark"] .erp-toolbar, [data-bs-theme="dark"] .inst-stack-card, 
[data-bs-theme="dark"] .div-stack-card { background: #142230 !important; }
[data-bs-theme="dark"] .inst-header-btn, [data-bs-theme="dark"] .div-header-btn { background-color: #142230 !important; }
[data-bs-theme="dark"] .inst-header-btn:not(.collapsed), [data-bs-theme="dark"] .div-header-btn:not(.collapsed) { background-color: #1a2b3c !important; }
[data-bs-theme="dark"] .inst-form-panel .form-control, 
[data-bs-theme="dark"] .inst-form-panel .form-select { background: #172534 !important; color: var(--erp-text); border-color: var(--erp-border); }
[data-bs-theme="dark"] .btn-erp-cancel, [data-bs-theme="dark"] .action-btn-saas { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
[data-bs-theme="dark"] .saas-table thead th { background-color: #1a2b3c; color: #9aabb9; }
[data-bs-theme="dark"] .saas-table td { border-bottom-color: #243545; color: #edf3f7; }
[data-bs-theme="dark"] .saas-table tbody tr:hover { background-color: #182838; }
[data-bs-theme="dark"] .dept-pill-count { background-color: #1a2b3c; color: #b8c6d1; border-color: var(--erp-border); }
</style>

<div class="units-page">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi <?= $page_icon ?>"></i>
            </div>
            <div>
                <h3><?= $page_title ?></h3>
                <p>Directory of registered laboratories, classrooms, and administrative spaces.</p>
            </div>
        </div>
        <button class="btn btn-erp-primary px-3" type="button" data-bs-toggle="collapse" data-bs-target="#addFacilityCollapse" aria-expanded="false">
            <i class="bi bi-plus-lg me-1"></i> Add Facility
        </button>
    </div>

    <!-- ALERTS -->
    <?php if ($success): ?>
        <div class="alert alert-success inst-alert alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger inst-alert alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- COLLAPSIBLE ADD FACILITY FORM -->
    <div class="collapse mb-4 <?= (!empty($error)) ? 'show' : '' ?>" id="addFacilityCollapse">
        <div class="inst-form-panel">
            <div class="inst-form-header">
                <div class="inst-form-title">
                    <i class="bi bi-plus-circle text-primary"></i> Register New Facility
                </div>
                <button type="button" class="btn-close small" data-bs-toggle="collapse" data-bs-target="#addFacilityCollapse"></button>
            </div>
            <div class="inst-form-body">
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Institution <span class="text-danger">*</span></label>
                            <select id="institution" class="form-select" required <?= $role !== 'SuperAdmin' ? 'disabled' : '' ?>>
                                <option value="">Select Institution</option>
                                <?php
                                $res = $conn->query("SELECT id, institution_name FROM institutions ORDER BY institution_name");
                                while ($iRow = $res->fetch_assoc()) { 
                                    $sel = ($iRow['id'] == $user_institution_id) ? 'selected' : '';
                                    echo "<option value='{$iRow['id']}' $sel>" . htmlspecialchars($iRow['institution_name']) . "</option>"; 
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Department <span class="text-danger">*</span></label>
                            <select name="division_id" id="division" class="form-select" required>
                                <option value="">Select Department</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Facility Code</label>
                            <input type="text" name="unit_code" class="form-control" placeholder="e.g. CSL01">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select name="unit_type" class="form-select">
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
                            <label class="form-label">Facility Name <span class="text-danger">*</span></label>
                            <input type="text" name="unit_name" class="form-control" placeholder="e.g. Advanced AI Lab" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Block A, 3rd Floor">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Area (Sq. Mt.)</label>
                            <input type="number" step="0.01" name="area_sqmt" class="form-control" placeholder="e.g. 120.50">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-erp-cancel px-3" data-bs-toggle="collapse" data-bs-target="#addFacilityCollapse">Cancel</button>
                        <button type="submit" name="add_unit" class="btn btn-erp-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Save Facility
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SEARCH TOOLBAR -->
    <div class="erp-toolbar">
        <div class="row g-2 align-items-center">
            <div class="col flex-grow-1">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="unitSearch" class="form-control shadow-none" placeholder="Filter by code (e.g. CSL01), name, or type...">
                </div>
            </div>
            <div class="col-auto d-flex gap-1">
                <button id="resetSearch" class="btn btn-erp-cancel px-2" title="Clear Search">
                    <i class="bi bi-x-lg"></i>
                </button>
                <button id="collapseAllBtn" class="btn btn-erp-cancel px-3" title="Collapse All Accordions">
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
                            <div class="d-flex align-items-center gap-2">
                                <div class="inst-icon-box">
                                    <i class="bi bi-building"></i>
                                </div>
                                <span class="fw-semibold text-dark fs-6"><?= htmlspecialchars($inst) ?></span>
                            </div>
                            <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.7rem;">
                                <?= $instDivCounts[$row['inst_id']] ?? 0 ?> Departments
                            </span>
                        </div>
                    </button>
                </h2>
                <div id="<?= $instId ?>" class="accordion-collapse collapse" data-bs-parent="#instAccordion">
                    <div class="accordion-body p-2" style="background-color: var(--erp-bg);">
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
                            <div class="d-flex align-items-center gap-2">
                                <div class="dept-icon-box">
                                    <i class="bi bi-grid-1x2"></i>
                                </div>
                                <span class="fw-semibold text-dark" style="font-size: 0.875rem;">
                                    <?= htmlspecialchars($div) ?>
                                </span>
                            </div>

                            <div class="d-flex gap-1 flex-wrap">
                                <?php if (isset($typeCounts[$row['div_id']])): 
                                    foreach ($typeCounts[$row['div_id']] as $tInfo): 
                                        $count = $tInfo['count'];
                                        $typeLabel = ucfirst($tInfo['type']);
                                        $displayName = ($count == 1) ? $typeLabel : ($typeLabel == 'Library' ? 'Libraries' : $typeLabel . 's');
                                ?>
                                    <span class="dept-pill-count">
                                        <strong><?= $count ?></strong> <?= $displayName ?>
                                    </span>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </button>
                </h2>
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
            <div class="inst-form-panel p-4 text-center text-muted">
                <i class="bi bi-inbox fs-3 d-block mb-1 opacity-50"></i>
                <p class="mb-0 small fw-medium">No facility records found.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Initial dynamic division load if institution pre-selected
    let initialInstId = $("#institution").val();
    if(initialInstId) {
        $.post("fetch_divisions.php", {institution_id: initialInstId}, function(data){ 
            $("#division").html(data); 
        });
    }

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
            
            $('.accordion-collapse').removeClass('show');
            $('.inst-stack-card, .div-stack-card, .saas-table tbody tr').css('display', '');
            return; 
        }

        // 2. Temporarily remove data-bs-parent while searching
        $('.div-stack-card .accordion-collapse').removeAttr('data-bs-parent');

        // 3. Process search filtering
        $('.inst-stack-card').each(function() {
            let $instCard = $(this);
            let instMatches = 0;

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

                if (matchingRowsCount > 0) {
                    $divCard.css('display', 'block');
                    $divCard.find('.accordion-collapse').addClass('show');
                    instMatches++;
                } else {
                    $divCard.css('display', 'none');
                    $divCard.find('.accordion-collapse').removeClass('show');
                }
            });

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
        if(id) {
            $.post("fetch_divisions.php", {institution_id: id}, function(data){ 
                $("#division").html(data); 
            });
        } else {
            $("#division").html('<option value="">Select Department</option>');
        }
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