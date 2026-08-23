<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/session.php";

$role = $_SESSION['role'] ?? '';
?>

<style>
:root {
    --brand-primary: #123b63;
    --brand-navy: #0b2942;
    --brand-white: #ffffff;
    --bg-surface: #f3f5f7;
    --card-bg: #ffffff;
    --card-border: #d9e0e7;
    --card-border-hover: #b8c5d1;
    --text-primary: #18344d;
    --text-body: #4b5f72;
    --text-muted: #6b7c8c;
    --shadow-subtle: 0 1px 2px rgba(20, 45, 70, 0.06);
    --shadow-hover: 0 4px 12px rgba(20, 45, 70, 0.10);
    --transition-smooth: all 0.18s ease;
}

.dashboard-wrapper {
    padding: 26px 0 36px;
    background: var(--bg-surface);
    min-height: 100vh;
}

.dashboard-wrapper .container {
    max-width: 1320px;
}

/* Institutional ERP module card */
.elite-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 6px;
    padding: 20px 20px 18px;
    min-height: 218px;
    height: 100%;
    position: relative;
    transition: var(--transition-smooth);
    text-decoration: none !important;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-subtle);
    overflow: hidden;
}

/* Formal module identification strip */
.elite-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--card-accent, var(--brand-primary));
    transition: var(--transition-smooth);
}

.elite-card:hover {
    transform: translateY(-2px);
    border-color: var(--card-border-hover);
    box-shadow: var(--shadow-hover);
}

.elite-card:hover::before {
    width: 5px;
    background: var(--card-accent, var(--brand-primary));
}

/* Module header */
.card-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 17px;
    padding-left: 3px;
}

