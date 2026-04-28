<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";
require_once "../admin/auth.php";

/* ---------- NOTIFY ---------- */
if (!function_exists('notify')) {
    function notify($type, $msg){
        $_SESSION['swal_type'] = $type; // 'success', 'error', 'warning'
        $_SESSION['swal_msg']  = $msg;
    }
}

$page_title = "Asset Registry";
$page_icon  = "bi-boxes";

/* ---------- UPDATE ---------- */
if(isset($_POST['update'])){
    $id    = intval($_POST['id']);
    $name  = trim($_POST['item_name']);
    $cat   = $_POST['category'];
    $type  = $_POST['stock_type'] ?? null; // Disabled fields aren't sent in POST

    if(!empty($name)){
        try {
            if($type) {
                // Update everything including type
                $stmt = $conn->prepare("UPDATE items_master SET item_name=?, category=?, stock_type=? WHERE id=?");
                $stmt->bind_param("sssi", $name, $cat, $type, $id);
            } else {
                // Update only name and category (stock exists, type was disabled)
                $stmt = $conn->prepare("UPDATE items_master SET item_name=?, category=? WHERE id=?");
                $stmt->bind_param("ssi", $name, $cat, $id);
            }
            $stmt->execute();
            notify("success", "Updated successfully!");
        } catch(mysqli_sql_exception $e) {
            notify("danger", "Error updating record.");
        }
    }
    header("Location: items_master.php");
    exit;
}

/* ---------- ADD ITEM ---------- */
if(isset($_POST['submit'])){
    $item_name  = trim($_POST['item_name']);
    $category   = $_POST['category'] ?? '';
    $stock_type = $_POST['stock_type'] ?? 'serial';

    if(empty($item_name)){
        notify("danger","Item name is required.");
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
        notify("danger","Cannot delete. Linked to existing stock records.");
    } else {
        $stmt = $conn->prepare("DELETE FROM items_master WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        notify("success","Deleted successfully.");
    }
    header("Location: items_master.php");
    exit;
}

/* ---------- DATA QUERY (WITH STOCK COUNTS) ---------- */
$query = "
    SELECT 
        im.*,
        (SELECT COUNT(*) FROM stock_details sd_count WHERE sd_count.stock_item_id = im.id) as stock_exists,
        IFNULL((SELECT SUM(quantity) FROM stock_details sd_sum WHERE sd_sum.stock_item_id = im.id), 0) as total_purchased,
        IFNULL((
            SELECT SUM(dd.quantity - IFNULL(dd.returned_quantity, 0))
            FROM dispatch_details dd
            JOIN stock_details sd2 ON dd.stock_detail_id = sd2.id
            WHERE sd2.stock_item_id = im.id
        ), 0) as total_dispatched
    FROM items_master im
    ORDER BY im.item_name ASC
";
$items = $conn->query($query);

ob_start();
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4 sticky-top" style="top: 20px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-plus-circle text-success me-2"></i>Add Asset Category</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Stock Tracking Type</label>
                        <select name="stock_type" class="form-select rounded-3">
                            <option value="serial">Serialized (Track by Serial No.)</option>
                            <option value="non_serial">Non-Serialized (Bulk Quantity)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Category Group</label>
                        <select name="category" class="form-select rounded-3">
                            <option>Computer</option>
                            <option>Accessory</option>
                            <option>Component</option>
                            <option>Networking</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="small fw-bold text-muted">Item Description/Name</label>
                        <input type="text" name="item_name" class="form-control rounded-3" placeholder="e.g. Dell Latitude 3420" required>
                    </div>
                    <button type="submit" name="submit" class="btn btn-success w-100 rounded-pill fw-bold">Add to Registry</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0">Master Asset Registry</h5>
                <div class="input-group w-50">
                    <span class="input-group-text bg-transparent border-end-0 rounded-start-pill"><i class="bi bi-search"></i></span>
                    <input type="text" id="search" class="form-control border-start-0 rounded-end-pill" placeholder="Filter items...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tbl">
                    <thead class="bg-light small text-muted text-uppercase">
                        <tr>
                            <th>Item Detail</th>
                            <th class="text-center">Total In</th>
                            <th class="text-center">Dispatched</th>
                            <th class="text-center">Available</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($row=$items->fetch_assoc()): 
                        $available = $row['total_purchased'] - $row['total_dispatched'];
                        $type_badge = ($row['stock_type'] == 'serial') ? 'bg-primary' : 'bg-info text-dark';
                    ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['item_name']) ?></div>
                                <div class="small text-muted">
                                    <span class="badge <?= $type_badge ?> p-1" style="font-size: 10px;"><?= strtoupper($row['stock_type']) ?></span>
                                    <?php if($row['stock_exists'] > 0): ?>
                                        <i class="bi bi-lock-fill text-muted ms-1" title="Stock records locked"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($row['category']) ?>
                                </div>
                            </td>
                            <td class="text-center fw-semibold text-dark"><?= number_format($row['total_purchased']) ?></td>
                            <td class="text-center text-danger"><?= number_format($row['total_dispatched']) ?></td>
                            <td class="text-center">
                                <span class="badge rounded-pill <?= ($available > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger') ?> px-3">
                                    <?= number_format($available) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary border-0" 
                                            onclick="editItem(<?= $row['id'] ?>,'<?= addslashes($row['item_name']) ?>','<?= $row['category'] ?>','<?= $row['stock_type'] ?>', <?= $row['stock_exists'] ?>)">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a href="javascript:void(0)" class="btn btn-sm btn-outline-danger border-0 delete-btn" data-id="<?= $row['id'] ?>">
                                        <i class="bi bi-trash3"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$modal_html='
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST" id="editForm">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Item Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="eid">
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Item Name</label>
                        <input type="text" name="item_name" id="ename" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Category</label>
                        <select name="category" id="ecat" class="form-select rounded-3">
                            <option>Computer</option>
                            <option>Accessory</option>
                            <option>Component</option>
                            <option>Networking</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Stock Tracking Type</label>
                        <select name="stock_type" id="etype" class="form-select rounded-3">
                            <option value="serial">Serialized (Track by Serial No.)</option>
                            <option value="non_serial">Non-Serialized (Bulk Quantity)</option>
                        </select>
                        <small id="typeWarning" class="text-muted" style="font-size: 11px;">Warning: Changing type may affect inventory logic.</small>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update" class="btn btn-primary rounded-pill px-4 fw-bold">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
