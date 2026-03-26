<?php 
include "../config/db.php"; 
session_start();

// --- 1. AJAX HANDLER (Enhanced for Full Updates) ---
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];

    if ($action == 'delete') {
        $id = (int)$_POST['id'];
        if ($conn->query("DELETE FROM component_stock WHERE id = $id")) {
            $response['success'] = true;
        }
    }

    // UPDATED: Handles all fields
    if ($action == 'update_all') {
        $id = (int)$_POST['id'];
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $cat = mysqli_real_escape_string($conn, $_POST['cat']);
        $spec = mysqli_real_escape_string($conn, $_POST['spec']);
        $qty = (int)$_POST['qty'];

        $sql = "UPDATE component_stock SET 
                item_name = '$name', 
                category = '$cat', 
                specification = '$spec', 
                total_quantity = $qty 
                WHERE id = $id";

        if ($conn->query($sql)) {
            $response['success'] = true;
        }
    }

    echo json_encode($response);
    exit(); 
}

ob_start();
?>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-4 px-2">
        <div class="col-md-5 mb-3 mb-md-0">
            <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.03rem;">Component Registry</h3>
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle me-1"></i> Master Registry — Real-time stock levels for electronic modules.
            </p>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="input-group bg-white border rounded-pill px-3 py-1 shadow-sm">
                <span class="input-group-text bg-transparent border-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" id="inventorySearch" class="form-control border-0 bg-transparent" placeholder="Search item, category, or specs...">
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
                        <th class="ps-4 py-3 text-uppercase small fw-bold text-muted" style="font-size: 11px; letter-spacing: 0.05rem;">Component Details</th>
                        <th class="py-3 text-uppercase small fw-bold text-muted text-center" style="font-size: 11px; letter-spacing: 0.05rem;">Stock Level</th>
                        <th class="pe-4 py-3 text-uppercase small fw-bold text-muted text-end" style="font-size: 11px; letter-spacing: 0.05rem;">Management</th>
                    </tr>
                </thead>
                <tbody class="border-0">
                    <?php
                    $res = $conn->query("SELECT * FROM component_stock ORDER BY id DESC");
                    if ($res->num_rows > 0):
                        while($row = $res->fetch_assoc()): 
                            $qty = $row['total_quantity'];
                            if ($qty <= 0) { $qtyColor = '#ef4444'; } 
                            elseif ($qty < 10) { $qtyColor = '#f59e0b'; } 
                            else { $qtyColor = '#059669'; } 
                        ?>
                        <tr id="row-<?= $row['id'] ?>" class="inventory-row border-bottom-0">
                            <td class="ps-4 py-3">
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-dark mb-1 fs-6 item-name-display"><?= htmlspecialchars($row['item_name']) ?></span>
                                    <div class="d-flex align-items-center gap-2 small text-muted searchable-meta">
                                        <span class="item-cat-display"><?= $row['category'] ?></span>
                                        <span style="font-size: 8px; opacity: 0.4;">●</span>
                                        <span class="item-spec-display"><?= htmlspecialchars($row['specification']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center py-3">
                                <div class="qty-container">
                                    <span class="fw-bold fs-4 qty-display" style="color: <?= $qtyColor ?>;">
                                        <?= $qty ?>
                                    </span>
                                    <span class="text-muted small ms-1">pcs</span>
                                </div>
                            </td>
                            <td class="pe-4 text-end py-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <button class="btn btn-icon edit-btn" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-name="<?= htmlspecialchars($row['item_name']) ?>" 
                                            data-cat="<?= $row['category'] ?>" 
                                            data-spec="<?= htmlspecialchars($row['specification']) ?>" 
                                            data-qty="<?= $row['total_quantity'] ?>">
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
                        <tr><td colspan="3" class="text-center py-5 text-muted">No components found.</td></tr>
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

    // 1. LIVE SEARCH
    $("#inventorySearch").on("keyup", function() {
        let value = $(this).val().toLowerCase();
        $("#inventoryTable tbody tr.inventory-row").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // 2. DELETE
    $('.delete-btn').on('click', function(){
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete Component?',
            text: "This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#f1f5f9',
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

    // 3. OPEN EDIT MODAL (Populates all fields)
    $('.edit-btn').on('click', function(){
        $('#editId').val($(this).data('id'));
        $('#editName').val($(this).data('name'));
        $('#editCat').val($(this).data('cat'));
        $('#editSpec').val($(this).data('spec'));
        $('#editQty').val($(this).data('qty'));
        
        const modal = new bootstrap.Modal(document.getElementById('editModal'));
        modal.show();
    });

    // 4. UPDATE AJAX (Sends all fields)
    $('#saveUpdate').on('click', function(){
        const data = {
            action: 'update_all',
            id: $('#editId').val(),
            name: $('#editName').val(),
            cat: $('#editCat').val(),
            spec: $('#editSpec').val(),
            qty: $('#editQty').val()
        };
        
        $.post(currentPath, data, function(res){
            if(res.success){
                const row = $(`#row-${data.id}`);
                
                // Update text on the main table
                row.find('.item-name-display').text(data.name);
                row.find('.item-cat-display').text(data.cat);
                row.find('.item-spec-display').text(data.spec);
                
                const qtySpan = row.find('.qty-display');
                qtySpan.text(data.qty);

                // Update data attributes on the edit button so next click is accurate
                const btn = row.find('.edit-btn');
                btn.data('name', data.name);
                btn.data('cat', data.cat);
                btn.data('spec', data.spec);
                btn.data('qty', data.qty);

                // Re-apply color logic
                if(data.qty <= 0) qtySpan.css('color', '#ef4444');
                else if(data.qty < 10) qtySpan.css('color', '#f59e0b');
                else qtySpan.css('color', '#059669');
                
                bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: 'Component updated', showConfirmButton: false, timer: 1500
                });
            }
        }, 'json');
    });
});
</script>

<style>

.table td { border-bottom: 1px solid #f8fafc; vertical-align: middle; }
.btn-icon {
    width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
    border-radius: 8px; border: none; background: transparent; color: #94a3b8; transition: all 0.2s;
}
.btn-icon:hover { background-color: #f1f5f9; color: #10b981; }
</style>

<?php
$content = ob_get_clean(); 

// --- 3. UPDATED MODAL BUFFER (Full Form) ---
ob_start();
?>
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 ps-4 pt-4">
                <h5 class="fw-bold mb-0">Edit Component</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="editId">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Item Name</label>
                    <input type="text" id="editName" class="form-control border-light-subtle rounded-3">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Category</label>
                    <select id="editCat" class="form-select border-light-subtle rounded-3">
                        <option value="Microcontrollers">Microcontrollers</option>
                                <option value="Passives">Passives (Resistors/Caps)</option>
                                <option value="Semiconductors">Semiconductors (ICs/Transistors)</option>
                                <option value="Modules">Sensors & Modules</option>
                                <option value="Connectors">Wires & Connectors</option>
                                <option value="Other">Other Components</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Specifications</label>
                    <input type="text" id="editSpec" class="form-control border-light-subtle rounded-3">
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Current Quantity</label>
                    <div class="input-group">
                        <input type="number" id="editQty" class="form-control border-light-subtle rounded-start-3">
                        <span class="input-group-text bg-light border-light-subtle rounded-end-3">pcs</span>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-light w-100 border rounded-pill py-2 fw-semibold text-muted" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn text-white w-100 shadow-sm rounded-pill py-2 fw-bold" id="saveUpdate" style="background-color: #10b981;">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$modal_html = ob_get_clean();
include "stocklayout.php";
?>