.icon-wrapper {
    width: 42px;
    height: 42px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    background: var(--soft-bg, #eef3f7);
    color: var(--card-accent, var(--brand-primary));
    border: 1px solid rgba(18, 59, 99, 0.08);
    transition: var(--transition-smooth);
}

.elite-card:hover .icon-wrapper {
    background: var(--soft-bg, #eef3f7);
    color: var(--card-accent, var(--brand-primary));
}

/* Module category */
.status-badge {
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.035em;
    text-transform: uppercase;
    color: var(--text-muted);
    background: #f6f8fa;
    padding: 5px 9px;
    border-radius: 3px;
    border: 1px solid var(--card-border);
    white-space: nowrap;
}

/* Typography */
.elite-card h5 {
    font-weight: 650;
    color: var(--text-primary);
    font-size: 1.04rem;
    margin-bottom: 8px;
    letter-spacing: 0;
    line-height: 1.35;
    padding-left: 3px;
}

.elite-card p {
    color: var(--text-body);
    font-size: 0.875rem;
    line-height: 1.6;
    margin-bottom: 20px;
    padding-left: 3px;
}

/* Conventional ERP action area */
.card-action-btn {
    margin-top: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    color: var(--card-accent, var(--brand-primary));
    background: #f8fafb;
    padding: 9px 11px;
    border-radius: 4px;
    border: 1px solid var(--card-border);
    transition: var(--transition-smooth);
}

.elite-card:hover .card-action-btn {
    background: #f3f6f9;
    color: var(--card-accent, var(--brand-primary));
    border-color: var(--card-border-hover);
}

.card-action-btn i {
    font-size: 0.78rem;
    transition: transform 0.18s ease;
}

.elite-card:hover .card-action-btn i {
    transform: translateX(2px);
}

/* Module accent categories */
.accent-blue {
    --card-accent: #1b5a8a;
    --soft-bg: #edf4f9;
}

.accent-navy {
    --card-accent: #123b63;
    --soft-bg: #edf2f7;
}

.accent-slate {
    --card-accent: #536b80;
    --soft-bg: #f0f3f5;
}
.furniture-icon svg {
    width: 35px;
    height: 35px;
}

.accent-emerald {
    --card-accent: #3f755e;
    --soft-bg: #eef5f1;
}

/* Dark mode integration */
[data-bs-theme="dark"] {
    --bg-surface: #101a24;
    --card-bg: #172534;
    --card-border: #2c3d4d;
    --card-border-hover: #42576a;
    --text-primary: #eef3f7;
    --text-body: #c4d0da;
    --text-muted: #9eacb8;
    --shadow-subtle: 0 1px 2px rgba(0, 0, 0, 0.22);
    --shadow-hover: 0 5px 14px rgba(0, 0, 0, 0.30);
}

[data-bs-theme="dark"] .status-badge {
    background: #1d2d3d;
    border-color: var(--card-border);
}

[data-bs-theme="dark"] .card-action-btn {
    background: #1b2b3a;
    border-color: var(--card-border);
    color: #d8e3eb;
}

[data-bs-theme="dark"] .elite-card:hover .card-action-btn {
    background: #203344;
    border-color: var(--card-border-hover);
    color: #eef3f7;
}

/* Responsive institutional layout */
@media (max-width: 991.98px) {
    .dashboard-wrapper {
        padding: 22px 0 30px;
    }

    .elite-card {
        min-height: 210px;
    }
}

@media (max-width: 575.98px) {
    .dashboard-wrapper {
        padding: 16px 0 24px;
    }

    .dashboard-wrapper .row {
        --bs-gutter-y: 1rem;
    }

    .elite-card {
        min-height: 205px;
        padding: 18px 18px 16px;
    }

    .status-badge {
        font-size: 0.63rem;
        padding: 4px 7px;
    }
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
                    <p>Maintain institutional records, including institutions, departments, laboratories and facilities.</p>
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
                <a href="/cecsms/services/index.php" class="elite-card accent-navy">
                    <div class="card-header-row">
                        <!-- <div class="icon-wrapper"><i class="bi bi-cpu-fill"></i></div> -->
                         <div class="icon-wrapper service-icon">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 64 64"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true">

                                <!-- Desktop monitor -->
                                <rect x="5" y="9" width="28" height="20" rx="2"/>

                                <!-- Monitor screen -->
                                <rect x="8" y="12" width="22" height="14" rx="1"/>

                                <!-- Monitor stand -->
                                <path d="M16 29v5"/>
                                <path d="M12 34h14"/>

                                <!-- Desktop CPU -->
                                <rect x="7" y="39" width="13" height="16" rx="1.5"/>
                                <circle cx="13.5" cy="44" r="1.2"/>
                                <path d="M10 49h7"/>
                                <path d="M10 52h5"/>

                                <!-- Printer -->
                                <path d="M35 35h18
                                        C55 35 57 37 57 39
                                        V49
                                        H35
                                        V39
                                        C35 37 36 35 38 35Z"/>

                                <!-- Printer top paper -->
                                <path d="M40 30h10v5H40z"/>

                                <!-- Printer output paper -->
                                <path d="M40 49v7h10v-7"/>

                                <!-- Printer control light -->
                                <circle cx="52" cy="39" r="1"/>

                                <!-- Service wrench -->
                                <path d="M44 17
                                        C42 15 42 12 44 10
                                        C45 9 47 9 48 10
                                        L45 13
                                        L48 16
                                        L51 13
                                        C52 14 52 17 50 19
                                        C48 21 45 21 43 19
                                        L37 25"/>

                                <!-- Wrench handle -->
                                <path d="M37 25l-3 3"/>

                            </svg>
                        </div>
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
                        <div class="icon-wrapper"><i class="bi bi-pc-display"></i></div>
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
                        <!-- <div class="icon-wrapper"><i class="bi bi-table"></i></div> -->
                         <div class="icon-wrapper furniture-icon">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 64 64"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true">
                                
                                <path d="M10 22h40"/>
                                <path d="M14 22v27"/>
                                <path d="M46 22v27"/>

                                <path d="M37 29v13"/>
                                <path d="M37 29h10"/>
                                <path d="M47 29v13"/>

                                <path d="M34 42h16"/>
                                <path d="M37 42v10"/>
                                <path d="M47 42v10"/>

                                <path d="M14 43h32"/>
                            </svg>
                        </div>
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
                        <!-- <div class="icon-wrapper"><i class="bi bi-plug-fill"></i></div> -->
                         <div class="icon-wrapper electrical-icon">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 64 64"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true">

                                <!-- Ceiling canopy -->
                                <path d="M27 5h10"/>
                                <path d="M27 5c0 3 2 5 5 5s5-2 5-5"/>
                                
                                <!-- Down rod -->
                                <path d="M31 10v10"/>
                                <path d="M33 10v10"/>

                                <!-- Fan motor housing -->
                                <path d="M25 20
                                        C25 18 27 17 32 17
                                        C37 17 39 18 39 20
                                        V25
                                        C39 28 36 30 32 30
                                        C28 30 25 28 25 25Z"/>

                                <!-- Fan lower motor -->
                                <path d="M27 25
                                        C27 28 29 30 32 30
                                        C35 30 37 28 37 25"/>

                                <!-- Center cap -->
                                <ellipse cx="32" cy="29" rx="4" ry="2"/>

                                <!-- Fan blade - top left -->
                                <path d="M27 21
                                        C22 18 16 14 11 15
                                        C9 16 9 18 11 19
                                        C16 22 22 23 27 23"/>

                                <!-- Fan blade - top right -->
                                <path d="M37 21
                                        C42 18 48 14 53 15
                                        C55 16 55 18 53 19
                                        C48 22 42 23 37 23"/>

                                <!-- Fan blade - bottom left -->
                                <path d="M28 26
                                        C23 29 17 34 16 39
                                        C16 41 18 42 20 40
                                        C24 37 27 32 30 27"/>

                                <!-- Fan blade - bottom right -->
                                <path d="M36 26
                                        C41 29 47 34 48 39
                                        C48 41 46 42 44 40
                                        C40 37 37 32 34 27"/>

                                <!-- Tube light -->
                                <path d="M13 48h38"/>

                                <!-- Tube light body -->
                                <rect x="15" y="45" width="34" height="6" rx="2"/>

                                <!-- Left end cap -->
                                <path d="M15 45h-2v6h2"/>
                                <path d="M13 46h-2"/>
                                <path d="M13 50h-2"/>

                                <!-- Right end cap -->
                                <path d="M49 45h2v6h-2"/>
                                <path d="M51 46h2"/>
                                <path d="M51 50h2"/>

                            </svg>
                        </div>
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