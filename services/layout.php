<?php 
require_once __DIR__ . "/../admin/auth.php"; 
require_once __DIR__ . "/../config/db.php";

$role = $_SESSION["role"] ?? 'User'; 
if (!isset($page_title)) {
    $page_title = "Service Management";
}

$current_page = basename($_SERVER['PHP_SELF']);

/* Prevent caching */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Fetch pending transitions count for consistency across layout notifications
$pending_count = 0;
$notif_res = null;
if (in_array($role, ['SuperAdmin', 'Admin'], true)) {
    $notif_query = "SELECT da.status, d.division_name, im.item_name, al.created_at
                    FROM division_assets da 
                    JOIN stock_details sd ON da.stock_detail_id = sd.id
                    JOIN items_master im ON sd.stock_item_id = im.id
                    JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
                    JOIN dispatch_master dm ON dd.dispatch_id = dm.id
                    JOIN divisions d ON dm.division_id = d.id
                    LEFT JOIN asset_logs al ON sd.id = al.asset_id AND al.action_type = da.status
                    WHERE da.status IN ('return_requested', 'repair_requested', 'dispose_requested')
                    GROUP BY da.id ORDER BY al.created_at DESC LIMIT 5";
    $notif_res = $conn->query($notif_query);
    if ($notif_res) {
        $pending_count = $notif_res->num_rows;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> | CECSMS ERP</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root {
            --sb-width: 290px;
            --primary-accent: #123b63;
            --primary-dark: #0b2942;
            --bg-body: #f3f5f7;
            --sidebar-bg: #ffffff;
            --text-main: #20384d;
            --text-muted: #64748b;
            --border-color: #d9e0e7;
            --shadow-sm: 0 1px 3px rgba(20,45,70,.05);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* --- SIDEBAR --- */
        #sidebar {
            width: var(--sb-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            transition: transform 0.3s ease-in-out;
            z-index: 1030; 
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--primary-accent);
            text-decoration: none;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-group-label {
            padding: 1.25rem 1.5rem 0.4rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        #sidebar .nav-link {
            margin: 0.15rem 0.85rem;
            padding: 0.65rem 1rem;
            color: var(--text-main);
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
            text-decoration: none;
        }

        #sidebar .nav-link:hover {
            background: #edf3f8;
            color: var(--primary-accent);
        }

        #sidebar .nav-link.active {
            background: var(--primary-accent);
            color: #ffffff !important;
            font-weight: 600;
        }

        #sidebar .nav-link i {
            font-size: 1rem;
        }

        /* --- MAIN CONTENT --- */
        .main-wrapper {
            margin-left: var(--sb-width);
            min-height: 100vh;
            padding: 1.25rem 1.75rem;
            font-size: 0.95rem;
            transition: margin 0.3s ease-in-out;
            position: relative;
            z-index: 1;
        }

        .top-navbar {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }

        .nav-home-icon {
            width: 36px;
            height: 36px;
            background-color: #f8fafc;
            color: var(--text-muted);
            border-radius: 6px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease-in-out;
            border: 1px solid var(--border-color);
            text-decoration: none;
        }

        .nav-home-icon:hover {
            background-color: var(--primary-accent);
            color: #ffffff;
            border-color: var(--primary-accent);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: #fff;
            cursor: pointer;
        }

        .extra-small {
            font-size: 0.72rem;
        }

        .whitespace-normal {
            white-space: normal !important;
        }

        .bg-emerald-soft { background-color: rgba(63, 117, 94, 0.12); color: #3f755e; }
        .bg-success-subtle { background-color: #dcfce7 !important; color: #15803d !important; }
        .bg-warning-subtle { background-color: #fef3c7 !important; color: #92400e !important; }
        .bg-info-subtle { background-color: #e0f2fe !important; color: #0369a1 !important; }
        .bg-danger-subtle { background-color: #fee2e2 !important; color: #991b1b !important; }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 992px) {
            #sidebar { transform: translateX(-100%); z-index: 2000; }
            .main-wrapper { margin-left: 0; }
            #sidebar.show { transform: translateX(0); }
        }
    </style>
</head>
<body>

    <nav id="sidebar">
        <a href="/cecsms/services/index.php" class="sidebar-brand">
            <div class="d-flex align-items-center justify-content-center rounded-3 bg-primary text-white shadow-sm" style="width: 38px; height: 38px;">
                <i class="bi bi-wrench-adjustable fs-5"></i>
            </div>
            <div class="d-flex flex-column">
                <span class="lh-1 fw-bold text-dark fs-5" style="letter-spacing: -0.02em;">ServiceFlow</span>
                <span class="extra-small text-muted fw-medium mt-1" style="font-size: 0.7rem; letter-spacing: 0.03em;">CECSMS Service Portal</span>
            </div>
        </a>

        <div class="overflow-y-auto flex-grow-1" style="scrollbar-width: thin;">
            <div class="nav-group-label">General</div>
            <div class="nav flex-column">
                <a href="index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i> Service Dashboard
                </a>
            </div>

            <div class="nav-group-label">Service Operations</div>
            <div class="nav flex-column">
                <a href="/cecsms/vendors/vendor_manager.php" class="nav-link <?= ($current_page == 'vendor_manager.php') ? 'active' : '' ?>">
                    <i class="bi bi-person-vcard-fill"></i> Vendor Manager
                </a>
                <a href="add_service.php" class="nav-link <?= ($current_page == 'add_service.php') ? 'active' : '' ?>">
                    <i class="bi bi-wrench-adjustable"></i> Add Service
                </a>
                <a href="view_services.php" class="nav-link <?= ($current_page == 'view_services.php') ? 'active' : '' ?>">
                    <i class="bi bi-list-columns-reverse"></i> View Services
                </a>
            </div>

            <div class="nav-group-label">Reports & Analytics</div>
            <div class="nav flex-column">
                <a href="export_excel.php" class="nav-link <?= ($current_page == 'export_excel.php') ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-excel-fill text-success"></i> Export Excel
                </a>
            </div>
        </div>

        <div class="p-3 border-top mt-auto">
            <a href="../admin/logout.php" class="btn btn-outline-danger w-100 rounded btn-sm fw-semibold">
                <i class="bi bi-power me-1"></i> Logout
            </a>
        </div>
    </nav>

    <main class="main-wrapper">
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none border-0 shadow-sm rounded" id="menuToggle">
                    <i class="bi bi-list fs-5"></i>
                </button>
                
                <a href="/cecsms/index.php" class="nav-home-icon" title="Dashboard Home">
                    <i class="bi bi-house-door"></i>
                </a>

                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex flex-column">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h5 class="mb-0 fw-bold text-dark lh-1" style="font-size: 1.15rem; letter-spacing: -0.01em;">
                                 <?= htmlspecialchars($page_title) ?>
                            </h5>   
                        </div>
                        <span class="text-muted extra-small d-none d-md-inline" style="font-size: 0.72rem; letter-spacing: 0.02em;">
                                Maintenance and Vendor Management Portal
                        </span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-sm-flex align-items-center gap-2 text-muted extra-small border-end pe-3">
                    <i class="bi bi-calendar3"></i>
                    <?= date('D, M j, Y') ?>
                </div>

                <div class="dropdown me-1">
                    <button class="btn btn-light position-relative border shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" 
                            style="width: 34px; height: 34px;" data-bs-toggle="dropdown">
                        <i class="bi bi-bell text-muted fs-6"></i>
                        
                        <span id="notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light <?= ($pending_count > 0) ? '' : 'd-none' ?>" style="font-size: 9px;">
                            <?= $pending_count ?>
                        </span>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-0 rounded-3 overflow-hidden animate-fade-in" style="width: 320px;">
                        <div class="p-3 border-bottom bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold small">Stock Transitions</h6>
                                <span id="notification-count-text" class="badge bg-emerald-soft extra-small"><?= $pending_count ?> Pending</span>
                            </div>
                        </div>

                        <div id="notification-list" class="max-vh-50 overflow-y-auto" style="max-height: 350px;">
                            <?php if ($notif_res && $notif_res->num_rows > 0): ?>
                                <?php while($n = $notif_res->fetch_assoc()): 
                                    $type = strtoupper(str_replace('_requested', '', $n['status']));
                                    $icon = ($type == 'REPAIR') ? 'bi-tools text-info' : (($type == 'RETURN') ? 'bi-arrow-left-circle text-warning' : 'bi-trash text-danger');
                                    $bg = ($type == 'REPAIR') ? 'bg-info-subtle' : (($type == 'RETURN') ? 'bg-warning-subtle' : 'bg-danger-subtle');
                                ?>
                                    <a href="/cecsms/divisions/returned_assets.php" class="dropdown-item p-3 border-bottom d-flex gap-3 align-items-start whitespace-normal">
                                        <div class="<?= $bg ?> rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                                            <i class="bi <?= $icon ?> fs-6"></i>
                                        </div>
                                        <div class="w-100">
                                            <div class="d-flex justify-content-between">
                                                <p class="mb-0 small fw-bold text-dark"><?= htmlspecialchars($n['division_name']) ?></p>
                                                <span class="text-muted" style="font-size: 9px;"><?= date('H:i', strtotime($n['created_at'])) ?></span>
                                            </div>
                                            <p class="mb-1 text-muted" style="font-size: 0.78rem;">
                                                <strong><?= $type ?>:</strong> <?= htmlspecialchars($n['item_name']) ?>
                                            </p>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="p-4 text-center">
                                    <i class="bi bi-check2-circle fs-1 text-muted opacity-25"></i>
                                    <p class="text-muted small mt-2 mb-0">All caught up!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <a href="/cecsms/divisions/returned_assets.php" class="dropdown-item text-center p-2 small fw-bold text-primary bg-light border-top">
                            View All Approvals
                        </a>
                    </div>
                </div>

                <div class="dropdown">
                    <div class="user-profile shadow-sm" data-bs-toggle="dropdown">
                        <div class="text-end d-none d-md-block">
                            <p class="extra-small fw-bold mb-0 text-dark"><?= htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
                            <span class="badge bg-emerald-soft" style="font-size: 9px; letter-spacing: 0.02em;">
                                <?= htmlspecialchars($role) ?>
                            </span>
                        </div>
                        <div class="avatar bg-light border rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-person text-secondary"></i>
                        </div>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li>
                            <a class="dropdown-item py-2 text-danger fw-semibold extra-small" href="../admin/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <div class="animate-fade-in">
            <div class="container-fluid p-0">
                <?php if(isset($content)) echo $content; ?>
            </div>
        </div>
    </main>

    <?php if(isset($extra_html)) echo $extra_html; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkNotifications() {
            fetch('/cecsms/services/get_notifications.php?v=' + Date.now())
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    const badge = document.getElementById('notification-badge');
                    const list = document.getElementById('notification-list');
                    const countText = document.getElementById('notification-count-text');

                    if (data.count > 0) {
                        badge.innerText = data.count;
                        badge.classList.remove('d-none');
                        if(countText) countText.innerText = data.count + " Pending";
                    } else {
                        badge.classList.add('d-none');
                        if(countText) countText.innerText = "0 Pending";
                    }

                    if (data.items && data.items.length > 0) {
                        let html = '';
                        data.items.forEach(item => {
                            let type = item.status.replace('_requested', '').toUpperCase();
                            let icon = (type === 'REPAIR') ? 'bi-tools text-info' : 
                                       ((type === 'RETURN') ? 'bi-arrow-left-circle text-warning' : 'bi-trash text-danger');
                            let bg = (type === 'REPAIR') ? 'bg-info-subtle' : 
                                     ((type === 'RETURN') ? 'bg-warning-subtle' : 'bg-danger-subtle');
                            
                            html += `
                                <a href="/cecsms/divisions/returned_assets.php" class="dropdown-item p-3 border-bottom d-flex gap-3 align-items-start whitespace-normal">
                                    <div class="${bg} rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                                        <i class="bi ${icon} fs-6"></i>
                                    </div>
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between">
                                            <p class="mb-0 small fw-bold text-dark">${item.division_name}</p>
                                            <span class="text-muted" style="font-size: 9px;">Live</span>
                                        </div>
                                        <p class="mb-1 text-muted" style="font-size: 0.78rem;">
                                            ${item.message}
                                        </p>
                                    </div>
                                </a>`;
                        });
                        list.innerHTML = html;
                    } else {
                        list.innerHTML = `
                            <div class="p-4 text-center">
                                <i class="bi bi-check2-circle fs-1 text-muted opacity-25"></i>
                                <p class="text-muted small mt-2 mb-0">All caught up!</p>
                            </div>`;
                    }
                })
                .catch(err => console.error('Notification Update Failed:', err));
        }

        setInterval(checkNotifications, 15000);
        document.addEventListener('DOMContentLoaded', checkNotifications);

        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if(menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('show');
            });
        }

        document.addEventListener("DOMContentLoaded", function() {
            const sidebarContainer = document.querySelector('.overflow-y-auto');
            const activeLink = document.querySelector('#sidebar .nav-link.active');
            if (activeLink && sidebarContainer) {
                setTimeout(() => {
                    const scrollPos = activeLink.offsetTop - (sidebarContainer.clientHeight / 2) + (activeLink.clientHeight / 2);
                    sidebarContainer.scrollTo({ top: scrollPos, behavior: 'smooth' });
                }, 100);
            }
        });

        window.onpageshow = function(event) {
            if (event.persisted) { window.location.reload(); }
        };
    </script>
</body>
</html>