<?php
session_start();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";

$page_title = "Global Purchase Ledger";

// Fetch Global Ledger Data
$query = "
    SELECT 
        pl.id,
        pl.purchase_date,
        v.vendor_name,
        pl.category,
        pl.bill_no,
        pl.master_sl_no,
        pl.final_invoice_amount,
        (SELECT SUM(qty) FROM purchase_items WHERE ledger_id = pl.id) as total_qty
    FROM purchase_ledger pl
    JOIN vendors v ON pl.vendor_id = v.id
    ORDER BY pl.purchase_date DESC";

$result = $conn->query($query);

ob_start();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-800 text-dark mb-0">Global Purchase Ledger</h4>
        <div class="btn-group shadow-sm">
            <button class="btn btn-white border fw-bold text-muted"><i class="bi bi-download me-2"></i>Export CSV</button>
            <button class="btn btn-primary fw-bold px-4">New Entry</button>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-muted small text-uppercase fw-bold">
                        <th class="ps-4">Date</th>
                        <th>Ref / Bill No</th>
                        <th>Vendor</th>
                        <th>Category</th>
                        <th class="text-center">Items Qty</th>
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
                            <div class="small text-muted"><?= $row['master_sl_no'] ?></div>
                            <div class="fw-bold">#<?= htmlspecialchars($row['bill_no']) ?></div>
                        </td>
                        <td class="fw-600 text-dark"><?= htmlspecialchars($row['vendor_name']) ?></td>
                        <td>
                            <span class="badge bg-secondary-soft text-secondary rounded-pill">
                                <?= $row['category'] ?>
                            </span>
                        </td>
                        <td class="text-center fw-bold"><?= number_format($row['total_qty'], 0) ?></td>
                        <td class="text-end pe-4">
                            <span class="fw-800 text-primary">₹<?= number_format($row['final_invoice_amount'], 2) ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>