<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "../config/db.php";

$page_title = "Service Records";
$page_icon  = "bi-card-checklist";

$where = "";
$total = 0;

// ✅ YOUR ORIGINAL LOGIC: Date Filtering
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

// ✅ YOUR DATA GROUPED (For Accordion View)
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

<div class="container-fluid animate-fade-in px-0">
    <div class="container-fluid animate-fade-in px-0">

    <?php if (isset($_GET['msg'])): ?>
        <div class="px-1 mb-3">
            <?php if ($_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center py-2 px-3" role="alert">
                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 24px; height: 24px;">
                        <i class="bi bi-check-lg small"></i>
                    </div>
                    <div class="small fw-bold text-success">Record deleted successfully!</div>
                    <button type="button" class="btn-close ms-auto small" data-bs-dismiss="alert" style="font-size: 0.5rem;"></button>
                </div>
            <?php elseif ($_GET['msg'] == 'error'): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center py-2 px-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>
                    <div class="small fw-bold text-danger">Error: Could not delete record.</div>
                    <button type="button" class="btn-close ms-auto small" data-bs-dismiss="alert" style="font-size: 0.5rem;"></button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4 align-items-center">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 border-start border-success border-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-emerald-soft p-2 rounded-3 text-success">
                        <i class="bi bi-currency-rupee fs-4"></i>
                    </div>
                    <div>
                        <p class="text-muted small fw-bold mb-0 text-uppercase">Total Expenditure</p>
                        <h4 class="fw-800 mb-0 text-dark">₹ <?= number_format($total, 2) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <form class="row g-2 align-items-center" method="GET">
                    <div class="col-sm-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-0 small fw-bold text-muted">FROM</span>
                            <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>" class="form-control border-light bg-light shadow-none" max="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-0 small fw-bold text-muted">TO</span>
                            <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>" class="form-control border-light bg-light shadow-none" max="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-sm-4 d-flex gap-2">
                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 fw-bold flex-grow-1">Filter</button>
                        <a href="view_services.php" class="btn btn-light btn-sm rounded-pill px-3 fw-bold border flex-grow-1 text-muted text-decoration-none text-center">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-3 px-1">
        <div class="position-relative" style="min-width: 320px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" id="vendorSearch" class="form-control rounded-pill ps-5 border-0 shadow-sm py-2" placeholder="Quick search vendor name...">
        </div>
        <div class="d-flex gap-2">
            <a href="export_excel.php" class="btn btn-white btn-sm border rounded-pill px-3 shadow-sm fw-bold text-success bg-white">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Export All
            </a>
            <a href="add_service.php" class="btn btn-dark btn-sm rounded-pill px-3 shadow-sm fw-bold">
                <i class="bi bi-plus-lg me-1"></i> Add Service
            </a>
        </div>
    </div>

    
    <div class="accordion accordion-flush rounded-4 overflow-hidden shadow-sm border bg-white" id="vendorAccordion">
        <?php if (empty($grouped_data)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-folder-x fs-1 text-muted mb-3 d-block"></i>
                <p class="text-muted fw-bold mb-0">No service records found.</p>
            </div>
        <?php else: 
            $v_index = 0;
            foreach ($grouped_data as $vendorName => $data): 
                $v_index++;
                $collapseId = "collapseVendor" . $v_index;
        ?>
            <div class="accordion-item vendor-group border-bottom" data-vendor="<?= strtolower($vendorName) ?>">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-3 px-4 bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                        <div class="d-flex align-items-center justify-content-between w-100 me-2">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-emerald-soft text-success rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px;">
                                    <i class="bi bi-buildings-fill fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="fw-800 text-dark mb-0"><?= htmlspecialchars($vendorName) ?></h6>
                                    <div class="d-flex gap-2 mt-1">
                                        <span class="badge bg-light text-muted border-0 fw-bold" style="font-size: 10px;"><?= $data['count'] ?> Service Registered</span>
                                        <?php if($data['unpaid_count'] > 0): ?>
                                            <span class="badge bg-danger-subtle text-danger border-0 fw-bold" style="font-size: 10px;">
                                                <i class="bi bi-exclamation-circle me-1"></i><?= $data['unpaid_count'] ?> Unpaid
                                            </span>
                                        
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-3">
                                <a href="export_excel.php?vendor_id=<?= $data['vendor_id'] ?>&from=<?= $_GET['from'] ?? '' ?>&to=<?= $_GET['to'] ?? '' ?>" 
                                class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold d-none d-md-flex align-items-center gap-1 shadow-sm" 
                                onclick="event.stopPropagation();" 
                                style="font-size: 11px; position: relative; z-index: 10;">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> 
                                    <span>Export</span>
                                </a>
                                <div class="text-end border-start ps-3">
                                    <p class="text-muted extra-small fw-800 text-uppercase mb-0" style="font-size: 9px; letter-spacing: 0.5px;">Vendor Total</p>
                                    <span class="fw-800 text-dark fs-5">₹ <?= number_format($data['total_amount'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </button>
                </h2>

                <div id="<?= $collapseId ?>" class="accordion-collapse collapse" data-bs-parent="#vendorAccordion">
                    <div class="accordion-body p-0 bg-light">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 bg-white">
                                <thead class="bg-light small text-uppercase fw-bold text-muted" style="font-size: 10px;">
                                    <tr>
                                        <th class="ps-4 py-3" style="width: 130px;">Service Date</th>
                                        <th class="py-3">Service Item</th>
                                        <th class="py-3">Bill Number</th>
                                        <th class="py-3">Amount</th>
                                        <th class="py-3">Status</th>
                                        <th class="text-end pe-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['services'] as $row): 
                                        $status = $row['bill_status'] ?? 'Unpaid';
                                        $badge_class = ($status == 'Paid') ? 'status-paid' : 'status-unpaid';

                                        //Switch Case Logic for Icons & Images
                                        $item_lower = strtolower($row['item_name'] ?? '');
                                        $icon = 'bi-wrench-adjustable'; // Default icon
                                        $img_url = '';

                                        switch (true) {
                                            case (strpos($item_lower, 'smps') !== false):
                                                $img_url = 'https://cdn.iconscout.com/icon/premium/png-512-thumb/power-supply-unit-icon-svg-download-png-10145540.png?f=webp&w=256';
                                                break;
                                            case (strpos($item_lower, 'printer') !== false): 
                                                $icon = 'bi-printer'; 
                                                break;
                                            case (strpos($item_lower, 'projector') !== false): 
                                                $icon = 'bi-projector'; 
                                                break;
                                            case (strpos($item_lower, 'ups') !== false): 
                                                $icon = 'bi-battery-charging'; 
                                                break;
                                            case (strpos($item_lower, 'cctv') !== false): 
                                                $icon = 'bi-camera-video'; 
                                                break;
                                            case (strpos($item_lower, 'motherboard') !== false): 
                                                $icon = 'bi-motherboard'; 
                                                break;
                                            case (strpos($item_lower, 'monitor') !== false): 
                                                $icon = 'bi-display'; 
                                                break;
                                            case (strpos($item_lower, 'mouse') !== false): 
                                                $icon = 'bi-mouse'; 
                                                break;
                                            case (strpos($item_lower, 'keyboard') !== false): 
                                                $icon = 'bi-keyboard'; 
                                                break;
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4 small fw-medium text-nowrap">
                                            <?= date("d M Y", strtotime($row['service_date'])) ?>
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="bg-light rounded-2 d-flex align-items-center justify-content-center shadow-sm border border-white" style="width: 40px; height: 40px; flex-shrink: 0;">
                                                    <?php if ($img_url): ?>
                                                        <img src="<?= $img_url ?>" style="width: 24px; height: 24px; object-fit: contain;">
                                                    <?php else: ?>
                                                        <i class="bi <?= $icon ?> text-secondary fs-5"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-800 text-dark lh-1 mb-1" style="font-size: 13px;">
                                                        <?= htmlspecialchars($row['item_name']) ?>
                                                    </div>
                                                    <div class="text-muted fw-bold text-uppercase d-flex align-items-center gap-1" style="font-size: 9px;">
                                                        <i class="bi bi-wrench-adjustable" style="font-size: 8px;"></i>
                                                        <?= htmlspecialchars($row['service_type']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="badge bg-light text-dark border fw-normal px-2" style="font-size: 11px;">
                                                <?= htmlspecialchars($row['bill_number']) ?>
                                            </span>
                                        </td>

                                        <td class="fw-800 text-dark">
                                            ₹ <?= number_format($row['amount'], 2) ?>
                                        </td>

                                        <td>
                                            <button class="status-badge <?= $badge_class ?> toggle-pill" 
                                                    data-id="<?= $row['id'] ?>" 
                                                    data-status="<?= $status ?>">
                                                <span class="status-dot"></span> <?= strtoupper($status) ?>
                                            </button>
                                        </td>

                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="edit_service.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-sm rounded-circle border-0 p-2">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <a href="delete_service.php?id=<?= $row['id'] ?>" class="btn btn-outline-danger btn-sm rounded-circle border-0 p-2" onclick="return confirm('Delete record?')">
                                                    <i class="bi bi-trash3"></i>
                                                </a>
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
// ✅ SEARCH LOGIC
document.getElementById('vendorSearch').addEventListener('input', function(e) {
    let term = e.target.value.toLowerCase();
    document.querySelectorAll('.vendor-group').forEach(group => {
        let vendorName = group.getAttribute('data-vendor');
        group.style.display = vendorName.includes(term) ? 'block' : 'none';
    });
});

// ✅ STATUS TOGGLE LOGIC (REFRESHES PAGE TO UPDATE UNPAID COUNTERS)
document.querySelectorAll('.toggle-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        let currentStatus = btn.getAttribute('data-status');
        let newStatus = currentStatus === 'Unpaid' ? 'Paid' : 'Unpaid';
        if(newStatus === 'Unpaid' && !confirm("Mark this bill as Unpaid?")) return;

        fetch('toggle_bill_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&status=${newStatus}`
        })
        .then(res => res.text())
        .then(res => {
            if(res.trim() === 'success'){
                location.reload(); 
            } else {
                alert("Update failed: " + res);
            }
        });
    });
});


// Auto-hide alerts after 3 seconds
setTimeout(function() {
    let alert = document.querySelector('.alert');
    if (alert) {
        let bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }
}, 3000);

</script>

<style>
.fw-800 { font-weight: 800 !important; }
.bg-emerald-soft { background-color: rgba(16, 185, 129, 0.1) !important; }
.extra-small { font-size: 10px; }
.bg-success-subtle { background-color: rgba(16, 185, 129, 0.12) !important; }
.bg-danger-subtle { background-color: rgba(244, 63, 94, 0.12) !important; }
.accordion-button:not(.collapsed) { background-color: #fff !important; color: #10b981 !important; box-shadow: none !important; }
.accordion-button:focus { box-shadow: none !important; }
.status-badge { display: inline-flex; align-items: center; gap: 8px; padding: 5px 14px; border-radius: 50px; font-size: 10px; font-weight: 700; border: 1px solid transparent; cursor: pointer; background: none; transition: 0.2s; }
.status-paid { background-color: rgba(16, 185, 129, 0.1) !important; color: #059669 !important; border-color: rgba(16, 185, 129, 0.2) !important; }
.status-paid .status-dot { width: 6px; height: 6px; background-color: #10b981; border-radius: 50%; }
.status-unpaid { background-color: rgba(244, 63, 94, 0.1) !important; color: #e11d48 !important; border-color: rgba(244, 63, 94, 0.2) !important; }
.status-unpaid .status-dot { width: 6px; height: 6px; background-color: #f43f5e; border-radius: 50%; }
.btn-outline-success { border-color: rgba(16, 185, 129, 0.3); color: #10b981; }
.btn-outline-success:hover { background-color: #10b981; color: #fff; }
</style>

<?php
$content = ob_get_clean();
$conn->close();
include "layout.php";
?>