<?php
require_once __DIR__ . "/../config/db.php";
require_once "../includes/session.php";
require_once "../admin/auth.php";

if (!function_exists('notify')) {
    function notify($type, $msg){
        $_SESSION['swal_type'] = ($type == 'danger') ? 'error' : $type; 
        $_SESSION['swal_msg']  = $msg;
    }
}

$page_title = "Stock Specifications";
$page_icon  = "bi-sliders";

/* ---------------- ADD MODEL ---------------- */
if(isset($_POST['add_model'])){
    $item_id      = $_POST['item_id'];
    $model_name   = trim(strtoupper($_POST['model_name']));
    $processor    = trim($_POST['processor']);
    $ram          = trim($_POST['ram']);
    $storage_type = $_POST['storage_type'];
    $storage_size = trim($_POST['storage_size']);

    // 1. Manual Check for Duplicate 
    $check = $conn->prepare("SELECT id FROM item_models WHERE item_id=? AND model_name=?");
    $check->bind_param("is", $item_id, $model_name);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        notify("warning", "Model '$model_name' already exists for this item.");
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO item_models (item_id, model_name, processor, ram, storage_type, storage_size) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $item_id, $model_name, $processor, $ram, $storage_type, $storage_size);
            
            if($stmt->execute()){
                notify("success", "Model added successfully!");
            }
        } catch (Exception $e) {
            notify("danger", "Database Error: " . $e->getMessage());
        }
    }
    header("Location: stock_specifications.php");
    exit;
}

/* ---------------- DELETE MODEL ---------------- */
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    $check = $conn->prepare("SELECT id FROM stock_details WHERE model_id = ? LIMIT 1");
    $check->bind_param("i", $id);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        notify("danger", "Cannot delete. Linked to existing assets.");
    } else {
        $stmt = $conn->prepare("DELETE FROM item_models WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        notify("success", "Deleted successfully.");
    }
    header("Location: stock_specifications.php");
    exit;
}

/* ---------------- UPDATE MODEL ---------------- */
if(isset($_POST['update_model'])){
    $id           = intval($_POST['id']);
    $model_name   = trim(strtoupper($_POST['model_name']));
    $processor    = trim($_POST['processor']);
    $ram          = trim($_POST['ram']);
    $storage_type = $_POST['storage_type'];
    $storage_size = trim($_POST['storage_size']);

    $stmt = $conn->prepare("UPDATE item_models SET model_name=?, processor=?, ram=?, storage_type=?, storage_size=? WHERE id=?");
    $stmt->bind_param("sssssi", $model_name, $processor, $ram, $storage_type, $storage_size, $id);
    
    if($stmt->execute()){
        notify("success", "Updated successfully!");
    }
    header("Location: stock_specifications.php");
    exit;
}

/* ---------------- DATA FETCHING ---------------- */
$items = $conn->query("SELECT id, item_name FROM items_master WHERE status='Active' AND category='Computer' ORDER BY item_name ASC");
$models = $conn->query("SELECT m.*, i.item_name FROM item_models m JOIN items_master i ON i.id = m.item_id ORDER BY i.item_name ASC, m.model_name ASC");

ob_start(); 
?>

