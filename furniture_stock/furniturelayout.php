<?php
require_once "../admin/auth.php"; 
$role = $_SESSION["role"] ?? 'User'; 
$user_division = $_SESSION['division_id'] ?? 0; // Get the division from session

if (!isset($page_title)) $page_title = "Furniture Dashboard";

// --- CACHE CONTROL ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$current_page = basename($_SERVER['PHP_SELF']);

// --- PENDING ASSET COUNT LOGIC (Division Aware) ---
$pending_count = 0;
if (isset($conn)) {
    // We join units to filter by division_id for non-SuperAdmins
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
    <title><?= htmlspecialchars($page_title) ?> | StockFlow Furniture</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --sb-width: 290px;
            --primary-accent: #10b981;
            --bg-body: #f8fafc;
            --sidebar-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
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
            top: 0; left: 0;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            transition: transform 0.3s ease-in-out;
            z-index: 1030; 
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--primary-accent);
            text-decoration: none;
        }

        .nav-group-label {
            padding: 1.5rem 1.5rem 0.5rem;
            font-size: 0.78rem;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.08rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        #sidebar .nav-link {
            margin: 0.2rem 1rem;
            padding: 0.85rem 1.2rem;
            color: var(--text-muted);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
        }

        #sidebar .nav-link:hover {
            background: #f1f5f9;
            color: var(--primary-accent);
            transform: translateX(4px);
        }

        #sidebar .nav-link.active {
            background: var(--primary-accent);
            color: #ffffff !important;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.25);
        }

        /* --- MAIN CONTENT --- */
        .main-wrapper {
            margin-left: var(--sb-width);
            min-height: 100vh;
            padding: 1.5rem;
            padding-left: 0.5rem;
            transition: margin 0.3s ease-in-out;
            position: relative;
            z-index: 1;
        }

        .top-navbar {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 0.8rem 1.5rem;
            margin-bottom: 1rem; /* Changed from 2rem to 1rem */
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }

        .nav-home-icon {
            width: 38px; height: 38px;
            background-color: #f8fafc;
            color: #64748b;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 12px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: #fff;
        }
        
        .pulse-badge {
            font-size: 0.65rem;
            padding: 4px 8px;
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 6px rgba(220, 53, 69, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }

        #sidebar .nav-link {
            display: flex;
            align-items: center;
            
        }

        .bg-emerald-soft { background-color: rgba(16, 185, 129, 0.1); }

        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 992px) {
            #sidebar { transform: translateX(-100%); }
            .main-wrapper { margin-left: 0; }
            #sidebar.show { transform: translateX(0); }
        }
    </style>
</head>
<body>

