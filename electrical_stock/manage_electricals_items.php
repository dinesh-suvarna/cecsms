<?php
include "../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

$message = "";

// --- DELETE LOGIC ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $check_stock = $conn->query("SELECT id FROM electrical_stock WHERE electrical_item_id = $delete_id LIMIT 1");
    
    if ($check_stock->num_rows > 0) {
        $message = "usage_error"; 
    } else {
        if ($conn->query("DELETE FROM electrical_items WHERE id = $delete_id")) {
            $message = "deleted";
        }
    }
}

// --- ADD / UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_type'])) {
    $raw_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $name = ucwords(strtolower(trim($raw_name)));
    $item_code = mysqli_real_escape_string($conn, strtoupper(trim($_POST['item_code'])));
    
    if (!empty($_POST['edit_id'])) {
        $edit_id = (int)$_POST['edit_id'];
        if ($conn->query("UPDATE electrical_items SET item_name = '$name', item_code = '$item_code' WHERE id = $edit_id")) {
            $message = "updated";
        }
    } else {
        $check = $conn->query("SELECT id FROM electrical_items WHERE item_name = '$name' OR (item_code = '$item_code' AND item_code != '')");
        if ($check->num_rows > 0) {
            $message = "exists";
        } else {
            if ($conn->query("INSERT INTO electrical_items (item_name, item_code) VALUES ('$name', '$item_code')")) {
                $message = "success";
            }
        }
    }
}

