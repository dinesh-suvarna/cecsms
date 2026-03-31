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

// Query logic based on role
$sql = "SELECT s.*, i.item_name, v.vendor_name, u.unit_name 
        FROM furniture_stock s
        JOIN furniture_items i ON s.furniture_item_id = i.id
        JOIN vendors v ON s.vendor_id = v.id
        JOIN units u ON s.unit_id = u.id";

if ($user_role !== 'SuperAdmin') {
    $sql .= " WHERE u.division_id = '$user_division'";
}
$sql .= " ORDER BY s.created_at DESC";

$inventory = $conn->query($sql);

$page_title = "Furniture Inventory";
ob_start();
?>

<div class="container-fluid py-4 px-4 mt-n3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Furniture Inventory</h4>
            <p class="text-muted small mb-0">Manage and track all furniture stock arrivals</p>
        </div>
        <a href="add_furniture_stock.php" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-plus-lg me-2"></i> Add New Stock
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-uppercase small fw-bold text-muted">Item & Bill Details</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted text-center">Quantity</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted">Unit Price</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted">Receiving Unit</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($inventory->num_rows > 0): ?>
                            <?php while($row = $inventory->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></div>
                                        <div class="small text-muted">
                                            <span class="badge bg-light text-dark border fw-normal"><?= htmlspecialchars($row['bill_no']) ?></span>
                                            <span class="ms-2"><?= date('d M, Y', strtotime($row['bill_date'])) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-bold fs-5"><?= $row['available_qty'] ?></div>
                                        <div class="x-small text-muted text-uppercase">of <?= $row['total_qty'] ?> Total</div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-success">₹<?= number_format($row['unit_price'], 2) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($row['vendor_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill">
                                            <i class="bi bi-geo-alt-fill me-1"></i> <?= htmlspecialchars($row['unit_name']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button onclick="editStock(<?= htmlspecialchars(json_encode($row)) ?>)" 
                                                class="btn btn-outline-primary btn-sm rounded-pill px-3 me-1">
                                            <i class="bi bi-pencil-square me-1"></i> Edit
                                        </button>
                                        <button onclick="deleteStock(<?= $row['id'] ?>)" 
                                                class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                            <i class="bi bi-trash3 me-1"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                                    No furniture stock records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editStock(data) {
    // Redirect to the entry form page with the ID to trigger edit mode
    // We will update add_furniture_stock.php to handle this
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'add_furniture_stock.php';

    // Add hidden input for the Edit ID
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
            window.location.href = `add_furniture_stock.php?delete_id=${id}`;
        }
    });
}
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>