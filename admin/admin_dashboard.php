<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/session.php";

$role = $_SESSION['role'] ?? '';
?>

<style>
/* Modern SaaS Variables */
:root {
    --glass-bg: rgba(255, 255, 255, 0.8);
    --card-border: #f1f5f9;
    --text-main: #1e293b;
    --text-muted: #64748b;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dashboard-wrapper {
    padding: 30px 0;
    background-color: #f8fafc; /* Very light SaaS grey */
}

/* THE CARD DESIGN */
.saas-card {
    background: #ffffff;
    border: 1px solid var(--card-border);
    border-radius: 24px; /* Softer corners */
    padding: 2rem 1.5rem;
    height: 100%;
    position: relative;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    overflow: hidden;
}

/* Floating shadow on hover */
.saas-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04);
    border-color: var(--accent-color);
}

/* Subtle Gradient Background Effect */
.saas-card::after {
    content: "";
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    background: radial-gradient(circle at top right, var(--soft-bg), transparent 70%);
    opacity: 0;
    transition: var(--transition);
    z-index: 0;
}

.saas-card:hover::after {
    opacity: 1;
}

/* ICON CONTAINER */
.icon-box {
    width: 70px;
    height: 70px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    background: var(--soft-bg);
    color: var(--accent-color);
    margin-bottom: 1.5rem;
    z-index: 1;
    transition: var(--transition);
}

.saas-card:hover .icon-box {
    transform: scale(1.1) rotate(-5deg);
    background: var(--accent-color);
    color: white;
}

/* TYPOGRAPHY */
.saas-card h5 {
    font-weight: 800;
    color: var(--text-main);
    font-size: 1.15rem;
    margin-bottom: 0.5rem;
    z-index: 1;
}

.saas-card p {
    color: var(--text-muted);
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 1.8rem;
    z-index: 1;
}

/* MODERN BUTTON */
.saas-btn {
    margin-top: auto;
    background: #f1f5f9;
    color: var(--text-main);
    border: none;
    padding: 10px 24px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.85rem;
    width: 100%;
    transition: var(--transition);
    z-index: 1;
    text-decoration: none;
}

.saas-card:hover .saas-btn {
    background: var(--accent-color);
    color: white !important;
}

/* ACCENT SCHEMES */
.accent-blue { --accent-color: #3b82f6; --soft-bg: #eff6ff; }
.accent-indigo { --accent-color: #6366f1; --soft-bg: #eef2ff; }
.accent-amber { --accent-color: #f59e0b; --soft-bg: #fffbeb; }
.accent-rose { --accent-color: #f43f5e; --soft-bg: #fff1f2; }
.accent-emerald { --accent-color: #10b981; --soft-bg: #ecfdf5; }
.accent-slate { --accent-color: #475569; --soft-bg: #f8fafc; }

/* Dark mode compatibility */
[data-bs-theme="dark"] .saas-card {
    background: #1e293b;
    border-color: #334155;
}
[data-bs-theme="dark"] .saas-card h5 { color: #f8fafc; }
[data-bs-theme="dark"] .saas-btn { background: #334155; color: #f8fafc; }
</style>

<div class="dashboard-wrapper">
    <div class="container">
        <div class="row g-4">

            <?php if(in_array($role, ['Admin', 'SuperAdmin'])): ?>
            <div class="col-lg-4 col-md-6">
                <div class="saas-card accent-blue">
                    <div class="icon-box"><i class="bi bi-people-fill"></i></div>
                    <h5>User Management</h5>
                    <p>Administer access levels, roles, and security protocols for system users.</p>
                    <a href="/cecsms/users/manage_users.php" class="saas-btn">Manage Users</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if(in_array($role, ['SuperAdmin'])): ?>
            <div class="col-lg-4 col-md-6">
                <div class="saas-card accent-indigo">
                    <div class="icon-box"><i class="bi bi-layers-half"></i></div>
                    <h5>Master Data</h5>
                    <p>Control the core database, including categories, units, and institutions.</p>
                    <a href="/cecsms/master/master_dashboard.php" class="saas-btn">Configure Data</a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="saas-card accent-slate">
                    <div class="icon-box"><i class="bi bi-cpu-fill"></i></div>
                    <h5>Services</h5>
                    <p>Track maintenance cycles, service logs, and vendor performance records.</p>
                    <a href="/cecsms/services/index.php" class="saas-btn">Open Records</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-lg-4 col-md-6">
                <div class="saas-card accent-blue">
                    <div class="icon-box"><i class="bi bi-laptop"></i></div>
                    <h5>Computer Stock</h5>
                    <p>Real-time tracking of hardware assets, serial numbers, and availability.</p>
                    <a href="/cecsms/stock/dashboard.php" class="saas-btn">Inventory</a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="saas-card accent-amber">
                    <div class="icon-box"><i class="bi bi-grid-1x2"></i></div>
                    <h5>Furniture Stock</h5>
                    <p>Asset management for office equipment and laboratory furniture.</p>
                    <a href="/cecsms/furniture/index.php" class="saas-btn">Inventory</a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="saas-card accent-rose">
                    <div class="icon-box"><i class="bi bi-plug-fill"></i></div>
                    <h5>Electronics</h5>
                    <p>Manage specialized electronic components and peripheral devices.</p>
                    <a href="/cecsms/electronics/index.php" class="saas-btn">Inventory</a>
                </div>
            </div>

            <?php if(in_array($role, ['Admin', 'SuperAdmin'])): ?>
            <div class="col-lg-4 col-md-6">
                <div class="saas-card accent-emerald">
                    <div class="icon-box"><i class="bi bi-recycle"></i></div>
                    <h5>E-Waste</h5>
                    <p>Handle decommissioned assets and environment-friendly disposal tracking.</p>
                    <a href="/cecsms/ewaste/index.php" class="saas-btn">Manage Disposal</a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>