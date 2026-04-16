<?php
include "../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

// HANDLE DELETION & SWAL LOGIC (Kept exactly as per your code)
$display_swal = false;
if (isset($_SESSION['swal_msg'])) {
    $display_swal = true;
    $swal_text = $_SESSION['swal_msg'];
    $swal_type = $_SESSION['swal_type'] ?? 'success';
    unset($_SESSION['swal_msg'], $_SESSION['swal_type']);
}

if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $check_assets = $conn->query("SELECT id FROM furniture_assets WHERE stock_id = $delete_id LIMIT 1");
    if ($check_assets->num_rows == 0) {
        if ($conn->query("DELETE FROM furniture_stock WHERE id = $delete_id")) {
            $_SESSION['swal_msg'] = "Stock record deleted successfully.";
            $_SESSION['swal_type'] = "success";
        } else {
            $_SESSION['swal_msg'] = "Database error: Could not delete record.";
            $_SESSION['swal_type'] = "error";
        }
    } else {
        $_SESSION['swal_msg'] = "Cannot delete: Asset IDs already generated for this batch.";
        $_SESSION['swal_type'] = "error";
    }
    header("Location: view_furniture.php");
    exit();
}

// UPDATED QUERY: Added division_name to handle grouping
$sql = "SELECT 
            SUM(s.total_qty) as total_qty, 
            MAX(s.id) as id, 
            i.item_name, 
            v.vendor_name, 
            u.unit_name, 
            u.unit_code,
            d.division_name,
            GROUP_CONCAT(DISTINCT s.bill_no SEPARATOR ', ') as combined_bills,
            MAX(s.bill_date) as latest_bill_date
        FROM furniture_stock s
        JOIN furniture_items i ON s.furniture_item_id = i.id
        JOIN vendors v ON s.vendor_id = v.id
        JOIN units u ON s.unit_id = u.id
        JOIN divisions d ON u.division_id = d.id";

if ($user_role !== 'SuperAdmin') {
    $sql .= " WHERE u.division_id = '$user_division'";
}

$sql .= " GROUP BY i.item_name, u.id, v.vendor_name 
          ORDER BY d.division_name ASC, u.unit_code ASC, i.item_name ASC";

$result = $conn->query($sql);

// MULTI-LEVEL GROUPING LOGIC
$grouped_inventory = [];
while ($row = $result->fetch_assoc()) {
    $div_name = $row['division_name'];
    $unit_label = strtoupper($row['unit_code']) . " - " . $row['unit_name'];
    $grouped_inventory[$div_name][$unit_label][] = $row;
}

$page_title = "Furniture Inventory";
ob_start();
?>

