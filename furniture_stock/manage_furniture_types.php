<?php
include "../config/db.php";
session_start();

if ($_SESSION['role'] !== 'SuperAdmin') { header("Location: ../index.php"); exit(); }

$message = "";

// --- DELETE LOGIC ---
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $check_stock = $conn->query("SELECT id FROM furniture_stock WHERE furniture_item_id = $delete_id LIMIT 1");
    
    if ($check_stock->num_rows > 0) {
        $message = "usage_error"; 
    } else {
        if ($conn->query("DELETE FROM furniture_items WHERE id = $delete_id")) {
            $message = "deleted";
        }
    }
}

// --- ADD / UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_type'])) {
    // Sanitize and Format to Title Case
    $raw_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $name = ucwords(strtolower(trim($raw_name)));
    
    if (!empty($_POST['edit_id'])) {
        $edit_id = (int)$_POST['edit_id'];
        if ($conn->query("UPDATE furniture_items SET item_name = '$name' WHERE id = $edit_id")) {
            $message = "updated";
        }
    } else {
        $check = $conn->query("SELECT id FROM furniture_items WHERE item_name = '$name'");
        if ($check->num_rows > 0) {
            $message = "exists";
        } else {
            if ($conn->query("INSERT INTO furniture_items (item_name) VALUES ('$name')")) {
                $message = "success";
            }
        }
    }
}

$items = $conn->query("SELECT * FROM furniture_items ORDER BY item_name ASC");
$page_title = "Furniture Registry";
ob_start(); 
?>

<style>
    .edit-mode-active {
        border: 2px solid #0d6efd !important;
        box-shadow: 0 0 15px rgba(13, 110, 253, 0.15) !important;
        transform: scale(1.01);
        transition: all 0.3s ease;
    }
    #item_name {
        text-transform: capitalize;
    }
    .edit-badge { display: none; }
    .edit-mode-active .edit-badge { display: inline-block; }
    
    /* Prevents layout jumping on table changes */
    .table-responsive {
        min-height: 400px; 
    }
</style>

<div class="container-fluid py-4 mt-n3">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4" id="registryCard">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0" id="formTitle">
                            <i class="bi bi-tag me-2 text-success"></i>Define Type
                        </h5>
                        <span class="badge bg-primary edit-badge animate__animated animate__fadeIn">EDIT MODE</span>
                    </div>

                    <form method="POST" id="registryForm">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label class="small fw-bold text-muted text-uppercase">Item Name</label>
                            <input type="text" name="item_name" id="item_name" 
                                   class="form-control rounded-3 border-light-subtle" 
                                   placeholder="e.g. Office Chair" 
                                   style="text-transform: capitalize;" required>
                        </div>
                        
                        <button type="submit" name="save_type" id="submitBtn" class="btn text-white w-100 rounded-pill py-2 fw-bold" style="background-color: #10b981;">
                            <i class="bi bi-check-circle me-2"></i> Save to Registry
                        </button>
                        
                        <button type="button" onclick="resetForm()" class="btn btn-danger w-100 rounded-pill mt-2 border-0 small d-none shadow-sm" id="cancelBtn">
                            Discard Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Furniture Registry</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 small fw-bold text-muted">ITEM NAME</th>
                                <th class="pe-4 py-3 small fw-bold text-muted text-end">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($items->num_rows > 0): while($row = $items->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-light border rounded-pill px-3 shadow-sm" 
                                            onclick='editRegistry(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-pencil-square text-primary"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light border rounded-pill px-3 ms-1 shadow-sm" 
                                            onclick="confirmDelete(<?= $row['id'] ?>, '<?= addslashes($row['item_name']) ?>')">
                                        <i class="bi bi-trash3 text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="2" class="text-center py-4 text-muted">No records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// CLEAN URL: Removes ?delete_id=XX from the browser bar so it doesn't re-trigger on refresh
if (window.history.replaceState) {
    const url = new URL(window.location.href);
    url.searchParams.delete('delete_id');
    window.history.replaceState({path:url.href}, '', url.href);
}

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete "${name}". This cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `?delete_id=${id}`;
        }
    });
}

function editRegistry(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('item_name').value = data.item_name;
    
    const card = document.getElementById('registryCard');
    const submitBtn = document.getElementById('submitBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    
    card.classList.add('edit-mode-active');
    submitBtn.style.backgroundColor = '#0d6efd';
    submitBtn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i> Update Item';
    document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil-square me-2 text-primary"></i>Modify Item';
    cancelBtn.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('registryForm').reset();
    document.getElementById('edit_id').value = "";
    const card = document.getElementById('registryCard');
    const submitBtn = document.getElementById('submitBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    
    card.classList.remove('edit-mode-active');
    submitBtn.style.backgroundColor = '#10b981';
    submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i> Save to Registry';
    document.getElementById('formTitle').innerHTML = '<i class="bi bi-tag me-2 text-success"></i>Define Type';
    cancelBtn.classList.add('d-none');
}

<?php if($message == "success"): ?>
    Swal.fire({ icon: 'success', title: 'Added', text: 'Furniture type registered!', timer: 1500, showConfirmButton: false });
<?php elseif($message == "updated"): ?>
    Swal.fire({ icon: 'success', title: 'Updated', text: 'Changes saved!', timer: 1500, showConfirmButton: false });
<?php elseif($message == "deleted"): ?>
    Swal.fire({ icon: 'success', title: 'Deleted', text: 'Item removed.', timer: 1500, showConfirmButton: false });
<?php elseif($message == "usage_error"): ?>
    Swal.fire({ icon: 'error', title: 'Blocked', text: 'This item is linked to stock records and cannot be deleted.' });
<?php endif; ?>
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>