<?php
include "../config/db.php";
session_start();

// --- 1. SESSION & ROLE CHECK ---
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

// --- 2. DELETE LOGIC ---
if (isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    // Check electrical_central_stock instead
    $check = $conn->query("SELECT total_qty, remaining_qty FROM electrical_central_stock WHERE id = $del_id")->fetch_assoc();
    
    if ($check && $check['remaining_qty'] == $check['total_qty']) {
        if ($conn->query("DELETE FROM electrical_central_stock WHERE id = $del_id")) {
            $_SESSION['swal_msg'] = "Electrical record deleted successfully!";
            $_SESSION['swal_type'] = "success";
        }
    } else {
        $_SESSION['swal_msg'] = "Cannot delete: Stock is already partially dispatched!";
        $_SESSION['swal_type'] = "error";
    }
    header("Location: view_electrical_central_stock.php");
    exit();
}

// --- 3. FETCH ELECTRICAL CENTRAL STOCK ---
$query = "SELECT cs.*, ei.item_name, v.vendor_name 
          FROM electrical_central_stock cs
          LEFT JOIN electrical_items ei ON cs.electrical_item_id = ei.id
          LEFT JOIN vendors v ON cs.vendor_id = v.id
          ORDER BY cs.created_at DESC";
$result = $conn->query($query);

$page_title = "Central Electrical Inventory";
ob_start();
?>

<style>
    .content-wrapper-full { width: 100%; padding: 0; }
    
    .stats-card {
        background: #fff;
        border-radius: 15px;
        border: 1px solid #eef0f2;
        padding: 20px;
        transition: transform 0.2s;
    }

    .custom-table {
        border-collapse: separate;
        border-spacing: 0 8px;
    }
    .custom-table thead th {
        background: #fffcf0; /* Light yellow tint for header */
        border: none;
        color: #6c757d;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 15px;
    }
    .custom-table tbody tr {
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        transition: all 0.2s ease;
    }
    .custom-table tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    .custom-table td {
        padding: 15px;
        border: none;
        vertical-align: middle;
    }
    .custom-table td:first-child { border-radius: 10px 0 0 10px; }
    .custom-table td:last-child { border-radius: 0 10px 10px 0; }

    .qty-badge {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-weight: 700;
    }

    .search-box-wrapper {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 10px 20px;
    }

    /* Electrical specific color override */
    .btn-electrical { background: #ffc107; border: none; color: #000; font-weight: 600; }
    .btn-electrical:hover { background: #e0a800; color: #000; }
    .text-electrical { color: #ff9f1c !important; }
</style>

<div class="container-fluid p-0 mt-3 content-wrapper-full">
    
    <div class="d-flex justify-content-between align-items-end mb-4 px-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Central Electrical Warehouse</h3>
            <p class="text-muted small mb-0">Manage bulk electrical procurement and monitor stock availability.</p>
        </div>
        <a href="add_electrical_central_stock.php" class="btn btn-electrical px-4 py-2" style="border-radius: 10px;">
            <i class="bi bi-plus-lg me-2"></i>New Bulk Entry
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4" style="border-radius:15px;">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="search-box-wrapper d-flex align-items-center">
                        <i class="bi bi-search text-muted me-3"></i>
                        <input type="text" id="tableSearch" class="form-control border-0 bg-transparent shadow-none" placeholder="Search Electrical Items, Vendors or Bill Numbers...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table custom-table" id="centralStockTable">
            <thead>
                <tr>
                    <th>Electrical Item Description</th>
                    <th>Supplier / Bill</th>
                    <th class="text-center">Remaining / Total</th>
                    <th>Unit Price</th>
                    <th>Date Added</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): 
                    $percent = ($row['remaining_qty'] / $row['total_qty']) * 100;
                    $status_color = ($percent > 50) ? 'success' : (($percent > 10) ? 'warning' : 'danger');
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="qty-badge bg-<?= $status_color ?> bg-opacity-10 text-<?= $status_color ?> me-3">
                                <i class="bi bi-cpu"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></div>
                                <small class="text-muted">ID: #EC-<?= $row['id'] ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-medium"><?= htmlspecialchars($row['vendor_name']) ?></div>
                        <small class="text-warning fw-bold" style="filter: brightness(0.8);">Bill: <?= htmlspecialchars($row['bill_no']) ?></small>
                    </td>
                    <td class="text-center">
                        <span class="fs-5 fw-bold text-<?= $status_color ?>"><?= $row['remaining_qty'] ?></span>
                        <span class="text-muted">/ <?= $row['total_qty'] ?></span>
                        <div class="progress mt-1" style="height: 4px; width: 80px; margin: 0 auto;">
                            <div class="progress-bar bg-<?= $status_color ?>" style="width: <?= $percent ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-bold text-dark">₹<?= number_format($row['unit_price'], 2) ?></div>
                        <small class="text-muted">Total: ₹<?= number_format($row['unit_price'] * $row['total_qty'], 2) ?></small>
                    </td>
                    <td>
                        <div class="small fw-medium"><?= date('d M, Y', strtotime($row['bill_date'])) ?></div>
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm rounded-3 shadow-sm border" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg" style="border-radius: 12px;">
                                <li>
                                    <form method="POST" action="add_electrical_central_stock.php">
                                        <input type="hidden" name="trigger_edit" value="<?= $row['id'] ?>">
                                        <button type="submit" class="dropdown-item py-2 text-primary">
                                            <i class="bi bi-pencil-square me-2"></i>Edit Entry
                                        </button>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item py-2 text-danger delete-btn" data-id="<?= $row['id'] ?>" data-dispatched="<?= ($row['remaining_qty'] != $row['total_qty']) ? '1' : '0' ?>">
                                        <i class="bi bi-trash3 me-2"></i>Remove
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="deleteForm" method="POST">
    <input type="hidden" name="delete_id" id="delete_id">
</form>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // 1. Search Filtering
    $("#tableSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#centralStockTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // 2. Delete Confirmation
    $('.delete-btn').on('click', function() {
        const id = $(this).data('id');
        const isDispatched = $(this).data('dispatched');

        if(isDispatched == '1') {
            Swal.fire('Action Denied', 'Cannot delete stock that has already been dispatched to units.', 'error');
            return;
        }

        Swal.fire({
            title: 'Delete Electrical Entry?',
            text: "This action cannot be undone and will remove the bulk stock record.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#delete_id').val(id);
                $('#deleteForm').submit();
            }
        });
    });

    // 3. Alerts
    <?php if (isset($_SESSION['swal_msg'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['swal_type'] ?>',
            title: '<?= $_SESSION['swal_msg'] ?>',
            timer: 2000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['swal_msg']); unset($_SESSION['swal_type']); ?>
    <?php endif; ?>
});
</script>

<?php 
$content = ob_get_clean(); 
include "electricalslayout.php"; 
?>