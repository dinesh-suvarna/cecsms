<?php
require_once __DIR__ . "/../config/db.php";
if (!isset($page_title)) $page_title = "Division Dashboard";

/* --- CACHE CONTROL --- */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION["role"] ?? 'User';
$notif_division_id = $_SESSION['division_id'] ?? 0;

/**
 * 1. Fetches Repair/Return status updates from asset_logs
 * 2. Fetches Dispatches from SuperAdmin where items don't have a Division Asset ID yet
 */
$notif_query = "
    (SELECT 
        al.id AS ref_id, 
        al.action_type, 
        al.created_at, 
        im.item_name, 
        'log' AS notif_source
     FROM asset_logs al
     INNER JOIN stock_details sd ON al.asset_id = sd.id
     INNER JOIN items_master im ON sd.stock_item_id = im.id
     LEFT JOIN division_assets da ON sd.id = da.stock_detail_id
     LEFT JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
     LEFT JOIN dispatch_master dm ON dd.dispatch_id = dm.id
     WHERE (dm.division_id = $notif_division_id OR al.performed_by IN (
         SELECT id FROM users WHERE division_id = $notif_division_id
     ))
     AND al.action_type IN ('assigned', 'return_requested', 'repair_requested')
     AND al.is_read = 0) 
    UNION ALL

    (SELECT 
        dm.id AS ref_id, 
        'NEW_DISPATCH' AS action_type, 
        dm.created_at AS created_at, 
        'Inventory Stock' AS item_name, 
        'dispatch' AS notif_source
     FROM dispatch_master dm
     INNER JOIN dispatch_details dd ON dm.id = dd.dispatch_id
     LEFT JOIN division_assets da ON dd.id = da.dispatch_detail_id
     WHERE dm.division_id = $notif_division_id 
     AND dm.status = 'active' 
     AND da.id IS NULL
     GROUP BY dm.id)
     ORDER BY created_at DESC";

