<?php
session_start();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";

$page_title = "Global Purchase Ledger";

// Virtual Ledger Query: Combining all 3 stock sources into one list
$query = "
    SELECT * FROM (
        -- Computer Stock
        SELECT 
            sd.bill_date as purchase_date,
            v.vendor_name,
            'Computer' as category,
            sd.bill_no,
            'COMP-' as prefix,
            sd.quantity as qty,
            sd.amount as total_amount
        FROM stock_details sd
        JOIN vendors v ON sd.vendor_id = v.id

        UNION ALL

        -- Furniture Stock
        SELECT 
            fs.bill_date as purchase_date,
            v.vendor_name,
            'Furniture' as category,
            fs.bill_no,
            'FURN-' as prefix,
            fs.total_qty as qty,
            (fs.total_qty * fs.unit_price) as total_amount
        FROM furniture_stock fs
        JOIN vendors v ON fs.vendor_id = v.id

        UNION ALL

        -- Electrical Stock
        SELECT 
            es.bill_date as purchase_date,
            v.vendor_name,
            'Electrical' as category,
            es.bill_no,
            'ELEC-' as prefix,
            es.total_qty as qty,
            (es.total_qty * es.unit_price) as total_amount
        FROM electrical_stock es
        JOIN vendors v ON es.vendor_id = v.id
    ) as virtual_ledger
    ORDER BY purchase_date DESC";

$result = $conn->query($query);

ob_start();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-800 text-dark mb-0">Global Purchase Ledger</h4>
            <p class="text-muted small mb-0">Consolidated view of all stock procurement records.</p>
        </div>
        <div class="btn-group shadow-sm">
            <button class="btn btn-white border fw-bold text-muted"><i class="bi bi-download me-2"></i>Export CSV</button>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="globalLedgerTable">
                <thead class="bg-light">
                    <tr class="text-muted small text-uppercase fw-bold">
                        <th class="ps-4">Date</th>
                        <th>Bill Number</th>
                        <th>Vendor</th>
                        <th>Category</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end pe-4">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?= date('d M, Y', strtotime($row['purchase_date'])) ?></div>
                        </td>
                        <td>
                            <div class="badge bg-light text-dark border fw-normal mb-1"><?= $row['prefix'] ?>Ref</div>
                            <div class="fw-800 text-dark">#<?= htmlspecialchars($row['bill_no']) ?></div>
                        </td>
                        <td class="fw-600 text-dark"><?= htmlspecialchars($row['vendor_name']) ?></td>
                        <td>
                            <?php 
                                $badgeClass = 'bg-soft-primary text-primary';
                                if($row['category'] == 'Furniture') $badgeClass = 'bg-soft-success text-success';
                                if($row['category'] == 'Electrical') $badgeClass = 'bg-soft-warning text-warning';
                            ?>
                            <span class="badge <?= $badgeClass ?> rounded-pill">
                                <?= $row['category'] ?>
                            </span>
                        </td>
                        <td class="text-center fw-bold"><?= number_format($row['qty'], 0) ?></td>
                        <td class="text-end pe-4">
                            <span class="fw-800 text-primary">₹<?= number_format($row['total_amount'], 2) ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#globalLedgerTable').DataTable({
        "pageLength": 15,
        "order": [[0, 'desc']], // Newest first
        "dom": '<"d-flex justify-content-between align-items-center p-4"lf>rt<"p-4 d-flex justify-content-between"ip>',
        "language": {
            "search": "",
            "searchPlaceholder": "Search ledger..."
        }
    });
});
</script>

<?php 
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>