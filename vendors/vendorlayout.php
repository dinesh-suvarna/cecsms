<?php
require_once "../admin/auth.php"; 
$role = $_SESSION["role"] ?? 'User'; 

if (!isset($page_title)) $page_title = "Vendor Management";

// --- CACHE CONTROL ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> | StockFlow Vendors</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --sb-width: 290px;
            --primary-accent: #3b82f6; /* Blue accent for Vendors */
            --bg-body: #f8fafc;
            --sidebar-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: var(--text-main); }

        /* --- SIDEBAR --- */
        #sidebar {
            width: var(--sb-width); height: 100vh; position: fixed;
            top: 0; left: 0; background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color); z-index: 1030;
            display: flex; flex-direction: column;
        }

        .sidebar-brand {
            padding: 1.5rem; display: flex; align-items: center; gap: 12px;
            font-weight: 800; font-size: 1.35rem; color: var(--primary-accent); text-decoration: none;
        }

        .nav-group-label {
            padding: 1.5rem 1.5rem 0.5rem; font-size: 0.78rem; text-transform: uppercase;
            letter-spacing: 0.08rem; font-weight: 700; color: var(--text-muted);
        }

        #sidebar .nav-link {
            margin: 0.2rem 1rem; padding: 0.85rem 1.2rem; color: var(--text-muted);
            border-radius: 10px; display: flex; align-items: center; gap: 12px;
            font-weight: 600; transition: all 0.2s; text-decoration: none;
        }

        #sidebar .nav-link:hover { background: #f1f5f9; color: var(--primary-accent); }

        #sidebar .nav-link.active {
            background: var(--primary-accent); color: #ffffff !important;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.25);
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

        /* --- MAIN CONTENT --- */
        .main-wrapper { margin-left: var(--sb-width); padding: 1.5rem; min-height: 100vh; }

        .top-navbar {
            background: #fff; border: 1px solid var(--border-color);
            border-radius: 16px; padding: 0.8rem 1.5rem; margin-bottom: 1.5rem;
            display: flex; justify-content: space-between; align-items: center;
        }

        .user-profile { display: flex; align-items: center; gap: 10px; padding: 5px 12px; border-radius: 12px; border: 1px solid var(--border-color); }

        .bg-blue-soft { background-color: rgba(59, 130, 246, 0.1); }

        @media (max-width: 992px) {
            #sidebar { transform: translateX(-100%); }
            .main-wrapper { margin-left: 0; }
            #sidebar.show { transform: translateX(0); }
        }
    </style>
</head>
<body>

<nav id="sidebar">
    <a href="../index.php" class="sidebar-brand">
        <div class="bg-primary text-white rounded-3 px-2 py-1 shadow-sm">
            <i class="bi bi-people-fill"></i>
        </div>
        <span>Stock<span class="text-dark">Vendors</span></span>
    </a>

    <div id="sidebarScrollArea" class="overflow-y-auto flex-grow-1">
    <div class="nav-group-label">General</div>
        <div class="nav flex-column">
            <a href="../vendors/vendor_dashboard.php" class="nav-link <?= ($current_page == 'vendor_dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
        </div>
        <div class="nav-group-label">Core Actions</div>
        <div class="nav flex-column">
            <a href="vendor_manager.php" class="nav-link <?= ($current_page == 'vendor_manager.php') ? 'active' : '' ?>">
                <i class="bi bi-person-plus"></i> Add Vendor
            </a>
            <a href="view_vendors.php" class="nav-link <?= ($current_page == 'view_vendors.php') ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i> View Vendors
            </a>
        </div>

        <div class="nav-group-label">Relationship Management</div>
        <div class="nav flex-column">
            <!-- Details is usually triggered from the View list, but kept here for logical grouping -->
            <a href="vendor_details.php" class="nav-link <?= ($current_page == 'vendor_details.php') ? 'active' : '' ?>">
                <i class="bi bi-info-circle"></i> Vendor Details
            </a>
        </div>

        <?php if ($role === 'SuperAdmin'): ?>
        <div class="nav-group-label">Reports & Analytics</div>
        <div class="nav flex-column">
            <a href="vendor_performance.php" class="nav-link">
                <i class="bi bi-bar-chart-steps"></i> Supply Performance
            </a>
            <a href="purchase_history.php" class="nav-link">
                <i class="bi bi-receipt"></i> Global Purchase Ledger
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="p-3 border-top mt-auto">
        <a href="../admin/logout.php" class="btn btn-outline-danger w-100 rounded-pill btn-sm fw-bold">
            <i class="bi bi-power me-2"></i> Logout
        </a>
    </div>
</nav>

<main class="main-wrapper">
    <header class="top-navbar shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none border-0 shadow-sm rounded-3" id="menuToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <a href="/cecsms/index.php" class="nav-home-icon" title="Go to Dashboard">
            <i class="bi bi-house-door"></i>
        </a>
            <div>
                <h5 class="mb-0 fw-bold text-dark lh-1"><?= htmlspecialchars($page_title) ?></h5>
                <p class="text-muted mb-0 d-none d-md-block" style="font-size: 11px;">
                    Managing official supplier profiles and procurement history.
                </p>
            </div>
        </div>

        <div class="dropdown">
            <div class="user-profile bg-white shadow-sm" data-bs-toggle="dropdown" style="cursor: pointer;">
                <div class="text-end d-none d-md-block">
                    <p class="small fw-bold mb-0"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                    <span class="badge bg-blue-soft text-primary" style="font-size: 9px;"><?= $role ?></span>
                </div>
                <div class="avatar bg-light border rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                    <i class="bi bi-person text-primary"></i>
                </div>
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <li><a class="dropdown-item py-2 text-danger fw-bold" href="../admin/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="content-body">
        <?php if (isset($content)) echo $content; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if(menuToggle) {
        menuToggle.addEventListener('click', () => { sidebar.classList.toggle('show'); });
    }
</script>
</body>
</html>