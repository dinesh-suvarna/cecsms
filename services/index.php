<?php

session_start();

if (!isset($_SESSION["role"])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/db.php";

$page_title = "Service Dashboard";

// Logic for statistics
$total_services = 0;
$total_amount = 0;
$result = $conn->query("SELECT COUNT(*) as total_services, IFNULL(SUM(amount),0) as total_amount FROM services");
if ($result) {
    $row = $result->fetch_assoc();
    $total_services = $row['total_services'];
    $total_amount = $row['total_amount'];
}

// Logic for Bill Search
$search_result = null;
if (isset($_GET['bill_no']) && !empty(trim($_GET['bill_no']))) {
    $bill_no = trim($_GET['bill_no']);
    $stmt = $conn->prepare("SELECT s.*, v.vendor_name FROM services s LEFT JOIN vendors v ON s.vendor_id = v.id WHERE s.bill_number = ?");
    $stmt->bind_param("s", $bill_no);
    $stmt->execute();
    $search_result = $stmt->get_result();
}

$action = $_GET['action'] ?? '';

ob_start();
?>

<style>
/* Enterprise UI Theme Tokens */
:root {
    --erp-navy: #173f63;
    --erp-navy-dark: #102f4a;
    --erp-text: #263746;
    --erp-muted: #71808f;
    --erp-border: #dce3e9;
    --erp-bg: #f5f7f9;
    --erp-white: #ffffff;
    --erp-shadow: 0 1px 3px rgba(20, 40, 60, .06);
    --erp-accent-green: #10b981;
    --erp-accent-blue: #0d6efd;
}

/* Page Layout Container */
.service-dashboard-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 20px 40px;
}

/* ERP Header Styling */
.inst-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding-bottom: 20px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--erp-border);
}
.inst-header-left { display: flex; align-items: center; gap: 14px; }
.inst-header-icon {
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    background: #edf3f8; border: 1px solid #dce6ee; border-radius: 5px;
    color: var(--erp-navy); font-size: 1.1rem;
}
.inst-header h3 { margin: 0; color: var(--erp-navy-dark); font-size: 1.18rem; font-weight: 650; }
.inst-header p { margin: 3px 0 0; color: var(--erp-muted); font-size: .76rem; }

/* Panel Styling */
.inst-form-panel {
    background: #f9fafb; border: 1px solid var(--erp-border); border-radius: 5px;
    margin-bottom: 22px; box-shadow: var(--erp-shadow);
}
.inst-form-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 18px; border-bottom: 1px solid var(--erp-border); background: #f5f7f9;
}
.inst-form-title { display: flex; align-items: center; gap: 8px; color: var(--erp-navy-dark); font-size: .82rem; font-weight: 650; }
.inst-form-body { padding: 20px; }

/* Form Controls */
.inst-form-panel .form-label { 
    color: #536575; font-size: .65rem; font-weight: 700; 
    text-transform: uppercase; letter-spacing: .045em; margin-bottom: 6px; 
}

.inst-form-panel .form-control {
    height: 38px; border: 1px solid var(--erp-border); border-radius: 4px !important;
    color: var(--erp-text); background: #fff; font-size: .8rem;
    box-shadow: none !important;
}

