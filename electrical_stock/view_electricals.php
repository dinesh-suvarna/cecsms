<?php
include "../config/db.php";
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['SuperAdmin', 'Admin'])) {
    header("Location: ../index.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_division = $_SESSION['division_id'] ?? 0;

$display_swal = false;
if (isset($_SESSION['swal_msg'])) {
    $display_swal = true;
    $swal_text = $_SESSION['swal_msg'];
    $swal_type = $_SESSION['swal_type'] ?? 'success';
    unset($_SESSION['swal_msg'], $_SESSION['swal_type']);
}

// DELETE LOGIC - Updated for electrical tables
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    // Check against electrical_assets
    $check_assets = $conn->query("SELECT id FROM electrical_assets WHERE stock_id = $delete_id LIMIT 1");
    if ($check_assets->num_rows == 0) {
        if ($conn->query("DELETE FROM electrical_stock WHERE id = $delete_id")) {
            $_SESSION['swal_msg'] = "Electrical stock record deleted successfully.";
            $_SESSION['swal_type'] = "success";
        } else {
            $_SESSION['swal_msg'] = "Database error: Could not delete record.";
            $_SESSION['swal_type'] = "error";
        }
    } else {
        $_SESSION['swal_msg'] = "Cannot delete: Asset IDs already generated for this electrical batch.";
        $_SESSION['swal_type'] = "error";
    }
    header("Location: view_electrical.php");
    exit();
}

// QUERY - Updated for electrical tables
$sql = "SELECT 
            SUM(s.total_qty) as total_qty, 
            SUM(s.total_qty * s.unit_price) as total_investment,
            MAX(s.id) as id, 
            i.item_name, 
            v.vendor_name, 
            u.unit_name, 
            u.unit_code,
            d.division_name,
            GROUP_CONCAT(DISTINCT s.bill_no SEPARATOR ', ') as combined_bills,
            MAX(s.bill_date) as latest_bill_date
        FROM electrical_stock s
        JOIN electrical_items i ON s.electrical_item_id = i.id
        JOIN vendors v ON s.vendor_id = v.id
        JOIN units u ON s.unit_id = u.id
        JOIN divisions d ON u.division_id = d.id";

if ($user_role !== 'SuperAdmin') {
    $sql .= " WHERE u.division_id = '$user_division'";
}

$sql .= " GROUP BY i.item_name, u.id, v.vendor_name 
         ORDER BY d.division_name ASC, u.unit_code ASC, i.item_name ASC";

$result = $conn->query($sql);

$grouped_inventory = [];
while ($row = $result->fetch_assoc()) {
    $div_name = $row['division_name'];
    $unit_label = strtoupper($row['unit_code']) . " - " . $row['unit_name'];
    $grouped_inventory[$div_name][$unit_label][] = $row;
}

$page_title = "Electrical Inventory";
ob_start();
?>

<div class="container-fluid py-4 px-4 mt-n3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Electrical Inventory & Reports</h4>
            <p class="text-muted small mb-0">Financial tracking for Electrical Assets</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3 shadow-sm">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            <a href="add_electricals.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-plus-lg me-2"></i> Add New Stock
            </a>
        </div>
    </div>

    <?php if (empty($grouped_inventory)): ?>
        <div class="card border-0 shadow-sm rounded-4 py-5 text-center">
            <i class="bi bi-lightning-charge fs-1 opacity-25"></i>
            <p class="text-muted mt-3">No electrical inventory records found.</p>
        </div>
    <?php else: ?>
        <div class="accordion border-0" id="divisionAccordion">
            <?php 
            $div_count = 0;
            foreach ($grouped_inventory as $division_name => $units): 
                $div_count++;
                $div_collapse_id = "div_collapse_" . $div_count;
                
                $div_total_qty = 0;
                $div_total_val = 0;
                foreach($units as $u_items) {
                    foreach($u_items as $item) {
                        $div_total_qty += $item['total_qty'];
                        $div_total_val += $item['total_investment'];
                    }
                }
            ?>
                <div class="accordion-item shadow-sm rounded-4 overflow-hidden mb-3 border">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $div_collapse_id ?>">
                            <div class="d-flex flex-wrap align-items-center w-100">
                                <i class="bi bi-geo-alt-fill me-2"></i>
                                <span class="fw-bold me-auto"><?= htmlspecialchars(strtoupper($division_name)) ?></span>
                                <div class="mt-2 mt-md-0 me-3">
                                    <span class="report-badge bg-qty">Qty: <?= number_format($div_total_qty) ?></span>
                                    <span class="report-badge bg-invested">Invested: ₹<?= number_format($div_total_val, 2) ?></span>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="<?= $div_collapse_id ?>" class="accordion-collapse collapse" data-bs-parent="#divisionAccordion">
                        <div class="accordion-body p-3 bg-light">
                            
                            <div class="accordion accordion-flush" id="unitAccordion_<?= $div_count ?>">
                                <?php 
                                $unit_count = 0;
                                foreach ($units as $unit_label => $items): 
                                    $unit_count++;
                                    $unit_collapse_id = "unit_collapse_" . $div_count . "_" . $unit_count;
                                    $unit_total_val = array_sum(array_column($items, 'total_investment'));
                                ?>
                                <div class="accordion-item border rounded-3 mb-2 overflow-hidden shadow-sm">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed bg-white text-primary fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $unit_collapse_id ?>">
                                            <div class="d-flex align-items-center justify-content-between w-100 pe-3">
                                                <div>
                                                    <i class="bi bi-building me-2"></i> 
                                                    <?= htmlspecialchars($unit_label) ?>
                                                </div>
                                                <div class="text-dark small fw-bold">
                                                    Unit Total: ₹<?= number_format($unit_total_val, 2) ?>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="<?= $unit_collapse_id ?>" class="accordion-collapse collapse" data-bs-parent="#unitAccordion_<?= $div_count ?>">
                                        <div class="accordion-body p-0 bg-white">
                                            <div class="table-responsive">
                                                <table class="table table-hover align-middle mb-0">
                                                    <thead class="bg-unit-header text-uppercase">
                                                        <tr class="x-small fw-bold">
                                                            <th class="ps-4">Item Name</th>
                                                            <th class="text-center">Qty</th>
                                                            <th class="text-end">Investment</th>
                                                            <th>Vendor</th>
                                                            <th>Bill Details</th>
                                                            <th class="text-end pe-4">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($items as $row): ?>
                                                            <tr>
                                                                <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></td>
                                                                <td class="text-center fw-bold"><?= $row['total_qty'] ?></td>
                                                                <td class="text-end fw-bold text-primary">₹<?= number_format($row['total_investment'], 2) ?></td>
                                                                <td class="small text-muted fw-bold"><?= htmlspecialchars($row['vendor_name']) ?></td>
                                                                <td>
                                                                    <div class="x-small fw-bold"><?= htmlspecialchars($row['combined_bills']) ?></div>
                                                                    <div class="x-small text-muted"><?= date('d M, Y', strtotime($row['latest_bill_date'])) ?></div>
                                                                </td>
                                                                <td class="text-end pe-4">
                                                                    <div class="btn-group border rounded-pill overflow-hidden shadow-sm bg-white">
                                                                        <button onclick="editStock(<?= $row['id'] ?>)" class="btn btn-sm btn-white border-end px-3">
                                                                            <i class="bi bi-pencil-square text-primary"></i>
                                                                        </button>
                                                                        <button onclick="deleteStock(<?= $row['id'] ?>)" class="btn btn-sm btn-white px-3">
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

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Electrical blue theme */
    #divisionAccordion .accordion-button {
        background-color: #eff6ff !important;
        color: #1e40af !important;
        box-shadow: none;
    }
    #divisionAccordion .accordion-button:not(.collapsed) {
        background-color: #3b82f6 !important;
        color: #fff !important;
    }
    #divisionAccordion .accordion-button::after { filter: sepia(100%) hue-rotate(190deg) saturate(500%); }
    #divisionAccordion .accordion-button:not(.collapsed)::after { filter: brightness(0) invert(1); }

    .report-badge {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.4rem 0.8rem;
        border-radius: 8px;
        margin-left: 8px;
        display: inline-block;
        border: 1px solid transparent;
    }
    .bg-qty { background-color: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
    .bg-invested { background-color: #fef3c7; color: #92400e; border-color: #fde68a; }

    .bg-unit-header { background-color: #f8fafc; color: #64748b; }
    .x-small { font-size: 0.72rem; }
    .btn-white { background-color: #fff; border:none; }
    .btn-white:hover { background-color: #f8f9fa; }
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
    form.method = 'POST'; form.action = 'add_electricals.php';
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'trigger_edit'; input.value = id;
    form.appendChild(input); document.body.appendChild(form); form.submit();
}

function deleteStock(id) {
    Swal.fire({
        title: 'Delete Electrical Batch?',
        text: "This batch and its recorded costs will be removed!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e33e4d',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `view_electrical.php?delete_id=${id}`;
        }
    });
}
</script>

<?php 
$content = ob_get_clean(); 
include "electricalslayout.php"; // Changed to electrical layout
?>