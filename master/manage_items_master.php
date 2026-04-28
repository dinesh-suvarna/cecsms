<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";
require_once "../admin/auth.php";

if (!function_exists('notify')) {
    function notify($type, $msg){
        $_SESSION['notify_type'] = $type; 
        $_SESSION['notify_msg']  = $msg;
    }
}

$page_title = "Manage Items (Master)";
$page_icon  = "bi-boxes";

/* ================= DELETE ITEM ================= */
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    $check = $conn->prepare("SELECT id FROM stock_details WHERE stock_item_id = ? LIMIT 1");
    $check->bind_param("i", $id);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        notify("danger", "Cannot delete. This item is already linked to stock records.");
    } else {
        $stmt = $conn->prepare("DELETE FROM items_master WHERE id = ?");
        $stmt->bind_param("i", $id);
        if($stmt->execute()){
            notify("success", "Item deleted successfully!");
        }
    }
    header("Location: manage_items_master.php");
    exit;
}

/* ================= UPDATE ITEM ================= */
if(isset($_POST['update'])){
    $id        = intval($_POST['id']);
    $item_name = trim($_POST['item_name']);
    $category  = $_POST['category'];

    if(!empty($item_name)){
        try {
            $stmt = $conn->prepare("UPDATE items_master SET item_name = ?, category = ? WHERE id = ?");
            $stmt->bind_param("ssi", $item_name, $category, $id);
            $stmt->execute();
            notify("success", "Item updated successfully!");
        } catch(mysqli_sql_exception $e){
            $msg = ($e->getCode() == 1062) ? "Item name already exists!" : "Database error.";
            notify("danger", $msg);
        }
    }
    header("Location: manage_items_master.php");
    exit;
}

$items = $conn->query("SELECT * FROM items_master ORDER BY item_name ASC");
ob_start();
?>

<?php
$type = $_SESSION['notify_type'] ?? '';
$msg  = $_SESSION['notify_msg'] ?? '';
unset($_SESSION['notify_type'], $_SESSION['notify_msg']);

if($msg): ?>
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1060;">
    <div class="toast align-items-center text-bg-<?= $type ?> border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="d-flex">
            <div class="toast-body fw-medium"><i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($msg) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function(){
    var toastEl = document.querySelector('.toast');
    if(toastEl){ new bootstrap.Toast(toastEl).show(); }
});
</script>
<?php endif; ?>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <div class="row align-items-center mb-4 g-3">
            <div class="col-md-4">
                <h5 class="fw-bold m-0"><i class="bi bi-boxes me-2 text-success"></i>Stock Items</h5>
            </div>
            <div class="col-md-5">
                <div class="input-group input-group-sm shadow-sm rounded-pill overflow-hidden border">
                    <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="tableSearch" class="form-control border-0 py-2" placeholder="Search items or categories...">
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <a href="add_items_master.php" class="btn btn-success btn-sm rounded-pill px-4 shadow-sm fw-bold">
                    <i class="bi bi-plus-lg me-1"></i> Add New
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle border-0" id="itemsTable">
                <thead>
                    <tr class="text-muted small text-uppercase bg-light">
                        <th class="ps-3 border-0 rounded-start">Sl.No</th>
                        <th class="border-0">Item Name</th>
                        <th class="border-0">Category</th>
                        <th class="text-end pe-3 border-0 rounded-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; while($row = $items->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-3 text-muted"><?= $i++ ?></td>
                        <td class="fw-semibold text-dark item-name"><?= htmlspecialchars($row['item_name']) ?></td>
                        <td><span class="badge bg-emerald-soft text-success rounded-pill px-3 item-category"><?= $row['category'] ?></span></td>
                        <td class="text-end pe-3">
                            <button type="button" class="btn btn-sm btn-white border shadow-sm rounded-3"
                                    onclick="openEditModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['item_name'])) ?>', '<?= $row['category'] ?>')">
                                <i class="bi bi-pencil-square text-primary"></i>
                            </button>
                            <a href="manage_items_master.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-white border shadow-sm rounded-3 ms-1" onclick="return confirm('Delete this item?');">
                                <i class="bi bi-trash text-danger"></i>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .bg-emerald-soft { background-color: rgba(16, 185, 129, 0.1); }
    .btn-white { background: #fff; border-color: #f1f5f9 !important; }
    .modal { z-index: 9999 !important; background: rgba(0,0,0,0.4); }
    .modal-backdrop { display: none !important; }
    #tableSearch:focus { box-shadow: none; }
</style>

<script>
// Modal Logic
function openEditModal(id, name, category) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_category').value = category;
    new bootstrap.Modal(document.getElementById('editModal'), { backdrop: false }).show();
}

// Live Search Logic
document.getElementById('tableSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#itemsTable tbody tr');
    
    rows.forEach(row => {
        let name = row.querySelector('.item-name').innerText.toLowerCase();
        let cat  = row.querySelector('.item-category').innerText.toLowerCase();
        row.style.display = (name.includes(filter) || cat.includes(filter)) ? '' : 'none';
    });
});
</script>

<?php
// This captures the table and search bar
$main_content = ob_get_clean(); 

// This stores the modal separately
$modal_html = '
<div class="modal" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold text-dark">Edit Master Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Item Name</label>
                        <input type="text" name="item_name" id="edit_name" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Category</label>
                        <select name="category" id="edit_category" class="form-select rounded-3" required>
                            <option value="Computer">Computer</option>
                            <option value="Accessory">Accessory</option>
                            <option value="Component">Component</option>
                            <option value="Networking">Networking</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>';

include "../master/masterlayout.php";
?>