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

$sql = "SELECT u.*, d.division_name, d.id AS div_id
        FROM units u
        JOIN divisions d ON u.division_id=d.id
        JOIN institutions i ON d.institution_id=i.id
        $where
        ORDER BY d.division_name ASC, LENGTH(u.unit_code) ASC, u.unit_code ASC, u.unit_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rawResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Grouping Units by Division */
$divisionsData = [];
foreach ($rawResult as $row) {
    $divId = $row['div_id'];
    if (!isset($divisionsData[$divId])) {
        $divisionsData[$divId] = [
            'division_name' => $row['division_name'],
            'units' => []
        ];
    }
    $divisionsData[$divId]['units'][] = $row;
}

ob_start(); 
?>

<style>
    :root {
        --erp-navy: #173f63;
        --erp-bg: #f3f5f7;
        --erp-border: #d9e0e7;
        --erp-text-main: #20384d;
        --erp-text-muted: #64748b;
        --erp-shadow-sm: 0 1px 3px rgba(20,45,70,.05);
    }

    body { background-color: var(--erp-bg); font-family: 'Inter', sans-serif; color: var(--erp-text-main); }

    /* Custom Color Overrides */
    .btn-custom-navy {
        background-color: #173f63 !important;
        border-color: #173f63 !important;
        color: #ffffff !important;
    }
    .btn-custom-navy:hover, .btn-custom-navy:focus {
        background-color: #11304c !important;
        border-color: #11304c !important;
        color: #ffffff !important;
    }

    .text-custom-navy {
        color: #173f63 !important;
    }

    /* Compact Form Controls (Matched to Divisions) */
    .form-control-sm, .form-select-sm {
        font-size: 0.8125rem;
        padding: 0.35rem 0.65rem;
        border-radius: 6px;
    }

    /* Compact Form Labels (Matched to Divisions) */
    .form-label-compact {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--erp-text-muted);
        margin-bottom: 0.25rem;
    }

    /* Search Box Styling */
    .global-search-wrapper {
        position: relative;
        width: 320px;
    }

    .global-search-input {
        border-radius: 6px;
        border: 1px solid var(--erp-border);
        padding: 0.4rem 0.85rem 0.4rem 2.25rem;
        font-size: 0.8125rem;
        width: 100%;
        background: #ffffff;
        transition: all 0.15s ease;
    }

    .global-search-input:focus {
        border-color: #173f63;
        box-shadow: 0 0 0 3px rgba(23, 63, 99, 0.15);
        outline: none;
    }

    .global-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--erp-text-muted);
        font-size: 0.8125rem;
    }

    /* Accordion Item Card */
    .accordion-item { 
        border: 1px solid var(--erp-border) !important; 
        border-radius: 8px !important; 
        margin-bottom: 0.85rem; 
        background: #ffffff; 
        overflow: hidden; 
    }
    .accordion-button { background: #ffffff !important; padding: 0.85rem 1.15rem; }
    .accordion-button:not(.collapsed) { border-bottom: 1px solid var(--erp-border); background: #f8fafc !important; }

    .table-scroll-container { max-height: 400px; overflow-y: auto; }
    
    .custom-ledger-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .custom-ledger-table thead th { 
        position: sticky; top: 0; z-index: 5; background-color: #f8fafc; color: var(--erp-text-muted); 
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase; padding: 0.65rem 1rem; border-bottom: 1px solid var(--erp-border); 
    }
    .custom-ledger-table tbody td { padding: 0.65rem 1rem; border-bottom: 1px solid var(--erp-border); font-size: 0.8125rem; }

    /* Form Panel Styling (Matched to Divisions) */
    .inst-form-panel {
        background: #ffffff; border: 1px solid var(--erp-border); border-radius: 8px;
        margin-bottom: 1.25rem; box-shadow: var(--erp-shadow-sm);
    }
    .inst-form-header {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.65rem 1rem; border-bottom: 1px solid var(--erp-border); background: #f8fafc;
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
</style>

<div class="container-fluid p-0">
    <!-- Header Block -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold tracking-tight mb-1" style="color: var(--erp-text-main); letter-spacing: -0.01em;"><?= $page_title ?></h4>
            <p class="text-muted small mb-0">Directory of registered laboratories, classrooms and administrative spaces.</p>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <div class="global-search-wrapper">
                <i class="bi bi-search global-search-icon"></i>
                <input type="text" id="unitSearchInput" class="global-search-input" placeholder="Search labs, facilities...">
            </div>
            <button class="btn btn-custom-navy btn-sm fw-bold shadow-sm px-3" type="button" data-bs-toggle="collapse" data-bs-target="#addFacilityCollapse">
                <i class="bi bi-plus-lg me-1"></i> Add Facility
            </button>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show py-2 px-3 mb-3" role="alert" style="font-size: 0.8125rem;">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2 px-3 mb-3" role="alert" style="font-size: 0.8125rem;">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- COMPACT COLLAPSIBLE ADD FACILITY FORM (Matched to Divisions Form Size & Typography) -->
    <div class="collapse mb-4 <?= (!empty($error)) ? 'show' : '' ?>" id="addFacilityCollapse">
        <div class="inst-form-panel">
            <div class="inst-form-header">
                <div class="fw-bold text-dark" style="font-size: 0.85rem;">
                    <i class="bi bi-plus-circle text-custom-navy me-1"></i> Register New Facility
                </div>
                <button type="button" class="btn-close small" data-bs-toggle="collapse" data-bs-target="#addFacilityCollapse"></button>
            </div>
            <div class="p-3">
                <form method="POST" action="">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label-compact">Institution <span class="text-danger">*</span></label>
                            <select id="institution" class="form-select form-select-sm" required <?= $role !== 'SuperAdmin' ? 'disabled' : '' ?>>
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
                            <label class="form-label-compact">Department <span class="text-danger">*</span></label>
                            <select name="division_id" id="division" class="form-select form-select-sm" required>
                                <option value="">Select Department</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label-compact">Facility Code</label>
                            <input type="text" name="unit_code" class="form-control form-control-sm" placeholder="e.g. CSL01">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label-compact">Type</label>
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
                            <label class="form-label-compact">Facility Name <span class="text-danger">*</span></label>
                            <input type="text" name="unit_name" class="form-control form-control-sm" placeholder="e.g. Machine Learning Lab" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-compact">Location</label>
                            <input type="text" name="location" class="form-control form-control-sm" placeholder="e.g. 3rd Floor-Library Block">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-compact">Area (Sq. Mt.)</label>
                            <input type="number" step="0.01" name="area_sqmt" class="form-control form-control-sm" placeholder="e.g. 120.50">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-toggle="collapse" data-bs-target="#addFacilityCollapse">Cancel</button>
                        <button type="submit" name="add_unit" class="btn btn-custom-navy btn-sm px-4">
                            <i class="bi bi-check-lg me-1"></i> Save Facility
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ACCORDION DIRECTORY -->
    <div class="accordion" id="acc-divisions">
        <?php if (empty($divisionsData)): ?>
            <div class="text-center py-5 bg-white rounded-3 border">
                <i class="bi bi-inbox text-muted fs-2 opacity-50"></i>
                <p class="text-muted extra-small mb-0 mt-2">No facility records found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($divisionsData as $divId => $divData): 
                $accId = "collapse-div-" . $divId;
                $divNameFormatted = str_replace(" And ", " and ", ucwords(strtolower($divData['division_name'])));
            ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accId ?>">
                            <div class="row align-items-center w-100 me-3">
                                <div class="col">
                                    <div class="fw-bold text-dark extra-small ledger-item-name"><?= htmlspecialchars($divNameFormatted) ?></div>
                                    <div class="text-muted extra-small mt-1" style="font-size: 0.72rem;">
                                        <?= count($divData['units']) ?> Registered Labs/Facilities
                                    </div>
                                </div>
                                <div class="col-auto text-end">
                                    <span class="d-block text-uppercase text-muted fw-bold" style="font-size: 0.65rem;">Total Labs / Facilities</span>
                                    <span class="fw-bold text-custom-navy extra-small"><?= count($divData['units']) ?></span>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="<?= $accId ?>" class="accordion-collapse collapse" data-bs-parent="#acc-divisions">
                        <div class="accordion-body p-0">
                            <div class="table-scroll-container">
                                <table class="custom-ledger-table align-middle">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Code</th>
                                            <th>Facility Name</th>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Area</th>
                                            <th class="text-center pe-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($divData['units'] as $row): 
                                            $unit_type_val = strtolower($row['unit_type']);
                                            $badge_class = match($unit_type_val) {
                                                'lab' => 'pill-lab',
                                                'classroom' => 'pill-classroom',
                                                'office', 'hod cabin', 'professor cabin' => 'pill-office',
                                                'library', 'staffroom' => 'pill-library',
                                                default => 'pill-default'
                                            };
                                        ?>
                                            <tr class="ledger-row" 
                                                data-code="<?= strtolower(trim($row['unit_code'] ?? '')) ?>" 
                                                data-name="<?= htmlspecialchars(strtolower($row['unit_name'])) ?>"
                                                data-type="<?= htmlspecialchars(strtolower($row['unit_type'])) ?>">
                                                <td class="ps-4">
                                                    <?php if (!empty($row['unit_code'])): ?>
                                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($row['unit_code']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">—</span>
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
                                                <td class="text-center pe-4">
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
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    let initialInstId = $("#institution").val();
    if(initialInstId) {
        $.post("fetch_divisions.php", {institution_id: initialInstId}, function(data){ 
            $("#division").html(data); 
        });
    }

    // Dynamic Search
    const searchInput = document.getElementById('unitSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const accordionItems = document.querySelectorAll('#acc-divisions .accordion-item');

            accordionItems.forEach(item => {
                const divName = item.querySelector('.ledger-item-name')?.innerText.toLowerCase() || '';
                const rows = item.querySelectorAll('.ledger-row');
                let matchingRowsInItem = 0;

                rows.forEach(row => {
                    const code = row.getAttribute('data-code') || '';
                    const name = row.getAttribute('data-name') || '';
                    const type = row.getAttribute('data-type') || '';

                    if (query === '' || divName.includes(query) || name.includes(query) || code.includes(query) || type.includes(query)) {
                        row.style.display = '';
                        matchingRowsInItem++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (query === '' || divName.includes(query) || matchingRowsInItem > 0) {
                    item.style.display = '';
                    const collapseEl = item.querySelector('.accordion-collapse');
                    if (query !== '' && matchingRowsInItem > 0 && collapseEl) {
                        bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                    }
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

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
        confirmButtonColor: isDeactivating ? '#ef4444' : '#173f63',
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