$items = $conn->query("SELECT * FROM electrical_items ORDER BY item_name ASC");
$page_title = "Electrical Item Registry";
ob_start(); 
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<style>
    .edit-mode-active {
        border: 2px solid #0d6efd !important;
        box-shadow: 0 0 15px rgba(13, 110, 253, 0.15) !important;
        transform: scale(1.01);
        transition: all 0.3s ease;
    }
    #item_name { text-transform: capitalize; }
    #item_code { text-transform: uppercase; }
    .edit-badge { display: none; }
    .edit-mode-active .edit-badge { display: inline-block; }

    /* DataTables Control Styling */
    .dt-controls-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        background: #fff;
        gap: 20px;
    }

    .dataTables_length label {
        display: inline-flex !important;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 0;
        font-size: 0.85rem;
    }

    .dataTables_length select {
        border: 1px solid #e2e8f0 !important;
        background-color: #f8fafc !important;
        border-radius: 8px !important;
        padding: 0.4rem 1.5rem 0.4rem 0.75rem !important;
        cursor: pointer;
        min-width: 70px;
        color: #1e293b;
        font-weight: 700;
    }

    .dataTables_filter input {
        border: 1px solid #f1f5f9;
        background-color: #f8fafc;
        border-radius: 12px;
        padding: 0.6rem 1rem 0.6rem 2.5rem !important;
        width: 280px !important;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .dataTables_filter input:focus {
        background-color: #fff;
        border-color: #10b981;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.05);
        outline: none;
    }

    .dataTables_filter { position: relative; }
    .dataTables_filter::before {
        content: "\f52a";
        font-family: "bootstrap-icons";
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        z-index: 10;
    }

    .dataTables_filter label { color: transparent; font-size: 0; }

    #itemsTable { width: 100% !important; margin: 0 !important; }
    .table-responsive { max-height: 500px; overflow-y: auto; }
    thead th {
        position: sticky; top: 0;
        background-color: #f8f9fa !important;
        z-index: 10;
        box-shadow: inset 0 -1px 0 #dee2e6;
    }
</style>

<div class="container-fluid py-4 mt-n3">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4" id="registryCard">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0" id="formTitle">
                            <i class="bi bi-plus-circle me-2 text-success"></i>Add Electrical Item
                        </h5>
                        <span class="badge bg-primary edit-badge">EDIT MODE</span>
                    </div>

                    <form method="POST" id="registryForm">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label class="small fw-bold text-muted text-uppercase">Item Name</label>
                            <input type="text" name="item_name" id="item_name" class="form-control rounded-3 border-light-subtle" placeholder="e.g. Ceiling Fan" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted text-uppercase">Item Code</label>
                            <input type="text" name="item_code" id="item_code" class="form-control rounded-3 border-light-subtle" placeholder="e.g. FAN-01" required>
                        </div>
                        <button type="submit" name="save_type" id="submitBtn" class="btn text-white w-100 rounded-pill py-2 fw-bold" style="background-color: #10b981;">
                            <i class="bi bi-check-circle me-2"></i> Save to Registry
                        </button>
                        <button type="button" onclick="resetForm()" class="btn btn-danger w-100 rounded-pill mt-2 d-none" id="cancelBtn">
                            Discard Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-800 text-dark mb-0">Electrical Item Registry</h5>
                    <p class="text-muted small mb-0">Manage and verify electrical classifications</p>
                </div>
                
                <div class="table-responsive">
                    <table id="itemsTable" class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 small fw-bold text-muted" style="width: 80px;">SL.NO</th>
                                <th class="py-3 small fw-bold text-muted">ITEM NAME</th>
                                <th class="py-3 small fw-bold text-muted">ITEM CODE</th>
                                <th class="pe-4 py-3 small fw-bold text-muted text-end">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if($items->num_rows > 0): 
                                $sl = 1;
                                while($row = $items->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= $sl++ ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></td>
                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['item_code']) ?></span></td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-light border rounded-pill px-3 shadow-sm" onclick='editRegistry(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-pencil-square text-primary"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light border rounded-pill px-3 ms-1 shadow-sm" onclick="confirmDelete(<?= $row['id'] ?>, '<?= addslashes($row['item_name']) ?>')">
                                        <i class="bi bi-trash3 text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {
    $('#itemsTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50],
        dom: "<'dt-controls-wrapper'lf>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row p-4'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>",
        language: {
            search: "_INPUT_", 
            searchPlaceholder: "Search electrical items...",
            lengthMenu: "Show _MENU_",
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>'
            }
        }
    });
});

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete "${name}".`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `?delete_id=${id}`;
        }
    });
}

function editRegistry(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('item_name').value = data.item_name;
    document.getElementById('item_code').value = data.item_code;
    
    document.getElementById('registryCard').classList.add('edit-mode-active');
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.style.backgroundColor = '#0d6efd';
    submitBtn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i> Update Item';
    document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil-square me-2 text-primary"></i>Edit Electrical Item';
    document.getElementById('cancelBtn').classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('registryForm').reset();
    document.getElementById('edit_id').value = "";
    document.getElementById('registryCard').classList.remove('edit-mode-active');
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.style.backgroundColor = '#10b981';
    submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i> Save to Registry';
    document.getElementById('formTitle').innerHTML = '<i class="bi bi-plus-circle me-2 text-success"></i> Add Electrical Item';
    document.getElementById('cancelBtn').classList.add('d-none');
}

<?php if($message == "success"): ?>
    Swal.fire({ icon: 'success', title: 'Added', text: 'Electrical item registered!', timer: 1500, showConfirmButton: false });
<?php elseif($message == "updated"): ?>
    Swal.fire({ icon: 'success', title: 'Updated', text: 'Changes saved!', timer: 1500, showConfirmButton: false });
<?php elseif($message == "deleted"): ?>
    Swal.fire({ icon: 'success', title: 'Deleted', text: 'Item removed.', timer: 1500, showConfirmButton: false });
<?php elseif($message == "usage_error"): ?>
    Swal.fire({ icon: 'error', title: 'Blocked', text: 'Item is linked to stock records.' });
<?php elseif($message == "exists"): ?>
    Swal.fire({ icon: 'warning', title: 'Duplicate', text: 'Item Name or Code already exists.' });
<?php endif; ?>
</script>

<?php 
$content = ob_get_clean(); 
include "electricalslayout.php"; 
?>