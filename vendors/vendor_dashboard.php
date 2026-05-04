<?php
session_start();
if (!isset($_SESSION["user_id"])) { header("Location: login.php"); exit(); }
require_once __DIR__ . "/../config/db.php";

$page_title = "Vendor Intelligence Dashboard";

// 1. Unified Metrics Query
$metrics_query = "
    SELECT 
        (SELECT COUNT(*) FROM vendors) as total_vendors,
        (SELECT COUNT(DISTINCT vendor_id) FROM (
            SELECT vendor_id FROM stock_details 
            UNION SELECT vendor_id FROM furniture_stock 
            UNION SELECT vendor_id FROM electrical_stock
        ) as active) as active_vendors,
        (SELECT 
            COALESCE(SUM(amount), 0) FROM stock_details) + 
            (SELECT COALESCE(SUM(total_qty * unit_price), 0) FROM furniture_stock) + 
            (SELECT COALESCE(SUM(total_qty * unit_price), 0) FROM electrical_stock
        ) as grand_total_spend";
$metrics = $conn->query($metrics_query)->fetch_assoc();

// 2. Performance Data
$perf_query = "
    SELECT 
        v.id, v.vendor_name, v.category, v.phone_number,
        (COALESCE((SELECT SUM(amount) FROM stock_details WHERE vendor_id = v.id), 0) + 
         COALESCE((SELECT SUM(total_qty * unit_price) FROM furniture_stock WHERE vendor_id = v.id), 0) + 
         COALESCE((SELECT SUM(total_qty * unit_price) FROM electrical_stock WHERE vendor_id = v.id), 0)) as total_spend,
        ((SELECT COUNT(*) FROM stock_details WHERE vendor_id = v.id) + 
         (SELECT COUNT(*) FROM furniture_stock WHERE vendor_id = v.id) + 
         (SELECT COUNT(*) FROM electrical_stock WHERE vendor_id = v.id)) as total_orders
    FROM vendors v
    ORDER BY total_spend DESC LIMIT 10";
$perf_result = $conn->query($perf_query);

ob_start();
?>