/* Standard Buttons */
.btn-erp-primary {
    height: 38px; background: var(--erp-navy); border: 1px solid var(--erp-navy);
    color: #fff; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center;
}
.btn-erp-primary:hover { background: var(--erp-navy-dark); border-color: var(--erp-navy-dark); color: #fff; }

.btn-erp-cancel {
    height: 38px; border: 1px solid #c8d2db; background: #fff;
    color: #596b7a; border-radius: 4px !important; font-size: .76rem; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
}
.btn-erp-cancel:hover { background: #f5f7f9; color: #334451; }

/* Dashboard Cards */
.erp-card {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 5px;
    box-shadow: var(--erp-shadow);
}

.erp-card-header {
    padding: 12px 18px;
    background: #f5f7f9;
    border-bottom: 1px solid var(--erp-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* Metric Widgets */
.metric-card {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 5px;
    padding: 18px;
    box-shadow: var(--erp-shadow);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
}

.metric-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.metric-icon {
    width: 36px;
    height: 36px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.metric-label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .045em;
    color: var(--erp-muted);
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--erp-navy-dark);
}

.badge-erp {
    font-size: 0.67rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    display: inline-block;
}

.badge-erp-active { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.badge-erp-neutral { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

/* Call-to-action Banner */
.action-card {
    background: linear-gradient(135deg, var(--erp-navy-dark) 0%, var(--erp-navy) 100%);
    border: 1px solid var(--erp-navy-dark);
    border-radius: 5px;
    padding: 20px;
    color: #ffffff;
    box-shadow: var(--erp-shadow);
}

/* Detail Card Styling */
.detail-card {
    background: #ffffff;
    border: 1px solid var(--erp-border);
    border-radius: 5px;
    padding: 16px;
    height: 100%;
    box-shadow: var(--erp-shadow);
}

.code-chip {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}

/* Dark Mode Overrides */
[data-bs-theme="dark"] {
    --erp-bg: #101a24;
    --erp-white: #172534;
    --erp-text: #edf3f7;
    --erp-muted: #9aabb9;
    --erp-border: #2d3e4e;
    --erp-navy: #8eafc9;
    --erp-navy-dark: #dce8f0;
}
[data-bs-theme="dark"] .inst-header h3 { color: #edf3f7; }
[data-bs-theme="dark"] .inst-header-icon { background: #203445; border-color: #33495a; color: #b8d0e2; }
[data-bs-theme="dark"] .inst-form-panel, 
[data-bs-theme="dark"] .inst-form-header,
[data-bs-theme="dark"] .erp-card,
[data-bs-theme="dark"] .erp-card-header,
[data-bs-theme="dark"] .metric-card,
[data-bs-theme="dark"] .detail-card { background: #142230 !important; }
[data-bs-theme="dark"] .inst-form-panel .form-control { background: #172534 !important; color: var(--erp-text); border-color: var(--erp-border); }
[data-bs-theme="dark"] .btn-erp-cancel { background: #172534; border-color: var(--erp-border); color: #b8c6d1; }
[data-bs-theme="dark"] .metric-value { color: #edf3f7; }
[data-bs-theme="dark"] .code-chip { background-color: #1e2d3d; color: #cbd5e1; border-color: #33495a; }
</style>

<div class="service-dashboard-page">

    <!-- PAGE HEADER -->
    <div class="inst-header">
        <div class="inst-header-left">
            <div class="inst-header-icon">
                <i class="bi bi-speedometer2"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($page_title) ?></h3>
                <p>Overview of recorded maintenance operations, billing search, and service expenditures.</p>
            </div>
        </div>
        <a href="add_service.php" class="btn btn-erp-primary px-3">
            <i class="bi bi-plus-lg me-1"></i> Add Service Record
        </a>
    </div>

    <!-- SEARCH PANEL -->
    <div class="inst-form-panel">
        <div class="inst-form-header">
            <div class="inst-form-title">
                <i class="bi bi-search text-primary me-1"></i> Find a Service Record
            </div>
            <!-- <span class="badge-erp badge-erp-neutral">Bill Query Engine</span> -->
        </div>
        <div class="inst-form-body">
            <form method="GET" action="index.php">
                <div class="row g-3 align-items-end">
                    <div class="col-md-9 col-lg-10">
                        <label class="form-label">Bill Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted" style="border-color: var(--erp-border); border-radius: 4px 0 0 4px;">
                                <i class="bi bi-receipt"></i>
                            </span>
                            <input type="text" name="bill_no" class="form-control border-start-0" 
                                   placeholder="Enter exact Bill Number (e.g. 2024/001)..."
                                   value="<?= isset($_GET['bill_no']) ? htmlspecialchars($_GET['bill_no']) : '' ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <button type="submit" class="btn btn-erp-primary w-100">
                            <i class="bi bi-search me-1"></i> Search Bill
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- SEARCH RESULTS SECTION -->
    <?php if ($search_result !== null): ?>
        <div class="erp-card mb-4">
            <div class="erp-card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-card-checklist text-primary"></i>
                    <span class="fw-semibold text-dark fs-6" style="font-size: .85rem;">Search Results</span>
                </div>
                <span class="text-muted small">Query: <strong class="code-chip"><?= htmlspecialchars($_GET['bill_no']) ?></strong></span>
            </div>
            <div class="p-3">
                <?php if ($search_result->num_rows > 0): ?>
                    <?php $bill_no_val = $_GET['bill_no']; ?>

                    <?php if ($action == ''): ?>
                        <div class="d-flex align-items-center justify-content-between p-3 border rounded bg-light">
                            <div class="d-flex align-items-center gap-3">
                                <div class="inst-header-icon bg-white">
                                    <i class="bi bi-file-earmark-medical text-primary"></i>
                                </div>
                                <div>
                                    <span class="text-muted small fw-bold text-uppercase d-block">Matching Bill Record</span>
                                    <span class="fw-bold fs-6 text-dark"><?= htmlspecialchars($bill_no_val); ?></span>
                                </div>
                            </div>
                            <a href="index.php?bill_no=<?= urlencode($bill_no_val); ?>&action=view" class="btn btn-erp-primary px-3">
                                <i class="bi bi-eye me-1"></i> View Details
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($action == 'view'): ?>
                        <div class="row g-3">
                            <?php while($row = $search_result->fetch_assoc()): ?>
                                <div class="col-md-6 col-xl-4">
                                    <div class="detail-card">
                                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                            <span class="badge-erp badge-erp-neutral">SERVICE ENTRY</span>
                                            <span class="fw-bold text-success fs-6">₹<?= number_format((float)$row['amount'], 2); ?></span>
                                        </div>
                                        
                                        <div class="mb-2 d-flex justify-content-between align-items-center">
                                            <span class="text-muted small">Vendor:</span>
                                            <span class="fw-semibold text-dark small"><?= htmlspecialchars($row['vendor_name']); ?></span>
                                        </div>

                                        <div class="mb-3 d-flex justify-content-between align-items-center">
                                            <span class="text-muted small">Service Date:</span>
                                            <span class="fw-semibold text-dark small"><?= date("d M Y", strtotime($row['service_date'])); ?></span>
                                        </div>

                                        <div class="pt-2 border-top">
                                            <span class="text-muted small fw-bold d-block mb-1 text-uppercase" style="font-size: .65rem;">Item Description</span>
                                            <p class="text-secondary mb-0 small" style="line-height: 1.4;"><?= htmlspecialchars($row['item_name']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="mt-3">
                            <a href="index.php?bill_no=<?= urlencode($bill_no_val); ?>" class="btn btn-erp-cancel px-3">
                                <i class="bi bi-arrow-left me-1"></i> Back to Query Result
                            </a>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-exclamation-circle fs-3 d-block mb-2 opacity-50"></i>
                        <p class="mb-0 small fw-medium">No service records found matching bill number <strong><?= htmlspecialchars($_GET['bill_no']) ?></strong>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- STATS OVERVIEW SECTION -->
    <?php if ($action != 'view'): ?>
        <div class="row g-3">
            <div class="col-md-6 col-xl-4">
                <div class="metric-card">
                    <div>
                        <div class="metric-header">
                            <div class="metric-icon" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;">
                                <i class="bi bi-gear-wide-connected"></i>
                            </div>
                            <span class="badge-erp badge-erp-active">Operational</span>
                        </div>
                        <div class="metric-label">Total Services Logged</div>
                        <div class="metric-value"><?= number_format($total_services); ?></div>
                    </div>
                    <div class="text-muted small mt-3" style="font-size: .72rem;">
                        <i class="bi bi-info-circle me-1"></i> Accumulated maintenance entries
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="metric-card">
                    <div>
                        <div class="metric-header">
                            <div class="metric-icon" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
                                <i class="bi bi-currency-rupee"></i>
                            </div>
                            <span class="badge-erp badge-erp-neutral">Financial</span>
                        </div>
                        <div class="metric-label">Total Expenditure</div>
                        <div class="metric-value">₹<?= number_format((float)$total_amount, 2); ?></div>
                    </div>
                    <div class="text-muted small mt-3" style="font-size: .72rem;">
                        <i class="bi bi-calculator me-1"></i> Gross sum of service bills
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-xl-4">
                <div class="action-card d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-pc-display fs-5"></i>
                            <h6 class="mb-0 fw-bold">IT Equipment Maintenance</h6>
                        </div>
                        <p class="small text-white-50 mb-3" style="font-size: .76rem; line-height: 1.4;">
                            Register service details, costs, and vendor logs for Computers, Printers, or peripherals.
                        </p>
                    </div>
                    <a href="add_service.php" class="btn btn-light btn-sm fw-bold text-dark w-100" style="font-size: .76rem; border-radius: 4px;">
                        <i class="bi bi-plus-circle me-1"></i> Register New Entry
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
//$conn->close();
include "layout.php";
?>