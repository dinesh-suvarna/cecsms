<?php
include "../config/db.php";
session_start();

// --- 1. SESSION & ROLE CHECK ---
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

// --- 2. HANDLE DISPATCH LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_dispatch'])) {
    $central_id = (int)$_POST['central_stock_id'];
    $unit_id = (int)$_POST['unit_id'];
    $dispatch_qty = (int)$_POST['dispatch_qty'];

    $check_res = $conn->query("SELECT * FROM furniture_central_stock WHERE id = $central_id");
    $central_item = $check_res->fetch_assoc();

    if ($central_item && $dispatch_qty > 0 && $dispatch_qty <= $central_item['remaining_qty']) {
        $conn->begin_transaction();
        try {
            // Deduct from Central
            $new_remaining = $central_item['remaining_qty'] - $dispatch_qty;
            $conn->query("UPDATE furniture_central_stock SET remaining_qty = $new_remaining WHERE id = $central_id");

            // Add to Unit Stock
            $item_id = $central_item['furniture_item_id'];
            $bill_no = $central_item['bill_no'];
            $bill_date = $central_item['bill_date'];
            $vendor_id = $central_item['vendor_id'];
            $price = $central_item['unit_price'];

            $sql_insert = "INSERT INTO furniture_stock (furniture_item_id, total_qty, available_qty, bill_no, bill_date, vendor_id, unit_id, unit_price) 
                           VALUES ('$item_id', '$dispatch_qty', '$dispatch_qty', '$bill_no', '$bill_date', '$vendor_id', '$unit_id', '$price')";
            $conn->query($sql_insert);

            $conn->commit();
            $_SESSION['swal_msg'] = "Dispatch successful!";
            $_SESSION['swal_type'] = "success";
            header("Location: view_central_stock.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
}

// --- 3. FETCH DATA ---
$available_stock = $conn->query("SELECT cs.*, fi.item_name FROM furniture_central_stock cs 
                                 JOIN furniture_items fi ON cs.furniture_item_id = fi.id 
                                 WHERE cs.remaining_qty > 0");

$divisions = $conn->query("SELECT * FROM divisions ORDER BY division_name ASC");

// Fetch all units (Division filtering happens via JavaScript)
if ($user_role === 'SuperAdmin') {
    $units_res = $conn->query("SELECT id, unit_name, unit_code, division_id FROM units ORDER BY unit_name ASC");
} else {
    $units_res = $conn->query("SELECT id, unit_name, unit_code, division_id FROM units WHERE division_id = '$user_division' ORDER BY unit_name ASC");
}

ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    .content-wrapper-full { width: 100%; padding: 0; }
    .main-card { border-radius: 15px; border: 1px solid #eef0f2; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.02) !important; margin-bottom: 2rem; }
    
    .form-section-header { display: flex; align-items: center; margin: 25px 0 15px 0; }
    .form-section-header .line { flex: 1; height: 1px; background: #f1f3f5; }
    .form-section-header .text { padding: 0 15px; font-weight: 800; font-size: 0.85rem; color: #4361ee; text-transform: uppercase; letter-spacing: 1px; }

    .field-wrapper { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 10px; padding: 8px 12px; transition: all 0.2s ease; }
    .field-wrapper:focus-within { border-color: #4361ee; background: #fff; box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.05); }
    .field-wrapper label { display: block; font-size: 0.65rem; font-weight: 700; color: #6c757d; text-transform: uppercase; margin-bottom: 2px; }
    .field-wrapper .form-control, .field-wrapper .form-select { border: none !important; padding: 0 !important; background: transparent !important; font-weight: 600; font-size: 0.95rem; }
    
    .stock-counter { background: #4361ee; color: #fff; padding: 2px 8px; border-radius: 5px; font-size: 0.75rem; font-weight: 700; }
</style>

<div class="container-fluid p-0 mt-3 content-wrapper-full">
    <div class="card main-card">
        <div class="card-body p-4 p-md-5">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1"><i class="bi bi-truck text-primary me-2"></i>Dispatch Furniture</h4>
                    <p class="text-muted small mb-0">Transfer assets from Central Warehouse to a facility Unit.</p>
                </div>
            </div>

            <form method="POST" id="dispatchForm">
                
                <div class="form-section-header">
                    <span class="text">Source Inventory</span>
                    <div class="line"></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="field-wrapper">
                            <label>Select Item from Central Stock</label>
                            <select name="central_stock_id" id="central_stock_id" class="form-select searchable-select" required>
                                <option value="">-- Search Item / Bill Number --</option>
                                <?php while($s = $available_stock->fetch_assoc()): ?>
                                    <option value="<?= $s['id'] ?>" data-max="<?= $s['remaining_qty'] ?>">
                                        <?= htmlspecialchars($s['item_name']) ?> (Bill: <?= $s['bill_no'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="field-wrapper">
                            <label>Quantity to Dispatch</label>
                            <div class="d-flex align-items-center">
                                <input type="number" name="dispatch_qty" id="dispatch_qty" class="form-control" placeholder="0" min="1" required>
                                <span class="stock-counter ms-2 d-none" id="max_badge">AVAIL: 0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section-header">
                    <span class="text">Logistics & Destination</span>
                    <div class="line"></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="field-wrapper">
                            <label>Division</label>
                            <select id="division_select" class="form-select searchable-select" required>
                                <option value="">-- Select Division --</option>
                                <?php while($d = $divisions->fetch_assoc()): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['division_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field-wrapper">
                            <label>Target Unit</label>
                            <select name="unit_id" id="unit_select" class="form-select searchable-select" required disabled>
                                <option value="">-- Select Division First --</option>
                                <?php while($u = $units_res->fetch_assoc()): ?>
                                    <option value="<?= $u['id'] ?>" data-division="<?= $u['division_id'] ?>">
                                        <?= htmlspecialchars($u['unit_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-5 pt-3 border-top">
                    <a href="view_central_stock.php" class="btn btn-light px-4 text-muted" style="border-radius:10px;">Cancel</a>
                    <button type="submit" name="process_dispatch" class="btn btn-primary px-5 py-2 fw-bold" style="border-radius:10px; background: #4361ee;">
                        <i class="bi bi-send-check me-2"></i>Process Dispatch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    $('.searchable-select').select2({ theme: 'bootstrap-5', width: '100%' });

    // 1. STOCK VALIDATION LOGIC
    $('#central_stock_id').on('change', function() {
        const max = $(this).find(':selected').data('max');
        if(max) {
            $('#max_badge').text('AVAIL: ' + max).removeClass('d-none');
            $('#dispatch_qty').attr('max', max);
        } else {
            $('#max_badge').addClass('d-none');
        }
    });

    // 2. DIVISION TO UNIT FILTERING
    const allUnitOptions = $('#unit_select option').clone();
    
    $('#division_select').on('change', function() {
        const selectedDivision = $(this).val();
        
        // Clear and Enable Unit Select
        $('#unit_select').empty().prop('disabled', false);
        $('#unit_select').append('<option value="">-- Select Receiving Unit --</option>');

        allUnitOptions.each(function() {
            if ($(this).data('division') == selectedDivision) {
                $('#unit_select').append($(this).clone());
            }
        });

        $('#unit_select').trigger('change');
    });

    // 3. FINAL SUBMIT VALIDATION
    $('#dispatchForm').on('submit', function(e) {
        const qty = parseInt($('#dispatch_qty').val());
        const max = parseInt($('#central_stock_id').find(':selected').data('max'));

        if (qty > max) {
            e.preventDefault();
            Swal.fire('Limit Exceeded', 'The warehouse only has ' + max + ' items available.', 'error');
        }
    });
});
</script>

<?php 
$content = ob_get_clean(); 
include "furniturelayout.php"; 
?>