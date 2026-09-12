<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";

date_default_timezone_set('Asia/Kolkata');

$type = $_GET['type'] ?? 'division'; // unit, division, or institution
$id = (int)($_GET['id'] ?? 0);

// Fallback headers if query yields 0 rows
$entity_info = null;
if ($type === 'division') {
    $res = $conn->query("SELECT d.division_name, i.institution_name FROM divisions d LEFT JOIN institutions i ON d.institution_id = i.id WHERE d.id = $id");
    $entity_info = $res->fetch_assoc();
} elseif ($type === 'unit') {
    $res = $conn->query("SELECT u.unit_name, u.unit_code, d.division_name, i.institution_name FROM units u LEFT JOIN divisions d ON u.division_id = d.id LEFT JOIN institutions i ON d.institution_id = i.id WHERE u.id = $id");
    $entity_info = $res->fetch_assoc();
} elseif ($type === 'institution') {
    $res = $conn->query("SELECT institution_name FROM institutions WHERE id = $id");
    $entity_info = $res->fetch_assoc();
}

// Query dispatch records
$where_clause = "";
if ($type === 'unit') $where_clause = "WHERE dm.unit_id = $id";
elseif ($type === 'division') $where_clause = "WHERE dm.division_id = $id";
else $where_clause = "WHERE dm.institution_id = $id";

$query = "SELECT 
            dm.id AS dispatch_id, 
            dm.dispatch_date, 
            dd.quantity, 
            sd.serial_number, 
            si.item_name, 
            d.division_name, 
            un.unit_name,
            un.unit_code
          FROM dispatch_details dd
          INNER JOIN dispatch_master dm ON dd.dispatch_id = dm.id
          LEFT JOIN stock_details sd ON dd.stock_detail_id = sd.id
          LEFT JOIN items_master si ON sd.stock_item_id = si.id
          LEFT JOIN institutions i ON dm.institution_id = i.id
          LEFT JOIN divisions d ON dm.division_id = d.id
          LEFT JOIN units un ON dm.unit_id = un.id
          $where_clause 
          ORDER BY dm.id DESC, dm.dispatch_date DESC";

$result = $conn->query($query);
$data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Extract header details
$header_row = $data[0] ?? null;

$div_name  = $header_row['division_name'] ?? ($entity_info['division_name'] ?? '');
$unit_code = $header_row['unit_code'] ?? ($entity_info['unit_code'] ?? '');
$unit_name = $header_row['unit_name'] ?? ($entity_info['unit_name'] ?? '');

$unit_label = !empty($unit_code) ? $unit_code . " - " . $unit_name : $unit_name;

$sub_header_parts = array_filter([$div_name, $unit_label]);
$sub_header_text  = implode(" | ", $sub_header_parts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dispatch Audit Report - <?= ucfirst(htmlspecialchars($type)) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #fff; color: #000; font-size: 13px; }
        .report-card { border: none; padding: 20px; background: #fff; }
        .table-clean { border-collapse: collapse !important; width: 100%; table-layout: fixed; }
        .table-clean th, .table-clean td { border: 1px solid #000000 !important; padding: 8px 10px; color: #000 !important; word-wrap: break-word; }
        .table-clean thead th { background-color: #f8fafc !important; text-transform: uppercase; font-size: 0.75rem; font-weight: 700; }
        .nowrap { white-space: nowrap !important; }
        .sig-line-box { width: 100%; height: 2px; margin-bottom: 6px; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0 !important; margin: 0 !important; }
            .container { max-width: 100% !important; width: 100% !important; padding: 0 !important; }
            .table-clean tr { page-break-inside: avoid !important; }
            .signature-block { page-break-inside: avoid !important; margin-top: 50px !important; }
            @page { size: A4 portrait; margin: 1cm; }
        }
    </style>
</head>
<body>

<div class="container my-4">
    <!-- Action Bar -->
    <div class="no-print d-flex justify-content-end gap-2 mb-4">
        <button onclick="window.print()" class="btn btn-primary px-4">Print Report</button>
        <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
    </div>

    <div class="report-card" id="printableReport">
        <!-- Header Banner & Title -->
        <div class="text-center mb-4">
            <img src="../admin/assets/header.PNG" alt="Header Logo" style="width:100%; max-width:850px;" class="mb-3">
            <h4 class="fw-bold text-uppercase mb-1" style="color: #123b63; letter-spacing: 0.03em;">DISPATCH AUDIT STOCK REPORT</h4>
            <?php if (!empty($sub_header_text)): ?>
                <h6 class="text-dark fw-bold mb-1"><?= htmlspecialchars($sub_header_text) ?></h6>
            <?php endif; ?>
            <p class="text-muted small mb-0">Report Generated: <?= date('d-m-Y h:i A') ?></p>
        </div>

        <!-- Dispatch Table -->
        <table class="table table-clean align-middle mt-3">
            <thead>
                <tr>
                    <th class="text-center nowrap" width="6%">SL.NO</th>
                    <th class="nowrap" width="14%">DISPATCH ID</th>
                    <th class="nowrap" width="14%">DATE</th>
                    <th width="42%">ITEM DESCRIPTION</th>
                    <th class="nowrap" width="24%">SERIAL / QUANTITY</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data)): ?>
                    <?php foreach ($data as $index => $row): 
                        $dsp_code = "DSP-" . str_pad($row['dispatch_id'], 4, '0', STR_PAD_LEFT);
                    ?>
                    <tr>
                        <td class="text-center nowrap"><?= $index + 1 ?></td>
                        <td class="font-monospace fw-bold nowrap"><?= $dsp_code ?></td>
                        <td class="nowrap"><?= date("d-m-Y", strtotime($row['dispatch_date'])) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($row['item_name']) ?></td>
                        <td class="font-monospace nowrap">
                            <?= !empty($row['serial_number']) ? htmlspecialchars($row['serial_number']) : $row['quantity'] . " Units" ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No dispatch records found for this location.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Signature Block -->
        <div class="signature-block mt-5 pt-4">
            <div class="d-flex justify-content-between">
                <div class="text-center" style="width: 200px;">
                    <svg class="sig-line-box"><line x1="0" y1="1" x2="200" y2="1" stroke="#000000" stroke-width="1.5"/></svg>
                    <small class="fw-bold">Lab Incharge</small>
                </div>
                <div class="text-center" style="width: 200px;">
                    <svg class="sig-line-box"><line x1="0" y1="1" x2="200" y2="1" stroke="#000000" stroke-width="1.5"/></svg>
                    <small class="fw-bold">Physical Lab Incharge</small>
                </div>
                <div class="text-center" style="width: 200px;">
                    <svg class="sig-line-box"><line x1="0" y1="1" x2="200" y2="1" stroke="#000000" stroke-width="1.5"/></svg>
                    <small class="fw-bold">System Admin</small>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>