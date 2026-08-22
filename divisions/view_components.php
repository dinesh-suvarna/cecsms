<?php 
require_once __DIR__ . "/../config/db.php";
session_start();
$page_title = "Component Registry";
$page_icon  = "bi-boxes";

$notif_division_id = $_SESSION['division_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'Division'; 

// --- AJAX HANDLER ---
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false];

    if ($action == 'delete') {
        $id = (int)$_POST['id'];
        $whereClause = ($user_role === 'SuperAdmin') ? "id = $id" : "id = $id AND division_id = $notif_division_id";
        if ($conn->query("DELETE FROM component_stock WHERE $whereClause")) {
            $_SESSION['success'] = "Item permanently removed from registry.";
            $response['success'] = true;
        }
    }

    if ($action == 'update_all') {
        $id = (int)$_POST['id'];
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $cat = mysqli_real_escape_string($conn, $_POST['cat']);
        $spec = mysqli_real_escape_string($conn, $_POST['spec']);
        $qty = (int)$_POST['qty'];
        $price = (float)$_POST['price'];
        $v_id = !empty($_POST['v_id']) ? (int)$_POST['v_id'] : "NULL";

        $whereClause = ($user_role === 'SuperAdmin') ? "id = $id" : "id = $id AND division_id = $notif_division_id";
        $sql = "UPDATE component_stock SET item_name = '$name', category = '$cat', specification = '$spec', total_quantity = $qty, unit_price = $price, vendor_id = $v_id WHERE $whereClause";

        if ($conn->query($sql)) { 
            $_SESSION['success'] = "Registry updated successfully.";
            $response['success'] = true; 
        }
    }
    echo json_encode($response); exit(); 
}

$vendor_list = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");
$vendors = []; while($v = $vendor_list->fetch_assoc()) { $vendors[] = $v; }

ob_start();
?>

<style>
:root {
    --erp-navy: #123b63;
    --erp-navy-dark: #0b2942;
    --erp-blue: #2b628f;
    --erp-green: #3f755e;
    --erp-amber: #9a6b22;
    --erp-red: #9a4a4a;
    --erp-bg: #f3f5f7;
    --erp-panel: #ffffff;
    --erp-panel-soft: #f7f9fb;
    --erp-border: #d9e0e7;
    --erp-border-dark: #c6d0da;
    --erp-text: #20384d;
    --erp-text-soft: #526679;
    --erp-muted: #718191;
    --erp-shadow: 0 1px 3px rgba(20,45,70,.05);
}

.container-fluid {
    max-width: 1440px;
    padding: 24px 28px 36px;
}

.dash-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 6px !important;
    background: var(--erp-panel);
    box-shadow: var(--erp-shadow) !important;
}

.extra-small { font-size: .72rem; }

.institution-card {
    border: 1px solid var(--erp-border) !important;
    border-radius: 6px !important;
    margin-bottom: 0.85rem;
    background: var(--erp-panel);
}

.inst-header {
    background-color: var(--erp-panel-soft) !important;
    border-left: 4px solid var(--erp-navy) !important;
    cursor: pointer;
    transition: background 0.2s;
    padding: 12px 16px;
}