<style>
    :root {
        --saas-primary: #4F46E5;
        --saas-bg: #F8FAFC;
        --saas-card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
    }

    body { background-color: var(--saas-bg); font-family: 'Inter', sans-serif; }
    
    .stats-card {
        background: #ffffff;
        border: 1px solid #F1F5F9;
        border-radius: 16px;
        padding: 1.5rem;
        transition: all 0.3s ease;
    }

    .stats-card:hover { box-shadow: var(--saas-card-shadow); border-color: var(--saas-primary); }

    .vendor-table-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #F1F5F9;
        overflow: hidden;
    }

    .table thead th {
        background-color: #F8FAFC;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        color: #64748B;
        padding: 1rem 1.5rem;
        border: none;
    }

    .table tbody td { padding: 1.2rem 1.5rem; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }

    .badge-category {
        background: #EEF2FF;
        color: #4338CA;
        font-weight: 600;
        padding: 0.5em 1em;
        border-radius: 8px;
    }

    .action-tile {
        background: #ffffff;
        border: 1px solid #F1F5F9;
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #1E293B;
        transition: 0.2s;
    }

    .action-tile:hover { background: #F1F5F9; color: var(--saas-primary); transform: translateX(5px); }
    
    .text-gradient {
        background: linear-gradient(45deg, #4F46E5, #9333EA);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
</style>

<div class="container-fluid py-4 px-lg-5">
    <!-- Hero Header -->
    <div class="row align-items-center mb-5">
        <div class="col-md-6">
            <h2 class="fw-bold tracking-tight text-dark">Intelligence <span class="text-gradient">Hub</span></h2>
            <p class="text-secondary">Global overview of vendor performance and procurement lifecycle.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <button class="btn btn-dark shadow-sm rounded-3 px-4 py-2 me-2">
                <i class="bi bi-download me-2"></i>Export Report
            </button>
            <a href="vendor_manager.php" class="btn btn-primary shadow-sm rounded-3 px-4 py-2">
                <i class="bi bi-plus-lg me-2"></i>Add Vendor
            </a>
        </div>
    </div>

    <!-- Metrics Grid -->
    <div class="row g-4 mb-5">
        <div class="col-lg-4">
            <div class="stats-card">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-secondary small fw-bold uppercase">Total Partners</span>
                    <i class="bi bi-people text-primary fs-5"></i>
                </div>
                <h2 class="fw-bold mb-1"><?= number_format($metrics['total_vendors']) ?></h2>
                <span class="text-success small fw-medium"><i class="bi bi-arrow-up-short"></i> 12% from last month</span>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="stats-card">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-secondary small fw-bold uppercase">Active Procurement</span>
                    <i class="bi bi-activity text-success fs-5"></i>
                </div>
                <h2 class="fw-bold mb-1"><?= number_format($metrics['active_vendors']) ?></h2>
                <span class="text-muted small">Currently supplying stock</span>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="stats-card">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-secondary small fw-bold uppercase">Total Spend</span>
                    <i class="bi bi-credit-card text-info fs-5"></i>
                </div>
                <h2 class="fw-bold mb-1">₹<?= number_format($metrics['grand_total_spend'], 0) ?></h2>
                <span class="text-secondary small fw-medium">Lifetime value across all units</span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Table Section -->
        <div class="col-xl-8">
            <div class="vendor-table-card shadow-sm">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Top Performing Partners</h5>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm rounded-2 border" type="button" data-bs-toggle="dropdown">
                            Sort by: Spend <i class="bi bi-chevron-down ms-1"></i>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Vendor Identity</th>
                                <th>Category</th>
                                <th class="text-center">Order Vol.</th>
                                <th class="text-end">Spend Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($v = $perf_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3 bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <span class="fw-bold text-primary"><?= substr($v['vendor_name'], 0, 1) ?></span>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($v['vendor_name']) ?></div>
                                            <div class="text-muted small"><?= $v['phone_number'] ?: 'Contact missing' ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge-category"><?= $v['category'] ?></span></td>
                                <td class="text-center fw-medium text-dark"><?= $v['total_orders'] ?></td>
                                <td class="text-end">
                                    <div class="fw-bold text-dark">₹<?= number_format($v['total_spend'], 2) ?></div>
                                    <div class="progress mt-1" style="height: 4px; width: 60px; margin-left: auto;">
                                        <div class="progress-bar bg-primary" style="width: 75%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Sidebar Sidebar Actions -->
        <div class="col-xl-4">
            <h6 class="fw-bold text-uppercase small text-secondary mb-3">Quick Navigation</h6>
            <div class="d-flex flex-column gap-2 mb-4">
                <a href="view_vendors.php?type=Computer" class="action-tile shadow-sm">
                    <div class="icon-box me-3 bg-soft-primary p-2 rounded-3"><i class="bi bi-pc-display"></i></div>
                    <span class="fw-medium">Computer Hardware</span>
                </a>
                <a href="view_vendors.php?type=Furniture" class="action-tile shadow-sm">
                    <div class="icon-box me-3 bg-soft-primary p-2 rounded-3"><i class="bi bi-lamp"></i></div>
                    <span class="fw-medium">Furniture & Decor</span>
                </a>
                <a href="view_vendors.php?type=Electricals" class="action-tile shadow-sm">
                    <div class="icon-box me-3 bg-soft-primary p-2 rounded-3"><i class="bi bi-lightning"></i></div>
                    <span class="fw-medium">Electrical Supplies</span>
                </a>
            </div>

            <div class="card border-0 bg-primary text-white rounded-4 overflow-hidden">
                <div class="card-body p-4 position-relative">
                    <div style="z-index: 2; position: relative;">
                        <h5 class="fw-bold mb-2">Service Logs</h5>
                        <p class="small opacity-75 mb-4">Monitor maintenance and repair history for assets linked to these vendors.</p>
                        <a href="service_manager.php" class="btn btn-light btn-sm fw-bold px-4 rounded-pill">Manage Services</a>
                    </div>
                    <!-- Decorative Circle -->
                    <div class="position-absolute" style="top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean();
include "../vendors/vendorlayout.php"; 
?>