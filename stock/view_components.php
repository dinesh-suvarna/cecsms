<?php 
include "../config/db.php"; 
session_start();

// 1. Get user session data
$notif_division_id = $_SESSION['division_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'Division'; 

// --- 1. AJAX HANDLER ---
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];

    if ($action == 'delete') {
        $id = (int)$_POST['id'];
        // Logic change: Filter by division if not SuperAdmin
        $whereClause = ($user_role === 'SuperAdmin') ? "id = $id" : "id = $id AND division_id = $notif_division_id";
        if ($conn->query("DELETE FROM component_stock WHERE $whereClause")) {
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

        // Logic change: Filter by division if not SuperAdmin
        $whereClause = ($user_role === 'SuperAdmin') ? "id = $id" : "id = $id AND division_id = $notif_division_id";

        $sql = "UPDATE component_stock SET 
                item_name = '$name', 
                category = '$cat', 
                specification = '$spec', 
                total_quantity = $qty,
                unit_price = $price,
                vendor_id = $v_id 
                WHERE $whereClause";

        if ($conn->query($sql)) {
            $response['success'] = true;
        }
    }

    echo json_encode($response);
    exit(); 
}

// Fetch vendors for the modal dropdown
$vendor_list = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name ASC");
$vendors = [];
while($v = $vendor_list->fetch_assoc()) { $vendors[] = $v; }

ob_start();
?>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-4 px-2">
        <div class="col-md-5 mb-3 mb-md-0">
            <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.03rem;">
                <?= ($user_role === 'SuperAdmin') ? 'Master Registry' : 'Component Registry' ?>
            </h3>
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle me-1"></i> 
                <?= ($user_role === 'SuperAdmin') ? 'Master Registry — Tracking stock across all divisions.' : 'Master Registry — Tracking stock, vendor, and unit pricing.' ?>
            </p>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="input-group bg-white border rounded-pill px-3 py-1 shadow-sm">
                <span class="input-group-text bg-transparent border-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" id="inventorySearch" class="form-control border-0 bg-transparent" placeholder="Search item, vendor, or specs...">
            </div>
        </div>
        <div class="col-md-3 text-md-end">
            <a href="add_components.php" class="btn text-white btn-sm rounded-pill px-4 py-2 shadow-sm fw-bold" style="background-color: #10b981;">
                <i class="bi bi-plus-lg me-1"></i> Add Item
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="inventoryTable">
                <thead class="bg-white">
                    <tr class="border-bottom">
                        <th class="ps-4 py-3 text-uppercase small fw-bold text-muted" style="font-size: 11px;">Component Details</th>
                        <th class="py-3 text-uppercase small fw-bold text-muted" style="font-size: 11px;">Vendor</th>
                        <th class="py-3 text-uppercase small fw-bold text-muted text-center" style="font-size: 11px;">Unit Price</th>
                        <th class="py-3 text-uppercase small fw-bold text-muted text-center" style="font-size: 11px;">Stock</th>
                        <th class="pe-4 py-3 text-uppercase small fw-bold text-muted text-end" style="font-size: 11px;">Management</th>
                    </tr>
                </thead>
                <tbody class="border-0">
                    <?php
                    // Logic change: Show everything for SuperAdmin, filter for others
                    $sql_filter = ($user_role === 'SuperAdmin') ? "1=1" : "c.division_id = $notif_division_id";

                    $sql = "SELECT c.*, v.vendor_name 
                            FROM component_stock c 
                            LEFT JOIN vendors v ON c.vendor_id = v.id 
                            WHERE $sql_filter
                            ORDER BY c.id DESC";
                    $res = $conn->query($sql);
                    if ($res && $res->num_rows > 0):
                        while($row = $res->fetch_assoc()): 
                            $qty = $row['total_quantity'];
                            $qtyColor = ($qty <= 0) ? '#ef4444' : (($qty < 10) ? '#f59e0b' : '#059669');
                        ?>
                        <tr id="row-<?= $row['id'] ?>" class="inventory-row border-bottom-0">
                            <td class="ps-4 py-3">
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-dark mb-1 fs-5 item-name-display" style="letter-spacing: -0.01rem;">
                                        <?= htmlspecialchars($row['item_name']) ?>
                                    </span>
                                    <div class="d-flex align-items-center gap-2 text-secondary searchable-meta" style="font-size: 0.9rem;">
                                        <span class="item-cat-display"><?= $row['category'] ?></span>
                                        <span class="text-muted opacity-50">•</span>
                                        <span class="item-spec-display"><?= htmlspecialchars($row['specification']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="small fw-medium text-dark item-vendor-display">
                                    <?= $row['vendor_name'] ?? 'Direct/Unknown' ?>
                                </span>
                            </td>
                            <td class="py-3 text-center">
                                <span class="fw-bold text-dark">₹<span class="item-price-display"><?= number_format($row['unit_price'], 2) ?></span></span>
                            </td>
                            <td class="text-center py-3">
                                <div class="qty-container">
                                    <span class="fw-bold fs-5 qty-display" style="color: <?= $qtyColor ?>;">
                                        <?= $qty ?>
                                    </span>
                                    <span class="text-muted extra-small ms-1">pcs</span>
                                </div>
                            </td>
                            <td class="pe-4 text-end py-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <button class="btn btn-icon edit-btn" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-name="<?= htmlspecialchars($row['item_name']) ?>" 
                                            data-cat="<?= $row['category'] ?>" 
                                            data-spec="<?= htmlspecialchars($row['specification']) ?>" 
                                            data-qty="<?= $row['total_quantity'] ?>"
                                            data-price="<?= $row['unit_price'] ?>"
                                            data-vendor="<?= $row['vendor_id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-icon delete-btn text-danger" data-id="<?= $row['id'] ?>">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; 
                    else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No components found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    const currentPath = '<?= $_SERVER['PHP_SELF'] ?>';

    $("#inventorySearch").on("keyup", function() {
        let value = $(this).val().toLowerCase();
        $("#inventoryTable tbody tr.inventory-row").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    $('.delete-btn').on('click', function(){
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete Component?',
            text: "This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Delete',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(currentPath, {action: 'delete', id: id}, function(res){
                    if(res.success){
                        $(`#row-${id}`).fadeOut(400, function(){ $(this).remove(); });
                    }
                }, 'json');
            }
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
        
        const modal = new bootstrap.Modal(document.getElementById('editModal'));
        modal.show();
    });

    $('#saveUpdate').on('click', function(){
        const data = {
            action: 'update_all',
            id: $('#editId').val(),
            name: $('#editName').val(),
            cat: $('#editCat').val(),
            spec: $('#editSpec').val(),
            qty: $('#editQty').val(),
            price: $('#editPrice').val(),
            v_id: $('#editVendor').val()
        };
        
        $.post(currentPath, data, function(res){
            if(res.success){
                const row = $(`#row-${data.id}`);
                const vendorName = $("#editVendor option:selected").text();
                
                row.find('.item-name-display').text(data.name);
                row.find('.item-cat-display').text(data.cat);
                row.find('.item-spec-display').text(data.spec);
                row.find('.item-vendor-display').text(data.v_id ? vendorName : 'Direct/Unknown');
                row.find('.item-price-display').text(parseFloat(data.price).toFixed(2));
                
                const qtySpan = row.find('.qty-display');
                qtySpan.text(data.qty);

                const btn = row.find('.edit-btn');
                btn.data('name', data.name);
                btn.data('cat', data.cat);
                btn.data('spec', data.spec);
                btn.data('qty', data.qty);
                btn.data('price', data.price);
                btn.data('vendor', data.v_id);

                if(data.qty <= 0) qtySpan.css('color', '#ef4444');
                else if(data.qty < 10) qtySpan.css('color', '#f59e0b');
                else qtySpan.css('color', '#059669');
                
                bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Registry updated', showConfirmButton: false, timer: 1500 });
            }
        }, 'json');
    });
});
</script>