.inst-header:hover { background-color: #edf2f7 !important; }

.division-header {
    background-color: #f1f5f9 !important;
    border-left: 4px solid var(--erp-blue) !important;
    border-radius: 4px;
    margin: 8px 12px;
    padding: 8px 12px !important;
    cursor: pointer;
}

.table thead th {
    font-size: 0.68rem;
    letter-spacing: 0.05em;
    font-weight: 700;
    color: var(--erp-text-soft);
    background-color: var(--erp-panel-soft) !important;
    border-bottom: 1px solid var(--erp-border) !important;
    padding-top: 8px;
    padding-bottom: 8px;
}

.table tbody td {
    border-bottom: 1px solid #edf0f3;
    padding-top: 8px;
    padding-bottom: 8px;
}

.stock-badge {
    padding: 3px 8px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.72rem;
}

.bg-amber-subtle   { background-color: #fef3c7 !important; color: #92400e !important; }
.bg-danger-subtle  { background-color: #fee2e2 !important; color: #991b1b !important; }
.bg-success-subtle { background-color: #dcfce7 !important; color: #15803d !important; }

.btn-icon {
    width: 28px;
    height: 28px;
    border-radius: 4px;
    border: 1px solid var(--erp-border);
    background: #ffffff;
    color: var(--erp-text-soft);
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: var(--erp-panel-soft);
    color: var(--erp-navy);
}

.btn-erp-primary {
    background-color: var(--erp-navy);
    color: #ffffff;
    border: none;
    font-weight: 600;
    font-size: 0.82rem;
    padding: 0.45rem 1rem;
    border-radius: 4px;
}

.btn-erp-primary:hover { background-color: var(--erp-navy-dark); color: #ffffff; }

.toggle-icon { transition: transform 0.2s ease; font-size: 0.75rem; color: var(--erp-muted); }
[aria-expanded="true"] .toggle-icon { transform: rotate(90deg); color: var(--erp-navy); }
</style>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1060;">
    <div id="liveToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true" style="background: var(--erp-navy-dark);">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center extra-small">
                <i class="bi bi-check-circle-fill text-success me-2"></i>
                <span id="toastMsg"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<div class="container-fluid py-0">
    <!-- Header Section -->
    <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-4 border-bottom gap-3">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--erp-navy-dark); font-size: 1.25rem;">
                <?= ($user_role === 'SuperAdmin') ? 'Master Component Registry' : 'Component Stock Registry' ?>
            </h4>
            <p class="text-muted small mb-0">Categorized stock items breakdown by institution and division.</p>
        </div>
        <div class="d-flex gap-2">
            <div class="input-group bg-white rounded border overflow-hidden" style="width: 260px;">
                <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="inventorySearch" class="form-control border-0 extra-small" placeholder="Filter stock items...">
            </div>
            <a href="add_components.php" class="btn btn-erp-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Component
            </a>
        </div>
    </div>

    <!-- Registry List -->
    <div id="registryContent">
        <?php
        $sql_filter = ($user_role === 'SuperAdmin') ? "1=1" : "c.division_id = $notif_division_id";
        $sql = "SELECT c.*, v.vendor_name, i.institution_name, d.division_name FROM component_stock c 
                LEFT JOIN vendors v ON c.vendor_id = v.id LEFT JOIN divisions d ON c.division_id = d.id
                LEFT JOIN institutions i ON d.institution_id = i.id WHERE $sql_filter ORDER BY i.institution_name, d.division_name, c.id DESC";
        $res = $conn->query($sql);
        $data = [];
        while($r = $res->fetch_assoc()){ $data[$r['institution_name'] ?? 'Unassigned'][$r['division_name'] ?? 'General'][] = $r; }

        foreach($data as $instName => $divisions):
            $inst_id = "inst_" . md5($instName);
        ?>
        <div class="card institution-card overflow-hidden">
            <div class="card-header inst-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#body_<?= $inst_id ?>" aria-expanded="false">
                <h6 class="mb-0 fw-bold extra-small text-dark"><i class="bi bi-caret-right-fill me-2 toggle-icon"></i><?= strtoupper($instName) ?></h6>
            </div>
            <div id="body_<?= $inst_id ?>" class="collapse">
                <div class="card-body p-0">
                    <?php foreach($divisions as $divName => $items): $div_id = "div_" . md5($instName . $divName); ?>
                        <div class="division-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#div_body_<?= $div_id ?>" aria-expanded="false">
                            <div class="fw-bold extra-small text-dark"><i class="bi bi-caret-right-fill me-2 toggle-icon"></i><?= $divName ?></div>
                            <span class="badge rounded-pill bg-white text-secondary border px-2 fw-semibold" style="font-size: 0.65rem;"><?= count($items) ?> items</span>
                        </div>
                        <div id="div_body_<?= $div_id ?>" class="collapse px-3 pb-3">
                            <div class="table-responsive rounded border bg-white">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-3">Component / Specification</th>
                                            <th>Vendor</th>
                                            <th class="text-center">Unit Price</th>
                                            <th class="text-center">Stock Qty</th>
                                            <th class="pe-3 text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($items as $row): 
                                            $qty = (int)$row['total_quantity'];
                                            $badge = ($qty <= 5) ? 'bg-danger-subtle' : (($qty < 15) ? 'bg-amber-subtle' : 'bg-success-subtle');
                                        ?>
                                        <tr id="row-<?= $row['id'] ?>" class="inventory-row">
                                            <td class="ps-3">
                                                <div class="fw-bold text-dark extra-small"><?= htmlspecialchars($row['item_name']) ?></div>
                                                <div class="text-muted extra-small"><?= htmlspecialchars($row['category']) ?> • <?= htmlspecialchars($row['specification']) ?></div>
                                            </td>
                                            <td><span class="extra-small text-muted"><?= htmlspecialchars($row['vendor_name'] ?? 'Direct Stock') ?></span></td>
                                            <td class="text-center extra-small fw-bold text-secondary">₹<?= number_format($row['unit_price'], 2) ?></td>
                                            <td class="text-center"><span class="stock-badge <?= $badge ?>"><?= $qty ?> <small>pcs</small></span></td>
                                            <td class="pe-3 text-end">
                                                <button class="btn btn-icon edit-btn me-1" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['item_name']) ?>" data-cat="<?= htmlspecialchars($row['category']) ?>" data-spec="<?= htmlspecialchars($row['specification']) ?>" data-qty="<?= $qty ?>" data-price="<?= $row['unit_price'] ?>" data-vendor="<?= $row['vendor_id'] ?>"><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-icon delete-btn text-danger" data-id="<?= $row['id'] ?>"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    <?php if(isset($_SESSION['success'])): ?>
        const toast = new bootstrap.Toast(document.getElementById('liveToast'));
        $('#toastMsg').text("<?= $_SESSION['success'] ?>");
        toast.show();
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    $("#inventorySearch").on("keyup", function() {
        let value = $(this).val().toLowerCase();
        
        if (value === "") {
            $(".inventory-row").show();
            $(".collapse").collapse('hide');
            $(".institution-card, .division-header").show();
            return;
        }

        $(".inventory-row").each(function() {
            let rowText = $(this).text().toLowerCase();
            let isMatch = rowText.indexOf(value) > -1;

            $(this).toggle(isMatch);

            if (isMatch) {
                $(this).closest('.collapse').collapse('show');
                $(this).closest('.institution-card').find('> .collapse').collapse('show');
            }
        });

        $(".division-header").each(function() {
            let targetId = $(this).data('bs-target');
            let visibleRows = $(targetId).find(".inventory-row:visible").length;
            $(this).toggle(visibleRows > 0);
        });

        $(".institution-card").each(function() {
            let visibleRows = $(this).find(".inventory-row:visible").length;
            $(this).toggle(visibleRows > 0);
        });
    });

    $('.edit-btn').on('click', function(){
        $('#editId').val($(this).data('id'));
        $('#editName').val($(this).data('name'));
        $('#editCat').val($(this).data('cat'));
        $('#editSpec').val($(this).data('spec'));
        $('#editQty').val($(this).data('qty'));
        $('#editPrice').val($(this).data('price'));
        $('#editVendor').val($(this).data('vendor'));
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });

    $('#saveUpdate').on('click', function(){
        const data = { action: 'update_all', id: $('#editId').val(), name: $('#editName').val(), cat: $('#editCat').val(), spec: $('#editSpec').val(), qty: $('#editQty').val(), price: $('#editPrice').val(), v_id: $('#editVendor').val() };
        $.post('<?= $_SERVER['PHP_SELF'] ?>', data, function(res){ if(res.success) window.location.reload(); }, 'json');
    });

    $('.delete-btn').on('click', function(){
        const id = $(this).data('id');
        if (confirm("Permanently remove this item from the component registry?")) {
            $.post('<?= $_SERVER['PHP_SELF'] ?>', {action: 'delete', id: id}, function(res){
                if(res.success) window.location.reload();
            }, 'json');
        }
    });
});
</script>

<?php
$content = ob_get_clean();
ob_start();
?>
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-3">
            <div class="modal-header border-bottom py-3 px-4">
                <h6 class="fw-bold mb-0 text-dark">Edit Component Item</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="editId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Item Name</label>
                        <input type="text" id="editName" class="form-control form-control-sm rounded">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Category</label>
                        <select id="editCat" class="form-select form-select-sm rounded">
                            <option value="Microcontrollers">Microcontrollers</option>
                            <option value="Passives">Passives</option>
                            <option value="Modules">Modules</option>
                            <option value="Semiconductors">Semiconductors</option>
                            <option value="Connectors">Connectors</option>
                            <option value="Motors">Motors</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Vendor</label>
                        <select id="editVendor" class="form-select form-select-sm rounded">
                            <option value="">No Vendor</option>
                            <?php foreach($vendors as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vendor_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Specifications</label>
                        <input type="text" id="editSpec" class="form-control form-control-sm rounded">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Qty</label>
                        <input type="number" id="editQty" class="form-control form-control-sm rounded">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Price (₹)</label>
                        <input type="number" step="0.01" id="editPrice" class="form-control form-control-sm rounded">
                    </div>
                </div>
                <button class="btn btn-erp-primary w-100 mt-4 py-2" id="saveUpdate">Update Registry Item</button>
            </div>
        </div>
    </div>
</div>
<?php
$modal_html = ob_get_clean();

if ($user_role === 'SuperAdmin') { 
    include "../stock/stocklayout.php"; 
} else { 
    include "../divisions/divisionslayout.php"; 
}
?>