<?php
require_once __DIR__ . "/../config/db.php";
include "../admin/auth.php";
include "../includes/session.php";

$division_id = $_SESSION['division_id'] ?? 0;
$role        = $_SESSION['role'] ?? '';

$report_type = $_GET['type'] ?? 'full';
$unit_id     = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;

/* ================= FETCH DIVISION & UNIT DETAILS ================= */
$division_name = "";
$unit_details  = "";

// Fetch actual division name
$div_q = $conn->query("SELECT division_name FROM divisions WHERE id = $division_id LIMIT 1");
if ($div_q && $div_q->num_rows > 0) {
    $division_name = $div_q->fetch_assoc()['division_name'];
}

// Fetch unit name if unit-wise report is selected
if ($report_type === 'unit' && $unit_id > 0) {
    $unit_q = $conn->query("SELECT unit_code, unit_name FROM units WHERE id = $unit_id LIMIT 1");
    if ($unit_q && $unit_q->num_rows > 0) {
        $u_row = $unit_q->fetch_assoc();
        $unit_details = ($u_row['unit_code'] ? $u_row['unit_code'] . " - " : "") . $u_row['unit_name'];
    }
} else {
    $unit_details = "All Division Units & Laboratories";
}

/* ================= SQL AUDIT LOGS QUERY ================= */
$query = "
    SELECT 
        al.id as log_id,
        al.created_at, 
        al.action_type, 
        al.notes,
        im.item_name, 
        sd.serial_number,
        COALESCE(
            al.asset_tag, 
            da.division_asset_id, 
            (
                SELECT al_sub.asset_tag 
                FROM asset_logs al_sub 
                WHERE al_sub.asset_id = al.asset_id AND al_sub.asset_tag IS NOT NULL 
                ORDER BY al_sub.id DESC LIMIT 1
            ),
            'STOCK'
        ) AS display_tag,
        NULLIF(al.unit_name, '0') AS snapshot_unit,
        un_active.unit_name AS active_unit,
        un_history.unit_name AS history_unit,
        u.username AS staff_name
    FROM asset_logs al
    JOIN stock_details sd ON al.asset_id = sd.id
    JOIN items_master im ON sd.stock_item_id = im.id
    LEFT JOIN users u ON al.performed_by = u.id
    LEFT JOIN division_assets da ON sd.id = da.stock_detail_id
    LEFT JOIN dispatch_details dd_active ON da.dispatch_detail_id = dd_active.id
    LEFT JOIN dispatch_master dm_active ON dd_active.dispatch_id = dm_active.id
    LEFT JOIN units un_active ON dm_active.unit_id = un_active.id
    LEFT JOIN dispatch_details dd_history ON sd.id = dd_history.stock_detail_id
    LEFT JOIN dispatch_master dm_history ON dd_history.dispatch_id = dm_history.id
    LEFT JOIN units un_history ON dm_history.unit_id = un_history.id
    WHERE 1=1
";

if ($role !== 'SuperAdmin') { 
    $query .= " AND (dm_active.division_id = $division_id OR dm_history.division_id = $division_id OR al.performed_by = {$_SESSION['user_id']})"; 
}

if ($report_type === 'unit' && $unit_id > 0) {
    $query .= " AND (dm_active.unit_id = $unit_id OR dm_history.unit_id = $unit_id)";
}

