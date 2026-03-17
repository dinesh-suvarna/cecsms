<?php
require_once "../config/db.php";
require_once "../includes/session.php";
require_once "../admin/auth.php";

/* ---------- NOTIFY ---------- */
if (!function_exists('notify')) {
    function notify($type, $msg){
        $_SESSION['notify_type'] = $type; 
        $_SESSION['notify_msg']  = $msg;
    }
}

$page_title = "Item Master";
$page_icon  = "bi-boxes";

/* ---------- ADD ITEM ---------- */
if(isset($_POST['submit'])){
    $item_name  = trim($_POST['item_name']);
    $category   = $_POST['category'] ?? '';
    $stock_type = $_POST['stock_type'] ?? 'serial';

    if(empty($item_name)){
        notify("danger","Item name is required.");
    } elseif(!in_array($stock_type, ['serial','non_serial'])){
        notify("danger","Invalid stock type.");
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO items_master (item_name, category, stock_type) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $item_name, $category, $stock_type);
            $stmt->execute();
            notify("success","Item added successfully!");
        } catch(mysqli_sql_exception $e){
            notify("danger", $e->getCode()==1062 ? "Item already exists!" : "Database error.");
        }
    }
    header("Location: items_master.php");
    exit;
}

/* ---------- DELETE ---------- */
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    $check = $conn->prepare("SELECT id FROM stock_details WHERE stock_item_id=? LIMIT 1");
    $check->bind_param("i",$id);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        notify("danger","Cannot delete. Linked to stock.");
    } else {
        $stmt = $conn->prepare("DELETE FROM items_master WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        notify("success","Deleted successfully.");
    }
    header("Location: items_master.php");
    exit;
}

/* ---------- UPDATE ---------- */
if(isset($_POST['update'])){
    $id = intval($_POST['id']);
    $name = trim($_POST['item_name']);
    $cat  = $_POST['category'];

    if($name){
        try{
            $stmt = $conn->prepare("UPDATE items_master SET item_name=?, category=? WHERE id=?");
            $stmt->bind_param("ssi",$name,$cat,$id);
            $stmt->execute();
            notify("success","Updated successfully!");
        }catch(mysqli_sql_exception $e){
            notify("danger",$e->getCode()==1062?"Duplicate name":"Database error");
        }
    }
    header("Location: items_master.php");
    exit;
}

/* ---------- DATA ---------- */
$items = $conn->query("SELECT * FROM items_master ORDER BY item_name ASC");

ob_start();
?>

<!-- TOAST -->
<?php
$type = $_SESSION['notify_type'] ?? '';
$msg  = $_SESSION['notify_msg'] ?? '';
unset($_SESSION['notify_type'], $_SESSION['notify_msg']);
if($msg): ?>
<div class="position-fixed top-0 end-0 p-3" style="z-index:2000;">
    <div class="toast show text-bg-<?= $type ?> border-0 shadow-lg">
        <div class="d-flex">
            <div class="toast-body"><?= htmlspecialchars($msg) ?></div>
            <button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">

<!-- LEFT: ADD -->
<div class="col-lg-4">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4"><i class="bi bi-plus-circle text-success me-2"></i>Add Item</h5>

            <form method="POST">
                <div class="mb-3">
                    <label class="small fw-bold text-muted">Stock Type</label>
                    <select name="stock_type" class="form-select rounded-3">
                        <option value="serial">Serialized</option>
                        <option value="non_serial">Non-Serialized (Bulk Quantity)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold text-muted">Category</label>
                    <select name="category" class="form-select rounded-3">
                        <option>Computer</option>
                        <option>Accessory</option>
                        <option>Component</option>
                        <option>Networking</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="small fw-bold text-muted">Item Name</label>
                    <input type="text" name="item_name" class="form-control rounded-3" required>
                </div>

                <button type="submit" name="submit" class="btn btn-success w-100 rounded-pill fw-bold">
                    Save Item
                </button>
            </form>
        </div>
    </div>
</div>

<!-- RIGHT: TABLE -->
<div class="col-lg-8">
    <div class="card shadow-sm border-0 rounded-4 p-4">

        <div class="d-flex justify-content-between mb-4">
            <h5 class="fw-bold m-0">Existing Items</h5>
            <input type="text" id="search" class="form-control form-control-sm w-50 rounded-pill" placeholder="Search...">
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="tbl">
                <thead class="bg-light small text-muted">
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row=$items->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-bold name"><?= $row['item_name'] ?></td>
                        <td class="cat"><?= $row['category'] ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-white border"
                                onclick="editItem(<?= $row['id'] ?>,'<?= addslashes($row['item_name']) ?>','<?= $row['category'] ?>')">
                                ✏️
                            </button>
                            <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-white border">🗑️</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

</div>

<!-- MODAL -->
<?php
$modal_html='
<div class="modal" id="editModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content rounded-4">
<form method="POST">
<div class="modal-body p-4">
<input type="hidden" name="id" id="eid">
<input type="text" name="item_name" id="ename" class="form-control mb-2">
<select name="category" id="ecat" class="form-select">
<option>Computer</option><option>Accessory</option>
</select>
</div>
<div class="p-3 text-end">
<button type="submit" name="update" class="btn btn-success">Update</button>
</div>
</form>
</div>
</div>
</div>';
?>

<script>
function editItem(id,name,cat){
    eid.value=id; ename.value=name; ecat.value=cat;
    new bootstrap.Modal(editModal).show();
}

search.onkeyup=function(){
    let f=this.value.toLowerCase();
    document.querySelectorAll("#tbl tbody tr").forEach(r=>{
        r.style.display = r.innerText.toLowerCase().includes(f) ? "" : "none";
    });
}
</script>

<?php
$main_content = ob_get_clean();
include "../master/masterlayout.php";
?>