<?php
include "../config/db.php";
session_start();

// Security check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

// HANDLE DELETION logic remains same...
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $check_assets = $conn->query("SELECT id FROM furniture_assets WHERE stock_id = $delete_id LIMIT 1");
    if ($check_assets->num_rows == 0) {
        $conn->query("DELETE FROM furniture_stock WHERE id = $delete_id");
        $_SESSION['swal_msg'] = "Stock record deleted successfully.";
        $_SESSION['swal_type'] = "success";
    } else {
        $_SESSION['swal_msg'] = "Cannot delete: Asset IDs already generated.";
        $_SESSION['swal_type'] = "error";
    }
    header("Location: view_furniture.php");
    exit();
}

// 1. Fetch data including Unit Code (assuming column name is unit_code)
$sql = "SELECT s.*, i.item_name, v.vendor_name, u.unit_name, u.unit_code 
        FROM furniture_stock s
        JOIN furniture_items i ON s.furniture_item_id = i.id
        JOIN vendors v ON s.vendor_id = v.id
        JOIN units u ON s.unit_id = u.id";

if ($user_role !== 'SuperAdmin') {
    $sql .= " WHERE u.division_id = '$user_division'";
}
$sql .= " ORDER BY u.unit_code ASC, s.created_at DESC";

$result = $conn->query($sql);

$grouped_inventory = [];
while ($row = $result->fetch_assoc()) {
    // Create the "Code - Name" label
    $display_label = strtoupper($row['unit_code']) . " - " . $row['unit_name'];
    $grouped_inventory[$display_label][] = $row;
}

$page_title = "Furniture Inventory";
ob_start();
?>

<div class="container-fluid py-4 px-4 mt-n3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Furniture Inventory</h4>
            <p class="text-muted small mb-0">Unit-wise stock distribution</p>
        </div>
        <a href="add_furniture.php" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-plus-lg me-2"></i> Add New Stock
        </a>
    </div>

    <?php if (empty($grouped_inventory)): ?>
        <div class="card border-0 shadow-sm rounded-4 py-5 text-center">
            <i class="bi bi-inbox fs-1 opacity-25"></i>
            <p class="text-muted mt-3">No inventory records found.</p>
        </div>
    <?php else: ?>
        <div class="accordion border-0 shadow-sm rounded-4 overflow-hidden" id="unitAccordion">
            <?php 
            $count = 0;
            foreach ($grouped_inventory as $display_label => $items): 
                $count++;
                $collapse_id = "collapse" . $count;
                $heading_id = "heading" . $count;
            ?>
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header" id="<?= $heading_id ?>">
                        <button class="accordion-button collapsed fw-bold py-3 bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>">
                            <i class="bi bi-building text-primary me-2"></i>
                            <?= htmlspecialchars($display_label) ?> 
                            <span class="badge bg-light text-muted border rounded-pill ms-3 fw-normal small">
                                <?= count($items) ?> Items
                            </span>
                        </button>
                    </h2>
                    <div id="<?= $collapse_id ?>" class="accordion-collapse collapse" data-bs-parent="#unitAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr class="small text-uppercase fw-bold text-muted">
                                            <th class="ps-4" style="width: 80px;">Sl.No</th>
                                            <th>Item Name</th>
                                            <th class="text-center">Quantity</th>
                                            <th>Vendor</th>
                                            <th>Bill Details</th>
                                            <th class="text-end pe-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $index => $row): ?>
                                            <tr>
                                                <td class="ps-4 text-muted small"><?= $index + 1 ?></td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold text-dark"><?= $row['total_qty'] ?></span>
                                                </td>
                                                <td class="fw-bold text-dark"><?= htmlspecialchars($row['vendor_name']) ?></td>
                                                <td>
                                                    <div class="small fw-bold">#<?= htmlspecialchars($row['bill_no']) ?></div>
                                                    <div class="x-small text-muted"><?= date('d M, Y', strtotime($row['bill_date'])) ?></div>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="btn-group">
                                                        <button onclick="editStock(<?= htmlspecialchars(json_encode($row)) ?>)" class="btn btn-sm btn-light border rounded-circle me-1">
                                                            <i class="bi bi-pencil-square text-primary"></i>
                                                        </button>
                                                        <button onclick="deleteStock(<?= $row['id'] ?>)" class="btn btn-sm btn-light border rounded-circle">
                                                            <i class="bi bi-trash3 text-danger"></i>
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
        </div>
    <?php endif; ?>
</div>

<style>
    /* Custom Styling to match your request */
    .accordion-button:not(.collapsed) { 
        background-color: #f8f9fa; 
        color: #0d6efd; 
        box-shadow: inset 0 -1px 0 rgba(0,0,0,.125); 
    }
    .accordion-button:focus { box-shadow: none; border-color: rgba(0,0,0,.125); }
    .x-small { font-size: 0.75rem; }
    .btn-group .btn { width: 35px; height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; }
</style>

<script>
function editStock(data) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'add_furniture.php';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'trigger_edit';
    input.value = data.id;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function deleteStock(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `view_furniture.php?delete_id=${id}`;
        }
    });
}
</script>

<?php if(isset($_SESSION['swal_msg'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['swal_type'] ?>',
        title: '<?= $_SESSION['swal_msg'] ?>',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
</script>
<?php unset($_SESSION['swal_msg'], $_SESSION['swal_type']); endif; ?>


<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>