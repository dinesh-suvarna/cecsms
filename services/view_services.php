<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/crypto.php"; 
require_once __DIR__ . "/../includes/functions.php";

$page_title = "Service Records";
$page_icon  = "bi-card-checklist";

$where = "";
$total = 0;

if(isset($_GET['from']) && isset($_GET['to']) && $_GET['from'] && $_GET['to']){
    $from = $_GET['from'];
    $to = $_GET['to'];
    $where = "WHERE s.service_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare("SELECT s.*, v.vendor_name FROM services s JOIN vendors v ON s.vendor_id = v.id $where ORDER BY s.service_date DESC");
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();

    $stmt2 = $conn->prepare("SELECT SUM(amount) as total FROM services WHERE service_date BETWEEN ? AND ?");
    $stmt2->bind_param("ss", $from, $to);
    $stmt2->execute();
    $total_res = $stmt2->get_result()->fetch_assoc();
    $total = $total_res['total'] ?? 0;
} else {
    $result = $conn->query("SELECT s.*, v.vendor_name FROM services s JOIN vendors v ON s.vendor_id = v.id ORDER BY s.service_date DESC");
    $total_res = $conn->query("SELECT SUM(amount) as total FROM services")->fetch_assoc();
    $total = $total_res['total'] ?? 0;
}

$grouped_data = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $vendor = $row['vendor_name'];
        if (!isset($grouped_data[$vendor])) {
            $grouped_data[$vendor] = [
                'services' => [],
                'total_amount' => 0,
                'count' => 0,
                'unpaid_count' => 0,
                'vendor_id' => $row['vendor_id']
            ];
        }
        $grouped_data[$vendor]['services'][] = $row;
        $grouped_data[$vendor]['total_amount'] += $row['amount'];
        $grouped_data[$vendor]['count']++;
        if (isset($row['bill_status']) && strtolower($row['bill_status']) === 'unpaid') {
            $grouped_data[$vendor]['unpaid_count']++;
        }
    }
}

ob_start();
?>

<style>
/* ERP Design System Tokens */
:root {
    --erp-navy: #123b63;
    --erp-bg: #f3f5f7;
    --erp-card-bg: #ffffff;
    --erp-border: #d9e0e7;
    --erp-text-main: #20384d;
    --erp-text-muted: #64748b;
}

body { background-color: var(--erp-bg); font-family: 'Inter', sans-serif; color: var(--erp-text-main); }

/* Outer Page Wrapper Padding */
.page-wrapper {
    padding: 24px 28px 36px;
}

/* Page Header Layout & Horizontal Line */
.inst-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding-bottom: 20px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--erp-border);
}

/* Header Left Block */
.inst-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

/* Header Square Icon Box */
.inst-header-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #edf3f8;
    border: 1px solid #dce6ee;
    border-radius: 6px;
    color: var(--erp-navy);
    font-size: 1.15rem;
    flex-shrink: 0;
}

/* Page Specific Utility Styles */
.metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid transparent;
    cursor: pointer;
    background: none;
    transition: all 0.2s;
}
.status-paid {
    background-color: rgba(16, 185, 129, 0.1) !important;
    color: #059669 !important;
    border-color: rgba(16, 185, 129, 0.25) !important;
}
.status-paid .status-dot {
    width: 6px;
    height: 6px;
    background-color: #10b981;
    border-radius: 50%;
}
.status-unpaid {
    background-color: rgba(244, 63, 94, 0.1) !important;
    color: #e11d48 !important;
    border-color: rgba(244, 63, 94, 0.25) !important;
}
.status-unpaid .status-dot {
    width: 6px;
    height: 6px;
    background-color: #f43f5e;
    border-radius: 50%;
}
</style>

