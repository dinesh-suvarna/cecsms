<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/session.php";

$role = $_SESSION['role'] ?? '';

// Handle Session-based hiding of dismissible broadcasts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_announcement_id'])) {
    $dismiss_id = intval($_POST['dismiss_announcement_id']);
    if (!isset($_SESSION['dismissed_announcements'])) {
        $_SESSION['dismissed_announcements'] = [];
    }
    $_SESSION['dismissed_announcements'][] = $dismiss_id;
    
    // Maintain auto-open parameter if present, otherwise clean URL
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$dismissed_ids = $_SESSION['dismissed_announcements'] ?? [];

// Automatically expand broadcast viewer if freshly published
$auto_expand = isset($_GET['broadcast_published']) && $_GET['broadcast_published'] === '1';
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

/* Eye-Catching ERP Broadcast Styling */
.erp-announcement-card {
    border: 1px solid var(--card-border);
    border-radius: 6px;
    box-shadow: var(--shadow-subtle);
    background-color: var(--card-bg);
}
.erp-announcement-header {
    background-color: var(--brand-navy);
    color: var(--brand-white);
    border-top-left-radius: 5px;
    border-top-right-radius: 5px;
}
.erp-broadcast-btn {
    background-color: var(--brand-primary);
    color: var(--brand-white);
    border: 1px solid var(--brand-navy);
    font-weight: 500;
    transition: var(--transition-smooth);
}
.erp-broadcast-btn:hover {
    background-color: var(--brand-navy);
    color: var(--brand-white);
    box-shadow: var(--shadow-subtle);
}
.erp-btn-outline {
    background-color: var(--card-bg);
    color: var(--brand-primary);
    border: 1px solid var(--card-border);
    font-weight: 500;
    transition: var(--transition-smooth);
}
.erp-btn-outline:hover {
    background-color: #eef3f7;
    color: var(--brand-navy);
    border-color: var(--card-border-hover);
}

/* Modern Eye-Catching Alert Box */
.erp-alert-item-eye-catchy {
    background: linear-gradient(135deg, #ffffff 0%, #f4f8fc 100%);
    border: 1px solid #b8d3e8;
    border-left: 5px solid #0066cc !important;
    border-radius: 8px;
    padding: 14px 18px;
    box-shadow: 0 4px 14px rgba(0, 102, 204, 0.08);
    position: relative;
    transition: var(--transition-smooth);
}
.erp-alert-item-eye-catchy:hover {
    border-color: #70a6d4;
    box-shadow: 0 6px 18px rgba(0, 102, 204, 0.14);
}

/* Pulsing Beacon Dot */
.broadcast-pulse-dot {
    width: 9px;
    height: 9px;
    background-color: #0066cc;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 rgba(0, 102, 204, 0.4);
    animation: broadcastPulse 1.8s infinite;
}

@keyframes broadcastPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(0, 102, 204, 0.7);
    }
    70% {
        box-shadow: 0 0 0 8px rgba(0, 102, 204, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(0, 102, 204, 0);
    }
}

