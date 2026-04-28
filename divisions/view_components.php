<?php 
require_once __DIR__ . "/../config/db.php";
session_start();
$page_title = "Component Stock Registry";

$notif_division_id = $_SESSION['division_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'Division'; 

// --- 1. AJAX HANDLER ---
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
:root { --brand-emerald: #10b981; --brand-forest: #065f46; --slate-50: #f8fafc; --slate-200: #e2e8f0; }
.collapse { transition: height 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; }
.institution-card { border: 1px solid #eef0f3 !important; border-radius: 12px; margin-bottom: 1rem; background: #fff; }
.inst-header { background-color: #fff !important; border-left: 4px solid var(--brand-emerald) !important; cursor: pointer; transition: background 0.2s; }
.inst-header:hover { background-color: var(--slate-50) !important; }
.division-header { background-color: #f1f5f9 !important; border-left: 5px solid #475569 !important; border-radius: 6px; margin: 8px 15px; padding: 10px 15px !important; cursor: pointer; }
.stock-badge { padding: 4px 12px; border-radius: 12px; font-weight: 700; font-size: 0.82rem; }
.btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid transparent; background: transparent; color: #94a3b8; transition: all 0.2s; }
.btn-icon:hover { background: #f8fafc; color: var(--brand-emerald); border-color: var(--slate-200); }
.toggle-icon { transition: transform 0.3s ease; font-size: 0.8rem; color: #94a3b8; }
[aria-expanded="true"] .toggle-icon { transform: rotate(90deg); color: var(--brand-forest); }
</style>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1060;">
    <div id="liveToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true" style="background: #1e293b;">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center">
                <i class="bi bi-check-circle-fill text-emerald-600 me-2"></i>
                <span id="toastMsg"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-4 px-2">
        <div class="col-md-5">
            <h3 class="fw-bold mb-1"><?= ($user_role === 'SuperAdmin') ? 'Master Registry' : 'Component Registry' ?></h3>
            <p class="text-muted small mb-0"><i class="bi bi-building text-emerald-600 me-1"></i> Categorized by Institution.</p>
        </div>
        <div class="col-md-4">
            <div class="input-group rounded-pill border bg-white px-3 py-1">
                <span class="input-group-text bg-transparent border-0"><i class="bi bi-search"></i></span>
                <input type="text" id="inventorySearch" class="form-control border-0 bg-transparent" placeholder="Filter stock...">
            </div>
        </div>
        <div class="col-md-3 text-md-end">
            <a href="add_components.php" class="btn text-white btn-sm rounded-pill px-4 py-2 fw-bold" style="background-color: var(--brand-emerald);">
                <i class="bi bi-plus-lg me-1"></i> Add Item
            </a>
        </div>
    </div>

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
        <div class="card institution-card overflow-hidden shadow-sm">
            <div class="card-header inst-header py-3 px-4 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#body_<?= $inst_id ?>" aria-expanded="false">
                <h6 class="mb-0 fw-bold"><i class="bi bi-caret-right-fill me-2 toggle-icon"></i><?= strtoupper($instName) ?></h6>
            </div>
            <div id="body_<?= $inst_id ?>" class="collapse">
                <div class="card-body p-0">
                    <?php foreach($divisions as $divName => $items): $div_id = "div_" . md5($instName . $divName); ?>
                        <div class="division-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#div_body_<?= $div_id ?>" aria-expanded="false">
                            <div class="fw-bold small text-dark"><i class="bi bi-caret-right-fill me-2 toggle-icon"></i><?= $divName ?></div>
                            <span class="badge rounded-pill bg-white text-dark border px-2 fw-normal" style="font-size: 0.7rem;"><?= count($items) ?> items</span>
                        </div>
                        <div id="div_body_<?= $div_id ?>" class="collapse px-3 pb-3">
                            <div class="table-responsive rounded-3 border bg-white">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr style="background: #fcfcfc;">
                                            <th class="ps-3 border-0">Item</th>
                                            <th class="border-0">Vendor</th>
                                            <th class="text-center border-0">Price</th>
                                            <th class="text-center border-0">Stock</th>
                                            <th class="pe-3 text-end border-0">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($items as $row): 
                                            $qty = (int)$row['total_quantity'];
                                            $badge = ($qty <= 5) ? 'bg-danger-subtle text-danger' : (($qty < 15) ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success');
                                        ?>
                                        <tr id="row-<?= $row['id'] ?>" class="inventory-row">
                                            <td class="ps-3">
                                                <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($row['item_name']) ?></div>
                                                <div class="text-muted extra-small"><?= $row['category'] ?> • <?= htmlspecialchars($row['specification']) ?></div>
                                            </td>
                                            <td><span class="small text-muted"><?= $row['vendor_name'] ?? 'Direct' ?></span></td>
                                            <td class="text-center fw-bold text-secondary">₹<?= number_format($row['unit_price'], 2) ?></td>
                                            <td class="text-center"><span class="stock-badge <?= $badge ?>"><?= $qty ?> <small>pcs</small></span></td>
                                            <td class="pe-3 text-end">
                                                <button class="btn btn-icon edit-btn" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['item_name']) ?>" data-cat="<?= $row['category'] ?>" data-spec="<?= htmlspecialchars($row['specification']) ?>" data-qty="<?= $qty ?>" data-price="<?= $row['unit_price'] ?>" data-vendor="<?= $row['vendor_id'] ?>"><i class="bi bi-pencil-square"></i></button>
                                                <button class="btn btn-icon delete-btn text-danger" data-id="<?= $row['id'] ?>"><i class="bi bi-trash3"></i></button>
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
    // Check for success session and trigger toast
    <?php if(isset($_SESSION['success'])): ?>
        const toast = new bootstrap.Toast(document.getElementById('liveToast'));
        $('#toastMsg').text("<?= $_SESSION['success'] ?>");
        toast.show();
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    $("#inventorySearch").on("keyup", function() {
    let value = $(this).val().toLowerCase();
    
    if (value === "") {
        // If search is empty, hide rows and collapse everything back
        $(".inventory-row").show();
        $(".collapse").collapse('hide');
        $(".institution-card, .division-header").show();
        return;
    }

    // 1. Loop through every row
    $(".inventory-row").each(function() {
        let rowText = $(this).text().toLowerCase();
        let isMatch = rowText.indexOf(value) > -1;

        $(this).toggle(isMatch);

        // 2. If it's a match, ensure the parents are expanded and visible
        if (isMatch) {
            // Expand the Division container
            $(this).closest('.collapse').collapse('show');
            // Expand the Institution container
            $(this).closest('.institution-card').find('> .collapse').collapse('show');
        }
    });

    // 3. Hide Division Headers that have no visible rows
    $(".division-header").each(function() {
        let targetId = $(this).data('bs-target');
        let visibleRows = $(targetId).find(".inventory-row:visible").length;
        $(this).toggle(visibleRows > 0);
    });

    // 4. Hide Institution Cards that have no visible rows
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
        Swal.fire({
            title: 'Remove Item?',
            text: "This component will be permanently deleted from the registry.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('<?= $_SERVER['PHP_SELF'] ?>', {action: 'delete', id: id}, function(res){
                    if(res.success) window.location.reload();
                }, 'json');
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
ob_start();
?>
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 ps-4 pt-4"><h5 class="fw-bold">Edit Registry Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <input type="hidden" id="editId">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label small fw-bold">ITEM NAME</label><input type="text" id="editName" class="form-control rounded-3"></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">CATEGORY</label><select id="editCat" class="form-select rounded-3"><option value="Microcontrollers">Microcontrollers</option><option value="Passives">Passives</option><option value="Modules">Modules</option><option value="Other">Other</option></select></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">VENDOR</label><select id="editVendor" class="form-select rounded-3"><option value="">No Vendor</option><?php foreach($vendors as $v): ?><option value="<?= $v['id'] ?>"><?= $v['vendor_name'] ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label small fw-bold">SPECIFICATIONS</label><input type="text" id="editSpec" class="form-control rounded-3"></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">QTY</label><input type="number" id="editQty" class="form-control rounded-3"></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">PRICE (₹)</label><input type="number" step="0.01" id="editPrice" class="form-control rounded-3"></div>
                </div>
                <button class="btn text-white w-100 mt-4 rounded-pill py-2 fw-bold shadow-sm" id="saveUpdate" style="background-color: var(--brand-emerald);">Update Registry</button>
            </div>
        </div>
    </div>
</div>
<?php
$modal_html = ob_get_clean();
if ($user_role === 'SuperAdmin') { 
    include "../stock/stocklayout.php"; 
} 
else { 
    include "../divisions/divisionslayout.php"; }
?>