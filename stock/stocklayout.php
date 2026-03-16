<?php
if (!isset($page_title)) $page_title = "Stock Dashboard";

// --- FIX FOR "NO FILE/REFRESH" ISSUE ---
// This tells the browser NOT to store a copy of the page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>


<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | Stock Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root { --sidebar-width: 270px; --primary-indigo: #6366f1; }
        
        [data-bs-theme="light"] { --bg-body: #f8fafc; --sidebar-bg: #ffffff; --text-muted: #64748b; --border-color: #e2e8f0; }
        [data-bs-theme="dark"] { --bg-body: #0f172a; --sidebar-bg: #1e293b; --text-muted: #94a3b8; --border-color: #334155; }

        body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; transition: 0.3s; }

        /* SIDEBAR */
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            position: fixed;
            transition: 0.3s;
            z-index: 1040;
        }

        .nav-link {
            color: var(--text-muted);
            padding: 0.7rem 1.2rem;
            margin: 0.2rem 0.8rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .nav-link:hover { background: rgba(99, 102, 241, 0.1); color: var(--primary-indigo); }
        .nav-link.active { background: var(--primary-indigo); color: #fff !important; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }

        .section-label { padding: 1.5rem 1.2rem 0.5rem; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; color: var(--text-muted); opacity: 0.7; }

        /* CONTENT AREA */
        .main-content { margin-left: var(--sidebar-width); padding: 1.5rem; transition: 0.3s; }
        
        .top-navbar {
            background: var(--sidebar-bg);
            border-radius: 12px;
            padding: 0.8rem 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        @media (max-width: 992px) {
            #sidebar { margin-left: calc(-1 * var(--sidebar-width)); }
            .main-content { margin-left: 0; }
            #sidebar.active { margin-left: 0; }
        }
    </style>
</head>
<body>

<nav id="sidebar">
    <div class="p-4 d-flex align-items-center gap-2">
        <div class="bg-primary text-white rounded-3 px-2 py-1 shadow-sm"><i class="bi bi-box-seam"></i></div>
        <span class="fw-bold fs-5">StockFlow</span>
    </div>

    <div class="section-label">General</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
    </ul>

    <div class="section-label">Inventory</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="add_stock_details.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='add_stock_details.php'?'active':'' ?>">
                <i class="bi bi-plus-circle"></i> Add Stock Details
            </a>
        </li>
        <li class="nav-item">
            <a href="view_stock_details.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='view_stock_details.php'?'active':'' ?>">
                <i class="bi bi-list-ul"></i> View Stock Details
            </a>
        </li>
    </ul>

    <div class="section-label">Logistics</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="dispatch.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='dispatch.php'?'active':'' ?>">
                <i class="bi bi-truck"></i> Dispatch Stock
            </a>
        </li>
        <li class="nav-item">
            <a href="dispatch_report.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='dispatch_report.php'?'active':'' ?>">
                <i class="bi bi-file-earmark-text"></i> Dispatch Report
            </a>
        </li>
    </ul>

</nav>

<div class="main-content">
    <header class="top-navbar d-flex align-items-center justify-content-between shadow-sm">
        <div class="d-flex align-items-center gap-2 fw-medium">
            <a href="/cecsms/index.php" class="text-dark fs-5 text-decoration-none" title="Go to Dashboard">
        <i class="bi bi-house-door"></i>
    </a>
            <span><?= $page_title ?></span>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light rounded-pill px-3 py-1 border d-flex align-items-center gap-2" id="themeToggler">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                <span class="small fw-bold d-none d-sm-inline">Theme</span>
            </button>

            <div class="dropdown">
                <button class="btn btn-white border rounded-circle shadow-sm" data-bs-toggle="dropdown" style="width: 38px; height: 38px;">
                    <i class="bi bi-person"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-3">
                    <li><a class="dropdown-item text-danger py-2" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="container-fluid p-0">
        <?php if(isset($content)) echo $content; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Theme Switcher Logic
    const themeToggler = document.getElementById('themeToggler');
    const themeIcon = document.getElementById('themeIcon');
    const htmlElement = document.documentElement;

    const savedTheme = localStorage.getItem('theme') || 'light';
    htmlElement.setAttribute('data-bs-theme', savedTheme);
    updateThemeUI(savedTheme);

    themeToggler.addEventListener('click', () => {
        const currentTheme = htmlElement.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        htmlElement.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeUI(newTheme);
    });

    function updateThemeUI(theme) {
        themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill text-warning';
    }

    // Sidebar Mobile Toggle
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // --- CRITICAL FIX FOR DASHBOARD NO FILE/REFRESH ISSUE ---
    // This forces a reload if the page is loaded from cache (hitting "Back" button)
    (function () {
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    })();
</script>
<?php include "../includes/notify.php"; ?>
</body>
</html>