if(isset($_SESSION['swal_msg'])): 
    $type = $_SESSION['swal_type'] == 'danger' ? 'error' : $_SESSION['swal_type'];
    $msg  = $_SESSION['swal_msg'];
    unset($_SESSION['swal_type'], $_SESSION['swal_msg']);
?>
<script>
    Swal.fire({
        icon: '<?= $type ?>',
        title: '<?= ($type == "success" ? "Done!" : "Oops...") ?>',
        text: '<?= htmlspecialchars($msg) ?>',
        timer: 3000,
        showConfirmButton: false
    });
</script>
<?php endif; ?>

<script>
function editItem(id, name, cat, type, stockCount) {
    document.getElementById('eid').value = id;
    document.getElementById('ename').value = name;
    document.getElementById('ecat').value = cat;
    
    const typeSelect = document.getElementById('etype');
    const typeWarning = document.getElementById('typeWarning');
    
    typeSelect.value = type;

    if (stockCount > 0) {
        typeSelect.disabled = true;
        typeWarning.innerHTML = "<i class='bi bi-lock-fill'></i> Type locked: " + stockCount + " stock records exist.";
        typeWarning.className = "text-danger small d-block mt-1";
    } else {
        typeSelect.disabled = false;
        typeWarning.innerText = "No stock linked. You can safely change the type.";
        typeWarning.className = "text-muted small d-block mt-1";
    }
    
    var myModal = new bootstrap.Modal(document.getElementById('editModal'));
    myModal.show();
}

// Search Logic
document.getElementById('search').onkeyup = function() {
    let filter = this.value.toLowerCase();
    document.querySelectorAll("#tbl tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
}

// Delete Confirmation
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        Swal.fire({
            title: 'Are you sure?',
            text: "This will only delete if no stock is linked!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `items_master.php?delete=${id}`;
            }
        });
    });
});
</script>

<?php
$main_content = ob_get_clean();
include "../master/masterlayout.php";
?>