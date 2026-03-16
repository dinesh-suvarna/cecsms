<?php
// Mocking session for testing; ensure session_start() is at the top of your actual file
$role = $_SESSION["role"] ?? 'User'; 
if (!isset($page_title)) $page_title = "Service Dashboard";

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> | Service Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 270px;
            --primary-indigo: #6366f1;
        }

        /* SaaS Themes */
        [data-bs-theme="light"] {
            --bg-body: #f8fafc;
            --sidebar-bg: #ffffff;
            --sidebar-text: #64748b;
            --topbar-bg: #ffffff;
            --border-color: #e2e8f0;
        }

        [data-bs-theme="dark"] {
            --bg-body: #0f172a;
            --sidebar-bg: #1e293b;
            --sidebar-text: #94a3b8;
            --topbar-bg: #1e293b;
            --border-color: #334155;
        }

        body {
            background: var(--bg-body);
            font-family: 'Inter', sans-serif;
            transition: 0.3s;
        }

        /* SIDEBAR */
        #sidebar {
    width: 270px; /* Fixed width */
    min-height: 100vh;
    background: var(--sidebar-bg);
    border-right: 1px solid var(--border-color);
    position: fixed; /* This stays fixed on the left */
    top: 0;
    left: 0;
    z-index: 1040;
}

        .sidebar-logo {
            padding: 1.5rem;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary-indigo);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-label {
            padding: 1.2rem 1.5rem .5rem;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .05rem;
            font-weight: 700;
            color: var(--sidebar-text);
            opacity: .6;
        }

        #sidebar .nav-link {
            color: var(--sidebar-text);
            padding: .75rem 1.5rem;
            margin: .2rem 1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: .9rem;
            font-weight: 500;
            transition: .2s;
        }

        #sidebar .nav-link:hover {
            background: rgba(99, 102, 241, .1);
            color: var(--primary-indigo);
        }

        #sidebar .nav-link.active {
            background: var(--primary-indigo);
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(99, 102, 241, .3);
        }

        /* MAIN CONTENT AREA */
        .main-content {
    /* This is the magic line that fixes the overlap */
    margin-left: 270px; 
    padding: 1.5rem;
    min-height: 100vh;
    transition: 0.3s;
}

        .topbar {
            background: var(--topbar-bg);
            border-radius: 16px;
            padding: .75rem 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px rgba(0,0,0,.05);
        }

        /* On mobile, remove the margin so it fills the screen */
@media (max-width: 992px) {
    .main-content {
        margin-left: 0;
    }
    #sidebar {
        margin-left: -270px;
    }
    #sidebar.active {
        margin-left: 0;
    }
}
    </style>
</head>
<body>

<nav id="sidebar">
    <div class="sidebar-logo">
        <div class="bg-primary text-white rounded-3 px-2 py-1">
            <i class="bi bi-gear-wide-connected"></i>
        </div>
        <span>Service<span class="text-dark">Manager</span></span>
    </div>

    <div class="section-label">Main Menu</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?= ($current_page=='index.php')?'active':'' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
    </ul>

    <div class="section-label">Service Operations</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="add_service.php" class="nav-link <?= ($current_page=='add_service.php')?'active':'' ?>">
                <i class="bi bi-plus-circle"></i> Add Service
            </a>
        </li>
        <li class="nav-item">
            <a href="vendor.php" class="nav-link <?= ($current_page=='vendor.php')?'active':'' ?>">
                <i class="bi bi-building-add"></i> Add Vendor
            </a>
        </li>
        <li class="nav-item">
            <a href="view_services.php" class="nav-link <?= ($current_page=='view_services.php')?'active':'' ?>">
                <i class="bi bi-list-ul"></i> View Services
            </a>
        </li>
    </ul>

    <div class="section-label">Reports</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="export_excel.php" class="nav-link <?= ($current_page=='export_excel.php')?'active':'' ?>">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </a>
        </li>
    </ul>

    <div class="mt-auto p-3">
        <a href="logout.php" class="btn btn-outline-danger w-100 rounded-3 btn-sm">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </div>
</nav>

<div class="main-content">
    <header class="topbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-light d-lg-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="d-flex align-items-center gap-2 fw-medium">
                    <a href="/cecsms/index.php" class="text-dark fs-5 text-decoration-none" title="Go to Dashboard">
                <i class="bi bi-house-door"></i>
            </a>
                   
        </div>
            <div>
                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($page_title) ?></h6>
                <small class="text-muted" style="font-size:11px;">
                    <i class="bi bi-calendar3 me-1"></i> <?= date("d M Y"); ?>
                </small>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link text-muted p-0" id="themeToggler">
                <i class="bi bi-moon-stars-fill fs-5" id="themeIcon"></i>
            </button>
            <div class="vr mx-1 opacity-25"></div>
            <div class="dropdown">
                <div class="d-flex align-items-center gap-2" data-bs-toggle="dropdown" style="cursor:pointer">
                    <div class="text-end d-none d-md-block">
                        <div class="fw-bold small mb-0"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
                        <span class="badge bg-primary text-white" style="font-size:10px;"><?= $role ?></span>
                    </div>
                    <div class="bg-light rounded-circle p-2 border shadow-sm">
                        <i class="bi bi-person"></i>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-3">
                    <li><a class="dropdown-item py-2" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="container-fluid p-0">
        <?php if(isset($content)) echo $content; ?>
    </main>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme logic
    const themeToggler = document.getElementById('themeToggler');
    const themeIcon = document.getElementById('themeIcon');
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('service_theme') || 'light';

    html.setAttribute('data-bs-theme', savedTheme);
    updateTheme(savedTheme);

    themeToggler.addEventListener('click', () => {
        const current = html.getAttribute('data-bs-theme');
        const newTheme = current === 'light' ? 'dark' : 'light';
        html.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('service_theme', newTheme);
        updateTheme(newTheme);
    });

    function updateTheme(theme) {
        themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5 text-warning';
    }

    // Mobile Sidebar Toggle
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('active');
    });
</script>