<div class="container-fluid page-wrapper">

    <!-- PAGE TOP BAR -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi <?= $page_icon ?>"></i>
            </div>
            <div>
                <h4 class="fw-bold tracking-tight mb-1" style="color: var(--erp-text-main); letter-spacing: -0.01em;"><?= htmlspecialchars($page_title) ?></h4>
                <p class="text-muted extra-small mb-0">Track and manage vendor billing records and maintenance logs.</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="export_excel.php" class="btn btn-outline-success btn-sm fw-semibold px-3">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
            </a>
            <a href="add_service.php" class="btn btn-primary btn-sm fw-semibold px-3" style="background-color: var(--primary-accent, var(--erp-navy)); border-color: var(--primary-accent, var(--erp-navy));">
                <i class="bi bi-plus-lg me-1"></i> Add Service
            </a>
        </div>
    </div>

    <!-- METRICS & FILTER BAR -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="metric-card shadow-sm h-100 d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 text-white d-flex align-items-center justify-content-center" style="background-color: var(--primary-accent, var(--erp-navy)); width: 48px; height: 48px;">
                    <i class="bi bi-currency-rupee fs-4"></i>
                </div>
                <div>
                    <span class="text-muted fw-bold text-uppercase d-block" style="font-size: 0.68rem; letter-spacing: 0.05em;">Total Expenditure</span>
                    <h4 class="fw-bold text-dark mb-0"><?= inr($total, true) ?></h4>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3">
                    <form class="row g-2 align-items-center" method="GET">
                        <div class="col-sm-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold">FROM</span>
                                <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>" class="form-control" max="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold">TO</span>
                                <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>" class="form-control" max="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-sm-4 d-flex gap-2">
                            <button type="submit" class="btn btn-sm text-white flex-grow-1 fw-bold" style="background-color: var(--primary-accent, var(--erp-navy));">Filter</button>
                            <a href="view_services.php" class="btn btn-sm btn-light border flex-grow-1 fw-bold">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- VENDOR SEARCH -->
    <div class="mb-3">
        <div class="input-group" style="max-width: 320px;">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="vendorSearch" class="form-control border-start-0 ps-0" placeholder="Search vendor name...">
        </div>
    </div>

    <!-- ACCORDION LIST -->
    <div class="accordion shadow-sm rounded-3 overflow-hidden" id="vendorAccordion">
        <?php if (empty($grouped_data)): ?>
            <div class="bg-white p-5 text-center">
                <i class="bi bi-folder-x fs-1 text-muted opacity-50 mb-2 d-block"></i>
                <p class="text-muted fw-semibold mb-0">No service records found.</p>
            </div>
        <?php else: 
            $v_index = 0;
            foreach ($grouped_data as $vendorName => $data): 
                $v_index++;
                $collapseId = "collapseVendor" . $v_index;
                // Encrypt vendor_id for export link
                $enc_vendor_id = encrypt_id($data['vendor_id']);
        ?>
            <div class="accordion-item border-0 border-bottom vendor-group" data-vendor="<?= strtolower($vendorName) ?>">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                        <div class="d-flex align-items-center justify-content-between w-100 me-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light text-primary border rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                                    <i class="bi bi-buildings-fill fs-6"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1" style="font-size: 0.92rem;"><?= htmlspecialchars($vendorName) ?></h6>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-light text-dark border fw-medium" style="font-size: 0.65rem;"><?= $data['count'] ?> Services</span>
                                        <?php if($data['unpaid_count'] > 0): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-medium" style="font-size: 0.65rem;">
                                                <i class="bi bi-exclamation-circle me-1"></i><?= $data['unpaid_count'] ?> Unpaid
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-3">
                                <a href="export_excel.php?vendor_id=<?= urlencode($enc_vendor_id) ?>&from=<?= $_GET['from'] ?? '' ?>&to=<?= $_GET['to'] ?? '' ?>" 
                                   class="btn btn-sm btn-outline-success px-2 d-none d-md-inline-flex align-items-center gap-1" 
                                   onclick="event.stopPropagation();" style="font-size: 0.72rem;">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> Export
                                </a>
                                <div class="text-end border-start ps-3">
                                    <span class="d-block text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Total</span>
                                    <span class="fw-bold text-dark"><?= inr($data['total_amount'], true) ?></span>
                                </div>
                            </div>
                        </div>
                    </button>
                </h2>

                <div id="<?= $collapseId ?>" class="accordion-collapse collapse" data-bs-parent="#vendorAccordion">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.83rem;">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3" style="width: 130px;">Date</th>
                                        <th>Service Item</th>
                                        <th>Bill No.</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['services'] as $row): 
                                        $status = $row['bill_status'] ?? 'Unpaid';
                                        $badge_class = ($status == 'Paid') ? 'status-paid' : 'status-unpaid';

                                        // Encrypt service ID for action links
                                        $enc_service_id = encrypt_id($row['id']);

                                        $item_lower = strtolower($row['item_name'] ?? '');
                                        $icon = 'bi-wrench-adjustable'; 
                                        $img_url = '';

                                        switch (true) {
                                            case (strpos($item_lower, 'smps') !== false):
                                                $img_url = 'https://cdn.iconscout.com/icon/premium/png-512-thumb/power-supply-unit-icon-svg-download-png-10145540.png?f=webp&w=256';
                                                break;
                                            case (strpos($item_lower, 'printer') !== false): $icon = 'bi-printer'; break;
                                            case (strpos($item_lower, 'projector') !== false): $icon = 'bi-projector'; break;
                                            case (strpos($item_lower, 'ups') !== false): $icon = 'bi-battery-charging'; break;
                                            case (strpos($item_lower, 'cctv') !== false): $icon = 'bi-camera-video'; break;
                                            case (strpos($item_lower, 'motherboard') !== false): $icon = 'bi-motherboard'; break;
                                            case (strpos($item_lower, 'monitor') !== false): $icon = 'bi-display'; break;
                                            case (strpos($item_lower, 'mouse') !== false): $icon = 'bi-mouse'; break;
                                            case (strpos($item_lower, 'keyboard') !== false): $icon = 'bi-keyboard'; break;
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-medium text-nowrap">
                                            <?= date("d M Y", strtotime($row['service_date'])) ?>
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-light border rounded d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0;">
                                                    <?php if ($img_url): ?>
                                                        <img src="<?= $img_url ?>" style="width: 18px; height: 18px; object-fit: contain;">
                                                    <?php else: ?>
                                                        <i class="bi <?= $icon ?> text-secondary fs-6"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark lh-1"><?= htmlspecialchars($row['item_name']) ?></div>
                                                    <div class="text-muted fw-semibold text-uppercase mt-1" style="font-size: 0.65rem;">
                                                        <?= htmlspecialchars($row['service_type']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars($row['bill_number']) ?>
                                            </span>
                                        </td>

                                        <td class="fw-bold text-dark">
                                            <?= inr($row['amount'], true) ?>
                                        </td>

                                        <td>
                                            <button class="status-badge <?= $badge_class ?> toggle-pill" 
                                                    data-token="<?= htmlspecialchars($enc_service_id) ?>" 
                                                    data-status="<?= $status ?>">
                                                <span class="status-dot"></span> <?= strtoupper($status) ?>
                                            </button>
                                        </td>

                                        <td class="text-end pe-3">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="edit_service.php?id=<?= urlencode($enc_service_id) ?>" class="btn btn-sm btn-light border p-1 text-primary">
                                                    <i class="bi bi-pencil-square fs-6"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-light border p-1 text-danger delete-btn" data-token="<?= htmlspecialchars($enc_service_id) ?>">
                                                    <i class="bi bi-trash3 fs-6"></i>
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
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // VENDOR SEARCH FILTER
    const searchInput = document.getElementById('vendorSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            let term = e.target.value.toLowerCase();
            document.querySelectorAll('.vendor-group').forEach(group => {
                let vendorName = group.getAttribute('data-vendor');
                group.style.display = vendorName.includes(term) ? 'block' : 'none';
            });
        });
    }

    // SWEETALERT2 DELETION CONFIRMATION
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const token = this.getAttribute('data-token');

            Swal.fire({
                title: 'Delete Service Record?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete_service.php?id=${encodeURIComponent(token)}`;
                }
            });
        });
    });

    // SWEETALERT2 STATUS TOGGLE CONFIRMATION
    document.querySelectorAll('.toggle-pill').forEach(btn => {
        btn.addEventListener('click', function() {
            const token = this.getAttribute('data-token');
            let currentStatus = this.getAttribute('data-status');
            let newStatus = currentStatus === 'Unpaid' ? 'Paid' : 'Unpaid';

            const executeToggle = () => {
                fetch('toggle_bill_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${encodeURIComponent(token)}&status=${newStatus}`
                })
                .then(res => res.text())
                .then(res => {
                    if (res.trim() === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: `Status changed to ${newStatus}.`,
                            timer: 1200,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', 'Update failed: ' + res, 'error');
                    }
                })
                .catch(() => {
                    Swal.fire('Error', 'Network error. Please try again.', 'error');
                });
            };

            if (newStatus === 'Unpaid') {
                Swal.fire({
                    title: 'Mark as Unpaid?',
                    text: "Are you sure you want to revert this bill status to Unpaid?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, mark Unpaid',
                    customClass: { confirmButton: 'text-dark' }
                }).then((result) => {
                    if (result.isConfirmed) executeToggle();
                });
            } else {
                executeToggle();
            }
        });
    });

    // Check URL parameters for status alerts
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg') === 'deleted') {
        Swal.fire('Deleted!', 'Service record has been deleted.', 'success');
    } else if (urlParams.get('msg') === 'error') {
        Swal.fire('Error!', 'Failed to delete record.', 'error');
    }
});
</script>

<?php
$content = ob_get_clean();
include "layout.php";
?>