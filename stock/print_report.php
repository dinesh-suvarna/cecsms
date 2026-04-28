<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";

$type = $_GET['type'] ?? 'unit'; // unit, division, or institution
$id = (int)($_GET['id'] ?? 0);

// 1. DYNAMIC QUERY BASED ON TYPE
$where_clause = "";
if ($type === 'unit') $where_clause = "WHERE dm.unit_id = $id";
elseif ($type === 'division') $where_clause = "WHERE dm.division_id = $id";
else $where_clause = "WHERE dm.institution_id = $id";

$query = "SELECT dm.*, dd.*, sd.serial_number, si.item_name, i.institution_name, d.division_name, un.unit_name
          FROM dispatch_master dm
          JOIN dispatch_details dd ON dm.id = dd.dispatch_id
          LEFT JOIN stock_details sd ON dd.stock_detail_id = sd.id
          LEFT JOIN items_master si ON sd.stock_item_id = si.id
          LEFT JOIN institutions i ON dm.institution_id = i.id
          LEFT JOIN divisions d ON dm.division_id = d.id
          LEFT JOIN units un ON dm.unit_id = un.id
          $where_clause ORDER BY dm.dispatch_date DESC";

$result = $conn->query($query);
$data = $result->fetch_all(MYSQLI_ASSOC);

// Get header info from first row
$header = $data[0] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dispatch Report - <?= htmlspecialchars($type) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @media print { .no-print { display: none; } }
        .report-header { border-bottom: 2px solid #333; margin-bottom: 20px; padding-bottom: 10px; }
        .signature-space { margin-top: 50px; border-top: 1px solid #ccc; width: 200px; display: inline-block; }
    </style>
</head>
<body class="bg-light">

<div class="container my-5 p-5 bg-white shadow-sm">
    <div class="no-print text-end mb-4">
        <button onclick="window.print()" class="btn btn-dark">Print Report</button>
        <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
    </div>

    <div class="row report-header align-items-center">
        <div class="col-6">
            <h3>DISPATCH VOUCHER</h3>
            <p class="text-muted small">Generated on: <?= date("d M, Y H:i") ?></p>
        </div>
        <div class="col-6 text-end">
            <h5 class="mb-0"><?= htmlspecialchars($header['institution_name'] ?? 'N/A') ?></h5>
            <p class="mb-0 text-muted"><?= htmlspecialchars($header['division_name'] ?? '') ?></p>
            <p class="fw-bold"><?= htmlspecialchars($header['unit_name'] ?? '') ?></p>
        </div>
    </div>

    <table class="table table-bordered mt-4">
        <thead class="table-light">
            <tr>
                <th>Date</th>
                <th>Item Name</th>
                <th>Serial / Qty</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($data as $row): ?>
            <tr>
                <td><?= date("d-m-Y", strtotime($row['dispatch_date'])) ?></td>
                <td><?= htmlspecialchars($row['item_name']) ?></td>
                <td class="font-monospace"><?= $row['serial_number'] ?: $row['quantity'] . " Units" ?></td>
                <td>DISPATCHED</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row mt-5 pt-5">
        <div class="col-6">
            <div class="signature-space"></div>
            <p>Authorized Signature</p>
        </div>
        <div class="col-6 text-end">
            <div class="signature-space"></div>
            <p>Receiver's Signature</p>
        </div>
    </div>
</div>

</body>
</html>