<style>
.table td { border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.item-name-display { font-size: 1.1rem !important; color: #334155 !important; }
.searchable-meta { font-weight: 400; color: #64748b !important; }
.item-vendor-display { color: #1e293b !important; font-weight: 500; }
.extra-small { font-size: 11px; }
.btn-icon {
    width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
    border-radius: 8px; border: none; background: transparent; color: #94a3b8; transition: all 0.2s;
}
.btn-icon:hover { background-color: #f1f5f9; color: #10b981; }
</style>

<?php
$content = ob_get_clean(); 
ob_start();
?>
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 ps-4 pt-4">
                <h5 class="fw-bold mb-0">Edit Registry Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="editId">
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted text-uppercase">Item Name</label>
                        <input type="text" id="editName" class="form-control border-light-subtle rounded-3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Category</label>
                        <select id="editCat" class="form-select border-light-subtle rounded-3">
                            <option value="Microcontrollers">Microcontrollers</option>
                            <option value="Passives">Passives</option>
                            <option value="Semiconductors">Semiconductors</option>
                            <option value="Modules">Sensors & Modules</option>
                            <option value="Connectors">Connectors</option>
                            <option value="Motors">Motors</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Vendor</label>
                        <select id="editVendor" class="form-select border-light-subtle rounded-3">
                            <option value="">No Vendor</option>
                            <?php foreach($vendors as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= $v['vendor_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted text-uppercase">Specifications</label>
                        <input type="text" id="editSpec" class="form-control border-light-subtle rounded-3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Quantity</label>
                        <input type="number" id="editQty" class="form-control border-light-subtle rounded-3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Unit Price (₹)</label>
                        <input type="number" step="0.01" id="editPrice" class="form-control border-light-subtle rounded-3">
                    </div>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-light w-100 border rounded-pill py-2 fw-semibold text-muted" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn text-white w-100 shadow-sm rounded-pill py-2 fw-bold" id="saveUpdate" style="background-color: #10b981;">Update Item</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$modal_html = ob_get_clean();

if ($user_role === 'SuperAdmin') {
    include "stocklayout.php";
} else {
    include "../divisions/divisionslayout.php";
}
?>