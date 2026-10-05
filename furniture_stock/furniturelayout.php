<?php
require_once "../admin/auth.php"; 
$role = $_SESSION["role"] ?? 'User'; 
$user_division = $_SESSION['division_id'] ?? 0; 

if (!isset($page_title)) $page_title = "Furniture Dashboard";

/* Prevent caching */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$current_page = basename($_SERVER['PHP_SELF']);

// --- PENDING ASSET COUNT LOGIC ---
$pending_count = 0;
if (isset($conn)) {
    $count_sql = "
        SELECT COUNT(*) as total FROM (
            SELECT s.id
            FROM furniture_stock s
            JOIN units u ON s.unit_id = u.id
            LEFT JOIN furniture_assets fa ON s.id = fa.stock_id
            WHERE 1=1";

    // Filter by division if the user is not a SuperAdmin
    if ($role !== 'SuperAdmin') {
        $count_sql .= " AND u.division_id = '$user_division'";
    }

    $count_sql .= " GROUP BY s.id, s.total_qty
            HAVING COUNT(fa.id) < s.total_qty
        ) as pending_queue";

    $count_res = $conn->query($count_sql);
    if ($count_res) {
        $pending_count = $count_res->fetch_assoc()['total'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> | CECSMS Furniture</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        input:focus, 
        select:focus, 
        textarea:focus, 
        button:focus,
        .form-control:focus, 
        .form-select:focus {
            outline: none !important;
            box-shadow: none !important;
        }

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

        .collapse .nav-link {
            margin-left: 2rem !important;
            font-size: 0.82rem !important;  
            padding: 0.5rem 0.85rem !important;
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

        #sidebar .nav-link i {
            font-size: 1rem;
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
        <a href="furniture_dashboard.php" class="sidebar-brand">
            <div class="d-flex align-items-center justify-content-center rounded-3 bg-primary text-white shadow-sm" style="width: 38px; height: 38px;">
                <i class="bi bi-box-seam fs-5"></i>
            </div>
            <div class="d-flex flex-column">
                <span class="lh-1 fw-bold text-dark fs-5" style="letter-spacing: -0.02em;">StockFurniture</span>
                <span class="extra-small text-muted fw-medium mt-1" style="font-size: 0.7rem; letter-spacing: 0.03em;">Furniture Management</span>
            </div>
        </a>

        <div id="sidebarScrollArea" class="overflow-y-auto flex-grow-1" style="scrollbar-width: thin;">
            <div class="nav-group-label">General</div>
            <div class="nav flex-column">
                <a href="../furniture_stock/furniture_dashboard.php" class="nav-link <?= ($current_page == 'furniture_dashboard.php') ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
            </div>

            <div class="nav-group-label">Master Data</div>
            <div class="nav flex-column">
                <a href="../vendors/vendor_manager.php?type=Furniture" class="nav-link <?= ($_GET['type'] ?? '') == 'Furniture' ? 'active' : '' ?>">
                    <i class="bi bi-person-vcard-fill"></i> Manage Vendors
                </a>
                <a href="/cecsms/furniture_stock/manage_furniture_types.php" class="nav-link <?= ($current_page == 'manage_furniture_types.php') ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i> Furniture Registry
                </a>
            </div>

            <div class="nav-group-label">Inventory Management</div>
            <div class="nav flex-column">
                <a href="/cecsms/furniture_stock/add_furniture.php" class="nav-link <?= ($current_page == 'add_furniture.php') ? 'active' : '' ?>">
                    <i class="bi bi-box-seam"></i> Add Furniture Stock
                </a>
                <a href="/cecsms/furniture_stock/tag_assets.php" class="nav-link <?= ($current_page == 'tag_assets.php') ? 'active' : '' ?>">
                    <i class="bi bi-upc-scan"></i> 
                    <span class="flex-grow-1">Add Asset ID</span>
                    <?php if ($pending_count > 0): ?>
                        <span class="badge rounded-pill bg-danger shadow-sm extra-small"><?= $pending_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="/cecsms/furniture_stock/view_assets.php" class="nav-link <?= ($current_page == 'view_assets.php') ? 'active' : '' ?>">
                    <i class="bi bi-boxes"></i> View Assets
                </a>
                <a href="/cecsms/furniture_stock/view_furniture.php" class="nav-link <?= ($current_page == 'view_furniture.php') ? 'active' : '' ?>">
                    <i class="bi bi-boxes"></i> Furniture Inventory
                </a>
            </div>

            <?php if ($role === 'SuperAdmin'): ?>
            <div class="nav-group-label">Central Supply</div>
            <div class="nav flex-column">
                <a href="/cecsms/furniture_stock/add_furniture_central_stock.php" class="nav-link <?= ($current_page == 'add_furniture_central_stock.php') ? 'active' : '' ?>">
                    <i class="bi bi-building-down"></i> Add Central Stock
                </a>
                <a href="/cecsms/furniture_stock/view_furniture_central_stock.php" class="nav-link <?= ($current_page == 'view_furniture_central_stock.php') ? 'active' : '' ?>">
                    <i class="bi bi-database-fill-check"></i> View Central Stock
                </a>
            </div>
            
            <div class="nav-group-label">Logistics</div>
            <div class="nav flex-column">
                <a href="/cecsms/furniture_stock/dispatch_furniture.php" class="nav-link <?= ($current_page == 'dispatch_furniture.php') ? 'active' : '' ?>">
                    <i class="bi bi-truck"></i> Dispatch Furniture
                </a>
            </div>

            <div class="nav-group-label">Procurement</div>
            <div class="nav flex-column">
                <a href="/cecsms/furniture_stock/purchase_ledger.php" class="nav-link <?= ($current_page == 'purchase_ledger.php') ? 'active' : '' ?>">
                    <i class="bi bi-journal-plus"></i> Purchase Ledger
                </a>
                <a href="/cecsms/furniture_stock/view_purchase_ledger.php" class="nav-link <?= ($current_page == 'view_purchase_ledger.php') ? 'active' : '' ?>">
                    <i class="bi bi-journal-check"></i> View Purchase Ledger
                </a>
            </div>

            <div class="nav-group-label">Analysis Reports</div>
            <div class="nav flex-column">
                <a href="/cecsms/furniture_stock/furniture_stockreports.php" class="nav-link <?= ($current_page == 'furniture_stockreports.php') ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-bar-graph"></i> Stock Reports
                </a>
                <a href="/cecsms/furniture_stock/furniture_reports.php" class="nav-link <?= ($current_page == 'furniture_reports.php') ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-bar-graph"></i> Asset Reports
                </a>
            </div>    
            <?php endif; ?>
        </div>

        <div class="p-3 border-top mt-auto">
            <a href="/cecsms/admin/logout.php" class="btn btn-outline-danger w-100 rounded btn-sm fw-semibold">
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

                <div class="d-flex flex-column ms-1">
                    <h5 class="mb-0 fw-bold text-dark lh-1" style="font-size: 1.15rem; letter-spacing: -0.01em;">
                        <?= htmlspecialchars($page_title) ?>
                    </h5>   
                    <span class="text-muted extra-small mt-1 d-none d-md-inline" style="font-size: 0.72rem; letter-spacing: 0.01em;">
                        Managing separated furniture assets and bulk dispatches.
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-sm-flex align-items-center gap-2 text-muted extra-small pe-2">
                    <i class="bi bi-calendar3"></i>
                    <?= date('D, M j, Y') ?>
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
                            <a class="dropdown-item py-2 text-danger fw-semibold extra-small" href="/cecsms/admin/logout.php">
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

    <?php if(isset($modal_html)) echo $modal_html; ?>
    <?php if(isset($extra_html)) echo $extra_html; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if(menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('show');
            });
        }

        const scrollContainer = document.getElementById('sidebarScrollArea');
        window.addEventListener('load', () => {
            const savedScrollPos = localStorage.getItem('sidebarScrollPos');
            if (savedScrollPos && scrollContainer) {
                scrollContainer.scrollTop = savedScrollPos;
            }
            const activeLink = document.querySelector('#sidebar .nav-link.active');
            if (activeLink && scrollContainer) {
                setTimeout(() => {
                    const scrollPos = activeLink.offsetTop - (scrollContainer.clientHeight / 2) + (activeLink.clientHeight / 2);
                    scrollContainer.scrollTo({ top: scrollPos, behavior: 'smooth' });
                }, 100);
            }
        });

        window.addEventListener('beforeunload', () => {
            if (scrollContainer) {
                localStorage.setItem('sidebarScrollPos', scrollContainer.scrollTop);
            }
        });

        window.onpageshow = function(event) {
            if (event.persisted) { window.location.reload(); }
        };
    </script>
    <script src="/cecsms/includes/heartbeat.js"></script>
</body>
</html>