<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/session.php";

$role = $_SESSION['role'] ?? '';
?>

<style>
:root {
    --brand-primary: #0d6efd;
    --brand-navy: #07116e;
    --brand-white: #ffffff;
    --bg-surface: #f8fafc;
    --card-bg: #ffffff;
    --card-border: #e2e8f0;
    --card-border-hover: #cbd5e1;
    --text-primary: #07116e;
    --text-body: #334155;
    --text-muted: #64748b;
    --shadow-subtle: 0 1px 3px rgba(7, 17, 110, 0.05);
    --shadow-hover: 0 12px 24px -6px rgba(7, 17, 110, 0.12), 0 4px 8px -4px rgba(13, 110, 253, 0.06);
    --transition-smooth: all 0.25s ease-in-out;
}

.dashboard-wrapper {
    padding: 40px 0;
    background-color: var(--bg-surface);
    min-height: 100vh;
}

/* Executive Card Component */
.elite-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 28px;
    height: 100%;
    position: relative;
    transition: var(--transition-smooth);
    text-decoration: none !important;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-subtle);
    overflow: hidden;
}

/* Accent Indicator Bar */
.elite-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--card-accent, var(--brand-navy));
    transition: var(--transition-smooth);
}

.elite-card:hover {
    transform: translateY(-4px);
    border-color: var(--card-border-hover);
    box-shadow: var(--shadow-hover);
}

.elite-card:hover::before {
    height: 4px;
    background: var(--card-accent, var(--brand-primary));
}

/* Header Elements */
.card-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    background: var(--soft-bg, rgba(13, 110, 253, 0.08));
    color: var(--card-accent, var(--brand-primary));
    transition: var(--transition-smooth);
}

.elite-card:hover .icon-wrapper {
    background: var(--card-accent, var(--brand-primary));
    color: var(--brand-white);
}

.status-badge {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--text-muted);
    background: var(--bg-surface);
    padding: 4px 10px;
    border-radius: 6px;
    border: 1px solid var(--card-border);
}

/* Typography */
.elite-card h5 {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 1.15rem;
    margin-bottom: 8px;
    letter-spacing: -0.01em;
}

.elite-card p {
    color: var(--text-body);
    font-size: 0.9rem;
    line-height: 1.55;
    margin-bottom: 24px;
}

/* Action CTA Button */
.card-action-btn {
    margin-top: auto;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--card-accent, var(--brand-primary));
    background: var(--soft-bg, rgba(13, 110, 253, 0.06));
    padding: 10px 16px;
    border-radius: 8px;
    border: 1px solid var(--card-border);
    transition: var(--transition-smooth);
}

.card-action-btn i {
    font-size: 0.85rem;
    transition: transform 0.2s ease;
}

.elite-card:hover .card-action-btn {
    background: var(--card-accent, var(--brand-primary));
    color: var(--brand-white);
    border-color: var(--card-accent, var(--brand-primary));
}

.elite-card:hover .card-action-btn i {
    transform: translateX(4px);
}

/* THEME ACCENT CATEGORIES */
.accent-blue { --card-accent: #0d6efd; --soft-bg: rgba(13, 110, 253, 0.08); }
.accent-navy { --card-accent: #07116e; --soft-bg: rgba(7, 17, 110, 0.08); }
.accent-slate { --card-accent: #475569; --soft-bg: rgba(71, 85, 105, 0.08); }
.accent-emerald { --card-accent: #059669; --soft-bg: rgba(5, 150, 105, 0.08); } /* Eco-Green Accent */

/* Dark Mode Integration */
[data-bs-theme="dark"] {
    --bg-surface: #040938;
    --card-bg: #07116e;
    --card-border: rgba(255, 255, 255, 0.12);
    --card-border-hover: rgba(13, 110, 253, 0.4);
    --text-primary: #ffffff;
    --text-body: #cbd5e1;
    --text-muted: #94a3b8;
    --shadow-subtle: 0 2px 4px rgba(0, 0, 0, 0.25);
}

[data-bs-theme="dark"] .status-badge {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.15);
}

[data-bs-theme="dark"] .card-action-btn {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.15);
}

[data-bs-theme="dark"] .elite-card:hover .card-action-btn {
    background: var(--card-accent, var(--brand-primary));
    border-color: var(--card-accent, var(--brand-primary));
}
</style>

<div class="dashboard-wrapper">
    <div class="container">
        <div class="row g-4">

            <?php if(in_array($role, ['Admin', 'SuperAdmin'])): ?>
            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/users/manage_users.php" class="elite-card accent-blue">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-people-fill"></i></div>
                        <span class="status-badge">System Access</span>
                    </div>
                    <h5>User Management</h5>
                    <p>Administer access levels, roles, and security protocols for system users.</p>
                    <div class="card-action-btn">
                        <span>Manage Access</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>
            <?php endif; ?>

            <?php if(in_array($role, ['SuperAdmin'])): ?>
            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/master/master_dashboard.php" class="elite-card accent-navy">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-layers-half"></i></div>
                        <span class="status-badge">Core Setup</span>
                    </div>
                    <h5>Master Data</h5>
                    <p>Control the core database, including categories, units, and institutions.</p>
                    <div class="card-action-btn">
                        <span>Configure</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/vendors/vendor_dashboard.php" class="elite-card accent-blue">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-person-vcard"></i></div>
                        <span class="status-badge">Suppliers</span>
                    </div>
                    <h5>Vendor Management</h5>
                    <p>Manage official supplier profiles, contact information, and partner directory records.</p>
                    <div class="card-action-btn">
                        <span>View Directory</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/services/index.php" class="elite-card accent-slate">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-cpu-fill"></i></div>
                        <span class="status-badge">Maintenance</span>
                    </div>
                    <h5>Services</h5>
                    <p>Track maintenance cycles, service logs, and vendor performance records.</p>
                    <div class="card-action-btn">
                        <span>Open Records</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>
            <?php endif; ?>

            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/stock/<?= ($role === 'SuperAdmin') ? 'dashboard.php' : '../divisions/division_dashboard.php'; ?>" class="elite-card accent-blue">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-laptop"></i></div>
                        <span class="status-badge">Hardware</span>
                    </div>
                    <h5>Computer Stock</h5>
                    <p>Real-time tracking of hardware assets, serial numbers, and availability.</p>
                    <div class="card-action-btn">
                        <span>Inventory</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/furniture_stock/furniture_dashboard.php" class="elite-card accent-navy">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-grid-1x2"></i></div>
                        <span class="status-badge">Assets</span>
                    </div>
                    <h5>Furniture Stock</h5>
                    <p>Asset management for office equipment and laboratory furniture.</p>
                    <div class="card-action-btn">
                        <span>Inventory</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/electrical_stock/electricals_dashboard.php" class="elite-card accent-blue">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-plug-fill"></i></div>
                        <span class="status-badge">Equipment</span>
                    </div>
                    <h5>Electricals</h5>
                    <p>Asset management for electrical equipment including lights and fans.</p>
                    <div class="card-action-btn">
                        <span>Inventory</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <?php if(in_array($role, ['Admin', 'SuperAdmin'])): ?>
            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/ewaste/ewaste_dashboard.php" class="elite-card accent-emerald">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-recycle"></i></div>
                        <span class="status-badge">Disposal</span>
                    </div>
                    <h5>E-Waste</h5>
                    <p>Handle decommissioned assets and environment-friendly disposal tracking.</p>
                    <div class="card-action-btn">
                        <span>Manage Disposal</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>