.erp-badge-role {
    background-color: var(--brand-navy);
    color: var(--brand-white);
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    padding: 4px 8px;
    border-radius: 4px;
}
.erp-timestamp {
    color: var(--text-muted);
    font-size: 0.75rem;
    white-space: nowrap;
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

        <!-- Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                <i class="bi bi-speedometer2 text-primary"></i> System Overview
            </h5>
            
            <div class="d-flex align-items-center gap-2">
                <!-- Show Broadcast Messages Button -->
                <button class="btn btn-sm erp-btn-outline d-flex align-items-center gap-2 px-3 py-2" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#announcementsDisplayCollapse" 
                        aria-expanded="<?= $auto_expand ? 'true' : 'false' ?>" 
                        aria-controls="announcementsDisplayCollapse">
                    <i class="bi bi-megaphone-fill text-primary"></i>
                    <span>Show Broadcast Messages</span>
                </button>

                <!-- Broadcast Update Button (SuperAdmin Only) -->
                <?php if ($role === 'SuperAdmin'): ?>
                <button class="btn btn-sm erp-broadcast-btn d-flex align-items-center gap-2 px-3 py-2" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#announcementFormCollapse" 
                        aria-expanded="false" 
                        aria-controls="announcementFormCollapse">
                    <i class="bi bi-broadcast"></i>
                    <span>Broadcast Update</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Collapsible Announcement Creation Form (SuperAdmin Only) -->
        <?php if ($role === 'SuperAdmin'): ?>
        <div class="collapse mb-4" id="announcementFormCollapse">
            <div class="card erp-announcement-card">
                <div class="card-header erp-announcement-header d-flex align-items-center justify-content-between py-2 px-3">
                    <span class="small fw-semibold"><i class="bi bi-megaphone me-2"></i> Create System Announcement</span>
                    <!-- <span class="badge bg-secondary text-light fw-normal" style="font-size: 0.68rem;">Developer Console</span> -->
                </div>
                <div class="card-body p-3">
                    <form action="/cecsms/includes/post_announcement.php" method="POST">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold small text-secondary">Target Audience</label>
                                <select name="target_role" class="form-select form-select-sm" required>
                                    <option value="Admin">Admins Only</option>
                                    <option value="All">All Users (Admin & Staff)</option>
                                    <option value="SuperAdmin">SuperAdmin / Dev Notes Only</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-semibold small text-secondary">Title / Reference</label>
                                <input type="text" name="title" class="form-control form-control-sm" placeholder="e.g., Database Migration / System Notice" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-secondary">Message Details</label>
                                <textarea name="message" class="form-control form-control-sm" rows="2" placeholder="Provide clear concise details regarding this deployment or update..." required></textarea>
                            </div>
                            <div class="col-12 text-end">
                                <button type="button" class="btn btn-sm btn-light border me-1" data-bs-toggle="collapse" data-bs-target="#announcementFormCollapse">Cancel</button>
                                <button type="submit" class="btn btn-sm erp-broadcast-btn">
                                    <i class="bi bi-send me-1"></i> Publish Announcement
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Collapsible Active Broadcast Messages Container -->
        <!-- Automatically opens ONLY when $auto_expand is true (i.e. fresh publish) -->
        <div class="collapse <?= $auto_expand ? 'show' : '' ?> mb-3" id="announcementsDisplayCollapse">
            <?php 
            require_once __DIR__ . "/../includes/announcement_fetcher.php";
            $announcements = get_role_announcements();
            $has_visible = false;

            if ($announcements && $announcements->num_rows > 0):
                while ($row = $announcements->fetch_assoc()):
                    $is_dismissed = in_array($row['id'], $dismissed_ids);
                    if (!$is_dismissed) {
                        $has_visible = true;
                    }
            ?>
                <!-- Eye-Catchy Broadcast Card -->
                <div class="erp-alert-item-eye-catchy d-flex align-items-center justify-content-between mb-2 <?= $is_dismissed ? 'opacity-50' : '' ?>">
                    <div class="d-flex align-items-center gap-3 overflow-hidden me-3">
                        <span class="broadcast-pulse-dot flex-shrink-0" title="Active Broadcast"></span>
                        <span class="erp-badge-role text-uppercase flex-shrink-0"><?= htmlspecialchars($row['sender_role']) ?></span>
                        <div class="text-truncate small text-dark">
                            <strong class="text-primary me-1"><?= htmlspecialchars($row['title']) ?>:</strong> 
                            <span class="text-body fw-medium"><?= htmlspecialchars($row['message']) ?></span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="erp-timestamp me-2"><i class="bi bi-clock me-1"></i><?= date('M d, H:i', strtotime($row['created_at'])) ?></span>
                        
                        <!-- Permanent Delete (SuperAdmin Only) -->
                        <?php if ($role === 'SuperAdmin'): ?>
                        <form action="/cecsms/includes/delete_announcement.php" method="POST" class="d-inline" onsubmit="return confirm('Permanently delete this announcement?');">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-link text-danger p-0 border-0 ms-1 opacity-75" title="Permanently Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- Session Hide / Dismiss -->
                        <?php if (!$is_dismissed): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="dismiss_announcement_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn-close small opacity-75 ms-1" aria-label="Close" title="Hide for this session"></button>
                        </form>
                        <?php else: ?>
                        <span class="badge bg-light text-muted border ms-1" style="font-size:0.65rem;">Dismissed</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php 
                endwhile;
            endif;

            if (!$has_visible && (!$announcements || $announcements->num_rows === 0)):
            ?>
                <div class="text-muted small italic p-3 text-center border rounded bg-white shadow-sm">
                    <i class="bi bi-info-circle text-primary me-1"></i> No broadcast announcements found.
                </div>
            <?php endif; ?>
        </div>

        <!-- Dashboard Module Grid -->
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
                         <div class="icon-wrapper service-icon">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 64 64"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true">
                                <rect x="5" y="9" width="28" height="20" rx="2"/>
                                <rect x="8" y="12" width="22" height="14" rx="1"/>
                                <path d="M16 29v5"/>
                                <path d="M12 34h14"/>
                                <rect x="7" y="39" width="13" height="16" rx="1.5"/>
                                <circle cx="13.5" cy="44" r="1.2"/>
                                <path d="M10 49h7"/>
                                <path d="M10 52h5"/>
                                <path d="M35 35h18 C55 35 57 37 57 39 V49 H35 V39 C35 37 36 35 38 35Z"/>
                                <path d="M40 30h10v5H40z"/>
                                <path d="M40 49v7h10v-7"/>
                                <circle cx="52" cy="39" r="1"/>
                                <path d="M44 17 C42 15 42 12 44 10 C45 9 47 9 48 10 L45 13 L48 16 L51 13 C52 14 52 17 50 19 C48 21 45 21 43 19 L37 25"/>
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
                         <div class="icon-wrapper electrical-icon">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 64 64"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true">
                                <path d="M27 5h10"/>
                                <path d="M27 5c0 3 2 5 5 5s5-2 5-5"/>
                                <path d="M31 10v10"/>
                                <path d="M33 10v10"/>
                                <path d="M25 20 C25 18 27 17 32 17 C37 17 39 18 39 20 V25 C39 28 36 30 32 30 C28 30 25 28 25 25Z"/>
                                <path d="M27 25 C27 28 29 30 32 30 C35 30 37 28 37 25"/>
                                <ellipse cx="32" cy="29" rx="4" ry="2"/>
                                <path d="M27 21 C22 18 16 14 11 15 C9 16 9 18 11 19 C16 22 22 23 27 23"/>
                                <path d="M37 21 C42 18 48 14 53 15 C55 16 55 18 53 19 C48 22 42 23 37 23"/>
                                <path d="M28 26 C23 29 17 34 16 39 C16 41 18 42 20 40 C24 37 27 32 30 27"/>
                                <path d="M36 26 C41 29 47 34 48 39 C48 41 46 42 44 40 C40 37 37 32 34 27"/>
                                <path d="M13 48h38"/>
                                <rect x="15" y="45" width="34" height="6" rx="2"/>
                                <path d="M15 45h-2v6h2"/>
                                <path d="M13 46h-2"/>
                                <path d="M13 50h-2"/>
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