<div class="container-fluid py-4 px-4 mt-n3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Furniture Inventory</h4>
            <p class="text-muted small mb-0">Stock distribution by Departments & Facilities</p>
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
        <div class="accordion border-0" id="divisionAccordion">
            <?php 
            $div_count = 0;
            foreach ($grouped_inventory as $division_name => $units): 
                $div_count++;
                $div_collapse_id = "div_collapse_" . $div_count;
            ?>
                <div class="accordion-item border shadow-sm rounded-4 overflow-hidden mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-success text-white fw-bold py-3" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $div_collapse_id ?>">
                            <i class="bi bi-geo-alt-fill me-2 text-warning"></i>
                            <?= htmlspecialchars(strtoupper($division_name)) ?>
                        </button>
                    </h2>
                    <div id="<?= $div_collapse_id ?>" class="accordion-collapse collapse" data-bs-parent="#divisionAccordion">
                        <div class="accordion-body p-3 bg-light">
                            
                            <div class="accordion accordion-flush rounded-3 border overflow-hidden" id="unitAccordion_<?= $div_count ?>">
                                <?php 
                                $unit_count = 0;
                                foreach ($units as $unit_label => $items): 
                                    $unit_count++;
                                    $unit_collapse_id = "unit_collapse_" . $div_count . "_" . $unit_count;
                                ?>
                                    <div class="accordion-item border-bottom">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed fw-bold py-3 bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $unit_collapse_id ?>">
                                                <i class="bi bi-building text-primary me-2"></i>
                                                <?= htmlspecialchars($unit_label) ?>
                                                <span class="badge bg-primary-subtle text-primary border rounded-pill ms-3 fw-normal small">
                                                    <?= count($items) ?> Items
                                                </span>
                                            </button>
                                        </h2>
                                        <div id="<?= $unit_collapse_id ?>" class="accordion-collapse collapse" data-bs-parent="#unitAccordion_<?= $div_count ?>">
                                            <div class="accordion-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-hover align-middle mb-0 bg-white">
                                                        <thead class="bg-light">
                                                            <tr class="small text-uppercase fw-bold text-muted">
                                                                <th class="ps-4" style="width: 60px;">#</th>
                                                                <th>Item Name</th>
                                                                <th class="text-center">Stock Qty</th>
                                                                <th>Vendor</th>
                                                                <th>Bill Refs</th>
                                                                <th class="text-end pe-4">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($items as $idx => $row): ?>
                                                                <tr>
                                                                    <td class="ps-4 text-muted small"><?= $idx + 1 ?></td>
                                                                    <td><div class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></div></td>
                                                                    <td class="text-center">
                                                                        <span class="fw-bold text-dark"><?= $row['total_qty'] ?></span>
                                                                    </td>
                                                                    <td class="text-dark small"><?= htmlspecialchars($row['vendor_name']) ?></td>
                                                                    <td>
                                                                        <div class="small fw-bold text-truncate" style="max-width: 200px;"><?= htmlspecialchars($row['combined_bills']) ?></div>
                                                                        <div class="x-small text-muted">Latest: <?= date('d M, Y', strtotime($row['latest_bill_date'])) ?></div>
                                                                    </td>
                                                                    <td class="text-end pe-4">
                                                                        <div class="btn-group">
                                                                            <button onclick="editStock(<?= $row['id'] ?>)" class="btn btn-sm btn-outline-primary border-0 rounded-0" title="Edit">
                                                                                <i class="bi bi-pencil-square"></i>
                                                                            </button>
                                                                            <button onclick="deleteStock(<?= $row['id'] ?>)" class="btn btn-sm btn-outline-danger border-0 rounded-0" title="Delete">
                                                                                <i class="bi bi-trash3"></i>
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
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Division (Main) Accordion Styles */
    #divisionAccordion .accordion-button:not(.collapsed) { background-color: #212529; color: #fff; box-shadow: none; }
    #divisionAccordion .accordion-button::after { filter: brightness(0) invert(1); } /* Make arrow white */
    
    /* Unit (Nested) Accordion Styles */
    .accordion-flush .accordion-item:last-child { border-bottom: 0; }
    .bg-primary-subtle { background-color: #eef2ff !important; }
    .x-small { font-size: 0.7rem; }
    
    /* Hover effects for a premium feel */
    .table-hover tbody tr:hover { background-color: #f8faff !important; }
    .btn-group .btn:hover { background-color: #f0f0f0; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if ($display_swal): ?>
    Swal.fire({
        icon: '<?= $swal_type ?>',
        title: '<?= $swal_type == "success" ? "Done!" : "Hold on..." ?>',
        text: '<?= $swal_text ?>',
        timer: <?= $swal_type == "success" ? "2500" : "5000" ?>,
        showConfirmButton: <?= $swal_type == "success" ? "false" : "true" ?>
    });
<?php endif; ?>

function editStock(id) {
    const form = document.createElement('form');
    form.method = 'POST'; form.action = 'add_furniture.php';
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'trigger_edit'; input.value = id;
    form.appendChild(input); document.body.appendChild(form); form.submit();
}

function deleteStock(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This batch will be removed permanently!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e33e4d',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `view_furniture.php?delete_id=${id}`;
        }
    });
}
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>