<?php
require_once "../config/db.php";
require_once "../includes/session.php";

$role = $_SESSION['role'];
$user_institution_id = $_SESSION['institution_id'] ?? null;

/* ================= DATA FETCHING (Nested Hierarchy) ================= */
$where = " WHERE u.status='Active' ";
$params = [];
$types = "";

if($role !== 'SuperAdmin'){
    $where .= " AND i.id=? ";
    $params[] = $user_institution_id;
    $types .= "i";
}

$sql = "SELECT u.*, d.division_name, i.institution_name, i.id as inst_id, d.id as div_id
        FROM units u
        JOIN divisions d ON u.division_id=d.id
        JOIN institutions i ON d.institution_id=i.id
        $where
        ORDER BY i.institution_name ASC, d.division_name ASC, u.unit_name ASC";

$stmt = $conn->prepare($sql);
if(!empty($params)){ $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();

$hierarchy = [];
while($row = $res->fetch_assoc()){
    $instId = $row['inst_id'];
    $divId = $row['div_id'];
    
    $hierarchy[$instId]['name'] = $row['institution_name'];
    $hierarchy[$instId]['divisions'][$divId]['name'] = $row['division_name'];
    $hierarchy[$instId]['divisions'][$divId]['units'][] = $row;
}

ob_start(); 
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .accordion-item { border: none !important; margin-bottom: 0.8rem !important; }
    .inst-header { background: #fff !important; border-radius: 12px !important; border: 1px solid #e2e8f0 !important; transition: 0.3s; padding: 1rem !important; }
    .inst-header:not(.collapsed) { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-color: #3b82f6 !important; }
    
    .div-header { background: #f8fafc !important; border-radius: 8px !important; font-size: 0.95rem !important; margin: 5px 0; border: 1px solid #f1f5f9 !important; }
    .div-header:not(.collapsed) { background: #eff6ff !important; border-left: 4px solid #3b82f6 !important; }

    .badge-count { background: #f1f5f9; color: #475569; font-weight: 500; font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 6px; margin-left: 4px; }
    .unit-row { transition: 0.2s; border-radius: 8px; }
    .unit-row:hover { background: #fff1f1 !important; } /* The red hover from your previous request */

    .search-container { position: sticky; top: 10px; z-index: 100; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Facility Management</h4>
            <p class="text-muted small">Manage and oversee all labs, offices, and classrooms.</p>
        </div>
        <a href="unit_add.php" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-plus-circle me-1"></i> Register New Unit
        </a>
    </div>

    <div class="search-container mb-4">
        <div class="input-group shadow-sm rounded-4 overflow-hidden">
            <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="liveSearch" class="form-control border-0 py-3" 
                   placeholder="Search by name, code, or location...">
            <button class="btn btn-dark px-4" type="button">Filter Results</button>
        </div>
    </div>

    <div class="accordion" id="instAccordion">
        <?php foreach($hierarchy as $instId => $inst): ?>
        <div class="accordion-item shadow-sm">
            <h2 class="accordion-header">
                <button class="accordion-button inst-header collapsed" type="button" 
                        data-bs-toggle="collapse" data-bs-target="#inst-<?= $instId ?>">
                    <div class="d-flex align-items-center w-100">
                        <i class="bi bi-building me-3 text-primary fs-5"></i>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($inst['name']) ?></span>
                        <span class="ms-auto badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 me-3" style="font-size: 0.75rem;">
                            <?= count($inst['divisions']) ?> Departments
                        </span>
                    </div>
                </button>
            </h2>
            <div id="inst-<?= $instId ?>" class="accordion-collapse collapse" data-bs-parent="#instAccordion">
                <div class="accordion-body p-3">
                    
                    <div class="accordion accordion-flush" id="divAccordion-<?= $instId ?>">
                        <?php foreach($inst['divisions'] as $divId => $div): 
                            // Calculate unit type counts dynamically
                            $counts = [];
                            foreach($div['units'] as $u) { 
                                $typeKey = $u['unit_type'];
                                $counts[$typeKey] = ($counts[$typeKey] ?? 0) + 1;
                            }
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button div-header collapsed" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#div-<?= $divId ?>">
                                    <i class="bi bi-folder2 me-2 text-muted"></i>
                                    <span class="fw-semibold text-dark me-3"><?= htmlspecialchars($div['name']) ?></span>
                                    
                                    <div class="d-none d-md-flex">
                                        <?php foreach($counts as $type => $count): ?>
                                            <span class="badge-count"><?= $count ?> <?= ucfirst($type) ?><?= $count > 1 ? 's' : '' ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </button>
                            </h2>
                            <div id="div-<?= $divId ?>" class="accordion-collapse collapse" data-bs-parent="#divAccordion-<?= $instId ?>">
                                <div class="accordion-body p-0 pt-2">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr style="font-size: 0.75rem; text-transform: uppercase; color: #64748b;">
                                                    <th class="ps-3">Code / Name</th>
                                                    <th>Location & Area</th>
                                                    <th>Category</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($div['units'] as $unit): ?>
                                                <tr class="unit-row border-bottom">
                                                    <td class="ps-3">
                                                        <span class="badge bg-primary bg-opacity-10 text-primary small fw-bold mb-1 d-inline-block">
                                                            <?= htmlspecialchars($unit['unit_code']) ?>
                                                        </span>
                                                        <div class="fw-bold small text-dark"><?= htmlspecialchars($unit['unit_name']) ?></div>
                                                    </td>
                                                    <td class="small">
                                                        <div class="text-muted"><i class="bi bi-geo-alt me-1 text-danger"></i><?= $unit['location'] ?: 'No Location' ?></div>
                                                        <div class="text-muted"><i class="bi bi-bounding-box-circles me-1"></i><?= $unit['area_sqmt'] ? $unit['area_sqmt'].' sq ft' : 'Area not specified' ?></div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning text-dark rounded-pill px-3" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                                            <?= strtoupper($unit['unit_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex gap-2 justify-content-center">
                                                            <a href="unit_edit.php?id=<?= $unit['id'] ?>" class="btn btn-sm btn-outline-warning rounded-circle">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button onclick="confirmDelete(<?= $unit['id'] ?>, '<?= addslashes($unit['unit_name']) ?>')" 
                                                                    class="btn btn-sm btn-outline-danger rounded-circle">
                                                                <i class="bi bi-trash"></i>
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
                    </div> </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// --- LIVE SEARCH LOGIC ---
$(document).ready(function() {
    $("#liveSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        
        $(".unit-row").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });

        // Auto-expand accordions if searching
        if(value.length > 1) {
            $('.accordion-collapse').addClass('show');
            $('.accordion-button').removeClass('collapsed');
        } else {
            // Optional: collapse others back if search is cleared
        }
    });
});

// --- SWEET ALERT DELETE ---
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Delete Unit?',
        text: `Are you sure you want to remove "${name}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444', // Red for delete
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Form submission logic
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'unit_delete.php';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = id;
            form.appendChild(input);
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