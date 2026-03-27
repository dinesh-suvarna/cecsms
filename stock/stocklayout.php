<?php
require_once "../admin/auth.php"; 
$role = $_SESSION["role"] ?? 'User'; 
if (!isset($page_title)) $page_title = "Stock Dashboard";

// --- CACHE CONTROL ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> | StockFlow Admin</title>

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
            letter-spacing: 0.1rem;
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
            font-size: 1rem;   /* Bigger */
            font-weight: 600;  /* Slightly bold */
            transition: all 0.2s;
            text-decoration: none;
        }

        #sidebar .nav-link:hover {
            background: #f1f5f9;
            color: var(--primary-accent);
            transform: translateX(4px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.04);
        }

        #sidebar .nav-link.active {
            background: var(--primary-accent);
            color: #ffffff !important;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.25);
            font-weight: 700;
        }

        .collapse .nav-link {
            margin-left: 2.5rem !important;
            font-size: 0.95rem !important;  
            padding: 0.6rem 1rem !important;
            font-weight: 500;
        }

        /* --- MAIN CONTENT --- */
        .main-wrapper {
            margin-left: var(--sb-width);
            min-height: 100vh;
            padding: 1.5rem;
            font-size: 0.98rem;
            transition: margin 0.3s ease-in-out;
            position: relative;
            z-index: 1;
        }

        .top-navbar {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 0.8rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }

        .nav-home-icon {
            width: 38px;
            height: 38px;
            background-color: #f8fafc;
            color: #64748b;
            border-radius: 10px;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease-in-out;
            border: 1px solid var(--border-color);
            text-decoration: none;
        }

        .nav-home-icon:hover {
            background-color: var(--primary-accent);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            border-color: var(--primary-accent);
        }

        

        #sidebar .nav-link i {
            font-size: 1.1rem;
        }

        .nav-link[aria-expanded="true"] .bi-chevron-down {
            transform: rotate(180deg);
            transition: transform 0.3s ease;
        }

        .nav-link .bi-chevron-down {
            transition: transform 0.3s ease;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 12px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: #fff;
            cursor: pointer;
        }

        /* --- GLOBAL UI RESET --- */
        
        *, 
        *:focus, 
        *:active, 
        *:focus-visible,
        .form-control, 
        .form-select, 
        .btn, 
        .input-group,
        .input-group-text {
            outline: none !important;
            box-shadow: none !important; /* This kills the blue glow */
            -webkit-tap-highlight-color: transparent; /* Fix for mobile blue tap */
        }

        /* 2. Custom Focus State (Emerald Theme) */
        .form-control:focus, 
        .form-select:focus {
            border-color: var(--primary-accent) !important;
            background-color: #fff !important;
            /* This creates a very tiny, soft green shadow instead of a thick blue one */
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.1) !important;
        }

        /* 3.Search Bar  */
        .input-group:focus-within {
            box-shadow: none !important;
            border-radius: 10px; /* Adjust to match your UI */
        }

        .btn:focus, .btn:active {
            outline: none !important;
            box-shadow: none !important;
        }

        .input-group.rounded-pill {
            transition: all 0.2s ease-in-out;
        }

        .input-group.rounded-pill:focus-within {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important; /* Soft shadow instead of blue */
            border-color: #10b981 !important; /* Changes the pill border to emerald */
        }

        #inventorySearch:focus {
            outline: none !important;
            box-shadow: none !important;
            background-color: transparent !important;
        }

        .input-group-text, #inventorySearch {
            border: none !important;
            box-shadow: none !important;
        }

        /* --- UI UTILITIES --- */
        .bg-emerald-soft { background-color: rgba(16, 185, 129, 0.1); }
        .modal-backdrop { z-index: 1040 !important; }
        .modal { z-index: 1050 !important; }

        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
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
    <a href="dashboard.php" class="sidebar-brand">
        <div class="bg-success text-white rounded-3 px-2 py-1 shadow-sm">
            <i class="bi bi-box-seam"></i>
        </div>
        <span>Stock<span class="text-dark">Flow</span></span>
    </a>

    <div class="overflow-y-auto flex-grow-1">
        <div class="nav-group-label">General</div>
        <div class="nav flex-column">
            <a href="dashboard.php" class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
        </div>

        <div class="nav-group-label">Inventory</div>
        <div class="nav flex-column">
            <a href="add_stock_details.php" class="nav-link <?= ($current_page == 'add_stock_details.php') ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i> Add Stock Details
            </a>
            <a href="view_stock_details.php" class="nav-link <?= ($current_page == 'view_stock_details.php') ? 'active' : '' ?>">
                <i class="bi bi-list-ul"></i> View Stock Details
            </a>
            <a href="add_components.php" class="nav-link <?= ($current_page == 'add_components.php') ? 'active' : '' ?>">
                <i class="bi bi-plugin"></i> Components & ICs
            </a>
            <a href="view_components.php" class="nav-link <?= ($current_page == 'view_components.php') ? 'active' : '' ?>">
                <i class="bi bi-layers-half"></i> Component Stock
            </a>
            
        </div>

        <div class="nav-group-label">Logistics</div>
        <div class="nav flex-column">
            <a href="dispatch.php" class="nav-link <?= ($current_page == 'dispatch.php') ? 'active' : '' ?>">
                <i class="bi bi-truck"></i> Dispatch Stock
            </a>
            <a href="dispatch_report.php" class="nav-link <?= ($current_page == 'dispatch_report.php') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i> Dispatch Report
            </a>
        </div>
    </div>

    <div class="p-3 border-top mt-auto">
        <a href="../logout.php" class="btn btn-outline-danger w-100 rounded-pill btn-sm fw-bold">
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
            
            <a href="/cecsms/index.php" class="nav-home-icon d-flex align-items-center justify-content-center text-decoration-none" title="Go to Dashboard">
                <i class="bi bi-house-door"></i>
            </a>

            <div>
                <h5 class="mb-0 fw-bold text-dark lh-1 mb-1"><?= htmlspecialchars($page_title) ?></h5>
                <p class="text-muted mb-0 d-none d-md-block" style="font-size: 11px; letter-spacing: 0.02rem;">
                    Manage your stock flow and logistics efficiently.
                </p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="dropdown me-2">
            <?php 
            $notif_query = "SELECT 
                                da.status, 
                                d.division_name, 
                                im.item_name,
                                al.notes,
                                al.created_at
                            FROM division_assets da 
                            JOIN stock_details sd ON da.stock_detail_id = sd.id
                            JOIN items_master im ON sd.stock_item_id = im.id
                            JOIN dispatch_details dd ON da.dispatch_detail_id = dd.id
                            JOIN dispatch_master dm ON dd.dispatch_id = dm.id
                            JOIN divisions d ON dm.division_id = d.id
                            -- Get the latest log entry for this asset to show the remarks/notes
                            LEFT JOIN asset_logs al ON sd.id = al.asset_id 
                                AND al.action_type = da.status
                            WHERE da.status IN ('return_requested', 'repair_requested', 'dispose_requested')
                            GROUP BY da.id -- Ensure unique asset rows
                            ORDER BY al.created_at DESC LIMIT 5";
                            
            $notif_res = $conn->query($notif_query);
            
            
            $count_query = "SELECT COUNT(*) as total FROM division_assets WHERE status IN ('return_requested', 'repair_requested', 'dispose_requested')";
            $count_res = $conn->query($count_query);
            $count_data = $count_res->fetch_assoc();
            $pending_count = $count_data['total'] ?? 0;
            ?>
            
            <button class="btn btn-light position-relative border shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" 
                    style="width: 38px; height: 38px;" data-bs-toggle="dropdown">
                <i class="bi bi-bell text-muted fs-5"></i>
                <?php if ($pending_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 10px;">
                        <?= $pending_count ?>
                    </span>
                <?php endif; ?>
            </button>

            <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-0 rounded-4 overflow-hidden" style="width: 320px;">
                <div class="p-3 border-bottom bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">Stock Transitions</h6>
                        <span class="badge bg-success-subtle text-success small"><?= $pending_count ?> Pending</span>
                    </div>
                </div>
                <div class="max-vh-50 overflow-y-auto" style="max-height: 350px;">
                    <?php if ($notif_res && $notif_res->num_rows > 0): ?>
                        <?php while($n = $notif_res->fetch_assoc()): 
                            $type = strtoupper(str_replace('_requested', '', $n['status']));
                            $icon = ($type == 'REPAIR') ? 'bi-tools text-info' : (($type == 'RETURN') ? 'bi-arrow-left-circle text-warning' : 'bi-trash text-danger');
                            $bg = ($type == 'REPAIR') ? 'bg-info-subtle' : (($type == 'RETURN') ? 'bg-warning-subtle' : 'bg-danger-subtle');
                        ?>
                            <a href="/cecsms/divisions/returned_assets.php" class="dropdown-item p-3 border-bottom d-flex gap-3 align-items-start" style="white-space: normal;">
                                <div class="<?= $bg ?> rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                                    <i class="bi <?= $icon ?> fs-5"></i>
                                </div>
                                <div class="w-100">
                                    <div class="d-flex justify-content-between">
                                        <p class="mb-0 small fw-bold text-dark"><?= htmlspecialchars($n['division_name']) ?></p>
                                        <span class="text-muted" style="font-size: 9px;"><?= date('H:i', strtotime($n['created_at'])) ?></span>
                                    </div>
                                    <p class="mb-1 text-muted" style="font-size: 0.8rem;">
                                        <strong><?= $type ?>:</strong> <?= htmlspecialchars($n['item_name']) ?>
                                    </p>
                                    <?php if(!empty($n['notes'])): ?>
                                        <div class="bg-light p-1 px-2 rounded small text-muted italic" style="font-size: 0.75rem; border-left: 3px solid #dee2e6;">
                                            "<?= htmlspecialchars($n['notes']) ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <i class="bi bi-check2-circle fs-1 text-muted opacity-25"></i>
                            <p class="text-muted small mt-2">All caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="/cecsms/divisions/returned_assets.php" class="dropdown-item text-center p-2 small fw-bold text-primary bg-light border-top">
                    View All Approvals
                </a>
            </div>
        </div>
            <div class="d-none d-sm-flex align-items-center gap-2 text-muted small border-end pe-3">
                <i class="bi bi-calendar-event"></i>
                <?= date('D, M j, Y') ?>
            </div>

            <div class="dropdown">
                <div class="user-profile shadow-sm" data-bs-toggle="dropdown">
                    <div class="text-end d-none d-md-block">
                        <?php $username = $_SESSION['username'] ?? 'User'; ?>
                        <p class="small fw-bold mb-0"><?= htmlspecialchars($username) ?></p>
                        <?php $role = $_SESSION['role'] ?? 'User'; ?>
                        <span class="badge bg-emerald-soft text-success" style="font-size: 9px;">
                            <?= htmlspecialchars($role) ?>
                        </span>
                    </div>
                    <div class="avatar bg-light border rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                        <i class="bi bi-person text-success"></i>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li>
                        <a class="dropdown-item py-2 text-danger fw-bold" href="../admin/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <div class="animate-fade-in">
        <?php if (isset($content)) echo $content; ?>
    </div>

    
</main>
<?php if(isset($modal_html)) echo $modal_html; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Mobile Toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if(menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }

    // Auto-scroll to active link
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

    // Back button cache fix
    window.onpageshow = function(event) {
        if (event.persisted) { window.location.reload(); }
    };
</script>
<?php include "../includes/notify.php"; ?>
</body>
</html>