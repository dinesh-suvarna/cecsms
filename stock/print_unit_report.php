<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";

$unit_id = (int)$_GET['id'];

$query = "SELECT 
            dm.dispatch_date, 
            dm.id AS dispatch_id,
            im.item_name, 
            im.category, 
            sd.serial_number, 
            sd.bill_no,
            v.vendor_name,
            dd.quantity,
            i.institution_name, 
            d.division_name, 
            u.unit_name
          FROM dispatch_details dd
          INNER JOIN dispatch_master dm ON dd.dispatch_id = dm.id
          /* Link to stock_details to get the serial and item reference */
          INNER JOIN stock_details sd ON dd.stock_detail_id = sd.id
          /* Link to items_master using the correct FK from stock_details */
          INNER JOIN items_master im ON sd.stock_item_id = im.id
          /* Optional: Link to vendors to show who supplied it */
          LEFT JOIN vendors v ON sd.vendor_id = v.id
          LEFT JOIN institutions i ON dm.institution_id = i.id
          LEFT JOIN divisions d ON dm.division_id = d.id
          LEFT JOIN units u ON dm.unit_id = u.id
          WHERE dm.unit_id = $unit_id
          ORDER BY dm.id DESC";

$result = $conn->query($query);
$unit_rows = [];
while($row = $result->fetch_assoc()) {
    $unit_rows[] = $row;
}

$header = $unit_rows[0] ?? null;

// Assignment with null coalescing to prevent warnings
$institution_name = $header['institution_name'] ?? "Unknown Institution";
$division_name    = $header['division_name'] ?? "Unknown Division";
$unit_name        = $header['unit_name'] ?? "Unknown Unit";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Unit Dispatch Voucher</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #fff; font-size: 12px; }
        .voucher-container { border: 2px solid #000; padding: 30px; margin-top: 20px; }
        .table th { background-color: #f2f2f2 !important; color: #000 !important; }
        .serial-badge { border: 1px solid #ccc; padding: 2px 5px; font-family: monospace; }
        @media print { .no-print { display: none; } body { padding: 0; } .voucher-container { border: none; } }
    </style>
</head>
<body>

<div class="container voucher-container">
    <div class="row mb-4">
        <div class="col-6">
            <h2 class="fw-bold">DISPATCH VOUCHER</h2>
            <p class="text-muted">ID: #<?= str_pad($unit_id, 5, '0', STR_PAD_LEFT) ?></p>
        </div>
        <div class="col-6 text-end">
            <h4 class="mb-0"><?= $institution_name ?></h4>
            <p class="mb-0"><?= $division_name ?> / <strong><?= $unit_name ?></strong></p>
            <p class="small">Date: <?= date("d-M-Y") ?></p>
        </div>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th width="10%">#</th>
                <th width="50%">Item Description</th>
                <th width="40%">Serial Number / Quantity</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($unit_rows as $index => $row): ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td>
                    <div class="fw-bold"><?= htmlspecialchars($row['item_name']) ?></div>
                    <small class="text-muted"><?= $row['category'] ?></small>
                </td>
                <td>
                    <?php if($row['serial_number']): ?>
                        <span class="serial-badge"><?= $row['serial_number'] ?></span>
                    <?php else: ?>
                        <?= $row['quantity'] ?> Units
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row mt-5">
        <div class="col-4 text-center">
            <div style="border-top: 1px solid #000; margin-top: 40px;"></div>
            <p class="fw-bold">Issued By</p>
        </div>
        <div class="col-4"></div>
        <div class="col-4 text-center">
            <div style="border-top: 1px solid #000; margin-top: 40px;"></div>
            <p class="fw-bold">Received By (Signature)</p>
            <p class="small text-muted">Name & Date</p>
        </div>
    </div>

    <div class="mt-5 pt-4 text-center text-muted small border-top no-print">
        <button onclick="window.print()" class="btn btn-primary px-4">Print Voucher</button>
        <button onclick="window.close()" class="btn btn-link text-decoration-none">Back to Dashboard</button>
    </div>
</div>

</body>
</html>