$notifications = $conn->query($notif_query);
$notif_count = $notifications ? $notifications->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> | StockFlow Division</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --sb-width: 290px;
            --primary: #0d6efd;
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
        <a href="division_dashboard.php" class="sidebar-brand">
            <div class="d-flex align-items-center justify-content-center rounded-3 text-white shadow-sm" style="width: 38px; height: 38px; background-color: var(--primary);">
                <i class="bi bi-box-seam fs-5"></i>
            </div>
            <div class="d-flex flex-column">
                <span class="lh-1 fw-bold text-dark fs-5" style="letter-spacing: -0.02em;">StockFlow</span>
                <span class="extra-small text-muted fw-medium mt-1" style="font-size: 0.7rem; letter-spacing: 0.03em;">Division Portal</span>
            </div>
        </a>

        <div class="overflow-y-auto flex-grow-1" style="scrollbar-width: thin;">
            <div class="nav-group-label">Overview</div>
            <div class="nav flex-column">
                <a href="/cecsms/divisions/division_dashboard.php" class="nav-link <?= ($current_page == 'division_dashboard.php') ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
            </div>

            <div class="nav-group-label">Master Data</div>
            <div class="nav flex-column">
                <a href="../vendors/vendor_manager.php?type=Computer" class="nav-link <?= (($_GET['type'] ?? '') == 'Computer') ? 'active' : '' ?>">
                    <i class="bi bi-person-vcard"></i> Manage Vendors
                </a>
                <a href="/cecsms/divisions/add_components.php" class="nav-link <?= ($current_page == 'add_components.php') ? 'active' : '' ?>">
                    <i class="bi bi-plugin"></i> Components & ICs
                </a>
                <a href="/cecsms/divisions/view_components.php" class="nav-link <?= ($current_page == 'view_components.php') ? 'active' : '' ?>">
                    <i class="bi bi-layers-half"></i> Component Stock
                </a>
            </div>

            <div class="nav-group-label">Asset Management</div>
            <div class="nav flex-column">
                <a href="/cecsms/divisions/assign_asset.php" class="nav-link <?= ($current_page == 'assign_asset.php') ? 'active' : '' ?>">
                    <i class="bi bi-tag"></i> Assign Asset ID
                </a>
                <a href="/cecsms/divisions/assigned_assets.php" class="nav-link <?= ($current_page == 'assigned_assets.php') ? 'active' : '' ?>">
                    <i class="bi bi-check-circle"></i> View My Assets
                </a>
            </div>

            <div class="nav-group-label">Maintenance</div>
            <div class="nav flex-column">
                <a href="/cecsms/divisions/asset_logs.php" class="nav-link <?= ($current_page == 'asset_logs.php') ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i> Asset Audit Logs
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
                            Department asset tracking and status management.
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
                        <span id="notif-badge-container">
                            <?php if ($notif_count > 0): ?>
                                <span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 9px;">
                                    <?= $notif_count ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-0 rounded-3 overflow-hidden animate-fade-in" style="width: 320px;">
                        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold small">Notifications</h6>
                            <?php if ($notif_count > 0): ?>
                                <a href="mark_all_read.php" class="text-primary fw-bold text-decoration-none extra-small">
                                    Mark all read
                                </a>
                            <?php endif; ?>
                        </div>

                        <div id="notif-list" class="overflow-y-auto" style="max-height: 350px;">
                            <?php if ($notif_count > 0): ?>
                                <?php while($n = $notifications->fetch_assoc()): 
                                    $type = $n['action_type'];
                                    $ref_id = $n['ref_id'];
                                    $is_dispatch = ($type === 'NEW_DISPATCH');
                                    $is_rejected = (strpos($type, 'rejected') !== false || strpos($type, 'REJECTED') !== false);

                                    if ($is_dispatch) {
                                        $icon = 'bi-box-seam text-primary';
                                        $link = 'assign_asset.php'; 
                                        $title = "New Dispatch Received";
                                        $message = "Items have arrived. Please <strong>Assign Asset IDs</strong>.";
                                    } else {
                                        $icon = $is_rejected ? 'bi-x-circle text-danger' : 'bi-check-circle text-success';
                                        $link = "mark_notif_read.php?id=" . urlencode($ref_id);
                                        $title = str_replace('_', ' ', $type);
                                        $message = "Your request for <strong>" . htmlspecialchars($n['item_name']) . "</strong> has been " . ($is_rejected ? 'rejected' : 'approved') . ".";
                                    }
                                ?>
                                    <a class="dropdown-item p-3 border-bottom d-flex gap-3 align-items-start whitespace-normal" href="<?= $link ?>">
                                        <div class="bg-light border rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                                            <i class="bi <?= $icon ?> fs-6"></i>
                                        </div>
                                        <div class="w-100">
                                            <p class="mb-0 small fw-bold text-dark"><?= strtoupper($title) ?></p>
                                            <p class="mb-1 text-muted extra-small"><?= $message ?></p>
                                            <span class="text-muted extra-small italic"><?= date('M d, H:i', strtotime($n['created_at'])) ?></span>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="p-4 text-center">
                                    <i class="bi bi-bell-slash fs-1 text-muted opacity-25"></i>
                                    <p class="text-muted small mt-2 mb-0">No new updates.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <a href="asset_logs.php" class="dropdown-item text-center p-2 small fw-bold text-primary bg-light border-top">
                            View All Activity
                        </a>
                    </div>
                </div>

                <div class="dropdown">
                    <div class="user-profile shadow-sm" data-bs-toggle="dropdown">
                        <div class="text-end d-none d-md-block">
                            <p class="extra-small fw-bold mb-0 text-dark"><?= htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
                            <span class="badge bg-emerald-soft" style="font-size: 9px; letter-spacing: 0.02em;">
                                <?= htmlspecialchars($_SESSION['role'] ?? 'Division'); ?>
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

        <div class="animate-fade-in pt-0">
            <?php 
            if (isset($main_content)) {
                echo $main_content;
            } elseif (isset($content)) {
                echo $content;
            }
            ?>
        </div>
    </main>

    <?php 
    if (isset($modal_html)) {
        echo $modal_html;
    } 
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        function fetchNotifications() {
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const badgeContainer = document.getElementById('notif-badge-container');
                    if (data.count > 0) {
                        badgeContainer.innerHTML = `<span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 9px;">${data.count}</span>`;
                    } else {
                        badgeContainer.innerHTML = '';
                    }
                    document.getElementById('notif-list').innerHTML = data.html;
                })
                .catch(error => console.error('Live Notif Error:', error));
        }

        setInterval(fetchNotifications, 15000);
    </script>

    <?php if (isset($_SESSION['swal_msg'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?= $_SESSION['swal_type'] ?? "success" ?>',
                title: '<?= ($_SESSION['swal_type'] == "success") ? "Done!" : "Notice" ?>',
                text: '<?= $_SESSION['swal_msg'] ?>',
                timer: 3000,
                showConfirmButton: false,
                timerProgressBar: true
            });
        });
    </script>
    <?php 
        unset($_SESSION['swal_msg']);
        unset($_SESSION['swal_type']);
    endif; 
    ?>
</body>
</html>