<?php
$type = $_SESSION['notify_type'] ?? '';
$msg  = $_SESSION['notify_msg'] ?? '';
unset($_SESSION['notify_type'], $_SESSION['notify_msg']);
if($msg): ?>
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1060;">
    <div class="toast show align-items-center text-bg-<?= $type ?> border-0 shadow-lg" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-medium"><?= htmlspecialchars($msg) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-cpu text-success me-2"></i>Add Specifications</h5>
                <form method="POST" id="specForm">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Select Main Item</label>
                        <select name="item_id" class="form-select rounded-3" required>
                            <option value="">Choose Computer Item...</option>
                            <?php while($row = $items->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['item_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Model Name</label>
                        <input type="text" name="model_name" class="form-control rounded-3" placeholder="e.g. Veriton M200-H510" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Processor</label>
                        <input type="text" name="processor" class="form-control rounded-3" placeholder="e.g. i5-1145G7">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">RAM</label>
                            <input type="text" name="ram" class="form-control rounded-3" placeholder="e.g. 16GB">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Storage Type</label>
                            <select name="storage_type" class="form-select rounded-3">
                                <option value="SSD">SSD</option>
                                <option value="HDD">HDD</option>
                                <option value="NVMe">NVMe</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Storage Size</label>
                        <input type="text" name="storage_size" class="form-control rounded-3" placeholder="e.g. 512GB">
                    </div>
                    <button type="submit" name="add_model" class="btn btn-success w-100 rounded-pill fw-bold">Save Specification</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0">Existing Models</h5>
                <input type="text" id="specSearch" class="form-control form-control-sm w-50 rounded-pill" placeholder="Search models...">
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle border-0" id="specTable">
                    <thead>
                        <tr class="bg-light small text-muted text-uppercase">
                            <th class="ps-3 border-0">Item / Model</th>
                            <th class="border-0">Processor</th>
                            <th class="border-0">RAM</th>
                            <th class="border-0">Storage</th>
                            <th class="text-end pe-3 border-0">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($m = $models->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold"><?= htmlspecialchars($m['model_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($m['item_name']) ?></div>
                            </td>
                            <td class="small"><?= $m['processor'] ?: '-' ?></td>
                            <td class="small"><?= $m['ram'] ?: '-' ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $m['storage_size'] ?> <?= $m['storage_type'] ?></span></td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-sm btn-white border" onclick='openEditSpecModal(<?= json_encode($m) ?>)'>
                                    <i class="bi bi-pencil-square text-primary"></i>
                                </button>
                                <a href="javascript:void(0)" 
                                class="btn btn-sm btn-white border ms-1 delete-spec-btn" 
                                data-id="<?= $m['id'] ?>" 
                                data-name="<?= htmlspecialchars($m['model_name']) ?>">
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
</div>

<?php
// Define the Modal HTML
$modal_html = '
<div class="modal" id="editSpecModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold">Edit Specification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Model Name</label>
                        <input type="text" name="model_name" id="edit_model_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Processor</label>
                        <input type="text" name="processor" id="edit_processor" class="form-control">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold text-muted">RAM</label>
                            <input type="text" name="ram" id="edit_ram" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">Storage Type</label>
                            <select name="storage_type" id="edit_storage_type" class="form-select">
                                <option value="SSD">SSD</option><option value="HDD">HDD</option><option value="NVMe">NVMe</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Storage Size</label>
                        <input type="text" name="storage_size" id="edit_storage_size" class="form-control">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_model" class="btn btn-success rounded-pill px-4 fw-bold">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>';

$main_content = ob_get_clean();

include "masterlayout.php";
?>

<script>
function openEditSpecModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_model_name').value = data.model_name;
    document.getElementById('edit_processor').value = data.processor;
    document.getElementById('edit_ram').value = data.ram;
    document.getElementById('edit_storage_type').value = data.storage_type;
    document.getElementById('edit_storage_size').value = data.storage_size;

    let modalEl = document.getElementById('editSpecModal');

    // FIX: remove any stuck backdrop
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');

    let modal = new bootstrap.Modal(modalEl);
    modal.show();
}

document.getElementById('specSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    document.querySelectorAll('#specTable tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});

// SweetAlert Delete Confirmation
document.querySelectorAll('.delete-spec-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        
        Swal.fire({
            title: 'Delete Specification?',
            text: `Are you sure you want to remove ${name}? This cannot be undone if linked assets exist.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `stock_specifications.php?delete=${id}`;
            }
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
if(isset($_SESSION['swal_msg'])): 
    $type = $_SESSION['swal_type'];
    $msg  = $_SESSION['swal_msg'];
    unset($_SESSION['swal_type'], $_SESSION['swal_msg']);
?>
<script>
    Swal.fire({
        icon: '<?= $type ?>',
        title: '<?= ($type == "success" ? "Success!" : "Notice") ?>',
        text: '<?= htmlspecialchars($msg) ?>',
        timer: 2500,
        showConfirmButton: false
    });
</script>
<?php endif; ?>