$query .= " GROUP BY al.id ORDER BY al.created_at DESC";
$logs = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Audit Report - <?= htmlspecialchars($division_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1e293b;
            background-color: #f8fafc;
        }
        .report-card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 2rem;
            max-width: 1100px;
            margin: 2rem auto;
        }
        .header-logo {
            max-height: 80px;
            width: auto;
            object-fit: contain;
        }
        .division-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .unit-subtitle {
            font-size: 0.95rem;
            font-weight: 600;
            color: #475569;
        }
        .audit-table {
            font-size: 0.82rem;
            border: 1px solid #cbd5e1;
        }
        .audit-table th {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.65rem 0.75rem;
            border-bottom: 2px solid #0f172a;
        }
        .audit-table td {
            padding: 0.65rem 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        @media print {
            body {
                background-color: #ffffff;
            }
            .report-card {
                box-shadow: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .audit-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="report-card">
        <!-- Print Action Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <a href="asset_logs.php" class="btn btn-outline-secondary btn-sm">
                &larr; Back to Audit Logs
            </a>
            <button onclick="window.print()" class="btn btn-primary btn-sm px-3">
                Print / Save PDF
            </button>
        </div>

        <!-- Header Image Banner -->
        <div class="text-center mb-3">
            <img src="../admin/assets/header.PNG" alt="Header Logo" class="header-logo img-fluid" onerror="this.style.display='none'">
        </div>

        <hr class="my-3" style="border-top: 2px solid #1e293b;">

        <!-- Meta Header Details -->
        <div class="mb-4 text-center">
            <div class="division-title"><?= htmlspecialchars($division_name) ?></div>
            <div class="unit-subtitle mt-1"><?= htmlspecialchars($unit_details) ?></div>
            <div class="text-muted small mt-1">Generated On: <?= date('d M, Y | h:i A') ?></div>
        </div>

        <!-- Audit Table -->
        <div class="table-responsive">
            <table class="table audit-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Transaction ID & Timestamp</th>
                        <th>Asset Details</th>
                        <th>Unit / Location</th>
                        <th class="text-center">Lifecycle Event</th>
                        <th class="text-center">Executed By</th>
                        <th>Remarks / Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while($row = $logs->fetch_assoc()): 
                            $status     = $row['action_type'];
                            $notes      = $row['notes'] ?? '';
                            $final_unit = $row['snapshot_unit'] ?? $row['active_unit'] ?? $row['history_unit'] ?? 'Main Stock / Returned';
                            
                            if (preg_match('/\[REF:#(\d+)\]\s*/', $notes, $matches)) {
                                $ref_id = "TRX-" . str_pad($matches[1], 5, '0', STR_PAD_LEFT);
                                $clean_notes = preg_replace('/\[REF:#\d+\]\s*/', '', $notes);
                            } else {
                                $ref_id = "TRX-" . str_pad($row['log_id'], 5, '0', STR_PAD_LEFT);
                                $clean_notes = $notes;
                            }
                            
                            switch ($status) {
                                case 'return_requested':  $status_label = "SERVICE REQUESTED"; break;
                                case 'repair_requested':
                                case 'repair_approved':   $status_label = "SENT TO REPAIR"; break;
                                case 'dispose_requested':
                                case 'disposal_approved': $status_label = "DECOMMISSIONED"; break;
                                case 'return_approved':
                                case 'completed':         $status_label = "RETURNED TO STOCK"; break;
                                case 'request_rejected':
                                case 'return_rejected':   $status_label = "REQUEST REJECTED"; break;
                                default:                  $status_label = !empty($status) ? strtoupper(str_replace('_', ' ', $status)) : "N/A"; break;
                            }
                        ?>
                        <tr>
                            <td>
                                <div><span class="fw-medium text-dark"><?= $ref_id ?></span></div>
                                <div class="fw-bold text-dark small mt-1"><?= date('d M, Y', strtotime($row['created_at'])) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']) ?></div>
                                <div class="text-primary small fw-semibold">SN: <?= htmlspecialchars($row['serial_number'] ?? 'N/A') ?></div>
                            </td>
                            <td>
                                <div class="fw-medium text-dark"><?= htmlspecialchars($final_unit) ?></div>
                                <div class="text-muted extra-small" style="font-size: 0.7rem;">ID: <?= htmlspecialchars($row['display_tag']) ?></div>
                            </td>
                            <td class="fw-medium text-dark" style="font-size: 0.72rem;">
                                <?= $status_label ?>
                            </td>
                            <td class="text-center">
                                <span class="fw-medium text-dark"><?= htmlspecialchars($row['staff_name'] ?: 'System') ?></span>
                            </td>
                            <td class="text-muted">
                                <?= htmlspecialchars($clean_notes ?: 'No notes recorded.') ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No audit logs found for this selection.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Report Footer -->
        <div class="d-flex justify-content-between align-items-center mt-5 pt-4 text-muted extra-small ">
            <div>StockFlow ERP Asset Management System</div>
            <div>System Admin</div>
        </div>
    </div>
</div>

</body>
</html>