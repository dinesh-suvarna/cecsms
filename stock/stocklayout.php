<?php
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
            --sb-width: 270px;
            --primary-accent: #10b981; /* SaaS Emerald */
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
            z-index: 1050;
            display: flex;
            flex-direction: column;
        }

        .overflow-y-auto {
            overflow-y: auto;
            scrollbar-width: thin;
            max-height: 100vh;
        }

        .sidebar-brand {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--primary-accent);
            text-decoration: none;
        }

        .nav-group-label {
            padding: 1.5rem 1.5rem 0.5rem;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.08rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        .nav-link {
            margin: 0.2rem 1rem;
            padding: 0.7rem 1rem;
            color: var(--text-muted);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: var(--primary-accent);
            transform: translateX(4px);
        }

        .nav-link.active {
            background: var(--primary-accent);
            color: #ffffff !important;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.25);
        }

        /* --- MAIN CONTENT --- */
        .main-wrapper {
            margin-left: var(--sb-width);
            min-height: 100vh;
            padding: 1.5rem;
            transition: margin 0.3s ease-in-out;
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
            transition: all 0.2s ease-in-out;
            border: 1px solid var(--border-color);
        }

        .nav-home-icon:hover {
            background-color: var(--primary-accent);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            border-color: var(--primary-accent);
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
                <i class="bi bi-speedometer2"></i> Dashboard
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
            <div class="d-none d-sm-flex align-items-center gap-2 text-muted small border-end pe-3">
                <i class="bi bi-calendar-event"></i>
                <?= date('D, M j, Y') ?>
            </div>

            <div class="dropdown">
                <div class="user-profile shadow-sm" data-bs-toggle="dropdown">
                    <div class="text-end d-none d-md-block">
                        <?php $username = $_SESSION['username'] ?? 'User'; ?>
                        <p class="small fw-bold mb-0"><?= htmlspecialchars($username) ?></p>
                        <span class="badge bg-emerald-soft text-success" style="font-size: 9px;">ADMIN</span>
                    </div>
                    <div class="avatar bg-light border rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                        <i class="bi bi-person text-success"></i>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li>
                        <a class="dropdown-item py-2 text-danger fw-bold" href="../logout.php">
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