<nav id="sidebar">
    <a href="furniture_dashboard.php" class="sidebar-brand">
        <div class="bg-success text-white rounded-3 px-2 py-1 shadow-sm">
            <i class="bi bi-chair"></i>
        </div>
        <span>Stock<span class="text-dark">Furniture</span></span>
    </a>

    <div class="overflow-y-auto flex-grow-1">
    <div class="nav-group-label">General</div>
        <div class="nav flex-column">
            <a href="../furniture_stock/furniture_dashboard.php" class="nav-link <?= ($current_page == 'furniture_dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
        </div>
        <div class="nav-group-label">Master Data</div>
        <div class="nav flex-column">
            <a href="../vendors/vendor_manager.php?type=Furniture" 
            class="nav-link <?= ($_GET['type'] ?? '') == 'Furniture' ? 'active' : '' ?>">
                <i class="bi bi-person-vcard-fill"></i> Manage Vendors
            </a>
            <a href="/cecsms/furniture_stock/manage_furniture_types.php" class="nav-link <?= ($current_page == 'manage_furniture_types.php') ? 'active' : '' ?>">
                <i class="bi-journal-text"></i> Furniture Registry
            </a>
        </div>

        <div class="nav-group-label">Inventory Management</div>
        <div class="nav flex-column">
            <a href="/cecsms/furniture_stock/add_furniture.php" class="nav-link <?= ($current_page == 'add_furniture.php') ? 'active' : '' ?>">
                <i class="bi-box-seam"></i> Add Furniture Stock
            </a>
            <a href="/cecsms/furniture_stock/view_furniture.php" class="nav-link <?= ($current_page == 'view_furniture.php') ? 'active' : '' ?>">
                <i class="bi-boxes"></i> Furniture Inventory
            </a>
            <a href="/cecsms/furniture_stock/tag_assets.php" class="nav-link <?= ($current_page == 'tag_assets.php') ? 'active' : '' ?>">
                <i class="bi-upc-scan"></i> 
                <span class="flex-grow-1">Add Asset ID</span>
                <?php if ($pending_count > 0): ?>
                    <span class="badge rounded-pill bg-danger shadow-sm pulse-badge">
                        <?= $pending_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="/cecsms/furniture_stock/view_assets.php" class="nav-link <?= ($current_page == 'view_assets.php') ? 'active' : '' ?>">
                <i class="bi-boxes"></i> View Assets
            </a>
        </div>

        <div class="nav-group-label">Logistics</div>
        <div class="nav flex-column">
            <a href="/cecsms/furniture_stock/dispatch_furniture.php" class="nav-link <?= ($current_page == 'dispatch_furniture.php') ? 'active' : '' ?>">
                <i class="bi bi-truck"></i> Dispatch Furniture
            </a>
        <div class="nav-group-label">Reports</div>
            <a href="/cecsms/furniture_stock/furniture_stockreports.php" class="nav-link <?= ($current_page == 'furniture_stockreports.php') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Stock Reports
            </a>
            <a href="/cecsms/furniture_stock/furniture_reports.php" class="nav-link <?= ($current_page == 'furniture_reports.php') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Asset Reports
            </a>
            <a href="/cecsms/furniture_stock/purchase_ledger.php" class="nav-link <?= ($current_page == 'purchase_ledger.php') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Purchase Ledger
            </a>
             <a href="/cecsms/furniture_stock/view_purchase_ledger.php" class="nav-link <?= ($current_page == 'view_purchase_ledger.php') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> View Purchase Ledger
            </a>
        </div>
    </div>

    <div class="p-3 border-top mt-auto">
        <a href="..admin//logout.php" class="btn btn-outline-danger w-100 rounded-pill btn-sm fw-bold">
            <i class="bi bi-power me-2"></i> Logout
        </a>
    </div>
</nav>

<main class="main-wrapper">
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none border-0 shadow-sm rounded-3" id="menuToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            
            <a href="/cecsms/index.php" class="nav-home-icon text-decoration-none" title="Back to Main Dashboard">
                <i class="bi bi-house-door"></i>
            </a>

            <div>
                <h5 class="mb-0 fw-bold text-dark lh-1 mb-1"><?= htmlspecialchars($page_title) ?></h5>
                <p class="text-muted mb-0 d-none d-md-block" style="font-size: 11px;">
                    Managing separated furniture assets and bulk dispatches.
                </p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="d-none d-sm-flex align-items-center gap-2 text-muted small border-end pe-3">
                <i class="bi bi-calendar-event"></i>
                <?= date('D, M j, Y') ?>
            </div>

            <div class="dropdown">
                <div class="user-profile shadow-sm" data-bs-toggle="dropdown">
                    <div class="text-end d-none d-md-block">
                        <p class="small fw-bold mb-0"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                        <span class="badge bg-emerald-soft text-success" style="font-size: 9px;">
                            <?= htmlspecialchars($role) ?>
                        </span>
                    </div>
                    <div class="avatar bg-light border rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                        <i class="bi bi-person text-success"></i>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li><a class="dropdown-item py-2 text-danger fw-bold" href="../admin/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="animate-fade-in">
        <?php if (isset($content)) echo $content; ?>
    </div>
</main>

<?php if (isset($modal_html)) echo $modal_html; ?>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Mobile Toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if(menuToggle) {
        menuToggle.addEventListener('click', () => { sidebar.classList.toggle('show'); });
    }
</script>
</body>
</html>