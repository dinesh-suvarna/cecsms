<?php
require_once __DIR__ . "/../config/db.php";
include "../includes/session.php";

date_default_timezone_set('Asia/Kolkata');

$unit_id = (int)($_GET['id'] ?? 0);

// Fallback unit details
$unit_info = null;
$u_res = $conn->query("SELECT u.unit_name, u.unit_code, d.division_name FROM units u LEFT JOIN divisions d ON u.division_id = d.id WHERE u.id = $unit_id");
if ($u_res) {
    $unit_info = $u_res->fetch_assoc();
}

// Fetch dispatch entries for unit
$query = "SELECT 
            dm.dispatch_date, 
            dm.id AS dispatch_id,
            im.item_name, 
            sd.serial_number, 
            dd.quantity,
            d.division_name, 
            u.unit_name,
            u.unit_code
          FROM dispatch_details dd
          INNER JOIN dispatch_master dm ON dd.dispatch_id = dm.id
          LEFT JOIN stock_details sd ON dd.stock_detail_id = sd.id
          LEFT JOIN items_master im ON sd.stock_item_id = im.id
          LEFT JOIN divisions d ON dm.division_id = d.id
          LEFT JOIN units u ON dm.unit_id = u.id
          WHERE dm.unit_id = $unit_id
          ORDER BY dm.id DESC, dm.dispatch_date DESC";

$result = $conn->query($query);
$unit_rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$header = $unit_rows[0] ?? null;

$division_name = $header['division_name'] ?? ($unit_info['division_name'] ?? "Unknown Division");
$unit_name     = $header['unit_name'] ?? ($unit_info['unit_name'] ?? "Unknown Unit");
$unit_code     = $header['unit_code'] ?? ($unit_info['unit_code'] ?? "");

$unit_label = !empty($unit_code) ? $unit_code . " - " . $unit_name : $unit_name;

$sub_header_parts = array_filter([$division_name, $unit_label]);
$sub_header_text  = implode(" | ", $sub_header_parts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unit Handover Voucher</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #fff; color: #000; font-size: 13px; }
        .voucher-container { padding: 20px; background: #fff; }
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
        <button onclick="window.print()" class="btn btn-primary px-4">Print Voucher</button>
        <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
    </div>

    <div class="voucher-container" id="printableReport">
        <!-- Header Banner & Title -->
        <div class="text-center mb-4">
            <img src="../admin/assets/header.PNG" alt="Header Logo" style="width:100%; max-width:850px;" class="mb-3">
            <h4 class="fw-bold text-uppercase mb-1" style="color: #123b63; letter-spacing: 0.03em;">UNIT DISPATCH HANDOVER VOUCHER</h4>
            <?php if (!empty($sub_header_text)): ?>
                <h6 class="text-dark fw-bold mb-1"><?= htmlspecialchars($sub_header_text) ?></h6>
            <?php endif; ?>
            <p class="text-muted small mb-0">Voucher Generated: <?= date('d-m-Y h:i A') ?></p>
        </div>

        <!-- Dispatch Table -->
        <table class="table table-clean align-middle mt-3">
            <thead>
                <tr>
                    <th class="text-center nowrap" width="6%">SL.NO</th>
                    <th class="nowrap" width="14%">DISPATCH ID</th>
                    <th class="nowrap" width="14%">DATE</th>
                    <th width="42%">ITEM DESCRIPTION</th>
                    <th class="nowrap" width="24%">SERIAL NUMBER / QUANTITY</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($unit_rows)): ?>
                    <?php foreach ($unit_rows as $index => $row): 
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
                        <td colspan="5" class="text-center py-4 text-muted">No items dispatched to this unit yet.</td>
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