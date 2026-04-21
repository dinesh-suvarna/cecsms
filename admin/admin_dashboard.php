<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/session.php";

$role = $_SESSION['role'] ?? '';
?>

<style>
/* Elite SaaS Variables */
:root {
    --bg-main: #f8fafc;
    --card-bg: #ffffff;
    --card-border: #e2e8f0;
    --text-main: #0f172a;
    --text-muted: #64748b;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dashboard-wrapper {
    padding: 40px 0;
    background-color: var(--bg-main);
    min-height: 100vh;
}

/* THE ELITE CARD */
.elite-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 28px;
    height: 100%;
    position: relative;
    transition: var(--transition);
    text-decoration: none !important;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.elite-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
    border-color: var(--accent-color);
}

/* HEADER AREA */
.card-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}

.icon-wrapper {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: var(--soft-bg);
    color: var(--accent-color);
    transition: var(--transition);
}

.elite-card:hover .icon-wrapper {
    background: var(--accent-color);
    color: white;
    transform: rotate(-5deg) scale(1.05);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #cbd5e1;
    transition: var(--transition);
}

.elite-card:hover .status-dot {
    background: var(--accent-color);
    box-shadow: 0 0 10px var(--accent-color);
}

/* CONTENT */
.elite-card h5 {
    font-weight: 700;
    color: var(--text-main);
    font-size: 1.15rem;
    margin-bottom: 10px;
    letter-spacing: -0.02em;
}

.elite-card p {
    color: var(--text-muted);
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 25px;
}

/* FOOTER ACTION */
.card-action {
    margin-top: auto;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--accent-color);
    opacity: 0.6;
    transition: var(--transition);
}

.elite-card:hover .card-action {
    opacity: 1;
    gap: 12px;
}

/* ACCENT PALETTES */
.accent-blue { --accent-color: #2563eb; --soft-bg: #eff6ff; }
.accent-indigo { --accent-color: #4f46e5; --soft-bg: #eef2ff; }
.accent-amber { --accent-color: #d97706; --soft-bg: #fffbeb; }
.accent-rose { --accent-color: #e11d48; --soft-bg: #fff1f2; }
.accent-emerald { --accent-color: #059669; --soft-bg: #ecfdf5; }
.accent-slate { --accent-color: #475569; --soft-bg: #f1f5f9; }

/* Dark Mode Overrides */
[data-bs-theme="dark"] .elite-card {
    background: #1e293b;
    border-color: #334155;
}
[data-bs-theme="dark"] .elite-card h5 { color: #f8fafc; }
[data-bs-theme="dark"] .dashboard-wrapper { background-color: #0f172a; }
</style>

<div class="dashboard-wrapper">
    <div class="container">
        <div class="row g-4">

            <?php if(in_array($role, ['Admin', 'SuperAdmin'])): ?>
            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/users/manage_users.php" class="elite-card accent-blue">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-people-fill"></i></div>
                        <div class="status-dot"></div>
                    </div>
                    <h5>User Management</h5>
                    <p>Administer access levels, roles, and security protocols for system users.</p>
                    <div class="card-action">Manage Access <i class="bi bi-arrow-right"></i></div>
                </a>
            </div>
            <?php endif; ?>

            <?php if(in_array($role, ['SuperAdmin'])): ?>
            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/master/master_dashboard.php" class="elite-card accent-indigo">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-layers-half"></i></div>
                        <div class="status-dot"></div>
                    </div>
                    <h5>Master Data</h5>
                    <p>Control the core database, including categories, units, and institutions.</p>
                    <div class="card-action">Configure <i class="bi bi-arrow-right"></i></div>
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/services/index.php" class="elite-card accent-slate">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-cpu-fill"></i></div>
                        <div class="status-dot"></div>
                    </div>
                    <h5>Services</h5>
                    <p>Track maintenance cycles, service logs, and vendor performance records.</p>
                    <div class="card-action">Open Records <i class="bi bi-arrow-right"></i></div>
                </a>
            </div>
            <?php endif; ?>

            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/stock/dashboard.php" class="elite-card accent-blue">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-laptop"></i></div>
                        <div class="status-dot"></div>
                    </div>
                    <h5>Computer Stock</h5>
                    <p>Real-time tracking of hardware assets, serial numbers, and availability.</p>
                    <div class="card-action">Inventory <i class="bi bi-arrow-right"></i></div>
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/furniture_stock/furniture_dashboard.php" class="elite-card accent-amber">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-grid-1x2"></i></div>
                        <div class="status-dot"></div>
                    </div>
                    <h5>Furniture Stock</h5>
                    <p>Asset management for office equipment and laboratory furniture.</p>
                    <div class="card-action">Inventory <i class="bi bi-arrow-right"></i></div>
                </a>
            </div>

            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/electrical_stock/electricals_dashboard.php" class="elite-card accent-rose">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-plug-fill"></i></div>
                        <div class="status-dot"></div>
                    </div>
                    <h5>Electricals</h5>
                    <p>Asset management for electrical equipment including lights and fans.</p>
                    <div class="card-action">Inventory <i class="bi bi-arrow-right"></i></div>
                </a>
            </div>

            <?php if(in_array($role, ['Admin', 'SuperAdmin'])): ?>
            <div class="col-lg-4 col-md-6">
                <a href="/cecsms/ewaste/index.php" class="elite-card accent-emerald">
                    <div class="card-header-row">
                        <div class="icon-wrapper"><i class="bi bi-recycle"></i></div>
                        <div class="status-dot"></div>
                    </div>
                    <h5>E-Waste</h5>
                    <p>Handle decommissioned assets and environment-friendly disposal tracking.</p>
                    <div class="card-action">Manage Disposal <i class="bi bi-arrow-right"></i></div>
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>