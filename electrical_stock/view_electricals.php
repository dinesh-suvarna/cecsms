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

/* ================= DELETE ================= */
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    // Check if assets exist
    $check_assets = $conn->query("
        SELECT id FROM electrical_assets 
        WHERE stock_id = $delete_id LIMIT 1
    ");

    if ($check_assets->num_rows == 0) {
        $conn->query("DELETE FROM electrical_stock WHERE id = $delete_id");

        $_SESSION['swal_msg'] = "Stock deleted successfully.";
        $_SESSION['swal_type'] = "success";
    } else {
        $_SESSION['swal_msg'] = "Cannot delete: Asset IDs already generated.";
        $_SESSION['swal_type'] = "error";
    }

    header("Location: view_electricals.php");
    exit();
}

/* ================= FETCH DATA ================= */
$sql = "SELECT 
            SUM(s.total_qty) as total_qty,
            MAX(s.id) as id,
            i.item_name,
            v.vendor_name,
            u.unit_name,
            u.unit_code,
            GROUP_CONCAT(DISTINCT s.bill_no SEPARATOR ', ') as combined_bills,
            MAX(s.bill_date) as latest_bill_date
        FROM electrical_stock s
        JOIN electrical_items i ON s.electrical_item_id = i.id
        JOIN vendors v ON s.vendor_id = v.id
        JOIN units u ON s.unit_id = u.id";

if ($user_role !== 'SuperAdmin') {
    $sql .= " WHERE u.division_id = '$user_division'";
}

$sql .= " GROUP BY i.item_name, u.id
          ORDER BY u.unit_code ASC, i.item_name ASC";

$result = $conn->query($sql);

/* ================= GROUPING ================= */
$grouped_inventory = [];
while ($row = $result->fetch_assoc()) {
    $display_label = strtoupper($row['unit_code']) . " - " . $row['unit_name'];
    $grouped_inventory[$display_label][] = $row;
}

$page_title = "Electricals Inventory";
ob_start();
?>

<div class="container-fluid py-4 px-4 mt-n3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Electricals Inventory</h4>
            <p class="text-muted small mb-0">Summarized stock distribution per unit</p>
        </div>
        <a href="add_electricals.php" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
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
            ?>
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold py-3 bg-white" 
                                type="button" data-bs-toggle="collapse" 
                                data-bs-target="#<?= $collapse_id ?>">
                            
                            <i class="bi bi-building text-primary me-2"></i>
                            <?= htmlspecialchars($display_label) ?>

                            <span class="badge bg-light text-muted border rounded-pill ms-3 small">
                                <?= count($items) ?> Items
                            </span>
                        </button>
                    </h2>

                    <div id="<?= $collapse_id ?>" class="accordion-collapse collapse">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr class="small text-uppercase fw-bold text-muted">
                                            <th class="ps-4">Sl.No</th>
                                            <th>Item Name</th>
                                            <th class="text-center">Total Qty</th>
                                            <th>Vendor</th>
                                            <th>Bill Info</th>
                                            <th class="text-end pe-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $index => $row): ?>
                                            <tr>
                                                <td class="ps-4 small"><?= $index + 1 ?></td>

                                                <td class="fw-bold text-dark">
                                                    <?= htmlspecialchars($row['item_name']) ?>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold">
                                                        <?= $row['total_qty'] ?>
                                                    </span>
                                                </td>

                                                <td class="small">
                                                    <?= htmlspecialchars($row['vendor_name']) ?>
                                                </td>

                                                <td>
                                                    <div class="small fw-bold">
                                                        Bills: <?= htmlspecialchars($row['combined_bills']) ?>
                                                    </div>
                                                    <div class="x-small text-muted">
                                                        Last: <?= date('d M, Y', strtotime($row['latest_bill_date'])) ?>
                                                    </div>
                                                </td>

                                                <td class="text-end pe-4">
                                                    <div class="btn-group">
                                                        <button onclick="editStock(<?= htmlspecialchars(json_encode(['id'=>$row['id']])) ?>)" 
                                                                class="btn btn-sm btn-light border rounded-circle me-1">
                                                            <i class="bi bi-pencil-square text-primary"></i>
                                                        </button>

                                                        <button onclick="deleteStock(<?= $row['id'] ?>)" 
                                                                class="btn btn-sm btn-light border rounded-circle">
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
.accordion-button:not(.collapsed){background:#f8f9fa;color:#0d6efd;}
.x-small{font-size:0.75rem;}
.bg-primary-subtle{background:#eef2ff!important;}
.btn-group .btn{
    width:32px;height:32px;
    display:flex;align-items:center;justify-content:center;
}
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// 1. DISPLAY AND CLEAR SESSION MESSAGES
<?php if(isset($_SESSION['swal_msg'])): ?>
    Swal.fire({
        icon: '<?= $_SESSION['swal_type'] ?>',
        title: '<?= $_SESSION['swal_msg'] ?>',
        timer: 3000,
        showConfirmButton: true
    });
    <?php 
        // THIS IS THE FIX: Clear the message after it is rendered
        unset($_SESSION['swal_msg']); 
        unset($_SESSION['swal_type']); 
    ?>
<?php endif; ?>

// 2. EDIT FUNCTION
function editStock(data){
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'add_electricals.php';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'trigger_edit';
    input.value = data.id;

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// 3. DELETE FUNCTION
function deleteStock(id){
    Swal.fire({
        title: 'Delete this entry?',
        text: 'This action cannot be undone if no assets are linked.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if(result.isConfirmed){
            window.location.href = `view_electricals.php?delete_id=${id}`;
        }
    });
}
</script>

<?php 
$content = ob_get_clean(); 
include "electricalslayout.php"; 
?>